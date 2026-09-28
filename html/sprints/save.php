<?php
declare(strict_types=1);
/** Actions `sprint_create` / `sprint_update` (log sprint.create / sprint.update). Gate: sprint.plan in the project. A closed sprint is history (409). */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/projects/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/sprints/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/sprints/present.php';
require_post();
verify_csrf();
require_login();
$pdo = db();
$me = (int) current_member_id();
$existing = null;
$sid = request_integer('sprint') ?? request_integer('sprint_id') ?? request_integer('id');
if ($sid !== null) {
    $seen = find_sprint($pdo, $sid);
    if ($seen === null) { refuse(404, 'Sprint not found.'); }
    $projectId = (int) $seen['project_id'];
    require_right_in('sprint.plan', $projectId);
    if ($seen['state'] === 'closed') { refuse(409, 'A closed sprint is history; it is not edited.'); }
    $existing = find_sprint_for_edit($pdo, $sid);
    $project = find_project($pdo, $projectId);
} else {
    $raw = request_string('project') ?: request_string('project_id');
    $project = ctype_digit($raw) ? find_project($pdo, (int) $raw) : (preg_match('/^[A-Za-z][A-Za-z0-9]{1,9}$/', $raw) ? find_project_by_key($pdo, strtoupper($raw)) : null);
    if ($project === null) { refuse(404, 'Project not found.'); }
    $projectId = (int) $project['project_id'];
    require_right_in('sprint.plan', $projectId);
    if ($project['status'] === 'archived') { refuse(409, 'An archived project plans no sprint.'); }
}
$errors = [];
$name = trim(request_string('name'));
if ($existing !== null && !array_key_exists('name', $_POST)) { $name = (string) $existing['name']; }
if ($name === '' || mb_strlen($name) > 80) { $errors[] = 'A sprint needs a name of up to 80 characters.'; }
$goal = array_key_exists('goal', $_POST) ? trim((string) $_POST['goal']) : (string) ($existing['goal'] ?? '');
if (mb_strlen($goal) > 500) { $errors[] = 'The goal is at most 500 characters.'; }
$dates = [];
foreach (['start_date', 'end_date'] as $d) {
    if (!array_key_exists($d, $_POST) && $existing !== null) { $dates[$d] = $existing[$d]; continue; }
    $v = request_date($d);
    if ($v === false) { $errors[] = ($d === 'start_date' ? 'Start' : 'End') . ' is a date (YYYY-MM-DD).'; $v = null; }
    $dates[$d] = $v;
}
if ($dates['start_date'] !== null && $dates['end_date'] !== null && $dates['end_date'] < $dates['start_date']) { $errors[] = 'The end comes after the start.'; }
$sortRaw = request_string('sort_order');
$sortOrder = $sortRaw === '' ? null : (ctype_digit($sortRaw) && (int) $sortRaw <= 9999 ? (int) $sortRaw : false);
if ($sortOrder === false) { $errors[] = 'Order is a whole number from 0 to 9999.'; $sortOrder = null; }
$renderForm = static function (array $errors) use ($existing, $project, $name, $goal, $dates, $sortOrder, $pdo): never {
    emit_action_status(false, ['errors' => $errors]);
    if (wants_json()) { respond_invalid($errors); }
    http_response_code(422);
    if (is_htmx_request()) { header('HX-Retarget: #page-content'); header('HX-Reswap: innerHTML'); }
    $record = ['id' => $existing['id'] ?? null, 'name' => $name, 'goal' => $goal, 'start_date' => $dates['start_date'], 'end_date' => $dates['end_date'], 'sort_order' => $sortOrder ?? ($existing['sort_order'] ?? null)];
    render_screen($existing === null ? 'New sprint' : 'Edit sprint', view('sprints/form.php', ['record' => $record, 'project' => $project, 'isNew' => $existing === null, 'errors' => $errors, 'settings' => projects_settings($pdo)]),
        ['activeNav' => 'projects-list', 'screen' => $existing === null ? 'sprint-add' : 'sprint-edit', 'entity' => 'sprint', 'recordId' => (string) ($existing['id'] ?? '')]);
    exit;
};
if ($errors !== []) { $renderForm($errors); }
$fields = ['project_id' => $projectId, 'name' => $name, 'goal' => $goal === '' ? null : $goal, 'start_date' => $dates['start_date'], 'end_date' => $dates['end_date'], 'sort_order' => $sortOrder];
$pdo->beginTransaction();
try {
    $row = upsert_sprint($pdo, $existing !== null ? (int) $existing['id'] : null, $fields, $me);
    $after = ['name' => $row['name'], 'goal' => $row['goal'], 'start_date' => $row['start_date'], 'end_date' => $row['end_date'], 'sort_order' => (int) $row['sort_order']];
    if ($existing === null) {
        log_activity($pdo, 'sprint.create', 'sprint', (int) $row['id'], ['after' => $after + ['project_key' => $project['key']]]);
    } else {
        $before = ['name' => $existing['name'], 'goal' => $existing['goal'], 'start_date' => $existing['start_date'], 'end_date' => $existing['end_date'], 'sort_order' => (int) $existing['sort_order']];
        log_activity($pdo, 'sprint.update', 'sprint', (int) $row['id'], ['before' => array_diff_assoc($before, $after), 'after' => array_diff_assoc($after, $before)]);
    }
    $pdo->commit();
} catch (PDOException $e) {
    $pdo->rollBack();
    if ($e->getCode() === '23505') { $renderForm(['A sprint named ' . $name . ' already exists in this project.']); }
    if ($e->getCode() === '23514') { $renderForm(['The end comes after the start.']); }
    error_log('sprint save failed: ' . $e->getMessage());
    refuse(500, 'The sprint could not be saved.');
} catch (Throwable $e) { $pdo->rollBack(); error_log('sprint save failed: ' . $e->getMessage()); refuse(500, 'The sprint could not be saved.'); }
$id = (int) $row['id'];
emit_action_status(true, ['did' => ($existing === null ? 'Created sprint ' : 'Saved sprint ') . $row['name'], 'record_id' => $id, 'sprint_id' => $id, 'refresh' => 'sprintChanged']);
saved_go('/sprints/' . $id, 'sprintChanged');
