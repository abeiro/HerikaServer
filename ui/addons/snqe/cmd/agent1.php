<?php

$enginePath = dirname(__FILE__) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR;
require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "runtime_bootstrap.php";
chimRuntimeBootstrap($enginePath, [
    'load_general_settings' => true,
    'load_player_name' => true,
    'load_narrator' => true,
]);

if (!chimIsGlobalLlmConnectorEnabled('CORE_CONNECTOR_DIRECTOR')) {
    http_response_code(409);
    echo json_encode(['error' => 'Director Mode is turned off in Global Settings.']);
    exit;
}

require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "model_dynmodel.php";
require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "chat_helper_functions.php";
require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "data_functions.php";
require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "logger.php";
require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "lazy_xml.php";
require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "quest_reference_data.php";

$GLOBALS["ENGINE_PATH"] = $enginePath;

$db = $GLOBALS["db"];
$GLOBALS["db"] = $db;

require_once $enginePath . "lib/core/npc_master.class.php";
require_once $enginePath . "lib/core/api_badge.class.php";
require_once $enginePath . "lib/core/core_profiles.class.php";
require_once $enginePath . "lib/core/llm_connector.class.php";

$connector = new LLMConnector();
$currentConnectorData = $connector->getById($GLOBALS["CORE_CONNECTOR_DIRECTOR"]);
$connector->setOldGlobals($currentConnectorData);

$method = $_SERVER['REQUEST_METHOD'];

$formInput = json_decode(file_get_contents("php://input"), true);
if (!is_array($formInput)) {
    $formInput = [];
}

$questType = $formInput["questType"] ?? "toplevel";
$npclist = is_array($formInput["npclist"] ?? null) ? $formInput["npclist"] : [];
$sysprompt_content = file_get_contents(__DIR__ . "/../prompts/agent1.txt");
$userPrompt = (string) ($formInput["userprompt"] ?? "");


if (strpos($userPrompt, "Quest Title") !== false) {
    // First Quest iteration
    $userPrompt .= "\nNote: this is the first step of the quest, so first topic should be a salutation, and then generate the quest steps. THIS IS THE QUEST BEGINNING, quest will continue later, so no final end.";

}

// Validation Rules (fallback values)
$allowedRaces = array_merge(quest_reference_playable_races(), ['draugr', 'elk', 'frost_troll', 'frostbite_spider', 'dwarven_sphere_guardian', 'falmer', 'giant']);
$allowedClasses = ['beggar', 'warrior', 'assassin', 'mage', 'farmer', 'soldier', 'merchant', 'noble', 'creature', 'forsworn'];
$allowedItemTypes = ['potion', 'necklace', 'amulet', 'ring', 'book', 'axe', 'note', 'dagger'];
$allowedItemLocations = ['nearby', 'pocket'];
$promptConstraints = quest_reference_prompt_constraints($allowedRaces, $allowedClasses, $allowedItemTypes);
$allowedRaces = $promptConstraints['races'];
$allowedClasses = $promptConstraints['classes'];
$allowedItemTypes = $promptConstraints['item_types'];
$sysprompt_content = quest_reference_apply_prompt_constraints($sysprompt_content, $promptConstraints);

$prompt = [];


$fquestTitle = trim((string) ($formInput["questTitle"] ?? ''));

$prompt[] = ['role' => 'system', 'content' => $sysprompt_content];
$prompt[] = ['role' => 'user', 'content' => $userPrompt];
$prompt[] = ['role' => 'user', 'content' => "Write XML to acomplish all the quest steps"];


$allowedLocationList = is_array($formInput["locations"] ?? null) ? $formInput["locations"] : [];
$allowedLocationList[] = "nearby";
$allowedLocationList = array_values(array_unique(array_filter(array_map(
    static fn ($location) => trim((string) $location),
    $allowedLocationList
))));

$spawneditemslist = is_array($formInput["spawneditemslist"] ?? null) ? $formInput["spawneditemslist"] : [];
$contextData = $prompt;

$connectionHandler = $connector->getConnector($currentConnectorData);

$MODEL = "google/gemini-3.7-flash";
//$MODEL = "nex-agi/deepseek-v3.1-nex-n1:free";

$buffer = $connectionHandler->fast_request(
    $contextData,
    ["MAX_TOKENS" => 4096, "model" => $MODEL],
    "questplanner"
);

header('Content-Type: application/json');

// Extract XML from markdown code block
$xmlString = $buffer;
if (preg_match('/```xml\n(.*?)\n```/s', $buffer, $matches)) {
    $xmlString = $matches[1];
}

// Helper function to extract multiple tag values
function extract_all_tags($text, $tag)
{
    $results = [];
    $pattern = '<' . preg_quote($tag) . '(?:\s[^>]*)?>.*?<\/' . preg_quote($tag) . '>';
    if (preg_match_all('/' . $pattern . '/s', $text, $matches)) {
        $results = $matches[0];
    }
    return $results;
}

// Helper function to extract tag content
function extract_tag_content($text, $tag)
{
    $pattern = '/<' . preg_quote($tag) . '(?:\s[^>]*)?>(.+?)<\/' . preg_quote($tag) . '>/s';
    if (preg_match($pattern, $text, $matches)) {
        return trim($matches[1]);
    }
    return null;
}

// Validate spawn entries
function validate_spawns($xmlString, $allowedRaces, $allowedClasses, $allowedLocationList)
{
    $spawns = extract_all_tags($xmlString, 'spawn');
    $errors = [];

    // Convert allowed locations to lowercase for case-insensitive comparison
    $allowedLocationsLower = array_map(function ($location) {
        return strtolower(str_replace(' (door/passage)', '', $location));
    }, $allowedLocationList);

    foreach ($spawns as $spawn) {
        $name = extract_tag_content($spawn, 'name');
        $race = strtolower(extract_tag_content($spawn, 'race') ?? '');
        $class = strtolower(extract_tag_content($spawn, 'class') ?? '');
        $location = strtolower(extract_tag_content($spawn, 'location') ?? '');

        if (!$name) {
            $errors[] = "NPC missing name";
            continue;
        }

        // Validate race
        if (!in_array($race, $allowedRaces, true)) {
            $errors[] = "NPC '$name': Invalid race '$race'. Allowed: " . implode(', ', $allowedRaces);
        }

        // Validate class
        if (!in_array($class, $allowedClasses, true)) {
            $errors[] = "NPC '$name': Invalid class '$class'. Allowed: " . implode(', ', $allowedClasses);
        }

        // Validate fornswor-breton relationship
        if ($class === 'forsworn' && $race !== 'breton') {
            $errors[] = "NPC '$name': Class 'forsworn' requires race 'breton', but got '$race'";
        }

        // if name contains forsworn, class should be forsworn, and race breton
        if (stripos($name,"forsworn")!==false) {
            if ($class !=='forsworn') {
                $errors[] = "NPC '$name': Name contains 'forsworn', so class should be 'forsworn', but got '$class'";
            }
            if ($race !=='breton') {
                $errors[] = "NPC '$name': Name contains 'forsworn', so race should be 'breton', but got '$race'";
            }
        }
         
        // Validate location (case-insensitive)
        if ($location !== 'nearby' && !in_array($location, $allowedLocationsLower, true)) {
            if (preg_match('/^[a-zA-Z0-9\s\'-]+@[0-9]+$/', $location)) {
                continue; // Valid reference format
            } else
                $errors[] = "NPC '$name': Invalid location '$location'. Allowed: nearby or " . implode(', ', $allowedLocationList);
        }


    }

    return $errors;
}

// Validate spawned items
function validate_spawned_items($xmlString, $allowedItemTypes, $allowedLocationList, $spawneditemslist = [], $allowedItemLocations = null, $npclist = [])
{
    // Check for both 'spawneditem' and standalone 'item' tags (not inside instructions)
    $spawnedItems = extract_all_tags($xmlString, 'spawneditem');

    // For 'item' tags, we need to exclude those inside 'instruction' tags
    // First, remove all instruction blocks temporarily
    $xmlWithoutInstructions = preg_replace('/<instruction>.*?<\/instruction>/s', '', $xmlString);
    $items = extract_all_tags($xmlWithoutInstructions, 'item');

    $allItems = array_merge($spawnedItems, $items);

    $errors = [];

    // Normalize spawneditemslist to lowercase for case-insensitive comparison
    $spawnedItemsLower = array_map('strtolower', array_map('trim', $spawneditemslist ?? []));

    // Extract all spawned NPC names for owner validation
    $spawns = extract_all_tags($xmlString, 'spawn');
    $spawnedNpcNames = [];
    $spawnedNpcMap = [];
    foreach ($spawns as $spawn) {
        $name = extract_tag_content($spawn, 'name');
        if ($name) {
            $lowerName = strtolower(trim($name));
            $spawnedNpcNames[] = $lowerName;
            $spawnedNpcMap[$lowerName] = $spawn;
        }
    }

    // Normalize npclist to lowercase for case-insensitive comparison
    $npclistLower = array_map('strtolower', array_map('trim', $npclist ?? []));

    // Convert allowed locations to lowercase for case-insensitive comparison
    $allowedLocationsLower = array_map(function ($location) {
        return strtolower(str_replace(' (door/passage)', '', $location));
    }, $allowedLocationList);

    // If specific item locations provided, use those; otherwise use general locations
    if ($allowedItemLocations !== null) {
        $allowedLocationsLower = array_merge($allowedLocationsLower, array_map('strtolower', $allowedItemLocations));
        $allowedLocationsLower = array_unique($allowedLocationsLower);
    }

    foreach ($allItems as $item) {
        $name = extract_tag_content($item, 'name');
        $type = strtolower(extract_tag_content($item, 'type') ?? '');
        $location = strtolower(extract_tag_content($item, 'location') ?? '');

        if (!$name) {
            $errors[] = "Item missing name";
            continue;
        }

        // Validate item type
        if (!in_array($type, $allowedItemTypes, true)) {
            $errors[] = "Item '$name': Invalid type '$type'. Allowed: " . implode(', ', $allowedItemTypes);
        }

        // Validate location (case-insensitive)
        if (!in_array($location, $allowedLocationsLower, true)) {
            // Check first if location is a reference in the format name:0xHEXID, pattern should be like "pedestal:0x00027f92"
            if (preg_match('/^[a-zA-Z0-9\s\'-]+:0x[0-9a-fA-F]+$/', $location)) {
                continue; // Valid reference format
            } else
                $errors[] = "Item '$name': Invalid location '$location'. Allowed: " . implode(', ', [...$allowedLocationList, ...$allowedItemLocations ?? []]);
        }

        // Validate owner if present
        $owner = extract_tag_content($item, 'owner');
        if ($owner) {
            $ownerLower = strtolower(trim($owner));
            if (in_array($ownerLower, $npclistLower, true)) {
                // Owner spawned in a previous session - no ordering constraint
            } elseif (isset($spawnedNpcMap[$ownerLower])) {
                $ownerSpawnPos = strpos($xmlString, $spawnedNpcMap[$ownerLower]);
                $itemPos = strpos($xmlString, $item);
                if ($ownerSpawnPos === false || $itemPos === false || $ownerSpawnPos > $itemPos) {
                    $errors[] = "Item '$name': Owner '$owner' must be spawned before this item in the XML.";
                }
            } else {
                $errors[] = "Item '$name': Owner '$owner' is not yet spawned or in the NPC list. Allowed NPCs: " . implode(', ', array_unique(array_merge($npclist, $spawnedNpcNames)));
            }
        }

        // Check if item is already in spawneditemslist and add <spawned>true</spawned> if it is
        if (!empty($spawnedItemsLower) && in_array(strtolower(trim($name)), $spawnedItemsLower)) {
            // Extract the tag name from the item
            if (preg_match('/<(\w+)[^>]*>/', $item, $tagMatch)) {
                $tagName = $tagMatch[1];
                // Add spawned element to the item in the XML
                $itemWithSpawned = preg_replace('/(<\/' . preg_quote($tagName) . '>)/', "    <spawned>true</spawned>$1\n", $item);
                error_log("[PRE-PATCHING] $item: $itemWithSpawned");
                // Store the patch mapping: original => patched
                if (!isset($GLOBALS["PATCH_ITEMS"])) {
                    $GLOBALS["PATCH_ITEMS"] = [];
                }
                $GLOBALS["PATCH_ITEMS"][$item] = $itemWithSpawned;
            }
        }
    }

    return $errors;
}

// Validate instructions (NPCs referenced must exist in spawns and not be the player)
function validate_instructions($xmlString, $playerName = null, $npclist = [])
{
    $instructions = extract_all_tags($xmlString, 'instruction');
    $errors = [];

    // Extract all spawned NPC names for validation
    $spawns = extract_all_tags($xmlString, 'spawn');
    $spawnedNpcNames = [];
    foreach ($spawns as $spawn) {
        $name = extract_tag_content($spawn, 'name');
        if ($name) {
            $spawnedNpcNames[] = strtolower(trim($name));
        }
    }

    // Normalize npclist to lowercase for case-insensitive comparison
    $npclistLower = array_map('strtolower', array_map('trim', $npclist ?? []));

    foreach ($instructions as $instruction) {
        $npc = extract_tag_content($instruction, 'npc');
        $action = strtolower(extract_tag_content($instruction, 'action') ?? '');
        $actionOriginal = (extract_tag_content($instruction, 'action') ?? '');
        $target = extract_tag_content($instruction, 'target');

        if (!$npc) {
            $errors[] = "Instruction missing NPC name (action '$actionOriginal')";
            continue;
        }

        $npcLower = strtolower(trim($npc));

        // Check if instruction NPC is the player
        // Allow player name only for WaitToItemBeRecovered action
        if ($playerName && strtolower(trim($playerName)) === $npcLower) {
            if ($action !== 'waittoitemberecovered' && $action !== 'travelto' && $action !== 'waitforactivation' && $action !== 'waitatlocation') {
                if ($action == "telltopictonpc")
                    $errors[] = "Instruction '$actionOriginal' references player as NPC: '$npc'. Instructions can only reference spawned NPCs, not the player. Change the sense of the topic and use TellTopicToPlayer";
                else
                    $errors[] = "Instruction '$actionOriginal' references player as NPC: '$npc'. Instructions can only reference spawned NPCs, not the player.";
                continue;
            } else  if ($action == 'travelto' ) {
                $errors[] = "Instruction '$actionOriginal' references player as NPC: '$npc'. Change to WaitAtLocation without npc_ref, so quest will pause until that location is reached.";
                continue;
            }
        } else if ($action !== 'waittoitemberecovered' && $action !== 'travelto' && $action !== 'waitforactivation' ) {
            if (!empty($npclistLower) && !in_array($npcLower, $npclistLower) && !in_array($npcLower, $spawnedNpcNames)) {
                $errors[] = "Instruction '$actionOriginal' references NPC '$npc' which is not in the NPC list. Allowed NPCs: " . implode(', ', $npclist);
            }
        }

         // Validate NPC is in the provided npclist or spawned by <spawn> tags (if list is provided)
        if ($action === 'waittoitemberecovered' ) {
            if ($npcLower !== "player" && $npcLower !== strtolower($GLOBALS["PLAYER_NAME"] ?? "")) {
                $errors[] = "Instruction '$actionOriginal' references NPC '$npc', not the player, change action to WaitForPickUpItem";
            }
            
        }

        // Validate CombatPlayer instruction
        if ($action === 'combatplayer' && $target) {
            $errors[] = "Instruction action 'CombatPlayer' should not have a target. CombatNPC should be used to assist player in combat.";
        }

        if ($action === 'combatnpc' && ($target=="player" || $target==($GLOBALS["PLAYER_NAME"]))) {
            $errors[] = "Instruction action 'CombatNPC' references player, use CombatPlayer instead with no target.";
        }

    }

    return $errors;
}

// Validate that spawned items have corresponding WaitItemToBeRecovered instructions
function validate_spawned_items_recovery($xmlString)
{
    $errors = [];

    // Extract all spawned items (both spawneditem and item tags)
    $spawnedItems = extract_all_tags($xmlString, 'spawneditem');

    // For 'item' tags, exclude those inside 'instruction' tags
    $xmlWithoutInstructions = preg_replace('/<instruction>.*?<\/instruction>/s', '', $xmlString);
    $items = extract_all_tags($xmlWithoutInstructions, 'item');

    $allItems = array_merge($spawnedItems, $items);
    $allItems = $items;
    // Extract all instructions and find WaitItemToBeRecovered actions
    $instructions = extract_all_tags($xmlString, 'instruction');
    $recoveryTargets = [];

    foreach ($instructions as $instruction) {
        $action = strtolower(extract_tag_content($instruction, 'action') ?? '');
        if ($action === 'waittoitemberecovered' || $action === 'waitforpickupitem') {
            $target = extract_tag_content($instruction, 'item');
            if ($target) {
                $recoveryTargets[] = strtolower(trim($target));
            }
        }
    }

    // Check each spawned item has a corresponding recovery instruction
    foreach ($allItems as $item) {
        $name = extract_tag_content($item, 'name');
        if ($name) {
            $nameLower = strtolower(trim($name));
            if (!in_array($nameLower, $recoveryTargets)) {
                $errors[] = "Item '$name' is spawned but no 'WaitToItemBeRecovered' or 'WaitForPickUpItem' instruction found for it. Add a WaitToItemBeRecovered or WaitForPickUpItem instruction somewhere after <item>, in a logical order";
                error_log("Item '$name' is spawned but no 'WaitToItemBeRecovered' or WaitForPickUpItem  instruction found for it." . print_r($recoveryTargets, true));
            }
        }
    }

    return $errors;
}

// Parse XML with validation and retry logic
$result = [
    'npc' => [],
    'instruction' => [],
    'spawned_items' => [],
    'journal' => [],
    'rumors' => [],
    'next' => [],
    'filteredXml' => '',
    'validation' => [
        'status' => 'pending',
        'errors' => [],
        'retries' => 0
    ]
];


$maxRetries = 3;
$retryCount = 0;
$validationPassed = false;
$currentBuffer = $buffer;
$currentXmlString = $xmlString;

// Validation and retry loop
while ($retryCount < $maxRetries && !$validationPassed) {
    try {
        // Extract XML from markdown code block if needed
        $xmlString = $currentBuffer;
        if (preg_match('/```xml\n(.*?)\n```/s', $currentBuffer, $matches)) {
            $xmlString = $matches[1];
        }

        // Validate spawns and items
        $spawnErrors = validate_spawns($xmlString, $allowedRaces, $allowedClasses, $allowedLocationList);

        // Reset patch items for this iteration
        $GLOBALS["PATCH_ITEMS"] = [];
        $itemErrors = validate_spawned_items($xmlString, $allowedItemTypes, $allowedLocationList, $spawneditemslist ?? [], $allowedItemLocations, $npclist);

        // Apply item patches to the XML
        if (!empty($GLOBALS["PATCH_ITEMS"])) {
            foreach ($GLOBALS["PATCH_ITEMS"] as $original => $patched) {
                error_log("Patching $patched");
                $currentBuffer = str_replace($original, $patched, $currentBuffer);
            }
        }

        $instructionErrors = validate_instructions($xmlString, $GLOBALS["PLAYER_NAME"] ?? null, $formInput["npclist"] ?? []);

        $spawnedItemsRecoveryErrors = validate_spawned_items_recovery($xmlString);

        $allErrors = array_merge($spawnErrors, $itemErrors, $instructionErrors, $spawnedItemsRecoveryErrors);

        if (empty($xmlString)) {
            $allErrors[] = "Generated XML is empty.";
        }

        if (empty($allErrors)) {
            // Validation passed
            $validationPassed = true;
            $result['validation']['status'] = 'valid';
            $result['validation']['retries'] = $retryCount;
        } else {
            // Validation failed - retry if attempts remaining
            $result['validation']['errors'] = $allErrors;
            $retryCount++;
            
            if ($retryCount < $maxRetries) {
                // Build retry prompt with error feedback
                $errorMessage = "The following validation errors occurred:\n\n";
                foreach ($allErrors as $error) {
                    $errorMessage .= "❌ " . $error . "\n";
                }
                error_log($errorMessage);
                error_log($currentBuffer);

                // Create retry context
                $retryPrompt = $contextData;

                // Add error feedback to system context
                $retryPrompt[] = [
                    'role' => 'assistant',
                    'content' => $currentBuffer
                ];
                $retryPrompt[] = [
                    'role' => 'user',
                    'content' => "The XML you generated has validation errors:\n\n$errorMessage\nPlease fix these errors, and generate the full XML again, ensuring all previous content is preserved and only the necessary corrections are made."
                ];

                // Make retry request
                $currentBuffer = $connectionHandler->fast_request(
                    $retryPrompt,
                    ["MAX_TOKENS" => 4096, "model" => $MODEL, "temperature" => 0.3],
                    "questplanner"
                );
            } else {
                // Max retries exceeded
                $result['validation']['status'] = 'invalid_max_retries_exceeded';
                $result['validation']['retries'] = $retryCount;
            }
        }
    } catch (Exception $e) {
        $result['error'] = $e->getMessage();
        $result['validation']['status'] = 'error';
        break;
    }
}

// Parse the final XML (whether valid or not)
try {
    // Extract XML from markdown code block
    $xmlString = $currentBuffer;
    if (preg_match('/```xml\n(.*?)\n```/s', $currentBuffer, $matches)) {
        $xmlString = $matches[1];
    }

    // Extract Journal entries
    $journals = extract_all_tags($xmlString, 'journal');
    foreach ($journals as $journal) {
        $text = trim(strip_tags($journal));
        if ($text) {
            $result['journal'][] = $text;
        }
    }

    // Extract Rumors
    $rumors = extract_all_tags($xmlString, 'rumor');
    foreach ($rumors as $rumor) {
        $location = extract_tag_content($rumor, 'location') ?? "Unknown";
        $content = extract_tag_content($rumor, 'content') ?? "";

        if ($content) {
            $result['rumors'][] = "[" . $location . "] " . $content;
        }
    }

    // Extract Next steps
    $nexts = extract_all_tags($xmlString, 'next');
    foreach ($nexts as $next) {
        $text = trim(strip_tags($next));
        if ($text) {
            $result['next'][] = $text;
        }
    }

    // Extract spawned NPCs
    $spawns = extract_all_tags($xmlString, 'spawn');
    foreach ($spawns as $spawn) {
        $name = extract_tag_content($spawn, 'name');
        if ($name) {
            $result['npc'][] = $name;
        }
    }

    // Extract Spawned Items
    $spawnedItems = extract_all_tags($xmlString, 'item');
    foreach ($spawnedItems as $item) {
        $name = extract_tag_content($item, 'name');
        if ($name) {
            $result['spawned_items'][] = $name;
        }
    }

    // Build filtered XML - remove journal, rumors, next, and spawneditem elements
    $filteredXml = $xmlString;

    // Remove all journal elements
    $filteredXml = preg_replace('/<journal>.*?<\/journal>/s', '', $filteredXml);

    // Remove all rumor elements
    $filteredXml = preg_replace('/<rumor>.*?<\/rumor>/s', '', $filteredXml);

    // Remove all next elements
    $filteredXml = preg_replace('/<next>.*?<\/next>/s', '', $filteredXml);

    // Remove all spawneditem elements
    $filteredXml = preg_replace('/<spawneditem>.*?<\/spawneditem>/s', '', $filteredXml);

    // Clean up extra whitespace
    $filteredXml = preg_replace('/\n\s*\n/', "\n", $filteredXml);

    $result['filteredXml'] = trim($filteredXml);

} catch (Exception $e) {
    $result['error'] = $e->getMessage();
    $result['validation']['status'] = 'error';
}

if ($fquestTitle) {
    $result["title"] = $fquestTitle;
}
//Patch 
$result["journallist"] = $result["journal"];
echo json_encode($result);
