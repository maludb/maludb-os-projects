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
/** Action `issue_unlink` (log issue.unlink). Gate: can_work on the link's issues. */
$linkId = request_integer('link') ?? request_integer('link_id');
$link = $linkId === null ? null : find_issue_link($pdo, $linkId);
if ($link === null || find_issue($pdo, (int) $link['from_issue_id']) === null) { refuse(404, 'Link not found.'); }
require_can_work((int) $link['project_id']);
require_project_active($pdo, (int) $link['project_id']);
$from = find_issue_for_edit($pdo, (int) $link['from_issue_id']); $to = find_issue_for_edit($pdo, (int) $link['to_issue_id']);
$pdo->beginTransaction();
try {
    unlink_issues($pdo, (int) $link['id'], $me);
    log_activity($pdo, 'issue.unlink', 'issue', (int) $link['from_issue_id'], ['before' => ['kind' => $link['kind'], 'from_key' => $from['key'] ?? null, 'to_key' => $to['key'] ?? null]]);
    $pdo->commit();
} catch (Throwable $e) { $pdo->rollBack(); error_log('unlink failed: ' . $e->getMessage()); refuse(500, 'The link could not be removed.'); }
issue_action_done((string) ($from['key'] ?? ''), 'Removed the link', []);
