<?php /** The epic's issues as a ledger. Data: epic, project, issues, canWrite, newIssueUrl, tz */
$canAdd = $canWrite && $project['i_can_work'] && $epic['status'] === 'open'; ?>
<div class="card" id="epic-view-issues">
    <div class="card-header d-flex justify-content-between align-items-center"><h5 class="card-title mb-0">Issues <span class="text-muted fw-normal fs-12" id="epic-view-issues-count">(<?= count($issues) ?>)</span></h5>
        <?php if ($canAdd): ?><a href="<?= e($newIssueUrl) ?>" class="btn btn-sm btn-light-brand" id="epic-view-new-issue-btn" hx-get="<?= e($newIssueUrl) ?>" hx-target="#page-content" hx-push-url="<?= e($newIssueUrl) ?>"><i class="feather-plus me-1"></i>New issue</a><?php endif; ?></div>
    <div class="card-body p-0">
        <div class="table-responsive" style="overflow-x:auto">
            <table class="table table-hover mb-0" id="epic-view-issues-table">
                <thead class="thead-light"><tr><th>Key</th><th></th><th>Summary</th><th>Status</th><th>Assignee</th><th class="text-end">Pts</th><th>Sprint</th></tr></thead>
                <tbody>
                <?php if ($issues === []): ?><tr><td colspan="7" class="text-center text-muted py-4" id="epic-view-issues-empty">No issue in this epic yet. Set an issue's epic on its form.</td></tr><?php endif; ?>
                <?php foreach ($issues as $i): $tone = issue_status_tone((string) $i['status_category']); $url = '/issues/' . $i['key']; ?>
                    <tr id="epic-issue-row-<?= (int) $i['issue_id'] ?>">
                        <td class="text-nowrap"><a href="<?= e($url) ?>" class="fw-semibold" hx-get="<?= e($url) ?>" hx-target="#page-content" hx-push-url="<?= e($url) ?>"><?= e($i['key']) ?></a></td>
                        <td><i class="<?= e(issue_type_icon((string) $i['type'])) ?> text-muted" title="<?= e(ucfirst((string) $i['type'])) ?>"></i></td>
                        <td class="text-wrap" style="min-width:14rem"><a href="<?= e($url) ?>" class="text-dark" hx-get="<?= e($url) ?>" hx-target="#page-content" hx-push-url="<?= e($url) ?>"><?= e($i['summary']) ?></a></td>
                        <td><span class="badge bg-soft-<?= e($tone) ?> text-<?= e($tone) ?>"><?= e($i['status']) ?></span></td>
                        <td class="text-nowrap"><?= $i['assignee_member_id'] !== null ? e($i['assignee_name']) : '<span class="text-muted">—</span>' ?></td>
                        <td class="text-end"><?= $i['story_points'] !== null ? e(days_label($i['story_points'])) : '<span class="text-muted">—</span>' ?></td>
                        <td class="text-nowrap fs-12"><?= $i['sprint_name'] !== null ? e($i['sprint_name']) : '<span class="text-muted">Backlog</span>' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
