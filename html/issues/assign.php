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
/** Action `issue_assign` (log issue.assign, before/after the assignee). Gate: can_work. An empty assignee unassigns. */
$issue = issue_from_request($pdo);
require_can_work((int) $issue['project_id']);
require_project_active($pdo, (int) $issue['project_id']);
$raw = request_string('assignee') ?: request_string('assignee_member_id') ?: request_string('member');
$to = null;
if ($raw !== '' && strtolower($raw) !== 'none' && strtolower($raw) !== 'unassigned') {
    if (ctype_digit($raw)) { $to = (int) $raw; }
    else {
        $st = $pdo->prepare("SELECT member_id FROM mcp_members WHERE status = 'active' AND display_name ILIKE :n ORDER BY member_kind = 'agent', display_name LIMIT 2"); $st->execute(['n' => $raw]);
        $rows = $st->fetchAll(PDO::FETCH_COLUMN);
        if (count($rows) !== 1) { refuse(422, count($rows) === 0 ? 'No member named ' . $raw . '.' : 'More than one member matches ' . $raw . '; use the id.'); }
        $to = (int) $rows[0];
    }
    $m = find_member_by_id($pdo, $to);
    if ($m === null || $m['status'] !== 'active') { refuse(422, 'The assignee must be an active member here.'); }
}
$name = static function (?int $id) use ($pdo): ?string { if ($id === null) { return null; } $m = find_member_by_id($pdo, $id); return $m['display_name'] ?? null; };
$pdo->beginTransaction();
try {
    [$b, $a] = assign_issue($pdo, (int) $issue['id'], $to, $me);
    if ((int) ($b['assignee_member_id'] ?? 0) !== (int) ($a['assignee_member_id'] ?? 0)) {
        log_activity($pdo, 'issue.assign', 'issue', (int) $issue['id'], [
            'before' => ['assignee_member_id' => $b['assignee_member_id'] !== null ? (int) $b['assignee_member_id'] : null, 'assignee_name' => $name($b['assignee_member_id'] !== null ? (int) $b['assignee_member_id'] : null)],
            'after' => ['assignee_member_id' => $to, 'assignee_name' => $name($to)]]);
    }
    $pdo->commit();
} catch (Throwable $e) { $pdo->rollBack(); error_log('assign failed: ' . $e->getMessage()); refuse(500, 'The assignment could not be saved.'); }
issue_action_done((string) $issue['key'], $to === null ? 'Unassigned ' . $issue['key'] : 'Assigned ' . $issue['key'] . ' to ' . $name($to), ['record_id' => (int) $issue['id']]);
