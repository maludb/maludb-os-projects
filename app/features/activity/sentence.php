<?php
declare(strict_types=1);
/** An activity row in words — issue rows through the exemplar's issue_history_sentence(), the rest here. */
require_once dirname(__DIR__) . '/issues/present.php';

function activity_sentence(array $r): string
{
    $who = (string) ($r['actor_name'] ?? 'Someone');
    $before = is_array($r['before'] ?? null) ? $r['before'] : (json_decode((string) ($r['before'] ?? ''), true) ?: []);
    $after = is_array($r['after'] ?? null) ? $r['after'] : (json_decode((string) ($r['after'] ?? ''), true) ?: []);
    $action = (string) $r['action'];
    $issue = !empty($r['issue_key']) ? $r['issue_key'] : (isset($after['key']) ? (string) $after['key'] : (isset($after['issue_key']) ? (string) $after['issue_key'] : (isset($before['key']) ? (string) $before['key'] : null)));
    if ((str_starts_with($action, 'issue.') && !in_array($action, ['issue.delete', 'issue.move_project'], true)) || str_starts_with($action, 'comment.') || str_starts_with($action, 'checklist_item.') || str_starts_with($action, 'worklog.') || str_starts_with($action, 'attachment.')) {
        $s = issue_history_sentence($r);
        return $issue !== null ? preg_replace('/\bit\b/', $issue, $s, 1) ?? $s : $s;
    }
    $pts = static fn ($v): string => $v === null ? '' : rtrim(rtrim(number_format((float) $v, 1, '.', ''), '0'), '.') . ' points';
    return match ($action) {
        'project.create' => $who . ' created project ' . ($after['key'] ?? '') . (isset($after['name']) ? ' ' . $after['name'] : ''),
        'project.update' => $who . ' changed the project: ' . implode(', ', array_map(static fn ($k): string => str_replace('_', ' ', (string) $k), array_keys($after)) ?: ['something']),
        'project.archive' => $who . ' archived the project', 'project.unarchive' => $who . ' brought the project back',
        'team.add' => $who . ' added ' . ($after['name'] ?? 'someone') . ' to the team' . (isset($after['project_role']) ? ' as ' . str_replace('_', ' ', (string) $after['project_role']) : ''),
        'team.remove' => $who . ' removed ' . ($before['name'] ?? 'someone') . ' from the team',
        'sprint.create' => $who . ' created sprint ' . ($after['name'] ?? ''),
        'sprint.update' => $who . ' changed sprint ' . ($after['name'] ?? $before['name'] ?? ''),
        'sprint.start' => $who . ' started ' . ($after['name'] ?? 'the sprint') . (isset($after['committed_points']) ? ' with ' . $pts($after['committed_points']) : ''),
        'sprint.close' => $who . ' closed ' . ($after['name'] ?? 'the sprint') . (isset($after['completed_points']) ? ': ' . $pts($after['completed_points']) . ' done' : '') . (!empty($after['carried_issues']) ? ', ' . $after['carried_issues'] . ' carried over' : ''),
        'sprint.delete' => $who . ' deleted sprint ' . ($before['name'] ?? ''),
        'epic.create' => $who . ' created epic ' . ($after['name'] ?? ''), 'epic.update' => $who . ' changed epic ' . ($after['name'] ?? $before['name'] ?? ''),
        'epic.status' => $who . ' marked epic ' . ($after['name'] ?? $before['name'] ?? '#' . (int) $r['entity_id']) . ' ' . str_replace('_', ' ', (string) ($after['status'] ?? 'open')),
        'issue.delete' => $who . ' deleted ' . ($before['key'] ?? 'an issue'), 'issue.move_project' => $who . ' moved ' . ($before['key'] ?? 'an issue') . ' to project ' . ($after['project_key'] ?? ($after['key'] ?? '')),
        'attachment.download' => $who . ' downloaded ' . ($after['file_name'] ?? 'an attachment'), 'report.export' => $who . ' exported a report',
        'assistant.reply' => 'The assistant answered ' . $who, 'member.sign_out.refused' => 'A sign-out notice was refused',
        'epic.close' => $who . ' closed epic ' . ($after['name'] ?? $before['name'] ?? ''), 'epic.reopen' => $who . ' reopened epic ' . ($after['name'] ?? ''),
        'label.save' => $who . ' saved label ' . ($after['name'] ?? ''), 'label.archive' => $who . ' archived label ' . ($before['name'] ?? $after['name'] ?? ''),
        'component.save' => $who . ' saved component ' . ($after['name'] ?? ''), 'component.archive' => $who . ' archived component ' . ($before['name'] ?? $after['name'] ?? ''),
        'board.save' => $who . ' changed the board', 'workflow.save' => $who . ' changed the workflow',
        'saved_filter.save' => $who . ' saved the filter ' . ($after['name'] ?? ''), 'saved_filter.delete' => $who . ' deleted the filter ' . ($before['name'] ?? ''),
        'activity.export' => $who . ' exported the trail',
        'member.sign_on' => $who . ' signed in', 'member.sign_out' => $who . ' signed out', 'member.sign_on.refused' => 'A sign-on was refused',
        'directory.sync' => 'The directory was synced', 'settings.save' => $who . ' changed the settings', 'assistant.message' => $who . ' asked the assistant',
        default => $who . ' — ' . str_replace(['.', '_'], [' ', ' '], $action),
    };
}
