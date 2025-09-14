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

// New profile system
require_once($path . "lib/core/api_badge.class.php");
require_once($path . "lib/core/llm_connector.class.php");
require_once($path . "lib/core/tts_connector.class.php");
require_once($path . "lib/core/npc_master.class.php");
require_once($path . "lib/core/core_profiles.class.php");

$GLOBALS["ENGINE_PATH"]=$path;

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


// Database Connection
$db = new sql();

require_once($path . "processor" .DIRECTORY_SEPARATOR."chim_modes.php");


requireFilesRecursively(__DIR__.DIRECTORY_SEPARATOR."ext".DIRECTORY_SEPARATOR,"preprocessing.php");
if (in_array($gameRequest[0],["inputtext","inputtext_s","ginputtext","ginputtext_s","instruction","init"])) {
    // This is just form mark that user has made an input request. We will check later when waiting for LLm response 
    // if use has made input after that request, so we can abort it.
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
    "util_location_name","spellcast","npcspellcast","updateprofiles_batch_async","core_profile_assign","switchrace"];

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
    terminate();
}

// Biography CSV upload
if ($gameRequest[0]=="biography_import") {
    require(__DIR__."/processor/biography_import.php");
    
    terminate();
}

// Oghma CSV upload
// Move this to a processor file
if ($gameRequest[0]=="oghma_import") {
    Logger::info("Processing Oghma CSV data upload");
    
    // Parse the message format: oghma_import|timestamp|gametime|filename|csv_data
    // $gameRequest[4] should contain the CSV data
    if (!isset($gameRequest[4]) || empty($gameRequest[4])) {
        Logger::error("Oghma Import: No CSV data provided");
        terminate();
    }
    
    $csvData = $gameRequest[4];
    $processedCount = 0;
    $errorCount = 0;
    
    try {
        // Create a temporary file to properly parse complex CSV data
        $tempFile = tempnam(sys_get_temp_dir(), 'oghma_import_');
        file_put_contents($tempFile, $csvData);
        
        $handle = fopen($tempFile, 'r');
        if ($handle === false) {
            Logger::error("Oghma Import: Could not open temporary CSV file");
            terminate();
        }
        
        // Read and process header
        $header = fgetcsv($handle, 0, ',', '"', '"');
        if ($header === false || empty($header)) {
            Logger::error("Oghma Import: Invalid CSV header");
            fclose($handle);
            unlink($tempFile);
            terminate();
        }
        
        // Normalize header labels and create header map
        $headerMap = [];
        foreach ($header as $i => $colName) {
            $normalized = strtolower(trim($colName));
            $headerMap[$normalized] = $i;
        }
        
        // Process each data row
        while (($data = fgetcsv($handle, 0, ',', '"', '"')) !== false) {
            if (empty($data) || count($data) < 2) {
                continue; // Skip empty or invalid rows
            }
            
            // Extract required fields
            $topic = '';
            if (isset($headerMap['topic']) && isset($data[$headerMap['topic']])) {
                $topic = strtolower(trim($data[$headerMap['topic']]));
            }
            
            $topic_desc = '';
            if (isset($headerMap['topic_desc']) && isset($data[$headerMap['topic_desc']])) {
                $topic_desc = trim($data[$headerMap['topic_desc']]);
            }
            
            // Extract optional fields
            $knowledge_class = '';
            if (isset($headerMap['knowledge_class']) && isset($data[$headerMap['knowledge_class']])) {
                $knowledge_class = trim($data[$headerMap['knowledge_class']]);
            }
            
            $topic_desc_basic = '';
            if (isset($headerMap['topic_desc_basic']) && isset($data[$headerMap['topic_desc_basic']])) {
                $topic_desc_basic = trim($data[$headerMap['topic_desc_basic']]);
            }
            
            $knowledge_class_basic = '';
            if (isset($headerMap['knowledge_class_basic']) && isset($data[$headerMap['knowledge_class_basic']])) {
                $knowledge_class_basic = trim($data[$headerMap['knowledge_class_basic']]);
            }
            
            $tags = '';
            if (isset($headerMap['tags']) && isset($data[$headerMap['tags']])) {
                $tags = trim($data[$headerMap['tags']]);
            }
            
            $category = '';
            if (isset($headerMap['category']) && isset($data[$headerMap['category']])) {
                $category = trim($data[$headerMap['category']]);
            }
            
            // Skip if required fields are missing
            if (empty($topic) || empty($topic_desc)) {
                Logger::warn("Oghma Import: Skipping row with missing topic or topic_desc");
                $errorCount++;
                continue;
            }
            
            // Insert or update record using upsertRowOnConflict
            try {
                $db->upsertRowOnConflict(
                    'oghma',
                    array(
                        'topic' => $topic,
                        'topic_desc' => $topic_desc,
                        'knowledge_class' => $knowledge_class,
                        'topic_desc_basic' => $topic_desc_basic,
                        'knowledge_class_basic' => $knowledge_class_basic,
                        'tags' => $tags,
                        'category' => $category
                    ),
                    'topic'
                );
                $processedCount++;
                Logger::info("Oghma Import: Successfully processed topic: $topic");
            } catch (Exception $e) {
                Logger::error("Oghma Import: Error processing topic '$topic': " . $e->getMessage());
                $errorCount++;
            }
        }
        
        fclose($handle);
        unlink($tempFile);
        
        Logger::info("Oghma Import: Processing complete. $processedCount records processed, $errorCount errors");
        
    } catch (Exception $e) {
        Logger::error("Oghma Import: Fatal error processing CSV: " . $e->getMessage());
        // Clean up temp file if it exists
        if (isset($tempFile) && file_exists($tempFile)) {
            unlink($tempFile);
        }
    }
    
    terminate();
}


// Dynamic Oghma CSV upload
// Move this to a processor file
if ($gameRequest[0]=="dynamic_oghma_import") {
    Logger::info("Processing Dynamic Oghma CSV data upload");
    
    // Parse the message format: dynamic_oghma_import|timestamp|gametime|filename|csv_data
    // $gameRequest[4] should contain the CSV data
    if (!isset($gameRequest[4]) || empty($gameRequest[4])) {
        Logger::error("Dynamic Oghma Import: No CSV data provided");
        terminate();
    }
    
    $csvData = $gameRequest[4];
    $processedCount = 0;
    $errorCount = 0;
    
    try {
        // Create a temporary file to properly parse complex CSV data
        $tempFile = tempnam(sys_get_temp_dir(), 'dynamic_oghma_import_');
        file_put_contents($tempFile, $csvData);
        
        $handle = fopen($tempFile, 'r');
        if ($handle === false) {
            Logger::error("Dynamic Oghma Import: Could not open temporary CSV file");
            terminate();
        }
        
        // Read and process header
        $header = fgetcsv($handle, 0, ',', '"', '"');
        if ($header === false || empty($header)) {
            Logger::error("Dynamic Oghma Import: Invalid CSV header");
            fclose($handle);
            unlink($tempFile);
            terminate();
        }
        
        // Normalize header labels and create header map
        $headerMap = [];
        foreach ($header as $i => $colName) {
            $normalized = strtolower(trim($colName));
            $headerMap[$normalized] = $i;
        }
        
        // Process each data row
        while (($data = fgetcsv($handle, 0, ',', '"', '"')) !== false) {
            if (empty($data) || count($data) < 3) {
                continue; // Skip empty or invalid rows
            }
            
            // Extract required fields
            $id_quest = '';
            if (isset($headerMap['id_quest']) && isset($data[$headerMap['id_quest']])) {
                $id_quest = trim($data[$headerMap['id_quest']]);
            }
            
            $stage = 0;
            if (isset($headerMap['stage']) && isset($data[$headerMap['stage']])) {
                $stage = intval(trim($data[$headerMap['stage']]));
            }
            
            $topic = '';
            if (isset($headerMap['topic']) && isset($data[$headerMap['topic']])) {
                $topic = strtolower(trim($data[$headerMap['topic']]));
            }
            
            // Extract optional fields
            $topic_desc = '';
            if (isset($headerMap['topic_desc']) && isset($data[$headerMap['topic_desc']])) {
                $topic_desc = trim($data[$headerMap['topic_desc']]);
            }
            
            $knowledge_class = '';
            if (isset($headerMap['knowledge_class']) && isset($data[$headerMap['knowledge_class']])) {
                $knowledge_class = trim($data[$headerMap['knowledge_class']]);
            }
            
            $topic_desc_basic = '';
            if (isset($headerMap['topic_desc_basic']) && isset($data[$headerMap['topic_desc_basic']])) {
                $topic_desc_basic = trim($data[$headerMap['topic_desc_basic']]);
            }
            
            $knowledge_class_basic = '';
            if (isset($headerMap['knowledge_class_basic']) && isset($data[$headerMap['knowledge_class_basic']])) {
                $knowledge_class_basic = trim($data[$headerMap['knowledge_class_basic']]);
            }
            
            $tags = '';
            if (isset($headerMap['tags']) && isset($data[$headerMap['tags']])) {
                $tags = trim($data[$headerMap['tags']]);
            }
            
            $category = '';
            if (isset($headerMap['category']) && isset($data[$headerMap['category']])) {
                $category = trim($data[$headerMap['category']]);
            }
            
            // Skip if required fields are missing
            if (empty($id_quest) || empty($topic)) {
                Logger::warn("Dynamic Oghma Import: Skipping row with missing id_quest or topic");
                $errorCount++;
                continue;
            }
            
            // Insert record (dynamic oghma doesn't use upsert, it allows multiple entries)
            try {
                $db->insert(
                    'oghma_dynamic',
                    array(
                        'id_quest' => $id_quest,
                        'stage' => $stage,
                        'topic' => $topic,
                        'topic_desc' => $topic_desc,
                        'knowledge_class' => $knowledge_class,
                        'topic_desc_basic' => $topic_desc_basic,
                        'knowledge_class_basic' => $knowledge_class_basic,
                        'tags' => $tags,
                        'category' => $category
                    )
                );
                $processedCount++;
                Logger::info("Dynamic Oghma Import: Successfully processed quest '$id_quest' stage $stage topic '$topic'");
            } catch (Exception $e) {
                Logger::error("Dynamic Oghma Import: Error processing quest '$id_quest' topic '$topic': " . $e->getMessage());
                $errorCount++;
            }
        }
        
        fclose($handle);
        unlink($tempFile);
        
        Logger::info("Dynamic Oghma Import: Processing complete. $processedCount records processed, $errorCount errors");
        
    } catch (Exception $e) {
        Logger::error("Dynamic Oghma Import: Fatal error processing CSV: " . $e->getMessage());
        // Clean up temp file if it exists
        if (isset($tempFile) && file_exists($tempFile)) {
            unlink($tempFile);
        }
    }
    
    terminate();
}


// Player rewrite

if (in_array($gameRequest[0],["inputtext","inputtext_s","ginputtext","ginputtext_s"]) && isset($GLOBALS["PLAYER_RESPEECH"]) && $GLOBALS["PLAYER_RESPEECH"]) {
    // Use preg_replace to remove the name and colon before the dialogue
    $cleaned_player_dialogue = addcslashes(preg_replace('/^[^:]+:/', '', $gameRequest[3]),'"');
    error_log($cleaned_player_dialogue);
    if (strpos($gameRequest[3],"**")===0 || strpos($cleaned_player_dialogue,"**")===0 ) {
        // If player speech starts with **
        error_log("Overwritting user prompt $cleaned_player_dialogue");

        //$newSpeech=file_get_contents(getBaseUrlForSpeech()."/HerikaServer/player_rewrite.php?speech=".urlencode($cleaned_player_dialogue));
        $newSpeech=`php player_rewrite.php "$cleaned_player_dialogue"`;
        $gameRequest[3]="{$GLOBALS["PLAYER_NAME"]}:$newSpeech";

    }
}

// Profile selection and migration

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
        // Migration here to new system
        error_log("[CHIM CORE] MIGRATING PROFILE {$_GET["profile"]}}");

        $npcMaster=new NpcMaster();
        $currentNpcData=$npcMaster->getByMD5($_GET["profile"]);
    
        if (!$currentNpcData) {
            
            require($path . "conf".DIRECTORY_SEPARATOR."conf_{$_GET["profile"]}.php");
            error_log("[CHIM CORE] CREATING EMPTY PROFILE for {$GLOBALS["HERIKA_NAME"]}");

            $npcMaster->create(["npc_name"=>$GLOBALS["HERIKA_NAME"]]);
            $currentNpcData=$npcMaster->getByMD5($_GET["profile"]);

            if ($currentNpcData) {
                $newNpcData=$npcMaster->migrateFromOldProfile($currentNpcData,$GLOBALS);


                $ingameDataRef=getBaseDataForNpcFromLog($GLOBALS["HERIKA_NAME"]);
                $newNpcData=array_merge($newNpcData,$ingameDataRef);
                if ($newNpcData) {
                    $npcMaster->updateByArray($newNpcData);
                }
                
            }

            $currentNpcData=$npcMaster->getByMD5($_GET["profile"]);

        } 

        // Profile has been migrated

        $profile=new CoreProfile();
        $currentProfileData=$profile->getById($currentNpcData["profile_id"]);
        $GLOBALS["CHIM_CORE_CURRENT_PROFILE_DATA"]=$currentProfileData;
        $connector=new LLMConnector();
        $currentActiveModelProfile=$db->fetchOne("select value from conf_opts where id='chim_profile_model'");

        if (isset($currentActiveModelProfile["value"])) {
            if ($currentActiveModelProfile["value"]==1) 
                $currentConnectorData=$connector->getById($currentProfileData["llm_primary_id"]); 
            else if ($currentActiveModelProfile["value"]==2) 
                $currentConnectorData=$connector->getById($currentProfileData["llm_secondary_id"]);
            else if ($currentActiveModelProfile["value"]==3) 
                $currentConnectorData=$connector->getById($currentProfileData["llm_tertiary_id"]); 
            else if ($currentActiveModelProfile["value"]==4) 
                $currentConnectorData=$connector->getById($currentProfileData["llm_quaternary_id"]);
            else
                $currentConnectorData=$connector->getById($currentProfileData["llm_primary_id"]); 

        } else
                $currentConnectorData=$connector->getById($currentProfileData["llm_primary_id"]); 
        
    
        $connector->setOldGlobals($currentConnectorData);
        $profile->setOldGlobals($currentProfileData);
        $npcMaster->setOldGlobalsFromCurrentNpcData($currentNpcData);

        $npcMaster->updateByArray($currentNpcData);
        
        $GLOBALS["CHIM_CORE_CURRENT_CONNECTOR_DATA"]=$currentConnectorData;
        
        @error_log("[CORE SYSTEM] Using new profile system , GLOBALS['LLM_LANG']:{$GLOBALS["LLM_LANG"]} profile: {$currentProfileData["label"]}");
        @error_log("[CORE SYSTEM] GLOBALS['LLM_LANG']:{$GLOBALS["LLM_LANG"]} GLOBALS['PATCH_OVERRIDE_TTS_LANGUAGE']:{$GLOBALS["PATCH_OVERRIDE_TTS_LANGUAGE"]}");

        rename($path . "conf".DIRECTORY_SEPARATOR."conf_{$_GET["profile"]}.php",$path . "conf/.old/".DIRECTORY_SEPARATOR."conf_{$_GET["profile"]}.php");

    } else {
        
        $npcMaster=new NpcMaster();
        $currentNpcData=$npcMaster->getByMD5($_GET["profile"]);
    
        if (!$currentNpcData) {
            error_log(__FILE__.". Using default profile because GET PROFILE NOT EXISTS");

        
        } else {
            error_log("[CHIM CORE] USING CORE PROFILE {$currentNpcData["npc_name"]}")    ;
        

            // Profile has been migrated
            $npcMaster->setOldGlobalsFromCurrentNpcData($currentNpcData);

            $profile=new CoreProfile();
            $currentProfileData=$profile->getById($currentNpcData["profile_id"]);
        
            $GLOBALS["CHIM_CORE_CURRENT_PROFILE_DATA"]=$currentProfileData;

            $connector=new LLMConnector();
            $currentActiveModelProfile=$db->fetchOne("select value from conf_opts where id='chim_profile_model'");

            if (isset($currentActiveModelProfile["value"])) {
                if ($currentActiveModelProfile["value"]==1) 
                    $currentConnectorData=$connector->getById($currentProfileData["llm_primary_id"]); 
                else if ($currentActiveModelProfile["value"]==2) 
                    $currentConnectorData=$connector->getById($currentProfileData["llm_secondary_id"]);
                else if ($currentActiveModelProfile["value"]==3) 
                    $currentConnectorData=$connector->getById($currentProfileData["llm_tertiary_id"]); 
                else if ($currentActiveModelProfile["value"]==4) 
                    $currentConnectorData=$connector->getById($currentProfileData["llm_quaternary_id"]);
                else
                    $currentConnectorData=$connector->getById($currentProfileData["llm_primary_id"]); 

            } else
                    $currentConnectorData=$connector->getById($currentProfileData["llm_primary_id"]); 
            
        
            $connector->setOldGlobals($currentConnectorData);
            $profile->setOldGlobals($currentProfileData);
            $npcMaster->setOldGlobalsFromCurrentNpcData($currentNpcData);

            $GLOBALS["CHIM_CORE_CURRENT_CONNECTOR_DATA"]=$currentConnectorData;
            
            error_log("[CORE SYSTEM] Using new profile system , GLOBALS['LLM_LANG']:{$GLOBALS["LLM_LANG"]} profile: {$currentProfileData["label"]}");
            error_log("[CORE SYSTEM] GLOBALS['LLM_LANG']:{$GLOBALS["LLM_LANG"]} GLOBALS['PATCH_OVERRIDE_TTS_LANGUAGE']:{$GLOBALS["PATCH_OVERRIDE_TTS_LANGUAGE"]}");
        }
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

if (in_array($gameRequest[0],["inputtext","inputtext_s","ginputtext","ginputtext_s"]) ) {
    // Empty request
    if (empty($gameRequest[3]) || trim($gameRequest[3])=="{$GLOBALS["PLAYER_NAME"]}:") {
        error_log("[MAIN] Empty request... aborting");
        terminate();
    } else {
        error_log("[MAIN] Request: {$gameRequest[3]}");
    }
    
}


/* *****
Player TTS

Player TTS. We overwrite some confs an then restore them.
*/
if (in_array($gameRequest[0],["inputtext","inputtext_s","ginputtext","ginputtext_s"]) && Translation::isSavePlayerTranslationEnabled()) {
   
    require(__DIR__."/processor/player_tts.php");
    
}


$GLOBALS["active_profile"]=md5($GLOBALS["HERIKA_NAME"]);


// End of profile selection

// This is the correct place, after arse $gameRequest and before starting to do substituions

if (($gameRequest[0]=="chatnf_book")&&($GLOBALS["BOOK_EVENT_ALWAYS_NARRATOR"])) {

    $npcMaster=new NpcMaster();
    $currentNpcData=$npcMaster->getByName("The Narrator");
    error_log("[CHIM CORE] [BOOK OVERRIDE] USING CORE PROFILE {$currentNpcData["npc_name"]}")    ;

    $npcMaster->setOldGlobalsFromCurrentNpcData($currentNpcData);

    $profile=new CoreProfile();
    $currentProfileData=$profile->getById($currentNpcData["profile_id"]);

    $GLOBALS["CHIM_CORE_CURRENT_PROFILE_DATA"]=$currentProfileData;

    $connector=new LLMConnector();
    $currentActiveModelProfile=$db->fetchOne("select value from conf_opts where id='chim_profile_model'");

    if (isset($currentActiveModelProfile["value"])) {
        if ($currentActiveModelProfile["value"]==1) 
            $currentConnectorData=$connector->getById($currentProfileData["llm_primary_id"]); 
        else if ($currentActiveModelProfile["value"]==2) 
            $currentConnectorData=$connector->getById($currentProfileData["llm_secondary_id"]);
        else if ($currentActiveModelProfile["value"]==3) 
            $currentConnectorData=$connector->getById($currentProfileData["llm_tertiary_id"]); 
        else if ($currentActiveModelProfile["value"]==4) 
            $currentConnectorData=$connector->getById($currentProfileData["llm_quaternary_id"]);
        else
            $currentConnectorData=$connector->getById($currentProfileData["llm_primary_id"]); 

    } else
            $currentConnectorData=$connector->getById($currentProfileData["llm_primary_id"]); 
    

    $connector->setOldGlobals($currentConnectorData);
    $profile->setOldGlobals($currentProfileData);
    $npcMaster->setOldGlobalsFromCurrentNpcData($currentNpcData);

    $GLOBALS["CHIM_CORE_CURRENT_CONNECTOR_DATA"]=$currentConnectorData;

}

foreach ($gameRequest as $i => $ele) {
    $gameRequest[$i] = trim(preg_replace('/\s\s+/', ' ', preg_replace('/\'/m', "'", $ele)));
    //$gameRequest[$i] = trim(preg_replace('/\s\s+/', ' ', preg_replace('/\'/m', "''", $ele)));
    $gameRequest[$i]=strtr($gameRequest[$i],["#HERIKA_NPC1#"=>$GLOBALS["HERIKA_NAME"]]);
}



// $gameRequest = type of message|localts|gamets|data

if ($gameRequest[0]=="diary") {
    $GLOBALS["CURRENT_CONNECTOR"]=$GLOBALS["CONNECTORS_DIARY"];
    
    // Add configurable cooldown for diary events to prevent spam (per NPC)
    $diaryCooldownPeriod = isset($GLOBALS["DIARY_COOLDOWN"]) ? intval($GLOBALS["DIARY_COOLDOWN"]) : 30;
    
    // Create a per-NPC cooldown key using the current NPC's name
    $npcName = preg_replace('/[^a-zA-Z0-9_]/', '_', $GLOBALS["HERIKA_NAME"]);
    $cooldownKey = "DIARY_LAST_TIMESTAMP_" . $npcName;
    
    // Fetch the last diary trigger timestamp for this specific NPC
    $diaryRecord = $GLOBALS["db"]->fetchAll("SELECT value FROM conf_opts WHERE id='" . $GLOBALS["db"]->escape($cooldownKey) . "'");
    
    // Check if the timestamp exists in the database
    if (!empty($diaryRecord)) {
        $lastTrigger = (int) $diaryRecord[0]['value'];
        $timeElapsed = time() - $lastTrigger;

        if ($timeElapsed < $diaryCooldownPeriod) {
            // Cooldown is still active for this NPC, exit
            Logger::info("DIARY is on cooldown for {$GLOBALS["HERIKA_NAME"]}. Try again in " . ($diaryCooldownPeriod - $timeElapsed) . " seconds.");
            terminate();
        }
    }

    // Update the timestamp in the database for this specific NPC
    $currentTimestamp = time();
    $GLOBALS["db"]->upsertRowOnConflict(
        "conf_opts",
        array(
            "id"    => $cooldownKey,
            "value" => $currentTimestamp
        ),
        'id'
    );
}






// Exit if only a event info log.
if ($gameRequest[0] == "npcspellcast") {
    // Handle npcspellcast events based on DETECT_MAGIC_EVENT setting
    if (isset($GLOBALS["DETECT_MAGIC_EVENT"]) && $GLOBALS["DETECT_MAGIC_EVENT"]) {
        $gameRequest[3] = isset($gameRequest[3]) ? $gameRequest[3] : "";
        
        // Check blacklist if configured
        $shouldLog = true;
        if (isset($GLOBALS["MAGIC_EVENT_BLACKLIST"]) && !empty($GLOBALS["MAGIC_EVENT_BLACKLIST"])) {
            $blacklistedEvents = array_map('trim', explode(',', strtolower($GLOBALS["MAGIC_EVENT_BLACKLIST"])));
            $eventData = strtolower($gameRequest[3]);
            
            foreach ($blacklistedEvents as $blacklistedEvent) {
                if (!empty($blacklistedEvent) && strpos($eventData, $blacklistedEvent) !== false) {
                    $shouldLog = false;
                    break;
                }
            }
        }
        
        if ($shouldLog) {
            logEvent($gameRequest);
        }
    }
    terminate(); // Always exit, whether logged or not
}

// Exit if only a event info log.

if (in_array($gameRequest[0],["info","infonpc","infonpc_close","infoloc","chatme","chat","infoaction","death","itemfound",
    "travelcancel","infoplayer","status_msg","util_npcname","bleedout","spellcast"])) {
    $gameRequest[3]=isset($gameRequest[3])?$gameRequest[3]:"";
    $lastInfoNpcData=$db->escape($gameRequest[3]);
    if (in_array($gameRequest[0],['infonpc','infoloc','infonpc_close'])) {
        // Special cases
        $lastlogEqual=$db->fetchAll("select count(*) as n from eventlog where type in ('infonpc','infoloc','infonpc_close') and data='$lastInfoNpcData' and localts>".(time()-5));
        if (is_array($lastlogEqual) && isset($lastlogEqual[0]) && ($lastlogEqual[0]["n"]>0)) {
            //error_log("Skipping {$gameRequest[0]}");
            terminate();
        }
    }
    logEvent($gameRequest);
    terminate();
}

// Check if the gameRequest matches specific types
if (in_array($gameRequest[0], ["playerinfo", "newgame"])) {
    if (!$GLOBALS["NARRATOR_WELCOME"]) {
        logEvent($gameRequest);
        terminate();
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
                terminate();
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
        terminate();

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
        terminate();
    }
    
    $rndNumber = rand(1, 100);
    if ($rndNumber <= intval($GLOBALS["RECHAT_P"])) {
        // Process Oghma for rechat events using NPC's last dialogue
        if ($GLOBALS["MINIME_T5"] && isset($FEATURES["MISC"]["OGHMA_INFINIUM"]) && ($FEATURES["MISC"]["OGHMA_INFINIUM"])) {
                require(__DIR__."/processor/oghma.php"); // Process Oghma
        }
    }
    else{
        terminate();
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
                        terminate();
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
    if (!getenv("PHPUNIT_TEST")) {
        @ob_end_flush();
        @flush();
    }    
    terminate();
}
if ($EXECUTION_MODE=="INJECTION_LOG") {
    
    terminate();
    
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
        terminate();
}


// Narrator stop (from config)

if (isset($GLOBALS["NARRATOR_TALKS"])&&($GLOBALS["NARRATOR_TALKS"]==false)) {
    if ($GLOBALS["HERIKA_NAME"]=="The Narrator")
        terminate();
}

// Use diary-specific context history if this is a diary request and CONTEXT_HISTORY_DIARY is set
if (($gameRequest[0] == "diary" || $gameRequest[0] == "diary_followers") && isset($GLOBALS["CONTEXT_HISTORY_DIARY"]) && $GLOBALS["CONTEXT_HISTORY_DIARY"] > 0) {
    $lastNDataForContext = $GLOBALS["CONTEXT_HISTORY_DIARY"];
} else {
    $lastNDataForContext = (isset($GLOBALS["CONTEXT_HISTORY"])) ? ($GLOBALS["CONTEXT_HISTORY"]) : "25";
}

if ($GLOBALS["CLEAN_CONTEXT_FOCUS_CHAT"]==1) {
    $lastNDataForContext=$GLOBALS["CLEAN_CONTEXT_FOCUS_CHAT_HISTORY"];
}

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
            $task="\n#QUESTS\nNo active quests right now.";
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
            if (isset($GLOBALS["COMMAND_PROMPT_ENFORCE_ACTIONS_LANG"]))
                $GLOBALS["COMMAND_PROMPT_ENFORCE_ACTIONS"]=$GLOBALS["COMMAND_PROMPT_ENFORCE_ACTIONS_LANG"];
            else
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
    if (isset($GLOBALS["COMMAND_PROMPT_ENFORCE_ACTIONS_LANG"]))
        $GLOBALS["COMMAND_PROMPT_ENFORCE_ACTIONS"]=$GLOBALS["COMMAND_PROMPT_ENFORCE_ACTIONS_LANG"];
    else
        $GLOBALS["COMMAND_PROMPT_ENFORCE_ACTIONS"]="(If {$GLOBALS["HERIKA_NAME"]} is just speaking, use action \"Talk\". If another action is even remotely contextually appropriate, use it, even if in doubt)";
}

// Cooldown definitions
$COOLDOWNMAP["ComeCloser"]=120/0.00864;
$COOLDOWNMAP["WaitHere"]=300/0.00864;
$COOLDOWNMAP["UseSoulGaze"]=300/0.00864;
$COOLDOWNMAP["InspectSurroundings"]=100/0.00864;


if ($GLOBALS["FUNCTIONS_ARE_ENABLED"]) {
    $localActorName=$GLOBALS["db"]->escape($GLOBALS["HERIKA_NAME"]);
    $lastActionsIssuedMap=$GLOBALS["db"]->fetchAll("SELECT * FROM (SELECT DISTINCT ON (action) * FROM actions_issued WHERE (actorname = '$localActorName' or actorname like '%$localActorName,%' or actorname='*') ORDER BY action, gamets DESC, ts DESC) AS sub ORDER BY gamets DESC, ts DESC");
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


if (isset($GLOBALS["is_rolemastered"])) {
    // ReturnBackHome is initially disabled. Les restore it from copy here. Only applies to rolemastered NPCs
    $GLOBALS["NPC_ROLEMASTERED"]=true;
    $GLOBALS["ENABLED_FUNCTIONS"][]="ReturnBackHome";
    $GLOBALS["FUNCTIONS"][]=$GLOBALS["BASE_FUNCTIONS"]["ReturnBackHome"];
    error_log("{$GLOBALS["HERIKA_NAME"]} is_rolemastered");
    if ((rand(0,5)!==0)){ // Remeber goal from time to time
        $GLOBALS["PATCH_PROMPT_ENFORCE_ACTIONS"]=true;
        $GLOBALS["COMMAND_PROMPT_ENFORCE_ACTIONS"]="(If {$GLOBALS["HERIKA_NAME"]} is just speaking, use action \"Talk\". If another action is even remotely contextually appropriate, use it, even if in doubt)";
        $GLOBALS["COMMAND_PROMPT_ENFORCE_ACTIONS"].="(consider character's goal and traits)";
        /*if (isset($GLOBALS["ENFORCE_ACTIONS_PROMPT"]) && $GLOBALS["ENFORCE_ACTIONS_PROMPT"]) {
            $GLOBALS["PATCH_PROMPT_ENFORCE_ACTIONS"]=true;
            if (isset($GLOBALS["COMMAND_PROMPT_ENFORCE_ACTIONS_LANG"]))
                $GLOBALS["COMMAND_PROMPT_ENFORCE_ACTIONS"]=$GLOBALS["COMMAND_PROMPT_ENFORCE_ACTIONS_LANG"];
            else
                $GLOBALS["COMMAND_PROMPT_ENFORCE_ACTIONS"]="(If {$GLOBALS["HERIKA_NAME"]} is just speaking, use action \"Talk\". If another action is even remotely contextually appropriate, use it, even if in doubt)";
        }*/
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
    //command prompt function now injected in json_response.php with actions
    //$GLOBALS["COMMAND_PROMPT"].=$GLOBALS["COMMAND_PROMPT_FUNCTIONS"];
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

// Use centralized function from data_functions.php
$dynamicBiography = buildDynamicBiography($GLOBALS);


if (isset($GLOBALS["PROFILE_PROMPT"])) {
    $dynamicBiography.="\n\n#Part of a group\n{$GLOBALS["PROFILE_PROMPT"]}";
}

// Middle term memory experiment
$npcMaster=new NpcMaster();
$currentNpcData=$npcMaster->getByMD5($_GET["profile"]);
$extended_data=$npcMaster->getExtendedData($currentNpcData);
if (isset($extended_data["middle_term_memory"])) {
    $middle_term_memory = end($extended_data["middle_term_memory"]);
    $dynamicBiography.="\n\n#Past events\n{$middle_term_memory}";

} 
    

if (isset($GLOBALS["OGHMA_HINT"]) && $GLOBALS["OGHMA_HINT"]) {
    
    $head[] = array('role' => 'system', 'content' =>  
        strtr($GLOBALS["PROMPT_HEAD"] . "\n\n#Character details\n".$GLOBALS["HERIKA_PERS"] . $dynamicBiography . "\n\n#Knowledge\n" . $GLOBALS["OGHMA_HINT"]."\n\n#General Instructions\n". $GLOBALS["COMMAND_PROMPT"],
        ["#PLAYER_NAME#"=>$GLOBALS["PLAYER_NAME"],"#HERIKA_NAME#"=>$GLOBALS["HERIKA_NAME"]])

    );
    //avoid reinjecting command prompt that we have already appended
    $GLOBALS["COMMAND_PROMPT"] = "";
} else {
    $head[] = array('role' => 'system', 'content' =>  
        strtr($GLOBALS["PROMPT_HEAD"] . "\n\n#Character details\n".$GLOBALS["HERIKA_PERS"] . $dynamicBiography . "\n\n#General Instructions\n". $GLOBALS["COMMAND_PROMPT"],
        ["#PLAYER_NAME#"=>$GLOBALS["PLAYER_NAME"],"#HERIKA_NAME#"=>$GLOBALS["HERIKA_NAME"]])
    );
    //avoid reinjecting command prompt that we have already appended
    $GLOBALS["COMMAND_PROMPT"] = "";
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
    if (in_array($GLOBALS["CURRENT_CONNECTOR"],["koboldcpp","openai","google_openai","openrouter"]) && false ) {  // OLD SCHEMA
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

error_log("*TRACE:\t".__LINE__. "\t".__FILE__.":\t".(microtime(true) - $startTime));
//returnLines(["Mmm..let me think"]);

// Global switch. Needed id we need to stop processing because sme function requires it. Example, funcret conditions.
if (isset($GLOBALS["AVOID_LLM_CALL"])&&($GLOBALS["AVOID_LLM_CALL"])) {
    Logger::info("Terminated by AVOID_LLM_CALL");
    terminate();
}

// Diary stuff 
if ($gameRequest[0] == "diary") {
    // TO-DO move this to its own processor file.

    generateFollowerDiary($GLOBALS["HERIKA_NAME"],$gameRequest,"diary");
    Logger::info("Terminated after diary request");
    terminate();
}

/**********************
CALL INITIALIZATION
***********************/


audit_log(__FILE__." [PRE LLM CALL]  ".__LINE__);

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
if (!getenv("PHPUNIT_TEST")) {
    @ob_end_flush();
    @flush();
}


if (php_sapi_name()=="cli" && !getenv('PHPUNIT_TEST')) {
    echo PHP_EOL;
    file_put_contents("log/debug_comm_".basename(__FILE__).".log", print_r($GLOBALS["DEBUG_DATA"], true));

    //$db->delete("eventlog", "sess='cli'");

}


// POST PROCESS TASKS
if (isset($semaphore) && $semaphore)
    sem_release($semaphore);

while(!getenv("PHPUNIT_TEST") && ob_get_length() && ob_end_flush());
requireFilesRecursively(__DIR__.DIRECTORY_SEPARATOR."ext".DIRECTORY_SEPARATOR,"prepostrequest.php");
require(__DIR__.DIRECTORY_SEPARATOR."processor".DIRECTORY_SEPARATOR."postrequest.php");
requireFilesRecursively(__DIR__.DIRECTORY_SEPARATOR."ext".DIRECTORY_SEPARATOR,"postrequest.php");

?>
