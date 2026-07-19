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
    resolved_gamets BIGINT,
    outcome TEXT NOT NULL DEFAULT '',
    payload_json JSONB NOT NULL DEFAULT '{}'::jsonb,
    created_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT NOW(),
    CONSTRAINT npc_commitments_status_check
        CHECK (status IN ('scheduled', 'due', 'completed', 'failed', 'cancelled'))
);

CREATE INDEX IF NOT EXISTS idx_npc_commitments_actor_status_due
    ON public.npc_commitments (LOWER(actor_name), status, due_gamets);

CREATE INDEX IF NOT EXISTS idx_npc_commitments_timeline
    ON public.npc_commitments (created_gamets, resolved_gamets);
