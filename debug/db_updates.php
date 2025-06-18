<?php 

require_once(dirname(__DIR__).DIRECTORY_SEPARATOR."lib/logger.php");

$checkVersion = function($tablename) {
    global $db;
    $query = "
    SELECT version 
    FROM public.database_versioning
    WHERE tablename = '$tablename'
    ";

    $existsColumn=$db->fetchAll($query);

    if (sizeof($existsColumn) == 0 || !$existsColumn[0]["version"] )
        return -1;
    else
        return $existsColumn[0]["version"]+0;
};

$updateVersion = function($tablename,$version) {
    global $db;
    $db->execQuery("INSERT INTO public.database_versioning SELECT '$tablename',$version where not exists (SELECT 1 from public.database_versioning where tablename='$tablename')");
    $db->execQuery("UPDATE public.database_versioning set version=$version WHERE tablename='$tablename'");
    Logger::info("TABLE $tablename updated to version $version");
};

/////////////////////////

$query = "
    SELECT column_name 
    FROM information_schema.columns 
    WHERE table_name = 'eventlog' AND column_name = 'people'
";

$existsColumn=$db->fetchAll($query);
if (!$existsColumn[0]["column_name"]) {
    $db->execQuery('ALTER TABLE "eventlog" ADD COLUMN "people" text');
    echo '<script>alert("A patch (0.1.2) has been applied to Database")</script>';
}

// Add location info to event log

$query = "
    SELECT column_name 
    FROM information_schema.columns 
    WHERE table_name = 'eventlog' AND column_name = 'location'
";

$existsColumn=$db->fetchAll($query);
if (!$existsColumn[0]["column_name"]) {
    $db->execQuery('ALTER TABLE "eventlog" ADD COLUMN "location" text');
    echo '<script>alert("A patch (0.1.3) has been applied to Database")</script>';
}

// Add party info to event log
$query = "
    SELECT column_name 
    FROM information_schema.columns 
    WHERE table_name = 'eventlog' AND column_name = 'party'
";

$existsColumn=$db->fetchAll($query);
if (!$existsColumn[0]["column_name"]) {
    $db->execQuery('ALTER TABLE "eventlog" ADD COLUMN "party" text');
    echo '<script>alert("A patch (0.1.4p1) has been applied to Database")</script>';
}

// Add tags to memory summary
$query = "
    SELECT column_name 
    FROM information_schema.columns 
    WHERE table_name = 'memory_summary' AND column_name = 'tags'
";

$existsColumn=$db->fetchAll($query);
if (!$existsColumn[0]["column_name"]) {
    $db->execQuery('ALTER TABLE "memory_summary" ADD COLUMN "tags" text');
    echo '<script>alert("A patch (0.1.4p2) has been applied to Database")</script>';
}

// Ensure native_vec is created
$query = "
    SELECT column_name 
    FROM information_schema.columns 
    WHERE table_name = 'memory_summary' AND column_name = 'native_vec'
";

$existsColumn=$db->fetchAll($query);
if (!$existsColumn[0]["column_name"]) {
    $db->execQuery('ALTER TABLE "memory_summary" ADD COLUMN "native_vec" TSVECTOR');
    $db->execQuery('CREATE INDEX memory_summary_tsv_idx ON articles USING GIN(native_vec);');
    echo '<script>alert("A patch (0.1.4p3) has been applied to Database")</script>';
}

$query = "
    SELECT column_name 
    FROM information_schema.columns 
    WHERE table_name = 'audit_memory' AND column_name = 'keywords'
";

$existsColumn=$db->fetchAll($query);
if (!$existsColumn[0]["column_name"]) {
    $db->execQuery('
    CREATE TABLE public.audit_memory (
    input text,
    keywords text,
    rank_any numeric(20,10),
    rank_all numeric(20,10),
    memory text,
    "time" text,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
)');
    echo '<script>alert("A patch (0.1.5p1) has been applied to Database")</script>';
}

// Memory ts
$query = "
    SELECT column_name 
    FROM information_schema.columns 
    WHERE table_name = 'memory' AND column_name = 'ts'
";


$existsColumn=$db->fetchAll($query);
if (!$existsColumn[0]["column_name"]) {
        $db->execQuery('ALTER TABLE "memory" ADD COLUMN "ts" bigint');
        $db->execQuery("CREATE OR REPLACE VIEW public.memory_v AS
 SELECT message,
    uid,
    gamets,
    speaker,
    listener,
    ts
   FROM ( SELECT memory.message,
            CAST(memory.uid AS integer),
            memory.gamets,
            '-'::text AS speaker,
            '-'::text AS listener,
           ts
           FROM public.memory
          WHERE ((memory.message !~~ 'Dear Diary%'::text) AND (memory.message <> ''::text))
        UNION
         SELECT ((((('(Context Location:'::text || speech.location) || ') '::text) || speech.speaker) || ': '::text) || speech.speech),
            CAST(speech.rowid AS integer),
            speech.gamets,
            speech.speaker,
            speech.listener,
            speech.ts
           FROM public.speech
          WHERE (speech.speech <> ''::text)
        UNION
         SELECT eventlog.data,
            CAST(eventlog.rowid AS integer),
            eventlog.gamets,
            '-'::text AS text,
            '-'::text AS listener,
            eventlog.ts
           FROM public.eventlog
          WHERE ((eventlog.type)::text = ANY (ARRAY[('death'::character varying)::text, ('location'::character varying)::text]))) subquery
  ORDER BY gamets, ts;
");

        echo '<script>alert("A patch (0.1.6p1) has been applied to Database")</script>';
    
}

// Npc profile backup

$query = "
    SELECT column_name 
    FROM information_schema.columns 
    WHERE table_name = 'npc_profile_backup'
";

$existsColumn=$db->fetchAll($query);
if (!$existsColumn[0]["column_name"]) {
        $db->execQuery("CREATE TABLE public.npc_profile_backup (
    \"name\" text,
    \"data\" text,
    \"created_at\" timestamp without time zone DEFAULT CURRENT_TIMESTAMP
    )
    ");
    echo '<script>alert("A patch (0.1.7p1) has been applied to Database")</script>';

}



$query = "
    SELECT column_name 
    FROM information_schema.columns 
    WHERE table_name = 'npc_profile_backup'
";

$existsColumn=$db->fetchAll($query);
if (!$existsColumn[0]["column_name"]) {
        $db->execQuery("CREATE TABLE public.npc_profile_backup (
    \"name\" text,
    \"data\" text,
    \"created_at\" timestamp without time zone DEFAULT CURRENT_TIMESTAMP
    )
    ");
    echo '<script>alert("A patch (0.1.7p1) has been applied to Database")</script>';

}

$query = "select npc_name from npc_templates where npc_name='neiva_deep_water'";
$existsColumn=$db->fetchAll($query);
if (!$existsColumn[0]["npc_name"]) {
    $db->execQuery(file_get_contents(__DIR__."/../data/npc_neiva_update.sql"));
    echo '<script>alert("A patch (neiva follower) has been applied to Database")</script>';
}


$query = "
    SELECT column_name 
    FROM information_schema.columns 
    WHERE table_name = 'audit_request' AND column_name = 'request'
";

$existsColumn=$db->fetchAll($query);
if (!$existsColumn[0]["column_name"]) {
    $db->execQuery('
    CREATE TABLE public.audit_request (
        request text,
        result text,
        created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
        rowid bigint NOT NULL
    );
    CREATE SEQUENCE public.audit_request_rowid_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;
    ALTER TABLE ONLY public.audit_request ALTER COLUMN rowid SET DEFAULT nextval(\'public.audit_request_rowid_seq\'::regclass);
    ALTER TABLE ONLY public.audit_request ADD CONSTRAINT audit_request_primary PRIMARY KEY (rowid);

');
    echo '<script>alert("A patch (0.9.7) has been applied to Database")</script>';
}


$query = "
    SELECT column_name 
    FROM information_schema.columns 
    WHERE table_name = 'oghma' AND column_name = 'topic'
";

$existsColumn=$db->fetchAll($query);
if (!$existsColumn[0]["column_name"]) {
    $db->execQuery(file_get_contents(__DIR__."/../data/oghma_infinium.sql"));
    echo '<script>alert("A patch (oghma_infinium) has been applied to Database")</script>';
}

$query = "
    SELECT column_name 
    FROM information_schema.columns 
    WHERE table_name = 'oghma' AND column_name = 'native_vector'
";

$existsColumn=$db->fetchAll($query);
if (!$existsColumn[0]["column_name"]) {
    $db->execQuery(file_get_contents(__DIR__."/../data/oghma_infinium2.sql"));
    echo '<script>alert("A patch (oghma_infinium 2) has been applied to Database")</script>';
}

$query = "SELECT 1 as column_name FROM oghma where topic='magnus'";
$existsColumn=$db->fetchAll($query);

// magnus
$existsColumn=$db->fetchAll($query);
if (!$existsColumn[0]["column_name"]) {
    $db->execQuery(file_get_contents(__DIR__."/../data/oghma_infinium3.sql"));
    echo '<script>alert("A patch (oghma_infinium 3) has been applied to Database")</script>';
}



$db->execQuery("update public.oghma SET native_vector = setweight(to_tsvector(coalesce(topic, '')),'A')||setweight(to_tsvector(coalesce(topic_desc, '')),'B')");


$query = "SELECT 1 as bad_syntax_exists  FROM public.npc_templates WHERE  npc_name LIKE '%' || CHR(39) || '%'";

$existsColumn=$db->fetchAll($query);
if (sizeof($existsColumn) > 0 && $existsColumn[0]["bad_syntax_exists"]) {
    $data = $db->fetchAll("SELECT npc_name FROM public.npc_templates WHERE npc_name LIKE '%' || CHR(39) || '%'");
    $n=0;    
    require_once(__DIR__."/../lib/utils.php");
    foreach ($data as $n=>$element) {
        $currentName=$element["npc_name"];
        $codename=npcNameToCodename($currentName);
        
        $cn=$db->escape($codename);
        $on=$db->escape($currentName);
        $db->execQuery("update public.npc_templates set npc_name='$cn' where npc_name='$on' and not exists (select 1 from public.npc_templates where npc_name='$cn')");
        $n++;

    }
    Logger::info("Silent npc_name patch applied ($n npcs patched). If you see this message too many times, some NPCs are probably duped in your database");
}

$query = "SELECT 1 as bad_syntax_exists  FROM npc_templates_custom WHERE  npc_name LIKE '%' || CHR(39) || '%'";

$existsColumn=$db->fetchAll($query);
if (sizeof($existsColumn) > 0 && $existsColumn[0]["bad_syntax_exists"]) {
    $data = $db->fetchAll("SELECT npc_name FROM npc_templates_custom WHERE npc_name LIKE '%' || CHR(39) || '%'");
        
    foreach ($data as $n=>$element) {
        $currentName=$element["npc_name"];
        $codename=strtr(strtolower(trim($currentName)),[" "=>"_","'"=>"+"]);
        $cn=$db->escape($codename);
        $on=$db->escape($currentName);
        $db->execQuery("update npc_templates_custom set npc_name='$cn' where npc_name='$on'");

    }
    Logger::info("Silent npc_templates_custom patch applied");
}



$query = "select npc_name from npc_templates where npc_name='kishar'";
$existsColumn=$db->fetchAll($query);
if (!$existsColumn[0]["npc_name"]) {
    $db->execQuery(file_get_contents(__DIR__."/../data/npc_kishar_update.sql"));
    echo '<script>alert("A patch (Kishar follower) has been applied to Database")</script>';
}



$query = "
    SELECT column_name 
    FROM information_schema.columns 
    WHERE table_name = 'oghma' AND column_name = 'native_vector'
";

$existsColumn=$db->fetchAll($query);
if (!$existsColumn[0]["column_name"]) {
    $db->execQuery(file_get_contents(__DIR__."/../data/oghma_infinium2.sql"));
    echo '<script>alert("A patch (oghma_infinium 2) has been applied to Database")</script>';
}

$query = "
    SELECT column_name 
    FROM information_schema.columns 
    WHERE table_name = 'npc_templates' AND column_name = 'xvasynth_voiceid'
";

$existsColumn=$db->fetchAll($query);
if (!$existsColumn[0]["column_name"]) {
    $db->execQuery(file_get_contents(__DIR__."/../data/add_voiceid_to_templates.sql"));
    $db->execQuery('ALTER TABLE "npc_templates" ADD COLUMN "melotts_voiceid" text');
    $db->execQuery('ALTER TABLE "npc_templates" ADD COLUMN "xtts_voiceid" text');
    $db->execQuery('ALTER TABLE "npc_templates" ADD COLUMN "xvasynth_voiceid" text');
    $db->execQuery('ALTER TABLE "npc_templates_custom" ADD COLUMN "melotts_voiceid" text');
    $db->execQuery('ALTER TABLE "npc_templates_custom" ADD COLUMN "xtts_voiceid" text');
    $db->execQuery('ALTER TABLE "npc_templates_custom" ADD COLUMN "xvasynth_voiceid" text');

    $db->execQuery('insert into npc_templates select * from npc_templates_v2 where npc_name not in (select npc_name from npc_templates)');

    $db->execQuery('UPDATE "npc_templates" A SET "melotts_voiceid"=(select melotts_voiceid from  npc_templates_v2 where npc_name=A.npc_name)');
    $db->execQuery('UPDATE "npc_templates" A SET "xtts_voiceid"=(select xtts_voiceid from  npc_templates_v2 where npc_name=A.npc_name)');
    $db->execQuery('UPDATE "npc_templates" A SET "xvasynth_voiceid"=(select xvasynth_voiceid from  npc_templates_v2 where npc_name=A.npc_name)');

    $db->execQuery('UPDATE "npc_templates_custom" A SET "melotts_voiceid"=(select melotts_voiceid from  npc_templates_v2 where npc_name=A.npc_name)');
    $db->execQuery('UPDATE "npc_templates_custom" A SET "xtts_voiceid"=(select xtts_voiceid from  npc_templates_v2 where npc_name=A.npc_name)');
    $db->execQuery('UPDATE "npc_templates_custom" A SET "xvasynth_voiceid"=(select xvasynth_voiceid from  npc_templates_v2 where npc_name=A.npc_name)');

    $db->execQuery(file_get_contents(__DIR__."/../data/add_voiceid_to_templates_2stage.sql"));

    echo '<script>alert("A patch (expanded npc table) has been applied to Database")</script>';
}

// <<<<<<< personalities-plugin
$path = dirname((__FILE__)) . DIRECTORY_SEPARATOR;
require_once("$path/add_json_personalities.php");


$query = "
    SELECT column_name 
    FROM information_schema.columns 
    WHERE table_name = 'npc_templates_trl' AND column_name = 'npc_misc'
";

$existsColumn=$db->fetchAll($query);
if (!$existsColumn[0]["column_name"]) {
    $db->execQuery(file_get_contents(__DIR__."/../data/npc_templates_trl_v1.sql"));
    echo '<script>alert("A patch (npc_templates_trl) has been applied to Database")</script>';
}

//database_versioning table
$query = "
    SELECT column_name 
    FROM information_schema.columns 
    WHERE table_name = 'database_versioning' AND column_name = 'version'
";

$existsColumn=$db->fetchAll($query);
if (!$existsColumn[0]["column_name"]) {
    $db->execQuery(file_get_contents(__DIR__."/../data/database_versioning.sql"));
    echo '<script>alert("A patch (database versioning) has been applied to Database")</script>';
}


$query = "
    SELECT version 
    FROM database_versioning
    WHERE tablename = 'npc_templates_trl'
";

$existsColumn=$db->fetchAll($query);

if (!$existsColumn[0]["version"] || $existsColumn[0]["version"]<20250117001) {
    $db->execQuery(file_get_contents(__DIR__."/../data/npc_templates_trl_es_v1.sql"));
    echo '<script>alert("A patch (npc_templates_trl [es]) has been applied to Database")</script>';
}

if (!$existsColumn[0]["version"] || $existsColumn[0]["version"]<20250120001) {
    $db->execQuery(file_get_contents(__DIR__."/../data/npc_templates_trl_es_v2.sql"));
    echo '<script>alert("A patch (npc_templates_trl [es]) has been applied to Database")</script>';
}

// Oghma npc table 20250129


if ($checkVersion("npc_templates")<20250129001) {
    $query = "
    SET schema 'public';
    CREATE TABLE IF NOT EXISTS npc_templates (
        npc_name character varying(128) NOT NULL,
        npc_pers text NOT NULL,
        npc_misc text
    );
    ALTER TABLE npc_templates 
    ADD COLUMN IF NOT EXISTS npc_dynamic TEXT;
    ALTER TABLE npc_templates 
    ADD COLUMN IF NOT EXISTS melotts_voiceid TEXT;
    ALTER TABLE npc_templates 
    ADD COLUMN IF NOT EXISTS xtts_voiceid TEXT;
    ALTER TABLE npc_templates 
    ADD COLUMN IF NOT EXISTS xvasynth_voiceid TEXT;
    ALTER TABLE npc_templates 
    ADD COLUMN IF NOT EXISTS npc_background TEXT;
    ALTER TABLE npc_templates 
    ADD COLUMN IF NOT EXISTS npc_personality TEXT;
    ALTER TABLE npc_templates 
    ADD COLUMN IF NOT EXISTS npc_appearance TEXT;
    ALTER TABLE npc_templates 
    ADD COLUMN IF NOT EXISTS npc_relationships TEXT;
    ALTER TABLE npc_templates 
    ADD COLUMN IF NOT EXISTS npc_occupation TEXT;
    ALTER TABLE npc_templates 
    ADD COLUMN IF NOT EXISTS npc_skills TEXT;
    ALTER TABLE npc_templates 
    ADD COLUMN IF NOT EXISTS npc_speechstyle TEXT;
    ALTER TABLE npc_templates 
    ADD COLUMN IF NOT EXISTS npc_goals TEXT;
    ";
    $db->execQuery($query);
    $updateVersion("npc_templates",20250129001);
}

if ($checkVersion("npc_templates_custom")<20250129001) {
    $query = "
    SET schema 'public';
    CREATE TABLE IF NOT EXISTS npc_templates_custom (
        npc_name character varying(128) NOT NULL,
        npc_pers text NOT NULL,
        npc_misc text
    );
    ALTER TABLE npc_templates_custom 
    ADD COLUMN IF NOT EXISTS npc_dynamic TEXT;
    ALTER TABLE npc_templates_custom 
    ADD COLUMN IF NOT EXISTS melotts_voiceid TEXT;
    ALTER TABLE npc_templates_custom 
    ADD COLUMN IF NOT EXISTS xtts_voiceid TEXT;
    ALTER TABLE npc_templates_custom 
    ADD COLUMN IF NOT EXISTS xvasynth_voiceid TEXT;
    ALTER TABLE npc_templates_custom 
    ADD COLUMN IF NOT EXISTS npc_background TEXT;
    ALTER TABLE npc_templates_custom 
    ADD COLUMN IF NOT EXISTS npc_personality TEXT;
    ALTER TABLE npc_templates_custom 
    ADD COLUMN IF NOT EXISTS npc_appearance TEXT;
    ALTER TABLE npc_templates_custom 
    ADD COLUMN IF NOT EXISTS npc_relationships TEXT;
    ALTER TABLE npc_templates_custom 
    ADD COLUMN IF NOT EXISTS npc_occupation TEXT;
    ALTER TABLE npc_templates_custom 
    ADD COLUMN IF NOT EXISTS npc_skills TEXT;
    ALTER TABLE npc_templates_custom 
    ADD COLUMN IF NOT EXISTS npc_speechstyle TEXT;
    ALTER TABLE npc_templates_custom 
    ADD COLUMN IF NOT EXISTS npc_goals TEXT;
    ";
    $db->execQuery($query);
    $updateVersion("npc_templates_custom",20250129001);
}

if ($checkVersion("combined_npc_templates")<20250129001) {
    $query="
    DROP VIEW public.combined_npc_templates;
    CREATE VIEW public.combined_npc_templates AS
     SELECT c.npc_name,
        c.npc_pers,
        c.npc_dynamic,
        c.npc_misc,
        c.melotts_voiceid,
        c.xtts_voiceid,
        c.xvasynth_voiceid
       FROM public.npc_templates_custom c
    UNION ALL
     SELECT t.npc_name,
        t.npc_pers,
        t.npc_dynamic,
        t.npc_misc,
        t.melotts_voiceid,
        t.xtts_voiceid,
        t.xvasynth_voiceid
       FROM (public.npc_templates t
         LEFT JOIN public.npc_templates_custom c ON (((t.npc_name)::text = (c.npc_name)::text)))
      WHERE (c.npc_name IS NULL);";
    
    $db->execQuery($query);
    $updateVersion("combined_npc_templates",20250129001);
}

if ($checkVersion("oghma")<20250902001) {
    $query = "
    SET schema 'public';
    CREATE TABLE IF NOT EXISTS oghma (
        topic character varying NOT NULL,
        topic_desc character varying NOT NULL,
        native_vector tsvector
    );
    ALTER TABLE oghma ADD COLUMN IF NOT EXISTS knowledge_class TEXT;
    ALTER TABLE oghma ADD COLUMN IF NOT EXISTS topic_desc_basic TEXT;
    ALTER TABLE oghma ADD COLUMN IF NOT EXISTS knowledge_class_basic TEXT;
    ALTER TABLE oghma ADD COLUMN IF NOT EXISTS tags TEXT;
    ALTER TABLE oghma ADD COLUMN IF NOT EXISTS category TEXT;
   
    ";
    $db->execQuery($query);
    $updateVersion("oghma",20250902001);
}


// Pfff

if ($checkVersion("npc_templates_custom")<20250211001) {
    $query="DROP VIEW public.combined_npc_templates;";
   
    $db->execQuery($query);

    $query = "
    ALTER TABLE npc_templates_custom 
    ADD COLUMN IF NOT EXISTS npc_dynamic TEXT;
    ALTER TABLE npc_templates_custom 
    ADD COLUMN IF NOT EXISTS melotts_voiceid TEXT;
    ALTER TABLE npc_templates_custom 
    ADD COLUMN IF NOT EXISTS xtts_voiceid TEXT;
    ALTER TABLE npc_templates_custom 
    ADD COLUMN IF NOT EXISTS xvasynth_voiceid TEXT;
    ";
    $db->execQuery($query);

    $query="
    CREATE VIEW public.combined_npc_templates AS
     SELECT c.npc_name,
        c.npc_pers,
        c.npc_dynamic,
        c.npc_misc,
        c.melotts_voiceid,
        c.xtts_voiceid,
        c.xvasynth_voiceid
       FROM public.npc_templates_custom c
    UNION ALL
     SELECT t.npc_name,
        t.npc_pers,
        t.npc_dynamic,
        t.npc_misc,
        t.melotts_voiceid,
        t.xtts_voiceid,
        t.xvasynth_voiceid
       FROM (public.npc_templates t
         LEFT JOIN public.npc_templates_custom c ON (((t.npc_name)::text = (c.npc_name)::text)))
      WHERE (c.npc_name IS NULL);";
    
    $db->execQuery($query);

    $updateVersion("npc_templates_custom",20250211001);
    $updateVersion("combined_npc_templates",20250211001);
    Logger::info("Applied patch 20250211001");
}

//----------------------------------------------------
// SQL convert gamets timestamp to date time formatted
//  sql_gamets_convert_functions 20250218001
//----------------------------------------------------

if ($checkVersion("sql_gamets_convert_functions")<20250218001) {
    Logger::debug(" try patch: sql_gamets_convert_functions 20250218001");

    $db->execQuery("DROP VIEW IF EXISTS public.speech_view;");
    $db->execQuery("DROP VIEW IF EXISTS public.eventlog_view;");

    $db->execQuery("DROP FUNCTION IF EXISTS public.convert_gamets2days(gamets bigint) CASCADE;");
    $db->execQuery("DROP FUNCTION IF EXISTS public.convert_gamets2gregorian_date(gamets bigint) CASCADE;");
    $db->execQuery("DROP FUNCTION IF EXISTS public.convert_gamets2skyrim_long_date(gamets bigint) CASCADE;");
    $db->execQuery("DROP FUNCTION IF EXISTS public.convert_gamets2skyrim_long_date2(gamets bigint) CASCADE;");
    $db->execQuery("DROP FUNCTION IF EXISTS public.convert_gamets2skyrim_date(gamets bigint) CASCADE;");
    $db->execQuery("DROP FUNCTION IF EXISTS public.convert_gamets2hours(gamets bigint) CASCADE;");

    $db->execQuery("
        CREATE OR REPLACE FUNCTION public.convert_gamets2days(gamets bigint) RETURNS bigint
            LANGUAGE plpgsql
            AS $$
            BEGIN
                RETURN floor(gamets * 0.0000001);
            END;
        $$;  ");

    $db->execQuery("
        CREATE OR REPLACE FUNCTION public.convert_gamets2gregorian_date(gamets bigint) RETURNS text
            LANGUAGE plpgsql
            AS $$
            BEGIN
                RETURN to_char(to_timestamp('1577.08.17 00:00:00','YYYY.MM.DD HH24:MI:SS') + (gamets * 0.0000024) * INTERVAL '1 hour', 'YYYY-MM-DD HH24:MI:SS');
            END;
        $$;  ");

    $db->execQuery("
        CREATE OR REPLACE FUNCTION public.convert_gamets2hours(gamets bigint) RETURNS bigint
            LANGUAGE plpgsql
            AS $$
            BEGIN
                RETURN floor(gamets * 0.0000024);
            END;
        $$; ");

    $db->execQuery("
        CREATE OR REPLACE FUNCTION public.convert_gamets2skyrim_date(gamets bigint) RETURNS text
            LANGUAGE plpgsql
            AS $$
            BEGIN
                RETURN to_char(to_timestamp('0201.08.17 00:00:00','YYYY.MM.DD HH24:MI:SS') + (gamets * 0.0000024) * INTERVAL '1 hour', 'YYYY-MM-DD HH24:MI:SS');
            END;
        $$; ");

    $db->execQuery("
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
                ts2 timestamp;
                s_res text;
            BEGIN
                f_hours := (gamets * 0.0000024);
                ts_base := to_timestamp('0201.08.17 00:00:00','YYYY.MM.DD HH24:MI:SS');
                ts2 := ts_base  + f_hours * INTERVAL '1 hour';
                s_month := to_char(ts2, 'MM');
                s_dayweek := to_char(ts2, 'D'); -- D	day of the week, 
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
                s_date1 := to_char(ts2, 'HH12:MI AM');
                s_date2 := to_char(ts2, 'FMDD');
                s_date3 := to_char(ts2, ', 4E FMYYYY');
                s_res := s_dayname || ', ' || s_date1 || ', ' || s_date2 ||  'th of ' || s_longm || s_date3;
                RETURN s_res;
            END;
        $$; ");

    $db->execQuery("
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
                ts2 timestamp;
                s_res text;
            BEGIN
                f_hours := (gamets * 0.0000024);
                ts_base := to_timestamp('0201.08.17 00:00:00','YYYY.MM.DD HH24:MI:SS');
                ts2 := ts_base  + f_hours * INTERVAL '1 hour';
                s_month := to_char(ts2, 'MM');
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
                s_date1 := to_char(ts2, 'DD');
                s_date2 := to_char(ts2, ' 4E FMYYYY, HH24:MI');
                s_res := s_date1 || 'th of ' || s_longm || s_date2;
                RETURN s_res;
            END;
        $$; ");

    $db->execQuery("
        CREATE OR REPLACE VIEW public.eventlog_view AS
          SELECT e.*,
            public.convert_gamets2skyrim_date(e.gamets) AS sk_date,
            public.convert_gamets2skyrim_long_date(e.gamets) AS sk_long_date,
            public.convert_gamets2days(e.gamets) AS sk_days,
            public.convert_gamets2gregorian_date(e.gamets) AS gregorian_date
          FROM public.eventlog e; ");

    $db->execQuery("
        CREATE OR REPLACE VIEW public.speech_view AS
          SELECT s.*,
            public.convert_gamets2skyrim_date(s.gamets) AS sk_date,
            public.convert_gamets2skyrim_long_date(s.gamets) AS sk_long_date,
            public.convert_gamets2days(s.gamets) AS sk_days,
            public.convert_gamets2gregorian_date(s.gamets) AS gregorian_date
          FROM public.speech s; ");
    
    $updateVersion("sql_gamets_convert_functions",20250218001);
    $updateVersion("sql_gamets_convert_functions",20250218001);
    Logger::debug("Applied patch: sql_gamets_convert_functions 20250218001");
}

if ($checkVersion("sql_gamets_convert_functions")<20250226001) {
    Logger::debug(" try patch: sql_gamets_convert_functions 2 20250226001");

    $db->execQuery("DROP FUNCTION IF EXISTS public.convert_gamets2skyrim_date_fmt(gamets bigint, s_format text) CASCADE;");
    $db->execQuery("DROP FUNCTION IF EXISTS public.convert_gamets2skyrim_long_date2_nt(gamets bigint) CASCADE;");
    $db->execQuery("DROP FUNCTION IF EXISTS public.convert_gamets2skyrim_long_date_nt(gamets bigint) CASCADE;");
    $db->execQuery("DROP FUNCTION IF EXISTS public.convert_gamets2skyrim_time_daypart(gamets bigint) CASCADE;");

    $db->execQuery("
    CREATE OR REPLACE FUNCTION public.convert_gamets2skyrim_date_fmt(gamets bigint, s_format text) RETURNS text
        LANGUAGE plpgsql
        AS $$
        DECLARE 
            s_date text; 
            s_format text; 
            f_hours float;
            ts_base timestamp;
            ts2 timestamp;
        BEGIN
            IF (s_format IS NULL) OR (LENGTH(s_format) < 1) THEN
                s_format := 'YYYY.MM.DD HH24:MI'; 
            END IF;
            f_hours := (gamets * 0.0000024);
            ts_base := to_timestamp('0201.08.17 00:00:00','YYYY.MM.DD HH24:MI:SS');
            ts2 := ts_base  + f_hours * INTERVAL '1 hour';
            RETURN to_char(ts2, s_format);
        END;
    $$;  ");

    $db->execQuery("
    CREATE OR REPLACE FUNCTION public.convert_gamets2skyrim_long_date_nt(gamets bigint) RETURNS text
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
            ts2 timestamp;
            s_res text;
        BEGIN
            f_hours := (gamets * 0.0000024);
            ts_base := to_timestamp('0201.08.17 00:00:00','YYYY.MM.DD HH24:MI:SS');
            ts2 := ts_base  + f_hours * INTERVAL '1 hour';
            s_month := to_char(ts2, 'MM');
            s_dayweek := to_char(ts2, 'D'); -- D	day of the week, 
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
            s_date2 := to_char(ts2, 'FMDD');
            s_date3 := to_char(ts2, ', 4E FMYYYY');
            s_res := s_dayname || ', ' || s_date2 ||  'th of ' || s_longm || s_date3;
            RETURN s_res;
        END;
    $$;  ");

    $db->execQuery("
    CREATE OR REPLACE FUNCTION public.convert_gamets2skyrim_long_date2_nt(gamets bigint) RETURNS text
        LANGUAGE plpgsql
        AS $$
        DECLARE 
            s_date1 text; 
            s_date2 text; 
            s_month text;
            s_longm text;
            f_hours float;
            ts_base timestamp;
            ts2 timestamp;
            s_res text;
        BEGIN
            f_hours := (gamets * 0.0000024);
            ts_base := to_timestamp('0201.08.17 00:00:00','YYYY.MM.DD HH24:MI:SS');
            ts2 := ts_base  + f_hours * INTERVAL '1 hour';
            s_month := to_char(ts2, 'MM');
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
            s_date1 := to_char(ts2, 'DD');
            s_date2 := to_char(ts2, ' 4E FMYYYY');
            s_res := s_date1 || 'th of ' || s_longm || s_date2;
            RETURN s_res;
        END;
    $$;  ");

    $db->execQuery("
    CREATE OR REPLACE FUNCTION public.convert_gamets2skyrim_time_daypart(gamets bigint) RETURNS text
        LANGUAGE plpgsql
        AS $$
        DECLARE 
            s_date1 text; 
            s_hour text;
            s_daypart text;
            f_hours float;
            ts_base timestamp;
            ts2 timestamp;
        BEGIN
            f_hours := (gamets * 0.0000024);
            ts_base := to_timestamp('0201.08.17 00:00:00','YYYY.MM.DD HH24:MI:SS');
            ts2 := ts_base  + f_hours * INTERVAL '1 hour';
            s_hour := to_char(ts2, 'HH24');
            CASE s_hour
                WHEN '00' THEN s_daypart := 'midnight';
                WHEN '01' THEN s_daypart := 'after midnight';
                WHEN '02' THEN s_daypart := 'night';
                WHEN '03' THEN s_daypart := 'night';
                WHEN '04' THEN s_daypart := 'night';
                WHEN '05' THEN s_daypart := 'early morning';
                WHEN '06' THEN s_daypart := 'early morning';
                WHEN '07' THEN s_daypart := 'early morning';
                WHEN '08' THEN s_daypart := 'morning';
                WHEN '09' THEN s_daypart := 'morning';
                WHEN '10' THEN s_daypart := 'morning';
                WHEN '11' THEN s_daypart := 'late morning';
                WHEN '12' THEN s_daypart := 'noon';
                WHEN '13' THEN s_daypart := 'early afternoon';
                WHEN '14' THEN s_daypart := 'early afternoon';
                WHEN '15' THEN s_daypart := 'afternoon';
                WHEN '16' THEN s_daypart := 'afternoon';
                WHEN '17' THEN s_daypart := 'late afternoon';
                WHEN '18' THEN s_daypart := 'early evening';
                WHEN '19' THEN s_daypart := 'evening';
                WHEN '20' THEN s_daypart := 'evening';
                WHEN '21' THEN s_daypart := 'evening';
                WHEN '22' THEN s_daypart := 'night';
                WHEN '23' THEN s_daypart := 'night';
                WHEN '24' THEN s_daypart := 'midnight';
                ELSE s_daypart := 'unknown';
            END CASE;
            s_date1 := to_char(ts2, 'HH24:MI');
            RETURN s_date1 || ', ' || s_daypart;
        END;
    $$;  ");

    $updateVersion("sql_gamets_convert_functions",20250226001);
    $updateVersion("sql_gamets_convert_functions",20250226001);
    Logger::debug("Applied patch: sql_gamets_convert_functions 2 20250226001");
}


//----------------------------------------------------
// npc_template and oghma table. 1.1.0 update
// 
//----------------------------------------------------
                                          
if ($checkVersion("npc_templates")<20250302001) {
    $query="TRUNCATE TABLE public.npc_templates";
    $db->execQuery($query);
    $db->execQuery(file_get_contents(__DIR__."/../data/npc_templates_20250302001.sql"));
    $updateVersion("npc_templates",20250302001);
    Logger::info("Applied patch npc_templates 20250302001");
}

if ($checkVersion("oghma")<20250902002) {

    $query="TRUNCATE TABLE public.oghma";
    $db->execQuery($query);
    $db->execQuery(file_get_contents(__DIR__."/../data/oghma_20250302001.sql"));
    
    $updateVersion("oghma",20250902002);
    Logger::info("Applied patch oghma 20250902002");
}

if ($checkVersion("questlog")<20250310001) {

    $db->execQuery(file_get_contents(__DIR__."/../data/questlog.sql"));


    $updateVersion("questlog",20250310001);
    Logger::info("Applied patch questlog 20250310001");
}

// fix for memory_summary missing companions
if ($checkVersion("memory_summary")<20250331001) {
    $db->execQuery("UPDATE memory_summary set companions = NULL WHERE companions = '';");
    $updateVersion("memory_summary",20250331001);
    Logger::info("Applied patch memory_summary 20250331001");
}

if ($checkVersion("oghma_dynamic")<20250310001) {
    $db->execQuery(file_get_contents(__DIR__."/../data/oghma_dynamic.sql"));
    $updateVersion("oghma_dynamic",20250310001);
    error_log("Applied patch oghma_dynamic 20250310001");
}

if ($checkVersion("rolemaster")<20250414001) {
    $db->execQuery(file_get_contents(__DIR__."/../data/rolemaster.sql"));
    $updateVersion("rolemaster",20250414001);
    error_log("Applied patch rolemaster 20250414001");
}

if ($checkVersion("locations")<20250516001) {
    $db->execQuery(file_get_contents(__DIR__."/../data/add_locations.sql"));
    $updateVersion("locations",20250516001);
    error_log("Applied patch locations 20250516001");
}

if ($checkVersion("actions_issued")<20250525001) {
    $db->execQuery(file_get_contents(__DIR__."/../data/actions_issued.sql"));
    $updateVersion("actions_issued",20250525001);
    error_log("Applied patch actions_issued 20250525001");
}


if ($checkVersion("moods_issued")<20250526001) {
    $db->execQuery(file_get_contents(__DIR__."/../data/table_moods_issued.sql"));
    $updateVersion("moods_issued",20250526001);
    error_log("Applied patch moods_issued 20250526001");
}

//----------------------------------------------------

if ($checkVersion("dynamic_bio")<20250710001) {
    $db->execQuery("
        CREATE TABLE IF NOT EXISTS public.dynamic_bio (
            id SERIAL PRIMARY KEY,
            prompt TEXT NOT NULL
        )
    ");

    // Always populate prompts - use INSERT ... WHERE NOT EXISTS to avoid duplicates
    Logger::info("Ensuring dynamic_bio prompts are populated...");
        $prompts = [
            "Has a habit of speaking in riddles or vague phrases, rarely giving a straightforward answer, leaving listeners puzzled or intrigued.",
            "Constantly assesses the value of objects or situations, muttering things like 'worth a handful of septims' or 'barely worth a second glance.'",
            "Often boasts of past deeds, embellishing stories to seem larger-than-life, whether its about defeating bandits or outrunning a pack of wolves.",
           "Uses overly formal or flowery language, regardless of the situation, giving the impression theyre more important than they might actually be.",
            "Startles easily, overreacting to minor surprises and making dramatic exclamations even when the situation is harmless.",
            "Frequently mentions their love of drink, often wishing they could be drinking rather then whatever they are doing right now.",
            "Keeps their sentences brief and to the point, constantly scanning their surroundings as though expecting trouble to appear at any moment.",
            "Peppers conversations with religious sayings or blessings, pausing at random to mutter prayers or invoke divine guidance.",
            "Laughs loudly and at odd moments, their mirth sometimes inappropriate and unsettling to those around them.",
            "Enjoys recounting old tales or bits of local history, often veering off into tangents about events or people from the past.",
            "Refuses to back down in any argument, no matter how small or insignificant, stubbornly clinging to their point of view.",
            "Insists on challenging anyone who questions their honor, even if the slight is minor or unintended, seeing every disagreement as a personal affront.",
            "Occasionally mutters strange combinations of words or phrases, as though rehearsing something, leaving an air of mystery about their knowledge.",
            "Cracks jokes and tries to lighten the mood, though their attempts at humor sometimes feel misplaced or poorly timed.",
            "Constantly uses idioms and proverbs, even in situations where they make little sense, leaving others scratching their heads.",
            "Has a habit of over-apologizing for even the smallest inconveniences, often stumbling over their words to avoid conflict.",
            "Repeats the words of others, they do this without realizing it.",
            "Avoids directly answering questions, instead responding with questions of their own or deflecting with vague statements.",
            "Greets everyone they meet with an overly cheerful tone, regardless of the situation, sometimes to the annoyance of others.",
            "Has a habit of over-explaining simple concepts, as though assuming others cant understand without detailed clarification.",
            "Hesitates before speaking, often starting sentences over or leaving thoughts unfinished, as if unsure of what to say.",
            "Insists on using nicknames for everyone they meet, even if the person prefers to be addressed more formally.",
            "Takes every compliment as a personal challenge, feeling the need to outdo themselves or prove they deserve the praise.",
            "Frequently comments on how things 'used to be better' in the past, regardless of whether theyve actually experienced those times.",
            "Carries a peculiar fixation on fairness, pointing out perceived injustices or unfair treatment in even trivial matters.",
            "Refers to themselves in the third person, making their speech stand out as eccentric or self-important.",
            "Seems overly curious, asking far too many questions about others lives, sometimes venturing into topics considered personal or taboo.",
            "Speaks sparingly, relying on gestures or brief comments to communicate, making their few words carry extra weight.",
            "Constantly mentions the weather, relating it to omens or signs from the divines about what might happen next.",
            "Frequently clears their throat before speaking, as if always preparing to make an important announcement.",
            "Has a habit of touching their weapons or armor while speaking, a nervous tic that suggests they're always ready for trouble.",
            "Speaks with an unusual cadence, emphasizing random words in their sentences for no apparent reason.",
            "Compares everything to hunting or fishing, using metaphors like 'quick as a slaughterfish' or 'stubborn as a horker'.",
            "Interrupts themselves mid-sentence to comment on seemingly unrelated observations about their surroundings.",
            "Occasionally whispers parts of sentences, as if sharing secrets even when discussing mundane topics.",
            "Sighs dramatically before responding to questions, as though the weight of the world rests on their shoulders.",
            "Frequently mentions their aches and pains, blaming old wounds or the changing seasons for their discomfort.",
            "Has a peculiar habit of sniffing the air while conversing, sometimes commenting on scents others can't detect.",
            "Speaks with excessive politeness to those of higher status, but becomes notably curt with those they deem beneath them.",
            "Prides themselves on their knowledge of herbs and potions, offering unsolicited advice about remedies for ailments no one mentioned."
        ];
        
        foreach ($prompts as $prompt) {
            $escapedPrompt = $db->escape($prompt);
            $db->execQuery("INSERT INTO dynamic_bio (prompt) 
                SELECT '".$escapedPrompt."' 
                WHERE NOT EXISTS (
                    SELECT 1 FROM dynamic_bio WHERE prompt = '".$escapedPrompt."'
                )");
        }
    
    $updateVersion("dynamic_bio", 20250710001);
}

//----------------------------------------------------

if ($checkVersion("oghma")<20250903001) { // version 202509... 
    Logger::debug(" try patch: oghma 20250903001");
    
    // Check if vector384 column exists first
    try {
        $columnCheck = $db->fetchAll("
            SELECT column_name 
            FROM information_schema.columns 
            WHERE table_name = 'oghma' 
            AND column_name = 'vector384' 
            AND table_schema = 'public'
        ");
        
        if (empty($columnCheck)) {
            $db->execQuery("ALTER TABLE public.oghma ADD COLUMN \"vector384\" vector(384)");
            Logger::info("Added vector384 column to oghma table");
        } else {
            Logger::info("vector384 column already exists, skipping...");
        }
    } catch (Exception $e) {
        Logger::error("Error with vector384 column: " . $e->getMessage());
        // If it's the "already exists" error, we can safely continue
        if (strpos($e->getMessage(), "already exists") !== false) {
            Logger::info("Column already exists, continuing...");
        } else {
            throw $e; // Re-throw if it's a different error
        }
    }
    
    $updateVersion("oghma",20250903001);
    Logger::info("Applied patch oghma 20250903001");
}

if ($checkVersion("locations")<20250526001) {
    Logger::debug(" try patch: locations 20250526001");
    $db->execQuery("CREATE EXTENSION IF NOT EXISTS pg_trgm;");
    $updateVersion("locations",20250526001);
    Logger::info("Applied patch locations 20250526001");
}

if ($checkVersion("rolemaster")<20250528001) {
    Logger::debug(" try patch: rolemaster 20250528001");
    $db->execQuery("ALTER TABLE public.responselog ALTER COLUMN \"action\" TYPE text");
    $db->execQuery("ALTER TABLE public.responselog ALTER COLUMN \"actor\" TYPE text");
    $db->execQuery("ALTER TABLE public.responselog ALTER COLUMN \"text\" TYPE text");
    $updateVersion("rolemaster",20250528001);
    Logger::info("Applied patch rolemaster 20250528001");
}

//----------------------------------------------------
// database maintenance tools
// - autovacuum / table
//----------------------------------------------------

if ($checkVersion("db_maintenance")<20250528002) {
    Logger::debug(" try patch: db_maintenance 20250528002");

    $db->execQuery("DROP FUNCTION IF EXISTS public.sql_exec2(text) CASCADE");

    $db->execQuery("
    CREATE FUNCTION public.sql_exec2(text) returns text 
    language plpgsql volatile 
    AS 
    $$
        BEGIN
          EXECUTE $1;
          RETURN $1;
        END;
    $$; 
    ");

    $db->execQuery("SELECT sql_exec2('ALTER TABLE \"'||pgc.relname||'\" SET (autovacuum_enabled = on, toast.autovacuum_enabled = on) '||';')
        FROM pg_catalog.pg_class pgc
        LEFT JOIN pg_catalog.pg_namespace pgn ON pgn.oid = pgc.relnamespace
        WHERE (pgc.relkind ='r')
        AND (pgn.nspname='public'); ");

    $updateVersion("db_maintenance",20250528002);
    Logger::info("Applied patch db_maintenance 20250528002");
}

//----------------------------------------------------
// Add new character profile fields to npc_templates tables
// Version 20250203001
//----------------------------------------------------

if ($checkVersion("npc_templates")<20250302002) {
    Logger::debug("try patch: npc_templates extended profile fields 20250302002");
    
    $query = "
    ALTER TABLE npc_templates 
    ADD COLUMN IF NOT EXISTS npc_background TEXT;
    ALTER TABLE npc_templates 
    ADD COLUMN IF NOT EXISTS npc_personality TEXT;
    ALTER TABLE npc_templates 
    ADD COLUMN IF NOT EXISTS npc_appearance TEXT;
    ALTER TABLE npc_templates 
    ADD COLUMN IF NOT EXISTS npc_relationships TEXT;
    ALTER TABLE npc_templates 
    ADD COLUMN IF NOT EXISTS npc_occupation TEXT;
    ALTER TABLE npc_templates 
    ADD COLUMN IF NOT EXISTS npc_skills TEXT;
    ALTER TABLE npc_templates 
    ADD COLUMN IF NOT EXISTS npc_speechstyle TEXT;
    ";
    $db->execQuery($query);
    $updateVersion("npc_templates",20250302002);
    Logger::info("Applied patch npc_templates extended profile fields 20250302002");
}

if ($checkVersion("npc_templates_custom")<20250302002) {
    Logger::debug("try patch: npc_templates_custom extended profile fields 20250302002");
    
    $query = "
    ALTER TABLE npc_templates_custom 
    ADD COLUMN IF NOT EXISTS npc_background TEXT;
    ALTER TABLE npc_templates_custom 
    ADD COLUMN IF NOT EXISTS npc_personality TEXT;
    ALTER TABLE npc_templates_custom 
    ADD COLUMN IF NOT EXISTS npc_appearance TEXT;
    ALTER TABLE npc_templates_custom 
    ADD COLUMN IF NOT EXISTS npc_relationships TEXT;
    ALTER TABLE npc_templates_custom 
    ADD COLUMN IF NOT EXISTS npc_occupation TEXT;
    ALTER TABLE npc_templates_custom 
    ADD COLUMN IF NOT EXISTS npc_skills TEXT;
    ALTER TABLE npc_templates_custom 
    ADD COLUMN IF NOT EXISTS npc_speechstyle TEXT;
    ";
    $db->execQuery($query);
    $updateVersion("npc_templates_custom",20250302002);
    Logger::info("Applied patch npc_templates_custom extended profile fields 20250302002");
}

if ($checkVersion("npc_templates_trl")<20250302002) {
    Logger::debug("try patch: npc_templates_trl extended profile fields 20250302002");
    
    $query = "
    ALTER TABLE npc_templates_trl 
    ADD COLUMN IF NOT EXISTS npc_background TEXT;
    ALTER TABLE npc_templates_trl 
    ADD COLUMN IF NOT EXISTS npc_personality TEXT;
    ALTER TABLE npc_templates_trl 
    ADD COLUMN IF NOT EXISTS npc_appearance TEXT;
    ALTER TABLE npc_templates_trl 
    ADD COLUMN IF NOT EXISTS npc_relationships TEXT;
    ALTER TABLE npc_templates_trl 
    ADD COLUMN IF NOT EXISTS npc_occupation TEXT;
    ALTER TABLE npc_templates_trl 
    ADD COLUMN IF NOT EXISTS npc_skills TEXT;
    ALTER TABLE npc_templates_trl 
    ADD COLUMN IF NOT EXISTS npc_speechstyle TEXT;
    ";
    $db->execQuery($query);
    $updateVersion("npc_templates_trl",20250302002);
    Logger::info("Applied patch npc_templates_trl extended profile fields 20250302002");
}

if ($checkVersion("combined_npc_templates")<20250302002) {
    Logger::debug("try patch: combined_npc_templates view update 20250302002");
    
    $query="
    DROP VIEW IF EXISTS public.combined_npc_templates;
    CREATE VIEW public.combined_npc_templates AS
     SELECT c.npc_name,
        c.npc_pers,
        c.npc_dynamic,
        c.npc_misc,
        c.melotts_voiceid,
        c.xtts_voiceid,
        c.xvasynth_voiceid,
        c.npc_background,
        c.npc_personality,
        c.npc_appearance,
        c.npc_relationships,
        c.npc_occupation,
        c.npc_skills,
        c.npc_speechstyle
       FROM public.npc_templates_custom c
    UNION ALL
     SELECT t.npc_name,
        t.npc_pers,
        t.npc_dynamic,
        t.npc_misc,
        t.melotts_voiceid,
        t.xtts_voiceid,
        t.xvasynth_voiceid,
        t.npc_background,
        t.npc_personality,
        t.npc_appearance,
        t.npc_relationships,
        t.npc_occupation,
        t.npc_skills,
        t.npc_speechstyle
       FROM (public.npc_templates t
         LEFT JOIN public.npc_templates_custom c ON (((t.npc_name)::text = (c.npc_name)::text)))
      WHERE (c.npc_name IS NULL);";
    
    $db->execQuery($query);
    $updateVersion("combined_npc_templates",20250302002);
    Logger::info("Applied patch combined_npc_templates view update 20250302002");
}

// Extended biography fields are now handled in the base table creation (20250129001)

if ($checkVersion("npc_templates_custom")<20250302003) {
    Logger::debug("try patch: npc_templates_custom add npc_goals field 20250302003");
    
    $query = "
    ALTER TABLE npc_templates_custom 
    ADD COLUMN IF NOT EXISTS npc_goals TEXT;
    ";
    $db->execQuery($query);
    $updateVersion("npc_templates_custom",20250302003);
    Logger::info("Applied patch npc_templates_custom add npc_goals field 20250302003");
}

if ($checkVersion("npc_templates")<20250302003) {
    Logger::debug("try patch: npc_templates add npc_goals field 20250302003");
    
    $query = "
    ALTER TABLE npc_templates 
    ADD COLUMN IF NOT EXISTS npc_goals TEXT;
    ";
    $db->execQuery($query);
    $updateVersion("npc_templates",20250302003);
    Logger::info("Applied patch npc_templates add npc_goals field 20250302003");
}

if ($checkVersion("npc_templates_trl")<20250302003) {
    Logger::debug("try patch: npc_templates_trl add npc_goals field 20250302003");
    
    $query = "
    ALTER TABLE npc_templates_trl 
    ADD COLUMN IF NOT EXISTS npc_goals TEXT;
    ";
    $db->execQuery($query);
    $updateVersion("npc_templates_trl",20250302003);
    Logger::info("Applied patch npc_templates_trl add npc_goals field 20250302003");
}

if ($checkVersion("combined_npc_templates")<20250302003) {
    Logger::debug("try patch: combined_npc_templates view update for npc_goals 20250302003");
    
    $query="
    DROP VIEW IF EXISTS public.combined_npc_templates;
    CREATE VIEW public.combined_npc_templates AS
     SELECT c.npc_name,
        c.npc_pers,
        c.npc_dynamic,
        c.npc_misc,
        c.melotts_voiceid,
        c.xtts_voiceid,
        c.xvasynth_voiceid,
        c.npc_background,
        c.npc_personality,
        c.npc_appearance,
        c.npc_relationships,
        c.npc_occupation,
        c.npc_skills,
        c.npc_speechstyle,
        c.npc_goals
       FROM public.npc_templates_custom c
    UNION ALL
     SELECT t.npc_name,
        t.npc_pers,
        t.npc_dynamic,
        t.npc_misc,
        t.melotts_voiceid,
        t.xtts_voiceid,
        t.xvasynth_voiceid,
        t.npc_background,
        t.npc_personality,
        t.npc_appearance,
        t.npc_relationships,
        t.npc_occupation,
        t.npc_skills,
        t.npc_speechstyle,
        t.npc_goals
       FROM (public.npc_templates t
         LEFT JOIN public.npc_templates_custom c ON (((t.npc_name)::text = (c.npc_name)::text)))
      WHERE (c.npc_name IS NULL);";
    
    $db->execQuery($query);
    $updateVersion("combined_npc_templates",20250302003);
    Logger::info("Applied patch combined_npc_templates view update for npc_goals 20250302003");
}

//----------------------------------------------------
// Update npc_templates table with new data
// Version 20250618001- New Profile Fields
//----------------------------------------------------

if ($checkVersion("npc_templates")<20250618001) {
    Logger::debug("try patch: npc_templates data update 20250618001");
    
    // Clear existing data
    $query="TRUNCATE TABLE public.npc_templates";
    $db->execQuery($query);
    
    // Load new data from SQL file
    $db->execQuery(file_get_contents(__DIR__."/../data/npc_templates_20250618001.sql"));
    
    $updateVersion("npc_templates",20250618001);
    Logger::info("Applied patch npc_templates data update 20250618001");
    echo '<script>alert("The New Profile fields patch has been applied. Please check configuration wizard for new changes.")</script>';
}

//----------------------------------------------------

Logger::info(__FILE__." update file processed");

?>