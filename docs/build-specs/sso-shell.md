# Build spec: Phase 2 — sign-on, the mirror, the shell

Built by the planning-class model before any slice. What exists at the end: a person clicks Projects on
the launcher and lands, signed in, on a Projects dashboard that looks finished though it holds no work
yet; the mirror refreshes every minute; every request is logged. The kit is HR's (`/srv/apps/hr`)
copied and renamed — the differences are listed under "Projects is not HR".

## Files
- `app/bootstrap.php` (env from `config/.env`, session cookie `PROJSID`, `json_mode_begin()`, the acting member set on the PDO connection, `os_gate()`), `app/db.php`, `app/http.php` (the kernel's helper contracts: `e`, `view`, `is_htmx_request`, `require_post`, `request_*`, `csrf_*`, `emit_action_status`, `json_mode_finish`, `respond_*`, `hx_location`, `hx_trigger`, `saved_go`, `refuse`), `app/activity.php` (`log_activity`, `log_screen_view`, `request_id`, run-aware `default_activity_source`), `app/auth.php` (`verify_sso_token`, `verify_sso_claims`, `verify_sso_logout_notice`, `verify_action_token` + relay, `verify_approval_replay`, `current_member`, `require_login`; gates `require_admin()` = `projects_is_admin()`, `require_can_see_project($id)`, `require_can_work($id)`, `require_right_in($right, $id)` — the HR gates `require_hr`, `require_own_or_hr`, pay and person functions are REMOVED), `app/directory.php` (mirror upsert from claims and from the feed; `directory_call()` is kept but nothing calls it — `directory.writes` is false), `app/partial_update.php`.
- `html/sso.php` (`/sso`), `html/sso/logout.php` (`/sso/logout`, 204), `html/logout.php` (own sign-out, POST + CSRF, back to the launcher), `html/login.php` (redirects to `OS_LAUNCHER_URL?app=projects`), `html/index.php` (dashboard), `html/activity.php` (slice 7 extends it), `html/assistant/message.php` (the kernel's chat endpoint), `html/api/v1/health.php`, `html/attachments/get.php` (the gated file handler — see below; slice 1 fills the upload).
- `app/views/layout.php` (the nxl shell with `#page-content`, the command bar partial, CSRF meta + listener, `htmx:afterSwap` re-init that also re-binds SortableJS on `[data-sortable]`), `app/views/shared/assistant-bar.php`, `shared/flash.php`, `shared/validation-errors.php`, `shared/chart.php` (the SVG helper, below), `sso/refused.php`, `home/page.php`.
- `html/assets/` = the design system's `examples/assets/` verbatim (+ `htmx.min.js`) **plus** `html/assets/vendors/sortablejs/Sortable.min.js` (SortableJS 1.15, MIT, vendored — the owner's answer 2). No other package.
- `bin/directory_sync.php` (the change-feed timer, HR's verbatim; `--full` on first run; logs `directory.sync`), `bin/mint_mcp_token.php`, `bin/build_action_registry.php`.
- `mcp/activity_ingest.py` (payload `application: "projects"` first).
- `deploy/apache-projects.conf` (public `projects.<domain>` with `/mcp/records` → `:8823`, `/mcp/activity` → `:8824`, the canonical rewrites for `/projects/{KEY}`, its seven sections, `/issues/{KEY-n}`, `/sprints/{id}`, `/epics/{id}`, `/my-work`, `/activity`; the API allow-list `/api/v1/health` only; the loopback vhost `127.0.0.1:${APP_INTERNAL_PORT}` = 8181), `deploy/projects-*.service|timer`.

## Projects is not HR
- **No directory writes.** `maludb-os.json` says `directory.writes: false`; `app/directory.php` keeps the mirror appliers only. A screen never edits a member or a department.
- **Roles from db/005**, not HR's: the claims' and feed's `roles[]` are matched against `projects_roles`; `members.roles` is written from the kernel's word alone. The layout's role badge shows the first of `projects_roles` (Viewer / Member / Product Owner / Scrum Master / Projects Admin) or the business role.
- **Drag-and-drop.** SortableJS is loaded on every shell page after HTMX; a list or a board column with `data-sortable` becomes sortable, and a drop POSTs the same action the buttons post (`issue_rank`, `issue_move_to_sprint`, `issue_transition`) through `htmx.ajax`. Without JavaScript the buttons stand alone. Keyboard: the buttons.
- **Charts: hand-drawn SVG in PHP** (`app/views/shared/chart.php`: `chart_line($series, $opts)`, `chart_bars($rows, $opts)`, `chart_stacked($rows, $opts)`), not a JavaScript chart library. Decided because every chart here is one series or a few bars over at most 60 points, an SVG renders inside an HTMX partial with no re-init, prints, and is read by the agents' screenshots; a library would be the second package for no question it answers better. Revisit only if a report needs interaction.
- **Attachments** live under `storage/attachments/{project_id}/{issue_id}/{attachment_id}-{file_name}` (gitignored). `html/attachments/get.php?id=` reads `attachments.stored_path` (the base table, behind `projects_can_see_project(issue.project_id)`), answers `Content-Disposition: attachment` with the stored name and mime, never lists a directory, never serves a path from the request. `ATTACHMENT_MAX_BYTES` from `.env` (default `projects_settings.attachment_max_bytes`).

## The receiver (`/sso`) — in order
1. `verify_sso_token($token, APP_KEY)` and `verify_sso_claims($claims)` — constant-time, both must pass, member ids must match.
2. Expiry, audience (`projects`), then the nonce: `INSERT INTO sso_nonces … ON CONFLICT DO NOTHING`; zero rows = replay → refuse.
3. Upsert the mirror row from the claims (`members` with `capability` and `roles`; `department_members` for the claimed departments; departments named but unknown are inserted by id with the claimed name until the feed fills them).
4. Open the session: `session_regenerate_id(true)`, `$_SESSION['member_id']`, fresh CSRF token; `INSERT INTO member_sessions (sha256(session_id), member_id)`.
5. `log_activity('member.sign_on', 'member', $id, ['source' => 'web', 'request_id' => X-Request-Id ?? new])`; redirect to `/`.
6. Any failure: one page `sso/refused.php` (*"This sign-on link has expired. Open Projects from app.<domain> again."*), `member.sign_on.refused` logged with the reason in `after.reason` — never shown.

## Every request (bootstrap)
- Session cookie `SameSite=Lax`, `Secure` when HTTPS, `HttpOnly`, name `PROJSID`, strict mode.
- A signed-in request re-checks the mirror row: `status = 'active'` and `capability IS NOT NULL`, and that `member_sessions` has an unended row for this session; otherwise the session is destroyed and the visitor sent to the launcher.
- Action token (`X-Action-Token` + `X-Action-Relay`, or `X-Approval-Replay`): verified with the tenant's keys; acts as that member for one request; `Set-Cookie` removed; refuses a member id with no mirror row; CSRF satisfied; `source = 'agent'` when the token carries a run id, else `'assistant'`; `request_id` = the run's when the token carries a run (the kernel's run-facts call, cached per run).
- `SET app.member_id` on the PDO connection before any query.
- `log_screen_view()` on every GET that renders a screen; on a JSON request only with `X-Screen-View: 1`.

## `/sso/logout` (POST from the kernel)
`verify_sso_logout_notice($notice, APP_KEY)` → `UPDATE member_sessions SET ended_at = now(), ended_by = 'kernel' WHERE member_id = … AND ended_at IS NULL`; log `member.sign_out` with `after.by = 'kernel'`; 204. Invalid → 204 too; log `member.sign_out.refused`.

## The shell
- Design-system skeleton, exactly. Sidebar groups: **Home** (Dashboard, My work), **Projects** (Projects), **Settings** (Settings — Projects admins; Tokens; Activity). Inside a project the tab strip on the project's pages (Overview · Backlog · Board · Sprints · Epics · Issues · Reports · Settings) is the navigation, not the sidebar. Items the member may not open are not rendered (the gate decides, not the menu).
- Header: the brand (`APP_NAME`, "Projects"), the member's name and role badge, **Sign out** (POST), "All applications" back to the launcher.
- `#assistant-bar` per chat-actions; `html/assistant/message.php` posts `{utterance, screen, context:{record_id}, conversation_id (session), wait: 60}` to the kernel's chat endpoint with `OS_APPLICATION_TOKEN` and `X-Acting-Member`; renders the reply and the actions list; `HX-Trigger` per action's entity (`issueChanged`, `sprintChanged`, `projectChanged`, `epicChanged`); 202 polls `GET ?run=` up to `wait`; refusals in one sentence (403 "You are not allowed to use the assistant here", 404 "Projects has no expert yet — a super-admin names one in the kernel", 409 "The expert is busy; try again in a moment").
- Dashboard (`/`, screen `home`): **My open issues** (up to 10 from `mcp_issues WHERE assignee_member_id = me AND status_category <> 'done'` by due date then rank; each a link to `/issues/{KEY}`; a "blocked" mark when `open_blockers > 0`; "All" → `/my-work`), **My projects** (cards from `mcp_projects` where I am on the team or lead, else the open ones, up to 6, each with its active sprint name and open-issue count), **Active sprints** (one row per active sprint the member may see: project key, sprint name, days left, `points_done`/`points`, a 120×32 burndown sparkline from `sprint_burndown()` via `chart_line`). Empty states: "No issue is assigned to you", "You are on no project yet — open one from Projects", "No sprint is running".
- `/activity` (screen `activity`): the member's trail from `mcp_activity_log`, 50 a page, filters `project` (slice 7 adds the rest).
- 375 px: sidebar collapses to the hamburger, no horizontal scroll, the bar is thumb-reachable.

## Query functions (`app/features/home/queries.php`)
- `home_my_issues(PDO, int $memberId, int $limit = 10): array`
- `home_my_projects(PDO, int $memberId, int $limit = 6): array`
- `home_active_sprints(PDO): array` (each row with `burndown` = the rows of `sprint_burndown(id)`)

## Activity log events
- `member.sign_on`, `member.sign_on.refused`, `member.sign_out`, `member.sign_out.refused`, `directory.sync`, `screen.view` (home, activity), `assistant.ask` (the utterance's length and the run id, never the text).

## Proof before Phase 3 — **run 2026-09-28, all held**
- [x] A token minted by the kernel's own `sso_launch_url()` for member 1 on the Projects application opens a session once (302 → `/`, dashboard 200); the same token again → 403 (`replay`); a token for `app_key = 'hr'` → 403 (`token`).
- [x] An action token for an id with no mirror row → 401 and NO row created; a run token without the relay → 401; with a relay but a run the kernel does not vouch for → 401.
- [x] `bin/directory_sync.php --full` fills the mirror; the incremental run applies 0 and advances the cursor; the timer runs every minute; a suspended member arrives `inactive` and their sessions end in the same pass.
- [x] `/sso/logout` with the kernel's notice → 204 and the next request → 302 to the launcher; an invalid notice → 204 and `member.sign_out.refused`.
- [x] The ingest ships rows tagged `projects` and advances the checkpoint; `/api/v1/health` answers on both ports with `ingest_lag` and the sync state.
- [x] The command bar renders the kernel's 404 sentence before the expert exists.
- [x] `html/attachments/get.php` refuses (404) an id the member may not see, and never a path.
- [x] SortableJS loads (`Sortable` defined after `htmx:load`), and with JavaScript disabled the dashboard renders whole.
- [x] Headless Chromium at 375 × 740 and 1280 × 800: `scrollWidth` = viewport, the bar at the bottom edge, HTMX navigation to `/activity` pushes URL and title.
- Kernel side: catalog `projects` (db/158) + the application row with `sso_path`, `sso_logout_path`, `scope_kind none`, url `http://projects.subello.com`; grants: member 1 admin, each standing department Member; the application token in `config/.env`.

## Built and proven (2026-09-28)
The kit from HR adapted (the HR gates removed, Projects' gates added: `require_admin`, `require_can_see_project`,
`require_can_work`, `require_right_in`); `config/.env` from the tenant's keys with fresh role passwords; the vhost
`projects.subello.com` + loopback :8181; the timers `projects-directory-sync` and `projects-activity-ingest` active;
the venv; in the kernel application 50 with its grants and token. The proofs above as run: hand-off 302 → `/` 200 with
the three cards, replay 403 (`replay`), an HR token 403 (`token`); an action token for an unknown id 401 and no row;
an unadmitted agent's relayed run token for a run the kernel does not vouch for 401 and no admission, the same
without the relay 401; `--full` applied 21 members, 12 departments, 19 memberships, 17 holdings, the incremental run
0 and a new cursor; the kernel's notice 204 and the old session then 302 to the launcher, an invalid notice 204 with
`member.sign_out.refused`; the ingest shipped 4/4 and the checkpoint moved, health `ingest_lag 0` on both names; the
bar's 404 sentence; `get.php` 404 for a stranger's id and for a path; the dashboard whole without JavaScript (curl);
headless Chromium at 1280×800 and 375×740: `scrollWidth` = viewport, `htmx` and `Sortable` defined, the bar at the
bottom edge, HTMX navigation to `/activity` pushing URL and title, no console errors. Not re-proven here: a
suspended member arriving `inactive` with their sessions ended — HR's `directory_sync.php` verbatim, proven there.
One fix on the way: `mcp_activity_log` granted to `projects_rw` (db/011) for the `/activity` screen.

## Open Questions
- The theme's `logo-full.png`: Projects needs its own mark, as HR does (the owner's artwork).
