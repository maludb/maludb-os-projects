<?php
declare(strict_types=1);
/** Action `component_save` (log `component.save`): a new component, or a change by `component`. Gate: project.admin. */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/projects/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/epics/queries.php';
require_post();
verify_csrf();
require_human();
$pdo = db();
$componentId = request_integer('component') ?? request_integer('component_id') ?? request_integer('id');
$existing = $componentId === null ? null : find_component($pdo, $componentId);
if ($componentId !== null && $existing === null) {
    refuse(404, 'Component not found.');
}
$raw = request_string('project') ?: request_string('project_id');
$project = $existing !== null ? find_project($pdo, (int) $existing['project_id'])
    : (ctype_digit($raw) ? find_project($pdo, (int) $raw) : (preg_match('/^[A-Za-z][A-Za-z0-9]{1,9}$/', $raw) ? find_project_by_key($pdo, $raw) : null));
if ($project === null) {
    refuse(404, 'Project not found.');
}
$projectId = (int) $project['project_id'];
require_right_in('project.admin', $projectId);
$f = [
    'name' => trim(request_string('name')) ?: (string) ($existing['name'] ?? ''),
    'description' => array_key_exists('description', $_POST) ? (trim(request_string('description')) ?: null) : ($existing['description'] ?? null),
    'default_assignee_id' => array_key_exists('default_assignee', $_POST) || array_key_exists('default_assignee_id', $_POST)
        ? (request_integer('default_assignee') ?? request_integer('default_assignee_id')) : ($existing['default_assignee_id'] ?? null),
];
$errors = [];
if ($f['name'] === '' || mb_strlen($f['name']) > 60) { $errors[] = 'A component needs a name of up to 60 characters.'; }
if ($f['default_assignee_id'] !== null) {
    $m = find_member_by_id($pdo, $f['default_assignee_id']);
    if ($m === null || $m['status'] !== 'active') { $errors[] = 'The default assignee must be an active member here.'; }
}
if ($errors !== []) {
    refuse(422, implode(' ', $errors));
}
$pdo->beginTransaction();
try {
    $row = save_component($pdo, $projectId, $componentId, $f, (int) current_member_id());
    log_activity($pdo, 'component.save', 'component', (int) $row['id'], ['before' => $existing !== null ? ['name' => $existing['name'], 'default_assignee_id' => $existing['default_assignee_id']] : null,
        'after' => ['name' => $row['name'], 'default_assignee_id' => $row['default_assignee_id'], 'project_key' => $project['key']], 'department_id' => $project['department_id']]);
    $pdo->commit();
} catch (RuntimeException $e) {
    $pdo->rollBack();
    refuse(422, $e->getMessage());
}
emit_action_status(true, ['did' => ($componentId === null ? 'Added component ' : 'Saved component ') . $row['name'], 'record_id' => (int) $row['id'], 'refresh' => 'labelsChanged']);
hx_trigger('labelsChanged');
if (wants_json()) { respond_saved(['did' => 'Saved component ' . $row['name'], 'record_id' => (int) $row['id'], 'location' => '/projects/' . $project['key'] . '/settings?section=labels']); }
if (is_htmx_request()) { http_response_code(204); exit; }
redirect('/projects/' . $project['key'] . '/settings?section=labels');
