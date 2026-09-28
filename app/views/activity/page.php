<?php /** Data: rows, page, more, entityType, entityId, projectKey, tz */
$base = '/activity' . ($entityType !== '' && $entityId !== null ? '?entity_type=' . rawurlencode($entityType) . '&entity_id=' . (int) $entityId . '&' : ($projectKey !== '' ? '?project=' . rawurlencode($projectKey) . '&' : '?'));
?>
<div class="page-header" id="activity-header">
    <div class="page-header-left d-flex align-items-center">
        <div class="page-header-title"><h5 class="m-b-10">Activity</h5></div>
        <ul class="breadcrumb">
            <li class="breadcrumb-item"><a href="/" hx-get="/" hx-target="#page-content" hx-push-url="/">Home</a></li>
            <li class="breadcrumb-item"><?= $entityType !== '' ? e(str_replace('_', ' ', $entityType)) . ' #' . (int) $entityId : ($projectKey !== '' ? e($projectKey) : 'Recent') ?></li>
        </ul>
    </div>
</div>
<div class="main-content" id="activity-content">
    <div class="row">
        <div class="col-lg-12" id="activity-results">
            <div class="card stretch stretch-full" id="activity-card">
                <div class="card-header"><h5 class="card-title"><?= $entityType !== '' ? 'History' : 'What happened' ?></h5></div>
                <div class="card-body custom-card-action p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="activity-table">
                            <thead class="thead-light"><tr>
                                <th id="activity-col-when">When</th><th id="activity-col-who">Who</th><th id="activity-col-what">What</th><th id="activity-col-record">Record</th><th id="activity-col-source">Source</th>
                            </tr></thead>
                            <tbody id="activity-tbody">
                            <?php if ($rows === []): ?>
                                <tr><td colspan="5" class="text-center text-muted py-4">Nothing yet.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($rows as $r): ?>
                                <tr id="activity-row-<?= (int) $r['activity_id'] ?>">
                                    <td><?= e(format_ts($r['occurred_at'], $tz)) ?></td>
                                    <td><?= e($r['actor_name'] ?? 'system') ?><?= ($r['actor_kind'] ?? '') === 'agent' ? ' <span class="badge bg-soft-info text-info">agent</span>' : '' ?></td>
                                    <td><code><?= e($r['action']) ?></code><?= $r['screen'] ? ' <small class="text-muted">' . e($r['screen']) . '</small>' : '' ?></td>
                                    <td><?php if ($r['entity_type']): ?><a href="/activity?entity_type=<?= e($r['entity_type']) ?>&amp;entity_id=<?= (int) $r['entity_id'] ?>" hx-get="/activity?entity_type=<?= e($r['entity_type']) ?>&amp;entity_id=<?= (int) $r['entity_id'] ?>" hx-target="#page-content" hx-push-url="/activity?entity_type=<?= e($r['entity_type']) ?>&amp;entity_id=<?= (int) $r['entity_id'] ?>"><?= e(str_replace('_', ' ', $r['entity_type'])) ?> #<?= (int) $r['entity_id'] ?></a><?php endif; ?></td>
                                    <td><?= e($r['source']) ?><?= $r['agent_run_id'] ? ' <small class="text-muted">run #' . (int) $r['agent_run_id'] . '</small>' : '' ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if ($page > 1 || $more): ?>
                    <nav class="p-3" id="activity-pagination"><ul class="pagination mb-0">
                        <?php if ($page > 1): ?><li class="page-item"><a class="page-link" href="<?= e($base) ?>page=<?= $page - 1 ?>" hx-get="<?= e($base) ?>page=<?= $page - 1 ?>" hx-target="#page-content" hx-push-url="<?= e($base) ?>page=<?= $page - 1 ?>">Newer</a></li><?php endif; ?>
                        <?php if ($more): ?><li class="page-item"><a class="page-link" href="<?= e($base) ?>page=<?= $page + 1 ?>" hx-get="<?= e($base) ?>page=<?= $page + 1 ?>" hx-target="#page-content" hx-push-url="<?= e($base) ?>page=<?= $page + 1 ?>">Older</a></li><?php endif; ?>
                    </ul></nav>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
