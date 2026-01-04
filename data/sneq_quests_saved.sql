CREATE TABLE public.sneq_quests_saved (
    quest_id text,
    code text,
    quest_run_state text,
    quest_data jsonb,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    title text,
    stage text,
    gamets BIGINT,
    state text,
    history_id integer NOT NULL
);


ALTER TABLE public.sneq_quests_saved OWNER TO dwemer;

--
-- Name: sneq_quests_saved_history_id_seq; Type: SEQUENCE; Schema: public; Owner: dwemer
--

CREATE SEQUENCE public.sneq_quests_saved_history_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.sneq_quests_saved_history_id_seq OWNER TO dwemer;

--
-- Name: sneq_quests_saved_history_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: dwemer
--

ALTER SEQUENCE public.sneq_quests_saved_history_id_seq OWNED BY public.sneq_quests_saved.history_id;


--
-- Name: sneq_quests_saved history_id; Type: DEFAULT; Schema: public; Owner: dwemer
--

ALTER TABLE ONLY public.sneq_quests_saved ALTER COLUMN history_id SET DEFAULT nextval('public.sneq_quests_saved_history_id_seq'::regclass);