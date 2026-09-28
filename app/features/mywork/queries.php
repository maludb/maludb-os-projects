<?php
declare(strict_types=1);
/**
 * My work — everything on a member's plate across projects, in the shape the kernel's future My Work
 * aggregation and the records server's `my_work` tool share (the design §7): kind, id, title, detail,
 * due_at, state, href, urgency. ONE query per tab over mcp_issues, so the Python tool can copy it.
 */
const MY_WORK_TABS = ['assigned', 'watching', 'review', 'reported'];

/** The SQL condition of a tab, over mcp_issues i with :me bound (the tool copies these verbatim). */
function my_work_tab_sql(string $tab): string
{
    return match ($tab) {
        'watching' => "i.status_category <> 'done' AND i.assignee_member_id IS DISTINCT FROM :me
                       AND i.issue_id IN (SELECT w.issue_id FROM mcp_issue_watchers w WHERE w.member_id = :me)",
        // The reviewer's queue: a status flagged is_review, or one the board maps to a column named Review —
        // for issues assigned to me, reported by me, watched by me, or in a project I am on the team of.
        'review' => "(i.in_review OR i.status_id IN (SELECT unnest(c.status_ids) FROM mcp_board_columns c WHERE c.name ILIKE '%review%'))
                     AND i.status_category <> 'done'
                     AND (i.assignee_member_id = :me OR i.reporter_member_id = :me
                          OR i.issue_id IN (SELECT w.issue_id FROM mcp_issue_watchers w WHERE w.member_id = :me)
                          OR i.project_id IN (SELECT t.project_id FROM mcp_project_team t WHERE t.member_id = :me AND t.removed_at IS NULL))",
        'reported' => "i.status_category <> 'done' AND i.reporter_member_id = :me",
        default => "i.status_category <> 'done' AND i.assignee_member_id = :me",
    };
}

/**
 * @return array<int, array{kind:string,id:int,key:string,title:string,detail:string,due_at:?string,state:string,href:string,urgency:int,
 *                          summary:string,type:string,project_key:string,status:string,status_category:string,sprint:?string,points:?float,
 *                          open_blockers:int,updated_at:?string}>
 */
function find_my_work(PDO $pdo, int $memberId, string $tab = 'assigned', int $limit = 100): array
{
    $tab = in_array($tab, MY_WORK_TABS, true) ? $tab : 'assigned';
    $sql = "SELECT i.issue_id, i.key, i.type, i.summary, i.project_key, i.project_name, i.status, i.status_category, i.in_review, i.sprint_name,
                   i.story_points, i.due_date, i.open_blockers, i.rank, i.updated_at,
                   CASE WHEN i.open_blockers > 0 THEN 'blocked'
                        WHEN i.due_date IS NOT NULL AND i.due_date < current_date THEN 'overdue'
                        WHEN i.due_date = current_date THEN 'due today'
                        WHEN i.due_date IS NOT NULL THEN 'due'
                        WHEN i.in_review THEN 'in review'
                        ELSE 'open' END AS state,
                   CASE WHEN i.open_blockers > 0 OR (i.due_date IS NOT NULL AND i.due_date < current_date) THEN 2
                        WHEN i.due_date = current_date THEN 1 ELSE 0 END AS urgency
              FROM mcp_issues i
             WHERE " . my_work_tab_sql($tab) . "
             ORDER BY urgency DESC, i.due_date NULLS LAST, i.rank, i.issue_id
             LIMIT :lim";
    $st = $pdo->prepare($sql);
    $st->bindValue('me', $memberId, PDO::PARAM_INT);
    $st->bindValue('lim', max(1, min(500, $limit)), PDO::PARAM_INT);
    $st->execute();
    $out = [];
    foreach ($st->fetchAll() as $r) {
        $out[] = [
            'kind' => 'issue', 'id' => (int) $r['issue_id'], 'key' => (string) $r['key'],
            'title' => $r['key'] . ' ' . $r['summary'],
            'detail' => $r['project_key'] . ' · ' . $r['status'] . ($r['sprint_name'] !== null ? ' · ' . $r['sprint_name'] : ''),
            'due_at' => $r['due_date'], 'state' => (string) $r['state'], 'href' => '/issues/' . $r['key'], 'urgency' => (int) $r['urgency'],
            'summary' => (string) $r['summary'], 'type' => (string) $r['type'], 'project_key' => (string) $r['project_key'], 'project_name' => (string) $r['project_name'],
            'status' => (string) $r['status'], 'status_category' => (string) $r['status_category'], 'sprint' => $r['sprint_name'],
            'points' => $r['story_points'] !== null ? (float) $r['story_points'] : null, 'open_blockers' => (int) $r['open_blockers'], 'updated_at' => $r['updated_at'],
        ];
    }
    return $out;
}

/** How many on each tab, for the strip. */
function my_work_counts(PDO $pdo, int $memberId): array
{
    $counts = [];
    foreach (MY_WORK_TABS as $tab) {
        $st = $pdo->prepare('SELECT count(*) FROM mcp_issues i WHERE ' . my_work_tab_sql($tab));
        $st->bindValue('me', $memberId, PDO::PARAM_INT);
        $st->execute();
        $counts[$tab] = (int) $st->fetchColumn();
    }
    return $counts;
}
