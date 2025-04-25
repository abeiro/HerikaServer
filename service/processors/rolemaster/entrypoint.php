<?php 

$GLOBALS["TASKS"]["rolemaster"]=[];
$GLOBALS["TASKS"]["rolemaster"]["fn"]=function() {

$enginePath = $GLOBALS["ENGINE_ROOT"];

/* Connector to use */
$file = $GLOBALS["ENGINE_ROOT"].'/data/CurrentModel.json';
;
$modelContents = file_get_contents($file);
logMsg("Current AI Model is set to $modelContents.");

// Initialize function parameters before requiring functions.php
$GLOBALS["FUNCTION_PARM_INSPECT"] = [];
$GLOBALS["FUNCTION_PARM_MOVETO"] = [];
$GLOBALS["F_NAMES"] = [];

require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."model_dynmodel.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."{$GLOBALS["DBDRIVER"]}.class.php");
require_once($enginePath . "prompts" .DIRECTORY_SEPARATOR."command_prompt.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."chat_helper_functions.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."data_functions.php");
require_once($enginePath . "lib/rolemaster_helpers.php");
require_once($enginePath . "functions/functions.php");

$FUNCTIONS_ARE_ENABLED=false;


$profile=md5("default");

if (file_exists($enginePath . "conf".DIRECTORY_SEPARATOR."conf_{$profile}.php")) {
    logMsg("PROFILE: {$profile}");
    $GLOBALS["active_profile"]=$profile;
    require_once($enginePath . "conf".DIRECTORY_SEPARATOR."conf_{$profile}.php");

} else 
    logMsg("Profile does not exists:  $enginePath" . "conf".DIRECTORY_SEPARATOR."conf_{$profile}.php",S_LOG_ERROR);

$GLOBALS["CURRENT_CONNECTOR"]=$GLOBALS["CONNECTORS_DIARY"];

$GLOBALS["db"]=new sql();

// Some functions need this setted */
$res=$GLOBALS["db"]->fetchAll("select max(gamets)+1 as gamets,max(ts)+1 as ts  from eventlog order by gamets desc limit 1 offset 0");
$GLOBALS["gameRequest"]=["inputtext"];
$GLOBALS["gameRequest"][2]=$res[0]["gamets"]+1;

if ($GLOBALS["argv"][2]=="instruction")
    require_once("cmd" . DIRECTORY_SEPARATOR . "instruction.php");
else if ($GLOBALS["argv"][2]=="suggestion")
    require_once("cmd" . DIRECTORY_SEPARATOR . "suggestion.php");
else if ($GLOBALS["argv"][2]=="impersonation")
    require_once("cmd" . DIRECTORY_SEPARATOR . "impersonation.php");


    

}
?>