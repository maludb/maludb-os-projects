<?php
declare(strict_types=1);
/**
 * /projects/{KEY}/settings?tab=general|workflow|board|team|labels — the project's own rules (screen project-settings),
 * for project.admin of the project. Each tab a URL; an HTMX request targeting #project-settings-body gets the tab
 * alone (Pattern B). `?section=labels` (slice 4's aliases) still answers the labels region alone; `?tab=labels`
 * is the canonical form. `?tab=workflow&fragment=row&n=` appends a blank status row (Pattern A).
 */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/projects/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/projects/present.php';
require_once dirname(__DIR__, 2) . '/app/features/epics/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/epics/present.php';
require_once dirname(__DIR__, 2) . '/app/features/workflow/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/team/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/board/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/board/present.php';
require_once dirname(__DIR__, 2) . '/app/features/settings/present.php';
require_login();
$pdo = db();
$key = strtoupper(request_string('key'));
$project = $key !== '' && preg_match('/^[A-Z][A-Z0-9]{1,9}$/', $key) ? find_project_by_key($pdo, $key) : null;
if ($project === null || !$project['i_administer']) {
    refuse(404, 'Project not found.');
}
$id = (int) $project['project_id'];
$canAdmin = has_right_in('project.admin', $id) && $project['status'] === 'active';
$labelsRegion = static function () use ($pdo, $project, $id, $canAdmin): string {
    return view('projects/partials/labels-components.php', ['project' => $project, 'labels' => find_labels($pdo, $id, true), 'components' => find_components($pdo, $id, true),
        'members' => member_options($pdo), 'canAdmin' => $canAdmin]);
};
// Slice 4's region, refreshed by labelsChanged: the labels and components alone.
if (request_string('section') === 'labels') {
    $labels = find_labels($pdo, $id, true);
    $components = find_components($pdo, $id, true);
    log_activity($pdo, 'screen.view', 'project', $id, ['screen' => 'labels-settings', 'after' => ['tab' => 'settings', 'section' => 'labels']]);
    if (wants_json()) {
        respond_screen(['project' => present_project_card($project), 'labels' => array_map('present_label', $labels), 'components' => array_map('present_component', $components)]);
    }
    $html = $labelsRegion();
    if (is_htmx_request() && ($_SERVER['HTTP_HX_TARGET'] ?? '') === 'project-labels-components') {
        header('Vary: HX-Request');
        echo $html;
        exit;
    }
    $_GET['tab'] = 'labels';
}
$tab = request_string('tab', 'general');
if (!in_array($tab, PROJECT_SETTINGS_TABS, true)) {
    $tab = 'general';
}
$data = ['project' => $project, 'tab' => $tab, 'canAdmin' => $canAdmin, 'tz' => member_timezone()];
switch ($tab) {
    case 'workflow':
        $data['workflow'] = find_workflow($pdo, $id);
        $data['errors'] = [];
        if (request_string('fragment') === 'row') {                      // Pattern A: one blank status row
            echo view('projects/partials/settings-workflow.php', $data + ['rowOnly' => max(0, (int) (request_integer('n') ?? 0))]);
            exit;
        }
        break;
    case 'board':
        $data['config'] = find_board_config($pdo, $id);
        break;
    case 'team':
        $data['team'] = find_team($pdo, $id);
        $data['candidates'] = team_candidates($pdo, $id);
        break;
    case 'labels':
        $data['labelsHtml'] = $labelsRegion();
        break;
    default:
        $data['record'] = find_project_for_edit($pdo, $id);
        $data['departments'] = department_options($pdo);
        $data['members'] = member_options($pdo);
        $data['errors'] = [];
}
log_activity($pdo, 'screen.view', 'project', $id, ['screen' => 'project-settings', 'after' => ['tab' => $tab]]);
if (wants_json()) {
    $out = ['project' => present_project($project), 'tab' => $tab];
    if ($tab === 'workflow') { $out['workflow'] = present_workflow($data['workflow']); }
    if ($tab === 'board') { $out['config'] = present_board_config($data['config']); }
    if ($tab === 'team') { $out['team'] = array_map('present_team_member', $data['team']); $out['roles'] = PROJECT_ROLES; }
    if ($tab === 'labels') { $out['labels'] = array_map('present_label', find_labels($pdo, $id, true)); $out['components'] = array_map('present_component', find_components($pdo, $id, true)); }
    respond_screen($out);
}
$body = view('projects/partials/settings-body.php', $data);
if (is_htmx_request() && ($_SERVER['HTTP_HX_TARGET'] ?? '') === 'project-settings-body') {
    header('Vary: HX-Request');
    echo $body;
    exit;
}
render_screen($project['key'] . ' · Settings', view('projects/settings.php', $data + ['body' => $body]),
    ['activeNav' => 'projects-list', 'screen' => 'project-settings', 'entity' => 'project', 'recordId' => (string) $id]);
