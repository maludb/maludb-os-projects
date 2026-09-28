<?php
declare(strict_types=1);
/** Action `sprint_delete` (log sprint.delete). Gate: sprint.plan in the project. Only a future sprint with no issue goes; the rest is history. */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/projects/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/sprints/queries.php';
require_post();
verify_csrf();
require_login();
$pdo = db();
$me = (int) current_member_id();
$sprint = sprint_from_request($pdo);
$sid = (int) $sprint['sprint_id'];
$projectId = (int) $sprint['project_id'];
require_can_see_project($projectId);
require_right_in('sprint.plan', $projectId);
if ($sprint['state'] !== 'future') { refuse(409, 'Only a future sprint is deleted; ' . $sprint['name'] . ' is ' . $sprint['state'] . ' and stays as history.'); }
if ((int) $sprint['issues'] > 0) { refuse(409, $sprint['name'] . ' still holds ' . (int) $sprint['issues'] . ($sprint['issues'] === 1 ? ' issue' : ' issues') . '; move them out first.'); }
$project = find_project($pdo, $projectId);
$pdo->beginTransaction();
try {
    if (!delete_sprint($pdo, $sid)) { throw new RuntimeException('The sprint was not deleted; it may have changed.'); }
    log_activity($pdo, 'sprint.delete', 'sprint', $sid, ['before' => ['name' => $sprint['name'], 'goal' => $sprint['goal'], 'start_date' => $sprint['start_date'], 'end_date' => $sprint['end_date'], 'project_key' => $sprint['project_key']]]);
    $pdo->commit();
} catch (RuntimeException $e) { $pdo->rollBack(); refuse(409, $e->getMessage()); }
catch (Throwable $e) { $pdo->rollBack(); error_log('sprint delete failed: ' . $e->getMessage()); refuse(500, 'The sprint could not be deleted.'); }
emit_action_status(true, ['did' => 'Deleted sprint ' . $sprint['name'], 'record_id' => $sid, 'refresh' => 'sprintChanged']);
saved_go('/projects/' . $sprint['project_key'] . '/sprints', 'sprintChanged, backlogChanged');
