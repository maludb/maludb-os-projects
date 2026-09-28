<?php
declare(strict_types=1);

/** /activity — the trail the caller may see, or one record's history (params entity_type, entity_id, project = a project key). Slice 7 adds the rest of the filters. */
require_once dirname(__DIR__) . '/app/bootstrap.php';

require_login();
$pdo = db();
$entityType = request_string('entity_type');
$entityId = request_integer('entity_id');
$projectKey = strtoupper(trim(request_string('project')));
if (!preg_match('/^[a-z_]{0,40}$/', $entityType)) {
    $entityType = '';
}
if (!preg_match('/^[A-Z][A-Z0-9]{1,9}$/', $projectKey)) {
    $projectKey = '';
}
$page = max(1, (int) (request_integer('page') ?? 1));
$per = 50;
$where = [];
$args = [];
if ($entityType !== '' && $entityId !== null) {
    $where[] = 'a.entity_type = :et AND a.entity_id = :ei';
    $args['et'] = $entityType;
    $args['ei'] = $entityId;
}
if ($projectKey !== '') {
    // Everything about the project: its own rows, and its issues', sprints' and epics'.
    $where[] = "((a.entity_type = 'project' AND a.entity_id = (SELECT project_id FROM mcp_projects WHERE key = :pk))
                 OR (a.entity_type = 'issue' AND a.entity_id IN (SELECT issue_id FROM mcp_issues WHERE project_key = :pk2))
                 OR (a.entity_type = 'sprint' AND a.entity_id IN (SELECT sprint_id FROM mcp_sprints WHERE project_key = :pk3))
                 OR (a.entity_type = 'epic' AND a.entity_id IN (SELECT epic_id FROM mcp_epics WHERE project_key = :pk4)))";
    $args += ['pk' => $projectKey, 'pk2' => $projectKey, 'pk3' => $projectKey, 'pk4' => $projectKey];
}
$sql = 'SELECT a.activity_id, a.occurred_at, a.actor_name, a.actor_kind, a.source, a.action, a.screen, a.entity_type, a.entity_id, a.after, a.agent_run_id
          FROM mcp_activity_log a' . ($where !== [] ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY a.occurred_at DESC, a.activity_id DESC LIMIT :lim OFFSET :off';
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
render_screen('Activity', view('activity/page.php', ['rows' => $rows, 'page' => $page, 'more' => $more, 'entityType' => $entityType, 'entityId' => $entityId, 'projectKey' => $projectKey, 'tz' => member_timezone()]),
    ['activeNav' => 'activity', 'screen' => 'activity', 'entity' => $entityType, 'recordId' => (string) ($entityId ?? '')]);
