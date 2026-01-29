CREATE TABLE public.actions_issued (
    action text,
    fullcall text,
    actorname text,
    ts numeric,
    localts numeric,
    gamets numeric,
    original text,
    rowid integer NOT NULL
);


ALTER TABLE public.actions_issued OWNER TO dwemer;

--
-- Name: actions_issued_rowid_seq; Type: SEQUENCE; Schema: public; Owner: dwemer
--

CREATE SEQUENCE public.actions_issued_rowid_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.actions_issued_rowid_seq OWNER TO dwemer;

--
-- Name: actions_issued_rowid_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: dwemer
--

ALTER SEQUENCE public.actions_issued_rowid_seq OWNED BY public.actions_issued.rowid;


--
-- Name: actions_issued rowid; Type: DEFAULT; Schema: public; Owner: dwemer
--

ALTER TABLE ONLY public.actions_issued ALTER COLUMN rowid SET DEFAULT nextval('public.actions_issued_rowid_seq'::regclass);


--
-- Name: actions_issued actions_issued_pkey; Type: CONSTRAINT; Schema: public; Owner: dwemer
--

ALTER TABLE ONLY public.actions_issued
    ADD CONSTRAINT actions_issued_pkey PRIMARY KEY (rowid);