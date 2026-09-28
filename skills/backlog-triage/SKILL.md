---
name: backlog-triage
description: How new and stale issues are triaged in this business's Projects application — type, priority, estimate or ask, duplicates through links, and the stale rules. Use when asked to triage, tidy or groom a backlog, when new issues arrive without an owner, or when the hygiene tool reports stale or unestimated work.
kind: runbook
---

# Backlog triage

## For each new issue (no type, priority or estimate yet)
1. **Type**: a story says what a person wants and why; a task is a thing to do; a bug says what is
   wrong against what was expected; a subtask belongs under a parent. If the summary does not say which,
   ask the reporter in a comment rather than guessing.
2. **Priority**: urgent (a person is blocked now), high (this sprint), normal (soon), low (someday).
   Default normal; raise only with a reason in the comment.
3. **Estimate or ask**: never estimate a story yourself. Comment asking the team for points if it sits
   above the next sprint's capacity line (`backlog` shows the line); a task or bug may carry the
   reporter's estimate.
4. **Duplicates**: `find_issues` by the summary's key words; a match gets an `issue_link` of kind
   `duplicates` and a comment on the newer one pointing at the older; the product owner decides which
   stays.
5. **Epic**: if the issue plainly belongs to an epic (`epic_progress` lists them), say so in the
   comment; the product owner sets it.
6. **Owner**: an issue in the backlog need not have an assignee; one in a sprint must.

## Stale rules (`hygiene` reports them)
- In progress with no change for more than 5 days: comment asking the assignee whether it is blocked
  or done; if blocked, ask for the `blocks` link so the board shows it.
- In the backlog with no change for more than 60 days and below the top 50: comment proposing to close
  it as "won't do" — the product owner decides; you never close it.
- Unassigned in the active sprint: comment naming who, by `workload`, has room.

## What you never do
Never change type, priority or estimate on a story that a person set; never delete; never move between
sprints without a person's word; never rank.
