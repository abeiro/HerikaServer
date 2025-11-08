-- Prompts table for storing customizable AI prompt templates
-- This allows users to edit prompts through the UI instead of modifying PHP files

CREATE TABLE IF NOT EXISTS public.prompts (
    name VARCHAR(128) PRIMARY KEY,
    cue TEXT NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create index for faster lookups
CREATE INDEX IF NOT EXISTS idx_prompts_name ON public.prompts(name);

-- Add update trigger to automatically update updated_at timestamp
CREATE OR REPLACE FUNCTION update_prompts_updated_at()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trigger_update_prompts_timestamp ON public.prompts;
CREATE TRIGGER trigger_update_prompts_timestamp
    BEFORE UPDATE ON public.prompts
    FOR EACH ROW
    EXECUTE FUNCTION update_prompts_updated_at();

COMMENT ON TABLE public.prompts IS 'Stores customizable AI prompt templates for various game events';
COMMENT ON COLUMN public.prompts.name IS 'Unique identifier for the prompt (matches event type)';
COMMENT ON COLUMN public.prompts.cue IS 'The prompt text sent to the LLM. Can be a single string or JSON array of multiple options';
COMMENT ON COLUMN public.prompts.description IS 'Human-readable description of when this prompt is used';

