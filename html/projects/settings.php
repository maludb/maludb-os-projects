<?php
declare(strict_types=1);
/**
 * /projects/{KEY}/settings — the project's settings tab. Slice 4 serves `?section=labels` (labels and
 * components, region #project-labels-components); every other section still renders through view.php
 * until slice 6 fills it.
 */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
if (($_GET['section'] ?? '') !== 'labels') {
    $_GET['tab'] = 'settings';
    require __DIR__ . '/view.php';
    return;
}
require_once dirname(__DIR__, 2) . '/app/features/projects/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/projects/present.php';
require_once dirname(__DIR__, 2) . '/app/features/epics/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/epics/present.php';
require_login();
$pdo = db();
$key = strtoupper(request_string('key'));
$project = $key !== '' && preg_match('/^[A-Z][A-Z0-9]{1,9}$/', $key) ? find_project_by_key($pdo, $key) : null;
if ($project === null || !$project['i_administer']) {
    refuse(404, 'Project not found.');
}
$id = (int) $project['project_id'];
$labels = find_labels($pdo, $id, true);
$components = find_components($pdo, $id, true);
$canAdmin = has_right_in('project.admin', $id) && $project['status'] === 'active';
log_activity($pdo, 'screen.view', 'project', $id, ['screen' => 'labels-settings', 'after' => ['tab' => 'settings', 'section' => 'labels']]);
if (wants_json()) {
    respond_screen(['project' => present_project_card($project), 'labels' => array_map('present_label', $labels), 'components' => array_map('present_component', $components)]);
}
$html = view('projects/partials/labels-components.php', ['project' => $project, 'labels' => $labels, 'components' => $components, 'members' => member_options($pdo), 'canAdmin' => $canAdmin]);
if (is_htmx_request() && ($_SERVER['HTTP_HX_TARGET'] ?? '') === 'project-labels-components') {
    header('Vary: HX-Request');
    echo $html;
    exit;
}
$base = '/projects/' . $project['key'];
$page = '<div class="page-header" id="labels-settings-header"><div class="page-header-left d-flex align-items-center"><div class="page-header-title"><h5 class="m-b-10"><span class="badge bg-soft-secondary text-dark me-2">' . e($project['key']) . '</span>Labels and components</h5></div>'
    . '<ul class="breadcrumb"><li class="breadcrumb-item"><a href="/projects" hx-get="/projects" hx-target="#page-content" hx-push-url="/projects">Projects</a></li>'
    . '<li class="breadcrumb-item"><a href="' . e($base) . '" hx-get="' . e($base) . '" hx-target="#page-content" hx-push-url="' . e($base) . '">' . e($project['key']) . '</a></li>'
    . '<li class="breadcrumb-item"><a href="' . e($base) . '/settings" hx-get="' . e($base) . '/settings" hx-target="#page-content" hx-push-url="' . e($base) . '/settings">Settings</a></li><li class="breadcrumb-item">Labels</li></ul></div></div>'
    . '<div class="main-content" id="labels-settings-content"><div class="card border-top-0" id="labels-settings-tabs-card">' . view('projects/partials/tabs.php', ['project' => $project, 'tab' => 'settings'])
    . '<div class="card-body">' . $html . '</div></div></div>';
render_screen($project['key'] . ' · Labels and components', $page, ['activeNav' => 'projects-list', 'screen' => 'labels-settings', 'entity' => 'project', 'recordId' => (string) $id]);
