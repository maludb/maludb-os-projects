<?php
declare(strict_types=1);
/**
 * Action `team_remove` (log `team.remove`; confirm; agent category other): date the membership. Gate: project.admin in the project.
 * A private project keeps its lead: removing the lead of a private project is refused ("A private project needs a lead").
 */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/projects/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/projects/present.php';
require_once dirname(__DIR__, 2) . '/app/features/team/queries.php';
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
require_project_active($pdo, $id);
$memberId = request_integer('member') ?? request_integer('member_id');
if ($memberId === null) {
    refuse(422, 'Which member?');
}
$st = $pdo->prepare('SELECT * FROM mcp_project_team WHERE project_id = :p AND member_id = :m AND removed_at IS NULL');
$st->execute(['p' => $id, 'm' => $memberId]);
$row = $st->fetch();
if ($row === false) {
    refuse(404, 'That member is not on this team.');
}
if ($project['visibility'] === 'private' && $row['project_role'] === 'lead') {
    refuse(422, 'A private project needs a lead — name another lead first.');
}
$pdo->beginTransaction();
try {
    remove_team_member($pdo, $id, $memberId, (int) current_member_id());
    log_activity($pdo, 'team.remove', 'project', $id, ['before' => ['member_id' => $memberId, 'name' => $row['display_name'], 'display_name' => $row['display_name'], 'project_role' => $row['project_role'], 'project_key' => $project['key']],
        'department_id' => $project['department_id'] !== null ? (int) $project['department_id'] : null]);
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    error_log('team remove failed: ' . $e->getMessage());
    refuse(500, 'The team could not be changed.');
}
project_settings_done('/projects/' . $project['key'] . '/settings?tab=team', 'Removed ' . $row['display_name'] . ' from ' . $project['key'], ['record_id' => $memberId], 'teamChanged');
