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
/** Actions `watch` / `unwatch` (action=; log issue.watch / issue.unwatch). Gate: may see the project. */
$issue = issue_from_request($pdo);
require_can_see_project((int) $issue['project_id']);
$action = request_string('action', 'watch') === 'unwatch' ? 'unwatch' : 'watch';
$pdo->beginTransaction();
try {
    set_watch($pdo, (int) $issue['id'], $me, $action === 'watch');
    log_activity($pdo, 'issue.' . $action, 'issue', (int) $issue['id'], ['after' => ['key' => $issue['key']]]);
    $pdo->commit();
} catch (Throwable $e) { $pdo->rollBack(); error_log('watch failed: ' . $e->getMessage()); refuse(500, 'The watch could not be saved.'); }
issue_action_done((string) $issue['key'], ($action === 'watch' ? 'Watching ' : 'No longer watching ') . $issue['key'], ['record_id' => (int) $issue['id']]);
