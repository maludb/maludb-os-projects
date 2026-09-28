<?php
declare(strict_types=1);
/** Action `label_archive` (log `label.archive`): the label stays on its issues and leaves the pickers. Gate: project.admin. */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/projects/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/epics/queries.php';
require_post();
verify_csrf();
require_human();
$pdo = db();
$id = request_integer('label') ?? request_integer('label_id') ?? request_integer('id');
$label = $id === null ? null : find_label($pdo, $id);
if ($label === null) {
    refuse(404, 'Label not found.');
}
$project = find_project($pdo, (int) $label['project_id']);
if ($project === null) {
    refuse(404, 'Label not found.');
}
require_right_in('project.admin', (int) $project['project_id']);
$pdo->beginTransaction();
archive_label($pdo, $id, (int) current_member_id());
log_activity($pdo, 'label.archive', 'label', $id, ['before' => ['name' => $label['name']], 'department_id' => $project['department_id']]);
$pdo->commit();
emit_action_status(true, ['did' => 'Archived label ' . $label['name'], 'record_id' => $id, 'refresh' => 'labelsChanged']);
hx_trigger('labelsChanged');
if (wants_json()) { respond_saved(['did' => 'Archived label ' . $label['name'], 'record_id' => $id]); }
if (is_htmx_request()) { http_response_code(204); exit; }
redirect('/projects/' . $project['key'] . '/settings?section=labels');
