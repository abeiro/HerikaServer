CREATE TABLE IF NOT EXISTS  public.factions (
    name text,
    formid text PRIMARY KEY
);


ALTER TABLE public.factions OWNER TO dwemer;

--
-- Name: TABLE factions; Type: COMMENT; Schema: public; Owner: dwemer
--

COMMENT ON TABLE public.factions IS 'factions sent from plugin';