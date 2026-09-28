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
/** Actions `issue_accept` / withdraw (log issue.accept / issue.unaccept). Gate: issue.accept in the project; only a done issue. */
$issue = issue_from_request($pdo);
require_right_in('issue.accept', (int) $issue['project_id']);
require_project_active($pdo, (int) $issue['project_id']);
$accept = request_bool('accept') || request_string('accept') === '' && request_string('action') !== 'withdraw';
$cat = $pdo->prepare('SELECT category FROM mcp_workflow_statuses WHERE status_id = :s'); $cat->execute(['s' => (int) $issue['status_id']]);
if ($cat->fetchColumn() !== 'done') { refuse(422, 'Only a done issue can be accepted.'); }
$pdo->beginTransaction();
try {
    accept_issue($pdo, (int) $issue['id'], $accept, $me);
    log_activity($pdo, $accept ? 'issue.accept' : 'issue.unaccept', 'issue', (int) $issue['id'], ['after' => ['key' => $issue['key']]]);
    $pdo->commit();
} catch (Throwable $e) { $pdo->rollBack(); error_log('accept failed: ' . $e->getMessage()); refuse(500, 'The acceptance could not be saved.'); }
issue_action_done((string) $issue['key'], ($accept ? 'Accepted ' : 'Withdrew the acceptance of ') . $issue['key'], ['record_id' => (int) $issue['id']]);
