<?php

ob_start();

$enginePath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR;
$GLOBALS["ENGINE_PATH"] = $enginePath;

require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "runtime_bootstrap.php");
chimRuntimeBootstrap($enginePath, [
    'load_general_settings' => true,
    'load_stt_connector' => false,
    'load_itt_connector' => false,
    'load_tts_connector' => false,
    'load_player_name' => true,
    'load_narrator' => true,
]);

require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "logger.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "core" . DIRECTORY_SEPARATOR . "core_profiles.class.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "core" . DIRECTORY_SEPARATOR . "llm_connector.class.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "core" . DIRECTORY_SEPARATOR . "tts_connector.class.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "core" . DIRECTORY_SEPARATOR . "itt_connector.class.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "core" . DIRECTORY_SEPARATOR . "api_badge.class.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "core" . DIRECTORY_SEPARATOR . "local_llm_setup.php");

if (!isset($GLOBALS["db"])) {
    $GLOBALS["db"] = new sql();
}

function profileConnectorTestsRespond(array $payload, int $statusCode = 200): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function profileConnectorTestsString($value): string
{
    return trim(strval($value ?? ''));
}

function profileConnectorTestsBoolish($value): bool
{
    if (is_bool($value)) {
        return $value;
    }

    $normalized = strtolower(trim(strval($value ?? '')));
    return $normalized !== '' && $normalized !== '0' && $normalized !== 'false' && $normalized !== 'no' && $normalized !== 'off';
}

function profileConnectorTestsDecodeMetadata($raw): array
{
    if (is_array($raw)) {
        return $raw;
    }

    $decoded = json_decode(strval($raw ?? '{}'), true);
    return is_array($decoded) ? $decoded : [];
}

function profileConnectorTestsConnectorLabel(array $row, string $fallback): string
{
    foreach (['label', 'model', 'driver'] as $field) {
        $value = profileConnectorTestsString($row[$field] ?? '');
        if ($value !== '') {
            return $value;
        }
    }

    return $fallback;
}

function profileConnectorTestsApiBadgeStatus($apiBadgeId): array
{
    $id = intval($apiBadgeId ?? 0);
    if ($id <= 0) {
        return [
            'status' => 'missing',
            'message' => 'No API key badge selected',
        ];
    }

    $apiBadge = new ApiBadge();
    $row = $apiBadge->getById($id);
    if (!is_array($row)) {
        return [
            'status' => 'missing',
            'message' => "API key badge #{$id} was not found",
        ];
    }

    $label = profileConnectorTestsString($row['label'] ?? ("Badge #{$id}"));
    $apiKey = profileConnectorTestsString($row['api_key'] ?? '');
    if ($apiKey === '') {
        return [
            'status' => 'empty',
            'message' => "API key badge '{$label}' has no key configured",
            'label' => $label,
        ];
    }

    return [
        'status' => 'ok',
        'message' => "API key badge '{$label}' is configured",
        'label' => $label,
    ];
}

function profileConnectorTestsLlmRequiresApiKey(array $row): bool
{
    $driver = strtolower(profileConnectorTestsString($row['driver'] ?? ''));
    $service = strtolower(profileConnectorTestsString($row['service'] ?? ''));
    $provider = strtolower(profileConnectorTestsString($row['provider'] ?? ''));
    $url = strtolower(profileConnectorTestsString($row['url'] ?? ''));

    if ($driver === 'openaijson' && herikaLocalLlmUrlIsAllowed($url)) {
        return false;
    }

    $remoteDrivers = [
        'anthropic',
        'google_openaijson',
        'groqjson',
        'openai',
        'openaijson',
        'openrouter',
        'openrouterjson',
    ];

    if (in_array($driver, $remoteDrivers, true)) {
        return true;
    }

    foreach (['anthropic', 'google', 'groq', 'openai', 'openrouter', 'mistral'] as $needle) {
        if (strpos($service, $needle) !== false || strpos($provider, $needle) !== false || strpos($url, $needle) !== false) {
            return true;
        }
    }

    return false;
}

function profileConnectorTestsProblemResult(string $type, int $id, string $status, string $message, array $details = []): array
{
    return [
        'job_key' => $type . ':' . $id,
        'type' => $type,
        'id' => $id,
        'status' => $status,
        'message' => $message,
        'details' => $details,
        'elapsed_ms' => 0,
    ];
}

function profileConnectorTestsRunWithCapturedErrors(callable $callback): array
{
    $errors = [];
    $previousHandler = set_error_handler(function ($errno, $errstr, $errfile, $errline) use (&$errors) {
        $errors[] = [
            'level' => intval($errno),
            'message' => strval($errstr),
            'file' => strval($errfile),
            'line' => intval($errline),
        ];
        return true;
    });

    try {
        $value = $callback();
    } catch (Throwable $e) {
        $errors[] = [
            'level' => E_ERROR,
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ];
        $value = null;
    } finally {
        if ($previousHandler !== null) {
            set_error_handler($previousHandler);
        } else {
            restore_error_handler();
        }
    }

    return [
        'value' => $value,
        'errors' => $errors,
    ];
}

function profileConnectorTestsFirstErrorMessage(array $errors): string
{
    if (empty($errors)) {
        return '';
    }

    $first = $errors[0];
    $message = profileConnectorTestsString($first['message'] ?? '');
    if ($message === '') {
        $message = 'Unknown connector error';
    }

    return $message;
}

function profileConnectorTestsBuildPlan(): array
{
    $slotDefinitions = [
        ['field' => 'tts_connector_id', 'type' => 'tts', 'label' => 'TTS Connector', 'required' => false],
        ['field' => 'llm_primary_id', 'type' => 'llm', 'label' => 'Standard LLM', 'required' => true],
        ['field' => 'llm_secondary_id', 'type' => 'llm', 'label' => 'Fast LLM', 'required' => false],
        ['field' => 'llm_tertiary_id', 'type' => 'llm', 'label' => 'Powerful LLM', 'required' => false],
        ['field' => 'llm_quaternary_id', 'type' => 'llm', 'label' => 'Experimental LLM', 'required' => false],
        ['field' => 'diary_connector_id', 'type' => 'llm', 'label' => 'Diary LLM', 'required' => false],
        ['field' => 'llm_formatter_id', 'type' => 'llm', 'label' => 'Formatter LLM', 'required' => false],
        ['field' => 'llm_fallback_id', 'type' => 'llm', 'label' => 'Fallback LLM', 'required' => false],
        ['field' => 'itt_connector_id', 'type' => 'itt', 'label' => 'ITT Connector', 'required' => false],
    ];

    $profiles = (new CoreProfile())->readAll();
    $jobs = [];
    $profileRows = [];

    foreach ($profiles as $profile) {
        $profileId = intval($profile['id'] ?? 0);
        $slots = [];
        foreach ($slotDefinitions as $definition) {
            $rawConnectorId = $profile[$definition['field']] ?? null;
            $connectorId = intval($rawConnectorId ?? 0);

            if ($connectorId <= 0) {
                $slots[] = [
                    'field' => $definition['field'],
                    'type' => $definition['type'],
                    'label' => $definition['label'],
                    'required' => $definition['required'],
                    'connector_id' => null,
                    'job_key' => null,
                    'status' => 'skipped',
                    'message' => 'No connector selected',
                ];
                continue;
            }

            $jobKey = $definition['type'] . ':' . $connectorId;
            $jobs[$jobKey] = [
                'job_key' => $jobKey,
                'type' => $definition['type'],
                'id' => $connectorId,
            ];
            $slots[] = [
                'field' => $definition['field'],
                'type' => $definition['type'],
                'label' => $definition['label'],
                'required' => $definition['required'],
                'connector_id' => $connectorId,
                'job_key' => $jobKey,
                'status' => 'pending',
                'message' => 'Waiting to test',
            ];
        }

        $profileRows[] = [
            'id' => $profileId,
            'label' => profileConnectorTestsString($profile['label'] ?? ("Profile #{$profileId}")),
            'default_npc' => profileConnectorTestsBoolish($profile['default_npc'] ?? false),
            'default_narrator' => profileConnectorTestsBoolish($profile['default_narrator'] ?? false),
            'slots' => $slots,
        ];
    }

    return [
        'profiles' => $profileRows,
        'jobs' => array_values($jobs),
    ];
}

function profileConnectorTestsGlobalValue(string $fieldName, $default = '')
{
    $schemaDefault = $default;
    if (function_exists('chimGetSchemaDefinition')) {
        $definition = chimGetSchemaDefinition($fieldName);
        if (array_key_exists('default', $definition)) {
            $schemaDefault = $definition['default'];
        }
    }

    if (function_exists('chimReadLegacyGlobalValue')) {
        return chimReadLegacyGlobalValue($fieldName, $schemaDefault);
    }

    return $GLOBALS[$fieldName] ?? $schemaDefault;
}

function profileConnectorTestsBuildGlobalPlan(): array
{
    $slotDefinitions = [
        ['field' => 'CORE_CONNECTOR_PLAYER', 'type' => 'llm', 'label' => 'Player Respeech', 'enabled_by' => 'PLAYER_RESPEECH'],
        ['field' => 'CORE_CONNECTOR_SUMMARY', 'type' => 'llm', 'label' => 'Summaries', 'enabled_by' => 'CORE_CONNECTOR_SUMMARY_ENABLED'],
        ['field' => 'CORE_CONNECTOR_MEDIUMTERM', 'type' => 'llm', 'label' => 'Background & Memory Tasks', 'enabled_by' => 'CORE_CONNECTOR_MEDIUMTERM_ENABLED'],
        ['field' => 'CORE_CONNECTOR_SCENECLASSIFIER', 'type' => 'llm', 'label' => 'Scene Classifier', 'enabled_by' => 'SCENE_CLASSIFIER_ENABLED', 'enabled_label' => 'Scene Classifier'],
        ['field' => 'CORE_CONNECTOR_PROFILES', 'type' => 'llm', 'label' => 'Profile Tasks', 'enabled_by' => 'CORE_CONNECTOR_PROFILES_ENABLED'],
        ['field' => 'CORE_CONNECTOR_DIRECTOR', 'type' => 'llm', 'label' => 'Director Mode', 'enabled_by' => 'CORE_CONNECTOR_DIRECTOR_ENABLED'],
        ['field' => 'CORE_CONNECTOR_BGL', 'type' => 'llm', 'label' => 'Background Life', 'enabled_by' => 'CORE_CONNECTOR_BGL_ENABLED'],
        ['field' => 'RELLLM_CONNECTOR', 'type' => 'llm', 'label' => 'Relationship Management', 'enabled_by' => 'RELATIONSHIP_SYSTEM_ENABLED', 'enabled_label' => 'Relationship Management'],
        ['field' => 'CORE_CONNECTOR_OGHMA_CUSTOM', 'type' => 'llm', 'label' => 'Oghma Extractor Fallback', 'enabled_by' => 'OGHMA_EXTRACTOR_FALLBACK', 'enabled_label' => 'Oghma Extractor Fallback'],
    ];

    $jobs = [];
    $slots = [];

    foreach ($slotDefinitions as $definition) {
        $enabledBy = profileConnectorTestsString($definition['enabled_by'] ?? '');
        if ($enabledBy !== '' && !profileConnectorTestsBoolish(profileConnectorTestsGlobalValue($enabledBy, false))) {
            $slots[] = [
                'field' => $definition['field'],
                'type' => $definition['type'],
                'label' => $definition['label'],
                'required' => false,
                'connector_id' => null,
                'job_key' => null,
                'status' => 'skipped',
                'message' => profileConnectorTestsString($definition['enabled_label'] ?? $definition['label']) . ' is disabled',
            ];
            continue;
        }

        $connectorId = intval(profileConnectorTestsGlobalValue($definition['field'], 0) ?? 0);
        if ($connectorId <= 0) {
            $slots[] = [
                'field' => $definition['field'],
                'type' => $definition['type'],
                'label' => $definition['label'],
                'required' => false,
                'connector_id' => null,
                'job_key' => null,
                'status' => 'skipped',
                'message' => 'No connector selected',
            ];
            continue;
        }

        $jobKey = $definition['type'] . ':' . $connectorId;
        $jobs[$jobKey] = [
            'job_key' => $jobKey,
            'type' => $definition['type'],
            'id' => $connectorId,
        ];
        $slots[] = [
            'field' => $definition['field'],
            'type' => $definition['type'],
            'label' => $definition['label'],
            'required' => false,
            'connector_id' => $connectorId,
            'job_key' => $jobKey,
            'status' => 'pending',
            'message' => 'Waiting to test',
        ];
    }

    return [
        'scope' => 'global',
        'profiles' => [[
            'id' => 'global',
            'label' => 'Global Connectors',
            'default_npc' => false,
            'default_narrator' => false,
            'slots' => $slots,
        ]],
        'jobs' => array_values($jobs),
    ];
}

function profileConnectorTestsTestLlm(int $connectorId): array
{
    $started = microtime(true);
    $llm = new LLMConnector();
    $connector = $llm->getById($connectorId);
    if (!is_array($connector) || intval($connector['id'] ?? 0) <= 0) {
        return profileConnectorTestsProblemResult('llm', $connectorId, 'fail', 'LLM connector was not found');
    }

    $driver = profileConnectorTestsString($connector['driver'] ?? '');
    $label = profileConnectorTestsConnectorLabel($connector, "LLM connector #{$connectorId}");
    $details = [
        'label' => $label,
        'driver' => $driver,
        'model' => profileConnectorTestsString($connector['model'] ?? ''),
        'url' => profileConnectorTestsString($connector['url'] ?? ''),
    ];

    if ($driver === '') {
        return profileConnectorTestsProblemResult('llm', $connectorId, 'fail', 'LLM connector has no driver selected', $details);
    }
    if (!preg_match('/^[A-Za-z0-9_]+$/', $driver)) {
        return profileConnectorTestsProblemResult('llm', $connectorId, 'fail', 'LLM connector driver has an invalid name', $details);
    }
    if (!file_exists($GLOBALS["ENGINE_PATH"] . "connector" . DIRECTORY_SEPARATOR . $driver . ".php")) {
        return profileConnectorTestsProblemResult('llm', $connectorId, 'fail', "LLM driver file '{$driver}.php' was not found", $details);
    }

    $model = profileConnectorTestsString($connector['model'] ?? '');
    if ($model === '' && !in_array(strtolower($driver), ['websocket', 'web_connector'], true)) {
        return profileConnectorTestsProblemResult('llm', $connectorId, 'fail', 'LLM connector has no model configured', $details);
    }

    if (profileConnectorTestsLlmRequiresApiKey($connector)) {
        $badge = profileConnectorTestsApiBadgeStatus($connector['api_badge_id'] ?? 0);
        $details['api_badge'] = $badge['label'] ?? '';
        if ($badge['status'] !== 'ok') {
            return profileConnectorTestsProblemResult('llm', $connectorId, 'fail', $badge['message'], $details);
        }
    }

    $run = profileConnectorTestsRunWithCapturedErrors(function () use ($llm, $connector, $driver) {
        $GLOBALS["HERIKA_NAME"] = 'CHIM Profile Test';
        $GLOBALS["PLAYER_NAME"] = $GLOBALS["PLAYER_NAME"] ?? 'Dragonborn';
        $GLOBALS["DEBUG_DATA"] = [];
        $GLOBALS["FUNCTIONS_ARE_ENABLED"] = false;
        $GLOBALS["PATCH_PROMPT_ENFORCE_ACTIONS"] = false;
        $GLOBALS["COMMAND_PROMPT"] = '';
        $GLOBALS["COMMAND_PROMPT_ENFORCE_ACTIONS"] = '';

        $llm->setOldGlobals($connector);
        require_once($GLOBALS["ENGINE_PATH"] . "connector" . DIRECTORY_SEPARATOR . $driver . ".php");
        $handler = new $driver();
        $contextData = [
            ['role' => 'system', 'content' => 'You are a connection health check. Reply with OK.'],
            ['role' => 'user', 'content' => 'Reply with exactly OK.'],
        ];

        $handler->open($contextData, []);
        $accumulated = '';
        $iterations = 0;
        while (!$handler->isDone() && $iterations < 2000) {
            $chunk = $handler->process();
            if ($chunk === -1) {
                break;
            }
            $accumulated .= strval($chunk);
            $iterations++;
        }

        $closed = $handler->close('profile_connector_test');
        return trim(strval($closed !== '' ? $closed : $accumulated));
    });

    $elapsedMs = intval(round((microtime(true) - $started) * 1000));
    $response = profileConnectorTestsString($run['value'] ?? '');
    if ($response === '') {
        $message = profileConnectorTestsFirstErrorMessage($run['errors']);
        if ($message === '') {
            $message = 'LLM test returned an empty response';
        }

        return [
            'job_key' => 'llm:' . $connectorId,
            'type' => 'llm',
            'id' => $connectorId,
            'status' => 'fail',
            'message' => $message,
            'details' => $details + ['errors' => $run['errors']],
            'elapsed_ms' => $elapsedMs,
        ];
    }

    return [
        'job_key' => 'llm:' . $connectorId,
        'type' => 'llm',
        'id' => $connectorId,
        'status' => empty($run['errors']) ? 'pass' : 'warn',
        'message' => empty($run['errors']) ? 'LLM responded successfully' : profileConnectorTestsFirstErrorMessage($run['errors']),
        'details' => $details + ['response_preview' => mb_substr($response, 0, 180), 'errors' => $run['errors']],
        'elapsed_ms' => $elapsedMs,
    ];
}

function profileConnectorTestsTestTts(int $connectorId): array
{
    $started = microtime(true);
    $tts = new TTSConnector();
    $connector = $tts->getById($connectorId);
    if (!is_array($connector) || intval($connector['id'] ?? 0) <= 0) {
        return profileConnectorTestsProblemResult('tts', $connectorId, 'fail', 'TTS connector was not found');
    }

    $driver = $tts->normalizeDriverValue($connector['driver'] ?? '');
    $label = profileConnectorTestsConnectorLabel($connector, "TTS connector #{$connectorId}");
    $details = [
        'label' => $label,
        'driver' => $driver,
        'url' => $tts->resolveConnectorUrl($connector),
    ];

    if ($driver === '' || $driver === 'none') {
        return profileConnectorTestsProblemResult('tts', $connectorId, 'skipped', 'TTS connector is disabled', $details);
    }

    if ($tts->driverUsesApiBadge($driver)) {
        $badge = profileConnectorTestsApiBadgeStatus($connector['api_badge_id'] ?? 0);
        $details['api_badge'] = $badge['label'] ?? '';
        if ($badge['status'] !== 'ok') {
            return profileConnectorTestsProblemResult('tts', $connectorId, 'fail', $badge['message'], $details);
        }
    }

    if ($tts->driverSupportsEditableUrl($driver) && $details['url'] === '') {
        return profileConnectorTestsProblemResult('tts', $connectorId, 'fail', 'TTS connector has no endpoint URL configured', $details);
    }

    require_once($GLOBALS["ENGINE_PATH"] . "lib" . DIRECTORY_SEPARATOR . "model_dynmodel.php");
    require_once($GLOBALS["ENGINE_PATH"] . "lib" . DIRECTORY_SEPARATOR . "data_functions.php");
    require_once($GLOBALS["ENGINE_PATH"] . "lib" . DIRECTORY_SEPARATOR . "chat_helper_functions.php");
    require_once($GLOBALS["ENGINE_PATH"] . "lib" . DIRECTORY_SEPARATOR . "online_translation.php");
    require_once($GLOBALS["ENGINE_PATH"] . "prompt.includes.php");

    $run = profileConnectorTestsRunWithCapturedErrors(function () use ($tts, $connector) {
        $originalTtsFunction = $GLOBALS["TTSFUNCTION"] ?? null;
        $originalName = $GLOBALS["HERIKA_NAME"] ?? null;
        $originalTrack = $GLOBALS["TRACK"] ?? [];

        try {
            $tts->setOldGlobals($connector);
            $GLOBALS["HERIKA_NAME"] = 'The Narrator';
            $GLOBALS["AVOID_TTS_CACHE"] = true;
            $GLOBALS["TTS_FFMPEG_FILTERS"] = [];
            $GLOBALS["HERIKA_ANIMATIONS"] = false;
            $GLOBALS["SCRIPTLINE_LISTENER"] = '';
            $GLOBALS["SCRIPTLINE_EXPRESSION"] = '';
            $GLOBALS["DEBUG_DATA"] = [];
            $GLOBALS["FEATURES"] = $GLOBALS["FEATURES"] ?? [];
            $GLOBALS["FEATURES"]["MISC"] = $GLOBALS["FEATURES"]["MISC"] ?? [];
            $GLOBALS["FEATURES"]["MISC"]["TTS_RANDOM_PITCH"] = false;
            $GLOBALS["PATCH_DONT_STORE_SPEECH_ON_DB"] = true;
            $GLOBALS["PATCH_OVERRIDE_VOICE"] = 'TheNarrator';
            unset($GLOBALS["PATCH_OVERRIDE_VOICE_ID"], $GLOBALS["PATCH_OVERRIDE_TTS_LANGUAGE"]);

            Translation::translate('CHIM profile connector test.');
            Translation::$sentences = [Translation::$response ?: 'CHIM profile connector test.'];
            returnLines(Translation::$sentences, false);
            Translation::reset();

            $generated = $GLOBALS["TRACK"]["FILES_GENERATED"][0] ?? '';
            return profileConnectorTestsString($generated);
        } finally {
            if ($originalTtsFunction === null) {
                unset($GLOBALS["TTSFUNCTION"]);
            } else {
                $GLOBALS["TTSFUNCTION"] = $originalTtsFunction;
            }
            if ($originalName === null) {
                unset($GLOBALS["HERIKA_NAME"]);
            } else {
                $GLOBALS["HERIKA_NAME"] = $originalName;
            }
            $GLOBALS["TRACK"] = $originalTrack;
            unset($GLOBALS["PATCH_DONT_STORE_SPEECH_ON_DB"], $GLOBALS["PATCH_OVERRIDE_VOICE"], $GLOBALS["SCRIPTLINE_ANIMATION_SENT"]);
        }
    });

    $elapsedMs = intval(round((microtime(true) - $started) * 1000));
    $generated = profileConnectorTestsString($run['value'] ?? '');
    if ($generated === '') {
        $message = profileConnectorTestsFirstErrorMessage($run['errors']);
        if ($message === '') {
            $message = 'TTS test did not produce audio';
        }

        return [
            'job_key' => 'tts:' . $connectorId,
            'type' => 'tts',
            'id' => $connectorId,
            'status' => 'fail',
            'message' => $message,
            'details' => $details + ['errors' => $run['errors']],
            'elapsed_ms' => $elapsedMs,
        ];
    }

    return [
        'job_key' => 'tts:' . $connectorId,
        'type' => 'tts',
        'id' => $connectorId,
        'status' => empty($run['errors']) ? 'pass' : 'warn',
        'message' => empty($run['errors']) ? 'TTS produced audio successfully' : profileConnectorTestsFirstErrorMessage($run['errors']),
        'details' => $details + ['generated_file' => basename($generated), 'errors' => $run['errors']],
        'elapsed_ms' => $elapsedMs,
    ];
}

function profileConnectorTestsTestItt(int $connectorId): array
{
    $started = microtime(true);
    $itt = new ITTConnector();
    $connector = $itt->getById($connectorId);
    if (!is_array($connector) || intval($connector['id'] ?? 0) <= 0) {
        return profileConnectorTestsProblemResult('itt', $connectorId, 'fail', 'ITT connector was not found');
    }

    $driver = $itt->normalizeDriverValue($connector['driver'] ?? '');
    $label = profileConnectorTestsConnectorLabel($connector, "ITT connector #{$connectorId}");
    $metadata = $itt->decodeMetadata($connector['metadata'] ?? '{}');
    $details = [
        'label' => $label,
        'driver' => $driver,
        'url' => profileConnectorTestsString($connector['url'] ?? ($metadata['url'] ?? ($metadata['URL'] ?? ''))),
    ];

    if ($driver === '') {
        return profileConnectorTestsProblemResult('itt', $connectorId, 'fail', 'ITT connector has no driver selected', $details);
    }
    if (!preg_match('/^[A-Za-z0-9_]+$/', $driver)) {
        return profileConnectorTestsProblemResult('itt', $connectorId, 'fail', 'ITT connector driver has an invalid name', $details);
    }
    $driverFile = $GLOBALS["ENGINE_PATH"] . "itt" . DIRECTORY_SEPARATOR . "itt-" . $driver . ".php";
    if (!file_exists($driverFile)) {
        return profileConnectorTestsProblemResult('itt', $connectorId, 'fail', "ITT driver file 'itt-{$driver}.php' was not found", $details);
    }

    if ($itt->driverUsesApiBadge($driver)) {
        $badge = profileConnectorTestsApiBadgeStatus($connector['api_badge_id'] ?? 0);
        $details['api_badge'] = $badge['label'] ?? '';
        if ($badge['status'] !== 'ok') {
            return profileConnectorTestsProblemResult('itt', $connectorId, 'fail', $badge['message'], $details);
        }
    }

    $sampleImage = $GLOBALS["ENGINE_PATH"] . "debug" . DIRECTORY_SEPARATOR . "data" . DIRECTORY_SEPARATOR . "sample.jpg";
    if (!file_exists($sampleImage)) {
        return profileConnectorTestsProblemResult('itt', $connectorId, 'fail', 'Sample image for ITT test was not found', $details);
    }

    $run = profileConnectorTestsRunWithCapturedErrors(function () use ($itt, $connector, $driverFile, $sampleImage) {
        $itt->setOldGlobals($connector);
        require_once($driverFile);
        return profileConnectorTestsString(itt($sampleImage, 'Health check. Reply briefly.'));
    });

    $elapsedMs = intval(round((microtime(true) - $started) * 1000));
    $response = profileConnectorTestsString($run['value'] ?? '');
    if ($response === '') {
        $message = profileConnectorTestsFirstErrorMessage($run['errors']);
        if ($message === '') {
            $message = 'ITT test returned an empty description';
        }

        return [
            'job_key' => 'itt:' . $connectorId,
            'type' => 'itt',
            'id' => $connectorId,
            'status' => 'fail',
            'message' => $message,
            'details' => $details + ['errors' => $run['errors']],
            'elapsed_ms' => $elapsedMs,
        ];
    }

    return [
        'job_key' => 'itt:' . $connectorId,
        'type' => 'itt',
        'id' => $connectorId,
        'status' => empty($run['errors']) ? 'pass' : 'warn',
        'message' => empty($run['errors']) ? 'ITT returned a description successfully' : profileConnectorTestsFirstErrorMessage($run['errors']),
        'details' => $details + ['response_preview' => mb_substr($response, 0, 180), 'errors' => $run['errors']],
        'elapsed_ms' => $elapsedMs,
    ];
}

$action = profileConnectorTestsString($_GET['action'] ?? $_POST['action'] ?? 'plan');
$scope = profileConnectorTestsString($_GET['scope'] ?? $_POST['scope'] ?? 'profiles');

try {
    if ($action === 'plan') {
        profileConnectorTestsRespond([
            'ok' => true,
            'plan' => $scope === 'global' ? profileConnectorTestsBuildGlobalPlan() : profileConnectorTestsBuildPlan(),
        ]);
    }

    if ($action === 'test') {
        $type = strtolower(profileConnectorTestsString($_GET['type'] ?? $_POST['type'] ?? ''));
        $id = intval($_GET['id'] ?? $_POST['id'] ?? 0);
        if (!in_array($type, ['llm', 'tts', 'itt'], true) || $id <= 0) {
            profileConnectorTestsRespond([
                'ok' => false,
                'error' => 'Invalid connector test request',
            ], 400);
        }

        if ($type === 'llm') {
            $result = profileConnectorTestsTestLlm($id);
        } elseif ($type === 'tts') {
            $result = profileConnectorTestsTestTts($id);
        } else {
            $result = profileConnectorTestsTestItt($id);
        }

        profileConnectorTestsRespond([
            'ok' => true,
            'result' => $result,
        ]);
    }

    profileConnectorTestsRespond([
        'ok' => false,
        'error' => 'Unknown action',
    ], 400);
} catch (Throwable $e) {
    profileConnectorTestsRespond([
        'ok' => false,
        'error' => $e->getMessage(),
    ], 500);
}
