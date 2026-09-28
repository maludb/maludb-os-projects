<?php
declare(strict_types=1);
/** Epics, labels and components as the JSON branch shows them. Never a raw row. */

function present_epic(array $e): array
{
    return [
        'id' => (int) $e['epic_id'], 'project_id' => (int) $e['project_id'], 'project_key' => (string) $e['project_key'], 'name' => (string) $e['name'],
        'summary' => $e['summary'] ?? null, 'colour' => (string) $e['colour'], 'status' => (string) $e['status'], 'target_date' => $e['target_date'],
        'sort_order' => (int) $e['sort_order'], 'issues' => (int) $e['issues'], 'issues_done' => (int) $e['issues_done'],
        'points' => (float) $e['points'], 'points_done' => (float) $e['points_done'], 'progress' => epic_progress_pct($e),
        'created_at' => json_ts($e['created_at'] ?? null), 'updated_at' => json_ts($e['updated_at'] ?? null),
    ];
}
/** Points done over points when estimated, else issues done over issues; 0–100. */
function epic_progress_pct(array $e): int
{
    if ((float) $e['points'] > 0) {
        return (int) round(100 * (float) $e['points_done'] / (float) $e['points']);
    }
    return (int) $e['issues'] > 0 ? (int) round(100 * (int) $e['issues_done'] / (int) $e['issues']) : 0;
}
function present_label(array $l): array
{
    return ['id' => (int) $l['label_id'], 'project_id' => (int) $l['project_id'], 'name' => (string) $l['name'], 'colour' => (string) $l['colour'],
            'issues' => (int) $l['issues'], 'archived' => $l['archived_at'] !== null];
}
function present_component(array $c): array
{
    return ['id' => (int) $c['component_id'], 'project_id' => (int) $c['project_id'], 'name' => (string) $c['name'], 'description' => $c['description'] ?? null,
            'default_assignee' => $c['default_assignee_id'] !== null ? ['member_id' => (int) $c['default_assignee_id'], 'name' => $c['default_assignee_name'] ?? null] : null,
            'archived' => $c['archived_at'] !== null];
}
