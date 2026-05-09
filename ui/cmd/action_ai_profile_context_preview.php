<?php

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
header('Content-Type: application/json');

$jsonDataInput = array_merge($_GET ?? [], $_POST ?? []);

$enginePath = __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR;
require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "runtime_bootstrap.php";
chimRuntimeBootstrap($enginePath, [
    'load_general_settings' => true,
    'load_player_name' => true,
    'load_narrator' => true,
]);

require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "data_functions.php";
require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "logger.php";
require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "chat_helper_functions.php";
require_once $enginePath . "lib/core/npc_master.class.php";
require_once __DIR__ . DIRECTORY_SEPARATOR . "ai_profile_generation_helper.php";

$db = $GLOBALS["db"];
$name = trim((string)($jsonDataInput["name"] ?? ""));
$eventLimit = isset($jsonDataInput["event_limit"]) ? intval($jsonDataInput["event_limit"]) : 100;
$eventLimit = max(10, min(200, $eventLimit));

if ($name === '') {
    echo json_encode([
        "done" => false,
        "error" => "Missing NPC name."
    ]);
    exit;
}

$npcMaster = new NpcMaster();
$currentNpcData = $npcMaster->getByName($name);
if (!$currentNpcData) {
    echo json_encode([
        "done" => false,
        "error" => "NPC not found."
    ]);
    exit;
}

$preview = aiProfileBuildPreviewEvents($name, $currentNpcData, $db, $eventLimit);

echo json_encode([
    "done" => true,
    "events" => $preview["events"],
    "used_count" => $preview["used_count"],
    "total_available" => $preview["total_available"],
    "event_limit" => $eventLimit,
    "middle_term_memory_included" => !empty($npcMaster->getExtendedData($currentNpcData)["middle_term_memory"]),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
