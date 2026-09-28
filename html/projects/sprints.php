<?php
declare(strict_types=1);
/** /projects/{KEY}/sprints — every sprint of the project as a ledger (screen sprints-list). Region #sprints-list re-fetches on sprintChanged. */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/projects/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/projects/present.php';
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
$sprints = find_sprints($pdo, $id);
$can = ['plan' => has_right_in('sprint.plan', $id), 'run' => has_right_in('sprint.run', $id)];
if (wants_json()) {
    log_activity($pdo, 'screen.view', 'project', $id, ['screen' => 'sprints-list', 'after' => ['tab' => 'sprints']]);
    respond_screen(['project' => present_project_card($project), 'sprints' => array_map('present_sprint', $sprints), 'can' => $can]);
}
$data = ['project' => $project, 'sprints' => $sprints, 'can' => $can, 'tz' => member_timezone()];
$listHtml = view('projects/partials/sprints-table.php', $data);
if (is_htmx_request() && ($_SERVER['HTTP_HX_TARGET'] ?? '') === 'sprints-list') {
    header('Vary: HX-Request');
    echo $listHtml;
    exit;
}
log_activity($pdo, 'screen.view', 'project', $id, ['screen' => 'sprints-list', 'after' => ['tab' => 'sprints']]);
render_screen($project['key'] . ' · Sprints', view('projects/sprints.php', $data + ['listHtml' => $listHtml]),
    ['activeNav' => 'projects-list', 'screen' => 'sprints-list', 'entity' => 'project', 'recordId' => (string) $id]);
