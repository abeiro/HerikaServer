<?php 
error_reporting(E_ERROR);
$enginePath = dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR;
require_once($enginePath . "conf/conf.php");
require_once($enginePath . "lib/$DBDRIVER.class.php");

if ($STTFUNCTION=="azure") {
    
    require_once($enginePath."stt/stt-azure.php");
    
    
} else if ($STTFUNCTION=="whisper") { 

    require_once($enginePath."stt/stt-whisper.php");
    
    
} else if ($STTFUNCTION=="localwhisper") { 

    require_once($enginePath."stt/stt-localwhisper.php");
    
    
} else if ($STTFUNCTION=="deepgram") { 
    require_once($enginePath."stt/stt-deepgram.php");
    
}


if (!$argv[1]) {
    if ((php_sapi_name()!="cli"))
        echo "<pre>";

	echo "Sending ".__DIR__.DIRECTORY_SEPARATOR."data/test.wav".PHP_EOL;
    echo "Expected result: 'Welcome to the jungle, we've got fun and games.': ".PHP_EOL;
	echo "Obtaining transcription from STT service.... ";
    
    echo  stt(__DIR__.DIRECTORY_SEPARATOR."data/test.wav").PHP_EOL;
    echo "Service used: ";

    print_r($GLOBALS["STTFUNCTION"].PHP_EOL);
    
    if ((php_sapi_name()!="cli"))
        echo "</pre>";

}
else
	echo  stt($argv[1]).PHP_EOL;


    
?>
