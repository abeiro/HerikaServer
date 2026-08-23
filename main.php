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

require_once($path . "lib/runtime_bootstrap.php");
chimRuntimeBootstrap($path, [
    'load_general_settings' => true,
    'load_stt_connector' => true,
    'load_itt_connector' => true,
    'load_player_name' => true,
    'load_narrator' => true,
]);
require_once($path . "lib/game_activity.php");
require_once($path . "lib/background_processor.php");
if (!headers_sent() && function_exists('chimGetNarratorDisplayNameHeaderValue')) {
    header('X-Narrator-Display-Name: ' . chimGetNarratorDisplayNameHeaderValue());
}
require_once($path . "lib/auditing.php");
require_once($path . "lib/model_dynmodel.php");
require_once($path . "lib/minimet5_service.php");
require_once($path . "lib/data_functions.php");
require_once($path . "lib/chat_helper_functions.php");
require_once($path . "lib/compact_context_history.php");
require_once($path . "lib/lazy_xml.php");
require_once($path . "lib/memory_helper_vectordb.php");
require_once($path . "lib/llm_randomizer.php");
require_once($path . "lib/utils_game_timestamp.php");
require_once($path . "lib/logger.php"); 
require_once($path . "lib/chim_quest_engine.php");
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
$GLOBALS["gameRequest"] = &$gameRequest;
unset($GLOBALS["CHIM_TURN_PEOPLE_SNAPSHOT"]);
unset($GLOBALS["CHIM_CHAT_SHORTCUT_ROUTED"]);
$requestRoutingSnapshot = chimDecodePlayerRoutingSnapshotField($gameRequest[4] ?? "");
if (($requestRoutingSnapshot["chat_shortcut_routed"] ?? false) === true) {
    $GLOBALS["CHIM_CHAT_SHORTCUT_ROUTED"] = true;
}


$startTime = microtime(true);
//error_log("Audit run ID: " . $GLOBALS["AUDIT_RUNID"]. " ({$gameRequest[0]}) started: ".$startTime);
$GLOBALS["AUDIT_RUNID_REQUEST"]=$gameRequest[0];

$gameRequest[0] = strtolower($gameRequest[0]); // Who put 'diary' uppercase?
chimRequestPerformanceSetRequestType($gameRequest[0]);
chimRequestPerformanceMark('request_parsed');

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
$db = $GLOBALS["db"] ?? new sql();
$GLOBALS["db"] = $db;

if (PHP_SAPI !== 'cli' && !getenv('PHPUNIT_TEST') && $gameRequest[0] !== 'request') {
    $newGameActivitySession = chimMarkGameActivity();
    if ($newGameActivitySession && function_exists('herikaEnsureBackgroundProcessorRunning')) {
        herikaEnsureBackgroundProcessorRunning(false);
    }
}

require_once($path . "processor" .DIRECTORY_SEPARATOR."chim_modes.php");

// In directed CHIM modes, normalize incoming dialogue tags so logs/prompts stay aligned
// with the active speaking style.
$chimExecutionMode = strtoupper((string)($GLOBALS["CHIM_EXECUTION_MODE"] ?? ""));
if (isset($gameRequest[3]) && is_string($gameRequest[3]) &&
    in_array($gameRequest[0], ["inputtext", "inputtext_s", "ginputtext", "ginputtext_s", "narrator_inputtext", "chat", "prechat", "rechat", "continue", "continue_group"], true)) {
    if ($chimExecutionMode === "WHISPER") {
        $gameRequest[3] = convertTalkingTagsToWhispering($gameRequest[3]);
    } elseif ($chimExecutionMode === "CLOSE") {
        $gameRequest[3] = convertTalkingTagsToPrivately($gameRequest[3]);
    } elseif ($chimExecutionMode === "SHOUT") {
        $gameRequest[3] = convertTalkingTagsToShouting($gameRequest[3]);
    }
}

// Call extension's preprocessing files
requireFilesRecursively(__DIR__.DIRECTORY_SEPARATOR."ext".DIRECTORY_SEPARATOR,"preprocessing.php");

// Raw physics telemetry is opt-in. Extensions may rename it during preprocessing;
// if none did, acknowledge it here without entering the dialogue/LLM pipeline.
if ($gameRequest[0] === "physics_raw") {
    terminate();
}

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


$fast_commands = ["addnpc","addbgnpc","updateprofile","updateprofile_narrator","diary","diary_narrator","diary_player","_quest","setconf","request","_speech","infoloc","infonpc","infonpc_close",
    "infoaction","status_msg","delete_event","itemfound","_questdata","_uquest","location","_questreset","chat","bleedout","waitstart","waitstop",
    "util_location_name","util_faction_name","spellcast","npcspellcast","updateprofiles_batch_async","core_profile_assign","switchrace","combatbark",
    "util_location_npc","enable_bg","region","named_cell","snqe","named_cell_static","player_menu_tts_prefetch","player_menu_tts_play",
    "physics_raw"]; // raw VR contact/gaze telemetry from client plugins: log-only unless an extension opts in by renaming it in preprocessing

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
chimRequestPerformanceMark('lock_ready');

// adnpc has its custom semaphore, as it write files
if (in_array($gameRequest[0],["addnpc"])) {
    if (!SemaphoreWait("ADDNPC", $semaphore_timeout, 101, null)) {
        Logger::warn("[main] addnpc semaphore wait failed for {$gameRequest[0]}");
        terminate();
    }
}

if (($gameRequest[0]=="playerinfo")||(($gameRequest[0]=="newgame"))) {
    sleep(1);   // Give time to populate data

    // Load/newgame is a hard scene boundary. Rolemaster scene notes are transient
    // director state; do not let them bleed across save/load into normal chat.
    try {
        $db->delete("rolemaster", "type='scenenote'");
        $db->delete("responselog", "sent=0 and actor='rolemaster' and (action like 'rolecommand|Instruction@%' or action like 'rolecommand|Suggestion@%')");
        Logger::info("[main] Cleared transient rolemaster scene state on {$gameRequest[0]}");
    } catch (Exception $e) {
        Logger::warn("[main] Failed to clear transient rolemaster scene state on {$gameRequest[0]}: " . $e->getMessage());
    }
}

// Misc events, some of them can terminate the request
// delete_event, biography_import, oghma_import
require(__DIR__."/processor/misc.php");


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
        $player_rewrite_speech=sanitizePlayerRespeechText($player_rewrite_speech, $GLOBALS["PLAYER_NAME"] ?? null);
        $gameRequest[3]="{$GLOBALS["PLAYER_NAME"]}:$player_rewrite_speech";
        $GLOBALS["CHIM_EXECUTION_MODE"] = "AUTOCHAT"; // Required when a conversation mode uses the ** player rewrite prefix.
    }
}


// Narrator inititalization
// Note: We should check if we need to load Narrator profile in all type of requests. 
require(__DIR__."/processor/narrator_init.php");

// maybeQueueNpcVoiceRefresh function moved to misc.php. 
// If function is called only in one place,and seems has no other uses elsewhere, then there is no point of having a function, write the code in place.
// Also, we must not declare functions on this file (main.php).

// Profile loading
if (!isset($GLOBALS["NARRATOR_BORED_EVENT_ACTIVE"])) {
    $GLOBALS["NARRATOR_BORED_EVENT_ACTIVE"] = false;
}


// Bored
if (($gameRequest[0] ?? '') === 'bored') {
    require_once(__DIR__ . DIRECTORY_SEPARATOR . "lib" . DIRECTORY_SEPARATOR . "core" . DIRECTORY_SEPARATOR . "narrator.class.php");
    $narratorSettings = new Narrator();
    if ($narratorSettings->getBool('bored_enabled', false)) {
        $boredChance = max(1, min(100, $narratorSettings->getInt('bored_chance', 25)));
        $boredRoll = random_int(1, 100);
        if ($boredRoll <= $boredChance) {
            $_GET["profile"] = md5('The Narrator');
            $GLOBALS["NARRATOR_BORED_EVENT_ACTIVE"] = true;
            Logger::info("[NARRATOR_BORED] Routing bored event through The Narrator runtime (roll {$boredRoll}/{$boredChance})");
        } else {
            Logger::info("[NARRATOR_BORED] Keeping bored event on NPC runtime (roll {$boredRoll}/{$boredChance})");
        }
    }
}

if (isset($_GET["profile"])) {
    
    // Initialize OVERRIDES array for all profile types
    $OVERRIDES["BOOK_EVENT_ALWAYS_NARRATOR"] = isset($GLOBALS["BOOK_EVENT_ALWAYS_NARRATOR"]) ? $GLOBALS["BOOK_EVENT_ALWAYS_NARRATOR"] : false;
    $OVERRIDES["MINIME_T5"] = isset($GLOBALS["MINIME_T5"]) ? $GLOBALS["MINIME_T5"] : false;
    $OVERRIDES["STTFUNCTION"] = isset($GLOBALS["STTFUNCTION"]) ? $GLOBALS["STTFUNCTION"] : "";
    $OVERRIDES["TTSFUNCTION_PLAYER"] = isset($GLOBALS["TTSFUNCTION_PLAYER"]) ? $GLOBALS["TTSFUNCTION_PLAYER"] : "";
    $OVERRIDES["TTSFUNCTION_PLAYER_VOICE"] = isset($GLOBALS["TTSFUNCTION_PLAYER_VOICE"]) ? $GLOBALS["TTSFUNCTION_PLAYER_VOICE"] : "";
    $OVERRIDES["TTSFUNCTION_PLAYER_VOICE_ID"] = isset($GLOBALS["TTSFUNCTION_PLAYER_VOICE_ID"]) ? $GLOBALS["TTSFUNCTION_PLAYER_VOICE_ID"] : "";
    $OVERRIDES["TTSFUNCTION_PLAYER_LANGUAGE"] = isset($GLOBALS["TTSFUNCTION_PLAYER_LANGUAGE"]) ? $GLOBALS["TTSFUNCTION_PLAYER_LANGUAGE"] : "";
    
    // Direct narrator requests must load the narrator runtime profile even if the
    // inbound request still carries the current NPC profile hash.
    $isNarratorRequest = in_array($gameRequest[0], [
        "narrator_inputtext",
        "narration",
        "narrator_welcome",
        "narrator_quest_comment"
    ], true);

    // Check if this is The Narrator (by MD5) or an explicit narrator request.
    $isNarratorProfile = $isNarratorRequest || ($_GET["profile"] === md5('The Narrator'));
    
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
        if (!empty($GLOBALS['AUTOFILL_CUSTOM_PROFILES'])) {
            require_once __DIR__ . DIRECTORY_SEPARATOR . "ui" . DIRECTORY_SEPARATOR . "cmd" . DIRECTORY_SEPARATOR . "ai_profile_generation_service.php";
            if (aiProfileShouldAttemptAutofill($currentNpcData, $npcMaster)) {
                $trigger = aiProfileGetAutofillTrigger($currentNpcData, $npcMaster);
                $previewBundle = aiProfileBuildPreviewEvents($currentNpcData["npc_name"], $currentNpcData, $GLOBALS["db"], $trigger);
                if (count($previewBundle["events"]) >= $trigger) {
                    $autoProfileResult = aiProfileGenerate([
                        'db' => $GLOBALS["db"],
                        'name' => $currentNpcData["npc_name"],
                        'event_limit' => $trigger,
                        'selected_events' => $previewBundle["events"],
                        'source' => 'auto',
                    ]);
                    if (!empty($autoProfileResult['done'])) {
                        $currentNpcData = $npcMaster->getById($currentNpcData["id"]);
                    }
                }
            }
        }
        $connector=new LLMConnector();
        
        // Use randomizer to determine which connector slot to use
        $connectorSlot = LLMRandomizer::getConnectorSlot($currentProfileData, $currentNpcData, $npcMaster);
        $connectorId = LLMRandomizer::getConnectorIdForSlot($currentProfileData, $connectorSlot);
        
        $currentConnectorData = $connector->getById($connectorId); 
        
    
        $connector->setOldGlobals($currentConnectorData);
        $profile->setOldGlobals($currentProfileData);
        $npcMaster->setOldGlobalsFromCurrentNpcData($currentNpcData);
        $GLOBALS["CHIM_CORE_CURRENT_NPC_DATA"] = $currentNpcData;
        $GLOBALS["STOBE_CORE_CURRENT_NPC_DATA"] = $currentNpcData;

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

            // Recovery path: when a stale/unknown profile hash is passed, we still need
            // a valid profile + connector context or call_llm_internal() will terminate.
            $profile = new CoreProfile();

            $requestText = isset($gameRequest[3]) ? trim((string)$gameRequest[3]) : "";
            $fallbackNpcName = null;
            $fallbackNpcData = null;
            $currentProfileData = null;

            // Highest-confidence target extraction from player text payload.
            if ($requestText !== "" && preg_match('/\(\s*(?:(?:talking|whispering|shouting)\s+to|speaking\s+(?:loudly|privately)\s+to)\s+([^()]+?)(?:\s+from\s+far\s+away)?\s*\)/i', $requestText, $matches)) {
                $candidate = trim($matches[1]);
                if ($candidate !== "") {
                    $fallbackNpcName = $candidate;
                }
            }

            // Rolemaster payloads can include actor targeting as Instruction@NpcName@...
            if ($fallbackNpcName === null && $requestText !== "" && preg_match('/(?:Instruction|Suggestion)@([^@]+?)@/i', $requestText, $matches)) {
                $candidate = trim($matches[1]);
                if ($candidate !== "") {
                    $fallbackNpcName = $candidate;
                }
            }

            $isNarratorScopedRequest = in_array($gameRequest[0], ["narrator_inputtext", "narration", "narrator_welcome"], true)
                || stripos($requestText, '(Talking to The Narrator)') !== false
                || stripos($requestText, '(Whispering to The Narrator)') !== false
                || stripos($requestText, '(Shouting to The Narrator)') !== false
                || stripos($requestText, '(Speaking privately to The Narrator)') !== false
                || ($fallbackNpcName !== null && strcasecmp($fallbackNpcName, "The Narrator") === 0);

            if ($fallbackNpcName !== null && strcasecmp($fallbackNpcName, "The Narrator") !== 0) {
                $escapedNpcName = $db->escape($fallbackNpcName);
                $fallbackNpcData = $db->fetchOne("SELECT * FROM core_npc_master WHERE lower(npc_name)=lower('{$escapedNpcName}') LIMIT 1");
                if ($fallbackNpcData) {
                    $npcMaster->setOldGlobalsFromCurrentNpcData($fallbackNpcData);
                    $GLOBALS["CHIM_CORE_CURRENT_NPC_DATA"] = $fallbackNpcData;
                    $GLOBALS["STOBE_CORE_CURRENT_NPC_DATA"] = $fallbackNpcData;
                    error_log("[CORE SYSTEM] Resolved unknown profile hash to NPC '{$fallbackNpcData["npc_name"]}' from request payload");
                } else {
                    error_log("[CORE SYSTEM] Could not resolve NPC '{$fallbackNpcName}' for unknown profile hash");
                }
            }

            // Prefer the resolved NPC profile when available.
            if ($fallbackNpcData) {
                if (empty($fallbackNpcData["profile_id"])) {
                    $defProfile = $profile->getDefaultNpc();
                    if ($defProfile) {
                        $fallbackNpcData["profile_id"] = (int)$defProfile["id"];
                        $npcMaster->updateByArray($fallbackNpcData);
                        error_log("[CORE SYSTEM] Resolved NPC '{$fallbackNpcData["npc_name"]}' had no profile, assigned default profile #{$defProfile["id"]}");
                    }
                }
                if (!empty($fallbackNpcData["profile_id"])) {
                    $currentProfileData = $profile->getById((int)$fallbackNpcData["profile_id"]);
                }
            }

            if (!$currentProfileData) {
                // NPC/default profile should win for normal requests; narrator only for narrator-scoped requests.
                $fallbackProfile = $isNarratorScopedRequest ? $profile->getDefaultNarrator() : $profile->getDefaultNpc();
                if (!$fallbackProfile) {
                    $fallbackProfile = $isNarratorScopedRequest ? $profile->getDefaultNpc() : $profile->getDefaultNarrator();
                }
                if (!$fallbackProfile) {
                    $fallbackProfile = $profile->getById(1);
                }

                if ($fallbackProfile) {
                    // Ensure we have the full profile row (id/label/connectors/metadata).
                    $currentProfileData = isset($fallbackProfile["id"])
                        ? $profile->getById((int)$fallbackProfile["id"])
                        : $fallbackProfile;
                }
            }

            if ($currentProfileData) {
                $GLOBALS["CHIM_CORE_CURRENT_PROFILE_DATA"] = $currentProfileData;

                $connector = new LLMConnector();
                // Respect current in-game mode when selecting active connector slot.
                $result = $GLOBALS["db"]->fetchOne("SELECT value FROM conf_opts WHERE id='chim_profile_model'");
                $connectorSlot = (isset($result['value']) && $result['value'] >= 1 && $result['value'] <= 4)
                    ? (int)$result['value']
                    : 1;
                $connectorId = LLMRandomizer::getConnectorIdForSlot($currentProfileData, $connectorSlot);
                $currentConnectorData = $connector->getById($connectorId);

                if ($currentConnectorData) {
                    $connector->setOldGlobals($currentConnectorData);
                    $profile->setOldGlobals($currentProfileData);
                    $GLOBALS["CHIM_CORE_CURRENT_CONNECTOR_DATA"] = $currentConnectorData;
                    if ($fallbackNpcData) {
                        error_log("[CORE SYSTEM] Loaded fallback NPC profile '{$currentProfileData["label"]}' for '{$fallbackNpcData["npc_name"]}'");
                    } else {
                        error_log("[CORE SYSTEM] Loaded fallback profile '{$currentProfileData["label"]}' for unknown profile hash");
                    }
                } else {
                    Logger::error("[CORE SYSTEM] Fallback profile loaded but no connector found for slot {$connectorSlot}");
                }
            } else {
                Logger::error("[CORE SYSTEM] No fallback profile available for unknown profile hash");
            }

        } else {
            error_log("[CHIM CORE] USING CORE PROFILE {$currentNpcData["npc_name"]}")    ;
        

            // Profile has been migrated
            $npcMaster->setOldGlobalsFromCurrentNpcData($currentNpcData);
            $GLOBALS["CHIM_CORE_CURRENT_NPC_DATA"] = $currentNpcData;
            $GLOBALS["STOBE_CORE_CURRENT_NPC_DATA"] = $currentNpcData;

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

            if (!empty($GLOBALS['AUTOFILL_CUSTOM_PROFILES'])) {
                require_once __DIR__ . DIRECTORY_SEPARATOR . "ui" . DIRECTORY_SEPARATOR . "cmd" . DIRECTORY_SEPARATOR . "ai_profile_generation_service.php";
                if (aiProfileShouldAttemptAutofill($currentNpcData, $npcMaster)) {
                    $trigger = aiProfileGetAutofillTrigger($currentNpcData, $npcMaster);
                    $previewBundle = aiProfileBuildPreviewEvents($currentNpcData["npc_name"], $currentNpcData, $GLOBALS["db"], $trigger);
                    if (count($previewBundle["events"]) >= $trigger) {
                        $autoProfileResult = aiProfileGenerate([
                            'db' => $GLOBALS["db"],
                            'name' => $currentNpcData["npc_name"],
                            'event_limit' => $trigger,
                            'selected_events' => $previewBundle["events"],
                            'source' => 'auto',
                        ]);
                        if (!empty($autoProfileResult['done'])) {
                            $currentNpcData = $npcMaster->getById($currentNpcData["id"]);
                        }
                    }
                }
            }

            $connector=new LLMConnector();
            
            // Use randomizer to determine which connector slot to use
            $connectorSlot = LLMRandomizer::getConnectorSlot($currentProfileData, $currentNpcData, $npcMaster);
            $connectorId = LLMRandomizer::getConnectorIdForSlot($currentProfileData, $connectorSlot);
            
            $currentConnectorData = $connector->getById($connectorId); 
            
        
            $connector->setOldGlobals($currentConnectorData);
            $profile->setOldGlobals($currentProfileData);
            $npcMaster->setOldGlobalsFromCurrentNpcData($currentNpcData);
            $GLOBALS["CHIM_CORE_CURRENT_NPC_DATA"] = $currentNpcData;
            $GLOBALS["STOBE_CORE_CURRENT_NPC_DATA"] = $currentNpcData;

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
    $GLOBALS["TTSFUNCTION_PLAYER_VOICE_ID"]=$OVERRIDES["TTSFUNCTION_PLAYER_VOICE_ID"];
    $GLOBALS["TTSFUNCTION_PLAYER_LANGUAGE"]=$OVERRIDES["TTSFUNCTION_PLAYER_LANGUAGE"];
    
    // $GLOBALS["PROMPT_HEAD"]=$OVERRIDES["PROMPT_HEAD"];
    // error_log("Using profile {$GLOBALS["TTSFUNCTION_PLAYER"]} {$_GET["profile"]} / ".$path . "conf".DIRECTORY_SEPARATOR."conf_{$_GET["profile"]}.php");
    
} else {
    $isNarratorRequestWithoutProfile = in_array($gameRequest[0], [
        "narrator_inputtext",
        "narration",
        "narrator_welcome",
        "narrator_quest_comment"
    ], true);

    if ($isNarratorRequestWithoutProfile) {
        require_once(__DIR__ . DIRECTORY_SEPARATOR . "lib" . DIRECTORY_SEPARATOR . "core" . DIRECTORY_SEPARATOR . "narrator.class.php");
        $narrator = new Narrator();
        $narratorData = $narrator->getNarratorData();

        if ($narratorData && isset($narratorData["profile_id"])) {
            $profile = new CoreProfile();
            $currentProfileData = $profile->getById($narratorData["profile_id"]);

            if ($currentProfileData) {
                $GLOBALS["CHIM_CORE_CURRENT_PROFILE_DATA"] = $currentProfileData;

                $connector = new LLMConnector();
                $npcMaster = new NpcMaster(); // still needed for LLMRandomizer compatibility
                $connectorSlot = LLMRandomizer::getConnectorSlot($currentProfileData, $narratorData, $npcMaster);
                $connectorId = LLMRandomizer::getConnectorIdForSlot($currentProfileData, $connectorSlot);
                $currentConnectorData = $connector->getById($connectorId);

                if ($currentConnectorData) {
                    $connector->setOldGlobals($currentConnectorData);
                    $profile->setOldGlobals($currentProfileData);
                    $narrator->loadCharacterIntoGlobals();
                    $GLOBALS["CHIM_CORE_CURRENT_CONNECTOR_DATA"] = $currentConnectorData;

                    $currentMode = $GLOBALS["db"]->fetchOne("SELECT value FROM conf_opts WHERE id='chim_mode'");
                    pipeline_status_set_context(
                        $currentMode['value'] ?? 'STANDARD',
                        $currentConnectorData['label'] ?? '',
                        $currentConnectorData['model'] ?? ''
                    );

                    error_log("[CORE SYSTEM] Using Narrator profile without explicit profile hash, profile: {$currentProfileData["label"]}");
                } else {
                    Logger::error("[CORE SYSTEM] Narrator request without profile hash could not resolve connector");
                    $GLOBALS["USING_DEFAULT_PROFILE"] = true;
                }
            } else {
                Logger::error("[CORE SYSTEM] Narrator request without profile hash could not resolve profile");
                $GLOBALS["USING_DEFAULT_PROFILE"] = true;
            }
        } else {
            Logger::error("[CORE SYSTEM] Narrator request without profile hash has no narrator profile configured");
            $GLOBALS["USING_DEFAULT_PROFILE"] = true;
        }
    } else {
        //error_log(__FILE__.". Using default profile because NO GET PROFILE SPECIFIED");
        $GLOBALS["USING_DEFAULT_PROFILE"]=true;
    }
}

if (isset($GLOBALS["CHIM_CORE_CURRENT_NPC_DATA"]) && $GLOBALS["CHIM_CORE_CURRENT_NPC_DATA"] && ($GLOBALS["HERIKA_NAME"] ?? "") !== "The Narrator") {
    $npcMasterForVoiceRefresh = isset($npcMaster) && ($npcMaster instanceof NpcMaster)
        ? $npcMaster
        : new NpcMaster();
    
    $refreshedNpcData = maybeQueueNpcVoiceRefresh($GLOBALS["CHIM_CORE_CURRENT_NPC_DATA"], $npcMasterForVoiceRefresh);
    if ($refreshedNpcData) {
        $GLOBALS["CHIM_CORE_CURRENT_NPC_DATA"] = $refreshedNpcData;
        $GLOBALS["STOBE_CORE_CURRENT_NPC_DATA"] = $refreshedNpcData;
    }
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
    $resolvedDiaryConnector = function_exists('chimResolveDiaryConnectorName')
        ? chimResolveDiaryConnectorName()
        : ($GLOBALS["CONNECTORS_DIARY"] ?? '');
    if (!empty($resolvedDiaryConnector)) {
        $GLOBALS["CURRENT_CONNECTOR"] = $resolvedDiaryConnector;
    }
    
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

if (in_array($gameRequest[0], ["ext_held_item_raw", "ext_vr_item_raw"], true)) {
    require_once(__DIR__ . DIRECTORY_SEPARATOR . "lib" . DIRECTORY_SEPARATOR . "vr_items.php");
    $processedHeldItemRequest = HeldItems::processEventRequest($gameRequest);
    if ($processedHeldItemRequest !== null) {
        logEvent($processedHeldItemRequest);
    }
    terminate();
}

// Exit if only a event info log.
// Optional events

if (in_array($gameRequest[0],["info","infonpc","infonpc_close","infoloc","infoitems","chatme","chat","infoaction","death","itemfound",
    "travelcancel","infoplayer","status_msg","util_npcname","bleedout","spellcast","backgroundaction","reanimate","itempickup","npc_reanimated","region"])) {
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

    require_once(__DIR__ . DIRECTORY_SEPARATOR . "lib" . DIRECTORY_SEPARATOR . "rolemaster_bored.php");
    $boredChance = max(0, min(100, intval($GLOBALS["BORED_EVENT"] ?? 0)));
    $boredRoll = random_int(0, 99);
    if (!chimBoredEventChancePasses($boredChance, $boredRoll)) {
        Logger::debug("[BORED_CHANCE] Skipped bored event (roll {$boredRoll}, chance {$boredChance}%)");
        terminate();
    }
    Logger::debug("[BORED_CHANCE] Accepted bored event (roll {$boredRoll}, chance {$boredChance}%)");
    
    if (!empty($GLOBALS["NARRATOR_BORED_EVENT_ACTIVE"])) {
        Logger::info("[NARRATOR_BORED] Using narrator bored flow");
    } else {
        $boredSeedActor = trim((string)($gameRequest[4] ?? $GLOBALS["HERIKA_NAME"] ?? ""));
        Logger::info("Redirecting bored event to rolemaster with seed actor '{$boredSeedActor}'");
        $phpCli = PHP_BINDIR . DIRECTORY_SEPARATOR . "php";
        $managerPath = __DIR__ . DIRECTORY_SEPARATOR . "service" . DIRECTORY_SEPARATOR . "manager.php";
        $command = escapeshellarg($phpCli)
            . " " . escapeshellarg($managerPath)
            . " rolemaster instruction " . escapeshellarg("")
            . " bored " . escapeshellarg($boredSeedActor);
        exec($command, $output, $returnCode);
        if ($returnCode !== 0) {
            Logger::warn("Failed to start bored rolemaster request (exit code {$returnCode})");
        }
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

// Direct narrator dialogue is an explicit action-capable request path.
// Keep DB-backed narrator actions enabled so narrator_inputtext can expose
// Kill_Target / Spawn_Item / Teleport_NPC instead of falling back to
// speech-only JSON with an empty action list.
if ($gameRequest[0] === "narrator_inputtext") {
    $FUNCTIONS_ARE_ENABLED=true;
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

// Non-LLM request handling.
// We need to include this file asap. Most events are handled there.
// Log events are handled there to, which are the most called requests, and we want to exit as fast as possible for them.
// Most called events: 'request,'infonpc','infonpc_close'.

require(__DIR__.DIRECTORY_SEPARATOR."processor".DIRECTORY_SEPARATOR."comm.php");


if (in_array($gameRequest[0],["rechat","narration"]) ) {
    $configuredChimMode = $db->fetchOne("SELECT value FROM conf_opts WHERE id='chim_mode'");
    $configuredChimMode = strtoupper(trim((string)($configuredChimMode["value"] ?? "")));
    if (in_array($configuredChimMode, ["WHISPER", "CLOSE"], true)) {
        Logger::info("[RECHAT_SELECT] {$configuredChimMode} mode is active; terminating private rechat/narration request");
        terminate();
    }
    
    //RECHAT. Must choose if we continue conversation or no.
    // Note: narration is part of rechat system (random narrator interjections count as rechat rounds)

    if ($gameRequest[0] === "rechat") {
        $rechatPayload = chimParseServerSideRechatPayload($gameRequest[3] ?? "");
        $GLOBALS["RECHAT_PREVIOUS_SPEAKER"] = trim((string)($rechatPayload["speaker"] ?? ""));
        $resolvedRechatTarget = chimResolveServerSideRechatTarget($rechatPayload);
        $GLOBALS["RECHAT_REQUEST_PAYLOAD"] = $rechatPayload;
        $GLOBALS["RECHAT_RESOLVED_TARGET"] = $resolvedRechatTarget;

        if (empty($resolvedRechatTarget["selected"])) {
            Logger::info("[RECHAT_SELECT] No valid responder selected; terminating rechat");
            terminate();
        }

        if (!chimSwitchActiveNpcProfile($resolvedRechatTarget["selected"])) {
            Logger::warn("[RECHAT_SELECT] Failed to switch active NPC profile to " . $resolvedRechatTarget["selected"]);
            terminate();
        }
    }

    $rechatHistory=DataRechatHistory();
    $currentSpeakerName = trim((string)($GLOBALS["HERIKA_NAME"] ?? ""));
    
    // Pre-calculated rechat budget with final-round closing prompt

    $sessionKey = isset($GLOBALS["RECHAT_RESOLVED_TARGET"])
        ? chimBuildServerSideRechatSessionKey($GLOBALS["RECHAT_RESOLVED_TARGET"])
        : md5($GLOBALS["HERIKA_NAME"] . "_" . floor(time() / 120));
    $budgetFile = sys_get_temp_dir() . "/chim_rechat_" . $sessionKey . ".json";
    $budgetStateWindow = 120;
    $budgetState = null;

    if (file_exists($budgetFile)) {
        $loadedBudgetState = json_decode(file_get_contents($budgetFile), true);
        if (is_array($loadedBudgetState) &&
            isset($loadedBudgetState["budget"]) &&
            isset($loadedBudgetState["used"]) &&
            isset($loadedBudgetState["ts"]) &&
            (time() - intval($loadedBudgetState["ts"]) <= $budgetStateWindow)) {
            $budgetState = $loadedBudgetState;
        } else {
            @unlink($budgetFile);
        }
    }

    if (!is_array($budgetState)) {
        $budget = 0;
        for ($i = 0; $i < intval($GLOBALS["RECHAT_H"]); $i++) {
            if (rand(1, 100) <= intval($GLOBALS["RECHAT_P"])) {
                $budget++;
            } else {
                break; // probability failed — chain ends here
            }
        }
        if ($budget === 0) {
            Logger::info("Rechat: pre-roll determined 0 rounds — terminating");
            terminate();
        }
        $budgetState = ['budget' => $budget, 'used' => 0, 'ts' => time()];
    }

    $budget = intval($budgetState['budget'] ?? 0);
    $currentRound = intval($budgetState['used'] ?? 0);
    if ($currentRound >= $budget) {
        Logger::info("Rechat: pre-roll budget exhausted ({$currentRound}/{$budget}) — terminating");
        Logger::info("[RECHAT_COUNT] exhausted speaker={$GLOBALS["HERIKA_NAME"]} chain_id=" .
            (isset($GLOBALS["RECHAT_RESOLVED_TARGET"]["chain_id"]) ? $GLOBALS["RECHAT_RESOLVED_TARGET"]["chain_id"] : "") .
            " used={$currentRound} budget={$budget}");
        @unlink($budgetFile);
        terminate();
    }

    $budgetState['used'] = $currentRound + 1;
    $budgetState['ts'] = time();
    file_put_contents($budgetFile, json_encode($budgetState));
    Logger::info("[RECHAT_COUNT] speaker={$GLOBALS["HERIKA_NAME"]} chain_id=" .
        (isset($GLOBALS["RECHAT_RESOLVED_TARGET"]["chain_id"]) ? $GLOBALS["RECHAT_RESOLVED_TARGET"]["chain_id"] : "") .
        " used={$budgetState['used']} budget={$budget}");

    // All gates passed — detect final round and inject closing prompt
    if ($currentRound + 1 >= $budget) {
        $GLOBALS["PROMPT_HEAD"] .= "\n[This is your final response in this exchange. Conclude your current thought naturally — you are not leaving, just finishing what you were saying for now.]";
        Logger::info("Rechat: final round ({$currentRound}/{$budget}) — closing prompt injected");
    }


    if ($currentRound > 1) {
        // Lets make rechat wait a bit, so events while NPCs are speaking get into context// disabled if using new rechat fire event
        SemaphoreManager::release("MAIN");
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
    if (!empty($GLOBALS["RANDOM_NARATION"]) && $GLOBALS["RANDOM_NARATION"] && $gameRequest[0] === "rechat" && sizeof($rechatHistory) >= 1) {
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

    $visibleChatStateSql = chimBuildChatDeliveryStateSql('delivery_state');
    $sqlfilter=" and (type in ('prechat','inputtext','ginputtext','infonpc','infonpc_close','logaction','infoaction','death','itemfound','innerchat') or (type='chat' and {$visibleChatStateSql} and data like '(Context%') )";  // Use prechat
    // chat entries starting by "(Context%" are standard skyrim dialogue

    $FUNCTIONS_ARE_ENABLED=false;       // Enabling this can be funny => CHAOS MODE

} else
    $sqlfilter=" and type<>'prechat' "; // Will dismiss prechat entries by default. prechat are LLM responses still not displayed in-game




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
        
        $npcMaster = new NpcMaster();
        $connectorSlot = LLMRandomizer::getConnectorSlot($currentProfileData, $narratorData, $npcMaster);
        
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
        
        $npcMaster = new NpcMaster();
        $connectorSlot = LLMRandomizer::getConnectorSlot($currentProfileData, $narratorData, $npcMaster);
        
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
        if (ob_get_length() !== false) {
            @ob_end_flush();
            @flush();
        }
    }    
    if (microtime(true) - $startTime > 0.5) {
        error_log("*TRACE EARLY END SQL: TOTAL DATABASE query execution time: {$GLOBALS["DB_EXECUTION_TIME"]} seconds");
        error_log("*TRACE EARLY END: ".__LINE__. " at ".__FILE__.": ".(microtime(true) - $startTime)." resolving request");
    }
    terminate();

}
if ($EXECUTION_MODE=="INJECTION_LOG") {
    
    terminate();

}

// What is this for?
if (in_array($gameRequest[0], ["continue", "continue_group"], true) && empty($GLOBALS["RECHAT_PREVIOUS_SPEAKER"])) {
    try {
        $lastSpeechRow = $db->fetchOne("SELECT speaker FROM speech ORDER BY rowid DESC LIMIT 1");
        $GLOBALS["RECHAT_PREVIOUS_SPEAKER"] = trim((string)($lastSpeechRow["speaker"] ?? ""));
    } catch (\Throwable $e) {
        $GLOBALS["RECHAT_PREVIOUS_SPEAKER"] = "";
    }
}


error_log("TRACE:\t".__LINE__. "\t".__FILE__.":\t".(microtime(true) - $startTime));

/**********************
 CONTEXT DATA BUILDING
***********************/

$GLOBALS["DIRECT_NARRATOR_DIALOGUE"] = (
    $gameRequest[0] === "narrator_inputtext"
    || (
        ($GLOBALS["HERIKA_NAME"] ?? "") === "The Narrator"
        && in_array($gameRequest[0], ["cheatmode", "instruction"], true)
    )
);

// Narrator-scoped requests must execute with the narrator runtime profile even
// when the inbound request still carries a valid NPC profile hash. If that hash
// wins earlier profile loading, narrator-only actions get filtered out of the
// runtime function list before response processing.
$isNarratorScopedRequest = in_array($gameRequest[0], [
    "narrator_inputtext",
    "narration",
    "narrator_welcome",
    "narrator_quest_comment",
], true) || (
    ($GLOBALS["HERIKA_NAME"] ?? "") === "The Narrator"
    && in_array($gameRequest[0], ["cheatmode", "instruction"], true)
);

if ($isNarratorScopedRequest && (($GLOBALS["HERIKA_NAME"] ?? "") !== "The Narrator")) {
    require_once(__DIR__ . DIRECTORY_SEPARATOR . "lib" . DIRECTORY_SEPARATOR . "core" . DIRECTORY_SEPARATOR . "narrator.class.php");

    $narrator = new Narrator();
    $narratorData = $narrator->getNarratorData();

    if ($narratorData && isset($narratorData["profile_id"])) {
        $profile = new CoreProfile();
        $currentProfileData = $profile->getById($narratorData["profile_id"]);

        if ($currentProfileData) {
            $GLOBALS["CHIM_CORE_CURRENT_PROFILE_DATA"] = $currentProfileData;

            $connector = new LLMConnector();
            $npcMaster = new NpcMaster();
            $connectorSlot = LLMRandomizer::getConnectorSlot($currentProfileData, $narratorData, $npcMaster);
            $connectorId = LLMRandomizer::getConnectorIdForSlot($currentProfileData, $connectorSlot);
            $currentConnectorData = $connector->getById($connectorId);

            if ($currentConnectorData) {
                $connector->setOldGlobals($currentConnectorData);
                $profile->setOldGlobals($currentProfileData);
                $narrator->loadCharacterIntoGlobals();

                $GLOBALS["CHIM_CORE_CURRENT_CONNECTOR_DATA"] = $currentConnectorData;
                $GLOBALS["CURRENT_CONNECTOR"] = $currentConnectorData['driver'] ?? ($GLOBALS["CURRENT_CONNECTOR"] ?? "");
                unset($GLOBALS["CHIM_CORE_CURRENT_NPC_DATA"], $GLOBALS["STOBE_CORE_CURRENT_NPC_DATA"]);
                $GLOBALS["IS_NPC"] = false;
                $GLOBALS["FUNCTIONS_ARE_ENABLED"] = true;

                error_log("[CORE SYSTEM] Re-synced narrator runtime profile before prompt build");
            } else {
                error_log("[CORE SYSTEM] Failed to re-sync narrator runtime profile: connector not found");
            }
        } else {
            error_log("[CORE SYSTEM] Failed to re-sync narrator runtime profile: profile not found");
        }
    } else {
        error_log("[CORE SYSTEM] Failed to re-sync narrator runtime profile: narrator data missing");
    }
}

error_log("*TRACE: ".__LINE__. " at ".__FILE__.": ".(microtime(true) - $startTime)."");

// Include prompts, command prompts and functions.
require(__DIR__.DIRECTORY_SEPARATOR."prompt.includes.php");
$gameRequest[0] = strtolower($gameRequest[0]); // one more time in case it was changed by an extension

error_log("*TRACE: ".__LINE__. " at ".__FILE__.": ".(microtime(true) - $startTime)."");

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
            "description" => (function_exists('chimGetPromptCharacterName') ? chimGetPromptCharacterName() : $GLOBALS["HERIKA_NAME"]) . " offers {$tier} {$skill} training.",
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

if (!empty($GLOBALS["NARRATOR_BORED_EVENT_ACTIVE"]) && $gameRequest[0] == "bored") {
    $boredPrompt = null;
    try {
        $promptData = $db->fetchOne("SELECT custom_prompt, default_prompt FROM prompts WHERE prompt_key = 'narrator_bored_prompt'");
        if ($promptData) {
            $boredPrompt = !empty($promptData['custom_prompt']) ? $promptData['custom_prompt'] : ($promptData['default_prompt'] ?? null);
        }
    } catch (Exception $e) {
        Logger::warn("[NARRATOR_BORED] Failed to load narrator_bored_prompt from database: " . $e->getMessage());
    }

    if (!$boredPrompt) {
        $boredPrompt = '({HERIKA_NAME} makes one short comment directly to {PLAYER_NAME} about something happening right now in the current scene. Keep it grounded in the present moment, do not ask follow-up questions, and do not continue the conversation.) {TEMPLATE_DIALOG}';
    }

    $PROMPTS["bored"] = ["cue" => [strtr($boredPrompt, [
                        '{HERIKA_NAME}' => function_exists('chimGetPromptCharacterName') ? chimGetPromptCharacterName() : ($GLOBALS["HERIKA_NAME"] ?? 'The Narrator'),
                        '{NARRATOR_NAME}' => function_exists('chimGetNarratorRoleplayName') ? chimGetNarratorRoleplayName() : 'The Narrator',
        '{PLAYER_NAME}' => $GLOBALS["PLAYER_NAME"] ?? 'Player',
        '{TEMPLATE_DIALOG}' => $GLOBALS["TEMPLATE_DIALOG"] ?? '',
    ])]];
    Logger::info("[NARRATOR_BORED] Injected narrator bored prompt");
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

$requestAudienceSnapshot = (string)($requestRoutingSnapshot["audience"] ?? "");
$requestPresentActorsSnapshot = chimSetCurrentTurnPresentActorsSnapshot(
    $requestRoutingSnapshot["present_actors"] ?? []
);
$requestPresentPeople = chimPresentActorsPeoplePipe($requestPresentActorsSnapshot);

if (in_array($gameRequest[0],["inputtext_s"]) && $requestAudienceSnapshot === "") {    // Stealth-targeted follower: scope to target NPC only
    $GLOBALS["CACHE_PEOPLE"]=$GLOBALS["HERIKA_NAME"];
}

if (($gameRequest[0] ?? "") === "infoloc") {
    $scenePeople = DataBeingsInRange();
    if (!empty($scenePeople)) {
        $GLOBALS["CACHE_PEOPLE"] = $scenePeople;
    }
}

error_log("TRACE:\t".__LINE__. "\t".__FILE__.":\t".(microtime(true) - $startTime));
// Scope all incoming dialogue-producing requests before returnLines() logs
// generated prechat/chat rows.
$playerInputEventTypes = ["inputtext", "inputtext_s", "ginputtext", "ginputtext_s", "narrator_inputtext"];
$directiveDialogueEventTypes = ["instruction", "suggestion"];
$turnPeopleSnapshotEventTypes = array_merge($playerInputEventTypes, $directiveDialogueEventTypes);
$hasAuthoritativeRequestAudience = (
    in_array($gameRequest[0] ?? "", $turnPeopleSnapshotEventTypes, true) &&
    $requestAudienceSnapshot !== ""
);
$resolvedRechatPeople = "";
if (($gameRequest[0] ?? "") === "rechat" && isset($GLOBALS["RECHAT_RESOLVED_TARGET"])) {
    $resolvedRechatPeople = (string)($GLOBALS["RECHAT_RESOLVED_TARGET"]["people_pipe"] ?? "");
}
$authoritativePeople = $hasAuthoritativeRequestAudience ? $requestAudienceSnapshot : $resolvedRechatPeople;
$directiveFallbackPeople = "";
if ($authoritativePeople === "" && in_array($gameRequest[0] ?? "", $directiveDialogueEventTypes, true)) {
    // Busy actors remain physically present even though Rolemaster must not
    // select them as new speakers.
    $directiveSpeakerName = trim((string)(
        $GLOBALS["CHIM_CORE_CURRENT_NPC_DATA"]["npc_name"]
        ?? $GLOBALS["HERIKA_NAME"]
        ?? ""
    ));
    $directiveFallbackPeople = chimBuildDirectivePeoplePipe(
        DataBeingsInCloseRange(true, true),
        $directiveSpeakerName,
        $gameRequest[3] ?? ""
    );
}

if (isWhisperExecutionMode() && in_array($gameRequest[0] ?? "", $playerInputEventTypes, true)) {
    $whisperPrivatePeople = buildWhisperPrivatePeople($GLOBALS["HERIKA_NAME"] ?? "");
    if ($whisperPrivatePeople !== "") {
        $authoritativePeople = $whisperPrivatePeople;
        $directiveFallbackPeople = "";
        Logger::info("Scoped CACHE_PEOPLE for WHISPER {$gameRequest[0]}: " . $whisperPrivatePeople);
    }
} elseif (isCloseExecutionMode() &&
          in_array($gameRequest[0] ?? "", $playerInputEventTypes, true) &&
          $authoritativePeople === "") {
    $closePrivatePeople = buildPrivateConversationPeople($GLOBALS["HERIKA_NAME"] ?? "");
    if ($closePrivatePeople !== "") {
        $authoritativePeople = $closePrivatePeople;
        $directiveFallbackPeople = "";
        Logger::info("Scoped CACHE_PEOPLE for CLOSE {$gameRequest[0]} from private fallback: " . $closePrivatePeople);
    }
}
if (isPrivateConversationExecutionMode() &&
    in_array($gameRequest[0] ?? "", $playerInputEventTypes, true)) {
    $requestPresentActorsSnapshot = chimSetCurrentTurnPresentActorsSnapshot([]);
    $requestPresentPeople = "";
}

if ($authoritativePeople !== "") {
    $GLOBALS["CACHE_PEOPLE"] = $authoritativePeople;
    Logger::info("Scoped CACHE_PEOPLE for {$gameRequest[0]}: " . $GLOBALS["CACHE_PEOPLE"]);
} elseif ($directiveFallbackPeople !== "") {
    $GLOBALS["CACHE_PEOPLE"] = $directiveFallbackPeople;
    Logger::info("Scoped CACHE_PEOPLE for {$gameRequest[0]} from close range: " . $GLOBALS["CACHE_PEOPLE"]);
} else {
    $scopedPeople = buildScopedPeopleForEvent(
        $gameRequest[0] ?? "",
        $gameRequest[3] ?? "",
        $GLOBALS["HERIKA_NAME"] ?? "",
        $GLOBALS["CACHE_PEOPLE"] ?? ""
    );
    if (!empty($scopedPeople)) {
        $GLOBALS["CACHE_PEOPLE"] = $scopedPeople;
        Logger::info("Scoped CACHE_PEOPLE for {$gameRequest[0]}: " . $GLOBALS["CACHE_PEOPLE"]);
    }

    if (!empty($GLOBALS["HERIKA_NAME"])) {
        $shouldAppendListener = shouldAutoAppendListenerToPeople(
            $gameRequest[0] ?? "",
            $gameRequest[3] ?? "",
            $GLOBALS["HERIKA_NAME"]
        );
        $currentPeople = isset($GLOBALS["CACHE_PEOPLE"]) ? (string)$GLOBALS["CACHE_PEOPLE"] : "";
        $peopleTokens = array_values(array_filter(array_map('trim', explode('|', $currentPeople))));
        if ($shouldAppendListener && !in_array($GLOBALS["HERIKA_NAME"], $peopleTokens, true)) {
            $peopleTokens[] = $GLOBALS["HERIKA_NAME"];
            $GLOBALS["CACHE_PEOPLE"] = "|" . implode("|", $peopleTokens) . "|";
            Logger::info("Added listener to CACHE_PEOPLE: " . $GLOBALS["HERIKA_NAME"]);
        } elseif (!$shouldAppendListener) {
            Logger::info("Skipped listener auto-append for scoped input event: " . $GLOBALS["HERIKA_NAME"]);
        }
    }
}

error_log("TRACE:\t".__LINE__. "\t".__FILE__.":\t".(microtime(true) - $startTime));

/// LOG INTO DB. Will use this later.
if ($gameRequest[0] != "diary" && $gameRequest[0] != "cheatmode") {
    // Filter out combat grunts
    $shouldLog = true;
    $data = isset($gameRequest[3]) ? $gameRequest[3] : '';
    
    // List of combat grunts to filter
    // Not agree. Make it optional. A guy using a chair, even 6 times, a combat grunt, a cough ... all of that is context relevant.

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
        if ($authoritativePeople !== "") {
            $eventPeople = $authoritativePeople;
            $GLOBALS["CACHE_PEOPLE"] = $authoritativePeople;
        } elseif ($directiveFallbackPeople !== "") {
            $eventPeople = $directiveFallbackPeople;
            $GLOBALS["CACHE_PEOPLE"] = $directiveFallbackPeople;
        } else {
            $eventPeople = buildScopedPeopleForEvent(
                $gameRequest[0] ?? "",
                $gameRequest[3] ?? "",
                $GLOBALS["HERIKA_NAME"] ?? "",
                $GLOBALS["CACHE_PEOPLE"] ?? ""
            );
            if (!empty($eventPeople)) {
                $GLOBALS["CACHE_PEOPLE"] = $eventPeople;
            }
        }
        if ($requestPresentPeople !== "" &&
            in_array($gameRequest[0] ?? "", $playerInputEventTypes, true)) {
            $eventPeople = chimMergePeoplePipeLists($eventPeople, $requestPresentPeople);
            Logger::info("Added physical presence to event people for {$gameRequest[0]}: " . $requestPresentPeople);
        }

        if (in_array($gameRequest[0], $turnPeopleSnapshotEventTypes, true)) {
            chimSetCurrentTurnPeopleSnapshot($eventPeople);
        }

        // Fixes. This should net be here.
        //if ($dataArray[0]=="funcret") {
        //    $eventPeople=DataBeingsInCloseRange(true);
        //}

        $eventlogInsert = array(
            'ts' => $gameRequest[1],
            'gamets' => $gameRequest[2],
            'type' => $gameRequest[0],
            'data' => ($gameRequest[3]),
            'sess' => (php_sapi_name()=="cli" && !getenv('PHPUNIT_TEST'))?'cli':'web',
            'localts' => time(),
            'people'=> $eventPeople,
            'location'=>$GLOBALS["CACHE_LOCATION"],
            'party'=>$GLOBALS["CACHE_PARTY"],
        );

        if ($gameRequest[0] === "chat") {
            $eventlogInsert["delivery_state"] = "spoken";
        }

        $db->insert('eventlog', $eventlogInsert);
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
        $chance = 50;
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

// Hard cooldown for RPG comment events (global, fixed to 60 seconds)
$rpgCommentEventMap = [
    'combatend'     => 'combat_end',
    'combatendmighty' => 'combat_end',
    'bleedout'      => 'bleedout',
    'rpg_lvlup'     => 'levelup',
    'rpg_shout'     => 'learn_shout',
    'rpg_word'      => 'learn_word',
    'rpg_soul'      => 'absorb_soul',
    'lockpicked'    => 'lockpick',
    'goodmorning'   => 'sleep',
];
$rpgCommentEventType = $rpgCommentEventMap[$gameRequest[0]] ?? null;
if ($gameRequest[0] === 'instruction' && isset($gameRequest[3])) {
    if (stripos($gameRequest[3], 'wounded bleedingout') !== false || stripos($gameRequest[3], 'lost combat') !== false) {
        $rpgCommentEventType = 'bleedout';
    }
}

if (!empty($rpgCommentEventType)) {
    $rpgCooldownSeconds = 60;
    $rpgCooldownKey = 'RPG_COMMENT_LAST_TIMESTAMP';
    $rpgRecord = $GLOBALS["db"]->fetchAll("SELECT value FROM conf_opts WHERE id='" . $GLOBALS["db"]->escape($rpgCooldownKey) . "'");
    if (!empty($rpgRecord)) {
        $lastTrigger = (int)$rpgRecord[0]['value'];
        $elapsed = time() - $lastTrigger;
        if ($elapsed < $rpgCooldownSeconds) {
            Logger::info("RPG comment {$rpgCommentEventType} skipped (hard cooldown active: {$elapsed}/{$rpgCooldownSeconds}s)");
            terminate();
        }
    }
    $GLOBALS["db"]->upsertRowOnConflict(
        "conf_opts",
        [
            "id" => $rpgCooldownKey,
            "value" => time(),
        ],
        'id'
    );
}


// Narrator stop (from config)

if (isset($GLOBALS["NARRATOR_TALKS"])&&($GLOBALS["NARRATOR_TALKS"]==false)) {
    if ($GLOBALS["HERIKA_NAME"]=="The Narrator")
        terminate();
}

// Use diary-specific context history if this is a diary request and CONTEXT_HISTORY_DIARY is set
chimRequestPerformanceMark('profile_ready');
if (($gameRequest[0] == "diary" || $gameRequest[0] == "diary_followers") && isset($GLOBALS["CONTEXT_HISTORY_DIARY"]) && $GLOBALS["CONTEXT_HISTORY_DIARY"] > 0) {
    $lastNDataForContext = $GLOBALS["CONTEXT_HISTORY_DIARY"];
} else {
    $lastNDataForContext = (isset($GLOBALS["CONTEXT_HISTORY"])) ? ($GLOBALS["CONTEXT_HISTORY"]) : "25";
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
chimRequestPerformanceMark('context_history_ready');

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

error_log("TRACE:\t".__LINE__. "\t".__FILE__.":\t".(microtime(true) - $startTime));

if (in_array($gameRequest[0],["inputtext","inputtext_s","ginputtext","ginputtext_s","narrator_inputtext","rechat","narration","continue","continue_group"]) ) {

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

chimRequestPerformanceMark('memory_ready');
error_log("TRACE:\t".__LINE__. "\t".__FILE__.":\t".(microtime(true) - $startTime));

// Whisper-mode speaking behavior: make the NPC explicitly treat this exchange as whispered.
if (isset($GLOBALS["CHIM_EXECUTION_MODE"]) && strtoupper((string)$GLOBALS["CHIM_EXECUTION_MODE"]) === "WHISPER") {
    if (!isset($GLOBALS["COMMAND_PROMPT"]) || !is_string($GLOBALS["COMMAND_PROMPT"])) {
        $GLOBALS["COMMAND_PROMPT"] = "";
    }
    $GLOBALS["COMMAND_PROMPT"] .= "\n\n[Whisper mode is active. {$GLOBALS["PLAYER_NAME"]} is whispering to you. Reply by whispering back in a quiet, discreet, close-range tone and keep the delivery private.]";
} elseif (isset($GLOBALS["CHIM_EXECUTION_MODE"]) && strtoupper((string)$GLOBALS["CHIM_EXECUTION_MODE"]) === "CLOSE") {
    if (!isset($GLOBALS["COMMAND_PROMPT"]) || !is_string($GLOBALS["COMMAND_PROMPT"])) {
        $GLOBALS["COMMAND_PROMPT"] = "";
    }
    $GLOBALS["COMMAND_PROMPT"] .= "\n\n[Close mode is active. {$GLOBALS["PLAYER_NAME"]} is speaking privately to you at close range. Respond only to {$GLOBALS["PLAYER_NAME"]}; do not assume any bystanders can hear or participate.]";
}


// array('role' => $currentSpeaker, 'content' => implode("\n", $buffer));

// Action-enforcement prompt is hard-disabled globally.
$GLOBALS["ENFORCE_ACTIONS_PROMPT"] = false;
$GLOBALS["PATCH_PROMPT_ENFORCE_ACTIONS"] = false;
$GLOBALS["COMMAND_PROMPT_ENFORCE_ACTIONS"] = "";


// Rechat case
if (in_array($gameRequest[0],["rechat","narration"]) ) {
    // CHAOS mode
    
    if (isset($GLOBALS["RECHAT_ALLOW_ACTIONS"]) && $GLOBALS["RECHAT_ALLOW_ACTIONS"]) {
        $FUNCTIONS_ARE_ENABLED=true;

        $GLOBALS["PATCH_PROMPT_ENFORCE_ACTIONS"]=false;
        $GLOBALS["COMMAND_PROMPT_ENFORCE_ACTIONS"]="";
        
        // Legacy plugin prompts can break rechat actor addressing: "Respond to #target# as #herika_name#".
        $GLOBALS['action_prompts']=[];
        $rechatEnabledFunctionSet=array_fill_keys($GLOBALS["ENABLED_FUNCTIONS"] ?? [], true);
        $rechatActionSourceCodes=[
            "TradeItems"=>"OpenInventory",
        ];
        $rechatActionWasEnabled=function ($functionCode) use ($rechatEnabledFunctionSet, $rechatActionSourceCodes) {
            $sourceCode=$rechatActionSourceCodes[$functionCode] ?? $functionCode;
            return isset($rechatEnabledFunctionSet[$sourceCode]);
        };

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
        /*
        if ($rechatActionWasEnabled("TradeItems") && isset($GLOBALS["BASE_FUNCTIONS"]["OpenInventory"])) {
            $NEWFUNCTION=$GLOBALS["BASE_FUNCTIONS"]["OpenInventory"];
            $NEWFUNCTION["name"]="TradeItems";
        $NEWFUNCTION["description"]=(function_exists('chimGetPromptCharacterName') ? chimGetPromptCharacterName() : $GLOBALS["HERIKA_NAME"]) . " trade items with another actor. Amount and item will be infered from dialogue, so no need to specify";
            $NEWFUNCTION["parameters"]["properties"]["target"]["description"]="Actor name to trade with";
            $GLOBALS["FUNCTIONS"][]=$NEWFUNCTION;
            $GLOBALS["ENABLED_FUNCTIONS"][]="TradeItems";
            $GLOBALS["F_NAMES"]["TradeItems"]="TradeItems";
        }
        */

        if ($GLOBALS["IS_NPC"] && $rechatActionWasEnabled("TravelTo") && isset($GLOBALS["BASE_FUNCTIONS"]["TravelTo"])) {
            // TravelTo (lead the way to for player) will be modified to TravelTo (TravelTo) if no follower
            $NEWFUNCTION=$GLOBALS["BASE_FUNCTIONS"]["TravelTo"];
            $NEWFUNCTION["name"]="TravelTo";
            $NEWFUNCTION["description"]=(function_exists('chimGetPromptCharacterName') ? chimGetPromptCharacterName() : $GLOBALS["HERIKA_NAME"]) . " travels to location";
            $NEWFUNCTION["parameters"]["properties"]["location"]["description"]="location name";
            $GLOBALS["FUNCTIONS"][]=$NEWFUNCTION;
            $GLOBALS["ENABLED_FUNCTIONS"][]="TravelTo";
            $GLOBALS["F_NAMES"]["TravelTo"]="TravelTo";
        } else {
            // Followers 
            unsetFunction("TakeGoldFromPlayer");

        }

        $GLOBALS["ENABLED_FUNCTIONS"]=array_values(array_unique(array_filter(
            $GLOBALS["ENABLED_FUNCTIONS"] ?? [],
            function ($functionCode) use ($rechatActionWasEnabled) {
                return $rechatActionWasEnabled($functionCode);
            }
        )));

        $GLOBALS["FUNCTIONS"]=array_values(array_filter(
            $GLOBALS["FUNCTIONS"] ?? [],
            function ($functionEntry) use ($rechatActionWasEnabled) {
                if (!is_array($functionEntry) || empty($functionEntry["name"])) {
                    return false;
                }
                $functionCode=getFunctionCodeName($functionEntry["name"]);
                return $functionCode !== false && $rechatActionWasEnabled($functionCode);
            }
        ));
       
    }
}

// Instruction reinforcement
if (in_array($gameRequest[0],["instruction"]) ) {
    
    $GLOBALS["PATCH_PROMPT_ENFORCE_ACTIONS"]=false;
    $GLOBALS["COMMAND_PROMPT_ENFORCE_ACTIONS"]="";
    
}

// Enforce actions
$GLOBALS["PATCH_PROMPT_ENFORCE_ACTIONS"]=false;
$GLOBALS["COMMAND_PROMPT_ENFORCE_ACTIONS"]="";

// Rolemaster stuff
if (herikaResolveNpcRolemasterState($GLOBALS["HERIKA_NAME"] ?? '', [
    'npc_data' => $currentNpcData ?? null,
])) {
    $GLOBALS["is_rolemastered"]=true;
    $GLOBALS["NPC_ROLEMASTERED"]=true;
    error_log("{$GLOBALS["HERIKA_NAME"]} is_rolemastered");
    $GLOBALS["PATCH_PROMPT_ENFORCE_ACTIONS"]=false;
    $GLOBALS["COMMAND_PROMPT_ENFORCE_ACTIONS"]="";
} 

// MINIME_T5 STUFF, command assistant

error_log("TRACE:\t".__LINE__. "\t".__FILE__.":\t".(microtime(true) - $startTime));

if ($GLOBALS["FUNCTIONS_ARE_ENABLED"]) {
    
    if (isMinimeT5Enabled()) {
        $pattern = "/\([^)]*Context location[^)]*\)/"; // Remove (Context location..
        $replacement = "";
        $TEST_TEXT = preg_replace($pattern, $replacement, $gameRequest[3]); // // assistant vs user war
        
        $pattern = '/\(\s*(?:(?:talking|whispering|shouting)\s+to|speaking\s+(?:loudly|privately)\s+to)\s+[^()]+(?:\s+from\s+far\s+away)?\s*\)/i';
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
                    $GLOBALS["PATCH_PROMPT_ENFORCE_ACTIONS"]=false;
                    $GLOBALS["COMMAND_PROMPT_ENFORCE_ACTIONS"]="";
                } 
            }
        }
    }
    //command prompt function now injected in json_response.php with actions
    //$GLOBALS["COMMAND_PROMPT"].=$GLOBALS["COMMAND_PROMPT_FUNCTIONS"];
}

if (!empty($GLOBALS["DIRECT_NARRATOR_DIALOGUE"])) {
    $GLOBALS["PATCH_PROMPT_ENFORCE_ACTIONS"] = false;
    $GLOBALS["COMMAND_PROMPT_ENFORCE_ACTIONS"] = "";
}


// audit_log(__FILE__." [MINIME]  ".__LINE__);

error_log("TRACE:\t".__LINE__. "\t".__FILE__.":\t".(microtime(true) - $startTime));

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

$minimeEnabled = isMinimeT5Enabled();
$oghmaCustomEnabled = isOghmaSettingEnabled($GLOBALS["OGHMA_CUSTOM"] ?? false);
$oghmaInfiniumEnabled = isOghmaSettingEnabled($GLOBALS["OGHMA_INFINIUM"] ?? false);
$racialOghmaEnabled = isOghmaSettingEnabled($GLOBALS['RACIAL_OGHMA'] ?? true);
$locationOghmaEnabled = isOghmaSettingEnabled($GLOBALS['LOCATION_OGHMA'] ?? true);

// Debug: Log the actual values being checked BEFORE the conditional
error_log("[OGHMA CHECK] MINIME_T5(auto)=" . ($minimeEnabled ? 'Y' : 'N')
    . " | OGHMA_CUSTOM=" . var_export($GLOBALS["OGHMA_CUSTOM"] ?? null, true)
    . " (enabled=" . ($oghmaCustomEnabled ? 'Y' : 'N') . ")"
    . " | OGHMA_INFINIUM=" . var_export($GLOBALS["OGHMA_INFINIUM"] ?? null, true)
    . " (enabled=" . ($oghmaInfiniumEnabled ? 'Y' : 'N') . ")");

if (($minimeEnabled || $oghmaCustomEnabled || $racialOghmaEnabled || $locationOghmaEnabled) && $oghmaInfiniumEnabled) {
    if (!isset($GLOBALS["OGHMA_CALLED"])) {// Avoid double call
        require(__DIR__."/processor/oghma.php");
        $GLOBALS["OGHMA_CALLED"] = true;
    }
}
chimRequestPerformanceMark('oghma_ready');

error_log("TRACE:\t".__LINE__. "\t".__FILE__.":\t".(microtime(true) - $startTime));

if (sizeof($memoryInjectionCtx)>0) {
    // Persist memory injection
    $gameRequestCopy=$gameRequest;
    $gameRequestCopy[0]="infoaction";
    $gameRequestCopy[3]=$memoryInjectionCtx[0]["content"];
    logEvent($gameRequestCopy,$GLOBALS["HERIKA_NAME"]);// Memory log only avaibale to current NPC.
}

$contextDataHistoric = filterHistoricContextForNarratorVisibility(
    $contextDataHistoric,
    $GLOBALS["HERIKA_NAME"] ?? ""
);
$compactHistoryBlock = '';
if (chimShouldCompactNpcContextHistory($GLOBALS["HERIKA_NAME"] ?? "")) {
    $compactHistoryBlock = chimFormatCompactNpcContextHistory(
        $contextDataHistoric,
        (string)($GLOBALS["HERIKA_NAME"] ?? "")
    );
    $contextDataHistoric = [];
}
$contextDataFull = array_merge($contextDataWorld, $contextDataHistoric);

// audit_log(__FILE__." [OGHMA]  ".__LINE__);

if (($gameRequest[0]=="chatnf_book")&&($GLOBALS["BOOK_EVENT_FULL"])) {
    // When chatnf_book (make the AI to read a book), context will only be the book data.
    $contextDataFull = array_merge($contextDataFull, DataGetLastReadedBook());
    //DataGetLastReadedBook();
}


// Player bio/appearance is surfaced through the nearby actors section.


// Use centralized function from data_functions.php
$dynamicBiography = buildDynamicBiography($GLOBALS);
$worldPrompt = buildWorldPrompt($gameRequest[2] ?? 0);

$playerBioSection = "";
try {
    require_once(__DIR__.DIRECTORY_SEPARATOR."lib".DIRECTORY_SEPARATOR."core".DIRECTORY_SEPARATOR."player.class.php");
    $playerObj = new Player();
    $playerBio = ResolvePlayerBackstory($playerObj);
    $bioKnownByAll = filter_var((string)($playerObj->get('bio_known_by_all') ?? ''), FILTER_VALIDATE_BOOLEAN);
    $isNarrator = isset($GLOBALS["HERIKA_NAME"]) && strcasecmp((string)$GLOBALS["HERIKA_NAME"], "The Narrator") === 0;

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
$currentHold=trim(DataLastKnownCanonicalHoldHuman(false));
$currentLoc=trim(DataLastKnownLocationBaseHuman(false));
$rumorGametsPerDay = (int) round(24 / 0.0000024);
$currentRumorGamets = (int) $gameRequest[2];
$rumorActiveClause = "(gamets + (COALESCE(rumor_length_days, 7) * {$rumorGametsPerDay})) > {$currentRumorGamets}";
if ($currentHold) {
    error_log("[RUMORS] Current hold {$currentHold}, currentLoc {$currentLoc}");
    $rumorLocationHoldClauses = ["hold='Skyrim'"];
    $rumorHoldClauses = ["hold='Skyrim'"];
    foreach (getCanonicalHoldAliases($currentHold) as $holdAlias) {
        $holdAliasEsc = $db->escape($holdAlias);
        $rumorHoldClauses[] = "hold ILIKE '{$holdAliasEsc}'";

        if ($currentLoc !== "") {
            $currentLocEsc = $db->escape($currentLoc);
            $rumorLocationHoldClauses[] = "hold ILIKE '{$currentLocEsc}%{$holdAliasEsc}%'";
        }
    }

    $query="SELECT * FROM rumors WHERE (" . implode(" OR ", array_unique(array_merge($rumorLocationHoldClauses, $rumorHoldClauses))) . ") and {$rumorActiveClause}";
    error_log($query);
    $rumors = $db->fetchAll($query);

    if (empty($rumors)) {
        $query="SELECT * FROM rumors WHERE (" . implode(" OR ", array_unique($rumorHoldClauses)) . ") and {$rumorActiveClause}";
        error_log($query);
        $rumors = $db->fetchAll($query);
    }


    $rumorsText = build_rumor_prompt_xml($rumors);
} else {
    error_log("[RUMORS] Current hold {$currentHold} empty");
    $query="SELECT * FROM rumors WHERE hold='Skyrim' and {$rumorActiveClause}";
    error_log($query);
    $rumors = $db->fetchAll($query);
    $rumorsText = build_rumor_prompt_xml($rumors);
}

// Narration-like requests should stay descriptive instead of drifting into
// ordinary conversation turns.
if ($gameRequest[0] === "vision") {
    $GLOBALS["COMMAND_PROMPT"] = "Respond with a Soulgaze scene explanation only. Focus on what is visibly present in the provided scene context. Use the Talk action.";
} else if ($gameRequest[0] === "narration" || $gameRequest[0] === "narrator_welcome") {
    $GLOBALS["COMMAND_PROMPT"] = "Respond with atmospheric narration only. Use the Talk action.";
}

if (function_exists('chimQuestEngineApplyActionSuppressionsForTurn')) {
    chimQuestEngineApplyActionSuppressionsForTurn(
        $GLOBALS["HERIKA_NAME"] ?? '',
        $GLOBALS["CACHE_LOCATION"] ?? ''
    );
}

// Ensure actions and nearby sections are added to PROMPT_HEAD before building system prompt
require_once(__DIR__.DIRECTORY_SEPARATOR."functions".DIRECTORY_SEPARATOR."json_response.php");
require_once(__DIR__.DIRECTORY_SEPARATOR."lib".DIRECTORY_SEPARATOR."prompt_composition.php");

if (
    $gameRequest[0] === "narrator_inputtext"
    && function_exists('chimEnsureNarratorJsonResponseState')
    && (
        !function_exists('chimNarratorJsonResponseNeedsRefresh')
        || chimNarratorJsonResponseNeedsRefresh()
    )
) {
    chimEnsureNarratorJsonResponseState('JSON_RESPONSE');
}

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
        'omnivoice' => 'OMNIVOICE',
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

//chimFormatPromptXmlSections moved to misc.php, chimRemovePromptXmlBlock,chimApplyPromptContextOptionsToSystemPrompt moved to misc.php

// Check for context overrides on ext dir (plugins) before system prompt build
requireFilesRecursively(__DIR__.DIRECTORY_SEPARATOR."ext".DIRECTORY_SEPARATOR,"context_pre.php");

// Re-sync nearby sections after context_pre plugins, since plugins can mutate PROMPT_NEARBY_SECTIONS.
if (isset($GLOBALS["PROMPT_NEARBY_SECTIONS"])) {
    $nearbySections = $GLOBALS["PROMPT_NEARBY_SECTIONS"];
}

$promptInjectionContext = [
    "game_request" => $gameRequest,
    "herika_name" => function_exists('chimGetPromptCharacterName') ? chimGetPromptCharacterName() : ($GLOBALS["HERIKA_NAME"] ?? ""),
    "narrator_name" => function_exists('chimGetNarratorRoleplayName') ? chimGetNarratorRoleplayName() : 'The Narrator',
    "player_name" => $GLOBALS["PLAYER_NAME"] ?? "",
];
$characterBottomInjections = function_exists('chimRenderPromptInjections')
    ? chimRenderPromptInjections("character_bottom", $promptInjectionContext)
    : "";
$latestDiaryContext = function_exists('chimBuildLatestDiaryContextBlock')
    ? chimBuildLatestDiaryContextBlock(
        strval($GLOBALS["HERIKA_NAME"] ?? ''),
        is_array($GLOBALS["CHIM_CORE_CURRENT_PROFILE_DATA"] ?? null)
            ? $GLOBALS["CHIM_CORE_CURRENT_PROFILE_DATA"]
            : []
    )
    : "";
$promptBottomInjections = function_exists('chimRenderPromptInjections')
    ? chimRenderPromptInjections("prompt_bottom", $promptInjectionContext)
    : "";

$knowledgeSection = "";
$questContext = chimQuestEngineBuildPromptContext(
    $GLOBALS["HERIKA_NAME"] ?? '',
    $GLOBALS["CACHE_LOCATION"] ?? ''
);
if ($questContext !== '') {
    $dynamicBiography .= $questContext;
}

if (!empty($GLOBALS["OGHMA_HINT"])) {
    $knowledgeSection = "\n\n<knowledge>\n" . $GLOBALS["OGHMA_HINT"] . "\n</knowledge>";
}

$systemPromptRaw = "<roleplay_instructions>\n" . $GLOBALS["PROMPT_HEAD"] .
    "\n</roleplay_instructions>" . $worldPrompt .
    "\n\n<character>\n" . $GLOBALS["HERIKA_PERS"] . $dynamicBiography . $latestDiaryContext . $characterBottomInjections .
    "\n</character>" . $knowledgeSection .
    "\n\n<general_instructions>\n" . $GLOBALS["COMMAND_PROMPT"] .
    "\n</general_instructions>" . $actionsList . $nearbySections . $promptBottomInjections . $paralinguisticTagsPrompt .
    "\n" . $rumorsText . "\n";

$promptCompositionSections = [
    'roleplay_instructions' => $GLOBALS["PROMPT_HEAD"] ?? '',
    'world' => $worldPrompt ?? '',
    'character' => ($GLOBALS["HERIKA_PERS"] ?? '') . ($dynamicBiography ?? '') . ($latestDiaryContext ?? '') . ($characterBottomInjections ?? ''),
    'knowledge' => $knowledgeSection ?? '',
    'general_instructions' => $GLOBALS["COMMAND_PROMPT"] ?? '',
    'actions' => $actionsList ?? '',
    'nearby_actors' => $nearbySections ?? '',
    'plugin_injections' => $promptBottomInjections ?? '',
    'paralinguistic_tags' => $paralinguisticTagsPrompt ?? '',
    'rumors' => $rumorsText ?? '',
];

$systemPrompt = chimFormatPromptXmlSections(
    strtr(
        $systemPromptRaw,
        [
            "#PLAYER_NAME#" => $GLOBALS["PLAYER_NAME"],
            "#HERIKA_NAME#" => function_exists('chimGetPromptCharacterName') ? chimGetPromptCharacterName() : $GLOBALS["HERIKA_NAME"],
            "#NARRATOR_NAME#" => function_exists('chimGetNarratorRoleplayName') ? chimGetNarratorRoleplayName() : 'The Narrator',
        ]
    )
);

$systemPrompt = chimApplyPromptContextOptionsToSystemPrompt($systemPrompt);

$head[] = array('role' => 'system', 'content' => $systemPrompt);
$head = chimAppendCompactHistoryToPrompt($head, $compactHistoryBlock);

if (!empty($GLOBALS["OGHMA_HINT"])) {
    //avoid reinjecting command prompt that we have already appended
    $GLOBALS["COMMAND_PROMPT"] = "";
} else {
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
        $explicitDirectNarratorInput = false;
        if (!empty($GLOBALS["DIRECT_NARRATOR_DIALOGUE"]) && $gameRequest[0] === "narrator_inputtext" && !empty($gameRequest[3])) {
            $explicitDirectNarratorInput = true;
            if (!empty($contextDataFull)) {
                $lastContextEntry = end($contextDataFull);
                if (is_array($lastContextEntry)
                    && (($lastContextEntry["role"] ?? "") === "user")
                    && trim((string)($lastContextEntry["content"] ?? "")) === trim((string)$gameRequest[3])) {
                    $explicitDirectNarratorInput = false;
                }
                reset($contextDataFull);
            }
        }
        if (!empty($request)) {
            if ($explicitDirectNarratorInput) {
                $prompt[] = array('role' => 'user', 'content' => $gameRequest[3]);
            }
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
chimRequestPerformanceMark('prompt_ready');


if (microtime(true) - $startTime > 0.25) {
    error_log("*TRACE SQL: TOTAL DATABASE query execution time: {$GLOBALS["DB_EXECUTION_TIME"]} seconds");
    error_log("*TRACE: ".__LINE__. " at ".__FILE__.": ".(microtime(true) - $startTime)." secs building call");
}

if (($gameRequest[0] ?? '') !== 'diary') {
    chimLogPromptComposition(
        $gameRequest[0] ?? '',
        array_merge(
            $promptCompositionSections ?? [],
            [
            'history' => $contextDataFull ?? [],
            'memory_injection' => $memoryInjectionCtx ?? [],
            ]
        ),
        $contextData ?? []
    );
}

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

if (isset($contextData) && is_array($contextData) && function_exists('chimApplyNarratorRoleplayNameToContext')) {
    $contextData = chimApplyNarratorRoleplayNameToContext($contextData);
}

/**********************
CALL INITIALIZATION
***********************/


audit_log(__FILE__." [PRE LLM CALL]  ".__LINE__);

// Set LLM processing status
pipeline_status_set('llm', true);

$outputWasValid = call_llm();
chimRequestPerformanceMark('llm_complete');

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
