-- Dumped from database version 15.10 (Debian 15.10-0+deb12u1)
-- Dumped by pg_dump version 15.10 (Debian 15.10-0+deb12u1)

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

--
--
--
DROP VIEW IF EXISTS public.speech_view;
DROP VIEW IF EXISTS public.eventlog_view;

DROP FUNCTION IF EXISTS public.convert_gamets2days(gamets bigint) CASCADE;
DROP FUNCTION IF EXISTS public.convert_gamets2gregorian_date(gamets bigint) CASCADE;
DROP FUNCTION IF EXISTS public.convert_gamets2skyrim_long_date(gamets bigint) CASCADE;
DROP FUNCTION IF EXISTS public.convert_gamets2skyrim_long_date2(gamets bigint) CASCADE;
DROP FUNCTION IF EXISTS public.convert_gamets2skyrim_date(gamets bigint) CASCADE;
DROP FUNCTION IF EXISTS public.convert_gamets2hours(gamets bigint) CASCADE;


--
-- Name: convert_gamets2days(bigint); Type: FUNCTION; Schema: public; Owner: dwemer
-- Convert gamets to number of days from Skyrim game start. 
-- Skyrim begining date is 0201-08-17 00:00:00
--

CREATE OR REPLACE FUNCTION public.convert_gamets2days(gamets bigint) RETURNS bigint
    LANGUAGE plpgsql
    AS $$
    BEGIN
        RETURN floor(gamets * 0.0000001);
    END;
$$;

ALTER FUNCTION public.convert_gamets2days(gamets bigint) OWNER TO dwemer;

--
-- Name: convert_gamets2gregorian_date(bigint); Type: FUNCTION; Schema: public; Owner: dwemer
-- Convert gamets to Gregorian equivalent date. 
-- Skyrim begining date is 0201-08-17 00:00:00. 
-- Gregorian calendar year 0 correspond to 2E 47
--

CREATE OR REPLACE FUNCTION public.convert_gamets2gregorian_date(gamets bigint) RETURNS text
    LANGUAGE plpgsql
    AS $$
    BEGIN
        RETURN to_char(to_timestamp('1577.08.17 00:00:00','YYYY.MM.DD HH24:MI:SS') + (gamets * 24.0 * 0.0000001) * INTERVAL '1 hour', 'YYYY-MM-DD HH24:MI:SS');
    END;
$$;


ALTER FUNCTION public.convert_gamets2gregorian_date(gamets bigint) OWNER TO dwemer;

--
-- Name: convert_gamets2hours(bigint); Type: FUNCTION; Schema: public; Owner: dwemer
-- Convert gamets to hours since Skyrim start date. 
-- Skyrim begining date is 0201-08-17 00:00:00. 
--

CREATE OR REPLACE FUNCTION public.convert_gamets2hours(gamets bigint) RETURNS bigint
    LANGUAGE plpgsql
    AS $$
    BEGIN
        RETURN floor(gamets * 24.0 * 0.0000001);
    END;
$$;


ALTER FUNCTION public.convert_gamets2hours(gamets bigint) OWNER TO dwemer;

--
-- Name: convert_gamets2skyrim_date(bigint); Type: FUNCTION; Schema: public; Owner: dwemer
-- Convert gamets to Skyrim short date - 0201-08-17 10:17:15. 
-- Skyrim begining date is 0201-08-17 00:00:00. 
--

CREATE OR REPLACE FUNCTION public.convert_gamets2skyrim_date(gamets bigint) RETURNS text
    LANGUAGE plpgsql
    AS $$
    BEGIN
        RETURN to_char(to_timestamp('0201.08.17 00:00:00','YYYY.MM.DD HH24:MI:SS') + (gamets * 24.0 * 0.0000001) * INTERVAL '1 hour', 'YYYY-MM-DD HH24:MI:SS');
    END;
$$;


ALTER FUNCTION public.convert_gamets2skyrim_date(gamets bigint) OWNER TO dwemer;


--
-- Name: convert_gamets2skyrim_long_date(bigint); Type: FUNCTION; Schema: public; Owner: dwemer
-- Convert gamets to Skyrim long date - 23:14, 17th of Last Seed, 4E 201
-- Skyrim begining date is 0201-08-17 00:00:00. 
--

CREATE OR REPLACE FUNCTION public.convert_gamets2skyrim_long_date(gamets bigint) RETURNS text
    LANGUAGE plpgsql
    AS $$
    DECLARE 
        s_date1 text; 
        s_date2 text; 
        s_date3 text; 
        s_month text;
		s_dayweek text;
		s_dayname text;
        s_longm text;
        f_hours float;
        ts_base timestamp;
        s_res text;
    BEGIN
        f_hours := (gamets * 24.0 * 0.0000001);
        ts_base := to_timestamp('0201.08.17 00:00:00','YYYY.MM.DD HH24:MI:SS');
        s_month := to_char(ts_base  + f_hours * INTERVAL '1 hour', 'MM');
        s_dayweek := to_char(ts_base  + f_hours * INTERVAL '1 hour', 'D'); -- D	day of the week, 
        CASE s_dayweek
            WHEN '2' THEN s_dayname := 'Sundas'; -- sunday
            WHEN '3' THEN s_dayname := 'Morndas';
            WHEN '4' THEN s_dayname := 'Tirdas';
            WHEN '5' THEN s_dayname := 'Middas';
            WHEN '6' THEN s_dayname := 'Turdas';
            WHEN '7' THEN s_dayname := 'Fredas';
            WHEN '1' THEN s_dayname := 'Loredas'; -- saturday
            ELSE s_dayname := 'unknown day';
        END CASE;
        CASE s_month
            WHEN '01' THEN s_longm := 'Morning Star';
            WHEN '02' THEN s_longm := 'Sun''s Dawn';
            WHEN '03' THEN s_longm := 'First Seed';
            WHEN '04' THEN s_longm := 'Rain''s Hand';
            WHEN '05' THEN s_longm := 'Second Seed';
            WHEN '06' THEN s_longm := 'Mid Year';
            WHEN '07' THEN s_longm := 'Sun''s Height';
            WHEN '08' THEN s_longm := 'Last Seed';
            WHEN '09' THEN s_longm := 'Hearthfire';
            WHEN '10' THEN s_longm := 'Frost Fall';
            WHEN '11' THEN s_longm := 'Sun''s Dusk';
            WHEN '12' THEN s_longm := 'Evening Star';
            ELSE s_longm := 'unknown month';
        END CASE;
        s_date1 := to_char(ts_base + f_hours * INTERVAL '1 hour', 'HH12:MI AM');
        s_date2 := to_char(ts_base + f_hours * INTERVAL '1 hour', 'FMDD');
        s_date3 := to_char(ts_base + f_hours * INTERVAL '1 hour', ', 4E FMYYYY');
        s_res := s_dayname || ', ' || s_date1 || ', ' || s_date2 ||  'th of ' || s_longm || s_date3;
        RETURN s_res;
    END;
$$;

ALTER FUNCTION public.convert_gamets2skyrim_long_date(gamets bigint) OWNER TO dwemer;


--
-- Name: convert_gamets2skyrim_long_date(bigint); Type: FUNCTION; Schema: public; Owner: dwemer
-- Convert gamets to Skyrim long date - 17th of Last Seed 4E 201 10:17
-- Skyrim begining date is 0201-08-17 00:00:00. 
--

CREATE OR REPLACE FUNCTION public.convert_gamets2skyrim_long_date2(gamets bigint) RETURNS text
    LANGUAGE plpgsql
    AS $$
    DECLARE 
        s_date1 text; 
        s_date2 text; 
        s_month text;
        s_longm text;
        f_hours float;
        ts_base timestamp;
        s_res text;
    BEGIN
        f_hours := (gamets * 24.0 * 0.0000001);
        ts_base := to_timestamp('0201.08.17 00:00:00','YYYY.MM.DD HH24:MI:SS');
        s_month := to_char(ts_base  + f_hours * INTERVAL '1 hour', 'MM');
        CASE s_month
            WHEN '01' THEN s_longm := 'Morning Star';
            WHEN '02' THEN s_longm := 'Sun''s Dawn';
            WHEN '03' THEN s_longm := 'First Seed';
            WHEN '04' THEN s_longm := 'Rain''s Hand';
            WHEN '05' THEN s_longm := 'Second Seed';
            WHEN '06' THEN s_longm := 'Mid Year';
            WHEN '07' THEN s_longm := 'Sun''s Height';
            WHEN '08' THEN s_longm := 'Last Seed';
            WHEN '09' THEN s_longm := 'Hearthfire';
            WHEN '10' THEN s_longm := 'Frost Fall';
            WHEN '11' THEN s_longm := 'Sun''s Dusk';
            WHEN '12' THEN s_longm := 'Evening Star';
            ELSE s_longm := 'unknown';
        END CASE;
        s_date1 := to_char(ts_base + f_hours * INTERVAL '1 hour', 'DD');
        s_date2 := to_char(ts_base + f_hours * INTERVAL '1 hour', ' 4E FMYYYY, HH24:MI');
        s_res := s_date1 || 'th of ' || s_longm || s_date2;
        RETURN s_res;
    END;
$$;

ALTER FUNCTION public.convert_gamets2skyrim_long_date2(gamets bigint) OWNER TO dwemer;


--
-- Name: eventlog_view; Type: VIEW; Schema: public; Owner: dwemer
--

CREATE OR REPLACE VIEW public.eventlog_view AS
 SELECT e.*,
    public.convert_gamets2skyrim_date(e.gamets) AS sk_date,
    public.convert_gamets2skyrim_long_date(e.gamets) AS sk_long_date,
    public.convert_gamets2days(e.gamets) AS sk_days,
    public.convert_gamets2gregorian_date(e.gamets) AS gregorian_date
   FROM public.eventlog e;

ALTER TABLE public.eventlog_view OWNER TO dwemer;


--
-- Name: speech_view; Type: VIEW; Schema: public; Owner: dwemer
--

CREATE OR REPLACE VIEW public.speech_view AS
 SELECT s.*,
    public.convert_gamets2skyrim_date(s.gamets) AS sk_date,
    public.convert_gamets2skyrim_long_date(s.gamets) AS sk_long_date,
    public.convert_gamets2days(s.gamets) AS sk_days,
    public.convert_gamets2gregorian_date(s.gamets) AS gregorian_date
   FROM public.speech s;

ALTER TABLE public.speech_view OWNER TO dwemer;

--
-- PostgreSQL database dump complete
--
