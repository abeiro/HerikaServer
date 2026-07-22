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
function triggerNpcUpdate($npcName, $error_count = 0)
{
    $npcManager = new NpcMaster();
    $extended["background_life_last_updated"] = 0;
    $extended["background_life_last_updated_ec"] = $error_count;
    $extended["background_life_last_updated_presence_delta"] = 0;
    

    $npcManager->updateExtendedKeysByName($npcName, $extended);
}

/**
 * Build a PostgreSQL point literal from NPC metadata last_coords.
 *
 * @param array $currentNpcData
 * @return string|null Point literal in the form '(x,y)' or null when unavailable
 */
function getNpcLastCoordsPoint($currentNpcData)
{
    $metadata = $currentNpcData['metadata'] ?? null;
    if (is_string($metadata)) {
        $metadata = json_decode($metadata, true);
    }

    $lastCoords = null;
    if (is_array($metadata) && isset($metadata['last_coords']) && is_array($metadata['last_coords'])) {
        $lastCoords = $metadata['last_coords'];
    } elseif (isset($currentNpcData['last_coords']) && is_array($currentNpcData['last_coords'])) {
        $lastCoords = $currentNpcData['last_coords'];
    }

    if (!$lastCoords) {
        return null;
    }

    $x = $lastCoords[0] ?? null;
    $y = $lastCoords[1] ?? null;
    if (!is_numeric($x) || !is_numeric($y)) {
        return null;
    }

    return '(' . floatval($x) . ',' . floatval($y) . ')';
}

/**
 * Resolve a TravelTo location using exact + fuzzy matching and optional coord distance.
 *
 * @param string $location
 * @param array $currentNpcData
 * @param object $db
 * @return array|null
 */
function resolveTravelLocation($location, $currentNpcData, $db)
{
    $cnLocation = $db->escape($location);

    if (strcasecmp($cnLocation, 'random') === 0) {
        return $db->fetchOne(
            "SELECT name, region, hold, formid, coords
             FROM locations
             ORDER BY CASE WHEN name = region THEN 1 ELSE 0 END DESC, random()
             LIMIT 1"
        );
    }

    $npcPoint = getNpcLastCoordsPoint($currentNpcData);
    $pointSql = '';
    $orderByDistanceSql = '';
    if (!empty($npcPoint)) {
        $npcPointEsc = $db->escape($npcPoint);
        $pointSql = ", coords <-> '{$npcPointEsc}'::point AS dist";
        $orderByDistanceSql = ', dist ASC';
    }

    // Prefer exact matches first, then fuzzy similarity. If we know NPC coords,
    // nearest matching marker is preferred when names collide.
    $loc = $db->fetchOne(
        "SELECT name, region, hold, formid, coords
                $pointSql,
                GREATEST(
                    COALESCE(similarity(name, '$cnLocation'), 0),
                    COALESCE(similarity(name||' (Interior)', '$cnLocation'), 0),
                    COALESCE(similarity(region, '$cnLocation'), 0),
                    COALESCE(similarity(hold, '$cnLocation'), 0)
                ) AS sim,
                CASE
                    WHEN lower(name) = lower('$cnLocation') THEN 3
                    WHEN lower(name||' (Interior)') = lower('$cnLocation') and is_interior=1 THEN 4
                    WHEN lower(region) = lower('$cnLocation') THEN 2
                    WHEN lower(hold) = lower('$cnLocation') THEN 1
                    ELSE 0
                END AS exact_rank
         FROM locations
         WHERE formid IS NOT NULL
         ORDER BY exact_rank DESC$orderByDistanceSql, sim DESC
         LIMIT 1"
    );

    return $loc ?: null;
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
    $locId = resolveTravelLocation($location, $currentNpcData, $db);
    $requestedLocation = $db->escape($location);
    $resolvedLocation = $locId['name'] ?? $requestedLocation;

    if (strcasecmp($requestedLocation, 'random') === 0) {
        error_log("[handleTravelToAction] random picked: " . print_r($locId, true));
    }

    if (!empty($locId)) {
        $sim = isset($locId['sim']) ? ', sim=' . $locId['sim'] : '';
        $dist = isset($locId['dist']) ? ', dist=' . $locId['dist'] : '';
        error_log("[handleTravelToAction] requested='$requestedLocation' resolved='{$resolvedLocation}' formid='{$locId['formid']}'$sim$dist");
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
            'data' => ($location == $resolvedLocation) ? "The Narrator: $npcName starts travelling to $location. Reason: {$GLOBALS["LAST_REASON"]}" : "The Narrator: $npcName starts travelling to $location (resolved as $resolvedLocation). Reason: {$GLOBALS["LAST_REASON"]}",
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
            'fullcall' => "TravelTo:$resolvedLocation",
            'actorname' => $npcName,
            'ts' => $last_ts,
            'gamets' => $last_gamets,
            'localts' => time(),
            'original' => 'backgroundaction',
        ]
    );

    // Insert bgl_history log entry
    $db->insert(
        'bgl_history',
        [
            'npc' => $npcName,
            'ts' => $last_ts,
            'gamets' => $last_gamets,
            'localts' => time(),
            'data' => ($location == $resolvedLocation) ? "$npcName starts travelling to $location. Reason: {$GLOBALS["LAST_REASON"]}" : "$npcName starts travelling to $location (resolved as $resolvedLocation). Reason: {$GLOBALS["LAST_REASON"]}",
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
function handleStayAtPlaceAction($location, $currentNpcData, $npcName, $last_ts, $last_gamets, $momentum, $db, $intent = '')
{
    $locId = resolveTravelLocation($location, $currentNpcData, $db);
    $requestedLocation = $db->escape($location);
    $resolvedLocation = $locId['name'] ?? $requestedLocation;
    $intent = trim((string) $intent);
    $intentSuffix = $intent !== '' ? ":$intent" : '';
    $intentText = $intent !== '' ? " with intent '$intent'" : '';

    if (strcasecmp($requestedLocation, 'random') === 0) {
        error_log("[handleStayAtPlaceAction] random picked: " . print_r($locId, true));
    }

    if (!empty($locId)) {
        $sim = isset($locId['sim']) ? ', sim=' . $locId['sim'] : '';
        $dist = isset($locId['dist']) ? ', dist=' . $locId['dist'] : '';
        error_log("[handleStayAtPlaceAction] requested='$requestedLocation' resolved='{$resolvedLocation}' formid='{$locId['formid']}'$sim$dist");
    }

    if (!isset($locId["formid"])) {
        return false;
    }

    $refHexString = convertSignedToUnsignedHex(hexdec($currentNpcData["refid"]));
    $locHexString = (convertHex($locId["formid"]));


    // Insert response log entry with return home command
    $db->insert(
        'responselog',
        [
            'localts' => time(),
            'sent' => 0,
            'actor' => "rolemaster",
            'text' => "",
            'action' => "rolecommand|BackgroundCmd@$refHexString@StayAtPlace/{$locId["formid"]}",
            'tag' => '',
        ]
    );

    // Insert bgl_history log entry
    $db->insert(
        'bgl_history',
        [
            'npc' => $npcName,
            'ts' => $last_ts,
            'gamets' => $last_gamets,
            'localts' => time(),
            'data' => "$npcName stays at current location ($requestedLocation, resolved as $resolvedLocation)$intentText. Reason: {$GLOBALS["LAST_REASON"]}",
        ]
    );

    $db->insert('eventlog', [
                'ts' => $last_ts,
                'gamets' => $last_gamets + 1,
                'type' => 'innerchat',
                'data' => "($npcName's decision: stay at $resolvedLocation $intentText)",
                'sess' => "processor",
                'localts' => time(),
                'people' => $npcName,
                'location' => null,
                'party' => '',
            ]);
    // Insert actions_issued log entry
    $db->insert(
        'actions_issued',
        [
            'action' => "Idle",
            'fullcall' => "StayAtPlace:$resolvedLocation$intentSuffix",
            'actorname' => $npcName,
            'ts' => $last_ts,
            'gamets' => $last_gamets,
            'localts' => time(),
            'original' => 'backgroundaction',
        ]
    );

    if (strtolower($intent) === 'socialize') {
        if (rand(0,1)) {
            triggerNpcUpdate($npcName);
        }
    }
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

    // Insert bgl_history log entry
    $db->insert(
        'bgl_history',
        [
            'npc' => $npcName,
            'ts' => $last_ts,
            'gamets' => $last_gamets,
            'localts' => time(),
            'data' => "$npcName returns back to {$GLOBALS['PLAYER_NAME']}. Reason: {$GLOBALS['LAST_REASON']}"
        ]
    );

    // Will mark meta PENDING_DIALOGUE. When NPC reaches player, will talk to player. (triggered at addnpc)
    $npcManager = new NpcMaster();
    $meta["PENDING_DIALOGUE"] = $last_gamets;
    $npcManager->updateExtendedKeysByName($npcName, $meta);
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

        if (resolveTravelLocation($targetNpcName, $currentNpcData, $db) !== null) {
            $db->insert('eventlog', [
                'ts' => $last_ts,
                'gamets' => $last_gamets + 5,
                'type' => 'infoaction',
                'data' => "The Narrator: $npcName cannot move to $targetNpcName because it is a location, not an NPC. Use TravelTo instead.",
                'sess' => $momentum,
                'localts' => time(),
                'people' => $npcName,
                'location' => null,
                'party' => '',
            ]);
        }
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

    // Insert bgl_history log entry
    $db->insert(
        'bgl_history',
        [
            'npc' => $npcName,
            'ts' => $last_ts,
            'gamets' => $last_gamets,
            'localts' => time(),
            'data' => "$npcName moves towards $resolvedName. Reason: {$GLOBALS["LAST_REASON"]}"
        ]
    );

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

    // Insert bgl_history log entry
    $db->insert(
        'bgl_history',
        [
            'npc' => $npcName,
            'ts' => $last_ts,
            'gamets' => $last_gamets,
            'localts' => time(),
            'data' => "$npcName looks for $resolvedName. Reason: {$GLOBALS["LAST_REASON"]}"
        ]
    );


    sleep(2); // Simulate time taken to find the NPC; in a real implementation, this would be event-driven rather than a fixed sleep

    $npcLocation = $db->fetchOne("SELECT *
    FROM core_npc_master
    WHERE refid='{$targetNpc['refid']}'
    AND (metadata->'last_coords'->>'last_updated')::bigint>$last_gamets
    ORDER BY (metadata->'last_coords'->>'last_updated')::bigint DESC");

    $detectedLocation = json_decode($npcLocation["metadata"], true)["last_coords"] ?? null;

    error_log("[handleFindNPCAction] <{$targetNpc['refid']}> <{$targetNpc['name']}>, wanted location: $lastReportedLocation. Detected" . print_r($detectedLocation, true));
    if ($detectedLocation && $detectedLocation[3] == $lastReportedLocation) {

        // Check if NPC to speak to is a vender/trader. We publish stock.
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

        // Insert bgl_history log entry
        $db->insert(
            'bgl_history',
            [
                'npc' => $npcName,
                'ts' => $last_ts,
                'gamets' => $last_gamets,
                'localts' => time(),
                'data' => "$npcName moves toward $resolvedName. Reason: {$GLOBALS["LAST_REASON"]}"
            ]
        );


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

    // Check first if name comes in the form of "NPC NAME" or "NPCname:refid);
    if (strpos($targetNpcName, ':') !== false) {
        $parts = explode(':', $targetNpcName);
        $targetNpcName = trim($parts[0]);
        $targetRefid = trim($parts[1]);
        error_log("[handleSpeakToAction] Target NPC name and refid provided: $targetNpcName, refid: $targetRefid");
    } else {
        error_log("[handleSpeakToAction] Target NPC name provided: $targetNpcName");
        $targetRefid = "0";
    }

    $targetNpc = resolveNpcByName($targetNpcName, $db);

    if ($targetNpc === null) {
        error_log("[handleSpeakToAction] Target NPC not found: $targetNpcName, trying to create profile.");
        // This can happen if player didn't visit the NPC yet, so the NPC is not in the core_npc_master table. 
        // Try to create profile
        $retVal = createProfile($targetNpcName, [], false, $targetNpcName); //1-NEW PROFILE, 2-PROFILE ALREADY EXISTS
        $resolvedName = $targetNpcName;
        $targetNpc['name'] = $resolvedName;
        $targetNpc['refid'] = $targetRefid;
    }

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
        // Give the game a moment to process the vendor container request before proceeding with dialogue.
        sleep(1 * sizeof($vendorFactionsNpcBelongs));
    }

    $vendorFactionsNpcBelongs = $db->fetchAll("SELECT name,formid,vendor_cont,stock,gold,player_rank FROM factions WHERE
        formid IN ('" . implode("','", $factionsArray) . "') and vendor_cont is not null and vendor_cont<>'00000000'");

    error_log("[handleSpeakToAction] Query to obtain vendor faction chest: SELECT name,formid,vendor_cont,stock,gold,player_rank FROM factions WHERE
        formid IN ('" . implode("','", $factionsArray) . "') and vendor_cont is not null and vendor_cont<>'00000000'");

    if ($vendorFactionsNpcBelongs && sizeof($vendorFactionsNpcBelongs) > 0 && !empty($vendorFactionsNpcBelongs[0]['stock'])) {
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

        $npcMaster = new NpcMaster();
        $targetNpcData = $npcMaster->getByName($resolvedName);
        $extdata=$npcMaster->getExtendedData($targetNpcData);
        

        $targetNpcDataBasicProfile="";

        if (isset($extdata['middle_term_memory'])) {
            $middleTermMemory = end($extdata['middle_term_memory']);
            
        }
        $targetNpcDataBasicProfile.= "Name: {$targetNpcData['npc_name']}\n";
        $targetNpcDataBasicProfile.= "Race: {$targetNpcData['race']}\n";
        $targetNpcDataBasicProfile.= "Gender: {$targetNpcData['gender']}\n";        
        $targetNpcDataBasicProfile.= "Bio: {$targetNpcData['npc_static_bio']}\n";
        $targetNpcDataBasicProfile.= "Personality: {$targetNpcData['personality']}\n";
        $targetNpcDataBasicProfile.= "Occupation: {$targetNpcData['occupation']}\n";
        $targetNpcDataBasicProfile.= "Appearance: {$targetNpcData['appearance']}\n";
        $targetNpcDataBasicProfile.= "Skills: {$targetNpcData['skills']}\n";
        $targetNpcDataBasicProfile.= "Speechstyle: {$targetNpcData['speechstyle']}\n";
        $targetNpcDataBasicProfile.= "Goals: {$targetNpcData['goals']}\n";
        $targetNpcDataBasicProfile.= "Memories: {$middleTermMemory}\n";
        
        $contextBlock = !empty($dynamicBiography)
            ? "<character_sheet>\n{$npcName}:\n{$dynamicBiography}\n</character_sheet>\n\n"
            : '';

        $contextBlock .= !empty($targetNpcDataBasicProfile)
            ? "<character_sheet>\n{$resolvedName}:\n{$targetNpcDataBasicProfile}\n</character_sheet>\n\n"
            : '';

        $historyBlock = !empty($contextHistory)
            ? "<context_history>\n{$contextHistory}\n$stockString\n</context_history>\n\n"
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
                    . "(at this point {$GLOBALS["HERIKA_NAME"]} thinks to himsel/herself:{$GLOBALS['LAST_REASON']})\n"
                    . "Write a brief, immersive dialogue between $npcName and $resolvedName.\n"
                    . "The conversation is initiated by $npcName.\n"
                    . "The dialogue must be consistent with the context_history above.\n"
                    . "Format each line exactly as:\n"
                    . "$npcName: ...\n$resolvedName: ...\n"
                    . 'Keep it to 3–5 exchanges total.'
                    . "When generating a dialogue that includes a transaction involving items, do not depict the actual exchange. "
                    . "The dialogue should conclude with Subject A and Subject B mutually agreeing or expressing their intention to "
                    . "perform the transaction next. Any transfer of items must occur only in the subsequent step, not within the generated dialogue."
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

        // Insert bgl_history log entry
        $db->insert(
            'bgl_history',
            [
                'npc' => $npcName,
                'ts' => $last_ts,
                'gamets' => $last_gamets + 20,
                'localts' => time(),
                'data' => "$npcName has a conversation with $resolvedName\nDialogue: $dialogueBuffer\nReason: {$GLOBALS["LAST_REASON"]}"
            ]
        );
    }

    return true;
}

/**
 * Handle BuyItem / SellItem action — instruct the NPC to buy from or sell to another NPC.
 *
 * @param string $tradeType      'BuyItem' or 'SellItem'
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
    $sourceRefHexString = strtolower(convertSignedToUnsignedHex(hexdec($currentNpcData['refid'])));
    $skyrimCmd = new SkyrimCommandBuilder();

    $transactions = array_values(array_filter(array_map('trim', explode(',', $actionArgument)), static function ($entry) {
        return $entry !== '';
    }));

    if (empty($transactions)) {
        error_log("[handleTradeItemsAction] [$tradeType] Empty actionArgument: $actionArgument");
        return false;
    }

    $processed = 0;
    $targetRefsToRefresh = [];

    foreach ($transactions as $transactionRaw) {
        $args = array_map('trim', explode(':', $transactionRaw));
        $targetNpcName = $args[0] ?? '';
        $itemId = $args[1] ?? '';
        $count = isset($args[2]) ? (int) $args[2] : 0;
        $gold = isset($args[3]) ? (int) $args[3] : 0;

        if ($targetNpcName === '' || $itemId === '' || $count <= 0 || $gold < 0) {
            error_log("[handleTradeItemsAction] [$tradeType] Malformed transaction skipped: $transactionRaw");
            continue;
        }

        $targetNpc = resolveNpcByName($targetNpcName, $db);
        if ($targetNpc === null) {
            error_log("[handleTradeItemsAction] [$tradeType] Target NPC not found: $targetNpcName");
            continue;
        }

        $resolvedName = $targetNpc['name'];
        $targetRefHexString = strtolower(convertSignedToUnsignedHex(hexdec($targetNpc['refid'])));
        $itemId = preg_replace('/^0x/i', '', strtolower($itemId));

        if ($tradeType === 'BuyItem') {
            // Buyer receives item and pays gold; seller loses item and receives gold.
            $json = $skyrimCmd->ObjectReference->AddItem($sourceRefHexString, "0x$itemId", $count, true);
            $skyrimCmd->send(cmd: $json);

            $json = $skyrimCmd->ObjectReference->RemoveItem($sourceRefHexString, "0x0000000f", $gold, true);
            $skyrimCmd->send(cmd: $json);

            $json = $skyrimCmd->ObjectReference->RemoveItem($targetRefHexString, "0x$itemId", $count, true);
            $skyrimCmd->send(cmd: $json);

            $json = $skyrimCmd->ObjectReference->AddItem($targetRefHexString, "0x0000000f", $gold, true);
            $skyrimCmd->send(cmd: $json);
        } else {
            // Seller gives item and receives gold; buyer gains item and spends gold.
            $json = $skyrimCmd->ObjectReference->RemoveItem($sourceRefHexString, "0x$itemId", $count, true);
            $skyrimCmd->send(cmd: $json);

            $json = $skyrimCmd->ObjectReference->AddItem($sourceRefHexString, "0x0000000f", $gold, true);
            $skyrimCmd->send(cmd: $json);

            $json = $skyrimCmd->ObjectReference->AddItem($targetRefHexString, "0x$itemId", $count, true);
            $skyrimCmd->send(cmd: $json);

            $json = $skyrimCmd->ObjectReference->RemoveItem($targetRefHexString, "0x0000000f", $gold, true);
            $skyrimCmd->send(cmd: $json);
        }

        $itemName = getNameForItemReference(strtoupper($itemId));
        if ($itemName) {
            $itemNameResolved = "($count {$itemName})";
        } else {
            $itemNameResolved = '';
        }

        $db->insert('eventlog', [
            'ts' => $last_ts,
            'gamets' => $last_gamets + 10,
            'type' => 'innerchat',
            'data' => "The Narrator: $npcName " . ($tradeType === 'BuyItem' ? 'buys items from' : 'sells items to') . " $resolvedName $itemNameResolved. Inventories updated!",
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

        $db->insert(
            'bgl_history',
            [
                'npc' => $npcName,
                'ts' => $last_ts,
                'gamets' => $last_gamets,
                'localts' => time(),
                'data' => "$npcName is trading with $resolvedName (tradeType:$tradeType, item:$itemId, count:$count, gold:$gold), item description:$itemNameResolved\nReason: {$GLOBALS["LAST_REASON"]}"
            ]
        );

        $targetRefsToRefresh[$targetRefHexString] = true;
        $processed++;
    }

    if ($processed === 0) {
        error_log("[handleTradeItemsAction] [$tradeType] No valid transactions processed: $actionArgument");
        return false;
    }

    // Schedule inventory updates for source and all unique targets after processing.
    $db->insert('responselog', [
        'localts' => time() + 10,
        'sent' => 0,
        'actor' => 'rolemaster',
        'text' => '',
        'action' => "rolecommand|BackgroundCmd@$sourceRefHexString@UpdateInventory",
        'tag' => '',
    ]);

    foreach (array_keys($targetRefsToRefresh) as $targetRefHexString) {
        $db->insert('responselog', [
            'localts' => time() + 10,
            'sent' => 0,
            'actor' => 'rolemaster',
            'text' => '',
            'action' => "rolecommand|BackgroundCmd@$targetRefHexString@UpdateInventory",
            'tag' => '',
        ]);
    }

    triggerNpcUpdate($npcName);
    return true;
}