# Build spec: project settings and Projects settings (slice 6)

Replicates the exemplar. Schema: `workflow_statuses`, `workflow_transitions`, `board_columns`, `project_team`,
`projects` (db/006), `projects_settings` (db/004), `labels`/`components` (slice 4's handlers).

## Screens

| Screen id | Canonical URL | Purpose |
|---|---|---|
| `project-settings` | `/projects/{KEY}/settings?tab=general|workflow|board|team|labels` | The project's own rules — for `project.admin` of the project |
| `settings` | `/settings` | The one row of `projects_settings` — Projects admins |

## Project settings — tabs (the card header; each a URL)
- **General**: the project form's fields (slice 1's form embedded, `project_update`), plus **Visibility** with its consequence spelled out ("Private: only the team sees it"), and **Archive** (confirm; `project_archive`, agent category `deletion`) / **Unarchive**. An archived project is read-only everywhere (every write handler checks `status = 'active'`, 409 otherwise) and leaves the launcher's counts.
- **Workflow**: the statuses table (name, category select todo/in_progress/done, order ▲▼, initial radio, archive ✕) and an add row; the **transitions matrix** (rows from, columns to; checkboxes) — saved together by `workflow_save`. Archiving a status that has issues: the form asks for a target status and moves them (`app.force_transition = 1` in the handler's transaction, one `issue.transition` log row per issue with `after.by = 'workflow'`). Exactly one initial, non-archived status. Renaming keeps the id, so the board and the history stay true.
- **Board**: slice 3's board form (`board_save`).
- **Team**: the team table (member with avatar and kind, project role select lead/product_owner/scrum_master/member/viewer, since, remove ✕) posting `team_add` (a select2 of active members not on the team + a role) and `team_remove` (agent category `other`). The lead in `projects.lead_member_id` follows the `lead` role (one lead: setting another demotes the previous to member). A private project's external member sees exactly this project.
- **Labels**: slice 4's labels and components forms.
- `data-screen="project-settings" data-entity="project" data-record-id="{id}"`; `screen.view` carries `after.tab`.

## Workflow form
| Field | Input | Required | Rule | id |
|---|---|---|---|---|
| statuses[i][id] | hidden | no (new rows empty) | | `workflow-form-field-status-id-{i}` |
| statuses[i][name] | text | yes | 1–40, unique | `workflow-form-field-status-name-{i}` |
| statuses[i][category] | select | yes | todo / in_progress / done | `workflow-form-field-status-category-{i}` |
| statuses[i][sort_order] | number | yes | | `workflow-form-field-status-order-{i}` |
| initial | radio (status index) | yes | exactly one | `workflow-form-field-initial-{i}` |
| transitions[from][to] | checkbox | no | from ≠ to | `workflow-form-field-transition-{from}-{to}` |
| archive[i] | checkbox + move_to select | no | a status with issues needs a target | `workflow-form-field-archive-{i}`, `workflow-form-field-archive-to-{i}` |

## Team form
| Field | Input | Required | Rule | id |
|---|---|---|---|---|
| member | select2 of active members (humans and agents) not on the team | yes | | `team-form-field-member` |
| project_role | select | yes | lead / product_owner / scrum_master / member / viewer | `team-form-field-role` |

## Projects settings (`/settings`)
| Field | Input | Required | Rule | id |
|---|---|---|---|---|
| timezone | select | yes | a valid zone | `settings-form-field-timezone` |
| sprint_length_days | number | yes | 1–60 | `settings-form-field-sprint-length-days` |
| point_scale | text (comma list) | yes | ascending positive integers | `settings-form-field-point-scale` |
| stale_after_days | number | yes | 1–90 | `settings-form-field-stale-after-days` |
| attachment_max_bytes | number (MB in the UI) | yes | > 0 | `settings-form-field-attachment-max-mb` |
| default_visibility | select | yes | open / private | `settings-form-field-default-visibility` |
- Also on `/settings`: **Tokens** (the member's own `mcp_` tokens: list, mint once-shown, revoke — HR's tokens screen copied) and a link to the activity screen.

## Files (exactly these)
- `html/projects/settings.php` · `archive.php` — `html/workflow/save.php` — `html/team/save.php` · `remove.php` — `html/settings/index.php` · `save.php` · `tokens.php` · `token-mint.php` · `token-revoke.php`
- `app/features/settings/queries.php` · `present.php` — `app/features/workflow/queries.php` — `app/features/team/queries.php`
- `app/views/projects/settings.php` · `partials/settings-general.php` · `settings-workflow.php` · `settings-team.php` — `app/views/settings/page.php` · `form.php` · `tokens.php`

## Query functions (signatures fixed)
- `find_workflow(PDO, int $projectId): array` (statuses + transitions matrix) · `save_workflow(PDO, int $projectId, array $statuses, array $transitions, array $archive, int $actorId): array`
- `find_team(PDO, int $projectId): array` · `add_team_member(PDO, int $projectId, int $memberId, string $role, int $actorId): array` · `remove_team_member(PDO, int $projectId, int $memberId, int $actorId): bool`
- `archive_project(PDO, int $id, bool $archive, int $actorId): array`
- `find_settings(PDO): array` · `save_settings(PDO, array $fields, int $actorId): array`
- `find_my_tokens(PDO, int $memberId): array` · `mint_token(PDO, int $memberId, string $label, string $scope): array` · `revoke_token(PDO, int $id, int $memberId): bool`

## Handlers
- `workflow/save.php` (`workflow_save`, agent category `other`): `require_right_in('project.admin')`; validates one initial, unique names, every referenced status of the project; moves the issues of an archived status inside the transaction; log `workflow.save` (`before`/`after`: statuses `[name, category]`, transitions as pairs, archived names) and an `issue.transition` per moved issue; `HX-Trigger: workflowChanged, boardChanged`.
- `team/save.php` (`team_add`): `project.admin`; a member must be active in the mirror; log `team.add` (`after`: member_id, display_name, project_role); `team/remove.php` (`team_remove`, category `other`): log `team.remove` (`before`).
- `projects/archive.php` (`project_archive` / with `restore=1` `project_update`): `project.admin`; category `deletion` for agents; log `project.archive` / `project.unarchive`; `saved_go("/projects/$key")`.
- `settings/save.php` (`settings_save` — add to the manifest, `admin`): `require_admin()`; log `settings.save` (`before`/`after`).
- Tokens: HR's handlers verbatim; log `mcp_token.mint` (label, scope — never the token) / `mcp_token.revoke`.

## Action manifest entries
- Screens: `project-settings` (prefill `tab`), `settings`. Actions: `workflow_save`, `board_save` (slice 3), `team_add`, `team_remove`, `project_archive`, `label_save`, `component_save` (slice 4), `settings_save`.

## Activity log events
- `screen.view`, `workflow.save`, `issue.transition` (`after.by = 'workflow'`), `team.add`, `team.remove`, `project.archive`, `project.unarchive`, `settings.save`, `mcp_token.mint`, `mcp_token.revoke`.

## Proof
- Workflow: renaming keeps ids; archiving a status with 3 issues moves them and logs 3 transitions; two initial statuses → 422; a transition to an archived status disappears from every issue's buttons; the board refuses to save until the new status is in a column.
- Team: an external added to a private project sees it and nothing else; setting a second lead demotes the first; removing the last admin-capable member of a private project is refused ("A private project needs a lead").
- Archive: writes → 409 on an archived project; the launcher card's open-issue count excludes it; unarchive restores.
- Settings: `point_scale` accepted ascending only; an attachment over the new limit → 422.
- An agent's `team_remove`, `workflow_save`, `board_save`, `project_archive` → `pending_approval`.
- 375 px: the transitions matrix scrolls inside its card; every row's controls are reachable.

## Open Questions
- (none)
