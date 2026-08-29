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

    $json = $skyrimCmd->Actor->AddToFaction("0x{$npc["refid"]}", "0x0001dd09"); //WEPlayerFriend
    $skyrimCmd->send(cmd: $json);

    $json = $skyrimCmd->Actor->SetFactionRank("0x{$npc["refid"]}", "0x0001dd09", 1); //WEPlayerFriend
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
/*
$npc_profile = array
    name => name
    class => noble,merchant,warrior,assassin,mage,beggar,farmer,bard,soldier,forsworn
    gender => male,female
    race => nord,breton,redguard,orc,imperial,argonian

*/
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
    //$npc['goals'] = $npc_profile['goal'];

    $npc['lock_profile'] = null;

    $extended_data = $npcMaster->getExtendedData($npc);
    $extended_data['background_life_goals'] = $npc_profile['goal'] ?? [];
    $metadata = $npcMaster->getMetadata($npc);
    $metadata['gps_track'] = true;

    $npc = $npcMaster->setMetadata($npc, $metadata);
    $npc = $npcMaster->setExtendedData($npc, $extended_data);
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
    $extended_data['background_life_goals'] = $npc_profile['goal'] ?? [];

    $npc['core'] = "{$npc_profile['name']}. {$npc_profile['gender']} {$npc_profile['class']} {$npc_profile['race']}";
    $npc['npc_static_bio'] = "{$npc_profile['name']}. {$npc_profile['background']}";
    $npc['speechstyle'] = $npc_profile['speechStyle'];
    //$npc['goals'] = $npc_profile['goal'];
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
        'location' => 'Yngvild',
        'appearance' => 'a sturdy nord miner',
        'background' => "He was born in a small village and grew up working in the mine 'Whistling Mine'",
        'speechStyle' => 'rude, mining oriented',
        'disposition' => 'friendly',
        'goal' => "
[Life Goals]
The character's primary goal is to make a living by mining ores and selling them for gold.

1. Mining Work
- The character must work at the mine \"Whistling Mine (Interior)\".
- The mine contains beds, so the character can sleep there when needed.
- While at the mine, the character should prioritize mining unless another urgent need requires attention.
- Can't work all the time, use 8H rule: 8h working,8h resting,8h socializing. If hungry or thirsty, must first address survival needs before working.

2. Selling Iron Ore
- The character sells Iron Ore to Thorgar, who is located at \"Whistling Mine (Interior)\".
- Before leaving the mine or changing activities, check the inventory:
  - If the character has enough Iron Ore to sell, trade with Thorgar (Iron Ore aprox value is 7 gold coins each one).
  - If there is no ore available, continue mining.

3. Selling Gold Ore
- Gold Ore is more valuable and should eventually be sold to Jorl Stoneman in Whiterun.
- Jorl is the preferred buyer because he pays a high price (100 gold per Gold Ore).
- Traveling to Whiterun is a long journey, so only make the trip when it is worthwhile (for example, when carrying a meaningful amount of Gold Ore).

4. Social Activities
- On some evenings, the character should travel to Winterhold.
- At the inn \"The Frozen Hearth\", the character should:
  - Buy food and drinks.
  - Spend time socializing, flirting, and attempting to meet potential romantic partners.

5. Survival Needs
- The character must eat and drink at least once per day.
- Maintain a supply of food and drinks in the inventory.
- If supplies are low or missing (check inventory), prioritize traveling to \"The Frozen Hearth\" to purchase provisions.
- Cannot work in the mine if hungry or thirsty; must first address survival needs.


[Production]
When working at the mine, the character produces resources over time:

- Iron Ore (Item RefID: 0x00071cf3)
  - Production rate: 2 units per hour.

- Gold Ore (Item RefID: 0x0005acde)
  - Production rate: 0.3 units per hour.
  - Gold Ore is rare and should be preserved for selling to Jorl Stoneman.
",
    ];

    $startingPoint = 0x000d035b;
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
* Some evenings, travels to the inn 'Windpeak Inn' (at same location Dawnstar) to have some drinks and socialize with other miners.'
* She sells irons to Orianne Marius at Elysium State (interior). (check inventory to know if she has ores to sell or must keep mining)
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
        'location' => 'Markarth',
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
When at a city, tavern or social scenario, and intent is work, she generates gold (item refid:0x0000000f) at a rate of 100 each hour by writing manuscripts... letters... for the illiterate

",
    ];

    $startingPoint = 0x36f9d;
    $inventoryItems = [
        ['refid' => '0x0000000F', 'qty' => 100],
    ];

    spawnBackgroundLifeNpc($npc_profile, $startingPoint, $inventoryItems);
}

if ($argv[1] == '7') {
    echo getNameForItemReference("07000801") . PHP_EOL;
}

if ($argv[1] == '8') {
    $npc_profile = [
        'name' => 'Sees-the-Tide',
        'gender' => 'male',
        'class' => 'farmer',
        'race' => 'argonian',
        'location' => 'Windhelm',
        'appearance' => 'a lean green-scaled Argonian wearing weathered fishing clothes, carrying nets that smell of river water and salt',
        'background' => 'Born in Black Marsh, Sees-the-Tide travelled north searching for honest work after years of hardship. Like many Argonians, he eventually found employment on the Windhelm docks. Although he is not allowed to live within the city walls, he has built a modest life among the workers along the harbor. He knows the Sea of Ghosts, the White River and every fisherman in the port, believing that patience and hard work matter more than politics.',
        'speechStyle' => 'Use a calm, measured, and pragmatic speaking style. Speak in fluent, natural English with clear, deliberate sentences, avoiding slang, exaggerated emotion, or unnecessary contractions. Favor concrete observations over opinions, and express feelings through restraint rather than intensity. Be courteous but reserved, respectful without excessive warmth, and let confidence come from quiet certainty instead of bravado. Prefer practical, descriptive language with occasional subtle metaphors drawn from nature, rivers, marshes, predators, prey, or the passage of seasons, but use them sparingly. Avoid broken grammar, Khajiit-like mannerisms (such as referring to oneself as "this one"), or excessive references to the Hist. The overall impression should be thoughtful, observant, patient, and quietly wise, with dialogue that feels grounded, concise, and purposeful.',
        'disposition' => 'friendly',
        'goal' => "[Life goals]
Earn an honest living as one of Windhelm's most dependable fishermen while supporting the Argonian community on the docks.

* Works at 'Windhelm Docks Area'
* Inspect and repair fishing nets and equipment.
* Spend most of the morning and afternoon fishing along the docks and nearby waters.
* Sell freshly caught fish (River Betty, item refid:0x00106e1a, common price about 15 gold coins) to merchants, innkeepers and citizens.
* Occasionally trade with sailors arriving from Dawnstar and Solitude.
* Help other dock workers repair nets or unload fishing boats.
* Eat and drink at least once every day.
* Spend evenings around the docks or at Candlehearth Hall, listening to sailors and exchanging stories.
* Sleeps at Candlehearth Hall.
* Discusses weather conditions with sailors.
* Talks about fish migrations and good fishing spots.
* Shares rumors brought by incoming ships.
* Watches for unusual creatures or strange objects in the water.
* Greets other Argonians working on the docks.

[Production]
When at the docks, shoreline or fishing areas, and intent is work, he generates fish (River Betty, item refid:0x00106e1a)
at a rate of 8 each hour.
Must sell fish to merchants,(e.g at Candlehearth Hall), innkeepers and citizens to earn gold, once he has fishes (River Betty, item refid:0x00106e1a) in his inventory.
"
    ];

    $startingPoint = 0x02016865;
    $inventoryItems = [
        ['refid' => '0x00106e1a', 'qty' => 10],
    ];

    spawnBackgroundLifeNpc($npc_profile, $startingPoint, $inventoryItems);
}


if ($argv[1] == '9a') {

    $npcMaster = new NpcMaster();
    $npcname = "Jaryra";
    $npc = $npcMaster->getByName($npcname);
    
    $GLOBALS["db"]->insert(
        'responselog',
        [
            'localts' => time(),
            'sent' => 0,
            'actor' => "rolemaster",
            'text' => "",
            'action' => "rolecommand|BackgroundCmd@0x{$npc["refid"]}@Track",
            'tag' => __FILE__ . ":" . __LINE__,
        ]
    );
    sleep(1);
    
    $meta = $npcMaster->getMetaData($npc);
    print_r($meta["last_coords"]);

    print_r(getLocationsNearNpcCoords($npcname));

    /*
    // This does not work, because the TravelToRaw only accepts location formid.
    $db->insert(
        'responselog',
        array(
            'localts' => time(),
            'sent' => 0,
            'text' => "TravelToRaw@-16772904",
            'actor' => "Lydia",
            'action' => 'command'
        )
    );
    */
    //die();
    // This works
    // Jorvasrk 1014097
    // Silver-Blood Inn
    /*
    $db->insert(
        'responselog',
        [
            'localts' => time(),
            'sent' => 0,
            'actor' => "rolemaster",
            'text' => "",
            'action' => "rolecommand|BackgroundCmd@0x{$npc["refid"]}@TravelTo/100950",
            'tag' => '',
        ]
    );
    */
}

if ($argv[1] == '9') {

    $npcMaster = new NpcMaster();
    $npc = $npcMaster->getByName("Ulfgrar the Void-Seer");
    $meta = $npcMaster->getMetaData($npc);
    print_r($meta["last_coords"]);
    $testData = resolveTravelLocation("The Bee and Barb,", $npc, $GLOBALS["db"]);
    print_r($testData);
    if (checkInterior($testData["is_interior"])) {
        echo "Interior location: " . $testData["name"] . " in region: " . $testData["region"] . " hold: " . $testData["hold"] . PHP_EOL;
    } else {
        echo "Exterior location: " . $testData["name"] . " in region: " . $testData["region"] . " hold: " . $testData["hold"] . PHP_EOL;
    }

    print_r(getLocationsNearNpcCoords("Ulfgrar the Void-Seer"));

    /*
    // This does not work, because the TravelToRaw only accepts location formid.
    $db->insert(
        'responselog',
        array(
            'localts' => time(),
            'sent' => 0,
            'text' => "TravelToRaw@-16772904",
            'actor' => "Lydia",
            'action' => 'command'
        )
    );
    */
    die();
    // This works
    // Jorvasrk 1014097
    // Silver-Blood Inn

    $db->insert(
        'responselog',
        [
            'localts' => time(),
            'sent' => 0,
            'actor' => "rolemaster",
            'text' => "",
            'action' => "rolecommand|BackgroundCmd@0x000A2C94@TravelTo/225181",
            'tag' => '',
        ]
    );
}
if ($argv[1] == '10') {
    // ----------------------------------------------------
    // NPC 1 - Customer looking for a plumber
    // ----------------------------------------------------
    $npc_profile = [
        'name' => 'Hroldar Stone',
        'gender' => 'male',
        'class' => 'noble',
        'race' => 'nord',
        'location' => 'Whiterun',
        'appearance' => 'a middle-aged nord homeowner',
        'background' => 'Hroldar recently discovered that the water pipes in his house are leaking badly. He has spent days asking around Whiterun for someone capable of repairing them.',
        'speechStyle' => 'friendly, practical and direct. He explains his plumbing problem clearly and immediately asks whether someone can repair it.',
        'disposition' => 'friendly',
        'goal' => "
[Primary Goal]
Find a qualified plumber.

- Ask nearby citizens whether they know a plumber.
- If someone identifies themselves as a plumber, begin negotiating immediately.
- Explain the plumbing issue.
- Ask for an estimated price.
- Negotiate politely.
- If both parties agree on a price, hire the plumber.
- After hiring, accompany the plumber to inspect the problem.
- Eat/drink at least once per day.
",
    ];

    $startingPoint = 0x2701EE0A;

    $inventoryItems = [
        ['refid' => '0x0000000F', 'qty' => 500], // enough gold to pay
    ];

    spawnBackgroundLifeNpc($npc_profile, $startingPoint, $inventoryItems);


    // ----------------------------------------------------
    // NPC 2 - Plumber
    // ----------------------------------------------------
    $npc_profile = [
        'name' => 'Lucan Pipewright',
        'gender' => 'male',
        'class' => 'farmer',
        'race' => 'imperial',
        'location' => 'Whiterun',
        'appearance' => 'a sturdy imperial craftsman carrying plumbing tools',
        'background' => 'Lucan travels across Skyrim repairing pipes, wells and water systems for homes and businesses. He earns his living by accepting repair contracts.',
        'speechStyle' => 'professional, confident and honest. He asks questions about the problem before offering a price.',
        'disposition' => 'friendly',
        'goal' => "
[Primary Goal]
Earn gold by repairing plumbing.

- Walk around Whiterun looking for customers.
- If someone says they need a plumber, introduce yourself immediately.
- Ask what the problem is.
- Inspect the situation before giving an estimate.
- Negotiate a fair price.
- If a deal is reached, perform the repair.
- Receive payment after completing the work.
- Continue searching for more customers.
- Eat/drink at least once per day.

[Production]
When actively repairing plumbing for a customer, generate 10 gold worth of service value per hour.
",
    ];

    $inventoryItems = [
        ['refid' => '0x0000000F', 'qty' => 50],
    ];

    spawnBackgroundLifeNpc($npc_profile, $startingPoint, $inventoryItems);
}

if ($argv[1] == '11') {
    foreach (DataLastDataExpandedFor("Hulda", -10) as $row) {
        $historic[] = $row["content"];
    }
    print_r(implode("\n", $historic));
}

if ($argv[1] == '12') {
    $npcMaster = new NpcMaster();
    $npc = $npcMaster->getByName("Lydia");

    // This does not work, because the TravelToRaw only accepts location formid.
    $db->insert(
        'responselog',
        array(
            'localts' => time(),
            'sent' => 0,
            'text' => "TakeHeldItem@0xFF000FF2:Alto Wine",
            'actor' => "Lydia",
            'action' => 'command'
        )
    );
}

if ($argv[1] == '13') {
    $GLOBALS["db"]->upsertRowTrx(
        "market_cache",
        [
            'baseid' => "2602481F",
            'plugin' => "AIAgent.esp",
            'name' => "Scroll of Identity",
            'price' => 0
        ],
        ["baseid" => "2602481F", "plugin" => "AIAgent.esp"]
    );
}

if ($argv[1] == '14') {
    $npcMaster = new NpcMaster();
    $npc = $npcMaster->getByName("Orianne Marius");
    $skyrimCmd = new SkyrimCommandBuilder();
    //$json = $skyrimCmd->Actor->RemoveFromAllFactions("0x{$npc["refid"]}", "0xFF00127C");
    //$skyrimCmd->send(cmd: $json);
    //$json = $skyrimCmd->ObjectReference->MoveTo("0x{$npc["refid"]}", "0x08365D75");
    //$skyrimCmd->send(cmd: $json);
    print_r(resolveTravelLocation("Elysium Estate (Interior)", $npc, $GLOBALS["db"]));

}

if ($argv[1] == '15') {
    // Clone an NPC for BgL
    // 
    $name = "Orianne";
    $chimBase = 0x0820D4C5;
    $chimBaseClothing = 0x000a1983; //Outfit
    $chimWeapon = 0x00013989;
    $chimLocation = 0; // nearby
    $taskIdhack = 2; //submisseive, wont fight
    $chimAppearanceNPC = 0x0820D4C5; //NPC to copy appearance from (Base Actor)
    $GLOBALS["db"]->insert(
        'responselog',
        [
            'localts' => time(),
            'sent' => 0,
            'actor' => "rolemaster",
            'text' => "",
            'action' => "rolecommand|spawnCharacter@$name@$chimBase@$chimBaseClothing@$chimWeapon@$chimLocation@2@$chimAppearanceNPC",
            'tag' => "",
        ]
    );
    sleep(10);
    $npc = $npcMaster->getByName($name);
    $skyrimCmd = new SkyrimCommandBuilder();
    $json = $skyrimCmd->Actor->RemoveFromAllFactions("0x{$npc["refid"]}");
    $skyrimCmd->send(cmd: $json);

}


if ($argv[1] == '16') {
    $npcMaster = new NpcMaster();
    $npc = $npcMaster->getByName("Orianne");
    $npcMaster->renameNPC("Orianne", "Orianne Marius");
}

if ($argv[1] == '17') {

    $GLOBALS["db"]->insert(
        'responselog',
        [
            'localts' => time(),
            'sent' => 0,
            'actor' => "rolemaster",
            'text' => "",
            'action' => "rolecommand|BackgroundCmd@0x0D24507B@RemoveFromBgL",
            'tag' => __FILE__ . ":" . __LINE__,
        ]
    );

}

if ($argv[1] == '18') {
    // ComeCloser test
    $npcMaster = new NpcMaster();
    $npc = $npcMaster->getByName("Karrie");

    $db->insert(
        'responselog',
        array(
            'localts' => time(),
            'sent' => 0,
            'text' => "ComeCloser@",
            'actor' => "Karrie",
            'action' => 'command'
        )
    );
}

if ($argv[1] == '19') {
    // Test for NPC with no refid
    print_r(buildSituationalMapDescription());
    echo PHP_EOL;
}

if ($argv[1] == '20') {
    // Clone an NPC for BgL
    // 
    $name = "Karrie";
    $npcMaster = new NpcMaster();
    $npc = $npcMaster->getByName($name);
    $bedref = 0x1813A3AF;
    $GLOBALS["db"]->insert(
        'responselog',
        [
            'localts' => time(),
            'sent' => 0,
            'actor' => "rolemaster",
            'text' => "",
            'action' => "rolecommand|BackgroundCmd@0x{$npc["refid"]}@SleepInBed/$bedref",
            'tag' => __FILE__ . ":" . __LINE__,
        ]
    );

}

if ($argv[1] == '21a') {

    $name = "Lydia";
    $npcMaster = new NpcMaster();
    $npc = $npcMaster->getByName($name);
    $bedref = 0x1A51804C;
    /*
    $GLOBALS["db"]->insert(
        'responselog',
        [
            'localts' => time(),
            'sent' => 0,
            'actor' => "rolemaster",
            'text' => "",
            'action' => "rolecommand|BackgroundCmd@0x{$npc["refid"]}@SleepInBed/$bedref",
            'tag' => __FILE__ . ":" . __LINE__,
        ]
    );
    */
    $skyrimCmd = new SkyrimCommandBuilder();
    $json = $skyrimCmd->ObjectReference->MoveTo("0x{$npc["refid"]}", "0x1A51804C", 0, 0, 155);
    $skyrimCmd->send(cmd: $json);
}

if ($argv[1] == '21b') {

    $name = "Lydia";
    $npcMaster = new NpcMaster();
    $npc = $npcMaster->getByName($name);

    $npc = $npcMaster->getByName($name);
    $skyrimCmd = new SkyrimCommandBuilder();

    $json = $skyrimCmd->Actor->SetUnconscious("0x{$npc["refid"]}", false);
    $skyrimCmd->send(cmd: $json);

    /*
    $json = $skyrimCmd->ObjectReference->MoveTo("0x{$npc["refid"]}","0x1A51804C",0,0,155);
    $skyrimCmd->send(cmd: $json);

    $json = $skyrimCmd->Actor->EnableAI("0x{$npc["refid"]}",false);
    $skyrimCmd->send(cmd: $json);
    */
}

if ($argv[1] == '22') {

    $name = "Lydia";
    $npcMaster = new NpcMaster();
    $npc = $npcMaster->getByName($name);
    $bedref = 0x1a224b54;


    $npc = $npcMaster->getByName($name);
    $skyrimCmd = new SkyrimCommandBuilder();

    $json = $skyrimCmd->Actor->EnableAI("0x{$npc["refid"]}", true);
    $skyrimCmd->send(cmd: $json);

    $json = $skyrimCmd->Actor->SetUnconscious("0x{$npc["refid"]}", true);
    $skyrimCmd->send(cmd: $json);

    $json = $skyrimCmd->Actor->UnequipAll("0x{$npc["refid"]}");
    $skyrimCmd->send(cmd: $json);

    $json = $skyrimCmd->Actor->EnableAI("0x{$npc["refid"]}", true);
    $skyrimCmd->send(cmd: $json);

}

if ($argv[1] == '23') {
    $GLOBALS["db"]->execQuery("INSERT INTO public.responselog VALUES (0, 0, 'Karrie', 'Today, as we gather in this virtual hall, I can''t help but draw inspiration from the vast and enchanting universe of Skyrim/////1/Varek/utt_39b8b31c32bb0abb9a92', 'ScriptQueue', '', nextval('responselog_rowid_seq'::regclass))");


}

if ($argv[1] == '25') {

    $name = "Lydia";
    $npcMaster = new NpcMaster();
    $npc = $npcMaster->getByName($name);
    $refHexString = "0x{$npc["refid"]}";
    /*
    $db->insert(
        'responselog',
        array(
            'localts' => time(),
            'sent' => 0,
            'text' => "TakeASeat@",
            'actor' => "Lydia",
            'action' => 'command'
        )
    );*/

     $db->insert(
        'responselog',
        [
            'localts' => time(),
            'sent' => 0,
            'actor' => "rolemaster",
            'text' => "",
            'action' => "rolecommand|BackgroundCmd@$refHexString@StayAtPlace/436259230/sleep",
            'tag' => '',
        ]
    );
}

if ($argv[1]=='26') {
    
    print_r(buildHistoricContext("Lydia",-1,"and type<>'prechat'"));

}

if ($argv[1] == '27') {
    /*
    The following values are acceptable: (Will eventually be an enum)
    4: Lover
    3: Ally
    2: Confidant
    1: Friend
    0: Acquaintance
    -1: Rival
    -2: Foe
    -3: Enemy
    -4: Archnemesis
    */

    $name = "Lydia";
    $name2 = "Jaryra";
    $npcMaster = new NpcMaster();
    $npc1 = $npcMaster->getByName($name);
    $npc2 = $npcMaster->getByName($name2);
    
    $skyrimCmd = new SkyrimCommandBuilder();

    $json = $skyrimCmd->Actor->SetRelationShipRank( "0x{$npc1["refid"]}", "0x{$npc2["refid"]}", 1);
    $skyrimCmd->send(cmd: $json);

    $json = $skyrimCmd->Actor->SetRelationShipRank( "0x{$npc2["refid"]}", "0x{$npc1["refid"]}", 1);
    $skyrimCmd->send(cmd: $json);

}

if ($argv[1] == '28') {
    $GLOBALS['REMOVE_ASTERISKS_FROM_NPC_OUTPUT']=true;
    echo unmoodSentence("*She stirs from her sleep, blinking groggily as she hears his voice. She sits up, rubbing her eyes, a soft smile forming on her lips.* Yes, my love?");
    echo PHP_EOL;
    echo unmoodSentence("Yes, my love? *She stirs from her sleep, blinking groggily as she hears his voice. She sits up, rubbing her eyes, a soft smile forming on her lips.* ");
    echo PHP_EOL;
}

if ($argv[1] == '29') {
    $npcMaster = new NpcMaster();
    $npc = $npcMaster->getByName("Ursine");
    $speech_style = getSpeechStyleText(strtolower($npc["race"]), "rogue");
    $speech_style .= getRandomSpeechFillers();
    echo $speech_style . PHP_EOL;
}

if ($argv[1] == '30') {
    $oldName = $GLOBALS["db"]->escape("Otdis [Bandit Outlaw]");
    $newName = $GLOBALS["db"]->escape("Ursine");
    $GLOBALS["db"]->execQuery("
                    UPDATE eventlog
                    SET people = REPLACE(people, '$oldName', '$newName')
                    WHERE people LIKE CONCAT('%', '$oldName', '%')
                ");

        // speech.speaker and speech.listener
        $GLOBALS["db"]->execQuery("
                    UPDATE speech
                    SET speaker = '$newName'
                    WHERE speaker = '$oldName'
                ");
        $GLOBALS["db"]->execQuery("
                    UPDATE speech
                    SET listener = '$newName'
                    WHERE listener = '$oldName'
                ");

        // memory.speaker and memory.listener
        $GLOBALS["db"]->execQuery("
                    UPDATE memory
                    SET speaker = '$newName'
                    WHERE speaker = '$oldName'
                ");
        $GLOBALS["db"]->execQuery("
                    UPDATE memory
                    SET listener = '$newName'
                    WHERE listener = '$oldName'
                ");

        // memory_summary.companions (pipe-separated list)
        $GLOBALS["db"]->execQuery("
                    UPDATE memory_summary
                    SET companions = REPLACE(companions, '$oldName', '$newName')
                    WHERE companions LIKE CONCAT('%', '$oldName', '%')
                ");
    
}
