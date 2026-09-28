# Build spec: reports (slice 5)

Replicates the exemplar. Schema: the report functions of db/010 — `sprint_burndown`, `project_velocity`,
`project_cycle_time`, `project_flow`, `project_hygiene` — and `mcp_sprint_scope_events`, `mcp_sprints` (db/011).
Charts are hand-drawn SVG (`app/views/shared/chart.php`, decided in `sso-shell.md`).

## Screens

| Screen id | Canonical URL | Purpose |
|---|---|---|
| `reports` | `/projects/{KEY}/reports?report=velocity|burndown|cycle|flow|scope|hygiene` | One page, one report at a time, the report a URL |

## The page
- A pill strip (Pattern B on `#report-body`, `hx-push-url` explicit): **Velocity** (default) · **Burndown** · **Cycle time** · **Cumulative flow** · **Scope changes** · **Hygiene**. Every report has a one-sentence lead saying what it answers (design §5: 7, 8, 9, 10, 14), the chart, and the table under it with the same numbers (the numbers appear once: the chart for the shape, the table for the values — small tables only).
- **Velocity**: `project_velocity(id, 6)` — grouped bars per closed sprint: committed vs completed points; a line for the 3-sprint average; the table: sprint, dates, committed, completed, carried; the lead: "The team completes N points a sprint (average of the last 3)". Empty: "No sprint has closed yet."
- **Burndown**: a sprint select (active first, then closed by date; `?sprint=`); `sprint_burndown(sprint)` — remaining vs the ideal straight line from committed to 0 at `end_date`; scope as a faint step line; the table by day. Empty: "This sprint has not started."
- **Cycle time**: `project_cycle_time(id, since)` with a window select (30 / 90 / 180 days, `?since=`); bars per type: median, with the p85 as a whisker; the table: type, resolved, median, p85, mean (days). Lead: "A story takes N days from start to done (median, last 90 days)."
- **Cumulative flow**: `project_flow(id, since)`, window 30 / 60 / 90 — stacked areas todo / in progress / done by day; no table (60 rows would drown the point) but a "download CSV" link (`?format=csv`).
- **Scope changes**: the active sprint's (or `?sprint=`) `mcp_sprint_scope_events` as a table: when, who, event, issue key (→ view), points; totals: added, removed, net; the lead: "N points were added and M removed after the sprint started."
- **Hygiene**: `project_hygiene(id)` — a table: issue key (→ view), summary, reason, days; grouped by reason; the lead counts each. This is what the Scrum Master reads each morning; a person reads the same.
- `data-screen="reports" data-entity="project" data-record-id="{id}"`; `screen.view` carries `after.report`.

## Files (exactly these)
- `html/projects/reports.php` — `app/features/reports/queries.php` · `present.php` — `app/views/projects/reports.php` · `partials/report-velocity.php` · `report-burndown.php` · `report-cycle.php` · `report-flow.php` · `report-scope.php` · `report-hygiene.php` — `app/views/shared/chart.php` (from Phase 2; this slice adds `chart_stacked`)

## Query functions (signatures fixed)
- `report_velocity(PDO, int $projectId, int $limit = 6): array` · `report_burndown(PDO, int $sprintId): array` · `report_cycle_time(PDO, int $projectId, int $days = 90): array` · `report_flow(PDO, int $projectId, int $days = 30): array` · `report_scope_changes(PDO, int $sprintId): array` · `report_hygiene(PDO, int $projectId): array`
- `report_sprint_options(PDO, int $projectId): array`

## Handlers
- None that write. The CSV download is a GET on `reports.php?report=flow&format=csv` behind `projects_can_see_project`; logged `report.export` (`after`: report, days).

## Action manifest entries
- Screens: `reports` (prefill `report`, `sprint`, `since`). Actions: none. Read tools: `velocity`, `burndown`, `cycle_time`, `sprint_scope_changes`, `hygiene` answer the same functions.

## Activity log events
- `screen.view` (`after.report`), `report.export`.

## Proof
- Velocity matches the closed sprints' `committed_points` / `completed_points`; burndown day 0 = committed and the last day = remaining now; adding an issue to the active sprint bends the scope line the same day; cycle time excludes issues never started; the flow's three areas sum to the top-level issues created by that day; hygiene shows a stale issue after `stale_after_days` and drops it after a comment (a comment touches `updated_at` through the handler).
- Every SVG has a `<title>`, axes labelled with units (points, days), and reads at 375 px (the chart scales to the column; the table scrolls inside its card).
- The `?report=` pills push the URL; a reload lands on the same report.

## Built and proven (2026-09-28)
By a fork on the exemplar: `app/features/reports/{queries,present}.php` (six readers over the db/010 functions and the two
`mcp_*` views, `report_sprint_options()`; the window bound as a cast because PDO's integer bind will not coerce inside
`current_date - :d`); `html/projects/reports.php` (an unknown `report=` renders velocity; `screen.view` carries the report;
Pattern B on `#report-body` with `Vary: HX-Request`; `?format=csv` on the flow report answers `text/csv`, logged
`report.export`); `app/views/reports/{body,report-velocity,report-burndown,report-cycle,report-flow,report-scope,report-hygiene}.php`
with the pill strip pushing the URL. Every chart has its table beside it and its title as `aria-label`; the leads read as
sentences. Proven live as member 1 over a SMOKE fixture (SMK5: three closed sprints, one active with five scope events, one
future, eight issues, one blocking link): all six reports 200, one SVG and a table on velocity, burndown, cycle and flow,
scope with its five events and totals, hygiene grouping the four reasons; the CSV's header and 31 day rows; the six JSON
shapes; the HTMX branch alone; headless Chromium at 1280×800 and 375×740 on four reports with no sideways scroll and no
console errors, the Scope pill swapping the region alone. Two notes: the three-sprint average is stated in velocity's lead,
not drawn; p85 is a second bar, not a whisker. `view()` reserves `$data`, so a report's rows travel as `rd`. SMOKE data
left: project SMK5 with its sprints, issues and scope events.

## Open Questions
- (none)
