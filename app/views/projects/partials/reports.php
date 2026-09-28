<?php /** The Reports tab as a page: the project's header and tab strip, then the report body. Data: project, report, bodyHtml */
$p = $project; $key = (string) $p['key']; $base = '/projects/' . $key; $id = (int) $p['project_id'];
?>
<div class="page-header" id="project-view-header">
    <div class="page-header-left d-flex align-items-center">
        <div class="page-header-title"><h5 class="m-b-10"><span class="badge bg-soft-secondary text-dark me-2"><?= e($key) ?></span><?= e($p['name']) ?>
            <?php if ($p['visibility'] === 'private'): ?><span class="badge bg-soft-warning text-warning ms-2" id="project-view-visibility"><i class="feather-lock me-1"></i>Private</span><?php endif; ?></h5></div>
        <ul class="breadcrumb">
            <li class="breadcrumb-item"><a href="/projects" hx-get="/projects" hx-target="#page-content" hx-push-url="/projects">Projects</a></li>
            <li class="breadcrumb-item"><a href="<?= e($base) ?>" hx-get="<?= e($base) ?>" hx-target="#page-content" hx-push-url="<?= e($base) ?>"><?= e($key) ?></a></li>
            <li class="breadcrumb-item">Reports</li>
        </ul>
    </div>
    <div class="page-header-right ms-auto">
        <div class="page-header-right-items">
            <div class="d-flex d-md-none"><a href="javascript:void(0)" class="page-header-right-close-toggle"><i class="feather-arrow-left me-2"></i><span>Back</span></a></div>
            <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                <?php if ($p['i_can_work'] && $p['status'] !== 'archived'): ?>
                <a href="/issues/new?project=<?= e($key) ?>" id="project-view-new-issue-btn" class="btn btn-primary" hx-get="/issues/new?project=<?= e($key) ?>" hx-target="#page-content" hx-push-url="/issues/new?project=<?= e($key) ?>"><i class="feather-plus me-2"></i><span>New issue</span></a>
                <?php endif; ?>
            </div>
        </div>
        <div class="d-md-none d-flex align-items-center"><a href="javascript:void(0)" class="page-header-right-open-toggle"><i class="feather-align-right fs-20"></i></a></div>
    </div>
</div>
<div class="main-content" id="project-view-content">
    <div class="card border-top-0" id="project-view-tabs-card">
        <?= view('projects/partials/tabs.php', ['project' => $p, 'tab' => 'reports']) ?>
        <div class="card-body" id="project-view-tab-content">
            <?= $bodyHtml ?>
        </div>
    </div>
</div>
