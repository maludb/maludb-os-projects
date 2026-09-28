-- 007: epics (a large body of work issues roll up to) and sprints (the time-box a team commits to).
BEGIN;

CREATE TABLE epics (
    id            bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    project_id    bigint NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
    name          text NOT NULL CHECK (length(name) BETWEEN 1 AND 120),
    summary       text,
    colour        text NOT NULL DEFAULT 'primary' CHECK (colour ~ '^[a-z]{3,20}$'),
    status        text NOT NULL DEFAULT 'open' CHECK (status IN ('open', 'done', 'cancelled')),
    target_date   date,
    sort_order    integer NOT NULL DEFAULT 0,
    created_by    bigint REFERENCES members(id) ON DELETE SET NULL,
    created_at    timestamptz NOT NULL DEFAULT now(),
    updated_at    timestamptz NOT NULL DEFAULT now(),
    UNIQUE (project_id, name)
);
CREATE INDEX epics_project_idx ON epics (project_id, status);
CREATE TRIGGER epics_touch BEFORE UPDATE ON epics FOR EACH ROW EXECUTE FUNCTION touch_updated_at();

CREATE TABLE sprints (
    id                bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    project_id        bigint NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
    name              text NOT NULL CHECK (length(name) BETWEEN 1 AND 80),
    goal              text,
    state             text NOT NULL DEFAULT 'future' CHECK (state IN ('future', 'active', 'closed')),
    start_date        date,
    end_date          date,
    sort_order        integer NOT NULL DEFAULT 0,                   -- the order of future sprints in the backlog
    committed_points  numeric(8,1),                                 -- at start: the points in the sprint
    completed_points  numeric(8,1),                                 -- at close: the points done
    carried_points    numeric(8,1),                                 -- at close: the points carried over
    started_by        bigint REFERENCES members(id) ON DELETE SET NULL,
    started_at        timestamptz,
    closed_by         bigint REFERENCES members(id) ON DELETE SET NULL,
    closed_at         timestamptz,
    created_by        bigint REFERENCES members(id) ON DELETE SET NULL,
    created_at        timestamptz NOT NULL DEFAULT now(),
    updated_at        timestamptz NOT NULL DEFAULT now(),
    UNIQUE (project_id, name),
    CHECK (start_date IS NULL OR end_date IS NULL OR end_date >= start_date),
    CHECK ((state = 'active') = (started_at IS NOT NULL AND closed_at IS NULL)),
    CHECK ((state = 'closed') = (closed_at IS NOT NULL))
);
CREATE INDEX sprints_project_idx ON sprints (project_id, state);
CREATE UNIQUE INDEX sprints_one_active ON sprints (project_id) WHERE state = 'active';
CREATE TRIGGER sprints_touch BEFORE UPDATE ON sprints FOR EACH ROW EXECUTE FUNCTION touch_updated_at();

-- What entered and left a sprint after it started — the scope-change report and the burndown's
-- day-by-day scope line. Written by the issue triggers (db/010), never by hand.
CREATE TABLE sprint_scope_events (
    id           bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    sprint_id    bigint NOT NULL REFERENCES sprints(id) ON DELETE CASCADE,
    issue_id     bigint NOT NULL,                                   -- no FK: an issue deleted keeps its trace
    event        text NOT NULL CHECK (event IN ('added', 'removed', 'done', 'reopened', 'estimated')),
    points       numeric(6,1),                                      -- the issue's points at the time (estimated: the new value)
    by_member_id bigint REFERENCES members(id) ON DELETE SET NULL,
    occurred_at  timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX sprint_scope_events_sprint_idx ON sprint_scope_events (sprint_id, occurred_at);

GRANT SELECT, INSERT, UPDATE, DELETE ON epics, sprints, sprint_scope_events TO projects_rw;

COMMIT;
