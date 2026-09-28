<?php
declare(strict_types=1);
/** /settings/tokens — the caller's own access tokens for the two MCP servers, and the URLs to connect their AI to (screen tokens). People only. */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/settings/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/settings/present.php';
require_login();
require_human();
$pdo = db();
$me = (int) current_member_id();
$rows = find_my_tokens($pdo, $me);
log_screen_view($pdo, 'tokens');
if (wants_json()) {
    respond_screen(['tokens' => array_map('present_token', $rows)]);
}
$raw = $_SESSION['minted_token'] ?? null;
unset($_SESSION['minted_token']);
render_screen('Tokens', view('settings/tokens.php', ['rows' => $rows, 'raw' => $raw, 'tz' => member_timezone()]), ['activeNav' => 'tokens', 'screen' => 'tokens', 'entity' => 'mcp_access_token']);
