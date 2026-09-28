<?php /** Data: me, issues, projects, sprints, tz */
require_once APP_ROOT . '/app/views/shared/chart.php';
$link = static fn (string $url, string $text, string $cls = ''): string => '<a href="' . e($url) . '" class="' . e($cls) . '" hx-get="' . e($url) . '" hx-target="#page-content" hx-push-url="' . e($url) . '">' . $text . '</a>';
?>
<div class="page-header" id="home-header">
    <div class="page-header-left d-flex align-items-center">
        <div class="page-header-title"><h5 class="m-b-10">Dashboard</h5></div>
        <ul class="breadcrumb">
            <li class="breadcrumb-item">Home</li>
            <li class="breadcrumb-item"><?= e($me['display_name'] ?? '') ?></li>
        </ul>
    </div>
    <div class="page-header-right ms-auto">
        <div class="page-header-right-items">
            <div class="d-flex d-md-none"><a href="javascript:void(0)" class="page-header-right-close-toggle"><i class="feather-arrow-left me-2"></i><span>Back</span></a></div>
            <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                <?= $link('/issues/new', '<i class="feather-plus me-2"></i><span>New issue</span>', 'btn btn-primary') ?>
            </div>
        </div>
        <div class="d-md-none d-flex align-items-center"><a href="javascript:void(0)" class="page-header-right-open-toggle"><i class="feather-align-right fs-20"></i></a></div>
    </div>
</div>
<div class="main-content" id="home-content">
    <div class="row">
        <div class="col-xxl-5 col-lg-6">
            <div class="card stretch stretch-full" id="home-my-issues">
                <div class="card-header"><h5 class="card-title">My open issues</h5><?= $link('/my-work', 'All', 'fs-12') ?></div>
                <div class="card-body custom-card-action p-0">
                    <?php if ($issues === []): ?>
                        <p class="text-muted p-4 mb-0" id="home-my-issues-empty">No issue is assigned to you.</p>
                    <?php else: ?>
                    <div class="table-responsive"><table class="table table-hover mb-0"><tbody>
                        <?php foreach ($issues as $i): ?>
                        <tr id="home-issue-<?= (int) $i['issue_id'] ?>">
                            <td class="text-nowrap"><?= $link('/issues/' . rawurlencode($i['key']), '<span class="fw-semibold">' . e($i['key']) . '</span>') ?></td>
                            <td><?= e($i['summary']) ?><div class="fs-12 text-muted"><?= e($i['project_key']) ?> · <?= e($i['status']) ?><?= $i['sprint_name'] ? ' · ' . e($i['sprint_name']) : '' ?></div></td>
                            <td class="text-end text-nowrap">
                                <?php if ((int) $i['open_blockers'] > 0): ?><span class="badge bg-soft-danger text-danger">blocked</span><?php endif; ?>
                                <?php if ($i['due_date']): ?><span class="fs-12 <?= $i['due_date'] < date('Y-m-d') ? 'text-danger' : 'text-muted' ?>"><?= e(format_date($i['due_date'])) ?></span><?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody></table></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-xxl-7 col-lg-6">
            <div class="card stretch stretch-full" id="home-my-projects">
                <div class="card-header"><h5 class="card-title">My projects</h5><?= $link('/projects/', 'All', 'fs-12') ?></div>
                <div class="card-body">
                    <?php if ($projects === []): ?>
                        <p class="text-muted mb-0" id="home-my-projects-empty">You are on no project yet — open one from <?= $link('/projects/', 'Projects') ?>.</p>
                    <?php else: ?>
                    <div class="row g-3">
                        <?php foreach ($projects as $p): ?>
                        <div class="col-md-6" id="home-project-<?= (int) $p['project_id'] ?>">
                            <div class="card border-start border-3 border-primary mb-0 h-100"><div class="card-body py-3">
                                <div class="d-flex align-items-center gap-2"><?= $link('/projects/' . rawurlencode($p['key']), '<span class="fw-bold text-dark">' . e($p['name']) . '</span>') ?>
                                    <span class="badge bg-soft-secondary text-dark"><?= e($p['key']) ?></span>
                                    <?php if ($p['visibility'] === 'private'): ?><span class="badge bg-soft-warning text-warning">private</span><?php endif; ?></div>
                                <div class="fs-12 text-muted mt-1"><?= $p['active_sprint_name'] ? e($p['active_sprint_name']) : 'No sprint running' ?> · <?= (int) $p['open_issues'] ?> open</div>
                            </div></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-12">
            <div class="card" id="home-active-sprints">
                <div class="card-header"><h5 class="card-title">Active sprints</h5></div>
                <div class="card-body custom-card-action p-0">
                    <?php if ($sprints === []): ?>
                        <p class="text-muted p-4 mb-0" id="home-active-sprints-empty">No sprint is running.</p>
                    <?php else: ?>
                    <div class="table-responsive"><table class="table table-hover mb-0">
                        <thead class="thead-light"><tr><th>Project</th><th>Sprint</th><th class="text-end">Days left</th><th class="text-end">Points</th><th>Burndown</th></tr></thead>
                        <tbody>
                        <?php foreach ($sprints as $s): ?>
                        <tr id="home-sprint-<?= (int) $s['sprint_id'] ?>">
                            <td><?= $link('/projects/' . rawurlencode($s['project_key']) . '/board', '<span class="fw-semibold">' . e($s['project_key']) . '</span>') ?></td>
                            <td><?= $link('/sprints/' . (int) $s['sprint_id'], e($s['name'])) ?><?= $s['goal'] ? '<div class="fs-12 text-muted text-truncate-1-line">' . e($s['goal']) . '</div>' : '' ?></td>
                            <td class="text-end"><?= $s['end_date'] ? (int) $s['days_left'] : '—' ?></td>
                            <td class="text-end text-nowrap"><?= e(rtrim(rtrim((string) $s['points_done'], '0'), '.')) ?> / <?= e(rtrim(rtrim((string) $s['points'], '0'), '.')) ?></td>
                            <td><?= chart_line([['label' => 'Remaining', 'points' => array_map(static fn (array $b): float => (float) $b['remaining'], $s['burndown'])]], ['width' => 120, 'height' => 32, 'title' => 'Burndown of ' . $s['name']]) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody></table></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
