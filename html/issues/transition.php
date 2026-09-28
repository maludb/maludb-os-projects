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
/** Action `issue_transition` (log issue.transition). Gate: can_work; another's issue needs board.move too. A move the workflow does not allow → 422 with the trigger's sentence. */
if (request_string('issue') === '' && request_string('issue_id') === '' && request_string('key') === '' && request_string('id') !== '') { $_POST['issue'] = request_string('id'); }   // a board drop names the card by `id`
$issue = issue_from_request($pdo);
$projectId = (int) $issue['project_id'];
require_can_work($projectId);
if ((int) ($issue['assignee_member_id'] ?? 0) !== $me && (int) ($issue['reporter_member_id'] ?? 0) !== $me) {
    require_right_in('board.move', $projectId);
}
require_project_active($pdo, $projectId);
$toRaw = request_string('status') ?: request_string('status_id') ?: request_string('to') ?: request_string('value');   // `value`: a board drop (slice 3)
$fromColumn = request_string('from_column'); $toColumn = request_string('to_column');   // the board's columns, for the trail
$st = $pdo->prepare('SELECT status_id, name, category FROM mcp_workflow_statuses WHERE project_id = :p AND archived_at IS NULL');
$st->execute(['p' => $projectId]);
$to = null;
foreach ($st->fetchAll() as $s) {
    if ((string) $s['status_id'] === $toRaw || strcasecmp((string) $s['name'], $toRaw) === 0) { $to = $s; }
}
if ($to === null) { refuse(422, 'That status is not in this project\'s workflow.'); }
$stB = $pdo->prepare('SELECT status_id, name, category FROM mcp_workflow_statuses WHERE status_id = :s'); $stB->execute(['s' => (int) $issue['status_id']]); $from = $stB->fetch();
$pdo->beginTransaction();
try {
    [$b, $a] = transition_issue($pdo, (int) $issue['id'], (int) $to['status_id'], $me);
    log_activity($pdo, 'issue.transition', 'issue', (int) $issue['id'], [
        'before' => ['status_id' => (int) $b['status_id'], 'status' => $from['name'] ?? null],
        'after' => ['status_id' => (int) $a['status_id'], 'status' => $to['name'], 'category' => $to['category'], 'sprint_id' => $a['sprint_id'] !== null ? (int) $a['sprint_id'] : null]
                  + ($fromColumn !== '' || $toColumn !== '' ? ['from_column' => $fromColumn ?: null, 'to_column' => $toColumn ?: null] : [])]);
    $pdo->commit();
} catch (RuntimeException $e) { $pdo->rollBack(); refuse(422, $e->getMessage()); }
catch (Throwable $e) { $pdo->rollBack(); error_log('transition failed: ' . $e->getMessage()); refuse(500, 'The move could not be made.'); }
issue_action_done((string) $issue['key'], 'Moved ' . $issue['key'] . ' to ' . $to['name'], ['record_id' => (int) $issue['id'], 'new_status' => $to['name']]);
