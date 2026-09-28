<?php
/** The results region: cards grouped by department + pagination. Data: rows, total, page, pages, filters, sort */
$qs = static function (int $p) use ($filters, $sort): string {
    return '/projects?' . http_build_query(array_filter(['q' => $filters['q'], 'department' => $filters['department'], 'status' => $filters['status'] !== 'active' ? $filters['status'] : null,
        'mine' => !empty($filters['mine']) ? 1 : null, 'sort' => $sort !== 'name' ? $sort : null, 'page' => $p > 1 ? $p : null]));
};
$groups = [];
$none = [];
foreach ($rows as $r) {
    if (($r['department_name'] ?? null) === null || $r['department_name'] === '') { $none[] = $r; } else { $groups[$r['department_name']][] = $r; }
}
ksort($groups, SORT_NATURAL | SORT_FLAG_CASE);
if ($none !== []) { $groups['No department'] = $none; }
?>
<div id="projects-list-results" hx-get="/projects" hx-trigger="projectChanged from:body" hx-include="#projects-list-filters" hx-swap="outerHTML">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <span class="text-muted fs-12" id="projects-list-count"><?= (int) $total ?> <?= $total === 1 ? 'project' : 'projects' ?></span>
    </div>
    <?php if ($rows === []): ?>
        <div class="card"><div class="card-body text-center text-muted py-5" id="projects-list-empty">No project matches. <?= $filters['status'] === 'archived' ? 'Nothing has been archived.' : 'A Projects admin creates one with the button above.' ?></div></div>
    <?php endif; ?>
    <?php $n = 0; foreach ($groups as $department => $list): $n++; ?>
    <section class="mb-4" id="projects-group-<?= $n ?>">
        <div class="d-flex align-items-baseline justify-content-between gap-2 mb-3">
            <h6 class="fw-bold mb-0"><?= e($department) ?></h6>
            <span class="fs-12 text-muted"><?= count($list) ?></span>
        </div>
        <div class="row g-3">
            <?php foreach ($list as $r) { echo view('projects/partials/card.php', ['r' => $r]); } ?>
        </div>
    </section>
    <?php endforeach; ?>
    <?php if ($pages > 1): ?>
    <nav id="projects-list-pagination" aria-label="Pages"><ul class="pagination justify-content-center">
        <?php for ($p = max(1, $page - 3); $p <= min($pages, $page + 3); $p++): ?>
            <li class="page-item <?= $p === $page ? 'active' : '' ?>"><a class="page-link" href="<?= e($qs($p)) ?>" hx-get="<?= e($qs($p)) ?>" hx-target="#projects-list-results" hx-swap="outerHTML" hx-push-url="<?= e($qs($p)) ?>"><?= $p ?></a></li>
        <?php endfor; ?>
    </ul></nav>
    <?php endif; ?>
</div>
