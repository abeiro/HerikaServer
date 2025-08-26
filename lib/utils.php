<?php

function npcNameToCodename($npcName) {
    $codename=mb_convert_encoding($npcName, 'UTF-8', mb_detect_encoding($npcName));
    $codename=strtr(strtolower(trim($codename)),[" "=>"_","'"=>"+"]);
    $codename=preg_replace('/[^\w+-]/u', '', $codename);
    return $codename;
}

function isNonEmptyArray($var) {
    if (!isset($var))
        return false;
    
    return is_array($var) && count($var) > 0;
}

function getBaseUrlForSpeech(): string {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
    //$host = $_SERVER['HTTP_HOST']; // host could contain port for some configurations
    $host = $_SERVER['SERVER_ADDR'];
    $port = intval($_SERVER['SERVER_PORT']); // under Apache 2, UseCanonicalName = On as well as UseCanonicalPhysicalPort = On must be set in order to get the real port, otherwise, this value can be spoofed.
    
    if (empty($port) || ($port == 80))
        $port = 8081; // Seems this is not being autodetected

    // Check if the port is non-standard for the protocol
    $isDefaultPort = ($protocol === "http://" && $port == 80) || ($protocol === "https://" && $port == 443);
    //error_log(" getBaseUrlForSpeech: $protocol - $host  -  $port "); //debug

    return $protocol . $host . ($isDefaultPort ? '' : ':' . $port);
}

?>
