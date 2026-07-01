<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

$enginePath = dirname(__FILE__) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR;
$GLOBALS['ENGINE_PATH'] = $enginePath;

// ─── Includes ─────────────────────────────────────────────────────────────────

require_once $enginePath . 'lib/runtime_bootstrap.php';
chimRuntimeBootstrap($enginePath, [
    'load_general_settings' => true,
    'load_player_name' => true,
    'load_narrator' => true,
]);

require_once $enginePath . 'lib/model_dynmodel.php';
require_once $enginePath . 'lib/chat_helper_functions.php';
require_once $enginePath . 'lib/data_functions.php';
require_once $enginePath . 'lib/logger.php';
require_once $enginePath . 'lib/utils_game_timestamp.php';
require_once $enginePath . 'lib/rolemaster_helpers.php';
require_once $enginePath . 'lib/scriptproxy_papyrus.php';
require_once $enginePath . 'lib/core/player.class.php';
require_once $enginePath . 'lib/core/npc_master.class.php';
require_once $enginePath . 'lib/core/api_badge.class.php';
require_once $enginePath . 'lib/core/core_profiles.class.php';
require_once $enginePath . 'lib/core/llm_connector.class.php';
require_once $enginePath . 'lib/core/tts_connector.class.php';
require_once $enginePath . 'lib/lazy_xml.php';
require_once $enginePath . 'debug/background_action_handler.php';

require_once $enginePath . "lib/scriptproxy_papyrus.php";
require_once $enginePath . "lib/core/activity_status.php";

// ─── Database ─────────────────────────────────────────────────────────────────

$db = $GLOBALS["db"];

error_log("[DEBUG] Starting insert_responselog.php with args: " . json_encode($argv)) . PHP_EOL;

// Send a NPC to Bannered Mare
if ($argv[1] == "0") {
    $GLOBALS["db"]->insert(
        'responselog',
        [
            'localts' => time(),
            'sent' => 0,
            'actor' => "rolemaster",
            'text' => "",
            'action' => "rolecommand|BackgroundCmd@0xff0010d8@TravelTo/100950",
            'tag' => __FILE__ . ":" . __LINE__,
        ]
    );
}

// Track Adrianne even if not in BgL
if ($argv[1] == "2") {
    $GLOBALS["db"]->insert(
        'responselog',
        [
            'localts' => time(),
            'sent' => 0,
            'actor' => "rolemaster",
            'text' => "",
            'action' => "rolecommand|BackgroundCmd@0x0001A67C@Track",
            'tag' => __FILE__ . ":" . __LINE__,
        ]
    );
}

//
// Courier delivery test
if ($argv[1] == "1") {

    $parm1 = 0x02005204;    // Base formid NPC (Flying Chaurus)
    $parm2 = 0;             // Outfit formid (0 for creatures)
    $parm3 = 0;             // Weapon formid (0 for creatures)
    $parm4 = 0;             // Location formid (0 for nearby)
    $parm5 = 0;             // NPC formid to copy apperance from (0 if creature)
    $patchedTaskid = "2";   // Behavior, 1 aggresive, 2 submissive.

    $GLOBALS["db"]->insert(
        'responselog',
        [
            'localts' => time(),
            'sent' => 0,
            'actor' => "rolemaster",
            'text' => "",
            'action' => "rolecommand|spawnCharacter@MailerFly@$parm1@$parm2@$parm3@$parm4@$patchedTaskid@$parm5",
            'tag' => "",
        ]
    );

    $spawned = false;
    while (!$spawned) {
        sleep(1);
        error_log("[DEBUG] Checking if MailerFly spawned: " . time() . PHP_EOL);
        $res = $GLOBALS["db"]->fetchOne("select count(*) as n from eventlog where type='status_msg' and data like '%spawned@MailerFly@%'");
        $spawned = $res["n"] > 0;

    }

    $npcMaster = new NpcMaster();
    $npc = $npcMaster->getByName("MailerFly");
    $skyrimCmd = new SkyrimCommandBuilder();
    $json = $skyrimCmd->ObjectReference->SetScale("0x{$npc["refid"]}", 0.5);
    $skyrimCmd->send(cmd: $json);

    $npc["core"] = "MailerFly. a messaging fly";
    $npc["npc_static_bio"] = "MailerFly.  a messaging fly, gives letters to people";
    $npc["speechstyle"] = "Talks, use the words Fofofofocker! as a greeting, and makes buzzing sounds when flying around (*bzz*)";
    $npc["goals"] = "To give a letter (A letter from unknown sender)  to the Player. Should use action ReturnBackHome after giving the letter to the player.  Should not attack or fight, should avoid combat.";
    $npc["voiceid"] = "fly4fun";

    $npcMaster->updateByArray($npc);

    // Make MailerFly friendly to player
    $json = $skyrimCmd->Actor->RemoveFromAllFactions("0x{$npc["refid"]}");
    $skyrimCmd->send(cmd: $json);

    $json = $skyrimCmd->Actor->AddToFaction("0x{$npc["refid"]}", "0x0001dd09");//WEPlayerFriend
    $skyrimCmd->send(cmd: $json);

    $json = $skyrimCmd->Actor->SetFactionRank("0x{$npc["refid"]}", "0x0001dd09", 1);//WEPlayerFriend
    $skyrimCmd->send(cmd: $json);



    SkCreateItem("note", "A letter from unknown sender", "pocket", "A letter to Varek, just saying how amazing he is, from an anonymous sender", "0", "MailerFly");
    // Wait to confirm spawn
    $cn = $GLOBALS["db"]->escape("A letter from unknown sender");
    $spawned = false;
    while (!$spawned) {
        sleep(1);
        error_log("[DEBUG] Checking if letter  spawned: " . time() . PHP_EOL);
        $res = $GLOBALS["db"]->fetchOne("select count(*) as n from eventlog where type='status_msg'
     and data like '%spawned%@$cn%success%' ");
        $spawned = $res["n"] > 0;

    }


    $npcMaster = new NpcMaster();
    $npc = $npcMaster->getByName("MailerFly");
    $unsignedIntRef = hexdec($npc["refid"]) & 0xFFFFFFFF;

    $GLOBALS["db"]->insert(
        'responselog',
        [
            'localts' => time(),
            'sent' => 0,
            'actor' => "rolemaster",
            'text' => "",
            'action' => "rolecommand|moveToPlayer@MailerFly@bogus@7", // intent 7 = move and follow
            'tag' => "",
        ]
    );

    $present = false;
    while (!$present) {
        sleep(1);
        error_log("[DEBUG] Checking if MailerFly reached destination: " . time() . PHP_EOL);
        // Check via status_msg
        $res = $GLOBALS["db"]->fetchOne("select count(*) as n from eventlog where type='status_msg' and data like '%reached_destination_player@MailerFly%' ");
        $present = $res["n"] > 0;

        // Check via infonpc_close (sometimes the status_msg is not sent, because NPC cannot get to destination clearly)
        if (!$present) {
            $res = $GLOBALS["db"]->fetchOne("select count(*) as n from eventlog where type='infonpc_close' and data like '%MailerFly%' ");
            $present = $res["n"] > 0;
        }

    }



    $gave = false;
    $retries = 0;
    while (!$gave) {
        sleep(1);
        error_log("[DEBUG] Checking if MailerFly gave letter to Varek: " . time() . PHP_EOL);
        $res = $GLOBALS["db"]->fetchOne("select count(*) as n from eventlog where type='infoaction' and data like '%MailerFly gives 1 A letter from unknown sender to Varek%' ");
        if (++$retries == 1) {
            // Give letter if not gave yet
            $GLOBALS["db"]->insert(
                'responselog',
                [
                    'localts' => time(),
                    'sent' => 0,
                    'actor' => "rolemaster",
                    'text' => "",
                    'action' => "rolecommand|Instruction@MailerFly@Should use GiveItem action to give letter to Varek@0",
                    'tag' => "",
                ]
            );
        }
        $gave = $res["n"] > 0;

    }


    $GLOBALS["db"]->insert(
        'responselog',
        [
            'localts' => time(),
            'sent' => 0,
            'actor' => "rolemaster",
            'text' => "",
            'action' => "rolecommand|Instruction@MailerFly@Should use ReturnBackHome action after giving letter to Varek@0",
            'tag' => "",
        ]
    );
}


// Miner creation test
if ($argv[1] == "3") {

    $miner_profile = [
        "name" => "Karl the Miner",         // Name of the NPC
        "gender" => "male",                 // male or female
        "class" => "farmer",                  // "beggar"|"warrior"|"assassin"|"mage"|"farmer"|"soldier"|"merchant"|"noble"|"creature"|"forsworn"
        "race" => "nord",                    // "Nord"|"Imperial"|"Argonian"|"RedGuard"|"Orc"|"Breton"
        "location" => "Whistling Mine",            // Initial placement (e.g., "Whiterun" or "nearby") 
        "appearance" => "a sturdy nord miner",        // Visual description (optional)
        "background" => "He was born in a small village and grew up working in the mine 'Whistling Mine'",        // Lore/backstory (optional) e.g. "A reclusive scholar seeking lost knowledge. Born in Raven Rock...Grew up studying ancient texts...Currently obsessed with finding old trinkets."
        "speechStyle" => "rude, mining oriented",      // How NPC talks (optional), e.g., "casual, using slang, sometimes cursed words and mentions old god names"
        "disposition" => "friendly",      // "defiant"|"submissive"|"friendly"|"serious"|"sad"|"aggressive"|"cheerful"|"distrustful"|"furious"|"drunk"|"high"|"dead" (optional)
        "goal" => "
Earn some gold by mining and selling ores to merchants. 
* He must work in the mine 'Whistling Mine' for the day , can rest at the camp outside the same mine. 
* He sells irons to Thorgar, who is at the same location. (check inventory to know if he has ores to sell or must keep mining)
* Some evenings, travels to Winterhold to have some drinks at the inn 'The Frozen Hearth' ",          // NPC's main goal (optional)
    ];


    // This will spawn the NPC in the game world, but won't set up its profile in the database
    npcProfileBase(
        $miner_profile["name"],
        $miner_profile["class"],
        $miner_profile["race"],
        $miner_profile["gender"],
        $miner_profile["location"],
        "0",
        $miner_profile["additional_data"] ?? [],
    );

    $spawned = false;
    $cnName = $GLOBALS["db"]->escape($miner_profile["name"]);
    $last_gamets = null;
    $last_ts=null;
    while (!$spawned) {
        sleep(1);
        error_log("[DEBUG] Checking if " . $miner_profile["name"] . " spawned: " . time() . PHP_EOL);
        $res = $GLOBALS["db"]->fetchOne("select count(*) as n, max(gamets) as gamets,max(ts) as ts from eventlog where type='status_msg' and data like '%spawned@$cnName@%'");
        $spawned = $res["n"] > 0;
        $last_gamets = $res["gamets"];
        $last_ts = $res["ts"];
    }

    // We spawned the NPC, addnpc should have beeen trigered, so we can now update the NPC profile in the database
    $npcMaster = new NpcMaster();
    $npc = $npcMaster->getByName($miner_profile["name"]);
    
    
    $npc["core"] = "{$miner_profile["name"]}. {$miner_profile["gender"]} {$miner_profile["class"]} {$miner_profile["race"]}";
    $npc["npc_static_bio"] = "{$miner_profile["name"]}. {$miner_profile["background"]}";
    $npc["speechstyle"] = $miner_profile["speechStyle"];
    $npc["goals"] = $miner_profile["goal"];
    $npc["lock_profile"] = null;
    
  
    $metadata = $npcMaster->getExtendedData($npc);
    $metadata["gps_track"] = true;
    

    $npc=$npcMaster->setMetadata($npc, $metadata);
    $npcMaster->updateByArray($npc);
    error_log("[DEBUG] Updated NPC profile for {$miner_profile["name"]} in database waiting 10 secs" . PHP_EOL);
    
    $refid=isset($npc["refid"]) ? $npc["refid"] : null;

    if (empty($refid)) {
        error_log("[DEBUG] Waiting to refid to be populated for {$miner_profile["name"]}...".PHP_EOL);

        $maxRetries = 30;
        $retryCount = 0;
        while (empty($refid) && $retryCount < $maxRetries) {
            sleep(1);
            $retryCount++;
            $npcMaster = new NpcMaster();
            $npc = $npcMaster->getByName($miner_profile["name"]);
            $refid=isset($npc["refid"]) ? $npc["refid"] : null;
            error_log("[DEBUG] Waiting to refid to be populated for {$miner_profile["name"]}... $retryCount of $maxRetries".PHP_EOL);
        }

        if (empty($refid)) {
            error_log("[ERROR] Refid was not populated for {$miner_profile["name"]} after {$maxRetries} retries. Exiting." . PHP_EOL);
            exit(1);
        }

        error_log("[DEBUG] Refid populated for {$miner_profile["name"]}: $refid" . PHP_EOL);
        $npcMaster = new NpcMaster();
        $npc = $npcMaster->getByName($miner_profile["name"]);

    }

    // Add to BgL (plugin side)
    $GLOBALS["db"]->insert(
        'responselog',
        [
            'localts' => time(),
            'sent'    => 0,
            'actor'   => "rolemaster",
            'text'    => "",
            'action'  => "rolecommand|RenameNPC@0x$refid@$cnName",
            'tag'     => '',
        ]
    );

    $extended_data = $npcMaster->getExtendedData($npc);
    $extended_data["background_life_commands"] = true;
    $extended_data["background_life_enabled"] = true;
    $extended_data["background_life_last_updated"] = $last_gamets;
    $extended_data["background_life_player_unattached"] = true;// This NPC is not attached to a player, it is a purely background life NPC. A follower should be attached.
    $npc=$npcMaster->setExtendedData($npc, $extended_data);
    
    $npcMaster->updateByArray($npc);

    $skyrimCmd = new SkyrimCommandBuilder();
    $json = $skyrimCmd->ObjectReference->AddItem("0x{$npc["refid"]}", "0x0000000F", 10, true); // Add a gold coin to the NPC's inventory
    $skyrimCmd->send(cmd: $json);

    $json = $skyrimCmd->ObjectReference->AddItem("0x{$npc["refid"]}", "0x00071cf3", 10, true); // Add a iron ores to the NPC's inventory
    $skyrimCmd->send(cmd: $json);
    
     // Send him to the mine (interior), when in, should trigger an bgevent.
    $whistlingMineInterior=0x0002b0dd;
    $GLOBALS["db"]->insert(
        'responselog',
        [
            'localts' => time(),
            'sent' => 0,
            'actor' => "rolemaster",
            'text' => "",
            'action' => "rolecommand|BackgroundCmd@$refid@TravelTo/".((int)$whistlingMineInterior),
            'tag' => __FILE__ . ":" . __LINE__,
        ]
    );
    // Need to register the action to bgevent recognize it as valid

    $GLOBALS["db"]->insert('actions_issued', [
        'action' => 'TravelTo',
        'fullcall' => "TravelTo",
        'actorname' => $npc["npc_name"],
        'ts' => $last_ts,
        'gamets' => $last_gamets,
        'localts' => time(),
        'original' => 'backgroundaction',
    ]);


    // NPC created and added to background life. He is free now to roam the world and do his thing.
    
}
?>