<?php

require_once(__DIR__.DIRECTORY_SEPARATOR."../profile_loader.php");

$TITLE = "🔊CHIM - TTS Test - CHIM Server";

ob_start();

include("../tmpl/head.html");

$debugPaneLink = false;
include("../tmpl/navbar.php");


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
echo"<pre>$commandcompact</pre>";

echo "<ul>";
$lines = explode("\n", $commandcompact);
foreach ($lines as $line) {
    $line = trim($line);
    if (!empty($line)) {
        echo "<li>$line</li>";
    }
}
echo "</ul>";

// Run sync command // Disabled
/*
$commandsync = 'php /var/www/html/HerikaServer/debug/util_memory_subsystem.php sync';
//$outputsync = shell_exec($commandsync);

// Output sync command
if ($embedding == 'local') {
    echo "<h1>Memory Sync for Local Text2Vec</h1>";
} else {
    echo "<h1>Memory Sync for OpenAI's ADA2</h1>";
}
*/

echo '</div>';
?>
