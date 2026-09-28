<?php /** Data: issue, project, regions, canWork, canAdmin, canDelete, … (see html/issues/view.php) */
$i = $issue; $pkey = (string) $i['project_key']; $key = (string) $i['key'];
$nav = static fn (string $url): string => 'href="' . e($url) . '" hx-get="' . e($url) . '" hx-target="#page-content" hx-swap="innerHTML" hx-push-url="' . e($url) . '"';
?>
<div class="page-header" id="issue-view-header">
    <div class="page-header-left d-flex align-items-center">
        <div class="page-header-title"><h5 class="m-b-10"><i class="<?= e(issue_type_icon((string) $i['type'])) ?> me-2 text-muted"></i><?= e($key) ?></h5></div>
        <ul class="breadcrumb">
            <li class="breadcrumb-item"><a <?= $nav('/projects') ?>>Projects</a></li>
            <li class="breadcrumb-item"><a <?= $nav('/projects/' . $pkey) ?>><?= e($pkey) ?></a></li>
            <li class="breadcrumb-item"><a <?= $nav('/projects/' . $pkey . '/issues') ?>>Issues</a></li>
            <li class="breadcrumb-item"><?= e($key) ?></li>
        </ul>
    </div>
    <div class="page-header-right ms-auto">
        <div class="page-header-right-items">
            <div class="d-flex d-md-none"><a href="javascript:void(0)" class="page-header-right-close-toggle"><i class="feather-arrow-left me-2"></i><span>Back</span></a></div>
            <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                <?php if ($canWork && $i['type'] !== 'subtask'): ?>
                <a <?= $nav('/issues/new?project=' . $pkey . '&parent=' . $key) ?> id="issue-view-subtask-btn" class="btn btn-light-brand"><i class="feather-corner-down-right me-2"></i><span>Subtask</span></a>
                <?php endif; ?>
                <?php if ($canWork): ?>
                <a <?= $nav('/issues/' . $key . '/edit') ?> id="issue-view-edit-btn" class="btn btn-primary"><i class="feather-edit-3 me-2"></i><span>Edit</span></a>
                <?php endif; ?>
            </div>
        </div>
        <div class="d-md-none d-flex align-items-center"><a href="javascript:void(0)" class="page-header-right-open-toggle"><i class="feather-align-right fs-20"></i></a></div>
    </div>
</div>
<div class="main-content" id="issue-view-content">
    <div class="row">
        <div class="col-xl-8">
            <div class="card" id="issue-body-card">
                <div class="card-body">
                    <?php if ($canWork): ?>
                    <form hx-post="/issues/save.php" hx-trigger="submit" hx-swap="none" id="issue-summary-form" class="mb-2">
                        <?= csrf_field() ?><input type="hidden" name="issue" value="<?= (int) $i['issue_id'] ?>">
                        <input type="text" name="summary" id="issue-view-summary" class="form-control form-control-lg fw-bold border-0 px-0" value="<?= e($i['summary']) ?>" maxlength="255" required
                               hx-post="/issues/save.php" hx-trigger="change" hx-include="#issue-summary-form" hx-swap="none" aria-label="Summary">
                    </form>
                    <?php else: ?>
                    <h4 class="fw-bold mb-2" id="issue-view-summary"><?= e($i['summary']) ?></h4>
                    <?php endif; ?>
                    <?php if (!empty($i['parent_key'])): ?><div class="fs-12 text-muted mb-2">Subtask of <a <?= $nav('/issues/' . $i['parent_key']) ?>><?= e($i['parent_key']) ?></a></div><?php endif; ?>
                    <div id="issue-view-description" class="issue-description">
                        <?= ($i['description'] ?? '') !== '' ? markdown_to_html((string) $i['description']) : '<p class="text-muted fs-12 mb-0">No description.</p>' ?>
                    </div>
                </div>
            </div>
            <?php if ($i['type'] !== 'subtask') { echo $regions['subtasks'](); } ?>
            <?= $regions['links']() ?>
            <?= $regions['attachments']() ?>
            <?= $regions['checklist']() ?>
            <?= $regions['worklogs']() ?>
            <?= $regions['comments']() ?>
            <?= $regions['history']() ?>
        </div>
        <div class="col-xl-4 order-first order-xl-0">
            <?= $regions['fields']() ?>
        </div>
    </div>
</div>
