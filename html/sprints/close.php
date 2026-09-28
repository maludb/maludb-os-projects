<?php
declare(strict_types=1);
/** Action `sprint_close` (log sprint.close). Gate: sprint.run in the project. The unfinished carry to `carry_to` (a future sprint) or the backlog. */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/projects/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/sprints/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/sprints/present.php';
require_post();
verify_csrf();
require_login();
$pdo = db();
$me = (int) current_member_id();
$sprint = sprint_from_request($pdo);
$sid = (int) $sprint['sprint_id'];
$projectId = (int) $sprint['project_id'];
require_can_see_project($projectId);
require_right_in('sprint.run', $projectId);
if ($sprint['state'] !== 'active') { refuse(409, 'Only the active sprint closes; ' . $sprint['name'] . ' is ' . $sprint['state'] . '.'); }
$carryRaw = request_string('carry_to') ?: request_string('carry_to_sprint_id');
$carryTo = null;
if ($carryRaw !== '' && strtolower($carryRaw) !== 'backlog') {
    if (!ctype_digit($carryRaw)) { refuse(422, 'Carry over to a future sprint of this project, or to the backlog.'); }
    $target = find_sprint($pdo, (int) $carryRaw);
    if ($target === null || (int) $target['project_id'] !== $projectId || $target['state'] !== 'future') { refuse(422, 'Carry over to a future sprint of this project, or to the backlog.'); }
    $carryTo = (int) $target['sprint_id'];
}
$carried = $pdo->prepare("SELECT key FROM mcp_issues WHERE sprint_id = :s AND parent_issue_id IS NULL AND status_category <> 'done' ORDER BY rank");
$carried->execute(['s' => $sid]);
$carriedKeys = $carried->fetchAll(PDO::FETCH_COLUMN);
$pdo->beginTransaction();
try {
    $r = close_sprint($pdo, $sid, $me, $carryTo);
    log_activity($pdo, 'sprint.close', 'sprint', $sid, ['before' => ['state' => 'active'],
        'after' => ['state' => 'closed', 'completed_points' => $r['completed'], 'carried_points' => $r['carried'], 'carried_issues' => $r['carried_issues'], 'carried_keys' => $carriedKeys, 'carry_to_sprint_id' => $carryTo, 'carry_to_sprint_name' => $target['name'] ?? null]]);
    $pdo->commit();
} catch (RuntimeException $e) { $pdo->rollBack(); refuse(409, $e->getMessage() . '.'); }
catch (Throwable $e) { $pdo->rollBack(); error_log('sprint close failed: ' . $e->getMessage()); refuse(500, 'The sprint could not close.'); }
$did = 'Closed ' . $sprint['name'] . ': ' . days_label($r['completed']) . ' points done, ' . $r['carried_issues'] . ($r['carried_issues'] === 1 ? ' issue' : ' issues') . ' (' . days_label($r['carried']) . ' points) carried to ' . ($carryTo === null ? 'the backlog' : $target['name']);
emit_action_status(true, ['did' => $did, 'record_id' => $sid, 'completed_points' => $r['completed'], 'carried_points' => $r['carried'], 'carried_issues' => $r['carried_issues'], 'carry_to_sprint_id' => $carryTo, 'refresh' => 'sprintChanged']);
saved_go('/sprints/' . $sid, 'sprintChanged, backlogChanged, issueChanged');
