<?php /** General: the project form's fields (project_update), Visibility with its consequence, Archive / Unarchive. Data: project, record, departments, members, errors, canAdmin */
$p = $project; $r = $record; $key = (string) $p['key']; $id = (int) $p['project_id']; $base = '/projects/' . $key; $archived = $p['status'] === 'archived';
$field = static function (string $name, string $label, string $input, string $help = ''): string {
    return '<div class="row mb-3" id="project-form-field-' . $name . '-row"><label for="project-form-field-' . $name . '" class="col-lg-3 col-form-label fs-12 text-muted">' . e($label) . '</label><div class="col-lg-9">' . $input . ($help !== '' ? '<div class="form-text">' . e($help) . '</div>' : '') . '</div></div>';
};
$select = static function (string $name, array $options, $current, ?string $blank = null, bool $disabled = false): string {
    $h = '<select name="' . $name . '" id="project-form-field-' . $name . '" class="form-select"' . ($disabled ? ' disabled' : '') . '>';
    if ($blank !== null) { $h .= '<option value="">' . e($blank) . '</option>'; }
    foreach ($options as $v => $l) { $h .= '<option value="' . e((string) $v) . '"' . ((string) $current === (string) $v ? ' selected' : '') . '>' . e($l) . '</option>'; }
    return $h . '</select>';
};
$deptOptions = []; foreach ($departments as $d) { $deptOptions[(int) $d['department_id']] = $d['name']; }
$memberOptions = []; foreach ($members as $m) { $memberOptions[(int) $m['member_id']] = $m['display_name'] . ($m['member_kind'] === 'agent' ? ' (agent)' : ($m['job_title'] ? ' · ' . $m['job_title'] : '')); }
$ro = $archived ? ' readonly' : '';
?>
<div class="row g-3" id="project-settings-general">
    <div class="col-xl-8">
        <div class="card mb-0" id="project-settings-general-card">
            <div class="card-header"><h5 class="card-title mb-0">General</h5></div>
            <div class="card-body">
                <?= view('shared/validation-errors.php', ['errors' => $errors]) ?>
                <?php if ($archived): ?><div class="alert alert-secondary fs-12" id="project-settings-readonly">This project is archived: everything is read-only until it is unarchived below.</div><?php endif; ?>
                <form method="post" action="/projects/save.php" id="project-form" hx-post="/projects/save.php" hx-target="#page-content" hx-swap="innerHTML">
                    <?= csrf_field() ?>
                    <input type="hidden" name="project" value="<?= $id ?>">
                    <?= $field('key', 'Key', '<input type="text" id="project-form-field-key" class="form-control" value="' . e($key) . '" readonly style="max-width:12rem">', 'A key never changes.') ?>
                    <?= $field('name', 'Name', '<input type="text" name="name" id="project-form-field-name" class="form-control" value="' . e($r['name']) . '" required maxlength="120"' . $ro . '>') ?>
                    <?= $field('description', 'Description', '<textarea name="description" id="project-form-field-description" class="form-control" rows="4"' . $ro . '>' . e($r['description'] ?? '') . '</textarea>', 'Markdown: paragraphs, lists, emphasis, links, issue keys.') ?>
                    <?= $field('department', 'Department', $select('department', $deptOptions, $r['department_id'], 'None', $archived), 'Who owns the project; its administrators administer it.') ?>
                    <?= $field('lead', 'Lead', $select('lead', $memberOptions, $r['lead_member_id'], 'None', $archived), 'A person or an agent. The lead joins the team as its lead.') ?>
                    <?= $field('board_mode', 'Board', $select('board_mode', ['scrum' => 'Scrum — the active sprint', 'kanban' => 'Kanban — the whole backlog'], $r['board_mode'], null, $archived)) ?>
                    <?= $field('customer', 'Customer', '<input type="text" name="customer" id="project-form-field-customer" class="form-control" value="' . e($r['customer'] ?? '') . '" maxlength="120"' . $ro . '>', 'Free text; the kernel has no CRM.') ?>
                    <hr>
                    <h6 class="fs-13 mb-3" id="project-settings-visibility-title">Visibility</h6>
                    <?= $field('visibility', 'Who sees it', $select('visibility', ['open' => 'Open', 'private' => 'Private'], $r['visibility'], null, $archived),
                        $r['visibility'] === 'private' ? 'Private: only the team sees it — its issues, its board, its reports, even that it exists. Everyone else gets "Project not found".'
                                                        : 'Open: everyone who may read projects sees it and its work. Choosing Private hides it from all but the team.') ?>
                    <?php if (!$archived): ?>
                    <div class="row"><div class="col-lg-9 offset-lg-3"><button type="submit" class="btn btn-primary" id="project-form-save-btn"><i class="feather-save me-2"></i>Save</button></div></div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="card mb-0" id="project-settings-archive-card">
            <div class="card-header"><h5 class="card-title mb-0"><?= $archived ? 'Unarchive' : 'Archive' ?></h5></div>
            <div class="card-body">
                <?php if ($archived): ?>
                <p class="fs-12 text-muted">Archived <?= e(format_date($p['archived_at'])) ?>. Unarchiving makes the project active again: its team can work in it and it counts on the launcher again.</p>
                <?php if ($canAdmin || has_right_in('project.admin', $id)): ?>
                <form method="post" action="/projects/archive.php" hx-post="/projects/archive.php" hx-target="#page-content" hx-swap="innerHTML" id="project-unarchive-form">
                    <?= csrf_field() ?><input type="hidden" name="project" value="<?= $id ?>"><input type="hidden" name="restore" value="1">
                    <button type="submit" class="btn btn-light-brand" id="project-unarchive-btn"><i class="feather-rotate-ccw me-2"></i>Unarchive</button>
                </form>
                <?php endif; ?>
                <?php else: ?>
                <p class="fs-12 text-muted">An archived project is read-only everywhere: no issue changes, no sprints, no board moves. It leaves the projects list and the launcher's counts and stays readable. Nothing is deleted; an admin unarchives it here.</p>
                <form method="post" action="/projects/archive.php" hx-post="/projects/archive.php" hx-target="#page-content" hx-swap="innerHTML" id="project-archive-form"
                      hx-confirm="Archive <?= e($key) ?>? It becomes read-only until unarchived.">
                    <?= csrf_field() ?><input type="hidden" name="project" value="<?= $id ?>">
                    <div class="mb-2"><label for="project-archive-reason" class="form-label fs-12 text-muted">Reason (optional)</label><input type="text" name="reason" id="project-archive-reason" class="form-control form-control-sm" maxlength="240"></div>
                    <button type="submit" class="btn btn-light-secondary text-danger" id="project-archive-btn"><i class="feather-archive me-2"></i>Archive project</button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
