CREATE TABLE IF NOT EXISTS public.profile_settings_presets (
    id BIGSERIAL PRIMARY KEY,
    name TEXT NOT NULL,
    snapshot JSONB NOT NULL,
    created_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT profile_settings_presets_name_not_blank CHECK (btrim(name) <> ''),
    CONSTRAINT profile_settings_presets_name_length CHECK (char_length(name) <= 60),
    CONSTRAINT profile_settings_presets_snapshot_object CHECK (jsonb_typeof(snapshot) = 'object')
);

CREATE UNIQUE INDEX IF NOT EXISTS profile_settings_presets_name_ci
    ON public.profile_settings_presets (lower(name));
