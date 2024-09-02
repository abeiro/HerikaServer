<?php


/* STT entry point */


$path = dirname((__FILE__)) . DIRECTORY_SEPARATOR;
require_once($path . "conf".DIRECTORY_SEPARATOR."conf.php"); // API KEY must be there

$finalName=__DIR__.DIRECTORY_SEPARATOR."soundcache/_stt_".md5($_FILES["file"]["tmp_name"]).".wav";


if (!$_FILES["file"]["tmp_name"])
    die("STT error, no data given");

@copy($_FILES["file"]["tmp_name"] ,$finalName);


if ($STTFUNCTION=="azure") {
    
    require_once($path."stt/stt-azure.php");
    $text= stt($finalName);
    
} else if ($STTFUNCTION=="whisper") { 

    require_once($path."stt/stt-whisper.php");
    $text= stt($finalName);

} else if ($STTFUNCTION=="deepgram") { 

    require_once($path."stt/stt-deepgram.php");
    $text= stt($finalName);
    
} else if ($STTFUNCTION=="localwhisper") { 

    require_once($path."stt/stt-localwhisper.php");
    $text= stt($finalName);
    
}

echo $text;

?>

