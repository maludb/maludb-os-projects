# Projects — MCP tool surface

2026-09-28 · the read tools every question in `docs/projects-design.md` §5 is answered by. Writes are
never here: they are the action tools the kernel's Actions MCP builds from `docs/projects-action-manifest.md`.

Conventions (mcp-and-api.md): Python 3 + FastMCP, streamable HTTP at `/mcp`, loopback, one systemd unit
each; `db.py` and `server_common.py` copied from the kernel; every query runs with `app.member_id` set
transaction-locally from the verified bearer, so the `mcp_*` views decide the rows. Tool names are
snake_case; every list tool takes `limit` (1–100, default 25); every tool carries `readOnlyHint: true`,
`openWorldHint: false`; results are JSON strings. Gates below are what the view already enforces — the
tool only trims. `see` = `projects_can_see_project()` (an admitted member, and the project is open and
they hold `project.read`, or they are on its team, or they administer its department, or they are a
Projects Admin; an admitted agent sees every open project); `own` = the caller's own rows.

Two token shapes, one key: a person's own `mcp_` token (`mcp_resolve_token`), or the tenant's signed
run token (`ACTION_TOKEN_KEY`), for which the server asks the kernel's run-facts call once per run and
offers exactly the tools granted — fail closed, and no tool acts when `is_eval`.

## Records server — `projects_records_mcp` (role `projects_records_ro`, `MCP_RECORDS_PORT`)

### Roles (kernel db/145, maludb-os-integration 0.4.1)
`app_roles` — no arguments — answers `os.app-roles/1`: Projects' roles (key, name, description,
capability, is_admin, rights[]) and its rights (key, description), from `mcp_app_roles` and
`projects_rights` (db/005). The kernel reads it with its own 60-second token, which reaches this tool
only; any person or agent may call it too.

### Projects
| Tool | Call it when | Answers | Reads | Key params | Gate |
|---|---|---|---|---|---|
| `find_projects` | Listing or looking up projects by key, name or department; the caller's own (on the team); active or archived. **Call before any action that needs a project** | 1 | `mcp_projects`, `mcp_project_team` | `q?`, `department_id?`, `mine?`, `status?` (active/archived, default active), `visibility?` | see |
| `get_project` | Everything about one project: the row, the team with project roles, the workflow's statuses and transitions, the board's columns and WIP, the active sprint (or the next future one), the epics with progress, labels, components | 1, 11 | `mcp_projects`, `mcp_project_team`, `mcp_workflow_statuses`, `mcp_board_columns`, `mcp_sprints`, `mcp_epics`, `mcp_labels`, `mcp_components` | `project_id` or `key` | see |

### Issues
| Tool | Call it when | Answers | Reads | Key params | Gate |
|---|---|---|---|---|---|
| `find_issues` | Any list of issues: by text, project, type, status or category, assignee or reporter, sprint, epic, label, component, priority; the unassigned; the blocked; the stale; what changed or was resolved since a date. **Call before any action that needs an issue** | 2, 4, 6, 12, 13, 14, 15, 17 | `mcp_issues` | `q?` (key or words), `project_id?`, `type?`, `status?` (name), `status_category?` (todo/in_progress/done/open), `assignee_member_id?`, `assignee_kind?` (human/agent), `reporter_member_id?`, `sprint_id?` (or `backlog`), `epic_id?`, `label?`, `component_id?`, `priority?`, `unassigned?`, `blocked?`, `stale_days?`, `updated_since?`, `resolved_since?`, `include_subtasks?` (default false), `sort?` (rank/updated/created/priority/due, default rank), `limit` | see |
| `get_issue` | One issue in full: fields, description, aliases, subtasks, links both ways, labels, watchers, checklist, comments, attachments (name, size, who), worklogs | 5, 12, 16 | `mcp_issues`, `mcp_issue_details`, `mcp_issue_links`, `mcp_issue_watchers`, `mcp_checklist_items`, `mcp_comments`, `mcp_attachments`, `mcp_worklogs` | `issue_id` or `key` (an alias resolves too) | see |
| `backlog` | The project's backlog in rank order — what is unsprinted and unestimated — with the future sprints' boxes above it (each sprint and its issues and points) | 3 | `mcp_issues`, `mcp_sprints` | `project_id`, `epic_id?`, `unestimated_only?`, `limit` | see |
| `board` | The board as shown: the active sprint's issues (in kanban mode, every open issue) by column in column order, each column's WIP limit and count, split by the project's swimlane (none/assignee/epic); blocked marked | 4, 6, 12, 13 | `mcp_board_columns`, `mcp_workflow_statuses`, `mcp_issues`, `mcp_sprints`, `mcp_projects` | `project_id`, `assignee_member_id?` (one lane) | see |
| `blocked_issues` | Which open issues are blocked, by what, and which issues block the most | 12 | `mcp_issue_links`, `mcp_issues` | `project_id?`, `limit` | see |
| `workload` | Who is working on what: open issues and points per assignee (agents marked), and who has nothing | 6, 13 | `mcp_issues`, `mcp_project_team`, `mcp_members` | `project_id?`, `sprint_id?` (default: the active sprint), `include_done?` | see |
| `my_work` | What is on a person's plate across projects, in the shape the kernel's aggregation wants: `kind` (issue), `id`, `title` (key — summary), `detail` (project · status), `due_at`, `state` (blocked / overdue / due today / due / in review / open), `href` (`/issues/KEY-n`), `urgency` (0–2), plus what they watch and what waits for their review | 2, 15 | `mcp_issues`, `mcp_issue_watchers` | `member_id?` (default: the caller), `include_watching?` (default true), `limit` | own; another member: admin of one of their departments, or Projects Admin |
| `hygiene` | What the Scrum Master flags: stale in progress, unassigned in the active sprint, unestimated stories in the next sprint, blocked with the blocker named | 14 | `project_hygiene()`, `mcp_issues` | `project_id`, `reason?` | see |

### Sprints, epics and reports
| Tool | Call it when | Answers | Reads | Key params | Gate |
|---|---|---|---|---|---|
| `sprint_status` | How a sprint stands: goal, dates, state, committed and completed points, issues by status category, days left. **Call before an action that needs a sprint** | 4, 7 | `mcp_sprints`, `mcp_issues` | `sprint_id?` (default: the project's active sprint), `project_id?`, `state?` (future/active/closed — to list them), `limit` | see |
| `burndown` | Points remaining day by day through a sprint, against its scope | 7 | `sprint_burndown()`, `mcp_sprints` | `sprint_id?` (default: the active sprint of `project_id`) | see |
| `velocity` | What the last sprints committed, finished and carried; what to commit to next (the average of the finished) | 8 | `project_velocity()` | `project_id`, `sprints?` (1–24, default 6) | see |
| `cycle_time` | From started to done, by issue type: median, 85th percentile, mean, over a window | 9 | `project_cycle_time()` | `project_id`, `since?` (default 90 days ago) | see |
| `flow` | Issues in to-do, in progress and done per day over a window (the cumulative-flow chart) | 4, 9 | `project_flow()` | `project_id`, `since?` (default 30 days ago) | see |
| `sprint_scope_changes` | What entered and left a sprint after it started, what was re-estimated, when and by whom | 10 | `mcp_sprint_scope_events` | `sprint_id`, `event?` (added/removed/done/reopened/estimated), `limit` | see |
| `epic_progress` | Each epic's issues and points done, target date, status | 11 | `mcp_epics`, `mcp_issues` | `project_id`, `epic_id?`, `status?` (default open) | see |
| `worklogs` | Hours logged: per issue, per person, over a sprint or a window | 16 | `mcp_worklogs`, `mcp_issues` | `project_id?`, `issue_id?`, `member_id?`, `sprint_id?`, `from?`, `to?`, `limit` | see |
| `projects_settings` | The defaults: timezone, sprint length, the point scale, the stale threshold, attachment limit, default visibility | — | `mcp_projects_settings` | — | all |
| `records_search` | The long tail: one SELECT over the views named in its docstring (`mcp_members`, `mcp_departments`, `mcp_department_members`, `mcp_projects`, `mcp_project_team`, `mcp_workflow_statuses`, `mcp_board_columns`, `mcp_labels`, `mcp_components`, `mcp_epics`, `mcp_sprints`, `mcp_sprint_scope_events`, `mcp_issues`, `mcp_issue_details`, `mcp_issue_links`, `mcp_issue_watchers`, `mcp_checklist_items`, `mcp_comments`, `mcp_attachments`, `mcp_worklogs`, `mcp_saved_filters`, `mcp_projects_settings`) | any | all views | `sql` | as the views; 5 s timeout; 200 rows |

## Activity server — `projects_activity_mcp` (role `projects_activity_ro`, `MCP_ACTIVITY_PORT`)

| Tool | Call it when | Answers | Reads | Key params | Gate |
|---|---|---|---|---|---|
| `issue_history` | Everything that happened to one issue, oldest first: transitions (from and to status, the sprint at the time), assignments (old and new), estimates (old and new), rank moves, sprint moves, links, comments, worklogs | 18, 19, 20, 22 | `mcp_activity_log` (entity `issue` and `comment` rows for it) | `issue_id` or `key`, `action_prefix?`, `limit?` | as the view |
| `who_did` | What one person or agent did in Projects over a period | 21 | `mcp_activity_log` | `member_id?` (default: the caller), `period?`, `project_id?`, `action_prefix?` | as the view (own; a project's: see) |
| `project_activity` | What happened in a project: yesterday, this week, since a date; by kind of event | 23 | `mcp_activity_log`, `mcp_issues` | `project_id`, `period?`, `action_prefix?`, `limit` | see |
| `sprint_events` | A sprint's log: created, started (with what it committed), scope changes, closed (what was done and carried) | 10, 24 | `mcp_activity_log` (`sprint.*`), `mcp_sprint_scope_events` | `sprint_id?`, `project_id?` (every sprint's start and close), `limit` | see |
| `activity_search` | The long tail: one SELECT over `mcp_activity_log` (and `mcp_members`, `mcp_projects`, `mcp_issues`, `mcp_sprints` for names) | any | those views | `sql` | as the view; 5 s; 200 rows |

## Question → tool map

1 `find_projects` · 2 `my_work` + `find_issues` · 3 `backlog` · 4 `board` + `sprint_status` · 5 `get_issue` · 6 `workload` + `board` ·
7 `burndown` + `sprint_status` · 8 `velocity` · 9 `cycle_time` + `flow` · 10 `sprint_scope_changes` + `sprint_events` · 11 `epic_progress` + `get_project` ·
12 `blocked_issues` + `find_issues` · 13 `find_issues` (assignee_kind agent) + `workload` · 14 `hygiene` + `find_issues` · 15 `my_work` + `find_issues` ·
16 `worklogs` · 17 `find_issues` · 18 `issue_history` · 19 `issue_history` · 20 `issue_history` · 21 `who_did` · 22 `issue_history` ·
23 `project_activity` · 24 `sprint_events`.

## What the log must carry for the activity tools (the manifest's log-payload rules)

`issue.transition` — `before.status`, `after.status`, `after.status_category`, `after.sprint_id` · `issue.assign` — `before.assignee_member_id`,
`after.assignee_member_id` (and names), `after.note` · `issue.estimate` — `before.story_points`, `after.story_points` · `issue.rank` —
`before.rank`, `after.rank`, `after.before_key`/`after.after_key` · `issue.move_to_sprint` — `before.sprint_id`, `after.sprint_id` (null = backlog) ·
`issue.create` — `after.type`, `after.sprint_id`, `after.epic_id` · `sprint.start` — `after.committed_points`, `after.issue_keys[]` · `sprint.close` —
`after.completed_points`, `after.carried_points`, `after.carried_keys[]`, `after.carry_to`.

## Entity resolution for the action tools (the registry's `resolve` block)

| Entity param | Tool | query param | id field | label field |
|---|---|---|---|---|
| `project` | `find_projects` | `q` (key or name) | `project_id` | `name` |
| `issue`, `parent`, `from_issue`, `to_issue` | `find_issues` | `q` (KEY-n, an alias, or words of the summary) | `issue_id` | `key` |
| `sprint`, `carry_to` | `sprint_status` (`state` listing) | `q` (name) | `sprint_id` | `name` |
| `epic` | `epic_progress` | `q` (name) | `epic_id` | `name` |
| `member`, `assignee`, `reporter`, `lead` | `find_members` (the directory: `mcp_members` — a thin tool on the records server, `q`, `member_kind?`) | `q` | `member_id` | `display_name` |
| `department` | `find_departments` (`mcp_departments`, `q`) | `q` | `department_id` | `name` |
| `label` | `get_project` labels | `q` | `label_id` | `name` |
| `component` | `get_project` components | `q` | `component_id` | `name` |
| `status` | `get_project` statuses | `q` (name) | `status_id` | `name` |
| `comment`, `checklist_item`, `worklog`, `attachment`, `link`, `filter` | `get_issue` (the screen supplies the id) | — | its `*_id` | — |

`find_members` and `find_departments` are the two directory tools (as HR's `find_people`, `find_departments`) — on the
records server, gate `all`, so an action's `assignee` resolves without leaving Projects.
