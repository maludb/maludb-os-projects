<?php /** Saved filters. Data: rows, me, projectId, projects, tz */
$describe = static function (array $f): string {
    $parts = [];
    foreach ($f as $k => $v) { $parts[] = str_replace('_', ' ', (string) $k) . ': ' . (is_array($v) ? implode('/', array_map('strval', $v)) : (is_bool($v) ? ($v ? 'yes' : 'no') : (string) $v)); }
    return $parts === [] ? 'every issue' : implode(' · ', $parts);
};
?>
<div class="page-header" id="filters-list-header">
    <div class="page-header-left d-flex align-items-center">
        <div class="page-header-title"><h5 class="m-b-10">Saved filters</h5></div>
        <ul class="breadcrumb">
            <li class="breadcrumb-item"><a href="/my-work" hx-get="/my-work" hx-target="#page-content" hx-push-url="/my-work">My work</a></li>
            <li class="breadcrumb-item">Saved filters</li>
        </ul>
    </div>
    <div class="page-header-right ms-auto">
        <div class="page-header-right-items">
            <div class="d-flex d-md-none"><a href="javascript:void(0)" class="page-header-right-close-toggle"><i class="feather-arrow-left me-2"></i><span>Back</span></a></div>
            <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                <form id="filters-list-filters" method="get" action="/filters" hx-get="/filters" hx-target="#page-content" hx-push-url="true" hx-trigger="change">
                    <select name="project" id="filters-list-project" class="form-select" aria-label="Project"><option value="">Every project</option><?php foreach ($projects as $p): ?><option value="<?= (int) $p['project_id'] ?>" <?= $projectId === (int) $p['project_id'] ? 'selected' : '' ?>><?= e($p['key']) ?> · <?= e($p['name']) ?></option><?php endforeach; ?></select>
                </form>
                <a href="/filters/new" id="filters-list-new-btn" class="btn btn-primary" hx-get="/filters/new" hx-target="#page-content" hx-push-url="/filters/new"><i class="feather-plus me-2"></i><span>New filter</span></a>
            </div>
        </div>
        <div class="d-md-none d-flex align-items-center"><a href="javascript:void(0)" class="page-header-right-open-toggle"><i class="feather-align-right fs-20"></i></a></div>
    </div>
</div>
<div class="main-content" id="filters-list-content">
    <p class="text-muted fs-12" id="filters-list-hint">A filter is saved from a project's issues list ("Save this filter as…"). Here you rename it, move it to another project, share it or remove it. A filter with no project appears in every project's list.</p>
    <div class="row" id="filters-list-results">
        <?php if ($rows === []): ?>
            <div class="col-12"><div class="card"><div class="card-body text-center text-muted py-5" id="filters-list-empty">No saved filters yet. Open a project's issues, set the filters you want and save them under a name.</div></div></div>
        <?php endif; ?>
        <?php foreach ($rows as $f): $mine = (int) $f['owner_id'] === $me; $href = $f['project_id'] !== null ? saved_filter_href($f) : null; ?>
        <div class="col-12 col-md-6 col-xl-4" id="filter-card-<?= (int) $f['saved_filter_id'] ?>">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between gap-2">
                        <div>
                            <h6 class="mb-1"><?php if ($href !== null): ?><a href="<?= e($href) ?>" hx-get="<?= e($href) ?>" hx-target="#page-content" hx-push-url="<?= e($href) ?>" class="text-dark"><?= e($f['name']) ?></a><?php else: ?><?= e($f['name']) ?><?php endif; ?></h6>
                            <div class="fs-12 text-muted"><?= $f['project_id'] !== null ? '<span class="badge bg-soft-secondary text-dark">' . e($f['project_key']) . '</span>' : '<span class="badge bg-soft-secondary text-dark">Any project</span>' ?>
                                <?= $f['is_shared'] ? '<span class="badge bg-soft-info text-info ms-1">shared</span>' : '<span class="badge bg-soft-secondary text-muted ms-1">private</span>' ?>
                                <?= $mine ? '' : ' <span class="ms-1">by ' . e($f['owner_name']) . '</span>' ?></div>
                        </div>
                        <?php if ($mine): ?>
                        <div class="d-flex gap-1">
                            <a href="/filters/<?= (int) $f['saved_filter_id'] ?>/edit" class="btn btn-sm btn-light-brand" id="filter-edit-<?= (int) $f['saved_filter_id'] ?>" hx-get="/filters/<?= (int) $f['saved_filter_id'] ?>/edit" hx-target="#page-content" hx-push-url="/filters/<?= (int) $f['saved_filter_id'] ?>/edit" aria-label="Edit"><i class="feather-edit-2"></i></a>
                            <form method="post" action="/filters/delete.php" hx-post="/filters/delete.php" hx-target="#page-content" hx-confirm="Delete the filter <?= e($f['name']) ?>?" class="d-inline" id="filter-delete-form-<?= (int) $f['saved_filter_id'] ?>">
                                <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $f['saved_filter_id'] ?>">
                                <button type="submit" class="btn btn-sm btn-light-danger" id="filter-delete-<?= (int) $f['saved_filter_id'] ?>" aria-label="Delete"><i class="feather-trash-2"></i></button>
                            </form>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="fs-12 mt-2 text-muted" id="filter-keys-<?= (int) $f['saved_filter_id'] ?>"><?= e($describe($f['filter'])) ?></div>
                    <?php if ($href !== null): ?><a href="<?= e($href) ?>" hx-get="<?= e($href) ?>" hx-target="#page-content" hx-push-url="<?= e($href) ?>" class="fs-12 fw-semibold d-inline-block mt-2" id="filter-apply-<?= (int) $f['saved_filter_id'] ?>">Open in <?= e($f['project_key']) ?> <i class="feather-arrow-right"></i></a>
                    <?php else: ?><span class="fs-12 text-muted d-inline-block mt-2">Pick it from "Saved filters…" on any project's issues list.</span><?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
