<?php

$GLOBALS["TASKS"]["middleterm"] = [];
$GLOBALS["TASKS"]["middleterm"]["fn"] = function () {

    $enginePath = $GLOBALS["ENGINE_ROOT"];
    $GLOBALS["ENGINE_PATH"] = $enginePath;


    if (!isset($GLOBALS["db"])) {
        $GLOBALS["db"] = new sql();
    }

    require_once($enginePath . "prompts/command_prompt.php");
    require_once($enginePath . "lib/chat_helper_functions.php");
    require_once($enginePath . "lib/data_functions.php");
    require_once($enginePath . "lib/rolemaster_helpers.php");
    require_once($enginePath . "lib/utils_game_timestamp.php");

    require_once $enginePath . "lib/core/npc_master.class.php";
    require_once $enginePath . "lib/core/api_badge.class.php";
    require_once $enginePath . "lib/core/core_profiles.class.php";
    require_once $enginePath . "lib/core/llm_connector.class.php";

    /**
     * Process delayed events waiting for TTS to finish
     * Events are stored in npcMaster extended_data and posted when speech has been idle for 15+ seconds
     */
    function processDelayedEvents($db, $enginePath)
    {
        $npcMaster = new NpcMaster();
        $lastSpeechGamets = GetLastSpeechTs();
        $currentGamets = DataLastKnownGameTS();

        // Check all NPCs with pending delayed events
        $allNpcs = $db->fetchAll("SELECT * FROM core_npc_master WHERE extended_data->>'pending_delayed_event' IS NOT NULL");

        foreach ($allNpcs as $npc) {
            $extendedData = $npcMaster->getExtendedData($npc);

            if (!isset($extendedData['pending_delayed_event'])) {
                continue;
            }

            $pendingEvent = $extendedData['pending_delayed_event'];

            // Check if 15 seconds have passed since last speech (using game ticks)
            $secondsSinceLastSpeech = gamets2seconds_between($lastSpeechGamets, $currentGamets);
            if ($secondsSinceLastSpeech >= 15) {
                logger::info("[DELAYED-EVENT] Posting delayed event for {$npc['npc_name']}");

                // Insert the pending event into responselog
                $db->insert('responselog', $pendingEvent);

                // Remove the pending event from extended_data
                unset($extendedData['pending_delayed_event']);
                $npc = $npcMaster->setExtendedData($npc, $extendedData);
                $npcMaster->updateByArray($npc);

                logger::info("[DELAYED-EVENT] Event posted and cleared for {$npc['npc_name']}");
            } else {
                $waitTime = 15 - $secondsSinceLastSpeech;
                logger::debug("[DELAYED-EVENT] Waiting {$waitTime}s more for speech to finish for {$npc['npc_name']}");
            }
        }
    }

    //$results = $GLOBALS["db"]->fetchAll("select max(gamets_truncated) as gamets_truncated from memory_summary where summary is not null order by gamets_truncated desc limit 1"); //0.8ms
    $results = $GLOBALS["db"]->fetchAll("select max(gamets_truncated) as gamets_truncated from memory_summary where summary is not null"); // 0.5ms, faster 
    $lastMemory = intval($results[0]["gamets_truncated"]);

    //$results = $GLOBALS["db"]->fetchAll("select max(gamets) as gamets from eventlog ORDER BY gamets desc limit 1");
    $results = $GLOBALS["db"]->fetchAll("select max(gamets) as gamets from eventlog"); // faster
    $maxRow = intval($results[0]["gamets"]);

    $allEnabledMtNpc = $GLOBALS["db"]->fetchAll("SELECT * FROM core_npc_master WHERE extended_data->'middle_term_enabled' = '1' ");

    foreach ($allEnabledMtNpc as $npc) {
        $mwdata = json_decode($npc["extended_data"]);
        //echo "[MIDDLETERM] {$npc["npc_name"]} has middleterm memory enabled".PHP_EOL;
        $GLOBALS["SELECTED_NPC"] = $npc["npc_name"];
        require("cmd" . DIRECTORY_SEPARATOR . "generate.php");
    }

    // BgL tracking coords, on NPCs marked with gps_track. in-game hourly
    $oneDayAgoGamets = $maxRow - ((24) / 0.0000024);
    $oneHourAgoGamets = $maxRow - ((1) / 0.0000024);

    // Get BgL trigger period from conf.php (default: 5 days)
    $bglTriggerDays = isset($GLOBALS['BGL_TRIGGER_DAYS']) && is_numeric($GLOBALS['BGL_TRIGGER_DAYS'])
        ? max(0.1, floatval($GLOBALS['BGL_TRIGGER_DAYS']))
        : 5;
    $bglTriggerDaysAgoGamets = $maxRow - ((24 * $bglTriggerDays) / 0.0000024);

    // BgL tracking coords, in-game daily

    $allEnabledBgLNpc = $GLOBALS["db"]->fetchAll("SELECT * FROM core_npc_master WHERE extended_data->>'background_life_enabled' = 'true' AND metadata->>'last_coords' IS NOT NULL AND metadata->'last_coords'->>'pending' IS NULL ");
    foreach ($allEnabledBgLNpc as $npc) {
        $mwdata = json_decode($npc["metadata"], true);
        if (!isset($mwdata["last_coords"]["last_updated"]) || !$mwdata["last_coords"]["last_updated"] || $mwdata["last_coords"]["last_updated"] < ($oneDayAgoGamets)) {
            logger::info("[BGL] Daily Tracking {$npc["npc_name"]}");
            $shellResult = shell_exec("php $enginePath/debug/simple_llm_request_with_context_life_command.php \"{$npc["npc_name"]}\" Track ");
            if (!empty($GLOBALS["CUSTOM_LOG_FILE"])) {
                Logger::info($shellResult, $GLOBALS["CUSTOM_LOG_FILE"]);
            }
        }

    }



    // GPS coords track
    if (false) {
        // This will track every 5 secs
        $oneHourAgoGamets = $maxRow;
    }

    error_log("[BGL] Checking tracked NPCs");

    $allEnabledBgLNpc = $GLOBALS["db"]->fetchAll("SELECT * FROM core_npc_master WHERE extended_data->>'background_life_enabled' = 'true' AND metadata->'gps_track' = 'true' AND metadata->'last_coords'->>'pending' IS NULL AND (metadata->'last_coords'->>'last_updated')::numeric < $oneHourAgoGamets ");

    foreach ($allEnabledBgLNpc as $npc) {
        $mwdata = json_decode($npc["metadata"], true);
        if (
            !isset($mwdata["last_coords"]["last_updated"]) || !$mwdata["last_coords"]["last_updated"]
            || $mwdata["last_coords"]["last_updated"] < $oneHourAgoGamets
        ) {
            logger::info("[BGL] Hourly Tracking {$npc["npc_name"]}");
            $shellResult = shell_exec("php $enginePath/debug/simple_llm_request_with_context_life_command.php \"{$npc["npc_name"]}\" Track ");
            if (!empty($GLOBALS["CUSTOM_LOG_FILE"])) {
                Logger::info($shellResult, $GLOBALS["CUSTOM_LOG_FILE"]);
            }
        }
    }



    // BgL content
    // In-game based on configured days

    error_log("[BGL] Checking passive events NPCs");
    $allEnabledBgLNpc = $GLOBALS["db"]->fetchAll("SELECT * FROM core_npc_master WHERE extended_data->>'background_life_enabled' = 'true' AND (extended_data->>'background_life_commands' = 'false' or extended_data->>'background_life_commands'  IS NULL)");
    foreach ($allEnabledBgLNpc as $npc) {

        $npcIsNearToPlayer = $GLOBALS["db"]->fetchOne("SELECT count(*) as n from eventlog where 
            type='infonpc' and data like '%" . ($GLOBALS["db"]->escape($npc["npc_name"])) . "%' and gamets > $oneHourAgoGamets");


        if (isset($npcIsNearToPlayer) && $npcIsNearToPlayer["n"] > 0) {
            $localDelta = ($npcIsNearToPlayer["n"] - $oneHourAgoGamets) * 0.0000024;
            error_log("[BGL] Skipping Passive event for {$npc["npc_name"]}, is NEAR TO PLAYER, delta: {$localDelta}");
            continue;
        }

        $mwdata = json_decode($npc["extended_data"], true);
        // Trigger if never updated, or if last update is older than configured threshold
        if (!isset($mwdata["background_life_last_updated"]) || $mwdata["background_life_last_updated"] < ($bglTriggerDaysAgoGamets)) {
            logger::info("[BGL] Passive event for {$npc["npc_name"]}");



            $shellResult = shell_exec("php $enginePath/debug/simple_llm_request_with_context_life.php \"{$npc["npc_name"]}\" ");
            if (!empty($GLOBALS["CUSTOM_LOG_FILE"])) {
                Logger::info($shellResult, $GLOBALS["CUSTOM_LOG_FILE"]);
            }

            $npcManager = new NpcMaster();
            $npcData = $npcManager->getByName($npc["npc_name"]);
            $extended = json_decode($npcData["extended_data"], true);
            $extended["background_life_last_updated"] = $maxRow;
            $npcData = $npcManager->setExtendedData($npcData, $extended);
            $npcManager->updateByArray($npcData);

            break;  // One per iteration - break after processing
        } else {
            logger::debug("[BGL] (Passive) Skipping {$npc["npc_name"]}, last updated: {$mwdata["background_life_last_updated"]}, threshold: {$bglTriggerDaysAgoGamets}, BGL_TRIGGER_DAYS: {$GLOBALS['BGL_TRIGGER_DAYS']}");
        }
    }

    // Process delayed events for BgL NPCs
    processDelayedEvents($GLOBALS["db"], $enginePath);

    error_log("[BGL] Checking active events NPCs");
    // BgL commands
    $allEnabledBgLNpc = $GLOBALS["db"]->fetchAll("SELECT * FROM core_npc_master WHERE extended_data->>'background_life_enabled' = 'true' AND extended_data->>'background_life_commands' = 'true' ");
    foreach ($allEnabledBgLNpc as $npc) {
        $mwdata = json_decode($npc["extended_data"], true);

        $npcIsNearToPlayer = $GLOBALS["db"]->fetchOne("SELECT max(gamets) as n from eventlog where 
            type='infonpc' and data like '%" . ($GLOBALS["db"]->escape($npc["npc_name"])) . "%' and gamets > $oneHourAgoGamets");

        if (isset($npcIsNearToPlayer) && $npcIsNearToPlayer["n"] > 0) {
            $localDelta = ($npcIsNearToPlayer["n"] - $oneHourAgoGamets) * 0.0000024;
            error_log("[BGL] Skipping Passive event for {$npc["npc_name"]}, is NEAR TO PLAYER, delta: {$localDelta}");
            continue;
        }

        // Trigger if never updated, or if last update is older than configured threshold
        if (!isset($mwdata["background_life_last_updated"]) || $mwdata["background_life_last_updated"] < ($bglTriggerDaysAgoGamets)) {
            $delta = ($mwdata["background_life_last_updated"] - $bglTriggerDaysAgoGamets) * 0.0000024;
            error_log("[BGL] Event for {$npc["npc_name"]}, last updated: {$mwdata["background_life_last_updated"]}, threshold: {$bglTriggerDaysAgoGamets}, BGL_TRIGGER_DAYS: {$GLOBALS['BGL_TRIGGER_DAYS']}, delta: {$delta}");
            $shellResult = shell_exec("php $enginePath/debug/simple_llm_request_with_context_life_v2.php \"{$npc["npc_name"]}\" full forceaction");
            if (!empty($GLOBALS["CUSTOM_LOG_FILE"])) {
                Logger::info($shellResult, $GLOBALS["CUSTOM_LOG_FILE"]);
            }
            break;  // One per iteration - break after processing
        } else {
            $delta = ($mwdata["background_life_last_updated"] - $bglTriggerDaysAgoGamets) * 0.0000024;
            error_log("[BGL] Skipping {$npc["npc_name"]}, last updated: {$mwdata["background_life_last_updated"]}, threshold: {$bglTriggerDaysAgoGamets}, BGL_TRIGGER_DAYS: {$GLOBALS['BGL_TRIGGER_DAYS']} , delta: {$delta}");
        }
    }

    if (sizeof($allEnabledBgLNpc) === 0) {
        error_log("[BGL] No NPCs with background life enabled");
    }


    $pfi = intval($GLOBALS["FEATURES"]["MEMORY_EMBEDDING"]["AUTO_CREATE_SUMMARY_INTERVAL"] ?? 10) * 100000;

    if (($maxRow - $lastMemory) > ($pfi)) {
        // Run memory compaction silently
        $shellResult = shell_exec("php {$GLOBALS["ENGINE_PATH"]}/debug/util_memory_subsystem.php compact embed 1 2>/dev/null");
    }

    //unset($GLOBALS["db"]);

}
    ?>