<?php
declare(strict_types=1);
/**
 * Action `workflow_save` (log `workflow.save` + one `issue.transition` per issue an archived status hands on; agent category other).
 * Gate: project.admin in the project. The form posts statuses[i][id|name|category|sort_order], initial (index), transitions[from][to],
 * archive[i] (+ archive_to[i]); the manifest posts `statuses` = "name:category:order, …", `transitions` = "From>To, …", `initial` = a name.
 * Rules: one initial, unique names, every referenced status of this project, a status with issues archived only with a target.
 * Everything in one transaction; the issues of an archived status move under app.force_transition with `after.by = 'workflow'`.
 */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/projects/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/projects/present.php';
require_once dirname(__DIR__, 2) . '/app/features/workflow/queries.php';
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
require_right_in('project.admin', $id);
require_project_active($pdo, $id);
$before = find_workflow($pdo, $id);
$known = [];                                                       // status id => row (live)
foreach ($before['statuses'] as $s) { $known[(int) $s['status_id']] = $s; }
$archivedNames = array_map(static fn (array $a): string => mb_strtolower((string) $a['name']), $before['archived']);
$errors = [];
$statuses = [];                                                    // index => ['id', 'name', 'category', 'sort_order', 'is_initial']
$transitions = [];                                                 // [fromIndex, toIndex]
$archive = [];                                                     // index => targetIndex|null
$posted = $_POST['statuses'] ?? null;
if (is_array($posted)) {
    $initial = request_string('initial');
    foreach ($posted as $i => $row) {
        if (!is_array($row)) { continue; }
        $name = trim((string) ($row['name'] ?? ''));
        $sid = trim((string) ($row['id'] ?? ''));
        if ($name === '' && $sid === '') { continue; }               // a blank added row
        $statuses[(string) $i] = ['id' => $sid !== '' ? (int) $sid : null, 'name' => $name, 'category' => (string) ($row['category'] ?? ''),
            'sort_order' => (int) ($row['sort_order'] ?? 0), 'is_initial' => (string) $i === $initial];
    }
    foreach ((array) ($_POST['transitions'] ?? []) as $from => $tos) {
        foreach ((array) $tos as $to => $on) { if ($on) { $transitions[] = [(string) $from, (string) $to]; } }
    }
    foreach ((array) ($_POST['archive'] ?? []) as $i => $on) {
        if ($on) { $t = trim((string) (($_POST['archive_to'] ?? [])[$i] ?? '')); $archive[(string) $i] = $t === '' ? null : $t; }
    }
} elseif (is_string($posted) && trim($posted) !== '') {
    // The manifest's strings. Existing statuses are matched by name (renames need the form).
    $byName = [];
    foreach ($known as $sid => $s) { $byName[mb_strtolower((string) $s['name'])] = $sid; }
    $n = 0;
    foreach (array_filter(array_map('trim', explode(',', $posted))) as $spec) {
        $parts = array_map('trim', explode(':', $spec, 3));
        $name = (string) ($parts[0] ?? '');
        $statuses[(string) $n] = ['id' => $byName[mb_strtolower($name)] ?? null, 'name' => $name, 'category' => (string) ($parts[1] ?? ($known[$byName[mb_strtolower($name)] ?? 0]['category'] ?? 'todo')),
            'sort_order' => (int) ($parts[2] ?? ($n + 1) * 10), 'is_initial' => false];
        $n++;
    }
    $indexByName = [];
    foreach ($statuses as $i => $s) { $indexByName[mb_strtolower($s['name'])] = $i; }
    $initial = mb_strtolower(request_string('initial'));
    if ($initial !== '' && isset($indexByName[$initial])) { $statuses[$indexByName[$initial]]['is_initial'] = true; }
    elseif ($initial === '') { foreach ($statuses as $i => $s) { if ($s['id'] !== null && !empty($known[$s['id']]['is_initial'])) { $statuses[$i]['is_initial'] = true; } } }
    else { $errors[] = 'The initial status must be one of the statuses given.'; }
    $tr = request_string('transitions');
    if ($tr === '') {
        foreach ($before['transitions'] as $from => $tos) {           // unchanged moves among the statuses that stay
            foreach ($tos as $to) {
                $f = $indexByName[mb_strtolower((string) $known[$from]['name'])] ?? null; $t = $indexByName[mb_strtolower((string) ($known[$to]['name'] ?? ''))] ?? null;
                if ($f !== null && $t !== null) { $transitions[] = [$f, $t]; }
            }
        }
    } else {
        foreach (array_filter(array_map('trim', explode(',', $tr))) as $pair) {
            $ends = array_map('trim', explode('>', $pair, 2));
            $f = $indexByName[mb_strtolower($ends[0] ?? '')] ?? null; $t = $indexByName[mb_strtolower($ends[1] ?? '')] ?? null;
            if ($f === null || $t === null) { $errors[] = 'The move "' . $pair . '" names a status that is not in the list.'; continue; }
            $transitions[] = [$f, $t];
        }
    }
    // A known status left out of the list is archived; its issues go to the initial status.
    $initialIndex = null;
    foreach ($statuses as $i => $s) { if ($s['is_initial']) { $initialIndex = $i; } }
    foreach ($known as $sid => $s) {
        if (!isset($indexByName[mb_strtolower((string) $s['name'])])) {
            $statuses['k' . $sid] = ['id' => $sid, 'name' => (string) $s['name'], 'category' => (string) $s['category'], 'sort_order' => (int) $s['sort_order'], 'is_initial' => false];
            $archive['k' . $sid] = (int) $s['issues'] > 0 ? $initialIndex : null;
        }
    }
} else {
    $errors[] = 'A workflow needs its statuses.';
}
// ---- validation --------------------------------------------------------------------------------------------
$names = []; $initialCount = 0;
foreach ($statuses as $i => $s) {
    if ($s['name'] === '' || mb_strlen($s['name']) > 40) { $errors[] = 'Every status needs a name of 1 to 40 characters.'; }
    $lower = mb_strtolower($s['name']);
    if (isset($names[$lower])) { $errors[] = 'Two statuses are named ' . $s['name'] . '.'; }
    if (in_array($lower, $archivedNames, true) && ($s['id'] === null || !isset($known[$s['id']]) )) { $errors[] = 'An archived status is already named ' . $s['name'] . '.'; }
    $names[$lower] = true;
    if (!isset(WORKFLOW_CATEGORIES[$s['category']])) { $errors[] = 'The category of ' . $s['name'] . ' is todo, in_progress or done.'; }
    if ($s['id'] !== null && !isset($known[$s['id']])) { $errors[] = 'Status #' . $s['id'] . ' is not in this project\'s workflow.'; }
    if ($s['is_initial'] && !isset($archive[$i])) { $initialCount++; }
}
if ($statuses !== [] && $initialCount !== 1) { $errors[] = 'Exactly one status is where a new issue starts.'; }
$live = array_diff_key($statuses, $archive);
if ($statuses !== [] && $live === []) { $errors[] = 'A workflow keeps at least one status.'; }
foreach ($archive as $i => $target) {
    if (!isset($statuses[$i]) || $statuses[$i]['id'] === null) { unset($archive[$i]); continue; }
    $issues = (int) ($known[$statuses[$i]['id']]['issues'] ?? 0);
    if ($issues > 0 && ($target === null || !isset($statuses[$target]) || isset($archive[$target]))) {
        $errors[] = $statuses[$i]['name'] . ' holds ' . $issues . ' issue' . ($issues === 1 ? '' : 's') . ' — choose a status to move them to before archiving it.';
    }
}
foreach ($transitions as [$f, $t]) {
    if (!isset($statuses[$f], $statuses[$t])) { $errors[] = 'A move names a status that is not in the list.'; break; }
}
$renderForm = function (array $errs) use ($pdo, $project, $id, $before): never {
    emit_action_status(false, ['errors' => $errs]);
    if (wants_json()) { respond_invalid($errs); }
    http_response_code(422);
    header('HX-Retarget: #project-settings-body');
    header('HX-Reswap: innerHTML');
    echo view('projects/partials/settings-workflow.php', ['project' => $project, 'workflow' => $before, 'errors' => $errs, 'canAdmin' => true]);
    exit;
};
if ($errors !== []) {
    $renderForm(array_values(array_unique($errors)));
}
$pdo->beginTransaction();
try {
    $result = save_workflow($pdo, $id, $statuses, $transitions, $archive, $me);
    $after = find_workflow($pdo, $id);
    $shapeB = workflow_log_shape($before); $shapeA = workflow_log_shape($after);
    log_activity($pdo, 'workflow.save', 'project', $id, ['before' => $shapeB, 'after' => $shapeA + ['archived' => $result['archived'], 'moved_issues' => count($result['moved'])],
        'department_id' => $project['department_id'] !== null ? (int) $project['department_id'] : null]);
    $statusName = [];
    foreach (array_merge($before['statuses'], $after['statuses'], $after['archived']) as $s) { $statusName[(int) $s['status_id']] = (string) $s['name']; }
    foreach ($result['moved'] as [$issueId, $key, $from, $to]) {
        log_activity($pdo, 'issue.transition', 'issue', $issueId, ['before' => ['status_id' => $from, 'status' => $statusName[$from] ?? null],
            'after' => ['status_id' => $to, 'status' => $statusName[$to] ?? null, 'by' => 'workflow', 'key' => $key]]);
    }
    $pdo->commit();
} catch (PDOException $e) {
    $pdo->rollBack();
    if (str_contains($e->getMessage(), 'workflow_statuses_project_id_name_key')) { $renderForm(['Two statuses would share a name — an archived one may already carry it.']); }
    error_log('workflow save failed: ' . $e->getMessage());
    $renderForm(['The workflow could not be saved.']);
} catch (Throwable $e) {
    $pdo->rollBack();
    error_log('workflow save failed: ' . $e->getMessage());
    $renderForm(['The workflow could not be saved.']);
}
$did = 'Saved the workflow of ' . $project['key'] . ($result['archived'] !== [] ? ' — archived ' . implode(', ', $result['archived']) . ($result['moved'] !== [] ? ', moved ' . count($result['moved']) . ' issue' . (count($result['moved']) === 1 ? '' : 's') : '') : '');
emit_action_status(true, ['did' => $did, 'record_id' => $id, 'refresh' => 'workflowChanged', 'archived' => $result['archived'], 'moved_issues' => count($result['moved'])]);
saved_go('/projects/' . $project['key'] . '/settings?tab=workflow', 'workflowChanged, boardChanged');
