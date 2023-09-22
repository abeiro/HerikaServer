<?php

// API-DOC https://learn.microsoft.com/en-us/azure/ai-services/speech-service/rest-speech-to-text-short

$localPath = dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR;
require_once($localPath . "conf".DIRECTORY_SEPARATOR."conf.php"); // API KEY must be there



function stt($file)
{
    global $AZURETTS_CONF;

    $region = $GLOBALS["TTS"]["AZURE"]["region"];
    $apiKey = (isset($GLOBALS["STT"]["AZURE"]["API_KEY"])) ? $GLOBALS["STT"]["AZURE"]["API_KEY"] : "";
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
