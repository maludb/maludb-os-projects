-- 013: the reports for the records MCP role. The report functions of db/010 read the base tables, which
-- projects_records_ro may not (it reads only the mcp_* views) — so its EXECUTE grant gave it functions it
-- could not run. These wrappers run as the owner, refuse a project the caller may not see (the answer is
-- empty, never an error), and are what the records server calls. The raw functions stay projects_rw's.
BEGIN;

CREATE OR REPLACE FUNCTION mcp_sprint_burndown(p_sprint_id bigint)
    RETURNS TABLE (day date, scope numeric, done numeric, remaining numeric)
    LANGUAGE sql STABLE SECURITY DEFINER SET search_path = public AS $$
    SELECT b.* FROM sprint_burndown(p_sprint_id) b
     WHERE EXISTS (SELECT 1 FROM sprints s WHERE s.id = p_sprint_id AND projects_can_see_project(s.project_id));
$$;
CREATE OR REPLACE FUNCTION mcp_project_velocity(p_project_id bigint, p_limit integer DEFAULT 6)
    RETURNS TABLE (sprint_id bigint, name text, start_date date, end_date date, committed numeric, completed numeric, carried numeric)
    LANGUAGE sql STABLE SECURITY DEFINER SET search_path = public AS $$
    SELECT v.* FROM project_velocity(p_project_id, p_limit) v WHERE projects_can_see_project(p_project_id);
$$;
CREATE OR REPLACE FUNCTION mcp_project_cycle_time(p_project_id bigint, p_since date DEFAULT current_date - 90)
    RETURNS TABLE (type text, resolved integer, median_days numeric, p85_days numeric, mean_days numeric)
    LANGUAGE sql STABLE SECURITY DEFINER SET search_path = public AS $$
    SELECT c.* FROM project_cycle_time(p_project_id, p_since) c WHERE projects_can_see_project(p_project_id);
$$;
CREATE OR REPLACE FUNCTION mcp_project_flow(p_project_id bigint, p_since date DEFAULT current_date - 30)
    RETURNS TABLE (day date, todo integer, in_progress integer, done integer)
    LANGUAGE sql STABLE SECURITY DEFINER SET search_path = public AS $$
    SELECT f.* FROM project_flow(p_project_id, p_since) f WHERE projects_can_see_project(p_project_id);
$$;
CREATE OR REPLACE FUNCTION mcp_project_hygiene(p_project_id bigint)
    RETURNS TABLE (issue_id bigint, key text, summary text, reason text, days integer)
    LANGUAGE sql STABLE SECURITY DEFINER SET search_path = public AS $$
    SELECT h.* FROM project_hygiene(p_project_id) h WHERE projects_can_see_project(p_project_id);
$$;

REVOKE ALL ON FUNCTION mcp_sprint_burndown(bigint), mcp_project_velocity(bigint, integer), mcp_project_cycle_time(bigint, date),
    mcp_project_flow(bigint, date), mcp_project_hygiene(bigint) FROM PUBLIC;
GRANT EXECUTE ON FUNCTION mcp_sprint_burndown(bigint), mcp_project_velocity(bigint, integer), mcp_project_cycle_time(bigint, date),
    mcp_project_flow(bigint, date), mcp_project_hygiene(bigint) TO projects_rw, projects_records_ro;
REVOKE EXECUTE ON FUNCTION sprint_burndown(bigint), project_velocity(bigint, integer), project_cycle_time(bigint, date),
    project_flow(bigint, date), project_hygiene(bigint) FROM projects_records_ro;

COMMIT;
