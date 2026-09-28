"""Issues — read tools (docs/projects-mcp-tool-surface.md, "Issues").
Reads mcp_issues, mcp_issue_details, mcp_issue_links, mcp_issue_watchers, mcp_checklist_items, mcp_comments,
mcp_attachments, mcp_worklogs, mcp_board_columns, mcp_workflow_statuses, mcp_sprints, mcp_project_team and
project_hygiene(). Every query runs as the asking member.
"""
from __future__ import annotations

import json

from pydantic import Field

import db
from business_common import RO, _Base
from projects_projects import _pool_from, resolve_project_id

ISSUE_COLS = """i.issue_id, i.key, i.project_id, i.project_key, i.type, i.summary, i.status_id, i.status, i.status_category, i.in_review,
    i.resolution, i.priority, i.story_points, i.estimate_hours, i.assignee_member_id, i.assignee_name, i.assignee_kind, i.reporter_member_id,
    i.reporter_name, i.parent_issue_id, i.parent_key, i.epic_id, i.epic_name, i.sprint_id, i.sprint_name, i.sprint_state, i.component_id,
    i.component_name, i.labels, i.rank, i.due_date, i.open_blockers, i.subtasks, i.subtasks_done, i.comments, i.hours_logged, i.i_watch,
    i.started_at, i.resolved_at, i.accepted_at, i.created_at, i.updated_at"""


class FindIssuesIn(_Base):
    q: str | None = Field(None, description="An issue key (PROJ-12, an old key too) or words of the summary")
    project_id: int | None = Field(None, description="Only this project (find_projects gives ids)")
    project_key: str | None = Field(None, description="Or its key")
    type: str | None = Field(None, description="story, task, bug or subtask")
    status: str | None = Field(None, description="A status name of the project's workflow, e.g. 'In review'")
    status_category: str | None = Field(None, description="todo, in_progress, done, or 'open' (= not done)")
    assignee_member_id: int | None = Field(None, description="Only issues assigned to this member (find_members gives ids)")
    assignee_kind: str | None = Field(None, description="'human' or 'agent' — the assignee's kind")
    reporter_member_id: int | None = None
    sprint_id: int | None = Field(None, description="Only this sprint")
    backlog: bool = Field(False, description="Only issues in no sprint")
    epic_id: int | None = None
    label: str | None = Field(None, description="A label name")
    component_id: int | None = None
    priority: str | None = Field(None, description="lowest, low, medium, high or highest")
    unassigned: bool = False
    blocked: bool = Field(False, description="Only issues with an open blocker")
    stale_days: int | None = Field(None, ge=1, description="Only issues in progress with no change for this many days")
    updated_since: str | None = Field(None, description="ISO date")
    resolved_since: str | None = Field(None, description="ISO date")
    include_subtasks: bool = Field(False, description="Subtasks are left out unless asked for")
    sort: str = Field("rank", description="rank, updated, created, priority or due")
    limit: int = Field(25, ge=1, le=100)


class GetIssueIn(_Base):
    issue_id: int | None = Field(None, description="The issue's id")
    key: str | None = Field(None, description="Or its key, e.g. PROJ-12 (an old key resolves too)")


class BacklogIn(_Base):
    project_id: int | None = None
    project_key: str | None = None
    epic_id: int | None = None
    unestimated_only: bool = False
    limit: int = Field(50, ge=1, le=100)


class BoardIn(_Base):
    project_id: int | None = None
    project_key: str | None = None
    assignee_member_id: int | None = Field(None, description="One lane only")


class BlockedIn(_Base):
    project_id: int | None = None
    limit: int = Field(25, ge=1, le=100)


class WorkloadIn(_Base):
    project_id: int | None = Field(None, description="One project; omit for every project the caller may see")
    sprint_id: int | None = Field(None, description="One sprint; omit for the project's active sprint, or all open work when there is none")
    include_done: bool = False


class MyWorkIn(_Base):
    member_id: int | None = Field(None, description="The person or agent; omit for the caller")
    include_watching: bool = True
    limit: int = Field(50, ge=1, le=100)


class HygieneIn(_Base):
    project_id: int | None = None
    project_key: str | None = None
    reason: str | None = Field(None, description="'stale', 'unassigned', 'unestimated' or 'blocked'; omit for all")


async def resolve_issue_id(pool, issue_id: int | None, key: str | None) -> int | None:
    if issue_id:
        rows = await db.fetch_scoped(pool, "SELECT issue_id FROM mcp_issues WHERE issue_id = $1", issue_id)
    elif key:
        k = key.strip().upper()
        rows = await db.fetch_scoped(pool, "SELECT issue_id FROM mcp_issues WHERE key = $1", k)
        if not rows:
            rows = await db.fetch_scoped(pool, "SELECT issue_id FROM mcp_issue_details WHERE $1 = ANY (aliases)", k)
    else:
        return None
    return int(rows[0]["issue_id"]) if rows else None


def register(mcp, q) -> None:
    @mcp.tool(name="find_issues", annotations={"title": "Find issues", **RO})
    async def find_issues(params: FindIssuesIn) -> str:
        """Any list of issues: by text, project, type, status or category, assignee or reporter, sprint,
        epic, label, component, priority; the unassigned; the blocked; the stale; what changed or was
        resolved since a date. CALL THIS FIRST whenever an action needs an issue — pass the key or words
        of the summary, never invent an id. Subtasks are left out unless include_subtasks."""
        pool = await _pool_from(q)
        w, a = ["1 = 1"], []
        pid = await resolve_project_id(pool, params.project_id, params.project_key) if (params.project_id or params.project_key) else None
        if (params.project_id or params.project_key) and pid is None:
            return json.dumps({"error": "No such project that you can see; find_projects looks them up by key or name."})
        if pid:
            a.append(pid); w.append(f"i.project_id = ${len(a)}")
        if params.q:
            k = params.q.strip().upper()
            a.append(k); a.append(f"%{params.q[:100]}%")
            w.append(f"(i.key = ${len(a) - 1} OR i.summary ILIKE ${len(a)} OR i.issue_id IN (SELECT issue_id FROM mcp_issue_details WHERE ${len(a) - 1} = ANY (aliases)))")
        if not params.include_subtasks:
            w.append("i.parent_issue_id IS NULL")
        if params.type in ("story", "task", "bug", "subtask"):
            a.append(params.type); w.append(f"i.type = ${len(a)}")
        if params.status:
            a.append(params.status); w.append(f"i.status ILIKE ${len(a)}")
        if params.status_category == "open":
            w.append("i.status_category <> 'done'")
        elif params.status_category in ("todo", "in_progress", "done"):
            a.append(params.status_category); w.append(f"i.status_category = ${len(a)}")
        if params.assignee_member_id:
            a.append(params.assignee_member_id); w.append(f"i.assignee_member_id = ${len(a)}")
        if params.assignee_kind in ("human", "agent"):
            a.append(params.assignee_kind); w.append(f"i.assignee_kind = ${len(a)}")
        if params.reporter_member_id:
            a.append(params.reporter_member_id); w.append(f"i.reporter_member_id = ${len(a)}")
        if params.sprint_id:
            a.append(params.sprint_id); w.append(f"i.sprint_id = ${len(a)}")
        if params.backlog:
            w.append("i.sprint_id IS NULL")
        if params.epic_id:
            a.append(params.epic_id); w.append(f"i.epic_id = ${len(a)}")
        if params.label:
            a.append(params.label); w.append(f"${len(a)} = ANY (i.labels)")
        if params.component_id:
            a.append(params.component_id); w.append(f"i.component_id = ${len(a)}")
        if params.priority in ("lowest", "low", "medium", "high", "highest"):
            a.append(params.priority); w.append(f"i.priority = ${len(a)}")
        if params.unassigned:
            w.append("i.assignee_member_id IS NULL")
        if params.blocked:
            w.append("i.open_blockers > 0")
        if params.stale_days:
            a.append(params.stale_days); w.append(f"i.status_category = 'in_progress' AND i.updated_at < now() - make_interval(days => ${len(a)})")
        if params.updated_since:
            a.append(params.updated_since[:10]); w.append(f"i.updated_at >= ${len(a)}::date")
        if params.resolved_since:
            a.append(params.resolved_since[:10]); w.append(f"i.resolved_at >= ${len(a)}::date")
        order = {"updated": "i.updated_at DESC", "created": "i.created_at DESC",
                 "priority": "array_position(ARRAY['highest','high','medium','low','lowest'], i.priority), i.rank",
                 "due": "i.due_date NULLS LAST, i.rank"}.get(params.sort, "i.rank, i.issue_id")
        a.append(params.limit)
        return await q(f"SELECT {ISSUE_COLS} FROM mcp_issues i WHERE {' AND '.join(w)} ORDER BY {order} LIMIT ${len(a)}", *a)

    @mcp.tool(name="get_issue", annotations={"title": "One issue, whole", **RO})
    async def get_issue(params: GetIssueIn) -> str:
        """One issue in full: every field, the description, old keys, subtasks, links both ways (what it
        blocks, what blocks it), labels, watchers, checklist, comments, attachments (name, size, who) and
        worklogs. Call for "tell me about PROJ-12", and before an action on its comments, checklist or
        worklogs (they carry the ids)."""
        pool = await _pool_from(q)
        iid = await resolve_issue_id(pool, params.issue_id, params.key)
        if iid is None:
            return json.dumps({"error": "No such issue that you can see; find_issues looks them up by key or words."})
        out = {}
        rows = await db.fetch_scoped(pool, f"SELECT {ISSUE_COLS}, d.description, d.accepted_by, d.accepted_by_name, d.aliases FROM mcp_issues i JOIN mcp_issue_details d ON d.issue_id = i.issue_id WHERE i.issue_id = $1", iid)
        out["issue"] = rows[0]
        out["subtasks"] = await db.fetch_scoped(pool, "SELECT issue_id, key, summary, status, status_category, assignee_name, story_points FROM mcp_issues WHERE parent_issue_id = $1 ORDER BY rank", iid)
        out["links"] = await db.fetch_scoped(pool, "SELECT link_id, kind, from_issue_id, from_key, from_summary, from_status_category, to_issue_id, to_key, to_summary, to_status_category FROM mcp_issue_links WHERE from_issue_id = $1 OR to_issue_id = $1 ORDER BY kind, link_id", iid)
        out["watchers"] = await db.fetch_scoped(pool, "SELECT member_id, display_name, member_kind FROM mcp_issue_watchers WHERE issue_id = $1 ORDER BY display_name", iid)
        out["checklist"] = await db.fetch_scoped(pool, "SELECT checklist_item_id, text, is_done, done_at FROM mcp_checklist_items WHERE issue_id = $1 ORDER BY sort_order, checklist_item_id", iid)
        out["comments"] = await db.fetch_scoped(pool, "SELECT comment_id, author_id, author_name, author_kind, body, edited_at, deleted_at, created_at FROM mcp_comments WHERE issue_id = $1 ORDER BY created_at", iid)
        out["attachments"] = await db.fetch_scoped(pool, "SELECT attachment_id, file_name, mime_type, size_bytes, uploaded_by_name, created_at FROM mcp_attachments WHERE issue_id = $1 ORDER BY created_at", iid)
        out["worklogs"] = await db.fetch_scoped(pool, "SELECT worklog_id, member_id, display_name, hours, worked_on, note FROM mcp_worklogs WHERE issue_id = $1 ORDER BY worked_on DESC, worklog_id DESC", iid)
        return db.to_json(out)

    @mcp.tool(name="backlog", annotations={"title": "The backlog", **RO})
    async def backlog(params: BacklogIn) -> str:
        """The project's backlog in rank order — what is unsprinted, and what is unestimated — with the
        future sprints' boxes above it (each sprint with its issues and points). Call for "what is next",
        "what is in the backlog", "what has no estimate". Rank orders the answer: the first row is the top."""
        pool = await _pool_from(q)
        pid = await resolve_project_id(pool, params.project_id, params.project_key)
        if pid is None:
            return json.dumps({"error": "No such project that you can see; find_projects looks them up by key or name."})
        out = {"sprints": []}
        for s in await db.fetch_scoped(pool, "SELECT sprint_id, name, goal, state, start_date, end_date, points, issues FROM mcp_sprints WHERE project_id = $1 AND state IN ('active', 'future') ORDER BY state = 'active' DESC, sort_order, sprint_id", pid):
            s["issues_list"] = await db.fetch_scoped(pool, "SELECT issue_id, key, type, summary, status, status_category, priority, story_points, assignee_name, open_blockers FROM mcp_issues WHERE sprint_id = $1 AND parent_issue_id IS NULL ORDER BY rank", int(s["sprint_id"]))
            out["sprints"].append(s)
        w, a = ["i.project_id = $1", "i.sprint_id IS NULL", "i.parent_issue_id IS NULL", "i.status_category <> 'done'"], [pid]
        if params.epic_id:
            a.append(params.epic_id); w.append(f"i.epic_id = ${len(a)}")
        if params.unestimated_only:
            w.append("i.story_points IS NULL")
        a.append(params.limit)
        out["backlog"] = await db.fetch_scoped(pool, f"SELECT issue_id, key, type, summary, status, priority, story_points, assignee_name, epic_name, labels, open_blockers, rank FROM mcp_issues i WHERE {' AND '.join(w)} ORDER BY i.rank LIMIT ${len(a)}", *a)
        out["unestimated"] = (await db.fetch_scoped(pool, "SELECT count(*) AS n FROM mcp_issues WHERE project_id = $1 AND sprint_id IS NULL AND parent_issue_id IS NULL AND status_category <> 'done' AND type = 'story' AND story_points IS NULL", pid))[0]["n"]
        return db.to_json(out)

    @mcp.tool(name="board", annotations={"title": "The board", **RO})
    async def board(params: BoardIn) -> str:
        """The board as the team sees it: the active sprint's issues (in kanban mode every open issue) by
        column in column order, each column's WIP limit and count, split by the project's swimlane (none,
        assignee or epic); blocked issues marked. Call for "what is on the board", "what is in review",
        "who is over their WIP"."""
        pool = await _pool_from(q)
        pid = await resolve_project_id(pool, params.project_id, params.project_key)
        if pid is None:
            return json.dumps({"error": "No such project that you can see; find_projects looks them up by key or name."})
        project = (await db.fetch_scoped(pool, "SELECT key, name, board_mode, swimlane, active_sprint_id, active_sprint_name FROM mcp_projects WHERE project_id = $1", pid))[0]
        w, a = ["i.project_id = $1", "i.parent_issue_id IS NULL"], [pid]
        if project["board_mode"] == "scrum":
            if project["active_sprint_id"] is None:
                return db.to_json({"project": project, "columns": [], "note": "No sprint is running — plan one from the backlog."})
            a.append(project["active_sprint_id"]); w.append(f"i.sprint_id = ${len(a)}")
        else:
            w.append("i.status_category <> 'done'")
        if params.assignee_member_id:
            a.append(params.assignee_member_id); w.append(f"i.assignee_member_id = ${len(a)}")
        issues = await db.fetch_scoped(pool, f"SELECT issue_id, key, type, summary, status_id, status, status_category, priority, story_points, assignee_member_id, assignee_name, assignee_kind, epic_id, epic_name, open_blockers, rank FROM mcp_issues i WHERE {' AND '.join(w)} ORDER BY i.rank", *a)
        cols = await db.fetch_scoped(pool, "SELECT column_id, name, sort_order, wip_limit, status_ids FROM mcp_board_columns WHERE project_id = $1 ORDER BY sort_order", pid)
        out_cols = []
        for c in cols:
            ids = set(int(x) for x in (c["status_ids"] or []))
            mine = [i for i in issues if int(i["status_id"]) in ids]
            lane_key = {"assignee": "assignee_name", "epic": "epic_name"}.get(project["swimlane"])
            lanes = {}
            for i in mine:
                lanes.setdefault((i[lane_key] or "(none)") if lane_key else "all", []).append(i)
            out_cols.append({"column_id": c["column_id"], "name": c["name"], "wip_limit": c["wip_limit"], "count": len(mine),
                             "over_limit": c["wip_limit"] is not None and len(mine) > c["wip_limit"], "lanes": lanes})
        return db.to_json({"project": project, "columns": out_cols})

    @mcp.tool(name="blocked_issues", annotations={"title": "What is blocked, and by what", **RO})
    async def blocked_issues(params: BlockedIn) -> str:
        """Which open issues are blocked, by which unfinished issues, and which issues block the most.
        Call for "what is stuck", "what is holding PROJ-12 up", "what should we unblock first"."""
        w, a = ["t.to_status_category <> 'done'", "t.from_status_category <> 'done'", "t.kind = 'blocks'"], []
        if params.project_id:
            a.append(params.project_id); w.append(f"i.project_id = ${len(a)}")
        a.append(params.limit)
        return await q(f"""SELECT t.to_issue_id AS issue_id, t.to_key AS key, t.to_summary AS summary, i.status, i.assignee_name,
                                  array_agg(t.from_key ORDER BY t.from_key) AS blocked_by
                             FROM mcp_issue_links t JOIN mcp_issues i ON i.issue_id = t.to_issue_id
                            WHERE {' AND '.join(w)} GROUP BY 1, 2, 3, 4, 5 ORDER BY count(*) DESC, t.to_key LIMIT ${len(a)}""", *a)

    @mcp.tool(name="workload", annotations={"title": "Who is working on what", **RO})
    async def workload(params: WorkloadIn) -> str:
        """Open issues and points per assignee (agents marked), and who on the team has nothing. Call
        for "who is busiest", "does Sasha have room", "what is unassigned in the sprint"."""
        pool = await _pool_from(q)
        w, a = ["i.parent_issue_id IS NULL"], []
        if not params.include_done:
            w.append("i.status_category <> 'done'")
        if params.project_id:
            a.append(params.project_id); w.append(f"i.project_id = ${len(a)}")
        if params.sprint_id:
            a.append(params.sprint_id); w.append(f"i.sprint_id = ${len(a)}")
        elif params.project_id:
            act = await db.fetch_scoped(pool, "SELECT active_sprint_id FROM mcp_projects WHERE project_id = $1", params.project_id)
            if act and act[0]["active_sprint_id"] is not None:
                a.append(act[0]["active_sprint_id"]); w.append(f"i.sprint_id = ${len(a)}")
        rows = await db.fetch_scoped(pool, f"""SELECT coalesce(i.assignee_member_id, 0) AS member_id, coalesce(i.assignee_name, '(unassigned)') AS name, i.assignee_kind AS kind,
                                                      count(*) AS issues, coalesce(sum(i.story_points), 0) AS points, count(*) FILTER (WHERE i.open_blockers > 0) AS blocked
                                                 FROM mcp_issues i WHERE {' AND '.join(w)} GROUP BY 1, 2, 3 ORDER BY points DESC, issues DESC""", *a)
        idle = []
        if params.project_id:
            busy = {int(r["member_id"]) for r in rows}
            idle = [t for t in await db.fetch_scoped(pool, "SELECT member_id, display_name, member_kind, project_role FROM mcp_project_team WHERE project_id = $1 AND removed_at IS NULL AND project_role <> 'viewer' ORDER BY display_name", params.project_id) if int(t["member_id"]) not in busy]
        return db.to_json({"workload": rows, "idle": idle})

    @mcp.tool(name="my_work", annotations={"title": "What is on someone's plate", **RO})
    async def my_work(params: MyWorkIn) -> str:
        """What is on a person's or an agent's plate across projects, in the shape the kernel's My Work
        wants: kind, id, title (key — summary), detail (project · status), due_at, state (blocked, overdue,
        due today, due, in review, open), href, urgency (2 blocked or overdue, 1 due soon or in review, 0),
        plus what they watch and what waits for their review. Call for "what should I do next", "what is
        Becky working on"."""
        pool = await _pool_from(q)
        mid = params.member_id or db.request_member_id.get()
        rows = await db.fetch_scoped(pool, """SELECT issue_id, key, summary, project_key, project_name, status, status_category, in_review, due_date, open_blockers, priority, updated_at
                                               FROM mcp_issues WHERE assignee_member_id = $1 AND status_category <> 'done'
                                              ORDER BY (open_blockers > 0) DESC, due_date NULLS LAST, rank LIMIT $2""", mid, params.limit)
        import datetime
        today = datetime.date.today()
        items = []
        for r in rows:
            due = r["due_date"]
            if r["open_blockers"] > 0:
                state, urgency = "blocked", 2
            elif due is not None and due < today:
                state, urgency = "overdue", 2
            elif due == today:
                state, urgency = "due today", 1
            elif r["in_review"]:
                state, urgency = "in review", 1
            elif due is not None and due <= today + datetime.timedelta(days=7):
                state, urgency = "due", 1
            else:
                state, urgency = "open", 0
            items.append({"kind": "issue", "id": r["issue_id"], "title": f"{r['key']} — {r['summary']}", "detail": f"{r['project_key']} · {r['status']}",
                          "due_at": due, "state": state, "href": f"/issues/{r['key']}", "urgency": urgency, "priority": r["priority"]})
        out = {"member_id": mid, "items": items}
        out["to_review"] = await db.fetch_scoped(pool, "SELECT issue_id, key, summary, project_key, assignee_name FROM mcp_issues WHERE in_review AND status_category <> 'done' AND (issue_id IN (SELECT issue_id FROM mcp_issue_watchers WHERE member_id = $1) OR reporter_member_id = $1) AND assignee_member_id IS DISTINCT FROM $1 ORDER BY updated_at DESC LIMIT 20", mid)
        if params.include_watching:
            out["watching"] = await db.fetch_scoped(pool, "SELECT i.issue_id, i.key, i.summary, i.project_key, i.status, i.updated_at FROM mcp_issues i JOIN mcp_issue_watchers w ON w.issue_id = i.issue_id WHERE w.member_id = $1 AND i.status_category <> 'done' ORDER BY i.updated_at DESC LIMIT 20", mid)
        return db.to_json(out)

    @mcp.tool(name="hygiene", annotations={"title": "What the Scrum Master flags", **RO})
    async def hygiene(params: HygieneIn) -> str:
        """What needs a nudge in a project: stale in progress (no change for the settings' days),
        unassigned in the active sprint, unestimated stories in the next sprint, blocked with the blocker
        named. Call at the start of a daily duty, or for "what is falling through the cracks"."""
        pool = await _pool_from(q)
        pid = await resolve_project_id(pool, params.project_id, params.project_key)
        if pid is None:
            return json.dumps({"error": "No such project that you can see; find_projects looks them up by key or name."})
        rows = await db.fetch_scoped(pool, "SELECT issue_id, key, summary, reason, days FROM mcp_project_hygiene($1) ORDER BY reason, key", pid)
        if params.reason:
            rows = [r for r in rows if r["reason"].startswith(params.reason)]
        return db.to_json(rows)
