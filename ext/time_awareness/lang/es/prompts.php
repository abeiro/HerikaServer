<?php

/**
 * Returns a text description based on the in-game seconds and days elapsed.
 *
 * @param float $inGameSeconds The number of in-game seconds elapsed.
 * @param float $inGameDays The number of in-game days elapsed.
 * @return string The contextual time text or an empty string if too little time has passed.
 */
$getTimeText = function($inGameSeconds, $inGameDays) {
    // If less than 12 in-game hours (43,200 seconds) have passed, no text is added.
    if ($inGameSeconds < 43200) {
        return "";
    }
    if ($inGameDays < 1) {
        return "Han pasado unas horas desde su última interacción.";
    } elseif ($inGameDays < 7) {
        return "Han pasado unos días desde su última interacción.";
    } elseif ($inGameDays < 30) {
        return "Han pasado semanas desde su última interacción.";
    } elseif ($inGameDays < 365) {
        return "Han pasado meses desde su última interacción.";
    } else {
        $years = floor($inGameDays / 365);
        return "Ha pasado $years año" . ($years > 1 ? "s" : "") . " desde su última interacción.";
    }
};


/**
 * Sets the global PROMPTS for an interaction (when previous interactions exist)
 * using the provided time context.
 *
 * @param string $npc The NPC's name.
 * @param string $player The player's name.
 * @param string $timeText The time context text.
 * @return string The prompt text or an empty string if the request type is not supported.
 */
$getInteractionPrompts = function($npc, $player, $timeText) {
    if (in_array($GLOBALS["gameRequest"][0],["radiant","im_alive"])) {
        return "$npc ve a $player. $timeText";
    }

    if (in_array($GLOBALS["gameRequest"][0],["inputtext","inputtext_s"])) {
        return "$npc reconoce a $player. $timeText";
    }

    return "";
};

/**
 * Sets the global PROMPTS for a first-time interaction.
 *
 * @param string $npc The NPC's name.
 * @param string $player The player's name.
 * @return string The prompt text or an empty string if the request type is not supported.
 */
$getFirstTimePrompts = function($npc, $player) {
    if (in_array($GLOBALS["gameRequest"][0],["radiant","im_alive","inputtext","inputtext_s"])) {
        return "$npc habla con $player por primera vez. Aún no se conocen.";
    }

    return "";
};

?>
