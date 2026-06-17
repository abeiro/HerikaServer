CREATE TABLE IF NOT EXISTS public.core_action (
    id SERIAL PRIMARY KEY,
    code_name VARCHAR(128) UNIQUE NOT NULL,
    action_name VARCHAR(255) NOT NULL,
    description TEXT NOT NULL DEFAULT '',
    return_message TEXT NOT NULL DEFAULT '',
    available_to_npc BOOLEAN NOT NULL DEFAULT FALSE,
    available_to_followers BOOLEAN NOT NULL DEFAULT FALSE,
    available_to_narrator BOOLEAN NOT NULL DEFAULT FALSE,
    is_activated BOOLEAN NOT NULL DEFAULT TRUE,
    parameters_json JSONB NOT NULL DEFAULT '{}'::jsonb,
    metadata JSONB NOT NULL DEFAULT '{}'::jsonb,
    game_function BOOLEAN NOT NULL DEFAULT TRUE,
    import_version BIGINT NOT NULL DEFAULT 0,
    script_proxy_program JSONB,
    created_at TIMESTAMP WITHOUT TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITHOUT TIME ZONE DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS public.core_action_custom (
    id SERIAL PRIMARY KEY,
    code_name VARCHAR(128) UNIQUE NOT NULL,
    action_name VARCHAR(255) NOT NULL,
    description TEXT NOT NULL DEFAULT '',
    return_message TEXT NOT NULL DEFAULT '',
    available_to_npc BOOLEAN NOT NULL DEFAULT FALSE,
    available_to_followers BOOLEAN NOT NULL DEFAULT FALSE,
    available_to_narrator BOOLEAN NOT NULL DEFAULT FALSE,
    is_activated BOOLEAN NOT NULL DEFAULT TRUE,
    parameters_json JSONB NOT NULL DEFAULT '{}'::jsonb,
    metadata JSONB NOT NULL DEFAULT '{}'::jsonb,
    game_function BOOLEAN NOT NULL DEFAULT TRUE,
    import_version BIGINT NOT NULL DEFAULT 0,
    script_proxy_program JSONB,
    created_at TIMESTAMP WITHOUT TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITHOUT TIME ZONE DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_core_action_code_name_lower ON public.core_action (LOWER(code_name));
CREATE INDEX IF NOT EXISTS idx_core_action_action_name_lower ON public.core_action (LOWER(action_name));
CREATE INDEX IF NOT EXISTS idx_core_action_is_activated ON public.core_action (is_activated);
CREATE INDEX IF NOT EXISTS idx_core_action_available_to_npc ON public.core_action (available_to_npc);
CREATE INDEX IF NOT EXISTS idx_core_action_available_to_followers ON public.core_action (available_to_followers);
CREATE INDEX IF NOT EXISTS idx_core_action_available_to_narrator ON public.core_action (available_to_narrator);
CREATE INDEX IF NOT EXISTS idx_core_action_game_function ON public.core_action (game_function);

CREATE INDEX IF NOT EXISTS idx_core_action_custom_code_name_lower ON public.core_action_custom (LOWER(code_name));
CREATE INDEX IF NOT EXISTS idx_core_action_custom_action_name_lower ON public.core_action_custom (LOWER(action_name));
CREATE INDEX IF NOT EXISTS idx_core_action_custom_is_activated ON public.core_action_custom (is_activated);
CREATE INDEX IF NOT EXISTS idx_core_action_custom_available_to_npc ON public.core_action_custom (available_to_npc);
CREATE INDEX IF NOT EXISTS idx_core_action_custom_available_to_followers ON public.core_action_custom (available_to_followers);
CREATE INDEX IF NOT EXISTS idx_core_action_custom_available_to_narrator ON public.core_action_custom (available_to_narrator);
CREATE INDEX IF NOT EXISTS idx_core_action_custom_game_function ON public.core_action_custom (game_function);

CREATE OR REPLACE VIEW public.combined_core_action AS
SELECT
    c.id,
    c.code_name,
    c.action_name,
    c.description,
    c.return_message,
    c.available_to_npc,
    c.available_to_followers,
    c.available_to_narrator,
    c.is_activated,
    c.parameters_json,
    c.metadata,
    c.game_function,
    c.import_version,
    c.script_proxy_program,
    c.created_at,
    c.updated_at
FROM public.core_action_custom c
UNION ALL
SELECT
    b.id,
    b.code_name,
    b.action_name,
    b.description,
    b.return_message,
    b.available_to_npc,
    b.available_to_followers,
    b.available_to_narrator,
    b.is_activated,
    b.parameters_json,
    b.metadata,
    b.game_function,
    b.import_version,
    b.script_proxy_program,
    b.created_at,
    b.updated_at
FROM public.core_action b
LEFT JOIN public.core_action_custom c ON LOWER(b.code_name) = LOWER(c.code_name)
WHERE c.code_name IS NULL;
