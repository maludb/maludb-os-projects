<?php
declare(strict_types=1);
/** The board's columns region alone (polled every 30 s and on issueChanged / boardChanged) — the same query as the page. Params as the page's. */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/projects/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/projects/present.php';
require_once dirname(__DIR__, 2) . '/app/features/issues/queries.php';
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
$filters = ['assignee' => request_string('assignee'), 'epic' => request_integer('epic'), 'type' => request_string('type'), 'q' => request_string('q'), 'me' => (int) current_member_id()];
$board = find_board($pdo, $id, $filters, request_string('lane'));
if (wants_json()) {
    respond_screen(['board' => present_board($board)]);
}
header('Vary: HX-Request');
echo view('projects/partials/board-columns.php', ['project' => $project, 'board' => $board, 'filters' => $filters, 'canWork' => (bool) $project['i_can_work']]);
