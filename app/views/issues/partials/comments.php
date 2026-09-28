<?php /** Region #issue-comments. Data: issue, comments, canComment, canAdmin, me, tz */
$key = (string) $issue['key']; $id = (int) $issue['issue_id']; ?>
<div class="card" id="issue-comments" hx-get="/issues/view.php?key=<?= e($key) ?>&region=comments" hx-trigger="issueChanged from:body" hx-swap="outerHTML">
    <div class="card-header"><h5 class="card-title mb-0">Comments <span class="text-muted fs-12 fw-normal">(<?= count(array_filter($comments, static fn (array $c): bool => $c['deleted_at'] === null)) ?>)</span></h5></div>
    <div class="card-body">
        <?php foreach ($comments as $c): $cid = (int) $c['comment_id']; ?>
        <div class="border-bottom pb-2 mb-2" id="issue-comment-<?= $cid ?>">
            <div class="d-flex align-items-center gap-2 fs-12 text-muted">
                <span class="fw-semibold text-dark"><?= e($c['author_name'] ?? 'Someone') ?></span><?= ($c['author_kind'] ?? '') === 'agent' ? '<span class="badge bg-soft-info text-info">agent</span>' : '' ?>
                <span><?= e(format_ts($c['created_at'], $tz)) ?></span><?= $c['edited_at'] !== null ? '<span>(edited)</span>' : '' ?>
                <?php if ($c['deleted_at'] === null && ((int) ($c['author_id'] ?? 0) === $me || $canAdmin)): ?>
                <span class="ms-auto d-flex gap-1">
                    <?php if ((int) ($c['author_id'] ?? 0) === $me): ?><button type="button" class="btn btn-sm btn-light" id="issue-comment-edit-<?= $cid ?>" onclick="document.getElementById('issue-comment-edit-form-<?= $cid ?>').classList.toggle('d-none')"><i class="feather-edit-2"></i></button><?php endif; ?>
                    <form hx-post="/issues/comments/delete.php" hx-swap="none" hx-confirm="Delete this comment?"><?= csrf_field() ?><input type="hidden" name="comment" value="<?= $cid ?>"><button type="submit" class="btn btn-sm btn-light text-danger" id="issue-comment-delete-<?= $cid ?>"><i class="feather-trash-2"></i></button></form>
                </span>
                <?php endif; ?>
            </div>
            <?php if ($c['deleted_at'] !== null): ?><div class="text-muted fst-italic fs-12">(deleted)</div>
            <?php else: ?>
            <div class="issue-comment-body"><?= markdown_to_html((string) $c['body']) ?></div>
            <?php if ((int) ($c['author_id'] ?? 0) === $me): ?>
            <form hx-post="/issues/comments/save.php" hx-swap="none" class="d-none mt-2" id="issue-comment-edit-form-<?= $cid ?>"><?= csrf_field() ?><input type="hidden" name="comment" value="<?= $cid ?>">
                <textarea name="body" class="form-control form-control-sm" rows="3" maxlength="20000" required><?= e($c['body']) ?></textarea>
                <button type="submit" class="btn btn-sm btn-light-brand mt-1">Save</button></form>
            <?php endif; endif; ?>
        </div>
        <?php endforeach; ?>
        <?php if ($comments === []): ?><p class="text-muted fs-12 mb-2">No comments yet.</p><?php endif; ?>
        <?php if ($canComment): ?>
        <form hx-post="/issues/comments/save.php" hx-swap="none" id="issue-comment-add-form" hx-on::after-request="if (event.detail.successful) this.reset()"><?= csrf_field() ?><input type="hidden" name="issue" value="<?= $id ?>">
            <textarea name="body" id="issue-comment-add-body" class="form-control form-control-sm" rows="3" maxlength="20000" placeholder="Write a comment (markdown)…" required></textarea>
            <button type="submit" class="btn btn-sm btn-primary mt-2" id="issue-comment-add-btn"><i class="feather-message-square me-1"></i>Comment</button></form>
        <?php endif; ?>
    </div>
</div>
