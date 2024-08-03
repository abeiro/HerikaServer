<?php

// API-DOC https://developers.deepgram.com/docs/getting-started-with-pre-recorded-audio

$localPath = dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR;
require_once($localPath . "conf".DIRECTORY_SEPARATOR."conf.php"); // API KEY must be there
require_once($localPath . "lib" .DIRECTORY_SEPARATOR."{$GLOBALS["DBDRIVER"]}.class.php");
require_once($localPath . "lib" .DIRECTORY_SEPARATOR."chat_helper_functions.php");



function stt($file)
{
    if (!$GLOBALS["db"])
        $GLOBALS["db"] = new sql();
    
    $filePath = $file;
    $fileContent = file_get_contents($filePath);
    $url = "https://api.deepgram.com/v1/listen?smart_format=false&language={$GLOBALS["STT"]["DEEPGRAM"]["LANG"]}&model=whisper-medium";
    $url = "https://api.deepgram.com/v1/listen?punctuate=true&filler_words=true&language={$GLOBALS["STT"]["DEEPGRAM"]["LANG"]}&model={$GLOBALS["STT"]["DEEPGRAM"]["MODEL"]}";
    
    if (strpos($GLOBALS["STT"]["DEEPGRAM"]["MODEL"],"whisper")===false) {   //WHISPER MODELS DONT SUPPORT KEYWORDS
        $keywords=lastKeyWordsNew(30);
        foreach ($keywords as $keyword)
            $url.="&keywords=".urlencode($keyword)."%3A1";
    }
    
    // print_r($keywords);
    
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

