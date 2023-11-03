<?php 

$enginePath = dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR;
require_once($enginePath . "conf/conf.php");
require_once($enginePath . "lib/$DBDRIVER.class.php");
require_once($enginePath . "conf".DIRECTORY_SEPARATOR."conf.php"); // API KEY must be there
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."model_dynmodel.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."{$GLOBALS["DBDRIVER"]}.class.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."data_functions.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."chat_helper_functions.php");
    
require_once($enginePath."itt/itt-{$GLOBALS["ITTFUNCTION"]}.php");
    
$db=new sql();

$location=DataLastKnownLocation();
$charactersArray=implode(",",DataPosibleInspectTargets(true));


$hints="Location: $location. Posible characters: {$GLOBALS["HERIKA_NAME"]},{$GLOBALS["PLAYER_NAME"]}, Other characters: $charactersArray";

echo itt($argv[1],$hints);


    
?>
