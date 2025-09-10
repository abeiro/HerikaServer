<?php
/**
 * SNQE CreateNPC function
 *
 * @param string $quest_id Unique quest identifier
 * @param string $npc_ref Internal NPC reference ID
 * @param string $name NPC display name
 * @param string $gender "Male" | "Female"
 * @param string $class "beggar"|"warrior"|"assassin"|"mage"|"farmer"|"soldier"|"merchant"|"noble"
 * @param string $race "Nord"|"Imperial"|"Argonian"|"RedGuard"|"Orc"|"Breton"
 * @param string $location Initial placement (e.g., "Whiterun" or "nearby")
 * @param string|null $appearance Visual description (optional)
 * @param string|null $background Lore/backstory (optional)
 * @param string|null $speechStyle How NPC talks (optional)
 * @param string|null $disposition "defiant"|"submissive"|"friendly"|"serious"|"sad"|"aggressive"|"cheerful"|"distrustful"|"furious"|"drunk"|"high" (optional)
 */
function CreateNPC(
    string $quest_id,
    string $npc_ref,
    string $name,
    string $gender,
    string $class,
    string $race,
    string $location,
    ?string $appearance = null,
    ?string $background = null,
    ?string $speechStyle = null,
    ?string $disposition = null
) {
    // Fetch existing quest state
    $quest = SNQEQuestManager::getQuest($quest_id);

    if (! $quest) {
        throw new Exception("Quest '$quest_id' does not exist. You must create or upsert the quest first.");
    }

    $quest_data = $quest["quest_data"] ?? [];

    // Initialize NPCs container if missing
    if (! isset($quest_data["npcs"])) {
        $quest_data["npcs"] = [];
    }

    // Check if NPC already exists
    if (isset($quest_data["npcs"][$npc_ref])) {
        // NPC already created; do nothing
        error_log("NPC already created; do nothing");
        return;
    }

    // Register NPC in quest state
    $quest_data["npcs"][$npc_ref] = [
        "name"           => $name,
        "gender"         => $gender,
        "class"          => $class,
        "race"           => $race,
        "location"       => $location,
        "appearance"     => $appearance,
        "background"     => $background,
        "speechStyle"    => $speechStyle,
        "disposition"    => $disposition,
        "spawned"        => false, // Track if SpawnNPC has been called
        "spawn_attempts" => 0,     // Count of spawn checks
    ];

    // Save updated quest state
    SNQEQuestManager::updateQuestData($quest_id, ["npcs" => $quest_data["npcs"]]);
}

/**
 * SNQE CreateItem function
 *
 * @param string $quest_id Unique quest identifier
 * @param string $item_ref Internal item reference ID
 * @param string $name Item display name
 * @param string $type "sword"|"armor"|"helmet"|"ring"|"amulet"|"book"|"note"|"axe"|"long sword"|"staff"|"great axe"|"bow"
 * @param string $location "nearby"|"major city"
 * @param string|null $description Description or content if book/note (optional)
 */
function CreateItem(
    string $quest_id,
    string $item_ref,
    string $name,
    string $type,
    string $location,
    ?string $description = null
) {
    // Fetch existing quest state
    $quest = SNQEQuestManager::getQuest($quest_id);

    if (! $quest) {
        throw new Exception("Quest '$quest_id' does not exist. You must create or upsert the quest first.");
    }

    $quest_data = $quest["quest_data"] ?? [];

    // Initialize items container if missing
    if (! isset($quest_data["items"])) {
        $quest_data["items"] = [];
    }

    // Check if item already exists
    if (isset($quest_data["items"][$item_ref])) {
        // Item already created; do nothing
        error_log("Item already created; do nothing");
        return;
    }

    // Register item in quest state
    $quest_data["items"][$item_ref] = [
        "name"           => $name,
        "type"           => $type,
        "location"       => $location,
        "description"    => $description,
        "spawned"        => false, // Track if SpawnItem has been called
        "spawn_attempts" => 0,     // Count of spawn checks
    ];

    // Save updated quest state
    SNQEQuestManager::updateQuestData($quest_id, ["items" => $quest_data["items"]]);
}

/**
 * SNQE CreateTopic function
 *
 * @param string $quest_id Unique quest identifier
 * @param string $topic_ref Internal topic reference ID
 * @param string $name Display name of the topic
 * @param string $type "Lore"|"Item"|"Location"
 * @param string|null $item If type="Item", reference to item_ref
 * @param string $giver NPC reference or name giving the topic
 * @param string $info Dialogue text or explanation
 * @param string $target NPC reference (char_ref) or "player" receiving the topic
 */
function CreateTopic(
    string $quest_id,
    string $topic_ref,
    string $name,
    string $type,
    ?string $item,
    string $giver,
    string $info,
    string $target
) {
    // Fetch existing quest state
    $quest = SNQEQuestManager::getQuest($quest_id);

    if (! $quest) {
        throw new Exception("Quest '$quest_id' does not exist. You must create or upsert the quest first.");
    }

    $quest_data = $quest["quest_data"] ?? [];

    // Initialize topics container if missing
    if (! isset($quest_data["topics"])) {
        $quest_data["topics"] = [];
    }

    // Check if topic already exists
    if (isset($quest_data["topics"][$topic_ref])) {
        // Topic already created; do nothing
        error_log("Topic already created; do nothing");
        return;
    }

    // Register topic in quest state
    $quest_data["topics"][$topic_ref] = [
        "name"              => $name,
        "type"              => $type,
        "item"              => $item,
        "giver"             => $giver,
        "info"              => $info,
        "target"            => $target,
        "delivered"         => false, // Track if topic has been delivered
        "delivery_attempts" => 0,     // Count attempts for delivery checks
    ];

    // Save updated quest state
    SNQEQuestManager::updateQuestData($quest_id, ["topics" => $quest_data["topics"]]);
}

/**
 * SNQE SpawnNPC function
 *
 * @param string $quest_id Unique quest identifier
 * @param string $npc_ref Internal NPC reference ID (from CreateNPC)
 * @param string $location Location where the NPC should spawn
 */
function SpawnNPC(
    string $quest_id,
    string $npc_ref,
    string $location
) {
    // Fetch quest state
    $quest = SNQEQuestManager::getQuest($quest_id);

    if (! $quest) {
        throw new Exception("Quest '$quest_id' does not exist.");
    }

    $quest_data = $quest["quest_data"] ?? [];

    if (! isset($quest_data["npcs"][$npc_ref])) {
        throw new Exception("NPC '$npc_ref' not declared. Use CreateNPC first.");
    }

    // If already spawned, skip
    if (! empty($quest_data["npcs"][$npc_ref]["spawned"])) {
        error_log("[SpawnNPC] NPC <$npc_ref> already spawned");
        return;
    }

                                                                 // Mark NPC as pending spawn
    $quest_data["npcs"][$npc_ref]["location"]       = $location; // update location if needed
    $quest_data["npcs"][$npc_ref]["spawned"]        = "pending";
    $quest_data["npcs"][$npc_ref]["spawn_attempts"] = 0;

    // Here we would call into the actual Skyrim engine (placeholder)
    // e.g., SkyrimAPI::spawnNPC($npc_ref, $location);
    error_log("[SpawnNPC] $npc_ref at $location, quest $quest_id");

    npcProfileBase(
        $quest_data["npcs"][$npc_ref]["name"],
        $quest_data["npcs"][$npc_ref]["class"],
        $quest_data["npcs"][$npc_ref]["race"],
        $quest_data["npcs"][$npc_ref]["gender"],
        $quest_data["npcs"][$npc_ref]["location"],
        $quest_id);

    // Save state
    SNQEQuestManager::updateQuestData($quest_id, ["npcs" => $quest_data["npcs"]]);
}

/**
 * SNQE CheckNPCSpawn function
 *
 * @param string $quest_id Unique quest identifier
 * @param string $npc_ref Internal NPC reference ID (from CreateNPC)
 * @param string $location Expected location of spawn
 * @param int $maxAttempts Maximum retries before failure (default 5)
 * @return bool|null true = spawned, false = failed, null = still waiting
 */
function CheckNPCSpawn(
    string $quest_id,
    string $npc_ref,
    string $location,
    int $maxAttempts = 5
) {
    // Fetch quest state
    $quest = SNQEQuestManager::getQuest($quest_id);

    if (! $quest) {
        throw new Exception("Quest '$quest_id' does not exist.");
    }

    $quest_data = $quest["quest_data"] ?? [];

    if (! isset($quest_data["npcs"][$npc_ref])) {
        throw new Exception("NPC '$npc_ref' not declared. Use CreateNPC first.");
    }

    $npc = $quest_data["npcs"][$npc_ref];

    // If already spawned, success
    if (($npc["spawned"] == "done")) {
        return true;
    }

    // Increment attempts
    $npc["spawn_attempts"] = ($npc["spawn_attempts"] ?? 0) + 1;

    // Simulate external Skyrim engine check (placeholder)
    // $isSpawned = SkyrimAPI::checkNPCSpawned($npc_ref, $location);

    $cn = $GLOBALS["db"]->escape($npc["name"]);
    error_log("[CheckNPCSpawn] Check if character $cn has spawned ");
    $spawned = $GLOBALS["db"]->fetchAll("select 1 as n,data from eventlog where type='status_msg'
        and data like '%spawned@$cn%' order by localts desc");

    // Pending, deal with timestamps

    if (is_array($spawned) && isset($spawned[0]) && ($spawned[0]["n"] > 0)) {
        $isSpawned = true;
        // At this point, character should be on database
        $npcMaster    = new NpcMaster();
        $npcLocalData = $npcMaster->GetByName($npc["name"]);

        $npcLocalData["core"]            = "{$npc["name"]}";
        $npcLocalData["npc_static_bio"]  = "{$npc["background"]}";
        $npcLocalData["appearance"]      = "{$npc["appearance"]}";
        $npcLocalData["is_rolemastered"] = "{$npc["appearance"]}";

        $ntopic = 0;
        foreach ($quest_data["topics"] as $topic_ref => $topic) {
            if ($topic["giver"] == $npc_ref) {
                if ($ntopic == 0) {
                    $npcLocalData["goals"] = "\nGenerate content based on the following contextual topics, but do not mention them directly or reveal any details
                        about them. Use the contextual topics only to ensure the generated content remains consistent and does not contradict future information. Avoid spoilers at all times.\n
                    *{$topic["info"]}\n";
                    $ntopic++;
                } else {
                    $npcLocalData["goals"] .= "*{$topic["info"]}\n";
                }
            }
            foreach ($quest_data["items"] as $item_key => $item) {
                if ($item_key == $quest_data["topics"][$topic_ref]["item"]) {
                    // Topic mention an item
                    $npcLocalData["goals"] .= "Related info: {$item["name"]}, {$item["description"]}";
                }
            }

        }

        foreach ($quest_data["items"] as $item) {
            if ($item["location"] == $npc_ref) {

                $npcLocalData["personality"] .= "\nInitially owns '{$item["name"]}'\n";

            }
        }

        $metaData = $npcMaster->getMetadata($npcLocalData);

        $metaData["is_rolemastered"] = true;

        $npcLocalData = $npcMaster->setMetadata($npcLocalData, $metaData);
        $npcMaster->updateByArray($npcLocalData);

        error_log("[CheckNPCSpawn] {$npc["name"]} spawned");

        $GLOBALS["db"]->insert(
            'responselog',
            [
                'localts' => time(),
                'sent'    => 0,
                'actor'   => "rolemaster",
                'text'    => "",
                'action'  => "rolecommand|moveToPlayer@{$npc["name"]}@$quest_id",
                'tag'     => "",
            ]
        );

    } else {
        $isSpawned = false;
    }
    // TODO: replace with actual engine response

    if ($isSpawned) {
        $npc["spawned"]               = "done";
        $quest_data["npcs"][$npc_ref] = $npc;
        SNQEQuestManager::updateQuestData($quest_id, ["npcs" => $quest_data["npcs"]]);
        return true;
    }

    // If exceeded max attempts, fail
    if ($npc["spawn_attempts"] >= $maxAttempts) {
        $npc["spawn_failed"]          = true;
        $quest_data["npcs"][$npc_ref] = $npc;
        SNQEQuestManager::updateQuestData($quest_id, ["npcs" => $quest_data["npcs"]]);
        error_log("NPC didn't spawn after $maxAttempts spawn_attempts");
        return false;
    }

    // Still waiting
    $quest_data["npcs"][$npc_ref] = $npc;
    error_log("NPC didn't spawn after {$npc["spawn_attempts"]} spawn_attempts");
    SNQEQuestManager::updateQuestData($quest_id, ["npcs" => $quest_data["npcs"]]);
    return null;
}

/**
 * SNQE SpawnItem function
 *
 * @param string $quest_id Unique quest identifier
 * @param string $item_ref Internal Item reference ID (from CreateItem)
 * @param string|null $char_ref If set, item will spawn in NPC's inventory
 */
function SpawnItem(
    string $quest_id,
    string $item_ref,
    ?string $char_ref = null
) {
    // Fetch quest state
    $quest = SNQEQuestManager::getQuest($quest_id);

    if (! $quest) {
        throw new Exception("Quest '$quest_id' does not exist.");
    }

    $quest_data = $quest["quest_data"] ?? [];

    if (! isset($quest_data["items"][$item_ref])) {
        throw new Exception("Item '$item_ref' not declared. Use CreateItem first.");
    }

    $item = $quest_data["items"][$item_ref];

    // If already spawned, skip
    if (! empty($item["spawned"])) {
        error_log("[SpawnItem] Spawned: <{$item["spawned"]}> {$item["type"]} {$item["name"]}, {$item["location"]}, {$item["description"]}, $quest_id)");
        return;
    }

    // Mark as pending spawn
    $item["spawned"]        = "pending";
    $item["spawn_attempts"] = 0;
    $item["spawn_target"]   = $char_ref ?? "world"; // world or NPC inventory

    // Placeholder: call to Skyrim engine
    // SkyrimAPI::spawnItem($item_ref, $char_ref);
    error_log("[SpawnItem] {$item["type"]} {$item["name"]}, {$item["location"]}, {$item["description"]}, $quest_id)".PHP_EOL);
    SkCreateItem($item["type"], $item["name"], $item["location"], $item["description"], $quest_id);

    // Save
    $quest_data["items"][$item_ref] = $item;
    SNQEQuestManager::updateQuestData($quest_id, ["items" => $quest_data["items"]]);
}

/**
 * SNQE CheckItemSpawn function
 *
 * @param string $quest_id Unique quest identifier
 * @param string $item_ref Internal item reference ID (from CreateItem)
 * @param int $maxAttempts Maximum retries before failure (default 5)
 * @return string "done"|"failed"|"pending"
 */
function CheckItemSpawn(
    string $quest_id,
    string $item_ref,
    int $maxAttempts = 5
): string {
    // Fetch quest state
    $quest = SNQEQuestManager::getQuest($quest_id);

    if (! $quest) {
        throw new Exception("Quest '$quest_id' does not exist.");
    }

    $quest_data = $quest["quest_data"] ?? [];

    if (! isset($quest_data["items"][$item_ref])) {
        throw new Exception("Item '$item_ref' not declared. Use CreateItem first.");
    }

    $item = $quest_data["items"][$item_ref];

    $cn      = $GLOBALS["db"]->escape($item["name"]);
    $spawned = $GLOBALS["db"]->fetchAll("select count(*) as n from eventlog where type='status_msg'
     and data like '%spawned_item%@$cn%success%' ");

    $isSpawned = false; // <-- stub


    // Pending, deal with timestamps
    if (!($spawned[0]["n"])) {
        error_log("[CheckItemSpawn] Item <$cn> not spawned");
        return "pending";
    } else 
        $isSpawned = true;


    // If already confirmed, return "done"
    if (! empty($item["spawned"]) && $item["spawned"] === "done") {
        return "done";
    }

    // Increment attempts
    $item["spawn_attempts"] = ($item["spawn_attempts"] ?? 0) + 1;

    // Placeholder: real Skyrim check
    // $isSpawned = SkyrimAPI::checkItemSpawned($item_ref, $item["spawn_target"]);

    if ($isSpawned) {
        error_log("[CheckItemSpawn] Item <$cn> spawned");
        $item["spawned"]                = "done";
        $quest_data["items"][$item_ref] = $item;
        SNQEQuestManager::updateQuestData($quest_id, ["items" => $quest_data["items"]]);
        return "done";
    }

    // Exceeded attempts → failure
    if ($item["spawn_attempts"] >= $maxAttempts) {
        $item["spawned"]                = "failed";
        $quest_data["items"][$item_ref] = $item;
        SNQEQuestManager::updateQuestData($quest_id, ["items" => $quest_data["items"]]);
        return "failed";
    }

    // Still pending
    $quest_data["items"][$item_ref] = $item;
    SNQEQuestManager::updateQuestData($quest_id, ["items" => $quest_data["items"]]);
    return "pending";
}

/**
 * SNQE TellTopicToPlayer function
 *
 * @param string $quest_id Unique quest identifier
 * @param string $npc_ref NPC reference (char_ref) delivering the topic
 * @param string $topic_ref Topic reference ID
 */
function TellTopicToPlayer(
    string $quest_id,
    string $npc_ref,
    string $topic_ref
) {
    // Fetch quest state
    $quest = SNQEQuestManager::getQuest($quest_id);

    if (! $quest) {
        throw new Exception("Quest '$quest_id' does not exist.");
    }

    $quest_data = $quest["quest_data"] ?? [];

    if (! isset($quest_data["npcs"][$npc_ref])) {
        throw new Exception("NPC '$npc_ref' not declared. Use CreateNPC first.");
    }

    if (! isset($quest_data["topics"][$topic_ref])) {
        throw new Exception("Topic '$topic_ref' not declared. Use CreateTopic first.");
    }

    if (strpos($GLOBALS["actors_present"], $quest_data["npcs"][$npc_ref]["name"]) === false) {
        // NPC not present
        error_log("[TellTopicToPlayer] NPC <{$quest_data["npcs"][$npc_ref]["name"]}> still not present, close npcs: {$GLOBALS["actors_present"]}");
        SNQEQuestManager::updateQuestData($quest_id,[]);
        return "pending";

    }

    $topic = $quest_data["topics"][$topic_ref];

    // If already delivered, skip
    if (! empty($topic["delivered"])) {
        error_log("[TellTopicToPlayer] Topic delivered <$topic_ref>");
        return "done";
    }

    // Mark as pending delivery
    $topic["delivered"]         = "pending";
    $topic["delivery_attempts"] = 0;
    $topic["giver"]             = $npc_ref; // force-set giver NPC
    $topic["target"]            = "player"; // player is always target here

    // Placeholder: Skyrim engine command
    // SkyrimAPI::npcTellTopicToPlayer($npc_ref, $topic_ref);

    $hintData = ("{$quest_data["npcs"][$npc_ref]["name"]} must talk to {$GLOBALS["PLAYER_NAME"]} about something like: \"{$quest_data["topics"][$topic_ref]["info"]}\".");

    foreach ($quest_data["items"] as $item_key => $item) {
        if ($item_key == $quest_data["topics"][$topic_ref]["item"]) {
            // Topic mention an item
            $hintData .= "Related info: {$item["name"]}, {$item["description"]}";

        }
    }

    $sugggestionText = make_replacements("$hintData");

    $GLOBALS["db"]->insert(
        'responselog',
        [
            'localts' => time(),
            'sent'    => 0,
            'actor'   => "rolemaster",
            'text'    => "",
            'action'  => "rolecommand|Suggestion@{$quest_data["npcs"][$npc_ref]["name"]}@$sugggestionText@$quest_id",
            'tag'     => "",
        ]
    );
    // Save
    $quest_data["topics"][$topic_ref] = $topic;
    SNQEQuestManager::updateQuestData($quest_id, ["topics" => $quest_data["topics"]]);
    return "done";
}

/**
 * SNQE CheckTopicToPlayer function
 *
 * @param string $quest_id Unique quest identifier
 * @param string $topic_ref Topic reference ID
 * @param int $maxAttempts Maximum retries before failure
 * @return string "done"|"failed"|"pending"
 */

function CheckTopicToPlayer(
    string $quest_id,
    string $topic_ref,
    int $maxAttempts = 50
): string {
    // Fetch quest state
    $quest = SNQEQuestManager::getQuest($quest_id);

    if (! $quest) {
        throw new Exception("Quest '$quest_id' does not exist.");
    }

    $quest_data = $quest["quest_data"] ?? [];

    if (! isset($quest_data["topics"][$topic_ref])) {
        throw new Exception("Topic '$topic_ref' not declared. Use CreateTopic first.");
    }

    $topic = $quest_data["topics"][$topic_ref];

    // If already delivered
    if (! empty($topic["delivered"]) && $topic["delivered"] === "done") {
        return "done";
    }

    // Increment attempts
    $topic["delivery_attempts"] = ($topic["delivery_attempts"] ?? 0) + 1;

    // Placeholder: Skyrim engine check
    // $isDelivered = SkyrimAPI::checkNPCToldTopicToPlayer($topic["giver"], $topic_ref);
    error_log("[CheckTopicToPlayer] pending...");
    $character= $quest_data["npcs"][$topic["giver"]];

    if (!isset($quest_data["topics"]["last_llm_call"])) {
        $quest_data["topics"]["last_llm_call"]=0;
    }
    
    $res=SkTopicCheck($character["name"],$topic["info"],$quest_data["topics"]["last_llm_call"],$topic["delivery_attempts"],$quest_id);
    if ($res==TOPIC_COVERED) {
        $isDelivered = true; // stub

    } else if ($res==TOPIC_UNCOVERED) {
        $isDelivered = false; // stub
        $quest_data["topics"]["last_llm_call"]=time();

    } else if ($res==WILL_DO_LATER) {
        $isDelivered = false; // stub
    }
    


    if ($isDelivered) {
        $topic["delivered"]               = "done";
        $quest_data["topics"][$topic_ref] = $topic;
        SNQEQuestManager::updateQuestData($quest_id, ["topics" => $quest_data["topics"]]);
        return "done";
    }

    // Exceeded attempts → failure
    if ($topic["delivery_attempts"] >= $maxAttempts) {
        $topic["delivered"]               = "failed";
        $quest_data["topics"][$topic_ref] = $topic;
        SNQEQuestManager::updateQuestData($quest_id, ["topics" => $quest_data["topics"]]);
        return "failed";
    }

    // Still pending
    $quest_data["topics"][$topic_ref] = $topic;
    SNQEQuestManager::updateQuestData($quest_id, ["topics" => $quest_data["topics"]]);
    return "pending";
}

/**
 * SNQE WaitToItemBeRecovered function
 *
 * Wait until the player has recovered (picked up or obtained) a specific item.
 *
 * @param string $quest_id Unique quest identifier
 * @param string $item_ref Internal item reference ID (from CreateItem)
 * @param int $maxAttempts Maximum retries before failure (default 10)
 * @return string "done"|"failed"|"pending"
 */
function WaitToItemBeRecovered(
    string $quest_id,
    string $item_ref,
    int $maxAttempts = 1000
): string {
    // Fetch quest state
    $quest = SNQEQuestManager::getQuest($quest_id);

    if (! $quest) {
        throw new Exception("Quest '$quest_id' does not exist.");
    }

    $quest_data = $quest["quest_data"] ?? [];

    if (! isset($quest_data["items"][$item_ref])) {
        throw new Exception("Item '$item_ref' not declared. Use CreateItem first.");
    }

    $item = $quest_data["items"][$item_ref];

    // If already recovered, return success
    if (! empty($item["recovered"]) && $item["recovered"] === "done") {
        return "done";
    }

    // Increment recovery attempts
    $item["recover_attempts"] = ($item["recover_attempts"] ?? 0) + 1;

    // Query event log to see if player has the item (stub integration with Skyrim engine)
    $cn   = $GLOBALS["db"]->escape($item["name"]);
    $rows = $GLOBALS["db"]->fetchAll("select count(*) as n from eventlog where type='itemfound' and data like '%$cn%'");

    $hasItem = false;
    if (is_array($rows) && isset($rows[0]) && $rows[0]["n"] > 0) {
        $hasItem = true;
    }

    if ($hasItem) {
        error_log("[WaitToItemBeRecovered] Item <$cn> recovered by player");
        $item["recovered"]              = "done";
        $quest_data["items"][$item_ref] = $item;
        SNQEQuestManager::updateQuestData($quest_id, ["items" => $quest_data["items"]]);
        return "done";
    }

    // Exceeded attempts → failure
    if ($item["recover_attempts"] >= $maxAttempts) {
        $item["recovered"]              = "failed";
        $quest_data["items"][$item_ref] = $item;
        SNQEQuestManager::updateQuestData($quest_id, ["items" => $quest_data["items"]]);
        error_log("[WaitToItemBeRecovered] Item <$cn> recovery failed after $maxAttempts attempts");
        return "failed";
    }

    // Still pending
    error_log("[WaitToItemBeRecovered] Item <$cn> not yet recovered (attempt {$item["recover_attempts"]})");
    $item["recovered"]              = "pending";
    $quest_data["items"][$item_ref] = $item;
    SNQEQuestManager::updateQuestData($quest_id, ["items" => $quest_data["items"]]);
    return "pending";
}


/**
 * SNQE WaitToItemBeTraded function
 *
 * Wait until the player has traded (given) a specific item to a specific NPC.
 *
 * @param string $quest_id Unique quest identifier
 * @param string $item_ref Internal item reference ID (from CreateItem)
 * @param string $char_ref NPC reference ID (from CreateNPC) who must receive the item
 * @param int $maxAttempts Maximum retries before failure (default 10)
 * @return string "done"|"failed"|"pending"
 */
function WaitToItemBeTraded(
    string $quest_id,
    string $item_ref,
    string $char_ref,
    int $maxAttempts = 10
): string {
    // Fetch quest state
    $quest = SNQEQuestManager::getQuest($quest_id);

    if (! $quest) {
        throw new Exception("Quest '$quest_id' does not exist.");
    }

    $quest_data = $quest["quest_data"] ?? [];

    if (! isset($quest_data["items"][$item_ref])) {
        throw new Exception("Item '$item_ref' not declared. Use CreateItem first.");
    }

    if (! isset($quest_data["npcs"][$char_ref])) {
        throw new Exception("NPC '$char_ref' not declared. Use CreateNPC first.");
    }

    $item = $quest_data["items"][$item_ref];
    $npc  = $quest_data["npcs"][$char_ref];

    // If already traded, return success
    if (! empty($item["traded"]) && $item["traded"] === "done") {
        return "done";
    }

    // Increment trade attempts
    $item["trade_attempts"] = ($item["trade_attempts"] ?? 0) + 1;

    // Query event log to see if player traded the item to the NPC
    $cnItem = $GLOBALS["db"]->escape($item["name"]);
    $cnNpc  = $GLOBALS["db"]->escape($npc["name"]);

    $rows = $GLOBALS["db"]->fetchAll("select count(*) as n from eventlog where type='itemfound' 
    and data like '%gave%$cnItem%$cnNpc%' ");

    $wasTraded = false;
    if (is_array($rows) && isset($rows[0]) && $rows[0]["n"] > 0) {
        $wasTraded = true;
    }

    if ($wasTraded) {
        error_log("[WaitToItemBeTraded] Item <$cnItem> successfully traded to NPC <$cnNpc>");
        $item["traded"]                = "done";
        $quest_data["items"][$item_ref] = $item;
        SNQEQuestManager::updateQuestData($quest_id, ["items" => $quest_data["items"]]);
        return "done";
    }

    // Exceeded attempts → failure
    if ($item["trade_attempts"] >= $maxAttempts) {
        $item["traded"]                = "failed";
        $quest_data["items"][$item_ref] = $item;
        SNQEQuestManager::updateQuestData($quest_id, ["items" => $quest_data["items"]]);
        error_log("[WaitToItemBeTraded] Item <$cnItem> trade to <$cnNpc> failed after $maxAttempts attempts");
        return "failed";
    }

    // Still pending
    error_log("[WaitToItemBeTraded] Item <$cnItem> not yet traded to <$cnNpc> (attempt {$item["trade_attempts"]})");
    $item["traded"]                = "pending";
    $quest_data["items"][$item_ref] = $item;
    SNQEQuestManager::updateQuestData($quest_id, ["items" => $quest_data["items"]]);
    return "pending";
}

/**
 * SNQE ToGoAway function
 *
 * Instructs an NPC to leave the game world (despawn).
 *
 * @param string $quest_id Unique quest identifier
 * @param string $char_ref NPC reference ID (from CreateNPC)
 * @param int $maxAttempts Maximum retries before failure (default 5)
 * @return string "done"|"failed"|"pending"
 */
function ToGoAway(
    string $quest_id,
    string $char_ref,
    int $maxAttempts = 5
): string {
    // Fetch quest state
    $quest = SNQEQuestManager::getQuest($quest_id);

    if (! $quest) {
        throw new Exception("Quest '$quest_id' does not exist.");
    }

    $quest_data = $quest["quest_data"] ?? [];

    if (! isset($quest_data["npcs"][$char_ref])) {
        throw new Exception("NPC '$char_ref' not declared. Use CreateNPC first.");
    }

    $npc = $quest_data["npcs"][$char_ref];

    // If already gone, return success
    if (! empty($npc["gone"]) && $npc["gone"] === "done") {
        return "done";
    }

    // Increment attempts
    $npc["goaway_attempts"] = ($npc["goaway_attempts"] ?? 0) + 1;

    // First time → send command to engine
    if ($npc["gone"] !== "pending" && $npc["gone"] !== "done") {
        $npc["gone"] = "pending";
        error_log("[ToGoAway] Sending NPC <{$npc["name"]}> away from quest <$quest_id>");
        
        $GLOBALS["db"]->insert(
            'responselog',
            [
                'localts' => time(),
                'sent'    => 0,
                'actor'   => "rolemaster",
                'text'    => "",
                'action'  => "rolecommand|TravelTo@{$npc["name"]}@WIDeadBodyCleanupCell@$quest_id",
                'tag'     => "",
            ]
        );
    }

    // TO-DO . Actually check NPC is gone
    $isGone = true;

    if ($isGone) {
        error_log("[ToGoAway] NPC <{$npc["name"]}> has gone away");
        $npc["gone"] = "done";
        $quest_data["npcs"][$char_ref] = $npc;
        SNQEQuestManager::updateQuestData($quest_id, ["npcs" => $quest_data["npcs"]]);
        return "done";
    }

    // Exceeded attempts → failure
    if ($npc["goaway_attempts"] >= $maxAttempts) {
        error_log("[ToGoAway] NPC <{$npc["name"]}> failed to go away after $maxAttempts attempts");
        $npc["gone"] = "failed";
        $quest_data["npcs"][$char_ref] = $npc;
        SNQEQuestManager::updateQuestData($quest_id, ["npcs" => $quest_data["npcs"]]);
        return "failed";
    }

    // Still pending
    error_log("[ToGoAway] NPC <{$npc["name"]}> still present (attempt {$npc["goaway_attempts"]})");
    $quest_data["npcs"][$char_ref] = $npc;
    SNQEQuestManager::updateQuestData($quest_id, ["npcs" => $quest_data["npcs"]]);
    return "pending";
}
