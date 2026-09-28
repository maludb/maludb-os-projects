<?php
declare(strict_types=1);
/** /filters — my saved filters and the shared ones I may see (screen `filters-list`). ?project=<id> narrows. */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/filters/queries.php';
require_login();
$pdo = db();
$me = (int) current_member_id();
$projectId = request_integer('project');
$rows = find_saved_filters($pdo, $me, $projectId);
$projects = $pdo->query('SELECT project_id, key, name FROM mcp_projects WHERE archived_at IS NULL ORDER BY key')->fetchAll();
log_screen_view($pdo, 'filters-list');
if (wants_json()) {
    respond_screen(['filters' => array_map(static fn (array $f): array => [
        'saved_filter_id' => (int) $f['saved_filter_id'], 'name' => (string) $f['name'], 'is_shared' => (bool) $f['is_shared'], 'mine' => (int) $f['owner_id'] === $me,
        'owner' => ['member_id' => (int) $f['owner_id'], 'name' => (string) $f['owner_name']],
        'project' => $f['project_id'] !== null ? ['id' => (int) $f['project_id'], 'key' => $f['project_key'], 'name' => $f['project_name']] : null,
        'filter' => $f['filter'], 'href' => saved_filter_href($f), 'updated_at' => json_ts($f['updated_at']),
    ], $rows), 'project_id' => $projectId]);
}
render_screen('Saved filters', view('filters/page.php', ['rows' => $rows, 'me' => $me, 'projectId' => $projectId, 'projects' => $projects, 'tz' => member_timezone()]),
    ['activeNav' => 'my-work', 'screen' => 'filters-list']);
