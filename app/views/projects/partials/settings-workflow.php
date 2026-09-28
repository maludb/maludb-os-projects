<?php /** Workflow: the statuses table and the transitions matrix, saved together (workflow_save) — or one blank status row when rowOnly is set (Pattern A). Data: project, workflow, errors, canAdmin, rowOnly? */
$p = $project; $key = (string) $p['key']; $id = (int) $p['project_id']; $wf = $workflow; $statuses = array_values($wf['statuses']); $n = count($statuses);
$row = static function (int $i, ?array $s, array $statuses) use ($p): string {
    $sid = $s !== null ? (int) $s['status_id'] : null; $issues = (int) ($s['issues'] ?? 0);
    $h = '<tr id="workflow-form-row-' . $i . '" class="workflow-form-row" data-index="' . $i . '">';
    $h .= '<td class="text-center"><input type="radio" name="initial" value="' . $i . '" id="workflow-form-field-initial-' . $i . '" class="form-check-input"' . (!empty($s['is_initial']) ? ' checked' : '') . ' aria-label="New issues start here"></td>';
    $h .= '<td><input type="hidden" name="statuses[' . $i . '][id]" id="workflow-form-field-status-id-' . $i . '" value="' . ($sid ?? '') . '">'
        . '<input type="text" name="statuses[' . $i . '][name]" id="workflow-form-field-status-name-' . $i . '" class="form-control form-control-sm" value="' . e($s['name'] ?? '') . '" required maxlength="40" placeholder="Status name">'
        . ($issues > 0 ? '<div class="fs-11 text-muted mt-1">' . $issues . ' issue' . ($issues === 1 ? '' : 's') . '</div>' : '') . '</td>';
    $h .= '<td><select name="statuses[' . $i . '][category]" id="workflow-form-field-status-category-' . $i . '" class="form-select form-select-sm">';
    foreach (WORKFLOW_CATEGORIES as $v => $l) { $h .= '<option value="' . $v . '"' . (($s['category'] ?? 'todo') === $v ? ' selected' : '') . '>' . e($l) . '</option>'; }
    $h .= '</select></td>';
    $h .= '<td><input type="number" name="statuses[' . $i . '][sort_order]" id="workflow-form-field-status-order-' . $i . '" class="form-control form-control-sm" value="' . (int) ($s['sort_order'] ?? ($i + 1) * 10) . '" step="1" required style="width:5.5rem" aria-label="Order"></td>';
    if ($sid !== null) {
        $h .= '<td class="text-nowrap"><div class="form-check d-inline-block me-1"><input type="checkbox" name="archive[' . $i . ']" value="1" id="workflow-form-field-archive-' . $i . '" class="form-check-input" aria-label="Archive ' . e($s['name']) . '"></div>';
        if ($issues > 0) {
            $h .= '<select name="archive_to[' . $i . ']" id="workflow-form-field-archive-to-' . $i . '" class="form-select form-select-sm d-inline-block" style="width:auto" aria-label="Move its issues to"><option value="">move its issues to…</option>';
            foreach ($statuses as $j => $o) { if ((int) $o['status_id'] !== $sid) { $h .= '<option value="' . $j . '">' . e($o['name']) . '</option>'; } }
            $h .= '</select>';
        }
        $h .= '</td>';
    } else {
        $h .= '<td class="text-end"><button type="button" class="btn btn-sm btn-light-brand text-danger" onclick="this.closest(\'tr\').remove()" aria-label="Remove row"><i class="feather-x"></i></button></td>';
    }
    return $h . '</tr>';
};
if (isset($rowOnly)) { echo $row((int) $rowOnly, null, $statuses); return; }
$tone = static fn (string $c): string => ['todo' => 'secondary', 'in_progress' => 'primary', 'done' => 'success'][$c] ?? 'secondary';
?>
<div class="card mb-0" id="project-settings-workflow">
    <div class="card-header d-flex align-items-center justify-content-between">
        <h5 class="card-title mb-0">Workflow</h5>
        <?php if ($canAdmin): ?><button type="submit" form="workflow-form" id="workflow-form-save-btn" class="btn btn-sm btn-primary"><i class="feather-save me-1"></i>Save workflow</button><?php endif; ?>
    </div>
    <div class="card-body">
        <?= view('shared/validation-errors.php', ['errors' => $errors]) ?>
        <p class="fs-12 text-muted">The statuses an issue moves through, each in a category the board and the reports read, and the moves the workflow allows. Renaming keeps the history true. Archiving a status that holds issues asks where they go — one logged move per issue. Exactly one status is where new issues start.</p>
        <form method="post" action="/workflow/save.php" id="workflow-form" hx-post="/workflow/save.php" hx-target="#page-content" hx-swap="innerHTML">
            <?= csrf_field() ?>
            <input type="hidden" name="project" value="<?= $id ?>">
            <fieldset <?= $canAdmin ? '' : 'disabled' ?>>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-2" id="workflow-form-statuses">
                    <thead class="thead-light"><tr><th class="text-center" title="New issues start here">Start</th><th style="min-width:180px">Status</th><th style="min-width:140px">Category</th><th>Order</th><th>Archive</th></tr></thead>
                    <tbody id="workflow-form-rows"><?php foreach ($statuses as $i => $s) { echo $row($i, $s, $statuses); } ?></tbody>
                </table>
            </div>
            <button type="button" class="btn btn-sm btn-light-brand mb-4" id="workflow-form-add-row"
                    hx-get="/projects/<?= e($key) ?>/settings?tab=workflow&fragment=row" hx-target="#workflow-form-rows" hx-swap="beforeend"
                    hx-vals='js:{n: 100 + document.querySelectorAll("#workflow-form-rows tr").length + Date.now() % 1000}'><i class="feather-plus me-1"></i>Add status</button>
            <h6 class="fs-13 mb-2" id="workflow-form-transitions-title">Allowed moves <span class="text-muted fw-normal fs-12">— from the row to the column</span></h6>
            <div class="table-responsive" id="workflow-form-transitions-wrap">
                <table class="table table-sm table-bordered text-center align-middle mb-2 fs-12" id="workflow-form-transitions">
                    <thead class="thead-light"><tr><th class="text-start">From ↓ / To →</th><?php foreach ($statuses as $j => $t): ?><th><span class="badge bg-soft-<?= $tone((string) $t['category']) ?> text-<?= $tone((string) $t['category']) ?>"><?= e($t['name']) ?></span></th><?php endforeach; ?></tr></thead>
                    <tbody>
                    <?php foreach ($statuses as $i => $s): $from = (int) $s['status_id']; ?>
                        <tr id="workflow-form-transition-row-<?= $i ?>"><th class="text-start fw-semibold"><?= e($s['name']) ?></th>
                        <?php foreach ($statuses as $j => $t): $to = (int) $t['status_id']; ?>
                            <td class="<?= $i === $j ? 'bg-light' : '' ?>"><?php if ($i !== $j): ?><input type="checkbox" name="transitions[<?= $i ?>][<?= $j ?>]" value="1" id="workflow-form-field-transition-<?= $i ?>-<?= $j ?>" class="form-check-input"
                                <?= in_array($to, $wf['transitions'][$from] ?? [], true) ? 'checked' : '' ?> aria-label="<?= e($s['name']) ?> to <?= e($t['name']) ?>"><?php endif; ?></td>
                        <?php endforeach; ?></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="fs-11 text-muted mb-0">A status added above gets its moves after the first save — it then appears in this grid.</p>
            </fieldset>
        </form>
        <?php if ($wf['archived'] !== []): ?>
        <hr><h6 class="fs-13 mb-2" id="workflow-archived-title">Archived statuses</h6>
        <div class="d-flex flex-wrap gap-1" id="workflow-archived">
            <?php foreach ($wf['archived'] as $a): ?><span class="badge bg-soft-secondary text-secondary" id="workflow-archived-<?= (int) $a['status_id'] ?>"><?= e($a['name']) ?> · <?= e(format_date($a['archived_at'])) ?></span><?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
