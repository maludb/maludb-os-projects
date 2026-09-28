<?php
declare(strict_types=1);
/** Action `filter_delete` (log `saved_filter.delete`): remove one of my saved filters. Own only. */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/filters/queries.php';
require_post();
verify_csrf();
$pdo = db();
$me = (int) current_member_id();
$id = request_integer('id');
$filter = $id !== null ? find_saved_filter($pdo, $id) : null;
if ($filter === null || (int) $filter['owner_id'] !== $me) {
    refuse(404, 'Filter not found.');
}
$pdo->beginTransaction();
try {
    if (!delete_saved_filter($pdo, $id, $me)) {
        throw new RuntimeException('nothing deleted');
    }
    log_activity($pdo, 'saved_filter.delete', 'saved_filter', $id, ['before' => ['name' => $filter['name'], 'project_id' => $filter['project_id'] !== null ? (int) $filter['project_id'] : null, 'is_shared' => (bool) $filter['is_shared']]]);
    emit_action_status(true, ['did' => 'Deleted the filter ' . $filter['name'], 'record_id' => $id]);
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    error_log('filter delete failed: ' . $e->getMessage());
    refuse(422, 'The filter could not be deleted.');
}
saved_go('/filters');
