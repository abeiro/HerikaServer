<?php
session_start();

$startTime = microtime(true);

$localPath = dirname(__FILE__) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR;
$enginePath = $localPath;

require_once($enginePath . "conf" . DIRECTORY_SEPARATOR . "conf.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "$DBDRIVER.class.php");
require_once($enginePath . "conf" . DIRECTORY_SEPARATOR . "conf.php"); // API KEY must be there
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "model_dynmodel.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "{$GLOBALS['DBDRIVER']}.class.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "data_functions.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "chat_helper_functions.php");

requireFilesRecursively($enginePath . "ext" . DIRECTORY_SEPARATOR, "globals.php");

if (isset($_SESSION["PROFILE"])) {
    require_once($_SESSION["PROFILE"]);
} else {
    $_SESSION["PROFILE"] = "$configFilepath/conf.php";
}

error_reporting(E_ALL);

$testString = "In Skyrim's land of snow and ice, Where dragons soar and souls entwine, Heroes rise, their fate unveiled, As ancient tales, the land does bind.";

$db = new sql();

require_once($enginePath . "prompt.includes.php");


$GLOBALS["AVOID_TTS_CACHE"] = true;

$DEBUG_DATA = [];

$soundFile = returnLines([$testString]);

$file = basename($GLOBALS["TRACK"]["FILES_GENERATED"][0]);
$ts = time();
?>
<!DOCTYPE html>
<html>
<head>
    <link rel="icon" type="image/x-icon" href="images/favicon.ico">
    <title>CHIM TTS Test</title>
    <link rel="icon" type="image/x-icon" href="../images/favicon.ico">
    <style>
        /* Updated CSS for Dark Grey Background Theme */
        body {
            font-family: Arial, sans-serif;
            background-color: #2c2c2c; /* Dark grey background */
            color: #f8f9fa; /* Light grey text for readability */
            padding: 20px;
        }

        h1, h2, h3, .header {
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

        audio {
            width: 100%;
            margin-top: 20px;
        }

        .error-message {
            color: #dc3545;
        }
    </style>
</head>
<body>

<div class="header">CHIM Text-to-Speech Test</div>

<div class="section">
    <?php
    if ($file) {
        echo '<h3>' . htmlspecialchars($testString) . '</h3>';
        echo '<audio controls>';
        echo '<source src="../../soundcache/' . htmlspecialchars($file) . '?ts=' . htmlspecialchars($ts) . '" type="audio/wav">';
        echo 'Your browser does not support the audio element.';
        echo '</audio>';
    } else {
        echo '<div class="error-message"><strong>Error:</strong><br/>';
        $errorFilePath = $enginePath . 'soundcache' . DIRECTORY_SEPARATOR . md5(trim($testString)) . '.err';
        if (file_exists($errorFilePath)) {
            echo nl2br(htmlspecialchars(file_get_contents($errorFilePath)));
        } else {
            echo 'An unknown error occurred.';
        }
        echo '</div>';
    }
    ?>
</div>

<br>
<div class="status">
    <span class="label" style="font-weight: bold; color: yellow; background-color: black; padding: 5px; display: inline-block;">
        IF YOU DO NOT SEE AN AUDIO FILE, HERE ARE A LIST OF POTENTIAL ERRORS AND FIXES
    </span>
    <ul class="error-list" style="margin-top: 15px; list-style-type: none; padding-left: 0;">
        <li style="margin-bottom: 20px;">
            <strong>500 = Internal Server Error</strong>
            <ul class="subpoints" style="margin-left: 20px; list-style-type: circle;">
                <li>The audio file for the voice ID does not exist</li>
                <li>CHIM XTTS = Sync Voices in XTTS Management page</li>
                <li>MeloTTS= Use one of the 
                    <a href="https://docs.google.com/document/d/12KBar_VTn0xuf2pYw9MYQd7CKktx4JNr_2hiv4kOx3Q/edit?tab=t.0#heading=h.21ics3hex54a" target="_blank" style="color: yellow;">approved voice IDs</a>
                </li>
                <li>xVASynth = Make sure you have the voice ID installed</li>
            </ul>
        </li>
        <li style="margin-bottom: 20px;">
            <strong>404 = Not Found</strong>
            <ul class="subpoints" style="margin-left: 20px; list-style-type: circle;">
                <li>The URL is not valid for your TTS server</li>
                <li>CHIM XTTS = If locally installed make sure it is http://127.0.0.1:8020. If its on the cloud verify the URL from the cloud provider </li>
                <li>MeloTTS= Make sure it is http://127.0.0.1:8084</li>
                <li>xVASynth = Make sure you have the URL pointed to your PC's IP address. 
                    <a href="https://docs.google.com/document/d/12KBar_VTn0xuf2pYw9MYQd7CKktx4JNr_2hiv4kOx3Q/edit?tab=t.0#heading=h.3tf6myep6rmw" target="_blank" style="color: yellow;">Read this guide.</a>
                </li>
                <li>Using a 2nd PC = Make sure your local network and firewall is not blocking the connections. 
                    <a href="https://docs.google.com/document/d/12KBar_VTn0xuf2pYw9MYQd7CKktx4JNr_2hiv4kOx3Q/edit?tab=t.0#heading=h.3amyap27i7u8" target="_blank" style="color: yellow;">Read this guide.</a>
                </li>
            </ul>
        </li>
        <li style="margin-bottom: 20px;">
            <strong>If it's not the voice you expected</strong>
            <ul class="subpoints" style="margin-left: 20px; list-style-type: circle;">
                <li>Change the profile (Blue Button) in the top left on the CHIM server page. Select the NPC you want to hear</li>
                <li>If their voice is still wrong, check their voiceID field and the TTSFUNCTION you have selected</li>
            </ul>
        </li>
    </ul>
</div>


</body>
</html>
