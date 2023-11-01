<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);
$file = __DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."data".DIRECTORY_SEPARATOR.'CurrentModel.json';
$enginePath = __DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR;

$modelContents = file_get_contents($file);

echo "Current AI Model is set to $modelContents.".PHP_EOL;

$enginePath = dirname((__FILE__)) . DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR;
require_once($enginePath . "conf".DIRECTORY_SEPARATOR."conf.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."model_dynmodel.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."{$GLOBALS["DBDRIVER"]}.class.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."chat_helper_functions.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."data_functions.php");



$FUNCTIONS_ARE_ENABLED=false;
$gameRequest=[];

if (!isset($GLOBALS["CURRENT_CONNECTOR"]) || (!file_exists($enginePath."connector".DIRECTORY_SEPARATOR."{$GLOBALS["CURRENT_CONNECTOR"]}.php"))) {
    die("Choose a LLM model and connector.".PHP_EOL);

} else {

    require($enginePath."connector".DIRECTORY_SEPARATOR."{$GLOBALS["CURRENT_CONNECTOR"]}.php");

    $COMMAND_PROMPT='';
    $db = new sql();
    $res=$db->fetchAll("select max(gamets) as last_gamets from eventlog");
    $last_gamets=$res[0]["last_gamets"]+1;
    $gameRequest=["inputtext","0",$last_gamets,$argv[1]];
    $request=$argv[1];
    
    $lastNDataForContext = (isset($GLOBALS["CONTEXT_HISTORY"])) ? ($GLOBALS["CONTEXT_HISTORY"]) : "25";

// Historic context (last dialogues, events,...)
$contextDataHistoric = DataLastDataExpandedFor("", -25);
$contextDataWorld = DataLastInfoFor("", -2);

$contextDataFull = array_merge($contextDataWorld, $contextDataHistoric);


$head[] = array('role' => 'system', 'content' =>  $GLOBALS["PROMPT_HEAD"] . $GLOBALS["HERIKA_PERS"] . $GLOBALS["COMMAND_PROMPT"]);
$prompt[] = array('role' => 'user', 'content' => $argv[1]);
 $prompt[] = array('role' => 'user', 'content' => $request);

    $contextData = array_merge($head, ($contextDataFull), $prompt);

    
    $connectionHandler=new connector();
    $connectionHandler->open($contextData,["MAX_TOKENS"=>75,"GRAMMAR_ACTIONS"=>true]);

    $buffer="";
    $totalBuffer="";
    $breakFlag=false;
    
     while (true) {

        if ($breakFlag) {
            break;
        }

        $buffer.=$connectionHandler->process();
        $totalBuffer.=$buffer;

        if ($connectionHandler->isDone()) {
            $breakFlag=true;
        }
        
     }
     
     echo PHP_EOL."$buffer".PHP_EOL;
   
}


?>
