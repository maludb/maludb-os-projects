<?php /** Board: the columns as they stand and the way to slice 3's board form (board_save). Data: project, config, canAdmin */
$p = $project; $key = (string) $p['key']; $statusName = []; foreach ($config['statuses'] as $s) { $statusName[(int) $s['status_id']] = (string) $s['name']; }
$formUrl = '/board/form?key=' . $key;
?>
<div class="card mb-0" id="project-settings-board">
    <div class="card-header d-flex align-items-center justify-content-between">
        <h5 class="card-title mb-0">Board</h5>
        <?php if ($canAdmin): ?><a href="<?= e($formUrl) ?>" id="project-settings-board-edit-btn" class="btn btn-sm btn-primary" hx-get="<?= e($formUrl) ?>" hx-target="#page-content" hx-push-url="<?= e($formUrl) ?>"><i class="feather-edit-3 me-1"></i>Edit board settings</a><?php endif; ?>
    </div>
    <div class="card-body">
        <p class="fs-12 text-muted mb-3"><?= $config['board_mode'] === 'kanban' ? 'Kanban — every open issue.' : 'Scrum — the active sprint.' ?> Swimlanes: <?= e(['none' => 'none', 'assignee' => 'by assignee', 'epic' => 'by epic'][$config['swimlane']] ?? $config['swimlane']) ?>. Every status of the workflow sits in exactly one column; a WIP limit warns and never blocks.</p>
        <div class="table-responsive"><table class="table table-sm align-middle mb-0" id="project-settings-board-columns">
            <thead class="thead-light"><tr><th>Column</th><th>Statuses</th><th class="text-end">WIP limit</th></tr></thead>
            <tbody>
            <?php foreach ($config['columns'] as $c): ?>
                <tr id="project-settings-board-column-<?= (int) $c['column_id'] ?>"><td class="fw-semibold"><?= e($c['name']) ?></td>
                    <td><?php $names = array_map(static fn (int $sid): string => $statusName[$sid] ?? ('#' . $sid), $c['status_ids']); echo $names === [] ? '<span class="text-muted fs-12">none</span>' : e(implode(', ', $names)); ?></td>
                    <td class="text-end"><?= $c['wip_limit'] !== null ? (int) $c['wip_limit'] : '<span class="text-muted">—</span>' ?></td></tr>
            <?php endforeach; ?>
            <?php $placed = []; foreach ($config['columns'] as $c) { foreach ($c['status_ids'] as $sid) { $placed[] = (int) $sid; } } $missing = array_diff(array_keys($statusName), $placed);
            if ($missing !== []): ?><tr id="project-settings-board-unplaced"><td class="text-warning" colspan="3"><i class="feather-alert-triangle me-1"></i>In no column yet: <?= e(implode(', ', array_map(static fn (int $sid): string => $statusName[$sid], $missing))) ?> — the board cannot save until every status has a column.</td></tr><?php endif; ?>
            </tbody>
        </table></div>
    </div>
</div>
