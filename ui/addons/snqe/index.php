<?php

$enginePath = dirname(__FILE__) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR;
require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "runtime_bootstrap.php";
chimRuntimeBootstrap($enginePath, [
    'load_general_settings' => true,
    'load_player_name' => true,
    'load_narrator' => true,
]);

require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "model_dynmodel.php";
require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "chat_helper_functions.php";
require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "data_functions.php";
require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "logger.php";

$GLOBALS["ENGINE_PATH"] = $enginePath;
$stateFile = "{$enginePath}log/snqe_state.json";
$logTailLines = 400;

$db = $GLOBALS["db"];

$isEmbed = (isset($_GET['embed']) && $_GET['embed'] == '1');

// Determine web root
$scriptPath = $_SERVER['SCRIPT_NAME'];
$uiPos = strpos($scriptPath, '/ui/');
$webRoot = ($uiPos !== false) ? substr($scriptPath, 0, $uiPos) : '';
$webRoot = rtrim($webRoot, '/');

// Function to read last N lines from a file
function getTailOfFile($filepath, $lines = 100) {
    if (!file_exists($filepath)) {
        return "File not found: " . htmlspecialchars($filepath);
    }
    if (!is_readable($filepath)) {
        return "Cannot read file (permission denied): " . htmlspecialchars($filepath);
    }
    $file = @fopen($filepath, 'r');
    if ($file === false) {
        return "Cannot read file: " . htmlspecialchars($filepath);
    }
    fseek($file, 0, SEEK_END);
    $fileSize = ftell($file);
    $buffer = min($fileSize, 8192);
    $position = $fileSize;
    $lineCount = 0;
    $content = '';
    while ($position > 0 && $lineCount <= $lines) {
        $position = max(0, $position - $buffer);
        fseek($file, $position);
        $chunk = fread($file, min($buffer, $fileSize - $position));
        $content = $chunk . $content;
        $lineCount = substr_count($content, "\n");
    }
    fclose($file);
    $allLines = explode("\n", $content);
    $lastLines = array_slice($allLines, -$lines);
    return implode("\n", $lastLines);
}

function buildCombinedLog($agentLog, $serviceLog) {
    return implode("\n", [
        "===== Quest Runner (log_run_agent.log) =====",
        trim((string) $agentLog) !== '' ? $agentLog : 'No quest runner log data available.',
        "",
        "===== Service (service.log) =====",
        trim((string) $serviceLog) !== '' ? $serviceLog : 'No service log data available.',
    ]);
}

function getFileContentsSafe($filepath) {
    if (!file_exists($filepath)) {
        return "File not found: " . htmlspecialchars($filepath);
    }
    if (!is_readable($filepath)) {
        return "Cannot read file (permission denied): " . htmlspecialchars($filepath);
    }
    $content = @file_get_contents($filepath);
    if ($content === false) {
        return "Cannot read file: " . htmlspecialchars($filepath);
    }
    return $content;
}

$logRunAgent = getTailOfFile("{$enginePath}log/log_run_agent.log", $logTailLines);
$serviceLog  = getTailOfFile("{$enginePath}log/service.log", $logTailLines);
$combinedLog = buildCombinedLog($logRunAgent, $serviceLog);

function getRunningQuests($db) {
    try {
        $query = "SELECT quest_id, title, stage, created_at, updated_at FROM sneq_quests WHERE quest_run_state = 'running' ORDER BY updated_at DESC";
        return $db->fetchAll($query);
    } catch (Exception $e) {
        Logger::error("Failed to fetch running quests: " . $e->getMessage());
        return [];
    }
}

$runningQuests = getRunningQuests($db);

function getLocationStats($db) {
    $default = [
        'table_exists' => false,
        'location_count' => 0
    ];

    try {
        $existsQuery = "
            SELECT EXISTS (
                SELECT 1
                FROM information_schema.tables
                WHERE table_schema = 'public'
                  AND table_name = 'locations'
            ) AS table_exists
        ";
        $existsResult = $db->fetchAll($existsQuery);
        if (empty($existsResult) || !isset($existsResult[0]['table_exists']) || $existsResult[0]['table_exists'] !== 't') {
            return $default;
        }

        $countQuery = "SELECT COUNT(*) AS location_count FROM public.locations";
        $countResult = $db->fetchAll($countQuery);
        $count = (!empty($countResult) && isset($countResult[0]['location_count'])) ? intval($countResult[0]['location_count']) : 0;

        return [
            'table_exists' => true,
            'location_count' => max(0, $count)
        ];
    } catch (Exception $e) {
        Logger::error("Failed to fetch SNQE location stats: " . $e->getMessage());
        return $default;
    }
}

$locationStats = getLocationStats($db);

// Fetch current pending step
function getPendingStep($db) {
    try {
        $query = "SELECT * FROM conf_opts WHERE id='snqe_pending_step'";
        $result = $db->fetchAll($query);
        if (!empty($result)) {
            return $result[0]['value'];
        }
        return 'No pending step';
    } catch (Exception $e) {
        Logger::error("Failed to fetch pending step: " . $e->getMessage());
        return 'Error fetching pending step';
    }
}

$pendingStep = getPendingStep($db);

// Read staged quest from state file
function getStagedQuestTitle($stateFile) {
    if (file_exists($stateFile)) {
        $content = file_get_contents($stateFile);
        $data = json_decode($content, true);
        if ($data && isset($data['questtitle']) && !empty($data['questtitle'])) {
            return $data['questtitle'];
        }
    }
    return 'No staged quest';
}

$stagedQuestTitle = getStagedQuestTitle($stateFile);

// Get last element of nextlist from state file
function getNextObjective($stateFile) {
    if (file_exists($stateFile)) {
        $content = file_get_contents($stateFile);
        $data = json_decode($content, true);
        if ($data && isset($data['nextlist']) && is_array($data['nextlist']) && !empty($data['nextlist'])) {
            return end($data['nextlist']);
        }
    }
    return null;
}

$nextObjective = getNextObjective($stateFile);


function InvolvedNPCs($stateFile) {
    if (file_exists($stateFile)) {
        $content = file_get_contents($stateFile);
        $data = json_decode($content, true);
        if ($data && isset($data['npclist']) && is_array($data['npclist']) && !empty($data['npclist'])) {
            return $data['npclist'] ?? [];
        }
    } else
        error_log("State file not found for involved NPCs: " . htmlspecialchars($stateFile));

    return null;
}

$involvedNPCs = InvolvedNPCs($stateFile);

// Handle AJAX request for running quests
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_running_quests') {
    header('Content-Type: application/json');
    echo json_encode([
        'quests' => $runningQuests,
        'pendingStep' => getPendingStep($GLOBALS['db']),
        'stagedQuestTitle' => getStagedQuestTitle($stateFile),
        'nextObjective' => getNextObjective($stateFile),
        'involvedNPCs' => InvolvedNPCs($stateFile) ?? []
    ]);
    exit;
}

// AJAX: logs
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_logs') {
    $agentLog = getTailOfFile("{$enginePath}log/log_run_agent.log", $logTailLines);
    $serviceLog = getTailOfFile("{$enginePath}log/service.log", $logTailLines);

    header('Content-Type: application/json');
    echo json_encode([
        'agentLog' => $agentLog,
        'serviceLog' => $serviceLog,
        'combinedLog' => buildCombinedLog($agentLog, $serviceLog),
        'pendingStep' => getPendingStep($GLOBALS['db']),
        'updatedAt' => date('H:i:s'),
        'tailLines' => $logTailLines,
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'download_logs') {
    $agentLogFull = getFileContentsSafe("{$enginePath}log/log_run_agent.log");
    $serviceLogFull = getFileContentsSafe("{$enginePath}log/service.log");
    $downloadBody = implode("\n", [
        "Quest System Server Logs Export",
        "Generated: " . date('Y-m-d H:i:s'),
        "",
        buildCombinedLog($agentLogFull, $serviceLogFull),
        "",
    ]);
    $downloadName = 'quest_system_server_logs_' . date('Ymd_His') . '.txt';

    header('Content-Type: text/plain; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $downloadName . '"');
    header('Content-Length: ' . strlen($downloadBody));
    echo $downloadBody;
    exit;
}

// AJAX: start quest
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'start_quest') {
    $enginePath = escapeshellarg($GLOBALS["ENGINE_PATH"]);
    $cmd = "php {$enginePath}/service/processors/snqe/run_agents.php full > {$enginePath}/log/log_run_agent.log 2>&1 &";
    $output = shell_exec($cmd);
    $output = trim($output);
    
    header('Content-Type: application/json');
    echo json_encode([
        'status'    => 'success',
        'message'   => 'Quest started successfully',
        'output'    => $output,
        'timestamp' => date('Y-m-d H:i:s'),
    ]);
    exit;
}


// AJAX: request end
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'request_end') {
    $enginePath = escapeshellarg($GLOBALS["ENGINE_PATH"]);
    $cmd = "php {$enginePath}/service/processors/snqe/run_agents.php full end> {$enginePath}/log/log_run_agent.log 2>&1 &";
    $output = shell_exec($cmd);
    $output = trim($output);    
    if ($output === null) {
        Logger::error("[SNQE] Failed to start background agent processing");
    } else {
        Logger::info("[SNQE] Background agent processing started successfully");
    }
    
    header('Content-Type: application/json');
    echo json_encode([
        'status'    => 'success',
        'message'   => 'Quest stop requested successfully',
        'output'    => $output,
        'timestamp' => date('Y-m-d H:i:s'),
    ]);
    exit;
}

// AJAX: clean all
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'clean_all') {
    // Execute SNQE manager clean command
    $enginePath = escapeshellarg($GLOBALS["ENGINE_PATH"]);
    $cmd = "php {$enginePath}/service/manager.php snqe clean 2>&1";
    
    try {
        $output = shell_exec($cmd);
        Logger::info("[SNQE] Clean command executed: " . trim($output ?? ""));
    } catch (Exception $e) {
        Logger::error("[SNQE] Clean command failed: " . $e->getMessage());
    }
    
    // Remove state file if it exists
    $stateFile = "{$GLOBALS["ENGINE_PATH"]}/log/snqe_state.json";
    if (file_exists($stateFile)) {
        if (!unlink($stateFile)) {
            Logger::warn("[SNQE] Failed to delete state file: {$stateFile}");
        } else {
            Logger::info("[SNQE] State file deleted successfully");
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode([
        'status'    => 'success',
        'message'   => 'Clear running quests executed successfully',
        'output'    => $output ?? '',
        'timestamp' => date('Y-m-d H:i:s'),
    ]);
    exit;
}

// Form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_form') {
    $userprompt = isset($_POST['userprompt']) ? $_POST['userprompt'] : '';
    $questtitle = isset($_POST['questtitle']) ? $_POST['questtitle'] : '';
    $briefing   = isset($_POST['briefing'])   ? $_POST['briefing']   : '';
    $suggested  = isset($_POST['suggested'])  ? $_POST['suggested']  : '';
    $locations  = isset($_POST['locationlist']) ? json_decode($_POST['locationlist'], true) : [];

    $state = [
        'userprompt'   => $userprompt,
        'briefing'     => $briefing,
        'questtitle'   => $questtitle,
        'suggested'    => $suggested,
        'locationlist' => $locations,
    ];
    file_put_contents($stateFile, json_encode($state, JSON_PRETTY_PRINT));
    chmod($stateFile, 0777);

    header('Content-Type: application/json');
    echo json_encode([
        'status'    => 'success',
        'message'   => 'Form data received successfully',
        'data'      => compact('userprompt', 'questtitle', 'briefing', 'suggested'),
        'timestamp' => date('Y-m-d H:i:s'),
    ]);
    exit;
}

$uiPath = $enginePath . "ui" . DIRECTORY_SEPARATOR;
$viewMode = isset($_GET['view']) ? (string) $_GET['view'] : 'manager';
$isLogsView = ($viewMode === 'logs');
$TITLE = $isLogsView ? "Quest System Server Logs" : "AI Quest Manager V1";

ob_start();
include($uiPath . "tmpl/head.html");
require_once($uiPath . "profile_loader.php");
?>
<link rel="stylesheet" href="<?php echo $webRoot; ?>/ui/css/main.css">
<style>
@font-face {
    font-family: "MagicCards";
    src: url("<?php echo $webRoot; ?>/ui/css/font/MagicCardsNormal.ttf") format("truetype");
}

main {
    padding-top: <?php echo $isEmbed ? '20px' : '80px'; ?>;
    padding-bottom: 40px;
    padding-left: 16px;
    padding-right: 16px;
}

footer { position: fixed; bottom: 0; width: 100%; height: 20px; background: #031633; z-index: 100; }

.snqe-layout {
    display: flex;
    gap: 20px;
    align-items: flex-start;
    flex-wrap: wrap;
}

.snqe-main {
    flex: 1;
    min-width: 360px;
    background: linear-gradient(135deg, rgba(42,42,42,0.95), rgba(34,34,34,0.98));
    border: 1px solid #3a3a3a;
    border-radius: 8px;
    padding: 28px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.3);
}

.snqe-sidebar {
    flex: 0 0 280px;
    background: linear-gradient(135deg, rgba(42,42,42,0.95), rgba(34,34,34,0.98));
    border: 1px solid #3a3a3a;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.3);
}

.snqe-page-title {
    font-family: "MagicCards", serif;
    font-size: 1.8em;
    color: #ffffff;
    text-shadow: 1px 1px 3px rgba(0,0,0,0.6);
    letter-spacing: 1px;
    word-spacing: 8px;
    margin: 0 0 24px 0;
}

.snqe-title-info {
    margin: -6px 0 22px 0;
    padding: 12px 14px;
    border-radius: 8px;
    border: 1px solid rgba(242,124,17,0.35);
    background: linear-gradient(135deg, rgba(242,124,17,0.15), rgba(242,124,17,0.05));
}

.snqe-title-info-text {
    color: #f6d8ba;
    font-size: 0.9em;
    line-height: 1.5;
    margin: 0 0 10px 0;
}

.snqe-title-info-text strong {
    color: #ffd7a8;
}

.snqe-alpha-note {
    margin: 12px 0 0 0;
    color: #ffd7a8;
    font-size: 0.85em;
    line-height: 1.45;
}

.snqe-alpha-note strong {
    color: #fff0d8;
}

.snqe-location-stat {
    display: inline-flex;
    align-items: baseline;
    gap: 8px;
    padding: 8px 12px;
    background: rgba(0,0,0,0.28);
    border: 1px solid rgba(242,124,17,0.25);
    border-radius: 6px;
}

.snqe-location-count {
    color: #fff;
    font-size: 1.25em;
    font-weight: 700;
    line-height: 1;
}

.snqe-location-label {
    color: #cfd9ea;
    font-size: 0.82em;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.snqe-section-title {
    font-family: "MagicCards", serif;
    font-size: 1.1em;
    color: #ffffff;
    margin: 0 0 14px 0;
    display: flex;
    align-items: center;
    gap: 8px;
    letter-spacing: 0.7px;
    word-spacing: 6px;
}

.pulse-dot {
    display: inline-block;
    width: 10px;
    height: 10px;
    background: #10b981;
    border-radius: 50%;
    animation: pulse 2s infinite;
    flex-shrink: 0;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50%       { opacity: 0.4; }
}

.form-group { margin-bottom: 18px; }

.form-group label {
    display: block;
    margin-bottom: 6px;
    color: #cfd9ea;
    font-weight: 600;
    font-size: 0.9em;
}

.form-group textarea,
.form-group input[type="text"] {
    width: 100%;
    padding: 10px 12px;
    background: #1e1e1e;
    border: 1px solid #444;
    border-radius: 6px;
    color: #e0e0e0;
    font-family: 'Courier New', monospace;
    font-size: small;
    resize: vertical;
    transition: border-color 0.2s;
    box-sizing: border-box;
}

.form-group textarea { min-height: 50px; }

.form-group textarea:focus,
.form-group input[type="text"]:focus {
    outline: none;
    border-color: rgb(242,124,17);
    box-shadow: 0 0 0 2px rgba(242,124,17,0.15);
}

.form-group input[readonly] {
    background: #161616;
    color: #999;
    cursor: default;
}

.log-box {
    width: 100%;
    padding: 10px 12px;
    background: #000;
    border: 1px solid #333;
    border-radius: 6px;
    color: #00ff88;
    font-family: 'Courier New', monospace;
    font-size: small;
    overflow-y: auto;
    max-height: 160px;
    white-space: pre-wrap;
    word-wrap: break-word;
    line-height: 1.4;
    resize: vertical;
    box-sizing: border-box;
}

.snqe-log-menu {
    margin: 18px 0 22px 0;
    padding: 16px;
    border: 1px solid rgba(242,124,17,0.22);
    border-radius: 8px;
    background: linear-gradient(135deg, rgba(17,17,17,0.92), rgba(25,25,25,0.98));
}

.snqe-log-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 14px;
    margin-bottom: 12px;
    flex-wrap: wrap;
}

.snqe-log-header .snqe-section-title {
    margin-bottom: 4px;
}

.snqe-log-caption {
    margin: 0;
    color: #b4bdc9;
    font-size: 0.85em;
    line-height: 1.45;
}

.snqe-log-meta {
    display: flex;
    gap: 8px;
    align-items: center;
    flex-wrap: wrap;
    justify-content: flex-end;
}

.snqe-log-badge {
    display: inline-flex;
    align-items: center;
    padding: 5px 10px;
    border-radius: 999px;
    border: 1px solid rgba(96,165,250,0.25);
    background: rgba(15,23,42,0.8);
    color: #93c5fd;
    font-size: 0.78em;
    letter-spacing: 0.2px;
}

.snqe-log-download {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 8px 12px;
    border-radius: 8px;
    border: 1px solid rgba(96,165,250,0.35);
    background: linear-gradient(135deg, rgba(37,99,235,0.82), rgba(29,78,216,0.75));
    color: #ffffff;
    font-size: 0.82em;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s ease;
    text-decoration: none;
    letter-spacing: 0.3px;
}

.snqe-log-download:hover {
    background: linear-gradient(135deg, rgba(59,130,246,0.92), rgba(37,99,235,0.85));
    transform: translateY(-1px);
}

.snqe-log-tabs {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-bottom: 12px;
}

.snqe-log-tab {
    border: 1px solid rgba(242,124,17,0.2);
    border-radius: 999px;
    background: rgba(0,0,0,0.28);
    color: #cfd9ea;
    padding: 8px 12px;
    font-size: 0.84em;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
}

.snqe-log-tab:hover {
    color: rgb(242,124,17);
    border-color: rgba(242,124,17,0.4);
}

.snqe-log-tab.active {
    color: #1b1205;
    background: linear-gradient(135deg, rgba(242,124,17,0.95), rgba(245,158,11,0.9));
    border-color: rgba(245,158,11,0.7);
}

.snqe-log-panel {
    display: none;
}

.snqe-log-panel.active {
    display: block;
}

.snqe-log-panel .log-box {
    min-height: 240px;
    max-height: 360px;
}

.btn-group { display: flex; gap: 10px; margin-top: 20px; flex-wrap: wrap; }

.btn-snqe {
    flex: 1;
    min-width: 160px;
    padding: 10px 18px;
    border: none;
    border-radius: 6px;
    font-weight: 600;
    font-size: 0.95em;
    cursor: pointer;
    transition: all 0.2s ease;
    letter-spacing: 0.3px;
    color: #fff;
}

.btn-snqe-generate {
    background: linear-gradient(135deg, rgba(37,99,235,0.85), rgba(37,99,235,0.65));
    border: 1px solid rgba(138,155,182,0.3);
}
.btn-snqe-generate:hover { background: linear-gradient(135deg, rgba(47,109,245,0.9), rgba(47,109,245,0.75)); transform: translateY(-1px); }

.btn-snqe-create {
    background: linear-gradient(135deg, rgba(16,185,129,0.85), rgba(5,150,105,0.75));
    border: 1px solid rgba(52,211,153,0.3);
}
.btn-snqe-create:hover { background: linear-gradient(135deg, rgba(16,185,129,1), rgba(5,150,105,0.9)); transform: translateY(-1px); }

.btn-snqe-clear {
    background: linear-gradient(135deg, rgba(185,28,28,0.75), rgba(153,27,27,0.65));
    border: 1px solid rgba(239,68,68,0.3);
}
.btn-snqe-clear:hover { background: linear-gradient(135deg, rgba(220,38,38,0.9), rgba(185,28,28,0.8)); transform: translateY(-1px); }

.btn-snqe:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    background: linear-gradient(135deg, rgba(100,100,100,0.6), rgba(80,80,80,0.5));
    border: 1px solid rgba(100,100,100,0.3);
}

.btn-snqe:disabled:hover {
    transform: none;
    background: linear-gradient(135deg, rgba(100,100,100,0.6), rgba(80,80,80,0.5));
}

.loading-msg {
    display: none;
    margin-top: 10px;
    color: rgb(242,124,17);
    font-weight: 600;
    font-size: 0.9em;
}
.loading-msg.active { display: block; }

.quest-item {
    background: rgba(30,30,30,0.8);
    border: 1px solid #3a3a3a;
    border-left: 3px solid rgb(242,124,17);
    border-radius: 6px;
    padding: 12px;
    margin-bottom: 12px;
    transition: border-left-color 0.2s;
}
.quest-item:hover { border-left-color: #f0a040; }

.quest-item-title { color: rgb(242,124,17); font-weight: 600; margin-bottom: 5px; word-break: break-word; }
.quest-item-id    { font-size: 0.78em; color: #666; margin-bottom: 4px; font-family: 'Courier New', monospace; word-break: break-all; }
.quest-item-stage { color: #aaa; font-size: 0.88em; margin-bottom: 5px; }
.quest-item-time  { color: #666; font-size: 0.75em; font-style: italic; }

.no-quests { color: #666; text-align: center; padding: 30px 10px; font-style: italic; }

.staged-quest-container {
    margin-top: 24px;
    padding-top: 20px;
    border-top: 1px solid #3a3a3a;
}

.staged-quest-container h3 {
    font-family: "MagicCards", serif;
    font-size: 1.1em;
    color: #ffffff;
    margin: 0 0 14px 0;
    letter-spacing: 0.7px;
    word-spacing: 6px;
}

.staged-quest-value {
    background: rgba(30,30,30,0.8);
    border: 1px solid #3a3a3a;
    border-left: 3px solid #10b981;
    border-radius: 6px;
    padding: 12px;
    color: #00ff88;
    font-weight: 600;
    word-break: break-word;
}

.pending-step-container {
    margin-top: 24px;
    padding-top: 20px;
    border-top: 1px solid #3a3a3a;
}

.pending-step-container h3 {
    font-family: "MagicCards", serif;
    font-size: 1.1em;
    color: #ffffff;
    margin: 0 0 14px 0;
    letter-spacing: 0.7px;
    word-spacing: 6px;
}

.pending-step-value {
    background: rgba(30,30,30,0.8);
    border: 1px solid #3a3a3a;
    border-left: 3px solid rgb(242,124,17);
    border-radius: 6px;
    padding: 12px;
    color: #cfd9ea;
    font-size: 0.9em;
    word-break: break-word;
}

.next-objective-container {
    margin-top: 24px;
    padding-top: 20px;
    border-top: 1px solid #3a3a3a;
}

.next-objective-container h3 {
    font-family: "MagicCards", serif;
    font-size: 1.1em;
    color: #ffffff;
    margin: 0 0 14px 0;
    letter-spacing: 0.7px;
    word-spacing: 6px;
}

.next-objective-value {
    background: rgba(30,30,30,0.8);
    border: 1px solid #3a3a3a;
    border-left: 3px solid #3b82f6;
    border-radius: 6px;
    padding: 12px;
    color: #60a5fa;
    font-size: 0.9em;
    word-break: break-word;
}

@media (max-width: 900px) {
    .snqe-layout { flex-direction: column; }
    .snqe-sidebar { flex: 1 1 100%; }
}

.small-info {
    margin: 0 0 8px 0;
    color: #666;
    font-size: 0.8em;
    font-style: italic;
    line-height: 1.4;
}
</style>

<?php if (!$isEmbed): ?>
<?php include($uiPath . "tmpl/navbar.php"); ?>
<?php endif; ?>

<main>
    <div class="snqe-layout">
        <div class="snqe-main">
            <div class="snqe-page-title"><?php echo $isLogsView ? 'Quest System Server Logs' : 'AI Quest Manager V1'; ?></div>
            <?php if ($isLogsView): ?>
                <div class="snqe-title-info">
                    <p class="snqe-title-info-text">Live quest-system server output from the runner and service logs. Use this tab to inspect what the server-side quest processor is doing.</p>
                    <p class="snqe-alpha-note"><strong>Source:</strong> `log_run_agent.log` and `service.log`, refreshed automatically every 5 seconds.</p>
                </div>
            <?php else: ?>
                <div class="snqe-title-info">
                    <p class="snqe-title-info-text">Before generating or staging quests, use <strong>"Send All Locations"</strong> in CHIM MCM under <strong>Tools</strong> so the quest system can target valid travel locations.</p>
                    <p class="snqe-alpha-note"><strong>Alpha:</strong> AI Quest Manager is still in alpha and requires OpenRouter to be configured for this to work.</p>
                    <div class="snqe-location-stat">
                        <span class="snqe-location-count"><?php echo intval($locationStats['location_count']); ?></span>
                        <span class="snqe-location-label">Travel To Locations in Database</span>
                    </div>
                </div>
            <?php endif; ?>
            <form id="snqeForm">
                <?php if (!$isLogsView): ?>
                    <div class="btn-group">
                        <button type="button" class="btn-snqe btn-snqe-generate" onclick="generateScenario()">Generate Scenario</button>
                        <button type="button" class="btn-snqe btn-snqe-create" onclick="submitFormData()">Stage Storyline</button>
                        <button type="button" class="btn-snqe btn-snqe-create" id="playQuestBtn" onclick="startQuest()" <?php echo (empty($runningQuests) && $stagedQuestTitle !== 'No staged quest') ? '' : 'disabled'; ?>>Play Quest</button>
                        <button type="button" class="btn-snqe btn-snqe-clear" id="requestEndBtn" onclick="requestEnd()" <?php echo !empty($runningQuests) ? '' : 'disabled'; ?>>Stop Quest</button>
                        <button type="button" class="btn-snqe btn-snqe-clear" onclick="clearAllData()">Clear Data</button>
                        <button type="button" class="btn-snqe btn-snqe-clear" onclick="cleanAll()">Clear Running Quests</button>

                        <a href="https://github.com/abeiro/HerikaServer/discussions/480" target="_blank" style="color:#60a5fa; font-size:0.9em; margin-bottom: 20px; display:inline-block;position:absolute;right:5px;top:-50px">Documentation</a>
                    </div>

                    <div class="loading-msg" id="loading">Generating scenario...</div>
                    <br />
                <?php endif; ?>

                <?php if ($isLogsView): ?>
                    <div class="snqe-log-menu">
                        <div class="snqe-log-header">
                            <div>
                                <div class="snqe-section-title">
                                    <span class="pulse-dot"></span>
                                    Quest System Server Logs
                                </div>
                                <p class="snqe-log-caption">Live quest-system server output from the runner and service logs. Refreshes automatically every 5 seconds.</p>
                            </div>
                            <div class="snqe-log-meta">
                                <button type="button" class="snqe-log-download" onclick="downloadLogs()">Download Logs</button>
                                <span class="snqe-log-badge">Latest <?php echo intval($logTailLines); ?> lines per stream</span>
                                <span class="snqe-log-badge" id="logUpdatedAt">Updated <?php echo date('H:i:s'); ?></span>
                            </div>
                        </div>

                        <div class="snqe-log-tabs" role="tablist" aria-label="Quest system server log sources">
                            <button type="button" class="snqe-log-tab active" data-log-tab="combined">All Server Logs</button>
                            <button type="button" class="snqe-log-tab" data-log-tab="agent">Quest Runner</button>
                            <button type="button" class="snqe-log-tab" data-log-tab="service">Service</button>
                        </div>

                        <div class="snqe-log-panel active" data-log-panel="combined">
                            <div class="log-box" id="combinedLog"><?php echo htmlspecialchars($combinedLog); ?></div>
                        </div>

                        <div class="snqe-log-panel" data-log-panel="agent">
                            <div class="log-box" id="agentLog"><?php echo htmlspecialchars($logRunAgent); ?></div>
                        </div>

                        <div class="snqe-log-panel" data-log-panel="service">
                            <div class="log-box" id="serviceLog"><?php echo htmlspecialchars($serviceLog); ?></div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!$isLogsView): ?>
                    <div class="form-group" id="suggestedGroup" style="<?php echo ($stagedQuestTitle !== 'No staged quest') ? 'display:none;' : ''; ?>">
                        <label for="suggested">User suggestions</label>
                        <p class="small-info">This will affect AI instructions when generating scenario for the first time. E.G. "A quest to retrieve some stolen item" or "action should take place at Silent Moons Camp"</p>
                        <textarea name="suggested" id="suggested" placeholder="Enter suggestions here..." style="height: 50px;"></textarea>
                    </div>

                    <div class="form-group" id="userpromptGroup" style="<?php echo ($stagedQuestTitle !== 'No staged quest') ? 'display:none;' : ''; ?>">
                        <label for="userprompt">AI instructions</label>
                        <p class="small-info">These are the instructions sent to AI. You can add here some details. E.G. (spawn several spiders at Broken Fang cave)</p>
                        <textarea name="userprompt" id="userprompt" style="height: 100px;"></textarea>
                    </div>

                    <div class="form-group">
                        <label for="questtitle">Quest Title</label>
                        <input type="text" name="questtitle" id="questtitle" placeholder="Quest title will appear here..."  readonly />
                    </div>

                    <div class="form-group">
                        <label for="briefing">Quest Briefing</label>
                        <input type="text" name="briefing" id="briefing" placeholder="Briefing will appear here..." readonly />
                    </div>

                    <div style="display:none;">
                        <select name="locationlist" id="locationlist" multiple></select>
                    </div>

                    <div>
                        <label>NPCs involved</label>
                        <div class="log-box" id="involvedNPCs" style="color:#60a5fa;"><?php echo htmlspecialchars(implode(", ", $involvedNPCs??[])); ?>
                        </div>
                    </div>
                <?php endif; ?>

            </form>
        </div>

        <?php if (!$isLogsView): ?>
        <div class="snqe-sidebar">
            <div class="snqe-section-title">
                <span class="pulse-dot"></span>
                Running Quests
            </div>
            <div id="runningQuestsList">
                <?php if (empty($runningQuests)): ?>
                    <div class="no-quests">No running quests</div>
                <?php else: ?>
                    <?php foreach ($runningQuests as $quest): ?>
                        <div class="quest-item">
                            <div class="quest-item-title"><?php echo htmlspecialchars($quest['title'] ?? 'Untitled Quest'); ?></div>
                            <div class="quest-item-id">ID: <?php echo htmlspecialchars(substr($quest['quest_id'], 0, 8)); ?>...</div>
                            <div class="quest-item-stage">Stage: <?php echo htmlspecialchars($quest['stage'] ?? 'N/A'); ?></div>
                            <div class="quest-item-time">Updated: <?php echo date('H:i:s', strtotime($quest['updated_at'])); ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <div class="staged-quest-container">
                <h3>📝 Staged StoryLine</h3>
                <div class="staged-quest-value" id="stagedQuestTitle">
                    <?php echo htmlspecialchars($stagedQuestTitle); ?>
                </div>
            </div>
            
            <div class="pending-step-container" id="pendingStepContainer" style="<?php echo empty($runningQuests) ? 'display:none;' : ''; ?>">
                <h3>⏳ Current Pending Step</h3>
                <div class="pending-step-value" id="pendingStepValue">
                    <?php echo htmlspecialchars($pendingStep); ?>
                </div>
            </div>
            
            <div class="next-objective-container" id="nextObjectiveContainer" style="<?php 
                $showNextObjective = !empty($stagedQuestTitle) && $stagedQuestTitle !== 'No staged quest' 
                    && empty($runningQuests) 
                    && ($pendingStep === 'No pending step' || empty($pendingStep))
                    && !empty($nextObjective);
                echo $showNextObjective ? '' : 'display:none;'; 
            ?>">
                <h3>🎯 Next Objective</h3>
                <div class="next-objective-value" id="nextObjectiveValue">
                    <?php echo htmlspecialchars($nextObjective ?? 'No objective'); ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</main>

<script>
const SNQE_SELF = '<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>';
const LOG_REFRESH_MS = 5000;

function escapeHtml(text) {
    const map = { '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#039;' };
    return String(text).replace(/[&<>"']/g, m => map[m]);
}

function formatTime(ts) {
    return new Date(ts).toLocaleTimeString('en-US', { hour12: false });
}

function activateLogTab(tabName) {
    document.querySelectorAll('.snqe-log-tab').forEach((tab) => {
        tab.classList.toggle('active', tab.dataset.logTab === tabName);
    });
    document.querySelectorAll('.snqe-log-panel').forEach((panel) => {
        panel.classList.toggle('active', panel.dataset.logPanel === tabName);
    });
}

function shouldStickToBottom(element) {
    if (!element) {
        return false;
    }
    return (element.scrollHeight - element.scrollTop - element.clientHeight) < 24;
}

function updateLogElement(element, content) {
    if (!element) {
        return;
    }
    const stickToBottom = shouldStickToBottom(element) || !element.textContent;
    element.textContent = content || '';
    if (stickToBottom) {
        element.scrollTop = element.scrollHeight;
    }
}

function buildCombinedLog(agentLog, serviceLog) {
    const sections = [
        '===== Quest Runner (log_run_agent.log) =====',
        agentLog || 'No quest runner log data available.',
        '',
        '===== Service (service.log) =====',
        serviceLog || 'No service log data available.'
    ];
    return sections.join('\n');
}

function refreshRunningQuests() {
    if (<?php echo $isLogsView ? 'true' : 'false'; ?>) {
        return;
    }
    fetch(SNQE_SELF + '?action=get_running_quests')
        .then(r => r.json())
        .then(data => {
            const el = document.getElementById('runningQuestsList');
            const pendingStepContainer = document.getElementById('pendingStepContainer');
            const nextObjectiveContainer = document.getElementById('nextObjectiveContainer');
            const nextObjectiveValue = document.getElementById('nextObjectiveValue');
            
            const hasRunningQuests = data.quests && data.quests.length > 0;
            const hasStagedQuest = data.stagedQuestTitle && data.stagedQuestTitle !== 'No staged quest';
            const hasPendingStep = data.pendingStep && data.pendingStep !== 'No pending step';
            const hasNextObjective = data.nextObjective && data.nextObjective !== '';
            
            if (!hasRunningQuests) {
                el.innerHTML = '<div class="no-quests">No running quests</div>';
                if (pendingStepContainer) pendingStepContainer.style.display = 'none';
            } else {
                el.innerHTML = data.quests.map(q => `
                    <div class="quest-item">
                        <div class="quest-item-title">${escapeHtml(q.title || 'Untitled Quest')}</div>
                        <div class="quest-item-id">ID: ${escapeHtml(q.quest_id.substring(0, 8))}...</div>
                        <div class="quest-item-stage">Stage: ${escapeHtml(q.stage || 'N/A')}</div>
                        <div class="quest-item-time">Updated: ${formatTime(q.updated_at)}</div>
                    </div>`).join('');
                if (pendingStepContainer) pendingStepContainer.style.display = 'block';
            }
            
            // Update pending step value
            const ps = document.getElementById('pendingStepValue');
            if (data.pendingStep && ps) { ps.textContent = data.pendingStep; }
            
            // Show next objective only if: staged quest exists, no running quests, no pending step, and next objective exists
            if (hasStagedQuest && !hasRunningQuests && !hasPendingStep && hasNextObjective) {
                if (nextObjectiveValue) nextObjectiveValue.textContent = data.nextObjective;
                if (nextObjectiveContainer) nextObjectiveContainer.style.display = 'block';
            } else {
                if (nextObjectiveContainer) nextObjectiveContainer.style.display = 'none';
            }
            
            // Update staged quest title
            if (data.stagedQuestTitle) {
                document.getElementById('stagedQuestTitle').textContent = data.stagedQuestTitle;
                
                // Hide/show user suggestions and AI instructions based on staged quest
                const suggestedGroup = document.getElementById('suggestedGroup');
                const userpromptGroup = document.getElementById('userpromptGroup');
                if (data.stagedQuestTitle && data.stagedQuestTitle !== 'No staged quest') {
                    if (suggestedGroup) suggestedGroup.style.display = 'none';
                    if (userpromptGroup) userpromptGroup.style.display = 'none';
                } else {
                    if (suggestedGroup) suggestedGroup.style.display = 'block';
                    if (userpromptGroup) userpromptGroup.style.display = 'block';
                }
                
                // Enable/disable Play Quest and Stop Quest buttons
                const playQuestBtn = document.getElementById('playQuestBtn');
                const requestEndBtn = document.getElementById('requestEndBtn');
                const canPlayQuest = hasStagedQuest && !hasRunningQuests;
                const canStopQuest = hasRunningQuests;
                
                if (playQuestBtn) playQuestBtn.disabled = !canPlayQuest;
                if (requestEndBtn) requestEndBtn.disabled = !canStopQuest;
            }
        })
        .catch(() => {});
}

function refreshLogs() {
    fetch(SNQE_SELF + '?action=get_logs')
        .then(r => r.json())
        .then(data => {
            const al = document.getElementById('agentLog');
            const sl = document.getElementById('serviceLog');
            const cl = document.getElementById('combinedLog');
            const ps = document.getElementById('pendingStepValue');
            const updatedAt = document.getElementById('logUpdatedAt');
            updateLogElement(al, data.agentLog || '');
            updateLogElement(sl, data.serviceLog || '');
            updateLogElement(cl, data.combinedLog || buildCombinedLog(data.agentLog || '', data.serviceLog || ''));
            if (data.pendingStep && ps) { ps.textContent = data.pendingStep; }
            if (updatedAt) {
                updatedAt.textContent = 'Updated ' + (data.updatedAt || '--:--:--');
            }
        })
        .catch(() => {});
}

function downloadLogs() {
    window.location.href = SNQE_SELF + '?action=download_logs';
}

function updateSelectBox(elementId, items) {
    const select = document.getElementById(elementId);
    if (!select) {
        return;
    }
    const existing = Array.from(select.options).map(o => o.textContent);
    items.forEach((item, i) => {
        if (!existing.includes(item)) {
            const opt = document.createElement('option');
            opt.value = existing.length + i;
            opt.textContent = item;
            opt.selected = true;
            select.appendChild(opt);
        }
    });
}

function generateScenario() {
    if (<?php echo $isLogsView ? 'true' : 'false'; ?>) {
        return;
    }
    const userprompt = document.getElementById('userprompt').value;
    const questTitle = document.getElementById('questtitle').value;
    const briefing   = document.getElementById('briefing').value;
    const suggested  = document.getElementById('suggested').value;
    const loadingEl  = document.getElementById('loading');

    loadingEl.textContent = 'Generating scenario...';
    loadingEl.classList.add('active');

    fetch('cmd/agent0.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            prompt: userprompt, locationlist: [], npclist: [],
            spawneditemslist: [], journallist: [], rumorlist: [], nextlist: [],
            questtitle: questTitle, briefing, suggested
        })
    })
    .then(r => r.json())
    .then(data => {
        document.getElementById('userprompt').value = data.response || '';
        if (data.briefing)   document.getElementById('briefing').value   = data.briefing;
        if (data.questtitle) document.getElementById('questtitle').value = data.questtitle;
        if (data.locations)  updateSelectBox('locationlist', data.locations);
        loadingEl.classList.remove('active');
    })
    .catch(() => loadingEl.classList.remove('active'));
}

function submitFormData() {
    if (<?php echo $isLogsView ? 'true' : 'false'; ?>) {
        return;
    }
    const userprompt  = document.getElementById('userprompt').value;
    const questTitle  = document.getElementById('questtitle').value;
    const briefing    = document.getElementById('briefing').value;
    const suggested   = document.getElementById('suggested').value;
    const locations   = Array.from(document.getElementById('locationlist').options).map(o => o.textContent);
    const loadingEl   = document.getElementById('loading');

    loadingEl.textContent = 'Submitting form data...';
    loadingEl.classList.add('active');

    const fd = new FormData();
    fd.append('action', 'submit_form');
    fd.append('userprompt', userprompt);
    fd.append('questtitle', questTitle);
    fd.append('briefing', briefing);
    fd.append('suggested', suggested);
    fd.append('locationlist', JSON.stringify(locations));

    fetch(SNQE_SELF, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
                localStorage.setItem('snqe_questtitle', questTitle);
                localStorage.setItem('snqe_briefing', briefing);
                try { alert('Submitted!\n\nQuest: ' + data.data.questtitle + '\nBriefing: ' + data.data.briefing + '\n' + data.timestamp); } catch(_) {}
                // Refresh the staged quest title in the sidebar
                refreshRunningQuests();
            } else {
                try { alert('Error: ' + data.message); } catch(_) {}
            }
            loadingEl.classList.remove('active');
            loadingEl.textContent = 'Generating scenario...';
        })
        .catch(() => { loadingEl.classList.remove('active'); });
}

function clearAllData() {
    if (<?php echo $isLogsView ? 'true' : 'false'; ?>) {
        return;
    }
    if (!confirm('Clear all data?')) return;
    localStorage.removeItem('snqe_questtitle');
    localStorage.removeItem('snqe_briefing');
    document.getElementById('userprompt').value = '';
    document.getElementById('questtitle').value = '';
    document.getElementById('briefing').value   = '';
    document.getElementById('suggested').value  = '';
    document.getElementById('involvedNPCs').textContent = '';
}

function startQuest() {
    if (<?php echo $isLogsView ? 'true' : 'false'; ?>) {
        return;
    }
    if (!confirm('Start the quest now?')) return;
    
    const loadingEl = document.getElementById('loading');
    loadingEl.textContent = 'Starting quest...';
    loadingEl.classList.add('active');
    
    const fd = new FormData();
    fd.append('action', 'start_quest');
    
    fetch(SNQE_SELF, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
                try { alert('Quest started!\n' + data.timestamp); } catch(_) {}
                refreshRunningQuests();
                refreshLogs();
            } else {
                try { alert('Error: ' + data.message); } catch(_) {}
            }
            loadingEl.classList.remove('active');
            loadingEl.textContent = 'Generating scenario...';
        })
        .catch(() => { 
            loadingEl.classList.remove('active');
            try { alert('Failed to start quest'); } catch(_) {}
        });
}

function requestEnd() {
    if (<?php echo $isLogsView ? 'true' : 'false'; ?>) {
        return;
    }
    if (!confirm('Stop quest now?')) return;
    
    const loadingEl = document.getElementById('loading');
    loadingEl.textContent = 'Stopping quest...';
    loadingEl.classList.add('active');
    
    const fd = new FormData();
    fd.append('action', 'request_end');
    
    fetch(SNQE_SELF, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
                try { alert('Quest stop requested!\n' + data.timestamp); } catch(_) {}
                refreshRunningQuests();
                refreshLogs();
            } else {
                try { alert('Error: ' + data.message); } catch(_) {}
            }
            loadingEl.classList.remove('active');
            loadingEl.textContent = 'Generating scenario...';
        })
        .catch(() => { 
            loadingEl.classList.remove('active');
            try { alert('Failed to stop quest'); } catch(_) {}
        });
}

function cleanAll() {
    if (<?php echo $isLogsView ? 'true' : 'false'; ?>) {
        return;
    }
    if (!confirm('Clear running quests? This will remove all running quests and state files.')) return;
    
    const loadingEl = document.getElementById('loading');
    loadingEl.textContent = 'Clearing running quests...';
    loadingEl.classList.add('active');
    
    const fd = new FormData();
    fd.append('action', 'clean_all');
    
    fetch(SNQE_SELF, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
                try { alert('Clear running quests executed successfully!\n' + data.timestamp); } catch(_) {}
                refreshRunningQuests();
                refreshLogs();
            } else {
                try { alert('Error: ' + data.message); } catch(_) {}
            }
            loadingEl.classList.remove('active');
            loadingEl.textContent = 'Generating scenario...';
        })
        .catch(() => { 
            loadingEl.classList.remove('active');
            try { alert('Failed to clear running quests'); } catch(_) {}
        });
}

window.addEventListener('DOMContentLoaded', function() {
    if (!<?php echo $isLogsView ? 'true' : 'false'; ?>) {
        const t = localStorage.getItem('snqe_questtitle');
        const b = localStorage.getItem('snqe_briefing');
        if (t) document.getElementById('questtitle').value = t;
        if (b) document.getElementById('briefing').value   = b;
    }

    document.querySelectorAll('.snqe-log-tab').forEach((tab) => {
        tab.addEventListener('click', function() {
            activateLogTab(this.dataset.logTab);
        });
    });

    refreshLogs();
    activateLogTab('combined');
    if (!<?php echo $isLogsView ? 'true' : 'false'; ?>) {
        refreshRunningQuests();
        setInterval(refreshRunningQuests, 5000);
    }
    setInterval(refreshLogs, LOG_REFRESH_MS);
});
</script>

<?php
include($uiPath . "tmpl/footer.html");

$buffer = ob_get_contents();
ob_end_clean();
$buffer = preg_replace('/(<title>)(.*?)(<\/title>)/i', '$1' . $TITLE . '$3', $buffer);
echo $buffer;
?>
