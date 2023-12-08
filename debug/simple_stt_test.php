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

if (!$argv[1]) {
	echo "Expected result: 'Welcome to the jungle. We've got fun and games'".PHP_EOL;
	echo  stt(__DIR__.DIRECTORY_SEPARATOR."data/test.wav").PHP_EOL;
}
else
	echo  stt($argv[1]).PHP_EOL;


    
?>
