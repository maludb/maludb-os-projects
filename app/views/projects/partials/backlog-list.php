<?php /** Region #backlog-list: the ranked backlog. Data: project, rows, filters, can, settings, targets, tz */
$key = (string) $project['key']; $base = '/projects/' . $key; $archived = $project['status'] === 'archived';
$pts = 0.0; foreach ($rows as $r) { $pts += (float) ($r['story_points'] ?? 0); }
$filtered = $filters['q'] !== '' || !empty($filters['epic']) || $filters['assignee'] !== '' || $filters['type'] !== '' || $filters['unestimated'];
?>
<div id="backlog-list" hx-get="<?= e($base) ?>/backlog" hx-trigger="backlogChanged from:body, issueChanged from:body, sprintChanged from:body" hx-include="#backlog-filters" hx-swap="outerHTML">
    <div class="d-flex align-items-center mb-2">
        <h6 class="mb-0">Backlog</h6>
        <span class="ms-2 text-muted fs-12" id="backlog-list-count"><?= count($rows) ?> <?= count($rows) === 1 ? 'issue' : 'issues' ?> · <?= e(days_label($pts)) ?> pts<?= $filtered ? ' (filtered)' : '' ?></span>
    </div>
    <div class="list-group backlog-rows" id="backlog-list-rows" style="min-height:2.5rem"
         <?php if ($can['rank'] && !$archived && !$filtered): ?>data-sortable data-sortable-group="backlog" data-sortable-handle=".backlog-row-handle" data-sortable-action="/issues/rank.php" data-sortable-value="" data-sortable-target="#backlog-drop"<?php endif; ?>>
        <?php if ($rows === []): ?><div class="list-group-item text-muted text-center py-4" id="backlog-list-empty" data-empty><?= $filtered ? 'No backlog issue matches.' : 'The backlog is empty.' ?></div><?php endif; ?>
        <?php foreach ($rows as $n => $i): echo view('projects/partials/backlog-row.php', ['i' => $i, 'rows' => $rows, 'n' => $n, 'can' => $can, 'settings' => $settings, 'targets' => $targets, 'inSprint' => null, 'archived' => $archived, 'ranked' => !$filtered]); endforeach; ?>
    </div>
</div>
