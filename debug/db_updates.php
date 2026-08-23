<?php 

require_once(dirname(__DIR__).DIRECTORY_SEPARATOR."lib/logger.php");
require_once(dirname(__DIR__).DIRECTORY_SEPARATOR."lib/settings.php");
require_once(dirname(__DIR__).DIRECTORY_SEPARATOR."lib/oghma_aliases.php");

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
$db->execQuery('CREATE SCHEMA IF NOT EXISTS plugins');
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
    if ($checkTableExists("general_settings") == -1) {
        $db->execQuery(file_get_contents(__DIR__."/../lib/core/database_schema/general_settings.sql"));
        $db->execQuery("SET search_path TO public");
    }
    if ($checkTableExists("core_api_badge") == -1) {
        $db->execQuery(file_get_contents(__DIR__."/../lib/core/database_schema/core_api_badge.sql"));
        $db->execQuery("SET search_path TO public");
    }
    if ($checkTableExists("core_stt_connector") == -1) {
        $db->execQuery(file_get_contents(__DIR__."/../lib/core/database_schema/core_stt_connector.sql"));
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
    if ($checkTableExists("core_tts_fallback") == -1) {
        $db->execQuery(file_get_contents(__DIR__."/../lib/core/database_schema/core_tts_fallback.sql"));
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
    if ($checkTableExists("core_action") == -1) {
        $db->execQuery(file_get_contents(__DIR__."/../lib/core/database_schema/core_action.sql"));
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

if ($checkVersion("core_action") < 20260426001) {
    Logger::debug("Applying core_action 20260426001 - add parameters/metadata/game function fields");

    $db->execQuery("ALTER TABLE public.core_action ADD COLUMN IF NOT EXISTS parameters_json JSONB NOT NULL DEFAULT '{}'::jsonb");
    $db->execQuery("ALTER TABLE public.core_action ADD COLUMN IF NOT EXISTS metadata JSONB NOT NULL DEFAULT '{}'::jsonb");
    $db->execQuery("ALTER TABLE public.core_action ADD COLUMN IF NOT EXISTS game_function BOOLEAN NOT NULL DEFAULT TRUE");
    $db->execQuery("ALTER TABLE public.core_action ADD COLUMN IF NOT EXISTS script_proxy_program JSONB");

    $db->execQuery("ALTER TABLE public.core_action_custom ADD COLUMN IF NOT EXISTS parameters_json JSONB NOT NULL DEFAULT '{}'::jsonb");
    $db->execQuery("ALTER TABLE public.core_action_custom ADD COLUMN IF NOT EXISTS metadata JSONB NOT NULL DEFAULT '{}'::jsonb");
    $db->execQuery("ALTER TABLE public.core_action_custom ADD COLUMN IF NOT EXISTS game_function BOOLEAN NOT NULL DEFAULT TRUE");
    $db->execQuery("ALTER TABLE public.core_action_custom ADD COLUMN IF NOT EXISTS script_proxy_program JSONB");

    $db->execQuery("CREATE INDEX IF NOT EXISTS idx_core_action_game_function ON public.core_action (game_function)");
    $db->execQuery("CREATE INDEX IF NOT EXISTS idx_core_action_custom_game_function ON public.core_action_custom (game_function)");

    $db->execQuery("DROP VIEW IF EXISTS public.combined_core_action");
    $db->execQuery("
        CREATE VIEW public.combined_core_action AS
        SELECT
            c.id,
            c.code_name,
            c.action_name,
            c.description,
            c.return_message,
            c.available_to_npc,
            c.available_to_followers,
            c.is_activated,
            c.parameters_json,
            c.metadata,
            c.game_function,
            c.script_proxy_program,
            c.created_at,
            c.updated_at
        FROM public.core_action_custom c
        UNION ALL
        SELECT
            b.id,
            b.code_name,
            b.action_name,
            b.description,
            b.return_message,
            b.available_to_npc,
            b.available_to_followers,
            b.is_activated,
            b.parameters_json,
            b.metadata,
            b.game_function,
            b.script_proxy_program,
            b.created_at,
            b.updated_at
        FROM public.core_action b
        LEFT JOIN public.core_action_custom c ON LOWER(b.code_name) = LOWER(c.code_name)
        WHERE c.code_name IS NULL
    ");

    $db->execQuery("
        DELETE FROM public.core_action_custom
        WHERE code_name IN ('AttackHunt', 'GetDateTime', 'SearchDiary', 'SetCurrentTask', 'ReadDiaryPage', 'SearchMemory')
    ");
    $db->execQuery("
        DELETE FROM public.core_action
        WHERE code_name IN ('AttackHunt', 'GetDateTime', 'SearchDiary', 'SetCurrentTask', 'ReadDiaryPage', 'SearchMemory')
    ");

    $updateVersion("core_action", 20260426001);
    Logger::info("Applied patch core_action 20260426001");
}

if ($checkVersion("core_action") < 20260427001) {
    Logger::debug("Applying core_action 20260427001 - add import_version field");

    $db->execQuery("ALTER TABLE public.core_action ADD COLUMN IF NOT EXISTS import_version BIGINT NOT NULL DEFAULT 0");
    $db->execQuery("ALTER TABLE public.core_action_custom ADD COLUMN IF NOT EXISTS import_version BIGINT NOT NULL DEFAULT 0");

    $db->execQuery("UPDATE public.core_action SET import_version = 0 WHERE import_version IS NULL");
    $db->execQuery("UPDATE public.core_action_custom SET import_version = 0 WHERE import_version IS NULL");

    $db->execQuery("DROP VIEW IF EXISTS public.combined_core_action");
    $db->execQuery("
        CREATE VIEW public.combined_core_action AS
        SELECT
            c.id,
            c.code_name,
            c.action_name,
            c.description,
            c.return_message,
            c.available_to_npc,
            c.available_to_followers,
            c.is_activated,
            c.parameters_json,
            c.metadata,
            c.game_function,
            c.import_version,
            c.script_proxy_program,
            c.created_at,
            c.updated_at
        FROM public.core_action_custom c
        UNION ALL
        SELECT
            b.id,
            b.code_name,
            b.action_name,
            b.description,
            b.return_message,
            b.available_to_npc,
            b.available_to_followers,
            b.is_activated,
            b.parameters_json,
            b.metadata,
            b.game_function,
            b.import_version,
            b.script_proxy_program,
            b.created_at,
            b.updated_at
        FROM public.core_action b
        LEFT JOIN public.core_action_custom c ON LOWER(b.code_name) = LOWER(c.code_name)
        WHERE c.code_name IS NULL
    ");

    $updateVersion("core_action", 20260427001);
    Logger::info("Applied patch core_action 20260427001");
}

if ($checkVersion("core_action") < 20260428001) {
    Logger::debug("Applying core_action 20260428001 - seed baseline actions from repo snapshot when empty");

    $row = $db->fetchOne("SELECT COUNT(*) AS total FROM public.core_action");
    $baseRowCount = intval($row['total'] ?? 0);
    if ($baseRowCount === 0) {
        $seedFile = __DIR__ . "/../data/core_action_seed.sql";
        if (file_exists($seedFile) && trim(strval(file_get_contents($seedFile))) !== '') {
            $db->execQuery(file_get_contents($seedFile));
            Logger::info("Seeded public.core_action from core_action_seed.sql");
        } else {
            Logger::warn("core_action seed file missing or empty; leaving public.core_action unseeded");
        }
    }

    $updateVersion("core_action", 20260428001);
    Logger::info("Applied patch core_action 20260428001");
}

if ($checkVersion("core_action") < 20260429001) {
    Logger::debug("Applying core_action 20260429001 - purge deprecated CHIM-Campfire imported actions");

    $db->execQuery("
        DELETE FROM public.core_action_custom
        WHERE LOWER(code_name) LIKE 'extcmdchimcampfire_%'
           OR COALESCE(LOWER(metadata->>'source'), '') = 'chim-campfire'
           OR COALESCE(LOWER(metadata->>'integration'), '') = 'campfire'
           OR COALESCE(LOWER(metadata->>'bridge_script'), '') = 'chimcampfire'
           OR COALESCE(LOWER(metadata->>'import_filename'), '') = 'campfire_actions.csv'
    ");

    $updateVersion("core_action", 20260429001);
    Logger::info("Applied patch core_action 20260429001");
}

if ($checkVersion("core_action") < 20260429002) {
    Logger::debug("Applying core_action 20260429002 - add available_to_narrator field");

    $db->execQuery("ALTER TABLE public.core_action ADD COLUMN IF NOT EXISTS available_to_narrator BOOLEAN NOT NULL DEFAULT FALSE");
    $db->execQuery("ALTER TABLE public.core_action_custom ADD COLUMN IF NOT EXISTS available_to_narrator BOOLEAN NOT NULL DEFAULT FALSE");

    $db->execQuery("UPDATE public.core_action SET available_to_narrator = FALSE WHERE available_to_narrator IS NULL");
    $db->execQuery("UPDATE public.core_action_custom SET available_to_narrator = FALSE WHERE available_to_narrator IS NULL");

    $db->execQuery("CREATE INDEX IF NOT EXISTS idx_core_action_available_to_narrator ON public.core_action (available_to_narrator)");
    $db->execQuery("CREATE INDEX IF NOT EXISTS idx_core_action_custom_available_to_narrator ON public.core_action_custom (available_to_narrator)");

    $db->execQuery("DROP VIEW IF EXISTS public.combined_core_action");
    $db->execQuery("
        CREATE VIEW public.combined_core_action AS
        SELECT
            c.id,
            c.code_name,
            c.action_name,
            c.description,
            c.return_message,
            c.available_to_npc,
            c.available_to_followers,
            c.available_to_narrator,
            c.is_activated,
            c.parameters_json,
            c.metadata,
            c.game_function,
            c.import_version,
            c.script_proxy_program,
            c.created_at,
            c.updated_at
        FROM public.core_action_custom c
        UNION ALL
        SELECT
            b.id,
            b.code_name,
            b.action_name,
            b.description,
            b.return_message,
            b.available_to_npc,
            b.available_to_followers,
            b.available_to_narrator,
            b.is_activated,
            b.parameters_json,
            b.metadata,
            b.game_function,
            b.import_version,
            b.script_proxy_program,
            b.created_at,
            b.updated_at
        FROM public.core_action b
        LEFT JOIN public.core_action_custom c ON LOWER(b.code_name) = LOWER(c.code_name)
        WHERE c.code_name IS NULL
    ");

    $updateVersion("core_action", 20260429002);
    Logger::info("Applied patch core_action 20260429002");
}

if ($checkVersion("core_action") < 20260429003) {
    Logger::debug("Applying core_action 20260429003 - add narrator TeleportNPC baseline action");

    $db->execQuery("
        INSERT INTO public.core_action (
            code_name,
            action_name,
            description,
            return_message,
            available_to_npc,
            available_to_followers,
            available_to_narrator,
            is_activated,
            parameters_json,
            metadata,
            game_function,
            import_version,
            script_proxy_program
        ) VALUES (
            'TeleportNPC',
            'Teleport_NPC',
            'Narrator-only action. Teleports a chosen NPC, actor, or #PLAYER_NAME# to a named location from the location database. Put who to teleport in the target field and the destination in the item field.',
            '#TARGET# teleports to #ITEM#.',
            FALSE,
            FALSE,
            TRUE,
            TRUE,
            '{\"type\":\"object\",\"properties\":{\"target\":{\"type\":\"string\",\"description\":\"Actor to teleport. Use #PLAYER_NAME#, PLAYER, or me to teleport the player.\"},\"item\":{\"type\":\"string\",\"description\":\"REQUIRED: destination location name from the location database.\"}},\"required\":[\"item\"]}'::jsonb,
            '{\"dispatch\":\"rolecommand\",\"builtin\":true,\"status\":\"active\",\"source\":\"functions.php\"}'::jsonb,
            TRUE,
            0,
            NULL
        )
        ON CONFLICT (code_name) DO UPDATE SET
            action_name = EXCLUDED.action_name,
            description = EXCLUDED.description,
            return_message = EXCLUDED.return_message,
            available_to_npc = EXCLUDED.available_to_npc,
            available_to_followers = EXCLUDED.available_to_followers,
            available_to_narrator = EXCLUDED.available_to_narrator,
            is_activated = EXCLUDED.is_activated,
            parameters_json = EXCLUDED.parameters_json,
            metadata = EXCLUDED.metadata,
            game_function = EXCLUDED.game_function,
            import_version = EXCLUDED.import_version,
            script_proxy_program = EXCLUDED.script_proxy_program,
            updated_at = NOW()
    ");

    $updateVersion("core_action", 20260429003);
    Logger::info("Applied patch core_action 20260429003");
}

if ($checkVersion("core_action") < 20260429004) {
    Logger::debug("Applying core_action 20260429004 - add narrator SpawnItem baseline action");

    $db->execQuery("
        INSERT INTO public.core_action (
            code_name,
            action_name,
            description,
            return_message,
            available_to_npc,
            available_to_followers,
            available_to_narrator,
            is_activated,
            parameters_json,
            metadata,
            game_function,
            import_version,
            script_proxy_program
        ) VALUES (
            'SpawnItem',
            'Spawn_Item',
            'Narrator-only action. Spawns a named item from the descriptions database and gives it to a target actor or #PLAYER_NAME#. Put the recipient in the target field, the item name in the item field, and the quantity in the amount field.',
            '#TARGET# receives #ITEM#.',
            FALSE,
            FALSE,
            TRUE,
            TRUE,
            '{\"type\":\"object\",\"properties\":{\"target\":{\"type\":\"string\",\"description\":\"Recipient actor. Use #PLAYER_NAME#, PLAYER, or me to give the item to the player.\"},\"item\":{\"type\":\"string\",\"description\":\"REQUIRED: item name from the descriptions database.\"},\"amount\":{\"type\":\"integer\",\"description\":\"Quantity to spawn and give (default: 1).\"}},\"required\":[\"item\"]}'::jsonb,
            '{\"dispatch\":\"rolecommand\",\"builtin\":true,\"status\":\"active\",\"source\":\"functions.php\"}'::jsonb,
            TRUE,
            0,
            NULL
        )
        ON CONFLICT (code_name) DO UPDATE SET
            action_name = EXCLUDED.action_name,
            description = EXCLUDED.description,
            return_message = EXCLUDED.return_message,
            available_to_npc = EXCLUDED.available_to_npc,
            available_to_followers = EXCLUDED.available_to_followers,
            available_to_narrator = EXCLUDED.available_to_narrator,
            is_activated = EXCLUDED.is_activated,
            parameters_json = EXCLUDED.parameters_json,
            metadata = EXCLUDED.metadata,
            game_function = EXCLUDED.game_function,
            import_version = EXCLUDED.import_version,
            script_proxy_program = EXCLUDED.script_proxy_program,
            updated_at = NOW()
    ");

    $updateVersion("core_action", 20260429004);
    Logger::info("Applied patch core_action 20260429004");
}

if ($checkVersion("core_action") < 20260429005) {
    Logger::debug("Applying core_action 20260429005 - normalize narrator action descriptions");

    $db->execQuery("
        UPDATE public.core_action
        SET
            description = CASE
                WHEN code_name = 'SpawnItem' THEN 'Create a named item from the descriptions database and give it to a target actor or #PLAYER_NAME#.'
                WHEN code_name = 'TeleportNPC' THEN 'Teleport a chosen NPC, actor, or #PLAYER_NAME# to a named location from the location database.'
                ELSE description
            END,
            parameters_json = CASE
                WHEN code_name = 'SpawnItem' THEN '{\"type\":\"object\",\"properties\":{\"target\":{\"type\":\"string\",\"description\":\"Recipient actor. Use #PLAYER_NAME#, PLAYER, or me to give the item to the player.\"},\"item\":{\"type\":\"string\",\"description\":\"REQUIRED: item name from the descriptions database.\"},\"amount\":{\"type\":\"integer\",\"description\":\"Quantity to spawn and give (default: 1).\"}},\"required\":[\"item\"]}'::jsonb
                WHEN code_name = 'TeleportNPC' THEN '{\"type\":\"object\",\"properties\":{\"target\":{\"type\":\"string\",\"description\":\"Actor to teleport. Use #PLAYER_NAME#, PLAYER, or me to teleport the player.\"},\"item\":{\"type\":\"string\",\"description\":\"REQUIRED: destination location name from the location database.\"}},\"required\":[\"item\"]}'::jsonb
                ELSE parameters_json
            END
        WHERE code_name IN ('SpawnItem', 'TeleportNPC')
    ");

    $updateVersion("core_action", 20260429005);
    Logger::info("Applied patch core_action 20260429005");
}

if ($checkVersion("core_action") < 20260429006) {
    Logger::debug("Applying core_action 20260429006 - add narrator KillTarget baseline action");

    $db->execQuery("
        INSERT INTO public.core_action (
            code_name,
            action_name,
            description,
            return_message,
            available_to_npc,
            available_to_followers,
            available_to_narrator,
            is_activated,
            parameters_json,
            metadata,
            game_function,
            import_version,
            script_proxy_program
        ) VALUES (
            'KillTarget',
            'Kill_Target',
            'Kill a chosen NPC, actor, or #PLAYER_NAME# immediately.',
            '#TARGET# is killed.',
            FALSE,
            FALSE,
            TRUE,
            TRUE,
            '{\"type\":\"object\",\"required\":[\"target\"],\"properties\":{\"target\":{\"type\":\"string\",\"description\":\"REQUIRED: actor to kill. Use #PLAYER_NAME#, PLAYER, or me to kill the player.\"}}}'::jsonb,
            '{\"source\":\"functions.php\",\"status\":\"active\",\"builtin\":true,\"dispatch\":\"rolecommand\"}'::jsonb,
            TRUE,
            0,
            NULL
        )
        ON CONFLICT (code_name) DO UPDATE SET
            action_name = EXCLUDED.action_name,
            description = EXCLUDED.description,
            return_message = EXCLUDED.return_message,
            available_to_npc = EXCLUDED.available_to_npc,
            available_to_followers = EXCLUDED.available_to_followers,
            available_to_narrator = EXCLUDED.available_to_narrator,
            is_activated = EXCLUDED.is_activated,
            parameters_json = EXCLUDED.parameters_json,
            metadata = EXCLUDED.metadata,
            game_function = EXCLUDED.game_function,
            import_version = EXCLUDED.import_version,
            script_proxy_program = EXCLUDED.script_proxy_program,
            updated_at = NOW()
    ");

    $updateVersion("core_action", 20260429006);
    Logger::info("Applied patch core_action 20260429006");
}

if ($checkVersion("core_action") < 20260429007) {
    Logger::debug("Applying core_action 20260429007 - add narrator CreateNewNPC baseline action");

    $db->execQuery("
        INSERT INTO public.core_action (
            code_name,
            action_name,
            description,
            return_message,
            available_to_npc,
            available_to_followers,
            available_to_narrator,
            is_activated,
            parameters_json,
            metadata,
            game_function,
            import_version,
            script_proxy_program
        ) VALUES (
            'CreateNewNPC',
            'Create_New_NPC',
            'Create and spawn a brand-new nearby NPC from a short creation brief.',
            'A new NPC is being created nearby.',
            FALSE,
            FALSE,
            TRUE,
            TRUE,
            '{\"type\":\"object\",\"required\":[\"target\"],\"properties\":{\"target\":{\"type\":\"string\",\"description\":\"REQUIRED: short creation brief for the new nearby NPC.\"}}}'::jsonb,
            '{\"source\":\"functions.php\",\"status\":\"active\",\"builtin\":true,\"dispatch\":\"server_action\"}'::jsonb,
            FALSE,
            0,
            NULL
        )
        ON CONFLICT (code_name) DO UPDATE SET
            action_name = EXCLUDED.action_name,
            description = EXCLUDED.description,
            return_message = EXCLUDED.return_message,
            available_to_npc = EXCLUDED.available_to_npc,
            available_to_followers = EXCLUDED.available_to_followers,
            available_to_narrator = EXCLUDED.available_to_narrator,
            is_activated = EXCLUDED.is_activated,
            parameters_json = EXCLUDED.parameters_json,
            metadata = EXCLUDED.metadata,
            game_function = EXCLUDED.game_function,
            import_version = EXCLUDED.import_version,
            script_proxy_program = EXCLUDED.script_proxy_program,
            updated_at = NOW()
    ");

    $updateVersion("core_action", 20260429007);
    Logger::info("Applied patch core_action 20260429007");
}

if ($checkVersion("core_action") < 20260429008) {
    Logger::debug("Applying core_action 20260429008 - add narrator SpawnNPC baseline action");

    $db->execQuery("
        INSERT INTO public.core_action (
            code_name,
            action_name,
            description,
            return_message,
            available_to_npc,
            available_to_followers,
            available_to_narrator,
            is_activated,
            parameters_json,
            metadata,
            game_function,
            import_version,
            script_proxy_program
        ) VALUES (
            'SpawnNPC',
            'Spawn_NPC',
            'Spawn one or more NPCs near #PLAYER_NAME# from the SNQE NPC template datasets. Put the template key in the target field and the spawn count in amount.',
            'Spawned #AMOUNT# #TARGET# near #PLAYER_NAME#.',
            FALSE,
            FALSE,
            TRUE,
            TRUE,
            '{\"type\":\"object\",\"required\":[\"target\"],\"properties\":{\"target\":{\"type\":\"string\",\"description\":\"REQUIRED: SNQE NPC template key from npc_templates or npc_own_templates.\"},\"amount\":{\"type\":\"integer\",\"description\":\"How many NPCs to spawn from that template key (default: 1, max: 10).\"}}}'::jsonb,
            '{\"source\":\"functions.php\",\"status\":\"active\",\"builtin\":true,\"dispatch\":\"rolecommand\"}'::jsonb,
            TRUE,
            0,
            NULL
        )
        ON CONFLICT (code_name) DO UPDATE SET
            action_name = EXCLUDED.action_name,
            description = EXCLUDED.description,
            return_message = EXCLUDED.return_message,
            available_to_npc = EXCLUDED.available_to_npc,
            available_to_followers = EXCLUDED.available_to_followers,
            available_to_narrator = EXCLUDED.available_to_narrator,
            is_activated = EXCLUDED.is_activated,
            parameters_json = EXCLUDED.parameters_json,
            metadata = EXCLUDED.metadata,
            game_function = EXCLUDED.game_function,
            import_version = EXCLUDED.import_version,
            script_proxy_program = EXCLUDED.script_proxy_program,
            updated_at = NOW()
    ");

    $updateVersion("core_action", 20260429008);
    Logger::info("Applied patch core_action 20260429008");
}

if ($checkVersion("core_action") < 20260430001) {
    Logger::debug("Applying core_action 20260430001 - seed dialogue_timing metadata for baseline action behavior");

    $dialogueTimingUpdates = [
        'Consume' => 'after',
        'InspectSurroundings' => 'after',
        'SpawnItem' => 'after',
        'SpawnNPC' => 'after',
        'TeleportNPC' => 'after',
        'KillTarget' => 'after',
        'CreateNewNPC' => 'before',
    ];

    foreach ($dialogueTimingUpdates as $codeName => $dialogueTiming) {
        $escapedCodeName = $db->escape($codeName);
        $escapedTiming = $db->escape($dialogueTiming);

        $db->execQuery("
            UPDATE public.core_action
            SET metadata = jsonb_set(
                COALESCE(metadata, '{}'::jsonb),
                '{dialogue_timing}',
                to_jsonb('{$escapedTiming}'::text),
                true
            ),
            updated_at = NOW()
            WHERE code_name = '{$escapedCodeName}'
        ");

        $db->execQuery("
            UPDATE public.core_action_custom
            SET metadata = jsonb_set(
                COALESCE(metadata, '{}'::jsonb),
                '{dialogue_timing}',
                to_jsonb('{$escapedTiming}'::text),
                true
            ),
            updated_at = NOW()
            WHERE code_name = '{$escapedCodeName}'
              AND (
                  metadata IS NULL
                  OR NOT (COALESCE(metadata, '{}'::jsonb) ? 'dialogue_timing')
              )
        ");
    }

    $updateVersion("core_action", 20260430001);
    Logger::info("Applied patch core_action 20260430001");
}

if ($checkVersion("core_action") < 20260430002) {
    Logger::debug("Applying core_action 20260430002 - update dialogue_timing metadata for baseline and CHIM-NFF actions");

    $dialogueTimingUpdates = [
        'AddBounty' => 'after',
        'ArrestPlayer' => 'before',
        'Attack' => 'after',
        'Brawl' => 'after',
        'CastSpell' => 'after',
        'CheckInventory' => 'before',
        'ComeCloser' => 'before',
        'Consume' => 'after',
        'DecreaseWalkSpeed' => 'before',
        'Drink' => 'after',
        'EndConversation' => 'before',
        'ExtCmdCHIMNFF_BehindMe' => 'after',
        'ExtCmdCHIMNFF_DismissFollower' => 'after',
        'ExtCmdCHIMNFF_FollowMe' => 'after',
        'ExtCmdCHIMNFF_OpenPlayerChest' => 'after',
        'ExtCmdCHIMNFF_OpenSatchel' => 'before',
        'ExtCmdCHIMNFF_RecruitFollower' => 'after',
        'ExtCmdCHIMNFF_SetHomeBase' => 'after',
        'ExtCmdCHIMNFF_StartAutoLoot' => 'after',
        'ExtCmdCHIMNFF_StopAutoLoot' => 'after',
        'ExtCmdCHIMNFF_TeachRightHandSpell' => 'after',
        'ExtCmdCHIMNFF_WaitHere' => 'after',
        'Follow' => 'after',
        'FollowPlayer' => 'after',
        'ForgiveCrime' => 'after',
        'GiveGoldTo' => 'after',
        'GiveItemTo' => 'after',
        'GoToSleep' => 'before',
        'HireCarriage' => 'before',
        'HireFerry' => 'before',
        'IncreaseWalkSpeed' => 'after',
        'Inspect' => 'after',
        'InspectSurroundings' => 'after',
        'LeadTheWayTo' => 'after',
        'MakeFollower' => 'after',
        'MoveTo' => 'after',
        'OpenInventory' => 'before',
        'OpenInventory2' => 'before',
        'PayBounty' => 'after',
        'PickupItem' => 'after',
        'ReadQuestJournal' => 'after',
        'Relax' => 'before',
        'RentRoom' => 'after',
        'ReturnBackHome' => 'before',
        'SheatheWeapon' => 'after',
        'SpawnItem' => 'after',
        'SpawnNPC' => 'after',
        'StartRitualCeremony' => 'after',
        'StopWalk' => 'after',
        'Surrender' => 'after',
        'TakeASeat' => 'after',
        'TeleportNPC' => 'after',
        'Toast' => 'after',
        'Training' => 'before',
        'TravelTo' => 'after',
        'UseSoulGaze' => 'after',
        'KillTarget' => 'after',
        'CreateNewNPC' => 'before',
    ];

    foreach ($dialogueTimingUpdates as $codeName => $dialogueTiming) {
        $escapedCodeName = $db->escape($codeName);
        $escapedTiming = $db->escape($dialogueTiming);

        $db->execQuery("
            UPDATE public.core_action
            SET metadata = jsonb_set(
                COALESCE(metadata, '{}'::jsonb),
                '{dialogue_timing}',
                to_jsonb('{$escapedTiming}'::text),
                true
            ),
            updated_at = NOW()
            WHERE code_name = '{$escapedCodeName}'
        ");

        $db->execQuery("
            UPDATE public.core_action_custom
            SET metadata = jsonb_set(
                COALESCE(metadata, '{}'::jsonb),
                '{dialogue_timing}',
                to_jsonb('{$escapedTiming}'::text),
                true
            ),
            updated_at = NOW()
            WHERE code_name = '{$escapedCodeName}'
              AND (
                  metadata IS NULL
                  OR NOT (COALESCE(metadata, '{}'::jsonb) ? 'dialogue_timing')
                  OR (COALESCE(metadata, '{}'::jsonb)->>'dialogue_timing') IN ('before', 'after', 'both', 'none')
              )
        ");
    }

    $updateVersion("core_action", 20260430002);
    Logger::info("Applied patch core_action 20260430002");
}

if ($checkVersion("core_action") < 20260430003) {
    Logger::debug("Applying core_action 20260430003 - change CheckInventory dialogue_timing to after");

    $escapedCodeName = $db->escape('CheckInventory');
    $escapedTiming = $db->escape('after');

    $db->execQuery("
        UPDATE public.core_action
        SET metadata = jsonb_set(
            COALESCE(metadata, '{}'::jsonb),
            '{dialogue_timing}',
            to_jsonb('{$escapedTiming}'::text),
            true
        ),
        updated_at = NOW()
        WHERE code_name = '{$escapedCodeName}'
    ");

    $db->execQuery("
        UPDATE public.core_action_custom
        SET metadata = jsonb_set(
            COALESCE(metadata, '{}'::jsonb),
            '{dialogue_timing}',
            to_jsonb('{$escapedTiming}'::text),
            true
        ),
        updated_at = NOW()
        WHERE code_name = '{$escapedCodeName}'
          AND (
              metadata IS NULL
              OR NOT (COALESCE(metadata, '{}'::jsonb) ? 'dialogue_timing')
              OR (COALESCE(metadata, '{}'::jsonb)->>'dialogue_timing') = 'before'
          )
    ");

    $updateVersion("core_action", 20260430003);
    Logger::info("Applied patch core_action 20260430003");
}

if ($checkVersion("core_action") < 20260430004) {
    Logger::debug("Applying core_action 20260430004 - change EndRitualCeremony dialogue_timing to after");

    $escapedCodeName = $db->escape('EndRitualCeremony');
    $escapedTiming = $db->escape('after');

    $db->execQuery("
        UPDATE public.core_action
        SET metadata = jsonb_set(
            COALESCE(metadata, '{}'::jsonb),
            '{dialogue_timing}',
            to_jsonb('{$escapedTiming}'::text),
            true
        ),
        updated_at = NOW()
        WHERE code_name = '{$escapedCodeName}'
    ");

    $db->execQuery("
        UPDATE public.core_action_custom
        SET metadata = jsonb_set(
            COALESCE(metadata, '{}'::jsonb),
            '{dialogue_timing}',
            to_jsonb('{$escapedTiming}'::text),
            true
        ),
        updated_at = NOW()
        WHERE code_name = '{$escapedCodeName}'
          AND (
              metadata IS NULL
              OR NOT (COALESCE(metadata, '{}'::jsonb) ? 'dialogue_timing')
              OR (COALESCE(metadata, '{}'::jsonb)->>'dialogue_timing') = 'both'
          )
    ");

    $updateVersion("core_action", 20260430004);
    Logger::info("Applied patch core_action 20260430004");
}

if ($checkVersion("core_action") < 20260430005) {
    Logger::debug("Applying core_action 20260430005 - remove deprecated dialogue_timing metadata");

    $removeDialogueTimingSql = "
        UPDATE %s
        SET metadata = CASE
            WHEN metadata IS NULL THEN NULL
            ELSE jsonb_strip_nulls(
                CASE
                    WHEN jsonb_typeof(COALESCE(metadata->'custom_config', '{}'::jsonb)) = 'object'
                         AND (COALESCE(metadata->'custom_config', '{}'::jsonb) ? 'dialogue_timing')
                    THEN jsonb_set(
                        COALESCE(metadata, '{}'::jsonb) - 'dialogue_timing',
                        '{custom_config}',
                        COALESCE(metadata->'custom_config', '{}'::jsonb) - 'dialogue_timing',
                        true
                    )
                    ELSE COALESCE(metadata, '{}'::jsonb) - 'dialogue_timing'
                END
            )
        END,
        updated_at = NOW()
        WHERE metadata IS NOT NULL
          AND (
              COALESCE(metadata, '{}'::jsonb) ? 'dialogue_timing'
              OR (
                  jsonb_typeof(COALESCE(metadata->'custom_config', '{}'::jsonb)) = 'object'
                  AND (COALESCE(metadata->'custom_config', '{}'::jsonb) ? 'dialogue_timing')
              )
          )
    ";

    $db->execQuery(sprintf($removeDialogueTimingSql, 'public.core_action'));
    $db->execQuery(sprintf($removeDialogueTimingSql, 'public.core_action_custom'));

    $updateVersion("core_action", 20260430005);
    Logger::info("Applied patch core_action 20260430005");
}

if ($checkVersion("core_action") < 20260430006) {
    Logger::debug("Applying core_action 20260430006 - add ReadQuestJournal and UseSoulGaze to narrator actions");

    $narratorCodes = ['ReadQuestJournal', 'UseSoulGaze'];
    foreach ($narratorCodes as $codeName) {
        $escapedCodeName = $db->escape($codeName);

        $db->execQuery("
            UPDATE public.core_action
            SET available_to_narrator = TRUE,
                updated_at = NOW()
            WHERE code_name = '{$escapedCodeName}'
        ");

        $db->execQuery("
            UPDATE public.core_action_custom
            SET available_to_narrator = TRUE,
                updated_at = NOW()
            WHERE code_name = '{$escapedCodeName}'
        ");
    }

    $updateVersion("core_action", 20260430006);
    Logger::info("Applied patch core_action 20260430006");
}

if ($checkVersion("core_action") < 20260430007) {
    Logger::debug("Applying core_action 20260430007 - seed metadata.funcret follow-up config for built-in actions");

    $funcretConfigs = [
        'GetTopicInfo' => ['mode' => 'handler', 'handler' => 'get_topic_info', 'arg_name' => 'topic'],
        'LeadTheWayTo' => ['mode' => 'handler', 'handler' => 'lead_the_way_to', 'arg_name' => 'location'],
        'MoveTo' => ['mode' => 'handler', 'handler' => 'move_to', 'arg_name' => 'target'],
        'Attack' => ['mode' => 'handler', 'handler' => 'attack', 'arg_name' => 'target'],
        'Inspect' => ['mode' => 'handler', 'handler' => 'inspect', 'arg_name' => 'target'],
        'InspectSurroundings' => ['mode' => 'handler', 'handler' => 'inspect_surroundings', 'arg_name' => 'target'],
        'GetTime' => ['mode' => 'handler', 'handler' => 'get_time', 'arg_name' => 'datestring'],
        'get_current_mission' => ['mode' => 'handler', 'handler' => 'current_mission', 'arg_name' => 'description'],
        'CheckInventory' => ['mode' => 'handler', 'handler' => 'check_inventory', 'arg_name' => 'target'],
        'GiveItemTo' => ['mode' => 'handler', 'handler' => 'give_item_to', 'arg_name' => 'target'],
        'GiveGoldTo' => ['mode' => 'handler', 'handler' => 'give_gold_to', 'arg_name' => 'target'],
        'RentRoom' => ['mode' => 'handler', 'handler' => 'rent_room', 'arg_name' => 'target'],
        'HireCarriage' => ['mode' => 'handler', 'handler' => 'hire_carriage', 'arg_name' => 'target'],
        'HireFerry' => ['mode' => 'handler', 'handler' => 'hire_ferry', 'arg_name' => 'target'],
        'AddBounty' => ['mode' => 'handler', 'handler' => 'add_bounty', 'arg_name' => 'target', 'use_functions_again' => true],
        'PayBounty' => ['mode' => 'handler', 'handler' => 'pay_bounty', 'arg_name' => 'target'],
        'ArrestPlayer' => ['mode' => 'handler', 'handler' => 'arrest_player', 'arg_name' => 'target'],
        'ForgiveCrime' => ['mode' => 'handler', 'handler' => 'forgive_crime', 'arg_name' => 'target'],
        'Consume' => ['mode' => 'none'],
        'FollowPlayer' => ['mode' => 'none'],
        'TakeGoldFromPlayer' => ['mode' => 'none'],
    ];

    foreach ($funcretConfigs as $codeName => $funcretConfig) {
        $escapedCodeName = $db->escape($codeName);
        $funcretJson = json_encode($funcretConfig, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $funcretJsonLiteral = $db->escapeLiteral($funcretJson);

        $db->execQuery("
            UPDATE public.core_action
            SET metadata = jsonb_set(
                COALESCE(metadata, '{}'::jsonb),
                '{funcret}',
                {$funcretJsonLiteral}::jsonb,
                true
            ),
            updated_at = NOW()
            WHERE code_name = '{$escapedCodeName}'
        ");

        $db->execQuery("
            UPDATE public.core_action_custom
            SET metadata = jsonb_set(
                COALESCE(metadata, '{}'::jsonb),
                '{funcret}',
                {$funcretJsonLiteral}::jsonb,
                true
            ),
            updated_at = NOW()
            WHERE code_name = '{$escapedCodeName}'
        ");
    }

    $updateVersion("core_action", 20260430007);
    Logger::info("Applied patch core_action 20260430007");
}

if ($checkVersion("core_action") < 20260430008) {
    Logger::debug("Applying core_action 20260430008 - migrate legacy metadata.funcret follow-up config into metadata.followup");

    $normalizeFollowupConfig = function ($config) {
        if (!is_array($config)) {
            return [];
        }

        $normalized = [];
        if (array_key_exists('enabled', $config)) {
            $normalized['enabled'] = !empty($config['enabled']);
        }

        $prompt = trim(strval($config['prompt'] ?? ''));
        if ($prompt !== '') {
            $normalized['prompt'] = $prompt;
        }

        $argName = trim(strval($config['arg_name'] ?? ''));
        if ($argName !== '') {
            $normalized['arg_name'] = $argName;
        }

        if (array_key_exists('use_functions_again', $config)) {
            $normalized['use_functions_again'] = !empty($config['use_functions_again']);
        }

        return $normalized;
    };

    $convertLegacyFuncretConfig = function ($config) use ($normalizeFollowupConfig) {
        if (!is_array($config) || count($config) === 0) {
            return [];
        }

        $mode = strtolower(trim(strval($config['mode'] ?? '')));
        $followupConfig = [];

        if ($mode === 'none') {
            $followupConfig['enabled'] = false;
        } else if ($mode !== '') {
            $followupConfig['enabled'] = true;
        }

        $instruction = trim(strval($config['instruction'] ?? ''));
        if ($instruction === '' && $mode === 'generic') {
            $instruction = 'Reply with one short in-character line reacting to the tool result below. Do not ask follow-up questions.';
        }
        if ($instruction !== '') {
            $followupConfig['prompt'] = $instruction;
        }

        $argName = trim(strval($config['arg_name'] ?? ''));
        if ($argName !== '') {
            $followupConfig['arg_name'] = $argName;
        }

        if (array_key_exists('use_functions_again', $config)) {
            $followupConfig['use_functions_again'] = !empty($config['use_functions_again']);
        }

        return $normalizeFollowupConfig($followupConfig);
    };

    $baseFollowupConfigs = [
        'GetTopicInfo' => ['enabled' => true, 'arg_name' => 'topic', 'prompt' => 'Reply with one short in-character line about the requested topic using the tool result below. Do not ask follow-up questions.'],
        'LeadTheWayTo' => ['enabled' => true, 'arg_name' => 'location', 'prompt' => 'Reply with one short in-character line acknowledging that you are now leading the player to the destination. Do not ask follow-up questions.'],
        'MoveTo' => ['enabled' => true, 'arg_name' => 'target', 'prompt' => 'Reply with one short in-character line acknowledging that you moved to the target. Do not ask follow-up questions.'],
        'Attack' => ['enabled' => true, 'arg_name' => 'target', 'prompt' => 'Reply with one short in-character combat line reacting to the attack outcome. Do not ask follow-up questions.'],
        'Inspect' => ['enabled' => true, 'arg_name' => 'target', 'prompt' => 'Reply with one short in-character observation using the inspect result below. Do not ask follow-up questions.'],
        'InspectSurroundings' => ['enabled' => true, 'arg_name' => 'target', 'prompt' => 'Reply with one short in-character observation about the surroundings using the tool result below. Do not ask follow-up questions.'],
        'GetTime' => ['enabled' => true, 'arg_name' => 'datestring', 'prompt' => 'Reply with one short in-character line acknowledging the reported time. Do not ask follow-up questions.'],
        'get_current_mission' => ['enabled' => true, 'arg_name' => 'description', 'prompt' => 'Reply with one short in-character line about the current mission using the tool result below. Do not ask follow-up questions.'],
        'CheckInventory' => ['enabled' => true, 'arg_name' => 'target', 'prompt' => 'Reply with one short in-character line about the inventory result below. Do not ask follow-up questions.'],
        'GiveItemTo' => ['enabled' => true, 'arg_name' => 'target', 'prompt' => 'Reply with one short in-character line reacting to the item handoff result below. Do not ask follow-up questions.'],
        'GiveGoldTo' => ['enabled' => true, 'arg_name' => 'target', 'prompt' => 'Reply with one short in-character line reacting to the gold transfer result below. Do not ask follow-up questions.'],
        'RentRoom' => ['enabled' => true, 'arg_name' => 'target', 'prompt' => 'Reply with one short in-character confirmation that the room rental is complete. Do not ask follow-up questions.'],
        'HireCarriage' => ['enabled' => true, 'arg_name' => 'target', 'prompt' => 'Reply with one short in-character line accepting payment and ending the conversation. Do not ask follow-up questions.'],
        'HireFerry' => ['enabled' => true, 'arg_name' => 'target', 'prompt' => 'Reply with one short in-character line accepting payment and ending the conversation. Do not ask follow-up questions.'],
        'AddBounty' => ['enabled' => true, 'arg_name' => 'target', 'prompt' => 'You just added a bounty to the player. React in character. You may follow up with another action if appropriate.', 'use_functions_again' => true],
        'PayBounty' => ['enabled' => true, 'arg_name' => 'target', 'prompt' => 'The player has already paid the bounty and stolen items were removed from inventory. This action is fully complete. Reply with one short confirmation line, do not ask follow-up questions, and end the conversation.'],
        'ArrestPlayer' => ['enabled' => true, 'arg_name' => 'target', 'prompt' => 'You attempted to arrest the player. They get a submit or resist prompt; resist starts combat. Reply with one short stern final line. Do not ask follow-up questions.'],
        'ForgiveCrime' => ['enabled' => true, 'arg_name' => 'target', 'prompt' => 'You forgave the player\'s crimes and cleared their bounty. Reply with one short in-character acknowledgment, warning, or blessing. Do not ask follow-up questions.'],
        'Consume' => ['enabled' => false],
        'FollowPlayer' => ['enabled' => false],
        'TakeGoldFromPlayer' => ['enabled' => false],
    ];

    $syncFollowupMetadata = function ($tableName) use ($db, $baseFollowupConfigs, $normalizeFollowupConfig, $convertLegacyFuncretConfig) {
        $rows = $db->fetchAll("SELECT id, code_name, metadata FROM public.{$tableName}");
        foreach ($rows as $row) {
            $rowId = intval($row['id'] ?? 0);
            if ($rowId <= 0) {
                continue;
            }

            $metadata = json_decode(strval($row['metadata'] ?? '{}'), true);
            if (!is_array($metadata)) {
                $metadata = [];
            }

            $codeName = trim(strval($row['code_name'] ?? ''));
            $customConfig = is_array($metadata['custom_config'] ?? null) ? $metadata['custom_config'] : null;
            $followupConfig = $normalizeFollowupConfig($metadata['followup'] ?? []);
            $legacyFuncretConfig = is_array($metadata['funcret'] ?? null) ? $metadata['funcret'] : [];
            $legacyConvertedConfig = $convertLegacyFuncretConfig($legacyFuncretConfig);
            $changed = false;

            if (count($followupConfig) === 0) {
                if (isset($baseFollowupConfigs[$codeName])) {
                    $followupConfig = $normalizeFollowupConfig($baseFollowupConfigs[$codeName]);
                } else if (count($legacyConvertedConfig) > 0) {
                    $followupConfig = $legacyConvertedConfig;
                }
            }

            if (count($followupConfig) > 0) {
                $metadata['followup'] = $followupConfig;
                $changed = true;
            }

            if (array_key_exists('funcret', $metadata)) {
                unset($metadata['funcret']);
                $changed = true;
            }

            if (is_array($customConfig)) {
                $legacyToNewCustomKeys = [
                    'funcret_mode' => 'followup_enabled',
                    'funcret_arg_name' => 'followup_arg_name',
                    'funcret_instruction' => 'followup_prompt',
                    'funcret_use_functions_again' => 'followup_use_functions_again',
                ];

                foreach ($legacyToNewCustomKeys as $legacyKey => $newKey) {
                    if (!array_key_exists($legacyKey, $customConfig)) {
                        continue;
                    }

                if (!array_key_exists($newKey, $customConfig)) {
                    if ($legacyKey === 'funcret_mode') {
                        $legacyMode = strtolower(trim(strval($customConfig[$legacyKey])));
                        $customConfig[$newKey] = $legacyMode !== 'none';
                    } else {
                        $customConfig[$newKey] = $customConfig[$legacyKey];
                    }
                }

                    unset($customConfig[$legacyKey]);
                    $changed = true;
                }

                if ($changed) {
                    $metadata['custom_config'] = $customConfig;
                }
            }

            if (!$changed) {
                continue;
            }

            $metadataJson = json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if (!is_string($metadataJson) || $metadataJson === '') {
                $metadataJson = '{}';
            }
            $metadataJsonLiteral = $db->escapeLiteral($metadataJson);

            $db->execQuery("
                UPDATE public.{$tableName}
                SET metadata = {$metadataJsonLiteral}::jsonb,
                    updated_at = NOW()
                WHERE id = {$rowId}
            ");
        }
    };

    $syncFollowupMetadata('core_action');
    $syncFollowupMetadata('core_action_custom');

    $updateVersion("core_action", 20260430008);
    Logger::info("Applied patch core_action 20260430008");
}

if ($checkVersion("core_action") < 20260430009) {
    Logger::debug("Applying core_action 20260430009 - remove legacy_external from followup metadata");

    $stripLegacyExternal = function ($tableName) use ($db) {
        $rows = $db->fetchAll("SELECT id, metadata FROM public.{$tableName}");
        foreach ($rows as $row) {
            $rowId = intval($row['id'] ?? 0);
            if ($rowId <= 0) {
                continue;
            }

            $metadata = json_decode(strval($row['metadata'] ?? '{}'), true);
            if (!is_array($metadata)) {
                continue;
            }

            $changed = false;

            if (is_array($metadata['followup'] ?? null) && array_key_exists('legacy_external', $metadata['followup'])) {
                unset($metadata['followup']['legacy_external']);
                $changed = true;
            }

            if (is_array($metadata['custom_config'] ?? null) && array_key_exists('followup_legacy_external', $metadata['custom_config'])) {
                unset($metadata['custom_config']['followup_legacy_external']);
                $changed = true;
            }

            if (!$changed) {
                continue;
            }

            $metadataJson = json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if (!is_string($metadataJson) || $metadataJson === '') {
                $metadataJson = '{}';
            }

            $metadataJsonLiteral = $db->escapeLiteral($metadataJson);
            $db->execQuery("
                UPDATE public.{$tableName}
                SET metadata = {$metadataJsonLiteral}::jsonb,
                    updated_at = NOW()
                WHERE id = {$rowId}
            ");
        }
    };

    $stripLegacyExternal('core_action');
    $stripLegacyExternal('core_action_custom');

    $updateVersion("core_action", 20260430009);
    Logger::info("Applied patch core_action 20260430009");
}

if ($checkVersion("core_action") < 20260430010) {
    Logger::debug("Applying core_action 20260430010 - normalize shared cost_gold action config");

    $normalizeSharedCostField = function ($tableName) use ($db) {
        $targetCodes = ['RentRoom', 'HireCarriage', 'HireFerry'];
        $legacyKeys = ['rent_room_cost', 'hire_carriage_cost', 'hire_ferry_cost'];

        $rows = $db->fetchAll("SELECT id, code_name, metadata FROM public.{$tableName}");
        foreach ($rows as $row) {
            $rowId = intval($row['id'] ?? 0);
            $codeName = trim(strval($row['code_name'] ?? ''));
            if ($rowId <= 0 || !in_array($codeName, $targetCodes, true)) {
                continue;
            }

            $metadata = json_decode(strval($row['metadata'] ?? '{}'), true);
            if (!is_array($metadata)) {
                $metadata = [];
            }

            $changed = false;

            if (is_array($metadata['editor_fields'] ?? null)) {
                foreach ($metadata['editor_fields'] as &$editorField) {
                    if (!is_array($editorField)) {
                        continue;
                    }

                    $fieldKey = trim(strval($editorField['key'] ?? ''));
                    if ($fieldKey === '' || !in_array($fieldKey, $legacyKeys, true)) {
                        continue;
                    }

                    $editorField['key'] = 'cost_gold';
                    $editorField['label'] = 'Gold Cost';
                    $editorField['help'] = 'How much gold this action costs.';
                    $changed = true;
                }
                unset($editorField);
            }

            if (is_array($metadata['parameter_template'] ?? null)) {
                array_walk_recursive($metadata['parameter_template'], function (&$value) use (&$changed) {
                    if (!is_string($value)) {
                        return;
                    }

                    $updatedValue = str_replace(
                        ['{{config.rent_room_cost}}', '{{config.hire_carriage_cost}}', '{{config.hire_ferry_cost}}'],
                        '{{config.cost_gold}}',
                        $value
                    );

                    if ($updatedValue !== $value) {
                        $value = $updatedValue;
                        $changed = true;
                    }
                });
            }

            if (is_array($metadata['custom_config'] ?? null)) {
                foreach ($legacyKeys as $legacyKey) {
                    if (!array_key_exists($legacyKey, $metadata['custom_config'])) {
                        continue;
                    }

                    if (!array_key_exists('cost_gold', $metadata['custom_config'])) {
                        $metadata['custom_config']['cost_gold'] = $metadata['custom_config'][$legacyKey];
                    }

                    unset($metadata['custom_config'][$legacyKey]);
                    $changed = true;
                }
            }

            if (!$changed) {
                continue;
            }

            $metadataJson = json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if (!is_string($metadataJson) || $metadataJson === '') {
                $metadataJson = '{}';
            }

            $metadataJsonLiteral = $db->escapeLiteral($metadataJson);
            $db->execQuery("
                UPDATE public.{$tableName}
                SET metadata = {$metadataJsonLiteral}::jsonb,
                    updated_at = NOW()
                WHERE id = {$rowId}
            ");
        }
    };

    $normalizeSharedCostField('core_action');
    $normalizeSharedCostField('core_action_custom');

    $updateVersion("core_action", 20260430010);
    Logger::info("Applied patch core_action 20260430010");
}

if ($checkVersion("core_action") < 20260430011) {
    Logger::debug("Applying core_action 20260430011 - normalize cost placeholders in action text");

    $normalizeCostPlaceholderText = function ($tableName, $rewriteCustomRows = false) use ($db) {
        $targetRows = [
            'RentRoom' => [
                'description' => '#HERIKA_NAME# rents a room to #PLAYER_NAME# for {{config.cost_gold}} gold. Only innkeepers can use this action and it only applies to #PLAYER_NAME#.',
                'return_message' => '#HERIKA_NAME# rented a room to #PLAYER_NAME# for {{config.cost_gold}} gold.',
                'legacy_descriptions' => [
                    '#HERIKA_NAME# rents a room to #PLAYER_NAME# for 10 gold. Only innkeepers can use this action and it only applies to #PLAYER_NAME#.',
                ],
                'legacy_return_messages' => [
                    '#HERIKA_NAME# rented a room to #PLAYER_NAME# for 10 gold.',
                ],
            ],
            'HireCarriage' => [
                'description' => '#HERIKA_NAME# accepts {{config.cost_gold}} gold for carriage travel and transports #PLAYER_NAME# to the specified destination. Reply with one short acceptance line, do not ask follow-up questions, then end the conversation.',
                'return_message' => '#HERIKA_NAME# accepted the {{config.cost_gold}} gold carriage fare to #TARGET# and ended the conversation.',
                'legacy_descriptions' => [
                    '#HERIKA_NAME# accepts 20 gold for carriage travel and transports #PLAYER_NAME# to the specified destination. Reply with one short acceptance line, do not ask follow-up questions, then end the conversation.',
                ],
                'legacy_return_messages' => [
                    '#HERIKA_NAME# accepted the 20 gold carriage fare to #TARGET# and ended the conversation.',
                ],
            ],
            'HireFerry' => [
                'description' => '#HERIKA_NAME# accepts {{config.cost_gold}} gold for ferry travel and transports #PLAYER_NAME# to the specified destination. Reply with one short acceptance line, do not ask follow-up questions, then end the conversation.',
                'return_message' => '#HERIKA_NAME# accepted the {{config.cost_gold}} gold ferry fare to #TARGET# and ended the conversation.',
                'legacy_descriptions' => [
                    '#HERIKA_NAME# accepts 50 gold for ferry travel and transports #PLAYER_NAME# to the specified destination. Reply with one short acceptance line, do not ask follow-up questions, then end the conversation.',
                ],
                'legacy_return_messages' => [
                    '#HERIKA_NAME# accepted the 50 gold ferry fare to #TARGET# and ended the conversation.',
                ],
            ],
        ];

        $rows = $db->fetchAll("SELECT id, code_name, description, return_message FROM public.{$tableName}");
        foreach ($rows as $row) {
            $rowId = intval($row['id'] ?? 0);
            $codeName = trim(strval($row['code_name'] ?? ''));
            if ($rowId <= 0 || !isset($targetRows[$codeName])) {
                continue;
            }

            $target = $targetRows[$codeName];
            $description = strval($row['description'] ?? '');
            $returnMessage = strval($row['return_message'] ?? '');
            $newDescription = $description;
            $newReturnMessage = $returnMessage;

            if ($tableName === 'core_action') {
                $newDescription = $target['description'];
                $newReturnMessage = $target['return_message'];
            } elseif ($rewriteCustomRows) {
                if (in_array($description, $target['legacy_descriptions'], true)) {
                    $newDescription = $target['description'];
                }
                if (in_array($returnMessage, $target['legacy_return_messages'], true)) {
                    $newReturnMessage = $target['return_message'];
                }
            }

            if ($newDescription === $description && $newReturnMessage === $returnMessage) {
                continue;
            }

            $descriptionLiteral = $db->escapeLiteral($newDescription);
            $returnMessageLiteral = $db->escapeLiteral($newReturnMessage);
            $db->execQuery("
                UPDATE public.{$tableName}
                SET description = {$descriptionLiteral},
                    return_message = {$returnMessageLiteral},
                    updated_at = NOW()
                WHERE id = {$rowId}
            ");
        }
    };

    $normalizeCostPlaceholderText('core_action', false);
    $normalizeCostPlaceholderText('core_action_custom', true);

    $updateVersion("core_action", 20260430011);
    Logger::info("Applied patch core_action 20260430011");
}

if ($checkVersion("core_action") < 20260430012) {
    Logger::debug("Applying core_action 20260430012 - normalize followup player references to #PLAYER_NAME#");

    $normalizeFollowupPlayerReferences = function ($tableName) use ($db) {
        $promptMap = [
            'AddBounty' => [
                'new' => 'You just added a bounty to #PLAYER_NAME#. React in character. You may follow up with another action if appropriate.',
                'old' => [
                    'You just added a bounty to the player. React in character. You may follow up with another action if appropriate.',
                ],
            ],
            'PayBounty' => [
                'new' => '#PLAYER_NAME# has already paid the bounty and stolen items were removed from inventory. This action is fully complete. Reply with one short confirmation line, do not ask follow-up questions, and end the conversation.',
                'old' => [
                    'The player has already paid the bounty and stolen items were removed from inventory. This action is fully complete. Reply with one short confirmation line, do not ask follow-up questions, and end the conversation.',
                ],
            ],
            'ArrestPlayer' => [
                'new' => 'You attempted to arrest #PLAYER_NAME#. They get a submit or resist prompt; resist starts combat. Reply with one short stern final line. Do not ask follow-up questions.',
                'old' => [
                    'You attempted to arrest the player. They get a submit or resist prompt; resist starts combat. Reply with one short stern final line. Do not ask follow-up questions.',
                ],
            ],
            'ForgiveCrime' => [
                'new' => 'You forgave #PLAYER_NAME#\'s crimes and cleared their bounty. Reply with one short in-character acknowledgment, warning, or blessing. Do not ask follow-up questions.',
                'old' => [
                    'You forgave the player\'s crimes and cleared their bounty. Reply with one short in-character acknowledgment, warning, or blessing. Do not ask follow-up questions.',
                ],
            ],
        ];

        $rows = $db->fetchAll("SELECT id, code_name, metadata FROM public.{$tableName}");
        foreach ($rows as $row) {
            $rowId = intval($row['id'] ?? 0);
            $codeName = trim(strval($row['code_name'] ?? ''));
            if ($rowId <= 0 || !isset($promptMap[$codeName])) {
                continue;
            }

            $metadata = json_decode(strval($row['metadata'] ?? '{}'), true);
            if (!is_array($metadata) || !is_array($metadata['followup'] ?? null)) {
                continue;
            }

            $currentPrompt = strval($metadata['followup']['prompt'] ?? '');
            if ($tableName !== 'core_action' && !in_array($currentPrompt, $promptMap[$codeName]['old'], true)) {
                continue;
            }

            if ($currentPrompt === $promptMap[$codeName]['new']) {
                continue;
            }

            $metadata['followup']['prompt'] = $promptMap[$codeName]['new'];
            $metadataJson = json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if (!is_string($metadataJson) || $metadataJson === '') {
                $metadataJson = '{}';
            }

            $metadataLiteral = $db->escapeLiteral($metadataJson);
            $db->execQuery("
                UPDATE public.{$tableName}
                SET metadata = {$metadataLiteral}::jsonb,
                    updated_at = NOW()
                WHERE id = {$rowId}
            ");
        }
    };

    $normalizeFollowupPlayerReferences('core_action');
    $normalizeFollowupPlayerReferences('core_action_custom');

    $updateVersion("core_action", 20260430012);
    Logger::info("Applied patch core_action 20260430012");
}

if ($checkVersion("core_action") < 20260430013) {
    Logger::debug("Applying core_action 20260430013 - disable built-in followups for selected actions");

    $disableBuiltInFollowups = function ($tableName, $rewriteCustomRows = false) use ($db) {
        $targetCodes = [
            'Attack',
            'ForgiveCrime',
            'GiveGoldTo',
            'GiveItemTo',
            'HireCarriage',
            'HireFerry',
            'LeadTheWayTo',
            'MoveTo',
            'RentRoom',
        ];

        $rows = $db->fetchAll("SELECT id, code_name, metadata FROM public.{$tableName}");
        foreach ($rows as $row) {
            $rowId = intval($row['id'] ?? 0);
            $codeName = trim(strval($row['code_name'] ?? ''));
            if ($rowId <= 0 || !in_array($codeName, $targetCodes, true)) {
                continue;
            }

            $metadata = json_decode(strval($row['metadata'] ?? '{}'), true);
            if (!is_array($metadata) || !is_array($metadata['followup'] ?? null)) {
                continue;
            }

            if ($rewriteCustomRows) {
                $customConfig = is_array($metadata['custom_config'] ?? null) ? $metadata['custom_config'] : [];
                if (array_key_exists('followup_enabled', $customConfig)) {
                    continue;
                }
            }

            if (array_key_exists('enabled', $metadata['followup']) && empty($metadata['followup']['enabled'])) {
                continue;
            }

            $metadata['followup']['enabled'] = false;
            $metadataJson = json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if (!is_string($metadataJson) || $metadataJson === '') {
                $metadataJson = '{}';
            }

            $metadataLiteral = $db->escapeLiteral($metadataJson);
            $db->execQuery("
                UPDATE public.{$tableName}
                SET metadata = {$metadataLiteral}::jsonb,
                    updated_at = NOW()
                WHERE id = {$rowId}
            ");
        }
    };

    $disableBuiltInFollowups('core_action', false);
    $disableBuiltInFollowups('core_action_custom', true);

    $updateVersion("core_action", 20260430013);
    Logger::info("Applied patch core_action 20260430013");
}

if ($checkVersion("core_action") < 20260430014) {
    Logger::debug("Applying core_action 20260430014 - add CHIM-NFF Teach_Spell followup metadata");

    $addTeachSpellFollowup = function ($tableName, $rewriteCustomRows = false) use ($db) {
        $rows = $db->fetchAll("
            SELECT id, metadata
            FROM public.{$tableName}
            WHERE LOWER(code_name) = LOWER('ExtCmdCHIMNFF_TeachRightHandSpell')
        ");

        foreach ($rows as $row) {
            $rowId = intval($row['id'] ?? 0);
            if ($rowId <= 0) {
                continue;
            }

            $metadata = json_decode(strval($row['metadata'] ?? '{}'), true);
            if (!is_array($metadata)) {
                $metadata = [];
            }

            if ($rewriteCustomRows) {
                $customConfig = is_array($metadata['custom_config'] ?? null) ? $metadata['custom_config'] : [];
                if (array_key_exists('followup_enabled', $customConfig)) {
                    continue;
                }
            }

            $metadata['followup'] = [
                'enabled' => true,
                'arg_name' => 'target',
                'prompt' => "Reply with one short in-character line reacting to learning #PLAYER_NAME#'s right-hand spell. Do not ask follow-up questions.",
            ];

            $metadataJson = json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if (!is_string($metadataJson) || $metadataJson === '') {
                $metadataJson = '{}';
            }

            $metadataLiteral = $db->escapeLiteral($metadataJson);
            $db->execQuery("
                UPDATE public.{$tableName}
                SET metadata = {$metadataLiteral}::jsonb,
                    updated_at = NOW()
                WHERE id = {$rowId}
            ");
        }
    };

    $addTeachSpellFollowup('core_action', false);
    $addTeachSpellFollowup('core_action_custom', true);

    $updateVersion("core_action", 20260430014);
    Logger::info("Applied patch core_action 20260430014");
}

if ($checkVersion("core_action") < 20260430015) {
    Logger::debug("Applying core_action 20260430015 - add ReadQuestJournal followup metadata");

    $addReadQuestJournalFollowup = function ($tableName, $rewriteCustomRows = false) use ($db) {
        $rows = $db->fetchAll("
            SELECT id, metadata
            FROM public.{$tableName}
            WHERE LOWER(code_name) = LOWER('ReadQuestJournal')
        ");

        foreach ($rows as $row) {
            $rowId = intval($row['id'] ?? 0);
            if ($rowId <= 0) {
                continue;
            }

            $metadata = json_decode(strval($row['metadata'] ?? '{}'), true);
            if (!is_array($metadata)) {
                $metadata = [];
            }

            if ($rewriteCustomRows) {
                $customConfig = is_array($metadata['custom_config'] ?? null) ? $metadata['custom_config'] : [];
                if (array_key_exists('followup_enabled', $customConfig)) {
                    continue;
                }
            }

            $metadata['followup'] = [
                'enabled' => true,
                'arg_name' => 'id_quest',
                'prompt' => 'Reply with one short in-character line about the quest journal result below. Do not ask follow-up questions.',
            ];

            $metadataJson = json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if (!is_string($metadataJson) || $metadataJson === '') {
                $metadataJson = '{}';
            }

            $metadataLiteral = $db->escapeLiteral($metadataJson);
            $db->execQuery("
                UPDATE public.{$tableName}
                SET metadata = {$metadataLiteral}::jsonb,
                    updated_at = NOW()
                WHERE id = {$rowId}
            ");
        }
    };

    $addReadQuestJournalFollowup('core_action', false);
    $addReadQuestJournalFollowup('core_action_custom', true);

    $updateVersion("core_action", 20260430015);
    Logger::info("Applied patch core_action 20260430015");
}

if ($checkVersion("game_plugins") < 20260427001) {
    Logger::debug("Applying game_plugins 20260427001 - create loaded plugin manifest table");

    $db->execQuery(file_get_contents(__DIR__ . "/../data/add_game_plugins.sql"));
    $db->execQuery("SET search_path TO public");

    $updateVersion("game_plugins", 20260427001);
    Logger::info("Applied patch game_plugins 20260427001");
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

// add memory_summary scope support (global by default in current system)
if ($checkVersion("memory_summary")<20260319001) {
    $scopeColumn = $db->fetchOne("
        SELECT column_name
        FROM information_schema.columns
        WHERE table_name = 'memory_summary' AND column_name = 'scope'
    ");

    if (!isset($scopeColumn["column_name"]) || !$scopeColumn["column_name"]) {
        $db->execQuery('ALTER TABLE "memory_summary" ADD COLUMN "scope" text');
    }

    $updateVersion("memory_summary",20260319001);
    Logger::info("Applied patch memory_summary 20260319001");
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

if ($checkVersion(tablename: "factions")<20260214001) {
    $db->execQuery(file_get_contents(__DIR__."/../data/add_factions.sql"));
    $updateVersion("factions",20260214001);
    error_log("Applied patch factions 20260214001");
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

if ($checkVersion("quest_reference_data")<20260323001) {
    Logger::debug("try patch: quest_reference_data 20260323001");
    $db->execQuery(file_get_contents(__DIR__."/../data/quest_reference_data.sql"));
    $updateVersion("quest_reference_data",20260323001);
    Logger::info("Applied patch quest_reference_data 20260323001");
}

if ($checkVersion("quest_reference_data")<20260323002) {
    Logger::debug("try patch: quest_reference_data 20260323002 (array support)");
    $db->execQuery(file_get_contents(__DIR__."/../data/quest_reference_data_arrays.sql"));
    $updateVersion("quest_reference_data",20260323002);
    Logger::info("Applied patch quest_reference_data 20260323002 (array support)");
}

if ($checkVersion("quest_reference_data")<20260323003) {
    Logger::debug("try patch: quest_reference_data 20260323003 (array insert defaults)");
    $db->execQuery(file_get_contents(__DIR__."/../data/quest_reference_data_arrays.sql"));
    $updateVersion("quest_reference_data",20260323003);
    Logger::info("Applied patch quest_reference_data 20260323003 (array insert defaults)");
}

if ($checkVersion("quest_reference_data")<20260323004) {
    Logger::debug("try patch: quest_reference_data 20260323004 (consolidate array rows)");
    $db->execQuery(file_get_contents(__DIR__."/../data/quest_reference_data_consolidate.sql"));
    $updateVersion("quest_reference_data",20260323004);
    Logger::info("Applied patch quest_reference_data 20260323004 (consolidate array rows)");
}

if ($checkVersion("quest_reference_data")<20260323005) {
    Logger::debug("try patch: quest_reference_data 20260323005 (canonical hex formids)");
    $a = $db->execQuery(file_get_contents(__DIR__."/../data/quest_reference_data_hex.sql"));
    if ($a) {
        $updateVersion("quest_reference_data",20260323005);
        Logger::info("Applied patch quest_reference_data 20260323005 (canonical hex formids)");
    } else {
        Logger::error("Patch quest_reference_data 20260323005 failed!");
    }
}

if ($checkVersion("quest_reference_data")<20260323006) {
    Logger::debug("try patch: quest_reference_data 20260323006 (json-only formids)");
    $a = $db->execQuery(file_get_contents(__DIR__."/../data/quest_reference_data_json_only.sql"));
    if ($a) {
        $updateVersion("quest_reference_data",20260323006);
        Logger::info("Applied patch quest_reference_data 20260323006 (json-only formids)");
    } else {
        Logger::error("Patch quest_reference_data 20260323006 failed!");
    }
}

if ($checkVersion("quest_reference_data")<20260323007) {
    Logger::debug("try patch: quest_reference_data 20260323007 (drop unsupported location datasets)");
    $a = $db->execQuery(file_get_contents(__DIR__."/../data/quest_reference_data_drop_unused.sql"));
    if ($a) {
        $updateVersion("quest_reference_data",20260323007);
        Logger::info("Applied patch quest_reference_data 20260323007 (drop unsupported location datasets)");
    } else {
        Logger::error("Patch quest_reference_data 20260323007 failed!");
    }
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
            // pg_dump exports clear search_path; restore it for subsequent unqualified helpers.
            $db->execQuery("SET search_path TO public");
            
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
        $db->execQuery("UPDATE public.core_api_badge SET label = 'Groq' WHERE LOWER(label) = 'groq'");
        $db->execQuery("UPDATE public.core_api_badge SET label = 'Nano-GPT' WHERE LOWER(label) = 'nano-gpt'");
        $db->execQuery("UPDATE public.core_api_badge SET label = 'DeepL' WHERE LOWER(label) = 'deepl'");
        
        // Add unique constraint once; reinstall/update paths can revisit this patch.
        $db->execQuery("
            DO $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1
                    FROM pg_constraint
                    WHERE conname = 'core_api_badge_label_unique'
                      AND conrelid = 'public.core_api_badge'::regclass
                ) THEN
                    ALTER TABLE public.core_api_badge
                    ADD CONSTRAINT core_api_badge_label_unique UNIQUE (label);
                END IF;
            END $$;
        ");
        
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

if ($checkVersion("core_llm_connector") < 20260423001) {
    Logger::debug("Applying core_llm_connector 20260423001 - Seeding dedicated scene classifier connector");
    try {
        $sceneClassifierLabel = "Gemma 3N E4B";
        $sceneClassifierLabelEscaped = $db->escape($sceneClassifierLabel);
        $existingSceneClassifier = $db->fetchOne(
            "SELECT id FROM public.core_llm_connector WHERE LOWER(COALESCE(label,'')) = LOWER('{$sceneClassifierLabelEscaped}') LIMIT 1"
        );

        if (!$existingSceneClassifier || !isset($existingSceneClassifier["id"])) {
            $openRouterBadge = $db->fetchOne("SELECT id FROM public.core_api_badge WHERE LOWER(label) = 'openrouter' LIMIT 1");
            $openRouterBadgeId = intval($openRouterBadge["id"] ?? 0);

            $insertPayload = [
                "label" => $sceneClassifierLabel,
                "metadata" => "{}",
                "url" => "https://openrouter.ai/api/v1/chat/completions",
                "model" => "google/gemma-3n-e4b-it",
                "provider" => "openrouter",
                "driver" => "openrouterjson",
                "max_tokens" => 128,
                "enforce_json" => 1,
                "prefill_json" => 0,
                "json_schema" => 1,
                "temperature" => 0.2,
                "service" => "openrouter"
            ];
            if ($openRouterBadgeId > 0) {
                $insertPayload["api_badge_id"] = $openRouterBadgeId;
            }

            $db->insert("public.core_llm_connector", $insertPayload);
            Logger::info("Inserted dedicated scene classifier connector '{$sceneClassifierLabel}'");
        } else {
            Logger::info("Dedicated scene classifier connector already exists with ID " . intval($existingSceneClassifier["id"]));
        }

        $updateVersion("core_llm_connector", 20260423001);
        Logger::info("Applied patch core_llm_connector 20260423001");
    } catch (Exception $e) {
        Logger::error("Error applying core_llm_connector 20260423001: " . $e->getMessage());
    }
}

if ($checkVersion("core_llm_connector") < 20260423002) {
    Logger::debug("Applying core_llm_connector 20260423002 - Migrating scene classifier default to Gemma 3N E4B");
    try {
        $sceneClassifierLabel = "Gemma 3N E4B";
        $legacySceneClassifierLabel = "Scene Classifier (Gemma 3N E4B)";
        $legacySceneClassifierLabel2 = "Scene Classifier (Gemini 2.5 Flash Lite)";
        $sceneClassifierLabelEscaped = $db->escape($sceneClassifierLabel);
        $legacySceneClassifierLabelEscaped = $db->escape($legacySceneClassifierLabel);
        $legacySceneClassifierLabelEscaped2 = $db->escape($legacySceneClassifierLabel2);

        $sceneClassifierRow = $db->fetchOne(
            "SELECT id FROM public.core_llm_connector
             WHERE LOWER(COALESCE(label,'')) = LOWER('{$sceneClassifierLabelEscaped}')
                OR LOWER(COALESCE(label,'')) = LOWER('{$legacySceneClassifierLabelEscaped}')
                OR LOWER(COALESCE(label,'')) = LOWER('{$legacySceneClassifierLabelEscaped2}')
             ORDER BY id ASC
             LIMIT 1"
        );

        $openRouterBadge = $db->fetchOne("SELECT id FROM public.core_api_badge WHERE LOWER(label) = 'openrouter' LIMIT 1");
        $openRouterBadgeId = intval($openRouterBadge["id"] ?? 0);

        $sceneClassifierPayload = [
            "label" => $sceneClassifierLabel,
            "metadata" => "{}",
            "url" => "https://openrouter.ai/api/v1/chat/completions",
            "model" => "google/gemma-3n-e4b-it",
            "provider" => "openrouter",
            "driver" => "openrouterjson",
            "max_tokens" => 128,
            "enforce_json" => 1,
            "prefill_json" => 0,
            "json_schema" => 1,
            "temperature" => 0.2,
            "service" => "openrouter"
        ];
        if ($openRouterBadgeId > 0) {
            $sceneClassifierPayload["api_badge_id"] = $openRouterBadgeId;
        }

        if ($sceneClassifierRow && isset($sceneClassifierRow["id"])) {
            $db->updateRow("public.core_llm_connector", $sceneClassifierPayload, "id=" . intval($sceneClassifierRow["id"]));
            Logger::info("Updated dedicated scene classifier connector ID " . intval($sceneClassifierRow["id"]) . " to Gemma 3N E4B");
        } else {
            $db->insert("public.core_llm_connector", $sceneClassifierPayload);
            Logger::info("Inserted dedicated scene classifier connector '{$sceneClassifierLabel}'");
        }

        $updateVersion("core_llm_connector", 20260423002);
        Logger::info("Applied patch core_llm_connector 20260423002");
    } catch (Exception $e) {
        Logger::error("Error applying core_llm_connector 20260423002: " . $e->getMessage());
    }
}

if ($checkVersion("core_llm_connector") < 20260423003) {
    Logger::debug("Applying core_llm_connector 20260423003 - Shortening scene classifier connector label");
    try {
        $sceneClassifierLabel = "Gemma 3N E4B";
        $legacySceneClassifierLabels = [
            "Scene Classifier (Gemma 3N E4B)",
            "Scene Classifier (Gemini 2.5 Flash Lite)"
        ];

        $conditions = [];
        $conditions[] = "LOWER(COALESCE(label,'')) = LOWER('" . $db->escape($sceneClassifierLabel) . "')";
        foreach ($legacySceneClassifierLabels as $legacyLabel) {
            $conditions[] = "LOWER(COALESCE(label,'')) = LOWER('" . $db->escape($legacyLabel) . "')";
        }

        $sceneClassifierRow = $db->fetchOne(
            "SELECT id FROM public.core_llm_connector WHERE " . implode(" OR ", $conditions) . " ORDER BY id ASC LIMIT 1"
        );

        $openRouterBadge = $db->fetchOne("SELECT id FROM public.core_api_badge WHERE LOWER(label) = 'openrouter' LIMIT 1");
        $openRouterBadgeId = intval($openRouterBadge["id"] ?? 0);

        $sceneClassifierPayload = [
            "label" => $sceneClassifierLabel,
            "metadata" => "{}",
            "url" => "https://openrouter.ai/api/v1/chat/completions",
            "model" => "google/gemma-3n-e4b-it",
            "provider" => "openrouter",
            "driver" => "openrouterjson",
            "max_tokens" => 128,
            "enforce_json" => 1,
            "prefill_json" => 0,
            "json_schema" => 1,
            "temperature" => 0.2,
            "service" => "openrouter"
        ];
        if ($openRouterBadgeId > 0) {
            $sceneClassifierPayload["api_badge_id"] = $openRouterBadgeId;
        }

        if ($sceneClassifierRow && isset($sceneClassifierRow["id"])) {
            $db->updateRow("public.core_llm_connector", $sceneClassifierPayload, "id=" . intval($sceneClassifierRow["id"]));
            Logger::info("Renamed scene classifier connector ID " . intval($sceneClassifierRow["id"]) . " to '{$sceneClassifierLabel}'");
        } else {
            $db->insert("public.core_llm_connector", $sceneClassifierPayload);
            Logger::info("Inserted dedicated scene classifier connector '{$sceneClassifierLabel}'");
        }

        $updateVersion("core_llm_connector", 20260423003);
        Logger::info("Applied patch core_llm_connector 20260423003");
    } catch (Exception $e) {
        Logger::error("Error applying core_llm_connector 20260423003: " . $e->getMessage());
    }
}

if ($checkTableExists("core_npc_master_history") == -1) {
    $db->execQuery(file_get_contents(__DIR__."/../lib/core/database_schema/core_npc_master_history.sql"));
} else
    Logger::info(__FILE__." core_npc_master_history exists");

$db->execQuery(
    "CREATE INDEX IF NOT EXISTS idx_core_npc_master_history_restore
     ON public.core_npc_master_history (
         npc_id,
         gamets_last_updated DESC NULLS LAST,
         (CASE WHEN extended_data ->> '_chim_history_source' = 'infosave' THEN 1 ELSE 0 END) DESC,
         created DESC
     )"
);

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
        ["name"=>"core_tts_fallback","file"=>__DIR__."/../lib/core/database_schema/core_tts_fallback.sql"],
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

// Backups created before the bio template primary keys existed can restore the
// tables without constraints. Repair that shape before migrations use
// ON CONFLICT (npc_name).
$repairBioTemplateTable = function($tableName) use ($checkTableExists) {
    global $db;

    if (!in_array($tableName, ["bio_templates", "bio_templates_custom"], true)) {
        return;
    }

    if ($checkTableExists($tableName) == -1) {
        return;
    }

    $columns = [
        "npc_name" => "character varying(128)",
        "oghma_knowledge_tags" => "text",
        "core" => "text",
        "npc_static_bio" => "text",
        "appearance" => "text",
        "personality" => "text",
        "relationships" => "text",
        "occupation" => "text",
        "skills" => "text",
        "speechstyle" => "text",
        "goals" => "text",
        "voiceid" => "text",
        "gender" => "text",
        "race" => "text",
        "refid" => "text",
    ];

    foreach ($columns as $columnName => $columnType) {
        $db->execQuery("ALTER TABLE public.{$tableName} ADD COLUMN IF NOT EXISTS {$columnName} {$columnType}");
    }

    $db->execQuery("DELETE FROM public.{$tableName} WHERE npc_name IS NULL OR btrim(npc_name::text) = ''");
    $db->execQuery("
        DELETE FROM public.{$tableName} a
        USING public.{$tableName} b
        WHERE a.npc_name = b.npc_name
          AND a.ctid < b.ctid
    ");
    $db->execQuery("ALTER TABLE public.{$tableName} ALTER COLUMN npc_name SET NOT NULL");

    $indexName = "idx_{$tableName}_npc_name_unique";
    $db->execQuery("
        DO $$
        BEGIN
            IF NOT EXISTS (
                SELECT 1
                  FROM pg_index i
                  JOIN pg_class t ON t.oid = i.indrelid
                  JOIN pg_namespace n ON n.oid = t.relnamespace
                  JOIN pg_attribute a ON a.attrelid = t.oid AND a.attnum = ANY(i.indkey)
                 WHERE n.nspname = 'public'
                   AND t.relname = '{$tableName}'
                   AND i.indisunique
                   AND i.indnatts = 1
                   AND a.attname = 'npc_name'
            ) THEN
                CREATE UNIQUE INDEX {$indexName} ON public.{$tableName} (npc_name);
            END IF;
        END $$;
    ");
};

try {
    $repairBioTemplateTable("bio_templates");
    $repairBioTemplateTable("bio_templates_custom");
} catch (Throwable $e) {
    Logger::warn("Bio template schema repair failed: " . $e->getMessage());
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
            plugin text NOT NULL DEFAULT '',
            baseid character varying(128) NOT NULL,
            name text,
            description text,
            PRIMARY KEY (plugin, baseid)
        );
    ");
    $updateVersion("descriptions",20241114001);
    Logger::info("Applied patch descriptions 20241114001");
}

if ($checkVersion("descriptions_custom")<20241114001) {
    Logger::debug("Applying descriptions_custom 20241114001");
    $db->execQuery("
        CREATE TABLE IF NOT EXISTS public.descriptions_custom (
            plugin text NOT NULL DEFAULT '',
            baseid character varying(128) NOT NULL,
            name text,
            description text,
            PRIMARY KEY (plugin, baseid)
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

if ($checkVersion("descriptions_schema_plugin_column")<20260611005) {
    Logger::debug("Applying descriptions_schema_plugin_column 20260611005");

    foreach (['descriptions', 'descriptions_custom'] as $tableName) {
        $db->execQuery("ALTER TABLE public.{$tableName} ADD COLUMN IF NOT EXISTS plugin text NOT NULL DEFAULT ''");
        $db->execQuery("ALTER TABLE public.{$tableName} DROP CONSTRAINT IF EXISTS {$tableName}_pkey");
        $db->execQuery("
            UPDATE public.{$tableName}
               SET plugin = split_part(baseid, '|', 1),
                   baseid = split_part(baseid, '|', 2)
             WHERE plugin = ''
               AND position('|' in baseid) > 0
        ");
        $db->execQuery("
            DELETE FROM public.{$tableName} a
             USING public.{$tableName} b
             WHERE a.ctid < b.ctid
               AND a.plugin = b.plugin
               AND a.baseid = b.baseid
        ");
        $db->execQuery("ALTER TABLE public.{$tableName} ADD PRIMARY KEY (plugin, baseid)");
    }

    $updateVersion("descriptions_schema_plugin_column", 20260611005);
    Logger::info("Applied patch descriptions_schema_plugin_column 20260611005");
}

if ($checkVersion("descriptions_defaults")<20260611005) {
    Logger::debug("Applying descriptions_defaults 20260611005");

    $sqlFile = __DIR__ . '/../data/descriptions_20241114001.sql';
    if (file_exists($sqlFile)) {
        $sql = file_get_contents($sqlFile);
        if ($sql !== false) {
            $sql = str_replace(
                "ON CONFLICT (plugin, baseid) DO NOTHING;",
                "ON CONFLICT (plugin, baseid) DO UPDATE SET name = EXCLUDED.name, description = EXCLUDED.description;",
                $sql
            );
            $db->execQuery($sql);
            Logger::info("Refreshed descriptions from descriptions_20241114001.sql");
        } else {
            Logger::warn("Could not read descriptions_20241114001.sql for descriptions default refresh");
        }
    } else {
        Logger::warn("descriptions_20241114001.sql not found at $sqlFile for descriptions default refresh");
    }

    // Remove old default runtime keys that would otherwise be checked before plugin-aware keys.
    // User edits live in descriptions_custom and are intentionally left untouched.
    $db->execQuery("
        WITH obsolete_runtime AS (
            SELECT baseid AS old_baseid,
                   CASE
                       WHEN UPPER(SUBSTRING(baseid FROM 1 FOR 2)) = '00'
                           THEN 'Skyrim.esm|' || UPPER(baseid)
                       WHEN UPPER(SUBSTRING(baseid FROM 1 FOR 2)) = '02'
                           THEN 'Dawnguard.esm|00' || UPPER(SUBSTRING(baseid FROM 3))
                       WHEN UPPER(SUBSTRING(baseid FROM 1 FOR 2)) = '03'
                           THEN 'HearthFires.esm|00' || UPPER(SUBSTRING(baseid FROM 3))
                       WHEN UPPER(SUBSTRING(baseid FROM 1 FOR 2)) = '04'
                           THEN 'Dragonborn.esm|00' || UPPER(SUBSTRING(baseid FROM 3))
                       ELSE baseid
                   END AS stable_baseid
              FROM public.descriptions
             WHERE UPPER(baseid) ~ '^(00|02|03|04)[0-9A-F]{6}$'
        )
        DELETE FROM public.descriptions d
         USING obsolete_runtime o
         WHERE d.baseid = o.old_baseid
           AND d.plugin = ''
           AND d.baseid <> o.stable_baseid
           AND EXISTS (
               SELECT 1
                 FROM public.descriptions stable
                WHERE stable.plugin || '|' || stable.baseid = o.stable_baseid
           )
    ");

    // Legacy wildcard defaults are safe to remove only when the refreshed stable row is identical.
    $db->execQuery("
        DELETE FROM public.descriptions old_default
         USING public.descriptions stable
         WHERE UPPER(old_default.baseid) ~ '^(XX[0-9A-F]{6}|FEXXX[0-9A-F]{3})$'
           AND old_default.plugin = ''
           AND stable.plugin <> ''
           AND stable.name IS NOT DISTINCT FROM old_default.name
           AND stable.description IS NOT DISTINCT FROM old_default.description
    ");

    $updateVersion("descriptions_defaults", 20260611005);
    Logger::info("Applied patch descriptions_defaults 20260611005");
}

// Always (re)create combined view once base tables exist
try {
    $db->execQuery("DROP VIEW IF EXISTS public.combined_descriptions CASCADE;");
    $db->execQuery("
        CREATE VIEW public.combined_descriptions AS
        SELECT c.plugin,
               c.baseid,
               c.name,
               c.description
          FROM public.descriptions_custom c
        UNION ALL
        SELECT i.plugin,
               i.baseid,
               i.name,
               i.description
          FROM (public.descriptions i
                LEFT JOIN public.descriptions_custom c
                  ON ((i.plugin)::text = (c.plugin)::text
                 AND (i.baseid)::text = (c.baseid)::text))
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
           FROM public.memory
          WHERE memory.message !~~ 'Dear Diary%'::text AND memory.message <> ''::text and event<>'backgroundlife_diary'::text
        UNION
         SELECT (((('(Context Location:'::text || speech.location) || ') '::text) || speech.speaker) || ': '::text) || speech.speech,
            speech.rowid::integer AS rowid,
            speech.gamets,
            speech.speaker,
            speech.listener,
            speech.ts
           FROM public.speech
          WHERE speech.speech <> ''::text
        UNION
         SELECT eventlog.data,
            eventlog.rowid::integer AS rowid,
            eventlog.gamets,
            '-'::text AS text,
            '-'::text AS listener,
            eventlog.ts
           FROM public.eventlog
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

if ($checkVersion("import_rules") < 20260725001) {
    try {
        $db->execQuery("ALTER TABLE public.import_rules ADD COLUMN IF NOT EXISTS match_faction text");
        $updateVersion("import_rules", 20260725001);
        Logger::info("Applied patch import_rules 20260725001 - add faction matching");
    } catch (Exception $e) {
        Logger::error("Failed to apply patch import_rules 20260725001: " . $e->getMessage());
    }
}

// Usage column
$db->execQuery("ALTER TABLE public.audit_request ADD COLUMN IF NOT EXISTS usage jsonb");

if ($checkTableExists("rumors") == -1) {
    $db->execQuery(file_get_contents(__DIR__."/../data/add_rumors.sql"));
} else
    Logger::info(__FILE__." rumors exists");

$db->execQuery("ALTER TABLE public.rumors ADD COLUMN IF NOT EXISTS rumor_length_days integer");

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


if ($checkTableExists("sneq_quests") == -1) {
    $db->execQuery(file_get_contents(__DIR__."/../data/sneq_quests.sql"));
} else
    Logger::info(__FILE__." sneq_quests exists");

    
if ($checkTableExists("sneq_quests_saved") == -1) {
    $db->execQuery(file_get_contents(__DIR__."/../data/sneq_quests_saved.sql"));
} else
    Logger::info(__FILE__." sneq_quests_saved exists");

$questEngineSchemaSql = file_get_contents(__DIR__."/../data/chim_quest_engine.sql");
if ($checkTableExists("skyrim_quest_definitions") == -1 || $checkTableExists("chim_quest_definitions") != -1) {
    $db->execQuery($questEngineSchemaSql);
} else {
    Logger::info(__FILE__." skyrim_quest_definitions exists");
    $db->execQuery($questEngineSchemaSql);
}

if ($checkTableExists("skyrim_quest_definitions") != -1) {
    require_once(__DIR__ . "/../lib/chim_quest_engine.php");
    try {
        $questDefinitionCount = $db->fetchOne("SELECT COUNT(*) AS n FROM public.skyrim_quest_definitions");
        $questDefinitionSeedVersion = 20260628003;
        if (intval($questDefinitionCount["n"] ?? 0) === 0 || $checkVersion("skyrim_quest_definitions") < $questDefinitionSeedVersion) {
            $db->execQuery("
                DELETE FROM public.skyrim_quest_definitions
                WHERE source_path LIKE '%/data/chim_quest_engine/definitions/%'
                   OR source_path LIKE '%\\data\\chim_quest_engine\\definitions\\%'
            ");
            $questImportResults = chimQuestEngineImportBundledDefinitions();
            $questImportSuccessCount = 0;
            foreach ($questImportResults as $questImportResult) {
                if (is_array($questImportResult) && !empty($questImportResult["success"])) {
                    $questImportSuccessCount++;
                }
            }
            Logger::info(__FILE__ . " imported bundled skyrim quest definitions: {$questImportSuccessCount}/" . count($questImportResults));
            if ($questImportSuccessCount === count($questImportResults) && $questImportSuccessCount > 0) {
                $updateVersion("skyrim_quest_definitions", $questDefinitionSeedVersion);
            } else {
                Logger::warn(__FILE__ . " bundled skyrim quest definitions import incomplete; version not advanced");
            }
        }
    } catch (Exception $e) {
        Logger::warn(__FILE__ . " could not import bundled skyrim quest definitions: " . $e->getMessage());
    }

}

// Some imported dump-style SQL files clear search_path; restore it before
// running unqualified late-stage migrations.
$db->execQuery("SET search_path TO public");

$db->execQuery("ALTER TABLE public.locations ADD COLUMN IF NOT EXISTS region text");
$db->execQuery("ALTER TABLE public.locations ADD COLUMN IF NOT EXISTS hold text");
$db->execQuery("ALTER TABLE public.locations ADD COLUMN IF NOT EXISTS tags text");
$db->execQuery("ALTER TABLE public.locations ADD COLUMN IF NOT EXISTS factions text");
$db->execQuery("ALTER TABLE public.locations ADD COLUMN IF NOT EXISTS is_interior int");
$db->execQuery("ALTER TABLE public.locations ADD COLUMN IF NOT EXISTS vanilla_location boolean");
$db->execQuery("ALTER TABLE public.locations ADD COLUMN IF NOT EXISTS coords POINT ");
$db->execQuery("ALTER TABLE public.locations ADD COLUMN IF NOT EXISTS refs text");
$db->execQuery("ALTER TABLE public.locations ADD COLUMN IF NOT EXISTS cleared boolean");
$db->execQuery("ALTER TABLE public.locations ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP");
$db->execQuery("ALTER TABLE public.locations ADD COLUMN IF NOT EXISTS world text");
$db->execQuery("ALTER TABLE public.locations ADD COLUMN IF NOT EXISTS chim_added int");
$db->execQuery("
CREATE OR REPLACE VIEW public.locations_v
as
select * FROM  public.locations
where
case 
  when formid=102771 and cleared=FALSE then FALSE -- Dustman's Cairn is closed until The Companions quest, 'Proving Honor' has been activated
  ELSE TRUE
END");

$db->execQuery("ALTER TABLE public.factions ADD COLUMN IF NOT EXISTS vendor_cont TEXT");
$db->execQuery("ALTER TABLE public.factions ADD COLUMN IF NOT EXISTS stock JSONB");
$db->execQuery("ALTER TABLE public.factions ADD COLUMN IF NOT EXISTS gold numeric");
$db->execQuery("ALTER TABLE public.factions ADD COLUMN IF NOT EXISTS player_rank numeric");
$db->execQuery("ALTER TABLE public.factions ADD COLUMN IF NOT EXISTS localts bigint");

$db->execQuery("ALTER TABLE public.sneq_quests ADD COLUMN IF NOT EXISTS title text");
$db->execQuery("ALTER TABLE public.sneq_quests ADD COLUMN IF NOT EXISTS stage text");
$db->execQuery("ALTER TABLE public.named_cell ADD COLUMN IF NOT EXISTS worldspace text");
$db->execQuery("ALTER TABLE public.named_cell ADD COLUMN IF NOT EXISTS closed int");
$db->execQuery("ALTER TABLE public.named_cell ADD COLUMN IF NOT EXISTS door_name text");
$db->execQuery("ALTER TABLE public.named_cell ADD COLUMN IF NOT EXISTS door_x numeric");
$db->execQuery("ALTER TABLE public.named_cell ADD COLUMN IF NOT EXISTS door_y numeric");
$db->execQuery("ALTER TABLE public.named_cell ADD COLUMN IF NOT EXISTS gamets bigint");
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


$db->execQuery("CREATE INDEX IF NOT EXISTS event_log_type ON public.eventlog USING btree (type)");
$db->execQuery("CREATE INDEX IF NOT EXISTS idx_eventlog_people_trgm
ON public.eventlog
USING gin (people gin_trgm_ops)");

$db->execQuery("CREATE INDEX IF NOT EXISTS idx_eventlog_people_trgm2
ON public.eventlog
USING gin (data gin_trgm_ops)");

$db->execQuery("CREATE INDEX IF NOT EXISTS idx_speech_speaker_trgm
ON public.speech
USING gin (speaker gin_trgm_ops)");

$db->execQuery("CREATE INDEX IF NOT EXISTS idx_speech_listener_trgm
ON public.speech
USING gin (listener gin_trgm_ops)");

$db->execQuery("CREATE INDEX IF NOT EXISTS idx_eventlog_gamets_pos
ON public.eventlog (gamets)
WHERE gamets > 0");

$db->execQuery("CREATE INDEX IF NOT EXISTS idx_eventlog_gamets_ts_pos
ON public.eventlog (gamets DESC, ts DESC)");

$db->execQuery("CREATE INDEX IF NOT EXISTS   idx_speech_gamets_pos
ON public.speech (gamets)
WHERE gamets > 0");



//----------------------------------------------------
// Prompts Table - System for managing default and custom prompts
// Version 20251110001
//----------------------------------------------------

try {
    if ($checkTableExists("prompts") == 1) {
        $db->execQuery("DELETE FROM public.prompts WHERE prompt_key IS NULL OR btrim(prompt_key) = ''");
        $db->execQuery("
            DELETE FROM public.prompts a
            USING public.prompts b
            WHERE a.prompt_key = b.prompt_key
              AND a.ctid < b.ctid
        ");
        $db->execQuery("CREATE UNIQUE INDEX IF NOT EXISTS idx_prompts_prompt_key_unique ON public.prompts (prompt_key)");

        $promptCountRow = $db->fetchOne("SELECT COUNT(*) AS count FROM public.prompts");
        $promptCount = intval($promptCountRow["count"] ?? 0);
        if ($promptCount === 0 && $checkVersion("prompts") >= 20251110001) {
            Logger::warn("Prompts table is empty but migration version is marked as applied. Clearing prompts version entry so seed migrations can rerun.");
            $db->execQuery("DELETE FROM public.database_versioning WHERE tablename='prompts'");
        }
    }
} catch (Throwable $e) {
    Logger::warn("Prompts migration self-heal check failed: " . $e->getMessage());
}

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
        "<speech_style>      Text. Speech Style\n".
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
// PLAYER AUTO DIARY FEATURE - Add auto diary toggles
// Version 20260707001
//----------------------------------------------------

if ($checkVersion("core_player")<20260707001) {
    Logger::debug("Applying core_player migration 20260707001 - Adding player auto diary toggles");

    $db->execQuery("
        INSERT INTO public.core_player (id, value)
        VALUES
            ('auto_diary_enabled', '0'),
            ('auto_diary_wait_enabled', '0')
        ON CONFLICT (id) DO NOTHING
    ");

    $updateVersion("core_player", 20260707001);
    Logger::info("Applied patch core_player 20260707001 - Added player auto diary toggles");
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
            'roleplay_name' => 'The Narrator',
            'enabled' => isset($GLOBALS["NARRATOR_TALKS"]) ? ($GLOBALS["NARRATOR_TALKS"] ? '1' : '0') : '1',
            'welcome_enabled' => isset($GLOBALS["NARRATOR_WELCOME"]) ? ($GLOBALS["NARRATOR_WELCOME"] ? '1' : '0') : '0',
            'random_enabled' => isset($GLOBALS["RANDOM_NARATION"]) ? ($GLOBALS["RANDOM_NARATION"] ? '1' : '0') : '0',
            'random_chance' => isset($GLOBALS["RANDOM_NARATION_CHANCE"]) ? (string)intval($GLOBALS["RANDOM_NARATION_CHANCE"]) : '15',
            'random_cooldown' => isset($GLOBALS["RANDOM_NARRATION_COOLDOWN"]) ? (string)intval($GLOBALS["RANDOM_NARRATION_COOLDOWN"]) : '2',
            'books_only_narrator' => isset($GLOBALS["BOOK_EVENT_ALWAYS_NARRATOR"]) ? ($GLOBALS["BOOK_EVENT_ALWAYS_NARRATOR"] ? '1' : '0') : '0',
            'hide_from_context' => isset($GLOBALS["HIDE_NARRATOR_DIALOGUE"]) ? ($GLOBALS["HIDE_NARRATOR_DIALOGUE"] ? '1' : '0') : '1',
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
                'roleplay_name' => 'The Narrator',
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
// NARRATOR DIARY FEATURE - Add diary_enabled toggle
// Version 20260209001
//----------------------------------------------------

if ($checkVersion("core_narrator")<20260209001) {
    Logger::debug("Applying core_narrator migration 20260209001 - Adding diary_enabled toggle");
    
    // Add diary_enabled field (default to disabled)
    $db->execQuery("
        INSERT INTO public.core_narrator (id, value) 
        VALUES ('diary_enabled', '0')
        ON CONFLICT (id) DO NOTHING
    ");
    
    Logger::info("Added diary_enabled to core_narrator (defaults to disabled)");
    
    $updateVersion("core_narrator", 20260209001);
    Logger::info("Applied patch core_narrator 20260209001 - Added diary_enabled toggle");
}

//----------------------------------------------------
// CORE_NARRATOR DEFAULT CHARACTER BACKFILL
// Version 20260417001
//----------------------------------------------------

if ($checkVersion("core_narrator")<20260417001) {
    Logger::debug("Skipping removed core_narrator backfill migration 20260417001");
    $updateVersion("core_narrator", 20260417001);
    Logger::info("Applied patch core_narrator 20260417001 - Backfill removed");
}

//----------------------------------------------------
// NARRATOR AUTO DIARY FEATURE - Add auto_diary_enabled toggle
// Version 20260522001
//----------------------------------------------------

if ($checkVersion("core_narrator")<20260522001) {
    Logger::debug("Applying core_narrator migration 20260522001 - Adding auto_diary_enabled toggle");

    // Add auto_diary_enabled field (default to disabled)
    $db->execQuery("
        INSERT INTO public.core_narrator (id, value)
        VALUES ('auto_diary_enabled', '0')
        ON CONFLICT (id) DO NOTHING
    ");

    Logger::info("Added auto_diary_enabled to core_narrator (defaults to disabled)");

    $updateVersion("core_narrator", 20260522001);
    Logger::info("Applied patch core_narrator 20260522001 - Added auto_diary_enabled toggle");
}

//----------------------------------------------------
// Background Life Prompts - Style prompts for letters and inner thoughts
// Version 20260118001 (fixed: was 20251207001 which was out of order and never applied)
//----------------------------------------------------

if ($checkVersion("prompts")<20260118001) {
    Logger::debug("Applying background life prompts 20260118001");
    
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
    $updateVersion("prompts",20260118001);

    Logger::info("Applied patch prompts 20260118001 - Added background life style prompts to database");
}

//----------------------------------------------------
// Relationship LLM Prompts
// Version 20260125001
//----------------------------------------------------

if ($checkVersion("prompts")<20260125001) {
    Logger::debug("Applying relationship LLM prompts 20260125001");

    // Relationship Analysis Prompt - For parsing TEXT relationships to JSONB
    $relAnalysisPrompt = $db->escape(
'You are a relationship analyzer for Skyrim NPCs. Analyze relationship descriptions and output JSON.

AFFINITY SCALE (-100 to +100, bell curve - extremes are RARE):
+91 to +100: Bonded (soulmates, unbreakable)
+76 to +90: Devoted (deep loyalty/love)
+56 to +75: Fond (genuine affection)
+31 to +55: Friendly (pleasant, helpful)
+6 to +30: Acquaintance (polite nod)
-5 to +5: Neutral (stranger)
-6 to -30: Wary (distrustful)
-31 to -55: Cold (unfriendly)
-56 to -75: Resentful (bitter, grudges)
-76 to -90: Hateful (active malice)
-91 to -100: Hostile (kill on sight)

TYPES: romantic, platonic, familial, professional, rival, enemy, neutral, nemesis, estranged, transactional, protective, indebted, fanatical, mentor, student, servant, client, patron, crush, ex, betrayed, suspicious, admirer, jealous, fearful, obsessed, awed, contempt, pitying, grateful, curious, dismissive

INFERENCE RULES:
1. FACTION: Imperial → add "Stormcloak": -60 enemy. Stormcloak → add "Imperial": -60 enemy.
2. RACIAL: If NPC shows racial attitudes, add race as target (e.g., "Khajit": -40 contempt)
3. OCCUPATION: Thieves Guild → "Guard": -40 rival. Companions → "Silver Hand": -70 enemy.
4. "{PLAYER_NAME}" = Player character. Store as "Player".

OUTPUT (JSON only):
{"relationships": {"Target": {"aff": 50, "type": "professional", "note": "works together"}}}'
    );

    $db->execQuery("
        INSERT INTO public.prompts (prompt_key, default_prompt, description)
        VALUES (
            'rel_llm_analysis',
            '$relAnalysisPrompt',
            'System prompt for relationship LLM analysis - parses TEXT relationships to JSONB format (contains {PLAYER_NAME} placeholder). Used in: ext/relationship_system/relationship_llm.php'
        )
        ON CONFLICT (prompt_key) DO UPDATE SET
            default_prompt = EXCLUDED.default_prompt,
            description = EXCLUDED.description,
            updated_at = CURRENT_TIMESTAMP
    ");

    // Relationship Evaluation Prompt - For evaluating conversations
    $relEvalPrompt = $db->escape(
'You are a behavioral psychologist. Evaluate interactions and provide BRIEF insight.

SPEAKER ATTRIBUTION:
- [PLAYER] and [NPC] tags show who said what
- Only evaluate based on what PLAYER did, not the NPC\'s own words

AFFINITY SCALE (-100 to +100):
- +/-1: Normal chat
- +/-2-3: Notably friendly/rude, small favors
- +/-5-10: Meaningful help, gifts, insults
- +/-15-25: Saving life, violence, betrayal
- +/-50+: Extreme events (killing loved ones, marriage)

MOST INTERACTIONS = 0 or +/-1. Be conservative. Skip trivial exchanges.

REASON FORMAT - Keep it SHORT (under 15 words):
✓ "Teasing triggered defensiveness"
✓ "Genuine interest validates their experience"
✓ "Protective action builds trust"
✗ NOT: Long clinical explanations

TYPE CHANGES (rare - only for defining moments):
- Only change type for: romance confession, betrayal, violence, marriage, family reveal
- Most interactions just adjust affinity, not type

OUTPUT (JSON only):
{"changes": {"Player": {"delta": 1, "reason": "brief insight"}}}

No changes? Return: {"changes": {}}'
    );

    $db->execQuery("
        INSERT INTO public.prompts (prompt_key, default_prompt, description)
        VALUES (
            'rel_llm_evaluation',
            '$relEvalPrompt',
            'System prompt for relationship evaluation - judges affinity changes from conversations. Used in: ext/relationship_system/relationship_llm.php'
        )
        ON CONFLICT (prompt_key) DO UPDATE SET
            default_prompt = EXCLUDED.default_prompt,
            description = EXCLUDED.description,
            updated_at = CURRENT_TIMESTAMP
    ");

    // NPC-to-NPC Evaluation Prompt - For bidirectional NPC conversations
    $relNpc2NpcPrompt = $db->escape(
'You are a behavioral psychologist. Evaluate NPC-to-NPC interaction briefly.

DIRECTION:
- speaker = NPC who SPOKE
- listener = NPC who HEARD
- speaker.delta = speaker\'s feelings toward listener changed?
- listener.delta = listener\'s feelings toward speaker changed?

SCALE: +/-1 typical, +/-2-3 notable, +/-5+ significant. Be conservative.

REASON FORMAT - Under 15 words:
✓ "Dark humor built rapport"
✓ "Bossy tone caused mild resentment"
✓ "Helpful advice appreciated"

OUTPUT - Use exactly "speaker" and "listener":
{"speaker": {"delta": 0, "reason": "brief"}, "listener": {"delta": 1, "reason": "brief"}}

No changes? Return empty objects: {}'
    );

    $db->execQuery("
        INSERT INTO public.prompts (prompt_key, default_prompt, description)
        VALUES (
            'rel_llm_npc_to_npc',
            '$relNpc2NpcPrompt',
            'System prompt for NPC-to-NPC relationship evaluation - bidirectional in single call. Used in: ext/relationship_system/relationship_llm.php'
        )
        ON CONFLICT (prompt_key) DO UPDATE SET
            default_prompt = EXCLUDED.default_prompt,
            description = EXCLUDED.description,
            updated_at = CURRENT_TIMESTAMP
    ");

    $updateVersion("prompts", 20260125001);
    Logger::info("Applied patch prompts 20260125001 - Added relationship LLM prompts");
}

//----------------------------------------------------
// INLINE NARRATION PROMPT
//----------------------------------------------------

if ($checkVersion("prompts")<20260203001) {
    Logger::debug("Applying prompts table 20260203001 - Adding inline_narration_prompt");
    
    $inlineNarrationPrompt = $db->escape(
        "You may include brief third-person narration in asterisks (e.g., *She smiles*) before the dialogue."
    );
    
    $db->execQuery("
        INSERT INTO public.prompts (prompt_key, default_prompt, description)
        VALUES (
            'inline_narration_prompt',
            '$inlineNarrationPrompt',
            'Prompt appended to dialogue instructions when inline narration is enabled. Encourages NPCs to include brief third-person narration in asterisks before dialogue. Used in: prompts/dialogue_prompt.php'
        )
        ON CONFLICT (prompt_key) DO UPDATE SET
            default_prompt = EXCLUDED.default_prompt,
            description = EXCLUDED.description,
            updated_at = CURRENT_TIMESTAMP
    ");
    
    $updateVersion("prompts", 20260203001);
    Logger::info("Applied patch prompts 20260203001 - Added inline_narration_prompt");
}

//----------------------------------------------------
// PLAYER SPEECH STYLE GENERATION PROMPT
//----------------------------------------------------

if ($checkVersion("prompts")<20260327001) {
    Logger::debug("Applying prompts table 20260327001 - Adding player_speech_style_prompt");
    
    $playerSpeechStylePrompt = $db->escape(
        "Generate a practical speech style prompt for {PLAYER_NAME} using recent dialogue and optional guidance. "
        . "Write exactly one paragraph (3-5 sentences) that can be used directly to rewrite player dialogue in roleplay. "
        . "Capture vocabulary, tone, cadence, formality, recurring phrases, and interpersonal style. "
        . "Stay grounded in the dialogue samples and guidance. Do not use bullet points, labels, or headings."
    );
    
    $db->execQuery("
        INSERT INTO public.prompts (prompt_key, default_prompt, description)
        VALUES (
            'player_speech_style_prompt',
            '$playerSpeechStylePrompt',
            'Prompt for generating player speech style from recent player input events and optional user guidance. Supports placeholders: {PLAYER_NAME}, {PLAYER_GUIDANCE}, {CURRENT_SPEECH_STYLE}, {DIALOGUE_SAMPLES}. Used in: ui/cmd/action_player_generate_speech_style.php'
        )
        ON CONFLICT (prompt_key) DO UPDATE SET
            default_prompt = EXCLUDED.default_prompt,
            description = EXCLUDED.description,
            updated_at = CURRENT_TIMESTAMP
    ");
    
    $updateVersion("prompts", 20260327001);
    Logger::info("Applied patch prompts 20260327001 - Added player_speech_style_prompt");
}

//----------------------------------------------------
// BASE DIALOGUE RESPONSE PROMPTS
//----------------------------------------------------

if ($checkVersion("prompts")<20260412001) {
    Logger::debug("Applying prompts table 20260412001 - Adding dialogue response prompts");

    $dialogueLineResponsePrompt = $db->escape(
        " Write {HERIKA_NAME}'s next dialogue line."
        . " Be original, creative, knowledgeable, use your own thoughts. "
        . " Review context history to focus on conversation topic and to avoid repeating sentences and phraseology from previous lines.{MAXIMUM_WORDS}"
    );

    $db->execQuery("
        INSERT INTO public.prompts (prompt_key, default_prompt, description)
        VALUES (
            'dialogue_line_response',
            '$dialogueLineResponsePrompt',
            'Base response instruction used for standard NPC dialogue when inline narration is disabled. Supports placeholders: {HERIKA_NAME}, {MAXIMUM_WORDS}. Used in: prompts/dialogue_prompt.php'
        )
        ON CONFLICT (prompt_key) DO UPDATE SET
            default_prompt = EXCLUDED.default_prompt,
            description = EXCLUDED.description,
            updated_at = CURRENT_TIMESTAMP
    ");

    $dialogueLineInlineResponsePrompt = $db->escape(
        " Write {HERIKA_NAME}'s next prose/narration."
        . " Be original, creative, knowledgeable, use your own thoughts. "
        . " Review context history to focus on conversation topic and to avoid repeating sentences and phraseology from previous lines.{MAXIMUM_WORDS}"
    );

    $db->execQuery("
        INSERT INTO public.prompts (prompt_key, default_prompt, description)
        VALUES (
            'dialogue_line_inline_response',
            '$dialogueLineInlineResponsePrompt',
            'Base response instruction used for NPC dialogue when inline narration is enabled. Supports placeholders: {HERIKA_NAME}, {MAXIMUM_WORDS}. Used in: prompts/dialogue_prompt.php'
        )
        ON CONFLICT (prompt_key) DO UPDATE SET
            default_prompt = EXCLUDED.default_prompt,
            description = EXCLUDED.description,
            updated_at = CURRENT_TIMESTAMP
    ");

    $updateVersion("prompts", 20260412001);
    Logger::info("Applied patch prompts 20260412001 - Added dialogue response prompts");
}

if ($checkVersion("prompts")<20260422001) {
    Logger::debug("Applying prompts table 20260422001 - Adding mode-specific inline narration prompts");

    $legacyInlineNarrationPromptRow = $db->fetchOne("SELECT custom_prompt FROM public.prompts WHERE prompt_key = 'inline_narration_prompt' LIMIT 1");
    $legacyInlineDialoguePromptRow = $db->fetchOne("SELECT custom_prompt FROM public.prompts WHERE prompt_key = 'dialogue_line_inline_response' LIMIT 1");

    $legacyInlineNarrationCustomPrompt = '';
    if ($legacyInlineNarrationPromptRow && isset($legacyInlineNarrationPromptRow['custom_prompt'])) {
        $legacyInlineNarrationCustomPrompt = trim((string)$legacyInlineNarrationPromptRow['custom_prompt']);
    }

    $legacyInlineDialogueCustomPrompt = '';
    if ($legacyInlineDialoguePromptRow && isset($legacyInlineDialoguePromptRow['custom_prompt'])) {
        $legacyInlineDialogueCustomPrompt = trim((string)$legacyInlineDialoguePromptRow['custom_prompt']);
    }

    $legacyInlineNarrationCustomPromptSql = $legacyInlineNarrationCustomPrompt === ''
        ? "NULL"
        : $db->escapeLiteral($legacyInlineNarrationCustomPrompt);
    $legacyInlineDialogueCustomPromptSql = $legacyInlineDialogueCustomPrompt === ''
        ? "NULL"
        : $db->escapeLiteral($legacyInlineDialogueCustomPrompt);

    $dialogueLineInlineResponseNarratorPrompt = $db->escape(
        " Write {HERIKA_NAME}'s next prose/narration."
        . " Be original, creative, knowledgeable, use your own thoughts. "
        . " Review context history to focus on conversation topic and to avoid repeating sentences and phraseology from previous lines.{MAXIMUM_WORDS}"
    );
    $inlineNarrationPromptNarrator = $db->escape(
        "You may include one brief third-person narration block in single asterisks before the dialogue (e.g., *She smiles*). Do not wrap the entire reply in asterisks; keep any spoken dialogue outside the asterisks."
    );
    $dialogueLineInlineResponseNpcPrompt = $db->escape(
        " Write {HERIKA_NAME}'s next dialogue line."
        . " If needed, you may include one brief third-person narration block in single asterisks before the dialogue."
        . " Keep any spoken dialogue outside the asterisks, and do not wrap the entire reply in asterisks."
        . " Be original, creative, knowledgeable, use your own thoughts."
        . " Review context history to focus on conversation topic and to avoid repeating sentences and phraseology from previous lines.{MAXIMUM_WORDS}"
    );
    $inlineNarrationPromptNpc = $db->escape(
        "You may include one brief third-person narration block in single asterisks before the dialogue (e.g., *She smiles softly*). Keep any spoken dialogue outside the asterisks. Do not wrap the entire reply in asterisks."
    );

    $db->execQuery("
        INSERT INTO public.prompts (prompt_key, default_prompt, custom_prompt, description)
        VALUES (
            'dialogue_line_inline_response_narrator',
            '$dialogueLineInlineResponseNarratorPrompt',
            $legacyInlineDialogueCustomPromptSql,
            'Base response instruction used when inline narration mode is narrator. Supports placeholders: {HERIKA_NAME}, {MAXIMUM_WORDS}. Used in: prompts/dialogue_prompt.php'
        )
        ON CONFLICT (prompt_key) DO UPDATE SET
            default_prompt = EXCLUDED.default_prompt,
            custom_prompt = CASE
                WHEN (public.prompts.custom_prompt IS NULL OR public.prompts.custom_prompt = '')
                    AND EXCLUDED.custom_prompt IS NOT NULL
                THEN EXCLUDED.custom_prompt
                ELSE public.prompts.custom_prompt
            END,
            description = EXCLUDED.description,
            updated_at = CURRENT_TIMESTAMP
    ");

    $db->execQuery("
        INSERT INTO public.prompts (prompt_key, default_prompt, custom_prompt, description)
        VALUES (
            'inline_narration_prompt_narrator',
            '$inlineNarrationPromptNarrator',
            $legacyInlineNarrationCustomPromptSql,
            'Additional narration formatting instruction used when inline narration mode is narrator. Used in: prompts/dialogue_prompt.php'
        )
        ON CONFLICT (prompt_key) DO UPDATE SET
            default_prompt = EXCLUDED.default_prompt,
            custom_prompt = CASE
                WHEN (public.prompts.custom_prompt IS NULL OR public.prompts.custom_prompt = '')
                    AND EXCLUDED.custom_prompt IS NOT NULL
                THEN EXCLUDED.custom_prompt
                ELSE public.prompts.custom_prompt
            END,
            description = EXCLUDED.description,
            updated_at = CURRENT_TIMESTAMP
    ");

    $db->execQuery("
        INSERT INTO public.prompts (prompt_key, default_prompt, custom_prompt, description)
        VALUES (
            'dialogue_line_inline_response_npc',
            '$dialogueLineInlineResponseNpcPrompt',
            $legacyInlineDialogueCustomPromptSql,
            'Base response instruction used when inline narration mode is npc. Supports placeholders: {HERIKA_NAME}, {MAXIMUM_WORDS}. Used in: prompts/dialogue_prompt.php'
        )
        ON CONFLICT (prompt_key) DO UPDATE SET
            default_prompt = EXCLUDED.default_prompt,
            custom_prompt = CASE
                WHEN (public.prompts.custom_prompt IS NULL OR public.prompts.custom_prompt = '')
                    AND EXCLUDED.custom_prompt IS NOT NULL
                THEN EXCLUDED.custom_prompt
                ELSE public.prompts.custom_prompt
            END,
            description = EXCLUDED.description,
            updated_at = CURRENT_TIMESTAMP
    ");

    $db->execQuery("
        INSERT INTO public.prompts (prompt_key, default_prompt, custom_prompt, description)
        VALUES (
            'inline_narration_prompt_npc',
            '$inlineNarrationPromptNpc',
            $legacyInlineNarrationCustomPromptSql,
            'Additional narration formatting instruction used when inline narration mode is npc. Used in: prompts/dialogue_prompt.php'
        )
        ON CONFLICT (prompt_key) DO UPDATE SET
            default_prompt = EXCLUDED.default_prompt,
            custom_prompt = CASE
                WHEN (public.prompts.custom_prompt IS NULL OR public.prompts.custom_prompt = '')
                    AND EXCLUDED.custom_prompt IS NOT NULL
                THEN EXCLUDED.custom_prompt
                ELSE public.prompts.custom_prompt
            END,
            description = EXCLUDED.description,
            updated_at = CURRENT_TIMESTAMP
    ");

    $updateVersion("prompts", 20260422001);
    Logger::info("Applied patch prompts 20260422001 - Added mode-specific inline narration prompts");
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

if ($checkVersion("prompts")<20260423001) {
    Logger::debug(" try patch: prompts 20260423001");

    // Fresh installs only seed the consolidated prompt entry.

    $directorSuggestionSystemSingle = $db->escape(
        "You are a game director, and we are roleplaying Skyrim in the Tamriel universe. You must create a instruction for an actor to generate new content/events on game.\n\n"
        . "# Examples\n\n"
        . "user request: actor \"a\" leaves the place \n"
        . "{\"instructions\":[{\n"
        . "  \"character\": \"actor a\",\n"
        . "  \"instruction\": \"actor a should say goodbye to everyone, hinting that they may not return for a long time\",\n"
        . "  \"action\": \"ExitLocation\",\n"
        . "  \"target\": \"everyone\",\n"
        . "  \"scene_note\": \"The mood is somber as actor a prepares to leave. Actor b watches in silence, perhaps with regret or longing.\"\n"
        . "},\n"
        . "{\n"
        . "  \"character\": \"actor b\",\n"
        . "  \"instruction\": \"actor b should say goodbye to b\",\n"
        . "  \"action\": \"JustTalk\",\n"
        . "  \"target\": \"Actor a\",\n"
        . "  \"scene_note\": \"\"\n"
        . "}\n"
        . "]\n"
        . "}\n\n"
        . "(no user request, randomly generated content)\n"
        . "{\"instructions\":[\n"
        . " {\n"
        . "  \"character\": \"actor a\",\n"
        . "  \"instruction\": \"actor a should ask actor b for a few coins, claiming they desperately need a drink.\",\n"
        . "  \"action\": \"Talk\",\n"
        . "  \"target\": \"actor b\",\n"
        . "  \"scene_note\": \"actor a looks disheveled but charming, half-joking and half-serious. Actor b is unsure whether to laugh, help, or walk away.\"\n"
        . " }\n"
        . "]\n"
        . "}\n\n"
        . "Just provide instructions! You can also provide more than one instruction, but one per actor (keep limit at 2 or 3 max actors)\n"
        . "In addition, follow these general scene rules as a game director:\n"
        . " * Use any actor in NEARBY ACTORS/NPC IN THE SCENE list ({PLAYER_NAME}, busy actors and far away actors are excluded)\n"
        . " * Continue the scene as naturally and fully as possible, unless the user explicitly requests a new one. You can specify actions to reinforce the actors' dialogue.\n"
        . " * If there are more actors in the room, try to involve them in the conversation.\n"
        . " * When dialogue becomes repetitive, make a plot twist.\n"
        . " * If a character reuses the same argument too often, nudge the scene towards a new topic.\n"
        . " * Occasionally introduce subtle foreshadowing or hint at future events, dangers, or quests.\n"
        . " * Do not resolve everything neatly - keep room for ongoing tension or future continuation.\n"
        . " * You must always provide dialogue instructions for the character, as every request requires a dialogue response.\n"
        . " * Here are a list of actions that can be used:\n{FUNCTION_LIST}\n  ** JustTalk\n"
        . " * Add a Scene Note: A brief description of the topic, mood, or idea introduced by the instruction. Should serve to guide the desired instruction to become reality.\n"
        . " * If scene is getting boring, add a plot twist"
    );

    $db->execQuery("INSERT INTO public.prompts (prompt_key, default_prompt, description) VALUES ('directorSuggestionSystem', '$directorSuggestionSystemSingle', 'Single prompt-manager entry for rolemaster suggestion generation. Includes system framing, examples, and suggestion rules. Supports {PLAYER_NAME} and {FUNCTION_LIST} placeholders. Used in: service/processors/rolemaster/cmd/suggestion.php') ON CONFLICT (prompt_key) DO UPDATE SET default_prompt = EXCLUDED.default_prompt, description = EXCLUDED.description, updated_at = CURRENT_TIMESTAMP");

    $updateVersion("prompts", 20260423001);
    Logger::info("Applied patch prompts 20260423001 - Added directorSuggestionSystem prompt");
}

//----------------------------------------------------

if ($checkVersion("core_action")<20260430016) {
    Logger::debug(" try patch: core_action 20260430016");

    $description = $db->escape("Send a short freeform director instruction to the server-side director mode so it can stage a scene or event.");
    $returnMessage = $db->escape("The director is preparing a scene instruction.");
    $parametersJson = $db->escape('{"type":"object","required":["target"],"properties":{"target":{"type":"string","description":"REQUIRED: short freeform director brief describing the scene instruction or event to stage."}}}');
    $metadataJson = $db->escape('{"source":"functions.php","status":"active","builtin":true,"dispatch":"server_action"}');

    $db->execQuery("
        INSERT INTO public.core_action (
            code_name,
            action_name,
            description,
            return_message,
            available_to_npc,
            available_to_followers,
            available_to_narrator,
            is_activated,
            parameters_json,
            metadata,
            game_function,
            import_version,
            script_proxy_program
        ) VALUES (
            'DirectorCommand',
            'Director_Command',
            '{$description}',
            '{$returnMessage}',
            FALSE,
            FALSE,
            TRUE,
            TRUE,
            '{$parametersJson}'::jsonb,
            '{$metadataJson}'::jsonb,
            FALSE,
            0,
            NULL
        )
        ON CONFLICT (code_name) DO UPDATE SET
            action_name = EXCLUDED.action_name,
            description = EXCLUDED.description,
            return_message = EXCLUDED.return_message,
            available_to_npc = EXCLUDED.available_to_npc,
            available_to_followers = EXCLUDED.available_to_followers,
            available_to_narrator = EXCLUDED.available_to_narrator,
            is_activated = EXCLUDED.is_activated,
            parameters_json = EXCLUDED.parameters_json,
            metadata = EXCLUDED.metadata,
            game_function = EXCLUDED.game_function,
            import_version = EXCLUDED.import_version,
            script_proxy_program = EXCLUDED.script_proxy_program,
            updated_at = CURRENT_TIMESTAMP
    ");

    $updateVersion("core_action",20260430016);
    Logger::info("Applied patch core_action 20260430016 - Added DirectorCommand narrator server action");
}

//----------------------------------------------------
// Add narrator_bored_prompt to prompts table
// Version 20260430017
//----------------------------------------------------

if ($checkVersion("prompts")<20260430017) {
    Logger::debug("Applying prompts table 20260430017 - Adding narrator_bored_prompt");

    $narratorBoredPrompt = $db->escape(
        "({HERIKA_NAME} makes one short comment directly to {PLAYER_NAME} about something happening right now in the current scene. Keep it grounded in the present moment, do not ask follow-up questions, and do not continue the conversation.) {TEMPLATE_DIALOG}"
    );

    $db->execQuery("
        INSERT INTO public.prompts (prompt_key, default_prompt, description)
        VALUES (
            'narrator_bored_prompt',
            '$narratorBoredPrompt',
            'Prompt for narrator bored events directed at the player (contains {HERIKA_NAME}, {PLAYER_NAME}, and {TEMPLATE_DIALOG} placeholders). Used in: main.php narrator bored routing.'
        )
        ON CONFLICT (prompt_key) DO UPDATE SET
            default_prompt = EXCLUDED.default_prompt,
            description = EXCLUDED.description,
            updated_at = CURRENT_TIMESTAMP
    ");

    $updateVersion("prompts", 20260430017);
    Logger::info("Applied patch prompts 20260430017 - Added narrator_bored_prompt");
}

if ($checkVersion("prompts")<20260502004) {
    Logger::debug("Applying prompts table 20260502004 - Adding managed rechat strict/relaxed prompts");

    $rechatResponsePromptRelaxed1 = $db->escape(
        "Dialogue turn for {HERIKA_NAME}. Respond naturally to whoever just spoke. Address the previous speaker directly. {TEMPLATE_DIALOG}"
    );
    $rechatResponsePromptRelaxed2 = $db->escape(
        "Dialogue turn for {HERIKA_NAME}. Continue the conversation naturally. Address whoever you're actually responding to. {TEMPLATE_DIALOG}"
    );
    $rechatResponsePromptRelaxed3 = $db->escape(
        "Dialogue turn for {HERIKA_NAME}. Focus on one actor - respond to whoever just spoke. {TEMPLATE_DIALOG}"
    );
    $rechatResponsePromptStrict = $db->escape(
        "Dialogue turn for {HERIKA_NAME}. The previous speaker was {PREVIOUS_SPEAKER}. You must respond directly to {PREVIOUS_SPEAKER}."
    );
    $rechatListenerPromptRelaxed = $db->escape(
        "specify who {HERIKA_NAME} is talking to. Address whoever just spoke - can be any person in the conversation."
    );
    $rechatListenerPromptStrict = $db->escape(
        "specify who {HERIKA_NAME} is talking to. The listener must be exactly {PREVIOUS_SPEAKER}. Address the person who just spoke."
    );

    $db->execQuery("
        INSERT INTO public.prompts (prompt_key, default_prompt, description)
        VALUES (
            'rechat_response_prompt_relaxed_1',
            '$rechatResponsePromptRelaxed1',
            'Relaxed rechat cue variant 1. Supports placeholders: {HERIKA_NAME}, {TEMPLATE_DIALOG}. Used in: prompts/prompts.php for standard rechat turns.'
        )
        ON CONFLICT (prompt_key) DO UPDATE SET
            default_prompt = EXCLUDED.default_prompt,
            description = EXCLUDED.description,
            updated_at = CURRENT_TIMESTAMP
    ");

    $db->execQuery("
        INSERT INTO public.prompts (prompt_key, default_prompt, description)
        VALUES (
            'rechat_response_prompt_relaxed_2',
            '$rechatResponsePromptRelaxed2',
            'Relaxed rechat cue variant 2. Supports placeholders: {HERIKA_NAME}, {TEMPLATE_DIALOG}. Used in: prompts/prompts.php for standard rechat turns.'
        )
        ON CONFLICT (prompt_key) DO UPDATE SET
            default_prompt = EXCLUDED.default_prompt,
            description = EXCLUDED.description,
            updated_at = CURRENT_TIMESTAMP
    ");

    $db->execQuery("
        INSERT INTO public.prompts (prompt_key, default_prompt, description)
        VALUES (
            'rechat_response_prompt_relaxed_3',
            '$rechatResponsePromptRelaxed3',
            'Relaxed rechat cue variant 3. Supports placeholders: {HERIKA_NAME}, {TEMPLATE_DIALOG}. Used in: prompts/prompts.php for standard rechat turns.'
        )
        ON CONFLICT (prompt_key) DO UPDATE SET
            default_prompt = EXCLUDED.default_prompt,
            description = EXCLUDED.description,
            updated_at = CURRENT_TIMESTAMP
    ");

    for ($i = 1; $i <= 3; $i++) {
        $db->execQuery("
            INSERT INTO public.prompts (prompt_key, default_prompt, description)
            VALUES (
                'rechat_response_prompt_strict_{$i}',
                '$rechatResponsePromptStrict',
                'Strict rechat cue variant {$i}. Supports placeholders: {HERIKA_NAME}, {PREVIOUS_SPEAKER}. Used in: prompts/prompts.php when strict rechat enforcement is enabled.'
            )
            ON CONFLICT (prompt_key) DO UPDATE SET
                default_prompt = EXCLUDED.default_prompt,
                description = EXCLUDED.description,
                updated_at = CURRENT_TIMESTAMP
        ");
    }

    $db->execQuery("
        INSERT INTO public.prompts (prompt_key, default_prompt, description)
        VALUES (
            'rechat_listener_prompt_relaxed',
            '$rechatListenerPromptRelaxed',
            'Relaxed listener instruction for rechat JSON responses. Supports placeholders: {HERIKA_NAME}. Used in: functions/json_response.php.'
        )
        ON CONFLICT (prompt_key) DO UPDATE SET
            default_prompt = EXCLUDED.default_prompt,
            description = EXCLUDED.description,
            updated_at = CURRENT_TIMESTAMP
    ");

    $db->execQuery("
        INSERT INTO public.prompts (prompt_key, default_prompt, description)
        VALUES (
            'rechat_listener_prompt_strict',
            '$rechatListenerPromptStrict',
            'Strict listener instruction for rechat JSON responses. Supports placeholders: {HERIKA_NAME}, {PREVIOUS_SPEAKER}. Used in: functions/json_response.php when strict rechat enforcement is enabled.'
        )
        ON CONFLICT (prompt_key) DO UPDATE SET
            default_prompt = EXCLUDED.default_prompt,
            description = EXCLUDED.description,
            updated_at = CURRENT_TIMESTAMP
    ");

    $updateVersion("prompts", 20260502004);
    Logger::info("Applied patch prompts 20260502004 - Added managed rechat strict/relaxed prompts");
}

if ($checkVersion("prompts")<20260611001) {
    Logger::debug("Applying prompts table 20260611001 - Adding player respeech prompts");

    $playerRespeechRewritePrompt = $db->escape(
        "Rewrite dialogue for {PLAYER_NAME}, using this text as source \"{PLAYER_NAME}:{SPEECH}\". Use comments between brackets only as guidance for tone, target, length, and verbosity. If the source includes brief narration or stage business before the spoken line, preserve it as one short third-person narration block in single asterisks before the dialogue. Do not repeat bracketed comments or speaker names in the output."
    );
    $playerRespeechOutputPrompt = $db->escape(
        "Output only the rewritten line. If the source includes brief leading narration, keep at most one short leading narration block in single asterisks before the spoken dialogue. Keep spoken dialogue outside the asterisks. No speaker names. No bracketed comments."
    );
    $playerRespeechRewriteStripPrompt = $db->escape(
        "Rewrite dialogue for {PLAYER_NAME}, using this text as source \"{PLAYER_NAME}:{SPEECH}\". Use comments between brackets only as guidance for tone, target, length, and verbosity. Do not repeat bracketed comments, stage directions, narration, asterisked narration, or speaker names in the output."
    );
    $playerRespeechOutputStripPrompt = $db->escape(
        "Output only the final spoken dialogue line. No narration. No stage directions. No asterisked narration. No speaker names. No bracketed comments."
    );

    $db->execQuery("
        INSERT INTO public.prompts (prompt_key, default_prompt, description)
        VALUES (
            'player_respeech_rewrite_prompt',
            '$playerRespeechRewritePrompt',
            'Main player respeech/auto-chat rewrite instruction. Supports placeholders: {PLAYER_NAME}, {SPEECH}. Used in: player_rewrite.php when player auto-chat narration removal is disabled.'
        )
        ON CONFLICT (prompt_key) DO UPDATE SET
            default_prompt = EXCLUDED.default_prompt,
            description = EXCLUDED.description,
            updated_at = CURRENT_TIMESTAMP
    ");

    $db->execQuery("
        INSERT INTO public.prompts (prompt_key, default_prompt, description)
        VALUES (
            'player_respeech_output_prompt',
            '$playerRespeechOutputPrompt',
            'Player respeech/auto-chat output formatting instruction. Supports placeholders: {PLAYER_NAME}, {SPEECH}. Used in: player_rewrite.php when player auto-chat narration removal is disabled.'
        )
        ON CONFLICT (prompt_key) DO UPDATE SET
            default_prompt = EXCLUDED.default_prompt,
            description = EXCLUDED.description,
            updated_at = CURRENT_TIMESTAMP
    ");

    $db->execQuery("
        INSERT INTO public.prompts (prompt_key, default_prompt, description)
        VALUES (
            'player_respeech_rewrite_strip_prompt',
            '$playerRespeechRewriteStripPrompt',
            'Main player respeech/auto-chat rewrite instruction for narration-stripping mode. Supports placeholders: {PLAYER_NAME}, {SPEECH}. Used in: player_rewrite.php when player auto-chat narration removal is enabled.'
        )
        ON CONFLICT (prompt_key) DO UPDATE SET
            default_prompt = EXCLUDED.default_prompt,
            description = EXCLUDED.description,
            updated_at = CURRENT_TIMESTAMP
    ");

    $db->execQuery("
        INSERT INTO public.prompts (prompt_key, default_prompt, description)
        VALUES (
            'player_respeech_output_strip_prompt',
            '$playerRespeechOutputStripPrompt',
            'Player respeech/auto-chat output formatting instruction for narration-stripping mode. Supports placeholders: {PLAYER_NAME}, {SPEECH}. Used in: player_rewrite.php when player auto-chat narration removal is enabled.'
        )
        ON CONFLICT (prompt_key) DO UPDATE SET
            default_prompt = EXCLUDED.default_prompt,
            description = EXCLUDED.description,
            updated_at = CURRENT_TIMESTAMP
    ");

    $updateVersion("prompts", 20260611001);
    Logger::info("Applied patch prompts 20260611001 - Added player respeech prompts");
}

if ($checkVersion("prompts")<20260615001) {
    Logger::debug("Applying prompts table 20260615001 - Adding player diary prompt");

    $playerDiaryPrompt = $db->escape(
        "Write a concise first-person diary entry for {PLAYER_NAME} based on the recent context above. "
        . "Summarize the most relevant recent dialogue, events, decisions, and observations as {PLAYER_NAME}'s private diary. "
        . "Start with the current date and time. Do not write as The Narrator or as any NPC."
    );

    $db->execQuery("
        INSERT INTO public.prompts (prompt_key, default_prompt, description)
        VALUES (
            'player_diary_prompt',
            '$playerDiaryPrompt',
            'Prompt for generating player diary entries. Supports placeholders: {PLAYER_NAME}, #PLAYER_NAME#. Used in: lib/dynamic_update_util.php'
        )
        ON CONFLICT (prompt_key) DO UPDATE SET
            default_prompt = EXCLUDED.default_prompt,
            description = EXCLUDED.description,
            updated_at = CURRENT_TIMESTAMP
    ");

    $updateVersion("prompts", 20260615001);
    Logger::info("Applied patch prompts 20260615001 - Added player_diary_prompt");
}

//----------------------------------------------------

if ($checkVersion("utterance_delivery") < 20260502001) {
    Logger::debug(" try patch: utterance_delivery 20260502001");
    $b_ok = true;

    try {
        $db->execQuery("ALTER TABLE public.eventlog ADD COLUMN IF NOT EXISTS utterance_id TEXT;");
    } catch (Exception $e) {
        $b_ok = false;
        Logger::error("Error altering 'eventlog' table (utterance_id): " . $e->getMessage());
    }

    try {
        $db->execQuery("ALTER TABLE public.eventlog ADD COLUMN IF NOT EXISTS delivery_state TEXT;");
    } catch (Exception $e) {
        $b_ok = false;
        Logger::error("Error altering 'eventlog' table (delivery_state): " . $e->getMessage());
    }

    try {
        $db->execQuery("ALTER TABLE public.speech ADD COLUMN IF NOT EXISTS utterance_id TEXT;");
    } catch (Exception $e) {
        $b_ok = false;
        Logger::error("Error altering 'speech' table (utterance_id): " . $e->getMessage());
    }

    try {
        $db->execQuery("CREATE INDEX IF NOT EXISTS idx_eventlog_utterance_id ON public.eventlog (utterance_id);");
        $db->execQuery("CREATE INDEX IF NOT EXISTS idx_eventlog_delivery_state ON public.eventlog (delivery_state);");
        $db->execQuery("CREATE INDEX IF NOT EXISTS idx_speech_utterance_id ON public.speech (utterance_id);");
    } catch (Exception $e) {
        $b_ok = false;
        Logger::error("Error creating utterance delivery indexes: " . $e->getMessage());
    }

    if ($b_ok) {
        $updateVersion("utterance_delivery", 20260502001);
        Logger::info("Applied patch utterance_delivery 20260502001");
    }
}

//----------------------------------------------------

if ($checkVersion("general_settings") < 20260502002) {
    Logger::debug("Applying general_settings 20260502002 - create database-backed general settings table");
    $b_ok = true;

    try {
        if ($checkTableExists("general_settings") == -1) {
            $db->execQuery(file_get_contents(__DIR__."/../lib/core/database_schema/general_settings.sql"));
            $db->execQuery("SET search_path TO public");
        }
    } catch (Exception $e) {
        $b_ok = false;
        Logger::error("Error ensuring 'general_settings' table: " . $e->getMessage());
    }

    try {
        $db->execQuery("ALTER TABLE public.general_settings ADD COLUMN IF NOT EXISTS description TEXT DEFAULT '';");
        $db->execQuery("ALTER TABLE public.general_settings ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;");
    } catch (Exception $e) {
        $b_ok = false;
        Logger::error("Error altering 'general_settings' table: " . $e->getMessage());
    }

    if ($b_ok) {
        $updateVersion("general_settings", 20260502002);
        Logger::info("Applied patch general_settings 20260502002");
    }
}

//----------------------------------------------------

if ($checkVersion("core_stt_connector") < 20260502002) {
    Logger::debug("Applying core_stt_connector 20260502002 - add api badge and URL support");
    $b_ok = true;

    try {
        if ($checkTableExists("core_stt_connector") == -1) {
            $db->execQuery(file_get_contents(__DIR__."/../lib/core/database_schema/core_stt_connector.sql"));
            $db->execQuery("SET search_path TO public");
        }
    } catch (Exception $e) {
        $b_ok = false;
        Logger::error("Error ensuring 'core_stt_connector' table: " . $e->getMessage());
    }

    try {
        $db->execQuery("ALTER TABLE public.core_stt_connector ADD COLUMN IF NOT EXISTS api_badge_id integer;");
        $db->execQuery("ALTER TABLE public.core_stt_connector ADD COLUMN IF NOT EXISTS url text;");
    } catch (Exception $e) {
        $b_ok = false;
        Logger::error("Error altering 'core_stt_connector' table: " . $e->getMessage());
    }

    try {
        $fkExists = $db->fetchAll("
            SELECT 1
            FROM pg_constraint
            WHERE conname = 'stt_connector_api_badge_id_fkey'
            LIMIT 1
        ");
        if (!$fkExists) {
            $db->execQuery("
                ALTER TABLE public.core_stt_connector
                ADD CONSTRAINT stt_connector_api_badge_id_fkey
                FOREIGN KEY (api_badge_id) REFERENCES public.core_api_badge(id)
            ");
        }
    } catch (Exception $e) {
        $b_ok = false;
        Logger::error("Error ensuring 'core_stt_connector' FK: " . $e->getMessage());
    }

    if ($b_ok) {
        $updateVersion("core_stt_connector", 20260502002);
        Logger::info("Applied patch core_stt_connector 20260502002");
    }
}

//----------------------------------------------------

if ($checkVersion("core_itt_connector") < 20260502002) {
    Logger::debug("Applying core_itt_connector 20260502002 - add api badge and URL support");
    $b_ok = true;

    try {
        if ($checkTableExists("core_itt_connector") == -1) {
            $db->execQuery(file_get_contents(__DIR__."/../lib/core/database_schema/core_itt_connector.sql"));
            $db->execQuery("SET search_path TO public");
        }
    } catch (Exception $e) {
        $b_ok = false;
        Logger::error("Error ensuring 'core_itt_connector' table: " . $e->getMessage());
    }

    try {
        $db->execQuery("ALTER TABLE public.core_itt_connector ADD COLUMN IF NOT EXISTS api_badge_id integer;");
        $db->execQuery("ALTER TABLE public.core_itt_connector ADD COLUMN IF NOT EXISTS url text;");
    } catch (Exception $e) {
        $b_ok = false;
        Logger::error("Error altering 'core_itt_connector' table: " . $e->getMessage());
    }

    try {
        $fkExists = $db->fetchAll("
            SELECT 1
            FROM pg_constraint
            WHERE conname = 'itt_connector_api_badge_id_fkey'
            LIMIT 1
        ");
        if (!$fkExists) {
            $db->execQuery("
                ALTER TABLE public.core_itt_connector
                ADD CONSTRAINT itt_connector_api_badge_id_fkey
                FOREIGN KEY (api_badge_id) REFERENCES public.core_api_badge(id)
            ");
        }
    } catch (Exception $e) {
        $b_ok = false;
        Logger::error("Error ensuring 'core_itt_connector' FK: " . $e->getMessage());
    }

    if ($b_ok) {
        $updateVersion("core_itt_connector", 20260502002);
        Logger::info("Applied patch core_itt_connector 20260502002");
    }
}

//----------------------------------------------------

if ($checkVersion("general_settings") < 20260502003) {
    Logger::debug("Applying general_settings 20260502003 - migrate legacy conf values and active STT/ITT selections");
    $b_ok = true;

    try {
        $managedDescriptions = chimGetManagedGeneralSettingDescriptions();
        foreach (chimGetManagedGeneralSettingIds() as $settingId) {
            $definition = chimGetSchemaDefinition($settingId);
            $hasLegacyValue = chimReadLegacyGlobalValue($settingId, "__CHIM_SETTING_MISSING__");
            if ($hasLegacyValue === "__CHIM_SETTING_MISSING__") {
                if ($settingId === 'FEATURES@MEMORY_EMBEDDING@AUTO_CREATE_SUMMARYS') {
                    $currentValue = true;
                } elseif (array_key_exists('default', $definition)) {
                    $currentValue = $definition['default'];
                } else {
                    continue;
                }
            } else {
                $currentValue = $hasLegacyValue;
            }

            $description = $managedDescriptions[$settingId] ?? chimGetSchemaDescription($settingId);
            if (!chimSetGeneralSetting($settingId, $currentValue, $description)) {
                throw new Exception("Failed writing general setting '{$settingId}'");
            }
        }
    } catch (Exception $e) {
        $b_ok = false;
        Logger::error("Error migrating legacy global settings: " . $e->getMessage());
    }

    try {
        require_once(__DIR__ . "/../lib/core/stt_connector.class.php");
        $sttConnector = new STTConnector();
        $sttRow = $sttConnector->ensureLegacySelectionFromGlobals();
        if ($sttRow && intval($sttRow['id'] ?? 0) > 0) {
            $description = chimGetManagedGeneralSettingDescriptions()['GLOBAL_STT_CONNECTOR_ID'] ?? 'Active global STT connector.';
            if (!chimSetGeneralSetting('GLOBAL_STT_CONNECTOR_ID', intval($sttRow['id']), $description)) {
                throw new Exception("Failed writing GLOBAL_STT_CONNECTOR_ID");
            }
        }
    } catch (Exception $e) {
        $b_ok = false;
        Logger::error("Error migrating legacy STT connector selection: " . $e->getMessage());
    }

    try {
        require_once(__DIR__ . "/../lib/core/itt_connector.class.php");
        $ittConnector = new ITTConnector();
        $ittRow = $ittConnector->ensureLegacySelectionFromGlobals();
        if ($ittRow && intval($ittRow['id'] ?? 0) > 0) {
            $description = chimGetManagedGeneralSettingDescriptions()['GLOBAL_ITT_CONNECTOR_ID'] ?? 'Active global ITT connector.';
            if (!chimSetGeneralSetting('GLOBAL_ITT_CONNECTOR_ID', intval($ittRow['id']), $description)) {
                throw new Exception("Failed writing GLOBAL_ITT_CONNECTOR_ID");
            }
        }
    } catch (Exception $e) {
        $b_ok = false;
        Logger::error("Error migrating legacy ITT connector selection: " . $e->getMessage());
    }

    if ($b_ok) {
        $updateVersion("general_settings", 20260502003);
        Logger::info("Applied patch general_settings 20260502003");
    }
}

if ($checkVersion("general_settings") < 20260502004) {
    Logger::debug("Applying general_settings 20260502004 - add strict rechat response setting");
    $b_ok = true;

    try {
        $settingId = 'ENFORCE_STRICT_RECHAT_RESPONSE';
        $definition = chimGetSchemaDefinition($settingId);
        $hasLegacyValue = chimReadLegacyGlobalValue($settingId, "__CHIM_SETTING_MISSING__");
        $currentValue = ($hasLegacyValue === "__CHIM_SETTING_MISSING__")
            ? ($definition['default'] ?? false)
            : $hasLegacyValue;
        $description = chimGetManagedGeneralSettingDescriptions()[$settingId] ?? chimGetSchemaDescription($settingId);

        if (!chimSetGeneralSetting($settingId, $currentValue, $description)) {
            throw new Exception("Failed writing general setting '{$settingId}'");
        }
    } catch (Exception $e) {
        $b_ok = false;
        Logger::error("Error adding strict rechat response setting: " . $e->getMessage());
    }

    if ($b_ok) {
        $updateVersion("general_settings", 20260502004);
        Logger::info("Applied patch general_settings 20260502004");
    }
}

//----------------------------------------------------

if ($checkVersion("general_settings") < 20260502005) {
    Logger::debug("Applying general_settings 20260502005 - add prompt context options setting");
    $b_ok = true;

    try {
        $settingId = 'PROMPT_CONTEXT_OPTIONS';
        $definition = chimGetSchemaDefinition($settingId);
        $hasLegacyValue = chimReadLegacyGlobalValue($settingId, "__CHIM_SETTING_MISSING__");
        $currentValue = ($hasLegacyValue === "__CHIM_SETTING_MISSING__")
            ? chimGetDefaultPromptContextOptions()
            : chimNormalizePromptContextOptions($hasLegacyValue);
        $description = chimGetManagedGeneralSettingDescriptions()[$settingId] ?? chimGetSchemaDescription($settingId);

        if (!chimSetGeneralSetting($settingId, $currentValue, $description)) {
            throw new Exception("Failed writing general setting '{$settingId}'");
        }
    } catch (Exception $e) {
        $b_ok = false;
        Logger::error("Error adding prompt context options setting: " . $e->getMessage());
    }

    if ($b_ok) {
        $updateVersion("general_settings", 20260502005);
        Logger::info("Applied patch general_settings 20260502005");
    }
}

//----------------------------------------------------

if ($checkVersion("general_settings") < 20260502006) {
    Logger::debug("Applying general_settings 20260502006 - add transformation detection setting");
    $b_ok = true;

    try {
        $settingId = 'TRANSFORMATION_DETECTION';
        $definition = chimGetSchemaDefinition($settingId);
        $hasLegacyValue = chimReadLegacyGlobalValue($settingId, "__CHIM_SETTING_MISSING__");
        $currentValue = ($hasLegacyValue === "__CHIM_SETTING_MISSING__")
            ? ($definition['default'] ?? true)
            : $hasLegacyValue;
        $description = chimGetManagedGeneralSettingDescriptions()[$settingId] ?? chimGetSchemaDescription($settingId);

        if (!chimSetGeneralSetting($settingId, $currentValue, $description)) {
            throw new Exception("Failed writing general setting '{$settingId}'");
        }
    } catch (Exception $e) {
        $b_ok = false;
        Logger::error("Error adding transformation detection setting: " . $e->getMessage());
    }

    if ($b_ok) {
        $updateVersion("general_settings", 20260502006);
        Logger::info("Applied patch general_settings 20260502006");
    }
}

//----------------------------------------------------

if ($checkVersion("core_action") < 20260502001) {
    Logger::debug("Applying core_action 20260502001 - hide Drink and Toast while NPC is sitting");
    $b_ok = true;

    try {
        $syncSittingRestrictions = function ($tableName) use ($db, $checkTableExists) {
            if ($checkTableExists($tableName) == -1) {
                return;
            }

            $rows = $db->fetchAll("
                SELECT id, code_name, metadata
                FROM public.{$tableName}
                WHERE LOWER(code_name) IN ('drink', 'toast')
            ");

            foreach ($rows as $row) {
                $rowId = intval($row['id'] ?? 0);
                if ($rowId <= 0) {
                    continue;
                }

                $metadata = json_decode(strval($row['metadata'] ?? '{}'), true);
                if (!is_array($metadata)) {
                    $metadata = [];
                }

                $requirements = is_array($metadata['requirements'] ?? null) ? $metadata['requirements'] : [];
                $activityRequirements = is_array($requirements['activity'] ?? null) ? $requirements['activity'] : [];
                $currentActionNotIn = $activityRequirements['current_action_not_in'] ?? [];
                if (!is_array($currentActionNotIn)) {
                    $currentActionNotIn = [$currentActionNotIn];
                }

                $normalizedValues = [];
                foreach ($currentActionNotIn as $value) {
                    $normalizedValue = strtolower(trim(strval($value)));
                    if ($normalizedValue !== '') {
                        $normalizedValues[$normalizedValue] = true;
                    }
                }

                if (isset($normalizedValues['sitting'])) {
                    continue;
                }

                $currentActionNotIn[] = 'sitting';
                $activityRequirements['current_action_not_in'] = array_values(array_unique(array_map(
                    function ($value) {
                        return strtolower(trim(strval($value)));
                    },
                    $currentActionNotIn
                )));
                $requirements['activity'] = $activityRequirements;
                $metadata['requirements'] = $requirements;

                $metadataJson = json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                if (!is_string($metadataJson) || $metadataJson === '') {
                    $metadataJson = '{}';
                }

                $metadataLiteral = $db->escapeLiteral($metadataJson);
                $db->execQuery("
                    UPDATE public.{$tableName}
                    SET metadata = {$metadataLiteral}::jsonb,
                        updated_at = NOW()
                    WHERE id = {$rowId}
                ");
            }
        };

        $syncSittingRestrictions('core_action');
        $syncSittingRestrictions('core_action_custom');
    } catch (Exception $e) {
        $b_ok = false;
        Logger::error("Error syncing sitting restrictions for Drink/Toast: " . $e->getMessage());
    }

    if ($b_ok) {
        $updateVersion("core_action", 20260502001);
        Logger::info("Applied patch core_action 20260502001");
    }
}

//----------------------------------------------------

if ($checkVersion("core_action") < 20260502002) {
    Logger::debug("Applying core_action 20260502002 - hide StartRitualCeremony while NPC is sitting");
    $b_ok = true;

    try {
        $syncSittingRestrictions = function ($tableName) use ($db, $checkTableExists) {
            if ($checkTableExists($tableName) == -1) {
                return;
            }

            $rows = $db->fetchAll("
                SELECT id, code_name, metadata
                FROM public.{$tableName}
                WHERE LOWER(code_name) = 'startritualceremony'
            ");

            foreach ($rows as $row) {
                $rowId = intval($row['id'] ?? 0);
                if ($rowId <= 0) {
                    continue;
                }

                $metadata = json_decode(strval($row['metadata'] ?? '{}'), true);
                if (!is_array($metadata)) {
                    $metadata = [];
                }

                $requirements = is_array($metadata['requirements'] ?? null) ? $metadata['requirements'] : [];
                $activityRequirements = is_array($requirements['activity'] ?? null) ? $requirements['activity'] : [];
                $currentActionNotIn = $activityRequirements['current_action_not_in'] ?? [];
                if (!is_array($currentActionNotIn)) {
                    $currentActionNotIn = [$currentActionNotIn];
                }

                $normalizedValues = [];
                foreach ($currentActionNotIn as $value) {
                    $normalizedValue = strtolower(trim(strval($value)));
                    if ($normalizedValue !== '') {
                        $normalizedValues[$normalizedValue] = true;
                    }
                }

                if (isset($normalizedValues['sitting'])) {
                    continue;
                }

                $currentActionNotIn[] = 'sitting';
                $activityRequirements['current_action_not_in'] = array_values(array_unique(array_map(
                    function ($value) {
                        return strtolower(trim(strval($value)));
                    },
                    $currentActionNotIn
                )));
                $requirements['activity'] = $activityRequirements;
                $metadata['requirements'] = $requirements;

                $metadataJson = json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                if (!is_string($metadataJson) || $metadataJson === '') {
                    $metadataJson = '{}';
                }

                $metadataLiteral = $db->escapeLiteral($metadataJson);
                $db->execQuery("
                    UPDATE public.{$tableName}
                    SET metadata = {$metadataLiteral}::jsonb,
                        updated_at = NOW()
                    WHERE id = {$rowId}
                ");
            }
        };

        $syncSittingRestrictions('core_action');
        $syncSittingRestrictions('core_action_custom');
    } catch (Exception $e) {
        $b_ok = false;
        Logger::error("Error syncing sitting restrictions for StartRitualCeremony: " . $e->getMessage());
    }

    if ($b_ok) {
        $updateVersion("core_action", 20260502002);
        Logger::info("Applied patch core_action 20260502002");
    }
}

//----------------------------------------------------

if ($checkVersion("general_settings") < 20260511001) {
    Logger::debug("Applying general_settings 20260511001 - ensure rechat mode defaults to random for new installs");
    $b_ok = true;

    try {
        $settingId = 'RECHAT_MODE';
        $existingRow = chimGetGeneralSettingRow($settingId);

        if (!$existingRow) {
            $definition = chimGetSchemaDefinition($settingId);
            $hasLegacyValue = chimReadLegacyGlobalValue($settingId, "__CHIM_SETTING_MISSING__");

            if ($hasLegacyValue !== "__CHIM_SETTING_MISSING__") {
                $currentValue = $hasLegacyValue;
            } elseif (array_key_exists('OPEN_RECHAT', $GLOBALS)) {
                $currentValue = !empty($GLOBALS['OPEN_RECHAT']) ? 'conversational' : 'tight';
            } else {
                $currentValue = $definition['default'] ?? 'random';
            }

            $description = chimGetManagedGeneralSettingDescriptions()[$settingId] ?? chimGetSchemaDescription($settingId);
            if (!chimSetGeneralSetting($settingId, $currentValue, $description)) {
                throw new Exception("Failed writing general setting '{$settingId}'");
            }
        }
    } catch (Exception $e) {
        $b_ok = false;
        Logger::error("Error ensuring default rechat mode setting: " . $e->getMessage());
    }

    if ($b_ok) {
        $updateVersion("general_settings", 20260511001);
        Logger::info("Applied patch general_settings 20260511001");
    }
}

//----------------------------------------------------

if ($checkVersion("general_settings") < 20260619001) {
    Logger::debug("Applying general_settings 20260619001 - add CHIM AI quest progression settings");
    $b_ok = true;

    try {
        $questSettingDefaults = [
            'CHIM_AI_QUEST_PROGRESSION' => false,
            'CHIM_PLAYER_ONLY_QUEST_ADVANCEMENT' => true,
        ];

        foreach ($questSettingDefaults as $settingId => $fallbackDefault) {
            $existingRow = chimGetGeneralSettingRow($settingId);
            $definition = chimGetSchemaDefinition($settingId);
            $description = chimGetManagedGeneralSettingDescriptions()[$settingId] ?? chimGetSchemaDescription($settingId);

            if ($existingRow) {
                $currentValue = $existingRow['value'] ?? ($definition['default'] ?? $fallbackDefault);
            } else {
                $hasLegacyValue = chimReadLegacyGlobalValue($settingId, "__CHIM_SETTING_MISSING__");
                $currentValue = ($hasLegacyValue === "__CHIM_SETTING_MISSING__")
                    ? ($definition['default'] ?? $fallbackDefault)
                    : $hasLegacyValue;
            }

            if (!chimSetGeneralSetting($settingId, $currentValue, $description)) {
                throw new Exception("Failed writing general setting '{$settingId}'");
            }
        }
    } catch (Exception $e) {
        $b_ok = false;
        Logger::error("Error adding CHIM AI quest progression settings: " . $e->getMessage());
    }

    if ($b_ok) {
        $updateVersion("general_settings", 20260619001);
        Logger::info("Applied patch general_settings 20260619001");
    }
}

//----------------------------------------------------

if ($checkVersion("general_settings") < 20260627001) {
    Logger::debug("Applying general_settings 20260627001 - add player item pickup eventlog threshold");
    $b_ok = true;

    try {
        $settingId = 'CHIM_ITEM_PICKUP_EVENTLOG_MIN_VALUE';
        $existingRow = chimGetGeneralSettingRow($settingId);
        $definition = chimGetSchemaDefinition($settingId);
        $description = chimGetManagedGeneralSettingDescriptions()[$settingId] ?? chimGetSchemaDescription($settingId);

        if ($existingRow) {
            $currentValue = $existingRow['value'] ?? ($definition['default'] ?? 500);
        } else {
            $hasLegacyValue = chimReadLegacyGlobalValue($settingId, "__CHIM_SETTING_MISSING__");
            $currentValue = ($hasLegacyValue === "__CHIM_SETTING_MISSING__")
                ? ($definition['default'] ?? 500)
                : $hasLegacyValue;
        }

        if (!chimSetGeneralSetting($settingId, $currentValue, $description)) {
            throw new Exception("Failed writing general setting '{$settingId}'");
        }
    } catch (Exception $e) {
        $b_ok = false;
        Logger::error("Error adding player item pickup eventlog threshold setting: " . $e->getMessage());
    }

    if ($b_ok) {
        $updateVersion("general_settings", 20260627001);
        Logger::info("Applied patch general_settings 20260627001");
    }
}

//----------------------------------------------------

if ($checkVersion("core_action") < 20260502011) {
    Logger::debug("Applying core_action 20260502011 - sync sitting restrictions for Drink, Toast, and StartRitualCeremony");
    $b_ok = true;

    try {
        $syncSittingRestrictions = function ($tableName) use ($db, $checkTableExists) {
            if ($checkTableExists($tableName) == -1) {
                return;
            }

            $targets = [
                'drink' => ['sitting'],
                'toast' => ['sitting'],
                'startritualceremony' => ['sitting'],
            ];

            $rows = $db->fetchAll("
                SELECT id, code_name, metadata
                FROM public.{$tableName}
                WHERE LOWER(code_name) IN ('drink', 'toast', 'startritualceremony')
            ");

            foreach ($rows as $row) {
                $rowId = intval($row['id'] ?? 0);
                $codeName = strtolower(trim(strval($row['code_name'] ?? '')));
                if ($rowId <= 0 || !isset($targets[$codeName])) {
                    continue;
                }

                $metadata = json_decode(strval($row['metadata'] ?? '{}'), true);
                if (!is_array($metadata)) {
                    $metadata = [];
                }

                $requirements = is_array($metadata['requirements'] ?? null) ? $metadata['requirements'] : [];
                $activityRequirements = is_array($requirements['activity'] ?? null) ? $requirements['activity'] : [];
                $currentActionNotIn = $activityRequirements['current_action_not_in'] ?? [];
                if (!is_array($currentActionNotIn)) {
                    $currentActionNotIn = [$currentActionNotIn];
                }

                $normalizedValues = [];
                foreach ($currentActionNotIn as $value) {
                    $normalizedValue = strtolower(trim(strval($value)));
                    if ($normalizedValue !== '') {
                        $normalizedValues[$normalizedValue] = true;
                    }
                }

                $changed = false;
                foreach ($targets[$codeName] as $requiredValue) {
                    if (!isset($normalizedValues[$requiredValue])) {
                        $currentActionNotIn[] = $requiredValue;
                        $normalizedValues[$requiredValue] = true;
                        $changed = true;
                    }
                }

                if (!$changed) {
                    continue;
                }

                $activityRequirements['current_action_not_in'] = array_values(array_unique(array_map(
                    function ($value) {
                        return strtolower(trim(strval($value)));
                    },
                    $currentActionNotIn
                )));
                $requirements['activity'] = $activityRequirements;
                $metadata['requirements'] = $requirements;

                $metadataJson = json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                if (!is_string($metadataJson) || $metadataJson === '') {
                    $metadataJson = '{}';
                }

                $metadataLiteral = $db->escapeLiteral($metadataJson);
                $db->execQuery("
                    UPDATE public.{$tableName}
                    SET metadata = {$metadataLiteral}::jsonb,
                        updated_at = NOW()
                    WHERE id = {$rowId}
                ");
            }
        };

        $syncSittingRestrictions('core_action');
        $syncSittingRestrictions('core_action_custom');
    } catch (Exception $e) {
        $b_ok = false;
        Logger::error("Error syncing higher-version sitting restrictions: " . $e->getMessage());
    }

    if ($b_ok) {
        $updateVersion("core_action", 20260502011);
        Logger::info("Applied patch core_action 20260502011");
    }
}

if ($checkVersion("core_action") < 20260512001) {
    Logger::debug("Applying core_action 20260512001 - add narrator SpawnGold action");

    $db->execQuery("
        INSERT INTO public.core_action (
            code_name,
            action_name,
            description,
            return_message,
            available_to_npc,
            available_to_followers,
            available_to_narrator,
            is_activated,
            parameters_json,
            metadata,
            game_function,
            import_version,
            script_proxy_program
        ) VALUES (
            'SpawnGold',
            'Spawn_Gold',
            'Create gold and give it to a target actor or #PLAYER_NAME#.',
            '#TARGET# receives #AMOUNT# gold.',
            FALSE,
            FALSE,
            TRUE,
            FALSE,
            '{\"type\":\"object\",\"required\":[\"amount\"],\"properties\":{\"amount\":{\"type\":\"integer\",\"description\":\"REQUIRED: positive integer amount of gold to create and give (max: 1000000).\"},\"target\":{\"type\":\"string\",\"description\":\"Recipient actor. Use #PLAYER_NAME#, PLAYER, or me to give the gold to the player.\"}}}'::jsonb,
            '{\"source\":\"functions.php\",\"status\":\"active\",\"builtin\":true,\"dispatch\":\"rolecommand\"}'::jsonb,
            TRUE,
            0,
            NULL
        )
        ON CONFLICT (code_name) DO UPDATE SET
            action_name = EXCLUDED.action_name,
            description = EXCLUDED.description,
            return_message = EXCLUDED.return_message,
            available_to_npc = EXCLUDED.available_to_npc,
            available_to_followers = EXCLUDED.available_to_followers,
            available_to_narrator = EXCLUDED.available_to_narrator,
            is_activated = EXCLUDED.is_activated,
            parameters_json = EXCLUDED.parameters_json,
            metadata = EXCLUDED.metadata,
            game_function = EXCLUDED.game_function,
            import_version = EXCLUDED.import_version,
            script_proxy_program = EXCLUDED.script_proxy_program,
            updated_at = NOW()
    ");

    $updateVersion("core_action", 20260512001);
    Logger::info("Applied patch core_action 20260512001");
}

if ($checkVersion("core_action") < 20260512002) {
    Logger::debug("Applying core_action 20260512002 - disable narrator-private gameplay actions by default");

    $db->execQuery("
        UPDATE public.core_action
           SET is_activated = FALSE,
               updated_at = NOW()
         WHERE code_name IN (
            'CreateNewNPC',
            'DirectorCommand',
            'KillTarget',
            'SpawnGold',
            'SpawnItem',
            'SpawnNPC',
            'TeleportNPC'
         )
    ");

    $updateVersion("core_action", 20260512002);
    Logger::info("Applied patch core_action 20260512002");
}

if ($checkVersion("core_action") < 20260610001) {
    Logger::debug("Applying core_action 20260610001 - make MoveTo actor-only");

    $db->execQuery("
        UPDATE public.core_action
           SET description = 'Move to a visible nearby actor or NPC. Use TravelTo for places, buildings, cities, doors, or locations.',
               return_message = '#HERIKA_NAME# moves to #TARGET#.',
               parameters_json = '{\"type\":\"object\",\"required\":[\"target\"],\"properties\":{\"target\":{\"enum\":[],\"type\":\"string\",\"description\":\"Visible nearby target NPC, actor, or being. Do not use this for places, buildings, cities, doors, or locations.\"}}}'::jsonb,
               updated_at = NOW()
         WHERE code_name = 'MoveTo'
    ");

    $db->execQuery("
        UPDATE public.core_action_custom
           SET description = 'Move to a visible nearby actor or NPC. Use TravelTo for places, buildings, cities, doors, or locations.',
               return_message = '#HERIKA_NAME# moves to #TARGET#.',
               parameters_json = '{\"type\":\"object\",\"required\":[\"target\"],\"properties\":{\"target\":{\"enum\":[],\"type\":\"string\",\"description\":\"Visible nearby target NPC, actor, or being. Do not use this for places, buildings, cities, doors, or locations.\"}}}'::jsonb,
               updated_at = NOW()
         WHERE code_name = 'MoveTo'
           AND (
                description = 'Move to a visible building or visible actor, also used to guide #PLAYER_NAME# to an actor or building.'
                OR return_message = 'Walk to a visible building or visible actor, also used to guide #PLAYER_NAME# to an actor or building.'
           )
    ");

    $updateVersion("core_action", 20260610001);
    Logger::info("Applied patch core_action 20260610001");
}

if ($checkVersion("core_action") < 20260610002) {
    Logger::debug("Applying core_action 20260610002 - clarify TravelTo long-distance targets");

    $db->execQuery("
        UPDATE public.core_action
           SET description = 'Travel long distance to a building, city, door or other location. Also known as lead the way.',
               parameters_json = '{\"type\":\"object\",\"required\":[\"location\"],\"properties\":{\"location\":{\"type\":\"string\",\"description\":\"Building, city, door, or other location to travel to.\"}}}'::jsonb,
               updated_at = NOW()
         WHERE code_name = 'TravelTo'
    ");

    $db->execQuery("
        UPDATE public.core_action_custom
           SET description = 'Travel long distance to a building, city, door or other location. Also known as lead the way.',
               parameters_json = '{\"type\":\"object\",\"required\":[\"location\"],\"properties\":{\"location\":{\"type\":\"string\",\"description\":\"Building, city, door, or other location to travel to.\"}}}'::jsonb,
               updated_at = NOW()
         WHERE code_name = 'TravelTo'
           AND (
                description = 'Only use if #PLAYER_NAME# explicitly suggests it. Guide #PLAYER_NAME# to a town or city. Also known as lead the way.'
                OR description = 'Use it to move to major locations and landmarks and POIs.'
           )
    ");

    $updateVersion("core_action", 20260610002);
    Logger::info("Applied patch core_action 20260610002");
}

if ($checkVersion("core_action") < 20260610003) {
    Logger::debug("Applying core_action 20260610003 - deactivate legacy LeadTheWayTo");

    $db->execQuery("
        UPDATE public.core_action
           SET is_activated = FALSE,
               metadata = jsonb_set(COALESCE(metadata, '{}'::jsonb), '{status}', '\"inactive\"'::jsonb, true),
               updated_at = NOW()
         WHERE code_name = 'LeadTheWayTo'
    ");

    $db->execQuery("
        UPDATE public.core_action_custom
           SET is_activated = FALSE,
               metadata = jsonb_set(COALESCE(metadata, '{}'::jsonb), '{status}', '\"inactive\"'::jsonb, true),
               updated_at = NOW()
         WHERE code_name = 'LeadTheWayTo'
    ");

    $updateVersion("core_action", 20260610003);
    Logger::info("Applied patch core_action 20260610003");
}

if ($checkVersion("core_action") < 20260803001) {
    Logger::debug("Applying core_action 20260803001 - add held item handoff action");

    $migrationOk = $db->execQuery("
        INSERT INTO public.core_action (
            code_name, action_name, description, return_message,
            available_to_npc, available_to_followers, available_to_narrator,
            is_activated, parameters_json, metadata, game_function,
            import_version, script_proxy_program
        ) VALUES (
            'TakeHeldItem',
            'Take_Held_Item',
            '#HERIKA_NAME# accepts one exact physical item currently held by #PLAYER_NAME#. Use only the exact RefID:ItemName shown in <held_items>. Do not use this for equipped or inventory-only items.',
            '#HERIKA_NAME# accepts #ITEM# from #PLAYER_NAME#.',
            TRUE, TRUE, FALSE, TRUE,
            '{\"type\":\"object\",\"required\":[\"item\"],\"properties\":{\"item\":{\"type\":\"string\",\"description\":\"REQUIRED: Exact RefID:ItemName from <held_items>.\"}}}'::jsonb,
            '{\"source\":\"functions.php\",\"status\":\"active\",\"builtin\":true,\"dispatch\":\"plugin_command\",\"confirmation\":{\"default_policy\":\"ask\"},\"followup\":{\"enabled\":false}}'::jsonb,
            TRUE, 0, NULL
        )
        ON CONFLICT (code_name) DO UPDATE SET
            action_name = EXCLUDED.action_name,
            description = EXCLUDED.description,
            return_message = EXCLUDED.return_message,
            available_to_npc = EXCLUDED.available_to_npc,
            available_to_followers = EXCLUDED.available_to_followers,
            available_to_narrator = EXCLUDED.available_to_narrator,
            is_activated = EXCLUDED.is_activated,
            parameters_json = EXCLUDED.parameters_json,
            metadata = EXCLUDED.metadata,
            game_function = EXCLUDED.game_function,
            import_version = EXCLUDED.import_version,
            script_proxy_program = EXCLUDED.script_proxy_program,
            updated_at = NOW()
    ") !== false;

    if ($migrationOk) {
        $updateVersion("core_action", 20260803001);
        Logger::info("Applied patch core_action 20260803001");
    } else {
        Logger::error("Failed to apply patch core_action 20260803001");
    }
}

//----------------------------------------------------

// Relationship Evaluation and Initialization Queues
$db->execQuery("CREATE TABLE IF NOT EXISTS public.relationship_eval_queue (
                id SERIAL PRIMARY KEY,
                npc_id INTEGER NOT NULL UNIQUE,
                eval_data JSONB NOT NULL,
                created_at TIMESTAMP DEFAULT NOW()  )");

$db->execQuery("CREATE TABLE IF NOT EXISTS public.relationship_init_queue (
                id SERIAL PRIMARY KEY,
                npc_id INTEGER NOT NULL UNIQUE,
                init_data JSONB NOT NULL,
                created_at TIMESTAMP DEFAULT NOW()  )");

$db->execQuery("
            ALTER TABLE public.relationship_init_queue
            ADD COLUMN IF NOT EXISTS retry_count INTEGER DEFAULT 0
        ");
$db->execQuery("
            ALTER TABLE public.relationship_init_queue
            ADD COLUMN IF NOT EXISTS last_error TEXT
        ");
$db->execQuery("
            ALTER TABLE public.relationship_eval_queue
            ADD COLUMN IF NOT EXISTS retry_count INTEGER DEFAULT 0
        ");

//----------------------------------------------------
// Refresh base bio template relationship metadata from canonical SQL
// Version 20260619001
//----------------------------------------------------
$relationshipMetadataNeedsRefresh = $checkVersion("bio_templates_relationship_refresh") < 20260619001;
if (!$relationshipMetadataNeedsRefresh) {
    try {
        $relationshipSentinel = $db->fetchOne("SELECT relationships FROM public.bio_templates WHERE npc_name = 'corpulus_vinius' LIMIT 1");
        $sentinelRelationships = ltrim(trim((string)($relationshipSentinel['relationships'] ?? '')));
        if ($sentinelRelationships === '' || $sentinelRelationships[0] !== '{') {
            $relationshipMetadataNeedsRefresh = true;
            Logger::warn("Reapplying bio_templates relationship metadata because sentinel row corpulus_vinius is stale.");
        }
    } catch (Throwable $e) {
        $relationshipMetadataNeedsRefresh = true;
        Logger::warn("Reapplying bio_templates relationship metadata because sentinel verification failed: " . $e->getMessage());
    }
}

if ($relationshipMetadataNeedsRefresh) {
    Logger::debug("Applying bio_templates_relationship_refresh 20260619001");
    try {
        $splitMergedIceMageTemplate = function($tableName) use ($db, $checkTableExists) {
            if (!in_array($tableName, ["bio_templates", "bio_templates_custom"], true) || $checkTableExists($tableName) == -1) {
                return;
            }

            foreach (["ice_mage", "ice_wizard"] as $targetName) {
                $targetEscaped = $db->escape($targetName);
                $db->execQuery("
                    INSERT INTO public.{$tableName} (
                        npc_name, oghma_knowledge_tags, core, npc_static_bio, appearance, personality,
                        relationships, occupation, skills, speechstyle, goals, voiceid, gender, race, refid
                    )
                    SELECT
                        '{$targetEscaped}', oghma_knowledge_tags, core, npc_static_bio, appearance, personality,
                        relationships, occupation, skills, speechstyle, goals, voiceid, gender, race, refid
                      FROM public.{$tableName}
                     WHERE npc_name = 'ice_mage ice_wizard'
                     LIMIT 1
                    ON CONFLICT (npc_name) DO NOTHING
                ");
            }

            $db->execQuery("DELETE FROM public.{$tableName} WHERE npc_name = 'ice_mage ice_wizard'");
        };

        $splitMergedIceMageTemplate("bio_templates");
        $splitMergedIceMageTemplate("bio_templates_custom");

        $sqlFile = __DIR__ . "/../data/relationship_metadata.sql";
        if (file_exists($sqlFile)) {
            $sqlContent = file_get_contents($sqlFile);
            if ($sqlContent !== false && strlen($sqlContent) > 0) {
                $sqlContent = preg_replace('/^\xEF\xBB\xBF/', '', $sqlContent);
                $refreshResult = $db->execQuery($sqlContent);
                $cleanupResult = false;
                if ($refreshResult) {
                    $baseCleanupResult = $db->execQuery("
                        UPDATE public.bio_templates
                           SET relationships = NULL
                         WHERE relationships IS NOT NULL
                           AND btrim(relationships) <> ''
                           AND left(ltrim(relationships), 1) <> '{'
                    ");
                    $customCleanupResult = $db->execQuery("
                        UPDATE public.bio_templates_custom
                           SET relationships = NULL
                         WHERE relationships IS NOT NULL
                           AND btrim(relationships) <> ''
                           AND left(ltrim(relationships), 1) <> '{'
                    ");
                    $cleanupResult = $baseCleanupResult && $customCleanupResult;
                } else {
                    $db->execQuery("ROLLBACK");
                }
                if ($refreshResult && $cleanupResult) {
                    $updateVersion("bio_templates_relationship_refresh", 20260619001);
                    Logger::info("Applied patch bio_templates_relationship_refresh 20260619001");
                } else {
                    Logger::error("Failed to apply bio_templates_relationship_refresh 20260619001 - canonical relationship metadata refresh did not execute cleanly.");
                }
            } else {
                Logger::warn("relationship metadata file is empty: " . $sqlFile);
            }
        } else {
            Logger::warn("relationship metadata file not found: " . $sqlFile);
        }
    } catch (Exception $e) {
        Logger::error("Error applying bio_templates relationship refresh: " . $e->getMessage());
    }
}

if ($checkVersion("memory") < 20260617001) {
    Logger::debug("Applying memory 20260617001 - widen localts to bigint (avoids int4 overflow on long-running games / Y2038)");

    $db->execQuery("ALTER TABLE public.memory ALTER COLUMN localts TYPE bigint");

    $updateVersion("memory", 20260617001);
    Logger::info("Applied patch memory 20260617001");
}

if ($checkVersion("bgl_history") < 20260623001) {
    Logger::debug("Applying bgl_history 20260623001 - create BgL history table");

    $db->execQuery("
        CREATE TABLE IF NOT EXISTS public.bgl_history (
            rowid bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
            npc varchar,
            gamets bigint,
            ts bigint,
            localts bigint,
            data varchar
        )
    ");

    $updateVersion("bgl_history", 20260623001);
    Logger::info("Applied patch bgl_history 20260623001");

}

if ($checkVersion("bgl_history") < 20260729001) {
    Logger::debug("Applying bgl_history 20260729001 - create BgL history table");

    $db->execQuery("
        ALTER TABLE public.bgl_history ADD COLUMN category TEXT DEFAULT NULL
    ");

    $updateVersion("bgl_history", 20260729001);
    Logger::info("Applied patch bgl_history 20260729001");

}

if ($checkVersion("oghma") < 20260625001) {
    Logger::debug("Applying oghma 20260625001 - ensure topic has a unique constraint for upserts");

    $db->execQuery("DELETE FROM public.oghma WHERE topic IS NULL");

    $db->execQuery("
        DELETE FROM public.oghma o
        USING (
            SELECT ctid
            FROM (
                SELECT
                    ctid,
                    row_number() OVER (
                        PARTITION BY topic
                        ORDER BY
                            CASE WHEN topic_desc IS NOT NULL AND btrim(topic_desc) <> '' THEN 1 ELSE 0 END DESC,
                            CASE WHEN topic_desc_basic IS NOT NULL AND btrim(topic_desc_basic) <> '' THEN 1 ELSE 0 END DESC,
                            CASE WHEN knowledge_class IS NOT NULL AND btrim(knowledge_class) <> '' THEN 1 ELSE 0 END DESC,
                            CASE WHEN knowledge_class_basic IS NOT NULL AND btrim(knowledge_class_basic) <> '' THEN 1 ELSE 0 END DESC,
                            CASE WHEN tags IS NOT NULL AND btrim(tags) <> '' THEN 1 ELSE 0 END DESC,
                            CASE WHEN category IS NOT NULL AND btrim(category) <> '' THEN 1 ELSE 0 END DESC,
                            ctid
                    ) AS rn
                FROM public.oghma
            ) ranked
            WHERE rn > 1
        ) dupes
        WHERE o.ctid = dupes.ctid
    ");

    $db->execQuery("
        DO $$
        DECLARE
            topic_attnum smallint;
        BEGIN
            SELECT a.attnum
              INTO topic_attnum
              FROM pg_attribute a
             WHERE a.attrelid = 'public.oghma'::regclass
               AND a.attname = 'topic'
               AND a.attnum > 0
               AND NOT a.attisdropped;

            IF NOT EXISTS (
                SELECT 1
                FROM pg_index i
                JOIN pg_class t ON t.oid = i.indrelid
                JOIN pg_namespace n ON n.oid = t.relnamespace
                WHERE n.nspname = 'public'
                  AND t.relname = 'oghma'
                  AND i.indisunique
                  AND i.indpred IS NULL
                  AND i.indnkeyatts = 1
                  AND i.indkey::text = topic_attnum::text
            ) THEN
                CREATE UNIQUE INDEX oghma_topic_unique_idx ON public.oghma (topic);
            END IF;
        END
        $$;
    ");

    $updateVersion("oghma", 20260625001);
    Logger::info("Applied patch oghma 20260625001");
}

if ($checkVersion("oghma_aliases") < 20260725001) {
    Logger::debug("Applying oghma_aliases 20260725001 - add static Oghma aliases and rebuild search vectors");

    $db->execQuery("ALTER TABLE public.oghma ADD COLUMN IF NOT EXISTS aliases text DEFAULT '' NOT NULL");
    $db->execQuery('UPDATE public.oghma SET native_vector = ' . chimOghmaNativeVectorSql());
    $db->execQuery('CREATE INDEX IF NOT EXISTS oghma_native_vector_idx ON public.oghma USING gin (native_vector)');

    $aliasSeed = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'oghma_aliases.csv';
    if (is_file($aliasSeed)) {
        $stats = chimOghmaApplyAliasSeed($db, $aliasSeed);
        Logger::info(
            'Oghma alias seed applied: matched=' . $stats['matched']
            . ', updated=' . $stats['updated']
            . ', reindexed=' . $stats['reindexed']
            . ', rejected=' . $stats['rejected']
        );
    }

    $updateVersion("oghma_aliases", 20260725001);
    Logger::info("Applied patch oghma_aliases 20260725001");
}

if ($checkVersion("core_tts_connector_pockettts_audiocpp") < 20260628001) {
    Logger::debug("Applying core_tts_connector_pockettts_audiocpp 20260628001 - expose audio.cpp PocketTTS metadata");

    if ($checkTableExists("core_tts_connector") != -1) {
        $db->execQuery("
            UPDATE public.core_tts_connector
               SET metadata = jsonb_set(
                    jsonb_set(
                        jsonb_set(
                            COALESCE(metadata, '{}'::jsonb),
                            '{endpoint,description}',
                            to_jsonb('Endpoint URL. DwemerDistro audio.cpp PocketTTS uses port 8086. Python PocketTTS uses dedicated port 8024.'::text),
                            true
                        ),
                        '{api_format}',
                        '{\"type\":\"select\",\"values\":[\"audio_cpp\",\"legacy\"],\"description\":\"PocketTTS API format. Use audio_cpp for the DwemerDistro C++ runtime or legacy for the older Python bridge.\"}'::jsonb,
                        true
                    ),
                    '{model}',
                    '{\"type\":\"string\",\"description\":\"audio.cpp model id. Default: pocket-tts.\"}'::jsonb,
                    true
                )
             WHERE driver = 'pockettts'
        ");
    }

    $updateVersion("core_tts_connector_pockettts_audiocpp", 20260628001);
    Logger::info("Applied patch core_tts_connector_pockettts_audiocpp 20260628001");
}

if ($checkVersion("core_tts_connector_omnivoice") < 20260708001) {
    Logger::debug("Applying core_tts_connector_omnivoice 20260708001 - add OmniVoice default connector");

    $b_ok = true;
    try {
        $db->execQuery("
            INSERT INTO public.core_tts_connector (driver, label, metadata, api_badge_id, url, voice_field)
            SELECT
                'omnivoice',
                'OmniVoice Default',
                '{\"language\":\"en\",\"voicelogic\":\"voicetype\",\"fallback_male\":\"malenord\",\"fallback_female\":\"femalenord\"}'::jsonb,
                NULL,
                'http://127.0.0.1:8021',
                'voiceid'
            WHERE NOT EXISTS (
                SELECT 1
                  FROM public.core_tts_connector
                 WHERE lower(coalesce(label, '')) = 'omnivoice default'
            )
        ");

        $db->execQuery("
            UPDATE public.core_tts_connector
               SET driver = 'omnivoice',
                   url = 'http://127.0.0.1:8021',
                   voice_field = 'voiceid',
                   metadata = COALESCE(metadata, '{}'::jsonb) || '{\"language\":\"en\",\"voicelogic\":\"voicetype\",\"fallback_male\":\"malenord\",\"fallback_female\":\"femalenord\"}'::jsonb
             WHERE lower(coalesce(label, '')) = 'omnivoice default'
        ");
    } catch (Throwable $e) {
        $b_ok = false;
        Logger::error("Error adding OmniVoice default TTS connector: " . $e->getMessage());
    }

    if ($b_ok) {
        $updateVersion("core_tts_connector_omnivoice", 20260708001);
        Logger::info("Applied patch core_tts_connector_omnivoice 20260708001");
    }
}

//----------------------------------------------------
// NARRATOR ROLEPLAY NAME - Prompt-facing narrator alias
// Version 20260714001
//----------------------------------------------------

if ($checkVersion("core_narrator")<20260714001) {
    Logger::debug("Applying core_narrator migration 20260714001 - Adding narrator roleplay name");

    $db->execQuery("
        INSERT INTO public.core_narrator (id, value)
        VALUES ('roleplay_name', 'The Narrator')
        ON CONFLICT (id) DO NOTHING
    ");

    $updateVersion("core_narrator", 20260714001);
    Logger::info("Applied patch core_narrator 20260714001 - Added narrator roleplay name");
}

if ($checkVersion("general_settings") < 20260711001) {
    Logger::debug("Applying general_settings 20260711001 - convert Background Life cooldown from days to hours");

    $b_ok = true;
    try {
        $hoursRow = $db->fetchOne("SELECT value FROM public.general_settings WHERE id = 'BGL_TRIGGER_HOURS' LIMIT 1");
        if (isset($hoursRow['value']) && is_numeric($hoursRow['value'])) {
            $cooldownHours = chimNormalizeBackgroundLifeTriggerHours($hoursRow['value']);
        } else {
            $daysRow = $db->fetchOne("SELECT value FROM public.general_settings WHERE id = 'BGL_TRIGGER_DAYS' LIMIT 1");
            $legacyDays = $daysRow['value'] ?? chimReadLegacyGlobalValue('BGL_TRIGGER_DAYS', null);
            $cooldownHours = is_numeric($legacyDays)
                ? chimConvertBackgroundLifeDaysToHours($legacyDays)
                : 24.0;
        }

        $description = chimGetManagedGeneralSettingDescriptions()['BGL_TRIGGER_HOURS']
            ?? chimGetSchemaDescription('BGL_TRIGGER_HOURS');
        if (!chimSetGeneralSetting('BGL_TRIGGER_HOURS', $cooldownHours, $description)) {
            throw new Exception("Failed writing BGL_TRIGGER_HOURS");
        }
    } catch (Throwable $e) {
        $b_ok = false;
        Logger::error("Error converting Background Life cooldown to hours: " . $e->getMessage());
    }

    if ($b_ok) {
        $updateVersion("general_settings", 20260711001);
        Logger::info("Applied patch general_settings 20260711001");
    }
}

if ($checkVersion("general_settings") < 20260720001) {
    Logger::debug("Applying general_settings 20260720001 - add forced Oghma scene context settings");

    $b_ok = true;
    try {
        foreach ([
            'RACIAL_OGHMA' => true,
            'LOCATION_OGHMA' => true,
        ] as $settingId => $fallbackDefault) {
            $existingRow = chimGetGeneralSettingRow($settingId);
            $definition = chimGetSchemaDefinition($settingId);
            $description = chimGetManagedGeneralSettingDescriptions()[$settingId]
                ?? chimGetSchemaDescription($settingId);

            if ($existingRow) {
                $currentValue = $existingRow['value'] ?? ($definition['default'] ?? $fallbackDefault);
            } else {
                $legacyValue = chimReadLegacyGlobalValue($settingId, "__CHIM_SETTING_MISSING__");
                $currentValue = ($legacyValue === "__CHIM_SETTING_MISSING__")
                    ? ($definition['default'] ?? $fallbackDefault)
                    : $legacyValue;
            }

            if (!chimSetGeneralSetting($settingId, $currentValue, $description)) {
                throw new Exception("Failed writing {$settingId}");
            }
        }
    } catch (Throwable $e) {
        $b_ok = false;
        Logger::error("Error adding forced Oghma scene context settings: " . $e->getMessage());
    }

    if ($b_ok) {
        $updateVersion("general_settings", 20260720001);
        Logger::info("Applied patch general_settings 20260720001");
    }
}

if ($checkVersion("general_settings") < 20260720002) {
    Logger::debug("Applying general_settings 20260720002 - move Oghma controls to global settings");

    $b_ok = true;
    try {
        $defaultProfileMetadata = [];
        $defaultProfile = $db->fetchOne(
            "SELECT metadata FROM public.core_profiles "
            . "WHERE lower(COALESCE(default_npc, '')) IN ('1', 'true', 'yes', 'on') "
            . "ORDER BY id ASC LIMIT 1"
        );
        if (is_array($defaultProfile)) {
            $rawMetadata = $defaultProfile['metadata'] ?? [];
            $defaultProfileMetadata = is_array($rawMetadata)
                ? $rawMetadata
                : (json_decode(strval($rawMetadata), true) ?: []);
        }

        $resolvedValues = [];
        foreach ([
            'OGHMA_INFINIUM' => true,
            'OGHMA_AMOUNT' => '1',
        ] as $settingId => $fallbackDefault) {
            $existingRow = chimGetGeneralSettingRow($settingId);
            $definition = chimGetSchemaDefinition($settingId);
            $description = chimGetManagedGeneralSettingDescriptions()[$settingId]
                ?? chimGetSchemaDescription($settingId);

            if ($existingRow) {
                $currentValue = $existingRow['value'] ?? ($definition['default'] ?? $fallbackDefault);
            } elseif (array_key_exists($settingId, $defaultProfileMetadata)) {
                $currentValue = $defaultProfileMetadata[$settingId];
            } else {
                $legacyValue = chimReadLegacyGlobalValue($settingId, "__CHIM_SETTING_MISSING__");
                $currentValue = ($legacyValue === "__CHIM_SETTING_MISSING__")
                    ? ($definition['default'] ?? $fallbackDefault)
                    : $legacyValue;
            }

            if (!chimSetGeneralSetting($settingId, $currentValue, $description)) {
                throw new Exception("Failed writing {$settingId}");
            }
            $resolvedValues[$settingId] = chimSettingsStringifyValue($currentValue);
        }

        // Keep only meaningful per-profile differences as overrides.
        foreach ($resolvedValues as $settingId => $globalValue) {
            $safeSettingId = $db->escapeLiteral($settingId);
            $safeGlobalValue = $db->escapeLiteral(strtolower(trim($globalValue)));
            if ($db->execQuery(
                "UPDATE public.core_profiles "
                . "SET metadata = COALESCE(metadata, '{}'::jsonb) - {$safeSettingId} "
                . "WHERE COALESCE(metadata, '{}'::jsonb) ? {$safeSettingId} "
                . "AND lower(trim(COALESCE(metadata ->> {$safeSettingId}, ''))) = {$safeGlobalValue}"
            ) === false) {
                throw new Exception("Failed normalizing profile override {$settingId}");
            }
        }
    } catch (Throwable $e) {
        $b_ok = false;
        Logger::error("Error moving Oghma controls to global settings: " . $e->getMessage());
    }

    if ($b_ok) {
        $updateVersion("general_settings", 20260720002);
        Logger::info("Applied patch general_settings 20260720002");
    }
}

if ($checkVersion("quest_asset_library") < 20260718003) {
    Logger::debug("Applying quest_asset_library 20260718003 - add curated quest spawn templates");

    $schemaFile = __DIR__ . "/../data/quest_asset_library.sql";
    $manifestDirectory = __DIR__ . "/../data/quest_assets";
    $migrationOk = is_readable($schemaFile)
        && $db->execQuery(file_get_contents($schemaFile)) !== false;

    if ($migrationOk) {
        require_once __DIR__ . "/../lib/quest_asset_library.php";
        foreach (["skyrim_official.json", "chim_spawn_templates.json"] as $manifestName) {
            $manifestPath = $manifestDirectory . "/" . $manifestName;
            $result = quest_asset_import_manifest_file($manifestPath);
            if (empty($result["success"])) {
                $migrationOk = false;
                Logger::error(
                    "Failed importing quest asset manifest {$manifestName}: "
                    . implode("; ", $result["errors"] ?? ["unknown error"])
                );
                break;
            }
        }
    }

    if ($migrationOk) {
        require_once __DIR__ . "/../lib/quest_reference_data.php";
        $canonicalDefaults = [
            "npc_templates" => [],
            "npc_own_templates" => [],
        ];
        foreach (["skyrim_official.json", "chim_spawn_templates.json"] as $manifestName) {
            $manifestPath = $manifestDirectory . "/" . $manifestName;
            $manifest = json_decode((string) file_get_contents($manifestPath), true);
            foreach (($manifest["groups"] ?? []) as $group) {
                $datasetName = strtolower(trim((string) ($group["dataset"] ?? "")));
                $groupKey = strtolower(trim((string) ($group["key"] ?? "")));
                if (!isset($canonicalDefaults[$datasetName]) || $groupKey === "") {
                    continue;
                }
                foreach (($group["members"] ?? []) as $member) {
                    $stableRef = trim((string) ($member["stable_ref"] ?? ""));
                    if ($stableRef !== "") {
                        $canonicalDefaults[$datasetName][$groupKey][] = $stableRef;
                    }
                }
            }
        }

        foreach ($canonicalDefaults as $datasetName => $valueMap) {
            if (quest_reference_add_missing_dataset_entries($datasetName, $valueMap) === false) {
                $migrationOk = false;
                Logger::error("Failed synchronizing bundled {$datasetName} groups into the canonical quest table");
                break;
            }
        }
    }

    if ($migrationOk) {
        $updateVersion("quest_asset_library", 20260718003);
        Logger::info("Applied patch quest_asset_library 20260718003");
    } else {
        Logger::error("Failed to apply quest_asset_library 20260718003");
    }
}

if ($checkVersion("quest_asset_library") < 20260719001) {
    Logger::debug("Applying quest_asset_library 20260719001 - remove unshipped quest NPC templates");

    require_once __DIR__ . "/../lib/quest_asset_library.php";
    require_once __DIR__ . "/../lib/quest_reference_data.php";

    $migrationOk = true;
    $manifestPath = __DIR__ . "/../data/quest_assets/chim_spawn_templates.json";
    $result = quest_asset_import_manifest_file($manifestPath);
    if (empty($result["success"])) {
        $migrationOk = false;
        Logger::error(
            "Failed importing cleaned quest asset manifest chim_spawn_templates.json: "
            . implode("; ", $result["errors"] ?? ["unknown error"])
        );
    }

    if ($migrationOk && quest_reference_table_exists("quest_npc_own_templates")) {
        $templateKeyFilter = "template_key ~* '^(male|female)_(altmer|bosmer|dunmer|khajiit)(_|$)'";
        $invalidStableRefs = "'aiagent.esp|00045ce7', 'aiagent.esp|00045ce8', "
            . "'aiagent.esp|00045ce9', 'aiagent.esp|00045cea', "
            . "'aiagent.esp|00045ceb', 'aiagent.esp|00045cec', "
            . "'aiagent.esp|00045ced', 'aiagent.esp|00045cee'";
        $invalidHexPattern = "^0x[0-9a-f]{2}045ce[7-9a-e]$";

        if (quest_reference_column_exists("quest_npc_own_templates", "formids_json")) {
            $migrationOk = $db->execQuery("
                UPDATE public.quest_npc_own_templates AS templates
                SET formids_json = (
                    SELECT COALESCE(jsonb_agg(entry.value ORDER BY entry.ordinality), '[]'::jsonb) AS formids_json
                    FROM jsonb_array_elements(COALESCE(templates.formids_json, '[]'::jsonb))
                        WITH ORDINALITY AS entry(value, ordinality)
                    WHERE NOT (
                        lower(entry.value #>> '{}') IN ({$invalidStableRefs})
                        OR lower(entry.value #>> '{}') ~ '{$invalidHexPattern}'
                        OR (
                            (entry.value #>> '{}') ~ '^[0-9]+$'
                            AND (((entry.value #>> '{}')::bigint & 16777215) BETWEEN 285927 AND 285934)
                        )
                    )
                )
                WHERE {$templateKeyFilter}
            ") !== false;

            if ($migrationOk) {
                $migrationOk = $db->execQuery("
                    DELETE FROM public.quest_npc_own_templates
                    WHERE {$templateKeyFilter}
                      AND jsonb_array_length(COALESCE(formids_json, '[]'::jsonb)) = 0
                ") !== false;
            }
        }

        if ($migrationOk && quest_reference_column_exists("quest_npc_own_templates", "formid")) {
            if (quest_reference_formid_column_is_text("quest_npc_own_templates")) {
                $formIdFilter = "lower(trim(formid::text)) IN ({$invalidStableRefs}) "
                    . "OR lower(trim(formid::text)) ~ '{$invalidHexPattern}' "
                    . "OR (trim(formid::text) ~ '^[0-9]+$' "
                    . "AND ((trim(formid::text)::bigint & 16777215) BETWEEN 285927 AND 285934))";
            } else {
                $formIdFilter = "(formid::bigint & 16777215) BETWEEN 285927 AND 285934";
            }

            $migrationOk = $db->execQuery("
                DELETE FROM public.quest_npc_own_templates
                WHERE {$templateKeyFilter}
                  AND ({$formIdFilter})
            ") !== false;
        }
    }

    if ($migrationOk) {
        $updateVersion("quest_asset_library", 20260719001);
        Logger::info("Applied patch quest_asset_library 20260719001");
    } else {
        Logger::error("Failed to apply quest_asset_library 20260719001");
    }
}

if ($checkVersion("quest_asset_library") < 20260729001) {
    Logger::debug("Applying quest_asset_library 20260729001 - restore shipped playable race NPC templates");

    require_once __DIR__ . "/../lib/quest_asset_library.php";
    require_once __DIR__ . "/../lib/quest_reference_data.php";

    $migrationOk = true;
    $manifestPath = __DIR__ . "/../data/quest_assets/chim_spawn_templates.json";
    $result = quest_asset_import_manifest_file($manifestPath);
    if (empty($result["success"])) {
        $migrationOk = false;
        Logger::error(
            "Failed importing playable race NPC templates: "
            . implode("; ", $result["errors"] ?? ["unknown error"])
        );
    }

    if ($migrationOk) {
        $manifest = json_decode((string) file_get_contents($manifestPath), true);
        $spawnTemplates = [];
        foreach (($manifest["groups"] ?? []) as $group) {
            if (strtolower(trim((string) ($group["dataset"] ?? ""))) !== "npc_own_templates") {
                continue;
            }

            $groupKey = strtolower(trim((string) ($group["key"] ?? "")));
            if ($groupKey === "") {
                continue;
            }

            foreach (($group["members"] ?? []) as $member) {
                $stableRef = trim((string) ($member["stable_ref"] ?? ""));
                if ($stableRef !== "") {
                    $spawnTemplates[$groupKey][] = $stableRef;
                }
            }
        }

        $migrationOk = quest_reference_add_missing_dataset_entries(
            "npc_own_templates",
            $spawnTemplates
        ) !== false;
    }

    if ($migrationOk) {
        $updateVersion("quest_asset_library", 20260729001);
        Logger::info("Applied patch quest_asset_library 20260729001");
    } else {
        Logger::error("Failed to apply quest_asset_library 20260729001");
    }
}

if ($checkVersion("prompts") < 20260719001) {
    Logger::debug("Applying prompts 20260719001 - improve book reading prompt");

    $bookSummaryPrompt = $db->escape(
        "Read the provided book as {HERIKA_NAME}. Give a concise, accurate summary based only on the book text included in the current context, then add a brief in-character reaction. Do not invent missing passages, quotations, author details, or lore. If the book text is unavailable, say that you do not see any legible words on the pages. {TEMPLATE_DIALOG}"
    );
    $description = $db->escape(
        "Controls how NPCs summarize and react to books. Editable in Prompts Manager; custom prompts are preserved during updates."
    );

    $db->execQuery("
        INSERT INTO public.prompts (prompt_key, default_prompt, description)
        VALUES ('book_summary_prompt', '{$bookSummaryPrompt}', '{$description}')
        ON CONFLICT (prompt_key) DO UPDATE SET
            default_prompt = EXCLUDED.default_prompt,
            description = EXCLUDED.description,
            updated_at = CURRENT_TIMESTAMP
    ");

    $updateVersion("prompts", 20260719001);
    Logger::info("Applied patch prompts 20260719001 - improved book reading prompt");
}

if ($checkVersion("prompts") < 20260727001) {
    Logger::debug("Applying prompts 20260727001 - add bored event director rules");

    require_once(__DIR__ . "/../lib/rolemaster_bored.php");
    $boredEventRules = $db->escape(chimRolemasterDefaultBoredEventRules());
    $description = $db->escape(
        "Additional Rolemaster rules used only for autonomous bored events. "
        . "Supports {SEED_ACTOR_RULE}, {SEED_ACTOR}, {NEARBY_ACTORS}, and {PLAYER_NAME} placeholders. "
        . "Used in: service/processors/rolemaster/cmd/instruction.php"
    );

    $migrationOk = $db->execQuery("
        INSERT INTO public.prompts (prompt_key, default_prompt, description)
        VALUES ('director_bored_event_rules', '{$boredEventRules}', '{$description}')
        ON CONFLICT (prompt_key) DO UPDATE SET
            default_prompt = EXCLUDED.default_prompt,
            description = EXCLUDED.description,
            updated_at = CURRENT_TIMESTAMP
    ") !== false;

    if ($migrationOk) {
        $updateVersion("prompts", 20260727001);
        Logger::info("Applied patch prompts 20260727001 - added bored event director rules");
    } else {
        Logger::error("Failed to apply patch prompts 20260727001");
    }
}

if ($checkVersion("prompts") < 20260805001) {
    Logger::debug("Applying prompts 20260805001 - keep bored Director dialogue chronological");

    require_once(__DIR__ . "/../lib/rolemaster_bored.php");
    $boredEventRules = $db->escape(chimRolemasterDefaultBoredEventRules());
    $description = $db->escape(
        "Additional Rolemaster rules used only for autonomous bored events. "
        . "Supports {SEED_ACTOR_RULE}, {SEED_ACTOR}, {NEARBY_ACTORS}, and {PLAYER_NAME} placeholders. "
        . "Used in: service/processors/rolemaster/cmd/instruction.php"
    );

    $migrationOk = $db->execQuery("
        INSERT INTO public.prompts (prompt_key, default_prompt, description)
        VALUES ('director_bored_event_rules', '{$boredEventRules}', '{$description}')
        ON CONFLICT (prompt_key) DO UPDATE SET
            default_prompt = EXCLUDED.default_prompt,
            description = EXCLUDED.description,
            updated_at = CURRENT_TIMESTAMP
    ") !== false;

    if ($migrationOk) {
        $updateVersion("prompts", 20260805001);
        Logger::info("Applied patch prompts 20260805001 - kept bored Director dialogue chronological");
    } else {
        Logger::error("Failed to apply patch prompts 20260805001");
    }
}

if ($checkVersion("prompts") < 20260821003) {
    Logger::debug("Applying prompts 20260821003 - ground autonomous bored dialogue");

    require_once(__DIR__ . "/../lib/rolemaster_bored.php");
    $boredSystemPrompt = $db->escape(chimRolemasterDefaultBoredSystemPrompt());
    $systemDescription = $db->escape(
        "System prompt used only for autonomous Smart Bored planning. "
        . "Replaces the general Director system prompt and examples for this route. "
        . "Used in: service/processors/rolemaster/cmd/instruction.php"
    );
    $boredEventRules = $db->escape(chimRolemasterDefaultBoredEventRules());
    $rulesDescription = $db->escape(
        "Complete Rolemaster rules used only for autonomous Smart Bored events. "
        . "Supports {SEED_ACTOR_RULE}, {SEED_ACTOR}, {NEARBY_ACTORS}, {PLAYER_NAME}, and {FUNCTION_LIST} placeholders. "
        . "Replaces the general Director instruction rules for this route. "
        . "Used in: service/processors/rolemaster/cmd/instruction.php"
    );

    $systemPromptOk = $db->execQuery("
        INSERT INTO public.prompts (prompt_key, default_prompt, description)
        VALUES ('director_bored_event_system_prompt', '{$boredSystemPrompt}', '{$systemDescription}')
        ON CONFLICT (prompt_key) DO UPDATE SET
            default_prompt = EXCLUDED.default_prompt,
            description = EXCLUDED.description,
            updated_at = CURRENT_TIMESTAMP
    ") !== false;

    $rulesPromptOk = $db->execQuery("
        INSERT INTO public.prompts (prompt_key, default_prompt, description)
        VALUES ('director_bored_event_rules', '{$boredEventRules}', '{$rulesDescription}')
        ON CONFLICT (prompt_key) DO UPDATE SET
            default_prompt = EXCLUDED.default_prompt,
            description = EXCLUDED.description,
            updated_at = CURRENT_TIMESTAMP
    ") !== false;
    $profileMetadataOk = $db->execQuery("
        UPDATE public.core_profiles
        SET metadata = metadata - 'BORED_EVENT_SERVERSIDE'
        WHERE metadata ? 'BORED_EVENT_SERVERSIDE'
    ") !== false;
    $migrationOk = $systemPromptOk && $rulesPromptOk && $profileMetadataOk;

    if ($migrationOk) {
        $updateVersion("prompts", 20260821003);
        Logger::info("Applied patch prompts 20260821003 - grounded autonomous bored dialogue");
    } else {
        Logger::error("Failed to apply patch prompts 20260821003");
    }
}

if ($checkVersion("memory_summary") < 20260721001) {
    Logger::debug("Applying memory_summary 20260721001 - normalize diary memory owners");

    $migrationOk = $db->execQuery("
        UPDATE public.memory_summary
        SET companions = '|' || trim(both '|' from trim(companions)) || '|'
        WHERE classifier = 'diary'
          AND nullif(trim(companions), '') IS NOT NULL
          AND companions NOT LIKE '|%|'
    ") !== false;

    if ($migrationOk) {
        $updateVersion("memory_summary", 20260721001);
        Logger::info("Applied patch memory_summary 20260721001");
    } else {
        Logger::error("Failed to apply patch memory_summary 20260721001");
    }
}


if ($checkVersion("physical_npc_diaries") < 20260719001) {
    Logger::debug("Applying physical_npc_diaries 20260719001 - add physical NPC diary tracking");

    $schemaPath = __DIR__ . "/../lib/core/database_schema/physical_npc_diaries.sql";
    if ($db->execQuery(file_get_contents($schemaPath))) {
        $updateVersion("physical_npc_diaries", 20260719001);
        Logger::info("Applied patch physical_npc_diaries 20260719001");
    } else {
        Logger::error("Failed to apply patch physical_npc_diaries 20260719001");
    }
}

if ($checkVersion("physical_npc_diaries") < 20260719002) {
    Logger::debug("Applying physical_npc_diaries 20260719002 - remove draft action registration");

    $db->execQuery("DELETE FROM public.core_action WHERE code_name = 'MaterializeDiary'");
    $updateVersion("physical_npc_diaries", 20260719002);
    Logger::info("Applied patch physical_npc_diaries 20260719002");
}

if ($checkVersion("playthrough_schema") < 20260723001) {
    Logger::debug("Applying playthrough_schema 20260723001 - repair stale database sequences");

    $schemaFunctionsPath = __DIR__ . "/../lib/schema_clone_function.sql";
    $migrationOk = is_readable($schemaFunctionsPath)
        && $db->execQuery(file_get_contents($schemaFunctionsPath)) !== false;

    if ($migrationOk) {
        $migrationOk = $db->execQuery(
            "SELECT chim_meta.sync_schema_sequences('public')"
        ) !== false;
    }

    if ($migrationOk) {
        $updateVersion("playthrough_schema", 20260723001);
        Logger::info("Applied patch playthrough_schema 20260723001");
    } else {
        Logger::error("Failed to apply patch playthrough_schema 20260723001");
    }
}

// master Packages update 
if ($checkVersion("master_packages")<20260716002) {
    if ($db->execQuery(file_get_contents(__DIR__."/../data/master_packages_202607.sql"))) {
       $updateVersion("master_packages", 20260716002);
       Logger::info("Applied patch master_packages 20260716002");
    } else {
        Logger::error("Failed to apply patch master_packages 20260716002");
    }

}
if ($checkVersion("master_packages")<20260724002) {
    if ($db->execQuery(file_get_contents(__DIR__."/../data/master_packages_202607-2.sql"))) {
       $updateVersion("master_packages", 20260724002);
       Logger::info("Applied patch master_packages 20260724002");
    } else {
        Logger::error("Failed to apply patch master_packages 20260724002");
    }

}


//----------------------------------------------------
// VISUAL CONTEXT - Persistent image descriptions
// Version 20260718001
//----------------------------------------------------

if ($checkVersion("visual_context") < 20260718001) {
    Logger::debug("Applying visual_context 20260718001 - add persistent visual descriptions");
    require_once(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'visual_context.php');

    $b_ok = chimEnsureVisualContextTable();
    if ($b_ok) {
        chimSetGeneralSetting('VISUAL_CONTEXT_SCENE_TTL_MINUTES', 10, chimGetSchemaDescription('VISUAL_CONTEXT_SCENE_TTL_MINUTES'));
        chimSetGeneralSetting('VISUAL_CONTEXT_PROMPT_MAX_CHARS', 1800, chimGetSchemaDescription('VISUAL_CONTEXT_PROMPT_MAX_CHARS'));
        $updateVersion("visual_context", 20260718001);
        Logger::info("Applied patch visual_context 20260718001");
    } else {
        Logger::error("Failed to apply patch visual_context 20260718001");
    }
}

if ($checkVersion("core_tts_fallback") < 20260727001) {
    Logger::debug("Applying core_tts_fallback 20260727001 - add global race and gender voice fallbacks");

    $schemaPath = __DIR__ . "/../lib/core/database_schema/core_tts_fallback.sql";
    if ($db->execQuery(file_get_contents($schemaPath)) !== false) {
        $updateVersion("core_tts_fallback", 20260727001);
        Logger::info("Applied patch core_tts_fallback 20260727001");
    } else {
        Logger::error("Failed to apply patch core_tts_fallback 20260727001");
    }
}

if ($checkVersion("latest_diary_context") < 20260727001) {
    Logger::debug("Applying latest_diary_context 20260727001 - index latest NPC diary lookups");

    $migrationOk = $db->execQuery(
        "CREATE INDEX IF NOT EXISTS idx_diarylog_people_gamets
         ON public.diarylog (lower(trim(people)), gamets DESC, localts DESC, rowid DESC)"
    ) !== false;

    if ($migrationOk) {
        $updateVersion("latest_diary_context", 20260727001);
        Logger::info("Applied patch latest_diary_context 20260727001");
    } else {
        Logger::error("Failed to apply patch latest_diary_context 20260727001");
    }
}

if ($checkVersion("faction_vanilla") < 20260803001) {
    Logger::debug("Applying faction_vanilla 20260803001 - some description fixes for vanilla factions");

    $migrationOk = $db->execQuery(file_get_contents(__DIR__."/../data/factions_vanilla.sql")) !== false;

    if ($migrationOk) {
        $updateVersion("faction_vanilla", 20260803001);
        Logger::info("Applied patch faction_vanilla 20260803001 - some description fixes for vanilla factions");
    } else {
        Logger::error("Failed to apply patch faction_vanilla 20260803001 - some description fixes for vanilla factions");
    }
}

if ($checkVersion("market_cache") < 20260805001) {
    Logger::debug("Applying market_cache 20260805001 - initial market cache setup");

    $migrationOk = $db->execQuery(file_get_contents(__DIR__."/../data/market_cache.sql")) !== false;

    if ($migrationOk) {
        $updateVersion("market_cache", 20260805001);
        Logger::info("Applied patch market_cache 20260805001 - initial market cache setup");
    } else {
        Logger::error("Failed to apply patch market_cache 20260805001 - initial market cache setup");
    }
}

if ($checkVersion("default_npc_tags") < 20260805003) {
    Logger::debug("Applying default_npc_tags 20260805003 - apply complete default NPC tag audit");

    $migrationPath = __DIR__ . "/../data/default_npc_tag_audit_20260805.sql";
    $migrationOk = is_readable($migrationPath)
        && $db->execQuery(file_get_contents($migrationPath)) !== false;

    if ($migrationOk) {
        $updateVersion("default_npc_tags", 20260805003);
        Logger::info("Applied patch default_npc_tags 20260805003");
    } else {
        Logger::error("Failed to apply patch default_npc_tags 20260805003");
    }
}

if ($checkVersion("eventlog_session_payload") < 20260807001) {
    Logger::debug("Applying eventlog_session_payload 20260807001 - allow complete routing snapshots");

    $migrationOk = $db->execQuery(
        "ALTER TABLE public.eventlog ALTER COLUMN sess TYPE text"
    ) !== false;

    if ($migrationOk) {
        $updateVersion("eventlog_session_payload", 20260807001);
        Logger::info("Applied patch eventlog_session_payload 20260807001");
    } else {
        Logger::error("Failed to apply patch eventlog_session_payload 20260807001");
    }
}


//----------------------------------------------------
// AUDIT REQUEST RESPONSE - Store the response text for audit requests
// Version 20260806001
//----------------------------------------------------
$db->execQuery("ALTER TABLE public.audit_request ADD COLUMN IF NOT EXISTS \"response\"  text");

$db->execQuery("
DROP VIEW public.eventlog_view;
ALTER TABLE eventlog ALTER COLUMN sess TYPE text;
CREATE VIEW public.eventlog_view AS
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
    e.utterance_id,
    e.delivery_state,
    public.convert_gamets2skyrim_date(e.gamets) AS sk_date,
    public.convert_gamets2skyrim_long_date(e.gamets) AS sk_long_date,
    public.convert_gamets2days(e.gamets) AS sk_days,
    public.convert_gamets2gregorian_date(e.gamets) AS gregorian_date
   FROM public.eventlog e;


ALTER TABLE public.eventlog_view OWNER TO dwemer;
");

Logger::info(__FILE__." update file processed");

//----------------------------------------------------
        
Logger::info(__FILE__." update file processed. This file has ".__LINE__." lines.");
?>
