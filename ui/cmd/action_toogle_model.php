<?php

session_start();

$enginePath = dirname((__FILE__)) . DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."../";
require_once($enginePath . "conf".DIRECTORY_SEPARATOR."conf.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."model_dynmodel.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."{$GLOBALS["DBDRIVER"]}.class.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."data_functions.php");

if (isset($_SESSION["PROFILE"])) {
    require_once($_SESSION["PROFILE"]);
}

$GLOBALS["active_profile"]=md5($GLOBALS["HERIKA_NAME"]);
$newModel=DMtoggleModel();

if (isset($_SERVER['HTTP_REFERER'])) {
    // Redirect to the referring page
    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit; // Make sure to call exit after header redirection
} else {
    // Fallback if no referring page is set
    header("Location: index.php");
    exit; // Make sure to call exit after header redirection
}

?>
