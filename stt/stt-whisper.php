<?php


$localPath = dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR;
require_once($localPath . "lib" .DIRECTORY_SEPARATOR."runtime_bootstrap.php");
chimRuntimeBootstrapIfNeeded($localPath, [
    'load_general_settings' => true,
    'load_stt_connector' => true,
    'load_itt_connector' => false,
]);
require_once($localPath . "lib" .DIRECTORY_SEPARATOR."chat_helper_functions.php");


function stt($file)
{
    $GLOBALS["db"] = new sql();

    $additionalKeywords = lastKeyWords(30, ["chat","chatme","DRO-VAH!"]);    
    $url = ($GLOBALS["STT"]["WHISPER"]["TRANSLATE"]) ?
        "https://api.openai.com/v1/audio/translations" :
        "https://api.openai.com/v1/audio/transcriptions";

    $lang = isset($GLOBALS["STT"]["WHISPER"]["LANG"]) ? $GLOBALS["STT"]["WHISPER"]["LANG"] : "en";

    // Build multipart/form-data body
    $filePath = $file;
    $boundary = '----WebKitFormBoundary' . md5(mt_rand() . microtime());
    $contentType = 'multipart/form-data; boundary=' . $boundary;

    $fileContent = file_get_contents($filePath);
    $filename = basename($filePath);

    if (!$GLOBALS["STT"]["WHISPER"]["TRANSLATE"]) {
        $multipartBody = "--{$boundary}\r\n"
            ."Content-Disposition: form-data; name=\"file\"; filename=\"{$filename}\"\r\n"
            ."Content-Type: audio/wav\r\n"
            ."Content-Transfer-Encoding: binary\r\n\r\n"
            .$fileContent . "\r\n"
            ."--{$boundary}\r\n"
            ."Content-Disposition: form-data; name=\"model\"\r\n\r\n"
            ."whisper-1\r\n"
            ."--{$boundary}\r\n"
            ."Content-Disposition: form-data; name=\"prompt\"\r\n\r\n"
            ."{$GLOBALS["HERIKA_NAME"]},Dragonborn,Whiterun,$additionalKeywords\r\n"
            ."--{$boundary}\r\n"
            ."Content-Disposition: form-data; name=\"language\"\r\n\r\n"
            ."$lang\r\n"
            ."--{$boundary}--\r\n";
    } else {
        $multipartBody = "--{$boundary}\r\n"
            ."Content-Disposition: form-data; name=\"file\"; filename=\"{$filename}\"\r\n"
            ."Content-Type: audio/wav\r\n"
            ."Content-Transfer-Encoding: binary\r\n\r\n"
            .$fileContent . "\r\n"
            ."--{$boundary}\r\n"
            ."Content-Disposition: form-data; name=\"model\"\r\n\r\n"
            ."whisper-1\r\n"
            ."--{$boundary}--\r\n";
    }

    // Resolve API key: prefer API Badge 'OpenAI', fallback to STT conf
    $apiKey = '';
    try {
        if (!isset($GLOBALS["db"]) || !$GLOBALS["db"]) $GLOBALS["db"] = new sql();
        $row = $GLOBALS["db"]->fetchOne("SELECT api_key FROM core_api_badge WHERE lower(label)='openai' LIMIT 1");
        if (is_array($row) && !empty($row['api_key'])) $apiKey = trim($row['api_key']);
    } catch (Throwable $_e) {}
    if ($apiKey === '') {
        $apiKey = trim($GLOBALS["STT"]["WHISPER"]["API_KEY"] ?? '');
    }

    $contextOptions = [
        'http' => [
            'method' => 'POST',
            'header' => "Authorization: Bearer {$apiKey}\r\n".
                        "Content-Type: {$contentType}\r\n".
                        "Content-Length: " . strlen($multipartBody) . "\r\n",
            'content' => $multipartBody,
            'timeout' => 60,
        ],
    ];

    $context = stream_context_create($contextOptions);
    $response = @file_get_contents($url, false, $context);
    if ($response === false || !is_string($response) || trim($response) === '') {
        return '';
    }

    $reponseParsed = json_decode($response);
    if (isset($reponseParsed->error)) {
        return '';
    }

    return isset($reponseParsed->text) ? $reponseParsed->text : '';
}
