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
-- Name: core_tts_connector; Type: TABLE; Schema: public; Owner: dwemer
--

CREATE TABLE public.core_tts_connector (
    id integer NOT NULL,
    driver text,
    label text,
    metadata jsonb,
    api_badge_id integer,
    url text,
    voice_field text
);


ALTER TABLE public.core_tts_connector OWNER TO dwemer;

--
-- Name: tts_connector_id_seq; Type: SEQUENCE; Schema: public; Owner: dwemer
--

CREATE SEQUENCE public.tts_connector_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.tts_connector_id_seq OWNER TO dwemer;

--
-- Name: tts_connector_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: dwemer
--

ALTER SEQUENCE public.tts_connector_id_seq OWNED BY public.core_tts_connector.id;


--
-- Name: core_tts_connector id; Type: DEFAULT; Schema: public; Owner: dwemer
--

ALTER TABLE ONLY public.core_tts_connector ALTER COLUMN id SET DEFAULT nextval('public.tts_connector_id_seq'::regclass);


--
-- Data for Name: core_tts_connector; Type: TABLE DATA; Schema: public; Owner: dwemer
--

INSERT INTO public.core_tts_connector VALUES (1, 'pockettts', 'ddistro pockettts', '{"_title": "PocketTTS (Installed in DwemerDistro)", "voiceid": {"type": "string", "helpurl": "/HerikaServer/ui/xtts_clone.php", "description": "Generated voice file name. Click the help link to go to voice management."}, "endpoint": {"type": "url", "helpurl": "https://dwemerdynamics.com/chim/tts.html#PocketTts", "description": "Endpoint URL. DwemerDistro audio.cpp PocketTTS uses port 8086. Python PocketTTS uses dedicated port 8024."}, "model": {"type": "string", "description": "audio.cpp model id. Default: pocket-tts."}, "language": {"type": "select", "values": ["ar", "pt", "zh-cn", "cs", "nl", "en", "fr", "de", "it", "pl", "ru", "es", "tr", "ja", "ko", "hu", "hi"], "description": "Language"}, "voicelogic": {"type": "select", "scope": "global", "values": ["voicetype", "name"], "description": "Default profile only. Logic used for generating [TTS POCKETTTS voiceid] <br> voicetype = NPC voicetype ID (femalenord) <br> name = NPC name (mjoll_the_lioness)"}}', NULL, 'http://127.0.0.1:8086', 'voiceid');
INSERT INTO public.core_tts_connector VALUES (2, 'omnivoice', 'OmniVoice Default', '{"language": "en", "voicelogic": "voicetype", "fallback_male": "malenord", "fallback_female": "femalenord"}', NULL, 'http://127.0.0.1:8021', 'voiceid');


--
-- Name: tts_connector_id_seq; Type: SEQUENCE SET; Schema: public; Owner: dwemer
--

SELECT pg_catalog.setval('public.tts_connector_id_seq', 2, true);


--
-- Name: core_tts_connector tts_connector_pkey; Type: CONSTRAINT; Schema: public; Owner: dwemer
--

ALTER TABLE ONLY public.core_tts_connector
    ADD CONSTRAINT tts_connector_pkey PRIMARY KEY (id);


--
-- Name: core_tts_connector tts_connector_api_badge_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: dwemer
--

ALTER TABLE ONLY public.core_tts_connector
    ADD CONSTRAINT tts_connector_api_badge_id_fkey FOREIGN KEY (api_badge_id) REFERENCES public.core_api_badge(id);


--
-- PostgreSQL database dump complete
--

