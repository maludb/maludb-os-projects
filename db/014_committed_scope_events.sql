-- 014 — the sprint's commitment as scope events (2026-09-28, slice 2's finding).
-- sprint_burndown() read a re-estimate of an issue committed at start as the FULL new value, because the
-- 'estimated' branch looks for the issue's earlier event in the sprint and an issue in the sprint when it
-- started had none. Now sprint_start() records one 'committed' event per issue (its points at start):
-- the burndown counts it as no change of scope (committed_points already holds it) and the re-estimate
-- finds it as the previous value; the scope-changes view leaves 'committed' rows out, so a scope report
-- still lists only what changed after the start. Sprints started before this migration have no committed
-- events, so a re-estimate of one of their committed issues is still read in full; nothing can backfill
-- the points an issue had at a start that was not recorded.
ALTER TABLE sprint_scope_events DROP CONSTRAINT sprint_scope_events_event_check;
ALTER TABLE sprint_scope_events ADD CONSTRAINT sprint_scope_events_event_check
    CHECK (event IN ('committed', 'added', 'removed', 'done', 'reopened', 'estimated'));

CREATE OR REPLACE FUNCTION sprint_start(p_sprint_id bigint, p_by bigint, p_start date DEFAULT current_date, p_end date DEFAULT NULL) RETURNS void
    LANGUAGE plpgsql AS $$
DECLARE
    v_project bigint; v_days smallint;
BEGIN
    SELECT project_id INTO v_project FROM sprints WHERE id = p_sprint_id AND state = 'future';
    IF v_project IS NULL THEN RAISE EXCEPTION 'Only a future sprint can start'; END IF;
    IF EXISTS (SELECT 1 FROM sprints WHERE project_id = v_project AND state = 'active') THEN RAISE EXCEPTION 'A sprint is already active in this project'; END IF;
    SELECT sprint_length_days INTO v_days FROM projects_settings;
    UPDATE sprints SET state = 'active', started_by = p_by, started_at = now(),
           start_date = coalesce(start_date, p_start), end_date = coalesce(p_end, end_date, coalesce(start_date, p_start) + v_days),
           committed_points = (SELECT coalesce(sum(story_points), 0) FROM issues WHERE sprint_id = p_sprint_id AND parent_issue_id IS NULL)
     WHERE id = p_sprint_id;
    INSERT INTO sprint_scope_events (sprint_id, issue_id, event, points, by_member_id)
    SELECT p_sprint_id, i.id, 'committed', i.story_points, p_by FROM issues i WHERE i.sprint_id = p_sprint_id AND i.parent_issue_id IS NULL;
END$$;

CREATE OR REPLACE FUNCTION sprint_burndown(p_sprint_id bigint)
    RETURNS TABLE (day date, scope numeric, done numeric, remaining numeric)
    LANGUAGE sql STABLE AS $$
    WITH s AS (SELECT * FROM sprints WHERE id = p_sprint_id),
    days AS (SELECT generate_series(s.start_date, least(coalesce(s.end_date, current_date), current_date), interval '1 day')::date AS day FROM s),
    ev AS (SELECT e.occurred_at::date AS day,
                  CASE e.event WHEN 'added' THEN coalesce(e.points, 0) WHEN 'removed' THEN -coalesce(e.points, 0)
                               WHEN 'estimated' THEN coalesce(e.points, 0) - coalesce((SELECT e2.points FROM sprint_scope_events e2 WHERE e2.sprint_id = e.sprint_id AND e2.issue_id = e.issue_id AND (e2.occurred_at, e2.id) < (e.occurred_at, e.id) ORDER BY e2.occurred_at DESC, e2.id DESC LIMIT 1), 0)
                               ELSE 0 END AS scope_delta,        -- 'committed' is already in committed_points: no change; the previous value is the latest earlier event by (time, id), so a same-instant commitment still counts
                  CASE e.event WHEN 'done' THEN coalesce(e.points, 0) WHEN 'reopened' THEN -coalesce(e.points, 0) ELSE 0 END AS done_delta
             FROM sprint_scope_events e WHERE e.sprint_id = p_sprint_id)
    SELECT d.day,
           coalesce(s.committed_points, 0) + coalesce((SELECT sum(scope_delta) FROM ev WHERE ev.day <= d.day), 0) AS scope,
           coalesce((SELECT sum(done_delta) FROM ev WHERE ev.day <= d.day), 0) AS done,
           coalesce(s.committed_points, 0) + coalesce((SELECT sum(scope_delta) FROM ev WHERE ev.day <= d.day), 0)
             - coalesce((SELECT sum(done_delta) FROM ev WHERE ev.day <= d.day), 0) AS remaining
      FROM days d CROSS JOIN s ORDER BY d.day;
$$;

-- The scope-changes view: what changed after the start — never the commitment itself.
CREATE OR REPLACE VIEW mcp_sprint_scope_events WITH (security_barrier = true) AS
SELECT e.id AS scope_event_id, e.sprint_id, e.issue_id, i.key AS issue_key, i.summary AS issue_summary, e.event, e.points, e.by_member_id, m.display_name AS by_name, e.occurred_at
  FROM sprint_scope_events e JOIN sprints s ON s.id = e.sprint_id LEFT JOIN issues i ON i.id = e.issue_id LEFT JOIN members m ON m.id = e.by_member_id
 WHERE e.event <> 'committed' AND projects_can_see_project(s.project_id);
