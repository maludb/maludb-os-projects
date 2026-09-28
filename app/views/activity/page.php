<?php /** Activity. Data: rows, page, more, filters, query, tz, resultsHtml, actors, projects */
$actions = ['' => 'Anything', 'issue.' => 'Issues', 'issue.transition' => 'Moves between statuses', 'issue.assign' => 'Assignments', 'comment.' => 'Comments', 'sprint.' => 'Sprints', 'epic.' => 'Epics', 'project.' => 'Projects', 'team.' => 'Team changes', 'worklog.' => 'Worklogs', 'saved_filter.' => 'Saved filters', 'member.' => 'Sign-ons'];
$csv = '/activity?' . http_build_query($query + ['page' => $page, 'format' => 'csv']);
?>
<div class="page-header" id="activity-header">
    <div class="page-header-left d-flex align-items-center">
        <div class="page-header-title"><h5 class="m-b-10">Activity</h5></div>
        <ul class="breadcrumb">
            <li class="breadcrumb-item"><a href="/" hx-get="/" hx-target="#page-content" hx-push-url="/">Home</a></li>
            <li class="breadcrumb-item"><?= $filters['entity'] !== '' ? e(str_replace([':', '_'], [' ', ' '], $filters['entity'])) : ($filters['project'] !== '' ? e($filters['project']) : 'Recent') ?></li>
        </ul>
    </div>
    <div class="page-header-right ms-auto">
        <div class="page-header-right-items">
            <div class="d-flex d-md-none"><a href="javascript:void(0)" class="page-header-right-close-toggle"><i class="feather-arrow-left me-2"></i><span>Back</span></a></div>
            <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                <a href="<?= e($csv) ?>" id="activity-export-btn" class="btn btn-light-brand" download><i class="feather-download me-2"></i><span>CSV</span></a>
            </div>
        </div>
        <div class="d-md-none d-flex align-items-center"><a href="javascript:void(0)" class="page-header-right-open-toggle"><i class="feather-align-right fs-20"></i></a></div>
    </div>
</div>
<div class="main-content" id="activity-content">
    <div class="card stretch stretch-full" id="activity-card">
        <div class="card-header">
            <h5 class="card-title"><?= $filters['entity'] !== '' ? 'History' : 'What happened' ?></h5>
            <form id="activity-filters" class="d-flex flex-wrap gap-2" method="get" action="/activity" hx-get="/activity" hx-target="#activity-results" hx-swap="outerHTML" hx-trigger="change" hx-push-url="true">
                <?php if ($filters['entity'] !== ''): ?><input type="hidden" name="entity" value="<?= e($filters['entity']) ?>"><?php endif; ?>
                <select name="project" id="activity-filter-project" class="form-select form-select-sm w-auto" aria-label="Project"><option value="">Every project</option><?php foreach ($projects as $p): ?><option value="<?= e($p['key']) ?>" <?= $filters['project'] === $p['key'] ? 'selected' : '' ?>><?= e($p['key']) ?></option><?php endforeach; ?></select>
                <select name="member" id="activity-filter-member" class="form-select form-select-sm w-auto" aria-label="Who"><option value="">Anyone</option><?php foreach ($actors as $a): ?><option value="<?= (int) $a['member_id'] ?>" <?= $filters['member'] === (int) $a['member_id'] ? 'selected' : '' ?>><?= e($a['display_name']) ?></option><?php endforeach; ?></select>
                <select name="action" id="activity-filter-action" class="form-select form-select-sm w-auto" aria-label="What"><?php foreach ($actions as $v => $l): ?><option value="<?= e($v) ?>" <?= $filters['action'] === $v ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select>
                <select name="since" id="activity-filter-since" class="form-select form-select-sm w-auto" aria-label="Period"><option value="">All time</option><?php foreach ([1 => 'Today', 7 => 'Last 7 days', 30 => 'Last 30 days', 90 => 'Last 90 days'] as $v => $l): ?><option value="<?= $v ?>" <?= $filters['since'] === $v ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select>
            </form>
        </div>
        <?= $resultsHtml ?>
    </div>
</div>
