<?php /** Hygiene: what the Scrum Master flags each morning. Data: rd{rows} */
$rows = $rd['rows'];
$groups = [];
foreach ($rows as $r) { $groups[(string) $r['reason']][] = $r; }
$counts = array_map('count', $groups);
?>
<div id="report-hygiene">
    <p class="fs-13 text-muted" id="report-hygiene-lead"><?php
        if ($rows === []) { echo 'Nothing to flag: nothing stale, unassigned in the sprint, unestimated in the next, or blocked.'; }
        else { $parts = []; foreach ($counts as $reason => $n) { $parts[] = $n . ' ' . (str_starts_with($reason, 'blocked') ? 'blocked' : $reason); } echo e(implode(' · ', $parts)) . '.'; }
    ?></p>
    <?php foreach ($groups as $reason => $items): $head = str_starts_with($reason, 'blocked') ? 'Blocked' : ucfirst($reason); ?>
    <h6 class="fw-bold fs-13 mt-3"><?= e($head) ?> <span class="text-muted fw-normal">(<?= count($items) ?>)</span></h6>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0" id="report-hygiene-table-<?= e(preg_replace('/[^a-z0-9]+/', '-', strtolower($head))) ?>">
            <thead class="thead-light"><tr><th>Issue</th><th>Summary</th><th>Reason</th><th class="text-end">Days</th></tr></thead>
            <tbody>
            <?php foreach ($items as $r): ?>
                <tr id="report-hygiene-row-<?= (int) $r['issue_id'] ?>">
                    <td class="text-nowrap"><a href="/issues/<?= e($r['key']) ?>" hx-get="/issues/<?= e($r['key']) ?>" hx-target="#page-content" hx-push-url="/issues/<?= e($r['key']) ?>"><?= e($r['key']) ?></a></td>
                    <td><?= e($r['summary']) ?></td>
                    <td class="text-muted fs-12"><?= e($r['reason']) ?></td>
                    <td class="text-end"><?= $r['days'] !== null ? (int) $r['days'] : '—' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endforeach; ?>
</div>
