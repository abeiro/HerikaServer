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
        return "Minęło kilka godzin od ich ostatniej interakcji.";
    } elseif ($inGameDays < 7) {
        return "Minęło kilka dni od ich ostatniej interakcji.";
    } elseif ($inGameDays < 30) {
        return "Minęło kilka tygodni od ich ostatniej interakcji.";
    } elseif ($inGameDays < 365) {
        return "Minęło kilka miesięcy od ich ostatniej interakcji.";
    } else {
        $years = floor($inGameDays / 365);
        return "Minęło $years rok" . ($years > 1 ? "ów" : "") . " od ich ostatniej interakcji.";
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
        return "$npc widzi $player. $timeText";
    }

    if (in_array($GLOBALS["gameRequest"][0],["inputtext","inputtext_s"])) {
        return "$npc rozpoznaje $player. $timeText";
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
        return "$npc rozmawia z $player po raz pierwszy. Nie znają się jeszcze.";
    }

    return "";
};

?>
