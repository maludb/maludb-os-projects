<?php
declare(strict_types=1);
/** The board, as the JSON branch shows it. */
require_once dirname(__DIR__) . '/issues/present.php';

function present_board(array $board): array
{
    return [
        'mode' => $board['mode'], 'lane' => $board['lane'], 'total' => (int) $board['total'],
        'sprint' => $board['sprint'] !== null ? ['id' => (int) $board['sprint']['sprint_id'], 'name' => (string) $board['sprint']['name'], 'goal' => $board['sprint']['goal'] ?? null,
            'start_date' => $board['sprint']['start_date'], 'end_date' => $board['sprint']['end_date'], 'points' => (string) $board['sprint']['points'], 'points_done' => (string) $board['sprint']['points_done']] : null,
        'lanes' => array_map(static fn (array $l): array => ['key' => $l['key'], 'label' => $l['label'], 'kind' => $l['kind'], 'count' => (int) $l['count']], $board['lanes']),
        'columns' => array_map(static fn (array $c): array => [
            'id' => (int) $c['column_id'], 'name' => (string) $c['name'], 'wip_limit' => $c['wip_limit'] !== null ? (int) $c['wip_limit'] : null, 'status_ids' => $c['status_ids'], 'count' => (int) $c['count'],
            'over_limit' => $c['wip_limit'] !== null && (int) $c['count'] > (int) $c['wip_limit'],
            'cards' => array_map(static fn (array $i): array => present_issue_row($i) + ['lane' => $i['lane'], 'can_move_to' => $i['can_move_to']], $c['cards']),
        ], $board['columns']),
    ];
}

function present_board_config(array $cfg): array
{
    return ['columns' => $cfg['columns'], 'swimlane' => $cfg['swimlane'], 'board_mode' => $cfg['board_mode'],
            'statuses' => array_map(static fn (array $s): array => ['id' => (int) $s['status_id'], 'name' => (string) $s['name'], 'category' => (string) $s['category']], $cfg['statuses'])];
}
