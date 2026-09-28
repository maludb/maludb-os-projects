<?php /** "Move to another project…" on the fields column — for an administrator of this project and of another (slice 4). Data: issue, project, canAdmin */
if (empty($canAdmin) || $issue['parent_issue_id'] !== null || $project['status'] !== 'active') { return; }
require_once dirname(__DIR__, 3) . '/features/epics/queries.php';
$targets = projects_i_administer(db(), (int) $project['project_id']);
if ($targets === []) { return; }
$mid = (int) $issue['issue_id']; $mkey = (string) $issue['key']; ?>
<details class="mt-3 fs-12" id="issue-move-project">
    <summary class="text-muted" id="issue-move-project-toggle"><i class="feather-corner-up-right me-1"></i>Move to another project…</summary>
    <form method="post" action="/issues/move-project.php" hx-post="/issues/move-project.php" hx-swap="none" hx-target="#page-content" id="issue-move-project-form" class="d-flex gap-2 align-items-end mt-2"
          hx-confirm="Move <?= e($mkey) ?> and its subtasks? They get new keys; the old keys keep working. Sprint, epic, component and labels are left behind.">
        <?= csrf_field() ?><input type="hidden" name="issue" value="<?= $mid ?>">
        <div class="flex-grow-1"><label for="issue-move-project-field-project" class="form-label text-muted mb-1">Project</label>
            <select name="project" id="issue-move-project-field-project" class="form-select form-select-sm" required><option value="">Choose…</option><?php foreach ($targets as $t): ?><option value="<?= (int) $t['project_id'] ?>"><?= e($t['key']) ?> · <?= e($t['name']) ?></option><?php endforeach; ?></select></div>
        <button type="submit" class="btn btn-sm btn-light-brand" id="issue-move-project-btn">Move</button>
    </form>
</details>
