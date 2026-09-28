<?php /** The sprint page. Data: sprint, project, issues, burndown, events, future, anyActive, can, tz */
$s = $sprint; $sid = (int) $s['sprint_id']; $key = (string) $project['key']; $base = '/projects/' . $key; $url = '/sprints/' . $sid;
$tone = sprint_state_tone((string) $s['state']); $archived = $project['status'] === 'archived';
?>
<div class="page-header" id="sprint-view-header">
    <div class="page-header-left d-flex align-items-center">
        <div class="page-header-title"><h5 class="m-b-10"><?= e($s['name']) ?> <span class="badge bg-soft-<?= e($tone) ?> text-<?= e($tone) ?> ms-2" id="sprint-view-state"><?= e(ucfirst((string) $s['state'])) ?></span></h5></div>
        <ul class="breadcrumb">
            <li class="breadcrumb-item"><a href="/projects" hx-get="/projects" hx-target="#page-content" hx-push-url="/projects">Projects</a></li>
            <li class="breadcrumb-item"><a href="<?= e($base) ?>" hx-get="<?= e($base) ?>" hx-target="#page-content" hx-push-url="<?= e($base) ?>"><?= e($key) ?></a></li>
            <li class="breadcrumb-item"><a href="<?= e($base) ?>/sprints" hx-get="<?= e($base) ?>/sprints" hx-target="#page-content" hx-push-url="<?= e($base) ?>/sprints">Sprints</a></li>
            <li class="breadcrumb-item"><?= e($s['name']) ?></li>
        </ul>
    </div>
    <div class="page-header-right ms-auto">
        <div class="page-header-right-items">
            <div class="d-flex d-md-none"><a href="javascript:void(0)" class="page-header-right-close-toggle"><i class="feather-arrow-left me-2"></i><span>Back</span></a></div>
            <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                <a href="<?= e($base) ?>/backlog" id="sprint-view-backlog-btn" class="btn btn-light-brand" hx-get="<?= e($base) ?>/backlog" hx-target="#page-content" hx-push-url="<?= e($base) ?>/backlog"><i class="feather-list me-2"></i><span>Backlog</span></a>
                <?php if ($can['plan'] && $s['state'] !== 'closed' && !$archived): ?>
                <a href="<?= e($url) ?>/edit" id="sprint-view-edit-btn" class="btn btn-light-brand" hx-get="<?= e($url) ?>/edit" hx-target="#page-content" hx-push-url="<?= e($url) ?>/edit"><i class="feather-edit-3 me-2"></i><span>Edit</span></a>
                <?php endif; ?>
                <?php if ($can['run'] && $s['state'] === 'future' && !$anyActive && !$archived): ?>
                <form method="post" action="/sprints/start.php" hx-post="/sprints/start.php" hx-swap="none" class="d-inline"><?= csrf_field() ?><input type="hidden" name="sprint" value="<?= $sid ?>"><button type="submit" class="btn btn-primary" id="sprint-view-start-btn"><i class="feather-play me-2"></i><span>Start sprint</span></button></form>
                <?php endif; ?>
            </div>
        </div>
        <div class="d-md-none d-flex align-items-center"><a href="javascript:void(0)" class="page-header-right-open-toggle"><i class="feather-align-right fs-20"></i></a></div>
    </div>
</div>
<div class="main-content" id="sprint-view-content">
    <?= view('sprints/partials/body.php', ['sprint' => $s, 'project' => $project, 'issues' => $issues, 'burndown' => $burndown, 'events' => $events, 'future' => $future, 'anyActive' => $anyActive, 'can' => $can, 'tz' => $tz]) ?>
</div>
