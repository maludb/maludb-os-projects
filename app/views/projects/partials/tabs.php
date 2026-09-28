<?php /** The project's tab strip — each tab a URL. Data: project, tab */
$key = (string) $project['key']; $base = '/projects/' . $key;
$labels = ['overview' => 'Overview', 'backlog' => 'Backlog', 'board' => 'Board', 'sprints' => 'Sprints', 'epics' => 'Epics', 'issues' => 'Issues', 'reports' => 'Reports', 'settings' => 'Settings'];
?>
<div class="card-header p-0">
    <ul class="nav nav-tabs flex-wrap w-100 text-center" role="tablist" id="project-view-tabs">
        <?php foreach ($labels as $t => $label): if ($t === 'settings' && !$project['i_administer']) { continue; }
            $url = $t === 'overview' ? $base : $base . '/' . $t; ?>
            <li class="nav-item flex-fill border-top" role="presentation">
                <a href="<?= e($url) ?>" class="nav-link <?= $t === $tab ? 'active' : '' ?>" id="project-view-tab-<?= e($t) ?>"
                   hx-get="<?= e($url) ?>" hx-target="#page-content" hx-swap="innerHTML" hx-push-url="<?= e($url) ?>"><?= e($label) ?></a>
            </li>
        <?php endforeach; ?>
    </ul>
</div>
