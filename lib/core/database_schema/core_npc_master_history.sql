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
-- Name: core_npc_master_history; Type: TABLE; Schema: public; Owner: dwemer
--

CREATE TABLE public.core_npc_master_history (
    history_id integer NOT NULL,
    npc_id integer NOT NULL,
    npc_name text,
    npc_favorite integer,
    lock_profile integer,
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
    created timestamp without time zone DEFAULT now(),
    core text,
    base text,
    tags text
);


ALTER TABLE public.core_npc_master_history OWNER TO dwemer;

--
-- Name: core_npc_master_history_history_id_seq; Type: SEQUENCE; Schema: public; Owner: dwemer
--

CREATE SEQUENCE public.core_npc_master_history_history_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.core_npc_master_history_history_id_seq OWNER TO dwemer;

--
-- Name: core_npc_master_history_history_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: dwemer
--

ALTER SEQUENCE public.core_npc_master_history_history_id_seq OWNED BY public.core_npc_master_history.history_id;


--
-- Name: core_npc_master_history history_id; Type: DEFAULT; Schema: public; Owner: dwemer
--

ALTER TABLE ONLY public.core_npc_master_history ALTER COLUMN history_id SET DEFAULT nextval('public.core_npc_master_history_history_id_seq'::regclass);


--
-- Name: core_npc_master_history core_npc_master_history_pkey; Type: CONSTRAINT; Schema: public; Owner: dwemer
--

ALTER TABLE ONLY public.core_npc_master_history
    ADD CONSTRAINT core_npc_master_history_pkey PRIMARY KEY (history_id);


--
-- Name: idx_core_npc_master_history_restore; Type: INDEX; Schema: public; Owner: dwemer
--

CREATE INDEX IF NOT EXISTS idx_core_npc_master_history_restore
    ON public.core_npc_master_history (
        npc_id,
        gamets_last_updated DESC NULLS LAST,
        (CASE WHEN extended_data ->> '_chim_history_source' = 'infosave' THEN 1 ELSE 0 END) DESC,
        created DESC
    );


--
-- PostgreSQL database dump complete
--

