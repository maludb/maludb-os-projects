# Build spec: epics, links, labels, components, subtasks, moving an issue (slice 4)

Replicates the exemplar. Schema: `epics` (db/007), `labels`, `components` (db/006), `issue_links`, `issue_aliases`,
`issue_labels`, `issues.parent_issue_id` (db/008), `projects_link_would_cycle` (db/010), `mcp_epics`, `mcp_labels`,
`mcp_components`, `mcp_issue_links` (db/011). Links, subtasks and labels on the issue view were rendered by slice 1
with this slice's handlers stubbed; this slice fills them and adds the screens.

## Screens

| Screen id | Canonical URL | Purpose |
|---|---|---|
| `epics-list` | `/projects/{KEY}/epics` | The project's epics as cards with progress |
| `epic-view` | `/epics/{id}` | One epic: summary, progress, its issues, target date |
| `epic-add` / `epic-edit` | `/epics/new?project=KEY`, `/epics/{id}/edit` | Create or change an epic |
| `labels-settings` | `/projects/{KEY}/settings?tab=labels` | Labels and components (rendered by slice 6's settings page; the handlers are this slice's) |

## Epics list
- Cards: colour edge, name (→ `epic-view`), summary (two lines), status badge (open/done/cancelled), target date (overdue in danger), progress bar `points_done`/`points` with `issues_done`/`issues` beneath. Open first by `sort_order`, then done, then cancelled; a filter `status`. **New epic** (`epic.write`).

## Epic view
- Header: name, colour, status badge; buttons **Edit**, **Mark done** / **Reopen** (`epic.write`; done requires every issue done or the confirm "N issues are not done"), **Cancel epic** (confirm; issues keep the epic but it is shown struck).
- Body: summary; progress (points and issues); **Issues** table (this epic's top-level issues: key, type, summary, status, assignee, points, sprint) with **Add issue** (→ `/issues/new?project=KEY&epic=id`); **Activity** (the epic's rows and its issues' transitions, 20).
- `data-screen="epic-view" data-entity="epic" data-record-id="{id}"`.

## Epic form
| Field | Input | Required | Rule | id |
|---|---|---|---|---|
| project | hidden | yes | | `epic-form-field-project` |
| name | text | yes | 1–120, unique in the project | `epic-form-field-name` |
| summary | textarea | no | | `epic-form-field-summary` |
| colour | select of the theme's tones | yes | default primary | `epic-form-field-colour` |
| target_date | date | no | | `epic-form-field-target-date` |
| sort_order | number | no | | `epic-form-field-sort-order` |

## Labels and components (the settings tab)
- Labels: a table (name chip in its colour, issues count, archive ✕) and an add row (name `^[a-z0-9][a-z0-9_-]{0,39}$`, colour select) posting `label_save`; editing a colour is `label_save` with `id`. Archiving keeps the rows and hides the label from pickers.
- Components: a table (name, description, default assignee, archive ✕) and an add row posting `component_save` (name 1–60, description, default assignee select2).
- Ids: `label-form-field-name`, `label-form-field-colour`, `component-form-field-name`, `component-form-field-description`, `component-form-field-default-assignee`.

## Links (on the issue view, slice 1's region)
- Add: kind select (blocks / is blocked by / relates to / duplicates) + the other issue's key (typed, resolved by `find_issue_by_key`; another project's key allowed when the member may see it). "Is blocked by" is stored as the other issue `blocks` this one. A cycle → 422 "That would make a cycle of blocks". Remove ✕ on a link (`issue_unlink`).
- The blocked mark everywhere (list, board, my work) is `open_blockers > 0`.

## Subtasks (on the issue view)
- The add box (summary only) POSTs `issue_create` with `parent` = this issue; the trigger sets type `subtask`, the parent's project and sprint. A subtask's page shows "Subtask of KEY-n". A parent shows `subtasks_done`/`subtasks`. A subtask cannot have subtasks (422).

## Moving an issue between projects (`issue_move_project`)
- On the issue edit form for `project.admin` of both projects: a **Move to project** select. The move gives the issue (and its subtasks) a new key in the target (`issues_before_insert` is not fired on UPDATE: the handler takes the next number from the target's counter, writes the old key to `issue_aliases`, clears sprint, epic, component and labels that belong to the source, sets the status to the target's initial status) and logs `issue.move_project` (`before`: key, project_key; `after`: key, project_key). The old key redirects.

## Files (exactly these)
- `html/epics/index.php` (redirects to the project's epics when `?project=`) · `view.php` · `form.php` · `save.php` · `status.php`
- `html/projects/epics.php`
- `html/labels/save.php` · `archive.php` — `html/components/save.php` · `archive.php`
- `html/issues/move-project.php`
- `app/features/epics/queries.php` · `present.php` — `app/features/labels/queries.php`
- `app/views/epics/page.php` · `view.php` · `form.php` · `partials/cards.php` · `card.php` · `issues.php` — `app/views/projects/epics.php` — `app/views/projects/partials/labels-form.php` · `components-form.php`

## Query functions (signatures fixed)
- `find_epics(PDO, int $projectId, string $status = ''): array` · `find_epic(PDO, int $id): ?array` · `find_epic_for_edit(PDO, int $id): ?array` · `upsert_epic(PDO, ?int $id, array $fields, int $actorId): array` · `set_epic_status(PDO, int $id, string $status, int $actorId): array` · `epic_issues(PDO, int $id): array`
- `find_labels(PDO, int $projectId, bool $includeArchived = false): array` · `save_label(PDO, int $projectId, ?int $id, string $name, string $colour, int $actorId): array` · `archive_label(PDO, int $id, int $actorId): bool`
- `find_components(PDO, int $projectId, bool $includeArchived = false): array` · `save_component(PDO, int $projectId, ?int $id, array $fields, int $actorId): array` · `archive_component(PDO, int $id, int $actorId): bool`
- `move_issue_to_project(PDO, int $issueId, int $targetProjectId, int $actorId): array`

## Handlers
- `epics/save.php` (`epic_create` / `epic_update`): `require_right_in('epic.write', $projectId)`; log `epic.create` (`after`: name, target_date) / `epic.update` (`before`/`after`); `saved_go("/epics/$id")`; `HX-Trigger: epicChanged`.
- `epics/status.php` (`epic_update` with `status`): `epic.write`; log `epic.close` or `epic.reopen` (`before`/`after`: status; the manifest's name, 2026-09-28).
- `labels/save.php` (`label_save`), `labels/archive.php`: `require_right_in('project.admin')`; log `label.save` / `label.archive`.
- `components/save.php` (`component_save`), `components/archive.php`: `project.admin`; log `component.save` / `component.archive`.
- `issues/link.php`, `unlink.php` (slice 1's, now real): `require_can_work` on both; the cycle sentence.
- `issues/move-project.php` (`issue_move_project`): `project.admin` on both; log `issue.move_project`; `saved_go` to the new key.

## Action manifest entries
- Screens: `epics-list`, `epic-view`, `epic-add`, `epic-edit`, `labels-settings`.
- Actions: `epic_create`, `epic_update`, `label_save`, `component_save`, `issue_move_project` (add to the manifest: `project.admin`, undo —, confirm ✔).

## Activity log events
- `screen.view`, `epic.create`, `epic.update`, `epic.close`, `epic.reopen`, `label.save`, `label.archive`, `component.save`, `component.archive`, `issue.link`, `issue.unlink`, `issue.move_project`.

## Proof
- Epic progress equals the sum of its top-level issues' points; marking done with open issues asks; a cancelled epic is struck on its issues.
- A → blocks → B, B → blocks → C, then C → blocks → A → 422 with the sentence; "is blocked by" stores the reverse; the blocked mark appears on the issue, the board and My work while the blocker is open and disappears when it is done.
- A subtask inherits project and sprint; a subtask of a subtask → 422.
- A move between projects gives `NEW-1`, keeps `OLD-7` as an alias that redirects, moves the subtasks, clears the source's sprint/epic/labels, lands on the target's initial status.
- Labels: a new colour on save; an archived label stays on its issues and leaves the pickers.
- 375 px on every screen.

## Built and proven (2026-09-28)
By a fork on the exemplar: `app/features/epics/{queries,present}.php` (epics, labels, components, `move_issue_to_project()`,
`projects_i_administer()`); the Epics tab as cards with a status filter (`html/projects/epics.php`, region `#epics-list-results`);
`html/epics/{index,view,form,save,close,status}.php` (`status.php` an alias of `close.php`); labels and components at
`html/labels/*`, `html/components/*` with aliases at the registry's `html/projects/{labels,components}/*` paths, edited on the
project's settings `?section=labels` (region `#project-labels-components`, refreshed by `labelsChanged`); the move to another
project (`html/issues/move-project.php`, alias `move.php`) offered on a parent issue only when another administered project
exists, never on a subtask. Links and subtasks already met the spec in slice 1. Proven live as member 1 through a hand-off:
the empty state, SMOKE Epic created, duplicate name and off-list colour refused (HTMX 422 re-renders the form with
HX-Retarget), an issue's points counted into the epic's progress, done with the open-issue confirm, reopened, cancelled;
labels and a component saved, set on an issue, archived (the issue keeps them, the pickers lose them); SMK1-12 with its
subtask moved to SMK4 as SMK4-2/SMK4-3 with the old keys answering 301, epic, labels, component and sprint cleared and the
status mapped to the target's first; self-move, subtask and unknown project refused; every activity row named above; headless
Chromium at 1280×800 and 375×740 with no sideways scroll and no console errors. The close logs `epic.close` / `epic.reopen`
(the manifest's name). The "no right → no button" case is proven by code path only — the mirror holds no non-admin member yet.
SMOKE data left: epic 1 in SMK1 (SMK1-14, SMK1-15), labels `smoke-red` (live) and `smoke-blue` (archived), component
"SMOKE part" (archived), project SMK4 with SMK4-1..3, aliases SMK1-10/12/13.

## Open Questions
- (none)
