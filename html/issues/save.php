<?php
declare(strict_types=1);
/**
 * Actions `issue_create` (log issue.create) and `issue_update` (log issue.update; a partial update: only
 * the keys present change). Gate: projects_can_work on the project. A subtask's project and sprint are
 * its parent's. The manifest's names and the columns' are both accepted.
 */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/projects/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/projects/present.php';
require_once dirname(__DIR__, 2) . '/app/features/issues/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/issues/present.php';
require_once dirname(__DIR__, 2) . '/app/features/issues/respond.php';
require_post();
verify_csrf();
require_login();
$pdo = db();
$me = (int) current_member_id();
$issueParam = request_string('issue') ?: request_string('issue_id');
$before = $issueParam !== '' ? issue_from_request($pdo) : null;
$isNew = $before === null;

if ($isNew) {
    $parentRaw = request_string('parent') ?: request_string('parent_issue_id');
    $parent = null;
    if ($parentRaw !== '') {
        $pv = ctype_digit($parentRaw) ? find_issue($pdo, (int) $parentRaw) : find_issue_by_key($pdo, $parentRaw);
        if ($pv === null) { refuse(404, 'Parent issue not found.'); }
        if ($pv['type'] === 'subtask') { refuse(422, 'A subtask cannot have subtasks.'); }
        $parent = $pv;
        $projectId = (int) $pv['project_id'];
    } else {
        $pRaw = request_string('project') ?: request_string('project_id');
        $project = ctype_digit($pRaw) ? find_project($pdo, (int) $pRaw) : ($pRaw !== '' ? find_project_by_key($pdo, $pRaw) : null);
        if ($project === null) { refuse(404, 'Project not found.'); }
        $projectId = (int) $project['project_id'];
    }
} else {
    $projectId = (int) $before['project_id'];
    $parent = $before['parent_issue_id'] !== null ? find_issue($pdo, (int) $before['parent_issue_id']) : null;
}
require_can_work($projectId);
require_project_active($pdo, $projectId);

$errors = [];
$f = [];
$has = static fn (string ...$names): bool => array_reduce($names, static fn (bool $c, string $n): bool => $c || array_key_exists($n, $_POST), false);
$first = static function (string ...$names): ?string { foreach ($names as $n) { if (array_key_exists($n, $_POST)) { return trim((string) $_POST[$n]); } } return null; };
$options = issue_form_options($pdo, $projectId);
$settings = projects_settings($pdo);

if ($isNew || $has('type')) {
    $type = $parent !== null ? 'subtask' : (request_string('type') ?: ($isNew ? 'task' : (string) $before['type']));
    if (!in_array($type, ['story', 'task', 'bug', 'subtask'], true) || ($type === 'subtask' && $parent === null)) { $errors[] = 'Type is story, task or bug (a subtask comes from its parent).'; }
    if (!$isNew && $before['parent_issue_id'] !== null && $type !== 'subtask') { $errors[] = 'A subtask stays a subtask; move it out of its parent first.'; }
    $f['type'] = $type;
}
if ($isNew || $has('summary')) {
    $f['summary'] = request_string('summary');
    if ($f['summary'] === '' || mb_strlen($f['summary']) > 255) { $errors[] = 'A summary is 1 to 255 characters.'; }
}
if ($has('description')) { $f['description'] = request_string('description') ?: null; }
if ($has('assignee', 'assignee_member_id')) {
    $v = $first('assignee', 'assignee_member_id');
    $f['assignee_member_id'] = $v === '' || $v === null ? null : (ctype_digit($v) ? (int) $v : null);
    if ($f['assignee_member_id'] !== null) {
        $m = find_member_by_id($pdo, $f['assignee_member_id']);
        if ($m === null || $m['status'] !== 'active') { $errors[] = 'The assignee must be an active member here.'; }
    } elseif ($v !== '' && $v !== null) {
        $st = $pdo->prepare("SELECT member_id FROM mcp_members WHERE status = 'active' AND display_name ILIKE :n ORDER BY member_kind = 'agent', display_name LIMIT 2");
        $st->execute(['n' => $v]);
        $rows = $st->fetchAll(PDO::FETCH_COLUMN);
        if (count($rows) !== 1) { $errors[] = count($rows) === 0 ? 'No member named ' . $v . '.' : 'More than one member matches ' . $v . '; use the id.'; } else { $f['assignee_member_id'] = (int) $rows[0]; }
    }
}
if ($isNew || $has('priority')) {
    $f['priority'] = request_string('priority') ?: 'medium';
    if (!in_array($f['priority'], ISSUE_PRIORITIES, true)) { $errors[] = 'Priority is lowest, low, medium, high or highest.'; }
}
if ($has('story_points', 'points')) {
    $v = $first('story_points', 'points');
    $f['story_points'] = $v === '' || $v === null ? null : (is_numeric($v) && (float) $v >= 0 && (float) $v < 1000 ? (float) $v : false);
    if ($f['story_points'] === false) { $errors[] = 'Story points is a number of 0 or more.'; $f['story_points'] = null; }
}
if ($has('estimate_hours', 'hours')) {
    $v = $first('estimate_hours', 'hours');
    $f['estimate_hours'] = $v === '' || $v === null ? null : (is_numeric($v) && (float) $v >= 0 ? (float) $v : false);
    if ($f['estimate_hours'] === false) { $errors[] = 'Estimate hours is a number of 0 or more.'; $f['estimate_hours'] = null; }
}
if ($has('epic', 'epic_id')) {
    $v = $first('epic', 'epic_id');
    $f['epic_id'] = $v === '' || $v === null ? null : (int) $v;
    if ($f['epic_id'] !== null && !in_array($f['epic_id'], array_map(static fn (array $e): int => (int) $e['epic_id'], $options['epics']), true)) { $errors[] = 'That epic is not an open epic of this project.'; }
}
if ($has('sprint', 'sprint_id')) {
    $v = $first('sprint', 'sprint_id');
    $f['sprint_id'] = $v === '' || $v === null || $v === 'backlog' ? null : (int) $v;
    if ($f['sprint_id'] !== null && !in_array($f['sprint_id'], array_map(static fn (array $s): int => (int) $s['sprint_id'], $options['sprints']), true)) { $errors[] = 'That sprint is not a future or active sprint of this project.'; }
}
if ($has('component', 'component_id')) {
    $v = $first('component', 'component_id');
    $f['component_id'] = $v === '' || $v === null ? null : (int) $v;
    if ($f['component_id'] !== null && !in_array($f['component_id'], array_map(static fn (array $c): int => (int) $c['component_id'], $options['components']), true)) { $errors[] = 'That component is not one of this project\'s.'; }
}
if ($has('labels')) {
    $f['labels'] = array_values(array_filter(array_map('intval', (array) $_POST['labels'])));
}
if ($has('due_date', 'due')) {
    $v = $first('due_date', 'due');
    $_POST['__due'] = $v;
    $d = request_date('__due');
    if ($d === false) { $errors[] = 'Due is a date (YYYY-MM-DD).'; } else { $f['due_date'] = $d; }
}
if ($isNew) {
    if ($parent !== null) { $f['parent_issue_id'] = (int) $parent['issue_id']; unset($f['story_points'], $f['epic_id'], $f['sprint_id']); }
    $f['project_id'] = $projectId;
    // A component's default assignee, when no assignee was chosen.
    if (($f['assignee_member_id'] ?? null) === null && ($f['component_id'] ?? null) !== null) {
        foreach ($options['components'] as $c) { if ((int) $c['component_id'] === $f['component_id'] && $c['default_assignee_id'] !== null) { $f['assignee_member_id'] = (int) $c['default_assignee_id']; } }
    }
}

$renderForm = function (array $errs) use ($pdo, $projectId, $f, $before, $parent, $isNew, $options, $settings): never {
    emit_action_status(false, ['errors' => $errs]);
    if (wants_json()) { respond_invalid($errs); }
    http_response_code(422);
    header('HX-Retarget: #page-content');
    header('HX-Reswap: innerHTML');
    $record = ($before ?? ['id' => null, 'project_id' => $projectId, 'type' => 'task', 'summary' => '', 'description' => '', 'assignee_member_id' => null, 'priority' => 'medium',
        'story_points' => null, 'estimate_hours' => null, 'epic_id' => null, 'sprint_id' => null, 'component_id' => null, 'due_date' => null, 'parent_issue_id' => null, 'labels' => []]);
    $record = array_merge($record, array_diff_key($f, ['project_id' => 1]));
    $st = $pdo->prepare('SELECT member_id FROM mcp_project_team WHERE project_id = :p AND removed_at IS NULL'); $st->execute(['p' => $projectId]);
    echo view('issues/form.php', ['record' => $record, 'project' => find_project($pdo, $projectId), 'parent' => $parent, 'options' => $options, 'settings' => $settings,
        'members' => member_options($pdo), 'team' => array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN)), 'errors' => $errs]);
    exit;
};
if ($errors !== []) { $renderForm($errors); }

$pdo->beginTransaction();
try {
    if ($isNew) {
        $row = create_issue($pdo, $f, $me);
        log_activity($pdo, 'issue.create', 'issue', (int) $row['id'], ['after' => ['key' => $row['key'], 'type' => $row['type'], 'summary' => $row['summary'],
            'assignee_member_id' => $row['assignee_member_id'], 'sprint_id' => $row['sprint_id'], 'epic_id' => $row['epic_id'], 'story_points' => $row['story_points'], 'parent_issue_id' => $row['parent_issue_id']]]);
        $pdo->commit();
        emit_action_status(true, ['did' => 'Created ' . $row['key'] . ': ' . $row['summary'], 'record_id' => (int) $row['id'], 'key' => $row['key'], 'refresh' => 'issueChanged']);
        saved_go('/issues/' . $row['key'], 'issueChanged');
    }
    [$b, $a] = update_issue($pdo, (int) $before['id'], $f, $me);
    [$cb, $ca] = issue_changed_fields($b, $a, array_keys(array_diff_key($f, ['labels' => 1])));
    if (array_key_exists('labels', $f)) { $ca['labels'] = $f['labels']; }
    if ($cb !== [] || $ca !== []) {
        log_activity($pdo, 'issue.update', 'issue', (int) $before['id'], ['before' => $cb, 'after' => $ca]);
    }
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    if ($e instanceof RuntimeException) { $renderForm([$e->getMessage()]); }
    error_log('issue save failed: ' . $e->getMessage());
    $renderForm(['The issue could not be saved.']);
}
emit_action_status(true, ['did' => 'Saved ' . $before['key'], 'record_id' => (int) $before['id'], 'key' => $before['key'], 'refresh' => 'issueChanged']);
if (is_htmx_request() && ($_SERVER['HTTP_HX_TARGET'] ?? '') === '' && request_string('summary') !== '' && count($f) === 1) {
    issue_action_done((string) $before['key'], 'Saved ' . $before['key']);   // the in-place summary edit: stay put, the regions refresh
}
saved_go('/issues/' . $before['key'], 'issueChanged');
