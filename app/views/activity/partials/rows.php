<?php /** The list — Pattern B's swapped region. Data: rows, page, more, query, tz */
$pageUrl = static fn (int $p): string => '/activity?' . http_build_query($query + ['page' => $p]);
?>
<div id="activity-results" class="card-body custom-card-action p-0">
    <div class="table-responsive">
        <table class="table table-hover mb-0" id="activity-table">
            <thead class="thead-light"><tr>
                <th id="activity-col-when">When</th><th id="activity-col-who">Who</th><th id="activity-col-what">What</th><th id="activity-col-record" class="d-none d-md-table-cell">Record</th><th id="activity-col-source" class="d-none d-md-table-cell">Source</th>
            </tr></thead>
            <tbody id="activity-tbody">
            <?php if ($rows === []): ?>
                <tr><td colspan="5" class="text-center text-muted py-4" id="activity-empty">Nothing here that you may see.</td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $r): $rec = $r['issue_key'] ?? ($r['entity_type'] !== null ? str_replace('_', ' ', $r['entity_type']) . ' #' . (int) $r['entity_id'] : null); ?>
                <tr id="activity-row-<?= (int) $r['activity_id'] ?>">
                    <td class="text-nowrap fs-12" title="<?= e($r['occurred_at']) ?>"><?= e(format_ts($r['occurred_at'], $tz, 'M j, g:i A')) ?></td>
                    <td class="text-nowrap"><?= e($r['actor_name'] ?? 'system') ?><?= ($r['actor_kind'] ?? '') === 'agent' ? ' <span class="badge bg-soft-info text-info">agent</span>' : '' ?></td>
                    <td class="text-wrap"><?= e($r['sentence']) ?><?php if ($rec !== null): ?><div class="fs-12 text-muted d-md-none"><?php if ($r['href'] !== null): ?><a href="<?= e($r['href']) ?>" hx-get="<?= e($r['href']) ?>" hx-target="#page-content" hx-push-url="<?= e($r['href']) ?>"><?= e($rec) ?></a><?php else: ?><?= e($rec) ?><?php endif; ?></div><?php endif; ?></td>
                    <td class="d-none d-md-table-cell"><?php if ($rec !== null): ?><?php if ($r['href'] !== null): ?><a href="<?= e($r['href']) ?>" hx-get="<?= e($r['href']) ?>" hx-target="#page-content" hx-push-url="<?= e($r['href']) ?>"><?= e($rec) ?></a><?php else: ?><?= e($rec) ?><?php endif; ?>
                        <?php $eq = $r['issue_key'] !== null ? 'issue:' . $r['issue_key'] : $r['entity_type'] . ':' . (int) $r['entity_id']; ?> <a href="/activity?entity=<?= e(rawurlencode($eq)) ?>" hx-get="/activity?entity=<?= e(rawurlencode($eq)) ?>" hx-target="#page-content" hx-push-url="/activity?entity=<?= e(rawurlencode($eq)) ?>" class="text-muted fs-12" title="Only this record"><i class="feather-filter"></i></a><?php endif; ?></td>
                    <td class="d-none d-md-table-cell"><span class="badge bg-soft-<?= ($r['source'] ?? '') === 'agent' ? 'info text-info' : (($r['source'] ?? '') === 'web' ? 'secondary text-dark' : 'warning text-warning') ?>"><?= e($r['source']) ?></span><?= $r['agent_run_id'] ? ' <small class="text-muted">run #' . (int) $r['agent_run_id'] . '</small>' : '' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($page > 1 || $more): ?>
    <nav class="p-3" id="activity-pagination"><ul class="pagination mb-0">
        <?php if ($page > 1): ?><li class="page-item"><a class="page-link" href="<?= e($pageUrl($page - 1)) ?>" hx-get="<?= e($pageUrl($page - 1)) ?>" hx-target="#activity-results" hx-swap="outerHTML" hx-push-url="<?= e($pageUrl($page - 1)) ?>">Newer</a></li><?php endif; ?>
        <?php if ($more): ?><li class="page-item"><a class="page-link" href="<?= e($pageUrl($page + 1)) ?>" hx-get="<?= e($pageUrl($page + 1)) ?>" hx-target="#activity-results" hx-swap="outerHTML" hx-push-url="<?= e($pageUrl($page + 1)) ?>">Older</a></li><?php endif; ?>
    </ul></nav>
    <?php endif; ?>
</div>
