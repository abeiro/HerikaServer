<?php
/**
 * Background Life Processor (v2 — refactored)
 *
 * Generates an NPC "background life" cycle when the player is absent:
 *   1. Inner-thought soliloquy (Step 1 LLM call)
 *   2. Action / rumor / optional letter decision (Step 2 LLM call)
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
        . " propose one of the defined actions that would make sense for the development of the story. If <text> contains an Action proposed, you should consider it in your response.";
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

// ─── Guard: Require at Least One Prior Interaction ───────────────────────────

$lastInteractionRow = $db->fetchOne(
    "SELECT max(gamets) AS gamets FROM speech
     WHERE speaker='$npcNameEsc' OR listener='$npcNameEsc' OR companions LIKE '%|$npcNameEsc|%'"
);

if (empty($lastInteractionRow['gamets'])) {
    error_log('[BACKGROUND LIFE] No prior interaction found — updating timestamp and skipping.');
    $extdata['background_life_last_updated'] = $last_gamets;
    $npcMaster->updateByArray($npcMaster->setExtendedData($currentNpcData, $extdata));
    return;
}

$lastItGamets = (int) $lastInteractionRow['gamets'];

// ─── Guard: Skip if Last Interaction Was < 3 In-Game Days Ago ────────────────

$minDeltaForRerun = (24 * 3) / GAMETS_TO_HOURS;   // 3 in-game days in gamets

if (($last_gamets - $lastItGamets) < $minDeltaForRerun) {
    Logger::info("[BACKGROUND LIFE] $npcNameEsc — last iteration was less than 3 days ago.");
    error_log("[BACKGROUND LIFE] $npcNameEsc — last iteration was less than 3 days ago.");

    $extdata['background_life_last_updated'] = $last_gamets;
    $npcMaster->updateByArray($npcMaster->setExtendedData($currentNpcData, $extdata));

    if ($forceLetter) {
        error_log("[BACKGROUND LIFE] $npcNameEsc — bypassing 3-day guard via forceletter.");
    } elseif ($forceAction) {
        error_log("[BACKGROUND LIFE] $npcNameEsc — bypassing 3-day guard via forceaction.");
    } else {
        return;
    }
}

$daysPassed = round(($last_gamets - $lastItGamets) * GAMETS_TO_HOURS / 24, 2);

// ─── Dynamic Biography ────────────────────────────────────────────────────────

$dynamicBiography = buildDynamicBiography($GLOBALS, true, true);

if (isset($extdata['middle_term_memory'])) {
    $middleTermMemory = end($extdata['middle_term_memory']);
    $dynamicBiography .= "\n\n<middle_term_memory>\nPast events\n{$middleTermMemory}\n</middle_term_memory>";
}

// ─── Dialogue History ─────────────────────────────────────────────────────────

$sqlFilter = " AND gamets < $lastItGamets"
    . " AND type NOT IN ('prechat','itemfound','infoaction','npcspellcast','innerchat')"
    . " AND data NOT LIKE '%inner thoughts%'";

$contextDataHistoric = DataLastDataExpandedFor($GLOBALS['HERIKA_NAME'], -50, $sqlFilter);
$contextDataHistoric = filterHistoricContextForNarratorVisibility(
    $contextDataHistoric,
    $GLOBALS['HERIKA_NAME'] ?? ''
);

$history = "\n<last_dialogue>
This represents last dialogue where player ({$GLOBALS['PLAYER_NAME']}) was present. Can be more dialogues with other NPCs from this point.\n";
foreach ($contextDataHistoric as $entry) {
    $line = trim($entry['content']);
    $history .= ($entry['role'] === 'assistant')
        ? "{$GLOBALS['HERIKA_NAME']}: $line\n\n"
        : "$line\n\n";
}
$history .= "\nNote: {$GLOBALS['PLAYER_NAME']} leaves and is absent from this point on.\n</last_dialogue>\n";

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
    $diaryEntries[] = [
        'gamets' => $row['gamets'],
        'content' => "$hoursAgo hours ago...\n{$row['content']}",
        'type' => ($row['topic'] === 'Sent Letter') ? 'sent_letter' : 'diary_entry',
    ];
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
}

// Append last known speech location
$bgEvents[] = [
    'gamets' => $lastLocRow['gamets'],
    'content' => $lastLocRow['location'],
    'type' => 'last_known_location',
];

// Append current and historical coordinate data
$LAST_REPORTED_LOCATION = '';

if (isset($metadata['last_coords']) && !empty($metadata['last_coords'][3])) {
    $coords = $metadata['last_coords'];
    $hoursAgo = number_format(($last_gamets - $coords['last_updated']) * GAMETS_TO_HOURS, 2);
    $bgEvents[] = [
        'gamets' => $coords['last_updated'],
        'content' => "{$coords[3]}, $hoursAgo hours ago",
        'type' => 'last_reported_location',
    ];
    $LAST_REPORTED_LOCATION = $coords[3];
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
    
    foreach ($metadata['low_process_actors'] as $gamets_lpa_processed=>$actorList) {
        if ($gamets_lpa_processed <= $lastItGamets) {
            continue;
        }
        $hoursAgo   = number_format(($last_gamets - $gamets_lpa_processed) * GAMETS_TO_HOURS, 2);
        // actorList in the form of name
        $bgEvents[] = [
            'gamets'  => $gamets_lpa_processed,
            'content' => "Nearby actors {$GLOBALS['HERIKA_NAME']} can see (refid,name): " . json_encode($actorList) . ", ($hoursAgo hours ago)",
            'type'    => 'nearby_actors',
        ];
        
    }
}

// ─── Rumors Near Current Location ────────────────────────────────────────────

if ($LAST_REPORTED_LOCATION) {
    $locationEsc = $db->escape($LAST_REPORTED_LOCATION);
    $rumorSinceTs = $gameRequest[2] - ((24 * 7) / GAMETS_TO_HOURS);   // Last 7 in-game days
    $rumorRows = $db->fetchAll(
        "SELECT gamets, content FROM rumors
         WHERE hold LIKE '%{$locationEsc}%' AND gamets > $rumorSinceTs"
    );
    foreach ($rumorRows as $rumor) {
        $bgEvents[] = [
            'gamets' => $rumor['gamets'],
            'content' => $rumor['content'],
            'type' => 'rumor',
        ];
    }
}

// ─── Merge & Sort Events; Append to History ───────────────────────────────────

$combinedEvents = array_merge($bgEvents, $diaryEntries, $innerChats);
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

// ─── Language Detection ───────────────────────────────────────────────────────

$npcMetadata = json_decode($currentNpcData['metadata'], true) ?? [];
$profileMetadata = json_decode($currentProfileData['metadata'], true) ?? [];

$lang = (($npcMetadata['CORE_LANG'] ?? '') === 'es' || ($profileMetadata['CORE_LANG'] ?? '') === 'es')
    ? 'es'
    : 'en';

// ─── Step 1: Inner-Thought Soliloquy ─────────────────────────────────────────

$systemPrompts = [
    'es' => [['role' => 'system', 'content' => 'Eres un asistente de escritor. Examina este texto con hechos ocurridos en el universo ficticio de Skyrim (The Elder Scrolls).']],
    'en' => [['role' => 'system', 'content' => 'You are a writing assistant. Examine this text containing events that occurred in the fictional universe of Skyrim (The Elder Scrolls).']],
];

$userPrompts = [
    'es' => <<<PROMPT_ES
El personaje principal en este cuaderno de bitácora es {$GLOBALS['HERIKA_NAME']}.
Lee el historial de contexto (context_history) y las memorias recientes (middle_term_memory),
prestando atención a los eventos notables y a los nombres de personajes relevantes.

Basándote en toda esta información, genera un soliloquio de {$GLOBALS['HERIKA_NAME']}.
Ten en cuenta la sección <speech_style> para el estilo de redacción.

Este soliloquio debería contener lo que el personaje podría haber hecho en estos últimos {$daysPassed} día(s):
* Qué actividades ha desarrollado.
* Qué posibles sucesos/encuentros podrían haber sucedido.
* Pensamientos íntimos.

Nota importante: El personaje '{$GLOBALS['PLAYER_NAME']}' y {$GLOBALS['HERIKA_NAME']} están separados
después de los hechos de <context_history>.
Escribe en español, en un par de párrafos, un monólogo como si fueses {$GLOBALS['HERIKA_NAME']}
en primera persona hablándose a sí misma/mismo.

IMPORTANTE: Mantén este pensamiento interno breve y conciso — máximo 2-3 párrafos cortos.
PROMPT_ES,

    'en' => <<<PROMPT_EN
The main character in this logbook is {$GLOBALS['HERIKA_NAME']}.
Read the context history (context_history) and the recent memories (middle_term_memory),
paying attention to notable events and the names of relevant characters.

Based on all this information, generate an inner-thought soliloquy for {$GLOBALS['HERIKA_NAME']}.
Take into account the <speech_style> section for the writing style, and particularly
<inner_thought_guidance> if present.

This soliloquy should reflect what the character might have done over the last {$daysPassed} day(s):
* Details of any set tasks.
* Intimate thoughts.
* Short (2 paragraphs max), concise, and focused on the character's perspective.

Always respect the character's last known location. If the character is in a specific place,
generated content should occur in that area or its surroundings. The character may express the
intention to travel elsewhere, but such travel should only be described as a future plan,
not an immediate action.

Important note: {$GLOBALS['PLAYER_NAME']} and {$GLOBALS['HERIKA_NAME']} are NOT in the same place
after the <context_history> events.

Write in English as if you were {$GLOBALS['HERIKA_NAME']}, in a soliloquy, speaking to yourself
in first person.

---

### Decision-Making Extension

At the end of the soliloquy, {$GLOBALS['HERIKA_NAME']} must decide her next step.

She/He  may choose ONE of the following actions:
* TravelTo(<location_name>). Travel to a specific location/city. Use when the character wants to move to a new area.
* FindNPC(<npc_name>). Locate an NPC whose exact location is unknown. Use this before MoveTo or SpeakTo when the character does not know where the target is. 
* MoveTo(<npc_name>). Move to another NPC whose location is already known. 
* SpeakTo(<npc_name>,<refid>). Engage in conversation with another NPC. (should be used before any BuyItems or SellItems action, to reflect the need to interact and agree on a transaction with the trader NPC) (specify the NPC's refid if known, otherwise use 0)
* BuyItems(<npc_name>,<itemid>,<count>,<gold_spent>) Buy items from another NPC (if character interacts with a trader and has agreed a transaction before, this step is *needed* to update inventories)
* SellItems(<npc_name>,<itemid>,<count>,<gold_received>) Sells items to another NPC (if character interacts with a trader and has agreed a transaction before, this step is *needed* to update inventories)
* ReturnHome. Returns to base location to meet {$GLOBALS['PLAYER_NAME']}. Use when all goals are done.
* SpreadRumor. Generate or spread a rumor related to the character's current location (e.g., if goal is to boost local trade, rumour about it).
* StayAtPlace. Remains in current location. If gathering info or spreading rumors, stay ≥24 hours.   

Rules:
- Only ONE action may be chosen per round.
- The action must be consistent with the context_history, memories, and current location.
- If no meaningful action is appropriate, she may choose to wait (no action).
- Previous actions are present at the context_history, prevent repetition, use previous actions on history to figure out if main goal is achieved or not, and decide accordingly.

If an action is chosen:
- The reasoning must be reflected naturally inside the soliloquy.
- The action itself must be explicitly stated at the end in the required format.

---

### Output Format

1. Soliloquy (in first person, as {$GLOBALS['HERIKA_NAME']})

2. Action block:

<Action>
Type: TravelTo | FindNPC | MoveTo | SpeakTo | BuyItems | SellItems | ReturnHome | SpreadRumor | StayAtPlace
Target: <location name | NPC name,refid| NPC name | item id | ... | None>
Reason: <brief justification>
</Action>

---

### Turn Constraint

If {$GLOBALS['HERIKA_NAME']} initiates an action, she MUST NOT describe the outcome of that action.
The result will occur in the next round.

She may express intention, expectations, or doubts — but never the resolution of the action.
PROMPT_EN,
];

$step1Prompt = array_merge($systemPrompts[$lang], [
    ['role' => 'user', 'content' => "<character_sheet>\n{$GLOBALS['HERIKA_NAME']}:\n$dynamicBiography\n</character_sheet>"],
    ['role' => 'user', 'content' => "<context_history>\nContext History\n$history\n</context_history>"],
    ['role' => 'user', 'content' => $userPrompts[$lang]],
]);

Logger::debug(__LINE__ . ' ' . (microtime(true) - $startTime));

$connectionHandler = $connector->getConnector($currentConnectorData);
$innerThoughtBuffer = $connectionHandler->fast_request($step1Prompt, ['MAX_TOKENS' => 2048], 'backgroundlife');

Logger::debug(__LINE__ . ' ' . (microtime(true) - $startTime));
echo $innerThoughtBuffer . PHP_EOL;

// ─── Dry-Run Guard ────────────────────────────────────────────────────────────

if ($isDryRun && !$forceAction) {
    die();
}

// ─── Step 2: Action / Rumor / Letter Decision ────────────────────────────────

$lettersEnabled = isset($extdata['background_life_letters']) && $extdata['background_life_letters'] === true;
$innerThoughtStyle = loadBGLStylePrompt('background_life_innerthought');
$letterStyle = loadBGLStylePrompt('background_life_letter', [
    '{HERIKA_NAME}' => $GLOBALS['HERIKA_NAME'],
    '{PLAYER_NAME}' => $GLOBALS['PLAYER_NAME'],
]);

$step2Content = "You are responsible for deciding an action, creating a rumor, and writing a letter"
    . " based on the character's inner thoughts and the provided context.\n"
    . "Character's name is {$GLOBALS['HERIKA_NAME']}.\n"
    . "$dynamicBiography\n\n";

if ($isFullMode) {
    $step2Content .= "<context_history>\nContext History\n$history\n</context_history>\n\n";
}

$step2Content .= "<text>\n$innerThoughtBuffer\n</text>\n\n";
$step2Content .= $innerThoughtStyle . "\n\n";


$step2Content .= "Possible actions:\n"
    . "StayAtPlace — Remains in current location. If gathering info or spreading rumors, stay ≥24 hours.\n"
    . "FindNPC:NPC — Search for an NPC whose exact location is unknown (replace <NPC> with target NPC name). Use this before MoveTo or SpeakTo when the character does not know where the target is. Requires a clear reason.\n"
    . "MoveTo:NPC — Move to another NPC whose location is already known (replace <NPC> with target NPC name). Requires a clear reason.\n"
    . "SpeakTo:NPC:refid — Engage in conversation with another NPC (replace <NPC> with target NPC name). Requires a clear reason.\n"
    . "BuyItems:NPC:itemid:count:gold_spent — Buy items from another NPC (replace <NPC> with target NPC name). (if character {$GLOBALS['HERIKA_NAME']} interacts with a trader and has agreed a transaction before, this step is *needed* to update inventories)\n"
    . "SellItems:NPC:itemid:count:gold_earned — Sell items to another NPC (replace <NPC> with target NPC name). (if character {$GLOBALS['HERIKA_NAME']} interacts with a trader and has agreed a transaction before, this step is *needed* to update inventories)\n"
    . "ReturnHome       — Returns to base location to meet {$GLOBALS['PLAYER_NAME']}. Use when all goals are done.\n"
    . "TravelTo:Place   — Travel to a specific location/city (replace <Place> with target location/city name). Requires a clear reason.\n"
    . "SpreadRumor — Character activities generate rumors (e.g., if goal is to boost local trade, rumour about it).\n";
$actionChoiceDesc = '<action>: chosen action (e.g., StayAtPlace, TravelTo:<Place>, ReturnHome, FindNPC:<NPC>, MoveTo:<NPC>, SpeakTo:<NPC>, BuyItems:<NPC>, SellItems:<NPC>, SpreadRumor). Choose only one action per turn. Single line.';


$numElements = $lettersEnabled ? 3 : 2;
$step2Content .= "\nElement Definitions:\n```\n"
    . "$actionChoiceDesc\n"
    . "<rumor>: rumor created or spread, related to character's current location ($LAST_REPORTED_LOCATION).\n";

if ($lettersEnabled) {
    $step2Content .= "<notification>: $letterStyle\n";
}

$step2Content .= "```\n\n"
    . "- Your answer must use XML format, containing exactly $numElements elements.\n"
    . "- NEVER include commentary inside or outside the element tags or ANY content beyond the defined format.\n\n"
    . "Use only this exact Response Format:\n```\n"
    . "<action> ... </action>\n"
    . "<rumor> ... </rumor>\n";

if ($lettersEnabled) {
    $step2Content .= "<notification> ... </notification>\n";
}
$step2Content .= "```";

$step2Prompt = [['role' => 'system', 'content' => $step2Content]];
$decisionBuffer = $connectionHandler->fast_request($step2Prompt, ['MAX_TOKENS' => 2048], 'backgroundlife');

echo $decisionBuffer . PHP_EOL;

// ─── Update Background-Life Timestamp ────────────────────────────────────────

$extdata['background_life_last_updated'] = $last_gamets;
$currentNpcData = $npcMaster->setExtendedData($currentNpcData, $extdata);
$npcMaster->updateByArray($currentNpcData);

// ─── Parse LLM Decision Response ─────────────────────────────────────────────

$parsed = [
    'action' => manual_get_tag_content($decisionBuffer, 'action'),
    'notification' => manual_get_tag_content($decisionBuffer, 'notification'),
    'rumor' => manual_get_tag_content($decisionBuffer, 'rumor'),
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
$recordDiaryEntry=true;
if (!empty($parsed['action'])) {
    [$actionCmd, $actionArg] = array_pad(explode(':', $parsed['action'], 2), 2, null);
    error_log("[BGL] Chosen action: $actionCmd, argument: $actionArg");
    switch ($actionCmd) {
        case 'TravelTo':
            handleTravelToAction($actionArg, $currentNpcData, $GLOBALS['HERIKA_NAME'], $last_ts, $last_gamets, $momentum, $lastEventParsed, $db);
            break;
        case 'StayAtPlace':
            handleStayAtPlaceAction($LAST_REPORTED_LOCATION, $currentNpcData, $GLOBALS['HERIKA_NAME'], $last_ts, $last_gamets, $momentum, $db);
            break;
        case 'ReturnHome':
            handleReturnHome($actionArg, $currentNpcData, $GLOBALS['HERIKA_NAME'], $last_ts, $last_gamets, $momentum, $db);
            unset($parsed['notification']);   // Prevent letter dispatch if ReturnHome action is chosen
            break;
        case 'FindNPC':
            handleFindNPCAction($actionArg, $currentNpcData, $GLOBALS['HERIKA_NAME'], $last_ts, $last_gamets, $momentum, $db, $LAST_REPORTED_LOCATION);
            unset($parsed['notification']);   // Prevent letter dispatch if FindNPC action is chosen
            unset($parsed['rumor']);   // Prevent rumor dispatch if FindNPC action is chosen
            $recordDiaryEntry=false;
            break;
        case 'MoveTo':
            handleMoveToAction($actionArg, $currentNpcData, $GLOBALS['HERIKA_NAME'], $last_ts, $last_gamets, $momentum, $db);
            unset($parsed['notification']);   // Prevent letter dispatch if MoveTo action is chosen
            unset($parsed['rumor']);   // Prevent rumor dispatch if MoveTo action is chosen
            $recordDiaryEntry=false;
            break;
        case 'SpeakTo':
            $historyWithInnerThought = $history
                . "\n\n<inner_thought>\n{$innerThoughtBuffer}\n</inner_thought>\n";
            handleSpeakToAction($actionArg, $currentNpcData, $GLOBALS['HERIKA_NAME'], $last_ts, $last_gamets, $momentum, $db, $connectionHandler, $dynamicBiography, $historyWithInnerThought,$lastEventParsed['location']);
            unset($parsed['notification']);   // Prevent letter dispatch if SpeakTo action is chosen
            //unset($parsed['rumor']);   // Prevent rumor dispatch if SpeakTo action is chosen
            $recordDiaryEntry=false;
            break;
        case 'BuyItems':
        case 'SellItems':
            // Support semicolon-separated multi-item trades, e.g.:
            // BuyItems:NPC:itemid1:count1:gold1;BuyItems:NPC:itemid2:count2:gold2
            $tradeEntries = explode(';', $parsed['action']);
            foreach ($tradeEntries as $tradeEntry) {
                $tradeEntry = trim($tradeEntry);
                if ($tradeEntry === '') continue;
                [$tradeCmd, $tradeArg] = array_pad(explode(':', $tradeEntry, 2), 2, null);
                $tradeCmd = trim($tradeCmd);
                if ($tradeCmd !== 'BuyItems' && $tradeCmd !== 'SellItems') continue;
                handleTradeItemsAction($tradeCmd, $tradeArg, $currentNpcData, $GLOBALS['HERIKA_NAME'], $last_ts, $last_gamets, $momentum, $db);
            }
            unset($parsed['notification']);
            //unset($parsed['rumor']);
            $recordDiaryEntry=false;
            break;
    }
}

// ─── Dispatch: Letter / Notification ─────────────────────────────────────────

if (!empty($parsed['notification']) && $lettersEnabled) {
    $dateStringSK = convert_gamets2skyrim_long_date(DataLastKnownGameTS());
    $fullTitle = "A letter from {$GLOBALS['HERIKA_NAME']} ($dateStringSK)";

    // Generate a picture with the letter content
    createLetter($fullTitle, $parsed['notification']);

    // Instruct plugin to download and store the letter image
    $db->insert('responselog', [
        'localts' => time(),
        'sent' => 0,
        'actor' => 'rolemaster',
        'text' => '',
        'action' => "rolecommand|generateLetter@$fullTitle",
        'tag' => '',
    ]);

    // Instruct plugin to send via in-game courier
    $db->insert('responselog', [
        'localts' => time(),
        'sent' => 0,
        'actor' => 'rolemaster',
        'text' => '',
        'action' => "rolecommand|BackgroundCmd@$refHexString@SendNote/$fullTitle",
        'tag' => '',
    ]);

    // Log letter dispatch in eventlog
    $db->insert('eventlog', [
        'ts' => $last_ts,
        'gamets' => $last_gamets + 1,
        'type' => 'innerchat',
        'data' => "The Narrator:{$GLOBALS['HERIKA_NAME']} sent this letter to {$GLOBALS['PLAYER_NAME']}"
            . "\n<letter_content>\n{$parsed['notification']}\n</letter_content>",
        'sess' => $momentum,
        'localts' => time(),
        'people' => $GLOBALS['HERIKA_NAME'],
        'location' => $lastEventParsed['location'] ?? null,
        'party' => '',
    ]);

    // Log letter content in diarylog
    $db->insert('diarylog', [
        'ts' => $last_ts,
        'gamets' => $last_gamets + 5,
        'topic' => 'Sent Letter',
        'content' => $parsed['notification'],
        'tags' => 'backgroundlife',
        'people' => $GLOBALS['HERIKA_NAME'],
        'location' => $LAST_REPORTED_LOCATION ?? null,
        'sess' => $momentum,
        'localts' => time(),
    ]);

    // Immediately notify the narrator to announce the letter
    $narratorInstantLetter = true;   // TODO: make globally configurable

    if ($narratorInstantLetter) {
        $taskId = uniqid();
        $instructionText = "hey narrator, {$GLOBALS['HERIKA_NAME']} has sent a letter to {$GLOBALS['PLAYER_NAME']},"
            . " announce it, and you MUST include the content of <letter_content> verbatim in your response."
            . " (listener MUST be {$GLOBALS['PLAYER_NAME']})";

        $roleMasterAction = make_replacements("rolecommand|Instruction@The Narrator@{$instructionText}@$taskId");

        // Queue a delayed event — posted by middleterm processor after ≥15 s of speech idle
        $extdata['pending_delayed_event'] = [
            'localts' => time(),
            'sent' => 0,
            'actor' => 'rolemaster',
            'text' => '',
            'action' => $roleMasterAction,
            'tag' => '',
        ];
        $currentNpcData = $npcMaster->setExtendedData($currentNpcData, $extdata);
        $npcMaster->updateByArray($currentNpcData);

        error_log("[DELAYED-EVENT] Letter announcement queued for {$GLOBALS['HERIKA_NAME']} — posts after 15 s speech idle.");

        $db->insert('responselog', [
            'localts' => time(),
            'sent' => 0,
            'actor' => 'rolemaster',
            'text' => '',
            'action' => "rolecommand|DebugNotification@Letter from {$GLOBALS['HERIKA_NAME']}",
            'tag' => '',
        ]);
    }

    // Store the letter in the books table for in-game reading
    $db->insert('books', [
        'ts' => 0,
        'gamets' => 0,
        'content' => $parsed['notification'],
        'sess' => 'generated',
        'localts' => time(),
        'title' => $fullTitle,
    ]);
}

// ─── Dispatch: Rumor ──────────────────────────────────────────────────────────

if (!empty($parsed['rumor'])) {
    $rumorContext = "Location: $LAST_REPORTED_LOCATION, {$parsed['rumor']}"
        . " (Contextual information about reasons of this rumor: {$parsed['notification']})";
    shell_exec("php {$enginePath}debug/simple_llm_request_with_context_rumors_custom.php " . escapeshellarg($rumorContext));
}

// ─── Persist Inner Thought to Event & Diary Logs ──────────────────────────────

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

// ─── Mark NPC as Background-Life Enabled ─────────────────────────────────────

$currentNpcData = $npcMaster->getByName($npcName);
$extdata = $npcMaster->getExtendedData($currentNpcData);
$extdata['background_life_enabled'] = true;
$currentNpcData = $npcMaster->setExtendedData($currentNpcData, $extdata);
$npcMaster->updateByArray($currentNpcData);

die();
