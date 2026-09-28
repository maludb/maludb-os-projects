<?php /** The sprint form — a full screen. Data: record, project, isNew, errors, settings */
$r = $record; $id = $r['id'] !== null ? (int) $r['id'] : null; $key = (string) $project['key']; $base = '/projects/' . $key;
$back = $isNew ? $base . '/backlog' : '/sprints/' . $id;
$field = static function (string $name, string $label, string $input, string $help = ''): string {
    return '<div class="row mb-3" id="sprint-form-field-' . $name . '-row"><label for="sprint-form-field-' . $name . '" class="col-lg-3 col-form-label fs-12 text-muted" id="sprint-form-field-' . $name . '-label">' . e($label) . '</label><div class="col-lg-9">' . $input . ($help !== '' ? '<div class="form-text">' . e($help) . '</div>' : '') . '</div></div>';
};
?>
<div class="page-header" id="sprint-form-header">
    <div class="page-header-left d-flex align-items-center">
        <div class="page-header-title"><h5 class="m-b-10"><?= $isNew ? 'New sprint' : 'Edit ' . e($r['name']) ?></h5></div>
        <ul class="breadcrumb">
            <li class="breadcrumb-item"><a href="/projects" hx-get="/projects" hx-target="#page-content" hx-push-url="/projects">Projects</a></li>
            <li class="breadcrumb-item"><a href="<?= e($base) ?>" hx-get="<?= e($base) ?>" hx-target="#page-content" hx-push-url="<?= e($base) ?>"><?= e($key) ?></a></li>
            <li class="breadcrumb-item"><a href="<?= e($base) ?>/sprints" hx-get="<?= e($base) ?>/sprints" hx-target="#page-content" hx-push-url="<?= e($base) ?>/sprints">Sprints</a></li>
            <?php if (!$isNew): ?><li class="breadcrumb-item"><a href="/sprints/<?= $id ?>" hx-get="/sprints/<?= $id ?>" hx-target="#page-content" hx-push-url="/sprints/<?= $id ?>"><?= e($r['name']) ?></a></li><?php endif; ?>
            <li class="breadcrumb-item"><?= $isNew ? 'New' : 'Edit' ?></li>
        </ul>
    </div>
    <div class="page-header-right ms-auto">
        <div class="page-header-right-items">
            <div class="d-flex d-md-none"><a href="javascript:void(0)" class="page-header-right-close-toggle"><i class="feather-arrow-left me-2"></i><span>Back</span></a></div>
            <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                <a href="<?= e($back) ?>" id="sprint-form-cancel-btn" class="btn btn-light-brand" hx-get="<?= e($back) ?>" hx-target="#page-content" hx-push-url="<?= e($back) ?>"><i class="feather-x me-2"></i><span>Cancel</span></a>
                <button type="submit" form="sprint-form" id="sprint-form-save-btn" class="btn btn-primary"><i class="feather-save me-2"></i><span><?= $isNew ? 'Create' : 'Save' ?></span></button>
            </div>
        </div>
        <div class="d-md-none d-flex align-items-center"><a href="javascript:void(0)" class="page-header-right-open-toggle"><i class="feather-align-right fs-20"></i></a></div>
    </div>
</div>
<div class="main-content" id="sprint-form-content">
    <div class="card stretch stretch-full" id="sprint-form-container">
        <div class="card-body">
            <?= view('shared/validation-errors.php', ['errors' => $errors]) ?>
            <form method="post" action="/sprints/save.php" id="sprint-form" hx-post="/sprints/save.php" hx-target="#page-content" hx-swap="innerHTML">
                <?= csrf_field() ?>
                <?php if ($isNew): ?><input type="hidden" name="project" value="<?= e($key) ?>"><?php else: ?><input type="hidden" name="sprint" value="<?= $id ?>"><?php endif; ?>
                <?= $field('project', 'Project', '<input type="text" id="sprint-form-field-project" class="form-control" value="' . e($key . ' · ' . $project['name']) . '" readonly>') ?>
                <?= $field('name', 'Name', '<input type="text" name="name" id="sprint-form-field-name" class="form-control" value="' . e($r['name']) . '" required maxlength="80">', 'Unique in the project.') ?>
                <?= $field('goal', 'Goal', '<textarea name="goal" id="sprint-form-field-goal" class="form-control" rows="3" maxlength="500">' . e($r['goal'] ?? '') . '</textarea>', 'One sentence the team can say yes or no to at the review.') ?>
                <?= $field('start-date', 'Start', '<input type="date" name="start_date" id="sprint-form-field-start-date" class="form-control" value="' . e($r['start_date'] ?? '') . '" style="max-width:14rem">', 'Optional now; starting the sprint fills it in with today.') ?>
                <?= $field('end-date', 'End', '<input type="date" name="end_date" id="sprint-form-field-end-date" class="form-control" value="' . e($r['end_date'] ?? '') . '" style="max-width:14rem">', 'Optional; starting fills it in ' . (int) $settings['sprint_length_days'] . ' days after the start.') ?>
                <?= $field('sort-order', 'Order', '<input type="number" name="sort_order" id="sprint-form-field-sort-order" class="form-control" value="' . e($r['sort_order'] !== null ? (string) $r['sort_order'] : '') . '" min="0" max="9999" step="1" style="max-width:8rem">', 'Where a future sprint sits among the others in the backlog; blank puts it last.') ?>
            </form>
        </div>
    </div>
</div>
