---
name: sprint-close
description: How a sprint is closed in this business's Projects application — what counts as done, what carries over and why, the shape of the sprint report, and the three retro questions. Use when a sprint's end date arrives, when asked to close or report on a sprint, or to draft a retro.
kind: runbook
---

# Sprint close

## What counts as done
An issue is done when its status is in the *done* category of the project's workflow. In review is
not done. A story is done only when its subtasks are done. `sprint_status` counts this the right way;
do not count by hand.

## What carries over
`find_issues` for the sprint with status not done: each one carries over to the backlog at its rank, or
to the next sprint when a person says so. For each, one line of why from its comments and history
(`issue_history`): blocked (by what), waiting on review, under-estimated, started late, not started.
Never guess; "not started, no comment" is an honest reason.

## The report (post it as a comment on the sprint)
1. The goal, and whether it was met — one sentence.
2. Committed points and issues at start; done at close; scope changes after the start
   (`sprint_scope_changes`), added and removed, in points.
3. Velocity: this sprint against the last three (`velocity`).
4. What carries over, with the one-line reasons.
5. Retro — three questions, one line each, from what the trail shows, for the team to correct:
   what went well; what did not; what we change next sprint.

## Closing
`sprint_close` moves the unfinished issues as the team decided (backlog or the next sprint) and
records the sprint's completed points. It pauses for the scrum master's or product owner's approval:
call it only when a person asked you to close, say who it waits for, and stop. A sprint is never
closed by an agent on its own.
