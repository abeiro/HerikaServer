<?php

$localPath = dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR;
require_once($localPath . "lib".DIRECTORY_SEPARATOR."runtime_bootstrap.php");
chimRuntimeBootstrapIfNeeded($localPath, [
    'load_general_settings' => true,
    'load_stt_connector' => true,
    'load_itt_connector' => false,
]);


function stt($file)
{

    $url = $GLOBALS["STT"]["LOCALWHISPER"]["URL"];
    ;
    $filePath = $file;
    $boundary = '----WebKitFormBoundary' . md5(mt_rand() . microtime());
    $contentType = 'multipart/form-data; boundary=' . $boundary;

    // Prepare the file content
    $fileContent = file_get_contents($filePath);
    $filename = basename($filePath);
    $multipartBody = "--{$boundary}\r\n"
        . "Content-Disposition: form-data; name=\"{$GLOBALS["STT"]["LOCALWHISPER"]["FORMFIELD"]}\"; filename=\"{$filename}\"\r\n"
        . "Content-Type: audio/wav\r\n\r\n"
        . $fileContent . "\r\n"
        . "--{$boundary}--\r\n";

    // Set up the context for the request
    $contextOptions = [
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: {$contentType}\r\n" .
            "Content-Length: " . strlen($multipartBody) . "\r\n",
            'content' => $multipartBody,
        ],
    ];

    $context = stream_context_create($contextOptions);

    // Send the request and get the response
    $response = file_get_contents($url, false, $context);

    // Handle the response
    if ($response === false) {
        error_log("STT LocalWhisper: Failed to get response from server");
        return "";
    }
    
    $responseParsed = json_decode($response);
    
    if ($responseParsed === null) {
        error_log("STT LocalWhisper: Failed to parse JSON response: " . $response);
        return "";
    }
    
    // Support both 'text' and 'transcription' keys in the response
    if (isset($responseParsed->text)) {
        return $responseParsed->text;
    } elseif (isset($responseParsed->transcription)) {
        return $responseParsed->transcription;
    } else {
        error_log("STT LocalWhisper: No text or transcription found in response: " . json_encode($responseParsed));
        return "";
    }


}
