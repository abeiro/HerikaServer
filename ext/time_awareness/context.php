<?php

/**
 * Returns the maximum gamets from the "speech" table.
 *
 * @return int|null The current gamets or null on failure.
 */
function getCurrentGamets() {
    global $db;
    $query = "SELECT MAX(gamets) AS current_gamets FROM speech";
    $results = $db->fetchAll($query);
    if (!$results || count($results) === 0) {
        return null;
    }
    return $results[0]['current_gamets'];
}

/**
 * Retrieves the last interaction before the current gamets.
 *
 * @return array|null Returns an associative array with keys 'last_gamets' and 'current_gamets',
 *                    or null if no interaction is found.
 */
function getLastInteractionTime() {
    global $db;
    $playerName = $GLOBALS["PLAYER_NAME"];
    $npcName    = $GLOBALS["HERIKA_NAME"];

    $currentGamets = getCurrentGamets();
    if ($currentGamets === null) {
        return null;
    }

    $query = "
        SELECT gamets AS last_gamets, speaker, listener
        FROM speech 
        WHERE gamets < $currentGamets
          AND listener = '$playerName'
          AND speaker = '$npcName'
        ORDER BY gamets DESC
        LIMIT 1
    ";
    $results = $db->fetchAll($query);
    if ($results && count($results) > 0) {
        return [
            'last_gamets'    => $results[0]['last_gamets'],
            'current_gamets' => $currentGamets
        ];
    }
    return null;
}

/**
 * Computes the in-game time difference based on gamets.
 *
 * @param int $lastGamets The gamets of the last interaction.
 * @param int $currentGamets The current gamets.
 * @return array An associative array with keys: 'seconds', 'hours', and 'days'.
 */
function computeInGameTime($lastGamets, $currentGamets) {
    $timeDiff = $currentGamets - $lastGamets;
    // Conversion factor: 167.63 gamets per in-game second.
    $conversionFactor = 167.63;
    $inGameSeconds = $timeDiff / $conversionFactor;
    $inGameHours   = $inGameSeconds / 3600;
    $inGameDays    = $inGameSeconds / 86400;
    return [
        'seconds' => $inGameSeconds,
        'hours'   => $inGameHours,
        'days'    => $inGameDays
    ];
}

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
        return "It's been a few hours since they last met.";
    } elseif ($inGameDays < 7) {
        return "It's been a few days since they last met.";
    } elseif ($inGameDays < 30) {
        return "It has been weeks since they last met.";
    } elseif ($inGameDays < 365) {
        return "It has been months since they last met.";
    } else {
        $years = floor($inGameDays / 365);
        return "It has been $years year" . ($years > 1 ? "s" : "") . " since they last interacted.";
    }
};

/**
 * Returns a text prompt for an interaction (when previous interactions exist)
 * using the provided time context.
 *
 * @param string $npc The NPC's name.
 * @param string $player The player's name.
 * @param string $timeText The time context text.
 * @return string The prompt text or an empty string if the request type is not supported.
 */
$getInteractionPrompts = function($npc, $player, $timeText) {
	if (in_array($GLOBALS["gameRequest"][0],["radiant","im_alive"])) {
		return "$npc sees $player. $timeText";
	}

	if (in_array($GLOBALS["gameRequest"][0],["inputtext","inputtext_s"])) {
		return "$npc recognizes $player. $timeText";
	}

	return "";
};

/**
 * Returns a text prompt for a first-time interaction.
 *
 * @param string $npc The NPC's name.
 * @param string $player The player's name.
 * @return string The prompt text or an empty string if the request type is not supported.
 */
$getFirstTimePrompts = function($npc, $player) {
	if (in_array($GLOBALS["gameRequest"][0],["radiant","im_alive","inputtext","inputtext_s"])) {
		return "$npc talks to $player for the first time. They haven't yet been acquainted.";
	}

	return "";
};

/**
 * Add time awareness to the prompt if time has passed since the last conversation with the NPC
 */
function getTimePrompt($getTimeText, $getInteractionPrompts, $getFirstTimePrompts) {
    $interaction = getLastInteractionTime();
    $npc    = $GLOBALS["HERIKA_NAME"];
    $player = $GLOBALS["PLAYER_NAME"];
    
    if ($interaction) {
        $timeData = computeInGameTime($interaction['last_gamets'], $interaction['current_gamets']);
        $timeText = $getTimeText($timeData['seconds'], $timeData['days']);
        
        // Only update the prompts if there's a meaningful time difference.
        if (!empty($timeText)) {
            return $getInteractionPrompts($npc, $player, $timeText);
        }

		return "";
    } else {
        return $getFirstTimePrompts($npc, $player);
    }
}

// === Main Flow ===

// override with language-specific functions if available
if (file_exists(__DIR__.DIRECTORY_SEPARATOR."lang".DIRECTORY_SEPARATOR.$GLOBALS['CORE_LANG'].DIRECTORY_SEPARATOR."prompts.php")) {
    require(__DIR__.DIRECTORY_SEPARATOR."lang".DIRECTORY_SEPARATOR.$GLOBALS['CORE_LANG'].DIRECTORY_SEPARATOR."prompts.php");
}

// add prompt to help the AI become more aware of time (if enabled and not talking to the narrator)
if (isset($GLOBALS["TIME_AWARENESS"]) && $GLOBALS["TIME_AWARENESS"] && $GLOBALS["HERIKA_NAME"] != "The Narrator") {
    $additionalPrompt = getTimePrompt($getTimeText, $getInteractionPrompts, $getFirstTimePrompts);
	if ($additionalPrompt) {
		$GLOBALS["request"]="$additionalPrompt {$GLOBALS['request']}";
	}
}

?>
