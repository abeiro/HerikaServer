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
$GLOBALS["ENGINE_PATH"] = $enginePath;

// ─── Includes ─────────────────────────────────────────────────────────────────

require_once $enginePath . 'lib/runtime_bootstrap.php';
chimRuntimeBootstrap($enginePath, [
    'load_general_settings' => true,
    'load_player_name' => true,
    'load_narrator' => true,
]);

require_once $enginePath . 'lib/model_dynmodel.php';
require_once $enginePath . 'lib/chat_helper_functions.php';
require_once $enginePath . 'lib/data_functions.php';
require_once $enginePath . 'lib/logger.php';
require_once $enginePath . 'lib/utils_game_timestamp.php';
require_once $enginePath . 'lib/rolemaster_helpers.php';
require_once $enginePath . 'lib/scriptproxy_papyrus.php';
require_once $enginePath . 'lib/core/player.class.php';
require_once $enginePath . 'lib/core/npc_master.class.php';
require_once $enginePath . 'lib/core/api_badge.class.php';
require_once $enginePath . 'lib/core/core_profiles.class.php';
require_once $enginePath . 'lib/core/llm_connector.class.php';
require_once $enginePath . 'lib/core/tts_connector.class.php';
require_once $enginePath . 'lib/lazy_xml.php';
require_once $enginePath . 'debug/background_action_handler.php';

// ─── Database ─────────────────────────────────────────────────────────────────

$db = $GLOBALS["db"];

require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "lazy_xml.php";

$connector            = new LLMConnector();
$currentConnectorData = $connector->getById($GLOBALS["CORE_CONNECTOR_MEDIUMTERM"]);

$connector->setOldGlobals($currentConnectorData);

$CLEAN_CONTEXT_FOCUS_CHAT = false;
$COMMAND_PROMPT           = '';

$res  = $db->fetchAll("select max(gamets) as last_gamets from eventlog");
$res2 = $db->fetchAll("select max(ts) as ts from eventlog where gamets='{$res[0]["last_gamets"]}'");

$last_gamets = $res[0]["last_gamets"] + 1;
$last_ts     = $res2[0]["ts"];

$contextDataFull = $db->fetchAll("SELECT summary as content,gamets_truncated FROM memory_summary  where summary is not null order by gamets_truncated desc LIMIT 5 OFFSET 0");

$smri = [];
foreach (array_reverse($contextDataFull) as $element) {
    $smri[] = $element["content"];

}
$summaries = "\n<summaries>\n<summary>" . implode("</summary>\n<summary>", $smri) . "\n</summaries>\n";

echo "===========================================================" . PHP_EOL;

$head[]  = ['role' => 'system', 'content' => "You are a journalist assistant in the fictional universe of Skyrim (The Elder Scrolls)."];
$request = "
Create a rumor/news about this topic {$argv[1]}.
Generate a rumor/breaking news entry, using this XML format:
    <type>rumor or breaking news</type>
    <location>Locations where rumor/news apply, comma seperated. E.G. Solitude, Haafingar</location>
    <content>Generate content about rumor/breaking news</location>
";

$prompt[] = ['role' => 'user', 'content' => $request];

$contextData = array_merge($head, $prompt);

Logger::debug(__LINE__ . " " . (microtime(true) - $startTime));

$connectionHandler = $connector->getConnector($currentConnectorData);
$buffer            = $connectionHandler->fast_request($contextData, ["MAX_TOKENS" => 2048, "model" => "google/gemini-2.5-flash-lite", "temperature" => 0.7], "backgroundlife");
Logger::debug(__LINE__ . " " . (microtime(true) - $startTime));

print_r($buffer . PHP_EOL);



$data = [];
$data["type"]=manual_get_tag_content($buffer,"type");
$data["content"]=manual_get_tag_content($buffer,"content");
$data["location"]=manual_get_tag_content($buffer,"location");

if (isset($data["type"]) && isset($data["location"])&isset($data["content"]) && ! empty($data["location"])) {

    $db->insert(
        'rumors',
        [
            'ts'      => $last_ts,
            'gamets'  => $last_gamets + 1,
            'type'    => $data["type"],
            'content' => $data["content"],
            'hold'    => $data["location"] ?? null,
        ]
    );
}
die();
