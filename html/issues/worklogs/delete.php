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
/** Action `worklog_delete` (log worklog.delete). Gate: one's own worklog only. */
$id = request_integer('worklog') ?? request_integer('worklog_id');
$w = $id === null ? null : find_worklog($pdo, $id);
if ($w === null || find_issue($pdo, (int) $w['issue_id']) === null) { refuse(404, 'Worklog not found.'); }
if ((int) $w['member_id'] !== $me) { refuse(403, 'Only the person who logged the hours may remove them.'); }
$pdo->beginTransaction();
try {
    delete_worklog($pdo, $id, $me);
    log_activity($pdo, 'worklog.delete', 'issue', (int) $w['issue_id'], ['before' => ['worklog_id' => $id, 'hours' => (float) $w['hours'], 'worked_on' => $w['worked_on']]]);
    $pdo->commit();
} catch (Throwable $e) { $pdo->rollBack(); error_log('worklog delete failed: ' . $e->getMessage()); refuse(500, 'The worklog could not be removed.'); }
issue_action_done((string) $w['issue_key'], 'Removed the worklog', ['record_id' => $id]);
