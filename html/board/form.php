<?php
declare(strict_types=1);
/** /board/form?key=KEY — the board's settings (screen `board-settings`): columns, their statuses, WIP limits, the swimlane, the mode. Slice 6's settings tab renders the same partial. Gate: board.configure. */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/projects/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/projects/present.php';
require_once dirname(__DIR__, 2) . '/app/features/issues/present.php';
require_once dirname(__DIR__, 2) . '/app/features/board/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/board/present.php';
require_login();
$pdo = db();
$key = strtoupper(request_string('key'));
$project = $key !== '' && preg_match('/^[A-Z][A-Z0-9]{1,9}$/', $key) ? find_project_by_key($pdo, $key) : null;
if ($project === null) {
    refuse(404, 'Project not found.');
}
$id = (int) $project['project_id'];
require_right_in('board.configure', $id);
$config = find_board_config($pdo, $id);
// Pattern A: a blank row appended by the form's "Add column" button.
if (request_string('fragment') === 'row') {
    $n = max(0, (int) (request_integer('n') ?? 0));
    echo view('projects/partials/board-form.php', ['project' => $project, 'config' => $config, 'errors' => [], 'rowOnly' => $n]);
    exit;
}
log_activity($pdo, 'screen.view', 'project', $id, ['screen' => 'board-settings']);
if (wants_json()) {
    respond_screen(['project' => present_project_card($project), 'config' => present_board_config($config)]);
}
render_screen($project['key'] . ' · Board settings', view('projects/partials/board-form.php', ['project' => $project, 'config' => $config, 'errors' => []]),
    ['activeNav' => 'projects-list', 'screen' => 'board-settings', 'entity' => 'project', 'recordId' => (string) $id]);
