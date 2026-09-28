<?php
declare(strict_types=1);
/**
 * Action `issue_move_project` (log `issue.move_project`): the issue and its subtasks get a new key in the
 * target project, the old keys stay as aliases (the issue view 301s them), the source's sprint, epic,
 * component and labels are dropped, the status is mapped by category. Gate: project.admin in both projects.
 */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/projects/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/issues/respond.php';
require_once dirname(__DIR__, 2) . '/app/features/epics/queries.php';
require_post();
verify_csrf();
$pdo = db();
$issue = issue_from_request($pdo);
$sourceId = (int) $issue['project_id'];
$raw = request_string('project') ?: request_string('project_id') ?: request_string('target_project');
$target = ctype_digit($raw) ? find_project($pdo, (int) $raw) : (preg_match('/^[A-Za-z][A-Za-z0-9]{1,9}$/', $raw) ? find_project_by_key($pdo, $raw) : null);
if ($target === null) {
    refuse(404, 'Project not found.');
}
$targetId = (int) $target['project_id'];
require_right_in('project.admin', $sourceId);
require_right_in('project.admin', $targetId);
if ($targetId === $sourceId) {
    refuse(422, 'The issue is already in that project.');
}
if ($target['status'] !== 'active') {
    refuse(422, 'That project is archived.');
}
if ($issue['parent_issue_id'] !== null) {
    refuse(422, 'Move the parent; a subtask follows it.');
}
$source = find_project($pdo, $sourceId);
$pdo->beginTransaction();
try {
    [$oldKey, $newKey] = move_issue_to_project($pdo, (int) $issue['id'], $targetId, (int) current_member_id());
    log_activity($pdo, 'issue.move_project', 'issue', (int) $issue['id'], [
        'before' => ['key' => $oldKey, 'project_id' => $sourceId, 'project_key' => $source['key'] ?? null],
        'after' => ['key' => $newKey, 'project_id' => $targetId, 'project_key' => $target['key']], 'department_id' => $target['department_id']]);
    $pdo->commit();
} catch (RuntimeException $e) {
    $pdo->rollBack();
    refuse(422, $e->getMessage());
}
emit_action_status(true, ['did' => 'Moved ' . $oldKey . ' to ' . $target['key'] . ' as ' . $newKey, 'record_id' => (int) $issue['id'], 'key' => $newKey, 'refresh' => 'issueChanged']);
saved_go('/issues/' . $newKey, 'issueChanged');
