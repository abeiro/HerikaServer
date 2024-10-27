<?php


$GLOBALS["TTS_IN_USE"]=function($textString, $mood = "default", $stringforhash)
{


    $apiEndpoint = $GLOBALS["TTS"]["XVASYNTH"]["url"];
    $fileName=md5(trim($stringforhash));

    if (!isset($GLOBALS["TEMP"]["XVASYNTH_INIT"])) {
        $GLOBALS["TEMP"]["XVASYNTH_INIT"]=true;


        $jsonData = array(
            "outputs" => "",
            "model" =>   "resources/app/models/skyrim/{$GLOBALS["TTS"]["XVASYNTH"]["model"]}",
            "modelType" =>  $GLOBALS["TTS"]["XVASYNTH"]["modelType"],
            "version" => "3.0",
            "base_lang" => $GLOBALS["TTS"]["XVASYNTH"]["base_lang"],
            "pluginsContext" => "{}"
        );


        $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: text/plain;charset=UTF-8\r\n",
            'content' => json_encode($jsonData),
        ],


        ]);

        $GLOBALS["DEBUG_DATA"]["XVASYNTH"]["prerequest"]=json_encode($jsonData);

        // Send the POST request with the specified headers
        $response = file_get_contents("$apiEndpoint/loadModel", false, $context);

        $GLOBALS["DEBUG_DATA"]["XVASYNTH"]["preresponse"]=$response;



    }

    $jsonData=array(
        "sequence" => "$textString",
        "editorStyles" => (object)[],
        "pace" => $GLOBALS["TTS"]["XVASYNTH"]["pace"],
        "base_lang" => $GLOBALS["TTS"]["XVASYNTH"]["base_lang"],
        "base_emb" => array(),
        "modelType" => $GLOBALS["TTS"]["XVASYNTH"]["modelType"],
        "useSR" => false,
        "useCleanup" => false,
        "outfile" => "\\\\wsl.localhost\\{$GLOBALS["TTS"]["XVASYNTH"]["distroname"]}\\var\\www\\html\\HerikaServer\\soundcache\\{$fileName}.wav",
        "pluginsContext" => "{}",
        "vocoder" => $GLOBALS["TTS"]["XVASYNTH"]["vocoder"],
        "waveglowPath" => $GLOBALS["TTS"]["XVASYNTH"]["waveglowPath"]
    );

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: text/plain;charset=UTF-8\r\n",
            'content' => json_encode($jsonData),
        ],
    ]);

    $GLOBALS["DEBUG_DATA"]["XVASYNTH"]["request"]=json_encode($jsonData);

    // Send the POST request with the specified headers
    $response = file_get_contents("$apiEndpoint/synthesize", false, $context);

    $GLOBALS["DEBUG_DATA"]["XVASYNTH"]["response"]=$response;

    // Handle the response
    if ($response !== false) {
        // Handle the successful response

        if (isset($GLOBALS["TTS"]["XVASYNTH"]["DEVENV"])) {
            file_put_contents("soundcache/" . md5(trim($stringforhash)) . ".wav",file_get_contents("http://172.16.1.128:8081/HerikaServer/soundcache/".md5(trim($stringforhash)) . ".wav"));
        }
        
        return "soundcache/" . md5(trim($stringforhash)) . ".wav";

    } else {
        $textString.=print_r($http_response_header, true);
        $GLOBALS["DEBUG_DATA"]["XVASYNTH"]["error"]="$textString";
        file_put_contents(dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "soundcache/" . md5(trim($stringforhash)) . ".err", trim($textString));
        return false;

    }

}
