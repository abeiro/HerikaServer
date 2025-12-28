<?php

$enginePath = dirname(__FILE__) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR;
require_once $enginePath . "conf" . DIRECTORY_SEPARATOR . "conf.php";
require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "model_dynmodel.php";
require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "{$GLOBALS["DBDRIVER"]}.class.php";
require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "chat_helper_functions.php";
require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "data_functions.php";
require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "logger.php";
require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "lazy_xml.php";

$GLOBALS["ENGINE_PATH"] = $enginePath;

$db = new sql();

require_once $enginePath . "lib/core/npc_master.class.php";
require_once $enginePath . "lib/core/api_badge.class.php";
require_once $enginePath . "lib/core/core_profiles.class.php";
require_once $enginePath . "lib/core/llm_connector.class.php";

$connector = new LLMConnector();
$currentConnectorData = $connector->getById($GLOBALS["CORE_CONNECTOR_MEDIUMTERM"]);
$connector->setOldGlobals($currentConnectorData);

$method = $_SERVER['REQUEST_METHOD'];

$formInput = json_decode(file_get_contents("php://input"), true) ?? ["npclist" => []];

header('Content-Type: application/json');

if (sizeof($formInput["npclist"]) == 0) { // Initial case


    $sqlfilter = " and data not like '%inner thoughts%' and type<>'innerchat' and type<>'backgroundaction'";
    $contextDataHistoric = DataLastDataExpandedFor("", 25 * -1, $sqlfilter);
    $history = "";
    foreach ($contextDataHistoric as $element) {
        $history .= trim("{$element["content"]}") . PHP_EOL . PHP_EOL;
    }

    // Weighted random selection of nord-like initials. TO-DO: expand per race
    $nordInitials =
        str_repeat('A', 4) .
        str_repeat('B', 3) .
        str_repeat('E', 4) .
        str_repeat('F', 3) .
        str_repeat('G', 4) .
        str_repeat('H', 5) .
        str_repeat('I', 3) .
        str_repeat('J', 2) .
        str_repeat('K', 4) .
        str_repeat('L', 4) .
        str_repeat('M', 4) .
        str_repeat('N', 3) .
        str_repeat('R', 6) .
        str_repeat('S', 5) .
        str_repeat('T', 5) .
        str_repeat('U', 2) .
        str_repeat('V', 2);

    $randomLettersA = $nordInitials[rand(0, strlen($nordInitials) - 1)];
    $randomLettersB = $nordInitials[rand(0, strlen($nordInitials) - 1)];

    $lastLocation = DataLastKnownLocation();

    $closeNpc = explode("|", DataBeingsInRange());
    $closeNpcText = implode("\n", $closeNpc);
    $locList = implode("\n", DataPosibleLocationsToGo());

    $locListArrayRaw = (DataPosibleLocationsToGoWide());
    $wideLocList = "";
    foreach ($locListArrayRaw as $name => $tags) {
        if ($tags) {
            $wideLocList .= "* <$name> ($tags)\n";
        } else {
            $wideLocList .= "* <$name>\n";
        }

    }

    $locListArray = array_keys($locListArrayRaw);

    // Filter out locations containing "military" (case-insensitive) and "Duskglow Crevice"
    $filteredLocArray = array_filter($locListArray, function ($location) {
        return stripos($location, "military") === false && $location !== "Duskglow Crevice";
    });

    // If all locations contain "military", use the original array
    if (empty($filteredLocArray)) {
        $filteredLocArray = $locListArray;
    }

    $suggested_location = $filteredLocArray[array_rand($filteredLocArray)];

    $nearByLoc = "\nLocations where new events/action can happen: \n$wideLocList";

    $nearByLoc .= "\n\nNote: locations for adventuring must be chosen from the list above. 
    * 'Dungeon' tagged locations are preferred for quest generation as they have interiors (classical D&D dungeon).
    * You can use other locations, but pay attention to tags.";

    $connector = new LLMConnector();
    $currentConnectorData = $connector->getById($GLOBALS["CORE_CONNECTOR_MEDIUMTERM"]);
    $connector->setOldGlobals($currentConnectorData);

    // Initial quest title provided, probably from web UI
    if ($formInput["questtitle"]) {
        $result["response"] = "

Player: {$GLOBALS["PLAYER_NAME"]}
Location: $lastLocation

* Nearby locations
$locList

* Nearby Actors. (you cannot instruct this actors) $closeNpcText

$nearByLoc

Current Location: $lastLocation

";
        $result["locations"] = $locListArray;

        $prompt[] = ['role' => 'system', 'content' => "You are an AI assistant, your job is to generate quest in the Skyrim universe."];
        $prompt[] = ['role' => 'user', 'content' => $result["response"]];

        $prompt[] = [
            'role' => 'user',
            'content' => "
As a rolemaster you can:
 * Spawm small items (amulets, rings, books, notes,...)
 * Spawn NEW Actors.
 * Spawn NEW enemies
 * Instruct Actors/enemies to tell topics, fight, travel.

Restrictions:
 * Use available locations
 * Scenarios are static so you CAN NOT spawn furniture/new locations or static elements,immovables, or non-interactive objects.
 * You cannot instruct player ({$GLOBALS["PLAYER_NAME"]}) directly, only give hints through NPCs.

Task:
Given this context, generate the next quest steps. (just generate 4/5 steps)

Creation rules:

* The next step must follow the quest logically. Read “Previous quest steps” and all dialogue + events history to determine the natural continuation.
* The next step must use the “Current Location” and “Nearby entrances” to determine where the next action occurs.
* If the story requires a different location, you may only choose one from “Locations/entrances available for adventuring” and quest step must be start travel to that location.
* Use only locations from lists (no hidden passages, no hidden entrances, no hidden chambers). If plot needs a hidden place, use the closest available location.
* If available nearby locations is none, this means current location has no passages to another locations, so no hidden caverns or places to enter.
* Try to involve only “Already spawned NPCs”; create one new NPC ONLY if absolutely needed.
* You may include enemies and an item to recover.
* You MAY NOT use or reference furniture or unspawned elements.
$finishInstruction

E.G:
  * Player must travel to location Y
  * Spawn item X at Y or NPC A's pocket
  * Spawn enemy NPC A at Y
  * Player must defeat NPC A
  * Player must find and recover item X
  * NPC B must talk to player about
  * NPC B must leave the scene

Explanation: Player fights with NPC A ay Y, to recover item X for NPC B


Output must be fully grounded in the provided context. Do NOT invent unrelated factions, locations, or events, and do not contradict the history.

Do NOT use standard vanilla Skyrim NPCs.

No out-of-context elements; keep everything consistent with events so far.

Now, based on the provided quest title and briefing, create a new quest in Skyrim Universe.

Quest: {$formInput["questtitle"]}
Short briefing:{$formInput["briefing"]}
",
        ];

        $contextData = $prompt;

        $connectionHandler = $connector->getConnector($currentConnectorData);

        $buffer = $connectionHandler->fast_request(
            $contextData,
            //["MAX_TOKENS" => 4096, "model" => "x-ai/grok-4-fast", "temperature" => 0.7],// Builds classical quest, find relic stuff.
            ["MAX_TOKENS" => 2048, "model" => "google/gemini-2.0-flash-001", "temperature" => 0.7], // Builds classical quest, find relic stuff.
            "questpreplanner"
        );


        $result["response"] .= "\n$buffer";

    } else {
        // Initial quest title NOT provided, from blank state
        if ($formInput["suggested"]) {
            $suggested = "Preferenced idea and starter NPC for the quest: " . $formInput["suggested"];
        } else {
            $suggested = "";
        }

        $result["response"] = "

Player: {$GLOBALS["PLAYER_NAME"]}
Location: $lastLocation

* Nearby locations
$locList

* Nearby Actors. (you cannot instruct this actors) $closeNpcText

$nearByLoc

Current Location: $lastLocation


Ideas for initial NPC name: a woman, which name must start with $randomLettersA, and surname/nick by $randomLettersB. Never use \" or ' in the name.
";
        $result["locations"] = $locListArray;

        $prompt[] = ['role' => 'system', 'content' => "You are an AI assistant, your job is to generate quest in the Skyrim universe."];
        $prompt[] = ['role' => 'user', 'content' => $result["response"]];

        $prompt[] = [
            'role' => 'user',
            'content' => "
    Giving this context, create a new quest in Skyrim Universe.

* Some examples

 * A trade caravan disappeared on the road between X and Y. Tracking clues reveals illusion magic masking an abandoned ruin
 * A dying hunter asks you to finish his life’s mission—finding a mythical beast.
 * Find a chest of unsent letters belonging to a deceased soldier. Delivering them reveals an interwoven story across Skyrim’s families.
 * A bard lost his voice to a magical artifact. Recovering it requires playing through musical puzzles or reenacting old songs as small quests.
 * A rumored “sweetroll cult” hoards pastries. Investigating reveals either a goofy club or—if you prefer—a surprisingly dark conspiracy.
 * A dying trapper in X begs you to retrieve his family’s ancestral amulet from a frostbite spider nest
 * A healer in X can remove a traumatic memory from a grieving widow. She asks the player to gather rare alchemical ingredients.
 * Get a secret for an NPC, obtained just talking to him
 * Travel through this region to gather information about a missing artifact.
 * Defeat 3 champions over Skyrim
 * Retrieve item from actor X to actor Y, without using combat/violence.
 * Seduce an actor to gain other's actor favor


Be creative but keep focus. Just write :
* The quest title
* A Short Brief — Keep it very general, avoid any spoilers or future revelations, and focus strictly on the moment the quest begins, and mention the starter character. 
* A new (not present) starter character.

Try to use new locations form location list.

Use this format:
Quest Title:
Quest Short brief (one paragraph):
Starter character. name (fantasy-style compound name consisting of a first name and a descriptive surname.) a female|male breton|nord warrior|mage|...
Starter character should aproach Player to init the quest.

$suggested",
        ];

        $contextData = $prompt;

        $connectionHandler = $connector->getConnector($currentConnectorData);

        $buffer = $connectionHandler->fast_request(
            $contextData,
            //["MAX_TOKENS" => 4096, "model" => "x-ai/grok-4-fast", "temperature" => 0.7],// Builds classical quest, find relic stuff.
            //["MAX_TOKENS" => 2048, "model" => "google/gemini-2.0-flash-001", "temperature" => 0.7], // Builds classical quest, find relic stuff.
            ["MAX_TOKENS" => 2048, "model" => "bytedance-seed/seed-1.6-flash", "temperature" => 0.3], // Builds classical quest, find relic stuff.
            "questpreplanner"
        );

        preg_match('/Quest Title:\s*(.+?)(?:\n|$)/i', $buffer, $titleMatch);
        preg_match('/Quest Short brief\s*(?:\(.+?\))?\s*:\s*(.+?)(?:\nStarter character|$)/is', $buffer, $briefMatch);

        $result["questtitle"] = trim($titleMatch[1] ?? "");
        $result["briefing"] = trim($briefMatch[1] ?? "");
        $result["response"] .= "\n$buffer";
    }

} else {

    $sqlfilter = " and data not like '%inner thoughts%' and type<>'innerchat' and type<>'backgroundaction' and type<>'quest'";
    $contextDataHistoric = DataLastDataExpandedFor("", 25 * -1, $sqlfilter);
    $history = "";
    foreach ($contextDataHistoric as $element) {
        $history .= trim("{$element["content"]}") . PHP_EOL . PHP_EOL;
    }

    $lastLocation = DataLastKnownLocationHuman();
    $journals = arrayToBulletedList($formInput["journallist"]);

    $npcList = [];
    foreach ($formInput["npclist"] as $npc) {
        $lastAction = DataLastAction($npc);
        $isDead = DataActorHasDied($npc);
        if ($isDead) {
            $npcList[] = " * $npc (dead)";
        } else {
            if (strpos(DataBeingsOrDeathsInRangeExcluding(), $npc) == false) {
                $outofscene = "(out of scene)";
            } else {
                $outofscene = "";
            }

            $lac = explode("|", $lastAction["original"]);
            if ($lac[2]) {
                $npcList[] = " * $npc: $outofscene is currently executing action " . trim($lac[2]);
            } else {
                $npcList[] = " * $npc  $outofscene";
            }

        }
    }

    $npcListFinal = implode("\n", $npcList);
    $closeNpc = explode("|", DataBeingsOrDeathsInRangeExcluding());
    $closeNpcArray = explode("|", DataBeingsOrDeathsInRangeExcluding());
    // Remove NPCs from closeNpcArray which are in array $formInput["npclist"]
    // closeNpcArray may contain notes, so we check if each NPC name is contained in the entry
    $closeNpcArray = array_filter($closeNpcArray, function ($entry) {
        foreach ($GLOBALS["formInput"]["npclist"] as $npcName) {
            if (stripos($entry, $npcName) !== false) {
                return false; // Found this NPC, discard it
            }
        }
        return true; // Keep this entry
    });

    $closeNpcText = (sizeof($closeNpcArray) > 0) ? arrayToBulletedList($closeNpcArray) : "none";

    $locList = arrayToBulletedList(DataPosibleLocationsToGo()) ?? " (none)";

    $locListArray = DataPosibleLocationsToGoWide();

    $locListArrayRaw = (DataPosibleLocationsToGoWide());
    $wideLocList = "";
    foreach ($locListArrayRaw as $name => $tags) {
        if ($tags) {
            $wideLocList .= "* <$name> ($tags)\n";
        } else {
            $wideLocList .= "* <$name>\n";
        }

    }

    //$wideLocList      = implode("\n", array_merge($locListArray, DataPosibleLocationsToGo()));
    $wideLocListArray = array_merge(array_keys($locListArrayRaw), DataPosibleLocationsToGo());
    $prevSteps = arrayToBulletedList($formInput["nextlist"], " * [done]");

    $spawnedItemArray = $formInput["spawneditemslist"];

    foreach ($spawnedItemArray as $n => $itemName) {
        $cn = $GLOBALS["db"]->escape($itemName);
        $rows = $GLOBALS["db"]->fetchAll("select count(*) as n from eventlog where type='itemfound' and data like '%$cn%'");
        if ($rows && $rows[0]["n"]) {
            $spawnedItemArray[$n] = "$itemName (already recovered)";
        } else {
            $spawnedItemArray[$n] = "$itemName (unrecovered)";
        }
    }

    $spawnedItemList = arrayToBulletedList($spawnedItemArray);

    $result["questtitle"] = $formInput["questtitle"];
    $result["briefing"] = $formInput["briefing"];

    $result["response"] = "
Storyline: {$result["questtitle"]}
Briefing:
{$result["briefing"]}

Player: {$GLOBALS["PLAYER_NAME"]}

# Journal Entry:
$journals

# Dialogue and events history:

$history

== end of dialogue and events history

# Nearby entrances (these are entrances to another building/cave/scenario)
$locList

# Locations/entrances available for adventuring:
$wideLocList

# Spawned items in previous session
$spawnedItemList

# Nearby NPC (not  relevant to the storyline, you cannot instruct this NPC).
$closeNpcText

# Already spawned NPCs (spawned in previous session, you can only instruct this NPCs, you cannot instruct other NPCs that are not in this list):

$npcListFinal

# Previous quest steps:
$prevSteps

# Current Location: $lastLocation
";
    $finishInstruction = "";

    if (isset($formInput["needs_end"]) && $formInput["needs_end"]) {
        $finishInstruction = " Important: * Next steps should be oriented to finish storyline. Conclude all plots. ";

    }

    $considerFinish = "Output format:
Output must be a bulleted list of single tasks that a rolemaster should do to achieve the next step, and a brief explanation after all elements.";

    $prompt[] = ['role' => 'system', 'content' => "You are a rolemaster , your job is to generate quests in the Skyrim universe."];
    $prompt[] = ['role' => 'user', 'content' => $result["response"]];
    $prompt[] = [
        'role' => 'user',
        'content' => "
As a rolemaster you can:
 * Spawm small items (amulets, rings, books, notes,...)
 * Spawn NEW Actors.
 * Spawn NEW enemies
 * Instruct Actors/enemies to tell topics, fight, travel.

Restrictions:
 * Use available locations
 * Scenarios are static so you CAN NOT spawn furniture/new locations or static elements,immovables, or non-interactive objects.

Task:
Given this context, generate the next quest steps. (just generate 4/5 steps)
$finishInstruction
Creation rules:

* The next step must follow the quest logically. Read “Previous quest steps” and all dialogue + events history to determine the natural continuation.
* The next step must use the “Current Location” and “Nearby entrances” to determine where the next action occurs.
* If the story requires a different location, you may only choose one from “Locations/entrances available for adventuring” and quest step must be start travel to that location.
* Use only locations from lists (no hidden passages, no hidden entrances, no hidden chambers). If plot needs a hidden place, use the closest available location.
* If available nearby locations is none, this means current location has no passages to another locations, so no hidden caverns or places to enter.
* Try to involve only “Already spawned NPCs”; create one new NPC ONLY if absolutely needed.
* You may include enemies and an item to recover.
* You MAY NOT use or reference furniture or unspawned elements.


E.G:
  * Player must travel to location Y
  * Spawn item X at Y or NPC A's pocket
  * Spawn enemy NPC A at Y
  * Player must defeat NPC A
  * Player must find and recover item X
  * NPC B must talk to player about
  * NPC B must leave the scene

Explanation: Player fights with NPC A ay Y, to recover item X for NPC B


Output must be fully grounded in the provided context. Do NOT invent unrelated factions, locations, or events, and do not contradict the history.

Do NOT use standard vanilla Skyrim NPCs.

No out-of-context elements; keep everything consistent with events so far.

$considerFinish

"
    ];

    $contextData = $prompt;

    $connectionHandler = $connector->getConnector($currentConnectorData);

    $buffer = $connectionHandler->fast_request(
        $contextData,
        ["MAX_TOKENS" => 2048, "model" => "google/gemini-2.0-flash-001", "temperature" => 0.7],
        "questpreplanner"
    );

    $result["response"] .= "\nInstruction:\n$buffer";

    $result["locations"] = $wideLocListArray;

}

echo json_encode($result);
