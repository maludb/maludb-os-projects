<?php /** Velocity: committed vs completed per closed sprint. Data: rd{rows, average}, project */
$rows = $rd['rows']; $avg = $rd['average'];
$fmt = static fn ($v): string => $v === null ? '—' : rtrim(rtrim(number_format((float) $v, 1, '.', ''), '0'), '.');
?>
<div id="report-velocity">
    <p class="fs-13 text-muted" id="report-velocity-lead"><?= $rows === [] ? 'No sprint has closed yet — velocity is what the team completes a sprint, read from closed sprints.'
        : 'The team completes ' . e($fmt($avg)) . ' points a sprint (average of the last ' . min(3, count($rows)) . ').' ?></p>
    <?php if ($rows !== []): ?>
    <div class="mb-3">
        <?= chart_bars(array_map(static fn (array $r): array => ['label' => (string) $r['name'], 'value' => (float) ($r['committed'] ?? 0), 'value2' => (float) ($r['completed'] ?? 0), 'label2' => $r['name'] . ' completed'], $rows),
            ['width' => 640, 'height' => 240, 'axes' => true, 'title' => 'Velocity: committed (dark) and completed (light) points per closed sprint', 'id' => 'report-velocity-chart']) ?>
        <div class="fs-11 text-muted">Points per sprint · dark = committed, light = completed. The 3-sprint average is the lead above (the chart draws no line).</div>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0" id="report-velocity-table">
            <thead class="thead-light"><tr><th>Sprint</th><th>Dates</th><th class="text-end">Committed</th><th class="text-end">Completed</th><th class="text-end">Carried</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr id="report-velocity-row-<?= (int) $r['sprint_id'] ?>">
                    <td><a href="/sprints/<?= (int) $r['sprint_id'] ?>" hx-get="/sprints/<?= (int) $r['sprint_id'] ?>" hx-target="#page-content" hx-push-url="/sprints/<?= (int) $r['sprint_id'] ?>"><?= e($r['name']) ?></a></td>
                    <td class="text-muted fs-12 text-nowrap"><?= e(format_date($r['start_date'])) ?> – <?= e(format_date($r['end_date'])) ?></td>
                    <td class="text-end"><?= e($fmt($r['committed'])) ?></td><td class="text-end fw-semibold"><?= e($fmt($r['completed'])) ?></td><td class="text-end text-muted"><?= e($fmt($r['carried'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
