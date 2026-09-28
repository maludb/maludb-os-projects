<?php
declare(strict_types=1);
/**
 * Reports — the questions 7, 8, 9, 10 and 14 of the design, each one db/010 function read as the
 * caller (app.member_id is set on the connection; the functions read the base tables, so the handler
 * gates on projects_can_see_project first). Nothing here writes.
 */
const REPORT_KINDS = ['velocity', 'burndown', 'cycle', 'flow', 'scope', 'hygiene'];
const REPORT_CYCLE_WINDOWS = [30, 90, 180];
const REPORT_FLOW_WINDOWS = [30, 60, 90];

/** The last N closed sprints: committed vs completed points (newest first from the function; oldest first here). */
function report_velocity(PDO $pdo, int $projectId, int $limit = 6): array
{
    $st = $pdo->prepare('SELECT * FROM project_velocity(:p, :n)');
    $st->bindValue('p', $projectId, PDO::PARAM_INT);
    $st->bindValue('n', max(1, min(24, $limit)), PDO::PARAM_INT);
    $st->execute();
    return array_reverse($st->fetchAll());
}

/** The sprint's day-by-day scope, done and remaining, with the sprint row itself under 'sprint'. */
function report_burndown(PDO $pdo, int $sprintId): array
{
    $st = $pdo->prepare('SELECT * FROM mcp_sprints WHERE sprint_id = :s');
    $st->execute(['s' => $sprintId]);
    $sprint = $st->fetch() ?: null;
    if ($sprint === null) {
        return ['sprint' => null, 'days' => []];
    }
    $st = $pdo->prepare('SELECT * FROM sprint_burndown(:s)');
    $st->bindValue('s', $sprintId, PDO::PARAM_INT);
    $st->execute();
    return ['sprint' => $sprint, 'days' => $st->fetchAll()];
}

/** Cycle time by type over the window: resolved count, median, p85, mean (days). */
function report_cycle_time(PDO $pdo, int $projectId, int $days = 90): array
{
    $st = $pdo->prepare('SELECT * FROM project_cycle_time(:p, (current_date - CAST(:d AS integer))::date)');
    $st->bindValue('p', $projectId, PDO::PARAM_INT);
    $st->bindValue('d', max(1, min(365, $days)), PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll();
}

/** Cumulative flow: issues per status category per day over the window. */
function report_flow(PDO $pdo, int $projectId, int $days = 30): array
{
    $st = $pdo->prepare('SELECT * FROM project_flow(:p, (current_date - CAST(:d AS integer))::date)');
    $st->bindValue('p', $projectId, PDO::PARAM_INT);
    $st->bindValue('d', max(1, min(365, $days)), PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll();
}

/** What entered and left a sprint after it started, with who and when. */
function report_scope_changes(PDO $pdo, int $sprintId): array
{
    $st = $pdo->prepare('SELECT * FROM mcp_sprint_scope_events WHERE sprint_id = :s ORDER BY occurred_at, scope_event_id');
    $st->execute(['s' => $sprintId]);
    return $st->fetchAll();
}

/** What the Scrum Master flags: stale, unassigned in the sprint, unestimated in the next, blocked. */
function report_hygiene(PDO $pdo, int $projectId): array
{
    $st = $pdo->prepare('SELECT * FROM project_hygiene(:p) ORDER BY reason, key');
    $st->bindValue('p', $projectId, PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll();
}

/** The sprints a report can be about: the active one first, then closed ones newest first. */
function report_sprint_options(PDO $pdo, int $projectId): array
{
    $st = $pdo->prepare("SELECT sprint_id, name, state, start_date, end_date FROM mcp_sprints WHERE project_id = :p AND state IN ('active', 'closed')
                         ORDER BY state = 'active' DESC, closed_at DESC NULLS LAST, sprint_id DESC");
    $st->execute(['p' => $projectId]);
    return $st->fetchAll();
}
