<?php

/* Definitions and main includes */
error_reporting(E_ALL);

@define("STOPALL_MAGIC_WORD", "/wake up/i");

@define("MAXIMUM_SENTENCE_SIZE", 125);
@define("MINIMUM_SENTENCE_SIZE", 75);

date_default_timezone_set('Europe/Madrid');

$GLOBALS["AVOID_TTS_CACHE"]=true;
$GLOBALS["CHIM_NO_EXAMPLES"]=true; // When no assistant entry in history, will try to provide a bogus example.
$GLOBALS["MEMORY_THRESHOLD_MODIFIER"]=0;    // POST MEMORY
$GLOBALS["skyrim_start_date"] = '0201-08-17 00:00:00'; // default Skyrim start date. Alternate start mods could change this. Candidate for global settings.
$GLOBALS["SEMAPHORES_TIMEOUT"] = 300; 
$GLOBALS["TTS_INJECT_NONVERBAL_VOCALIZATION"] = true; // Spice the TTS with non-verbal vocalization when expressing strong emotion. 
$GLOBALS['use_emotions_expression'] = true; 


// Cooldown for some actions
$COOLDOWNMAP=[];

$path = dirname((__FILE__)) . DIRECTORY_SEPARATOR;
$GLOBALS["ENGINE_PATH"]=$path;

require($path . "conf/conf.php");
require_once($path . "lib/auditing.php");
require_once($path . "lib/model_dynmodel.php");
require_once($path . "lib/{$GLOBALS["DBDRIVER"]}.class.php");
$GLOBALS["db"] = new sql();
require_once($path . "lib/minimet5_service.php");
require_once($path . "lib/data_functions.php");
require_once($path . "lib/chat_helper_functions.php");
require_once($path . "lib/memory_helper_vectordb.php");
require_once($path . "lib/llm_randomizer.php");
require_once($path . "lib/utils_game_timestamp.php");
require_once($path . "lib/logger.php"); 
requireFilesRecursively(__DIR__."/ext/","globals.php");

// New profile system
require_once($path . "lib/core/api_badge.class.php");
require_once($path . "lib/core/llm_connector.class.php");
require_once($path . "lib/core/tts_connector.class.php");
require_once($path . "lib/core/npc_master.class.php");
require_once($path . "lib/core/core_profiles.class.php");
require_once($path . "lib/semaphore_manager.class.php");
require_once($path . "lib/pipeline_status.php");

// PARSE GET RESPONSE into $gameRequest
$cooldownPeriod = 600;


if (php_sapi_name()=="cli" && !getenv('PHPUNIT_TEST')) {
    // You can run this script directly with php: main.php "Player text"
    $GLOBALS["db"] = new sql();

    $latsRid=$db->fetchAll("select * from eventlog order by rowid desc LIMIT 1 OFFSET 0");
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
        $receivedData = mb_scrub(base64_decode(substr($_SERVER["QUERY_STRING"],5,strpos($_SERVER["QUERY_STRING"],"&")-5)));

    //error_log($receivedData." ".$_GET["profile"]);

}


if (!isset($FUNCTIONS_ARE_ENABLED)) {
    $FUNCTIONS_ARE_ENABLED=false;
}


while (!getenv('PHPUNIT_TEST') && ob_get_length() && ob_end_clean())	;
ignore_user_abort(true);
set_time_limit(1200);

$momentum=time();
$GLOBALS["runid"]=uniqid("run_",false);
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

// Handle deprecated events now processed by gamedata.php
if (in_array($gameRequest[0], ['updateequipment', 'updateinventory', 'updateskills', 'updatestats'])) {
    // These events are now handled by gamedata.php with JSON POST
    // The C++ plugin has been updated to use the new endpoint
    echo "DEPRECATED: This event is now handled by gamedata.php\n";
    if (!getenv("PHPUNIT_TEST")) {
        @ob_end_flush();
        @flush();
    }
    exit;
}

// Database Connection
$db = new sql();

// Load PLAYER_NAME from core_player table
try {
    require_once(__DIR__ . DIRECTORY_SEPARATOR . "lib" . DIRECTORY_SEPARATOR . "core" . DIRECTORY_SEPARATOR . "player.class.php");
    $player = new Player();
    $playerNameFromTable = $player->get('player_name');
    if ($playerNameFromTable !== null && $playerNameFromTable !== '') {
        $GLOBALS["PLAYER_NAME"] = $playerNameFromTable;
    } else {
        // Fallback to conf_opts
        $playerNameFromDb = $db->fetchOne("SELECT value FROM conf_opts WHERE id='PLAYER_NAME'");
        if ($playerNameFromDb && !empty($playerNameFromDb['value'])) {
            $GLOBALS["PLAYER_NAME"] = $playerNameFromDb['value'];
        }
    }
} catch (Exception $e) {
    // Fallback to conf_opts on error
    $playerNameFromDb = $db->fetchOne("SELECT value FROM conf_opts WHERE id='PLAYER_NAME'");
    if ($playerNameFromDb && !empty($playerNameFromDb['value'])) {
        $GLOBALS["PLAYER_NAME"] = $playerNameFromDb['value'];
    }
}

// Load narrator settings from core_narrator table
try {
    require_once(__DIR__ . DIRECTORY_SEPARATOR . "lib" . DIRECTORY_SEPARATOR . "core" . DIRECTORY_SEPARATOR . "narrator.class.php");
    $narrator = new Narrator();
    // Load all narrator settings into GLOBALS with proper type conversion
    // Falls back to existing GLOBALS values (from conf.php) if not found in database
    $narrator->loadIntoGlobals();
} catch (Exception $e) {
    // Fallback to conf.php values already loaded
    // Settings will use defaults or values from conf.php
}

require_once($path . "processor" .DIRECTORY_SEPARATOR."chim_modes.php");


requireFilesRecursively(__DIR__.DIRECTORY_SEPARATOR."ext".DIRECTORY_SEPARATOR,"preprocessing.php");

if (in_array($gameRequest[0],["inputtext","inputtext_s","ginputtext","ginputtext_s","narrator_inputtext","instruction","init"])) {
    // This is just a mark that user has made an input request. We will check later when waiting for LLm response 
    // if user has made input after initial request, so we can abort it.
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
    "util_location_name","util_faction_name","spellcast","npcspellcast","updateprofiles_batch_async","core_profile_assign","switchrace","combatbark",
    "util_location_npc","enable_bg","region","named_cell","snqe","named_cell_static"];

if (isset($GLOBALS["external_fast_commands"])) {
    $fast_commands = array_merge($fast_commands, $GLOBALS["external_fast_commands"]);
}

$GLOBALS["all_fast_commands"] = $fast_commands;

$semaphore_timeout = $GLOBALS["SEMAPHORES_TIMEOUT"] ?? 300;


// Use logical id "MAIN" so other code can still find $GLOBALS["SEMAPHORES"]["MAIN"]
if (!in_array($gameRequest[0],$fast_commands)) {
    if (!SemaphoreWait("MAIN", $semaphore_timeout, 1003, null)) {
        Logger::warn("[main] main semaphore wait failed for {$gameRequest[0]}");
        terminate();
    }
    Logger::info("Audit:Lock acquired by {$gameRequest[0]}");
} 

// adnpc has its custom semaphore, as it write files
if (in_array($gameRequest[0],["addnpc"])) {
    if (!SemaphoreWait("ADDNPC", $semaphore_timeout, 101, null)) {
        Logger::warn("[main] addnpc semaphore wait failed for {$gameRequest[0]}");
        terminate();
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
// Will insert data into database and will terminate.
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

// Will change  $gameRequest[3] with the rewritten LLM request.

$player_rewrite_speech = "";
if (in_array($gameRequest[0],["inputtext","inputtext_s","ginputtext","ginputtext_s","narrator_inputtext"]) && isset($GLOBALS["PLAYER_RESPEECH"]) && $GLOBALS["PLAYER_RESPEECH"]) {
    // Use preg_replace to remove the name and colon before the dialogue
    $cleaned_player_dialogue = addcslashes(preg_replace('/^[^:]+:/', '', $gameRequest[3]),'"');
    error_log($cleaned_player_dialogue);
    if (strpos($gameRequest[3],"**")===0 || strpos($cleaned_player_dialogue,"**")===0 ) {
        // If player speech starts with **
        error_log("Overwritting user prompt $cleaned_player_dialogue");

        //$newSpeech=file_get_contents(getBaseUrlForSpeech()."/HerikaServer/player_rewrite.php?speech=".urlencode($cleaned_player_dialogue));
        // Profile isn't loaded yet at this point, so derive the NPC name from the DB using the profile MD5
        $npcTarget = '';
        if (isset($_GET["profile"]) && $_GET["profile"] !== '' && $_GET["profile"] !== md5('The Narrator')) {
            $npcRow = $db->fetchOne("SELECT npc_name FROM core_npc_master WHERE md5='" . $db->escape($_GET["profile"]) . "' LIMIT 1");
            if ($npcRow && !empty($npcRow['npc_name'])) {
                $npcTarget = $npcRow['npc_name'];
            }
        }
        $escapedDialogue = escapeshellarg($cleaned_player_dialogue);
        $escapedNpc = escapeshellarg($npcTarget);
        $player_rewrite_speech=`php player_rewrite.php $escapedDialogue $escapedNpc`;
        $player_rewrite_speech=cleanResponse($player_rewrite_speech);
        $gameRequest[3]="{$GLOBALS["PLAYER_NAME"]}:$player_rewrite_speech";
        $GLOBALS["CHIM_EXECUTION_MODE"] = "AUTOCHAT"; //required when using STANDARD/WHISPER and ** prefix triggers speech database fix
    }
}



// Narrator initialization - ensure narrator data exists
// Narrator is now managed via core_narrator table, not core_npc_master
try {
    require_once(__DIR__ . DIRECTORY_SEPARATOR . "lib" . DIRECTORY_SEPARATOR . "core" . DIRECTORY_SEPARATOR . "narrator.class.php");
    $narrator = new Narrator();
    
    // Ensure narrator has a profile_id set (default to profile 1 if not set)
    $profileId = $narrator->getProfileId();
    if ($profileId === null) {
        $profileMgr = new CoreProfile();
        $defProfile = $profileMgr->getDefaultNarrator();
        if ($defProfile) {
            $narrator->set('profile_id', (string)$defProfile['id']);
        } else {
            // Fallback to profile 1
            $narrator->set('profile_id', '1');
        }
    }
    
    // Ensure voiceid is set
    if (!$narrator->get('voiceid')) {
        $narrator->set('voiceid', 'TheNarrator');
    }
} catch (Exception $e) {
    // Narrator initialization failed, will use defaults
    Logger::warn("Narrator initialization failed: " . $e->getMessage());
} 


// Profile loading
if (isset($_GET["profile"])) {
    
    // Initialize OVERRIDES array for all profile types
    $OVERRIDES["BOOK_EVENT_ALWAYS_NARRATOR"]=$GLOBALS["BOOK_EVENT_ALWAYS_NARRATOR"];
    $OVERRIDES["MINIME_T5"]=$GLOBALS["MINIME_T5"];
    $OVERRIDES["STTFUNCTION"]=$GLOBALS["STTFUNCTION"];
    $OVERRIDES["TTSFUNCTION_PLAYER"]=$GLOBALS["TTSFUNCTION_PLAYER"];
    $OVERRIDES["TTSFUNCTION_PLAYER_VOICE"]=$GLOBALS["TTSFUNCTION_PLAYER_VOICE"];
    $OVERRIDES["TTSFUNCTION_PLAYER_LANGUAGE"]=$GLOBALS["TTSFUNCTION_PLAYER_LANGUAGE"];
    
    // Check if this is The Narrator (by MD5)
    $isNarratorProfile = ($_GET["profile"] === md5('The Narrator'));
    
    // If this is The Narrator, use Narrator class instead of NpcMaster
    if ($isNarratorProfile) {
        require_once(__DIR__ . DIRECTORY_SEPARATOR . "lib" . DIRECTORY_SEPARATOR . "core" . DIRECTORY_SEPARATOR . "narrator.class.php");
        $narrator = new Narrator();
        $narratorData = $narrator->getNarratorData();
        
        // Load narrator settings into GLOBALS (includes NARRATOR_DIARY_ENABLED, etc.)
        $narrator->loadIntoGlobals();
        
        if ($narratorData && isset($narratorData["profile_id"])) {
            $profile = new CoreProfile();
            $currentProfileData = $profile->getById($narratorData["profile_id"]);
            
            $GLOBALS["CHIM_CORE_CURRENT_PROFILE_DATA"] = $currentProfileData;
            
            $connector = new LLMConnector();
            $npcMaster = new NpcMaster(); // Still needed for LLMRandomizer compatibility
            $connectorSlot = LLMRandomizer::getConnectorSlot($currentProfileData, $narratorData, $npcMaster);
            $connectorId = LLMRandomizer::getConnectorIdForSlot($currentProfileData, $connectorSlot);
            $currentConnectorData = $connector->getById($connectorId);
            
            $connector->setOldGlobals($currentConnectorData);
            $profile->setOldGlobals($currentProfileData);
            
            // Load narrator character data into GLOBALS (this sets PROMPT_HEAD and all character fields)
            $narrator->loadCharacterIntoGlobals();
            
            $GLOBALS["CHIM_CORE_CURRENT_CONNECTOR_DATA"] = $currentConnectorData;
            
            // Update pipeline status with mode and connector info
            $currentMode = $GLOBALS["db"]->fetchOne("SELECT value FROM conf_opts WHERE id='chim_mode'");
            pipeline_status_set_context(
                $currentMode['value'] ?? 'STANDARD',
                $currentConnectorData['label'] ?? '',
                $currentConnectorData['model'] ?? ''
            );
            
            error_log("[CORE SYSTEM] Using Narrator profile from core_narrator table, profile: {$currentProfileData["label"]}");
        } else {
            error_log("[CORE SYSTEM] Narrator profile not found, using defaults");
        }
    } else {
        // Regular NPC profile loading
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

        // Fallback: assign default profile if NPC has none (orphaned by profile deletion)
        if (empty($currentNpcData["profile_id"])) {
            $defProfile = $profile->getDefaultNpc();
            if ($defProfile) {
                $currentNpcData["profile_id"] = (int)$defProfile['id'];
                error_log("[CORE SYSTEM] NPC '{$currentNpcData["npc_name"]}' had no profile, assigned default profile #{$defProfile['id']}");
            }
        }

        $currentProfileData=$profile->getById($currentNpcData["profile_id"]);
        $GLOBALS["CHIM_CORE_CURRENT_PROFILE_DATA"]=$currentProfileData;
        $connector=new LLMConnector();
        
        // Use randomizer to determine which connector slot to use
        $connectorSlot = LLMRandomizer::getConnectorSlot($currentProfileData, $currentNpcData, $npcMaster);
        $connectorId = LLMRandomizer::getConnectorIdForSlot($currentProfileData, $connectorSlot);
        
        $currentConnectorData = $connector->getById($connectorId); 
        
    
        $connector->setOldGlobals($currentConnectorData);
        $profile->setOldGlobals($currentProfileData);
        $npcMaster->setOldGlobalsFromCurrentNpcData($currentNpcData);
        $GLOBALS["CHIM_CORE_CURRENT_NPC_DATA"] = $currentNpcData;

        $npcMaster->updateByArray($currentNpcData);
        
        $GLOBALS["CHIM_CORE_CURRENT_CONNECTOR_DATA"]=$currentConnectorData;
        
        // Update pipeline status with connector info
        $currentMode = $GLOBALS["db"]->fetchOne("SELECT value FROM conf_opts WHERE id='chim_mode'");
        pipeline_status_set_context(
            $currentMode['value'] ?? 'STANDARD',
            $currentConnectorData['label'] ?? '',
            $currentConnectorData['model'] ?? ''
        );
        
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
            $GLOBALS["CHIM_CORE_CURRENT_NPC_DATA"] = $currentNpcData;

            $profile=new CoreProfile();

            // Fallback: assign default profile if NPC has none (orphaned by profile deletion)
            if (empty($currentNpcData["profile_id"])) {
                $defProfile = $profile->getDefaultNpc();
                if ($defProfile) {
                    $currentNpcData["profile_id"] = (int)$defProfile['id'];
                    $npcMaster->updateByArray($currentNpcData);
                    error_log("[CORE SYSTEM] NPC '{$currentNpcData["npc_name"]}' had no profile, assigned default profile #{$defProfile['id']}");
                }
            }

            $currentProfileData=$profile->getById($currentNpcData["profile_id"]);
        
            $GLOBALS["CHIM_CORE_CURRENT_PROFILE_DATA"]=$currentProfileData;

            $connector=new LLMConnector();
            
            // Use randomizer to determine which connector slot to use
            $connectorSlot = LLMRandomizer::getConnectorSlot($currentProfileData, $currentNpcData, $npcMaster);
            $connectorId = LLMRandomizer::getConnectorIdForSlot($currentProfileData, $connectorSlot);
            
            $currentConnectorData = $connector->getById($connectorId); 
            
        
            $connector->setOldGlobals($currentConnectorData);
            $profile->setOldGlobals($currentProfileData);
            $npcMaster->setOldGlobalsFromCurrentNpcData($currentNpcData);
            $GLOBALS["CHIM_CORE_CURRENT_NPC_DATA"] = $currentNpcData;

            $GLOBALS["CHIM_CORE_CURRENT_CONNECTOR_DATA"]=$currentConnectorData;

            $debugLang = $GLOBALS["LLM_LANG"] ?? "unset";
            $debugOverrideTtsLang = $GLOBALS["PATCH_OVERRIDE_TTS_LANGUAGE"] ?? "unset";
            error_log("[CORE SYSTEM] Using new profile system , GLOBALS['LLM_LANG']:{$debugLang} profile: {$currentProfileData["label"]}");
            error_log("[CORE SYSTEM] GLOBALS['LLM_LANG']:{$debugLang} GLOBALS['PATCH_OVERRIDE_TTS_LANGUAGE']:{$debugOverrideTtsLang}");
        }
    }
    }
    
    $GLOBALS["BOOK_EVENT_ALWAYS_NARRATOR"]=$OVERRIDES["BOOK_EVENT_ALWAYS_NARRATOR"];
    //$GLOBALS["MINIME_T5"]=$OVERRIDES["MINIME_T5"];
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



if (in_array($gameRequest[0],["inputtext","inputtext_s","ginputtext","ginputtext_s","narrator_inputtext"]) ) {
    // Empty request
    if (empty($gameRequest[3]) || trim($gameRequest[3])=="{$GLOBALS["PLAYER_NAME"]}:") {
        error_log("[MAIN] Empty request... aborting");
        terminate();
    } else {
        error_log("[MAIN] Request: {$gameRequest[3]}");
    }
    
}

// Will enable functions and change $gameRequest[0] to cheatmode and $gameRequest[3] to a formatted instruction.
$GLOBALS["CHEAT_MODE"]=true;
if (in_array($gameRequest[0],["inputtext","inputtext_s","ginputtext","ginputtext_s","narrator_inputtext"]) && isset($GLOBALS["CHEAT_MODE"]) && $GLOBALS["CHEAT_MODE"]) {
    // Use preg_replace to remove the name and colon before the dialogue
    if (isset($_GET["profile"])) {
        $cleaned_player_dialogue = addcslashes(preg_replace('/^[^:]+:/', '', $gameRequest[3]),'"');
        $newSpeech=strtr($cleaned_player_dialogue,["#"=>""]);
        error_log($cleaned_player_dialogue);
        if (strpos($gameRequest[3],"#")===0 || strpos($cleaned_player_dialogue,"#")===0 ) {
            // If player speech starts with #
            $gameRequest[0]="cheatmode";
            $gameRequest[3]="<$newSpeech>";
            $GLOBALS["FUNCTIONS_ARE_ENABLED"]=true;

        }
    }
}

/* *****
Player TTS

Player TTS. We overwrite some confs an then restore them.
*/
if (in_array($gameRequest[0],["inputtext","inputtext_s","ginputtext","ginputtext_s","narrator_inputtext"]) && Translation::isSavePlayerTranslationEnabled()) {
   
    require(__DIR__."/processor/player_tts.php");
    
}


$GLOBALS["active_profile"]=md5($GLOBALS["HERIKA_NAME"]);


// End of profile selection

// This is the correct place, after parsing $gameRequest and before starting to do substitutions
// Will change connector, and apply narrator settings
if (($gameRequest[0]=="chatnf_book")&&($GLOBALS["BOOK_EVENT_ALWAYS_NARRATOR"])) {

    require_once(__DIR__ . DIRECTORY_SEPARATOR . "lib" . DIRECTORY_SEPARATOR . "core" . DIRECTORY_SEPARATOR . "narrator.class.php");
    $narrator = new Narrator();
    $narratorData = $narrator->getNarratorData();
    error_log("[CHIM CORE] [BOOK OVERRIDE] USING CORE PROFILE {$narratorData["npc_name"]}");

    // Load narrator character data into GLOBALS
    $narrator->loadCharacterIntoGlobals();

    $profile=new CoreProfile();
    $currentProfileData=$profile->getById($narratorData["profile_id"]);

    $GLOBALS["CHIM_CORE_CURRENT_PROFILE_DATA"]=$currentProfileData;

    $connector=new LLMConnector();
    
    // Use randomizer to determine which connector slot to use
    // getConnectorSlot expects npc data format, so we pass the narrator data array
    $npcMaster = new NpcMaster(); // Still needed for LLMRandomizer compatibility
    $connectorSlot = LLMRandomizer::getConnectorSlot($currentProfileData, $narratorData, $npcMaster);
    $connectorId = LLMRandomizer::getConnectorIdForSlot($currentProfileData, $connectorSlot);
    
    $currentConnectorData = $connector->getById($connectorId); 
    

    $connector->setOldGlobals($currentConnectorData);
    $profile->setOldGlobals($currentProfileData);

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
// Optional events

if (in_array($gameRequest[0],["info","infonpc","infonpc_close","infoloc","infoitems","chatme","chat","infoaction","death","itemfound",
    "travelcancel","infoplayer","status_msg","util_npcname","bleedout","spellcast","backgroundaction","reanimate","itempickup","npc_reanimated"])) {
    $gameRequest[3]=isset($gameRequest[3])?$gameRequest[3]:"";
    $lastInfoNpcData=$db->escape($gameRequest[3]);
    if (in_array($gameRequest[0],['infonpc','infoloc','infonpc_close','infoitems'])) {
        // Special cases
        if ($gameRequest[0] === 'infoitems') {
            // For infoitems, use shorter duplicate window (2 seconds) to allow refreshes
            $lastlogEqual=$db->fetchAll("select count(*) as n from eventlog where type = 'infoitems' and data='$lastInfoNpcData' and localts>".(time()-2));
            
            if (is_array($lastlogEqual) && isset($lastlogEqual[0]) && ($lastlogEqual[0]["n"]>0)) {
                terminate();
            }
        } else {
            // For other types, use normal 5 second window
            $lastlogEqual=$db->fetchAll("select count(*) as n from eventlog where type in ('infonpc','infoloc','infonpc_close') and data='$lastInfoNpcData' and localts>".(time()-5));
            if (is_array($lastlogEqual) && isset($lastlogEqual[0]) && ($lastlogEqual[0]["n"]>0)) {
                //error_log("Skipping {$gameRequest[0]}");
                terminate();
            }
        }
    }
    // NOTE: Automatic player name detection from infoplayer event is disabled
    // Player name is now managed through Player Management UI or quickstart menu
    if ($gameRequest[0] == 'infoplayer') {
        // infoplayer format: level:{},name:"{}",race:"{}",gender:"{}"
        // Player name detection is disabled - manage through Player Management
    }
    if (in_array($gameRequest[0],['backgroundaction','npc_reanimated'])) {
        
        require_once($GLOBALS["ENGINE_PATH"]."/processor/background_event.php");
    } else {
        logEvent($gameRequest);
    }
    terminate();
}

// Check if the gameRequest matches specific types
if (in_array($gameRequest[0], ["playerinfo", "newgame"])) {
    // NOTE: Automatic player name detection from game is disabled
    // Player name is now managed through Player Management UI or quickstart menu
    // This was formerly: Update player name from playerinfo event
    logEvent($gameRequest);
    terminate();
}


// Fake entry to mark time passing when bored event
if (in_array($gameRequest[0],["bored"])) {
    //Loggar::trace(" bored event - exec trace"); // debug
    if ((($gameRequest[2] ?? 0)-GetLastSpeechTs()) > 416667) { // 1/0.0000024 = 416667 
        $localGameRequest=$gameRequest;
        $localGameRequest[0]="infoaction";
            $localGameRequest[3].=". (Time passes without anyone in the group talking) ";
        logEvent($localGameRequest);
    }
    
    if ((isset($GLOBALS["BORED_EVENT_SERVERSIDE"])&&($GLOBALS["BORED_EVENT_SERVERSIDE"]))) {
        Logger::info("Redirecting bored event to rolemaster");
        `php service/manager.php rolemaster instruction ""`;
        terminate();

    }
}

// Combat bark event - log as infoaction and apply cooldown
if (in_array($gameRequest[0],["combatbark"])) {
    // Add configurable cooldown for combat barks to prevent spam (global across all NPCs)
    $combatBarkCooldownPeriod = isset($GLOBALS["COMBAT_BARK_COOLDOWN"]) ? intval($GLOBALS["COMBAT_BARK_COOLDOWN"]) : 90;
    
    // Use a global cooldown key (shared across all NPCs)
    $cooldownKey = "COMBAT_BARK_LAST_TIMESTAMP";
    
    // Fetch the last combat bark trigger timestamp
    $combatBarkRecord = $GLOBALS["db"]->fetchAll("SELECT value FROM conf_opts WHERE id='" . $GLOBALS["db"]->escape($cooldownKey) . "'");
    
    // Check if the timestamp exists in the database
    if (!empty($combatBarkRecord)) {
        $lastTrigger = (int) $combatBarkRecord[0]['value'];
        $timeElapsed = time() - $lastTrigger;

        if ($timeElapsed < $combatBarkCooldownPeriod) {
            // Cooldown is still active, exit
            Logger::info("COMBAT_BARK is on cooldown. Try again in " . ($combatBarkCooldownPeriod - $timeElapsed) . " seconds.");
            terminate();
        }
    }
    
    // Update the timestamp in the database to the current time
    $currentTimestamp = time();
    $GLOBALS["db"]->upsertRowOnConflict(
        "conf_opts",
        array(
            "id"    => $cooldownKey,
            "value" => $currentTimestamp
        ),
        'id'
    );
    
    $localGameRequest=$gameRequest;
    $localGameRequest[0]="infoaction";
    $localGameRequest[3].=" ({$GLOBALS["HERIKA_NAME"]} shouts during combat)";
    logEvent($localGameRequest);
}


// Only allow functions when explicit request
if (!in_array($gameRequest[0],["inputtext","inputtext_s","ginputtext","ginputtext_s","narrator_inputtext","instruction","welcome","cheatmode"])) {
    $FUNCTIONS_ARE_ENABLED=false;
}

// Force actions when instruction issued
if (in_array($gameRequest[0],["instruction"])) {
    $FUNCTIONS_ARE_ENABLED=true;
    // Remove any "SpeakerName:" prefix to prevent player/NPC attribution in instructions
    $gameRequest[3] = preg_replace('/^[^:]+:\s*/', '', $gameRequest[3]);
}

if (in_array($gameRequest[0],["suggestion"])) {
    $FUNCTIONS_ARE_ENABLED=false;
    // Remove any "SpeakerName:" prefix to prevent player/NPC attribution in suggestions
    $gameRequest[3] = preg_replace('/^[^:]+:\s*/', '', $gameRequest[3]);
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

$GLOBALS["RECHAT_IS_FINAL_ROUND"] = false;
$GLOBALS["RECHAT_PRECALCULATED_BUDGET"] = null;

if (in_array($gameRequest[0],["rechat","narration"]) ) {
    
    //RECHAT. Must choose if we continue conversation or no.
    // Note: narration is part of rechat system (random narrator interjections count as rechat rounds)

    $rechatHistory=DataRechatHistory();
    $rechatTurnHistory=DataRechatTurnHistory();
    $rechatTurnsSoFar=sizeof($rechatTurnHistory);
    $maxRechatRounds=max(1, intval($GLOBALS["RECHAT_H"]));

    $lastInputEvent = $db->fetchOne("SELECT ts FROM eventlog WHERE type in ('inputtext','inputtext_s','ginputtext','ginputtext_s','narrator_inputtext')
        and localts>".(time()-600)." ORDER BY rowid DESC LIMIT 1");
    $lastInputTs = isset($lastInputEvent["ts"]) ? intval($lastInputEvent["ts"]) : 0;
    $rechatSessionKey = md5(($GLOBALS["HERIKA_NAME"] ?? "unknown")."|".$lastInputTs."|".($_GET["profile"] ?? ""));
    $rechatBudgetFile = sys_get_temp_dir().DIRECTORY_SEPARATOR."chim_rechat_budget_".$rechatSessionKey.".json";
    $precalculatedBudget = null;

    if (file_exists($rechatBudgetFile)) {
        $budgetData = json_decode((string)@file_get_contents($rechatBudgetFile), true);
        if (is_array($budgetData)
            && isset($budgetData["budget"], $budgetData["last_input_ts"], $budgetData["created_at"])
            && intval($budgetData["budget"]) > 0
            && intval($budgetData["budget"]) <= $maxRechatRounds
            && intval($budgetData["last_input_ts"]) === $lastInputTs
            && (time() - intval($budgetData["created_at"])) <= 900) {
            $precalculatedBudget = intval($budgetData["budget"]);
        } else {
            @unlink($rechatBudgetFile);
        }
    }
    
    if ($rechatTurnsSoFar >= $maxRechatRounds) {   // TOO MUCH RECHAT
        Logger::info("Rechat discarded, rechatTurns:".$rechatTurnsSoFar.">={$maxRechatRounds}");
        if (file_exists($rechatBudgetFile)) {
            @unlink($rechatBudgetFile);
        }
        // Lets try to summarize
        SemaphoreManager::release("MAIN");
        while(ob_get_length() && ob_end_clean());
        require(__DIR__.DIRECTORY_SEPARATOR."processor".DIRECTORY_SEPARATOR."postrequest.php");
        terminate();
    }

    if ($precalculatedBudget === null) {
        $precalculatedBudget = $rechatTurnsSoFar;
        for ($i = $rechatTurnsSoFar; $i < $maxRechatRounds; $i++) {
            if (rand(1, 100) <= intval($GLOBALS["RECHAT_P"])) {
                $precalculatedBudget++;
            } else {
                break;
            }
        }

        if ($precalculatedBudget <= $rechatTurnsSoFar) {
            Logger::info("Rechat terminated by pre-calculated budget (0 remaining rounds)");
            @unlink($rechatBudgetFile);
            terminate();
        }

        @file_put_contents($rechatBudgetFile, json_encode([
            "budget" => $precalculatedBudget,
            "last_input_ts" => $lastInputTs,
            "created_at" => time()
        ]));

        Logger::info("Rechat pre-calculated budget={$precalculatedBudget}, turnsSoFar={$rechatTurnsSoFar}, actor={$GLOBALS["HERIKA_NAME"]}");
    }

    if ($rechatTurnsSoFar >= $precalculatedBudget) {
        Logger::info("Rechat terminated, pre-calculated budget exhausted ({$rechatTurnsSoFar}/{$precalculatedBudget})");
        @unlink($rechatBudgetFile);
        terminate();
    }

    $GLOBALS["RECHAT_PRECALCULATED_BUDGET"] = $precalculatedBudget;
    if (($rechatTurnsSoFar + 1) >= $precalculatedBudget) {
        $GLOBALS["RECHAT_IS_FINAL_ROUND"] = true;
        Logger::info("Rechat final round detected ({$rechatTurnsSoFar}+1/{$precalculatedBudget})");
    }

    // Process Oghma for rechat events using NPC's last dialogue
    // Use profile-based OGHMA_INFINIUM setting (not legacy conf.php $FEATURES["MISC"]["OGHMA_INFINIUM"])
    // Use helper function to handle string "false" values from form submissions
    if (!function_exists('isOghmaSettingEnabled')) {
        function isOghmaSettingEnabled($value) {
            if ($value === null) return false;
            if ($value === false || $value === 'false' || $value === '0' || $value === 0) return false;
            if ($value === true || $value === 'true' || $value === '1' || $value === 1) return true;
            return (bool)$value;
        }
    }
    $minimeEnabled = isOghmaSettingEnabled($GLOBALS["MINIME_T5"] ?? false);
    $oghmaCustomEnabled = isOghmaSettingEnabled($GLOBALS["OGHMA_CUSTOM"] ?? false);
    $oghmaInfiniumEnabled = isOghmaSettingEnabled($GLOBALS["OGHMA_INFINIUM"] ?? false);
    
    if (($minimeEnabled || $oghmaCustomEnabled) && $oghmaInfiniumEnabled) {
        $GLOBALS["OGHMA_CALLED"] = true;
        require(__DIR__."/processor/oghma.php"); // Process Oghma
    }
    
    if (sizeof($rechatHistory)>1) {
        // Lets make rechat wait a bit, so events while NPCs are speaking get into context// disabled if using new rechat fire event
        SemaphoreManager::release("MAIN");
        Logger::info("HOLDING RECHAT EVENT ".sizeof($rechatHistory));
        // Check if this conflicts with smart rechat
        // Is this doing something?
        $semaphore_timeout = $GLOBALS["SEMAPHORES_TIMEOUT"] ?? 300;
        if (!SemaphoreWait("MAIN", $semaphore_timeout, 1007, function() use ($db, $gameRequest) {
            //$user_input_after=$db->fetchAll("select count(*) as N from eventlog where type='user_input' and ts>$gameRequest[1]"); // 72 ms 
            $user_input_after=$db->fetchAll("SELECT rowid as N FROM eventlog WHERE type='user_input' AND ts>{$gameRequest[1]} ORDER BY rowid DESC LIMIT 1 "); // faster, 1.5 ms
            if (isset($user_input_after[0])) {
                if (isset($user_input_after[0]["N"]))
                    if (intval($user_input_after[0]["N"])>0) {
                        Logger::warn("[main] rechat event - generation stopped because user_input. " .__FILE__ . " " . __LINE__); // debug
                        terminate();
                    }
            }
            return true;
        })) {
            Logger::warn("[main] rechat event - semaphore wait failed in " .__FILE__ . " " . __LINE__);
            terminate();
        }
    }

    // RANDOM NARRATION - Narrator visual scene descriptions
    // Trigger after any NPC response (after first NPC responds to player)
    // AND only on "rechat" events (not on events already converted to "narration")
    // AND only if The Narrator wasn't the last speaker (prevent consecutive narrations)
    if (!empty($GLOBALS["RANDOM_NARATION"]) && $GLOBALS["RANDOM_NARATION"] && empty($GLOBALS["RECHAT_IS_FINAL_ROUND"]) && $gameRequest[0] === "rechat" && sizeof($rechatHistory) >= 1) {
        // Check if the last event was a narration event (if so, skip to prevent consecutive narrations)
        $lastEvent = $db->fetchOne("SELECT type FROM eventlog WHERE type IN ('rechat', 'narration') ORDER BY gamets DESC, ts DESC LIMIT 1");
        $wasLastNarration = ($lastEvent && $lastEvent['type'] === 'narration');
        
        // Check cooldown - ensure at least N non-narration events occurred since last narration
        $cooldownRounds = isset($GLOBALS["RANDOM_NARRATION_COOLDOWN"]) ? intval($GLOBALS["RANDOM_NARRATION_COOLDOWN"]) : 2;
        $eventsSinceNarration = $db->fetchOne("
            SELECT COUNT(*) as count 
            FROM eventlog 
            WHERE type IN ('rechat', 'inputtext', 'inputtext_s') 
            AND gamets > (
                SELECT COALESCE(MAX(gamets), 0) 
                FROM eventlog 
                WHERE type = 'narration'
            )
        ");
        
        $eventCount = $eventsSinceNarration ? intval($eventsSinceNarration['count']) : 999;
        
        // Skip if cooldown hasn't passed
        if ($eventCount < $cooldownRounds) {
            Logger::info("[RANDOM_NARRATION] Skipped - Cooldown active (events since last: {$eventCount}, required: {$cooldownRounds})");
        } else if ($wasLastNarration) {
            Logger::info("[RANDOM_NARRATION] Skipped - Last event was narration, preventing consecutive narrations");
        } else {
            $randomChance = rand(1, 100);
            $narrationChance = isset($GLOBALS["RANDOM_NARATION_CHANCE"]) ? intval($GLOBALS["RANDOM_NARATION_CHANCE"]) : 15;
            
            if ($randomChance <= $narrationChance) {
                Logger::info("[RANDOM_NARRATION] Triggered (chance: $randomChance <= $narrationChance)");
            
            // Switch to The Narrator profile temporarily
            require_once(__DIR__ . DIRECTORY_SEPARATOR . "lib" . DIRECTORY_SEPARATOR . "core" . DIRECTORY_SEPARATOR . "narrator.class.php");
            $narrator = new Narrator();
            $narratorData = $narrator->getNarratorData();
            
            if ($narratorData && isset($narratorData["profile_id"])) {
                // Store current profile data
                $originalHerikaName = $GLOBALS["HERIKA_NAME"];
                
                // Load Narrator profile - set connector and profile first, character data last
                $profile = new CoreProfile();
                $currentProfileData = $profile->getById($narratorData["profile_id"]);
                
                $GLOBALS["CHIM_CORE_CURRENT_PROFILE_DATA"] = $currentProfileData;
                
                $connector = new LLMConnector();
                $npcMaster = new NpcMaster(); // Still needed for LLMRandomizer compatibility
                $connectorSlot = LLMRandomizer::getConnectorSlot($currentProfileData, $narratorData, $npcMaster);
                $connectorId = LLMRandomizer::getConnectorIdForSlot($currentProfileData, $connectorSlot);
                $currentConnectorData = $connector->getById($connectorId);
                
                $connector->setOldGlobals($currentConnectorData);
                $profile->setOldGlobals($currentProfileData);
                
                // Load narrator character data into GLOBALS
                $narrator->loadCharacterIntoGlobals();
                
                $GLOBALS["CHIM_CORE_CURRENT_CONNECTOR_DATA"] = $currentConnectorData;
                
                // Load random narration prompt from database with fallback
                $narrationPrompt = null;
                try {
                    $promptData = $db->fetchOne("SELECT custom_prompt, default_prompt FROM prompts WHERE prompt_key = 'random_narration_prompt'");
                    if ($promptData) {
                        // Use custom_prompt if set, otherwise use default_prompt
                        $narrationPrompt = (!empty($promptData['custom_prompt'])) ? $promptData['custom_prompt'] : $promptData['default_prompt'];
                        Logger::info("[RANDOM_NARRATION] Loaded prompt from database (custom: " . (!empty($promptData['custom_prompt']) ? 'yes' : 'no') . ")");
                    }
                } catch (Exception $e) {
                    Logger::warn("[RANDOM_NARRATION] Failed to load prompt from database, using hardcoded fallback: " . $e->getMessage());
                }
                
                // Hardcoded fallback if database query failed or returned no results
                if (!$narrationPrompt) {
                    $narrationPrompt = 'Describe the current scene visually using ONLY details from the provided context. Focus on the characters present - their appearance, expressions, body language, and what they\'re wearing. Include environmental details like lighting and atmosphere. Keep it grounded and concise (2-3 sentences). Do not invent new information, advance the plot, or include dialogue.';
                    Logger::info("[RANDOM_NARRATION] Using hardcoded fallback prompt");
                }
                
                // Mark this as a narration event (not a regular rechat)
                $gameRequest[0] = "narration";
                
                // Send event type header IMMEDIATELY before any output
                // This must be done early so C++ plugin knows this is narration
                header("X-Event-Type: narration");
                Logger::info("[RANDOM_NARRATION] Sent X-Event-Type: narration header");
                
                // Store narration prompt for later injection (after prompts.php is loaded)
                $GLOBALS["RANDOM_NARRATION_PROMPT"] = $narrationPrompt;
                
                Logger::info("[RANDOM_NARRATION] Executing as The Narrator with narration request");
                
                // Process will continue with Narrator profile loaded
                // After response, it will send to game as normal narrator dialogue
            } else {
                Logger::warn("[RANDOM_NARRATION] Skipped - Narrator profile not found");
            }
            } else {
                Logger::trace("[RANDOM_NARRATION] Not triggered (chance: $randomChance > $narrationChance)");
            }
        }
    }

    $sqlfilter=" and type in ('prechat','inputtext','ginputtext','infonpc','infonpc_close','logaction','infoaction','death','itemfound') or (type='chat' and data like '(Context%') ";  // Use prechat
    // chat entries starting by "(Context%" are standard skyrim dialogue

    $FUNCTIONS_ARE_ENABLED=false;       // Enabling this can be funny => CHAOS MODE

} else
    $sqlfilter=" and type<>'prechat' "; // Will dismiss prechat entries by default. prechat are LLM responses still not displayed in-game


// Non-LLM request handling.

require(__DIR__.DIRECTORY_SEPARATOR."processor".DIRECTORY_SEPARATOR."comm.php");

// Handle narrator_welcome events (must be AFTER comm.php which converts init to narrator_welcome)
if ($gameRequest[0] == "narrator_welcome") {
    // Load narrator profile with full connector configuration
    require_once(__DIR__ . DIRECTORY_SEPARATOR . "lib" . DIRECTORY_SEPARATOR . "core" . DIRECTORY_SEPARATOR . "narrator.class.php");
    $narrator = new Narrator();
    $narratorData = $narrator->getNarratorData();
    
    if ($narratorData && isset($narratorData["profile_id"])) {
        // Load Narrator profile - set connector and profile first, character data last
        $profile = new CoreProfile();
        $currentProfileData = $profile->getById($narratorData["profile_id"]);
        
        if (!$currentProfileData) {
            Logger::error("[NARRATOR_WELCOME] Profile ID {$narratorData['profile_id']} not found in core_profiles table");
            Logger::error("[NARRATOR_WELCOME] Please ensure The Narrator has a valid profile assigned");
            terminate();
        }
        
        $GLOBALS["CHIM_CORE_CURRENT_PROFILE_DATA"] = $currentProfileData;
        
        $connector = new LLMConnector();
        
        // Get global connector slot (respects in-game mode)
        $db = $GLOBALS['db'];
        $result = $db->fetchOne("SELECT value FROM conf_opts WHERE id='chim_profile_model'");
        $connectorSlot = (isset($result['value']) && $result['value'] >= 1 && $result['value'] <= 4) 
            ? (int)$result['value'] 
            : 1;
        
        $connectorId = LLMRandomizer::getConnectorIdForSlot($currentProfileData, $connectorSlot);
        
        $slotName = LLMRandomizer::getSlotName($connectorSlot);
        
        if (!$connectorId) {
            Logger::error("[NARRATOR_WELCOME] No connector assigned to {$slotName} slot (slot {$connectorSlot}) for profile '{$currentProfileData['label']}'");
            Logger::error("[NARRATOR_WELCOME] Please configure connectors for The Narrator's profile:");
            Logger::error("[NARRATOR_WELCOME]   - Go to Profile Management > Edit The Narrator's profile");
            Logger::error("[NARRATOR_WELCOME]   - Assign connectors to: Standard (slot 1), Fast (slot 2), Powerful (slot 3), Experimental (slot 4)");
            Logger::error("[NARRATOR_WELCOME]   - The system uses the ingame mode setting to pick which connector to use");
            terminate();
        }
        
        $currentConnectorData = $connector->getById($connectorId);
        
        if (!$currentConnectorData) {
            Logger::error("[NARRATOR_WELCOME] Connector ID {$connectorId} not found in core_connectors table");
            terminate();
        }
        
        $connector->setOldGlobals($currentConnectorData);
        $profile->setOldGlobals($currentProfileData);
        
        // Load narrator character data into GLOBALS
        $narrator->loadCharacterIntoGlobals();
        
        $GLOBALS["CHIM_CORE_CURRENT_CONNECTOR_DATA"] = $currentConnectorData;
        
        // Set CURRENT_CONNECTOR for compatibility with old code paths
        $GLOBALS["CURRENT_CONNECTOR"] = $currentConnectorData['driver'];
        
        // Load welcome prompt from prompts table with hardcoded fallback
        $welcomePrompt = null;
        try {
            $promptData = $db->fetchOne("SELECT custom_prompt, default_prompt FROM prompts WHERE prompt_key = 'narrator_welcome_prompt'");
            if ($promptData) {
                $welcomePrompt = (!empty($promptData['custom_prompt'])) 
                    ? $promptData['custom_prompt'] 
                    : $promptData['default_prompt'];
            }
        } catch (Exception $e) {
            Logger::warn("[NARRATOR_WELCOME] Failed to load prompt from database: " . $e->getMessage());
        }
        
        // Hardcoded fallback if database query failed
        if (!$welcomePrompt) {
            $welcomePrompt = "Give a brief (2-3 sentence) recap of recent events and adventures. Welcome the player back to their journey.";
        }
        
        $GLOBALS["NARRATOR_WELCOME_PROMPT"] = $welcomePrompt;
    } else {
        Logger::error("[NARRATOR_WELCOME] Narrator profile_id not found in core_narrator table");
        Logger::error("[NARRATOR_WELCOME] Please configure The Narrator in Narrator Management");
        terminate();
    }
}

// Handle narrator_quest_comment events (must be AFTER comm.php which converts quest to narrator_quest_comment)
if ($gameRequest[0] == "narrator_quest_comment") {
    // Load narrator profile with full connector configuration
    require_once(__DIR__ . DIRECTORY_SEPARATOR . "lib" . DIRECTORY_SEPARATOR . "core" . DIRECTORY_SEPARATOR . "narrator.class.php");
    $narrator = new Narrator();
    $narratorData = $narrator->getNarratorData();
    
    if ($narratorData && isset($narratorData["profile_id"])) {
        // Load Narrator profile - set connector and profile first, character data last
        $profile = new CoreProfile();
        $currentProfileData = $profile->getById($narratorData["profile_id"]);
        
        if (!$currentProfileData) {
            Logger::error("[NARRATOR_QUEST_COMMENT] Profile ID {$narratorData['profile_id']} not found in core_profiles table");
            Logger::error("[NARRATOR_QUEST_COMMENT] Please ensure The Narrator has a valid profile assigned");
            terminate();
        }
        
        $GLOBALS["CHIM_CORE_CURRENT_PROFILE_DATA"] = $currentProfileData;
        
        $connector = new LLMConnector();
        
        // Get global connector slot (respects in-game mode)
        $db = $GLOBALS['db'];
        $result = $db->fetchOne("SELECT value FROM conf_opts WHERE id='chim_profile_model'");
        $connectorSlot = (isset($result['value']) && $result['value'] >= 1 && $result['value'] <= 4) 
            ? (int)$result['value'] 
            : 1;
        
        $connectorId = LLMRandomizer::getConnectorIdForSlot($currentProfileData, $connectorSlot);
        
        $slotName = LLMRandomizer::getSlotName($connectorSlot);
        
        if (!$connectorId) {
            Logger::error("[NARRATOR_QUEST_COMMENT] No connector assigned to {$slotName} slot (slot {$connectorSlot}) for profile '{$currentProfileData['label']}'");
            Logger::error("[NARRATOR_QUEST_COMMENT] Please configure connectors for The Narrator's profile:");
            Logger::error("[NARRATOR_QUEST_COMMENT]   - Go to Profile Management > Edit The Narrator's profile");
            Logger::error("[NARRATOR_QUEST_COMMENT]   - Assign connectors to: Standard (slot 1), Fast (slot 2), Powerful (slot 3), Experimental (slot 4)");
            Logger::error("[NARRATOR_QUEST_COMMENT]   - The system uses the ingame mode setting to pick which connector to use");
            terminate();
        }
        
        $currentConnectorData = $connector->getById($connectorId);
        
        if (!$currentConnectorData) {
            Logger::error("[NARRATOR_QUEST_COMMENT] Connector ID {$connectorId} not found in core_connectors table");
            terminate();
        }
        
        $connector->setOldGlobals($currentConnectorData);
        $profile->setOldGlobals($currentProfileData);
        
        // Load narrator character data into GLOBALS
        $narrator->loadCharacterIntoGlobals();
        
        $GLOBALS["CHIM_CORE_CURRENT_CONNECTOR_DATA"] = $currentConnectorData;
        
        // Set CURRENT_CONNECTOR for compatibility with old code paths
        $GLOBALS["CURRENT_CONNECTOR"] = $currentConnectorData['driver'];
    } else {
        Logger::error("[NARRATOR_QUEST_COMMENT] Narrator profile_id not found in core_narrator table");
        Logger::error("[NARRATOR_QUEST_COMMENT] Please configure The Narrator in Narrator Management");
        terminate();
    }
}

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

// Inject training function for trainer NPCs (only if Training is enabled)
if (in_array('Training', $GLOBALS["ENABLED_FUNCTIONS"]) && isset($currentNpcData) && $currentNpcData && $GLOBALS["HERIKA_NAME"] != "The Narrator") {
    $npcMaster = new NpcMaster();
    $extended = $npcMaster->getExtendedData($currentNpcData);
    if (isset($extended['class']['teaches']) && !empty($extended['class']['teaches'])) {
        $skill = $extended['class']['teaches'];
        $maxLevel = isset($extended['class']['max_training_level']) ? intval($extended['class']['max_training_level']) : 0;
        
        // Convert level to tier name
        $tier = 'Novice';
        if ($maxLevel >= 100) {
            $tier = 'Master';
        } elseif ($maxLevel >= 75) {
            $tier = 'Expert';
        } elseif ($maxLevel >= 50) {
            $tier = 'Adept';
        } elseif ($maxLevel >= 25) {
            $tier = 'Apprentice';
        }
        
        $functionName = "Train" . ucfirst($skill);
        $GLOBALS["FUNCTIONS"][] = [
            "name" => $functionName,
            "description" => "{$GLOBALS["HERIKA_NAME"]} offers {$tier} {$skill} training.",
            "parameters" => [
                "type" => "object",
                "properties" => [
                    "target" => [
                        "type" => "string",
                        "description" => "Keep it blank",
                    ],
                ],
                "required" => [""],
            ],
        ];
        $GLOBALS["ENABLED_FUNCTIONS"][] = $functionName;
        $GLOBALS["F_NAMES"][$functionName] = $functionName;
    }
}

// Inject random narration prompt if this is a narration event
// This must happen AFTER prompts.php is loaded to avoid being overwritten
// Inject as the "cue" so it appears as the penultimate user message (like section 81 for normal NPCs)
if (isset($GLOBALS["RANDOM_NARRATION_PROMPT"]) && $gameRequest[0] == "narration") {
    $PROMPTS["narration"]["cue"] = [$GLOBALS["RANDOM_NARRATION_PROMPT"]];
    Logger::info("[RANDOM_NARRATION] Injected narration prompt as cue");
}

// Inject narrator welcome prompt if this is a narrator_welcome event
if ($gameRequest[0] == "narrator_welcome") {
    $welcomePrompt = isset($GLOBALS["NARRATOR_WELCOME_PROMPT"]) && !empty($GLOBALS["NARRATOR_WELCOME_PROMPT"]) 
        ? $GLOBALS["NARRATOR_WELCOME_PROMPT"]
        : "Give a brief (2-3 sentence) recap of recent events and adventures. Welcome the player back to their journey.";
    
    $PROMPTS["narrator_welcome"]["cue"] = [$welcomePrompt];
}

// Take care of override request if needed..
require(__DIR__.DIRECTORY_SEPARATOR."processor".DIRECTORY_SEPARATOR."request.php");

if (!empty($GLOBALS["RECHAT_IS_FINAL_ROUND"]) && $gameRequest[0] === "rechat" && !empty($request)) {
    $request .= " This is your final response in this exchange. Conclude naturally for now without abruptly ending the relationship.";
    Logger::info("Rechat final-round close instruction appended to request (budget={$GLOBALS["RECHAT_PRECALCULATED_BUDGET"]})");
}




/*
 Safe stop
*/
Logger::info("Current STOPALL_MAGIC_WORD ".STOPALL_MAGIC_WORD);
if (in_array($gameRequest[0],["inputtext","inputtext_s","ginputtext","ginputtext_s","narrator_inputtext","instruction"]) && preg_match(STOPALL_MAGIC_WORD, $gameRequest[3]) === 1) {
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
if ($gameRequest[0] != "diary" && $gameRequest[0] != "cheatmode") {
    // Filter out combat grunts
    $shouldLog = true;
    $data = isset($gameRequest[3]) ? $gameRequest[3] : '';
    
    // List of combat grunts to filter
    $combatGrunts = [
        'Unff!', 'Argh!', 'Off!', 'Ugh!', 'Gah!', 'Oof!', 'Urgh!', 'Ngh!', 
        'Aah!', 'Ouch!', 'Grr!', 'Hah!', 'Huh!', 'Hmm!', 'Oof', 'Argh', 
        'Unff', 'Off', 'Ugh', 'Gah', 'Aah', 'Ouch', 'Hah',
        'Arghhh!', 'Yarghhh!', 'Rrrghhh!', 'Uuuuhhhnnnn... aaarrrghhh...',
        'Ooohhhh, ahhhrrrghhhh... uuuuggghhh.', 'Yrrrgh!', 'Weergh!', 'Yeagh!',
        'Hyargh!', 'Nyyarrggh!', 'Yearrgh!', 'Ah...', 'Hmph.', 'Hhyyarargghhhh!',
        'Aaaayyyaarrrrgghh!', 'Rrrraaaaarrggghhhh!', 'Ahhhhh!', 'Heh heh...',
        'Grrargh!'
    ];
    
    // Check if data is just a combat grunt
    $trimmedData = trim($data);
    if (in_array($trimmedData, $combatGrunts)) {
        $shouldLog = false;
        error_log("[FILTER] Blocked combat grunt from eventlog: {$trimmedData}");
    }
    
    if ($shouldLog) {
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

}

// Check if this event  has been disabled 
if (isset($GLOBALS["PROMPTS"][$gameRequest[0]]["extra"]["dontuse"])) {
    //Logger::warn(" event=".$gameRequest[0]." use=". (!($GLOBALS["PROMPTS"][$gameRequest[0]]["extra"]["dontuse"]) ? "Y" : "N") ." - exec trace"); // debug
    if ($GLOBALS["PROMPTS"][$gameRequest[0]]["extra"]["dontuse"])
        terminate();
}

// Filter bleedout-related instructions based on RPG_COMMENTS setting
// Note: Papyrus RecoverFromCombat sends "instruction" events when NPCs enter bleedout
// These would trigger automatic AI responses, so we check RPG_COMMENTS to prevent that
if ($gameRequest[0] === 'instruction' && isset($gameRequest[3])) {
    if (stripos($gameRequest[3], 'wounded bleedingout') !== false || stripos($gameRequest[3], 'lost combat') !== false) {
        // Check if bleedout RPG comments are enabled
        if (empty($GLOBALS["RPG_COMMENTS"]) || !in_array('bleedout', $GLOBALS["RPG_COMMENTS"])) {
            Logger::info("Bleedout instruction skipped (RPG comment disabled)");
            terminate();
        }
        
        // Apply RPG_COMMENTS_CHANCE probability
        $chance = 100;
        if (isset($GLOBALS["RPG_COMMENTS_CHANCE"])) {
            $chance = intval($GLOBALS["RPG_COMMENTS_CHANCE"]);
        }
        $chance = max(0, min(100, $chance));
        
        if ($chance < 100) {
            $roll = rand(1, 100);
            if ($roll > $chance) {
                Logger::info("Bleedout instruction skipped (failed chance roll: {$roll} > {$chance})");
                terminate();
            }
        }
    }
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

// Ensure contextDataHistoric is an array
if (!is_array($contextDataHistoric)) {
    $contextDataHistoric = [];
}

// Info about location and npcs in first position
// Check $nearbySections
$contextDataWorld = DataLastInfoFor("", -2,true);

// Ensure contextDataWorld is an array
if (!is_array($contextDataWorld)) {
    $contextDataWorld = [];
}

// Add current motto to COMMAND_PROMPT
if (isset($GLOBALS["CURRENT_TASK"]) && $GLOBALS["CURRENT_TASK"] && $gameRequest[0] != "diary") {
    if ((!$GLOBALS["IS_NPC"])||($GLOBALS["HERIKA_NAME"]=="The Narrator")) {
        $task=DataGetCurrentTask();
        if (empty($task)) {
            $task="\n\n#Active Quests\nNo active quests right now.";
        }
        $GLOBALS["COMMAND_PROMPT"].=$task;
    } else {
        Logger::info("Task avoided {$GLOBALS["IS_NPC"]} ");
    }
}

// Offer memory in CONTEXT 


if (in_array($gameRequest[0],["inputtext","inputtext_s","ginputtext","ginputtext_s","narrator_inputtext","rechat","narration","continue"]) ) {

    $memoryInjection=offerMemory($gameRequest);
    //Logger::info("Memory injection:".json_encode($memoryInjection));

    if (!empty($memoryInjection)) {
        
        //$memoryInjectionCtx[]= array('role' => 'user', 'content' => $gameRequest[3]);
        $memoryInjectionCtx[]= array('role' => 'user', 'content' => "<memory> {$GLOBALS["HERIKA_NAME"]} remembers this: [$memoryInjection] </memory>");
        //$GLOBALS["COMMAND_PROMPT"].="'{$gameRequest[3]}'\n{$GLOBALS["HERIKA_NAME"]}:$memoryInjection\n";
        
    } else {
        $memoryInjectionCtx=[];
        $request=str_replace($GLOBALS["MEMORY_STATEMENT"],"",$request);//Cleans the memory statement.
            
    }
} else
     $memoryInjectionCtx=[];



// array('role' => $currentSpeaker, 'content' => implode("\n", $buffer));


// Rechat case
if (in_array($gameRequest[0],["rechat","narration"]) ) {
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

// Instruction reinforcement
if (in_array($gameRequest[0],["instruction"]) ) {
    
    $GLOBALS["PATCH_PROMPT_ENFORCE_ACTIONS"]=true;
    $GLOBALS["COMMAND_PROMPT_ENFORCE_ACTIONS"]="(If {$GLOBALS["HERIKA_NAME"]} is just speaking, use action \"Talk\". If another action is even remotely contextually appropriate, use it, even if in doubt)";
    
}

// Enforce actions
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
$COOLDOWNMAP["Inspect"]=300/0.00864;
$COOLDOWNMAP["Relax"]=180/0.00864;
$COOLDOWNMAP["MakeAToast"]=60/0.00864;
$COOLDOWNMAP["Toast"]=60/0.00864;
$COOLDOWNMAP["StartRitualCeremony"]=60/0.00864;
$COOLDOWNMAP["Follow"]=60/0.00864;
$COOLDOWNMAP["FollowPlayer"]=60/0.00864;

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
    if ((rand(0,5)!==0)){ // Remember goal from time to time
        $GLOBALS["PATCH_PROMPT_ENFORCE_ACTIONS"]=true;
        $GLOBALS["COMMAND_PROMPT_ENFORCE_ACTIONS"]="(If {$GLOBALS["HERIKA_NAME"]} is just speaking, use action \"Talk\". If another action is even remotely contextually appropriate, use it, even if in doubt)";
        $GLOBALS["COMMAND_PROMPT_ENFORCE_ACTIONS"].="(consider character's goal and traits, check #Storyline as this actor is part of a storyline)";
        /*if (isset($GLOBALS["ENFORCE_ACTIONS_PROMPT"]) && $GLOBALS["ENFORCE_ACTIONS_PROMPT"]) {
            $GLOBALS["PATCH_PROMPT_ENFORCE_ACTIONS"]=true;
            if (isset($GLOBALS["COMMAND_PROMPT_ENFORCE_ACTIONS_LANG"]))
                $GLOBALS["COMMAND_PROMPT_ENFORCE_ACTIONS"]=$GLOBALS["COMMAND_PROMPT_ENFORCE_ACTIONS_LANG"];
            else
                $GLOBALS["COMMAND_PROMPT_ENFORCE_ACTIONS"]="(If {$GLOBALS["HERIKA_NAME"]} is just speaking, use action \"Talk\". If another action is even remotely contextually appropriate, use it, even if in doubt)";
        }*/
    }
} 

// MINIME_T5 STUFF, command assistant

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
                    //$memoryInjectionCtx=[]; // Disable memories when command.
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

// OGHMA STUFF - Only run if Oghma is enabled in profile
// Helper function to properly check boolean values (handles string "false" from form submissions)
if (!function_exists('isOghmaSettingEnabled')) {
    function isOghmaSettingEnabled($value) {
        if ($value === null) return false;
        if ($value === false || $value === 'false' || $value === '0' || $value === 0) return false;
        if ($value === true || $value === 'true' || $value === '1' || $value === 1) return true;
        return (bool)$value;
    }
}

$minimeEnabled = isOghmaSettingEnabled($GLOBALS["MINIME_T5"] ?? false);
$oghmaCustomEnabled = isOghmaSettingEnabled($GLOBALS["OGHMA_CUSTOM"] ?? false);
$oghmaInfiniumEnabled = isOghmaSettingEnabled($GLOBALS["OGHMA_INFINIUM"] ?? false);

// Debug: Log the actual values being checked BEFORE the conditional
error_log("[OGHMA CHECK] MINIME_T5=" . var_export($GLOBALS["MINIME_T5"] ?? null, true) 
    . " (enabled=" . ($minimeEnabled ? 'Y' : 'N') . ")"
    . " | OGHMA_CUSTOM=" . var_export($GLOBALS["OGHMA_CUSTOM"] ?? null, true)
    . " (enabled=" . ($oghmaCustomEnabled ? 'Y' : 'N') . ")"
    . " | OGHMA_INFINIUM=" . var_export($GLOBALS["OGHMA_INFINIUM"] ?? null, true)
    . " (enabled=" . ($oghmaInfiniumEnabled ? 'Y' : 'N') . ")");

if (($minimeEnabled || $oghmaCustomEnabled) && $oghmaInfiniumEnabled) {
    if (!isset($GLOBALS["OGHMA_CALLED"])) {// Avoid double call
        require(__DIR__."/processor/oghma.php");
        $GLOBALS["OGHMA_CALLED"] = true;
    }
}

if (sizeof($memoryInjectionCtx)>0) {
    // Persist memory injection
    $gameRequestCopy=$gameRequest;
    $gameRequestCopy[0]="infoaction";
    $gameRequestCopy[3]=$memoryInjectionCtx[0]["content"];
    logEvent($gameRequestCopy,$GLOBALS["HERIKA_NAME"]);// Memory log only avaibale to current NPC.
}

$contextDataFull = array_merge($contextDataWorld, $contextDataHistoric);

// If enabled, hide narrator dialogue lines from NPC prompts, but keep narrator context
if (!empty($GLOBALS["HIDE_NARRATOR_DIALOGUE"]) && $GLOBALS["HERIKA_NAME"] !== "The Narrator") {
    $isContextNarratorLine = function(string $content): bool {
        if (strpos($content, 'The Narrator:') !== 0) return false;
        // Keep known context markers
        if (preg_match('/^The Narrator:\s*\(/', $content)) return true; // parenthetical events
        if (strpos($content, 'The Narrator: background dialogue:') === 0) return true;
        if (strpos($content, 'The Narrator: action moved to new location:') === 0) return true;
        if (strpos($content, 'The Narrator: SCENARIO CHANGE') === 0) return true;
        if (preg_match('/^The Narrator:\s*about\s+\d+\s+hours\s+later/i', $content)) return true;
        return false;
    };
    // Filter only historic part to avoid dropping world info
    $contextDataHistoric = array_values(array_filter($contextDataHistoric, function($entry) use ($isContextNarratorLine){
        if (!is_array($entry)) return true;
        $content = isset($entry['content']) ? (string)$entry['content'] : '';
        // Remove user lines that are explicitly directed to The Narrator
        if (strpos($content, '(Talking to The Narrator)') !== false) return false;
        if (strpos($content, 'The Narrator:') === 0) {
            // Remove narrator dialogue (non-context narrator lines)
            return $isContextNarratorLine($content);
        }
        return true;
    }));
    $contextDataFull = array_merge($contextDataWorld, $contextDataHistoric);
}

// audit_log(__FILE__." [OGHMA]  ".__LINE__);

if (($gameRequest[0]=="chatnf_book")&&($GLOBALS["BOOK_EVENT_FULL"])) {
    // When chatnf_book (make the AI to read a book), context will only be the book data.
    $contextDataFull = array_merge($contextDataFull, DataGetLastReadedBook());
    //DataGetLastReadedBook();
}


// Player character section removed - player appearance now handled elsewhere


// Use centralized function from data_functions.php
$dynamicBiography = buildDynamicBiography($GLOBALS);

$playerBioSection = "";
try {
    require_once(__DIR__.DIRECTORY_SEPARATOR."lib".DIRECTORY_SEPARATOR."core".DIRECTORY_SEPARATOR."player.class.php");
    $playerObj = new Player();
    $playerBio = trim((string)($playerObj->get('bio') ?? ""));
    $bioKnownByAll = $playerObj->get('bio_known_by_all') === 'true';
    $isNarrator = ($GLOBALS["HERIKA_NAME"] === "The Narrator");

    if ($playerBio !== "" && ($bioKnownByAll || $isNarrator)) {
        $playerBioSection = "\n\n<player_character>\n# Player Character: {$GLOBALS["PLAYER_NAME"]}\n{$playerBio}\n</player_character>";
    }
} catch (Exception $e) {
    Logger::debug("Could not load player bio for prompt: " . $e->getMessage());
}


if (isset($GLOBALS["PROFILE_PROMPT"])) {
    $dynamicBiography.="\n<group>\n#Part of a group\n{$GLOBALS["PROFILE_PROMPT"]}\n</group>";
}




// Middle term memory experiment
// Skip middle-term memory for The Narrator (atmospheric narration shouldn't include individual NPC memories)
if ($GLOBALS["HERIKA_NAME"] !== "The Narrator" && isset($_GET["profile"])) {
    $npcMaster=new NpcMaster();
    $currentNpcData=$npcMaster->getByMD5($_GET["profile"]);
    // Only process if we got valid NPC data (not The Narrator)
    if ($currentNpcData && $currentNpcData["npc_name"] !== "The Narrator") {
        $extended_data=$npcMaster->getExtendedData($currentNpcData);
        if (isset($extended_data["middle_term_memory"])&&is_array($extended_data["middle_term_memory"])) {
            $middle_term_memory = end($extended_data["middle_term_memory"]);
            $dynamicBiography.="\n<middle_term_memory>\n#Past events\n{$middle_term_memory}\n</middle_term_memory>";
        }
    }
}

// Rumors and breaking news
$rumorsText="";
$currentHold=trim(DataLastKnownLocationHuman(true,false));
$currentLoc=trim(DataLastKnownLocationHuman(false,false));
if ($currentHold) {
    error_log("[RUMORS] Current hold {$currentHold}, currentLoc {$currentLoc}");
    $currentHoldEsc=$db->escape($currentHold);
    $currentLocEsc=$db->escape($currentLoc);
    $query="SELECT * FROM rumors WHERE hold like '{$currentLocEsc}%{$currentHoldEsc}%' and gamets>".round($gameRequest[2]- ( 7 * 24 /0.0000024));
    error_log($query);
    $rumors = $db->fetchAll($query);

    if (empty($umors)) {
        $query="SELECT * FROM rumors WHERE hold like '{$currentHoldEsc}%' and gamets>".round($gameRequest[2]- ( 7 * 24 /0.0000024));
        error_log($query);
        $rumors = $db->fetchAll($query);
    }


    foreach ($rumors as $n=>$rumor) {
       if (isset($rumor["content"])) {
            $tag=strtolower(str_replace(" ","_",$rumor["type"]));
            $rumorsText.="\n<$tag>\n{$rumor["content"]}\n</$tag>";
        }
        if ($n>=2) {
            break;
        }
    }
} else {
    error_log("[RUMORS] Current hold {$currentHold} empty");
}

// For narration events, simplify the command prompt (no actions needed for atmospheric descriptions)
if ($gameRequest[0] === "narration" || $gameRequest[0] === "narrator_welcome") {
    $GLOBALS["COMMAND_PROMPT"] = "Respond with atmospheric narration only. Use the Talk action.";
}

// Ensure actions and nearby sections are added to PROMPT_HEAD before building system prompt
require_once(__DIR__.DIRECTORY_SEPARATOR."functions".DIRECTORY_SEPARATOR."json_response.php");

// Build nearby sections string
$nearbySections = "";
if (isset($GLOBALS["PROMPT_NEARBY_SECTIONS"]) && !empty($GLOBALS["PROMPT_NEARBY_SECTIONS"])) {
    $nearbySections = $GLOBALS["PROMPT_NEARBY_SECTIONS"];
}

// Build actions list string
$actionsList = "";
if (isset($GLOBALS["PROMPT_ACTIONS_LIST"]) && !empty($GLOBALS["PROMPT_ACTIONS_LIST"])) {
    $actionsList = $GLOBALS["PROMPT_ACTIONS_LIST"];
}

// Inject paralinguistic tags prompt if enabled (works for any TTS provider)
$paralinguisticTagsPrompt = "";
if (isset($GLOBALS["TTSFUNCTION"]) && !empty($GLOBALS["TTSFUNCTION"])) {
    // Map TTSFUNCTION to TTS array key
    $ttsMap = [
        'melotts' => 'MELOTTS',
        'xtts-fastapi' => 'XTTSFASTAPI',
        'chatterbox' => 'CHATTERBOX',
        'pockettts' => 'POCKETTTS',
        'mimic3' => 'MIMIC3',
        'xvasynth' => 'XVASYNTH',
        'azure' => 'AZURE',
        '11labs' => 'ELEVEN_LABS',
        'openai' => 'openai',
        'kokoro' => 'KOKORO',
        'koboldcpp' => 'koboldcpp',
        'zonos_gradio' => 'ZONOS_GRADIO',
        'piper-tts' => 'PIPERTTS',
        'deepgram' => 'DEEPGRAM',
        'cartesia' => 'CARTESIA',
        'inworld' => 'INWORLD'
    ];
    
    $ttsKey = $ttsMap[$GLOBALS["TTSFUNCTION"]] ?? strtoupper($GLOBALS["TTSFUNCTION"]);
    
    if (isset($GLOBALS["TTS"][$ttsKey]["PARALINGUISTIC_TAGS_ENABLED"]) && 
        (bool)$GLOBALS["TTS"][$ttsKey]["PARALINGUISTIC_TAGS_ENABLED"]) {
        if (isset($GLOBALS["TTS"][$ttsKey]["PARALINGUISTIC_TAGS_PROMPT"]) && 
            !empty(trim($GLOBALS["TTS"][$ttsKey]["PARALINGUISTIC_TAGS_PROMPT"]))) {
            $paralinguisticTagsPrompt = "\n\n<paralinguistic_tags>\n" . 
                trim($GLOBALS["TTS"][$ttsKey]["PARALINGUISTIC_TAGS_PROMPT"]) . 
                "\n</paralinguistic_tags>";
        }
    }
}


// Check for context overrides on ext dir (plugins) before system prompt build
requireFilesRecursively(__DIR__.DIRECTORY_SEPARATOR."ext".DIRECTORY_SEPARATOR,"context_pre.php");


if (!empty($GLOBALS["OGHMA_HINT"])) {

    $head[] = array('role' => 'system', 'content' =>  
        strtr("<roleplay_instructions>\n".$GLOBALS["PROMPT_HEAD"] . "\n</roleplay_instructions>".$playerBioSection."\n\n<character>\n".$GLOBALS["HERIKA_PERS"] . $dynamicBiography . "\n</character>\n\n<knowledge>\n" . $GLOBALS["OGHMA_HINT"]."\n</knowledge>\n\n<general_instructions>\n". $GLOBALS["COMMAND_PROMPT"]."</general_instructions>".$actionsList.$nearbySections.$paralinguisticTagsPrompt."\n$rumorsText\n",
        ["#PLAYER_NAME#"=>$GLOBALS["PLAYER_NAME"],"#HERIKA_NAME#"=>$GLOBALS["HERIKA_NAME"]])

    );
    //avoid reinjecting command prompt that we have already appended
    $GLOBALS["COMMAND_PROMPT"] = "";
} else {
    $head[] = array('role' => 'system', 'content' =>  
        strtr("<roleplay_instructions>\n".$GLOBALS["PROMPT_HEAD"] . "\n</roleplay_instructions>".$playerBioSection."\n\n<character>\n".$GLOBALS["HERIKA_PERS"] . $dynamicBiography . "\n</character>\n\n<general_instructions>\n". $GLOBALS["COMMAND_PROMPT"]."\n</general_instructions>".$actionsList.$nearbySections.$paralinguisticTagsPrompt."\n$rumorsText\n",
        ["#PLAYER_NAME#"=>$GLOBALS["PLAYER_NAME"],"#HERIKA_NAME#"=>$GLOBALS["HERIKA_NAME"]])
    );
    //avoid reinjecting command prompt that we have already appended
    $GLOBALS["COMMAND_PROMPT"] = "";
}

// Check for context overrides on ext dir (plugins) after system prompt build
requireFilesRecursively(__DIR__.DIRECTORY_SEPARATOR."ext".DIRECTORY_SEPARATOR,"context.php");

// audit_log(__FILE__." [PLUGINS CONTEXT]  ".__LINE__);

/**********************
CALL BUILDING
***********************/
error_log("*TRACE: ".__LINE__. " at ".__FILE__.": ".(microtime(true) - $startTime)."");

if ($gameRequest[0] == "funcret") {

    $prompt[] = array('role' => 'assistant', 'content' => $request);

    // Manage function stuff
    // $contextData will be populated

    require(__DIR__.DIRECTORY_SEPARATOR."processor".DIRECTORY_SEPARATOR."funcret.php");


} else if ($gameRequest[0] == "cheatmode") {

    $prompt[] = array('role' => $LAST_ROLE, 'content' => $request);
    $contextData = array_merge($head, ($contextDataFull), $prompt);



} elseif ((strpos($gameRequest[0], "chatnf_book")!==false)) {

    $prompt[] = array('role' => $LAST_ROLE, 'content' => $request);
    $contextData = array_merge($head, $contextDataFull, $prompt);


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
    // Ensure CURRENT_CONNECTOR is set
    if (!isset($GLOBALS["CURRENT_CONNECTOR"]) || empty($GLOBALS["CURRENT_CONNECTOR"])) {
        if (isset($GLOBALS["CHIM_CORE_CURRENT_CONNECTOR_DATA"]["driver"])) {
            $GLOBALS["CURRENT_CONNECTOR"] = $GLOBALS["CHIM_CORE_CURRENT_CONNECTOR_DATA"]["driver"];
        } else {
            Logger::error("CURRENT_CONNECTOR not set and CHIM_CORE_CURRENT_CONNECTOR_DATA not available!");
            $GLOBALS["CURRENT_CONNECTOR"] = "unknown";
        }
    }
    
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
            $connectorName = isset($GLOBALS["CURRENT_CONNECTOR"]) ? $GLOBALS["CURRENT_CONNECTOR"] : "unknown";
            Logger::error("CRITICAL? :: Empty request, prompt empty. Type: {$gameRequest[0]} Connector: {$connectorName}");
            $prompt=[];
        }
    }

    $contextData = array_merge($head, ($contextDataFull), $prompt);
    
}

error_log("*TRACE SQL: TOTAL DATABASE query execution time: {$GLOBALS["DB_EXECUTION_TIME"]} seconds");

error_log("*TRACE: ".__LINE__. " at ".__FILE__.": ".(microtime(true) - $startTime)." secs building call");
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

// Set LLM processing status
pipeline_status_set('llm', true);

$outputWasValid = call_llm();

// Clear LLM processing status
pipeline_status_set('llm', false);

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
            
            // Update speech table with LLM-generated text for AUTOCHAT mode
            if (isset($GLOBALS["CHIM_EXECUTION_MODE"]) && $GLOBALS["CHIM_EXECUTION_MODE"] === "AUTOCHAT" 
                && in_array($gameRequest[0], ["inputtext", "inputtext_s", "ginputtext", "ginputtext_s"])
                && sizeof($talkedSoFar) > 0) {
                
                $transformedSpeech = trim($db->escape($player_rewrite_speech));
                $playerName = $db->escape($GLOBALS["PLAYER_NAME"]);
                $currentGamets = intval($gameRequest[2]);
                
                // Update the most recent player speech entry with the LLM-generated text
                $db->execQuery(
                    "UPDATE speech 
                     SET speech = '{$transformedSpeech}' 
                     WHERE speaker ILIKE '{$playerName}' 
                     AND gamets >= {$currentGamets} - 100 
                     AND gamets <= {$currentGamets} + 100
                     AND sess = 'pending'"
                );
                Logger::info("[AUTOCHAT] Updated speech table with LLM-generated player text");
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
SemaphoreManager::release("MAIN");
SemaphoreManager::release("ADDNPC");


while(!getenv("PHPUNIT_TEST") && ob_get_length() && ob_end_flush());
requireFilesRecursively(__DIR__.DIRECTORY_SEPARATOR."ext".DIRECTORY_SEPARATOR,"prepostrequest.php");
require(__DIR__.DIRECTORY_SEPARATOR."processor".DIRECTORY_SEPARATOR."postrequest.php");
requireFilesRecursively(__DIR__.DIRECTORY_SEPARATOR."ext".DIRECTORY_SEPARATOR,"postrequest.php");


?>
