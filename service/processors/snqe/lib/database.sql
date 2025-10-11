CREATE TABLE IF NOT EXISTS sneq_quests (
    quest_id TEXT PRIMARY KEY,           -- Unique quest identifier
    code TEXT NOT NULL,                  -- PHP code of the quest
    quest_run_state TEXT NOT NULL,       -- Quest state: running | not_running | finished | failed etc.
    quest_data JSONB DEFAULT '{}'::jsonb, -- Serialized quest state
    created_at TIMESTAMP WITH TIME ZONE DEFAULT now(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT now()
);

-- Optional: automatically update updated_at on row modification
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
   NEW.updated_at = now();
   RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_sneq_quests_updated_at
BEFORE UPDATE ON sneq_quests
FOR EACH ROW
EXECUTE FUNCTION update_updated_at_column();
