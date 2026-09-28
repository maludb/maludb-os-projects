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
/** Action `issue_link` (log issue.link). Gate: can_work on both. kind: blocks | is_blocked_by | relates | duplicates; a blocks-cycle → 422 with the sentence. */
$issue = issue_from_request($pdo);
$otherRaw = request_string('other') ?: request_string('to') ?: request_string('other_issue');
if ($otherRaw === '') { refuse(422, 'Which issue does it link to?'); }
$otherView = ctype_digit($otherRaw) ? find_issue($pdo, (int) $otherRaw) : find_issue_by_key($pdo, $otherRaw);
if ($otherView === null) { refuse(404, 'Issue not found: ' . $otherRaw); }
$other = find_issue_for_edit($pdo, (int) $otherView['issue_id']);
require_can_work((int) $issue['project_id']);
require_can_work((int) $other['project_id']);
require_project_active($pdo, (int) $issue['project_id']);
if ((int) $other['id'] === (int) $issue['id']) { refuse(422, 'An issue cannot link to itself.'); }
$kind = request_string('kind', 'relates');
[$from, $to] = [$issue, $other];
if ($kind === 'is_blocked_by') { $kind = 'blocks'; [$from, $to] = [$other, $issue]; }
if (!in_array($kind, ISSUE_LINK_KINDS, true)) { refuse(422, 'A link blocks, relates to or duplicates.'); }
$pdo->beginTransaction();
try {
    $link = link_issues($pdo, (int) $from['id'], (int) $to['id'], $kind, $me);
    log_activity($pdo, 'issue.link', 'issue', (int) $issue['id'], ['after' => ['kind' => $kind, 'from_key' => $from['key'], 'to_key' => $to['key'], 'link_id' => (int) $link['id']]]);
    $pdo->commit();
} catch (RuntimeException $e) { $pdo->rollBack(); refuse(422, $e->getMessage()); }
catch (Throwable $e) { $pdo->rollBack(); error_log('link failed: ' . $e->getMessage()); refuse(500, 'The link could not be made.'); }
issue_action_done((string) $issue['key'], $from['key'] . ' ' . str_replace('_', ' ', $kind) . ' ' . $to['key'], ['record_id' => (int) $link['id']]);
