<?php /** The board's settings form (screen board-settings) — or one blank row when rowOnly is set (Pattern A). Data: project, config, errors, rowOnly? */
$p = $project; $key = (string) $p['key']; $base = '/projects/' . $key;
$statuses = $config['statuses'];
$row = static function (int $n, array $c) use ($statuses): string {
    $sel = array_map('intval', $c['status_ids'] ?? []);
    $h = '<tr id="board-form-row-' . $n . '" class="board-form-row"><td><input type="text" name="columns[' . $n . '][name]" id="board-form-field-column-name-' . $n . '" class="form-control form-control-sm" value="' . e($c['name'] ?? '') . '" maxlength="40" placeholder="Column name" required></td>';
    $h .= '<td><select name="columns[' . $n . '][statuses][]" id="board-form-field-column-statuses-' . $n . '" class="form-select form-select-sm" multiple size="' . max(2, min(6, count($statuses))) . '" aria-label="Statuses in this column">';
    foreach ($statuses as $s) { $h .= '<option value="' . (int) $s['status_id'] . '"' . (in_array((int) $s['status_id'], $sel, true) ? ' selected' : '') . '>' . e($s['name']) . '</option>'; }
    $h .= '</select></td><td><input type="number" name="columns[' . $n . '][wip_limit]" id="board-form-field-column-wip-' . $n . '" class="form-control form-control-sm" min="1" step="1" value="' . e($c['wip_limit'] !== null ? (string) $c['wip_limit'] : '') . '" placeholder="none" aria-label="WIP limit"></td>';
    $h .= '<td class="text-end"><button type="button" class="btn btn-sm btn-light-brand text-danger" id="board-form-remove-' . $n . '" onclick="this.closest(\'tr\').remove()" aria-label="Remove column"><i class="feather-x"></i></button></td></tr>';
    return $h;
};
if (isset($rowOnly)) { echo $row((int) $rowOnly, ['name' => '', 'status_ids' => [], 'wip_limit' => null]); return; }
?>
<div class="page-header" id="board-form-header">
    <div class="page-header-left d-flex align-items-center">
        <div class="page-header-title"><h5 class="m-b-10">Board settings · <?= e($key) ?></h5></div>
        <ul class="breadcrumb">
            <li class="breadcrumb-item"><a href="/projects" hx-get="/projects" hx-target="#page-content" hx-push-url="/projects">Projects</a></li>
            <li class="breadcrumb-item"><a href="<?= e($base) ?>" hx-get="<?= e($base) ?>" hx-target="#page-content" hx-push-url="<?= e($base) ?>"><?= e($key) ?></a></li>
            <li class="breadcrumb-item"><a href="<?= e($base) ?>/board" hx-get="<?= e($base) ?>/board" hx-target="#page-content" hx-push-url="<?= e($base) ?>/board">Board</a></li>
            <li class="breadcrumb-item">Settings</li>
        </ul>
    </div>
    <div class="page-header-right ms-auto">
        <div class="page-header-right-items">
            <div class="d-flex d-md-none"><a href="javascript:void(0)" class="page-header-right-close-toggle"><i class="feather-arrow-left me-2"></i><span>Back</span></a></div>
            <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                <a href="<?= e($base) ?>/board" id="board-form-cancel-btn" class="btn btn-light-brand" hx-get="<?= e($base) ?>/board" hx-target="#page-content" hx-push-url="<?= e($base) ?>/board"><i class="feather-x me-2"></i><span>Cancel</span></a>
                <button type="submit" form="board-form" id="board-form-save-btn" class="btn btn-primary"><i class="feather-save me-2"></i><span>Save</span></button>
            </div>
        </div>
        <div class="d-md-none d-flex align-items-center"><a href="javascript:void(0)" class="page-header-right-open-toggle"><i class="feather-align-right fs-20"></i></a></div>
    </div>
</div>
<div class="main-content" id="board-form-content">
    <div class="card stretch stretch-full" id="board-form-container">
        <div class="card-body">
            <?= view('shared/validation-errors.php', ['errors' => $errors]) ?>
            <form method="post" action="/board/save.php" id="board-form" hx-post="/board/save.php" hx-target="#page-content" hx-swap="innerHTML">
                <?= csrf_field() ?>
                <input type="hidden" name="project" value="<?= (int) $p['project_id'] ?>">
                <div class="row mb-3">
                    <div class="col-lg-6">
                        <label for="board-form-field-board-mode" class="form-label fs-12 text-muted">Board mode</label>
                        <select name="board_mode" id="board-form-field-board-mode" class="form-select">
                            <option value="scrum" <?= $config['board_mode'] === 'scrum' ? 'selected' : '' ?>>Scrum — the active sprint</option>
                            <option value="kanban" <?= $config['board_mode'] === 'kanban' ? 'selected' : '' ?>>Kanban — every open issue</option>
                        </select>
                    </div>
                    <div class="col-lg-6">
                        <label for="board-form-field-swimlane" class="form-label fs-12 text-muted">Swimlanes</label>
                        <select name="swimlane" id="board-form-field-swimlane" class="form-select">
                            <?php foreach (['none' => 'None', 'assignee' => 'By assignee', 'epic' => 'By epic'] as $v => $l): ?><option value="<?= e($v) ?>" <?= $config['swimlane'] === $v ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-2" id="board-form-columns">
                        <thead class="thead-light"><tr><th style="min-width: 160px;">Column</th><th style="min-width: 200px;">Statuses</th><th style="width: 110px;">WIP limit</th><th></th></tr></thead>
                        <tbody id="board-form-rows">
                            <?php foreach (array_values($config['columns']) as $n => $c) { echo $row($n, $c); } ?>
                        </tbody>
                    </table>
                </div>
                <button type="button" class="btn btn-sm btn-light-brand" id="board-form-add-row"
                        hx-get="/board/form.php?key=<?= e($key) ?>&fragment=row" hx-target="#board-form-rows" hx-swap="beforeend"
                        hx-vals='js:{n: document.querySelectorAll("#board-form-rows tr").length + Date.now() % 1000}'><i class="feather-plus me-1"></i>Add column</button>
                <p class="fs-12 text-muted mt-3 mb-0">Every status of the workflow sits in exactly one column. A WIP limit warns when a column holds more than it — it never blocks a move; the Scrum Master flags it.</p>
            </form>
        </div>
    </div>
</div>
