<?php /** The board page (screen board). Data: project, board, filters, canConfigure, canWork, epics, members, tz */
$p = $project; $key = (string) $p['key']; $base = '/projects/' . $key; $s = $board['sprint'];
$daysLeft = $s !== null && $s['end_date'] !== null ? (int) ((strtotime((string) $s['end_date']) - strtotime(date('Y-m-d'))) / 86400) : null;
$qs = static function (array $over = []) use ($filters, $board, $base): string {
    $q = array_filter(['lane' => $board['lane'], 'assignee' => $filters['assignee'], 'epic' => $filters['epic'], 'type' => $filters['type'], 'q' => $filters['q']] + $over, static fn ($v) => $v !== null && $v !== '');
    return $base . '/board' . ($q === [] ? '' : '?' . http_build_query($q));
};
?>
<div class="page-header" id="board-header">
    <div class="page-header-left d-flex align-items-center">
        <div class="page-header-title"><h5 class="m-b-10"><span class="badge bg-soft-secondary text-dark me-2"><?= e($key) ?></span><?= e($p['name']) ?> · Board</h5></div>
        <ul class="breadcrumb">
            <li class="breadcrumb-item"><a href="/projects" hx-get="/projects" hx-target="#page-content" hx-push-url="/projects">Projects</a></li>
            <li class="breadcrumb-item"><a href="<?= e($base) ?>" hx-get="<?= e($base) ?>" hx-target="#page-content" hx-push-url="<?= e($base) ?>"><?= e($key) ?></a></li>
            <li class="breadcrumb-item">Board</li>
        </ul>
    </div>
    <div class="page-header-right ms-auto">
        <div class="page-header-right-items">
            <div class="d-flex d-md-none"><a href="javascript:void(0)" class="page-header-right-close-toggle"><i class="feather-arrow-left me-2"></i><span>Back</span></a></div>
            <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                <form id="board-filters" class="d-flex align-items-center gap-2 flex-wrap" method="get" action="<?= e($base) ?>/board"
                      hx-get="<?= e($base) ?>/board" hx-target="#page-content" hx-swap="innerHTML" hx-trigger="change, keyup changed delay:400ms from:#board-search" hx-push-url="true">
                    <div class="input-group input-group-sm" style="width: 190px;"><span class="input-group-text"><i class="feather-search"></i></span>
                        <input type="search" name="q" id="board-search" class="form-control" placeholder="Key or summary…" value="<?= e($filters['q']) ?>" aria-label="Search the board"></div>
                    <select name="lane" id="board-filter-lane" class="form-select form-select-sm w-auto" aria-label="Swimlane">
                        <?php foreach (['none' => 'No swimlanes', 'assignee' => 'By assignee', 'epic' => 'By epic'] as $v => $l): ?>
                            <option value="<?= e($v) ?>" <?= $board['lane'] === $v ? 'selected' : '' ?>><?= e($l) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="assignee" id="board-filter-assignee" class="form-select form-select-sm w-auto" aria-label="Assignee">
                        <option value="">Anyone</option><option value="me" <?= $filters['assignee'] === 'me' ? 'selected' : '' ?>>Me</option><option value="none" <?= $filters['assignee'] === 'none' ? 'selected' : '' ?>>Unassigned</option>
                        <?php foreach ($members as $m): ?><option value="<?= (int) $m['member_id'] ?>" <?= $filters['assignee'] === (string) $m['member_id'] ? 'selected' : '' ?>><?= e($m['display_name']) ?><?= $m['member_kind'] === 'agent' ? ' (agent)' : '' ?></option><?php endforeach; ?>
                    </select>
                    <?php if ($epics !== []): ?>
                    <select name="epic" id="board-filter-epic" class="form-select form-select-sm w-auto" aria-label="Epic">
                        <option value="">Any epic</option>
                        <?php foreach ($epics as $ep): ?><option value="<?= (int) $ep['epic_id'] ?>" <?= (int) ($filters['epic'] ?? 0) === (int) $ep['epic_id'] ? 'selected' : '' ?>><?= e($ep['name']) ?></option><?php endforeach; ?>
                    </select>
                    <?php endif; ?>
                    <select name="type" id="board-filter-type" class="form-select form-select-sm w-auto" aria-label="Type">
                        <option value="">Any type</option>
                        <?php foreach (['story' => 'Story', 'task' => 'Task', 'bug' => 'Bug'] as $v => $l): ?><option value="<?= e($v) ?>" <?= $filters['type'] === $v ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
                    </select>
                </form>
                <?php if ($canConfigure): ?>
                <a href="/board/form?key=<?= e($key) ?>" id="board-settings-btn" class="btn btn-light-brand" hx-get="/board/form?key=<?= e($key) ?>" hx-target="#page-content" hx-push-url="/board/form?key=<?= e($key) ?>"><i class="feather-sliders me-2"></i><span>Board settings</span></a>
                <?php endif; ?>
                <?php if ($canWork && $p['status'] !== 'archived'): ?>
                <a href="/issues/new?project=<?= e($key) ?>" id="board-new-issue-btn" class="btn btn-primary" hx-get="/issues/new?project=<?= e($key) ?>" hx-target="#page-content" hx-push-url="/issues/new?project=<?= e($key) ?>"><i class="feather-plus me-2"></i><span>New issue</span></a>
                <?php endif; ?>
            </div>
        </div>
        <div class="d-md-none d-flex align-items-center"><a href="javascript:void(0)" class="page-header-right-open-toggle"><i class="feather-align-right fs-20"></i></a></div>
    </div>
</div>
<div class="main-content" id="board-content">
    <div class="card border-top-0" id="board-card">
        <?= view('projects/partials/tabs.php', ['project' => $p, 'tab' => 'board']) ?>
        <div class="card-body">
            <div class="d-flex flex-wrap align-items-center gap-3 mb-3 fs-12" id="board-summary">
                <?php if ($board['mode'] === 'kanban'): ?>
                    <span class="fw-semibold">All open work</span><span class="text-muted">and the last <?= BOARD_DONE_DAYS ?> days of done · <a href="<?= e($base) ?>/issues?category=done" hx-get="<?= e($base) ?>/issues?category=done" hx-target="#page-content" hx-push-url="<?= e($base) ?>/issues?category=done">Done older than <?= BOARD_DONE_DAYS ?> days</a></span>
                <?php elseif ($s !== null): ?>
                    <span class="fw-semibold" id="board-sprint-name"><a href="/sprints/<?= (int) $s['sprint_id'] ?>" hx-get="/sprints/<?= (int) $s['sprint_id'] ?>" hx-target="#page-content" hx-push-url="/sprints/<?= (int) $s['sprint_id'] ?>"><?= e($s['name']) ?></a></span>
                    <?php if (($s['goal'] ?? '') !== ''): ?><span class="text-muted"><?= e($s['goal']) ?></span><?php endif; ?>
                    <?php if ($daysLeft !== null): ?><span class="badge bg-soft-<?= $daysLeft < 0 ? 'danger' : ($daysLeft <= 2 ? 'warning' : 'info') ?> text-<?= $daysLeft < 0 ? 'danger' : ($daysLeft <= 2 ? 'warning' : 'info') ?>"><?= $daysLeft < 0 ? abs($daysLeft) . ' days over' : $daysLeft . ' days left' ?></span><?php endif; ?>
                    <span class="text-muted"><?= e((string) (float) $s['points_done']) ?> / <?= e((string) (float) $s['points']) ?> points done</span>
                <?php else: ?>
                    <span class="text-muted" id="board-no-sprint">No sprint is running — start one from the <a href="<?= e($base) ?>/backlog" hx-get="<?= e($base) ?>/backlog" hx-target="#page-content" hx-push-url="<?= e($base) ?>/backlog">backlog</a>.</span>
                <?php endif; ?>
                <span class="text-muted ms-auto" id="board-count"><?= (int) $board['total'] ?> card<?= (int) $board['total'] === 1 ? '' : 's' ?></span>
            </div>
            <?= view('projects/partials/board-columns.php', ['project' => $p, 'board' => $board, 'filters' => $filters, 'canWork' => $canWork]) ?>
        </div>
    </div>
</div>
<script src="/assets/js/board.js"></script>
<script>if (window.bindBoard) { window.bindBoard(); }</script>
