<?php
declare(strict_types=1);
/** /epics/new?project=KEY and /epics/{id}/edit — create or change an epic (screens epic-add / epic-edit). */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/projects/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/epics/queries.php';
require_login();
require_human();
$pdo = db();
$id = request_integer('id');
$epic = null;
if ($id !== null) {
    $seen = find_epic($pdo, $id);
    if ($seen === null) {
        refuse(404, 'Epic not found.');
    }
    $project = find_project($pdo, (int) $seen['project_id']);
    require_right_in('epic.write', (int) $seen['project_id']);
    $epic = find_epic_for_edit($pdo, $id);
} else {
    $key = strtoupper(request_string('project'));
    $project = preg_match('/^[A-Z][A-Z0-9]{1,9}$/', $key) ? find_project_by_key($pdo, $key) : null;
    if ($project === null) {
        refuse(404, 'Project not found.');
    }
    require_right_in('epic.write', (int) $project['project_id']);
}
$record = $epic ?? ['id' => null, 'name' => '', 'summary' => '', 'colour' => 'primary', 'target_date' => null, 'sort_order' => 0];
log_screen_view($pdo, $epic === null ? 'epic-add' : 'epic-edit');
render_screen($epic === null ? 'New epic' : 'Edit epic', view('epics/form.php', ['record' => $record, 'project' => $project, 'isNew' => $epic === null, 'errors' => []]),
    ['activeNav' => 'projects-list', 'screen' => $epic === null ? 'epic-add' : 'epic-edit', 'entity' => 'epic', 'recordId' => (string) ($epic['id'] ?? '')]);
