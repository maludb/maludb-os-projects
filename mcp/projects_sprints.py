"""Sprints, epics, worklogs and the settings — read tools (docs/projects-mcp-tool-surface.md).
Reads mcp_sprints, mcp_issues, mcp_sprint_scope_events, mcp_epics, mcp_worklogs, mcp_projects_settings.
"""
from __future__ import annotations

import json

from pydantic import Field

import db
from business_common import RO, _Base
from projects_projects import _pool_from, resolve_project_id


class SprintStatusIn(_Base):
    q: str | None = Field(None, description="Words of a sprint's name — to LOOK ONE UP (answers the matching sprints with sprint_id and name)")
    sprint_id: int | None = Field(None, description="One sprint; omit for the project's active sprint")
    project_id: int | None = None
    project_key: str | None = None
    state: str | None = Field(None, description="'future', 'active' or 'closed' — to LIST the project's sprints in that state")
    limit: int = Field(25, ge=1, le=100)


class ScopeChangesIn(_Base):
    sprint_id: int
    event: str | None = Field(None, description="added, removed, done, reopened or estimated; omit for all")
    limit: int = Field(50, ge=1, le=100)


class EpicProgressIn(_Base):
    q: str | None = Field(None, description="Words of an epic's name — to LOOK ONE UP")
    project_id: int | None = None
    project_key: str | None = None
    epic_id: int | None = None
    status: str | None = Field("open", description="open, done, cancelled or 'all'")


class WorklogsIn(_Base):
    project_id: int | None = None
    issue_id: int | None = None
    member_id: int | None = None
    sprint_id: int | None = None
    date_from: str | None = Field(None, description="ISO date")
    date_to: str | None = Field(None, description="ISO date")
    limit: int = Field(50, ge=1, le=100)


class NoArgs(_Base):
    pass


def register(mcp, q) -> None:
    @mcp.tool(name="sprint_status", annotations={"title": "How a sprint stands", **RO})
    async def sprint_status(params: SprintStatusIn) -> str:
        """How a sprint stands: goal, dates, state, committed and completed points, issues by status
        category, days left. CALL THIS FIRST before an action that needs a sprint — with state 'future'
        or 'closed' it lists the project's sprints by name. Call for "how is the sprint going", "what did
        sprint 3 commit to"."""
        pool = await _pool_from(q)
        if params.state or params.q:
            pid = await resolve_project_id(pool, params.project_id, params.project_key) if (params.project_id or params.project_key) else None
            w, a = ["1 = 1"], []
            if params.state in ("future", "active", "closed"):
                a.append(params.state); w.append(f"s.state = ${len(a)}")
            if params.q:
                a.append(f"%{params.q[:100]}%"); w.append(f"s.name ILIKE ${len(a)}")
            if pid:
                a.append(pid); w.append(f"s.project_id = ${len(a)}")
            a.append(params.limit)
            return db.to_json(await db.fetch_scoped(pool, f"SELECT sprint_id, project_id, project_key, name, goal, state, start_date, end_date, committed_points, completed_points, carried_points, issues, issues_done, points, points_done FROM mcp_sprints s WHERE {' AND '.join(w)} ORDER BY s.state = 'active' DESC, s.sort_order, s.sprint_id LIMIT ${len(a)}", *a))
        sid = params.sprint_id
        if sid is None:
            pid = await resolve_project_id(pool, params.project_id, params.project_key)
            if pid is None:
                return json.dumps({"error": "Say which sprint (sprint_id) or which project (project_id or project_key)."})
            rows = await db.fetch_scoped(pool, "SELECT active_sprint_id FROM mcp_projects WHERE project_id = $1", pid)
            sid = rows[0]["active_sprint_id"] if rows else None
            if sid is None:
                return json.dumps({"error": "No sprint is running in that project — plan one from the backlog."})
        rows = await db.fetch_scoped(pool, "SELECT * FROM mcp_sprints WHERE sprint_id = $1", sid)
        if not rows:
            return json.dumps({"error": "No such sprint that you can see."})
        s = rows[0]
        import datetime
        s["days_left"] = (s["end_date"] - datetime.date.today()).days if s["end_date"] and s["state"] == "active" else None
        s["by_category"] = await db.fetch_scoped(pool, "SELECT status_category, count(*) AS issues, coalesce(sum(story_points), 0) AS points FROM mcp_issues WHERE sprint_id = $1 AND parent_issue_id IS NULL GROUP BY 1 ORDER BY 1", sid)
        s["issues_list"] = await db.fetch_scoped(pool, "SELECT issue_id, key, type, summary, status, status_category, priority, story_points, assignee_name, open_blockers FROM mcp_issues WHERE sprint_id = $1 AND parent_issue_id IS NULL ORDER BY rank", sid)
        return db.to_json(s)

    @mcp.tool(name="sprint_scope_changes", annotations={"title": "What changed in a sprint's scope", **RO})
    async def sprint_scope_changes(params: ScopeChangesIn) -> str:
        """What entered and left a sprint after it started, what was re-estimated, when and by whom —
        the scope-change report. Call for "did the sprint grow", "what was pulled in mid-sprint"."""
        w, a = ["sprint_id = $1"], [params.sprint_id]
        if params.event in ("added", "removed", "done", "reopened", "estimated"):
            a.append(params.event); w.append(f"event = ${len(a)}")
        a.append(params.limit)
        return await q(f"SELECT scope_event_id, event, issue_id, issue_key, issue_summary, points, by_member_id, by_name, occurred_at FROM mcp_sprint_scope_events WHERE {' AND '.join(w)} ORDER BY occurred_at LIMIT ${len(a)}", *a)

    @mcp.tool(name="epic_progress", annotations={"title": "How far along each epic is", **RO})
    async def epic_progress(params: EpicProgressIn) -> str:
        """Each epic's issues and points done, its target date and status. CALL THIS before an action
        that needs an epic (pass the project). Call for "how is the onboarding epic going", "what epics are open"."""
        pool = await _pool_from(q)
        w, a = ["1 = 1"], []
        pid = await resolve_project_id(pool, params.project_id, params.project_key) if (params.project_id or params.project_key) else None
        if pid:
            a.append(pid); w.append(f"e.project_id = ${len(a)}")
        if params.epic_id:
            a.append(params.epic_id); w.append(f"e.epic_id = ${len(a)}")
        if params.q:
            a.append(f"%{params.q[:100]}%"); w.append(f"e.name ILIKE ${len(a)}")
        if params.status and params.status != "all" and not params.q:
            a.append(params.status); w.append(f"e.status = ${len(a)}")
        return db.to_json(await db.fetch_scoped(pool, f"SELECT epic_id, project_id, project_key, name, summary, colour, status, target_date, issues, issues_done, points, points_done, CASE WHEN points > 0 THEN round(100 * points_done / points) ELSE NULL END AS percent FROM mcp_epics e WHERE {' AND '.join(w)} ORDER BY e.status = 'open' DESC, e.sort_order, e.name", *a))

    @mcp.tool(name="worklogs", annotations={"title": "Hours logged", **RO})
    async def worklogs(params: WorklogsIn) -> str:
        """Hours logged on issues: per issue, per person, over a sprint or a window. Call for "how many
        hours went into PROJ-12", "what did Jack log this week"."""
        w, a = ["1 = 1"], []
        if params.project_id:
            a.append(params.project_id); w.append(f"w.project_id = ${len(a)}")
        if params.issue_id:
            a.append(params.issue_id); w.append(f"w.issue_id = ${len(a)}")
        if params.member_id:
            a.append(params.member_id); w.append(f"w.member_id = ${len(a)}")
        if params.sprint_id:
            a.append(params.sprint_id); w.append(f"w.issue_id IN (SELECT issue_id FROM mcp_issues WHERE sprint_id = ${len(a)})")
        if params.date_from:
            a.append(params.date_from[:10]); w.append(f"w.worked_on >= ${len(a)}::date")
        if params.date_to:
            a.append(params.date_to[:10]); w.append(f"w.worked_on <= ${len(a)}::date")
        a.append(params.limit)
        return await q(f"SELECT worklog_id, issue_id, issue_key, project_id, member_id, display_name, hours, worked_on, note FROM mcp_worklogs w WHERE {' AND '.join(w)} ORDER BY worked_on DESC, worklog_id DESC LIMIT ${len(a)}", *a)

    @mcp.tool(name="projects_settings", annotations={"title": "Projects' settings", **RO})
    async def projects_settings(params: NoArgs) -> str:
        """The defaults every project starts from: timezone, sprint length, the story-point scale, the
        stale threshold, the attachment limit, the default visibility."""
        return await q("SELECT timezone, sprint_length_days, point_scale, stale_after_days, attachment_max_bytes, default_visibility FROM mcp_projects_settings")
