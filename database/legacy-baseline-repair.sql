-- Legacy process state can outlive a recreated database, and the legacy version
-- table stores only the newest marker for a feature. Reassert the two structures
-- known to be skipped by those behaviors before accepting the baseline.
CREATE TABLE IF NOT EXISTS public.visual_context (
    id BIGSERIAL PRIMARY KEY,
    subject_type TEXT NOT NULL DEFAULT 'scene',
    subject_key TEXT NOT NULL,
    subject_name TEXT NOT NULL DEFAULT '',
    plugin TEXT NOT NULL DEFAULT '',
    baseid TEXT NOT NULL DEFAULT '',
    refid TEXT NOT NULL DEFAULT '',
    cell_id TEXT NOT NULL DEFAULT '',
    location_name TEXT NOT NULL DEFAULT '',
    image_path TEXT NOT NULL DEFAULT '',
    image_sha256 TEXT NOT NULL DEFAULT '',
    description TEXT NOT NULL DEFAULT '',
    perspective TEXT NOT NULL DEFAULT 'first_person',
    provider TEXT NOT NULL DEFAULT '',
    model TEXT NOT NULL DEFAULT '',
    metadata JSONB NOT NULL DEFAULT '{}'::jsonb,
    locked BOOLEAN NOT NULL DEFAULT FALSE,
    active BOOLEAN NOT NULL DEFAULT TRUE,
    user_edited BOOLEAN NOT NULL DEFAULT FALSE,
    captured_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS visual_context_location_idx
    ON public.visual_context (LOWER(location_name), active, captured_at DESC);
CREATE INDEX IF NOT EXISTS visual_context_subject_idx
    ON public.visual_context (subject_type, subject_key, active, captured_at DESC);
CREATE INDEX IF NOT EXISTS visual_context_image_idx
    ON public.visual_context (image_sha256);

CREATE UNIQUE INDEX IF NOT EXISTS idx_prompts_prompt_key_unique
    ON public.prompts (prompt_key);

-- ALTER TYPE is blocked while profile eventlog views depend on the column, even
-- though the views remain semantically identical. Capture and rebuild all of
-- those views atomically when needed.
DO $$
DECLARE
    dependent_view record;
BEGIN
    IF EXISTS (
        SELECT 1
        FROM information_schema.columns
        WHERE table_schema = 'public'
          AND table_name = 'eventlog'
          AND column_name = 'sess'
          AND udt_name <> 'text'
    ) THEN
        CREATE TEMP TABLE migration_eventlog_sess_views ON COMMIT DROP AS
        SELECT DISTINCT
               view_namespace.nspname AS schema_name,
               dependent.relname AS view_name,
               pg_get_viewdef(dependent.oid, true) AS definition,
               pg_get_userbyid(dependent.relowner) AS owner_name,
               dependent.relacl,
               dependent.reloptions
        FROM pg_depend dependency
        JOIN pg_rewrite rewrite ON dependency.objid = rewrite.oid
        JOIN pg_class dependent ON rewrite.ev_class = dependent.oid
        JOIN pg_namespace view_namespace ON view_namespace.oid = dependent.relnamespace
        JOIN pg_attribute source_column
          ON source_column.attrelid = dependency.refobjid
         AND source_column.attnum = dependency.refobjsubid
        WHERE dependency.refobjid = 'public.eventlog'::regclass
          AND source_column.attname = 'sess'
          AND dependent.relkind = 'v';

        IF EXISTS (
            SELECT 1 FROM migration_eventlog_sess_views
            WHERE relacl IS NOT NULL OR reloptions IS NOT NULL
        ) THEN
            RAISE EXCEPTION 'eventlog sess migration found a view with custom grants or options; repair it manually';
        END IF;

        FOR dependent_view IN SELECT * FROM migration_eventlog_sess_views LOOP
            EXECUTE format('DROP VIEW %I.%I', dependent_view.schema_name, dependent_view.view_name);
        END LOOP;

        ALTER TABLE public.eventlog ALTER COLUMN sess TYPE text;

        FOR dependent_view IN SELECT * FROM migration_eventlog_sess_views LOOP
            EXECUTE format(
                'CREATE VIEW %I.%I AS %s',
                dependent_view.schema_name,
                dependent_view.view_name,
                dependent_view.definition
            );
            EXECUTE format(
                'ALTER VIEW %I.%I OWNER TO %I',
                dependent_view.schema_name,
                dependent_view.view_name,
                dependent_view.owner_name
            );
        END LOOP;
    END IF;
END
$$;

INSERT INTO public.database_versioning (tablename, version)
VALUES ('eventlog_session_payload', 20260807001)
ON CONFLICT (tablename) DO UPDATE
SET version = GREATEST(public.database_versioning.version, EXCLUDED.version);
