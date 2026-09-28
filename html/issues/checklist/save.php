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
/** Action `checklist_add` (log checklist_item.add). Gate: can_work. */
$issue = issue_from_request($pdo);
require_can_work((int) $issue['project_id']);
require_project_active($pdo, (int) $issue['project_id']);
$text = request_string('text') ?: request_string('item_text');
if ($text === '' || mb_strlen($text) > 500) { refuse(422, 'A checklist item is 1 to 500 characters.'); }
$pdo->beginTransaction();
try {
    $row = add_checklist_item($pdo, (int) $issue['id'], $text, $me);
    log_activity($pdo, 'checklist_item.add', 'issue', (int) $issue['id'], ['after' => ['item_id' => (int) $row['id'], 'text' => $text]]);
    $pdo->commit();
} catch (Throwable $e) { $pdo->rollBack(); error_log('checklist add failed: ' . $e->getMessage()); refuse(500, 'The item could not be added.'); }
issue_action_done((string) $issue['key'], 'Added a checklist item to ' . $issue['key'], ['record_id' => (int) $row['id']]);
