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


-- Step 1: Add the new column
ALTER TABLE public.core_profiles
ADD COLUMN llm_formatter_id integer;

-- Step 2: Add the foreign key constraint
ALTER TABLE public.core_profiles
ADD CONSTRAINT profiles_llm_formatter_id_fkey
FOREIGN KEY (llm_formatter_id) REFERENCES public.core_llm_connector(id);


