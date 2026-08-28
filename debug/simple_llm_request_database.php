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
$GLOBALS["ENGINE_PATH"]=$enginePath;

require_once $enginePath . "lib/runtime_bootstrap.php";
chimRuntimeBootstrap($enginePath, [
    'load_general_settings' => true,
    'load_player_name' => true,
    'load_narrator' => true,
]);

if (!chimIsGlobalLlmConnectorEnabled('CORE_CONNECTOR_MEDIUMTERM')) {
    echo "Background & Memory Tasks are disabled globally." . PHP_EOL;
    exit(0);
}

require_once $enginePath . "lib/model_dynmodel.php";
require_once $enginePath . "lib/chat_helper_functions.php";
require_once $enginePath . "lib/data_functions.php";
require_once $enginePath . "lib/logger.php";


$db = $GLOBALS["db"];

require_once $enginePath . "lib/core/npc_master.class.php";
require_once $enginePath . "lib/core/api_badge.class.php";
require_once $enginePath . "lib/core/core_profiles.class.php";
require_once $enginePath . "lib/core/llm_connector.class.php";
require_once $enginePath . "lib/core/tts_connector.class.php";


$connector = new LLMConnector();
$currentConnectorData = $connector->getById($GLOBALS["CORE_CONNECTOR_MEDIUMTERM"]);

$connector->setOldGlobals($currentConnectorData);

$COMMAND_PROMPT = '';

$database_desc=file_get_contents(__DIR__."/../lib/core/database_schema/database_description.txt");

$head[] = ['role' => 'system', 'content' => "You're a SQL assistant. Read carefully this database description. Database engine is PostgreSQL. Check also 'PostgreSQL Strictness Highlights' chapter"];

$prompt[] = ['role' => 'user', 'content' => $database_desc];
$prompt[] = ['role' => 'user', 'content' => "Write a SQL query to achieve the user request. Use this JSON object {\"sql\":\"sql query\",\"explanaton\":\"\"}"];
$prompt[] = ['role' => 'user', 'content' => $argv[1]];

$contextData = array_merge($head, $prompt);

Logger::debug(__LINE__ . " " . (microtime(true) - $startTime));

$connectionHandler =$connector->getConnector($currentConnectorData);
$buffer=$connectionHandler->fast_request($contextData,["MAX_TOKENS"=>4096,"model"=>"openai/gpt-oss-120b"],"sqlassistant");

Logger::debug(__LINE__ . " " . (microtime(true) - $startTime));

print_r($buffer. PHP_EOL);
$parsedResponse=__jpd_decode_lazy($buffer);
print_r($parsedResponse);
die();

