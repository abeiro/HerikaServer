<?php

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
function handleTravelToAction($location, $currentNpcData, $npcName, $last_ts, $last_gamets, $momentum, $locationSrc, $db) {
    $cnLocation = $db->escape($location);
    if ($cnLocation=="random") {
        $locId = $db->fetchOne("select name,region,hold,formid from locations order by case when name=region then 1 else 0 end desc, random()");
        error_log("[handleTravelToAction] random picked: ".print_r($locId,true));
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
            'sent'    => 0,
            'actor'   => "rolemaster",
            'text'    => "",
            'action'  => "rolecommand|BackgroundCmd@$refHexString@TravelTo/{$locId["formid"]}",
            'tag'     => '',
        ]
    );
    
    // Insert event log entry
    $db->insert(
        'eventlog',
        [
            'ts'       => $last_ts,
            'gamets'   => $last_gamets + 10,
            'type'     => "infoaction",
            'data'     => "The Narrator: $npcName starts travelling to $cnLocation",
            'sess'     => $momentum,
            'localts'  => time(),
            'people'   => $npcName,
            'location' => $location ?? null,
            'party'    => "",
        ]
    );
    
    // Insert actions_issued log entry
    $db->insert(
        'actions_issued',
        [
            'action'    => "TravelTo",
            'fullcall'  => "TravelTo:$cnLocation",
            'actorname' => $npcName,
            'ts'        => $last_ts,
            'gamets'    => $last_gamets,
            'localts'   => time(),
            'original'  => 'backgroundaction',
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
function handleStayAtPlaceAction($location, $currentNpcData, $npcName, $last_ts, $last_gamets, $momentum, $db) {
    $cnLocation = $db->escape($location);
    $locId = $db->fetchOne("select formid from locations where name='$cnLocation'");
    
    $refHexString = convertSignedToUnsignedHex(hexdec($currentNpcData["refid"]));
    
    // Insert response log entry with return home command
    $db->insert(
        'responselog',
        [
            'localts' => time(),
            'sent'    => 0,
            'actor'   => "rolemaster",
            'text'    => "",
            'action'  => "rolecommand|BackgroundCmd@$refHexString@StayAyPlace/",
            'tag'     => '',
        ]
    );
    
    return true;
}

//sends a track signal, NPC should report coords
function handleTrack($currentNpcData,$db) {
    $refHexString = convertSignedToUnsignedHex(hexdec($currentNpcData["refid"]));
    
    // Insert response log entry with return home command
    $db->insert(
        'responselog',
        [
            'localts' => time(),
            'sent'    => 0,
            'actor'   => "rolemaster",
            'text'    => "",
            'action'  => "rolecommand|BackgroundCmd@$refHexString@Track/",
            'tag'     => '',
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
function handleReturnHome($location, $currentNpcData, $npcName, $last_ts, $last_gamets, $momentum, $db) {
    $cnLocation = $db->escape($location);
    $locId = $db->fetchOne("select formid from locations where name='$cnLocation'");
    
    $refHexString = convertSignedToUnsignedHex(hexdec($currentNpcData["refid"]));
    
    // Insert response log entry with return home command
    $db->insert(
        'responselog',
        [
            'localts' => time(),
            'sent'    => 0,
            'actor'   => "rolemaster",
            'text'    => "",
            'action'  => "rolecommand|BackgroundCmd@$refHexString@ReturnHome/",
            'tag'     => '',
        ]
    );
    
    return true;
}