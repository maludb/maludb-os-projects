<?php
declare(strict_types=1);
/** /projects — every project the member may see, as cards by department (screen projects-list). Pattern B; the results region alone on search/paging. */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/projects/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/projects/present.php';
require_login();
$pdo = db();
$filters = [
    'q' => request_string('q'), 'department' => request_integer('department'), 'status' => request_string('status', 'active') ?: 'active',
    'mine' => request_bool('mine'), 'me' => (int) current_member_id(),
];
$sort = request_string('sort', 'name');
$page = max(1, (int) (request_integer('page') ?? 1));
$result = find_projects($pdo, $filters, $sort, $page);
$pages = max(1, (int) ceil($result['total'] / PROJECTS_PER_PAGE));
if (wants_json()) {
    log_screen_view($pdo, 'projects-list');
    respond_screen(['projects' => array_map('present_project_card', $result['rows']), 'total' => $result['total'], 'page' => $page, 'pages' => $pages]);
}
$resultsHtml = view('projects/partials/cards.php', ['rows' => $result['rows'], 'total' => $result['total'], 'page' => $page, 'pages' => $pages, 'filters' => $filters, 'sort' => $sort]);
if (is_htmx_request() && ($_SERVER['HTTP_HX_TARGET'] ?? '') === 'projects-list-results') {
    header('Vary: HX-Request');
    echo $resultsHtml;
    exit;
}
log_screen_view($pdo, 'projects-list');
render_screen('Projects', view('projects/page.php', ['resultsHtml' => $resultsHtml, 'filters' => $filters, 'sort' => $sort,
    'departments' => department_options($pdo), 'canCreate' => is_projects_admin() || has_right('project.admin')]),
    ['activeNav' => 'projects-list', 'screen' => 'projects-list', 'entity' => 'project']);
