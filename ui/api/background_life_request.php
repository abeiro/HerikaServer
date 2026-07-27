<?php

error_reporting(E_ERROR);
session_start();

define('BASE_PATH', dirname(dirname(__DIR__)));
define('CONFIG_PATH', BASE_PATH . DIRECTORY_SEPARATOR . 'conf');
define('LIB_PATH', BASE_PATH . DIRECTORY_SEPARATOR . 'lib');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'POST required']);
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
    $refid = trim((string)($_POST['refid'] ?? ''));
    $npcName = trim((string)($_POST['npc_name'] ?? ''));
    $requestType = trim((string)($_POST['request_type'] ?? ''));
    $instruction = trim((string)($_POST['instruction'] ?? ''));
    if ($refid === '' && $npcName === '') {
        throw new InvalidArgumentException('NPC RefID or name is required');
    }
    if (!in_array($requestType, ['action', 'letter', 'instruction'], true)) {
        throw new InvalidArgumentException('Request type must be action, letter, or instruction');
    }

    $npc = chimBglResolveNpc($npcMaster, $refid, $npcName);
    if (!$npc) {
        http_response_code(404);
        throw new RuntimeException('NPC has not been discovered by CHIM');
    }

    $status = chimBglNpcStatus($npcMaster, $npc, $refid, $npcName);
    if (!$status['background_life_enabled']) {
        http_response_code(409);
        throw new DomainException('Enable Background Life before requesting an action');
    }

    $queueId = chimBglQueueRequest($GLOBALS['db'], $npc, $requestType, $instruction);
    $messages = [
        'letter' => 'Letter request queued',
        'instruction' => 'Direct Background Life instruction queued',
        'action' => 'Background action queued',
    ];
    echo json_encode([
        'success' => true,
        'message' => $messages[$requestType],
        'queue_id' => $queueId,
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
    Logger::error('Background Life request API failed: ' . $error->getMessage());
    if (http_response_code() < 400) {
        http_response_code(500);
    }
    echo json_encode(['success' => false, 'error' => $error->getMessage()]);
}
