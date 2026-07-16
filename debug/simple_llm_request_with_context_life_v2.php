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
$last_ts = $lastTsRow[0]['ts'];
$momentum = time();

$gameRequest = ['inputtext', '0', $last_gamets, $npcName];
$npcNameEsc = $db->escape($npcName);

// Last action issued by the NPC (if any) in the last 24 in-game hours

$lastIssuedAction = $db->fetchOne(
    "SELECT gamets, action FROM actions_issued
     WHERE actorname='$npcNameEsc' 
     ORDER BY gamets DESC, ts ASC"
);

if ($lastIssuedAction["gamets"] && ($lastIssuedAction["action"] == "TravelTo" || $lastIssuedAction["action"] == "MoveTo")) {
    $npcIsTravelling = true;
    $npcIsTravellingStarted = $lastIssuedAction["gamets"];
} else {
    $npcIsTravelling = false;
    $npcIsTravellingStarted = 0;
}



// ─── Guard: Require at Least One Prior Interaction ───────────────────────────

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

$lastItGamets = (int) $lastInteractionRow['gamets'];

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

// ─── Dynamic Biography ────────────────────────────────────────────────────────

$dynamicBiography = buildDynamicBiography($GLOBALS, true, true, true);

if (isset($extdata['middle_term_memory'])) {
    $middleTermMemory = end($extdata['middle_term_memory']);
    $dynamicBiography .= "\n\n<middle_term_memory>\nPast events\n{$middleTermMemory}\n</middle_term_memory>";
}

// ─── Dialogue History ─────────────────────────────────────────────────────────

if ($extdata["background_life_player_unattached"] === true) {

    $sqlFilter = " AND gamets < $lastItGamets"
        . " AND type NOT IN ('prechat','itemfound','npcspellcast')";

} else {
    $sqlFilter = " AND gamets < $lastItGamets"
        . " AND type NOT IN ('prechat','itemfound','infoaction','npcspellcast','innerchat')"
        . " AND data NOT LIKE '%inner thoughts%'";
}

$contextDataHistoric = DataLastDataExpandedFor($GLOBALS['HERIKA_NAME'], -50, $sqlFilter);
/*$contextDataHistoric = filterHistoricContextForNarratorVisibility(
    $contextDataHistoric,
    $GLOBALS['HERIKA_NAME'] ?? ''
);*/

if ($extdata['background_life_player_unattached']) {
    // NPC unattached, so maybe does not nothing about player
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
     LIMIT 16 OFFSET 0"
);

$innerChats = [];
foreach (array_reverse($innerChatEntryRows) as $row) {
    $hoursAgo = number_format(($last_gamets - $row['gamets']) * GAMETS_TO_HOURS, 2);
    $innerChats[] = [
        'gamets' => $row['gamets'],
        'content' => "{$row['data']}",
        'type' => 'inner_chat',
    ];
    // Update daysPassed to reflect the earliest inner chat entry if it's older than the last interaction
    $daysPassed = round(($last_gamets - $row['gamets']) * GAMETS_TO_HOURS / 24, 2);
    $hoursPassed = round(($last_gamets - $row['gamets']) * GAMETS_TO_HOURS, 2);
}

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


// ─── Background Events Since Last Iteration ───────────────────────────────────

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
        if ($richLocation['is_interior']) {
            $LAST_REPORTED_LOCATION .= " (Interior)";
        }
    }
}


if (isset($metadata['low_process_actors'])) {

    foreach ($metadata['low_process_actors'] as $gamets_lpa_processed => $actorList) {
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
                } else {
                    $actorListExpanded[] = "$key;$actor;;";
                }
            } else {
                
                $npcMaster = new NpcMaster();
                $actorRow = $npcMaster->getByName($actor);
                if ($actorRow && isset($actorRow['oghma_knowledge_tags']) && !empty($actorRow['oghma_knowledge_tags'])) {
                    $actorListExpanded[] = "$key;$actor;{$actorRow['oghma_knowledge_tags']}";
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
    $bgEvents[] = [
        'gamets' => $metadata['last_inventory_update_gamets'],
        'content' => implode("\n", chimFormatInventoryPromptLines($metadata['inventory'] ?? [])),
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
    $history .= "\n<{$entry['type']} date=\"".convert_gamets2skyrim_date($entry['gamets'])."\">\n{$content}\n</{$entry['type']}>\n";
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

$history .= "\nCurrent location: $LAST_REPORTED_LOCATION\n";
$history .= "\nCurrent date and hour: ".convert_gamets2skyrim_long_date($last_gamets)."\n";





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
     ORDER BY gamets DESC, localts DESC
     LIMIT 1"
);

$isIdleAction = !empty($lastBackgroundAction)
    && (
        strcasecmp((string) ($lastBackgroundAction['action'] ?? ''), 'Idle') === 0
        || stripos((string) ($lastBackgroundAction['fullcall'] ?? ''), 'StayAtPlace:') === 0
    );

if ($isIdleAction) {
    $idleGamets = (int) ($lastBackgroundAction['gamets'] ?? 0);
    $idleHours = max(0, round(($last_gamets - $idleGamets) * GAMETS_TO_HOURS, 2));

    $preStep1Prompt = [
        ['role' => 'system', 'content' => 'Examine this text containing events that occurred in the fictional universe of Skyrim (The Elder Scrolls).'],
        [
            'role' => 'user',
            'content' => "<character_sheet>\n{$GLOBALS['HERIKA_NAME']}:\n$dynamicBiography\n</character_sheet>",
            "cache_control" => ["type" => "ephemeral"]
        ],
        [
            'role' => 'user',
            'content' => "<context_history>\nContext History (chronological order)\n$history\n</context_history>",
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
   - If the NPC was in a relaxing scenario (e.g. inn, home, tavern, camp, etc.), determine whether any consumable items should have been used during the last `$idleHours` hours.
   - Consumables include food, drinks, potions, medicine, or any other item intended to be consumed.
   - Only report items that would actually have been consumed during the idle period and *present on the character's inventory*.

2. Working scenarios
   - If the NPC was in a working scenario, determine whether any goods were produced during the last `$idleHours` hours.
   - Inspect the `[production]` subsection inside `<goals>` to find:
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
"
        ]
    ];

    Logger::debug(__LINE__ . ' ' . (microtime(true) - $startTime));

    $connectionHandler = $connector->getConnector($currentConnectorData);
    $preResponse = $connectionHandler->fast_request($preStep1Prompt, ['MAX_TOKENS' => 1024], 'backgroundlife');

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

            $itemName = $db->fetchOne("SELECT * FROM \"public\".\"combined_descriptions\" where baseid='" . strtoupper($itemId) . "'");
            if ($itemName) {
                $itemNameResolved = "($count {$itemName["name"]})";
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
            ]
        );

        sleep(1);   // Allow time for the command to be processed

        // Refetch the NPC data to update the dynamic biography with the new inventory state
        $dynamicBiography = buildDynamicBiography($GLOBALS, true, true, true);

        if (isset($extdata['middle_term_memory'])) {
            $middleTermMemory = end($extdata['middle_term_memory']);
            $dynamicBiography .= "\n\n<middle_term_memory>\nPast events\n{$middleTermMemory}\n</middle_term_memory>";
        }
        $history .= "\nThe Narrator: $npcName produced/consumed items while idle: $actionTextFinal $actionTextDescriptionFinal. Reasoning: $reasoning. Inventory will get updated next turn.";


    } else {
        error_log("[BGL RUN] $npcNameEsc — Idle production/consumption detected: " . json_encode($parsedResponse) . ". No action taken.");
    }

}


// ─── Last iteration was speak ───────────────────────────────────────────────────────


$npcNameEscBg = $db->escape($GLOBALS['HERIKA_NAME']);
$lastBackgroundAction = $db->fetchOne(
    "SELECT action, fullcall, gamets
     FROM actions_issued
     WHERE actorname='$npcNameEscBg' AND original='backgroundaction'
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
if (strtolower($lastIssuedBgEvent["name"]) == "sandbox" && $lastIssuedBgEvent["event"] == "start" && $npcIsTravelling
|| strtolower($lastIssuedBgEvent["name"]) == "travelto" && $lastIssuedBgEvent["event"] == "end" && $npcIsTravelling) {
    // Last action was MoveTo or TravelTo.
    // Last event was a Sandbox event. This means the NPC reached destination
    // 
    error_log(date("YMd H:i:s") . " [BGL RUN] HINT bypassInnerThoughts: true");
    $bypassInnerThoughts = true;
} else {
    $bypassInnerThoughts = false;
}

// ─── Step 1: Inner-Thought Soliloquy ─────────────────────────────────────────

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
* Consider the character's goals, desires, and motivations.
* Short (2 paragraphs max), concise, and focused on the character's perspective.

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
    ['role' => 'user', 'content' => "<context_history>\nContext History (chronological order)\n$history\n</context_history>", "cache_control" => ["type" => "ephemeral"]],
    ['role' => 'user', 'content' => $userPrompts[$lang], "cache_control" => ["type" => "ephemeral"]],
]);

Logger::debug(__LINE__ . ' ' . (microtime(true) - $startTime));

$recordInnerThoughts = true;

if (!$isSpeakAction) {
    // If last action was not SpeakTo, we generate inner thoughts. If it was SpeakTo, we skip this step to avoid redundant inner thoughts.

    if ($bypassInnerThoughts == false) {
        $connectionHandler = $connector->getConnector($currentConnectorData);
        $innerThoughtBuffer = $connectionHandler->fast_request($step1Prompt, ['MAX_TOKENS' => 2048], 'backgroundlife');
    } else {
        $innerThoughtBuffer = "{$GLOBALS['HERIKA_NAME']}'s inner thought: I've reached destination, I must figure out my next action";
        $recordInnerThoughts = false;
    }

} else {
    //If last action was SpeakTo, we skip this step to avoid redundant inner thoughts.
    $innerThoughtBuffer = "{$GLOBALS['HERIKA_NAME']}'s inner thought: I must figure out my next action";
    $recordInnerThoughts = false;
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
    $step2Content .= "<context_history>\nContext History (chronological order)\n$history\n</context_history>\n\n";
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
- Remain at the current location to work, rest, relax, socialize, or perform ongoing activities.
- This is the default action when the NPC should remain where they are.
- At an inn: rest, relax, socialize with patrons. E.G StayAtPlace:Inn:Relax
- At home: rest, relax, socialize with companions,sleep. e.g StayAtPlace:Breezehome:Sleep
- If gathering information or spreading rumors, remain for at least 24 hours.
- After arriving somewhere, prefer interacting (SpeakTo, BuyItem, SellItem) before choosing StayAtPlace again, unless there is no meaningful interaction available.

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

$step2Content .= <<<PROMPT
BuyItem:<NPC name>:<itemid>:<count>:<total_gold_spent>,<NPC name>:<itemid>:<count>:<total_gold_spent>
- Buy items from another NPC.
- Required after a previously agreed trade so inventories can be updated.

SellItem:<NPC name>:<itemid>:<count>:<total_gold_received>,<NPC name>:<itemid>:<count>:<total_gold_received>,...
- Sell items to another NPC.
- Required after a previously agreed trade so inventories can be updated.

TravelTo:<Place>
- Travel to another location.
- Use only when the destination is different from the current location and travel is necessary.

$returnHomeAction
PROMPT;

if ($npcIsTravelling) {
    $step2Content .= <<<PROMPT
Continue
- Continue executing the previously selected action.
- Prefer this while travelling unless there is a compelling reason to interrupt or change destination.

Note:
{$GLOBALS['HERIKA_NAME']} is already travelling. Do NOT issue another TravelTo action unless the destination must change.

PROMPT;
}


// Hinter

if ((strtolower($lastIssuedBgEvent["name"]) == "sandbox" && $lastIssuedBgEvent["event"] == "start" && $npcIsTravelling)
|| (strtolower($lastIssuedBgEvent["name"]) == "travelto" && $lastIssuedBgEvent["event"] == "end" && $npcIsTravelling)) {
     
    // Last action was MoveTo or TravelTo.
    // Last event was a Sandbox event. This means the NPC reached destination
    // 
    $actionChoiceDesc = "Hint: Character just reached destination. Preferred actions should be:
    * SpeakTo (talk with another nearby character)
    * FindNPC (if wanting to talk to a specific character and is not present)
    * TravelTo (keeps moving if current location is not the final destination)";

} else {
    if ($isSpeakAction) {
        $actionChoiceDesc = "Hint: The character has just completed a conversation. Analyze the dialogue outcome first. If there is an unresolved transaction, continue it by choosing the appropriate action: BuyItem or SellItem.
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

$step2Content .= "Examples ```\n\n"
    . "<action>SpeakTo:Adrianne Avenicci:0001A67C</action>\n"
    . "<reason>I need to speak to Adrianne Avenicci to progress in my current objectives.</reason>\n"
    . "```";

$step2Content .= "Examples ```\n\n"
    . "<action>BuyItem:Adrianne Avenicci:000721E8:1:5,Adrianne Avenicci:000721E6:2:16</action>\n"
    . "<reason>I agreed to buy two items from Adrianne Avenicci</reason>\n"
    . "```";

$step2Content .= "
Rules:
- Only ONE action may be chosen per round.
- The action must be consistent with the context_history, memories, and current location.
- Previous actions are present at the context_history, prevent repetition, use previous actions on history to figure out if main goal is achieved or not, and decide accordingly.
For example:
* To Sell/Buy Item to a trader: SpeakTo:<NPC/Actor name> ->(next iteration) SellItem:.. 
* To Sell/Buy Item to a trader that maybe is not present: MoveTo:<NPC/Actor name> ->(next iteration) SpeakTo:<NPC/Actor name> ->(next iteration) SellItem:.. 
* Buy food at an inn: SpeakTo:<NPC innkeeper> ->(next iteration),BuyItem:<NPC/Actor name> ->(next iteration) StayAtPlace:Inn 
* Relax/Socialize at an inn: SpeakTo:<NPC/Actor name> ->(next iteration) ->(next iteration) StayAtPlace:Inn 
* Relax at home: SpeakTo:<NPC/Actor name> ->(next iteration) StayAtPlace:Home:Sleep
* Generally speaking, try to Speak to an NPC before trading with him/her, unless the NPC is not present. If the NPC is not present, use MoveTo:<NPC name> to reach him/her first.



";

$step2Prompt = [['role' => 'system', 'content' => $step2Content]];
$connectionHandler = $connector->getConnector($currentConnectorData);
$decisionBuffer = $connectionHandler->fast_request($step2Prompt, ['MAX_TOKENS' => 2048], 'backgroundlife');

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
            $recordDiaryEntry = false;
            break;
        case 'MoveTo':
            handleMoveToAction($actionArg, $currentNpcData, $GLOBALS['HERIKA_NAME'], $last_ts, $last_gamets, $momentum, $db);
            unset($parsed['notification']);   // Prevent letter dispatch if MoveTo action is chosen
            unset($parsed['rumor']);   // Prevent rumor dispatch if MoveTo action is chosen
            $recordDiaryEntry = false;
            break;
        case 'SpeakTo':
            $historyWithInnerThought = $history
                . "\n\n<inner_thought>\n{$innerThoughtBuffer}\n</inner_thought>\n";
            handleSpeakToAction($actionArg, $currentNpcData, $GLOBALS['HERIKA_NAME'], $last_ts, $last_gamets, $momentum, $db, $connectionHandler, $dynamicBiography, $historyWithInnerThought, $lastEventParsed['location']);
            unset($parsed['notification']);   // Prevent letter dispatch if SpeakTo action is chosen
            //unset($parsed['rumor']);   // Prevent rumor dispatch if SpeakTo action is chosen
            $recordDiaryEntry = false;
            break;
        case 'BuyItem':
        case 'SellItem':
            // Support semicolon-separated multi-item trades, e.g.:
            // BuyItem:NPC:itemid1:count1:gold1;BuyItem:NPC:itemid2:count2:gold2
            $tradeEntries = explode(';', $parsed['action']);
            foreach ($tradeEntries as $tradeEntry) {
                $tradeEntry = trim($tradeEntry);
                if ($tradeEntry === '')
                    continue;
                [$tradeCmd, $tradeArg] = array_pad(explode(':', $tradeEntry, 2), 2, null);
                $tradeCmd = trim($tradeCmd);
                if ($tradeCmd !== 'BuyItem' && $tradeCmd !== 'SellItem')
                    continue;
                handleTradeItemsAction($tradeCmd, $tradeArg, $currentNpcData, $GLOBALS['HERIKA_NAME'], $last_ts, $last_gamets, $momentum, $db);
            }
            unset($parsed['notification']);
            unset($parsed['rumor']);
            $recordDiaryEntry = false;
            break;
        case 'Continue':
            error_log("[BGL RUN] Chosen action: Continue. No new action will be issued. Reason: {$parsed['reason']}");
            unset($parsed['notification']);
            unset($parsed['rumor']);
            $recordDiaryEntry = false;
            break;
        default:
            error_log("[BGL RUN] ERROR! Chosen action: $actionCmd. No handler implemented for this action. Reason: {$parsed['reason']}");
            unset($parsed['notification']);
            unset($parsed['rumor']);
            $recordDiaryEntry = false;
            triggerNpcUpdate($GLOBALS['HERIKA_NAME'], ($extdata['background_life_last_updated_ec'] ?? 0) + 1);
            break;
    }
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
