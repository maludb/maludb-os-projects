<?php
declare(strict_types=1);
/**
 * /sprints/{id} — one sprint: header and actions, the burndown, its issues by category, its scope changes
 * (screen sprint-view). Region #sprint-view-body re-fetches on sprintChanged / issueChanged / backlogChanged.
 */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/projects/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/projects/present.php';
require_once dirname(__DIR__, 2) . '/app/features/issues/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/issues/present.php';
require_once dirname(__DIR__, 2) . '/app/features/sprints/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/sprints/present.php';
require_once dirname(__DIR__, 2) . '/app/views/shared/chart.php';   // chart_line for the burndown
require_login();
$pdo = db();
$sprint = sprint_from_request($pdo, 'id');
$sid = (int) $sprint['sprint_id'];
$projectId = (int) $sprint['project_id'];
require_can_see_project($projectId);
$project = find_project($pdo, $projectId);
if ($project === null) { refuse(404, 'Project not found.'); }
$can = ['plan' => has_right_in('sprint.plan', $projectId), 'run' => has_right_in('sprint.run', $projectId)];
$issues = sprint_issues($pdo, $sid);
$burndown = $sprint['state'] !== 'future' ? sprint_burndown_rows($pdo, $sid) : [];
$events = $sprint['state'] !== 'future' ? sprint_scope_events($pdo, $sid) : [];
$future = array_values(array_filter(find_sprints($pdo, $projectId), static fn (array $s): bool => $s['state'] === 'future'));
$active = array_values(array_filter(find_sprints($pdo, $projectId), static fn (array $s): bool => $s['state'] === 'active'));
if (wants_json()) {
    log_activity($pdo, 'screen.view', 'sprint', $sid, ['screen' => 'sprint-view']);
    respond_screen(['sprint' => present_sprint($sprint), 'project' => present_project_card($project), 'issues' => array_map('present_issue_row', $issues),
        'burndown' => array_map(static fn (array $r): array => ['day' => $r['day'], 'scope' => (float) $r['scope'], 'done' => (float) $r['done'], 'remaining' => (float) $r['remaining']], $burndown),
        'scope_events' => array_map('present_scope_event', $events), 'can' => $can]);
}
$data = ['sprint' => $sprint, 'project' => $project, 'issues' => $issues, 'burndown' => $burndown, 'events' => $events, 'future' => $future, 'anyActive' => $active !== [], 'can' => $can, 'tz' => member_timezone()];
if (is_htmx_request() && ($_SERVER['HTTP_HX_TARGET'] ?? '') === 'sprint-view-body') {
    header('Vary: HX-Request');
    echo view('sprints/partials/body.php', $data);
    exit;
}
log_activity($pdo, 'screen.view', 'sprint', $sid, ['screen' => 'sprint-view']);
render_screen($sprint['name'] . ' · ' . $project['key'], view('sprints/view.php', $data), ['activeNav' => 'projects-list', 'screen' => 'sprint-view', 'entity' => 'sprint', 'recordId' => (string) $sid]);
