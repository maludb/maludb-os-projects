<?php
declare(strict_types=1);
/**
 * Action `project_archive` (log `project.archive`; agent category deletion) — with `restore=1`, unarchive (`project_update`, log
 * `project.unarchive`). Gate: project.admin in the project. An archived project is read-only everywhere and leaves the list and
 * the launcher's counts; nothing is deleted.
 */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/projects/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/projects/present.php';
require_post();
verify_csrf();
require_login();
$pdo = db();
$raw = request_string('project') ?: request_string('project_id') ?: request_string('key');
$project = ctype_digit($raw) ? find_project($pdo, (int) $raw) : (preg_match('/^[A-Za-z][A-Za-z0-9]{1,9}$/', $raw) ? find_project_by_key($pdo, strtoupper($raw)) : null);
if ($project === null) {
    refuse(404, 'Project not found.');
}
$id = (int) $project['project_id'];
require_right_in('project.admin', $id);
$restore = request_bool('restore') || request_bool('unarchive');
$reason = mb_substr(trim(request_string('reason')), 0, 240) ?: null;
if ($restore && $project['status'] === 'active') {
    refuse(409, 'This project is not archived.');
}
if (!$restore && $project['status'] === 'archived') {
    refuse(409, 'This project is already archived.');
}
$pdo->beginTransaction();
try {
    $row = archive_project($pdo, $id, !$restore, (int) current_member_id());
    log_activity($pdo, $restore ? 'project.unarchive' : 'project.archive', 'project', $id, [
        'before' => ['status' => $project['status']], 'after' => ['status' => $row['status'], 'key' => $row['key'], 'name' => $row['name']] + ($reason !== null ? ['reason' => $reason] : []),
        'department_id' => $row['department_id'] !== null ? (int) $row['department_id'] : null]);
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    error_log('project archive failed: ' . $e->getMessage());
    refuse(500, 'The project could not be ' . ($restore ? 'unarchived' : 'archived') . '.');
}
emit_action_status(true, ['did' => ($restore ? 'Unarchived project ' : 'Archived project ') . $row['key'], 'record_id' => $id, 'refresh' => 'projectChanged']);
saved_go('/projects/' . $row['key'], 'projectChanged');
