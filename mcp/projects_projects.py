"""Projects — read tools (docs/projects-mcp-tool-surface.md, "Projects").
Reads mcp_projects, mcp_project_team, mcp_workflow_statuses, mcp_board_columns, mcp_sprints, mcp_epics,
mcp_labels, mcp_components. Every query runs as the asking member.
"""
from __future__ import annotations

import json

from pydantic import Field

import db
from business_common import RO, _Base


class FindProjectsIn(_Base):
    q: str | None = Field(None, description="A key (PROJ) or words of the name, as the user said it")
    department_id: int | None = Field(None, description="Only projects this department owns (find_departments gives ids)")
    mine: bool = Field(False, description="Only projects the caller is on the team of")
    status: str | None = Field("active", description="'active' (default), 'archived' or 'all'")
    visibility: str | None = Field(None, description="'open' or 'private'; omit for both")
    limit: int = Field(25, ge=1, le=100)


class GetProjectIn(_Base):
    project_id: int | None = Field(None, description="The project's id")
    key: str | None = Field(None, description="Or its key, e.g. PROJ")


async def _pool_from(q):
    return await q.__globals__["_pool"]()


async def resolve_project_id(pool, project_id: int | None, key: str | None) -> int | None:
    """A project by id or key, as the caller may see it (None when neither is theirs)."""
    if project_id:
        rows = await db.fetch_scoped(pool, "SELECT project_id FROM mcp_projects WHERE project_id = $1", project_id)
    elif key:
        rows = await db.fetch_scoped(pool, "SELECT project_id FROM mcp_projects WHERE key = $1", key.strip().upper())
    else:
        return None
    return int(rows[0]["project_id"]) if rows else None


def register(mcp, q) -> None:
    @mcp.tool(name="find_projects", annotations={"title": "Find projects", **RO})
    async def find_projects(params: FindProjectsIn) -> str:
        """Listing or looking up projects by key, name or department; the caller's own (on the team);
        active or archived. CALL THIS FIRST whenever an action needs a project — pass the key or the
        name, never invent an id. A private project the caller is not on is simply absent."""
        w, a = ["1 = 1"], []
        if params.q:
            a.append(f"%{params.q[:100]}%"); w.append(f"(p.key ILIKE ${len(a)} OR p.name ILIKE ${len(a)})")
        if params.department_id:
            a.append(params.department_id); w.append(f"p.department_id = ${len(a)}")
        if params.mine:
            mid = db.request_member_id.get()
            a.append(mid); w.append(f"EXISTS (SELECT 1 FROM mcp_project_team t WHERE t.project_id = p.project_id AND t.member_id = ${len(a)} AND t.removed_at IS NULL)")
        if params.status == "archived":
            w.append("p.status = 'archived'")
        elif params.status != "all":
            w.append("p.status = 'active'")
        if params.visibility in ("open", "private"):
            a.append(params.visibility); w.append(f"p.visibility = ${len(a)}")
        a.append(params.limit)
        return await q(f"""SELECT p.project_id, p.key, p.name, p.description, p.department_id, p.department_name, p.lead_member_id, p.lead_name,
                                  p.visibility, p.status, p.board_mode, p.customer, p.team_size, p.open_issues, p.active_sprint_id, p.active_sprint_name,
                                  p.open_epics, p.i_can_work, p.i_administer, p.updated_at
                             FROM mcp_projects p WHERE {' AND '.join(w)} ORDER BY p.name LIMIT ${len(a)}""", *a)

    @mcp.tool(name="get_project", annotations={"title": "One project, whole", **RO})
    async def get_project(params: GetProjectIn) -> str:
        """Everything about one project: the row, the team with project roles, the workflow's statuses
        and allowed moves, the board's columns and WIP limits, the active sprint (or the next future one),
        the epics with progress, the labels and components. Call for "tell me about PROJ", and before an
        action that needs a status, label or component id."""
        pool = await _pool_from(q)
        pid = await resolve_project_id(pool, params.project_id, params.key)
        if pid is None:
            return json.dumps({"error": "No such project that you can see; find_projects looks them up by key or name."})
        out = {}
        out["project"] = (await db.fetch_scoped(pool, "SELECT * FROM mcp_projects WHERE project_id = $1", pid))[0]
        out["team"] = await db.fetch_scoped(pool, "SELECT member_id, display_name, member_kind, is_external, project_role, added_at FROM mcp_project_team WHERE project_id = $1 AND removed_at IS NULL ORDER BY display_name", pid)
        out["statuses"] = await db.fetch_scoped(pool, "SELECT status_id, name, category, sort_order, is_initial, is_review, can_move_to FROM mcp_workflow_statuses WHERE project_id = $1 AND archived_at IS NULL ORDER BY sort_order", pid)
        out["columns"] = await db.fetch_scoped(pool, "SELECT column_id, name, sort_order, wip_limit, status_ids FROM mcp_board_columns WHERE project_id = $1 ORDER BY sort_order", pid)
        sprints = await db.fetch_scoped(pool, "SELECT sprint_id, name, goal, state, start_date, end_date, committed_points, points, points_done, issues, issues_done FROM mcp_sprints WHERE project_id = $1 AND state IN ('active', 'future') ORDER BY state = 'active' DESC, sort_order, sprint_id LIMIT 1", pid)
        out["current_sprint"] = sprints[0] if sprints else None
        out["epics"] = await db.fetch_scoped(pool, "SELECT epic_id, name, colour, status, target_date, issues, issues_done, points, points_done FROM mcp_epics WHERE project_id = $1 ORDER BY status = 'open' DESC, sort_order, name", pid)
        out["labels"] = await db.fetch_scoped(pool, "SELECT label_id, name, colour, issues FROM mcp_labels WHERE project_id = $1 AND archived_at IS NULL ORDER BY name", pid)
        out["components"] = await db.fetch_scoped(pool, "SELECT component_id, name, description, default_assignee_id, default_assignee_name FROM mcp_components WHERE project_id = $1 AND archived_at IS NULL ORDER BY name", pid)
        return db.to_json(out)
