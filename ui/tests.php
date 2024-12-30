<?php 
session_start();
?>
<!DOCTYPE html>
<html>
<head>
    <title>CHIM AI/LLM Test</title>
    <link rel="icon" type="image/x-icon" href="images/favicon.ico">
    <style>
        /* Updated CSS for Dark Grey Background Theme */
        body {
            font-family: Arial, sans-serif;
            background-color: #2c2c2c; /* Dark grey background */
            color: #f8f9fa; /* Light grey text for readability */
            padding: 20px;
        }

        h1, h2, .header {
            color: #ffffff; /* White color for headings */
        }

        .status {
            margin-bottom: 15px;
            background-color: #3a3a3a; /* Slightly lighter grey for backgrounds */
            padding: 10px;
            border-radius: 5px;
            border: 1px solid #555555; /* Darker border for contrast */
        }

        .status .label {
            font-weight: bold;
            color: #f8f9fa; /* Ensure labels are readable */
        }

        .status .ok {
            color: #28a745; /* Bootstrap success color */
        }

        .status .error {
            color: #dc3545; /* Bootstrap danger color */
        }

        pre {
            background-color: #3a3a3a; /* Dark background for code blocks */
            padding: 15px;
            border: 1px solid #555555;
            overflow: auto;
            color: #f8f9fa; /* Light text color */
            border-radius: 5px;
        }

        .response {
            font-weight: bold;
            color: #f8f9fa; /* Light text color */
            background-color: #3a3a3a;
            padding: 15px;
            border-radius: 5px;
            border: 1px solid #555555;
        }

        .section {
            margin-bottom: 30px;
        }

        .header {
            font-size: 24px;
            margin-bottom: 20px;
        }

        .divider {
            border-bottom: 1px solid #555555; /* Darker divider */
            margin: 20px 0;
        }

        .message {
            background-color: #444444;
            padding: 10px;
            border-radius: 5px;
            border: 1px solid #555555;
            max-width: 800px;
            margin-bottom: 20px;
            color: #f8f9fa;
        }

        .message p {
            margin: 0;
        }

        .button {
            padding: 8px 16px;
            margin-top: 10px;
            cursor: pointer;
            background-color: #007bff;
            border: none;
            color: white;
            border-radius: 5px;
            font-size: 18px;
            font-weight: bold;
            transition: background-color 0.3s ease;
        }

        .button:hover {
            background-color: #0056b3;
        }
    </style>
</head>
<body>

<div class="header">CHIM AI/LLM Test</div>

<?php

$enginePath = dirname(__FILE__) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR;
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo '<div class="section">';
echo '<div class="status"><span class="label">Checking <code>conf.php</code>... </span>';

if (!file_exists($enginePath . "conf" . DIRECTORY_SEPARATOR . "conf.php")) {
    echo '<span class="error">Not found</span></div>';
} else {
    echo '<span class="ok">Found</span></div>';
}

echo '</div>'; // End of section

echo '<div class="section">';
echo '<div class="status"><span class="label">Initializing... </span>';

require_once($enginePath . "conf" . DIRECTORY_SEPARATOR . "conf.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "model_dynmodel.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "{$GLOBALS['DBDRIVER']}.class.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "data_functions.php");

if (isset($_SESSION["PROFILE"])) {
    $overrides = [
        "BOOK_EVENT_ALWAYS_NARRATOR" => $GLOBALS["BOOK_EVENT_ALWAYS_NARRATOR"],
        "MINIME_T5" => $GLOBALS["MINIME_T5"],
        "STTFUNCTION" => $GLOBALS["STTFUNCTION"]
    ];

    require_once($_SESSION["PROFILE"]);

    foreach ($overrides as $key => $value) {
        $GLOBALS[$key] = $overrides[$key];
    }
} else {
    $GLOBALS["USING_DEFAULT_PROFILE"] = true;
}

$GLOBALS["active_profile"] = md5($GLOBALS["HERIKA_NAME"]);
$GLOBALS["CURRENT_CONNECTOR"] = DMgetCurrentModel();
$FEATURES["MEMORY_EMBEDDING"]["ENABLED"] = false;

echo '<span class="ok">Done</span></div>';
echo '</div>'; // End of section

echo '<div class="section">';
echo '<div class="status"><span class="label">Opening database connection... </span>';

$db = new sql();
if (!$db) {
    echo '<span class="error">Failed</span></div>';
} else {
    echo '<span class="ok">Connected</span></div>';
}

echo '</div>'; // End of section

echo '<div class="section">';
echo '<div class="status"><span class="label">Processing request...</span></div>';

echo '<pre>';

$FUNCTIONS_ARE_ENABLED = true;
if ($FUNCTIONS_ARE_ENABLED) {
    $GLOBALS["TEMPLATE_DIALOG"] = "";
    $FUNCTION_PARM_MOVETO = [$GLOBALS["PLAYER_NAME"]];
    $FUNCTION_PARM_INSPECT = [$GLOBALS["PLAYER_NAME"]];

    require_once(__DIR__ . DIRECTORY_SEPARATOR . "../prompts" . DIRECTORY_SEPARATOR . "command_prompt.php");
    require_once(__DIR__ . DIRECTORY_SEPARATOR . "../functions" . DIRECTORY_SEPARATOR . "functions.php");
}

$gameRequest = ["inputtext"];

if (!isset($GLOBALS["CURRENT_CONNECTOR"]) || !file_exists($enginePath . "connector" . DIRECTORY_SEPARATOR . "{$GLOBALS['CURRENT_CONNECTOR']}.php")) {
    die("{$GLOBALS['HERIKA_NAME']}|AASPGQuestDialogue2Topic1B1Topic|I'm mindless. Choose a LLM model and connector." . PHP_EOL);
} else {
    require($enginePath . "connector" . DIRECTORY_SEPARATOR . "{$GLOBALS['CURRENT_CONNECTOR']}.php");

    $head = [
        ['role' => 'system', 'content' => strtr($GLOBALS["PROMPT_HEAD"] . $GLOBALS["HERIKA_PERS"], ["#PLAYER_NAME#" => $GLOBALS["PLAYER_NAME"]])]
    ];
    $prompt = [
        ['role' => 'user', 'content' => "Hey, {$GLOBALS['HERIKA_NAME']}, attack that monster!!"]
    ];
    $contextData = array_merge($head, $prompt);

    $connectionHandler = new connector();
    $connectionHandler->open($contextData, []);

    $buffer = "";
    $totalBuffer = "";
    $breakFlag = false;

    while (true) {
        if ($breakFlag) {
            break;
        }

        $buffer .= $connectionHandler->process();
        $totalBuffer .= $buffer;

        if ($connectionHandler->isDone()) {
            $breakFlag = true;
        }
    }

    $connectionHandler->close();

    $actions = $connectionHandler->processActions();
    if (is_array($actions) && count($actions) > 0) {
        $GLOBALS["DEBUG_DATA"]["response"][] = $actions;
        echo implode("\r\n", $actions);
    }

    print_r($GLOBALS["DEBUG_DATA"]);
    if (isset($GLOBALS["ALREADY_SENT_BUFFER"])) {
        print_r($GLOBALS["ALREADY_SENT_BUFFER"]);
    }
}

echo '</pre>';
echo '</div>'; // End of section

echo '<div class="section">';
echo '<div class="divider"></div>';
echo '<div class="status">
        <span class="label" style="font-weight: bold;">LLM Response:</span>
        <div class="response">' . nl2br(htmlspecialchars($buffer)) . '</div>
    </div>';
echo '<br>';
echo '<div class="status">
        <span class="label" style="font-weight: bold; color: yellow; background-color: black; padding: 5px; display: inline-block;">
            TROUBLESHOOTING FIXES
        </span>
        <ul class="error-list" style="margin-top: 15px; list-style-type: none; padding-left: 0;">
            <li style="margin-bottom: 20px;">
                <strong>401 = Unauthorized</strong>
                <ul class="subpoints" style="margin-left: 20px; list-style-type: circle;">
                    <li>Check your API key is correct</li>
                    <li>Make sure you have credits on your account</li>
                </ul>
            </li>
            <li style="margin-bottom: 20px;">
                <strong>402 = Payment Required</strong>
                <ul class="subpoints" style="margin-left: 20px; list-style-type: circle;">
                    <li>Make sure you have credits on your account</li>
                </ul>
            </li>
            <li style="margin-bottom: 20px;">
                <strong>403 = Forbidden</strong>
                <ul class="subpoints" style="margin-left: 20px; list-style-type: circle;">
                    <li>Your prompt may have been flagged for content moderation</li>
                </ul>
            </li>
            <li style="margin-bottom: 20px;">
                <strong>404 = Not Found</strong>
                <ul class="subpoints" style="margin-left: 20px; list-style-type: circle;">
                    <li>Your connector URL has been changed or is incorrect</li>
                </ul>
            </li>
            <li style="margin-bottom: 20px;">
                <strong>500 = Internal Server Error</strong>
                <ul class="subpoints" style="margin-left: 20px; list-style-type: circle;">
                    <li>The server is experiencing technical difficulties</li>
                </ul>
            </li>
            <li style="margin-bottom: 20px;">
                <strong>LLM Response is Empty</strong>
                <ul class="subpoints" style="margin-left: 20px; list-style-type: circle;">
                    <li>You need to put credits in your account</li>
                </ul>
            </li>
            <li style="margin-bottom: 20px;">
                <strong>If you get a response but it fails ingame (array) </strong>
                <ul class="subpoints" style="margin-left: 20px; list-style-type: circle;">
                    <li>Check Server Plugins - Request Logs, you most likely have a token credit limit on your API.</li>
                </ul>
            </li>
        </ul>
    </div>';
echo '</div>'; // End of section

?>
</body>
</html>
