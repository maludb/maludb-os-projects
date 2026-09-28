"""The directory as Projects mirrors it — the two resolution tools (docs/projects-mcp-tool-surface.md).
Reads mcp_members and mcp_departments. Every query runs as the asking member.
"""
from __future__ import annotations

from pydantic import Field

import db
from business_common import RO, _Base


class FindMembersIn(_Base):
    q: str | None = Field(None, description="A name or email fragment, as the user said it")
    member_kind: str | None = Field(None, description="'human' or 'agent'; omit for both")
    department_id: int | None = Field(None, description="Only members of this department")
    include_inactive: bool = Field(False, description="Also members the kernel deactivated")
    limit: int = Field(25, ge=1, le=100)


class FindDepartmentsIn(_Base):
    q: str | None = Field(None, description="A name fragment")
    include_archived: bool = False
    limit: int = Field(50, ge=1, le=100)


def register(mcp, q) -> None:
    @mcp.tool(name="find_members", annotations={"title": "Find people and agents", **RO})
    async def find_members(params: FindMembersIn) -> str:
        """Look up anyone in the business by name or email — people and agents alike — with their
        Projects roles, primary department and open-issue count. CALL THIS FIRST whenever an action
        needs an assignee, a reporter or a lead — pass the name, never invent an id. Agents have
        member_kind 'agent' and can be assignees."""
        w, a = ["(m.status = 'active' OR $1)"], [params.include_inactive]
        if params.q:
            a.append(f"%{params.q[:100]}%"); w.append(f"(m.display_name ILIKE ${len(a)} OR m.email ILIKE ${len(a)})")
        if params.member_kind in ("human", "agent"):
            a.append(params.member_kind); w.append(f"m.member_kind = ${len(a)}")
        if params.department_id:
            a.append(params.department_id); w.append(f"EXISTS (SELECT 1 FROM mcp_department_members dm WHERE dm.member_id = m.member_id AND dm.department_id = ${len(a)} AND dm.left_at IS NULL)")
        a.append(params.limit)
        return await q(f"""SELECT m.member_id, m.display_name, m.email, m.member_kind, m.business_role, m.is_external, m.status,
                                  m.job_title, m.projects_capability, m.projects_roles, m.primary_department, m.primary_department_id, m.open_issues
                             FROM mcp_members m WHERE {' AND '.join(w)} ORDER BY m.member_kind = 'agent', m.display_name LIMIT ${len(a)}""", *a)

    @mcp.tool(name="find_departments", annotations={"title": "Find departments", **RO})
    async def find_departments(params: FindDepartmentsIn) -> str:
        """The departments: name, manager, parent, how many active projects each owns, and whether the
        caller administers it. Call before an action that needs a department; pass the name."""
        w, a = ["1 = 1"], []
        if not params.include_archived:
            w.append("archived_at IS NULL")
        if params.q:
            a.append(f"%{params.q[:100]}%"); w.append(f"name ILIKE ${len(a)}")
        a.append(params.limit)
        return await q(f"SELECT department_id, name, description, parent_id, parent_name, manager_member_id, manager_name, is_system, system_key, archived_at, projects, i_administer FROM mcp_departments WHERE {' AND '.join(w)} ORDER BY name LIMIT ${len(a)}", *a)
