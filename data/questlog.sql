CREATE TABLE IF NOT EXISTS public.questlog (
    ts text,
    sess character varying(1024),
    id_quest character varying(1024),
    name text,
    editor_id text,
    giver_actor_id text,
    reward text,
    target_id text,
    is_unique boolean,
    mod text,
    stage integer,
    briefing text,
    briefing2 text,
    localts bigint,
    gamets bigint,
    data text,
    status text,
    rowid integer NOT NULL
);


ALTER TABLE public.questlog OWNER TO dwemer;

--
-- Name: questlog_rowid_seq; Type: SEQUENCE; Schema: public; Owner: dwemer
--

CREATE SEQUENCE IF NOT EXISTS public.questlog_rowid_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.questlog_rowid_seq OWNER TO dwemer;

--
-- Name: questlog_rowid_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: dwemer
--

ALTER SEQUENCE public.questlog_rowid_seq OWNED BY public.questlog.rowid;


--
-- Name: questlog rowid; Type: DEFAULT; Schema: public; Owner: dwemer
--

ALTER TABLE ONLY public.questlog ALTER COLUMN rowid SET DEFAULT nextval('public.questlog_rowid_seq'::regclass);


--
-- Name: questlog questlog_pkey; Type: CONSTRAINT; Schema: public; Owner: dwemer
--

ALTER TABLE ONLY public.questlog
    ADD CONSTRAINT questlog_pkey PRIMARY KEY (rowid);