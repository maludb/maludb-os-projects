<?php /** The columns region (#board-columns): polled every 30 s and on issueChanged / boardChanged. Data: project, board, filters, canWork */
$key = (string) $project['key'];
$url = '/board/columns.php?' . http_build_query(array_filter(['key' => $key, 'lane' => $board['lane'], 'assignee' => $filters['assignee'], 'epic' => $filters['epic'], 'type' => $filters['type'], 'q' => $filters['q']], static fn ($v) => $v !== null && $v !== ''));
$lanes = $board['lane'] === 'none' ? [['key' => 'all', 'label' => '', 'kind' => '', 'count' => $board['total']]] : $board['lanes'];
$statuses = $board['statuses'];
?>
<div id="board-columns" hx-get="<?= e($url) ?>" hx-trigger="every 30s [!document.body.hasAttribute('data-dragging')], issueChanged from:body, boardChanged from:body" hx-swap="outerHTML scroll:none" data-board-key="<?= e($key) ?>">
    <div id="board" class="pb-2">
        <?php if ($board['columns'] === []): ?>
            <div class="text-center text-muted py-5" id="board-empty">This board has no columns yet — set them up in Board settings.</div>
        <?php elseif ($board['total'] === 0): ?>
            <div class="text-center text-muted py-4" id="board-empty"><?= $board['mode'] === 'scrum' && $board['sprint'] === null ? 'No sprint is running — start one from the backlog.' : 'Nothing on the board.' ?></div>
        <?php endif; ?>
        <?php if ($board['columns'] !== []): ?>
        <div class="d-flex gap-3 align-items-start" style="min-width: max-content;" id="board-column-heads">
            <?php foreach ($board['columns'] as $c): $over = $c['wip_limit'] !== null && (int) $c['count'] > (int) $c['wip_limit']; ?>
                <div class="board-column-head" style="width: 280px; flex: 0 0 280px;" id="board-column-head-<?= (int) $c['column_id'] ?>">
                    <div class="d-flex align-items-center justify-content-between px-2 py-1 rounded <?= $over ? 'bg-soft-danger' : 'bg-light' ?>">
                        <span class="fw-semibold fs-12 text-uppercase"><?= e($c['name']) ?></span>
                        <span class="badge <?= $over ? 'bg-danger text-white' : 'bg-soft-secondary text-dark' ?>" id="board-column-count-<?= (int) $c['column_id'] ?>" title="<?= $c['wip_limit'] !== null ? 'WIP limit ' . (int) $c['wip_limit'] : 'No WIP limit' ?>"><?= (int) $c['count'] ?><?= $c['wip_limit'] !== null ? ' / ' . (int) $c['wip_limit'] : '' ?></span>
                    </div>
                    <?php if ($over): ?><div class="fs-11 text-danger px-2 mt-1" id="board-column-over-<?= (int) $c['column_id'] ?>"><i class="feather-alert-triangle me-1"></i>Over the WIP limit</div><?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php foreach ($lanes as $lane): ?>
            <?php if ($lane['key'] !== 'all'): ?>
            <div class="fs-12 fw-semibold text-muted mt-3 mb-1 border-bottom pb-1" id="board-lane-<?= e($lane['key']) ?>" style="position: sticky; left: 0;">
                <?= $lane['kind'] === 'agent' ? '<i class="feather-cpu me-1"></i>' : ($lane['kind'] === 'epic' ? '<i class="feather-flag me-1"></i>' : '<i class="feather-user me-1"></i>') ?><?= e($lane['label']) ?> <span class="text-muted fw-normal">· <?= (int) $lane['count'] ?></span>
            </div>
            <?php endif; ?>
            <div class="d-flex gap-3 align-items-stretch" style="min-width: max-content;">
                <?php foreach ($board['columns'] as $c): $first = $c['status_ids'][0] ?? 0; ?>
                    <div class="board-column d-flex flex-column gap-2 p-2 rounded bg-light" style="width: 280px; flex: 0 0 280px; min-height: 60px;"
                         id="board-column-<?= (int) $c['column_id'] ?>-<?= e($lane['key']) ?>" data-board-column="<?= (int) $c['column_id'] ?>" data-statuses="<?= e(implode(',', $c['status_ids'])) ?>"
                         data-column-name="<?= e($c['name']) ?>" data-sortable-value="<?= (int) $first ?>" <?= $canWork ? 'data-board-droppable' : '' ?>>
                        <?php foreach ($c['cards'] as $i): if ($i['lane'] !== $lane['key']) { continue; } ?>
                            <?= view('projects/partials/board-card.php', ['i' => $i, 'statuses' => $statuses, 'columns' => $board['columns'], 'canWork' => $canWork, 'column' => $c]) ?>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
