<?php /** The results region: the epics as cards with progress. Data: project, epics, status */
$key = (string) $project['key']; $listUrl = '/projects/' . $key . '/epics';
$statusTone = ['open' => 'primary', 'done' => 'success', 'cancelled' => 'secondary'];
?>
<div id="epics-list-results" hx-get="<?= e($listUrl) ?>" hx-trigger="epicChanged from:body" hx-include="#epics-list-filters" hx-swap="outerHTML">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <span class="text-muted fs-12" id="epics-list-count"><?= count($epics) ?> <?= count($epics) === 1 ? 'epic' : 'epics' ?></span>
    </div>
    <?php if ($epics === []): ?>
    <div class="text-center text-muted py-5" id="epics-list-empty"><?= $status === '' ? 'No epic yet. An epic gathers a body of work; its issues report their progress here.' : 'No ' . e($status) . ' epic.' ?></div>
    <?php endif; ?>
    <div class="row g-3" id="epics-list-cards">
        <?php foreach ($epics as $e): $id = (int) $e['epic_id']; $url = '/epics/' . $id; $pct = epic_progress_pct($e); $tone = $statusTone[$e['status']] ?? 'secondary'; ?>
        <div class="col-xl-4 col-md-6" id="epic-card-<?= $id ?>">
            <div class="card mb-0 h-100 border-start border-3 border-<?= e($e['colour']) ?>">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <a href="<?= e($url) ?>" class="fw-bold text-dark text-truncate-2-line" hx-get="<?= e($url) ?>" hx-target="#page-content" hx-push-url="<?= e($url) ?>" id="epic-card-<?= $id ?>-name"><?= e($e['name']) ?></a>
                        <span class="badge bg-soft-<?= e($tone) ?> text-<?= e($tone) ?>"><?= e(ucfirst((string) $e['status'])) ?></span>
                    </div>
                    <?php if (!empty($e['summary'])): ?><p class="text-muted fs-12 mt-2 mb-2 text-truncate-2-line"><?= e($e['summary']) ?></p><?php endif; ?>
                    <div class="d-flex justify-content-between fs-12 text-muted mt-2">
                        <span><?= (int) $e['issues_done'] ?>/<?= (int) $e['issues'] ?> issues<?php if ((float) $e['points'] > 0): ?> · <?= days_label($e['points_done']) ?>/<?= days_label($e['points']) ?> pts<?php endif; ?></span>
                        <span><?= $pct ?>%</span>
                    </div>
                    <div class="progress mt-1" style="height:6px" role="progressbar" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100" aria-label="Progress"><div class="progress-bar bg-<?= e($e['colour']) ?>" style="width:<?= $pct ?>%"></div></div>
                    <?php if ($e['target_date'] !== null): ?><div class="fs-12 mt-2 <?= $e['status'] === 'open' && $e['target_date'] < date('Y-m-d') ? 'text-danger' : 'text-muted' ?>"><i class="feather-flag me-1"></i>Target <?= e(format_date($e['target_date'])) ?></div><?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
