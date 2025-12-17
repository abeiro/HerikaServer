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
-- Name: core_player; Type: TABLE; Schema: public; Owner: dwemer
--

CREATE TABLE public.core_player (
    id text NOT NULL,
    value text
);


ALTER TABLE public.core_player OWNER TO dwemer;

--
-- Name: COLUMN core_player.id; Type: COMMENT; Schema: public; Owner: dwemer
--

COMMENT ON COLUMN public.core_player.id IS 'Key name (e.g., player_name, appearance, speech_style, or Skyrim stats)';


--
-- Name: COLUMN core_player.value; Type: COMMENT; Schema: public; Owner: dwemer
--

COMMENT ON COLUMN public.core_player.value IS 'Value for the key';


--
-- Name: core_player core_player_pkey; Type: CONSTRAINT; Schema: public; Owner: dwemer
--

ALTER TABLE ONLY public.core_player
    ADD CONSTRAINT core_player_pkey PRIMARY KEY (id);


--
-- PostgreSQL database dump complete
--

