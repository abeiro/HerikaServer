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
-- Name: core_profiles; Type: TABLE; Schema: public; Owner: dwemer
--

CREATE TABLE public.core_profiles (
    id integer NOT NULL,
    label text,
    default_npc text,
    default_narrator text,
    tts_connector_id integer,
    itt_connector_id integer,
    llm_primary_id integer,
    llm_secondary_id integer,
    llm_tertiary_id integer,
    llm_quaternary_id integer,
    llm_formatter_id integer,
    llm_fallback_id integer,
    metadata jsonb,
    diary_connector_id integer,
    slot integer,
    prompt text
);


ALTER TABLE public.core_profiles OWNER TO dwemer;

--
-- Name: COLUMN core_profiles.prompt; Type: COMMENT; Schema: public; Owner: dwemer
--

COMMENT ON COLUMN public.core_profiles.prompt IS 'profile specific prompt, will be added to context';


--
-- Name: profiles_id_seq; Type: SEQUENCE; Schema: public; Owner: dwemer
--

CREATE SEQUENCE public.profiles_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.profiles_id_seq OWNER TO dwemer;

--
-- Name: profiles_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: dwemer
--

ALTER SEQUENCE public.profiles_id_seq OWNED BY public.core_profiles.id;


--
-- Name: core_profiles id; Type: DEFAULT; Schema: public; Owner: dwemer
--

ALTER TABLE ONLY public.core_profiles ALTER COLUMN id SET DEFAULT nextval('public.profiles_id_seq'::regclass);


--
-- Data for Name: core_profiles; Type: TABLE DATA; Schema: public; Owner: dwemer
--

INSERT INTO public.core_profiles (
    id,
    label,
    default_npc,
    default_narrator,
    tts_connector_id,
    itt_connector_id,
    llm_primary_id,
    llm_secondary_id,
    llm_tertiary_id,
    llm_quaternary_id,
    llm_formatter_id,
    llm_fallback_id,
    metadata,
    diary_connector_id,
    slot,
    prompt
) VALUES (
    1,
    'Default Profile',
    '1',
    '1',
    1,
    NULL,
    1,
    2,
    3,
    4,
    6,
    NULL,
    '{"RECHAT_H": "4", "RECHAT_P": "100", "CORE_LANG": "", "MINIME_T5": false, "BORED_EVENT": "50", "CURRENT_TASK": true, "DIARY_PROMPT": "Please write a short summary of #PLAYER_NAME# and #HERIKA_NAME#''s last dialogues and events written above into #HERIKA_NAME#''s diary . WRITE AS IF YOU WERE #HERIKA_NAME#. Start the diary entry with the current date and time.", "ALIVE_MESSAGE": true, "LANG_LLM_XTTS": true, "QUEST_COMMENT": false, "DIARY_COOLDOWN": "120", "TIME_AWARENESS": false, "AUTO_DIARY_WAIT": true, "CONTEXT_HISTORY": "75", "MAX_WORDS_LIMIT": "0", "HERIKA_ANIMATIONS": true, "QUEST_COMMENT_CHANCE": "10%", "RECHAT_ALLOW_ACTIONS": true, "CONTEXT_HISTORY_DIARY": "100", "ENFORCE_ACTIONS_PROMPT": false, "CONTEXT_HISTORY_DYNAMIC_PROFILE": "50", "DYNAMIC_PROFILE_FIELDS": ["personality", "speechstyle", "goals"], "RPG_COMMENTS": ["levelup", "combat_end", "bleedout"], "RPG_COMMENTS_CHANCE": "50"}',
    1,
    1,
    NULL
);


--
-- Name: profiles_id_seq; Type: SEQUENCE SET; Schema: public; Owner: dwemer
--

SELECT pg_catalog.setval('public.profiles_id_seq', 4, true);


--
-- Name: core_profiles profiles_pkey; Type: CONSTRAINT; Schema: public; Owner: dwemer
--

ALTER TABLE ONLY public.core_profiles
    ADD CONSTRAINT profiles_pkey PRIMARY KEY (id);


--
-- Name: core_profiles fk_diary_connector; Type: FK CONSTRAINT; Schema: public; Owner: dwemer
--

ALTER TABLE ONLY public.core_profiles
    ADD CONSTRAINT fk_diary_connector FOREIGN KEY (diary_connector_id) REFERENCES public.core_llm_connector(id);


--
-- Name: core_profiles profiles_itt_connector_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: dwemer
--

ALTER TABLE ONLY public.core_profiles
    ADD CONSTRAINT profiles_itt_connector_id_fkey FOREIGN KEY (itt_connector_id) REFERENCES public.core_itt_connector(id);


--
-- Name: core_profiles profiles_llm_primary_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: dwemer
--

ALTER TABLE ONLY public.core_profiles
    ADD CONSTRAINT profiles_llm_primary_id_fkey FOREIGN KEY (llm_primary_id) REFERENCES public.core_llm_connector(id);


--
-- Name: core_profiles profiles_llm_quaternary_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: dwemer
--

ALTER TABLE ONLY public.core_profiles
    ADD CONSTRAINT profiles_llm_quaternary_id_fkey FOREIGN KEY (llm_quaternary_id) REFERENCES public.core_llm_connector(id);


--
-- Name: core_profiles profiles_llm_secondary_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: dwemer
--

ALTER TABLE ONLY public.core_profiles
    ADD CONSTRAINT profiles_llm_secondary_id_fkey FOREIGN KEY (llm_secondary_id) REFERENCES public.core_llm_connector(id);


--
-- Name: core_profiles profiles_llm_tertiary_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: dwemer
--

ALTER TABLE ONLY public.core_profiles
    ADD CONSTRAINT profiles_llm_tertiary_id_fkey FOREIGN KEY (llm_tertiary_id) REFERENCES public.core_llm_connector(id);

--
-- Name: core_profiles profiles_llm_formatter_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: dwemer
--

ALTER TABLE ONLY public.core_profiles
    ADD CONSTRAINT profiles_llm_formatter_id_fkey FOREIGN KEY (llm_formatter_id) REFERENCES public.core_llm_connector(id);


--
-- Name: core_profiles profiles_llm_fallback_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: dwemer
--

ALTER TABLE ONLY public.core_profiles
    ADD CONSTRAINT profiles_llm_fallback_id_fkey FOREIGN KEY (llm_fallback_id) REFERENCES public.core_llm_connector(id);


--
-- Name: core_profiles profiles_tts_connector_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: dwemer
--

ALTER TABLE ONLY public.core_profiles
    ADD CONSTRAINT profiles_tts_connector_id_fkey FOREIGN KEY (tts_connector_id) REFERENCES public.core_tts_connector(id);


--
-- PostgreSQL database dump complete
--

