<?php

// API-DOC https://learn.microsoft.com/en-us/azure/ai-services/speech-service/rest-speech-to-text-short

$localPath = dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR;
require_once($localPath . "lib".DIRECTORY_SEPARATOR."runtime_bootstrap.php");
chimRuntimeBootstrapIfNeeded($localPath, [
    'load_general_settings' => true,
    'load_stt_connector' => true,
    'load_itt_connector' => false,
]);



function stt($file)
{
    global $AZURETTS_CONF;

    $region = $GLOBALS["TTS"]["AZURE"]["region"];
    // Resolve API key: prefer STT conf, fallback to API Badge 'Azure'
    $apiKey = trim($GLOBALS["STT"]["AZURE"]["API_KEY"] ?? '');
    if ($apiKey === '') {
        try {
            if (!isset($GLOBALS["db"]) || !$GLOBALS["db"]) require_once($localPath . "lib/{$GLOBALS["DBDRIVER"]}.class.php");
            if (!isset($GLOBALS["db"]) || !$GLOBALS["db"]) $GLOBALS["db"] = new sql();
            $row = $GLOBALS["db"]->fetchOne("SELECT api_key FROM core_api_badge WHERE lower(label)='azure' LIMIT 1");
            if (is_array($row) && !empty($row['api_key'])) $apiKey = $row['api_key'];
        } catch (Throwable $_e) {}
    }
    $lang=($GLOBALS["STT"]["AZURE"]["LANG"]) ? $GLOBALS["STT"]["AZURE"]["LANG"] : "en-US";
    $profanity=($GLOBALS["STT"]["AZURE"]["profanity"]) ? $GLOBALS["STT"]["AZURE"]["profanity"] : "masked";



    $url = "https://$region.stt.speech.microsoft.com/speech/recognition/conversation/cognitiveservices/v1?language=$lang";
    $fileData = file_get_contents($file);


    $headers = array(
        'Content-Type: audio/wav',
        "Ocp-Apim-Subscription-Key: $apiKey"
    );


    $contextOptions = array(
        'http' => array(
            'method' => 'POST',
            'header' => implode("\r\n", $headers),
            'content' => $fileData
        )
    );

    $context = stream_context_create($contextOptions);


    $response = file_get_contents($url, false, $context);


    if ($response === false) {
        // Error handling
    } else {


    }
    $reponseParsed=json_decode($response, true);


    return $reponseParsed["DisplayText"];


}
