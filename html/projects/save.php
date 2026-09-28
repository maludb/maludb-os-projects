<?php
declare(strict_types=1);
/**
 * Actions `project_create` / `project_update` (log `project.create` / `project.update`). Create: a Projects admin or
 * project.admin business-wide; edit: project.admin in the project. Accepts the manifest's param names and the
 * column names; `_partial=1` under an action token fills the rest from the base row (app/partial_update.php).
 */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/projects/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/projects/present.php';
require_once dirname(__DIR__, 2) . '/app/partial_update.php';
require_post();
verify_csrf();
require_human();
$pdo = db();
partial_update_prefill($pdo);
$id = request_integer('project') ?? request_integer('id');
$before = null;
if ($id !== null) {
    $seen = find_project($pdo, $id);
    if ($seen === null) {
        refuse(404, 'Project not found.');
    }
    require_right_in('project.admin', $id);
    $before = find_project_for_edit($pdo, $id);
} elseif (!is_projects_admin() && !has_right('project.admin')) {
    refuse(403, 'Creating a project is for a Projects admin.');
}
$errors = [];
$f = [];
$f['key'] = strtoupper(trim(request_string('key')));
if ($id === null) {
    if (!preg_match('/^[A-Z][A-Z0-9]{1,9}$/', $f['key'])) { $errors[] = 'A key is two to ten letters and digits, starting with a letter.'; }
    else {
        $st = $pdo->prepare('SELECT 1 FROM projects WHERE key = :k'); $st->execute(['k' => $f['key']]);
        if ($st->fetchColumn() !== false) { $errors[] = 'That key is taken.'; }
    }
} else {
    $f['key'] = (string) $before['key'];
}
$f['name'] = trim(request_string('name'));
if ($f['name'] === '' || mb_strlen($f['name']) > 120) { $errors[] = 'A project needs a name of up to 120 characters.'; }
$f['description'] = trim(request_string('description')) ?: null;
$f['department_id'] = request_integer('department') ?? request_integer('department_id');
if ($f['department_id'] !== null) {
    $st = $pdo->prepare('SELECT 1 FROM mcp_departments WHERE department_id = :d AND archived_at IS NULL'); $st->execute(['d' => $f['department_id']]);
    if ($st->fetchColumn() === false) { $errors[] = 'That department does not exist here.'; }
}
$f['lead_member_id'] = request_integer('lead') ?? request_integer('lead_member_id');
if ($f['lead_member_id'] !== null) {
    $lead = find_member_by_id($pdo, $f['lead_member_id']);
    if ($lead === null || $lead['status'] !== 'active') { $errors[] = 'The lead must be an active member here.'; }
}
$f['visibility'] = request_string('visibility') ?: (string) projects_settings($pdo)['default_visibility'];
if (!in_array($f['visibility'], PROJECT_VISIBILITIES, true)) { $errors[] = 'Visibility is open or private.'; }
$f['board_mode'] = request_string('board_mode', 'scrum') ?: 'scrum';
if (!in_array($f['board_mode'], PROJECT_BOARD_MODES, true)) { $errors[] = 'The board is scrum or kanban.'; }
$f['customer'] = trim(request_string('customer')) ?: null;
if ($f['customer'] !== null && mb_strlen($f['customer']) > 120) { $errors[] = 'Customer is up to 120 characters.'; }
$renderForm = function (array $errs) use ($pdo, $id, $before, $f): never {
    emit_action_status(false, ['errors' => $errs]);
    if (wants_json()) { respond_invalid($errs); }
    http_response_code(422);
    header('HX-Retarget: #page-content');
    header('HX-Reswap: innerHTML');
    echo view('projects/form.php', ['record' => $f + ['id' => $id], 'isNew' => $id === null, 'errors' => $errs, 'departments' => department_options($pdo), 'members' => member_options($pdo)]);
    exit;
};
if ($errors !== []) {
    $renderForm($errors);
}
$pdo->beginTransaction();
try {
    $row = upsert_project($pdo, $id, $f, (int) current_member_id());
    if ($id === null) {
        log_activity($pdo, 'project.create', 'project', (int) $row['id'], ['after' => ['key' => $row['key'], 'name' => $row['name'], 'visibility' => $row['visibility'],
            'department_id' => $row['department_id'], 'lead_member_id' => $row['lead_member_id']], 'department_id' => $row['department_id']]);
        emit_action_status(true, ['did' => 'Created project ' . $row['key'] . ' ' . $row['name'], 'record_id' => (int) $row['id'], 'refresh' => 'projectChanged']);
    } else {
        $changedBefore = []; $changedAfter = [];
        foreach (['name', 'description', 'department_id', 'lead_member_id', 'visibility', 'board_mode', 'customer'] as $k) {
            if ((string) ($before[$k] ?? '') !== (string) ($row[$k] ?? '')) { $changedBefore[$k] = $before[$k]; $changedAfter[$k] = $row[$k]; }
        }
        log_activity($pdo, 'project.update', 'project', $id, ['before' => $changedBefore, 'after' => $changedAfter, 'department_id' => $row['department_id']]);
        emit_action_status(true, ['did' => 'Saved project ' . $row['key'], 'record_id' => $id, 'refresh' => 'projectChanged']);
    }
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    error_log('project save failed: ' . $e->getMessage());
    $renderForm(['The project could not be saved.']);
}
saved_go('/projects/' . $row['key'], 'projectChanged');
