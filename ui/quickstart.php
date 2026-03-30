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
if (file_exists($rootPath . "conf" . DIRECTORY_SEPARATOR . "conf.php")) {
    require_once($rootPath . "conf" . DIRECTORY_SEPARATOR . "conf.php");  // Should contain current ones
}

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
        $row = $GLOBALS['db']->fetchOne("SELECT metadata FROM core_profiles WHERE id=".$pid." LIMIT 1");
        $meta = [];
        if (isset($row['metadata']) && $row['metadata'] !== '') {
            try { $tmp = json_decode($row['metadata'], true); if (is_array($tmp)) $meta = $tmp; } catch (Throwable $_) {}
        }
        if ($oghma  !== null) { $meta['OGHMA_INFINIUM'] = $oghma ? true : false; }
        $json = json_encode($meta, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
        $GLOBALS['db']->updateRow('core_profiles', [ 'metadata' => $json ], "id=".$pid);

        $player2ConnectorId = null;
        if ($player2Force !== null) {
            $player2ConnectorId = LLMRandomizer::setPlayer2ForceEnabled($player2Force ? true : false);
        }

        echo json_encode(['ok'=>true, 'id'=>$pid, 'player2_connector_id' => $player2ConnectorId]);
        exit;
    }

    if ($action === 'save_conf') {
        require_once($rootPath . "conf" . DIRECTORY_SEPARATOR . 'conf_loader.php');
        $confSchemaFlat = conf_loader_load_schema();
        $currentConf = conf_loader_load();
        $allPairs = [];
        foreach ($currentConf as $pname => $parms) {
            $fieldName = strtr($pname, [" " => "@"]); // flatten
            $type = $parms['type'] ?? ($confSchemaFlat[$pname]['type'] ?? 'string');
            $val = $parms['currentValue'] ?? '';
            if ($type === 'boolean') $allPairs[$fieldName] = $val ? 'true' : 'false';
            else if ($type === 'selectmultiple') $allPairs[$fieldName] = is_array($val) ? $val : [];
            else $allPairs[$fieldName] = (string)$val;
        }
        // Override with posted values from Quickstart form
        foreach ($_POST as $k => $v) {
            if ($k === 'qs_action' || $k === 'profile') continue;
            $plain = strtr($k, ["@" => " "]);
            $type = $confSchemaFlat[$plain]['type'] ?? 'string';
            if (is_array($v)) {
                $allPairs[$k] = $v;
            } else if ($type === 'number') {
                if ($v === '') continue; else $allPairs[$k] = (string)$v;
            } else if ($type === 'boolean') {
                $allPairs[$k] = ($v === 'true') ? 'true' : 'false';
            } else {
                $allPairs[$k] = (string)$v;
            }
        }
        
        // Save PLAYER_NAME to core_player table
        if (isset($_POST['PLAYER_NAME']) && $_POST['PLAYER_NAME'] !== '') {
            try {
                require_once($rootPath . "lib" . DIRECTORY_SEPARATOR . "core" . DIRECTORY_SEPARATOR . "player.class.php");
                $player = new Player();
                $player->set('player_name', $_POST['PLAYER_NAME']);
            } catch (Exception $e) {
                // Silently fail, will still save to conf.php
            }
        }
        
        // Build and write conf.php
        $buffer = "<?php" . PHP_EOL;
        $oldGroup = '';
        $oldSubGroup = '';
        $process_slashes = function(string $s_input): string { $sx = str_replace("\\'", "'", $s_input); return addcslashes($sx, "'"); };
        foreach ($allPairs as $k => $v) {
            $full = explode('@', $k);
            $plain = strtr($k, ['@' => ' ']);
            $type = $confSchemaFlat[$plain]['type'] ?? 'string';
            if (is_array($v)) $value = json_encode($v, true);
            else if ($type === 'number') { if ($v === '') continue; else $value = "" . addcslashes($v, "'") . ""; }
            else if ($type === 'boolean') $value = ($v === 'true') ? 'true' : 'false';
            else $value = "'" . $process_slashes((string)$v) . "'";
            if ($oldGroup !== $full[0]) { $buffer .= PHP_EOL . PHP_EOL; $oldGroup = $full[0]; }
            if (isset($full[1]) && $oldSubGroup !== $full[1]) { $buffer .= PHP_EOL; $oldSubGroup = $full[1]; }
            if (count($full) === 1) { if (isset($confSchemaFlat[$plain]['description'])) $buffer .= "//" . $confSchemaFlat[$plain]['description'] . PHP_EOL; $buffer .= '$' . $full[0] . '=' . $value . ';' . PHP_EOL; }
            else if (count($full) === 2) { $inline = isset($confSchemaFlat[$plain]['description']) ? ("//" . $confSchemaFlat[$plain]['description']) : ''; $buffer .= '$' . $full[0] . '["' . $full[1] . '"]=' . $value . ';' . "\t" . $inline . PHP_EOL; }
            else if (count($full) === 3) { $inline = isset($confSchemaFlat[$plain]['description']) ? ("//" . $confSchemaFlat[$plain]['description']) : ''; $buffer .= '$' . $full[0] . '["' . $full[1] . '"]["' . $full[2] . '"]=' . $value . ';' . "\t" . $inline . PHP_EOL; }
        }
        $buffer .= "?>" . PHP_EOL;
        $target = $rootPath . "conf" . DIRECTORY_SEPARATOR . "conf.php";
        $result = @file_put_contents($target, $buffer);
        echo json_encode([ 'ok' => $result !== false ]);
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

require($rootPath . "conf" . DIRECTORY_SEPARATOR . 'conf_loader.php');

$configFilepath = realpath($configFilepath) . DIRECTORY_SEPARATOR;

// Function to compare modification dates
function compareFileModificationDate($a, $b) {
    return filemtime($b) - filemtime($a);
}

// Profile selection
foreach (glob($configFilepath . 'conf_????????????????????????????????.php') as $mconf) {
    if (file_exists($mconf)) {
        $filename = basename($mconf);
        $pattern = '/conf_([a-f0-9]+)\.php/';
        preg_match($pattern, $filename, $matches);
        $hash = $matches[1];
        $GLOBALS["PROFILES"]["$hash"] = $mconf;
    }
}

// Sort the profiles by modification date descending
if (is_array($GLOBALS["PROFILES"])) {
    usort($GLOBALS["PROFILES"], 'compareFileModificationDate');
} else {
    $GLOBALS["PROFILES"] = [];
}

$GLOBALS["PROFILES"] = array_merge(["default" => "$configFilepath/conf.php"], $GLOBALS["PROFILES"]);

if (isset($_SESSION["PROFILE"]) && in_array($_SESSION["PROFILE"], $GLOBALS["PROFILES"])) {
    require_once($_SESSION["PROFILE"]);
} else {
    $_SESSION["PROFILE"] = "$configFilepath/conf.php";
    require_once($_SESSION["PROFILE"]);
}
// End of profile selection

include(__DIR__.DIRECTORY_SEPARATOR."tmpl/head.html");

$debugPaneLink = false;
include(__DIR__.DIRECTORY_SEPARATOR."tmpl/navbar.php");

$rootPath = __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR;
$configFilepath = __DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."conf".DIRECTORY_SEPARATOR;
$rootEnginePath = __DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR;

$configFilepath = realpath($configFilepath) . DIRECTORY_SEPARATOR;

// Include necessary files
require_once($rootPath . "lib" . DIRECTORY_SEPARATOR . "model_dynmodel.php");
require_once($rootPath . "conf" . DIRECTORY_SEPARATOR . "conf.sample.php"); // Defaults
if (file_exists($rootPath . "conf" . DIRECTORY_SEPARATOR . "conf.php")) {
    require_once($rootPath . "conf" . DIRECTORY_SEPARATOR . "conf.php"); // Current configs
}
require_once($rootPath . "lib" . DIRECTORY_SEPARATOR . "llm_randomizer.php");
require_once($rootPath . "conf" . DIRECTORY_SEPARATOR . 'conf_loader.php');

/* DB update logic */
require_once($rootEnginePath . "lib" .DIRECTORY_SEPARATOR."{$GLOBALS["DBDRIVER"]}.class.php");
$db = new sql();
/* Check for database updates */
require_once(__DIR__."/../debug/db_updates.php");
/* END of check database for updates */

// Load current configurations
$currentConf = conf_loader_load();
$currentConfTitles = conf_loader_load_titles();

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
echo '<main>';
echo '<div class="container">
        <form action="" method="post" name="mainC" class="confwizard" id="top">
            <input type="hidden" name="profile" value="' . htmlspecialchars($_SESSION["PROFILE"]) . '" />
      ';

// Main Heading
echo '<div class="container">
      <h1 class="qs-title text-center mb-4">Quickstart Menu</h1>
      <h2 class="qs-subtitle text-center mb-4">Only to be used for the initial setup!</h2>
      <h3 class="qs-note text-center mb-4">If you want to make more advanced changes before playing go to the Configuration tab above.</h3>
    </div>';

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
echo '<div class="container">
        <div class="form-group">
            <label for="PLAYER_NAME">Player Name</label>
            <input type="text" class="form-control" id="PLAYER_NAME" name="PLAYER_NAME" value="' . htmlspecialchars($playerNameVal) . '">
            <small class="form-text">Your in-game character name. Defaults to "Prisoner" and is automatically updated when you load a save. You can also manage player settings in <a href="' . $webRoot . '/ui/core/config_hub.php?tab=player" target="_blank" style="color:#4a8ab6;">Player Management</a>.</small>
        </div>
      </div>';

// API Keys section (OpenRouter only here; Deepgram rendered under STT)
try { $openrouterRow = $db->fetchOne("SELECT api_key FROM core_api_badge WHERE lower(label)='openrouter' LIMIT 1"); } catch (Throwable $_e) { $openrouterRow = []; }
$openrouterKey = isset($openrouterRow["api_key"]) ? $openrouterRow["api_key"] : "";
$player2ForceAllLlm = false;
try { $player2ForceAllLlm = LLMRandomizer::isPlayer2ForceEnabled(); } catch (Throwable $_e) { $player2ForceAllLlm = false; }
$player2ForceChecked = $player2ForceAllLlm ? " checked" : "";
$llmNoteDefaultStyle = $player2ForceAllLlm ? ' style="display:none;"' : '';
$llmNotePlayer2Style = $player2ForceAllLlm ? '' : ' style="display:none;"';
$llmCardsBaseStyle = 'display:grid; grid-template-columns:repeat(auto-fit,minmax(240px,1fr)); gap:12px; margin-top:8px;';
$llmCardsDefaultStyle = $llmCardsBaseStyle . ($player2ForceAllLlm ? ' display:none;' : '');
$llmCardsPlayer2Style = $llmCardsBaseStyle . ($player2ForceAllLlm ? '' : ' display:none;');

// Preload default profile metadata flags for Oghma (safe if tables missing)
$oghmaChecked = "";
try {
    $rowPid = $db->fetchOne("SELECT id FROM core_profiles ORDER BY CASE WHEN lower(label)='default' THEN 0 WHEN default_narrator='1' THEN 1 WHEN default_npc='1' THEN 2 ELSE 3 END, id ASC LIMIT 1");
    $pid = isset($rowPid['id']) ? intval($rowPid['id']) : 0;
    if ($pid > 0) {
        $rowMeta = $db->fetchOne("SELECT metadata FROM core_profiles WHERE id=".$pid." LIMIT 1");
        if (isset($rowMeta['metadata']) && $rowMeta['metadata'] !== '') {
            $meta = json_decode($rowMeta['metadata'], true);
            if (is_array($meta)) {
                $isTruthy = function($v){
                    if ($v === true || $v === 1) return true;
                    $s = strtolower(trim((string)$v));
                    return in_array($s, ['1','true','yes','on'], true);
                };
                if (array_key_exists('OGHMA_INFINIUM', $meta) && $isTruthy($meta['OGHMA_INFINIUM'])) { $oghmaChecked = " checked"; }
            }
        }
    }
} catch (Throwable $_e) { /* ignore on first-run before tables exist */ }

$minimeProbeUrl = herikaQuickstartMiniMeDefaultUrl();
$minimeProbeResult = herikaQuickstartProbeUrl($minimeProbeUrl);
$minimeHealthyInitial = !empty($minimeProbeResult['ok']);
$minimeStatusText = $minimeHealthyInitial
    ? ('MiniMe reachable (' . intval($minimeProbeResult['http_code'] ?? 0) . ') in ' . intval($minimeProbeResult['latency_ms'] ?? 0) . ' ms.')
    : ('MiniMe not reachable (' . intval($minimeProbeResult['http_code'] ?? 0) . ') in ' . intval($minimeProbeResult['latency_ms'] ?? 0) . ' ms. ' . trim(strval($minimeProbeResult['error'] ?? '')));
$minimeStatusClass = $minimeHealthyInitial ? 'qs-status ok' : 'qs-status err';
$oghmaContainerStyle = $minimeHealthyInitial ? '' : ' style="display:none;"';

echo '<div class="container">
        <div class="form-group">
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
      </div>';

echo '<div class="container" id="qs_openrouter_section"' . ($player2ForceAllLlm ? ' style="display:none;"' : '') . '>
        <div class="form-group">
            <label for="qs_openrouter_api_key">OpenRouter API Key</label>
            <div class="input-group">
                <input type="password" class="form-control" id="qs_openrouter_api_key" value="' . htmlspecialchars($openrouterKey) . '" style="filter: blur(3px);">
                <div class="input-group-append">
                    <button class="btn-primary" type="button" onclick="document.getElementById(\'qs_openrouter_api_key\').style.filter=\'blur(0px)\'; document.getElementById(\'qs_openrouter_api_key\').setAttribute(\'type\', \'text\');">Unhide</button>
                </div>
            </div>
            <small class="form-text">Paste your OpenRouter API key. <a href="https://openrouter.ai/keys" target="_blank">Create key</a></small>
        </div>
      </div>';

echo '<div class="container">
        <div class="form-group">
            <label>MiniMe Service</label>
            <small class="form-text">Checks if MiniMe is reachable at the local default endpoint. Oghma is only shown when MiniMe is healthy.</small>
            <input id="qs_minime_probe_url" type="hidden" value="' . htmlspecialchars($minimeProbeUrl) . '">
            <div id="qs_minime_probe_status" class="' . $minimeStatusClass . '">' . htmlspecialchars(trim($minimeStatusText)) . '</div>
        </div>
      </div>';

echo '<div class="container" id="qs_oghma_section"' . $oghmaContainerStyle . '>
        <br>
        <div class="form-group">
            <div class="qs-toggle-block">
                <div class="qs-toggle-header">
                    <label class="qs-toggle-title" for="qs_oghma_infinium">Enable Oghma Infinium</label>
                    <div class="qs-toggle-control">
                        <input class="form-check-input qs-switch-input" type="checkbox" id="qs_oghma_infinium" value="1"' . $oghmaChecked . '>
                        <label class="form-check-label qs-switch-label" for="qs_oghma_infinium">
                            <span class="qs-switch-track"></span>
                            <span class="qs-switch-copy" data-off="Off" data-on="On"></span>
                        </label>
                    </div>
                </div>
            </div>
            <small class="form-text">Uses MiniMe-T5 automatically when service is running. Oghma Infinium improves AI roleplay by adding and restricting lore to NPCs.</small>
        </div>
      </div>';

if ($_SESSION["PROFILE"] == "$configFilepath/conf.php") {
    $DEFAULT_PROFILE = true;
} else {
    $DEFAULT_PROFILE = false;
}

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

    // Handle scope and constant parameters
    if ($DEFAULT_PROFILE && $fieldName == "HERIKA_NAME") {
        $fieldValue = "The Narrator";
        $FORCE_DISABLED = " readonly='true' ";
    } else {
        $FORCE_DISABLED = "";
    }

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

    echo "<div class='" . $groupClass . "' $MAKE_NO_VISIBLE_MARK>";

    // Label
    $displayLabel = $pname;
    if ($pname == "TTSFUNCTION") {
        $displayLabel = "TTS Service";
    } else if ($pname == "STTFUNCTION") {
        $displayLabel = "STT Service";
    }
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
            'pockettts'    => 'PocketTTS',
            'chatterbox'   => 'Chatterbox',
            'xtts-fastapi' => 'XTTS',
            'melotts'      => 'MeloTTS',
            'parakeet'     => 'Parakeet',
            'deepgram'     => 'Deepgram',
            'localwhisper' => 'Local Whisper',
        ];
        if ($pname == "TTSFUNCTION") {
            $parms["values"] = ["pockettts","chatterbox","xtts-fastapi","melotts"];
            $parms["description"] = "Select the TTS service you wish to use. <br>You can install PocketTTS, Chatterbox, XTTS and MeloTTS in the CHIM Launcher under <b>Install Components.</b>";
        } else if ($pname == "STTFUNCTION") {
            $parms["values"] = ["parakeet","deepgram","localwhisper"];
            $parms["description"] = "Select the STT service you wish to use.";
        }
        echo "<select class='form-control' id='$fieldName' name='" . htmlspecialchars($fieldName) . "' $FORCE_DISABLED>";
        foreach ($parms["values"] as $item) {
            $selected = ($item == $parms["currentValue"]) ? "selected" : "";
            $displayName = $selectDisplayNames[$item] ?? $item;
            echo "<option value='" . htmlspecialchars($item) . "' $selected>" . htmlspecialchars($displayName) . "</option>";
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
}


echo "<br>";
echo '<div class="btn-group-custom text-center">
        <div class="btn-group-custom text-center">
            <div class="btn-group-custom text-center">
                <h3 class="warning-text3">
                    Once done click Save and startup Skyrim with the AIAgent mod installed. Please read the <a href="https://dwemerdynamics.hostwiki.io/" target="_blank" style="color: #ffcc00; text-decoration: underline;">CHIM Wiki</a> to learn more about how CHIM works.
                </h3>
            </div>';


echo '      <div class="container">
                <h2 class="qs-section-title">LLM Connectors Note</h2>
                <p class="form-text" id="qs_llm_connectors_note_default"' . $llmNoteDefaultStyle . '>The default CHIM installation comes with 4 predefined LLMs that you can hotswap ingame.</p>
                <p class="form-text" id="qs_llm_connectors_note_player2"' . $llmNotePlayer2Style . '>Player2 mode is active. Standard, Fast, Powerful, and Experimental all use the local Player2 connector, and Diary, Formatter, plus Fallback also route through Player2. The actual model stays controlled in the Player2 app.</p>
                <div id="qs_llm_connectors_cards_default" style="' . $llmCardsDefaultStyle . '">
                    <div style="background:#1f1f1f; border:1px solid #3b3b3b; border-radius:8px; padding:12px;">
                        <div style="font-size:14px; color:#cfd9ea;">&#x1F579;&#xFE0F; <b>Standard</b></div>
                        <div style="margin-top:6px; color:#9fb1c9;">OpenRouter: GLM 4.7 (z-ai/glm-4.7)</div>
                        <div style="margin-top:4px; color:#bbb; font-size:12px;">Released Dec 22, 2025 | 202,752 context</div>
                        <div style="margin-top:4px; color:#bbb; font-size:12px;">$0.39/M input | $1.75/M output</div>
                    </div>
                    <div style="background:#1f1f1f; border:1px solid #3b3b3b; border-radius:8px; padding:12px;">
                        <div style="font-size:14px; color:#cfd9ea;">&#x1F3C3;&#x200D;&#x2642;&#xFE0F; <b>Fast</b></div>
                        <div style="margin-top:6px; color:#9fb1c9;">Gemini 2.0 Flash</div>
                        <div style="margin-top:4px; color:#bbb; font-size:12px;">$0.10/M input | $0.40/M output</div>
                    </div>
                    <div style="background:#1f1f1f; border:1px solid #3b3b3b; border-radius:8px; padding:12px;">
                        <div style="font-size:14px; color:#cfd9ea;">&#x1F4AA; <b>Powerful</b></div>
                        <div style="margin-top:6px; color:#9fb1c9;">Claude Sonnet 4.5</div>
                        <div style="margin-top:4px; color:#bbb; font-size:12px;">$3.00/M input | $15.00/M output</div>
                    </div>
                    <div style="background:#1f1f1f; border:1px solid #3b3b3b; border-radius:8px; padding:12px;">
                        <div style="font-size:14px; color:#cfd9ea;">&#x1F9EA; <b>Experimental</b></div>
                        <div style="margin-top:6px; color:#9fb1c9;">DeepSeek Chat V3.1</div>
                        <div style="margin-top:4px; color:#bbb; font-size:12px;">$0.15/M input | $0.75/M output</div>
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
            </div>

            <button
                type="button"
                class="btn-primary"
                name="save"
                value="Save"
                style="background-color: #28a745 !important;"
                onclick="saveQuickstartAndDB()"
            >
                Save
            </button>
        </div>
    </div>';



echo '</main>'; // End of container/main

include("tmpl/footer.html");

$buffer = ob_get_contents();
ob_end_clean();
$title = $TITLE;
$buffer = preg_replace('/(<title>)(.*?)(<\/title>)/i', '$1' . $title . '$3', $buffer);
echo $buffer;

echo '<style>
    @font-face { font-family: "MagicCards"; src: url("css/font/MagicCardsNormal.ttf") format("truetype"); font-weight: normal; font-style: normal; }
    /* Override main container styles */
    main {
        padding-top: 80px;
        padding-bottom: 40px;
        padding-left: 10px;
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

    /* Additional quickstart-specific styles */
    .container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 20px;
    }

    /* Headings styled like Oghma */
    .qs-title {
        margin: 0 0 12px 0;
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
        margin: 12px 0 10px 0;
        font-size: 1.4em;
    }

    .confwizard {
        background-color: #1e1e1e;
        padding: 30px 30px 24px 30px;
        border-radius: 8px;
        box-shadow: 0 0 15px rgba(0, 0, 0, 0.5);
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
let qsMinimeHealthy = '.json_encode($minimeHealthyInitial).';

function updateOghmaQuickstartUI(isHealthy){
  try {
    qsMinimeHealthy = !!isHealthy;
    const oghmaSection = document.getElementById("qs_oghma_section");
    if (oghmaSection) {
      oghmaSection.style.display = qsMinimeHealthy ? "" : "none";
    }
  } catch(_e){}
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
      updateOghmaQuickstartUI(false);
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
      updateOghmaQuickstartUI(true);
    } else {
      status.textContent = `MiniMe not reachable (${http || 0}) in ${latency} ms. ${message}`;
      status.classList.remove("ok");
      status.classList.add("err");
      updateOghmaQuickstartUI(false);
    }
  } catch (_error) {
    const status = document.getElementById("qs_minime_probe_status");
    if (status) {
      status.textContent = "MiniMe probe failed.";
      status.classList.remove("ok");
      status.classList.add("err");
    }
    updateOghmaQuickstartUI(false);
  }
}

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
    try {
      const oghmaToggle = document.getElementById("qs_oghma_infinium");
      if (qsMinimeHealthy && oghmaToggle) {
        fdm.append("oghma_infinium", oghmaToggle.checked ? "1" : "0");
      }
    } catch(_e){}
    try { fdm.append("player2_force_all_llm", document.getElementById("qs_player2_force_all_llm").checked ? "1" : "0"); } catch(_e){}
    fdm.append("qs_action", "profile_quicksave_metadata");
    await fetch("quickstart.php", { method: "POST", body: fdm, cache: "no-store", credentials: "same-origin" });

    // 3) Save conf.php with all form values
    const form = document.getElementById("top");
    const fdw = new FormData(form);
    fdw.append("qs_action", "save_conf");
    await fetch("quickstart.php", { method: "POST", body: fdw, cache: "no-store", credentials: "same-origin" });

    // Notify user, then redirect
    try { alert("Quickstart settings have been saved."); } catch(_a){}
    window.location.href = finishUrl;
  } catch (_e) {
    try { alert("Save failed or partially completed. Redirecting to home."); } catch(_a){}
    window.location.href = finishUrl;
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
