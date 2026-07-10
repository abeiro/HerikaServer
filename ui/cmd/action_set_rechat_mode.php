<?php

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
header('Content-Type: application/json');

$enginePath = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR;

require_once($enginePath . 'lib' . DIRECTORY_SEPARATOR . 'runtime_bootstrap.php');
chimRuntimeBootstrap($enginePath, [
    'load_general_settings' => true,
    'load_player_name' => false,
    'load_narrator' => false,
]);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'ok' => false,
        'message' => 'POST required.',
    ]);
    exit;
}

$rechatMode = strtolower(trim(strval($_POST['mode'] ?? '')));
$allowedRechatModes = ['tight', 'conversational', 'group', 'random'];
if (!in_array($rechatMode, $allowedRechatModes, true)) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'message' => 'Unsupported rechat mode.',
    ]);
    exit;
}

try {
    $description = chimGetSchemaDescription('RECHAT_MODE');
    if (!chimSetGeneralSetting('RECHAT_MODE', $rechatMode, $description)) {
        throw new RuntimeException('Failed to persist RECHAT_MODE.');
    }

    chimLoadGeneralSettingsIntoGlobals();
    Logger::info('[CHATBOX] Global RECHAT_MODE changed to ' . $rechatMode);

    echo json_encode([
        'ok' => true,
        'rechat_mode' => $rechatMode,
    ]);
} catch (Throwable $e) {
    Logger::error('[CHATBOX] Failed to change RECHAT_MODE: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => 'Failed to update rechat mode.',
    ]);
}
