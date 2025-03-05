<?php

function uploadFileToGradio($filePath, $gradioApiUrl) {
    if (!file_exists($filePath)) {
        return false;
    }

    $cfile = new CURLFile(realpath($filePath), mime_content_type($filePath), basename($filePath));

    $postData = [
        "files" => $cfile,
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $gradioApiUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        return false;
    }

    curl_close($ch);

    return $response;
}


$GLOBALS["TTS_IN_USE"]=function($textString, $mood , $stringforhash) {
        
        if (isset($GLOBALS["AVOID_TTS_CACHE"]) && $GLOBALS["AVOID_TTS_CACHE"]===false )
            if (file_exists(dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "soundcache/" . md5(trim($stringforhash)) . ".wav"))
                return dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "soundcache/" . md5(trim($stringforhash)) . ".wav";
        
        $starTime = microtime(true);
        
        $lang=isset($GLOBALS["TTS"]["FORCED_LANG_DEV"])?$GLOBALS["TTS"]["FORCED_LANG_DEV"]:$GLOBALS["TTS"]["ZONOS_GRADIO"]["language"];
        
        if (empty($lang))
            $lang=$GLOBALS["TTS"]["ZONOS_GRADIO"]["language"];
    
        $voice=isset($GLOBALS["TTS"]["FORCED_VOICE_DEV"])?$GLOBALS["TTS"]["FORCED_VOICE_DEV"]:$GLOBALS["TTS"]["ZONOS_GRADIO"]["voiceid"];
        
        // fallback to herika_name if voiceid is blank
        if (empty($voice)) {
            $voice = str_replace(" ", "_", mb_strtolower($GLOBALS["HERIKA_NAME"], 'UTF-8'));
            $voice = str_replace("'", "+", $voice);
            $voice=preg_replace('/[^a-zA-Z0-9_+]/u', '', $voice);
        }

        if (isset($GLOBALS["PATCH_OVERRIDE_VOICE"]))
            $voice=$GLOBALS["PATCH_OVERRIDE_VOICE"];
        
        $baseURL = $GLOBALS["TTS"]["ZONOS_GRADIO"]["endpoint"]."/gradio_api";

        $filePath = "/var/www/html/HerikaServer/data/voices/{$voice}.wav";
        $uploadURL = "{$baseURL}/upload";
        $result = uploadFileToGradio($filePath, $uploadURL);

        // response is something like ["/tmp/gradio/randomalphanumeric/TheNarrator.wav"]
        $resultArray = json_decode($result);
        if (is_array($resultArray) && isset($resultArray[0])) {
            $filePath = $resultArray[0];
        } else {
            error_log("could not upload {$voice}.wav to zonos_gradio");
            return false;
        }

        // TODO: the emotion values should be set dynamically based on the mood that the LLM selected
        $data = array(
            'data' => [
                $GLOBALS["TTS"]["ZONOS_GRADIO"]["model"], // Zyphra/Zonos-v0.1-transformer or Zyphra/Zonos-v0.1-hybrid
                $textString, // the dialogue to be generated
                $GLOBALS["TTS"]["ZONOS_GRADIO"]["language"], // en-us, ja, de, etc
                array( // speaker audio
                    "meta" => array (
                        "_type" => "gradio.FileData"
                    ),
                    "mime_type" => "audio/wav",
                    "orig_name" => "{$voice}.wav",
                    "path" => $filePath,
                    "url" => "{$baseURL}/file={$filePath}"
                ),
                null, // prefix audio (could use a 100ms silence wav for example)
                0.05, // happiness
                0.05, // sadness
                0.05, // disgust
                0.05, // fear
                0.05, // surprise
                0.05, // anger
                0.05, // other
                1, // neutral
                0.78, // vq score
                24000, // fmax (hz)
                45, // pitch std
                15, // speaking rate
                4, // dnsmos overall slider
                false, // denoise speaker?
                2, // cfg scale
                0, // top p
                0, // min k
                0, // min p
                0.5, // linear (set to 0 to disable unified sampling)
                0.4, // confidence
                0, // quadratic
                420, // seed
                true, // randomize seed
                [
                    "emotion" // unconditional keys
                ]
            ]
        );
        error_log(print_r($data, true));

        // call to /generate_audio
        $options = array(
            'http' => array(
                'header' => "Content-Type: application/json\r\n" .
                            "Accept: application/json\r\n",
                'method' => 'POST',
                'content' => json_encode($data)
            )
        );
        $context = stream_context_create($options);
        $response = file_get_contents("{$baseURL}/call/generate_audio", false, $context);
        // response is something like {"event_id":"randomalphanumeric"}
        $respObj = json_decode($response);
        if (!$respObj) {
            error_log("could not generate audio from zonos_gradio");
            return false;
        }

        // get generate_audio result using the event_id
        $options = array(
            'http' => array(
                'header' => "Content-Type: application/json\r\n" .
                            "Content: application/json\r\n",
                'method' => 'GET'
            )
        );
        $context = stream_context_create($options);
        $response = file_get_contents("{$baseURL}/call/generate_audio/{$respObj->event_id}", false, $context);

        // extract json from within the response
        $startPos = strpos($response, '{');
        $endPos = strrpos($response, '}');
        $jsonString = substr($response, $startPos, ($endPos - $startPos) + 1);
        $respObj = json_decode($jsonString);
        if (!$respObj) {
            error_log("could not retrieve generate_audio results from zonos_gradio");
            return false;
        }

        // get contents of the generated audio file
        $options = array(
            'http' => array(
                'header' => "Content-Type: application/json\r\n" .
                            "Content: application/json\r\n",
                'method' => 'GET'
            )
        );
        $context = stream_context_create($options);
        $response = file_get_contents("{$baseURL}/file={$respObj->path}", false, $context);

        if (is_array($GLOBALS["TTS_FFMPEG_FILTERS"])) {
            $GLOBALS["TTS_FFMPEG_FILTERS"]["adelay"]="adelay=150|150";
            $FFMPEG_FILTER='-af "'.implode(",",$GLOBALS["TTS_FFMPEG_FILTERS"]).'"';
            
        } else {
            $FFMPEG_FILTER='-filter:a "adelay=150|150"';
        }
        
        // Handle the response
        if ($response !== false ) {
            // Handle the successful response
            $size=strlen($response);
            $oname=dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "soundcache/" . md5(trim($stringforhash)) . "_o.wav";
            $fname=dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "soundcache/" . md5(trim($stringforhash)) . ".wav";
            
            file_put_contents($oname, $response); // Save the audio response to a file
            $startTimeTrans = microtime(true);
            //shell_exec("ffmpeg -y -i $oname  -af \"adelay=150|150,silenceremove=start_periods=1:start_silence=0.1:start_threshold=-25dB,areverse,silenceremove=start_periods=1:start_silence=0.1:start_threshold=-40dB,areverse,speechnorm=e=3:r=0.0001:l=1:p=0.75\" $fname 2>/dev/null >/dev/null");
            shell_exec("ffmpeg -y -i $oname  $FFMPEG_FILTER $fname 2>/dev/null >/dev/null");
            //error_log("ffmpeg -y -i $oname  $FFMPEG_FILTER $fname ");
            $endTimeTrans = microtime(true)-$startTimeTrans;
            
            file_put_contents(dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "soundcache/" . md5(trim($stringforhash)) . ".txt", trim($textString) . "\n$FFMPEG_FILTER\n\rtotal call time:" . (microtime(true) - $starTime) . " ms\n\rffmpeg transcoding: $endTimeTrans secs\n\rsize of wav ($size)\n\rfunction tts($textString,$mood=\"cheerful\",$stringforhash)");
            $GLOBALS["DEBUG_DATA"][]=(microtime(true) - $starTime)." secs in zonos_gradio call";
            return "soundcache/" . md5(trim($stringforhash)) . ".wav";
            
        } else {
            $textString.=print_r($http_response_header,true);
            file_put_contents(dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "soundcache/" . md5(trim($stringforhash)) . ".err", trim($textString));
            return false;
            
        }

};
