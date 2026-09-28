<?php
declare(strict_types=1);
/** Action `sprint_start` (log sprint.start). Gate: sprint.run in the project. A second active sprint is refused (409). */
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
if ($sprint['state'] !== 'future') { refuse(409, 'Only a future sprint can start; ' . $sprint['name'] . ' is ' . $sprint['state'] . '.'); }
$st = $pdo->prepare("SELECT name FROM mcp_sprints WHERE project_id = :p AND state = 'active'");
$st->execute(['p' => $projectId]);
if (($activeName = $st->fetchColumn()) !== false) { refuse(409, $activeName . ' is already active in this project; close it first.'); }
$start = request_date('start_date'); $end = request_date('end_date');
if ($start === false || $end === false) { refuse(422, 'Start and end are dates (YYYY-MM-DD).'); }
$pdo->beginTransaction();
try {
    $row = start_sprint($pdo, $sid, $me, $start, $end);
    $keys = $pdo->prepare('SELECT key FROM mcp_issues WHERE sprint_id = :s AND parent_issue_id IS NULL ORDER BY rank');
    $keys->execute(['s' => $sid]);
    log_activity($pdo, 'sprint.start', 'sprint', $sid, ['before' => ['state' => 'future'],
        'after' => ['state' => 'active', 'committed_points' => (float) $row['committed_points'], 'issue_keys' => $keys->fetchAll(PDO::FETCH_COLUMN), 'start_date' => $row['start_date'], 'end_date' => $row['end_date']]]);
    $pdo->commit();
} catch (RuntimeException $e) { $pdo->rollBack(); refuse(409, $e->getMessage() . '.'); }
catch (Throwable $e) { $pdo->rollBack(); error_log('sprint start failed: ' . $e->getMessage()); refuse(500, 'The sprint could not start.'); }
emit_action_status(true, ['did' => 'Started ' . $sprint['name'] . ' with ' . days_label($row['committed_points']) . ' points committed', 'record_id' => $sid, 'committed_points' => (float) $row['committed_points'], 'refresh' => 'sprintChanged']);
saved_go('/sprints/' . $sid, 'sprintChanged, backlogChanged');
