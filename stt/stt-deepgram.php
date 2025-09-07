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
    $stt_model = $GLOBALS["STT"]["DEEPGRAM"]["MODEL"] ?? "none";
    $stt_lang = $GLOBALS["STT"]["DEEPGRAM"]["LANG"] ?? "en";

    if (!(strpos($stt_model, "nova-3") === false)) { // nova-3 need keyterm not keywords
        $keywords = lastKeyWordsNew(30);
        foreach ($keywords as $keyword)
            $url .= "&keyterm=" . urlencode($keyword) . "%3A1";
        if (stripos(" multi, en, en-US, de, nl, sv, sv-SE, da, da-DK ", $stt_lang) === false) {
            $stt_lang = 'en';
        }
    } else if (strpos($stt_model, "whisper") === false) {   //WHISPER MODELS DONT SUPPORT KEYWORDS
        $keywords = lastKeyWordsNew(30);
        foreach ($keywords as $keyword)
            $url .= "&keywords=" . urlencode($keyword) . "%3A1";
    }

    //$url = "https://api.deepgram.com/v1/listen?smart_format=false&language={$GLOBALS["STT"]["DEEPGRAM"]["LANG"]}&model=whisper-medium";
    $url = "https://api.deepgram.com/v1/listen?punctuate=true&filler_words=true&utterances=true&language={$stt_lang}&model={$stt_model}";

    // print_r($keywords);
    $ch = curl_init();

    // Resolve API key: prefer STT conf, fallback to API Badge 'Deepgram'
    $apiKey = trim($GLOBALS['STT']['DEEPGRAM']['API_KEY'] ?? '');
    if ($apiKey === '') {
        try {
            $row = $GLOBALS["db"]->fetchOne("SELECT api_key FROM core_api_badge WHERE lower(label)='deepgram' LIMIT 1");
            if (is_array($row) && !empty($row['api_key'])) $apiKey = $row['api_key'];
        } catch (Throwable $_e) {}
    }

    // Set cURL options
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $fileContent);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Authorization: Token ' . $apiKey,
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
    error_log("STT Deepgram: " . $response);
    return $responseParsed['results']['channels'][0]['alternatives'][0]['transcript'];
}
