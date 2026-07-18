<?php 

// Check modes should be here
// * Standard (STANDARD)
//      - when using text input, Easy Roleplay can be done just by prepending ** to the text)
//      Example:**(create a long speech about being the Dragonborn) => I am no mere wanderer upon these snow-bitten roads. I am Dovahkiin...
//      Example:**you're like a zombie => By the Nine, thou walk’st with the stench of the draugr—undead, cursed, and far from Sovngarde’s grace
//      - when using text input, you can achieve Event Injection With Response just putting text bewteen parenthesys
//      Example:(Volkur falls to the ground wounded)
//
// * Whisper (WHISPER)
//      (When enabled, we should send to plugin via InternalSetting a reduced DISTANCE_ACTIVATING_NPC,
//      from this point, all NPC beyond that distance should be marked as far away, We must take this in 
//      account to only store people NOT far away on eventlog (so far away NPCs won't have access to this context).
//       If player is in stealh mode, no rechat (this is a standard behavior).
//
// * Narrator (NARRATOR)
//      Routes player speech privately to The Narrator only, using narrator_inputtext semantics.
//
// * Director. (DIRECTOR)
//      Call instruction directly.
//
// * Spawn Character (SPAWN)
//      Call spawn character directly.
//
// * Cheat Mode (CHEATMODE)
//      Processes ALL user input through cheatmode function (no # prefix required).
//      Sends input wrapped in <> brackets directly to LLM with functions enabled.
//      NPCs will execute whatever action/command is requested.
//      Example: "give me 1000 gold" => <give me 1000 gold>
//
// * Auto Chat (AUTOCHAT)
//      Generates clean text following player instructions using Skyrim lore language.
//      Wraps input with **() to generate contextual text without stage directions.
//      Example: "Speech about being the Dragonborn" => **(Generate text employing Skyrim lore language and drawing upon the context, following the next instruction:Speech about being the Dragonborn)
//      Example: "Hello" => **(Hello) 
//
// * Event Injection (INJECTION_LOG)
//      (Whatever is typed/said is injected into event log as an roleplay instruction)
//      Just store player speech on eventlog and die.
//
// * Event Injection With Response  (INJECTION_CHAT)
//      (Whatever is typed/said is injected into event log as an roleplay instruction expecting response)
//      Just store player speech on eventlog and follow the standard flow.

if (!isset($db)) $db = new sql();

$EXECUTION_MODE_=$db->fetchOne("SELECT value FROM conf_opts WHERE id='chim_mode'");
$EXECUTION_MODE=isset($EXECUTION_MODE_["value"])?$EXECUTION_MODE_["value"]:"STANDARD";

$EXECUTION_MODE=strtoupper($EXECUTION_MODE);

if (!in_array($gameRequest[0],["inputtext","inputtext_s","ginputtext","ginputtext_s","narrator_inputtext"])) {
    $EXECUTION_MODE="STANDARD";
}

// Store globally for later use (e.g., updating speech table after LLM response)
$GLOBALS["CHIM_EXECUTION_MODE"] = $EXECUTION_MODE;

if ($EXECUTION_MODE=="STANDARD") {


} else if ($EXECUTION_MODE=="WHISPER") {
    // Hard whisper range for CHIM whisper mode.
    $GLOBALS["WHISPER_RANGE"] = 200;
    
    // Send commands to plugin to reduce NPC detection range to whisper distance
    $GLOBALS["db"]->insert(
        'responselog',
        array(
            'localts' => time(),
            'sent' => 0,
            'actor' => "rolemaster",
            'text' => '',
            'action' => "rolecommand|SetConf@_max_distance_outside@{$GLOBALS["WHISPER_RANGE"]}@0@",
            'tag' => ""
        )
    );
    $GLOBALS["db"]->insert(
        'responselog',
        array(
            'localts' => time(),
            'sent' => 0,
            'actor' => "rolemaster",
            'text' => '',
            'action' => "rolecommand|SetConf@_max_distance_inside@{$GLOBALS["WHISPER_RANGE"]}@0@",
            'tag' => ""
        )
    );
    
    // Disable rechat when player is sneaking (handled by plugin side based on stealth state)

} else if ($EXECUTION_MODE=="NARRATOR") {
    if (in_array($gameRequest[0],["inputtext","inputtext_s","ginputtext","ginputtext_s","narrator_inputtext"], true)) {
        $gameRequest[0] = "narrator_inputtext";
    }
    
} else if ($EXECUTION_MODE=="DIRECTOR") {
    
    ignore_user_abort(true);

    // Expected format input|ts|gamets|PLAYER_NAME::
    $gameRequest = explode("|", $receivedData);
    
    $userWish=explode(":",$gameRequest[3]);
    $output='';
    $instruction=escapeshellarg("{$userWish[1]}");
    $db->upsertRow(
        'conf_opts',
        array(
            'id' => 'chim_mode',
            'value' => 'STANDARD'
        ),
        "id='chim_mode'"
    );
    exec("php /var/www/html/HerikaServer/service/manager.php rolemaster instruction \"$instruction\" notify", $output, $returnCode);
    terminate();

} else if ($EXECUTION_MODE=="SPAWN") {
    ignore_user_abort(true);

    // Expected format input|ts|gamets|PLAYER_NAME::
    $gameRequest = explode("|", $receivedData);
    
    $userWish=explode(":",$gameRequest[3]);
    $output='';
    $instruction=escapeshellarg("{$userWish[1]}");
    $db->upsertRow(
        'conf_opts',
        array(
            'id' => 'chim_mode',
            'value' => 'STANDARD'
        ),
        "id='chim_mode'"
    );
    $GLOBALS["db"]->insert(
        'responselog',
            array(
                'localts' => time(),
                'sent' => 0,
                'actor' => "rolemaster",
                'text' => '',
                'action' => "rolecommand|DebugNotification@Spawn instruction processed, back to standard mode",
                'tag' => ""
            )
        );
    exec("php /var/www/html/HerikaServer/service/manager.php rolemaster spawn \"$instruction\"", $output, $returnCode);
    terminate();

} else if ($EXECUTION_MODE=="CHEATMODE") {
    // Process all input as cheat commands
    $cleaned_player_dialogue = preg_replace('/^[^:]+:/', '', $gameRequest[3]);
    $newSpeech = strtr($cleaned_player_dialogue, ["#"=>""]);
    $gameRequest[0] = "cheatmode";
    $gameRequest[3] = "<$newSpeech>";
    $GLOBALS["FUNCTIONS_ARE_ENABLED"] = true;
    
} else if ($EXECUTION_MODE=="AUTOCHAT") {
    
    $cleaned_player_dialogue = preg_replace('/^[^:]+:\s*/', '', $gameRequest[3]);
    $gameRequest[3]="**(".trim($cleaned_player_dialogue).")";
    $GLOBALS["PLAYER_RESPEECH"] = true; // Route through player_rewrite.php for bio/speech style context
    
} else if ($EXECUTION_MODE=="INJECTION_LOG") {
    $cleaned_player_dialogue = preg_replace('/^[^:]+:\s*/', '', $gameRequest[3]);
    $gameRequest[3]="(".trim($cleaned_player_dialogue).")";
    logEvent($gameRequest);
    terminate();
    
} else if ($EXECUTION_MODE=="INJECTION_CHAT") {
    $cleaned_player_dialogue = preg_replace('/^[^:]+:\s*/', '', $gameRequest[3]);

    $gameRequest[3]="(".trim($cleaned_player_dialogue).")";

    
}

$CONTEXT_MODE=$db->fetchOne("SELECT value FROM conf_opts WHERE id='chim_context_mode'");
if (isset($CONTEXT_MODE["value"]) && $CONTEXT_MODE["value"]==1) 
    $GLOBALS["CLEAN_CONTEXT_FOCUS_CHAT"]=true;
else
    $GLOBALS["CLEAN_CONTEXT_FOCUS_CHAT"]=false;

// Restore normal distances when leaving whisper mode
if ($EXECUTION_MODE != "WHISPER") {
    // Check if we were previously in whisper mode and need to restore
    $prevMode = $db->fetchOne("SELECT value FROM conf_opts WHERE id='chim_mode_previous'");
    if (isset($prevMode["value"]) && strtoupper($prevMode["value"]) == "WHISPER") {
        // Restore normal distances (2400 outdoors, 1200 indoors)
        $GLOBALS["db"]->insert(
            'responselog',
            array(
                'localts' => time(),
                'sent' => 0,
                'actor' => "rolemaster",
                'text' => '',
                'action' => "rolecommand|SetConf@_max_distance_outside@2400@0@",
                'tag' => ""
            )
        );
        $GLOBALS["db"]->insert(
            'responselog',
            array(
                'localts' => time(),
                'sent' => 0,
                'actor' => "rolemaster",
                'text' => '',
                'action' => "rolecommand|SetConf@_max_distance_inside@1200@0@",
                'tag' => ""
            )
        );
    }
}

// Store current mode as previous for next check
$db->upsertRow(
    'conf_opts',
    array(
        'id' => 'chim_mode_previous',
        'value' => $EXECUTION_MODE
    ),
    "id='chim_mode_previous'"
);


?>
