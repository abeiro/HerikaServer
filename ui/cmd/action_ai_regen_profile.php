<?php

$jsonDataInput = $_GET;

$startTime = microtime(true);

define("MAXIMUM_SENTENCE_SIZE", 125);
define("MINIMUM_SENTENCE_SIZE", 15);

$GLOBALS["SCRIPTLINE_EXPRESSION"] = "";
$GLOBALS["SCRIPTLINE_LISTENER"]   = "";
$GLOBALS["SCRIPTLINE_ANIMATION"]  = "";

error_reporting(E_ALL);
ini_set('display_errors', 1);
$file       = __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "data" . DIRECTORY_SEPARATOR . 'CurrentModel_.json';

$enginePath = __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR;
require_once $enginePath . "conf" . DIRECTORY_SEPARATOR . "conf.php";
require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "model_dynmodel.php";
require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "{$GLOBALS["DBDRIVER"]}.class.php";
require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "chat_helper_functions.php";
require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "data_functions.php";
require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "logger.php";
require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "utils_game_timestamp.php";
require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "rolemaster_helpers.php";
require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "dynamic_update_util.php";
$GLOBALS["ENGINE_PATH"] = $enginePath;

$db = new sql();

require_once $enginePath . "lib/core/npc_master.class.php";
require_once $enginePath . "lib/core/api_badge.class.php";
require_once $enginePath . "lib/core/core_profiles.class.php";
require_once $enginePath . "lib/core/llm_connector.class.php";
require_once $enginePath . "lib/core/tts_connector.class.php";

require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "lazy_xml.php";

$name = $jsonDataInput["name"];

$npcMaster = new NpcMaster();

$connector            = new LLMConnector();
$currentConnectorData = $connector->getById($GLOBALS["CORE_CONNECTOR_PROFILES"]);
$currentNpcData       = $npcMaster->getByName($name);

$profile            = new CoreProfile();
$currentProfileData = $profile->getById($currentNpcData["profile_id"]);
$connector->setOldGlobals($currentConnectorData);
$npcMaster->setOldGlobalsFromCurrentNpcData($currentNpcData);

$CLEAN_CONTEXT_FOCUS_CHAT = false;

$COMMAND_PROMPT = '';

$dbNpcName = $db->escape($name);

$momentum = time();

$GLOBALS["HERIKA_NAME"] = $name;

$res  = $db->fetchAll("select max(gamets) as last_gamets from eventlog");
$res2 = $db->fetchAll("select max(ts) as ts from eventlog where gamets='{$res[0]["last_gamets"]}'");

$last_gamets = $res[0]["last_gamets"] + 1;
$last_ts     = $res2[0]["ts"];

$gameRequest = ["inputtext", "0", $last_gamets, $name];

$dynamicBiography = buildDynamicBiography($GLOBALS);
$npcMaster        = new NpcMaster();
$currentNpcData   = $npcMaster->getByName($name);
$extended_data    = $npcMaster->getExtendedData($currentNpcData);

if (isset($extended_data["middle_term_memory"])) {
    $middle_term_memory = end($extended_data["middle_term_memory"]);
    $dynamicBiography .= "\n\n<middle_term_memory>\nPast events\n{$middle_term_memory}\n</middle_term_memory>";

}

// Things that happened after last iteration
$npcNameEsc = $db->escape($GLOBALS["HERIKA_NAME"]);
$query      = "SELECT max(gamets) as  gamets from speech where
    (speaker='$npcNameEsc' or listener='$npcNameEsc' or companions like '%|$npcNameEsc|%')
    ";

error_log($query);
$lastIt       = $db->fetchOne($query);
$lastItNumber = $lastIt["gamets"] ?? 0;

$task                = "";
$history             = "\n<last_dialogue>\n";
$sqlfilter           = " and gamets<$lastItNumber and type<>'prechat' and type<>'itemfound' and type<>'infoaction' and type<>'npcspellcast' and data not like '%inner thoughts%' ";
$contextDataHistoric = DataLastDataExpandedFor("{$GLOBALS["HERIKA_NAME"]}", 100 * -1, $sqlfilter);

if (! empty($GLOBALS["HIDE_NARRATOR_DIALOGUE"]) && $GLOBALS["HERIKA_NAME"] !== "The Narrator") {
    $isContextNarratorLine = function (string $content): bool {
        if (strpos($content, 'The Narrator:') !== 0) {
            return false;
        }

        if (preg_match('/^The Narrator:\s*\(/', $content)) {
            return true;
        }
        // parenthetical
        if (strpos($content, 'The Narrator: background dialogue:') === 0) {
            return true;
        }

        if (strpos($content, 'The Narrator: action moved to new location:') === 0) {
            return true;
        }

        if (strpos($content, 'The Narrator: SCENARIO CHANGE') === 0) {
            return true;
        }

        if (preg_match('/^The Narrator:\s*about\s+\d+\s+hours\s+later/i', $content)) {
            return true;
        }

        return false;
    };
    $contextDataHistoric = array_values(array_filter($contextDataHistoric, function ($entry) use ($isContextNarratorLine) {
        if (! is_array($entry)) {
            return true;
        }

        $content = isset($entry['content']) ? (string) $entry['content'] : '';
        if (strpos($content, '(Talking to The Narrator)') !== false) {
            return false;
        }

        if (strpos($content, 'The Narrator:') === 0) {
            return $isContextNarratorLine($content);
        }
        return true;
    }));
}
foreach ($contextDataHistoric as $element) {
    if ($element["role"] != "assistant") {
        $history .= trim("{$element["content"]}") . PHP_EOL . PHP_EOL;
    } else {
        $history .= trim("{$GLOBALS["HERIKA_NAME"]}: {$element["content"]}") . PHP_EOL . PHP_EOL;
    }

}
$history .= "\n</last_dialogue>\n";

$query = "SELECT location,gamets from speech where
    (speaker='$npcNameEsc' or listener='$npcNameEsc' or companions like '%|$npcNameEsc|%')
    order by gamets desc,ts desc
    ";

$lastLoc = $db->fetchOne($query);
error_log($query);

// Diary entries after last iteration

$cn     = $db->escape($GLOBALS["HERIKA_NAME"]);
$query2 = "SELECT content,gamets,topic FROM diarylog
 where people='$cn' and gamets>$lastItNumber and (topic='Sent Letter' or topic='Journal Note')
 order by gamets desc ,ts desc limit 5 offset 0";
error_log($query2);
$diaryEntry   = [];
$diaryEntries = $db->fetchAll($query2);
foreach (array_reverse($diaryEntries) as $dentry) {
    //$history .= "\n<diary_entry>\n{$dentry["content"]}\n</diary_entry>\n";
    $diaryEntry[] = ["gamets" => $dentry["gamets"], "content" => $dentry["content"], "type" => $dentry["topic"] == "Sent Letter" ? "sent_letter" : "diary_entry"];

}

$bgEvents = [];
error_log("Last interaction {$lastIt["gamets"]}");
if (isset($lastIt["gamets"])) {
    $query2 = "SELECT gamets,data FROM eventlog where type='backgroundaction' and gamets>$lastItNumber order by gamets asc ,ts asc";
    error_log($query2);
    $backgroundEvents = $db->fetchAll($query2);
    foreach ($backgroundEvents as $event) {
        $eventParsed = json_decode($event["data"], true);
        if (! $eventParsed["source"] || $eventParsed["source"] != "AIAgent.esp") // Only AIAgent.esp for now.
        {
            continue;
        }

        if (! $eventParsed["description"]) {
            continue;
        } else if ($eventParsed["description"] == "unknown") {
            continue;
        } else if ($eventParsed["actor"] != $GLOBALS["HERIKA_NAME"]) {
            continue;
        }

        $hours      = ($event["gamets"] - $lastIt["gamets"]) * 0.0000024;
        $bgEvents[] = ["gamets" => $event["gamets"], "content" => "{$eventParsed["description"]}", "type" => "event"];

    }

}

$bgEvents[] = ["gamets" => $lastLoc["gamets"], "content" => "{$lastLoc["location"]}", "type" => "last_known_location"];

// print_r($bgEvents);

// Must mix bgEvents array and diaryEntry array, and order them using key gamets asc
$combinedEvents = array_merge($bgEvents, $diaryEntry);
usort($combinedEvents, function ($a, $b) {
    return $a['gamets'] <=> $b['gamets'];
});

// print_r($combinedEvents);
$previous = 0;
foreach ($combinedEvents as $dentry) {
    if ($dentry["type"] == "event" && $previous) {
        $hours             = ($dentry["gamets"] - $previous) * 0.0000024;
        $dentry["content"] = "* $hours hours later: {$dentry["content"]}";
    }
    $previous = $dentry["gamets"];
    $history .= "\n<{$dentry["type"]}>\n{$dentry["content"]}\n</{$dentry["type"]}>\n";

}

$daysPassed = round(($last_gamets - $lastIt["gamets"]) * 0.0000024 / 24, 2);
if (sizeof($combinedEvents) == 0) {

    $history .= "Note: After this events, $daysPassed has passed";
}

//$head[] = ['role' => 'system', 'content' => "You're an AI writer. Examine this character's logbook from a story in the Skyrim universe."];
$head["en"][] = ['role' => 'system', 'content' => "You are a writing assistant.
Examine this text containing events that occurred in the fictional universe of Skyrim (The Elder Scrolls)."];

// Extract NPC gender and race for query seed
$npcGender = isset($currentNpcData["gender"]) && trim((string)$currentNpcData["gender"]) !== "" ? trim((string)$currentNpcData["gender"]) : "";
$npcRace = isset($currentNpcData["race"]) && trim((string)$currentNpcData["race"]) !== "" ? trim((string)$currentNpcData["race"]) : "";
$characterSeed = "";
if ($npcGender !== "" && $npcRace !== "") {
    $characterSeed = " This character is {$npcRace} {$npcGender}.";
} else if ($npcGender !== "") {
    $characterSeed = " This character is {$npcGender}.";
} else if ($npcRace !== "") {
    $characterSeed = " This character is {$npcRace}.";
}

// Get optional user prompt for custom generation instructions
$userCustomPrompt = isset($jsonDataInput["user_prompt"]) ? trim((string)$jsonDataInput["user_prompt"]) : "";

// Load profile generation prompt from database with fallback to hardcoded default
$profilePrompt = null;
try {
    $promptData = $GLOBALS["db"]->fetchOne("SELECT custom_prompt, default_prompt FROM prompts WHERE prompt_key = 'character_profile_generation'");
    if ($promptData) {
        // Use custom_prompt if set, otherwise use default_prompt
        $profilePrompt = (!empty($promptData['custom_prompt'])) ? $promptData['custom_prompt'] : $promptData['default_prompt'];
    }
} catch (Exception $e) {
    Logger::warn("Failed to load profile generation prompt from database, using hardcoded fallback: " . $e->getMessage());
}

// Hardcoded fallback if database query failed or returned no results
if (!$profilePrompt) {
    $profilePrompt = 
        "The main character in this logbook is {HERIKA_NAME}.{CHARACTER_SEED}\n".
        "Read the context history (context_history) and the recent memories (middle_term_memory),\n".
        " paying attention to notable events and the names of relevant characters.\n\n\n".
        "Based on all this information, generate an character sheet for {HERIKA_NAME}.\n\n".
        "This profile must be in XML format and have these fields.\n\n".
        "<core>              Text. Core Identity, name,race an gender, and most remarkable job. Should be in the form of a sentence. e.g. 'Rose. Imperial female warrior.'\n".
        "<npc_static_bio>    Text. Basic Summary, and bio. Create if not info available in <context_history>\n".
        "<personality>       Text. Personality Traits. How the characters behave. Traumas. Likes.\n".
        "<appearance>        Text. Physical Appearance. Infer from info available in <context_history>\n".
        "<relationships>     Text. relationships with other actors.\n".
        "<occupation>        Text. Main Occupation & Role\n".
        "<skills>            Text. Skills & Abilities\n".
        "<speechstyle>       Text. Speech Style\n".
        "<goals>             Text. Long term Goals & Aspirations'\n";
}

// Replace placeholders with actual values
$profilePromptProcessed = str_replace(
    ['{HERIKA_NAME}', '{CHARACTER_SEED}'],
    [$GLOBALS["HERIKA_NAME"], $characterSeed],
    $profilePrompt
);

$userprompt["en"] = $profilePromptProcessed;

// Append user's custom instructions if provided
if ($userCustomPrompt !== "") {
    $userprompt["en"] .= "\n\nADDITIONAL INSTRUCTIONS FROM USER:\n{$userCustomPrompt}\n\nMake sure to incorporate these specific details and instructions into the generated character sheet where appropriate.";
}

$metadata   = json_decode($currentNpcData["metadata"], true);
$metadata_p = json_decode($currentProfileData["metadata"], true);

if ($metadata["CORE_LANG"] == "es" || $metadata_p["CORE_LANG"] == "es") {
    $LANG = "es";
} else {
    $LANG = "en";
}

$LANG = "en";

if (true); // Use actual
$prompt[] = ['role' => 'user', 'content' => "<character_sheet>\n{$GLOBALS["HERIKA_NAME"]}:\n$dynamicBiography\n</character_sheet>"];

$prompt[] = ['role' => 'user', 'content' => "<context_history>\nContext History\n$history\n$task\n</context_history>"];

$prompt[] = ['role' => 'user', 'content' => $userprompt[$LANG]];

$contextData = array_merge($head[$LANG], $prompt);

Logger::debug(__LINE__ . " " . (microtime(true) - $startTime));

$connectionHandler = $connector->getConnector($currentConnectorData);
$buffer            = $connectionHandler->fast_request($contextData, ["MAX_TOKENS" => 2048, "temperature" => 0.9], "profile_generation");
Logger::debug(__LINE__ . " " . (microtime(true) - $startTime));

error_log(print_r($buffer,true));

$profileFields = [
    'core'           => 'Core Identity',
    'npc_static_bio' => 'Basic Summary',
    'personality'    => 'Personality Traits',
    'appearance'     => 'Physical Appearance',
    'relationships'  => 'Relationships',
    'occupation'     => 'Occupation & Role',
    'skills'         => 'Skills & Abilities',
    'speechstyle'    => 'Speech Style',
    'goals'          => 'Goals & Aspirations',
];

$cs = [];
foreach (array_keys($profileFields) as $field) {
    $cs[$field] = strip_tags(current(manual_get_all_tag_contents($buffer, $field)));
}

//die(print_r($cs));

saveDynamicProfileUpdates($GLOBALS["HERIKA_NAME"], $cs, $db,false);
echo json_encode(["done" => true]);
