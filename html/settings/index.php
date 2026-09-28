<?php
declare(strict_types=1);
/** /settings/ — the business's Projects defaults, one row (screen settings). Projects admins. */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/settings/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/settings/present.php';
require_admin();
$pdo = db();
$s = find_settings($pdo);
log_screen_view($pdo, 'settings');
if (wants_json()) {
    respond_screen(['settings' => present_settings($s)]);
}
render_screen('Settings', view('settings/page.php', ['s' => $s, 'errors' => []]), ['activeNav' => 'settings', 'screen' => 'settings', 'entity' => 'projects_settings', 'recordId' => '1']);
