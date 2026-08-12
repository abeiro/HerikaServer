ALTER TABLE public.audit_request
    ADD COLUMN IF NOT EXISTS response text;
