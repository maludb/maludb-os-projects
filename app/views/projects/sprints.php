<?php /** The sprints tab. Data: project, sprints, can, tz, listHtml */
$p = $project; $key = (string) $p['key']; $base = '/projects/' . $key; $archived = $p['status'] === 'archived';
?>
<div class="page-header" id="sprints-list-header">
    <div class="page-header-left d-flex align-items-center">
        <div class="page-header-title"><h5 class="m-b-10"><span class="badge bg-soft-secondary text-dark me-2"><?= e($key) ?></span><?= e($p['name']) ?> <span class="text-muted fw-normal">· Sprints</span></h5></div>
        <ul class="breadcrumb">
            <li class="breadcrumb-item"><a href="/projects" hx-get="/projects" hx-target="#page-content" hx-push-url="/projects">Projects</a></li>
            <li class="breadcrumb-item"><a href="<?= e($base) ?>" hx-get="<?= e($base) ?>" hx-target="#page-content" hx-push-url="<?= e($base) ?>"><?= e($key) ?></a></li>
            <li class="breadcrumb-item">Sprints</li>
        </ul>
    </div>
    <div class="page-header-right ms-auto">
        <div class="page-header-right-items">
            <div class="d-flex d-md-none"><a href="javascript:void(0)" class="page-header-right-close-toggle"><i class="feather-arrow-left me-2"></i><span>Back</span></a></div>
            <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                <?php if ($can['plan'] && !$archived): $u = '/sprints/new?project=' . $key; ?>
                <a href="<?= e($u) ?>" id="sprints-list-new-btn" class="btn btn-primary" hx-get="<?= e($u) ?>" hx-target="#page-content" hx-push-url="<?= e($u) ?>"><i class="feather-plus me-2"></i><span>New sprint</span></a>
                <?php endif; ?>
            </div>
        </div>
        <div class="d-md-none d-flex align-items-center"><a href="javascript:void(0)" class="page-header-right-open-toggle"><i class="feather-align-right fs-20"></i></a></div>
    </div>
</div>
<div class="main-content" id="sprints-list-content">
    <div class="card border-top-0" id="project-view-tabs-card">
        <?= view('projects/partials/tabs.php', ['project' => $p, 'tab' => 'sprints']) ?>
        <div class="card-body p-0"><?= $listHtml ?></div>
    </div>
</div>
