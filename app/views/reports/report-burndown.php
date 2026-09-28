<?php /** Burndown: remaining vs the ideal line, scope as a faint step. Data: rd{sprint, days}, sprints, sprintId, url */
$s = $rd['sprint']; $days = $rd['days'];
$fmt = static fn ($v): string => $v === null ? '—' : rtrim(rtrim(number_format((float) $v, 1, '.', ''), '0'), '.');
?>
<div id="report-burndown">
    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
        <p class="fs-13 text-muted mb-0 flex-grow-1" id="report-burndown-lead"><?php
            if ($s === null) { echo 'No sprint has run yet — a burndown shows the points left each day of a sprint.'; }
            elseif ($days === []) { echo 'This sprint has not started.'; }
            else { $last = end($days); echo e($s['name']) . ': ' . e($fmt($last['remaining'])) . ' of ' . e($fmt($last['scope'])) . ' points remain' . ($s['end_date'] ? ' (ends ' . e(format_date($s['end_date'])) . ')' : '') . '.'; }
        ?></p>
        <?php if (count($sprints) > 1): ?>
        <form class="d-flex align-items-center gap-2" id="report-burndown-sprint-form" method="get" action="<?= e($url('burndown')) ?>"
              hx-get="<?= e($url('burndown')) ?>" hx-target="#report-body" hx-swap="outerHTML" hx-trigger="change" hx-push-url="true">
            <input type="hidden" name="report" value="burndown">
            <select name="sprint" id="report-burndown-sprint" class="form-select form-select-sm" aria-label="Sprint">
                <?php foreach ($sprints as $o): ?><option value="<?= (int) $o['sprint_id'] ?>" <?= (int) $o['sprint_id'] === $sprintId ? 'selected' : '' ?>><?= e($o['name']) ?> (<?= e($o['state']) ?>)</option><?php endforeach; ?>
            </select>
        </form>
        <?php endif; ?>
    </div>
    <?php if ($s !== null && $days !== []):
        $committed = (float) ($s['committed_points'] ?? 0);
        $total = max(1, (int) ((strtotime((string) $s['end_date']) - strtotime((string) $s['start_date'])) / 86400));
        $remaining = []; $ideal = []; $scope = []; $labels = [];
        foreach (array_values($days) as $i => $d) {
            $remaining[] = (float) $d['remaining']; $scope[] = (float) $d['scope'];
            $ideal[] = round($committed * max(0.0, 1 - $i / $total), 2);
            $labels[] = date('j M', strtotime((string) $d['day']));
        }
    ?>
    <div class="mb-3">
        <?= chart_line([['label' => 'Remaining points', 'points' => $remaining], ['label' => 'Ideal', 'points' => $ideal, 'dashed' => true], ['label' => 'Scope', 'points' => $scope, 'dashed' => true]],
            ['width' => 640, 'height' => 240, 'axes' => true, 'labels' => $labels, 'title' => 'Burndown of ' . $s['name'] . ': remaining, ideal and scope, in points by day', 'id' => 'report-burndown-chart']) ?>
        <div class="fs-11 text-muted">Points by day · solid = remaining, dashed grey = ideal from <?= e($fmt($committed)) ?> to 0 on the end date, dashed green = scope.</div>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0" id="report-burndown-table">
            <thead class="thead-light"><tr><th>Day</th><th class="text-end">Scope</th><th class="text-end">Done</th><th class="text-end">Remaining</th></tr></thead>
            <tbody>
            <?php foreach ($days as $d): ?>
                <tr><td class="text-nowrap"><?= e(format_date($d['day'])) ?></td><td class="text-end"><?= e($fmt($d['scope'])) ?></td><td class="text-end"><?= e($fmt($d['done'])) ?></td><td class="text-end fw-semibold"><?= e($fmt($d['remaining'])) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
