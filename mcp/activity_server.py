"""projects_activity_mcp — Projects' activity-memory server (activity_log through mcp_activity_log, read-only).

Tool surface: docs/projects-mcp-tool-surface.md, "Activity server". Every query runs as the asking member;
the view decides which rows they see (an admin everything; others the rows of projects they may see and
their own). The activity role reads mcp_activity_log, mcp_members, mcp_departments, mcp_projects, mcp_issues
and mcp_sprints only — sprint scope events come from the records server.
"""
from __future__ import annotations

import json

from mcp.server.fastmcp import FastMCP
from pydantic import Field

import db
from business_common import RO, _Base, _period_bounds

mcp = FastMCP("projects_activity_mcp")


async def _pool():
    return await db.get_pool(db.ENV["MCP_ACTIVITY_DB_USER"], db.ENV["MCP_ACTIVITY_DB_PASSWORD"])


async def _q(sql: str, *args) -> str:
    return db.to_json(await db.fetch_scoped(await _pool(), sql, *args))


_COLS = "activity_id, occurred_at, actor_member_id, actor_name, actor_kind, source, action, screen, entity_type, entity_id, before, after, request_id, agent_run_id, department_id"


def _obj(v) -> dict:
    if isinstance(v, dict):
        return v
    if isinstance(v, str) and v:
        try:
            d = json.loads(v)
            return d if isinstance(d, dict) else {}
        except ValueError:
            return {}
    return {}


def history_sentence(a: dict) -> str:
    """The same sentence the Issue page's History tab shows (app/features/issues/present.php, issue_history_sentence)."""
    who = a.get("actor_name") or "Someone"
    before, after, action = _obj(a.get("before")), _obj(a.get("after")), str(a.get("action") or "")
    if action == "issue.create":
        return who + " created it" + (f" as a {after['type']}" if after.get("type") else "")
    if action == "issue.transition":
        return who + " moved it to " + str(after.get("status") or "another status") + (f" from {before['status']}" if before.get("status") else "")
    if action == "issue.assign":
        return who + (f" assigned it to {after['assignee_name']}" if after.get("assignee_name") is not None else " unassigned it")
    if action == "issue.estimate":
        pts = after.get("story_points")
        return who + " estimated it at " + (f"{pts} points" if pts is not None else "no points") + (f" (was {before['story_points']})" if before.get("story_points") is not None else "")
    if action == "issue.update":
        keys = [str(k).replace("_member_id", "").replace("_id", "").replace("_", " ") for k in after.keys()] or ["it"]
        return who + " changed " + ", ".join(keys)
    fixed = {"issue.accept": " accepted it", "issue.unaccept": " withdrew the acceptance", "issue.watch": " started watching",
             "issue.unwatch": " stopped watching", "issue.unlink": " removed a link", "issue.rank": " reordered it in the backlog",
             "comment.add": " commented", "comment.edit": " edited a comment", "comment.delete": " deleted a comment",
             "checklist_item.add": " added a checklist item", "checklist_item.delete": " removed a checklist item",
             "worklog.delete": " removed a worklog", "attachment.delete": " removed an attachment"}
    if action in fixed:
        return who + fixed[action]
    if action == "issue.link":
        return f"{who} linked it: {after.get('from_key', '?')} {str(after.get('kind') or 'relates').replace('_', ' ')} {after.get('to_key', '?')}"
    if action == "issue.move_to_sprint":
        return who + (f" moved it to {after['sprint_name']}" if after.get("sprint_name") is not None else " moved it to the backlog")
    if action == "checklist_item.toggle":
        return who + (" ticked" if after.get("is_done") else " unticked") + " a checklist item"
    if action == "worklog.add":
        return f"{who} logged {after.get('hours', '?')} h"
    if action == "attachment.add":
        return f"{who} attached {after.get('file_name') or 'a file'}"
    return who + " — " + action.replace(".", " ").replace("_", " ")


class IssueHistoryIn(_Base):
    issue_id: int | None = Field(None, description="The issue's id")
    key: str | None = Field(None, description="Or its key, e.g. PROJ-12")
    action_prefix: str | None = Field(None, description="e.g. 'issue.transition', 'comment.'")
    limit: int = Field(100, ge=1, le=200)


@mcp.tool(name="issue_history", annotations={"title": "Everything that happened to an issue", **RO})
async def issue_history(params: IssueHistoryIn) -> str:
    """Everything that happened to one issue, oldest first, each row with the sentence the History tab
    shows (who created it, moved it, assigned it, estimated it, commented, linked it, logged hours…)
    and the changed fields (before/after). Call for "what happened to PROJ-12", "who moved it to done"."""
    pool = await _pool()
    iid = params.issue_id
    if iid is None and params.key:
        rows = await db.fetch_scoped(pool, "SELECT issue_id FROM mcp_issues WHERE key = $1", params.key.strip().upper())
        iid = int(rows[0]["issue_id"]) if rows else None
    if iid is None:
        return json.dumps({"error": "Say which issue: issue_id or key (find_issues on the records server looks them up)."})
    w = ["((entity_type = 'issue' AND entity_id = $1) OR (entity_type IN ('comment', 'checklist_item', 'worklog', 'attachment', 'issue_link') AND (after ->> 'issue_id' = $1::text OR before ->> 'issue_id' = $1::text)))"]
    a: list = [iid]
    if params.action_prefix:
        a.append(params.action_prefix + "%"); w.append(f"action LIKE ${len(a)}")
    a.append(params.limit)
    rows = await db.fetch_scoped(pool, f"SELECT {_COLS} FROM mcp_activity_log WHERE {' AND '.join(w)} ORDER BY occurred_at LIMIT ${len(a)}", *a)
    for r in rows:
        r["sentence"] = history_sentence(r)
    return db.to_json(rows)


class WhoDidIn(_Base):
    member_id: int | None = Field(None, description="The person or agent; omit for the caller")
    period: str | None = Field("this_week", description="today, this_week, last_week, this_month, last_month, this_quarter, this_year, or YYYY-MM")
    date_from: str | None = None
    date_to: str | None = None
    project_id: int | None = Field(None, description="Only in this project")
    action_prefix: str | None = Field(None, description="e.g. 'issue.', 'comment.', 'sprint.'")
    limit: int = Field(100, ge=1, le=200)


@mcp.tool(name="who_did", annotations={"title": "What someone did", **RO})
async def who_did(params: WhoDidIn) -> str:
    """What one person or agent did in Projects over a period, newest first — the stand-up answer.
    Call for "what did Sasha do yesterday", "what has the Scrum Master agent done this week"."""
    mid = params.member_id or db.request_member_id.get()
    s, e = _period_bounds(params.period, params.date_from, params.date_to)
    w = ["actor_member_id = $1", "occurred_at >= $2::date", "occurred_at < ($3::date + 1)"]
    a: list = [mid, s, e]
    if params.project_id:
        a.append(params.project_id)
        w.append(f"((entity_type = 'project' AND entity_id = ${len(a)}) OR coalesce(after ->> 'project_id', before ->> 'project_id') = ${len(a)}::text OR entity_id IN (SELECT issue_id FROM mcp_issues WHERE project_id = ${len(a)}) AND entity_type = 'issue')")
    if params.action_prefix:
        a.append(params.action_prefix + "%"); w.append(f"action LIKE ${len(a)}")
    a.append(params.limit)
    rows = await db.fetch_scoped(await _pool(), f"SELECT {_COLS} FROM mcp_activity_log WHERE {' AND '.join(w)} ORDER BY occurred_at DESC LIMIT ${len(a)}", *a)
    for r in rows:
        r["sentence"] = history_sentence(r)
    return db.to_json(rows)


class ProjectActivityIn(_Base):
    project_id: int | None = None
    project_key: str | None = None
    period: str | None = Field("this_week", description="A period word or YYYY-MM")
    date_from: str | None = None
    date_to: str | None = None
    action_prefix: str | None = Field(None, description="e.g. 'issue.transition', 'sprint.'")
    agents_only: bool = Field(False, description="Only what agents did (source 'agent')")
    limit: int = Field(100, ge=1, le=200)


@mcp.tool(name="project_activity", annotations={"title": "A project's activity feed", **RO})
async def project_activity(params: ProjectActivityIn) -> str:
    """Everything that happened in one project over a period, newest first — issues created, moved,
    assigned, estimated, commented; sprints started and closed; settings changed; what agents did.
    Call for "what happened in PROJ this week", "what did the agents do in the sprint"."""
    pool = await _pool()
    pid = params.project_id
    if pid is None and params.project_key:
        rows = await db.fetch_scoped(pool, "SELECT project_id FROM mcp_projects WHERE key = $1", params.project_key.strip().upper())
        pid = int(rows[0]["project_id"]) if rows else None
    if pid is None:
        return json.dumps({"error": "Say which project: project_id or project_key."})
    s, e = _period_bounds(params.period, params.date_from, params.date_to)
    w = ["occurred_at >= $2::date", "occurred_at < ($3::date + 1)",
         """((entity_type = 'project' AND entity_id = $1)
            OR coalesce(after ->> 'project_id', before ->> 'project_id') = $1::text
            OR (entity_type = 'issue' AND entity_id IN (SELECT issue_id FROM mcp_issues WHERE project_id = $1))
            OR (entity_type = 'sprint' AND entity_id IN (SELECT sprint_id FROM mcp_sprints WHERE project_id = $1)))"""]
    a: list = [pid, s, e]
    if params.action_prefix:
        a.append(params.action_prefix + "%"); w.append(f"action LIKE ${len(a)}")
    if params.agents_only:
        w.append("source = 'agent'")
    a.append(params.limit)
    rows = await db.fetch_scoped(pool, f"SELECT {_COLS} FROM mcp_activity_log WHERE {' AND '.join(w)} ORDER BY occurred_at DESC LIMIT ${len(a)}", *a)
    for r in rows:
        r["sentence"] = history_sentence(r)
    return db.to_json(rows)


class SprintEventsIn(_Base):
    sprint_id: int | None = Field(None, description="One sprint; omit for every sprint in the period")
    project_id: int | None = None
    period: str | None = Field("this_month", description="A period word or YYYY-MM")
    date_from: str | None = None
    date_to: str | None = None


@mcp.tool(name="sprint_events", annotations={"title": "Sprints planned, started and closed", **RO})
async def sprint_events(params: SprintEventsIn) -> str:
    """When sprints were planned, started and closed and by whom, with what each close carried over
    (the after holds the counts). Call for "when did sprint 4 start", "who closed the sprint". The
    issue-by-issue scope changes are sprint_scope_changes on the records server."""
    s, e = _period_bounds(params.period, params.date_from, params.date_to)
    w = ["entity_type = 'sprint'", "occurred_at >= $1::date", "occurred_at < ($2::date + 1)"]
    a: list = [s, e]
    if params.sprint_id:
        a.append(params.sprint_id); w.append(f"entity_id = ${len(a)}")
    if params.project_id:
        a.append(params.project_id); w.append(f"entity_id IN (SELECT sprint_id FROM mcp_sprints WHERE project_id = ${len(a)})")
    return await _q(f"SELECT {_COLS} FROM mcp_activity_log WHERE {' AND '.join(w)} ORDER BY occurred_at DESC LIMIT 200", *a)


class AgentActivityIn(_Base):
    agent_member_id: int | None = None
    run_id: int | None = Field(None, description="One kernel run")
    period: str | None = Field("this_week", description="A period word or YYYY-MM")
    date_from: str | None = None
    date_to: str | None = None
    paused_only: bool = Field(False, description="Only actions that paused for approval")


@mcp.tool(name="agent_activity", annotations={"title": "What agents did in Projects", **RO})
async def agent_activity(params: AgentActivityIn) -> str:
    """What the kernel's agents did in Projects, by run: every action taken under a run token (source
    'agent'), with the run id that joins to the kernel's prompt ledger. Call for "what did the Scrum
    Master change", "what is waiting on approval"."""
    s, e = _period_bounds(params.period, params.date_from, params.date_to)
    w = ["source = 'agent'", "occurred_at >= $1::date", "occurred_at < ($2::date + 1)"]
    a: list = [s, e]
    if params.agent_member_id:
        a.append(params.agent_member_id); w.append(f"actor_member_id = ${len(a)}")
    if params.run_id:
        a.append(params.run_id); w.append(f"agent_run_id = ${len(a)}")
    if params.paused_only:
        w.append("action LIKE '%.paused'")
    rows = await db.fetch_scoped(await _pool(), f"SELECT {_COLS} FROM mcp_activity_log WHERE {' AND '.join(w)} ORDER BY occurred_at DESC LIMIT 200", *a)
    for r in rows:
        r["sentence"] = history_sentence(r)
    return db.to_json(rows)


class SystemJobsIn(_Base):
    job: str | None = Field(None, description="'directory.sync', 'cron.' or omit for all")
    period: str | None = Field("this_month", description="A period word or YYYY-MM")
    date_from: str | None = None
    date_to: str | None = None


@mcp.tool(name="system_jobs", annotations={"title": "The jobs: directory sync, sign-ons", **RO})
async def system_jobs(params: SystemJobsIn) -> str:
    """When the directory mirror last synced and what it applied, and the sign-ons and refusals.
    Call for "is the directory up to date", "any refused sign-ons today"."""
    s, e = _period_bounds(params.period, params.date_from, params.date_to)
    w = ["(action LIKE 'directory.sync%' OR action LIKE 'cron.%' OR action LIKE 'member.sign_%' OR action = 'member.refused')", "occurred_at >= $1::date", "occurred_at < ($2::date + 1)"]
    a: list = [s, e]
    if params.job:
        a.append(params.job + "%"); w.append(f"action LIKE ${len(a)}")
    return await _q(f"SELECT {_COLS} FROM mcp_activity_log WHERE {' AND '.join(w)} ORDER BY occurred_at DESC LIMIT 200", *a)


class SearchIn(_Base):
    sql: str = Field(..., description="A single read-only SELECT over mcp_activity_log (and mcp_members, mcp_departments, mcp_projects, mcp_issues, mcp_sprints for names).")


@mcp.tool(name="activity_search", annotations={"title": "Activity search (SQL)", **RO})
async def activity_search(params: SearchIn) -> str:
    """The long tail: one read-only SELECT over mcp_activity_log (activity_id, occurred_at,
    actor_member_id, actor_name, actor_kind, source, action, screen, entity_type, entity_id, before,
    after, request_id, agent_run_id, department_id) — joined to mcp_members, mcp_departments,
    mcp_projects (project_id, key, name), mcp_issues (issue_id, key, summary) or mcp_sprints for names.
    Already scoped to what you may see. One statement, 5 s timeout, 200 rows."""
    return await db.run_search(await _pool(), params.sql)


if __name__ == "__main__":
    import server_common
    server_common.run(mcp, "MCP_ACTIVITY_DB_USER", "MCP_ACTIVITY_DB_PASSWORD", port=int(db.ENV["MCP_ACTIVITY_PORT"]), endpoint_name="Activity MCP")
