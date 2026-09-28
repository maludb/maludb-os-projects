<?php
declare(strict_types=1);
/** Saved filters — a named query a member keeps, private or shared with a project (db/009). Reads through mcp_saved_filters. */
const FILTER_KEYS_ALLOWED = ['q', 'type', 'status', 'category', 'assignee', 'reporter', 'sprint', 'epic', 'label', 'component', 'priority', 'due', 'blocked', 'updated_since', 'include_subtasks', 'sort'];

/** Mine, and the shared ones I may see (the view decides); $projectId narrows to one project plus the cross-project ones. */
function find_saved_filters(PDO $pdo, int $memberId, ?int $projectId): array
{
    $sql = 'SELECT f.*, p.key AS project_key, p.name AS project_name FROM mcp_saved_filters f LEFT JOIN mcp_projects p ON p.project_id = f.project_id';
    $args = [];
    if ($projectId !== null) {
        $sql .= ' WHERE f.project_id = :p OR f.project_id IS NULL';
        $args['p'] = $projectId;
    }
    $st = $pdo->prepare($sql . ' ORDER BY (f.owner_id = :me) DESC, f.project_id NULLS FIRST, f.name');
    $args['me'] = $memberId;
    $st->execute($args);
    return array_map('saved_filter_decode', $st->fetchAll());
}
function find_saved_filter(PDO $pdo, int $id): ?array
{
    $st = $pdo->prepare('SELECT f.*, p.key AS project_key, p.name AS project_name FROM mcp_saved_filters f LEFT JOIN mcp_projects p ON p.project_id = f.project_id WHERE f.saved_filter_id = :id');
    $st->execute(['id' => $id]);
    $row = $st->fetch();
    return $row === false ? null : saved_filter_decode($row);
}
function saved_filter_decode(array $row): array
{
    $f = is_array($row['filter']) ? $row['filter'] : (json_decode((string) $row['filter'], true) ?: []);
    $row['filter'] = array_intersect_key($f, array_flip(FILTER_KEYS_ALLOWED));   // an unknown key is ignored
    return $row;
}
/** Only the issues list's own keys are kept. */
function saved_filter_clean(array $filter): array
{
    return array_filter(array_intersect_key($filter, array_flip(FILTER_KEYS_ALLOWED)), static fn ($v) => $v !== null && $v !== '' && $v !== []);
}
function save_saved_filter(PDO $pdo, ?int $id, int $ownerId, ?int $projectId, string $name, array $filter, bool $shared): array
{
    $filter = saved_filter_clean($filter);
    if ($id === null) {
        $st = $pdo->prepare('INSERT INTO saved_filters (owner_id, project_id, name, filter, is_shared) VALUES (:o, :p, :n, :f, :s)
                             ON CONFLICT (owner_id, project_id, name) DO UPDATE SET filter = EXCLUDED.filter, is_shared = EXCLUDED.is_shared RETURNING *');
        $st->execute(['o' => $ownerId, 'p' => $projectId, 'n' => $name, 'f' => json_encode($filter, JSON_UNESCAPED_SLASHES), 's' => $shared ? 't' : 'f']);
        return $st->fetch();
    }
    $st = $pdo->prepare('UPDATE saved_filters SET project_id = :p, name = :n, filter = :f, is_shared = :s WHERE id = :id AND owner_id = :o RETURNING *');
    $st->execute(['p' => $projectId, 'n' => $name, 'f' => json_encode($filter, JSON_UNESCAPED_SLASHES), 's' => $shared ? 't' : 'f', 'id' => $id, 'o' => $ownerId]);
    $row = $st->fetch();
    if ($row === false) {
        throw new RuntimeException('No such filter of yours.');
    }
    return $row;
}
function delete_saved_filter(PDO $pdo, int $id, int $ownerId): bool
{
    $st = $pdo->prepare('DELETE FROM saved_filters WHERE id = :id AND owner_id = :o');
    $st->execute(['id' => $id, 'o' => $ownerId]);
    return $st->rowCount() > 0;
}
/** Where a saved filter applies: its project's issues list; a project-less one is picked from any list, so no single place. */
function saved_filter_href(array $f): ?string
{
    return $f['project_id'] !== null && !empty($f['project_key']) ? '/projects/' . $f['project_key'] . '/issues?filter=' . (int) $f['saved_filter_id'] : null;
}
