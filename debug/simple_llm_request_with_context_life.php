<?php

$startTime = microtime(true);

define("MAXIMUM_SENTENCE_SIZE", 125);
define("MINIMUM_SENTENCE_SIZE", 15);

$GLOBALS["SCRIPTLINE_EXPRESSION"] = "";
$GLOBALS["SCRIPTLINE_LISTENER"] = "";
$GLOBALS["SCRIPTLINE_ANIMATION"] = "";

/** Conversion factor: in-game time units (gamets) → real hours */
define('GAMETS_TO_HOURS', 0.0000024);

error_reporting(E_ALL);
ini_set('display_errors', 1);

$enginePath = __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR;

$enginePath = dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR;
$GLOBALS["ENGINE_PATH"] = $enginePath;


// ─── Includes ─────────────────────────────────────────────────────────────────

require_once $enginePath . 'lib/runtime_bootstrap.php';
chimRuntimeBootstrap($enginePath, [
    'load_general_settings' => true,
    'load_player_name' => true,
    'load_narrator' => true,
]);

if (!chimIsGlobalLlmConnectorEnabled('CORE_CONNECTOR_BGL')) {
    echo "Background Life is disabled globally." . PHP_EOL;
    exit(0);
}

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


// Load PLAYER_NAME from core_player table
try {
    require_once $enginePath . "lib/core/player.class.php";
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



require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "lazy_xml.php";
require_once $enginePath . "debug" . DIRECTORY_SEPARATOR . "background_action_handler.php";

/**
 * Load a background life style prompt from database
 * 
 * @param string $promptKey The prompt key to load ('background_life_letter' or 'background_life_innerthought')
 * @param array $replacements Associative array of placeholder => value pairs
 * @return string The prompt content with replacements
 */
function loadBGLStylePrompt($promptKey, $replacements = [])
{
    global $db;

    try {
        //TODO commented while working on default prompts
        $promptData = false;//$db->fetchOne("SELECT custom_prompt, default_prompt FROM prompts WHERE prompt_key = '$promptKey'");

        if (!$promptData) {
            error_log("[BGL RUN] Style prompt not found: $promptKey - using fallback");
            // Fallback defaults
            if ($promptKey === 'background_life_letter') {
                return "Write a letter to {$GLOBALS["PLAYER_NAME"]} from {$GLOBALS["HERIKA_NAME"]} based on the content of <text>. Use same language as <text>. Take into account the <speech_style> section for the writing style, and particularly <letter_guidance> if present. Do not include any meta-commentary or asside, only the content of the letter.";
            } else {
                return "Read the <text> content, which represents a mental note or inner monologue of the character within the Skyrim universe.\nBased on the content of the <text>, propose one of the defined actions that would make sense for the development of the story.";
            }
        }

        // Use custom_prompt if set, otherwise use default_prompt
        $prompt = (!empty($promptData['custom_prompt'])) ? $promptData['custom_prompt'] : $promptData['default_prompt'];

        // Replace placeholders
        foreach ($replacements as $key => $value) {
            $prompt = str_replace($key, $value, $prompt);
        }

        return $prompt;
    } catch (Exception $e) {
        error_log("[BGL RUN] Exception loading style prompt $promptKey: " . $e->getMessage());
        // Return fallback
        if ($promptKey === 'background_life_letter') {
            return "Write it as a letter to {$GLOBALS["PLAYER_NAME"]} from {$GLOBALS["HERIKA_NAME"]}. Use same language as <text>.";
        } else {
            return "Read the following text, which represents a mental note or inner monologue of a character within the Skyrim universe.\nBased on the content of the <text>, propose one of the defined actions that would make sense for the development of the story:";
        }
    }
}

$npcMaster = new NpcMaster();

$connector = new LLMConnector();
$currentConnectorData = $connector->getById($GLOBALS["CORE_CONNECTOR_BGL"]);
$currentNpcData = $npcMaster->getByName($argv[1]);

$profile = new CoreProfile();
$currentProfileData = $profile->getById($currentNpcData["profile_id"]);
$connector->setOldGlobals($currentConnectorData);
$npcMaster->setOldGlobalsFromCurrentNpcData($currentNpcData);

$COMMAND_PROMPT = '';

$dbNpcName = $db->escape($argv[1]);
$limit = 100;
$momentum = time();

$GLOBALS["HERIKA_NAME"] = $argv[1];

$res = $db->fetchAll("select max(gamets) as last_gamets from eventlog");
$res2 = $db->fetchAll("select max(ts) as ts from eventlog where gamets='{$res[0]["last_gamets"]}'");

$last_gamets = $res[0]["last_gamets"] + 1;
$last_ts = $res2[0]["ts"];

$gameRequest = ["inputtext", "0", $last_gamets, $argv[1]];

$request = $argv[1];

$dynamicBiography = buildDynamicBiography($GLOBALS, true, true);
$npcMaster = new NpcMaster();
$currentNpcData = $npcMaster->getByName($argv[1]);
$dynamicBiography = $npcMaster->appendBackgroundLifeGoals($dynamicBiography, $currentNpcData);
$extended_data = $npcMaster->getExtendedData($currentNpcData);
$metadata = $npcMaster->getMetadata($currentNpcData);

if (isset($extended_data["middle_term_memory"])) {
    $middle_term_memory = end($extended_data["middle_term_memory"]);
    $dynamicBiography .= "\n\n<middle_term_memory>\nPast events\n{$middle_term_memory}\n</middle_term_memory>";

}

// Things that happened after last iteration
$npcNameEsc = $db->escape($GLOBALS["HERIKA_NAME"]);
$query = "SELECT max(gamets) as  gamets from speech where
    (speaker='$npcNameEsc' or listener='$npcNameEsc' or companions like '%|$npcNameEsc|%')
    ";

error_log($query);
$lastIt = $db->fetchOne($query);

if (!$lastIt["gamets"]) {

    
    $extdata["background_life_last_updated"] = $last_gamets;
    $npcMaster->updateExtendedKeysByName($GLOBALS["HERIKA_NAME"], $extdata);

    error_log("[BGL RUN] NO LAST ITERATION, SKIPPING $argv[1] - {$GLOBALS["HERIKA_NAME"]} last_gamets: $last_gamets");
    return;
}
$lastItNumber = $lastIt["gamets"] ?? 0;


$bglTriggerHours = chimGetBackgroundLifeTriggerHours();
if (($last_gamets - $lastItNumber) < ($bglTriggerHours / GAMETS_TO_HOURS)) {
    Logger::info("[BGL RUN] $npcNameEsc Last interaction less than {$bglTriggerHours} hours ago");
    $extdata = $npcMaster->getExtendedData($currentNpcData);
    $extdata["background_life_last_updated"] = $last_gamets;
    $currentNpcData = $npcMaster->setExtendedData($currentNpcData, $extdata);
    $npcMaster->updateByArray($currentNpcData);
    error_log("[BGL RUN] $npcNameEsc Last interaction less than {$bglTriggerHours} hours ago");

    if (isset($argv[2]) && $argv[2] == "forceletter")
        error_log("[BGL RUN] $npcNameEsc Bypassing interaction cooldown via forceletter");
    
    else if (isset($argv[3]) && $argv[3] == "forceaction")
        error_log("[BGL RUN] $npcNameEsc Bypassing interaction cooldown via forceaction");
    else {
        error_log("[BGL RUN] $npcNameEsc Interaction cooldown active...suspended: ".implode("|",$argv));
        return;
    }
        
}

$task = "";
$history = "\n<last_dialogue>\n";
$sqlfilter = " and gamets<$lastItNumber and type<>'prechat' and type<>'itemfound' and type<>'infoaction' and type<>'npcspellcast' and data not like '%inner thoughts%' ";
$contextDataHistoric = DataLastDataExpandedFor("{$GLOBALS["HERIKA_NAME"]}", 50 * -1, $sqlfilter);
$contextDataHistoric = filterHistoricContextForNarratorVisibility(
    $contextDataHistoric,
    $GLOBALS["HERIKA_NAME"] ?? ""
);


foreach ($contextDataHistoric as $element) {
    if ($element["role"] != "assistant") {
        $history .= trim("{$element["content"]}") . PHP_EOL . PHP_EOL;
    } else {
        $history .= trim("{$GLOBALS["HERIKA_NAME"]}: {$element["content"]}") . PHP_EOL . PHP_EOL;
    }

}
$history .= "\nNote: {$GLOBALS["PLAYER_NAME"]} leaves and is absent from this point on.\n</last_dialogue>\n";

$query = "SELECT location,gamets from speech where
    (speaker='$npcNameEsc' or listener='$npcNameEsc' or companions like '%|$npcNameEsc|%')
    order by gamets desc,ts desc
    ";

$lastLoc = $db->fetchOne($query);
error_log($query);

// Diary entries after last iteration

$cn = $db->escape($GLOBALS["HERIKA_NAME"]);
$query2 = "SELECT content,gamets,topic FROM diarylog
 where people='$cn' and gamets>{$lastIt["gamets"]} and (topic='Sent Letter' or topic='Journal Note')
 order by gamets desc ,ts desc limit 16 offset 0";
error_log($query2);
$diaryEntry = [];
$diaryEntries = $db->fetchAll($query2);
foreach (array_reverse($diaryEntries) as $dentry) {
    //$history .= "\n<diary_entry>\n{$dentry["content"]}\n</diary_entry>\n";
    $diaryEntry[] = [
        "gamets" => $dentry["gamets"],
        "content" => number_format(($last_gamets - $dentry["gamets"]) * 0.0000024, 2) . " hours ago...\n" . $dentry["content"],
        "type" => $dentry["topic"] == "Sent Letter" ? "sent_letter" : "diary_entry"
    ];

}

$bgEvents = [];
error_log("[BGL RUN] Last interaction {$lastIt["gamets"]}");
if (isset($lastIt["gamets"])) {
    $query2 = "SELECT gamets,data FROM eventlog where type='backgroundaction' and gamets>{$lastIt["gamets"]} order by gamets asc ,ts asc";
    error_log($query2);
    $backgroundEvents = $db->fetchAll($query2);
    foreach ($backgroundEvents as $event) {
        $eventParsed = json_decode($event["data"], true);
        if (!$eventParsed["source"] || $eventParsed["source"] != "AIAgent.esp") // Only AIAgent.esp for now.
        {
            continue;
        }

        if (!$eventParsed["description"]) {
            continue;
        } else if ($eventParsed["description"] == "unknown") {
            continue;
        } else if ($eventParsed["actor"] != $GLOBALS["HERIKA_NAME"]) {
            continue;
        }

        $hours = ($event["gamets"] - $lastIt["gamets"]) * 0.0000024;
        $bgEvents[] = ["gamets" => $event["gamets"], "content" => "{$eventParsed["description"]}", "type" => "event"];

    }

}


$bgEvents[] = ["gamets" => $lastLoc["gamets"], "content" => "{$lastLoc["location"]}", "type" => "last_known_location"];

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

if (isset($metadata['last_coords_history'])) {
    $localLast = "";
    foreach ($metadata['last_coords_history'] as $historicalCoord) {
        if (!empty($historicalCoord[3])) {
            if ($localLast == $historicalCoord[3])
                continue;
            $bgEvents[] = [
                "gamets" => $historicalCoord["last_updated"],
                "content" => "{$historicalCoord[3]}, " .
                    number_format(($last_gamets - $historicalCoord["last_updated"]) * 0.0000024, 2) .
                    " hours ago",
                "type" => "reported_location"
            ];
            $localLast = $historicalCoord[3];
        }
    }
}


$rumors = [];
error_log("Last interaction {$lastIt["gamets"]}, {$lastLoc["location"]}");
$currentHoldEsc = $db->escape($LAST_REPORTED_LOCATION);
if ($currentHoldEsc) {
    $query2 = "SELECT gamets,content FROM rumors WHERE hold like '%{$currentHoldEsc}%' and gamets>" . ($gameRequest[2] - ((24 * 7) / 0.0000024));
    error_log($query2);
    $rumors = $db->fetchAll($query2);
    foreach ($rumors as $event) {
        $bgEvents[] = ["gamets" => $event["gamets"], "content" => "{$event["content"]}", "type" => "rumor"];
        // error_log("[BACKGROUNDLIFE] Adding rumor {$event["content"]}");
    }

}

print_r($bgEvents);

// Must mix bgEvents array and diaryEntry array, and order them using key gamets asc
$combinedEvents = array_merge($bgEvents, $diaryEntry);
usort($combinedEvents, function ($a, $b) {
    return $a['gamets'] <=> $b['gamets'];
});

print_r($combinedEvents);
$previous = 0;
foreach ($combinedEvents as $dentry) {
    if ($dentry["type"] == "event" && $previous) {
        $hours = ($dentry["gamets"] - $previous) * 0.0000024;
        $hoursAgo = ($last_gamets - $dentry["gamets"]) * 0.0000024;
        $dentry["content"] = "* $hours hours after last entry: {$dentry["content"]}, $hoursAgo hours ago";
    }
    $previous = $dentry["gamets"];
    $history .= "\n<{$dentry["type"]}>\n{$dentry["content"]}\n</{$dentry["type"]}>\n";

}

echo "===========================================================" . PHP_EOL;

// WIP
$daysPassed = round(($last_gamets - $lastIt["gamets"]) * 0.0000024 / 24, 2);
if (sizeof($combinedEvents) == 0) {

    $history .= "Note: After this events, $daysPassed has passed";
}

// Inception
$lastMinuteNotes="";
$extdata = $npcMaster->getExtendedData($currentNpcData);
if (isset($extdata['bgl_inception']) && !empty($extdata['bgl_inception'])) {
    $lastMinuteNotes .= "\nImportant:A thought crosses {$GLOBALS['HERIKA_NAME']}'s mind: He/She should {$extdata['bgl_inception']}\n";
    $npcMaster = new NpcMaster();
    $npcData = $npcMaster->getByName($GLOBALS['HERIKA_NAME']);
    $npcMaster->updateExtendedKeysByName($GLOBALS['HERIKA_NAME'], ['bgl_inception' => ""]);
    error_log("[BGL RUN] HINT inception: {$extdata['bgl_inception']}");
}

//$head[] = ['role' => 'system', 'content' => "You're an AI writer. Examine this character's logbook from a story in the Skyrim universe."];
$head["es"][] = ['role' => 'system', 'content' => "Eres un asistente de escritor. Examina este texto con hechos ocurridos en el universo ficticio de Skyrim (The Elder Scrolls)"];
$head["en"][] = ['role' => 'system', 'content' => "You are a writing assistant. Examine this text containing events that occurred in the fictional universe of Skyrim (The Elder Scrolls)."];

$userprompt["es"] = "El personaje principal en este cuaderno de bitácora es {$GLOBALS["HERIKA_NAME"]}.
Lee el historial de contexto (context_history), las memorias recientes (middle_term_memory) , prestando atención a los eventos notables y a los nombres de personajes relevantes.


Basandote en toda esta informacion, genera un soliloquio de {$GLOBALS["HERIKA_NAME"]},
Ten en cuenta la seccion <speech_style> para el estilo de redacción.

Este soliloquio deberia de contener lo que el personaje deberia de haber hecho en estos ultimos $daysPassed dia(s)
* Que actividades ha de desarrollado.
* Que posibles sucesos/encuentros podrian haber sucedido.
* Pensamientos intimos.

Nota importante: El personaje '{$GLOBALS["PLAYER_NAME"]}' y  {$GLOBALS["HERIKA_NAME"]} estan separados despues los hechos de <context_history>.
Escribe en español en un par de parrafos un monologo, como si fueses {$GLOBALS["HERIKA_NAME"]} en primera persona hablandose a si misma/mismo.

IMPORTANTE: Mantén este pensamiento interno breve y conciso - máximo 2-3 párrafos cortos.
";

$userprompt["en"] = "
The main character in this logbook is {$GLOBALS["HERIKA_NAME"]}.
Read the context history (context_history) and the recent memories (middle_term_memory), paying attention to notable events and the names of relevant characters.

Based on all this information, generate an inner thought - soliloquy for {$GLOBALS["HERIKA_NAME"]}.
Take into account the <speech_style> section for the writing style, and particularly <inner_thought_guidance> if present.

This soliloquy should reflect what the character might have done over the last $daysPassed day(s):

 * Details of set tasks if given 
 * What activities they have engaged in. Detailed list.
 * What possible events or encounters might have occurred.
 * Intimate thoughts.
 * Give special attention to <background_life_goals> when present; <goals> contains the character's general motivations.

$lastMinuteNotes

Always respect the character's last known location. If the character is currently in a specific place, generated content should occur in that same area or its surroundings.
The character may express the intention to travel elsewhere, but such travel should only be described as a future plan, not an immediate action.

Important note: Character {$GLOBALS["PLAYER_NAME"]} and {$GLOBALS["HERIKA_NAME"]} ARE NOT  IN THE SAME PLACE after <context_history> events.
Write in english as if you were {$GLOBALS["HERIKA_NAME"]}, soliloquy, speaking to yourself in first person.
";

//IMPORTANT: Keep this inner thought short and concise - aim for 2-3 brief paragraphs maximum.
//";

$metadata = json_decode($currentNpcData["metadata"], true);
$metadata_p = json_decode($currentProfileData["metadata"], true);

if ($metadata["CORE_LANG"] == "es" || $metadata_p["CORE_LANG"] == "es") {
    $LANG = "es";
} else {
    $LANG = "en";
}

$prompt[] = ['role' => 'user', 'content' => "<character_sheet>\n{$GLOBALS["HERIKA_NAME"]}:\n$dynamicBiography\n</character_sheet>"];
$prompt[] = ['role' => 'user', 'content' => "<context_history>\nContext History\n$history\n$task\n</context_history>"];

$prompt[] = ['role' => 'user', 'content' => $userprompt[$LANG]];

$contextData = array_merge($head[$LANG], $prompt);

Logger::debug(__LINE__ . " " . (microtime(true) - $startTime));

$connectionHandler = $connector->getConnector($currentConnectorData);
$buffer = $connectionHandler->fast_request($contextData, ["MAX_TOKENS" => 2048], "backgroundlife");
Logger::debug(__LINE__ . " " . (microtime(true) - $startTime));

print_r($buffer . PHP_EOL);

if (isset($argv[2]) && ($argv[2] == "dryrun"  )) {
    die();
}

// Check if letters are enabled for this NPC
$extdata = $npcMaster->getExtendedData($currentNpcData);
$lettersEnabled = isset($extdata['background_life_letters']) && $extdata['background_life_letters'] === true;

// Step-2: Build prompt with hardcoded structure and style from database
$fullMode = isset($argv[2]) && $argv[2] == "full";

// Load customizable style prompts
$innerThoughtStyle = loadBGLStylePrompt('background_life_innerthought');
$letterStyle = loadBGLStylePrompt('background_life_letter', [
    '{HERIKA_NAME}' => $GLOBALS["HERIKA_NAME"],
    '{PLAYER_NAME}' => $GLOBALS["PLAYER_NAME"]
]);

// Build hardcoded prompt structure
$promptContent = "You are responsible for deciding an action, creating a rumor, and writing a letter based on the character's inner thoughts and the provided context.\n";
$promptContent .= "Character's name is {$GLOBALS["HERIKA_NAME"]}.\n";
$promptContent .= "$dynamicBiography\n\n";

// Add context history for full mode
if ($fullMode) {
    $promptContent .= "<context_history>\nContext History\n$history\n$task\n</context_history>\n\n";
}

$promptContent .= "<text>\n$buffer\n</text>\n\n";

$promptContent .= $innerThoughtStyle . "\n\n";

// Hardcoded action definitions
if ($fullMode) {
    $promptContent .= "Possible actions (check character's goals section):\n";
    $promptContent .= "
StayAtPlace:<Place>:<intent>
The character remains in their current location, performing activities locally (intent). Take into account how much time character has been at 
this location and its current task

- intent can be: Work, Rest, Relax, Socialize, Sleep, Study, Guard.
- Remain at the current location to work, rest, relax, socialize, or perform ongoing activities.
- This is the default action when the NPC should remain where they are.
- At an inn: rest, relax, socialize with patrons. E.G StayAtPlace:Inn:Relax
- At home: rest, relax, socialize with companions,sleep. e.g StayAtPlace:Breezehome:Sleep

TravelTo:<Place>
- Travel to another location.
- Use only when the destination is different from the current location and travel is necessary.

ReturnHome 
Character returns to its base location, probably to meet {$GLOBALS["PLAYER_NAME"]} . 
Use when no further action is needed or all goals have been accomplished.

";
  
} else {
    $promptContent .= "Possible actions:\n";
    $promptContent .= "
StayAtPlace:<Place>:<intent>
The character remains in their current location, performing activities locally (intent). Take into account how much time character has been at 
this location and its current task

- intent can be: Work, Rest, Relax, Socialize, Sleep, Study, Guard.
- Remain at the current location to work, rest, relax, socialize, or perform ongoing activities.
- This is the default action when the NPC should remain where they are.
- At an inn: rest, relax, socialize with patrons. E.G StayAtPlace:Inn:Relax
- At home: rest, relax, socialize with companions,sleep. e.g StayAtPlace:Breezehome:Sleep

SpreadRumor - Character activities generate rumors, also, character can explictly create a rumor. 
E.G. If character's goal or activity is to enforce local trade, create a rumor about local trading being enhanced.

";
}

// XML structure based on letter setting
$numElements = $lettersEnabled ? 3 : 2;

$promptContent .= "\nElement Definitions:\n```\n";

// Field descriptions
if ($fullMode) {
    $promptContent .= "<action>: chosen action (e.g., StayAtPlace,TravelTo:<Place>,ReturnHome)\n";
} else {
    $promptContent .= "<action>: chosen action (StayAtPlace or SpreadRumor)\n";
}
$promptContent .= "<rumor>: rumor spread or created. rumor should be located and related to current character's location ($LAST_REPORTED_LOCATION), e.g if character is at Dawnstar, rumor should be Dawnstar related.\n";
if ($lettersEnabled) {
    $promptContent .= "<notification>: $letterStyle\n";
}
$promptContent .= "```\n\n- Your answer must use XML format, containing exactly $numElements elements\n".
    " - NEVER include any commentary inside or outside of the element tags or ANY other content beyond the following defined format in your response.\n\n".
    "Use only this exact Response Format:\n```\n";
$promptContent .= "<action> ... </action>\n";
$promptContent .= "<rumor> ... </rumor>\n";
if ($lettersEnabled) {
    $promptContent .= "<notification> ... </notification>\n";
}
$promptContent .= "```";

$prompt = [['role' => 'system', 'content' => $promptContent]];

$buffer2 = $connectionHandler->fast_request($prompt, ["MAX_TOKENS" => 2048], "backgroundlife");

print_r($buffer2);
//$parsed = parse_xml_fragment($buffer2);

$extdata = $npcMaster->getExtendedData($currentNpcData);
$extdata["background_life_last_updated"] = $last_gamets;
$currentNpcData = $npcMaster->setExtendedData($currentNpcData, $extdata);
$npcMaster->updateByArray($currentNpcData);

$parsed = [];
$parsed["action"] = manual_get_tag_content($buffer2, "action");
$parsed["notification"] = manual_get_tag_content($buffer2, "notification");
$parsed["rumor"] = manual_get_tag_content($buffer2, "rumor");

print_r($parsed);

if (is_array($parsed)) {

    $refHexString = (convertSignedToUnsignedHex(hexdec($currentNpcData["refid"])));

    if (isset($parsed["action"])) {
        $cmds = explode(":", $parsed["action"]);
        if ($cmds[0] == "TravelTo") {
            handleTravelToAction($cmds[1], $currentNpcData, $GLOBALS["HERIKA_NAME"], $last_ts, $last_gamets, $momentum, $eventParsed, $db);
            unset($parsed['rumor']);   // Prevent rumor dispatch if MoveTo action is chosen

        } else if ($cmds[0] == "StayAtPlace") {
            handleStayAtPlaceAction($LAST_REPORTED_LOCATION, $currentNpcData, $GLOBALS["HERIKA_NAME"], $last_ts, $last_gamets, $momentum, $db);
            unset($parsed['rumor']);   // Prevent rumor dispatch if MoveTo action is chosen

        } else if ($cmds[0] == "ReturnHome") {
            handleReturnHome($cmds[1], $currentNpcData, $GLOBALS["HERIKA_NAME"], $last_ts, $last_gamets, $momentum, $db);
            unset($parsed['rumor']);   // Prevent rumor dispatch if MoveTo action is chosen
        }
    }

    if ($parsed["notification"] && $lettersEnabled) {
        $dateStringSK = convert_gamets2skyrim_long_date(DataLastKnownGameTS());
        $fullTitle = "A letter from {$GLOBALS["HERIKA_NAME"]} ($dateStringSK)";

        // This is going to create a picture with the letter.
        createLetter($fullTitle, $parsed["notification"]);

        // Will make plugin to download letter image to data folder, and will be stored using title's hash as name
        $db->insert(
            'responselog',
            [
                'localts' => time(),
                'sent' => 0,
                'actor' => "rolemaster",
                'text' => "",
                'action' => "rolecommand|generateLetter@$fullTitle",
                'tag' => '',
            ]
        );
        // Will make plugin to generate formid for letter, and will send vanilla courier

        $db->insert(
            'responselog',
            [
                'localts' => time(),
                'sent' => 0,
                'actor' => "rolemaster",
                'text' => "",
                'action' => "rolecommand|BackgroundCmd@$refHexString@SendNote/" . $fullTitle,
                'tag' => '',
            ]
        );

        // Log to diary/eventlog when letters are sent
        $db->insert(
            'eventlog',
            [
                'ts' => $last_ts,
                'gamets' => $last_gamets + 1,
                'type' => "innerchat",
                'data'   => "The Narrator:{$GLOBALS["HERIKA_NAME"]} sent this letter to {$GLOBALS["PLAYER_NAME"]} " . "\n<letter_content>\n{$parsed["notification"]}\n</letter_content>",
                'sess' => $momentum,
                'localts' => time(),
                'people' => $GLOBALS["HERIKA_NAME"],
                'location' => $eventParsed["location"] ?? null,
                'party' => "",
            ]
        );
        // Insert bgl_history log entry
        $db->insert(
            'bgl_history',
            [
                'npc' => $GLOBALS["HERIKA_NAME"],
                'ts' => $last_ts,
                'gamets' => $last_gamets+1,
                'localts' => time(),
                'data' => "{$GLOBALS["HERIKA_NAME"]} sends a letter to {$GLOBALS["PLAYER_NAME"]}",
            ]
        );
        
        $db->insert(
            'diarylog',
            [
                'ts' => $last_ts,
                'gamets' => $last_gamets + 5,
                'topic' => "Sent Letter",
                'content' => $parsed["notification"],
                'tags' => "backgroundlife",
                'people' => $GLOBALS["HERIKA_NAME"],
                'location' => $LAST_REPORTED_LOCATION ?? null,
                'sess' => $momentum,
                'localts' => time(),
            ]
        );

        $narratorInstantLetter = true; //TODO make global / configurable
        if($narratorInstantLetter) {
            $taskId = uniqid();

            $instructionText = "hey narrator, {$GLOBALS["HERIKA_NAME"]} has sent a letter to {$GLOBALS["PLAYER_NAME"]}, announce it, and you MUST include the content of <letter_content> verbatim in your response. (listener MUST be {$GLOBALS["PLAYER_NAME"]})";

            // Format action string
            $roleMasterAction = make_replacements("rolecommand|Instruction@The Narrator@{$instructionText}@$taskId");

            // Store event as pending delayed event instead of inserting immediately
            // This will be posted by middleterm processor after speech has been idle for 15+ seconds
            $delayedEvent = array(
                'localts' => time(),
                'sent' => 0,
                'actor' => "rolemaster",
                'text' => '',
                'action' => $roleMasterAction,
                'tag' => ""
            );

            // Store in npcMaster extended_data
            $extendedData['pending_delayed_event'] = $delayedEvent;
            $currentNpcData = $npcMaster->setExtendedData($currentNpcData, $extendedData);
            $npcMaster->updateByArray($currentNpcData);

            error_log("[DELAYED-EVENT] Letter announcement event queued for {$GLOBALS["HERIKA_NAME"]}, will post after speech idle for 15s");

            $GLOBALS["db"]->insert(
                'responselog',
                array(
                    'localts' => time(),
                    'sent' => 0,
                    'actor' => "rolemaster",
                    'text' => '',
                    'action' => "rolecommand|DebugNotification@Letter from {$GLOBALS["HERIKA_NAME"]}",
                    'tag' => ""
                )
            );
        }

        // Write into books table, books.php entrypoint will pickup and create new book entry when readed by player
        $GLOBALS["db"]->insert(
            'books',
            array(
                'ts' => 0,
                'gamets' => 0,
                'content' => $parsed["notification"],
                'sess' => 'generated',
                'localts' => time(),
                'title' => $fullTitle
            )
        );
    }

    if ($parsed["rumor"]) {
        $detParm = "Location: $LAST_REPORTED_LOCATION, {$parsed["rumor"]} (Contextual information about reasons of this rumor: {$parsed["notification"]})";
        shell_exec("php $enginePath/debug/simple_llm_request_with_context_rumors_custom.php " . escapeshellarg($detParm));
    }
}

$db->insert(
    'eventlog',
    [
        'ts' => $last_ts,
        'gamets' => $last_gamets,
        'type' => "innerchat",
        'data' => "{$GLOBALS["HERIKA_NAME"]}'s inner thoughts: " . $buffer . " )",
        'sess' => $momentum,
        'localts' => time(),
        'people' => $GLOBALS["HERIKA_NAME"],
        'location' => $eventParsed["location"] ?? null,
        'party' => "",
    ]
);

$db->insert(
    'diarylog',
    [
        'ts' => $last_ts,
        'gamets' => $last_gamets,
        'topic' => "Journal Note",
        'content' => convert_gamets2skyrim_long_date($last_gamets) . "\n" . trim($buffer),
        'tags' => "Auto-diary, backgroundlife",
        'people' => $GLOBALS["HERIKA_NAME"],
        'location' => $eventParsed["location"] ?? null,
        'sess' => $momentum,
        'localts' => time(),
    ]
);

logMemory($GLOBALS["HERIKA_NAME"], $GLOBALS["HERIKA_NAME"], trim($buffer), $momentum, $last_gamets, 'backgroundlife_diary', $last_ts);
// Mark NPC as background_life_enabled
$npcManager = new NpcMaster();
$npcData = $npcManager->getByName($GLOBALS["HERIKA_NAME"]);
$extended = json_decode($npcData["extended_data"], true);
$extended["background_life_enabled"] = true;
$npcData["extended_data"] = json_encode($extended);
$npcManager->updateByArray($npcData);

die();
