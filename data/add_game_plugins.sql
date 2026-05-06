CREATE TABLE IF NOT EXISTS public.game_plugins (
    plugin_name text PRIMARY KEY,
    is_light boolean NOT NULL DEFAULT false,
    compile_index integer NOT NULL DEFAULT 0,
    small_file_compile_index integer NOT NULL DEFAULT 0,
    partial_index integer NOT NULL DEFAULT 0,
    formid_prefix text NOT NULL DEFAULT '',
    updated_at timestamp without time zone NOT NULL DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.game_plugins OWNER TO dwemer;

COMMENT ON TABLE public.game_plugins IS 'Loaded Skyrim plugins sent from the game runtime for plugin-aware form resolution';
