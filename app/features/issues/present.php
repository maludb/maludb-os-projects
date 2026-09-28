<?php
declare(strict_types=1);
/** Issues and what hangs on them, as the JSON branch shows them. Never a raw row. */

function present_issue_row(array $i): array
{
    $int = static fn ($v): ?int => $v === null ? null : (int) $v;
    return [
        'id' => (int) $i['issue_id'], 'key' => (string) $i['key'], 'project_id' => (int) $i['project_id'], 'project_key' => (string) $i['project_key'],
        'type' => (string) $i['type'], 'summary' => (string) $i['summary'], 'status_id' => (int) $i['status_id'], 'status' => (string) $i['status'],
        'status_category' => (string) $i['status_category'], 'in_review' => (bool) ($i['in_review'] ?? false), 'resolution' => $i['resolution'] ?? null,
        'priority' => (string) $i['priority'], 'story_points' => $i['story_points'] !== null ? (float) $i['story_points'] : null,
        'estimate_hours' => $i['estimate_hours'] !== null ? (float) $i['estimate_hours'] : null,
        'assignee' => $i['assignee_member_id'] !== null ? ['member_id' => (int) $i['assignee_member_id'], 'name' => $i['assignee_name'] ?? null, 'kind' => $i['assignee_kind'] ?? null] : null,
        'reporter' => $i['reporter_member_id'] !== null ? ['member_id' => (int) $i['reporter_member_id'], 'name' => $i['reporter_name'] ?? null] : null,
        'parent_issue_id' => $int($i['parent_issue_id']), 'parent_key' => $i['parent_key'] ?? null,
        'epic_id' => $int($i['epic_id']), 'epic' => $i['epic_name'] ?? null,
        'sprint_id' => $int($i['sprint_id']), 'sprint' => $i['sprint_name'] ?? null, 'sprint_state' => $i['sprint_state'] ?? null,
        'component_id' => $int($i['component_id']), 'component' => $i['component_name'] ?? null,
        'labels' => present_pg_names($i['labels'] ?? null), 'rank' => (string) $i['rank'], 'due_date' => $i['due_date'],
        'open_blockers' => (int) $i['open_blockers'], 'subtasks' => (int) $i['subtasks'], 'subtasks_done' => (int) $i['subtasks_done'],
        'comments' => (int) $i['comments'], 'hours_logged' => (float) $i['hours_logged'], 'i_watch' => (bool) $i['i_watch'],
        'started_at' => json_ts($i['started_at'] ?? null), 'resolved_at' => json_ts($i['resolved_at'] ?? null), 'accepted_at' => json_ts($i['accepted_at'] ?? null),
        'created_at' => json_ts($i['created_at'] ?? null), 'updated_at' => json_ts($i['updated_at'] ?? null),
    ];
}

function present_issue(array $i): array
{
    return present_issue_row($i) + [
        'description' => $i['description'] ?? null,
        'accepted_by' => $i['accepted_by'] !== null ? ['member_id' => (int) $i['accepted_by'], 'name' => $i['accepted_by_name'] ?? null] : null,
        'aliases' => present_pg_names($i['aliases'] ?? null),
    ];
}

function present_comment(array $c): array
{
    return ['id' => (int) $c['comment_id'], 'issue_id' => (int) $c['issue_id'], 'author' => $c['author_id'] !== null ? ['member_id' => (int) $c['author_id'], 'name' => $c['author_name'] ?? null, 'kind' => $c['author_kind'] ?? null] : null,
            'body' => $c['body'], 'deleted' => $c['deleted_at'] !== null, 'edited_at' => json_ts($c['edited_at'] ?? null), 'created_at' => json_ts($c['created_at'] ?? null)];
}
function present_link(array $l): array
{
    return ['id' => (int) $l['link_id'], 'kind' => (string) $l['kind'],
            'from' => ['issue_id' => (int) $l['from_issue_id'], 'key' => (string) $l['from_key'], 'summary' => (string) $l['from_summary'], 'done' => $l['from_status_category'] === 'done'],
            'to' => ['issue_id' => (int) $l['to_issue_id'], 'key' => (string) $l['to_key'], 'summary' => (string) $l['to_summary'], 'done' => $l['to_status_category'] === 'done']];
}
function present_checklist_item(array $c): array
{
    return ['id' => (int) $c['checklist_item_id'], 'text' => (string) $c['text'], 'is_done' => (bool) $c['is_done'], 'done_at' => json_ts($c['done_at'] ?? null)];
}
function present_worklog(array $w): array
{
    return ['id' => (int) $w['worklog_id'], 'member' => ['member_id' => (int) $w['member_id'], 'name' => (string) $w['display_name']], 'hours' => (float) $w['hours'], 'worked_on' => $w['worked_on'], 'note' => $w['note'] ?? null];
}
function present_attachment(array $a): array
{
    return ['id' => (int) $a['attachment_id'], 'file_name' => (string) $a['file_name'], 'mime_type' => (string) $a['mime_type'], 'size_bytes' => (int) $a['size_bytes'],
            'uploaded_by' => $a['uploaded_by'] !== null ? ['member_id' => (int) $a['uploaded_by'], 'name' => $a['uploaded_by_name'] ?? null] : null, 'created_at' => json_ts($a['created_at'] ?? null),
            'url' => '/attachments/get.php?id=' . (int) $a['attachment_id']];
}

/** A Postgres text[] literal as strings. */
function present_pg_names(mixed $literal): array
{
    if (!is_string($literal) || $literal === '' || $literal === '{}') {
        return [];
    }
    return array_values(array_filter(array_map(static fn (string $s): string => trim($s, '"'), str_getcsv(trim($literal, '{}'), ',', '"', '\\')), static fn (string $s): bool => $s !== ''));
}

/** The vocabulary the screens share (the spec's status vocabulary). */
function issue_status_tone(string $category): string
{
    return match ($category) { 'in_progress' => 'primary', 'done' => 'success', default => 'secondary' };
}
function issue_priority_mark(string $priority): array
{
    return match ($priority) {
        'highest' => ['↑↑', 'danger', 'Highest'], 'high' => ['↑', 'warning', 'High'], 'low' => ['↓', 'info', 'Low'], 'lowest' => ['↓↓', 'muted', 'Lowest'],
        default => ['–', 'secondary', 'Medium'],
    };
}
function issue_type_icon(string $type): string
{
    return match ($type) { 'story' => 'feather-bookmark', 'bug' => 'feather-alert-circle', 'subtask' => 'feather-corner-down-right', default => 'feather-check-square' };
}
/** An activity row about an issue, in words ("Sasha moved it to In review"). */
function issue_history_sentence(array $a): string
{
    $who = (string) ($a['actor_name'] ?? 'Someone');
    $before = is_array($a['before']) ? $a['before'] : (json_decode((string) ($a['before'] ?? ''), true) ?: []);
    $after = is_array($a['after']) ? $a['after'] : (json_decode((string) ($a['after'] ?? ''), true) ?: []);
    return match ((string) $a['action']) {
        'issue.create' => $who . ' created it' . (isset($after['type']) ? ' as a ' . $after['type'] : ''),
        'issue.transition' => $who . ' moved it to ' . ($after['status'] ?? 'another status') . (isset($before['status']) ? ' from ' . $before['status'] : ''),
        'issue.assign' => $who . (isset($after['assignee_name']) && $after['assignee_name'] !== null ? ' assigned it to ' . $after['assignee_name'] : ' unassigned it'),
        'issue.estimate' => $who . ' estimated it at ' . (isset($after['story_points']) && $after['story_points'] !== null ? $after['story_points'] . ' points' : 'no points') . (isset($before['story_points']) && $before['story_points'] !== null ? ' (was ' . $before['story_points'] . ')' : ''),
        'issue.update' => $who . ' changed ' . implode(', ', array_map(static fn ($k): string => str_replace(['_member_id', '_id', '_'], ['', '', ' '], (string) $k), array_keys($after)) ?: ['it']),
        'issue.accept' => $who . ' accepted it', 'issue.unaccept' => $who . ' withdrew the acceptance',
        'issue.watch' => $who . ' started watching', 'issue.unwatch' => $who . ' stopped watching',
        'issue.link' => $who . ' linked it: ' . ($after['from_key'] ?? '?') . ' ' . str_replace('_', ' ', (string) ($after['kind'] ?? 'relates')) . ' ' . ($after['to_key'] ?? '?'),
        'issue.unlink' => $who . ' removed a link', 'issue.rank' => $who . ' reordered it in the backlog',
        'issue.move_to_sprint' => $who . (isset($after['sprint_name']) && $after['sprint_name'] !== null ? ' moved it to ' . $after['sprint_name'] : ' moved it to the backlog'),
        'comment.add' => $who . ' commented', 'comment.edit' => $who . ' edited a comment', 'comment.delete' => $who . ' deleted a comment',
        'checklist_item.add' => $who . ' added a checklist item', 'checklist_item.toggle' => $who . (!empty($after['is_done']) ? ' ticked' : ' unticked') . ' a checklist item', 'checklist_item.delete' => $who . ' removed a checklist item',
        'worklog.add' => $who . ' logged ' . ($after['hours'] ?? '?') . ' h', 'worklog.delete' => $who . ' removed a worklog',
        'attachment.add' => $who . ' attached ' . ($after['file_name'] ?? 'a file'), 'attachment.delete' => $who . ' removed an attachment',
        default => $who . ' — ' . str_replace(['.', '_'], [' ', ' '], (string) $a['action']),
    };
}
