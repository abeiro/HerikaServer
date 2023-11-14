<?php 

$enginePath = dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR;
require_once($enginePath . "conf/conf.php");
require_once($enginePath . "lib/$DBDRIVER.class.php");
require_once($enginePath . "conf".DIRECTORY_SEPARATOR."conf.php"); // API KEY must be there
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."model_dynmodel.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."{$GLOBALS["DBDRIVER"]}.class.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."data_functions.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."chat_helper_functions.php");
    

error_reporting(E_ALL);
$db=new sql();

$GLOBALS["AVOID_TTS_CACHE"]=true;

$DEBUG_DATA=[];

print_r(returnLines(["I heard there's a powerful mage in Winterhold. We should pay them a visit"]));

print_r($DEBUG_DATA);

?>
