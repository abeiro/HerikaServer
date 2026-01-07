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
-- Name: core_api_badge; Type: TABLE; Schema: public; Owner: dwemer
--

CREATE TABLE public.core_api_badge (
    id integer NOT NULL,
    label text NOT NULL,
    api_key text NOT NULL
);


ALTER TABLE public.core_api_badge OWNER TO dwemer;

--
-- Name: api_badge_id_seq; Type: SEQUENCE; Schema: public; Owner: dwemer
--

CREATE SEQUENCE public.api_badge_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.api_badge_id_seq OWNER TO dwemer;

--
-- Name: api_badge_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: dwemer
--

ALTER SEQUENCE public.api_badge_id_seq OWNED BY public.core_api_badge.id;


--
-- Name: core_api_badge id; Type: DEFAULT; Schema: public; Owner: dwemer
--

ALTER TABLE ONLY public.core_api_badge ALTER COLUMN id SET DEFAULT nextval('public.api_badge_id_seq'::regclass);


--
-- Data for Name: core_api_badge; Type: TABLE DATA; Schema: public; Owner: dwemer
--

INSERT INTO public.core_api_badge VALUES (1, 'OpenRouter', '');
INSERT INTO public.core_api_badge VALUES (2, 'OpenAI', '');
INSERT INTO public.core_api_badge VALUES (3, 'Deepgram', '');
INSERT INTO public.core_api_badge VALUES (4, 'Google', '');
INSERT INTO public.core_api_badge VALUES (5, 'Azure', '');
INSERT INTO public.core_api_badge VALUES (6, 'ElevenLabs', '');
INSERT INTO public.core_api_badge VALUES (7, 'Replicate', '');
INSERT INTO public.core_api_badge VALUES (8, 'Cartesia', '');
INSERT INTO public.core_api_badge VALUES (9, 'Nano-GPT', '');
INSERT INTO public.core_api_badge VALUES (10, 'DeepL', '');
INSERT INTO public.core_api_badge VALUES (11, 'Inworld', '');
INSERT INTO public.core_api_badge VALUES (12, 'Groq', '');


--
-- Name: api_badge_id_seq; Type: SEQUENCE SET; Schema: public; Owner: dwemer
--

SELECT pg_catalog.setval('public.api_badge_id_seq', 2, true);


--
-- Name: core_api_badge my_table_pkey; Type: CONSTRAINT; Schema: public; Owner: dwemer
--

ALTER TABLE ONLY public.core_api_badge
    ADD CONSTRAINT my_table_pkey PRIMARY KEY (id);


--
-- Name: core_api_badge core_api_badge_label_unique; Type: CONSTRAINT; Schema: public; Owner: dwemer
--

ALTER TABLE ONLY public.core_api_badge
    ADD CONSTRAINT core_api_badge_label_unique UNIQUE (label);


--
-- Name: idx_core_api_badge_label_lower; Type: INDEX; Schema: public; Owner: dwemer
--

CREATE INDEX idx_core_api_badge_label_lower ON public.core_api_badge USING btree (lower(label));


--
-- PostgreSQL database dump complete
--

