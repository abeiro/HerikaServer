<?php

// Functions to be provided to OpenAI


$ENABLED_FUNCTIONS_LOCAL=[
    'Inspect',
    'LookAt',
    'InspectSurroundings',
    'MoveTo',
    'OpenInventory',
    'OpenInventory2',
    'Attack',
    'AttackHunt',
    'Follow',
    'CheckInventory',
    'SheatheWeapon',
    'Relax',
    'LeadTheWayTo',
    'TakeASeat',
    'ReadQuestJournal',
    'IncreaseWalkSpeed',
    'DecreaseWalkSpeed',
    'GetDateTime',
    'SearchDiary',
    'SetCurrentTask',
    'StopWalk',
    'TravelTo',
    'SearchMemory',
    'GiveItemToPlayer',
    'FollowPlayer',
    'ComeCloser',
    'Brawl',
    'ReturnBackHome',
    'GiveGoldTo',
    'GiveItemTo',
    'GoToSleep',
    'UseSoulGaze'
//    'WaitHere'
];

$GLOBALS["ENABLED_FUNCTIONS"]=$ENABLED_FUNCTIONS_LOCAL;

// We must use internal keys here.

$F_TRANSLATIONS_LOCAL["Inspect"]="Inspects ONLY an ACTOR/NPC. Wait for result to give a dialogue message.";
$F_TRANSLATIONS_LOCAL["LookAt"]="Inspects ONLY an ACTOR/NPC. Wait for result to give a dialogue message.";
$F_TRANSLATIONS_LOCAL["InspectSurroundings"]="Looks for actors around.Wait for result to give a dialogue message";
$F_TRANSLATIONS_LOCAL["MoveTo"]= "Move to a visible building or visible actor, also used to guide {$GLOBALS["PLAYER_NAME"]} to a actor or building.";
$F_TRANSLATIONS_LOCAL["OpenInventory"]="Initiates trading or exchange ITEMS with {$GLOBALS["PLAYER_NAME"]}.";
$F_TRANSLATIONS_LOCAL["OpenInventory2"]="Initiates trading, {$GLOBALS["PLAYER_NAME"]} must give ITEMS to {$GLOBALS["HERIKA_NAME"]}";
$F_TRANSLATIONS_LOCAL["Attack"]="Attack with intention to kill an Actor, NPC or entity.";
$F_TRANSLATIONS_LOCAL["AttackHunt"]="Hunt with intention to kill an Actor, NPC or entity.";
$F_TRANSLATIONS_LOCAL["Follow"]="Move to and follow the specified target actor";
$F_TRANSLATIONS_LOCAL["CheckInventory"]="Search in {$GLOBALS["HERIKA_NAME"]}'s inventory, backpack or pocket. List their inventory contents";
$F_TRANSLATIONS_LOCAL["SheatheWeapon"]="Sheathes/put away current weapon";
$F_TRANSLATIONS_LOCAL["Relax"]="Stop whatever you are doing and relax at the current location";
$F_TRANSLATIONS_LOCAL["TravelTo"]="Use it to move to major locations and landmarks and POIs.";
$F_TRANSLATIONS_LOCAL["TakeASeat"]="{$GLOBALS["HERIKA_NAME"]} take a seat at seating location nearby.";
$F_TRANSLATIONS_LOCAL["ReadQuestJournal"]="Only use if {$GLOBALS["PLAYER_NAME"]} explicitly ask for a quest. Get info about current quests";
$F_TRANSLATIONS_LOCAL["IncreaseWalkSpeed"]="Increase {$GLOBALS["HERIKA_NAME"]} speed when moving or travelling";
$F_TRANSLATIONS_LOCAL["DecreaseWalkSpeed"]="Decrease {$GLOBALS["HERIKA_NAME"]} speed when moving or travelling";
$F_TRANSLATIONS_LOCAL["GetDateTime"]="Get Current Date and Time";
$F_TRANSLATIONS_LOCAL["SearchDiary"]="Read {$GLOBALS["HERIKA_NAME"]}'s diary to make her remember something. Search in diary index";
$F_TRANSLATIONS_LOCAL["SetCurrentTask"]="Set the current plan of action or task or quest";
$F_TRANSLATIONS_LOCAL["ReadDiaryPage"]="Read {$GLOBALS["HERIKA_NAME"]}'s diary to access a specific topic";
$F_TRANSLATIONS_LOCAL["StopWalk"]="Stop all {$GLOBALS["HERIKA_NAME"]}'s actions inmediately";
$F_TRANSLATIONS_LOCAL["TravelTo"]="Only use if {$GLOBALS["PLAYER_NAME"]} explicitly orders it. Guide {$GLOBALS["PLAYER_NAME"]} to a Town o City. ";
$F_TRANSLATIONS_LOCAL["SearchMemory"]="{$GLOBALS["HERIKA_NAME"]} tries to remember information. REPLY with hashtags";
$F_TRANSLATIONS_LOCAL["WaitHere"]="{$GLOBALS["HERIKA_NAME"]} waits and loiters at the current location";
$F_TRANSLATIONS_LOCAL["GiveItemToPlayer"]="{$GLOBALS["HERIKA_NAME"]} gives item (property target) to {$GLOBALS["PLAYER_NAME"]} (property listener)";
$F_TRANSLATIONS_LOCAL["TakeGoldFromPlayer"]="{$GLOBALS["HERIKA_NAME"]} takes amount (property target) of gold from {$GLOBALS["PLAYER_NAME"]} (property listener) once {$GLOBALS["PLAYER_NAME"]} is agree";
$F_TRANSLATIONS_LOCAL["FollowPlayer"]="{$GLOBALS["HERIKA_NAME"]} follows  {$GLOBALS["PLAYER_NAME"]}";
$F_TRANSLATIONS_LOCAL["ComeCloser"]="{$GLOBALS["HERIKA_NAME"]} aproaches to {$GLOBALS["PLAYER_NAME"]}";
$F_TRANSLATIONS_LOCAL["Brawl"]="{$GLOBALS["HERIKA_NAME"]} engages non lethtal combat with another actor, using weapons";
$F_TRANSLATIONS_LOCAL["ReturnBackHome"]="{$GLOBALS["HERIKA_NAME"]} travels to home/origin place.Returns home.";
$F_TRANSLATIONS_LOCAL["GiveGoldTo"]="{$GLOBALS["HERIKA_NAME"]} gives some gold/coins/septims to a single actor (target property is the actor). Amount will be infered from dialogue, so no need to specify";
$F_TRANSLATIONS_LOCAL["GiveItemTo"]="{$GLOBALS["HERIKA_NAME"]} gives item to a single actor (target property is the actor). Amount and item will be infered from dialogue, so no need to specify";
$F_TRANSLATIONS_LOCAL["GoToSleep"]="{$GLOBALS["HERIKA_NAME"]} takes a nap";
$F_TRANSLATIONS_LOCAL["UseSoulGaze"]="Use the spell SoulGaze, a powerful incantation that allows {$GLOBALS["HERIKA_NAME"]} to perceive surroundings in vivid detail through {$GLOBALS["PLAYER_NAME"]}'s eyes. The spell, however, causes some disturbance to the caster.";

$GLOBALS["F_TRANSLATIONS"]=$F_TRANSLATIONS_LOCAL;



$F_RETURNMESSAGES_LOCAL["Inspect"]="{$GLOBALS["HERIKA_NAME"]} inspects #TARGET# and see this: #RESULT#";
$F_RETURNMESSAGES_LOCAL["LookAt"]="LOOK at or Inspects NPC, Actor, or being OUTFIT and GEAR";
$F_RETURNMESSAGES_LOCAL["InspectSurroundings"]="{$GLOBALS["HERIKA_NAME"]} takes a look around and see this: #RESULT#";
$F_RETURNMESSAGES_LOCAL["MoveTo"]= "Walk to a visible building or visible actor, also used to guide {$GLOBALS["PLAYER_NAME"]} to a actor or building.";
$F_RETURNMESSAGES_LOCAL["OpenInventory"]="Initiates trading or exchange items with {$GLOBALS["PLAYER_NAME"]}.";
$F_RETURNMESSAGES_LOCAL["OpenInventory2"]="{$GLOBALS["PLAYER_NAME"]} give items to {$GLOBALS["HERIKA_NAME"]}. Accept gift.";
$F_RETURNMESSAGES_LOCAL["Attack"]="{$GLOBALS["HERIKA_NAME"]} Attacks #TARGET# ";
$F_RETURNMESSAGES_LOCAL["AttackHunt"]="{$GLOBALS["HERIKA_NAME"]} Attacks #TARGET# ";
$F_RETURNMESSAGES_LOCAL["Follow"]="{$GLOBALS["HERIKA_NAME"]} follows #TARGET# ";
$F_RETURNMESSAGES_LOCAL["CheckInventory"]="{$GLOBALS["HERIKA_NAME"]}'s INVENTORY:#RESULT#";
$F_RETURNMESSAGES_LOCAL["SheatheWeapon"]="Sheathes/put away current weapon";
$F_RETURNMESSAGES_LOCAL["Relax"]="{$GLOBALS["HERIKA_NAME"]} is relaxed. Time to enjoy life.";
$F_RETURNMESSAGES_LOCAL["LeadTheWayTo"]="Only use if {$GLOBALS["PLAYER_NAME"]} explicitly orders it. Guide {$GLOBALS["PLAYER_NAME"]} to a Town o City. ";
$F_RETURNMESSAGES_LOCAL["TakeASeat"]="{$GLOBALS["HERIKA_NAME"]} seats in nearby chair or furniture ";
$F_RETURNMESSAGES_LOCAL["ReadQuestJournal"]="";
$F_RETURNMESSAGES_LOCAL["IncreaseWalkSpeed"]="Increases {$GLOBALS["HERIKA_NAME"]} speed/pace when moving or travelling";
$F_RETURNMESSAGES_LOCAL["DecreaseWalkSpeed"]="Decreases {$GLOBALS["HERIKA_NAME"]} speed/pace when moving or travelling";
$F_RETURNMESSAGES_LOCAL["GetDateTime"]="Get Current Date and Time";
$F_RETURNMESSAGES_LOCAL["SearchDiary"]="Read {$GLOBALS["HERIKA_NAME"]}'s diary to make her remember something. Search in diary index";
$F_RETURNMESSAGES_LOCAL["SetCurrentTask"]="Set the current plan of action or task or quest";
$F_RETURNMESSAGES_LOCAL["ReadDiaryPage"]="Read {$GLOBALS["HERIKA_NAME"]}'s diary to access a specific topic";
$F_RETURNMESSAGES_LOCAL["StopWalk"]="Stop all {$GLOBALS["HERIKA_NAME"]}'s actions inmediately";
$F_RETURNMESSAGES_LOCAL["TravelTo"]="{$GLOBALS["HERIKA_NAME"]} begins travelling to #TARGET#";
$F_RETURNMESSAGES_LOCAL["SearchMemory"]="{$GLOBALS["HERIKA_NAME"]} tries to remember information. JUST REPLY something like 'Let me think' and wait";
$F_RETURNMESSAGES_LOCAL["WaitHere"]="{$GLOBALS["HERIKA_NAME"]} waits and stands at the place";
$F_RETURNMESSAGES_LOCAL["GiveItemToPlayer"]="{$GLOBALS["HERIKA_NAME"]} gave #TARGET# to {$GLOBALS["PLAYER_NAME"]}.If this a transaction, maybe TakeGoldFromPlayer is needed.";
$F_RETURNMESSAGES_LOCAL["TakeGoldFromPlayer"]="{$GLOBALS["PLAYER_NAME"]} gave #TARGET# coins to {$GLOBALS["HERIKA_NAME"]}. If this a transaction, maybe GiveItemToPlayer is needed.";
$F_RETURNMESSAGES_LOCAL["FollowPlayer"]="{$GLOBALS["HERIKA_NAME"]} follows {$GLOBALS["PLAYER_NAME"]}";
$F_RETURNMESSAGES_LOCAL["Brawl"]="{$GLOBALS["HERIKA_NAME"]} Attacks #TARGET# ";
$F_RETURNMESSAGES_LOCAL["ReturnBackHome"]="{$GLOBALS["HERIKA_NAME"]} goes back home";
$F_RETURNMESSAGES_LOCAL["GiveGoldTo"]="{$GLOBALS["HERIKA_NAME"]} gives gold to #TARGET#";
$F_RETURNMESSAGES_LOCAL["GiveItemTo"]="{$GLOBALS["HERIKA_NAME"]} gives items to #TARGET#";
$F_RETURNMESSAGES_LOCAL["GoToSleep"]="{$GLOBALS["HERIKA_NAME"]} takes a nap";
$F_RETURNMESSAGES_LOCAL["UseSoulGaze"]="{$GLOBALS["HERIKA_NAME"]} used soulgaze";

$GLOBALS["F_RETURNMESSAGES"]=$F_RETURNMESSAGES_LOCAL;



// What is this?. We can translate functions or give them a custom name. 
// This array will handle translations. Plugin must receive the codename always.

$F_NAMES_LOCAL["Inspect"]="Inspect";
$F_NAMES_LOCAL["LookAt"]="LookAt";
$F_NAMES_LOCAL["InspectSurroundings"]="InspectSurroundings";
$F_NAMES_LOCAL["MoveTo"]= "MoveTo";
$F_NAMES_LOCAL["OpenInventory"]="ExchangeItems";
$F_NAMES_LOCAL["OpenInventory2"]="AcceptGift";
$F_NAMES_LOCAL["Attack"]="Attack";
$F_NAMES_LOCAL["AttackHunt"]="Hunt";
$F_NAMES_LOCAL["Follow"]="Follow";
$F_NAMES_LOCAL["CheckInventory"]="ListInventory";
$F_NAMES_LOCAL["SheatheWeapon"]="SheatheWeapon";
$F_NAMES_LOCAL["Relax"]="LetsRelax";
//$F_NAMES_LOCAL["LeadTheWayTo"]="LeadTheWayTo";
$F_NAMES_LOCAL["TakeASeat"]="TakeASeat";
$F_NAMES_LOCAL["ReadQuestJournal"]="ReadQuestJournal";
$F_NAMES_LOCAL["IncreaseWalkSpeed"]="IncreaseWalkSpeed";
$F_NAMES_LOCAL["DecreaseWalkSpeed"]="DecreaseWalkSpeed";
$F_NAMES_LOCAL["GetDateTime"]="GetDateTime";
$F_NAMES_LOCAL["SearchDiary"]="SearchDiary";
$F_NAMES_LOCAL["SetCurrentTask"]="SetCurrentTask";
$F_NAMES_LOCAL["ReadDiaryPage"]="ReadDiaryPage";
$F_NAMES_LOCAL["StopWalk"]="StopWalk";
$F_NAMES_LOCAL["TravelTo"]="TravelTo";
$F_NAMES_LOCAL["SearchMemory"]="TryToRemember";
$F_NAMES_LOCAL["WaitHere"]="WaitHere";
$F_NAMES_LOCAL["GiveItemToPlayer"]="GiveItemToPlayer";
$F_NAMES_LOCAL["TakeGoldFromPlayer"]="ReceiveCoinsFromPlayer";
$F_NAMES_LOCAL["FollowPlayer"]="FollowPlayer";
$F_NAMES_LOCAL["ComeCloser"]="ComeCloser";
$F_NAMES_LOCAL["Brawl"]="Fight";
$F_NAMES_LOCAL["ReturnBackHome"]="ExitLocation";
$F_NAMES_LOCAL["GiveGoldTo"]="GiveCoinsTo";
$F_NAMES_LOCAL["GiveItemTo"]="GiveItemToActor";
$F_NAMES_LOCAL["GoToSleep"]="GoToSleep";
$F_NAMES_LOCAL["UseSoulGaze"]="UseSoulGaze";


$GLOBALS["F_NAMES"]=$F_NAMES_LOCAL;

if (isset($GLOBALS["CORE_LANG"]))
	if (file_exists(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."lang".DIRECTORY_SEPARATOR.$GLOBALS["CORE_LANG"].DIRECTORY_SEPARATOR."functions.php")) 
		require_once(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."lang".DIRECTORY_SEPARATOR.$GLOBALS["CORE_LANG"].DIRECTORY_SEPARATOR."functions.php");
    
    
    
$GLOBALS["FUNCTIONS"] = [
    [
        "name" => $F_NAMES_LOCAL["Inspect"],
        "description" => $F_TRANSLATIONS_LOCAL["Inspect"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "target" => [
                    "type" => "string",
                    "description" => "Target NPC, Actor, or being",
                    "enum" => $GLOBALS['FUNCTION_PARM_INSPECT']

                ]
            ],
            "required" => ["target"],
        ],
    ],
    [
        "name" => $F_NAMES_LOCAL["InspectSurroundings"],
        "description" => $F_TRANSLATIONS_LOCAL["InspectSurroundings"],
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
        "name" => $F_NAMES_LOCAL["LookAt"],
        "description" => $F_TRANSLATIONS_LOCAL["Inspect"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "target" => [
                    "type" => "string",
                    "description" => "Target NPC, Actor, or being",
                    "enum" => $GLOBALS['FUNCTION_PARM_INSPECT']

                ]
            ],
            "required" => ["target"],
        ],
    ],
    [
        "name" => $F_NAMES_LOCAL["MoveTo"],
        "description" => $F_TRANSLATIONS_LOCAL["MoveTo"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "target" => [
                    "type" => "string",
                    "description" => "Visible Target NPC, Actor, or being, or building.",
                    "enum" => $GLOBALS['FUNCTION_PARM_MOVETO']
                ]
            ],
            "required" => ["target"],
        ],
    ],
    [
        "name" => $F_NAMES_LOCAL["OpenInventory"],
        "description" => $F_TRANSLATIONS_LOCAL["OpenInventory"],
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
        "name" => $F_NAMES_LOCAL["OpenInventory2"],
        "description" => $F_TRANSLATIONS_LOCAL["OpenInventory2"],
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
        "name" => $F_NAMES_LOCAL["Attack"],
        "description" => $F_TRANSLATIONS_LOCAL["Attack"],
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
        "name" => $F_NAMES_LOCAL["AttackHunt"],
        "description" => $F_TRANSLATIONS_LOCAL["AttackHunt"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "target" => [
                    "type" => "string",
                    "description" => "Target animal",
                ]
            ],
            "required" => ["target"],
        ]
        ],
    [
        "name" => $F_NAMES_LOCAL["Follow"],
        "description" => $F_TRANSLATIONS_LOCAL["Follow"],
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
        "name" => $F_NAMES_LOCAL["CheckInventory"],
        "description" => $F_TRANSLATIONS_LOCAL["CheckInventory"],
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
        "name" => $F_NAMES_LOCAL["SheatheWeapon"],
        "description" => $F_TRANSLATIONS_LOCAL["SheatheWeapon"],
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
        "name" => $F_NAMES_LOCAL["Relax"],
        "description" => $F_TRANSLATIONS_LOCAL["Relax"],
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
    /*[
        "name" => $F_NAMES_LOCAL["LeadTheWayTo"],
        "description" => $F_TRANSLATIONS_LOCAL["LeadTheWayTo"],
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
    ],*/
    [
        "name" => $F_NAMES_LOCAL["TravelTo"],
        "description" => $F_TRANSLATIONS_LOCAL["TravelTo"],
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
        "name" => $F_NAMES_LOCAL["TakeASeat"],
        "description" => $F_TRANSLATIONS_LOCAL["TakeASeat"],
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
        "name" => $F_NAMES_LOCAL["ReadQuestJournal"],
        "description" => $F_TRANSLATIONS_LOCAL["ReadQuestJournal"],
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
        "name" => $F_NAMES_LOCAL["IncreaseWalkSpeed"],
        "description" => $F_TRANSLATIONS_LOCAL["IncreaseWalkSpeed"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "speed" => [
                    "type" => "string",
                    "description" => "Speed",
                    "enum" => ["run",  "jog"]
                ]

            ],
            "required" => []
        ]
    ],
     [
        "name" => $F_NAMES_LOCAL["DecreaseWalkSpeed"],
        "description" => $F_TRANSLATIONS_LOCAL["DecreaseWalkSpeed"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "speed" => [
                    "type" => "string",
                    "description" => "Speed",
                    "enum" => [ "jog", "walk"]
                ]

            ],
            "required" => []
        ]
    ],
    [
        "name" => $F_NAMES_LOCAL["GetDateTime"],
        "description" => $F_TRANSLATIONS_LOCAL["GetDateTime"],
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
        "name" => $F_NAMES_LOCAL["SearchDiary"],
        "description" => $F_TRANSLATIONS_LOCAL["SearchDiary"],
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
        "name" => $F_NAMES_LOCAL["SetCurrentTask"],
        "description" => $F_TRANSLATIONS_LOCAL["SetCurrentTask"],
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
        "name" => $F_NAMES_LOCAL["StopWalk"],
        "description" => $F_TRANSLATIONS_LOCAL["StopWalk"],
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
        "name" => $F_NAMES_LOCAL["SearchMemory"],
        "description" => $F_TRANSLATIONS_LOCAL["SearchMemory"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "target" => [
                    "type" => "string",
                    "description" => "",
                ]
            ],
            "required" =>[""]
        ]
    ],
    [
            "name" => $F_NAMES_LOCAL["WaitHere"],
            "description" => $F_TRANSLATIONS_LOCAL["WaitHere"],
            "parameters" => [
                "type" => "object",
                "properties" => [
                    "target" => [
                        "type" => "string",
                        "description" => "",
                    ]
                ],
                "required" =>[""]
            ]
    ],
    [
            "name" => $F_NAMES_LOCAL["GiveItemToPlayer"],
            "description" => $F_TRANSLATIONS_LOCAL["GiveItemToPlayer"],
            "parameters" => [
                "type" => "object",
                "properties" => [
                    "target" => [
                        "type" => "string",
                        "description" => "",
                    ]
                ],
                "required" =>["target"]
            ]
    ],
    [
        "name" => $F_NAMES_LOCAL["TakeGoldFromPlayer"],
        "description" => $F_TRANSLATIONS_LOCAL["TakeGoldFromPlayer"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "target" => [
                    "type" => "string",
                    "description" => "",
                ]
            ],
            "required" =>["target"]
        ]
    ],
    [
            "name" => $F_NAMES_LOCAL["FollowPlayer"],
            "description" => $F_TRANSLATIONS_LOCAL["FollowPlayer"],
            "parameters" => [
                "type" => "object",
                "properties" => [
                    "target" => [
                        "type" => "string",
                        "description" => "",
                    ]
                ],
                "required" =>[""]
            ]
            ],
    [
            "name" => $F_NAMES_LOCAL["ComeCloser"],
            "description" => $F_TRANSLATIONS_LOCAL["ComeCloser"],
            "properties" => [
                "target" => [
                    "type" => "string",
                    "description" => "Keep it blank",
                ]
            ],
            "required" => [""]
    ],
    [
        "name" => $F_NAMES_LOCAL["Brawl"],
        "description" => $F_TRANSLATIONS_LOCAL["Brawl"],
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
        "name" => $F_NAMES_LOCAL["ReturnBackHome"],
        "description" => $F_TRANSLATIONS_LOCAL["ReturnBackHome"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "target" => [
                    "type" => "string",
                    "description" => "Keep it blank",
                ]
            ],
            "required" => [""],
        ]
    ],
    [
    "name" => $F_NAMES_LOCAL["GiveGoldTo"],
        "description" => $F_TRANSLATIONS_LOCAL["GiveGoldTo"],
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
        "name" => $F_NAMES_LOCAL["GiveItemTo"],
        "description" => $F_TRANSLATIONS_LOCAL["GiveItemTo"],
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
        "name" => $F_NAMES_LOCAL["GoToSleep"],
        "description" => $F_TRANSLATIONS_LOCAL["GoToSleep"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "target" => [
                    "type" => "string",
                    "description" => "Keep it blank",
                ]
            ],
            "required" => [""],
        ]
    ],
    [
        "name" => $F_NAMES_LOCAL["UseSoulGaze"],
        "description" => $F_TRANSLATIONS_LOCAL["UseSoulGaze"],
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
    ]
];

// Mantain a copy of all functions defined here
foreach ($GLOBALS["FUNCTIONS"] as $n=>$functionEntry)
    $GLOBALS["BASE_FUNCTIONS"][getFunctionCodeName($functionEntry["name"])]=$GLOBALS["FUNCTIONS"][$n];

// This function only is offered when SearchDiary
$FUNCTIONS_GHOSTED_LOCAL =  [
        "name" => $F_NAMES_LOCAL["ReadDiaryPage"],
        "description" => $F_TRANSLATIONS_LOCAL["ReadDiaryPage"],
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

$GLOBALS["FUNCTIONS_GHOSTED"]=$FUNCTIONS_GHOSTED_LOCAL;

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

function getFunctionByTrlName($searchValue) {
    $keys = [];

    foreach ($GLOBALS["F_NAMES"] as $key => $value) {
        if ($value === $searchValue) {
            return $key;
        }
    }
    
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

function unsetFunction($functionCodename) {
    if (($key = array_search($functionCodename, $GLOBALS["ENABLED_FUNCTIONS"])) !== false) {
        unset($GLOBALS["ENABLED_FUNCTIONS"][$key]);
        
    }

    foreach ($GLOBALS["FUNCTIONS"] as $n=>$v)
        if (!in_array(getFunctionCodeName($v["name"]),$GLOBALS["ENABLED_FUNCTIONS"])) {
            // error_log("Removing {$GLOBALS["FUNCTIONS"][$n]["name"]}");
            unset($GLOBALS["FUNCTIONS"][$n]);
        }
}

if (isset($GLOBALS["IS_NPC"])&&$GLOBALS["IS_NPC"]) { 
    $GLOBALS["ENABLED_FUNCTIONS"]=[
        'Inspect',
        //'LookAt',
        'InspectSurroundings',
        //'MoveTo',
        'OpenInventory',
        'OpenInventory2',
        'Attack',
        'AttackHunt',
        'TravelTo',
        'Follow',
        'CheckInventory',
        //'SheatheWeapon',
        'Relax',
        //'LeadTheWayTo',
        'TakeASeat',
        'IncreaseWalkSpeed',
        'DecreaseWalkSpeed',
        //'GetDateTime',
        //'SearchDiary',
        //'SetCurrentTask',
        //'SearchMemory',
        //'StopWalk'
        'WaitHere',
        'ComeCloser',
        //'GiveItemToPlayer',
        'TakeGoldFromPlayer',
        'FollowPlayer',
        'Brawl',
        'GiveGoldTo',
        'GiveItemTo',
    ];
} else {
    $GLOBALS["ENABLED_FUNCTIONS"]=[
        'Inspect',
        //'LookAt',
        'InspectSurroundings',
        //'MoveTo',
        'OpenInventory',
        'OpenInventory2',
        'Attack',
        'AttackHunt',
        'TravelTo',
        //'Follow',
        'CheckInventory',
        'SheatheWeapon',
        'Relax',
        //'LeadTheWayTo',
        'TakeASeat',
        'ReadQuestJournal',
        'IncreaseWalkSpeed',
        'DecreaseWalkSpeed',
        'WaitHere',
        'SetCurrentTask',
        'ComeCloser',
        //'GiveItemToPlayer',
        'TakeGoldFromPlayer',
        'Brawl',
        'GiveGoldTo',
        'GiveItemTo',
        'GoToSleep',
        'UseSoulGaze'
        //'GetDateTime',
        //'SearchDiary',
        //'SearchMemory',
        //'StopWalk'
    ];
}


$folderPath = __DIR__.DIRECTORY_SEPARATOR."../ext/";
requireFunctionFilesRecursively($folderPath);

// Why is this here?
if (file_exists(__DIR__.DIRECTORY_SEPARATOR."lang".DIRECTORY_SEPARATOR.$GLOBALS["CORE_LANG"].DIRECTORY_SEPARATOR."prompts.php")) {
    require(__DIR__.DIRECTORY_SEPARATOR."lang".DIRECTORY_SEPARATOR.$GLOBALS["CORE_LANG"].DIRECTORY_SEPARATOR."prompts.php");
}

if (file_exists(__DIR__.DIRECTORY_SEPARATOR."../prompts/prompts_custom.php")) {
    require(__DIR__.DIRECTORY_SEPARATOR."../prompts/prompts_custom.php");
}

// Delete non wanted functions    

foreach ($GLOBALS["FUNCTIONS"] as $n=>$v)
    if (!in_array(getFunctionCodeName($v["name"]),$GLOBALS["ENABLED_FUNCTIONS"])) {
            unset($GLOBALS["FUNCTIONS"][$n]);
    }

$GLOBALS["FUNCTIONS"]=array_values($GLOBALS["FUNCTIONS"]); //Get rid of array keys


?>
