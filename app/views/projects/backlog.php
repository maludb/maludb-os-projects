<?php /** The backlog tab. Data: project, boxes, rows, filters, can, settings, targets, tz, options, members */
$p = $project; $key = (string) $p['key']; $base = '/projects/' . $key; $archived = $p['status'] === 'archived';
$memberOptions = []; foreach ($members as $m) { $memberOptions[(int) $m['member_id']] = $m['display_name'] . ($m['member_kind'] === 'agent' ? ' (agent)' : ''); }
?>
<div class="page-header" id="backlog-header">
    <div class="page-header-left d-flex align-items-center">
        <div class="page-header-title"><h5 class="m-b-10"><span class="badge bg-soft-secondary text-dark me-2"><?= e($key) ?></span><?= e($p['name']) ?> <span class="text-muted fw-normal">· Backlog</span></h5></div>
        <ul class="breadcrumb">
            <li class="breadcrumb-item"><a href="/projects" hx-get="/projects" hx-target="#page-content" hx-push-url="/projects">Projects</a></li>
            <li class="breadcrumb-item"><a href="<?= e($base) ?>" hx-get="<?= e($base) ?>" hx-target="#page-content" hx-push-url="<?= e($base) ?>"><?= e($key) ?></a></li>
            <li class="breadcrumb-item">Backlog</li>
        </ul>
    </div>
    <div class="page-header-right ms-auto">
        <div class="page-header-right-items">
            <div class="d-flex d-md-none"><a href="javascript:void(0)" class="page-header-right-close-toggle"><i class="feather-arrow-left me-2"></i><span>Back</span></a></div>
            <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                <?php if ($can['plan'] && !$archived): $u = '/sprints/new?project=' . $key; ?>
                <a href="<?= e($u) ?>" id="backlog-new-sprint-btn" class="btn btn-light-brand" hx-get="<?= e($u) ?>" hx-target="#page-content" hx-push-url="<?= e($u) ?>"><i class="feather-flag me-2"></i><span>Create sprint</span></a>
                <?php endif; ?>
                <?php if ($p['i_can_work'] && !$archived): $u = '/issues/new?project=' . $key; ?>
                <a href="<?= e($u) ?>" id="backlog-new-issue-btn" class="btn btn-primary" hx-get="<?= e($u) ?>" hx-target="#page-content" hx-push-url="<?= e($u) ?>"><i class="feather-plus me-2"></i><span>New issue</span></a>
                <?php endif; ?>
            </div>
        </div>
        <div class="d-md-none d-flex align-items-center"><a href="javascript:void(0)" class="page-header-right-open-toggle"><i class="feather-align-right fs-20"></i></a></div>
    </div>
</div>
<div class="main-content" id="backlog-content">
    <div class="card border-top-0" id="project-view-tabs-card">
        <?= view('projects/partials/tabs.php', ['project' => $p, 'tab' => 'backlog']) ?>
        <div class="card-body" id="backlog">
            <?= view('projects/partials/backlog-boxes.php', ['project' => $p, 'boxes' => $boxes, 'can' => $can, 'settings' => $settings, 'targets' => $targets, 'tz' => $tz]) ?>
            <form id="backlog-filters" class="row g-2 align-items-end mb-3" hx-get="<?= e($base) ?>/backlog" hx-target="#backlog-list" hx-swap="outerHTML" hx-trigger="change, keyup changed delay:300ms from:#backlog-filter-q, search from:#backlog-filter-q" hx-push-url="true">
                <div class="col-12 col-md-4"><label for="backlog-filter-q" class="form-label fs-12 text-muted mb-1">Search</label><input type="search" name="q" id="backlog-filter-q" class="form-control form-control-sm" value="<?= e($filters['q']) ?>" placeholder="Key or summary" autocomplete="off"></div>
                <div class="col-6 col-md-2"><label for="backlog-filter-epic" class="form-label fs-12 text-muted mb-1">Epic</label><select name="epic" id="backlog-filter-epic" class="form-select form-select-sm"><option value="">Any epic</option><?php foreach ($options['epics'] as $ep): ?><option value="<?= (int) $ep['epic_id'] ?>" <?= (int) ($filters['epic'] ?? 0) === (int) $ep['epic_id'] ? 'selected' : '' ?>><?= e($ep['name']) ?></option><?php endforeach; ?></select></div>
                <div class="col-6 col-md-2"><label for="backlog-filter-assignee" class="form-label fs-12 text-muted mb-1">Assignee</label><select name="assignee" id="backlog-filter-assignee" class="form-select form-select-sm"><option value="">Anyone</option><option value="me" <?= $filters['assignee'] === 'me' ? 'selected' : '' ?>>Me</option><option value="none" <?= $filters['assignee'] === 'none' ? 'selected' : '' ?>>Unassigned</option><?php foreach ($memberOptions as $mid => $mname): ?><option value="<?= $mid ?>" <?= $filters['assignee'] === (string) $mid ? 'selected' : '' ?>><?= e($mname) ?></option><?php endforeach; ?></select></div>
                <div class="col-6 col-md-2"><label for="backlog-filter-type" class="form-label fs-12 text-muted mb-1">Type</label><select name="type" id="backlog-filter-type" class="form-select form-select-sm"><option value="">Any type</option><?php foreach (ISSUE_TYPES as $t): if ($t === 'subtask') { continue; } ?><option value="<?= e($t) ?>" <?= $filters['type'] === $t ? 'selected' : '' ?>><?= e(ucfirst($t)) ?></option><?php endforeach; ?></select></div>
                <div class="col-6 col-md-2"><div class="form-check mb-1"><input type="checkbox" name="unestimated" value="1" id="backlog-filter-unestimated" class="form-check-input" <?= $filters['unestimated'] ? 'checked' : '' ?>><label for="backlog-filter-unestimated" class="form-check-label fs-12">Unestimated only</label></div></div>
            </form>
            <?= view('projects/partials/backlog-list.php', ['project' => $p, 'rows' => $rows, 'filters' => $filters, 'can' => $can, 'settings' => $settings, 'targets' => $targets, 'tz' => $tz]) ?>
        </div>
    </div>
</div>
