<?php


/* STT entry point */

$path = dirname((__FILE__)) . DIRECTORY_SEPARATOR;
require_once($path . "lib" .DIRECTORY_SEPARATOR."runtime_bootstrap.php");
chimRuntimeBootstrap($path, [
    'load_general_settings' => true,
    'load_stt_connector' => true,
    'load_itt_connector' => false,
]);
require_once($path . "lib" .DIRECTORY_SEPARATOR."auditing.php");
require_once($path . "lib" .DIRECTORY_SEPARATOR."logger.php");
require_once($path . "lib" .DIRECTORY_SEPARATOR."pipeline_status.php");


$startTime = microtime(true);
Logger::trace("Audit run ID: " . $GLOBALS["AUDIT_RUNID"]. " (STT) started: ".$startTime);
$GLOBALS["AUDIT_RUNID_REQUEST"]="STT";

// Set STT processing status
pipeline_status_set('stt', true);

$finalName=__DIR__.DIRECTORY_SEPARATOR."soundcache/_stt_".md5($_FILES["file"]["tmp_name"]).".wav";


if (!$_FILES["file"]["tmp_name"]) {
    Logger::error("STT error, no data given {$_FILES["file"]["tmp_name"]}");
    die("STT error, no data given");
}

@copy($_FILES["file"]["tmp_name"] ,$finalName);


if ($STTFUNCTION=="azure") {
    
    require_once($path."stt/stt-azure.php");
    $text= stt($finalName);
    
} else if ($STTFUNCTION=="whisper") { 

    require_once($path."stt/stt-whisper.php");
    $text= stt($finalName);
    
} else if ($STTFUNCTION=="localwhisper") { 
    require_once($path."stt/stt-localwhisper.php");
    $text= stt($finalName);
    
} else if ($STTFUNCTION=="deepgram") {
    require_once($path."stt/stt-deepgram.php");
    $text= stt($finalName);

} else if ($STTFUNCTION=="gemini") {
    require_once($path."stt/stt-gemini.php");
    $text= stt($finalName);

} else if (file_exists($path . "stt" . DIRECTORY_SEPARATOR . "stt-{$STTFUNCTION}.php")){
    require_once($path . "stt" . DIRECTORY_SEPARATOR . "stt-{$STTFUNCTION}.php");
    $text= stt($finalName);
} else {
    require_once($path."stt/stt-none.php");
    $text= stt($finalName);
} 

// Clear STT processing status
pipeline_status_set('stt', false);

echo $text;

?>

