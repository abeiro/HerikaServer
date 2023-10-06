<?php 

$enginePath = dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR;
require_once($enginePath . "conf/conf.php");
require_once($enginePath . "lib/$DBDRIVER.class.php");

if ($STTFUNCTION=="azure") {
    
    require_once($enginePath."stt/stt-azure.php");
    
    
} else if ($STTFUNCTION=="whisper") { 

    require_once($enginePath."stt/stt-whisper.php");
    
    
} else if ($STTFUNCTION=="localwhisper") { 

    require_once($enginePath."stt/stt-localwhisper.php");
    
    
}

echo  stt(__DIR__.DIRECTORY_SEPARATOR."data/test.wav").PHP_EOL;


    
?>
