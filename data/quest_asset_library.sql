-- Normalized, plugin-aware asset catalog for AI Quest Manager.
-- Existing quest reference tables remain available as a compatibility layer.

CREATE TABLE IF NOT EXISTS public.quest_asset_packs (
    pack_key text PRIMARY KEY,
    label text NOT NULL,
    game text NOT NULL DEFAULT 'SkyrimSE',
    manifest_version text NOT NULL DEFAULT '1',
    required_plugins_json jsonb NOT NULL DEFAULT '[]'::jsonb,
    source text NOT NULL DEFAULT '',
    manifest_hash text NOT NULL DEFAULT '',
    active boolean NOT NULL DEFAULT true,
    note text NOT NULL DEFAULT '',
    imported_at timestamp without time zone NOT NULL DEFAULT now(),
    updated_at timestamp without time zone NOT NULL DEFAULT now(),
    CONSTRAINT quest_asset_packs_plugins_is_array
        CHECK (jsonb_typeof(required_plugins_json) = 'array')
);

CREATE TABLE IF NOT EXISTS public.quest_assets (
    source_pack text NOT NULL REFERENCES public.quest_asset_packs(pack_key) ON DELETE CASCADE,
    stable_ref text NOT NULL,
    signature varchar(4) NOT NULL,
    editor_id text NOT NULL DEFAULT '',
    display_name text NOT NULL DEFAULT '',
    source_plugin text NOT NULL,
    winning_plugin text NOT NULL DEFAULT '',
    metadata_json jsonb NOT NULL DEFAULT '{}'::jsonb,
    safety_status text NOT NULL DEFAULT 'review',
    active boolean NOT NULL DEFAULT true,
    created_at timestamp without time zone NOT NULL DEFAULT now(),
    updated_at timestamp without time zone NOT NULL DEFAULT now(),
    PRIMARY KEY (source_pack, stable_ref),
    CONSTRAINT quest_assets_metadata_is_object
        CHECK (jsonb_typeof(metadata_json) = 'object'),
    CONSTRAINT quest_assets_safety_status
        CHECK (safety_status IN ('approved', 'review', 'rejected')),
    CONSTRAINT quest_assets_stable_ref_format
        CHECK (stable_ref ~ '^[^|]+\|[0-9A-Fa-f]{8}$')
);

CREATE TABLE IF NOT EXISTS public.quest_asset_groups (
    dataset_name text NOT NULL,
    group_key text NOT NULL,
    label text NOT NULL DEFAULT '',
    description text NOT NULL DEFAULT '',
    selection_policy_json jsonb NOT NULL DEFAULT '{}'::jsonb,
    source_pack text NOT NULL REFERENCES public.quest_asset_packs(pack_key) ON DELETE CASCADE,
    active boolean NOT NULL DEFAULT true,
    created_at timestamp without time zone NOT NULL DEFAULT now(),
    updated_at timestamp without time zone NOT NULL DEFAULT now(),
    PRIMARY KEY (source_pack, dataset_name, group_key),
    CONSTRAINT quest_asset_groups_dataset
        CHECK (dataset_name IN ('item_types', 'npc_templates', 'npc_own_templates', 'outfit', 'weapons')),
    CONSTRAINT quest_asset_groups_policy_is_object
        CHECK (jsonb_typeof(selection_policy_json) = 'object'),
    CONSTRAINT quest_asset_groups_key_format
        CHECK (group_key ~ '^[a-z0-9_]+$')
);

CREATE TABLE IF NOT EXISTS public.quest_asset_group_members (
    dataset_name text NOT NULL,
    group_key text NOT NULL,
    stable_ref text NOT NULL,
    weight integer NOT NULL DEFAULT 1,
    constraints_json jsonb NOT NULL DEFAULT '{}'::jsonb,
    note text NOT NULL DEFAULT '',
    source_pack text NOT NULL REFERENCES public.quest_asset_packs(pack_key) ON DELETE CASCADE,
    active boolean NOT NULL DEFAULT true,
    created_at timestamp without time zone NOT NULL DEFAULT now(),
    updated_at timestamp without time zone NOT NULL DEFAULT now(),
    PRIMARY KEY (source_pack, dataset_name, group_key, stable_ref),
    FOREIGN KEY (source_pack, dataset_name, group_key)
        REFERENCES public.quest_asset_groups(source_pack, dataset_name, group_key) ON DELETE CASCADE,
    FOREIGN KEY (source_pack, stable_ref)
        REFERENCES public.quest_assets(source_pack, stable_ref) ON DELETE CASCADE,
    CONSTRAINT quest_asset_group_members_weight
        CHECK (weight BETWEEN 1 AND 100),
    CONSTRAINT quest_asset_group_members_constraints_is_object
        CHECK (jsonb_typeof(constraints_json) = 'object')
);

CREATE TABLE IF NOT EXISTS public.quest_asset_imports (
    id bigserial PRIMARY KEY,
    pack_key text NOT NULL,
    manifest_version text NOT NULL,
    manifest_hash text NOT NULL,
    source_file text NOT NULL DEFAULT '',
    asset_count integer NOT NULL DEFAULT 0,
    group_count integer NOT NULL DEFAULT 0,
    member_count integer NOT NULL DEFAULT 0,
    imported_at timestamp without time zone NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS quest_assets_source_pack_idx
    ON public.quest_assets(source_pack);
CREATE INDEX IF NOT EXISTS quest_assets_signature_idx
    ON public.quest_assets(signature);
CREATE INDEX IF NOT EXISTS quest_assets_safety_idx
    ON public.quest_assets(safety_status, active);
CREATE INDEX IF NOT EXISTS quest_asset_groups_pack_idx
    ON public.quest_asset_groups(source_pack);
CREATE INDEX IF NOT EXISTS quest_asset_members_pack_idx
    ON public.quest_asset_group_members(source_pack);
CREATE INDEX IF NOT EXISTS quest_asset_members_group_idx
    ON public.quest_asset_group_members(dataset_name, group_key, active);
