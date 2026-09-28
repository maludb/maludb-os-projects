<?php
declare(strict_types=1);
/**
 * /my-work — everything on my plate across projects (screen `my-work`). Tabs are URLs: ?tab=assigned|watching|review|reported.
 * Pattern B on #my-work-results (the tab strip and the list swap together). JSON: the shared my_work shape.
 */
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/features/mywork/queries.php';
require_once dirname(__DIR__) . '/app/features/mywork/present.php';
require_once dirname(__DIR__) . '/app/features/issues/present.php';
require_login();
$pdo = db();
$me = (int) current_member_id();
$tab = request_string('tab', 'assigned');
if (!in_array($tab, MY_WORK_TABS, true)) {
    $tab = 'assigned';
}
$rows = find_my_work($pdo, $me, $tab);
$counts = my_work_counts($pdo, $me);
log_activity($pdo, 'screen.view', null, null, ['screen' => 'my-work', 'after' => ['tab' => $tab]]);
if (wants_json()) {
    respond_screen(['tab' => $tab, 'counts' => $counts, 'items' => array_map('present_my_work_row', $rows), 'total' => count($rows)]);
}
$data = ['rows' => $rows, 'tab' => $tab, 'counts' => $counts, 'tz' => member_timezone()];
$resultsHtml = view('mywork/partials/rows.php', $data);
if (is_htmx_request() && ($_SERVER['HTTP_HX_TARGET'] ?? '') === 'my-work-results') {
    header('Vary: HX-Request');
    echo $resultsHtml;
    exit;
}
render_screen('My work', view('mywork/page.php', $data + ['resultsHtml' => $resultsHtml]), ['activeNav' => 'my-work', 'screen' => 'my-work']);
