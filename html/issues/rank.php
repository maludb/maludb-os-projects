<?php
declare(strict_types=1);
/**
 * Action `issue_rank` (log issue.rank) — place an issue in the backlog order: between before (the issue it goes
 * above) and after (the one it goes below), or top / bottom. A drop from a sprint box into the backlog list
 * arrives with value='' and also leaves the sprint. Gate: backlog.rank in the project.
 */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/projects/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/issues/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/issues/present.php';
require_once dirname(__DIR__, 2) . '/app/features/issues/respond.php';
require_once dirname(__DIR__, 2) . '/app/features/sprints/queries.php';
require_post();
verify_csrf();
require_login();
$pdo = db();
$me = (int) current_member_id();
$issue = issue_from_request($pdo, isset($_POST['issue']) ? 'issue' : 'id');
$projectId = (int) $issue['project_id'];
require_can_see_project($projectId);
require_right_in('backlog.rank', $projectId);
if ($issue['parent_issue_id'] !== null) { refuse(422, 'A subtask ranks with its parent.'); }
$neighbour = static function (string $name) use ($pdo, $projectId, $issue): ?int {
    $raw = request_string($name);
    if ($raw === '') { return null; }
    $n = ctype_digit($raw) ? find_issue($pdo, (int) $raw) : find_issue_by_key($pdo, $raw);
    if ($n === null || (int) $n['project_id'] !== $projectId) { refuse(422, 'The neighbour ' . $raw . ' is not an issue of this project.'); }
    if ((int) $n['issue_id'] === (int) $issue['id']) { return null; }
    return (int) $n['issue_id'];
};
$before = $neighbour('before'); $after = $neighbour('after');
$top = request_bool('top'); $bottom = request_bool('bottom');
$leaveSprint = array_key_exists('value', $_POST) && trim((string) $_POST['value']) === '' && $issue['sprint_id'] !== null;
if ($before === null && $after === null && !$top && !$bottom && !$leaveSprint) { refuse(422, 'Say where the issue goes: before or after another issue, or top or bottom.'); }
$pdo->beginTransaction();
try {
    if ($leaveSprint) {
        require_right_in('sprint.plan', $projectId);
        [$b0, $a0] = move_issue_to_sprint($pdo, (int) $issue['id'], null, $me);
        log_activity($pdo, 'issue.move_to_sprint', 'issue', (int) $issue['id'], ['before' => ['sprint_id' => (int) $b0['sprint_id'], 'sprint_name' => find_sprint($pdo, (int) $b0['sprint_id'])['name'] ?? null], 'after' => ['sprint_id' => null, 'sprint_name' => null]]);
    }
    if ($top) { [$b, $a] = rank_issue_top($pdo, (int) $issue['id'], $me); }
    elseif ($before !== null || $after !== null || $bottom) { [$b, $a] = rank_issue($pdo, (int) $issue['id'], $before, $after, $me); }
    if (isset($b, $a)) {
        $keyOf = static fn (?int $id) => $id === null ? null : (find_issue($pdo, $id)['key'] ?? null);
        log_activity($pdo, 'issue.rank', 'issue', (int) $issue['id'], ['before' => ['rank' => (string) $b['rank']],
            'after' => ['rank' => (string) $a['rank'], 'before_key' => $keyOf($before), 'after_key' => $keyOf($after), 'top' => $top ?: null, 'bottom' => $bottom ?: null]]);
    }
    $pdo->commit();
} catch (RuntimeException $e) { $pdo->rollBack(); refuse(422, $e->getMessage()); }
catch (Throwable $e) { $pdo->rollBack(); error_log('rank failed: ' . $e->getMessage()); refuse(500, 'The issue could not be ranked.'); }
issue_action_done((string) $issue['key'], 'Ranked ' . $issue['key'], ['record_id' => (int) $issue['id']], $leaveSprint ? 'issueChanged, backlogChanged' : 'backlogChanged');
