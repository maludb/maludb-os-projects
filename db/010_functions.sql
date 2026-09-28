-- 010: the rules that keep the work true, as functions and triggers — so a screen, an agent's action
-- and a bulk fix all behave the same: a new project gets its workflow and board; an issue gets its
-- key and its place in the rank; a transition must be allowed and stamps the cycle-time clocks; a
-- sprint records what it committed to and what it finished; a blocking link may not form a cycle;
-- the reports (burndown, velocity, cycle time, hygiene) are one function each.
BEGIN;

-- ---------------------------------------------------------------------------------------------
-- 1. A new project: the default workflow (To do → In progress → In review → Done) and a board
--    with a column per status.
-- ---------------------------------------------------------------------------------------------
CREATE OR REPLACE FUNCTION projects_seed_workflow(p_project_id bigint) RETURNS void
    LANGUAGE plpgsql AS $$
DECLARE
    v_todo bigint; v_prog bigint; v_rev bigint; v_done bigint; v_col bigint;
BEGIN
    INSERT INTO workflow_statuses (project_id, name, category, sort_order, is_initial) VALUES (p_project_id, 'To do', 'todo', 1, true) RETURNING id INTO v_todo;
    INSERT INTO workflow_statuses (project_id, name, category, sort_order) VALUES (p_project_id, 'In progress', 'in_progress', 2) RETURNING id INTO v_prog;
    INSERT INTO workflow_statuses (project_id, name, category, sort_order) VALUES (p_project_id, 'In review', 'in_progress', 3) RETURNING id INTO v_rev;
    INSERT INTO workflow_statuses (project_id, name, category, sort_order) VALUES (p_project_id, 'Done', 'done', 4) RETURNING id INTO v_done;
    INSERT INTO workflow_transitions (project_id, from_status_id, to_status_id) VALUES
        (p_project_id, v_todo, v_prog), (p_project_id, v_prog, v_rev), (p_project_id, v_prog, v_todo),
        (p_project_id, v_rev, v_done), (p_project_id, v_rev, v_prog), (p_project_id, v_done, v_prog), (p_project_id, v_done, v_todo);
    FOR v_col IN SELECT id FROM workflow_statuses WHERE project_id = p_project_id ORDER BY sort_order LOOP
        INSERT INTO board_columns (project_id, name, sort_order)
        SELECT p_project_id, s.name, s.sort_order FROM workflow_statuses s WHERE s.id = v_col;
        INSERT INTO board_column_statuses (column_id, status_id) VALUES (currval('board_columns_id_seq'), v_col);
    END LOOP;
END$$;

CREATE OR REPLACE FUNCTION projects_after_insert() RETURNS trigger
    LANGUAGE plpgsql AS $$
BEGIN
    PERFORM projects_seed_workflow(NEW.id);
    IF NEW.lead_member_id IS NOT NULL THEN
        INSERT INTO project_team (project_id, member_id, project_role, added_by) VALUES (NEW.id, NEW.lead_member_id, 'lead', NEW.created_by)
        ON CONFLICT (project_id, member_id) DO UPDATE SET project_role = 'lead', removed_at = NULL;
    END IF;
    RETURN NEW;
END$$;
CREATE TRIGGER projects_seed AFTER INSERT ON projects FOR EACH ROW EXECUTE FUNCTION projects_after_insert();

-- ---------------------------------------------------------------------------------------------
-- 2. Rank: a numeric with gaps. The backlog is ordered by rank ascending; a move sets the rank to
--    the midpoint between its neighbours; the last takes the max + 1000.
-- ---------------------------------------------------------------------------------------------
CREATE OR REPLACE FUNCTION projects_rank_between(p_before numeric, p_after numeric) RETURNS numeric
    LANGUAGE sql IMMUTABLE AS $$
    SELECT CASE
        WHEN p_before IS NULL AND p_after IS NULL THEN 1000
        WHEN p_before IS NULL THEN p_after - 1000
        WHEN p_after IS NULL THEN p_before + 1000
        ELSE (p_before + p_after) / 2
    END;
$$;

-- ---------------------------------------------------------------------------------------------
-- 3. An issue's key, initial status and rank; a subtask inherits its parent's project and sprint.
-- ---------------------------------------------------------------------------------------------
CREATE OR REPLACE FUNCTION issues_before_insert() RETURNS trigger
    LANGUAGE plpgsql AS $$
DECLARE
    v_key text; v_n integer;
BEGIN
    IF NEW.parent_issue_id IS NOT NULL THEN
        SELECT project_id, sprint_id INTO NEW.project_id, NEW.sprint_id FROM issues WHERE id = NEW.parent_issue_id;
        NEW.type := 'subtask';
    END IF;
    UPDATE projects SET issue_counter = issue_counter + 1 WHERE id = NEW.project_id RETURNING issue_counter, key INTO v_n, v_key;
    IF v_key IS NULL THEN RAISE EXCEPTION 'No such project'; END IF;
    NEW.number := v_n;
    NEW.key := v_key || '-' || v_n;
    IF NEW.status_id IS NULL THEN
        SELECT id INTO NEW.status_id FROM workflow_statuses WHERE project_id = NEW.project_id AND is_initial AND archived_at IS NULL;
    END IF;
    IF NEW.rank = 0 THEN
        SELECT coalesce(max(rank), 0) + 1000 INTO NEW.rank FROM issues WHERE project_id = NEW.project_id;
    END IF;
    IF NEW.reporter_member_id IS NULL THEN NEW.reporter_member_id := NEW.created_by; END IF;
    RETURN NEW;
END$$;
CREATE TRIGGER issues_key BEFORE INSERT ON issues FOR EACH ROW EXECUTE FUNCTION issues_before_insert();

-- ---------------------------------------------------------------------------------------------
-- 4. A transition must be one the workflow allows (an admin may force with app.force_transition);
--    the clocks: started_at on the first move into in_progress, resolved_at on a move into done
--    (cleared when reopened); resolution defaults to done and is cleared when reopened; a sprint
--    keeps its scope events; a subtask follows its parent's sprint.
-- ---------------------------------------------------------------------------------------------
CREATE OR REPLACE FUNCTION issues_before_update() RETURNS trigger
    LANGUAGE plpgsql AS $$
DECLARE
    v_from text; v_to text; v_same_project boolean;
BEGIN
    IF NEW.status_id IS DISTINCT FROM OLD.status_id THEN
        SELECT s.category INTO v_from FROM workflow_statuses s WHERE s.id = OLD.status_id;
        SELECT s.category INTO v_to FROM workflow_statuses s WHERE s.id = NEW.status_id AND s.project_id = NEW.project_id;
        IF v_to IS NULL THEN RAISE EXCEPTION 'That status is not in this project''s workflow'; END IF;
        IF current_setting('app.force_transition', true) IS DISTINCT FROM '1'
           AND NOT EXISTS (SELECT 1 FROM workflow_transitions t WHERE t.from_status_id = OLD.status_id AND t.to_status_id = NEW.status_id) THEN
            RAISE EXCEPTION 'The workflow does not allow that move';
        END IF;
        IF v_to = 'in_progress' AND NEW.started_at IS NULL THEN NEW.started_at := now(); END IF;
        IF v_to = 'done' THEN
            NEW.resolved_at := now();
            IF NEW.resolution IS NULL THEN NEW.resolution := 'done'; END IF;
        ELSIF v_from = 'done' THEN
            NEW.resolved_at := NULL; NEW.resolution := NULL; NEW.accepted_at := NULL; NEW.accepted_by := NULL;
        END IF;
    END IF;
    IF NEW.sprint_id IS DISTINCT FROM OLD.sprint_id AND NEW.sprint_id IS NOT NULL THEN
        SELECT s.project_id = NEW.project_id INTO v_same_project FROM sprints s WHERE s.id = NEW.sprint_id;
        IF NOT coalesce(v_same_project, false) THEN RAISE EXCEPTION 'That sprint belongs to another project'; END IF;
    END IF;
    RETURN NEW;
END$$;
CREATE TRIGGER issues_rules BEFORE UPDATE ON issues FOR EACH ROW EXECUTE FUNCTION issues_before_update();

CREATE OR REPLACE FUNCTION issues_after_update() RETURNS trigger
    LANGUAGE plpgsql AS $$
DECLARE
    v_active boolean; v_cat_old text; v_cat_new text;
BEGIN
    -- Scope events on an ACTIVE sprint only: added, removed, done, reopened, estimated.
    IF NEW.sprint_id IS DISTINCT FROM OLD.sprint_id THEN
        IF OLD.sprint_id IS NOT NULL AND EXISTS (SELECT 1 FROM sprints WHERE id = OLD.sprint_id AND state = 'active') THEN
            INSERT INTO sprint_scope_events (sprint_id, issue_id, event, points, by_member_id) VALUES (OLD.sprint_id, NEW.id, 'removed', OLD.story_points, app_current_member_id());
        END IF;
        IF NEW.sprint_id IS NOT NULL AND EXISTS (SELECT 1 FROM sprints WHERE id = NEW.sprint_id AND state = 'active') THEN
            INSERT INTO sprint_scope_events (sprint_id, issue_id, event, points, by_member_id) VALUES (NEW.sprint_id, NEW.id, 'added', NEW.story_points, app_current_member_id());
        END IF;
        -- Subtasks follow their parent.
        UPDATE issues SET sprint_id = NEW.sprint_id WHERE parent_issue_id = NEW.id AND sprint_id IS DISTINCT FROM NEW.sprint_id;
    END IF;
    IF NEW.sprint_id IS NOT NULL THEN
        SELECT state = 'active' INTO v_active FROM sprints WHERE id = NEW.sprint_id;
        IF coalesce(v_active, false) THEN
            IF NEW.status_id IS DISTINCT FROM OLD.status_id THEN
                SELECT category INTO v_cat_old FROM workflow_statuses WHERE id = OLD.status_id;
                SELECT category INTO v_cat_new FROM workflow_statuses WHERE id = NEW.status_id;
                IF v_cat_new = 'done' AND v_cat_old <> 'done' THEN
                    INSERT INTO sprint_scope_events (sprint_id, issue_id, event, points, by_member_id) VALUES (NEW.sprint_id, NEW.id, 'done', NEW.story_points, app_current_member_id());
                ELSIF v_cat_old = 'done' AND v_cat_new <> 'done' THEN
                    INSERT INTO sprint_scope_events (sprint_id, issue_id, event, points, by_member_id) VALUES (NEW.sprint_id, NEW.id, 'reopened', NEW.story_points, app_current_member_id());
                END IF;
            END IF;
            IF NEW.story_points IS DISTINCT FROM OLD.story_points AND NEW.sprint_id = OLD.sprint_id THEN
                INSERT INTO sprint_scope_events (sprint_id, issue_id, event, points, by_member_id) VALUES (NEW.sprint_id, NEW.id, 'estimated', NEW.story_points, app_current_member_id());
            END IF;
        END IF;
    END IF;
    RETURN NEW;
END$$;
CREATE TRIGGER issues_scope AFTER UPDATE ON issues FOR EACH ROW EXECUTE FUNCTION issues_after_update();

-- ---------------------------------------------------------------------------------------------
-- 5. A blocking link may not form a cycle.
-- ---------------------------------------------------------------------------------------------
CREATE OR REPLACE FUNCTION projects_link_would_cycle(p_from bigint, p_to bigint) RETURNS boolean
    LANGUAGE sql STABLE AS $$
    WITH RECURSIVE chain AS (
        SELECT l.to_issue_id AS id FROM issue_links l WHERE l.from_issue_id = p_to AND l.kind = 'blocks'
        UNION
        SELECT l.to_issue_id FROM issue_links l JOIN chain c ON l.from_issue_id = c.id WHERE l.kind = 'blocks'
    )
    SELECT p_from = p_to OR EXISTS (SELECT 1 FROM chain WHERE id = p_from);
$$;
CREATE OR REPLACE FUNCTION issue_links_before_insert() RETURNS trigger
    LANGUAGE plpgsql AS $$
BEGIN
    IF NEW.kind = 'blocks' AND projects_link_would_cycle(NEW.from_issue_id, NEW.to_issue_id) THEN
        RAISE EXCEPTION 'That would make a cycle of blocks';
    END IF;
    RETURN NEW;
END$$;
CREATE TRIGGER issue_links_no_cycle BEFORE INSERT ON issue_links FOR EACH ROW EXECUTE FUNCTION issue_links_before_insert();

-- ---------------------------------------------------------------------------------------------
-- 6. Sprints: start records the commitment; close records what was done and carries the rest to
--    the next future sprint (or the backlog).
-- ---------------------------------------------------------------------------------------------
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
END$$;

CREATE OR REPLACE FUNCTION sprint_close(p_sprint_id bigint, p_by bigint, p_carry_to bigint DEFAULT NULL) RETURNS TABLE (completed numeric, carried numeric, carried_issues integer)
    LANGUAGE plpgsql AS $$
DECLARE
    v_project bigint; v_done numeric; v_carry numeric; v_n integer;
BEGIN
    SELECT project_id INTO v_project FROM sprints WHERE id = p_sprint_id AND state = 'active';
    IF v_project IS NULL THEN RAISE EXCEPTION 'Only the active sprint can close'; END IF;
    IF p_carry_to IS NOT NULL AND NOT EXISTS (SELECT 1 FROM sprints WHERE id = p_carry_to AND project_id = v_project AND state = 'future') THEN
        RAISE EXCEPTION 'Carry over to a future sprint of this project, or to the backlog';
    END IF;
    SELECT coalesce(sum(i.story_points), 0) INTO v_done FROM issues i JOIN workflow_statuses s ON s.id = i.status_id
     WHERE i.sprint_id = p_sprint_id AND i.parent_issue_id IS NULL AND s.category = 'done';
    SELECT coalesce(sum(i.story_points), 0), count(*) INTO v_carry, v_n FROM issues i JOIN workflow_statuses s ON s.id = i.status_id
     WHERE i.sprint_id = p_sprint_id AND i.parent_issue_id IS NULL AND s.category <> 'done';
    UPDATE sprints SET state = 'closed', closed_by = p_by, closed_at = now(), completed_points = v_done, carried_points = v_carry WHERE id = p_sprint_id;
    -- The unfinished go on (subtasks follow through the issues trigger); scope events are not written for a closed sprint.
    UPDATE issues i SET sprint_id = p_carry_to FROM workflow_statuses s
     WHERE s.id = i.status_id AND i.sprint_id = p_sprint_id AND i.parent_issue_id IS NULL AND s.category <> 'done';
    RETURN QUERY SELECT v_done, v_carry, v_n;
END$$;

-- ---------------------------------------------------------------------------------------------
-- 7. The reports.
-- ---------------------------------------------------------------------------------------------
-- Burndown: for each day of the sprint, the points remaining (scope − done) at the end of that day.
CREATE OR REPLACE FUNCTION sprint_burndown(p_sprint_id bigint)
    RETURNS TABLE (day date, scope numeric, done numeric, remaining numeric)
    LANGUAGE sql STABLE AS $$
    WITH s AS (SELECT * FROM sprints WHERE id = p_sprint_id),
    days AS (SELECT generate_series(s.start_date, least(coalesce(s.end_date, current_date), current_date), interval '1 day')::date AS day FROM s),
    ev AS (SELECT e.occurred_at::date AS day,
                  CASE e.event WHEN 'added' THEN coalesce(e.points, 0) WHEN 'removed' THEN -coalesce(e.points, 0)
                               WHEN 'estimated' THEN coalesce(e.points, 0) - coalesce((SELECT e2.points FROM sprint_scope_events e2 WHERE e2.sprint_id = e.sprint_id AND e2.issue_id = e.issue_id AND e2.occurred_at < e.occurred_at ORDER BY e2.occurred_at DESC LIMIT 1), 0)
                               ELSE 0 END AS scope_delta,
                  CASE e.event WHEN 'done' THEN coalesce(e.points, 0) WHEN 'reopened' THEN -coalesce(e.points, 0) ELSE 0 END AS done_delta
             FROM sprint_scope_events e WHERE e.sprint_id = p_sprint_id)
    SELECT d.day,
           coalesce(s.committed_points, 0) + coalesce((SELECT sum(scope_delta) FROM ev WHERE ev.day <= d.day), 0) AS scope,
           coalesce((SELECT sum(done_delta) FROM ev WHERE ev.day <= d.day), 0) AS done,
           coalesce(s.committed_points, 0) + coalesce((SELECT sum(scope_delta) FROM ev WHERE ev.day <= d.day), 0)
             - coalesce((SELECT sum(done_delta) FROM ev WHERE ev.day <= d.day), 0) AS remaining
      FROM days d CROSS JOIN s ORDER BY d.day;
$$;

-- Velocity: the last N closed sprints of a project — committed and completed points.
CREATE OR REPLACE FUNCTION project_velocity(p_project_id bigint, p_limit integer DEFAULT 6)
    RETURNS TABLE (sprint_id bigint, name text, start_date date, end_date date, committed numeric, completed numeric, carried numeric)
    LANGUAGE sql STABLE AS $$
    SELECT s.id, s.name, s.start_date, s.end_date, s.committed_points, s.completed_points, s.carried_points
      FROM sprints s WHERE s.project_id = p_project_id AND s.state = 'closed'
     ORDER BY s.closed_at DESC LIMIT greatest(1, least(p_limit, 24));
$$;

-- Cycle time: from the first move into in_progress to done, by type, over issues resolved in a window.
CREATE OR REPLACE FUNCTION project_cycle_time(p_project_id bigint, p_since date DEFAULT current_date - 90)
    RETURNS TABLE (type text, resolved integer, median_days numeric, p85_days numeric, mean_days numeric)
    LANGUAGE sql STABLE AS $$
    SELECT i.type, count(*)::integer,
           round((percentile_cont(0.5) WITHIN GROUP (ORDER BY extract(epoch FROM (i.resolved_at - i.started_at)) / 86400))::numeric, 1),
           round((percentile_cont(0.85) WITHIN GROUP (ORDER BY extract(epoch FROM (i.resolved_at - i.started_at)) / 86400))::numeric, 1),
           round(avg(extract(epoch FROM (i.resolved_at - i.started_at)) / 86400)::numeric, 1)
      FROM issues i WHERE i.project_id = p_project_id AND i.resolved_at IS NOT NULL AND i.started_at IS NOT NULL AND i.resolved_at::date >= p_since
     GROUP BY i.type ORDER BY i.type;
$$;

-- Cumulative flow: issues per status category per day over a window (from the log would be exact; this
-- is the current picture plus the resolved history, enough for the chart).
CREATE OR REPLACE FUNCTION project_flow(p_project_id bigint, p_since date DEFAULT current_date - 30)
    RETURNS TABLE (day date, todo integer, in_progress integer, done integer)
    LANGUAGE sql STABLE AS $$
    SELECT d.day,
           (SELECT count(*) FROM issues i WHERE i.project_id = p_project_id AND i.parent_issue_id IS NULL AND i.created_at::date <= d.day AND (i.started_at IS NULL OR i.started_at::date > d.day) AND (i.resolved_at IS NULL OR i.resolved_at::date > d.day))::integer,
           (SELECT count(*) FROM issues i WHERE i.project_id = p_project_id AND i.parent_issue_id IS NULL AND i.started_at IS NOT NULL AND i.started_at::date <= d.day AND (i.resolved_at IS NULL OR i.resolved_at::date > d.day))::integer,
           (SELECT count(*) FROM issues i WHERE i.project_id = p_project_id AND i.parent_issue_id IS NULL AND i.resolved_at IS NOT NULL AND i.resolved_at::date <= d.day)::integer
      FROM generate_series(p_since, current_date, interval '1 day') AS d(day) ORDER BY d.day;
$$;

-- Hygiene: what the Scrum Master flags — stale, unassigned in the active sprint, unestimated stories, blocked.
CREATE OR REPLACE FUNCTION project_hygiene(p_project_id bigint)
    RETURNS TABLE (issue_id bigint, key text, summary text, reason text, days integer)
    LANGUAGE sql STABLE AS $$
    WITH st AS (SELECT stale_after_days FROM projects_settings),
    act AS (SELECT id FROM sprints WHERE project_id = p_project_id AND state = 'active'),
    nxt AS (SELECT id FROM sprints WHERE project_id = p_project_id AND state = 'future' ORDER BY sort_order, id LIMIT 1)
    SELECT i.id, i.key, i.summary, 'stale', (current_date - i.updated_at::date)::integer
      FROM issues i JOIN workflow_statuses s ON s.id = i.status_id, st
     WHERE i.project_id = p_project_id AND s.category = 'in_progress' AND i.updated_at < now() - (st.stale_after_days || ' days')::interval
    UNION ALL
    SELECT i.id, i.key, i.summary, 'unassigned in the active sprint', NULL
      FROM issues i JOIN workflow_statuses s ON s.id = i.status_id
     WHERE i.project_id = p_project_id AND i.sprint_id IN (SELECT id FROM act) AND i.assignee_member_id IS NULL AND s.category <> 'done'
    UNION ALL
    SELECT i.id, i.key, i.summary, 'unestimated in the next sprint', NULL
      FROM issues i WHERE i.project_id = p_project_id AND i.sprint_id IN (SELECT id FROM nxt) AND i.type = 'story' AND i.story_points IS NULL
    UNION ALL
    SELECT i.id, i.key, i.summary, 'blocked by ' || string_agg(b.key, ', '), NULL
      FROM issues i JOIN issue_links l ON l.to_issue_id = i.id AND l.kind = 'blocks'
      JOIN issues b ON b.id = l.from_issue_id JOIN workflow_statuses bs ON bs.id = b.status_id AND bs.category <> 'done'
      JOIN workflow_statuses s ON s.id = i.status_id AND s.category <> 'done'
     WHERE i.project_id = p_project_id GROUP BY i.id, i.key, i.summary;
$$;

GRANT EXECUTE ON FUNCTION projects_seed_workflow(bigint), projects_rank_between(numeric, numeric), projects_link_would_cycle(bigint, bigint),
    sprint_start(bigint, bigint, date, date), sprint_close(bigint, bigint, bigint) TO projects_rw;
GRANT EXECUTE ON FUNCTION sprint_burndown(bigint), project_velocity(bigint, integer), project_cycle_time(bigint, date), project_flow(bigint, date), project_hygiene(bigint)
    TO projects_rw, projects_records_ro;

COMMIT;
