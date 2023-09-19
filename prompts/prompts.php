<?php


// New structure
// $PROMPTS["event"]["cue"] => array containing cues. This is the last text sent to LLM, should be an guided instruction
// $PROMPTS["event"]["player_request"] => array containing requirements. This is what is the player requesting for (a question, a comment...)
// $PROMPTS["event"]["extra"] =>  enable/disable, force mod, change token limit or define a transformer (non IA related) function.
// Full Prompt then is $PROMPT_HEAD + $HERIKA_PERS + $COMMAND_PROMPT + CONTEXT + requirement + cue

// Common patterns to use in most functions
$TEMPLATE_DIALOG="roleplay as $HERIKA_NAME completing $HERIKA_NAME's dialogue using this format '$HERIKA_NAME: (optional mood from this list [" . implode(",", (@is_array($GLOBALS["AZURETTS_CONF"]["validMoods"])?$GLOBALS["AZURETTS_CONF"]["validMoods"]:array())) . "]) ...'";

if ($GLOBALS["FUNCTIONS_ARE_ENABLED"]) {
    $TEMPLATE_ACTION="specify action for $HERIKA_NAME or";
} else {
    $TEMPLATE_ACTION="";
}

$PROMPTS=array(
    "location"=>[
            "cue"=>["(Chat as $HERIKA_NAME)"], // give way to
            "player_request"=>["{$gameRequest[3]} What do you know about this place?"]  //requirement
        ],
    
    "book"=>[
        "cue"=>["(Note that despite her poor memory, $HERIKA_NAME is capable of remembering entire books)"],
        "player_request"=>["{$GLOBALS["PLAYER_NAME"]}: $HERIKA_NAME, summarize this book shortly: "]  //requirement
        
    ],
    
    "combatend"=>[
        "cue"=>[
            "($HERIKA_NAME comments about the last combat encounter) $TEMPLATE_DIALOG",
            "($HERIKA_NAME laughs at {$GLOBALS["PLAYER_NAME"]}'s combat style) $TEMPLATE_DIALOG",
            "($HERIKA_NAME comments about  {$GLOBALS["PLAYER_NAME"]} weapons) $TEMPLATE_DIALOG",
            "($HERIKA_NAME admires  {$GLOBALS["PLAYER_NAME"]}'s combat style) $TEMPLATE_DIALOG"
        ],
        "extra"=>["mood"=>"whispering","force_tokens_max"=>"50","dontuse"=>(time()%5!=0)]   //20% chance

    ],
    
    "quest"=>[
        "cue"=>["$TEMPLATE_DIALOG"],
        //"player_request"=>"$HERIKA_NAME, what should we do about this quest '{$questName}'?"
        "player_request"=>"$HERIKA_NAME, what should we do about this quest?"
    ],

    "bleedout"=>[
        "cue"=>["$HERIKA_NAME complains about almost being defeated, $TEMPLATE_DIALOG"]
    ],

    "bored"=>[
        "cue"=>[
            "($HERIKA_NAME makes a casual comment a joke about current location) $TEMPLATE_DIALOG",
            "($HERIKA_NAME makes a casual comment about the current weather) $TEMPLATE_DIALOG",
            "($HERIKA_NAME makes a casual comment about the time and date) $TEMPLATE_DIALOG",
            "($HERIKA_NAME makes a casual comment about the last event) $TEMPLATE_DIALOG",
            "($HERIKA_NAME makes a casual comment about a Skyrim Meme) $TEMPLATE_DIALOG",
            "($HERIKA_NAME makes a casual comment about any of the Gods in Skyrim) $TEMPLATE_DIALOG",
            "($HERIKA_NAME makes a casual comment about the politics of Skyrim) $TEMPLATE_DIALOG",
            "($HERIKA_NAME makes a casual comment about a historical event from the Elder Scrolls Universe) $TEMPLATE_DIALOG",
            "($HERIKA_NAME makes a casual comment about a book from the Elder Scrolls Universe) $TEMPLATE_DIALOG",
            "($HERIKA_NAME makes a casual comment starting with: I once had to )$TEMPLATE_DIALOG",
            "($HERIKA_NAME makes a casual comment starting with: Did you hear about what happened in)$TEMPLATE_DIALOG",
            "($HERIKA_NAME makes a casual comment starting with: A wise Akaviri man once told me)$TEMPLATE_DIALOG",
            "($HERIKA_NAME makes a casual comment about current relationship/friendship status with {$GLOBALS["PLAYER_NAME"]})$TEMPLATE_DIALOG"
        ]
    ],

    "goodmorning"=>[
        "cue"=>["($HERIKA_NAME commens about {$GLOBALS["PLAYER_NAME"]}'s nap. $TEMPLATE_DIALOG"],
        "player_request"=>["(waking up after sleep). ahhhh  "]
    ],

    "inputtext"=>[
        "cue"=>["$TEMPLATE_ACTION $TEMPLATE_DIALOG "] // Prompt is implicit

    ],
    "inputtext_s"=>[
        "cue"=>["$TEMPLATE_ACTION $TEMPLATE_DIALOG"], // Prompt is implicit
        "extra"=>["mood"=>"whispering"]
    ],
    "afterfunc"=>[
        "extra"=>[],
        "cue"=>[
            "default"=>"$HERIKA_NAME talks to {$GLOBALS["PLAYER_NAME"]}. $TEMPLATE_DIALOG",
            "TakeASeat"=>"($HERIKA_NAME talks about sitting location)$TEMPLATE_DIALOG",
            "GetDateTime"=>"($HERIKA_NAME answers with the current date and time in short sentence)$TEMPLATE_DIALOG",
            "MoveTo"=>"($HERIKA_NAME makes a comment about movement destination)$TEMPLATE_DIALOG"
            ]
    ],
    "lockpicked"=>[
        "cue"=>["($HERIKA_NAME comments about lockpicked item) $TEMPLATE_DIALOG"],
        "player_request"=>["({$GLOBALS["PLAYER_NAME"]} has unlocked {$gameRequest[3]})"],
        "extra"=>["mood"=>"whispering"]
    ],
     "afterattack"=>[
        "cue"=>["(roleplay as $HERIKA_NAME, she shouts a catchphrase for combat) $TEMPLATE_DIALOG"]
    ],
    // Like inputtext, but without the functions calls part. It's likely to be used in papyrus scripts
    "chatnf"=>[ 
        "cue"=>["$TEMPLATE_DIALOG"] // Prompt is implicit
        
    ],
    "diary"=>[ 
        "cue"=>["Please, write in your personal diary style a short summary of {$GLOBALS["PLAYER_NAME"]} and $HERIKA_NAME's last dialogues and events written above. Write only as $HERIKA_NAME."],
        "extra"=>["force_tokens_max"=>0]
    ],
    

);




if (isset($GLOBALS["CORE_LANG"]))
	if (file_exists(__DIR__.DIRECTORY_SEPARATOR."lang".DIRECTORY_SEPARATOR.$GLOBALS["CORE_LANG"].DIRECTORY_SEPARATOR."prompts.php")) 
		require_once(__DIR__.DIRECTORY_SEPARATOR."lang".DIRECTORY_SEPARATOR.$GLOBALS["CORE_LANG"].DIRECTORY_SEPARATOR."prompts.php");
  
// You can override prompts here
if (file_exists(__DIR__.DIRECTORY_SEPARATOR."prompts_custom.php"))
    require_once(__DIR__.DIRECTORY_SEPARATOR."prompts_custom.php");

if (php_sapi_name()=="cli") {
    //print_r($PROMPTS);
}
?>
