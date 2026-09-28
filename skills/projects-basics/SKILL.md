---
name: projects-basics
description: How the Projects application is organised and how to answer work questions with its tools — projects, issues, epics, sprints, the board and its workflow, issue keys and ranks, which tool answers which question, who may see what, and how to comment. Use before any question or action about work in Projects.
---

# Projects basics

## The shape of the work
- A **project** (key `PROJ`, a name, a lead, an owning department, open or private) holds everything.
- An **issue** is one unit of work: a *story* (something a person wants), a *task* (something to do),
  a *bug* (something wrong), or a *subtask* (a part of one of those, under its parent). Fixed types.
- An **epic** is a large body of work that issues roll up to; `epic_progress` says how far along.
- The **backlog** is every issue not in a sprint and not done, in **rank** order — top is next.
- A **sprint** is a time-box (a name, a goal, start and end dates): *future* while it is planned,
  *active* once started (one per project at a time), *closed* when it ends; what was not done carries
  over to the backlog or the next sprint.
- The **board** shows the active sprint (or the whole backlog, on a kanban project) as columns; each
  column maps to one or more workflow statuses and may carry a WIP limit.
- The **workflow** is the project's statuses in order, each in a category — *to do*, *in progress*,
  *done* — and the moves allowed between them. Categories drive the reports, so renamed statuses still
  burn down.

## Keys and ranks
- An issue key is `PROJ-123`: the project's key, a dash, a number that counts up and is never reused.
  A key never changes; an issue moved to another project gets a new key and keeps the old as an alias.
- Rank is a position in the backlog, not a number to quote: say "top of the backlog", "third".
- **Points** are the estimate (0.5, 1, 2, 3, 5, 8, 13); an unestimated story cannot be committed to a
  sprint without a word from the product owner.

## Which tool answers which question
| Question | Tool |
|---|---|
| Which projects, mine, what state | `find_projects`, `get_project` |
| What is on my plate, due, overdue, waiting on my review | `my_work` |
| The backlog in order, what is unestimated | `backlog` |
| The sprint by column and by assignee, what is blocked | `board`, `blocked_issues` |
| One issue in full, with its history | `get_issue`, `issue_history` |
| Who works on what, who has nothing | `workload` |
| How the sprint is going | `sprint_status`, `burndown` |
| What we can commit to next | `velocity` |
| How long things take, by type | `cycle_time` |
| What changed in the sprint after it started | `sprint_scope_changes`, `sprint_events` |
| Unassigned, stale, unestimated | `hygiene` |
| Hours logged this sprint | `worklogs` |
| What someone did, what happened in a project | `who_did`, `project_activity` |
| Find the issue I half-remember | `find_issues`, `records_search` |

## Who may see what (the tools already enforce it — this is so you can explain a refusal)
- A **Viewer** reads open projects and the private ones they are on the team of.
- A **Member** also writes issues, comments, moves cards and logs hours.
- A **Product Owner** also ranks the backlog, plans sprints, accepts stories and writes epics.
- A **Scrum Master** also starts and closes sprints and configures the board.
- A **Projects Admin** does everything, including creating projects, workflows, teams and labels.
- An external collaborator is a person on a private project's team and sees that project alone.
- An agent sees what its grants name and only the projects the views admit for it.

## Comments
A comment is for the team: one point, plain words, the issue key when you refer to another issue
(`PROJ-45`), a person's name when something waits on them. Never paste a whole tool result; say what
it means. A nudge names what is missing and who is best placed to fix it, and stops there.

## Actions that pause for a person
Starting or closing a sprint, changing a workflow or a board, removing someone from a team, archiving a
project, deleting an issue or a comment. When a result is `pending_approval`, report who it waits for
and stop.
