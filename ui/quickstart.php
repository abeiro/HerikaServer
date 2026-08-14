<?php 

header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1
header("Pragma: no-cache"); // HTTP 1.0
header("Expires: 0"); // Proxies

error_reporting(E_ERROR);
session_start();

ob_start();

$url = 'conf_editor.php';
$rootPath = __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR;
$configFilepath = $rootPath . "conf" . DIRECTORY_SEPARATOR;

require_once($rootPath . "lib" . DIRECTORY_SEPARATOR . "model_dynmodel.php");
// Load configuration files in the correct order
require_once($rootPath . "conf" . DIRECTORY_SEPARATOR . "conf.sample.php");  // Should contain defaults

function herikaQuickstartMiniMeDefaultUrl(): string {
    return 'http://127.0.0.1:8082/';
}

function herikaQuickstartProbeUrl(string $rawUrl): array {
    $result = [
        'ok' => false,
        'http_code' => 0,
        'latency_ms' => 0,
        'error' => '',
    ];

    $start = microtime(true);
    $parts = @parse_url($rawUrl);
    $scheme = strtolower(strval($parts['scheme'] ?? ''));
    $host = trim(strval($parts['host'] ?? ''));
    $port = intval($parts['port'] ?? 0);
    $path = strval($parts['path'] ?? '/');
    $query = strval($parts['query'] ?? '');

    if ($path === '') $path = '/';
    if ($query !== '') $path .= '?' . $query;
    if ($port <= 0) $port = ($scheme === 'https') ? 443 : 80;

    if (function_exists('curl_init')) {
        $ch = @curl_init($rawUrl);
        if ($ch) {
            @curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 4,
                CURLOPT_CONNECTTIMEOUT => 2,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 2,
                CURLOPT_HTTPHEADER => ['Accept: application/json, text/plain;q=0.9, */*;q=0.8'],
            ]);
            @curl_exec($ch);
            $httpCode = intval(@curl_getinfo($ch, CURLINFO_HTTP_CODE));
            $curlError = trim(strval(@curl_error($ch)));
            @curl_close($ch);

            $result['http_code'] = $httpCode;
            $result['latency_ms'] = intval(round((microtime(true) - $start) * 1000));
            if ($httpCode >= 200 && $httpCode < 500) {
                $result['ok'] = true;
            } else if ($curlError !== '') {
                $result['error'] = $curlError;
            } else {
                $result['error'] = 'HTTP ' . strval($httpCode) . ' from endpoint probe.';
            }
            return $result;
        }
    }

    $transport = ($scheme === 'https') ? 'ssl://' : 'tcp://';
    $target = $transport . $host . ':' . strval($port);
    $errno = 0;
    $errstr = '';
    $socket = @stream_socket_client($target, $errno, $errstr, 2.0, STREAM_CLIENT_CONNECT);
    if (!$socket) {
        $result['latency_ms'] = intval(round((microtime(true) - $start) * 1000));
        $result['error'] = trim($errstr) !== '' ? trim($errstr) : ('Connection failed (' . strval($errno) . ').');
        return $result;
    }

    @stream_set_timeout($socket, 4);
    $request =
        "GET " . $path . " HTTP/1.1\r\n" .
        "Host: " . $host . "\r\n" .
        "User-Agent: HerikaQuickstartProbe/1.0\r\n" .
        "Accept: */*\r\n" .
        "Connection: close\r\n\r\n";
    @fwrite($socket, $request);
    $statusLine = strval(@fgets($socket, 512));
    @fclose($socket);

    $result['latency_ms'] = intval(round((microtime(true) - $start) * 1000));
    if (preg_match('/^HTTP\/\d(?:\.\d)?\s+(\d{3})/i', $statusLine, $matches)) {
        $httpCode = intval($matches[1] ?? 0);
        $result['http_code'] = $httpCode;
        if ($httpCode >= 200 && $httpCode < 500) {
            $result['ok'] = true;
            return $result;
        }
        $result['error'] = 'HTTP ' . strval($httpCode) . ' from endpoint probe.';
        return $result;
    }

    $result['error'] = 'No HTTP response from endpoint.';
    return $result;
}

function herikaQuickstartEnsureActiveSttConnectorId(STTConnector $connector): int {
    $activeId = chimGetGeneralSettingInt('GLOBAL_STT_CONNECTOR_ID', 0);
    if ($activeId > 0) {
        $row = $connector->getById($activeId);
        if ($row) {
            return $activeId;
        }
    }

    $migrated = $connector->ensureLegacySelectionFromGlobals();
    if ($migrated && !empty($migrated['id'])) {
        $activeId = intval($migrated['id']);
        chimSetGeneralSetting('GLOBAL_STT_CONNECTOR_ID', $activeId, chimGetSchemaDescription('GLOBAL_STT_CONNECTOR_ID'));
        return $activeId;
    }

    $rows = $connector->readAll();
    if (!empty($rows)) {
        $activeId = intval($rows[0]['id'] ?? 0);
        if ($activeId > 0) {
            chimSetGeneralSetting('GLOBAL_STT_CONNECTOR_ID', $activeId, chimGetSchemaDescription('GLOBAL_STT_CONNECTOR_ID'));
        }
        return $activeId;
    }

    $driverOptions = $connector->getDriverOptions();
    $defaultDriver = 'deepgram';
    foreach ($driverOptions as $driverOption) {
        $candidate = $connector->normalizeDriverValue($driverOption);
        if ($candidate !== '' && $candidate !== 'none') {
            $defaultDriver = $candidate;
            break;
        }
    }

    $createdId = $connector->create([
        'driver' => $defaultDriver,
        'label' => ($defaultDriver === 'none') ? 'Disabled STT' : ('Global ' . $connector->getDisplayName($defaultDriver)),
        'metadata' => json_encode([], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        'api_badge_id' => $connector->getDefaultApiBadgeIdForDriver($defaultDriver),
        'url' => $connector->getDefaultUrlForDriver($defaultDriver),
    ]);
    if ($createdId > 0) {
        chimSetGeneralSetting('GLOBAL_STT_CONNECTOR_ID', $createdId, chimGetSchemaDescription('GLOBAL_STT_CONNECTOR_ID'));
    }
    return $createdId;
}

function herikaQuickstartNormalizeTtsDriver(TTSConnector $connector, $driver): string {
    $normalizedDriver = $connector->normalizeDriverValue($driver);
    if ($normalizedDriver === '') {
        $normalizedDriver = 'none';
    }
    return $normalizedDriver;
}

function herikaQuickstartEnsureTtsConnectorForDriver(TTSConnector $connector, string $selectedDriver): ?array {
    $selectedDriver = herikaQuickstartNormalizeTtsDriver($connector, $selectedDriver);

    $existingRow = chimResolvePreferredTtsConnectorRow($selectedDriver);
    if ($existingRow) {
        return $existingRow;
    }

    $metadata = $connector->applyForcedMetadataDefaults($selectedDriver, []);
    $payload = [
        'driver' => $selectedDriver,
        'label' => ($selectedDriver === 'none') ? 'Disabled TTS' : ('Default ' . $connector->getDisplayName($selectedDriver)),
        'metadata' => json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        'api_badge_id' => $connector->driverUsesApiBadge($selectedDriver)
            ? $connector->getDefaultApiBadgeIdForDriver($selectedDriver)
            : null,
        'url' => $connector->driverSupportsEditableUrl($selectedDriver)
            ? $connector->getDefaultUrlForDriver($selectedDriver)
            : null,
        'voice_field' => ($selectedDriver === 'none') ? null : $connector->getVoiceFieldForDriver($selectedDriver),
    ];

    $createdId = $connector->create($payload);
    if ($createdId <= 0) {
        return null;
    }

    return $connector->getById($createdId) ?: null;
}

function herikaQuickstartApplyTtsSelection(TTSConnector $connector, $selectedDriver): int {
    $selectedDriver = herikaQuickstartNormalizeTtsDriver($connector, $selectedDriver);
    $previousPreferredRow = chimResolvePreferredTtsConnectorRow();
    $previousPreferredId = intval($previousPreferredRow['id'] ?? 0);

    $selectedRow = herikaQuickstartEnsureTtsConnectorForDriver($connector, $selectedDriver);
    $selectedId = intval($selectedRow['id'] ?? 0);
    if ($selectedId <= 0) {
        return 0;
    }

    if ($previousPreferredId > 0 && $previousPreferredId !== $selectedId) {
        $GLOBALS['db']->query("UPDATE core_profiles SET tts_connector_id = {$selectedId} WHERE tts_connector_id = {$previousPreferredId}");
    }
    // QuickStart configures NPC and narrator TTS; Player TTS remains opt-in in Player Management.
    $GLOBALS['db']->query("UPDATE core_profiles SET tts_connector_id = {$selectedId} WHERE tts_connector_id IS NULL OR default_narrator = '1' OR default_npc = '1'");

    return $selectedId;
}

function herikaQuickstartGetLlmConnectorLabelById($db, int $id): string {
    if ($id <= 0 || !$db) {
        return '';
    }
    try {
        $row = $db->fetchOne("SELECT label FROM core_llm_connector WHERE id=" . intval($id) . " LIMIT 1");
    } catch (Throwable $_e) {
        $row = [];
    }
    return trim(strval($row['label'] ?? ''));
}

function herikaQuickstartGetGeneralLlmConnectorSummary($db): array {
    $items = [
        'CORE_CONNECTOR_SUMMARY' => 'Summaries',
        'CORE_CONNECTOR_MEDIUMTERM' => 'Background Life',
        'CORE_CONNECTOR_SCENECLASSIFIER' => 'Scene Classifier',
        'CORE_CONNECTOR_PROFILES' => 'Dynamic Profile',
        'CORE_CONNECTOR_DIRECTOR' => 'Director Mode',
        'RELLLM_CONNECTOR' => 'Relationship Management',
        'CORE_CONNECTOR_OGHMA_CUSTOM' => 'Custom Oghma LLM',
    ];

    $summary = [];
    foreach ($items as $settingId => $displayName) {
        $connectorId = chimGetGeneralSettingInt($settingId, 0);
        $label = herikaQuickstartGetLlmConnectorLabelById($db, $connectorId);
        if ($label === '') {
            continue;
        }
        $summary[] = [
            'name' => $displayName,
            'label' => $label,
        ];
    }

    return $summary;
}

if (isset($_GET['minime_probe']) && strval($_GET['minime_probe']) === '1') {
    header('Content-Type: application/json; charset=utf-8');
    $rawUrl = trim(strval($_GET['url'] ?? herikaQuickstartMiniMeDefaultUrl()));
    $result = [
        'ok' => false,
        'url' => $rawUrl,
        'http_code' => 0,
        'latency_ms' => 0,
        'message' => 'Invalid URL',
    ];

    if ($rawUrl === '') {
        $result['message'] = 'URL is required.';
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $parts = @parse_url($rawUrl);
    $scheme = strtolower(strval($parts['scheme'] ?? ''));
    $host = trim(strval($parts['host'] ?? ''));
    if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
        $result['message'] = 'Use a valid http:// or https:// URL.';
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $probe = herikaQuickstartProbeUrl($rawUrl);
    $result['http_code'] = intval($probe['http_code'] ?? 0);
    $result['latency_ms'] = intval($probe['latency_ms'] ?? 0);
    $result['ok'] = !empty($probe['ok']);
    if ($result['ok']) {
        $result['message'] = 'MiniMe service reachable.';
    } else {
        $result['message'] = trim(strval($probe['error'] ?? '')) ?: 'MiniMe service not reachable.';
    }

    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// Inline quicksave handlers
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['qs_action'])) {
    try { require_once($rootPath . "lib" .DIRECTORY_SEPARATOR."{$GLOBALS["DBDRIVER"]}.class.php"); } catch (Throwable $_e) {}
    try { if (!isset($GLOBALS['db']) || !$GLOBALS['db']) { $GLOBALS['db'] = new sql(); } } catch (Throwable $_e) {}
    // Ensure database schema/tables exist before handling any quicksave actions
    try { require_once($rootPath . "debug" . DIRECTORY_SEPARATOR . "db_updates.php"); } catch (Throwable $_e) {}
    try { require_once($rootPath . "lib" . DIRECTORY_SEPARATOR . "llm_randomizer.php"); } catch (Throwable $_e) {}
    header('Content-Type: application/json');

    $action = (string)($_POST['qs_action'] ?? '');

    if ($action === 'api_badge_quicksave') {
        $openrouter = isset($_POST['openrouter_api_key']) ? (string)$_POST['openrouter_api_key'] : '';
        $deepgram = isset($_POST['deepgram_api_key']) ? (string)$_POST['deepgram_api_key'] : '';
        $save = function($labelLower, $apiKey) {
            if ($labelLower === '' || $apiKey === null) return;
            $db = $GLOBALS['db'];
            $row = $db->fetchOne("SELECT id FROM core_api_badge WHERE lower(label)='" . $db->escape($labelLower) . "' LIMIT 1");
            if (isset($row['id']) && $row['id'] !== '' && $row['id'] !== null) {
                $db->updateRow('core_api_badge', [ 'api_key' => (string)$apiKey ], "id=".intval($row['id']));
            } else {
                $db->insert('core_api_badge', [ 'label' => (string)$labelLower, 'api_key' => (string)$apiKey ]);
            }
        };
        if ($openrouter !== '') { $save('openrouter', $openrouter); }
        if ($deepgram !== '') { $save('deepgram', $deepgram); }
        echo json_encode([ 'ok' => true ]);
        exit;
    }

    if ($action === 'profile_quicksave_metadata') {
        require_once($rootPath . "lib" . DIRECTORY_SEPARATOR . "settings.php");
        $truthy = function($v) {
            if ($v === null) return null;
            $s = strtolower(trim((string)$v));
            if ($s === '') return null;
            return in_array($s, ['1','true','on','yes'], true);
        };
        $rowPid = $GLOBALS['db']->fetchOne("SELECT id FROM core_profiles ORDER BY CASE WHEN lower(label)='default' THEN 0 WHEN default_narrator='1' THEN 1 WHEN default_npc='1' THEN 2 ELSE 3 END, id ASC LIMIT 1");
        $pid = isset($rowPid['id']) ? intval($rowPid['id']) : 0;
        if ($pid <= 0) { echo json_encode(['ok'=>false,'error'=>'No profile found']); exit; }
        $oghma  = $truthy($_POST['oghma_infinium'] ?? null);
        $player2Force = $truthy($_POST['player2_force_all_llm'] ?? null);
        $localLlmPreset = $truthy($_POST['local_llm_preset'] ?? null);
        if ($oghma !== null && !chimSetGeneralSetting('OGHMA_INFINIUM', $oghma, chimGetSchemaDescription('OGHMA_INFINIUM'))) {
            echo json_encode(['ok'=>false,'error'=>'Unable to save Oghma Infinium']);
            exit;
        }

        $player2ConnectorId = null;
        if ($player2Force !== null) {
            $player2ConnectorId = LLMRandomizer::setPlayer2ForceEnabled($player2Force ? true : false);
        }

        $localLlmPresetApplied = false;
        if ($localLlmPreset === true) {
            $localLlmContextOptions = chimNormalizePromptContextOptions([
                'enabled_sections' => [
                    'roleplay_instructions',
                    'world',
                    'knowledge',
                    'available_actions_list',
                    'nearby_actors',
                    'nearby_items',
                    'adventuring_party',
                    'scene_notes',
                    'paralinguistic_tags',
                ],
                'enabled_character_subsections' => [
                    'basic_summary',
                    'groups',
                    'personality',
                    'relationships',
                    'occupation',
                    'skills',
                    'speech_style',
                    'goals',
                    'middle_term_memory',
                    'group',
                    'storyline_starring',
                    'quest_topics',
                ],
                'enabled_appearance_subsections' => [
                    'appearance',
                    'equipment',
                    'inventory',
                    'current_activity',
                    'current_condition',
                    'reanimation_status',
                ],
                'enabled_general_subsections' => [
                    'current_plans',
                ],
                'enabled_nearby_actor_subsections' => [
                    'equipment',
                    'current_activity',
                ],
                'enabled_nearby_item_subsections' => [
                    'group_duplicates',
                ],
            ]);
            $localLlmContextJson = json_encode($localLlmContextOptions, JSON_UNESCAPED_SLASHES);
            if ($localLlmContextJson === false) {
                echo json_encode(['ok'=>false,'error'=>'Unable to prepare the Local LLM context preset']);
                exit;
            }
            $localLlmContextValue = $GLOBALS['db']->escapeLiteral($localLlmContextJson);
            $localLlmContextDescription = $GLOBALS['db']->escapeLiteral(chimGetSchemaDescription('PROMPT_CONTEXT_OPTIONS'));
            $localLlmPresetSaved = $GLOBALS['db']->execQuery(
                "WITH updated_profiles AS (
                    UPDATE core_profiles
                    SET metadata = jsonb_set(
                        jsonb_set(
                            jsonb_set(
                                jsonb_set(COALESCE(metadata, '{}'::jsonb), '{CONTEXT_HISTORY}', '\"40\"'::jsonb, true),
                                '{CONTEXT_HISTORY_DIARY}', '\"40\"'::jsonb, true
                            ),
                            '{CONTEXT_HISTORY_DYNAMIC_PROFILE}', '\"30\"'::jsonb, true
                        ),
                        '{MAX_WORDS_LIMIT}', '\"60\"'::jsonb, true
                    )
                    RETURNING id
                 ),
                 updated_context_options AS (
                    INSERT INTO public.general_settings (id, value, description, updated_at)
                    VALUES ('PROMPT_CONTEXT_OPTIONS', {$localLlmContextValue}, {$localLlmContextDescription}, CURRENT_TIMESTAMP)
                    ON CONFLICT (id) DO UPDATE SET
                        value = EXCLUDED.value,
                        description = EXCLUDED.description,
                        updated_at = CURRENT_TIMESTAMP
                    RETURNING id
                 )
                 INSERT INTO conf_opts (id, value) VALUES
                    ('CONTEXT_HISTORY', '40'),
                    ('CONTEXT_HISTORY_DIARY', '40'),
                    ('CONTEXT_HISTORY_DYNAMIC_PROFILE', '30'),
                    ('MAX_WORDS_LIMIT', '60'),
                    ('chim_context_mode', '1')
                 ON CONFLICT (id) DO UPDATE SET value = EXCLUDED.value"
            );
            if ($localLlmPresetSaved === false) {
                echo json_encode(['ok'=>false,'error'=>'Unable to apply the Local LLM preset']);
                exit;
            }
            $localLlmPresetApplied = true;
        }

        echo json_encode([
            'ok'=>true,
            'id'=>$pid,
            'player2_connector_id' => $player2ConnectorId,
            'local_llm_preset_applied' => $localLlmPresetApplied,
        ]);
        exit;
    }

    if ($action === 'save_quickstart') {
        $result = false;
        try {
            require_once($rootPath . "lib" . DIRECTORY_SEPARATOR . "settings.php");
            require_once($rootPath . "lib" . DIRECTORY_SEPARATOR . "core" . DIRECTORY_SEPARATOR . "player.class.php");
            require_once($rootPath . "lib" . DIRECTORY_SEPARATOR . "core" . DIRECTORY_SEPARATOR . "stt_connector.class.php");
            require_once($rootPath . "lib" . DIRECTORY_SEPARATOR . "core" . DIRECTORY_SEPARATOR . "tts_connector.class.php");

            if (isset($_POST['PLAYER_NAME']) && trim(strval($_POST['PLAYER_NAME'])) !== '') {
                $player = new Player();
                $player->set('player_name', trim(strval($_POST['PLAYER_NAME'])));
            }

            $ttsConnector = new TTSConnector();
            $selectedTtsDriver = herikaQuickstartNormalizeTtsDriver($ttsConnector, $_POST['TTSFUNCTION'] ?? ($GLOBALS["TTSFUNCTION"] ?? 'none'));
            $savedTtsId = herikaQuickstartApplyTtsSelection($ttsConnector, $selectedTtsDriver);

            $sttConnector = new STTConnector();
            $activeSttId = herikaQuickstartEnsureActiveSttConnectorId($sttConnector);
            $selectedSttDriver = $sttConnector->normalizeDriverValue($_POST['STTFUNCTION'] ?? ($GLOBALS["STTFUNCTION"] ?? 'none'));
            if ($selectedSttDriver === '') {
                $selectedSttDriver = 'none';
            }
            $existingStt = $activeSttId > 0 ? $sttConnector->getById($activeSttId) : null;
            $existingSttDriver = $sttConnector->normalizeDriverValue($existingStt['driver'] ?? '');
            $metadata = ($existingStt && $existingSttDriver === $selectedSttDriver)
                ? $sttConnector->decodeMetadata($existingStt['metadata'] ?? '{}')
                : [];
            $url = null;
            if ($sttConnector->driverSupportsEditableUrl($selectedSttDriver)) {
                if ($existingStt && $existingSttDriver === $selectedSttDriver) {
                    $url = trim(strval($existingStt['url'] ?? ''));
                }
                if ($url === '') {
                    $url = $sttConnector->getDefaultUrlForDriver($selectedSttDriver);
                }
            }
            $sttPayload = [
                'driver' => $selectedSttDriver,
                'label' => ($selectedSttDriver === 'none') ? 'Disabled STT' : ('Global ' . $sttConnector->getDisplayName($selectedSttDriver)),
                'metadata' => json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'api_badge_id' => $sttConnector->driverUsesApiBadge($selectedSttDriver)
                    ? $sttConnector->getDefaultApiBadgeIdForDriver($selectedSttDriver)
                    : null,
                'url' => $url,
            ];
            if ($activeSttId > 0 && $existingStt) {
                $sttConnector->update($activeSttId, $sttPayload);
                chimSetGeneralSetting('GLOBAL_STT_CONNECTOR_ID', $activeSttId, chimGetSchemaDescription('GLOBAL_STT_CONNECTOR_ID'));
                $savedSttId = $activeSttId;
            } else {
                $savedSttId = $sttConnector->create($sttPayload);
                if ($savedSttId > 0) {
                    chimSetGeneralSetting('GLOBAL_STT_CONNECTOR_ID', $savedSttId, chimGetSchemaDescription('GLOBAL_STT_CONNECTOR_ID'));
                }
            }

            $result = ($savedTtsId > 0) && (intval($savedSttId ?? 0) > 0);
        } catch (Throwable $_e) {
            $result = false;
        }
        echo json_encode([ 'ok' => $result ]);
        exit;
    }

    echo json_encode([ 'ok' => false, 'error' => 'Unknown action' ]);
    exit;
}

// Get the relative web path from document root to our application
$scriptPath = $_SERVER['SCRIPT_NAME'];
$webRoot = dirname(dirname($scriptPath)); // Go up two levels from the script location
if ($webRoot == '/') $webRoot = '';
$webRoot = rtrim($webRoot, '/');

$TITLE = "CHIM - Quickstart";

require_once($rootPath . "lib" . DIRECTORY_SEPARATOR . "runtime_bootstrap.php");
chimRuntimeBootstrapIfNeeded($rootPath, [
    'load_general_settings' => true,
    'load_stt_connector' => true,
    'load_itt_connector' => false,
    'load_tts_connector' => true,
    'load_player_name' => true,
    'load_narrator' => true,
]);

include(__DIR__.DIRECTORY_SEPARATOR."tmpl/head.html");

$debugPaneLink = false;
include(__DIR__.DIRECTORY_SEPARATOR."tmpl/navbar.php");

$rootPath = __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR;
$configFilepath = __DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."conf".DIRECTORY_SEPARATOR;
$configFilepath = realpath($configFilepath) . DIRECTORY_SEPARATOR;

require_once($rootPath . "lib" . DIRECTORY_SEPARATOR . "llm_randomizer.php");
require_once($rootPath . "conf" . DIRECTORY_SEPARATOR . 'conf_loader.php');
require_once($rootPath . "lib" . DIRECTORY_SEPARATOR . "core" . DIRECTORY_SEPARATOR . "tts_connector.class.php");
require_once($rootPath . "lib" . DIRECTORY_SEPARATOR . "core" . DIRECTORY_SEPARATOR . "stt_connector.class.php");
$db = $GLOBALS['db'];

// Load current configurations
$currentConf = conf_loader_load();
$currentConfTitles = conf_loader_load_titles();

$quickstartSttConnector = new STTConnector();
$quickstartActiveSttId = herikaQuickstartEnsureActiveSttConnectorId($quickstartSttConnector);
$quickstartActiveSttRow = $quickstartActiveSttId > 0 ? $quickstartSttConnector->getById($quickstartActiveSttId) : [];
$quickstartActiveSttDriver = $quickstartSttConnector->normalizeDriverValue($quickstartActiveSttRow['driver'] ?? ($GLOBALS["STTFUNCTION"] ?? ''));
$quickstartTtsConnector = new TTSConnector();
$quickstartActiveTtsRow = chimResolvePreferredTtsConnectorRow();
$quickstartActiveTtsDriver = herikaQuickstartNormalizeTtsDriver($quickstartTtsConnector, $quickstartActiveTtsRow['driver'] ?? ($GLOBALS["TTSFUNCTION"] ?? ''));

if (isset($currentConf['TTSFUNCTION']) && in_array($quickstartActiveTtsDriver, $quickstartTtsConnector->getDriverOptions(), true)) {
    $currentConf['TTSFUNCTION']['currentValue'] = $quickstartActiveTtsDriver;
}
if (isset($currentConf['STTFUNCTION']) && in_array($quickstartActiveSttDriver, $quickstartSttConnector->getDriverOptions(), true)) {
    $currentConf['STTFUNCTION']['currentValue'] = $quickstartActiveSttDriver;
}

// Filter the configurations you want to display in the Quickstart Menu
$quickstartKeys = [
    "TTSFUNCTION",
    "STTFUNCTION"
];

$quickstartConf = array_filter($currentConf, function($key) use ($quickstartKeys) {
    return in_array($key, $quickstartKeys);
}, ARRAY_FILTER_USE_KEY);

// Start of Form
echo '<link rel="stylesheet" href="'.$webRoot.'/ui/css/main.css">';
echo '<main class="qs-page">';
echo '<div class="qs-shell">
        <form action="" method="post" name="mainC" class="confwizard" id="top">';

// Main Heading
echo '<section class="qs-section qs-header-card">
        <h1 class="qs-title">Quickstart Menu</h1>
      </section>';

// PLAYER_NAME at top
$playerNameVal = 'Prisoner'; // Default value
// Try to get from core_player table first
try {
    require_once($rootPath . "lib" . DIRECTORY_SEPARATOR . "core" . DIRECTORY_SEPARATOR . "player.class.php");
    $player = new Player();
    $nameFromPlayer = $player->get('player_name');
    if ($nameFromPlayer !== null && $nameFromPlayer !== '') {
        $playerNameVal = $nameFromPlayer;
    }
} catch (Exception $e) {
    // Fall back to conf value if core_player fails
    if (isset($currentConf['PLAYER_NAME']['currentValue']) && $currentConf['PLAYER_NAME']['currentValue'] !== '') {
        $playerNameVal = (string)$currentConf['PLAYER_NAME']['currentValue'];
    }
}
echo '<section class="qs-section">
        <h2 class="qs-section-title">Player</h2>
        <div class="form-group qs-field">
            <label for="PLAYER_NAME">Player Name</label>
            <input type="text" class="form-control" id="PLAYER_NAME" name="PLAYER_NAME" value="' . htmlspecialchars($playerNameVal) . '">
            <small class="form-text">Your in-game character name. Defaults to "Prisoner" and is automatically updated when you load a save. You can also manage player settings in <a href="' . $webRoot . '/ui/core/config_hub.php?tab=player" target="_blank" style="color:#4a8ab6;">Player Management</a>.</small>
        </div>
      </section>';

// API Keys section (OpenRouter only here; Deepgram rendered under STT)
try { $openrouterRow = $db->fetchOne("SELECT api_key FROM core_api_badge WHERE lower(label)='openrouter' LIMIT 1"); } catch (Throwable $_e) { $openrouterRow = []; }
$openrouterKey = isset($openrouterRow["api_key"]) ? $openrouterRow["api_key"] : "";
$player2ForceAllLlm = false;
try { $player2ForceAllLlm = LLMRandomizer::isPlayer2ForceEnabled(); } catch (Throwable $_e) { $player2ForceAllLlm = false; }
$player2ForceChecked = $player2ForceAllLlm ? " checked" : "";
$llmNoteDefaultStyle = $player2ForceAllLlm ? ' style="display:none;"' : '';
$llmNotePlayer2Style = $player2ForceAllLlm ? '' : ' style="display:none;"';
$llmCardsBaseStyle = 'display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:12px; margin-top:8px;';
$llmCardsDefaultStyle = $llmCardsBaseStyle . ($player2ForceAllLlm ? ' display:none;' : '');
$llmCardsPlayer2Style = $llmCardsBaseStyle . ($player2ForceAllLlm ? '' : ' display:none;');
$generalLlmConnectorSummary = herikaQuickstartGetGeneralLlmConnectorSummary($db);
$generalLlmConnectorListHtml = '';
if (!empty($generalLlmConnectorSummary)) {
    $generalLlmConnectorListHtml .= '<ul class="qs-general-connector-list">';
    foreach ($generalLlmConnectorSummary as $item) {
        $generalLlmConnectorListHtml .= '<li><span class="qs-general-connector-name">' . htmlspecialchars($item['name']) . ':</span> ' . htmlspecialchars($item['label']) . '</li>';
    }
    $generalLlmConnectorListHtml .= '</ul>';
}

echo '<section class="qs-section" id="qs_openrouter_section"' . ($player2ForceAllLlm ? ' style="display:none;"' : '') . '>
        <h2 class="qs-section-title">OpenRouter</h2>
        <div class="form-group qs-field">
            <label for="qs_openrouter_api_key">OpenRouter API Key</label>
            <div class="input-group">
                <input type="password" class="form-control" id="qs_openrouter_api_key" value="' . htmlspecialchars($openrouterKey) . '" style="filter: blur(3px);">
                <div class="input-group-append">
                    <button class="btn-primary" type="button" onclick="document.getElementById(\'qs_openrouter_api_key\').style.filter=\'blur(0px)\'; document.getElementById(\'qs_openrouter_api_key\').setAttribute(\'type\', \'text\');">Unhide</button>
                </div>
            </div>
            <small class="form-text">Paste your OpenRouter API key. <a href="https://openrouter.ai/keys" target="_blank">Create key</a></small>
        </div>
      </section>';

echo '<section class="qs-section" id="qs_minime_section">
        <h2 class="qs-section-title">MiniMe Service</h2>
        <div class="form-group qs-field">
            <small class="form-text">Checks if MiniMe is reachable at the local default endpoint.</small>
            <input id="qs_minime_probe_url" type="hidden" value="' . htmlspecialchars(herikaQuickstartMiniMeDefaultUrl()) . '">
            <div id="qs_minime_probe_status" class="qs-status">Checking MiniMe service...</div>
        </div>
      </section>';

$access = ["basic" => 0, "pro" => 1, "wip" => 2];

foreach ($quickstartConf as $pname => $parms) {

    if (isset($parms["helpurl"])) {
        $parms["description"] .= " <a target='_blank' href='" . htmlspecialchars($parms["helpurl"]) . "'>[help/doc]</a>";
    }

    if (isset($parms["userlvl"]) && !($access[$parms["userlvl"]] <= $access[$_SESSION["OPTION_TO_SHOW"]])) {
        $MAKE_NO_VISIBLE_MARK = " style='display:none' ";
    } else {
        $MAKE_NO_VISIBLE_MARK = "";
    }

    $fieldName = strtr($pname, array(" " => "@"));

    if (!is_array($parms["currentValue"])) {
        $fieldValue = htmlspecialchars(stripslashes($parms["currentValue"]));
    } else {
        $fieldValue = '';
    }

    $FORCE_DISABLED = "";

    if (isset($parms["scope"]) && $parms["scope"] == "global") {
        $FORCE_DISABLED = "";
    }

    if (isset($parms["scope"]) && $parms["scope"] == "constant") {
        $FORCE_DISABLED = " readonly='true' disabled='true' title='This is a readonly parameter'";
    }

    $groupClass = "form-group";
    if ($pname == "TTSFUNCTION") {
        $groupClass .= " qs-service-group";
    } else if ($pname == "STTFUNCTION") {
        $groupClass .= " qs-service-group qs-service-group-stt";
    }

    // Label
    $displayLabel = $pname;
    if ($pname == "TTSFUNCTION") {
        $displayLabel = "TTS Service";
    } else if ($pname == "STTFUNCTION") {
        $displayLabel = "STT Service";
    }

    echo "<section class='qs-section qs-service-card' $MAKE_NO_VISIBLE_MARK>";
    echo "<h2 class='qs-section-title'>" . htmlspecialchars($displayLabel) . "</h2>";
    echo "<div class='" . $groupClass . "'>";
    echo "<label for='$fieldName'>" . htmlspecialchars($displayLabel) . "</label>";

    // Input Types
    if ($parms["type"] == "string") {
        echo "<input type='text' class='form-control' id='$fieldName' name='" . htmlspecialchars($fieldName) . "' value=\"$fieldValue\" $FORCE_DISABLED>";
    } else if ($parms["type"] == "longstring") {
        echo "<textarea class='form-control' id='$fieldName' name='" . htmlspecialchars($fieldName) . "' $FORCE_DISABLED>$fieldValue</textarea>";
    } else if ($parms["type"] == "url") {
        echo "<div class='input-group'>";
        echo "<input type='url' class='form-control' id='$fieldName' name='" . htmlspecialchars($fieldName) . "' value='" . htmlspecialchars($fieldValue) . "' $FORCE_DISABLED>";
        echo "<div class='input-group-append'>";
        echo "<button class='btn btn-outline-secondary' type='button' onclick=\"checkUrlFromServer('$fieldName')\">Check</button>";
        echo "</div></div>";

    } else if ($parms["type"] == "select") {
        // Display name mappings for UI labels
        $selectDisplayNames = [
            'omnivoice'    => 'OmniVoice',
            'pockettts'    => 'PocketTTS',
            'chatterbox'   => 'Chatterbox',
            'xtts-fastapi' => 'XTTS',
            'parakeet'     => 'Parakeet',
            'deepgram'     => 'Deepgram',
            'localwhisper' => 'Local Whisper',
            'whisper'      => 'Whisper',
            'gemini'       => 'Gemini',
            'azure'        => 'Azure',
            'inworld'      => 'Inworld',
            'none'         => 'Disabled',
        ];
        $recommendedValues = [];
        if ($pname == "TTSFUNCTION") {
            $parms["values"] = ["omnivoice","pockettts","chatterbox","xtts-fastapi","inworld"];
            if (in_array($quickstartActiveTtsDriver, $parms["values"], true)) {
                $parms["currentValue"] = $quickstartActiveTtsDriver;
            }
            $recommendedValues = ["omnivoice", "pockettts", "chatterbox"];
            $parms["description"] = "Select the TTS service you wish to use. Recommended: OmniVoice, PocketTTS or Chatterbox. <br>You can install OmniVoice, PocketTTS, Chatterbox, XTTS and configure Inworld in CHIM. For provider-specific settings and advanced endpoint editing, use the <a href='" . $webRoot . "/ui/core/tts_connectors.php' target='_blank'>TTS Connectors</a> page.";
        } else if ($pname == "STTFUNCTION") {
            $parms["values"] = ["parakeet", "deepgram"];
            if (in_array($quickstartActiveSttDriver, $parms["values"], true)) {
                $parms["currentValue"] = $quickstartActiveSttDriver;
            }
            $recommendedValues = ["parakeet", "deepgram"];
            $parms["description"] = "Select the STT service you wish to use. Recommended: Parakeet or Deepgram. For provider-specific settings and endpoint editing, use the <a href='" . $webRoot . "/ui/stt_connectors.php' target='_blank'>STT Connectors</a> page.";
        }
        $recommendedValues = array_values(array_filter(
            $recommendedValues,
            function($value) use ($parms) {
                return in_array($value, $parms["values"], true);
            }
        ));
        $otherValues = array_values(array_filter(
            $parms["values"],
            function($value) use ($recommendedValues) {
                return !in_array($value, $recommendedValues, true);
            }
        ));
        echo "<select class='form-control' id='$fieldName' name='" . htmlspecialchars($fieldName) . "' $FORCE_DISABLED>";
        if (count($recommendedValues) > 0) {
            echo "<optgroup label='Recommended'>";
            foreach ($recommendedValues as $item) {
                $selected = ($item == $parms["currentValue"]) ? "selected" : "";
                $displayName = $selectDisplayNames[$item] ?? $item;
                echo "<option value='" . htmlspecialchars($item) . "' $selected>" . htmlspecialchars($displayName . " (Recommended)") . "</option>";
            }
            echo "</optgroup>";
        }
        if (count($otherValues) > 0) {
            $otherLabel = "Other Services";
            if ($pname == "TTSFUNCTION") {
                $otherLabel = "Other TTS Services";
            } else if ($pname == "STTFUNCTION") {
                $otherLabel = "Other STT Services";
            }
            echo "<optgroup label='" . htmlspecialchars($otherLabel) . "'>";
            foreach ($otherValues as $item) {
                $selected = ($item == $parms["currentValue"]) ? "selected" : "";
                $displayName = $selectDisplayNames[$item] ?? $item;
                echo "<option value='" . htmlspecialchars($item) . "' $selected>" . htmlspecialchars($displayName) . "</option>";
            }
            echo "</optgroup>";
        }
        echo "</select>";
        
        if ($pname == "STTFUNCTION") {
            try { $deepgramRow = $db->fetchOne("SELECT api_key FROM core_api_badge WHERE lower(label)='deepgram' LIMIT 1"); } catch (Throwable $_e) { $deepgramRow = []; }
            $deepgramKey = isset($deepgramRow["api_key"]) ? $deepgramRow["api_key"] : "";
            $showDG = ($parms["currentValue"] === "deepgram") ? "" : " style=\"display:none\"";
            echo "<div class='form-group' id='dg_api_wrap'".$showDG.">";
            echo "<label for='qs_deepgram_api_key'>Deepgram API Key</label>";
            echo "<div class='input-group'>";
            echo "<input type='password' class='form-control' id='qs_deepgram_api_key' value='" . htmlspecialchars($deepgramKey) . "' style='filter: blur(3px);'>";
            echo "<div class='input-group-append'>";
            echo "<button class='btn-primary' type='button' onclick=\"document.getElementById('qs_deepgram_api_key').style.filter='blur(0px)'; document.getElementById('qs_deepgram_api_key').setAttribute('type','text');\">Unhide</button>";
            echo "</div></div>";
            echo "<small class='form-text'>Paste your Deepgram API key. <a href='https://console.deepgram.com/' target='_blank'>Create key</a></small>";
            echo "</div>";
            echo "<script>(function(){try{var s=document.getElementById('STTFUNCTION'); if(!s) return; var w=document.getElementById('dg_api_wrap'); var f=function(){ if(!w) return; w.style.display = (s.value==='deepgram') ? '' : 'none'; }; s.addEventListener('change', f); f();}catch(_e){}})();</script>";
        }
    
    } else if ($parms["type"] == "boolean") {
        // Add a wrapper div to ensure radio buttons are on a new line
        echo "<div class='mt-2'>";

        // True Radio Button
        $checkedTrue = ($parms["currentValue"]) ? "checked" : "";
        $idTrue = uniqid("bool_true_");
        echo "<div class='form-check'>";
        echo "<input class='form-check-input' type='radio' name='" . htmlspecialchars($fieldName) . "' id='$idTrue' value='true' $checkedTrue $FORCE_DISABLED>";
        echo "<label class='form-check-label' for='$idTrue'>True</label>";
        echo "</div>";

        // False Radio Button
        $checkedFalse = (!$parms["currentValue"]) ? "checked" : "";
        $idFalse = uniqid("bool_false_");
        echo "<div class='form-check'>";
        echo "<input class='form-check-input' type='radio' name='" . htmlspecialchars($fieldName) . "' id='$idFalse' value='false' $checkedFalse $FORCE_DISABLED>";
        echo "<label class='form-check-label' for='$idFalse'>False</label>";
        echo "</div>";

        echo "</div>"; // End of mt-2 div
    } else if ($parms["type"] == "integer") {
        echo "<input type='number' class='form-control' id='$fieldName' name='" . htmlspecialchars($fieldName) . "' value='" . htmlspecialchars($fieldValue) . "' step='1' $FORCE_DISABLED>";
    } else if ($parms["type"] == "number") {
        echo "<input type='number' class='form-control' id='$fieldName' name='" . htmlspecialchars($fieldName) . "' value='" . htmlspecialchars($fieldValue) . "' step='0.01' $FORCE_DISABLED>";
    } else if ($parms["type"] == "apikey") {
        $jsid = strtr($fieldName, ["@" => "_"]);

        if ($pname == "CONNECTOR openrouter API_KEY") {
            $parms["description"] = "Copy and Paste THE EXACT SAME OpenRouter API Key. <i>Yes we need to do it 2 times.</i>";
        } elseif ($pname == "CONNECTOR openrouterjson API_KEY") {
            $parms["description"] = "Copy and Paste your OpenRouter API Key. <br><a href='https://openrouter.ai/' target='_blank'>SETUP ACCOUNT HERE</a> <b>YOU MUST PUT AT LEAST $5 ON IT!</b>";
        } elseif ($pname == "STT WHISPER API_KEY") {
            $parms["description"] = "Copy and Paste your OpenAI API Key. If you do not plan to use your microphone you can skip this. <br><a href='https://platform.openai.com/docs/overview/' target='_blank'>SETUP ACCOUNT HERE</a> <b>YOU MUST PUT AT LEAST $5 ON IT!</b>";
        }
        echo "<div class='input-group'>";
        echo "<input type='text' class='form-control' id='$jsid' name='" . htmlspecialchars($fieldName) . "' value='" . htmlspecialchars($fieldValue) . "' style='filter: blur(3px);' $FORCE_DISABLED>";
        echo "<div class='input-group-append'>";
        echo "<button class='btn-primary' type='button' onclick=\"document.getElementById('$jsid').style.filter='blur(0px)'\">Unhide</button>";
        echo "</div></div>";
    }
    // Add other input types as needed

    // Description
    if (isset($parms["description"]) && !empty($parms["description"])) {
        echo "<small class='form-text'>" . $parms["description"] . "</small>";
    }

    echo "</div>";
    echo "</section>";
}

echo '<section class="qs-section">
        <h2 class="qs-section-title">Player2 Connector</h2>
        <div class="form-group qs-field">
            <div class="qs-toggle-block">
                <div class="qs-toggle-header">
                    <label class="qs-toggle-title" for="qs_player2_force_all_llm">Use Player 2 for LLMs</label>
                    <div class="qs-toggle-control">
                        <input class="form-check-input qs-switch-input" type="checkbox" id="qs_player2_force_all_llm" value="1"' . $player2ForceChecked . '>
                        <label class="form-check-label qs-switch-label" for="qs_player2_force_all_llm">
                            <span class="qs-switch-track"></span>
                            <span class="qs-switch-copy" data-off="Off" data-on="On"></span>
                        </label>
                    </div>
                </div>
            </div>
            <small class="form-text">Route all LLM calls through your local Player2 connector. Model choice stays in the Player2 app.</small>
        </div>
      </section>';

echo '<section class="qs-section">
                <h2 class="qs-section-title">LLM Connectors Note</h2>
                <p class="form-text" id="qs_llm_connectors_note_default"' . $llmNoteDefaultStyle . '>Quickstart gives you four hot-swappable LLMs for in-game use.</p>
                <p class="form-text" id="qs_llm_connectors_note_player2"' . $llmNotePlayer2Style . '>Player2 mode is active. Standard, Fast, Powerful, and Experimental all use the local Player2 connector.</p>
                <div id="qs_llm_connectors_cards_default" style="' . $llmCardsDefaultStyle . '">
                    <div style="background:#1f1f1f; border:1px solid #3b3b3b; border-radius:8px; padding:12px;">
                        <div style="font-size:14px; color:#cfd9ea;">&#x1F579;&#xFE0F; <b>Standard</b></div>
                        <div style="margin-top:6px; color:#9fb1c9;">OpenRouter: DeepSeek V4 Flash (deepseek/deepseek-v4-flash)</div>
                        <div style="margin-top:4px; color:#bbb; font-size:12px;">$0.14/M input | $0.28/M output</div>
                    </div>
                    <div style="background:#1f1f1f; border:1px solid #3b3b3b; border-radius:8px; padding:12px;">
                        <div style="font-size:14px; color:#cfd9ea;">&#x1F3C3;&#x200D;&#x2642;&#xFE0F; <b>Fast</b></div>
                        <div style="margin-top:6px; color:#9fb1c9;">OpenRouter: Gemini 2.5 Flash Lite (google/gemini-2.5-flash-lite)</div>
                        <div style="margin-top:4px; color:#bbb; font-size:12px;">$0.10/M input | $0.40/M output</div>
                    </div>
                    <div style="background:#1f1f1f; border:1px solid #3b3b3b; border-radius:8px; padding:12px;">
                        <div style="font-size:14px; color:#cfd9ea;">&#x1F4AA; <b>Powerful</b></div>
                        <div style="margin-top:6px; color:#9fb1c9;">OpenRouter: GLM 5.2 (z-ai/glm-5.2)</div>
                        <div style="margin-top:4px; color:#bbb; font-size:12px;">$1.40/M input | $4.40/M output</div>
                    </div>
                    <div style="background:#1f1f1f; border:1px solid #3b3b3b; border-radius:8px; padding:12px;">
                        <div style="font-size:14px; color:#cfd9ea;">&#x1F9EA; <b>Experimental</b></div>
                        <div style="margin-top:6px; color:#9fb1c9;">OpenRouter: DeepSeek V4 Pro (deepseek/deepseek-v4-pro)</div>
                        <div style="margin-top:4px; color:#bbb; font-size:12px;">$0.435/M input | $0.87/M output</div>
                    </div>
                </div>
                <div id="qs_llm_connectors_cards_player2" style="' . $llmCardsPlayer2Style . '">
                    <div style="background:#1f1f1f; border:1px solid #3b3b3b; border-radius:8px; padding:12px;">
                        <div style="font-size:14px; color:#cfd9ea;">&#x1F579;&#xFE0F; <b>Standard</b></div>
                        <div style="margin-top:6px; color:#9fb1c9;">Player2 Local</div>
                        <div style="margin-top:4px; color:#bbb; font-size:12px;">Uses the model selected in the Player2 app</div>
                    </div>
                    <div style="background:#1f1f1f; border:1px solid #3b3b3b; border-radius:8px; padding:12px;">
                        <div style="font-size:14px; color:#cfd9ea;">&#x1F3C3;&#x200D;&#x2642;&#xFE0F; <b>Fast</b></div>
                        <div style="margin-top:6px; color:#9fb1c9;">Player2 Local</div>
                        <div style="margin-top:4px; color:#bbb; font-size:12px;">Same local Player2 connector as Standard</div>
                    </div>
                    <div style="background:#1f1f1f; border:1px solid #3b3b3b; border-radius:8px; padding:12px;">
                        <div style="font-size:14px; color:#cfd9ea;">&#x1F4AA; <b>Powerful</b></div>
                        <div style="margin-top:6px; color:#9fb1c9;">Player2 Local</div>
                        <div style="margin-top:4px; color:#bbb; font-size:12px;">Same local Player2 connector as Standard</div>
                    </div>
                    <div style="background:#1f1f1f; border:1px solid #3b3b3b; border-radius:8px; padding:12px;">
                        <div style="font-size:14px; color:#cfd9ea;">&#x1F9EA; <b>Experimental</b></div>
                        <div style="margin-top:6px; color:#9fb1c9;">Player2 Local</div>
                        <div style="margin-top:4px; color:#bbb; font-size:12px;">Same local Player2 connector as Standard</div>
                    </div>
                </div>
                <div class="qs-general-connector-wrap">
                    <div class="qs-general-connector-title">Other Connectors Used:</div>
                    ' . ($generalLlmConnectorListHtml !== '' ? $generalLlmConnectorListHtml : '<div class="qs-general-connector-empty">No additional general-settings connectors are configured.</div>') . '
                </div>
                <div class="form-group qs-field qs-local-llm-preset">
                    <div class="qs-toggle-block">
                        <div class="qs-toggle-header">
                            <label class="qs-toggle-title" for="qs_local_llm_preset">Optimize for Local LLMs</label>
                            <div class="qs-toggle-control">
                                <input class="form-check-input qs-switch-input" type="checkbox" id="qs_local_llm_preset" value="1">
                                <label class="form-check-label qs-switch-label" for="qs_local_llm_preset">
                                    <span class="qs-switch-track"></span>
                                    <span class="qs-switch-copy" data-off="Off" data-on="On"></span>
                                </label>
                            </div>
                        </div>
                    </div>
                    <small class="form-text">Recommended for local small and medium models. Applies a compact 40-event context, shorter 60-word responses, enables Compact Chat, and trims high-cost secondary context while preserving roleplay, actions, memory, inventory, and Oghma knowledge. New profiles inherit the smaller defaults.</small>
                </div>
                <p class="qs-note warning-text3">
                    Once done click Save and startup Skyrim with the AIAgent mod installed. Please read the <a href="https://dwemerdynamics.com/chim/index.html" target="_blank" style="color: #ffcc00; text-decoration: underline;">CHIM Wiki</a> to learn more about how CHIM works.
                </p>
                <div class="qs-actions">
                    <button
                        type="button"
                        class="btn-primary qs-save-btn"
                        name="save"
                        value="Save"
                        style="background-color: #28a745 !important;"
                        onclick="saveQuickstartAndDB()"
                    >
                        Save and Continue
                    </button>
                </div>
          </section>';

echo '      </form>
      </div>
</main>'; // End of shell/main

include("tmpl/footer.html");

$buffer = ob_get_contents();
ob_end_clean();
$title = $TITLE;
$buffer = preg_replace('/(<title>)(.*?)(<\/title>)/i', '$1' . $title . '$3', $buffer);
echo $buffer;

echo '<style>
    @font-face { font-family: "MagicCards"; src: url("css/font/MagicCardsNormal.ttf") format("truetype"); font-weight: normal; font-style: normal; }
    /* Page shell */
    .qs-page {
        padding-top: 80px;
        padding-bottom: 40px;
        padding-left: 10px;
        padding-right: 10px;
    }

    .qs-shell {
        max-width: 980px;
        margin: 0 auto;
    }
    
    /* Override footer styles */
    footer {
        position: fixed;
        bottom: 0;
        width: 100%;
        height: 20px;
        background: #031633;
        z-index: 100;
    }

    .confwizard {
        background: transparent;
        padding: 0;
        margin: 0;
        border: 0;
        box-shadow: none;
    }

    .qs-section {
        border: 1px solid #3a3a3a;
        border-radius: 10px;
        background: linear-gradient(180deg, rgba(42, 42, 42, 0.95), rgba(34, 34, 34, 0.98));
        padding: 16px;
        margin-bottom: 14px;
    }

    .qs-header-card {
        text-align: center;
    }

    /* Headings styled like Oghma */
    .qs-title {
        margin: 0;
        font-family: "MagicCards", serif;
        word-spacing: 8px;
        font-size: 2.2em;
        color: rgb(242, 124, 17);
        text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
        text-align: center;
    }

    .qs-subtitle {
        font-family: "MagicCards", serif;
        color: rgb(242, 124, 17);
        text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
        word-spacing: 6px;
        margin-bottom: 10px;
        font-size: 1.4em;
        text-align: center;
    }

    .qs-note {
        color: #cfd8e3;
        margin-bottom: 18px;
        text-align: center;
    }

    .qs-section-title {
        font-family: "MagicCards", serif;
        color: rgb(242, 124, 17);
        text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
        word-spacing: 6px;
        margin: 0 0 10px 0;
        font-size: 1.4em;
    }

    .qs-field {
        margin-bottom: 0;
    }

    .qs-general-connector-wrap {
        margin-top: 14px;
        padding: 12px;
        border: 1px solid #3b3b3b;
        border-radius: 8px;
        background: rgba(20, 20, 20, 0.65);
    }

    .qs-general-connector-title {
        color: #cfd9ea;
        font-weight: 600;
        margin-bottom: 8px;
    }

    .qs-general-connector-list {
        margin: 0;
        padding-left: 18px;
        color: #b9c4d6;
        font-size: 13px;
    }

    .qs-general-connector-list li {
        margin-bottom: 4px;
    }

    .qs-general-connector-name {
        color: #e5e7eb;
        font-weight: 600;
    }

    .qs-general-connector-empty {
        color: #9ca3af;
        font-size: 13px;
    }

    .qs-actions {
        display: flex;
        justify-content: flex-end;
        align-items: center;
        margin-top: 14px;
    }

    .qs-save-btn {
        min-width: 180px;
    }

    /* Button overrides */
    .confwizard .btn-primary,
    .confwizard button.btn-primary,
    .confwizard a.btn-primary {
        padding: 10px 18px !important;
        color: #ffffff !important;
        border: 2px solid rgba(255, 255, 255, 0.65) !important;
        border-radius: 6px !important;
        cursor: pointer !important;
        font-size: 16px !important;
        text-decoration: none !important;
        display: inline-block !important;
        transition: background-color 0.3s, color 0.3s !important;
        margin: 6px !important;
        font-weight: bold !important;
        background-color: rgb(0, 48, 176) !important;
    }

    .confwizard .btn-primary:hover,
    .confwizard button.btn-primary:hover,
    .confwizard a.btn-primary:hover {
        background-color: rgb(0, 38, 156) !important;
        color: #ffffff !important;
    }

    .conf-item label {
        color: #e0e0e0;
        font-weight: 500;
    }

    .form-control, .form-control:focus, .custom-select, .custom-select:focus, textarea.form-control {
        background-color: #2c2c2c;
        color: #e0e0e0;
        border: 1px solid #444;
    }

    .form-control::placeholder {
        color: #888;
    }

    .form-text {
        color: #bbb;
    }

    .qs-status {
        margin-top: 8px;
        padding: 8px 10px;
        border-radius: 8px;
        border: 1px solid #3b3b3b;
        background: #222;
        color: #cfd8e3;
        font-size: 0.92rem;
    }

    .qs-status.ok {
        border-color: #2f7c55;
        background: rgba(31, 81, 57, 0.28);
        color: #d7f5e5;
    }

    .qs-status.err {
        border-color: #8a4c3d;
        background: rgba(85, 34, 34, 0.28);
        color: #ffd3c9;
    }

    .qs-toggle-block {
        background: linear-gradient(180deg, rgba(34, 34, 34, 0.98), rgba(25, 25, 25, 0.98));
        border: 1px solid #3b3b3b;
        border-radius: 12px;
        padding: 14px 16px;
        box-shadow: inset 0 1px 0 rgba(255,255,255,0.03);
    }

    .qs-toggle-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
    }

    .qs-toggle-title {
        margin: 0;
        color: #f1f4fa;
        font-weight: 600;
        font-size: 1rem;
    }

    .qs-toggle-control {
        display: inline-flex;
        align-items: center;
        justify-content: flex-end;
        min-width: 112px;
    }

    .qs-switch-input {
        position: absolute;
        opacity: 0;
        pointer-events: none;
    }

    .qs-switch-label {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        margin: 0;
        cursor: pointer;
        user-select: none;
    }

    .qs-switch-track {
        position: relative;
        width: 56px;
        height: 32px;
        border-radius: 999px;
        background: #4a3b26;
        border: 1px solid #735730;
        transition: background-color 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
        box-shadow: inset 0 0 0 1px rgba(0,0,0,0.15);
    }

    .qs-switch-track::after {
        content: "";
        position: absolute;
        top: 3px;
        left: 3px;
        width: 24px;
        height: 24px;
        border-radius: 50%;
        background: #f7f3ed;
        box-shadow: 0 2px 6px rgba(0,0,0,0.35);
        transition: transform 0.2s ease;
    }

    .qs-switch-copy {
        min-width: 28px;
        color: #d7deea;
        font-size: 0.9rem;
        text-align: right;
    }

    .qs-switch-copy::before {
        content: attr(data-off);
    }

    .qs-switch-input:checked + .qs-switch-label .qs-switch-track {
        background: #245c43;
        border-color: #41a56f;
        box-shadow: 0 0 0 4px rgba(65,165,111,0.14);
    }

    .qs-switch-input:checked + .qs-switch-label .qs-switch-track::after {
        transform: translateX(24px);
    }

    .qs-switch-input:checked + .qs-switch-label .qs-switch-copy::before {
        content: attr(data-on);
    }

    .qs-service-group {
        margin-bottom: 18px;
    }

    .qs-service-group-stt {
        margin-top: 26px;
    }

    .qs-service-group .form-control {
        margin-top: 6px;
    }

    .qs-service-group .form-text {
        display: block;
        margin-top: 8px;
    }

    @media (max-width: 640px) {
        .qs-toggle-header {
            flex-direction: column;
            align-items: flex-start;
        }

        .qs-toggle-control {
            min-width: 0;
        }

        #qs_llm_connectors_cards_default,
        #qs_llm_connectors_cards_player2 {
            grid-template-columns: 1fr !important;
        }
    }

    /* Warning Text Styling */
    .warning-text {
        color: #ffcc00;
        font-weight: bold;
        margin-bottom: 15px;
    }

    .warning-text2 {
        color: #28a745;
        font-weight: bold;
        margin-bottom: 15px;
    }

    /* Manual and Guide Links */
    .warning-text3 a {
        color: #ffcc00 !important;
        text-decoration: underline !important;
        background: none !important;
        border: none !important;
        padding: 0 !important;
        margin: 0 !important;
        font-weight: normal !important;
        display: inline !important;
    }

    .warning-text3 a:hover {
        color: #ffd700 !important;
    }
</style>';

echo '<script>
const WEB_ROOT = '.json_encode($webRoot).';

async function saveQuickstartAndDB(){
  try {
    const finishUrl = WEB_ROOT + "/ui/home.php";
    // 1) Save API keys
    const fd = new FormData();
    const orKey = document.getElementById("qs_openrouter_api_key");
    const dgKey = document.getElementById("qs_deepgram_api_key");
    fd.append("qs_action", "api_badge_quicksave");
    fd.append("openrouter_api_key", orKey ? orKey.value : "");
    fd.append("deepgram_api_key", dgKey ? dgKey.value : "");
    await fetch("quickstart.php", { method: "POST", body: fd, cache: "no-store", credentials: "same-origin" });

    // 2) Save profile metadata flags
    const fdm = new FormData();
    try { fdm.append("player2_force_all_llm", document.getElementById("qs_player2_force_all_llm").checked ? "1" : "0"); } catch(_e){}
    try { fdm.append("local_llm_preset", document.getElementById("qs_local_llm_preset").checked ? "1" : "0"); } catch(_e){}
    fdm.append("qs_action", "profile_quicksave_metadata");
    const profileResponse = await fetch("quickstart.php", { method: "POST", body: fdm, cache: "no-store", credentials: "same-origin" });
    const profileResult = await profileResponse.json();
    if (!profileResponse.ok || !profileResult.ok) {
      throw new Error(profileResult.error || "Unable to save profile settings");
    }

    // 3) Save quickstart selections to the database
    const form = document.getElementById("top");
    const fdw = new FormData(form);
    fdw.append("qs_action", "save_quickstart");
    await fetch("quickstart.php", { method: "POST", body: fdw, cache: "no-store", credentials: "same-origin" });

    // Notify user, then redirect
    try { alert("Quickstart settings have been saved."); } catch(_a){}
    window.location.href = finishUrl;
  } catch (_e) {
    try { alert("Save failed or partially completed. Redirecting to home."); } catch(_a){}
    window.location.href = finishUrl;
  }
}

async function checkMiniMeEndpoint(){
  try {
    const input = document.getElementById("qs_minime_probe_url");
    const status = document.getElementById("qs_minime_probe_status");
    if (!input || !status) return;
    const url = String(input.value || "").trim();
    if (url === "") {
      status.textContent = "MiniMe endpoint URL is empty.";
      status.classList.remove("ok");
      status.classList.add("err");
      return;
    }

    status.textContent = "Checking MiniMe service...";
    status.classList.remove("ok", "err");

    const probeUrl = "quickstart.php?minime_probe=1&url=" + encodeURIComponent(url);
    const response = await fetch(probeUrl, { cache: "no-store", credentials: "same-origin" });
    const result = await response.json();
    const http = Number(result && result.http_code ? result.http_code : 0);
    const latency = Number(result && result.latency_ms ? result.latency_ms : 0);
    const message = String((result && result.message) ? result.message : "MiniMe probe failed.");
    if (result && result.ok) {
      status.textContent = `MiniMe reachable (${http}) in ${latency} ms. ${message}`;
      status.classList.remove("err");
      status.classList.add("ok");
    } else {
      status.textContent = `MiniMe not reachable (${http || 0}) in ${latency} ms. ${message}`;
      status.classList.remove("ok");
      status.classList.add("err");
    }
  } catch (_error) {
    const status = document.getElementById("qs_minime_probe_status");
    if (status) {
      status.textContent = "MiniMe probe failed.";
      status.classList.remove("ok");
      status.classList.add("err");
    }
  }
}

function updatePlayer2QuickstartUI(){
  try {
    const enabled = !!(document.getElementById("qs_player2_force_all_llm") && document.getElementById("qs_player2_force_all_llm").checked);
    const openrouterSection = document.getElementById("qs_openrouter_section");
    const defaultNote = document.getElementById("qs_llm_connectors_note_default");
    const player2Note = document.getElementById("qs_llm_connectors_note_player2");
    const defaultCards = document.getElementById("qs_llm_connectors_cards_default");
    const player2Cards = document.getElementById("qs_llm_connectors_cards_player2");
    if (openrouterSection) {
      openrouterSection.style.display = enabled ? "none" : "";
    }
    if (defaultNote) {
      defaultNote.style.display = enabled ? "none" : "";
    }
    if (player2Note) {
      player2Note.style.display = enabled ? "" : "none";
    }
    if (defaultCards) {
      defaultCards.style.display = enabled ? "none" : "grid";
    }
    if (player2Cards) {
      player2Cards.style.display = enabled ? "grid" : "none";
    }
  } catch(_e){}
}

document.addEventListener("DOMContentLoaded", function(){
  const player2Toggle = document.getElementById("qs_player2_force_all_llm");
  if (player2Toggle) {
    player2Toggle.addEventListener("change", updatePlayer2QuickstartUI);
  }
  updatePlayer2QuickstartUI();
  checkMiniMeEndpoint();
});
</script>';

?>
