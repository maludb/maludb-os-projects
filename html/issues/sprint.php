<?php
declare(strict_types=1);
/**
 * Action `issue_move_to_sprint` (log issue.move_to_sprint) — an issue into a future or active sprint (`sprint`),
 * or back to the backlog ('' / 'backlog'). A drop arrives as value=<sprint id> with neighbours, which also rank
 * it. A closed sprint takes nothing. Gate: sprint.plan in the project. Subtasks follow their parent by trigger.
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
require_right_in('sprint.plan', $projectId);
if ($issue['parent_issue_id'] !== null) { refuse(422, 'A subtask follows its parent into a sprint.'); }
$raw = array_key_exists('sprint', $_POST) ? trim((string) $_POST['sprint']) : trim((string) ($_POST['value'] ?? ''));
$target = null;
if ($raw !== '' && strtolower($raw) !== 'backlog') {
    if (!ctype_digit($raw)) { refuse(422, 'Which sprint?'); }
    $target = find_sprint($pdo, (int) $raw);
    if ($target === null || (int) $target['project_id'] !== $projectId) { refuse(404, 'Sprint not found.'); }
    if ($target['state'] === 'closed') { refuse(422, 'A closed sprint takes no more issues.'); }
}
$targetId = $target === null ? null : (int) $target['sprint_id'];
$neighbour = static function (string $name) use ($pdo, $projectId, $issue): ?int {
    $raw = request_string($name);
    if ($raw === '' || !ctype_digit($raw)) { return null; }
    $n = find_issue($pdo, (int) $raw);
    return $n === null || (int) $n['project_id'] !== $projectId || (int) $n['issue_id'] === (int) $issue['id'] ? null : (int) $n['issue_id'];
};
$before = $neighbour('before'); $after = $neighbour('after');
$fromId = $issue['sprint_id'] !== null ? (int) $issue['sprint_id'] : null;
$moved = $fromId !== $targetId;
$pdo->beginTransaction();
try {
    if ($moved) {
        [$b, $a] = move_issue_to_sprint($pdo, (int) $issue['id'], $targetId, $me);
        log_activity($pdo, 'issue.move_to_sprint', 'issue', (int) $issue['id'], [
            'before' => ['sprint_id' => $fromId, 'sprint_name' => $fromId === null ? null : (find_sprint($pdo, $fromId)['name'] ?? null)],
            'after' => ['sprint_id' => $targetId, 'sprint_name' => $target['name'] ?? null]]);
    }
    if ($before !== null || $after !== null) {
        require_right_in('backlog.rank', $projectId);
        [$rb, $ra] = rank_issue($pdo, (int) $issue['id'], $before, $after, $me);
        $keyOf = static fn (?int $id) => $id === null ? null : (find_issue($pdo, $id)['key'] ?? null);
        log_activity($pdo, 'issue.rank', 'issue', (int) $issue['id'], ['before' => ['rank' => (string) $rb['rank']], 'after' => ['rank' => (string) $ra['rank'], 'before_key' => $keyOf($before), 'after_key' => $keyOf($after)]]);
    }
    $pdo->commit();
} catch (RuntimeException $e) { $pdo->rollBack(); refuse(422, $e->getMessage()); }
catch (Throwable $e) { $pdo->rollBack(); error_log('move to sprint failed: ' . $e->getMessage()); refuse(500, 'The issue could not be moved.'); }
issue_action_done((string) $issue['key'], ($moved ? 'Moved ' . $issue['key'] . ' to ' . ($target === null ? 'the backlog' : $target['name']) : 'Ranked ' . $issue['key']),
    ['record_id' => (int) $issue['id'], 'sprint_id' => $targetId], $moved ? 'issueChanged, backlogChanged' : 'backlogChanged');
