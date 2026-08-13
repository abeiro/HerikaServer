ALTER TABLE public.oghma
    ADD COLUMN IF NOT EXISTS retrieval_phrases text NOT NULL DEFAULT '';

ALTER TABLE public.oghma_catalog_entries
    ADD COLUMN IF NOT EXISTS retrieval_phrases text NOT NULL DEFAULT '';
