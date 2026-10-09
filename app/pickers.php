<?php
declare(strict_types=1);

/**
 * app/pickers.php — the registry of what a form can pick (app/picker.php has the contract). One entry per record kind.
 * `search` reuses the feature's own query (reads go through the mcp_* views, so a source lists only what the screen's
 * user could see in the select it replaced), `label` names one record, `params` are the only filter keys the endpoint
 * forwards (integers; a field's `include` selectors must name them).
 *
 * Projects has no role names: `gate` is the source's authorization — fn(PDO, array $params): bool — answered by the same
 * helpers the screens use (can_see_project, has_right_in). A refused gate answers 403 as a fragment (html/pick/index.php).
 * Rows: id, label, detail (one muted line), badge + color (the status-color vocabulary) — optional.
 */

/** A member row as the select showed it: the name, "(agent)" for an agent; the job title is the detail line. */
$memberRow = static fn(array $m, ?string $badge = null): array => [
    'id' => (int) $m['member_id'],
    'label' => $m['display_name'] . ($m['member_kind'] === 'agent' ? ' (agent)' : ''),
    'detail' => implode(' · ', array_filter([$m['job_title'] ?? null, !empty($m['is_external']) ? 'external' : null])),
    'badge' => $badge, 'color' => 'info',
];
$memberLabel = static function (PDO $pdo, int $id): ?string {
    $st = $pdo->prepare("SELECT display_name, member_kind FROM mcp_members WHERE member_id = :id AND status = 'active'");
    $st->execute(['id' => $id]);
    $m = $st->fetch();
    return $m ? $m['display_name'] . ($m['member_kind'] === 'agent' ? ' (agent)' : '') : null;
};

return [
    // Members and agents (an assignee, a lead, a default assignee). With project_id the project's team comes first, badged
    // Team, as the assignee select listed it — and the project must be one the caller may see.
    'member' => [
        'title' => 'People', 'noun' => 'member', 'params' => ['project_id'],
        'gate' => static fn(PDO $pdo, array $params): bool => ($params['project_id'] ?? null) === null || can_see_project((int) $params['project_id']),
        'search' => static function (PDO $pdo, string $q, array $params, int $page) use ($memberRow): array {
            require_once __DIR__ . '/features/projects/queries.php';
            $result = find_members($pdo, $q, $page, $params['project_id'] ?? null);
            $result['rows'] = array_map(static fn(array $m): array => $memberRow($m, (int) $m['on_team'] === 1 ? 'Team' : null), $result['rows']);
            return $result;
        },
        'label' => $memberLabel,
    ],
    // Members not yet on a project's team — "Add to the team". project_id is required; project.admin in it, as the screen is.
    'team_candidate' => [
        'title' => 'Add to the team', 'noun' => 'member', 'params' => ['project_id'],
        'gate' => static fn(PDO $pdo, array $params): bool => ($params['project_id'] ?? null) !== null && has_right_in('project.admin', (int) $params['project_id']),
        'search' => static function (PDO $pdo, string $q, array $params, int $page) use ($memberRow): array {
            require_once __DIR__ . '/features/team/queries.php';
            $result = find_team_candidates($pdo, (int) $params['project_id'], $q, $page);
            $result['rows'] = array_map($memberRow, $result['rows']);
            return $result;
        },
        'label' => $memberLabel,
    ],
    // Who has acted in the trail the caller may read — the activity screen's "Who" filter.
    'actor' => [
        'title' => 'Who', 'noun' => 'person', 'params' => [],
        'gate' => static fn(PDO $pdo, array $params): bool => true,
        'search' => static function (PDO $pdo, string $q, array $params, int $page): array {
            require_once __DIR__ . '/features/activity/queries.php';
            $result = find_activity_actors($pdo, $q, $page);
            $result['rows'] = array_map(static fn(array $m): array => [
                'id' => (int) $m['member_id'], 'label' => $m['display_name'] . ($m['member_kind'] === 'agent' ? ' (agent)' : ''),
            ], $result['rows']);
            return $result;
        },
        'label' => $memberLabel,
    ],
    // Active projects the caller can see (a saved filter's project) — mcp_projects is the one visibility rule.
    'project' => [
        'title' => 'Projects', 'noun' => 'project', 'params' => [],
        'gate' => static fn(PDO $pdo, array $params): bool => true,
        'search' => static function (PDO $pdo, string $q, array $params, int $page): array {
            require_once __DIR__ . '/features/projects/queries.php';
            $found = find_projects($pdo, ['q' => $q, 'status' => 'active'], 'key', $page, 25);
            return [
                'rows' => array_map(static fn(array $p): array => [
                    'id' => (int) $p['project_id'], 'label' => $p['key'] . ' · ' . $p['name'], 'detail' => $p['department_name'] ?? null,
                ], $found['rows']),
                'total' => $found['total'], 'page' => $page, 'page_size' => 25, 'pages' => max(1, (int) ceil($found['total'] / 25)),
            ];
        },
        'label' => static function (PDO $pdo, int $id): ?string {
            $st = $pdo->prepare('SELECT key, name FROM mcp_projects WHERE project_id = :id');
            $st->execute(['id' => $id]);
            $p = $st->fetch();
            return $p ? $p['key'] . ' · ' . $p['name'] : null;
        },
    ],
    // Projects the caller administers, other than project_id — "Move to another project".
    'project_admin' => [
        'title' => 'Move to', 'noun' => 'project', 'params' => ['project_id'],
        'gate' => static fn(PDO $pdo, array $params): bool => true,
        'search' => static function (PDO $pdo, string $q, array $params, int $page): array {
            require_once __DIR__ . '/features/epics/queries.php';
            $result = find_projects_i_administer($pdo, $q, $params['project_id'] ?? null, $page);
            $result['rows'] = array_map(static fn(array $p): array => ['id' => (int) $p['project_id'], 'label' => $p['key'] . ' · ' . $p['name']], $result['rows']);
            return $result;
        },
        'label' => static function (PDO $pdo, int $id): ?string {
            $st = $pdo->prepare('SELECT key, name FROM mcp_projects WHERE project_id = :id AND i_administer');
            $st->execute(['id' => $id]);
            $p = $st->fetch();
            return $p ? $p['key'] . ' · ' . $p['name'] : null;
        },
    ],
];
