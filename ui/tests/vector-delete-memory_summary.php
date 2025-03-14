<?php

require_once(__DIR__.DIRECTORY_SEPARATOR."../profile_loader.php");

$TITLE = "🔊CHIM - TTS Test - CHIM Server";

ob_start();

include("../tmpl/head.html");

$debugPaneLink = false;
include("../tmpl/navbar.php");
include("../ui/css/main.css");



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

session_start();

// Enable error reporting (for development/testing)
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Database connection details
$host = 'localhost';
$port = '5432';
$dbname = 'dwemer';
$schema = 'public';
$username = 'dwemer';
$password = 'dwemer';

// Connect to the database
$conn = pg_connect("host=$host port=$port dbname=$dbname user=$username password=$password");
if (!$conn) {
    echo "Failed to connect to the database: " . pg_last_error();
    exit;
}
echo '<div style="padding-top: 160px; padding-left: 20px; padding-right: 20px;">';
// Delete all entries from memory_summary
$query = "DELETE FROM {$schema}.memory_summary;";
$result = pg_query($conn, $query);

if ($result) {
    echo "<h1>All entries in the memory summary table have been deleted successfully.</h1>";
} else {
    echo "<h1>Error deleting entries from memory summary: " . pg_last_error($conn) . "</h1>";
}

// Close the connection
pg_close($conn);
echo '</div>';
?>
