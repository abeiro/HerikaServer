CREATE TABLE public.sneq_quests (
    quest_id text NOT NULL,
    code text NOT NULL,
    quest_run_state text NOT NULL,
    quest_data jsonb DEFAULT '{}'::jsonb,
    created_at timestamp with time zone DEFAULT now(),
    updated_at timestamp with time zone DEFAULT now(),
    title text,
    stage text
)
WITH (autovacuum_enabled='on', toast.autovacuum_enabled='on');


ALTER TABLE public.sneq_quests OWNER TO dwemer;

--
-- Name: sneq_quests sneq_quests_pkey; Type: CONSTRAINT; Schema: public; Owner: dwemer
--

ALTER TABLE ONLY public.sneq_quests
    ADD CONSTRAINT sneq_quests_pkey PRIMARY KEY (quest_id);