# Build spec: My work, saved filters, activity (slice 7)

Replicates the exemplar. Schema: `mcp_issues`, `mcp_issue_watchers`, `mcp_saved_filters`, `mcp_activity_log` (db/011),
`saved_filters` (db/009).

## Screens

| Screen id | Canonical URL | Purpose |
|---|---|---|
| `my-work` | `/my-work?tab=assigned|watching|review|reported` | Everything on my plate across projects — the kernel's My Work aggregation asks for the same shape |
| `filters-list` | `/filters` | The member's saved filters, and the shared ones they may see |
| `filter-edit` | `/filters/{id}/edit` | Rename, share, change the filter's project |
| `activity` | `/activity?project=KEY&member=&action=&since=` | The trail: what happened, by project, by person, by kind |

## My work
- Tabs (URLs): **Assigned to me** (default; open issues where I am the assignee, by `state` then due date then rank), **Watching** (open issues I watch, not assigned to me), **In review for me** (issues in a status of category `in_progress` named "In review" or any status the project's board maps to a column named "Review", where I am on the team or watching — the reviewer's queue, question 15), **Reported by me** (open, I am the reporter).
- Each row: the **state** (blocked when `open_blockers > 0`; overdue when `due_date < today`; due today; due; else none), key (→ view), type, summary, project key, status, sprint, points, due; the row's tone by state. A count per tab in the strip.
- The `my_work` shape (the records tool and the kernel's future aggregation): `kind = 'issue'`, `id`, `title = key + summary`, `detail = project · status · sprint`, `due_at`, `state ∈ blocked|overdue|due today|due|open`, `href = /issues/{key}`, `urgency 2 blocked or overdue | 1 due today | 0`. The screen and the tool share `find_my_work()`.
- `data-screen="my-work"`; `screen.view` carries `after.tab`.

## Saved filters
- List: name (→ the issues list with the filter applied: `/projects/{KEY}/issues?filter=id` or `/my-work?filter=id` when cross-project), project, shared badge, owner, updated; **Edit**, **Delete** ✕ (own). Shared filters of projects I may see appear under "Shared with the team".
- The issues list (slice 1) reads `?filter=id`, applies `mcp_saved_filters.filter` (validated keys only — the list's own filter names) and shows "Filter: name" with a clear link; **Save this filter** on the list posts `filter_save` with the current query.
- Form: name (1–80, unique per owner and project), shared checkbox, project select (own projects or "across projects").
- Ids: `filter-form-field-name`, `filter-form-field-shared`, `filter-form-field-project`.

## Activity
- The member's trail from `mcp_activity_log` (what they may see: their own rows, their projects' rows, everything for admins), 50 a page, newest first. Filters (URL): `project` (key), `member` (actor id), `action` (prefix: `issue.`, `sprint.`, `comment.`…), `since` (days: 1/7/30/90), `entity` (`issue:KEY-1` — the record's trail, as the issue view's History links here).
- A row: when, who (avatar; agent badge), the sentence (built from action + before/after by `activity_sentence()`: "moved KEY-12 to In review", "estimated KEY-3 at 5 points (was 3)", "started Sprint 4 with 34 points"), the record link, source badge (web / agent / assistant).
- **Export**: `?format=csv` of the current page's filter, behind the same view; logged `activity.export`.

## Files (exactly these)
- `html/my-work.php` — `html/filters/index.php` · `form.php` · `save.php` (slice 1's, extended with the form's fields) · `delete.php` — `html/activity.php` (replaces Phase 2's stub)
- `app/features/mywork/queries.php` · `present.php` — `app/features/filters/queries.php` — `app/features/activity/queries.php` · `sentence.php`
- `app/views/mywork/page.php` · `partials/rows.php` — `app/views/filters/page.php` · `form.php` — `app/views/activity/page.php` · `partials/rows.php`

## Query functions (signatures fixed)
- `find_my_work(PDO, int $memberId, string $tab = 'assigned', int $limit = 100): array` (the shape above; the tool calls it with `assigned`)
- `find_saved_filters(PDO, int $memberId, ?int $projectId): array` · `find_saved_filter(PDO, int $id): ?array` · `save_saved_filter(PDO, ?int $id, int $ownerId, ?int $projectId, string $name, array $filter, bool $shared): array` · `delete_saved_filter(PDO, int $id, int $ownerId): bool`
- `find_activity(PDO, array $filters, int $page = 1, int $per = 50): array` · `activity_sentence(array $row): string`

## Handlers
- `filters/save.php` (`filter_save`): own; a filter's keys validated against the issues list's allowlist; log `saved_filter.save` (`after`: name, shared, project_id, keys); `filters/delete.php`: own; log `saved_filter.delete`.
- `activity.php` GET only; the CSV export logs `activity.export` (`after`: filters).

## Action manifest entries
- Screens: `my-work` (prefill `tab`), `filters-list`, `filter-edit`, `activity` (prefill `project`, `member`, `action`, `since`, `entity`). Actions: `filter_save`, `filter_delete` (add to the manifest: own, undo —).

## Activity log events
- `screen.view` (`after.tab` on my-work), `saved_filter.save`, `saved_filter.delete`, `activity.export`.

## Proof
- My work: a blocked issue sorts first with urgency 2, an overdue one too, due-today 1, the rest 0; an issue assigned to an agent does not appear on a person's tab; the tool `my_work` for member N returns the same rows as the screen's Assigned tab; Watching excludes assigned; In review finds the "In review" status and a column named Review.
- Filters: a saved filter re-applies exactly (the URL it was saved from); an unknown key in the JSON is ignored; a shared filter of a private project is invisible off the team.
- Activity: a Viewer sees their own rows and their open projects' rows, never a private project's; the sentence for each event kind reads right (a fixture per kind); the CSV has the page's rows and the header.
- 375 px: tabs wrap, rows keep the state mark and the key visible without horizontal scroll.

## Built and proven (2026-09-28)
By a fork on the exemplar: My work (`html/my-work.php`, `app/features/mywork/{queries,present}.php` — `my_work_tab_sql()` is
one SQL condition per tab over `mcp_issues`, the shape the Python `my_work` tool copies; four tabs as URLs with counts,
Pattern B on `#my-work-results`, JSON `{tab, counts, items[], total}`); saved filters (`html/filters/{index,form,save,delete}.php`,
`app/features/filters/queries.php`; save keeps the issues-list path and adds the edit path — name, project and sharing change,
keys stay; a duplicate name 422 with `fields.name`); the trail (`html/activity.php` rewritten over
`app/features/activity/{queries,sentence}.php`: project, member, action prefix, since 1/7/30/90, `entity=issue:KEY` or
`kind:id`; `activity_sentence()` covers every action the application logs, issue rows through the exemplar's
`issue_history_sentence()`; the CSV download logged `activity.export`). Proven live as member 1 through a hand-off: three
SMOKE issues landing on the right tabs (assigned and overdue, watching, in review) with the strip's counts matching; a filter
saved from the issues list (HX-Location), edited, applied by the list's JSON, its duplicate refused, deleted, a bogus id 404;
the trail by project, prefix, period and entity with record links, the CSV with its Content-Disposition, Pattern B partials
with `Vary: HX-Request`; headless Chromium at 1280×800 and 375×740 on five screens with no sideways scroll and no console
errors (the sentence column wraps). Open: a project-less saved filter has no single place to apply, so its card says to pick
it from any project's issues list; "In review for me" keys on the `is_review` flag or a column named Review — epics have no
owner column, so that clause is not in the query. SMOKE data left: SMK1-5, SMK1-6, SMK1-7 and a watcher row on SMK1-6.

## Open Questions
- (none)
