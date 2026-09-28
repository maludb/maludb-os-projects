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
/** Action `attachment_delete` (log attachment.delete; category deletion for agents). Gate: the uploader, or project.admin. Soft, and the file goes. */
$id = request_integer('attachment') ?? request_integer('attachment_id');
$a = $id === null ? null : find_attachment($pdo, $id);
if ($a === null || $a['deleted_at'] !== null || find_issue($pdo, (int) $a['issue_id']) === null) { refuse(404, 'Attachment not found.'); }
if ((int) ($a['uploaded_by'] ?? 0) !== $me && !has_right_in('project.admin', (int) $a['project_id'])) { refuse(403, 'Only the uploader or a project admin may remove an attachment.'); }
$pdo->beginTransaction();
try {
    delete_attachment($pdo, $id, $me);
    log_activity($pdo, 'attachment.delete', 'issue', (int) $a['issue_id'], ['before' => ['attachment_id' => $id, 'file_name' => $a['file_name']]]);
    $pdo->commit();
} catch (Throwable $e) { $pdo->rollBack(); error_log('attachment delete failed: ' . $e->getMessage()); refuse(500, 'The attachment could not be removed.'); }
issue_action_done((string) $a['issue_key'], 'Removed ' . $a['file_name'], ['record_id' => $id]);
