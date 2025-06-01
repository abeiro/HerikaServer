<?php

/* Definitions and main includes */
error_reporting(E_ALL);

@define("STOPALL_MAGIC_WORD", "/wake up/i");

@define("MAXIMUM_SENTENCE_SIZE", 125);
@define("MINIMUM_SENTENCE_SIZE", 50);

date_default_timezone_set('Europe/Madrid');

$GLOBALS["AVOID_TTS_CACHE"]=true;
$GLOBALS["CHIM_NO_EXAMPLES"]=true; // When no assistant entry in history, will try ti provide a bogus example.

// Cooldown for some actions
$COOLDOWNMAP=[];

$path = dirname((__FILE__)) . DIRECTORY_SEPARATOR;
require($path . "conf".DIRECTORY_SEPARATOR."conf.php");
require_once($path . "lib" .DIRECTORY_SEPARATOR."auditing.php");
require_once($path . "lib" .DIRECTORY_SEPARATOR."model_dynmodel.php");
require_once($path . "lib" .DIRECTORY_SEPARATOR."{$GLOBALS["DBDRIVER"]}.class.php");
require_once($path . "lib" .DIRECTORY_SEPARATOR."minimet5_service.php");
require_once($path . "lib" .DIRECTORY_SEPARATOR."data_functions.php");
require_once($path . "lib" .DIRECTORY_SEPARATOR."chat_helper_functions.php");
require_once($path . "lib" .DIRECTORY_SEPARATOR."memory_helper_vectordb.php");
require_once($path . "lib" .DIRECTORY_SEPARATOR."utils_game_timestamp.php");
require_once($path . "lib" .DIRECTORY_SEPARATOR."logger.php"); 
requireFilesRecursively(__DIR__.DIRECTORY_SEPARATOR."ext".DIRECTORY_SEPARATOR,"globals.php");


// PARSE GET RESPONSE into $gameRequest
$cooldownPeriod = 600;


if (php_sapi_name()=="cli" && !getenv('PHPUNIT_TEST')) {
    // You can run this script directly with php: main.php "Player text"
    $GLOBALS["db"] = new sql();

    $latsRid=$db->fetchAll("select *  from eventlog order by rowid desc LIMIT 1 OFFSET 0");
    $res=$db->fetchAll("select max(gamets)+1 as gamets,max(ts)+1 as ts  from eventlog where rowid={$latsRid[0]["rowid"]}");
    $res[0]["ts"]=$res[0]["ts"]+1;
    $res[0]["gamets"]=$res[0]["gamets"]+1;
        
    
        
    $receivedData = "inputtext|{$res[0]["ts"]}|{$res[0]["gamets"]}|{$GLOBALS["PLAYER_NAME"]}: {$argv[1]}";
    $_GET["profile"]=$argv[2];
    $GLOBALS["FUNCTIONS_ARE_ENABLED"]=true;

    unset($GLOBALS["db"]);
} else {

    //$receivedData = base64_decode($_GET["DATA"]);
    //base64 string has '+' chars. THis conflicts with urldecode, so $_GET["DATA"] will get bullshit.
    if (strpos($_SERVER["QUERY_STRING"],"&")===false)
        $receivedData = mb_scrub(base64_decode(substr($_SERVER["QUERY_STRING"],5)));
    else
        $receivedData = mb_scrub(base64_decode(substr($_SERVER["QUERY_STRING"],5,strpos($_SERVER["QUERY_STRING"],"&")-4)));

    //error_log($receivedData." ".$_GET["profile"]);

}




if (!isset($FUNCTIONS_ARE_ENABLED)) {
    $FUNCTIONS_ARE_ENABLED=false;
}



while (!getenv('PHPUNIT_TEST') && ob_get_length() && ob_end_clean())	;
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

$GLOBALS["TTS_FFMPEG_FILTERS"]=[];

/**********************
MAIN FLOW
***********************/

$gameRequest = explode("|", $receivedData);


$startTime = microtime(true);
//error_log("Audit run ID: " . $GLOBALS["AUDIT_RUNID"]. " ({$gameRequest[0]}) started: ".$startTime);
$GLOBALS["AUDIT_RUNID_REQUEST"]=$gameRequest[0];

$gameRequest[0] = strtolower($gameRequest[0]); // Who put 'diary' uppercase?


// Lock to avoid TTS hangs
/*if (($gameRequest[0]!="updateprofile")&&($gameRequest[0]!="diary")&&($gameRequest[0]!="_quest")&&($gameRequest[0]!="setConf")&&($gameRequest[0]!="request")
/*    &&($gameRequest[0]!="addnpc")&&($gameRequest[0]!="_speech")) {
*/
$db = new sql();

requireFilesRecursively(__DIR__.DIRECTORY_SEPARATOR."ext".DIRECTORY_SEPARATOR,"preprocessing.php");
if (in_array($gameRequest[0],["inputtext","inputtext_s","ginputtext","ginputtext_s","instruction","init"])) {
    $GLOBALS["ADD_PLAYER_BIOS"]=true;
    // $db = new sql();
    $db->insert(
        'eventlog',
        array(
            'ts' => $gameRequest[1],
            'gamets' => $gameRequest[2],
            'type' => "user_input",
            'data' => $gameRequest[0],
            'sess' => 'pending',
            'localts' => time(),
            'people'=> '',
            'location'=>'',
            'party'=>''
        )
    );
    // unset($db);
}


$fast_commands = ["addnpc","updateprofile","diary","_quest","setconf","request","_speech","infoloc","infonpc","infonpc_close",
    "infoaction","status_msg","delete_event","itemfound","_questdata","_uquest","location","_questreset","chat","bleedout","waitstart","waitstop",
    "util_location_name","spellcast","npcspellcast"];

if (isset($GLOBALS["external_fast_commands"])) {
    $fast_commands = array_merge($fast_commands, $GLOBALS["external_fast_commands"]);
}

if (!in_array($gameRequest[0],$fast_commands)) {
    $semaphoreKey =abs(crc32(__FILE__));
    $semaphore = sem_get($semaphoreKey);
    
    while (sem_acquire($semaphore,true)!=true)  {
        //error_log("Audit: Waiting for lock: {$gameRequest[0]}");
        usleep(1000);
    }
    Logger::info("Audit:Lock acquired by {$gameRequest[0]}");
} 

// adnpc has its custom semaphore, as it write files
if (in_array($gameRequest[0],["addnpc"])) {
    $semaphoreKey2 =abs(crc32(__FILE__."_secondary"));
    $semaphore2 = sem_get($semaphoreKey2);
    while (sem_acquire($semaphore2,true)!=true)  {
        usleep(100);
    }
} 


if (($gameRequest[0]=="playerinfo")||(($gameRequest[0]=="newgame"))) {
    sleep(1);   // Give time to populate data
}


if (($gameRequest[0]=="delete_event")) {
    // Do this ASAP
    $datacn=$db->escape($gameRequest[3]);
    $db->delete("eventlog","type in ('chat','prechat') and data like '%$datacn%' and localts>".(time()- 120));
    // audit_log(__FILE__);
    die();
}

// Player rewrite

if (in_array($gameRequest[0],["inputtext","inputtext_s","ginputtext","ginputtext_s"]) && isset($GLOBALS["PLAYER_REESPECH"]) && $GLOBALS["PLAYER_REESPECH"]) {
    // Use preg_replace to remove the name and colon before the dialogue
    $cleaned_player_dialogue = preg_replace('/^[^:]+:/', '', $gameRequest[3]);
    error_log($cleaned_player_dialogue);
    if (strpos($cleaned_player_dialogue,"**")===0) {
        // If player speech starts with **
        error_log("Overwritting user prompt $cleaned_player_dialogue");
        function getBaseUrlForSpeech(): string {
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
            $host = $_SERVER['HTTP_HOST'];
            return $protocol . $host;
        }

        $newSpeech=file_get_contents(getBaseUrlForSpeech()."/HerikaServer/player_rewrite.php?speech=".urlencode($cleaned_player_dialogue));
        $gameRequest[3]="{$GLOBALS["PLAYER_NAME"]}:$newSpeech";

    }
}

// Profile selection
if (isset($_GET["profile"])) {
    
    $OVERRIDES["BOOK_EVENT_ALWAYS_NARRATOR"]=$GLOBALS["BOOK_EVENT_ALWAYS_NARRATOR"];
    $OVERRIDES["MINIME_T5"]=$GLOBALS["MINIME_T5"];
    $OVERRIDES["STTFUNCTION"]=$GLOBALS["STTFUNCTION"];
    $OVERRIDES["TTSFUNCTION_PLAYER"]=$GLOBALS["TTSFUNCTION_PLAYER"];
    $OVERRIDES["TTSFUNCTION_PLAYER_VOICE"]=$GLOBALS["TTSFUNCTION_PLAYER_VOICE"];
    $OVERRIDES["TTSFUNCTION_PLAYER_LANGUAGE"]=$GLOBALS["TTSFUNCTION_PLAYER_LANGUAGE"];

    //$OVERRIDES["PROMPT_HEAD"]=$GLOBALS["PROMPT_HEAD"];
    
    if (file_exists($path . "conf".DIRECTORY_SEPARATOR."conf_{$_GET["profile"]}.php")) {
       // error_log("PROFILE: {$_GET["profile"]}");
        require($path . "conf".DIRECTORY_SEPARATOR."conf_{$_GET["profile"]}.php");

    } else {
        // error_log(__FILE__.". Using default profile because GET PROFILE NOT EXISTS");
    }
    
    $GLOBALS["BOOK_EVENT_ALWAYS_NARRATOR"]=$OVERRIDES["BOOK_EVENT_ALWAYS_NARRATOR"];
    $GLOBALS["MINIME_T5"]=$OVERRIDES["MINIME_T5"];
    $GLOBALS["STTFUNCTION"]=$OVERRIDES["STTFUNCTION"];
    $GLOBALS["TTSFUNCTION_PLAYER"]=$OVERRIDES["TTSFUNCTION_PLAYER"];
    $GLOBALS["TTSFUNCTION_PLAYER_VOICE"]=$OVERRIDES["TTSFUNCTION_PLAYER_VOICE"];
    $GLOBALS["TTSFUNCTION_PLAYER_LANGUAGE"]=$OVERRIDES["TTSFUNCTION_PLAYER_LANGUAGE"];

    // $GLOBALS["PROMPT_HEAD"]=$OVERRIDES["PROMPT_HEAD"];
    // error_log("Using profile {$GLOBALS["TTSFUNCTION_PLAYER"]} {$_GET["profile"]} / ".$path . "conf".DIRECTORY_SEPARATOR."conf_{$_GET["profile"]}.php");
    
} else {
    //error_log(__FILE__.". Using default profile because NO GET PROFILE SPECIFIED");
    $GLOBALS["USING_DEFAULT_PROFILE"]=true;
}

/* *****
Player TTS

Player TTS. We overwrite some confs an then restore them.
*/
if (in_array($gameRequest[0],["inputtext","inputtext_s","ginputtext","ginputtext_s"]) && Translation::isSavePlayerTranslationEnabled()) {
   
    require(__DIR__."/processor/player_tts.php");
    
}


$GLOBALS["active_profile"]=md5($GLOBALS["HERIKA_NAME"]);
$GLOBALS["CURRENT_CONNECTOR"]=DMgetCurrentModel();


// End of profile selection

// This is the correct place, after arse $gameRequest and before starting to do substituions

if (($gameRequest[0]=="chatnf_book")&&($GLOBALS["BOOK_EVENT_ALWAYS_NARRATOR"])) {
    // When chatnf_book (make the AI to read a book), will override profile and will select default one
    Logger::info("Override conf with default");
    require($path . "conf".DIRECTORY_SEPARATOR."conf.php");
    $GLOBALS["CURRENT_CONNECTOR"]=DMgetCurrentModel();
}

foreach ($gameRequest as $i => $ele) {
    $gameRequest[$i] = trim(preg_replace('/\s\s+/', ' ', preg_replace('/\'/m', "'", $ele)));
    //$gameRequest[$i] = trim(preg_replace('/\s\s+/', ' ', preg_replace('/\'/m', "''", $ele)));
    $gameRequest[$i]=strtr($gameRequest[$i],["#HERIKA_NPC1#"=>$GLOBALS["HERIKA_NAME"]]);
}



// $gameRequest = type of message|localts|gamets|data

if ($gameRequest[0]=="diary") {
    $GLOBALS["CURRENT_CONNECTOR"]=$GLOBALS["CONNECTORS_DIARY"];
}






// Exit if only a event info log.
if (in_array($gameRequest[0],["info","infonpc","infonpc_close","infoloc","chatme","chat","infoaction","death","goodnight","itemfound",
    "travelcancel","infoplayer","infosave","status_msg","util_npcname","bleedout","spellcast","npcspellcast"])) {
    $gameRequest[3]=isset($gameRequest[3])?$gameRequest[3]:"";
    $lastInfoNpcData=$db->escape($gameRequest[3]);
    $lastlogEqual=$db->fetchAll("select count(*) as n from eventlog where type in ('infonpc','infoloc','infonpc_close') and data='$lastInfoNpcData' and localts>".(time()-5));
    if (is_array($lastlogEqual) && isset($lastlogEqual[0]) && ($lastlogEqual[0]["n"]>0)) {
        //error_log("Skipping {$gameRequest[0]}");
        die();
    }
    logEvent($gameRequest);
    die();
}

// Check if the gameRequest matches specific types
if (in_array($gameRequest[0], ["playerinfo", "newgame"])) {
    if (!$GLOBALS["NARRATOR_WELCOME"]) {
        logEvent($gameRequest);
        die();
    } else {
        // Fetch the last trigger timestamp from the database
        $narratorRecord = $GLOBALS["db"]->fetchAll("SELECT value FROM conf_opts WHERE id='NARRATOR_WELCOME_TIMESTAMP'");
        
        // Check if the timestamp exists in the database
        if (!empty($narratorRecord)) {
            $lastTrigger = (int) $narratorRecord[0]['value'];
            $timeElapsed = time() - $lastTrigger;

            if ($timeElapsed < $cooldownPeriod) {
                // Cooldown is still active, exit
                Logger::info("NARRATOR_WELCOME is on cooldown. Try again in " . ($cooldownPeriod - $timeElapsed) . " seconds.");
                die("X-CUSTOM-CLOSE");
            }
        }

        // Update the timestamp in the database to the current time
        $currentTimestamp = time();
        $GLOBALS["db"]->upsertRowOnConflict(
            "conf_opts",
            array(
                "id"    => "NARRATOR_WELCOME_TIMESTAMP",
                "value" => $currentTimestamp
            ),
            'id'
        );

        // If cooldown has passed, allow execution and disable functions
        $FUNCTIONS_ARE_ENABLED = false;
    }
}


// Fake entry to mark time passing when bored event
if (in_array($gameRequest[0],["bored"])) {
    $localGameRequest=$gameRequest;
    $localGameRequest[0]="infoaction";
    $localGameRequest[3].=". (Time passes without anyone in the group talking) ";
    logEvent($localGameRequest);
    $GLOBALS["ADD_PLAYER_BIOS"]=false;

    if ((isset($GLOBALS["BORED_EVENT_SERVERSIDE"])&&($GLOBALS["BORED_EVENT_SERVERSIDE"]))) {
        Logger::info("Redirecting bored event to rolemaster");
        `php service/manager.php rolemaster instruction ""`;
        die();

    }
}


// Only allow functions when explicit request
if (!in_array($gameRequest[0],["inputtext","inputtext_s","ginputtext","ginputtext_s","instruction","welcome"])) {
    $FUNCTIONS_ARE_ENABLED=false;
}

// Force actions when instruction issued
if (in_array($gameRequest[0],["instruction"])) {
    $FUNCTIONS_ARE_ENABLED=true;
    $gameRequest[3]=strtr($gameRequest[3],[$GLOBALS["PLAYER_NAME"].":"=>""]);// Remove 'Player:'
    $GLOBALS["ADD_PLAYER_BIOS"]=false;
}

if (in_array($gameRequest[0],["suggestion"])) {
    $FUNCTIONS_ARE_ENABLED=false;
    $gameRequest[3]=strtr($gameRequest[3],[$GLOBALS["PLAYER_NAME"].":"=>""]);// Remove 'Player:'
}

// Disable functions for The Narrator
if ($GLOBALS["HERIKA_NAME"]=="The Narrator") {
    $FUNCTIONS_ARE_ENABLED=false;
}

$GLOBALS["CACHE_PARTY"]=DataGetCurrentPartyConf();
$currentParty=json_decode($GLOBALS["CACHE_PARTY"],true);
if (is_array($currentParty)) {
    if (in_array($GLOBALS["HERIKA_NAME"],array_keys($currentParty))) {
        $GLOBALS["IS_NPC"]=false;
    } else
        $GLOBALS["IS_NPC"]=true;
} else
    $GLOBALS["IS_NPC"]=false;

// RECHAT PRE MANAGMENT



requireFilesRecursively(__DIR__.DIRECTORY_SEPARATOR."ext".DIRECTORY_SEPARATOR,"prerequest.php");

if (in_array($gameRequest[0],["rechat"]) ) {
    
    //RECHAT. Must choose if we continue conversation or no.

    $rechatHistory=DataRechatHistory();
    
    if (sizeof($rechatHistory)>(intval($GLOBALS["RECHAT_H"])))    {   // TOO MUCH RECHAT
        Logger::info("Rechat discarded, rechatHistory:".sizeof($rechatHistory).">{$GLOBALS["RECHAT_H"]}");
        // Lets try to summarize
        sem_release($semaphore);
        while(ob_get_length() && ob_end_clean());
        require(__DIR__.DIRECTORY_SEPARATOR."processor".DIRECTORY_SEPARATOR."postrequest.php");
        die();
    }
    
    $rndNumber = rand(1, 100);
    if ($rndNumber <= intval($GLOBALS["RECHAT_P"])) {
        // Process Oghma for rechat events using NPC's last dialogue
        if ($GLOBALS["MINIME_T5"] && isset($FEATURES["MISC"]["OGHMA_INFINIUM"]) && ($FEATURES["MISC"]["OGHMA_INFINIUM"])) {
                require(__DIR__."/processor/oghma.php"); // Process Oghma
        }
    }
    else{
        die();
    }
    
    
    if (sizeof($rechatHistory)>1) {
        // Lets make rechat wait a bit, so events while NPCs are speaking get into context// disabled if using new rechat fire event
        sem_release($semaphore);
        Logger::info("HOLDING RECHAT EVENT ".sizeof($rechatHistory));
        // Check if this conflicts with smart rechat
        // Is this doing something?
        while (sem_acquire($semaphore,true)!=true)  {
            $user_input_after=$db->fetchAll("select count(*) as N from eventlog where type='user_input' and ts>$gameRequest[1]");
            if (isset($user_input_after[0]))
                if (isset($user_input_after[0]["N"]))
                    if ($user_input_after[0]["N"]>0) {
                        Logger::info("Generation stopped because user_input. ".__LINE__);
                        die();// Abort rechat
                    }

            usleep(100);
        }
    }

    $sqlfilter=" and type in ('prechat','inputtext','ginputtext','infonpc','infonpc_close','logaction','infoaction','death') or (type='chat' and data like '(Context%') ";  // Use prechat
    // chat entries starting by "(Context%" are standard skyrim dialogue

    $FUNCTIONS_ARE_ENABLED=false;       // Enabling this can be funny => CHAOS MODE
   
    $GLOBALS["ADD_PLAYER_BIOS"]=false;

} else
    $sqlfilter=" and type<>'prechat' "; // Will dismiss prechat entries by default. prechat are LLM responses still not displayed in-game


// Non-LLM request handling.

require(__DIR__.DIRECTORY_SEPARATOR."processor".DIRECTORY_SEPARATOR."comm.php");

if ($MUST_END) {  // Shorthand for non LLM processing
    echo 'X-CUSTOM-CLOSE'.PHP_EOL;
    return;
}


//error_log("TRACE:\t".__LINE__. "\t".__FILE__.":\t".(microtime(true) - $startTime));

/**********************
 CONTEXT DATA BUILDING
***********************/

// Include prompts, command prompts and functions.
require(__DIR__.DIRECTORY_SEPARATOR."prompt.includes.php");
$gameRequest[0] = strtolower($gameRequest[0]); // one more time in case it was changed by an extension

// Take care of override request if needed..
require(__DIR__.DIRECTORY_SEPARATOR."processor".DIRECTORY_SEPARATOR."request.php");



/*
 Safe stop
*/
Logger::info("Current STOPALL_MAGIC_WORD ".STOPALL_MAGIC_WORD);
if (in_array($gameRequest[0],["inputtext","inputtext_s","ginputtext","ginputtext_s","instruction"]) && preg_match(STOPALL_MAGIC_WORD, $gameRequest[3]) === 1) {
    echo "{$GLOBALS["HERIKA_NAME"]}|command|Halt@\r\n";
    if (ob_get_level()) @ob_flush();
    $alreadysent[md5("{$GLOBALS["HERIKA_NAME"]}|command|Halt@\r\n")] = "{$GLOBALS["HERIKA_NAME"]}|command|Halt@\r\n";
    
}

if (!isset($GLOBALS["CACHE_PEOPLE"])) {
    $GLOBALS["CACHE_PEOPLE"]=DataBeingsInCloseRange();
} 
if (!isset($GLOBALS["CACHE_LOCATION"])) {
    $GLOBALS["CACHE_LOCATION"]=DataLastKnownLocation();
}     

if (!isset($GLOBALS["CACHE_PARTY"])) {
    $GLOBALS["CACHE_PARTY"]=DataGetCurrentPartyConf();
} 

if (in_array($gameRequest[0],["inputtext_s"])) {    // I stealth and targetet follower, CACHE_PEOPLE will only contain target NPC
    $GLOBALS["CACHE_PEOPLE"]=$GLOBALS["HERIKA_NAME"];
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
            'sess' => (php_sapi_name()=="cli" && !getenv('PHPUNIT_TEST'))?'cli':'web',
            'localts' => time(),
            'people'=> $GLOBALS["CACHE_PEOPLE"],
            'location'=>$GLOBALS["CACHE_LOCATION"],
            'party'=>$GLOBALS["CACHE_PARTY"],
            
        )
    );

}

// Check if this event  has been disabled 
if (isset($GLOBALS["PROMPTS"][$gameRequest[0]]["extra"]["dontuse"])) {
    if ($GLOBALS["PROMPTS"][$gameRequest[0]]["extra"]["dontuse"])
        die("\r\n");
}


// Narrator stop (from config)

if (isset($GLOBALS["NARRATOR_TALKS"])&&($GLOBALS["NARRATOR_TALKS"]==false)) {
    if ($GLOBALS["HERIKA_NAME"]=="The Narrator")
        die("\r\n");
}

$lastNDataForContext = (isset($GLOBALS["CONTEXT_HISTORY"])) ? ($GLOBALS["CONTEXT_HISTORY"]) : "25";

// Historic context (last dialogues, events,...)
//if ((!$GLOBALS["IS_NPC"])||($GLOBALS["HERIKA_NAME"]=="The Narrator"))
if (($GLOBALS["HERIKA_NAME"]=="The Narrator"))
    $contextDataHistoric = DataLastDataExpandedFor("", $lastNDataForContext * -1,$sqlfilter);
else if (!$GLOBALS["IS_NPC"])
    $contextDataHistoric = DataLastDataExpandedFor("{$GLOBALS["HERIKA_NAME"]}", $lastNDataForContext * -1,$sqlfilter);
else if ($GLOBALS["IS_NPC"]) {
    $contextDataHistoric = DataLastDataExpandedFor("{$GLOBALS["HERIKA_NAME"]}", $lastNDataForContext * -1,$sqlfilter);
    
}


// Info about location and npcs in first position
$contextDataWorld = DataLastInfoFor("", -2,true);

// Add current motto to COMMAND_PROMPT
if (isset($GLOBALS["CURRENT_TASK"]) && $GLOBALS["CURRENT_TASK"] && $gameRequest[0] != "diary") {
    if ((!$GLOBALS["IS_NPC"])||($GLOBALS["HERIKA_NAME"]=="The Narrator")) {
        $task=DataGetCurrentTask();
        if (empty($task)) {
            $task="No active quests right now.";
        }
        $GLOBALS["COMMAND_PROMPT"].=$task;
    } else {
        Logger::info("Task avoided {$GLOBALS["IS_NPC"]} ");
    }
}

// Offer memory in CONTEXT 


if (in_array($gameRequest[0],["inputtext","inputtext_s","ginputtext","ginputtext_s","rechat"]) ) {

    $memoryInjection=offerMemory($gameRequest, $DIALOGUE_TARGET);
    //Logger::info("Memory injection:".json_encode($memoryInjection));

    if (!empty($memoryInjection)) {
        
        //$memoryInjectionCtx[]= array('role' => 'user', 'content' => $gameRequest[3]);
        $memoryInjectionCtx[]= array('role' => 'user', 'content' => "#MEMORY: {$GLOBALS["HERIKA_NAME"]} remembers this: [$memoryInjection]");
        //$GLOBALS["COMMAND_PROMPT"].="'{$gameRequest[3]}'\n{$GLOBALS["HERIKA_NAME"]}):$memoryInjection\n";
        
    } else {
        $memoryInjectionCtx=[];
        $request=str_replace($GLOBALS["MEMORY_STATEMENT"],"",$request);//Cleans the memory statement.
            
    }
} else
     $memoryInjectionCtx=[];



// array('role' => $currentSpeaker, 'content' => implode("\n", $buffer));



if (in_array($gameRequest[0],["rechat"]) ) {
    // CHAOS mode
    
    if (isset($GLOBALS["RECHAT_ALLOW_ACTIONS"]) && $GLOBALS["RECHAT_ALLOW_ACTIONS"]) {
        $FUNCTIONS_ARE_ENABLED=true;

        if (isset($GLOBALS["ENFORCE_ACTIONS_PROMPT"]) && $GLOBALS["ENFORCE_ACTIONS_PROMPT"]) {
            $GLOBALS["PATCH_PROMPT_ENFORCE_ACTIONS"]=true;
            $GLOBALS["COMMAND_PROMPT_ENFORCE_ACTIONS"]="(If {$GLOBALS["HERIKA_NAME"]} is just speaking, use action \"Talk\". If another action is even remotely contextually appropriate, use it, even if in doubt)";
        }
        
        // MinAI prompts are breaking rechat actor adressing "Respond to #target# as #herika_name#"
        $GLOBALS['action_prompts']=[];
        // Unset some functions here.
       
        unsetFunction("OpenInventory");
        unsetFunction("TravelTo");
        unsetFunction("ComeCloser");
        unsetFunction("IncreaseWalkSpeed");
        unsetFunction("DecreaseWalkSpeed");
        unsetFunction("DecreaseWalkSpeed");
        unsetFunction("OpenInventory2");
        unsetFunction("FollowPlayer");// Will use generic Follow and postfilters

        // Change name of functions here
        // Function clone and renaming
        // ExchangeItems (trade with player) will be modified to TradeItems (roleplayed trade)
        $NEWFUNCTION=$GLOBALS["BASE_FUNCTIONS"]["OpenInventory"];
        $NEWFUNCTION["name"]="TradeItems";
        $NEWFUNCTION["description"]="{$GLOBALS["HERIKA_NAME"]} trade items with another actor. Amount and item will be infered from dialogue, so no need to specify";
        $NEWFUNCTION["parameters"]["properties"]["target"]["description"]="Actor name to trade with";
        $GLOBALS["FUNCTIONS"][]=$NEWFUNCTION;
        $GLOBALS["ENABLED_FUNCTIONS"][]="TradeItems";
        $GLOBALS["F_NAMES"]["TradeItems"]="TradeItems";

        if ($GLOBALS["IS_NPC"]) {
            // TravelTo (lead the way to for player) will be modified to TravelTo (TravelTo) if no follower
            $NEWFUNCTION=$GLOBALS["BASE_FUNCTIONS"]["TravelTo"];
            $NEWFUNCTION["name"]="TravelTo";
            $NEWFUNCTION["description"]="{$GLOBALS["HERIKA_NAME"]} travels to location";
            $NEWFUNCTION["parameters"]["properties"]["location"]["description"]="location name";
            $GLOBALS["FUNCTIONS"][]=$NEWFUNCTION;
            $GLOBALS["ENABLED_FUNCTIONS"][]="TravelTo";
            $GLOBALS["F_NAMES"]["TravelTo"]="TravelTo";
        } else {
            // Followers 
            unsetFunction("TakeGoldFromPlayer");

        }


       
    }
}

if (in_array($gameRequest[0],["instruction"]) ) {
    
    $GLOBALS["PATCH_PROMPT_ENFORCE_ACTIONS"]=true;
    $GLOBALS["COMMAND_PROMPT_ENFORCE_ACTIONS"]="(If {$GLOBALS["HERIKA_NAME"]} is just speaking, use action \"Talk\". If another action is even remotely contextually appropriate, use it, even if in doubt)";
    
}

if (isset($GLOBALS["ENFORCE_ACTIONS_PROMPT"]) && $GLOBALS["ENFORCE_ACTIONS_PROMPT"]) {
    $GLOBALS["PATCH_PROMPT_ENFORCE_ACTIONS"]=true;
    $GLOBALS["COMMAND_PROMPT_ENFORCE_ACTIONS"]="(If {$GLOBALS["HERIKA_NAME"]} is just speaking, use action \"Talk\". If another action is even remotely contextually appropriate, use it, even if in doubt)";
}


$COOLDOWNMAP["ComeCloser"]=120/0.00864;
$COOLDOWNMAP["WaitHere"]=300/0.00864;
$COOLDOWNMAP["UseSoulGaze"]=300/0.00864;
$COOLDOWNMAP["InspectSurroundings"]=300/0.00864;


if ($GLOBALS["FUNCTIONS_ARE_ENABLED"]) {
    $localActorName=$GLOBALS["db"]->escape($GLOBALS["HERIKA_NAME"]);
    $lastActionsIssuedMap=$GLOBALS["db"]->fetchAll("SELECT * FROM (SELECT DISTINCT ON (action) * FROM actions_issued WHERE actorname = '$localActorName' ORDER BY action, gamets DESC, ts DESC) AS sub ORDER BY gamets DESC, ts DESC");
    if (isset($lastActionsIssuedMap[0])) {
        foreach ($lastActionsIssuedMap as $lastActionsIssued) {

            $ingamenow=convert_gamets2seconds($gameRequest[2]);
            $lasttriggered=convert_gamets2seconds($lastActionsIssued["gamets"]);
            $elapsedSecs=gamets2seconds_between($gameRequest[2],$lastActionsIssued["gamets"]);

            if (isset($COOLDOWNMAP[$lastActionsIssued["action"]])) {
                if (($ingamenow-$lasttriggered)<$COOLDOWNMAP[$lastActionsIssued["action"]]) {   // COnsider here use gamets and ts and id001 time functions
                    error_log("{$lastActionsIssued["action"]} in cooldown for $localActorName, {$COOLDOWNMAP[$lastActionsIssued["action"]]} $ingamenow-$lasttriggered $elapsedSecs");
                    unsetFunction($lastActionsIssued["action"]);
                } else {
                    error_log("{$lastActionsIssued["action"]} NOT in cooldown for $localActorName  {$COOLDOWNMAP[$lastActionsIssued["action"]]} $ingamenow-$lasttriggered $elapsedSecs");
                }
            }
        }
    }
}

// Rolemaster stuff

$namedKey="{$GLOBALS["HERIKA_NAME"]}_is_rolemastered";
$npcRoleMastered=$GLOBALS["db"]->fetchOne("select 1  as is_rolemastered from conf_opts where id='".$GLOBALS["db"]->escape($namedKey)."'");
if (isset($npcRoleMastered["is_rolemastered"])) {
    // ReturnBackHome is initially disabled. Les restore it from copy here. Only applies to rolemastered NPCs
    $GLOBALS["NPC_ROLEMASTERED"]=true;
    $GLOBALS["ENABLED_FUNCTIONS"][]="ReturnBackHome";
    $GLOBALS["FUNCTIONS"][]=$GLOBALS["BASE_FUNCTIONS"]["ReturnBackHome"];
    error_log("{$GLOBALS["HERIKA_NAME"]}_is_rolemastered");
    if ((rand(0,5)!==0)){ // Remeber goal from time to time
        $GLOBALS["PATCH_PROMPT_ENFORCE_ACTIONS"]=true;
        $GLOBALS["COMMAND_PROMPT_ENFORCE_ACTIONS"]="(If {$GLOBALS["HERIKA_NAME"]} is just speaking, use action \"Talk\". If another action is even remotely contextually appropriate, use it, even if in doubt)";
        $GLOBALS["COMMAND_PROMPT_ENFORCE_ACTIONS"].="(consider character's goal and traits)";

    }
} 


// MINIME_T5 STUFF, command assiastant

if ($GLOBALS["FUNCTIONS_ARE_ENABLED"]) {
    
    if ($GLOBALS["MINIME_T5"]) {
        $pattern = "/\([^)]*Context location[^)]*\)/"; // Remove (Context location..
        $replacement = "";
        $TEST_TEXT = preg_replace($pattern, $replacement, $gameRequest[3]); // // assistant vs user war
        
        $pattern = '/\(talking to [^()]+\)/i';
        $TEST_TEXT = preg_replace($pattern, '', $TEST_TEXT);
        
        if (!in_array($gameRequest[0],["rechat","instruction"]) ) {// Dont use minime command force on rechat.
            $TEST_TEXT=strtr($TEST_TEXT,["."=>" ","{$GLOBALS["PLAYER_NAME"]}:"=>""]);
            $command=minimeCommand($TEST_TEXT);
            if ($command && $command !== "null") {
                $preCommand=json_decode($command,true);
                if ($preCommand["is_command"]!="Talk") {
                    $GLOBALS["db"]->insert(
                        'audit_memory',
                        array(
                            'input' => $TEST_TEXT,
                            'keywords' =>'command offered',
                            'rank_any'=> -1,
                            'rank_all'=>-1,
                            'memory'=>$preCommand["is_command"],
                            'time'=>$preCommand["elapsed_time"]
                        )
                    );
                    Logger::info("ENFORCING COMMAND: <{$preCommand["is_command"]}>");
                    //$memoryInjectionCtx=[]; // Disable memorie when command.
                    $COMMAND_PROMPT_ENFORCE_ACTIONS.="(USER MAY WANTS YOU TO ISSUE ACTION {$preCommand["is_command"]}).";
                    $GLOBALS["PATCH_PROMPT_ENFORCE_ACTIONS"]=true;
                } 
            }
        }

       
    }

    $GLOBALS["COMMAND_PROMPT"].=$GLOBALS["COMMAND_PROMPT_FUNCTIONS"];
}


// audit_log(__FILE__." [MINIME]  ".__LINE__);

// OGHMA STUFF

require(__DIR__."/processor/oghma.php");

if (sizeof($memoryInjectionCtx)>0) {
    // Persist memory injection
    $gameRequestCopy=$gameRequest;
    $gameRequestCopy[0]="infoaction";
    $gameRequestCopy[3]=$memoryInjectionCtx[0]["content"];
    logEvent($gameRequestCopy,$GLOBALS["HERIKA_NAME"]);// Memory log only avaibale to current NPC.
}

$contextDataFull = array_merge($contextDataWorld, $contextDataHistoric);

// audit_log(__FILE__." [OGHMA]  ".__LINE__);

if (($gameRequest[0]=="chatnf_book")&&($GLOBALS["BOOK_EVENT_FULL"])) {
    // When chatnf_book (make the AI to read a book), context will only be the book data.
    $contextDataFull = DataGetLastReadedBook();
}


if (isset($GLOBALS["ADD_PLAYER_BIOS"])&&($GLOBALS["ADD_PLAYER_BIOS"])) {
    $GLOBALS["PROMPT_HEAD"].=PHP_EOL.$GLOBALS["PLAYER_BIOS"];
}

if (isset($GLOBALS["OGHMA_HINT"]) && $GLOBALS["OGHMA_HINT"]) {

    $head[] = array('role' => 'system', 'content' =>  
        strtr($GLOBALS["PROMPT_HEAD"] . "\n".$GLOBALS["HERIKA_PERS"] . $GLOBALS["HERIKA_DYNAMIC"] . $GLOBALS["OGHMA_HINT"]."\n". $GLOBALS["COMMAND_PROMPT"],
        ["#PLAYER_NAME#"=>$GLOBALS["PLAYER_NAME"]])
    );
} else {
    $head[] = array('role' => 'system', 'content' =>  
        strtr($GLOBALS["PROMPT_HEAD"] . "\n".$GLOBALS["HERIKA_PERS"] . $GLOBALS["HERIKA_DYNAMIC"] . "\n". $GLOBALS["COMMAND_PROMPT"],
        ["#PLAYER_NAME#"=>$GLOBALS["PLAYER_NAME"]])
    );
}




// Check for context overrides on ext dir (plugins)
requireFilesRecursively(__DIR__.DIRECTORY_SEPARATOR."ext".DIRECTORY_SEPARATOR,"context.php");

// audit_log(__FILE__." [PLUGINS CONTEXT]  ".__LINE__);

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
    if (!empty($request) && $request != "") {
        $prompt[] = array('role' => $LAST_ROLE, 'content' => $request);
        $contextData = array_merge($head, ($contextDataFull), $prompt);
    }
    else {
        $contextData = array_merge($head, ($contextDataFull));
    }


}  else {
    if (in_array($GLOBALS["CURRENT_CONNECTOR"],["koboldcpp","openai","google_openai","openrouter"])) {  // OLD SCHEMA
        if (!empty($request)) {
            if (sizeof($memoryInjectionCtx)>0) {
                if (!isset($prompt)) {
                    $prompt=[];
                }
                array_splice($prompt, -1, 0, $memoryInjectionCtx); // add memory as second-to-last entry
                Logger::info("Injected memory");
            }
            $FUNCTIONS_ARE_ENABLED=false;
            $prompt[] = array('role' => $LAST_ROLE, 'content' => $request);

            
        } else
            $prompt=[];
     
        $GLOBALS["CONNECTOR"][$GLOBALS["CURRENT_CONNECTOR"]]["stop"]=["\n"];
        
        if ($gameRequest[0]=="diary") {
            unset($GLOBALS["CONNECTOR"][$GLOBALS["CURRENT_CONNECTOR"]]["stop"]);
        }
        
        
    } else {
        if (!empty($request)) {
            $prompt[] = array('role' => $LAST_ROLE, 'content' => $request);
            if (sizeof($memoryInjectionCtx)>0) {
                array_splice($prompt, -1, 0, $memoryInjectionCtx); // add memory as second-to-last entry
                Logger::info("Injected memory");
            }
            
        } else {
            Logger::error("CRITICAL? :: Empty request, prompt empty. Type: {$gameRequest[0]} Connector: {$GLOBALS["CURRENT_CONNECTOR"]} ");
            $prompt=[];
        }
    }

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

    require_once(__DIR__.DIRECTORY_SEPARATOR."connector".DIRECTORY_SEPARATOR."{$GLOBALS["CURRENT_CONNECTOR"]}.php");
}

// audit_log(__FILE__." [PRE LLM CALL]  ".__LINE__);

$outputWasValid = call_llm();
if (!$outputWasValid) {
    Logger::warn("LLM returned invalid output.");
    if (isset($GLOBALS["LLM_RETRY_FNCT"])) {
        $GLOBALS["LLM_RETRY_FNCT"]();
    }
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
            
            // Format diary content into paragraphs
            $formattedContent = "";
            $currentParagraph = [];
            $sentenceCount = 0;
            
            foreach ($talkedSoFar as $sentence) {
                $currentParagraph[] = $sentence;
                $sentenceCount++;
                
                // Start new paragraph if we have 2-4 sentences or this is the last sentence
                if ($sentenceCount >= 2 && $sentenceCount <= 4 || $sentence === end($talkedSoFar)) {
                    $formattedContent .= implode(" ", $currentParagraph) . "\n\n";
                    $currentParagraph = [];
                    $sentenceCount = 0;
                }
            }
            
            $db->insert(
                'diarylog',
                array(
                    'ts' => $gameRequest[1],
                    'gamets' => $gameRequest[2],
                    'topic' => "$topic",
                    'content' => trim($formattedContent),
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
            if ((php_sapi_name()!="cli") || getenv('PHPUNIT_TEST'))	
                logMemory($GLOBALS["HERIKA_NAME"], $GLOBALS["HERIKA_NAME"],implode(" ", $talkedSoFar), $momentum, $gameRequest[2],$gameRequest[0],$gameRequest[1]);

            Translation::translate($RESPONSE_OK_NOTED);
            Translation::$sentences = [Translation::$response];
            returnLines([$RESPONSE_OK_NOTED]);

        } else {
            
            $lastPlayerLine=$db->fetchAll("SELECT data from eventlog where type in ('inputtext','inputtext_s') order by gamets desc limit 1 offset 0");
            if (php_sapi_name()!="cli" || getenv('PHPUNIT_TEST'))	{
                if (in_array($gameRequest[0],["inputtext","inputtext_s"]))
                    // logMemory($GLOBALS["HERIKA_NAME"], $GLOBALS["PLAYER_NAME"], "{$lastPlayerLine[0]["data"]} \n\r {$GLOBALS["HERIKA_NAME"]}:".implode(" ", $talkedSoFar), $momentum, $gameRequest[2],$gameRequest[1]);
                    ;
                else {
                    // Speech table will take care
                    //logMemory($GLOBALS["HERIKA_NAME"], $GLOBALS["PLAYER_NAME"], "{$GLOBALS["HERIKA_NAME"]}:".implode(" ", $talkedSoFar), $momentum, $gameRequest[2]);
                    ;
                }
            }
        }
    }
}



echo 'X-CUSTOM-CLOSE'.PHP_EOL;

if (php_sapi_name()=="cli" && !getenv('PHPUNIT_TEST')) {
    echo PHP_EOL;
    file_put_contents("log/debug_comm_".basename(__FILE__).".log", print_r($GLOBALS["DEBUG_DATA"], true));

    //$db->delete("eventlog", "sess='cli'");

}


// POST PROCESS TASKS
if (isset($semaphore) && $semaphore)
    sem_release($semaphore);

while(!getenv("PHPUNIT_TEST") && ob_get_length() && ob_end_clean());
require(__DIR__.DIRECTORY_SEPARATOR."processor".DIRECTORY_SEPARATOR."postrequest.php");
requireFilesRecursively(__DIR__.DIRECTORY_SEPARATOR."ext".DIRECTORY_SEPARATOR,"postrequest.php");

?>
