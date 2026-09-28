<?php
declare(strict_types=1);
/** Action `component_archive` (log `component.archive`): issues keep it; the pickers stop offering it. Gate: project.admin. */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/projects/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/epics/queries.php';
require_post();
verify_csrf();
require_human();
$pdo = db();
$id = request_integer('component') ?? request_integer('component_id') ?? request_integer('id');
$component = $id === null ? null : find_component($pdo, $id);
if ($component === null) {
    refuse(404, 'Component not found.');
}
$project = find_project($pdo, (int) $component['project_id']);
if ($project === null) {
    refuse(404, 'Component not found.');
}
require_right_in('project.admin', (int) $project['project_id']);
require_project_active($pdo, (int) $project['project_id']);
$pdo->beginTransaction();
archive_component($pdo, $id, (int) current_member_id());
log_activity($pdo, 'component.archive', 'component', $id, ['before' => ['name' => $component['name']], 'department_id' => $project['department_id']]);
$pdo->commit();
emit_action_status(true, ['did' => 'Archived component ' . $component['name'], 'record_id' => $id, 'refresh' => 'labelsChanged']);
hx_trigger('labelsChanged');
if (wants_json()) { respond_saved(['did' => 'Archived component ' . $component['name'], 'record_id' => $id]); }
if (is_htmx_request()) { http_response_code(204); exit; }
redirect('/projects/' . $project['key'] . '/settings?section=labels');
