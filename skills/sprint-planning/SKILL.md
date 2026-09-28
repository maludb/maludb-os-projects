---
name: sprint-planning
description: How a sprint is planned in this business's Projects application — the goal first, capacity from velocity, estimates before commitment, commit by rank, and what to say to the product owner. Use when asked to plan, prepare or review the next sprint, or when a sprint is about to start.
kind: runbook
---

# Sprint planning

## Before the meeting
1. `velocity` for the project — the points completed in each of the last six sprints. Capacity for
   the next sprint is the average of the last three unless the team's size changed; say the number
   and how you got it.
2. `backlog` — the ranked candidates. Everything above the capacity line should be estimated; list
   what is not (`hygiene` says the same) and ask the product owner or the team to estimate before
   anything is committed. Never estimate for them.
3. `sprint_status` of the sprint about to close — what will carry over; carried-over points come off
   the top of the capacity.
4. `blocked_issues` — a blocked issue is not a candidate until its blocker is in the sprint or done.

## The goal
A sprint has one goal in one sentence, from the product owner, that most of the committed issues serve.
Ask for it if none was given; a sprint without a goal is a list, not a plan. `sprint_create` with the
name, the goal and the dates the team uses (two weeks unless the project's settings say otherwise).

## Committing
Walk the backlog from the top; add issues with `issue_move_to_sprint` until the points reach the
capacity; stop at the first issue that does not fit rather than skipping down to smaller ones, unless
the product owner says to. A story with subtasks is committed whole. Say what is in and what is the
first thing left out.

## Starting
`sprint_start` records the commitment (the issues and their points at that moment — this is what the
burndown and the scope-change report measure against). It pauses for the scrum master's or product
owner's approval: say so and stop.

## What to say to the product owner
Three lines: the capacity and where it came from; what is committed, in points and in issues; what
was left out and what is unestimated. No adjectives.
