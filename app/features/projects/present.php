<?php
declare(strict_types=1);
/** Projects, as the JSON branch and the cards show them. Never a raw row. */

function present_project_card(array $p): array
{
    return [
        'id' => (int) $p['project_id'], 'key' => (string) $p['key'], 'name' => (string) $p['name'],
        'description' => $p['description'] ?? null, 'department_id' => $p['department_id'] !== null ? (int) $p['department_id'] : null,
        'department' => $p['department_name'] ?? null, 'lead_member_id' => $p['lead_member_id'] !== null ? (int) $p['lead_member_id'] : null,
        'lead' => $p['lead_name'] ?? null, 'visibility' => (string) $p['visibility'], 'status' => (string) $p['status'],
        'board_mode' => (string) $p['board_mode'], 'team_size' => (int) $p['team_size'], 'open_issues' => (int) $p['open_issues'],
        'active_sprint_id' => $p['active_sprint_id'] !== null ? (int) $p['active_sprint_id'] : null, 'active_sprint' => $p['active_sprint_name'] ?? null,
        'open_epics' => (int) $p['open_epics'], 'i_can_work' => (bool) $p['i_can_work'], 'i_administer' => (bool) $p['i_administer'],
        'updated_at' => json_ts($p['updated_at'] ?? null),
    ];
}

function present_project(array $p): array
{
    return present_project_card($p) + [
        'customer' => $p['customer'] ?? null,
        'team' => array_map(static fn (array $t): array => ['member_id' => (int) $t['member_id'], 'name' => (string) $t['display_name'],
            'kind' => (string) $t['member_kind'], 'is_external' => (bool) $t['is_external'], 'role' => (string) $t['project_role']], $p['team'] ?? []),
        'statuses' => array_map(static fn (array $s): array => ['id' => (int) $s['status_id'], 'name' => (string) $s['name'], 'category' => (string) $s['category'],
            'is_initial' => (bool) $s['is_initial'], 'is_review' => (bool) $s['is_review'], 'can_move_to' => present_pg_int_array($s['can_move_to'] ?? null)], $p['statuses'] ?? []),
        'columns' => array_map(static fn (array $c): array => ['id' => (int) $c['column_id'], 'name' => (string) $c['name'], 'wip_limit' => $c['wip_limit'] !== null ? (int) $c['wip_limit'] : null,
            'status_ids' => present_pg_int_array($c['status_ids'] ?? null)], $p['columns'] ?? []),
        'active_sprint_detail' => isset($p['active_sprint']) && $p['active_sprint'] !== null ? [
            'id' => (int) $p['active_sprint']['sprint_id'], 'name' => (string) $p['active_sprint']['name'], 'goal' => $p['active_sprint']['goal'] ?? null,
            'start_date' => $p['active_sprint']['start_date'], 'end_date' => $p['active_sprint']['end_date'],
            'points' => (string) $p['active_sprint']['points'], 'points_done' => (string) $p['active_sprint']['points_done'],
        ] : null,
        'epics' => array_map(static fn (array $e): array => ['id' => (int) $e['epic_id'], 'name' => (string) $e['name'], 'colour' => (string) $e['colour'],
            'issues' => (int) $e['issues'], 'issues_done' => (int) $e['issues_done'], 'points' => (string) $e['points'], 'points_done' => (string) $e['points_done']], $p['epics'] ?? []),
    ];
}

/** A Postgres int[] literal ("{1,2}") as ints. */
function present_pg_int_array(mixed $literal): array
{
    if (!is_string($literal) || $literal === '' || $literal === '{}') {
        return [];
    }
    return array_map('intval', array_filter(explode(',', trim($literal, '{}')), 'strlen'));
}

function initials(string $name): string
{
    $words = preg_split('/\s+/', trim($name)) ?: [];
    return strtoupper(implode('', array_map(static fn (string $w): string => mb_substr($w, 0, 1), array_slice($words, 0, 2)))) ?: '?';
}
