<?php 


// Add people info to event log

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

?>