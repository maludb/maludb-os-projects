# Projects — an application from us for the Business OS

The second application beside the Business OS kernel: Agile project management in the manner of Jira
and Linear — projects with a ranked backlog of issues under epics, sprints with a goal, a kanban board
over the project's workflow, and the reports a Scrum team reads (velocity, burndown, cycle time, scope
change). Agents are assignees like anyone else. Installed by default with the kernel.

- People reach it from the launcher (`app.<domain>`) at `projects.<domain>`; there is no login form.
  An external collaborator is a kernel member with a grant, on a private project's team.
- Agents reach it through its two read MCP servers; writes go through the kernel's Actions MCP running
  Projects' action registry. Projects ships its expert and a Scrum Master agent (hired on install); it
  never calls a model.
- Stack: Ubuntu 24.04, Apache, PHP 8.3, HTMX, Bootstrap 5.3 (the nxl design system), PostgreSQL 17,
  MaluDB for activity memory; SortableJS for drag-and-drop on the board and the backlog, over buttons
  that post the same actions. Built with the `htmx-php-builder` plugin; fitted with `maludb-os-integration`.

## Layout
```
maludb-os.json               what the installation agent reads (registration.md)
db/                          numbered, additive migrations — run in order as postgres
docs/projects-design.md      Phase 0–1: purpose, actors, rules, memory model, questions, screens, decisions
docs/projects-mcp-tool-surface.md · docs/projects-action-manifest.md · docs/build-specs/
os/                          each shipped agent's job description (expert.md, scrum_master.md)
skills/                      the skills Projects ships: projects-basics (a skill), sprint-planning,
                             sprint-close and backlog-triage (runbooks, kind: runbook)
html/ app/ config/ storage/  the application (Phase 2 onward); attachments under storage/, never public
mcp/ bin/ deploy/            the read servers, the CLI jobs, the vhost and units
```

## Install (by hand, until the installer — `bin/app_install.php` on the kernel, build plan C4)
```
sudo -u postgres createdb subello_projects
for f in db/*.sql; do sudo -u postgres psql -v ON_ERROR_STOP=1 -d subello_projects -f "$f"; done
sudo -u postgres psql -c "ALTER ROLE projects_rw PASSWORD '…'"   # and projects_records_ro, projects_activity_ro
cp config/.env.example config/.env   # fill in; chmod 640, group www-data; ports 8181 / 8823 / 8824 unless taken (ss -ltn)
mkdir -p storage/attachments && sudo chown -R www-data:www-data storage
python3 -m venv mcp/venv && mcp/venv/bin/pip install asyncpg httpx "mcp>=1.2,<2" uvicorn pydantic
sudo cp deploy/apache-projects.conf /etc/apache2/sites-available/projects.conf && sudo a2ensite projects && sudo systemctl reload apache2
sudo cp deploy/projects-*.service deploy/projects-*.timer /etc/systemd/system/ && sudo systemctl daemon-reload
sudo systemctl enable --now projects-records-mcp projects-activity-mcp projects-directory-sync.timer projects-activity-ingest.timer
```
Then in the kernel, in the order `registration.md` lists (what the installer will do): the catalog entry
`projects` is seeded (kernel db/158); `application_save` with the url, `sso_path` `/sso`, `sso_logout_path`
`/sso/logout`, `scope_kind` none; the application token into `config/.env` (`mint_application_token`);
`bin/directory_sync.php --full`; the endpoints (Records MCP, Activity MCP, Web UI, Health) only after each
server's `initialize` answers; `application_roles_refresh`; `application_set_status` active;
`mcp/registries/projects.json` (from `deploy/kernel-registry-projects.json`) and a restart of
`certstudy-actions-mcp`; the four skills imported (`bin/import_skill.php`) and assigned at application scope
(the three runbooks record `kind: runbook`); the expert proposed and the Scrum Master hired
(`bin/hire_scrum_master.php` on the kernel). Every standing department is granted the Member role.
See `docs/projects-design.md` §10 for the build order and its gates.
