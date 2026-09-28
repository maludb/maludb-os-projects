<?php
declare(strict_types=1);
/** Sprints, as the JSON branch shows them. Never a raw row. */

function present_sprint(array $s): array
{
    $num = static fn ($v): ?float => $v === null ? null : (float) $v;
    return [
        'id' => (int) $s['sprint_id'], 'project_id' => (int) $s['project_id'], 'project_key' => (string) $s['project_key'], 'name' => (string) $s['name'],
        'goal' => $s['goal'] ?? null, 'state' => (string) $s['state'], 'start_date' => $s['start_date'], 'end_date' => $s['end_date'], 'sort_order' => (int) $s['sort_order'],
        'committed_points' => $num($s['committed_points']), 'completed_points' => $num($s['completed_points']), 'carried_points' => $num($s['carried_points']),
        'issues' => (int) $s['issues'], 'issues_done' => (int) $s['issues_done'], 'points' => (float) $s['points'], 'points_done' => (float) $s['points_done'],
        'started_at' => json_ts($s['started_at'] ?? null), 'closed_at' => json_ts($s['closed_at'] ?? null), 'updated_at' => json_ts($s['updated_at'] ?? null),
    ];
}

function present_scope_event(array $e): array
{
    return ['id' => (int) $e['scope_event_id'], 'sprint_id' => (int) $e['sprint_id'], 'issue_id' => (int) $e['issue_id'], 'issue_key' => $e['issue_key'] ?? null,
            'issue_summary' => $e['issue_summary'] ?? null, 'event' => (string) $e['event'], 'points' => $e['points'] !== null ? (float) $e['points'] : null,
            'by' => $e['by_member_id'] !== null ? ['member_id' => (int) $e['by_member_id'], 'name' => $e['by_name'] ?? null] : null, 'occurred_at' => json_ts($e['occurred_at'] ?? null)];
}

/** Days left in a sprint, or null when it has no end. */
function sprint_days_left(?string $endDate): ?int
{
    if ($endDate === null || $endDate === '') { return null; }
    return (int) (new DateTimeImmutable('today'))->diff(new DateTimeImmutable($endDate))->format('%r%a');
}
function sprint_state_tone(string $state): string
{
    return match ($state) { 'active' => 'success', 'closed' => 'secondary', default => 'info' };
}
