<?php 

header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1
header("Pragma: no-cache"); // HTTP 1.0
header("Expires: 0"); // Proxies

error_reporting(E_ERROR);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

ob_start();

$url = 'conf_editor.php';
$rootPath = __DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR;
$configFilepath = $rootPath."conf".DIRECTORY_SEPARATOR;

require_once($rootPath . "lib" .DIRECTORY_SEPARATOR."runtime_bootstrap.php");
chimRuntimeBootstrapIfNeeded($rootPath, [
    'load_general_settings' => true,
    'load_stt_connector' => false,
    'load_itt_connector' => false,
    'load_tts_connector' => false,
    'load_player_name' => true,
    'load_narrator' => true,
]);

require_once($rootPath . "lib" .DIRECTORY_SEPARATOR."model_dynmodel.php");

require_once(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."conf".DIRECTORY_SEPARATOR.'conf_loader.php');

$configFilepath = realpath($configFilepath).DIRECTORY_SEPARATOR;

// Profile selection
foreach (glob($configFilepath . 'conf_????????????????????????????????.php') as $mconf ) {
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

// Ensure PROFILES is initialized and sort by modification date
if (!isset($GLOBALS["PROFILES"]) || !is_array($GLOBALS["PROFILES"])) {
    $GLOBALS["PROFILES"] = [];
}
usort($GLOBALS["PROFILES"], 'compareFileModificationDate');

$GLOBALS["PROFILES"] = array_merge(["default"=>"$configFilepath/conf.php"], $GLOBALS["PROFILES"]);

// Load the appropriate profile
if (isset($_SESSION["PROFILE"]) && in_array($_SESSION["PROFILE"],$GLOBALS["PROFILES"])) {
    require_once($_SESSION["PROFILE"]);
} else {
    $_SESSION["PROFILE"] = "$configFilepath/conf.php";
    require_once($_SESSION["PROFILE"]);
}

chimRuntimeApplyBootstrapOptions($rootPath, [
    'load_general_settings' => true,
    'load_stt_connector' => false,
    'load_itt_connector' => false,
    'load_tts_connector' => false,
    'load_player_name' => true,
    'load_narrator' => true,
]);

// Initialize automatic backup system (after profiles are loaded)
require_once($rootPath . "lib" . DIRECTORY_SEPARATOR . "automatic_backup.php");
