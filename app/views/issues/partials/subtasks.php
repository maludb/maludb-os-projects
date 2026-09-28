<?php /** Region #issue-subtasks. Data: issue, children, canWork */
$key = (string) $issue['key']; $nav = static fn (string $url): string => 'href="' . e($url) . '" hx-get="' . e($url) . '" hx-target="#page-content" hx-swap="innerHTML" hx-push-url="' . e($url) . '"'; ?>
<div class="card" id="issue-subtasks" hx-get="/issues/view.php?key=<?= e($key) ?>&region=subtasks" hx-trigger="issueChanged from:body" hx-swap="outerHTML">
    <div class="card-header"><h5 class="card-title mb-0">Subtasks <span class="text-muted fs-12 fw-normal">(<?= count($children) ?>)</span></h5></div>
    <div class="card-body p-0">
        <?php if ($children !== []): ?>
        <div class="table-responsive"><table class="table table-sm table-hover mb-0" id="issue-subtasks-table">
            <thead class="thead-light"><tr><th>Key</th><th>Summary</th><th>Status</th><th>Assignee</th></tr></thead><tbody>
            <?php foreach ($children as $c): $t = issue_status_tone((string) $c['status_category']); ?>
            <tr id="issue-subtask-row-<?= (int) $c['issue_id'] ?>"><td class="text-nowrap"><a <?= $nav('/issues/' . $c['key']) ?> class="fw-semibold"><?= e($c['key']) ?></a></td><td><?= e($c['summary']) ?></td>
                <td><span class="badge bg-soft-<?= e($t) ?> text-<?= e($t) ?>"><?= e($c['status']) ?></span></td><td class="text-muted fs-12"><?= e($c['assignee_name'] ?? '—') ?></td></tr>
            <?php endforeach; ?>
            </tbody></table></div>
        <?php endif; ?>
        <?php if ($canWork): ?>
        <form hx-post="/issues/save.php" hx-swap="none" class="d-flex gap-2 p-3" id="issue-subtask-add-form"><?= csrf_field() ?>
            <input type="hidden" name="project" value="<?= e($issue['project_key']) ?>"><input type="hidden" name="parent" value="<?= (int) $issue['issue_id'] ?>"><input type="hidden" name="type" value="subtask">
            <input type="text" name="summary" id="issue-subtask-add-summary" class="form-control form-control-sm" placeholder="Add a subtask…" maxlength="255" required>
            <button type="submit" class="btn btn-sm btn-light-brand text-nowrap" id="issue-subtask-add-btn"><i class="feather-plus me-1"></i>Add</button></form>
        <?php elseif ($children === []): ?><p class="text-muted fs-12 p-3 mb-0">No subtasks.</p><?php endif; ?>
    </div>
</div>
