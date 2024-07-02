<?php

// Functions to be provided to OpenAI

$ENABLED_FUNCTIONS=[
    'Inspect',
    'LookAt',
    'InspectSurroundings',
    'MoveTo',
    'OpenInventory',
    'Attack',
    'Follow',
    'CheckInventory',
    'SheatheWeapon',
    'Relax',
    'LeadTheWayTo',
    'TakeASeat',
    'ReadQuestJournal',
    'SetSpeed',
    'GetDateTime',
    'SearchDiary',
    'SetCurrentTask',
    'StopWalk',
    'TravelTo',
    'SearchMemory'
];



$F_TRANSLATIONS["Inspect"]="LOOK at or Inspects NPC, Actor, or being OUTFIT and GEAR.JUST REPLY something like 'Let me see' and wait";
$F_TRANSLATIONS["LookAt"]="LOOK at or Inspects NPC, Actor, or being OUTFIT and GEAR";
$F_TRANSLATIONS["InspectSurroundings"]="Looks for beings nearby";
$F_TRANSLATIONS["MoveTo"]= "Walk to a visible building or visible actor, also used to guide {$GLOBALS["PLAYER_NAME"]} to a actor or building.";
$F_TRANSLATIONS["OpenInventory"]="Initiates trading or exchange items with {$GLOBALS["PLAYER_NAME"]}.";
$F_TRANSLATIONS["Attack"]="Attacks actor, npc or being.";
$F_TRANSLATIONS["Follow"]="Moves to and follow a NPC, an actor or being";
$F_TRANSLATIONS["CheckInventory"]="Search in {$GLOBALS["HERIKA_NAME"]}\'s inventory, backpack or pocket. List inventory";
$F_TRANSLATIONS["SheatheWeapon"]="Sheates current weapon";
$F_TRANSLATIONS["Relax"]="Makes {$GLOBALS["HERIKA_NAME"]} to stop current action and relax herself";
$F_TRANSLATIONS["LeadTheWayTo"]="Only use if {$GLOBALS["PLAYER_NAME"]} explicitly orders it. Guide {$GLOBALS["PLAYER_NAME"]} to a Town o City. ";
$F_TRANSLATIONS["TakeASeat"]="{$GLOBALS["HERIKA_NAME"]} seats in nearby chair or furniture ";
$F_TRANSLATIONS["ReadQuestJournal"]="Only use if {$GLOBALS["PLAYER_NAME"]} explicitly ask for a quest. Get info about current quests";
$F_TRANSLATIONS["SetSpeed"]="Set {$GLOBALS["HERIKA_NAME"]} speed when moving or travelling";
$F_TRANSLATIONS["GetDateTime"]="Get Current Date and Time";
$F_TRANSLATIONS["SearchDiary"]="Read {$GLOBALS["HERIKA_NAME"]}'s diary to make her remember something. Search in diary index";
$F_TRANSLATIONS["SetCurrentTask"]="Set the current plan of action or task or quest";
$F_TRANSLATIONS["ReadDiaryPage"]="Read {$GLOBALS["HERIKA_NAME"]}'s diary to access a specific topic";
$F_TRANSLATIONS["StopWalk"]="Stop all {$GLOBALS["HERIKA_NAME"]}'s actions inmediately";
$F_TRANSLATIONS["TravelTo"]="{$GLOBALS["HERIKA_NAME"]} travels to a city/location";
$F_TRANSLATIONS["SearchMemory"]="{$GLOBALS["HERIKA_NAME"]} tries to remember information. JUST REPLY something like 'Let me think' and wait";


$F_RETURNMESSAGES["Inspect"]="{$GLOBALS["HERIKA_NAME"]} inspects #TARGET# and see this: #RESULT#";
$F_RETURNMESSAGES["LookAt"]="LOOK at or Inspects NPC, Actor, or being OUTFIT and GEAR";
$F_RETURNMESSAGES["InspectSurroundings"]="Looks for beings nearby";
$F_RETURNMESSAGES["MoveTo"]= "Walk to a visible building or visible actor, also used to guide {$GLOBALS["PLAYER_NAME"]} to a actor or building.";
$F_RETURNMESSAGES["OpenInventory"]="Initiates trading or exchange items with {$GLOBALS["PLAYER_NAME"]}. Accept gift.";
$F_RETURNMESSAGES["Attack"]="Attacks actor, npc or being. but always avoid the deaths of innocent actors.";
$F_RETURNMESSAGES["Follow"]="Moves to and follow a NPC, an actor or being";
$F_RETURNMESSAGES["CheckInventory"]="{$GLOBALS["HERIKA_NAME"]}'s INVENTORY:#RESULT#";
$F_RETURNMESSAGES["SheatheWeapon"]="Sheates current weapon";
$F_RETURNMESSAGES["Relax"]="{$GLOBALS["HERIKA_NAME"]} takes a little rest";
$F_RETURNMESSAGES["LeadTheWayTo"]="Only use if {$GLOBALS["PLAYER_NAME"]} explicitly orders it. Guide {$GLOBALS["PLAYER_NAME"]} to a Town o City. ";
$F_RETURNMESSAGES["TakeASeat"]="{$GLOBALS["HERIKA_NAME"]} seats in nearby chair or furniture ";
$F_RETURNMESSAGES["ReadQuestJournal"]="";
$F_RETURNMESSAGES["SetSpeed"]="Set {$GLOBALS["HERIKA_NAME"]} speed when moving or travelling";
$F_RETURNMESSAGES["GetDateTime"]="Get Current Date and Time";
$F_RETURNMESSAGES["SearchDiary"]="Read {$GLOBALS["HERIKA_NAME"]}'s diary to make her remember something. Search in diary index";
$F_RETURNMESSAGES["SetCurrentTask"]="Set the current plan of action or task or quest";
$F_RETURNMESSAGES["ReadDiaryPage"]="Read {$GLOBALS["HERIKA_NAME"]}'s diary to access a specific topic";
$F_RETURNMESSAGES["StopWalk"]="Stop all {$GLOBALS["HERIKA_NAME"]}'s actions inmediately";
$F_RETURNMESSAGES["TravelTo"]="{$GLOBALS["HERIKA_NAME"]} begind travelling to #TARGET#";
$F_RETURNMESSAGES["SearchMemory"]="{$GLOBALS["HERIKA_NAME"]} tries to remember information. JUST REPLY something like 'Let me think' and wait";


// What is this?. We can translate functions or give them a custom name. 
// This array will handle translations. Plugin must receive the codename always.

$F_NAMES["Inspect"]="Inspect";
$F_NAMES["LookAt"]="LookAt";
$F_NAMES["InspectSurroundings"]="InspectSurroundings";
$F_NAMES["MoveTo"]= "MoveTo";
$F_NAMES["OpenInventory"]="ExchangeItems";
$F_NAMES["Attack"]="Attack";
$F_NAMES["Follow"]="Follow";
$F_NAMES["CheckInventory"]="ListInventory";
$F_NAMES["SheatheWeapon"]="SheatheWeapon";
//$F_NAMES["Relax"]="Relax";
//$F_NAMES["LeadTheWayTo"]="LeadTheWayTo";
$F_NAMES["TakeASeat"]="TakeASeat";
$F_NAMES["ReadQuestJournal"]="ReadQuestJournal";
$F_NAMES["SetSpeed"]="SetWalkSpeed";
$F_NAMES["GetDateTime"]="GetDateTime";
$F_NAMES["SearchDiary"]="SearchDiary";
$F_NAMES["SetCurrentTask"]="SetCurrentTask";
$F_NAMES["ReadDiaryPage"]="ReadDiaryPage";
$F_NAMES["StopWalk"]="StopWalk";
$F_NAMES["TravelTo"]="LeadTheWayTo";
$F_NAMES["SearchMemory"]="TryToRemember";

if (isset($GLOBALS["CORE_LANG"]))
	if (file_exists(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."lang".DIRECTORY_SEPARATOR.$GLOBALS["CORE_LANG"].DIRECTORY_SEPARATOR."functions.php")) 
		require_once(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."lang".DIRECTORY_SEPARATOR.$GLOBALS["CORE_LANG"].DIRECTORY_SEPARATOR."functions.php");
    
    
    
$FUNCTIONS = [
    [
        "name" => $F_NAMES["Inspect"],
        "description" => $F_TRANSLATIONS["Inspect"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "target" => [
                    "type" => "string",
                    "description" => "Target NPC, Actor, or being",
                    "enum" => $FUNCTION_PARM_INSPECT

                ]
            ],
            "required" => ["target"],
        ],
    ],
    [
        "name" => $F_NAMES["InspectSurroundings"],
        "description" => $F_TRANSLATIONS["InspectSurroundings"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "target" => [
                    "type" => "string",
                    "description" => "Keep it blank",
                ]
            ],
            "required" => []
        ],
    ],
    [
        "name" => $F_NAMES["LookAt"],
        "description" => $F_TRANSLATIONS["Inspect"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "target" => [
                    "type" => "string",
                    "description" => "Target NPC, Actor, or being",
                    "enum" => $FUNCTION_PARM_INSPECT

                ]
            ],
            "required" => ["target"],
        ],
    ],
    [
        "name" => $F_NAMES["MoveTo"],
        "description" => $F_TRANSLATIONS["MoveTo"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "target" => [
                    "type" => "string",
                    "description" => "Visible Target NPC, Actor, or being, or building.",
                    "enum" => $FUNCTION_PARM_MOVETO
                ]
            ],
            "required" => ["target"],
        ],
    ],
    [
        "name" => $F_NAMES["OpenInventory"],
        "description" => $F_TRANSLATIONS["OpenInventory"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "target" => [
                    "type" => "string",
                    "description" => "Keep it blank",
                ]
            ],
            "required" => []
        ],
    ],
    [
        "name" => $F_NAMES["Attack"],
        "description" => $F_TRANSLATIONS["Attack"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "target" => [
                    "type" => "string",
                    "description" => "Target NPC, Actor, or being",
                ]
            ],
            "required" => ["target"],
        ]
    ],
    [
        "name" => $F_NAMES["Follow"],
        "description" => $F_TRANSLATIONS["Follow"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "target" => [
                    "type" => "string",
                    "description" => "Target NPC, Actor, or being",
                ]
            ],
            "required" => ["target"],
        ]
    ],
    [
        "name" => $F_NAMES["CheckInventory"],
        "description" => $F_TRANSLATIONS["CheckInventory"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "target" => [
                    "type" => "string",
                    "description" => "item to look for, if empty all items will be returned",
                ]
            ],
            "required" => []
        ]
    ],
    [
        "name" => $F_NAMES["SheatheWeapon"],
        "description" => $F_TRANSLATIONS["SheatheWeapon"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "target" => [
                    "type" => "string",
                    "description" => "Keep it blank",
                ]
            ],
            "required" => []
        ]
    ],
    [
        "name" => $F_NAMES["Relax"],
        "description" => $F_TRANSLATIONS["Relax"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "target" => [
                    "type" => "string",
                    "description" => "Keep it blank",
                ]
            ],
            "required" => []
        ]
    ],
    [
        "name" => $F_NAMES["LeadTheWayTo"],
        "description" => $F_TRANSLATIONS["LeadTheWayTo"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "location" => [
                    "type" => "string",
                    "description" => "Town or City to travel to, only if {$GLOBALS["PLAYER_NAME"]} explicitly orders it"
                    
                ]
            ],
            "required" => ["location"]
        ]
    ],
    [
        "name" => $F_NAMES["TravelTo"],
        "description" => $F_TRANSLATIONS["TravelTo"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "location" => [
                    "type" => "string",
                    "description" => "Town or City to travel to, only if {$GLOBALS["PLAYER_NAME"]} explicitly orders it"
                    
                ]
            ],
            "required" => ["location"]
        ]
    ],
    [
        "name" => $F_NAMES["TakeASeat"],
        "description" => $F_TRANSLATIONS["TakeASeat"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "target" => [
                    "type" => "string",
                    "description" => "Keep it blank",
                ]
            ],
            "required" => [""]
        ]
    ],
    [
        "name" => $F_NAMES["ReadQuestJournal"],
        "description" => $F_TRANSLATIONS["ReadQuestJournal"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "id_quest" => [
                    "type" => "string",
                    "description" => "Specific quest to get info for, or blank to get all",
                ]
            ],
            "required" => [""]
        ]
    ],
    [
        "name" => $F_NAMES["SetSpeed"],
        "description" => $F_TRANSLATIONS["SetSpeed"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "speed" => [
                    "type" => "string",
                    "description" => "Speed",
                    "enum" => ["run", "fastwalk", "jog", "walk"]
                ]

            ],
            "required" => ["speed"]
        ]
    ],
    [
        "name" => $F_NAMES["GetDateTime"],
        "description" => $F_TRANSLATIONS["GetDateTime"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "datestring" => [
                    "type" => "string",
                    "description" => "Formatted date and time",
                ]

            ],
            "required" => []
        ]
    ],
    [
        "name" => $F_NAMES["SearchDiary"],
        "description" => $F_TRANSLATIONS["SearchDiary"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "keyword" => [
                    "type" => "string",
                    "description" => "keyword to search in full-text query syntax",
                ]
            ],
            "required" => [""]
        ]
    ],
    [
        "name" => $F_NAMES["SetCurrentTask"],
        "description" => $F_TRANSLATIONS["SetCurrentTask"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "description" => [
                    "type" => "string",
                    "description" => "Short description of current task talked by the party",
                ]
            ],
            "required" => ["description"]
        ]
    ], 
    [
        "name" => $F_NAMES["StopWalk"],
        "description" => $F_TRANSLATIONS["StopWalk"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "target" => [
                    "type" => "string",
                    "description" => "action",
                ]
            ],
            "required" =>[""]
        ]
    ],
     [
        "name" => $F_NAMES["SearchMemory"],
        "description" => $F_TRANSLATIONS["SearchMemory"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "target" => [
                    "type" => "string",
                    "description" => "target keywords to search in memory database",
                ]
            ],
            "required" =>[""]
        ]
    ]
];



// This function only is offered when SearchDiary
$FUNCTIONS_GHOSTED =  [
        "name" => $F_NAMES["ReadDiaryPage"],
        "description" => $F_TRANSLATIONS["ReadDiaryPage"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "page" => [
                    "type" => "string",
                    "description" => "topic to search in full-text query syntax",
                ]
            ],
            "required" => ["topic"]
        ]
    ]
    ;

function getFunctionCodeName($key) {
    
    $functionCode=array_search($key, $GLOBALS["F_NAMES"]);
    return $functionCode;
    
}

function getFunctionTrlName($key) {
    return $GLOBALS["F_NAMES"][$key];
    
}

function findFunctionByName($name) {
    foreach ($GLOBALS["FUNCTIONS"] as $function) {
        if ($function['name'] === $name) {
            return $function;
        }
    }
    return null; // Return null if function not found
}


function requireFunctionFilesRecursively($dir) {
    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }

        $path = $dir . '/' . $file;

        if (is_dir($path)) {
            requireFunctionFilesRecursively($path);
        } elseif (is_file($path) && $file === 'functions.php') {
            require_once $path;
        } 
    }
}

$folderPath = __DIR__.DIRECTORY_SEPARATOR."../ext/";
requireFunctionFilesRecursively($folderPath);


if (file_exists(__DIR__.DIRECTORY_SEPARATOR."lang".DIRECTORY_SEPARATOR.$GLOBALS["CORE_LANG"].DIRECTORY_SEPARATOR."prompts.php")) {
    require(__DIR__.DIRECTORY_SEPARATOR."lang".DIRECTORY_SEPARATOR.$GLOBALS["CORE_LANG"].DIRECTORY_SEPARATOR."prompts.php");
}

if (file_exists(__DIR__.DIRECTORY_SEPARATOR."../prompts/prompts_custom.php")) {
    require(__DIR__.DIRECTORY_SEPARATOR."../prompts/prompts_custom.php");
}


$GLOBALS["ENABLED_FUNCTIONS"]=[
    'Inspect',
    //'LookAt',
    //'InspectSurroundings',
    //'MoveTo',
    'OpenInventory',
    'Attack',
    'TravelTo',
    //'Follow',
    'CheckInventory',
    //'SheatheWeapon',
    //'Relax',
    //'LeadTheWayTo',
    'TakeASeat',
    'ReadQuestJournal',
    'SetSpeed',
    //'GetDateTime',
    //'SearchDiary',
    'SetCurrentTask',
    'SearchMemory',
    //'StopWalk'
];

// Delete non wanted functions    

foreach ($FUNCTIONS as $n=>$v)
    if (!in_array(getFunctionCodeName($v["name"]),$ENABLED_FUNCTIONS)) {
            unset($FUNCTIONS[$n]);
    }

    $FUNCTIONS=array_values($FUNCTIONS); //Get rid of array keys


?>
