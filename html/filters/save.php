<?php
declare(strict_types=1);
/**
 * Action `filter_save` (log `saved_filter.save`): from the issues list, the current filters kept under a name; from the
 * filters form (id set), the name, project and sharing changed while the saved keys stay. Own only.
 */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/projects/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/filters/queries.php';
require_post();
verify_csrf();
$pdo = db();
$me = (int) current_member_id();
$id = request_integer('id');
$existing = null;
if ($id !== null) {
    $existing = find_saved_filter($pdo, $id);
    if ($existing === null || (int) $existing['owner_id'] !== $me) {
        refuse(404, 'Filter not found.');
    }
}
$projectId = request_integer('project') ?? request_integer('project_id');
$project = $projectId !== null ? find_project($pdo, $projectId) : null;
if ($projectId !== null && $project === null) {
    refuse(404, 'Project not found.');
}
$name = trim(request_string('name'));
$errors = [];
if ($name === '' || mb_strlen($name) > 80) {
    $errors['name'] = 'A filter needs a name of up to 80 characters.';
}
if ($errors === []) {
    $st = $pdo->prepare('SELECT 1 FROM saved_filters WHERE owner_id = :o AND project_id IS NOT DISTINCT FROM :p AND name = :n' . ($id !== null ? ' AND id <> :id' : ''));
    $st->execute(['o' => $me, 'p' => $projectId, 'n' => $name] + ($id !== null ? ['id' => $id] : []));
    if ($id !== null && $st->fetchColumn() !== false) {
        $errors['name'] = 'You already have a filter of that name here.';
    }
}
if ($errors !== []) {
    emit_action_status(false, ['errors' => array_values($errors)]);
    if (wants_json()) {
        respond_invalid(array_values($errors), $errors);
    }
    if ($id === null) {
        refuse(422, $errors['name']);
    }
    http_response_code(422);
    render_screen('Edit filter', view('filters/form.php', ['filter' => $existing, 'projects' => $pdo->query('SELECT project_id, key, name FROM mcp_projects WHERE archived_at IS NULL ORDER BY key')->fetchAll(),
        'errors' => $errors, 'values' => ['name' => $name, 'project_id' => $projectId, 'shared' => request_bool('shared')]]), ['activeNav' => 'my-work', 'screen' => 'filter-edit', 'entity' => 'saved_filter', 'recordId' => (string) $id]);
    exit;
}
if ($existing !== null) {
    $filter = $existing['filter'];   // the form changes the name, project and sharing only
} else {
    $list = static fn (string $n): array => array_values(array_filter(array_map('strval', (array) ($_POST[$n] ?? [])), 'strlen'));
    $filter = [
        'q' => request_string('q'), 'type' => $list('type'), 'status' => $list('status'), 'category' => request_string('category'), 'assignee' => request_string('assignee'),
        'reporter' => request_integer('reporter'), 'sprint' => request_string('sprint'), 'epic' => request_integer('epic'), 'label' => request_string('label'),
        'component' => request_integer('component'), 'priority' => $list('priority'), 'due' => request_string('due'), 'blocked' => request_bool('blocked') ?: null,
        'updated_since' => request_integer('updated_since'), 'include_subtasks' => request_bool('subtasks') ?: null, 'sort' => request_string('sort') ?: null,
    ];
}
$pdo->beginTransaction();
try {
    $row = save_saved_filter($pdo, $id, $me, $projectId, $name, $filter, request_bool('shared'));
    log_activity($pdo, 'saved_filter.save', 'saved_filter', (int) $row['id'], [
        'before' => $existing !== null ? ['name' => $existing['name'], 'project_id' => $existing['project_id'] !== null ? (int) $existing['project_id'] : null, 'is_shared' => (bool) $existing['is_shared']] : null,
        'after' => ['name' => $name, 'project_id' => $projectId, 'is_shared' => (bool) $row['is_shared'], 'keys' => array_keys(saved_filter_clean($filter))]]);
    emit_action_status(true, ['did' => 'Saved the filter ' . $name, 'record_id' => (int) $row['id']]);
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    error_log('filter save failed: ' . $e->getMessage());
    refuse(422, 'The filter could not be saved.');
}
if ($existing !== null) {
    saved_go('/filters');
}
if ($project !== null) {
    saved_go('/projects/' . $project['key'] . '/issues?filter=' . (int) $row['id']);
}
saved_go('/filters');
