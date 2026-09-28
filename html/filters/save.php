<?php
declare(strict_types=1);
/** Action `filter_save` (log `saved_filter.save`): the current issues filters kept under a name, private or shared with the project. Own. */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/projects/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/projects/present.php';
require_once dirname(__DIR__, 2) . '/app/features/issues/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/issues/present.php';
require_post();
verify_csrf();
$pdo = db();
$me = (int) current_member_id();
$projectId = request_integer('project') ?? request_integer('project_id');
$project = $projectId !== null ? find_project($pdo, $projectId) : null;
if ($projectId !== null && $project === null) {
    refuse(404, 'Project not found.');
}
$name = trim(request_string('name'));
if ($name === '' || mb_strlen($name) > 80) {
    refuse(422, 'A filter needs a name of up to 80 characters.');
}
$list = static fn (string $n): array => array_values(array_filter(array_map('strval', (array) ($_POST[$n] ?? [])), 'strlen'));
$filter = array_filter([
    'q' => request_string('q'), 'type' => $list('type'), 'status' => $list('status'), 'category' => request_string('category'), 'assignee' => request_string('assignee'),
    'reporter' => request_integer('reporter'), 'sprint' => request_string('sprint'), 'epic' => request_integer('epic'), 'label' => request_string('label'),
    'component' => request_integer('component'), 'priority' => $list('priority'), 'due' => request_string('due'), 'blocked' => request_bool('blocked') ?: null,
    'updated_since' => request_integer('updated_since'), 'include_subtasks' => request_bool('subtasks') ?: null,
], static fn ($v) => $v !== null && $v !== '' && $v !== []);
$pdo->beginTransaction();
try {
    $row = save_filter($pdo, $me, $projectId, $name, $filter, request_bool('shared'));
    log_activity($pdo, 'saved_filter.save', 'saved_filter', (int) $row['id'], ['after' => ['name' => $name, 'project_id' => $projectId, 'is_shared' => (bool) $row['is_shared'], 'keys' => array_keys($filter)]]);
    emit_action_status(true, ['did' => 'Saved the filter ' . $name, 'record_id' => (int) $row['id']]);
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    error_log('filter save failed: ' . $e->getMessage());
    refuse(422, 'The filter could not be saved.');
}
if ($project !== null) {
    saved_go('/projects/' . $project['key'] . '/issues?filter=' . (int) $row['id']);
}
saved_go('/my-work');
