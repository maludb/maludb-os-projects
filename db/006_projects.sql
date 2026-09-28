-- 006: projects — the body of work, its team, its workflow, its board, its labels and components
-- (the design §4). The department is a property of a project (who owns it, whose admin administers it),
-- not a wall; scope_id is reserved NULL for a scoped mode the design defers.
BEGIN;

CREATE TABLE projects (
    id               bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    key              text NOT NULL UNIQUE CHECK (key ~ '^[A-Z][A-Z0-9]{1,9}$'),   -- PROJ; the prefix of every issue key
    name             text NOT NULL CHECK (length(name) BETWEEN 1 AND 120),
    description      text,
    department_id    bigint REFERENCES departments(id) ON DELETE SET NULL,
    lead_member_id   bigint REFERENCES members(id) ON DELETE SET NULL,
    visibility       text NOT NULL DEFAULT 'open' CHECK (visibility IN ('open', 'private')),
    status           text NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'archived')),
    board_mode       text NOT NULL DEFAULT 'scrum' CHECK (board_mode IN ('scrum', 'kanban')),   -- scrum: the active sprint; kanban: the whole backlog
    swimlane         text NOT NULL DEFAULT 'none' CHECK (swimlane IN ('none', 'assignee', 'epic')),
    issue_counter    integer NOT NULL DEFAULT 0,                    -- the last issue number handed out
    customer         text,                                          -- free text: the kernel has no CRM
    scope_id         bigint,                                        -- reserved (design §3), always NULL for now
    archived_at      timestamptz,
    archived_by      bigint REFERENCES members(id) ON DELETE SET NULL,
    search_tsv       tsvector GENERATED ALWAYS AS (to_tsvector('simple', coalesce(key, '') || ' ' || coalesce(name, '') || ' ' || coalesce(description, ''))) STORED,
    created_by       bigint REFERENCES members(id) ON DELETE SET NULL,
    created_at       timestamptz NOT NULL DEFAULT now(),
    updated_at       timestamptz NOT NULL DEFAULT now(),
    CHECK ((status = 'archived') = (archived_at IS NOT NULL))
);
CREATE INDEX projects_department_idx ON projects (department_id);
CREATE INDEX projects_status_idx ON projects (status);
CREATE INDEX projects_search_idx ON projects USING gin (search_tsv);
CREATE TRIGGER projects_touch BEFORE UPDATE ON projects FOR EACH ROW EXECUTE FUNCTION touch_updated_at();

-- The team: who works on the project, in what project role. A human or an agent.
CREATE TABLE project_team (
    project_id     bigint NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
    member_id      bigint NOT NULL REFERENCES members(id) ON DELETE CASCADE,
    project_role   text NOT NULL DEFAULT 'member' CHECK (project_role IN ('lead', 'product_owner', 'scrum_master', 'member', 'viewer')),
    added_by       bigint REFERENCES members(id) ON DELETE SET NULL,
    added_at       timestamptz NOT NULL DEFAULT now(),
    removed_at     timestamptz,                                     -- dated, never erased
    PRIMARY KEY (project_id, member_id)
);
CREATE INDEX project_team_member_idx ON project_team (member_id) WHERE removed_at IS NULL;

-- The workflow: one per project — its statuses (each in a category the board and the reports read)
-- and the allowed moves. Seeded on project create by projects_seed_workflow() (db/010).
CREATE TABLE workflow_statuses (
    id           bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    project_id   bigint NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
    name         text NOT NULL CHECK (length(name) BETWEEN 1 AND 40),
    category     text NOT NULL CHECK (category IN ('todo', 'in_progress', 'done')),
    sort_order   integer NOT NULL DEFAULT 0,
    is_initial   boolean NOT NULL DEFAULT false,                    -- where a new issue lands (exactly one per project)
    is_review    boolean NOT NULL DEFAULT false,                    -- "in review for me" (question 15) keys on this, not on a name
    archived_at  timestamptz,
    UNIQUE (project_id, name)
);
CREATE UNIQUE INDEX workflow_statuses_one_initial ON workflow_statuses (project_id) WHERE is_initial AND archived_at IS NULL;
CREATE TABLE workflow_transitions (
    project_id      bigint NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
    from_status_id  bigint NOT NULL REFERENCES workflow_statuses(id) ON DELETE CASCADE,
    to_status_id    bigint NOT NULL REFERENCES workflow_statuses(id) ON DELETE CASCADE,
    PRIMARY KEY (from_status_id, to_status_id),
    CHECK (from_status_id <> to_status_id)
);

-- The board: columns, each mapped to one or more statuses, with an optional WIP limit.
CREATE TABLE board_columns (
    id           bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    project_id   bigint NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
    name         text NOT NULL CHECK (length(name) BETWEEN 1 AND 40),
    sort_order   integer NOT NULL DEFAULT 0,
    wip_limit    smallint CHECK (wip_limit IS NULL OR wip_limit > 0),
    UNIQUE (project_id, name)
);
CREATE TABLE board_column_statuses (
    column_id    bigint NOT NULL REFERENCES board_columns(id) ON DELETE CASCADE,
    status_id    bigint NOT NULL REFERENCES workflow_statuses(id) ON DELETE CASCADE,
    PRIMARY KEY (column_id, status_id)
);
CREATE UNIQUE INDEX board_column_statuses_one_column ON board_column_statuses (status_id);   -- a status sits in one column

-- Labels (free tags) and components (a project's named parts, with an optional default assignee).
CREATE TABLE labels (
    id           bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    project_id   bigint NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
    name         text NOT NULL CHECK (name ~ '^[a-z0-9][a-z0-9_-]{0,39}$'),
    colour       text NOT NULL DEFAULT 'secondary' CHECK (colour ~ '^[a-z]{3,20}$'),
    archived_at  timestamptz,
    UNIQUE (project_id, name)
);
CREATE TABLE components (
    id                  bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    project_id          bigint NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
    name                text NOT NULL CHECK (length(name) BETWEEN 1 AND 60),
    description         text,
    default_assignee_id bigint REFERENCES members(id) ON DELETE SET NULL,
    archived_at         timestamptz,
    UNIQUE (project_id, name)
);

GRANT SELECT, INSERT, UPDATE, DELETE ON projects, project_team, workflow_statuses, workflow_transitions,
    board_columns, board_column_statuses, labels, components TO projects_rw;

COMMIT;
