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

-- Step 1: Add column appearance if it does not exist
ALTER TABLE public.core_npc_master_history
    ADD COLUMN IF NOT EXISTS appearance text;

ALTER TABLE public.core_npc_master
    ADD COLUMN IF NOT EXISTS appearance text;

-- Step 2: Add llm_formatter_id column if not exists
ALTER TABLE public.core_profiles
    ADD COLUMN IF NOT EXISTS llm_formatter_id integer;

-- Step 3: Add foreign key constraint only if not exists
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'profiles_llm_formatter_id_fkey'
          AND conrelid = 'public.core_profiles'::regclass
    ) THEN
        ALTER TABLE public.core_profiles
            ADD CONSTRAINT profiles_llm_formatter_id_fkey
            FOREIGN KEY (llm_formatter_id)
            REFERENCES public.core_llm_connector(id);
    END IF;
END$$;

