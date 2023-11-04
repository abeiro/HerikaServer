<?php


/* Definitions and main includes */
error_reporting(E_ALL);

define("MAXIMUM_SENTENCE_SIZE", 125);
define("MINIMUM_SENTENCE_SIZE", 15);

date_default_timezone_set('Europe/Madrid');

$path = dirname((__FILE__)) . DIRECTORY_SEPARATOR;
require_once($path . "conf".DIRECTORY_SEPARATOR."conf.php");
require_once($path . "lib" .DIRECTORY_SEPARATOR."model_dynmodel.php");
require_once($path . "lib" .DIRECTORY_SEPARATOR."{$GLOBALS["DBDRIVER"]}.class.php");
require_once($path . "lib" .DIRECTORY_SEPARATOR."data_functions.php");
require_once($path . "lib" .DIRECTORY_SEPARATOR."chat_helper_functions.php");
require_once($path . "lib" .DIRECTORY_SEPARATOR."memory_helper_vectordb.php");
require_once($path . "lib" .DIRECTORY_SEPARATOR."memory_helper_embeddings.php");

$db = new sql();

if (!isset($FUNCTIONS_ARE_ENABLED)) {
    $FUNCTIONS_ARE_ENABLED=false;
}

while (@ob_end_clean())	;
ignore_user_abort(true);
set_time_limit(1200);

$momentum=time();

// Array with sentences talked so far
$talkedSoFar = array();

// Array with sentences sent so far
$alreadysent = array();

// Array with parameters to override
$overrideParameters=array();

$ERROR_TRIGGERED=false;


$LAST_ROLE="user";


/**********************
MAIN FLOW
***********************/

$startTime = microtime(true);

// PARSE GET RESPONSE into $gameRequest

if (php_sapi_name()=="cli") {
    // You can run this script directly with php: main.php "Player text"
    $receivedData = "inputtext|108826400925500|770416256|{$GLOBALS["PLAYER_NAME"]}: {$argv[1]}";
    $receivedData = "{$argv[1]}";
    error_reporting(E_ALL);


} else {

    //$receivedData = base64_decode($_GET["DATA"]);
    //base64 string has '+' chars. THis conflicts with urldecode, so $_GET["DATA"] will get bullshit.
    $receivedData = mb_scrub(base64_decode(substr($_SERVER["QUERY_STRING"],5)));

}


$gameRequest = explode("|", $receivedData);
foreach ($gameRequest as $i => $ele) {
    $gameRequest[$i] = trim(preg_replace('/\s\s+/', ' ', preg_replace('/\'/m', "''", $ele)));
}


$gameRequest[0] = strtolower($gameRequest[0]); // Who put 'diary' uppercase?

// $gameRequest = type of message|localts|gamets|data

// Exit if only a event info log.
if (in_array($gameRequest[0],["info","infonpc","infoloc","chatme","chat","infoaction","death","goodnight"])) {
    logEvent($gameRequest);
    die();
}

if (!in_array($gameRequest[0],["inputtext","inputtext_s"])) {
    $FUNCTIONS_ARE_ENABLED=false;
}


// Non-LLM request handling.

require(__DIR__.DIRECTORY_SEPARATOR."processor".DIRECTORY_SEPARATOR."comm.php");
if ($MUST_END) {  // Shorthand for non LLM processing
    die('X-CUSTOM-CLOSE');
    
}



/**********************
 CONTEXT DATA BUILDING
***********************/

// Include prompts, command prompts and functions.
require(__DIR__.DIRECTORY_SEPARATOR."prompt.includes.php");

// Take care of override request if needed..
require(__DIR__.DIRECTORY_SEPARATOR."processor".DIRECTORY_SEPARATOR."request.php");


/*
 Safe stop
*/

if (stripos($gameRequest[3], "stop") !== false) {
    echo "Herika|command|StopAll@\r\n";
    @ob_flush();
    $alreadysent[md5("Herika|command|StopAll@\r\n")] = "Herika|command|StopAll@\r\n";
}


/// LOG INTO DB. Will use this later.
if ($gameRequest[0] != "diary") {
    $db->insert(
        'eventlog',
        array(
            'ts' => $gameRequest[1],
            'gamets' => $gameRequest[2],
            'type' => $gameRequest[0],
            'data' => ($gameRequest[3]),
            'sess' => (php_sapi_name()=="cli")?'cli':'web',
            'localts' => time()
        )
    );

}

if (isset($GLOBALS["PROMPTS"][$gameRequest[0]]["extra"]["dontuse"])) {
    if ($GLOBALS["PROMPTS"][$gameRequest[0]]["extra"]["dontuse"])
        die();
}

$lastNDataForContext = (isset($GLOBALS["CONTEXT_HISTORY"])) ? ($GLOBALS["CONTEXT_HISTORY"]) : "25";

// Historic context (last dialogues, events,...)
$contextDataHistoric = DataLastDataExpandedFor("", $lastNDataForContext * -1);

// Info about location and npcs in first position
$contextDataWorld = DataLastInfoFor("", -2);

// Add current motto to COMMAND_PROMPT
if ($gameRequest[0] != "diary")
    $GLOBALS["COMMAND_PROMPT"].=DataGetCurrentTask();

// Offer memory in CONTEXT 
$memoryInjection=offerMemory($gameRequest, $DIALOGUE_TARGET);
if ($memoryInjection) {
    
    //$memoryInjectionCtx[]= array('role' => 'user', 'content' => $gameRequest[3]);
    $memoryInjectionCtx[]= array('role' => 'user', 'content' => "#MEMORY: [$memoryInjection]");
    $contextDataHistoric=array_merge($memoryInjectionCtx,$contextDataHistoric);

    if (isset($GLOBALS["USE_MEMORY_STATEMENT_DELETE"]) && $GLOBALS["USE_MEMORY_STATEMENT_DELETE"] ) {
        $request=str_replace($GLOBALS["MEMORY_STATEMENT"],"",$request);
    }
    //$GLOBALS["COMMAND_PROMPT"].="'{$gameRequest[3]}'\n{$GLOBALS["HERIKA_NAME"]}):$memoryInjection\n";
    
} else {
    
    $request=str_replace($GLOBALS["MEMORY_STATEMENT"],"",$request);
        
}
   

// array('role' => $currentSpeaker, 'content' => implode("\n", $buffer));


if ($GLOBALS["FUNCTIONS_ARE_ENABLED"]) {
    $GLOBALS["COMMAND_PROMPT"].=$GLOBALS["COMMAND_PROMPT_FUNCTIONS"];
}

$contextDataFull = array_merge($contextDataWorld, $contextDataHistoric);


requireFilesRecursively(__DIR__.DIRECTORY_SEPARATOR."ext".DIRECTORY_SEPARATOR,"context.php");

$head[] = array('role' => 'system', 'content' =>  $GLOBALS["PROMPT_HEAD"] . $GLOBALS["HERIKA_PERS"] . $GLOBALS["COMMAND_PROMPT"]);


/**********************
CALL BUILDING
***********************/

if ($gameRequest[0] == "funcret") {

    $prompt[] = array('role' => 'assistant', 'content' => $request);

    // Manage function stuff
    // $contextData will be populated

    require(__DIR__.DIRECTORY_SEPARATOR."processor".DIRECTORY_SEPARATOR."funcret.php");


} elseif ((strpos($gameRequest[0], "chatnf")!==false)) {

    // Won't use  functions.
    // $prompt and $contextData will be created
    $prompt[] = array('role' => $LAST_ROLE, 'content' => $request);

    $contextData = array_merge($head, ($contextDataFull), $prompt);


}  else {

    $prompt[] = array('role' => $LAST_ROLE, 'content' => $request);

    $contextData = array_merge($head, ($contextDataFull), $prompt);
    
}

/**********************
CALL INITIALIZATION
***********************/

if (!isset($GLOBALS["CURRENT_CONNECTOR"]) || (!file_exists(__DIR__.DIRECTORY_SEPARATOR."connector".DIRECTORY_SEPARATOR."{$GLOBALS["CURRENT_CONNECTOR"]}.php"))) {
    die("{$GLOBALS["HERIKA_NAME"]}|AASPGQuestDialogue2Topic1B1Topic|I'm mindless. Choose a LLM model and connector.".PHP_EOL);

} else {

    require(__DIR__.DIRECTORY_SEPARATOR."connector".DIRECTORY_SEPARATOR."{$GLOBALS["CURRENT_CONNECTOR"]}.php");

    $connectionHandler=new connector();
    $connectionHandler->open($contextData,$overrideParameters);
}

if ($connectionHandler->primary_handler === false) {

    $db->insert(
        'log',
        array(
            'localts' => time(),
            'prompt' => nl2br((json_encode($GLOBALS["DEBUG_DATA"], JSON_PRETTY_PRINT))),
            'response' => ((print_r(error_get_last(), true))),
            'url' => nl2br(("$receivedData in " . (time() - $startTime) . " secs "))


        )
    );
    returnLines([$GLOBALS["ERROR_OPENAI"]]);
    
    $ERROR_TRIGGERED=true;
    @ob_end_flush();

    error_log(print_r(error_get_last(), true));

} else {

    // Read and process the response line by line
    $buffer="";
    $totalBuffer="";
    $breakFlag=false;
    $lineCounter=0;
    $fullContent="";
    $totalProcessedData="";
    $numOutputTokens = 0;

    while (true) {

        if ($breakFlag) {
            break;
        }


        $buffer.=$connectionHandler->process();
        $totalBuffer.=$buffer;


        if ($connectionHandler->isDone()) {
            $breakFlag=true;
        }

        $buffer=strtr($buffer, array("\""=>"",".)"=>")."));

        if (strlen($buffer)<MINIMUM_SENTENCE_SIZE) {	// Avoid too short buffers
            continue;
        }

        $position = findDotPosition($buffer);

        if ($position !== false) {
            $extractedData = substr($buffer, 0, $position + 1);
            $remainingData = substr($buffer, $position + 1);
            $sentences=split_sentences_stream(cleanResponse($extractedData));
            $GLOBALS["DEBUG_DATA"]["response"][]=["raw"=>$buffer,"processed"=>implode("|", $sentences)];
            $GLOBALS["DEBUG_DATA"]["perf"][]=(microtime(true) - $startTime)." secs in openai stream";

            if ($gameRequest[0] != "diary") {
                returnLines($sentences);
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
            returnLines($sentences);
        } else {
            $talkedSoFar[md5(implode(" ", $sentences))]=implode(" ", $sentences);
        }
        $totalBuffer.=trim($buffer);
        $totalProcessedData.=trim($buffer);
    }


    $actions=$connectionHandler->processActions();
    if (sizeof($actions)>0) {

        $GLOBALS["DEBUG_DATA"]["response"][]=$actions;
        echo implode("\r\n", $actions);
    }
    $connectionHandler->close();
    //fwrite($fileLog, $totalBuffer . PHP_EOL); // Write the line to the file with a line break // DEBUG CODE


}

if (sizeof($talkedSoFar) == 0) {
    if (sizeof($alreadysent) > 0) { // AI only issued commands

        $db->insert(
            'log',
            array(
                'localts' => time(),
                'prompt' => nl2br((json_encode($GLOBALS["DEBUG_DATA"], JSON_PRETTY_PRINT))),
                'response' => (print_r($alreadysent, true)),
                'url' => nl2br(("$receivedData in " . (time() - $startTime) . " secs "))


            )
        );
        // Should choose wich events she tends to call function without response.
        //returnLines(["Sure thing!"]);

    } else { // Fail request? or maybe an invalid command was issued

        //returnLines(array($randomSentence));
        $db->insert(
            'log',
            array(
                'localts' => time(),
                'prompt' => nl2br((json_encode($GLOBALS["DEBUG_DATA"], JSON_PRETTY_PRINT))),
                'response' => (print_r($alreadysent, true)),
                'url' => nl2br(("$receivedData in " . (time() - $startTime) . " secs "))


            )
        );

    }
} else {

    if (sizeof($alreadysent) > 0) { // AI only issued commands
        $db->insert(
            'log',
            array(
                'localts' => time(),
                'prompt' => nl2br((json_encode($GLOBALS["DEBUG_DATA"], JSON_PRETTY_PRINT))),
                'response' => (print_r($alreadysent, true)),
                'url' => nl2br(("$receivedData in " . (time() - $startTime) . " secs "))
            )
        );
    }

    if (!$ERROR_TRIGGERED) {
        if ($gameRequest[0] == "diary") {
            $topic=DataLastKnowDate();
            $location=DataLastKnownLocation();
            $db->insert(
                'diarylog',
                array(
                    'ts' => $gameRequest[1],
                    'gamets' => $gameRequest[2],
                    'topic' => "$topic",
                    'content' => (implode(" ", $talkedSoFar)),
                    'tags' => "Pending",
                    'people' => "Pending",
                    'location' => "$location",
                    'sess' => 'pending',
                    'localts' => time()
                )
            );
            
            $db->insert(
			'diarylogv2',
			array(
				'topic' => ($topic),
				'content' => (implode(" ", $talkedSoFar)),
				'tags' => "Pending",
                'people' => "Pending",
                'location' => "$location"
			)
		);
            
            // Log Memory also.
            if ((php_sapi_name()!="cli"))	
	            logMemory($GLOBALS["HERIKA_NAME"], $GLOBALS["HERIKA_NAME"],implode(" ", $talkedSoFar), $momentum, $gameRequest[2]);
            returnLines([$RESPONSE_OK_NOTED]);

        } else {
            
            $lastPlayerLine=$db->fetchAll("SELECT data from eventlog where type in ('inputtext','inputtext_s') order by gamets desc limit 0,1");
            if (php_sapi_name()!="cli")	{
                if (in_array($gameRequest[0],["inputtext","inputtext_s"]))
                    logMemory($GLOBALS["HERIKA_NAME"], $GLOBALS["PLAYER_NAME"], "{$lastPlayerLine[0]["data"]} \n\r {$GLOBALS["HERIKA_NAME"]}:".implode(" ", $talkedSoFar), $momentum, $gameRequest[2]);
                else
                    logMemory($GLOBALS["HERIKA_NAME"], $GLOBALS["PLAYER_NAME"], "{$GLOBALS["HERIKA_NAME"]}:".implode(" ", $talkedSoFar), $momentum, $gameRequest[2]);
            }
        }
    }
}


// EXPERIMENTAL FEATURE
if ($FEATURES["EXPERIMENTAL"]["KOBOLDCPP_ACTIONS"] && ((DMgetCurrentModel()=="koboldcpp")||(DMgetCurrentModel()=="llamacpp")) && (in_array($gameRequest[0],["inputtext","inputtext_s"])) ) {
    
    $herikaCondensedResponse=implode(" ",$talkedSoFar);

    array_pop($contextData);
    $lastElementByUser=end($contextData);
    $lastUserSentence=str_replace("-$DIALOGUE_TARGET", "", $lastElementByUser["content"]);
            
    $contextData[]=array('role' => 'user', 'content' =>  "{$GLOBALS["HERIKA_NAME"]}: $herikaCondensedResponse");
    $contextData[]=array('role' => 'user', 'content' =>  
        "Analyze this sentence: '$lastUserSentence'. ".
        "If sentence is a question, then answer is NoOp().".
        "If sentence is a trade/exchange request then answer is ExchangeItems().".
        "If sentence explicitly describes a new plan, then answer is SetCurrentPlan(plan description).".
        "Else, if nothing is true, then answer is NoOp().".
        "Write correct answer:"
    );

    //$GLOBALS["PROMPT_HEAD"]="Follow the logical steps and write the final answer in first place. Then explain reasoning.".
    //$GLOBALS["HERIKA_PERS"]="";
    //$GLOBALS["COMMAND_PROMPT"]="";
    
    //$GLOBALS["PLAYER_NAME"]="no name";
    
    $connectionHandler=new connector();
    $overrideParameters["MAX_TOKENS"]=25;
    $overrideParameters["GRAMMAR_ACTIONS"]=true;
    
    $connectionHandler->open($contextData,$overrideParameters);
      
    $buffer="";
    $totalBuffer="";
    $breakFlag=false;
    
    $connectionHandler->_functionMode=true;
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
   
   
    $actions=$connectionHandler->processActions();
    if (sizeof($actions)>0) {

        $GLOBALS["DEBUG_DATA"]["response"][]=$actions;
        echo implode("\r\n", $actions);
    }

}

echo 'X-CUSTOM-CLOSE';

if (php_sapi_name()=="cli") {
    echo PHP_EOL;
    file_put_contents("log/debug_comm_".basename(__FILE__).".log", print_r($GLOBALS["DEBUG_DATA"], true));

    $db->delete("eventlog", "sess='cli'");

}


// POST PROCESS TASKS

require(__DIR__.DIRECTORY_SEPARATOR."processor".DIRECTORY_SEPARATOR."postrequest.php");


?>
