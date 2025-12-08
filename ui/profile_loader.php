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

// Load player data from core_player database table if available (overrides conf.php)
// This ensures UI pages and scripts always show current player data from the game
try {
    if (isset($GLOBALS["DBDRIVER"]) && !empty($GLOBALS["DBDRIVER"])) {
        $dbClassFile = $rootPath . "lib" . DIRECTORY_SEPARATOR . "{$GLOBALS["DBDRIVER"]}.class.php";
        $playerClassFile = $rootPath . "lib" . DIRECTORY_SEPARATOR . "core" . DIRECTORY_SEPARATOR . "player.class.php";
        
        if (!class_exists('sql') && file_exists($dbClassFile)) {
            require_once($dbClassFile);
        }
        if (!class_exists('Player') && file_exists($playerClassFile)) {
            require_once($playerClassFile);
        }
        
        if (class_exists('sql') && class_exists('Player')) {
            $db_player = new sql();
            $player = new Player();
            
            // Load player name from core_player table
            $playerNameFromTable = $player->get('player_name');
            if ($playerNameFromTable !== null && $playerNameFromTable !== '') {
                $GLOBALS["PLAYER_NAME"] = $playerNameFromTable;
            } else {
                // Fallback to conf_opts for backward compatibility
                $playerNameFromDb = $db_player->fetchOne("SELECT value FROM conf_opts WHERE id='PLAYER_NAME'");
                if ($playerNameFromDb && !empty($playerNameFromDb['value'])) {
                    $GLOBALS["PLAYER_NAME"] = $playerNameFromDb['value'];
                }
            }
            
            // Load player appearance from core_player table (maps to PLAYER_BIOS for backward compatibility)
            $playerAppearance = $player->get('appearance');
            if ($playerAppearance !== null && $playerAppearance !== '') {
                $GLOBALS["PLAYER_BIOS"] = $playerAppearance;
            }
            
            // Load player speech style from core_player table
            $playerSpeechStyle = $player->get('speech_style');
            if ($playerSpeechStyle !== null && $playerSpeechStyle !== '') {
                $GLOBALS["PLAYER_SPEECH_STYLE"] = $playerSpeechStyle;
            }
        }
    }
} catch (Throwable $e) {
    // Silently fail and use conf.php values if database query fails
}

    