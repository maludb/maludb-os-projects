<?php /** Cycle time by type over a window. Data: rd{days, rows}, cycleDays, url */
$rows = $rd['rows'];
$fmt = static fn ($v): string => $v === null ? '—' : rtrim(rtrim(number_format((float) $v, 1, '.', ''), '0'), '.');
$story = null; foreach ($rows as $r) { if ($r['type'] === 'story') { $story = $r; } }
$first = $rows[0] ?? null;
?>
<div id="report-cycle">
    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
        <p class="fs-13 text-muted mb-0 flex-grow-1" id="report-cycle-lead"><?php
            if ($rows === []) { echo 'Nothing was started and finished in the last ' . (int) $cycleDays . ' days — cycle time is the days from the first move into progress to done.'; }
            else { $r = $story ?? $first; echo 'A ' . e($r['type']) . ' takes ' . e($fmt($r['median_days'])) . ' days from start to done (median, last ' . (int) $cycleDays . ' days).'; }
        ?></p>
        <form class="d-flex align-items-center gap-2" id="report-cycle-window-form" method="get" action="<?= e($url('cycle')) ?>"
              hx-get="<?= e($url('cycle')) ?>" hx-target="#report-body" hx-swap="outerHTML" hx-trigger="change" hx-push-url="true">
            <input type="hidden" name="report" value="cycle">
            <select name="since" id="report-cycle-window" class="form-select form-select-sm" aria-label="Window">
                <?php foreach (REPORT_CYCLE_WINDOWS as $w): ?><option value="<?= $w ?>" <?= $w === $cycleDays ? 'selected' : '' ?>>Last <?= $w ?> days</option><?php endforeach; ?>
            </select>
        </form>
    </div>
    <?php if ($rows !== []): ?>
    <div class="mb-3">
        <?= chart_bars(array_map(static fn (array $r): array => ['label' => ucfirst((string) $r['type']), 'value' => (float) ($r['median_days'] ?? 0), 'value2' => (float) ($r['p85_days'] ?? 0), 'label2' => ucfirst((string) $r['type']) . ' p85'], $rows),
            ['width' => 640, 'height' => 200, 'axes' => true, 'title' => 'Cycle time by type: median (dark) and 85th percentile (light), in days', 'id' => 'report-cycle-chart']) ?>
        <div class="fs-11 text-muted">Days · dark = median, light = 85th percentile (the whisker the chart cannot draw).</div>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0" id="report-cycle-table">
            <thead class="thead-light"><tr><th>Type</th><th class="text-end">Resolved</th><th class="text-end">Median (days)</th><th class="text-end">p85 (days)</th><th class="text-end">Mean (days)</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr id="report-cycle-row-<?= e($r['type']) ?>"><td><?= e(ucfirst((string) $r['type'])) ?></td><td class="text-end"><?= (int) $r['resolved'] ?></td><td class="text-end fw-semibold"><?= e($fmt($r['median_days'])) ?></td><td class="text-end"><?= e($fmt($r['p85_days'])) ?></td><td class="text-end text-muted"><?= e($fmt($r['mean_days'])) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
