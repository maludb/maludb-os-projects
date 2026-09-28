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
/** Action `issue_estimate` (log issue.estimate). Gate: backlog.rank in the project, or the assignee on their own issue. */
$issue = issue_from_request($pdo);
$projectId = (int) $issue['project_id'];
require_can_see_project($projectId);
if ((int) ($issue['assignee_member_id'] ?? 0) !== $me) { require_right_in('backlog.rank', $projectId); }
$pRaw = array_key_exists('story_points', $_POST) ? trim((string) $_POST['story_points']) : (array_key_exists('points', $_POST) ? trim((string) $_POST['points']) : null);
$hRaw = array_key_exists('estimate_hours', $_POST) ? trim((string) $_POST['estimate_hours']) : (array_key_exists('hours', $_POST) ? trim((string) $_POST['hours']) : null);
$points = $pRaw === null ? ($issue['story_points'] !== null ? (float) $issue['story_points'] : null) : ($pRaw === '' || strtolower($pRaw) === 'none' ? null : (is_numeric($pRaw) && (float) $pRaw >= 0 ? (float) $pRaw : false));
$hours = $hRaw === null ? ($issue['estimate_hours'] !== null ? (float) $issue['estimate_hours'] : null) : ($hRaw === '' ? null : (is_numeric($hRaw) && (float) $hRaw >= 0 ? (float) $hRaw : false));
if ($points === false) { refuse(422, 'Story points is a number of 0 or more.'); }
if ($hours === false) { refuse(422, 'Estimate hours is a number of 0 or more.'); }
if ($issue['parent_issue_id'] !== null && $points !== null) { refuse(422, 'A subtask carries no points; estimate its parent.'); }
$pdo->beginTransaction();
try {
    [$b, $a] = estimate_issue($pdo, (int) $issue['id'], $points, $hours, $me);
    log_activity($pdo, 'issue.estimate', 'issue', (int) $issue['id'], [
        'before' => ['story_points' => $b['story_points'] !== null ? (float) $b['story_points'] : null, 'estimate_hours' => $b['estimate_hours'] !== null ? (float) $b['estimate_hours'] : null],
        'after' => ['story_points' => $points, 'estimate_hours' => $hours]]);
    $pdo->commit();
} catch (Throwable $e) { $pdo->rollBack(); error_log('estimate failed: ' . $e->getMessage()); refuse(500, 'The estimate could not be saved.'); }
issue_action_done((string) $issue['key'], 'Estimated ' . $issue['key'] . ' at ' . ($points === null ? 'no points' : $points . ' points'), ['record_id' => (int) $issue['id']]);
