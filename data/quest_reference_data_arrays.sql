ALTER TABLE IF EXISTS public.quest_item_types
    ADD COLUMN IF NOT EXISTS formids_json jsonb;
ALTER TABLE IF EXISTS public.quest_item_types
    ALTER COLUMN formid SET DEFAULT -1;

ALTER TABLE IF EXISTS public.quest_npc_templates
    ADD COLUMN IF NOT EXISTS formids_json jsonb;
ALTER TABLE IF EXISTS public.quest_npc_templates
    ALTER COLUMN formid SET DEFAULT -1;

ALTER TABLE IF EXISTS public.quest_npc_own_templates
    ADD COLUMN IF NOT EXISTS formids_json jsonb;
ALTER TABLE IF EXISTS public.quest_npc_own_templates
    ALTER COLUMN formid SET DEFAULT -1;

ALTER TABLE IF EXISTS public.quest_outfits
    ADD COLUMN IF NOT EXISTS formids_json jsonb;
ALTER TABLE IF EXISTS public.quest_outfits
    ALTER COLUMN formid SET DEFAULT -1;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'quest_item_types_formids_json_is_array'
    ) THEN
        ALTER TABLE public.quest_item_types
            ADD CONSTRAINT quest_item_types_formids_json_is_array
            CHECK (formids_json IS NULL OR jsonb_typeof(formids_json) = 'array');
    END IF;
END $$;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'quest_npc_templates_formids_json_is_array'
    ) THEN
        ALTER TABLE public.quest_npc_templates
            ADD CONSTRAINT quest_npc_templates_formids_json_is_array
            CHECK (formids_json IS NULL OR jsonb_typeof(formids_json) = 'array');
    END IF;
END $$;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'quest_npc_own_templates_formids_json_is_array'
    ) THEN
        ALTER TABLE public.quest_npc_own_templates
            ADD CONSTRAINT quest_npc_own_templates_formids_json_is_array
            CHECK (formids_json IS NULL OR jsonb_typeof(formids_json) = 'array');
    END IF;
END $$;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'quest_outfits_formids_json_is_array'
    ) THEN
        ALTER TABLE public.quest_outfits
            ADD CONSTRAINT quest_outfits_formids_json_is_array
            CHECK (formids_json IS NULL OR jsonb_typeof(formids_json) = 'array');
    END IF;
END $$;
