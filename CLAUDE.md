# Projects — CLAUDE.md

This is **Projects**, the second application from us beside the Business OS kernel (kernel repo `/var/www`,
its design `docs/business-os-integration.md` there; the kernel's plan for this application
`docs/build-specs/projects-application-plan.md`, build plan A9). Read, in order: `docs/projects-design.md`
(what and why, the actors, the gates, the memory model, the question inventory, the decisions and the owner's
answers), `docs/projects-mcp-tool-surface.md`, `docs/projects-action-manifest.md`, the slice specs in
`docs/build-specs/` (Phase 2 first: `sso-shell.md`; the exemplar: `projects-and-issues.md`).

## Two plugins govern this repo
- `htmx-php-builder` — **how it is built**: `new-app` (the fixed phase order), `php-patterns` before any PHP,
  `design-system` before any markup, `php-session-auth` for session hardening and CSRF, `chat-actions`
  for the command bar, `mcp-servers` for the two read servers, `new-screen` for every screen.
- `maludb-os-integration` 0.4.1 (skill `os-integration`, `~/maludb-os-integration`) — **how it fits**: the
  mirror with the kernel's ids, the `/sso` receivers, the run token honoured everywhere, the command bar
  through the kernel's chat endpoint, roles and rights published by `app_roles`, `maludb-os.json`, runbooks
  as skills with `kind: runbook`. Where the two conflict, the integration contract wins: **no password, no
  login form, no account, no model key, no shared tables** — the builder's Google/TOTP/register
  scaffolding is not built here. Projects never writes the directory (`directory.writes: false`).

## Rules that do not move
- **Checkpoint gate**: no feature PHP until the owner approves the schema + tool surface + manifest + slice
  specs together (Phase 1). Schema changes after that are numbered additive migrations, each with sign-off.
- Every state-changing handler: `require_post()` + `verify_csrf()` (an action token stands in) + the
  gate (`app_has_right(right, project)`) + `log_activity()` + `emit_action_status()`; a create's `location`
  ends in the record id (an issue's: its key).
- The kernel's rules copied here: activity `source` is `web` for the UI, `agent` under a run token;
  `action` is `entity.verb`; `mcp_*` views are the only thing the read roles see; `app.member_id` is set
  before any query; an unknown member id is refused, never created. Issue history is the activity log —
  there is no second history table; log payloads carry what the activity questions need (assign old/new,
  transition from/to with the sprint, estimate old/new, rank old/new, sprint start's commitment, close's
  carry-over).
- **The owner's answers (2026-09-28) are rules**: JavaScript packages are allowed where the board earns
  them — SortableJS vendored under `html/assets/vendors/`, a chart library for the reports — always over
  buttons that post the same actions; worklogs are in (a line on an issue; no timesheets); issue types are
  fixed (story, task, bug, subtask); the default application stays each person's or their admin's choice;
  the Scrum Master agent is hired on install (`maludb-os.json` agents[1], `hired_on_install: true`).
- Unscoped (`scope_kind` none) with open and private projects; `projects.scope_id` reserved NULL. A
  private project is visible to its team only, whatever the role.
- Lists of named things are cards (projects, epics); ledgers (issues, sprints, worklogs) are tables; the
  board and the backlog are their own screens, the board scrolling horizontally inside a panel with a
  visible scrollbar. No modals — except the record picker (`app/picker.php`, the one modal in the shell, chooses a member or a project and nothing else; beside `hx-confirm`); every screen works at 375 px; ids per the kebab-case scheme;
  `hx-push-url` always explicit; the kernel's click-around rules (every name a link, every page a way back).
- Attachments live under `storage/attachments/<project>/<issue>/`, size-capped by `ATTACHMENT_MAX_BYTES`,
  served by a PHP handler through the gate — never from a public path.
- Commit after every finished step on `main`, in the kernel repo's message style; never push.
- Smokes and fixtures are named `SMOKE <run>`; test tokens are signed with the tenant's key from the
  kernel's `config/.env` and never written down.

## Environment facts
- Repo and install path `/srv/apps/projects` (`/srv/apps/<catalog_key>` for every application from us).
  Apache: `deploy/apache-projects.conf` = `/etc/apache2/sites-available/projects.conf` (public
  `projects.subello.com`, loopback :8181); timers `projects-directory-sync` and `projects-activity-ingest`
  from `deploy/`. In the kernel the catalog entry `projects` (kind `ours`) is seeded by db/158; the
  application row, its token, endpoints, registry and agents come with registration (Phase 4).
- **State (2026-09-28, evening): BUILT and proven end to end.** Phases 0–4 complete: the schema (db/001–014), Phase 2 (sign-on,
  mirror, shell), Phase 3's seven slices (each spec's "Built and proven" section is the record; the exemplar is
  `docs/build-specs/projects-issues.md`), the two read MCP servers (8823/8824) and the registration in the kernel — application 50,
  endpoints 20–23, roles from `app_roles`, skills 22–25 at application scope, the registry on the kernel's Actions MCP (48 of 48
  actions), the **Projects Expert (agent 59)** and the **Scrum Master (agent 58)** hired by the kernel's
  `bin/hire_application_agent.php`; the command bar answers through the kernel's chat endpoint; an agent's deletion pauses for
  approval. `deploy/` files are templates (`{{DOMAIN}}`, `{{APP_INTERNAL_PORT}}`, …) for the kernel's installer
  `bin/app_install.php`. The record of what was built and why: `docs/projects-design.md` §13. Owed to the owner: run the kernel's
  `sudo php bin/app_install.php apply /srv/apps/projects --grant-standing-departments` (writes the six agent approval policies
  for the `other`-category actions), DNS and proxy for `projects.subello.com`, decide approval request 28; SMOKE data (projects
  SMK1, SMK4, SMK5, SMK6, SMKA and their issues, sprints, epic, labels) is yours to delete.
- Database `subello_projects` on the office's PostgreSQL 17 (roles `projects_rw`, `projects_records_ro`,
  `projects_activity_ro`).
- Ports are configuration: `APP_INTERNAL_PORT` 8181, `MCP_RECORDS_PORT` 8823, `MCP_ACTIVITY_PORT` 8824 in
  `config/.env` (the kernel owns 8080 and 8811–8816; HR holds 8180, 8821, 8822). The kernel's internal
  API: `OS_INTERNAL_URL=http://127.0.0.1:8080`.
- The kernel's own copies to reuse verbatim: `app/auth.php` verifiers, `app/http.php` helper contracts,
  `app/activity.php`, `app/partial_update.php`, `mcp/db.py`, `mcp/server_common.py`, `mcp/activity_ingest.py`,
  `bin/build_action_registry.php`, `bin/mint_mcp_token.php`; HR's `app/directory.php`, `bin/directory_sync.php`,
  `html/sso.php`, `html/sso/logout.php`, `db/001`–`003`.
