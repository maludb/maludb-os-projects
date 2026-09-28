<?php
declare(strict_types=1);
/** Action `settings_save` (log `settings.save`, before/after). Projects admins. The UI posts the attachment limit in MB; the manifest in bytes. */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/settings/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/settings/present.php';
require_post();
verify_csrf();
require_admin();
$pdo = db();
$before = find_settings($pdo);
$f = [];
$f['timezone'] = request_string('timezone', (string) $before['timezone']);
$f['sprint_length_days'] = request_integer('sprint_length_days') ?? (int) $before['sprint_length_days'];
$f['stale_after_days'] = request_integer('stale_after_days') ?? (int) $before['stale_after_days'];
$f['default_visibility'] = request_string('default_visibility', (string) $before['default_visibility']);
$scaleRaw = request_string('point_scale');
$scaleParts = $scaleRaw === '' ? $before['point_scale'] : array_map('trim', explode(',', $scaleRaw));
$mb = request_string('attachment_max_mb');
$f['attachment_max_bytes'] = $mb !== '' ? (int) round((float) $mb * 1048576) : (request_integer('attachment_max_bytes') ?? (int) $before['attachment_max_bytes']);
$errors = [];
if (!in_array($f['timezone'], timezone_identifiers_list(), true)) { $errors[] = 'That is not a time zone.'; }
if ($f['sprint_length_days'] < 1 || $f['sprint_length_days'] > 60) { $errors[] = 'A sprint is 1 to 60 days.'; }
if ($f['stale_after_days'] < 1 || $f['stale_after_days'] > 90) { $errors[] = 'Stale after 1 to 90 days.'; }
if (!in_array($f['default_visibility'], SETTINGS_VISIBILITIES, true)) { $errors[] = 'The default visibility is open or private.'; }
if ($f['attachment_max_bytes'] < 1) { $errors[] = 'The attachment limit is above zero.'; }
$scale = [];
foreach ($scaleParts as $part) {
    if ($part === '' || !ctype_digit((string) $part) || (int) $part < 1 || (int) $part > 32767) { $errors[] = 'The point scale is positive whole numbers, comma-separated (1, 2, 3, 5, 8).'; $scale = []; break; }
    $scale[] = (int) $part;
}
if ($scale !== [] && ($scale !== array_values(array_unique($scale)) || $scale !== (static function (array $a): array { sort($a); return $a; })($scale))) { $errors[] = 'The point scale climbs: each number larger than the one before.'; }
if ($scale === [] && $errors === []) { $errors[] = 'The point scale needs at least one number.'; }
$f['point_scale'] = $scale;
if ($errors !== []) {
    emit_action_status(false, ['errors' => $errors]);
    if (wants_json()) { respond_invalid($errors); }
    http_response_code(422);
    header('HX-Retarget: #page-content');
    header('HX-Reswap: innerHTML');
    echo view('settings/page.php', ['s' => $f + $before, 'errors' => $errors]);
    exit;
}
$after = save_settings($pdo, $f, (int) current_member_id());
$keys = ['timezone', 'sprint_length_days', 'point_scale', 'stale_after_days', 'attachment_max_bytes', 'default_visibility'];
$b = []; $a = [];
foreach ($keys as $k) { if ($before[$k] != $after[$k]) { $b[$k] = $before[$k]; $a[$k] = $after[$k]; } }
log_activity($pdo, 'settings.save', 'projects_settings', 1, ['before' => $b, 'after' => $a]);
emit_action_status(true, ['did' => $a === [] ? 'The Projects settings are unchanged' : 'Saved the Projects settings (' . implode(', ', array_keys($a)) . ')', 'record_id' => 1, 'refresh' => 'settingsChanged']);
saved_go('/settings/', 'settingsChanged');
