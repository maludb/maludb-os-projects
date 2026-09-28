"""The reports — read tools over the db/010 functions (docs/projects-mcp-tool-surface.md).
Through the scoped wrappers mcp_sprint_burndown(), mcp_project_velocity(), mcp_project_cycle_time(), mcp_project_flow() (db/013).
"""
from __future__ import annotations

import json

from pydantic import Field

import db
from business_common import RO, _Base
from projects_projects import _pool_from, resolve_project_id


class BurndownIn(_Base):
    sprint_id: int | None = Field(None, description="One sprint; omit for the active sprint of project_id / project_key")
    project_id: int | None = None
    project_key: str | None = None


class VelocityIn(_Base):
    project_id: int | None = None
    project_key: str | None = None
    sprints: int = Field(6, ge=1, le=24, description="How many closed sprints back")


class CycleTimeIn(_Base):
    project_id: int | None = None
    project_key: str | None = None
    since: str | None = Field(None, description="ISO date; default 90 days ago")


class FlowIn(_Base):
    project_id: int | None = None
    project_key: str | None = None
    since: str | None = Field(None, description="ISO date; default 30 days ago")


async def _pid(pool, params):
    pid = await resolve_project_id(pool, params.project_id, params.project_key)
    return pid


def register(mcp, q) -> None:
    @mcp.tool(name="burndown", annotations={"title": "The sprint's burndown", **RO})
    async def burndown(params: BurndownIn) -> str:
        """Points remaining day by day through a sprint, against its scope (which moves when issues are
        added, removed or re-estimated). Call for "are we on track", "how much is left in the sprint"."""
        pool = await _pool_from(q)
        sid = params.sprint_id
        if sid is None:
            pid = await _pid(pool, params)
            if pid is None:
                return json.dumps({"error": "Say which sprint (sprint_id) or which project."})
            rows = await db.fetch_scoped(pool, "SELECT active_sprint_id FROM mcp_projects WHERE project_id = $1", pid)
            sid = rows[0]["active_sprint_id"] if rows else None
            if sid is None:
                return json.dumps({"error": "No sprint is running in that project."})
        sprint = await db.fetch_scoped(pool, "SELECT sprint_id, name, state, start_date, end_date, committed_points, points, points_done FROM mcp_sprints WHERE sprint_id = $1", sid)
        if not sprint:
            return json.dumps({"error": "No such sprint that you can see."})
        days = await db.fetch_scoped(pool, "SELECT day, scope, done, remaining FROM mcp_sprint_burndown($1)", sid)
        return db.to_json({"sprint": sprint[0], "days": days})

    @mcp.tool(name="velocity", annotations={"title": "Velocity", **RO})
    async def velocity(params: VelocityIn) -> str:
        """What the last sprints committed, finished and carried, and what to commit to next (the average
        of the finished). Call for "what is our velocity", "how much can we take on"."""
        pool = await _pool_from(q)
        pid = await _pid(pool, params)
        if pid is None:
            return json.dumps({"error": "No such project that you can see; find_projects looks them up by key or name."})
        rows = await db.fetch_scoped(pool, "SELECT sprint_id, name, start_date, end_date, committed, completed, carried FROM mcp_project_velocity($1, $2)", pid, params.sprints)
        done = [float(r["completed"]) for r in rows if r["completed"] is not None]
        return db.to_json({"sprints": rows, "average_completed": round(sum(done) / len(done), 1) if done else None, "suggest_commit": round(sum(done) / len(done)) if done else None})

    @mcp.tool(name="cycle_time", annotations={"title": "Cycle time by type", **RO})
    async def cycle_time(params: CycleTimeIn) -> str:
        """From started to done, by issue type: median, 85th percentile and mean days, over issues
        resolved since a date. Call for "how long does a bug take", "is our cycle time getting longer"."""
        pool = await _pool_from(q)
        pid = await _pid(pool, params)
        if pid is None:
            return json.dumps({"error": "No such project that you can see; find_projects looks them up by key or name."})
        import datetime
        since = datetime.date.fromisoformat(params.since[:10]) if params.since else datetime.date.today() - datetime.timedelta(days=90)
        return db.to_json(await db.fetch_scoped(pool, "SELECT type, resolved, median_days, p85_days, mean_days FROM mcp_project_cycle_time($1, $2)", pid, since))

    @mcp.tool(name="flow", annotations={"title": "Cumulative flow", **RO})
    async def flow(params: FlowIn) -> str:
        """Issues in to-do, in progress and done per day over a window — the cumulative-flow chart's
        numbers. Call for "is work piling up in progress", "how has the flow looked this month"."""
        pool = await _pool_from(q)
        pid = await _pid(pool, params)
        if pid is None:
            return json.dumps({"error": "No such project that you can see; find_projects looks them up by key or name."})
        import datetime
        since = datetime.date.fromisoformat(params.since[:10]) if params.since else datetime.date.today() - datetime.timedelta(days=30)
        return db.to_json(await db.fetch_scoped(pool, "SELECT day, todo, in_progress, done FROM mcp_project_flow($1, $2)", pid, since))
