-- 005: Projects' roles and the rights they give (kernel db/145; maludb-os-integration 0.4.1
-- roles-and-rights.md; the design §3).
--
-- Projects publishes its roles to the kernel through its records MCP server (tool app_roles): the
-- kernel keeps a copy, a super-admin grants a person a SET of roles, and the claims and the
-- directory feed carry the role keys back. What a role lets its holder do is Projects' to enforce:
-- projects_has_right(right) answers from the catalogue and the mirror; inside a project the team
-- list narrows it (db/006, projects_can_see_project). The super-admin holds every right. A member
-- the kernel has not yet sent roles for (roles = '{}') is read from the capability: admin = Projects
-- Admin, write = Member, read = Viewer.
BEGIN;

CREATE TABLE projects_rights (
    right_key   text PRIMARY KEY CHECK (right_key ~ '^[a-z][a-z0-9_.]{0,59}$'),
    description text NOT NULL,
    sort_order  integer NOT NULL DEFAULT 0
);
CREATE TABLE projects_roles (
    role_key    text PRIMARY KEY CHECK (role_key ~ '^[a-z][a-z0-9_]{0,39}$'),
    name        text NOT NULL,
    description text NOT NULL,
    capability  text NOT NULL CHECK (capability IN ('read', 'write', 'admin')),
    is_admin    boolean NOT NULL DEFAULT false,
    sort_order  integer NOT NULL DEFAULT 0,
    CHECK (NOT is_admin OR capability = 'admin')
);
CREATE UNIQUE INDEX projects_roles_one_admin ON projects_roles (is_admin) WHERE is_admin;
CREATE TABLE projects_role_rights (
    role_key  text NOT NULL REFERENCES projects_roles(role_key) ON DELETE CASCADE,
    right_key text NOT NULL REFERENCES projects_rights(right_key) ON DELETE CASCADE,
    PRIMARY KEY (role_key, right_key)
);

INSERT INTO projects_rights (right_key, description, sort_order) VALUES
 ('project.read',   'See open projects, their backlogs, boards, sprints, epics, issues and reports; and the private projects they are on the team of', 1),
 ('issue.write',    'Create and edit issues, subtasks and checklists; move their own and their team''s issues on the board', 2),
 ('comment.write',  'Comment on issues and watch them', 3),
 ('board.move',     'Move any issue of a project they may see across the board (a transition)', 4),
 ('worklog.write',  'Log hours on issues', 5),
 ('backlog.rank',   'Rank the backlog and estimate issues', 6),
 ('sprint.plan',    'Create and shape sprints: move issues in and out, set the goal', 7),
 ('issue.accept',   'Accept or reject a story (the product owner''s word on done)', 8),
 ('epic.write',     'Create and edit epics', 9),
 ('sprint.run',     'Start and close sprints, carry issues over', 10),
 ('board.configure','Change a project''s board: columns, WIP limits, swimlanes', 11),
 ('project.admin',  'Create and archive projects, set their workflow, team, roles, labels, components and visibility', 12);

INSERT INTO projects_roles (role_key, name, description, capability, is_admin, sort_order) VALUES
 ('viewer',         'Viewer',         'Reads the work: projects, boards, issues and reports; never changes anything.', 'read', false, 1),
 ('member',         'Member',         'Works the issues: creates, edits, comments, moves cards, logs hours.', 'write', false, 2),
 ('product_owner',  'Product Owner',  'Owns the backlog: ranks it, estimates, plans sprints, accepts stories, keeps the epics.', 'write', false, 3),
 ('scrum_master',   'Scrum Master',   'Runs the sprints and the board: starts and closes sprints, shapes the columns and WIP.', 'write', false, 4),
 ('projects_admin', 'Projects Admin', 'Runs Projects: creates and archives projects, sets workflows, teams and roles. A super-admin holds this role.', 'admin', true, 5);

INSERT INTO projects_role_rights (role_key, right_key)
SELECT 'viewer', r FROM unnest(ARRAY['project.read']) r
UNION ALL SELECT 'member', r FROM unnest(ARRAY['project.read','issue.write','comment.write','board.move','worklog.write']) r
UNION ALL SELECT 'product_owner', r FROM unnest(ARRAY['project.read','issue.write','comment.write','board.move','worklog.write','backlog.rank','sprint.plan','issue.accept','epic.write']) r
UNION ALL SELECT 'scrum_master', r FROM unnest(ARRAY['project.read','issue.write','comment.write','board.move','worklog.write','backlog.rank','sprint.plan','sprint.run','board.configure']) r
UNION ALL SELECT 'projects_admin', right_key FROM projects_rights;

-- Each member's roles, as the kernel sends them.
ALTER TABLE members ADD COLUMN roles text[] NOT NULL DEFAULT '{}';
COMMENT ON COLUMN members.roles IS 'The Projects roles the kernel says this member holds (claims.roles / feed access[].roles). Written only from the kernel''s word, like capability.';

-- The rule: a right the member's roles give — or, roles not yet sent, the role the capability implies.
CREATE OR REPLACE FUNCTION projects_has_right(p_right text) RETURNS boolean
    LANGUAGE sql STABLE SECURITY DEFINER SET search_path = public AS $$
    SELECT app_is_super_admin()
        OR EXISTS (SELECT 1 FROM members m
                     JOIN projects_role_rights rr ON rr.role_key = ANY (m.roles)
                    WHERE m.id = app_current_member_id() AND m.status = 'active' AND m.capability IS NOT NULL
                      AND rr.right_key = p_right)
        OR EXISTS (SELECT 1 FROM members m
                     JOIN projects_role_rights rr ON rr.role_key = CASE m.capability WHEN 'admin' THEN 'projects_admin' WHEN 'write' THEN 'member' ELSE 'viewer' END
                    WHERE m.id = app_current_member_id() AND m.status = 'active' AND m.capability IS NOT NULL
                      AND cardinality(m.roles) = 0 AND rr.right_key = p_right);
$$;

-- Projects admins (db/001 declared it; the roles define it now): the super-admin, or a human with project.admin.
CREATE OR REPLACE FUNCTION projects_is_admin() RETURNS boolean
    LANGUAGE sql STABLE SECURITY DEFINER SET search_path = public AS $$
    SELECT app_is_super_admin() OR (app_member_kind() = 'human' AND projects_has_right('project.admin'));
$$;

-- What app_roles answers, in one read.
CREATE OR REPLACE VIEW mcp_app_roles AS
SELECT r.role_key, r.name, r.description, r.capability, r.is_admin, r.sort_order,
       COALESCE((SELECT array_agg(rr.right_key ORDER BY h.sort_order) FROM projects_role_rights rr
                   JOIN projects_rights h ON h.right_key = rr.right_key WHERE rr.role_key = r.role_key), '{}') AS rights
FROM projects_roles r;

GRANT SELECT ON projects_rights, projects_roles, projects_role_rights, mcp_app_roles TO projects_rw, projects_records_ro;
GRANT EXECUTE ON FUNCTION projects_has_right(text), projects_is_admin() TO projects_rw, projects_records_ro, projects_activity_ro;

COMMIT;
