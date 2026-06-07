<?php


/**
 * Force-trigger an NPC background life update on the next mid-term BGL check.
 *
 * Resets the NPC's `background_life_last_updated` timestamp to 0 so that the
 * next background life evaluation cycle treats the NPC as overdue and
 * immediately requests a new action from the LLM.
 *
 * @param string $npcName The display name of the NPC to trigger
 * @return void
 */
function triggerNpcUpdate($npcName)
{
    $npcManager = new NpcMaster();
    $npcData = $npcManager->getByName($npcName);
    $extended = json_decode($npcData["extended_data"], true);
    $extended["background_life_last_updated"] = 0;
    $npcData = $npcManager->setExtendedData($npcData, $extended);
    $npcManager->updateByArray($npcData);
}
/**
 * Handle TravelTo action for NPC background life
 * 
 * @param string $location The location to travel to
 * @param array $currentNpcData The NPC data array containing refid
 * @param string $npcName The NPC character name
 * @param int $last_ts The last timestamp
 * @param int $last_gamets The last game timestamp
 * @param int $momentum The current momentum/session timestamp
 * @param array $locationSrc The source location of the event 
 * @param object $db The database connection object
 * @return bool True if action was successfully processed, false otherwise
 */
function handleTravelToAction($location, $currentNpcData, $npcName, $last_ts, $last_gamets, $momentum, $locationSrc, $db)
{
    $cnLocation = $db->escape($location);
    if ($cnLocation == "random") {
        $locId = $db->fetchOne("select name,region,hold,formid from locations order by case when name=region then 1 else 0 end desc, random()");
        error_log("[handleTravelToAction] random picked: " . print_r($locId, true));
    } else {
        $locId = $db->fetchOne("select formid from locations where name='$cnLocation' order by case when name=region then 1 else 0 end desc");
    }

    if (!isset($locId["formid"])) {
        return false;
    }

    $refHexString = convertSignedToUnsignedHex(hexdec($currentNpcData["refid"]));
    $locHexString = (convertHex($locId["formid"]));

    error_log("Using refid $refHexString , location $locHexString");
    // Insert response log entry for travel command
    $db->insert(
        'responselog',
        [
            'localts' => time(),
            'sent' => 0,
            'actor' => "rolemaster",
            'text' => "",
            'action' => "rolecommand|BackgroundCmd@$refHexString@TravelTo/{$locId["formid"]}",
            'tag' => '',
        ]
    );

    // Insert event log entry
    $db->insert(
        'eventlog',
        [
            'ts' => $last_ts,
            'gamets' => $last_gamets + 10,
            'type' => "infoaction",
            'data' => "The Narrator: $npcName starts travelling to $cnLocation",
            'sess' => $momentum,
            'localts' => time(),
            'people' => $npcName,
            'location' => $location ?? null,
            'party' => "",
        ]
    );

    // Insert actions_issued log entry
    $db->insert(
        'actions_issued',
        [
            'action' => "TravelTo",
            'fullcall' => "TravelTo:$cnLocation",
            'actorname' => $npcName,
            'ts' => $last_ts,
            'gamets' => $last_gamets,
            'localts' => time(),
            'original' => 'backgroundaction',
        ]
    );

    return true;
}

/**
 * Handle StayAtPlace action for NPC background life
 * 
 * @param string $location The location where the NPC stays
 * @param array $currentNpcData The NPC data array containing refid
 * @param string $npcName The NPC character name
 * @param int $last_ts The last timestamp
 * @param int $last_gamets The last game timestamp
 * @param int $momentum The current momentum/session timestamp
 * @param object $db The database connection object
 * @return bool True if action was successfully processed, false otherwise
 */
function handleStayAtPlaceAction($location, $currentNpcData, $npcName, $last_ts, $last_gamets, $momentum, $db)
{
    $cnLocation = $db->escape($location);
    $locId = $db->fetchOne("select formid from locations where name='$cnLocation'");

    $refHexString = convertSignedToUnsignedHex(hexdec($currentNpcData["refid"]));

    // Insert response log entry with return home command
    $db->insert(
        'responselog',
        [
            'localts' => time(),
            'sent' => 0,
            'actor' => "rolemaster",
            'text' => "",
            'action' => "rolecommand|BackgroundCmd@$refHexString@StayAtPlace/",
            'tag' => '',
        ]
    );

    return true;
}

//sends a track signal, NPC should report coords
function handleTrack($currentNpcData, $db)
{
    $refHexString = convertSignedToUnsignedHex(hexdec($currentNpcData["refid"]));

    // Insert response log entry with return home command
    $db->insert(
        'responselog',
        [
            'localts' => time(),
            'sent' => 0,
            'actor' => "rolemaster",
            'text' => "",
            'action' => "rolecommand|BackgroundCmd@$refHexString@Track/",
            'tag' => '',
        ]
    );

    return true;
}

/**
 * Handle returnHome action for NPC background life
 * 
 * @param string $location The location where the NPC stays
 * @param array $currentNpcData The NPC data array containing refid
 * @param string $npcName The NPC character name
 * @param int $last_ts The last timestamp
 * @param int $last_gamets The last game timestamp
 * @param int $momentum The current momentum/session timestamp
 * @param object $db The database connection object
 * @return bool True if action was successfully processed, false otherwise
 */
function handleReturnHome($location, $currentNpcData, $npcName, $last_ts, $last_gamets, $momentum, $db)
{
    $cnLocation = $db->escape($location);
    $locId = $db->fetchOne("select formid from locations where name='$cnLocation'");

    $refHexString = convertSignedToUnsignedHex(hexdec($currentNpcData["refid"]));

    // Insert response log entry with return home command
    /*$db->insert(
        'responselog',
        [
            'localts' => time(),
            'sent' => 0,
            'actor' => "rolemaster",
            'text' => "",
            'action' => "rolecommand|BackgroundCmd@$refHexString@ReturnHome/",
            'tag' => '',
        ]
    );*/
    // Will move to player.
    // To-Do Define a home location for NPCs? For now, we will assume home is the player's location,
    //  so we will just send a move command to the player.

    $db->insert('responselog', [
        'localts' => time(),
        'sent' => 0,
        'actor' => 'rolemaster',
        'text' => '',
        'action' => "rolecommand|BackgroundCmd@$refHexString@MoveToPlayer",
        'tag' => '',
    ]);

    $db->insert('actions_issued', [
        'action' => 'MoveTo',
        'fullcall' => "ReturnHome",
        'actorname' => $npcName,
        'ts' => $last_ts,
        'gamets' => $last_gamets,
        'localts' => time(),
        'original' => 'backgroundaction',
    ]);
    
    // Will mark meta PENDING_DIALOGUE. When NPC reaches player, will talk to player. (triggered at addnpc)
    $npcManager = new NpcMaster();
    $npcData = $npcManager->getByName($npcName);
    $meta = $npcManager->getMetadata($npcData);
    $meta["PENDING_DIALOGUE"] = $last_gamets;
    $npcData = $npcManager->setMetadata($npcData, $meta);
    $npcManager->updateByArray($npcData);
    return true;
}

/**
 * Resolve a target NPC by name against core_npc_master.
 *
 * Strategy (requires pg_trgm extension, no schema changes beyond that):
 *   1. Exact case-insensitive match — fastest path, no trigram overhead.
 *   2. Trigram similarity via pg_trgm's % operator and similarity() function.
 *      The % operator filters rows whose similarity score meets the session
 *      threshold (default 0.3), and ORDER BY sim DESC returns the best match first.
 *
 * @param string $targetNpcName  Raw NPC name from the LLM response
 * @param object $db             Database connection
 * @return array|null            ['name' => ..., 'refid' => ...] or null if unresolvable
 */
function resolveNpcByName(string $targetNpcName, $db): ?array
{
    $targetEsc = $db->escape($targetNpcName);

    // Step 1 — exact case-insensitive match (avoids trigram scan entirely)
    $exact = $db->fetchOne(
        "SELECT npc_name AS name, refid
         FROM core_npc_master
         WHERE lower(npc_name) = lower('$targetEsc')
         LIMIT 1"
    );
    if (!empty($exact['refid'])) {
        return $exact;
    }

    // Step 2 — trigram similarity (pg_trgm). The % operator uses the GIN/GiST index
    // when available but works without one. similarity() scores 0..1; higher is closer.
    $best = $db->fetchOne(
        "SELECT npc_name AS name, refid,
                similarity(npc_name, '$targetEsc') AS sim
         FROM core_npc_master
         WHERE npc_name % '$targetEsc'
         ORDER BY sim DESC
         LIMIT 1"
    );

    if (!empty($best['refid'])) {
        error_log("[resolveNpcByName] Trigram match: '$targetNpcName' → '{$best['name']}' (similarity={$best['sim']})");
        return $best;
    }

    return null;
}

/**
 * Handle MoveTo action — instruct the NPC to physically move to another NPC.
 *
 * @param string $targetNpcName  The name of the target NPC
 * @param array  $currentNpcData The acting NPC's data (must contain refid)
 * @param string $npcName        The acting NPC's display name
 * @param int    $last_ts        Last wall-clock timestamp
 * @param int    $last_gamets    Last in-game timestamp
 * @param int    $momentum       Session timestamp
 * @param object $db             Database connection
 * @return bool  True on success, false if target NPC could not be resolved
 */
function handleMoveToAction($targetNpcName, $currentNpcData, $npcName, $last_ts, $last_gamets, $momentum, $db)
{
    $targetNpc = resolveNpcByName($targetNpcName, $db);

    if ($targetNpc === null) {
        error_log("[handleMoveToAction] Target NPC not found: $targetNpcName");
        return false;
    }

    $resolvedName = $targetNpc['name'];
    $refHexString = convertSignedToUnsignedHex(hexdec($currentNpcData['refid']));
    $targetRefHexString = convertSignedToUnsignedHex(hexdec($targetNpc['refid']));

    $db->insert('responselog', [
        'localts' => time(),
        'sent' => 0,
        'actor' => 'rolemaster',
        'text' => '',
        'action' => "rolecommand|BackgroundCmd@$refHexString@MoveTo/$targetRefHexString",
        'tag' => '',
    ]);

    $db->insert('eventlog', [
        'ts' => $last_ts,
        'gamets' => $last_gamets + 10,
        'type' => 'infoaction',
        'data' => "The Narrator: $npcName moves towards $resolvedName",
        'sess' => $momentum,
        'localts' => time(),
        'people' => $npcName,
        'location' => null,
        'party' => '',
    ]);

    $db->insert('actions_issued', [
        'action' => 'MoveTo',
        'fullcall' => "MoveTo:$resolvedName",
        'actorname' => $npcName,
        'ts' => $last_ts,
        'gamets' => $last_gamets,
        'localts' => time(),
        'original' => 'backgroundaction',
    ]);

    return true;
}

/**
 * Handle FindNPC action — instruct the NPC to search for another NPC whose location is unknown.
 * This is a preparatory step before MoveTo or SpeakTo; the character actively looks for the target.
 *
 * @param string $targetNpcName  The name of the target NPC to search for
 * @param array  $currentNpcData The acting NPC's data (must contain refid)
 * @param string $npcName        The acting NPC's display name
 * @param int    $last_ts        Last wall-clock timestamp
 * @param int    $last_gamets    Last in-game timestamp
 * @param int    $momentum       Session timestamp
 * @param object $db             Database connection
 * @return bool  True on success, false if target NPC could not be resolved
 */
function handleFindNPCAction($targetNpcName, $currentNpcData, $npcName, $last_ts, $last_gamets, $momentum, $db, $lastReportedLocation = null)
{
    $targetNpc = resolveNpcByName($targetNpcName, $db);

    if ($targetNpc === null) {
        error_log("[handleFindNPCAction] Target NPC not found: $targetNpcName");
        return false;
    }

    $resolvedName = $targetNpc['name'];
    $refHexString = convertSignedToUnsignedHex(hexdec($currentNpcData['refid']));
    $targetRefHexString = convertSignedToUnsignedHex(hexdec($targetNpc['refid']));

    $db->insert('responselog', [
        'localts' => time(),
        'sent' => 0,
        'actor' => 'rolemaster',
        'text' => '',
        'action' => "rolecommand|BackgroundCmd@$refHexString@FindNPC/$targetRefHexString",
        'tag' => '',
    ]);

    // Lets check if we have vendor factions for this NPC
    // If the NPC belongs to any vendor factions, we can assume it's a trader 
    // We can check and publish stock later
    $npcMaster = new NpcMaster();
    $targetNpcData = $npcMaster->getByName($resolvedName);
    $factions = $npcMaster->getNpcFactions($targetNpcData);
    $factionsArray = [];
    foreach ($factions as $faction) {
        $factionsArray[] = $faction["formid"];
    }
    $vendorFactionsNpcBelongs = $db->fetchAll("SELECT name,formid,vendor_cont,stock,gold,player_rank FROM factions WHERE
        formid IN ('" . implode("','", $factionsArray) . "') and vendor_cont is not null and vendor_cont<>'00000000'");

    if ($vendorFactionsNpcBelongs) {
        foreach ($vendorFactionsNpcBelongs as $vendorFaction) {
            $skyrimCmd = new SkyrimCommandBuilder();
            $json = $skyrimCmd->Faction->GetVendorFactionContainer("0x{$vendorFaction["formid"]}");
            $skyrimCmd->send(cmd: $json);
        }
    }


    $db->insert('eventlog', [
        'ts' => $last_ts,
        'gamets' => $last_gamets + 10,
        'type' => 'infoaction',
        'data' => "The Narrator: $npcName is searching for $resolvedName",
        'sess' => $momentum,
        'localts' => time(),
        'people' => $npcName,
        'location' => null,
        'party' => '',
    ]);

    $db->insert('actions_issued', [
        'action' => 'FindNPC',
        'fullcall' => "FindNPC:$resolvedName",
        'actorname' => $npcName,
        'ts' => $last_ts,
        'gamets' => $last_gamets,
        'localts' => time(),
        'original' => 'backgroundaction',
    ]);

    sleep(2); // Simulate time taken to find the NPC; in a real implementation, this would be event-driven rather than a fixed sleep

    $npcLocation = $db->fetchOne("SELECT *
    FROM core_npc_master
    WHERE refid='{$targetNpc['refid']}'
    AND (metadata->'last_coords'->>'last_updated')::bigint>$last_gamets
    ORDER BY (metadata->'last_coords'->>'last_updated')::bigint DESC");

    $detectedLocation = json_decode($npcLocation["metadata"], true)["last_coords"] ?? null;

    error_log("[handleFindNPCAction] <{$targetNpc['refid']}> <{$targetNpc['name']}>, wanted location: $lastReportedLocation" . print_r($detectedLocation, true));
    if ($detectedLocation && $detectedLocation[3] == $lastReportedLocation) {

        // Check if NPC to speak to is a vender/dtrader. We pusblish stock.
        $stockString = "";
        $npcMaster = new NpcMaster();
        $targetNpcData = $npcMaster->getByName($resolvedName);
        $factions = $npcMaster->getNpcFactions($targetNpcData);
        $factionsArray = [];
        foreach ($factions as $faction) {
            $factionsArray[] = $faction["formid"];
        }
        $vendorFactionsNpcBelongs = $db->fetchAll("SELECT name,formid,vendor_cont,stock,gold,player_rank FROM factions WHERE
         formid IN ('" . implode("','", $factionsArray) . "') and vendor_cont is not null and vendor_cont<>'00000000'");

        error_log("[handleFindNPCAction] Query to obtain vendor faction chest: SELECT name,formid,vendor_cont,stock,gold,player_rank FROM factions WHERE
         formid IN ('" . implode("','", $factionsArray) . "') and vendor_cont is not null and vendor_cont<>'00000000'");
         
        if ($vendorFactionsNpcBelongs) {
            $stockString = " $resolvedName seems to be a trader, use action SpeakTo to dialogue and obtain his stock information. ";

        }


        error_log("[handleFindNPCAction] $npcName found $resolvedName at location: $lastReportedLocation. " . print_r($detectedLocation, true));
        // Move to the NPC's last known location
        $db->insert('responselog', [
            'localts' => time(),
            'sent' => 0,
            'actor' => 'rolemaster',
            'text' => '',
            'action' => "rolecommand|BackgroundCmd@$refHexString@MoveTo/$targetRefHexString",
            'tag' => '',
        ]);

        $db->insert('eventlog', [
            'ts' => $last_ts + 1,
            'gamets' => $last_gamets + 20,
            'type' => 'innerchat',
            'data' => "The Narrator: $npcName locates $resolvedName at $lastReportedLocation, and walks towards him/her.$stockString",
            'sess' => $momentum,
            'localts' => time(),
            'people' => $npcName,
            'location' => null,
            'party' => '',
        ]);

        $db->insert('actions_issued', [
            'action' => 'MoveTo',
            'fullcall' => "MoveTo:$targetRefHexString:$resolvedName",
            'actorname' => $npcName,
            'ts' => $last_ts,
            'gamets' => $last_gamets,
            'localts' => time(),
            'original' => 'backgroundaction',
        ]);



    } else if ($detectedLocation && $detectedLocation[3] != $lastReportedLocation) {

        $db->insert('eventlog', [
            'ts' => $last_ts + 1,
            'gamets' => $last_gamets + 20,
            'type' => 'innerchat',
            'data' => "The Narrator: Despite searching, $npcName could not find $resolvedName. (hint: Seems like $resolvedName is at {$detectedLocation[3]} {$detectedLocation["state"]}. Use action MoveTo:$resolvedName to move $npcName near to $resolvedName at that location) ",
            'sess' => $momentum,
            'localts' => time(),
            'people' => $npcName,
            'location' => null,
            'party' => '',
        ]);

        triggerNpcUpdate($npcName); // Force NPC to update its background life data on the next mid-term check, which should lead it to discover the new location and update accordingly.
    } else {
        error_log("[handleFindNPCAction] $npcName could not find $resolvedName. No recent location data available.");

        $db->insert('eventlog', [
            'ts' => time(),
            'gamets' => $last_gamets + 20,
            'type' => 'infoaction',
            'data' => "The Narrator: Despite searching, $npcName could not find any trace of $resolvedName",
            'sess' => $momentum,
            'localts' => time(),
            'people' => $npcName,
            'location' => null,
            'party' => '',
        ]);
        triggerNpcUpdate($npcName); // Force NPC to update its background life data on the next mid-term check, which should lead it to discover the new location and update accordingly.
    }




    return true;
}

/**
 * Handle SpeakTo action — instruct the NPC to initiate a conversation with another NPC.
 *
 * @param string $targetNpcName  The name of the target NPC
 * @param array  $currentNpcData The acting NPC's data (must contain refid)
 * @param string $npcName        The acting NPC's display name
 * @param int    $last_ts        Last wall-clock timestamp
 * @param int    $last_gamets    Last in-game timestamp
 * @param int    $momentum       Session timestamp
 * @param object $db             Database connection
 * @return bool  True on success, false if target NPC could not be resolved
 */
function handleSpeakToAction($targetNpcName, $currentNpcData, $npcName, $last_ts, $last_gamets, $momentum, $db, $connectionHandler = null, $dynamicBiography = '', $contextHistory = '', $lastEventLocation = null)
{
    $targetNpc = resolveNpcByName($targetNpcName, $db);

    if ($targetNpc === null) {
        error_log("[handleSpeakToAction] Target NPC not found: $targetNpcName");
        return false;
    }

    $resolvedName = $targetNpc['name'];
    $refHexString = convertSignedToUnsignedHex(hexdec($currentNpcData['refid']));
    $targetRefHexString = convertSignedToUnsignedHex(hexdec($targetNpc['refid']));

    // Check if NPC to speak to is a vender/dtrader. We pusblish stock.
    $stockString = "";
    $npcMaster = new NpcMaster();
    $targetNpcData = $npcMaster->getByName($resolvedName);
    $factions = $npcMaster->getNpcFactions($targetNpcData);
    $factionsArray = [];
    foreach ($factions as $faction) {
        $factionsArray[] = $faction["formid"];
    }
    $vendorFactionsNpcBelongs = $db->fetchAll("SELECT name,formid,vendor_cont,stock,gold,player_rank FROM factions WHERE
        formid IN ('" . implode("','", $factionsArray) . "') and vendor_cont is not null and vendor_cont<>'00000000'");

    error_log("[handleSpeakToAction] Query to obtain vendor faction chest: SELECT name,formid,vendor_cont,stock,gold,player_rank FROM factions WHERE
        formid IN ('" . implode("','", $factionsArray) . "') and vendor_cont is not null and vendor_cont<>'00000000'" );

    if ($vendorFactionsNpcBelongs && sizeof($vendorFactionsNpcBelongs) > 0  ) {
        $stockString = " $resolvedName seems to be a trader, selling: ";
        foreach ($vendorFactionsNpcBelongs as $vendorFaction) {
            $stockString .= " {$vendorFaction['stock']}.";
        }
    }

    $db->insert('eventlog', [
        'ts' => $last_ts,
        'gamets' => $last_gamets + 10,
        'type' => 'innerchat',
        'data' => "The Narrator: $npcName approaches $resolvedName to speak.$stockString",
        'sess' => $momentum,
        'localts' => time(),
        'people' => $npcName,
        'location' => $lastEventLocation,
        'party' => '',
    ]);

    $db->insert('actions_issued', [
        'action' => 'SpeakTo',
        'fullcall' => "SpeakTo:$resolvedName",
        'actorname' => $npcName,
        'ts' => $last_ts,
        'gamets' => $last_gamets,
        'localts' => time(),
        'original' => 'backgroundaction',
    ]);

    // ─── Generate NPC-to-NPC Dialogue via LLM ────────────────────────────────
    if ($connectionHandler !== null) {
        $contextBlock = !empty($dynamicBiography)
            ? "<character_sheet>\n{$npcName}:\n{$dynamicBiography}\n</character_sheet>\n\n"
            : '';

        $historyBlock = !empty($contextHistory)
            ? "<context_history>\n{$contextHistory}\n$stockString</context_history>\n\n"
            : '';

        $dialoguePrompt = [
            [
                'role' => 'system',
                'content' => 'You are a creative writer for the Skyrim (The Elder Scrolls) universe.'
                    . ' Generate a short, natural, in-character conversation between two NPCs.'
                    . ' Keep it concise (3–5 exchanges) and true to the Skyrim setting.'
                    . ' Output only the dialogue lines — no stage directions, no meta-commentary.',
            ],
            [
                'role' => 'user',
                'content' => "{$contextBlock}"
                    . "{$historyBlock}"
                    . "Write a brief, immersive dialogue between $npcName and $resolvedName.\n"
                    . "The conversation is initiated by $npcName.\n"
                    . "The dialogue must be consistent with the context_history above.\n"
                    . "Format each line exactly as:\n"
                    . "$npcName: ...\n$resolvedName: ...\n"
                    . 'Keep it to 3–5 exchanges total.',
            ],
        ];

        $dialogueBuffer = $connectionHandler->fast_request($dialoguePrompt, ['MAX_TOKENS' => 512], 'backgroundlife');

        if (!empty($dialogueBuffer)) {
            error_log("[handleSpeakToAction] Generated dialogue between $npcName and $resolvedName.");

            $db->insert('eventlog', [
                'ts' => $last_ts,
                'gamets' => $last_gamets + 20,
                'type' => 'innerchat',
                'data' => "The Narrator: background dialogue:\n$dialogueBuffer",
                'sess' => $momentum,
                'localts' => time(),
                'people' => "|$npcName|$resolvedName|",
                'location' => $lastEventLocation,
                'party' => '',
            ]);
        }
        triggerNpcUpdate($npcName);
    }

    return true;
}

/**
 * Handle BuyItems / SellItems action — instruct the NPC to buy from or sell to another NPC.
 *
 * @param string $tradeType      'BuyItems' or 'SellItems'
 * @param string $targetNpcName  The name of the target NPC
 * @param array  $currentNpcData The acting NPC's data (must contain refid)
 * @param string $npcName        The acting NPC's display name
 * @param int    $last_ts        Last wall-clock timestamp
 * @param int    $last_gamets    Last in-game timestamp
 * @param int    $momentum       Session timestamp
 * @param object $db             Database connection
 * @return bool  True on success, false if target NPC could not be resolved
 */
function handleTradeItemsAction($tradeType, $actionArgument, $currentNpcData, $npcName, $last_ts, $last_gamets, $momentum, $db)
{
    $args = explode(':', $actionArgument);
    $targetNpcName = $args[0] ?? '';
    $itemId = $args[1] ?? '';
    $count = $args[2] ?? '';
    $gold = $args[3] ?? '';

    $targetNpc = resolveNpcByName($targetNpcName, $db);

    if ($targetNpc === null) {
        error_log("[handleTradeItemsAction] [$tradeType] Target NPC not found: $targetNpcName");
        return false;
    }

    $resolvedName = $targetNpc['name'];
    $sourceRefHexString = convertSignedToUnsignedHex(hexdec($currentNpcData['refid']));
    $targetRefHexString = convertSignedToUnsignedHex(hexdec($targetNpc['refid']));

    if ($tradeType === 'BuyItems') {
        $skyrimCmd = new SkyrimCommandBuilder();
        // Item
        $json = $skyrimCmd->ObjectReference->AddItem($sourceRefHexString, "0x$itemId", $count, true);
        $skyrimCmd->send(cmd: $json);
        // Gold
        $json = $skyrimCmd->ObjectReference->RemoveItem($sourceRefHexString, "0x000000ff", $gold, true);
        $skyrimCmd->send(cmd: $json);

        $json = $skyrimCmd->ObjectReference->RemoveItem($targetRefHexString, "0x$itemId", $count, true);
        $skyrimCmd->send(cmd: $json);
        // Gold
        $json = $skyrimCmd->ObjectReference->AddItem($sourceRefHexString, "0x000000ff", $gold, true);
        $skyrimCmd->send(cmd: $json);

    } else {
        $skyrimCmd = new SkyrimCommandBuilder();
        // Item
        $json = $skyrimCmd->ObjectReference->RemoveItem($sourceRefHexString, "0x$itemId", $count, true);
        $skyrimCmd->send(cmd: $json);
        // Gold
        $json = $skyrimCmd->ObjectReference->AddItem($sourceRefHexString, "0x000000ff", $gold, true);
        $skyrimCmd->send(cmd: $json);

        $json = $skyrimCmd->ObjectReference->AddItem($targetRefHexString, "0x$itemId", $count, true);
        $skyrimCmd->send(cmd: $json);
        // Gold
        $json = $skyrimCmd->ObjectReference->RemoveItem($sourceRefHexString, "0x000000ff", $gold, true);
        $skyrimCmd->send(cmd: $json);
    }


    $db->insert('eventlog', [
        'ts' => $last_ts,
        'gamets' => $last_gamets + 10,
        'type' => 'innerchat',
        'data' => "The Narrator: $npcName " . ($tradeType === 'BuyItems' ? "buys items from" : "sells items to") . " $resolvedName. Inventories updated!",
        'sess' => $momentum,
        'localts' => time(),
        'people' => "|$npcName|$resolvedName|",
        'location' => null,
        'party' => '',
    ]);

    $db->insert('actions_issued', [
        'action' => $tradeType,
        'fullcall' => "$tradeType:$resolvedName:$itemId:$count:$gold",
        'actorname' => $npcName,
        'ts' => $last_ts,
        'gamets' => $last_gamets,
        'localts' => time(),
        'original' => 'backgroundaction',
    ]);


    triggerNpcUpdate($npcName);
    return true;
}