CREATE TABLE public.moods_issued (
    sess character varying(1024),
    speaker text,
    mood text,
    listener text,
    localts bigint NOT NULL,
    gamets bigint NOT NULL,
    ts bigint,
    rowid bigint DEFAULT nextval('public.speech_rowid_seq'::regclass) NOT NULL
);


ALTER TABLE public.moods_issued OWNER TO dwemer;

--
-- Name: moods_issued moods_issued_pkey; Type: CONSTRAINT; Schema: public; Owner: dwemer
--

ALTER TABLE ONLY public.moods_issued
    ADD CONSTRAINT moods_issued_pkey PRIMARY KEY (rowid);


--
-- PostgreSQL database dump complete
--