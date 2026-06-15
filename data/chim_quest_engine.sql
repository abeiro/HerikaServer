DO $$
BEGIN
    IF EXISTS (
        SELECT 1
        FROM information_schema.tables
        WHERE table_schema = 'public'
          AND table_name = 'chim_quest_definitions'
    ) AND NOT EXISTS (
        SELECT 1
        FROM information_schema.tables
        WHERE table_schema = 'public'
          AND table_name = 'skyrim_quest_definitions'
    ) THEN
        ALTER TABLE public.chim_quest_definitions RENAME TO skyrim_quest_definitions;
    END IF;

    IF EXISTS (
        SELECT 1
        FROM information_schema.tables
        WHERE table_schema = 'public'
          AND table_name = 'chim_quest_instances'
    ) AND NOT EXISTS (
        SELECT 1
        FROM information_schema.tables
        WHERE table_schema = 'public'
          AND table_name = 'skyrim_quest_instances'
    ) THEN
        ALTER TABLE public.chim_quest_instances RENAME TO skyrim_quest_instances;
    END IF;

    IF EXISTS (
        SELECT 1
        FROM information_schema.tables
        WHERE table_schema = 'public'
          AND table_name = 'chim_quest_beat_state'
    ) AND NOT EXISTS (
        SELECT 1
        FROM information_schema.tables
        WHERE table_schema = 'public'
          AND table_name = 'skyrim_quest_beat_state'
    ) THEN
        ALTER TABLE public.chim_quest_beat_state RENAME TO skyrim_quest_beat_state;
    END IF;

    IF EXISTS (
        SELECT 1
        FROM information_schema.tables
        WHERE table_schema = 'public'
          AND table_name = 'chim_quest_events'
    ) AND NOT EXISTS (
        SELECT 1
        FROM information_schema.tables
        WHERE table_schema = 'public'
          AND table_name = 'skyrim_quest_events'
    ) THEN
        ALTER TABLE public.chim_quest_events RENAME TO skyrim_quest_events;
    END IF;

    IF EXISTS (
        SELECT 1
        FROM information_schema.tables
        WHERE table_schema = 'public'
          AND table_name = 'chim_quest_action_outbox'
    ) AND NOT EXISTS (
        SELECT 1
        FROM information_schema.tables
        WHERE table_schema = 'public'
          AND table_name = 'skyrim_quest_action_outbox'
    ) THEN
        ALTER TABLE public.chim_quest_action_outbox RENAME TO skyrim_quest_action_outbox;
    END IF;

    IF EXISTS (
        SELECT 1
        FROM pg_class
        WHERE relkind = 'i'
          AND relname = 'idx_chim_quest_definitions_editor_id'
    ) AND NOT EXISTS (
        SELECT 1
        FROM pg_class
        WHERE relkind = 'i'
          AND relname = 'idx_skyrim_quest_definitions_editor_id'
    ) THEN
        ALTER INDEX public.idx_chim_quest_definitions_editor_id RENAME TO idx_skyrim_quest_definitions_editor_id;
    END IF;

    IF EXISTS (
        SELECT 1
        FROM pg_class
        WHERE relkind = 'i'
          AND relname = 'idx_chim_quest_instances_run_state'
    ) AND NOT EXISTS (
        SELECT 1
        FROM pg_class
        WHERE relkind = 'i'
          AND relname = 'idx_skyrim_quest_instances_run_state'
    ) THEN
        ALTER INDEX public.idx_chim_quest_instances_run_state RENAME TO idx_skyrim_quest_instances_run_state;
    END IF;

    IF EXISTS (
        SELECT 1
        FROM pg_class
        WHERE relkind = 'i'
          AND relname = 'idx_chim_quest_events_type_created'
    ) AND NOT EXISTS (
        SELECT 1
        FROM pg_class
        WHERE relkind = 'i'
          AND relname = 'idx_skyrim_quest_events_type_created'
    ) THEN
        ALTER INDEX public.idx_chim_quest_events_type_created RENAME TO idx_skyrim_quest_events_type_created;
    END IF;

    IF EXISTS (
        SELECT 1
        FROM pg_class
        WHERE relkind = 'i'
          AND relname = 'idx_chim_quest_events_quest_created'
    ) AND NOT EXISTS (
        SELECT 1
        FROM pg_class
        WHERE relkind = 'i'
          AND relname = 'idx_skyrim_quest_events_quest_created'
    ) THEN
        ALTER INDEX public.idx_chim_quest_events_quest_created RENAME TO idx_skyrim_quest_events_quest_created;
    END IF;

    IF EXISTS (
        SELECT 1
        FROM pg_class
        WHERE relkind = 'i'
          AND relname = 'idx_chim_quest_action_outbox_status_created'
    ) AND NOT EXISTS (
        SELECT 1
        FROM pg_class
        WHERE relkind = 'i'
          AND relname = 'idx_skyrim_quest_action_outbox_status_created'
    ) THEN
        ALTER INDEX public.idx_chim_quest_action_outbox_status_created RENAME TO idx_skyrim_quest_action_outbox_status_created;
    END IF;

    IF EXISTS (
        SELECT 1
        FROM pg_trigger
        WHERE tgname = 'trg_chim_quest_definitions_updated_at'
    ) AND NOT EXISTS (
        SELECT 1
        FROM pg_trigger
        WHERE tgname = 'trg_skyrim_quest_definitions_updated_at'
    ) THEN
        ALTER TRIGGER trg_chim_quest_definitions_updated_at ON public.skyrim_quest_definitions RENAME TO trg_skyrim_quest_definitions_updated_at;
    END IF;

    IF EXISTS (
        SELECT 1
        FROM pg_trigger
        WHERE tgname = 'trg_chim_quest_instances_updated_at'
    ) AND NOT EXISTS (
        SELECT 1
        FROM pg_trigger
        WHERE tgname = 'trg_skyrim_quest_instances_updated_at'
    ) THEN
        ALTER TRIGGER trg_chim_quest_instances_updated_at ON public.skyrim_quest_instances RENAME TO trg_skyrim_quest_instances_updated_at;
    END IF;

    IF EXISTS (
        SELECT 1
        FROM pg_trigger
        WHERE tgname = 'trg_chim_quest_beat_state_updated_at'
    ) AND NOT EXISTS (
        SELECT 1
        FROM pg_trigger
        WHERE tgname = 'trg_skyrim_quest_beat_state_updated_at'
    ) THEN
        ALTER TRIGGER trg_chim_quest_beat_state_updated_at ON public.skyrim_quest_beat_state RENAME TO trg_skyrim_quest_beat_state_updated_at;
    END IF;

    IF EXISTS (
        SELECT 1
        FROM pg_trigger
        WHERE tgname = 'trg_chim_quest_action_outbox_updated_at'
    ) AND NOT EXISTS (
        SELECT 1
        FROM pg_trigger
        WHERE tgname = 'trg_skyrim_quest_action_outbox_updated_at'
    ) THEN
        ALTER TRIGGER trg_chim_quest_action_outbox_updated_at ON public.skyrim_quest_action_outbox RENAME TO trg_skyrim_quest_action_outbox_updated_at;
    END IF;
END $$;

CREATE TABLE IF NOT EXISTS public.skyrim_quest_definitions (
    quest_key text PRIMARY KEY,
    quest_editor_id text NOT NULL,
    title text NOT NULL,
    source_plugin text,
    source_form_id text,
    source_path text,
    skeleton jsonb NOT NULL DEFAULT '{}'::jsonb,
    active boolean NOT NULL DEFAULT true,
    created_at timestamp with time zone NOT NULL DEFAULT now(),
    updated_at timestamp with time zone NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS public.skyrim_quest_instances (
    quest_key text PRIMARY KEY REFERENCES public.skyrim_quest_definitions(quest_key) ON DELETE CASCADE,
    quest_editor_id text NOT NULL,
    run_state text NOT NULL DEFAULT 'inactive',
    current_stage integer,
    last_gamets bigint,
    state_json jsonb NOT NULL DEFAULT '{}'::jsonb,
    created_at timestamp with time zone NOT NULL DEFAULT now(),
    updated_at timestamp with time zone NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS public.skyrim_quest_beat_state (
    quest_key text NOT NULL REFERENCES public.skyrim_quest_instances(quest_key) ON DELETE CASCADE,
    beat_id text NOT NULL,
    fired boolean NOT NULL DEFAULT false,
    fired_order integer,
    fired_gamets bigint,
    evidence_json jsonb NOT NULL DEFAULT '{}'::jsonb,
    created_at timestamp with time zone NOT NULL DEFAULT now(),
    updated_at timestamp with time zone NOT NULL DEFAULT now(),
    PRIMARY KEY (quest_key, beat_id)
);

CREATE TABLE IF NOT EXISTS public.skyrim_quest_events (
    id bigserial PRIMARY KEY,
    quest_key text REFERENCES public.skyrim_quest_definitions(quest_key) ON DELETE SET NULL,
    event_type text NOT NULL,
    event_source text,
    npc_name text,
    location_name text,
    gamets bigint,
    payload_json jsonb NOT NULL DEFAULT '{}'::jsonb,
    created_at timestamp with time zone NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS public.skyrim_quest_action_outbox (
    id bigserial PRIMARY KEY,
    quest_key text NOT NULL REFERENCES public.skyrim_quest_instances(quest_key) ON DELETE CASCADE,
    beat_id text,
    action_type text NOT NULL,
    action_gamets bigint,
    payload_json jsonb NOT NULL DEFAULT '{}'::jsonb,
    status text NOT NULL DEFAULT 'pending',
    result_json jsonb NOT NULL DEFAULT '{}'::jsonb,
    created_at timestamp with time zone NOT NULL DEFAULT now(),
    updated_at timestamp with time zone NOT NULL DEFAULT now(),
    applied_at timestamp with time zone
);

ALTER TABLE public.skyrim_quest_instances
ADD COLUMN IF NOT EXISTS last_gamets bigint;

ALTER TABLE public.skyrim_quest_action_outbox
ADD COLUMN IF NOT EXISTS action_gamets bigint;

CREATE OR REPLACE FUNCTION public.chim_touch_updated_at()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = now();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_trigger
        WHERE tgname = 'trg_skyrim_quest_definitions_updated_at'
    ) THEN
        CREATE TRIGGER trg_skyrim_quest_definitions_updated_at
        BEFORE UPDATE ON public.skyrim_quest_definitions
        FOR EACH ROW
        EXECUTE FUNCTION public.chim_touch_updated_at();
    END IF;

    IF NOT EXISTS (
        SELECT 1
        FROM pg_trigger
        WHERE tgname = 'trg_skyrim_quest_instances_updated_at'
    ) THEN
        CREATE TRIGGER trg_skyrim_quest_instances_updated_at
        BEFORE UPDATE ON public.skyrim_quest_instances
        FOR EACH ROW
        EXECUTE FUNCTION public.chim_touch_updated_at();
    END IF;

    IF NOT EXISTS (
        SELECT 1
        FROM pg_trigger
        WHERE tgname = 'trg_skyrim_quest_beat_state_updated_at'
    ) THEN
        CREATE TRIGGER trg_skyrim_quest_beat_state_updated_at
        BEFORE UPDATE ON public.skyrim_quest_beat_state
        FOR EACH ROW
        EXECUTE FUNCTION public.chim_touch_updated_at();
    END IF;

    IF NOT EXISTS (
        SELECT 1
        FROM pg_trigger
        WHERE tgname = 'trg_skyrim_quest_action_outbox_updated_at'
    ) THEN
        CREATE TRIGGER trg_skyrim_quest_action_outbox_updated_at
        BEFORE UPDATE ON public.skyrim_quest_action_outbox
        FOR EACH ROW
        EXECUTE FUNCTION public.chim_touch_updated_at();
    END IF;
END $$;

CREATE INDEX IF NOT EXISTS idx_skyrim_quest_definitions_editor_id
ON public.skyrim_quest_definitions (quest_editor_id);

CREATE INDEX IF NOT EXISTS idx_skyrim_quest_instances_run_state
ON public.skyrim_quest_instances (run_state);

CREATE INDEX IF NOT EXISTS idx_skyrim_quest_events_type_created
ON public.skyrim_quest_events (event_type, created_at DESC);

CREATE INDEX IF NOT EXISTS idx_skyrim_quest_events_quest_created
ON public.skyrim_quest_events (quest_key, created_at DESC);

CREATE INDEX IF NOT EXISTS idx_skyrim_quest_events_gamets
ON public.skyrim_quest_events (gamets DESC);

CREATE INDEX IF NOT EXISTS idx_skyrim_quest_action_outbox_status_created
ON public.skyrim_quest_action_outbox (status, created_at ASC);

CREATE INDEX IF NOT EXISTS idx_skyrim_quest_action_outbox_gamets
ON public.skyrim_quest_action_outbox (action_gamets DESC);
