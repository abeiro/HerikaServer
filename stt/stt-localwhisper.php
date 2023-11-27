<?php

$localPath = dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR;
require_once($localPath . "conf".DIRECTORY_SEPARATOR."conf.php"); // API KEY must be there


function stt($file)
{
    // Use ffmpeg to convert to 16000Hz for whispercpp server
    $output_file = $file . '_tmp.wav';
    $command = "ffmpeg -loglevel quiet -i $file -ar 16000 $output_file";
    $output = array();
    $return_var = 0;
    exec($command, $output, $return_var); // Execute the command and ignore the output
    if ($return_var != 0) {
        echo "The command '$command' failed with error code $return_var\n";
    } else {
        rename($output_file, $file); // Replace the old file with the new converted file
    }

    $url = $GLOBALS["STT"]["LOCALWHISPER"]["URL"];
    ;
    $filePath = $file;
    $boundary = '----WebKitFormBoundary' . md5(mt_rand() . microtime());
    $contentType = 'multipart/form-data; boundary=' . $boundary;

    // Prepare the file content
    $fileContent = file_get_contents($filePath);
    $filename = basename($filePath);
    $multipartBody = "--{$boundary}\r\n"
        . "Content-Disposition: form-data; name=\"file\"; filename=\"{$filename}\"\r\n"
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

    // Manejar la respuesta
    if ($response === false) {
        // Error handling
    } else {
        // Procesar la respuesta

    }
    $reponseParsed = json_decode($response);

    //echo $reponseParsed->text;
    return $reponseParsed->text;


}
