<?php 

header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1
header("Pragma: no-cache"); // HTTP 1.0
header("Expires: 0"); // Proxies

error_reporting(E_ERROR);
session_start();

ob_start();

// Define base paths
define('BASE_PATH', dirname(__DIR__));
define('CONFIG_PATH', BASE_PATH . DIRECTORY_SEPARATOR . 'conf');
define('LIB_PATH', BASE_PATH . DIRECTORY_SEPARATOR . 'lib');

$url = 'conf_editor.php';

// Ensure config directory exists
if (!is_dir(CONFIG_PATH)) {
    die('Configuration directory not found');
}

require_once(LIB_PATH . DIRECTORY_SEPARATOR . "model_dynmodel.php");

// Check if conf.php exists, if not copy from sample
$mainConfigFile = CONFIG_PATH . DIRECTORY_SEPARATOR . "conf.php";
$sampleConfigFile = CONFIG_PATH . DIRECTORY_SEPARATOR . "conf.sample.php";

if (!file_exists($mainConfigFile)) {
    if (!file_exists($sampleConfigFile)) {
        die('Sample configuration file not found');
    }
    @copy($sampleConfigFile, $mainConfigFile);   // Defaults
}

require_once($sampleConfigFile);	// Should contain defaults
require_once($mainConfigFile);	// Should contain current ones
require(CONFIG_PATH . DIRECTORY_SEPARATOR . 'conf_loader.php');

// Profile selection
$GLOBALS["PROFILES"]["default"] = $mainConfigFile;
foreach (glob(CONFIG_PATH . DIRECTORY_SEPARATOR . 'conf_????????????????????????????????.php') as $mconf) {
    if (file_exists($mconf)) {
        $filename = basename($mconf);
        $pattern = '/conf_([a-f0-9]+)\.php/';
        preg_match($pattern, $filename, $matches);
        $hash = $matches[1];
        $GLOBALS["PROFILES"][$hash] = $mconf;
    }
}

// Function to compare modification dates
function compareFileModificationDate($a, $b) {
    return filemtime($b) - filemtime($a);
}

// Sort the profiles by modification date descending
if (is_array($GLOBALS["PROFILES"]))
    usort($GLOBALS["PROFILES"], 'compareFileModificationDate');
else
    $GLOBALS["PROFILES"] = [];

if (isset($_SESSION["PROFILE"]) && in_array($_SESSION["PROFILE"], $GLOBALS["PROFILES"])) {
    if (file_exists($_SESSION["PROFILE"])) {
        require_once($_SESSION["PROFILE"]);
    } else {
        $_SESSION["PROFILE"] = $mainConfigFile;
    }
} else {
    $_SESSION["PROFILE"] = $mainConfigFile;
}


    