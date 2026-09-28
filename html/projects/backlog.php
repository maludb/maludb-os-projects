<?php
declare(strict_types=1);
/**
 * /projects/{KEY}/backlog — the sprint boxes and the ranked backlog (screen backlog). Two regions re-fetch
 * themselves: #backlog-boxes (the active and future sprints) and #backlog-list (the ranked backlog with its filters).
 */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/projects/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/projects/present.php';
require_once dirname(__DIR__, 2) . '/app/features/issues/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/issues/present.php';
require_once dirname(__DIR__, 2) . '/app/features/sprints/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/sprints/present.php';
require_login();
$pdo = db();
$key = strtoupper(request_string('key'));
$project = $key !== '' && preg_match('/^[A-Z][A-Z0-9]{1,9}$/', $key) ? find_project_by_key($pdo, $key) : null;
if ($project === null) {
    refuse(404, 'Project not found.');
}
$id = (int) $project['project_id'];
$me = (int) current_member_id();
$filters = ['q' => request_string('q'), 'epic' => request_integer('epic'), 'assignee' => request_string('assignee'), 'type' => request_string('type'), 'unestimated' => request_bool('unestimated'), 'me' => $me];
$can = ['rank' => has_right_in('backlog.rank', $id), 'plan' => has_right_in('sprint.plan', $id), 'run' => has_right_in('sprint.run', $id), 'estimate' => has_right_in('backlog.rank', $id)];
$boxes = find_sprint_boxes($pdo, $id);
$rows = find_backlog($pdo, $id, $filters);
if (wants_json()) {
    log_activity($pdo, 'screen.view', 'project', $id, ['screen' => 'backlog', 'after' => ['tab' => 'backlog']]);
    respond_screen(['project' => present_project_card($project),
        'sprints' => array_map(static fn (array $b): array => present_sprint($b) + ['issues_list' => array_map('present_issue_row', $b['issue_rows'])], $boxes),
        'backlog' => array_map('present_issue_row', $rows), 'can' => $can]);
}
$settings = projects_settings($pdo);
$targets = [];                                                  // where "Move to…" can send an issue
foreach ($boxes as $b) { $targets[(int) $b['sprint_id']] = $b['name'] . ($b['state'] === 'active' ? ' (active)' : ''); }
$data = ['project' => $project, 'boxes' => $boxes, 'rows' => $rows, 'filters' => $filters, 'can' => $can, 'settings' => $settings, 'targets' => $targets, 'tz' => member_timezone()];
$region = is_htmx_request() ? ($_SERVER['HTTP_HX_TARGET'] ?? '') : '';
if ($region === 'backlog-boxes') {
    header('Vary: HX-Request');
    echo view('projects/partials/backlog-boxes.php', $data);
    exit;
}
if ($region === 'backlog-list') {
    header('Vary: HX-Request');
    echo view('projects/partials/backlog-list.php', $data);
    exit;
}
log_activity($pdo, 'screen.view', 'project', $id, ['screen' => 'backlog', 'after' => ['tab' => 'backlog']]);
render_screen($project['key'] . ' · Backlog', view('projects/backlog.php', $data + ['options' => issue_form_options($pdo, $id), 'members' => member_options($pdo)]),
    ['activeNav' => 'projects-list', 'screen' => 'backlog', 'entity' => 'project', 'recordId' => (string) $id]);
