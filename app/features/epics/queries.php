<?php
declare(strict_types=1);
/**
 * Epics, labels and components — a project's larger bodies of work, its free tags and its named parts
 * (slice 4). Reads go through the mcp_* views; the forms read the base rows behind their gates. Moving an
 * issue between projects is the database's projects_move_issue() (db/010): a new key, the old an alias.
 */
const EPIC_STATUSES = ['open', 'done', 'cancelled'];
const EPIC_COLOURS = ['primary', 'secondary', 'success', 'danger', 'warning', 'info', 'dark'];
const LABEL_COLOURS = EPIC_COLOURS;

/** The project's epics as the view shows them: open first by sort_order, then done, then cancelled. */
function find_epics(PDO $pdo, int $projectId, string $status = ''): array
{
    $where = 'e.project_id = :p';
    $args = ['p' => $projectId];
    if (in_array($status, EPIC_STATUSES, true)) {
        $where .= ' AND e.status = :s';
        $args['s'] = $status;
    }
    $st = $pdo->prepare("SELECT e.* FROM mcp_epics e WHERE {$where}
                         ORDER BY CASE e.status WHEN 'open' THEN 0 WHEN 'done' THEN 1 ELSE 2 END, e.sort_order, e.name");
    $st->execute($args);
    return $st->fetchAll();
}

function find_epic(PDO $pdo, int $id): ?array
{
    $st = $pdo->prepare('SELECT * FROM mcp_epics WHERE epic_id = :id');
    $st->execute(['id' => $id]);
    return $st->fetch() ?: null;
}

/** The epic from the BASE table — the form's source (the gate has already spoken). */
function find_epic_for_edit(PDO $pdo, int $id): ?array
{
    $st = $pdo->prepare('SELECT * FROM epics WHERE id = :id');
    $st->execute(['id' => $id]);
    return $st->fetch() ?: null;
}

/** Create or change an epic. $fields: project_id (create), name, summary, colour, target_date, sort_order. */
function upsert_epic(PDO $pdo, ?int $id, array $fields, int $actorId): array
{
    if ($id === null) {
        $st = $pdo->prepare('INSERT INTO epics (project_id, name, summary, colour, target_date, sort_order, created_by)
                             VALUES (:project_id, :name, :summary, :colour, :target_date, :sort_order, :by) RETURNING *');
        $st->execute(['project_id' => $fields['project_id'], 'name' => $fields['name'], 'summary' => $fields['summary'], 'colour' => $fields['colour'],
                      'target_date' => $fields['target_date'], 'sort_order' => $fields['sort_order'], 'by' => $actorId]);
        return $st->fetch();
    }
    $st = $pdo->prepare('UPDATE epics SET name = :name, summary = :summary, colour = :colour, target_date = :target_date, sort_order = :sort_order WHERE id = :id RETURNING *');
    $st->execute(['id' => $id, 'name' => $fields['name'], 'summary' => $fields['summary'], 'colour' => $fields['colour'], 'target_date' => $fields['target_date'], 'sort_order' => $fields['sort_order']]);
    return $st->fetch();
}

/** open | done | cancelled. Returns [before, after] base rows. */
function set_epic_status(PDO $pdo, int $id, string $status, int $actorId): array
{
    $before = find_epic_for_edit($pdo, $id);
    if ($before === null) {
        throw new RuntimeException('No such epic.');
    }
    if (!in_array($status, EPIC_STATUSES, true)) {
        throw new RuntimeException('An epic is open, done or cancelled.');
    }
    $pdo->prepare('UPDATE epics SET status = :s WHERE id = :id')->execute(['s' => $status, 'id' => $id]);
    return [$before, find_epic_for_edit($pdo, $id)];
}

/** The epic's top-level issues, by rank. */
function epic_issues(PDO $pdo, int $id): array
{
    $st = $pdo->prepare('SELECT * FROM mcp_issues WHERE epic_id = :id AND parent_issue_id IS NULL ORDER BY rank, number');
    $st->execute(['id' => $id]);
    return $st->fetchAll();
}

/** The epic's trail: its own rows and its issues' transitions. */
function epic_activity(PDO $pdo, int $id, int $limit = 20): array
{
    $st = $pdo->prepare("SELECT a.* FROM mcp_activity_log a
                          WHERE (a.entity_type = 'epic' AND a.entity_id = :a)
                             OR (a.entity_type = 'issue' AND a.action = 'issue.transition' AND a.entity_id IN (SELECT issue_id FROM mcp_issues WHERE epic_id = :b))
                          ORDER BY a.occurred_at DESC, a.activity_id DESC LIMIT :lim");
    $st->bindValue('a', $id, PDO::PARAM_INT);
    $st->bindValue('b', $id, PDO::PARAM_INT);
    $st->bindValue('lim', max(1, min(200, $limit)), PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll();
}

function find_labels(PDO $pdo, int $projectId, bool $includeArchived = false): array
{
    $st = $pdo->prepare('SELECT * FROM mcp_labels WHERE project_id = :p' . ($includeArchived ? '' : ' AND archived_at IS NULL') . ' ORDER BY archived_at IS NOT NULL, name');
    $st->execute(['p' => $projectId]);
    return $st->fetchAll();
}
function find_label(PDO $pdo, int $id): ?array
{
    $st = $pdo->prepare('SELECT * FROM labels WHERE id = :id');
    $st->execute(['id' => $id]);
    return $st->fetch() ?: null;
}
/** A new label, or a rename/recolour of one (by id). Returns the base row. */
function save_label(PDO $pdo, int $projectId, ?int $id, string $name, string $colour, int $actorId): array
{
    if ($id === null) {
        $st = $pdo->prepare('INSERT INTO labels (project_id, name, colour) VALUES (:p, :n, :c)
                             ON CONFLICT (project_id, name) DO UPDATE SET colour = EXCLUDED.colour, archived_at = NULL RETURNING *');
        $st->execute(['p' => $projectId, 'n' => $name, 'c' => $colour]);
        return $st->fetch();
    }
    $st = $pdo->prepare('UPDATE labels SET name = :n, colour = :c WHERE id = :id AND project_id = :p RETURNING *');
    $st->execute(['n' => $name, 'c' => $colour, 'id' => $id, 'p' => $projectId]);
    $row = $st->fetch();
    if ($row === false) {
        throw new RuntimeException('No such label in this project.');
    }
    return $row;
}
function archive_label(PDO $pdo, int $id, int $actorId): bool
{
    $st = $pdo->prepare('UPDATE labels SET archived_at = now() WHERE id = :id AND archived_at IS NULL');
    $st->execute(['id' => $id]);
    return $st->rowCount() > 0;
}

function find_components(PDO $pdo, int $projectId, bool $includeArchived = false): array
{
    $st = $pdo->prepare('SELECT * FROM mcp_components WHERE project_id = :p' . ($includeArchived ? '' : ' AND archived_at IS NULL') . ' ORDER BY archived_at IS NOT NULL, name');
    $st->execute(['p' => $projectId]);
    return $st->fetchAll();
}
function find_component(PDO $pdo, int $id): ?array
{
    $st = $pdo->prepare('SELECT * FROM components WHERE id = :id');
    $st->execute(['id' => $id]);
    return $st->fetch() ?: null;
}
/** $fields: name, description, default_assignee_id. */
function save_component(PDO $pdo, int $projectId, ?int $id, array $fields, int $actorId): array
{
    if ($id === null) {
        $st = $pdo->prepare('INSERT INTO components (project_id, name, description, default_assignee_id) VALUES (:p, :n, :d, :a)
                             ON CONFLICT (project_id, name) DO UPDATE SET description = EXCLUDED.description, default_assignee_id = EXCLUDED.default_assignee_id, archived_at = NULL RETURNING *');
        $st->execute(['p' => $projectId, 'n' => $fields['name'], 'd' => $fields['description'], 'a' => $fields['default_assignee_id']]);
        return $st->fetch();
    }
    $st = $pdo->prepare('UPDATE components SET name = :n, description = :d, default_assignee_id = :a WHERE id = :id AND project_id = :p RETURNING *');
    $st->execute(['n' => $fields['name'], 'd' => $fields['description'], 'a' => $fields['default_assignee_id'], 'id' => $id, 'p' => $projectId]);
    $row = $st->fetch();
    if ($row === false) {
        throw new RuntimeException('No such component in this project.');
    }
    return $row;
}
function archive_component(PDO $pdo, int $id, int $actorId): bool
{
    $st = $pdo->prepare('UPDATE components SET archived_at = now() WHERE id = :id AND archived_at IS NULL');
    $st->execute(['id' => $id]);
    return $st->rowCount() > 0;
}

/**
 * Move an issue (and its subtasks) to another project: the database re-keys it, keeps the old keys as
 * aliases, drops the source's sprint, epic, component and labels, maps the status by category. Returns
 * [old key, new key].
 */
function move_issue_to_project(PDO $pdo, int $issueId, int $targetProjectId, int $actorId): array
{
    $st = $pdo->prepare('SELECT key FROM issues WHERE id = :id');
    $st->execute(['id' => $issueId]);
    $old = $st->fetchColumn();
    if ($old === false) {
        throw new RuntimeException('No such issue.');
    }
    try {
        $st = $pdo->prepare('SELECT projects_move_issue(:i, :p, :by)');
        $st->execute(['i' => $issueId, 'p' => $targetProjectId, 'by' => $actorId]);
    } catch (PDOException $e) {
        throw new RuntimeException(preg_match('/ERROR:\s+([^\n]+)/', $e->getMessage(), $m) ? trim($m[1]) : 'The issue could not be moved.');
    }
    return [(string) $old, (string) $st->fetchColumn()];
}

/** The projects the caller administers, for the move picker (never the one the issue is in). */
function projects_i_administer(PDO $pdo, ?int $exceptProjectId = null): array
{
    $st = $pdo->prepare("SELECT project_id, key, name FROM mcp_projects WHERE i_administer AND status = 'active' AND project_id <> :x ORDER BY name");
    $st->execute(['x' => $exceptProjectId ?? 0]);
    return $st->fetchAll();
}
