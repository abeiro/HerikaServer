<?php

// Functions to be provided to OpenAI

$ENABLED_FUNCTIONS_LOCAL = [
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
    'PickupItem',
    'CastSpell',
    'GoToSleep',
    'UseSoulGaze',
    'MakeFollower',
    'Toast',
    'Drink',
    'StartRitualCeremony',
    'EndRitualCeremony',
    'Training'
    //    'WaitHere'
];

$GLOBALS["ENABLED_FUNCTIONS"] = $ENABLED_FUNCTIONS_LOCAL;

// Ensure PLAYER_NAME is defined before use in string templates below.
// Prefer database (conf_opts) value; fallback to existing global or 'Player'.
if (!isset($GLOBALS["PLAYER_NAME"]) || $GLOBALS["PLAYER_NAME"] === '') {
    $safePlayerName = 'Player';
    try {
        $rootPath = __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR;
        @include_once $rootPath . "conf" . DIRECTORY_SEPARATOR . "conf.php";
        if (isset($GLOBALS["DBDRIVER"]) && $GLOBALS["DBDRIVER"] !== '') {
            $dbClassFile = $rootPath . "lib" . DIRECTORY_SEPARATOR . $GLOBALS["DBDRIVER"] . ".class.php";
            if (!class_exists('sql') && file_exists($dbClassFile)) {
                require_once $dbClassFile;
            }
            if (class_exists('sql')) {
                $db_local = new sql();
                if (method_exists($db_local, 'fetchOne')) {
                    $row = $db_local->fetchOne("select value from conf_opts where id='PLAYER_NAME'");
                    if (is_array($row) && isset($row['value']) && $row['value'] !== '') {
                        $safePlayerName = (string) $row['value'];
                    }
                }
            }
        }
    } catch (Throwable $_) {
        // ignore and use fallback
    }
    $GLOBALS["PLAYER_NAME"] = $safePlayerName;
}

// We must use internal keys here.

$F_TRANSLATIONS_LOCAL["Inspect"] = "Inspects ONLY an ACTOR/NPC. Wait for result to give a dialogue message.";
$F_TRANSLATIONS_LOCAL["LookAt"] = "Inspects ONLY an ACTOR/NPC. Wait for result to give a dialogue message.";
$F_TRANSLATIONS_LOCAL["InspectSurroundings"] = "Looks for actors around.Wait for result to give a dialogue message";
$F_TRANSLATIONS_LOCAL["MoveTo"] = "Move to a visible building or visible actor, also used to guide {$GLOBALS["PLAYER_NAME"]} to a actor or building.";
$F_TRANSLATIONS_LOCAL["OpenInventory"] = "Initiates trading or exchange ITEMS with {$GLOBALS["PLAYER_NAME"]}.";
$F_TRANSLATIONS_LOCAL["OpenInventory2"] = "Initiates trading, {$GLOBALS["PLAYER_NAME"]} must give ITEMS to {$GLOBALS["HERIKA_NAME"]}";
$F_TRANSLATIONS_LOCAL["Attack"] = "Attack with intention to kill an Actor, NPC or entity.";
$F_TRANSLATIONS_LOCAL["AttackHunt"] = "Hunt with intention to kill an Actor, NPC or entity.";
$F_TRANSLATIONS_LOCAL["Follow"] = "Move to and follow the specified target actor";
$F_TRANSLATIONS_LOCAL["CheckInventory"] = "Search in {$GLOBALS["HERIKA_NAME"]}'s inventory, backpack or pocket. List their inventory contents";
$F_TRANSLATIONS_LOCAL["SheatheWeapon"] = "Sheathes/put away current weapon";
$F_TRANSLATIONS_LOCAL["Relax"] = "Stop whatever you are doing and relax at the current location.Used to Unwind,Loosen Up,Enjoy Moment,Chill";
$F_TRANSLATIONS_LOCAL["TravelTo"] = "Use it to move to major locations and landmarks and POIs.";
$F_TRANSLATIONS_LOCAL["TakeASeat"] = "{$GLOBALS["HERIKA_NAME"]} take a seat at seating location nearby.";
$F_TRANSLATIONS_LOCAL["ReadQuestJournal"] = "Only use if {$GLOBALS["PLAYER_NAME"]} explicitly ask for a quest. Get info about current quests";
$F_TRANSLATIONS_LOCAL["IncreaseWalkSpeed"] = "Increase {$GLOBALS["HERIKA_NAME"]} speed when moving or travelling";
$F_TRANSLATIONS_LOCAL["DecreaseWalkSpeed"] = "Decrease {$GLOBALS["HERIKA_NAME"]} speed when moving or travelling";
$F_TRANSLATIONS_LOCAL["GetDateTime"] = "Get Current Date and Time";
$F_TRANSLATIONS_LOCAL["SearchDiary"] = "Read {$GLOBALS["HERIKA_NAME"]}'s diary to make her remember something. Search in diary index";
$F_TRANSLATIONS_LOCAL["SetCurrentTask"] = "Set the current plan of action or task or quest";
$F_TRANSLATIONS_LOCAL["ReadDiaryPage"] = "Read {$GLOBALS["HERIKA_NAME"]}'s diary to access a specific topic";
$F_TRANSLATIONS_LOCAL["StopWalk"] = "Stop all {$GLOBALS["HERIKA_NAME"]}'s actions inmediately";
$F_TRANSLATIONS_LOCAL["TravelTo"] = "Only use if {$GLOBALS["PLAYER_NAME"]} explicitly suggest it. Guide {$GLOBALS["PLAYER_NAME"]} to a Town o City. Also known as lead the way";
$F_TRANSLATIONS_LOCAL["SearchMemory"] = "{$GLOBALS["HERIKA_NAME"]} tries to remember information. REPLY with hashtags";
$F_TRANSLATIONS_LOCAL["WaitHere"] = "{$GLOBALS["HERIKA_NAME"]} waits and loiters at the current location";
$F_TRANSLATIONS_LOCAL["GiveItemToPlayer"] = "{$GLOBALS["HERIKA_NAME"]} gives item (property target) to {$GLOBALS["PLAYER_NAME"]} (property listener)";
$F_TRANSLATIONS_LOCAL["TakeGoldFromPlayer"] = "{$GLOBALS["HERIKA_NAME"]} takes amount (property target) of gold from {$GLOBALS["PLAYER_NAME"]}, once {$GLOBALS["PLAYER_NAME"]} is agree. infer amount from context.";
$F_TRANSLATIONS_LOCAL["FollowPlayer"] = "{$GLOBALS["HERIKA_NAME"]} follows  {$GLOBALS["PLAYER_NAME"]}";
$F_TRANSLATIONS_LOCAL["ComeCloser"] = "{$GLOBALS["HERIKA_NAME"]} aproaches to {$GLOBALS["PLAYER_NAME"]}";
$F_TRANSLATIONS_LOCAL["Brawl"] = "{$GLOBALS["HERIKA_NAME"]} engages non lethtal combat with another actor, using weapons";
$F_TRANSLATIONS_LOCAL["ReturnBackHome"] = "{$GLOBALS["HERIKA_NAME"]} travels to home/origin place.Returns home.";
$F_TRANSLATIONS_LOCAL["GiveGoldTo"] = "{$GLOBALS["HERIKA_NAME"]} gives gold/coins/septims to another actor. Specify the amount to give";
$F_TRANSLATIONS_LOCAL["GiveItemTo"] = "{$GLOBALS["HERIKA_NAME"]} gives a specific item from inventory to another actor. REQUIRED: Must include 'item' field with exact item name from <inventory> tag, and 'target' field with recipient name";
$F_TRANSLATIONS_LOCAL["PickupItem"] = "{$GLOBALS["HERIKA_NAME"]} picks up a specific item from the ground. Use the exact RefID:ItemName format from nearby_items (e.g. 0x12345:Iron Sword)";
$F_TRANSLATIONS_LOCAL["GoToSleep"] = "{$GLOBALS["HERIKA_NAME"]} takes a nap";
$F_TRANSLATIONS_LOCAL["UseSoulGaze"] = "Use the spell SoulGaze, a powerful incantation that allows {$GLOBALS["HERIKA_NAME"]} to perceive surroundings in vivid detail through {$GLOBALS["PLAYER_NAME"]}'s eyes. The spell, however, causes some disturbance to the caster.";
$F_TRANSLATIONS_LOCAL["CastSpell"] = "{$GLOBALS["HERIKA_NAME"]} casts a spell on target actor. Must specify spell name from <spells> and target actor name. Use 'self' as target for self-targeted spells.";
$F_TRANSLATIONS_LOCAL["MakeFollower"] = "{$GLOBALS["HERIKA_NAME"]} joins to {$GLOBALS["PLAYER_NAME"]}, forming a squad or adventuring party";

$F_TRANSLATIONS_LOCAL["Toast"] = "Raises a glass in celebration or honor.";
$F_TRANSLATIONS_LOCAL["Drink"] = "Drinks a beverage to quench thirst or enjoy flavor.";
$F_TRANSLATIONS_LOCAL["StartRitualCeremony"] = "Participates in a ritual or ceremony, following its customs and practices.";
$F_TRANSLATIONS_LOCAL["EndRitualCeremony"] = "Concludes a ritual or ceremony, marking its completion.";
    
$F_TRANSLATIONS_LOCAL["Training"] = "Opens training menu to improve skills with a trainer.";


$F_RETURNMESSAGES_LOCAL["Inspect"] = "{$GLOBALS["HERIKA_NAME"]} inspects #TARGET# and see this: #RESULT#";
$F_RETURNMESSAGES_LOCAL["LookAt"] = "LOOK at or Inspects NPC, Actor, or being OUTFIT and GEAR";
$F_RETURNMESSAGES_LOCAL["InspectSurroundings"] = "{$GLOBALS["HERIKA_NAME"]} takes a look around and see this: #RESULT#";
$F_RETURNMESSAGES_LOCAL["MoveTo"] = "Walk to a visible building or visible actor, also used to guide {$GLOBALS["PLAYER_NAME"]} to a actor or building.";
$F_RETURNMESSAGES_LOCAL["OpenInventory"] = "Initiates trading or exchange items with {$GLOBALS["PLAYER_NAME"]}.";
$F_RETURNMESSAGES_LOCAL["OpenInventory2"] = "{$GLOBALS["PLAYER_NAME"]} give items to {$GLOBALS["HERIKA_NAME"]}. Accept gift.";
$F_RETURNMESSAGES_LOCAL["Attack"] = "{$GLOBALS["HERIKA_NAME"]} Attacks #TARGET# ";
$F_RETURNMESSAGES_LOCAL["AttackHunt"] = "{$GLOBALS["HERIKA_NAME"]} Attacks #TARGET# ";
$F_RETURNMESSAGES_LOCAL["Follow"] = "{$GLOBALS["HERIKA_NAME"]} follows #TARGET# ";
$F_RETURNMESSAGES_LOCAL["CheckInventory"] = "{$GLOBALS["HERIKA_NAME"]}'s INVENTORY:#RESULT#";
$F_RETURNMESSAGES_LOCAL["SheatheWeapon"] = "Sheathes/put away current weapon";
$F_RETURNMESSAGES_LOCAL["Relax"] = "{$GLOBALS["HERIKA_NAME"]} is relaxed. Time to enjoy life.";
$F_RETURNMESSAGES_LOCAL["LeadTheWayTo"] = "Only use if {$GLOBALS["PLAYER_NAME"]} explicitly orders it. Guide {$GLOBALS["PLAYER_NAME"]} to a Town o City. ";
$F_RETURNMESSAGES_LOCAL["TakeASeat"] = "{$GLOBALS["HERIKA_NAME"]} seats in nearby chair or furniture ";
$F_RETURNMESSAGES_LOCAL["ReadQuestJournal"] = "";
$F_RETURNMESSAGES_LOCAL["IncreaseWalkSpeed"] = "Increases {$GLOBALS["HERIKA_NAME"]} speed/pace when moving or travelling";
$F_RETURNMESSAGES_LOCAL["DecreaseWalkSpeed"] = "Decreases {$GLOBALS["HERIKA_NAME"]} speed/pace when moving or travelling";
$F_RETURNMESSAGES_LOCAL["GetDateTime"] = "Get Current Date and Time";
$F_RETURNMESSAGES_LOCAL["SearchDiary"] = "Read {$GLOBALS["HERIKA_NAME"]}'s diary to make her remember something. Search in diary index";
$F_RETURNMESSAGES_LOCAL["SetCurrentTask"] = "Set the current plan of action or task or quest";
$F_RETURNMESSAGES_LOCAL["ReadDiaryPage"] = "Read {$GLOBALS["HERIKA_NAME"]}'s diary to access a specific topic";
$F_RETURNMESSAGES_LOCAL["StopWalk"] = "Stop all {$GLOBALS["HERIKA_NAME"]}'s actions inmediately";
$F_RETURNMESSAGES_LOCAL["TravelTo"] = "{$GLOBALS["HERIKA_NAME"]} begins travelling to #TARGET#";
$F_RETURNMESSAGES_LOCAL["SearchMemory"] = "{$GLOBALS["HERIKA_NAME"]} tries to remember information. JUST REPLY something like 'Let me think' and wait";
$F_RETURNMESSAGES_LOCAL["WaitHere"] = "{$GLOBALS["HERIKA_NAME"]} waits and stands at the place";
$F_RETURNMESSAGES_LOCAL["GiveItemToPlayer"] = "{$GLOBALS["HERIKA_NAME"]} gave #TARGET# to {$GLOBALS["PLAYER_NAME"]}.If this a transaction, maybe TakeGoldFromPlayer is needed.";
$F_RETURNMESSAGES_LOCAL["TakeGoldFromPlayer"] = "{$GLOBALS["PLAYER_NAME"]} gave #TARGET# coins to {$GLOBALS["HERIKA_NAME"]}. If this a transaction, maybe GiveItemToPlayer is needed.";
$F_RETURNMESSAGES_LOCAL["FollowPlayer"] = "{$GLOBALS["HERIKA_NAME"]} follows {$GLOBALS["PLAYER_NAME"]}";
$F_RETURNMESSAGES_LOCAL["Brawl"] = "{$GLOBALS["HERIKA_NAME"]} Attacks #TARGET# ";
$F_RETURNMESSAGES_LOCAL["ReturnBackHome"] = "{$GLOBALS["HERIKA_NAME"]} goes back home";
$F_RETURNMESSAGES_LOCAL["GiveGoldTo"] = "{$GLOBALS["HERIKA_NAME"]} gives #TARGET# gold";
$F_RETURNMESSAGES_LOCAL["GiveItemTo"] = "{$GLOBALS["HERIKA_NAME"]} gives #ITEM# to #TARGET#";
$F_RETURNMESSAGES_LOCAL["PickupItem"] = "{$GLOBALS["HERIKA_NAME"]} picks up #ITEM#";
$F_RETURNMESSAGES_LOCAL["GoToSleep"] = "{$GLOBALS["HERIKA_NAME"]} takes a nap";
$F_RETURNMESSAGES_LOCAL["UseSoulGaze"] = "{$GLOBALS["HERIKA_NAME"]} used soulgaze";
$F_RETURNMESSAGES_LOCAL["CastSpell"] = "{$GLOBALS["HERIKA_NAME"]} casts #ITEM# on #TARGET#";
$F_RETURNMESSAGES_LOCAL["MakeFollower"] = "{$GLOBALS["HERIKA_NAME"]} is now part of the adventuring party";

$F_RETURNMESSAGES_LOCAL["Toast"] = "{$GLOBALS["HERIKA_NAME"]} raises a glass in celebration or honor.";      
$F_RETURNMESSAGES_LOCAL["Drink"] = "{$GLOBALS["HERIKA_NAME"]} drinks a beverage to quench thirst or enjoy flavor.";
$F_RETURNMESSAGES_LOCAL["StartRitualCeremony"] = "{$GLOBALS["HERIKA_NAME"]} begins a ritual or ceremony, following its customs and practices.";
$F_RETURNMESSAGES_LOCAL["EndRitualCeremony"] = "{$GLOBALS["HERIKA_NAME"]} concludes a ritual or ceremony, marking its completion.";
$F_RETURNMESSAGES_LOCAL["Training"] = "{$GLOBALS["HERIKA_NAME"]} opens the training menu.";

// What is this?. We can translate functions or give them a custom name.
// This array will handle translations. Plugin must receive the codename always.

$F_NAMES_LOCAL["Inspect"] = "Inspect";
$F_NAMES_LOCAL["LookAt"] = "LookAt";
$F_NAMES_LOCAL["InspectSurroundings"] = "InspectSurroundings";
$F_NAMES_LOCAL["MoveTo"] = "MoveTo";
$F_NAMES_LOCAL["OpenInventory"] = "ExchangeItems";
$F_NAMES_LOCAL["OpenInventory2"] = "AcceptGift";
$F_NAMES_LOCAL["Attack"] = "Attack";
$F_NAMES_LOCAL["AttackHunt"] = "Hunt";
$F_NAMES_LOCAL["Follow"] = "Follow";
$F_NAMES_LOCAL["CheckInventory"] = "ListInventory";
$F_NAMES_LOCAL["SheatheWeapon"] = "SheatheWeapon";
$F_NAMES_LOCAL["Relax"] = "LetsRelax";
//$F_NAMES_LOCAL["LeadTheWayTo"]="LeadTheWayTo";
$F_NAMES_LOCAL["TakeASeat"] = "TakeASeat";
$F_NAMES_LOCAL["ReadQuestJournal"] = "ReadQuestJournal";
$F_NAMES_LOCAL["IncreaseWalkSpeed"] = "IncreaseWalkSpeed";
$F_NAMES_LOCAL["DecreaseWalkSpeed"] = "DecreaseWalkSpeed";
$F_NAMES_LOCAL["GetDateTime"] = "GetDateTime";
$F_NAMES_LOCAL["SearchDiary"] = "SearchDiary";
$F_NAMES_LOCAL["SetCurrentTask"] = "SetCurrentTask";
$F_NAMES_LOCAL["ReadDiaryPage"] = "ReadDiaryPage";
$F_NAMES_LOCAL["StopWalk"] = "StopWalk";
$F_NAMES_LOCAL["TravelTo"] = "TravelTo";
$F_NAMES_LOCAL["SearchMemory"] = "TryToRemember";
$F_NAMES_LOCAL["WaitHere"] = "WaitHere";
$F_NAMES_LOCAL["GiveItemToPlayer"] = "GiveItemToPlayer";
$F_NAMES_LOCAL["TakeGoldFromPlayer"] = "TakeMoneyFrom{$GLOBALS["PLAYER_NAME"]}"; // Mmm
$F_NAMES_LOCAL["FollowPlayer"] = "FollowPlayer";
$F_NAMES_LOCAL["ComeCloser"] = "ComeCloser";
$F_NAMES_LOCAL["Brawl"] = "Fight";
$F_NAMES_LOCAL["ReturnBackHome"] = "ReturnBackHome";
$F_NAMES_LOCAL["GiveGoldTo"] = "GiveGoldTo";
$F_NAMES_LOCAL["GiveItemTo"] = "GiveItemTo";
$F_NAMES_LOCAL["PickupItem"] = "PickupItem";
$F_NAMES_LOCAL["GoToSleep"] = "GoToSleep";
$F_NAMES_LOCAL["UseSoulGaze"] = "UseSoulGaze";
$F_NAMES_LOCAL["CastSpell"] = "CastSpell";
$F_NAMES_LOCAL["MakeFollower"] = "JoinTo{$GLOBALS["PLAYER_NAME"]}Squad";

$F_NAMES_LOCAL["Toast"] = "MakeAToast";
$F_NAMES_LOCAL["Drink"] = "Drink";
$F_NAMES_LOCAL["StartRitualCeremony"] = "StartRitualCeremony";
$F_NAMES_LOCAL["EndRitualCeremony"] = "EndRitualCeremony";

$F_NAMES_LOCAL["Training"] = "Training";

if (isset($GLOBALS["CORE_LANG"])) {
    if (file_exists(__DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "lang" . DIRECTORY_SEPARATOR . $GLOBALS["CORE_LANG"] . DIRECTORY_SEPARATOR . "functions.php")) {
        require_once __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "lang" . DIRECTORY_SEPARATOR . $GLOBALS["CORE_LANG"] . DIRECTORY_SEPARATOR . "functions.php";
    }
}

$GLOBALS["F_TRANSLATIONS"] = $F_TRANSLATIONS_LOCAL;
$GLOBALS["F_RETURNMESSAGES"] = $F_RETURNMESSAGES_LOCAL;
$GLOBALS["F_NAMES"] = $F_NAMES_LOCAL;

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
                    "enum" => isset($GLOBALS['FUNCTION_PARM_INSPECT']) ? $GLOBALS['FUNCTION_PARM_INSPECT'] : [],

                ],
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
                ],
            ],
            "required" => [],
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
                    "enum" => isset($GLOBALS['FUNCTION_PARM_INSPECT']) ? $GLOBALS['FUNCTION_PARM_INSPECT'] : [],

                ],
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
                    "enum" => isset($GLOBALS['FUNCTION_PARM_MOVETO']) ? $GLOBALS['FUNCTION_PARM_MOVETO'] : [],
                ],
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
                ],
            ],
            "required" => [],
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
                ],
            ],
            "required" => [],
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
                ],
            ],
            "required" => ["target"],
        ],
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
                ],
            ],
            "required" => ["target"],
        ],
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
                ],
            ],
            "required" => ["target"],
        ],
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
                ],
            ],
            "required" => [],
        ],
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
                ],
            ],
            "required" => [],
        ],
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
                ],
            ],
            "required" => [],
        ],
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
                    "description" => "Town or City to travel to, only if {$GLOBALS["PLAYER_NAME"]} explicitly orders it",

                ],
            ],
            "required" => ["location"],
        ],
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
                ],
            ],
            "required" => [""],
        ],
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
                ],
            ],
            "required" => [""],
        ],
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
                    "enum" => ["run", "jog"],
                ],

            ],
            "required" => [],
        ],
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
                    "enum" => ["jog", "walk"],
                ],

            ],
            "required" => [],
        ],
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
                ],

            ],
            "required" => [],
        ],
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
                ],
            ],
            "required" => [""],
        ],
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
                ],
            ],
            "required" => ["description"],
        ],
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
                ],
            ],
            "required" => [""],
        ],
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
                ],
            ],
            "required" => [""],
        ],
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
                ],
            ],
            "required" => [""],
        ],
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
                ],
            ],
            "required" => ["target"],
        ],
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
                ],
            ],
            "required" => ["target"],
        ],
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
                ],
            ],
            "required" => [""],
        ],
    ],
    [
        "name" => $F_NAMES_LOCAL["ComeCloser"],
        "description" => $F_TRANSLATIONS_LOCAL["ComeCloser"],
        "properties" => [
            "target" => [
                "type" => "string",
                "description" => "Keep it blank",
            ],
        ],
        "required" => [""],
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
                ],
            ],
            "required" => ["target"],
        ],
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
                ],
            ],
            "required" => [""],
        ],
    ],
    [
        "name" => $F_NAMES_LOCAL["GiveGoldTo"],
        "description" => $F_TRANSLATIONS_LOCAL["GiveGoldTo"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "target" => [
                    "type" => "string",
                    "description" => "Target NPC, Actor, or being to receive gold",
                ],
                "item" => [
                    "type" => "string",
                    "description" => "Amount of gold to give (number as string)",
                ]
            ],
            "required" => ["target", "item"],
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
                    "description" => "Target NPC, Actor, or being to receive the item",
                ],
                "item" => [
                    "type" => "string",
                    "description" => "REQUIRED: Exact name of item from <inventory> tag. Must match item name exactly.",
                ],
                "amount" => [
                    "type" => "integer",
                    "description" => "Number of items to give (default: 1). Cannot exceed quantity in <inventory>.",
                ],
            ],
            "required" => ["target", "item"],
        ],
    ],
    [
        "name" => $F_NAMES_LOCAL["PickupItem"],
        "description" => $F_TRANSLATIONS_LOCAL["PickupItem"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "target" => [
                    "type" => "string",
                    "description" => "Target actor (leave empty for PickupItem)",
                ],
                "item" => [
                    "type" => "string",
                    "description" => "REQUIRED: Exact RefID:ItemName from <nearby_items> tag (e.g., 0x12345:Iron Sword). Must match format exactly.",
                ],
            ],
            "required" => ["item"],
        ],
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
                ],
            ],
            "required" => [""],
        ],
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
                ],
            ],
            "required" => [],
        ],
    ],
    [
        "name" => $F_NAMES_LOCAL["CastSpell"],
        "description" => $F_TRANSLATIONS_LOCAL["CastSpell"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "target" => [
                    "type" => "string",
                    "description" => "Target actor name, or 'self' for self-cast spells",
                ],
                "item" => [
                    "type" => "string",
                    "description" => "REQUIRED: Spell name from <spells> tag (exact name)",
                ],
            ],
            "required" => ["target", "item"],
        ],
    ],
    [
        "name" => $F_NAMES_LOCAL["MakeFollower"],
        "description" => $F_TRANSLATIONS_LOCAL["MakeFollower"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "target" => [
                    "type" => "string",
                    "description" => "Keep it blank",
                ],
            ],
            "required" => [""],
        ],
    ],
     [
        "name" => $F_NAMES_LOCAL["Toast"],
        "description" => $F_TRANSLATIONS_LOCAL["Toast"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "target" => [
                    "type" => "string",
                    "description" => "Keep it blank",
                ],
            ],
            "required" => [""],
        ],
    ],
     [
        "name" => $F_NAMES_LOCAL["Drink"],
        "description" => $F_TRANSLATIONS_LOCAL["Drink"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "target" => [
                    "type" => "string",
                    "description" => "Keep it blank",
                ],
            ],
            "required" => [""],
        ],
    ],
    [
        "name" => $F_NAMES_LOCAL["Training"],
        "description" => $F_TRANSLATIONS_LOCAL["Training"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "target" => [
                    "type" => "string",
                    "description" => "Keep it blank",
                ],
            ],
            "required" => [""],
        ],
    ],

    [
        "name" => $F_NAMES_LOCAL["StartRitualCeremony"],
        "description" => $F_TRANSLATIONS_LOCAL["StartRitualCeremony"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "target" => [
                    "type" => "string",
                    "description" => "Type of ceremony or ritual to start:Religious, Magical, Cultural, Personal, Blood",
                ],
            ],
            "required" => [""],
        ],
    ],
     [
        "name" => $F_NAMES_LOCAL["EndRitualCeremony"],
        "description" => $F_TRANSLATIONS_LOCAL["EndRitualCeremony"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "target" => [
                    "type" => "string",
                    "description" => "Keep it blank",
                ],
            ],
            "required" => [""],
        ],
    ],
];

// Mantain a copy of all functions defined here
foreach ($GLOBALS["FUNCTIONS"] as $n => $functionEntry) {
    $GLOBALS["BASE_FUNCTIONS"][getFunctionCodeName($functionEntry["name"])] = $GLOBALS["FUNCTIONS"][$n];
}

// This function only is offered when SearchDiary
$FUNCTIONS_GHOSTED_LOCAL = [
    "name" => $F_NAMES_LOCAL["ReadDiaryPage"],
    "description" => $F_TRANSLATIONS_LOCAL["ReadDiaryPage"],
    "parameters" => [
        "type" => "object",
        "properties" => [
            "page" => [
                "type" => "string",
                "description" => "topic to search in full-text query syntax",
            ],
        ],
        "required" => ["topic"],
    ],
]
;

$GLOBALS["FUNCTIONS_GHOSTED"] = $FUNCTIONS_GHOSTED_LOCAL;

function getFunctionCodeName($key)
{
    if (!isset($GLOBALS["F_NAMES"]) || !is_array($GLOBALS["F_NAMES"])) {
        return false;
    }

    $functionCode = array_search($key, $GLOBALS["F_NAMES"]);

    // If not found, return false (function will be filtered out)
    return $functionCode !== false ? $functionCode : false;
}

function getFunctionTrlName($key)
{
    if (isset($GLOBALS["F_NAMES"][$key]) && !empty($GLOBALS["F_NAMES"][$key])) {
        return $GLOBALS["F_NAMES"][$key];
    } else {
        return $key;
    }

}

function findFunctionByName($name)
{
    foreach ($GLOBALS["FUNCTIONS"] as $function) {
        if ($function['name'] === $name) {
            return $function;
        }
    }
    return null; // Return null if function not found
}

function getFunctionByTrlName($searchValue)
{
    $keys = [];

    foreach ($GLOBALS["F_NAMES"] as $key => $value) {
        if ($value === $searchValue) {
            return $key;
        }
    }

}

function requireFunctionFilesRecursively($dir)
{
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

function unsetFunction($functionCodename)
{
    if (($key = array_search($functionCodename, $GLOBALS["ENABLED_FUNCTIONS"])) !== false) {
        unset($GLOBALS["ENABLED_FUNCTIONS"][$key]);

    }

    foreach ($GLOBALS["FUNCTIONS"] as $n => $v) {
        if (!in_array(getFunctionCodeName($v["name"]), $GLOBALS["ENABLED_FUNCTIONS"])) {
            // error_log("Removing {$GLOBALS["FUNCTIONS"][$n]["name"]}");
            unset($GLOBALS["FUNCTIONS"][$n]);
        }
    }

}

if (isset($GLOBALS["IS_NPC"]) && $GLOBALS["IS_NPC"]) {
    $GLOBALS["ENABLED_FUNCTIONS"] = [
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
        //'CheckInventory', // Commented out - redundant since <inventory> tag already shows NPC's items. Could be repurposed to check OTHER NPCs' inventories later.
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
        'PickupItem',
        'GoToSleep',
        'UseSoulGaze',
        'CastSpell',
        'MakeFollower',
        'Drink',
        'Toast',
        'StartRitualCeremony',
        'EndRitualCeremony',
        'Training'

    ];
    error_log("[DEBUG functions.php] IS_NPC=true, CastSpell in ENABLED: " . (in_array('CastSpell', $GLOBALS["ENABLED_FUNCTIONS"]) ? "YES" : "NO"));
} else {
    $GLOBALS["ENABLED_FUNCTIONS"] = [
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
        //'CheckInventory', // Commented out - redundant since <inventory> tag already shows NPC's items. Could be repurposed to check OTHER NPCs' inventories later.
        'SheatheWeapon',
        'Relax',
        //'LeadTheWayTo',
        'TakeASeat',
        'ReadQuestJournal',
        'IncreaseWalkSpeed',
        'DecreaseWalkSpeed',
        'WaitHere',
        //'SetCurrentTask',
        'ComeCloser',
        //'GiveItemToPlayer',
        'TakeGoldFromPlayer',
        'Brawl',
        'GiveGoldTo',
        'GiveItemTo',
        'PickupItem',
        'GoToSleep',
        'UseSoulGaze',
        'CastSpell',
        'Drink',
        'Toast',
        'Training',
        'StartRitualCeremony',
        'EndRitualCeremony',
        //'GetDateTime',
        //'SearchDiary',
        //'SearchMemory',
        //'StopWalk'
    ];
}

$folderPath = __DIR__ . DIRECTORY_SEPARATOR . "../ext/";
requireFunctionFilesRecursively($folderPath);

// Why is this here?
if (file_exists(__DIR__ . DIRECTORY_SEPARATOR . "lang" . DIRECTORY_SEPARATOR . $GLOBALS["CORE_LANG"] . DIRECTORY_SEPARATOR . "prompts.php")) {
    require __DIR__ . DIRECTORY_SEPARATOR . "lang" . DIRECTORY_SEPARATOR . $GLOBALS["CORE_LANG"] . DIRECTORY_SEPARATOR . "prompts.php";
}

if (file_exists(__DIR__ . DIRECTORY_SEPARATOR . "../prompts/prompts_custom.php")) {
    require __DIR__ . DIRECTORY_SEPARATOR . "../prompts/prompts_custom.php";
}

// Delete non wanted functions

foreach ($GLOBALS["FUNCTIONS"] as $n => $v) {
    $codeName = getFunctionCodeName($v["name"]);
    if ($codeName === false) {
        error_log("[FUNCTION] Warning: Could not get code name for function: {$v["name"]}");
        continue;
    }
    if (!in_array($codeName, $GLOBALS["ENABLED_FUNCTIONS"])) {
        error_log("[FUNCTION] Removing $n {$v["name"]}:$codeName");
        unset($GLOBALS["FUNCTIONS"][$n]);
    } 
    
    $GLOBALS["DEFINED_FUNCTIONS"][] = $codeName;
    
}

file_put_contents(__DIR__ . "/../log/bug_func.txt", print_r($GLOBALS["FUNCTIONS"], true));
file_put_contents(__DIR__ . "/../log/bug_func.txt", print_r($GLOBALS["ENABLED_FUNCTIONS"], true), FILE_APPEND);
file_put_contents(__DIR__ . "/../log/bug_func.txt", print_r($GLOBALS["ENABLED_FUNCTIONS"], true), FILE_APPEND);

$GLOBALS["FUNCTIONS"] = array_values($GLOBALS["FUNCTIONS"]); //Get rid of array keys


// POST FILTER HOOK. Used for cleaning actions returned by LLM
// We are putting this here because we want this actions to be executed serverside via ScriptProxy
// They will NOT be sent to DLL for execution using the standard method

require_once __DIR__ . "/../lib/scriptproxy_papyrus.php";

// action_post_process_fnct_ex is an arrya containing functions that process the actions after they are generated by the LLM
// more working examples in data_functions.php
$GLOBALS["action_post_process_fnct_ex"][]=function($actions) {
    
    global $gameRequest;

    $actionsCopy=$actions;
    foreach ($actions as $n=>$action) {
        
        $actionParts=explode("|",$action);
        $actionParts2=explode("@",$actionParts[2]);
        
        if (isset($actionParts2[0])) {
            // Parameter part 
            if ($actionParts2[0]=="Drink") {
               
                // Make NPC to toast
                $npcMaster = new Npcmaster();
                $npcData   = $npcMaster->getByName($actionParts[0]);

                $metadata=$npcMaster->getMetadata($npcData);

                if (isset($metadata["furniture"]) && $metadata["furniture"]=="Chair") {
                    $animation="0x00065d07";//ChairDrinkingStart (0x00065d07)
                }  else 
                    $animation="0x00103656";//DrinkIdle (0x00065d07)

                $skyrimCmd = new SkyrimCommandBuilder();
                $json      = $skyrimCmd->Actor->PlayIdle("0x{$npcData["refid"]}", $animation);// DrinkIdle Start                $skyrimCmd->send($json);
                $skyrimCmd->send(cmd: $json);

                unset($actionsCopy[$n]);// Remove action from list, so client does not execute it
                
                $GLOBALS["db"]->insert(
                    'actions_issued',
                    array(
                        'action' => "Drink",
                        'fullcall' =>$actionParts[0]."|".$actionParts[1]."|".$actionParts[2],
                        'actorname'=> $actionParts[0],
                        'ts' => $gameRequest[1],
                        'gamets' => $gameRequest[2],
                        'localts'=>time(),
                        'original'=>''
                    )
                );

                error_log("[ACTION POSTFILTER Drink] Executed server-side");

            } else  if ($actionParts2[0]=="Toast") {
                
                $npcMaster = new Npcmaster();
                $npcData   = $npcMaster->getByName($actionParts[0]);

                $skyrimCmd = new SkyrimCommandBuilder();
                $json      = $skyrimCmd->Actor->PlayIdle("0x{$npcData["refid"]}", "0x0010528a");// Toast Start                $skyrimCmd->send($json);
                $skyrimCmd->send(cmd: $json);

                
                $totalChars = 0;
                if (isset($GLOBALS["DEBUG"]["BUFFER"]) && is_array($GLOBALS["DEBUG"]["BUFFER"])) {
                    foreach ($GLOBALS["DEBUG"]["BUFFER"] as $item) {
                        $str = is_string($item) ? $item : (string)$item;
                        $totalChars += mb_strlen($str, 'UTF-8');
                    }
                } 
                error_log("[POST-FILTER] Toast: Current buffer size before delay: " . $totalChars . " chars");
                $timeToWait= ceil($totalChars / 8); // 1 second per 8 chars
                
                $json      = $skyrimCmd->Actor->PlayIdle("0x{$npcData["refid"]}", "0x00103656");// DrinkIdle Start                $skyrimCmd->send($json);
                $skyrimCmd->send(cmd: $json, localts:time()+$timeToWait);  // 30 seconds later actually drink to avoid NPC stuck in toast animation

                unset($actionsCopy[$n]);// Remove action from list, so client does not execute it

                $GLOBALS["db"]->insert(
                    'actions_issued',
                    array(
                        'action' => "Toast",
                        'fullcall' =>$actionParts[0]."|".$actionParts[1]."|".$actionParts[2],
                        'actorname'=> $actionParts[0],
                        'ts' => $gameRequest[1],
                        'gamets' => $gameRequest[2],
                        'localts'=>time(),
                        'original'=>''
                    )
                );

                error_log("[ACTION POSTFILTER Toast] Executed server-side");

            } else if (preg_match('/^Train(.+)$/', $actionParts2[0], $matches)) {
                // Training function called - send rolecommand to open training menu
                $GLOBALS["db"]->insert(
                    'responselog',
                    array(
                        'localts' => time(),
                        'sent' => 0,
                        'actor' => "rolemaster",
                        'text' => '',
                        'action' => "rolecommand|ShowTrainingMenu@{$actionParts[0]}",
                        'tag' => ""
                    )
                );
                
                $GLOBALS["db"]->insert(
                    'actions_issued',
                    array(
                        'action' => "Training",
                        'fullcall' =>$actionParts[0]."|".$actionParts[1]."|".$actionParts[2],
                        'actorname'=> $actionParts[0],
                        'ts' => $gameRequest[1],
                        'gamets' => $gameRequest[2],
                        'localts'=>time(),
                        'original'=>''
                    )
                );
                
                unset($actionsCopy[$n]);// Remove action from list, so client does not execute it

            } else if ($actionParts2[0]=="StartRitualCeremony") {
                
                $npcMaster = new Npcmaster();
                $npcData   = $npcMaster->getByName($actionParts[0]);

                $defAnim="0x000f11e1";// IdleRitualSkull1
                $shader="0x00050f02";// RitualSkullShader
                if (isset($actionParts2[1])) {
                    //Religious, Magical, Cultural, Personal, Blood
                    if ($actionParts2[1]== "Religious") {
                        $defAnim="0x0006f300";// IdlePray
                    } else if ($actionParts2[1]== "Magical") {

                        $skyrimCmd = new SkyrimCommandBuilder();
                        $json      = $skyrimCmd->Actor->PlayIdle("0x{$npcData["refid"]}", "0x000f11e2");//IdleRitualSkull2
                        $skyrimCmd->send(cmd: $json);
                        $json = $skyrimCmd->EffectShader->Play("0x0005fb82", "0x{$npcData["refid"]}", 20);
                        $skyrimCmd->send(cmd: $json);
                        

                    } else if ($actionParts2[1]== "Cultural") {
                        $defAnim="0x000f11e4";// IdleCrouchedPray
                    } else if ($actionParts2[1]== "Personal") {
                        $defAnim="0x000f11e5";// IdleCrouchedPrayEnterInstant
                    } else if ($actionParts2[1]== "Blood") {
                        $skyrimCmd = new SkyrimCommandBuilder();
                        $json      = $skyrimCmd->Actor->PlayIdle("0x{$npcData["refid"]}", "0x000af886");//IdleHandCut
                        $skyrimCmd->send(cmd: $json);
                        $json = $skyrimCmd->EffectShader->Play("0x0010f505", "0x{$npcData["refid"]}", 20);
                        $skyrimCmd->send(cmd: $json);
                        $json      = $skyrimCmd->Actor->PlayIdle("0x{$npcData["refid"]}", "0x0006f300");//idlePray
                        $skyrimCmd->send(cmd: $json,    localts: time()+10);//10 seconds later
                        
                    }
                } else {

                    $skyrimCmd = new SkyrimCommandBuilder();
                    $json      = $skyrimCmd->Actor->PlayIdle("0x{$npcData["refid"]}", $defAnim);
                    $skyrimCmd->send(cmd: $json);
                    $json = $skyrimCmd->EffectShader->Play($shader, "0x{$npcData["refid"]}", 20);
                    $skyrimCmd->send(cmd: $json);
                }

                $GLOBALS["db"]->insert(
                    'rolemaster',
                    [
                        'localts' => time(),
                        'ttl' => 60,
                        'type' => "scenenote",
                        'data' => "{$actionParts[0]} is celebrating a ritual",
                    ]
                );
                unset($actionsCopy[$n]);// Remove action from list, so client does not execute it

                $GLOBALS["db"]->insert(
                    'actions_issued',
                    array(
                        'action' => "StartRitualCeremony",
                        'fullcall' =>$actionParts[0]."|".$actionParts[1]."|".$actionParts[2],
                        'actorname'=> $actionParts[0],
                        'ts' => $gameRequest[1],
                        'gamets' => $gameRequest[2],
                        'localts'=>time(),
                        'original'=>''
                    )
                );

                error_log("[ACTION POSTFILTER Toast] Executed server-side");

            } else if ($actionParts2[0]=="EndRitualCeremony") {
                
                $npcMaster = new Npcmaster();
                $npcData   = $npcMaster->getByName($actionParts[0]);

                $skyrimCmd = new SkyrimCommandBuilder();
                $json      = $skyrimCmd->Actor->PlayIdle("0x{$npcData["refid"]}", "0x000f11e3");// IdleRitualSkull3
                $skyrimCmd->send(cmd: $json);

                $GLOBALS["db"]->insert(
                    'rolemaster',
                    [
                        'localts' => time(),
                        'ttl' => 30,
                        'type' => "scenenote",
                        'data' => "{$actionParts[0]} just ended the ritual celebration",
                    ]
                );
                unset($actionsCopy[$n]);// Remove action from list, so client does not execute it

                $GLOBALS["db"]->insert(
                    'actions_issued',
                    array(
                        'action' => "EndRitualCeremony",
                        'fullcall' =>$actionParts[0]."|".$actionParts[1]."|".$actionParts[2],
                        'actorname'=> $actionParts[0],
                        'ts' => $gameRequest[1],
                        'gamets' => $gameRequest[2],
                        'localts'=>time(),
                        'original'=>''
                    )
                );

                error_log("[ACTION POSTFILTER Toast] Executed server-side");
            }
        }
    }

    return $actionsCopy;
};