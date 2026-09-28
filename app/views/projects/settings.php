<?php /** The project's settings page: header, the project's tab strip on "Settings", the settings tabs and the chosen tab's body. Data: project, tab, body */
$p = $project; $key = (string) $p['key']; $base = '/projects/' . $key; $id = (int) $p['project_id'];
?>
<div class="page-header" id="project-settings-header">
    <div class="page-header-left d-flex align-items-center">
        <div class="page-header-title"><h5 class="m-b-10"><span class="badge bg-soft-secondary text-dark me-2"><?= e($key) ?></span>Settings
            <?php if ($p['status'] === 'archived'): ?><span class="badge bg-soft-secondary text-secondary ms-2" id="project-settings-archived">Archived</span><?php endif; ?></h5></div>
        <ul class="breadcrumb">
            <li class="breadcrumb-item"><a href="/projects" hx-get="/projects" hx-target="#page-content" hx-push-url="/projects">Projects</a></li>
            <li class="breadcrumb-item"><a href="<?= e($base) ?>" hx-get="<?= e($base) ?>" hx-target="#page-content" hx-push-url="<?= e($base) ?>"><?= e($key) ?></a></li>
            <li class="breadcrumb-item">Settings</li>
        </ul>
    </div>
</div>
<div class="main-content" id="project-settings-content">
    <div class="card border-top-0" id="project-settings-tabs-card">
        <?= view('projects/partials/tabs.php', ['project' => $p, 'tab' => 'settings']) ?>
        <div class="card-body" id="project-settings-tab-content">
            <ul class="nav nav-pills flex-wrap gap-1 mb-3" id="project-settings-tabs" role="tablist">
                <?php foreach (['general' => 'General', 'workflow' => 'Workflow', 'board' => 'Board', 'team' => 'Team', 'labels' => 'Labels'] as $t => $label): $url = $base . '/settings?tab=' . $t; ?>
                <li class="nav-item" role="presentation"><a href="<?= e($url) ?>" class="nav-link py-1 px-3 <?= $t === $tab ? 'active' : '' ?>" id="project-settings-tab-<?= e($t) ?>"
                    hx-get="<?= e($url) ?>" hx-target="#project-settings-body" hx-swap="innerHTML" hx-push-url="<?= e($url) ?>"><?= e($label) ?></a></li>
                <?php endforeach; ?>
            </ul>
            <div id="project-settings-body" data-screen="project-settings" data-entity="project" data-record-id="<?= $id ?>"><?= $body ?></div>
        </div>
    </div>
</div>
