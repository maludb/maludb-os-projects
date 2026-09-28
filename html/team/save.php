<?php
declare(strict_types=1);
/**
 * Action `team_add` (log `team.add`): put a member on the project's team in a role, or change the role of one already on it.
 * Gate: project.admin in the project. A member must be active in the mirror. A lead demotes the previous lead to member.
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
    $name = request_string('member');
    if ($name !== '') {
        $st = $pdo->prepare("SELECT member_id FROM mcp_members WHERE status = 'active' AND display_name ILIKE :n ORDER BY member_kind = 'agent', display_name LIMIT 2");
        $st->execute(['n' => $name]);
        $hits = $st->fetchAll(PDO::FETCH_COLUMN);
        if (count($hits) === 1) { $memberId = (int) $hits[0]; }
        elseif (count($hits) > 1) { refuse(422, 'More than one member is named ' . $name . ' — give the id.'); }
    }
}
if ($memberId === null) {
    refuse(422, 'Which member?');
}
$member = find_member_by_id($pdo, $memberId);
if ($member === null || $member['status'] !== 'active') {
    refuse(422, 'The member must be active here.');
}
$role = request_string('project_role') ?: request_string('role') ?: 'member';
if (!in_array($role, PROJECT_ROLES, true)) {
    refuse(422, 'A project role is lead, product_owner, scrum_master, member or viewer.');
}
$st = $pdo->prepare('SELECT project_role FROM mcp_project_team WHERE project_id = :p AND member_id = :m AND removed_at IS NULL');
$st->execute(['p' => $id, 'm' => $memberId]);
$was = $st->fetchColumn();
if ($was !== false && $was === $role) {
    project_settings_done('/projects/' . $project['key'] . '/settings?tab=team', $member['display_name'] . ' is already ' . $role . ' on ' . $project['key'], ['record_id' => $memberId], 'teamChanged');
}
$pdo->beginTransaction();
try {
    $row = add_team_member($pdo, $id, $memberId, $role, (int) current_member_id());
    log_activity($pdo, 'team.add', 'project', $id, ['before' => $was === false ? null : ['member_id' => $memberId, 'name' => $member['display_name'], 'display_name' => $member['display_name'], 'project_role' => $was],
        'after' => ['member_id' => $memberId, 'name' => $member['display_name'], 'display_name' => $member['display_name'], 'project_role' => $role, 'project_key' => $project['key']],
        'department_id' => $project['department_id'] !== null ? (int) $project['department_id'] : null]);
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    error_log('team add failed: ' . $e->getMessage());
    refuse(500, 'The team could not be changed.');
}
$did = $was === false ? 'Added ' . $member['display_name'] . ' to ' . $project['key'] . ' as ' . str_replace('_', ' ', $role) : 'Made ' . $member['display_name'] . ' ' . str_replace('_', ' ', $role) . ' on ' . $project['key'];
project_settings_done('/projects/' . $project['key'] . '/settings?tab=team', $did, ['record_id' => $memberId], 'teamChanged');
