-- 004: the business's Projects settings (one row) — the defaults every new project starts from.
BEGIN;

CREATE TABLE projects_settings (
    id                    smallint PRIMARY KEY DEFAULT 1 CHECK (id = 1),
    timezone              text NOT NULL DEFAULT 'UTC',
    sprint_length_days    smallint NOT NULL DEFAULT 14 CHECK (sprint_length_days BETWEEN 1 AND 60),
    point_scale           smallint[] NOT NULL DEFAULT '{1,2,3,5,8,13,21}',   -- the story points a picker offers
    stale_after_days      smallint NOT NULL DEFAULT 5 CHECK (stale_after_days BETWEEN 1 AND 90),   -- hygiene: no change for this long
    attachment_max_bytes  bigint NOT NULL DEFAULT 10485760 CHECK (attachment_max_bytes > 0),
    default_visibility    text NOT NULL DEFAULT 'open' CHECK (default_visibility IN ('open', 'private')),
    updated_by            bigint REFERENCES members(id) ON DELETE SET NULL,
    created_at            timestamptz NOT NULL DEFAULT now(),
    updated_at            timestamptz NOT NULL DEFAULT now()
);
INSERT INTO projects_settings (id) VALUES (1) ON CONFLICT DO NOTHING;
CREATE TRIGGER projects_settings_touch BEFORE UPDATE ON projects_settings FOR EACH ROW EXECUTE FUNCTION touch_updated_at();
GRANT SELECT, UPDATE ON projects_settings TO projects_rw;

COMMIT;
