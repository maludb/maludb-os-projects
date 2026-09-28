<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/projects/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/issues/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/issues/present.php';
require_once dirname(__DIR__, 2) . '/app/features/issues/respond.php';
require_post();
verify_csrf();
require_login();
$pdo = db();
$me = (int) current_member_id();
/** Action `attachment_add` (log attachment.add) — a screen's upload, people only (the manifest: who people). Gate: can_work. Size ≤ ATTACHMENT_MAX_BYTES. */
require_human();
$issue = issue_from_request($pdo);
require_can_work((int) $issue['project_id']);
require_project_active($pdo, (int) $issue['project_id']);
$max = (int) (projects_settings($pdo)['attachment_max_bytes'] ?: env('ATTACHMENT_MAX_BYTES') ?: 10485760);   // the settings row rules; the env key is the fallback (slice 6)
$file = $_FILES['file'] ?? null;
if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) { refuse(422, 'Choose a file to attach.'); }
if (($file['error'] ?? 0) === UPLOAD_ERR_INI_SIZE || ($file['error'] ?? 0) === UPLOAD_ERR_FORM_SIZE || (int) ($file['size'] ?? 0) > $max) { refuse(422, 'The file is larger than ' . round($max / 1048576, 1) . ' MB.'); }
if (($file['error'] ?? 0) !== UPLOAD_ERR_OK || !is_uploaded_file((string) $file['tmp_name'])) { refuse(422, 'The upload did not arrive whole; try again.'); }
$pdo->beginTransaction();
try {
    $row = store_attachment($pdo, (int) $issue['id'], $file, $me);
    log_activity($pdo, 'attachment.add', 'issue', (int) $issue['id'], ['after' => ['attachment_id' => (int) $row['id'], 'file_name' => $row['file_name'], 'size_bytes' => (int) $row['size_bytes'], 'mime_type' => $row['mime_type']]]);
    $pdo->commit();
} catch (RuntimeException $e) { $pdo->rollBack(); refuse(422, $e->getMessage()); }
catch (Throwable $e) { $pdo->rollBack(); error_log('upload failed: ' . $e->getMessage()); refuse(500, 'The file could not be stored.'); }
issue_action_done((string) $issue['key'], 'Attached ' . $row['file_name'] . ' to ' . $issue['key'], ['record_id' => (int) $row['id']]);
