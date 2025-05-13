<?php 

$GLOBALS["TASKS"]["rolemaster"]=[];
$GLOBALS["TASKS"]["rolemaster"]["fn"]=function() {

    $enginePath = $GLOBALS["ENGINE_ROOT"];

    /* Connector to use */
    $file = $GLOBALS["ENGINE_ROOT"].'/data/CurrentModel_.json';
    $modelContents = file_get_contents($file);
    Logger::info("Current AI Model is set to $modelContents.");

    // Initialize function parameters before requiring functions.php
    $GLOBALS["FUNCTION_PARM_INSPECT"] = [];
    $GLOBALS["FUNCTION_PARM_MOVETO"] = [];
    $GLOBALS["F_NAMES"] = [];

    require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."model_dynmodel.php");
    require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."{$GLOBALS["DBDRIVER"]}.class.php");
    require_once($enginePath . "prompts" .DIRECTORY_SEPARATOR."command_prompt.php");
    require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."chat_helper_functions.php");
    require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."data_functions.php");
    require_once($enginePath . "lib/rolemaster_helpers.php");

    $GLOBALS["db"]=new sql();
    
    $GLOBALS["HERIKA_NAME"]="(actor)";

    require($enginePath . "functions/functions.php");

    // Make functions.php data global

    
    $GLOBALS["FUNCTIONS_ARE_ENABLED"]=false;

    $GLOBALS["CURRENT_CONNECTOR"]=$GLOBALS["CONNECTORS_DIARY"];

    // Some functions need this setted */
    $res=$GLOBALS["db"]->fetchAll("select max(gamets)+1 as gamets,max(ts)+1 as ts  from eventlog order by gamets desc limit 1 offset 0");
    $GLOBALS["gameRequest"]=["inputtext"];
    $GLOBALS["gameRequest"][2]=$res[0]["gamets"]+1;

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
    }


}
?>