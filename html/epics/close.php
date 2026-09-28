<?php
declare(strict_types=1);
/**
 * Action `epic_close` (log `epic.close` / `epic.reopen`; the spec's name for both is `epic.status`): status done or cancelled (default done); `action=reopen` or
 * `status=open` reopens. Gate: epic.write in the project. Marking done with open issues is the screen's
 * confirm; the handler records what it was told.
 */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/projects/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/epics/queries.php';
require_post();
verify_csrf();
$pdo = db();
$id = request_integer('epic') ?? request_integer('epic_id') ?? request_integer('id');
$seen = $id === null ? null : find_epic($pdo, $id);
if ($seen === null) {
    refuse(404, 'Epic not found.');
}
$projectId = (int) $seen['project_id'];
require_right_in('epic.write', $projectId);
require_project_active($pdo, $projectId);
$project = find_project($pdo, $projectId);
$status = request_string('action') === 'reopen' ? 'open' : (request_string('status') ?: 'done');
if (!in_array($status, EPIC_STATUSES, true)) {
    refuse(422, 'An epic is open, done or cancelled.');
}
$pdo->beginTransaction();
try {
    [$before, $after] = set_epic_status($pdo, $id, $status, (int) current_member_id());
    log_activity($pdo, $status === 'open' ? 'epic.reopen' : 'epic.close', 'epic', $id, ['before' => ['status' => $before['status']], 'after' => ['status' => $after['status']], 'department_id' => $project['department_id'] ?? null]);
    $pdo->commit();
} catch (RuntimeException $e) {
    $pdo->rollBack();
    refuse(422, $e->getMessage());
}
$did = match ($status) { 'open' => 'Reopened epic ' . $seen['name'], 'cancelled' => 'Cancelled epic ' . $seen['name'], default => 'Marked epic ' . $seen['name'] . ' done' };
emit_action_status(true, ['did' => $did, 'record_id' => $id, 'refresh' => 'epicChanged']);
saved_go('/epics/' . $id, 'epicChanged');
