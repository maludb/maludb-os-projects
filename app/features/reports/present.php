<?php
declare(strict_types=1);
/** Reports, as the JSON branch answers them. Numbers as numbers, dates as strings. */

function present_report(string $report, array $data): array
{
    $num = static fn ($v) => $v === null ? null : (float) $v;
    return match ($report) {
        'velocity' => ['sprints' => array_map(static fn (array $r): array => ['sprint_id' => (int) $r['sprint_id'], 'name' => (string) $r['name'], 'start_date' => $r['start_date'], 'end_date' => $r['end_date'],
            'committed' => $num($r['committed']), 'completed' => $num($r['completed']), 'carried' => $num($r['carried'])], $data['rows']), 'average_3' => $data['average'] ?? null],
        'burndown' => ['sprint' => $data['sprint'] === null ? null : ['sprint_id' => (int) $data['sprint']['sprint_id'], 'name' => (string) $data['sprint']['name'], 'state' => (string) $data['sprint']['state'],
            'start_date' => $data['sprint']['start_date'], 'end_date' => $data['sprint']['end_date'], 'committed' => $num($data['sprint']['committed_points'])],
            'days' => array_map(static fn (array $d): array => ['day' => $d['day'], 'scope' => $num($d['scope']), 'done' => $num($d['done']), 'remaining' => $num($d['remaining'])], $data['days'])],
        'cycle' => ['since_days' => (int) $data['days'], 'types' => array_map(static fn (array $r): array => ['type' => (string) $r['type'], 'resolved' => (int) $r['resolved'],
            'median_days' => $num($r['median_days']), 'p85_days' => $num($r['p85_days']), 'mean_days' => $num($r['mean_days'])], $data['rows'])],
        'flow' => ['since_days' => (int) $data['days'], 'days' => array_map(static fn (array $r): array => ['day' => $r['day'], 'todo' => (int) $r['todo'], 'in_progress' => (int) $r['in_progress'], 'done' => (int) $r['done']], $data['rows'])],
        'scope' => ['sprint_id' => $data['sprint_id'], 'events' => array_map(static fn (array $r): array => ['id' => (int) $r['scope_event_id'], 'event' => (string) $r['event'], 'issue_id' => (int) $r['issue_id'],
            'issue_key' => $r['issue_key'], 'points' => $num($r['points']), 'by' => $r['by_name'], 'occurred_at' => json_ts($r['occurred_at'] ?? null)], $data['rows']), 'totals' => $data['totals']],
        'hygiene' => ['issues' => array_map(static fn (array $r): array => ['issue_id' => (int) $r['issue_id'], 'key' => (string) $r['key'], 'summary' => (string) $r['summary'], 'reason' => (string) $r['reason'],
            'days' => $r['days'] !== null ? (int) $r['days'] : null], $data['rows'])],
        default => [],
    };
}
