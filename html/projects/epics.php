<?php
declare(strict_types=1);
/** /projects/{KEY}/epics — the project's epics as cards with progress (screen epics-list). Filter `status`. */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/projects/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/projects/present.php';
require_once dirname(__DIR__, 2) . '/app/features/epics/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/epics/present.php';
require_login();
$pdo = db();
$key = strtoupper(request_string('key'));
$project = $key !== '' && preg_match('/^[A-Z][A-Z0-9]{1,9}$/', $key) ? find_project_by_key($pdo, $key) : null;
if ($project === null) {
    refuse(404, 'Project not found.');
}
$id = (int) $project['project_id'];
$status = request_string('status');
$epics = find_epics($pdo, $id, $status);
$canWrite = has_right_in('epic.write', $id) && $project['status'] === 'active';
log_activity($pdo, 'screen.view', 'project', $id, ['screen' => 'epics-list', 'after' => ['tab' => 'epics']]);
if (wants_json()) {
    respond_screen(['project' => present_project_card($project), 'epics' => array_map('present_epic', $epics), 'status' => $status]);
}
$resultsHtml = view('epics/partials/cards.php', ['project' => $project, 'epics' => $epics, 'status' => $status]);
if (is_htmx_request() && ($_SERVER['HTTP_HX_TARGET'] ?? '') === 'epics-list-results') {
    header('Vary: HX-Request');
    echo $resultsHtml;
    exit;
}
render_screen($project['key'] . ' · Epics', view('projects/epics.php', ['project' => $project, 'resultsHtml' => $resultsHtml, 'status' => $status, 'canWrite' => $canWrite]),
    ['activeNav' => 'projects-list', 'screen' => 'epics-list', 'entity' => 'project', 'recordId' => (string) $id]);
