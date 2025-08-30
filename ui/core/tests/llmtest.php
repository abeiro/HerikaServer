<?php
// Core LLM Connector Test (DB-driven)

$enginePath = __DIR__ . "/../../../";

require_once($enginePath . "conf" . DIRECTORY_SEPARATOR . "conf.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "model_dynmodel.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "{$GLOBALS["DBDRIVER"]}.class.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "data_functions.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "logger.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "core" . DIRECTORY_SEPARATOR . "llm_connector.class.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "core" . DIRECTORY_SEPARATOR . "api_badge.class.php");

// Minimal page; no navbar
require_once(__DIR__ . DIRECTORY_SEPARATOR . "../../profile_loader.php");

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>LLM Connector Test</title>
    <style>
        body { background:#0e1624; color:#e8eef9; font-family: Arial, Helvetica, sans-serif; margin:0; padding:18px; }
        .test-wrap { max-width: 1100px; margin: 0 auto; }
        .panel { background:#1a2233; border-radius:10px; padding:14px 16px; margin: 12px 0; border:1px solid rgba(138,155,182,0.35); box-shadow: 0 2px 10px rgba(0,0,0,0.25); }
        .status .label { font-weight:bold; color:#fff; }
        .ok { color:#6fdc8c; }
        .error { color:#ff6b6b; }
        pre { white-space: pre-wrap; color:#cfd8e3; background:#0f1726; padding:10px; border-radius:8px; border:1px solid rgba(138,155,182,0.25); }
        h1 { margin: 0 0 10px; font-size: 20px; color:#ffb862; }
    </style>
</head>
<body>
<div class="test-wrap">
    <h1>🔧 LLM Connector Test</h1>
    <?php
        ini_set('display_errors', 1);
        error_reporting(E_ALL);

        // Resolve connector id from multiple sources for robustness
        $connectorId = isset($_GET['connector_id']) ? intval($_GET['connector_id']) : 0;
        if ($connectorId <= 0 && isset($_GET['edit'])) {
            $connectorId = intval($_GET['edit']);
        }
        if ($connectorId <= 0 && !empty($_SERVER['HTTP_REFERER'])) {
            $ref = parse_url($_SERVER['HTTP_REFERER']);
            if (!empty($ref['query'])) {
                parse_str($ref['query'], $refQ);
                if (!empty($refQ['connector_id'])) $connectorId = intval($refQ['connector_id']);
                if ($connectorId <= 0 && !empty($refQ['edit'])) $connectorId = intval($refQ['edit']);
            }
        }
        if ($connectorId <= 0) {
            echo '<div class="panel"><div class="status"><span class="label">Connector:</span> <span class="error">Missing or invalid connector_id</span></div></div>';
            echo '</div></body></html>';
            exit;
        }
        echo '<div class="panel"><div class="status"><span class="label">Connector:</span> <span class="ok">#'.htmlspecialchars($connectorId).'</span></div></div>';

        echo '<div class="panel"><div class="status"><span class="label">Opening database connection... </span>';
        $db = new sql();
        if (!$db) {
            echo '<span class="error">Failed</span></div></div>'; // stop early
        } else {
            echo '<span class="ok">Connected</span></div></div>';
        }

        $buffer = '';
        $endTimeTrans = 0.0;
        if ($connectorId > 0 && $db) {
            echo '<div class="panel"><div class="status"><span class="label">Preparing test...</span></div>';
            $llm = new LLMConnector();
            $conn = $llm->getById($connectorId);
            if (!$conn) {
                echo '<div class="status"><span class="error">Connector not found.</span></div></div>';
            } else {
                // Load connector globals from DB row and set driver
                $llm->setOldGlobals($conn);
                $driver = $conn['driver'] ?? '';
                if (!$driver) {
                    echo '<div class="status"><span class="error">Connector has no driver set.</span></div></div>';
                } else {
                    echo '<div class="status"><span class="label">Driver:</span> <span class="ok">'.htmlspecialchars($driver).'</span></div>';
                    // Build a small test context
                    $head = [
                        ['role' => 'system', 'content' => strtr($GLOBALS["PROMPT_HEAD"].($GLOBALS["HERIKA_PERS"] ?? ''), ["#PLAYER_NAME#" => $GLOBALS["PLAYER_NAME"] ?? 'Dragonborn'])]
                    ];
                    $prompt = [
                        ['role' => 'user', 'content' => "Hey, ".($GLOBALS['HERIKA_NAME'] ?? 'Herika').", say hello to the player!"]
                    ];
                    $contextData = array_merge($head, $prompt);

                    // Match tests.php environment to avoid warnings
                    $GLOBALS["FEATURES"]["MEMORY_EMBEDDING"]["ENABLED"] = false;
                    $GLOBALS["TEMPLATE_DIALOG"] = "";
                    $FUNCTIONS_ARE_ENABLED = true;
                    $FUNCTION_PARM_MOVETO = [$GLOBALS["PLAYER_NAME"] ?? 'Player'];
                    $FUNCTION_PARM_INSPECT = [$GLOBALS["PLAYER_NAME"] ?? 'Player', 'monster'];

                    require_once($enginePath . "prompts" . DIRECTORY_SEPARATOR . "command_prompt.php");
                    require_once($enginePath . "functions" . DIRECTORY_SEPARATOR . "functions.php");

                    // Execute
                    require_once($enginePath . "connector" . DIRECTORY_SEPARATOR . $driver . ".php");
                    $handler = new $driver();
                    $start = microtime(true);
                    $handler->open($contextData, []);
                    $accum = '';
                    $done = false;
                    while (!$done) {
                        $chunk = $handler->process();
                        if ($chunk === -1) { $done = true; break; }
                        $accum .= $chunk;
                        if ($handler->isDone()) $done = true;
                    }
                    $buffer = $handler->close();
                    $endTimeTrans = microtime(true) - $start;

                    echo '<div class="status"><span class="label">Status:</span> <span class="ok">Completed</span></div>';
                    echo '</div>'; // panel

                    echo '<div class="panel">';
                    echo '<div class="status"><span class="label">LLM Response:</span></div>';
                    echo '<div class="response" style="font-size: 1.0em; color: #ffffff;">' . nl2br(htmlspecialchars($buffer)) . '</div>';
                    // Styled timing similar to tests.php
                    $perf = 'TOO CHIMMING SLOW'; $color = '#dc3545';
                    if ($endTimeTrans < 2) { $perf='FAST!'; $color='#28a745'; }
                    else if ($endTimeTrans < 5) { $perf='GOOD'; $color='#007bff'; }
                    else if ($endTimeTrans < 10) { $perf='NORMAL'; $color='#ffc107'; }
                    else if ($endTimeTrans < 30) { $perf='SLOW'; $color='#fd7e14'; }
                    echo '<pre><b>Response time:</b> ' . number_format($endTimeTrans, 3) . ' secs. ';
                    echo '<span style="color: '.$color.'; font-weight:bold; font-size:1.1em;">'.$perf.'</span></pre>';
                    echo '</div>';

                    // Request sent to LLM (from connector DEBUG_DATA)
                    echo '<div class="panel">';
                    echo '<div class="status"><span class="label">Request Payload:</span></div>';
                    if (!empty($GLOBALS['DEBUG_DATA']['full'])) {
                        $reqJson = json_encode($GLOBALS['DEBUG_DATA']['full'], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
                        echo '<pre>'.htmlspecialchars($reqJson).'</pre>';
                    } else {
                        echo '<pre>No request payload captured.</pre>';
                    }
                    echo '</div>';

                    // Raw/Debug info (e.g., parsed RAW content)
                    echo '<div class="panel">';
                    echo '<div class="status"><span class="label">Debug (RAW/Actions):</span></div>';
                    if (!empty($GLOBALS['DEBUG_DATA']['RAW'])) {
                        $rawShow = is_string($GLOBALS['DEBUG_DATA']['RAW']) ? $GLOBALS['DEBUG_DATA']['RAW'] : json_encode($GLOBALS['DEBUG_DATA']['RAW'], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
                        echo '<pre>'.htmlspecialchars($rawShow).'</pre>';
                    } else {
                        echo '<pre>No RAW debug available.</pre>';
                    }
                    echo '</div>';

                    // Any accumulated buffers/errors similar to tests.php
                    if (!empty($GLOBALS['ALREADY_SENT_BUFFER'])) {
                        echo '<div class="panel">';
                        echo '<div class="status"><span class="label">Internal Buffers:</span></div>';
                        $bufDump = is_string($GLOBALS['ALREADY_SENT_BUFFER']) ? $GLOBALS['ALREADY_SENT_BUFFER'] : print_r($GLOBALS['ALREADY_SENT_BUFFER'], true);
                        echo '<pre>'.htmlspecialchars($bufDump).'</pre>';
                        echo '</div>';
                    }
                }
            }
        }
        ?>
</div>
</body>
</html>


