<?php
declare(strict_types=1);
/**
 * A project's workflow as the settings edit it (slice 6): the statuses with their issue counts, the transitions
 * as a matrix, and one save that replaces the lot inside the caller's transaction. Reads go through the mcp_*
 * views; the save writes the base tables behind project.admin (the handler's gate).
 */
const WORKFLOW_CATEGORIES = ['todo' => 'To do', 'in_progress' => 'In progress', 'done' => 'Done'];

/**
 * @return array{statuses: array, transitions: array<int, int[]>, archived: array}
 *   statuses: the live ones in order, each with `issues` (how many sit in it); transitions: from_id => [to_id…].
 */
function find_workflow(PDO $pdo, int $projectId): array
{
    $st = $pdo->prepare(<<<'SQL'
        SELECT s.status_id, s.name, s.category, s.sort_order, s.is_initial, s.is_review, s.archived_at, s.can_move_to,
               (SELECT count(*) FROM mcp_issues i WHERE i.status_id = s.status_id) AS issues
          FROM mcp_workflow_statuses s WHERE s.project_id = :p ORDER BY s.archived_at IS NOT NULL, s.sort_order, s.status_id
    SQL);
    $st->execute(['p' => $projectId]);
    $live = []; $archived = []; $transitions = [];
    foreach ($st->fetchAll() as $s) {
        $s['status_id'] = (int) $s['status_id'];
        $s['issues'] = (int) $s['issues'];
        $s['can_move_to'] = present_pg_int_array($s['can_move_to'] ?? null);
        if ($s['archived_at'] !== null) { $archived[] = $s; continue; }
        $live[] = $s;
        $transitions[$s['status_id']] = $s['can_move_to'];
    }
    return ['statuses' => $live, 'transitions' => $transitions, 'archived' => $archived];
}

/**
 * Replace the workflow (the caller opened the transaction and validated):
 *   $statuses    list, in order: ['id' => ?int, 'name', 'category', 'sort_order' => int, 'is_initial' => bool] — id null = new
 *   $transitions list of [fromIndex, toIndex] into $statuses
 *   $archive     [index => moveToIndex|null] — the statuses to archive; a status with issues needs its target
 * Renaming keeps the id. An archived status loses its transitions and its board column; its issues move to the
 * target under app.force_transition (one issue.transition row each is the handler's to log — `moved` lists them).
 * Returns ['statuses' => [index => id], 'moved' => [[issue_id, key, from_status_id, to_status_id]…], 'archived' => [names]].
 */
function save_workflow(PDO $pdo, int $projectId, array $statuses, array $transitions, array $archive, int $actorId): array
{
    $ids = [];
    $upd = $pdo->prepare('UPDATE workflow_statuses SET name = :n, category = :c, sort_order = :o, is_initial = false WHERE id = :id AND project_id = :p');
    $ins = $pdo->prepare('INSERT INTO workflow_statuses (project_id, name, category, sort_order, is_initial) VALUES (:p, :n, :c, :o, false) RETURNING id');
    // Every initial flag off first, so the one-initial index never trips mid-way.
    $pdo->prepare('UPDATE workflow_statuses SET is_initial = false WHERE project_id = :p')->execute(['p' => $projectId]);
    foreach ($statuses as $i => $s) {
        if (($s['id'] ?? null) !== null) {
            $upd->execute(['n' => $s['name'], 'c' => $s['category'], 'o' => (int) $s['sort_order'], 'id' => (int) $s['id'], 'p' => $projectId]);
            $ids[$i] = (int) $s['id'];
        } else {
            $ins->execute(['p' => $projectId, 'n' => $s['name'], 'c' => $s['category'], 'o' => (int) $s['sort_order']]);
            $ids[$i] = (int) $ins->fetchColumn();
        }
    }
    $moved = []; $archivedNames = [];
    if ($archive !== []) {
        $pdo->exec("SELECT set_config('app.force_transition', '1', true)");
        $mv = $pdo->prepare('UPDATE issues SET status_id = :to WHERE project_id = :p AND status_id = :from RETURNING id, key');
        $arc = $pdo->prepare('UPDATE workflow_statuses SET archived_at = now(), is_initial = false WHERE id = :id AND project_id = :p AND archived_at IS NULL RETURNING name');
        foreach ($archive as $i => $target) {
            $sid = $ids[$i] ?? null;
            if ($sid === null) { continue; }
            if ($target !== null && isset($ids[$target])) {
                $mv->execute(['to' => $ids[$target], 'p' => $projectId, 'from' => $sid]);
                foreach ($mv->fetchAll() as $r) { $moved[] = [(int) $r['id'], (string) $r['key'], $sid, $ids[$target]]; }
            }
            $arc->execute(['id' => $sid, 'p' => $projectId]);
            if (($n = $arc->fetchColumn()) !== false) { $archivedNames[] = (string) $n; }
            $pdo->prepare('DELETE FROM board_column_statuses WHERE status_id = :s')->execute(['s' => $sid]);
            unset($ids[$i]);
        }
    }
    foreach ($statuses as $i => $s) {
        if (!empty($s['is_initial']) && isset($ids[$i])) {
            $pdo->prepare('UPDATE workflow_statuses SET is_initial = true WHERE id = :id')->execute(['id' => $ids[$i]]);
        }
    }
    $pdo->prepare('DELETE FROM workflow_transitions WHERE project_id = :p')->execute(['p' => $projectId]);
    $tr = $pdo->prepare('INSERT INTO workflow_transitions (project_id, from_status_id, to_status_id) VALUES (:p, :f, :t) ON CONFLICT DO NOTHING');
    foreach ($transitions as [$from, $to]) {
        if (isset($ids[$from], $ids[$to]) && $ids[$from] !== $ids[$to]) {
            $tr->execute(['p' => $projectId, 'f' => $ids[$from], 't' => $ids[$to]]);
        }
    }
    return ['statuses' => $ids, 'moved' => $moved, 'archived' => $archivedNames];
}

/** The workflow as the log records it: statuses [name, category], transitions as "From>To" pairs. */
function workflow_log_shape(array $wf): array
{
    $names = [];
    foreach ($wf['statuses'] as $s) { $names[(int) $s['status_id']] = (string) $s['name']; }
    $pairs = [];
    foreach ($wf['transitions'] as $from => $tos) {
        foreach ($tos as $to) { if (isset($names[$from], $names[$to])) { $pairs[] = $names[$from] . '>' . $names[$to]; } }
    }
    sort($pairs);
    return ['statuses' => array_map(static fn (array $s): array => ['name' => $s['name'], 'category' => $s['category'], 'initial' => (bool) $s['is_initial']], $wf['statuses']), 'transitions' => $pairs];
}
