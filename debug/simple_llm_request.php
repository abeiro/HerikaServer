<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);
$file = __DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."data".DIRECTORY_SEPARATOR.'CurrentModel_72dc4b1c501563d149fec99eb45b45f1.json';
$enginePath = __DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR;

$modelContents = file_get_contents($file);

echo "Current AI Model is set to $modelContents.".PHP_EOL;

$enginePath = dirname((__FILE__)) . DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR;
require_once($enginePath . "conf".DIRECTORY_SEPARATOR."conf.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."model_dynmodel.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."{$GLOBALS["DBDRIVER"]}.class.php");
require_once($enginePath . "prompts" .DIRECTORY_SEPARATOR."command_prompt.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."chat_helper_functions.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."data_functions.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."logger.php");



$FUNCTIONS_ARE_ENABLED=false;
$gameRequest=["inputtext"];

$profile=md5("default");

if (file_exists($enginePath . "conf".DIRECTORY_SEPARATOR."conf_{$profile}.php")) {
    Logger::debug("PROFILE: {$profile}");
    $GLOBALS["active_profile"]=$profile;
    require_once($enginePath . "conf".DIRECTORY_SEPARATOR."conf_{$profile}.php");

} else 
    Logger::debug($enginePath . "conf".DIRECTORY_SEPARATOR."conf_{$profile}.php");

$GLOBALS["CURRENT_CONNECTOR"]=DMgetCurrentModel();

$db=new sql();

if (!isset($GLOBALS["CURRENT_CONNECTOR"]) || (!file_exists($enginePath."connector".DIRECTORY_SEPARATOR."{$GLOBALS["CURRENT_CONNECTOR"]}.php"))) {
    die("Choose a LLM model and connector.".PHP_EOL);

} else {
    Logger::debug("Using {$GLOBALS["CURRENT_CONNECTOR"]}");
    require($enginePath."connector".DIRECTORY_SEPARATOR."{$GLOBALS["CURRENT_CONNECTOR"]}.php");


    $GLOBALS["HERIKA_NAME"]="Herika";

    $contextDataHistoric = DataLastDataExpandedFor("", -50);
    
    $contextDataWorld = DataLastInfoFor("", -2);
    $contextDataFull = array_merge($contextDataWorld, $contextDataHistoric);
    $historyData="";

    foreach ($contextDataFull as $element) {
    
        $historyData.=trim("{$element["content"]}").PHP_EOL;
        
      }

    // Database Prompt (Movie Director)
    //$GLOBALS["HERIKA_NAME"]="random present actor";
    $GLOBALS["HERIKA_PERS"]="";

    $prompt[] = array('role' => 'system', 'content' => "I want you to read this gameplay transcription in Skyrim universe.");
    $prompt[] = array('role' => 'user', 'content' => $historyData);
    $prompt[] = array('role' => 'user', 'content' =>"Now act as a movie director and create a new line of dialogue for any of the participants. 
This new line can introduce a new topic, keep talking about same topics, say someting new, or point to a enviromental action that has happened...be creative but logical.");
    
    $connectionHandler = new $GLOBALS["CURRENT_CONNECTOR"];
    $connectionHandler->open($prompt,["MAX_TOKENS"=>256]);

    $buffer="";
    $totalBuffer="";
    $breakFlag=false;
    
     while (true) {

        if ($breakFlag) {
            break;
        }

        $buffer=$connectionHandler->process();
        $totalBuffer.=$buffer;

        if ($connectionHandler->isDone()) {
            $breakFlag=true;
        }
        
     }
    
    $rawbuffer=$connectionHandler->close();
     
    print_r($rawbuffer);
   
}


?>
