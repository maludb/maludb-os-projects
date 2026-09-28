<?php /** The project form — a full screen. Data: record, isNew, errors, departments, members */
$r = $record; $id = $r['id'] !== null ? (int) $r['id'] : null; $key = (string) ($r['key'] ?? '');
$back = $isNew ? '/projects' : '/projects/' . $key;
$field = static function (string $name, string $label, string $input, string $help = ''): string {
    return '<div class="row mb-3" id="project-form-field-' . $name . '-row"><label for="project-form-field-' . $name . '" class="col-lg-3 col-form-label fs-12 text-muted" id="project-form-field-' . $name . '-label">' . e($label) . '</label><div class="col-lg-9">' . $input . ($help !== '' ? '<div class="form-text">' . e($help) . '</div>' : '') . '</div></div>';
};
$select = static function (string $name, array $options, $current, ?string $blank = null): string {
    $h = '<select name="' . $name . '" id="project-form-field-' . $name . '" class="form-select">';
    if ($blank !== null) { $h .= '<option value="">' . e($blank) . '</option>'; }
    foreach ($options as $v => $l) { $h .= '<option value="' . e((string) $v) . '"' . ((string) $current === (string) $v ? ' selected' : '') . '>' . e($l) . '</option>'; }
    return $h . '</select>';
};
$deptOptions = []; foreach ($departments as $d) { $deptOptions[(int) $d['department_id']] = $d['name']; }
$memberOptions = []; foreach ($members as $m) { $memberOptions[(int) $m['member_id']] = $m['display_name'] . ($m['member_kind'] === 'agent' ? ' (agent)' : ($m['job_title'] ? ' · ' . $m['job_title'] : '')); }
?>
<div class="page-header" id="project-form-header">
    <div class="page-header-left d-flex align-items-center">
        <div class="page-header-title"><h5 class="m-b-10"><?= $isNew ? 'New project' : 'Edit ' . e($key) ?></h5></div>
        <ul class="breadcrumb">
            <li class="breadcrumb-item"><a href="/projects" hx-get="/projects" hx-target="#page-content" hx-push-url="/projects">Projects</a></li>
            <?php if (!$isNew): ?><li class="breadcrumb-item"><a href="/projects/<?= e($key) ?>" hx-get="/projects/<?= e($key) ?>" hx-target="#page-content" hx-push-url="/projects/<?= e($key) ?>"><?= e($key) ?></a></li><?php endif; ?>
            <li class="breadcrumb-item"><?= $isNew ? 'New' : 'Edit' ?></li>
        </ul>
    </div>
    <div class="page-header-right ms-auto">
        <div class="page-header-right-items">
            <div class="d-flex d-md-none"><a href="javascript:void(0)" class="page-header-right-close-toggle"><i class="feather-arrow-left me-2"></i><span>Back</span></a></div>
            <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                <a href="<?= e($back) ?>" id="project-form-cancel-btn" class="btn btn-light-brand" hx-get="<?= e($back) ?>" hx-target="#page-content" hx-push-url="<?= e($back) ?>"><i class="feather-x me-2"></i><span>Cancel</span></a>
                <button type="submit" form="project-form" id="project-form-save-btn" class="btn btn-primary"><i class="feather-save me-2"></i><span><?= $isNew ? 'Create' : 'Save' ?></span></button>
            </div>
        </div>
        <div class="d-md-none d-flex align-items-center"><a href="javascript:void(0)" class="page-header-right-open-toggle"><i class="feather-align-right fs-20"></i></a></div>
    </div>
</div>
<div class="main-content" id="project-form-content">
    <div class="card stretch stretch-full" id="project-form-container">
        <div class="card-body">
            <?= view('shared/validation-errors.php', ['errors' => $errors]) ?>
            <form method="post" action="/projects/save.php" id="project-form" hx-post="/projects/save.php" hx-target="#page-content" hx-swap="innerHTML">
                <?= csrf_field() ?>
                <?php if (!$isNew): ?><input type="hidden" name="project" value="<?= $id ?>"><?php endif; ?>
                <?= $field('key', 'Key', $isNew
                    ? '<input type="text" name="key" id="project-form-field-key" class="form-control text-uppercase" value="' . e($key) . '" required pattern="[A-Za-z][A-Za-z0-9]{1,9}" maxlength="10" autocomplete="off" style="max-width:12rem">'
                    : '<input type="text" id="project-form-field-key" class="form-control" value="' . e($key) . '" readonly style="max-width:12rem">',
                    $isNew ? 'Two to ten letters and digits, starting with a letter. It prefixes every issue key and never changes.' : 'A key never changes.') ?>
                <?= $field('name', 'Name', '<input type="text" name="name" id="project-form-field-name" class="form-control" value="' . e($r['name']) . '" required maxlength="120">') ?>
                <?= $field('description', 'Description', '<textarea name="description" id="project-form-field-description" class="form-control" rows="6">' . e($r['description'] ?? '') . '</textarea>', 'Markdown: paragraphs, lists, emphasis, links, issue keys.') ?>
                <?= $field('department', 'Department', $select('department', $deptOptions, $r['department_id'], 'None'), 'Who owns the project; its administrators administer it.') ?>
                <?= $field('lead', 'Lead', $select('lead', $memberOptions, $r['lead_member_id'], 'None'), 'A person or an agent. The lead joins the team.') ?>
                <?= $field('visibility', 'Visibility', $select('visibility', ['open' => 'Open — everyone who may read projects', 'private' => 'Private — its team only'], $r['visibility'])) ?>
                <?= $field('board_mode', 'Board', $select('board_mode', ['scrum' => 'Scrum — the active sprint', 'kanban' => 'Kanban — the whole backlog'], $r['board_mode'])) ?>
                <?= $field('customer', 'Customer', '<input type="text" name="customer" id="project-form-field-customer" class="form-control" value="' . e($r['customer'] ?? '') . '" maxlength="120">', 'Free text; the kernel has no CRM.') ?>
            </form>
        </div>
    </div>
</div>
