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
/** Action `comment_delete` (log comment.delete; category deletion for agents). Gate: the author, or project.admin. Soft: the body stays in the log. */
$commentId = request_integer('comment') ?? request_integer('comment_id');
$c = $commentId === null ? null : find_comment($pdo, $commentId);
if ($c === null || find_issue($pdo, (int) $c['issue_id']) === null) { refuse(404, 'Comment not found.'); }
if ((int) ($c['author_id'] ?? 0) !== $me && !has_right_in('project.admin', (int) $c['project_id'])) { refuse(403, 'Only the author or a project admin may delete a comment.'); }
$pdo->beginTransaction();
try {
    delete_comment($pdo, $commentId, $me);
    log_activity($pdo, 'comment.delete', 'comment', $commentId, ['before' => ['issue_key' => $c['issue_key'], 'body' => mb_substr((string) $c['body'], 0, 2000)]]);
    $pdo->commit();
} catch (Throwable $e) { $pdo->rollBack(); error_log('comment delete failed: ' . $e->getMessage()); refuse(500, 'The comment could not be deleted.'); }
issue_action_done((string) $c['issue_key'], 'Deleted the comment', ['record_id' => $commentId]);
