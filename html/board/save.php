<?php
declare(strict_types=1);
/**
 * Action `board_save` (log board.save; agent category other). Gate: board.configure in the project. Columns arrive as the
 * form's columns[i][name|statuses[]|wip_limit] or, from the manifest, as `columns` = "name:status+status:wip, …" (statuses by
 * id or name). Rule: every non-archived status in exactly one column. Replaces the columns in one transaction.
 */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/projects/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/projects/present.php';
require_once dirname(__DIR__, 2) . '/app/features/issues/present.php';
require_once dirname(__DIR__, 2) . '/app/features/board/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/board/present.php';
require_post();
verify_csrf();
require_login();
$pdo = db();
$me = (int) current_member_id();
$raw = request_string('project') ?: request_string('project_id') ?: request_string('key');
$project = ctype_digit($raw) ? find_project($pdo, (int) $raw) : (preg_match('/^[A-Za-z][A-Za-z0-9]{1,9}$/', $raw) ? find_project_by_key($pdo, strtoupper($raw)) : null);
if ($project === null) {
    refuse(404, 'Project not found.');
}
$id = (int) $project['project_id'];
require_right_in('board.configure', $id);
require_project_active($pdo, $id);
$before = find_board_config($pdo, $id);
$statusByName = [];
$statusIds = [];
foreach ($before['statuses'] as $s) {
    $statusByName[mb_strtolower((string) $s['name'])] = (int) $s['status_id'];
    $statusIds[(int) $s['status_id']] = (string) $s['name'];
}
$errors = [];
$columns = [];
$posted = $_POST['columns'] ?? null;
if (is_array($posted)) {
    foreach ($posted as $row) {
        if (!is_array($row)) { continue; }
        $name = trim((string) ($row['name'] ?? ''));
        if ($name === '' && empty($row['statuses'])) { continue; }        // a blank added row
        $columns[] = ['name' => $name, 'status_ids' => array_map('intval', (array) ($row['statuses'] ?? [])),
                      'wip_limit' => trim((string) ($row['wip_limit'] ?? '')) === '' ? null : (int) $row['wip_limit'], 'wip_raw' => trim((string) ($row['wip_limit'] ?? ''))];
    }
} elseif (is_string($posted) && trim($posted) !== '') {
    foreach (array_filter(array_map('trim', explode(',', $posted))) as $spec) {
        $parts = array_map('trim', explode(':', $spec, 3));
        $ids = [];
        foreach (array_filter(array_map('trim', explode('+', $parts[1] ?? ''))) as $s) {
            $ids[] = ctype_digit($s) ? (int) $s : ($statusByName[mb_strtolower($s)] ?? 0);
        }
        $columns[] = ['name' => (string) ($parts[0] ?? ''), 'status_ids' => $ids, 'wip_limit' => ($parts[2] ?? '') === '' ? null : (int) $parts[2], 'wip_raw' => (string) ($parts[2] ?? '')];
    }
}
$swimlane = request_string('swimlane', $before['swimlane']);
$mode = request_string('board_mode', $before['board_mode']);
if (!in_array($swimlane, BOARD_LANES, true)) { $errors[] = 'The swimlane is none, assignee or epic.'; }
if (!in_array($mode, PROJECT_BOARD_MODES, true)) { $errors[] = 'The board mode is scrum or kanban.'; }
if ($columns === []) { $errors[] = 'A board needs at least one column.'; }
$seenNames = [];
$placed = [];
foreach ($columns as $n => $c) {
    if ($c['name'] === '' || mb_strlen($c['name']) > 40) { $errors[] = 'Column ' . ($n + 1) . ' needs a name of 1 to 40 characters.'; }
    if (isset($seenNames[mb_strtolower($c['name'])])) { $errors[] = 'Two columns are named ' . $c['name'] . '.'; }
    $seenNames[mb_strtolower($c['name'])] = true;
    if ($c['wip_raw'] !== '' && (!ctype_digit($c['wip_raw']) || (int) $c['wip_raw'] < 1)) { $errors[] = 'A WIP limit is a whole number above 0 (' . $c['name'] . ').'; }
    foreach ($c['status_ids'] as $sid) {
        if (!isset($statusIds[$sid])) { $errors[] = 'Column ' . $c['name'] . ' names a status that is not in this project\'s workflow.'; continue; }
        if (isset($placed[$sid])) { $errors[] = 'The status ' . $statusIds[$sid] . ' is in two columns (' . $placed[$sid] . ' and ' . $c['name'] . ').'; }
        $placed[$sid] = $c['name'];
    }
}
foreach ($statusIds as $sid => $name) {
    if (!isset($placed[$sid])) { $errors[] = 'The status ' . $name . ' is in no column — every status needs one.'; }
}
$renderForm = function (array $errs) use ($pdo, $project, $id, $columns, $swimlane, $mode, $before): never {
    emit_action_status(false, ['errors' => $errs]);
    if (wants_json()) { respond_invalid($errs); }
    http_response_code(422);
    header('HX-Retarget: #page-content');
    header('HX-Reswap: innerHTML');
    $cfg = $before;
    $cfg['columns'] = array_map(static fn (array $c): array => ['column_id' => 0, 'name' => $c['name'], 'wip_limit' => $c['wip_limit'], 'status_ids' => $c['status_ids']], $columns);
    $cfg['swimlane'] = $swimlane; $cfg['board_mode'] = $mode;
    echo view('projects/partials/board-form.php', ['project' => $project, 'config' => $cfg, 'errors' => $errs]);
    exit;
};
if ($errors !== []) {
    $renderForm(array_values(array_unique($errors)));
}
$pdo->beginTransaction();
try {
    $after = save_board_config($pdo, $id, $columns, $swimlane, $mode, $me);
    $shape = static fn (array $cfg): array => ['columns' => array_map(static fn (array $c): array => [$c['name'] => ['statuses' => $c['status_ids'], 'wip' => $c['wip_limit']]], $cfg['columns']), 'swimlane' => $cfg['swimlane'], 'mode' => $cfg['board_mode']];
    log_activity($pdo, 'board.save', 'project', $id, ['before' => $shape($before), 'after' => $shape($after)]);
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    error_log('board save failed: ' . $e->getMessage());
    refuse(500, 'The board could not be saved.');
}
emit_action_status(true, ['did' => 'Saved the board of ' . $project['key'], 'record_id' => $id, 'refresh' => 'boardChanged']);
saved_go('/projects/' . $project['key'] . '/board', 'boardChanged');
