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

CREATE TABLE IF NOT EXISTS public.quest_item_types (
    type_key text NOT NULL,
    formid bigint NOT NULL,
    active boolean NOT NULL DEFAULT true,
    note text,
    created_at timestamp with time zone DEFAULT now(),
    updated_at timestamp with time zone DEFAULT now(),
    CONSTRAINT quest_item_types_pk PRIMARY KEY (type_key, formid)
);

CREATE TABLE IF NOT EXISTS public.quest_npc_templates (
    template_key text NOT NULL,
    formid bigint NOT NULL,
    active boolean NOT NULL DEFAULT true,
    note text,
    created_at timestamp with time zone DEFAULT now(),
    updated_at timestamp with time zone DEFAULT now(),
    CONSTRAINT quest_npc_templates_pk PRIMARY KEY (template_key, formid)
);

CREATE TABLE IF NOT EXISTS public.quest_npc_own_templates (
    template_key text NOT NULL,
    formid bigint NOT NULL,
    active boolean NOT NULL DEFAULT true,
    note text,
    created_at timestamp with time zone DEFAULT now(),
    updated_at timestamp with time zone DEFAULT now(),
    CONSTRAINT quest_npc_own_templates_pk PRIMARY KEY (template_key, formid)
);

CREATE TABLE IF NOT EXISTS public.quest_outfits (
    class_key text NOT NULL,
    formid bigint NOT NULL,
    active boolean NOT NULL DEFAULT true,
    note text,
    created_at timestamp with time zone DEFAULT now(),
    updated_at timestamp with time zone DEFAULT now(),
    CONSTRAINT quest_outfits_pk PRIMARY KEY (class_key, formid)
);

CREATE INDEX IF NOT EXISTS idx_quest_item_types_active
    ON public.quest_item_types (active);
CREATE INDEX IF NOT EXISTS idx_quest_npc_templates_active
    ON public.quest_npc_templates (active);
CREATE INDEX IF NOT EXISTS idx_quest_npc_own_templates_active
    ON public.quest_npc_own_templates (active);
CREATE INDEX IF NOT EXISTS idx_quest_outfits_active
    ON public.quest_outfits (active);
