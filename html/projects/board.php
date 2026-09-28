<?php
declare(strict_types=1);
/**
 * /projects/{KEY}/board — the board (screen `board`, slice 3): the active sprint's issues (scrum) or every open
 * issue (kanban) by column and swimlane. Pattern B: the columns region alone re-fetches itself (html/board/columns.php)
 * every 30 s and on issueChanged / boardChanged. Params: lane (none|assignee|epic), assignee (me|none|id), epic, type, q.
 */
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
$lane = request_string('lane');
$board = find_board($pdo, $id, $filters, $lane);
$canConfigure = has_right_in('board.configure', $id);
$canWork = (bool) $project['i_can_work'];
log_activity($pdo, 'screen.view', 'project', $id, ['screen' => 'board', 'after' => ['tab' => 'board', 'lane' => $board['lane']]]);
if (wants_json()) {
    respond_screen(['project' => present_project_card($project), 'board' => present_board($board), 'filters' => $filters, 'can' => ['configure' => $canConfigure, 'work' => $canWork]]);
}
$options = issue_form_options($pdo, $id);
render_screen($project['key'] . ' · Board', view('projects/board.php', ['project' => $project, 'board' => $board, 'filters' => $filters, 'canConfigure' => $canConfigure,
    'canWork' => $canWork, 'epics' => $options['epics'], 'members' => member_options($pdo), 'tz' => member_timezone()]),
    ['activeNav' => 'projects-list', 'screen' => 'board', 'entity' => 'project', 'recordId' => (string) $id]);
