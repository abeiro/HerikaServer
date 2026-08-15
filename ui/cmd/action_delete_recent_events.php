<?php

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
header('Content-Type: application/json');

$enginePath = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR;

require_once($enginePath . 'lib' . DIRECTORY_SEPARATOR . 'runtime_bootstrap.php');
chimRuntimeBootstrap($enginePath, [
    'load_general_settings' => true,
    'load_player_name' => true,
    'load_narrator' => true,
]);

require_once($enginePath . 'lib' . DIRECTORY_SEPARATOR . 'eventlog_helper.php');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'ok' => false,
        'message' => 'POST required.',
    ]);
    exit;
}

$deleteCount = intval($_POST['count'] ?? 0);
if (!in_array($deleteCount, [5, 10, 20, 50, 100], true)) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'message' => 'Unsupported delete count.',
    ]);
    exit;
}

try {
    $db = $GLOBALS["db"];
    $result = chimDeleteLatestVisibleEventLogRows($db, $deleteCount);
    if (empty($result['ok'])) {
        http_response_code(400);
    }

    echo json_encode($result);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => 'Failed to delete latest events.',
    ]);
}
