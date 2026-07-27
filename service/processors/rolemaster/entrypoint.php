<?php 

$GLOBALS["TASKS"]["rolemaster"]=[];
$GLOBALS["TASKS"]["rolemaster"]["fn"]=function() {

    $enginePath = $GLOBALS["ENGINE_ROOT"];

    // Initialize function parameters before requiring functions.php
    $GLOBALS["FUNCTION_PARM_INSPECT"] = [];
    $GLOBALS["FUNCTION_PARM_MOVETO"] = [];
    $GLOBALS["F_NAMES"] = [];
    
    require_once($enginePath . "lib/runtime_bootstrap.php");
    chimRuntimeBootstrapIfNeeded($enginePath, [
        'load_general_settings' => true,
        'load_stt_connector' => false,
        'load_itt_connector' => false,
        'load_player_name' => true,
        'load_narrator' => true,
    ]);
    if (isset($GLOBALS["CORE_LANG"])) {
        $GLOBALS["CORE_LANG"] = '';
    }
    if (!isset($GLOBALS["db"])) { $GLOBALS["db"] = new sql(); }
    require_once($enginePath . "prompts/command_prompt.php");
    require_once($enginePath . "lib/chat_helper_functions.php");
    require_once($enginePath . "lib/data_functions.php");
    require_once($enginePath . "lib/rolemaster_helpers.php");
    require_once($enginePath . "lib/rolemaster_bored.php");

    
    SaveOriginalHerikaName(); 
    $GLOBALS["HERIKA_NAME"]="(actor)";
    
    require($enginePath . "functions/functions.php");

    // Make functions.php data global

    
    $GLOBALS["FUNCTIONS_ARE_ENABLED"]=false;

    $GLOBALS["CURRENT_CONNECTOR"]=$GLOBALS["CONNECTORS_DIARY"];


    if (!isset($GLOBALS["db"])) { $GLOBALS["db"] = new sql(); }
    if (!$GLOBALS["db"]) {
        throw new Exception("Database connection established, but an error occurred during initialization.");
    }

    // Some functions need this setted 
    //$res=$GLOBALS["db"]->fetchAll("select max(gamets)+1 as gamets,max(ts)+1 as ts  from eventlog order by gamets desc limit 1 offset 0");
    $res=$GLOBALS["db"]->fetchAll("SELECT max(gamets)+1 as gamets, max(ts)+1 as ts FROM eventlog "); // without order is faster, pgsql can use a better exec plan
    if ($res) {
        $GLOBALS["gameRequest"]=["inputtext"];
        $GLOBALS["gameRequest"][2]=$res[0]["gamets"]+1;
    } else {
        Logger::error("Empty recordset. ".__FILE__." ".__LINE__." ".__FUNCTION__);
    }

    if (isset($GLOBALS["argv"][2])) {
        if ($GLOBALS["argv"][2]=="instruction") {
            Logger::info("Loading instruction command");
            require_once("cmd" . DIRECTORY_SEPARATOR . "instruction.php");
        } else if ($GLOBALS["argv"][2]=="suggestion") {
            Logger::info("Loading suggestion command");
            require_once("cmd" . DIRECTORY_SEPARATOR . "suggestion.php");
        } else if ($GLOBALS["argv"][2]=="impersonation") {
            Logger::info("Loading impersonation command");
            require_once("cmd" . DIRECTORY_SEPARATOR . "impersonation.php");
        }  else if ($GLOBALS["argv"][2]=="spawn") {
            Logger::info("Loading spawn command");
            require_once("cmd" . DIRECTORY_SEPARATOR . "spawncharacter.php");
        }  else if ($GLOBALS["argv"][2]=="smart_impersonation") {
            Logger::info("Loading smart_impersonation command");
            require_once("cmd" . DIRECTORY_SEPARATOR . "smart_impersonation.php");
        }
    }


}
?>
