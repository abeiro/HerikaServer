<?php

/**
 * Cartesia TTS Implementation
 * 
 * This implementation supports:
 * - Voice cloning from local voice samples
 * - Text-to-speech generation using cloned voices
 * - Automatic voice cloning when first needed (like XTTS)
 * - Caching of Cartesia voice IDs in conf_opts database
 */

/**
 * Get or create Cartesia voice ID for a given voice sample
 * 
 * @param string $voiceName The name of the voice (without extension)
 * @return string|false The Cartesia voice ID or false on error
 */
function getOrCreateCartesiaVoice($voiceName) {
    global $db;
    
    if (!isset($GLOBALS["db"]) || !$GLOBALS["db"]) {
        require_once(__DIR__ . "/../lib/{$GLOBALS["DBDRIVER"]}.class.php");
    }
    if (!isset($GLOBALS["db"]) || !$GLOBALS["db"]) {
        $GLOBALS["db"] = new sql();
    }
    
    $db = $GLOBALS["db"];
    
    // Check if we already have a Cartesia voice ID for this voice
    $optKey = "cartesia_voice_id_{$voiceName}";
    $optKeyEscaped = $db->escape($optKey);
    $row = $db->fetchOne("SELECT value FROM conf_opts WHERE id = '{$optKeyEscaped}'");
    
    if (is_array($row) && !empty($row['value'])) {
        Logger::info("Using cached Cartesia voice ID for {$voiceName}: {$row['value']}");
        return $row['value'];
    }
    
    // No cached voice ID, need to clone the voice
    Logger::info("No cached Cartesia voice ID found for {$voiceName}, cloning voice...");
    
    $voiceSamplePath = "/var/www/html/HerikaServer/data/voices/{$voiceName}.wav";
    
    if (!file_exists($voiceSamplePath)) {
        Logger::error("Voice sample not found: {$voiceSamplePath}");
        return false;
    }
    
    $fileSize = filesize($voiceSamplePath);
    Logger::info("Found voice sample at {$voiceSamplePath} (size: " . number_format($fileSize) . " bytes)");
    
    $cartesiaVoiceId = cloneVoiceToCartesia($voiceName, $voiceSamplePath);
    
    if ($cartesiaVoiceId === false) {
        Logger::error("Failed to clone voice {$voiceName} to Cartesia");
        return false;
    }
    
    // Cache the voice ID in the database
    $optKeyEscaped = $db->escape($optKey);
    $voiceIdEscaped = $db->escape($cartesiaVoiceId);
    $db->execQuery(
        "INSERT INTO conf_opts (id, value) VALUES ('{$optKeyEscaped}', '{$voiceIdEscaped}') 
         ON CONFLICT(id) DO UPDATE SET value = '{$voiceIdEscaped}'"
    );
    
    Logger::info("Successfully cloned voice {$voiceName} to Cartesia with ID: {$cartesiaVoiceId}");
    
    return $cartesiaVoiceId;
}

/**
 * Clone a voice to Cartesia
 * 
 * @param string $voiceName The name to give the voice
 * @param string $voiceSamplePath Path to the voice sample file
 * @return string|false The Cartesia voice ID or false on error
 */
function cloneVoiceToCartesia($voiceName, $voiceSamplePath) {
    $apiKey = '';
    
    // Try to get API key from API badge first
    try {
        global $db;
        if (!isset($GLOBALS["db"]) || !$GLOBALS["db"]) {
            require_once(__DIR__ . "/../lib/{$GLOBALS["DBDRIVER"]}.class.php");
        }
        if (!isset($GLOBALS["db"]) || !$GLOBALS["db"]) {
            $GLOBALS["db"] = new sql();
        }
        $row = $GLOBALS["db"]->fetchOne("SELECT api_key FROM core_api_badge WHERE lower(label)='cartesia' LIMIT 1");
        if (is_array($row) && !empty($row['api_key'])) {
            $apiKey = trim($row['api_key']);
        }
    } catch (Throwable $_e) {}
    
    // Fallback to TTS config
    if ($apiKey === '') {
        $apiKey = $GLOBALS["TTS"]["CARTESIA"]["API_KEY"] ?? '';
    }
    
    if (empty($apiKey)) {
        Logger::error("Cartesia API key not found. Please set it in the API Badge page.");
        return false;
    }
    
    Logger::info("Cloning voice {$voiceName} to Cartesia...");
    
    $url = "https://api.cartesia.ai/voices/clone";
    
    // Get language from config
    $language = $GLOBALS["TTS"]["CARTESIA"]["language"] ?? 'en';
    
    // Override language if set globally
    if (isset($GLOBALS["PATCH_OVERRIDE_TTS_LANGUAGE"])) {
        $language = $GLOBALS["PATCH_OVERRIDE_TTS_LANGUAGE"];
    }
    
    // Use similarity mode for best voice cloning results
    $mode = 'similarity';
    
    // Prepare multipart form data
    $boundary = '----WebKitFormBoundary' . uniqid();
    $eol = "\r\n";
    
    $data = '';
    
    // Add clip file
    $data .= '--' . $boundary . $eol;
    $data .= 'Content-Disposition: form-data; name="clip"; filename="' . basename($voiceSamplePath) . '"' . $eol;
    $data .= 'Content-Type: audio/wav' . $eol . $eol;
    $audioContent = file_get_contents($voiceSamplePath);
    if ($audioContent === false) {
        Logger::error("Failed to read voice file: {$voiceSamplePath}");
        return false;
    }
    Logger::info("Read " . number_format(strlen($audioContent)) . " bytes from voice file");
    $data .= $audioContent . $eol;
    
    // Add name
    $data .= '--' . $boundary . $eol;
    $data .= 'Content-Disposition: form-data; name="name"' . $eol . $eol;
    $data .= $voiceName . $eol;
    
    // Add description
    $data .= '--' . $boundary . $eol;
    $data .= 'Content-Disposition: form-data; name="description"' . $eol . $eol;
    $data .= "Voice cloned from Herika Server for NPC: {$voiceName}" . $eol;
    
    // Add language
    $data .= '--' . $boundary . $eol;
    $data .= 'Content-Disposition: form-data; name="language"' . $eol . $eol;
    $data .= $language . $eol;
    
    // Add mode
    $data .= '--' . $boundary . $eol;
    $data .= 'Content-Disposition: form-data; name="mode"' . $eol . $eol;
    $data .= $mode . $eol;
    
    $data .= '--' . $boundary . '--' . $eol;
    
    // Prepare request
    $options = array(
        'http' => array(
            'method' => 'POST',
            'header' => "X-API-Key: {$apiKey}\r\n" .
                       "Cartesia-Version: 2024-11-13\r\n" .
                       "Content-Type: multipart/form-data; boundary={$boundary}\r\n",
            'content' => $data,
            'timeout' => 60
        )
    );
    
    $context = stream_context_create($options);
    $response = @file_get_contents($url, false, $context);
    
    if ($response === false) {
        $error = error_get_last();
        $errorMsg = $error['message'] ?? 'Unknown error';
        Logger::error("Failed to clone voice to Cartesia: " . $errorMsg);
        
        // Provide helpful message for common errors
        if (strpos($errorMsg, '402') !== false) {
            Logger::error("Cartesia returned 402 Payment Required. Please check:");
            Logger::error("1. Your API key is valid and set in the API Badge");
            Logger::error("2. Your Cartesia account has credits/payment method");
            Logger::error("3. Visit https://play.cartesia.ai/console to manage your account");
        } else if (strpos($errorMsg, '401') !== false) {
            Logger::error("Cartesia returned 401 Unauthorized. Your API key may be invalid.");
        }
        
        return false;
    }
    
    $result = json_decode($response, true);
    
    if (!isset($result['id'])) {
        Logger::error("Invalid response from Cartesia clone API: " . $response);
        return false;
    }
    
    return $result['id'];
}

/**
 * Generate TTS audio from Cartesia
 * 
 * @param string $text The text to synthesize
 * @param string $voiceId The Cartesia voice ID
 * @param string $mood The mood/emotion for the voice
 * @return string|false The audio data or false on error
 */
function generateCartesiaTTS($text, $voiceId, $mood = 'normal') {
    $apiKey = '';
    
    // Try to get API key from API badge first
    try {
        global $db;
        if (!isset($GLOBALS["db"]) || !$GLOBALS["db"]) {
            require_once(__DIR__ . "/../lib/{$GLOBALS["DBDRIVER"]}.class.php");
        }
        if (!isset($GLOBALS["db"]) || !$GLOBALS["db"]) {
            $GLOBALS["db"] = new sql();
        }
        $row = $GLOBALS["db"]->fetchOne("SELECT api_key FROM core_api_badge WHERE lower(label)='cartesia' LIMIT 1");
        if (is_array($row) && !empty($row['api_key'])) {
            $apiKey = trim($row['api_key']);
        }
    } catch (Throwable $_e) {}
    
    // Fallback to TTS config
    if ($apiKey === '') {
        $apiKey = $GLOBALS["TTS"]["CARTESIA"]["API_KEY"] ?? '';
    }
    
    if (empty($apiKey)) {
        Logger::error("Cartesia API key not found");
        return false;
    }
    
    $url = "https://api.cartesia.ai/tts/bytes";
    
    // Get model ID
    $modelId = $GLOBALS["TTS"]["CARTESIA"]["model_id"] ?? 'sonic-english';
    
    // Get speed setting
    $speed = $GLOBALS["TTS"]["CARTESIA"]["speed"] ?? 'normal';
    
    // Get language from config
    $language = $GLOBALS["TTS"]["CARTESIA"]["language"] ?? 'en';
    
    // Override language if set globally
    if (isset($GLOBALS["PATCH_OVERRIDE_TTS_LANGUAGE"])) {
        $language = $GLOBALS["PATCH_OVERRIDE_TTS_LANGUAGE"];
    }
    
    // Prepare voice specification
    $voiceSpec = array(
        'mode' => 'id',
        'id' => $voiceId
    );
    
    // Add emotion SSML tag if enabled and mood is provided
    $useEmotionsEnabled = $GLOBALS["TTS"]["CARTESIA"]["use_emotions"] ?? false;
    
    Logger::info("Cartesia TTS called - use_emotions: " . ($useEmotionsEnabled ? 'true' : 'false') . ", mood: '{$mood}'");
    
    if ($useEmotionsEnabled && !empty($mood) && $mood !== 'default') {
        $emotion = mapMoodToCartesiaEmotion($mood);
        if (!empty($emotion)) {
            // Prepend SSML emotion tag to text
            $text = '<emotion value="' . $emotion . '" /> ' . $text;
            Logger::info("Using Cartesia emotion: {$emotion} (from mood: {$mood})");
        } else {
            Logger::info("Mood '{$mood}' did not map to any Cartesia emotion");
        }
    } else {
        if (!$useEmotionsEnabled) {
            Logger::info("Cartesia emotions disabled in config");
        } else if (empty($mood)) {
            Logger::info("No mood provided to Cartesia TTS");
        } else if ($mood === 'default') {
            Logger::info("Mood is 'default', skipping emotion");
        }
    }
    
    // Prepare output format
    $outputFormat = array(
        'container' => 'wav',
        'encoding' => 'pcm_s16le',
        'sample_rate' => 22050
    );
    
    // Prepare request data
    $data = array(
        'model_id' => $modelId,
        'transcript' => $text,
        'voice' => $voiceSpec,
        'language' => $language,
        'output_format' => $outputFormat,
        'speed' => $speed
    );
    
    // Prepare request
    $options = array(
        'http' => array(
            'method' => 'POST',
            'header' => "X-API-Key: {$apiKey}\r\n" .
                       "Cartesia-Version: 2024-11-13\r\n" .
                       "Content-Type: application/json\r\n",
            'content' => json_encode($data),
            'timeout' => 30
        )
    );
    
    $context = stream_context_create($options);
    $response = @file_get_contents($url, false, $context);
    
    if ($response === false) {
        $error = error_get_last();
        Logger::error("Failed to generate TTS from Cartesia: " . ($error['message'] ?? 'Unknown error'));
        
        // Log request details for debugging
        Logger::error("Request data: " . json_encode($data));
        
        // Check HTTP response headers for error details
        if (isset($http_response_header)) {
            foreach ($http_response_header as $header) {
                if (stripos($header, 'HTTP/') === 0 || stripos($header, 'content-type') === 0) {
                    Logger::error("Response header: " . $header);
                }
            }
        }
        
        return false;
    }
    
    // Check if response is JSON error
    $jsonCheck = json_decode($response, true);
    if (is_array($jsonCheck) && isset($jsonCheck['error'])) {
        Logger::error("Cartesia API error: " . json_encode($jsonCheck));
        return false;
    }
    
    return $response;
}

/**
 * Map mood to Cartesia emotion string
 * Based on Cartesia's emotion list: https://docs.cartesia.ai/api-reference/tts/bytes
 * Primary emotions (best results): neutral, angry, excited, content, sad, scared
 * 
 * @param string $mood The mood string
 * @return string|null The Cartesia emotion string or null if no mapping
 */
function mapMoodToCartesiaEmotion($mood) {
    // Handle multiple moods separated by pipe - use first one
    $moodArray = explode('|', $mood);
    $primaryMood = strtolower(trim($moodArray[0]));
    
    // Map common moods to Cartesia emotions
    switch ($primaryMood) {
        // Anger/Frustration emotions
        case 'angry':
        case 'furious':
        case 'enraged':
        case 'mad':
            return 'angry';
        case 'outraged':
            return 'outraged';
        case 'irritated':
        case 'annoyed':
        case 'frustrated':
            return 'frustrated';
        case 'agitated':
            return 'agitated';
        case 'threatened':
            return 'threatened';
        case 'disgusted':
            return 'disgusted';
        case 'contempt':
            return 'contempt';
        case 'envious':
            return 'envious';
            
        // Happy/Positive emotions
        case 'happy':
        case 'cheerful':
        case 'joyful':
            return 'happy';
        case 'excited':
        case 'enthusiastic':
            return 'excited';
        case 'elated':
            return 'elated';
        case 'euphoric':
            return 'euphoric';
        case 'triumphant':
            return 'triumphant';
        case 'playful':
        case 'amused':
        case 'joking':
        case 'comedic':
            return 'joking/comedic';
        case 'flirtatious':
            return 'flirtatious';
        case 'content':
        case 'peaceful':
            return 'content';
        case 'serene':
            return 'serene';
        case 'grateful':
            return 'grateful';
        case 'affectionate':
            return 'affectionate';
        case 'proud':
            return 'proud';
        case 'confident':
            return 'confident';
            
        // Sad/Negative emotions
        case 'sad':
        case 'melancholy':
        case 'gloomy':
            return 'sad';
        case 'dejected':
            return 'dejected';
        case 'melancholic':
            return 'melancholic';
        case 'depressed':
        case 'disappointed':
            return 'disappointed';
        case 'sorrowful':
        case 'hurt':
            return 'hurt';
        case 'guilty':
            return 'guilty';
        case 'rejected':
            return 'rejected';
        case 'nostalgic':
            return 'nostalgic';
        case 'wistful':
            return 'wistful';
        case 'bored':
            return 'bored';
        case 'tired':
            return 'tired';
        case 'resigned':
            return 'resigned';
            
        // Surprise/Curiosity emotions
        case 'surprised':
        case 'shocked':
        case 'astonished':
            return 'surprised';
        case 'amazed':
            return 'amazed';
        case 'curious':
        case 'inquisitive':
        case 'interested':
            return 'curious';
        case 'anticipation':
            return 'anticipation';
        case 'mysterious':
            return 'mysterious';
            
        // Fear/Anxiety emotions
        case 'scared':
        case 'afraid':
        case 'fearful':
            return 'scared';
        case 'anxious':
        case 'worried':
            return 'anxious';
        case 'panicked':
            return 'panicked';
        case 'alarmed':
            return 'alarmed';
        case 'hesitant':
            return 'hesitant';
        case 'insecure':
            return 'insecure';
        case 'confused':
            return 'confused';
            
        // Other emotions
        case 'sarcastic':
            return 'sarcastic';
        case 'ironic':
            return 'ironic';
        case 'apologetic':
            return 'apologetic';
        case 'sympathetic':
            return 'sympathetic';
        case 'trust':
            return 'trust';
        case 'distant':
            return 'distant';
        case 'skeptical':
            return 'skeptical';
        case 'contemplative':
            return 'contemplative';
        case 'determined':
            return 'determined';
            
        // Neutral/Calm
        case 'neutral':
        case 'default':
        case 'calm':
            return 'neutral';
            
        default:
            // Check if the mood directly matches a Cartesia emotion
            $cartesiaEmotions = [
                'happy', 'excited', 'enthusiastic', 'elated', 'euphoric', 'triumphant',
                'amazed', 'surprised', 'flirtatious', 'joking/comedic', 'curious',
                'content', 'peaceful', 'serene', 'calm', 'grateful', 'affectionate',
                'trust', 'sympathetic', 'anticipation', 'mysterious', 'angry', 'mad',
                'outraged', 'frustrated', 'agitated', 'threatened', 'disgusted',
                'contempt', 'envious', 'sarcastic', 'ironic', 'sad', 'dejected',
                'melancholic', 'disappointed', 'hurt', 'guilty', 'bored', 'tired',
                'rejected', 'nostalgic', 'wistful', 'apologetic', 'hesitant',
                'insecure', 'confused', 'resigned', 'anxious', 'panicked', 'alarmed',
                'scared', 'neutral', 'proud', 'confident', 'distant', 'skeptical',
                'contemplative', 'determined'
            ];
            
            if (in_array($primaryMood, $cartesiaEmotions)) {
                return $primaryMood;
            }
            
            // Default to neutral if no match
            return 'neutral';
    }
}

/**
 * Map speed setting to Cartesia format
 * 
 * @param string $speed The speed setting
 * @return string|float The Cartesia speed value
 */
function mapSpeedToCartesia($speed) {
    switch (strtolower($speed)) {
        case 'slowest':
            return 'slowest';
        case 'slow':
            return 'slow';
        case 'fast':
            return 'fast';
        case 'fastest':
            return 'fastest';
        case 'normal':
        default:
            return 'normal';
    }
}

/**
 * Get voice name to use for TTS
 * 
 * @return string The voice name
 */
function getCartesiaVoiceName() {
    $voice = isset($GLOBALS["TTS"]["FORCED_VOICE_DEV"]) 
        ? $GLOBALS["TTS"]["FORCED_VOICE_DEV"] 
        : ($GLOBALS["TTS"]["CARTESIA"]["voiceid"] ?? '');
    
    // Fallback to herika_name if voiceid is blank
    if (empty($voice)) {
        $voice = str_replace(" ", "_", mb_strtolower($GLOBALS["HERIKA_NAME"], 'UTF-8'));
        $voice = str_replace("'", "+", $voice);
        $voice = preg_replace('/[^a-zA-Z0-9_+]/u', '', $voice);
    }
    
    if (isset($GLOBALS["PATCH_OVERRIDE_VOICE"])) {
        $voice = $GLOBALS["PATCH_OVERRIDE_VOICE"];
    }
    
    return $voice;
}

// Main TTS function
$GLOBALS["TTS_IN_USE"] = function($textString, $mood, $stringforhash) {
    // Check cache first
    if (isset($GLOBALS["AVOID_TTS_CACHE"]) && $GLOBALS["AVOID_TTS_CACHE"] === false) {
        $cacheFile = dirname(__FILE__) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . 
                    "soundcache/" . md5(trim($stringforhash)) . ".wav";
        if (file_exists($cacheFile)) {
            return $cacheFile;
        }
    }
    
    $startTime = microtime(true);
    
    // Get voice name
    $voiceName = getCartesiaVoiceName();
    
    // Get or create Cartesia voice ID
    $cartesiaVoiceId = getOrCreateCartesiaVoice($voiceName);
    
    if ($cartesiaVoiceId === false) {
        Logger::error("Failed to get or create Cartesia voice for {$voiceName}");
        return false;
    }
    
    // Generate TTS
    $response = generateCartesiaTTS($textString, $cartesiaVoiceId, $mood);
    
    if ($response === false) {
        Logger::error("Failed to generate TTS from Cartesia");
        return false;
    }
    
    // Apply FFMPEG filters if configured
    if (is_array($GLOBALS["TTS_FFMPEG_FILTERS"] ?? null)) {
        $GLOBALS["TTS_FFMPEG_FILTERS"]["adelay"] = "adelay=150|150";
        $FFMPEG_FILTER = '-af "' . implode(",", $GLOBALS["TTS_FFMPEG_FILTERS"]) . '"';
    } else {
        $FFMPEG_FILTER = '-filter:a "adelay=150|150"';
    }
    
    // Save audio files
    $size = strlen($response);
    $oname = dirname(__FILE__) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . 
            "soundcache/" . md5(trim($stringforhash)) . "_o.wav";
    $fname = dirname(__FILE__) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . 
            "soundcache/" . md5(trim($stringforhash)) . ".wav";
    
    file_put_contents($oname, $response);
    
    $startTimeTrans = microtime(true);
    shell_exec("ffmpeg -y -i $oname $FFMPEG_FILTER $fname 2>/dev/null >/dev/null");
    $endTimeTrans = microtime(true) - $startTimeTrans;
    
    // Save debug info
    file_put_contents(
        dirname(__FILE__) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . 
        "soundcache/" . md5(trim($stringforhash)) . ".txt",
        trim($textString) . "\n$FFMPEG_FILTER\n\rtotal call time:" . 
        (microtime(true) - $startTime) . " ms\n\rffmpeg transcoding: $endTimeTrans secs\n\r" .
        "size of wav ($size)\n\rfunction tts(\$textString,\$mood,\$stringforhash)"
    );
    
    $GLOBALS["DEBUG_DATA"][] = (microtime(true) - $startTime) . " secs in Cartesia TTS call";
    
    return "soundcache/" . md5(trim($stringforhash)) . ".wav";
};

