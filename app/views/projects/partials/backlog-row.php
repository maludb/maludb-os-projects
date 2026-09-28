<?php /** One backlog row. Data: i, rows, n, can, settings, targets, inSprint (sprint id or null), archived, ranked (rank buttons make sense) */
$iid = (int) $i['issue_id']; $url = '/issues/' . $i['key']; [$pm, $ptone, $plabel] = issue_priority_mark((string) $i['priority']); $tone = issue_status_tone((string) $i['status_category']);
$ranked = ($ranked ?? true) && $inSprint === null; $count = count($rows);
$prev = $n > 0 ? (int) $rows[$n - 1]['issue_id'] : null; $prev2 = $n > 1 ? (int) $rows[$n - 2]['issue_id'] : null;
$next = $n < $count - 1 ? (int) $rows[$n + 1]['issue_id'] : null; $next2 = $n < $count - 2 ? (int) $rows[$n + 2]['issue_id'] : null;
$vals = static fn (array $v): string => e(json_encode(array_filter($v, static fn ($x) => $x !== null), JSON_THROW_ON_ERROR));
$mayRank = $can['rank'] && !$archived; $mayPlan = $can['plan'] && !$archived; $mayEstimate = ($can['estimate'] || (int) ($i['assignee_member_id'] ?? 0) === (int) current_member_id()) && !$archived;
?>
<div class="list-group-item backlog-row px-2 py-1" id="backlog-row-<?= $iid ?>" data-id="<?= $iid ?>">
    <form class="d-flex flex-wrap align-items-center gap-2" id="backlog-row-<?= $iid ?>-form" onsubmit="return false">
        <?= csrf_field() ?><input type="hidden" name="issue" value="<?= $iid ?>">
        <?php if ($mayRank || $mayPlan): ?><span class="backlog-row-handle text-muted" style="cursor:grab" title="Drag to reorder"><i class="feather-menu"></i></span><?php endif; ?>
        <i class="<?= e(issue_type_icon((string) $i['type'])) ?> text-muted" title="<?= e(ucfirst((string) $i['type'])) ?>"></i>
        <a href="<?= e($url) ?>" class="fw-semibold text-nowrap" hx-get="<?= e($url) ?>" hx-target="#page-content" hx-push-url="<?= e($url) ?>"><?= e($i['key']) ?></a>
        <a href="<?= e($url) ?>" class="text-dark flex-grow-1 text-truncate" style="min-width:8rem" hx-get="<?= e($url) ?>" hx-target="#page-content" hx-push-url="<?= e($url) ?>"><?= e($i['summary']) ?></a>
        <?php if ((int) $i['open_blockers'] > 0): ?><i class="feather-slash text-danger" title="Blocked"></i><?php endif; ?>
        <?php if ($i['epic_name'] !== null): ?><span class="badge bg-soft-secondary text-dark fs-11"><?= e($i['epic_name']) ?></span><?php endif; ?>
        <span class="badge bg-soft-<?= e($tone) ?> text-<?= e($tone) ?>"><?= e($i['status']) ?></span>
        <span class="text-<?= e($ptone) ?>" title="<?= e($plabel) ?>"><?= e($pm) ?></span>
        <?php if ($i['assignee_member_id'] !== null): ?><span class="avatar-text avatar-xs rounded-circle" title="<?= e($i['assignee_name']) ?>"><?= e(initials((string) $i['assignee_name'])) ?></span><?php endif; ?>
        <?php if ($mayEstimate): ?>
            <select name="story_points" class="form-select form-select-sm w-auto" aria-label="Story points" id="backlog-row-<?= $iid ?>-points" hx-post="/issues/estimate.php" hx-trigger="change" hx-include="closest form" hx-swap="none">
                <option value="">— pts</option>
                <?php foreach ($settings['point_scale'] as $p): ?><option value="<?= (int) $p ?>" <?= $i['story_points'] !== null && (float) $i['story_points'] === (float) $p ? 'selected' : '' ?>><?= (int) $p ?></option><?php endforeach; ?>
            </select>
        <?php else: ?><span class="text-muted fs-12 text-nowrap"><?= $i['story_points'] !== null ? e(days_label($i['story_points'])) . ' pts' : '—' ?></span><?php endif; ?>
        <?php if ($mayRank && $ranked): ?>
            <span class="btn-group btn-group-sm" role="group" aria-label="Rank">
                <button type="button" class="btn btn-light-brand px-1" title="Top" id="backlog-row-<?= $iid ?>-top" hx-post="/issues/rank.php" hx-vals='<?= $vals(['top' => 1]) ?>' hx-include="closest form" hx-swap="none" <?= $n === 0 ? 'disabled' : '' ?>><i class="feather-chevrons-up"></i></button>
                <button type="button" class="btn btn-light-brand px-1" title="Up" id="backlog-row-<?= $iid ?>-up" hx-post="/issues/rank.php" hx-vals='<?= $vals(['before' => $prev, 'after' => $prev2]) ?>' hx-include="closest form" hx-swap="none" <?= $prev === null ? 'disabled' : '' ?>><i class="feather-chevron-up"></i></button>
                <button type="button" class="btn btn-light-brand px-1" title="Down" id="backlog-row-<?= $iid ?>-down" hx-post="/issues/rank.php" hx-vals='<?= $vals(['after' => $next, 'before' => $next2]) ?>' hx-include="closest form" hx-swap="none" <?= $next === null ? 'disabled' : '' ?>><i class="feather-chevron-down"></i></button>
                <button type="button" class="btn btn-light-brand px-1" title="Bottom" id="backlog-row-<?= $iid ?>-bottom" hx-post="/issues/rank.php" hx-vals='<?= $vals(['bottom' => 1]) ?>' hx-include="closest form" hx-swap="none" <?= $n === $count - 1 ? 'disabled' : '' ?>><i class="feather-chevrons-down"></i></button>
            </span>
        <?php endif; ?>
        <?php if ($mayPlan && ($targets !== [] || $inSprint !== null)): ?>
            <select name="sprint" class="form-select form-select-sm w-auto" aria-label="Move to" id="backlog-row-<?= $iid ?>-sprint" hx-post="/issues/sprint.php" hx-trigger="change" hx-include="closest form" hx-swap="none">
                <option value="" <?= $inSprint === null ? 'selected' : '' ?>><?= $inSprint === null ? 'Move to…' : 'Backlog' ?></option>
                <?php foreach ($targets as $tid => $tname): ?><option value="<?= (int) $tid ?>" <?= $inSprint === (int) $tid ? 'selected' : '' ?>><?= e($tname) ?></option><?php endforeach; ?>
            </select>
        <?php endif; ?>
    </form>
</div>
