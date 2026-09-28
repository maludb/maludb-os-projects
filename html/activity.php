<?php
declare(strict_types=1);
/**
 * /activity — the trail the caller may see (screen `activity`), filtered by project (key), member (actor), action prefix,
 * period (since days) or one record (entity=issue:KEY-1 · kind:id; or the older entity_type + entity_id). Pattern B on
 * #activity-results. ?format=csv downloads the same page (log `activity.export`). JSON: rows with their sentences.
 */
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/features/activity/queries.php';
require_once dirname(__DIR__) . '/app/features/activity/sentence.php';
require_login();
$pdo = db();
$filters = [
    'project' => strtoupper(trim(request_string('project'))), 'member' => request_integer('member'), 'action' => trim(request_string('action')),
    'since' => request_integer('since'), 'entity' => trim(request_string('entity')), 'entity_type' => request_string('entity_type'), 'entity_id' => request_integer('entity_id'),
];
if (!preg_match('/^[A-Z][A-Z0-9]{1,9}$/', $filters['project'])) { $filters['project'] = ''; }
if (!preg_match('/^[a-z_]+(\.[a-z_]+)*\.?$/', $filters['action'])) { $filters['action'] = ''; }
if (!in_array($filters['since'], ACTIVITY_SINCE_ALLOWED, true)) { $filters['since'] = null; }
if (!preg_match('/^[a-z_]{1,40}:[A-Za-z0-9-]{1,40}$/', $filters['entity'])) { $filters['entity'] = ''; }
if (!preg_match('/^[a-z_]{1,40}$/', $filters['entity_type'])) { $filters['entity_type'] = ''; }
if ($filters['entity'] === '' && $filters['entity_type'] !== '' && $filters['entity_id'] !== null) {
    $filters['entity'] = $filters['entity_type'] . ':' . $filters['entity_id'];   // one form of the record filter
}
$page = max(1, (int) (request_integer('page') ?? 1));
$result = find_activity($pdo, $filters, $page);
$rows = $result['rows'];
foreach ($rows as &$r) {
    $r['sentence'] = activity_sentence($r);
    $r['href'] = activity_record_href($r);
}
unset($r);
$query = array_filter(['project' => $filters['project'], 'member' => $filters['member'], 'action' => $filters['action'], 'since' => $filters['since'], 'entity' => $filters['entity']], static fn ($v) => $v !== null && $v !== '');
if (request_string('format') === 'csv') {
    log_activity($pdo, 'activity.export', null, null, ['after' => ['filters' => $query, 'page' => $page, 'rows' => count($rows)]]);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="activity-' . gmdate('Ymd-His') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['occurred_at', 'actor', 'actor_kind', 'action', 'sentence', 'entity_type', 'entity_id', 'record', 'source', 'agent_run_id', 'request_id']);
    foreach ($rows as $r) {
        fputcsv($out, [$r['occurred_at'], $r['actor_name'] ?? 'system', $r['actor_kind'] ?? '', $r['action'], $r['sentence'], $r['entity_type'] ?? '', $r['entity_id'] ?? '',
            $r['issue_key'] ?? ($r['entity_type'] !== null ? str_replace('_', ' ', $r['entity_type']) . ' #' . $r['entity_id'] : ''), $r['source'], $r['agent_run_id'] ?? '', $r['request_id'] ?? '']);
    }
    fclose($out);
    exit;
}
log_activity($pdo, 'screen.view', null, null, ['screen' => 'activity', 'after' => ['filters' => $query]]);
if (wants_json()) {
    respond_screen(['rows' => array_map(static fn (array $r): array => [
        'activity_id' => (int) $r['activity_id'], 'occurred_at' => json_ts($r['occurred_at']), 'actor' => ['member_id' => $r['actor_member_id'] !== null ? (int) $r['actor_member_id'] : null, 'name' => $r['actor_name'], 'kind' => $r['actor_kind']],
        'action' => $r['action'], 'sentence' => $r['sentence'], 'entity_type' => $r['entity_type'], 'entity_id' => $r['entity_id'] !== null ? (int) $r['entity_id'] : null, 'record' => $r['issue_key'] ?? null, 'href' => $r['href'],
        'source' => $r['source'], 'agent_run_id' => $r['agent_run_id'] !== null ? (int) $r['agent_run_id'] : null,
    ], $rows), 'page' => $page, 'more' => $result['more'], 'filters' => $query]);
}
$data = ['rows' => $rows, 'page' => $page, 'more' => $result['more'], 'filters' => $filters, 'query' => $query, 'tz' => member_timezone()];
$resultsHtml = view('activity/partials/rows.php', $data);
if (is_htmx_request() && ($_SERVER['HTTP_HX_TARGET'] ?? '') === 'activity-results') {
    header('Vary: HX-Request');
    echo $resultsHtml;
    exit;
}
render_screen('Activity', view('activity/page.php', $data + [
    'resultsHtml' => $resultsHtml, 'actors' => activity_actors($pdo),
    'projects' => $pdo->query('SELECT key, name FROM mcp_projects ORDER BY key')->fetchAll(),
]), ['activeNav' => 'activity', 'screen' => 'activity', 'entity' => $filters['entity'] !== '' ? explode(':', $filters['entity'])[0] : '', 'recordId' => $filters['entity'] !== '' ? explode(':', $filters['entity'])[1] : '']);
