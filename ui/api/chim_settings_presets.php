<?php

error_reporting(E_ERROR);
session_start();

define('BASE_PATH', dirname(dirname(__DIR__)));
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

require_once LIB_PATH . DIRECTORY_SEPARATOR . 'runtime_bootstrap.php';
chimRuntimeBootstrap(BASE_PATH . DIRECTORY_SEPARATOR, [
    'load_general_settings' => true,
    'load_stt_connector' => false,
    'load_itt_connector' => false,
]);
require_once LIB_PATH . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'settings_presets.php';

function chimSettingsPresetRespond(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if ($method === 'GET') {
        chimSettingsPresetEnsureStorage();
        chimSettingsPresetRespond(['success' => true, 'data' => chimSettingsPresetCatalog()]);
    }
    if ($method !== 'POST') {
        chimSettingsPresetRespond(['success' => false, 'error' => 'Method not allowed.'], 405);
    }

    $body = json_decode(file_get_contents('php://input'), true);
    if (!is_array($body)) {
        $body = $_POST;
    }
    $operation = trim((string)($body['operation'] ?? ''));
    if ($operation === 'apply') {
        $presetId = trim((string)($body['preset_id'] ?? ''));
        if ($presetId === '') {
            throw new InvalidArgumentException('Preset is required.');
        }
        chimSettingsPresetRespond(['success' => true, 'result' => chimSettingsPresetApply($presetId)]);
    }
    if ($operation === 'save_new' || $operation === 'overwrite') {
        $settings = $body['settings'] ?? null;
        if (!is_array($settings)) {
            throw new InvalidArgumentException('Settings payload is required.');
        }
        $snapshot = chimSettingsPresetCaptureSnapshot($settings, $body['prompt_context_options'] ?? null);
        if ($operation === 'save_new') {
            $preset = chimSettingsPresetSaveNew((string)($body['name'] ?? ''), $snapshot);
        } else {
            $preset = chimSettingsPresetOverwrite((string)($body['preset_id'] ?? ''), $snapshot);
        }
        chimSettingsPresetRespond(['success' => true, 'result' => ['preset' => $preset]]);
    }

    throw new InvalidArgumentException('Unknown preset operation.');
} catch (Throwable $e) {
    $status = $e instanceof InvalidArgumentException ? 400 : 500;
    chimSettingsPresetRespond(['success' => false, 'error' => $e->getMessage()], $status);
}
