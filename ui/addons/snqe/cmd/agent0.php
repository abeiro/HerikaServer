<?php

$enginePath = dirname(__FILE__) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR;
require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "runtime_bootstrap.php";
chimRuntimeBootstrap($enginePath, [
    'load_general_settings' => true,
    'load_player_name' => true,
    'load_narrator' => true,
]);

if (!chimIsGlobalLlmConnectorEnabled('CORE_CONNECTOR_MEDIUMTERM')) {
    http_response_code(409);
    echo json_encode(['error' => 'Background & Memory Tasks are turned off in Global Settings.']);
    exit;
}

require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "model_dynmodel.php";
require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "chat_helper_functions.php";
require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "data_functions.php";
require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "logger.php";
require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "lazy_xml.php";

$GLOBALS["ENGINE_PATH"] = $enginePath;

$db = $GLOBALS["db"];

require_once $enginePath . "lib/core/npc_master.class.php";
require_once $enginePath . "lib/core/api_badge.class.php";
require_once $enginePath . "lib/core/core_profiles.class.php";
require_once $enginePath . "lib/core/llm_connector.class.php";

$connector = new LLMConnector();
$currentConnectorData = $connector->getById($GLOBALS["CORE_CONNECTOR_MEDIUMTERM"]);
$connector->setOldGlobals($currentConnectorData);

$method = $_SERVER['REQUEST_METHOD'];

$MODEL_1 = "google/gemma-4-26b-a4b-it"; // Initial quest generator
$MODEL_2 = "google/gemini-3-flash-preview";   // Quest steps generator


$formInput = json_decode(file_get_contents("php://input"), true) ?? ["npclist" => []];

header('Content-Type: application/json');

$awaredCell = $db->fetchOne("SELECT A.gamets,A.localts,cell_name,name as location_name,statics_list,A.sess::BIGINT
FROM public.eventlog A
LEFT JOIN public.named_cell B ON (B.id = A.sess::BIGINT AND B.statics_list IS NOT NULL)
LEFT JOIN public.locations C ON (C.formid=B.location_id  )
WHERE A.sess ~ '^[0-9]+$' and type='request'
and A.sess<>'pending'
order by A.gamets desc,A.localts desc
limit 1
");

// Initial case. Quest creation
if (sizeof($formInput["npclist"]) == 0) {


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

    $lastLocation = getLastLocationNamedCell();

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

    if (!empty($filteredLocArray)) {
        $suggested_location = $filteredLocArray[array_rand($filteredLocArray)];
    } else {
        $suggested_location = null;
    }

    $filteredWideLocList = "";
    foreach ($locListArrayRaw as $name => $tags) {
        if (strpos($lastLocation, $name) === false) {
            if ($tags) {
                $filteredWideLocList .= "* <$name> ($tags)\n";
            } else {
                $filteredWideLocList .= "* <$name>\n";
            }
        }
    }
    $nearByLoc = "\nLocations where new events/action can happen: \n$filteredWideLocList";

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
 * Spawn teleport doors to known locations.
 * Instruct Actors/enemies to tell topics, fight, travel.

Restrictions:
 * Use available locations
 * Scenarios are static so you CAN NOT spawn furniture/new locations or static elements,immovables.
 * You cannot instruct player ({$GLOBALS["PLAYER_NAME"]}) directly, only give hints through NPCs.
 * If you need a hidden chamber/passage, spawn a teleport door to closest available location.
 * AVOID referring to not spawned objects.
 * Avoid 'hidden doors','opened passages','small alcove'. Use a teleport door to closest available location instead.
 * Avoid conditionals/branchs. Things must be direct and concrete.

Task:
Given this context, generate the next quest steps. 

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
            ["MAX_TOKENS" => 2048, "model" => $MODEL_2, "temperature" => 0.7], // Builds classical quest, find relic stuff.
            "questpreplanner"
        );


        $result["response"] .= "\n$buffer";
    } else {
        // Initial quest title NOT provided, from blank state
        if ($formInput["suggested"]) {
            $suggested = "**You MUST follow user instructions to generate quest steps: {$formInput["suggested"]}**";
        } else {
            $suggested = "";
        }

        $query = "SELECT summary as content,gamets_truncated FROM memory_summary where summary is not null and gamets_truncated>0 order by gamets_truncated desc LIMIT 10";
        $contextDataFull = $GLOBALS["db"]->fetchAll($query);
        // $task=DataGetCurrentTask();
        $limit = 10;
        $contextFromMemories = "";

        if (sizeof($contextDataFull) == 0 || sizeof($contextDataFull) < $limit) {
        } else {

            foreach (array_reverse($contextDataFull) as $entry) {
                if ($entry["content"]) {
                    $contextFromMemories .= "===\nMemory entry, date " . convert_gamets2skyrim_date($entry["gamets_truncated"]) . PHP_EOL;
                    $contextFromMemories .= trim($entry["content"]) . PHP_EOL . PHP_EOL;
                    $lastgamets = $entry["gamets_truncated"];
                }
            }
        }




        $result["locations"] = $locListArray;

        $prompt[] = ['role' => 'system', 'content' => "You are an AI assistant, your job is to generate quest in the Skyrim universe."];

        if ($formInput["suggested"]) {

            //
            // USER PROVIDED INSTRUCTIONS
            // 

            $result["response"] = "

Player: {$GLOBALS["PLAYER_NAME"]}
Location: $lastLocation

* Nearby locations
$locList

* Nearby Actors. (you cannot instruct this actors) $closeNpcText

* Context for quest generation:
$contextFromMemories

$nearByLoc

Current Location: $lastLocation

Latest events:
$history

";

            // Contextual data
            $prompt[] = ['role' => 'user', 'content' => $result["response"]];

            $prompt[] = [
                'role' => 'user',
                'content' => "
    Giving this context, create a new quest in Skyrim Universe.

    You must follow this user instructions:
{$formInput["suggested"]}

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
* A Short Brief — Keep it very general, avoid any spoilers or future revelations, and focus strictly on the moment the quest begins, and mention the starter character and the choosed location for adventuring. 

Try to use locations from location list.

Use this format:
Quest Title:
Quest Short brief (one paragraph):

",
            ];
        } else {
            //
            // USER DID NOT PROVIDE INSTRUCTIONS
            // 
            $result["response"] = "

Player: {$GLOBALS["PLAYER_NAME"]}
Location: $lastLocation

* Nearby locations
$locList

* Nearby Actors. (you cannot instruct this actors) $closeNpcText

* Context for quest generation:
$contextFromMemories

$nearByLoc

Current Location: $lastLocation

Latest events:
$history

Ideas for initial NPC name: a character, which name must start with $randomLettersA, and surname/nick by $randomLettersB. Never use \" or ' in the name.
";

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
* A Short Brief — Keep it very general, avoid any spoilers or future revelations, and focus strictly on the moment the quest begins, and mention the starter character and the choosed location for adventuring. 
* A new (not present) starter character.

Try to use new locations from location list.

Use this format:
Quest Title:
Quest Short brief (one paragraph):
Starter character. name (fantasy-style compound name consisting of a first name and a descriptive surname.) a female|male breton|nord warrior|mage|...
Starter character should aproach Player to init the quest.

$suggested",
            ];
        }

        $contextData = $prompt;

        $connectionHandler = $connector->getConnector($currentConnectorData);

        $buffer = $connectionHandler->fast_request(
            $contextData,
            //["MAX_TOKENS" => 4096, "model" => "x-ai/grok-4-fast", "temperature" => 0.7],// Builds classical quest, find relic stuff.
            //["MAX_TOKENS" => 2048, "model" => "google/gemini-2.0-flash-001", "temperature" => 0.7], // Builds classical quest, find relic stuff.
            ["MAX_TOKENS" => 2048, "model" => $MODEL_1, "temperature" => 0.3], // Builds classical quest, find relic stuff.
            "questpreplanner"
        );

        preg_match('/Quest Title:\s*(.+?)(?:\n|$)/i', $buffer, $titleMatch);
        preg_match('/Quest Short brief\s*(?:\(.+?\))?\s*:\s*(.+?)(?:\nStarter character|$)/is', $buffer, $briefMatch);


        $result["questtitle"] = trim($titleMatch[1] ?? "");
        $result["briefing"] = trim($briefMatch[1] ?? "");
        $result["response"] = $buffer;
        $result["response"] .= "\nPlayer Name: {$GLOBALS["PLAYER_NAME"]}";
        $result["response"] .= "\nCurrent location: $lastLocation";
        if ($formInput["suggested"]) {
            $result["response"] .= "\nUser instructions (must override all): {$formInput["suggested"]}";
        }
    }
} else {
    // Quest continuation case

    $sqlfilter = " and data not like '%inner thoughts%' and type<>'innerchat' and type<>'backgroundaction' and type<>'quest'";
    $contextDataHistoric = DataLastDataExpandedFor("", 25 * -1, $sqlfilter);
    $history = "";
    $bookPattern = '/check this book:\s*<([^>]+)>/i';

    foreach ($contextDataHistoric as $element) {
        $history .= trim("{$element["content"]}") . PHP_EOL . PHP_EOL;
        // Book 

        if (preg_match($bookPattern, $element["content"], $matches)) {
            $bookTitle = $matches[1];
            $cnTitle = $GLOBALS["db"]->escape(trim($bookTitle));
            $results = $db->fetchOne("select content from books where title='$cnTitle' and content is not null order by gamets desc limit 1");
            if (empty($results)) {
                error_log("" . $bookTitle . "" . $cnTitle . " not found in database.");
            } else
                $history .= trim($results["content"]) . PHP_EOL . PHP_EOL;
        }
    }

    $lastLocation = getLastLocationNamedCell();
    //$formInput["journallist"] and $formInput["nextlist"] are arrays of strings. We should mix them resptecting order, 
    //$formInput["journallist"][0]
    //$formInput["nextlist"][0]
    //$formInput["journallist"][1]
    //$formInput["nextlist"][1]
    $journalsMixed = [];
    if ($formInput["journallist"]) {
        $maxItems = count($formInput["journallist"]);
        for ($i = 0; $i < $maxItems; $i++) {
            if (isset($formInput["journallist"][$i])) {
                $journalsMixed[] = $formInput["journallist"][$i];
            }
            if (isset($formInput["nextlist"][$i])) {
                $journalsMixed[] = $formInput["nextlist"][$i];
            }
        }
        $journals = arrayToBulletedList($journalsMixed);
    } else {
        $journals = "";
    }

    $continuationStep=end($formInput["nextlist"]);

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
                $outofscene = "(present)";
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

    $activatorsText = "";
    $activators = $awaredCell["statics_list"] ?? "";
    if (!empty($activators)) {
        $activatorsText = "# Available activators in current location. (specify id to use them for triggering, e.g. wait for Pedestal:0x00112233 to be activated)" . PHP_EOL;
        $activatorsText .= $activators . PHP_EOL;
    }

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


    // Last player diary

    $playerNameCn=$GLOBALS["db"]->escape($GLOBALS["PLAYER_NAME"]);
    $diaryRows = $GLOBALS["db"]->fetchOne("SELECT * FROM \"public\".\"diarylog\" where people='$playerNameCn' order by gamets desc limit 1");
    if ($diaryRows) {
        $lastDiaryEntry = "{$GLOBALS["PLAYER_NAME"]}'s last diary entry: " . $diaryRows["content"];
    } else {
        $lastDiaryEntry = "";
    }   
    $situationalMapDescription = buildSituationalMapDescription();
    $result["questtitle"] = $formInput["questtitle"];
    $result["briefing"] = $formInput["briefing"];

    $result["response"] = "
Storyline: {$result["questtitle"]}
Briefing:
{$result["briefing"]}

Player: {$GLOBALS["PLAYER_NAME"]}

# Dialogue and events history:

$history
$lastDiaryEntry
== end of dialogue and events history

# Other locations/entrances available for adventuring:
$wideLocList

# Spawned items in previous session
$spawnedItemList

# 🚫 FORBIDDEN / BACKGROUND NPCs (STRICTLY DO NOT USE):
# WARNING: These entities are purely decorative. You are ABSOLUTELY FORBIDDEN from mentioning them, giving them orders, or involving them in the plot.
$closeNpcText

# Already spawned NPCs (spawned in previous session, ONLY INSTRUCT this NPCs, you cannot instruct other NPCs that are not in this list):

$npcListFinal

# Previous quest steps:
$journals

# Suggested next quest intent (from previous session):
$continuationStep

# Current Location: $lastLocation

$activatorsText

# Nearby entrances (these are entrances/exits to a building/cave/scenario/room)
$situationalMapDescription
If no nearby entrances, this means current location has no passages/doors/chambers .
";

    $finishInstruction = "Create 4-5 new SIMPLE quest steps continuing the storyline, formatted as a bulleted list.";

    if (isset($formInput["needs_end"]) && $formInput["needs_end"]) {
        $finishInstruction = " Important: ** Storyline must end. Next steps should be oriented to finish storyline. Conclude all plots. ** ";
    }

    $considerFinish = "";

    $prompt[] = ['role' => 'system', 'content' => "You are a rolemaster , your job is to generate quests in the Skyrim universe."];
    $prompt[] = ['role' => 'user', 'content' => $result["response"]];
    $prompt[] = [
        'role' => 'user',
        'content' => "You may:
- Spawn **small portable items** (amulets, rings, books, notes, keys).
- Spawn **new NPC actors**. (avoid reusing Skyrim vanilla NPCs, create new ones fitting the world and scenario).
- Spawn **new enemies**.
- Instruct NPCs or enemies to **speak, fight, move, or travel**.

---

## World Constraints (Strict)
- The world is **static**.
- You **cannot** spawn or reference:
  - Furniture
  - New locations
  - Static scenery
  - Immovable or non-interactive objects
  - Chests, doors, levers, or mechanisms not already present in the world
- You **cannot** directly instruct the player character (**{$GLOBALS["PLAYER_NAME"]}**).
  - All guidance must be delivered indirectly via NPC dialogue or events.

---

## Location Rules
- Use **only** the provided:
  - **Current Location**
  - **Nearby door/passages** (try to specify ref id)
  - **Locations / Entrances Available for Adventuring**
- You **may not invent** hidden passages, secret rooms, or new entrances.
- If a different location is required:
  - The quest step **must be a travel step**
  - The destination **must exist in the provided lists**
- If **Nearby Entrances** is empty:
  - The current location has **no exits**.

---

## NPC & Entity Rules
* You MUST ONLY use NPCs from the 'Already spawned NPCs or new ones.
- ZERO TOLERANCE RULE: You are STRICTLY FORBIDDEN from using, mentioning, talking to, or giving orders to ANY NPC listed under '🚫 FORBIDDEN / BACKGROUND NPCs'. 
- Create **at most ONE new NPC**, and only if absolutely necessary.
- You may:
  - Spawn enemies
  - Spawn one recoverable item
- You may **NOT** reference:
  - Unspawned objects
  - Furniture
  - Environmental storytelling elements that do not already exist
---

## Task
Using the provided context, generate **next quest steps**.

Each quest step must:
1. Follow logically from **Previous Quest Steps**, use **Suggested Next Quest Intent** for continuity
2. Be consistent with **all dialogue and event history**
3. Take place in the **Current Location** or valid connected locations
4. Respect all world, location, and NPC constraints above

---

## Output Format
- Bullet-point list of **next steps**
- Each step must describe:
  - What happens
  - Who is involved
  - Where it occurs
- Keep steps **concrete and actionable**

---

## Example Steps (Illustrative Only)
- Player must travel to **Location Y**
- Spawn **Item X** at **Location Y**
- Spawn **Enemy NPC A** at **Location Y**
- Enemy NPC A attacks nearby NPCs
- NPC B explains the importance of Item X and leaves the area

**Explanation:**  
The player encounters Enemy NPC A at Location Y, defeats them, and recovers Item X needed by NPC B.

---

## Hard Rules (Do Not Break)
- Output must be **fully grounded** in the provided context.
- Do **not** invent factions, locations, lore, or events.
- Do **not** contradict prior quest history.
- Do **not** use standard vanilla Skyrim NPCs.
- Do **not** include out-of-context elements.
- Spawn single items (not sets of items, or items inside items).

$finishInstruction

$considerFinish

"
    ];

    $contextData = $prompt;

    $connectionHandler = $connector->getConnector($currentConnectorData);

    $buffer = $connectionHandler->fast_request(
        $contextData,
        ["MAX_TOKENS" => 2048, "model" => $MODEL_2, "temperature" => 0.7],
        "questpreplanner"
    );


    $result["last_step"] = $buffer;
    $result["locations"] = $wideLocListArray;
    $result["response"] .= "\nInstruction:\n$buffer";
}

echo json_encode($result);
