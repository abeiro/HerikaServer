<?php 

$GLOBALS["TASKS"]["middleterm"]=[];
$GLOBALS["TASKS"]["middleterm"]["fn"]=function() {

    $enginePath = $GLOBALS["ENGINE_ROOT"];

    require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."{$GLOBALS["DBDRIVER"]}.class.php");
    require_once($enginePath . "prompts" .DIRECTORY_SEPARATOR."command_prompt.php");
    require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."chat_helper_functions.php");
    require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."data_functions.php");
    require_once($enginePath . "lib/rolemaster_helpers.php");

    $GLOBALS["ENGINE_PATH"]=$enginePath;
    $GLOBALS["db"]=new sql();
    
    require_once $enginePath . "lib/core/npc_master.class.php";
    require_once $enginePath . "lib/core/api_badge.class.php";
    require_once $enginePath . "lib/core/core_profiles.class.php";
    require_once $enginePath . "lib/core/llm_connector.class.php";


    $allEnabledMtNpc=$GLOBALS["db"]->fetchAll("SELECT * FROM core_npc_master WHERE extended_data->'middle_term_enabled' = '1'");

    foreach ($allEnabledMtNpc as $npc) {
        $mwdata=json_decode($npc["extended_data"]);
        echo "[MIDDLETERM] {$npc["npc_name"]} has middleterm memory enabled".PHP_EOL;
        $GLOBALS["SELECTED_NPC"]=$npc["npc_name"];
        require("cmd" . DIRECTORY_SEPARATOR . "generate.php");


    }

    $results = $GLOBALS["db"]->fetchAll("select max(gamets_truncated) as gamets_truncated from memory_summary where summary is not null order by gamets_truncated desc limit 1");

    $lastMemory=$results[0]["gamets_truncated"]+0;
    
    $results = $GLOBALS["db"]->fetchAll("select max(gamets) as gamets from eventlog ORDER BY gamets desc limit 1");

    $maxRow=$results[0]["gamets"]+0;

    $pfi=($GLOBALS["FEATURES"]["MEMORY_EMBEDDING"]["AUTO_CREATE_SUMMARY_INTERVAL"]+0)*100000;
    

    if (($maxRow-$lastMemory)>($pfi)) {
        error_log("[SUMMARY] memory creation maxRow-lastMemory > pfi  ($maxRow-$lastMemory)>($pfi) ");

        Logger::info(shell_exec("php {$GLOBALS["ENGINE_PATH"]}/debug/util_memory_subsystem.php compact embed 1 &"));
        
    } else {
        
        error_log("[SUMMARY] Skiping memory creation maxRow-lastMemory > pfi  ($maxRow-$lastMemory)>($pfi) ");

    }

    unset($GLOBALS["db"]);


}
?>