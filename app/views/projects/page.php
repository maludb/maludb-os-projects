<?php /** Data: resultsHtml, filters, sort, departments, canCreate */ ?>
<div class="page-header" id="projects-list-header">
    <div class="page-header-left d-flex align-items-center">
        <div class="page-header-title"><h5 class="m-b-10">Projects</h5></div>
        <ul class="breadcrumb">
            <li class="breadcrumb-item"><a href="/" hx-get="/" hx-target="#page-content" hx-push-url="/">Home</a></li>
            <li class="breadcrumb-item">Projects</li>
        </ul>
    </div>
    <div class="page-header-right ms-auto">
        <div class="page-header-right-items">
            <div class="d-flex d-md-none"><a href="javascript:void(0)" class="page-header-right-close-toggle"><i class="feather-arrow-left me-2"></i><span>Back</span></a></div>
            <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                <form id="projects-list-filters" class="d-flex align-items-center gap-2 flex-wrap" method="get" action="/projects"
                      hx-get="/projects" hx-target="#projects-list-results" hx-swap="outerHTML" hx-trigger="change, keyup changed delay:400ms from:#projects-list-search, search from:#projects-list-search"
                      hx-push-url="false">
                    <div class="input-group">
                        <span class="input-group-text"><i class="feather-search"></i></span>
                        <input type="search" name="q" id="projects-list-search" class="form-control" placeholder="Key, name…" value="<?= e($filters['q']) ?>" aria-label="Search projects" />
                    </div>
                    <select name="department" id="projects-list-filter-department" class="form-select" aria-label="Department">
                        <option value="">Any department</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?= (int) $d['department_id'] ?>" <?= (int) ($filters['department'] ?? 0) === (int) $d['department_id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="status" id="projects-list-filter-status" class="form-select" aria-label="Status">
                        <?php foreach (['active' => 'Active', 'archived' => 'Archived', 'all' => 'All'] as $v => $l): ?>
                            <option value="<?= e($v) ?>" <?= $filters['status'] === $v ? 'selected' : '' ?>><?= e($l) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-check form-check-inline mb-0">
                        <input type="checkbox" class="form-check-input" name="mine" value="1" id="projects-list-filter-mine" <?= !empty($filters['mine']) ? 'checked' : '' ?>>
                        <label class="form-check-label fs-12" for="projects-list-filter-mine">Mine</label>
                    </div>
                    <select name="sort" id="projects-list-sort" class="form-select" aria-label="Sort">
                        <?php foreach (['name' => 'By name', 'key' => 'By key', 'updated' => 'Recently updated'] as $v => $l): ?>
                            <option value="<?= e($v) ?>" <?= $sort === $v ? 'selected' : '' ?>><?= e($l) ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
                <?php if ($canCreate): ?>
                <a href="/projects/new" id="projects-list-new-btn" class="btn btn-primary" hx-get="/projects/new" hx-target="#page-content" hx-swap="innerHTML" hx-push-url="/projects/new">
                    <i class="feather-plus me-2"></i><span>New project</span>
                </a>
                <?php endif; ?>
            </div>
        </div>
        <div class="d-md-none d-flex align-items-center"><a href="javascript:void(0)" class="page-header-right-open-toggle"><i class="feather-align-right fs-20"></i></a></div>
    </div>
</div>
<div class="main-content" id="projects-list-content">
    <?= $resultsHtml ?>
</div>
