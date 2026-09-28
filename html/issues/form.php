<?php
declare(strict_types=1);
/**
 * /issues/new?project=KEY&type=&parent=&epic=&sprint= and /issues/{KEY-n}/edit (screens issue-add, issue-edit).
 * A ?parent= makes a subtask: its type is fixed, points/epic/sprint are the parent's, its project too.
 */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/projects/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/projects/present.php';
require_once dirname(__DIR__, 2) . '/app/features/issues/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/issues/present.php';
require_login();
require_human();
$pdo = db();
$key = strtoupper(request_string('key'));
$record = null;
$parent = null;
if ($key !== '') {
    $view = find_issue_by_key($pdo, $key);
    if ($view === null) {
        respond_not_found('Issue not found.');
    }
    $projectId = (int) $view['project_id'];
    require_can_work($projectId);
    $record = find_issue_for_edit($pdo, (int) $view['issue_id']);
    $project = find_project($pdo, $projectId);
    $st = $pdo->prepare('SELECT label_id FROM issue_labels WHERE issue_id = :i');
    $st->execute(['i' => (int) $record['id']]);
    $record['labels'] = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    if ($record['parent_issue_id'] !== null) {
        $parent = find_issue($pdo, (int) $record['parent_issue_id']);
    }
} else {
    $parentKey = strtoupper(request_string('parent'));
    if ($parentKey !== '') {
        $parent = ctype_digit($parentKey) ? find_issue($pdo, (int) $parentKey) : find_issue_by_key($pdo, $parentKey);
        if ($parent === null) {
            respond_not_found('Issue not found.');
        }
        $projectId = (int) $parent['project_id'];
    } else {
        $project = find_project_by_key($pdo, strtoupper(request_string('project')));
        if ($project === null) {
            respond_not_found('Project not found.');
        }
        $projectId = (int) $project['project_id'];
    }
    require_can_work($projectId);
    $project = $project ?? find_project($pdo, $projectId);
    $type = request_string('type', 'task');
    $record = ['id' => null, 'project_id' => $projectId, 'type' => $parent !== null ? 'subtask' : (in_array($type, ['story', 'task', 'bug'], true) ? $type : 'task'),
        'summary' => '', 'description' => '', 'assignee_member_id' => null, 'priority' => 'medium', 'story_points' => null, 'estimate_hours' => null,
        'epic_id' => request_integer('epic'), 'sprint_id' => request_integer('sprint'), 'component_id' => null, 'due_date' => null,
        'parent_issue_id' => $parent !== null ? (int) $parent['issue_id'] : null, 'labels' => []];
}
$options = issue_form_options($pdo, $projectId);
$settings = projects_settings($pdo);
log_screen_view($pdo, $record['id'] === null ? 'issue-add' : 'issue-edit');
if (wants_json()) {
    respond_screen(['issue' => $record === null ? null : ['id' => $record['id'], 'key' => $record['key'] ?? null, 'project_id' => $projectId], 'project' => present_project_card($project),
        'options' => ['types' => ['story', 'task', 'bug'], 'priorities' => ISSUE_PRIORITIES, 'point_scale' => $settings['point_scale']] + $options]);
}
$html = view('issues/form.php', ['record' => $record, 'project' => $project, 'parent' => $parent, 'options' => $options, 'settings' => $settings,
    'members' => member_options($pdo), 'team' => (function () use ($pdo, $projectId): array { $st = $pdo->prepare('SELECT member_id FROM mcp_project_team WHERE project_id = :p AND removed_at IS NULL'); $st->execute(['p' => $projectId]); return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN)); })(),
    'errors' => []]);
render_screen(($record['id'] === null ? 'New issue' : 'Edit ' . $record['key']) . ' · ' . $project['key'], $html,
    ['activeNav' => 'projects-list', 'screen' => $record['id'] === null ? 'issue-add' : 'issue-edit', 'entity' => 'issue', 'recordId' => $record['id'] !== null ? (string) $record['id'] : null]);
