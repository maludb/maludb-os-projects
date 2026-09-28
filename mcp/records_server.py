"""projects_records_mcp — Projects' record-memory server (PostgreSQL, the mcp_* views, read-only).

Tool surface: docs/projects-mcp-tool-surface.md. Every tool reads the views as the asking member, so a
person sees what they see on the screens (projects_can_see_project decides every row); an agent sees
only the tools the kernel granted it (server_common).
"""
from __future__ import annotations

from mcp.server.fastmcp import FastMCP
from pydantic import Field

import db
from business_common import RO, _Base

mcp = FastMCP("projects_records_mcp")


async def _pool():
    return await db.get_pool(db.ENV["MCP_RECORDS_DB_USER"], db.ENV["MCP_RECORDS_DB_PASSWORD"])


async def _q(sql: str, *args) -> str:
    return db.to_json(await db.fetch_scoped(await _pool(), sql, *args))


class SearchIn(_Base):
    sql: str = Field(..., description="A single read-only SELECT over the mcp_* views (see below).")


@mcp.tool(name="records_search", annotations={"title": "Records search (SQL)", **RO})
async def records_search(params: SearchIn) -> str:
    """The long tail: one read-only SELECT over Projects' views — the directory (mcp_members: member_id,
    display_name, member_kind, projects_roles, primary_department, open_issues; mcp_departments;
    mcp_department_members), projects (mcp_projects: project_id, key, name, department_name, lead_name,
    visibility, status, board_mode, team_size, open_issues, active_sprint_id; mcp_project_team;
    mcp_workflow_statuses; mcp_board_columns; mcp_labels; mcp_components), work (mcp_issues: issue_id, key,
    project_key, type, summary, status, status_category, priority, story_points, assignee_name, sprint_name,
    epic_name, labels, open_blockers, due_date, started_at, resolved_at; mcp_issue_details; mcp_issue_links;
    mcp_issue_watchers; mcp_checklist_items; mcp_comments; mcp_attachments; mcp_worklogs), sprints and epics
    (mcp_sprints, mcp_sprint_scope_events, mcp_epics), mcp_saved_filters and mcp_projects_settings.
    Already scoped to what you may see. One statement, 5 s timeout, 200 rows."""
    return await db.run_search(await _pool(), params.sql)


@mcp.tool(name="app_roles", annotations={"title": "Projects' roles and rights", **RO})
async def app_roles() -> str:
    """The roles Projects offers and the rights each gives inside Projects — what the Business OS kernel
    reads to let a super-admin grant them (schema os.app-roles/1, maludb-os-integration 0.4.1). The
    catalogue is not about anyone, so the kernel's own token may read it; so may any person or agent."""
    pool = await _pool()
    async with pool.acquire() as con:
        rights = await con.fetch("SELECT right_key, description FROM projects_rights ORDER BY sort_order, right_key")
        roles = await con.fetch("SELECT role_key, name, description, capability, is_admin, rights FROM mcp_app_roles ORDER BY sort_order")
    return db.json.dumps({
        "schema": "os.app-roles/1",
        "rights": [{"key": r["right_key"], "description": r["description"]} for r in rights],
        "roles": [{"key": r["role_key"], "name": r["name"], "description": r["description"],
                   "capability": r["capability"], "is_admin": r["is_admin"], "rights": list(r["rights"])} for r in roles],
    })


import projects_projects
import projects_issues
import projects_sprints
import projects_reports
import projects_directory

projects_projects.register(mcp, _q)
projects_issues.register(mcp, _q)
projects_sprints.register(mcp, _q)
projects_reports.register(mcp, _q)
projects_directory.register(mcp, _q)


if __name__ == "__main__":
    import server_common
    server_common.run(mcp, "MCP_RECORDS_DB_USER", "MCP_RECORDS_DB_PASSWORD", port=int(db.ENV["MCP_RECORDS_PORT"]), endpoint_name="Records MCP")
