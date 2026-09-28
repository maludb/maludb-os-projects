# Build spec: projects and issues — THE EXEMPLAR (slice 1)

Built by the planning-class model. Every later slice replicates its files, ids, gates and flow.
Schema: `projects`, `project_team`, `workflow_statuses`, `workflow_transitions` (db/006), `issues`,
`issue_aliases`, `issue_labels`, `issue_links`, `issue_watchers`, `checklist_items` (db/008), `comments`,
`attachments`, `worklogs` (db/009), the triggers of db/010, the views of db/011. Never modify them.

## Screens

| Screen id | Canonical URL | Purpose |
|---|---|---|
| `projects-list` | `/projects` | Every project the member may see, as cards by department |
| `project-view` | `/projects/{KEY}` | One project: the overview and the tab strip |
| `project-add` / `project-edit` | `/projects/new`, `/projects/{KEY}/edit` | Create or change a project |
| `issues-list` | `/projects/{KEY}/issues` | The project's issues as a table with every filter; saved filters |
| `issue-view` | `/issues/{KEY-n}` | One issue, whole |
| `issue-add` / `issue-edit` | `/issues/new?project=KEY&type=&parent=&epic=&sprint=`, `/issues/{KEY-n}/edit` | Create or change an issue |

(`/projects/{KEY}/backlog|board|sprints|epics|reports|settings` are later slices; this slice renders each
as an empty state with the slice's name.)

## Projects list
- Cards (`RecordCard` pattern: key badge, name, lead avatar initials, department, open-issue count, active sprint name, a lock icon for private), grouped by department name (A–Z, "No department" last), 24 per page.
- Search matches key, name, description (`search_tsv`); filters `department`, `status` (active default / archived), `mine` (on the team); sort allowlist name / key / updated; default name.
- Card link → `project-view`. Page-header action **New project** for `projects_is_admin()` or `projects_has_right('project.admin')`.
- Region `#projects-list-results` (Pattern B), listens to `projectChanged`.

## Project view
- Header: key + name, visibility badge, status badge; buttons **New issue** (→ `/issues/new?project=KEY`, `projects_can_work`), **Edit** (`projects_has_right_in('project.admin', id)`).
- The tab strip is the card header: Overview · Backlog · Board · Sprints · Epics · Issues · Reports · Settings (Settings only for `project.admin`). Each tab is its own URL (a page, Pattern B on `#page-content`), never client state.
- Overview: lead, department, customer, description; **Team** (avatars with project role; "Manage" → settings); **Active sprint** (name, goal, days left, `points_done`/`points`, sparkline) or "No sprint is running — plan one from the backlog"; **Epics** (up to 6, each a progress bar `points_done`/`points`, → `/epics/{id}`); **Recent activity** (10 rows from `mcp_activity_log` for this project and its issues; "All" → `/activity?project=KEY`).
- `data-screen="project-view" data-entity="project" data-record-id="{id}"`.

## Project form
| Field | Input | Required | Rule | id |
|---|---|---|---|---|
| key | text, uppercase | yes (create only; read-only on edit) | `^[A-Z][A-Z0-9]{1,9}$`, unique | `project-form-field-key` |
| name | text | yes | 1–120 | `project-form-field-name` |
| description | textarea (markdown) | no | | `project-form-field-description` |
| department | select of `mcp_departments` (not archived) | no | | `project-form-field-department` |
| lead | select2 of active members (humans and agents) | no | on create the lead joins the team as `lead` (trigger) | `project-form-field-lead` |
| visibility | select open/private | yes | default `projects_settings.default_visibility` | `project-form-field-visibility` |
| board_mode | select scrum/kanban | yes | default scrum | `project-form-field-board-mode` |
| customer | text | no | ≤ 120 | `project-form-field-customer` |
- Save/cancel in the page header; errors re-render `#project-form-container`. Create: `projects_is_admin()` or `projects_has_right('project.admin')`; edit: `projects_has_right_in('project.admin', id)`.

## Issues list
- A table (a ledger, not cards): key, type icon, summary (→ `issue-view`), status badge (by category tone), priority icon, assignee (avatar + name; "agent" badge), points, sprint, updated. 50 a page, server pagination.
- Filters (all in the URL; Pattern B on `#issues-list-results`): `q` (`search_tsv` + key), `type[]`, `status[]` (this project's statuses), `category` (todo/in_progress/done/open), `assignee` (member id, `me`, `none`), `reporter`, `sprint` (id, `backlog`, `active`), `epic`, `label`, `component`, `priority[]`, `due` (overdue/week/none), `blocked` (1), `updated_since` (days). Sort allowlist rank / updated / created / priority / due / key; default rank.
- **Saved filters**: a select of `mcp_saved_filters` for this project and the member's own across projects; "Save this filter" (name + shared checkbox, POST `filter_save`); the CRUD screen is slice 7.
- Page-header action **New issue** (`projects_can_work`). Bulk actions are out of scope for the first version.

## Issue view
- Two columns (one at 375 px): the body (summary editable in place by `issue_update` when `projects_can_work` — an `hx-post` on blur; the description rendered from markdown; **Subtasks** table with add box; **Links** (grouped by kind, with the other issue's key, summary and done mark; add form: kind + issue key; remove ✕); **Attachments** (name, size, who, when; upload form; delete ✕ for the uploader or an admin); **Checklist** (items with checkbox toggle, add box); **Worklogs** (table: who, hours, day, note; add box: hours, day, note); **Comments** (author, when, markdown body; add box; edit/delete own, delete any for admin); **History** (the issue's rows from `mcp_activity_log`, newest first, each in words: "Sasha moved it to In review", from `before`/`after`).
- The fields column: type, status with **the transition buttons** — one button per `mcp_workflow_statuses.can_move_to` of the current status, POST `issue_transition` (Pattern D: `HX-Trigger: issueChanged` re-renders `#issue-fields`); priority; assignee (select2 inline, POST `issue_assign`); reporter; story points (inline number from `point_scale`, POST `issue_estimate`); estimate hours; epic; sprint; component; labels (chips); due date; created/updated; **Watch / Unwatch** toggle; **Accept** (`issue.accept` right; only when the status category is done; sets `accepted_at`/`accepted_by`; a second press withdraws); **Edit** → `issue-edit`; **Delete** (Pattern C confirm; `projects_has_right_in('project.admin')` or the reporter while nothing else hangs on it) — category `deletion` for agents.
- Aliases: a request for an alias key 301s to the current key.
- `data-screen="issue-view" data-entity="issue" data-record-id="{id}"`.

## Issue form
| Field | Input | Required | Rule | id |
|---|---|---|---|---|
| project | hidden (create) | yes | the member must `projects_can_work` | `issue-form-field-project` |
| type | select story/task/bug (subtask only via `?parent=`) | yes | fixed set (owner's answer 4) | `issue-form-field-type` |
| summary | text | yes | 1–255 | `issue-form-field-summary` |
| description | textarea (markdown) | no | | `issue-form-field-description` |
| assignee | select2 of the project's team + any active member | no | | `issue-form-field-assignee` |
| priority | select lowest…highest | yes | default medium | `issue-form-field-priority` |
| story_points | select from `point_scale` + "none" | no | hidden for subtasks | `issue-form-field-story-points` |
| estimate_hours | number step 0.25 | no | ≥ 0 | `issue-form-field-estimate-hours` |
| epic | select of the project's open epics | no | hidden for subtasks | `issue-form-field-epic` |
| sprint | select backlog / future and active sprints | no | subtasks follow the parent | `issue-form-field-sprint` |
| component | select | no | default assignee applied when assignee empty | `issue-form-field-component` |
| labels | multi select2 (existing; new names created by `label_save` on the fly for `project.admin`) | no | | `issue-form-field-labels` |
| due_date | date | no | | `issue-form-field-due-date` |
| parent | hidden (`?parent=`) | no | makes it a subtask | `issue-form-field-parent` |
- The status is never on the form: a new issue lands on the initial status (trigger); changes go through `issue_transition`.

## Files (exactly these)
- `html/projects/index.php` · `view.php` · `form.php` · `save.php` · `archive.php` (slice 6 fills) · `issues.php`
- `html/issues/form.php` · `view.php` · `save.php` · `transition.php` · `assign.php` · `estimate.php` · `accept.php` · `watch.php` · `delete.php` · `link.php` · `unlink.php`
- `html/issues/comments/save.php` · `delete.php` — `html/issues/checklist/save.php` · `toggle.php` · `delete.php` — `html/issues/worklogs/save.php` · `delete.php` — `html/attachments/upload.php` · `get.php` · `delete.php` — `html/filters/save.php`
- `app/features/projects/queries.php` · `present.php` — `app/features/issues/queries.php` · `present.php` · `markdown.php` (a small safe renderer: paragraphs, emphasis, code, lists, links — no raw HTML)
- `app/views/projects/page.php` · `view.php` · `partials/cards.php` · `card.php` · `overview.php` · `form.php` · `tabs.php` · `issues.php` · `partials/issues-table.php`
- `app/views/issues/view.php` · `form.php` · `partials/fields.php` · `subtasks.php` · `links.php` · `attachments.php` · `checklist.php` · `worklogs.php` · `comments.php` · `history.php`

## Query functions (signatures fixed)
- `find_projects(PDO, array $filters, string $sort = 'name', int $page = 1, int $per = 24): array` (rows from `mcp_projects`; total)
- `find_project_by_key(PDO, string $key): ?array` (mcp_projects + team + active sprint + epics summary)
- `find_project_for_edit(PDO, int $id): ?array` (base table, behind `project.admin`)
- `upsert_project(PDO, ?int $id, array $fields, int $actorId): array`
- `find_issues(PDO, int $projectId, array $filters, string $sort = 'rank', int $page = 1, int $per = 50): array`
- `find_issue_by_key(PDO, string $key): ?array` (mcp_issues + mcp_issue_details; resolves an alias)
- `find_issue_for_edit(PDO, int $id): ?array`
- `issue_children(PDO, int $id): array` · `issue_links(PDO, int $id): array` · `issue_comments(PDO, int $id): array` · `issue_checklist(PDO, int $id): array` · `issue_worklogs(PDO, int $id): array` · `issue_attachments(PDO, int $id): array` · `issue_history(PDO, int $id, int $limit = 50): array`
- `create_issue(PDO, array $fields, int $actorId): array` (the trigger gives key, status, rank) · `update_issue(PDO, int $id, array $fields, int $actorId): array` (partial: only the keys present)
- `transition_issue(PDO, int $id, int $toStatusId, int $actorId): array` (refuses a move not in `can_move_to` with the trigger's sentence)
- `assign_issue(PDO, int $id, ?int $memberId, int $actorId): array` · `estimate_issue(PDO, int $id, ?float $points, ?float $hours, int $actorId): array` · `accept_issue(PDO, int $id, bool $accept, int $actorId): array`
- `link_issues(PDO, int $fromId, int $toId, string $kind, int $actorId): array` · `unlink_issues(PDO, int $linkId, int $actorId): bool`
- `add_comment(PDO, int $issueId, string $body, int $actorId): array` · `edit_comment(PDO, int $id, string $body, int $actorId): array` · `delete_comment(PDO, int $id, int $actorId): bool`
- `add_checklist_item(PDO, int $issueId, string $text, int $actorId): array` · `toggle_checklist_item(PDO, int $id, bool $done, int $actorId): array`
- `add_worklog(PDO, int $issueId, float $hours, string $day, ?string $note, int $actorId): array` · `delete_worklog(PDO, int $id, int $actorId): bool`
- `store_attachment(PDO, int $issueId, array $file, int $actorId): array` · `delete_attachment(PDO, int $id, int $actorId): bool`
- `set_watch(PDO, int $issueId, int $memberId, bool $watch): void`
- `delete_issue(PDO, int $id, int $actorId): bool`
- `save_filter(PDO, int $ownerId, ?int $projectId, string $name, array $filter, bool $shared): array`

## Handlers (every one: `require_post(); verify_csrf();` the gate; a transaction; `log_activity`; `emit_action_status`)
- `projects/save.php`: create → `require_admin()` or `project.admin`; edit → `require_right_in('project.admin', $id)`; validate; upsert; log `project.create` (`after`: key, name, visibility, department_id, lead_member_id) or `project.update` (`before`/`after`: changed fields); `saved_go("/projects/$key")`; `HX-Trigger: projectChanged`.
- `issues/save.php`: `require_can_work($projectId)`; a subtask's project is the parent's; create logs `issue.create` (`after`: key, type, summary, assignee_member_id, sprint_id, epic_id, story_points, parent_issue_id); update logs `issue.update` (`before`/`after`: changed fields); `saved_go("/issues/$key")`; `HX-Trigger: issueChanged`.
- `issues/transition.php` (`issue_transition`): `require_can_work`; also `board.move` for another's issue (own issues need only `issue.write`); log `issue.transition` (`before`: status_id, status; `after`: status_id, status, category, sprint_id); a disallowed move → 422 with the trigger's sentence.
- `issues/assign.php` (`issue_assign`): `require_can_work`; log `issue.assign` (`before`/`after`: assignee_member_id, assignee_name).
- `issues/estimate.php` (`issue_estimate`): `require_right_in('backlog.rank')` or the assignee for their own; log `issue.estimate` (`before`/`after`: story_points, estimate_hours).
- `issues/accept.php`: `require_right_in('issue.accept')`; log `issue.accept` / `issue.unaccept`.
- `issues/watch.php` (`watch` / `unwatch` by `action=`): any member who may see; log `issue.watch` / `issue.unwatch`.
- `issues/link.php` (`issue_link`): `require_can_work` on both; a cycle → 422 "That would make a cycle of blocks"; log `issue.link` (`after`: kind, from_key, to_key); `unlink.php` logs `issue.unlink`.
- `issues/delete.php` (`issue_delete`): `project.admin`, or the reporter when the issue has no subtasks, comments by others, worklogs or links; log `issue.delete` (`before`: key, summary, type); `saved_go("/projects/$key/issues")`.
- `comments/save.php` (`comment_add`; edit with `id`): `require_right_in('comment.write')` (an agent: any it may see); edit own only; log `comment.add` (`after`: issue_key, length) / `comment.edit`; `delete.php` (`comment_delete`): own or admin; soft delete; log `comment.delete`.
- `checklist/save.php` (`checklist_add`) / `toggle.php` (`checklist_toggle`) / `delete.php`: `require_can_work`; log `checklist_item.add` / `.toggle` (`after`: is_done) / `.delete`.
- `worklogs/save.php` (`worklog_add`): `require_right_in('worklog.write')`; own only on delete; log `worklog.add` (`after`: hours, worked_on) / `worklog.delete`.
- `attachments/upload.php` (`attachment_add`, screen only — the manifest marks it `who: people`): `require_can_work`; size ≤ `ATTACHMENT_MAX_BYTES`; mime from the bytes; stored under `storage/attachments/…`; log `attachment.add` (`after`: file_name, size_bytes, mime_type); `delete.php`: uploader or admin; soft delete + unlink the file; log `attachment.delete`. `get.php` is a GET behind `projects_can_see_project`; logs `attachment.get`.
- `filters/save.php` (`filter_save`): own; log `saved_filter.save`.

## Action manifest entries
- Screens: `projects-list`, `project-view`, `project-add`, `project-edit`, `issues-list`, `issue-view`, `issue-add`, `issue-edit`.
- Actions: `project_create`, `project_update`, `issue_create`, `issue_update`, `issue_assign`, `issue_transition`, `issue_estimate`, `issue_link`, `issue_unlink`, `issue_delete`, `comment_add`, `comment_delete`, `checklist_add`, `checklist_toggle`, `worklog_add`, `attachment_add`, `watch`, `unwatch`, `filter_save`.

## Activity log events
- `screen.view` (each screen, `after.tab` on the project view), `project.create`, `project.update`, `issue.create`, `issue.update`, `issue.transition`, `issue.assign`, `issue.estimate`, `issue.accept`, `issue.unaccept`, `issue.watch`, `issue.unwatch`, `issue.link`, `issue.unlink`, `issue.delete`, `comment.add`, `comment.edit`, `comment.delete`, `checklist_item.add`, `checklist_item.toggle`, `checklist_item.delete`, `worklog.add`, `worklog.delete`, `attachment.add`, `attachment.get`, `attachment.delete`, `saved_filter.save`.

## Status vocabulary
- Category todo → secondary · in_progress → primary · done → success. Priority: highest ↑↑ danger, high ↑ warning, medium – secondary, low ↓ info, lowest ↓↓ muted. Type icons: story `feather-bookmark`, task `feather-check-square`, bug `feather-alert-circle`, subtask `feather-corner-down-right`. Blocked: `feather-slash` in danger.

## Out of scope for this slice
- Rank and sprint moves (slice 2); the board (3); epics, labels and components screens, moving between projects (4); reports (5); workflow, team and archive (6); My work, the filters screen, the full activity screen (7). This slice leaves each tab as an empty state naming its slice.

## Proof
- List: cards grouped, search + filters + sort re-fetch only `#projects-list-results`; a private project is absent for a member off its team and present for a team member; an archived project appears only under `status=archived`.
- View: the eight tabs are URLs; the Settings tab is hidden without `project.admin`; `screen.view` carries `after.tab`.
- Issue: create gets `KEY-1`, `KEY-2` …; a subtask created from a parent lands in the parent's sprint with the parent's project; the transition buttons are exactly `can_move_to`; a disallowed move → 422 with the trigger's sentence; moving to Done stamps `resolved_at` and resolution; reopening clears them; `started_at` is set once; a blocks-link that would cycle → 422; watch/unwatch; a comment edited by another → 403; an attachment over the limit → 422, an id the member may not see → 404 on `get.php`; a partial update through the kernel's actions server (`_partial=1`, action token) changes one field and keeps the rest; an agent's `issue_delete` → `pending_approval`.
- 375 px and 1280 px: no horizontal scroll on the list, the project view, the issues table (it scrolls inside its card with a visible scrollbar), the issue view and the form.
- Rules the exemplar sets: query functions take PDO and explicit values; reads through `mcp_*` views except the form's base-table read behind the gate; `refuse()` for every refusal; `saved_go()` after a save; every form field id `{entity}-form-field-{name}`; the manifest's param name and the column name both accepted (`assignee` / `assignee_member_id`, `sprint` / `sprint_id`, `epic` / `epic_id`); an issue is addressed by key everywhere a person types, by id in every table.

## Built and proven (2026-09-28)
Two halves, both by the planning-class model: the projects half (list, view with the eight tabs, form and save, the issues
list with saved filters, the archive stub) and the issues half (the view with its eight regions each re-fetched on
`issueChanged`, the form for new/edit/subtask, save with the partial update, the transition, assign, estimate, accept,
watch, link, unlink and delete actions, the comment, checklist, worklog and attachment satellites; the shared helpers in
`app/features/issues/respond.php`). Every proof above was run live as member 1 through kernel-minted hand-offs and as a
second member for the refusals: SMK1 created, viewed on every tab, edited, listed and searched; SMK1-1 created with its
subtasks, moved To do → In progress → In review → Done with the clocks stamped, reopened, refused To do → Done with the
trigger's sentence, assigned, estimated, accepted and withdrawn, watched, linked with the reverse refused as a cycle,
commented, ticked, logged, attached (over the limit refused; deleted files gone), deleted; another member refused on
another's comment, worklog, attachment and issue and on rights they lack; a partial update through an action token
(`_partial=1`, `priority` alone) changed one field, kept the rest and logged before/after with source `assistant`; headless
Chromium at 1280×800 and 375×740 on the list, the project view, the issues list, the form, the issue view and the issue
form with no sideways scroll and no console errors; on a phone the Details card comes first. An agent's `issue_delete`
pausing as `pending_approval` is Phase 4's proof (the registry on the kernel's actions server). Two fixes on the way:
`toggle_checklist_item()` casts `done_by`; `save.php` requires `partial_update.php` itself. SMOKE data left: SMK1 with
SMK1-1 (Done, watched, one checklist item), soft-deleted comments and one soft-deleted attachment row, saved filter 2.

## Open Questions
- (none)
