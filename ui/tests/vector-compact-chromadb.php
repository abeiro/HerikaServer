<?php

require_once(__DIR__.DIRECTORY_SEPARATOR."../profile_loader.php");

$TITLE = "🔊CHIM - TTS Test - CHIM Server";

ob_start();

include("../tmpl/head.html");

$debugPaneLink = false;
include("../tmpl/navbar.php");

// Add styles for command output
echo <<<HTML
<style>
pre.command-output {
    background-color: #2c2c2c; /* Site background color */
    border: 1px solid #444;    /* Darker border to complement the dark background */
    padding: 15px;
    border-radius: 5px;
    white-space: pre-wrap;    /* CSS3 - wrap lines */
    word-wrap: break-word;    /* Internet Explorer 5.5+ */
    font-family: monospace;
    font-size: 0.9em;
    color: #ffffff;           /* White text color */
}
</style>
HTML;

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

$embedding = $FEATURES["MEMORY_EMBEDDING"]["TEXT2VEC_PROVIDER"];

//Run the Compact Command
$commandcompact = 'php /var/www/html/HerikaServer/debug/util_memory_subsystem.php compact noembed';
$commandcompact = shell_exec($commandcompact);
echo '<link rel="stylesheet" type="text/css" href="../css/main.css">';
echo "<title> CHIM - Compact Memories</title>";

echo '<div style="padding-top: 160px; padding-left: 20px; padding-right: 20px;">';

echo "<h1>Compact Memories</h1>";
echo "<pre class='command-output'>$commandcompact</pre>";

//Run the Sync Command
$commandsync = 'php /var/www/html/HerikaServer/debug/util_memory_subsystem.php sync';
$outputsync = shell_exec($commandsync);
    echo "<br>";
    echo "<h1>Memory Sync for TXT2VEC</h1>";
    echo "<pre class='command-output'>$outputsync</pre>";


echo '</div>';
?>
