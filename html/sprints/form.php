<?php
declare(strict_types=1);
/** /sprints/new?project=KEY and /sprints/{id}/edit — create or change a sprint (screens sprint-add / sprint-edit). Gate: sprint.plan in the project. */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/projects/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/sprints/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/sprints/present.php';
require_login();
require_human();
$pdo = db();
$id = request_integer('id');
$sprint = null;
if ($id !== null) {
    $seen = find_sprint($pdo, $id);
    if ($seen === null) { refuse(404, 'Sprint not found.'); }
    $project = find_project($pdo, (int) $seen['project_id']);
    require_right_in('sprint.plan', (int) $seen['project_id']);
    if ($seen['state'] === 'closed') { refuse(409, 'A closed sprint is history; it is not edited.'); }
    $sprint = find_sprint_for_edit($pdo, $id);
} else {
    $key = strtoupper(request_string('project'));
    $project = $key !== '' && preg_match('/^[A-Z][A-Z0-9]{1,9}$/', $key) ? find_project_by_key($pdo, $key) : null;
    if ($project === null) { refuse(404, 'Project not found.'); }
    require_right_in('sprint.plan', (int) $project['project_id']);
    if ($project['status'] === 'archived') { refuse(409, 'An archived project plans no sprint.'); }
}
$settings = projects_settings($pdo);
$record = $sprint ?? ['id' => null, 'name' => next_sprint_name($pdo, (int) $project['project_id']), 'goal' => '', 'start_date' => null, 'end_date' => null, 'sort_order' => null];
log_screen_view($pdo, $sprint === null ? 'sprint-add' : 'sprint-edit');
render_screen($sprint === null ? 'New sprint' : 'Edit ' . $sprint['name'], view('sprints/form.php', ['record' => $record, 'project' => $project, 'isNew' => $sprint === null, 'errors' => [], 'settings' => $settings]),
    ['activeNav' => 'projects-list', 'screen' => $sprint === null ? 'sprint-add' : 'sprint-edit', 'entity' => 'sprint', 'recordId' => (string) ($sprint['id'] ?? '')]);
