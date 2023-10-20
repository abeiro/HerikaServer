<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);
$file = __DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."data".DIRECTORY_SEPARATOR.'CurrentModel.json';

$enginePath = dirname((__FILE__)) . DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR;
require_once($enginePath . "conf".DIRECTORY_SEPARATOR."conf.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."model_dynmodel.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."{$GLOBALS["DBDRIVER"]}.class.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."chat_helper_functions.php");

// $gameRequest = type of message|localts|gamets|data

$db=new sql();
$results = $db->fetchAll("select  A.*,ROWID FROM  eventlog a order by ROWID desc LIMIT 0,10");

$gameRequest=["infoaction",$results[0]["ts"]+1,$results[0]["gamets"]+1,"{$argv[1]}"];

logEvent($gameRequest);

?>
