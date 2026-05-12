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
-- Name: core_stt_connector; Type: TABLE; Schema: public; Owner: dwemer
--

CREATE TABLE public.core_stt_connector (
    id integer NOT NULL,
    driver text,
    label text,
    metadata jsonb,
    api_badge_id integer,
    url text
);


ALTER TABLE public.core_stt_connector OWNER TO dwemer;

--
-- Name: stt_connector_id_seq; Type: SEQUENCE; Schema: public; Owner: dwemer
--

CREATE SEQUENCE public.stt_connector_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.stt_connector_id_seq OWNER TO dwemer;

--
-- Name: stt_connector_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: dwemer
--

ALTER SEQUENCE public.stt_connector_id_seq OWNED BY public.core_stt_connector.id;


--
-- Name: core_stt_connector id; Type: DEFAULT; Schema: public; Owner: dwemer
--

ALTER TABLE ONLY public.core_stt_connector ALTER COLUMN id SET DEFAULT nextval('public.stt_connector_id_seq'::regclass);


--
-- Data for Name: core_stt_connector; Type: TABLE DATA; Schema: public; Owner: dwemer
--



--
-- Name: stt_connector_id_seq; Type: SEQUENCE SET; Schema: public; Owner: dwemer
--

SELECT pg_catalog.setval('public.stt_connector_id_seq', 1, false);


--
-- Name: core_stt_connector stt_connector_pkey; Type: CONSTRAINT; Schema: public; Owner: dwemer
--

ALTER TABLE ONLY public.core_stt_connector
    ADD CONSTRAINT stt_connector_pkey PRIMARY KEY (id);


--
-- Name: core_stt_connector stt_connector_api_badge_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: dwemer
--

ALTER TABLE ONLY public.core_stt_connector
    ADD CONSTRAINT stt_connector_api_badge_id_fkey FOREIGN KEY (api_badge_id) REFERENCES public.core_api_badge(id);


--
-- PostgreSQL database dump complete
--

