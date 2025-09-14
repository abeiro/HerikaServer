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
-- Name: core_llm_connector; Type: TABLE; Schema: public; Owner: dwemer
--

CREATE TABLE public.core_llm_connector (
    id integer NOT NULL,
    label text,
    metadata jsonb,
    url text,
    model text,
    provider text,
    driver text,
    reasoning_model integer,
    max_tokens integer,
    enforce_json integer DEFAULT 1,
    prefill_json integer DEFAULT 0,
    api_badge_id integer,
    json_schema integer,
    temperature numeric,
    presence_penalty numeric,
    frequency_penalty numeric,
    repetition_penalty numeric,
    top_p numeric,
    top_k integer,
    min_p numeric,
    top_a numeric
);


ALTER TABLE public.core_llm_connector OWNER TO dwemer;

--
-- Name: llm_connector_id_seq; Type: SEQUENCE; Schema: public; Owner: dwemer
--

CREATE SEQUENCE public.llm_connector_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.llm_connector_id_seq OWNER TO dwemer;

--
-- Name: llm_connector_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: dwemer
--

ALTER SEQUENCE public.llm_connector_id_seq OWNED BY public.core_llm_connector.id;


--
-- Name: core_llm_connector id; Type: DEFAULT; Schema: public; Owner: dwemer
--

ALTER TABLE ONLY public.core_llm_connector ALTER COLUMN id SET DEFAULT nextval('public.llm_connector_id_seq'::regclass);


--
-- Data for Name: core_llm_connector; Type: TABLE DATA; Schema: public; Owner: dwemer
--
INSERT INTO public.core_llm_connector VALUES (1, 'gemini flash 2', '{}', 'https://openrouter.ai/api/v1/chat/completions', 'google/gemini-2.0-flash-001', NULL, 'openrouterjson', NULL, 250, 1, 0, 1, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL);
INSERT INTO public.core_llm_connector VALUES (2, 'qwen3-235b-a22b-2507', '{}', 'https://openrouter.ai/api/v1/chat/completions', 'qwen/qwen3-235b-a22b-2507', NULL, 'openrouterjson', NULL, 250, 1, NULL, 1, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL);
INSERT INTO public.core_llm_connector VALUES (3, 'deepseek-r1-0528', '{}', 'https://openrouter.ai/api/v1/chat/completions', 'deepseek/deepseek-r1-0528', NULL, 'openrouterjson', NULL, 250, 1, NULL, 1, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL);
INSERT INTO public.core_llm_connector VALUES (4, 'openrouter llama-4-maverick', '{}', 'https://openrouter.ai/api/v1/chat/completions', 'meta-llama/llama-4-maverick', NULL, 'openrouterjson', NULL, 250, 1, NULL, 1, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL);


--
-- Name: llm_connector_id_seq; Type: SEQUENCE SET; Schema: public; Owner: dwemer
--

SELECT pg_catalog.setval('public.llm_connector_id_seq', 16, true);


--
-- Name: core_llm_connector llm_connector_pkey; Type: CONSTRAINT; Schema: public; Owner: dwemer
--

ALTER TABLE ONLY public.core_llm_connector
    ADD CONSTRAINT llm_connector_pkey PRIMARY KEY (id);


--
-- Name: core_llm_connector llm_connector_api_badge_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: dwemer
--

ALTER TABLE ONLY public.core_llm_connector
    ADD CONSTRAINT llm_connector_api_badge_id_fkey FOREIGN KEY (api_badge_id) REFERENCES public.core_api_badge(id);


--
-- PostgreSQL database dump complete
--

