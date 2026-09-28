<?php
declare(strict_types=1);
/** /projects/{KEY}/issues — the project's issues as a ledger with every filter (screen issues-list). Pattern B on #issues-list-results. */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/projects/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/projects/present.php';
require_once dirname(__DIR__, 2) . '/app/features/issues/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/issues/present.php';
require_login();
$pdo = db();
$key = strtoupper(request_string('key'));
$project = $key !== '' && preg_match('/^[A-Z][A-Z0-9]{1,9}$/', $key) ? find_project_by_key($pdo, $key) : null;
if ($project === null) {
    refuse(404, 'Project not found.');
}
$id = (int) $project['project_id'];
$me = (int) current_member_id();
$list = static fn (string $n): array => array_values(array_filter(array_map('strval', (array) ($_GET[$n] ?? [])), 'strlen'));
$filters = [
    'q' => request_string('q'), 'type' => $list('type'), 'status' => $list('status'), 'category' => request_string('category'),
    'assignee' => request_string('assignee'), 'reporter' => request_integer('reporter'), 'sprint' => request_string('sprint'), 'epic' => request_integer('epic'),
    'label' => request_string('label'), 'component' => request_integer('component'), 'priority' => $list('priority'), 'due' => request_string('due'),
    'blocked' => request_bool('blocked'), 'updated_since' => request_integer('updated_since'), 'include_subtasks' => request_bool('subtasks'), 'me' => $me,
];
$savedId = request_integer('filter');
$saved = saved_filters_for($pdo, $id);
if ($savedId !== null) {
    foreach ($saved as $sf) {
        if ((int) $sf['saved_filter_id'] === $savedId) {
            $stored = is_array($sf['filter']) ? $sf['filter'] : (json_decode((string) $sf['filter'], true) ?: []);
            foreach ($stored as $k => $v) { if (array_key_exists($k, $filters) && $k !== 'me') { $filters[$k] = $v; } }
        }
    }
}
$sort = request_string('sort', 'rank');
$page = max(1, (int) (request_integer('page') ?? 1));
$result = find_issues($pdo, $id, $filters, $sort, $page);
$pages = max(1, (int) ceil($result['total'] / ISSUES_PER_PAGE));
if (wants_json()) {
    log_activity($pdo, 'screen.view', 'project', $id, ['screen' => 'issues-list', 'after' => ['tab' => 'issues']]);
    respond_screen(['project' => present_project_card($project), 'issues' => array_map('present_issue_row', $result['rows']), 'total' => $result['total'], 'page' => $page, 'pages' => $pages]);
}
$data = ['project' => $project, 'rows' => $result['rows'], 'total' => $result['total'], 'page' => $page, 'pages' => $pages, 'filters' => $filters, 'sort' => $sort, 'tz' => member_timezone(), 'savedId' => $savedId];
$resultsHtml = view('projects/partials/issues-table.php', $data);
if (is_htmx_request() && ($_SERVER['HTTP_HX_TARGET'] ?? '') === 'issues-list-results') {
    header('Vary: HX-Request');
    echo $resultsHtml;
    exit;
}
log_activity($pdo, 'screen.view', 'project', $id, ['screen' => 'issues-list', 'after' => ['tab' => 'issues']]);
render_screen($project['key'] . ' · Issues', view('projects/issues.php', $data + ['resultsHtml' => $resultsHtml, 'options' => issue_form_options($pdo, $id), 'members' => member_options($pdo), 'saved' => $saved]),
    ['activeNav' => 'projects-list', 'screen' => 'issues-list', 'entity' => 'project', 'recordId' => (string) $id]);
