CREATE TABLE IF NOT EXISTS public.oghma_context_rule (
    id BIGSERIAL PRIMARY KEY,
    label TEXT NOT NULL,
    enabled BOOLEAN NOT NULL DEFAULT TRUE,
    priority INTEGER NOT NULL DEFAULT 100,
    selector_type TEXT NOT NULL DEFAULT 'topic',
    selector_value TEXT NOT NULL,
    conditions JSONB NOT NULL DEFAULT '{}'::jsonb,
    max_articles SMALLINT NOT NULL DEFAULT 1,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT oghma_context_rule_selector_type_check
        CHECK (selector_type IN ('topic', 'tag', 'category')),
    CONSTRAINT oghma_context_rule_max_articles_check
        CHECK (max_articles BETWEEN 1 AND 5)
);

CREATE INDEX IF NOT EXISTS idx_oghma_context_rule_active
    ON public.oghma_context_rule (enabled, priority, id);
