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
            'action' => "rolecommand|BackgroundCmd@0xff0010d8@TravelTo/129133",
            'tag' => __FILE__ . ":" . __LINE__,
        ]
    );
}

// Track Adrianne vene if not in BgL
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

    $parm1 = 0x02005204;    // Base formid NPC
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
        $res = $GLOBALS["db"]->fetchOne("select count(*) as n from eventlog where type='status_msg'
     and data like '%spawned@MailerFly@%'");
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
        $res = $GLOBALS["db"]->fetchOne("select count(*) as n from eventlog where type='status_msg'
     and data like '%reached_destination_player@MailerFly%' ");
        $present = $res["n"] > 0;

        // Check via infonpc_close (sometimes the status_msg is not sent, because NPC cannot get to destination clearly)
        if (!$present) {
            $res = $GLOBALS["db"]->fetchOne("select count(*) as n from eventlog where type='infonpc_close'
        and data like '%MailerFly%' ");
            $present = $res["n"] > 0;
        }

    }



    $gave = false;
    $retries = 0;
    while (!$gave) {
        sleep(1);
        error_log("[DEBUG] Checking if MailerFly gave letter to Varek: " . time() . PHP_EOL);
        $res = $GLOBALS["db"]->fetchOne("select count(*) as n from eventlog where type='infoaction'
     and data like '%MailerFly gives 1 A letter from unknown sender to Varek%' ");
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
?>