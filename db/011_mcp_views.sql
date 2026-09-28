-- 011: the mcp_* visibility views — the only thing the two read roles can see (memory.md §1).
-- Every WHERE clause IS the row rule; the servers never filter in Python. Primary keys are
-- re-aliased <entity>_id.
--
-- The rule, in one place (the design §3):
--   projects_can_see_project(p)  an active member admitted to Projects, and: the project is open and the
--                                member holds project.read (an agent: any admitted agent) | the member is
--                                on its team | the member administers its department | a Projects admin.
--   Everything an issue carries follows its project. A private project's existence is not shown.
BEGIN;

CREATE OR REPLACE FUNCTION projects_can_see_project(p_project_id bigint) RETURNS boolean
    LANGUAGE sql STABLE SECURITY DEFINER SET search_path = public AS $$
    SELECT app_is_active_member() AND EXISTS (
        SELECT 1 FROM projects p
         WHERE p.id = p_project_id
           AND (projects_is_admin()
                OR EXISTS (SELECT 1 FROM project_team t WHERE t.project_id = p.id AND t.member_id = app_current_member_id() AND t.removed_at IS NULL)
                OR (p.visibility = 'open' AND (app_member_kind() = 'agent' OR projects_has_right('project.read')))
                OR (p.department_id IS NOT NULL AND app_is_admin_of(p.department_id))));
$$;
-- May the caller change the work of a project: on its team with a working role, or the right business-wide.
CREATE OR REPLACE FUNCTION projects_can_work(p_project_id bigint) RETURNS boolean
    LANGUAGE sql STABLE SECURITY DEFINER SET search_path = public AS $$
    SELECT projects_can_see_project(p_project_id) AND (
           projects_is_admin()
        OR app_member_kind() = 'agent'
        OR projects_has_right('issue.write')
        OR EXISTS (SELECT 1 FROM project_team t WHERE t.project_id = p_project_id AND t.member_id = app_current_member_id()
                    AND t.removed_at IS NULL AND t.project_role <> 'viewer'));
$$;
-- A right inside a project: the business-wide right, or the project role that carries it.
CREATE OR REPLACE FUNCTION projects_has_right_in(p_right text, p_project_id bigint) RETURNS boolean
    LANGUAGE sql STABLE SECURITY DEFINER SET search_path = public AS $$
    SELECT projects_can_see_project(p_project_id) AND (
           projects_has_right(p_right)
        OR EXISTS (SELECT 1 FROM project_team t JOIN projects_role_rights rr
                     ON rr.role_key = CASE t.project_role WHEN 'lead' THEN 'projects_admin' WHEN 'product_owner' THEN 'product_owner'
                                                          WHEN 'scrum_master' THEN 'scrum_master' WHEN 'member' THEN 'member' ELSE 'viewer' END
                    WHERE t.project_id = p_project_id AND t.member_id = app_current_member_id() AND t.removed_at IS NULL
                      AND rr.right_key = p_right AND (p_right <> 'project.admin' OR t.project_role = 'lead')));
$$;
GRANT EXECUTE ON FUNCTION projects_can_see_project(bigint), projects_can_work(bigint), projects_has_right_in(text, bigint)
    TO projects_rw, projects_records_ro, projects_activity_ro;

-- The directory, as mirrored. Anyone admitted to Projects may read who works here.
CREATE OR REPLACE VIEW mcp_members WITH (security_barrier = true) AS
SELECT m.id AS member_id, m.display_name, m.email, m.member_kind, m.business_role, m.is_external, m.status,
       m.job_title, m.timezone, m.capability AS projects_capability,
       (SELECT array_agg(r.name ORDER BY r.sort_order) FROM projects_roles r WHERE r.role_key = ANY (m.roles)) AS projects_roles,
       (SELECT d.name FROM department_members dm JOIN departments d ON d.id = dm.department_id
         WHERE dm.member_id = m.id AND dm.left_at IS NULL ORDER BY dm.is_primary DESC, d.name LIMIT 1) AS primary_department,
       (SELECT dm.department_id FROM department_members dm
         WHERE dm.member_id = m.id AND dm.left_at IS NULL ORDER BY dm.is_primary DESC, dm.department_id LIMIT 1) AS primary_department_id,
       (SELECT count(*) FROM issues i JOIN workflow_statuses s ON s.id = i.status_id WHERE i.assignee_member_id = m.id AND s.category <> 'done') AS open_issues,
       m.synced_at
  FROM members m
 WHERE app_is_active_member();

CREATE OR REPLACE VIEW mcp_departments WITH (security_barrier = true) AS
SELECT d.id AS department_id, d.name, d.description, d.parent_id, p.name AS parent_name,
       d.manager_member_id, mg.display_name AS manager_name, d.is_system, d.system_key, d.archived_at,
       (SELECT count(*) FROM projects pr WHERE pr.department_id = d.id AND pr.status = 'active') AS projects,
       app_is_admin_of(d.id) AS i_administer
  FROM departments d
  LEFT JOIN departments p ON p.id = d.parent_id
  LEFT JOIN members mg ON mg.id = d.manager_member_id
 WHERE app_is_active_member();

CREATE OR REPLACE VIEW mcp_department_members WITH (security_barrier = true) AS
SELECT dm.member_id, m.display_name, m.member_kind, dm.department_id, d.name AS department_name, dm.is_admin, dm.is_primary, dm.joined_at, dm.left_at
  FROM department_members dm JOIN members m ON m.id = dm.member_id JOIN departments d ON d.id = dm.department_id
 WHERE app_is_active_member();

-- Projects and what they are made of.
CREATE OR REPLACE VIEW mcp_projects WITH (security_barrier = true) AS
SELECT p.id AS project_id, p.key, p.name, p.description, p.department_id, d.name AS department_name,
       p.lead_member_id, l.display_name AS lead_name, p.visibility, p.status, p.board_mode, p.swimlane, p.customer,
       (SELECT count(*) FROM project_team t WHERE t.project_id = p.id AND t.removed_at IS NULL) AS team_size,
       (SELECT count(*) FROM issues i JOIN workflow_statuses s ON s.id = i.status_id WHERE i.project_id = p.id AND i.parent_issue_id IS NULL AND s.category <> 'done') AS open_issues,
       (SELECT s.id FROM sprints s WHERE s.project_id = p.id AND s.state = 'active') AS active_sprint_id,
       (SELECT s.name FROM sprints s WHERE s.project_id = p.id AND s.state = 'active') AS active_sprint_name,
       (SELECT count(*) FROM epics e WHERE e.project_id = p.id AND e.status = 'open') AS open_epics,
       projects_can_work(p.id) AS i_can_work, projects_has_right_in('project.admin', p.id) AS i_administer,
       p.archived_at, p.created_by, p.created_at, p.updated_at
  FROM projects p
  LEFT JOIN departments d ON d.id = p.department_id
  LEFT JOIN members l ON l.id = p.lead_member_id
 WHERE projects_can_see_project(p.id);

CREATE OR REPLACE VIEW mcp_project_team WITH (security_barrier = true) AS
SELECT t.project_id, p.key AS project_key, t.member_id, m.display_name, m.member_kind, m.is_external, t.project_role, t.added_at, t.removed_at
  FROM project_team t JOIN projects p ON p.id = t.project_id JOIN members m ON m.id = t.member_id
 WHERE projects_can_see_project(t.project_id);

CREATE OR REPLACE VIEW mcp_workflow_statuses WITH (security_barrier = true) AS
SELECT s.id AS status_id, s.project_id, s.name, s.category, s.sort_order, s.is_initial, s.archived_at,
       (SELECT array_agg(t.to_status_id) FROM workflow_transitions t WHERE t.from_status_id = s.id) AS can_move_to
  FROM workflow_statuses s WHERE projects_can_see_project(s.project_id);

CREATE OR REPLACE VIEW mcp_board_columns WITH (security_barrier = true) AS
SELECT c.id AS column_id, c.project_id, c.name, c.sort_order, c.wip_limit,
       (SELECT array_agg(cs.status_id) FROM board_column_statuses cs WHERE cs.column_id = c.id) AS status_ids
  FROM board_columns c WHERE projects_can_see_project(c.project_id);

CREATE OR REPLACE VIEW mcp_labels WITH (security_barrier = true) AS
SELECT l.id AS label_id, l.project_id, l.name, l.colour, l.archived_at,
       (SELECT count(*) FROM issue_labels il WHERE il.label_id = l.id) AS issues
  FROM labels l WHERE projects_can_see_project(l.project_id);

CREATE OR REPLACE VIEW mcp_components WITH (security_barrier = true) AS
SELECT c.id AS component_id, c.project_id, c.name, c.description, c.default_assignee_id, m.display_name AS default_assignee_name, c.archived_at
  FROM components c LEFT JOIN members m ON m.id = c.default_assignee_id WHERE projects_can_see_project(c.project_id);

CREATE OR REPLACE VIEW mcp_epics WITH (security_barrier = true) AS
SELECT e.id AS epic_id, e.project_id, p.key AS project_key, e.name, e.summary, e.colour, e.status, e.target_date, e.sort_order,
       (SELECT count(*) FROM issues i WHERE i.epic_id = e.id AND i.parent_issue_id IS NULL) AS issues,
       (SELECT count(*) FROM issues i JOIN workflow_statuses s ON s.id = i.status_id WHERE i.epic_id = e.id AND i.parent_issue_id IS NULL AND s.category = 'done') AS issues_done,
       (SELECT coalesce(sum(i.story_points), 0) FROM issues i WHERE i.epic_id = e.id AND i.parent_issue_id IS NULL) AS points,
       (SELECT coalesce(sum(i.story_points), 0) FROM issues i JOIN workflow_statuses s ON s.id = i.status_id WHERE i.epic_id = e.id AND i.parent_issue_id IS NULL AND s.category = 'done') AS points_done,
       e.created_at, e.updated_at
  FROM epics e JOIN projects p ON p.id = e.project_id WHERE projects_can_see_project(e.project_id);

CREATE OR REPLACE VIEW mcp_sprints WITH (security_barrier = true) AS
SELECT s.id AS sprint_id, s.project_id, p.key AS project_key, s.name, s.goal, s.state, s.start_date, s.end_date, s.sort_order,
       s.committed_points, s.completed_points, s.carried_points,
       (SELECT count(*) FROM issues i WHERE i.sprint_id = s.id AND i.parent_issue_id IS NULL) AS issues,
       (SELECT count(*) FROM issues i JOIN workflow_statuses st ON st.id = i.status_id WHERE i.sprint_id = s.id AND i.parent_issue_id IS NULL AND st.category = 'done') AS issues_done,
       (SELECT coalesce(sum(i.story_points), 0) FROM issues i WHERE i.sprint_id = s.id AND i.parent_issue_id IS NULL) AS points,
       (SELECT coalesce(sum(i.story_points), 0) FROM issues i JOIN workflow_statuses st ON st.id = i.status_id WHERE i.sprint_id = s.id AND i.parent_issue_id IS NULL AND st.category = 'done') AS points_done,
       s.started_by, s.started_at, s.closed_by, s.closed_at, s.created_at, s.updated_at
  FROM sprints s JOIN projects p ON p.id = s.project_id WHERE projects_can_see_project(s.project_id);

CREATE OR REPLACE VIEW mcp_sprint_scope_events WITH (security_barrier = true) AS
SELECT e.id AS scope_event_id, e.sprint_id, e.issue_id, i.key AS issue_key, i.summary AS issue_summary, e.event, e.points, e.by_member_id, m.display_name AS by_name, e.occurred_at
  FROM sprint_scope_events e JOIN sprints s ON s.id = e.sprint_id LEFT JOIN issues i ON i.id = e.issue_id LEFT JOIN members m ON m.id = e.by_member_id
 WHERE projects_can_see_project(s.project_id);

-- Issues: the list's columns; the description travels only on the single-issue read.
CREATE OR REPLACE VIEW mcp_issues WITH (security_barrier = true) AS
SELECT i.id AS issue_id, i.key, i.project_id, p.key AS project_key, p.name AS project_name, i.number, i.type, i.summary,
       i.status_id, s.name AS status, s.category AS status_category, i.resolution, i.priority, i.story_points, i.estimate_hours,
       i.assignee_member_id, a.display_name AS assignee_name, a.member_kind AS assignee_kind,
       i.reporter_member_id, r.display_name AS reporter_name,
       i.parent_issue_id, pi.key AS parent_key, i.epic_id, e.name AS epic_name, i.sprint_id, sp.name AS sprint_name, sp.state AS sprint_state,
       i.component_id, c.name AS component_name, i.rank, i.due_date, i.started_at, i.resolved_at, i.accepted_at,
       (SELECT array_agg(l.name ORDER BY l.name) FROM issue_labels il JOIN labels l ON l.id = il.label_id WHERE il.issue_id = i.id) AS labels,
       (SELECT count(*) FROM issue_links l JOIN issues b ON b.id = l.from_issue_id JOIN workflow_statuses bs ON bs.id = b.status_id
         WHERE l.to_issue_id = i.id AND l.kind = 'blocks' AND bs.category <> 'done') AS open_blockers,
       (SELECT count(*) FROM issues st WHERE st.parent_issue_id = i.id) AS subtasks,
       (SELECT count(*) FROM issues st JOIN workflow_statuses ss ON ss.id = st.status_id WHERE st.parent_issue_id = i.id AND ss.category = 'done') AS subtasks_done,
       (SELECT count(*) FROM comments cm WHERE cm.issue_id = i.id AND cm.deleted_at IS NULL) AS comments,
       (SELECT coalesce(sum(w.hours), 0) FROM worklogs w WHERE w.issue_id = i.id) AS hours_logged,
       EXISTS (SELECT 1 FROM issue_watchers w WHERE w.issue_id = i.id AND w.member_id = app_current_member_id()) AS i_watch,
       i.created_by, i.created_at, i.updated_at
  FROM issues i
  JOIN projects p ON p.id = i.project_id
  JOIN workflow_statuses s ON s.id = i.status_id
  LEFT JOIN members a ON a.id = i.assignee_member_id
  LEFT JOIN members r ON r.id = i.reporter_member_id
  LEFT JOIN issues pi ON pi.id = i.parent_issue_id
  LEFT JOIN epics e ON e.id = i.epic_id
  LEFT JOIN sprints sp ON sp.id = i.sprint_id
  LEFT JOIN components c ON c.id = i.component_id
 WHERE projects_can_see_project(i.project_id);

CREATE OR REPLACE VIEW mcp_issue_details WITH (security_barrier = true) AS
SELECT i.id AS issue_id, i.key, i.description, i.accepted_by, ab.display_name AS accepted_by_name,
       (SELECT array_agg(a.old_key) FROM issue_aliases a WHERE a.issue_id = i.id) AS aliases
  FROM issues i LEFT JOIN members ab ON ab.id = i.accepted_by WHERE projects_can_see_project(i.project_id);

CREATE OR REPLACE VIEW mcp_issue_links WITH (security_barrier = true) AS
SELECT l.id AS link_id, l.kind, l.from_issue_id, f.key AS from_key, f.summary AS from_summary, fs.category AS from_status_category,
       l.to_issue_id, t.key AS to_key, t.summary AS to_summary, ts.category AS to_status_category, l.created_at
  FROM issue_links l
  JOIN issues f ON f.id = l.from_issue_id JOIN workflow_statuses fs ON fs.id = f.status_id
  JOIN issues t ON t.id = l.to_issue_id JOIN workflow_statuses ts ON ts.id = t.status_id
 WHERE projects_can_see_project(f.project_id) AND projects_can_see_project(t.project_id);

CREATE OR REPLACE VIEW mcp_issue_watchers WITH (security_barrier = true) AS
SELECT w.issue_id, w.member_id, m.display_name, m.member_kind, w.added_at
  FROM issue_watchers w JOIN issues i ON i.id = w.issue_id JOIN members m ON m.id = w.member_id WHERE projects_can_see_project(i.project_id);

CREATE OR REPLACE VIEW mcp_checklist_items WITH (security_barrier = true) AS
SELECT c.id AS checklist_item_id, c.issue_id, c.text, c.is_done, c.sort_order, c.done_by, c.done_at, c.created_at
  FROM checklist_items c JOIN issues i ON i.id = c.issue_id WHERE projects_can_see_project(i.project_id);

CREATE OR REPLACE VIEW mcp_comments WITH (security_barrier = true) AS
SELECT c.id AS comment_id, c.issue_id, i.key AS issue_key, c.author_id, m.display_name AS author_name, m.member_kind AS author_kind,
       CASE WHEN c.deleted_at IS NULL THEN c.body ELSE NULL END AS body, c.edited_at, c.deleted_at, c.created_at
  FROM comments c JOIN issues i ON i.id = c.issue_id LEFT JOIN members m ON m.id = c.author_id WHERE projects_can_see_project(i.project_id);

CREATE OR REPLACE VIEW mcp_attachments WITH (security_barrier = true) AS
SELECT a.id AS attachment_id, a.issue_id, a.file_name, a.mime_type, a.size_bytes, a.uploaded_by, m.display_name AS uploaded_by_name, a.created_at
  FROM attachments a JOIN issues i ON i.id = a.issue_id LEFT JOIN members m ON m.id = a.uploaded_by
 WHERE a.deleted_at IS NULL AND projects_can_see_project(i.project_id);     -- stored_path is in NO view: the gated handler reads it

CREATE OR REPLACE VIEW mcp_worklogs WITH (security_barrier = true) AS
SELECT w.id AS worklog_id, w.issue_id, i.key AS issue_key, i.project_id, w.member_id, m.display_name, w.hours, w.worked_on, w.note, w.created_at
  FROM worklogs w JOIN issues i ON i.id = w.issue_id JOIN members m ON m.id = w.member_id WHERE projects_can_see_project(i.project_id);

CREATE OR REPLACE VIEW mcp_saved_filters WITH (security_barrier = true) AS
SELECT f.id AS saved_filter_id, f.owner_id, o.display_name AS owner_name, f.project_id, f.name, f.filter, f.is_shared, f.updated_at
  FROM saved_filters f JOIN members o ON o.id = f.owner_id
 WHERE app_is_active_member() AND (f.owner_id = app_current_member_id() OR (f.is_shared AND (f.project_id IS NULL OR projects_can_see_project(f.project_id))));

CREATE OR REPLACE VIEW mcp_projects_settings AS
SELECT timezone, sprint_length_days, point_scale, stale_after_days, attachment_max_bytes, default_visibility FROM projects_settings WHERE app_is_active_member();

-- Activity memory, for the activity role: the trail of what the caller may see, never the kernel's.
CREATE OR REPLACE VIEW mcp_activity_log WITH (security_barrier = true) AS
SELECT a.id AS activity_id, a.occurred_at, a.actor_member_id, m.display_name AS actor_name, m.member_kind AS actor_kind, a.source, a.action,
       a.screen, a.entity_type, a.entity_id, a.before, a.after, a.request_id, a.agent_run_id, a.department_id
  FROM activity_log a LEFT JOIN members m ON m.id = a.actor_member_id
 WHERE app_is_active_member() AND a.action <> 'screen.view'
   AND (projects_is_admin()
        OR a.actor_member_id = app_current_member_id()
        OR (a.entity_type = 'project' AND projects_can_see_project(a.entity_id))
        OR (a.entity_type = 'issue' AND EXISTS (SELECT 1 FROM issues i WHERE i.id = a.entity_id AND projects_can_see_project(i.project_id)))
        OR (a.entity_type = 'sprint' AND EXISTS (SELECT 1 FROM sprints s WHERE s.id = a.entity_id AND projects_can_see_project(s.project_id)))
        OR (a.entity_type = 'epic' AND EXISTS (SELECT 1 FROM epics e WHERE e.id = a.entity_id AND projects_can_see_project(e.project_id)))
        OR (a.entity_type = 'comment' AND EXISTS (SELECT 1 FROM comments c JOIN issues i ON i.id = c.issue_id WHERE c.id = a.entity_id AND projects_can_see_project(i.project_id))));

GRANT SELECT ON mcp_members, mcp_departments, mcp_department_members, mcp_projects, mcp_project_team, mcp_workflow_statuses, mcp_board_columns,
    mcp_labels, mcp_components, mcp_epics, mcp_sprints, mcp_sprint_scope_events, mcp_issues, mcp_issue_details, mcp_issue_links, mcp_issue_watchers,
    mcp_checklist_items, mcp_comments, mcp_attachments, mcp_worklogs, mcp_saved_filters, mcp_projects_settings
    TO projects_rw, projects_records_ro;
GRANT SELECT ON mcp_activity_log, mcp_members, mcp_departments, mcp_projects, mcp_issues, mcp_sprints TO projects_activity_ro;

COMMIT;
