<?php /** Region #issue-links. Data: issue, links, canWork */
$key = (string) $issue['key']; $id = (int) $issue['issue_id']; $nav = static fn (string $url): string => 'href="' . e($url) . '" hx-get="' . e($url) . '" hx-target="#page-content" hx-swap="innerHTML" hx-push-url="' . e($url) . '"';
$groups = [];
foreach ($links as $l) {
    $outgoing = (int) $l['from_issue_id'] === $id;
    $label = match ($l['kind']) { 'blocks' => $outgoing ? 'blocks' : 'is blocked by', 'duplicates' => $outgoing ? 'duplicates' : 'is duplicated by', default => 'relates to' };
    $groups[$label][] = $l + ['other_key' => $outgoing ? $l['to_key'] : $l['from_key'], 'other_summary' => $outgoing ? $l['to_summary'] : $l['from_summary'], 'other_done' => ($outgoing ? $l['to_status_category'] : $l['from_status_category']) === 'done'];
} ?>
<div class="card" id="issue-links" hx-get="/issues/view.php?key=<?= e($key) ?>&region=links" hx-trigger="issueChanged from:body" hx-swap="outerHTML">
    <div class="card-header"><h5 class="card-title mb-0">Links <span class="text-muted fs-12 fw-normal">(<?= count($links) ?>)</span></h5></div>
    <div class="card-body">
        <?php foreach ($groups as $label => $rows): ?>
        <div class="fs-12 text-muted text-uppercase mb-1"><?= e($label) ?></div>
        <ul class="list-unstyled mb-3">
            <?php foreach ($rows as $l): ?>
            <li class="d-flex align-items-center gap-2 py-1" id="issue-link-row-<?= (int) $l['link_id'] ?>">
                <a <?= $nav('/issues/' . $l['other_key']) ?> class="fw-semibold text-nowrap"><?= e($l['other_key']) ?></a><span class="<?= $l['other_done'] ? 'text-decoration-line-through text-muted' : '' ?> text-truncate"><?= e($l['other_summary']) ?></span>
                <?php if ($l['other_done']): ?><i class="feather-check text-success"></i><?php endif; ?>
                <?php if ($canWork): ?><form hx-post="/issues/unlink.php" hx-swap="none" class="ms-auto"><?= csrf_field() ?><input type="hidden" name="link" value="<?= (int) $l['link_id'] ?>"><button type="submit" class="btn btn-sm btn-light text-danger" aria-label="Remove link" id="issue-unlink-<?= (int) $l['link_id'] ?>">✕</button></form><?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endforeach; ?>
        <?php if ($links === []): ?><p class="text-muted fs-12 mb-2">No links.</p><?php endif; ?>
        <?php if ($canWork): ?>
        <form hx-post="/issues/link.php" hx-swap="none" class="row g-2 align-items-center" id="issue-link-add-form"><?= csrf_field() ?><input type="hidden" name="issue" value="<?= $id ?>">
            <div class="col-5 col-md-4"><select name="kind" id="issue-link-kind" class="form-select form-select-sm" aria-label="Kind"><option value="blocks">blocks</option><option value="is_blocked_by">is blocked by</option><option value="relates">relates to</option><option value="duplicates">duplicates</option></select></div>
            <div class="col-5 col-md-6"><input type="text" name="other" id="issue-link-other" class="form-control form-control-sm" placeholder="KEY-12" pattern="[A-Za-z][A-Za-z0-9]{1,9}-[0-9]+" required aria-label="The other issue's key"></div>
            <div class="col-2"><button type="submit" class="btn btn-sm btn-light-brand w-100" id="issue-link-add-btn">Link</button></div>
        </form>
        <?php endif; ?>
    </div>
</div>
