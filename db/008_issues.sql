-- 008: issues — the unit of work — and what hangs on them: aliases (a key is permanent), labels,
-- links, watchers, checklists. A subtask is an issue with a parent. Rank orders the backlog:
-- a numeric with gaps, so a move is one UPDATE (db/010 projects_rank_between).
BEGIN;

CREATE TABLE issues (
    id                 bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    project_id         bigint NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
    number             integer NOT NULL,                             -- the N of PROJ-N, from projects.issue_counter
    key                text NOT NULL UNIQUE,                         -- PROJ-N, permanent (moved: a new key, the old an alias)
    type               text NOT NULL DEFAULT 'task' CHECK (type IN ('story', 'task', 'bug', 'subtask')),
    summary            text NOT NULL CHECK (length(summary) BETWEEN 1 AND 255),
    description        text,                                         -- markdown
    status_id          bigint NOT NULL REFERENCES workflow_statuses(id),
    resolution         text CHECK (resolution IN ('done', 'wont_do', 'duplicate', 'cannot_reproduce')),
    priority           text NOT NULL DEFAULT 'medium' CHECK (priority IN ('lowest', 'low', 'medium', 'high', 'highest')),
    story_points       numeric(6,1) CHECK (story_points IS NULL OR story_points >= 0),
    estimate_hours     numeric(7,2) CHECK (estimate_hours IS NULL OR estimate_hours >= 0),
    assignee_member_id bigint REFERENCES members(id) ON DELETE SET NULL,   -- a human or an agent
    reporter_member_id bigint REFERENCES members(id) ON DELETE SET NULL,
    parent_issue_id    bigint REFERENCES issues(id) ON DELETE CASCADE,    -- a subtask's parent
    epic_id            bigint REFERENCES epics(id) ON DELETE SET NULL,
    sprint_id          bigint REFERENCES sprints(id) ON DELETE SET NULL,  -- NULL = the backlog
    component_id       bigint REFERENCES components(id) ON DELETE SET NULL,
    rank               numeric(20,6) NOT NULL DEFAULT 0,
    due_date           date,
    started_at         timestamptz,                                  -- first move into in_progress (cycle time)
    resolved_at        timestamptz,                                  -- last move into done
    accepted_by        bigint REFERENCES members(id) ON DELETE SET NULL,   -- the product owner's word (issue.accept)
    accepted_at        timestamptz,
    search_tsv         tsvector GENERATED ALWAYS AS (to_tsvector('simple', coalesce(key, '') || ' ' || coalesce(summary, '') || ' ' || coalesce(description, ''))) STORED,
    created_by         bigint REFERENCES members(id) ON DELETE SET NULL,
    created_at         timestamptz NOT NULL DEFAULT now(),
    updated_at         timestamptz NOT NULL DEFAULT now(),
    UNIQUE (project_id, number),
    CHECK ((type = 'subtask') = (parent_issue_id IS NOT NULL))
);
CREATE INDEX issues_project_status_idx ON issues (project_id, status_id);
CREATE INDEX issues_sprint_idx ON issues (sprint_id) WHERE sprint_id IS NOT NULL;
CREATE INDEX issues_backlog_rank_idx ON issues (project_id, rank) WHERE sprint_id IS NULL;
CREATE INDEX issues_assignee_idx ON issues (assignee_member_id) WHERE resolved_at IS NULL;
CREATE INDEX issues_epic_idx ON issues (epic_id);
CREATE INDEX issues_parent_idx ON issues (parent_issue_id);
CREATE INDEX issues_search_idx ON issues USING gin (search_tsv);
CREATE INDEX issues_updated_idx ON issues (updated_at);
CREATE TRIGGER issues_touch BEFORE UPDATE ON issues FOR EACH ROW EXECUTE FUNCTION touch_updated_at();

-- A key is permanent: an issue moved between projects gets a new key and keeps the old ones here.
CREATE TABLE issue_aliases (
    old_key     text PRIMARY KEY,
    issue_id    bigint NOT NULL REFERENCES issues(id) ON DELETE CASCADE,
    moved_at    timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE issue_labels (
    issue_id   bigint NOT NULL REFERENCES issues(id) ON DELETE CASCADE,
    label_id   bigint NOT NULL REFERENCES labels(id) ON DELETE CASCADE,
    PRIMARY KEY (issue_id, label_id)
);

-- A typed relation. blocks is a dependency: a cycle is refused (db/010 projects_link_would_cycle).
CREATE TABLE issue_links (
    id             bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    from_issue_id  bigint NOT NULL REFERENCES issues(id) ON DELETE CASCADE,
    to_issue_id    bigint NOT NULL REFERENCES issues(id) ON DELETE CASCADE,
    kind           text NOT NULL CHECK (kind IN ('blocks', 'relates', 'duplicates')),   -- from blocks to; from relates to; from duplicates to
    created_by     bigint REFERENCES members(id) ON DELETE SET NULL,
    created_at     timestamptz NOT NULL DEFAULT now(),
    UNIQUE (from_issue_id, to_issue_id, kind),
    CHECK (from_issue_id <> to_issue_id)
);
CREATE INDEX issue_links_to_idx ON issue_links (to_issue_id);

CREATE TABLE issue_watchers (
    issue_id   bigint NOT NULL REFERENCES issues(id) ON DELETE CASCADE,
    member_id  bigint NOT NULL REFERENCES members(id) ON DELETE CASCADE,
    added_at   timestamptz NOT NULL DEFAULT now(),
    PRIMARY KEY (issue_id, member_id)
);

CREATE TABLE checklist_items (
    id          bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    issue_id    bigint NOT NULL REFERENCES issues(id) ON DELETE CASCADE,
    text        text NOT NULL CHECK (length(text) BETWEEN 1 AND 500),
    is_done     boolean NOT NULL DEFAULT false,
    sort_order  integer NOT NULL DEFAULT 0,
    done_by     bigint REFERENCES members(id) ON DELETE SET NULL,
    done_at     timestamptz,
    created_by  bigint REFERENCES members(id) ON DELETE SET NULL,
    created_at  timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX checklist_items_issue_idx ON checklist_items (issue_id, sort_order);

GRANT SELECT, INSERT, UPDATE, DELETE ON issues, issue_aliases, issue_labels, issue_links, issue_watchers, checklist_items TO projects_rw;

COMMIT;
