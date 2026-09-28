<?php /** Cumulative flow: to do / in progress / done by day. Data: rd{days, rows}, flowDays, url */
$rows = $rd['rows']; $last = $rows === [] ? null : end($rows);
?>
<div id="report-flow">
    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
        <p class="fs-13 text-muted mb-0 flex-grow-1" id="report-flow-lead"><?php
            if ($last === null) { echo 'Nothing to chart yet.'; }
            else { echo 'Today: ' . (int) $last['todo'] . ' to do, ' . (int) $last['in_progress'] . ' in progress, ' . (int) $last['done'] . ' done (top-level issues, last ' . (int) $flowDays . ' days).'; }
        ?></p>
        <form class="d-flex align-items-center gap-2" id="report-flow-window-form" method="get" action="<?= e($url('flow')) ?>"
              hx-get="<?= e($url('flow')) ?>" hx-target="#report-body" hx-swap="outerHTML" hx-trigger="change" hx-push-url="true">
            <input type="hidden" name="report" value="flow">
            <select name="since" id="report-flow-window" class="form-select form-select-sm" aria-label="Window">
                <?php foreach (REPORT_FLOW_WINDOWS as $w): ?><option value="<?= $w ?>" <?= $w === $flowDays ? 'selected' : '' ?>>Last <?= $w ?> days</option><?php endforeach; ?>
            </select>
        </form>
        <a href="<?= e($url('flow', ['since' => $flowDays, 'format' => 'csv'])) ?>" class="btn btn-sm btn-light-brand" id="report-flow-csv"><i class="feather-download me-1"></i>Download CSV</a>
    </div>
    <?php if ($rows !== []): ?>
    <div class="mb-2">
        <?= chart_stacked(array_map(static fn (array $r): array => ['label' => date('j M', strtotime((string) $r['day'])), 'values' => [(int) $r['todo'], (int) $r['in_progress'], (int) $r['done']]], $rows),
            ['width' => 640, 'height' => 240, 'axes' => true, 'keys' => ['To do', 'In progress', 'Done'], 'title' => 'Cumulative flow: to do, in progress and done issues by day', 'id' => 'report-flow-chart']) ?>
        <div class="fs-11 text-muted">Issues by day · blue = to do, green = in progress, grey = done (stacked). The same numbers are in the CSV.</div>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0" id="report-flow-table">
            <thead class="thead-light"><tr><th>Day</th><th class="text-end">To do</th><th class="text-end">In progress</th><th class="text-end">Done</th></tr></thead>
            <tbody>
            <?php foreach (array_reverse($rows) as $r): ?>
                <tr><td class="text-nowrap"><?= e(format_date($r['day'])) ?></td><td class="text-end"><?= (int) $r['todo'] ?></td><td class="text-end"><?= (int) $r['in_progress'] ?></td><td class="text-end fw-semibold"><?= (int) $r['done'] ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
