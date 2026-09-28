<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';
require_once dirname(__DIR__, 3) . '/app/features/projects/queries.php';
require_once dirname(__DIR__, 3) . '/app/features/issues/queries.php';
require_once dirname(__DIR__, 3) . '/app/features/issues/present.php';
require_once dirname(__DIR__, 3) . '/app/features/issues/respond.php';
require_post();
verify_csrf();
require_login();
$pdo = db();
$me = (int) current_member_id();
/** Action `worklog_add` (log worklog.add). Gate: worklog.write in the project. Hours 0 < h ≤ 24; the day a date (today by default). */
$issue = issue_from_request($pdo);
require_right_in('worklog.write', (int) $issue['project_id']);
$h = request_string('hours');
if (!is_numeric($h) || (float) $h <= 0 || (float) $h > 24) { refuse(422, 'Hours is a number above 0 and up to 24.'); }
$day = request_date('worked_on') ?? request_date('day');
if ($day === false) { refuse(422, 'The day is a date (YYYY-MM-DD).'); }
$day = $day ?? date('Y-m-d');
$note = request_string('note') ?: null;
if ($note !== null && mb_strlen($note) > 300) { refuse(422, 'A note is up to 300 characters.'); }
$pdo->beginTransaction();
try {
    $row = add_worklog($pdo, (int) $issue['id'], (float) $h, $day, $note, $me);
    log_activity($pdo, 'worklog.add', 'issue', (int) $issue['id'], ['after' => ['worklog_id' => (int) $row['id'], 'hours' => (float) $h, 'worked_on' => $day]]);
    $pdo->commit();
} catch (Throwable $e) { $pdo->rollBack(); error_log('worklog add failed: ' . $e->getMessage()); refuse(500, 'The hours could not be logged.'); }
issue_action_done((string) $issue['key'], 'Logged ' . (float) $h . ' h on ' . $issue['key'], ['record_id' => (int) $row['id']]);
