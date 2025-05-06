<?php

// Override some descriptions when in instruction mode

// We must use internal keys here.

$GLOBALS["F_TRANSLATIONS"]["Inspect"]="Inspects a target character's OUTFIT and GEAR. JUST REPLY in a format similar to 'Let me see...' and then wait";
$GLOBALS["F_TRANSLATIONS"]["LookAt"]="LOOK at or INSPECT an NPC, Actor, or being OUTFIT and GEAR";
$GLOBALS["F_TRANSLATIONS"]["InspectSurroundings"]="Looks for entites, friendly or hostile, nearby";
$GLOBALS["F_TRANSLATIONS"]["MoveTo"]= "Move to a visible building or visible actor, also used to guide {$GLOBALS["PLAYER_NAME"]} to a actor or building.";
$GLOBALS["F_TRANSLATIONS"]["OpenInventory"]="Initiates trading or exchange items with {$GLOBALS["PLAYER_NAME"]}.";
$GLOBALS["F_TRANSLATIONS"]["OpenInventory2"]="Initiates trading, {$GLOBALS["PLAYER_NAME"]} must give items to {$GLOBALS["HERIKA_NAME"]}";
$GLOBALS["F_TRANSLATIONS"]["Attack"]="Attack with intetnion to kill an Actor, NPC or entity.";
$GLOBALS["F_TRANSLATIONS"]["AttackHunt"]="Hunt with intetion to kill an Actor, NPC or entity.";
$GLOBALS["F_TRANSLATIONS"]["Follow"]="Move to and follow a NPC, an actor or being";
$GLOBALS["F_TRANSLATIONS"]["CheckInventory"]="Search in {$GLOBALS["HERIKA_NAME"]}'s inventory, backpack or pocket. List their inventory contents";
$GLOBALS["F_TRANSLATIONS"]["SheatheWeapon"]="Sheates current weapon";
$GLOBALS["F_TRANSLATIONS"]["Relax"]="Stop whatever you are doing and relax at the current location";
$GLOBALS["F_TRANSLATIONS"]["TravelTo"]="Long distance travel command. Use it to move to major locations and landmarks.";
$GLOBALS["F_TRANSLATIONS"]["TakeASeat"]="{$GLOBALS["HERIKA_NAME"]} take a seat at seating location nearby.";
$GLOBALS["F_TRANSLATIONS"]["ReadQuestJournal"]="Only use if {$GLOBALS["PLAYER_NAME"]} explicitly ask for a quest. Get info about current quests";
$GLOBALS["F_TRANSLATIONS"]["IncreaseWalkSpeed"]="Increase {$GLOBALS["HERIKA_NAME"]} speed when moving or travelling";
$GLOBALS["F_TRANSLATIONS"]["DecreaseWalkSpeed"]="Decrease {$GLOBALS["HERIKA_NAME"]} speed when moving or travelling";
$GLOBALS["F_TRANSLATIONS"]["GetDateTime"]="Get Current Date and Time";
$GLOBALS["F_TRANSLATIONS"]["SearchDiary"]="Read {$GLOBALS["HERIKA_NAME"]}'s diary to make her remember something. Search in diary index";
$GLOBALS["F_TRANSLATIONS"]["SetCurrentTask"]="Set the current plan of action or task or quest";
$GLOBALS["F_TRANSLATIONS"]["ReadDiaryPage"]="Read {$GLOBALS["HERIKA_NAME"]}'s diary to access a specific topic";
$GLOBALS["F_TRANSLATIONS"]["StopWalk"]="Stop all {$GLOBALS["HERIKA_NAME"]}'s actions inmediately";
$GLOBALS["F_TRANSLATIONS"]["TravelTo"]="Starts travelling to a location/city";
$GLOBALS["F_TRANSLATIONS"]["SearchMemory"]="{$GLOBALS["HERIKA_NAME"]} tries to remember information. REPLY with hashtags";
$GLOBALS["F_TRANSLATIONS"]["WaitHere"]="{$GLOBALS["HERIKA_NAME"]} waits and loiters at the current location";
$GLOBALS["F_TRANSLATIONS"]["GiveItemToPlayer"]="{$GLOBALS["HERIKA_NAME"]} gives item (property target) to {$GLOBALS["PLAYER_NAME"]} (property listener)";
$GLOBALS["F_TRANSLATIONS"]["TakeGoldFromPlayer"]="{$GLOBALS["HERIKA_NAME"]} takes amount (property target) of gold from {$GLOBALS["PLAYER_NAME"]} (property listener)";
$GLOBALS["F_TRANSLATIONS"]["FollowPlayer"]="{$GLOBALS["HERIKA_NAME"]} follows  {$GLOBALS["PLAYER_NAME"]}";
$GLOBALS["F_TRANSLATIONS"]["ComeCloser"]="{$GLOBALS["HERIKA_NAME"]} aproaches to {$GLOBALS["PLAYER_NAME"]}";



$GLOBALS["F_RETURNMESSAGES"]["Inspect"]="{$GLOBALS["HERIKA_NAME"]} inspects #TARGET# and see this: #RESULT#";
$GLOBALS["F_RETURNMESSAGES"]["LookAt"]="LOOK at or Inspects NPC, Actor, or being OUTFIT and GEAR";
$GLOBALS["F_RETURNMESSAGES"]["InspectSurroundings"]="{$GLOBALS["HERIKA_NAME"]} takes a look around and see this: #RESULT#";
$GLOBALS["F_RETURNMESSAGES"]["MoveTo"]= "Walk to a visible building or visible actor, also used to guide {$GLOBALS["PLAYER_NAME"]} to a actor or building.";
$GLOBALS["F_RETURNMESSAGES"]["OpenInventory"]="Initiates trading or exchange items with {$GLOBALS["PLAYER_NAME"]}. Accept gift.";
$GLOBALS["F_RETURNMESSAGES"]["OpenInventory2"]="{$GLOBALS["PLAYER_NAME"]} give items to {$GLOBALS["HERIKA_NAME"]}";
$GLOBALS["F_RETURNMESSAGES"]["Attack"]="{$GLOBALS["HERIKA_NAME"]} Attacks #TARGET# ";
$GLOBALS["F_RETURNMESSAGES"]["AttackHunt"]="{$GLOBALS["HERIKA_NAME"]} Attacks #TARGET# ";
$GLOBALS["F_RETURNMESSAGES"]["Follow"]="Moves to and follow a NPC, an actor or being";
$GLOBALS["F_RETURNMESSAGES"]["CheckInventory"]="{$GLOBALS["HERIKA_NAME"]}'s INVENTORY:#RESULT#";
$GLOBALS["F_RETURNMESSAGES"]["SheatheWeapon"]="Sheates current weapon";
$GLOBALS["F_RETURNMESSAGES"]["Relax"]="{$GLOBALS["HERIKA_NAME"]} is relaxed. Time to enjoy life.";
$GLOBALS["F_RETURNMESSAGES"]["LeadTheWayTo"]="Only use if {$GLOBALS["PLAYER_NAME"]} explicitly orders it. Guide {$GLOBALS["PLAYER_NAME"]} to a Town o City. ";
$GLOBALS["F_RETURNMESSAGES"]["TakeASeat"]="{$GLOBALS["HERIKA_NAME"]} seats in nearby chair or furniture ";
$GLOBALS["F_RETURNMESSAGES"]["ReadQuestJournal"]="";
$GLOBALS["F_RETURNMESSAGES"]["IncreaseWalkSpeed"]="Increases {$GLOBALS["HERIKA_NAME"]} speed/pace when moving or travelling";
$GLOBALS["F_RETURNMESSAGES"]["DecreaseWalkSpeed"]="Decreases {$GLOBALS["HERIKA_NAME"]} speed/pace when moving or travelling";
$GLOBALS["F_RETURNMESSAGES"]["GetDateTime"]="Get Current Date and Time";
$GLOBALS["F_RETURNMESSAGES"]["SearchDiary"]="Read {$GLOBALS["HERIKA_NAME"]}'s diary to make her remember something. Search in diary index";
$GLOBALS["F_RETURNMESSAGES"]["SetCurrentTask"]="Set the current plan of action or task or quest";
$GLOBALS["F_RETURNMESSAGES"]["ReadDiaryPage"]="Read {$GLOBALS["HERIKA_NAME"]}'s diary to access a specific topic";
$GLOBALS["F_RETURNMESSAGES"]["StopWalk"]="Stop all {$GLOBALS["HERIKA_NAME"]}'s actions inmediately";
$GLOBALS["F_RETURNMESSAGES"]["TravelTo"]="{$GLOBALS["HERIKA_NAME"]} begins travelling to #TARGET#";
$GLOBALS["F_RETURNMESSAGES"]["SearchMemory"]="{$GLOBALS["HERIKA_NAME"]} tries to remember information. JUST REPLY something like 'Let me think' and wait";
$GLOBALS["F_RETURNMESSAGES"]["WaitHere"]="{$GLOBALS["HERIKA_NAME"]} waits and stands at the place";
$GLOBALS["F_RETURNMESSAGES"]["GiveItemToPlayer"]="{$GLOBALS["HERIKA_NAME"]} gave #TARGET# to {$GLOBALS["PLAYER_NAME"]}.If this a transaction, maybe TakeGoldFromPlayer is needed.";
$GLOBALS["F_RETURNMESSAGES"]["TakeGoldFromPlayer"]="{$GLOBALS["PLAYER_NAME"]} gave #TARGET# coins to {$GLOBALS["HERIKA_NAME"]}. If this a transaction, maybe GiveItemToPlayer is needed.";
$GLOBALS["F_RETURNMESSAGES"]["FollowPlayer"]="{$GLOBALS["HERIKA_NAME"]} follows {$GLOBALS["PLAYER_NAME"]}";
$GLOBALS["F_RETURNMESSAGES"]["ComeCloser"]="{$GLOBALS["HERIKA_NAME"]} aproaches {$GLOBALS["PLAYER_NAME"]}";


// What is this?. We can translate functions or give them a custom name. 
// This array will handle translations. Plugin must receive the codename always.

$GLOBALS["F_NAMES"]["Inspect"]="Inspect";
$GLOBALS["F_NAMES"]["LookAt"]="LookAt";
$GLOBALS["F_NAMES"]["InspectSurroundings"]="InspectSurroundings";
$GLOBALS["F_NAMES"]["MoveTo"]= "MoveTo";
$GLOBALS["F_NAMES"]["OpenInventory"]="ExchangeItems";
$GLOBALS["F_NAMES"]["OpenInventory2"]="TakeItemsFromPlayer";
$GLOBALS["F_NAMES"]["Attack"]="Attack";
$GLOBALS["F_NAMES"]["AttackHunt"]="Hunt";
$GLOBALS["F_NAMES"]["Follow"]="Follow";
$GLOBALS["F_NAMES"]["CheckInventory"]="ListInventory";
$GLOBALS["F_NAMES"]["SheatheWeapon"]="SheatheWeapon";
$GLOBALS["F_NAMES"]["Relax"]="LetsRelax";
//$GLOBALS["F_NAMES"]["LeadTheWayTo"]="LeadTheWayTo";
$GLOBALS["F_NAMES"]["TakeASeat"]="TakeASeat";
$GLOBALS["F_NAMES"]["ReadQuestJournal"]="ReadQuestJournal";
$GLOBALS["F_NAMES"]["IncreaseWalkSpeed"]="IncreaseWalkSpeed";
$GLOBALS["F_NAMES"]["DecreaseWalkSpeed"]="DecreaseWalkSpeed";
$GLOBALS["F_NAMES"]["GetDateTime"]="GetDateTime";
$GLOBALS["F_NAMES"]["SearchDiary"]="SearchDiary";
$GLOBALS["F_NAMES"]["SetCurrentTask"]="SetCurrentTask";
$GLOBALS["F_NAMES"]["ReadDiaryPage"]="ReadDiaryPage";
$GLOBALS["F_NAMES"]["StopWalk"]="StopWalk";
$GLOBALS["F_NAMES"]["TravelTo"]="LeadTheWayTo";
$GLOBALS["F_NAMES"]["SearchMemory"]="TryToRemember";
$GLOBALS["F_NAMES"]["WaitHere"]="WaitHere";
$GLOBALS["F_NAMES"]["GiveItemToPlayer"]="GiveItemToPlayer";
$GLOBALS["F_NAMES"]["TakeGoldFromPlayer"]="TakeGoldFromPlayer";
$GLOBALS["F_NAMES"]["FollowPlayer"]="FollowPlayer";
$GLOBALS["F_NAMES"]["ComeCloser"]="ComeCloser";



?>
