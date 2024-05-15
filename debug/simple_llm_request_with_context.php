<?php

$startTime=microtime(true);

define("MAXIMUM_SENTENCE_SIZE", 125);
define("MINIMUM_SENTENCE_SIZE", 15);

$GLOBALS["SCRIPTLINE_EXPRESSION"]="";
$GLOBALS["SCRIPTLINE_LISTENER"]="";
$GLOBALS["SCRIPTLINE_ANIMATION"]="";



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

if (isset($argv[2])) {
    if (file_exists($enginePath . "conf".DIRECTORY_SEPARATOR."conf_{$argv[2]}.php")) {
        error_log("PROFILE: {$argv[2]}");
        require_once($enginePath . "conf".DIRECTORY_SEPARATOR."conf_{$argv[2]}.php");

    } else 
        error_log($enginePath . "conf".DIRECTORY_SEPARATOR."conf_{$argv[2]}.php");
    
    $GLOBALS["CURRENT_CONNECTOR"]=DMgetCurrentModel();

}



$FUNCTIONS_ARE_ENABLED=true;


$gameRequest=[];

error_log(__LINE__." " .(microtime(true) - $startTime));
if (!isset($GLOBALS["CURRENT_CONNECTOR"]) || (!file_exists($enginePath."connector".DIRECTORY_SEPARATOR."{$GLOBALS["CURRENT_CONNECTOR"]}.php"))) {
    die("Choose a LLM model and connector.".PHP_EOL);

} else {
    //$GLOBALS["CURRENT_CONNECTOR"]="koboldcpp";
    //require($enginePath."connector".DIRECTORY_SEPARATOR."{$GLOBALS["CURRENT_CONNECTOR"]}.php");
    require($enginePath."connector".DIRECTORY_SEPARATOR."{$GLOBALS["CURRENT_CONNECTOR"]}.php");

    $COMMAND_PROMPT='';
    $db = new sql();
    $res=$db->fetchAll("select max(gamets) as last_gamets from eventlog");
    $last_gamets=$res[0]["last_gamets"]+1;
    $gameRequest=["inputtext","0",$last_gamets,$argv[1]];
    $request=$argv[1];
    require($enginePath.DIRECTORY_SEPARATOR."prompt.includes.php");

    
    $lastNDataForContext = (isset($GLOBALS["CONTEXT_HISTORY"])) ? ($GLOBALS["CONTEXT_HISTORY"]) : "25";

// Historic context (last dialogues, events,...)
$contextDataHistoric = DataLastDataExpandedFor("", -50);
$contextDataWorld = DataLastInfoFor("", -2);

$contextDataFull = array_merge($contextDataWorld, $contextDataHistoric);


$head[] = array('role' => 'system', 'content' =>  $GLOBALS["PROMPT_HEAD"] . $GLOBALS["HERIKA_PERS"] . $GLOBALS["COMMAND_PROMPT"]);
//$prompt[] = array('role' => 'user', 'content' => $argv[1]);
$prompt[] = array('role' => 'user', 'content' => $request);

if (isset($PROMPTS[$gameRequest[0]]["player_request"])) {
		$request = selectRandomInArray($PROMPTS[$gameRequest[0]]["cue"]); // Add support for arrays here	
		$gameRequest[3]=selectRandomInArray($PROMPTS[$gameRequest[0]]["player_request"]);	// Overwrite
	}
else {
		if (isset($PROMPTS[$gameRequest[0]]["cue"]))
			$request = selectRandomInArray($PROMPTS[$gameRequest[0]]["cue"]); // Add support for arrays here	
}

	$prompt[] = array('role' => 'user', 'content' => $request);

    $contextData = array_merge($head, ($contextDataFull), $prompt);
    

    error_log(__LINE__." " .(microtime(true) - $startTime));    
    
    $connectionHandler=new connector();
    $connectionHandler->open($contextData,[]);
    
    error_log(__LINE__." " .(microtime(true) - $startTime));
    
    //print_r($contextData);
    error_log("FUNCTIONS_ARE_ENABLED $FUNCTIONS_ARE_ENABLED");
    $buffer="";
    $totalBuffer="";
    $breakFlag=false;
    
    $totalProcessedData="";
    
     while (true) {

        if ($breakFlag) {
            break;
        }

        $buffer.=$connectionHandler->process();
       

        
        if ($connectionHandler->isDone()) {
            $breakFlag=true;
        }
        
        $position = findDotPosition($buffer);

        if ($position !== false) {
            $extractedData = substr($buffer, 0, $position + 1);
            $remainingData = substr($buffer, $position + 1);
            $sentences=split_sentences_stream(cleanResponse($extractedData));
            $GLOBALS["DEBUG_DATA"]["response"][]=["raw"=>$buffer,"processed"=>implode("|", $sentences)];
            $GLOBALS["DEBUG_DATA"]["perf"][]=(microtime(true) - $startTime)." secs in openai stream";
            
            if ($gameRequest[0] != "diary") {
                error_log("[PRE-TTS] Line output:".(microtime(true) - $startTime));
                echo "\033[1;33m";
                returnLines($sentences);
                echo "\033[0m";
                error_log("[POST-TTS] Line output:".(microtime(true) - $startTime));
            } else {
                $talkedSoFar[md5(implode(" ", $sentences))]=implode(" ", $sentences);
            }

            //echo "$extractedData  # ".(microtime(true)-$startTime)."\t".strlen($finalData)."\t".PHP_EOL;  // Output
            $totalProcessedData.=$extractedData;
            $extractedData="";
            $buffer=$remainingData;

        }
     }
     
     if (trim($buffer)) {
        $sentences=split_sentences_stream(cleanResponse(trim($buffer)));
        $GLOBALS["DEBUG_DATA"]["response"][]=["raw"=>$buffer,"processed"=>implode("|", $sentences)];
        $GLOBALS["DEBUG_DATA"]["perf"][]=(microtime(true) - $startTime)." secs in openai stream";
        if ($gameRequest[0] != "diary") {
            error_log("[PRE-TTS] Line output:".(microtime(true) - $startTime));
            echo "\033[1;33m";
            returnLines($sentences);
            error_log("[POST-TTS] Line output:".(microtime(true) - $startTime));
            echo "\033[0m2";
        } else {
            $talkedSoFar[md5(implode(" ", $sentences))]=implode(" ", $sentences);
        }
        $totalBuffer.=trim($buffer);
        $totalProcessedData.=trim($buffer);
    }
    
     error_log("End:" .(microtime(true) - $startTime));

     $actions=$connectionHandler->processActions();
     //echo PHP_EOL."<$buffer>".PHP_EOL;
     echo "\033[0;32m";
     echo implode("\r\n", $actions);
     echo "\033[0m2";
     $connectionHandler->close();
     //print_r($GLOBALS["DEBUG_DATA"]["koboldcpp_prompt"]);
     
   
}


?>
