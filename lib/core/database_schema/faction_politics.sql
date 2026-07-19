CREATE TABLE IF NOT EXISTS public.core_faction_politics_state (
    faction_key text PRIMARY KEY,
    faction_name text NOT NULL,
    status text NOT NULL DEFAULT 'stable',
    influence smallint NOT NULL DEFAULT 0 CHECK (influence BETWEEN -100 AND 100),
    agenda text NOT NULL DEFAULT '',
    summary text NOT NULL DEFAULT '',
    gamets bigint NOT NULL DEFAULT 0,
    created_at bigint NOT NULL DEFAULT 0,
    updated_at bigint NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS public.core_faction_politics_relation (
    faction_a_key text NOT NULL,
    faction_a_name text NOT NULL,
    faction_b_key text NOT NULL,
    faction_b_name text NOT NULL,
    stance text NOT NULL DEFAULT 'neutral',
    score smallint NOT NULL DEFAULT 0 CHECK (score BETWEEN -100 AND 100),
    summary text NOT NULL DEFAULT '',
    gamets bigint NOT NULL DEFAULT 0,
    created_at bigint NOT NULL DEFAULT 0,
    updated_at bigint NOT NULL DEFAULT 0,
    PRIMARY KEY (faction_a_key, faction_b_key),
    CHECK (faction_a_key <> faction_b_key)
);

CREATE TABLE IF NOT EXISTS public.core_faction_politics_development (
    id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    title text NOT NULL,
    summary text NOT NULL,
    faction_keys jsonb NOT NULL DEFAULT '[]'::jsonb,
    status text NOT NULL DEFAULT 'active',
    gamets bigint NOT NULL DEFAULT 0,
    created_at bigint NOT NULL DEFAULT 0,
    updated_at bigint NOT NULL DEFAULT 0
);

CREATE INDEX IF NOT EXISTS faction_politics_development_active_idx
    ON public.core_faction_politics_development (status, gamets DESC, id DESC);
