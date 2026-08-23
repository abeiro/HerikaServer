<?php 

$enginePath = dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR;
require_once($enginePath . "lib/runtime_bootstrap.php");
chimRuntimeBootstrap($enginePath, [
    'load_general_settings' => true,
    'load_stt_connector' => true,
    'load_itt_connector' => true,
    'load_tts_connector' => true,
    'load_player_name' => true,
    'load_narrator' => true,
]);

require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."model_dynmodel.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."data_functions.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."chat_helper_functions.php");
    

error_reporting(E_ALL);
$db = $GLOBALS["db"];

$GLOBALS["AVOID_TTS_CACHE"]=true;

$DEBUG_DATA=[];
$GLOBALS["HERIKA_NAME"]="Karrie";
$GLOBALS["TTS"]["FORCED_VOICE_DEV"]="femaleyoungeager";
print_r(returnLines(["I heard there's a powerful mage in Winterhold. We should pay them a visit","Today, as we gather in this virtual hall, I can't help but draw inspiration from the vast and enchanting universe of Skyrim"]));

print_r($DEBUG_DATA);

?>
