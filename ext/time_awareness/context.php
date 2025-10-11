<?php

require_once("/var/www/html/HerikaServer/lib/data_functions.php");
require_once("/var/www/html/HerikaServer/lib/utils_game_timestamp.php");


/**
 * Returns a text description based on the in-game seconds and days elapsed.
 *
 * @param int $firstGamets - game datetime stamp of first interaction 
 * @param int $lastGamets - game datetime stamp of most recent interaction 
 * @param int $currentGamets - current game datetime stamp
 * @param string $npc, $player - interacting NPCs
 * @return string - contextual time text or an empty string 
 */ 
function getTimeText($firstGamets=-1, $lastGamets=-1, $currentGamets=-9, $npc="", $player="") {

    $s_res = "";
    
    $b_speak = (in_array($GLOBALS["gameRequest"][0],["rechat","radiant","inputtext","inputtext_s", "ginputtext", "ginputtext_s"]));
    if ($b_speak) 
    {
        $FirstTimeDiff = intval($currentGamets - $firstGamets);
        $LastTimeDiff = intval($currentGamets - $lastGamets);
        if (($FirstTimeDiff >= 0) && ($LastTimeDiff >= 0)) {
            $FirstTimeSeconds = convert_gamets2seconds($FirstTimeDiff);
            $LastTimeSeconds = convert_gamets2seconds($LastTimeDiff);
            $inGameHours   = floor($LastTimeSeconds / 3600.0);
            $inGameDays    = floor($LastTimeSeconds / 86400.0);

            $sl_never_met       = "{$npc} and {$player} have never met.";
            $sl_first_time      = "{$npc} talks to {$player} for the first time.";
            $sl_first_time_1h   = "{$npc} and {$player} spoke for the first time less than one hour ago.";
            $sl_hours_ago       = "{$npc} and {$player} spoke {$inGameHours} hours ago.";
            $sl_days_ago        = "{$npc} and {$player} spoke {$inGameDays} days ago.";
            $sl_weeks_ago       = "{$npc} and {$player} spoke a few weeks ago.";
            $sl_months_ago      = "{$npc} and {$player} spoke a few months ago.";
            $sl_years_ago       = "{$npc} and {$player} spoke years ago.";
            
            // override with language-specific resource if available
            $s_lang = strtolower(substr(($GLOBALS['CORE_LANG'] ?? ""), 0, 2));
            if ((strlen($s_lang) == 2) && ($s_lang != 'en')) {
                $ta_translation_file = __DIR__."/lang/".$s_lang."/ta_translation.php";
                if (file_exists($ta_translation_file)) {
                    include($ta_translation_file);
                }
            }

            if ($firstGamets == 0) {
                $s_res = $sl_never_met; //"{$npc} and {$player} have never met.";      
            } elseif ($FirstTimeSeconds < 1200) {
                $s_res = $sl_first_time; //"{$npc} talks to {$player} for the first time.";       
            } elseif ($FirstTimeSeconds < 3600) {
                $s_res = $sl_first_time_1h; //"{$npc} and {$player} spoke for the first time less than one hour ago.";
            } elseif ($inGameHours < 25) {
                $s_res = ""; 
            } elseif ($inGameHours < 72) {
                $s_res = $sl_hours_ago; //"{$npc} and {$player} spoke {$inGameHours} hours ago.";
            } elseif ($inGameDays < 15) {
                $s_res = $sl_days_ago; //"{$npc} and {$player} spoke {$inGameDays} days ago.";
            } elseif ($inGameDays < 31) {
                $s_res = $sl_weeks_ago ; //"{$npc} and {$player} spoke a few weeks ago.";
            } elseif ($inGameDays < 366) {
                $s_res = $sl_months_ago; //"{$npc} and {$player} spoke a few moths ago.";
            } elseif ($inGameDays > 365) {
                $s_res = $sl_years_ago; //"{$npc} and {$player} spoke years ago.";
            }
            if (strlen($s_res) > 0) {
                $s_res = "(" . $s_res . ")";    
                //error_log("time awareness: {$s_res} | ". $GLOBALS["gameRequest"][0]. " | $s_lang | $b_speak | $FirstTimeSeconds | $LastTimeSeconds - exec trace "); // debug
            }
        } else
            error_log(" Warning - time awareness wrong timestamp values! first=$FirstTimeDiff last=$LastTimeDiff crt=$currentGamets");
    }

    return $s_res;
};


/**
 * Add time awareness to the prompt if time has passed since the last conversation with the NPC
 */
function getTimePrompt() {
    $s_res = "";
    $npc    = $GLOBALS["HERIKA_NAME"] ?? "";
    $player = $GLOBALS["PLAYER_NAME"] ?? "";
    $haystack = " {$player} {$npc} ";
    if ((strlen($npc) > 0) && (strlen($player) > 0) && (stripos($haystack , 'narrator') === false) && 
        (stripos($haystack , 'player') === false) && (stripos($haystack , 'prisoner') === false)) {
        
        $currentGamets = intval(DataLastKnownGameTS()); //getCurrentGamets();
        if ($currentGamets > 0) {
            $first_met = GetFirstTimeMet($player, $npc);
            if ($first_met > 0)
                $last_met = GetLastInteraction($player, $npc); 
            else 
                $last_met = 0;
            $s_res = getTimeText($first_met, $last_met, $currentGamets, $npc, $player);
        }
    } 
    return $s_res;
}

// === Main Flow ===

// add prompt to help the AI become more aware of time (if enabled and not talking to the narrator)
// FORCED DISABLED: ensure TIME_AWARENESS never triggers
if (false && isset($GLOBALS["TIME_AWARENESS"]) && $GLOBALS["TIME_AWARENESS"]) {
    $additionalPrompt = getTimePrompt();
	if (strlen($additionalPrompt) > 0) {
		$GLOBALS["request"]="{$additionalPrompt} {$GLOBALS['request']}";
	}
}

?>