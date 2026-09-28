<?php /** One card on the board. Data: i (mcp_issues row + can_move_to + lane), statuses, columns, canWork, column */
$key = (string) $i['key']; $url = '/issues/' . $key; [$pm, $pt, $pl] = issue_priority_mark((string) $i['priority']);
$moves = [];                                                     // the reachable statuses, grouped by their column
foreach ($i['can_move_to'] as $sid) {
    if (!isset($statuses[$sid])) { continue; }
    foreach ($columns as $c) { if (in_array($sid, $c['status_ids'], true)) { $moves[(string) $c['name']][] = $statuses[$sid]; } }
}
$labels = present_pg_names($i['labels'] ?? null);
?>
<div class="card mb-0 shadow-sm board-card" style="min-height: 44px;" id="board-card-<?= (int) $i['issue_id'] ?>" data-id="<?= (int) $i['issue_id'] ?>" data-key="<?= e($key) ?>"
     data-status="<?= (int) $i['status_id'] ?>" data-can-move-to="<?= e(implode(',', $i['can_move_to'])) ?>">
    <div class="card-body p-2">
        <div class="d-flex align-items-start gap-2">
            <i class="<?= e(issue_type_icon((string) $i['type'])) ?> text-muted mt-1" title="<?= e(ucfirst((string) $i['type'])) ?>"></i>
            <div class="flex-grow-1 overflow-hidden">
                <a href="<?= e($url) ?>" hx-get="<?= e($url) ?>" hx-target="#page-content" hx-push-url="<?= e($url) ?>" class="fw-semibold fs-12 text-dark"><?= e($key) ?></a>
                <div class="fs-12 text-truncate-2-line"><?= e($i['summary']) ?></div>
            </div>
            <?php if ($i['assignee_member_id'] !== null): ?>
                <span class="avatar-text avatar-sm rounded-circle bg-soft-primary text-primary flex-shrink-0" title="<?= e($i['assignee_name'] ?? '') ?>"><?= ($i['assignee_kind'] ?? '') === 'agent' ? '<i class="feather-cpu"></i>' : e(initials((string) $i['assignee_name'])) ?></span>
            <?php endif; ?>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-1 mt-2 fs-11">
            <span class="text-<?= e($pt) ?> fw-bold" title="<?= e($pl) ?>"><?= e($pm) ?></span>
            <?php if ($i['story_points'] !== null): ?><span class="badge bg-soft-secondary text-dark" title="Story points"><?= e((string) (float) $i['story_points']) ?></span><?php endif; ?>
            <?php if ((int) $i['open_blockers'] > 0): ?><span class="text-danger" title="Blocked"><i class="feather-slash"></i></span><?php endif; ?>
            <?php if ((int) $i['subtasks'] > 0): ?><span class="text-muted" title="Subtasks done"><i class="feather-corner-down-right"></i> <?= (int) $i['subtasks_done'] ?>/<?= (int) $i['subtasks'] ?></span><?php endif; ?>
            <?php if ($i['epic_name'] !== null): ?><span class="badge bg-soft-primary text-primary text-truncate" style="max-width: 110px;"><?= e($i['epic_name']) ?></span><?php endif; ?>
            <?php foreach (array_slice($labels, 0, 3) as $l): ?><span class="badge bg-soft-secondary text-dark"><?= e($l) ?></span><?php endforeach; ?>
            <?php if ($canWork && $moves !== []): ?>
            <form class="ms-auto" hx-post="/issues/transition.php" hx-swap="none" hx-trigger="change"><?= csrf_field() ?><input type="hidden" name="issue" value="<?= (int) $i['issue_id'] ?>"><input type="hidden" name="from_column" value="<?= e($column['name']) ?>">
                <select name="status" class="form-select form-select-sm py-0 fs-11" style="width: auto; min-height: 28px;" id="board-card-move-<?= (int) $i['issue_id'] ?>" aria-label="Move <?= e($key) ?> to">
                    <option value="">Move to…</option>
                    <?php foreach ($moves as $colName => $sts): ?><optgroup label="<?= e($colName) ?>"><?php foreach ($sts as $s): ?><option value="<?= (int) $s['status_id'] ?>"><?= e($s['name']) ?></option><?php endforeach; ?></optgroup><?php endforeach; ?>
                </select></form>
            <?php endif; ?>
        </div>
    </div>
</div>
