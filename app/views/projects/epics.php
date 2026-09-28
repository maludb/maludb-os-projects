<?php /** The epics tab (screen epics-list). Data: project, resultsHtml, status, canWrite */
$p = $project; $key = (string) $p['key']; $base = '/projects/' . $key; $listUrl = $base . '/epics'; $newUrl = '/epics/new?project=' . $key;
?>
<div class="page-header" id="epics-list-header">
    <div class="page-header-left d-flex align-items-center">
        <div class="page-header-title"><h5 class="m-b-10"><span class="badge bg-soft-secondary text-dark me-2"><?= e($key) ?></span>Epics</h5></div>
        <ul class="breadcrumb">
            <li class="breadcrumb-item"><a href="/projects" hx-get="/projects" hx-target="#page-content" hx-push-url="/projects">Projects</a></li>
            <li class="breadcrumb-item"><a href="<?= e($base) ?>" hx-get="<?= e($base) ?>" hx-target="#page-content" hx-push-url="<?= e($base) ?>"><?= e($key) ?></a></li>
            <li class="breadcrumb-item">Epics</li>
        </ul>
    </div>
    <div class="page-header-right ms-auto">
        <div class="page-header-right-items">
            <div class="d-flex d-md-none"><a href="javascript:void(0)" class="page-header-right-close-toggle"><i class="feather-arrow-left me-2"></i><span>Back</span></a></div>
            <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                <?php if ($canWrite): ?>
                <a href="<?= e($newUrl) ?>" id="epics-list-new-btn" class="btn btn-primary" hx-get="<?= e($newUrl) ?>" hx-target="#page-content" hx-push-url="<?= e($newUrl) ?>"><i class="feather-plus me-2"></i><span>New epic</span></a>
                <?php endif; ?>
            </div>
        </div>
        <div class="d-md-none d-flex align-items-center"><a href="javascript:void(0)" class="page-header-right-open-toggle"><i class="feather-align-right fs-20"></i></a></div>
    </div>
</div>
<div class="main-content" id="epics-list-content">
    <div class="card border-top-0" id="epics-list-tabs-card">
        <?= view('projects/partials/tabs.php', ['project' => $p, 'tab' => 'epics']) ?>
        <div class="card-body">
            <form id="epics-list-filters" class="row g-2 align-items-end mb-3" method="get" action="<?= e($listUrl) ?>"
                  hx-get="<?= e($listUrl) ?>" hx-target="#epics-list-results" hx-swap="outerHTML" hx-trigger="change" hx-push-url="false">
                <div class="col-6 col-md-3"><select name="status" id="epics-list-filter-status" class="form-select" aria-label="Status"><?php foreach (['' => 'Open, done and cancelled', 'open' => 'Open', 'done' => 'Done', 'cancelled' => 'Cancelled'] as $v => $l): ?><option value="<?= $v ?>" <?= $status === $v ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
            </form>
            <?= $resultsHtml ?>
        </div>
    </div>
</div>
