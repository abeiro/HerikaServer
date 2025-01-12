<?php

function npcNameToCodename($npcName) {
    $codename=mb_convert_encoding($npcName, 'UTF-8', mb_detect_encoding($npcName));
    $codename=strtr(strtolower(trim($codename)),[" "=>"_","'"=>"+"]);
    $codename=preg_replace('/[^\w+-]/u', '', $codename);
    return $codename;
}

?>
