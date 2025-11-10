<?php 

$GLOBALS["TASKS"]["middleterm"]=[];
$GLOBALS["TASKS"]["middleterm"]["fn"]=function() {

    $enginePath = $GLOBALS["ENGINE_ROOT"];
    $GLOBALS["ENGINE_PATH"]=$enginePath;

    require_once($enginePath . "lib/{$GLOBALS["DBDRIVER"]}.class.php");
	if (!isset($GLOBALS["db"])) { $GLOBALS["db"] = new sql(); }
    require_once($enginePath . "prompts/command_prompt.php");
    require_once($enginePath . "lib/chat_helper_functions.php");
    require_once($enginePath . "lib/data_functions.php");
    require_once($enginePath . "lib/rolemaster_helpers.php");

    require_once $enginePath . "lib/core/npc_master.class.php";
    require_once $enginePath . "lib/core/api_badge.class.php";
    require_once $enginePath . "lib/core/core_profiles.class.php";
    require_once $enginePath . "lib/core/llm_connector.class.php";


    //$results = $GLOBALS["db"]->fetchAll("select max(gamets_truncated) as gamets_truncated from memory_summary where summary is not null order by gamets_truncated desc limit 1"); //0.8ms
    $results = $GLOBALS["db"]->fetchAll("select max(gamets_truncated) as gamets_truncated from memory_summary where summary is not null"); // 0.5ms, faster 
    $lastMemory = intval($results[0]["gamets_truncated"]);
    
    //$results = $GLOBALS["db"]->fetchAll("select max(gamets) as gamets from eventlog ORDER BY gamets desc limit 1");
    $results = $GLOBALS["db"]->fetchAll("select max(gamets) as gamets from eventlog"); // faster
    $maxRow = intval($results[0]["gamets"]);

    $allEnabledMtNpc=$GLOBALS["db"]->fetchAll("SELECT * FROM core_npc_master WHERE extended_data->'middle_term_enabled' = '1' ");

    foreach ($allEnabledMtNpc as $npc) {
        $mwdata=json_decode($npc["extended_data"]);
        // echo "[MIDDLETERM] {$npc["npc_name"]} has middleterm memory enabled".PHP_EOL;
        $GLOBALS["SELECTED_NPC"]=$npc["npc_name"];
        require("cmd" . DIRECTORY_SEPARATOR . "generate.php");
    }

    // BgL tracking coords
    /*
    $allEnabledBgLNpc=$GLOBALS["db"]->fetchAll("SELECT * FROM core_npc_master WHERE extended_data->>'background_life_enabled' = 'true' AND metadata->>'last_coords' IS NOT NULL ");
    foreach ($allEnabledBgLNpc as $npc) {
        $mwdata=json_decode($npc["metadata"],true);
        if (!isset($mwdata["last_coords"]["last_updated"]) || !$mwdata["last_coords"]["last_updated"] || $mwdata["last_coords"]["last_updated"]<($maxRow - ( 24 /0.0000024))) {
            echo("[BACKGROUND-LIFE] Tracking {$npc["npc_name"]}".PHP_EOL);
            `php $enginePath/debug/simple_llm_request_with_context_life_command.php "{$npc["npc_name"]}" Track`;
        }
        
    }
    */
    // BgL commands
    $allEnabledBgLNpc=$GLOBALS["db"]->fetchAll("SELECT * FROM core_npc_master WHERE extended_data->>'background_life_enabled' = 'true' AND extended_data->>'background_life_last_updated' IS NOT NULL ");
    foreach ($allEnabledBgLNpc as $npc) {
        $mwdata=json_decode($npc["extended_data"],true);
        if (isset($mwdata["background_life_last_updated"])  && $mwdata["background_life_last_updated"]<($maxRow - ( 7 * 24 /0.0000024))) {
            echo("[BACKGROUND-LIFE] Generating event for {$npc["npc_name"]}".PHP_EOL);
            `php $enginePath/debug/simple_llm_request_with_context_life.php "{$npc["npc_name"]}" full`;
        }
        break;  // One per iteration
    }

    $pfi = intval($GLOBALS["FEATURES"]["MEMORY_EMBEDDING"]["AUTO_CREATE_SUMMARY_INTERVAL"] ?? 10) * 100000;
    
    if (($maxRow-$lastMemory)>($pfi)) {
        echo "[SUMMARY] memory creation maxRow-lastMemory > pfi  ($maxRow-$lastMemory)>($pfi) ";

        $shellResult = shell_exec("php {$GLOBALS["ENGINE_PATH"]}/debug/util_memory_subsystem.php compact embed 1 &");
        if (!empty($GLOBALS["CUSTOM_LOG_FILE"])) {
            Logger::info($shellResult, $GLOBALS["CUSTOM_LOG_FILE"]);
        }

    } else {
        error_log("[SUMMARY] Skiping memory creation maxRow-lastMemory > pfi  ($maxRow-$lastMemory)>($pfi) ");
    }

    //unset($GLOBALS["db"]);
    
}
?>