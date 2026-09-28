<?php
declare(strict_types=1);
/**
 * /projects/{KEY}/reports?report=velocity|burndown|cycle|flow|scope|hygiene — the project's reports, one at a
 * time, the report a URL (screen reports). Pattern B: the pills re-fetch #report-body alone. ?format=csv on the
 * flow report answers the rows as text/csv (logged report.export). Nothing here writes.
 */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/projects/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/projects/present.php';
require_once dirname(__DIR__, 2) . '/app/features/reports/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/reports/present.php';
require_once dirname(__DIR__, 2) . '/app/views/shared/chart.php';   // chart_bars, chart_line, chart_stacked
require_login();
$pdo = db();
$key = strtoupper(request_string('key'));
$project = $key !== '' && preg_match('/^[A-Z][A-Z0-9]{1,9}$/', $key) ? find_project_by_key($pdo, $key) : null;
if ($project === null) {
    refuse(404, 'Project not found.');            // the same sentence for a private project one is not on
}
$id = (int) $project['project_id'];
$report = request_string('report', 'velocity');
if (!in_array($report, REPORT_KINDS, true)) {
    $report = 'velocity';
}
$sprints = report_sprint_options($pdo, $id);
$sprintId = request_integer('sprint');
if ($sprintId !== null && !in_array($sprintId, array_map('intval', array_column($sprints, 'sprint_id')), true)) {
    $sprintId = null;
}
if ($sprintId === null && $sprints !== []) {
    $sprintId = (int) $sprints[0]['sprint_id'];   // the active sprint, else the newest closed
}
$since = request_integer('since');
$cycleDays = in_array($since, REPORT_CYCLE_WINDOWS, true) ? $since : 90;
$flowDays = in_array($since, REPORT_FLOW_WINDOWS, true) ? $since : 30;

$data = match ($report) {
    'velocity' => (static function () use ($pdo, $id): array {
        $rows = report_velocity($pdo, $id, 6);
        $last3 = array_slice($rows, -3);
        $avg = $last3 === [] ? null : round(array_sum(array_map(static fn (array $r): float => (float) ($r['completed'] ?? 0), $last3)) / count($last3), 1);
        return ['rows' => $rows, 'average' => $avg];
    })(),
    'burndown' => $sprintId === null ? ['sprint' => null, 'days' => []] : report_burndown($pdo, $sprintId),
    'cycle' => ['days' => $cycleDays, 'rows' => report_cycle_time($pdo, $id, $cycleDays)],
    'flow' => ['days' => $flowDays, 'rows' => report_flow($pdo, $id, $flowDays)],
    'scope' => (static function () use ($pdo, $sprintId): array {
        $rows = $sprintId === null ? [] : report_scope_changes($pdo, $sprintId);
        $added = 0.0; $removed = 0.0;
        foreach ($rows as $r) {
            if ($r['event'] === 'added') { $added += (float) ($r['points'] ?? 0); }
            if ($r['event'] === 'removed') { $removed += (float) ($r['points'] ?? 0); }
        }
        return ['sprint_id' => $sprintId, 'rows' => $rows, 'totals' => ['added' => $added, 'removed' => $removed, 'net' => $added - $removed]];
    })(),
    default => ['rows' => report_hygiene($pdo, $id)],
};

if ($report === 'flow' && request_string('format') === 'csv') {
    log_activity($pdo, 'report.export', 'project', $id, ['screen' => 'reports', 'after' => ['report' => 'flow', 'days' => $flowDays]]);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $key . '-flow-' . $flowDays . 'd.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['day', 'todo', 'in_progress', 'done']);
    foreach ($data['rows'] as $r) {
        fputcsv($out, [$r['day'], (int) $r['todo'], (int) $r['in_progress'], (int) $r['done']]);
    }
    fclose($out);
    exit;
}

log_activity($pdo, 'screen.view', 'project', $id, ['screen' => 'reports', 'after' => ['report' => $report]]);
if (wants_json()) {
    respond_screen(['project' => present_project_card($project), 'report' => $report, 'data' => present_report($report, $data),
        'sprints' => array_map(static fn (array $s): array => ['sprint_id' => (int) $s['sprint_id'], 'name' => (string) $s['name'], 'state' => (string) $s['state']], $sprints)]);
}
$vars = ['project' => $project, 'report' => $report, 'rd' => $data, 'sprints' => $sprints, 'sprintId' => $sprintId, 'cycleDays' => $cycleDays, 'flowDays' => $flowDays, 'tz' => member_timezone()];
$bodyHtml = view('reports/body.php', $vars);
if (is_htmx_request() && ($_SERVER['HTTP_HX_TARGET'] ?? '') === 'report-body') {
    header('Vary: HX-Request');
    echo $bodyHtml;
    exit;
}
render_screen($project['key'] . ' · Reports', view('projects/partials/reports.php', $vars + ['bodyHtml' => $bodyHtml]),
    ['activeNav' => 'projects-list', 'screen' => 'reports', 'entity' => 'project', 'recordId' => (string) $id]);
