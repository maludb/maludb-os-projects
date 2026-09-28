<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';
require_once dirname(__DIR__, 3) . '/app/features/projects/queries.php';
require_once dirname(__DIR__, 3) . '/app/features/issues/queries.php';
require_once dirname(__DIR__, 3) . '/app/features/issues/present.php';
require_once dirname(__DIR__, 3) . '/app/features/issues/respond.php';
require_post();
verify_csrf();
require_login();
$pdo = db();
$me = (int) current_member_id();
/** Action `comment_add` (log comment.add), or an edit of one's own with `comment` (log comment.edit). Gate: comment.write in the project, or an admitted agent. */
$body = trim((string) ($_POST['body'] ?? $_POST['comment_body'] ?? $_POST['text'] ?? ''));
if ($body === '' || mb_strlen($body) > 20000) { refuse(422, 'A comment is 1 to 20,000 characters.'); }
$commentId = request_integer('comment') ?? request_integer('comment_id');
if ($commentId !== null) {
    $c = find_comment($pdo, $commentId);
    if ($c === null || find_issue($pdo, (int) $c['issue_id']) === null) { refuse(404, 'Comment not found.'); }
    if ((int) ($c['author_id'] ?? 0) !== $me) { refuse(403, 'Only the author may edit a comment.'); }
    $pdo->beginTransaction();
    try {
        edit_comment($pdo, $commentId, $body, $me);
        log_activity($pdo, 'comment.edit', 'comment', $commentId, ['after' => ['issue_key' => $c['issue_key'], 'length' => mb_strlen($body)]]);
        $pdo->commit();
    } catch (Throwable $e) { $pdo->rollBack(); error_log('comment edit failed: ' . $e->getMessage()); refuse(500, 'The comment could not be saved.'); }
    issue_action_done((string) $c['issue_key'], 'Edited the comment', ['record_id' => $commentId]);
}
$issue = issue_from_request($pdo);
$projectId = (int) $issue['project_id'];
if (current_member()['member_kind'] !== 'agent') { require_right_in('comment.write', $projectId); } else { require_can_see_project($projectId); }
$pdo->beginTransaction();
try {
    $row = add_comment($pdo, (int) $issue['id'], $body, $me);
    log_activity($pdo, 'comment.add', 'comment', (int) $row['id'], ['after' => ['issue_key' => $issue['key'], 'length' => mb_strlen($body)]]);
    $pdo->commit();
} catch (Throwable $e) { $pdo->rollBack(); error_log('comment add failed: ' . $e->getMessage()); refuse(500, 'The comment could not be saved.'); }
issue_action_done((string) $issue['key'], 'Commented on ' . $issue['key'], ['record_id' => (int) $row['id']]);
