--
-- Database-driven general settings for HerikaServer
--

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
-- Name: general_settings; Type: TABLE; Schema: public; Owner: dwemer
--

CREATE TABLE public.general_settings (
    id text NOT NULL,
    value text,
    description text DEFAULT ''::text,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.general_settings OWNER TO dwemer;

--
-- Name: general_settings general_settings_pkey; Type: CONSTRAINT; Schema: public; Owner: dwemer
--

ALTER TABLE ONLY public.general_settings
    ADD CONSTRAINT general_settings_pkey PRIMARY KEY (id);

