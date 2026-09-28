<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/projects/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/issues/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/issues/present.php';
require_once dirname(__DIR__, 2) . '/app/features/issues/respond.php';
require_post();
verify_csrf();
require_login();
$pdo = db();
$me = (int) current_member_id();
/** Action `issue_delete` (log issue.delete; category deletion for agents). Gate: project.admin, or the reporter while nothing hangs on it. */
$issue = issue_from_request($pdo);
$projectId = (int) $issue['project_id'];
require_can_see_project($projectId);
if (!has_right_in('project.admin', $projectId)) {
    if ((int) ($issue['reporter_member_id'] ?? 0) !== $me) { refuse(403, 'Only the reporter or a project admin may delete an issue.'); }
    $dep = issue_dependents($pdo, (int) $issue['id'], $me);
    if (array_sum($dep) > 0) { refuse(422, 'Others have built on this issue (subtasks, comments, worklogs or links); a project admin may delete it.'); }
}
$project = find_project($pdo, $projectId);
$pdo->beginTransaction();
try {
    log_activity($pdo, 'issue.delete', 'issue', (int) $issue['id'], ['before' => ['key' => $issue['key'], 'summary' => $issue['summary'], 'type' => $issue['type']]]);
    delete_issue($pdo, (int) $issue['id'], $me);
    $pdo->commit();
} catch (Throwable $e) { $pdo->rollBack(); error_log('delete failed: ' . $e->getMessage()); refuse(500, 'The issue could not be deleted.'); }
emit_action_status(true, ['did' => 'Deleted ' . $issue['key'], 'refresh' => 'issueChanged']);
saved_go('/projects/' . $project['key'] . '/issues', 'issueChanged');
