<?php 

require_once(__DIR__."/../lib/api_doc.php");
require_once(__DIR__."/../lib/api.php");
require_once(__DIR__."/../lib/snqe.class.php");

error_log("===================================================================================== RUN ");

$latsRid=$GLOBALS["db"]->fetchAll("select *  from eventlog order by rowid desc LIMIT 1 OFFSET 0");
$res=$GLOBALS["db"]->fetchAll("select max(gamets)+1 as gamets,max(ts)+1 as ts,max(localts)+1 as localts from eventlog where rowid={$latsRid[0]["rowid"]}");
$res2=$GLOBALS["db"]->fetchAll("select max(localts) as localts from responselog");
$GLOBALS["gameRequest"][1]=$res[0]["ts"]+0;
$GLOBALS["gameRequest"][2]=$res[0]["gamets"]+0;
$GLOBALS["gameRequest"][0]="";
$GLOBALS["last_localts"]=$res[0]["localts"]+0;
$GLOBALS["last_gamets"]=$res[0]["gamets"]+0;
$GLOBALS["last_instruction_sent"]=$res2[0]["localts"]+0;
$GLOBALS["actors_present"]=DataBeingsInCloseRange(true);

$lastChat=$GLOBALS["db"]->fetchAll("select max(localts) as m from eventlog where type IN ('chat','prechat','rechat')");
$lastEvent=$GLOBALS["db"]->fetchAll("select max(localts) as n from eventlog ");
if (($lastEvent[0]["n"]-$lastChat[0]["m"])>20) {  // 20 seconds of silence
    if (($GLOBALS["last_localts"]-$GLOBALS["last_instruction_sent"])>30) {
        $GLOBALS["NPCS_ARE_NOT_TALKING"]=1;
        error_log("[MAIN] NPCS_ARE_NOT_TALKING");
    } else {
        $GLOBALS["NPCS_ARE_NOT_TALKING"]=0;
        error_log("[MAIN] NPCS_ARE_NOT_TALKING, but instruction was sent ".($GLOBALS["last_localts"]-$GLOBALS["last_instruction_sent"])." secs ago");
    }

} else {
    $GLOBALS["NPCS_ARE_NOT_TALKING"]=0;
}





$quest_id = 'skooma_seekers';

// Load quest data $quest["quest_data"] will have the quest state as JSON object.

$quest=SNQEQuestManager::getQuest($quest_id);
if (!$quest) {
    SNQEQuestManager::createNewQuest($quest_id,'');
    $quest=SNQEQuestManager::getQuest($quest_id);
} else {
    if ($quest["quest_run_state"]=="finished")  {
        error_log("Quest ended".PHP_EOL);
        return;
    }

}

if (isset($quest["quest_data"]["lastgamets"]) && $quest["quest_data"]["lastgamets"]>=$GLOBALS["last_gamets"]) {
    error_log("[GAME PAUSED?] last: {$GLOBALS["last_gamets"]} quest last:{$quest["quest_data"]["lastgamets"]}");
    return;
} else {
    if (isset($quest["quest_data"]["lastgamets"]))
        error_log("[GAME PAUSED?] last: {$GLOBALS["last_gamets"]} quest last:{$quest["quest_data"]["lastgamets"]}");
    else
        error_log("[GAME PAUSED?] last: {$GLOBALS["last_gamets"]} ");

}

// QUEST CODE
// NPC references
$npc1_ref = 'friend_joren';
$npc2_ref = 'friend_savos';

// 1. Declare the two NPCs
CreateNPC(
    $quest_id,
    $npc1_ref,
    'Joren',
    'Male',
    'beggar',
    'Nord',
    'Whiterun',
    'Ragged clothes, tired eyes',
    'Once a merchant, now fallen on hard times.',
    'casual',
    'sad'
);

CreateNPC(
    $quest_id,
    $npc2_ref,
    'Savos',
    'Male',
    'beggar',
    'Imperial',
    'Whiterun',
    'Dirty tunic, hopeful smile',
    'Joren\'s loyal friend, always optimistic.',
    'casual',
    'cheerful'
);

// 2. Declare the skooma item
$item_ref = 'skooma_bottle';
CreateItem(
    $quest_id,
    $item_ref,
    'Skooma',
    'potion',
    'nearby',
    'A bottle of illegal skooma.'
);

// 3. Declare a topic for Joren to ask the player for skooma
CreateTopic(
    $quest_id,
    't_ask_skooma',
    'Ask Player for Skooma',
    'Item',
    $item_ref,
    $npc1_ref,
    'Hey friend, could you spare some skooma for us? It\'s been a rough week.',
    'player'
);

// 3b. Declare a topic for Joren to insult the player if refused
CreateTopic(
    $quest_id,
    't_insult',
    'Joren Angry Insult to player',
    'Lore',
    null,
    $npc1_ref,
    'Fine, be that way! Some friend you are. Come on, Savos, let\'s go.',
    'player'
);

// 4. Spawn both NPCs
SpawnNPC($quest_id, $npc1_ref, 'nearby');
if (CheckNPCSpawn($quest_id, $npc1_ref)!=='done') return;

SpawnNPC($quest_id, $npc2_ref, 'nearby');
if (CheckNPCSpawn($quest_id, $npc2_ref)!=='done') return;

// 6. Joren asks the player for skooma
$tell_result = TellTopicToPlayer($quest_id, $npc1_ref, 't_ask_skooma');
if ($tell_result!=='done') return;
if (CheckTopicToPlayer($quest_id, 't_ask_skooma')!=='done') return;

// 7. Wait for the player to give the skooma to Joren, with timeout
$trade_result = WaitToItemBeTraded($quest_id, $item_ref, $npc1_ref, 10);
if ($trade_result === 'done') {
    // Player gave skooma, friends leave happy
    ToGoAway($quest_id, $npc1_ref);
    ToGoAway($quest_id, $npc2_ref);
    CompleteQuest($quest_id, 'Joren and Savos received their skooma and left.');
    return;
} elseif ($trade_result === 'failed') {
    // Player did not give skooma, Joren insults, then both leave
    $insult_result = TellTopicToPlayer($quest_id, $npc1_ref, 't_insult');
    if ($insult_result !== 'done') return;
    if (CheckTopicToPlayer($quest_id, 't_insult') !== 'done') return;
    ToGoAway($quest_id, $npc1_ref);
    ToGoAway($quest_id, $npc2_ref);
    CompleteQuest($quest_id, 'Joren and Savos left angry after being refused skooma.');
    return;
} else {
    // Still waiting
    return;
}

?>