--
-- PostgreSQL database dump
--

-- Dumped from database version 16.3 (Debian 16.3-1)
-- Dumped by pg_dump version 16.3 (Debian 16.3-1)

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Name: vector; Type: EXTENSION; Schema: -; Owner: -
--

CREATE EXTENSION IF NOT EXISTS vector WITH SCHEMA public;


--
-- Name: EXTENSION vector; Type: COMMENT; Schema: -; Owner: 
--

COMMENT ON EXTENSION vector IS 'vector data type and ivfflat and hnsw access methods';


SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: books; Type: TABLE; Schema: public; Owner: dwemer
--

CREATE TABLE public.books (
    sess character varying(1024),
    title text,
    content text,
    localts bigint NOT NULL,
    gamets bigint NOT NULL,
    ts bigint,
    rowid bigint NOT NULL
);


ALTER TABLE public.books OWNER TO dwemer;

--
-- Name: books_rowid_seq; Type: SEQUENCE; Schema: public; Owner: dwemer
--

CREATE SEQUENCE public.books_rowid_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.books_rowid_seq OWNER TO dwemer;

--
-- Name: books_rowid_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: dwemer
--

ALTER SEQUENCE public.books_rowid_seq OWNED BY public.books.rowid;


--
-- Name: currentmission; Type: TABLE; Schema: public; Owner: dwemer
--

CREATE TABLE public.currentmission (
    sess character varying(1024),
    description text,
    localts bigint NOT NULL,
    gamets bigint NOT NULL,
    ts bigint,
    rowid bigint NOT NULL
);


ALTER TABLE public.currentmission OWNER TO dwemer;

--
-- Name: currentmission_rowid_seq; Type: SEQUENCE; Schema: public; Owner: dwemer
--

CREATE SEQUENCE public.currentmission_rowid_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.currentmission_rowid_seq OWNER TO dwemer;

--
-- Name: currentmission_rowid_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: dwemer
--

ALTER SEQUENCE public.currentmission_rowid_seq OWNED BY public.currentmission.rowid;


--
-- Name: diarylog; Type: TABLE; Schema: public; Owner: dwemer
--

CREATE TABLE public.diarylog (
    ts text NOT NULL,
    sess character varying(1024),
    topic text,
    content text,
    tags text,
    people text,
    localts bigint NOT NULL,
    location text,
    gamets bigint NOT NULL,
    rowid bigint NOT NULL
);


ALTER TABLE public.diarylog OWNER TO dwemer;

--
-- Name: diarylog_rowid_seq; Type: SEQUENCE; Schema: public; Owner: dwemer
--

CREATE SEQUENCE public.diarylog_rowid_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.diarylog_rowid_seq OWNER TO dwemer;

--
-- Name: diarylog_rowid_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: dwemer
--

ALTER SEQUENCE public.diarylog_rowid_seq OWNED BY public.diarylog.rowid;


--
-- Name: embeddings; Type: TABLE; Schema: public; Owner: dwemer
--

CREATE TABLE public.embeddings (
    id bigint NOT NULL,
    collection text,
    embedding public.vector(384)
);


ALTER TABLE public.embeddings OWNER TO dwemer;

--
-- Name: embeddings_id_seq; Type: SEQUENCE; Schema: public; Owner: dwemer
--

CREATE SEQUENCE public.embeddings_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.embeddings_id_seq OWNER TO dwemer;

--
-- Name: embeddings_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: dwemer
--

ALTER SEQUENCE public.embeddings_id_seq OWNED BY public.embeddings.id;


--
-- Name: eventlog; Type: TABLE; Schema: public; Owner: dwemer
--

CREATE TABLE public.eventlog (
    type character varying(128),
    data text,
    sess character varying(1024),
    gamets bigint NOT NULL,
    localts bigint NOT NULL,
    ts bigint,
    rowid bigint NOT NULL
);


ALTER TABLE public.eventlog OWNER TO dwemer;

--
-- Name: eventlog_rowid_seq; Type: SEQUENCE; Schema: public; Owner: dwemer
--

CREATE SEQUENCE public.eventlog_rowid_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.eventlog_rowid_seq OWNER TO dwemer;

--
-- Name: eventlog_rowid_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: dwemer
--

ALTER SEQUENCE public.eventlog_rowid_seq OWNED BY public.eventlog.rowid;


--
-- Name: memory; Type: TABLE; Schema: public; Owner: dwemer
--

CREATE TABLE public.memory (
    speaker text,
    message text,
    session text,
    uid integer NOT NULL,
    listener text,
    localts integer,
    gamets bigint NOT NULL,
    momentum text
);


ALTER TABLE public.memory OWNER TO dwemer;

--
-- Name: memory_summary; Type: TABLE; Schema: public; Owner: dwemer
--

CREATE TABLE public.memory_summary (
    gamets_truncated bigint NOT NULL,
    n integer,
    packed_message text,
    summary text,
    classifier text,
    uid integer,
    rowid integer NOT NULL
);


ALTER TABLE public.memory_summary OWNER TO dwemer;

--
-- Name: memory_summary_rowid_seq; Type: SEQUENCE; Schema: public; Owner: dwemer
--

CREATE SEQUENCE public.memory_summary_rowid_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.memory_summary_rowid_seq OWNER TO dwemer;

--
-- Name: memory_summary_rowid_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: dwemer
--

ALTER SEQUENCE public.memory_summary_rowid_seq OWNED BY public.memory_summary.rowid;


--
-- Name: memory_uid_seq; Type: SEQUENCE; Schema: public; Owner: dwemer
--

CREATE SEQUENCE public.memory_uid_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.memory_uid_seq OWNER TO dwemer;

--
-- Name: memory_uid_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: dwemer
--

ALTER SEQUENCE public.memory_uid_seq OWNED BY public.memory.uid;


--
-- Name: speech; Type: TABLE; Schema: public; Owner: dwemer
--

CREATE TABLE public.speech (
    sess character varying(1024),
    speaker text,
    speech text,
    location text,
    listener text,
    topic text,
    localts bigint NOT NULL,
    gamets bigint NOT NULL,
    ts bigint,
    rowid bigint NOT NULL
);


ALTER TABLE public.speech OWNER TO dwemer;

--
-- Name: memory_v; Type: VIEW; Schema: public; Owner: dwemer
--

CREATE VIEW public.memory_v AS
 SELECT message,
    uid,
    gamets,
    speaker,
    listener,
    ts
   FROM ( SELECT memory.message,
            memory.uid,
            memory.gamets,
            '-'::text AS speaker,
            '-'::text AS listener,
            '999999999999999999'::bigint AS ts
           FROM public.memory
          WHERE ((memory.message !~~ 'Dear Diary%'::text) AND (memory.message <> ''::text))
        UNION
         SELECT ((((('(Context Location:'::text || speech.location) || ') '::text) || speech.speaker) || ': '::text) || speech.speech),
            0,
            speech.gamets,
            speech.speaker,
            speech.listener,
            speech.ts
           FROM public.speech
          WHERE (speech.speech <> ''::text)
        UNION
         SELECT eventlog.data,
            0,
            eventlog.gamets,
            '-'::text,
            '-'::text AS listener,
            eventlog.ts
           FROM public.eventlog
          WHERE ((eventlog.type)::text = ANY ((ARRAY['death'::character varying, 'location'::character varying])::text[]))) subquery
  ORDER BY gamets, ts;


ALTER VIEW public.memory_v OWNER TO dwemer;

--
-- Name: quests; Type: TABLE; Schema: public; Owner: dwemer
--

CREATE TABLE public.quests (
    ts text NOT NULL,
    sess character varying(1024),
    id_quest character varying(1024) NOT NULL,
    name text,
    editor_id text,
    giver_actor_id bigint,
    reward text,
    target_id text,
    is_unique boolean,
    mod text,
    stage integer,
    briefing text,
    briefing2 text,
    localts bigint NOT NULL,
    gamets bigint NOT NULL,
    data text,
    status text,
    rowid bigint NOT NULL
);


ALTER TABLE public.quests OWNER TO dwemer;

--
-- Name: quests_rowid_seq; Type: SEQUENCE; Schema: public; Owner: dwemer
--

CREATE SEQUENCE public.quests_rowid_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.quests_rowid_seq OWNER TO dwemer;

--
-- Name: quests_rowid_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: dwemer
--

ALTER SEQUENCE public.quests_rowid_seq OWNED BY public.quests.rowid;


--
-- Name: responselog; Type: TABLE; Schema: public; Owner: dwemer
--

CREATE TABLE public.responselog (
    localts bigint NOT NULL,
    sent bigint NOT NULL,
    actor character varying(128),
    text text,
    action character varying(256),
    tag character varying(256),
    rowid bigint NOT NULL
);


ALTER TABLE public.responselog OWNER TO dwemer;

--
-- Name: responselog_rowid_seq; Type: SEQUENCE; Schema: public; Owner: dwemer
--

CREATE SEQUENCE public.responselog_rowid_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.responselog_rowid_seq OWNER TO dwemer;

--
-- Name: responselog_rowid_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: dwemer
--

ALTER SEQUENCE public.responselog_rowid_seq OWNED BY public.responselog.rowid;


--
-- Name: speech_rowid_seq; Type: SEQUENCE; Schema: public; Owner: dwemer
--

CREATE SEQUENCE public.speech_rowid_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.speech_rowid_seq OWNER TO dwemer;

--
-- Name: speech_rowid_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: dwemer
--

ALTER SEQUENCE public.speech_rowid_seq OWNED BY public.speech.rowid;


--
-- Name: books rowid; Type: DEFAULT; Schema: public; Owner: dwemer
--

ALTER TABLE ONLY public.books ALTER COLUMN rowid SET DEFAULT nextval('public.books_rowid_seq'::regclass);


--
-- Name: currentmission rowid; Type: DEFAULT; Schema: public; Owner: dwemer
--

ALTER TABLE ONLY public.currentmission ALTER COLUMN rowid SET DEFAULT nextval('public.currentmission_rowid_seq'::regclass);


--
-- Name: diarylog rowid; Type: DEFAULT; Schema: public; Owner: dwemer
--

ALTER TABLE ONLY public.diarylog ALTER COLUMN rowid SET DEFAULT nextval('public.diarylog_rowid_seq'::regclass);


--
-- Name: embeddings id; Type: DEFAULT; Schema: public; Owner: dwemer
--

ALTER TABLE ONLY public.embeddings ALTER COLUMN id SET DEFAULT nextval('public.embeddings_id_seq'::regclass);


--
-- Name: eventlog rowid; Type: DEFAULT; Schema: public; Owner: dwemer
--

ALTER TABLE ONLY public.eventlog ALTER COLUMN rowid SET DEFAULT nextval('public.eventlog_rowid_seq'::regclass);


--
-- Name: memory uid; Type: DEFAULT; Schema: public; Owner: dwemer
--

ALTER TABLE ONLY public.memory ALTER COLUMN uid SET DEFAULT nextval('public.memory_uid_seq'::regclass);


--
-- Name: memory_summary rowid; Type: DEFAULT; Schema: public; Owner: dwemer
--

ALTER TABLE ONLY public.memory_summary ALTER COLUMN rowid SET DEFAULT nextval('public.memory_summary_rowid_seq'::regclass);


--
-- Name: quests rowid; Type: DEFAULT; Schema: public; Owner: dwemer
--

ALTER TABLE ONLY public.quests ALTER COLUMN rowid SET DEFAULT nextval('public.quests_rowid_seq'::regclass);


--
-- Name: responselog rowid; Type: DEFAULT; Schema: public; Owner: dwemer
--

ALTER TABLE ONLY public.responselog ALTER COLUMN rowid SET DEFAULT nextval('public.responselog_rowid_seq'::regclass);


--
-- Name: speech rowid; Type: DEFAULT; Schema: public; Owner: dwemer
--

ALTER TABLE ONLY public.speech ALTER COLUMN rowid SET DEFAULT nextval('public.speech_rowid_seq'::regclass);


--
-- Data for Name: books; Type: TABLE DATA; Schema: public; Owner: dwemer
--



--
-- Data for Name: currentmission; Type: TABLE DATA; Schema: public; Owner: dwemer
--



--
-- Data for Name: diarylog; Type: TABLE DATA; Schema: public; Owner: dwemer
--



--
-- Data for Name: embeddings; Type: TABLE DATA; Schema: public; Owner: dwemer
--



--
-- Data for Name: eventlog; Type: TABLE DATA; Schema: public; Owner: dwemer
--



--
-- Data for Name: memory; Type: TABLE DATA; Schema: public; Owner: dwemer
--



--
-- Data for Name: memory_summary; Type: TABLE DATA; Schema: public; Owner: dwemer
--



--
-- Data for Name: quests; Type: TABLE DATA; Schema: public; Owner: dwemer
--



--
-- Data for Name: responselog; Type: TABLE DATA; Schema: public; Owner: dwemer
--



--
-- Data for Name: speech; Type: TABLE DATA; Schema: public; Owner: dwemer
--



--
-- Name: books_rowid_seq; Type: SEQUENCE SET; Schema: public; Owner: dwemer
--

SELECT pg_catalog.setval('public.books_rowid_seq', 1, false);


--
-- Name: currentmission_rowid_seq; Type: SEQUENCE SET; Schema: public; Owner: dwemer
--

SELECT pg_catalog.setval('public.currentmission_rowid_seq', 1, false);


--
-- Name: diarylog_rowid_seq; Type: SEQUENCE SET; Schema: public; Owner: dwemer
--

SELECT pg_catalog.setval('public.diarylog_rowid_seq', 1, false);


--
-- Name: embeddings_id_seq; Type: SEQUENCE SET; Schema: public; Owner: dwemer
--

SELECT pg_catalog.setval('public.embeddings_id_seq', 1, false);


--
-- Name: eventlog_rowid_seq; Type: SEQUENCE SET; Schema: public; Owner: dwemer
--

SELECT pg_catalog.setval('public.eventlog_rowid_seq', 1, false);


--
-- Name: memory_summary_rowid_seq; Type: SEQUENCE SET; Schema: public; Owner: dwemer
--

SELECT pg_catalog.setval('public.memory_summary_rowid_seq', 139, true);


--
-- Name: memory_uid_seq; Type: SEQUENCE SET; Schema: public; Owner: dwemer
--

SELECT pg_catalog.setval('public.memory_uid_seq', 1, false);


--
-- Name: quests_rowid_seq; Type: SEQUENCE SET; Schema: public; Owner: dwemer
--

SELECT pg_catalog.setval('public.quests_rowid_seq', 1, false);


--
-- Name: responselog_rowid_seq; Type: SEQUENCE SET; Schema: public; Owner: dwemer
--

SELECT pg_catalog.setval('public.responselog_rowid_seq', 1, false);


--
-- Name: speech_rowid_seq; Type: SEQUENCE SET; Schema: public; Owner: dwemer
--

SELECT pg_catalog.setval('public.speech_rowid_seq', 1, false);


--
-- Name: embeddings embeddings_pkey; Type: CONSTRAINT; Schema: public; Owner: dwemer
--

ALTER TABLE ONLY public.embeddings
    ADD CONSTRAINT embeddings_pkey PRIMARY KEY (id);


--
-- Name: memory memory_pkey; Type: CONSTRAINT; Schema: public; Owner: dwemer
--

ALTER TABLE ONLY public.memory
    ADD CONSTRAINT memory_pkey PRIMARY KEY (uid);


--
-- PostgreSQL database dump complete
--

