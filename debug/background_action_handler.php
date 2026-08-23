<?php


define('_LOCATION_RESOLVE_SIM_THRESHOLD', 0.74); // Minimum similarity score for location resolution
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
    $extended["background_life_last_run"] = $GLOBALS["LAST_GAMETS_BGL"] + 20; // Some actions can insert events using a future gamets up to 20


    $npcManager->updateExtendedKeysByName($npcName, $extended);
}

function updateLastActionGameTs($npcName)
{
    $npcManager = new NpcMaster();
    $extended["background_life_last_run"] = $GLOBALS["LAST_GAMETS_BGL"] + 20; // Some actions can insert events using a future gamets up to 20
    $npcManager->updateExtendedKeysByName($npcName, $extended);
}


function updateLastLLMCall($npcName)
{
    $npcManager = new NpcMaster();
    $currentData = $npcManager->getByName($npcName);
    $extended = $npcManager->getExtendedData($currentData);
    $extendedCopy["background_life_last_llm_call"] = $extended["background_life_last_llm_call"] ?? [];
    $extendedCopy["background_life_last_llm_call"][] = time();
    $extendedCopy["background_life_last_llm_call"] = array_slice($extendedCopy["background_life_last_llm_call"], -5); // keep only last 5 calls

    $extended["background_life_last_llm_call"] = $extendedCopy["background_life_last_llm_call"];

    $npcManager->updateExtendedKeysByName($npcName, $extended);
}

function markAsErrored($npcName)
{
    $npcManager = new NpcMaster();

    $extended["background_life_last_llm_call_suspended"] = true;

    $npcManager->updateExtendedKeysByName($npcName, $extended);
}

function gameIsPaused()
{
    // Check $GLOBALS["LAST_GAMETS_BGL"] against the last run on DB
    $localLastGameTs = $GLOBALS["db"]->fetchAll('SELECT max(gamets) AS last_gamets FROM eventlog');
    $lastGameTs = $localLastGameTs[0]['last_gamets'] ?? 0;
    if (($GLOBALS["LAST_GAMETS_BGL"] + 20) > $lastGameTs) {
        error_log("[BGL RUN] Game is paused. LAST_GAMETS_BGL: {$GLOBALS["LAST_GAMETS_BGL"]}, lastGameTs: $lastGameTs");
        return true; // Game is paused
    }
    return false;

}

function checkLastCallsFor($npcName)
{
    // Check if the last 6 calls were made within the last 2 minutes
    $npcManager = new NpcMaster();
    $currentData = $npcManager->getByName($npcName);
    $extended = $npcManager->getExtendedData($currentData);
    $lastCalls = $extended["background_life_last_llm_call"] ?? [];
    if (isset($extended["background_life_last_llm_call_suspended"]) && $extended["background_life_last_llm_call_suspended"] === true) {
        return true; // Suspended, treat as exceeded
    }
    $now = time();
    $recentCalls = array_filter($lastCalls, function ($ts) use ($now) {
        return ($now - $ts) <= 120; // last 2 minutes
    });
    return count($recentCalls) >= 6;
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

    $resolvedLocationInterior = checkInterior($locId['is_interior'] ?? 0) ? ' (Interior)' : '';

    if (strcasecmp($requestedLocation, 'random') === 0) {
        error_log("[handleTravelToAction] random picked: " . print_r($locId, true));
    }

    if (!empty($locId)) {
        $sim = isset($locId['sim']) ? ', sim=' . $locId['sim'] : '';
        $dist = isset($locId['dist']) ? ', dist=' . $locId['dist'] : '';
        error_log("[handleTravelToAction] requested='$requestedLocation' resolved='{$resolvedLocation}' formid='{$locId['formid']}'$sim$dist");
    }

    if (!isset($locId["formid"]) || (isset($locId['sim']) && $locId['sim'] < _LOCATION_RESOLVE_SIM_THRESHOLD)) {
        $db->insert('eventlog', [
            'ts' => $last_ts,
            'gamets' => $last_gamets + 10,
            'type' => 'innerchat',
            'data' => "The Narrator: $npcName didn't find $location. $npcName must desist from this action and choose another destination",
            'sess' => $momentum,
            'localts' => time(),
            'people' => "$npcName",
            'location' => null,
            'party' => '',
        ]);
        $db->insert(
            'bgl_history',
            [
                'npc' => $npcName,
                'ts' => $last_ts,
                'gamets' => $last_gamets,
                'localts' => time(),
                'data' => ($location == $resolvedLocation) ? "$npcName failed to travel to $location. Reason: {$GLOBALS["LAST_REASON"]}" : "$npcName starts travelling to $location (resolved as $resolvedLocation $resolvedLocationInterior). Reason: {$GLOBALS["LAST_REASON"]}",
                'category' => 'error',
            ]
        );
        triggerNpcUpdate($npcName);
        return false;
    }

    $refHexString = convertSignedToUnsignedHex(hexdec($currentNpcData["refid"]));
    $locHexString = (convertHex($locId["formid"]));

    // Use direct_destination_ref if available
    if ($locId["direct_destination_ref"] ?? false) {
        $locDecString = hexdec($locId["direct_destination_ref"]);
        if ($locDecString >= 0x80000000) {
            $locDecString -= 0x100000000;
        }
    } else {
        $locDecString = ($locId["formid"]);
    }

    error_log("Using refid $refHexString , location $locHexString");
    // Insert response log entry for travel command
    $db->insert(
        'responselog',
        [
            'localts' => time(),
            'sent' => 0,
            'actor' => "rolemaster",
            'text' => "",
            'action' => "rolecommand|BackgroundCmd@$refHexString@TravelTo/{$locDecString}",
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
            'fullcall' => "TravelTo:$resolvedLocation:{$GLOBALS["LAST_REASON"]}",
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
            'data' => ($location == $resolvedLocation) ? "$npcName starts travelling to $location. Reason: {$GLOBALS["LAST_REASON"]}" : "$npcName starts travelling to $location (resolved as $resolvedLocation $resolvedLocationInterior). Reason: {$GLOBALS["LAST_REASON"]}",
            'category' => 'travel',
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
    $resolvedLocationInterior = checkInterior($locId['is_interior'] ?? 0) ? ' (Interior)' : '';
    $intent = trim((string) $intent);
    $intentSuffix = $intent !== '' ? ":$intent" : '';
    $intentText = $intent !== '' ? " with intent '$intent'" : '';
    $previousIntent = $db->fetchOne("SELECT category FROM bgl_history WHERE npc='$npcName' ORDER BY gamets DESC LIMIT 1");
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
            'action' => "rolecommand|BackgroundCmd@$refHexString@StayAtPlace/{$locId["formid"]}/$intent",
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
            'data' => "$npcName stays at current location ($requestedLocation, resolved as $resolvedLocation $resolvedLocationInterior)$intentText. Reason: {$GLOBALS["LAST_REASON"]}",
            'category' => $intent,
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
        // If last intent was not socialize, we will trigger an update to the NPC to make it more dynamic and social.
        if (strtolower($previousIntent['category']) !== 'socialize') {
            if (rand(0, 1)) {
                triggerNpcUpdate($npcName);
            }
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
 * Handle SendLetter action for NPC background life.
 *
 * @param string $letterContent The letter body content
 * @param array $currentNpcData The NPC data array containing refid
 * @param string $npcName The NPC character name
 * @param int $last_ts The last timestamp
 * @param int $last_gamets The last game timestamp
 * @param int $momentum The current momentum/session timestamp
 * @param object $db The database connection object
 * @return bool True if letter action was successfully processed, false otherwise
 */
function handleSendLetter($letterContent, $currentNpcData, $npcName, $last_ts, $last_gamets, $momentum, $db, $connectionHandler, $dynamicBiography, $historyWithInnerThought, $lastLocation)
{
    $letterContent = trim((string) $letterContent);
    if ($letterContent === '') {
        error_log("[handleSendLetter] Empty letter content for NPC: $npcName");
        return false;
    }

    $letterStyle = loadBGLStylePrompt('background_life_letter', [
        '{HERIKA_NAME}' => $GLOBALS["HERIKA_NAME"],
        '{PLAYER_NAME}' => $GLOBALS["PLAYER_NAME"]
    ]);

    $refHexString = convertSignedToUnsignedHex(hexdec($currentNpcData["refid"]));
    $dateStringSK = convert_gamets2skyrim_long_date(DataLastKnownGameTS());
    $fullTitle = "A letter from {$GLOBALS["HERIKA_NAME"]} ($dateStringSK)";

    $contextBlock = !empty($dynamicBiography)
        ? "<character_sheet>\n{$npcName}:\n{$dynamicBiography}\n</character_sheet>\n\n"
        : '';



    $historyBlock = !empty($historyWithInnerThought)
        ? "<context_history>\n{$historyWithInnerThought}\n</context_history>\n\n"
        : '';

    $dialoguePrompt = [
        [
            'role' => 'system',
            'content' => 'You are a creative writer for the Skyrim (The Elder Scrolls) universe.'
                . " Generate a short, natural, letter from {$GLOBALS["HERIKA_NAME"]} to {$GLOBALS["PLAYER_NAME"]}  "
        ],
        [
            'role' => 'user',
            'content' => "{$contextBlock}"
                . "{$historyBlock}"
                . "(at this point {$GLOBALS["HERIKA_NAME"]} thinks to himsel/herself:{$GLOBALS['LAST_REASON']})\n"
                . "{$letterStyle}\n"
        ],
    ];

    $dialogueBuffer = $connectionHandler->fast_request($dialoguePrompt, ['MAX_TOKENS' => 512], 'backgroundlife');
    updateLastLLMCall($GLOBALS['HERIKA_NAME']);
    // This is going to create a picture with the letter.
    if ($dialogueBuffer === null || trim($dialogueBuffer) === '') {
        error_log("[handleSendLetter] Failed to generate letter content for NPC: $npcName");
        return false;
    }
    createLetter($fullTitle, $dialogueBuffer);

    // Will make plugin to download letter image to data folder, and will be stored using title's hash as name
    $db->insert(
        'responselog',
        [
            'localts' => time(),
            'sent' => 0,
            'actor' => "rolemaster",
            'text' => "",
            'action' => "rolecommand|generateLetter@$fullTitle",
            'tag' => '',
        ]
    );

    // Will make plugin to generate formid for letter, and will send vanilla courier
    $db->insert(
        'responselog',
        [
            'localts' => time(),
            'sent' => 0,
            'actor' => "rolemaster",
            'text' => "",
            'action' => "rolecommand|BackgroundCmd@$refHexString@SendNote/" . $fullTitle,
            'tag' => '',
        ]
    );

    // Log to diary/eventlog when letters are sent
    $db->insert(
        'eventlog',
        [
            'ts' => $last_ts,
            'gamets' => $last_gamets + 1,
            'type' => "innerchat",
            'data' => "The Narrator:{$GLOBALS["HERIKA_NAME"]} sent this letter to {$GLOBALS["PLAYER_NAME"]} " . "\n<letter_content>\n{$letterContent}\n</letter_content>",
            'sess' => $momentum,
            'localts' => time(),
            'people' => $GLOBALS["HERIKA_NAME"],
            'location' => $lastLocation,
            'party' => "",
        ]
    );

    // Insert bgl_history log entry
    $db->insert(
        'bgl_history',
        [
            'npc' => $GLOBALS["HERIKA_NAME"],
            'ts' => $last_ts,
            'gamets' => $last_gamets + 1,
            'localts' => time(),
            'data' => "{$GLOBALS["HERIKA_NAME"]} sends a letter to {$GLOBALS["PLAYER_NAME"]}",
            'category' => 'letter',
        ]
    );

    $db->insert(
        'diarylog',
        [
            'ts' => $last_ts,
            'gamets' => $last_gamets + 5,
            'topic' => "Sent Letter",
            'content' => $letterContent,
            'tags' => "backgroundlife",
            'people' => $GLOBALS["HERIKA_NAME"],
            'location' => $lastLocation,
            'sess' => $momentum,
            'localts' => time(),
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
            'data' => "$npcName returns back to {$GLOBALS['PLAYER_NAME']}. Reason: {$GLOBALS['LAST_REASON']}",
            'category' => 'return_home',
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
        $locationCandidate=resolveTravelLocation($targetNpcName, $currentNpcData, $db);

        if ( $locationCandidate && isset($locationCandidate["sim"]) && $locationCandidate["sim"] > _LOCATION_RESOLVE_SIM_THRESHOLD) {
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
            $db->insert(
                'bgl_history',
                [
                    'npc' => $npcName,
                    'ts' => $last_ts,
                    'gamets' => $last_gamets,
                    'localts' => time(),
                    'data' => " $npcName cannot move to $targetNpcName because it is a location, not an NPC. Use TravelTo instead.",
                    'category' => 'error',
                ]
            );
            triggerNpcUpdate($npcName);
        } else {
            $db->insert('eventlog', [
                'ts' => $last_ts,
                'gamets' => $last_gamets + 10,
                'type' => 'innerchat',
                'data' => "The Narrator: $npcName didn't find any $targetNpcName. $npcName desists from this action and continue with normal life",
                'sess' => $momentum,
                'localts' => time(),
                'people' => "$npcName",
                'location' => null,
                'party' => '',
            ]);

            $db->insert(
                'bgl_history',
                [
                    'npc' => $npcName,
                    'ts' => $last_ts,
                    'gamets' => $last_gamets,
                    'localts' => time(),
                    'data' => "$npcName didn't find any $targetNpcName. $npcName desists from this action and continue with normal life",
                    'category' => 'error',
                ]
            );
            triggerNpcUpdate($npcName);
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
            'data' => "$npcName moves towards $resolvedName. Reason: {$GLOBALS["LAST_REASON"]}",
            'category' => 'move',
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
        $db->insert('eventlog', [
            'ts' => $last_ts,
            'gamets' => $last_gamets + 10,
            'type' => 'innerchat',
            'data' => "The Narrator: $npcName didn't find $targetNpcName. $npcName desists from this action and continue with normal life",
            'sess' => $momentum,
            'localts' => time(),
            'people' => "$npcName",
            'location' => null,
            'party' => '',
        ]);
        $db->insert(
            'bgl_history',
            [
                'npc' => $npcName,
                'ts' => $last_ts,
                'gamets' => $last_gamets,
                'localts' => time(),
                'data' => "$npcName didn't find $targetNpcName. $npcName desists from this action and continue with normal life",
                'category' => 'error',
            ]
        );
        triggerNpcUpdate($npcName);
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
            'data' => "$npcName looks for $resolvedName. Reason: {$GLOBALS["LAST_REASON"]}",
            'category' => 'find',
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
                'data' => "$npcName moves toward $resolvedName. Reason: {$GLOBALS["LAST_REASON"]}",
                'category' => 'move',
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

        $db->insert(
            'bgl_history',
            [
                'npc' => $npcName,
                'ts' => $last_ts,
                'gamets' => $last_gamets,
                'localts' => time(),
                'data' => "$npcName could not find $resolvedName. (hint: Seems like $resolvedName is at {$detectedLocation[3]} {$detectedLocation["state"]}. Use action MoveTo:$resolvedName to move $npcName near to $resolvedName at that location) ",
                'category' => 'error',
            ]
        );

        triggerNpcUpdate($npcName); // Force NPC to update its background life data on the next mid-term check, which should lead it to discover the new location and update accordingly.
    } else {
        error_log("[handleFindNPCAction] $npcName could not find $resolvedName. No recent location data available.");

        $db->insert('eventlog', [
            'ts' => time(),
            'gamets' => $last_gamets + 20,
            'type' => 'infoaction',
            'data' => "The Narrator: Despite searching, $npcName could not find any trace of $resolvedName. $npcName desists from this action and continue with other tasks",
            'sess' => $momentum,
            'localts' => time(),
            'people' => $npcName,
            'location' => null,
            'party' => '',
        ]);

        $db->insert(
            'bgl_history',
            [
                'npc' => $npcName,
                'ts' => time(),
                'gamets' => $last_gamets,
                'localts' => time(),
                'data' => "$npcName could not find any trace of $resolvedName. $npcName should desist from this action and continue with other tasks",
                'category' => 'error',
            ]
        );
        $extdata=$npcMaster->getExtendedData($currentNpcData);
        triggerNpcUpdate($npcName, ++$extdata["background_life_last_updated_ec"]); // Force NPC to update its background life data on the next mid-term check, which should lead it to discover the new location and update accordingly.
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

    $checkResponseLimitTs = time();
    if ($vendorFactionsNpcBelongs) {
        foreach ($vendorFactionsNpcBelongs as $vendorFaction) {
            $skyrimCmd = new SkyrimCommandBuilder();
            $json = $skyrimCmd->Faction->GetVendorFactionContainer("0x{$vendorFaction["formid"]}");
            $skyrimCmd->send(cmd: $json);
        }
        // Give the game a moment to process the vendor container request before proceeding with dialogue.
        sleep(1 * sizeof($vendorFactionsNpcBelongs));

        // TO-DO change this to a more robust check to ensure the vendor container data has been received before proceeding.
        // Add updated_gamests column to factions table, and check if updated_gamets > last_gamets before proceeding with dialogue.
        $maxRetryCount = 5;
        $retryCount = 0;
        while ($retryCount < $maxRetryCount) {
            $vendorFactionsNpcBelongs = $db->fetchAll("SELECT name,formid,vendor_cont,stock,gold,player_rank FROM factions WHERE
            formid IN ('" . implode("','", $factionsArray) . "') and vendor_cont is not null and vendor_cont<>'00000000' and localts>$checkResponseLimitTs");
            if (sizeof($vendorFactionsNpcBelongs) == 0) {
                error_log("[handleSpeakToAction] Vendor faction container data not received in time for $resolvedName. Proceeding without stock information.");
                sleep(1);
            }
            if (!gameIsPaused())
                $retryCount++;
            sleep(1);
        }
    }

    $vendorFactionsNpcBelongs = $db->fetchAll("SELECT name,formid,vendor_cont,stock,gold,player_rank FROM factions WHERE
        formid IN ('" . implode("','", $factionsArray) . "') and vendor_cont is not null and vendor_cont<>'00000000'");

    error_log("[handleSpeakToAction] Query to obtain vendor faction chest: SELECT name,formid,vendor_cont,stock,gold,player_rank FROM factions WHERE formid IN ('" . implode("','", $factionsArray) . "') and vendor_cont is not null and vendor_cont<>'00000000'");

    if ($vendorFactionsNpcBelongs && sizeof($vendorFactionsNpcBelongs) > 0 && !empty($vendorFactionsNpcBelongs[0]['stock'])) {
        $stockString = " $resolvedName can sell these items: ";
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
        'people' => "|$npcName|$resolvedName|",
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
        $extdata = $npcMaster->getExtendedData($targetNpcData);


        $targetNpcDataBasicProfile = "";

        if (isset($extdata['middle_term_memory'])) {
            $middleTermMemory = end($extdata['middle_term_memory']);

        }
        $targetNpcDataBasicProfile .= "Name: {$targetNpcData['npc_name']}\n";
        $targetNpcDataBasicProfile .= "Race: {$targetNpcData['race']}\n";
        $targetNpcDataBasicProfile .= "Gender: {$targetNpcData['gender']}\n";
        $targetNpcDataBasicProfile .= "Bio: {$targetNpcData['npc_static_bio']}\n";
        $targetNpcDataBasicProfile .= "Personality: {$targetNpcData['personality']}\n";
        $targetNpcDataBasicProfile .= "Occupation: {$targetNpcData['occupation']}\n";
        $targetNpcDataBasicProfile .= "Appearance: {$targetNpcData['appearance']}\n";
        $targetNpcDataBasicProfile .= "Skills: {$targetNpcData['skills']}\n";
        $targetNpcDataBasicProfile .= "Speechstyle: {$targetNpcData['speechstyle']}\n";
        $targetNpcDataBasicProfile .= "Goals: {$targetNpcData['goals']}\n";
        $targetNpcDataBasicProfile .= "Memories: {$middleTermMemory}\n";

        $contextBlock = !empty($dynamicBiography)
            ? "<character_sheet>\n{$npcName}:\n{$dynamicBiography}\n</character_sheet>\n\n"
            : '';

        $contextBlock .= !empty($targetNpcDataBasicProfile)
            ? "<character_sheet>\n{$resolvedName}:\n{$targetNpcDataBasicProfile}\n</character_sheet>\n\n"
            : '';

        foreach (DataLastDataExpandedFor($targetNpcData['npc_name'], -10) as $row) {
            $historicTarget[] = $row["content"];

        }
        if (empty($historicTarget)) {
            $contextTargetHistory = "";
        } else
            $contextTargetHistory = implode("\n", $historicTarget);


        $targetHistoryBlock = !empty($historicTarget)
            ? "<context_history_target>\n{$targetNpcData['npc_name']}'s point of view history:\n{$contextTargetHistory}\n</context_history_target>\n\n"
            : '';

        $historyBlock = !empty($contextHistory)
            ? "<context_history>\n{$targetNpcData['npc_name']}'s point of view history:\n{$contextHistory}\n$stockString\n</context_history>\n\n"
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
                'content' => "{$contextBlock}\n" . ($targetHistoryBlock ? $targetHistoryBlock : '')
                    . "{$historyBlock}"
                    . "(at this point {$GLOBALS["HERIKA_NAME"]} thinks to himsel/herself:{$GLOBALS['LAST_REASON']})\n"
                    . "Write a brief, immersive dialogue between $npcName and $resolvedName.\n"
                    . "The conversation is initiated by $npcName.\n"
                    . "The dialogue must be consistent with the context_history above.\n"
                    . "Format each line exactly as:\n"
                    . "$npcName: ...\n$resolvedName: ...\n"
                    . 'Keep it to 3–5 exchanges total.'
                    . "Note: When generating a dialogue, if dialogue includes a transaction involving items, do not depict the actual exchange. "
                    . "The dialogue should conclude with Subject A and Subject B mutually agreeing or expressing their intention to "
                    . "perform the transaction next. Any transfer of items must occur only in the subsequent step, not within the generated dialogue."
            ],
        ];

        $dialogueBuffer = $connectionHandler->fast_request($dialoguePrompt, ['MAX_TOKENS' => 512], 'backgroundlife');
        updateLastLLMCall($GLOBALS["HERIKA_NAME"]);

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
                'data' => "$npcName has a conversation with $resolvedName\nDialogue: $dialogueBuffer\nReason: {$GLOBALS["LAST_REASON"]}",
                'category' => 'dialogue',
            ]
        );
    }

    return true;
}

/**
 * Handle GiveGoldTo action — instruct the NPC to give gold to one or more NPCs.
 *
 * Action argument format:
 *   "Target NPC:gold_amount,Another NPC:gold_amount"
 *
 * @param string $actionArgument Raw action argument payload
 * @param array  $currentNpcData Acting NPC data (must contain refid)
 * @param string $npcName        Acting NPC display name
 * @param int    $last_ts        Last wall-clock timestamp
 * @param int    $last_gamets    Last in-game timestamp
 * @param int    $momentum       Session timestamp
 * @param object $db             Database connection
 * @return bool  True when at least one transfer is processed, false otherwise
 */
function handleGiveGoldToAction($actionArgument, $currentNpcData, $npcName, $last_ts, $last_gamets, $momentum, $db)
{
    $sourceRefHexString = strtolower(convertSignedToUnsignedHex(hexdec($currentNpcData['refid'])));
    $skyrimCmd = new SkyrimCommandBuilder();

    $transfers = array_values(array_filter(array_map('trim', explode(',', (string) $actionArgument)), static function ($entry) {
        return $entry !== '';
    }));

    if (empty($transfers)) {
        error_log("[handleGiveGoldToAction] Empty actionArgument: $actionArgument");
        return false;
    }

    $processed = 0;
    $resolvedTargets = [];
    $targetRefsToRefresh = [];

    foreach ($transfers as $transferRaw) {
        $args = array_map('trim', explode(':', $transferRaw));
        $targetNpcName = $args[0] ?? '';
        $gold = isset($args[1]) ? (int) $args[1] : 0;

        if ($targetNpcName === '' || $gold <= 0) {
            error_log("[handleGiveGoldToAction] Malformed transfer skipped: $transferRaw");
            continue;
        }

        $targetNpc = resolveNpcByName($targetNpcName, $db);
        if ($targetNpc === null) {
            error_log("[handleGiveGoldToAction] Target NPC not found: $targetNpcName");
            continue;
        }

        $resolvedName = $targetNpc['name'];
        $targetRefHexString = strtolower(convertSignedToUnsignedHex(hexdec($targetNpc['refid'])));

        // Source loses gold, target gains gold.
        $json = $skyrimCmd->ObjectReference->RemoveItem($sourceRefHexString, "0x0000000f", $gold, true);
        $skyrimCmd->send(cmd: $json);

        $json = $skyrimCmd->ObjectReference->AddItem($targetRefHexString, "0x0000000f", $gold, true);
        $skyrimCmd->send(cmd: $json);

        $db->insert('eventlog', [
            'ts' => $last_ts,
            'gamets' => $last_gamets + 10,
            'type' => 'innerchat',
            'data' => "The Narrator: $npcName gives $gold gold to $resolvedName. Inventories updated!.Reason: {$GLOBALS["LAST_REASON"]}",
            'sess' => $momentum,
            'localts' => time(),
            'people' => "|$npcName|$resolvedName|",
            'location' => null,
            'party' => '',
        ]);

        $db->insert('actions_issued', [
            'action' => 'GiveGoldTo',
            'fullcall' => "GiveGoldTo:$resolvedName:$gold",
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
                'data' => "$npcName gives $gold gold to $resolvedName. Reason: {$GLOBALS["LAST_REASON"]}",
                'category' => 'give',
            ]
        );

        $resolvedTargets[] = "$resolvedName:$gold";
        $targetRefsToRefresh[$targetRefHexString] = true;
        $processed++;
    }

    if ($processed === 0) {
        error_log("[handleGiveGoldToAction] No valid transfers processed: $actionArgument");
        $db->insert('eventlog', [
            'ts' => $last_ts,
            'gamets' => $last_gamets + 10,
            'type' => 'innerchat',
            'data' => "The Narrator: $npcName tried to give gold away, but no valid transfers were processed. $npcName desists from this action and continue with normal life",
            'sess' => $momentum,
            'localts' => time(),
            'people' => "|$npcName|",
            'location' => null,
            'party' => '',
        ]);

        $db->insert(
            'bgl_history',
            [
                'npc' => $npcName,
                'ts' => $last_ts,
                'gamets' => $last_gamets,
                'localts' => time(),
                'data' => "$npcName tried to give gold away, but no valid transfers were processed. Reason: {$GLOBALS["LAST_REASON"]}",
                'category' => 'error',
            ]
        );
        triggerNpcUpdate($npcName);
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

    error_log('[handleGiveGoldToAction] Processed transfers: ' . implode(', ', $resolvedTargets));
    triggerNpcUpdate($npcName);
    return true;
}

/**
 * Handle SellService action for NPC background life.
 *
 * Sells a service to one or more NPCs. No inventory item is moved;
 * only gold changes hands: the buyer loses gold and the service provider
 * (the acting NPC) receives it.
 *
 * Format: SellService:<NPC name>:<service_description>:<total_gold_amount>,...
 *
 * @param string $actionArgument Comma-separated service entries
 * @param array  $currentNpcData Acting NPC data (must contain refid)
 * @param string $npcName        Acting NPC display name
 * @param int    $last_ts        Last wall-clock timestamp
 * @param int    $last_gamets    Last in-game timestamp
 * @param int    $momentum       Session timestamp
 * @param object $db             Database connection
 * @return bool  True when at least one service is processed, false otherwise
 */
function handleSellServiceAction($actionArgument, $currentNpcData, $npcName, $last_ts, $last_gamets, $momentum, $db)
{
    $sourceRefHexString = strtolower(convertSignedToUnsignedHex(hexdec($currentNpcData['refid'])));
    $skyrimCmd = new SkyrimCommandBuilder();

    $entries = array_values(array_filter(array_map('trim', explode(',', (string) $actionArgument)), static function ($entry) {
        return $entry !== '';
    }));

    if (empty($entries)) {
        error_log("[handleSellServiceAction] Empty actionArgument: $actionArgument");
        return false;
    }

    $processed = 0;
    $resolvedTargets = [];
    $targetRefsToRefresh = [];

    foreach ($entries as $entryRaw) {
        // Format: TargetName:service_description:gold
        // Service description may contain colons, so split from the ends.
        $args = array_map('trim', explode(':', $entryRaw));
        $targetNpcName = $args[0] ?? '';
        $gold = isset($args[count($args) - 1]) ? (int) $args[count($args) - 1] : 0;
        $service = trim(implode(':', array_slice($args, 1, -1)));

        if ($targetNpcName === '' || $service === '' || $gold <= 0) {
            error_log("[handleSellServiceAction] Malformed entry skipped: $entryRaw");
            continue;
        }

        $targetNpc = resolveNpcByName($targetNpcName, $db);
        if ($targetNpc === null) {
            error_log("[handleSellServiceAction] Target NPC not found: $targetNpcName");
            continue;
        }

        $resolvedName = $targetNpc['name'];
        $targetRefHexString = strtolower(convertSignedToUnsignedHex(hexdec($targetNpc['refid'])));

        // Buyer pays gold; service provider receives gold.
        $json = $skyrimCmd->ObjectReference->RemoveItem($targetRefHexString, "0x0000000f", $gold, true);
        $skyrimCmd->send(cmd: $json);

        $json = $skyrimCmd->ObjectReference->AddItem($sourceRefHexString, "0x0000000f", $gold, true);
        $skyrimCmd->send(cmd: $json);

        $db->insert('eventlog', [
            'ts' => $last_ts,
            'gamets' => $last_gamets + 10,
            'type' => 'innerchat',
            'data' => "The Narrator: $npcName sells a service ($service) to $resolvedName for $gold gold. Inventories updated!.Reason: {$GLOBALS["LAST_REASON"]}",
            'sess' => $momentum,
            'localts' => time(),
            'people' => "|$npcName|$resolvedName|",
            'location' => null,
            'party' => '',
        ]);

        $db->insert('actions_issued', [
            'action' => 'SellService',
            'fullcall' => "SellService:$resolvedName:$service:$gold",
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
                'data' => "$npcName sells a service ($service) to $resolvedName for $gold gold. Reason: {$GLOBALS["LAST_REASON"]}",
                'category' => 'trade',
            ]
        );

        $resolvedTargets[] = "$resolvedName:$service:$gold";
        $targetRefsToRefresh[$targetRefHexString] = true;
        $processed++;
    }

    if ($processed === 0) {
        error_log("[handleSellServiceAction] No valid entries processed: $actionArgument");
        $db->insert('eventlog', [
            'ts' => $last_ts,
            'gamets' => $last_gamets + 10,
            'type' => 'innerchat',
            'data' => "The Narrator: $npcName tried to sell a service, but no valid transactions were processed. $npcName desists from this action and continue with normal life",
            'sess' => $momentum,
            'localts' => time(),
            'people' => "|$npcName|",
            'location' => null,
            'party' => '',
        ]);

        $db->insert(
            'bgl_history',
            [
                'npc' => $npcName,
                'ts' => $last_ts,
                'gamets' => $last_gamets,
                'localts' => time(),
                'data' => "$npcName tried to sell a service, but no valid transactions were processed. Reason: {$GLOBALS["LAST_REASON"]}",
                'category' => 'error',
            ]
        );
        triggerNpcUpdate($npcName);
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

    error_log('[handleSellServiceAction] Processed services: ' . implode(', ', $resolvedTargets));
    triggerNpcUpdate($npcName);
    return true;
}

/**
 * Handle BuyItem / SellItem / GiveItemTo action.
 *
 * @param string $tradeType      'BuyItem', 'SellItem', or 'GiveItemTo'
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
    if ($tradeType !== 'BuyItem' && $tradeType !== 'SellItem' && $tradeType !== 'GiveItemTo') {
        error_log("[handleTradeItemsAction] Unsupported tradeType: $tradeType");
        return false;
    }

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
    $targetNpcNames="";
    foreach ($transactions as $transactionRaw) {
        $args = array_map('trim', explode(':', $transactionRaw));
        $targetNpcName = $args[0] ?? '';
        $itemId = $args[1] ?? '';
        $count = isset($args[2]) ? (int) $args[2] : 0;
        // GiveItemTo accepts both formats:
        // - GiveItemTo:Target:itemid:count
        // - GiveItemTo:Target:itemid:count:0
        $gold = isset($args[3]) ? (int) $args[3] : 0;

        
        $isMalformed = ($targetNpcName === '' || $itemId === '' || $count <= 0);
        $itemId = preg_replace('/^0x/i', '', strtolower($itemId));
        if ($tradeType !== 'GiveItemTo' && $gold <= 0) {

            $dbGoldValueRow = $db->fetchOne("select price from market_cache where UPPER(baseid)=UPPER('$itemId')");
            if ($dbGoldValueRow && isset($dbGoldValueRow['price'])) {
                // Price correction: If the gold value is zero, we can attempt to correct it by fetching the price from the market_cache table.
                $gold = (int) $dbGoldValueRow['price'] * $count;
                error_log("[handleTradeItemsAction] [$tradeType] Corrected zero price for: $itemId to $gold (count: $count)");
                $isMalformed = false;
            } else {
                error_log("[handleTradeItemsAction] [$tradeType] Could not determine gold value for item: $itemId. Skipping transaction.");
                $isMalformed = true;
            }
        }

        if ($isMalformed) {
            error_log("[handleTradeItemsAction] [$tradeType] Malformed transaction skipped: $transactionRaw");
            $db->insert(
                'bgl_history',
                [
                    'npc' => $npcName,
                    'ts' => $last_ts,
                    'gamets' => $last_gamets,
                    'localts' => time(),
                    'data' => "$npcName tried trading, but the transaction was malformed. <$transactionRaw>, Reason: {$GLOBALS["LAST_REASON"]}",
                    'category' => 'warning',
                ]
            );
            continue;
        }

        $targetNpc = resolveNpcByName($targetNpcName, $db);
        $targetNpcNames.= $targetNpcName;
        if ($targetNpc === null) {
            error_log("[handleTradeItemsAction] [$tradeType] Target NPC not found: $targetNpcName");
            $db->insert(
                'bgl_history',
                [
                    'npc' => $npcName,
                    'ts' => $last_ts,
                    'gamets' => $last_gamets,
                    'localts' => time(),
                    'data' => "$npcName tried trading, but the target NPC '$targetNpcName' was not found. Reason: {$GLOBALS["LAST_REASON"]}",
                    'category' => 'warning',
                ]
            );
            continue;
        }

        $resolvedName = $targetNpc['name'];
        $targetRefHexString = strtolower(convertSignedToUnsignedHex(hexdec($targetNpc['refid'])));
        

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
        } else if ($tradeType === 'GiveItemTo') {
            // Direct item handoff without any gold exchange.
            $json = $skyrimCmd->ObjectReference->RemoveItem($sourceRefHexString, "0x$itemId", $count, true);
            $skyrimCmd->send(cmd: $json);

            $json = $skyrimCmd->ObjectReference->AddItem($targetRefHexString, "0x$itemId", $count, true);
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
            'data' => "The Narrator: $npcName is trading with $resolvedName (tradeType:$tradeType, item:$itemId, count:$count, gold:" . ($tradeType === 'GiveItemTo' ? 0 : $gold) . "), item description:$itemNameResolved\nReason: \"{$GLOBALS["LAST_REASON"]}\"",
            'sess' => $momentum,
            'localts' => time(),
            'people' => "|$npcName|$resolvedName|",
            'location' => null,
            'party' => '',
        ]);

        $db->insert('actions_issued', [
            'action' => $tradeType,
            'fullcall' => "$tradeType:$resolvedName:$itemId:$count:" . ($tradeType === 'GiveItemTo' ? 0 : $gold),
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
                'data' => "$npcName is trading with $resolvedName (tradeType:$tradeType, item:$itemId, count:$count, gold:" . ($tradeType === 'GiveItemTo' ? 0 : $gold) . "), item description:$itemNameResolved\nReason: {$GLOBALS["LAST_REASON"]}",
                'category' => 'trade',
            ]
        );

        $targetRefsToRefresh[$targetRefHexString] = true;
        $processed++;
    }

    if ($processed === 0) {
        error_log("[handleTradeItemsAction] [$tradeType] No valid transactions processed: $actionArgument");
        $db->insert('eventlog', [
            'ts' => $last_ts,
            'gamets' => $last_gamets + 10,
            'type' => 'innerchat',
            'data' => "The Narrator: $npcName tried to $tradeType with $targetNpcName, but no valid transactions were processed. $npcName desists from trading and continue with normal life",
            'sess' => $momentum,
            'localts' => time(),
            'people' => "|$npcName|$resolvedName|",
            'location' => null,
            'party' => '',
        ]);
        $db->insert(
            'bgl_history',
            [
                'npc' => $npcName,
                'ts' => $last_ts,
                'gamets' => $last_gamets,
                'localts' => time(),
                'data' => "$npcName $npcName tried to $tradeType with $targetNpcName, but no valid transactions were processed. <$actionArgument>",
                'category' => 'error',
            ]
        );
        triggerNpcUpdate($npcName);
        return false;
    } else {
        $db->insert('eventlog', [
            'ts' => $last_ts,
            'gamets' => $last_gamets + 11,
            'type' => 'innerchat',
            'data' => "The Narrator: $npcName completed the transaction $tradeType with $targetNpcNames",
            'sess' => $momentum,
            'localts' => time(),
            'people' => "|$npcName|$resolvedName|",
            'location' => null,
            'party' => '',
        ]);
        $db->insert(
            'bgl_history',
            [
                'npc' => $npcName,
                'ts' => $last_ts,
                'gamets' => $last_gamets,
                'localts' => time(),
                'data' => "$npcName completed $processed transactions  out of " . sizeof($transactions) . ". Reason: {$GLOBALS["LAST_REASON"]}",
                'category' => 'trade',
            ]
        );
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