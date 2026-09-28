<?php /** The report body: the pill strip and the chosen report. Region #report-body (Pattern B). Data: project, report, rd (the report's rows), sprints, sprintId, cycleDays, flowDays, tz */
$key = (string) $project['key']; $base = '/projects/' . $key . '/reports';
$pills = ['velocity' => 'Velocity', 'burndown' => 'Burndown', 'cycle' => 'Cycle time', 'flow' => 'Cumulative flow', 'scope' => 'Scope changes', 'hygiene' => 'Hygiene'];
$url = static function (string $r, array $extra = []) use ($base): string {
    return $base . '?' . http_build_query(['report' => $r] + array_filter($extra, static fn ($v): bool => $v !== null && $v !== ''));
};
?>
<div id="report-body">
    <ul class="nav nav-pills flex-wrap gap-1 mb-3" id="report-pills" role="tablist">
        <?php foreach ($pills as $r => $label): ?>
        <li class="nav-item"><a href="<?= e($url($r)) ?>" class="nav-link py-1 <?= $r === $report ? 'active' : '' ?>" id="report-pill-<?= e($r) ?>"
               hx-get="<?= e($url($r)) ?>" hx-target="#report-body" hx-swap="outerHTML" hx-push-url="<?= e($url($r)) ?>"><?= e($label) ?></a></li>
        <?php endforeach; ?>
    </ul>
    <?= view('reports/report-' . $report . '.php', ['project' => $project, 'rd' => $rd, 'sprints' => $sprints, 'sprintId' => $sprintId, 'cycleDays' => $cycleDays, 'flowDays' => $flowDays, 'tz' => $tz, 'url' => $url]) ?>
</div>
