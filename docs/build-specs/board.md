# Build spec: the board (slice 3)

Replicates the exemplar. Schema: `board_columns`, `board_column_statuses`, `projects.board_mode`,
`projects.swimlane` (db/006), `issues` (db/008), `mcp_board_columns`, `mcp_issues`, `mcp_workflow_statuses` (db/011).

## Screens

| Screen id | Canonical URL | Purpose |
|---|---|---|
| `board` | `/projects/{KEY}/board` | The kanban: the active sprint's issues (scrum mode) or every open issue (kanban mode) by column |
| `board-settings` | `/projects/{KEY}/settings?tab=board` | Columns, their statuses, WIP limits, the swimlane (rendered by slice 6's settings page; the handler is this slice's) |

## The board
- Header: project key + name, the sprint (scrum: name, goal, days left, `points_done`/`points`; kanban: "All open work"), **Swimlane** select (none / assignee / epic — the project's default from `projects.swimlane`, overridable in the URL `?lane=`), **Filter** (`assignee`=me/id, `epic`, `type`, `q`), **Board settings** (`board.configure`).
- Columns from `mcp_board_columns` by `sort_order`; a column shows its name, the count of its cards, and `count / wip_limit` in danger when over the limit. The column panel: `#board` is `overflow-x: auto` with a visible scrollbar (the design system's rule: no hidden overflow), columns of fixed width 280 px, cards 44 px minimum height.
- Cards: key (→ `issue-view`), type icon, summary (two lines), assignee avatar (an agent: the cpu icon), points badge, labels chips, epic chip, priority icon, a blocked mark when `open_blockers > 0`, subtasks done/total. Swimlanes group the cards by assignee (unassigned last) or epic (no epic last); a swimlane is a row across all columns with a heading.
- **Moving a card**: each card carries a **⋯ Move to** control (a small select of the statuses reachable from its status, from `can_move_to`, grouped by column) posting `issue_transition`; the enhancement: every column list is `data-sortable` in one group; a drop onto another column POSTs `issue_transition` to the first status of that column reachable from the card's status — if none is reachable the drop is refused with the trigger's sentence and the card springs back (the server answers 422 and the region re-renders). A drop within a column POSTs nothing (columns are not ranked).
- **Live**: `#board-columns` has `hx-trigger="every 30s, issueChanged from:body, boardChanged from:body"` and re-fetches itself (Pattern B on the region, `hx-swap="outerHTML"`); the swap keeps the scroll position (`hx-swap` with `scroll:none`) and never interrupts a drag (the client defers the swap while `Sortable` is active).
- Kanban mode (`board_mode = 'kanban'`): every top-level issue with status category ≠ done plus those resolved in the last 3 days in the Done column; a "Done older than 3 days" link to the issues list.
- `data-screen="board" data-entity="project" data-record-id="{id}"`.

## Board settings (the handler; the page is slice 6's settings tab)
| Field | Input | Required | Rule | id |
|---|---|---|---|---|
| columns[i][name] | text | yes | 1–40, unique | `board-form-field-column-name-{i}` |
| columns[i][statuses][] | multi select of the workflow's statuses | yes | a status in exactly one column; every non-archived status somewhere | `board-form-field-column-statuses-{i}` |
| columns[i][wip_limit] | number | no | > 0 | `board-form-field-column-wip-{i}` |
| swimlane | select none/assignee/epic | yes | | `board-form-field-swimlane` |
| board_mode | select scrum/kanban | yes | | `board-form-field-board-mode` |
- Rows can be added and removed (an ✕ on the row; add-row button appends a blank row through a fragment, Pattern A). Save replaces the columns in one transaction (delete + insert; a column's identity is its name).

## Files (exactly these)
- `html/projects/board.php` — `html/board/columns.php` (the region fragment for polling; same query as the page) — `html/board/save.php`
- `app/features/board/queries.php` · `present.php`
- `app/views/projects/board.php` · `partials/board-columns.php` · `board-card.php` · `board-form.php`
- `html/assets/js/board.js` (the SortableJS binding for the board and the backlog: reads `data-sortable-group`, `data-post`, `data-issue`; posts through `htmx.ajax`; defers swaps during a drag)

## Query functions (signatures fixed)
- `find_board(PDO, int $projectId, array $filters, string $lane): array` (columns with cards, grouped by lane; the reachable statuses per card)
- `find_board_config(PDO, int $projectId): array` · `save_board_config(PDO, int $projectId, array $columns, string $swimlane, string $mode, int $actorId): array`

## Handlers
- `board/save.php` (`board_save`, agent category `other`): `require_right_in('board.configure', $projectId)`; validate that every non-archived status is in exactly one column; log `board.save` (`before`/`after`: columns as `[name → statuses, wip]`, swimlane, mode); `HX-Trigger: boardChanged`.
- The transition on drop is slice 1's `issues/transition.php`, called with `to_status` = the column's first reachable status; a drop also carries `from_column`/`to_column` into `after` for the trail.

## Action manifest entries
- Screens: `board`, `board-settings`. Actions: `board_save` (`issue_transition` is slice 1's; `board` is a read tool).

## Activity log events
- `screen.view` (board, with `after.lane`), `board.save`, and slice 1's `issue.transition` (with `after.from_column`, `after.to_column` when it came from the board).

## Proof
- The columns match the config; a status in no column → the save is refused with the sentence naming it; WIP: a column at its limit shows in danger and still accepts a drop (a limit warns, never blocks — the Scrum Master flags it).
- A drop to a column with no reachable status → 422 and the card springs back; a drop to a reachable one moves it and `issue.transition` carries the columns.
- Polling: with a second session moving a card, the first board shows it within 30 s; a drag in progress is not interrupted by the poll.
- Swimlanes by assignee put an agent's lane beside a person's; unassigned last.
- 375 px: the board scrolls horizontally inside `#board` with a visible scrollbar, the page does not; cards are 44 px tall at least; the Move-to select works without JavaScript.
- Kanban mode shows every open issue and the last 3 days of done.

## Built and proven (2026-09-28)
By a fork on the exemplar: `app/features/board/{queries,present}.php` (`find_board`, `find_board_config`, `save_board_config`);
`html/projects/board.php` and the polled region `html/board/columns.php` (trigger `every 30s [not dragging], issueChanged,
boardChanged`; swimlanes by assignee or epic; the header's filters); the settings screen `html/board/form.php?key=<project>`
(`?fragment=row` for a blank row) and `html/board/save.php` (`board_save`, the manifest's string form accepted); the views
`app/views/projects/board.php`, `partials/board-columns.php`, `board-card.php`, `board-form.php`; `html/assets/js/board.js` —
SortableJS over each `[data-board-droppable]` column, a drop posting `issue_transition` to the first status of the column
reachable from the card, an unreachable drop springing back with the sentence in `#flash`, `data-dragging` deferring the poll,
rebound after every swap. Two shared edits: `html/issues/transition.php` accepts `id`/`value` (what a drop sends) and records
`from_column`/`to_column` in the trail; `app/views/layout.php` gained the `htmx-config` meta with `responseHandling` so a 422
swaps — htmx 2 swaps nothing above 3xx by default, so before it no refused write in the application showed its sentence in the
browser. Proven live as member 1 through hand-offs: the card's select and a drop by `id`/`value` both moving an issue (204,
`issueChanged`), To do → Done refused into `#flash`; four columns with counts, both swimlanes, the region alone, the JSON
branch with `can_move_to`, `over_limit` and `lanes`; a WIP limit of 1 shown as `3 / 1` in danger and blocking nothing; a
status in no column refused with the rows kept; `board.save` logged before/after; headless Chromium at 1280×800 and 375×740
with the `#board` panel scrolling and the page not, a real drag landing In progress after the refresh, a refused drag springing
back with its sentence, the settings' add, remove and save. Cosmetic: the header's GET form pushes empty params. Fixed by the
parent: the transition's reply named the issue's status `status`, clobbering the envelope's `success` — it is `new_status`.
SMOKE data left: SMK1-8 and SMK1-9 on To do; the board back to four columns.

## Open Questions
- (none)
