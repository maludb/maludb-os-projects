# Projects Action Manifest

2026-09-28 · Edward Honour · **Phase 1, for the checkpoint**

> The registry the command bar (through the kernel's chat endpoint), the kernel's Actions MCP and the
> screens resolve against. A screen or action missing here cannot be reached by voice or by an agent —
> unfinished design, like a question with no tool. Pairs with `db/` (tables) and
> `docs/projects-mcp-tool-surface.md` (read tools). Generated into `mcp/action_registry.json` by
> `bin/build_action_registry.php`; an action whose file does not exist yet is registered but not exposed.

## Conventions

### Screens
- **Ids** are kebab-case `{entity}-list`, `{entity}-add`, `{entity}-view`, `{entity}-edit`, plus named screens.
- **Canonical URLs** need the rewrites in `deploy/apache-projects.conf`: `/{x}/new` → `form.php`, `/{x}/{id}/edit` → `form.php?id=`, `/{x}/{id}` → `view.php?id=`; a project by its key (`/projects/{key}/…` → `…?key=`), an issue by its key (`/issues/{KEY-n}` → `view.php?key=`).
- **Prefill params** become query-string values the GET controller reads.
- **Every screen partial stamps** `data-screen`, `data-entity`, `data-record-id` on `#page-content`, so "that", "this issue" and "move this to the sprint" resolve against the page the user is on.

### Actions
- **Name** `{entity}_{verb}`; the **log event** `{entity}.{verb}` is what the handler writes with `log_activity()` and what the kernel's approval policies match. `.delete` destroys a record; `.remove` detaches something; `.archive` keeps it out of the way.
- **Endpoints** are POST to `/{base}/{file}` with `require_post()`, `verify_csrf()` (or the relayed action token), the gate, and `log_activity()`. Handlers report through `emit_action_status()`; a create's `location` ends in the new record's id (an issue's: its key).
- **Params:** **bold** = required; `[]` = repeated; parentheses = a hint (no comma or semicolon inside; no note after the last parameter). A param named for a record (`project`, `issue`, `sprint`, `epic`, `member`, `assignee`, `label`, `component`, `status`) accepts an id or a name, resolved through Projects' own `find_*` tool (`docs/projects-mcp-tool-surface.md`, resolution table). `any field of X` = X's fields, all optional, a partial update.
- **Who** is enforced in the endpoint: `all` = anyone admitted to Projects who may see the project; `team` = on the project's team with a working role, or `issue.write` business-wide (`projects_can_work()`); `own` = the record's author; `po` = `backlog.rank` / `sprint.plan` / `issue.accept` / `epic.write` in the project (`projects_has_right_in()`: the Product Owner role, or the project's lead); `sm` = `sprint.run` / `board.configure` in the project (the Scrum Master role, or the lead); `admin` = `project.admin` in the project (the lead) or a Projects Admin; `super` = the super-admin only. An agent is `team` on every open project it may see.
- **Undo** is the inverse `undo_last` applies; — means it cannot be undone and the reply says so.
- **Confirm (✔)** = the command bar asks first (destructive, or something a team decided together).
- **Agent approval** names the kernel's category that pauses the action when an **agent** performs it: `other`, `deletion`. People are never paused. The categories are exactly `maludb-os.json` `approvals[]`.
- **Refresh:** a data action returns `HX-Trigger: {entity}Changed` (camelCase entity).
- **Log payloads** (what `before`/`after` must carry, for the activity questions): `issue.transition` — from and to status (id, name, category) and the sprint at the time; `issue.assign` — old and new assignee (id and name) and the note; `issue.estimate` — old and new points; `issue.rank` — old and new rank and the neighbour keys; `issue.move_to_sprint` — old and new sprint (null = backlog); `issue.create` — type, sprint, epic; `sprint.start` — committed points and the issue keys; `sprint.close` — completed and carried points, the carried keys, where they went.

### Result contract
```json
{"status": "success", "did": "Moved PROJ-42 to In review", "record_id": 42, "undo_id": "act_31", "refresh": "issueChanged"}
{"status": "pending_approval", "did": "Closing Sprint 7 waits for Mia", "approval_request_id": 17}
{"status": "error", "message": "Found 2 issues matching 'login': PROJ-12 (Login page), PROJ-40 (Login bug). Which one?"}
```

## Home

Screens:

| Screen id | URL | When the user wants… |
| --- | --- | --- |
| `dashboard` | `/` | their Projects home: their open issues, their projects, the active sprints and how each burns down |
| `my-work` | `/my-work` | everything on their plate across projects — assigned, watching, in review for them (params: `state`, `project`) |
| `activity` | `/activity` | recent activity in Projects, or one project's or one issue's history (params: `project`, `entity_type`, `entity_id`, `period`) |
| `settings` | `/settings/` | the business's Projects defaults: timezone, sprint length, the point scale, the stale threshold, attachment limit, default visibility |
| `tokens` | `/settings/tokens/` | their own access tokens for the two MCP servers |

Actions (base `/settings/`):

| Action | File | Params | Undo | Confirm | Agent approval | Log | Who |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `settings_save` | `save.php` | timezone, sprint_length_days (1 to 60), point_scale (comma-separated numbers), stale_after_days (1 to 90), attachment_max_bytes, default_visibility (open or private) | restore prior | | | `settings.save` | admin |
| `token_mint` | `tokens/mint.php` | **label**, scope (mcp) | revoke it | | | `token.mint` | all (own) |
| `token_revoke` | `tokens/revoke.php` | **token** | — | ✔ | | `token.revoke` | own |

## Projects

Screens:

| Screen id | URL | When the user wants… |
| --- | --- | --- |
| `projects-list` | `/projects/` | the projects, as cards by department, open or private, active or archived (params: `q`, `department`, `status`, `mine`) |
| `project-view` | `/projects/{key}` | one project's overview: lead, team, active sprint, epics with progress, recent activity |
| `project-add` | `/projects/new` | to create a project (params: `name`, `key`, `department`, `lead`, `visibility`) |
| `project-edit` | `/projects/{key}/edit` | to change a project's name, description, department, lead, customer, visibility, board mode or swimlane |
| `project-settings` | `/projects/{key}/settings` | a project's workflow, board, labels, components, team and roles, visibility, archive (params: `tab`) |
| `project-backlog` | `/projects/{key}/backlog` | the backlog in rank order with the next sprints' boxes above it: estimate, rank, move to a sprint (params: `epic`, `unestimated`) |
| `project-board` | `/projects/{key}/board` | the board: the active sprint by column and swimlane, WIP counts, blocked marks (params: `assignee`, `epic`) |
| `project-sprints` | `/projects/{key}/sprints` | the project's sprints: future, active, closed, with their points |
| `project-epics` | `/projects/{key}/epics` | the epics as cards with progress bars (params: `status`) |
| `project-issues` | `/projects/{key}/issues` | the issue table with filters and saved filters (params: `q`, `type`, `status`, `category`, `assignee`, `sprint`, `epic`, `label`, `component`, `priority`, `unassigned`, `blocked`, `stale`, `filter`, `sort`) |
| `project-reports` | `/projects/{key}/reports` | velocity, burndown, cycle time, cumulative flow, scope change (params: `report`, `sprint`, `since`) |

Actions (base `/projects/`):

| Action | File | Params | Undo | Confirm | Agent approval | Log | Who |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `project_create` | `save.php` | **name**, **key** (2 to 10 capitals and digits — the prefix of every issue key), description, department, lead, visibility (open or private), board_mode (scrum or kanban), swimlane (none or assignee or epic), customer | archive it | | | `project.create` | admin |
| `project_update` | `save.php` | **project**, any field of project_create | restore prior | | | `project.update` | admin |
| `project_archive` | `archive.php` | **project**, reason | — (an admin unarchives from the kernel's trail) | ✔ | deletion | `project.archive` | admin |
| `team_add` | `team-add.php` | **project**, **member**, project_role (lead or product_owner or scrum_master or member or viewer — default member) | remove again | | | `team.add` | admin |
| `team_remove` | `team-remove.php` | **project**, **member** | add back | ✔ | other | `team.remove` | admin |
| `label_save` | `labels/save.php` | **project**, **name** (lower-case and hyphens), colour, label (to rename or recolour an existing one) | restore prior / archive new | | | `label.save` | admin |
| `label_archive` | `labels/archive.php` | **label** | restore | | | `label.archive` | admin |
| `component_save` | `components/save.php` | **project**, **name**, description, default_assignee, component (to change an existing one) | restore prior / archive new | | | `component.save` | admin |
| `component_archive` | `components/archive.php` | **component** | restore | | | `component.archive` | admin |
| `workflow_save` | `workflow/save.php` | **project**, statuses (name:category:order per status comma-separated — categories are todo in_progress done), transitions (from>to per move comma-separated), initial (the status a new issue starts in) | restore prior | ✔ | other | `workflow.save` | admin |
| `board_save` | `board/save.php` | **project**, columns (name:statuses:wip per column comma-separated — statuses joined with plus), swimlane (none or assignee or epic), board_mode (scrum or kanban) | restore prior | | other | `board.save` | sm, admin |

## Issues

Screens:

| Screen id | URL | When the user wants… |
| --- | --- | --- |
| `issue-view` | `/issues/{key}` | one issue: fields, description, subtasks, links, attachments, checklist, worklogs, comments, history, watch (params: `tab`) |
| `issue-add` | `/issues/new` | to create an issue — the type decides the fields (params: `project`, `type`, `parent`, `epic`, `sprint`, `assignee`, `summary`) |
| `issue-edit` | `/issues/{key}/edit` | to change an issue's summary, description, type, priority, points, estimate, assignee, epic, component, labels, due date |

Actions (base `/issues/`):

| Action | File | Params | Undo | Confirm | Agent approval | Log | Who |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `issue_create` | `save.php` | **project**, **summary**, type (story or task or bug — a subtask needs parent), description, priority (lowest or low or medium or high or highest), story_points, estimate_hours, assignee, epic, sprint (or backlog), component, labels[], due_date, parent (makes it a subtask of that issue) | delete it | | | `issue.create` | team |
| `issue_update` | `save.php` | **issue**, any field of issue_create | restore prior | | | `issue.update` | team |
| `issue_assign` | `assign.php` | **issue**, assignee (a person or an agent — empty unassigns), note | restore prior | | | `issue.assign` | team |
| `issue_transition` | `transition.php` | **issue**, **status** (a status of the project's workflow the current one may move to), resolution (done or wont_do or duplicate or cannot_reproduce — when moving to done) | move back | | | `issue.transition` | team |
| `issue_estimate` | `estimate.php` | **issue**, story_points (empty clears it), estimate_hours | restore prior | | | `issue.estimate` | po, team |
| `issue_rank` | `rank.php` | **issue**, before (the issue to place it above), after (the issue to place it below — or top or bottom) | restore prior | | | `issue.rank` | po |
| `issue_move_to_sprint` | `sprint.php` | **issue**, **sprint** (a future or the active sprint — or backlog) | restore prior | | | `issue.move_to_sprint` | po, sm |
| `issue_accept` | `accept.php` | **issue** (a done story), accepted (true or false — false reopens it with a note), note | restore prior | | | `issue.accept` | po |
| `issue_link` | `link.php` | **issue**, **kind** (blocks or relates or duplicates), **to_issue** | unlink | | | `issue.link` | team |
| `issue_unlink` | `unlink.php` | **link** | link again | | | `issue.unlink` | team |
| `issue_move_project` | `move.php` | **issue**, **project** (the new key is issued and the old kept as an alias) | move back | ✔ | | `issue.move_project` | admin |
| `issue_delete` | `delete.php` | **issue**, reason | — | ✔ | deletion | `issue.delete` | admin |
| `watch` | `watch.php` | **issue** | unwatch | | | `issue.watch` | all |
| `unwatch` | `unwatch.php` | **issue** | watch | | | `issue.unwatch` | all |
| `comment_add` | `comments/save.php` | **issue**, **body** | delete it | | | `comment.add` | team, all (a viewer may not) |
| `comment_edit` | `comments/save.php` | **comment**, **body** | restore prior | | | `comment.edit` | own |
| `comment_delete` | `comments/delete.php` | **comment** | — | ✔ | deletion | `comment.delete` | own, admin |
| `checklist_add` | `checklist/save.php` | **issue**, **text** | delete it | | | `checklist_item.add` | team |
| `checklist_toggle` | `checklist/toggle.php` | **checklist_item** | toggle back | | | `checklist_item.toggle` | team |
| `checklist_delete` | `checklist/delete.php` | **checklist_item** | add back | | | `checklist_item.delete` | team |
| `worklog_add` | `worklogs/save.php` | **issue**, **hours** (up to 24), worked_on (a date — default today), note | delete it | | | `worklog.add` | team |
| `worklog_delete` | `worklogs/delete.php` | **worklog** | add back | | | `worklog.delete` | own, admin |
| `attachment_add` | `attachments/save.php` | **issue**, **file** (the screen's upload — never by an agent) | delete it | | | `attachment.add` | team |
| `attachment_delete` | `attachments/delete.php` | **attachment** | — | ✔ | deletion | `attachment.delete` | own, admin |

## Sprints

Screens:

| Screen id | URL | When the user wants… |
| --- | --- | --- |
| `sprint-view` | `/sprints/{id}` | one sprint: goal, dates, burndown, its issues by status, scope changes, and to close it with carry-over |
| `sprint-add` | `/sprints/new` | to create a sprint in a project (params: `project`, `name`, `goal`, `start_date`, `end_date`) |
| `sprint-edit` | `/sprints/{id}/edit` | to change a sprint's name, goal or dates |

Actions (base `/sprints/`):

| Action | File | Params | Undo | Confirm | Agent approval | Log | Who |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `sprint_create` | `save.php` | **project**, **name**, goal, start_date, end_date | delete it while future | | | `sprint.create` | po, sm |
| `sprint_update` | `save.php` | **sprint**, name, goal, start_date, end_date | restore prior | | | `sprint.update` | po, sm |
| `sprint_start` | `start.php` | **sprint** (a future one — records the commitment), start_date, end_date | — (the commitment is recorded) | ✔ | other | `sprint.start` | sm |
| `sprint_close` | `close.php` | **sprint** (the active one — records what was done), carry_to (the future sprint the unfinished go to — or backlog) | — | ✔ | other | `sprint.close` | sm |
| `sprint_delete` | `delete.php` | **sprint** (a future sprint with no issues) | — | ✔ | deletion | `sprint.delete` | sm, admin |

## Epics

Screens:

| Screen id | URL | When the user wants… |
| --- | --- | --- |
| `epic-view` | `/epics/{id}` | one epic: its summary, progress, and its issues |
| `epic-add` | `/epics/new` | to create an epic in a project (params: `project`, `name`, `target_date`) |
| `epic-edit` | `/epics/{id}/edit` | to change an epic's name, summary, colour, target date or status |

Actions (base `/epics/`):

| Action | File | Params | Undo | Confirm | Agent approval | Log | Who |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `epic_create` | `save.php` | **project**, **name**, summary, colour, target_date | close it | | | `epic.create` | po |
| `epic_update` | `save.php` | **epic**, any field of epic_create | restore prior | | | `epic.update` | po |
| `epic_close` | `close.php` | **epic**, status (done or cancelled — default done) | reopen | | | `epic.close` | po |

## Saved filters

Screens: none of its own — the issues screen keeps them (`project-issues`, param `filter`).

Actions (base `/filters/`):

| Action | File | Params | Undo | Confirm | Agent approval | Log | Who |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `filter_save` | `save.php` | **name**, project (empty = across projects), filter (the issues screen's query string), is_shared, saved_filter (to change an existing one) | delete it | | | `saved_filter.save` | all (own) |
| `filter_delete` | `delete.php` | **saved_filter** | — | ✔ | deletion | `saved_filter.delete` | own |

## Counts

Screens: 25. Actions: 48. Agent-approval actions: 11 (`other` 5 — team remove, sprint start, sprint close, workflow save, board save; `deletion` 6 — project archive, issue delete, comment delete, attachment delete, sprint delete, filter delete).
