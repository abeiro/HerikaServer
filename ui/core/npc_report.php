<?php 

$enginePath = __DIR__ . DIRECTORY_SEPARATOR . "../../";

require_once($enginePath . "conf" . DIRECTORY_SEPARATOR . "conf.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "model_dynmodel.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "{$GLOBALS["DBDRIVER"]}.class.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "chat_helper_functions.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "data_functions.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "logger.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "utils_game_timestamp.php");

$GLOBALS["ENGINE_PATH"]=$enginePath;

$CONF_SAMPLE_VARS=extract_assignments("$enginePath/conf/conf.php");

$GLOBALS["db"] = new sql();

require_once $enginePath . "lib/core/npc_master.class.php";
require_once $enginePath . "lib/core/api_badge.class.php";
require_once $enginePath . "lib/core/core_profiles.class.php";
require_once $enginePath . "lib/core/llm_connector.class.php";
require_once $enginePath . "lib/core/tts_connector.class.php";

$npc = new NpcMaster();
$npcData=$npc->getById($_GET["npcid"]);
if ($npcData) {
    $query="
    SELECT personality, created
    FROM (
    SELECT personality,
            created,
            ROW_NUMBER() OVER (PARTITION BY personality ORDER BY created) AS rn
    FROM core_npc_master_history
    WHERE npc_name = '".$GLOBALS["db"]->escape($npcData["npc_name"])."'
    ) AS sub
    WHERE rn = 1
    ORDER BY created";
    $hdata=$GLOBALS["db"]->fetchAll($query);

    $connector = new LLMConnector();
    $currentConnectorData = $connector->getById($GLOBALS["CORE_CONNECTOR_MEDIUMTERM"]);


    $connector->setOldGlobals($currentConnectorData);

    $reportSource=[];
    foreach ($hdata as $row) {
        if ($row["personality"]) {
            $reportSource[]=$row["personality"];
        }
    }

    $CLEAN_CONTEXT_FOCUS_CHAT = false;

    $COMMAND_PROMPT = '';

    $head[] = ['role' => 'system', 'content' => "You are a character assistant. Carefully read the evolution of the character’s personality and write a report."];
    $prompt[] = ['role' => 'user', 'content' => implode("\n=====\n",$reportSource)];
    $prompt[] = ['role' => 'user', 'content' => "Write a reporting showing {$npcData["npc_name"]} evolution"];
    
    $contextData = array_merge($head, $prompt);

    Logger::debug(__LINE__ . " " . (microtime(true) - $startTime));

    $connectionHandler =$connector->getConnector($currentConnectorData);
    $buffer=$connectionHandler->fast_request($contextData,["MAX_TOKENS"=>4096]);
    
    print_r(nl2br($buffer));
    
}
?>