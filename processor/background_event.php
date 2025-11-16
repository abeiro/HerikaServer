<?php 



function convertSignedToUnsignedHexLocal($signedInt) {
    // Keep only the lower 24 bits (force leading 00)
    $masked = $signedInt & 0x00FFFFFF;

    // Format as 8-character zero-padded hex
    return "0x" . strtoupper(sprintf("%08x", $masked));
}


$bgevent=json_decode($gameRequest[3],true);
if (is_array($bgevent)) {
    $bgevent["pkgformid"] = convertSignedToUnsignedHexLocal($bgevent["pkgformid"]);

    $packageDesc=$GLOBALS["db"]->fetchOne("select * from master_packages where mod ~* '{$bgevent["source"]}' and formid='{$bgevent["pkgformid"]}'");

    // error_log("select * from master_packages where mod ~* '{$bgevent["source"]}' and formid='{$bgevent["pkgformid"]}'");

    if (is_array($packageDesc) && isset($packageDesc[$bgevent["event"]])) {
        $bgevent["description"] = $packageDesc[$bgevent["event"]];
        $bgevent["name"]=$packageDesc["name"];
        $ubstitutions=[
            "player"=>$GLOBALS["PLAYER_NAME"],
            "location"=>$bgevent["location"],
            "actor"=>$bgevent["actor"],
        ];
        foreach ($ubstitutions as $key => $value) {
            $bgevent["description"] = str_replace("{" . $key . "}", $value, $bgevent["description"]);
        }
        
        if ($bgevent["actor"]) {
            $npcManager      = new NpcMaster();
            $npcData         = $npcManager->getByName($bgevent["actor"]);
            $cn=$GLOBALS["db"]->escape($npcData["npc_name"]);
            $extended = json_decode($npcData["extended_data"], true);
            if (isset($extended["background_life_enabled"]) && $extended["background_life_enabled"]==true) {
                $lastAction=$GLOBALS["db"]->fetchOne("select * from actions_issued where actorname='$cn' order by gamets desc,ts desc limit 1 offset 0");
                if (isset($lastAction) && isset($lastAction["action"]) && ($lastAction["action"]==$bgevent["name"])) {
                    // NPC finishes? last action issued
                    if ($bgevent["event"]=="end")
                        error_log("[BGL] {$npcData["npc_name"]} has finished action {$bgevent["name"]}");

                }
                $extended["background_life_last"]=$bgevent;
                $npcData=$npcManager->setExtendedData($npcData,$extended);
                $npcManager->updateByArray($npcData);
            }
        }
    }
    else
        $bgevent["description"] = 'unknown';

    // Substitutions

    $gameRequest[3]=json_encode($bgevent);
    // error_log("[BACKGROUND EVENT] {$gameRequest[3]}".print_r($bgevent,true));
    logEvent($gameRequest,$GLOBALS["HERIKA_NAME"]);// Force actors involved in this event...this is the current actor
} else  {
    // Probably comment this when in production, to avoid unknown events
    logEvent($gameRequest,$GLOBALS["HERIKA_NAME"]);// Force actors involved in this event...this is the current actor
}

terminate();

?>