<?php /** Scope changes of a sprint. Data: rd{sprint_id, rows, totals}, sprints, sprintId, tz, url */
$rows = $rd['rows']; $t = $rd['totals'];
$fmt = static fn ($v): string => $v === null ? '—' : rtrim(rtrim(number_format((float) $v, 1, '.', ''), '0'), '.');
$words = ['added' => 'added', 'removed' => 'removed', 'done' => 'done', 'reopened' => 'reopened', 'estimated' => 're-estimated'];
?>
<div id="report-scope">
    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
        <p class="fs-13 text-muted mb-0 flex-grow-1" id="report-scope-lead"><?php
            if ($sprintId === null) { echo 'No sprint has run yet — scope changes are what entered and left a sprint after it started.'; }
            else { echo e($fmt($t['added'])) . ' points were added and ' . e($fmt($t['removed'])) . ' removed after the sprint started (net ' . e($fmt($t['net'])) . ').'; }
        ?></p>
        <?php if (count($sprints) > 1): ?>
        <form class="d-flex align-items-center gap-2" id="report-scope-sprint-form" method="get" action="<?= e($url('scope')) ?>"
              hx-get="<?= e($url('scope')) ?>" hx-target="#report-body" hx-swap="outerHTML" hx-trigger="change" hx-push-url="true">
            <input type="hidden" name="report" value="scope">
            <select name="sprint" id="report-scope-sprint" class="form-select form-select-sm" aria-label="Sprint">
                <?php foreach ($sprints as $o): ?><option value="<?= (int) $o['sprint_id'] ?>" <?= (int) $o['sprint_id'] === $sprintId ? 'selected' : '' ?>><?= e($o['name']) ?> (<?= e($o['state']) ?>)</option><?php endforeach; ?>
            </select>
        </form>
        <?php endif; ?>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0" id="report-scope-table">
            <thead class="thead-light"><tr><th>When</th><th>Who</th><th>Event</th><th>Issue</th><th class="text-end">Points</th></tr></thead>
            <tbody>
            <?php if ($rows === []): ?><tr><td colspan="5" class="text-center text-muted py-4">Nothing entered or left after the start.</td></tr><?php endif; ?>
            <?php foreach ($rows as $r): ?>
                <tr id="report-scope-row-<?= (int) $r['scope_event_id'] ?>">
                    <td class="text-nowrap fs-12"><?= e(format_ts($r['occurred_at'], $tz, 'M j, g:i A')) ?></td>
                    <td><?= e($r['by_name'] ?? '—') ?></td>
                    <td><span class="badge bg-soft-<?= $r['event'] === 'added' ? 'warning' : ($r['event'] === 'removed' ? 'danger' : ($r['event'] === 'done' ? 'success' : 'secondary')) ?> text-dark"><?= e($words[$r['event']] ?? $r['event']) ?></span></td>
                    <td><?php if ($r['issue_key'] !== null): ?><a href="/issues/<?= e($r['issue_key']) ?>" hx-get="/issues/<?= e($r['issue_key']) ?>" hx-target="#page-content" hx-push-url="/issues/<?= e($r['issue_key']) ?>"><?= e($r['issue_key']) ?></a> <span class="text-muted fs-12"><?= e($r['issue_summary'] ?? '') ?></span><?php else: ?><span class="text-muted">(deleted)</span><?php endif; ?></td>
                    <td class="text-end"><?= e($fmt($r['points'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <?php if ($rows !== []): ?><tfoot><tr class="fw-semibold"><td colspan="4">Added <?= e($fmt($t['added'])) ?> · removed <?= e($fmt($t['removed'])) ?></td><td class="text-end">net <?= e($fmt($t['net'])) ?></td></tr></tfoot><?php endif; ?>
        </table>
    </div>
</div>
