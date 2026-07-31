<?php

error_reporting(E_ERROR);
session_start();

define('BASE_PATH', dirname(dirname(__DIR__)));
define('CONFIG_PATH', BASE_PATH . DIRECTORY_SEPARATOR . 'conf');
define('LIB_PATH', BASE_PATH . DIRECTORY_SEPARATOR . 'lib');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}
if (!file_exists(CONFIG_PATH . DIRECTORY_SEPARATOR . 'conf.php')) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Configuration file not found']);
    exit;
}

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'profile_loader.php';
require_once LIB_PATH . DIRECTORY_SEPARATOR . 'logger.php';
require_once LIB_PATH . DIRECTORY_SEPARATOR . "{$GLOBALS['DBDRIVER']}.class.php";
require_once LIB_PATH . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'npc_master.class.php';
require_once LIB_PATH . DIRECTORY_SEPARATOR . 'background_life_requests.php';
require_once LIB_PATH . DIRECTORY_SEPARATOR . 'background_life_dashboard.php';
require_once LIB_PATH . DIRECTORY_SEPARATOR . 'utils_game_timestamp.php';

$db = new sql();

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $operation = trim((string)($_POST['operation'] ?? ''));
        if ($operation !== 'save_settings') {
            throw new InvalidArgumentException('Unsupported dashboard operation');
        }

        $triggerHours = chimNormalizeBackgroundLifeTriggerHours((float)($_POST['trigger_hours'] ?? 24));
        if (!chimSetGeneralSetting('BGL_TRIGGER_HOURS', $triggerHours, chimGetSchemaDescription('BGL_TRIGGER_HOURS'))) {
            throw new RuntimeException('Could not save Background Life trigger time');
        }
        echo json_encode([
            'success' => true,
            'message' => 'Background Life trigger time saved',
            'settings' => ['trigger_hours' => $triggerHours],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    $scriptPath = (string)($_SERVER['SCRIPT_NAME'] ?? '');
    $webRoot = rtrim(dirname(dirname(dirname($scriptPath))), '/');
    $showAllCoords = chimBglBoolean($_GET['show_all_coords'] ?? false);
    echo json_encode([
        'success' => true,
        'data' => chimBglDashboardPayload($db, BASE_PATH, $webRoot, $showAllCoords),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $error) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $error->getMessage()]);
} catch (Throwable $error) {
    Logger::error('Background Life dashboard API failed: ' . $error->getMessage());
    if (http_response_code() < 400) {
        http_response_code(500);
    }
    echo json_encode(['success' => false, 'error' => 'Unable to load Background Life dashboard']);
}
