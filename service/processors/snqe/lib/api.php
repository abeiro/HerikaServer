<?php

define("_TRADE_TIMEOUT", 120);

/**
 * SNQE CreateNPC function
 *
 * @param string $quest_id Unique quest identifier
 * @param string $npc_ref Internal NPC reference ID
 * @param string $name NPC display name
 * @param string $gender "Male" | "Female"
 * @param string $class "beggar"|"warrior"|"assassin"|"mage"|"farmer"|"soldier"|"merchant"|"noble"|"creature"||"forsworn"
 * @param string $race "Nord"|"Imperial"|"Argonian"|"RedGuard"|"Orc"|"Breton"
 * @param string $location Initial placement (e.g., "Whiterun" or "nearby")
 * @param string|null $appearance Visual description (optional)
 * @param string|null $background Lore/backstory (optional) e.g. "A reclusive scholar seeking lost knowledge. Born in Raven Rock...Grew up studying ancient texts...Currently obsessed with finding old trinkets."
 * @param string|null $speechStyle How NPC talks (optional), e.g., "casual, using slang, sometimes cursed words and metions old god names"
 * @param string|null $disposition "defiant"|"submissive"|"friendly"|"serious"|"sad"|"aggressive"|"cheerful"|"distrustful"|"furious"|"drunk"|"high"|"dead" (optional)
 * @param string|null $goal inmediate goal (optional)
 */
function CreateNPC(
    string $quest_id,
    string $npc_ref,
    string $name,
    ?string $gender = '',
    ?string $class = '',
    ?string $race = '',
    ?string $location = '',
    ?string $appearance = null,
    ?string $background = null,
    ?string $speechStyle = null,
    ?string $disposition = null,
    ?string $goal = null
) {
    // Fetch existing quest state
    $quest = SNQEQuestManager::getQuest($quest_id);

    if (!$quest) {
        throw new Exception("Quest '$quest_id' does not exist. You must create or upsert the quest first.");
    }

    $quest_data = $quest["quest_data"] ?? [];

    // Initialize NPCs container if missing
    if (!isset($quest_data["npcs"])) {
        $quest_data["npcs"] = [];
    }

    // Check if NPC already exists
    if (isset($quest_data["npcs"][$npc_ref])) {
        // NPC already created; do nothing
        error_log("[CreateNPC]\tNPC already created; do nothing");
        return;
    }

    // Register NPC in quest state
    $quest_data["npcs"][$npc_ref] = [
        "name" => $name,
        "gender" => $gender,
        "class" => $class,
        "race" => $race,
        "location" => $location,
        "appearance" => $appearance,
        "background" => $background,
        "speechStyle" => $speechStyle,
        "disposition" => $disposition,
        "goal" => $goal,
        "spawned" => false, // Track if SpawnNPC has been called
        "spawn_attempts" => 0,     // Count of spawn checks
    ];

    $npcMaster = new NpcMaster();
    $npcLocalData = $npcMaster->GetByName($name);
    if ($npcLocalData) {
        // If NPC already exists in database, update with quest info
        $extData = $npcMaster->getExtendedData($npcLocalData);
        $extData["starring_in_quest"] = $quest_id;
        $npcLocalData = $npcMaster->setExtendedData($npcLocalData, $extData);
        $npcMaster->updateByArray($npcLocalData);
    }
    
    // Save updated quest state
    SNQEQuestManager::updateQuestData($quest_id, ["npcs" => $quest_data["npcs"]]);
}

/**
 * SNQE CreateItem function
 *
 * @param string $quest_id Unique quest identifier
 * @param string $item_ref Internal item reference ID
 * @param string $name Item display name
 * @param string $type  'potion', 'necklace', 'amulet', 'ring', 'book', 'axe', 'note', 'dagger','armor'
 * @param string $location "nearby"|"major city"
 * @param string|null $description Description or content if book/note (optional)
 * @param string|null $npc_ref NPC reference ID to place item in inventory (optional). **Do not confuse with owner of the item.**
 */
function CreateItem(
    string $quest_id,
    string $item_ref,
    string $name,
    string $type,
    string $location,
    ?string $description = null,
    ?string $npc_ref = null
) {
    // Fetch existing quest state
    $quest = SNQEQuestManager::getQuest($quest_id);

    if (!$quest) {
        throw new Exception("Quest '$quest_id' does not exist. You must create or upsert the quest first.");
    }


    $quest_data = $quest["quest_data"] ?? [];

    // Initialize items container if missing
    if (!isset($quest_data["items"])) {
        $quest_data["items"] = [];
    }

    // Check if item already exists
    if (isset($quest_data["items"][$item_ref])) {
        // Item already created; do nothing
        error_log("[CreateItem]\tItem <$item_ref> already created; do nothing");
        return;
    }

    // Register item in quest state
    $quest_data["items"][$item_ref] = [
        "name" => $name,
        "type" => $type,
        "location" => $location,
        "description" => $description,
        "npc_ref" => $npc_ref, // NPC inventory reference (optional)
        "spawned" => false,    // Track if SpawnItem has been called
        "spawn_attempts" => 0,        // Count of spawn checks
    ];

    if ($npc_ref) {
        $npcMaster = new NpcMaster();
        $currentNpcData = $npcMaster->getByName($quest_data["npcs"][$npc_ref]["name"]);
        $currentNpcData["goals"] .= "\nNote that this actor has this item: <$name> in its inventory, which can be relevant to the current storyline\n";
        $npcMaster->updateByArray($currentNpcData);
    }

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
 * @param bool $important Whether topic requires multiple checks (default true)
 */
function CreateTopic(
    string $quest_id,
    string $topic_ref,
    string $name,
    string $type,
    ?string $item,
    string $giver,
    string $info,
    string $target,
    bool $important = true
) {
    // Fetch existing quest state
    $quest = SNQEQuestManager::getQuest($quest_id);

    if (!$quest) {
        throw new Exception("Quest '$quest_id' does not exist. You must create or upsert the quest first.");
    }

    $quest_data = $quest["quest_data"] ?? [];

    // Initialize topics container if missing
    if (!isset($quest_data["topics"])) {
        $quest_data["topics"] = [];
    }

    // Check if topic already exists
    if (isset($quest_data["topics"][$topic_ref])) {
        // Topic already created; do nothing
        error_log("[CreateTopic]\tTopic <$topic_ref> already created; do nothing");
        return;
    }

    // Register topic in quest state
    $quest_data["topics"][$topic_ref] = [
        "name" => $name,
        "type" => $type,
        "item" => $item,
        "giver" => $giver,
        "info" => $info,
        "target" => $target,
        "important" => $important, // Whether topic requires verification checks
        "delivered" => false,      // Track if topic has been delivered
        "delivery_attempts" => 0,  // Count attempts for delivery checks
        "attempts" => 0,           // Count retries while the speaker is not nearby
        "created" => 0,            // Set when the suggestion is actually issued
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

    if (!$quest) {
        throw new Exception("Quest '$quest_id' does not exist.");
    }

    $quest_data = $quest["quest_data"] ?? [];

    if (!isset($quest_data["npcs"][$npc_ref])) {
        throw new Exception("NPC '$npc_ref' not declared. Use CreateNPC first.");
    }

    $npc = $quest_data["npcs"][$npc_ref];
    $cn = $GLOBALS["db"]->escape($npc["name"]);
    error_log("[CheckNPCSpawn]\tCheck if character $cn has spawned ");
    error_log("select 1 as n,data from eventlog where type='status_msg'
        and data like '%spawned@$cn@%' order by localts desc");
    $spawned = $GLOBALS["db"]->fetchOne("select 1 as n,data from eventlog where type='status_msg'
        and data like '%spawned@$cn@%' order by localts desc");

    // Check if spawned in previous session
    if (isset($spawned["n"])) {
        error_log("Spawned in previous session");
        return;
    }

    // If already spawned, skip
    if (!empty($quest_data["npcs"][$npc_ref]["spawned"])) {
        error_log("[SpawnNPC]\tNPC <$npc_ref> already spawned");
        return;
    }

    // Mark NPC as pending spawn
    $quest_data["npcs"][$npc_ref]["location"] = $location; // update location if needed
    $quest_data["npcs"][$npc_ref]["spawned"] = "pending";
    $quest_data["npcs"][$npc_ref]["spawn_attempts"] = 0;

    // Here we would call into the actual Skyrim engine (placeholder)
    // e.g., SkyrimAPI::spawnNPC($npc_ref, $location);
    error_log("[SpawnNPC]\t$npc_ref at $location, quest $quest_id");

    npcProfileBase(
        $quest_data["npcs"][$npc_ref]["name"],
        $quest_data["npcs"][$npc_ref]["class"],
        $quest_data["npcs"][$npc_ref]["race"],
        $quest_data["npcs"][$npc_ref]["gender"],
        $quest_data["npcs"][$npc_ref]["location"],
        $quest_id,
        $quest_data["npcs"][$npc_ref]
    );

    // Save state
    SNQEQuestManager::updateQuestData($quest_id, ["npcs" => $quest_data["npcs"]]);
}

/**
 * SNQE CheckNPCSpawn function
 *
 * @param string $quest_id Unique quest identifier
 * @param string $npc_ref Internal NPC reference ID (from CreateNPC)
 * @param int $maxAttempts Maximum retries before failure (default 5)
 * @return bool|null true = spawned, false = failed, null = still waiting
 */
function CheckNPCSpawn(
    string $quest_id,
    string $npc_ref,
    int $maxAttempts = 5
): string {
    // Fetch quest state
    $quest = SNQEQuestManager::getQuest($quest_id);

    if (!$quest) {
        throw new Exception("Quest '$quest_id' does not exist.");
    }

    $quest_data = $quest["quest_data"] ?? [];

    if (!isset($quest_data["npcs"][$npc_ref])) {
        throw new Exception("NPC '$npc_ref' not declared. Use CreateNPC first.");
    }

    $npc = $quest_data["npcs"][$npc_ref];

    // If already spawned, success
    if (($npc["spawned"] == "done")) {
        error_log("[CheckNPCSpawn]\t<{$npc["spawned"]}> markd as spawned ");
        return "done";
    }

    // Increment attempts
    $npc["spawn_attempts"] = ($npc["spawn_attempts"] ?? 0) + 1;

    // Simulate external Skyrim engine check (placeholder)
    // $isSpawned = SkyrimAPI::checkNPCSpawned($npc_ref, $location);

    $cn = $GLOBALS["db"]->escape($npc["name"]);
    error_log("[CheckNPCSpawn]\tCheck if character $cn has spawned ");
    error_log("select 1 as n,data from eventlog where type='status_msg'
        and data like '%spawned@$cn@%' order by localts desc");
    $spawned = $GLOBALS["db"]->fetchAll("select 1 as n,data from eventlog where type='status_msg'
        and data like '%spawned@$cn@%' order by localts desc");

    // Pending, deal with timestamps

    if (is_array($spawned) && isset($spawned[0]) && ($spawned[0]["n"] > 0)) {
        $isSpawned = true;
        // At this point, character should be on database
        $npcMaster = new NpcMaster();
        $npcLocalData = $npcMaster->GetByName($npc["name"]);

        $npcLocalData["core"] = "{$npc["name"]}";
        $npcLocalData["npc_static_bio"] = "{$npc["background"]}";
        $npcLocalData["appearance"] = "{$npc["appearance"]}";
        $npcLocalData["is_rolemastered"] = "{$npc["appearance"]}";
        $npcLocalData["goals"] = "";
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
            if (isset($quest_data["items"])) {
                foreach ($quest_data["items"] as $item_key => $item) {
                    if ($item_key == $quest_data["topics"][$topic_ref]["item"]) {
                        // Topic mention an item
                        $npcLocalData["goals"] .= "Related info: {$item["name"]}, {$item["description"]}";
                    }
                }
            }

        }
        $npcLocalData["goals"] .= "{$npc["goal"]}";

        if (isset($quest_data["items"])) {
            foreach ($quest_data["items"] as $item) {
                if ($item["npc_ref"] == $npc_ref) {
                    if ($item["location"] == "pocket") {
                        // Add to inventory
                        //$npcLocalData["personality"] .= "\nInitially owns '{$item["name"]}'\n";
                    }


                }
            }
        }

        $metaData = $npcMaster->getMetadata($npcLocalData);
        $extData = $npcMaster->getExtendedData($npcLocalData);

        $metaData["is_rolemastered"] = true;
        $metaData["gps_track"] = true;
        $extData["background_life_enabled"] = true;
        $extData["background_life_last_updated"] = $GLOBALS["last_gamets"];
        $extData["middle_term_enabled"] = 1;
        $extData["starring_in_quest"] = $quest_id;
        $npcLocalData["dynamic_profile"] = 1;

        $npcLocalData["speechstyle"] = getSpeechStyleText(strtolower($npc["race"]), strtolower($npc["class"])) ?? $npc["speechStyle"];
        $npcLocalData["speechstyle"] .= getRandomSpeechFillers();
        $npcLocalData = $npcMaster->setMetadata($npcLocalData, $metaData);
        $npcLocalData = $npcMaster->setExtendedData($npcLocalData, $extData);
        $npcMaster->updateByArray($npcLocalData);

        error_log("[CheckNPCSpawn]\t{$npc["name"]} spawned");

        // Only move to player if location is nearby
        if ($npc["location"] == "nearby") {
            // Set Alerted if nearby
            if (isset($npc["disposition"])) {
                if (in_array($npc["disposition"], ["aggressive"])) {

                    $skyrimCmd = new SkyrimCommandBuilder();
                    $json = $skyrimCmd->Actor->SetAlert("0x{$npcLocalData["refid"]}", 1);
                    $skyrimCmd->send($json);

                    $GLOBALS["db"]->insert(
                        'responselog',
                        [
                            'localts' => time(),
                            'sent' => 0,
                            'actor' => "rolemaster",
                            'text' => "",
                            'action' => "rolecommand|moveToPlayer@{$npc["name"]}@$quest_id@9", // intent 9 = snqe s&d
                            'tag' => "",
                        ]
                    );
                } else if (in_array($npc["disposition"], ["dead"])) {

                    $skyrimCmd = new SkyrimCommandBuilder();
                    $json = $skyrimCmd->Actor->Kill("0x{$npcLocalData["refid"]}");
                    $skyrimCmd->send($json);

                } else {
                    $GLOBALS["db"]->insert(
                        'responselog',
                        [
                            'localts' => time(),
                            'sent' => 0,
                            'actor' => "rolemaster",
                            'text' => "",
                            'action' => "rolecommand|moveToPlayer@{$npc["name"]}@$quest_id@5", // intent 5 = snqe spawned.
                            'tag' => "",
                        ]
                    );
                }
            }
        } else {
            // Teleport to starting point.
            $startingPointDec=getLocationReferences($npc["location"]);
            $GLOBALS['db']->insert(
                'responselog',
                [
                    'localts' => time(),
                    'sent' => 0,
                    'actor' => 'rolemaster',
                    'text' => '',
                    'action' => "rolecommand|BackgroundCmd@{$npcLocalData["refid"]}@Teleport/$startingPointDec",
                    'tag' => __FILE__ . ':' . __LINE__,
                ]
            );
        }
        
        if (isset($npc["disposition"])) {
            if (in_array($npc["disposition"], ["dead"])) {

                $skyrimCmd = new SkyrimCommandBuilder();
                $json = $skyrimCmd->Actor->Kill("0x{$npcLocalData["refid"]}");
                $skyrimCmd->send($json);

            } else {
                // Will order to move to player if disposition is not aggressive or dead
                // Why? maybe its just patrolling remote location ...review
                /*
                $GLOBALS["db"]->insert(
                    'responselog',
                    [
                        'localts' => time(),
                        'sent' => 0,
                        'actor' => "rolemaster",
                        'text' => "",
                        'action' => "rolecommand|moveToPlayer@{$npc["name"]}@$quest_id@5", // intent 5 = snqe spawned.
                        'tag' => "",
                    ]
                );
                */
            }
        }


    } else {
        $isSpawned = false;
    }
    // TODO: replace with actual engine response

    if ($isSpawned) {
        $npc["spawned"] = "done";
        $quest_data["npcs"][$npc_ref] = $npc;
        SNQEQuestManager::updateQuestData($quest_id, ["npcs" => $quest_data["npcs"]]);

        // Enhance NPC profile with more details from quest state (topics, items, etc)
        enhanceProfileUsingQuestData($quest_data,$npc);

        return "done";
    }

    // If exceeded max attempts, fail
    if ($npc["spawn_attempts"] >= $maxAttempts) {
        $npc["spawn_failed"] = true;
        $quest_data["npcs"][$npc_ref] = $npc;
        SNQEQuestManager::updateQuestData($quest_id, ["npcs" => $quest_data["npcs"]]);
        error_log("[CheckNPCSpawn]\tNPC didn't spawn after $maxAttempts spawn_attempts");
        return "failed";
    }

    // Still waiting
    $quest_data["npcs"][$npc_ref] = $npc;
    error_log("[CheckNPCSpawn]\tNPC didn't spawn after {$npc["spawn_attempts"]} spawn_attempts");
    SNQEQuestManager::updateQuestData($quest_id, ["npcs" => $quest_data["npcs"]]);
    return "pending";
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

    if (!$quest) {
        throw new Exception("Quest '$quest_id' does not exist.");
    }

    $quest_data = $quest["quest_data"] ?? [];

    if (!isset($quest_data["items"][$item_ref])) {
        throw new Exception("Item '$item_ref' not declared. Use CreateItem first.");
    }

    $item = $quest_data["items"][$item_ref];

    // Check if spawned on previous session.
    $cn = $GLOBALS["db"]->escape($item["name"]);
    error_log("select count(*) as n from eventlog where type='status_msg'
     and data like '%spawned%@$cn%success%'");
    $spawned = $GLOBALS["db"]->fetchAll("select count(*) as n ,data from eventlog where type='status_msg'
     and data like '%spawned%@$cn%success%' group by data");

    if ($spawned && ($spawned[0]["n"])) {
        $msg = explode("@", $spawned[0]["data"]);

        error_log("[CheckItemSpawn] Item <{$item["name"]}> already spawned");
        $item["spawned"] = "done";
        $item["refid"] = $msg[3]; // Store refid
        $quest_data["items"][$item_ref] = $item;
        SNQEQuestManager::updateQuestData($quest_id, ["items" => $quest_data["items"]]);

        return "done";
    }

    // If already spawned, skip
    if (!empty($item["spawned"])) {
        error_log("[SpawnItem] Spawned: <{$item["spawned"]}> <{$item["type"]}> <{$item["name"]}>, <{$item["location"]}>, <{$item["description"]}>, $quest_id)");
        return;
    }

    // Mark as pending spawn
    $item["spawned"] = "pending";
    $item["spawn_attempts"] = 0;
    $item["spawn_target"] = $char_ref ?? "world"; // world or NPC inventory

    // Placeholder: call to Skyrim engine
    // SkyrimAPI::spawnItem($item_ref, $char_ref);
    error_log("[SpawnItem] {$item["type"]} {$item["name"]}, {$item["location"]}, {$item["description"]}, $quest_id)" . PHP_EOL);

    // Get NPC name if npc_ref is set
    $npc_name = null;
    if ($item["npc_ref"] && isset($quest_data["npcs"][$item["npc_ref"]])) {
        $npc_name = $quest_data["npcs"][$item["npc_ref"]]["name"];
        $item["location"] = "pocket";
    }

    SkCreateItem($item["type"], $item["name"], $item["location"], $item["description"], $quest_id, $npc_name);

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

    if (!$quest) {
        throw new Exception("Quest '$quest_id' does not exist.");
    }

    $quest_data = $quest["quest_data"] ?? [];

    if (!isset($quest_data["items"][$item_ref])) {
        throw new Exception("Item '$item_ref' not declared. Use CreateItem first.");
    }

    $item = $quest_data["items"][$item_ref];

    $cn = $GLOBALS["db"]->escape($item["name"]);
    error_log("select count(*) as n from eventlog where type='status_msg'
     and data like '%spawned%@$cn%success%'");
    $spawned = $GLOBALS["db"]->fetchAll("select count(*) as n from eventlog where type='status_msg'
     and data like '%spawned%@$cn%success%' ");

    $isSpawned = false; // <-- stub

    // Pending, deal with timestamps
    if (!($spawned[0]["n"])) {
        error_log("[CheckItemSpawn] Item <{$item["name"]}> not spawned after <{$item["spawn_attempts"]}>");
        $item["spawn_attempts"] = ($item["spawn_attempts"] ?? 0) + 1;
        $quest_data["items"][$item_ref] = $item;

        SNQEQuestManager::updateQuestData($quest_id, ["items" => $quest_data["items"]]);

        if ($item["spawn_attempts"] % 10 == 0) {
            $npc_name = null;
            if ($item["npc_ref"] && isset($quest_data["npcs"][$item["npc_ref"]])) {
                $npc_name = $quest_data["npcs"][$item["npc_ref"]]["name"];
                $item["location"] = "pocket";
            }
            error_log("[CheckItemSpawn] SkCreateItem({$item["type"]}, {$item["name"]}, {$item["location"]}, {$item["description"]}, $quest_id, $npc_name);");
            SkCreateItem($item["type"], $item["name"], $item["location"], $item["description"], $quest_id, $npc_name);
        }

        return "pending";
    } else {
        // Store in-game refid
        //spawned_item@Ancient Tome of Doomstone@success@Xalara of the Crimson Cloak@-16773743

        $spawnedDet = $GLOBALS["db"]->fetchOne("select data from eventlog where type='status_msg'
        and data like '%spawned%@$cn%success%' order by gamets desc");
        $details = explode("@", $spawnedDet["data"]);
        $item["int_refid"] = $details[4];
        $quest_data["items"][$item_ref] = $item;
        SNQEQuestManager::updateQuestData($quest_id, ["items" => $quest_data["items"]]);

        $isSpawned = true;
    }

    // If already confirmed, return "done"
    if (!empty($item["spawned"]) && $item["spawned"] === "done") {
        return "done";
    }

    // Increment attempts
    $item["spawn_attempts"] = ($item["spawn_attempts"] ?? 0) + 1;

    // Placeholder: real Skyrim check
    // $isSpawned = SkyrimAPI::checkItemSpawned($item_ref, $item["spawn_target"]);

    if ($isSpawned) {
        error_log("[CheckItemSpawn] Item <$cn> spawned");
        $item["spawned"] = "done";
        $quest_data["items"][$item_ref] = $item;
        SNQEQuestManager::updateQuestData($quest_id, ["items" => $quest_data["items"]]);
        return "done";
    }

    // Exceeded attempts → failure
    if ($item["spawn_attempts"] >= $maxAttempts) {
        $item["spawned"] = "failed";
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
 * SNQE MoveToPlayer function
 *
 * Orders an NPC to move to the player.
 *
 * @param string $quest_id Unique quest identifier
 * @param string $npc_ref NPC reference ID (from CreateNPC) to move to player
 * @param bool $follow Optional, whether NPC should follow the player (default false)
 * @return void
 */
function MoveToPlayer(
    string $quest_id,
    string $npc_ref,
    bool $follow = false
): void {
    // Fetch quest state
    $quest = SNQEQuestManager::getQuest($quest_id);

    if (!$quest) {
        throw new Exception("Quest '$quest_id' does not exist.");
    }

    $quest_data = $quest["quest_data"] ?? [];

    if (!isset($quest_data["npcs"][$npc_ref])) {
        throw new Exception("NPC '$npc_ref' not declared. Use CreateNPC first.");
    }

    $npc = $quest_data["npcs"][$npc_ref];

    // If already moved, skip (idempotent)
    if (!empty($npc["moved_to_player"])) {
        error_log("[MoveToPlayer]\tNPC <{$npc["name"]}> already commanded to move to player");
        return;
    }

    // Mark as pending movement
    $npc["moved_to_player"] = "pending";
    $npc["following_player"] = $follow;
    $npc["move_attempts"] = 0;

    // Insert command to move NPC to player
    $followStr = $follow ? "true" : "false";
    error_log("[MoveToPlayer]\tOrdering NPC <{$npc["name"]}> to move to player (follow=$followStr) for quest $quest_id");

    $GLOBALS["db"]->insert(
        'responselog',
        [
            'localts' => time(),
            'sent' => 0,
            'actor' => "rolemaster",
            'text' => "",
            'action' => "rolecommand|moveToPlayer@{$npc["name"]}@$quest_id@" . ($follow ? 7 : 0), // intent 7 = follow player,0 none
            'tag' => "",
        ]
    );

    // Save state
    $quest_data["npcs"][$npc_ref] = $npc;
    SNQEQuestManager::updateQuestData($quest_id, ["npcs" => $quest_data["npcs"]]);
}

/**
 * SNQE TellTopicToPlayer function
 *
 * @param string $quest_id Unique quest identifier
 * @param string $npc_ref NPC reference (char_ref) delivering the topic. **Make sure NPC has been declared**
 * @param string $topic_ref Topic reference ID
 */
function TellTopicToPlayer(
    string $quest_id,
    string $npc_ref,
    string $topic_ref
) {
    // Fetch quest state
    $quest = SNQEQuestManager::getQuest($quest_id);

    if (!$quest) {
        throw new Exception("Quest '$quest_id' does not exist.");
    }

    $quest_data = $quest["quest_data"] ?? [];

    if (!isset($quest_data["npcs"][$npc_ref])) {
        if ($npc_ref == "player") {
            error_log("[TellTopicToPlayer] WARNING, topic teller is player Marking Topic delivered <$topic_ref>");
            return "done";
        } else
            throw new Exception("NPC '$npc_ref' not declared. Use CreateNPC first.");
    }

    if (!isset($quest_data["topics"][$topic_ref])) {
        throw new Exception("Topic '$topic_ref' not declared. Use CreateTopic first.");
    }

    $topic = $quest_data["topics"][$topic_ref];

    // If already delivered, skip
    if (!empty($topic["delivered"])) {
        error_log("[TellTopicToPlayer] Topic delivered <$topic_ref>");
        return "done";
    }

    $character = $quest_data["npcs"][$npc_ref]["name"];

    //
    $cnNpc = $GLOBALS["db"]->escape($character);
    $rows = $GLOBALS["db"]->fetchOne("select 1 as n,gamets from eventlog where type='death' and data like '%defeated $cnNpc%' order by gamets desc limit 1");

    if (isset($rows["n"])) {
        $topic["delivered"] = "failed";
        $quest_data["topics"][$topic_ref] = $topic;
        SNQEQuestManager::updateQuestData($quest_id, ["topics" => $quest_data["topics"]]);
        error_log("NPC $cnNpc is dead");
        return "done";
    }

    if (strpos($GLOBALS["actors_present"], $quest_data["npcs"][$npc_ref]["name"]) === false) {
        // NPC not present
        $attempts = (int) ($quest_data["topics"][$topic_ref]["attempts"] ?? 0);
        error_log("[TellTopicToPlayer] Topic <$topic_ref> NPC <{$quest_data["npcs"][$npc_ref]["name"]}>  not present, close npcs: {$GLOBALS["actors_present"]}, {$attempts}");
        $quest_data["topics"][$topic_ref]["attempts"] = $attempts + 1;

        if ($quest_data["topics"][$topic_ref]["attempts"] % 10 == 0) { // Will move to player every 10 attempts if actor still not present.
            $npcMaster = new NpcMaster();
            $currentNpcData = $npcMaster->getByName($quest_data["npcs"][$npc_ref]["name"]);
            $unsignedInt = hexdec($currentNpcData["refid"]) & 0xFFFFFFFF;
            $refHexString = "0x" . str_pad(dechex($unsignedInt), 8, "0", STR_PAD_LEFT);

            $GLOBALS["db"]->insert(
                'responselog',
                [
                    'localts' => time(),
                    'sent' => 0,
                    'actor' => "rolemaster",
                    'text' => "",
                    'action' => "rolecommand|BackgroundCmd@$refHexString@MoveToPlayer",
                    'tag' => '',
                ]
            );
        }

        $GLOBALS["db"]->upsertRowOnConflict(
            'conf_opts',
            array(
                'id' => "snqe_pending_step",
                'value' => "Waiting for {$quest_data["npcs"][$npc_ref]["name"]}"
            ),
            "id"
        );
        SNQEQuestManager::updateQuestData($quest_id, $quest_data);
        return "pending";

    }

    // Mark as pending delivery
    $topic["delivered"] = "pending";
    $topic["delivery_attempts"] = 0;
    $topic["giver"] = $npc_ref; // force-set giver NPC
    $topic["target"] = "player"; // player is always target here

    // Placeholder: Skyrim engine command
    // SkyrimAPI::npcTellTopicToPlayer($npc_ref, $topic_ref);

    $hintData = ("{$quest_data["npcs"][$npc_ref]["name"]} should talk to {$GLOBALS["PLAYER_NAME"]} about this topic: \"{$quest_data["topics"][$topic_ref]["info"]}\", but using own words and speech style, and following current dialogue context. If topic already said, just follow up");

    if (isset($quest_data["items"])) {
        foreach ($quest_data["items"] as $item_key => $item) {
            if ($item_key == $quest_data["topics"][$topic_ref]["item"]) {
                // Topic mention an item
                $hintData .= "Related info: {$item["name"]}, {$item["description"]}";

            }
        }
    }

    $suggestionText = make_replacements("$hintData");

    $GLOBALS["db"]->insert(
        'responselog',
        [
            'localts' => time(),
            'sent' => 0,
            'actor' => "rolemaster",
            'text' => "",
            'action' => "rolecommand|Suggestion@{$quest_data["npcs"][$npc_ref]["name"]}@$suggestionText@$quest_id",
            'tag' => "",
        ]
    );
    // Mark when topic is delivered
    $topic["created"] = time();
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

    if (!$quest) {
        throw new Exception("Quest '$quest_id' does not exist.");
    }

    $quest_data = $quest["quest_data"] ?? [];

    if (!isset($quest_data["topics"][$topic_ref])) {
        throw new Exception("Topic '$topic_ref' not declared. Use CreateTopic first.");
    }

    $topic = $quest_data["topics"][$topic_ref];

    // If already delivered
    if (!empty($topic["delivered"]) && $topic["delivered"] === "done") {
        error_log("[CheckTopicToPlayer]\t<$topic_ref> already done");
        return "done";
    }

     // If not  delivered
    if (!empty($topic["delivered"]) && $topic["delivered"] === "failed") {
        error_log("[CheckTopicToPlayer]\t<$topic_ref> failed!");
        return "done";   // Treat this as a terminal state for now; the current LLM-driven quest flow does not yet support conditional recovery or alternate branching after a topic delivery failure.
    }

    // If not important, return success on first call
    if (empty($topic["important"])) {
        error_log("[CheckTopicToPlayer]\tTopic <$topic_ref> is not important, marking as done");
        $topic["delivered"] = "done";
        $quest_data["topics"][$topic_ref] = $topic;
        SNQEQuestManager::updateQuestData($quest_id, ["topics" => $quest_data["topics"]]);
        return "done";
    }

    if (empty($topic["created"])) {
        error_log("[CheckTopicToPlayer]\tTopic <$topic_ref> has not been created yet. Waiting.");
        return "pending";
    }

    if ((time() - $topic["created"]) < 10) {
        error_log("[CheckTopicToPlayer]\tTopic <$topic_ref> was created less than 10 seconds ago. Skipping check");
        return "pending";
    }

    // Increment attempts
    $topic["delivery_attempts"] = ($topic["delivery_attempts"] ?? 0) + 1;

    // Placeholder: Skyrim engine check
    // $isDelivered = SkyrimAPI::checkNPCToldTopicToPlayer($topic["giver"], $topic_ref);

    $character = $quest_data["npcs"][$topic["giver"]];

    //
    $cnNpc = $GLOBALS["db"]->escape($character["name"]);
    $rows = $GLOBALS["db"]->fetchOne("select 1 as n,gamets from eventlog where type='death' and (data like '%defeated $cnNpc%' or data like '%killed $cnNpc%') order by gamets desc limit 1");

    if (isset($rows["n"])) {
        $topic["delivered"] = "failed";
        $quest_data["topics"][$topic_ref] = $topic;
        SNQEQuestManager::updateQuestData($quest_id, ["topics" => $quest_data["topics"]]);
        error_log("NPC is dead");
        return "done";
    }

    if (!isset($quest_data["last_llm_call"])) {
        $quest_data["last_llm_call"] = 0;
    }

    $res = SkTopicCheck($character["name"], $topic["info"], $quest_data["last_llm_call"], $topic["delivery_attempts"], $quest_id);

    if ($res == TOPIC_COVERED) {
        $isDelivered = true; // stub
        $quest_data["last_llm_call"] = time();

    } else if ($res == TOPIC_UNCOVERED) {
        $isDelivered = false; // stub
        $quest_data["last_llm_call"] = time();

    } else if ($res == WILL_DO_LATER) {
        $isDelivered = false; // stub
    } else if ($res == TOPIC_TOOEARLY) {
        $isDelivered = false; // stub
    }

    SNQEQuestManager::updateQuestData($quest_id, ["last_llm_call" => $quest_data["last_llm_call"]]);

    if ($isDelivered) {
        $topic["delivered"] = "done";
        $quest_data["topics"][$topic_ref] = $topic;
        SNQEQuestManager::updateQuestData($quest_id, ["topics" => $quest_data["topics"]]);
        return "done";
    }

    // Exceeded attempts → failure
    if ($topic["delivery_attempts"] >= $maxAttempts) {
        $topic["delivered"] = "failed";
        $quest_data["topics"][$topic_ref] = $topic;
        SNQEQuestManager::updateQuestData($quest_id, ["topics" => $quest_data["topics"]]);
        return "failed";
    }

    // Still pending
    $quest_data["topics"][$topic_ref] = $topic;
    SNQEQuestManager::updateQuestData($quest_id, ["topics" => $quest_data["topics"]]);

    $GLOBALS["db"]->upsertRowOnConflict(
        'conf_opts',
        array(
            'id' => "snqe_pending_step",
            'value' => "Waiting for topic: '{$topic["name"]}' by '{$quest_data["npcs"][$topic["giver"]]["name"]}'"
        ),
        "id"
    );
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

    if (!$quest) {
        throw new Exception("Quest '$quest_id' does not exist.");
    }

    $quest_data = $quest["quest_data"] ?? [];

    if (!isset($quest_data["items"][$item_ref])) {
        throw new Exception("Item '$item_ref' not declared. Use CreateItem first.");
    }

    $item = $quest_data["items"][$item_ref];

    // If already recovered, return success
    if (!empty($item["recovered"]) && $item["recovered"] === "done") {
        return "done";
    }

    // Increment recovery attempts
    $item["recover_attempts"] = ($item["recover_attempts"] ?? 0) + 1;

    // Query event log to see if player has the item (stub integration with Skyrim engine)
    $cn = $GLOBALS["db"]->escape($item["name"]);
    $rows = $GLOBALS["db"]->fetchAll("select count(*) as n from eventlog where (type='itemfound' and data like '%$cn%') or (type='infoaction' and data like '%picks up $cn%')");

    $hasItem = false;
    if (is_array($rows) && isset($rows[0]) && $rows[0]["n"] > 0) {
        $hasItem = true;
    }

    if ($hasItem) {
        error_log("[WaitToItemBeRecovered] Item <$cn> recovered by player");
        $item["recovered"] = "done";
        $quest_data["items"][$item_ref] = $item;
        SNQEQuestManager::updateQuestData($quest_id, ["items" => $quest_data["items"]]);
        return "done";
    }

    $sceneNoteDataEsc = $GLOBALS["db"]->escape("#Storyline: {$GLOBALS["PLAYER_NAME"]} and companions must recover item <{$item["name"]}>");
    $sceneNoteData = "#Storyline: {$GLOBALS["PLAYER_NAME"]} and companions  must recover item <{$item["name"]}>";

    // Check if row with same data already exists
    $existingRow = $GLOBALS["db"]->fetchOne(
        "SELECT rowid FROM rolemaster WHERE type = 'scenenote' AND data = '$sceneNoteDataEsc' LIMIT 1"
    );
    error_log("SELECT rowid FROM rolemaster WHERE type = 'scenenote' AND data = '$sceneNoteDataEsc' LIMIT 1");
    if ($existingRow) {
        // Update existing row's localts
        $GLOBALS["db"]->update(
            'rolemaster',
            'localts=' . time(),
            "rowid = {$existingRow["rowid"]}"
        );
    } else {
        // Insert new row
        $GLOBALS["db"]->insert(
            'rolemaster',
            [
                'localts' => time(),
                'ttl' => 25,
                'type' => "scenenote",
                'data' => $sceneNoteData,
            ]
        );
    }
    if ($item["recover_attempts"] == 1) {
        // First time, so we update Quest Tracker
        // Convert signed int32 to HEX
        $formId = convertSignedToUnsignedHex($item["int_refid"]);

        $GLOBALS["db"]->insert(
            'responselog',
            [
                'localts' => time(),
                'sent' => 0,
                'actor' => "rolemaster",
                'text' => "",
                'action' => "rolecommand|QuestTrackReference@{$formId}",
                'tag' => "",
            ]
        );

    }

    if ($item["recover_attempts"] % 25 == 0) {
        // Updating time, so we update Quest Tracker
        // Convert signed int32 to HEX
        $formId = convertSignedToUnsignedHex($item["int_refid"]);

        $GLOBALS["db"]->insert(
            'responselog',
            [
                'localts' => time(),
                'sent' => 0,
                'actor' => "rolemaster",
                'text' => "",
                'action' => "rolecommand|QuestTrackReference@{$formId}",
                'tag' => "",
            ]
        );

    }
    // Exceeded attempts → failure
    if ($item["recover_attempts"] >= $maxAttempts) {
        $item["recovered"] = "failed";
        $quest_data["items"][$item_ref] = $item;
        SNQEQuestManager::updateQuestData($quest_id, ["items" => $quest_data["items"]]);
        error_log("[WaitToItemBeRecovered] Item <$cn> recovery failed after $maxAttempts attempts");
        return "failed";
    }

    if ($item["recover_attempts"] % 12 == 0) {
        // Send hints here

    }

    // Still pending
    error_log("[WaitToItemBeRecovered] Item <$cn> not yet recovered (attempt {$item["recover_attempts"]})");
    $item["recovered"] = "pending";
    $quest_data["items"][$item_ref] = $item;
    SNQEQuestManager::updateQuestData($quest_id, ["items" => $quest_data["items"]]);
    $GLOBALS["db"]->upsertRowOnConflict(
        'conf_opts',
        array(
            'id' => "snqe_pending_step",
            'value' => "Waiting for player to get item <{$item["name"]}>"
        ),
        "id"
    );
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

    if (!$quest) {
        throw new Exception("Quest '$quest_id' does not exist.");
    }

    $quest_data = $quest["quest_data"] ?? [];

    if (!isset($quest_data["items"][$item_ref])) {
        throw new Exception("Item '$item_ref' not declared. Use CreateItem first.");
    }

    if (!isset($quest_data["npcs"][$char_ref])) {
        throw new Exception("NPC '$char_ref' not declared. Use CreateNPC first.");
    }

    $item = $quest_data["items"][$item_ref];
    $npc = $quest_data["npcs"][$char_ref];

    // If already traded, return success
    if (!empty($item["traded"]) && $item["traded"] === "done") {
        error_log("[WaitToItemBeTraded] $item_ref traded");
        return "done";
    }

    // Increment trade attempts
    $item["trade_attempts"] = ($item["trade_attempts"] ?? 0) + 1;

    if (empty($item["trade_start_gamets"])) {
        $item["trade_start_gamets"] = $GLOBALS["last_gamets"];
    }

    // Query event log to see if player traded the item to the NPC
    $cnItem = $GLOBALS["db"]->escape($item["name"]);
    $cnNpc = $GLOBALS["db"]->escape($npc["name"]);

    $rows = $GLOBALS["db"]->fetchAll("select count(*) as n from eventlog where type='itemfound'
    and data like '%gave%$cnItem%$cnNpc%' ");

    $wasTraded = false;
    if (is_array($rows) && isset($rows[0]) && $rows[0]["n"] > 0) {
        $wasTraded = true;
    }

    if ($wasTraded) {
        error_log("[WaitToItemBeTraded] Item <$cnItem> successfully traded to NPC <$cnNpc>");
        $item["traded"] = "done";
        $quest_data["items"][$item_ref] = $item;
        SNQEQuestManager::updateQuestData($quest_id, ["items" => $quest_data["items"]]);
        return "done";
    }

    $sceneNoteDataEsc = $GLOBALS["db"]->escape("#Storyline: {$GLOBALS["PLAYER_NAME"]} must give item <{$item["name"]}> to {$npc["name"]}");
    $sceneNoteData = "#Storyline: {$GLOBALS["PLAYER_NAME"]} must give item <{$item["name"]}> to {$npc["name"]}";

    // Check if row with same data already exists
    $existingRow = $GLOBALS["db"]->fetchOne(
        "SELECT rowid FROM rolemaster WHERE type = 'scenenote' AND data = '$sceneNoteDataEsc' LIMIT 1"
    );
    error_log("SELECT rowid FROM rolemaster WHERE type = 'scenenote' AND data = '$sceneNoteDataEsc' LIMIT 1");
    if ($existingRow) {
        // Update existing row's localts
        $GLOBALS["db"]->update(
            'rolemaster',
            'localts=' . time(),
            "rowid = {$existingRow["rowid"]}"
        );
    } else {
        // Insert new row
        $GLOBALS["db"]->insert(
            'rolemaster',
            [
                'localts' => time(),
                'ttl' => 25,
                'type' => "scenenote",
                'data' => $sceneNoteData,
            ]
        );
    }

    // Exceeded attempts → failure
    if (($GLOBALS["last_gamets"] - $item["trade_start_gamets"]) > (_TRADE_TIMEOUT / 0.00864)) {
        $item["traded"] = "failed";
        $quest_data["items"][$item_ref] = $item;
        SNQEQuestManager::updateQuestData($quest_id, ["items" => $quest_data["items"]]);
        error_log("[WaitToItemBeTraded] Item <$cnItem> trade to <$cnNpc> failed after $maxAttempts attempts");
        return "failed";
    }

    // Still pending
    error_log("[WaitToItemBeTraded] Item <$cnItem> not yet traded to <$cnNpc> (attempt {$item["trade_attempts"]})");
    $item["traded"] = "pending";
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

    if (!$quest) {
        throw new Exception("Quest '$quest_id' does not exist.");
    }

    $quest_data = $quest["quest_data"] ?? [];

    if (!isset($quest_data["npcs"][$char_ref])) {
        throw new Exception("NPC '$char_ref' not declared. Use CreateNPC first.");
    }

    $npc = $quest_data["npcs"][$char_ref];

    // If already gone, return success
    if (!empty($npc["gone"])) {
        error_log("[ToGoAway] DONE <$quest_id> <$char_ref>");
        return "done";
    }

    // Increment attempts
    $npc["goaway_attempts"] = ($npc["goaway_attempts"] ?? 0) + 1;

    // First time → send command to engine
    if (!isset($npc["gone"]) || ($npc["gone"] !== "pending" && $npc["gone"] !== "done")) {
        $npc["gone"] = "pending";
        error_log("[ToGoAway] Sending NPC <{$npc["name"]}> away from quest <$quest_id>");

        $GLOBALS["db"]->insert(
            'responselog',
            [
                'localts' => time(),
                'sent' => 0,
                'actor' => "rolemaster",
                'text' => "",
                'action' => "rolecommand|Suggestion@{$npc["name"]}@should say goodbye@$quest_id",
                'tag' => "",
            ]
        );

        $GLOBALS["db"]->insert(
            'responselog',
            [
                'localts' => time(),
                'sent' => 0,
                'actor' => "rolemaster",
                'text' => "",
                'action' => "rolecommand|TravelTo@{$npc["name"]}@WIDeadBodyCleanupCell@$quest_id",
                'tag' => "",
            ]
        );

        $GLOBALS["db"]->insert(
            'actions_issued',
            [
                'action' => "ToGoAway",
                'fullcall' => "ToGoAway:Unknown place",
                'actorname' => $npc["name"],
                'ts' => $GLOBALS["last_ts"],
                'gamets' => $GLOBALS["gamets"],
                'localts' => time(),
                'original' => 'backgroundaction',
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

/**
 * SNQE WaitForCoins function
 *
 * Waits until the player gives a specified amount of gold to an NPC.
 *
 * @param string $quest_id Unique quest identifier
 * @param string $npc_ref NPC reference ID (from CreateNPC)
 * @param int $amount Amount of gold to receive
 * @param int $maxAttempts Maximum retries before failure (default 10)
 * @return string "done"|"failed"|"pending"
 */
function WaitForCoins(
    string $quest_id,
    string $npc_ref,
    int $amount,
    int $maxAttempts = 100
): string {
    // Fetch quest state
    $quest = SNQEQuestManager::getQuest($quest_id);

    if (!$quest) {
        throw new Exception("Quest '$quest_id' does not exist.");
    }

    $quest_data = $quest["quest_data"] ?? [];

    if (!isset($quest_data["npcs"][$npc_ref])) {
        throw new Exception("NPC '$npc_ref' not declared. Use CreateNPC first.");
    }

    $npc = $quest_data["npcs"][$npc_ref];

    // Initialize coins tracking
    if (!isset($npc["coins_received"])) {
        $npc["coins_received"] = 0;
    }

    // Check if already done
    if ($npc["coins_received"] >= $amount) {
        error_log("[WaitForCoins]\t<$npc_ref>");
        return "done";

    }

    // Increment attempts
    $npc["coins_attempts"] = ($npc["coins_attempts"] ?? 0) + 1;

    // Query event log to see if player gave coins
    $cnNpc = $GLOBALS["db"]->escape($npc["name"]);
    error_log("Check if character $cnNpc has received gold ");

    $rows = $GLOBALS["db"]->fetchAll("SELECT data,GREATEST(
        -- First try to get the explicit value after 'value'
        (regexp_matches(data, 'value (\d+) gold'))[1]::int,
        -- If no explicit value, get the number before 'Gold'
        (regexp_matches(data, '(\d+) Gold'))[1]::int
    ) AS gold_value
FROM eventlog
WHERE type='itemfound' and (data like '%gave%Gold%to%{$cnNpc}%' or data like '%gave%to%{$cnNpc}%')"); //Check for amount

    if (is_array($rows) && isset($rows[0])) {
        $received = (int) $rows[0]["gold_value"];
    } else {
        $received = 0;
    }

    $npc["coins_received"] = $received;

    if ($received >= $amount) {
        $npc["coins_status"] = "done";
        $quest_data["npcs"][$npc_ref] = $npc;
        SNQEQuestManager::updateQuestData($quest_id, ["npcs" => $quest_data["npcs"]]);
        error_log("[WaitForCoins] NPC <$cnNpc> received $received coins (target $amount)");
        return "done";
    }

    // Exceeded attempts → failure
    if ($npc["coins_attempts"] >= $maxAttempts) {
        $npc["coins_status"] = "failed";
        $quest_data["npcs"][$npc_ref] = $npc;
        SNQEQuestManager::updateQuestData($quest_id, ["npcs" => $quest_data["npcs"]]);
        error_log("[WaitForCoins] NPC <$cnNpc> did not receive enough coins after $maxAttempts attempts");
        return "failed";
    }

    // Still pending
    $npc["coins_status"] = "pending";
    $quest_data["npcs"][$npc_ref] = $npc;
    SNQEQuestManager::updateQuestData($quest_id, ["npcs" => $quest_data["npcs"]]);
    error_log("[WaitForCoins] NPC <$cnNpc> has received $received/$amount coins (attempt {$npc['coins_attempts']})");
    return "pending";
}

/**
 * SNQE CompleteQuest function
 *
 * Marks a quest as finished and updates its state.
 *
 * @param string $quest_id Unique quest identifier
 * @param string|null $result Optional description or outcome of the quest
 * @return void
 */
function CompleteQuest(
    string $quest_id,
    ?string $result = null
): void {
    // Fetch quest state
    $quest = SNQEQuestManager::getQuest($quest_id);

    if (!$quest) {
        error_log("[CompleteQuest]\t$quest_id  does not exist.");
        throw new Exception("Quest '$quest_id' does not exist.");
    }

    // Check if player has spoken after the last event was delivered
    // Query the speech table to verify player has spoken after the quest's lastgamets timestamp
    $quest_data = $quest["quest_data"] ?? [];
    $quest_lastgamets = $quest_data["last_llm_call_topic"] ?? 0;

    $playerName = $GLOBALS["db"]->escape($GLOBALS["PLAYER_NAME"]);
    $playerSpokenAfter = $GLOBALS["db"]->fetchOne(
        "SELECT gamets FROM speech WHERE speaker = '$playerName' AND gamets > $quest_lastgamets ORDER BY gamets DESC LIMIT 1"
    );
    error_log("SELECT gamets FROM speech WHERE speaker = '$playerName' AND gamets > $quest_lastgamets ORDER BY gamets DESC LIMIT 1");
    if (!$playerSpokenAfter) {
        error_log("[CompleteQuest]\tPlayer has not spoken yet after quest event. Deferring completion.");
        $GLOBALS["db"]->upsertRowOnConflict(
            'conf_opts',
            array(
                'id' => "snqe_pending_step",
                'value' => "Waiting for player to speak after quest completion event"
            ),
            "id"
        );
        return;
    }

    if ($quest["quest_run_state"] == "finished") {
        error_log("[CompleteQuest]\t$quest_id is completed");
        return;
    }

    // Log completion
    $GLOBALS["db"]->insert(
        'responselog',
        [

            'localts' => time(),
            'sent' => 0,
            'actor' => "rolemaster",
            'text' => "",
            'action' => "rolecommand|EndQuest@{$quest["title"]}@$quest_id",
            'tag' => "",

        ]
    );

    SNQEQuestManager::updateQuestState($quest_id, 'finished');
    $GLOBALS["db"]->upsertRowOnConflict(
        'conf_opts',
        array(
            'id' => "snqe_pending_step",
            'value' => ""
        ),
        "id"
    );
    error_log("[CompleteQuest]\tQuest '$quest_id' marked as finished");
}

/**
 * SNQE CombatPlayer function
 *
 * Orders an NPC to engage the player in combat.
 *
 * @param string $quest_id Unique quest identifier
 * @param string $npc_ref NPC reference ID (from CreateNPC) to initiate combat
 * @return void
 */
function CombatPlayer(
    string $quest_id,
    string $npc_ref
): void {
    // Fetch quest state
    $quest = SNQEQuestManager::getQuest($quest_id);

    if (!$quest) {
        throw new Exception("Quest '$quest_id' does not exist.");
    }

    $quest_data = $quest["quest_data"] ?? [];

    if (!isset($quest_data["npcs"][$npc_ref])) {
        throw new Exception("NPC '$npc_ref' not declared. Use CreateNPC first.");
    }

    $npc = $quest_data["npcs"][$npc_ref];

    // If already in combat, skip (idempotent)
    if (!empty($npc["in_combat"])) {
        error_log("[CombatPlayer]\tNPC <{$npc["name"]}> already engaged in combat");
        return;
    }
    // Check if NPC is present, if not return early
    if (strpos($GLOBALS["actors_present"], $npc["name"]) === false) {
        error_log("[CombatPlayer]\tNPC <{$npc["name"]}> not currently present, deferring combat");
        return;
    }

    // Mark as pending combat
    $npc["in_combat"] = "pending";
    $npc["combat_attempts"] = 0;
    $npc["combat_start_time"] = time();

    // Insert command to initiate combat
    error_log("[CombatPlayer]\tOrdering NPC <{$npc["name"]}> to engage player in combat for quest $quest_id");

    $GLOBALS["db"]->insert(
        'responselog',
        [
            'localts' => time(),
            'sent' => 0,
            'actor' => "rolemaster",
            'text' => "",
            'action' => "rolecommand|CombatPlayer@{$npc["name"]}@$quest_id",
            'tag' => "",
        ]
    );

    // Save state
    $quest_data["npcs"][$npc_ref] = $npc;
    SNQEQuestManager::updateQuestData($quest_id, ["npcs" => $quest_data["npcs"]]);
}

/**
 * SNQE CombatNPC function
 *
 * Orders one NPC to engage another NPC in combat.
 *
 * @param string $quest_id Unique quest identifier
 * @param string $npc_ref_attacker NPC reference ID (from CreateNPC) to initiate combat
 * @param string $npc_ref_target NPC reference ID (from CreateNPC) to be attacked
 * @return void
 */
function CombatNPC(
    string $quest_id,
    string $npc_ref_attacker,
    string $npc_ref_target
): void {
    // Fetch quest state
    $quest = SNQEQuestManager::getQuest($quest_id);

    if (!$quest) {
        throw new Exception("Quest '$quest_id' does not exist.");
    }

    $quest_data = $quest["quest_data"] ?? [];

    if (!isset($quest_data["npcs"][$npc_ref_attacker])) {
        throw new Exception("NPC '$npc_ref_attacker' not declared. Use CreateNPC first.");
    }

    if (!isset($quest_data["npcs"][$npc_ref_target])) {
        if ($npc_ref_target == "player") {
            ;
        } else
            throw new Exception("NPC '$npc_ref_target' not declared. Use CreateNPC first.");
    }

    $npc_attacker = $quest_data["npcs"][$npc_ref_attacker];
    $npc_target = $quest_data["npcs"][$npc_ref_target];

    // If already in combat, skip (idempotent)
    if (!empty($npc_attacker["in_npc_combat"])) {
        error_log("[CombatNPC]\tNPC <{$npc_attacker["name"]}> already engaged in NPC combat");
        return;
    }

    // Insert command to initiate combat between NPCs
    error_log("[CombatNPC]\tOrdering NPC <{$npc_attacker["name"]}> to engage NPC <{$npc_target["name"]}> in combat for quest $quest_id");

    // Actual code here

    // Save state
    $quest_data["npcs"][$npc_ref_attacker] = $npc_attacker;
    $quest_data["npcs"][$npc_ref_target] = $npc_target;
    SNQEQuestManager::updateQuestData($quest_id, ["npcs" => $quest_data["npcs"]]);
}

/**
 * SNQE WaitforCombatEnd function
 *
 * Waits until combat between an NPC and the player has ended.
 *
 * @param string $quest_id Unique quest identifier
 * @param string $npc_ref NPC reference ID (from CreateNPC) engaged in combat
 * @param int $maxAttempts Maximum retries before failure (default 100)
 * @return string "done"|"failed"|"pending"
 */
function WaitforCombatEnd(
    string $quest_id,
    string $npc_ref,
    int $maxAttempts = 200
): string {
    // Fetch quest state
    $quest = SNQEQuestManager::getQuest($quest_id);

    if (!$quest) {
        throw new Exception("Quest '$quest_id' does not exist.");
    }

    $quest_data = $quest["quest_data"] ?? [];

    if (!isset($quest_data["npcs"][$npc_ref])) {
        throw new Exception("NPC '$npc_ref' not declared. Use CreateNPC first.");
    }

    $npc = $quest_data["npcs"][$npc_ref];

    // If combat already ended, return success
    if (!empty($npc["in_combat"]) && $npc["in_combat"] === "done") {
        error_log("[WaitforCombatEnd] Combat for <{$npc["name"]}> already ended");
        return "done";
    }

    // Increment combat check attempts
    $npc["combat_attempts"] = ($npc["combat_attempts"] ?? 0) + 1;

    // Query event log to check if combat has ended
    $cnNpc = $GLOBALS["db"]->escape($npc["name"]);
    $rows = $GLOBALS["db"]->fetchAll("select 1 as n,gamets from eventlog where type='death' and (data like '%defeated $cnNpc%' or data  like '%killed $cnNpc%' or data  like '%$cnNpc died%')  order by gamets desc limit 1");

    $combatEnded = false;
    if (is_array($rows) && isset($rows[0]) && $rows[0]["n"] > 0) {
        $combatEnded = true;
    }

    $rows = $GLOBALS["db"]->fetchAll("select 1 as n,gamets from eventlog where type='combatend' and (data like '%defeated $cnNpc%' or data  like '%killed $cnNpc%' or data  like '%$cnNpc died%')  order by gamets desc limit 1");

    if (is_array($rows) && isset($rows[0]) && $rows[0]["n"] > 0) {
        $combatEnded = true;
    }

    if ($combatEnded) {
        error_log("[WaitforCombatEnd] Combat for NPC <$cnNpc> has ended");
        $npc["in_combat"] = "done";
        $quest_data["npcs"][$npc_ref] = $npc;
        SNQEQuestManager::updateQuestData($quest_id, ["npcs" => $quest_data["npcs"]]);
        return "done";
    }

    // Exceeded attempts → failure
    if ($npc["combat_attempts"] >= $maxAttempts) {

        error_log("[WaitforCombatEnd] Combat for NPC <$cnNpc> did not end after $maxAttempts attempts");
        $npc["in_combat"] = "failed";
        $quest_data["npcs"][$npc_ref] = $npc;
        SNQEQuestManager::updateQuestData($quest_id, ["npcs" => $quest_data["npcs"]]);
        return "failed";
    }

    if ($npc["combat_attempts"] % 10 == 0) {
        $GLOBALS["db"]->insert(
            'responselog',
            [
                'localts' => time(),
                'sent' => 0,
                'actor' => "rolemaster",
                'text' => "",
                'action' => "rolecommand|CombatPlayer@{$npc["name"]}@$quest_id",
                'tag' => "",
            ]
        );
        // If not present
        $npcMaster = new NpcMaster();
        $npcLocalData = $npcMaster->GetByName($npc["name"]);
        $skyrimCmd = new SkyrimCommandBuilder();
        $json = $skyrimCmd->Actor->SetAlert("0x{$npcLocalData["refid"]}", 1);
        $skyrimCmd->send($json);

    }

    if ($npc["combat_attempts"] == 20) {

        $npcMaster = new NpcMaster();
        $npcLocalData = $npcMaster->GetByName($npc["name"]);

        $GLOBALS["db"]->insert(
            'responselog',
            [
                'localts' => time(),
                'sent' => 0,
                'actor' => "rolemaster",
                'text' => "",
                'action' => "rolecommand|QuestTrackReference@0x{$npcLocalData["refid"]}",
                'tag' => "",
            ]
        );

    }

    if ($npc["combat_attempts"] % 15 === 0) {
        error_log("[WaitforCombatEnd] {$GLOBALS["actors_present"]}");

        if (strpos($GLOBALS["actors_present"], $npc["name"]) !== false) {
            error_log("[WaitforCombatEnd] MOVING <$cnNpc> still ongoing (attempt {$npc["combat_attempts"]})");
            $GLOBALS["db"]->insert(
                'responselog',
                [
                    'localts' => time(),
                    'sent' => 0,
                    'actor' => "rolemaster",
                    'text' => "",
                    'action' => "rolecommand|moveToPlayer@{$npc["name"]}@$quest_id@9", // intent 5 = snqe spawned.
                    'tag' => "",
                ]
            );
        } else {
            // Review. This will move remove NPCs to player location, which can be out of roleplay

            error_log("[WaitforCombatEnd] {$GLOBALS["actors_present"]}");
            $npcMaster = new NpcMaster();
            $currentNpcData = $npcMaster->getByName($npc["name"]);
            $unsignedInt = hexdec($currentNpcData["refid"]) & 0xFFFFFFFF;
            $refHexString = "0x" . str_pad(dechex($unsignedInt), 8, "0", STR_PAD_LEFT);

            $GLOBALS["db"]->insert(
                'responselog',
                [
                    'localts' => time(),
                    'sent' => 0,
                    'actor' => "rolemaster",
                    'text' => "",
                    'action' => "rolecommand|BackgroundCmd@$refHexString@SeekAndKillPlayer",
                    'tag' => '',
                ]
            );

        }
    }
    // Still pending
    error_log("[WaitforCombatEnd] Combat for NPC <$cnNpc> still ongoing (attempt {$npc["combat_attempts"]})");
    $GLOBALS["db"]->upsertRowOnConflict(
        'conf_opts',
        array(
            'id' => "snqe_pending_step",
            'value' => "Defeat NPC <$cnNpc>"
        ),
        "id"
    );
    $npc["in_combat"] = "pending";
    $quest_data["npcs"][$npc_ref] = $npc;
    SNQEQuestManager::updateQuestData($quest_id, ["npcs" => $quest_data["npcs"]]);
    return "pending";
}

/**
 * SNQE WaitForNPCCombatEnd function
 *
 * Waits until combat between two NPCs has ended.
 *
 * @param string $quest_id Unique quest identifier
 * @param string $npc_ref_attacker NPC reference ID (from CreateNPC) engaged in combat
 * @param string $npc_ref_target NPC reference ID (from CreateNPC) target of combat
 * @param int $maxAttempts Maximum retries before failure (default 100)
 * @return string "done"|"failed"|"pending"
 */
function WaitForNPCCombatEnd(
    string $quest_id,
    string $npc_ref_attacker,
    string $npc_ref_target,
    int $maxAttempts = 100
): string {
    // Fetch quest state
    $quest = SNQEQuestManager::getQuest($quest_id);

    if (!$quest) {
        throw new Exception("Quest '$quest_id' does not exist.");
    }

    $quest_data = $quest["quest_data"] ?? [];

    if (!isset($quest_data["npcs"][$npc_ref_attacker])) {
        throw new Exception("NPC '$npc_ref_attacker' not declared. Use CreateNPC first.");
    }

    if (!isset($quest_data["npcs"][$npc_ref_target])) {
        throw new Exception("NPC '$npc_ref_target' not declared. Use CreateNPC first.");
    }

    $npc_attacker = $quest_data["npcs"][$npc_ref_attacker];
    $npc_target = $quest_data["npcs"][$npc_ref_target];

    // If combat already ended, return success
    if (!empty($npc_attacker["in_npc_combat"]) && $npc_attacker["in_npc_combat"] === "done") {
        error_log("[WaitForNPCCombatEnd] Combat between <{$npc_attacker["name"]}> and <{$npc_target["name"]}> already ended");
        return "done";
    }

    // Increment combat check attempts
    $npc_attacker["npc_combat_attempts"] = ($npc_attacker["npc_combat_attempts"] ?? 0) + 1;

    // Query event log to check if combat has ended
    // Check if either NPC was defeated/died
    $cnAttacker = $GLOBALS["db"]->escape($npc_attacker["name"]);
    $cnTarget = $GLOBALS["db"]->escape($npc_target["name"]);

    // Check for death events for either combatant
    $rows = $GLOBALS["db"]->fetchAll("select 1 as n,gamets from eventlog where type='death'
        and (data like '%defeated $cnAttacker%' or data like '%defeated $cnTarget%'
        or data like '%killed $cnTarget%' or data like '%killed $cnAttacker%' or data like '%$cnAttacker died%'
         or data like '%$cnTarget died%')
        order by gamets desc limit 1");

    $combatEnded = false;
    if (is_array($rows) && isset($rows[0]) && $rows[0]["n"] > 0) {
        $combatEnded = true;
    }

    // Query event log to check if combat has ended
    // Check if either NPC was defeated/died
    $cnAttacker = $GLOBALS["db"]->escape($GLOBALS["PLAYER_NAME"]);
    $cnTarget = $GLOBALS["db"]->escape($npc_target["name"]);

    // Check for death events for either combatant
    $rows = $GLOBALS["db"]->fetchAll("select 1 as n,gamets from eventlog where type='death'
        and (data like '%defeated $cnAttacker%' or data like '%defeated $cnTarget%'
        or data like '%killed $cnTarget%' or data like '%killed $cnAttacker%' or data like '%$cnAttacker died%'
         or data like '%$cnTarget died%')
        order by gamets desc limit 1");


    if (is_array($rows) && isset($rows[0]) && $rows[0]["n"] > 0) {
        $combatEnded = true;
    }

    if ($combatEnded) {
        error_log("[WaitForNPCCombatEnd] Combat between <$cnAttacker> and <$cnTarget> has ended");
        $npc_attacker["in_npc_combat"] = "done";
        $quest_data["npcs"][$npc_ref_attacker] = $npc_attacker;
        SNQEQuestManager::updateQuestData($quest_id, ["npcs" => $quest_data["npcs"]]);
        return "done";
    }

    $enemyNPC = $npc_attacker;

    if ($npc_target["disposition"] == "aggressive") {
        $enemyNPC = $npc_target;
    }

    if ($npc_attacker["npc_combat_attempts"] % 10 == 0) {
        $GLOBALS["db"]->insert(
            'responselog',
            [
                'localts' => time(),
                'sent' => 0,
                'actor' => "rolemaster",
                'text' => "",
                'action' => "rolecommand|CombatPlayer@{$enemyNPC["name"]}@$quest_id",
                'tag' => "",
            ]
        );
        // If not present
        $npcMaster = new NpcMaster();
        $npcLocalData = $npcMaster->GetByName($enemyNPC["name"]);
        $skyrimCmd = new SkyrimCommandBuilder();
        $json = $skyrimCmd->Actor->SetAlert("0x{$npcLocalData["refid"]}", 1);
        $skyrimCmd->send($json);

    }

    if ($npc_attacker["npc_combat_attempts"] % 15 === 0) {
        error_log("[WaitforCombatEnd] {$GLOBALS["actors_present"]}");
        $cnNpc = $npc_attacker["name"];
        if (strpos($GLOBALS["actors_present"], $npc_attacker["name"]) !== false) {
            error_log("[WaitforCombatEnd] MOVING <$cnNpc> still ongoing (attempt {$npc_attacker["npc_combat_attempts"]})");
            $GLOBALS["db"]->insert(
                'responselog',
                [
                    'localts' => time(),
                    'sent' => 0,
                    'actor' => "rolemaster",
                    'text' => "",
                    'action' => "rolecommand|moveToPlayer@{$enemyNPC["name"]}@$quest_id@9", // intent 9 = SeekAndDestroy
                    'tag' => "",
                ]
            );
        } else {

            error_log("[WaitforCombatEnd] [SeekAndKillPlayer] {$GLOBALS["actors_present"]}");
            $npcMaster = new NpcMaster();
            $currentNpcData = $npcMaster->getByName($enemyNPC["name"]);
            $unsignedInt = hexdec($currentNpcData["refid"]) & 0xFFFFFFFF;
            $refHexString = "0x" . str_pad(dechex($unsignedInt), 8, "0", STR_PAD_LEFT);

            $GLOBALS["db"]->insert(
                'responselog',
                [
                    'localts' => time(),
                    'sent' => 0,
                    'actor' => "rolemaster",
                    'text' => "",
                    'action' => "rolecommand|BackgroundCmd@$refHexString@SeekAndKillPlayer",
                    'tag' => '',
                ]
            );

        }
    }
    // Exceeded attempts → failure
    if ($npc_attacker["npc_combat_attempts"] >= $maxAttempts) {
        error_log("[WaitForNPCCombatEnd] Combat between <$cnAttacker> and <$cnTarget> did not end after $maxAttempts attempts");
        $npc_attacker["in_npc_combat"] = "failed";
        $quest_data["npcs"][$npc_ref_attacker] = $npc_attacker;
        SNQEQuestManager::updateQuestData($quest_id, ["npcs" => $quest_data["npcs"]]);
        return "failed";
    }

    // Still pending
    error_log("[WaitForNPCCombatEnd] Combat between <$cnAttacker> and <$cnTarget> still ongoing (attempt {$npc_attacker["npc_combat_attempts"]})");
    $npc_attacker["in_npc_combat"] = "pending";
    $quest_data["npcs"][$npc_ref_attacker] = $npc_attacker;
    SNQEQuestManager::updateQuestData($quest_id, ["npcs" => $quest_data["npcs"]]);
    return "pending";
}

/**
 * SNQE WaitAtLocation function
 *
 * Waits until the player reaches a specific location.
 *
 * @param string $quest_id Unique quest identifier
 * @param string $location Location name to wait for player to reach
 * @param int $maxAttempts Maximum retries before failure (default 1000).
 * @param string|null $npc_ref Optional NPC reference to travel to that location
 * @return string "done"|"failed"|"pending"
 */
function WaitAtLocation(
    string $quest_id,
    string $location,
    int $maxAttempts = 1000,
    ?string $npc_ref = null
): string {
    // Fetch quest state
    $quest = SNQEQuestManager::getQuest($quest_id);

    if (!$quest) {
        throw new Exception("Quest '$quest_id' does not exist.");
    }

    $quest_data = $quest["quest_data"] ?? [];

    if ($npc_ref && isset($quest_data["npcs"][$npc_ref])) {
        $npc = $quest_data["npcs"][$npc_ref];
        if ($quest_data["npcs"][$npc_ref]["name"] == $GLOBALS["PLAYER_NAME"]) {
            $npc_ref = null;
        }
    }

    // Initialize location tracking if not exists
    if (!isset($quest_data["location_wait"])) {
        $quest_data["location_wait"] = [];
        error_log("[WaitAtLocation] first call");
        if ($npc_ref) {
            if ((strpos($GLOBALS["actors_present"], $quest_data["npcs"][$npc_ref]["name"]) === false)) {
                error_log("[WaitAtLocation] Using Background TravelTo");
                // If NPC ref is provided, move NPC to location
                if ($npc_ref && isset($quest_data["npcs"][$npc_ref])) {
                    $npcMaster = new NpcMaster();
                    $currentNpcData = $npcMaster->getByName($quest_data["npcs"][$npc_ref]["name"]);
                    $unsignedInt = hexdec($currentNpcData["refid"]) & 0xFFFFFFFF;
                    $refHexString = "0x" . str_pad(dechex($unsignedInt), 8, "0", STR_PAD_LEFT);
                   
                   
                    // Some locations have same name, so try to pick the nearest one to the player.
                    // locations.coords is a PostgreSQL POINT. We'll order by distance using the <-> operator.
                    // Player coords: SELECT b.coords FROM named_cell a LEFT JOIN locations b ON (b.formid=a.location_id) WHERE a.id=0;
                    $playerCoordsRow = $GLOBALS["db"]->fetchOne(
                        'SELECT b.coords
                         FROM "public"."named_cell" a
                         LEFT JOIN locations b ON (b.formid = a.location_id)
                         WHERE a.id = 0'
                    );

                    $playerPoint = $playerCoordsRow["coords"] ?? null;

                    if (!empty($playerPoint)) {
                        $playerPointEsc = $GLOBALS["db"]->escape($playerPoint); // expected format "(x,y)"
                        error_log("[WaitAtLocation] Player coords: $playerPointEsc");
                        $locs = $GLOBALS["db"]->fetchOne(
                            "SELECT *
                             FROM locations
                             WHERE name='" . $GLOBALS["db"]->escape($location) . "'
                             ORDER BY coords <-> '{$playerPointEsc}'::point
                             LIMIT 1"
                        );
                        error_log("[WaitAtLocation] SELECT *
                             FROM locations
                             WHERE name='" . $GLOBALS["db"]->escape($location) . "'
                             ORDER BY coords <-> '{$playerPointEsc}'::point
                             LIMIT 1");
                    } else {
                        // Fallback if player coords are not available
                        error_log("[WaitAtLocation] Player coords not available, using first match for location");
                        $locs = $GLOBALS["db"]->fetchOne(
                            "SELECT *
                             FROM locations
                             WHERE name='" . $GLOBALS["db"]->escape($location) . "'
                             LIMIT 1"
                        );
                    }

                    $locationRef = getLocationReferences($locs["formid"]);
                    if ($locationRef) {
                        $locs["formid"] = $locationRef;
                    }

                    if ($locs) {
                        $GLOBALS["db"]->insert(
                            'responselog',
                            [
                                'localts' => time(),
                                'sent' => 0,
                                'actor' => "rolemaster",
                                'text' => "",
                                'action' => "rolecommand|BackgroundCmd@$refHexString@TravelTo/{$locs["formid"]}",
                                'tag' => '',
                            ]
                        );

                        //$GLOBALS["last_gamets"]

                        $GLOBALS["db"]->insert(
                            'eventlog',
                            [
                                'ts' => $GLOBALS["last_ts"],
                                'gamets' => $GLOBALS["last_gamets"],
                                'type' => "infoaction",
                                'data' => "The Narrator: {$currentNpcData["npc_name"]} starts travelling to $location",
                                'sess' => time(),
                                'localts' => time(),
                                'people' => $GLOBALS["actors_present"],
                                'location' => "",
                                'party' => "",
                            ]
                        );

                        // Insert actions_issued log entry
                        $GLOBALS["db"]->insert(
                            'actions_issued',
                            [
                                'action' => "TravelTo",
                                'fullcall' => "TravelTo:$location",
                                'actorname' => $currentNpcData["npc_name"],
                                'ts' => $GLOBALS["last_ts"],
                                'gamets' => $GLOBALS["gamets"],
                                'localts' => time(),
                                'original' => 'backgroundaction',
                            ]
                        );
                    }
                }
            } else {
                error_log("Using Foreground TravelTo");
                $suggestionText = "The following step  in the storyline requires {$quest_data["npcs"][$npc_ref]["name"]} to travel to a new location. {$quest_data["npcs"][$npc_ref]["name"]} must explain why this travel is needed.{$quest_data["npcs"][$npc_ref]["name"]} should travel to <$location>, (use TravelTo action). ";
                $GLOBALS["db"]->insert(
                    'responselog',
                    [
                        'localts' => time(),
                        'sent' => 0,
                        'actor' => "rolemaster",
                        'text' => "",
                        'action' => "rolecommand|Instruction@{$quest_data["npcs"][$npc_ref]["name"]}@$suggestionText@$quest_id",
                        'tag' => "",
                    ]
                );
            }
        }

    }

    $wait_key = "wait_" . md5($location);

    // If already at location, return success
    if (!empty($quest_data["location_wait"][$wait_key]) && $quest_data["location_wait"][$wait_key]["status"] === "done") {
        error_log("[WaitAtLocation] Player already at location <$location>");
        return "done";
    }

    // Initialize attempt counter for this location wait
    if (!isset($quest_data["location_wait"][$wait_key])) {
        $quest_data["location_wait"][$wait_key] = [
            "location" => $location,
            "attempts" => 0,
            "status" => "pending",
        ];
    }

    // Increment attempts
    $quest_data["location_wait"][$wait_key]["attempts"]++;

    // Query event log to check if player is at the location
    $locName = $GLOBALS["db"]->escape($location);

    $rows = $GLOBALS["db"]->fetchAll("select 1 as n from eventlog where type='location' and data like '%$locName%' and gamets>{$quest_data["started"]} order by localts desc limit 1");
    error_log("select 1 as n from eventlog where type='location' and data like '%$locName%' and gamets>{$quest_data["started"]} order by localts desc limit 1");
    $atLocation = false;
    if (is_array($rows) && isset($rows[0]) && $rows[0]["n"] > 0) {
        $atLocation = true;
    }

    $locGuess = DataLastKnownLocationHuman(false, true);
    error_log("<$locGuess> vs <$location>");
    if (stripos($location, $locGuess) !== false) {
        $atLocation = true;
    }

    if (stripos($locGuess, $location) !== false) {
        $atLocation = true;
    }

    if ($quest_data["location_wait"][$wait_key]["attempts"] > 100) {
        // Relaxed check after many attempts
        $locGuess = preg_replace('/outdoors/i', '', $locGuess);
        $locGuess = trim($location);
        error_log("Relaxed <$locGuess> vs <$location>");

        if (stripos($location, $locGuess) !== false) {
            $atLocation = true;
        }

        if (stripos($locGuess, $location) !== false) {
            $atLocation = true;
        }

    }
    // If npc_ref, also check NPC is present.

    if (@strpos($GLOBALS["actors_present"], $quest_data["npcs"][$npc_ref]["name"]) === false) {
        error_log("[WaitAtLocation] Reference NPC <{$quest_data["npcs"][$npc_ref]["name"]}> not present  <$location>,{$GLOBALS["actors_present"]}");
    
        if ($quest_data["location_wait"][$wait_key]["attempts"] % 25 == 0) { // Background command if NPC is not around

            if ($npc_ref && isset($quest_data["npcs"][$npc_ref])) {
                $npcMaster = new NpcMaster();
                $currentNpcData = $npcMaster->getByName($quest_data["npcs"][$npc_ref]["name"]);
                $unsignedInt = hexdec($currentNpcData["refid"]) & 0xFFFFFFFF;
                $refHexString = "0x" . str_pad(dechex($unsignedInt), 8, "0", STR_PAD_LEFT);

                // Some locations have same name, so try to pick the nearest one to the player.
                // locations.coords is a PostgreSQL POINT. We'll order by distance using the <-> operator.
                // Player coords: SELECT b.coords FROM named_cell a LEFT JOIN locations b ON (b.formid=a.location_id) WHERE a.id=0;
                $playerCoordsRow = $GLOBALS["db"]->fetchOne(
                    'SELECT b.coords
                        FROM "public"."named_cell" a
                        LEFT JOIN locations b ON (b.formid = a.location_id)
                        WHERE a.id = 0'
                );

                $playerPoint = $playerCoordsRow["coords"] ?? null;

                if (!empty($playerPoint)) {
                    $playerPointEsc = $GLOBALS["db"]->escape($playerPoint); // expected format "(x,y)"
                    error_log("[WaitAtLocation] Player coords: $playerPointEsc");
                    $locs = $GLOBALS["db"]->fetchOne(
                        "SELECT *
                            FROM locations
                            WHERE name='" . $GLOBALS["db"]->escape($location) . "'
                            ORDER BY coords <-> '{$playerPointEsc}'::point
                            LIMIT 1"
                    );
                    error_log("[WaitAtLocation] SELECT *
                            FROM locations
                            WHERE name='" . $GLOBALS["db"]->escape($location) . "'
                            ORDER BY coords <-> '{$playerPointEsc}'::point
                            LIMIT 1");
                } else {
                    // Fallback if player coords are not available
                    error_log("[WaitAtLocation] Player coords not available, using first match for location");
                    $locs = $GLOBALS["db"]->fetchOne(
                        "SELECT *  FROM locations WHERE name='" . $GLOBALS["db"]->escape($location) . "' LIMIT 1"
                    );
                }

                $locationRef = getLocationReferences($locs["formid"]);
                if ($locationRef) {
                    $locs["formid"] = $locationRef;
                }

                if ($locs) {
                    $GLOBALS["db"]->insert(
                        'responselog',
                        [
                            'localts' => time(),
                            'sent' => 0,
                            'actor' => "rolemaster",
                            'text' => "",
                            'action' => "rolecommand|BackgroundCmd@$refHexString@TravelTo/{$locs["formid"]}",
                            'tag' => '',
                        ]
                    );
                    
                    //$GLOBALS["last_gamets"]

                    $GLOBALS["db"]->insert(
                        'eventlog',
                        [
                            'ts' => $GLOBALS["last_ts"],
                            'gamets' => $GLOBALS["last_gamets"],
                            'type' => "infoaction",
                            'data' => "The Narrator: {$currentNpcData["npc_name"]} starts travelling to $location",
                            'sess' => time(),
                            'localts' => time(),
                            'people' => $GLOBALS["actors_present"],
                            'location' => "",
                            'party' => "",
                        ]
                    );

                    // Insert actions_issued log entry
                    $GLOBALS["db"]->insert(
                        'actions_issued',
                        [
                            'action' => "TravelTo",
                            'fullcall' => "TravelTo:$location",
                            'actorname' => $currentNpcData["npc_name"],
                            'ts' => $GLOBALS["last_ts"],
                            'gamets' => $GLOBALS["gamets"],
                            'localts' => time(),
                            'original' => 'backgroundaction',
                        ]
                    );
                }
            }

        } else {
            //GPS track. Should detect if NPC is at remote location.
            // Review: return tru if NPC is at location, but player did not get there yet.
            /*$GLOBALS["db"]->insert(
                'responselog',
                [
                    'localts' => time(),
                    'sent' => 0,
                    'actor' => "rolemaster",
                    'text' => "",
                    'action' => "rolecommand|BackgroundCmd@$refHexString@Track",
                    'tag' => '',
                ]
            );
            */
        }

        // Check by GPS tracking
        $cn = $GLOBALS["db"]->escape($quest_data["npcs"][$npc_ref]["name"]);
        $q = "SELECT
            id,
            metadata->'last_coords' AS last_coords,
            metadata->'last_coords'->>'3' AS location
        FROM core_npc_master
        WHERE npc_name = '$cn'
        AND (metadata->'last_coords'->>'last_updated')::bigint > {$quest_data["started"]}
        AND COALESCE(metadata->'last_coords'->>'3', '') <> ''";
        error_log($q);
        $gpsloc = $GLOBALS["db"]->fetchOne($q);
        if ($gpsloc) {
            $locationGps = $gpsloc["location"];

            error_log("[WaitAtLocation] <$locationGps> vs <$location>");
            if (strpos($location, $locationGps) !== false) {
                $npcAtLocation = true;
            }

            if (strpos($locationGps, $location) !== false) {
                $npcAtLocation = true;
            }
        }

        if (!DataActorHasDied($quest_data["npcs"][$npc_ref]["name"])) {
            $atLocation = false;
        }

    }  else {
        if ($location=="nearby") {
            $atLocation = true; // NPC is nearby;
        }   
    }


    if (isset($npc_ref) && $npcAtLocation) {
        error_log("[WaitAtLocation] Reference NPC <{$quest_data["npcs"][$npc_ref]["name"]}> is at location <$location>");
        $atLocation = true;
    }

    if ($atLocation) {
        error_log("[WaitAtLocation] Player reached location <$location>");
        $quest_data["location_wait"][$wait_key]["status"] = "done";

        SNQEQuestManager::updateQuestData($quest_id, ["location_wait" => $quest_data["location_wait"]]);
        return "done";
    } else {

        $sceneNoteData = "#Storyline: Quest: {$quest["title"]} : {$GLOBALS["PLAYER_NAME"]} and companions should travel to <$location>, next scene will happen there";
        $sceneNoteDataEsc = $GLOBALS["db"]->escape($sceneNoteData);
        // Check if row with same data already exists
        $existingRow = $GLOBALS["db"]->fetchOne(
            "SELECT rowid FROM rolemaster WHERE type = 'scenenote' AND data = '$sceneNoteDataEsc' LIMIT 1"
        );
        error_log("SELECT rowid FROM rolemaster WHERE type = 'scenenote' AND data = '$sceneNoteDataEsc' LIMIT 1");
        if ($existingRow) {
            // Update existing row's localts
            $GLOBALS["db"]->update(
                'rolemaster',
                'localts=' . time(),
                "rowid = {$existingRow["rowid"]}"
            );
        } else {
            // Insert new row
            $GLOBALS["db"]->insert(
                'rolemaster',
                [
                    'localts' => time(),
                    'ttl' => 25,
                    'type' => "scenenote",
                    'data' => $sceneNoteData,
                ]
            );
        }
    }

    if (!$npc_ref) {
        if ($quest_data["location_wait"][$wait_key]["attempts"] == 40) {
            if ($npc_ref && isset($quest_data["npcs"][$npc_ref])) { // Track waiting NPC
                $npcMaster = new NpcMaster();
                $currentNpcData = $npcMaster->getByName($quest_data["npcs"][$npc_ref]["name"]);
                $unsignedInt = hexdec($currentNpcData["refid"]) & 0xFFFFFFFF;
                $GLOBALS["db"]->insert(
                    'responselog',
                    [
                        'localts' => time(),
                        'sent' => 0,
                        'actor' => "rolemaster",
                        'text' => "",
                        'action' => "rolecommand|QuestTrackReference@0x{$currentNpcData["refid"]}",
                        'tag' => "",
                    ]
                );
            }

            // Remember player where to go
            $GLOBALS["db"]->insert(
                'responselog',
                [

                    'localts' => time(),
                    'sent' => 0,
                    'actor' => "rolemaster",
                    'text' => "",
                    'action' => "rolecommand|UpdateQuest@{$quest["title"]}@Travel to $location",
                    'tag' => "",

                ]
            );
        }
    }

    // Exceeded attempts → failure
    if ($quest_data["location_wait"][$wait_key]["attempts"] >= $maxAttempts && false) { // Eternal wait
        error_log("[WaitAtLocation] Player did not reach location <$location> after $maxAttempts attempts");
        $quest_data["location_wait"][$wait_key]["status"] = "failed";
        SNQEQuestManager::updateQuestData($quest_id, ["location_wait" => $quest_data["location_wait"]]);
        return "failed";
    }

    // Still pending
    error_log("[WaitAtLocation] Waiting for player/$npc_ref to reach <$location> (attempt {$quest_data["location_wait"][$wait_key]["attempts"]})");

    $GLOBALS["db"]->upsertRowOnConflict(
        'conf_opts',
        array(
            'id' => "snqe_pending_step",
            'value' => " Waiting for $npc_ref to reach <$location>"
        ),
        "id"
    );

    SNQEQuestManager::updateQuestData($quest_id, ["location_wait" => $quest_data["location_wait"]]);
    return "pending";
}

/**
 * SNQE TravelTo function
 *
 * Issues instruction to travel to a location and returns "done" after first call.
 * Uses the same instruction mechanism as WaitAtLocation but completes immediately.
 *
 * @param string $quest_id Unique quest identifier
 * @param string $location Location name to travel to
 * @param string|null $npc_ref Optional NPC reference to travel to that location
 * @return string Always returns "done"
 */
function TravelTo(
    string $quest_id,
    string $location,
    ?string $npc_ref = null
): string {
    // Fetch quest state
    $quest = SNQEQuestManager::getQuest($quest_id);

    if (!$quest) {
        throw new Exception("Quest '$quest_id' does not exist.");
    }

    $quest_data = $quest["quest_data"] ?? [];

    // Initialize travel tracking if not exists
    if (!isset($quest_data["travels"])) {
        $quest_data["travels"] = [];
    }

    $travel_key = "travel_" . md5($location);

    // If already issued, return done
    if (!empty($quest_data["travels"][$travel_key]) && $quest_data["travels"][$travel_key]["issued"] === true) {
        error_log("[TravelTo] Already issued instruction to travel to <$location>");
        return "done";
    }

    // Initialize travel tracking for this location
    if (!isset($quest_data["travels"][$travel_key])) {
        $quest_data["travels"][$travel_key] = [
            "location" => $location,
            "issued" => false,
        ];
    }

    error_log("[TravelTo] Issuing instruction to travel to <$location>");

    // Issue the travel instruction using same mechanism as WaitAtLocation
    if ($npc_ref && isset($quest_data["npcs"][$npc_ref])) {
        // NPC travel instruction
        if (strpos($GLOBALS["actors_present"], $quest_data["npcs"][$npc_ref]["name"]) === false) {
            error_log("[TravelTo] Using Background TravelTo");
            // Background command
            $npcMaster = new NpcMaster();
            $currentNpcData = $npcMaster->getByName($quest_data["npcs"][$npc_ref]["name"]);
            $unsignedInt = hexdec($currentNpcData["refid"]) & 0xFFFFFFFF;
            $refHexString = "0x" . str_pad(dechex($unsignedInt), 8, "0", STR_PAD_LEFT);

            $locs = $GLOBALS["db"]->fetchOne("SELECT * FROM locations where name='" . $GLOBALS["db"]->escape($location) . "' LIMIT 1");

            if ($locs) {
                $GLOBALS["db"]->insert(
                    'responselog',
                    [
                        'localts' => time(),
                        'sent' => 0,
                        'actor' => "rolemaster",
                        'text' => "",
                        'action' => "rolecommand|BackgroundCmd@$refHexString@TravelTo/{$locs["formid"]}",
                        'tag' => '',
                    ]
                );

                $GLOBALS["db"]->insert(
                    'eventlog',
                    [
                        'ts' => $GLOBALS["last_ts"],
                        'gamets' => $GLOBALS["last_gamets"],
                        'type' => "infoaction",
                        'data' => "The Narrator: {$currentNpcData["npc_name"]} starts travelling to $location",
                        'sess' => time(),
                        'localts' => time(),
                        'people' => $GLOBALS["actors_present"],
                        'location' => "",
                        'party' => "",
                    ]
                );

                // Insert actions_issued log entry
                $GLOBALS["db"]->insert(
                    'actions_issued',
                    [
                        'action' => "TravelTo",
                        'fullcall' => "TravelTo:$location",
                        'actorname' => $currentNpcData["npc_name"],
                        'ts' => $GLOBALS["last_ts"],
                        'gamets' => $GLOBALS["gamets"],
                        'localts' => time(),
                        'original' => 'backgroundaction',
                    ]
                );
            }
        } else {
            error_log("[TravelTo] Using Foreground TravelTo");
            // Foreground suggestion
            $suggestionText = "The following step in the storyline requires {$quest_data["npcs"][$npc_ref]["name"]} to travel to a new location. {$quest_data["npcs"][$npc_ref]["name"]} must explain why this travel is needed. {$quest_data["npcs"][$npc_ref]["name"]} should travel to <$location>, (use TravelTo action). If this topic has already said, follow up dialogue in a logical way";
            $GLOBALS["db"]->insert(
                'responselog',
                [
                    'localts' => time(),
                    'sent' => 0,
                    'actor' => "rolemaster",
                    'text' => "",
                    'action' => "rolecommand|Instruction@{$quest_data["npcs"][$npc_ref]["name"]}@$suggestionText@$quest_id",
                    'tag' => "",
                ]
            );
        }
    } else {
        // Player travel instruction (generic)
        $sceneNoteData = "#Storyline: {$GLOBALS["PLAYER_NAME"]} should travel to <$location>";
        $sceneNoteDataEsc = $GLOBALS["db"]->escape($sceneNoteData);

        $GLOBALS["db"]->insert(
            'rolemaster',
            [
                'localts' => time(),
                'ttl' => 25,
                'type' => "scenenote",
                'data' => $sceneNoteData,
            ]
        );
    }

    // Mark as issued and save
    $quest_data["travels"][$travel_key]["issued"] = true;
    SNQEQuestManager::updateQuestData($quest_id, ["travels" => $quest_data["travels"]]);

    error_log("[TravelTo] Travel instruction issued for <$location>, returning done");
    return "done";
}

/**
 * SNQE StationAtLocation function
 *
 * Non-blocking version of WaitAtLocation. Stations an NPC at a location and returns true after first execution.
 * Does not wait for confirmation that the NPC has arrived, immediately marks as complete.
 *
 * @param string $quest_id Unique quest identifier
 * @param string $location Location name to station NPC at
 * @param string|null $npc_ref NPC reference to station at the location
 * @return bool Always returns true after first execution
 */
function StationAtLocation(
    string $quest_id,
    string $location,
    ?string $npc_ref = null
): bool {
    // Fetch quest state
    $quest = SNQEQuestManager::getQuest($quest_id);

    if (!$quest) {
        throw new Exception("Quest '$quest_id' does not exist.");
    }

    $quest_data = $quest["quest_data"] ?? [];

    // Initialize station tracking if not exists
    if (!isset($quest_data["station_at_location"])) {
        $quest_data["station_at_location"] = [];
    }

    $station_key = "station_" . md5($location . ($npc_ref ?? ""));

    // If already stationed, return true
    if (!empty($quest_data["station_at_location"][$station_key]) && $quest_data["station_at_location"][$station_key]["stationed"] === true) {
        error_log("[StationAtLocation] NPC already stationed at <$location>");
        return true;
    }

    // Initialize station tracking for this location
    if (!isset($quest_data["station_at_location"][$station_key])) {
        $quest_data["station_at_location"][$station_key] = [
            "location" => $location,
            "npc_ref" => $npc_ref,
            "stationed" => false,
        ];
    }

    error_log("[StationAtLocation] Issuing station instruction for NPC at <$location>");

    // Issue the station instruction
    if ($npc_ref && isset($quest_data["npcs"][$npc_ref])) {
        // NPC station instruction
        if (strpos($GLOBALS["actors_present"], $quest_data["npcs"][$npc_ref]["name"]) === false) {
            error_log("[StationAtLocation] Using Background TravelTo");
            // Background command
            $npcMaster = new NpcMaster();
            $currentNpcData = $npcMaster->getByName($quest_data["npcs"][$npc_ref]["name"]);
            $unsignedInt = hexdec($currentNpcData["refid"]) & 0xFFFFFFFF;
            $refHexString = "0x" . str_pad(dechex($unsignedInt), 8, "0", STR_PAD_LEFT);

            $locs = $GLOBALS["db"]->fetchOne("SELECT * FROM locations where name='" . $GLOBALS["db"]->escape($location) . "' LIMIT 1");

            $locationRef = getLocationReferences($locs["formid"]);
            if ($locationRef) {
                $locs["formid"] = $locationRef;
            }

            if ($locs) {
                $GLOBALS["db"]->insert(
                    'responselog',
                    [
                        'localts' => time(),
                        'sent' => 0,
                        'actor' => "rolemaster",
                        'text' => "",
                        'action' => "rolecommand|BackgroundCmd@$refHexString@TravelTo/{$locs["formid"]}",
                        'tag' => '',
                    ]
                );

                $GLOBALS["db"]->insert(
                    'eventlog',
                    [
                        'ts' => $GLOBALS["last_ts"],
                        'gamets' => $GLOBALS["last_gamets"],
                        'type' => "infoaction",
                        'data' => "The Narrator: {$currentNpcData["npc_name"]} takes position at $location",
                        'sess' => time(),
                        'localts' => time(),
                        'people' => $GLOBALS["actors_present"],
                        'location' => "",
                        'party' => "",
                    ]
                );

                // Insert actions_issued log entry
                $GLOBALS["db"]->insert(
                    'actions_issued',
                    [
                        'action' => "StationAtLocation",
                        'fullcall' => "StationAtLocation:$location",
                        'actorname' => $currentNpcData["npc_name"],
                        'ts' => $GLOBALS["last_ts"],
                        'gamets' => $GLOBALS["gamets"],
                        'localts' => time(),
                        'original' => 'backgroundaction',
                    ]
                );
            }
        } else {
            error_log("[StationAtLocation] Using Foreground suggestion");
            // Foreground suggestion
            $suggestionText = "The following step in the storyline requires {$quest_data["npcs"][$npc_ref]["name"]} to station at a location. {$quest_data["npcs"][$npc_ref]["name"]} should move to and stay at <$location>.";
            $GLOBALS["db"]->insert(
                'responselog',
                [
                    'localts' => time(),
                    'sent' => 0,
                    'actor' => "rolemaster",
                    'text' => "",
                    'action' => "rolecommand|Suggestion@{$quest_data["npcs"][$npc_ref]["name"]}@$suggestionText@$quest_id",
                    'tag' => "",
                ]
            );
        }
    }

    // Mark as stationed and save (non-blocking, return true immediately)
    $quest_data["station_at_location"][$station_key]["stationed"] = true;
    SNQEQuestManager::updateQuestData($quest_id, ["station_at_location" => $quest_data["station_at_location"]]);

    error_log("[StationAtLocation] Station instruction issued for <$location>, returning true immediately");
    return true;
}

/**
 * SNQE WaitForActivation function
 *
 * Waits until an activator is activated by the player.
 * Blocks quest execution until the activator is activated.
 *
 * @param string $quest_id Unique quest identifier
 * @param string $activator_ref Activator reference in format "Name:0xHEXID" or "0xHEXID"
 * @param int $maxAttempts Maximum retries before failure (default 1000)
 * @return string "done" if activator was activated, "waiting" if still waiting
 */
function WaitForActivation(
    string $quest_id,
    string $activator_ref,
    int $maxAttempts = 1000
): string {
    // Fetch quest state
    $quest = SNQEQuestManager::getQuest($quest_id);

    if (!$quest) {
        throw new Exception("Quest '$quest_id' does not exist.");
    }

    $quest_data = $quest["quest_data"] ?? [];

    // Initialize activation tracking if not present
    if (!isset($quest_data["activation_tracking"])) {
        $quest_data["activation_tracking"] = [];
    }

    // Parse activator reference
    // Format: "Name:0xHEXID" or "0xHEXID"
    $activator_name = null;
    $activator_id = null;

    if (strpos($activator_ref, ':') !== false) {
        // Format: "Name:0xHEXID"
        list($activator_name, $activator_id) = explode(':', $activator_ref, 2);
    } else {
        // Format: "0xHEXID"
        $activator_id = $activator_ref;
    }


    // Use the form ID as the tracking key
    $tracking_key = $activator_id;

    // If already activated, return success
    if (
        !empty($quest_data["activation_tracking"][$tracking_key]) &&
        $quest_data["activation_tracking"][$tracking_key] === "done"
    ) {
        error_log("[WaitForActivation] Activator <$activator_ref> already activated");
        return "done";
    }

    // Increment activation check attempts
    $quest_data["activation_tracking"][$tracking_key] = $quest_data["activation_tracking"][$tracking_key] ?? [];
    if (is_array($quest_data["activation_tracking"][$tracking_key])) {
        $attempts = 0;
        $GLOBALS["db"]->insert(
            'responselog',
            [
                'localts' => time(),
                'sent' => 0,
                'actor' => "rolemaster",
                'text' => "",
                'action' => "rolecommand|QuestTrackReference@{$activator_id}",
                'tag' => "",
            ]
        );

    } else {
        $attempts = (int) $quest_data["activation_tracking"][$tracking_key];
    }
    $attempts++;

    $activator_id = strtoupper(dechex(hexdec($activator_id)));
    // Query event log to check if activator was activated
    // Pattern: "X activates Y" where Y contains the form ID or activator name
    $searchPattern = "%$activator_id%activated";

    $rows = $GLOBALS["db"]->fetchAll(
        "SELECT count(*) as n FROM eventlog WHERE type='status_msg' AND data LIKE '" .
        $GLOBALS["db"]->escape($searchPattern) . "'"
    );
    error_log("SELECT count(*) as n FROM eventlog WHERE type='status_msg' AND data LIKE '" .
        $GLOBALS["db"]->escape($searchPattern) . "'");
    $wasActivated = false;
    if (is_array($rows) && isset($rows[0]) && $rows[0]["n"] > 0) {
        $wasActivated = true;
    }

    if ($wasActivated) {
        error_log("[WaitForActivation] Activator <$activator_ref> has been activated");
        $quest_data["activation_tracking"][$tracking_key] = "done";
        SNQEQuestManager::updateQuestData($quest_id, ["activation_tracking" => $quest_data["activation_tracking"]]);
        return "done";
    }
    $searchPattern = "%activates $activator_name%";
    $rows = $GLOBALS["db"]->fetchAll(
        "SELECT count(*) as n FROM eventlog WHERE type='infoaction' AND data LIKE '" .
        $GLOBALS["db"]->escape($searchPattern) . "'"
    );

    if (is_array($rows) && isset($rows[0]) && $rows[0]["n"] > 0) {
        $wasActivated = true;
    }

    if ($wasActivated) {
        error_log("[WaitForActivation] Activator <$activator_ref> has been activated");
        $quest_data["activation_tracking"][$tracking_key] = "done";
        SNQEQuestManager::updateQuestData($quest_id, ["activation_tracking" => $quest_data["activation_tracking"]]);
        return "done";
    }
    // Store attempt count
    $quest_data["activation_tracking"][$tracking_key] = $attempts;

    // Insert or update a scenenote to guide the player
    $activator_display = $activator_name ? "$activator_name ($activator_id)" : $activator_id;
    $sceneNoteData = "#Storyline: {$GLOBALS["PLAYER_NAME"]} must activate <$activator_display>";
    $sceneNoteDataEsc = $GLOBALS["db"]->escape($sceneNoteData);

    // Check if row with same data already exists
    $existingRow = $GLOBALS["db"]->fetchOne(
        "SELECT rowid FROM rolemaster WHERE type = 'scenenote' AND data = '$sceneNoteDataEsc' LIMIT 1"
    );
    error_log("SELECT rowid FROM rolemaster WHERE type = 'scenenote' AND data = '$sceneNoteDataEsc' LIMIT 1");

    if ($existingRow) {
        // Update existing row's localts to keep it active
        $GLOBALS["db"]->update(
            'rolemaster',
            'localts=' . time(),
            "rowid = {$existingRow["rowid"]}"
        );
    } else {
        // Insert new row
        $GLOBALS["db"]->insert(
            'rolemaster',
            [
                'localts' => time(),
                'ttl' => 25,
                'type' => "scenenote",
                'data' => $sceneNoteData,
            ]
        );
    }

    if ($attempts % 15 == 0) {
        error_log("[WaitForActivation] Re-issuing activator tracking for <$activator_ref>");
        if ($activator_id) {
            $GLOBALS["db"]->insert(
                'responselog',
                [
                    'localts' => time(),
                    'sent' => 0,
                    'actor' => "rolemaster",
                    'text' => "",
                    'action' => "rolecommand|QuestTrackReference@{$activator_id}",
                    'tag' => "",
                ]
            );

        } else {
            $attempts = (int) $quest_data["activation_tracking"][$tracking_key];
        }
    }
    // Save updated quest state
    SNQEQuestManager::updateQuestData($quest_id, ["activation_tracking" => $quest_data["activation_tracking"]]);

    error_log("[WaitForActivation] Waiting for activator <$activator_ref> activation (attempt $attempts/$maxAttempts)");
    return "waiting";
}

/**
 * SNQE PickUpItem function
 *
 * @param string $quest_id Unique quest identifier
 * @param string $npc_ref NPC reference ID
 * @param string $item_ref Item reference ID
 * @return void
 */
function PickUpItem(
    string $quest_id,
    string $npc_ref,
    string $item_ref
): void {
    // Fetch quest state
    $quest = SNQEQuestManager::getQuest($quest_id);

    if (!$quest) {
        throw new Exception("Quest '$quest_id' does not exist.");
    }

    $quest_data = $quest["quest_data"] ?? [];

    if (!isset($quest_data["npcs"][$npc_ref])) {
        throw new Exception("NPC '$npc_ref' not declared. Use CreateNPC first.");
    }

    if (!isset($quest_data["items"][$item_ref])) {
        throw new Exception("Item '$item_ref' not declared. Use CreateItem first.");
    }

    $npc = $quest_data["npcs"][$npc_ref];
    $item = $quest_data["items"][$item_ref];

    $tracking_key = "{$npc_ref}_{$item_ref}";

    // Check if item was spawned on npc_ref's pocket (item location="pocket")
    // If so, mark as already picked up
    if (isset($item["location"]) && $item["location"] === "pocket" && !empty($npc)) {
        // Item spawned in NPC's pocket, already considered picked up
        if (!isset($quest_data["pickup_tracking"])) {
            $quest_data["pickup_tracking"] = [];
        }

        $quest_data["pickup_tracking"][$tracking_key] = [
            "npc_ref" => $npc_ref,
            "item_ref" => $item_ref,
            "picked_up" => true,
            "pickup_time" => time(),
        ];

        // Mark item as in NPC inventory
        if (!isset($quest_data["items"][$item_ref]["in_inventory"])) {
            $quest_data["items"][$item_ref]["in_inventory"] = [];
        }
        $quest_data["items"][$item_ref]["in_inventory"][$npc_ref] = true;

        // Save updated quest state and return early
        SNQEQuestManager::updateQuestData($quest_id, [
            "pickup_tracking" => $quest_data["pickup_tracking"],
            "items" => $quest_data["items"]
        ]);

        error_log("[PickUpItem] Item <$item_ref> already in NPC <$npc_ref>'s pocket, marking as picked up");
        return;
    }

    // Initialize pickup tracking if not exists
    if (!isset($quest_data["pickup_tracking"][$tracking_key])) {
        $quest_data["pickup_tracking"] = [];

        $npcMaster = new NpcMaster();
        $currentNpcData = $npcMaster->getByName($quest_data["npcs"][$npc_ref]["name"]);
        $unsignedInt = hexdec($currentNpcData["refid"]) & 0xFFFFFFFF;
        $refHexString = "0x" . str_pad(dechex($unsignedInt), 8, "0", STR_PAD_LEFT);

        $formId = convertSignedToUnsignedHex($item["int_refid"]);

        $GLOBALS["db"]->insert(
            'responselog',
            [
                'localts' => time(),
                'sent' => 0,
                'actor' => "rolemaster",
                'text' => "",
                'action' => "rolecommand|BackgroundCmd@$refHexString@PickUpItem/{$item["int_refid"]}",
                'tag' => '',
            ]
        );

        $GLOBALS["db"]->insert(
            'responselog',
            [
                'localts' => time(),
                'sent' => 0,
                'actor' => "rolemaster",
                'text' => "",
                'action' => "rolecommand|QuestTrackReference@{$item["int_refid"]}",
                'tag' => "",
            ]
        );

        // Mark item as picked up by NPC
        $quest_data["pickup_tracking"][$tracking_key] = [
            "npc_ref" => $npc_ref,
            "item_ref" => $item_ref,
            "picked_up" => false,
            "pickup_time" => time(),
        ];

        // Save updated quest state
        SNQEQuestManager::updateQuestData($quest_id, [
            "pickup_tracking" => $quest_data["pickup_tracking"],
            "items" => $quest_data["items"]
        ]);

        error_log("[PickUpItem] NPC <$npc_ref> picked up item <$item_ref>");
    } else {
        error_log("[PickUpItem] NPC <$npc_ref> picked up item <$item_ref> Already issued");
    }
}

/**
 * SNQE WaitForPickUpItem function
 *
 * @param string $quest_id Unique quest identifier
 * @param string $npc_ref NPC reference ID
 * @param string $item_ref Item reference ID
 * @param int $maxAttempts Maximum number of attempts to wait (default: 1000)
 * @return string "done" if item was picked up, "waiting" otherwise
 */
function WaitForPickUpItem(
    string $quest_id,
    string $npc_ref,
    string $item_ref,
    int $maxAttempts = 1000
): string {
    // Fetch quest state
    $quest = SNQEQuestManager::getQuest($quest_id);

    if (!$quest) {
        throw new Exception("Quest '$quest_id' does not exist.");
    }

    $quest_data = $quest["quest_data"] ?? [];

    if (!isset($quest_data["npcs"][$npc_ref])) {
        if ($npc_ref == "player") {
            error_log("[WaitForPickUpItem] NPC reference is 'player', treating as player character");
            $quest_data["npcs"][$npc_ref] = [
                "name" => $GLOBALS["PLAYER_NAME"],
                "refid" => null,
            ];
        } else
            throw new Exception("NPC '$npc_ref' not declared. Use CreateNPC first.");
    }

    if (!isset($quest_data["items"][$item_ref])) {
        throw new Exception("Item '$item_ref' not declared. Use CreateItem first.");
    }

    $npc = $quest_data["npcs"][$npc_ref];
    $item = $quest_data["items"][$item_ref];

    // Initialize pickup tracking if not exists
    if (!isset($quest_data["pickup_tracking"])) {
        $quest_data["pickup_tracking"] = [];
    }

    $tracking_key = "{$npc_ref}_{$item_ref}";

    // Check if item has already been picked up
    if (isset($quest_data["pickup_tracking"][$tracking_key]) && $quest_data["pickup_tracking"][$tracking_key]["picked_up"] === true) {
        error_log("[WaitForPickUpItem] Item <$item_ref> already picked up by <$npc_ref>");
        return "done";
    }

    // Increment pickup attempts
    if (!isset($quest_data["pickup_tracking"][$tracking_key])) {
        $quest_data["pickup_tracking"][$tracking_key] = [
            "pickup_attempts" => 0,
        ];
    }

    //error_log(print_r($quest_data["pickup_tracking"], true));

    $quest_data["pickup_tracking"][$tracking_key]["pickup_attempts"] = ($quest_data["pickup_tracking"][$tracking_key]["pickup_attempts"] ?? 0) + 1;
    $attempts = $quest_data["pickup_tracking"][$tracking_key]["pickup_attempts"];

    if ($attempts % 10 == 0) {
        error_log("[WaitForPickUpItem] Reissuing PickUpItem command for item <$item_ref> by NPC <$npc_ref> (attempt $attempts)");
        unset($quest_data["pickup_tracking"][$tracking_key]);
        SNQEQuestManager::updateQuestData($quest_id, [
            "pickup_tracking" => $quest_data["pickup_tracking"],
            "items" => $quest_data["items"]
        ]);
        PickUpItem($quest_id, $npc_ref, $item_ref);
        return "waiting";
    } else {
    }
    // Check if max attempts exceeded
    if ($attempts > $maxAttempts) {
        error_log("[WaitForPickUpItem] Max attempts ($maxAttempts) exceeded for item <$item_ref> by NPC <$npc_ref>");
        return "failed";
    }


    // Query event log to see if NPC picked up the item
    $cnItem = $GLOBALS["db"]->escape($item["name"]);
    $cnNpc = $GLOBALS["db"]->escape($npc["name"]);

    $rows = $GLOBALS["db"]->fetchAll("select count(*) as n from eventlog where type='itempickup' and data like '%$cnNpc%' and data like '%$cnItem%'");

    $pickedUp = false;
    if (is_array($rows) && isset($rows[0]) && $rows[0]["n"] > 0) {
        $pickedUp = true;
    }

    if ($npc_ref == "player") {
        $player = new Player();
        $playerInventory = json_decode($player->get("inventory"), true);
        foreach ($playerInventory as $itemInIventory) {
            if ($itemInIventory["name"] == $item["name"]) {
                error_log("[WaitForPickUpItem] Item <$cnItem> found in player inventory");
                $pickedUp = true;
                break;
            }
        }
    } else {
        $npcMaster = new NpcMaster();
        $currentNpcData = $npcMaster->getByName($quest_data["npcs"][$npc_ref]["name"]);
        $currentNpcMetaData = $npcMaster->getMetadata($currentNpcData);
        foreach ($currentNpcMetaData["inventory"] as $itemInIventory) {
            if ($itemInIventory["name"] == $item["name"]) {
                $pickedUp = true;
                break;
            }
        }
    }
    $rows = $GLOBALS["db"]->fetchOne("select count(*) as n from eventlog where type='itemfound' and data like '%gave%$cnItem%$cnNpc%'");

    if (is_array($rows) && isset($rows) && $rows["n"] > 0) {
        $pickedUp = true;
    }

    $rows = $GLOBALS["db"]->fetchOne("select 1 as n,gamets from eventlog where type='death' and (data like '%defeated $cnNpc%' or data like '%killed $cnNpc%') order by gamets desc limit 1");

    if (isset($rows["n"])) {
        $pickedUp = true;
        error_log("NPC is dead");
        return "done";
    }

    if ($pickedUp) {
        error_log("[WaitForPickUpItem] Item <$cnItem> successfully picked up by NPC <$cnNpc>");
        $quest_data["pickup_tracking"][$tracking_key]["picked_up"] = true;
        $quest_data["pickup_tracking"][$tracking_key]["pickup_time"] = time();

        // Update item state to show it's in NPC inventory
        if (!isset($quest_data["items"][$item_ref]["in_inventory"])) {
            $quest_data["items"][$item_ref]["in_inventory"] = [];
        }
        $quest_data["items"][$item_ref]["in_inventory"][$npc_ref] = true;

        SNQEQuestManager::updateQuestData($quest_id, [
            "pickup_tracking" => $quest_data["pickup_tracking"],
            "items" => $quest_data["items"]
        ]);
        return "done";
    }



    // Save updated quest state
    SNQEQuestManager::updateQuestData($quest_id, ["pickup_tracking" => $quest_data["pickup_tracking"]]);

    error_log("[WaitForPickUpItem] Waiting for NPC <$npc_ref> to pick up item <$item_ref> (attempt $attempts/$maxAttempts) tracking_key: $tracking_key");
    return "waiting";
}
