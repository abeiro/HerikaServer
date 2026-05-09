<?php

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
header('Content-Type: application/json');

$enginePath = __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR;
$GLOBALS["ENGINE_PATH"] = $enginePath;
require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "runtime_bootstrap.php";
chimRuntimeBootstrap($enginePath, [
    'load_general_settings' => true,
    'load_player_name' => true,
    'load_narrator' => true,
]);

require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "logger.php";
require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "dynamic_update_util.php";
require_once $enginePath . "lib/core/npc_master.class.php";
require_once $enginePath . "lib/core/core_profiles.class.php";
require_once $enginePath . "lib/core/llm_connector.class.php";
require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "lazy_xml.php";
require_once __DIR__ . DIRECTORY_SEPARATOR . "ai_profile_generation_service.php";

$db = $GLOBALS["db"];
$jsonDataInput = aiProfileMergeRequestData();

$selectedEvents = [];
$selectedEventsProvided = array_key_exists('selected_events', $jsonDataInput);
if ($selectedEventsProvided) {
    $rawSelectedEvents = trim((string)$jsonDataInput['selected_events']);
    if ($rawSelectedEvents !== '') {
        $decodedSelectedEvents = json_decode($rawSelectedEvents, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo json_encode([
                'done' => false,
                'error' => 'Invalid selected event payload.',
                'error_type' => 'invalid_selected_events',
            ]);
            exit;
        }

        if (is_array($decodedSelectedEvents)) {
            $selectedEvents = $decodedSelectedEvents;
        }
    }
}

$result = aiProfileGenerate([
    'db' => $db,
    'name' => $jsonDataInput['name'] ?? '',
    'connector_id' => $jsonDataInput['connector_id'] ?? '',
    'user_prompt' => $jsonDataInput['user_prompt'] ?? '',
    'event_limit' => $jsonDataInput['event_limit'] ?? 100,
    'selected_events' => $selectedEvents,
    'selected_events_provided' => $selectedEventsProvided,
    'source' => 'manual',
]);

echo json_encode($result);
