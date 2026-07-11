<?php
/**
 * Background Life Processor (v2 — refactored)
 *
 * Generates an NPC "background life" cycle when the player is absent:
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
        error_log("[BGL] Style prompt not found: $promptKey — using fallback.");
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
$currentConnectorData = $connector->getById($GLOBALS['CORE_CONNECTOR_DIRECTOR']);

$profile = new CoreProfile();
$currentProfileData = $profile->getById($currentNpcData['profile_id']);

$connector->setOldGlobals($currentConnectorData);
$npcMaster->setOldGlobalsFromCurrentNpcData($currentNpcData);

$extdata = $npcMaster->getExtendedData($currentNpcData);
$metadata = $npcMaster->getMetadata($currentNpcData);

// ─── Game Timestamps ──────────────────────────────────────────────────────────

$lastGameTsRow = $db->fetchAll('SELECT max(gamets) AS last_gamets FROM eventlog');
$lastTsRow = $db->fetchAll("SELECT max(ts) AS ts FROM eventlog WHERE gamets='{$lastGameTsRow[0]['last_gamets']}'");

$last_gamets = (int) $lastGameTsRow[0]['last_gamets'] + 1;
$last_ts = $lastTsRow[0]['ts'];
$momentum = time();

$gameRequest = ['inputtext', '0', $last_gamets, $npcName];
$npcNameEsc = $db->escape($npcName);

// Last action issued by the NPC (if any) in the last 24 in-game hours

$lastIssuedEvent = $db->fetchOne(
    "SELECT gamets, action FROM actions_issued
     WHERE actorname='$npcNameEsc' 
     ORDER BY gamets DESC, ts ASC"
);

if ($lastIssuedEvent["gamets"] && $lastIssuedEvent["action"] == "TravelTo") {
    $npcIsTravelling = true;
    $npcIsTravellingStarted = $lastIssuedEvent["gamets"];
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
        error_log('[BACKGROUND LIFE] No prior interaction found but background_life_player_unattached is true');
    } else {
        error_log('[BACKGROUND LIFE] No prior interaction found, but background_life_player_unattached is false — skipping.');
        $extdata['background_life_last_updated'] = $last_gamets;
        $npcMaster->updateByArray($npcMaster->setExtendedData($currentNpcData, $extdata));

        return;

    }

}

$lastItGamets = (int) $lastInteractionRow['gamets'];

// ─── Guard: Skip if Last Interaction Is Within the Configured Cooldown ────────────────

$bglTriggerHours = chimGetBackgroundLifeTriggerHours();
$minDeltaForRerun = $bglTriggerHours / GAMETS_TO_HOURS;

if (($last_gamets - $lastItGamets) < $minDeltaForRerun) {
    Logger::info("[BACKGROUND LIFE] $npcNameEsc — last interaction was less than {$bglTriggerHours} hours ago.");
    error_log("[BACKGROUND LIFE] $npcNameEsc — last interaction was less than {$bglTriggerHours} hours ago.");

    $extdata['background_life_last_updated'] = $last_gamets;
    $npcMaster->updateByArray($npcMaster->setExtendedData($currentNpcData, $extdata));

    if ($forceLetter) {
        error_log("[BACKGROUND LIFE] $npcNameEsc — bypassing interaction cooldown via forceletter.");
    } elseif ($forceAction) {
        error_log("[BACKGROUND LIFE] $npcNameEsc — bypassing interaction cooldown via forceaction.");
    } else {
        return;
    }
}


$daysPassed = round(($last_gamets - $lastItGamets) * GAMETS_TO_HOURS / 24, 2);

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
$contextDataHistoric = filterHistoricContextForNarratorVisibility(
    $contextDataHistoric,
    $GLOBALS['HERIKA_NAME'] ?? ''
);

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
    $history .= "\nNote: {$GLOBALS['PLAYER_NAME']} leaves and is absent from this point on.\n</last_dialogue>\n";
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
            'content' => "$hoursAgo hours ago...\n{$row['content']}",
            'type' => ($row['topic'] === 'Sent Letter') ? 'sent_letter' : 'diary_entry',
        ];

        // Update daysPassed to reflect the latest inner chat entry if it's older than the last interaction
        $daysPassed = round(($last_gamets - $row['gamets']) * GAMETS_TO_HOURS / 24, 2);
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
        'content' => "$hoursAgo hours ago...\n{$row['data']}",
        'type' => 'inner_chat',
    ];
    // Update daysPassed to reflect the earliest inner chat entry if it's older than the last interaction
    $daysPassed = round(($last_gamets - $row['gamets']) * GAMETS_TO_HOURS / 24, 2);
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
        'content' => "$hoursAgo hours ago... ".($row["action"]=="TravelTo" ? "{$row['actorname']} starts journey: {$row['fullcall']}" :
             "{$row['actorname']} moves to: {$row['fullcall']}"),
        'type' => 'travel_action',

    ];
    // Update daysPassed to reflect the earliest action entry if it's older than the last interaction
    $daysPassed = round(($last_gamets - $row['gamets']) * GAMETS_TO_HOURS / 24, 2);
}


// ─── Background Events Since Last Iteration ───────────────────────────────────

$bgEvents = [];
$lastEventParsed = [];   // Tracks the most recent valid background event for location context

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
}

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
        'content' => "{$coords[3]}, $hoursAgo hours ago",
        'type' => 'reported_location',
    ];
    $LAST_REPORTED_LOCATION = $coords[3];

    $richLocation = $db->fetchOne("SELECT name,region,hold,is_interior  FROM locations WHERE formid='{$coords["location_formid"]}'");
    // error_log("[BACKGROUND LIFE]  Last reported location: " . json_encode($coords) . " => rich location: " . json_encode($richLocation));
    if ($richLocation && !empty($richLocation['name'])) {
        $LAST_REPORTED_LOCATION = $richLocation['name'];
        if ($richLocation['is_interior']) {
            $LAST_REPORTED_LOCATION .= " (Interior)";
        }
    }
}
/*
if (isset($metadata['last_coords_history'])) {
    $lastSeenLocation = '';
    foreach ($metadata['last_coords_history'] as $historicalCoord) {
        if (empty($historicalCoord[3]) || $historicalCoord[3] === $lastSeenLocation) {
            continue;
        }
        $hoursAgo   = number_format(($last_gamets - $historicalCoord['last_updated']) * GAMETS_TO_HOURS, 2);
        $bgEvents[] = [
            'gamets'  => $historicalCoord['last_updated'],
            'content' => "{$historicalCoord[3]}, $hoursAgo hours ago",
            'type'    => 'reported_location',
        ];
        $lastSeenLocation = $historicalCoord[3];
    }
}
*/


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

        $bgEvents[] = [
            'gamets' => $gamets_lpa_processed,
            'content' => "Nearby actors {$GLOBALS['HERIKA_NAME']} can see (refid,name): " . json_encode($actorList) . ", ($hoursAgo hours ago)",
            'type' => 'nearby_actors',
        ];

    }
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
    error_log("[BACKGROUND LIFE] LAST_REPORTED_LOCATION " . count($rumorRows) . " rumors near <$LAST_REPORTED_LOCATION> since gamets $rumorSinceTs");
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
    $history .= "\n<{$entry['type']}>\n{$content}\n</{$entry['type']}>\n";
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
        ['role' => 'user', 'content' => "<character_sheet>\n{$GLOBALS['HERIKA_NAME']}:\n$dynamicBiography\n</character_sheet>"],
        ['role' => 'user', 'content' => "<context_history>\nContext History\n$history\n</context_history>"],
        [
            'role' => 'user',
            'content' => "Your task is:
Tis character has been idle for the last $idleHours hours. We need to know:
    1) If NPC was on a relaxing scenario (inn..home..), if any item was consumed from inventory (food, drink, potion, etc)
    2) If NPC was on a working (scenario), if any good was produced. (iron ore, leather, etc)  IN THE LAST $idleHours HOURS.. 
       Subsection production at <goals> specifies what is produced and how much per hour. We must check if we have produced any good IN THE LAST $idleHours HOURS.
    3) Optionally, action can be just DoNothing if no consumption or production happened. In this case, reasoning can be empty.   

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
  \"reasoning\": \"optional short explanation\"
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
- reasoning must be short (max 1–2 sentences) and optional.
- Do not add any keys other than 'action' and 'reasoning'.
"
        ]
    ];

    Logger::debug(__LINE__ . ' ' . (microtime(true) - $startTime));

    $connectionHandler = $connector->getConnector($currentConnectorData);
    $preResponse = $connectionHandler->fast_request($preStep1Prompt, ['MAX_TOKENS' => 2048], 'backgroundlife');

    $parsedResponse = __jpd_decode_lazy($preResponse);

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
        foreach ($action as $singleAction) {
            error_log("[BACKGROUND LIFE] $npcNameEsc — Idle production/consumption detected: $singleAction. Reasoning: $reasoning");

            $skyrimCmd = new SkyrimCommandBuilder();
            $sourceRefHexString = strtolower(convertSignedToUnsignedHex(hexdec($currentNpcData['refid'])));
            // Parse action string
            list($actionType, $itemId, $count) = explode(':', $singleAction);
            $count = (int) $count;
            if ($actionType === 'Consume') {
                $json = $skyrimCmd->ObjectReference->RemoveItem($sourceRefHexString, "0x$itemId", $count, true);
                $skyrimCmd->send(cmd: $json);
            } elseif ($actionType === 'Produced') {
                $json = $skyrimCmd->ObjectReference->AddItem($sourceRefHexString, "0x$itemId", $count, true);
                $skyrimCmd->send(cmd: $json);
            }
            $actionText[] = $singleAction;
        }

        $actionTextFinal = implode(', ', $actionText);

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
            'data' => "The Narrator: $npcName produced/consumed items while idle: $actionTextFinal. Reasoning: $reasoning",
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
                'data' => "$npcName produced/consumed items while idle: $actionTextFinal. Reasoning: $reasoning",
            ]
        );

        sleep(1);   // Allow time for the command to be processed

        // Refetch the NPC data to update the dynamic biography with the new inventory state
        $dynamicBiography = buildDynamicBiography($GLOBALS, true, true, true);

        if (isset($extdata['middle_term_memory'])) {
            $middleTermMemory = end($extdata['middle_term_memory']);
            $dynamicBiography .= "\n\n<middle_term_memory>\nPast events\n{$middleTermMemory}\n</middle_term_memory>";
        }
        $history .= "\nThe Narrator: $npcName produced/consumed items while idle: $actionTextFinal. Reasoning: $reasoning";


    } else {
        error_log("[BACKGROUND LIFE] $npcNameEsc — Idle production/consumption detected: none");
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


$lang = (($npcMetadata['CORE_LANG'] ?? '') === 'es' || ($profileMetadata['CORE_LANG'] ?? '') === 'es')
    ? 'es'
    : 'en';

// ─── Step 1: Inner-Thought Soliloquy ─────────────────────────────────────────

$systemPrompts = [
    'en' => [['role' => 'system', 'content' => 'You are a writing assistant. Examine this text containing events that occurred in the fictional universe of Skyrim (The Elder Scrolls).']],
];

$noteAboutPlayer = $extdata['background_life_player_unattached']
    ? "Character should stick to its own goals. A miner will mine, a trader will trade,...."
    : "Important note: {$GLOBALS['PLAYER_NAME']} and {$GLOBALS['HERIKA_NAME']} are NOT in the same place after the <context_history> events.";


$userPrompts = [
    'en' => <<<PROMPT_EN
The main character in this logbook is {$GLOBALS['HERIKA_NAME']}.
Read the context history (context_history) and the recent memories (middle_term_memory),
paying attention to notable events and the names of relevant characters.

Based on all this information, generate an inner-thought soliloquy for {$GLOBALS['HERIKA_NAME']}.
Take into account the <speech_style> section for the writing style, and particularly
<inner_thought_guidance> if present.

This soliloquy should reflect what the character might have done over the last {$daysPassed} day(s), 
and after last inner thoughts presented in the <context_history>:

* Details of any set tasks.
* Intimate thoughts.
* Evolution of the character's state of mind based on latest inner thoughts (if any) and events.
* Short (2 paragraphs max), concise, and focused on the character's perspective.

Always respect the character's last known location. If the character is in a specific place,
generated content should occur in that area or its surroundings. The character may express the
intention to travel elsewhere, but such travel should only be described as a future plan,
not an immediate action.

$noteAboutPlayer

Write in English as if you were {$GLOBALS['HERIKA_NAME']}, in a soliloquy, speaking to yourself
in first person.

PROMPT_EN,
];

$step1Prompt = array_merge($systemPrompts[$lang], [
    ['role' => 'user', 'content' => "<character_sheet>\n{$GLOBALS['HERIKA_NAME']}:\n$dynamicBiography\n</character_sheet>"],
    ['role' => 'user', 'content' => "<context_history>\nContext History\n$history\n</context_history>"],
    ['role' => 'user', 'content' => $userPrompts[$lang]],
]);

Logger::debug(__LINE__ . ' ' . (microtime(true) - $startTime));

if (!$isSpeakAction) {
    // If last action was not SpeakTo, we generate inner thoughts. If it was SpeakTo, we skip this step to avoid redundant inner thoughts.

    $connectionHandler = $connector->getConnector($currentConnectorData);
    $innerThoughtBuffer = $connectionHandler->fast_request($step1Prompt, ['MAX_TOKENS' => 2048], 'backgroundlife');

} else {
    $innerThoughtBuffer = "({$GLOBALS['HERIKA_NAME']} thinks about the last conversation )";
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
    $step2Content .= "<context_history>\nContext History\n$history\n</context_history>\n\n";
}

$step2Content .= "<text>\n$innerThoughtBuffer\n</text>\n\n";
$step2Content .= $innerThoughtStyle . "\n\n";


$step2Content .= "Possible actions :\n"
    . "StayAtPlace:Place — Remains in current location. If gathering info or spreading rumors, stay ≥24 hours.\n"
    . "FindNPC:<NPC name> — Search for an NPC whose exact location is unknown (replace <NPC> with target NPC name). Use this before MoveTo or SpeakTo when the character does not know where the target is. Requires a clear reason.\n"
    . "MoveTo:<NPC name> — Move to another NPC whose location is already known (replace <NPC> with target NPC name). Requires a clear reason.\n";

// Avoid too consecutive SpeakTo actions, as they may be redundant. If the last action was SpeakTo, we skip this option to prevent repetitive interactions.

if (!$isSpeakAction)
    $step2Content.="SpeakTo:<NPC name>:<npc refid> — Engage in conversation with another NPC (replace <NPC> with target NPC name). \n";

$step2Content.="BuyItem:<NPC name>:itemid:count:gold_spent — Buy item from another NPC (replace <NPC> with target NPC name). (if character {$GLOBALS['HERIKA_NAME']} interacts with a trader and has agreed a transaction before, this step is *needed* to update inventories)\n"
    . "SellItem:<NPC name>:itemid:count:gold_earned — Sell item to another NPC (replace <NPC> with target NPC name). (if character {$GLOBALS['HERIKA_NAME']} interacts with a trader and has agreed a transaction before, this step is *needed* to update inventories)\n"
    . "ReturnHome       — Returns to base location to meet {$GLOBALS['PLAYER_NAME']}. Use when all goals are done.\n"
    . "TravelTo:Place   — Travel to a specific location/city (replace <Place> with target location/city name). Requires a clear reason.\n"
    . "Continue   — Just keep last issued action (For example, if the character is already on a journey, continue the current TravelTo action).\n";
$actionChoiceDesc = '<action> chosen action (e.g., StayAtPlace:Place, TravelTo:Place, ReturnHome, FindNPC:<NPC>, MoveTo:<NPC>, SpeakTo:<NPC>:..., BuyItem:<NPC>:..., SellItem:<NPC>:...). Choose only one action per turn. Single line.';
    
if ($npcIsTravelling) {
    $step2Content .= "Note: {$GLOBALS['HERIKA_NAME']} is currently traveling. If the character is already on a journey, avoid choosing another TravelTo action unless there is a compelling reason to change the destination.\n";
}
$step2Content .= "\nElement Definitions:\n```\n"
    . "$actionChoiceDesc\n"
    . "```\n\n"
    . "- Your answer must use XML format, containing exactly 2 elements.\n"
    . "- NEVER include commentary inside or outside the element tags or ANY content beyond the defined format.\n\n"
    . "Use only this exact Response Format:\n```\n"
    . "<action> ... </action>\n"
    . "<reason> ... </reason>\n"
    . "```";

$step2Content .= "Example: ```\n\n"
    . "<action>FindNPC:Lydia</action>\n"
    . "<reason>I need to find Lydia to speak to her</reason>\n"
    ."```";

$step2Content .= "Examples ```\n\n"
    . "<action>SpeakTo:Lydia:000A2C94</action>\n"
    . "<reason>I need to speak to Lydia to progress in my current objectives.</reason>\n"
    ."```";

$step2Content .= "
Rules:
- Only ONE action may be chosen per round.
- The action must be consistent with the context_history, memories, and current location.
- If no meaningful action is appropriate, she may choose to wait (no action).
- Previous actions are present at the context_history, prevent repetition, use previous actions on history to figure out if main goal is achieved or not, and decide accordingly.
For example:
* To Sell/Buy Item to a trader: SpeakTo:<NPC> ->(next iteration) SellItem:.. 
* To Sell/Buy Item to a trader that maye is not present: MoveTo:<NPC> ->(next iteration) SpeakTo:<NPC> ->(next iteration) SellItem:.. 
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
$currentNpcData = $npcMaster->setExtendedData($currentNpcData, $extdata);
$npcMaster->updateByArray($currentNpcData);

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
    error_log("[BGL] Chosen action: $actionCmd, argument: $actionArg, reason: {$parsed['reason']}");
    $GLOBALS["LAST_REASON"] = $parsed['reason'];
    switch ($actionCmd) {
        case 'TravelTo':
            handleTravelToAction($actionArg, $currentNpcData, $GLOBALS['HERIKA_NAME'], $last_ts, $last_gamets, $momentum, $lastEventParsed, $db);
            unset($parsed['rumor']);   // Prevent rumor dispatch if MoveTo action is chosen
            break;
        case 'StayAtPlace':
            handleStayAtPlaceAction($actionArg, $currentNpcData, $GLOBALS['HERIKA_NAME'], $last_ts, $last_gamets, $momentum, $db);
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

if ($innerThoughtBuffer) {
    $db->insert('eventlog', [
        'ts' => $last_ts,
        'gamets' => $last_gamets,
        'type' => 'innerchat',
        'data' => "{$GLOBALS['HERIKA_NAME']}'s inner thoughts: " . $innerThoughtBuffer . ' )',
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

die();
