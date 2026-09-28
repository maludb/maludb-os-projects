<?php
declare(strict_types=1);
/**
 * The board — the active sprint's issues (scrum) or every open issue (kanban) by column and swimlane.
 * Reads go through the mcp_* views; the config is written in one transaction (a column's identity is
 * its name; delete + insert). Slice 3 (docs/build-specs/board.md).
 */
require_once dirname(__DIR__) . '/projects/queries.php';

const BOARD_LANES = ['none', 'assignee', 'epic'];
const BOARD_DONE_DAYS = 3;

/**
 * @param array $filters assignee (id|me), epic (id), type, q, me (the caller's id)
 * @return array{project: array, mode: string, lane: string, sprint: ?array, statuses: array<int, array>, columns: array, lanes: array, total: int}
 */
function find_board(PDO $pdo, int $projectId, array $filters, string $lane): array
{
    $project = find_project($pdo, $projectId);
    if ($project === null) {
        return ['project' => [], 'mode' => 'scrum', 'lane' => 'none', 'sprint' => null, 'statuses' => [], 'columns' => [], 'lanes' => [], 'total' => 0];
    }
    $mode = (string) $project['board_mode'];
    $lane = in_array($lane, BOARD_LANES, true) ? $lane : (string) $project['swimlane'];
    $st = $pdo->prepare('SELECT * FROM mcp_workflow_statuses WHERE project_id = :p AND archived_at IS NULL ORDER BY sort_order');
    $st->execute(['p' => $projectId]);
    $statuses = [];
    foreach ($st->fetchAll() as $s) {
        $s['can_move_to'] = present_pg_int_array($s['can_move_to'] ?? null);
        $statuses[(int) $s['status_id']] = $s;
    }
    $st = $pdo->prepare('SELECT * FROM mcp_board_columns WHERE project_id = :p ORDER BY sort_order, column_id');
    $st->execute(['p' => $projectId]);
    $columns = [];
    $statusColumn = [];
    foreach ($st->fetchAll() as $c) {
        $c['status_ids'] = present_pg_int_array($c['status_ids'] ?? null);
        $c['cards'] = [];
        $c['count'] = 0;
        foreach ($c['status_ids'] as $sid) {
            $statusColumn[$sid] = (int) $c['column_id'];
        }
        $columns[(int) $c['column_id']] = $c;
    }
    $sprint = null;
    $where = ['i.project_id = :p', 'i.parent_issue_id IS NULL'];
    $args = ['p' => $projectId];
    if ($mode === 'kanban') {
        $where[] = "(i.status_category <> 'done' OR i.resolved_at >= now() - make_interval(days => :dd))";
        $args['dd'] = BOARD_DONE_DAYS;
    } else {
        if ($project['active_sprint_id'] !== null) {
            $sq = $pdo->prepare('SELECT * FROM mcp_sprints WHERE sprint_id = :s');
            $sq->execute(['s' => (int) $project['active_sprint_id']]);
            $sprint = $sq->fetch() ?: null;
        }
        $where[] = 'i.sprint_id = :sprint';
        $args['sprint'] = $sprint !== null ? (int) $sprint['sprint_id'] : -1;
    }
    $assignee = (string) ($filters['assignee'] ?? '');
    if ($assignee === 'me') {
        $where[] = 'i.assignee_member_id = :me';
        $args['me'] = (int) ($filters['me'] ?? 0);
    } elseif ($assignee === 'none') {
        $where[] = 'i.assignee_member_id IS NULL';
    } elseif (ctype_digit($assignee)) {
        $where[] = 'i.assignee_member_id = :assignee';
        $args['assignee'] = (int) $assignee;
    }
    if (!empty($filters['epic'])) {
        $where[] = 'i.epic_id = :epic';
        $args['epic'] = (int) $filters['epic'];
    }
    if (in_array($filters['type'] ?? '', ISSUE_TYPES, true)) {
        $where[] = 'i.type = :type';
        $args['type'] = $filters['type'];
    }
    $q = trim((string) ($filters['q'] ?? ''));
    if ($q !== '') {
        $where[] = '(i.key ILIKE :qk OR i.summary ILIKE :qs)';
        $args += ['qk' => '%' . mb_substr($q, 0, 100) . '%', 'qs' => '%' . mb_substr($q, 0, 100) . '%'];
    }
    $st = $pdo->prepare('SELECT i.* FROM mcp_issues i WHERE (' . implode(') AND (', $where) . ') ORDER BY i.rank, i.number LIMIT 500');
    $st->execute($args);
    $total = 0;
    $laneKeys = [];
    foreach ($st->fetchAll() as $i) {
        $sid = (int) $i['status_id'];
        $cid = $statusColumn[$sid] ?? null;
        if ($cid === null) {
            continue;                              // a status in no column: not on the board (the settings form refuses that state)
        }
        $i['can_move_to'] = $statuses[$sid]['can_move_to'] ?? [];
        [$laneKey, $laneLabel, $laneKind] = match ($lane) {
            'assignee' => $i['assignee_member_id'] !== null ? ['a' . (int) $i['assignee_member_id'], (string) $i['assignee_name'], (string) $i['assignee_kind']] : ['zz-none', 'Unassigned', ''],
            'epic' => $i['epic_id'] !== null ? ['e' . (int) $i['epic_id'], (string) $i['epic_name'], 'epic'] : ['zz-none', 'No epic', ''],
            default => ['all', '', ''],
        };
        $i['lane'] = $laneKey;
        $laneKeys[$laneKey] = ['key' => $laneKey, 'label' => $laneLabel, 'kind' => $laneKind, 'count' => ($laneKeys[$laneKey]['count'] ?? 0) + 1];
        $columns[$cid]['cards'][] = $i;
        $columns[$cid]['count']++;
        $total++;
    }
    uasort($laneKeys, static fn (array $a, array $b): int => [$a['key'] === 'zz-none', $a['label']] <=> [$b['key'] === 'zz-none', $b['label']]);
    return ['project' => $project, 'mode' => $mode, 'lane' => $lane, 'sprint' => $sprint, 'statuses' => $statuses,
            'columns' => array_values($columns), 'lanes' => array_values($laneKeys), 'total' => $total];
}

/** The board's configuration for the form: columns with their statuses and limits, the swimlane, the mode, every status. */
function find_board_config(PDO $pdo, int $projectId): array
{
    $project = find_project($pdo, $projectId);
    $st = $pdo->prepare('SELECT * FROM mcp_board_columns WHERE project_id = :p ORDER BY sort_order, column_id');
    $st->execute(['p' => $projectId]);
    $columns = array_map(static function (array $c): array {
        return ['column_id' => (int) $c['column_id'], 'name' => (string) $c['name'], 'wip_limit' => $c['wip_limit'] !== null ? (int) $c['wip_limit'] : null,
                'status_ids' => present_pg_int_array($c['status_ids'] ?? null)];
    }, $st->fetchAll());
    $st = $pdo->prepare('SELECT status_id, name, category, sort_order FROM mcp_workflow_statuses WHERE project_id = :p AND archived_at IS NULL ORDER BY sort_order');
    $st->execute(['p' => $projectId]);
    return ['columns' => $columns, 'swimlane' => (string) ($project['swimlane'] ?? 'none'), 'board_mode' => (string) ($project['board_mode'] ?? 'scrum'), 'statuses' => $st->fetchAll()];
}

/**
 * Replace the board's columns in one transaction (the caller opened it): $columns = [['name', 'status_ids' => [..], 'wip_limit'], …]
 * in order. The rule — every non-archived status in exactly one column — was validated by the handler; the unique index
 * board_column_statuses_one_column enforces "one column" regardless. Returns the config after.
 */
function save_board_config(PDO $pdo, int $projectId, array $columns, string $swimlane, string $mode, int $actorId): array
{
    $pdo->prepare('DELETE FROM board_columns WHERE project_id = :p')->execute(['p' => $projectId]);
    $ins = $pdo->prepare('INSERT INTO board_columns (project_id, name, sort_order, wip_limit) VALUES (:p, :n, :o, :w) RETURNING id');
    $map = $pdo->prepare('INSERT INTO board_column_statuses (column_id, status_id) VALUES (:c, :s)');
    foreach (array_values($columns) as $n => $c) {
        $ins->execute(['p' => $projectId, 'n' => $c['name'], 'o' => $n + 1, 'w' => $c['wip_limit']]);
        $cid = (int) $ins->fetchColumn();
        foreach ($c['status_ids'] as $sid) {
            $map->execute(['c' => $cid, 's' => (int) $sid]);
        }
    }
    $pdo->prepare('UPDATE projects SET swimlane = :l, board_mode = :m WHERE id = :p')->execute(['l' => $swimlane, 'm' => $mode, 'p' => $projectId]);
    return find_board_config($pdo, $projectId);
}
