<?php
// This file is for apply db updates via command line.

error_reporting(E_ERROR);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$configFilepath = __DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."conf".DIRECTORY_SEPARATOR;
$rootEnginePath = __DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR;

$defaultConf = $rootEnginePath . "conf" . DIRECTORY_SEPARATOR . "conf.php";
$sampleConf = $rootEnginePath . "conf" . DIRECTORY_SEPARATOR . "conf.sample.php";
$confLoader = $rootEnginePath . "conf" . DIRECTORY_SEPARATOR . "conf_loader.php";

if (file_exists($sampleConf)) {
    require_once($sampleConf);
}
if (file_exists($defaultConf)) {
    require_once($defaultConf);
}
if (file_exists($confLoader)) {
    require_once($confLoader);
}

if (empty($GLOBALS["DBDRIVER"])) {
    $GLOBALS["DBDRIVER"] = "postgresql";
}

$configFilepath=realpath($configFilepath).DIRECTORY_SEPARATOR;

// Profile selection
$GLOBALS["PROFILES"] = is_array($GLOBALS["PROFILES"] ?? null) ? $GLOBALS["PROFILES"] : [];
$GLOBALS["PROFILES"]["default"] = file_exists($defaultConf) ? $defaultConf : $sampleConf;
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
        $_SESSION["PROFILE"] = $GLOBALS["PROFILES"]["default"];
        
    }

} else
    $_SESSION["PROFILE"] = $GLOBALS["PROFILES"]["default"];
// End of profile selection




require_once($rootEnginePath . "lib" .DIRECTORY_SEPARATOR."{$GLOBALS["DBDRIVER"]}.class.php");
require_once($rootEnginePath . "lib" .DIRECTORY_SEPARATOR."chat_helper_functions.php");
$db = new sql();
require_once(__DIR__."/db_updates.php");
?>
