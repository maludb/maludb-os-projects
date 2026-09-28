<?php /** Region #backlog-boxes: the active sprint, then the future ones. Data: project, boxes, can, settings, targets, tz */
$key = (string) $project['key']; $base = '/projects/' . $key; $archived = $project['status'] === 'archived';
$anyActive = false; foreach ($boxes as $b) { if ($b['state'] === 'active') { $anyActive = true; } }
?>
<div id="backlog-boxes" hx-get="<?= e($base) ?>/backlog" hx-trigger="backlogChanged from:body, issueChanged from:body, sprintChanged from:body" hx-swap="outerHTML">
    <?php if ($boxes === []): ?>
        <div class="text-muted fs-12 mb-3" id="backlog-boxes-empty">No sprint yet.<?php if ($can['plan'] && !$archived): ?> <a href="/sprints/new?project=<?= e($key) ?>" hx-get="/sprints/new?project=<?= e($key) ?>" hx-target="#page-content" hx-push-url="/sprints/new?project=<?= e($key) ?>">Create the first sprint</a> and drag issues into it.<?php endif; ?></div>
    <?php endif; ?>
    <?php foreach ($boxes as $b): $sid = (int) $b['sprint_id']; $url = '/sprints/' . $sid; $tone = sprint_state_tone((string) $b['state']); $left = sprint_days_left($b['end_date']); ?>
    <div class="card border mb-3 sprint-box" id="sprint-box-<?= $sid ?>">
        <div class="card-header py-2 d-flex flex-wrap align-items-center gap-2">
            <a href="<?= e($url) ?>" class="fw-semibold" hx-get="<?= e($url) ?>" hx-target="#page-content" hx-push-url="<?= e($url) ?>" id="sprint-box-<?= $sid ?>-name"><?= e($b['name']) ?></a>
            <span class="badge bg-soft-<?= e($tone) ?> text-<?= e($tone) ?>"><?= e(ucfirst((string) $b['state'])) ?></span>
            <span class="text-muted fs-12"><?php if ($b['start_date'] !== null): ?><?= e(format_date($b['start_date'])) ?><?= $b['end_date'] !== null ? ' – ' . e(format_date($b['end_date'])) : '' ?><?php else: ?>No dates yet<?php endif; ?><?php if ($b['state'] === 'active' && $left !== null): ?> · <?= $left < 0 ? abs($left) . ' days over' : $left . ' days left' ?><?php endif; ?></span>
            <span class="ms-auto text-muted fs-12" id="sprint-box-<?= $sid ?>-points"><?= (int) $b['issues'] ?> <?= (int) $b['issues'] === 1 ? 'issue' : 'issues' ?> · <?= e(days_label($b['points'])) ?> pts<?php if ($b['state'] === 'active'): ?> · <?= e(days_label($b['points_done'])) ?> done<?php endif; ?></span>
            <?php if ($b['state'] === 'future' && !$anyActive && $can['run'] && !$archived): ?>
                <form method="post" action="/sprints/start.php" hx-post="/sprints/start.php" hx-swap="none" class="d-inline"><?= csrf_field() ?><input type="hidden" name="sprint" value="<?= $sid ?>"><button type="submit" class="btn btn-sm btn-primary" id="sprint-box-<?= $sid ?>-start-btn"><i class="feather-play me-1"></i>Start</button></form>
            <?php endif; ?>
            <?php if ($can['plan'] && !$archived): $eu = $url . '/edit'; ?>
                <a href="<?= e($eu) ?>" class="btn btn-sm btn-light-brand" hx-get="<?= e($eu) ?>" hx-target="#page-content" hx-push-url="<?= e($eu) ?>" id="sprint-box-<?= $sid ?>-edit-btn"><i class="feather-edit-3"></i></a>
            <?php endif; ?>
        </div>
        <div class="list-group list-group-flush backlog-rows" id="sprint-box-<?= $sid ?>-issues" style="min-height:2.5rem"
             <?php if ($can['plan'] && !$archived): ?>data-sortable data-sortable-group="backlog" data-sortable-handle=".backlog-row-handle" data-sortable-action="/issues/sprint.php" data-sortable-value="<?= $sid ?>" data-sortable-target="#backlog-drop"<?php endif; ?>>
            <?php if ($b['issue_rows'] === []): ?><div class="list-group-item text-muted fs-12 text-center" data-empty>Nothing planned — drag issues here or use Move to…</div><?php endif; ?>
            <?php foreach ($b['issue_rows'] as $n => $i): echo view('projects/partials/backlog-row.php', ['i' => $i, 'rows' => $b['issue_rows'], 'n' => $n, 'can' => $can, 'settings' => $settings, 'targets' => $targets, 'inSprint' => $sid, 'archived' => $archived]); endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>
    <div id="backlog-drop" hidden></div>
</div>
