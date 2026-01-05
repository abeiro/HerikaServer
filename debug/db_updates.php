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
        return intval($existsColumn[0]["version"]);
};

$checkTableExists = function($tablename) {
    global $db;
    $query = "
    
        SELECT 1 as exists
        FROM information_schema.tables 
        WHERE table_schema = 'public'
          AND table_name = '$tablename'
    
    ";

    $result = $db->fetchAll($query);

    if (sizeof($result) == 0) {
        return -1;
    }

    return ($result[0]["exists"] == "1")?1:-1;
};

$checkColumnExists = function($tablename, $columnname) {
    global $db;
    $query = "
        SELECT 1 AS exists 
        FROM information_schema.columns 
        WHERE table_schema = 'public' AND table_name = '{$tablename}' AND column_name = '{$columnname}'
    ";

    $result = $db->fetchAll($query);

    if (sizeof($result) == 0) {
        return -1;
    }

    return ($result[0]["exists"] == "1") ? 1 : -1;
};

$updateVersion = function($tablename,$version) {
    global $db;
    $db->execQuery("INSERT INTO public.database_versioning SELECT '$tablename',$version where not exists (SELECT 1 from public.database_versioning where tablename='$tablename')");
    $db->execQuery("UPDATE public.database_versioning set version=$version WHERE tablename='$tablename'");
    Logger::info("TABLE $tablename updated to version $version");
};

/////////////////////////

// Ensure base schema and extensions exist for fresh installs
$db->execQuery('CREATE SCHEMA IF NOT EXISTS public');
$db->execQuery("SET search_path TO public");
$db->execQuery('CREATE EXTENSION IF NOT EXISTS vector');
$db->execQuery('CREATE EXTENSION IF NOT EXISTS pg_trgm');

// Ensure database_versioning exists before version checks
try {
    $exists = $db->fetchAll("SELECT 1 FROM information_schema.tables WHERE table_schema='public' AND table_name='database_versioning'");
    if (!$exists) {
        $db->execQuery(file_get_contents(__DIR__."/../data/database_versioning.sql"));
        $db->execQuery("SET search_path TO public");
    }
} catch (Exception $e) {
    Logger::warn("database_versioning bootstrap: ".$e->getMessage());
}

// Bootstrap critical core tables early to avoid UI queries failing during initial load
try {
    if ($checkTableExists("core_api_badge") == -1) {
        $db->execQuery(file_get_contents(__DIR__."/../lib/core/database_schema/core_api_badge.sql"));
        $db->execQuery("SET search_path TO public");
    }
    if ($checkTableExists("core_itt_connector") == -1) {
        $db->execQuery(file_get_contents(__DIR__."/../lib/core/database_schema/core_itt_connector.sql"));
        $db->execQuery("SET search_path TO public");
    }
    if ($checkTableExists("core_tts_connector") == -1) {
        $db->execQuery(file_get_contents(__DIR__."/../lib/core/database_schema/core_tts_connector.sql"));
        $db->execQuery("SET search_path TO public");
    }
    if ($checkTableExists("core_llm_connector") == -1) {
        // ensure api_badge for FK first
        if ($checkTableExists("core_api_badge") == -1) {
            $db->execQuery(file_get_contents(__DIR__."/../lib/core/database_schema/core_api_badge.sql"));
            $db->execQuery("SET search_path TO public");
        }
        $db->execQuery(file_get_contents(__DIR__."/../lib/core/database_schema/core_llm_connector.sql"));
        $db->execQuery("SET search_path TO public");
    }
if ($checkTableExists("core_profiles") == -1) {
    $db->execQuery(file_get_contents(__DIR__."/../lib/core/database_schema/core_profiles.sql"));
    $db->execQuery("SET search_path TO public");
}
    if ($checkTableExists("core_npc_master") == -1) {
        $db->execQuery(file_get_contents(__DIR__."/../lib/core/database_schema/core_npc_master.sql"));
        $db->execQuery("SET search_path TO public");
    }
    if ($checkTableExists("core_player") == -1) {
        $db->execQuery(file_get_contents(__DIR__."/../lib/core/database_schema/core_player.sql"));
        $db->execQuery("SET search_path TO public");
    }
    if ($checkTableExists("core_narrator") == -1) {
        $db->execQuery(file_get_contents(__DIR__."/../lib/core/database_schema/core_narrator.sql"));
        $db->execQuery("SET search_path TO public");
    }
} catch (Exception $e) {
    Logger::warn("Bootstrap core tables: " . $e->getMessage());
}

// Narrator is now managed via core_narrator table, not core_npc_master
// Seeding of narrator data happens in the core_narrator migration blocks

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

// Ensure memory_v exists
// Memory ts
$query = "
    SELECT view_definition 
    FROM information_schema.views 
    WHERE table_name = 'memory_v'
";


$existsColumn=$db->fetchAll($query);
if (!$existsColumn[0]["view_definition"]) {
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
    
}

// Recreate vectors summary
$query = "
    SELECT column_name 
    FROM information_schema.columns 
    WHERE table_name = 'memory_summary' AND column_name = 'embedding'
";

$existsColumn=$db->fetchAll($query);
if (!$existsColumn[0]["column_name"]) {
    $db->execQuery('ALTER TABLE memory_summary add embedding VECTOR(384)');
    
}

// Recreate vectors summary
$query = "
    SELECT column_name 
    FROM information_schema.columns 
    WHERE table_name = 'memory_summary' AND column_name = 'embedding768'
";

$existsColumn=$db->fetchAll($query);
if (!$existsColumn[0]["column_name"]) {
    $db->execQuery('ALTER TABLE memory_summary add embedding768 VECTOR(768)');
    
}


// Ensure combined_animations exists
$query = "
    SELECT view_definition 
    FROM information_schema.views 
    WHERE table_name = 'combined_animations'
";


$existsColumn=$db->fetchAll($query);
if (!$existsColumn[0]["view_definition"]) {
        $db->execQuery("CREATE OR REPLACE VIEW public.combined_animations AS
 SELECT c.mood,
    c.animations,
    c.npc
   FROM public.animations_custom c
UNION ALL
 SELECT t.mood,
    t.animations,
    t.npc
   FROM (public.animations t
     LEFT JOIN public.animations_custom c ON (((t.mood)::text = (c.mood)::text)))
  WHERE (c.mood IS NULL);
");

}


// Ensure combined_animations exists
$query = "
    SELECT view_definition 
    FROM information_schema.views 
    WHERE table_name = 'combined_npc_templates'
";


$existsColumn=$db->fetchAll($query);
if (!$existsColumn[0]["view_definition"]) {
        $db->execQuery("CREATE OR REPLACE VIEW public.combined_npc_templates  AS
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
  WHERE (c.npc_name IS NULL);
");

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

        // before updating primary key, check if the new value exists
        $rx = $db->fetchAll("SELECT count(*) as n_recs FROM npc_templates_custom WHERE npc_name='$cn' ");
        if (isset($rx[0]) && ($rx[0]["n_recs"] > 0)) { // corrected npc name already exists, delete malformed one
            Logger::warn(" npc_templates_custom: potential duplicate primary key value deleted ({$on} => {$cn}) ");
            $db->execQuery("DELETE FROM npc_templates_custom WHERE npc_name='$on' "); 
        } else { // safe to update
            $db->execQuery("UPDATE npc_templates_custom SET npc_name='$cn' WHERE npc_name='$on' ");
        }
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
$db->execQuery("SET search_path TO public");
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

// Check if functions exist to force patch if they're missing
$checkFunctionExists = function($functionName) {
    global $db;
    $query = "
        SELECT 1 
        FROM information_schema.routines 
        WHERE routine_schema = 'public' 
          AND routine_name = '$functionName'
    ";
    $result = $db->fetchAll($query);
    return (sizeof($result) > 0);
};

$forceRecreate = false;
if (!$checkFunctionExists('convert_gamets2skyrim_date') || 
    !$checkFunctionExists('convert_gamets2days') ||
    !$checkFunctionExists('convert_gamets2gregorian_date') ||
    !$checkFunctionExists('convert_gamets2skyrim_long_date')) {
    Logger::warn("Some gamets conversion functions are missing. Forcing recreation.");
    $forceRecreate = true;
}

if ($checkVersion("sql_gamets_convert_functions")<20250218001 || $forceRecreate) {
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

// Check if additional functions exist to force patch if they're missing
$forceRecreate2 = false;
if (!$checkFunctionExists('convert_gamets2skyrim_date_fmt') || 
    !$checkFunctionExists('convert_gamets2skyrim_long_date2_nt') ||
    !$checkFunctionExists('convert_gamets2skyrim_long_date_nt') ||
    !$checkFunctionExists('convert_gamets2skyrim_time_daypart')) {
    Logger::warn("Some additional gamets conversion functions are missing. Forcing recreation.");
    $forceRecreate2 = true;
}

if ($checkVersion("sql_gamets_convert_functions")<20250226001 || $forceRecreate2) {
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



// Views dependant on sql_gamets_convert_functions
// Only create views if the required functions exist
$requiredFunctions = [
    'convert_gamets2skyrim_date',
    'convert_gamets2skyrim_long_date',
    'convert_gamets2days',
    'convert_gamets2gregorian_date'
];

$allFunctionsExist = true;
foreach ($requiredFunctions as $funcName) {
    if (!$checkFunctionExists($funcName)) {
        Logger::warn("Required function $funcName does not exist. Skipping view creation. Run database functions patch first.");
        $allFunctionsExist = false;
        break;
    }
}

if ($allFunctionsExist) {
    // Ensure speech_view exists
    $query = "
        SELECT view_definition 
        FROM information_schema.views 
        WHERE table_name = 'speech_view'
    ";

    $existsColumn=$db->fetchAll($query);
    if (!$existsColumn[0]["view_definition"]) {
            $db->execQuery("CREATE OR REPLACE VIEW public.speech_view  AS
      SELECT s.sess,
        s.speaker,
        s.speech,
        s.location,
        s.listener,
        s.topic,
        s.localts,
        s.gamets,
        s.ts,
        s.rowid,
        s.companions,
        s.audios,
        public.convert_gamets2skyrim_date(s.gamets) AS sk_date,
        public.convert_gamets2skyrim_long_date(s.gamets) AS sk_long_date,
        public.convert_gamets2days(s.gamets) AS sk_days,
        public.convert_gamets2gregorian_date(s.gamets) AS gregorian_date
       FROM public.speech s;
    ");
    }

    // Ensure eventlog_view exists
    $query = "
        SELECT view_definition 
        FROM information_schema.views 
        WHERE table_name = 'eventlog_view'
    ";

    $existsColumn=$db->fetchAll($query);
    if (!$existsColumn[0]["view_definition"]) {
            $db->execQuery("CREATE OR REPLACE VIEW public.eventlog_view  AS
     SELECT e.type,
        e.data,
        e.sess,
        e.gamets,
        e.localts,
        e.ts,
        e.rowid,
        e.people,
        e.location,
        e.party,
        public.convert_gamets2skyrim_date(e.gamets) AS sk_date,
        public.convert_gamets2skyrim_long_date(e.gamets) AS sk_long_date,
        public.convert_gamets2days(e.gamets) AS sk_days,
        public.convert_gamets2gregorian_date(e.gamets) AS gregorian_date
       FROM public.eventlog e;
    ");
    }
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
            $db->execQuery("INSERT INTO public.dynamic_bio (prompt) 
                SELECT '".$escapedPrompt."' 
                WHERE NOT EXISTS (
                    SELECT 1 FROM public.dynamic_bio WHERE prompt = '".$escapedPrompt."'
                )");
        }
    
    $updateVersion("dynamic_bio", 20250710001);
}

//----------------------------------------------------

//if ($checkVersion("oghma")<20250903001) { // version 202509... 
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
            $db->execQuery("CREATE EXTENSION IF NOT EXISTS vector;");
            $db->execQuery("ALTER TABLE public.oghma ADD COLUMN \"vector384\" public.vector(384)");
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
//}

if ($checkVersion("oghma")<20260104001) {
    $query = "DELETE FROM public.oghma WHERE topic = 'dragon_tongue'";
    $db->execQuery($query);
    $updateVersion("oghma",20260104001);
    Logger::info("Applied patch oghma 20260104001 - Removed dragon_tongue entry");
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


if ($checkVersion("audit_request")<20250616001) {
    Logger::debug(" try patch: audit_request 20250616001");
    $a=$db->execQuery("ALTER TABLE public.audit_request ADD COLUMN IF NOT EXISTS \"url\"  text");
    $a=$a && $db->execQuery("ALTER TABLE public.audit_request ADD COLUMN IF NOT EXISTS \"connector\"  text");
    if ($a) {
        $updateVersion("audit_request",20250616001);
        Logger::info("Applied patch audit_request 20250616001");
    } else {
        Logger::error("Patch audit_request 20250616001 failed!");
    }
}

//----------------------------------------------------
// database maintenance tools
// - autovacuum / table
//----------------------------------------------------

if ($checkVersion("db_maintenance")<20251208001) {
    Logger::debug(" try patch: db_maintenance 20251208001");

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

    $db->execQuery("SELECT public.sql_exec2('ALTER TABLE '||quote_ident(pgn.nspname)||'.'||quote_ident(pgc.relname)||' SET (autovacuum_enabled = on, toast.autovacuum_enabled = on);')
        FROM pg_catalog.pg_class pgc
        LEFT JOIN pg_catalog.pg_namespace pgn ON pgn.oid = pgc.relnamespace
        WHERE (pgc.relkind ='r')
        AND (pgn.nspname='public'); ");

    $updateVersion("db_maintenance",20251208001);
    Logger::info("Applied patch db_maintenance 20251208001");
}

//----------------------------------------------------
// NPC Templates Extended Profile Update 
// Version 20250619001 - Works for both new and existing installs
//----------------------------------------------------

if ($checkVersion("npc_templates")<20250619001) {
    Logger::debug("Applying consolidated NPC templates extended profile update 20250619001");
    
    // Ensure all NPC template tables exist with complete structure
    $db->execQuery("
        CREATE TABLE IF NOT EXISTS public.npc_templates (
            npc_name character varying(128) NOT NULL PRIMARY KEY,
            npc_pers text NOT NULL,
            npc_misc text,
            npc_dynamic text,
            melotts_voiceid text,
            xtts_voiceid text,
            xvasynth_voiceid text,
            npc_background text,
            npc_personality text,
            npc_appearance text,
            npc_relationships text,
            npc_occupation text,
            npc_skills text,
            npc_speechstyle text,
            npc_goals text
        );
    ");
    
    $db->execQuery("
        CREATE TABLE IF NOT EXISTS public.npc_templates_custom (
            npc_name character varying(128) NOT NULL PRIMARY KEY,
            npc_pers text NOT NULL,
            npc_misc text,
            npc_dynamic text,
            melotts_voiceid text,
            xtts_voiceid text,
            xvasynth_voiceid text,
            npc_background text,
            npc_personality text,
            npc_appearance text,
            npc_relationships text,
            npc_occupation text,
            npc_skills text,
            npc_speechstyle text,
            npc_goals text
        );
    ");
    
    // Add columns to existing tables (safe if they already exist)
    $columns_to_add = [
        'npc_dynamic', 'melotts_voiceid', 'xtts_voiceid', 'xvasynth_voiceid',
        'npc_background', 'npc_personality', 'npc_appearance', 'npc_relationships',
        'npc_occupation', 'npc_skills', 'npc_speechstyle', 'npc_goals'
    ];
    
    foreach ($columns_to_add as $column) {
        try {
            $db->execQuery("ALTER TABLE public.npc_templates ADD COLUMN IF NOT EXISTS $column text");
            $db->execQuery("ALTER TABLE public.npc_templates_custom ADD COLUMN IF NOT EXISTS $column text");
        } catch (Exception $e) {
            // Column might already exist, continue
            Logger::debug("Column $column might already exist: " . $e->getMessage());
        }
    }
    
    // Create/update the combined view with all columns
    $db->execQuery("DROP VIEW IF EXISTS public.combined_npc_templates CASCADE;");
    $db->execQuery("
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
        WHERE (c.npc_name IS NULL);
    ");
    
    // Load/update NPC template data preserving existing custom data
    try {
        $sqlFile = __DIR__."/../data/npc_templates_20250618001.sql";
        if (file_exists($sqlFile)) {
            // Create temporary table for new data (define columns explicitly to avoid dependency on base table)
            $db->execQuery("DROP TABLE IF EXISTS npc_templates_new");
    $db->execQuery("CREATE TEMP TABLE npc_templates_new (
                npc_name character varying(128) PRIMARY KEY,
                npc_pers text NOT NULL,
                npc_misc text,
                npc_dynamic text,
                melotts_voiceid text,
                xtts_voiceid text,
                xvasynth_voiceid text,
                npc_background text,
                npc_personality text,
                npc_appearance text,
                npc_relationships text,
                npc_occupation text,
                npc_skills text,
                npc_speechstyle text,
                npc_goals text
            )");
            
            // Load new data into temp table
            $newDataSql = file_get_contents($sqlFile);
            // Replace table references to use temp table
            $newDataSql = str_replace('INSERT INTO public.npc_templates', 'INSERT INTO npc_templates_new', $newDataSql);
            $newDataSql = str_replace('INSERT INTO npc_templates', 'INSERT INTO npc_templates_new', $newDataSql);
            $newDataSql = str_replace('npc_templates_new_new', 'npc_templates_new', $newDataSql);
            
            // Defensive: ensure base tables exist before upsert
            $db->execQuery("CREATE TABLE IF NOT EXISTS public.npc_templates (
                npc_name character varying(128) PRIMARY KEY,
                npc_pers text NOT NULL,
                npc_misc text,
                npc_dynamic text,
                melotts_voiceid text,
                xtts_voiceid text,
                xvasynth_voiceid text,
                npc_background text,
                npc_personality text,
                npc_appearance text,
                npc_relationships text,
                npc_occupation text,
                npc_skills text,
                npc_speechstyle text,
                npc_goals text
            )");
            $db->execQuery($newDataSql);
            
            // Upsert from temp table to main table with explicit column list
            $db->execQuery("INSERT INTO public.npc_templates (
                npc_name, npc_pers, npc_misc, npc_dynamic,
                melotts_voiceid, xtts_voiceid, xvasynth_voiceid,
                npc_background, npc_personality, npc_appearance, npc_relationships,
                npc_occupation, npc_skills, npc_speechstyle, npc_goals
            )
            SELECT 
                npc_name, npc_pers, npc_misc, npc_dynamic,
                melotts_voiceid, xtts_voiceid, xvasynth_voiceid,
                npc_background, npc_personality, npc_appearance, npc_relationships,
                npc_occupation, npc_skills, npc_speechstyle, npc_goals
            FROM npc_templates_new
            ON CONFLICT (npc_name) DO UPDATE SET
                npc_pers = EXCLUDED.npc_pers,
                npc_dynamic = EXCLUDED.npc_dynamic,
                npc_misc = EXCLUDED.npc_misc,
                melotts_voiceid = EXCLUDED.melotts_voiceid,
                xtts_voiceid = EXCLUDED.xtts_voiceid,
                xvasynth_voiceid = EXCLUDED.xvasynth_voiceid,
                npc_background = EXCLUDED.npc_background,
                npc_personality = EXCLUDED.npc_personality,
                npc_appearance = EXCLUDED.npc_appearance,
                npc_relationships = EXCLUDED.npc_relationships,
                npc_occupation = EXCLUDED.npc_occupation,
                npc_skills = EXCLUDED.npc_skills,
                npc_speechstyle = EXCLUDED.npc_speechstyle,
                npc_goals = EXCLUDED.npc_goals");
            
            Logger::info("NPC template data loaded/updated successfully");
        }
    } catch (Exception $e) {
        Logger::error("Error loading NPC template data: " . $e->getMessage());
        // Continue with structure updates even if data loading fails
    }
    
    $updateVersion("npc_templates", 20250619001);
    Logger::info("Applied consolidated NPC templates extended profile update 20250619001");
    echo '<script>alert("NPC Templates have been updated with extended profile fields!");</script>';
}

if ($checkTableExists("core_api_badge") == -1) {
    $db->execQuery(file_get_contents(__DIR__."/../lib/core/database_schema/core_api_badge.sql"));
    $db->execQuery("SET search_path TO public");
} else
    Logger::info(__FILE__." core_api_badge exists");

// Add unique constraint on core_api_badge.label to prevent duplicates
if ($checkTableExists("core_api_badge") > 0 && $checkVersion("core_api_badge") < 20251127001) {
    try {
        // Remove duplicates: keep row with highest id and non-empty key per label (case-insensitive)
        $db->execQuery("
            DELETE FROM public.core_api_badge a
            WHERE a.id NOT IN (
                SELECT DISTINCT ON (LOWER(label)) id
                FROM public.core_api_badge
                ORDER BY LOWER(label), CASE WHEN api_key = '' THEN 0 ELSE 1 END DESC, id DESC
            )
        ");
        
        // Normalize label casing to match preset expectations
        $db->execQuery("UPDATE public.core_api_badge SET label = 'OpenRouter' WHERE LOWER(label) = 'openrouter'");
        $db->execQuery("UPDATE public.core_api_badge SET label = 'OpenAI' WHERE LOWER(label) = 'openai'");
        $db->execQuery("UPDATE public.core_api_badge SET label = 'Deepgram' WHERE LOWER(label) = 'deepgram'");
        $db->execQuery("UPDATE public.core_api_badge SET label = 'Google' WHERE LOWER(label) = 'google'");
        $db->execQuery("UPDATE public.core_api_badge SET label = 'Azure' WHERE LOWER(label) = 'azure'");
        $db->execQuery("UPDATE public.core_api_badge SET label = 'ElevenLabs' WHERE LOWER(label) = 'elevenlabs'");
        $db->execQuery("UPDATE public.core_api_badge SET label = 'Cartesia' WHERE LOWER(label) = 'cartesia'");
        $db->execQuery("UPDATE public.core_api_badge SET label = 'Replicate' WHERE LOWER(label) = 'replicate'");
        $db->execQuery("UPDATE public.core_api_badge SET label = 'Nano-GPT' WHERE LOWER(label) = 'nano-gpt'");
        $db->execQuery("UPDATE public.core_api_badge SET label = 'DeepL' WHERE LOWER(label) = 'deepl'");
        
        // Add unique constraint
        $db->execQuery("ALTER TABLE public.core_api_badge ADD CONSTRAINT core_api_badge_label_unique UNIQUE (label)");
        
        // Add case-insensitive index for faster lookups
        $db->execQuery("CREATE INDEX IF NOT EXISTS idx_core_api_badge_label_lower ON public.core_api_badge (LOWER(label))");
        
        $updateVersion("core_api_badge", 20251127001);
        Logger::info("Applied core_api_badge unique constraint 20251127001 (cleaned duplicates, normalized case, added UNIQUE constraint)");
    } catch (Exception $e) {
        Logger::warn("core_api_badge unique constraint update: " . $e->getMessage());
    }
}

if ($checkTableExists("core_itt_connector") == -1) {
    $db->execQuery(file_get_contents(__DIR__."/../lib/core/database_schema/core_itt_connector.sql"));
    $db->execQuery("SET search_path TO public");
} else
    Logger::info(__FILE__." core_itt_connector exists");

if ($checkTableExists("core_llm_connector") == -1) {
    $db->execQuery(file_get_contents(__DIR__."/../lib/core/database_schema/core_llm_connector.sql"));
    $db->execQuery("SET search_path TO public");
} else
    Logger::info(__FILE__." core_llm_connector exists");

// Add 'service' column to core_llm_connector if missing
$query = "
    SELECT column_name 
    FROM information_schema.columns 
    WHERE table_name = 'core_llm_connector' AND column_name = 'service'
";

$existsColumn=$db->fetchAll($query);
if (!$existsColumn || !$existsColumn[0]["column_name"]) {
    $db->execQuery('ALTER TABLE "core_llm_connector" ADD COLUMN "service" text');
    echo '<script>alert("A patch (add service column to core_llm_connector) has been applied to Database")</script>';
}

if ($checkTableExists("core_npc_master_history") == -1) {
    $db->execQuery(file_get_contents(__DIR__."/../lib/core/database_schema/core_npc_master_history.sql"));
} else
    Logger::info(__FILE__." core_npc_master_history exists");

if ($checkTableExists("core_stt_connector") == -1) {
    $db->execQuery(file_get_contents(__DIR__."/../lib/core/database_schema/core_stt_connector.sql"));
} else
    Logger::info(__FILE__." core_stt_connector exists");


if ($checkTableExists("core_tts_connector") == -1) {
    $db->execQuery(file_get_contents(__DIR__."/../lib/core/database_schema/core_tts_connector.sql"));
    $db->execQuery("SET search_path TO public");
} else
    Logger::info(__FILE__." core_tts_connector exists");

if ($checkTableExists("core_llm_connector") == -1) {
    $db->execQuery(file_get_contents(__DIR__."/../lib/core/database_schema/core_llm_connector.sql"));
} else
    Logger::info(__FILE__." core_llm_connector exists");

if ($checkTableExists("core_profiles") == -1) {
    $db->execQuery(file_get_contents(__DIR__."/../lib/core/database_schema/core_profiles.sql"));
    $db->execQuery("SET search_path TO public");
} else
    Logger::info(__FILE__." core_profiles exists");

if ($checkTableExists("core_npc_master") == -1) {
    $db->execQuery(file_get_contents(__DIR__."/../lib/core/database_schema/core_npc_master.sql"));
    $db->execQuery("SET search_path TO public");
} else
    Logger::info(__FILE__." core_npc_master exists");


if (($checkTableExists("core_profiles") > 0) && ($checkVersion("core_profiles") < 20250904005)) {
    $db->execQuery(file_get_contents(__DIR__."/../lib/core/database_schema/core_profiles_2.sql"));
    $db->execQuery("SET search_path TO public");
    // ensure slot column exists for existing installs
    $db->execQuery('ALTER TABLE public.core_profiles ADD COLUMN IF NOT EXISTS "slot" integer');
    // set default profile slot to 1 if missing
    $db->execQuery("UPDATE public.core_profiles SET slot = 1 WHERE id = 1 AND (slot IS NULL OR slot = 0)");
    $updateVersion("core_profiles",20250904005);
    Logger::info("Applied core_profiles 20250904005 (added slot, set default slot=1)");
} else {
    Logger::info(__FILE__." core_profiles up-to-date");
}

// Ensure core_profiles.slot exists even if version was previously bumped
try {
    $colCheck = $db->fetchAll("SELECT column_name FROM information_schema.columns WHERE table_schema='public' AND table_name='core_profiles' AND column_name='slot'");
    if (!$colCheck || !isset($colCheck[0]["column_name"])) {
        Logger::warn("core_profiles.slot missing; adding column now");
        $db->execQuery('ALTER TABLE public.core_profiles ADD COLUMN "slot" integer');
        $db->execQuery("UPDATE public.core_profiles SET slot = 1 WHERE id = 1 AND (slot IS NULL OR slot = 0)");
        if ($checkVersion("core_profiles") < 20250904006) {
            $updateVersion("core_profiles",20250904006);
        }
        Logger::info("Added core_profiles.slot and set default profile slot=1");
    } else {
        // Column exists; still ensure default profile set to 1
        $db->execQuery("UPDATE public.core_profiles SET slot = 1 WHERE id = 1 AND (slot IS NULL OR slot = 0)");
    }
} catch (Exception $e) {
    Logger::error("Error ensuring core_profiles.slot: ".$e->getMessage());
}

// Enforce uniqueness of core_profiles.slot (1-4), allowing NULLs
try {
    $idx = $db->fetchAll("SELECT indexname FROM pg_indexes WHERE schemaname='public' AND tablename='core_profiles' AND indexname='core_profiles_slot_unique_idx'");
    if (!$idx || !isset($idx[0]["indexname"])) {
        // Clear duplicates: keep the lowest id per slot, set others to NULL
        $db->execQuery("WITH d AS (
            SELECT id, slot, ROW_NUMBER() OVER (PARTITION BY slot ORDER BY id) AS rn
            FROM public.core_profiles WHERE slot IS NOT NULL
        )
        UPDATE public.core_profiles p SET slot = NULL
        FROM d WHERE p.id = d.id AND d.rn > 1");
        // Create unique partial index
    $db->execQuery("CREATE UNIQUE INDEX IF NOT EXISTS core_profiles_slot_unique_idx ON public.core_profiles (slot) WHERE slot IS NOT NULL");
    $db->execQuery("SET search_path TO public");
        // Ensure default profile has slot 1
        $db->execQuery("UPDATE public.core_profiles SET slot = 1 WHERE id = 1 AND (slot IS NULL OR slot = 0)");
        if ($checkVersion("core_profiles") < 20250904007) {
            $updateVersion("core_profiles",20250904007);
        }
        Logger::info("Enforced unique slot on core_profiles and set default slot=1");
    }
} catch (Exception $e) {
    Logger::error("Error enforcing unique slot index: ".$e->getMessage());
}

// Add llm_fallback_id column to core_profiles for fallback connector support
if ($checkVersion("core_profiles") < 20251203001) {
    Logger::debug("Applying core_profiles 20251203001 - Adding llm_fallback_id for fallback support");
    try {
        // Add the column if it doesn't exist
        $db->execQuery('ALTER TABLE public.core_profiles ADD COLUMN IF NOT EXISTS llm_fallback_id integer');
        
        // Add foreign key constraint if it doesn't exist
        $fkExists = $db->fetchAll("
            SELECT 1 FROM pg_constraint 
            WHERE conname = 'profiles_llm_fallback_id_fkey'
        ");
        
        if (!$fkExists || !isset($fkExists[0])) {
            $db->execQuery("
                ALTER TABLE public.core_profiles
                ADD CONSTRAINT profiles_llm_fallback_id_fkey 
                FOREIGN KEY (llm_fallback_id) REFERENCES public.core_llm_connector(id)
            ");
            Logger::info("Added foreign key constraint profiles_llm_fallback_id_fkey");
        }
        
        // Add comment
        $db->execQuery("
            COMMENT ON COLUMN public.core_profiles.llm_fallback_id 
            IS 'Fallback LLM connector used when primary connector fails with network error'
        ");
        
        $updateVersion("core_profiles", 20251203001);
        Logger::info("Applied patch core_profiles 20251203001 - Added llm_fallback_id for automatic fallback on network errors");
    } catch (Exception $e) {
        Logger::error("Error adding llm_fallback_id to core_profiles: " . $e->getMessage());
    }
}

// Final repair pass: ensure critical core tables exist even if versions were bumped earlier
try {
    $coreTables = [
        ["name"=>"core_api_badge",   "file"=>__DIR__."/../lib/core/database_schema/core_api_badge.sql"],
        ["name"=>"core_llm_connector","file"=>__DIR__."/../lib/core/database_schema/core_llm_connector.sql"],
        ["name"=>"core_tts_connector","file"=>__DIR__."/../lib/core/database_schema/core_tts_connector.sql"],
        ["name"=>"core_stt_connector","file"=>__DIR__."/../lib/core/database_schema/core_stt_connector.sql"],
        ["name"=>"core_profiles",     "file"=>__DIR__."/../lib/core/database_schema/core_profiles.sql"],
        ["name"=>"core_npc_master",   "file"=>__DIR__."/../lib/core/database_schema/core_npc_master.sql"]
    ];
    foreach ($coreTables as $t) {
        if ($checkTableExists($t["name"]) == -1) {
            Logger::warn("Repair: creating missing table ".$t["name"]);
            $db->execQuery(file_get_contents($t["file"]));
        }
    }
} catch (Exception $e) {
    Logger::error("Final repair pass failed: ".$e->getMessage());
}

//----------------------------------------------------
// Bio templates: new tables and combined view
// Version 20250913001
//----------------------------------------------------

if ($checkVersion("bio_templates")<20250913001) {
    Logger::debug("Applying bio_templates 20250913001");
    $db->execQuery("
        CREATE TABLE IF NOT EXISTS public.bio_templates (
            npc_name character varying(128) NOT NULL PRIMARY KEY,
            oghma_knowledge_tags text,
            core text,
            npc_static_bio text,
            appearance text,
            personality text,
            relationships text,
            occupation text,
            skills text,
            speechstyle text,
            goals text,
            voiceid text,
            gender text,
            race text,
            refid text
        );
    ");
    $updateVersion("bio_templates",20250913001);
    Logger::info("Applied patch bio_templates 20250913001");
}

if ($checkVersion("bio_templates_custom")<20250913001) {
    Logger::debug("Applying bio_templates_custom 20250913001");
    $db->execQuery("
        CREATE TABLE IF NOT EXISTS public.bio_templates_custom (
            npc_name character varying(128) NOT NULL PRIMARY KEY,
            oghma_knowledge_tags text,
            core text,
            npc_static_bio text,
            appearance text,
            personality text,
            relationships text,
            occupation text,
            skills text,
            speechstyle text,
            goals text,
            voiceid text,
            gender text,
            race text,
            refid text
        );
    ");
    $updateVersion("bio_templates_custom",20250913001);
    Logger::info("Applied patch bio_templates_custom 20250913001");
}

// Seed base bio templates from SQL file (run once)
if ($checkVersion("bio_templates_seed")<20250913001) {
    try {
        $sqlFile = __DIR__ . "/../data/bio_templates_20250913.sql";
        if (file_exists($sqlFile)) {
            $sqlContent = file_get_contents($sqlFile);
            if ($sqlContent !== false && strlen($sqlContent) > 0) {
                $db->execQuery($sqlContent);
                $updateVersion("bio_templates_seed", 20250913001);
                Logger::info("Seeded bio_templates from bio_templates_20250913.sql");
            } else {
                Logger::warn("bio_templates seed file is empty: " . $sqlFile);
            }
        } else {
            Logger::warn("bio_templates seed file not found: " . $sqlFile);
        }
    } catch (Exception $e) {
        Logger::error("Error seeding bio_templates: " . $e->getMessage());
    }
}

// Always (re)create combined view once base tables exist
try {
    $db->execQuery("DROP VIEW IF EXISTS public.combined_bio_templates CASCADE;");
    $db->execQuery("
        CREATE VIEW public.combined_bio_templates AS
        SELECT c.npc_name,
               c.oghma_knowledge_tags,
               c.core,
               c.npc_static_bio,
               c.appearance,
               c.personality,
               c.relationships,
               c.occupation,
               c.skills,
               c.speechstyle,
               c.goals,
               c.voiceid,
               c.gender,
               c.race,
               c.refid
          FROM public.bio_templates_custom c
        UNION ALL
        SELECT b.npc_name,
               b.oghma_knowledge_tags,
               b.core,
               b.npc_static_bio,
               b.appearance,
               b.personality,
               b.relationships,
               b.occupation,
               b.skills,
               b.speechstyle,
               b.goals,
               b.voiceid,
               b.gender,
               b.race,
               b.refid
          FROM (public.bio_templates b
                LEFT JOIN public.bio_templates_custom c
                  ON ((b.npc_name)::text = (c.npc_name)::text))
         WHERE c.npc_name IS NULL;
    ");
    // Track the view version under the same version key
    $updateVersion("combined_bio_templates",20250913001);
    Logger::info("Created view combined_bio_templates 20250913001");
} catch (Exception $e) {
    Logger::error("Error creating combined_bio_templates view: " . $e->getMessage());
}

// Remove DB-layer protection for The Narrator to allow deletion via UI
// Version 20250124001
if ($checkVersion("narrator_protection")<20250124001) {
    Logger::debug("Removing narrator delete/rename protection triggers");
    try {
        $db->execQuery("DROP TRIGGER IF EXISTS trg_protect_narrator_delete ON public.core_npc_master");
        $db->execQuery("DROP FUNCTION IF EXISTS public.protect_narrator_delete() CASCADE");
        $db->execQuery("DROP TRIGGER IF EXISTS trg_protect_narrator_rename ON public.core_npc_master");
        $db->execQuery("DROP FUNCTION IF EXISTS public.protect_narrator_rename() CASCADE");
        $updateVersion("narrator_protection",20250124001);
        Logger::info("Removed narrator protection triggers");
    } catch (Exception $e) {
        Logger::error("Error removing narrator protection triggers: " . $e->getMessage());
    }
}

// Enforce DB-layer protection for The Narrator: prevent delete or rename
// NOTE: This is now commented out to allow narrator deletion from UI
/*
try {
    $db->execQuery("DROP FUNCTION IF EXISTS public.protect_narrator_delete() CASCADE");
    $db->execQuery("CREATE OR REPLACE FUNCTION public.protect_narrator_delete() RETURNS trigger AS $$\nBEGIN\n    IF OLD.id = 1 OR OLD.npc_name = 'The Narrator' THEN\n        RAISE EXCEPTION 'Deletion of The Narrator is not allowed';\n    END IF;\n    RETURN OLD;\nEND;\n$$ LANGUAGE plpgsql;");
    $db->execQuery("DROP TRIGGER IF EXISTS trg_protect_narrator_delete ON public.core_npc_master");
    $db->execQuery("CREATE TRIGGER trg_protect_narrator_delete BEFORE DELETE ON public.core_npc_master FOR EACH ROW EXECUTE FUNCTION public.protect_narrator_delete();");

    $db->execQuery("DROP FUNCTION IF EXISTS public.protect_narrator_rename() CASCADE");
    $db->execQuery("CREATE OR REPLACE FUNCTION public.protect_narrator_rename() RETURNS trigger AS $$\nBEGIN\n    IF (OLD.id = 1 OR OLD.npc_name = 'The Narrator') AND NEW.npc_name IS DISTINCT FROM OLD.npc_name THEN\n        RAISE EXCEPTION 'Renaming The Narrator is not allowed';\n    END IF;\n    RETURN NEW;\nEND;\n$$ LANGUAGE plpgsql;");
    $db->execQuery("DROP TRIGGER IF EXISTS trg_protect_narrator_rename ON public.core_npc_master");
    $db->execQuery("CREATE TRIGGER trg_protect_narrator_rename BEFORE UPDATE OF npc_name ON public.core_npc_master FOR EACH ROW EXECUTE FUNCTION public.protect_narrator_rename();");
} catch (Exception $e) {
    Logger::warn("DB trigger setup for narrator protection failed or already present: ".$e->getMessage());
}
*/

//----------------------------------------------------
// Item descriptions: new tables and combined view
// Version 20241113001
//----------------------------------------------------

if ($checkVersion("descriptions")<20241114001) {
    Logger::debug("Applying descriptions 20241114001");
    $db->execQuery("
        CREATE TABLE IF NOT EXISTS public.descriptions (
            baseid character varying(128) NOT NULL PRIMARY KEY,
            name text,
            description text
        );
    ");
    $updateVersion("descriptions",20241114001);
    Logger::info("Applied patch descriptions 20241114001");
}

if ($checkVersion("descriptions_custom")<20241114001) {
    Logger::debug("Applying descriptions_custom 20241114001");
    $db->execQuery("
        CREATE TABLE IF NOT EXISTS public.descriptions_custom (
            baseid character varying(128) NOT NULL PRIMARY KEY,
            name text,
            description text
        );
    ");
    $updateVersion("descriptions_custom",20241114001);
    Logger::info("Applied patch descriptions_custom 20241114001");
}

if ($checkVersion("descriptions_defaults")<20241114001) {
    Logger::debug("Applying descriptions_defaults 20241114001");
    
    $sqlFile = __DIR__ . '/../data/descriptions_20241114001.sql';
    if (file_exists($sqlFile)) {
        $sql = file_get_contents($sqlFile);
        if ($sql !== false) {
            $db->execQuery($sql);
            Logger::info("Imported descriptions from descriptions_20241114001.sql");
        } else {
            Logger::warn("Could not read descriptions_20241114001.sql");
        }
    } else {
        Logger::warn("descriptions_20241114001.sql not found at $sqlFile");
    }
    
    $updateVersion("descriptions_defaults",20241114001);
    Logger::info("Applied patch descriptions_defaults 20241114001");
}

if ($checkVersion("spell_descriptions")<20241129001) {
    Logger::debug("Applying spell_descriptions 20241129001");
    
    $sqlFile = __DIR__ . '/../data/spell_descriptions.sql';
    if (file_exists($sqlFile)) {
        $sql = file_get_contents($sqlFile);
        if ($sql !== false) {
            $db->execQuery($sql);
            Logger::info("Imported spell descriptions from spell_descriptions.sql");
        } else {
            Logger::warn("Could not read spell_descriptions.sql");
        }
    } else {
        Logger::warn("spell_descriptions.sql not found at $sqlFile");
    }
    
    $updateVersion("spell_descriptions",20241129001);
    Logger::info("Applied patch spell_descriptions 20241129001");
}

if ($checkVersion("faction_descriptions")<20250115001) {
    Logger::debug("Applying faction_descriptions 20250115001");
    
    $sqlFile = __DIR__ . '/../data/faction_descriptions.sql';
    if (file_exists($sqlFile)) {
        $sql = file_get_contents($sqlFile);
        if ($sql !== false) {
            $db->execQuery($sql);
            Logger::info("Imported faction descriptions from faction_descriptions.sql");
        } else {
            Logger::warn("Could not read faction_descriptions.sql");
        }
    } else {
        Logger::warn("faction_descriptions.sql not found at $sqlFile");
    }
    
    $updateVersion("faction_descriptions",20250115001);
    Logger::info("Applied patch faction_descriptions 20250115001");
}

// Always (re)create combined view once base tables exist
try {
    $db->execQuery("DROP VIEW IF EXISTS public.combined_descriptions CASCADE;");
    $db->execQuery("
        CREATE VIEW public.combined_descriptions AS
        SELECT c.baseid,
               c.name,
               c.description
          FROM public.descriptions_custom c
        UNION ALL
        SELECT i.baseid,
               i.name,
               i.description
          FROM (public.descriptions i
                LEFT JOIN public.descriptions_custom c
                  ON ((i.baseid)::text = (c.baseid)::text))
         WHERE c.baseid IS NULL;
    ");
    $updateVersion("combined_descriptions",20241114001);
    Logger::info("Created view combined_descriptions 20241114001");
} catch (Exception $e) {
    Logger::error("Error creating combined_descriptions view: " . $e->getMessage());
}

try {
    $db->execQuery("CREATE OR REPLACE VIEW \"public\".\"memory_v\" AS
 SELECT message,
    uid,
    gamets,
    speaker,
    listener,
    ts
   FROM ( SELECT memory.message,
            memory.uid,
            memory.gamets,
            '-'::text AS speaker,
            '-'::text AS listener,
            memory.ts
           FROM memory
          WHERE memory.message !~~ 'Dear Diary%'::text AND memory.message <> ''::text and event<>'backgroundlife_diary'::text
        UNION
         SELECT (((('(Context Location:'::text || speech.location) || ') '::text) || speech.speaker) || ': '::text) || speech.speech,
            speech.rowid::integer AS rowid,
            speech.gamets,
            speech.speaker,
            speech.listener,
            speech.ts
           FROM speech
          WHERE speech.speech <> ''::text
        UNION
         SELECT eventlog.data,
            eventlog.rowid::integer AS rowid,
            eventlog.gamets,
            '-'::text AS text,
            '-'::text AS listener,
            eventlog.ts
           FROM eventlog
          WHERE eventlog.type::text = ANY (ARRAY['death'::character varying::text, 'location'::character varying::text])) subquery
  ORDER BY gamets, ts");
    $updateVersion("memory_v",20251122001);
    Logger::info("Updated memory_v BgL patch");
} catch (Exception $e) {
    Logger::error("Error creating memory_v BgL patch: " . $e->getMessage());
}


if ($checkTableExists("translations") == -1) {
    $db->execQuery(file_get_contents(__DIR__."/../data/translations_table.sql"));
} else
    Logger::info(__FILE__." translations exists");

if ($checkTableExists("import_rules") == -1) {
    $db->execQuery(file_get_contents(__DIR__."/../data/import_rules.sql"));
} else
    Logger::info(__FILE__." import_rules exists");

// Usage column
$db->execQuery("ALTER TABLE public.audit_request ADD COLUMN IF NOT EXISTS usage jsonb");

if ($checkTableExists("rumors") == -1) {
    $db->execQuery(file_get_contents(__DIR__."/../data/add_rumors.sql"));
} else
    Logger::info(__FILE__." rumors exists");

if ($checkTableExists("named_cell") == -1) {
    $db->execQuery(file_get_contents(__DIR__."/../data/named_cell.sql"));
} else
    Logger::info(__FILE__." named_cell exists");

if ($checkColumnExists("named_cell","vanilla_cell") == -1) {
    $db->execQuery(file_get_contents(__DIR__."/../data/named_cell.sql"));
    Logger::info(__FILE__." named_cell - vanilla_cell not found! ");
} else {
    Logger::info(__FILE__." named_cell - vanilla_cell exists");
    if ($checkColumnExists("named_cell","door_id") == -1) {
        $db->execQuery(file_get_contents(__DIR__."/../data/named_cell.sql"));
        Logger::info(__FILE__." named_cell - door_id not found! ");
    } else
        Logger::info(__FILE__." named_cell - door_id exists");
}

if ($checkTableExists("sneq_quests_saved") == -1) {
    $db->execQuery(file_get_contents(__DIR__."/../data/sneq_quests_saved.sql"));
} else
    Logger::info(__FILE__." sneq_quests_saved exists");

$db->execQuery("ALTER TABLE public.locations ADD COLUMN IF NOT EXISTS region text");
$db->execQuery("ALTER TABLE public.locations ADD COLUMN IF NOT EXISTS hold text");
$db->execQuery("ALTER TABLE public.locations ADD COLUMN IF NOT EXISTS tags text");
$db->execQuery("ALTER TABLE public.locations ADD COLUMN IF NOT EXISTS factions text");
$db->execQuery("ALTER TABLE public.locations ADD COLUMN IF NOT EXISTS is_interior int");
$db->execQuery("ALTER TABLE public.locations ADD COLUMN IF NOT EXISTS vanilla_location boolean");
$db->execQuery("ALTER TABLE public.sneq_quests ADD COLUMN IF NOT EXISTS title text");
$db->execQuery("ALTER TABLE public.sneq_quests ADD COLUMN IF NOT EXISTS stage text");
$db->execQuery("DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'sneq_quests_saved_id'
    ) THEN
        ALTER TABLE public.sneq_quests_saved
        ADD CONSTRAINT sneq_quests_saved_id
        PRIMARY KEY (history_id);
    END IF;
END $$;");

if ($checkTableExists("master_packages") == -1) {
    $db->execQuery(file_get_contents(__DIR__."/../data/master_packages.sql"));
} else
    Logger::info(__FILE__." master_packages exists");

//----------------------------------------------------
// Prompts Table - System for managing default and custom prompts
// Version 20251110001
//----------------------------------------------------

if ($checkVersion("prompts")<20251110001) {
    Logger::debug("Applying prompts table 20251110001");
    
    // Create prompts table
    $db->execQuery("
        CREATE TABLE IF NOT EXISTS public.prompts (
            prompt_key character varying(128) NOT NULL PRIMARY KEY,
            default_prompt text NOT NULL,
            custom_prompt text,
            description text,
            created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
            updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    // Seed initial middleterm narrative summarizer system prompt
    $middletermPrompt = $db->escape(
        "You are a long-term narrative continuity summarizer for an improvised Skyrim universe chronicle.\n".
        "- Always read ALL provided materials.\n".
        "- Treat any **Previous Context History Summary** as the canonical prior unless anything in the new Context History explicitly supersedes it.\n".
        "- Maintain in-universe tone and correct chronology. Do not invent facts outside the supplied context.\n".
        "- When combining prior and new histories, you may compress the earlier parts of the prior summary.\n".
        "- Maintain roughly 20–25 bullet points total in **Notable Events**. Older portions should be condensed into broader, grouped statements unless they describe major quest milestones, major character life events (e.g., death, intimacy, severe injury, transformation), or other pivotal story turns.\n".
        "- Preserve continuity and references to major quests even when compressing earlier material."
    );
    
    $db->execQuery("
        INSERT INTO public.prompts (prompt_key, default_prompt, description)
        VALUES (
            'middleterm_narrative_summarizer',
            '$middletermPrompt',
            'System prompt for long-term narrative continuity summarization in middleterm memory processing. Used in: service/processors/middleterm/cmd/generate.php'
        )
        ON CONFLICT (prompt_key) DO UPDATE SET
            default_prompt = EXCLUDED.default_prompt,
            description = EXCLUDED.description,
            updated_at = CURRENT_TIMESTAMP
    ");
    
    // Seed middleterm request/task prompt (uses {HERIKA_NAME} placeholder)
    $middletermRequestPrompt = $db->escape(
        "Main character in this logbook is {HERIKA_NAME}.\n".
        "Task: Read **Context History** (newest session) and, if present, the **Previous Context History Summary** (prior canon). ".
        "Integrate them to produce an updated broad narrative strokes summary that preserves continuity. Summary sections:\n\n".
        "- **Notable Events in Chronological Order:**\n".
        "  - Provide ~10 bullet points from earliest to latest, reflecting the story so far.\n".
        "  - Prefer facts already established in the previous summary; only revise if the new context clearly changes them.\n\n".
        "- **Current Quest Progression and background:**\n".
        "  - Name questlines, stages/milestones if stated, objectives completed/active, and motivations.\n".
        "When generating entries, ensure that {HERIKA_NAME} — the protagonist — is actively present in the scene. ".
        "Any narrative content that occurs before {HERIKA_NAME}'s arrival or outside {HERIKA_NAME}'s perspective should be omitted, ".
        "reflect only events {HERIKA_NAME} directly witness or participate in.\n".
        "If the resulting summary would exceed roughly 25 bullet points, merge or generalise older entries into broader grouped events. ".
        "Always retain explicit entries for major quest milestones, major character life events, or turning points."
    );
    
    $db->execQuery("
        INSERT INTO public.prompts (prompt_key, default_prompt, description)
        VALUES (
            'middleterm_narrative_request',
            '$middletermRequestPrompt',
            'User request/task instructions for middleterm narrative summarization (contains {HERIKA_NAME} placeholder). Used in: service/processors/middleterm/cmd/generate.php'
        )
        ON CONFLICT (prompt_key) DO UPDATE SET
            default_prompt = EXCLUDED.default_prompt,
            description = EXCLUDED.description,
            updated_at = CURRENT_TIMESTAMP
    ");
    
    // Seed character profile generation prompt (uses {HERIKA_NAME} and {CHARACTER_SEED} placeholders)
    $profileGenPrompt = $db->escape(
        "The main character in this logbook is {HERIKA_NAME}.{CHARACTER_SEED}\n".
        "Read the context history (context_history) and the recent memories (middle_term_memory),\n".
        " paying attention to notable events and the names of relevant characters.\n\n\n".
        "Based on all this information, generate an character sheet for {HERIKA_NAME}.\n\n".
        "This profile must be in XML format and have these fields.\n\n".
        "<core>              Text. Core Identity, name,race an gender, and most remarkable job. Should be in the form of a sentence. e.g. 'Rose. Imperial female warrior.'\n".
        "<npc_static_bio>    Text. Basic Summary, and bio. Create if not info available in <context_history>\n".
        "<personality>       Text. Personality Traits. How the characters behave. Traumas. Likes.\n".
        "<appearance>        Text. Physical Appearance. Infer from info available in <context_history>\n".
        "<relationships>     Text. relationships with other actors.\n".
        "<occupation>        Text. Main Occupation & Role\n".
        "<skills>            Text. Skills & Abilities\n".
        "<speechstyle>       Text. Speech Style\n".
        "<goals>             Text. Long term Goals & Aspirations'\n"
    );
    
    $db->execQuery("
        INSERT INTO public.prompts (prompt_key, default_prompt, description)
        VALUES (
            'character_profile_generation',
            '$profileGenPrompt',
            'Prompt for AI-generated character profile/biography creation (contains {HERIKA_NAME} and {CHARACTER_SEED} placeholders). Used in: ui/cmd/action_ai_regen_profile.php'
        )
        ON CONFLICT (prompt_key) DO UPDATE SET
            default_prompt = EXCLUDED.default_prompt,
            description = EXCLUDED.description,
            updated_at = CURRENT_TIMESTAMP
    ");
    
    // Seed AI vision appearance description prompt (uses {HERIKA_NAME} placeholder)
    $visionAppearancePrompt = $db->escape(
        "Describe the character in the picture. Name is {HERIKA_NAME} .\n".
        "Do not focus on clothing, focus on physical appearance (face, eyes, hair, figure, waist,legs,breast size, tattoos if any....). Be concise. \n".
        "Start generation with this text:\n".
        "{HERIKA_NAME} is "
    );
    
    $db->execQuery("
        INSERT INTO public.prompts (prompt_key, default_prompt, description)
        VALUES (
            'ai_vision_appearance',
            '$visionAppearancePrompt',
            'AI vision prompt for describing character physical appearance from images (contains {HERIKA_NAME} placeholder). Used in: ui/cmd/action_ai_update_appearance.php'
        )
        ON CONFLICT (prompt_key) DO UPDATE SET
            default_prompt = EXCLUDED.default_prompt,
            description = EXCLUDED.description,
            updated_at = CURRENT_TIMESTAMP
    ");
    
    // Seed memory subsystem summary prompt (uses {PLAYER_NAME}, {COMPANIONS_LINE}, {SUMMARY_PROMPT} placeholders)
    $memorySubsystemPrompt = $db->escape(
        "{PLAYER_NAME} is the player.\n".
        "{COMPANIONS_LINE}\n".
        "You must write a memory summary from the narrator's point of view by analyzing the chat history. Focus only on roleplay elements: character behavior, feelings, relationships, decisions, dialogue, and locations relevant to the story. Ignore any references to game engine mechanics, menus, stats, or system messages.\n".
        "Pay close attention to details that could influence a character's behavior or emotions, as well as tag names and locations. Include quotes from character dialogue in the summary if they are relevant to understanding actions, motivations, or relationships\n\n".
        "Here are additional instructions: {SUMMARY_PROMPT}"
    );
    
    $db->execQuery("
        INSERT INTO public.prompts (prompt_key, default_prompt, description)
        VALUES (
            'memory_subsystem_summary',
            '$memorySubsystemPrompt',
            'Prompt for generating memory summaries from chat history (contains {PLAYER_NAME}, {COMPANIONS_LINE}, {SUMMARY_PROMPT} placeholders). Used in: debug/util_memory_subsystem.php'
        )
        ON CONFLICT (prompt_key) DO UPDATE SET
            default_prompt = EXCLUDED.default_prompt,
            description = EXCLUDED.description,
            updated_at = CURRENT_TIMESTAMP
    ");
    
    // Seed global dynamic prompts (migrated from conf schema)
    
    // SUMMARY_PROMPT - Memory summary instructions  
    $summaryPrompt = $db->escape("Focus on key events, tagging characters, locations, and factions accurately. Ensure memories align and maintain chronological order while foreshadowing future arcs. Prioritize player agency, and use environmental cues to enhance storytelling and continuity.");
    $db->execQuery("INSERT INTO public.prompts (prompt_key, default_prompt, description) VALUES ('summary_prompt', '$summaryPrompt', 'Additional instructions for memory summary generation. Used as {SUMMARY_PROMPT} placeholder in: debug/util_memory_subsystem.php') ON CONFLICT (prompt_key) DO UPDATE SET default_prompt = EXCLUDED.default_prompt, description = EXCLUDED.description, updated_at = CURRENT_TIMESTAMP");
    
    // DYNAMIC_PROMPT_PERSONALITY - Uses {HERIKA_NAME} placeholder
    $dynPersonality = $db->escape("Based on the dialogue history and recent events, update {HERIKA_NAME} personality traits. Maintain all existing relevant personality traits and add new ones based on recent experiences. Focus on behavioral changes, emotional growth/regression, new traits that emerged, and changes in confidence or outlook. Emphasize any past traumas or new traumas caused by the death of companions, allies, or other known characters, and how these events shape the character's behavior and mindset. Return ONLY the updated personality description in 3-5 sentences. Do not include any introductory text, meta-commentary, or phrases like 'Here is the updated personality' or 'The character's personality is'. Start directly with the personality content.");
    $db->execQuery("INSERT INTO public.prompts (prompt_key, default_prompt, description) VALUES ('dynamic_prompt_personality', '$dynPersonality', 'Instructions for updating NPC personality traits based on recent events (contains {HERIKA_NAME} placeholder). Used in: lib/dynamic_update_util.php, ui/cmd/action_dynamic_profile_*.php') ON CONFLICT (prompt_key) DO UPDATE SET default_prompt = EXCLUDED.default_prompt, description = EXCLUDED.description, updated_at = CURRENT_TIMESTAMP");
    
    // DYNAMIC_PROMPT_RELATIONSHIPS - Uses {HERIKA_NAME} placeholder
    $dynRelationships = $db->escape("Based on recent interactions, update {HERIKA_NAME} relationships with other people and factions. Maintain all existing relevant relationships and add new ones or modify existing ones based on recent interactions. Focus on changed relationships, new relationships formed, evolved existing ones, and only remove relationships that are clearly no longer relevant. Return ONLY a bulleted list using * Name/Faction - Description format. Do not include any introductory text, meta-commentary, or phrases like 'Here are the updated relationships' or 'The character's relationships include'. Start directly with the first bullet point.");
    $db->execQuery("INSERT INTO public.prompts (prompt_key, default_prompt, description) VALUES ('dynamic_prompt_relationships', '$dynRelationships', 'Instructions for updating NPC relationships based on interactions (contains {HERIKA_NAME} placeholder). Used in: lib/dynamic_update_util.php, ui/cmd/action_dynamic_profile_*.php') ON CONFLICT (prompt_key) DO UPDATE SET default_prompt = EXCLUDED.default_prompt, description = EXCLUDED.description, updated_at = CURRENT_TIMESTAMP");
    
    // DYNAMIC_PROMPT_OCCUPATION - Uses {HERIKA_NAME} placeholder
    $dynOccupation = $db->escape("Based on story progression and events, update {HERIKA_NAME} occupation and role. Maintain the current occupation unless significant changes have occurred. Add new responsibilities, changes in social status, and professional affiliations. Focus on job changes, new duties, and evolving professional relationships. Return ONLY the updated occupation description in 2-3 sentences. Do not include any introductory text, meta-commentary, or phrases like 'The character's occupation is' or 'Here is the updated occupation'. Start directly with the occupation content.");
    $db->execQuery("INSERT INTO public.prompts (prompt_key, default_prompt, description) VALUES ('dynamic_prompt_occupation', '$dynOccupation', 'Instructions for updating NPC occupation and role based on story progression (contains {HERIKA_NAME} placeholder). Used in: lib/dynamic_update_util.php, ui/cmd/action_dynamic_profile_*.php') ON CONFLICT (prompt_key) DO UPDATE SET default_prompt = EXCLUDED.default_prompt, description = EXCLUDED.description, updated_at = CURRENT_TIMESTAMP");
    
    // DYNAMIC_PROMPT_SKILLS - Uses {HERIKA_NAME} placeholder
    $dynSkills = $db->escape("Based on experiences and training, update {HERIKA_NAME} skills and abilities. Maintain all existing relevant skills and add new ones based on recent experiences. Focus on new skills learned, existing skills improved, any skills that deteriorated, and combat/magical knowledge gained. Return ONLY a bulleted list using * Skill - Description format. Do not include any introductory text, meta-commentary, or phrases like 'Here are the updated skills' or 'The character's skills include'. Start directly with the first bullet point.");
    $db->execQuery("INSERT INTO public.prompts (prompt_key, default_prompt, description) VALUES ('dynamic_prompt_skills', '$dynSkills', 'Instructions for updating NPC skills and abilities based on experiences (contains {HERIKA_NAME} placeholder). Used in: lib/dynamic_update_util.php, ui/cmd/action_dynamic_profile_*.php') ON CONFLICT (prompt_key) DO UPDATE SET default_prompt = EXCLUDED.default_prompt, description = EXCLUDED.description, updated_at = CURRENT_TIMESTAMP");
    
    // DYNAMIC_PROMPT_SPEECHSTYLE - Uses {HERIKA_NAME} placeholder
    $dynSpeech = $db->escape("Based on recent interactions, update how {HERIKA_NAME} speaks and communicates. Maintain existing consistent speech patterns and add new ones based on recent interactions. Focus on changes in vocabulary, new mannerisms, accent changes, and confidence level in speech. Return ONLY the updated speech style description in 2-3 sentences. Do not include any introductory text, meta-commentary, or phrases like 'The character speaks' or 'Here is the updated speech style'. Start directly with the speech style content.");
    $db->execQuery("INSERT INTO public.prompts (prompt_key, default_prompt, description) VALUES ('dynamic_prompt_speechstyle', '$dynSpeech', 'Instructions for updating NPC speech patterns and communication style (contains {HERIKA_NAME} placeholder). Used in: lib/dynamic_update_util.php, ui/cmd/action_dynamic_profile_*.php') ON CONFLICT (prompt_key) DO UPDATE SET default_prompt = EXCLUDED.default_prompt, description = EXCLUDED.description, updated_at = CURRENT_TIMESTAMP");
    
    // DYNAMIC_PROMPT_GOALS - Uses {HERIKA_NAME} placeholder
    $dynGoals = $db->escape("Based on story developments and achievements, update the {HERIKA_NAME} goals and aspirations. Maintain existing relevant goals, compressing related goals, and add new ones. Remove goals that have been clearly completed or are no longer applicable. Focus on new aspirations that emerged, modified existing goals due to circumstances, and updated long-term objectives. Return ONLY a bulleted list using * Goal description as actionable aspiration format. Do not include any introductory text, meta-commentary, or phrases like 'Here are the updated goals' or 'The character's goals are'. Start directly with the first bullet point (maintain a maximum of 20 goals with reduction priority when required: 1- compress related goals, 2-eliminate 'study' related goals, 3- eliminate older goals).");
    $db->execQuery("INSERT INTO public.prompts (prompt_key, default_prompt, description) VALUES ('dynamic_prompt_goals', '$dynGoals', 'Instructions for updating NPC goals and aspirations based on story developments (contains {HERIKA_NAME} placeholder). Used in: lib/dynamic_update_util.php, ui/cmd/action_dynamic_profile_*.php') ON CONFLICT (prompt_key) DO UPDATE SET default_prompt = EXCLUDED.default_prompt, description = EXCLUDED.description, updated_at = CURRENT_TIMESTAMP");
    
    // DIRECTOR_SYSTEM_PROMPT - Game director system prompt
    $directorSystem = $db->escape("You are a game director, and we are roleplaying Skyrim in the Tamriel universe. You must create a instruction for an actor to generate new content/events on game.");
    $db->execQuery("INSERT INTO public.prompts (prompt_key, default_prompt, description) VALUES ('director_system_prompt', '$directorSystem', 'Main system prompt defining the game director role. Used in: service/processors/rolemaster/cmd/instruction.php') ON CONFLICT (prompt_key) DO UPDATE SET default_prompt = EXCLUDED.default_prompt, description = EXCLUDED.description, updated_at = CURRENT_TIMESTAMP");
    
    // DIRECTOR_EXAMPLES_PROMPT - Examples of instruction formats
    $directorExamples = $db->escape("# Examples\n\nuser request: actor \"a\" leaves the place \n{\"instructions\":[{\n  \"character\": \"actor a\",\n  \"instruction\": \"actor a should say goodbye to everyone, hinting that they may not return for a long time\",\n  \"action\": \"ExitLocation\",\n  \"target\": \"everyone\",\n  \"scene_note\": \"The mood is somber as actor a prepares to leave. Actor b watches in silence, perhaps with regret or longing.\"\n},\n{\n  \"character\": \"actor b\",\n  \"instruction\": \"actor b should say goodbye to b\",\n  \"action\": \"JustTalk\",\n  \"target\": \"Actor a\",\n  \"scene_note\": \"Is a sad moment, generally speaking.\"\n}\n]\n}\n\n(no user request, randomly generated content)\n{\"instructions\":[\n {\n  \"character\": \"actor a\",\n  \"instruction\": \"actor a should ask actor b for a few coins, claiming they desperately need a drink.\",\n  \"action\": \"Talk\",\n  \"target\": \"actor b\",\n  \"scene_note\": \"actor a looks disheveled but charming, half-joking and half-serious. Actor b is unsure whether to laugh, help, or walk away. Other actors watch this two guys with curiosity\"\n }\n]\n}");
    $db->execQuery("INSERT INTO public.prompts (prompt_key, default_prompt, description) VALUES ('director_examples_prompt', '$directorExamples', 'Examples of instruction format for game director responses. Used in: service/processors/rolemaster/cmd/instruction.php') ON CONFLICT (prompt_key) DO UPDATE SET default_prompt = EXCLUDED.default_prompt, description = EXCLUDED.description, updated_at = CURRENT_TIMESTAMP");
    
    // DIRECTOR_INSTRUCTION_RULES - Rules for generating instructions (uses {PLAYER_NAME}, {FUNCTION_LIST} placeholders)
    $directorRules = $db->escape("Just provide instructions! You can also provide more than one instruction, but one per actor (keep limit at  2 or 3 max actors)\nIn addition, follow these general scene rules as a game director:\n * Use any actor in NEARBY ACTORS/NPC IN THE SCENE list ({PLAYER_NAME},busy actors and far away actors are EXCLUDED!)\n * Continue the scene as naturally and fully as possible, unless the user explicitly requests a new one. You can specify actions to reinforce the actors' dialogue.\n * If there are more actors in the room, try to involve them in the conversation.\n * When dialogue becomes repetitive, make a plot twist.\n * If a character reuses the same argument too often, nudge the scene towards a new topic.\n * Occasionally introduce subtle foreshadowing or hint at future events, dangers, or quests.\n * Do not resolve everything neatly—keep room for ongoing tension or future continuation.\n * You must always provide dialogue instructions for the character, as every request requires a dialogue response.\n * Here are a list of actions that can be used: \n{FUNCTION_LIST}\n  ** JustTalk \n * Add a Scene Note: A brief description of the topic, mood, or idea introduced by the instruction. Should serve to guide the desired instruction to become reality. Other actors can see this to properly react.\n * If scene is getting boring/repetitive, add a plot twist");
    $db->execQuery("INSERT INTO public.prompts (prompt_key, default_prompt, description) VALUES ('director_instruction_rules', '$directorRules', 'Rules and guidelines for game director when generating instructions (contains {PLAYER_NAME}, {FUNCTION_LIST} placeholders). Used in: service/processors/rolemaster/cmd/instruction.php') ON CONFLICT (prompt_key) DO UPDATE SET default_prompt = EXCLUDED.default_prompt, description = EXCLUDED.description, updated_at = CURRENT_TIMESTAMP");
    
    // Seed Oghma LLM topic extraction prompt
    $oghmaTopicPrompt = $db->escape(
        "You are an expert at extracting important topics from text.\n".
        "Follow these rules strictly:\n\n".
        "1. Extract only ONE most important topic (person, place, item, concept, etc.) from the text\n".
        "2. Ensure the output is in the **singular form** (e.g., dragons→dragon, cities→city)\n".
        "3. Return ONLY the word or phrase (no explanations, no extra text)\n".
        "4. If multiple candidates exist, choose the most important one\n".
        "5. Keep the topic in the same language as the input text\n\n".
        "Examples:\n".
        "Input: 'I heard about dragons'\n".
        "Output: dragon\n\n".
        "Input: 'Going to Whiterun today'\n".
        "Output: Whiterun\n\n".
        "Input: 'Met with the Greybeards'\n".
        "Output: Greybeard\n\n".
        "Input: 'Used magic in combat'\n".
        "Output: magic"
    );
    
    $db->execQuery("
        INSERT INTO public.prompts (prompt_key, default_prompt, description)
        VALUES (
            'custom_oghma',
            '$oghmaTopicPrompt',
            'System prompt for Oghma LLM-based topic extraction from dialogue/text (does not apply to MiniMe T5 version). Used in: lib/oghma_llm_service.php'
        )
        ON CONFLICT (prompt_key) DO UPDATE SET
            default_prompt = EXCLUDED.default_prompt,
            description = EXCLUDED.description,
            updated_at = CURRENT_TIMESTAMP
    ");
    
    $updateVersion("prompts", 20251110001);
    Logger::info("Applied patch prompts 20251110001 - Added all dynamic prompts and director prompts");
}

//----------------------------------------------------
// RANDOM NARRATION PROMPT
//----------------------------------------------------

if ($checkVersion("prompts")<20251116001) {
    Logger::debug("Applying prompts table 20251116001 - Adding random_narration_prompt");
    
    // Seed random narration prompt
    $randomNarrationPrompt = $db->escape(
        "Describe the current scene visually using ONLY details from the provided context. Focus on the characters present - their appearance, expressions, body language, and what they're wearing. Include environmental details like lighting and atmosphere. Keep it grounded and concise (2-3 sentences). Do not invent new information, advance the plot, or include dialogue."
    );
    
    $db->execQuery("
        INSERT INTO public.prompts (prompt_key, default_prompt, description)
        VALUES (
            'random_narration_prompt',
            '$randomNarrationPrompt',
            'Prompt for random Narrator interjections that add cinematic visual scene descriptions during conversations. Styled as atmospheric, present-tense narration (2-3 sentences). Used when RANDOM_NARATION is enabled in global settings.'
        )
        ON CONFLICT (prompt_key) DO UPDATE SET
            default_prompt = EXCLUDED.default_prompt,
            description = EXCLUDED.description,
            updated_at = CURRENT_TIMESTAMP
    ");
    
    $updateVersion("prompts", 20251116001);
    Logger::info("Applied patch prompts 20251116001 - Added random_narration_prompt");
}

//----------------------------------------------------
// HEIGHT DESCRIPTIONS PROMPT
//----------------------------------------------------

if ($checkVersion("prompts")<20251128002) {
    Logger::debug("Applying prompts table 20251128002 - Adding height_descriptions");
    
    // Seed height descriptions as JSON
    $heightDescriptions = $db->escape(json_encode([
        "height_descriptions" => [
            [
                "name" => "VerySmall",
                "min_scale" => 0.0,
                "max_scale" => 0.60,
                "description" => "Very small and tiny in stature"
            ],
            [
                "name" => "Small",
                "min_scale" => 0.60,
                "max_scale" => 0.80,
                "description" => "Smaller than most people"
            ],
            [
                "name" => "ModestStature",
                "min_scale" => 0.80,
                "max_scale" => 0.95,
                "description" => "Slightly below average height"
            ],
            [
                "name" => "Average",
                "min_scale" => 0.95,
                "max_scale" => 1.05,
                "description" => "Typical height"
            ],
            [
                "name" => "Tall",
                "min_scale" => 1.05,
                "max_scale" => 1.20,
                "description" => "Tall, standing a head above most people"
            ],
            [
                "name" => "VeryTall",
                "min_scale" => 1.20,
                "max_scale" => 1.40,
                "description" => "Very tall"
            ],
            [
                "name" => "Giantlike",
                "min_scale" => 1.40,
                "max_scale" => 99.0,
                "description" => "Giant in height and stature"
            ]
        ]
    ]));
    
    $db->execQuery("
        INSERT INTO public.prompts (prompt_key, default_prompt, description)
        VALUES (
            'height_descriptions',
            '$heightDescriptions',
            'JSON configuration for NPC height descriptions based on scale values. Used to generate natural language height descriptions from numeric scale values for NPC context.'
        )
        ON CONFLICT (prompt_key) DO UPDATE SET
            default_prompt = EXCLUDED.default_prompt,
            description = EXCLUDED.description,
            updated_at = CURRENT_TIMESTAMP
    ");
    
    $updateVersion("prompts", 20251128002);
    Logger::info("Applied patch prompts 20251128002 - Added height_descriptions");
}

//----------------------------------------------------
// Book Summary Prompt - Version 20251214001
//----------------------------------------------------

if ($checkVersion("prompts")<20251214001) {
    Logger::debug("Applying prompts table 20251214001 - Adding book_summary_prompt");
    
    // Seed book summary prompt (uses {HERIKA_NAME} and {TEMPLATE_DIALOG} placeholders)
    $bookSummaryPrompt = $db->escape(
        "({HERIKA_NAME} reads the book ) {TEMPLATE_DIALOG}"
    );
    
    $db->execQuery("
        INSERT INTO public.prompts (prompt_key, default_prompt, description)
        VALUES (
            'book_summary_prompt',
            '$bookSummaryPrompt',
            'Instruction prompt for book summary/reading events (contains {HERIKA_NAME} and {TEMPLATE_DIALOG} placeholders). Used in: processor/request.php for chatnf_book events'
        )
        ON CONFLICT (prompt_key) DO UPDATE SET
            default_prompt = EXCLUDED.default_prompt,
            description = EXCLUDED.description,
            updated_at = CURRENT_TIMESTAMP
    ");
    
    $updateVersion("prompts", 20251214001);
    Logger::info("Applied patch prompts 20251214001 - Added book_summary_prompt");
}

//----------------------------------------------------
// Add narrator_welcome_prompt to prompts table
// Version 20251224001
//----------------------------------------------------

if ($checkVersion("prompts")<20251224001) {
    Logger::debug("Applying prompts table 20251224001 - Adding narrator_welcome_prompt");
    
    $welcomePrompt = $db->escape(
        "Give a brief (2-3 sentence) recap of recent events and adventures. ".
        "Welcome the player back to their journey."
    );
    
    $db->execQuery("
        INSERT INTO public.prompts (prompt_key, default_prompt, description)
        VALUES (
            'narrator_welcome_prompt',
            '$welcomePrompt',
            'Prompt for narrator welcome message when loading a save game. Used in: main.php'
        )
        ON CONFLICT (prompt_key) DO UPDATE SET
            default_prompt = EXCLUDED.default_prompt,
            description = EXCLUDED.description,
            updated_at = CURRENT_TIMESTAMP
    ");
    
    $updateVersion("prompts", 20251224001);
    Logger::info("Applied patch prompts 20251224001 - Added narrator_welcome_prompt");
}

//----------------------------------------------------
// Add quest_comment_prompt to prompts table
// Version 20251224002
//----------------------------------------------------

if ($checkVersion("prompts")<20251224002) {
    Logger::debug("Applying prompts table 20251224002 - Adding quest_comment_prompt");
    
    $questPrompt = $db->escape(
        "{HERIKA_NAME}, what should we do about this new quest?"
    );
    
    $db->execQuery("
        INSERT INTO public.prompts (prompt_key, default_prompt, description)
        VALUES (
            'quest_comment_prompt',
            '$questPrompt',
            'Prompt for narrator/NPC comments on quest objective updates (contains {HERIKA_NAME} placeholder). Used in: prompts/prompts.php'
        )
        ON CONFLICT (prompt_key) DO UPDATE SET
            default_prompt = EXCLUDED.default_prompt,
            description = EXCLUDED.description,
            updated_at = CURRENT_TIMESTAMP
    ");
    
    $updateVersion("prompts", 20251224002);
    Logger::info("Applied patch prompts 20251224002 - Added quest_comment_prompt");
}

//----------------------------------------------------
// CORE_PLAYER DATA MIGRATION
//----------------------------------------------------

if ($checkVersion("core_player")<20241128001) {
    Logger::debug("Applying core_player migration 20241128001 - Migrating player data from conf_opts");
    
    // List of keys to migrate from conf_opts to core_player
    $keysToMigrate = [
        'PLAYER_NAME' => 'player_name',
        'PLAYER_BIOS' => 'appearance',  // Renamed
        'PLAYER_SPEECH_STYLE' => 'speech_style',
        // Skyrim stats
        'Mauls', 'Werewolf Transformations', 'Days As Werewolf',
        'Necks Bitten', 'Days As Vampire', 'Locations Discovered',
        'Dungeons Cleared', 'Days Passed', 'Hours Slept',
        'Hours Waited', 'Standing Stones Found', 'Gold Found',
        'Most Gold Carried', 'Chests Looted', 'Skill Increases',
        'Skill Books Read', 'Food Eaten', 'Training Sessions',
        'Books Read', 'Horses Owned', 'Houses Owned',
        'Stores Invested In', 'Barters', 'Persuasions',
        'Bribes', 'Intimidations', 'Diseases Contracted',
        'Dragonborn Quests Completed DB', 'Dawnguard Quests Completed DG',
        'Quests Completed', 'Misc Objectives Completed',
        'Main Quests Completed', 'Side Quests Completed',
        'The Companions Quests Completed', 'College of Winterhold Quests Completed',
        'Thieves\' Guild Quests Completed', 'The Dark Brotherhood Quests Completed',
        'Civil War Quests Completed', 'Daedric Quests Completed',
        'Questlines Completed', 'Bard\'s College Quests Completed',
        'Blades Quests Completed', 'Forsworn Quests Completed',
        'Imperial Legion Quests Completed', 'Stormcloaks Quests Completed',
        'Thieves\' Guild Special Jobs Completed', 'Dark Brotherhood Contracts Completed'
    ];
    
    foreach ($keysToMigrate as $confKey => $playerKey) {
        // If confKey is numeric (index in array), use it as both source and dest
        if (is_numeric($confKey)) {
            $confKey = $playerKey;
        }
        
        // Check if data exists in conf_opts
        $escapedConfKey = $db->escape($confKey);
        $result = $db->fetchAll("SELECT value FROM public.conf_opts WHERE id = '{$escapedConfKey}' LIMIT 1");
        
        if ($result && isset($result[0]['value'])) {
            $value = $result[0]['value'];
            $escapedPlayerKey = $db->escape($playerKey);
            $escapedValue = $db->escape($value);
            
            // Insert or update in core_player
            $db->execQuery("
                INSERT INTO public.core_player (id, value) 
                VALUES ('{$escapedPlayerKey}', '{$escapedValue}')
                ON CONFLICT (id) DO UPDATE SET value = EXCLUDED.value
            ");
            
            Logger::debug("Migrated {$confKey} -> core_player.{$playerKey}");
        }
    }
    
    $updateVersion("core_player", 20241128001);
    Logger::info("Applied patch core_player 20241128001 - Migrated player data from conf_opts");
}

//----------------------------------------------------
// CORE_NARRATOR DATA MIGRATION
//----------------------------------------------------

if ($checkVersion("core_narrator")<20250101001) {
    Logger::debug("Applying core_narrator migration 20250101001 - Migrating narrator settings from conf_opts");
    
    // Map conf_opts keys to core_narrator keys
    $keysToMigrate = [
        'NARRATOR_TALKS' => 'enabled',
        'NARRATOR_WELCOME' => 'welcome_enabled',
        'RANDOM_NARATION' => 'random_enabled',
        'RANDOM_NARATION_CHANCE' => 'random_chance',
        'RANDOM_NARRATION_COOLDOWN' => 'random_cooldown',
        'BOOK_EVENT_ALWAYS_NARRATOR' => 'books_only_narrator',
        'HIDE_NARRATOR_DIALOGUE' => 'hide_from_context',
    ];
    
    foreach ($keysToMigrate as $confKey => $narratorKey) {
        // Check if data exists in conf_opts
        $escapedConfKey = $db->escape($confKey);
        $result = $db->fetchAll("SELECT value FROM public.conf_opts WHERE id = '{$escapedConfKey}' LIMIT 1");
        
        if ($result && isset($result[0]['value'])) {
            $value = $result[0]['value'];
            $escapedNarratorKey = $db->escape($narratorKey);
            $escapedValue = $db->escape($value);
            
            // Insert or update in core_narrator
            $db->execQuery("
                INSERT INTO public.core_narrator (id, value) 
                VALUES ('{$escapedNarratorKey}', '{$escapedValue}')
                ON CONFLICT (id) DO UPDATE SET value = EXCLUDED.value
            ");
            
            Logger::debug("Migrated {$confKey} -> core_narrator.{$narratorKey}");
        }
    }
    
    // Seed defaults if no values exist (only if table is empty)
    $countResult = $db->fetchAll("SELECT COUNT(*) AS c FROM public.core_narrator");
    $count = $countResult && isset($countResult[0]['c']) ? intval($countResult[0]['c']) : 0;
    
    if ($count === 0) {
        // Seed with defaults from conf.php if available, otherwise use hardcoded defaults
        $defaults = [
            'enabled' => isset($GLOBALS["NARRATOR_TALKS"]) ? ($GLOBALS["NARRATOR_TALKS"] ? '1' : '0') : '1',
            'welcome_enabled' => isset($GLOBALS["NARRATOR_WELCOME"]) ? ($GLOBALS["NARRATOR_WELCOME"] ? '1' : '0') : '0',
            'random_enabled' => isset($GLOBALS["RANDOM_NARATION"]) ? ($GLOBALS["RANDOM_NARATION"] ? '1' : '0') : '0',
            'random_chance' => isset($GLOBALS["RANDOM_NARATION_CHANCE"]) ? (string)intval($GLOBALS["RANDOM_NARATION_CHANCE"]) : '15',
            'random_cooldown' => isset($GLOBALS["RANDOM_NARRATION_COOLDOWN"]) ? (string)intval($GLOBALS["RANDOM_NARRATION_COOLDOWN"]) : '2',
            'books_only_narrator' => isset($GLOBALS["BOOK_EVENT_ALWAYS_NARRATOR"]) ? ($GLOBALS["BOOK_EVENT_ALWAYS_NARRATOR"] ? '1' : '0') : '0',
            'hide_from_context' => isset($GLOBALS["HIDE_NARRATOR_DIALOGUE"]) ? ($GLOBALS["HIDE_NARRATOR_DIALOGUE"] ? '1' : '0') : '0',
        ];
        
        foreach ($defaults as $key => $value) {
            $escapedKey = $db->escape($key);
            $escapedValue = $db->escape($value);
            $db->execQuery("
                INSERT INTO public.core_narrator (id, value) 
                VALUES ('{$escapedKey}', '{$escapedValue}')
                ON CONFLICT (id) DO UPDATE SET value = EXCLUDED.value
            ");
        }
        
        Logger::debug("Seeded core_narrator with default values");
    }
    
    $updateVersion("core_narrator", 20250101001);
    Logger::info("Applied patch core_narrator 20250101001 - Migrated narrator settings from conf_opts");
}

//----------------------------------------------------
// CORE_NARRATOR CHARACTER DATA MIGRATION FROM CORE_NPC_MASTER
//----------------------------------------------------

if ($checkVersion("core_narrator")<20250101002) {
    Logger::debug("Applying core_narrator migration 20250101002 - Migrating narrator character data from core_npc_master");
    
    // Check if The Narrator exists in core_npc_master
    $narratorNpc = $db->fetchOne("SELECT * FROM public.core_npc_master WHERE npc_name = 'The Narrator' LIMIT 1");
    
    if ($narratorNpc) {
        // Copy character data to core_narrator
        $migrationData = [];
        
        if (isset($narratorNpc['profile_id']) && $narratorNpc['profile_id'] !== null) {
            $migrationData['profile_id'] = (string)intval($narratorNpc['profile_id']);
        }
        
        if (isset($narratorNpc['voiceid']) && $narratorNpc['voiceid'] !== null && $narratorNpc['voiceid'] !== '') {
            $migrationData['voiceid'] = $narratorNpc['voiceid'];
        }
        
        if (isset($narratorNpc['core']) && $narratorNpc['core'] !== null && $narratorNpc['core'] !== '') {
            $migrationData['core'] = $narratorNpc['core'];
        }
        
        if (isset($narratorNpc['npc_static_bio']) && $narratorNpc['npc_static_bio'] !== null && $narratorNpc['npc_static_bio'] !== '') {
            $migrationData['background'] = $narratorNpc['npc_static_bio'];
        }
        
        if (isset($narratorNpc['personality']) && $narratorNpc['personality'] !== null && $narratorNpc['personality'] !== '') {
            $migrationData['personality'] = $narratorNpc['personality'];
        }
        
        if (isset($narratorNpc['speechstyle']) && $narratorNpc['speechstyle'] !== null && $narratorNpc['speechstyle'] !== '') {
            $migrationData['speechstyle'] = $narratorNpc['speechstyle'];
        }
        
        if (isset($narratorNpc['goals']) && $narratorNpc['goals'] !== null && $narratorNpc['goals'] !== '') {
            $migrationData['goals'] = $narratorNpc['goals'];
        }
        
        if (isset($narratorNpc['oghma_knowledge_tags']) && $narratorNpc['oghma_knowledge_tags'] !== null && $narratorNpc['oghma_knowledge_tags'] !== '') {
            $migrationData['oghma_knowledge'] = $narratorNpc['oghma_knowledge_tags'];
        }
        
        if (isset($narratorNpc['gender']) && $narratorNpc['gender'] !== null && $narratorNpc['gender'] !== '') {
            $migrationData['gender'] = $narratorNpc['gender'];
        }
        
        if (isset($narratorNpc['prompt_head']) && $narratorNpc['prompt_head'] !== null && $narratorNpc['prompt_head'] !== '') {
            $migrationData['prompt_head'] = $narratorNpc['prompt_head'];
        }
        
        // Insert/update in core_narrator (only if not already set)
        foreach ($migrationData as $key => $value) {
            $existing = $db->fetchOne("SELECT value FROM public.core_narrator WHERE id = '{$db->escape($key)}' LIMIT 1");
            if (!$existing || !isset($existing['value']) || $existing['value'] === '') {
                $escapedKey = $db->escape($key);
                $escapedValue = $db->escape($value);
                $db->execQuery("
                    INSERT INTO public.core_narrator (id, value) 
                    VALUES ('{$escapedKey}', '{$escapedValue}')
                    ON CONFLICT (id) DO UPDATE SET value = EXCLUDED.value
                ");
                Logger::debug("Migrated narrator.{$key} from core_npc_master");
            }
        }
        
    // Delete The Narrator from core_npc_master
    $db->execQuery("DELETE FROM public.core_npc_master WHERE npc_name = 'The Narrator'");
    Logger::info("Deleted The Narrator from core_npc_master");
    } else {
        // No narrator in core_npc_master - check if we need to seed defaults in core_narrator
        $narratorCount = $db->fetchAll("SELECT COUNT(*) AS c FROM public.core_narrator");
        $count = $narratorCount && isset($narratorCount[0]['c']) ? intval($narratorCount[0]['c']) : 0;
        
        if ($count === 0) {
            // Fresh install - seed narrator character data with defaults
            $defaultProfile = $db->fetchOne("SELECT id FROM public.core_profiles WHERE default_narrator = '1' LIMIT 1");
            $profileId = $defaultProfile && isset($defaultProfile['id']) ? (string)intval($defaultProfile['id']) : '1';
            
            $defaults = [
                'profile_id' => $profileId,
                'voiceid' => 'TheNarrator',
                'core' => "The Narrator is a male voice within the player's mind. His job is to help the player as they navigate the world of Tamriel. Provide unique insight and descriptions of what is going on in the world.",
                'background' => "A guiding voice that describes the world, events, and transitions. He is not a character, but a voice within the player's mind.",
                'personality' => 'Detached, descriptive, witty, helpful.',
                'speechstyle' => '',
                'goals' => '',
                'oghma_knowledge' => 'knowall',
                'gender' => 'male',
            ];
            
            foreach ($defaults as $key => $value) {
                $escapedKey = $db->escape($key);
                $escapedValue = $db->escape($value);
                $db->execQuery("
                    INSERT INTO public.core_narrator (id, value) 
                    VALUES ('{$escapedKey}', '{$escapedValue}')
                    ON CONFLICT (id) DO UPDATE SET value = EXCLUDED.value
                ");
            }
            
            Logger::info("Seeded narrator character data with defaults for fresh install");
        }
    }
    
    $updateVersion("core_narrator", 20250101002);
    Logger::info("Applied patch core_narrator 20250101002 - Migrated narrator character data from core_npc_master and removed from NPC list");
}

//----------------------------------------------------
// Background Life Prompts - Style prompts for letters and inner thoughts
// Version 20251207001
//----------------------------------------------------

if ($checkVersion("prompts")<20251207001) {
    Logger::debug("Applying background life prompts 20251207001");
    
    // Prompt 1: Letter writing style
    $bglLetterStyle = $db->escape(
        "Write it as a letter to {PLAYER_NAME} from {HERIKA_NAME}. Use same language as <text>. IMPORTANT: Keep the letter SHORT and CONCISE - maximum 2-3 brief paragraphs."
    );
    
    $db->execQuery("
        INSERT INTO public.prompts (prompt_key, default_prompt, description)
        VALUES (
            'background_life_letter',
            '$bglLetterStyle',
            'Writing style instructions for background life letters/notifications. This is embedded into the notification field instructions. Contains placeholders: {HERIKA_NAME}, {PLAYER_NAME}. Used in: debug/simple_llm_request_with_context_life.php'
        )
        ON CONFLICT (prompt_key) DO UPDATE SET
            default_prompt = EXCLUDED.default_prompt,
            description = EXCLUDED.description,
            updated_at = CURRENT_TIMESTAMP
    ");
    
    // Prompt 2: Inner thought/monologue style
    $bglInnerThoughtStyle = $db->escape(
        "Read the following text, which represents a mental note or inner monologue of a character within the Skyrim universe.\n".
        "Based on the content of the text, propose one of the following actions that would make sense for the development of the story:"
    );
    
    $db->execQuery("
        INSERT INTO public.prompts (prompt_key, default_prompt, description)
        VALUES (
            'background_life_innerthought',
            '$bglInnerThoughtStyle',
            'Introduction/framing style for processing background life inner thoughts and monologues. This appears at the start of the system prompt. Contains no placeholders. Used in: debug/simple_llm_request_with_context_life.php'
        )
        ON CONFLICT (prompt_key) DO UPDATE SET
            default_prompt = EXCLUDED.default_prompt,
            description = EXCLUDED.description,
            updated_at = CURRENT_TIMESTAMP
    ");
    
    //$db->execQuery("UPDATE versions SET version=20251207001 WHERE section='prompts'"); // ???
    $updateVersion("prompts",20251207001);
    
    Logger::info("Applied patch prompts 20251207001 - Added background life style prompts to database");
}


//----------------------------------------------------
// emotions expression
//----------------------------------------------------

if ($checkVersion("emotions_expression")<20251130003) {
    Logger::debug(" try patch: emotions_expression 20251130003");
    $b_ok = true;
    try {
        $query = " ALTER TABLE public.speech ADD COLUMN IF NOT EXISTS mood TEXT; ";
        $db->execQuery($query);        
    } catch (Exception $e) {
        $b_ok = false;
        Logger::error("Error altering 'speech' table: " . $e->getMessage());
    }
    try {
        $query = " ALTER TABLE public.speech ADD COLUMN IF NOT EXISTS emotion TEXT; ";
        $db->execQuery($query);        
    } catch (Exception $e) {
        $b_ok = false;
        Logger::error("Error altering 'speech' table: " . $e->getMessage());
    }
    try {
        $query = " ALTER TABLE public.speech ADD COLUMN IF NOT EXISTS emotion_intensity TEXT; ";
        $db->execQuery($query);        
    } catch (Exception $e) {
        $b_ok = false;
        Logger::error("Error altering 'speech' table: " . $e->getMessage());
    }

    if ($b_ok) {
        $updateVersion("emotions_expression",20251130003);
        Logger::info("Applied patch emotions_expression 20251130003");
    }
}

if ($checkVersion("emotions_expression")<20251230001) {
    Logger::debug(" try patch: emotions_expression 20251230001");
    $b_ok = true;
    try {
        $query = " ALTER TABLE public.moods_issued ADD COLUMN IF NOT EXISTS emotion TEXT; ";
        $db->execQuery($query);        
    } catch (Exception $e) {
        $b_ok = false;
        Logger::error("Error altering 'moods_issued' table: " . $e->getMessage());
    }
    try {
        $query = " ALTER TABLE public.moods_issued ADD COLUMN IF NOT EXISTS emotion_intensity TEXT; ";
        $db->execQuery($query);        
    } catch (Exception $e) {
        $b_ok = false;
        Logger::error("Error altering 'moods_issued' table: " . $e->getMessage());
    }

    if ($b_ok) {
        $updateVersion("emotions_expression",20251230001);
        Logger::info("Applied patch emotions_expression 20251230001");
    }
}

//----------------------------------------------------

// Relationship Evaluation and Initialization Queues
$db->execQuery("CREATE TABLE IF NOT EXISTS relationship_eval_queue (
                id SERIAL PRIMARY KEY,
                npc_id INTEGER NOT NULL UNIQUE,
                eval_data JSONB NOT NULL,
                created_at TIMESTAMP DEFAULT NOW()  )");

$db->execQuery("CREATE TABLE IF NOT EXISTS relationship_init_queue (
                id SERIAL PRIMARY KEY,
                npc_id INTEGER NOT NULL UNIQUE,
                init_data JSONB NOT NULL,
                created_at TIMESTAMP DEFAULT NOW()  )");

$db->execQuery("
            ALTER TABLE relationship_init_queue
            ADD COLUMN IF NOT EXISTS retry_count INTEGER DEFAULT 0
        ");
$db->execQuery("
            ALTER TABLE relationship_init_queue
            ADD COLUMN IF NOT EXISTS last_error TEXT
        ");

//----------------------------------------------------
        
Logger::info(__FILE__." update file processed. This file has ".__LINE__." lines.");
?>
