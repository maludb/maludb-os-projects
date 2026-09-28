<?php
declare(strict_types=1);
/**
 * Sprints and the backlog (slice 2). Reads go through the mcp_* views; the form's source is the base
 * row behind the sprint.plan gate. The moves — rank, into and out of a sprint, start, close — are one
 * function each; the database's triggers write the sprint's scope events and carry subtasks along.
 */
require_once dirname(__DIR__) . '/issues/queries.php';

const SPRINT_STATES = ['future', 'active', 'closed'];

/** The ranked backlog: top-level issues with no sprint and not done. Filters: q, epic, assignee (id|me|none), type, unestimated. */
function find_backlog(PDO $pdo, int $projectId, array $filters = []): array
{
    $where = ['i.project_id = :p', 'i.parent_issue_id IS NULL', 'i.sprint_id IS NULL', "i.status_category <> 'done'"];
    $args = ['p' => $projectId];
    $q = trim((string) ($filters['q'] ?? ''));
    if ($q !== '') {
        $where[] = '(i.key ILIKE :qk OR i.summary ILIKE :qs)';
        $args += ['qk' => '%' . mb_substr($q, 0, 100) . '%', 'qs' => '%' . mb_substr($q, 0, 100) . '%'];
    }
    if (!empty($filters['epic'])) { $where[] = 'i.epic_id = :epic'; $args['epic'] = (int) $filters['epic']; }
    $assignee = (string) ($filters['assignee'] ?? '');
    if ($assignee === 'me') { $where[] = 'i.assignee_member_id = :me'; $args['me'] = (int) ($filters['me'] ?? 0); }
    elseif ($assignee === 'none') { $where[] = 'i.assignee_member_id IS NULL'; }
    elseif (ctype_digit($assignee)) { $where[] = 'i.assignee_member_id = :a'; $args['a'] = (int) $assignee; }
    if (in_array($filters['type'] ?? '', ISSUE_TYPES, true)) { $where[] = 'i.type = :t'; $args['t'] = $filters['type']; }
    if (!empty($filters['unestimated'])) { $where[] = 'i.story_points IS NULL'; }
    $st = $pdo->prepare('SELECT i.* FROM mcp_issues i WHERE (' . implode(') AND (', $where) . ') ORDER BY i.rank, i.number LIMIT 500');
    $st->execute($args);
    return $st->fetchAll();
}

/** The active sprint first, then the future ones by order — each with its top-level issues by rank and its points. */
function find_sprint_boxes(PDO $pdo, int $projectId): array
{
    $st = $pdo->prepare("SELECT * FROM mcp_sprints WHERE project_id = :p AND state IN ('active', 'future') ORDER BY state = 'active' DESC, sort_order, sprint_id");
    $st->execute(['p' => $projectId]);
    $boxes = $st->fetchAll();
    $issues = $pdo->prepare('SELECT * FROM mcp_issues WHERE sprint_id = :s AND parent_issue_id IS NULL ORDER BY rank, number LIMIT 500');
    foreach ($boxes as &$b) {
        $issues->execute(['s' => (int) $b['sprint_id']]);
        $b['issue_rows'] = $issues->fetchAll();
    }
    unset($b);
    return $boxes;
}

/** Every sprint of the project: active, then future by order, then closed newest first. */
function find_sprints(PDO $pdo, int $projectId): array
{
    $st = $pdo->prepare("SELECT * FROM mcp_sprints WHERE project_id = :p
                         ORDER BY CASE state WHEN 'active' THEN 0 WHEN 'future' THEN 1 ELSE 2 END, CASE WHEN state = 'closed' THEN NULL ELSE sort_order END, closed_at DESC NULLS LAST, sprint_id");
    $st->execute(['p' => $projectId]);
    return $st->fetchAll();
}

function find_sprint(PDO $pdo, int $id): ?array
{
    $st = $pdo->prepare('SELECT * FROM mcp_sprints WHERE sprint_id = :id');
    $st->execute(['id' => $id]);
    return $st->fetch() ?: null;
}

/** The base row — the form's and the handlers' source (the gate has already spoken). */
function find_sprint_for_edit(PDO $pdo, int $id): ?array
{
    $st = $pdo->prepare('SELECT * FROM sprints WHERE id = :id');
    $st->execute(['id' => $id]);
    return $st->fetch() ?: null;
}

/** The sprint's top-level issues by status category, then rank. */
function sprint_issues(PDO $pdo, int $sprintId): array
{
    $st = $pdo->prepare("SELECT * FROM mcp_issues WHERE sprint_id = :s AND parent_issue_id IS NULL
                         ORDER BY CASE status_category WHEN 'in_progress' THEN 0 WHEN 'todo' THEN 1 ELSE 2 END, rank, number LIMIT 500");
    $st->execute(['s' => $sprintId]);
    return $st->fetchAll();
}

function next_sprint_name(PDO $pdo, int $projectId): string
{
    $st = $pdo->prepare('SELECT count(*) FROM mcp_sprints WHERE project_id = :p');
    $st->execute(['p' => $projectId]);
    return 'Sprint ' . ((int) $st->fetchColumn() + 1);
}

/** Create or change a sprint. $fields: project_id (create), name, goal, start_date, end_date, sort_order. */
function upsert_sprint(PDO $pdo, ?int $id, array $fields, int $actorId): array
{
    if ($id === null) {
        $st = $pdo->prepare('INSERT INTO sprints (project_id, name, goal, start_date, end_date, sort_order, created_by)
                             VALUES (:p, :name, :goal, :start_date, :end_date, coalesce(:sort_order, (SELECT coalesce(max(sort_order), 0) + 1 FROM sprints WHERE project_id = :p2)), :by) RETURNING *');
        $st->execute(['p' => $fields['project_id'], 'name' => $fields['name'], 'goal' => $fields['goal'], 'start_date' => $fields['start_date'], 'end_date' => $fields['end_date'],
                      'sort_order' => $fields['sort_order'], 'p2' => $fields['project_id'], 'by' => $actorId]);
        return $st->fetch();
    }
    $st = $pdo->prepare('UPDATE sprints SET name = :name, goal = :goal, start_date = :start_date, end_date = :end_date, sort_order = coalesce(:sort_order, sort_order) WHERE id = :id RETURNING *');
    $st->execute(['id' => $id, 'name' => $fields['name'], 'goal' => $fields['goal'], 'start_date' => $fields['start_date'], 'end_date' => $fields['end_date'], 'sort_order' => $fields['sort_order']]);
    return $st->fetch();
}

/** A future sprint with no issues may go. */
function delete_sprint(PDO $pdo, int $id): bool
{
    $st = $pdo->prepare("DELETE FROM sprints WHERE id = :id AND state = 'future' AND NOT EXISTS (SELECT 1 FROM issues WHERE sprint_id = :id2)");
    $st->execute(['id' => $id, 'id2' => $id]);
    return $st->rowCount() > 0;
}

/**
 * Place an issue between two neighbours (issue ids) in rank order — before = the issue it goes above,
 * after = the issue it goes below; both null = the bottom of its list. Returns [before, after] base rows.
 */
function rank_issue(PDO $pdo, int $issueId, ?int $beforeIssueId, ?int $afterIssueId, int $actorId): array
{
    $before = find_issue_for_edit($pdo, $issueId);
    if ($before === null) {
        throw new RuntimeException('No such issue.');
    }
    $rank = static function (?int $id) use ($pdo): ?string {
        if ($id === null) { return null; }
        $st = $pdo->prepare('SELECT rank FROM issues WHERE id = :id');
        $st->execute(['id' => $id]);
        $r = $st->fetchColumn();
        return $r === false ? null : (string) $r;
    };
    $lo = $rank($afterIssueId);      // the neighbour above (smaller rank)
    $hi = $rank($beforeIssueId);     // the neighbour below (larger rank)
    if ($lo === null && $hi === null) {
        $st = $pdo->prepare('SELECT coalesce(max(rank), 0) + 1000 FROM issues WHERE project_id = :p AND id <> :id');
        $st->execute(['p' => (int) $before['project_id'], 'id' => $issueId]);
        $new = (string) $st->fetchColumn();
    } else {
        $st = $pdo->prepare('SELECT projects_rank_between(:lo, :hi)');
        $st->execute(['lo' => $lo, 'hi' => $hi]);
        $new = (string) $st->fetchColumn();
    }
    $pdo->prepare('UPDATE issues SET rank = :r WHERE id = :id')->execute(['r' => $new, 'id' => $issueId]);
    return [$before, find_issue_for_edit($pdo, $issueId)];
}

/** The top of the list: above the smallest rank in the project. */
function rank_issue_top(PDO $pdo, int $issueId, int $actorId): array
{
    $before = find_issue_for_edit($pdo, $issueId);
    if ($before === null) {
        throw new RuntimeException('No such issue.');
    }
    $st = $pdo->prepare('SELECT coalesce(min(rank), 1000) - 1000 FROM issues WHERE project_id = :p AND id <> :id');
    $st->execute(['p' => (int) $before['project_id'], 'id' => $issueId]);
    $pdo->prepare('UPDATE issues SET rank = :r WHERE id = :id')->execute(['r' => (string) $st->fetchColumn(), 'id' => $issueId]);
    return [$before, find_issue_for_edit($pdo, $issueId)];
}

/** Into a sprint (or NULL = the backlog). The trigger refuses another project's sprint and moves subtasks along. */
function move_issue_to_sprint(PDO $pdo, int $issueId, ?int $sprintId, int $actorId): array
{
    $before = find_issue_for_edit($pdo, $issueId);
    if ($before === null) {
        throw new RuntimeException('No such issue.');
    }
    try {
        $pdo->prepare('UPDATE issues SET sprint_id = :s WHERE id = :id')->execute(['s' => $sprintId, 'id' => $issueId]);
    } catch (PDOException $e) {
        if (str_contains($e->getMessage(), 'another project')) {
            throw new RuntimeException('That sprint belongs to another project.');
        }
        throw $e;
    }
    return [$before, find_issue_for_edit($pdo, $issueId)];
}

/** Start: the commitment is recorded by sprint_start(); its refusals come back as sentences. */
function start_sprint(PDO $pdo, int $id, int $actorId, ?string $start, ?string $end): array
{
    try {
        $st = $pdo->prepare('SELECT sprint_start(:id, :by, coalesce(:start::date, current_date), :end::date)');
        $st->execute(['id' => $id, 'by' => $actorId, 'start' => $start, 'end' => $end]);
    } catch (PDOException $e) {
        throw new RuntimeException(preg_match('/ERROR:\s+([^\n]+)/', $e->getMessage(), $m) ? trim($m[1]) : 'The sprint could not start.');
    }
    return find_sprint_for_edit($pdo, $id) ?? [];
}

/** Close: what was done stays, the rest carries to $carryTo (a future sprint) or the backlog (null). */
function close_sprint(PDO $pdo, int $id, int $actorId, ?int $carryTo): array
{
    try {
        $st = $pdo->prepare('SELECT * FROM sprint_close(:id, :by, :carry)');
        $st->execute(['id' => $id, 'by' => $actorId, 'carry' => $carryTo]);
    } catch (PDOException $e) {
        throw new RuntimeException(preg_match('/ERROR:\s+([^\n]+)/', $e->getMessage(), $m) ? trim($m[1]) : 'The sprint could not close.');
    }
    $r = $st->fetch() ?: ['completed' => 0, 'carried' => 0, 'carried_issues' => 0];
    return ['completed' => (float) $r['completed'], 'carried' => (float) $r['carried'], 'carried_issues' => (int) $r['carried_issues']];
}

function sprint_burndown_rows(PDO $pdo, int $id): array
{
    $st = $pdo->prepare('SELECT * FROM sprint_burndown(:id)');
    $st->execute(['id' => $id]);
    return $st->fetchAll();
}
function sprint_scope_events(PDO $pdo, int $id): array
{
    $st = $pdo->prepare('SELECT * FROM mcp_sprint_scope_events WHERE sprint_id = :id ORDER BY occurred_at DESC, scope_event_id DESC LIMIT 500');
    $st->execute(['id' => $id]);
    return $st->fetchAll();
}

/** The sprint a request names (the manifest's `sprint`, the column's `sprint_id`, or `id`), as the view shows it, or 404. */
function sprint_from_request(PDO $pdo, string $param = 'sprint'): array
{
    $raw = request_string($param) ?: request_string($param . '_id') ?: request_string('id');
    if (!ctype_digit($raw)) {
        refuse(422, 'Which sprint?');
    }
    $s = find_sprint($pdo, (int) $raw);
    if ($s === null) {
        refuse(404, 'Sprint not found.');
    }
    return $s;
}
