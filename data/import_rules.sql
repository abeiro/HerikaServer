CREATE TABLE public.import_rules (
    id integer NOT NULL,
    description text NOT NULL,
    match_name text,
    match_race text,
    match_gender text,
    match_base text,
    match_mods text[],
    action jsonb,
    profile integer,
    priority integer DEFAULT 0,
    enabled boolean DEFAULT true
);


ALTER TABLE public.import_rules OWNER TO dwemer;

--
-- Name: import_rules_id_seq; Type: SEQUENCE; Schema: public; Owner: dwemer
--

CREATE SEQUENCE public.import_rules_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.import_rules_id_seq OWNER TO dwemer;

--
-- Name: import_rules_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: dwemer
--

ALTER SEQUENCE public.import_rules_id_seq OWNED BY public.import_rules.id;


--
-- Name: import_rules id; Type: DEFAULT; Schema: public; Owner: dwemer
--

ALTER TABLE ONLY public.import_rules ALTER COLUMN id SET DEFAULT nextval('public.import_rules_id_seq'::regclass);


--
-- Name: import_rules import_rules_pkey; Type: CONSTRAINT; Schema: public; Owner: dwemer
--

ALTER TABLE ONLY public.import_rules
    ADD CONSTRAINT import_rules_pkey PRIMARY KEY (id);


--
-- Name: import_rules import_rules_profile_fkey; Type: FK CONSTRAINT; Schema: public; Owner: dwemer
--

ALTER TABLE ONLY public.import_rules
    ADD CONSTRAINT import_rules_profile_fkey FOREIGN KEY (profile) REFERENCES public.core_profiles(id);


--
-- PostgreSQL database dump complete
--
