<?php
declare(strict_types=1);

/** /activity — recent activity the caller may see, or one record's history (params entity_type, entity_id). */
require_once dirname(__DIR__) . '/app/bootstrap.php';

require_login();
$pdo = db();
$entityType = request_string('entity_type');
$entityId = request_integer('entity_id');
if (!preg_match('/^[a-z_]{0,40}$/', $entityType)) {
    $entityType = '';
}
$page = max(1, (int) (request_integer('page') ?? 1));
$per = 50;
$where = [];
$args = [];
if ($entityType !== '' && $entityId !== null) {
    $where[] = 'entity_type = :et AND entity_id = :ei';
    $args['et'] = $entityType;
    $args['ei'] = $entityId;
}
$sql = 'SELECT activity_id, occurred_at, actor_name, actor_kind, source, action, screen, entity_type, entity_id, subject_member_id, after, kernel_request_id
          FROM mcp_activity_log' . ($where !== [] ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY occurred_at DESC LIMIT :lim OFFSET :off';
$st = $pdo->prepare($sql);
foreach ($args as $k => $v) {
    $st->bindValue($k, $v);
}
$st->bindValue('lim', $per + 1, PDO::PARAM_INT);
$st->bindValue('off', ($page - 1) * $per, PDO::PARAM_INT);
$st->execute();
$rows = $st->fetchAll();
$more = count($rows) > $per;
$rows = array_slice($rows, 0, $per);
log_screen_view($pdo, 'activity');
render_screen('Activity', view('activity/page.php', ['rows' => $rows, 'page' => $page, 'more' => $more, 'entityType' => $entityType, 'entityId' => $entityId, 'tz' => member_timezone()]),
    ['activeNav' => 'activity', 'screen' => 'activity', 'entity' => $entityType, 'recordId' => (string) ($entityId ?? '')]);
