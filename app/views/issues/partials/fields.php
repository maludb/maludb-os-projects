<?php /** The fields column (region #issue-fields). Data: issue, project, statuses, options, settings, me, canWork, canAdmin, canAccept, canEstimate, canDelete, members, tz */
$i = $issue; $key = (string) $i['key']; $id = (int) $i['issue_id']; $current = $statuses[(int) $i['status_id']] ?? null;
$tone = issue_status_tone((string) $i['status_category']); [$pm, $pt, $pl] = issue_priority_mark((string) $i['priority']);
$nav = static fn (string $url): string => 'href="' . e($url) . '" hx-get="' . e($url) . '" hx-target="#page-content" hx-swap="innerHTML" hx-push-url="' . e($url) . '"';
$post = static fn (string $url, string $extra = ''): string => 'hx-post="' . e($url) . '" hx-swap="none" ' . $extra;
$refresh = 'hx-get="/issues/view.php?key=' . e($key) . '&region=fields" hx-trigger="issueChanged from:body" hx-swap="outerHTML"';
?>
<div class="card" id="issue-fields" <?= $refresh ?>>
    <div class="card-header"><h5 class="card-title mb-0">Details</h5>
        <span class="badge bg-soft-<?= e($tone) ?> text-<?= e($tone) ?>" id="issue-fields-status"><?= e($i['status']) ?></span></div>
    <div class="card-body">
        <?php if ($canWork && $current !== null && $current['can_move_to'] !== []): ?>
        <div class="d-flex flex-wrap gap-2 mb-3" id="issue-transitions">
            <?php foreach ($current['can_move_to'] as $sid): if (!isset($statuses[$sid])) { continue; } $s = $statuses[$sid]; $t = issue_status_tone((string) $s['category']); ?>
            <form <?= $post('/issues/transition.php') ?> class="d-inline"><?= csrf_field() ?><input type="hidden" name="issue" value="<?= $id ?>"><input type="hidden" name="status" value="<?= $sid ?>">
                <button type="submit" class="btn btn-sm btn-light-<?= e($t) ?>" id="issue-transition-<?= $sid ?>"><i class="feather-arrow-right me-1"></i><?= e($s['name']) ?></button></form>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <dl class="row mb-0 fs-12" id="issue-fields-list">
            <dt class="col-5 text-muted">Type</dt><dd class="col-7"><i class="<?= e(issue_type_icon((string) $i['type'])) ?> me-1"></i><?= e(ucfirst((string) $i['type'])) ?></dd>
            <dt class="col-5 text-muted">Priority</dt><dd class="col-7"><span class="text-<?= e($pt) ?> fw-bold"><?= e($pm) ?></span> <?= e($pl) ?></dd>
            <dt class="col-5 text-muted">Assignee</dt><dd class="col-7">
                <?php if ($canWork): ?>
                <form <?= $post('/issues/assign.php') ?>><?= csrf_field() ?><input type="hidden" name="issue" value="<?= $id ?>">
                    <select name="assignee" id="issue-field-assignee" class="form-select form-select-sm" hx-post="/issues/assign.php" hx-trigger="change" hx-include="closest form" hx-swap="none" aria-label="Assignee">
                        <option value="">Unassigned</option>
                        <?php foreach ($members as $m): ?><option value="<?= (int) $m['member_id'] ?>" <?= (int) ($i['assignee_member_id'] ?? 0) === (int) $m['member_id'] ? 'selected' : '' ?>><?= e($m['display_name']) ?><?= $m['member_kind'] === 'agent' ? ' (agent)' : '' ?></option><?php endforeach; ?>
                    </select></form>
                <?php else: ?><?= e($i['assignee_name'] ?? 'Unassigned') ?><?= ($i['assignee_kind'] ?? '') === 'agent' ? ' <span class="badge bg-soft-info text-info">agent</span>' : '' ?><?php endif; ?>
            </dd>
            <dt class="col-5 text-muted">Reporter</dt><dd class="col-7"><?= e($i['reporter_name'] ?? '—') ?></dd>
            <?php if ($i['type'] !== 'subtask'): ?>
            <dt class="col-5 text-muted">Story points</dt><dd class="col-7">
                <?php if ($canEstimate): ?>
                <form <?= $post('/issues/estimate.php') ?>><?= csrf_field() ?><input type="hidden" name="issue" value="<?= $id ?>"><input type="hidden" name="estimate_hours" value="<?= e($i['estimate_hours'] ?? '') ?>">
                    <select name="story_points" id="issue-field-points" class="form-select form-select-sm" hx-post="/issues/estimate.php" hx-trigger="change" hx-include="closest form" hx-swap="none" aria-label="Story points">
                        <option value="">none</option>
                        <?php foreach ($settings['point_scale'] as $p): ?><option value="<?= (int) $p ?>" <?= $i['story_points'] !== null && (float) $i['story_points'] === (float) $p ? 'selected' : '' ?>><?= (int) $p ?></option><?php endforeach; ?>
                    </select></form>
                <?php else: ?><?= $i['story_points'] !== null ? e((string) (float) $i['story_points']) : '—' ?><?php endif; ?>
            </dd>
            <?php endif; ?>
            <dt class="col-5 text-muted">Estimate</dt><dd class="col-7"><?= $i['estimate_hours'] !== null ? e((string) (float) $i['estimate_hours']) . ' h' : '—' ?><?= (float) $i['hours_logged'] > 0 ? ' · ' . e((string) (float) $i['hours_logged']) . ' h logged' : '' ?></dd>
            <?php if ($i['type'] !== 'subtask'): ?>
            <dt class="col-5 text-muted">Epic</dt><dd class="col-7"><?= $i['epic_id'] !== null ? '<a ' . $nav('/epics/' . (int) $i['epic_id']) . '>' . e($i['epic_name']) . '</a>' : '—' ?></dd>
            <?php endif; ?>
            <dt class="col-5 text-muted">Sprint</dt><dd class="col-7"><?= $i['sprint_id'] !== null ? '<a ' . $nav('/sprints/' . (int) $i['sprint_id']) . '>' . e($i['sprint_name']) . '</a>' . ($i['sprint_state'] === 'active' ? ' <span class="badge bg-soft-success text-success">active</span>' : '') : 'Backlog' ?></dd>
            <dt class="col-5 text-muted">Component</dt><dd class="col-7"><?= e($i['component_name'] ?? '—') ?></dd>
            <dt class="col-5 text-muted">Labels</dt><dd class="col-7"><?php $labels = present_pg_names($i['labels'] ?? null); echo $labels === [] ? '—' : implode(' ', array_map(static fn (string $l): string => '<span class="badge bg-soft-secondary text-dark">' . e($l) . '</span>', $labels)); ?></dd>
            <dt class="col-5 text-muted">Due</dt><dd class="col-7 <?= $i['due_date'] !== null && $i['due_date'] < date('Y-m-d') && $i['status_category'] !== 'done' ? 'text-danger' : '' ?>"><?= e(format_date($i['due_date'])) ?: '—' ?></dd>
            <?php if ((int) $i['open_blockers'] > 0): ?><dt class="col-5 text-muted">Blocked</dt><dd class="col-7 text-danger"><i class="feather-slash me-1"></i>by <?= (int) $i['open_blockers'] ?> open issue<?= (int) $i['open_blockers'] === 1 ? '' : 's' ?></dd><?php endif; ?>
            <?php if ($i['resolution'] !== null): ?><dt class="col-5 text-muted">Resolution</dt><dd class="col-7"><?= e(str_replace('_', ' ', (string) $i['resolution'])) ?><?= $i['accepted_at'] !== null ? ' · accepted by ' . e($i['accepted_by_name'] ?? '?') : '' ?></dd><?php endif; ?>
            <dt class="col-5 text-muted">Created</dt><dd class="col-7"><?= e(format_ts($i['created_at'], $tz)) ?></dd>
            <dt class="col-5 text-muted">Updated</dt><dd class="col-7"><?= e(format_ts($i['updated_at'], $tz)) ?></dd>
            <?php if (!empty($i['aliases'])): ?><dt class="col-5 text-muted">Was</dt><dd class="col-7"><?= e(implode(', ', present_pg_names($i['aliases']))) ?></dd><?php endif; ?>
        </dl>
        <div class="d-flex flex-wrap gap-2 mt-3" id="issue-fields-actions">
            <form <?= $post('/issues/watch.php') ?>><?= csrf_field() ?><input type="hidden" name="issue" value="<?= $id ?>"><input type="hidden" name="action" value="<?= $i['i_watch'] ? 'unwatch' : 'watch' ?>">
                <button type="submit" class="btn btn-sm btn-light-brand" id="issue-watch-btn"><i class="feather-<?= $i['i_watch'] ? 'eye-off' : 'eye' ?> me-1"></i><?= $i['i_watch'] ? 'Unwatch' : 'Watch' ?></button></form>
            <?php if ($canAccept && $i['status_category'] === 'done'): ?>
            <form <?= $post('/issues/accept.php') ?>><?= csrf_field() ?><input type="hidden" name="issue" value="<?= $id ?>"><input type="hidden" name="accept" value="<?= $i['accepted_at'] !== null ? '0' : '1' ?>">
                <button type="submit" class="btn btn-sm btn-light-<?= $i['accepted_at'] !== null ? 'secondary' : 'success' ?>" id="issue-accept-btn"><i class="feather-check-circle me-1"></i><?= $i['accepted_at'] !== null ? 'Withdraw acceptance' : 'Accept' ?></button></form>
            <?php endif; ?>
            <?php if ($canDelete): ?>
            <form <?= $post('/issues/delete.php', 'hx-confirm="Delete ' . e($key) . '? This cannot be undone."') ?> hx-target="#page-content" hx-swap="innerHTML"><?= csrf_field() ?><input type="hidden" name="issue" value="<?= $id ?>">
                <button type="submit" class="btn btn-sm btn-light-danger" id="issue-delete-btn"><i class="feather-trash-2 me-1"></i>Delete</button></form>
            <?php endif; ?>
        </div>
    </div>
</div>
