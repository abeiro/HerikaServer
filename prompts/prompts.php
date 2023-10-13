<?php


// New structure
// $PROMPTS["event"]["cue"] => array containing cues. This is the last text sent to LLM, should be an guided instruction
// $PROMPTS["event"]["player_request"] => array containing requirements. This is what is the player requesting for (a question, a comment...)
// $PROMPTS["event"]["extra"] =>  enable/disable, force mod, change token limit or define a transformer (non IA related) function.
// Full Prompt then is $PROMPT_HEAD + $HERIKA_PERS + $COMMAND_PROMPT + CONTEXT + requirement + cue

// Common patterns to use in most functions
$TEMPLATE_DIALOG="write {$GLOBALS["HERIKA_NAME"]}'s dialogue using this format '{$GLOBALS["HERIKA_NAME"]}: ";

if (@is_array($GLOBALS["TTS"]["AZURE"]["validMoods"]) &&  sizeof($GLOBALS["TTS"]["AZURE"]["validMoods"])>0) 
    if ($GLOBALS["TTSFUNCTION"]=="azure")
        $TEMPLATE_DIALOG.="(optional way of speaking from this list [" . implode(",", $GLOBALS["TTS"]["AZURE"]["validMoods"]) . "])";

$TEMPLATE_DIALOG.=" ', (stay in the roleplay)";

    
if ($GLOBALS["FUNCTIONS_ARE_ENABLED"]) {
    $TEMPLATE_ACTION="call a function to control {$GLOBALS["HERIKA_NAME"]} or";
    $TEMPLATE_ACTION="";    // WIP
} else {
    $TEMPLATE_ACTION="";
}

$PROMPTS=array(
    "location"=>[
            "cue"=>["(Chat as {$GLOBALS["HERIKA_NAME"]})"], // give way to
            "player_request"=>["{$gameRequest[3]} What do you know about this place?"]  //requirement
        ],
    
    "book"=>[
        "cue"=>["(Note that despite her poor memory, {$GLOBALS["HERIKA_NAME"]} is capable of remembering entire books)"],
        "player_request"=>["{$GLOBALS["PLAYER_NAME"]}: {$GLOBALS["HERIKA_NAME"]}, summarize this book shortly: "]  //requirement
        
    ],
    
    "combatend"=>[
        "cue"=>[
            "({$GLOBALS["HERIKA_NAME"]} comments about the last combat encounter) $TEMPLATE_DIALOG",
            "({$GLOBALS["HERIKA_NAME"]} laughs at {$GLOBALS["PLAYER_NAME"]}'s combat style) $TEMPLATE_DIALOG",
            "({$GLOBALS["HERIKA_NAME"]} comments about  {$GLOBALS["PLAYER_NAME"]} weapons) $TEMPLATE_DIALOG",
            "({$GLOBALS["HERIKA_NAME"]} admires  {$GLOBALS["PLAYER_NAME"]}'s combat style) $TEMPLATE_DIALOG"
        ],
        "extra"=>["force_tokens_max"=>"50","dontuse"=>(time()%5!=0)]   //20% chance
    ],
    
    "quest"=>[
        "cue"=>["$TEMPLATE_DIALOG"],
        //"player_request"=>"{$GLOBALS["HERIKA_NAME"]}, what should we do about this quest '{$questName}'?"
        "player_request"=>"{$GLOBALS["HERIKA_NAME"]}, what should we do about this quest?"
    ],

    "bleedout"=>[
        "cue"=>["{$GLOBALS["HERIKA_NAME"]} complains about almost being defeated, $TEMPLATE_DIALOG"]
    ],

    "bored"=>[
        "cue"=>[
            "({$GLOBALS["HERIKA_NAME"]} makes a joke about current location) $TEMPLATE_DIALOG",
            "({$GLOBALS["HERIKA_NAME"]} makes a casual comment about the current weather) $TEMPLATE_DIALOG",
            "({$GLOBALS["HERIKA_NAME"]} makes a casual comment about the time and date) $TEMPLATE_DIALOG",
            "({$GLOBALS["HERIKA_NAME"]} makes a casual comment about the last event) $TEMPLATE_DIALOG",
            "({$GLOBALS["HERIKA_NAME"]} makes a casual comment about a Skyrim Meme) $TEMPLATE_DIALOG",
            "({$GLOBALS["HERIKA_NAME"]} makes a casual comment about any of the Gods in Skyrim) $TEMPLATE_DIALOG",
            "({$GLOBALS["HERIKA_NAME"]} makes a casual comment about the politics of Skyrim) $TEMPLATE_DIALOG",
            "({$GLOBALS["HERIKA_NAME"]} makes a casual comment about a historical event from the Elder Scrolls Universe) $TEMPLATE_DIALOG",
            "({$GLOBALS["HERIKA_NAME"]} makes a casual comment about a book from the Elder Scrolls Universe) $TEMPLATE_DIALOG",
            "({$GLOBALS["HERIKA_NAME"]} makes a casual comment starting with: I once had to ) $TEMPLATE_DIALOG",
            "({$GLOBALS["HERIKA_NAME"]} makes a casual comment starting with: Did you hear about what happened in) $TEMPLATE_DIALOG",
            "({$GLOBALS["HERIKA_NAME"]} makes a casual comment starting with: A wise Akaviri man once told me) $TEMPLATE_DIALOG",
            "({$GLOBALS["HERIKA_NAME"]} makes a casual comment about current relationship/friendship status with {$GLOBALS["PLAYER_NAME"]}) $TEMPLATE_DIALOG"
        ]
    ],

    "goodmorning"=>[
        "cue"=>["({$GLOBALS["HERIKA_NAME"]} commens about {$GLOBALS["PLAYER_NAME"]}'s nap. $TEMPLATE_DIALOG"],
        "player_request"=>["(waking up after sleep). ahhhh  "]
    ],

    "inputtext"=>[
        "cue"=>["$TEMPLATE_ACTION {$GLOBALS["HERIKA_NAME"]} answers to {$GLOBALS["PLAYER_NAME"]}. $TEMPLATE_DIALOG "] // Prompt is implicit

    ],
    "inputtext_s"=>[
        "cue"=>["$TEMPLATE_ACTION {$GLOBALS["HERIKA_NAME"]} answers to {$GLOBALS["PLAYER_NAME"]}. $TEMPLATE_DIALOG"], // Prompt is implicit
        "extra"=>["mood"=>"whispering"]
    ],
    "afterfunc"=>[
        "extra"=>[],
        "cue"=>[
            "default"=>"{$GLOBALS["HERIKA_NAME"]} talks to {$GLOBALS["PLAYER_NAME"]}. $TEMPLATE_DIALOG",
            "TakeASeat"=>"({$GLOBALS["HERIKA_NAME"]} talks about sitting location)$TEMPLATE_DIALOG",
            "GetDateTime"=>"({$GLOBALS["HERIKA_NAME"]} answers with the current date and time in short sentence)$TEMPLATE_DIALOG",
            "MoveTo"=>"({$GLOBALS["HERIKA_NAME"]} makes a comment about movement destination)$TEMPLATE_DIALOG"
            ]
    ],
    "lockpicked"=>[
        "cue"=>["({$GLOBALS["HERIKA_NAME"]} comments about lockpicked item) $TEMPLATE_DIALOG"],
        "player_request"=>["({$GLOBALS["PLAYER_NAME"]} has unlocked {$gameRequest[3]})"],
        "extra"=>["mood"=>"whispering"]
    ],
     "afterattack"=>[
        "cue"=>["(roleplay as {$GLOBALS["HERIKA_NAME"]}, she shouts a catchphrase for combat) $TEMPLATE_DIALOG"]
    ],
    // Like inputtext, but without the functions calls part. It's likely to be used in papyrus scripts
    "chatnf"=>[ 
        "cue"=>["$TEMPLATE_DIALOG"] // Prompt is implicit
        
    ],
    "diary"=>[ 
        "cue"=>["Please write a summary of {$GLOBALS["PLAYER_NAME"]} and {$GLOBALS["HERIKA_NAME"]}'s last dialogues and events written above into {$GLOBALS["HERIKA_NAME"]}'s diary . WRITE AS IF YOU WERE {$GLOBALS["HERIKA_NAME"]}."],
        "extra"=>["force_tokens_max"=>0]
    ],
    

);




if (isset($GLOBALS["CORE_LANG"]))
	if (file_exists(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."lang".DIRECTORY_SEPARATOR.$GLOBALS["CORE_LANG"].DIRECTORY_SEPARATOR."prompts.php")) 
		require_once(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."lang".DIRECTORY_SEPARATOR.$GLOBALS["CORE_LANG"].DIRECTORY_SEPARATOR."prompts.php");
  
// You can override prompts here
if (file_exists(__DIR__.DIRECTORY_SEPARATOR."prompts_custom.php"))
    require_once(__DIR__.DIRECTORY_SEPARATOR."prompts_custom.php");

if (php_sapi_name()=="cli") {
    //print_r($PROMPTS);
}
?>
