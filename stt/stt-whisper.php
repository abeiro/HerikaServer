<?php


$localPath = dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR;
require_once($localPath . "conf".DIRECTORY_SEPARATOR."conf.php"); // API KEY must be there
require_once($localPath . "lib" .DIRECTORY_SEPARATOR."{$GLOBALS["DBDRIVER"]}.class.php");
require_once($localPath . "lib" .DIRECTORY_SEPARATOR."chat_helper_functions.php");


function stt($file)
{
    
    $GLOBALS["db"] = new sql();
    
    $additionalKeywords=lastKeyWords(30,["chat","chatme","DRO-VAH!"]);
    
    if ($GLOBALS["STT"]["WHISPER"]["TRANSLATE"])
        $url = "https://api.openai.com/v1/audio/translations";
    else
        $url = "https://api.openai.com/v1/audio/transcriptions";
    
    $lang=(isset($GLOBALS["STT"]["WHISPER"]["LANG"]))?$GLOBALS["STT"]["WHISPER"]["LANG"]:"en";

    $filePath = $file;
    $boundary = '----WebKitFormBoundary' . md5(mt_rand() . microtime());
    $contentType = 'multipart/form-data; boundary=' . $boundary;

    // Prepare the file content
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
            ."--{$boundary}\r\n";
    }
         
    $contextOptions = [
        'http' => [
            'method' => 'POST',
            'header' => "Authorization: Bearer  {$GLOBALS["STT"]["WHISPER"]["API_KEY"]}\r\n"."Content-Type: {$contentType}\r\n" .
            "Content-Length: " . strlen($multipartBody) . "\r\n",
            'content' => $multipartBody,
        ],
    ];

    file_put_contents(__DIR__."/../log/stt.log",date("dMy").PHP_EOL.$additionalKeywords,FILE_APPEND);
    $context = stream_context_create($contextOptions);
    $response = file_get_contents($url, false, $context);
    file_put_contents(__DIR__."/../log/stt.log",print_r($response,true),FILE_APPEND);

    if ($response === false) {

    } else {


    }
    $reponseParsed = json_decode($response);

    return $reponseParsed->text;
}
