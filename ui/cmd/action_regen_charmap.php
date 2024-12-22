<?php

session_start();

$enginePath = dirname((__FILE__)) . DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."../";
require_once($enginePath . "conf".DIRECTORY_SEPARATOR."conf.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."model_dynmodel.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."{$GLOBALS["DBDRIVER"]}.class.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."data_functions.php");



$configFilepath=$enginePath."conf".DIRECTORY_SEPARATOR;

// Profile selection


foreach (glob($configFilepath . 'conf_????????????????????????????????.php') as $mconf ) {
    if (file_exists($mconf)) {
        $filename=basename($mconf);
        $pattern = '/conf_([a-f0-9]+)\.php/';
        preg_match($pattern, $filename, $matches);
        $hash = $matches[1];
        require($mconf);
        $GLOBALS["PROFILES"][$hash]=$GLOBALS["HERIKA_NAME"];
    }
}


file_put_contents($enginePath . "conf".DIRECTORY_SEPARATOR."character_map.json",json_encode($GLOBALS["PROFILES"]));
    
echo "Done! You can close this tab. If you see nothing then it means your character map was already good to go.";

?>
