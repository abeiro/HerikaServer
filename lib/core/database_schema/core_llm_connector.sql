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
    top_a numeric,
    service text
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



INSERT INTO public.core_llm_connector (
    id, label, metadata, url, model, provider, driver, reasoning_model,
    max_tokens, enforce_json, prefill_json, api_badge_id, json_schema, temperature, service
) VALUES
    (1, 'DeepSeek V4 Flash', '{}', 'https://openrouter.ai/api/v1/chat/completions', 'deepseek/deepseek-v4-flash', 'openrouter', 'openrouterjson', 1, 750, 1, 0, 1, 1, 0.6, 'openrouter'),
    (2, 'Gemini 2.5 Flash Lite', '{}', 'https://openrouter.ai/api/v1/chat/completions', 'google/gemini-2.5-flash-lite', 'openrouter', 'openrouterjson', NULL, 750, 1, 0, 1, 1, 1, 'openrouter'),
    (3, 'GLM 5.2',               '{}', 'https://openrouter.ai/api/v1/chat/completions', 'z-ai/glm-5.2', 'openrouter', 'openrouterjson', 1, 750, 1, 0, 1, 1, 1, 'openrouter'),
    (4, 'DeepSeek V4 Pro',          '{}', 'https://openrouter.ai/api/v1/chat/completions', 'deepseek/deepseek-v4-pro', 'openrouter', 'openrouterjson', NULL, 750, 1, 0, 1, 1, 0.6, 'openrouter'),
    (5, 'Mistral Small 3.2 24B', '{}', 'https://openrouter.ai/api/v1/chat/completions', 'mistralai/mistral-small-3.2-24b-instruct', 'openrouter', 'openrouterjson', NULL, 750, 1, 0, 1, 1, 1, 'openrouter'),
    (6, 'Ministral 8B',   '{}', 'https://openrouter.ai/api/v1/chat/completions', 'mistralai/ministral-8b-2512', 'openrouter', 'openrouterjson', NULL, 750, 1, 0, 1, 1, 1, 'openrouter'),
    (7, 'Gemma 3N E4B', '{}', 'https://openrouter.ai/api/v1/chat/completions', 'google/gemma-3n-e4b-it', 'openrouter', 'openrouterjson', NULL, 128, 1, 0, 1, 1, 0.2, 'openrouter');


--
-- Name: llm_connector_id_seq; Type: SEQUENCE SET; Schema: public; Owner: dwemer
--

SELECT pg_catalog.setval('public.llm_connector_id_seq', 7, true);


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

