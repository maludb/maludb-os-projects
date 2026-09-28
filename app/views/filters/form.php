<?php /** Saved filter form. Data: filter (null = new), projects, values{name, project_id, shared}, errors{field => msg} */
$isNew = $filter === null; $id = $isNew ? null : (int) $filter['saved_filter_id'];
$keys = $isNew ? [] : $filter['filter'];
?>
<div class="page-header" id="filter-edit-header">
    <div class="page-header-left d-flex align-items-center">
        <div class="page-header-title"><h5 class="m-b-10"><?= $isNew ? 'New filter' : 'Edit filter' ?></h5></div>
        <ul class="breadcrumb">
            <li class="breadcrumb-item"><a href="/my-work" hx-get="/my-work" hx-target="#page-content" hx-push-url="/my-work">My work</a></li>
            <li class="breadcrumb-item"><a href="/filters" hx-get="/filters" hx-target="#page-content" hx-push-url="/filters">Saved filters</a></li>
            <li class="breadcrumb-item"><?= $isNew ? 'New' : e($filter['name']) ?></li>
        </ul>
    </div>
</div>
<div class="main-content" id="filter-edit-content">
    <div class="card"><div class="card-body">
        <?= view('shared/validation-errors.php', ['errors' => array_values($errors)]) ?>
        <?php if ($isNew): ?>
        <p class="text-muted" id="filter-form-new-hint">A new filter starts on a project's issues list: set the filters there and save them under a name. Here you can only name an empty one — it matches every issue until you save it again from the list.</p>
        <?php endif; ?>
        <form id="filter-form" method="post" action="/filters/save.php" hx-post="/filters/save.php" hx-target="#page-content">
            <?= csrf_field() ?>
            <?php if (!$isNew): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif; ?>
            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <label class="form-label" for="filter-form-field-name">Name</label>
                    <input type="text" class="form-control<?= isset($errors['name']) ? ' is-invalid' : '' ?>" id="filter-form-field-name" name="name" maxlength="80" required value="<?= e($values['name']) ?>">
                    <?php if (isset($errors['name'])): ?><div class="invalid-feedback"><?= e($errors['name']) ?></div><?php endif; ?>
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label" for="filter-form-field-project">Project</label>
                    <select class="form-select" id="filter-form-field-project" name="project"><option value="">Any project</option><?php foreach ($projects as $p): ?><option value="<?= (int) $p['project_id'] ?>" <?= (int) ($values['project_id'] ?? 0) === (int) $p['project_id'] ? 'selected' : '' ?>><?= e($p['key']) ?> · <?= e($p['name']) ?></option><?php endforeach; ?></select>
                    <div class="form-text">A filter kept on one project uses that project's statuses, sprints and epics.</div>
                </div>
                <div class="col-12">
                    <div class="form-check"><input type="checkbox" class="form-check-input" id="filter-form-field-shared" name="shared" value="1" <?= $values['shared'] ? 'checked' : '' ?>><label class="form-check-label" for="filter-form-field-shared">Shared — everyone who can see the project may use it</label></div>
                </div>
                <?php if (!$isNew): ?>
                <div class="col-12">
                    <label class="form-label">What it matches</label>
                    <div class="fs-12 text-muted" id="filter-form-keys"><?php if ($keys === []): ?>Every issue.<?php else: ?><?php foreach ($keys as $k => $v): ?><span class="badge bg-soft-secondary text-dark me-1"><?= e(str_replace('_', ' ', (string) $k)) ?>: <?= e(is_array($v) ? implode('/', array_map('strval', $v)) : (is_bool($v) ? ($v ? 'yes' : 'no') : (string) $v)) ?></span><?php endforeach; ?><?php endif; ?></div>
                    <div class="form-text">To change what it matches, apply it on the issues list, adjust the filters and save it again under the same name.</div>
                </div>
                <?php endif; ?>
            </div>
            <div class="d-flex gap-2 mt-4">
                <button type="submit" class="btn btn-primary" id="filter-form-save-btn">Save</button>
                <a href="/filters" class="btn btn-light" hx-get="/filters" hx-target="#page-content" hx-push-url="/filters" id="filter-form-cancel-btn">Cancel</a>
            </div>
        </form>
    </div></div>
</div>
