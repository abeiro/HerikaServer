CREATE TABLE IF NOT EXISTS public.global_settings_presets (
    id BIGSERIAL PRIMARY KEY,
    name TEXT NOT NULL,
    snapshot JSONB NOT NULL,
    created_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT global_settings_presets_name_not_blank CHECK (btrim(name) <> ''),
    CONSTRAINT global_settings_presets_name_length CHECK (char_length(name) <= 60),
    CONSTRAINT global_settings_presets_snapshot_object CHECK (jsonb_typeof(snapshot) = 'object')
);

CREATE UNIQUE INDEX IF NOT EXISTS global_settings_presets_name_ci
    ON public.global_settings_presets (lower(name));
