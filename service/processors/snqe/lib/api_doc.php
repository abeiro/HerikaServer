<?php 

/*
Skyrim Narrative Quest Engine (SNQE)

API 
All functions take quest_id as the first parameter.
All functions are idempotent: if already executed successfully for a given quest_id, they do nothing.
State is persisted per quest_id to allow quest scripts to resume execution across multiple runs.


1. Creation Functions

* CreateNPC(quest_id, npc_ref, name, gender, class, race, location, appearance, background, speechStyle, disposition)
Declares a new NPC for reference, and  - optionally - later spawning. Already spawned NPCs from previous sessions must be created.
quest_id (string, required) – Quest identifier.
npc_ref (string, required) – Internal NPC reference ID.
name (string, required) – NPC display name.
gender (string, required) – "Male" or "Female".
class (enum, required) – beggar, warrior, assassin, mage, farmer, soldier, merchant, noble.
race (enum, required) – Nord, Imperial, Argonian, RedGuard, Orc, Breton.
location (string, required) – Default placement, e.g., "Whiterun" or "nearby".
appearance (string, optional) – Hair, clothes, scars, visual description.
background (string, optional) – Lore or backstory. Should be about 200 character long for good roleplay. 
speechStyle (string, optional) – How NPC talks (formal, rustic, archaic, etc., cursed words, uses specific fillir words).
disposition (enum, optional) – defiant, submissive, friendly, serious, sad, aggressive, cheerful, distrustful, furious, drunk, high.

* CreateItem(quest_id, item_ref, name, type, location, description, npc_ref)
Declares a new item for later spawning. **Items in pockets should be spawned too**

quest_id (string, required) – Quest identifier.
item_ref (string, required) – Internal item reference ID.
name (string, required) – Item display name.
type (enum, required) – sword, armor, helmet, ring, amulet, book, note, axe, long sword, staff, great axe, bow.
location (enum, required) – "nearby", "major city".
description (string) – Description, or content if item is book or note.
npc_ref (string, optional) – NPC reference ID to place item in NPC's inventory. If omitted, item will be placed in the world.

* CreateTopic(quest_id, topic_ref, name, type, item, giver, info, target, important)
Declares a dialogue topic.

quest_id (string, required) – Quest identifier.
topic_ref (string, required) – Internal topic reference ID.
name (string, required) –  // topic concept/description
type (enum, required) – Lore, Item, Location.
item (string, optional) – If type=Item, the item_ref this topic refers to.
giver (string, required) – NPC reference or name giving the topic.
info (string, required) – Topic the NPC should talk about. Use a few example phrases, but mainly describe the topic.
target (string, required) – NPC reference (char_ref) or "player" who receives the topic. *MAKE SURE NPC HAS BEEN CREATED*
important (bool, required, default=true) – If true, topic delivery will be verified through multiple checks. If false, CheckTopicToPlayer returns success immediately on first call. On plot relevant topics should be true.

2. Spawn Functions

Spawn functions initiate asynchronous placement of NPCs or items and must be followed by Check functions.

* SpawnNPC(quest_id, npc_ref, location)

Spawns a declared NPC at a location.

quest_id (string, required)
npc_ref (string, required) – NPC reference created via CreateNPC.
location (string, required) – Placement location. eg: "Whiterun" or "nearby".

* CheckNPCSpawn(quest_id, npc_ref, maxAttempts)
Checks if NPC has successfully spawned.

quest_id (string, required)
npc_ref (string, required)
maxAttempts (int, optional, default=5) – Maximum retries before failure.

Returns: "done" | "pending" | "failed".

* SpawnItem(quest_id, item_ref, location_or_char_ref)

Spawns a declared item. **Items in pockets should be spawned too**

quest_id (string, required)
item_ref (string, required) (Maker sure item has been created)
location_or_char_ref (string, optional) – If omitted → world spawn. If NPC ref → NPC inventory.

* CheckItemSpawn(quest_id, item_ref,  maxAttempts)

Checks if item has spawned.

quest_id (string, required)
item_ref (string, required)
maxAttempts (int, optional, default=5)

Returns: "done" | "pending" | "failed".

3. Interaction Functions

* MoveToPlayer(quest_id, npc_ref, follow)

Orders NPC to move to player.

quest_id (string, required)
npc_ref (string, required)
follow (bool, optional, default=false) – Whether NPC follows the player.

* TellTopicToPlayer(quest_id, npc_ref, topic_ref)

NPC delivers a topic to the player.

quest_id (string, required)
npc_ref (string, required) (Make sure NPC has been created or this function will fail)
topic_ref (string, required) (Make sure topic has been created or this function will fail)

Quest fails if NPC does not deliver topic within timeout.

* TellTopicToNPC(quest_id, npc_ref, topic_ref, destination_ref)

NPC delivers a topic to another NPC.

quest_id (string, required)
npc_ref (string, required)
topic_ref (string, required)
destination_ref (string, required)

Quest fails if delivery does not occur within timeout.

* ToGoAway(quest_id, npc_ref, maxAttempts)

Removes NPC from the world.

quest_id (string, required)
npc_ref (string, required)
maxAttempts (int, optional, default=5) – Maximum retries before failure.

Returns: "done" | "pending" | "failed".

* CombatPlayer(quest_id, npc_ref)

Orders an NPC to engage the player in combat.

quest_id (string, required)
npc_ref (string, required)

Returns: void

Notes:

Initiates combat between the NPC and the player.
Use WaitforCombatEnd to monitor the outcome.
Idempotent: if already ordered to combat, does nothing.

* CombatNPC(quest_id, npc_ref_attacker, npc_ref_target)

Orders one NPC to engage another NPC in combat.

quest_id (string, required)
npc_ref_attacker (string, required) – NPC reference that will initiate the attack.
npc_ref_target (string, required) – NPC reference that will be attacked.

Returns: void

Notes:

Initiates combat between two NPCs.
Use WaitForNPCCombatEnd to monitor the outcome.
Idempotent: if already ordered to combat, does nothing.
Both NPCs must be created via CreateNPC first.
Useful for PvP encounters, NPC duels, or NPC betrayals in quest sequences.

4. Wait Functions

Wait functions pause quest execution until a condition is met, fails, or times out.

* WaitToItemBeRecovered(quest_id, item_ref, timeout)

Waits until the player recovers an item. (make sure item has been spawned using SpawnItem and CheckItemSpawn)

quest_id (string, required)
item_ref (string, required) (check if item has been spawned)
timeout (int, optional, default=10)

Returns: "done" | "pending" | "failed".

* WaitForCoins(quest_id, npc_ref, amount, timeout)

Waits until the player gives coins to an NPC.

quest_id (string, required)
npc_ref (string, required)
amount (int, required)
timeout (int, optional, default=10)

Returns: "done" | "pending" | "failed".

* WaitToItemBeTraded(quest_id, item_ref, npc_ref, timeout)

Waits until the player gives a specific item to an NPC.

quest_id (string, required)
item_ref (string, required)
npc_ref (string, required)
timeout (int, optional, default=10)

Returns: "done" | "pending" | "failed".

* WaitforCombatEnd(quest_id, npc_ref, maxAttempts)

Waits until combat between an NPC and the player has ended.

quest_id (string, required)
npc_ref (string, required)
maxAttempts (int, optional, default=100) – Maximum retries before failure.

Returns: "done" | "pending" | "failed".

Notes:

Use this function after CombatPlayer to monitor combat resolution.
Returns "done" when combat has ended (NPC defeated or fled).
Returns "pending" while combat is still ongoing.
Returns "failed" if combat does not end within maxAttempts checks.
Tracks combat_attempts and combat state per NPC in quest data.

* WaitForNPCCombatEnd(quest_id, npc_ref_attacker, npc_ref_target, maxAttempts)

Waits until combat between two NPCs has ended.

quest_id (string, required)
npc_ref_attacker (string, required) – NPC reference that initiated the attack.
npc_ref_target (string, required) – NPC reference that was attacked.
maxAttempts (int, optional, default=100) – Maximum retries before failure.

Returns: "done" | "pending" | "failed".

Notes:

Use this function after CombatNPC to monitor combat resolution.
Returns "done" when combat has ended (one NPC defeated or both fled).
Returns "pending" while combat is still ongoing.
Returns "failed" if combat does not end within maxAttempts checks.
Tracks npc_combat_attempts and combat state for both NPCs in quest data.
Monitors event log for death events involving either NPC.

* CheckTopicToPlayer(quest_id, topic_ref, maxAttempts)

Checks whether an NPC has successfully delivered a declared topic to the player.
This function is typically used after TellTopicToPlayer to confirm dialogue delivery.

quest_id (string, required) – Quest identifier.
topic_ref (string, required) – Topic reference created via CreateTopic.
maxAttempts (int, optional, default=50) – Maximum number of retries before failure.
Returns: "done" | "pending" | "failed"

"done" → Topic was delivered to the player.
"pending" → NPC has not yet delivered the topic; further checks required.
"failed" → Maximum attempts exceeded without successful delivery.

Notes:

Function is idempotent. If the topic was already delivered, it returns "done".
Tracks delivery_attempts per topic in quest state.

* WaitAtLocation(quest_id, location, maxAttempts, npc_ref)

Waits until the player reaches a specific location.

quest_id (string, required) – Quest identifier.
location (string, required) – Location name to wait for (e.g., "Whiterun", "Markarth", "Solitude").
maxAttempts (int, optional, default=1000) – Maximum retries before failure. Set to 1000 for indefinite waiting.
npc_ref (string, optional) – NPC reference ID. If provided, the NPC will travel to that location when the player arrives.

Returns: "done" | "pending" | "failed"

"done" → Player has reached the specified location.
"pending" → Player has not yet reached the location; further checks required.
"failed" → Maximum attempts exceeded without player reaching the location.

Notes:

Queries the eventlog for location events.
Function is idempotent. If the player already reached the location, it returns "done".
Tracks location_wait state per location in quest data.
Useful for branching quest logic based on player location.
If npc_ref is provided, sends a command to move the NPC to that location once the player arrives.
Function is idempotent. If the player already reached the location, it returns "done".
Tracks location_wait state per location in quest data.
Useful for branching quest logic based on player location.

* CompleteQuest(quest_id, result)

Marks a quest as finished and updates its state. This function is intended to be called as the final step in a quest sequence.

quest_id (string, required) – Quest identifier.

result (string, optional) – Optional textual description or outcome of the quest.

Returns: void

Notes:

Updates the quest state quest_run_state to "finished".

Records a timestamp (completed_at) in quest data.

Logs the completion in responselog for traceability.

Idempotent: if the quest is already finished, calling it again does not cause side effects


✅ Execution Flow Notes

Create functions are declarative; safe to call multiple times.

Spawn functions trigger asynchronous events; use Check* to confirm.

Wait functions allow branching: success → continue, timeout → alternate path, waiting → pause and retry on next run.


Interaction functions (MoveToPlayer, TellTopic*, CombatPlayer) are executed once and persist state.


Example quest:

// Quest: Find the Lost Tome

$quest_id = "find_lost_ring";
$npc_ref = "wizard_ulfric";
$item_ref = "ancient_tome";


// 1. Declare the wizard NPC
CreateNPC(
    $quest_id,
    $npc_ref,
    "Ulfric the Wise",
    "Male",
    "mage",
    "Breton",
    "Raven Rock",
    "Long white beard, blue robes, staff",
    "A reclusive scholar seeking lost knowledge.",
    "formal",
    "serious"
);


// 2. Declare the book item
CreateItem(
    $quest_id,
    $item_ref,
    "Ancient Ring",
    "ring",
    "nearby",
    "A ring tome filled with arcane secrets."
);


// 3. Declare a topic for the wizard to ask for the book
CreateTopic(
    $quest_id,
    "t_ask_ring",
    "Seeking the Ancient Ring",
    "Item",
    $item_ref,
    $npc_ref,
    "Character should talk about the ring and ask the player to find it. E.g.:'I require the Ancient Ring for my research. Can you find it for me?'",
    "player"
);




// 4. Spawn the wizard nearby
SpawnNPC($quest_id, $npc_ref, "nearby");
if (CheckNPCSpawn($quest_id, $npc_ref)!="done") {
    error_log("NPC not spawned <SpawnItem($quest_id, $item_ref>".PHP_EOL);
    return;
}

// 5. Spawn the book somewhere nearby
SpawnItem($quest_id, $item_ref, "nearby");
if (CheckItemSpawn($quest_id, $item_ref)!="done") {
    error_log("Item not spawned <SpawnItem($quest_id, $item_ref>".PHP_EOL);
    return;
}


// We could use WaitAtLocation here if we want the wizard to stay there until player finds him
// This is prefered when spawning location is not nearby

// WaitAtLocation($quest_id, "Remote Location", 10000);

// If NPC was spawned nearby, just TellTopicToPlayer

// 6. Wizard tells the player about the quest
if (TellTopicToPlayer($quest_id, $npc_ref, "t_ask_ring") !="done") {
    error_log("TellTopicToPlayer failed, will retry".PHP_EOL);
    return;
}

if (CheckTopicToPlayer($quest_id, "t_ask_ring") !="done") {
    error_log("Topic not covered ".PHP_EOL);
    return;
}

// 7. Wait for the player to recover the book 
if (WaitToItemBeRecovered($quest_id, $item_ref)  != "done") {
    error_log("Item not recovered ".PHP_EOL);
    return;

}

// 8. Wait for the player to give the book to the wizard
if (WaitToItemBeTraded($quest_id, $item_ref, $npc_ref) == "pending") {
    error_log("Item not traded, pending ".PHP_EOL);
    return;
} else if (WaitToItemBeTraded($quest_id, $item_ref, $npc_ref) == "failed") {
    error_log("Item not traded, failed after reachinf timeout ".PHP_EOL);
    // Note: Quest can branch here and execute different steps

} if (WaitToItemBeTraded($quest_id, $item_ref, $npc_ref) == "done") {
    error_log("Item traded ".PHP_EOL);
    
} else  // Default case
    return;

// 9. (Optional) Wizard thanks the player
CreateTopic(
    $quest_id,
    "t_thanks",
    "Thank You",
    "Lore",
    null,
    $npc_ref,
    "Thank you for retrieving the ring. Your help is invaluable.",
    "player",
    false   // Not relevant for the quest, so important is false
);

TellTopicToPlayer($quest_id, $npc_ref, "t_thanks");
if (CheckTopicToPlayer($quest_id, "t_thanks") !="done") {
    error_log("Topic not covered ".PHP_EOL);
    return;
}
// 10. Remove the wizard (optional, quest end)
ToGoAway($quest_id, $npc_ref);

CompleteQuest($quest_id);

return;

*/

?>