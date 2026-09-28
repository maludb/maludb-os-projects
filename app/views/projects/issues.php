<?php /** The issues tab. Data: project, resultsHtml, filters, sort, options, members, saved, savedId */
$p = $project; $key = (string) $p['key']; $base = '/projects/' . $key; $listUrl = $base . '/issues';
$sel = static fn (array $list, $v): string => in_array((string) $v, array_map('strval', $list), true) ? 'selected' : '';
?>
<div class="page-header" id="issues-list-header">
    <div class="page-header-left d-flex align-items-center">
        <div class="page-header-title"><h5 class="m-b-10"><span class="badge bg-soft-secondary text-dark me-2"><?= e($key) ?></span>Issues</h5></div>
        <ul class="breadcrumb">
            <li class="breadcrumb-item"><a href="/projects" hx-get="/projects" hx-target="#page-content" hx-push-url="/projects">Projects</a></li>
            <li class="breadcrumb-item"><a href="<?= e($base) ?>" hx-get="<?= e($base) ?>" hx-target="#page-content" hx-push-url="<?= e($base) ?>"><?= e($key) ?></a></li>
            <li class="breadcrumb-item">Issues</li>
        </ul>
    </div>
    <div class="page-header-right ms-auto">
        <div class="page-header-right-items">
            <div class="d-flex d-md-none"><a href="javascript:void(0)" class="page-header-right-close-toggle"><i class="feather-arrow-left me-2"></i><span>Back</span></a></div>
            <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                <?php if ($p['i_can_work'] && $p['status'] === 'active'): ?>
                <a href="/issues/new?project=<?= e($key) ?>" id="issues-list-new-btn" class="btn btn-primary" hx-get="/issues/new?project=<?= e($key) ?>" hx-target="#page-content" hx-push-url="/issues/new?project=<?= e($key) ?>"><i class="feather-plus me-2"></i><span>New issue</span></a>
                <?php endif; ?>
            </div>
        </div>
        <div class="d-md-none d-flex align-items-center"><a href="javascript:void(0)" class="page-header-right-open-toggle"><i class="feather-align-right fs-20"></i></a></div>
    </div>
</div>
<div class="main-content" id="issues-list-content">
    <div class="card border-top-0" id="issues-list-tabs-card">
        <?= view('projects/partials/tabs.php', ['project' => $p, 'tab' => 'issues']) ?>
        <div class="card-body">
            <form id="issues-list-filters" class="row g-2 align-items-end mb-3" method="get" action="<?= e($listUrl) ?>"
                  hx-get="<?= e($listUrl) ?>" hx-target="#issues-list-results" hx-swap="outerHTML" hx-trigger="change, keyup changed delay:400ms from:#issues-list-search, search from:#issues-list-search" hx-push-url="false">
                <div class="col-md-3"><div class="input-group"><span class="input-group-text"><i class="feather-search"></i></span><input type="search" name="q" id="issues-list-search" class="form-control" placeholder="Key, summary…" value="<?= e($filters['q']) ?>" aria-label="Search issues"></div></div>
                <div class="col-6 col-md-2"><select name="type[]" id="issues-list-filter-type" class="form-select" aria-label="Type"><option value="">Any type</option><?php foreach (['story' => 'Story', 'task' => 'Task', 'bug' => 'Bug'] as $v => $l): ?><option value="<?= $v ?>" <?= $sel($filters['type'], $v) ?>><?= $l ?></option><?php endforeach; ?></select></div>
                <div class="col-6 col-md-2"><select name="status[]" id="issues-list-filter-status" class="form-select" aria-label="Status"><option value="">Any status</option><?php foreach ($options['statuses'] as $s): ?><option value="<?= (int) $s['status_id'] ?>" <?= $sel($filters['status'], (int) $s['status_id']) ?>><?= e($s['name']) ?></option><?php endforeach; ?></select></div>
                <div class="col-6 col-md-2"><select name="category" id="issues-list-filter-category" class="form-select" aria-label="Category"><?php foreach (['' => 'Open and done', 'open' => 'Open', 'todo' => 'To do', 'in_progress' => 'In progress', 'done' => 'Done'] as $v => $l): ?><option value="<?= $v ?>" <?= $filters['category'] === $v ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
                <div class="col-6 col-md-3"><select name="assignee" id="issues-list-filter-assignee" class="form-select" aria-label="Assignee"><option value="">Anyone</option><option value="me" <?= $filters['assignee'] === 'me' ? 'selected' : '' ?>>Me</option><option value="none" <?= $filters['assignee'] === 'none' ? 'selected' : '' ?>>Unassigned</option><?php foreach ($members as $m): ?><option value="<?= (int) $m['member_id'] ?>" <?= $filters['assignee'] === (string) $m['member_id'] ? 'selected' : '' ?>><?= e($m['display_name']) ?><?= $m['member_kind'] === 'agent' ? ' (agent)' : '' ?></option><?php endforeach; ?></select></div>
                <div class="col-6 col-md-2"><select name="sprint" id="issues-list-filter-sprint" class="form-select" aria-label="Sprint"><option value="">Any sprint</option><option value="backlog" <?= $filters['sprint'] === 'backlog' ? 'selected' : '' ?>>Backlog</option><option value="active" <?= $filters['sprint'] === 'active' ? 'selected' : '' ?>>Active sprint</option><?php foreach ($options['sprints'] as $s): ?><option value="<?= (int) $s['sprint_id'] ?>" <?= $filters['sprint'] === (string) $s['sprint_id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach; ?></select></div>
                <div class="col-6 col-md-2"><select name="epic" id="issues-list-filter-epic" class="form-select" aria-label="Epic"><option value="">Any epic</option><?php foreach ($options['epics'] as $ep): ?><option value="<?= (int) $ep['epic_id'] ?>" <?= (int) ($filters['epic'] ?? 0) === (int) $ep['epic_id'] ? 'selected' : '' ?>><?= e($ep['name']) ?></option><?php endforeach; ?></select></div>
                <div class="col-6 col-md-2"><select name="label" id="issues-list-filter-label" class="form-select" aria-label="Label"><option value="">Any label</option><?php foreach ($options['labels'] as $l): ?><option value="<?= e($l['name']) ?>" <?= $filters['label'] === $l['name'] ? 'selected' : '' ?>><?= e($l['name']) ?></option><?php endforeach; ?></select></div>
                <div class="col-6 col-md-2"><select name="priority[]" id="issues-list-filter-priority" class="form-select" aria-label="Priority"><option value="">Any priority</option><?php foreach (ISSUE_PRIORITIES as $v): ?><option value="<?= $v ?>" <?= $sel($filters['priority'], $v) ?>><?= ucfirst($v) ?></option><?php endforeach; ?></select></div>
                <div class="col-6 col-md-2"><select name="due" id="issues-list-filter-due" class="form-select" aria-label="Due"><?php foreach (['' => 'Any due date', 'overdue' => 'Overdue', 'week' => 'Due this week', 'none' => 'No due date'] as $v => $l): ?><option value="<?= $v ?>" <?= $filters['due'] === $v ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
                <div class="col-6 col-md-2"><select name="sort" id="issues-list-sort" class="form-select" aria-label="Sort"><?php foreach (['rank' => 'By rank', 'updated' => 'Recently updated', 'created' => 'Newest', 'priority' => 'By priority', 'due' => 'By due date', 'key' => 'By key'] as $v => $l): ?><option value="<?= $v ?>" <?= $sort === $v ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
                <div class="col-12 col-md-4 d-flex flex-wrap gap-3 align-items-center">
                    <div class="form-check mb-0"><input type="checkbox" class="form-check-input" name="blocked" value="1" id="issues-list-filter-blocked" <?= !empty($filters['blocked']) ? 'checked' : '' ?>><label class="form-check-label fs-12" for="issues-list-filter-blocked">Blocked</label></div>
                    <div class="form-check mb-0"><input type="checkbox" class="form-check-input" name="subtasks" value="1" id="issues-list-filter-subtasks" <?= !empty($filters['include_subtasks']) ? 'checked' : '' ?>><label class="form-check-label fs-12" for="issues-list-filter-subtasks">Subtasks too</label></div>
                    <?php if ($saved !== []): ?>
                    <select name="filter" id="issues-list-saved" class="form-select form-select-sm w-auto" aria-label="Saved filter"><option value="">Saved filters…</option><?php foreach ($saved as $sf): ?><option value="<?= (int) $sf['saved_filter_id'] ?>" <?= $savedId === (int) $sf['saved_filter_id'] ? 'selected' : '' ?>><?= e($sf['name']) ?><?= $sf['is_shared'] ? ' (shared)' : '' ?></option><?php endforeach; ?></select>
                    <?php endif; ?>
                </div>
            </form>
            <form id="issues-list-save-filter" class="d-flex flex-wrap gap-2 align-items-center mb-3" method="post" action="/filters/save.php" hx-post="/filters/save.php" hx-target="#issues-list-results" hx-swap="outerHTML" hx-include="#issues-list-filters">
                <?= csrf_field() ?>
                <input type="hidden" name="project" value="<?= (int) $p['project_id'] ?>">
                <input type="text" name="name" id="issues-list-save-filter-name" class="form-control form-control-sm w-auto" placeholder="Save this filter as…" maxlength="80" required>
                <div class="form-check mb-0"><input type="checkbox" class="form-check-input" name="shared" value="1" id="issues-list-save-filter-shared"><label class="form-check-label fs-12" for="issues-list-save-filter-shared">Share with the project</label></div>
                <button type="submit" class="btn btn-sm btn-light-brand" id="issues-list-save-filter-btn"><i class="feather-bookmark me-1"></i>Save filter</button>
            </form>
            <?= $resultsHtml ?>
        </div>
    </div>
</div>
