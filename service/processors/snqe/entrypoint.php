<?php

$GLOBALS["TASKS"]["snqe"] = [];
$GLOBALS["TASKS"]["snqe"]["fn"] = function () {

    $enginePath = $GLOBALS["ENGINE_ROOT"];
    $GLOBALS["ENGINE_PATH"] = $enginePath;

    require_once($enginePath . "lib/{$GLOBALS["DBDRIVER"]}.class.php");
    if (!isset($GLOBALS["db"])) {
        $GLOBALS["db"] = new sql();
    }
    require_once($enginePath . "prompts/command_prompt.php");
    require_once($enginePath . "lib/chat_helper_functions.php");
    require_once($enginePath . "lib/data_functions.php");
    require_once($enginePath . "lib/rolemaster_helpers.php");

    require_once $enginePath . "lib/core/npc_master.class.php";
    require_once $enginePath . "lib/core/api_badge.class.php";
    require_once $enginePath . "lib/core/core_profiles.class.php";
    require_once $enginePath . "lib/core/llm_connector.class.php";
    require_once $enginePath . "lib/core/player.class.php";
    require_once $enginePath . "lib/scriptproxy_papyrus.php";

    if (isset($GLOBALS["argv"][2])) {
        if ($GLOBALS["argv"][2] == "create") {
            Logger::info("Loading instruction command");
            require_once("cmd" . DIRECTORY_SEPARATOR . "create.php");
        } else if ($GLOBALS["argv"][2] == "run") {
            Logger::info("Loading suggestion command");
            require_once("cmd" . DIRECTORY_SEPARATOR . "main.php");
        } else if ($GLOBALS["argv"][2] == "reset") {
            $GLOBALS["db"]->execQuery("update sneq_quests set quest_data='{}',quest_run_state='not started' where quest_run_state<>'finished'");
        } else if ($GLOBALS["argv"][2] == "clean") {
            $GLOBALS["db"]->execQuery("truncate sneq_quests");
        }
    } else {
        Logger::info("Running default run command");
        require_once("cmd" . DIRECTORY_SEPARATOR . "main.php");
    }

    //unset($GLOBALS["db"]);

}
    ?>