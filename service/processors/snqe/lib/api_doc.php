<?php 

/*
Skyrim Narrative Quest Engine (SNQE)

API 
All functions take quest_id as the first parameter.
All functions are idempotent: if already executed successfully for a given quest_id, they do nothing.
State is persisted per quest_id to allow quest scripts to resume execution across multiple runs.


1. Creation Functions

* CreateNPC(quest_id, npc_ref, name, gender, class, race, location, appearance, background, speechStyle, disposition)
Declares a new NPC for later spawning.
quest_id (string, required) – Quest identifier.
npc_ref (string, required) – Internal NPC reference ID.
name (string, required) – NPC display name.
gender (string, required) – "Male" or "Female".
class (enum, required) – beggar, warrior, assassin, mage, farmer, soldier, merchant, noble.
race (enum, required) – Nord, Imperial, Argonian, RedGuard, Orc, Breton.
location (string, required) – Initial placement, e.g., "Whiterun" or "nearby".
appearance (string, optional) – Hair, clothes, scars, visual description.
background (string, optional) – Lore or backstory.
speechStyle (string, optional) – How NPC talks (formal, rustic, archaic, etc.).
disposition (enum, optional) – defiant, submissive, friendly, serious, sad, aggressive, cheerful, distrustful, furious, drunk, high.

* CreateItem(quest_id, item_ref, name, type, location, description)
Declares a new item for later spawning.

quest_id (string, required) – Quest identifier.
item_ref (string, required) – Internal item reference ID.
name (string, required) – Item display name.
type (enum, required) – sword, armor, helmet, ring, amulet, book, note, axe, long sword, staff, great axe, bow.
location (enum, required) – "nearby", "major city".
description (string, optional) – Description or content if item is book or note.

* CreateTopic(quest_id, topic_ref, name, type, item, giver, info, target)
Declares a dialogue topic.

quest_id (string, required) – Quest identifier.
topic_ref (string, required) – Internal topic reference ID.
name (string, required) – Display name of the topic.
type (enum, required) – Lore, Item, Location.
item (string, optional) – If type=Item, the item_ref this topic refers to.
giver (string, required) – NPC reference or name giving the topic.
info (string, required) – Dialogue text or explanation.
target (string, required) – NPC reference (char_ref) or "player" who receives the topic.

2. Spawn Functions

Spawn functions initiate asynchronous placement of NPCs or items and must be followed by Check functions.

* SpawnNPC(quest_id, npc_ref, location)

Spawns a declared NPC at a location.

quest_id (string, required)
npc_ref (string, required) – NPC reference created via CreateNPC.
location (string, required) – Placement location.

* CheckNPCSpawn(quest_id, npc_ref, location, maxAttempts)
Checks if NPC has successfully spawned.

quest_id (string, required)
npc_ref (string, required)
location (string, required)
maxAttempts (int, optional, default=5) – Maximum retries before failure.

Returns: true → spawned, false → failed, null → still waiting.

* SpawnItem(quest_id, item_ref, location_or_char_ref)

Spawns a declared item.

quest_id (string, required)
item_ref (string, required)
location_or_char_ref (string, optional) – If omitted → world spawn. If NPC ref → NPC inventory.

* CheckItemSpawn(quest_id, item_ref, location_or_char_ref, maxAttempts)

Checks if item has spawned.

quest_id (string, required)
item_ref (string, required)
location_or_char_ref (string, required)
maxAttempts (int, optional, default=5)

Returns: true → spawned, false → failed, null → still waiting.

3. Interaction Functions

* MoveToPlayer(quest_id, npc_ref, follow)

Orders NPC to move to player.

quest_id (string, required)
npc_ref (string, required)
follow (bool, optional, default=false) – Whether NPC follows the player.

* TellTopicToPlayer(quest_id, npc_ref, topic_ref)

NPC delivers a topic to the player.

quest_id (string, required)
npc_ref (string, required)
topic_ref (string, required)

Quest fails if NPC does not deliver topic within timeout.

* TellTopicToNPC(quest_id, npc_ref, topic_ref, destination_ref)

NPC delivers a topic to another NPC.

quest_id (string, required)
npc_ref (string, required)
topic_ref (string, required)
destination_ref (string, required)

Quest fails if delivery does not occur within timeout.

* ToGoAway(quest_id, npc_ref)

Removes NPC from the world.

quest_id (string, required)
npc_ref (string, required)

* CombatPlayer(quest_id, npc_ref)

NPC attacks the player.

quest_id (string, required)
npc_ref (string, required)

4. Wait Functions

Wait functions pause quest execution until a condition is met, fails, or times out.

* WaitToItemBeRecovered(quest_id, item_ref, timeout)

Waits until the player recovers an item.

quest_id (string, required)
item_ref (string, required)
timeout (int, optional, default=10)

Returns: success | timeout | waiting.

* WaitForCoins(quest_id, npc_ref, amount, timeout)

Waits until the player gives coins to an NPC.

quest_id (string, required)
npc_ref (string, required)
amount (int, required)
timeout (int, optional, default=10)

Returns: success | timeout | waiting.

* WaitToItemBeTraded(quest_id, item_ref, npc_ref, timeout)

Waits until the player gives a specific item to an NPC.

quest_id (string, required)
item_ref (string, required)
npc_ref (string, required)
timeout (int, optional, default=10)

Returns: success | timeout | waiting.

✅ Execution Flow Notes

Create functions are declarative; safe to call multiple times.

Spawn functions trigger asynchronous events; use Check* to confirm.

Wait functions allow branching: success → continue, timeout → alternate path, waiting → pause and retry on next run.


Interaction functions (MoveToPlayer, TellTopic*, CombatPlayer) are executed once and persist state.

*/
?>