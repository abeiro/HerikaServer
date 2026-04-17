<?php

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
header('Content-Type: application/json');

$enginePath = __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR;
$GLOBALS["ENGINE_PATH"] = $enginePath;
require_once $enginePath . "conf" . DIRECTORY_SEPARATOR . "conf.php";
require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "{$GLOBALS["DBDRIVER"]}.class.php";
require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "logger.php";
require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "dynamic_update_util.php";
require_once $enginePath . "lib/core/npc_master.class.php";
require_once $enginePath . "lib/core/core_profiles.class.php";
require_once $enginePath . "lib/core/llm_connector.class.php";
require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "lazy_xml.php";
require_once __DIR__ . DIRECTORY_SEPARATOR . "ai_profile_generation_service.php";

$db = new sql();
$GLOBALS["db"] = $db;
$jsonDataInput = aiProfileMergeRequestData();

$selectedEvents = [];
if (isset($jsonDataInput['selected_events']) && trim((string)$jsonDataInput['selected_events']) !== '') {
    $decodedSelectedEvents = json_decode((string)$jsonDataInput['selected_events'], true);
    if (json_last_error() === JSON_ERROR_NONE) {
        $selectedEvents = $decodedSelectedEvents;
    }
}

$result = aiProfileGenerate([
    'db' => $db,
    'name' => $jsonDataInput['name'] ?? '',
    'connector_id' => $jsonDataInput['connector_id'] ?? '',
    'user_prompt' => $jsonDataInput['user_prompt'] ?? '',
    'event_limit' => $jsonDataInput['event_limit'] ?? 100,
    'selected_events' => $selectedEvents,
    'source' => 'manual',
]);

echo json_encode($result);
