<?php 

require_once(__DIR__."/../lib/api_doc.php");
require_once(__DIR__."/../lib/api.php");
require_once(__DIR__."/../lib/snqe.class.php");


$latsRid=$GLOBALS["db"]->fetchAll("select *  from eventlog order by rowid desc LIMIT 1 OFFSET 0");
$res=$GLOBALS["db"]->fetchAll("select max(gamets)+1 as gamets,max(ts)+1 as ts,max(localts)+1 as localts from eventlog where rowid={$latsRid[0]["rowid"]}");
$GLOBALS["gameRequest"][1]=$res[0]["ts"]+0;
$GLOBALS["gameRequest"][2]=$res[0]["gamets"]+0;
$GLOBALS["gameRequest"][0]="";
$GLOBALS["last_localts"]=$res[0]["localts"]+0;
$GLOBALS["last_gamets"]=$res[0]["gamets"]+0;
$GLOBALS["actors_present"]=DataBeingsInCloseRange(true);


$quest_id = "find_lost_tome";

// Load quest data $quest["quest_data"] will have the quest state as JSON object.

$quest=SNQEQuestManager::getQuest($quest_id);

if (isset($quest["quest_data"]["lastgamets"]) && $quest["quest_data"]["lastgamets"]>=$GLOBALS["last_gamets"]) {
    error_log("[GAME PAUSED?] last: {$GLOBALS["last_gamets"]} quest last:{$quest["quest_data"]["lastgamets"]}");
    return;
} else {
    if (isset($quest["quest_data"]["lastgamets"]))
        error_log("[GAME PAUSED?] last: {$GLOBALS["last_gamets"]} quest last:{$quest["quest_data"]["lastgamets"]}");
    else
        error_log("[GAME PAUSED?] last: {$GLOBALS["last_gamets"]} ");

}
// Quest: Find the Lost Tome

$npc_ref = "wizard_ulfric";
$item_ref = "ancient_tome";


// 1. Declare the wizard NPC
CreateNPC(
    $quest_id,
    $npc_ref,
    "Ulfric the Wise",
    "Male",
    "mage",
    "Breton",
    "Raven Rock",
    "Long white beard, blue robes, staff",
    "A reclusive scholar seeking lost knowledge.",
    "formal",
    "serious"
);


// 2. Declare the book item
CreateItem(
    $quest_id,
    $item_ref,
    "Ancient Ring",
    "ring",
    "nearby",
    "A ring tome filled with arcane secrets."
);


// 3. Declare a topic for the wizard to ask for the book
CreateTopic(
    $quest_id,
    "t_ask_ring",
    "Seeking the Ancient Ring",
    "Item",
    $item_ref,
    $npc_ref,
    "I require the Ancient Ring for my research. Can you find it for me?",
    "player"
);




// 4. Spawn the wizard in Winterhold
SpawnNPC($quest_id, $npc_ref, "Raven Rock");
CheckNPCSpawn($quest_id, $npc_ref, "Raven Rock") or die("CheckNPCSpawn");


// 5. Spawn the book somewhere nearby
SpawnItem($quest_id, $item_ref, "nearby");
if (CheckItemSpawn($quest_id, $item_ref)!="done") {
    die("Item not spawned <SpawnItem($quest_id, $item_ref>".PHP_EOL);
}



// 6. Wizard tells the player about the quest
if (TellTopicToPlayer($quest_id, $npc_ref, "t_ask_ring") !="done") {
    die("TellTopicToPlayer failed, will retry".PHP_EOL);
}

if (CheckTopicToPlayer($quest_id, "t_ask_ring") !="done") {
    die("Topic not covered ".PHP_EOL);
}

// 7. Wait for the player to recover the book
if (WaitToItemBeRecovered($quest_id, $item_ref)  != "done") {
    die("Item not recovered ".PHP_EOL);
}

// 8. Wait for the player to give the book to the wizard
if (WaitToItemBeTraded($quest_id, $item_ref, $npc_ref) != "done") {
    die("Item not traded ".PHP_EOL);
}

// 9. (Optional) Wizard thanks the player
CreateTopic(
    $quest_id,
    "t_thanks",
    "Thank You",
    "Lore",
    null,
    $npc_ref,
    "Thank you for retrieving the ring. Your help is invaluable.",
    "player"
);

TellTopicToPlayer($quest_id, $npc_ref, "t_thanks");
if (CheckTopicToPlayer($quest_id, "t_thanks") !="done") {
    die("Topic not covered ".PHP_EOL);
}
// 10. Remove the wizard (optional, quest end)
ToGoAway($quest_id, $npc_ref);

?>