<?php

require_once("util.php");

error_log("LifeLink GLOBALS");

$GLOBALS["UPDATE_PERSONALITY_PROMPT"] = "**Based on the Dialogue History, update the following:** 
RELATIONSHIPS
Relationships between characters. Catch only MAJOR and important details here. 
Keep untouched relationships which weren't affected by dialogue or decisions. Each description 1-3 sentences.

NEEDS
The character's specific, immediate needs or requests, often directed at the player or other NPCs. 
Immediate, Major Short-Term Goals (Around 5 words, Comma-Separated)

DESIRES
The character's primary goals or ambitions, which influence their behavior and decisions. 
Primary, Major Long-Term Ambitions (Around 5 words, Comma-Separated)

DON'T ADD ANY \"updated\", \"new\", \"deleted\", \"No change\" STATUSES.

**Mandatory Format:**

RELATIONSHIPS
{npc1 full name}: {relationship description}
{npc2 full name}: {relationship description}
{npcN full name}: {relationship description}

NEEDS
{need1}, {need2}

DESIRES
{desire1}, {desire2}

Footnote: 
- OMIT any \"updated\", \"new\", \"deleted\", or \"No change\" status notations from your response.
- Never analyze relationships with The Narrator
- If there is no update to relationship don't change it at all!";

$GLOBALS["CustomUpdateProfileFunction"] = function($content) {
    error_log("CustomUpdateProfileFunction");
    $data = parseUpdate($GLOBALS["HERIKA_NAME"], $content);
    return buildPersonality($data, $GLOBALS["HERIKA_PERS_STATIC"]);
};