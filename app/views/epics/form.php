<?php /** The epic form — a full screen (screens epic-add / epic-edit). Data: record, project, isNew, errors */
$r = $record; $id = $r['id'] !== null ? (int) $r['id'] : null; $key = (string) $project['key'];
$back = $isNew ? '/projects/' . $key . '/epics' : '/epics/' . $id;
$field = static function (string $name, string $label, string $input, string $help = ''): string {
    return '<div class="row mb-3" id="epic-form-field-' . $name . '-row"><label for="epic-form-field-' . $name . '" class="col-lg-3 col-form-label fs-12 text-muted" id="epic-form-field-' . $name . '-label">' . e($label) . '</label><div class="col-lg-9">' . $input . ($help !== '' ? '<div class="form-text">' . e($help) . '</div>' : '') . '</div></div>';
};
$colours = '<select name="colour" id="epic-form-field-colour" class="form-select" style="max-width:14rem">';
foreach (EPIC_COLOURS as $c) { $colours .= '<option value="' . $c . '"' . ((string) ($r['colour'] ?? 'primary') === $c ? ' selected' : '') . '>' . ucfirst($c) . '</option>'; }
$colours .= '</select>';
?>
<div class="page-header" id="epic-form-header">
    <div class="page-header-left d-flex align-items-center">
        <div class="page-header-title"><h5 class="m-b-10"><?= $isNew ? 'New epic' : 'Edit epic' ?></h5></div>
        <ul class="breadcrumb">
            <li class="breadcrumb-item"><a href="/projects" hx-get="/projects" hx-target="#page-content" hx-push-url="/projects">Projects</a></li>
            <li class="breadcrumb-item"><a href="/projects/<?= e($key) ?>" hx-get="/projects/<?= e($key) ?>" hx-target="#page-content" hx-push-url="/projects/<?= e($key) ?>"><?= e($key) ?></a></li>
            <li class="breadcrumb-item"><a href="/projects/<?= e($key) ?>/epics" hx-get="/projects/<?= e($key) ?>/epics" hx-target="#page-content" hx-push-url="/projects/<?= e($key) ?>/epics">Epics</a></li>
            <li class="breadcrumb-item"><?= $isNew ? 'New' : 'Edit' ?></li>
        </ul>
    </div>
    <div class="page-header-right ms-auto">
        <div class="page-header-right-items">
            <div class="d-flex d-md-none"><a href="javascript:void(0)" class="page-header-right-close-toggle"><i class="feather-arrow-left me-2"></i><span>Back</span></a></div>
            <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                <a href="<?= e($back) ?>" id="epic-form-cancel-btn" class="btn btn-light-brand" hx-get="<?= e($back) ?>" hx-target="#page-content" hx-push-url="<?= e($back) ?>"><i class="feather-x me-2"></i><span>Cancel</span></a>
                <button type="submit" form="epic-form" id="epic-form-save-btn" class="btn btn-primary"><i class="feather-save me-2"></i><span><?= $isNew ? 'Create' : 'Save' ?></span></button>
            </div>
        </div>
        <div class="d-md-none d-flex align-items-center"><a href="javascript:void(0)" class="page-header-right-open-toggle"><i class="feather-align-right fs-20"></i></a></div>
    </div>
</div>
<div class="main-content" id="epic-form-content">
    <div class="card stretch stretch-full" id="epic-form-container">
        <div class="card-body">
            <?= view('shared/validation-errors.php', ['errors' => $errors]) ?>
            <form method="post" action="/epics/save.php" id="epic-form" hx-post="/epics/save.php" hx-target="#page-content" hx-swap="innerHTML">
                <?= csrf_field() ?>
                <input type="hidden" name="project" value="<?= e($key) ?>" id="epic-form-field-project">
                <?php if (!$isNew): ?><input type="hidden" name="epic" value="<?= $id ?>"><?php endif; ?>
                <?= $field('name', 'Name', '<input type="text" name="name" id="epic-form-field-name" class="form-control" value="' . e((string) $r['name']) . '" required maxlength="120">', 'Unique within the project.') ?>
                <?= $field('summary', 'Summary', '<textarea name="summary" id="epic-form-field-summary" class="form-control" rows="4">' . e((string) ($r['summary'] ?? '')) . '</textarea>', 'What this body of work delivers.') ?>
                <?= $field('colour', 'Colour', $colours, 'Marks its issues on the board and the backlog.') ?>
                <?= $field('target_date', 'Target date', '<input type="date" name="target_date" id="epic-form-field-target_date" class="form-control" value="' . e((string) ($r['target_date'] ?? '')) . '" style="max-width:14rem">', 'Optional. An open epic past its target shows in red.') ?>
                <?= $field('sort_order', 'Order', '<input type="number" name="sort_order" id="epic-form-field-sort_order" class="form-control" value="' . (int) ($r['sort_order'] ?? 0) . '" min="0" step="1" style="max-width:8rem">', 'Lower first among the open epics.') ?>
            </form>
        </div>
    </div>
</div>
