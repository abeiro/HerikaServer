--
-- PostgreSQL database dump
--

-- Dumped from database version 16.4 (Debian 16.4-3+b1)
-- Dumped by pg_dump version 17.5 (Debian 17.5-1)

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

SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: core_npc_master; Type: TABLE; Schema: public; Owner: dwemer
--

CREATE TABLE public.core_npc_master (
    id integer NOT NULL,
    npc_name text NOT NULL,
    npc_favorite integer DEFAULT 0,
    lock_profile integer DEFAULT 0,
    prompt_head text,
    npc_static_bio text,
    oghma_knowledge_tags text,
    emote_moods text,
    personality text,
    relationships text,
    occupation text,
    appearance text,
    skills text,
    speechstyle text,
    goals text,
    voiceid text,
    metadata jsonb,
    gender text,
    race text,
    refid character varying(16),
    profile_id integer,
    dynamic_profile integer,
    extended_data jsonb,
    md5 text,
    gamets_last_updated numeric,
    core text,
    base text,
    tags text,
    profile_owner_npc_id integer
);


ALTER TABLE public.core_npc_master OWNER TO dwemer;

--
-- Name: COLUMN core_npc_master.personality; Type: COMMENT; Schema: public; Owner: dwemer
--

COMMENT ON COLUMN public.core_npc_master.personality IS 'how they behave';


--
-- Name: COLUMN core_npc_master.core; Type: COMMENT; Schema: public; Owner: dwemer
--

COMMENT ON COLUMN public.core_npc_master.core IS 'really quick summary of character';


--
-- Name: COLUMN core_npc_master.tags; Type: COMMENT; Schema: public; Owner: dwemer
--

COMMENT ON COLUMN public.core_npc_master.tags IS 'comma separated,user tags';


--
-- Name: npc_master_id_seq; Type: SEQUENCE; Schema: public; Owner: dwemer
--

CREATE SEQUENCE public.npc_master_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.npc_master_id_seq OWNER TO dwemer;

--
-- Name: npc_master_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: dwemer
--

ALTER SEQUENCE public.npc_master_id_seq OWNED BY public.core_npc_master.id;


--
-- Name: core_npc_master id; Type: DEFAULT; Schema: public; Owner: dwemer
--

ALTER TABLE ONLY public.core_npc_master ALTER COLUMN id SET DEFAULT nextval('public.npc_master_id_seq'::regclass);


--
-- Data for Name: core_npc_master; Type: TABLE DATA; Schema: public; Owner: dwemer
--


--
-- Name: npc_master_id_seq; Type: SEQUENCE SET; Schema: public; Owner: dwemer
--

SELECT pg_catalog.setval('public.npc_master_id_seq', 2189, true);


--
-- Name: core_npc_master display identity; Type: INDEX; Schema: public; Owner: dwemer
--

CREATE UNIQUE INDEX idx_core_npc_master_display_identity
    ON public.core_npc_master (lower(npc_name), upper(refid))
    WHERE refid IS NOT NULL AND BTRIM(refid) <> '';

CREATE INDEX idx_core_npc_master_name_lookup
    ON public.core_npc_master (lower(npc_name), id);

CREATE INDEX idx_core_npc_master_refid_lookup
    ON public.core_npc_master (lower(refid))
    WHERE refid IS NOT NULL;


--
-- Name: core_npc_master npc_master_pkey; Type: CONSTRAINT; Schema: public; Owner: dwemer
--

ALTER TABLE ONLY public.core_npc_master
    ADD CONSTRAINT npc_master_pkey PRIMARY KEY (id);

CREATE INDEX idx_npc_profile_owner ON public.core_npc_master (profile_owner_npc_id)
    WHERE profile_owner_npc_id IS NOT NULL;

ALTER TABLE public.core_npc_master ADD CONSTRAINT npc_profile_owner_not_self
    CHECK (profile_owner_npc_id IS NULL OR profile_owner_npc_id <> id);
ALTER TABLE public.core_npc_master ADD CONSTRAINT npc_profile_owner_fk
    FOREIGN KEY (profile_owner_npc_id) REFERENCES public.core_npc_master(id)
    DEFERRABLE INITIALLY DEFERRED;


--
-- Name: core_npc_master fk_profile_id; Type: FK CONSTRAINT; Schema: public; Owner: dwemer
--

ALTER TABLE ONLY public.core_npc_master
    ADD CONSTRAINT fk_profile_id FOREIGN KEY (profile_id) REFERENCES public.core_profiles(id) ON DELETE SET NULL;


--
-- PostgreSQL database dump complete
--

