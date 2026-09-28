<?php
declare(strict_types=1);
/**
 * Actions `epic_create` / `epic_update` (log `epic.create` / `epic.update`). Gate: epic.write in the project.
 * Accepts the manifest's param names (project as KEY or id, epic as id) and the columns'.
 */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/projects/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/epics/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/epics/present.php';
require_post();
verify_csrf();
$pdo = db();
$id = request_integer('epic') ?? request_integer('epic_id') ?? request_integer('id');
$before = null;
if ($id !== null) {
    $seen = find_epic($pdo, $id);
    if ($seen === null) {
        refuse(404, 'Epic not found.');
    }
    $projectId = (int) $seen['project_id'];
    $before = find_epic_for_edit($pdo, $id);
} else {
    $raw = request_string('project') ?: request_string('project_id');
    $project = ctype_digit($raw) ? find_project($pdo, (int) $raw) : (preg_match('/^[A-Za-z][A-Za-z0-9]{1,9}$/', $raw) ? find_project_by_key($pdo, $raw) : null);
    if ($project === null) {
        refuse(404, 'Project not found.');
    }
    $projectId = (int) $project['project_id'];
}
require_right_in('epic.write', $projectId);
require_project_active($pdo, $projectId);
$project ??= find_project($pdo, $projectId);
$errors = [];
$f = [];
$f['project_id'] = $projectId;
$f['name'] = trim(request_string('name')) ?: (string) ($before['name'] ?? '');
if ($f['name'] === '' || mb_strlen($f['name']) > 120) { $errors[] = 'An epic needs a name of up to 120 characters.'; }
else {
    $st = $pdo->prepare('SELECT 1 FROM epics WHERE project_id = :p AND lower(name) = lower(:n) AND id IS DISTINCT FROM :id');
    $st->execute(['p' => $projectId, 'n' => $f['name'], 'id' => $id]);
    if ($st->fetchColumn() !== false) { $errors[] = 'An epic with that name already exists in this project.'; }
}
$f['summary'] = array_key_exists('summary', $_POST) ? (trim(request_string('summary')) ?: null) : ($before['summary'] ?? null);
$f['colour'] = request_string('colour') ?: (string) ($before['colour'] ?? 'primary');
if (!in_array($f['colour'], EPIC_COLOURS, true)) { $errors[] = 'Colour is one of the theme\'s tones.'; }
if (array_key_exists('target_date', $_POST)) {
    $d = request_date('target_date');
    if ($d === false) { $errors[] = 'Target date is not a date.'; $d = null; }
    $f['target_date'] = $d;
} else {
    $f['target_date'] = $before['target_date'] ?? null;
}
$so = request_string('sort_order');
$f['sort_order'] = $so === '' ? (int) ($before['sort_order'] ?? 0) : (ctype_digit(ltrim($so, '-')) ? (int) $so : 0);
$renderForm = function (array $errs) use ($pdo, $id, $project, $f): never {
    emit_action_status(false, ['errors' => $errs]);
    if (wants_json()) { respond_invalid($errs); }
    http_response_code(422);
    header('HX-Retarget: #page-content');
    header('HX-Reswap: innerHTML');
    echo view('epics/form.php', ['record' => $f + ['id' => $id], 'project' => $project, 'isNew' => $id === null, 'errors' => $errs]);
    exit;
};
if ($errors !== []) {
    $renderForm($errors);
}
$pdo->beginTransaction();
try {
    $row = upsert_epic($pdo, $id, $f, (int) current_member_id());
    if ($id === null) {
        log_activity($pdo, 'epic.create', 'epic', (int) $row['id'], ['after' => ['name' => $row['name'], 'target_date' => $row['target_date'], 'project_key' => $project['key']], 'department_id' => $project['department_id']]);
        emit_action_status(true, ['did' => 'Created epic ' . $row['name'], 'record_id' => (int) $row['id'], 'refresh' => 'epicChanged']);
    } else {
        $b = []; $a = [];
        foreach (['name', 'summary', 'colour', 'target_date', 'sort_order'] as $k) {
            if ((string) ($before[$k] ?? '') !== (string) ($row[$k] ?? '')) { $b[$k] = $before[$k]; $a[$k] = $row[$k]; }
        }
        log_activity($pdo, 'epic.update', 'epic', $id, ['before' => $b, 'after' => $a, 'department_id' => $project['department_id']]);
        emit_action_status(true, ['did' => 'Saved epic ' . $row['name'], 'record_id' => $id, 'refresh' => 'epicChanged']);
    }
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    error_log('epic save failed: ' . $e->getMessage());
    $renderForm(['The epic could not be saved.']);
}
saved_go('/epics/' . (int) $row['id'], 'epicChanged');
