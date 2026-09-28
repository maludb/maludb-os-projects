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
/** Action `checklist_toggle` (log checklist_item.toggle, after.is_done). Gate: can_work. */
$itemId = request_integer('item') ?? request_integer('checklist_item') ?? request_integer('item_id');
$item = $itemId === null ? null : find_checklist_item($pdo, $itemId);
if ($item === null || find_issue($pdo, (int) $item['issue_id']) === null) { refuse(404, 'Checklist item not found.'); }
require_can_work((int) $item['project_id']);
$done = array_key_exists('done', $_POST) ? request_bool('done') : !$item['is_done'];
$pdo->beginTransaction();
try {
    toggle_checklist_item($pdo, $itemId, $done, $me);
    log_activity($pdo, 'checklist_item.toggle', 'issue', (int) $item['issue_id'], ['after' => ['item_id' => $itemId, 'is_done' => $done, 'text' => $item['text']]]);
    $pdo->commit();
} catch (Throwable $e) { $pdo->rollBack(); error_log('checklist toggle failed: ' . $e->getMessage()); refuse(500, 'The item could not be changed.'); }
issue_action_done((string) $item['issue_key'], ($done ? 'Ticked ' : 'Unticked ') . $item['text'], ['record_id' => $itemId]);
