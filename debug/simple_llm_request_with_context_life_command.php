<?php

$startTime = microtime(true);

define("MAXIMUM_SENTENCE_SIZE", 125);
define("MINIMUM_SENTENCE_SIZE", 15);

$GLOBALS["SCRIPTLINE_EXPRESSION"] = "";
$GLOBALS["SCRIPTLINE_LISTENER"]   = "";
$GLOBALS["SCRIPTLINE_ANIMATION"]  = "";

error_reporting(E_ALL);
ini_set('display_errors', 1);
$file       = __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "data" . DIRECTORY_SEPARATOR . 'CurrentModel_.json';
$enginePath = __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR;

$enginePath = dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR;
require_once $enginePath . "conf" . DIRECTORY_SEPARATOR . "conf.php";
require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "model_dynmodel.php";
require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "{$GLOBALS["DBDRIVER"]}.class.php";
require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "chat_helper_functions.php";
require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "data_functions.php";
require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "logger.php";
require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "utils_game_timestamp.php";
require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "rolemaster_helpers.php";
$GLOBALS["ENGINE_PATH"] = $enginePath;

$db = new sql();

require_once $enginePath . "lib/core/npc_master.class.php";
require_once $enginePath . "lib/core/api_badge.class.php";
require_once $enginePath . "lib/core/core_profiles.class.php";
require_once $enginePath . "lib/core/llm_connector.class.php";
require_once $enginePath . "lib/core/tts_connector.class.php";

require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "lazy_xml.php";
require_once $enginePath . "debug" . DIRECTORY_SEPARATOR . "background_action_handler.php";

$npcMaster = new NpcMaster();

$connector            = new LLMConnector();
$currentConnectorData = $connector->getById($GLOBALS["CORE_CONNECTOR_MEDIUMTERM"]);
$currentNpcData       = $npcMaster->getByName($argv[1]);// Lookup NPC data by name passed as first argument

$profile            = new CoreProfile();
$currentProfileData = $profile->getById($currentNpcData["profile_id"]);
$connector->setOldGlobals($currentConnectorData);
$npcMaster->setOldGlobalsFromCurrentNpcData($currentNpcData);

$CLEAN_CONTEXT_FOCUS_CHAT = false;

$COMMAND_PROMPT = '';

$dbNpcName = $db->escape($argv[1]);
$limit     = 100;
$momentum  = time();

$GLOBALS["HERIKA_NAME"] = $argv[1];

$res  = $db->fetchAll("select max(gamets) as last_gamets from eventlog");
$res2 = $db->fetchAll("select max(ts) as ts from eventlog where gamets='{$res[0]["last_gamets"]}'");

$last_gamets = $res[0]["last_gamets"] + 1;
$last_ts     = $res2[0]["ts"];

$gameRequest = ["inputtext", "0", $last_gamets, $argv[1]];

$request = $argv[1];

//$dynamicBiography = buildDynamicBiography($GLOBALS, true, true);
$npcMaster        = new NpcMaster();
$currentNpcData   = $npcMaster->getByName($argv[1]);
$extended_data    = $npcMaster->getExtendedData($currentNpcData);

// Things that happened after last iteration
$npcNameEsc = $db->escape($GLOBALS["HERIKA_NAME"]);
$query      = "SELECT max(gamets) as  gamets from speech where
    (speaker='$npcNameEsc' or listener='$npcNameEsc' or companions like '%|$npcNameEsc|%')
    ";

// error_log($query);
$lastIt       = $db->fetchOne($query);
$lastItNumber = $lastIt["gamets"] ?? 0;
$momentum=time();

$cmds[0] = $argv[2];
$cmds[1] = isset($argv[3])?$argv[3]:null;

if ($cmds[0] == "TravelTo") {
    handleTravelToAction($cmds[1], $currentNpcData, $GLOBALS["HERIKA_NAME"], $last_ts, $last_gamets, $momentum, $cmds[1], $db);

} else if ($cmds[0] == "StayAtPlace") {
    handleStayAtPlaceAction($cmds[1], $currentNpcData, $GLOBALS["HERIKA_NAME"], $last_ts, $last_gamets, $momentum, $db);
} else if ($cmds[0] == "ReturnHome") {
    handleReturnHome($cmds[1], $currentNpcData, $GLOBALS["HERIKA_NAME"], $last_ts, $last_gamets, $momentum, $db);
} else if ($cmds[0] == "Track") {
    $metadata=$npcMaster->getMetadata($currentNpcData);
    $metadata["last_coords"]["pending"]=true;
    $npcMaster->updateByArray($npcMaster->setMetadata($currentNpcData,$metadata));
    handleTrack($currentNpcData, $db);
} else if ($cmds[0] == "TrackAll") {
    $allBglNPC=$db->fetchAll("select * from public.core_npc_master WHERE (extended_data ->> 'background_life_enabled')::boolean = true");
    foreach ($allBglNPC as $npc) {
        // handleTrack only needs refid
        $currentNpcData   = $npcMaster->getByName($npc["npc_name"]);
        $metadata=$npcMaster->getMetadata($currentNpcData);
        $metadata["last_coords"]["pending"]=true;
        $npcMaster->updateByArray($npcMaster->setMetadata($currentNpcData,$metadata));

        handleTrack($npc, $db);
    }
}
