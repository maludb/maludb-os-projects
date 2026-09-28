<?php
declare(strict_types=1);
/**
 * /issues/{KEY-n} — one issue, whole (screen issue-view). An alias key 301s to the current key.
 * Regions: the fields column and each body section is a region that re-fetches itself with
 * `?region=<name>` when a handler answers `HX-Trigger: issueChanged` (Pattern D) — so every handler
 * answers 204 and the page keeps itself true.
 */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/projects/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/projects/present.php';
require_once dirname(__DIR__, 2) . '/app/features/issues/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/issues/present.php';
require_once dirname(__DIR__, 2) . '/app/features/issues/markdown.php';
require_login();
$pdo = db();
$key = strtoupper(request_string('key'));
$issue = $key === '' ? null : find_issue_by_key($pdo, $key);
if ($issue === null) {
    respond_not_found('Issue not found.');
}
if (isset($issue['alias_of'])) {
    header('Location: /issues/' . $issue['alias_of'], true, 301);
    exit;
}
$id = (int) $issue['issue_id'];
$projectId = (int) $issue['project_id'];
require_can_see_project($projectId);
$me = (int) current_member_id();
$project = find_project($pdo, $projectId);
$statuses = [];
$st = $pdo->prepare('SELECT * FROM mcp_workflow_statuses WHERE project_id = :p AND archived_at IS NULL ORDER BY sort_order');
$st->execute(['p' => $projectId]);
foreach ($st->fetchAll() as $s) {
    $s['can_move_to'] = present_pg_int_array($s['can_move_to'] ?? null);
    $statuses[(int) $s['status_id']] = $s;
}
$options = issue_form_options($pdo, $projectId);
$settings = projects_settings($pdo);
$canWork = can_work($projectId);
$canAdmin = has_right_in('project.admin', $projectId);
$dependents = issue_dependents($pdo, $id, $me);
$common = [
    'issue' => $issue, 'project' => $project, 'statuses' => $statuses, 'options' => $options, 'settings' => $settings, 'me' => $me,
    'canWork' => $canWork, 'canAdmin' => $canAdmin, 'canAccept' => has_right_in('issue.accept', $projectId), 'canComment' => has_right_in('comment.write', $projectId) || current_member()['member_kind'] === 'agent',
    'canLog' => has_right_in('worklog.write', $projectId), 'canEstimate' => has_right_in('backlog.rank', $projectId) || (int) ($issue['assignee_member_id'] ?? 0) === $me,
    'canDelete' => $canAdmin || ((int) ($issue['reporter_member_id'] ?? 0) === $me && array_sum($dependents) === 0),
    'members' => member_options($pdo), 'tz' => member_timezone(),
];
$regions = [
    'fields' => static fn (): string => view('issues/partials/fields.php', $common),
    'subtasks' => static fn (): string => view('issues/partials/subtasks.php', $common + ['children' => issue_children($pdo, $id)]),
    'links' => static fn (): string => view('issues/partials/links.php', $common + ['links' => issue_links($pdo, $id)]),
    'attachments' => static fn (): string => view('issues/partials/attachments.php', $common + ['attachments' => issue_attachments($pdo, $id)]),
    'checklist' => static fn (): string => view('issues/partials/checklist.php', $common + ['items' => issue_checklist($pdo, $id)]),
    'worklogs' => static fn (): string => view('issues/partials/worklogs.php', $common + ['worklogs' => issue_worklogs($pdo, $id)]),
    'comments' => static fn (): string => view('issues/partials/comments.php', $common + ['comments' => issue_comments($pdo, $id)]),
    'history' => static fn (): string => view('issues/partials/history.php', $common + ['history' => issue_history($pdo, $id)]),
];
$region = request_string('region');
if ($region !== '' && isset($regions[$region])) {
    header('Vary: HX-Request');
    echo $regions[$region]();
    exit;
}
log_activity($pdo, 'screen.view', 'issue', $id, ['screen' => 'issue-view']);
if (wants_json()) {
    respond_screen([
        'issue' => present_issue($issue), 'project' => $project === null ? null : present_project_card($project),
        'statuses' => array_values(array_map(static fn (array $s): array => ['id' => (int) $s['status_id'], 'name' => $s['name'], 'category' => $s['category'], 'can_move_to' => $s['can_move_to']], $statuses)),
        'subtasks' => array_map('present_issue_row', issue_children($pdo, $id)), 'links' => array_map('present_link', issue_links($pdo, $id)),
        'comments' => array_map('present_comment', issue_comments($pdo, $id)), 'checklist' => array_map('present_checklist_item', issue_checklist($pdo, $id)),
        'worklogs' => array_map('present_worklog', issue_worklogs($pdo, $id)), 'attachments' => array_map('present_attachment', issue_attachments($pdo, $id)),
        'watchers' => array_map(static fn (array $w): array => ['member_id' => (int) $w['member_id'], 'name' => $w['display_name'], 'kind' => $w['member_kind']], issue_watchers($pdo, $id)),
        'history' => array_map(static fn (array $a): array => ['at' => json_ts($a['occurred_at']), 'actor' => $a['actor_name'], 'action' => $a['action'], 'sentence' => issue_history_sentence($a)], issue_history($pdo, $id)),
        'can' => ['work' => $canWork, 'admin' => $canAdmin, 'delete' => $common['canDelete']],
    ]);
}
$html = view('issues/view.php', $common + ['regions' => $regions]);
render_screen($issue['key'] . ' · ' . $issue['summary'], $html, ['activeNav' => 'projects-list', 'screen' => 'issue-view', 'entity' => 'issue', 'recordId' => (string) $id]);
