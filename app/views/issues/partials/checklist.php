<?php /** Region #issue-checklist. Data: issue, items, canWork */
$key = (string) $issue['key']; $id = (int) $issue['issue_id']; $done = count(array_filter($items, static fn (array $c): bool => (bool) $c['is_done'])); ?>
<div class="card" id="issue-checklist" hx-get="/issues/view.php?key=<?= e($key) ?>&region=checklist" hx-trigger="issueChanged from:body" hx-swap="outerHTML">
    <div class="card-header"><h5 class="card-title mb-0">Checklist <span class="text-muted fs-12 fw-normal">(<?= $done ?>/<?= count($items) ?>)</span></h5></div>
    <div class="card-body">
        <ul class="list-unstyled mb-2" id="issue-checklist-list">
            <?php foreach ($items as $c): ?>
            <li class="d-flex align-items-center gap-2 py-1" id="issue-checklist-row-<?= (int) $c['checklist_item_id'] ?>">
                <?php if ($canWork): ?>
                <form hx-post="/issues/checklist/toggle.php" hx-swap="none" class="d-flex align-items-center gap-2 flex-grow-1"><?= csrf_field() ?><input type="hidden" name="item" value="<?= (int) $c['checklist_item_id'] ?>"><input type="hidden" name="done" value="<?= $c['is_done'] ? '0' : '1' ?>">
                    <input type="checkbox" class="form-check-input" id="issue-checklist-check-<?= (int) $c['checklist_item_id'] ?>" <?= $c['is_done'] ? 'checked' : '' ?> hx-post="/issues/checklist/toggle.php" hx-trigger="change" hx-include="closest form" hx-swap="none">
                    <label for="issue-checklist-check-<?= (int) $c['checklist_item_id'] ?>" class="mb-0 <?= $c['is_done'] ? 'text-decoration-line-through text-muted' : '' ?>"><?= e($c['text']) ?></label></form>
                <form hx-post="/issues/checklist/delete.php" hx-swap="none"><?= csrf_field() ?><input type="hidden" name="item" value="<?= (int) $c['checklist_item_id'] ?>"><button type="submit" class="btn btn-sm btn-light text-danger" aria-label="Remove item" id="issue-checklist-delete-<?= (int) $c['checklist_item_id'] ?>">✕</button></form>
                <?php else: ?><i class="feather-<?= $c['is_done'] ? 'check-square' : 'square' ?>"></i><span class="<?= $c['is_done'] ? 'text-decoration-line-through text-muted' : '' ?>"><?= e($c['text']) ?></span><?php endif; ?>
            </li>
            <?php endforeach; ?>
            <?php if ($items === []): ?><li class="text-muted fs-12">Nothing on the checklist.</li><?php endif; ?>
        </ul>
        <?php if ($canWork): ?>
        <form hx-post="/issues/checklist/save.php" hx-swap="none" class="d-flex gap-2" id="issue-checklist-add-form"><?= csrf_field() ?><input type="hidden" name="issue" value="<?= $id ?>">
            <input type="text" name="text" id="issue-checklist-add-text" class="form-control form-control-sm" placeholder="Add an item…" maxlength="500" required>
            <button type="submit" class="btn btn-sm btn-light-brand text-nowrap" id="issue-checklist-add-btn"><i class="feather-plus me-1"></i>Add</button></form>
        <?php endif; ?>
    </div>
</div>
