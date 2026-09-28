<?php
declare(strict_types=1);
/** /projects/{KEY} and /projects/{KEY}/{tab} — one project: the overview and the tab strip (screen project-view). A tab is a URL. */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/projects/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/projects/present.php';
require_once dirname(__DIR__, 2) . '/app/features/issues/present.php';
require_once dirname(__DIR__, 2) . '/app/features/issues/markdown.php';
require_login();
$pdo = db();
$key = strtoupper(request_string('key'));
$project = $key !== '' && preg_match('/^[A-Z][A-Z0-9]{1,9}$/', $key) ? find_project_by_key($pdo, $key) : null;
if ($project === null) {
    refuse(404, 'Project not found.');            // the same sentence for a private project one is not on
}
$id = (int) $project['project_id'];
$tab = request_string('tab', 'overview');
if (!in_array($tab, PROJECT_TABS, true) || ($tab === 'settings' && !$project['i_administer'])) {
    $tab = 'overview';
}
$activity = $tab === 'overview' ? project_activity($pdo, $id, 10) : [];
log_activity($pdo, 'screen.view', 'project', $id, ['screen' => 'project-view', 'after' => ['tab' => $tab]]);
if (wants_json()) {
    respond_screen(['project' => present_project($project), 'tab' => $tab]);
}
render_screen($project['key'] . ' · ' . $project['name'], view('projects/view.php', ['project' => $project, 'tab' => $tab, 'activity' => $activity, 'tz' => member_timezone()]),
    ['activeNav' => 'projects-list', 'screen' => 'project-view', 'entity' => 'project', 'recordId' => (string) $id]);
