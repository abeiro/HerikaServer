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
//      Uses the plugin-provided reduced routing radius and private server context.
//
// * Close (CLOSE)
//      Uses the plugin-provided 200-unit private routing scope. Only the player
//      and resolved responder are admitted to the conversation context.
//
// * Narrator (NARRATOR)
//      Routes player speech privately to The Narrator only, using narrator_inputtext semantics.
//
// * Director. (DIRECTOR)
//      Call instruction directly.
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
$PLAYER_INPUT_REQUEST = in_array(
    $gameRequest[0],
    ["inputtext", "inputtext_s", "ginputtext", "ginputtext_s", "narrator_inputtext"],
    true
);
$SYMBOL_MODE_OVERRIDE = false;
$CHAT_SHORTCUT_ROUTED = ($GLOBALS["CHIM_CHAT_SHORTCUT_ROUTED"] ?? false) === true;

if ($PLAYER_INPUT_REQUEST && $CHAT_SHORTCUT_ROUTED && isset($gameRequest[3]) && is_string($gameRequest[3])) {
    $speakerSeparator = strpos($gameRequest[3], ":");
    $speakerPrefix = $speakerSeparator === false ? "" : substr($gameRequest[3], 0, $speakerSeparator + 1);
    $playerDialogue = $speakerSeparator === false
        ? $gameRequest[3]
        : substr($gameRequest[3], $speakerSeparator + 1);
    $symbolMode = chimParseChatModeShortcut($playerDialogue);

    if ($symbolMode["matched"]) {
        if ($symbolMode["content"] === "") {
            Logger::warn("[chim_modes] Ignored symbol-only player submission");
            terminate();
        }

        $SYMBOL_MODE_OVERRIDE = true;
        $EXECUTION_MODE = $symbolMode["mode"];
        $gameRequest[3] = $speakerPrefix . $symbolMode["content"];
    }
}
$REQUEST_LOCAL_MODE_OVERRIDE = $SYMBOL_MODE_OVERRIDE;

// Retire the old free-form Spawn mode without leaving upgraded installs stuck in it.
if ($EXECUTION_MODE === "SPAWN") {
    $db->upsertRow(
        'conf_opts',
        [
            'id' => 'chim_mode',
            'value' => 'STANDARD',
        ],
        "id='chim_mode'"
    );
    $EXECUTION_MODE = "STANDARD";
}

if (!$PLAYER_INPUT_REQUEST) {
    $EXECUTION_MODE="STANDARD";
}

// Store globally for later use (e.g., updating speech table after LLM response)
$GLOBALS["CHIM_EXECUTION_MODE"] = $EXECUTION_MODE;

if ($EXECUTION_MODE=="STANDARD") {


} else if ($EXECUTION_MODE=="WHISPER") {
    // Routing distance is request-local and supplied by the CHIM plugin.

} else if ($EXECUTION_MODE=="CLOSE") {
    // Routing distance and private audience are supplied by the CHIM plugin.

} else if ($EXECUTION_MODE=="NARRATOR") {
    if (in_array($gameRequest[0],["inputtext","inputtext_s","ginputtext","ginputtext_s","narrator_inputtext"], true)) {
        $gameRequest[0] = "narrator_inputtext";
    }
    
} else if ($EXECUTION_MODE=="DIRECTOR") {
    
    ignore_user_abort(true);

    $userWish = preg_replace('/^[^:]+:\s*/', '', $gameRequest[3]);
    $output='';
    $instruction=escapeshellarg($userWish);
    if (!$REQUEST_LOCAL_MODE_OVERRIDE) {
        $db->upsertRow(
            'conf_opts',
            array(
                'id' => 'chim_mode',
                'value' => 'STANDARD'
            ),
            "id='chim_mode'"
        );
    }
    exec("php /var/www/html/HerikaServer/service/manager.php rolemaster instruction \"$instruction\" notify", $output, $returnCode);
    terminate();

} else if ($EXECUTION_MODE=="CHEATMODE") {
    // Process all input as cheat commands
    $cleaned_player_dialogue = preg_replace('/^[^:]+:/', '', $gameRequest[3]);
    $newSpeech = $REQUEST_LOCAL_MODE_OVERRIDE
        ? $cleaned_player_dialogue
        : strtr($cleaned_player_dialogue, ["#"=>""]);
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
    $GLOBALS["FOCUS_CHAT_MODE"]=true;
else
    $GLOBALS["FOCUS_CHAT_MODE"]=false;

?>
