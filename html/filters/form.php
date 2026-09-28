<?php
declare(strict_types=1);
/** /filters/new and /filters/{id}/edit — a saved filter's name, project and sharing (screen `filter-edit`). The keys are kept as saved. Own only. */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/filters/queries.php';
require_login();
$pdo = db();
$me = (int) current_member_id();
$id = request_integer('id');
$filter = null;
if ($id !== null) {
    $filter = find_saved_filter($pdo, $id);
    if ($filter === null || (int) $filter['owner_id'] !== $me) {
        refuse(404, 'Filter not found.');
    }
}
$projects = $pdo->query('SELECT project_id, key, name FROM mcp_projects WHERE archived_at IS NULL ORDER BY key')->fetchAll();
log_screen_view($pdo, 'filter-edit');
if (wants_json()) {
    respond_screen(['filter' => $filter === null ? null : ['saved_filter_id' => (int) $filter['saved_filter_id'], 'name' => $filter['name'], 'project_id' => $filter['project_id'] !== null ? (int) $filter['project_id'] : null, 'is_shared' => (bool) $filter['is_shared'], 'filter' => $filter['filter']],
        'projects' => array_map(static fn (array $p): array => ['id' => (int) $p['project_id'], 'key' => $p['key'], 'name' => $p['name']], $projects)]);
}
render_screen($filter === null ? 'New filter' : 'Edit filter', view('filters/form.php', ['filter' => $filter, 'projects' => $projects, 'errors' => [], 'values' => [
    'name' => $filter['name'] ?? '', 'project_id' => $filter['project_id'] ?? request_integer('project'), 'shared' => (bool) ($filter['is_shared'] ?? false),
]]), ['activeNav' => 'my-work', 'screen' => 'filter-edit', 'entity' => 'saved_filter', 'recordId' => (string) ($id ?? '')]);
