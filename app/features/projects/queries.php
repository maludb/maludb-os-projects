<?php
declare(strict_types=1);
/**
 * Projects — the body of work with its team, workflow and board. Reads go through the mcp_* views (the
 * caller sees what the views allow); the form alone reads the base table, behind the project.admin gate.
 */
const PROJECTS_SORT_ALLOWED = ['name', 'key', 'updated'];
const PROJECTS_PER_PAGE = 24;
const PROJECT_VISIBILITIES = ['open', 'private'];
const PROJECT_BOARD_MODES = ['scrum', 'kanban'];
const PROJECT_ROLES = ['lead', 'product_owner', 'scrum_master', 'member', 'viewer'];
const PROJECT_TABS = ['overview', 'backlog', 'board', 'sprints', 'epics', 'issues', 'reports', 'settings'];

/**
 * @param array $filters q, department (id), status (active default | archived | all), mine (bool)
 * @return array{rows: array, total: int}
 */
function find_projects(PDO $pdo, array $filters, string $sort = 'name', int $page = 1, int $per = PROJECTS_PER_PAGE): array
{
    $where = ['1 = 1'];
    $args = [];
    $q = trim((string) ($filters['q'] ?? ''));
    if ($q !== '') {
        $where[] = "(p.key ILIKE :qk OR p.name ILIKE :qn OR p.description ILIKE :qd)";
        $like = '%' . mb_substr($q, 0, 100) . '%';
        $args += ['qk' => $like, 'qn' => $like, 'qd' => $like];
    }
    if (!empty($filters['department'])) {
        $where[] = 'p.department_id = :dept';
        $args['dept'] = (int) $filters['department'];
    }
    $status = (string) ($filters['status'] ?? 'active');
    if ($status === 'archived') {
        $where[] = "p.status = 'archived'";
    } elseif ($status !== 'all') {
        $where[] = "p.status = 'active'";
    }
    if (!empty($filters['mine'])) {
        $where[] = 'EXISTS (SELECT 1 FROM mcp_project_team t WHERE t.project_id = p.project_id AND t.member_id = :me AND t.removed_at IS NULL)';
        $args['me'] = (int) ($filters['me'] ?? 0);
    }
    $order = match (in_array($sort, PROJECTS_SORT_ALLOWED, true) ? $sort : 'name') {
        'key' => 'p.key',
        'updated' => 'p.updated_at DESC, p.name',
        default => 'p.name',
    };
    $from = 'FROM mcp_projects p WHERE (' . implode(') AND (', $where) . ')';
    $count = $pdo->prepare('SELECT count(*) ' . $from);
    $count->execute($args);
    $total = (int) $count->fetchColumn();
    $st = $pdo->prepare("SELECT p.* {$from} ORDER BY {$order} LIMIT :lim OFFSET :off");
    foreach ($args as $k => $v) {
        $st->bindValue($k, $v);
    }
    $st->bindValue('lim', $per, PDO::PARAM_INT);
    $st->bindValue('off', max(0, $page - 1) * $per, PDO::PARAM_INT);
    $st->execute();
    return ['rows' => $st->fetchAll(), 'total' => $total];
}

/** One project as the view shows it, with its team, statuses, columns, the active sprint and the epics' progress. */
function find_project_by_key(PDO $pdo, string $key): ?array
{
    $st = $pdo->prepare('SELECT * FROM mcp_projects WHERE key = :k');
    $st->execute(['k' => strtoupper($key)]);
    $p = $st->fetch();
    if ($p === false) {
        return null;
    }
    $id = (int) $p['project_id'];
    $st = $pdo->prepare('SELECT * FROM mcp_project_team WHERE project_id = :p AND removed_at IS NULL ORDER BY CASE project_role WHEN \'lead\' THEN 0 WHEN \'product_owner\' THEN 1 WHEN \'scrum_master\' THEN 2 WHEN \'member\' THEN 3 ELSE 4 END, display_name');
    $st->execute(['p' => $id]);
    $p['team'] = $st->fetchAll();
    $st = $pdo->prepare('SELECT * FROM mcp_workflow_statuses WHERE project_id = :p AND archived_at IS NULL ORDER BY sort_order');
    $st->execute(['p' => $id]);
    $p['statuses'] = $st->fetchAll();
    $st = $pdo->prepare('SELECT * FROM mcp_board_columns WHERE project_id = :p ORDER BY sort_order');
    $st->execute(['p' => $id]);
    $p['columns'] = $st->fetchAll();
    $p['active_sprint'] = null;
    if ($p['active_sprint_id'] !== null) {
        $st = $pdo->prepare('SELECT * FROM mcp_sprints WHERE sprint_id = :s');
        $st->execute(['s' => (int) $p['active_sprint_id']]);
        $p['active_sprint'] = $st->fetch() ?: null;
        if ($p['active_sprint'] !== null) {
            $st = $pdo->prepare('SELECT * FROM sprint_burndown(:s)');
            $st->execute(['s' => (int) $p['active_sprint_id']]);
            $p['active_sprint']['burndown'] = $st->fetchAll();
        }
    }
    $st = $pdo->prepare("SELECT * FROM mcp_epics WHERE project_id = :p AND status = 'open' ORDER BY sort_order, name LIMIT 6");
    $st->execute(['p' => $id]);
    $p['epics'] = $st->fetchAll();
    return $p;
}

/** The project by id, as the view shows it (a gate has already said the caller may see it). */
function find_project(PDO $pdo, int $id): ?array
{
    $st = $pdo->prepare('SELECT * FROM mcp_projects WHERE project_id = :id');
    $st->execute(['id' => $id]);
    return $st->fetch() ?: null;
}

/** The project from the BASE table — the form's source, behind project.admin (the caller checks). */
function find_project_for_edit(PDO $pdo, int $id): ?array
{
    $st = $pdo->prepare('SELECT * FROM projects WHERE id = :id');
    $st->execute(['id' => $id]);
    return $st->fetch() ?: null;
}

/**
 * Create or change a project. $fields: key (create), name, description, department_id, lead_member_id,
 * visibility, board_mode, customer. Returns the base row. The trigger seeds the workflow and the board and
 * puts the lead on the team; on edit a new lead joins the team as lead and the old one stays a member.
 */
function upsert_project(PDO $pdo, ?int $id, array $fields, int $actorId): array
{
    if ($id === null) {
        $st = $pdo->prepare('INSERT INTO projects (key, name, description, department_id, lead_member_id, visibility, board_mode, customer, created_by)
                             VALUES (:key, :name, :description, :department_id, :lead_member_id, :visibility, :board_mode, :customer, :by) RETURNING *');
        $st->execute(['key' => $fields['key'], 'name' => $fields['name'], 'description' => $fields['description'], 'department_id' => $fields['department_id'],
                      'lead_member_id' => $fields['lead_member_id'], 'visibility' => $fields['visibility'], 'board_mode' => $fields['board_mode'],
                      'customer' => $fields['customer'], 'by' => $actorId]);
        return $st->fetch();
    }
    $st = $pdo->prepare('UPDATE projects SET name = :name, description = :description, department_id = :department_id, lead_member_id = :lead_member_id,
                            visibility = :visibility, board_mode = :board_mode, customer = :customer WHERE id = :id RETURNING *');
    $st->execute(['id' => $id, 'name' => $fields['name'], 'description' => $fields['description'], 'department_id' => $fields['department_id'],
                  'lead_member_id' => $fields['lead_member_id'], 'visibility' => $fields['visibility'], 'board_mode' => $fields['board_mode'], 'customer' => $fields['customer']]);
    $row = $st->fetch();
    if ($fields['lead_member_id'] !== null) {
        $pdo->prepare("UPDATE project_team SET project_role = 'member' WHERE project_id = :p AND project_role = 'lead' AND member_id <> :m AND removed_at IS NULL")->execute(['p' => $id, 'm' => $fields['lead_member_id']]);
        $pdo->prepare("INSERT INTO project_team (project_id, member_id, project_role, added_by) VALUES (:p, :m, 'lead', :by)
                       ON CONFLICT (project_id, member_id) DO UPDATE SET project_role = 'lead', removed_at = NULL")->execute(['p' => $id, 'm' => $fields['lead_member_id'], 'by' => $actorId]);
    }
    return $row;
}

/** The project's recent trail: its own rows and those of its issues, sprints and epics. */
function project_activity(PDO $pdo, int $projectId, int $limit = 10): array
{
    $st = $pdo->prepare(<<<'SQL'
        SELECT a.* FROM mcp_activity_log a
         WHERE (a.entity_type = 'project' AND a.entity_id = :p1)
            OR (a.entity_type = 'issue' AND a.entity_id IN (SELECT issue_id FROM mcp_issues WHERE project_id = :p2))
            OR (a.entity_type = 'sprint' AND a.entity_id IN (SELECT sprint_id FROM mcp_sprints WHERE project_id = :p3))
            OR (a.entity_type = 'epic' AND a.entity_id IN (SELECT epic_id FROM mcp_epics WHERE project_id = :p4))
         ORDER BY a.occurred_at DESC, a.activity_id DESC LIMIT :lim
    SQL);
    foreach (['p1', 'p2', 'p3', 'p4'] as $k) {
        $st->bindValue($k, $projectId, PDO::PARAM_INT);
    }
    $st->bindValue('lim', max(1, min(200, $limit)), PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll();
}

/** Options for the forms: departments, members (humans and agents, active), the point scale. */
function department_options(PDO $pdo): array
{
    return $pdo->query('SELECT department_id, name FROM mcp_departments WHERE archived_at IS NULL ORDER BY name')->fetchAll();
}
function member_options(PDO $pdo): array
{
    return $pdo->query("SELECT member_id, display_name, member_kind, job_title FROM mcp_members WHERE status = 'active' ORDER BY member_kind = 'agent', display_name")->fetchAll();
}
function projects_settings(PDO $pdo): array
{
    $row = $pdo->query('SELECT * FROM mcp_projects_settings')->fetch();
    if ($row === false) {
        return ['timezone' => 'UTC', 'sprint_length_days' => 14, 'point_scale' => [1, 2, 3, 5, 8, 13, 21], 'stale_after_days' => 5, 'attachment_max_bytes' => 10485760, 'default_visibility' => 'open'];
    }
    $row['point_scale'] = array_map('intval', array_filter(explode(',', trim((string) $row['point_scale'], '{}')), 'strlen'));
    return $row;
}
