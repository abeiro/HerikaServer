CREATE TABLE IF NOT EXISTS  public.locations (
    name text,
    formid bigint
);


ALTER TABLE public.locations OWNER TO dwemer;

--
-- Name: TABLE locations; Type: COMMENT; Schema: public; Owner: dwemer
--

COMMENT ON TABLE public.locations IS 'locations sent from plugin';