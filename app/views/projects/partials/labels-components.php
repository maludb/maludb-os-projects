<?php /** Labels and components (settings section `labels`, region #project-labels-components). Data: project, labels, components, members, canAdmin */
$p = $project; $key = (string) $p['key']; $pid = (int) $p['project_id']; $url = '/projects/' . $key . '/settings?section=labels';
$post = static fn (string $action, string $extra = ''): string => 'method="post" action="' . e($action) . '" hx-post="' . e($action) . '" hx-swap="none" ' . $extra;
?>
<div id="project-labels-components" hx-get="<?= e($url) ?>" hx-trigger="labelsChanged from:body" hx-swap="outerHTML">
    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card mb-0 h-100" id="project-labels">
                <div class="card-header"><h5 class="card-title mb-0">Labels <span class="text-muted fw-normal fs-12">(<?= count(array_filter($labels, static fn (array $l): bool => $l['archived_at'] === null)) ?>)</span></h5></div>
                <div class="card-body">
                    <p class="text-muted fs-12">Free tags on issues; lower-case, one word. Archiving keeps a label on its issues and takes it out of the pickers.</p>
                    <ul class="list-unstyled mb-3" id="project-labels-list">
                        <?php foreach ($labels as $l): $lid = (int) $l['label_id']; ?>
                        <li class="d-flex align-items-center gap-2 py-1 border-bottom" id="project-label-<?= $lid ?>">
                            <span class="badge bg-soft-<?= e($l['colour']) ?> text-<?= e($l['colour']) ?>"><?= e($l['name']) ?></span>
                            <span class="text-muted fs-12"><?= (int) $l['issues'] ?> issue<?= (int) $l['issues'] === 1 ? '' : 's' ?></span>
                            <?php if ($l['archived_at'] !== null): ?><span class="badge bg-soft-secondary text-secondary">archived</span><?php endif; ?>
                            <?php if ($canAdmin && $l['archived_at'] === null): ?>
                            <form <?= $post('/labels/archive.php', 'hx-confirm="Archive label ' . e($l['name']) . '? Its issues keep it."') ?> class="ms-auto"><?= csrf_field() ?><input type="hidden" name="label" value="<?= $lid ?>">
                                <button type="submit" class="btn btn-sm btn-light-secondary" id="project-label-<?= $lid ?>-archive-btn" aria-label="Archive <?= e($l['name']) ?>"><i class="feather-archive"></i></button></form>
                            <?php endif; ?>
                        </li>
                        <?php endforeach; ?>
                        <?php if ($labels === []): ?><li class="text-muted fs-12" id="project-labels-empty">No label yet.</li><?php endif; ?>
                    </ul>
                    <?php if ($canAdmin): ?>
                    <form <?= $post('/labels/save.php') ?> id="label-form" class="row g-2 align-items-end">
                        <?= csrf_field() ?><input type="hidden" name="project" value="<?= $pid ?>">
                        <div class="col-8 col-md-7"><label for="label-form-field-name" class="form-label fs-12 text-muted">New label</label><input type="text" name="name" id="label-form-field-name" class="form-control form-control-sm" required maxlength="40" pattern="[a-z0-9][a-z0-9_-]*" placeholder="e.g. frontend"></div>
                        <div class="col-4 col-md-3"><label for="label-form-field-colour" class="form-label fs-12 text-muted">Colour</label><select name="colour" id="label-form-field-colour" class="form-select form-select-sm"><?php foreach (LABEL_COLOURS as $c): ?><option value="<?= $c ?>" <?= $c === 'secondary' ? 'selected' : '' ?>><?= ucfirst($c) ?></option><?php endforeach; ?></select></div>
                        <div class="col-12 col-md-2"><button type="submit" class="btn btn-sm btn-primary w-100" id="label-form-save-btn"><i class="feather-plus"></i></button></div>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card mb-0 h-100" id="project-components">
                <div class="card-header"><h5 class="card-title mb-0">Components <span class="text-muted fw-normal fs-12">(<?= count(array_filter($components, static fn (array $c): bool => $c['archived_at'] === null)) ?>)</span></h5></div>
                <div class="card-body">
                    <p class="text-muted fs-12">Named parts of the product. A component may carry a default assignee for new issues.</p>
                    <ul class="list-unstyled mb-3" id="project-components-list">
                        <?php foreach ($components as $c): $cid = (int) $c['component_id']; ?>
                        <li class="d-flex align-items-center gap-2 py-1 border-bottom" id="project-component-<?= $cid ?>">
                            <span class="fw-semibold"><?= e($c['name']) ?></span>
                            <?php if (!empty($c['description'])): ?><span class="text-muted fs-12 text-truncate"><?= e($c['description']) ?></span><?php endif; ?>
                            <?php if ($c['default_assignee_id'] !== null): ?><span class="text-muted fs-12">→ <?= e($c['default_assignee_name'] ?? '?') ?></span><?php endif; ?>
                            <?php if ($c['archived_at'] !== null): ?><span class="badge bg-soft-secondary text-secondary">archived</span><?php endif; ?>
                            <?php if ($canAdmin && $c['archived_at'] === null): ?>
                            <form <?= $post('/components/archive.php', 'hx-confirm="Archive component ' . e($c['name']) . '? Its issues keep it."') ?> class="ms-auto"><?= csrf_field() ?><input type="hidden" name="component" value="<?= $cid ?>">
                                <button type="submit" class="btn btn-sm btn-light-secondary" id="project-component-<?= $cid ?>-archive-btn" aria-label="Archive <?= e($c['name']) ?>"><i class="feather-archive"></i></button></form>
                            <?php endif; ?>
                        </li>
                        <?php endforeach; ?>
                        <?php if ($components === []): ?><li class="text-muted fs-12" id="project-components-empty">No component yet.</li><?php endif; ?>
                    </ul>
                    <?php if ($canAdmin): ?>
                    <form <?= $post('/components/save.php') ?> id="component-form" class="row g-2 align-items-end">
                        <?= csrf_field() ?><input type="hidden" name="project" value="<?= $pid ?>">
                        <div class="col-12 col-md-5"><label for="component-form-field-name" class="form-label fs-12 text-muted">New component</label><input type="text" name="name" id="component-form-field-name" class="form-control form-control-sm" required maxlength="60"></div>
                        <div class="col-12 col-md-7"><label for="component-form-field-description" class="form-label fs-12 text-muted">Description</label><input type="text" name="description" id="component-form-field-description" class="form-control form-control-sm" maxlength="240"></div>
                        <div class="col-9"><label for="component-form-field-default-assignee" class="form-label fs-12 text-muted">Default assignee</label><select name="default_assignee" id="component-form-field-default-assignee" class="form-select form-select-sm"><option value="">None</option><?php foreach ($members as $m): ?><option value="<?= (int) $m['member_id'] ?>"><?= e($m['display_name']) ?><?= $m['member_kind'] === 'agent' ? ' (agent)' : '' ?></option><?php endforeach; ?></select></div>
                        <div class="col-3"><button type="submit" class="btn btn-sm btn-primary w-100" id="component-form-save-btn"><i class="feather-plus"></i></button></div>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
