<?php /** Region #issue-history. Data: issue, history, tz */
$key = (string) $issue['key']; ?>
<div class="card" id="issue-history" hx-get="/issues/view.php?key=<?= e($key) ?>&region=history" hx-trigger="issueChanged from:body" hx-swap="outerHTML">
    <div class="card-header"><h5 class="card-title mb-0">History</h5></div>
    <div class="card-body">
        <ul class="list-unstyled mb-0 fs-12" id="issue-history-list">
            <?php foreach ($history as $a): ?>
            <li class="py-1 border-bottom d-flex gap-2"><span class="text-muted text-nowrap"><?= e(format_ts($a['occurred_at'], $tz, 'M j, g:i A')) ?></span><span><?= e(issue_history_sentence($a)) ?></span><?= ($a['source'] ?? 'web') === 'agent' ? '<span class="badge bg-soft-info text-info">agent</span>' : '' ?></li>
            <?php endforeach; ?>
            <?php if ($history === []): ?><li class="text-muted">Nothing yet.</li><?php endif; ?>
        </ul>
    </div>
</div>
