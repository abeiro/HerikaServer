<?php



function convertSignedToUnsignedHexTrunc($signedInt)
{

    $masked = $signedInt & 0x00FFFFFF;

    // Format as 8-character zero-padded hex
    return "0x" . strtoupper(sprintf("%08x", $masked));
}

function convertSignedToUnsignedHexLocal($signedInt, $preprend0x = true)
{

    $masked = $signedInt & 0xFFFFFFFF;

    // Format as 8-character zero-padded hex
    $hex = strtoupper(sprintf("%08x", $masked));
    return $preprend0x ? "0x" . $hex : $hex;
}

// Handle npc_reanimated event
if ($gameRequest[0] === 'npc_reanimated') {
    // Event format: npc_reanimated|timestamp|gametime|target_name
    $targetName = isset($gameRequest[3]) ? $gameRequest[3] : "";

    if (!empty($targetName)) {
        error_log("[NPC_REANIMATED] Processing: Target={$targetName}");

        try {
            require_once(__DIR__ . "/../lib/core/npc_master.class.php");

            $npcManager = new NpcMaster();
            $npcData = $npcManager->getByName($targetName);

            if ($npcData && isset($npcData['id'])) {
                // Get current extended_data
                $extended = $npcManager->getExtendedData($npcData);
                if (!is_array($extended)) {
                    $extended = [];
                }

                // Mark as reanimated (always track the state in extended_data)
                $extended["reanimated"] = true;

                // Only modify core field and prompts if reanimation tracking is enabled
                if (empty($GLOBALS["DISABLE_REANIMATION_TRACKING"])) {
                    // Append reanimation status to the core field if not already there
                    $currentCore = isset($npcData['core']) ? $npcData['core'] : '';
                    $reanimationText = "You have been reanimated from death as a zombie.";

                    // Only add if not already present
                    if (stripos($currentCore, 'reanimated') === false && stripos($currentCore, 'zombie') === false) {
                        $npcData['core'] = trim($currentCore) . ' ' . $reanimationText;
                    }
                }

                // Update NPC profile
                $npcData = $npcManager->setExtendedData($npcData, $extended);
                $npcManager->updateByArray($npcData);

                error_log("[NPC_REANIMATED] Successfully updated {$targetName} - marked as reanimated" . (empty($GLOBALS["DISABLE_REANIMATION_TRACKING"]) ? " and updated core field" : " (core field update skipped - tracking disabled)"));
            } else {
                error_log("[NPC_REANIMATED] NPC not found in database: {$targetName}");
            }

        } catch (Exception $e) {
            error_log("[NPC_REANIMATED] Error processing reanimation: " . $e->getMessage());
        }
    }

    // Log the event for history
    logEvent($gameRequest, $GLOBALS["HERIKA_NAME"]);
    terminate();
}

// Handle backgroundaction event
$bgevent = json_decode($gameRequest[3], true);
if (is_array($bgevent)) {
    $bgevent["pkgformid"] = convertSignedToUnsignedHexTrunc($bgevent["pkgformid"]);

    $packageDesc = $GLOBALS["db"]->fetchOne("select * from master_packages where mod ~* '{$bgevent["source"]}' and formid='{$bgevent["pkgformid"]}'");

    // error_log("select * from master_packages where mod ~* '{$bgevent["source"]}' and formid='{$bgevent["pkgformid"]}'");

    if (is_array($packageDesc) && isset($packageDesc[$bgevent["event"]])) {
        $bgevent["description"] = $packageDesc[$bgevent["event"]];
        $bgevent["name"] = $packageDesc["name"];
        $ubstitutions = [
            "player" => $GLOBALS["PLAYER_NAME"],
            "location" => $bgevent["location"],
            "actor" => $bgevent["actor"],
        ];
        foreach ($ubstitutions as $key => $value) {
            $bgevent["description"] = str_replace("{" . $key . "}", $value, $bgevent["description"]);
        }

        if (is_array($bgevent["debug"])) {
            foreach ($bgevent["debug"] as $key => $value) {
                if (is_array($value)) {
                    error_log("Debug array for key {$key}: " . print_r($value, true));
                    if ($value[0] == "62") {// Linked refrence is a NPC, try to get its name
                        $targetRefid = convertSignedToUnsignedHexLocal($value[1], false);
                        $npcManager = new NpcMaster();
                        $targetNpcData = $npcManager->getByRefid($targetRefid);
                        if (isset($targetNpcData) && isset($targetNpcData["npc_name"])) {
                            $npcDestinationname = $targetNpcData["npc_name"];
                            if ($bgevent["description"]) {
                                if ($bgevent["event"] == "start")
                                    $bgevent["description"] .= " (target is: {$npcDestinationname})";
                                if ($bgevent["event"] == "end")
                                    $bgevent["description"] .= "(target was: {$npcDestinationname})";
                            }
                        }
                    }

                } else {
                    error_log("Debug value for key {$key}: " . $value);
                }
            }
        } else {
            error_log("No debug information for this event.");
        }

        if ($bgevent["actor"]) {
            $npcManager = new NpcMaster();
            $npcData = $npcManager->getByName($bgevent["actor"]);
            $cn = $GLOBALS["db"]->escape($npcData["npc_name"]);
            $extended = json_decode($npcData["extended_data"], true);
            $bgevent["server_processed"] = false;
            if (isset($extended["background_life_enabled"]) && $extended["background_life_enabled"] == true) {
                $lastAction = $GLOBALS["db"]->fetchOne("select * from actions_issued where actorname='$cn' order by gamets desc,ts desc,localts desc limit 1 offset 0");
                if (isset($lastAction) && isset($lastAction["action"]) && ( 
                    ($lastAction["action"] == $bgevent["name"])
                    ||
                    ($lastAction["action"] == $bgevent["name"] . "Raw") // Handle the TravelToRaw case, where the issued action is TravelToRaw but the event is TravelTo
                    ||
                    ($lastAction["action"] == "MoveTo" && $bgevent["name"]=="TravelTo") // Handle the MoveTo/TravelTo case, where the issued action is MoveTo but the event is TravelTo
                    )
                ) {
                    // NPC finishes? last action issued
                    if ($bgevent["event"] == "end") {
                        error_log("[BGL] {$npcData["npc_name"]} has finished ISSUED action {$bgevent["name"]} <{$lastAction["action"]}>");
                        $extended["background_life_last_updated"] = 0; // Force trigger on next middleterm BgL check, NPC will request a new action.
                        $bgevent["server_processed"] = true;

                        if ($bgevent["name"] == "TravelTo" || $bgevent["name"] == "MoveTo") {
                            $message="{$npcData["npc_name"]} reaches destination";
                            $category="travel";
                        } else {
                            $message="{$npcData["npc_name"]} {$bgevent["event"]} {$bgevent["name"]}";
                            $category="other";
                        }

                        $GLOBALS["db"]->insert(
                            'bgl_history',
                            [
                                'npc' => $npcData["npc_name"],
                                'ts' => $gameRequest[1],
                                'gamets' => $gameRequest[2],
                                'localts' => time(),
                                'data' => $message,
                                'category' => $category,
                            ]
                        );
                    }

                } else 
                    if ($bgevent["event"] == "end")
                        error_log("[BGL] {$npcData["npc_name"]} has finished NON-ISSUED action {$bgevent["name"]} <{$lastAction["action"]}>");

                $extended["background_life_last"] = $bgevent;
                $npcData = $npcManager->setExtendedData($npcData, $extended);
                $npcManager->updateByArray($npcData);

                // Also, lets track coords.
                if ($npcData["refid"]) {
                    $GLOBALS["db"]->insert(
                        'responselog',
                        [
                            'localts' => time(),
                            'sent' => 0,
                            'actor' => "rolemaster",
                            'text' => "",
                            'action' => "rolecommand|BackgroundCmd@0x{$npcData['refid']}@Track/",
                            'tag' => "requested after background event {$bgevent["name"]}",
                        ]
                    );
                }
            }
        }
    } else
        $bgevent["description"] = 'unknown';

    // Substitutions

    $gameRequest[3] = json_encode($bgevent);
    // error_log("[BACKGROUND EVENT] {$gameRequest[3]}".print_r($bgevent,true));
    logEvent($gameRequest, $GLOBALS["HERIKA_NAME"]);// Force actors involved in this event...this is the current actor
} else {
    // Probably comment this when in production, to avoid unknown events
    logEvent($gameRequest, $GLOBALS["HERIKA_NAME"]);// Force actors involved in this event...this is the current actor
}

terminate();

?>