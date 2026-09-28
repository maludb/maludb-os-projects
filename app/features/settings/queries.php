<?php
declare(strict_types=1);
/**
 * Projects' one settings row and the caller's own access tokens (slice 6). The settings row is read from the base
 * table behind require_admin(); the tokens table holds only hashes — a raw token exists once, in the mint's answer.
 */
const SETTINGS_VISIBILITIES = ['open', 'private'];
const TOKEN_SCOPES = ['mcp' => 'mcp', 'api' => 'api'];

/** The row, point_scale as ints. */
function find_settings(PDO $pdo): array
{
    $row = $pdo->query('SELECT * FROM projects_settings WHERE id = 1')->fetch();
    if ($row === false) {
        $pdo->exec('INSERT INTO projects_settings (id) VALUES (1) ON CONFLICT DO NOTHING');
        $row = $pdo->query('SELECT * FROM projects_settings WHERE id = 1')->fetch();
    }
    $row['point_scale'] = array_map('intval', array_filter(explode(',', trim((string) $row['point_scale'], '{}')), 'strlen'));
    return $row;
}

/** $fields: timezone, sprint_length_days, point_scale (int[]), stale_after_days, attachment_max_bytes, default_visibility. Returns the row after. */
function save_settings(PDO $pdo, array $fields, int $actorId): array
{
    $st = $pdo->prepare('UPDATE projects_settings SET timezone = :tz, sprint_length_days = :len, point_scale = :scale, stale_after_days = :stale,
                            attachment_max_bytes = :max, default_visibility = :vis, updated_by = :by WHERE id = 1');
    $st->execute(['tz' => $fields['timezone'], 'len' => (int) $fields['sprint_length_days'], 'scale' => '{' . implode(',', array_map('intval', $fields['point_scale'])) . '}',
                  'stale' => (int) $fields['stale_after_days'], 'max' => (int) $fields['attachment_max_bytes'], 'vis' => $fields['default_visibility'], 'by' => $actorId]);
    return find_settings($pdo);
}

/** The member's tokens, live first, newest first — never a hash. */
function find_my_tokens(PDO $pdo, int $memberId): array
{
    $st = $pdo->prepare('SELECT id, label, scope, created_at, last_used_at, revoked_at, expires_at FROM mcp_access_tokens WHERE member_id = :m ORDER BY revoked_at NULLS FIRST, created_at DESC');
    $st->execute(['m' => $memberId]);
    return $st->fetchAll();
}

/** Mint a token: `mcp_` + 48 hex, stored hashed. Returns ['id', 'raw', 'label', 'scope'] — the raw value's only appearance. */
function mint_token(PDO $pdo, int $memberId, string $label, string $scope): array
{
    $raw = 'mcp_' . bin2hex(random_bytes(24));
    $st = $pdo->prepare('INSERT INTO mcp_access_tokens (member_id, label, token_hash, scope) VALUES (:m, :l, :h, :s) RETURNING id');
    $st->execute(['m' => $memberId, 'l' => $label, 'h' => hash('sha256', $raw), 's' => $scope]);
    return ['id' => (int) $st->fetchColumn(), 'raw' => $raw, 'label' => $label, 'scope' => $scope];
}

/** Revoke one of the member's own live tokens. False when there is none such. */
function revoke_token(PDO $pdo, int $id, int $memberId): bool
{
    $st = $pdo->prepare('UPDATE mcp_access_tokens SET revoked_at = now() WHERE id = :id AND member_id = :m AND revoked_at IS NULL RETURNING id');
    $st->execute(['id' => $id, 'm' => $memberId]);
    return $st->fetchColumn() !== false;
}

/** The label and scope of one of the member's tokens, for the log. */
function find_my_token(PDO $pdo, int $id, int $memberId): ?array
{
    $st = $pdo->prepare('SELECT id, label, scope, revoked_at FROM mcp_access_tokens WHERE id = :id AND member_id = :m');
    $st->execute(['id' => $id, 'm' => $memberId]);
    return $st->fetch() ?: null;
}
