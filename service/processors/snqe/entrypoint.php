<?php 

$GLOBALS["TASKS"]["snqe"]=[];
$GLOBALS["TASKS"]["snqe"]["fn"]=function() {

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

    if ($GLOBALS["argv"][2]=="create") {
        Logger::info("Loading instruction command");
        require_once("cmd" . DIRECTORY_SEPARATOR . "create.php");
    } else if ($GLOBALS["argv"][2]=="run") {
        Logger::info("Loading suggestion command");
        require_once("cmd" . DIRECTORY_SEPARATOR . "main.php");
    } else if ($GLOBALS["argv"][2]=="reset") {
        $GLOBALS["db"]->execQuery("update sneq_quests set quest_data='{}',quest_run_state='not started'");
    }


}
?>