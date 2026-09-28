-- 001: roles, extensions, the acting member, and the directory mirror.
--
-- Projects is an application from us beside the Business OS kernel (maludb-os-integration 0.4.1).
-- It owns WORK (projects, issues, sprints, boards); the kernel owns the DIRECTORY. The identity tables below are a MIRROR of
-- the kernel's directory with the kernel's ids -- written only from a hand-off token's claims
-- or the directory change feed, never generated here. There is no password and no account.
--
-- Run in order as postgres on an empty database named <tenant>_projects:
--   sudo -u postgres psql -v ON_ERROR_STOP=1 -d subello_projects -f db/001_roles_and_identity.sql
-- The installer sets the three role passwords afterwards (ALTER ROLE ... PASSWORD).
BEGIN;

CREATE EXTENSION IF NOT EXISTS citext;
CREATE EXTENSION IF NOT EXISTS pg_trgm;

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'projects_rw') THEN
        CREATE ROLE projects_rw LOGIN;                 -- PHP: the only writer
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'projects_records_ro') THEN
        CREATE ROLE projects_records_ro LOGIN;         -- records MCP: mcp_* views only
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'projects_activity_ro') THEN
        CREATE ROLE projects_activity_ro LOGIN;        -- activity MCP: mcp_activity_* views only
    END IF;
END$$;

GRANT USAGE ON SCHEMA public TO projects_rw, projects_records_ro, projects_activity_ro;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT USAGE, SELECT ON SEQUENCES TO projects_rw;

-- ---------------------------------------------------------------------------------------------
-- The acting member. PHP sets it connection-wide from the session (or from a verified action
-- token); the MCP servers set it transaction-locally from the verified bearer. Unset = anonymous
-- and every rule below denies.
-- ---------------------------------------------------------------------------------------------
CREATE OR REPLACE FUNCTION app_current_member_id() RETURNS bigint
    LANGUAGE sql STABLE AS $$
    SELECT NULLIF(current_setting('app.member_id', true), '')::bigint;
$$;

CREATE OR REPLACE FUNCTION touch_updated_at() RETURNS trigger
    LANGUAGE plpgsql AS $$
BEGIN
    NEW.updated_at := now();
    RETURN NEW;
END$$;

-- ---------------------------------------------------------------------------------------------
-- The mirror (sign-on-and-directory.md §3-4). Columns are the directory's own facts as the
-- change feed carries them (os.directory/1). Projects adds its own facts in its own tables keyed by
-- member_id (project_team, issues and the rest), never here.
-- ---------------------------------------------------------------------------------------------
CREATE TABLE members (
    id              bigint PRIMARY KEY,                       -- the kernel's member id
    member_kind     text NOT NULL CHECK (member_kind IN ('human', 'agent')),
    display_name    text NOT NULL,
    email           citext,
    business_role   text NOT NULL CHECK (business_role IN ('super_admin', 'dept_admin', 'user')),
    is_external     boolean NOT NULL DEFAULT false,
    status          text NOT NULL CHECK (status IN ('active', 'inactive')),
    capability      text CHECK (capability IN ('read', 'write', 'admin')),  -- this application's grant; NULL = none
    job_title       text,
    phone           text,
    timezone        text NOT NULL DEFAULT 'UTC',
    directory_updated_at timestamptz,                         -- the kernel's updated_at, for the feed
    synced_at       timestamptz NOT NULL DEFAULT now(),
    created_at      timestamptz NOT NULL DEFAULT now(),
    updated_at      timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX members_name_trgm_idx ON members USING gin (display_name gin_trgm_ops);
CREATE INDEX members_status_idx ON members (status, member_kind);
CREATE TRIGGER members_touch BEFORE UPDATE ON members FOR EACH ROW EXECUTE FUNCTION touch_updated_at();

CREATE TABLE departments (
    id                 bigint PRIMARY KEY,                    -- the kernel's department id
    name               text NOT NULL,
    description        text,
    parent_id          bigint REFERENCES departments(id) ON DELETE SET NULL,
    manager_member_id  bigint REFERENCES members(id) ON DELETE SET NULL,
    is_system          boolean NOT NULL DEFAULT false,
    system_key         text,                                  -- front_office | hr | accounting | audit
    archived_at        timestamptz,
    directory_updated_at timestamptz,
    synced_at          timestamptz NOT NULL DEFAULT now(),
    created_at         timestamptz NOT NULL DEFAULT now(),
    updated_at         timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX departments_parent_idx  ON departments (parent_id);
CREATE INDEX departments_manager_idx ON departments (manager_member_id);
CREATE TRIGGER departments_touch BEFORE UPDATE ON departments FOR EACH ROW EXECUTE FUNCTION touch_updated_at();

CREATE TABLE department_members (
    member_id       bigint NOT NULL REFERENCES members(id) ON DELETE CASCADE,
    department_id   bigint NOT NULL REFERENCES departments(id) ON DELETE CASCADE,
    is_admin        boolean NOT NULL DEFAULT false,
    is_primary      boolean NOT NULL DEFAULT false,
    joined_at       timestamptz,
    left_at         timestamptz,                              -- dated, never erased (the feed's rule)
    synced_at       timestamptz NOT NULL DEFAULT now(),
    PRIMARY KEY (member_id, department_id)
);
CREATE INDEX department_members_dept_idx ON department_members (department_id) WHERE left_at IS NULL;

-- The change-feed checkpoint (one row), advanced under an advisory lock by bin/directory_sync.php.
CREATE TABLE directory_sync_state (
    id           smallint PRIMARY KEY DEFAULT 1 CHECK (id = 1),
    next_cursor  text,                                        -- the feed's `next`; NULL = never synced
    full_at      timestamptz,                                 -- last full refresh
    last_run_at  timestamptz,
    last_error   text,
    updated_at   timestamptz NOT NULL DEFAULT now()
);
INSERT INTO directory_sync_state (id) VALUES (1) ON CONFLICT DO NOTHING;

-- Hand-off tokens are single use: the nonce is kept until it expires (sign-on-and-directory.md §1).
CREATE TABLE sso_nonces (
    nonce       text PRIMARY KEY,
    member_id   bigint NOT NULL,
    expires_at  timestamptz NOT NULL,
    created_at  timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX sso_nonces_expires_idx ON sso_nonces (expires_at);

-- Every session Projects opens, so the kernel's sign-out notice can end all of a member's at once (§2).
CREATE TABLE member_sessions (
    session_hash  text PRIMARY KEY,                           -- sha256 of the PHP session id
    member_id     bigint NOT NULL REFERENCES members(id) ON DELETE CASCADE,
    created_at    timestamptz NOT NULL DEFAULT now(),
    last_seen_at  timestamptz NOT NULL DEFAULT now(),
    ended_at      timestamptz,
    ended_by      text CHECK (ended_by IN ('member', 'kernel', 'expired', 'directory'))
);
CREATE INDEX member_sessions_member_idx ON member_sessions (member_id) WHERE ended_at IS NULL;

-- ---------------------------------------------------------------------------------------------
-- Who is asking, and what they are. Every rule in Projects is one of these; SQL enforces them in the
-- mcp_* views (db/009) and PHP mirrors them in its gates. A caller with no mirror row is nobody.
-- ---------------------------------------------------------------------------------------------
CREATE OR REPLACE FUNCTION app_member_kind() RETURNS text
    LANGUAGE sql STABLE SECURITY DEFINER SET search_path = public AS $$
    SELECT member_kind FROM members WHERE id = app_current_member_id();
$$;

-- Active in the directory AND admitted to Projects: a human needs a live capability; an agent is
-- admitted by its access grant, which the kernel expresses as a capability too.
CREATE OR REPLACE FUNCTION app_is_active_member() RETURNS boolean
    LANGUAGE sql STABLE SECURITY DEFINER SET search_path = public AS $$
    SELECT EXISTS (SELECT 1 FROM members m
                    WHERE m.id = app_current_member_id()
                      AND m.status = 'active' AND m.capability IS NOT NULL);
$$;

CREATE OR REPLACE FUNCTION app_is_super_admin() RETURNS boolean
    LANGUAGE sql STABLE SECURITY DEFINER SET search_path = public AS $$
    SELECT EXISTS (SELECT 1 FROM members m
                    WHERE m.id = app_current_member_id() AND m.status = 'active'
                      AND m.member_kind = 'human' AND m.business_role = 'super_admin');
$$;

-- Someone who works here: an active, non-external human with a capability.
CREATE OR REPLACE FUNCTION app_is_insider() RETURNS boolean
    LANGUAGE sql STABLE SECURITY DEFINER SET search_path = public AS $$
    SELECT EXISTS (SELECT 1 FROM members m
                    WHERE m.id = app_current_member_id() AND m.status = 'active'
                      AND m.member_kind = 'human' AND NOT m.is_external
                      AND m.capability IS NOT NULL);
$$;

CREATE OR REPLACE FUNCTION app_capability() RETURNS text
    LANGUAGE sql STABLE SECURITY DEFINER SET search_path = public AS $$
    SELECT capability FROM members WHERE id = app_current_member_id() AND status = 'active';
$$;

-- Projects admins: the people who run this application. A super-admin, or a human holding the
-- application at capability 'admin' (granted in the kernel, per person or per department).
CREATE OR REPLACE FUNCTION projects_is_admin() RETURNS boolean
    LANGUAGE sql STABLE SECURITY DEFINER SET search_path = public AS $$
    SELECT app_is_super_admin()
        OR EXISTS (SELECT 1 FROM members m
                    WHERE m.id = app_current_member_id() AND m.status = 'active'
                      AND m.member_kind = 'human' AND m.capability = 'admin');
$$;

-- The departments the caller administers: flagged admin of, or the named manager of.
CREATE OR REPLACE FUNCTION app_admin_department_ids() RETURNS SETOF bigint
    LANGUAGE sql STABLE SECURITY DEFINER SET search_path = public AS $$
    SELECT dm.department_id FROM department_members dm
     WHERE dm.member_id = app_current_member_id() AND dm.is_admin AND dm.left_at IS NULL
    UNION
    SELECT d.id FROM departments d
     WHERE d.manager_member_id = app_current_member_id() AND d.archived_at IS NULL;
$$;

CREATE OR REPLACE FUNCTION app_is_dept_admin() RETURNS boolean
    LANGUAGE sql STABLE SECURITY DEFINER SET search_path = public AS $$
    SELECT app_is_insider() AND EXISTS (SELECT 1 FROM app_admin_department_ids());
$$;

CREATE OR REPLACE FUNCTION app_is_admin_of(p_department_id bigint) RETURNS boolean
    LANGUAGE sql STABLE SECURITY DEFINER SET search_path = public AS $$
    SELECT app_is_super_admin()
        OR (app_is_insider() AND p_department_id IN (SELECT app_admin_department_ids()));
$$;

-- The people rule: the super-admin, or an administrator of a department the person is in.
CREATE OR REPLACE FUNCTION app_can_admin_member(p_member_id bigint) RETURNS boolean
    LANGUAGE sql STABLE SECURITY DEFINER SET search_path = public AS $$
    SELECT app_is_super_admin()
        OR (app_is_insider() AND EXISTS (
                SELECT 1 FROM department_members dm
                 WHERE dm.member_id = p_member_id AND dm.left_at IS NULL
                   AND dm.department_id IN (SELECT app_admin_department_ids())));
$$;

GRANT EXECUTE ON FUNCTION app_current_member_id(), app_member_kind(), app_is_active_member(),
    app_is_super_admin(), app_is_insider(), app_capability(), projects_is_admin(),
    app_admin_department_ids(), app_is_dept_admin(), app_is_admin_of(bigint),
    app_can_admin_member(bigint)
TO projects_rw, projects_records_ro, projects_activity_ro;

-- PHP is the only writer of the mirror and the session tables; the read roles see none of it
-- directly (the mcp_* views in db/009 are their only window).
GRANT SELECT, INSERT, UPDATE, DELETE ON members, departments, department_members,
    directory_sync_state, sso_nonces, member_sessions TO projects_rw;

COMMIT;
