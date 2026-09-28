<?php /** Region #issue-attachments. Data: issue, attachments, canWork, canAdmin, me, tz, settings */
$key = (string) $issue['key']; $id = (int) $issue['issue_id'];
$size = static fn (int $b): string => $b >= 1048576 ? round($b / 1048576, 1) . ' MB' : ($b >= 1024 ? round($b / 1024) . ' KB' : $b . ' B'); ?>
<div class="card" id="issue-attachments" hx-get="/issues/view.php?key=<?= e($key) ?>&region=attachments" hx-trigger="issueChanged from:body" hx-swap="outerHTML">
    <div class="card-header"><h5 class="card-title mb-0">Attachments <span class="text-muted fs-12 fw-normal">(<?= count($attachments) ?>)</span></h5></div>
    <div class="card-body">
        <?php if ($attachments !== []): ?>
        <ul class="list-unstyled mb-3" id="issue-attachments-list">
            <?php foreach ($attachments as $a): ?>
            <li class="d-flex align-items-center gap-2 py-1" id="issue-attachment-row-<?= (int) $a['attachment_id'] ?>">
                <i class="feather-paperclip text-muted"></i><a href="/attachments/get.php?id=<?= (int) $a['attachment_id'] ?>" class="text-truncate"><?= e($a['file_name']) ?></a>
                <span class="text-muted fs-12 text-nowrap"><?= e($size((int) $a['size_bytes'])) ?> · <?= e($a['uploaded_by_name'] ?? '?') ?> · <?= e(format_ts($a['created_at'], $tz, 'M j')) ?></span>
                <?php if ($canAdmin || (int) ($a['uploaded_by'] ?? 0) === $me): ?><form hx-post="/attachments/delete.php" hx-swap="none" hx-confirm="Remove <?= e($a['file_name']) ?>?" class="ms-auto"><?= csrf_field() ?><input type="hidden" name="attachment" value="<?= (int) $a['attachment_id'] ?>"><button type="submit" class="btn btn-sm btn-light text-danger" aria-label="Remove attachment" id="issue-attachment-delete-<?= (int) $a['attachment_id'] ?>">✕</button></form><?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php else: ?><p class="text-muted fs-12 mb-2">No attachments.</p><?php endif; ?>
        <?php if ($canWork): ?>
        <form method="post" action="/attachments/upload.php" enctype="multipart/form-data" hx-post="/attachments/upload.php" hx-encoding="multipart/form-data" hx-swap="none" class="d-flex gap-2 align-items-center" id="issue-attachment-upload-form"><?= csrf_field() ?><input type="hidden" name="issue" value="<?= $id ?>">
            <input type="file" name="file" id="issue-attachment-file" class="form-control form-control-sm" required aria-label="File">
            <button type="submit" class="btn btn-sm btn-light-brand text-nowrap" id="issue-attachment-upload-btn"><i class="feather-upload me-1"></i>Attach</button>
        </form>
        <div class="form-text">Up to <?= e($size((int) (env('ATTACHMENT_MAX_BYTES') ?: $settings['attachment_max_bytes']))) ?>.</div>
        <?php endif; ?>
    </div>
</div>
