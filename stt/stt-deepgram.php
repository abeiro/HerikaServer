<?php

// API-DOC https://developers.deepgram.com/docs/getting-started-with-pre-recorded-audio

$localPath = dirname(__FILE__) . '/../';
require_once($localPath . "conf/conf.php"); // API KEY must be there
require_once($localPath . "lib/{$GLOBALS["DBDRIVER"]}.class.php");
require_once($localPath . "lib/chat_helper_functions.php");


function stt($filePath)
{

    if (!isset($GLOBALS["db"]) || !$GLOBALS["db"])
        $GLOBALS["db"] = new sql();

    $fileContent = file_get_contents($filePath);
    //$url = "https://api.deepgram.com/v1/listen?smart_format=false&language={$GLOBALS["STT"]["DEEPGRAM"]["LANG"]}&model=whisper-medium";
    $url = "https://api.deepgram.com/v1/listen?punctuate=true&filler_words=true&language={$GLOBALS["STT"]["DEEPGRAM"]["LANG"]}&model={$GLOBALS["STT"]["DEEPGRAM"]["MODEL"]}";

    if (strpos($GLOBALS["STT"]["DEEPGRAM"]["MODEL"], "whisper") === false)
    {   //WHISPER MODELS DONT SUPPORT KEYWORDS
        $keywords = lastKeyWordsNew(30);
        foreach ($keywords as $keyword)
            $url .= "&keywords=" . urlencode($keyword) . "%3A1";
    }

    // print_r($keywords);
    $ch = curl_init();

    // Set cURL options
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $fileContent);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Authorization: Token ' . $GLOBALS['STT']['DEEPGRAM']['API_KEY'],
        'Content-Type: audio/wav'
    ));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    // Execute cURL request
    $response = curl_exec($ch);


    if ($response === false)
    {
        // Handle error
        return null;
    }

    $responseParsed = json_decode($response, true);

    return $responseParsed['results']['channels'][0]['alternatives'][0]['transcript'];
}
