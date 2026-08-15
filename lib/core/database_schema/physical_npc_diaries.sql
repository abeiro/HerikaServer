CREATE TABLE IF NOT EXISTS public.physical_npc_diaries (
    npc_name text PRIMARY KEY,
    title text NOT NULL,
    last_diary_localts bigint NOT NULL DEFAULT 0,
    created_at bigint NOT NULL DEFAULT 0,
    updated_at bigint NOT NULL DEFAULT 0
);

CREATE INDEX IF NOT EXISTS physical_npc_diaries_updated_idx
    ON public.physical_npc_diaries (updated_at DESC);
