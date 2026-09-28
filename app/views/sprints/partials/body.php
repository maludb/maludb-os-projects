<?php /** Region #sprint-view-body: facts, burndown, issues, scope changes. Data: sprint, project, issues, burndown, events, future, anyActive, can, tz */
$s = $sprint; $sid = (int) $s['sprint_id']; $url = '/sprints/' . $sid; $archived = $project['status'] === 'archived'; $left = sprint_days_left($s['end_date']);
$byCat = ['in_progress' => [], 'todo' => [], 'done' => []]; foreach ($issues as $i) { $byCat[$i['status_category']][] = $i; }
$catLabel = ['todo' => 'To do', 'in_progress' => 'In progress', 'done' => 'Done'];
?>
<div id="sprint-view-body" hx-get="<?= e($url) ?>" hx-trigger="sprintChanged from:body, issueChanged from:body, backlogChanged from:body" hx-swap="outerHTML">
    <div class="d-flex flex-column flex-lg-row gap-3 align-items-stretch">
        <div style="flex:1 1 0;min-width:0">
            <div class="card mb-0 h-100" id="sprint-view-facts">
                <div class="card-body">
                    <?php if ($s['goal']): ?><p class="mb-3" id="sprint-view-goal"><i class="feather-target text-muted me-1"></i><?= e($s['goal']) ?></p><?php else: ?><p class="text-muted fs-12 mb-3" id="sprint-view-goal">No goal set.</p><?php endif; ?>
                    <dl class="row mb-0 fs-13">
                        <dt class="col-5 text-muted">Dates</dt><dd class="col-7"><?= $s['start_date'] !== null ? e(format_date($s['start_date'])) : '—' ?><?= $s['end_date'] !== null ? ' – ' . e(format_date($s['end_date'])) : '' ?><?php if ($s['state'] === 'active' && $left !== null): ?><div class="fs-12 text-muted"><?= $left < 0 ? abs($left) . ' days over' : $left . ' days left' ?></div><?php endif; ?></dd>
                        <dt class="col-5 text-muted">Issues</dt><dd class="col-7"><?= (int) $s['issues_done'] ?> of <?= (int) $s['issues'] ?> done</dd>
                        <dt class="col-5 text-muted">Points</dt><dd class="col-7"><?= e(days_label($s['points_done'])) ?> of <?= e(days_label($s['points'])) ?> done</dd>
                        <?php if ($s['committed_points'] !== null): ?><dt class="col-5 text-muted">Committed</dt><dd class="col-7" id="sprint-view-committed"><?= e(days_label($s['committed_points'])) ?> pts</dd><?php endif; ?>
                        <?php if ($s['state'] === 'closed'): ?>
                        <dt class="col-5 text-muted">Completed</dt><dd class="col-7" id="sprint-view-completed"><?= e(days_label($s['completed_points'])) ?> pts</dd>
                        <dt class="col-5 text-muted">Carried over</dt><dd class="col-7" id="sprint-view-carried"><?= e(days_label($s['carried_points'])) ?> pts</dd>
                        <dt class="col-5 text-muted">Closed</dt><dd class="col-7"><?= e(format_ts($s['closed_at'], $tz, 'M j, Y')) ?></dd>
                        <?php endif; ?>
                    </dl>
                    <?php if ($can['run'] && $s['state'] === 'active' && !$archived): ?>
                    <form method="post" action="/sprints/close.php" hx-post="/sprints/close.php" hx-swap="none" class="mt-3 border-top pt-3" id="sprint-close-form" hx-confirm="Close <?= e($s['name']) ?>? What is not done carries over.">
                        <?= csrf_field() ?><input type="hidden" name="sprint" value="<?= $sid ?>">
                        <label for="sprint-close-carry-to" class="form-label fs-12 text-muted mb-1">Unfinished issues go to</label>
                        <div class="d-flex gap-2">
                            <select name="carry_to" id="sprint-close-carry-to" class="form-select form-select-sm"><option value="">The backlog</option><?php foreach ($future as $f): ?><option value="<?= (int) $f['sprint_id'] ?>"><?= e($f['name']) ?></option><?php endforeach; ?></select>
                            <button type="submit" class="btn btn-sm btn-danger text-nowrap" id="sprint-view-close-btn"><i class="feather-check-square me-1"></i>Close sprint</button>
                        </div>
                    </form>
                    <?php endif; ?>
                    <?php if ($can['plan'] && $s['state'] === 'future' && (int) $s['issues'] === 0 && !$archived): ?>
                    <form method="post" action="/sprints/delete.php" hx-post="/sprints/delete.php" hx-swap="none" class="mt-3 border-top pt-3" id="sprint-delete-form" hx-confirm="Delete <?= e($s['name']) ?>?">
                        <?= csrf_field() ?><input type="hidden" name="sprint" value="<?= $sid ?>">
                        <button type="submit" class="btn btn-sm btn-light-brand text-danger" id="sprint-view-delete-btn"><i class="feather-trash-2 me-1"></i>Delete this empty sprint</button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div style="flex:2 1 0;min-width:0">
            <div class="card mb-0 h-100" id="sprint-view-burndown">
                <div class="card-header py-2"><h6 class="card-title mb-0">Burndown</h6></div>
                <div class="card-body">
                    <?php if ($s['state'] === 'future'): ?><div class="text-muted fs-12 text-center py-4">The burndown starts with the sprint.</div>
                    <?php elseif ($burndown === []): ?><div class="text-muted fs-12 text-center py-4">No day of the sprint has passed yet.</div>
                    <?php else:
                        $n = count($burndown); $committed = (float) ($s['committed_points'] ?? $burndown[0]['scope']);
                        $span = max(1, $s['start_date'] !== null && $s['end_date'] !== null ? (int) (new DateTimeImmutable($s['start_date']))->diff(new DateTimeImmutable($s['end_date']))->days : $n - 1);
                        $ideal = []; for ($k = 0; $k < $n; $k++) { $ideal[] = round(max(0, $committed - $committed * $k / $span), 2); }
                        echo '<div class="table-responsive">' . chart_line([
                            ['label' => 'Remaining', 'points' => array_map(static fn ($r) => (float) $r['remaining'], $burndown)],
                            ['label' => 'Ideal', 'points' => $ideal, 'dashed' => true],
                            ['label' => 'Scope', 'points' => array_map(static fn ($r) => (float) $r['scope'], $burndown)],
                        ], ['width' => 640, 'height' => 240, 'axes' => true, 'title' => 'Burndown of ' . $s['name'], 'id' => 'sprint-burndown-chart', 'labels' => array_map(static fn ($r) => (new DateTimeImmutable($r['day']))->format('M j'), $burndown)]) . '</div>';
                        echo '<div class="fs-12 text-muted mt-2"><span class="me-3"><span class="d-inline-block me-1" style="width:12px;height:2px;background:var(--bs-primary);vertical-align:middle"></span>Remaining</span><span class="me-3"><span class="d-inline-block me-1" style="width:12px;height:0;border-top:2px dashed var(--bs-secondary);vertical-align:middle"></span>Ideal</span><span><span class="d-inline-block me-1" style="width:12px;height:2px;background:var(--bs-success);vertical-align:middle"></span>Scope</span></div>';
                    endif; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="card mt-3 mb-0" id="sprint-view-issues">
        <div class="card-header py-2"><h6 class="card-title mb-0">Issues <span class="text-muted fw-normal fs-12">(<?= count($issues) ?>)</span></h6></div>
        <div class="card-body p-0">
            <?php if ($issues === []): ?><div class="text-center text-muted py-4" id="sprint-view-issues-empty">No issue in this sprint.</div><?php endif; ?>
            <?php foreach ($byCat as $cat => $list): if ($list === []) { continue; } ?>
            <div class="px-3 pt-2 fs-12 text-muted text-uppercase" id="sprint-view-cat-<?= e($cat) ?>"><?= e($catLabel[$cat]) ?> · <?= count($list) ?></div>
            <div class="table-responsive"><table class="table table-hover mb-0 fs-13">
                <tbody>
                <?php foreach ($list as $i): [$pm, $ptone, $plabel] = issue_priority_mark((string) $i['priority']); $tone = issue_status_tone((string) $i['status_category']); $iu = '/issues/' . $i['key']; ?>
                <tr id="sprint-issue-row-<?= (int) $i['issue_id'] ?>">
                    <td class="text-nowrap" style="width:1%"><i class="<?= e(issue_type_icon((string) $i['type'])) ?> text-muted me-1"></i><a href="<?= e($iu) ?>" class="fw-semibold" hx-get="<?= e($iu) ?>" hx-target="#page-content" hx-push-url="<?= e($iu) ?>"><?= e($i['key']) ?></a></td>
                    <td class="text-wrap" style="min-width:12rem"><a href="<?= e($iu) ?>" class="text-dark" hx-get="<?= e($iu) ?>" hx-target="#page-content" hx-push-url="<?= e($iu) ?>"><?= e($i['summary']) ?></a><?php if ((int) $i['open_blockers'] > 0): ?> <i class="feather-slash text-danger" title="Blocked"></i><?php endif; ?></td>
                    <td><span class="badge bg-soft-<?= e($tone) ?> text-<?= e($tone) ?>"><?= e($i['status']) ?></span></td>
                    <td><span class="text-<?= e($ptone) ?>" title="<?= e($plabel) ?>"><?= e($pm) ?></span></td>
                    <td class="text-nowrap"><?php if ($i['assignee_member_id'] !== null): ?><span class="avatar-text avatar-xs rounded-circle me-1"><?= e(initials((string) $i['assignee_name'])) ?></span><?= e($i['assignee_name']) ?><?php else: ?><span class="text-muted">—</span><?php endif; ?></td>
                    <td class="text-end text-nowrap"><?= $i['story_points'] !== null ? e(days_label($i['story_points'])) . ' pts' : '<span class="text-muted">—</span>' ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php if ($s['state'] !== 'future'): ?>
    <div class="card mt-3 mb-0" id="sprint-view-scope">
        <div class="card-header py-2"><h6 class="card-title mb-0">Scope changes <span class="text-muted fw-normal fs-12">(<?= count($events) ?>)</span></h6></div>
        <div class="card-body p-0">
            <?php if ($events === []): ?><div class="text-center text-muted py-4 fs-12" id="sprint-view-scope-empty">Nothing changed since the sprint started.</div>
            <?php else: ?>
            <div class="table-responsive"><table class="table mb-0 fs-13" id="sprint-view-scope-table">
                <thead class="thead-light"><tr><th>When</th><th>What</th><th>Issue</th><th class="text-end">Points</th><th>By</th></tr></thead>
                <tbody>
                <?php foreach ($events as $ev): $iu = $ev['issue_key'] !== null ? '/issues/' . $ev['issue_key'] : null;
                    $word = ['added' => 'Added to the sprint', 'removed' => 'Removed from the sprint', 'done' => 'Done', 'reopened' => 'Reopened', 'estimated' => 'Re-estimated'][$ev['event']] ?? ucfirst((string) $ev['event']);
                    $etone = ['added' => 'warning', 'removed' => 'secondary', 'done' => 'success', 'reopened' => 'danger', 'estimated' => 'info'][$ev['event']] ?? 'secondary'; ?>
                <tr id="scope-event-row-<?= (int) $ev['scope_event_id'] ?>">
                    <td class="text-nowrap text-muted"><?= e(format_ts($ev['occurred_at'], $tz, 'M j, g:i A')) ?></td>
                    <td><span class="badge bg-soft-<?= e($etone) ?> text-<?= e($etone) ?>"><?= e($word) ?></span></td>
                    <td><?php if ($iu !== null): ?><a href="<?= e($iu) ?>" class="fw-semibold" hx-get="<?= e($iu) ?>" hx-target="#page-content" hx-push-url="<?= e($iu) ?>"><?= e($ev['issue_key']) ?></a> <span class="text-muted"><?= e($ev['issue_summary'] ?? '') ?></span><?php else: ?><span class="text-muted">an issue since deleted</span><?php endif; ?></td>
                    <td class="text-end"><?= $ev['points'] !== null ? e(days_label($ev['points'])) : '—' ?></td>
                    <td class="text-nowrap"><?= e($ev['by_name'] ?? '—') ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>
