<?php /** One epic (screen epic-view). Data: epic, project, issues, activity, canWrite, openIssues, tz */
$e = $epic; $id = (int) $e['epic_id']; $key = (string) $project['key']; $base = '/projects/' . $key; $pct = epic_progress_pct($e);
$statusTone = ['open' => 'primary', 'done' => 'success', 'cancelled' => 'secondary']; $tone = $statusTone[$e['status']] ?? 'secondary';
$nav = static fn (string $url): string => 'href="' . e($url) . '" hx-get="' . e($url) . '" hx-target="#page-content" hx-swap="innerHTML" hx-push-url="' . e($url) . '"';
$post = static fn (string $extra = ''): string => 'method="post" action="/epics/close.php" hx-post="/epics/close.php" hx-swap="none" ' . $extra;
$editUrl = '/epics/' . $id . '/edit'; $newIssueUrl = '/issues/new?project=' . $key . '&epic=' . $id;
?>
<div class="page-header" id="epic-view-header">
    <div class="page-header-left d-flex align-items-center">
        <div class="page-header-title"><h5 class="m-b-10"><span class="badge bg-<?= e($e['colour']) ?> me-2">&nbsp;</span><?= e($e['name']) ?> <span class="badge bg-soft-<?= e($tone) ?> text-<?= e($tone) ?> ms-1" id="epic-view-status"><?= e(ucfirst((string) $e['status'])) ?></span></h5></div>
        <ul class="breadcrumb">
            <li class="breadcrumb-item"><a href="/projects" hx-get="/projects" hx-target="#page-content" hx-push-url="/projects">Projects</a></li>
            <li class="breadcrumb-item"><a <?= $nav($base) ?>><?= e($key) ?></a></li>
            <li class="breadcrumb-item"><a <?= $nav($base . '/epics') ?>>Epics</a></li>
            <li class="breadcrumb-item"><?= e($e['name']) ?></li>
        </ul>
    </div>
    <div class="page-header-right ms-auto">
        <div class="page-header-right-items">
            <div class="d-flex d-md-none"><a href="javascript:void(0)" class="page-header-right-close-toggle"><i class="feather-arrow-left me-2"></i><span>Back</span></a></div>
            <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                <a <?= $nav($base . '/epics') ?> id="epic-view-back-btn" class="btn btn-light-brand"><i class="feather-arrow-left me-2"></i><span>Epics</span></a>
                <?php if ($canWrite): ?>
                <a <?= $nav($editUrl) ?> id="epic-view-edit-btn" class="btn btn-light-brand"><i class="feather-edit-3 me-2"></i><span>Edit</span></a>
                <?php if ($e['status'] === 'open'): ?>
                <form <?= $post($openIssues > 0 ? 'hx-confirm="' . $openIssues . ' issue' . ($openIssues === 1 ? ' is' : 's are') . ' not done. Mark the epic done anyway?"' : '') ?> id="epic-view-done-form" class="d-inline"><?= csrf_field() ?><input type="hidden" name="epic" value="<?= $id ?>"><input type="hidden" name="status" value="done">
                    <button type="submit" class="btn btn-success" id="epic-view-done-btn"><i class="feather-check me-2"></i><span>Mark done</span></button></form>
                <form <?= $post('hx-confirm="Cancel this epic? Its issues stay where they are."') ?> id="epic-view-cancel-form" class="d-inline"><?= csrf_field() ?><input type="hidden" name="epic" value="<?= $id ?>"><input type="hidden" name="status" value="cancelled">
                    <button type="submit" class="btn btn-light-danger" id="epic-view-cancel-btn"><i class="feather-x-circle me-2"></i><span>Cancel epic</span></button></form>
                <?php else: ?>
                <form <?= $post() ?> id="epic-view-reopen-form" class="d-inline"><?= csrf_field() ?><input type="hidden" name="epic" value="<?= $id ?>"><input type="hidden" name="action" value="reopen">
                    <button type="submit" class="btn btn-light-brand" id="epic-view-reopen-btn"><i class="feather-rotate-ccw me-2"></i><span>Reopen</span></button></form>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        <div class="d-md-none d-flex align-items-center"><a href="javascript:void(0)" class="page-header-right-open-toggle"><i class="feather-align-right fs-20"></i></a></div>
    </div>
</div>
<div class="main-content" id="epic-view-content">
    <div class="row">
        <div class="col-xl-8">
            <div class="card" id="epic-view-summary">
                <div class="card-body">
                    <?php if (!empty($e['summary'])): ?><p class="mb-3" id="epic-view-summary-text"><?= nl2br(e($e['summary'])) ?></p><?php else: ?><p class="text-muted mb-3">No summary.</p><?php endif; ?>
                    <div class="d-flex justify-content-between fs-12 text-muted">
                        <span id="epic-view-counts"><?= (int) $e['issues_done'] ?> of <?= (int) $e['issues'] ?> issue<?= (int) $e['issues'] === 1 ? '' : 's' ?> done<?php if ((float) $e['points'] > 0): ?> · <?= days_label($e['points_done']) ?> of <?= days_label($e['points']) ?> points<?php endif; ?></span>
                        <span id="epic-view-pct"><?= $pct ?>%</span>
                    </div>
                    <div class="progress mt-1" style="height:8px" role="progressbar" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100" aria-label="Progress"><div class="progress-bar bg-<?= e($e['colour']) ?>" style="width:<?= $pct ?>%"></div></div>
                </div>
            </div>
            <?= view('epics/partials/issues.php', ['epic' => $e, 'project' => $project, 'issues' => $issues, 'canWrite' => $canWrite, 'newIssueUrl' => $newIssueUrl, 'tz' => $tz]) ?>
        </div>
        <div class="col-xl-4">
            <div class="card" id="epic-view-details">
                <div class="card-header"><h5 class="card-title mb-0">Details</h5></div>
                <div class="card-body">
                    <dl class="row mb-0 fs-12">
                        <dt class="col-5 text-muted">Project</dt><dd class="col-7"><a <?= $nav($base) ?>><?= e($key) ?></a> · <?= e($project['name']) ?></dd>
                        <dt class="col-5 text-muted">Status</dt><dd class="col-7"><?= e(ucfirst((string) $e['status'])) ?></dd>
                        <dt class="col-5 text-muted">Target</dt><dd class="col-7 <?= $e['target_date'] !== null && $e['status'] === 'open' && $e['target_date'] < date('Y-m-d') ? 'text-danger' : '' ?>"><?= $e['target_date'] !== null ? e(format_date($e['target_date'])) : '—' ?></dd>
                        <dt class="col-5 text-muted">Colour</dt><dd class="col-7"><span class="badge bg-<?= e($e['colour']) ?>"><?= e($e['colour']) ?></span></dd>
                        <dt class="col-5 text-muted">Order</dt><dd class="col-7"><?= (int) $e['sort_order'] ?></dd>
                        <dt class="col-5 text-muted">Created</dt><dd class="col-7"><?= e(format_ts($e['created_at'], $tz)) ?></dd>
                        <dt class="col-5 text-muted">Updated</dt><dd class="col-7"><?= e(format_ts($e['updated_at'], $tz)) ?></dd>
                    </dl>
                </div>
            </div>
            <div class="card" id="epic-view-activity">
                <div class="card-header"><h5 class="card-title mb-0">Activity</h5></div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0 fs-12" id="epic-view-activity-list">
                        <?php foreach ($activity as $a): $after = is_array($a['after']) ? $a['after'] : (json_decode((string) ($a['after'] ?? ''), true) ?: []); $before = is_array($a['before']) ? $a['before'] : (json_decode((string) ($a['before'] ?? ''), true) ?: []);
                            $who = (string) ($a['actor_name'] ?? 'Someone');
                            $sentence = match ((string) $a['action']) {
                                'epic.create' => $who . ' created the epic', 'epic.update' => $who . ' changed ' . (implode(', ', array_keys($after)) ?: 'it'),
                                'epic.close' => $who . ' marked it ' . ($after['status'] ?? 'closed'), 'epic.reopen' => $who . ' reopened it', 'epic.status' => $who . ' set it ' . ($after['status'] ?? ''),
                                'issue.transition' => $who . ' moved an issue to ' . ($after['status'] ?? 'another status'),
                                default => $who . ' · ' . str_replace(['.', '_'], ' ', (string) $a['action']) }; ?>
                        <li class="py-1 border-bottom d-flex gap-2"><span class="text-muted text-nowrap"><?= e(format_ts($a['occurred_at'], $tz, 'M j, g:i A')) ?></span><span><?= e($sentence) ?></span><?= ($a['source'] ?? 'web') === 'agent' ? '<span class="badge bg-soft-info text-info">agent</span>' : '' ?></li>
                        <?php endforeach; ?>
                        <?php if ($activity === []): ?><li class="text-muted">Nothing yet.</li><?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
