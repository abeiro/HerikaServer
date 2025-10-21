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
        $ubstitutions=[
            "player"=>$GLOBALS["PLAYER_NAME"],
            "location"=>$bgevent["location"],
            "actor"=>$bgevent["actor"],
        ];
        foreach ($ubstitutions as $key => $value) {
            $bgevent["description"] = str_replace("{" . $key . "}", $value, $bgevent["description"]);
        }
    }
    else
        $bgevent["description"] = 'unknown';

    // Substitutions

        


    $gameRequest[3]=json_encode($bgevent);
    // error_log("[BACKGROUND EVENT] {$gameRequest[3]}".print_r($bgevent,true));
    logEvent($gameRequest,$GLOBALS["HERIKA_NAME"]);// Force actors involved in this event...this is the current actor
} else  {
    
    logEvent($gameRequest,$GLOBALS["HERIKA_NAME"]);// Force actors involved in this event...this is the current actor
}

terminate();

?>