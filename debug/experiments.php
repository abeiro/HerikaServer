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

//
if ($argv[1] == "0x") {
    $GLOBALS["db"]->insert(
        'responselog',
        [
            'localts' => time(),
            'sent' => 0,
            'actor' => "rolemaster",
            'text' => "",
            'action' => "rolecommand|BackgroundCmd@0xFF0010D8@MoveToPlayer",
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
            'action' => "rolecommand|BackgroundCmd@0x0001A6C8@Track",
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


function resolveFormIdToDecimal($formId)
{
    if (is_int($formId)) {
        return $formId;
    }

    $raw = trim((string) $formId);
    if ($raw === '') {
        return 0;
    }

    if (stripos($raw, '0x') === 0) {
        return (int) hexdec(substr($raw, 2));
    }

    return (int) $raw;
}

function spawnBackgroundLifeNpc($npc_profile, $startingPoint, $inventoryItems)
{
    if (!is_array($npc_profile) || empty($npc_profile['name'])) {
        error_log('[ERROR] Invalid npc profile provided to spawnBackgroundLifeNpc');
        return false;
    }

    $startingPointDec = resolveFormIdToDecimal($startingPoint);
    if ($startingPointDec <= 0) {
        error_log('[ERROR] Invalid starting point provided for ' . ($npc_profile['name'] ?? 'unknown npc'));
        return false;
    }

    npcProfileBase(
        $npc_profile['name'],
        $npc_profile['class'],
        $npc_profile['race'],
        $npc_profile['gender'],
        $npc_profile['location'],
        '0',
        $npc_profile['additional_data'] ?? []
    );

    $spawned = false;
    $cnName = $GLOBALS['db']->escape($npc_profile['name']);
    $last_gamets = null;
    $last_ts = null;

    while (!$spawned) {
        sleep(1);
        error_log('[DEBUG] Checking if ' . $npc_profile['name'] . ' spawned: ' . time() . PHP_EOL);
        $res = $GLOBALS['db']->fetchOne("select count(*) as n, max(gamets) as gamets,max(ts) as ts from eventlog where type='status_msg' and data like '%spawned@$cnName@%'");
        $spawned = $res['n'] > 0;
        $last_gamets = $res['gamets'];
        $last_ts = $res['ts'];
    }

    $npcMaster = new NpcMaster();
    $npc = $npcMaster->getByName($npc_profile['name']);
    $npc['core'] = "{$npc_profile['name']}. {$npc_profile['gender']} {$npc_profile['class']} {$npc_profile['race']}";
    $npc['npc_static_bio'] = "{$npc_profile['name']}. {$npc_profile['background']}";
    $npc['speechstyle'] = $npc_profile['speechStyle'];
    $npc['goals'] = $npc_profile['goal'];
    $npc['lock_profile'] = null;

    $metadata = $npcMaster->getExtendedData($npc);
    $metadata['gps_track'] = true;
    $npc = $npcMaster->setMetadata($npc, $metadata);
    $npcMaster->updateByArray($npc);

    $refid = isset($npc['refid']) ? $npc['refid'] : null;
    if (empty($refid)) {
        error_log('[DEBUG] Waiting to refid to be populated for ' . $npc_profile['name'] . '...' . PHP_EOL);

        $maxRetries = 30;
        $retryCount = 0;
        while (empty($refid) && $retryCount < $maxRetries) {
            sleep(1);
            $retryCount++;
            $npcMaster = new NpcMaster();
            $npc = $npcMaster->getByName($npc_profile['name']);
            $refid = isset($npc['refid']) ? $npc['refid'] : null;
            error_log('[DEBUG] Waiting to refid to be populated for ' . $npc_profile['name'] . "... $retryCount of $maxRetries" . PHP_EOL);
        }

        if (empty($refid)) {
            error_log('[ERROR] Refid was not populated for ' . $npc_profile['name'] . " after {$maxRetries} retries. Exiting." . PHP_EOL);
            return false;
        }

        $npcMaster = new NpcMaster();
        $npc = $npcMaster->getByName($npc_profile['name']);
    }

    sleep(1);
    $GLOBALS['db']->insert(
        'responselog',
        [
            'localts' => time(),
            'sent' => 0,
            'actor' => 'rolemaster',
            'text' => '',
            'action' => "rolecommand|RenameNPC@0x$refid@$cnName",
            'tag' => '',
        ]
    );

    sleep(1);
    $npcMaster = new NpcMaster();
    $npc = $npcMaster->getByName($npc_profile['name']);
    $extended_data = $npcMaster->getExtendedData($npc);
    $extended_data['background_life_commands'] = true;
    $extended_data['background_life_enabled'] = true;
    $extended_data['background_life_last_updated'] = $last_gamets;
    $extended_data['background_life_player_unattached'] = true;
    $extended_data['middle_term_enabled'] = 1;
    

    $npc['core'] = "{$npc_profile['name']}. {$npc_profile['gender']} {$npc_profile['class']} {$npc_profile['race']}";
    $npc['npc_static_bio'] = "{$npc_profile['name']}. {$npc_profile['background']}";
    $npc['speechstyle'] = $npc_profile['speechStyle'];
    $npc['goals'] = $npc_profile['goal'];
    $npc['lock_profile'] = null;

    $metadata = $npcMaster->getExtendedData($npc);
    $metadata['gps_track'] = true;
    $npc = $npcMaster->setMetadata($npc, $metadata);
    $npc = $npcMaster->setExtendedData($npc, $extended_data);
    $npcMaster->updateByArray($npc);

    $skyrimCmd = new SkyrimCommandBuilder();
    foreach ($inventoryItems as $itemEntry) {
        if (!is_array($itemEntry)) {
            continue;
        }

        $itemRefId = isset($itemEntry['refid']) ? (string) $itemEntry['refid'] : '';
        $itemQty = isset($itemEntry['qty']) ? (int) $itemEntry['qty'] : 0;
        if ($itemRefId === '' || $itemQty <= 0) {
            continue;
        }

        $json = $skyrimCmd->ObjectReference->AddItem("0x{$npc['refid']}", $itemRefId, $itemQty, true);
        $skyrimCmd->send(cmd: $json);
    }

    $GLOBALS['db']->insert(
        'responselog',
        [
            'localts' => time(),
            'sent' => 0,
            'actor' => 'rolemaster',
            'text' => '',
            'action' => "rolecommand|BackgroundCmd@$refid@TravelTo/$startingPointDec",
            'tag' => __FILE__ . ':' . __LINE__,
        ]
    );

    $res = $GLOBALS['db']->fetchOne('select max(gamets) as gamets,max(ts) as ts from eventlog order by gamets desc,ts desc limit 1');
    $last_gamets = $res['gamets'];
    $last_ts = $res['ts'];

    $GLOBALS['db']->insert('actions_issued', [
        'action' => 'TravelTo',
        'fullcall' => 'TravelTo',
        'actorname' => $npc['npc_name'],
        'ts' => $last_ts,
        'gamets' => $last_gamets,
        'localts' => time(),
        'original' => 'backgroundaction',
    ]);

    return true;
}

// Route 3: Karl the Miner example using reusable spawner
if ($argv[1] == '3') {
    $npc_profile = [
        'name' => 'Karl the Miner',
        'gender' => 'male',
        'class' => 'farmer',
        'race' => 'nord',
        'location' => 'Whistling Mine',
        'appearance' => 'a sturdy nord miner',
        'background' => "He was born in a small village and grew up working in the mine 'Whistling Mine'",
        'speechStyle' => 'rude, mining oriented',
        'disposition' => 'friendly',
        'goal' => "[Life goals]
Earn some gold by mining and selling ores to merchants.
* He must work in the mine 'Whistling Mine (Interior)' for the day , can rest at the camp outside the same mine ('Whistling Mine').
* He sells irons to Thorgar, who is at the same location. (check inventory to know if he has ores to sell or must keep mining)
* He sells Gold Ores to Jorl Stoneman, who is at Whiterun (it's a long travel, but Jorl is the only one buying gold ores at good price, 100 gold each!)
* Some evenings, travels to Winterhold to have some drinks at the inn 'The Frozen Hearth', and spend some time flirting and trying to pick up women.
* He must eat/drink at least once a day.
[Production]
When at a working scenario, he produces iron ore (item refid:0x00071cf3) at a rate of 2 each hour.
Sometimes he finds additional Gold Ore (item refid:0x0005acde) at a rate of 0.2 each hour.
",
    ];

    $startingPoint = 0x0002b0dd;
    $inventoryItems = [
        ['refid' => '0x0000000F', 'qty' => 100], // 100 gold coins
        ['refid' => '0x00071cf3', 'qty' => 10], // 10 ores
        ['refid' => '0x0005acde', 'qty' => 5], // 5 gold ores
    ];

    spawnBackgroundLifeNpc($npc_profile, $startingPoint, $inventoryItems);
}

if ($argv[1] == '4') {
    $npc_profile = [
        'name' => 'Ingesh the Miner',
        'gender' => 'female',
        'class' => 'farmer',
        'race' => 'breton',
        'location' => 'Dawnstar',
        'appearance' => 'a sturdy breton miner',
        'background' => "She was born in a small village far away, after travellng over Skyrim searching for work, she found a job in the mine 'Iron-Breaker Mine'",
        'speechStyle' => 'rude, mining oriented',
        'disposition' => 'friendly',
        'goal' => "[Life goals]
Earn some gold by mining and selling ores to merchants.
* She must work in the mine 'Iron-Breaker Mine (Interior)' for the day , can rest at the 'Windpeak Inn' .
* She sells irons to Gjak, who is at the same location. (check inventory to know if she has ores to sell or must keep mining)
* Some evenings, travels to the inn 'Windpeak Inn' (at same location Dawnstar) to have some drinks and socialize with other miners.'
[Production]
When at a working scenario, she produces iron ore (item refid:0x00071cf3) at a rate of 2 each hour.
",
    ];

    $startingPoint = 0x0001a6bc;
    $inventoryItems = [
        ['refid' => '0x0000000F', 'qty' => 10],
        ['refid' => '0x00071cf3', 'qty' => 10],
    ];

    spawnBackgroundLifeNpc($npc_profile, $startingPoint, $inventoryItems);
}

if ($argv[1] == '5') {
    $npc_profile = [
        'name' => 'Anne Rimbaunn',
        'gender' => 'female',
        'class' => 'mage',
        'race' => 'imperial',
        'location' => 'Solitude',
        'appearance' => 'an imperial mage',
        'background' => 'She was born in a small village far away, after travellng over Skyrim, stablished a business about enchanting tools and weapons',
        'speechStyle' => 'technical, use latin terms and arcane references',
        'disposition' => 'friendly',
        'goal' => "[Life goals]
Earn some gold by travelling to the main cities to Skyrim, enchanting weapons and tools for citizens.
* She must travel to the main cities of Skyrim, Whiterun, Solitude, Windhelm, Riften, Markarth and Dawnstar to offer her services.
* Eventually, she can rest at the inns of the cities, and socialize with other merchants and citizens.
* She likes to visit inns, shops and relevant POIs when in a city. Speak with citizens in a non-commercial way (tourism).
[Production]
When at a working scenario, she produces gold (item refid:0x00000f) at a rate of 20 each hour, by enchanting weapons and tools for citizens.
It's a service, so no items are produced, but gold is earned by enchanting weapons and tools for citizens.
",
    ];

    $startingPoint = 0x0004deb7;
    $inventoryItems = [
        ['refid' => '0x0000000F', 'qty' => 100],
    ];

    spawnBackgroundLifeNpc($npc_profile, $startingPoint, $inventoryItems);
}

if ($argv[1] == '6') {
    $npc_profile = [
        'name' => 'Cassia Valerius',
        'gender' => 'female',
        'class' => 'merchant',
        'race' => 'imperial',
        'location' => 'Solitude',
        'appearance' => 'an elegant imperial journalist',
        'background' => 'Born in Cyrodiil to a family of historians, Cassia Valerius became fascinated by the stories of ordinary people living through extraordinary events. She travelled to Skyrim after the Civil War began, determined to document the truth beyond the official speeches of jarls and generals. She believes every citizen, from a miner to a noble, has a story worth recording.',
        'speechStyle' => 'professional, inquisitive and diplomatic. She asks precise questions, listens carefully, and often references history, politics and local rumors. She is polite but persistent when seeking the truth.',
        'disposition' => 'friendly',
        'goal' => "[Life goals]
Become the most respected chronicler in Skyrim by collecting stories, rumors and testimonies from every corner of the province.

* She must travel regularly between the main cities of Skyrim: Solitude, Whiterun, Windhelm, Riften, Markarth and Dawnstar.
* In each city, she visits inns, markets, temples and important locations to speak with citizens.
* She interviews merchants, guards, workers and travelers about local events, conflicts, strange occurrences and rumors.
* She records interesting stories in her journal.
* She spends evenings at inns, discussing politics and recent events with locals.
* She sometimes investigates abandoned places, ruins or battlefields to gather first-hand accounts.
* She must eat/drink at least once a day.

[Journalism]
When visiting a city, she collects information from citizens:
- Local gossip
- Political tensions
- Crimes and mysteries
- Strange sightings
- Heroic deeds
- Problems affecting common people

[Production]
When at a city, tavern or social scenario, she generates gold (item refid:0x0000000f) at a rate of 1 each hour by selling written chronicles, reports and stories to publishers and nobles.
",
    ];

    $startingPoint = 0x0004deb7;
    $inventoryItems = [
        ['refid' => '0x0000000F', 'qty' => 100],
    ];

    spawnBackgroundLifeNpc($npc_profile, $startingPoint, $inventoryItems);
}
?>