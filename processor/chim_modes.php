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
// * Director. (DIRECTOR)
//      Call instruction directly.
//
// * Spawn Character (SPAWN)
//      Call spawn character directly.
//
// * Easy Roleplay (IMPERSONATION)
//      (Smart Impersonation) (we should need a prompt parameter so user can customice this). 
//      Just prefix two asterisks at user input, and add the prompt. 
//      Example: "Hello" => **(Rewrite and translate the following text into English, employing Skyrim lore language and drawing upon the context.) Hello.
//
// * Easy Roleplay (CREATION)
//      
//      Example: "Speech about being the Dragonborn" => **(Generate text employing Skyrim lore language and drawing upon the context, following the next instruction:Speech about being the Dragonborn ) 
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

if (!in_array($gameRequest[0],["inputtext","inputtext_s","ginputtext","ginputtext_s"])) {
    $EXECUTION_MODE="STANDARD";
}

if ($EXECUTION_MODE=="STANDARD") {


} else if ($EXECUTION_MODE=="WHISPER") {

    
} else if ($EXECUTION_MODE=="DIRECTOR") {
    
    ignore_user_abort(true);

    // Expected format input|ts|gamets|PLAYER_NAME::
    $gameRequest = explode("|", $receivedData);
    
    $userWish=explode(":",$gameRequest[3]);
    $output='';
    $instruction=escapeshellarg("{$userWish[1]}");
    $db->upsertRowOnConflict(
        'conf_opts',
        array(
            'id' => 'chim_mode',
            'value' => 'STANDARD'
        ),
        "id"
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
    $db->upsertRowOnConflict(
        'conf_opts',
        array(
            'id' => 'chim_mode',
            'value' => 'STANDARD'
        ),
        "id"
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

} else if ($EXECUTION_MODE=="IMPERSONATION") {
    
    $gameRequest[3]="**".$gameRequest[3];
    $GLOBALS["PLAYER_RESPEECH"]=true;
    
} else if ($EXECUTION_MODE=="CREATION") {
    
    $gameRequest[3]="**(".$gameRequest[3].")";
    $GLOBALS["PLAYER_RESPEECH"]=true;
    
} else if ($EXECUTION_MODE=="INJECTION_LOG") {
    $cleaned_player_dialogue = preg_replace('/^[^:]+:/', '', $gameRequest[3]);
    $gameRequest[3]="($cleaned_player_dialogue)";
    logEvent($gameRequest);


    
} else if ($EXECUTION_MODE=="INJECTION_CHAT") {
    $cleaned_player_dialogue = preg_replace('/^[^:]+:/', '', $gameRequest[3]);

    $gameRequest[3]="($cleaned_player_dialogue)";

    
}

$CONTEXT_MODE=$db->fetchOne("SELECT value FROM conf_opts WHERE id='chim_context_mode'");
if (isset($CONTEXT_MODE["value"]) && $CONTEXT_MODE["value"]==1) 
    $GLOBALS["CLEAN_CONTEXT_FOCUS_CHAT"]=true;
else
    $GLOBALS["CLEAN_CONTEXT_FOCUS_CHAT"]=false;


?>