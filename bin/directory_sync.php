<?php
declare(strict_types=1);

/**
 * The mirror's timer (sign-on-and-directory.md §4): every minute, GET the change feed since the
 * stored cursor and apply it; --full refreshes the whole directory. Advisory-locked so two runs
 * never overlap. Logs directory.sync with the counts (source cron).
 *   php bin/directory_sync.php [--full]
 */
if (PHP_SAPI !== 'cli') { fwrite(STDERR, "CLI only.\n"); exit(1); }
require_once dirname(__DIR__) . '/app/bootstrap.php';

$full = in_array('--full', $argv, true);
$pdo = db();
if (!(bool) $pdo->query("SELECT pg_try_advisory_lock(hashtext('directory_sync'))")->fetchColumn()) {
    echo "another sync holds the lock — skipping.\n";
    exit(0);
}
$state = $pdo->query('SELECT next_cursor FROM directory_sync_state WHERE id = 1')->fetch();
$cursor = $full ? null : ($state['next_cursor'] ?? null);
$path = 'changes.php' . ($cursor !== null ? '?since=' . rawurlencode((string) $cursor) : '');
$doc = directory_read($path);
if ($doc === null || ($doc['schema'] ?? '') !== 'os.directory-changes/1') {
    $pdo->prepare('UPDATE directory_sync_state SET last_run_at = now(), last_error = :e, updated_at = now() WHERE id = 1')
        ->execute(['e' => 'the kernel did not answer the change feed']);
    log_activity($pdo, 'directory.sync.failed', null, null, ['actor_member_id' => null, 'after' => ['cursor' => $cursor]]);
    fwrite(STDERR, "the kernel did not answer the change feed\n");
    exit(1);
}
$pdo->beginTransaction();
try {
    $counts = mirror_apply_feed($pdo, $doc);
    $pdo->prepare('UPDATE directory_sync_state SET next_cursor = :n, full_at = CASE WHEN :full THEN now() ELSE full_at END, last_run_at = now(), last_error = NULL, updated_at = now() WHERE id = 1')
        ->execute(['n' => (string) $doc['next'], 'full' => !empty($doc['full']) ? 't' : 'f']);
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    error_log('directory_sync: ' . $e->getMessage());
    $pdo->prepare('UPDATE directory_sync_state SET last_run_at = now(), last_error = :e, updated_at = now() WHERE id = 1')->execute(['e' => 'apply failed']);
    exit(1);
}
$changed = array_sum($counts);
if ($changed > 0 || !empty($doc['full'])) {
    log_activity($pdo, 'directory.sync', null, null, ['actor_member_id' => null, 'after' => $counts + ['full' => !empty($doc['full'])]]);
}
printf("applied %d members, %d departments, %d memberships, %d holdings%s; cursor %s\n", $counts['members'], $counts['departments'], $counts['memberships'], $counts['access'] ?? 0, !empty($doc['full']) ? ' (full)' : '', $doc['next']);
