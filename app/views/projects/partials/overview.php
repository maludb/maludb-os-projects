<?php /** The Overview tab. Data: project, activity, tz */
require_once APP_ROOT . '/app/views/shared/chart.php';   // the burndown sparkline
$p = $project; $key = (string) $p['key']; $base = '/projects/' . $key; $s = $p['active_sprint'] ?? null;
$roleLabel = ['lead' => 'Lead', 'product_owner' => 'Product owner', 'scrum_master' => 'Scrum master', 'member' => 'Member', 'viewer' => 'Viewer'];
$daysLeft = $s !== null && $s['end_date'] !== null ? (int) ((strtotime((string) $s['end_date']) - strtotime(date('Y-m-d'))) / 86400) : null;
?>
<div class="row g-3">
    <div class="col-lg-7">
        <dl class="row mb-3 fs-13" id="project-view-facts">
            <dt class="col-4 text-muted">Lead</dt><dd class="col-8"><?php if ($p['lead_member_id'] !== null): ?><span class="avatar-text avatar-xs rounded-circle me-1"><?= e(initials((string) $p['lead_name'])) ?></span><?= e($p['lead_name']) ?><?php else: ?>—<?php endif; ?></dd>
            <dt class="col-4 text-muted">Department</dt><dd class="col-8"><?= e($p['department_name'] ?? '—') ?></dd>
            <dt class="col-4 text-muted">Customer</dt><dd class="col-8"><?= e($p['customer'] ?? '—') ?></dd>
            <dt class="col-4 text-muted">Board</dt><dd class="col-8"><?= e(ucfirst((string) $p['board_mode'])) ?> · swimlanes by <?= e($p['swimlane'] === 'none' ? 'nothing' : $p['swimlane']) ?></dd>
            <dt class="col-4 text-muted">Open issues</dt><dd class="col-8"><a href="<?= e($base) ?>/issues" hx-get="<?= e($base) ?>/issues" hx-target="#page-content" hx-push-url="<?= e($base) ?>/issues"><?= (int) $p['open_issues'] ?></a></dd>
        </dl>
        <?php if (!empty($p['description'])): ?>
        <div class="mb-4" id="project-view-description"><?= markdown_to_html((string) $p['description']) ?></div>
        <?php endif; ?>
        <div class="d-flex align-items-center mb-2"><h6 class="fw-bold mb-0">Team</h6>
            <?php if ($p['i_administer']): ?><a href="<?= e($base) ?>/settings" class="ms-auto fs-12" hx-get="<?= e($base) ?>/settings" hx-target="#page-content" hx-push-url="<?= e($base) ?>/settings">Manage</a><?php endif; ?></div>
        <div class="d-flex flex-wrap gap-2 mb-4" id="project-view-team">
            <?php if ($p['team'] === []): ?><span class="text-muted fs-12">Nobody is on the team yet.</span><?php endif; ?>
            <?php foreach ($p['team'] as $t): ?>
            <span class="badge bg-soft-secondary text-dark d-inline-flex align-items-center gap-1 py-2" id="project-team-<?= (int) $t['member_id'] ?>" title="<?= e($roleLabel[$t['project_role']] ?? $t['project_role']) ?>">
                <span class="avatar-text avatar-xs rounded-circle"><?= e(initials((string) $t['display_name'])) ?></span><?= e($t['display_name']) ?>
                <?php if ($t['member_kind'] === 'agent'): ?><span class="badge bg-soft-info text-info">agent</span><?php endif; ?>
                <span class="text-muted fs-11">· <?= e($roleLabel[$t['project_role']] ?? $t['project_role']) ?></span>
            </span>
            <?php endforeach; ?>
        </div>
        <h6 class="fw-bold mb-2">Epics</h6>
        <div class="mb-4" id="project-view-epics">
            <?php if ($p['epics'] === []): ?><span class="text-muted fs-12">No epic yet.</span><?php endif; ?>
            <?php foreach ($p['epics'] as $e): $pct = (float) $e['points'] > 0 ? (int) round(100 * (float) $e['points_done'] / (float) $e['points']) : ((int) $e['issues'] > 0 ? (int) round(100 * (int) $e['issues_done'] / (int) $e['issues']) : 0); ?>
            <div class="mb-2" id="project-epic-<?= (int) $e['epic_id'] ?>">
                <div class="d-flex justify-content-between fs-12"><a href="/epics/<?= (int) $e['epic_id'] ?>" hx-get="/epics/<?= (int) $e['epic_id'] ?>" hx-target="#page-content" hx-push-url="/epics/<?= (int) $e['epic_id'] ?>"><?= e($e['name']) ?></a><span class="text-muted"><?= (int) $e['issues_done'] ?>/<?= (int) $e['issues'] ?> issues · <?= days_label($e['points_done']) ?>/<?= days_label($e['points']) ?> pts</span></div>
                <div class="progress" style="height:6px" role="progressbar" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar bg-<?= e($e['colour']) ?>" style="width:<?= $pct ?>%"></div></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card mb-3" id="project-view-sprint-card">
            <div class="card-header"><h5 class="card-title">Active sprint</h5></div>
            <div class="card-body">
                <?php if ($s === null): ?>
                    <p class="text-muted fs-12 mb-0" id="project-view-no-sprint">No sprint is running — plan one from the <a href="<?= e($base) ?>/backlog" hx-get="<?= e($base) ?>/backlog" hx-target="#page-content" hx-push-url="<?= e($base) ?>/backlog">backlog</a>.</p>
                <?php else: ?>
                    <div class="fw-bold"><a href="/sprints/<?= (int) $s['sprint_id'] ?>" hx-get="/sprints/<?= (int) $s['sprint_id'] ?>" hx-target="#page-content" hx-push-url="/sprints/<?= (int) $s['sprint_id'] ?>"><?= e($s['name']) ?></a></div>
                    <?php if (!empty($s['goal'])): ?><div class="fs-12 text-muted mb-2"><?= e($s['goal']) ?></div><?php endif; ?>
                    <div class="fs-12 mb-2"><?= $daysLeft === null ? '' : ($daysLeft >= 0 ? $daysLeft . ' day' . ($daysLeft === 1 ? '' : 's') . ' left' : abs($daysLeft) . ' days over') ?> · <?= days_label($s['points_done']) ?>/<?= days_label($s['points']) ?> points done · <?= (int) $s['issues_done'] ?>/<?= (int) $s['issues'] ?> issues</div>
                    <?= chart_line([['label' => 'Remaining', 'points' => array_map(static fn (array $b): float => (float) $b['remaining'], $s['burndown'] ?? [])]], ['width' => 240, 'height' => 48, 'title' => 'Burndown of ' . $s['name'], 'id' => 'project-view-burndown']) ?>
                <?php endif; ?>
            </div>
        </div>
        <div class="card mb-0" id="project-view-activity-card">
            <div class="card-header"><h5 class="card-title">Recent activity</h5><a href="/activity?project=<?= e($key) ?>" class="fs-12" hx-get="/activity?project=<?= e($key) ?>" hx-target="#page-content" hx-push-url="/activity?project=<?= e($key) ?>">All</a></div>
            <div class="card-body p-0">
                <ul class="list-unstyled mb-0" id="project-view-activity">
                    <?php if ($activity === []): ?><li class="p-3 text-muted fs-12">Nothing yet.</li><?php endif; ?>
                    <?php foreach ($activity as $a): ?>
                    <li class="px-3 py-2 border-bottom fs-12">
                        <?php if ($a['entity_type'] === 'issue' || $a['entity_type'] === 'comment'): ?><?= e(issue_history_sentence($a)) ?>
                        <?php else: ?><?= e((string) ($a['actor_name'] ?? 'Someone')) ?> — <?= e(str_replace(['.', '_'], [' ', ' '], (string) $a['action'])) ?><?php endif; ?>
                        <div class="text-muted fs-11"><?= e(format_ts($a['occurred_at'], $tz)) ?></div>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
</div>
