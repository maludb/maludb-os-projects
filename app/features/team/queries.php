<?php
declare(strict_types=1);
/**
 * A project's team as the settings keep it (slice 6). Reads through mcp_project_team; writes to project_team
 * behind project.admin (the handler's gate). The lead in projects.lead_member_id follows the `lead` role: one
 * lead — setting another demotes the previous to member.
 */

/** The live team in role order, each row with the member's kind and whether they are external. */
function find_team(PDO $pdo, int $projectId): array
{
    $st = $pdo->prepare(<<<'SQL'
        SELECT t.*, m.job_title, m.status AS member_status
          FROM mcp_project_team t JOIN mcp_members m ON m.member_id = t.member_id
         WHERE t.project_id = :p AND t.removed_at IS NULL
         ORDER BY CASE t.project_role WHEN 'lead' THEN 0 WHEN 'product_owner' THEN 1 WHEN 'scrum_master' THEN 2 WHEN 'member' THEN 3 ELSE 4 END, t.display_name
    SQL);
    $st->execute(['p' => $projectId]);
    return $st->fetchAll();
}

/** Active members (humans and agents) not on the team — the add form's options. */
function team_candidates(PDO $pdo, int $projectId): array
{
    $st = $pdo->prepare("SELECT member_id, display_name, member_kind, job_title, is_external FROM mcp_members
                          WHERE status = 'active' AND member_id NOT IN (SELECT member_id FROM mcp_project_team WHERE project_id = :p AND removed_at IS NULL)
                          ORDER BY member_kind = 'agent', display_name");
    $st->execute(['p' => $projectId]);
    return $st->fetchAll();
}

/**
 * team_candidates() matching a search, one page — the record picker's source for "Add to the team" (app/pickers.php).
 * @return array{rows: array, total: int, page: int, page_size: int, pages: int}
 */
function find_team_candidates(PDO $pdo, int $projectId, string $q, int $page = 1, int $per = 25): array
{
    $where = "status = 'active' AND member_id NOT IN (SELECT member_id FROM mcp_project_team WHERE project_id = :p AND removed_at IS NULL)";
    $args = ['p' => $projectId];
    if ($q !== '') {
        $where .= ' AND (display_name ILIKE :q OR job_title ILIKE :q OR email ILIKE :q)';
        $args['q'] = '%' . $q . '%';
    }
    $count = $pdo->prepare("SELECT count(*) FROM mcp_members WHERE {$where}");
    $count->execute($args);
    $total = (int) $count->fetchColumn();
    $st = $pdo->prepare("SELECT member_id, display_name, member_kind, job_title, is_external FROM mcp_members WHERE {$where}
                          ORDER BY member_kind = 'agent', display_name, member_id LIMIT :lim OFFSET :off");
    foreach ($args as $k => $v) {
        $st->bindValue($k, $v);
    }
    $st->bindValue('lim', $per, PDO::PARAM_INT);
    $st->bindValue('off', max(0, $page - 1) * $per, PDO::PARAM_INT);
    $st->execute();
    return ['rows' => $st->fetchAll(), 'total' => $total, 'page' => $page, 'page_size' => $per, 'pages' => max(1, (int) ceil($total / $per))];
}

/** Add (or re-add, or re-role) a member. A lead demotes the previous lead and becomes the project's lead. Returns the row after. */
function add_team_member(PDO $pdo, int $projectId, int $memberId, string $role, int $actorId): array
{
    if ($role === 'lead') {
        $pdo->prepare("UPDATE project_team SET project_role = 'member' WHERE project_id = :p AND project_role = 'lead' AND member_id <> :m AND removed_at IS NULL")->execute(['p' => $projectId, 'm' => $memberId]);
        $pdo->prepare('UPDATE projects SET lead_member_id = :m WHERE id = :p')->execute(['p' => $projectId, 'm' => $memberId]);
    }
    $st = $pdo->prepare('INSERT INTO project_team (project_id, member_id, project_role, added_by) VALUES (:p, :m, :r, :by)
                         ON CONFLICT (project_id, member_id) DO UPDATE SET project_role = EXCLUDED.project_role, removed_at = NULL, added_by = EXCLUDED.added_by, added_at = now()
                         RETURNING *');
    $st->execute(['p' => $projectId, 'm' => $memberId, 'r' => $role, 'by' => $actorId]);
    $row = $st->fetch();
    if ($role !== 'lead') {
        // A lead re-roled to something else is no longer the project's lead.
        $pdo->prepare('UPDATE projects SET lead_member_id = NULL WHERE id = :p AND lead_member_id = :m')->execute(['p' => $projectId, 'm' => $memberId]);
    }
    return $row;
}

/** Date the membership (never erased). A removed lead leaves projects.lead_member_id empty. False when they were not on the team. */
function remove_team_member(PDO $pdo, int $projectId, int $memberId, int $actorId): bool
{
    $st = $pdo->prepare('UPDATE project_team SET removed_at = now() WHERE project_id = :p AND member_id = :m AND removed_at IS NULL RETURNING project_role');
    $st->execute(['p' => $projectId, 'm' => $memberId]);
    $role = $st->fetchColumn();
    if ($role === false) {
        return false;
    }
    if ($role === 'lead') {
        $pdo->prepare('UPDATE projects SET lead_member_id = NULL WHERE id = :p AND lead_member_id = :m')->execute(['p' => $projectId, 'm' => $memberId]);
    }
    return true;
}
