CREATE TABLE IF NOT EXISTS public.npc_commitments (
    id BIGSERIAL PRIMARY KEY,
    actor_name TEXT NOT NULL,
    commitment_type VARCHAR(32) NOT NULL DEFAULT 'other',
    subject TEXT NOT NULL,
    counterparty TEXT NOT NULL DEFAULT '',
    location_name TEXT NOT NULL DEFAULT '',
    status VARCHAR(16) NOT NULL DEFAULT 'scheduled',
    created_gamets BIGINT NOT NULL,
    due_gamets BIGINT NOT NULL,
    repeat_interval_gamets BIGINT NOT NULL DEFAULT 0,
    occurrence_count INTEGER NOT NULL DEFAULT 0,
    last_resolved_gamets BIGINT,
    resolved_gamets BIGINT,
    outcome TEXT NOT NULL DEFAULT '',
    payload_json JSONB NOT NULL DEFAULT '{}'::jsonb,
    created_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT NOW(),
    CONSTRAINT npc_commitments_status_check
        CHECK (status IN ('scheduled', 'due', 'completed', 'failed', 'cancelled')),
    CONSTRAINT npc_commitments_repeat_interval_check CHECK (repeat_interval_gamets >= 0),
    CONSTRAINT npc_commitments_occurrence_count_check CHECK (occurrence_count >= 0)
);

ALTER TABLE public.npc_commitments
    ADD COLUMN IF NOT EXISTS repeat_interval_gamets BIGINT NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS occurrence_count INTEGER NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS last_resolved_gamets BIGINT;

CREATE INDEX IF NOT EXISTS idx_npc_commitments_actor_status_due
    ON public.npc_commitments (LOWER(actor_name), status, due_gamets);

CREATE INDEX IF NOT EXISTS idx_npc_commitments_timeline
    ON public.npc_commitments (created_gamets, resolved_gamets);
