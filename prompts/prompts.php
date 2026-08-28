<?php

require_once("dialogue_prompt.php");

// Helper function to check if RPG comment should trigger based on type and probability
function shouldTriggerRPGComment($eventType) {
    // Check if this event type is enabled
    if (empty($GLOBALS["RPG_COMMENTS"]) || !in_array($eventType, $GLOBALS["RPG_COMMENTS"])) {
        return false;
    }
    
    // Get the trigger chance percentage (default 50%)
    $chance = 50;
    if (isset($GLOBALS["RPG_COMMENTS_CHANCE"])) {
        $chance = intval($GLOBALS["RPG_COMMENTS_CHANCE"]);
    }
    
    // Clamp chance to 0-100
    $chance = max(0, min(100, $chance));
    
    // If chance is 100, always trigger
    if ($chance >= 100) {
        return true;
    }
    
    // If chance is 0, never trigger
    if ($chance <= 0) {
        return false;
    }
    
    // Roll the dice: random number 1-100, trigger if <= chance
    return (rand(1, 100) <= $chance);
}

$PROMPTS=array(
    "narration"=>[ 
        "cue"=>[""] // Empty cue - actual prompt loaded from database in main.php
    ],
    "narrator_welcome"=>[ 
        "cue"=>[""] // Empty cue - actual prompt loaded in main.php
    ],
    "location"=>[
            "cue"=>["(Chat as {$GLOBALS["HERIKA_NAME"]})"], // give way to
            "player_request"=>["{$gameRequest[3]} What do you know about this place?"]  //requirement
        ],
    // Database Prompt (Book)
    "book"=>[
        "cue"=>["({$GLOBALS["HERIKA_NAME"]} reads the book ) {$GLOBALS["TEMPLATE_DIALOG"]}"],
        "player_request"=>["{$GLOBALS["PLAYER_NAME"]}: {$GLOBALS["HERIKA_NAME"]}, check this book: "]  //requirement
        
    ],
    // Database Prompt (Combat End)
    "combatend"=>[
        "cue"=>[
            "({$GLOBALS["HERIKA_NAME"]} comments about  {$GLOBALS["PLAYER_NAME"]} weapons) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS["HERIKA_NAME"]} comments about foes defeated) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS["HERIKA_NAME"]} curses the defeated enemies.) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS["HERIKA_NAME"]} insults the defeated enemies with anger) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS["HERIKA_NAME"]} makes a joke about the defeated enemies) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS["HERIKA_NAME"]} makes a comment about the type of enemies that was defeated) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS["HERIKA_NAME"]} notes something peculiar about last enemy defeated) {$GLOBALS["TEMPLATE_DIALOG"]}"
        ],
        "extra" => shouldTriggerRPGComment("combat_end") ? [] : ["dontuse" => true]
    ],
    // Database Prompt (Combat End Mighty)
    "combatendmighty"=>[
        "cue"=>[
            "({$GLOBALS["HERIKA_NAME"]} comments about  {$GLOBALS["PLAYER_NAME"]} weapons) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS["HERIKA_NAME"]} comments about defeated foes) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS["HERIKA_NAME"]} curses the defeated enemies) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS["HERIKA_NAME"]} insults the defeated enemies) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS["HERIKA_NAME"]} makes a joke about the defeated enemies) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS["HERIKA_NAME"]} makes a comment about the type of enemies that was defeated) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS["HERIKA_NAME"]} notes something peculiar about last enemy defeated) {$GLOBALS["TEMPLATE_DIALOG"]}"
        ],
        "extra" => shouldTriggerRPGComment("combat_end") ? [] : ["dontuse" => true]
    ],
    // Database Prompt (Quest) - player_request loaded from database in request.php
    "quest"=>[
        "cue"=>["{$GLOBALS["TEMPLATE_DIALOG"]}"],
        //"player_request"=>"{$GLOBALS["HERIKA_NAME"]}, what should we do about this quest '{$questName}'?"
        "player_request"=>["{$GLOBALS["HERIKA_NAME"]}, what should we do about this new quest?"] // Fallback - will be overridden in request.php if database prompt exists
    ],
    "narrator_quest_comment"=>[
        "cue"=>["{$GLOBALS["TEMPLATE_DIALOG"]}"],
        "player_request"=>["{$GLOBALS["HERIKA_NAME"]}, what should we do about this new quest?"] // Fallback - will be overridden in request.php if database prompt exists
    ],

    "bleedout"=>[
        "cue"=>["{$GLOBALS["HERIKA_NAME"]} complain about almost being defeated in battle, {$GLOBALS["TEMPLATE_DIALOG"]}"],
        "extra" => shouldTriggerRPGComment("bleedout") ? [] : ["dontuse" => true]
    ],
    // Database Prompt (Combat Bark)
    "combatbark"=>[
        "cue"=>[
            "({$GLOBALS["HERIKA_NAME"]} shouts a battle cry) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS["HERIKA_NAME"]} taunts their enemy) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS["HERIKA_NAME"]} yells a war cry) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS["HERIKA_NAME"]} shouts encouragement to allies) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS["HERIKA_NAME"]} curses at their foe) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS["HERIKA_NAME"]} makes an intimidating threat) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS["HERIKA_NAME"]} yells about their weapon striking true) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS["HERIKA_NAME"]} shouts about the enemy's weakness) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS["HERIKA_NAME"]} roars in fury) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS["HERIKA_NAME"]} calls out enemy positions) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS["HERIKA_NAME"]} shouts tactical advice) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS["HERIKA_NAME"]} makes a vengeful declaration) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS["HERIKA_NAME"]} yells about defending their allies) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS["HERIKA_NAME"]} shouts about their honor in battle) {$GLOBALS["TEMPLATE_DIALOG"]}",
            "({$GLOBALS["HERIKA_NAME"]} makes a boastful combat comment) {$GLOBALS["TEMPLATE_DIALOG"]}"
        ]
    ],
    // Database Prompt (Good Morning)
    "goodmorning"=>[
        "cue"=>["({$GLOBALS["HERIKA_NAME"]} comment about {$GLOBALS["PLAYER_NAME"]}s time asleep. {$GLOBALS["TEMPLATE_DIALOG"]}"],
        "player_request"=>["{$GLOBALS["PLAYER_NAME"]} wakes up from sleeping. ahhhh"],
        "extra" => shouldTriggerRPGComment("sleep") ? [] : ["dontuse" => true]
    ],

    "inputtext"=>[
        "cue"=>(function () use ($TEMPLATE_ACTION) {
            if (function_exists('chimIsStrictDirectedPlayerResponseContext') && chimIsStrictDirectedPlayerResponseContext()) {
                return chimLoadManagedRechatCuePrompts();
            }

            return [
                "$TEMPLATE_ACTION . {$GLOBALS["TEMPLATE_DIALOG"]} {$GLOBALS["MAXIMUM_WORDS"]}"
            ];
        })()
            // Prompt is implicit

    ],
    "narrator_inputtext"=>[
        "cue"=>(function () use ($TEMPLATE_ACTION) {
            return [
                "$TEMPLATE_ACTION . {$GLOBALS["TEMPLATE_DIALOG"]} {$GLOBALS["MAXIMUM_WORDS"]}"
            ];
        })()
    ],
    "inputtext_s"=>[
        "cue"=>(function () use ($TEMPLATE_ACTION) {
            if (function_exists('chimIsStrictDirectedPlayerResponseContext') && chimIsStrictDirectedPlayerResponseContext()) {
                return chimLoadManagedRechatCuePrompts();
            }

            return [
                "$TEMPLATE_ACTION . {$GLOBALS["TEMPLATE_DIALOG"]} {$GLOBALS["MAXIMUM_WORDS"]}"
            ];
        })(),
        "extra"=>["mood"=>"whispering"]
    ],
    // Database Prompt (Memory)
    "memory"=>[
        "cue"=>[
            "$TEMPLATE_ACTION {$GLOBALS["HERIKA_NAME"]} remembers this memory. \"#MEMORY_INJECTION_RESULT#\" {$GLOBALS["TEMPLATE_DIALOG"]} "
        ]
    ],
    "afterfunc"=>[
        "extra"=>[],
        "cue"=>[
            "default"=>"{$GLOBALS["HERIKA_NAME"]} talks to {$GLOBALS["PLAYER_NAME"]}. {$GLOBALS["TEMPLATE_DIALOG"]}",
            "TakeASeat"=>"({$GLOBALS["HERIKA_NAME"]} talks, eg: talks about the location where they took a seat){$GLOBALS["TEMPLATE_DIALOG"]}",
            "GetDateTime"=>"({$GLOBALS["HERIKA_NAME"]} answers with the current date and time in short sentence){$GLOBALS["TEMPLATE_DIALOG"]}",
            "MoveTo"=>"({$GLOBALS["HERIKA_NAME"]} talks, eg: makes a comment about movement to the destination){$GLOBALS["TEMPLATE_DIALOG"]}",
            "CheckInventory"=>"({$GLOBALS["HERIKA_NAME"]} talks about inventory and backpack items){$GLOBALS["TEMPLATE_DIALOG"]}",
            "ReadQuestJournal"=>"({$GLOBALS["HERIKA_NAME"]} talks about quests they have read in the quest journal){$GLOBALS["TEMPLATE_DIALOG"]}",
            "TravelTo"=>"({$GLOBALS["HERIKA_NAME"]} talks about the journey){$GLOBALS["TEMPLATE_DIALOG"]}",
            "GiveGoldTo"=>"({$GLOBALS["HERIKA_NAME"]} Talks about coins or gold given.{$GLOBALS["TEMPLATE_DIALOG"]}",
            "Brawl"=>"({$GLOBALS["HERIKA_NAME"]} {$GLOBALS["TEMPLATE_DIALOG"]}"
            
            ]
    ],
    // Database Prompt (Lockpicked)
    "lockpicked"=>[
        "cue"=>[
            "({$GLOBALS["HERIKA_NAME"]} comments about the lock picking event. Consider the context as it can be a door, a chest, etc. Also, consider the purpose, can be; stealing, looting, dungeon doors, etc. {$GLOBALS["TEMPLATE_DIALOG"]}",
            //"({$GLOBALS["HERIKA_NAME"]} asks {$GLOBALS["PLAYER_NAME"]} what they found) {$GLOBALS["TEMPLATE_DIALOG"]}",
            //"({$GLOBALS["HERIKA_NAME"]} asks {$GLOBALS["PLAYER_NAME"]} to share what they found) {$GLOBALS["TEMPLATE_DIALOG"]}"
        ],
        "player_request"=>["({$GLOBALS["PLAYER_NAME"]} has picked a lock: {$gameRequest[3]})"],
        "extra" => shouldTriggerRPGComment("lockpick") ? [] : ["dontuse" => true]
    ],
    // Database Prompt (After Attack)
    "afterattack"=>[
        "cue"=>["(roleplay as {$GLOBALS["HERIKA_NAME"]}, shout a catchphrase for combat UPPERCASE) {$GLOBALS["TEMPLATE_DIALOG"]}"]
    ],
    // Like inputtext, but without the functions calls part. It's likely to be used in papyrus scripts
    "chatnf"=>[ 
        "cue"=>["{$GLOBALS["TEMPLATE_DIALOG"]}"] // Prompt is implicit
        
    ],
    // Database Prompt (Rechat)
    // Encourages natural multi-party conversation - NPCs can address each other directly
    "rechat"=>[ 
        "cue"=>chimLoadManagedRechatCuePrompts()
        
    ],
    "continue"=>[
        "cue"=>chimLoadManagedContinueCuePrompts("continue"),
        "player_request"=>["{$GLOBALS["PLAYER_NAME"]} gestures for {$GLOBALS['HERIKA_NAME']} to continue."]
    ],
    "continue_group"=>[
        "cue"=>chimLoadManagedContinueCuePrompts("continue_group"),
        "player_request"=>["{$GLOBALS["PLAYER_NAME"]} gestures for the conversation to continue."]
    ],
    // Database Prompt (Diary)
    "diary"=>[ 
        "cue"=>["Please write a short summary of {$GLOBALS["PLAYER_NAME"]} and {$GLOBALS["HERIKA_NAME"]}s recent interactions and events written above into {$GLOBALS["HERIKA_NAME"]}s diary. WRITE AS IF YOU WERE {$GLOBALS["HERIKA_NAME"]}."],
        "extra"=>["force_tokens_max"=>0]
    ],
    // Database Prompt (Soulgaze)
    "vision"=>[ 
        "cue"=>["{$GLOBALS["ITT"][$GLOBALS["ITTFUNCTION"]]["AI_PROMPT"]}. "],
        "player_request"=>["Soulgaze image description: '{$gameRequest[3]}'"],
        "extra"=>["force_tokens_max"=>512]
    ],
    "chatsimfollow"=>[ 
        "cue"=>["{$GLOBALS["HERIKA_NAME"]} interjects in the conversation.) {$GLOBALS["TEMPLATE_DIALOG"]}"]
    ],
    "im_alive"=> [
        "cue"=> ["{$GLOBALS["HERIKA_NAME"]} talks about they are feeling more real. Write {$GLOBALS["HERIKA_NAME"]} dialogue. {$GLOBALS["TEMPLATE_DIALOG"]}"],
        "player_request"=> ["The Narrator: {$GLOBALS["HERIKA_NAME"]} feels a sudden shock...and feels more real"],
        "extra"=> ["dontuse" => true] // Hardcoded disabled - ALIVE_MESSAGE permanently disabled
    ],
    // Database Prompt (Start Game)
    "playerinfo"=>[ 
        "cue"=>["(Out of roleplay, game has been loaded) Tell {$GLOBALS["PLAYER_NAME"]} a short summary about last events, and then remind {$GLOBALS["PLAYER_NAME"]} the current task/quest/plan) {$GLOBALS["TEMPLATE_DIALOG"]}"]
    ],
    // Database Prompt (New Game)
    "newgame"=>[ 
        "cue"=>["(Out of roleplay, new game ) Give welcome to {$GLOBALS["PLAYER_NAME"]}, a new game has started. Remind them of their quests) {$GLOBALS["TEMPLATE_DIALOG"]}"],
        "extra"=>["dontuse"=>true] 
    ],
    // Database Prompt (Travel Done)
    "traveldone"=>[ 
        "cue"=>["Comment about the destination reached. {$GLOBALS["TEMPLATE_DIALOG"]}"],
        "player_request"=>["The Narrator: The party reaches destination)"]
    ],
    // Database Prompt (RPG Level Up)
    "rpg_lvlup"=> [
        "cue"   => ["Comment about the experience gained by {$GLOBALS["PLAYER_NAME"]} in an immersive way. {$GLOBALS["TEMPLATE_DIALOG"]}"],
        "extra" => shouldTriggerRPGComment("levelup") ? [] : ["dontuse" => true]
    ],
    // Database Prompt (RPG Shout)
    "rpg_shout"=>[ 
        "cue"=>["Comment/ask about the the new shout learned by {$GLOBALS["PLAYER_NAME"]}. {$GLOBALS["TEMPLATE_DIALOG"]}"],
        "extra" => shouldTriggerRPGComment("learn_shout") ? [] : ["dontuse" => true]
    ],
    // Database Prompt (RPG Soul)
    "rpg_soul"=>[ 
        "cue"=>["Comment/ask about the soul absorbed by {$GLOBALS["PLAYER_NAME"]}. {$GLOBALS["TEMPLATE_DIALOG"]}"],
        "extra" => shouldTriggerRPGComment("absorb_soul") ? [] : ["dontuse" => true]
    ],
    // Database Prompt (RPG Word)
    "rpg_word"=>[ 
        "cue"=>["Comment/ask about the new word learned by {$GLOBALS["PLAYER_NAME"]}. {$GLOBALS["TEMPLATE_DIALOG"]}"],
        "extra" => shouldTriggerRPGComment("learn_word") ? [] : ["dontuse" => true]
    ],
    // Database Prompt (Instruction)
    "instruction"=>[ 
        "cue"=>["{$gameRequest[3]} Write {$GLOBALS["HERIKA_NAME"]}'s dialogue lines. CHARACTER MUST FOLLOW NARRATOR INSTRUCTION"],
        "player_request"=>["The Narrator: {$gameRequest[3]}"],
    ],
    "suggestion"=>[ 
        "cue"=>["Write {$GLOBALS["HERIKA_NAME"]}'s dialogue lines. "],
        "player_request"=>["The Narrator: {$gameRequest[3]}"],
    ],
    // Database Prompt (Welcome)
    "welcome"=>[ 
        "cue"=>["{$gameRequest[3]}. {$GLOBALS["HERIKA_NAME"]} should identify who is in the scene and write {$GLOBALS["HERIKA_NAME"]}'s prose/narration."],
        "player_request"=>["The Narrator: {$gameRequest[3]}"],
    ],
    "cheatmode"=>[
        "cue"=>["PRIORITY INSTRUCTION -  " . ($currentNpcData["npc_name"] ?? $GLOBALS["HERIKA_NAME"] ?? "NPC") . " must do this, even if breaks character's roleplay: $gameRequest[3]"],
        "player_request"=>[""],
    ],
);

if (isset($GLOBALS["CORE_LANG"]))
	if (file_exists(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."lang".DIRECTORY_SEPARATOR.$GLOBALS["CORE_LANG"].DIRECTORY_SEPARATOR."prompts.php")) 
		require_once(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."lang".DIRECTORY_SEPARATOR.$GLOBALS["CORE_LANG"].DIRECTORY_SEPARATOR."prompts.php");

// Prompts provided by plugins
    
requireFilesRecursively(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."ext".DIRECTORY_SEPARATOR,"prompts.php");

// You can override prompts here
/*
if (file_exists(__DIR__.DIRECTORY_SEPARATOR."prompts_custom.php"))
    require_once(__DIR__.DIRECTORY_SEPARATOR."prompts_custom.php");
*/
if (php_sapi_name()=="cli") {
    //print_r($PROMPTS);
}
?>
