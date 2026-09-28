<?php /** One project card. Data: r (a mcp_projects row) */
$id = (int) $r['project_id']; $key = (string) $r['key']; $url = '/projects/' . $key;
$archived = $r['status'] === 'archived';
?>
<div class="col-xl-4 col-md-6" id="project-card-<?= $id ?>" data-state="<?= $archived ? 'muted' : 'live' ?>">
    <div class="card h-100 mb-0 <?= $archived ? 'border-dashed bg-transparent shadow-none' : 'border-start border-3 border-primary' ?>">
        <div class="card-body d-flex flex-column gap-3">
            <div class="d-flex align-items-start gap-3">
                <div class="avatar-text avatar-md rounded flex-shrink-0 <?= $archived ? 'bg-gray-200 text-muted' : 'bg-soft-primary text-primary' ?>" title="<?= e($key) ?>"><i class="feather-trello"></i></div>
                <div class="flex-grow-1 overflow-hidden">
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <a href="<?= e($url) ?>" class="fw-bold text-dark" hx-get="<?= e($url) ?>" hx-target="#page-content" hx-swap="innerHTML" hx-push-url="<?= e($url) ?>" id="project-card-<?= $id ?>-link"><?= e($r['name']) ?></a>
                        <span class="badge bg-soft-secondary text-dark"><?= e($key) ?></span>
                        <?php if ($r['visibility'] === 'private'): ?><i class="feather-lock text-muted" title="Private: its team only"></i><?php endif; ?>
                        <?php if ($archived): ?><span class="badge bg-soft-secondary text-secondary">archived</span><?php endif; ?>
                    </div>
                    <?php if (!empty($r['description'])): ?><div class="fs-12 text-muted text-truncate-2-line"><?= e(mb_substr((string) $r['description'], 0, 160)) ?></div><?php endif; ?>
                </div>
            </div>
            <div class="d-flex flex-column gap-1 fs-12">
                <div class="d-flex justify-content-between gap-2"><span class="text-muted flex-shrink-0">Lead</span><span class="text-truncate text-end"><?php if ($r['lead_member_id'] !== null): ?><span class="avatar-text avatar-xs rounded-circle me-1"><?= e(initials((string) $r['lead_name'])) ?></span><?= e($r['lead_name']) ?><?php else: ?>—<?php endif; ?></span></div>
                <div class="d-flex justify-content-between gap-2"><span class="text-muted flex-shrink-0">Department</span><span class="text-truncate text-end"><?= e($r['department_name'] ?? '—') ?></span></div>
                <div class="d-flex justify-content-between gap-2"><span class="text-muted flex-shrink-0">Open issues</span><span class="text-end" id="project-card-<?= $id ?>-open"><?= (int) $r['open_issues'] ?></span></div>
                <div class="d-flex justify-content-between gap-2"><span class="text-muted flex-shrink-0">Sprint</span><span class="text-truncate text-end"><?= e($r['active_sprint_name'] ?? 'none running') ?></span></div>
            </div>
            <div class="d-flex flex-wrap align-items-center gap-2 mt-auto">
                <span class="fs-11 text-muted"><?= (int) $r['team_size'] ?> on the team</span>
                <a href="<?= e($url) ?>" id="project-card-<?= $id ?>-open-btn" class="btn btn-sm btn-light-brand ms-auto" hx-get="<?= e($url) ?>" hx-target="#page-content" hx-swap="innerHTML" hx-push-url="<?= e($url) ?>">Open<i class="feather-arrow-right ms-1"></i></a>
            </div>
        </div>
    </div>
</div>
