<?php

/**
 * BGL Processor (v2 — refactored)
 *
 * Generates an NPC "BGL" cycle when the player is absent:
 *   1. Inner-thought soliloquy (Step 1 LLM call)
 *   2. Action decision (Step 2 LLM call)
 *
 * Usage:
 *   php simple_llm_request_with_context_life_v2.php <npc_name> [dryrun|forceletter|forceaction|full] [forceaction]
 */

// ─── Bootstrap ────────────────────────────────────────────────────────────────

$startTime = microtime(true);

define('MAXIMUM_SENTENCE_SIZE', 125);
define('MINIMUM_SENTENCE_SIZE', 15);

/** Conversion factor: in-game time units (gamets) → real hours */
define('GAMETS_TO_HOURS', 0.0000024);

define('HISTORY_LIMIT', 75);   // Max number of context entries to include in the LLM prompt

// Expected globals consumed by included library functions
$GLOBALS['SCRIPTLINE_EXPRESSION'] = '';
$GLOBALS['SCRIPTLINE_LISTENER'] = '';
$GLOBALS['SCRIPTLINE_ANIMATION'] = '';

error_reporting(E_ALL);
ini_set('display_errors', 1);

$enginePath = dirname(__FILE__) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR;
$GLOBALS['ENGINE_PATH'] = $enginePath;

// ─── Includes ─────────────────────────────────────────────────────────────────

require_once $enginePath . 'lib/runtime_bootstrap.php';
chimRuntimeBootstrap($enginePath, [
    'load_general_settings' => true,
    'load_player_name' => true,
    'load_narrator' => true,
]);

require_once $enginePath . 'lib/model_dynmodel.php';
require_once $enginePath . 'lib/chat_helper_functions.php';
require_once $enginePath . 'lib/data_functions.php';
require_once $enginePath . 'lib/logger.php';
require_once $enginePath . 'lib/utils_game_timestamp.php';
require_once $enginePath . 'lib/rolemaster_helpers.php';
require_once $enginePath . 'lib/scriptproxy_papyrus.php';
require_once $enginePath . 'lib/core/player.class.php';
require_once $enginePath . 'lib/core/npc_master.class.php';
require_once $enginePath . 'lib/core/api_badge.class.php';
require_once $enginePath . 'lib/core/core_profiles.class.php';
require_once $enginePath . 'lib/core/llm_connector.class.php';
require_once $enginePath . 'lib/core/tts_connector.class.php';
require_once $enginePath . 'lib/lazy_xml.php';
require_once $enginePath . 'debug/background_action_handler.php';

// ─── Database ─────────────────────────────────────────────────────────────────

$db = $GLOBALS["db"];

// ─── Helper Functions ─────────────────────────────────────────────────────────

/**
 * Resolve the player's name from the Player table, falling back to conf_opts.
 */
function resolvePlayerName(sql $db): string
{
    try {
        $player = new Player();
        $name = $player->get('player_name');
        if (!empty($name)) {
            return $name;
        }
    } catch (Exception $e) {
        // Fall through to database fallback
    }

    $row = $db->fetchOne("SELECT value FROM conf_opts WHERE id='PLAYER_NAME'");
    return !empty($row['value']) ? $row['value'] : '';
}

/**
 * Load a background-life style prompt from the database, with hardcoded fallbacks.
 *
 * @param string $promptKey    'background_life_letter' or 'background_life_innerthought'
 * @param array  $replacements Placeholder => value pairs to substitute
 * @return string              Resolved prompt content
 */
function loadBGLStylePrompt(string $promptKey, array $replacements = []): string
{
    global $db;

    // TODO: Enable DB lookup once default prompts are ready
    $promptData = false; // $db->fetchOne("SELECT custom_prompt, default_prompt FROM prompts WHERE prompt_key='$promptKey'");

    if (!$promptData) {
        error_log("[BGL RUN] Style prompt not found: $promptKey — using fallback.");
        return getBGLStyleFallback($promptKey);
    }

    $prompt = !empty($promptData['custom_prompt'])
        ? $promptData['custom_prompt']
        : $promptData['default_prompt'];

    foreach ($replacements as $placeholder => $value) {
        $prompt = str_replace($placeholder, $value, $prompt);
    }

    return $prompt;
}

/**
 * Return the hardcoded fallback text for a BGL style prompt key.
 */
function getBGLStyleFallback(string $promptKey): string
{
    if ($promptKey === 'background_life_letter') {
        return "Write a letter to {$GLOBALS['PLAYER_NAME']} from {$GLOBALS['HERIKA_NAME']} based on the content of <text>."
            . " Use same language as <text>."
            . " Take into account the <speech_style> section for the writing style,"
            . " and particularly <letter_guidance> if present."
            . " Do not include any meta-commentary or aside, only the content of the letter.";
    }

    return "Read the <text> content, which represents a mental note or inner monologue of the character"
        . " within the Skyrim universe.\nBased on the content of the <text>,"
        . " propose one of the defined actions that would make sense for the development of the story.";
}


// ─── Argument Parsing ─────────────────────────────────────────────────────────

$npcName = $argv[1];
$argMode = $argv[2] ?? '';   // dryrun | forceletter | forceaction | full
$argMode3 = $argv[3] ?? '';   // optional third arg (forceaction)

// Simple non-blocking process lock to avoid concurrent runs for the same NPC.
$lockKeyRaw = $npcName ?: 'global';
$lockKey = preg_replace('/[^a-zA-Z0-9_-]/', '_', (string) $lockKeyRaw);
$lockPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . "herika_bgl_life_v2_{$lockKey}.lock";
$lockHandle = @fopen($lockPath, 'c');

if ($lockHandle === false) {
    error_log("[BGL RUN] $npcName — unable to create lock file at $lockPath");
    exit(1);
}

if (!flock($lockHandle, LOCK_EX | LOCK_NB)) {
    error_log("[BGL RUN] LOCK! $npcName — another background-life run is already in progress, skipping.");
    exit(0);
}

ftruncate($lockHandle, 0);
fwrite($lockHandle, (string) getmypid());
fflush($lockHandle);

register_shutdown_function(static function () use ($lockHandle): void {
    if (is_resource($lockHandle)) {
        flock($lockHandle, LOCK_UN);
        fclose($lockHandle);
    }
});

$isDryRun = ($argMode === 'dryrun');
$isFullMode = ($argMode === 'full');
$forceLetter = ($argMode === 'forceletter');
$forceAction = ($argMode === 'forceaction' || $argMode3 === 'forceaction');

$GLOBALS['HERIKA_NAME'] = $npcName;
if (empty($GLOBALS['PLAYER_NAME'])) {
    $GLOBALS['PLAYER_NAME'] = resolvePlayerName($db);
}

// Variables expected by some library functions
$CLEAN_CONTEXT_FOCUS_CHAT = false;
$COMMAND_PROMPT = '';

// ─── NPC & Connector Setup ────────────────────────────────────────────────────

$npcMaster = new NpcMaster();
$connector = new LLMConnector();

$currentNpcData = $npcMaster->getByName($npcName);
$currentConnectorData = $connector->getById($GLOBALS['CORE_CONNECTOR_BGL']);

$profile = new CoreProfile();
$currentProfileData = $profile->getById($currentNpcData['profile_id']);

$connector->setOldGlobals($currentConnectorData);
$npcMaster->setOldGlobalsFromCurrentNpcData($currentNpcData);

$extdata = $npcMaster->getExtendedData($currentNpcData);
$metadata = $npcMaster->getMetadata($currentNpcData);


// Guardrail, if background_life_last_updated_ec exceeds 2, skip processing to avoid infinite loops or repeated errors
// background_life_last_updated_ec is incremented each time an error occurs during processing, and reset to 0 on successful completion.

$backgroundLifeErrorCount = (int) ($extdata['background_life_last_updated_ec'] ?? 0);
if ($backgroundLifeErrorCount > 2) {
    error_log("[BGL RUN] $npcName — background_life_last_updated_ec exceeded 2, skipping.");
    return;
}



// ─── Game Timestamps ──────────────────────────────────────────────────────────

$lastGameTsRow = $db->fetchAll('SELECT max(gamets) AS last_gamets FROM eventlog');
$lastTsRow = $db->fetchAll("SELECT max(ts) AS ts FROM eventlog WHERE gamets='{$lastGameTsRow[0]['last_gamets']}'");

$last_gamets = (int) $lastGameTsRow[0]['last_gamets'] + 1;
$GLOBALS["LAST_GAMETS_BGL"] = $last_gamets;
$last_ts = $lastTsRow[0]['ts'];
$momentum = time();

$gameRequest = ['inputtext', '0', $last_gamets, $npcName];
$npcNameEsc = $db->escape($npcName);

// Last action issued by the NPC (if any) in the last 24 in-game hours

// Guard: Avoid running if game is paused.
if (isset($extdata["background_life_last_run"]) && $extdata["background_life_last_run"] >= $GLOBALS["LAST_GAMETS_BGL"]) {
    error_log("[BGL RUN] $npcName — background_life_last_run equals LAST_GAMETS_BGL <{$GLOBALS["LAST_GAMETS_BGL"]}>, game is paused?.");
    return;
} else {
    error_log("[BGL RUN] $npcName — background_life_last_run: {$extdata["background_life_last_run"]}, LAST_GAMETS_BGL: {$GLOBALS["LAST_GAMETS_BGL"]}");
}

$lastIssuedAction = $db->fetchOne(
    "SELECT gamets, action FROM actions_issued
     WHERE actorname='$npcNameEsc' 
     and gamets is not null
     ORDER BY gamets DESC, ts ASC"
);

if ($lastIssuedAction["gamets"] && ($lastIssuedAction["action"] == "TravelTo" || $lastIssuedAction["action"] == "MoveTo")) {
    $npcIsTravelling = true;
    $npcIsTravellingStarted = $lastIssuedAction["gamets"];
} else {
    $npcIsTravelling = false;
    $npcIsTravellingStarted = 0;
}

// Guard: Check lasts LLM requests to avoid exceeding the maximum allowed LLM calls per hour for this NPC
// Check if the last 5 calls were made within the last 2 minutes

if (checkLastCallsFor($GLOBALS['HERIKA_NAME'])) {
    error_log("[BGL RUN] $npcName — LLM call limit exceeded for this NPC, skipping.");
    if (isset($extdata["background_life_last_llm_call_suspended"]) && $extdata["background_life_last_llm_call_suspended"] === true) {
        error_log("[BGL RUN] $npcName — LLM calls are suspended for this NPC, skipping.");
    }
    markAsErrored($GLOBALS['HERIKA_NAME']);
    return;
}

// ─── Guard: Require at Least One Prior Interaction ───────────────────────────
// if background_life_player_unattached is true, we can skip this guard 

$lastInteractionRow = $db->fetchOne(
    "SELECT max(gamets) AS gamets FROM speech
     WHERE speaker='$npcNameEsc' OR listener='$npcNameEsc' OR companions LIKE '%|$npcNameEsc|%'"
);

if (empty($lastInteractionRow['gamets'])) {
    if ($extdata["background_life_player_unattached"]) {
        error_log('[BGL RUN] No prior interaction found but background_life_player_unattached is true');
    } else {
        error_log('[BGL RUN] No prior interaction found, but background_life_player_unattached is false — skipping.');
        $extdata['background_life_last_updated'] = $last_gamets;
        $npcMaster->updateExtendedKeysByName($npcName, $extdata);


        return;
    }
}

// Default behaviour is to get the events... from last interaction with player gamets 
// This can lead to too long context history.

$lastItGamets = (int) $lastInteractionRow['gamets'];

$npcNameEscDb = $db->escape($GLOBALS['HERIKA_NAME']);
$diaryEntryRowsCheck = $db->fetchAll(
    "SELECT content, gamets, topic FROM diarylog
     WHERE people='$npcNameEscDb'
       AND gamets > $lastItGamets
       AND topic IN ('Journal Note')
     ORDER BY gamets DESC, ts DESC
     LIMIT 11 OFFSET 0"
);

if (sizeof($diaryEntryRowsCheck) > 10) {
    // If there are more than 10 journal notes since last interaction
    // lastItGamets will be updated to the gamets of the last diary entry 
    $diaryEntryRowsCheck = array_reverse($diaryEntryRowsCheck);
    $lastItGamets = (int) $diaryEntryRowsCheck[0]['gamets'];
    error_log("[BGL RUN] $npcNameEsc — gamets limit updated to last diary entry gamets: $lastItGamets");
}

// ─── Guard: Skip if Last Interaction Is Within the Configured Cooldown ────────────────

$bglTriggerHours = chimGetBackgroundLifeTriggerHours();
$minDeltaForRerun = $bglTriggerHours / GAMETS_TO_HOURS;

if (($last_gamets - $lastItGamets) < $minDeltaForRerun) {
    Logger::info("[BGL RUN] $npcNameEsc — last interaction was less than {$bglTriggerHours} hours ago.");
    error_log("[BGL RUN] $npcNameEsc — last interaction was less than {$bglTriggerHours} hours ago.");

    $extLocaldata['background_life_last_updated'] = $last_gamets;
    $npcMaster->updateExtendedKeysByName($npcName, $extLocaldata);

    if ($forceLetter) {
        error_log("[BGL RUN] $npcNameEsc — bypassing interaction cooldown via forceletter.");
    } elseif ($forceAction) {
        error_log("[BGL RUN] $npcNameEsc — bypassing interaction cooldown via forceaction.");
    } else {
        return;
    }
}


$daysPassed = round(($last_gamets - $lastItGamets) * GAMETS_TO_HOURS / 24, 2);
$hoursPassed = round(($last_gamets - $lastItGamets) * GAMETS_TO_HOURS, 2);
$history = "";


// TravelTo Guard. 
// Sometimes NPCs get stuck inside a building (probably locked doors?)
// We must detect if last 2 actions were TravelTo or MoveTo, and if so, we can assume NPC is stuck and we should solve it
// Exammple
//"action","fullcall","actorname","ts","localts","gamets","original","rowid"
//"TravelTo","TravelTo:Elysium Estate:I have successfully completed my business in Whiterun, having finalized wholesale agreements with Ysolda and liquidated gemstones and a Grand Soul Gem with Belethor. Now that the treasury has been bolstered and the supply lines secured, it is time to return to the estate to record these profits in the master ledger and report my success to Varek.","Orianne Marius","273604281978300","1787053128","198555825","backgroundaction","497"
//"TravelTo","TravelTo:Elysium Estate:I have successfully completed my business in Whiterun, having finalized wholesale agreements with Ysolda and liquidated gemstones and a Grand Soul Gem with Belethor. Now that the treasury has been bolstered and the supply lines secured, it is time to return to the estate to record these profits in the master ledger and report my success to Varek.","Orianne Marius","275230813533300","1787054759","203116385","backgroundaction","500"
//"TravelTo","TravelTo:Elysium Estate:I have successfully completed my business in Whiterun, having finalized wholesale agreements with Ysolda and liquidated gemstones and a Grand Soul Gem with Belethor. Now that the treasury has been bolstered and the supply lines secured, it is time to return to the estate to record these profits in the master ledger and report my success to Varek.","Orianne Marius","282335400088300","1787061864","206665217","backgroundaction","510"
// Exmaple 2
//"action","fullcall","actorname","ts","localts","gamets","original","rowid"
//"FindNPC","FindNPC:Orianne Marius","Ingesh the Miner","1787485589","1787485599","455147659","backgroundaction","1053"
//"FindNPC","FindNPC:Orianne Marius","Ingesh the Miner","1787485580","1787485587","455147638","backgroundaction","1052"
//"FindNPC","FindNPC:Orianne Marius","Ingesh the Miner","706053222187800","1787485578","455147617","backgroundaction","1051"
//"MoveTo","MoveTo:Orianne Marius","Ingesh the Miner","706046609647600","1787485568","455132289","backgroundaction","1050"
//"FindNPC","FindNPC:Orianne Marius","Ingesh the Miner","706036532583200","1787485559","455108865","backgroundaction","1049"
//"MoveTo","MoveTo:Orianne Marius","Ingesh the Miner","706031002810800","1787485551","455096065","backgroundaction","1048"
//"FindNPC","FindNPC:Orianne Marius","Ingesh the Miner","706021437690800","1787485542","455080993","backgroundaction","1047"
//"MoveTo","MoveTo:Orianne Marius","Ingesh the Miner","706013937821600","1787485535","455063585","backgroundaction","1046

$actionsRows = $db->fetchAll(
    "SELECT action,actorname,gamets,fullcall FROM actions_issued
     WHERE actorname='$npcNameEscDb' 
       AND gamets > $lastItGamets
     ORDER BY gamets DESC, ts DESC
     LIMIT 10 OFFSET 0"
);

// Prepare the values used by the stuck checks.
// We only need the action type and the first argument after the action name.
// Examples:
//   TravelTo:Elysium Estate:...
//   MoveTo:Orianne Marius
//   FindNPC:Orianne Marius
foreach ($actionsRows as &$row) {
    $parts = explode(':', $row['fullcall'], 3);
    $row['destination'] = $parts[1] ?? '';

    // TravelTo and MoveTo are considered the same family for the travel stuck check.
    $row['action_stuck_check1'] = in_array($row['action'], ['TravelTo', 'MoveTo'], true)
        ? 'TravelTo'
        : '';

    // MoveTo and FindNPC are considered the same family for the NPC-target stuck check.
    $row['action_stuck_check2'] = in_array($row['action'], ['MoveTo', 'FindNPC'], true)
        ? 'FindNPC'
        : '';
}
unset($row);

// We deliberately require 3 consecutive actions now.
// This catches patterns such as:
//   TravelTo -> MoveTo -> TravelTo
//   TravelTo -> TravelTo -> TravelTo
//   MoveTo   -> TravelTo -> MoveTo
// etc.
//
// For the NPC-target check it also requires all 3 actions to point to the
// exact same NPC, so different FindNPC/MoveTo targets do not trigger it.
if ($actionsRows && sizeof($actionsRows) >= 3) {

    // ---------------------------------------------------------------------
    // TravelTo / MoveTo stuck check
    // ---------------------------------------------------------------------
    $lastThreeTravelActions = array_slice($actionsRows, 0, 3);

    $allTravelActions = count(array_filter(
        $lastThreeTravelActions,
        fn($row) => $row['action_stuck_check1'] === 'TravelTo'
    )) === 3;

    if ($allTravelActions) {
        $sameDestination = (
            $lastThreeTravelActions[0]['destination'] !== '' &&
            $lastThreeTravelActions[0]['destination'] === $lastThreeTravelActions[1]['destination'] &&
            $lastThreeTravelActions[1]['destination'] === $lastThreeTravelActions[2]['destination']
        );

        if ($sameDestination) {
            $destination = $lastThreeTravelActions[0]['destination'];

            error_log("[BGL RUN] $npcNameEsc — last 3 actions were TravelTo/MoveTo to the same destination ({$destination}), assuming NPC is stuck. Teleport it near destination");

            $candidateLocation = resolveTravelLocation($destination, $currentNpcData, $GLOBALS['db']);

            if ($candidateLocation["sim"] > _LOCATION_RESOLVE_SIM_THRESHOLD && $candidateLocation["refs"] != "") {
                // Extract first ref if any, e.g.
                // [refs] => 0x0001bdf1:0x2101e6ec;0x0001bdf1:0x2101e6ec
                $refs = explode(';', $candidateLocation['refs']);
                $firstReferencePair = explode(":", $refs[0]);

                $skyrimCmd = new SkyrimCommandBuilder();
                $json = $skyrimCmd->ObjectReference->MoveTo(
                    "0x{$currentNpcData['refid']}",
                    "{$firstReferencePair[1]}"
                );
                $skyrimCmd->send(cmd: $json);

                error_log("[BGL RUN] $npcNameEsc — Teleported to {$candidateLocation['name']} (formid: {$candidateLocation['formid']})");

                $db->insert('actions_issued', [
                    'action' => 'TeleportTo',
                    'fullcall' => "TeleportTo:{$candidateLocation['name']}:Teleporting to resolve stuck NPC",
                    'actorname' => $npcName,
                    'ts' => $last_ts,
                    'gamets' => $last_gamets,
                    'localts' => time(),
                    'original' => 'backgroundaction',
                ]);

                die();
            } else {
                error_log("[BGL RUN] $npcNameEsc — Could not resolve a valid location for destination: $destination");
            }
        }

        // If the last 3 actions are TravelTo/MoveTo but their destinations
        // differ, fall back to the coordinate-history check.
        if (isset($extdata['last_coords']) && is_array($extdata['last_coords'])) {
            $coordsHistory = $extdata['last_coords'];
            $recentCoords = array_slice($coordsHistory, -3);
            $uniqueLocations = array_unique(array_column($recentCoords, 3));

            if (count($uniqueLocations) === 1) {
                error_log("[BGL RUN] $npcNameEsc — last 3 coordinates are the same location ({$uniqueLocations[0]}), assuming NPC is stuck. Teleporting to resolve.");

                $candidateLocation = resolveTravelLocation($uniqueLocations[0], $currentNpcData, $GLOBALS['db']);

                if ($candidateLocation["sim"] > _LOCATION_RESOLVE_SIM_THRESHOLD && $candidateLocation["refs"] != "") {
                    $refs = explode(';', $candidateLocation['refs']);
                    $firstReferencePair = explode(":", $refs[0]);

                    $skyrimCmd = new SkyrimCommandBuilder();
                    $json = $skyrimCmd->ObjectReference->MoveTo(
                        "0x{$currentNpcData['refid']}",
                        "{$firstReferencePair[1]}"
                    );
                    $skyrimCmd->send(cmd: $json);

                    error_log("[BGL RUN] $npcNameEsc — Teleported to {$candidateLocation['name']} (formid: {$candidateLocation['formid']})");

                    $db->insert('actions_issued', [
                        'action' => 'TeleportTo',
                        'fullcall' => "TeleportTo:{$candidateLocation['name']}:Teleporting to resolve stuck NPC",
                        'actorname' => $npcName,
                        'ts' => $last_ts,
                        'gamets' => $last_gamets,
                        'localts' => time(),
                        'original' => 'backgroundaction',
                    ]);

                    die();
                } else {
                    error_log("[BGL RUN] $npcNameEsc — Could not resolve a valid location for destination: {$uniqueLocations[0]}");
                }
            }
        }
    }

    // ---------------------------------------------------------------------
    // MoveTo / FindNPC stuck check
    // ---------------------------------------------------------------------
    $lastThreeNpcActions = array_slice($actionsRows, 0, 3);

    $allNpcActions = count(array_filter(
        $lastThreeNpcActions,
        fn($row) => $row['action_stuck_check2'] === 'FindNPC'
    )) === 3;

    if ($allNpcActions) {
        // All 3 actions must target the exact same NPC.
        $targetNpcName = $lastThreeNpcActions[0]['destination'];

        $sameTargetNpc = (
            $targetNpcName !== '' &&
            $targetNpcName === $lastThreeNpcActions[1]['destination'] &&
            $targetNpcName === $lastThreeNpcActions[2]['destination']
        );

        if ($sameTargetNpc) {
            $targetNpcData = $npcMaster->getByName($targetNpcName);

            if ($targetNpcData && isset($targetNpcData['refid'])) {
                $skyrimCmd = new SkyrimCommandBuilder();
                $json = $skyrimCmd->ObjectReference->MoveTo(
                    "0x{$currentNpcData['refid']}",
                    "0x{$targetNpcData['refid']}"
                );
                $skyrimCmd->send(cmd: $json);

                error_log("[BGL RUN] $npcNameEsc — Last 3 MoveTo/FindNPC actions target the same NPC {$targetNpcName}. Teleported to resolve stuck NPC.");

                $db->insert('actions_issued', [
                    'action' => 'TeleportTo',
                    'fullcall' => "TeleportTo:{$targetNpcName}:Teleporting to resolve stuck NPC",
                    'actorname' => $npcName,
                    'ts' => $last_ts,
                    'gamets' => $last_gamets,
                    'localts' => time(),
                    'original' => 'backgroundaction',
                ]);

                die();
            } else {
                error_log("[BGL RUN] $npcNameEsc — Could not resolve target NPC {$targetNpcName} for teleportation.");
            }
        }
    }
}
// ─── Dynamic Biography ────────────────────────────────────────────────────────

$dynamicBiography = buildDynamicBiography($GLOBALS, true, true, true);
$dynamicBiography = $npcMaster->appendBackgroundLifeGoals($dynamicBiography, $currentNpcData);

if (isset($extdata['middle_term_memory'])) {
    $middleTermMemory = end($extdata['middle_term_memory']);
    $dynamicBiography .= "\n\n<middle_term_memory>\nPast events\n{$middleTermMemory}\n</middle_term_memory>";
}

// ─── Dialogue History ─────────────────────────────────────────────────────────

if ($extdata["background_life_player_unattached"] === true) {

    $sqlFilter = " AND gamets < $lastItGamets"
        . " AND type NOT IN ('prechat','itemfound','npcspellcast','innerchat','infoaction')";
} else {
    $sqlFilter = " AND gamets < $lastItGamets"
        . " AND type NOT IN ('prechat','itemfound','infoaction','npcspellcast','innerchat')"
        . " AND data NOT LIKE '%inner thoughts%'";
}

$contextDataHistoric = DataLastDataExpandedFor($GLOBALS['HERIKA_NAME'], -100, $sqlFilter);
/*$contextDataHistoric = filterHistoricContextForNarratorVisibility(
    $contextDataHistoric,
    $GLOBALS['HERIKA_NAME'] ?? ''
);*/

if ($extdata['background_life_player_unattached']) {
    // NPC unattached, so maybe does not know anything about player
    foreach ($contextDataHistoric as $entry) {
        $line = trim($entry['content']);
        $history .= ($entry['role'] === 'assistant')
            ? "{$GLOBALS['HERIKA_NAME']}: $line\n\n"
            : "$line\n\n";
    }
} else {
    $history = "\n<last_dialogue>
This represents last dialogue where player ({$GLOBALS['PLAYER_NAME']}) was present. Can be more dialogues with other NPCs from this point.\n";
    foreach ($contextDataHistoric as $entry) {
        $line = trim($entry['content']);
        $history .= ($entry['role'] === 'assistant')
            ? "{$GLOBALS['HERIKA_NAME']}: $line\n\n"
            : "$line\n\n";
    }
    $history .= "\nNote: {$GLOBALS['PLAYER_NAME']} is absent from this point on.\n</last_dialogue>\n";
}

// ─── Last Known Location ──────────────────────────────────────────────────────

$lastLocRow = $db->fetchOne(
    "SELECT location, gamets FROM speech
     WHERE speaker='$npcNameEsc' OR listener='$npcNameEsc' OR companions LIKE '%|$npcNameEsc|%'
     ORDER BY gamets DESC, ts DESC"
);

// ─── Diary Entries Since Last Iteration ──────────────────────────────────────

$npcNameEscDb = $db->escape($GLOBALS['HERIKA_NAME']);
$diaryEntryRows = $db->fetchAll(
    "SELECT content, gamets, topic FROM diarylog
     WHERE people='$npcNameEscDb'
       AND gamets > $lastItGamets
       AND topic IN ('Sent Letter','Journal Note')
     ORDER BY gamets DESC, ts DESC
     LIMIT 16 OFFSET 0"
);

$diaryEntries = [];
foreach (array_reverse($diaryEntryRows) as $row) {
    $hoursAgo = number_format(($last_gamets - $row['gamets']) * GAMETS_TO_HOURS, 2);
    if ($row['topic'] === 'Sent Letter') {
        $diaryEntries[] = [
            'gamets' => $row['gamets'],
            'content' => "{$row['content']}",
            'type' => ($row['topic'] === 'Sent Letter') ? 'sent_letter' : 'diary_entry',
        ];

        // Update daysPassed to reflect the latest inner chat entry if it's older than the last interaction
        $daysPassed = round(($last_gamets - $row['gamets']) * GAMETS_TO_HOURS / 24, 2);
        $hoursPassed = round(($last_gamets - $row['gamets']) * GAMETS_TO_HOURS, 2);
    }
}

// ─── Remote dialogues  ──────────────────────────────────────

$npcNameEscDb = $db->escape($GLOBALS['HERIKA_NAME']);
$innerChatEntryRows = $db->fetchAll(
    "SELECT data, gamets,ts,people FROM eventlog
     WHERE (people like '%|$npcNameEscDb|%' or people='$npcNameEscDb')
       AND gamets > $lastItGamets
       AND type IN ('innerchat')
     ORDER BY gamets DESC, ts DESC
     LIMIT 100 OFFSET 0"
);

$innerChats = [];
$localCounter = 0;


foreach (array_reverse($innerChatEntryRows) as $row) {

    if (strpos($row['data'], 'can sell these items') !== false) {
        if ($localCounter < sizeof($innerChatEntryRows) - 3) {
            $localCounter++;
            continue; // Skip this entry if it's not one of the last 3 inner chats
            // $row['data'] = "content skipped due to being a trader inner chat";
        }
    }

    $hoursAgo = number_format(($last_gamets - $row['gamets']) * GAMETS_TO_HOURS, 2);
    $innerChats[] = [
        'gamets' => $row['gamets'],
        'content' => "{$row['data']}",
        'type' => 'inner_chat',
    ];
    // Update daysPassed to reflect the earliest inner chat entry if it's older than the last interaction
    $daysPassed = round(($last_gamets - $row['gamets']) * GAMETS_TO_HOURS / 24, 2);
    $hoursPassed = round(($last_gamets - $row['gamets']) * GAMETS_TO_HOURS, 2);
    $localCounter++;
}

// ─── Actions since last Interaction ───────────────────────────────────


$actionsRows = $db->fetchAll(
    "SELECT action,actorname,gamets,fullcall FROM actions_issued
     WHERE actorname='$npcNameEscDb' and action in ('TravelTo','MoveTo')
       AND gamets > $lastItGamets
     ORDER BY gamets DESC, ts DESC
     LIMIT 16 OFFSET 0"
);
$actions = [];
foreach (array_reverse($actionsRows) as $row) {
    $hoursAgo = number_format(($last_gamets - $row['gamets']) * GAMETS_TO_HOURS, 2);
    $actions[] = [
        'gamets' => $row['gamets'],
        'content' => ($row["action"] == "TravelTo" ? "{$row['actorname']} starts journey: {$row['fullcall']}" :
            "{$row['actorname']} moves to: {$row['fullcall']}"),
        'type' => 'travel_action',

    ];
    // Update daysPassed to reflect the earliest action entry if it's older than the last interaction
    $daysPassed = round(($last_gamets - $row['gamets']) * GAMETS_TO_HOURS / 24, 2);
    $hoursPassed = round(($last_gamets - $row['gamets']) * GAMETS_TO_HOURS, 2);
}


// ─── Background Events Since Last Interaction ───────────────────────────────────

$bgEvents = [];
$lastEventParsed = [];   // Tracks the most recent valid background event for location context

$lastLocRow['location'] = $lastLocRow['location'] ?? '';

error_log("Last interaction gamets: $lastItGamets, location: {$lastLocRow['location']}");

$backgroundEventRows = $db->fetchAll(
    "SELECT gamets, data FROM eventlog
     WHERE type='backgroundaction' AND gamets > $lastItGamets
     ORDER BY gamets ASC, ts ASC"
);

foreach ($backgroundEventRows as $event) {
    $eventParsed = json_decode($event['data'], true);

    if (empty($eventParsed['source']) || $eventParsed['source'] !== 'AIAgent.esp') {
        continue;
    }
    if (empty($eventParsed['description']) || $eventParsed['description'] === 'unknown') {
        continue;
    }
    if ($eventParsed['actor'] !== $GLOBALS['HERIKA_NAME']) {
        continue;
    }

    $bgEvents[] = [
        'gamets' => $event['gamets'],
        'content' => $eventParsed['description'],
        'type' => 'event',
    ];
    $lastEventParsed = $eventParsed;   // Keep last matching event for location reference

    // Update daysPassed to reflect the latest background event if it's older than the last interactions
    $daysPassed = round(($last_gamets - $event['gamets']) * GAMETS_TO_HOURS / 24, 2);
    $hoursPassed = round(($last_gamets - $event['gamets']) * GAMETS_TO_HOURS, 2);
}

$lastIssuedBgEvent = $lastEventParsed;
// Append last known speech location
if ($lastLocRow['location']) {
    $bgEvents[] = [
        'gamets' => $lastLocRow['gamets'],
        'content' => $lastLocRow['location'],
        'type' => 'last_known_location',
    ];
}

// Append current and historical coordinate data
$LAST_REPORTED_LOCATION = '';

if (isset($metadata['last_coords']) && !empty($metadata['last_coords'][3])) {
    $coords = $metadata['last_coords'];
    $hoursAgo = number_format(($last_gamets - $coords['last_updated']) * GAMETS_TO_HOURS, 2);
    $bgEvents[] = [
        'gamets' => $coords['last_updated'],
        'content' => "{$coords[3]}",
        'type' => 'reported_location',
    ];
    $LAST_REPORTED_LOCATION = $coords[3];

    $richLocation = $db->fetchOne("SELECT name,region,hold,is_interior  FROM locations WHERE formid='{$coords["location_formid"]}'");
    // error_log("[BGL RUN]  Last reported location: " . json_encode($coords) . " => rich location: " . json_encode($richLocation));
    if ($richLocation && !empty($richLocation['name'])) {
        $LAST_REPORTED_LOCATION = $richLocation['name'];
        if (checkInterior($richLocation['is_interior'])) {
            $LAST_REPORTED_LOCATION .= " (Interior)";
        }
    }
}


if (isset($metadata['low_process_actors'])) {

    // Keep only the last 5 entries.
    $metadataLow_process_actors = array_slice($metadata['low_process_actors'], -5, 5, true);
    $metadataLow_process_actors = ($metadata['low_process_actors']);
    foreach ($metadataLow_process_actors as $gamets_lpa_processed => $actorList) {
        if ($gamets_lpa_processed <= $lastItGamets) {
            continue;
        }
        $hoursAgo = number_format(($last_gamets - $gamets_lpa_processed) * GAMETS_TO_HOURS, 2);
        // actorList in the form of name
        if ($actorList === []) {
            $actorList = ["No visible characters nearby"];
        }

        $actorListExpanded = [];
        foreach ($actorList as $key => $actor) {
            if (is_array($actor)) {

                $npcMaster = new NpcMaster();
                $actorRow = $npcMaster->getByName($actor[1]);
                if ($actorRow && isset($actorRow['oghma_knowledge_tags']) && !empty($actorRow['oghma_knowledge_tags'])) {
                    $actorListExpanded[] = "$key;$actor;{$actorRow['oghma_knowledge_tags']}";
                } else if ($actorRow && isset($actorRow['race']) && !empty($actorRow['race'])) {
                    $actorListExpanded[] = "$key;$actor;{$actorRow['race']} {$actorRow['gender']}";
                } else {
                    $actorListExpanded[] = "$key;$actor;;";
                }
            } else {

                $npcMaster = new NpcMaster();
                $actorRow = $npcMaster->getByName($actor);
                if ($actorRow && isset($actorRow['oghma_knowledge_tags']) && !empty($actorRow['oghma_knowledge_tags'])) {
                    $actorListExpanded[] = "$key;$actor;{$actorRow['oghma_knowledge_tags']}";
                } else if ($actorRow && isset($actorRow['race']) && !empty($actorRow['race'])) {
                    $actorListExpanded[] = "$key;$actor;{$actorRow['race']} {$actorRow['gender']}";
                } else {
                    $actorListExpanded[] = "$key;$actor;;";
                }
            }
        }

        $bgEvents[] = [
            'gamets' => $gamets_lpa_processed,
            'content' => "Nearby actors/npc {$GLOBALS['HERIKA_NAME']} can see (refid;name;tags): \n" . implode("\n", $actorListExpanded) . "\n",
            'type' => 'nearby_npcs',
        ];
    }
}


if (isset($metadata['last_inventory_update_gamets'])) {
    $nullArray = [];
    $bgEvents[] = [
        'gamets' => $metadata['last_inventory_update_gamets'],
        'content' => implode("\n", chimFormatInventoryPromptLines($metadata['inventory'] ?? [], null, $nullArray, false, true)),
        'type' => 'inventory_update',
    ];
}

// ─── Rumors Near Current Location ────────────────────────────────────────────

if ($LAST_REPORTED_LOCATION) {
    $locationEsc = $db->escape(str_replace(" (Interior)", "", $LAST_REPORTED_LOCATION));
    $rumorSinceTs = $last_gamets - ((24 * 7) / GAMETS_TO_HOURS);   // Last 7 in-game days
    $rumorRows = $db->fetchAll(
        "SELECT gamets, content FROM rumors
         WHERE (
            hold LIKE '%{$locationEsc}%' 
            or hold IN (SELECT distinct(hold) FROM locations where name='$locationEsc')
            or hold IN (SELECT distinct(region) FROM locations where name in (SELECT distinct(hold) FROM locations where name='$locationEsc'))
            )
         AND gamets > $rumorSinceTs order by gamets desc, ts desc LIMIT 2 OFFSET 0"
    );
    error_log("[BGL RUN] LAST_REPORTED_LOCATION " . count($rumorRows) . " rumors near <$LAST_REPORTED_LOCATION> since gamets $rumorSinceTs");
    foreach ($rumorRows as $rumor) {
        $bgEvents[] = [
            'gamets' => $rumor['gamets'],
            'content' => $rumor['content'],
            'type' => 'rumor',
        ];
    }
}

// ─── Merge & Sort Events; Append to History ───────────────────────────────────

$combinedEvents = array_merge($bgEvents, $diaryEntries, $innerChats, $actions);
usort($combinedEvents, fn($a, $b) => $a['gamets'] <=> $b['gamets']);

// To avoid very long contexts, lets consider only last 100 records from combinedEvents
// if sizeof($diaryEntryRowsCheck)>10, we can consider context has grown big enough.
if (sizeof($diaryEntryRowsCheck) > 10) {
    $combinedEvents = array_slice($combinedEvents, HISTORY_LIMIT * -1, HISTORY_LIMIT, true);
    error_log("[BGL RUN] Slicing context history");
}



if (empty($combinedEvents)) {
    $history .= "Note: After these events, $daysPassed days have passed.";
}

$previousGamets = 0;
foreach ($combinedEvents as $entry) {
    $content = $entry['content'];
    if ($entry['type'] === 'event' && $previousGamets) {
        $hoursSincePrev = round(($entry['gamets'] - $previousGamets) * GAMETS_TO_HOURS, 2);
        $hoursAgo = round(($last_gamets - $entry['gamets']) * GAMETS_TO_HOURS, 2);
        $content = "* {$hoursSincePrev}h after last entry: {$content}, {$hoursAgo}h ago";
    }
    $previousGamets = $entry['gamets'];
    $history .= "\n<{$entry['type']} date=\"" . convert_gamets2skyrim_date($entry['gamets']) . "\">\n{$content}\n</{$entry['type']}>\n";
}

echo str_repeat('=', 63) . PHP_EOL;

$closestLocations = getLocationsNearNpcCoords($GLOBALS['HERIKA_NAME']);
if (is_array($closestLocations) && count($closestLocations) > 0) {
    $history .= "Hint: Closest locations to {$GLOBALS['HERIKA_NAME']} ordered by distance. (Use TravelTo to move to one of this locations if needed):\n";
    foreach ($closestLocations as $loc) {
        $history .= "\n$loc";
    }
    $history .= "\n";
}

$postHistory = "\nCurrent location: $LAST_REPORTED_LOCATION\n";
$postHistory .= "\nCurrent date and hour: " . convert_gamets2skyrim_long_date($last_gamets) . "\n";

// ─── Check last Idles  ───────────────────────────────────

$lastMinuteNotes = "\n";
$fortyEightHoursAgo = $last_gamets - 48 / GAMETS_TO_HOURS;
$actionIdleRows = $db->fetchAll(
    "SELECT action,actorname,gamets,fullcall FROM actions_issued
     WHERE actorname='$npcNameEscDb' and action in ('Idle')
       AND gamets > $fortyEightHoursAgo
     ORDER BY gamets DESC, ts DESC
     LIMIT 10 OFFSET 0"
);
if (sizeof($actionIdleRows) > 3) {

    $summaryIdleActions = [];
    $summaryIdleActions['Sleep'] = 0;
    $summaryIdleActions['Work'] = 0;
    $summaryIdleActions['Relax'] = 0;
    $summaryIdleActions['Socialize'] = 0;

    foreach ($actionIdleRows as $row) {
        $data = explode(":", $row['fullcall']);

        $actionType = $data[2] ?? '';
        if (isset($summaryIdleActions[$actionType])) {
            $summaryIdleActions[$actionType]++;
        }
    }

    if ($summaryIdleActions['Sleep'] == 0) {
        $lastMinuteNotes .= "\nNote: {$GLOBALS['HERIKA_NAME']} hasn't been sleeping for the last 48h. This may affect health and well-being.\n";
    }
    if ($summaryIdleActions['Work'] >= 3) {
        $lastMinuteNotes .= "\nNote: {$GLOBALS['HERIKA_NAME']} has been working too much for the last 48h. This may affect health and well-being.\n";
    }
    if ($summaryIdleActions['Socialize'] == 0) {
        $lastMinuteNotes .= "\nNote: {$GLOBALS['HERIKA_NAME']} hasn't been properly socializing for the last 48h. This may affect health and well-being. Should make an effort to interact with others at a inn or tavern by staying with intent 'Socialize'.\n";
    }
    if ($summaryIdleActions['Relax'] == 0 && ($summaryIdleActions['Sleep'] == 0)) {
        $lastMinuteNotes .= "\nNote: {$GLOBALS['HERIKA_NAME']} hasn't been relaxing for the last 48h. This may affect health and well-being.\n";
    }
    error_log("[BGL RUN] $npcNameEscDb — summary of last 48h idle actions: " . json_encode($summaryIdleActions));
}



// ─── Language Detection ───────────────────────────────────────────────────────

$npcMetadata = json_decode($currentNpcData['metadata'], true) ?? [];
$profileMetadata = json_decode($currentProfileData['metadata'], true) ?? [];


// ─── NPC Production Detection ───────────────────────────────────────────────────────

// Lets check if last action was Idle. This means NPC is staying at a place doing something
// We must as first what was doing. We need to know:
// 1) If NPC was on a relaxing scenario (inn..home..), ask if we consumed any item in inventory (food, drink, potion, etc)
// 2) If NPC was on a working (scenario), ask if we produced any good. (iron ore, leather, etc). Subsection production at <goals> specifies what is produced and how much per hour. We must check if we have produced any good.

$npcNameEscBg = $db->escape($GLOBALS['HERIKA_NAME']);
$lastBackgroundAction = $db->fetchOne(
    "SELECT action, fullcall, gamets
     FROM actions_issued
     WHERE actorname='$npcNameEscBg' AND original='backgroundaction'
     AND gamets is not null
     ORDER BY gamets DESC, localts DESC
     LIMIT 1"
);

$isIdleAction = !empty($lastBackgroundAction)
    && (
        strcasecmp((string) ($lastBackgroundAction['action'] ?? ''), 'Idle') === 0
        || stripos((string) ($lastBackgroundAction['fullcall'] ?? ''), 'StayAtPlace:') === 0
    );

$idleGamets = (int) ($lastBackgroundAction['gamets'] ?? 0);
$idleHours = max(0, round(($last_gamets - $idleGamets) * GAMETS_TO_HOURS, 2));


if ($isIdleAction && $idleHours > 1) { // If last Idle was Socialize, there a chance of a follow up.
    $intent = explode(':', (string) ($lastBackgroundAction['fullcall'] ?? ''));
    $lastIntent = $intent[2] ?? '';
    $lastIntentBasedHint = "";

    if ($lastIntent === 'Work') {
        $lastIntentBasedHint = "Hint: Last intent was 'Work', so check if any goods were produced during the idle period based on production rules.";
    }

    if ($lastIntent === 'Relax') {
        $lastIntentBasedHint = "Hint: Last intent was 'Relax', so probably ate/drank items in inventory.";
    }

    if ($lastIntent === 'Socialize') {
        $lastIntentBasedHint = "Hint: Last intent was 'Socialize', so probably drank items in inventory.";
    }

    if ($lastIntent === 'Sleep') {
        $bypassProduction = true;
    } else {
        $bypassProduction = false;
    }

    if (!$bypassProduction) {
        // We don't need full history, just a short version to determine if we consumed or produced something
        // We consider only the last 150 lines of history for this purpose
        $historyShort = implode("\n", array_slice(explode("\n", $history), -150));

        $preStep1Prompt = [
            ['role' => 'system', 'content' => 'Examine this text containing events that occurred in the fictional universe of Skyrim (The Elder Scrolls).'],
            [
                'role' => 'user',
                'content' => "<character_sheet>\n{$GLOBALS['HERIKA_NAME']}:\n$dynamicBiography\n</character_sheet>",
                "cache_control" => ["type" => "ephemeral"]
            ],
            [
                'role' => 'user',
                'content' => "<context_history>\nContext History (chronological order)\n... $historyShort\n</context_history>\n$postHistory\n",
                "cache_control" => ["type" => "ephemeral"]
            ],
            [
                'role' => 'user',
                'content' => "
The character has been idle for the last `$idleHours` hours.

Your task is to determine what happened during this idle period and return the single most appropriate action.

Rules:

0. Check latest {$GLOBALS["HERIKA_NAME"]}'s intent to know if the NPC was in a relaxing or working scenario. Sometimes place seems a working place but the NPC is relaxing or resting.

1. Relaxing scenarios
   - If the NPC was in a relaxing scenario (e.g. inn, home, tavern, camp, etc.), and last intent was a relaxing/sleeping intent, determine whether any consumable items should have been used during the last `$idleHours` hours.
   - Consumables include food, drinks, potions, medicine, or any other item intended to be consumed.
   - Only report items that would actually have been consumed during the idle period and *present on the character's inventory*.

2. Working scenarios
   - If the NPC was in a working scenario,(and last intent was a production intent) determine whether any goods were produced during the last `$idleHours` hours.
   - Inspect the `[production]` subsection inside `<background_life_goals>` first, then `<goals>` if no Background Life production rule is defined, to find:
     - what item(s) are produced
     - the production rate (units per hour)
   - Calculate production only for the last `$idleHours` hours.
   - If production is fractional, round up
   - Produced goods will be added to the character's inventory in the future, so they will not be present in the current inventory.

3. No activity
   - If neither is a working or relaxing scenario (e.g. {$GLOBALS["HERIKA_NAME"]} was sleeping), return the `DoNothing` action.

Requirements

- Consider **only** the last `$idleHours` hours.
- Do not infer events outside this time window.
- Produce exactly one action.
- Base your decision solely on the current scenario, inventory, goals, and production rules provided in the context.
- Do not invent production or consumption that is not supported by the data.

Choose the action that best describes what occurred during the idle period.
$lastIntentBasedHint
"
            ],
            [
                'role' => 'user',
                'content' => "
Return ONLY a valid JSON object with no extra text, no markdown, and no explanation.

Format:
{
  \"action\": [
    \"Consume:itemid:qty\",
    \"Produced:itemid:qty\",
    \"DoNothing\"
  ],
  \"reasoning\": \"optional one-sentence explanation\"
}

Rules:
- Use an empty array [] if no consumption or production happened.
- Only include valid actions in this exact string format:
  Consume:itemid:qty
  Produced:itemid:qty
  DoNothing
- itemid must match in-game inventory identifiers.
- qty must be an integer.
- You may include multiple actions if needed.
- reasoning must be short (one sentence)
- Do not add any keys other than 'action' and 'reasoning'.
- 1 gold coin (or septim) is represented as itemid 0000000F. 9 gold coins would be represented as 0000000F:9, 900 gold coins would be represented as 0000000F:900, and so on.
"
            ]
        ];

        Logger::debug(__LINE__ . ' ' . (microtime(true) - $startTime));

        $connectionHandler = $connector->getConnector($currentConnectorData);
        $preResponse = $connectionHandler->fast_request($preStep1Prompt, ['MAX_TOKENS' => 1024], 'backgroundlife');

        // Keep timestamp of last LLM call for this NPC to avoid too frequent calls
        updateLastLLMCall($GLOBALS['HERIKA_NAME']);

        $parsedResponse = __jpd_decode_lazy($preResponse);

        if (isset($parsedResponse[0]) && is_array($parsedResponse[0])) {
            $parsedResponse = $parsedResponse[0];
        }

        if (isset($parsedResponse['action']) && is_array($parsedResponse['action'])) {
            $action = ($parsedResponse['action']);
        } else {
            $action = '';
        }
        if (isset($parsedResponse['reasoning'])) {
            $reasoning = $parsedResponse['reasoning'];
        } else {
            $reasoning = '';
        }


        if ($action) {
            $actionTextDescription = [];
            foreach ($action as $singleAction) {
                error_log("[BGL RUN] $npcNameEsc — Idle production/consumption detected: $singleAction. Reasoning: $reasoning");

                $skyrimCmd = new SkyrimCommandBuilder();
                $sourceRefHexString = strtolower(convertSignedToUnsignedHex(hexdec($currentNpcData['refid'])));
                // Parse action string
                list($actionType, $itemId, $count) = explode(':', $singleAction);
                $itemId = strtr(strtolower($itemId), ["0x" => ""]); // Remove 0x prefix if present

                $count = (int) $count;
                if ($actionType === 'Consume') {
                    $json = $skyrimCmd->ObjectReference->RemoveItem($sourceRefHexString, "0x$itemId", $count, true);
                    $skyrimCmd->send(cmd: $json);
                } elseif ($actionType === 'Produced') {
                    $json = $skyrimCmd->ObjectReference->AddItem($sourceRefHexString, "0x$itemId", $count, true);
                    $skyrimCmd->send(cmd: $json);
                }

                $itemName = getNameForItemReference(strtoupper($itemId));

                if ($itemName) {
                    $itemNameResolved = "($count {$itemName})";
                } else {
                    $itemNameResolved = "";
                }

                $actionText[] = $singleAction;
                $actionTextDescription[] = $itemNameResolved;
            }

            $actionTextFinal = implode(', ', $actionText);
            $actionTextDescriptionFinal = sizeof($actionTextDescription) > 0 ? implode(', ', $actionTextDescription) : "";

            sleep(sizeof($action));   // Allow time for the command to be processed
            // Send signal to update inventory
            $db->insert('responselog', [
                'localts' => time(),
                'sent' => 0,
                'actor' => 'rolemaster',
                'text' => '',
                'action' => "rolecommand|BackgroundCmd@$sourceRefHexString@UpdateInventory",
                'tag' => '',
            ]);

            sleep(1);   // Allow time for the command to be processed

            $db->insert('eventlog', [
                'ts' => $last_ts,
                'gamets' => $last_gamets - 10,
                'type' => 'innerchat',
                'data' => "The Narrator: $npcName produced/consumed items while idle: $actionTextFinal $actionTextDescriptionFinal. Reasoning: $reasoning",
                'sess' => $momentum,
                'localts' => time(),
                'people' => "|$npcName|",
                'location' => null,
                'party' => '',
            ]);

            // Insert bgl_history log entry
            $db->insert(
                'bgl_history',
                [
                    'npc' => $npcName,
                    'ts' => $last_ts,
                    'gamets' => $last_gamets - 10,
                    'localts' => time(),
                    'data' => "$npcName produced/consumed items while idle: $actionTextFinal $actionTextDescriptionFinal. Reasoning: $reasoning",
                    'category' => 'produce_consume'
                ]
            );

            sleep(1);   // Allow time for the command to be processed

            // Refetch the NPC data to update the dynamic biography with the new inventory state
            $dynamicBiography = buildDynamicBiography($GLOBALS, true, true, true);
            $dynamicBiography = $npcMaster->appendBackgroundLifeGoals($dynamicBiography, $currentNpcData);

            if (isset($extdata['middle_term_memory'])) {
                $middleTermMemory = end($extdata['middle_term_memory']);
                $dynamicBiography .= "\n\n<middle_term_memory>\nPast events\n{$middleTermMemory}\n</middle_term_memory>";
            }
            $history .= "\nThe Narrator: $npcName produced/consumed items while idle: $actionTextFinal $actionTextDescriptionFinal. Reasoning: $reasoning. Inventory will get updated next turn.";
        } else {
            error_log("[BGL RUN] $npcNameEsc — Idle production/consumption detected: " . json_encode($parsedResponse) . ". No action taken.");
        }
    }
}


// ─── Last iteration was speak ───────────────────────────────────────────────────────


$npcNameEscBg = $db->escape($GLOBALS['HERIKA_NAME']);
$lastBackgroundAction = $db->fetchOne(
    "SELECT action, fullcall, gamets
     FROM actions_issued
     WHERE actorname='$npcNameEscBg' AND original='backgroundaction'
     and gamets is not null
     ORDER BY gamets DESC, localts DESC
     LIMIT 1"
);

$isSpeakAction = !empty($lastBackgroundAction)
    && (
        strcasecmp((string) ($lastBackgroundAction['action'] ?? ''), 'SpeakTo') === 0
        || stripos((string) ($lastBackgroundAction['fullcall'] ?? ''), 'SpeakTo:') === 0
    );


// Language detection (for translated prompts in the future (TO-DO))
$lang = (($npcMetadata['CORE_LANG'] ?? '') === 'es' || ($profileMetadata['CORE_LANG'] ?? '') === 'es')
    ? 'es'
    : 'en';


// Hinter
error_log(date("YMd H:i:s") . " [BGL RUN] HINT $npcNameEsc — last action: {$lastBackgroundAction['action']}, last event: <{$lastIssuedBgEvent['name']}> <{$lastIssuedBgEvent['event']}>, npcIsTravelling: " . ($npcIsTravelling ? 'true' : 'false'));
if (
    strtolower($lastIssuedBgEvent["name"]) == "sandbox" && $lastIssuedBgEvent["event"] == "start" && $npcIsTravelling
    || strtolower($lastIssuedBgEvent["name"]) == "travelto" && $lastIssuedBgEvent["event"] == "end" && $npcIsTravelling
) {
    // Last action was MoveTo or TravelTo.
    // Last event was a Sandbox event. This means the NPC reached destination
    // 
    error_log(date("YMd H:i:s") . " [BGL RUN] HINT bypassInnerThoughts: true");
    $bypassInnerThoughts = true;
} else {
    $bypassInnerThoughts = false;
}

// Avoid too much transactions.

$byspassTradingActions = false;
if ($lastBackgroundAction['action'] === 'BuyItem' || $lastBackgroundAction['action'] === 'SellItem' || $lastBackgroundAction['action'] === 'SellService') {
    // Last action was BuyItem, SellItem or SellService.
    // Avoid repeated trading actions in the same turn, as it can lead to infinite loops of buying/selling items/services.
    // 
    error_log(date("YMd H:i:s") . " [BGL RUN] HINT bypass Trading actions: true");
    $bypassTradingActions = true;
} else {
    $bypassTradingActions = false;
}

$localHoursPassed = round($last_gamets - (1 / GAMETS_TO_HOURS), 2);

$tradingGuard = $db->fetchAll("select * from actions_issued where actorname='$npcNameEscDb' and action in ('BuyItem','SellItem','SellService') and gamets>$localHoursPassed order by gamets desc limit 5");
if (sizeof($tradingGuard) > 3) {
    error_log(date("YMd H:i:s") . " [BGL RUN] HINT bypass Trading actions: because transactions>=3 in the last hour");
    $bypassTradingActions = true;
}


// Socialize chain
$wasSocializeIntentAction = false;
if (
    !empty($lastBackgroundAction)
    && (
        strcasecmp((string) ($lastBackgroundAction['action'] ?? ''), 'Idle') === 0
        || stripos((string) ($lastBackgroundAction['fullcall'] ?? ''), 'StayAtPlace:') === 0
    )
) {
    // If NPC selected StayAtPlace and intent Socialize, there is a chance that next turn happens inmediatly after (50%)
    // If this is the case, we can skip inner thoughts and go directly to action decision suggestion SpeakTo.
    // If this is the case, $hoursAgo should have a low value.

    $action_parts = explode(":", $lastBackgroundAction['fullcall'] ?? '');
    if ($action_parts[0] === 'StayAtPlace' && isset($action_parts[2]) && strtolower($action_parts[2]) === 'socialize') {
        if ($hoursPassed < 1) {
            $wasSocializeIntentAction = true;
            $bypassInnerThoughts = true;
            //$innerThoughtBufferForced = "{$GLOBALS['HERIKA_NAME']}'s inner thought: I'm socializing, let's see who is around and speak to them.";
            $innerThoughtBufferForced = "{$GLOBALS['HERIKA_NAME']}'s inner thought: I'm here to socialize. If there's already a conversation I'm part of, I'll stay engaged with it. Otherwise, I'll look around for someone to meet and start a conversation.";
            error_log(date("YMd H:i:s") . " [BGL RUN] HINT bypassInnerThoughts: true (socialize intent)");
        } else {
            // Was a socialize intent action, but more than 1 hour has passed since last action. We will generate inner thoughts.
            $wasSocializeIntentAction = true;
        }
    }
}

// IF last action was less than half an hour ago, skip inner thoughts and go directly to action decision suggestion.
$action_parts = explode(":", $lastBackgroundAction['fullcall'] ?? '');
$localHoursPassed = round(($last_gamets - $lastBackgroundAction['gamets']) * GAMETS_TO_HOURS, 2);
if ($localHoursPassed < 0.5 && !$wasSocializeIntentAction) {

    $bypassInnerThoughts = true;
    $innerThoughtBufferForced = "{$GLOBALS['HERIKA_NAME']}'s inner thought: Let’s see where this takes us";
    error_log(date("YMd H:i:s") . " [BGL RUN] HINT bypassInnerThoughts: true (last actions was half an hour ago or less)");
}


// Inception

if (isset($extdata['bgl_inception']) && !empty($extdata['bgl_inception'])) {
    $lastMinuteNotes .= "\nImportant:A thought crosses {$GLOBALS['HERIKA_NAME']}'s mind: He should {$extdata['bgl_inception']}\n";
    $npcMaster = new NpcMaster();
    $npcData = $npcMaster->getByName($GLOBALS['HERIKA_NAME']);
    $npcMaster->updateExtendedKeysByName($GLOBALS['HERIKA_NAME'], ['bgl_inception' => ""]);
    error_log("[BGL RUN] HINT inception: {$extdata['bgl_inception']}");
}


// Check gold

$currentNpcData = $npcMaster->getByName($npcName); // Refresh NPC data to ensure we have the latest metadata

$npcMetadata = json_decode($currentNpcData['metadata'], true) ?? [];
$profileMetadata = json_decode($currentProfileData['metadata'], true) ?? [];

$inventory = $npcMetadata["inventory"] ?? [];
foreach ($inventory as $item) {
    if (isset($item['baseid']) && strcasecmp($item['baseid'], '0000000F') === 0) {
        $goldFound = true;
        $goldAmount = (int) ($item['count'] ?? 0);
        if ($goldAmount < 50) {
            $lastMinuteNotes .= "\nNote: {$GLOBALS['HERIKA_NAME']} has low gold budget ($goldAmount). Consider StayAtPlace, work intent, to ensure financial stability.\n";
            error_log("[BGL RUN] $npcNameEscDb — low gold budget: $goldAmount");

            break;
        }
    }
}

$lastMinuteNotesSpeakContext = "";

if (!isset($goldFound)) {
    $lastMinuteNotes .= "\nNote: {$GLOBALS['HERIKA_NAME']} has no gold coins. Should work to get some coins. Check background_life_goals->production to know how to earn gold.\n";
    $lastMinuteNotesSpeakContext .= "\nNote: {$GLOBALS['HERIKA_NAME']} has no gold coins, so cannot buy anything.\n";
    error_log("[BGL RUN] $npcNameEscDb — has no gold!");
} else
    $lastMinuteNotesSpeakContext = "";

// ─── Step 1: Inner-Thought Soliloquy ─────────────────────────────────────────
if ($wasSocializeIntentAction && !$bypassInnerThoughts) {
    // If last action was a socialize intent, we will generate inner thoughts, 
    // but we will add a note to the inner thoughts that the NPC is socializing.
    $innerThoughtEnforceSocialice = "* Also, as last action was a socialize intent, simulate that {$GLOBALS['HERIKA_NAME']} has been talking with other characters (E.G. I've talked to X about <topic>, and Y about <topic>)";
    error_log(date("YMd H:i:s") . " [BGL RUN] HINT inner thoughts: enforced socialize/conversation prompt");
} else
    $innerThoughtEnforceSocialice = "";

$systemPrompts = [
    'en' => [['role' => 'system', 'content' => 'You are a writing assistant. Examine this text containing events that occurred in the fictional universe of Skyrim (The Elder Scrolls).']],
];

$noteAboutPlayer = $extdata['background_life_player_unattached']
    ? ""
    : "Important note: {$GLOBALS['PLAYER_NAME']} and {$GLOBALS['HERIKA_NAME']} are NOT in the same place after the <context_history> events.";


$userPrompts = [
    'en' => <<<PROMPT_EN
The main character in this logbook is {$GLOBALS['HERIKA_NAME']}.
Read the context history (context_history) and the recent memories (middle_term_memory),
paying attention to notable events and the names of relevant characters.

Based on all this information, generate an inner-thought soliloquy for {$GLOBALS['HERIKA_NAME']}.
Take into account the <speech_style> section for the writing style, and particularly
<inner_thought_guidance> if present.

This soliloquy should reflect what the character might have done over the last {$hoursPassed} hours(s), 
and after last inner thoughts presented in the <context_history>:

* Intimate thoughts.
* Evolution of the character's state of mind based on latest inner thoughts (if any) and events.
* Consider the character's goals, desires, and motivations. Give special attention to <background_life_goals> when present; <goals> contains their general motivations.
* Short (2 paragraphs max), concise, and focused on the character's perspective.
$innerThoughtEnforceSocialice

Always respect the character's last known location. If the character is in a specific place,
generated content should occur in that area or its surroundings. The character may express the
intention to travel elsewhere, but such travel should only be described as an immediate plan. (e.g. I'm going to)


$noteAboutPlayer

Write in English as if you were {$GLOBALS['HERIKA_NAME']}, in a soliloquy, speaking to yourself
in first person.

PROMPT_EN,
];

$step1Prompt = array_merge($systemPrompts[$lang], [
    ['role' => 'user', 'content' => "<character_sheet>\n{$GLOBALS['HERIKA_NAME']}:\n$dynamicBiography\n</character_sheet>", "cache_control" => ["type" => "ephemeral"]],
    ['role' => 'user', 'content' => "<context_history>\nContext History (chronological order)\n$history\n</context_history>{$postHistory}\n{$lastMinuteNotes}", "cache_control" => ["type" => "ephemeral"]],
    ['role' => 'user', 'content' => $userPrompts[$lang], "cache_control" => ["type" => "ephemeral"]],
]);

Logger::debug(__LINE__ . ' ' . (microtime(true) - $startTime));

$recordInnerThoughts = true;

if (!$isSpeakAction) {
    // If last action was not SpeakTo, we generate inner thoughts. If it was SpeakTo, we skip this step to avoid redundant inner thoughts.

    if ($bypassInnerThoughts == false) {
        $connectionHandler = $connector->getConnector($currentConnectorData);
        $innerThoughtBuffer = $connectionHandler->fast_request($step1Prompt, ['MAX_TOKENS' => 2048], 'backgroundlife');
        updateLastLLMCall($GLOBALS['HERIKA_NAME']);
        $recordDiaryEntry = true;
    } else {
        $innerThoughtBuffer = $innerThoughtBufferForced ?? "{$GLOBALS['HERIKA_NAME']}'s inner thought: I've reached destination, I must figure out my next action";
        $recordInnerThoughts = false;
        $recordDiaryEntry = false;
    }
} else {
    //If last action was SpeakTo, we skip this step to avoid redundant inner thoughts.
    $innerThoughtBuffer = "{$GLOBALS['HERIKA_NAME']}'s inner thought: I must figure out my next action";
    $recordInnerThoughts = false;
    $recordDiaryEntry = false;
}

Logger::debug(__LINE__ . ' ' . (microtime(true) - $startTime));
echo $innerThoughtBuffer . PHP_EOL;

// ─── Dry-Run Guard ────────────────────────────────────────────────────────────

if ($isDryRun && !$forceAction) {
    die();
}

// ─── Step 2: Action Decision ─────────────────────────────────────────────────

$lettersEnabled = isset($extdata['background_life_letters']) && $extdata['background_life_letters'] === true;

$innerThoughtStyle = loadBGLStylePrompt('background_life_innerthought');

$step2Content = "You are responsible for deciding a single action"
    . " based on the character's inner thoughts and the provided context.\n"
    . "Character's name is {$GLOBALS['HERIKA_NAME']}.\n"
    . "$dynamicBiography\n\n";

if ($isFullMode) {
    $step2Content .= "<context_history>\nContext History (chronological order)\n$history\n</context_history>{$postHistory} {$lastMinuteNotes}\n\n";
}

$step2Content .= "<text>\n$innerThoughtBuffer\n</text>\n\n";
$step2Content .= $innerThoughtStyle . "\n\n";


$step2Content .= <<<PROMPT
Choose exactly **one** action for this turn.

Decision rules (highest priority first):

1. Continue an unfinished action (travel, transaction, meeting, etc.) whenever appropriate.
2. If the NPC has an active goal, choose the action that makes the most progress toward that goal.
3. Avoid unnecessary movement or repetitive conversations.
4. Do not invent information that is not present in the context.

Available actions:

StayAtPlace:<Place>:<intent>
- intent can be: Work, Rest, Relax, Socialize, Sleep, Study, Guard.
- Remain at the current location to work, rest, relax, socialize, or perform ongoing activities.
- This is the default action when the NPC should remain where they are.
- At an inn: rest, relax, socialize with patrons. E.G StayAtPlace:Inn:Relax, StayAtPlace:Inn:Socialize (Socialize is preferred if there are other NPCs present)
- At home: rest, relax, socialize with companions,sleep. e.g StayAtPlace:Breezehome:Sleep
- If gathering information or spreading rumors, remain for at least 24 hours.
- After arriving somewhere, prefer interacting (SpeakTo, BuyItem, SellItem, SellService) before choosing StayAtPlace again, unless there is no meaningful interaction available.

FindNPC:<NPC name>
- Search for an NPC whose current location is unknown.
- Use before MoveTo or SpeakTo when the target's location is unknown.
- Requires a clear reason.

MoveTo:<NPC name>
- Move to an NPC whose current location is already known.
- Only use for characters, never for places.
- Requires a clear reason.
PROMPT;

if (!$isSpeakAction) {
    $step2Content .= <<<PROMPT


SpeakTo:<NPC name>:<npc_refid>
- Start a conversation with another NPC (should be nearby).
- Avoid selecting SpeakTo repeatedly with no new purpose.
- Prefer conversations that advance goals, exchange information, negotiate, or socialize.
PROMPT;
} else {
    error_log(date("YMd H:i:s") . " [BGL RUN] HINT $npcNameEsc — last action was SpeakTo, skipping SpeakTo in available actions.");
}


if (!isset($extdata['background_life_player_unattached']) || $extdata['background_life_player_unattached'] == false) {
    $returnHomeAction = "ReturnHome
- Return to the base location to meet {$GLOBALS['PLAYER_NAME']}.
- Use only after all current goals have been completed.";
} else
    $returnHomeAction = "";

// Needs to be worked. We need to define a "home". Moving to player (current ReturnHome implementation, is not a general case)
$returnHomeAction = "";

if (!$bypassTradingActions) {
    $step2Content .= <<<PROMPT

BuyItem:<NPC name>:<itemid>:<count>:<total_gold_spent>,<NPC name>:<itemid>:<count>:<total_gold_spent>
- Buy items from another NPC.
- Required after a previously agreed trade so inventories can be updated.
- total_gold_spent is <item price>*<count>, the total amount of gold spent for that item, including any haggling or discounts.

SellItem:<NPC name>:<itemid>:<count>:<total_gold_amount>,<NPC name>:<itemid>:<count>:<total_gold_amount>,...
- Sell items to another NPC.
- Required after a previously agreed trade so inventories can be updated.
- total_gold_amount is <item price>*<count>, the total amount of gold received for that item, including any haggling or discounts (price*count).

GiveItemTo:<NPC name>:<itemid>:<count>,<NPC name>:<itemid>:<count>
- Give items directly to one or more NPCs with no gold exchange.
- Use this for gifts, aid, or non-commercial handoffs.
- Item should be on {$GLOBALS["HERIKA_NAME"]}'s inventory.
- E.G. If you want to invite someone to a drink, you must have the drink item in your inventory. If not, buy it from an innkeeper or trader first, then give it to the NPC.

GiveGoldTo:<NPC name>:<gold_amount>,<NPC name>:<gold_amount>
- Give gold directly to one or more NPCs.
- Use this for gifts, donations, payments, or helping allies where only gold should be transferred.

SellService:<NPC name>:<service_description>:<total_gold_amount>,<NPC name>:<service_description>:<total_gold_amount>
- Sell a service to another NPC. No inventory item is moved; only gold changes hands.
- The service_description is a short label (e.g. 'healing', 'repair', 'lockpicking', 'mercenary work') describing what was provided.
- total_gold_amount is the full price paid by the buyer for the service.
PROMPT;
}
$step2Content .= <<<PROMPT2

TravelTo:<Place>
- Travel to another location.
- Use only when the destination is different from the current location and travel is necessary.

$returnHomeAction
PROMPT2;

// SendLetter action is only available if the background_life_letters feature is enabled in the extended data.
if (isset($extdata['background_life_letters']) && $extdata['background_life_letters'] == true) {

    $step2Content .= "
SendLetter
- Send a letter to {$GLOBALS["PLAYER_NAME"]}.
";
}

if ($npcIsTravelling) {
    $step2Content .= <<<PROMPT3
Continue
- Continue executing the previously selected action.
- Prefer this while travelling unless there is a compelling reason to interrupt or change destination.

Note:
{$GLOBALS['HERIKA_NAME']} is already travelling. Do NOT issue another TravelTo action unless the destination must change.

PROMPT3;
}


// Hinter

if (
    (strtolower($lastIssuedBgEvent["name"]) == "sandbox" && $lastIssuedBgEvent["event"] == "start" && $npcIsTravelling)
    || (strtolower($lastIssuedBgEvent["name"]) == "travelto" && $lastIssuedBgEvent["event"] == "end" && $npcIsTravelling)
) {

    // Last action was MoveTo or TravelTo.
    // Last event was a Sandbox event. This means the NPC reached destination
    // 
    $actionChoiceDesc = "Hint: Character just reached destination. Preferred actions should be:
    * SpeakTo (talk with another nearby character)
    * FindNPC (if wanting to talk to a specific character and is not present)
    * TravelTo (keeps moving if current location is not the final destination)";
} else {
    if ($isSpeakAction) {
        $actionChoiceDesc = "Hint: The character has just completed a conversation. Analyze the dialogue outcome first. If there is an unresolved transaction, continue it by choosing the appropriate action: BuyItem, SellItem, SellService, or GiveItemTo.
If no transaction is pending, review the character's active goals and select the action that provides the highest progress toward achieving them.";
    } else {
        $actionChoiceDesc = "";
    }
}

$step2Content .= "$actionChoiceDesc\n"
    . "\nElement Definitions:\n```\n"
    . "```\n\n"
    . "- Your answer must use XML format, containing exactly 2 elements.\n"
    . "- NEVER include commentary inside or outside the element tags or ANY content beyond the defined format.\n\n"
    . "Use only this exact Response Format:\n```\n"
    . "<action> ... </action>\n"
    . "<reason> ... </reason>\n"
    . "```";

$step2Content .= "Example: ```\n\n"
    . "<action>FindNPC:Adrianne Avenicci</action>\n"
    . "<reason>I need to find Adrianne to speak to her</reason>\n"
    . "```";
if (!$isSpeakAction) {
    $step2Content .= "Examples ```\n\n"
        . "<action>SpeakTo:Adrianne Avenicci:0001A67C</action>\n"
        . "<reason>I need to speak to Adrianne Avenicci to progress in my current objectives.</reason>\n"
        . "```";
}

if (!$bypassTradingActions) {
    $step2Content .= "Examples ```\n\n"
        . "<action>BuyItem:Adrianne Avenicci:000721E8:1:5,Adrianne Avenicci:00065C97:2:16</action>\n"
        . "<reason>I agreed to buy two items from Adrianne Avenicci, 1 Cooked Beef (5 gold), 2 Bread (16 gold)</reason>\n"
        . "```";

    $step2Content .= "Examples ```\n\n"
        . "<action>GiveGoldTo:Lucan Valerius:25</action>\n"
        . "<reason>I want to support Lucan Valerius with some gold.</reason>\n"
        . "```";

    $step2Content .= "Examples ```\n\n"
        . "<action>SellService:Lucan Valerius:repair:50</action>\n"
        . "<reason>I repaired Lucan's lock for 50 gold.</reason>\n"
        . "```";
}
$step2Content .= "
Rules:
- Only ONE action may be chosen per round.
- The action must be consistent with the context_history, memories, and current location.
- Previous actions are present at the context_history, prevent repetition, use previous actions on history to figure out if main goal is achieved or not, and decide accordingly.
For example:
* To Sell/Buy Item to a trader: SpeakTo:<NPC/Actor name> ->(next iteration) SellItem:.. 
* To Sell/Buy Item to a trader that maybe is not present: MoveTo:<NPC/Actor name> ->(next iteration) SpeakTo:<NPC/Actor name> ->(next iteration) SellItem:.. 
* To gift items without taking money: SpeakTo:<NPC/Actor name> ->(next iteration) GiveItemTo:<NPC/Actor name>:<itemid>:<count>
* To give money without trading items: SpeakTo:<NPC/Actor name> ->(next iteration) GiveGoldTo:<NPC/Actor name>:<amount>
* To sell a service: SpeakTo:<NPC/Actor name> ->(next iteration) SellService:<NPC/Actor name>:<service_description>:<amount>
* Buy food at an inn: SpeakTo:<NPC innkeeper> ... ->(next iteration),BuyItem:<NPC/Actor name>... ->(next iteration) StayAtPlace:Inn ...
* Relax/Socialize at an inn: SpeakTo:<NPC/Actor name> ->(next iteration) ->(next iteration) StayAtPlace:Inn 
* Relax at home: SpeakTo:<NPC/Actor name> ->(next iteration) StayAtPlace:Home:Sleep
* Generally speaking, try to Speak to an NPC before trading with him/her, unless the NPC is not present. If the NPC is not present, use MoveTo:<NPC name> to reach him/her first.



";

$step2Prompt = [['role' => 'system', 'content' => $step2Content]];
$connectionHandler = $connector->getConnector($currentConnectorData);
$decisionBuffer = $connectionHandler->fast_request($step2Prompt, ['MAX_TOKENS' => 2048], 'backgroundlife');
updateLastLLMCall($GLOBALS['HERIKA_NAME']);

echo $decisionBuffer . PHP_EOL;

// Refresh NPC data to ensure we have the latest information before executing any actions
// This is important because the NPC's state may have changed during the decision-making process

$currentNpcData = $npcMaster->getByName($npcName);
$extdata = $npcMaster->getExtendedData($currentNpcData);
$metadata = $npcMaster->getMetadata($currentNpcData);

// ─── Update Background-Life Timestamp ────────────────────────────────────────

$extdata['background_life_last_updated'] = $last_gamets;
$npcMaster->updateExtendedKeysByName($npcName, $extdata);


// ─── Parse LLM Decision Response ─────────────────────────────────────────────

$parsed = [
    'action' => manual_get_tag_content($decisionBuffer, 'action'),
    'notification' => '',
    'rumor' => '',
    'reason' => manual_get_tag_content($decisionBuffer, 'reason')
];

print_r($parsed);

if (!is_array($parsed)) {
    die();
}

if ($isDryRun && $forceAction) {   // In dry-run mode (forceAction was enabled), we stop after parsing the decision without executing actions
    die();
}

$refHexString = convertSignedToUnsignedHex(hexdec($currentNpcData['refid']));

// ─── Dispatch: Movement / Stay Action ────────────────────────────────────────
$recordDiaryEntry = true;
if (!empty($parsed['action'])) {
    [$actionCmd, $actionArg] = array_pad(explode(':', $parsed['action'], 2), 2, null);
    error_log("[BGL RUN] Chosen action: $actionCmd, argument: $actionArg, reason: {$parsed['reason']}");
    $GLOBALS["LAST_REASON"] = $parsed['reason'];
    switch ($actionCmd) {
        case 'TravelTo':
            handleTravelToAction($actionArg, $currentNpcData, $GLOBALS['HERIKA_NAME'], $last_ts, $last_gamets, $momentum, $lastEventParsed, $db);
            unset($parsed['rumor']);   // Prevent rumor dispatch if MoveTo action is chosen
            break;
        case 'StayAtPlace':
            [$stayLocation, $stayIntent] = array_pad(explode(':', (string) $actionArg, 2), 2, '');
            $stayLocation = trim($stayLocation);
            $stayIntent = trim($stayIntent);
            handleStayAtPlaceAction($stayLocation, $currentNpcData, $GLOBALS['HERIKA_NAME'], $last_ts, $last_gamets, $momentum, $db, $stayIntent);
            break;
        case 'ReturnHome':
            handleReturnHome($actionArg, $currentNpcData, $GLOBALS['HERIKA_NAME'], $last_ts, $last_gamets, $momentum, $db);
            unset($parsed['notification']);   // Prevent letter dispatch if ReturnHome action is chosen
            break;
        case 'FindNPC':
            handleFindNPCAction($actionArg, $currentNpcData, $GLOBALS['HERIKA_NAME'], $last_ts, $last_gamets, $momentum, $db, $LAST_REPORTED_LOCATION);
            unset($parsed['notification']);   // Prevent letter dispatch if FindNPC action is chosen
            unset($parsed['rumor']);   // Prevent rumor dispatch if FindNPC action is chosen

            break;
        case 'MoveTo':
            handleMoveToAction($actionArg, $currentNpcData, $GLOBALS['HERIKA_NAME'], $last_ts, $last_gamets, $momentum, $db);
            unset($parsed['notification']);   // Prevent letter dispatch if MoveTo action is chosen
            unset($parsed['rumor']);   // Prevent rumor dispatch if MoveTo action is chosen

            break;
        case 'SpeakTo':
            $historyWithInnerThought = $history
                . "\n{$postHistory}\n$lastMinuteNotesSpeakContext\n<inner_thought>\n{$innerThoughtBuffer}\n</inner_thought>\n";
            handleSpeakToAction($actionArg, $currentNpcData, $GLOBALS['HERIKA_NAME'], $last_ts, $last_gamets, $momentum, $db, $connectionHandler, $dynamicBiography, $historyWithInnerThought, $lastEventParsed['location']);
            unset($parsed['notification']);   // Prevent letter dispatch if SpeakTo action is chosen
            //unset($parsed['rumor']);   // Prevent rumor dispatch if SpeakTo action is chosen

            break;
        case 'BuyItem':
        case 'SellItem':
        case 'GiveItemTo':
            // Support semicolon-separated multi-item trades, e.g.:
            // BuyItem:NPC:itemid1:count1:gold1;BuyItem:NPC:itemid2:count2:gold2
            $tradeEntries = explode(';', $parsed['action']);
            foreach ($tradeEntries as $tradeEntry) {
                $tradeEntry = trim($tradeEntry);
                if ($tradeEntry === '')
                    continue;
                [$tradeCmd, $tradeArg] = array_pad(explode(':', $tradeEntry, 2), 2, null);
                $tradeCmd = trim($tradeCmd);
                if ($tradeCmd !== 'BuyItem' && $tradeCmd !== 'SellItem' && $tradeCmd !== 'GiveItemTo')
                    continue;
                handleTradeItemsAction($tradeCmd, $tradeArg, $currentNpcData, $GLOBALS['HERIKA_NAME'], $last_ts, $last_gamets, $momentum, $db);
            }
            unset($parsed['notification']);
            unset($parsed['rumor']);

            break;
        case 'GiveGoldTo':
            handleGiveGoldToAction($actionArg, $currentNpcData, $GLOBALS['HERIKA_NAME'], $last_ts, $last_gamets, $momentum, $db);
            unset($parsed['notification']);
            unset($parsed['rumor']);

            break;
        case 'SellService':
            handleSellServiceAction($actionArg, $currentNpcData, $GLOBALS['HERIKA_NAME'], $last_ts, $last_gamets, $momentum, $db);
            unset($parsed['notification']);
            unset($parsed['rumor']);

            break;
        case 'Continue':
            error_log("[BGL RUN] Chosen action: Continue. No new action will be issued. Reason: {$parsed['reason']}");
            unset($parsed['notification']);
            unset($parsed['rumor']);

            break;
        case 'SendLetter':
            $historyWithInnerThought = $history
                . "\n{$postHistory}\n<inner_thought>\n{$innerThoughtBuffer}\n</inner_thought>\n";
            handleSendLetter($parsed['reason'], $currentNpcData, $GLOBALS['HERIKA_NAME'], $last_ts, $last_gamets, $momentum, $db, $connectionHandler, $dynamicBiography, $historyWithInnerThought, $lastEventParsed['location']);
            error_log("[BGL RUN] Chosen action: SendLetter. No new action will be issued. Reason: {$parsed['reason']}");
            unset($parsed['notification']);
            unset($parsed['rumor']);

            break;
        default:
            error_log("[BGL RUN] ERROR! Chosen action: $actionCmd. No handler implemented for this action. Reason: {$parsed['reason']}");
            unset($parsed['notification']);
            unset($parsed['rumor']);
            $recordDiaryEntry = false;
            triggerNpcUpdate($GLOBALS['HERIKA_NAME'], ($extdata['background_life_last_updated_ec'] ?? 0) + 1);
            break;
    }
    updateLastActionGameTs($GLOBALS['HERIKA_NAME']);
}

// ─── Dispatch: Letter / Notification (disabled) ─────────────────────────────

/*
if (!empty($parsed['notification']) && $lettersEnabled) {
    // Disabled in v2 flow: Step 2 now decides action only.
}
*/

// ─── Dispatch: Rumor (disabled) ──────────────────────────────────────────────

/*
if (!empty($parsed['rumor'])) {
    // Disabled in v2 flow: Step 2 now decides action only.
}
*/

// ─── Persist Inner Thought to Event & Diary Logs ──────────────────────────────

if ($innerThoughtBuffer && $recordInnerThoughts) {
    $db->insert('eventlog', [
        'ts' => $last_ts,
        'gamets' => $last_gamets,
        'type' => 'innerchat',
        'data' => "{$GLOBALS['HERIKA_NAME']}'s inner thoughts: " . $innerThoughtBuffer,
        'sess' => $momentum,
        'localts' => time(),
        'people' => $GLOBALS['HERIKA_NAME'],
        'location' => $lastEventParsed['location'] ?? null,
        'party' => '',
    ]);
    $cnName = $db->escape($GLOBALS['HERIKA_NAME']);
    $checkLatestDiaryEntry = $db->fetchOne("SELECT * FROM diarylog WHERE topic='Journal Note' AND people='$cnName' ORDER BY gamets DESC, ts DESC LIMIT 1");
    $latestDiaryGamets = (float) $checkLatestDiaryEntry['gamets'];
    if ($last_gamets - $latestDiaryGamets < (1 / GAMETS_TO_HOURS) * 4) {
        // If the last diary entry was less than 4 hours ago, we skip adding a new diary entry to avoid cluttering the diary with too many entries in a short time.
        $recordDiaryEntry = false;
    }

    if ($recordDiaryEntry) {
        $db->insert('diarylog', [
            'ts' => $last_ts,
            'gamets' => $last_gamets,
            'topic' => 'Journal Note',
            'content' => convert_gamets2skyrim_long_date($last_gamets) . "\n" . trim($innerThoughtBuffer),
            'tags' => 'Auto-diary, backgroundlife',
            'people' => $GLOBALS['HERIKA_NAME'],
            'location' => $lastEventParsed['location'] ?? null,
            'sess' => $momentum,
            'localts' => time(),
        ]);
    }

    logMemory($GLOBALS['HERIKA_NAME'], $GLOBALS['HERIKA_NAME'], trim($innerThoughtBuffer), $momentum, $last_gamets, 'backgroundlife_diary', $last_ts);
}
// ─── Mark NPC as Background-Life Enabled ─────────────────────────────────────

$currentNpcData = $npcMaster->getByName($npcName);
$extdata = $npcMaster->getExtendedData($currentNpcData);
if (!$extdata['background_life_enabled']) {
    $extdata['background_life_enabled'] = true;
    $currentNpcData = $npcMaster->setExtendedData($currentNpcData, $extdata);
    $npcMaster->updateByArray($currentNpcData);
}

if (is_resource($lockHandle)) {
    flock($lockHandle, LOCK_UN);
    fclose($lockHandle);
}
die();
