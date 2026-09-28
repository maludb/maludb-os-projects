<?php
declare(strict_types=1);
/** Action `token_mint` (log `mcp_token.mint`: label and scope, never the token): a personal token, shown once on the next screen. */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/settings/queries.php';
require_post();
verify_csrf();
require_login();
require_human();
$pdo = db();
$me = (int) current_member_id();
$label = request_string('label');
$scope = request_string('scope', 'mcp') ?: 'mcp';
if ($label === '' || mb_strlen($label) > 80) {
    refuse(422, 'A label is required (up to 80 characters) — what will use this token.');
}
if (!isset(TOKEN_SCOPES[$scope])) {
    refuse(422, 'The scope is mcp or api.');
}
if ($scope === 'api' && !is_projects_admin()) {
    refuse(403, 'An api token is a Projects admin\'s.');
}
$minted = mint_token($pdo, $me, $label, $scope);
log_activity($pdo, 'mcp_token.mint', 'mcp_access_token', $minted['id'], ['after' => ['label' => $label, 'scope' => $scope]]);
emit_action_status(true, ['did' => 'Minted the ' . $scope . ' token "' . $label . '" — shown once on the Tokens screen', 'record_id' => $minted['id'], 'refresh' => 'tokenChanged']);
if (wants_json()) {
    respond_saved(['id' => $minted['id'], 'token' => $minted['raw'], 'location' => '/settings/tokens']);   // the value, once
}
$_SESSION['minted_token'] = $minted;
saved_go('/settings/tokens', 'tokenChanged');
