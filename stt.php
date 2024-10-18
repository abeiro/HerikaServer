<?php


/* STT entry point */


$path = dirname((__FILE__)) . DIRECTORY_SEPARATOR;
require_once($path . "conf".DIRECTORY_SEPARATOR."conf.php"); // API KEY must be there
require_once($path . "lib" .DIRECTORY_SEPARATOR."auditing.php");



$startTime = microtime(true);
error_log("Audit run ID: " . $GLOBALS["AUDIT_RUNID"]. " (STT) started: ".$startTime);
$GLOBALS["AUDIT_RUNID_REQUEST"]="STT";

$finalName=__DIR__.DIRECTORY_SEPARATOR."soundcache/_stt_".md5($_FILES["file"]["tmp_name"]).".wav";


if (!$_FILES["file"]["tmp_name"]) {
    error_log("STT error, no data given {$_FILES["file"]["tmp_name"]}");
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
    
}

echo $text;

?>

