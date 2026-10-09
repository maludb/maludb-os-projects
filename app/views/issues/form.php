<?php /** The issue form — a full screen. Data: record, project, parent, options, settings, members, team, errors */
$r = $record; $isNew = $r['id'] === null; $isSub = ($r['type'] ?? '') === 'subtask' || $parent !== null; $pkey = (string) $project['key'];
$back = $isNew ? ($parent !== null ? '/issues/' . $parent['key'] : '/projects/' . $pkey . '/issues') : '/issues/' . $r['key'];
$nav = static fn (string $url): string => 'href="' . e($url) . '" hx-get="' . e($url) . '" hx-target="#page-content" hx-swap="innerHTML" hx-push-url="' . e($url) . '"';
$field = static fn (string $name, string $label, string $input, string $help = ''): string =>
    '<div class="row mb-3" id="issue-form-field-' . $name . '-row"><label for="issue-form-field-' . $name . '" class="col-lg-3 col-form-label fs-12 text-muted">' . e($label) . '</label><div class="col-lg-9">' . $input . ($help !== '' ? '<div class="form-text">' . e($help) . '</div>' : '') . '</div></div>';
// A record is chosen through the record picker (app/picker.php): the label points at the opener, the hidden input keeps the old name and id.
$pfield = static fn (string $name, string $label, string $input): string =>
    '<div class="row mb-3" id="issue-form-field-' . $name . '-row"><label for="issue-form-field-' . $name . '-open" id="issue-form-field-' . $name . '-label" class="col-lg-3 col-form-label fs-12 text-muted">' . e($label) . '</label><div class="col-lg-9">' . $input . '</div></div>';
$select = static function (string $name, array $options, $current, ?string $blank = null, string $extra = ''): string {
    $h = '<select name="' . $name . '" id="issue-form-field-' . str_replace('_', '-', $name) . '" class="form-select" ' . $extra . '>';
    if ($blank !== null) { $h .= '<option value="">' . e($blank) . '</option>'; }
    foreach ($options as $v => $l) { $h .= '<option value="' . e((string) $v) . '"' . ((string) $current === (string) $v ? ' selected' : '') . '>' . e($l) . '</option>'; }
    return $h . '</select>';
};
$sprints = []; foreach ($options['sprints'] as $s) { $sprints[(int) $s['sprint_id']] = $s['name'] . ($s['state'] === 'active' ? ' (active)' : ''); }
$epics = []; foreach ($options['epics'] as $ep) { $epics[(int) $ep['epic_id']] = $ep['name']; }
$components = []; foreach ($options['components'] as $c) { $components[(int) $c['component_id']] = $c['name']; }
$points = ['' => 'none']; foreach ($settings['point_scale'] as $p) { $points[(string) $p] = (string) $p; }
?>
<div class="page-header" id="issue-form-header">
    <div class="page-header-left d-flex align-items-center">
        <div class="page-header-title"><h5 class="m-b-10"><?= $isNew ? ($isSub ? 'New subtask' : 'New issue') : 'Edit ' . e($r['key']) ?></h5></div>
        <ul class="breadcrumb">
            <li class="breadcrumb-item"><a <?= $nav('/projects') ?>>Projects</a></li>
            <li class="breadcrumb-item"><a <?= $nav('/projects/' . $pkey) ?>><?= e($pkey) ?></a></li>
            <?php if ($parent !== null): ?><li class="breadcrumb-item"><a <?= $nav('/issues/' . $parent['key']) ?>><?= e($parent['key']) ?></a></li><?php endif; ?>
            <li class="breadcrumb-item"><?= $isNew ? 'New' : e($r['key']) ?></li>
        </ul>
    </div>
    <div class="page-header-right ms-auto">
        <div class="page-header-right-items">
            <div class="d-flex d-md-none"><a href="javascript:void(0)" class="page-header-right-close-toggle"><i class="feather-arrow-left me-2"></i><span>Back</span></a></div>
            <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                <a <?= $nav($back) ?> id="issue-form-cancel-btn" class="btn btn-light-brand"><i class="feather-x me-2"></i><span>Cancel</span></a>
                <button type="submit" form="issue-form" id="issue-form-save-btn" class="btn btn-primary"><i class="feather-save me-2"></i><span><?= $isNew ? 'Create' : 'Save' ?></span></button>
            </div>
        </div>
        <div class="d-md-none d-flex align-items-center"><a href="javascript:void(0)" class="page-header-right-open-toggle"><i class="feather-align-right fs-20"></i></a></div>
    </div>
</div>
<div class="main-content" id="issue-form-content">
    <div class="card stretch stretch-full" id="issue-form-container">
        <div class="card-body">
            <?= view('shared/validation-errors.php', ['errors' => $errors]) ?>
            <form method="post" action="/issues/save.php" id="issue-form" hx-post="/issues/save.php" hx-target="#page-content" hx-swap="innerHTML">
                <?= csrf_field() ?>
                <?php if ($isNew): ?><input type="hidden" name="project" id="issue-form-field-project" value="<?= e($pkey) ?>"><?php else: ?><input type="hidden" name="issue" value="<?= (int) $r['id'] ?>"><?php endif; ?>
                <?php if ($parent !== null): ?><input type="hidden" name="parent" id="issue-form-field-parent" value="<?= (int) $parent['issue_id'] ?>"><input type="hidden" name="type" value="subtask"><?php endif; ?>
                <?php if (!$isSub): ?>
                <?= $field('type', 'Type', $select('type', ['story' => 'Story', 'task' => 'Task', 'bug' => 'Bug'], $r['type'], null, 'required')) ?>
                <?php else: ?><?= $field('type', 'Type', '<span class="form-control-plaintext">Subtask of ' . e($parent['key'] ?? '') . '</span>') ?><?php endif; ?>
                <?= $field('summary', 'Summary', '<input type="text" name="summary" id="issue-form-field-summary" class="form-control" value="' . e($r['summary'] ?? '') . '" maxlength="255" required>') ?>
                <?= $field('description', 'Description', '<textarea name="description" id="issue-form-field-description" class="form-control" rows="8">' . e($r['description'] ?? '') . '</textarea>', 'Markdown: paragraphs, **bold**, *italic*, `code`, lists, links, and issue keys become links.') ?>
                <?= $pfield('assignee', 'Assignee', picker_field(['id' => 'issue-form-field-assignee', 'name' => 'assignee', 'source' => 'member', 'value' => $r['assignee_member_id'],
                    'placeholder' => 'Unassigned', 'title' => 'Assignee', 'search' => 'Search people…', 'params' => ['project_id' => (int) $project['project_id']],
                    'labelledby' => 'issue-form-field-assignee-label'])) ?>
                <?= $field('priority', 'Priority', $select('priority', array_combine(ISSUE_PRIORITIES, array_map('ucfirst', ISSUE_PRIORITIES)), $r['priority'] ?? 'medium', null, 'required')) ?>
                <?php if (!$isSub): ?>
                <?= $field('story-points', 'Story points', $select('story_points', $points, $r['story_points'] !== null ? (string) (float) $r['story_points'] : '')) ?>
                <?php endif; ?>
                <?= $field('estimate-hours', 'Estimate (hours)', '<input type="number" name="estimate_hours" id="issue-form-field-estimate-hours" class="form-control" step="0.25" min="0" value="' . e($r['estimate_hours'] !== null ? (string) (float) $r['estimate_hours'] : '') . '">') ?>
                <?php if (!$isSub): ?>
                <?= $field('epic', 'Epic', $select('epic', $epics, $r['epic_id'], 'No epic')) ?>
                <?= $field('sprint', 'Sprint', $select('sprint', $sprints, $r['sprint_id'], 'Backlog')) ?>
                <?php endif; ?>
                <?= $field('component', 'Component', $select('component', $components, $r['component_id'], 'None'), 'A component with a default assignee assigns the issue when no assignee is chosen.') ?>
                <?php $labelsInput = '<select name="labels[]" id="issue-form-field-labels" class="form-select" multiple size="' . max(2, min(6, count($options['labels']))) . '">';
                foreach ($options['labels'] as $l) { $labelsInput .= '<option value="' . (int) $l['label_id'] . '"' . (in_array((int) $l['label_id'], $r['labels'] ?? [], true) ? ' selected' : '') . '>' . e($l['name']) . '</option>'; }
                $labelsInput .= '</select>'; ?>
                <?= $field('labels', 'Labels', $options['labels'] === [] ? '<span class="form-control-plaintext text-muted fs-12">No labels yet — a project admin adds them in Settings.</span>' : $labelsInput) ?>
                <?= $field('due-date', 'Due', '<input type="date" name="due_date" id="issue-form-field-due-date" class="form-control" value="' . e($r['due_date'] ?? '') . '">') ?>
            </form>
        </div>
    </div>
</div>
