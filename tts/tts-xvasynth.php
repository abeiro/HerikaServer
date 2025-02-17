<?php


$GLOBALS["TTS_IN_USE"]=function($textString, $mood = "default", $stringforhash)
{
    // Cache check
    if (isset($GLOBALS["AVOID_TTS_CACHE"]) && $GLOBALS["AVOID_TTS_CACHE"] === false) {
        if (file_exists(dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "soundcache/" . md5(trim($stringforhash)) . ".wav")) {
            return dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "soundcache/" . md5(trim($stringforhash)) . ".wav";
        }
    }

    $starTime = microtime(true);
    $apiEndpoint = $GLOBALS["TTS"]["XVASYNTH"]["url"];
    $fileName = md5(trim($stringforhash));

    // Determine which voice to use
    $voice = $GLOBALS["TTS"]["XVASYNTH"]["model"]; // Default NPC voice
    if (isset($GLOBALS["PATCH_OVERRIDE_VOICE"]) && !empty($GLOBALS["PATCH_OVERRIDE_VOICE"])) {
        $voice = $GLOBALS["PATCH_OVERRIDE_VOICE"]; // Player voice
    }

    // Always initialize the model for each call
    $initData = array(
        "outputs" => "",
        "model" =>   "resources/app/models/{$GLOBALS["TTS"]["XVASYNTH"]["game"]}/{$voice}",
        "modelType" =>  $GLOBALS["TTS"]["XVASYNTH"]["modelType"],
        "version" => $GLOBALS["TTS"]["XVASYNTH"]["version"],
        "base_lang" => $GLOBALS["TTS"]["XVASYNTH"]["base_lang"],
        "pluginsContext" => "{}"
    );

    $initContext = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: text/plain;charset=UTF-8\r\n",
            'content' => json_encode($initData),
        ],
    ]);

    $GLOBALS["DEBUG_DATA"]["XVASYNTH"]["prerequest"]=json_encode($initData);
    
    // Initialize with the correct voice for this call
    $response = file_get_contents("$apiEndpoint/loadModel", false, $initContext);
    $GLOBALS["DEBUG_DATA"]["XVASYNTH"]["preresponse"]=$response;

    // For synthesis, use the determined voice model
    $jsonData = array(
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
        "waveglowPath" => $GLOBALS["TTS"]["XVASYNTH"]["waveglowPath"],
        "model" => "resources/app/models/{$GLOBALS["TTS"]["XVASYNTH"]["game"]}/{$voice}"
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

    // Add FFMPEG filter handling from xtts
    if (is_array($GLOBALS["TTS_FFMPEG_FILTERS"])) {
        $GLOBALS["TTS_FFMPEG_FILTERS"]["adelay"] = "adelay=150|150";
        $FFMPEG_FILTER = '-af "' . implode(",", $GLOBALS["TTS_FFMPEG_FILTERS"]) . '"';
    } else {
        $FFMPEG_FILTER = '-filter:a "adelay=150|150"';
    }

    // Handle the response with improved error handling and audio processing
    if ($response !== false) {
        $size = strlen($response);
        $oname = dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "soundcache/" . $fileName . "_o.wav";
        $fname = dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "soundcache/" . $fileName . ".wav";

        if (isset($GLOBALS["TTS"]["XVASYNTH"]["DEVENV"])) {
            file_put_contents("soundcache/" . $fileName . ".wav", 
                file_get_contents("http://172.16.1.128:8081/HerikaServer/soundcache/" . $fileName . ".wav"));
        }

        // Add audio processing like in xtts
        $startTimeTrans = microtime(true);
        shell_exec("ffmpeg -y -i $oname $FFMPEG_FILTER $fname 2>/dev/null >/dev/null");
        $endTimeTrans = microtime(true) - $startTimeTrans;

        // Add detailed logging like in xtts
        file_put_contents(
            dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "soundcache/" . $fileName . ".txt", 
            trim($textString) . "\n$FFMPEG_FILTER\n\rtotal call time:" . (microtime(true) - $starTime) . 
            " ms\n\rffmpeg transcoding: $endTimeTrans secs\n\rsize of wav ($size)\n\rfunction tts($textString,$mood=\"default\",$stringforhash)"
        );
        
        $GLOBALS["DEBUG_DATA"][] = (microtime(true) - $starTime) . " secs in xvasynth call";
        return "soundcache/" . $fileName . ".wav";
    } else {
        $textString .= print_r($http_response_header, true);
        $GLOBALS["DEBUG_DATA"]["XVASYNTH"]["error"] = "$textString";
        file_put_contents(
            dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "soundcache/" . $fileName . ".err", 
            trim($textString)
        );
        return false;
    }
};
