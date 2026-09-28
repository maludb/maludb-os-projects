-- 009: what people say and log on an issue — comments, attachments (on disk, the row is the fact),
-- worklogs (hours, owner's answer 3: a line on the issue, no timesheets) — and saved filters.
BEGIN;

CREATE TABLE comments (
    id           bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    issue_id     bigint NOT NULL REFERENCES issues(id) ON DELETE CASCADE,
    author_id    bigint REFERENCES members(id) ON DELETE SET NULL,
    body         text NOT NULL CHECK (length(body) BETWEEN 1 AND 20000),   -- markdown
    edited_at    timestamptz,
    deleted_at   timestamptz,                                              -- kept, shown as "(deleted)"; the log has the body
    created_at   timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX comments_issue_idx ON comments (issue_id, created_at);

CREATE TABLE attachments (
    id            bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    issue_id      bigint NOT NULL REFERENCES issues(id) ON DELETE CASCADE,
    file_name     text NOT NULL CHECK (length(file_name) BETWEEN 1 AND 255),
    mime_type     text NOT NULL,
    size_bytes    bigint NOT NULL CHECK (size_bytes >= 0),
    stored_path   text NOT NULL UNIQUE,                                    -- storage/attachments/<project>/<issue>/<id>-<name>, served by a gated handler
    sha256        text,
    uploaded_by   bigint REFERENCES members(id) ON DELETE SET NULL,
    created_at    timestamptz NOT NULL DEFAULT now(),
    deleted_at    timestamptz
);
CREATE INDEX attachments_issue_idx ON attachments (issue_id);

CREATE TABLE worklogs (
    id           bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    issue_id     bigint NOT NULL REFERENCES issues(id) ON DELETE CASCADE,
    member_id    bigint NOT NULL REFERENCES members(id) ON DELETE CASCADE,
    hours        numeric(6,2) NOT NULL CHECK (hours > 0 AND hours <= 24),
    worked_on    date NOT NULL DEFAULT current_date,
    note         text,
    created_at   timestamptz NOT NULL DEFAULT now(),
    updated_at   timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX worklogs_issue_idx ON worklogs (issue_id, worked_on);
CREATE INDEX worklogs_member_idx ON worklogs (member_id, worked_on);
CREATE TRIGGER worklogs_touch BEFORE UPDATE ON worklogs FOR EACH ROW EXECUTE FUNCTION touch_updated_at();

-- A named query a person keeps: the issues screen's filters as JSON, private or shared with a project.
CREATE TABLE saved_filters (
    id           bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    owner_id     bigint NOT NULL REFERENCES members(id) ON DELETE CASCADE,
    project_id   bigint REFERENCES projects(id) ON DELETE CASCADE,      -- NULL = across projects
    name         text NOT NULL CHECK (length(name) BETWEEN 1 AND 80),
    filter       jsonb NOT NULL DEFAULT '{}',
    is_shared    boolean NOT NULL DEFAULT false,
    created_at   timestamptz NOT NULL DEFAULT now(),
    updated_at   timestamptz NOT NULL DEFAULT now(),
    UNIQUE (owner_id, project_id, name)
);
CREATE TRIGGER saved_filters_touch BEFORE UPDATE ON saved_filters FOR EACH ROW EXECUTE FUNCTION touch_updated_at();

-- A comment, an attachment or a worklog is activity on its issue: the issue's updated_at moves, so the
-- hygiene rule (db/010, "stale") and "recently updated" see it.
CREATE OR REPLACE FUNCTION touch_issue_of_row() RETURNS trigger
    LANGUAGE plpgsql AS $$
BEGIN
    UPDATE issues SET updated_at = now() WHERE id = COALESCE(NEW.issue_id, OLD.issue_id);
    RETURN COALESCE(NEW, OLD);
END$$;
CREATE TRIGGER comments_touch_issue AFTER INSERT OR UPDATE OR DELETE ON comments FOR EACH ROW EXECUTE FUNCTION touch_issue_of_row();
CREATE TRIGGER attachments_touch_issue AFTER INSERT OR UPDATE OR DELETE ON attachments FOR EACH ROW EXECUTE FUNCTION touch_issue_of_row();
CREATE TRIGGER worklogs_touch_issue AFTER INSERT OR UPDATE OR DELETE ON worklogs FOR EACH ROW EXECUTE FUNCTION touch_issue_of_row();

GRANT SELECT, INSERT, UPDATE, DELETE ON comments, attachments, worklogs, saved_filters TO projects_rw;

COMMIT;
