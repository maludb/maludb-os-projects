<?php
declare(strict_types=1);
/** /projects/new and /projects/{KEY}/edit — create or change a project (screens project-add / project-edit). */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/projects/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/projects/present.php';
require_login();
require_human();
$pdo = db();
$key = strtoupper(request_string('key'));
$project = null;
if ($key !== '') {
    $seen = find_project_by_key($pdo, $key);
    if ($seen === null) {
        refuse(404, 'Project not found.');
    }
    require_right_in('project.admin', (int) $seen['project_id']);
    $project = find_project_for_edit($pdo, (int) $seen['project_id']);
} elseif (!is_projects_admin() && !has_right('project.admin')) {
    refuse(403, 'Creating a project is for a Projects admin.');
}
$settings = projects_settings($pdo);
$record = $project ?? ['id' => null, 'key' => '', 'name' => '', 'description' => '', 'department_id' => null, 'lead_member_id' => null,
    'visibility' => $settings['default_visibility'], 'board_mode' => 'scrum', 'customer' => ''];
log_screen_view($pdo, $project === null ? 'project-add' : 'project-edit');
render_screen($project === null ? 'New project' : 'Edit ' . $project['key'], view('projects/form.php', ['record' => $record, 'isNew' => $project === null, 'errors' => [],
    'departments' => department_options($pdo), 'members' => member_options($pdo)]),
    ['activeNav' => 'projects-list', 'screen' => $project === null ? 'project-add' : 'project-edit', 'entity' => 'project', 'recordId' => (string) ($project['id'] ?? '')]);
