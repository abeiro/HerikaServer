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
require_once LIB_PATH . DIRECTORY_SEPARATOR . 'data_functions.php';
require_once LIB_PATH . DIRECTORY_SEPARATOR . 'rolemaster_helpers.php';
require_once LIB_PATH . DIRECTORY_SEPARATOR . 'scriptproxy_papyrus.php';
require_once LIB_PATH . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'npc_master.class.php';
require_once LIB_PATH . DIRECTORY_SEPARATOR . 'background_life_requests.php';
require_once LIB_PATH . DIRECTORY_SEPARATOR . 'background_life_npc_creation.php';

$GLOBALS['db'] = new sql();

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
        echo json_encode([
            'success' => true,
            'data' => chimBglNpcCreationOptions(),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        throw new RuntimeException('Unsupported request method');
    }

    $result = chimBglCreateNpc($_POST);
    if (!($result['ok'] ?? false)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $result['message'] ?? 'Failed to create NPC.',
            'data' => $result['form_data'] ?? null,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode([
        'success' => true,
        'message' => $result['message'],
        'data' => $result['npc'],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (Throwable $error) {
    Logger::error('Background Life NPC creation API failed: ' . $error->getMessage());
    if (http_response_code() < 400) {
        http_response_code(500);
    }
    echo json_encode(['success' => false, 'error' => $error->getMessage()]);
}
