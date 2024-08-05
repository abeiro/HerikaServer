--
-- PostgreSQL database dump
--

-- Dumped from database version 16.3 (Debian 16.3-1+b1)
-- Dumped by pg_dump version 16.3 (Debian 16.3-1+b1)

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
-- Name: conf_opts; Type: TABLE; Schema: public; Owner: dwemer
--

ALTER TABLE "public"."eventlog" ADD COLUMN "people" text


--
-- PostgreSQL database dump complete
--
