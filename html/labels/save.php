<?php
declare(strict_types=1);
/** Action `label_save` (log `label.save`): a new label, or rename/recolour by `label`. Gate: project.admin in the project. */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/projects/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/epics/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/epics/present.php';
require_post();
verify_csrf();
require_human();
$pdo = db();
$labelId = request_integer('label') ?? request_integer('label_id') ?? request_integer('id');
$existing = $labelId === null ? null : find_label($pdo, $labelId);
if ($labelId !== null && $existing === null) {
    refuse(404, 'Label not found.');
}
$raw = request_string('project') ?: request_string('project_id');
$project = $existing !== null ? find_project($pdo, (int) $existing['project_id'])
    : (ctype_digit($raw) ? find_project($pdo, (int) $raw) : (preg_match('/^[A-Za-z][A-Za-z0-9]{1,9}$/', $raw) ? find_project_by_key($pdo, $raw) : null));
if ($project === null) {
    refuse(404, 'Project not found.');
}
$projectId = (int) $project['project_id'];
require_right_in('project.admin', $projectId);
require_project_active($pdo, $projectId);
$name = strtolower(trim(request_string('name'))) ?: (string) ($existing['name'] ?? '');
$colour = request_string('colour') ?: (string) ($existing['colour'] ?? 'secondary');
$errors = [];
if (!preg_match('/^[a-z0-9][a-z0-9_-]{0,39}$/', $name)) { $errors[] = 'A label is lower-case letters, digits, hyphens and underscores (up to 40).'; }
if (!in_array($colour, LABEL_COLOURS, true)) { $errors[] = 'Colour is one of the theme\'s tones.'; }
if ($errors !== []) {
    refuse(422, implode(' ', $errors));
}
$pdo->beginTransaction();
try {
    $row = save_label($pdo, $projectId, $labelId, $name, $colour, (int) current_member_id());
    log_activity($pdo, 'label.save', 'label', (int) $row['id'], ['before' => $existing !== null ? ['name' => $existing['name'], 'colour' => $existing['colour']] : null,
        'after' => ['name' => $row['name'], 'colour' => $row['colour'], 'project_key' => $project['key']], 'department_id' => $project['department_id']]);
    $pdo->commit();
} catch (RuntimeException $e) {
    $pdo->rollBack();
    refuse(422, $e->getMessage());
}
emit_action_status(true, ['did' => ($labelId === null ? 'Added label ' : 'Saved label ') . $row['name'], 'record_id' => (int) $row['id'], 'refresh' => 'labelsChanged']);
hx_trigger('labelsChanged');
if (wants_json()) { respond_saved(['did' => 'Saved label ' . $row['name'], 'record_id' => (int) $row['id'], 'location' => '/projects/' . $project['key'] . '/settings?section=labels']); }
if (is_htmx_request()) { http_response_code(204); exit; }
redirect('/projects/' . $project['key'] . '/settings?section=labels');
