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

$GLOBALS['db'] = new sql();
$npcMaster = new NpcMaster();

try {
    $refid = trim((string)($_REQUEST['refid'] ?? ''));
    $npcName = trim((string)($_REQUEST['npc_name'] ?? ''));
    if ($refid === '' && $npcName === '') {
        throw new InvalidArgumentException('NPC RefID or name is required');
    }

    $npc = chimBglResolveNpc($npcMaster, $refid, $npcName);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (($_POST['operation'] ?? '') !== 'toggle') {
            throw new InvalidArgumentException('Unsupported operation');
        }
        if (!$npc) {
            http_response_code(404);
            throw new RuntimeException('NPC has not been discovered by CHIM');
        }

        $status = chimBglNpcStatus($npcMaster, $npc, $refid, $npcName);
        if (!$status['background_life_enabled']) {
            http_response_code(409);
            throw new DomainException('Enable Background Life before changing NPC settings');
        }

        $setting = trim((string)($_POST['setting'] ?? ''));
        $value = chimBglBoolean($_POST['value'] ?? false);
        $status = chimBglUpdateNpcSetting($npcMaster, $npc, $setting, $value);
        echo json_encode([
            'success' => true,
            'message' => 'NPC setting saved',
            'data' => $status,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode([
        'success' => true,
        'data' => chimBglNpcStatus($npcMaster, $npc, $refid, $npcName),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $error) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $error->getMessage()]);
} catch (DomainException $error) {
    if (http_response_code() < 400) {
        http_response_code(409);
    }
    echo json_encode(['success' => false, 'error' => $error->getMessage()]);
} catch (Throwable $error) {
    Logger::error('Background Life NPC API failed: ' . $error->getMessage());
    if (http_response_code() < 400) {
        http_response_code(500);
    }
    echo json_encode(['success' => false, 'error' => $error->getMessage()]);
}
