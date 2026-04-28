<?php

// Functions to be provided to OpenAI

$ENABLED_FUNCTIONS_LOCAL = [
    'MoveTo',
    'OpenInventory',
    'OpenInventory2',
    'Attack',
    'Follow',
    'CheckInventory',
    'SheatheWeapon',
    'Relax',
    'LeadTheWayTo',
    'TakeASeat',
    'IncreaseWalkSpeed',
    'DecreaseWalkSpeed',
    'StopWalk',
    'TravelTo',
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
    'Consume',
    'StartRitualCeremony',
    'EndRitualCeremony',
    'Training',
    'RentRoom',
    'HireCarriage',
    'HireFerry',
    'AddBounty',
    'PayBounty',
    'ArrestPlayer',
    'ForgiveCrime',
    'EndConversation'
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

require_once __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "lib" . DIRECTORY_SEPARATOR . "core" . DIRECTORY_SEPARATOR . "action_catalog.php";

function getConfiguredPositiveActionGoldCost($codeName, $configKey, $defaultCost)
{
    $override = null;
    if (function_exists('herikaActionCatalogGetCustomConfigValue')) {
        $override = herikaActionCatalogGetCustomConfigValue($codeName, $configKey, null);
    }

    $overrideCost = intval($override);
    if ($override !== null && $overrideCost > 0) {
        return $overrideCost;
    }

    return intval($defaultCost);
}

function formatConfiguredActionGoldCost($cost)
{
    $cost = intval($cost);
    return ($cost === 1) ? '1 gold' : ("{$cost} gold");
}

function getConfiguredRentRoomCost()
{
    return getConfiguredPositiveActionGoldCost("RentRoom", "rent_room_cost", 10);
}

function getConfiguredHireCarriageCost()
{
    return getConfiguredPositiveActionGoldCost("HireCarriage", "hire_carriage_cost", 20);
}

function getConfiguredHireFerryCost()
{
    return getConfiguredPositiveActionGoldCost("HireFerry", "hire_ferry_cost", 50);
}

function decodeFunctionExecutionParameterPayload($parameter)
{
    if (is_array($parameter)) {
        return $parameter;
    }

    $text = trim(strval($parameter));
    if ($text === '' || $text[0] !== '{') {
        return null;
    }

    $decoded = json_decode($text, true);
    return is_array($decoded) ? $decoded : null;
}

function buildTravelExecutionParameter($parameter, $amount)
{
    $payload = decodeFunctionExecutionParameterPayload($parameter);
    if (!is_array($payload)) {
        $payload = [];
    }

    if (!isset($payload["target"]) || trim(strval($payload["target"])) === "") {
        $payload["target"] = is_array($parameter) ? "" : trim(strval($parameter));
    }

    $payload["amount"] = intval($amount);

    return json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

function buildConfiguredActionParameterFromMetadata($functionCodeName, $parameter)
{
    if (!function_exists('herikaGetActionCatalogRow') || !function_exists('herikaActionCatalogResolveTemplateValue')) {
        return null;
    }

    $row = herikaGetActionCatalogRow($functionCodeName);
    if (!is_array($row)) {
        return null;
    }

    $metadata = herikaActionCatalogDecodeJson($row['metadata'] ?? [], []);
    $parameterTemplate = $metadata['parameter_template'] ?? null;
    if ($parameterTemplate === null || $parameterTemplate === '') {
        return null;
    }

    $parameterData = decodeFunctionExecutionParameterPayload($parameter);
    if (!is_array($parameterData)) {
        $parameterData = [];
    }

    $parameterTarget = strval($parameterData['target'] ?? (is_array($parameter) ? '' : trim(strval($parameter))));
    $context = [
        'action_name' => $functionCodeName,
        'parameter_raw' => is_array($parameter)
            ? json_encode($parameter, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            : strval($parameter),
        'parameter_target' => $parameterTarget,
        'parameters' => $parameterData,
        'config' => function_exists('herikaActionCatalogGetResolvedCustomConfig')
            ? herikaActionCatalogGetResolvedCustomConfig($functionCodeName, $row)
            : [],
    ];

    $resolved = herikaActionCatalogResolveTemplateValue($parameterTemplate, $context);
    if (is_array($resolved)) {
        return json_encode($resolved, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    if ($resolved === null) {
        return '';
    }

    return is_string($resolved)
        ? $resolved
        : json_encode($resolved, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

$rentRoomCost = getConfiguredRentRoomCost();
$hireCarriageCost = getConfiguredHireCarriageCost();
$hireFerryCost = getConfiguredHireFerryCost();
$rentRoomCostText = formatConfiguredActionGoldCost($rentRoomCost);
$hireCarriageCostText = formatConfiguredActionGoldCost($hireCarriageCost);
$hireFerryCostText = formatConfiguredActionGoldCost($hireFerryCost);

// We must use internal keys here.

$F_TRANSLATIONS_LOCAL["MoveTo"] = "Move to a visible building or visible actor, also used to guide {$GLOBALS["PLAYER_NAME"]} to a actor or building.";
$F_TRANSLATIONS_LOCAL["OpenInventory"] = "Initiates trading or exchange ITEMS with {$GLOBALS["PLAYER_NAME"]}.";
$F_TRANSLATIONS_LOCAL["OpenInventory2"] = "Initiates trading, {$GLOBALS["PLAYER_NAME"]} must give ITEMS to {$GLOBALS["HERIKA_NAME"]}";
$F_TRANSLATIONS_LOCAL["Attack"] = "Attack with intention to kill an Actor, NPC or entity.";
$F_TRANSLATIONS_LOCAL["Follow"] = "Move to and follow the specified target actor";
$F_TRANSLATIONS_LOCAL["CheckInventory"] = "Search in {$GLOBALS["HERIKA_NAME"]}'s inventory, backpack or pocket. List their inventory contents";
$F_TRANSLATIONS_LOCAL["SheatheWeapon"] = "Sheathes/put away current weapon";
$F_TRANSLATIONS_LOCAL["Relax"] = "Stop whatever you are doing and relax at the current location.Used to Unwind,Loosen Up,Enjoy Moment,Chill";
$F_TRANSLATIONS_LOCAL["TravelTo"] = "Use it to move to major locations and landmarks and POIs.";
$F_TRANSLATIONS_LOCAL["TakeASeat"] = "{$GLOBALS["HERIKA_NAME"]} take a seat at seating location nearby.";
$F_TRANSLATIONS_LOCAL["IncreaseWalkSpeed"] = "Increase {$GLOBALS["HERIKA_NAME"]} speed when moving or travelling";
$F_TRANSLATIONS_LOCAL["DecreaseWalkSpeed"] = "Decrease {$GLOBALS["HERIKA_NAME"]} speed when moving or travelling";
$F_TRANSLATIONS_LOCAL["StopWalk"] = "Stop all {$GLOBALS["HERIKA_NAME"]}'s actions inmediately";
$F_TRANSLATIONS_LOCAL["TravelTo"] = "Only use if {$GLOBALS["PLAYER_NAME"]} explicitly suggest it. Guide {$GLOBALS["PLAYER_NAME"]} to a Town o City. Also known as lead the way";
$F_TRANSLATIONS_LOCAL["WaitHere"] = "{$GLOBALS["HERIKA_NAME"]} waits and loiters at the current location";
$F_TRANSLATIONS_LOCAL["GiveItemToPlayer"] = "{$GLOBALS["HERIKA_NAME"]} gives item (property target) to {$GLOBALS["PLAYER_NAME"]} (property listener)";
$F_TRANSLATIONS_LOCAL["TakeGoldFromPlayer"] = "{$GLOBALS["HERIKA_NAME"]} takes amount (property target) of gold from {$GLOBALS["PLAYER_NAME"]}, once {$GLOBALS["PLAYER_NAME"]} is agree. infer amount from context.";
$F_TRANSLATIONS_LOCAL["RentRoom"] = "{$GLOBALS["HERIKA_NAME"]} rents a room to {$GLOBALS["PLAYER_NAME"]} for {$rentRoomCostText}. Only innkeepers can use this action and it only applies to {$GLOBALS["PLAYER_NAME"]}.";
$F_TRANSLATIONS_LOCAL["HireCarriage"] = "{$GLOBALS["HERIKA_NAME"]} accepts {$hireCarriageCostText} for carriage travel and transports {$GLOBALS["PLAYER_NAME"]} to the specified destination. Reply with one short acceptance line, do not ask follow-up questions, then end the conversation.";
$F_TRANSLATIONS_LOCAL["HireFerry"] = "{$GLOBALS["HERIKA_NAME"]} accepts {$hireFerryCostText} for ferry travel and transports {$GLOBALS["PLAYER_NAME"]} to the specified destination. Reply with one short acceptance line, do not ask follow-up questions, then end the conversation.";
$F_TRANSLATIONS_LOCAL["AddBounty"] = "{$GLOBALS["HERIKA_NAME"]} adds a crime bounty to {$GLOBALS["PLAYER_NAME"]} for a witnessed or reported crime. Guard-only action.";
$F_TRANSLATIONS_LOCAL["PayBounty"] = "{$GLOBALS["PLAYER_NAME"]} pays off their bounty to {$GLOBALS["HERIKA_NAME"]}. Stolen items are confiscated and the matter is resolved immediately. Guard-only action.";
$F_TRANSLATIONS_LOCAL["ArrestPlayer"] = "{$GLOBALS["HERIKA_NAME"]} attempts to arrest {$GLOBALS["PLAYER_NAME"]}. The player can submit or resist. Guard-only action for serious crimes or refusal to pay.";
$F_TRANSLATIONS_LOCAL["ForgiveCrime"] = "{$GLOBALS["HERIKA_NAME"]} forgives {$GLOBALS["PLAYER_NAME"]}'s crimes and clears their bounty. Guard-only action for persuasion, bribe, or thane status.";
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
$F_TRANSLATIONS_LOCAL["Consume"] = "{$GLOBALS["HERIKA_NAME"]} consumes a food, drink, or potion from inventory. Use the exact inventory item name in the target field.";
$F_TRANSLATIONS_LOCAL["StartRitualCeremony"] = "Participates in a ritual or ceremony, following its customs and practices.";
$F_TRANSLATIONS_LOCAL["EndRitualCeremony"] = "Concludes a ritual or ceremony, marking its completion.";
    
$F_TRANSLATIONS_LOCAL["Training"] = "Opens training menu to improve skills with a trainer.";
$F_TRANSLATIONS_LOCAL["EndConversation"] = "{$GLOBALS["HERIKA_NAME"]} ends the conversation and becomes unavailable to talk for a short time.";

$F_RETURNMESSAGES_LOCAL["MoveTo"] = "Walk to a visible building or visible actor, also used to guide {$GLOBALS["PLAYER_NAME"]} to a actor or building.";
$F_RETURNMESSAGES_LOCAL["OpenInventory"] = "Initiates trading or exchange items with {$GLOBALS["PLAYER_NAME"]}.";
$F_RETURNMESSAGES_LOCAL["OpenInventory2"] = "{$GLOBALS["PLAYER_NAME"]} give items to {$GLOBALS["HERIKA_NAME"]}. Accept gift.";
$F_RETURNMESSAGES_LOCAL["Attack"] = "{$GLOBALS["HERIKA_NAME"]} Attacks #TARGET# ";
$F_RETURNMESSAGES_LOCAL["Follow"] = "{$GLOBALS["HERIKA_NAME"]} follows #TARGET# ";
$F_RETURNMESSAGES_LOCAL["CheckInventory"] = "{$GLOBALS["HERIKA_NAME"]}'s INVENTORY:#RESULT#";
$F_RETURNMESSAGES_LOCAL["SheatheWeapon"] = "Sheathes/put away current weapon";
$F_RETURNMESSAGES_LOCAL["Relax"] = "{$GLOBALS["HERIKA_NAME"]} is relaxed. Time to enjoy life.";
$F_RETURNMESSAGES_LOCAL["LeadTheWayTo"] = "Only use if {$GLOBALS["PLAYER_NAME"]} explicitly orders it. Guide {$GLOBALS["PLAYER_NAME"]} to a Town o City. ";
$F_RETURNMESSAGES_LOCAL["TakeASeat"] = "{$GLOBALS["HERIKA_NAME"]} seats in nearby chair or furniture ";
$F_RETURNMESSAGES_LOCAL["IncreaseWalkSpeed"] = "Increases {$GLOBALS["HERIKA_NAME"]} speed/pace when moving or travelling";
$F_RETURNMESSAGES_LOCAL["DecreaseWalkSpeed"] = "Decreases {$GLOBALS["HERIKA_NAME"]} speed/pace when moving or travelling";
$F_RETURNMESSAGES_LOCAL["StopWalk"] = "Stop all {$GLOBALS["HERIKA_NAME"]}'s actions inmediately";
$F_RETURNMESSAGES_LOCAL["TravelTo"] = "{$GLOBALS["HERIKA_NAME"]} begins travelling to #TARGET#";
$F_RETURNMESSAGES_LOCAL["WaitHere"] = "{$GLOBALS["HERIKA_NAME"]} waits and stands at the place";
$F_RETURNMESSAGES_LOCAL["GiveItemToPlayer"] = "{$GLOBALS["HERIKA_NAME"]} gave #TARGET# to {$GLOBALS["PLAYER_NAME"]}.If this a transaction, maybe TakeGoldFromPlayer is needed.";
$F_RETURNMESSAGES_LOCAL["TakeGoldFromPlayer"] = "{$GLOBALS["PLAYER_NAME"]} gave #TARGET# coins to {$GLOBALS["HERIKA_NAME"]}. If this a transaction, maybe GiveItemToPlayer is needed.";
$F_RETURNMESSAGES_LOCAL["RentRoom"] = "{$GLOBALS["HERIKA_NAME"]} rented a room to {$GLOBALS["PLAYER_NAME"]} for {$rentRoomCostText}.";
$F_RETURNMESSAGES_LOCAL["HireCarriage"] = "{$GLOBALS["HERIKA_NAME"]} accepted the {$hireCarriageCostText} carriage fare to #TARGET# and ended the conversation.";
$F_RETURNMESSAGES_LOCAL["HireFerry"] = "{$GLOBALS["HERIKA_NAME"]} accepted the {$hireFerryCostText} ferry fare to #TARGET# and ended the conversation.";
$F_RETURNMESSAGES_LOCAL["AddBounty"] = "{$GLOBALS["HERIKA_NAME"]} added a bounty for #TARGET# to {$GLOBALS["PLAYER_NAME"]}.";
$F_RETURNMESSAGES_LOCAL["PayBounty"] = "{$GLOBALS["PLAYER_NAME"]} paid off their bounty to {$GLOBALS["HERIKA_NAME"]}, and stolen items were removed from inventory.";
$F_RETURNMESSAGES_LOCAL["ArrestPlayer"] = "{$GLOBALS["HERIKA_NAME"]} attempted to arrest {$GLOBALS["PLAYER_NAME"]}.";
$F_RETURNMESSAGES_LOCAL["ForgiveCrime"] = "{$GLOBALS["HERIKA_NAME"]} forgave {$GLOBALS["PLAYER_NAME"]}'s crimes and cleared their bounty.";
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
$F_RETURNMESSAGES_LOCAL["Consume"] = "{$GLOBALS["HERIKA_NAME"]} consumes an item from inventory.";
$F_RETURNMESSAGES_LOCAL["StartRitualCeremony"] = "{$GLOBALS["HERIKA_NAME"]} begins a ritual or ceremony, following its customs and practices.";
$F_RETURNMESSAGES_LOCAL["EndRitualCeremony"] = "{$GLOBALS["HERIKA_NAME"]} concludes a ritual or ceremony, marking its completion.";
$F_RETURNMESSAGES_LOCAL["Training"] = "{$GLOBALS["HERIKA_NAME"]} opens the training menu.";

// What is this?. We can translate functions or give them a custom name.
// This array will handle translations. Plugin must receive the codename always.

$F_NAMES_LOCAL["MoveTo"] = "MoveTo";
$F_NAMES_LOCAL["OpenInventory"] = "TradeItems";
$F_NAMES_LOCAL["OpenInventory2"] = "AcceptGift";
$F_NAMES_LOCAL["Attack"] = "Attack";
$F_NAMES_LOCAL["Follow"] = "Follow";
$F_NAMES_LOCAL["CheckInventory"] = "CheckInventory";
$F_NAMES_LOCAL["SheatheWeapon"] = "SheatheWeapon";
$F_NAMES_LOCAL["Relax"] = "Relax";
//$F_NAMES_LOCAL["LeadTheWayTo"]="LeadTheWayTo";
$F_NAMES_LOCAL["TakeASeat"] = "TakeASeat";
$F_NAMES_LOCAL["IncreaseWalkSpeed"] = "IncreaseWalkSpeed";
$F_NAMES_LOCAL["DecreaseWalkSpeed"] = "DecreaseWalkSpeed";
$F_NAMES_LOCAL["StopWalk"] = "StopWalk";
$F_NAMES_LOCAL["TravelTo"] = "TravelTo";
$F_NAMES_LOCAL["WaitHere"] = "WaitHere";
$F_NAMES_LOCAL["GiveItemToPlayer"] = "GiveItemToPlayer";
$F_NAMES_LOCAL["TakeGoldFromPlayer"] = "TakeGoldFrom{$GLOBALS["PLAYER_NAME"]}";
$F_NAMES_LOCAL["RentRoom"] = "RentRoom";
$F_NAMES_LOCAL["HireCarriage"] = "HireCarriage";
$F_NAMES_LOCAL["HireFerry"] = "HireFerry";
$F_NAMES_LOCAL["AddBounty"] = "AddBounty";
$F_NAMES_LOCAL["PayBounty"] = "PayBounty";
$F_NAMES_LOCAL["ArrestPlayer"] = "ArrestPlayer";
$F_NAMES_LOCAL["ForgiveCrime"] = "ForgiveCrime";
$F_NAMES_LOCAL["FollowPlayer"] = "FollowPlayer";
$F_NAMES_LOCAL["ComeCloser"] = "ComeCloser";
$F_NAMES_LOCAL["Brawl"] = "Brawl";
$F_NAMES_LOCAL["ReturnBackHome"] = "ReturnHome";
$F_NAMES_LOCAL["GiveGoldTo"] = "GiveGoldTo";
$F_NAMES_LOCAL["GiveItemTo"] = "GiveItemTo";
$F_NAMES_LOCAL["PickupItem"] = "PickupItem";
$F_NAMES_LOCAL["GoToSleep"] = "GoToSleep";
$F_NAMES_LOCAL["UseSoulGaze"] = "UseSoulGaze";
$F_NAMES_LOCAL["CastSpell"] = "CastSpell";
$F_NAMES_LOCAL["MakeFollower"] = "Join{$GLOBALS["PLAYER_NAME"]}Party";

$F_NAMES_LOCAL["Toast"] = "Toast";
$F_NAMES_LOCAL["Drink"] = "Drink";
$F_NAMES_LOCAL["Consume"] = "Consume";
$F_NAMES_LOCAL["StartRitualCeremony"] = "StartRitualCeremony";
$F_NAMES_LOCAL["EndRitualCeremony"] = "EndRitualCeremony";

$F_NAMES_LOCAL["Training"] = "Training";
$F_NAMES_LOCAL["EndConversation"] = "EndConversation";

if (function_exists('herikaNormalizeActionCatalogDisplayActionName')) {
    foreach ($F_NAMES_LOCAL as $functionCode => $functionName) {
        $F_NAMES_LOCAL[$functionCode] = herikaNormalizeActionCatalogDisplayActionName($functionName);
    }
}

if (isset($GLOBALS["CORE_LANG"])) {
    if (file_exists(__DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "lang" . DIRECTORY_SEPARATOR . $GLOBALS["CORE_LANG"] . DIRECTORY_SEPARATOR . "functions.php")) {
        require_once __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "lang" . DIRECTORY_SEPARATOR . $GLOBALS["CORE_LANG"] . DIRECTORY_SEPARATOR . "functions.php";
    }
}

$herikaRetiredActionCodes = [
    'AttackHunt',
    'Inspect',
    'InspectSurroundings',
    'LookAt',
    'Surrender',
    'ReadQuestJournal',
    'GetDateTime',
    'SearchDiary',
    'SetCurrentTask',
    'ReadDiaryPage',
    'SearchMemory',
];
$herikaRetiredActionNames = [];
foreach ($herikaRetiredActionCodes as $herikaRetiredActionCode) {
    if (isset($F_NAMES_LOCAL[$herikaRetiredActionCode])) {
        $herikaRetiredActionNames[] = $F_NAMES_LOCAL[$herikaRetiredActionCode];
    }
}

$GLOBALS["F_TRANSLATIONS"] = $F_TRANSLATIONS_LOCAL;
$GLOBALS["F_RETURNMESSAGES"] = $F_RETURNMESSAGES_LOCAL;
$GLOBALS["F_NAMES"] = $F_NAMES_LOCAL;
$GLOBALS["F_TRANSLATIONS_BASE"] = $F_TRANSLATIONS_LOCAL;
$GLOBALS["F_RETURNMESSAGES_BASE"] = $F_RETURNMESSAGES_LOCAL;

$hireCarriageDestinations = [
    "Whiterun",
    "Solitude",
    "Markarth",
    "Riften",
    "Windhelm",
    "Morthal",
    "Dawnstar",
    "Falkreath",
    "Winterhold",
    "Darkwater Crossing",
    "Dragon Bridge",
    "Ivarstead",
    "Karthwasten",
    "Kynesgrove",
    "Old Hroldan",
    "Riverwood",
    "Rorikstead",
    "Shor's Stone",
    "Stonehills",
];

$hireFerryDestinations = [
    "Windhelm",
    "Dawnstar",
    "Solitude",
    "Icewater Jetty",
    "Castle Volkihar",
    "Giant's Tooth",
];

$crimeTypes = ["Assault", "Murder", "Theft", "Pickpocketing", "Trespassing", "Jailbreak", "Custom"];

$GLOBALS["FUNCTIONS"] = [
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
        "name" => $F_NAMES_LOCAL["RentRoom"],
        "description" => $F_TRANSLATIONS_LOCAL["RentRoom"],
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
        "name" => $F_NAMES_LOCAL["HireCarriage"],
        "description" => $F_TRANSLATIONS_LOCAL["HireCarriage"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "target" => [
                    "type" => "string",
                    "description" => "Vanilla carriage destination for {$GLOBALS["PLAYER_NAME"]}",
                    "enum" => $hireCarriageDestinations,
                ],
            ],
            "required" => ["target"],
        ],
    ],
    [
        "name" => $F_NAMES_LOCAL["HireFerry"],
        "description" => $F_TRANSLATIONS_LOCAL["HireFerry"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "target" => [
                    "type" => "string",
                    "description" => "Vanilla ferry destination for {$GLOBALS["PLAYER_NAME"]}",
                    "enum" => $hireFerryDestinations,
                ],
            ],
            "required" => ["target"],
        ],
    ],
    [
        "name" => $F_NAMES_LOCAL["AddBounty"],
        "description" => $F_TRANSLATIONS_LOCAL["AddBounty"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "target" => [
                    "type" => "string",
                    "description" => "Crime type for the bounty",
                    "enum" => $crimeTypes,
                ],
                "item" => [
                    "type" => "string",
                    "description" => "Custom gold amount (only used when crime_type is Custom)",
                ],
            ],
            "required" => ["target"],
        ],
    ],
    [
        "name" => $F_NAMES_LOCAL["PayBounty"],
        "description" => $F_TRANSLATIONS_LOCAL["PayBounty"],
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
        "name" => $F_NAMES_LOCAL["ArrestPlayer"],
        "description" => $F_TRANSLATIONS_LOCAL["ArrestPlayer"],
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
        "name" => $F_NAMES_LOCAL["ForgiveCrime"],
        "description" => $F_TRANSLATIONS_LOCAL["ForgiveCrime"],
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
        "name" => $F_NAMES_LOCAL["Consume"],
        "description" => $F_TRANSLATIONS_LOCAL["Consume"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "target" => [
                    "type" => "string",
                    "description" => "REQUIRED: Exact name of the food, drink, or potion from <inventory> to consume.",
                ],
                "item" => [
                    "type" => "string",
                    "description" => "Optional fallback copy of the same inventory item name if target is empty.",
                ],
            ],
            "required" => ["target"],
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
    [
        "name" => $F_NAMES_LOCAL["EndConversation"],
        "description" => $F_TRANSLATIONS_LOCAL["EndConversation"],
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

foreach ($herikaRetiredActionCodes as $herikaRetiredActionCode) {
    unset($F_TRANSLATIONS_LOCAL[$herikaRetiredActionCode], $F_RETURNMESSAGES_LOCAL[$herikaRetiredActionCode], $F_NAMES_LOCAL[$herikaRetiredActionCode]);
}

$GLOBALS["F_TRANSLATIONS"] = $F_TRANSLATIONS_LOCAL;
$GLOBALS["F_RETURNMESSAGES"] = $F_RETURNMESSAGES_LOCAL;
$GLOBALS["F_NAMES"] = $F_NAMES_LOCAL;
$GLOBALS["FUNCTIONS"] = array_values(array_filter($GLOBALS["FUNCTIONS"], function ($functionEntry) use ($herikaRetiredActionNames) {
    return !in_array($functionEntry["name"] ?? "", $herikaRetiredActionNames, true);
}));

// Mantain a copy of all functions defined here
foreach ($GLOBALS["FUNCTIONS"] as $n => $functionEntry) {
    $GLOBALS["BASE_FUNCTIONS"][getFunctionCodeName($functionEntry["name"])] = $GLOBALS["FUNCTIONS"][$n];
}
$HERIKA_BASE_FUNCTIONS_LOCAL = $GLOBALS["BASE_FUNCTIONS"];

function getFunctionNameAliases()
{
    $playerName = strval($GLOBALS["PLAYER_NAME"] ?? "Player");

    $aliases = [
        'ExchangeItems' => 'OpenInventory',
        'ListInventory' => 'CheckInventory',
        'LetsRelax' => 'Relax',
        "TakeMoneyFrom{$playerName}" => 'TakeGoldFromPlayer',
        'Fight' => 'Brawl',
        'ReturnBackHome' => 'ReturnBackHome',
        "JoinTo{$playerName}Squad" => 'MakeFollower',
        'MakeAToast' => 'Toast',
    ];

    if (function_exists('herikaNormalizeActionCatalogDisplayActionName')) {
        foreach ($aliases as $legacyActionName => $codeName) {
            $normalizedLegacyActionName = herikaNormalizeActionCatalogDisplayActionName($legacyActionName);
            if ($normalizedLegacyActionName !== '' && !isset($aliases[$normalizedLegacyActionName])) {
                $aliases[$normalizedLegacyActionName] = $codeName;
            }
        }
    }

    return $aliases;
}

function getFunctionCodeName($key)
{
    if (!isset($GLOBALS["F_NAMES"]) || !is_array($GLOBALS["F_NAMES"])) {
        return false;
    }

    $key = strval($key);
    if (isset($GLOBALS["F_NAMES"][$key])) {
        return $key;
    }

    if (isset($GLOBALS["HERIKA_ACTION_NAME_PREFERRED_CODE"]) && is_array($GLOBALS["HERIKA_ACTION_NAME_PREFERRED_CODE"])) {
        $preferredCode = $GLOBALS["HERIKA_ACTION_NAME_PREFERRED_CODE"][$key] ?? false;
        if ($preferredCode !== false) {
            return $preferredCode;
        }
    }

    $keysToTry = [$key];
    if (function_exists('herikaNormalizeActionCatalogDisplayActionName')) {
        $normalizedKey = herikaNormalizeActionCatalogDisplayActionName($key);
        if ($normalizedKey !== '' && !in_array($normalizedKey, $keysToTry, true)) {
            $keysToTry[] = $normalizedKey;
        }
    }

    foreach ($keysToTry as $candidateKey) {
        if (isset($GLOBALS["HERIKA_ACTION_NAME_PREFERRED_CODE"]) && is_array($GLOBALS["HERIKA_ACTION_NAME_PREFERRED_CODE"])) {
            $preferredCode = $GLOBALS["HERIKA_ACTION_NAME_PREFERRED_CODE"][$candidateKey] ?? false;
            if ($preferredCode !== false) {
                return $preferredCode;
            }
        }

        $matchingCodes = [];
        foreach ($GLOBALS["F_NAMES"] as $functionCode => $functionName) {
            if ($functionName === $candidateKey) {
                $matchingCodes[] = $functionCode;
            }
        }

        if (count($matchingCodes) === 1) {
            return $matchingCodes[0];
        }

        if (count($matchingCodes) > 1) {
            foreach ($matchingCodes as $matchingCode) {
                if (function_exists('herikaGetActionCatalogRow')) {
                    $row = herikaGetActionCatalogRow($matchingCode);
                    if (is_array($row) && herikaActionCatalogRowIsAvailableInCurrentMode($row) && !empty(($row['metadata'] ?? [])['builtin']) === false) {
                        return $matchingCode;
                    }
                }
            }

            return $matchingCodes[0];
        }
    }

    $aliases = getFunctionNameAliases();
    return $aliases[$key] ?? false;
}

function herikaFormatReturnMessageTemplate($codeName, $primaryArgument = '', array $extraReplacements = [])
{
    $codeName = trim(strval($codeName));
    if ($codeName === '' || !isset($GLOBALS["F_RETURNMESSAGES"][$codeName])) {
        return '';
    }

    $template = strval($GLOBALS["F_RETURNMESSAGES"][$codeName] ?? '');
    if ($template === '') {
        return '';
    }

    if (is_scalar($primaryArgument) || $primaryArgument === null) {
        $primaryArgument = strval($primaryArgument ?? '');
    } else {
        $primaryArgument = '';
    }

    $replacements = [
        '#TARGET#' => $primaryArgument,
        '#HERIKA_NAME#' => strval($GLOBALS["HERIKA_NAME"] ?? 'NPC'),
        '#PLAYER_NAME#' => strval($GLOBALS["PLAYER_NAME"] ?? 'Player'),
    ];

    foreach ($extraReplacements as $key => $value) {
        $replacements[strval($key)] = is_scalar($value) || $value === null ? strval($value ?? '') : '';
    }

    return strtr($template, $replacements);
}

function herikaFormatActionPromptTemplate($template, array $extraReplacements = [])
{
    $template = strval($template);
    if ($template === '') {
        return '';
    }

    $replacements = [
        '#HERIKA_NAME#' => strval($GLOBALS["HERIKA_NAME"] ?? 'NPC'),
        '#PLAYER_NAME#' => strval($GLOBALS["PLAYER_NAME"] ?? 'Player'),
        '{$GLOBALS["HERIKA_NAME"]}' => strval($GLOBALS["HERIKA_NAME"] ?? 'NPC'),
        '{$GLOBALS["PLAYER_NAME"]}' => strval($GLOBALS["PLAYER_NAME"] ?? 'Player'),
    ];

    foreach ($extraReplacements as $key => $value) {
        $replacements[strval($key)] = is_scalar($value) || $value === null ? strval($value ?? '') : '';
    }

    $rendered = strtr($template, $replacements);

    // Some catalog/imported strings can still carry SQL-style doubled apostrophes.
    return str_replace("''", "'", $rendered);
}

function herikaGetPromptActionDescription($codeName, $fallbackDescription = '')
{
    $codeName = trim(strval($codeName));
    $description = '';

    if ($codeName !== '' && isset($GLOBALS["F_TRANSLATIONS"][$codeName])) {
        $description = strval($GLOBALS["F_TRANSLATIONS"][$codeName] ?? '');
    }

    if ($description === '') {
        $description = strval($fallbackDescription);
    }

    return herikaFormatActionPromptTemplate($description);
}

function getFunctionTrlName($key)
{
    if (isset($GLOBALS["F_NAMES"][$key]) && !empty($GLOBALS["F_NAMES"][$key])) {
        return $GLOBALS["F_NAMES"][$key];
    } else {
        return $key;
    }

}

function getSingleFunctionParameterValue($functionDef, $parsedResponse)
{
    if (!is_array($parsedResponse)) {
        return "";
    }

    $properties = $functionDef["parameters"]["properties"] ?? [];
    if (is_array($properties) && count($properties) === 0) {
        return "";
    }

    if (is_array($properties) && count($properties) === 1) {
        $parameterName = array_key_first($properties);
        if (is_string($parameterName) && array_key_exists($parameterName, $parsedResponse)) {
            return $parsedResponse[$parameterName];
        }
    }

    return $parsedResponse["target"] ?? "";
}

function normalizeFunctionParameterValueFromSchema($parameterSchema, $value)
{
    if (!is_array($parameterSchema)) {
        return $value;
    }

    $parameterType = strtolower(trim(strval($parameterSchema["type"] ?? "")));
    if ($parameterType === "integer" && is_numeric($value)) {
        return intval(round(floatval($value)));
    }

    if ($parameterType === "number" && is_numeric($value)) {
        return floatval($value);
    }

    if ($parameterType === "boolean") {
        if (is_bool($value)) {
            return $value;
        }

        $text = strtolower(trim(strval($value)));
        if (in_array($text, ["1", "true", "yes", "on", "t"], true)) {
            return true;
        }
        if (in_array($text, ["0", "false", "no", "off", "f"], true)) {
            return false;
        }
    }

    return $value;
}

function functionDefinitionHasRequiredParameters($functionDef)
{
    return is_array($functionDef) && count($functionDef["parameters"]["required"] ?? []) > 0;
}

function functionExecutionParameterValueIsEmpty($parameterValue)
{
    if (is_array($parameterValue)) {
        return count($parameterValue) === 0;
    }

    return trim(strval($parameterValue)) === "";
}

function buildFunctionParameterValueFromResponse($functionDef, $parsedResponse)
{
    $properties = $functionDef["parameters"]["properties"] ?? [];
    $requiredParameters = [];
    foreach (($functionDef["parameters"]["required"] ?? []) as $requiredParameter) {
        $requiredParameter = trim(strval($requiredParameter));
        if ($requiredParameter !== "") {
            $requiredParameters[] = $requiredParameter;
        }
    }

    $missingRequiredParameters = [];
    foreach ($requiredParameters as $requiredParameter) {
        if (!array_key_exists($requiredParameter, $parsedResponse) || $parsedResponse[$requiredParameter] === "" || $parsedResponse[$requiredParameter] === null) {
            $missingRequiredParameters[] = $requiredParameter;
        }
    }

    if (count($properties) > 1) {
        $parameters = [];
        foreach ($properties as $parameterName => $parameterSchema) {
            if (array_key_exists($parameterName, $parsedResponse)) {
                $parameters[$parameterName] = normalizeFunctionParameterValueFromSchema($parameterSchema, $parsedResponse[$parameterName]);
            }
        }

        return [
            "parameter_value" => $parameters,
            "missing_required" => $missingRequiredParameters,
        ];
    }

    return [
        "parameter_value" => getSingleFunctionParameterValue($functionDef, $parsedResponse),
        "missing_required" => $missingRequiredParameters,
    ];
}

function buildFunctionExecutionContextFromResponse($parsedResponse)
{
    $actionName = trim(strval($parsedResponse["action"] ?? ""));
    $functionDef = $actionName !== "" ? findFunctionByName($actionName) : null;
    $functionCodeName = $actionName;
    $parameterValue = $parsedResponse["target"] ?? "";
    $missingRequired = [];

    if (is_array($functionDef)) {
        $resolvedCodeName = getFunctionCodeName($actionName);
        if (is_string($resolvedCodeName) && $resolvedCodeName !== "") {
            $functionCodeName = $resolvedCodeName;
        }

        $parameterData = buildFunctionParameterValueFromResponse($functionDef, is_array($parsedResponse) ? $parsedResponse : []);
        $parameterValue = $parameterData["parameter_value"];
        $missingRequired = $parameterData["missing_required"];
    }

    return [
        "action_name" => $actionName,
        "function_def" => $functionDef,
        "function_found" => is_array($functionDef),
        "function_code_name" => $functionCodeName,
        "parameter_value" => $parameterValue,
        "parameter_string" => buildFunctionExecutionParameter($functionCodeName, $parameterValue),
        "missing_required" => $missingRequired,
        "has_required_parameters" => functionDefinitionHasRequiredParameters($functionDef),
        "parameter_is_empty" => functionExecutionParameterValueIsEmpty($parameterValue),
    ];
}

function queueFunctionExecutionCommand(&$commandBuffer, &$alreadySent, $executionContext, $connectorName, $actorName = null)
{
    $actionName = trim(strval($executionContext["action_name"] ?? ""));
    if ($actionName === "") {
        return false;
    }

    if (empty($executionContext["function_found"])) {
        if ($actionName !== "Talk") {
            Logger::warn("{$connectorName}: Function not found for {$actionName}");
        }
        return false;
    }

    $missingRequired = $executionContext["missing_required"] ?? [];
    if (count($missingRequired) > 0) {
        Logger::warn("{$connectorName}: Missing required parameter(s) for " . strval($executionContext["function_code_name"] ?? $actionName) . ": " . implode(", ", $missingRequired));
    }

    if (!empty($executionContext["has_required_parameters"]) && !empty($executionContext["parameter_is_empty"])) {
        return false;
    }

    $actorName = ($actorName !== null && trim(strval($actorName)) !== "") ? strval($actorName) : strval($GLOBALS["HERIKA_NAME"] ?? "Herika");
    $commandStr = $actorName . "|command|" . strval($executionContext["function_code_name"] ?? "") . "@" . strval($executionContext["parameter_string"] ?? "") . "\r\n";
    $commandHash = md5($commandStr);

    if (isset($alreadySent[$commandHash])) {
        return false;
    }

    $commandBuffer[] = $commandStr;
    $alreadySent[$commandHash] = $commandStr;
    return true;
}

function chimActionShouldSuppressImmediateMessage($actionName)
{
    $actionName = trim(strval($actionName));
    if ($actionName === '') {
        return false;
    }

    return getFunctionCodeName($actionName) === 'Consume';
}


function buildFunctionExecutionParameter($functionCodeName, $parameter)
{
    $functionCodeName = trim(strval($functionCodeName));

    $configuredPayload = buildConfiguredActionParameterFromMetadata($functionCodeName, $parameter);
    if ($configuredPayload !== null) {
        return $configuredPayload;
    }

    if (is_array($parameter)) {
        return json_encode($parameter, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    return strval($parameter);
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

$seedActionRows = herikaBuildActionCatalogSeedRows(
    $F_NAMES_LOCAL ?? [],
    $F_TRANSLATIONS_LOCAL ?? [],
    $F_RETURNMESSAGES_LOCAL ?? [],
    [],
    $ENABLED_FUNCTIONS_LOCAL,
    herikaBuildActionCatalogFunctionDefinitionsByCode($HERIKA_BASE_FUNCTIONS_LOCAL ?? [])
);
if (herikaActionCatalogDbReady()) {
    herikaSyncActionCatalogBaseRows($seedActionRows);
    herikaImportLegacyActionPreferences($seedActionRows);
}

$isNpcMode = isset($GLOBALS["IS_NPC"]) && $GLOBALS["IS_NPC"];
$defaultEnabledFunctions = $isNpcMode ? herikaGetNpcDefaultActionCodes() : herikaGetFollowerDefaultActionCodes();
$dbEnabledFunctions = herikaLoadEnabledActionCodesForMode($isNpcMode, true);
$GLOBALS["ENABLED_FUNCTIONS"] = herikaActionCatalogDbReady()
    ? $dbEnabledFunctions
    : $defaultEnabledFunctions;

$folderPath = __DIR__ . DIRECTORY_SEPARATOR . "../ext/";
requireFunctionFilesRecursively($folderPath);

if (herikaActionCatalogDbReady()) {
    // Do not re-seed core_action from the live runtime list here.
    // Runtime functions may already include DB-backed custom actions that
    // intentionally share an action_name with shipped actions (for example
    // CHIM-Custom NFF wrappers like WaitHere / FollowMe / BehindMe). If we
    // write back from the runtime list, those custom rows can be mistaken for
    // built-in functions and get rewritten as source=function.php rows.
    herikaActionCatalogApplyRowsToRuntimeFunctions();
}

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
require_once __DIR__ . "/../lib/core/activity_status.php";

// action_post_process_fnct_ex is an arrya containing functions that process the actions after they are generated by the LLM
// more working examples in data_functions.php
$GLOBALS["action_post_process_fnct_ex"][]=function($actions) {
    
    global $gameRequest;

    $actionsCopy=$actions;
    foreach ($actions as $n=>$action) {
        
        $actionParts=explode("|",$action);
        $actionParts2=explode("@",$actionParts[2]);
        
        if (isset($actionParts2[0])) {
            if (herikaActionCatalogExecuteScriptProxyAction($action)) {
                unset($actionsCopy[$n]);
                continue;
            }

            // Parameter part 
            if ($actionParts2[0]=="Drink") {
               
                // Make NPC to toast
                $npcMaster = new Npcmaster();
                $npcData   = $npcMaster->getByName($actionParts[0]);

                $metadata=$npcMaster->getMetadata($npcData);

                $activityStatus = chimNormalizeActivityStatus($metadata);
                if ((!empty($metadata["furniture"]) && $metadata["furniture"]=="Chair") ||
                    (!empty($activityStatus["use_type"]) && $activityStatus["use_type"] === "chair")) {
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
                $timeToWait= ceil($totalChars / 12); // 1 second per 12 chars
                
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

                error_log("[ACTION POSTFILTER Train] Executed server-side");
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
                        
                    } else {

                        $skyrimCmd = new SkyrimCommandBuilder();
                        $json      = $skyrimCmd->Actor->PlayIdle("0x{$npcData["refid"]}", $defAnim);
                        $skyrimCmd->send(cmd: $json);
                        $json = $skyrimCmd->EffectShader->Play($shader, "0x{$npcData["refid"]}", 20);
                        $skyrimCmd->send(cmd: $json);
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

                chimApplyNpcMetadataUpdatesByName($actionParts[0], [
                    'ritual_state' => [
                        'active' => true,
                        'type' => strval($actionParts2[1] ?? ''),
                        'started_at' => time(),
                        'gamets' => $gameRequest[2],
                    ],
                    'activity_status' => [
                        'current_action' => 'ritual',
                        'current_use' => strval($actionParts2[1] ?? ''),
                        'use_type' => 'ritual',
                        'timestamp' => (int) round(microtime(true) * 1000),
                        'gamets' => $gameRequest[2],
                    ],
                ]);

                error_log("[ACTION POSTFILTER StartRitualCeremony] Executed server-side");

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

                chimApplyNpcMetadataUpdatesByName($actionParts[0], [
                    'ritual_state' => null,
                    'activity_status' => [
                        'current_action' => 'idle',
                        'current_use' => '',
                        'use_type' => '',
                        'furniture_name' => '',
                        'timestamp' => (int) round(microtime(true) * 1000),
                        'gamets' => $gameRequest[2],
                    ],
                ]);

                error_log("[ACTION POSTFILTER Toast] Executed server-side");

            }
        }
    }

    return $actionsCopy;
};
