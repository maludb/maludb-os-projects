# Build spec: backlog and sprints (slice 2)

Replicates the exemplar. Schema: `sprints`, `sprint_scope_events` (db/007), `issues.rank`, `issues.sprint_id`
(db/008), `projects_rank_between`, `sprint_start`, `sprint_close`, `sprint_burndown` (db/010),
`mcp_sprints`, `mcp_sprint_scope_events`, `mcp_issues` (db/011).

## Screens

| Screen id | Canonical URL | Purpose |
|---|---|---|
| `backlog` | `/projects/{KEY}/backlog` | The ranked backlog with the sprint boxes above it — planning happens here |
| `sprints-list` | `/projects/{KEY}/sprints` | Every sprint of the project, future → active → closed |
| `sprint-view` | `/sprints/{id}` | One sprint: goal, dates, its issues, the burndown, scope changes; close |
| `sprint-add` / `sprint-edit` | `/sprints/new?project=KEY`, `/sprints/{id}/edit` | Create or change a sprint |

## Backlog
- Top: **the sprint boxes** — the active sprint first (name, goal, days left, `points_done`/`points`, its top-level issues), then each future sprint by `sort_order` (name, dates, points, its issues, **Start sprint** for `sprint.run` when none is active, **Edit**), then **Create sprint** (`sprint.plan`).
- Below: **Backlog** — every top-level issue with `sprint_id IS NULL` and status category ≠ done, in `rank` order: key, type icon, summary (→ `issue-view`), priority, assignee avatar, epic chip, labels, **points** (an inline select from `point_scale` posting `issue_estimate`, `backlog.rank`), and the move controls.
- Move controls, always present: **▲ ▼** (POST `issue_rank` with `before`/`after` = the neighbour's issue id, computed server-side from `projects_rank_between`), **Move to…** (a select of the sprint boxes + "Backlog", POST `issue_move_to_sprint`), **Top** / **Bottom**. Enhancement: every list is `data-sortable`; a drop within the backlog POSTs `issue_rank` with the new neighbours; a drop into a sprint box POSTs `issue_move_to_sprint`; the server answers the re-rendered region (Pattern D, `HX-Trigger: backlogChanged`).
- Filters (URL): `q`, `epic`, `assignee`, `type`, `unestimated` (1). Count and points totals per box.
- Region `#backlog-boxes` and `#backlog-list` re-fetch on `backlogChanged` / `issueChanged` / `sprintChanged`.
- Region polling: none on the backlog (the board polls).

## Sprints list
- A table: name (→ `sprint-view`), state badge, dates, goal (truncated), issues, points, committed → completed (closed ones), carried. Future first, then active, then closed newest first. **New sprint** (`sprint.plan`).

## Sprint view
- Header: name, state badge, project key (→ `project-view`), dates and days left; buttons **Edit** (`sprint.plan`; a closed sprint is read-only), **Start** (future; `sprint.run`; posts `sprint_start`), **Close** (active; `sprint.run`; a confirm form: carry the unfinished to *next future sprint* (select) or *the backlog*; posts `sprint_close`).
- Body: goal; **Burndown** (SVG from `sprint_burndown(id)`: remaining vs the ideal line; scope as a faint step line); **Issues** table by status category with points; **Scope changes** (from `mcp_sprint_scope_events`: when, who, added/removed/done/reopened/estimated, issue key, points) — the report of design question 10; after close: committed / completed / carried and the carry-over destination.
- `data-screen="sprint-view" data-entity="sprint" data-record-id="{id}"`.

## Sprint form
| Field | Input | Required | Rule | id |
|---|---|---|---|---|
| project | hidden | yes | | `sprint-form-field-project` |
| name | text | yes | 1–80, unique in the project; default "Sprint N" | `sprint-form-field-name` |
| goal | textarea | no | | `sprint-form-field-goal` |
| start_date | date | no | | `sprint-form-field-start-date` |
| end_date | date | no | ≥ start; default start + `sprint_length_days` | `sprint-form-field-end-date` |
| sort_order | number | no | among future sprints | `sprint-form-field-sort-order` |

## Files (exactly these)
- `html/projects/backlog.php` — `html/sprints/index.php` (redirects to the project's sprints when `?project=`) · `view.php` · `form.php` · `save.php` · `start.php` · `close.php`
- `html/issues/rank.php` · `move-to-sprint.php`
- `app/features/sprints/queries.php` · `present.php`
- `app/views/projects/backlog.php` · `partials/backlog-boxes.php` · `backlog-list.php` · `backlog-row.php`
- `app/views/sprints/page.php` · `view.php` · `form.php` · `partials/burndown.php` · `scope-events.php` · `close-form.php`

## Query functions (signatures fixed)
- `find_backlog(PDO, int $projectId, array $filters): array` (top-level, no sprint, not done, by rank)
- `find_sprint_boxes(PDO, int $projectId): array` (active + future sprints, each with its top-level issues by rank)
- `find_sprints(PDO, int $projectId): array` · `find_sprint(PDO, int $id): ?array` · `find_sprint_for_edit(PDO, int $id): ?array`
- `upsert_sprint(PDO, ?int $id, array $fields, int $actorId): array`
- `rank_issue(PDO, int $issueId, ?int $beforeIssueId, ?int $afterIssueId, int $actorId): array` (computes the new rank with `projects_rank_between`; `before`/`after` both null = bottom)
- `move_issue_to_sprint(PDO, int $issueId, ?int $sprintId, int $actorId): array` (NULL = backlog; the trigger writes scope events and moves subtasks)
- `start_sprint(PDO, int $id, int $actorId, ?string $start, ?string $end): array` (calls `sprint_start`)
- `close_sprint(PDO, int $id, int $actorId, ?int $carryTo): array` (calls `sprint_close`; returns completed, carried, carried_issues)
- `sprint_burndown_rows(PDO, int $id): array` · `sprint_scope_events(PDO, int $id): array`

## Handlers
- `sprints/save.php` (`sprint_create` / `sprint_update`): `require_right_in('sprint.plan', $projectId)`; a closed sprint → 409; log `sprint.create` (`after`: name, goal, start_date, end_date) / `sprint.update` (`before`/`after`); `saved_go("/sprints/$id")`; `HX-Trigger: sprintChanged`.
- `sprints/start.php` (`sprint_start`, agent category `other`): `require_right_in('sprint.run')`; another active → 409 "A sprint is already active in this project"; log `sprint.start` (`after`: committed_points, issue_keys[], start_date, end_date).
- `sprints/close.php` (`sprint_close`, category `other`): `require_right_in('sprint.run')`; log `sprint.close` (`after`: completed_points, carried_points, carried_issues, carry_to_sprint_id); `saved_go("/sprints/$id")`.
- `issues/rank.php` (`issue_rank`): `require_right_in('backlog.rank')`; log `issue.rank` (`before`/`after`: rank; `after.before_key`, `after.after_key`); answers the backlog region.
- `issues/move-to-sprint.php` (`issue_move_to_sprint`): `require_right_in('sprint.plan')`; a closed sprint → 422; log `issue.move_to_sprint` (`before`/`after`: sprint_id, sprint_name).

## Action manifest entries
- Screens: `backlog`, `sprints-list`, `sprint-view`, `sprint-add`, `sprint-edit`.
- Actions: `sprint_create`, `sprint_update`, `sprint_start`, `sprint_close`, `issue_rank`, `issue_move_to_sprint`.

## Activity log events
- `screen.view`, `sprint.create`, `sprint.update`, `sprint.start`, `sprint.close`, `issue.rank`, `issue.move_to_sprint` (plus `issue.estimate` from slice 1 when the inline points change).

## Proof
- Rank: ▲ on the second row swaps the order, and the ranks stay gapped (no renumbering); dragging (headless: `Sortable` event simulated by POSTing what the drop would) lands the same; `Top` and `Bottom`.
- Move to sprint: a top-level issue with two subtasks moves with them; into the ACTIVE sprint writes an `added` scope event with its points; back out writes `removed`; into a future sprint writes nothing.
- Start: `committed_points` = the sum at that moment; a second active → 409; the burndown's day 0 equals committed.
- Close: the done issues stay, the rest go to the chosen sprint or the backlog, `completed`/`carried` recorded, the sprint is read-only after; an agent's `sprint_close` → `pending_approval`.
- 375 px: the sprint boxes stack, the backlog rows keep their move controls reachable (44 px), no horizontal scroll.

## Built and proven (2026-09-28)
By a fork on the exemplar: `app/features/sprints/{queries,present}.php` with the spec's signatures; the backlog
(`html/projects/backlog.php`, views `app/views/projects/backlog.php` and partials `backlog-boxes`, `backlog-list`,
`backlog-row`) — the sprint boxes and the backlog list as one SortableJS group, a drop in the list posting `issues/rank.php`
and a drop in a box posting `issues/sprint.php` (the manifest's `move-to-sprint.php` is a shim of it), the inline points
select, Top/Up/Down/Bottom, Move to…, the filters (q, epic, assignee, type, unestimated; drag disabled while a filter is on),
regions `#backlog-boxes` and `#backlog-list` re-fetched on `backlogChanged`, `issueChanged`, `sprintChanged`; the sprints
list (`html/projects/sprints.php`, region `#sprints-list`); `html/sprints/{index,view,form,save,start,close,delete}.php` with
the sprint page's facts, the close form with its carry-over select, delete for an empty future sprint, the burndown SVG
(remaining, ideal dashed, scope), issues by category and the scope changes. Proven live as member 1: rank top, between,
bottom and "nothing said" 422 (`issue.rank` with before/after and the neighbours' keys); create, bad date and duplicate
name 422, edit, end-before-start 422, a closed sprint's edit 409; start with 8 points committed and the keys logged, start
again 409, a second active sprint 409; a move into the active sprint an `added` event, a re-estimate an `estimated` event,
Done a `done` event with the burndown answering; close to the backlog (2 issues, 11 points carried) and close into a future
sprint that then held them; a closed sprint takes nothing 422, closes again 409, delete 409; delete refused with an issue
in it and allowed once empty; the JSON branches; headless Chromium at 1280×800 and 375×740 with no sideways scroll and no
console errors, a real drag from the backlog into a box posting the move and re-rendering both regions (SortableJS's mouse
fallback forced in the probe only — headless Chromium runs no native HTML5 drag). Fixed on the way: the sprint page
requires `app/views/shared/chart.php` itself; the facts-and-burndown pair is a flex row (a `.row` straight under
`.main-content` overflowed by 8px). Fixed in db/014: `sprint_burndown()` (db/010) counted a re-estimate of an issue committed at start
as the full new value — `sprint_start()` now records one `committed` event per issue, so the re-estimate finds its previous
value; the scope-changes view leaves `committed` rows out (proven rolled back: 3 + 5 committed, 5 → 8, scope 11 not 16). SMOKE data left: SMK1-16..18, closed sprints 6, 9, 11.

## Open Questions
- (none)
