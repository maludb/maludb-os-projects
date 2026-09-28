<?php /** Data: project, tab, activity, tz */
$p = $project; $id = (int) $p['project_id']; $key = (string) $p['key']; $base = '/projects/' . $key;
$archived = $p['status'] === 'archived';
?>
<div class="page-header" id="project-view-header">
    <div class="page-header-left d-flex align-items-center">
        <div class="page-header-title"><h5 class="m-b-10"><span class="badge bg-soft-secondary text-dark me-2"><?= e($key) ?></span><?= e($p['name']) ?>
            <?php if ($p['visibility'] === 'private'): ?><span class="badge bg-soft-warning text-warning ms-2" id="project-view-visibility"><i class="feather-lock me-1"></i>Private</span><?php endif; ?>
            <?php if ($archived): ?><span class="badge bg-soft-secondary text-secondary ms-2" id="project-view-status">Archived</span><?php endif; ?></h5></div>
        <ul class="breadcrumb">
            <li class="breadcrumb-item"><a href="/projects" hx-get="/projects" hx-target="#page-content" hx-push-url="/projects">Projects</a></li>
            <li class="breadcrumb-item"><?= e($key) ?></li>
        </ul>
    </div>
    <div class="page-header-right ms-auto">
        <div class="page-header-right-items">
            <div class="d-flex d-md-none"><a href="javascript:void(0)" class="page-header-right-close-toggle"><i class="feather-arrow-left me-2"></i><span>Back</span></a></div>
            <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                <?php if ($p['i_administer']): ?>
                <a href="<?= e($base) ?>/edit" id="project-view-edit-btn" class="btn btn-light-brand" hx-get="<?= e($base) ?>/edit" hx-target="#page-content" hx-push-url="<?= e($base) ?>/edit"><i class="feather-edit-3 me-2"></i><span>Edit</span></a>
                <?php endif; ?>
                <?php if ($p['i_can_work'] && !$archived): ?>
                <a href="/issues/new?project=<?= e($key) ?>" id="project-view-new-issue-btn" class="btn btn-primary" hx-get="/issues/new?project=<?= e($key) ?>" hx-target="#page-content" hx-push-url="/issues/new?project=<?= e($key) ?>"><i class="feather-plus me-2"></i><span>New issue</span></a>
                <?php endif; ?>
            </div>
        </div>
        <div class="d-md-none d-flex align-items-center"><a href="javascript:void(0)" class="page-header-right-open-toggle"><i class="feather-align-right fs-20"></i></a></div>
    </div>
</div>
<div class="main-content" id="project-view-content">
    <div class="card border-top-0" id="project-view-tabs-card">
        <?= view('projects/partials/tabs.php', ['project' => $p, 'tab' => $tab]) ?>
        <div class="card-body" id="project-view-tab-content">
            <?php
            $slices = ['backlog' => 'The backlog comes with slice 2.', 'board' => 'The board comes with slice 3.', 'sprints' => 'Sprints come with slice 2.',
                       'epics' => 'Epics come with slice 4.', 'reports' => 'Reports come with slice 5.', 'settings' => 'Settings come with slice 6.'];
            if ($tab === 'overview') {
                echo view('projects/partials/overview.php', ['project' => $p, 'activity' => $activity, 'tz' => $tz]);
            } else {
                echo '<div class="text-center text-muted py-5" id="project-view-' . e($tab) . '-empty">' . e($slices[$tab] ?? 'Arrives with its slice.') . '</div>';
            }
            ?>
        </div>
    </div>
</div>
