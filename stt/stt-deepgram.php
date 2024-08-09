<?php

// API-DOC https://developers.deepgram.com/docs/getting-started-with-pre-recorded-audio

$localPath = dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR;
require_once($localPath . "conf".DIRECTORY_SEPARATOR."conf.php"); // API KEY must be there
require_once($localPath . "lib" .DIRECTORY_SEPARATOR."{$GLOBALS["DBDRIVER"]}.class.php");
require_once($localPath . "lib" .DIRECTORY_SEPARATOR."chat_helper_functions.php");



function stt($file)
{
    //global $AZURETTS_CONF;
    $GLOBALS["db"] = new sql();

    $filePath = $file;
    $fileContent = file_get_contents($filePath);
    $url = "https://api.deepgram.com/v1/listen?smart_format=false&language=en&model=whisper-medium";

    $contextOptions = [
        'http' => [
            'method' => 'POST',
            'header' => [
                "Authorization: Token {$GLOBALS["STT"]["DEEPGRAM"]["API_KEY"]}",
                "Content-Type: audio/wav"
            ],
            'content' => $fileContent,
        ],
    ];

    $context = stream_context_create($contextOptions);
    $response = file_get_contents($url, false, $context);

    if ($response === false) {
        // Handle error
        return null;
    }

    $responseParsed = json_decode($response, true);

    return $responseParsed['results']['channels'][0]['alternatives'][0]['transcript'];
}

