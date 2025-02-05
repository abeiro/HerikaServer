<?php

if (isset($GLOBALS["FEATURES"]["MISC"]["LIFE_LINK_PLUGIN"])) {
    return;
}

require_once("util.php");

$GLOBALS["UPDATE_PERSONALITY_PROMPT"] = "**Based on the Dialogue History, update the following:** 
RELATIONSHIPS
Relationships between characters. Catch only MAJOR and important details here.
Return relationships description as is for characters whose relationship remains unchanged, don't remove it or replace with some \"no change\".
Only update the relationships for characters whose relationship status or attitude has changed based on the Dialogue History.
Each description 1-3 sentences.

NEEDS
The character's specific, immediate needs or requests, often directed at the player or other NPCs. 
Immediate, Major Short-Term Goals (Around 5 words, Comma-Separated)

DESIRES
The character's primary goals or ambitions, which influence their behavior and decisions. 
Primary, Major Long-Term Ambitions (Around 5 words, Comma-Separated)

**DON'T** ADD ANY STATUSES like \"updated\", \"new\", \"deleted\", \"No change\".

**Mandatory Format:**

Don't use any formatting with asteriks, dashes, hash signs etc...

RELATIONSHIPS
{only npc name}: {relationship description}

NEEDS
{need1}, {need2}

DESIRES
{desire1}, {desire2}

Footnote: 
- OMIT any \"updated\", \"new\", \"deleted\", or \"No change\" status notations from your response.
- Never analyze relationships with The Narrator
- If there is no update to relationship don't change it at all!";

/**
 * This function is used by plugin to update npc config file with updated data when update profile is triggered from CHIM
 * It parses LLM response for update personality and combines it with static personality data, it returs new personality content which is used by CHIM and inserted in npc's config file
 */
$GLOBALS["CustomUpdateProfileFunction"] = function($content) {
    $data = parseUpdate($GLOBALS["HERIKA_NAME"], $content);
    return buildPersonality($data, $GLOBALS["HERIKA_PERS_DYNAMIC"]);
};
