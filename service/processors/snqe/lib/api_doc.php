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
class (enum, required) – beggar, warrior, assassin, mage, farmer, soldier, merchant, noble,creature,forsworn.
race (enum, required) – Nord, Imperial, Argonian, RedGuard, Orc, Breton.draugr,elk,frost_troll,frostbite_spider,dwarven_sphere_guardian,falmer,giant
location (string, required) – Default placement, e.g., "Whiterun" or "nearby".
appearance (string, optional) – Hair, clothes, scars, visual description.
background (string, optional) – Lore or backstory. Should be about 200 character long for good roleplay. 
speechStyle (string, optional) – How NPC talks (formal, rustic, archaic, etc., cursed words, uses specific fillir words).
disposition (enum, optional) – defiant, submissive, friendly, serious, sad, aggressive, cheerful, distrustful, furious, drunk, high,dead.
goal (string, optional) – defiant, submissive, friendly, serious, sad, aggressive, cheerful, distrustful, furious, drunk, high.

* CreateItem(quest_id, item_ref, name, type, location, description, npc_ref)
Declares a new item for later spawning or reference. **Items in pockets should be spawned too**

quest_id (string, required) – Quest identifier.
item_ref (string, required) – Internal item reference ID.
name (string, required) – Item display name.
type (enum, required) –  potion, necklace, amulet, ring, book, axe, note, dagger,armor.
location (enum, required) – "nearby", "major city", or "location" (dungeons allowed), or near and element reference (e.g., "Pedestal:0x00027f92").
description (string) – Description, or **full** content if item is book or note.
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
Checks if NPC has successfully spawned. *This function must be called after SpawnNPC to confirm placement.*

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

Returns void


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

This function does not need to be waited on. It issues the command and returns immediately.
Returns: "done" | "pending" | "failed".

* CombatPlayer(quest_id, npc_ref) (Make sure npc_ref has been created and spawned)

Orders an NPC to engage the player in combat. NPC should have a good reason to combat AGAINST player, like being an enemy.

quest_id (string, required)
npc_ref (string, required)

Returns: void

Notes:

Initiates a combat between the NPC and the player.
**YOU MUST USE WaitforCombatEnd to wait for completion.**
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
**YOU MUST USE WaitforCombatEnd to wait for completion.**

Idempotent: if already ordered to combat, does nothing.
Both NPCs must be created via CreateNPC first.
Useful for PvP encounters, NPC duels, or NPC betrayals in quest sequences.

4. Wait Functions

Wait functions pause quest execution until a condition is met, fails, or times out.

* WaitToItemBeRecovered(quest_id, item_ref, timeout)

Waits until the player recovers an item. (Make sure item has been spawned using SpawnItem and CheckItemSpawn)

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

* PickUpItem(quest_id, npc_ref, item_ref)

Immediately marks an item as picked up by an NPC. Used to command an NPC to pick up an item from the ground or from a container.
Do not use if item already on pocket.
quest_id (string, required)
npc_ref (string, required) – NPC reference created via CreateNPC.
item_ref (string, required) – Item reference created via CreateItem.

Returns: void. Updates quest state to track item pickup.

Notes:

This is an action function that directly marks an item as picked up by the NPC.
Updates internal inventory tracking in quest data.
Use PickUpItem before WaitForPickUpItem to initiate the pickup action.

* WaitForPickUpItem(quest_id, npc_ref, item_ref, maxAttempts)

Waits until an NPC picks up a specific item. Monitors event log for pickup confirmation.

quest_id (string, required)
npc_ref (string, required) – NPC reference created via CreateNPC.
item_ref (string, required) – Item reference created via CreateItem.
maxAttempts (int, optional, default=1000) – Maximum retries before failure.

Returns: "done" | "waiting" | "failed".

Notes:

Make sure item has been spawned using SpawnItem and CheckItemSpawn before calling this.
Tracks pickup_attempts per NPC-item combination.
Creates scenenotes to guide the player on what the NPC should do.
Queries eventlog for 'itempickup' events matching NPC and item names.
Function is idempotent; if the item is already picked up, it returns "done".

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
Function is idempotent. If the player already reached the location, it returns "done".

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
Function is idempotent. If the player already reached the location, it returns "done".

* WaitForActivation(quest_id, activator_ref, maxAttempts)

Waits until the player activates a specified activator object (e.g., furniture, switch, lever, pedestal).
This is a blocking wait that pauses quest execution until the activator is activated.

quest_id (string, required) – Quest identifier.
activator_ref (string, required) – Activator reference in format "Name:0xHEXID" or "0xHEXID".
  Examples: "Pedestal:0x00027f92" or "0x00027f92"
maxAttempts (int, optional, default=1000) – Maximum retries before failure.

Returns: "done" | "waiting"

"done" → Activator has been successfully activated by the player.
"waiting" → Activator has not yet been activated; further checks required.

Notes:

Queries the eventlog for activation events matching the pattern "X activates Y".
Tracks activation state per activator (by form ID) in quest data.
Function is idempotent. If the activator was already activated, it returns "done".
Creates/updates a scenenote to guide the player on what needs to be activated.
Useful for puzzle elements, door/gate activation, furniture interaction requirements, etc.
Tracks activation_attempts in quest data for debugging purposes.

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
npc_ref (string, optional) – NPC reference ID. If provided, the NPC will travel to that location and will wait for the player.

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

* TravelTo(quest_id, location, npc_ref)

Issues a travel instruction to move to a specific location and returns "done" immediately.
Uses the same instruction mechanism as WaitAtLocation but completes on first call without waiting for confirmation.

quest_id (string, required) – Quest identifier.
location (string, required) – Location name to travel to (e.g., "Whiterun", "Markarth", "Solitude").
npc_ref (string, optional) – NPC reference ID. If provided, the NPC will travel to that location. If omitted, the player receives the instruction.

Returns: "done"

"done" → Travel instruction has been issued successfully.

Notes:

Always returns "done" on first call after issuing the instruction.
Subsequent calls for the same location return "done" without re-issuing instructions (idempotent).
Issues background command for NPC travel if NPC is not present.
Issues foreground suggestion for NPC travel if NPC is present in the scene.
Issues generic scene note for player travel if no npc_ref is provided.
Does not wait for the travel to be completed; use WaitAtLocation if you need to verify player arrival.
Useful for quest steps where you want to initiate travel without blocking on completion.
Tracks travel state per location in quest data to prevent duplicate instructions.
Function is idempotent. If the player or NPC has already been instructed to travel to the location, it returns "done".

* StationAtLocation(quest_id, location, npc_ref)

Non-blocking version of WaitAtLocation. Issues an instruction to station an NPC at a location and returns true immediately.
Does not wait for confirmation that the NPC has arrived; completes on first call without blocking.

quest_id (string, required) – Quest identifier.
location (string, required) – Location name to station NPC at (e.g., "Whiterun", "Markarth", "Solitude").
npc_ref (string, optional) – NPC reference ID. If provided, the NPC will be stationed at that location.

Returns: bool (always true after first execution)

true → Station instruction has been issued successfully.

Notes:

Always returns true on first call after issuing the instruction.
Subsequent calls for the same location and NPC return true without re-issuing instructions (idempotent).
Issues background command for NPC travel if NPC is not present.
Issues foreground suggestion for NPC travel if NPC is present in the scene.
Does not wait for the NPC to reach the location; completes immediately upon instruction issue.
Non-blocking: execution continues immediately without waiting for NPC arrival confirmation.
Useful for quest steps where you want to position an NPC at a location as part of scene setup without blocking progression.
Tracks station state per location and NPC in quest data to prevent duplicate instructions.
Function is idempotent. If the NPC has already been instructed to station at the location, it returns true.

* CompleteQuest(quest_id, result)

MANDATORY. Marks a quest as finished and updates its state. This function is intended to be called as the final step in a quest sequence.

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

1. Create functions should be at the top of the code.
2. Besides create functions, code should follow original instructions order. **very important**.
3. Respect instructions order.

Example quest #1:

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
    "formal",   //speech style
    "serious",  //disposition
    "Wants to recover the Ancient Ring to unlock hidden powers."
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


// We could use WaitAtLocation here if we want the wizard to stay there until player finds him
// This is prefered when spawning location is not nearby

// if (WaitAtLocation($quest_id, "Remote Location", 10000,$ npc_ref) != "done") {
//    error_log("NPC waiting at location".PHP_EOL);
// }

// If NPC was spawned nearby, just TellTopicToPlayer

// 5. Wizard tells the player about the quest. We have ensured topic is created by CreateTopic, and NPC exists by CreateNPC

if (TellTopicToPlayer($quest_id, $npc_ref, "t_ask_ring") !="done") {
    error_log("TellTopicToPlayer failed, will retry".PHP_EOL);
    return;
}

if (CheckTopicToPlayer($quest_id, "t_ask_ring") !="done") {
    error_log("Topic not covered ".PHP_EOL);
    return;
}

// 6. Spawn the book somewhere nearby, note this is done after the wizard talks to player
SpawnItem($quest_id, $item_ref, "nearby");
if (CheckItemSpawn($quest_id, $item_ref)!="done") {
    error_log("Item not spawned <SpawnItem($quest_id, $item_ref>".PHP_EOL);
    return;
}

// 7. Wait for the player to recover the book . Yes, We have checked item has been spawned using SpawnItem and CheckItemSpawn.
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
// 10. Remove the wizard (optional, NPC says farewell) and we are not going  to need NPC more in the future)
ToGoAway($quest_id, $npc_ref);

// Mandatory, always end with CompleteQuest
CompleteQuest($quest_id);

return;


* Example Quest #2: Combat quest

$quest_id = "bandit_camp_encounter_6941a5684e7b4";

// Create and spawn bandits
CreateNPC($quest_id, "grimvar", "Grimvar the Ruthless", "Male", "warrior", "Nord", "nearby", "Scarred face, heavy armor", "A seasoned bandit with a reputation for brutality.", "Gruff and menacing", "aggressive");
SpawnNPC($quest_id, "grimvar", "nearby");
if (CheckNPCSpawn($quest_id, "grimvar") != "done") return;

CreateNPC($quest_id, "eira", "Eira Shadowglow", "Female", "assassin", "Nord", "nearby", "Dark cloak, dagger", "A stealthy bandit skilled in ambushes.", "Soft-spoken and sinister", "aggressive");
SpawnNPC($quest_id, "eira", "nearby");
if (CheckNPCSpawn($quest_id, "eira") != "done") return;

CreateNPC($quest_id, "thoren", "Thoren Stonefist", "Male", "warrior", "Nord", "nearby", "Massive build, warhammer", "A burly bandit with immense strength.", "Loud and boastful", "aggressive");
SpawnNPC($quest_id, "thoren", "nearby");
if (CheckNPCSpawn($quest_id, "thoren") != "done") return;


// Combat instructions
CombatPlayer($quest_id, "grimvar");
CombatPlayer($quest_id, "eira");
CombatPlayer($quest_id, "thoren");

if ( WaitforCombatEnd($quest_id,"eira") == "pending") {
    error_log("Still in combat ".PHP_EOL);
    return;
):

if ( WaitforCombatEnd($quest_id,"thoren") == "pending") {
    error_log("Still in combat ".PHP_EOL);
    return;
):

if ( WaitforCombatEnd($quest_id,"grimvar") == "pending") {
    error_log("Still in combat ".PHP_EOL);
    return;
):

* Example Quest #3 : Recover Ring from Necromancer

$quest_id = "darkshade_copse_investigation_694af5596a1ea";

// 1. Create and spawn Xaren the Necromancer (from <spawn> element)
CreateNPC(
    $quest_id,
    "xaren",
    "Xaren the Necromancer",
    "Male",
    "mage",
    "Breton",
    "nearby",
    "Dark robes, glowing staff",
    "A dark sorcerer specializing in necromancy, feared for his mastery over the undead.",
    "Speaks in a cold, calculated tone, often whispering incantations.",
    "aggressive"
);
SpawnNPC($quest_id, "xaren", "nearby");
if (CheckNPCSpawn($quest_id, "xaren") != "done") return;

// 2. Create Zara Quill (from <instruction> element - already spawned)
CreateNPC(
    $quest_id,
    "zara",
    "Zara Quill",
    "Female",
    "warrior",
    "Nord",
    "nearby",
    "Leather armor, bow",
    "A skilled ranger and investigator.",
    "Direct and practical",
    "serious"
);

// 3. Create Roric's Ring (from <item> element)
CreateItem(
    $quest_id,
    "roric_ring",
    "Roric's Ring",
    "ring",
    "pocket",
    "A simple silver ring with a small engraving of a scouting compass, a family heirloom passed down through Roric's family.",
    "xaren"
);

// Spawn item before combat/interactions, but after spawning NPC, because is in Xaren's inventory. 
// This is important because if we spawn it before Xaren is spawned, the item will not be in his inventory and will be lost.
// and if spawned after combat, NPC could be an ash pile.

SpawnItem($quest_id, "roric_ring", "xaren");
if (CheckItemSpawn($quest_id, "roric_ring") != "done") return;

// 4. Travel to Darkshade Copse
TravelTo($quest_id, "Darkshade Copse", "zara");

// 5. Combat between Xaren and Zara
CombatNPC($quest_id, "xaren", "zara");
CombatNPC($quest_id, "zara", "xaren");

// Wait for combat to end
if (WaitForNPCCombatEnd($quest_id, "xaren", "zara") != "done") return;

// 6. Wait for ring recovery

// Now we spawn the item, after combat ends
SpawnItem($quest_id, "roric_ring", "xaren");
if (CheckItemSpawn($quest_id, "roric_ring") != "done") return;
if (WaitToItemBeRecovered($quest_id, "roric_ring") != "done") return;

// 7. Zara tells player about the ring
CreateTopic(
    $quest_id,
    "t_roric_ring",
    "Roric's Ring Discovery",
    "Item",
    "roric_ring",
    "zara",
    "This is Roric's ring... I knew something terrible had happened to him.",
    "player",
    true
);
TellTopicToPlayer($quest_id, "zara", "t_roric_ring");
if (CheckTopicToPlayer($quest_id, "t_roric_ring") != "done") return;

// Complete quest
CompleteQuest($quest_id);
*/

?>