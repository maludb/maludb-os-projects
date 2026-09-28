<?php /** Region #sprints-list: the sprints ledger. Data: project, sprints, can, tz */
$key = (string) $project['key']; $base = '/projects/' . $key;
?>
<div id="sprints-list" hx-get="<?= e($base) ?>/sprints" hx-trigger="sprintChanged from:body" hx-swap="outerHTML">
    <div class="table-responsive" style="overflow-x:auto">
        <table class="table table-hover mb-0" id="sprints-list-table">
            <thead class="thead-light"><tr><th>Sprint</th><th>State</th><th>Dates</th><th class="text-end">Issues</th><th class="text-end">Points</th><th class="text-end">Committed</th><th class="text-end">Completed</th><th class="text-end">Carried</th></tr></thead>
            <tbody>
            <?php if ($sprints === []): ?><tr><td colspan="8" class="text-center text-muted py-5" id="sprints-list-empty">No sprint yet.</td></tr><?php endif; ?>
            <?php foreach ($sprints as $s): $sid = (int) $s['sprint_id']; $url = '/sprints/' . $sid; $tone = sprint_state_tone((string) $s['state']); ?>
                <tr id="sprint-row-<?= $sid ?>">
                    <td><a href="<?= e($url) ?>" class="fw-semibold" hx-get="<?= e($url) ?>" hx-target="#page-content" hx-push-url="<?= e($url) ?>"><?= e($s['name']) ?></a><?php if ($s['goal']): ?><div class="fs-12 text-muted text-truncate" style="max-width:24rem"><?= e($s['goal']) ?></div><?php endif; ?></td>
                    <td><span class="badge bg-soft-<?= e($tone) ?> text-<?= e($tone) ?>"><?= e(ucfirst((string) $s['state'])) ?></span></td>
                    <td class="text-nowrap fs-12"><?= $s['start_date'] !== null ? e(format_date($s['start_date'])) : '—' ?><?= $s['end_date'] !== null ? ' – ' . e(format_date($s['end_date'])) : '' ?></td>
                    <td class="text-end"><?= (int) $s['issues_done'] ?> / <?= (int) $s['issues'] ?></td>
                    <td class="text-end"><?= e(days_label($s['points_done'])) ?> / <?= e(days_label($s['points'])) ?></td>
                    <td class="text-end"><?= $s['committed_points'] !== null ? e(days_label($s['committed_points'])) : '<span class="text-muted">—</span>' ?></td>
                    <td class="text-end"><?= $s['completed_points'] !== null ? e(days_label($s['completed_points'])) : '<span class="text-muted">—</span>' ?></td>
                    <td class="text-end"><?= $s['carried_points'] !== null ? e(days_label($s['carried_points'])) : '<span class="text-muted">—</span>' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
