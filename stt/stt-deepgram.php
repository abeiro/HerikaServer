<?php

// API-DOC https://developers.deepgram.com/docs/getting-started-with-pre-recorded-audio

$localPath = dirname(__FILE__) . '/../';
require_once($localPath . "lib/runtime_bootstrap.php");
chimRuntimeBootstrapIfNeeded($localPath, [
    'load_general_settings' => true,
    'load_stt_connector' => true,
    'load_itt_connector' => false,
]);
require_once($localPath . "lib/chat_helper_functions.php");


function stt($filePath)
{

    if (!isset($GLOBALS["db"]) || !$GLOBALS["db"])
        $GLOBALS["db"] = new sql();

    $fileContent = file_get_contents($filePath);
    if ($fileContent === false) {
        error_log("STT Deepgram: warning - input file missing or unreadable! {$filePath}");
        return null;
    }
    
    $i_sz = filesize($filePath);
    if ($i_sz == 144046) {
        error_log("STT Deepgram: warning input file {$filePath} probably contains silence. "); //debug
        //return null;
    }
    
    $stt_model = $GLOBALS["STT"]["DEEPGRAM"]["MODEL"] ?? "none";
    $stt_lang = $GLOBALS["STT"]["DEEPGRAM"]["LANG"] ?? "en";

    if (!(strpos($stt_model, "nova-3") === false)) { // nova-3 need keyterm not keywords
        $keywords = lastKeyWordsNew(30);
        $url = "";
        foreach ($keywords as $keyword)
            $url .= "&keyterm=" . urlencode($keyword) . "%3A1";
        if (stripos("|multi|en|en-US|en-GB|en-IN|en-NZ|".
            "bg|ca|cs|da|da-DK|de|de-CH|es|es-419|el|et|fi|fr|fr-CA|hi|hu|".
            "id|it|ja|ko|ko-KR|lv|lt|ms|nl|nl-BE|no|pl|pt|pt-BR|pt-PT|".
            "ro|ru|sk|sv|sv-SE|tr|uk|vi", $stt_lang) === false) { 
            $stt_lang = 'en';
        }
    } elseif (!(strpos($stt_model, "flux-general") === false)) {// flux-general-en !!! TODO: update web-ui with this new option
        $keywords = lastKeyWordsNew(30);
        $url = "";
        $stt_lang = 'en'; // flux is only EN at this moment
        foreach ($keywords as $keyword)
            $url .= "&keyterm=" . urlencode($keyword) . "%3A1";
    } elseif (stripos($stt_model, "whisper") === false) {   //WHISPER MODELS DONT SUPPORT KEYWORDS
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
    error_log("STT Deepgram: " . $response); // debug
    return $responseParsed['results']['channels'][0]['alternatives'][0]['transcript'];
}
