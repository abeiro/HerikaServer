<?php

function getZonosLanguage() {
    $lang=isset($GLOBALS["TTS"]["FORCED_LANG_DEV"])?$GLOBALS["TTS"]["FORCED_LANG_DEV"]:$GLOBALS["TTS"]["ZONOS_GRADIO"]["language"];

    if (isset($GLOBALS["PATCH_OVERRIDE_TTS_LANGUAGE"]))
        $lang = $GLOBALS["PATCH_OVERRIDE_TTS_LANGUAGE"];

    if (empty($lang)) {
        $lang=$GLOBALS["TTS"]["ZONOS_GRADIO"]["language"];
    }

    return $lang;
}

function getZonosVoice() {
    $voice=isset($GLOBALS["TTS"]["FORCED_VOICE_DEV"])?$GLOBALS["TTS"]["FORCED_VOICE_DEV"]:$GLOBALS["TTS"]["ZONOS_GRADIO"]["voiceid"];
        
    // fallback to herika_name if voiceid is blank
    if (empty($voice)) {
        $voice = str_replace(" ", "_", mb_strtolower($GLOBALS["HERIKA_NAME"], 'UTF-8'));
        $voice = str_replace("'", "+", $voice);
        $voice=preg_replace('/[^a-zA-Z0-9_+]/u', '', $voice);
    }

    if (isset($GLOBALS["PATCH_OVERRIDE_VOICE"])) {
        $voice=$GLOBALS["PATCH_OVERRIDE_VOICE"];
    }

    return $voice;
}

function isVoiceSampleCached($baseURL, $voiceSamplePath, $cachedVoicePath) {
    $getLastModifiedHeader = function($headers) {
        $header_name = 'last-modified:';

        foreach ($headers as $header) {
            // Case-insensitive comparison to check if the header starts with "last-modified:"
            if (stripos($header, $header_name) === 0) {
                // Find the position of the colon and space
                $colon_pos = strpos($header, ': ');
                if ($colon_pos !== false) {
                    // Extract the value after the colon and space
                    return strtotime(substr($header, $colon_pos + 2));
                }
            }
        }

        return -1;
    };

    $cachedFileExists = function($headers) {
        if (isset($headers) && is_array($headers)) {
            // The first header line contains the status code (e.g., "HTTP/1.1 200 OK")
            $status_line = $headers[0];
    
            // Extract the status code (e.g., "200")
            preg_match('{HTTP\/\S+\s(\d+)}', $status_line, $match);
            $status_code = $match[1] ?? null; // Use null coalescing for safety
    
            // Check if the status code is 200
            if ($status_code === '200') {
                return true;
            }
        }
    
        // If we didn't get a 200 status or couldn't get headers
        return false;
    };

    // get contents of the generated audio file
    $options = array(
        'http' => array(
            'method' => 'HEAD',
            "ignore_errors" => true
        )
    );
    $context = stream_context_create($options);
    $cacheHeaders = @get_headers("{$baseURL}/gradio_api/file={$cachedVoicePath}", 0, $context);

    // check if file exists in zonos cache
    if (!$cachedFileExists($cacheHeaders)) {
        return false;
    }

    // get last modified timestamp of the local voice sample
    $voiceSampleModifiedTime = @filemtime($voiceSamplePath);
    if (!$voiceSampleModifiedTime) {
        return false;
    }

    // get last modified timestamp of the cached voice sample
    $cacheLastModifiedTime = $getLastModifiedHeader($cacheHeaders);
    if ($cacheLastModifiedTime === -1) {
        return false;
    }

    return $voiceSampleModifiedTime <= $cacheLastModifiedTime;
}

function uploadFileToGradio($baseURL, $voiceSamplePath) {
    if (!file_exists($voiceSamplePath)) {
        return false;
    }

    $cfile = new CURLFile(realpath($voiceSamplePath), mime_content_type($voiceSamplePath), basename($voiceSamplePath));

    $postData = [
        "files" => $cfile,
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "{$baseURL}/gradio_api/upload");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        return false;
    }

    curl_close($ch);

    // response is something like ["/tmp/gradio/randomalphanumeric/TheNarrator.wav"]
    return json_decode($response);
}

function setCachedVoicePath($cachedVoicePath) {
    $hashedname=md5($GLOBALS["HERIKA_NAME"]);
    $confFilePath = __DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."conf".DIRECTORY_SEPARATOR."conf_$hashedname.php";

    // regex to retrieve the current cachedVoicePath
    $pattern = '/\$TTS\[\"ZONOS_GRADIO\"\]\[\"cached_voice_path\"\]\s*=\s*(".*"|\'.*\');/';
    $confContent = file_get_contents($confFilePath);
    preg_match_all($pattern, $confContent, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);

    // replace the last instance of cachedVoicePath with the new path
    if (!empty($matches)) {
        $lastMatch = end($matches);
        $startPosition = $lastMatch[0][1];
        $length = strlen($lastMatch[0][0]);
        $replacement = "\$TTS[\"ZONOS_GRADIO\"][\"cached_voice_path\"]='$cachedVoicePath';";
        $updatedContent = substr_replace($confContent, $replacement, $startPosition, $length);
        file_put_contents($confFilePath, $updatedContent);
    }
}

function getZonosEmotions($mood) {
    // defaults
    $emotions = array(
        "response_tone_happiness" => 0.05,
        "response_tone_sadness" => 0.05,
        "response_tone_disgust" => 0.05,
        "response_tone_fear" => 0.05,
        "response_tone_surprise" => 0.05,
        "response_tone_anger" => 0.05,
        "response_tone_other" => 0.05,
        "response_tone_neutral" => 0.05
    );

    // First check if we have emotion values in the LAST_LLM_RESPONSE global variable
    if (isset($GLOBALS["LAST_LLM_RESPONSE"])) {
        foreach ($emotions as $emotion => $default) {
            if (isset($GLOBALS["LAST_LLM_RESPONSE"][$emotion])) {
                // Ensure the value is between 0 and 1
                $value = floatval($GLOBALS["LAST_LLM_RESPONSE"][$emotion]);
                $emotions[$emotion] = max(0, min(1, $value));
            }
        }
    }

    // Adjust emotions based on the mood parameter if provided
    if (!empty($mood) && $mood !== "default") {
        // Handle multiple moods separated by pipe character
        $moodArray = explode('|', $mood);
        $primaryMood = trim($moodArray[0]); // Use the first mood as primary
        
        // Simple mapping of moods to emotion adjustments
        switch (strtolower($primaryMood)) {
            case "happy":
            case "cheerful":
            case "joyful":
            case "excited":
            case "playful":
            case "amused":
                $emotions["response_tone_happiness"] = 0.8;
                $emotions["response_tone_neutral"] = 0.2;
                break;
                
            case "sad":
            case "melancholy":
            case "depressed":
            case "gloomy":
            case "sorrowful":
                $emotions["response_tone_sadness"] = 0.8;
                $emotions["response_tone_neutral"] = 0.2;
                break;
                
            case "angry":
            case "furious":
            case "irritated":
            case "annoyed":
            case "enraged":
                $emotions["response_tone_anger"] = 0.8;
                $emotions["response_tone_neutral"] = 0.2;
                break;
                
            case "fearful":
            case "scared":
            case "terrified":
            case "anxious":
            case "nervous":
                $emotions["response_tone_fear"] = 0.8;
                $emotions["response_tone_neutral"] = 0.2;
                break;
                
            case "surprised":
            case "shocked":
            case "astonished":
            case "amazed":
                $emotions["response_tone_surprise"] = 0.8;
                $emotions["response_tone_neutral"] = 0.2;
                break;
                
            case "disgusted":
            case "repulsed":
            case "revolted":
                $emotions["response_tone_disgust"] = 0.8;
                $emotions["response_tone_neutral"] = 0.2;
                break;
                
            case "sarcastic":
            case "sardonic":
            case "mocking":
            case "teasing":
            case "smug":
            case "smirking":
                $emotions["response_tone_happiness"] = 0.4;
                $emotions["response_tone_other"] = 0.4;
                $emotions["response_tone_neutral"] = 0.2;
                break;
                
            case "whispering":
            case "quiet":
                // For whispering, we don't change emotions but this will be handled by the mood parameter
                break;
                
            case "neutral":
            case "default":
            case "calm":
                $emotions["response_tone_neutral"] = 1.0;
                $emotions["response_tone_happiness"] = 0.0;
                $emotions["response_tone_sadness"] = 0.0;
                $emotions["response_tone_disgust"] = 0.0;
                $emotions["response_tone_fear"] = 0.0;
                $emotions["response_tone_surprise"] = 0.0;
                $emotions["response_tone_anger"] = 0.0;
                $emotions["response_tone_other"] = 0.0;
                break;
                
            case "seductive":
            case "sexy":
            case "flirtatious":
            case "lovely":
                $emotions["response_tone_happiness"] = 0.5;
                $emotions["response_tone_other"] = 0.3;
                $emotions["response_tone_neutral"] = 0.2;
                break;
                
            case "assertive":
            case "confident":
            case "determined":
                $emotions["response_tone_neutral"] = 0.6;
                $emotions["response_tone_other"] = 0.4;
                break;
                
            case "kindly":
            case "gentle":
            case "compassionate":
                $emotions["response_tone_happiness"] = 0.4;
                $emotions["response_tone_neutral"] = 0.6;
                break;
                
            // Default case - use neutral if no specific mapping
            default:
                // Keep default emotions
                break;
        }
    }
    
    // Log the emotion values being used
    /*
    Logger::trace("Using emotions for TTS generation (mood: $mood):");
    foreach ($emotions as $emotion => $value) {
        $emotion_name = str_replace("response_tone_", "", $emotion);
        Logger::trace("Emotion: $emotion_name: $value");
    }
    */

    return $emotions;
}

function generateAudioFromZonos($baseURL, $voice, $cachedVoicePath, $textString, $emotions) {
    $data = array(
        'data' => [
            $GLOBALS["TTS"]["ZONOS_GRADIO"]["model"] ??= "Zyphra/Zonos-v0.1-hybrid", // Zyphra/Zonos-v0.1-transformer or Zyphra/Zonos-v0.1-hybrid
            $textString, // the dialogue to be generated
            $GLOBALS["TTS"]["ZONOS_GRADIO"]["language"] ??= "en-us", // en-us, ja, de, etc
            array( // speaker audio
                "meta" => array (
                    "_type" => "gradio.FileData"
                ),
                "mime_type" => "audio/wav",
                "orig_name" => "{$voice}.wav",
                "path" => $cachedVoicePath,
                "url" => "{$baseURL}/gradio_api/file={$cachedVoicePath}"
            ),
            null, // prefix audio (could use a 100ms silence wav for example)
            $emotions["response_tone_happiness"],
            $emotions["response_tone_sadness"],
            $emotions["response_tone_disgust"],
            $emotions["response_tone_fear"],
            $emotions["response_tone_surprise"],
            $emotions["response_tone_anger"],
            $emotions["response_tone_other"],
            $emotions["response_tone_neutral"],
            0.7, // vq score
            24000, // fmax (hz)
            $GLOBALS["TTS"]["ZONOS_GRADIO"]["pitch_std"] ??= 45 , // pitch std
            $GLOBALS["TTS"]["ZONOS_GRADIO"]["speaking_rate"] ??= 14.6, // speaking rate
            4, // dnsmos overall slider
            false, // denoise speaker?
            $GLOBALS["TTS"]["ZONOS_GRADIO"]["cfg_scale"] ??= 4.5, // cfg scale
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
    $response = file_get_contents("{$baseURL}/gradio_api/call/generate_audio", false, $context);
    // response is something like {"event_id":"randomalphanumeric"}
    return json_decode($response);
}

function getGenerateAudioResultsFromZonos($baseURL, $event_id) {
    $options = array(
        'http' => array(
            'header' => "Content-Type: application/json\r\n" .
                        "Content: application/json\r\n",
            'method' => 'GET'
        )
    );
    $context = stream_context_create($options);
    $response = file_get_contents("{$baseURL}/gradio_api/call/generate_audio/{$event_id}", false, $context);

    // extract json from within the response
    $startPos = strpos($response, '{');
    $endPos = strrpos($response, '}');
    $jsonString = substr($response, $startPos, ($endPos - $startPos) + 1);
    return json_decode($jsonString);
}

function getZonosFileContents($baseURL, $generatedAudioPath) {
    // get contents of the generated audio file
    $options = array(
        'http' => array(
            'header' => "Content-Type: application/json\r\n" .
                        "Content: application/json\r\n",
            'method' => 'GET'
        )
    );
    $context = stream_context_create($options);
    return file_get_contents("{$baseURL}/gradio_api/file={$generatedAudioPath}", false, $context);
}

// Applies substitutes for words Zonos has trouble pronouncing. Only affects pronunciation, not the subtitles in game
function applyZonosWordSubstitutes($textString, $lang) {
    // Add more to this list as needed.
    $replacements = array(
        "en-us" => [
            "Jarl" => "Yarl"
        ]
    );
    if (isset($replacements[$lang])) {
        foreach ($replacements[$lang] as $from => $to) {
            $textString = str_ireplace($from, $to, $textString);
        }
    }

    return $textString;
}

$GLOBALS["TTS_IN_USE"]=function($textString, $mood, $stringforhash) {
        // skip generation if the generated audio already exists in cache
        if (isset($GLOBALS["AVOID_TTS_CACHE"]) && $GLOBALS["AVOID_TTS_CACHE"]===false )
            if (file_exists(dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "soundcache/" . md5(trim($stringforhash)) . ".wav"))
                return dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "soundcache/" . md5(trim($stringforhash)) . ".wav";
        
        $starTime = microtime(true);

        $emotions = getZonosEmotions($mood);
        $lang = getZonosLanguage();
        $voice = getZonosVoice();
        $textString = applyZonosWordSubstitutes($textString, $GLOBALS["TTS"]["ZONOS_GRADIO"]["language"] ??= "en-us");
        
        $baseURL = rtrim($GLOBALS["TTS"]["ZONOS_GRADIO"]["endpoint"], '/');
        $voiceSamplePath = "/var/www/html/HerikaServer/data/voices/{$voice}.wav";
        $cachedVoicePath = $GLOBALS["TTS"]["ZONOS_GRADIO"]["cached_voice_path"] ??= "";

        // POST the voice sample to zonos if it doesn't already exist
        // but avoid caching the player_tts voice
        if (isset($GLOBALS["PATCH_OVERRIDE_VOICE"]) || !$cachedVoicePath || !isVoiceSampleCached($baseURL, $voiceSamplePath, $cachedVoicePath)) {
            $respObj = uploadFileToGradio($baseURL, $voiceSamplePath);
            if (is_array($respObj) && isset($respObj[0])) {
                $cachedVoicePath = $respObj[0];
            } else {
                Logger::error("could not upload {$voice}.wav to zonos_gradio");
                return false;
            }

            // save the path of the cached file to the npc's profile
            if (!isset($GLOBALS["PATCH_OVERRIDE_VOICE"])) {
                setCachedVoicePath($cachedVoicePath);
            }
        }

        // POST request to /generate_audio
        $respObj = generateAudioFromZonos($baseURL, $voice, $cachedVoicePath, $textString, $emotions);
        if (!$respObj) {
            Logger::error("could not generate audio from zonos_gradio");
            return false;
        }

        // GET generate_audio result using the event_id
        $respObj = getGenerateAudioResultsFromZonos($baseURL, $respObj->event_id);
        if (!$respObj) {
            Logger::error("could not retrieve generate_audio results from zonos_gradio");
            return false;
        }

        // GET file contents of generated audio
        $response = getZonosFileContents($baseURL, $respObj->path);

        if ($GLOBALS["TTS_FFMPEG_FILTERS"] && is_array($GLOBALS["TTS_FFMPEG_FILTERS"])) {
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
