<?php

$enginePath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR;

require_once($enginePath . 'lib' . DIRECTORY_SEPARATOR . 'runtime_bootstrap.php');
chimRuntimeBootstrap($enginePath, [
    'load_general_settings' => true,
    'load_player_name' => true,
]);

require_once($enginePath . 'lib' . DIRECTORY_SEPARATOR . 'logger.php');
require_once($enginePath . 'lib' . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'player.class.php');
require_once($enginePath . 'lib' . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'tts_connector.class.php');

const CHIM_PORTABLE_MAX_BYTES = 1048576;

// Portable fields intentionally omit live playthrough data, connector IDs, secrets, and local service URLs.
function chimPortablePlayerFields(): array
{
    return [
        'player_name' => 'string',
        'appearance' => 'string',
        'bio' => 'string',
        'bio_known_by_all' => 'boolean_true_false',
        'speech_style' => 'string',
        'diary_enabled' => 'boolean_10',
        'auto_diary_enabled' => 'boolean_10',
        'auto_diary_wait_enabled' => 'boolean_10',
        'tts_voice_override' => 'string',
        'tts_voice_id_override' => 'string',
        'tts_language_override' => 'string',
        'tts_elevenlabs_model_id' => 'string',
        'tts_elevenlabs_speed' => 'string',
        'tts_elevenlabs_stability' => 'string',
        'tts_elevenlabs_similarity_boost' => 'string',
        'tts_elevenlabs_style' => 'string',
        'tts_elevenlabs_use_speaker_boost' => 'string',
        'tts_elevenlabs_v3_audio_tags' => 'string',
    ];
}

function chimPortableGlobalFields(): array
{
    return [
        'PROMPT_HEAD' => 'string',
        'EMOTEMOODS' => 'string',
        'RECHAT_MODE' => 'string',
        'ENFORCE_STRICT_RECHAT_RESPONSE' => 'boolean',
        'OGHMA_INFINIUM' => 'boolean',
        'OGHMA_AMOUNT' => 'integer',
        'RACIAL_OGHMA' => 'boolean',
        'LOCATION_OGHMA' => 'boolean',
        'FEATURES@MEMORY_EMBEDDING@ENABLED' => 'boolean',
        'FEATURES@MEMORY_EMBEDDING@USE_TEXT2VEC' => 'boolean',
        'FEATURES@MEMORY_EMBEDDING@AUTO_CREATE_SUMMARY_INTERVAL' => 'integer',
        'PLAYER_WORST_MEMORY_GAME_DAYS' => 'integer',
        'AUTO_LOCK_PROFILE' => 'boolean',
        'AUTOFILL_CUSTOM_PROFILES' => 'boolean',
        'AUTOFILL_CUSTOM_PROFILES_TRIGGER' => 'integer',
        'BGL_TRIGGER_HOURS' => 'number',
        'END_CONVERSATION_COOLDOWN' => 'integer',
        'CHIM_AI_QUEST_PROGRESSION' => 'boolean',
        'CHIM_PLAYER_ONLY_QUEST_ADVANCEMENT' => 'boolean',
        'DETECT_MAGIC_EVENT' => 'boolean',
        'GROUND_ITEMS_DESCRIPTIONS_ONLY' => 'boolean',
        'INVENTORY_ITEMS_DESCRIPTIONS_ONLY' => 'boolean',
        'HIDE_AMBIENT_COMBAT' => 'boolean',
        'DISABLE_REANIMATION_TRACKING' => 'boolean',
        'TRANSFORMATION_DETECTION' => 'boolean',
        'POWER_AWARENESS_ENABLED' => 'boolean',
        'CHIM_ITEM_PICKUP_EVENTLOG_MIN_VALUE' => 'integer',
        'PROMPT_TIMESTAMP' => 'boolean',
        'MAGIC_EVENT_BLACKLIST' => 'string',
        'LOCATION_BLACKLIST' => 'string',
        'ITEM_BLACKLIST' => 'string',
        'EVENT_TYPE_FILTER' => 'string',
        'PROMPT_CONTEXT_OPTIONS' => 'prompt_context',
        'TRANSLATION_FUNCTION' => 'string',
        'TRANSLATION@settings@translate_audio' => 'boolean',
        'TRANSLATION@settings@translate_text' => 'boolean',
        'TRANSLATION@settings@save_translated_text' => 'boolean',
        'TRANSLATION@settings@translate_player_audio' => 'boolean',
        'TRANSLATION@settings@save_translated_player_text' => 'boolean',
        'TRANSLATION@DeepL@source_language' => 'string',
        'TRANSLATION@DeepL@target_language' => 'string',
        'TRANSLATION@DeepL@player_source_language' => 'string',
        'TRANSLATION@DeepL@player_target_language' => 'string',
        'RELATIONSHIP_SYSTEM_ENABLED' => 'boolean',
        'SCENE_CLASSIFIER_ENABLED' => 'boolean',
        'OGHMA_CUSTOM' => 'boolean',
    ];
}

function chimPortableServerVersion(string $enginePath): string
{
    $versionPath = $enginePath . '.version_number.txt';
    $version = is_file($versionPath) ? trim(strval(file_get_contents($versionPath))) : '';
    return $version !== '' ? $version : 'unknown';
}

function chimPortableJsonResponse(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function chimPortableNormalize($value, string $type, bool &$valid)
{
    $valid = true;
    if ($type === 'string') {
        if (is_array($value) || is_object($value)) {
            $valid = false;
            return null;
        }
        return trim(strval($value ?? ''));
    }

    if (in_array($type, ['boolean', 'boolean_10', 'boolean_true_false'], true)) {
        if (is_bool($value)) {
            return $value;
        }
        if (in_array($value, [1, '1', 'true'], true)) {
            return true;
        }
        if (in_array($value, [0, '0', 'false'], true)) {
            return false;
        }
        $valid = false;
        return null;
    }

    if ($type === 'integer') {
        if (!is_numeric($value)) {
            $valid = false;
            return null;
        }
        return intval($value);
    }

    if ($type === 'number') {
        if (!is_numeric($value)) {
            $valid = false;
            return null;
        }
        return floatval($value);
    }

    if ($type === 'prompt_context') {
        if (!is_array($value)) {
            $valid = false;
            return null;
        }
        return chimNormalizePromptContextOptions($value);
    }

    $valid = false;
    return null;
}

function chimPortableTypedGlobalValue(string $name, string $type)
{
    if ($type === 'boolean') {
        return chimGetGeneralSettingBool($name, false);
    }
    if ($type === 'integer') {
        return chimGetGeneralSettingInt($name, 0);
    }
    if ($type === 'number') {
        return chimGetGeneralSettingFloat($name, 0.0);
    }
    if ($type === 'prompt_context') {
        return chimGetPromptContextOptions();
    }
    return chimGetGeneralSetting($name, '');
}

function chimPortableDownload(string $scope, string $enginePath): void
{
    $version = chimPortableServerVersion($enginePath);
    $export = [
        'package_type' => $scope === 'player' ? 'chim_player_settings' : 'chim_global_settings',
        'export_version' => $version,
        'exported_at' => date('c'),
        'settings' => [],
    ];

    if ($scope === 'player') {
        $player = new Player();
        $allPlayerData = $player->getAll();
        foreach (chimPortablePlayerFields() as $name => $type) {
            $raw = $allPlayerData[$name] ?? '';
            if (in_array($type, ['boolean_10', 'boolean_true_false'], true)) {
                $export['settings'][$name] = in_array(strtolower(trim(strval($raw))), ['1', 'true', 'yes', 'on'], true);
            } else {
                $export['settings'][$name] = strval($raw);
            }
        }

        $connectorId = trim(strval($allPlayerData['tts_connector_id'] ?? ''));
        $export['tts_connector'] = null;
        if ($connectorId !== '') {
            $connector = (new TTSConnector())->readOne($connectorId);
            if (is_array($connector)) {
                $export['tts_connector'] = [
                    'label' => strval($connector['label'] ?? ''),
                    'driver' => strval($connector['driver'] ?? ''),
                ];
            }
        }

        $safeName = trim(strval(preg_replace('/[^a-z0-9_-]+/i', '_', strtolower(trim(strval($allPlayerData['player_name'] ?? 'player'))))), '_');
        $filename = ($safeName !== '' ? $safeName : 'player') . '_player_settings.json';
    } else {
        foreach (chimPortableGlobalFields() as $name => $type) {
            $export['settings'][$name] = chimPortableTypedGlobalValue($name, $type);
        }
        $filename = 'chim_global_settings.json';
    }

    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store');
    echo json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function chimPortableImport(string $scope, array $export, string $enginePath): array
{
    $expectedType = $scope === 'player' ? 'chim_player_settings' : 'chim_global_settings';
    if (($export['package_type'] ?? '') !== $expectedType) {
        throw new InvalidArgumentException('This file is not a ' . ($scope === 'player' ? 'Player' : 'Global Settings') . ' export.');
    }

    $exportVersion = trim(strval($export['export_version'] ?? ''));
    if ($exportVersion === '') {
        throw new InvalidArgumentException('The export does not include a server version.');
    }
    if (!isset($export['settings']) || !is_array($export['settings'])) {
        throw new InvalidArgumentException('The export does not contain valid settings.');
    }

    $manifest = $scope === 'player' ? chimPortablePlayerFields() : chimPortableGlobalFields();
    $settings = $export['settings'];
    $applied = [];
    $skippedInvalid = [];
    $skippedUnknown = array_values(array_diff(array_keys($settings), array_keys($manifest)));
    $warnings = [];
    $db = $GLOBALS['db'];
    $transactionStarted = false;

    $currentVersion = chimPortableServerVersion($enginePath);
    if ($currentVersion !== 'unknown' && preg_match('/^\d+\.\d+\.\d+/', $exportVersion)) {
        if (version_compare($exportVersion, $currentVersion, '<')) {
            $warnings[] = "Imported settings from older CHIM {$exportVersion}; settings absent from the file were left unchanged.";
        } elseif (version_compare($exportVersion, $currentVersion, '>')) {
            $warnings[] = "Imported settings from newer CHIM {$exportVersion}; unsupported settings were skipped.";
        }
    }

    try {
        if ($db->query('BEGIN') === false) {
            throw new RuntimeException('Could not start settings import transaction.');
        }
        $transactionStarted = true;

        if ($scope === 'player') {
            $player = new Player();
            foreach ($manifest as $name => $type) {
                if (!array_key_exists($name, $settings)) {
                    continue;
                }
                $valid = false;
                $value = chimPortableNormalize($settings[$name], $type, $valid);
                if (!$valid) {
                    $skippedInvalid[] = $name;
                    continue;
                }
                if ($type === 'boolean_true_false') {
                    $value = $value ? 'true' : 'false';
                } elseif ($type === 'boolean_10') {
                    $value = $value ? '1' : '0';
                } else {
                    $value = strval($value);
                }
                if (!$player->set($name, $value)) {
                    throw new RuntimeException("Could not import player setting {$name}.");
                }
                $applied[] = $name;
            }

            if (array_key_exists('tts_connector', $export)) {
                $connectorRef = $export['tts_connector'];
                if ($connectorRef === null) {
                    if (!$player->set('tts_connector_id', '')) {
                        throw new RuntimeException('Could not clear the Player TTS connector.');
                    }
                    $applied[] = 'tts_connector';
                } elseif (is_array($connectorRef)) {
                    $label = trim(strval($connectorRef['label'] ?? ''));
                    $driver = trim(strval($connectorRef['driver'] ?? ''));
                    if ($label !== '' && $driver !== '') {
                        $connector = $db->fetchOne(
                            'SELECT id FROM core_tts_connector WHERE LOWER(label) = LOWER(' . $db->escapeLiteral($label) .
                            ') AND LOWER(driver) = LOWER(' . $db->escapeLiteral($driver) . ') LIMIT 1'
                        );
                        if (is_array($connector) && !empty($connector['id'])) {
                            if (!$player->set('tts_connector_id', strval($connector['id']))) {
                                throw new RuntimeException('Could not import the Player TTS connector.');
                            }
                            $applied[] = 'tts_connector';
                        } else {
                            $warnings[] = "TTS connector '{$label}' was not found; the current Player TTS connector was left unchanged.";
                        }
                    } else {
                        $skippedInvalid[] = 'tts_connector';
                    }
                } else {
                    $skippedInvalid[] = 'tts_connector';
                }
            }
        } else {
            foreach ($manifest as $name => $type) {
                if (!array_key_exists($name, $settings)) {
                    continue;
                }
                $valid = false;
                $value = chimPortableNormalize($settings[$name], $type, $valid);
                if (!$valid) {
                    $skippedInvalid[] = $name;
                    continue;
                }
                if (!chimSetGeneralSetting($name, $value, null)) {
                    throw new RuntimeException("Could not import global setting {$name}.");
                }
                $applied[] = $name;
            }
        }

        if ($db->query('COMMIT') === false) {
            throw new RuntimeException('Could not commit settings import.');
        }
        $transactionStarted = false;
        if ($scope === 'global') {
            chimLoadGeneralSettingsIntoGlobals();
        }
    } catch (Throwable $e) {
        if ($transactionStarted) {
            $db->query('ROLLBACK');
        }
        throw $e;
    }

    return [
        'applied' => $applied,
        'skipped_unknown' => $skippedUnknown,
        'skipped_invalid' => array_values(array_unique($skippedInvalid)),
        'warnings' => $warnings,
        'export_version' => $exportVersion,
        'current_version' => $currentVersion,
    ];
}

$scope = strtolower(trim(strval($_GET['scope'] ?? '')));
if (!in_array($scope, ['player', 'global'], true)) {
    chimPortableJsonResponse(['ok' => false, 'error' => 'Invalid settings scope.'], 400);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'export') {
    chimPortableDownload($scope, $enginePath);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    chimPortableJsonResponse(['ok' => false, 'error' => 'Unsupported request.'], 405);
}

$rawBody = file_get_contents('php://input');
if (!is_string($rawBody) || $rawBody === '' || strlen($rawBody) > CHIM_PORTABLE_MAX_BYTES) {
    chimPortableJsonResponse(['ok' => false, 'error' => 'Import file is empty or exceeds 1 MB.'], 400);
}

$request = json_decode($rawBody, true);
if (!is_array($request) || !isset($request['export']) || !is_array($request['export'])) {
    chimPortableJsonResponse(['ok' => false, 'error' => 'Invalid JSON import data.'], 400);
}

try {
    $result = chimPortableImport($scope, $request['export'], $enginePath);
    Logger::info('[SETTINGS_IMPORT] Imported ' . count($result['applied']) . " {$scope} settings from CHIM " . $result['export_version']);
    chimPortableJsonResponse(['ok' => true, 'result' => $result]);
} catch (InvalidArgumentException $e) {
    chimPortableJsonResponse(['ok' => false, 'error' => $e->getMessage()], 400);
} catch (Throwable $e) {
    Logger::error('[SETTINGS_IMPORT] ' . $e->getMessage());
    chimPortableJsonResponse(['ok' => false, 'error' => 'Import failed without changing existing settings.'], 500);
}
