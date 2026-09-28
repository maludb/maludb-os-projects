<?php
/** The results region: the issues ledger + pagination. Data: project, rows, total, page, pages, filters, sort, tz */
$key = (string) $project['key']; $listUrl = '/projects/' . $key . '/issues';
$qs = static function (int $p) use ($filters, $sort, $listUrl): string {
    $q = array_filter(['q' => $filters['q'], 'type' => $filters['type'] ?: null, 'status' => $filters['status'] ?: null, 'category' => $filters['category'], 'assignee' => $filters['assignee'],
        'reporter' => $filters['reporter'], 'sprint' => $filters['sprint'], 'epic' => $filters['epic'], 'label' => $filters['label'], 'component' => $filters['component'],
        'priority' => $filters['priority'] ?: null, 'due' => $filters['due'], 'blocked' => !empty($filters['blocked']) ? 1 : null, 'updated_since' => $filters['updated_since'],
        'subtasks' => !empty($filters['include_subtasks']) ? 1 : null, 'sort' => $sort !== 'rank' ? $sort : null, 'page' => $p > 1 ? $p : null], static fn ($v) => $v !== null && $v !== '' && $v !== []);
    return $listUrl . ($q === [] ? '' : '?' . http_build_query($q));
};
?>
<div id="issues-list-results" hx-get="<?= e($listUrl) ?>" hx-trigger="issueChanged from:body" hx-include="#issues-list-filters" hx-swap="outerHTML">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <span class="text-muted fs-12" id="issues-list-count"><?= (int) $total ?> <?= $total === 1 ? 'issue' : 'issues' ?></span>
    </div>
    <div class="table-responsive" style="overflow-x:auto">
        <table class="table table-hover mb-0" id="issues-list-table">
            <thead class="thead-light"><tr><th>Key</th><th></th><th>Summary</th><th>Status</th><th>Pri</th><th>Assignee</th><th class="text-end">Pts</th><th>Sprint</th><th>Updated</th></tr></thead>
            <tbody>
            <?php if ($rows === []): ?>
                <tr><td colspan="9" class="text-center text-muted py-5" id="issues-list-empty">No issue matches.</td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $i): [$pm, $ptone, $plabel] = issue_priority_mark((string) $i['priority']); $tone = issue_status_tone((string) $i['status_category']); $url = '/issues/' . $i['key']; ?>
                <tr id="issue-row-<?= (int) $i['issue_id'] ?>">
                    <td class="text-nowrap"><a href="<?= e($url) ?>" class="fw-semibold" hx-get="<?= e($url) ?>" hx-target="#page-content" hx-push-url="<?= e($url) ?>"><?= e($i['key']) ?></a></td>
                    <td><i class="<?= e(issue_type_icon((string) $i['type'])) ?> text-muted" title="<?= e(ucfirst((string) $i['type'])) ?>"></i><?php if ((int) $i['open_blockers'] > 0): ?> <i class="feather-slash text-danger" title="Blocked"></i><?php endif; ?></td>
                    <td class="text-wrap" style="min-width:16rem"><a href="<?= e($url) ?>" class="text-dark" hx-get="<?= e($url) ?>" hx-target="#page-content" hx-push-url="<?= e($url) ?>"><?= e($i['summary']) ?></a><?php if ($i['parent_key'] !== null): ?> <span class="fs-11 text-muted">↳ <?= e($i['parent_key']) ?></span><?php endif; ?></td>
                    <td><span class="badge bg-soft-<?= e($tone) ?> text-<?= e($tone) ?>"><?= e($i['status']) ?></span></td>
                    <td><span class="text-<?= e($ptone) ?>" title="<?= e($plabel) ?>"><?= e($pm) ?></span></td>
                    <td class="text-nowrap"><?php if ($i['assignee_member_id'] !== null): ?><span class="avatar-text avatar-xs rounded-circle me-1"><?= e(initials((string) $i['assignee_name'])) ?></span><?= e($i['assignee_name']) ?><?php if ($i['assignee_kind'] === 'agent'): ?> <span class="badge bg-soft-info text-info">agent</span><?php endif; ?><?php else: ?><span class="text-muted">—</span><?php endif; ?></td>
                    <td class="text-end"><?= $i['story_points'] !== null ? e(days_label($i['story_points'])) : '<span class="text-muted">—</span>' ?></td>
                    <td class="text-nowrap fs-12"><?= e($i['sprint_name'] ?? '—') ?></td>
                    <td class="text-nowrap fs-12 text-muted"><?= e(format_ts($i['updated_at'], $tz, 'M j')) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($pages > 1): ?>
    <nav id="issues-list-pagination" aria-label="Pages" class="mt-3"><ul class="pagination justify-content-center">
        <?php for ($p = max(1, $page - 3); $p <= min($pages, $page + 3); $p++): ?>
            <li class="page-item <?= $p === $page ? 'active' : '' ?>"><a class="page-link" href="<?= e($qs($p)) ?>" hx-get="<?= e($qs($p)) ?>" hx-target="#issues-list-results" hx-swap="outerHTML" hx-push-url="<?= e($qs($p)) ?>"><?= $p ?></a></li>
        <?php endfor; ?>
    </ul></nav>
    <?php endif; ?>
</div>
