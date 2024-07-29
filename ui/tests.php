<?php 

session_start();

?>
<!DOCTYPE html>
<html>
<head>
    <link rel="icon" type="image/x-icon" href="images/favicon.ico">
</head>
<body>

<?php


$enginePath = dirname((__FILE__)) . DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR;
ini_set('display_errors', 1);
error_reporting(E_ERROR);

echo "Checking conf.php...";
if (!file_exists($enginePath."conf".DIRECTORY_SEPARATOR."conf.php")) {
    echo "not found<br>";
} else {
    echo "ok<br/>";
}


echo "Trying to instantiate...";
require_once($enginePath . "conf".DIRECTORY_SEPARATOR."conf.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."model_dynmodel.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."{$GLOBALS["DBDRIVER"]}.class.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."data_functions.php");


if (isset($_SESSION["PROFILE"])) {
    require_once($_SESSION["PROFILE"]);
}

$GLOBALS["active_profile"]=md5($GLOBALS["HERIKA_NAME"]);

$GLOBALS["CURRENT_CONNECTOR"]=DMgetCurrentModel();



$FEATURES["MEMORY_EMBEDDING"]["ENABLED"]=false;

echo "ok<br/>";

echo "Opening database...";
$db = new sql();
if (!$db) {
    echo "error<br/>";
} else {
    echo "ok<br/>";
}


echo "Trying to make a request...<pre>";

$FUNCTIONS_ARE_ENABLED=true;
if ($FUNCTIONS_ARE_ENABLED) {
    /* 
    * Info gathering to mangle function definitions. This will enforce some parameters to be fixed-
    */
    
    $GLOBALS["TEMPLATE_DIALOG"]="";
    $FUNCTION_PARM_MOVETO=[];		// To avoid moving to non existant target, lets limit available targets to the real ones in function definition
    if (!isset($FUNCTION_PARM_MOVETO))
        $FUNCTION_PARM_MOVETO=[];
    $FUNCTION_PARM_MOVETO[]=$GLOBALS["PLAYER_NAME"];


    $FUNCTION_PARM_INSPECT=[];	// To avoid moving to non existant target, lets limit available targets to the real ones in function definition
    if (!isset($FUNCTION_PARM_INSPECT))
        $FUNCTION_PARM_INSPECT=[];
    $FUNCTION_PARM_INSPECT[]=$GLOBALS["PLAYER_NAME"];


    require_once(__DIR__.DIRECTORY_SEPARATOR ."../prompts".DIRECTORY_SEPARATOR."command_prompt.php");
    require_once(__DIR__.DIRECTORY_SEPARATOR ."../functions" . DIRECTORY_SEPARATOR . "functions.php");
   
}
$gameRequest=["inputtext"];

if (!isset($GLOBALS["CURRENT_CONNECTOR"]) || (!file_exists($enginePath."connector".DIRECTORY_SEPARATOR."{$GLOBALS["CURRENT_CONNECTOR"]}.php"))) {
    die("{$GLOBALS["HERIKA_NAME"]}|AASPGQuestDialogue2Topic1B1Topic|I'm mindless. Choose a LLM model and connector.".PHP_EOL);

} else {

    require($enginePath."connector".DIRECTORY_SEPARATOR."{$GLOBALS["CURRENT_CONNECTOR"]}.php");

    $head[] = array('role' => 'system', 'content' => $GLOBALS["PROMPT_HEAD"] . $GLOBALS["HERIKA_PERS"] );
    $prompt[] = array('role' => 'user', 'content' => 
        "Hey, {$GLOBALS["HERIKA_NAME"]}, attack that monster!!"
    );
    $contextData = array_merge($head, $prompt);

    $connectionHandler=new connector();
    $connectionHandler->open($contextData,[]);

    $buffer="";
    $totalBuffer="";
    $breakFlag=false;
    
     while (true) {

        if ($breakFlag) {
            break;
        }

        $buffer.=$connectionHandler->process();
        $totalBuffer.=$buffer;
        //$bugBuffer[]=$buffer;

        if ($connectionHandler->isDone()) {
            $breakFlag=true;
        }
        
     }
     
    $connectionHandler->close();
    
    //echo "$totalBuffer".PHP_EOL;
    
    $actions=$connectionHandler->processActions();
    if (is_array($actions) && (sizeof($actions)>0)) {

        $GLOBALS["DEBUG_DATA"]["response"][]=$actions;
        echo implode("\r\n", $actions);
    }
    
    print_r($GLOBALS["DEBUG_DATA"]);
    print_r($GLOBALS["ALREADY_SENT_BUFFER"]); 
    print_r($bugBuffer);
}

echo "</pre>LLM Response:<strong>$buffer</strong>";


?>
