<?php /** Team: who is on the project and in what role; add (team_add), re-role (team_add), remove (team_remove). Data: project, team, candidates, canAdmin, tz */
$p = $project; $key = (string) $p['key']; $id = (int) $p['project_id']; $url = '/projects/' . $key . '/settings?tab=team';
$roles = ['lead' => 'Lead', 'product_owner' => 'Product owner', 'scrum_master' => 'Scrum Master', 'member' => 'Member', 'viewer' => 'Viewer'];
?>
<div id="project-settings-team" hx-get="<?= e($url) ?>" hx-trigger="teamChanged from:body" hx-target="#project-settings-body" hx-swap="innerHTML">
    <div class="row g-3">
        <div class="col-xl-8">
            <div class="card mb-0 h-100" id="project-team-card">
                <div class="card-header"><h5 class="card-title mb-0">Team <span class="text-muted fw-normal fs-12">(<?= count($team) ?>)</span></h5></div>
                <div class="card-body custom-card-action p-0">
                    <p class="fs-12 text-muted px-3 pt-3 mb-2">The lead administers the project; product owner, Scrum Master and member work in it; a viewer only reads. One lead: choosing another demotes the current one to member. A private project's external member sees exactly this project.</p>
                    <div class="table-responsive"><table class="table table-hover align-middle mb-0" id="project-team-table">
                        <thead class="thead-light"><tr><th>Member</th><th>Role</th><th class="d-none d-md-table-cell">Since</th><th class="text-end"></th></tr></thead>
                        <tbody>
                        <?php if ($team === []): ?><tr><td colspan="4" class="text-center text-muted py-4" id="project-team-empty">No one on the team yet.</td></tr><?php endif; ?>
                        <?php foreach ($team as $t): $mid = (int) $t['member_id']; ?>
                            <tr id="project-team-row-<?= $mid ?>">
                                <td><div class="d-flex align-items-center gap-2"><span class="avatar-text avatar-sm"><?= e(initials((string) $t['display_name'])) ?></span>
                                    <div><span class="fw-semibold"><?= e($t['display_name']) ?></span>
                                        <span class="badge bg-soft-<?= $t['member_kind'] === 'agent' ? 'info text-info' : 'secondary text-secondary' ?> ms-1"><?= $t['member_kind'] === 'agent' ? 'agent' : 'person' ?></span>
                                        <?php if ($t['is_external']): ?><span class="badge bg-soft-warning text-warning ms-1">external</span><?php endif; ?>
                                        <?php if (!empty($t['job_title'])): ?><div class="fs-11 text-muted"><?= e($t['job_title']) ?></div><?php endif; ?></div></div></td>
                                <td><?php if ($canAdmin): ?>
                                    <form method="post" action="/team/save.php" hx-post="/team/save.php" hx-trigger="change" hx-swap="none" id="project-team-role-form-<?= $mid ?>"><?= csrf_field() ?><input type="hidden" name="project" value="<?= $id ?>"><input type="hidden" name="member" value="<?= $mid ?>">
                                        <select name="project_role" class="form-select form-select-sm" id="project-team-role-<?= $mid ?>" aria-label="Role of <?= e($t['display_name']) ?>"><?php foreach ($roles as $v => $l): ?><option value="<?= $v ?>" <?= $t['project_role'] === $v ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></form>
                                    <?php else: ?><?= e($roles[$t['project_role']] ?? $t['project_role']) ?><?php endif; ?></td>
                                <td class="d-none d-md-table-cell fs-12 text-muted"><?= e(format_ts($t['added_at'], $tz, 'M j, Y')) ?></td>
                                <td class="text-end"><?php if ($canAdmin): ?>
                                    <form method="post" action="/team/remove.php" hx-post="/team/remove.php" hx-swap="none" hx-confirm="Remove <?= e($t['display_name']) ?> from <?= e($key) ?>?"><?= csrf_field() ?><input type="hidden" name="project" value="<?= $id ?>"><input type="hidden" name="member" value="<?= $mid ?>">
                                        <button type="submit" class="btn btn-sm btn-light-secondary" id="project-team-remove-<?= $mid ?>" aria-label="Remove <?= e($t['display_name']) ?>"><i class="feather-x"></i></button></form>
                                    <?php endif; ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table></div>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <?php if ($canAdmin): ?>
            <div class="card mb-0" id="project-team-add-card">
                <div class="card-header"><h5 class="card-title mb-0">Add to the team</h5></div>
                <div class="card-body">
                    <form method="post" action="/team/save.php" id="team-form" hx-post="/team/save.php" hx-swap="none">
                        <?= csrf_field() ?><input type="hidden" name="project" value="<?= $id ?>">
                        <div class="mb-3"><label for="team-form-field-member" class="form-label fs-12 text-muted">Member</label>
                            <select name="member" id="team-form-field-member" class="form-select" data-select2-selector="team-member" required>
                                <option value="">Choose…</option>
                                <?php foreach ($candidates as $c): ?><option value="<?= (int) $c['member_id'] ?>"><?= e($c['display_name']) ?><?= $c['member_kind'] === 'agent' ? ' (agent)' : ($c['is_external'] ? ' (external)' : ($c['job_title'] ? ' · ' . e($c['job_title']) : '')) ?></option><?php endforeach; ?>
                            </select></div>
                        <div class="mb-3"><label for="team-form-field-role" class="form-label fs-12 text-muted">Project role</label>
                            <select name="project_role" id="team-form-field-role" class="form-select"><?php foreach ($roles as $v => $l): ?><option value="<?= $v ?>" <?= $v === 'member' ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
                        <button type="submit" class="btn btn-primary" id="team-form-save-btn"><i class="feather-user-plus me-2"></i>Add</button>
                    </form>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
