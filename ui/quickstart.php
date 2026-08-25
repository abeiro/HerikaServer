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

    if ($action === 'local_llm_test_draft') {
        require_once($rootPath . "lib" . DIRECTORY_SEPARATOR . "core" . DIRECTORY_SEPARATOR . "local_llm_setup.php");
        echo json_encode([
            'ok' => true,
            'result' => herikaLocalLlmTestDraft($_POST),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

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
        require_once($rootPath . "lib" . DIRECTORY_SEPARATOR . "core" . DIRECTORY_SEPARATOR . "settings_presets.php");
        require_once($rootPath . "lib" . DIRECTORY_SEPARATOR . "core" . DIRECTORY_SEPARATOR . "local_llm_setup.php");
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
        $settingsPresetId = trim((string)($_POST['settings_preset_id'] ?? 'builtin:default'));
        $player2Effective = $player2Force !== null ? $player2Force : LLMRandomizer::isPlayer2ForceEnabled();
        $localLlmRequested = $settingsPresetId === 'builtin:local_llm' && !$player2Effective;
        $localLlmResult = null;
        if ($localLlmRequested) {
            try {
                herikaLocalLlmNormalizeSetup($_POST);
            } catch (InvalidArgumentException $validationError) {
                echo json_encode(['ok' => false, 'error' => $validationError->getMessage()]);
                exit;
            }
        }

        $transactionStarted = false;
        try {
            if ($GLOBALS['db']->execQuery('BEGIN') === false) {
                throw new RuntimeException('Unable to start the Quickstart update.');
            }
            $transactionStarted = true;
            $settingsPresetResult = chimSettingsPresetApply($settingsPresetId, false);

            if ($oghma !== null && !chimSetGeneralSetting('OGHMA_INFINIUM', $oghma, chimGetSchemaDescription('OGHMA_INFINIUM'))) {
                throw new RuntimeException('Unable to save Oghma Infinium.');
            }

            $player2ConnectorId = null;
            if ($player2Force !== null) {
                $player2ConnectorId = LLMRandomizer::setPlayer2ForceEnabled($player2Force);
            }
            if ($localLlmRequested) {
                $localLlmResult = herikaLocalLlmApplySetup($_POST);
            }

            if ($GLOBALS['db']->execQuery('COMMIT') === false) {
                throw new RuntimeException('Unable to finish the Quickstart update.');
            }
            $transactionStarted = false;
            chimLoadGeneralSettingsIntoGlobals();
        } catch (Throwable $saveError) {
            if ($transactionStarted) {
                $GLOBALS['db']->execQuery('ROLLBACK');
            }
            error_log('[Quickstart] Profile save failed: ' . $saveError->getMessage());
            $clientMessage = $saveError instanceof InvalidArgumentException
                ? $saveError->getMessage()
                : 'Unable to save the selected profile settings.';
            echo json_encode(['ok' => false, 'error' => $clientMessage]);
            exit;
        }

        echo json_encode([
            'ok'=>true,
            'id'=>$pid,
            'player2_connector_id' => $player2ConnectorId,
            'settings_preset' => $settingsPresetResult,
            'local_llm' => $localLlmResult,
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
require_once($rootPath . "lib" . DIRECTORY_SEPARATOR . "core" . DIRECTORY_SEPARATOR . "settings_presets.php");
require_once($rootPath . "lib" . DIRECTORY_SEPARATOR . "core" . DIRECTORY_SEPARATOR . "local_llm_setup.php");

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
// The Local LLM recap starts hidden; the default Setup profile is selected on load.
$llmCardsLocalStyle = $llmCardsBaseStyle . ' display:none;';
$generalLlmConnectorSummary = herikaQuickstartGetGeneralLlmConnectorSummary($db);
$generalLlmConnectorListHtml = '';
if (!empty($generalLlmConnectorSummary)) {
    $generalLlmConnectorListHtml .= '<ul class="qs-general-connector-list">';
    foreach ($generalLlmConnectorSummary as $item) {
        $generalLlmConnectorListHtml .= '<li><span class="qs-general-connector-name">' . htmlspecialchars($item['name']) . ':</span> ' . htmlspecialchars($item['label']) . '</li>';
    }
    $generalLlmConnectorListHtml .= '</ul>';
}

// Quickstart offers the two built-in profiles as a compact choice.
$quickstartPresetDefaultId = 'builtin:default';
$quickstartLocalLlmPresetId = 'builtin:local_llm';
$quickstartPresetDescriptions = [
    'builtin:default' => 'The Recommended CHIM experience.',
    'builtin:local_llm' => 'Minimal mode for a local model around 13B sharing your GPU with Skyrim. NPCs still talk and act, but prompts and replies are shorter and most optional background AI features are turned off.',
];
$quickstartPresetSelectedDescription = (string)($quickstartPresetDescriptions[$quickstartPresetDefaultId] ?? '');
$quickstartPresetDescriptionsJson = json_encode($quickstartPresetDescriptions, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
if ($quickstartPresetDescriptionsJson === false) {
    $quickstartPresetDescriptionsJson = '{}';
}

// Reuse a previous Quickstart-managed connector without exposing its saved API key.
$quickstartLocalLlmSetup = herikaLocalLlmCurrentSetup();
$quickstartWslIp = trim(strval($quickstartLocalLlmSetup['wsl_ip'] ?? ''));
$quickstartHostIp = trim(strval($quickstartLocalLlmSetup['host_ip'] ?? ''));
$quickstartLocalLlmServerType = strval($quickstartLocalLlmSetup['server_type'] ?? 'lm_studio');
$quickstartLocalLlmDefaultUrl = trim(strval($quickstartLocalLlmSetup['url'] ?? ''));
if ($quickstartLocalLlmDefaultUrl === '') {
    $quickstartLocalLlmDefaultUrl = herikaLocalLlmDefaultEndpoint(
        'lm_studio',
        $quickstartHostIp !== '' ? $quickstartHostIp : '127.0.0.1'
    );
}
$quickstartLocalLlmModel = trim(strval($quickstartLocalLlmSetup['model'] ?? ''));
$quickstartLocalLlmScope = strval($quickstartLocalLlmSetup['scope'] ?? 'conversations');
$quickstartLocalLlmTimeout = max(5, min(120, intval($quickstartLocalLlmSetup['timeout'] ?? 30)));
$quickstartLocalLlmDisableStreamingChecked = !empty($quickstartLocalLlmSetup['disable_streaming']) ? ' checked' : '';
$quickstartLocalLlmApiKeyPlaceholder = !empty($quickstartLocalLlmSetup['has_api_key'])
    ? 'Saved key will be kept unless replaced'
    : 'Optional API key';
$quickstartLocalLlmServerOptions = '';
foreach (herikaLocalLlmServerCatalog() as $serverType => $serverDefinition) {
    $quickstartLocalLlmServerOptions .= '<option value="' . htmlspecialchars($serverType) . '"'
        . ($serverType === $quickstartLocalLlmServerType ? ' selected' : '') . '>'
        . htmlspecialchars($serverDefinition['label']) . '</option>';
}
// "all" is the only opt-in value; anything else falls back to the dialogue-only default.
$quickstartLocalLlmScopeAllChecked = ($quickstartLocalLlmScope === 'all') ? ' checked' : '';
$quickstartLocalLlmScopeConversationsChecked = ($quickstartLocalLlmScope === 'all') ? '' : ' checked';
$quickstartHostIpDisabled = ($quickstartHostIp === '') ? ' disabled' : '';
$quickstartHostIpTitle = ($quickstartHostIp === '')
    ? 'Network/HOST_IP is not configured yet.'
    : ('Use ' . $quickstartHostIp);
$quickstartWslIpDisabled = ($quickstartWslIp === '') ? ' disabled' : '';
$quickstartWslIpTitle = ($quickstartWslIp === '')
    ? 'Network/WSL_IP is not configured yet.'
    : ('Use ' . $quickstartWslIp);

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

// Setup lives in its own section, as a sibling of OpenRouter, because Player2 hides
// the OpenRouter section entirely and the profile picker must stay reachable in that mode.
echo '<section class="qs-section qs-profile-section" id="qs_settings_preset_section">
        <h2 class="qs-section-title">Setup</h2>
        <div class="form-group qs-field qs-settings-preset">
            <fieldset class="qs-preset-fieldset" id="qs_settings_preset" aria-describedby="qs_settings_preset_desc">
                <legend class="qs-preset-label">Profile</legend>
                <div class="qs-preset-options">
                    <label class="qs-preset-option">
                        <input class="qs-preset-input" type="radio" name="qs_settings_preset" value="' . htmlspecialchars($quickstartPresetDefaultId) . '" checked>
                        <span class="qs-preset-card">
                            <span class="qs-preset-mark" aria-hidden="true"></span>
                            <span class="qs-preset-title">Default</span>
                        </span>
                    </label>
                    <label class="qs-preset-option">
                        <input class="qs-preset-input" type="radio" name="qs_settings_preset" value="' . htmlspecialchars($quickstartLocalLlmPresetId) . '">
                        <span class="qs-preset-card">
                            <span class="qs-preset-mark" aria-hidden="true"></span>
                            <span class="qs-preset-title">Local LLM</span>
                        </span>
                    </label>
                </div>
            </fieldset>
            <p class="qs-preset-desc" id="qs_settings_preset_desc" role="status" aria-live="polite">' . htmlspecialchars($quickstartPresetSelectedDescription) . '</p>
        </div>
        <div class="qs-local-llm" id="qs_local_llm_panel" hidden>
            <div class="qs-local-llm-head">
                <h3 class="qs-local-llm-title">Local LLM Setup</h3>
            </div>
            <p class="qs-local-llm-note qs-local-llm-note-warn" id="qs_local_llm_player2_warning" role="status" aria-live="polite" hidden>Player2 is on. Player2 handles every LLM call, so these Local LLM fields are turned off and will not be used. Your values are kept if you switch Player2 back off.</p>
            <div class="qs-local-llm-grid">
                <div class="qs-local-llm-field">
                    <label for="qs_local_llm_server_type">Server type</label>
                    <div class="qs-select-wrap">
                        <select class="form-control" id="qs_local_llm_server_type" name="qs_local_llm_server_type">
                            ' . $quickstartLocalLlmServerOptions . '
                        </select>
                    </div>
                </div>
                <div class="qs-local-llm-field">
                    <label for="qs_local_llm_model">Model name</label>
                    <input type="text" class="form-control" id="qs_local_llm_model" name="qs_local_llm_model" value="' . htmlspecialchars($quickstartLocalLlmModel) . '" placeholder="llama-3.1-8b-instruct" autocomplete="off" spellcheck="false" required aria-describedby="qs_local_llm_model_help">
                    <small class="form-text" id="qs_local_llm_model_help">Enter the exact model id your server reports.</small>
                </div>
                <div class="qs-local-llm-field qs-local-llm-field-wide">
                    <label for="qs_local_llm_url">Server URL</label>
                    <input type="url" class="form-control" id="qs_local_llm_url" name="qs_local_llm_url" value="' . htmlspecialchars($quickstartLocalLlmDefaultUrl) . '" placeholder="http://192.168.1.10:1234/v1/chat/completions" autocomplete="off" spellcheck="false" inputmode="url" aria-describedby="qs_local_llm_url_help qs_local_llm_loopback_warning">
                    <div class="qs-local-llm-actions">
                        <button type="button" class="qs-mini-btn" id="qs_local_llm_use_host_ip" data-ip="' . htmlspecialchars($quickstartHostIp) . '" title="' . htmlspecialchars($quickstartHostIpTitle) . '"' . $quickstartHostIpDisabled . '>Use Windows host IP</button>
                        <button type="button" class="qs-mini-btn" id="qs_local_llm_use_wsl_ip" data-ip="' . htmlspecialchars($quickstartWslIp) . '" title="' . htmlspecialchars($quickstartWslIpTitle) . '"' . $quickstartWslIpDisabled . '>Use WSL IP</button>
                    </div>
                    <small class="form-text" id="qs_local_llm_url_help">OpenAI compatible chat completions endpoint. Defaults: LM Studio 1234, Ollama 11434, llama.cpp 8080, KoboldCPP 5001, path /v1/chat/completions.</small>
                    <p class="qs-local-llm-note qs-local-llm-note-warn" id="qs_local_llm_loopback_warning" role="status" aria-live="polite" hidden>Warning: this URL points at the WSL container itself. HerikaServer runs in WSL, so localhost and 127.0.0.1 will not reach a server running on Windows. Use the Windows host IP instead.</p>
                </div>
                <fieldset class="qs-local-llm-field-wide qs-scope-fieldset">
                    <legend class="qs-scope-legend">Where should CHIM use this model?</legend>
                    <div class="qs-scope-cards">
                        <label class="qs-scope-option">
                            <input class="qs-scope-input" type="radio" id="qs_local_llm_scope_conversations" name="qs_local_llm_scope" value="conversations"' . $quickstartLocalLlmScopeConversationsChecked . ' aria-labelledby="qs_local_llm_scope_conversations_title qs_local_llm_scope_conversations_badge" aria-describedby="qs_local_llm_scope_conversations_desc">
                            <span class="qs-scope-card">
                                <span class="qs-scope-head">
                                    <span class="qs-scope-mark" aria-hidden="true"></span>
                                    <span class="qs-scope-title" id="qs_local_llm_scope_conversations_title">Dialogue only</span>
                                    <span class="qs-scope-badge" id="qs_local_llm_scope_conversations_badge">Recommended</span>
                                </span>
                                <span class="qs-scope-desc" id="qs_local_llm_scope_conversations_desc">Use this local model for in-game dialogue. Other AI tasks continue using OpenRouter.</span>
                            </span>
                        </label>
                        <label class="qs-scope-option">
                            <input class="qs-scope-input" type="radio" id="qs_local_llm_scope_all" name="qs_local_llm_scope" value="all"' . $quickstartLocalLlmScopeAllChecked . ' aria-labelledby="qs_local_llm_scope_all_title" aria-describedby="qs_local_llm_scope_all_desc">
                            <span class="qs-scope-card">
                                <span class="qs-scope-head">
                                    <span class="qs-scope-mark" aria-hidden="true"></span>
                                    <span class="qs-scope-title" id="qs_local_llm_scope_all_title">Dialogue + background tasks</span>
                                </span>
                                <span class="qs-scope-desc" id="qs_local_llm_scope_all_desc">Also use it for memories, summaries, relationships, profiles, scene handling, and other supporting tasks.</span>
                            </span>
                        </label>
                    </div>
                </fieldset>
            </div>
            <details class="qs-local-llm-advanced">
                <summary>Advanced</summary>
                <div class="qs-local-llm-grid">
                    <div class="qs-local-llm-field">
                        <label for="qs_local_llm_api_key">API key</label>
                        <input type="password" class="form-control" id="qs_local_llm_api_key" name="qs_local_llm_api_key" value="" placeholder="' . htmlspecialchars($quickstartLocalLlmApiKeyPlaceholder) . '" autocomplete="new-password" aria-describedby="qs_local_llm_api_key_help">
                        <small class="form-text" id="qs_local_llm_api_key_help">Optional. Most local servers ignore it, but some require any non empty value.</small>
                    </div>
                    <div class="qs-local-llm-field">
                        <label for="qs_local_llm_timeout">Timeout (seconds)</label>
                        <input type="number" class="form-control" id="qs_local_llm_timeout" name="qs_local_llm_timeout" value="' . $quickstartLocalLlmTimeout . '" min="5" max="120" step="1" inputmode="numeric" aria-describedby="qs_local_llm_timeout_help">
                        <small class="form-text" id="qs_local_llm_timeout_help">How long to wait for a reply before giving up. Default 30.</small>
                    </div>
                    <div class="qs-local-llm-field qs-local-llm-field-wide">
                        <div class="form-check qs-local-llm-check">
                            <input class="form-check-input" type="checkbox" id="qs_local_llm_disable_streaming" name="qs_local_llm_disable_streaming" value="1"' . $quickstartLocalLlmDisableStreamingChecked . ' aria-describedby="qs_local_llm_disable_streaming_help">
                            <label class="form-check-label" for="qs_local_llm_disable_streaming">Disable streaming</label>
                        </div>
                        <small class="form-text" id="qs_local_llm_disable_streaming_help">Off by default. Turn on only if your server returns broken or empty streamed replies.</small>
                    </div>
                </div>
            </details>
            <div class="qs-local-llm-test">
                <button type="button" class="btn-primary qs-mini-btn qs-test-btn" id="qs_test_local_llm" aria-describedby="qs_local_llm_status">Test connection</button>
                <div class="qs-status qs-local-llm-status" id="qs_local_llm_status" role="status" aria-live="polite" hidden></div>
            </div>
            <pre class="qs-local-llm-preview" id="qs_local_llm_preview" hidden></pre>
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
                <p class="form-text" id="qs_llm_connectors_note_local" style="display:none;">Local LLM profile selected. The recap below reflects the Local LLM Setup fields in the Setup section and is applied on Save and Continue.</p>
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
                <div id="qs_llm_connectors_cards_local" style="' . $llmCardsLocalStyle . '">
                    <div style="background:#1f1f1f; border:1px solid #3b3b3b; border-radius:8px; padding:12px;">
                        <div style="font-size:14px; color:#cfd9ea;">&#x1F579;&#xFE0F; <b>Standard</b></div>
                        <div style="margin-top:6px; color:#9fb1c9;" data-local-slot="standard">Local model (name not set)</div>
                        <div style="margin-top:4px; color:#bbb; font-size:12px;" data-local-detail="standard">Server URL not set</div>
                    </div>
                    <div style="background:#1f1f1f; border:1px solid #3b3b3b; border-radius:8px; padding:12px;">
                        <div style="font-size:14px; color:#cfd9ea;">&#x1F3C3;&#x200D;&#x2642;&#xFE0F; <b>Fast</b></div>
                        <div style="margin-top:6px; color:#9fb1c9;" data-local-slot="fast">Local model (name not set)</div>
                        <div style="margin-top:4px; color:#bbb; font-size:12px;" data-local-detail="fast">Server URL not set</div>
                    </div>
                    <div style="background:#1f1f1f; border:1px solid #3b3b3b; border-radius:8px; padding:12px;">
                        <div style="font-size:14px; color:#cfd9ea;">&#x1F4AA; <b>Powerful</b></div>
                        <div style="margin-top:6px; color:#9fb1c9;" data-local-slot="powerful">Local model (name not set)</div>
                        <div style="margin-top:4px; color:#bbb; font-size:12px;" data-local-detail="powerful">Server URL not set</div>
                    </div>
                    <div style="background:#1f1f1f; border:1px solid #3b3b3b; border-radius:8px; padding:12px;">
                        <div style="font-size:14px; color:#cfd9ea;">&#x1F9EA; <b>Experimental</b></div>
                        <div style="margin-top:6px; color:#9fb1c9;" data-local-slot="experimental">Local model (name not set)</div>
                        <div style="margin-top:4px; color:#bbb; font-size:12px;" data-local-detail="experimental">Server URL not set</div>
                    </div>
                </div>
                <div class="qs-general-connector-wrap">
                    <div class="qs-general-connector-title" id="qs_general_connector_title">Other Connectors Used:</div>
                    <div id="qs_general_connector_default">
                        ' . ($generalLlmConnectorListHtml !== '' ? $generalLlmConnectorListHtml : '<div class="qs-general-connector-empty">No additional general-settings connectors are configured.</div>') . '
                    </div>
                    <div class="qs-general-connector-empty" id="qs_general_connector_local" style="display:none;">OpenRouter</div>
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

    /* Setup section: profile tiles + Local LLM ------------------------------ */
    .qs-settings-preset {
        display: grid;
        gap: 6px;
    }

    .confwizard fieldset.qs-preset-fieldset {
        display: block;
        width: 100%;
        min-width: 0;
        margin: 0;
        padding: 0;
        border: 0 !important;
        border-radius: 0 !important;
        background: transparent !important;
        box-shadow: none !important;
    }

    .confwizard fieldset.qs-preset-fieldset > legend.qs-preset-label {
        display: block;
        float: none;
        width: auto;
        padding: 0;
        margin: 0;
        border: 0;
        border-radius: 0;
        background: transparent !important;
        color: #cfd9ea !important;
        font-family: inherit;
        font-weight: 600;
        font-size: 0.9rem;
        line-height: 1.3;
    }

    .qs-preset-options {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 8px;
        margin-top: 6px;
        max-width: 560px;
    }

    .qs-preset-option {
        position: relative;
        display: block;
        min-width: 0;
        margin: 0;
        cursor: pointer;
        font-weight: 400;
    }

    .qs-preset-input {
        position: absolute;
        width: 1px;
        height: 1px;
        margin: 0;
        padding: 0;
        opacity: 0;
        pointer-events: none;
    }

    .qs-preset-card {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        min-height: 46px;
        height: 100%;
        padding: 9px 11px;
        border: 1px solid #4a4a4a;
        border-radius: 6px;
        background: #2c2c2c;
        text-align: center;
    }

    .qs-preset-option:hover .qs-preset-card {
        border-color: #5c5c5c;
        background: #383838;
    }

    .qs-preset-mark {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 auto;
        width: 15px;
        height: 15px;
        border: 1px solid #6b6b6b;
        border-radius: 50%;
        font-size: 0.7rem;
        line-height: 1;
    }

    .qs-preset-title {
        min-width: 0;
        color: #e9eefb;
        font-weight: 600;
        font-size: 0.9rem;
    }

    .qs-preset-input:checked + .qs-preset-card {
        border-color: #ffcc00;
        background: rgba(90, 70, 20, 0.22);
    }

    .qs-preset-input:checked + .qs-preset-card .qs-preset-mark {
        border-color: #ffcc00;
        background: #ffcc00;
        color: #1b1b1b;
    }

    .qs-preset-input:checked + .qs-preset-card .qs-preset-mark::before {
        content: "\2713";
    }

    .qs-preset-input:focus-visible + .qs-preset-card {
        outline: 2px solid #f6d365;
        outline-offset: 2px;
    }

    .qs-preset-desc {
        margin: 0;
        color: #b9c4d6;
        font-size: 0.9rem;
    }

    .qs-local-llm {
        margin-top: 14px;
        padding: 12px;
        border: 1px solid #3b3b3b;
        border-radius: 8px;
        background: rgba(20, 20, 20, 0.65);
    }

    .qs-preset-desc[hidden],
    .qs-local-llm[hidden],
    .qs-local-llm-note[hidden],
    .qs-local-llm-preview[hidden] {
        display: none !important;
    }

    .qs-local-llm-head {
        margin-bottom: 10px;
    }

    .qs-local-llm-title {
        margin: 0;
        color: #f1f4fa;
        font-weight: 600;
        font-size: 1rem;
    }

    .qs-local-llm-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px 12px;
    }

    .qs-local-llm-field {
        display: grid;
        gap: 4px;
        align-content: start;
        min-width: 0;
    }

    .qs-local-llm-field-wide {
        grid-column: 1 / -1;
    }

    .qs-local-llm-field > label {
        margin: 0;
        color: #cfd9ea;
        font-weight: 600;
        font-size: 0.9rem;
    }

    .qs-local-llm-field .form-text {
        margin-top: 0;
        font-size: 0.8rem;
    }

    .qs-local-llm-field .qs-select-wrap {
        max-width: none;
    }

    /* Routing scope: native radio group, whole card is the click target. */
    .confwizard fieldset.qs-scope-fieldset {
        display: block;
        width: 100%;
        min-width: 0;
        margin: 0;
        padding: 0;
        border: 0 !important;
        border-radius: 0 !important;
        background: transparent !important;
        box-shadow: none !important;
    }

    .confwizard fieldset.qs-scope-fieldset > legend.qs-scope-legend {
        display: block;
        float: none;
        width: auto;
        margin: 0 0 6px 0;
        padding: 0;
        border: 0;
        border-radius: 0;
        background: transparent !important;
        color: #cfd9ea !important;
        font-family: inherit;
        font-weight: 600;
        font-size: 0.9rem;
        line-height: 1.3;
    }

    .qs-scope-cards {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 8px;
    }

    .qs-scope-option {
        display: block;
        position: relative;
        margin: 0;
        min-width: 0;
        cursor: pointer;
        font-weight: 400;
    }

    .qs-scope-input {
        position: absolute;
        width: 1px;
        height: 1px;
        margin: 0;
        padding: 0;
        opacity: 0;
        pointer-events: none;
    }

    .qs-scope-card {
        display: flex;
        flex-direction: column;
        gap: 4px;
        min-height: 44px;
        height: 100%;
        padding: 9px 11px;
        border: 1px solid #4a4a4a;
        border-radius: 6px;
        background: #2c2c2c;
    }

    .qs-scope-option:hover .qs-scope-card {
        border-color: #5c5c5c;
        background: #383838;
    }

    .qs-scope-head {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 6px;
    }

    .qs-scope-mark {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 auto;
        width: 15px;
        height: 15px;
        border: 1px solid #6b6b6b;
        border-radius: 50%;
        font-size: 0.7rem;
        line-height: 1;
    }

    .qs-scope-title {
        color: #e9eefb;
        font-weight: 600;
        font-size: 0.9rem;
    }

    .qs-scope-badge {
        padding: 1px 6px;
        border: 1px solid #8a6d2f;
        border-radius: 10px;
        background: rgba(90, 70, 20, 0.28);
        color: #ffe6a6;
        font-size: 0.72rem;
        font-weight: 600;
        letter-spacing: 0.02em;
        text-transform: uppercase;
    }

    .qs-scope-desc {
        color: #9ca3af;
        font-size: 0.8rem;
        line-height: 1.35;
    }

    /* Selected state is marked by the checkmark as well as border and background. */
    .qs-scope-input:checked + .qs-scope-card {
        border-color: #ffcc00;
        background: rgba(90, 70, 20, 0.22);
    }

    .qs-scope-input:checked + .qs-scope-card .qs-scope-mark {
        border-color: #ffcc00;
        background: #ffcc00;
        color: #1b1b1b;
    }

    .qs-scope-input:checked + .qs-scope-card .qs-scope-mark::before {
        content: "\2713";
    }

    .qs-scope-input:checked + .qs-scope-card .qs-scope-desc {
        color: #bfc7d4;
    }

    .qs-scope-input:disabled + .qs-scope-card {
        cursor: not-allowed;
    }

    .qs-local-llm-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-top: 2px;
    }

    .qs-mini-btn {
        padding: 5px 10px;
        border: 1px solid #4a4a4a;
        border-radius: 6px;
        background: #2c2c2c;
        color: #e0e0e0;
        font-size: 0.82rem;
        line-height: 1.2;
        cursor: pointer;
    }

    .qs-mini-btn:hover:not(:disabled) {
        background: #383838;
        border-color: #5c5c5c;
    }

    .qs-mini-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    .confwizard .qs-local-llm .btn-primary.qs-mini-btn {
        padding: 7px 14px !important;
        margin: 0 !important;
        font-size: 0.9rem !important;
        border-width: 1px !important;
    }

    .qs-local-llm-check {
        display: flex;
        align-items: center;
        gap: 8px;
        margin: 0;
        padding: 0;
    }

    .qs-local-llm-check .form-check-input {
        position: static;
        margin: 0;
        width: 16px;
        height: 16px;
    }

    .qs-local-llm-check .form-check-label {
        margin: 0;
        color: #cfd9ea;
        font-weight: 600;
        font-size: 0.9rem;
    }

    .qs-local-llm-advanced {
        margin-top: 10px;
        border-top: 1px solid #3b3b3b;
        padding-top: 8px;
    }

    .qs-local-llm-advanced > summary {
        cursor: pointer;
        color: #cfd9ea;
        font-weight: 600;
        font-size: 0.9rem;
        list-style: revert;
        margin-bottom: 8px;
    }

    .qs-local-llm-note {
        margin: 6px 0 0 0;
        padding: 7px 9px;
        border: 1px solid #8a6d2f;
        border-left-width: 3px;
        border-radius: 6px;
        background: rgba(90, 70, 20, 0.28);
        color: #ffe6a6;
        font-size: 0.85rem;
    }

    .qs-local-llm-test {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 10px;
        margin-top: 12px;
    }

    .qs-local-llm-status {
        margin-top: 0;
        flex: 1 1 260px;
        min-width: 0;
    }

    .qs-local-llm-status.pending {
        border-color: #4a5a72;
        background: rgba(30, 45, 66, 0.35);
        color: #d6e3f5;
    }

    .qs-local-llm-preview {
        margin: 8px 0 0 0;
        padding: 8px 10px;
        border: 1px solid #3b3b3b;
        border-radius: 6px;
        background: #1b1b1b;
        color: #cfd8e3;
        font-size: 0.8rem;
        max-height: 140px;
        overflow: auto;
        white-space: pre-wrap;
        word-break: break-word;
    }

    #qs_llm_connectors_cards_local,
    #qs_llm_connectors_cards_local > div {
        min-width: 0;
    }

    #qs_llm_connectors_cards_local [data-local-detail] {
        overflow-wrap: anywhere;
        word-break: break-word;
    }

    /* Player2 owns every LLM call, so the panel is visibly inert but keeps its values. */
    .qs-local-llm.is-locked .qs-local-llm-grid,
    .qs-local-llm.is-locked .qs-local-llm-advanced,
    .qs-local-llm.is-locked .qs-local-llm-test,
    .qs-local-llm.is-locked .qs-local-llm-preview {
        opacity: 0.55;
    }

    .qs-profile-section select:focus-visible,
    .qs-profile-section input:focus-visible,
    .qs-profile-section button:focus-visible,
    .qs-profile-section summary:focus-visible {
        outline: 2px solid #ffcc00;
        outline-offset: 2px;
    }

    .qs-profile-section select:focus,
    .qs-profile-section input:focus,
    .qs-profile-section summary:focus {
        outline: 2px solid #ffcc00;
        outline-offset: 2px;
    }

    /* The radio itself is visually hidden, so the card label carries the focus ring. */
    .qs-profile-section .qs-scope-input:focus,
    .qs-profile-section .qs-scope-input:focus-visible {
        outline: none;
    }

    .qs-scope-input:focus + .qs-scope-card,
    .qs-scope-input:focus-visible + .qs-scope-card {
        outline: 2px solid #ffcc00;
        outline-offset: 2px;
    }

    @media (max-width: 480px) {
        #qs_llm_connectors_cards_local {
            grid-template-columns: 1fr !important;
        }

        .qs-local-llm-grid {
            grid-template-columns: 1fr;
        }

        .qs-local-llm-field-wide {
            grid-column: auto;
        }

        .qs-scope-cards {
            grid-template-columns: 1fr;
        }

        .qs-local-llm-actions .qs-mini-btn {
            flex: 1 1 100%;
        }

        .qs-local-llm-test {
            align-items: stretch;
            flex-direction: column;
        }

        .confwizard .qs-local-llm .btn-primary.qs-test-btn {
            width: 100%;
        }
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
const QS_PRESET_DESCRIPTIONS = '.$quickstartPresetDescriptionsJson.';
const QS_LOCAL_LLM_PRESET_ID = '.json_encode($quickstartLocalLlmPresetId).';
const QS_HOST_IP = '.json_encode($quickstartHostIp).';
const QS_WSL_IP = '.json_encode($quickstartWslIp).';
const QS_LOCAL_LLM_PATH = "/v1/chat/completions";
const QS_LOCAL_LLM_PORTS = { lm_studio: 1234, ollama: 11434, llama_cpp: 8080, koboldcpp: 5001 };
const QS_LOCAL_LLM_FIELD_IDS = [
  "qs_local_llm_server_type",
  "qs_local_llm_url",
  "qs_local_llm_model",
  "qs_local_llm_api_key",
  "qs_local_llm_disable_streaming",
  "qs_local_llm_timeout",
  "qs_local_llm_scope_conversations",
  "qs_local_llm_scope_all"
];

function qsEl(id){
  return document.getElementById(id);
}

function qsSettingsPresetValue(){
  const checked = document.querySelector("input[name=\"qs_settings_preset\"]:checked");
  return checked ? String(checked.value || "") : "builtin:default";
}

function qsLocalLlmSelected(){
  return qsSettingsPresetValue() === QS_LOCAL_LLM_PRESET_ID;
}

// The scope control is a radio group, so the value comes from whichever card is checked.
function qsLocalLlmScopeValue(){
  const checked = document.querySelector("input[name=\"qs_local_llm_scope\"]:checked");
  const value = checked ? String(checked.value || "").trim() : "";
  return value === "all" ? "all" : "conversations";
}

function qsPlayer2Enabled(){
  const toggle = qsEl("qs_player2_force_all_llm");
  return !!toggle && !!toggle.checked;
}

// Collected explicitly rather than through FormData(form) so values survive the disabled state
// that Player2 puts the panel into.
function qsLocalLlmValues(){
  const text = function(id){
    const el = qsEl(id);
    return el ? String(el.value || "").trim() : "";
  };
  const timeout = text("qs_local_llm_timeout");
  const streaming = qsEl("qs_local_llm_disable_streaming");
  const apiKey = qsEl("qs_local_llm_api_key");
  return {
    qs_local_llm_server_type: text("qs_local_llm_server_type") || "lm_studio",
    qs_local_llm_url: text("qs_local_llm_url"),
    qs_local_llm_model: text("qs_local_llm_model"),
    qs_local_llm_api_key: apiKey ? String(apiKey.value || "") : "",
    qs_local_llm_disable_streaming: (streaming && streaming.checked) ? "1" : "0",
    qs_local_llm_timeout: timeout !== "" ? timeout : "30",
    qs_local_llm_scope: qsLocalLlmScopeValue()
  };
}

function qsAppendLocalLlmValues(formData){
  const values = qsLocalLlmValues();
  Object.keys(values).forEach(function(key){
    formData.append(key, values[key]);
  });
}

function qsLocalLlmTemplateUrl(serverType, host){
  const port = QS_LOCAL_LLM_PORTS[serverType];
  if (!port) return "";
  const target = String(host || "").trim() || QS_HOST_IP || "127.0.0.1";
  return "http://" + target + ":" + port + QS_LOCAL_LLM_PATH;
}

function qsLocalLlmParseUrl(rawUrl){
  const match = String(rawUrl || "").trim().match(/^(https?):\/\/(\[[^\]]+\]|[^\/:\s]+)(?::(\d+))?(\/[^\s]*)?$/i);
  if (!match) return null;
  return {
    scheme: String(match[1] || "http").toLowerCase(),
    host: String(match[2] || ""),
    port: match[3] ? String(match[3]) : "",
    path: match[4] ? String(match[4]) : ""
  };
}

function qsLocalLlmIsLoopback(rawUrl){
  const parsed = qsLocalLlmParseUrl(rawUrl);
  if (!parsed) return false;
  const host = parsed.host.toLowerCase().replace(/^\[/, "").replace(/\]$/, "");
  if (host === "localhost" || host === "::1" || host === "0.0.0.0") return true;
  return /^127\./.test(host);
}

function qsLocalLlmApplyHost(ip){
  const urlEl = qsEl("qs_local_llm_url");
  const cleanIp = String(ip || "").trim();
  if (!urlEl || cleanIp === "") return;
  const typeEl = qsEl("qs_local_llm_server_type");
  const serverType = typeEl ? String(typeEl.value || "") : "";
  const parsed = qsLocalLlmParseUrl(urlEl.value);
  if (parsed) {
    const port = parsed.port !== "" ? parsed.port : String(QS_LOCAL_LLM_PORTS[serverType] || 1234);
    const path = parsed.path !== "" ? parsed.path : QS_LOCAL_LLM_PATH;
    urlEl.value = parsed.scheme + "://" + cleanIp + ":" + port + path;
  } else {
    urlEl.value = qsLocalLlmTemplateUrl(serverType || "lm_studio", cleanIp)
      || ("http://" + cleanIp + ":1234" + QS_LOCAL_LLM_PATH);
  }
  qsLocalLlmRefreshWarnings();
  qsLocalLlmUpdateRecap();
  qsLocalLlmInvalidateTest();
  try { urlEl.focus(); } catch(_e){}
}

// "Other" is the escape hatch for custom endpoints, so it never overwrites a typed URL.
function qsLocalLlmApplyServerTemplate(){
  const typeEl = qsEl("qs_local_llm_server_type");
  const urlEl = qsEl("qs_local_llm_url");
  if (!typeEl || !urlEl) return;
  const template = qsLocalLlmTemplateUrl(String(typeEl.value || ""), "");
  if (template !== "") {
    urlEl.value = template;
  }
  qsLocalLlmRefreshWarnings();
  qsLocalLlmUpdateRecap();
  qsLocalLlmInvalidateTest();
}

function qsLocalLlmRefreshWarnings(){
  const urlEl = qsEl("qs_local_llm_url");
  const loopbackWarning = qsEl("qs_local_llm_loopback_warning");
  if (loopbackWarning) {
    loopbackWarning.hidden = !(urlEl && qsLocalLlmIsLoopback(urlEl.value));
  }
  const player2Warning = qsEl("qs_local_llm_player2_warning");
  if (player2Warning) {
    player2Warning.hidden = !qsPlayer2Enabled();
  }
}

function qsLocalLlmApplyLockState(){
  const panel = qsEl("qs_local_llm_panel");
  if (!panel) return;
  const locked = qsPlayer2Enabled();
  panel.classList.toggle("is-locked", locked);
  const controls = panel.querySelectorAll("input, select, button, textarea");
  for (let i = 0; i < controls.length; i++) {
    controls[i].disabled = locked;
    if (locked) {
      controls[i].setAttribute("aria-disabled", "true");
    } else {
      controls[i].removeAttribute("aria-disabled");
    }
  }
  if (!locked) {
    const hostBtn = qsEl("qs_local_llm_use_host_ip");
    if (hostBtn) hostBtn.disabled = String(hostBtn.getAttribute("data-ip") || "").trim() === "";
    const wslBtn = qsEl("qs_local_llm_use_wsl_ip");
    if (wslBtn) wslBtn.disabled = String(wslBtn.getAttribute("data-ip") || "").trim() === "";
  }
}

function qsLocalLlmUpdateRecap(){
  const cards = qsEl("qs_llm_connectors_cards_local");
  if (!cards) return;
  const values = qsLocalLlmValues();
  const primary = values.qs_local_llm_model !== ""
    ? ("Local: " + values.qs_local_llm_model)
    : "Local model (name not set)";
  const detail = values.qs_local_llm_url !== "" ? values.qs_local_llm_url : "Server URL not set";
  const slots = cards.querySelectorAll("[data-local-slot]");
  for (let i = 0; i < slots.length; i++) {
    slots[i].textContent = primary;
  }
  const details = cards.querySelectorAll("[data-local-detail]");
  for (let i = 0; i < details.length; i++) {
    details[i].textContent = detail;
  }
}

function qsLocalLlmSetStatus(text, state){
  const status = qsEl("qs_local_llm_status");
  if (!status) return;
  status.hidden = text === "";
  status.className = "qs-status qs-local-llm-status" + (state ? (" " + state) : "");
  status.textContent = text;
}

function qsLocalLlmSetPreview(text){
  const preview = qsEl("qs_local_llm_preview");
  if (!preview) return;
  const clean = String(text || "").trim();
  if (clean === "") {
    preview.hidden = true;
    preview.textContent = "";
    return;
  }
  preview.textContent = clean.length > 600 ? (clean.slice(0, 600) + "…") : clean;
  preview.hidden = false;
}

function qsLocalLlmInvalidateTest(){
  qsLocalLlmSetStatus("", "");
  qsLocalLlmSetPreview("");
}

async function testLocalLlmConnection(){
  const button = qsEl("qs_test_local_llm");
  const values = qsLocalLlmValues();
  qsLocalLlmSetPreview("");
  if (values.qs_local_llm_url === "") {
    qsLocalLlmSetStatus("Failed - enter a server URL before testing.", "err");
    return;
  }
  if (values.qs_local_llm_model === "") {
    qsLocalLlmSetStatus("Failed - enter the model name before testing.", "err");
    return;
  }
  qsLocalLlmSetStatus("Testing - contacting the local server...", "pending");
  if (button) button.disabled = true;
  try {
    const fd = new FormData();
    fd.append("qs_action", "local_llm_test_draft");
    qsAppendLocalLlmValues(fd);
    const response = await fetch("quickstart.php", { method: "POST", body: fd, cache: "no-store", credentials: "same-origin" });
    let payload = null;
    try { payload = await response.json(); } catch(_e){ payload = null; }
    const result = (payload && payload.result) ? payload.result : {};
    const state = String(result.status || "").toLowerCase();
    const elapsed = Number(result.elapsed_ms || 0);
    const elapsedText = elapsed > 0 ? (" in " + elapsed + " ms") : "";
    const message = String(result.message || (payload && payload.error) || "").trim();
    const ok = !!(payload && payload.ok) && (state === "pass" || state === "warn");
    if (ok) {
      const prefix = state === "warn" ? "Warning" : "Success";
      qsLocalLlmSetStatus(prefix + " - the local server replied" + elapsedText + (message !== "" ? (". " + message) : "."), state === "warn" ? "pending" : "ok");
      const details = result.details || {};
      qsLocalLlmSetPreview(details.response_preview || "");
    } else {
      qsLocalLlmSetStatus("Failed - " + (message !== "" ? message : "no usable reply from the local server") + elapsedText + ".", "err");
    }
  } catch (_error) {
    qsLocalLlmSetStatus("Failed - the test request could not be completed.", "err");
  } finally {
    if (button) button.disabled = false;
    qsLocalLlmApplyLockState();
  }
}

// Selecting a profile only changes what this page shows; nothing is written until Save and Continue.
function updateQuickstartProfileUI(){
  try {
    const description = qsEl("qs_settings_preset_desc");
    const panel = qsEl("qs_local_llm_panel");
    const selectedId = qsSettingsPresetValue();
    if (description) {
      const copy = String(QS_PRESET_DESCRIPTIONS[selectedId] || "");
      description.textContent = copy;
      description.hidden = copy === "";
    }
    const showLocal = qsLocalLlmSelected();
    if (panel) {
      panel.hidden = !showLocal;
      panel.setAttribute("aria-hidden", showLocal ? "false" : "true");
    }
    qsLocalLlmRefreshWarnings();
    qsLocalLlmApplyLockState();
    qsLocalLlmUpdateRecap();
  } catch(_e){}
}

async function saveQuickstartAndDB(){
  const finishUrl = WEB_ROOT + "/ui/home.php";
  try {
    // 1) Save API keys
    const fd = new FormData();
    const orKey = document.getElementById("qs_openrouter_api_key");
    const dgKey = document.getElementById("qs_deepgram_api_key");
    fd.append("qs_action", "api_badge_quicksave");
    fd.append("openrouter_api_key", orKey ? orKey.value : "");
    fd.append("deepgram_api_key", dgKey ? dgKey.value : "");
    const badgeResponse = await fetch("quickstart.php", { method: "POST", body: fd, cache: "no-store", credentials: "same-origin" });
    const badgeResult = await badgeResponse.json();
    if (!badgeResponse.ok || !badgeResult.ok) {
      throw new Error(badgeResult.error || "Unable to save API keys");
    }

    // 2) Save profile metadata flags
    const fdm = new FormData();
    try { fdm.append("player2_force_all_llm", document.getElementById("qs_player2_force_all_llm").checked ? "1" : "0"); } catch(_e){}
    try { fdm.append("settings_preset_id", qsSettingsPresetValue()); } catch(_e){}
    try { if (qsLocalLlmSelected()) { qsAppendLocalLlmValues(fdm); } } catch(_e){}
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
    const settingsResponse = await fetch("quickstart.php", { method: "POST", body: fdw, cache: "no-store", credentials: "same-origin" });
    const settingsResult = await settingsResponse.json();
    if (!settingsResponse.ok || !settingsResult.ok) {
      throw new Error(settingsResult.error || "Unable to save Quickstart selections");
    }

    // Notify user, then redirect
    try { alert("Quickstart settings have been saved."); } catch(_a){}
    window.location.href = finishUrl;
  } catch (_e) {
    const message = (_e && _e.message) ? String(_e.message) : "Unknown error";
    try { alert("Save failed: " + message + ". Your Quickstart page has been kept open."); } catch(_a){}
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
    const localNote = document.getElementById("qs_llm_connectors_note_local");
    const defaultCards = document.getElementById("qs_llm_connectors_cards_default");
    const player2Cards = document.getElementById("qs_llm_connectors_cards_player2");
    const localCards = document.getElementById("qs_llm_connectors_cards_local");
    const generalTitle = document.getElementById("qs_general_connector_title");
    const generalDefault = document.getElementById("qs_general_connector_default");
    const generalLocal = document.getElementById("qs_general_connector_local");
    // Player2 wins over the Setup profile because it routes every LLM call.
    const localMode = !enabled && qsLocalLlmSelected();
    if (openrouterSection) {
      openrouterSection.style.display = enabled ? "none" : "";
    }
    if (defaultNote) {
      defaultNote.style.display = (enabled || localMode) ? "none" : "";
    }
    if (player2Note) {
      player2Note.style.display = enabled ? "" : "none";
    }
    if (localNote) {
      localNote.style.display = localMode ? "" : "none";
    }
    if (defaultCards) {
      defaultCards.style.display = (enabled || localMode) ? "none" : "grid";
    }
    if (player2Cards) {
      player2Cards.style.display = enabled ? "grid" : "none";
    }
    if (localCards) {
      localCards.style.display = localMode ? "grid" : "none";
    }
    if (generalTitle) {
      generalTitle.textContent = localMode ? "Other AI tasks:" : "Other Connectors Used:";
    }
    if (generalDefault) {
      generalDefault.style.display = localMode ? "none" : "";
    }
    if (generalLocal) {
      generalLocal.style.display = localMode ? "" : "none";
      generalLocal.textContent = qsLocalLlmScopeValue() === "all" ? "Local LLM" : "OpenRouter";
    }
  } catch(_e){}
}

document.addEventListener("DOMContentLoaded", function(){
  const player2Toggle = document.getElementById("qs_player2_force_all_llm");
  if (player2Toggle) {
    player2Toggle.addEventListener("change", function(){
      updateQuickstartProfileUI();
      updatePlayer2QuickstartUI();
    });
  }

  const presetChoices = qsEl("qs_settings_preset");
  if (presetChoices) {
    presetChoices.addEventListener("change", function(){
      updateQuickstartProfileUI();
      updatePlayer2QuickstartUI();
    });
  }

  const serverType = qsEl("qs_local_llm_server_type");
  if (serverType) {
    serverType.addEventListener("change", qsLocalLlmApplyServerTemplate);
  }

  const urlInput = qsEl("qs_local_llm_url");
  if (urlInput) {
    urlInput.addEventListener("input", function(){
      qsLocalLlmRefreshWarnings();
      qsLocalLlmUpdateRecap();
    });
  }

  const modelInput = qsEl("qs_local_llm_model");
  if (modelInput) {
    modelInput.addEventListener("input", qsLocalLlmUpdateRecap);
  }

  const scopeRadios = document.querySelectorAll("input[name=\"qs_local_llm_scope\"]");
  for (let i = 0; i < scopeRadios.length; i++) {
    scopeRadios[i].addEventListener("change", function(){
      qsLocalLlmUpdateRecap();
      updatePlayer2QuickstartUI();
    });
  }

  const hostIpButton = qsEl("qs_local_llm_use_host_ip");
  if (hostIpButton) {
    hostIpButton.addEventListener("click", function(){
      qsLocalLlmApplyHost(this.getAttribute("data-ip") || QS_HOST_IP);
    });
  }

  const wslIpButton = qsEl("qs_local_llm_use_wsl_ip");
  if (wslIpButton) {
    wslIpButton.addEventListener("click", function(){
      qsLocalLlmApplyHost(this.getAttribute("data-ip") || QS_WSL_IP);
    });
  }

  const testButton = qsEl("qs_test_local_llm");
  if (testButton) {
    testButton.addEventListener("click", testLocalLlmConnection);
  }

  QS_LOCAL_LLM_FIELD_IDS.forEach(function(id){
    const field = qsEl(id);
    if (!field) return;
    field.addEventListener("input", qsLocalLlmInvalidateTest);
    field.addEventListener("change", qsLocalLlmInvalidateTest);
  });

  updateQuickstartProfileUI();
  updatePlayer2QuickstartUI();
  checkMiniMeEndpoint();
});
</script>';

?>
