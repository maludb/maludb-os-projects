<?php /** Region #issue-worklogs. Data: issue, worklogs, canLog, me */
$key = (string) $issue['key']; $id = (int) $issue['issue_id']; $total = array_sum(array_map(static fn (array $w): float => (float) $w['hours'], $worklogs)); ?>
<div class="card" id="issue-worklogs" hx-get="/issues/view.php?key=<?= e($key) ?>&region=worklogs" hx-trigger="issueChanged from:body" hx-swap="outerHTML">
    <div class="card-header"><h5 class="card-title mb-0">Worklogs <span class="text-muted fs-12 fw-normal">(<?= e((string) $total) ?> h)</span></h5></div>
    <div class="card-body p-0">
        <?php if ($worklogs !== []): ?>
        <div class="table-responsive"><table class="table table-sm mb-0" id="issue-worklogs-table">
            <thead class="thead-light"><tr><th>Who</th><th class="text-end">Hours</th><th>Day</th><th>Note</th><th></th></tr></thead><tbody>
            <?php foreach ($worklogs as $w): ?>
            <tr id="issue-worklog-row-<?= (int) $w['worklog_id'] ?>"><td><?= e($w['display_name']) ?></td><td class="text-end"><?= e((string) (float) $w['hours']) ?></td><td class="text-nowrap"><?= e(format_date($w['worked_on'])) ?></td><td class="text-muted fs-12"><?= e($w['note'] ?? '') ?></td>
                <td class="text-end"><?php if ((int) $w['member_id'] === $me): ?><form hx-post="/issues/worklogs/delete.php" hx-swap="none"><?= csrf_field() ?><input type="hidden" name="worklog" value="<?= (int) $w['worklog_id'] ?>"><button type="submit" class="btn btn-sm btn-light text-danger" aria-label="Remove worklog" id="issue-worklog-delete-<?= (int) $w['worklog_id'] ?>">✕</button></form><?php endif; ?></td></tr>
            <?php endforeach; ?>
            </tbody></table></div>
        <?php endif; ?>
        <?php if ($canLog): ?>
        <form hx-post="/issues/worklogs/save.php" hx-swap="none" class="row g-2 p-3 align-items-center" id="issue-worklog-add-form"><?= csrf_field() ?><input type="hidden" name="issue" value="<?= $id ?>">
            <div class="col-4 col-md-2"><input type="number" name="hours" id="issue-worklog-hours" class="form-control form-control-sm" step="0.25" min="0.25" max="24" placeholder="Hours" required aria-label="Hours"></div>
            <div class="col-8 col-md-3"><input type="date" name="worked_on" id="issue-worklog-day" class="form-control form-control-sm" value="<?= e(date('Y-m-d')) ?>" required aria-label="Day"></div>
            <div class="col-9 col-md-5"><input type="text" name="note" id="issue-worklog-note" class="form-control form-control-sm" placeholder="What was done" maxlength="300" aria-label="Note"></div>
            <div class="col-3 col-md-2"><button type="submit" class="btn btn-sm btn-light-brand w-100" id="issue-worklog-add-btn">Log</button></div>
        </form>
        <?php elseif ($worklogs === []): ?><p class="text-muted fs-12 p-3 mb-0">No hours logged.</p><?php endif; ?>
    </div>
</div>
