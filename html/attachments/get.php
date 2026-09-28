<?php
declare(strict_types=1);

/**
 * GET /attachments/get.php?id= — one file, for someone who may see its issue's project. The path
 * comes from the attachments row (the base table, behind projects_can_see_project), never from the
 * request; a directory is never listed; the answer is a download with the stored name and mime.
 * Slice 1 fills the upload; this handler is the one door out.
 */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

require_login();
$pdo = db();
$id = request_integer('id');
if ($id === null) {
    refuse(404, 'No such attachment.');
}
$st = $pdo->prepare('SELECT a.id, a.file_name, a.mime_type, a.size_bytes, a.stored_path, i.project_id
                       FROM attachments a JOIN issues i ON i.id = a.issue_id
                      WHERE a.id = :id AND a.deleted_at IS NULL');
$st->execute(['id' => $id]);
$a = $st->fetch();
if ($a === false || !can_see_project((int) $a['project_id'])) {
    refuse(404, 'No such attachment.');                 // the same sentence whether it is missing or not theirs
}
$root = realpath(APP_ROOT . '/storage/attachments');
$path = realpath(APP_ROOT . '/' . ltrim((string) $a['stored_path'], '/'));
if ($root === false || $path === false || !str_starts_with($path, $root . DIRECTORY_SEPARATOR) || !is_file($path)) {
    refuse(404, 'No such attachment.');
}
log_activity($pdo, 'attachment.download', 'attachment', (int) $a['id'], ['after' => ['file_name' => $a['file_name']]]);
header('Content-Type: ' . (preg_match('#^[\w.+-]+/[\w.+-]+$#', (string) $a['mime_type']) ? $a['mime_type'] : 'application/octet-stream'));
header('Content-Length: ' . (string) filesize($path));
header('Content-Disposition: attachment; filename="' . str_replace(['"', "\r", "\n"], '', (string) $a['file_name']) . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($path);
exit;
