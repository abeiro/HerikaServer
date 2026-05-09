<?php 

$enginePath = dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR;
require_once($enginePath . "lib/runtime_bootstrap.php");
chimRuntimeBootstrap($enginePath, [
    'load_general_settings' => true,
    'load_stt_connector' => true,
    'load_itt_connector' => true,
    'load_player_name' => true,
    'load_narrator' => true,
]);

require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."model_dynmodel.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."data_functions.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."chat_helper_functions.php");
    
require_once($enginePath."itt/itt-{$GLOBALS["ITTFUNCTION"]}.php");
    
$db = $GLOBALS["db"];

$location=DataLastKnownLocation();
$charactersArray=implode(",",DataPosibleInspectTargets(true));


$hints="Posible characters: {$GLOBALS["PLAYER_NAME"]} : $charactersArray";

echo itt($argv[1],$hints);


    
?>
