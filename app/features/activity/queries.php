<?php
declare(strict_types=1);
/**
 * The trail — what the member may see (mcp_activity_log decides: their own rows, their projects' rows,
 * everything for an admin), newest first, 50 a page, by project, member, action prefix, period or one record.
 */
const ACTIVITY_PER_PAGE = 50;
const ACTIVITY_SINCE_ALLOWED = [1, 7, 30, 90];

/**
 * @param array $filters project (key), member (actor id), action (prefix such as issue.), since (days), entity ("issue:KEY-1" or "kind:id"),
 *                       entity_type + entity_id (the older form the issue view links with)
 * @return array{rows: array, more: bool}
 */
function find_activity(PDO $pdo, array $filters, int $page = 1, int $per = ACTIVITY_PER_PAGE): array
{
    $where = ["a.action <> 'screen.view'"];
    $args = [];
    $projectKey = strtoupper(trim((string) ($filters['project'] ?? '')));
    if (preg_match('/^[A-Z][A-Z0-9]{1,9}$/', $projectKey)) {
        $where[] = "((a.entity_type = 'project' AND a.entity_id = (SELECT project_id FROM mcp_projects WHERE key = :pk))
                     OR (a.entity_type = 'issue' AND a.entity_id IN (SELECT issue_id FROM mcp_issues WHERE project_key = :pk2))
                     OR (a.entity_type = 'comment' AND a.entity_id IN (SELECT c.comment_id FROM mcp_comments c JOIN mcp_issues i ON i.issue_id = c.issue_id WHERE i.project_key = :pk5))
                     OR (a.entity_type = 'sprint' AND a.entity_id IN (SELECT sprint_id FROM mcp_sprints WHERE project_key = :pk3))
                     OR (a.entity_type = 'epic' AND a.entity_id IN (SELECT epic_id FROM mcp_epics WHERE project_key = :pk4)))";
        $args += ['pk' => $projectKey, 'pk2' => $projectKey, 'pk5' => $projectKey, 'pk3' => $projectKey, 'pk4' => $projectKey];
    }
    if (!empty($filters['member'])) {
        $where[] = 'a.actor_member_id = :member';
        $args['member'] = (int) $filters['member'];
    }
    $action = trim((string) ($filters['action'] ?? ''));
    if ($action !== '' && preg_match('/^[a-z_]+(\.[a-z_]+)*\.?$/', $action)) {
        $where[] = 'a.action LIKE :action';
        $args['action'] = $action . '%';
    }
    $since = (int) ($filters['since'] ?? 0);
    if (in_array($since, ACTIVITY_SINCE_ALLOWED, true)) {
        $where[] = 'a.occurred_at >= now() - make_interval(days => :since)';
        $args['since'] = $since;
    }
    $entity = trim((string) ($filters['entity'] ?? ''));
    if ($entity !== '' && preg_match('/^([a-z_]{1,40}):(.+)$/', $entity, $m)) {
        [$kind, $ref] = [$m[1], strtoupper($m[2])];
        if ($kind === 'issue' && !ctype_digit($ref)) {
            $where[] = "a.entity_type = 'issue' AND a.entity_id = (SELECT issue_id FROM mcp_issues WHERE key = :ekey)";
            $args['ekey'] = $ref;
        } elseif (ctype_digit($ref)) {
            $where[] = 'a.entity_type = :ekind AND a.entity_id = :eid';
            $args += ['ekind' => $kind, 'eid' => (int) $ref];
        }
    } elseif (($filters['entity_type'] ?? '') !== '' && ($filters['entity_id'] ?? null) !== null && preg_match('/^[a-z_]{1,40}$/', (string) $filters['entity_type'])) {
        $where[] = 'a.entity_type = :ekind AND a.entity_id = :eid';
        $args += ['ekind' => (string) $filters['entity_type'], 'eid' => (int) $filters['entity_id']];
    }
    $st = $pdo->prepare('SELECT a.*, i.key AS issue_key, i.summary AS issue_summary FROM mcp_activity_log a
                           LEFT JOIN mcp_issues i ON a.entity_type = \'issue\' AND i.issue_id = a.entity_id
                          WHERE (' . implode(') AND (', $where) . ') ORDER BY a.occurred_at DESC, a.activity_id DESC LIMIT :lim OFFSET :off');
    foreach ($args as $k => $v) {
        $st->bindValue($k, $v);
    }
    $st->bindValue('lim', $per + 1, PDO::PARAM_INT);
    $st->bindValue('off', max(0, $page - 1) * $per, PDO::PARAM_INT);
    $st->execute();
    $rows = $st->fetchAll();
    return ['rows' => array_slice($rows, 0, $per), 'more' => count($rows) > $per];
}

/** Who has acted, for the filter. */
function activity_actors(PDO $pdo): array
{
    return $pdo->query("SELECT DISTINCT actor_member_id AS member_id, actor_name AS display_name FROM mcp_activity_log WHERE actor_member_id IS NOT NULL AND action <> 'screen.view' ORDER BY actor_name LIMIT 200")->fetchAll();
}
/**
 * activity_actors() matching a search, one page — the record picker's source for the trail's "Who" filter (app/pickers.php).
 * @return array{rows: array, total: int, page: int, page_size: int, pages: int}
 */
function find_activity_actors(PDO $pdo, string $q, int $page = 1, int $per = 25): array
{
    $where = "actor_member_id IS NOT NULL AND action <> 'screen.view'" . ($q !== '' ? ' AND actor_name ILIKE :q' : '');
    $args = $q !== '' ? ['q' => '%' . $q . '%'] : [];
    $count = $pdo->prepare("SELECT count(DISTINCT actor_member_id) FROM mcp_activity_log WHERE {$where}");
    $count->execute($args);
    $total = (int) $count->fetchColumn();
    $st = $pdo->prepare("SELECT DISTINCT actor_member_id AS member_id, actor_name AS display_name, actor_kind AS member_kind FROM mcp_activity_log WHERE {$where}
                          ORDER BY actor_name, actor_member_id LIMIT :lim OFFSET :off");
    foreach ($args as $k => $v) {
        $st->bindValue($k, $v);
    }
    $st->bindValue('lim', $per, PDO::PARAM_INT);
    $st->bindValue('off', max(0, $page - 1) * $per, PDO::PARAM_INT);
    $st->execute();
    return ['rows' => $st->fetchAll(), 'total' => $total, 'page' => $page, 'page_size' => $per, 'pages' => max(1, (int) ceil($total / $per))];
}
/** Where a row's record lives, for the link. */
function activity_record_href(array $r): ?string
{
    $id = $r['entity_id'] !== null ? (int) $r['entity_id'] : null;
    return match ((string) ($r['entity_type'] ?? '')) {
        'issue' => !empty($r['issue_key']) ? '/issues/' . $r['issue_key'] : null,
        'project' => $id !== null ? '/projects/view.php?id=' . $id : null,
        'sprint' => $id !== null ? '/sprints/' . $id : null,
        'epic' => $id !== null ? '/epics/' . $id : null,
        'saved_filter' => '/filters',
        default => null,
    };
}
