<?php /** The tab strip and the list — Pattern B's swapped region. Data: rows, tab, counts, tz */
$tabs = ['assigned' => 'Assigned to me', 'watching' => 'Watching', 'review' => 'In review for me', 'reported' => 'Reported by me'];
$empty = ['assigned' => 'Nothing is assigned to you. Enjoy the quiet, or pick something from a backlog.', 'watching' => 'You are not watching any open issue.', 'review' => 'Nothing is waiting for your review.', 'reported' => 'You have reported nothing that is still open.'];
?>
<div id="my-work-results">
    <div class="card-header border-bottom-0 pb-0">
        <ul class="nav nav-tabs" id="my-work-tabs" role="tablist">
            <?php foreach ($tabs as $k => $label): $url = '/my-work?tab=' . $k; ?>
            <li class="nav-item" role="presentation"><a class="nav-link<?= $tab === $k ? ' active' : '' ?>" id="my-work-tab-<?= $k ?>" href="<?= e($url) ?>" hx-get="<?= e($url) ?>" hx-target="#my-work-results" hx-swap="outerHTML" hx-push-url="<?= e($url) ?>"><?= $label ?> <span class="badge bg-soft-secondary text-dark ms-1" id="my-work-count-<?= $k ?>"><?= (int) $counts[$k] ?></span></a></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <div class="card-body custom-card-action p-0">
        <?php if ($rows === []): ?>
            <div class="text-center text-muted py-5" id="my-work-empty"><?= e($empty[$tab]) ?></div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0" id="my-work-table">
                <thead class="thead-light"><tr><th>Issue</th><th class="d-none d-md-table-cell">Project · status · sprint</th><th>State</th><th class="text-end d-none d-md-table-cell">Due</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $r): $href = $r['href']; ?>
                    <tr id="my-work-row-<?= (int) $r['id'] ?>" class="<?= $r['urgency'] === 2 ? 'table-danger-soft' : '' ?>">
                        <td><a href="<?= e($href) ?>" hx-get="<?= e($href) ?>" hx-target="#page-content" hx-push-url="<?= e($href) ?>" class="fw-semibold text-dark"><i class="<?= e(issue_type_icon($r['type'])) ?> me-1 text-muted"></i><span class="text-muted me-1"><?= e($r['key']) ?></span><?= e($r['summary']) ?></a>
                            <div class="fs-12 text-muted d-md-none"><?= e($r['detail']) ?></div></td>
                        <td class="d-none d-md-table-cell"><span class="badge bg-soft-secondary text-dark me-1"><?= e($r['project_key']) ?></span><span class="badge bg-soft-<?= e(issue_status_tone($r['status_category'])) ?> text-<?= e(issue_status_tone($r['status_category'])) ?>"><?= e($r['status']) ?></span><?= $r['sprint'] !== null ? ' <span class="text-muted fs-12">' . e($r['sprint']) . '</span>' : '' ?></td>
                        <td><span class="badge bg-soft-<?= my_work_state_tone($r['state']) ?> text-<?= my_work_state_tone($r['state']) ?>" id="my-work-state-<?= (int) $r['id'] ?>"><?= e($r['state']) ?></span><?= $r['open_blockers'] > 0 ? ' <span class="fs-12 text-danger">' . (int) $r['open_blockers'] . ' blocker' . ($r['open_blockers'] > 1 ? 's' : '') . '</span>' : '' ?></td>
                        <td class="text-end text-nowrap d-none d-md-table-cell"><?= $r['due_at'] !== null ? e(format_date($r['due_at'])) : '<span class="text-muted">—</span>' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>
