<?php
// This file is for apply db updates via command line.

error_reporting(E_ERROR);
session_start();

$configFilepath = __DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."conf".DIRECTORY_SEPARATOR;
$rootEnginePath = __DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR;


require_once($rootEnginePath . "conf".DIRECTORY_SEPARATOR."conf.php");

$configFilepath=realpath($configFilepath).DIRECTORY_SEPARATOR;

// Profile selection
$GLOBALS["PROFILES"]["default"]="$configFilepath/conf.php";
foreach (glob($configFilepath . 'conf_????????????????????????????????????????????????.php') as $mconf ) {
    if (file_exists($mconf)) {
        $filename=basename($mconf);
        $pattern = '/conf_([a-f0-9]+)\.php/';
        preg_match($pattern, $filename, $matches);
        $hash = $matches[1];
        $GLOBALS["PROFILES"][$hash]=$mconf;
    }
}

if (isset($_SESSION["PROFILE"]) && in_array($_SESSION["PROFILE"],$GLOBALS["PROFILES"])) {
    if (file_exists($_SESSION["PROFILE"]))
        require_once($_SESSION["PROFILE"]);
    else {
        $_SESSION["PROFILE"]="$configFilepath/conf.php";
        
    }

} else
    $_SESSION["PROFILE"]="$configFilepath/conf.php";
// End of profile selection




require_once($rootEnginePath . "lib" .DIRECTORY_SEPARATOR."{$GLOBALS["DBDRIVER"]}.class.php");
require_once($rootEnginePath . "lib" .DIRECTORY_SEPARATOR."chat_helper_functions.php");
$db = new sql();
require_once(__DIR__."/db_updates.php");
?>
