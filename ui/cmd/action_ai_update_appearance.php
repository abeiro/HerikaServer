<?php

$jsonDataInput = $_GET;

$startTime = microtime(true);

define("MAXIMUM_SENTENCE_SIZE", 125);
define("MINIMUM_SENTENCE_SIZE", 15);

$GLOBALS["SCRIPTLINE_EXPRESSION"] = "";
$GLOBALS["SCRIPTLINE_LISTENER"]   = "";
$GLOBALS["SCRIPTLINE_ANIMATION"]  = "";

error_reporting(E_ALL);
ini_set('display_errors', 1);
$file       = __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "data" . DIRECTORY_SEPARATOR . 'CurrentModel_.json';

$enginePath = __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR;
require_once $enginePath . "conf" . DIRECTORY_SEPARATOR . "conf.php";
require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "model_dynmodel.php";
require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "{$GLOBALS["DBDRIVER"]}.class.php";
require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "chat_helper_functions.php";
require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "data_functions.php";
require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "logger.php";
require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "utils_game_timestamp.php";
require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "rolemaster_helpers.php";
require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "dynamic_update_util.php";
$GLOBALS["ENGINE_PATH"] = $enginePath;

$db = new sql();

require_once $enginePath . "lib/core/npc_master.class.php";
require_once $enginePath . "lib/core/api_badge.class.php";
require_once $enginePath . "lib/core/core_profiles.class.php";
require_once $enginePath . "lib/core/llm_connector.class.php";
require_once $enginePath . "lib/core/tts_connector.class.php";

require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "lazy_xml.php";

$name = $jsonDataInput["name"];

$npcMaster = new NpcMaster();

$connector            = new LLMConnector();
$currentConnectorData = $connector->getById($GLOBALS["CORE_CONNECTOR_PROFILES"]);
$currentNpcData       = $npcMaster->getByName($name);

$profile            = new CoreProfile();
$currentProfileData = $profile->getById($currentNpcData["profile_id"]);
$connector->setOldGlobals($currentConnectorData);
$npcMaster->setOldGlobalsFromCurrentNpcData($currentNpcData);

$CLEAN_CONTEXT_FOCUS_CHAT = false;

$COMMAND_PROMPT = '';

$dbNpcName = $db->escape($name);

$momentum = time();

$GLOBALS["HERIKA_NAME"] = $name;

$metadata=json_decode($currentNpcData["metadata"],true);
$image="";
if (isset($metadata["portrait"])) {
    $image=$enginePath."/data/pictures/{$metadata["portrait"]}";
} else if (file_exists($enginePath."/data/pictures/{$metadata["portrait"]}")) {
    $image=$enginePath."/data/pictures/profile/{$currentNpcData["refid"]}.jpg";
}

if (!$image) {
    die(json_encode(["done" => false]));
}
//die(print_r($cs));

require_once($path."itt/itt-{$GLOBALS["ITTFUNCTION"]}.php");

$GLOBALS["ITT"][$GLOBALS["ITTFUNCTION"]]["AI_VISION_PROMPT"]="Describe the character in the picture. Name is {$GLOBALS["HERIKA_NAME"]} .
Do not focus on clothing, focus on physical appearance (face, eyes, hair, figure, waist,legs,breast size, tattoos if any....). Be concise. 
Start generation with this text:
{$GLOBALS["HERIKA_NAME"]} is " ;

$desc = itt($image,"");

echo json_encode(["done" => true,"appearance"=>$desc]);
