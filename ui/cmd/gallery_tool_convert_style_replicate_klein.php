<?php

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {

    $jsonDataInput = json_decode(file_get_contents("php://input"), true);

    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    $enginePath = dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR;
    require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "runtime_bootstrap.php";
    chimRuntimeBootstrap($enginePath, [
        'load_general_settings' => true,
        'load_player_name' => true,
        'load_narrator' => true,
    ]);

    require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "model_dynmodel.php";
    require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "chat_helper_functions.php";
    require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "data_functions.php";
    require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "logger.php";

    $GLOBALS["ENGINE_PATH"] = $enginePath;

    $db = $GLOBALS["db"];

    require_once $enginePath . "lib/core/npc_master.class.php";
    require_once $enginePath . "lib/core/api_badge.class.php";
    require_once $enginePath . "lib/core/core_profiles.class.php";
    require_once $enginePath . "lib/core/llm_connector.class.php";
    require_once dirname(__FILE__) . "/handlers/ReplicateFluxKleinHandler.class.php";

    $apiBadge   = new ApiBadge();
    $apiKeyData = $apiBadge->getByLabel("replicate");
    $api_key    = $apiKeyData["api_key"];

    $source = $jsonDataInput["source"];

    $handler = new ReplicateFluxKleinHandler($api_key);

    $result = $handler->process([
        'prompt' => $jsonDataInput["userhint"] ?? "",
        'image'  => [
            'path'     => $source,
            'filename' => basename($source),
        ],
    ]);

    die(json_encode($result));
}
die(json_encode(["status" => "success"]));
