<?php 

header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1
header("Pragma: no-cache"); // HTTP 1.0
header("Expires: 0"); // Proxies

error_reporting(E_ERROR);
session_start();

ob_start();

$url = 'conf_editor.php';
$rootPath = __DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR;
$configFilepath = $rootPath."conf".DIRECTORY_SEPARATOR;

require_once($rootPath . "lib" .DIRECTORY_SEPARATOR."model_dynmodel.php");

require_once($rootPath."conf".DIRECTORY_SEPARATOR."conf.sample.php");	// Should contain defaults
if (file_exists($rootPath."conf".DIRECTORY_SEPARATOR."conf.php"))
    require_once($rootPath."conf".DIRECTORY_SEPARATOR."conf.php");	// Should contain current ones

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

// Initialize automatic backup system (after profiles are loaded)
require_once($rootPath . "lib" . DIRECTORY_SEPARATOR . "automatic_backup.php");

// Load PLAYER_NAME from database if available (overrides conf.php)
// This ensures UI pages always show the current player name from the game
try {
    if (isset($GLOBALS["DBDRIVER"]) && !empty($GLOBALS["DBDRIVER"])) {
        $dbClassFile = $rootPath . "lib" . DIRECTORY_SEPARATOR . "{$GLOBALS["DBDRIVER"]}.class.php";
        if (!class_exists('sql') && file_exists($dbClassFile)) {
            require_once($dbClassFile);
        }
        if (class_exists('sql')) {
            $db_player = new sql();
            $playerNameFromDb = $db_player->fetchOne("SELECT value FROM conf_opts WHERE id='PLAYER_NAME'");
            if ($playerNameFromDb && !empty($playerNameFromDb['value'])) {
                $GLOBALS["PLAYER_NAME"] = $playerNameFromDb['value'];
            }
        }
    }
} catch (Throwable $e) {
    // Silently fail and use conf.php value if database query fails
}

    