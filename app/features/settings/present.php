<?php
declare(strict_types=1);
/** Slice 6's JSON shapes: the settings row, a token (never its value or hash), a workflow, a team member. */

function present_settings(array $s): array
{
    return [
        'timezone' => (string) $s['timezone'], 'sprint_length_days' => (int) $s['sprint_length_days'],
        'point_scale' => array_map('intval', is_array($s['point_scale']) ? $s['point_scale'] : []),
        'stale_after_days' => (int) $s['stale_after_days'], 'attachment_max_bytes' => (int) $s['attachment_max_bytes'],
        'default_visibility' => (string) $s['default_visibility'], 'updated_at' => json_ts($s['updated_at'] ?? null),
    ];
}

function present_token(array $t): array
{
    return [
        'id' => (int) $t['id'], 'label' => (string) $t['label'], 'scope' => (string) $t['scope'],
        'created_at' => json_ts($t['created_at'] ?? null), 'last_used_at' => json_ts($t['last_used_at'] ?? null),
        'revoked_at' => json_ts($t['revoked_at'] ?? null), 'expires_at' => json_ts($t['expires_at'] ?? null), 'live' => ($t['revoked_at'] ?? null) === null,
    ];
}

function present_workflow(array $wf): array
{
    $status = static fn (array $s): array => ['id' => (int) $s['status_id'], 'name' => (string) $s['name'], 'category' => (string) $s['category'],
        'sort_order' => (int) $s['sort_order'], 'is_initial' => (bool) $s['is_initial'], 'is_review' => (bool) $s['is_review'], 'issues' => (int) ($s['issues'] ?? 0),
        'can_move_to' => array_map('intval', $s['can_move_to'] ?? [])];
    return ['statuses' => array_map($status, $wf['statuses']), 'archived' => array_map($status, $wf['archived']),
            'transitions' => array_map(static fn (int $from, array $tos): array => ['from' => $from, 'to' => array_map('intval', $tos)], array_keys($wf['transitions']), array_values($wf['transitions']))];
}

function present_team_member(array $t): array
{
    return ['member_id' => (int) $t['member_id'], 'name' => (string) $t['display_name'], 'kind' => (string) $t['member_kind'], 'is_external' => (bool) $t['is_external'],
            'job_title' => $t['job_title'] ?? null, 'role' => (string) $t['project_role'], 'since' => json_ts($t['added_at'] ?? null)];
}
