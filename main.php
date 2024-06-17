<?php


/* Definitions and main includes */
error_reporting(E_ALL);

define("MAXIMUM_SENTENCE_SIZE", 125);
define("MINIMUM_SENTENCE_SIZE", 15);

date_default_timezone_set('Europe/Madrid');

$GLOBALS["AVOID_TTS_CACHE"]=true;

$path = dirname((__FILE__)) . DIRECTORY_SEPARATOR;
require_once($path . "conf".DIRECTORY_SEPARATOR."conf.php");
require_once($path . "lib" .DIRECTORY_SEPARATOR."model_dynmodel.php");
require_once($path . "lib" .DIRECTORY_SEPARATOR."{$GLOBALS["DBDRIVER"]}.class.php");
require_once($path . "lib" .DIRECTORY_SEPARATOR."data_functions.php");
require_once($path . "lib" .DIRECTORY_SEPARATOR."chat_helper_functions.php");
require_once($path . "lib" .DIRECTORY_SEPARATOR."memory_helper_vectordb_txtai.php");


$db = new sql();

// PARSE GET RESPONSE into $gameRequest

if (php_sapi_name()=="cli") {
    // You can run this script directly with php: main.php "Player text"

    $res=$db->fetchAll("select max(gamets)+1 as gamets,max(ts)+1 as ts  from eventlog");
    
    $receivedData = "inputtext|{$res[0]["ts"]}|{$res[0]["gamets"]}|{$GLOBALS["PLAYER_NAME"]}: {$argv[1]}";
    //$receivedData = "{$argv[1]}";
    $_GET["profile"]=$argv[2];
    error_reporting(E_ALL);
    $FUNCTIONS_ARE_ENABLED=true;


} else {

    //$receivedData = base64_decode($_GET["DATA"]);
    //base64 string has '+' chars. THis conflicts with urldecode, so $_GET["DATA"] will get bullshit.
    if (strpos($_SERVER["QUERY_STRING"],"&")===false)
        $receivedData = mb_scrub(base64_decode(substr($_SERVER["QUERY_STRING"],5)));
    else
        $receivedData = mb_scrub(base64_decode(substr($_SERVER["QUERY_STRING"],5,strpos($_SERVER["QUERY_STRING"],"&")-4)));

    //error_log($receivedData." ".$_GET["profile"]);

}


// Profile selection
if (isset($_GET["profile"])) {
    if (file_exists($path . "conf".DIRECTORY_SEPARATOR."conf_{$_GET["profile"]}.php")) {
       // error_log("PROFILE: {$_GET["profile"]}");
        require_once($path . "conf".DIRECTORY_SEPARATOR."conf_{$_GET["profile"]}.php");

    }
    $GLOBALS["CURRENT_CONNECTOR"]=DMgetCurrentModel();

}

// End of profile selection


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

// SCRIPT LINE QUEUE
$GLOBALS["SCRIPTLINE_EXPRESSION"]="";
$GLOBALS["SCRIPTLINE_LISTENER"]="";
$GLOBALS["SCRIPTLINE_ANIMATION"]="";

/**********************
MAIN FLOW
***********************/

$startTime = microtime(true);

//error_log("TRACE:\t".__LINE__. "\t".__FILE__.":\t".(microtime(true) - $startTime));

// Lock to avoid TTS hangs

$semaphoreKey =abs(crc32(__FILE__));
$semaphore = sem_get($semaphoreKey);
while (sem_acquire($semaphore,true)!=true)  {
    usleep(1000);
}




//error_log("TRACE:\t".__LINE__. "\t".__FILE__.":\t".(microtime(true) - $startTime));

$gameRequest = explode("|", $receivedData);
foreach ($gameRequest as $i => $ele) {
    $gameRequest[$i] = trim(preg_replace('/\s\s+/', ' ', preg_replace('/\'/m', "'", $ele)));
    //$gameRequest[$i] = trim(preg_replace('/\s\s+/', ' ', preg_replace('/\'/m', "''", $ele)));
    $gameRequest[$i]=strtr($gameRequest[$i],["#HERIKA_NPC1#"=>$GLOBALS["HERIKA_NAME"]]);
}


$gameRequest[0] = strtolower($gameRequest[0]); // Who put 'diary' uppercase?

// $gameRequest = type of message|localts|gamets|data

if ($gameRequest[0]=="diary") {
    $GLOBALS["CURRENT_CONNECTOR"]=$GLOBALS["CONNECTORS_DIARY"];
}


// Exit if only a event info log.
if (in_array($gameRequest[0],["info","infonpc","infoloc","chatme","chat","infoaction","death","goodnight","itemfound"])) {
    logEvent($gameRequest);
    die();
}


// Only allow functions when explicit request
if (!in_array($gameRequest[0],["inputtext","inputtext_s"])) {
    $FUNCTIONS_ARE_ENABLED=false;
}

// RECHAT
if (in_array($gameRequest[0],["rechat"])) {
    //RECHAT. Must choose if we continue conversation or no.
    $rechatHistory=DataRechatHistory();
    
    if (sizeof($rechatHistory)>($GLOBALS["RECHAT_H"]))    {   // TOO MUCH RECHAT
        error_log("Rechat discarded");
        die();
    }
    
    $rndNumber=rand(1,100);
    if ($rndNumber>($GLOBALS["RECHAT_P"]+0)) {              
        //die();
    } else
        die();
    
    $sqlfilter=" and type in ('prechat','inputtext','inputtext_s') ";  // Use prechat
    $FUNCTIONS_ARE_ENABLED=false;       // Enabling this can be funny

} else
    $sqlfilter=" and type<>'prechat' "; // Will dismiss prechat entries by default. prechat are LLM responses still not displayed in-game


// Non-LLM request handling.

require(__DIR__.DIRECTORY_SEPARATOR."processor".DIRECTORY_SEPARATOR."comm.php");
if ($MUST_END) {  // Shorthand for non LLM processing
    die('X-CUSTOM-CLOSE');
    
}


//error_log("TRACE:\t".__LINE__. "\t".__FILE__.":\t".(microtime(true) - $startTime));

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
    echo "{$GLOBALS["HERIKA_NAME"]}|command|StopAll@\r\n";
    @ob_flush();
    $alreadysent[md5("{$GLOBALS["HERIKA_NAME"]}|command|StopAll@\r\n")] = "Herika|command|StopAll@\r\n";
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
        die("\r\n");
}

$lastNDataForContext = (isset($GLOBALS["CONTEXT_HISTORY"])) ? ($GLOBALS["CONTEXT_HISTORY"]) : "25";

// Historic context (last dialogues, events,...)
$contextDataHistoric = DataLastDataExpandedFor("", $lastNDataForContext * -1,$sqlfilter);

// Info about location and npcs in first position
$contextDataWorld = DataLastInfoFor("", -2);

// Add current motto to COMMAND_PROMPT
if ($gameRequest[0] != "diary")
    $GLOBALS["COMMAND_PROMPT"].=DataGetCurrentTask();

// Offer memory in CONTEXT 
/*
if (!(isset($GLOBALS["MEMORY_INJECTION_ON"]) || (!$GLOBALS["MEMORY_INJECTION_ON"]))) {
    $GLOBALS["FEATURES"]["MEMORY_EMBEDDING"]["ENABLED"]=false;
}

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
*/   

// array('role' => $currentSpeaker, 'content' => implode("\n", $buffer));


if ($GLOBALS["FUNCTIONS_ARE_ENABLED"]) {
    $GLOBALS["COMMAND_PROMPT"].=$GLOBALS["COMMAND_PROMPT_FUNCTIONS"];
}

$contextDataFull = array_merge($contextDataWorld, $contextDataHistoric);

//error_log("TRACE:\t".__LINE__. "\t".__FILE__.":\t".(microtime(true) - $startTime));

requireFilesRecursively(__DIR__.DIRECTORY_SEPARATOR."ext".DIRECTORY_SEPARATOR,"context.php");

//error_log("TRACE:\t".__LINE__. "\t".__FILE__.":\t".(microtime(true) - $startTime));

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

    if (!empty($request)) {
        $prompt[] = array('role' => $LAST_ROLE, 'content' => $request);
    } else
        $prompt=[];

    $contextData = array_merge($head, ($contextDataFull), $prompt);
    
}

//error_log("*TRACE:\t".__LINE__. "\t".__FILE__.":\t".(microtime(true) - $startTime));
//returnLines(["Mmm..let me think"]);


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
            'url' => nl2br(("$receivedData in " . (microtime(true) - $startTime) . " secs "))


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
    if (is_array($actions) && (sizeof($actions)>0)) {

        $GLOBALS["DEBUG_DATA"]["response"][]=$actions;
        echo implode("\r\n", $actions);
        file_put_contents(__DIR__."/log/ouput_to_plugin.log",implode("\r\n", $actions), FILE_APPEND | LOCK_EX);

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
                'url' => nl2br(("$receivedData in " . (microtime(true) - $startTime) . " secs "))


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
                'url' => nl2br(("$receivedData in " . (microtime(true) - $startTime) . " secs "))


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
                'url' => nl2br(("$receivedData in " . (microtime(true) - $startTime) . " secs "))
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
                    'people' => $GLOBALS["HERIKA_NAME"],
                    'location' => "$location",
                    'sess' => 'pending',
                    'localts' => time()
                )
            );
            /*
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
            */
            // Log Memory also.
            if ((php_sapi_name()!="cli"))	
	            logMemory($GLOBALS["HERIKA_NAME"], $GLOBALS["HERIKA_NAME"],implode(" ", $talkedSoFar), $momentum, $gameRequest[2],$gameRequest[0]);
            returnLines([$RESPONSE_OK_NOTED]);

        } else {
            
            $lastPlayerLine=$db->fetchAll("SELECT data from eventlog where type in ('inputtext','inputtext_s') order by gamets desc limit 1 offset 0");
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
    //$contextData=[];       
    //$GLOBALS["PROMPT_HEAD"]="";
    //$GLOBALS["HERIKA_PERS"]="";
    //$GLOBALS["COMMAND_PROMPT"]="";
    //$contextData[]=array('role' => 'user', 'content' =>  "{$GLOBALS["HERIKA_NAME"]}: $herikaCondensedResponse");
    $contextData[]=array('role' => 'user', 'content' =>  
        "Analyze this sentence: '$lastUserSentence.\n".
        "\nChoose the command from this list:
        WalkTo,GatherInfo,NoCommand,OpenBackPack,Sleep,ContinueDialogue,OpenInventory,UpdatePersonalDiary (provide topic), UpdateQuestJournal (provide quest topic)\n
        Write command in this JSON object {\"command\":\"\",\"topic\":,\"priority\":\"now|future\",\"keyword\":\"\"}:"
    );

    //$GLOBALS["PROMPT_HEAD"]="Follow the logical steps and write the final answer in first place. Then explain reasoning.".
    //$GLOBALS["HERIKA_PERS"]="";
    //$GLOBALS["COMMAND_PROMPT"]="";
    
    //$GLOBALS["PLAYER_NAME"]="no name";
    
    $connectionHandler=new connector();
    $overrideParameters["MAX_TOKENS"]=50;
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
while(@ob_end_clean());
require(__DIR__.DIRECTORY_SEPARATOR."processor".DIRECTORY_SEPARATOR."postrequest.php");


?>
