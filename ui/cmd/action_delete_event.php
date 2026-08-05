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

$rowId = intval($_POST['rowid'] ?? 0);
if ($rowId <= 0) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'message' => 'Invalid event row.',
    ]);
    exit;
}

try {
    $result = chimDeleteEventLogRow($GLOBALS['db'], $rowId);
    if (empty($result['ok'])) {
        http_response_code(500);
    }
    echo json_encode($result);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => 'Failed to delete event.',
    ]);
}
