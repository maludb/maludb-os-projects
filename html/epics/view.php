<?php
declare(strict_types=1);
/** /epics/{id} — one epic: summary, progress, its issues, its trail (screen epic-view). */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/projects/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/projects/present.php';
require_once dirname(__DIR__, 2) . '/app/features/issues/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/issues/present.php';
require_once dirname(__DIR__, 2) . '/app/features/issues/markdown.php';
require_once dirname(__DIR__, 2) . '/app/features/epics/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/epics/present.php';
require_login();
$pdo = db();
$id = request_integer('id');
$epic = $id === null ? null : find_epic($pdo, $id);
if ($epic === null) {
    refuse(404, 'Epic not found.');
}
$project = find_project($pdo, (int) $epic['project_id']);
if ($project === null) {
    refuse(404, 'Epic not found.');
}
$issues = epic_issues($pdo, $id);
$activity = epic_activity($pdo, $id, 20);
$canWrite = has_right_in('epic.write', (int) $project['project_id']) && $project['status'] === 'active';
log_activity($pdo, 'screen.view', 'epic', $id, ['screen' => 'epic-view']);
if (wants_json()) {
    respond_screen(['epic' => present_epic($epic), 'project' => present_project_card($project), 'issues' => array_map('present_issue_row', $issues)]);
}
$openIssues = count(array_filter($issues, static fn (array $i): bool => $i['status_category'] !== 'done'));
render_screen($epic['name'] . ' · ' . $project['key'], view('epics/view.php', ['epic' => $epic, 'project' => $project, 'issues' => $issues, 'activity' => $activity,
    'canWrite' => $canWrite, 'openIssues' => $openIssues, 'tz' => member_timezone()]),
    ['activeNav' => 'projects-list', 'screen' => 'epic-view', 'entity' => 'epic', 'recordId' => (string) $id]);
