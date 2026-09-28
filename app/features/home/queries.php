<?php
declare(strict_types=1);

/** The dashboard's three cards — every row from an mcp_* view or a report function, so a person sees what they may see. */

/** My open issues, due first then by rank; a "blocked" mark when open_blockers > 0. */
function home_my_issues(PDO $pdo, int $memberId, int $limit = 10): array
{
    $st = $pdo->prepare("SELECT issue_id, key, project_key, type, summary, status, status_category, priority, story_points, due_date, open_blockers, sprint_name
                           FROM mcp_issues
                          WHERE assignee_member_id = :m AND status_category <> 'done'
                          ORDER BY due_date NULLS LAST, rank, issue_id
                          LIMIT :lim");
    $st->bindValue('m', $memberId, PDO::PARAM_INT);
    $st->bindValue('lim', max(1, min($limit, 50)), PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll();
}

/** My projects: the ones I lead or am on the team of; else the open ones I may see. */
function home_my_projects(PDO $pdo, int $memberId, int $limit = 6): array
{
    $st = $pdo->prepare("SELECT p.project_id, p.key, p.name, p.department_name, p.lead_name, p.visibility, p.active_sprint_id, p.active_sprint_name, p.open_issues, p.team_size,
                                EXISTS (SELECT 1 FROM mcp_project_team t WHERE t.project_id = p.project_id AND t.member_id = :m AND t.removed_at IS NULL) AS mine
                           FROM mcp_projects p
                          WHERE p.status = 'active'
                          ORDER BY mine DESC, p.updated_at DESC
                          LIMIT :lim");
    $st->bindValue('m', $memberId, PDO::PARAM_INT);
    $st->bindValue('lim', max(1, min($limit, 24)), PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll();
}

/** Every active sprint I may see, with its burndown rows for the sparkline. */
function home_active_sprints(PDO $pdo): array
{
    $rows = $pdo->query("SELECT sprint_id, project_id, project_key, name, goal, start_date, end_date, points, points_done, issues, issues_done,
                                GREATEST(0, end_date - current_date) AS days_left
                           FROM mcp_sprints WHERE state = 'active' ORDER BY end_date NULLS LAST, project_key LIMIT 24")->fetchAll();
    $bd = $pdo->prepare('SELECT day, scope, done, remaining FROM sprint_burndown(:s)');
    foreach ($rows as &$r) {
        $bd->execute(['s' => (int) $r['sprint_id']]);
        $r['burndown'] = $bd->fetchAll();
    }
    unset($r);
    return $rows;
}
