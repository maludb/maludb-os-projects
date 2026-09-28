<?php
declare(strict_types=1);
/**
 * Issues — the unit of work and what hangs on it. Reads go through the mcp_* views; every write is one
 * function here, called inside the handler's transaction, so a screen, an agent's action and a bulk
 * fix behave the same. The database's triggers give the key, the initial status and the rank, refuse a
 * move the workflow does not allow, stamp the cycle-time clocks and keep the sprint's scope events.
 */
const ISSUE_TYPES = ['story', 'task', 'bug', 'subtask'];
const ISSUE_PRIORITIES = ['lowest', 'low', 'medium', 'high', 'highest'];
const ISSUE_LINK_KINDS = ['blocks', 'relates', 'duplicates'];
const ISSUE_RESOLUTIONS = ['done', 'wont_do', 'duplicate', 'cannot_reproduce'];
const ISSUES_SORT_ALLOWED = ['rank', 'updated', 'created', 'priority', 'due', 'key'];
const ISSUES_PER_PAGE = 50;

/**
 * @param array $filters q, type[], status[] (ids), category (todo|in_progress|done|open), assignee (id|me|none), reporter (id),
 *                       sprint (id|backlog|active), epic (id), label (name), component (id), priority[], due (overdue|week|none),
 *                       blocked (bool), updated_since (days), include_subtasks (bool, default false), me (the caller's id)
 * @return array{rows: array, total: int}
 */
function find_issues(PDO $pdo, int $projectId, array $filters, string $sort = 'rank', int $page = 1, int $per = ISSUES_PER_PAGE): array
{
    $where = ['i.project_id = :project'];
    $args = ['project' => $projectId];
    if (empty($filters['include_subtasks'])) {
        $where[] = 'i.parent_issue_id IS NULL';
    }
    $q = trim((string) ($filters['q'] ?? ''));
    if ($q !== '') {
        $where[] = '(i.key ILIKE :qk OR i.summary ILIKE :qs)';
        $args += ['qk' => '%' . mb_substr($q, 0, 100) . '%', 'qs' => '%' . mb_substr($q, 0, 100) . '%'];
    }
    $types = array_values(array_intersect((array) ($filters['type'] ?? []), ISSUE_TYPES));
    if ($types !== []) {
        $where[] = 'i.type = ANY (:types)';
        $args['types'] = '{' . implode(',', $types) . '}';
    }
    $statuses = array_values(array_filter(array_map('intval', (array) ($filters['status'] ?? []))));
    if ($statuses !== []) {
        $where[] = 'i.status_id = ANY (:statuses)';
        $args['statuses'] = '{' . implode(',', $statuses) . '}';
    }
    $category = (string) ($filters['category'] ?? '');
    if ($category === 'open') {
        $where[] = "i.status_category <> 'done'";
    } elseif (in_array($category, ['todo', 'in_progress', 'done'], true)) {
        $where[] = 'i.status_category = :category';
        $args['category'] = $category;
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
    if (!empty($filters['reporter'])) {
        $where[] = 'i.reporter_member_id = :reporter';
        $args['reporter'] = (int) $filters['reporter'];
    }
    $sprint = (string) ($filters['sprint'] ?? '');
    if ($sprint === 'backlog') {
        $where[] = 'i.sprint_id IS NULL';
    } elseif ($sprint === 'active') {
        $where[] = "i.sprint_state = 'active'";
    } elseif (ctype_digit($sprint)) {
        $where[] = 'i.sprint_id = :sprint';
        $args['sprint'] = (int) $sprint;
    }
    if (!empty($filters['epic'])) {
        $where[] = 'i.epic_id = :epic';
        $args['epic'] = (int) $filters['epic'];
    }
    if (($filters['label'] ?? '') !== '') {
        $where[] = ':label = ANY (i.labels)';
        $args['label'] = (string) $filters['label'];
    }
    if (!empty($filters['component'])) {
        $where[] = 'i.component_id = :component';
        $args['component'] = (int) $filters['component'];
    }
    $priorities = array_values(array_intersect((array) ($filters['priority'] ?? []), ISSUE_PRIORITIES));
    if ($priorities !== []) {
        $where[] = 'i.priority = ANY (:priorities)';
        $args['priorities'] = '{' . implode(',', $priorities) . '}';
    }
    $due = (string) ($filters['due'] ?? '');
    if ($due === 'overdue') {
        $where[] = "i.due_date < current_date AND i.status_category <> 'done'";
    } elseif ($due === 'week') {
        $where[] = "i.due_date BETWEEN current_date AND current_date + 7";
    } elseif ($due === 'none') {
        $where[] = 'i.due_date IS NULL';
    }
    if (!empty($filters['blocked'])) {
        $where[] = 'i.open_blockers > 0';
    }
    if (!empty($filters['updated_since'])) {
        $where[] = 'i.updated_at >= now() - make_interval(days => :since)';
        $args['since'] = max(1, (int) $filters['updated_since']);
    }
    $order = match (in_array($sort, ISSUES_SORT_ALLOWED, true) ? $sort : 'rank') {
        'updated' => 'i.updated_at DESC',
        'created' => 'i.created_at DESC',
        'priority' => "array_position(ARRAY['highest','high','medium','low','lowest'], i.priority), i.rank",
        'due' => 'i.due_date NULLS LAST, i.rank',
        'key' => 'i.number',
        default => 'i.rank, i.number',
    };
    $from = 'FROM mcp_issues i WHERE (' . implode(') AND (', $where) . ')';
    $count = $pdo->prepare('SELECT count(*) ' . $from);
    foreach ($args as $k => $v) {
        $count->bindValue($k, $v);
    }
    $count->execute();
    $total = (int) $count->fetchColumn();
    $st = $pdo->prepare("SELECT i.* {$from} ORDER BY {$order} LIMIT :lim OFFSET :off");
    foreach ($args as $k => $v) {
        $st->bindValue($k, $v);
    }
    $st->bindValue('lim', $per, PDO::PARAM_INT);
    $st->bindValue('off', max(0, $page - 1) * $per, PDO::PARAM_INT);
    $st->execute();
    return ['rows' => $st->fetchAll(), 'total' => $total];
}

/** One issue by key (an alias resolves; the row then carries `alias_of` = the current key). */
function find_issue_by_key(PDO $pdo, string $key): ?array
{
    $key = strtoupper(trim($key));
    $st = $pdo->prepare('SELECT i.*, d.description, d.accepted_by, d.accepted_by_name, d.aliases FROM mcp_issues i JOIN mcp_issue_details d ON d.issue_id = i.issue_id WHERE i.key = :k');
    $st->execute(['k' => $key]);
    $row = $st->fetch();
    if ($row !== false) {
        return $row;
    }
    $st = $pdo->prepare('SELECT i.*, d.description, d.accepted_by, d.accepted_by_name, d.aliases FROM issue_aliases a JOIN mcp_issues i ON i.issue_id = a.issue_id JOIN mcp_issue_details d ON d.issue_id = i.issue_id WHERE a.old_key = :k');
    $st->execute(['k' => $key]);
    $row = $st->fetch();
    if ($row === false) {
        return null;
    }
    $row['alias_of'] = $row['key'];
    return $row;
}

function find_issue(PDO $pdo, int $id): ?array
{
    $st = $pdo->prepare('SELECT i.*, d.description, d.accepted_by, d.accepted_by_name, d.aliases FROM mcp_issues i JOIN mcp_issue_details d ON d.issue_id = i.issue_id WHERE i.issue_id = :id');
    $st->execute(['id' => $id]);
    return $st->fetch() ?: null;
}

/** The issue from the BASE table — the form's and the handlers' source (the gate has already spoken). */
function find_issue_for_edit(PDO $pdo, int $id): ?array
{
    $st = $pdo->prepare('SELECT * FROM issues WHERE id = :id');
    $st->execute(['id' => $id]);
    return $st->fetch() ?: null;
}

function issue_children(PDO $pdo, int $id): array
{
    $st = $pdo->prepare('SELECT * FROM mcp_issues WHERE parent_issue_id = :id ORDER BY rank, number');
    $st->execute(['id' => $id]);
    return $st->fetchAll();
}
function issue_links(PDO $pdo, int $id): array
{
    $st = $pdo->prepare('SELECT * FROM mcp_issue_links WHERE from_issue_id = :a OR to_issue_id = :b ORDER BY kind, link_id');
    $st->execute(['a' => $id, 'b' => $id]);
    return $st->fetchAll();
}
function issue_comments(PDO $pdo, int $id): array
{
    $st = $pdo->prepare('SELECT * FROM mcp_comments WHERE issue_id = :id ORDER BY created_at, comment_id');
    $st->execute(['id' => $id]);
    return $st->fetchAll();
}
function issue_checklist(PDO $pdo, int $id): array
{
    $st = $pdo->prepare('SELECT * FROM mcp_checklist_items WHERE issue_id = :id ORDER BY sort_order, checklist_item_id');
    $st->execute(['id' => $id]);
    return $st->fetchAll();
}
function issue_worklogs(PDO $pdo, int $id): array
{
    $st = $pdo->prepare('SELECT * FROM mcp_worklogs WHERE issue_id = :id ORDER BY worked_on DESC, worklog_id DESC');
    $st->execute(['id' => $id]);
    return $st->fetchAll();
}
function issue_attachments(PDO $pdo, int $id): array
{
    $st = $pdo->prepare('SELECT * FROM mcp_attachments WHERE issue_id = :id ORDER BY created_at');
    $st->execute(['id' => $id]);
    return $st->fetchAll();
}
function issue_watchers(PDO $pdo, int $id): array
{
    $st = $pdo->prepare('SELECT * FROM mcp_issue_watchers WHERE issue_id = :id ORDER BY display_name');
    $st->execute(['id' => $id]);
    return $st->fetchAll();
}
function issue_history(PDO $pdo, int $id, int $limit = 50): array
{
    $st = $pdo->prepare("SELECT * FROM mcp_activity_log WHERE (entity_type = 'issue' AND entity_id = :a)
                            OR (entity_type = 'comment' AND entity_id IN (SELECT comment_id FROM mcp_comments WHERE issue_id = :b))
                         ORDER BY occurred_at DESC, activity_id DESC LIMIT :lim");
    $st->bindValue('a', $id, PDO::PARAM_INT);
    $st->bindValue('b', $id, PDO::PARAM_INT);
    $st->bindValue('lim', max(1, min(200, $limit)), PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll();
}

/** The project's statuses, sprints (future and active), epics, components and labels — the form's options. */
function issue_form_options(PDO $pdo, int $projectId): array
{
    $one = static function (string $sql) use ($pdo, $projectId): array {
        $st = $pdo->prepare($sql);
        $st->execute(['p' => $projectId]);
        return $st->fetchAll();
    };
    return [
        'statuses' => $one('SELECT * FROM mcp_workflow_statuses WHERE project_id = :p AND archived_at IS NULL ORDER BY sort_order'),
        'sprints' => $one("SELECT sprint_id, name, state FROM mcp_sprints WHERE project_id = :p AND state IN ('future', 'active') ORDER BY state = 'active' DESC, sort_order, sprint_id"),
        'epics' => $one("SELECT epic_id, name FROM mcp_epics WHERE project_id = :p AND status = 'open' ORDER BY sort_order, name"),
        'components' => $one('SELECT component_id, name, default_assignee_id FROM mcp_components WHERE project_id = :p AND archived_at IS NULL ORDER BY name'),
        'labels' => $one('SELECT label_id, name, colour FROM mcp_labels WHERE project_id = :p AND archived_at IS NULL ORDER BY name'),
    ];
}

/**
 * Create an issue. $fields: project_id, type, summary, description, assignee_member_id, priority, story_points,
 * estimate_hours, epic_id, sprint_id, component_id, due_date, parent_issue_id, labels (ids). The trigger gives
 * the key, the initial status and the rank; a subtask takes its parent's project and sprint.
 */
function create_issue(PDO $pdo, array $fields, int $actorId): array
{
    $st = $pdo->prepare('INSERT INTO issues (project_id, type, summary, description, assignee_member_id, reporter_member_id, priority, story_points, estimate_hours,
                                             epic_id, sprint_id, component_id, due_date, parent_issue_id, created_by)
                         VALUES (:project_id, :type, :summary, :description, :assignee_member_id, :reporter_member_id, :priority, :story_points, :estimate_hours,
                                 :epic_id, :sprint_id, :component_id, :due_date, :parent_issue_id, :by) RETURNING *');
    $st->execute([
        'project_id' => $fields['project_id'], 'type' => $fields['type'], 'summary' => $fields['summary'], 'description' => $fields['description'] ?? null,
        'assignee_member_id' => $fields['assignee_member_id'] ?? null, 'reporter_member_id' => $fields['reporter_member_id'] ?? $actorId,
        'priority' => $fields['priority'] ?? 'medium', 'story_points' => $fields['story_points'] ?? null, 'estimate_hours' => $fields['estimate_hours'] ?? null,
        'epic_id' => $fields['epic_id'] ?? null, 'sprint_id' => $fields['sprint_id'] ?? null, 'component_id' => $fields['component_id'] ?? null,
        'due_date' => $fields['due_date'] ?? null, 'parent_issue_id' => $fields['parent_issue_id'] ?? null, 'by' => $actorId,
    ]);
    $row = $st->fetch();
    set_issue_labels($pdo, (int) $row['id'], (array) ($fields['labels'] ?? []));
    return $row;
}

/** Change an issue: only the keys present in $fields (a partial update). Returns [before, after]. */
function update_issue(PDO $pdo, int $id, array $fields, int $actorId): array
{
    $before = find_issue_for_edit($pdo, $id);
    if ($before === null) {
        throw new RuntimeException('No such issue.');
    }
    $columns = ['type', 'summary', 'description', 'assignee_member_id', 'priority', 'story_points', 'estimate_hours', 'epic_id', 'sprint_id', 'component_id', 'due_date', 'resolution'];
    $set = [];
    $args = ['id' => $id];
    foreach ($columns as $c) {
        if (array_key_exists($c, $fields)) {
            $set[] = "{$c} = :{$c}";
            $args[$c] = $fields[$c];
        }
    }
    if ($set !== []) {
        $pdo->prepare('UPDATE issues SET ' . implode(', ', $set) . ' WHERE id = :id')->execute($args);
    }
    if (array_key_exists('labels', $fields)) {
        set_issue_labels($pdo, $id, (array) $fields['labels']);
    }
    return [$before, find_issue_for_edit($pdo, $id)];
}

function set_issue_labels(PDO $pdo, int $issueId, array $labelIds): void
{
    $pdo->prepare('DELETE FROM issue_labels WHERE issue_id = :i')->execute(['i' => $issueId]);
    $ins = $pdo->prepare('INSERT INTO issue_labels (issue_id, label_id) SELECT :i, l.id FROM labels l JOIN issues i ON i.project_id = l.project_id WHERE i.id = :i2 AND l.id = :l ON CONFLICT DO NOTHING');
    foreach (array_unique(array_map('intval', $labelIds)) as $l) {
        if ($l > 0) {
            $ins->execute(['i' => $issueId, 'i2' => $issueId, 'l' => $l]);
        }
    }
}

/**
 * Move an issue to a status. The trigger refuses a move the workflow does not allow (its sentence comes
 * back as a RuntimeException the handler answers 422 with). Returns [before, after] base rows.
 */
function transition_issue(PDO $pdo, int $id, int $toStatusId, int $actorId): array
{
    $before = find_issue_for_edit($pdo, $id);
    if ($before === null) {
        throw new RuntimeException('No such issue.');
    }
    try {
        $pdo->prepare('UPDATE issues SET status_id = :s WHERE id = :id')->execute(['s' => $toStatusId, 'id' => $id]);
    } catch (PDOException $e) {
        $msg = $e->getMessage();
        if (str_contains($msg, 'does not allow') || str_contains($msg, 'not in this project')) {
            throw new RuntimeException(preg_match('/ERROR:\s+([^\n]+)/', $msg, $m) ? trim($m[1]) : 'The workflow does not allow that move.');
        }
        throw $e;
    }
    return [$before, find_issue_for_edit($pdo, $id)];
}

function assign_issue(PDO $pdo, int $id, ?int $memberId, int $actorId): array
{
    return update_issue($pdo, $id, ['assignee_member_id' => $memberId], $actorId);
}
function estimate_issue(PDO $pdo, int $id, ?float $points, ?float $hours, int $actorId): array
{
    return update_issue($pdo, $id, ['story_points' => $points, 'estimate_hours' => $hours], $actorId);
}
function accept_issue(PDO $pdo, int $id, bool $accept, int $actorId): array
{
    $before = find_issue_for_edit($pdo, $id);
    if ($before === null) {
        throw new RuntimeException('No such issue.');
    }
    if ($accept) {
        $pdo->prepare('UPDATE issues SET accepted_by = :by, accepted_at = now() WHERE id = :id')->execute(['by' => $actorId, 'id' => $id]);
    } else {
        $pdo->prepare('UPDATE issues SET accepted_by = NULL, accepted_at = NULL WHERE id = :id')->execute(['id' => $id]);
    }
    return [$before, find_issue_for_edit($pdo, $id)];
}

/** A typed link. A blocks-cycle is refused by the trigger (its sentence comes back as a RuntimeException). */
function link_issues(PDO $pdo, int $fromId, int $toId, string $kind, int $actorId): array
{
    try {
        $st = $pdo->prepare('INSERT INTO issue_links (from_issue_id, to_issue_id, kind, created_by) VALUES (:f, :t, :k, :by) ON CONFLICT (from_issue_id, to_issue_id, kind) DO UPDATE SET kind = EXCLUDED.kind RETURNING *');
        $st->execute(['f' => $fromId, 't' => $toId, 'k' => $kind, 'by' => $actorId]);
    } catch (PDOException $e) {
        $msg = $e->getMessage();
        if (str_contains($msg, 'cycle')) {
            throw new RuntimeException('That would make a cycle of blocks.');
        }
        throw $e;
    }
    return $st->fetch();
}
function unlink_issues(PDO $pdo, int $linkId, int $actorId): bool
{
    $st = $pdo->prepare('DELETE FROM issue_links WHERE id = :id');
    $st->execute(['id' => $linkId]);
    return $st->rowCount() > 0;
}
function find_issue_link(PDO $pdo, int $linkId): ?array
{
    $st = $pdo->prepare('SELECT l.*, f.project_id FROM issue_links l JOIN issues f ON f.id = l.from_issue_id WHERE l.id = :id');
    $st->execute(['id' => $linkId]);
    return $st->fetch() ?: null;
}

function add_comment(PDO $pdo, int $issueId, string $body, int $actorId): array
{
    $st = $pdo->prepare('INSERT INTO comments (issue_id, author_id, body) VALUES (:i, :a, :b) RETURNING *');
    $st->execute(['i' => $issueId, 'a' => $actorId, 'b' => $body]);
    return $st->fetch();
}
function edit_comment(PDO $pdo, int $id, string $body, int $actorId): array
{
    $st = $pdo->prepare('UPDATE comments SET body = :b, edited_at = now() WHERE id = :id AND deleted_at IS NULL RETURNING *');
    $st->execute(['b' => $body, 'id' => $id]);
    $row = $st->fetch();
    if ($row === false) {
        throw new RuntimeException('No such comment.');
    }
    return $row;
}
function delete_comment(PDO $pdo, int $id, int $actorId): bool
{
    $st = $pdo->prepare('UPDATE comments SET deleted_at = now() WHERE id = :id AND deleted_at IS NULL');
    $st->execute(['id' => $id]);
    return $st->rowCount() > 0;
}
function find_comment(PDO $pdo, int $id): ?array
{
    $st = $pdo->prepare('SELECT c.*, i.project_id, i.key AS issue_key FROM comments c JOIN issues i ON i.id = c.issue_id WHERE c.id = :id');
    $st->execute(['id' => $id]);
    return $st->fetch() ?: null;
}

function add_checklist_item(PDO $pdo, int $issueId, string $text, int $actorId): array
{
    $st = $pdo->prepare('INSERT INTO checklist_items (issue_id, text, sort_order, created_by) VALUES (:i, :t, (SELECT coalesce(max(sort_order), 0) + 1 FROM checklist_items WHERE issue_id = :i2), :by) RETURNING *');
    $st->execute(['i' => $issueId, 't' => $text, 'i2' => $issueId, 'by' => $actorId]);
    return $st->fetch();
}
function toggle_checklist_item(PDO $pdo, int $id, bool $done, int $actorId): array
{
    $st = $pdo->prepare('UPDATE checklist_items SET is_done = :d, done_by = CASE WHEN :d2 THEN CAST(:by AS bigint) ELSE NULL END, done_at = CASE WHEN :d3 THEN now() ELSE NULL END WHERE id = :id RETURNING *');
    $st->execute(['d' => $done ? 't' : 'f', 'd2' => $done ? 't' : 'f', 'd3' => $done ? 't' : 'f', 'by' => $actorId, 'id' => $id]);
    $row = $st->fetch();
    if ($row === false) {
        throw new RuntimeException('No such checklist item.');
    }
    return $row;
}
function delete_checklist_item(PDO $pdo, int $id, int $actorId): bool
{
    $st = $pdo->prepare('DELETE FROM checklist_items WHERE id = :id');
    $st->execute(['id' => $id]);
    return $st->rowCount() > 0;
}
function find_checklist_item(PDO $pdo, int $id): ?array
{
    $st = $pdo->prepare('SELECT c.*, i.project_id, i.key AS issue_key FROM checklist_items c JOIN issues i ON i.id = c.issue_id WHERE c.id = :id');
    $st->execute(['id' => $id]);
    return $st->fetch() ?: null;
}

function add_worklog(PDO $pdo, int $issueId, float $hours, string $day, ?string $note, int $actorId): array
{
    $st = $pdo->prepare('INSERT INTO worklogs (issue_id, member_id, hours, worked_on, note) VALUES (:i, :m, :h, :d, :n) RETURNING *');
    $st->execute(['i' => $issueId, 'm' => $actorId, 'h' => $hours, 'd' => $day, 'n' => $note]);
    return $st->fetch();
}
function delete_worklog(PDO $pdo, int $id, int $actorId): bool
{
    $st = $pdo->prepare('DELETE FROM worklogs WHERE id = :id');
    $st->execute(['id' => $id]);
    return $st->rowCount() > 0;
}
function find_worklog(PDO $pdo, int $id): ?array
{
    $st = $pdo->prepare('SELECT w.*, i.project_id, i.key AS issue_key FROM worklogs w JOIN issues i ON i.id = w.issue_id WHERE w.id = :id');
    $st->execute(['id' => $id]);
    return $st->fetch() ?: null;
}

/**
 * Store an uploaded file under storage/attachments/{project}/{issue}/{id}-{name}. $file is one entry of $_FILES
 * (already checked for size and error by the handler). The row is written first so the id names the file.
 */
function store_attachment(PDO $pdo, int $issueId, array $file, int $actorId): array
{
    $issue = find_issue_for_edit($pdo, $issueId);
    if ($issue === null) {
        throw new RuntimeException('No such issue.');
    }
    $name = preg_replace('/[^\w.\-]+/u', '_', basename((string) $file['name'])) ?: 'file';
    $name = mb_substr($name, 0, 200);
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file((string) $file['tmp_name']) ?: 'application/octet-stream';
    $sha = hash_file('sha256', (string) $file['tmp_name']) ?: null;
    $st = $pdo->prepare('INSERT INTO attachments (issue_id, file_name, mime_type, size_bytes, stored_path, sha256, uploaded_by) VALUES (:i, :n, :m, :s, :p, :h, :by) RETURNING *');
    $placeholder = 'pending/' . bin2hex(random_bytes(8));
    $st->execute(['i' => $issueId, 'n' => $name, 'm' => $mime, 's' => (int) $file['size'], 'p' => $placeholder, 'h' => $sha, 'by' => $actorId]);
    $row = $st->fetch();
    $rel = sprintf('%d/%d/%d-%s', (int) $issue['project_id'], $issueId, (int) $row['id'], $name);
    $dir = APP_ROOT . '/storage/attachments/' . dirname($rel);
    if (!is_dir($dir) && !mkdir($dir, 0770, true) && !is_dir($dir)) {
        throw new RuntimeException('The attachment store is not writable.');
    }
    if (!move_uploaded_file((string) $file['tmp_name'], APP_ROOT . '/storage/attachments/' . $rel) && !rename((string) $file['tmp_name'], APP_ROOT . '/storage/attachments/' . $rel)) {
        throw new RuntimeException('The file could not be stored.');
    }
    $pdo->prepare('UPDATE attachments SET stored_path = :p WHERE id = :id')->execute(['p' => 'storage/attachments/' . $rel, 'id' => (int) $row['id']]);
    $row['stored_path'] = 'storage/attachments/' . $rel;
    return $row;
}
function delete_attachment(PDO $pdo, int $id, int $actorId): bool
{
    $st = $pdo->prepare('UPDATE attachments SET deleted_at = now() WHERE id = :id AND deleted_at IS NULL RETURNING stored_path');
    $st->execute(['id' => $id]);
    $row = $st->fetch();
    if ($row === false) {
        return false;
    }
    $path = APP_ROOT . '/' . ltrim((string) $row['stored_path'], '/');
    if (str_starts_with(realpath($path) ?: '', realpath(APP_ROOT . '/storage/attachments') ?: '/nonexistent') && is_file($path)) {
        @unlink($path);
    }
    return true;
}
function find_attachment(PDO $pdo, int $id): ?array
{
    $st = $pdo->prepare('SELECT a.*, i.project_id, i.key AS issue_key FROM attachments a JOIN issues i ON i.id = a.issue_id WHERE a.id = :id');
    $st->execute(['id' => $id]);
    return $st->fetch() ?: null;
}

function set_watch(PDO $pdo, int $issueId, int $memberId, bool $watch): void
{
    if ($watch) {
        $pdo->prepare('INSERT INTO issue_watchers (issue_id, member_id) VALUES (:i, :m) ON CONFLICT DO NOTHING')->execute(['i' => $issueId, 'm' => $memberId]);
    } else {
        $pdo->prepare('DELETE FROM issue_watchers WHERE issue_id = :i AND member_id = :m')->execute(['i' => $issueId, 'm' => $memberId]);
    }
}

/** What hangs on an issue that a reporter's own delete must respect. */
function issue_dependents(PDO $pdo, int $id, int $actorId): array
{
    $st = $pdo->prepare('SELECT (SELECT count(*) FROM issues WHERE parent_issue_id = :a) AS subtasks,
                                (SELECT count(*) FROM comments WHERE issue_id = :b AND deleted_at IS NULL AND author_id <> :me) AS others_comments,
                                (SELECT count(*) FROM worklogs WHERE issue_id = :c) AS worklogs,
                                (SELECT count(*) FROM issue_links WHERE from_issue_id = :d OR to_issue_id = :e) AS links');
    $st->execute(['a' => $id, 'b' => $id, 'me' => $actorId, 'c' => $id, 'd' => $id, 'e' => $id]);
    return array_map('intval', $st->fetch() ?: []);
}
function delete_issue(PDO $pdo, int $id, int $actorId): bool
{
    $st = $pdo->prepare('DELETE FROM issues WHERE id = :id');
    $st->execute(['id' => $id]);
    return $st->rowCount() > 0;
}

function save_filter(PDO $pdo, int $ownerId, ?int $projectId, string $name, array $filter, bool $shared): array
{
    $st = $pdo->prepare('INSERT INTO saved_filters (owner_id, project_id, name, filter, is_shared) VALUES (:o, :p, :n, :f, :s)
                         ON CONFLICT (owner_id, project_id, name) DO UPDATE SET filter = EXCLUDED.filter, is_shared = EXCLUDED.is_shared RETURNING *');
    $st->execute(['o' => $ownerId, 'p' => $projectId, 'n' => $name, 'f' => json_encode($filter, JSON_UNESCAPED_SLASHES), 's' => $shared ? 't' : 'f']);
    return $st->fetch();
}
function saved_filters_for(PDO $pdo, ?int $projectId): array
{
    $st = $pdo->prepare('SELECT * FROM mcp_saved_filters WHERE (project_id = :p OR project_id IS NULL) ORDER BY project_id NULLS LAST, name');
    $st->execute(['p' => $projectId]);
    return $st->fetchAll();
}
