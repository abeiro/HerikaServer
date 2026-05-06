<?php

require_once(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."lib".DIRECTORY_SEPARATOR."emote_moods.php");

/**
 * Inworld TTS Implementation
 * 
 * This implementation supports:
 * - Voice cloning from local voice samples
 * - Text-to-speech generation using cloned voices
 * - Automatic voice cloning when first needed (like XTTS)
 * - Caching of Inworld voice IDs in conf_opts database
 */


/**
 * is this TTS service capable to express emotions?
 * @return: boolean
*/
function isEmotionCapable() {
    // inworld-tts-1 is not yet capable to handle emotions consistently, max is
    $modelId = $GLOBALS["TTS"]["INWORLD"]["model_id"] ?? 'inworld-tts-1';
    if ($modelId == 'inworld-tts-1')
        return false;
    else 
        return true;
}

/**
 * is this TTS service capable to handle non-verbal vocalization markups?
 * @return: boolean
*/
function isNonVerbalVocalizationCapable() {
    // inworld-tts-1 is not yet capable to handle non-verbal vocalization consistently, max is
    $modelId = $GLOBALS["TTS"]["INWORLD"]["model_id"] ?? 'inworld-tts-1';
    if ($modelId == 'inworld-tts-1')
        return false;
    else 
        return true;
}

//Delivery Style: [laughing], [whispering]

/**
 * list of accepted emotions
 * @return: string
*/
function getEmotionsList() {
    // inworld documented emotions: happy sad calm angry surprised fearful disgusted
    // inworld (max) could handle also other emotions with mixed results
    $s_emo = "[happy] [sad] [calm] [angry] [surprised] [fearful] [disgusted] ". // documented
        " [desire] [desiring] [love] [loving] [arousal] [aroused] [panic] [envy] [envious] ".
        " [jealousy] [jealous] [shame] [ashamed] [gratitude] [proud} "; 
    return $s_emo;
}

/**
 * is in accepted emotions list
 * @param string $s_emotion 
 * @return: string
*/
function isInEmotionsList($s_emotion) {
    $b_res = false;
    $s_list = getEmotionsList();
    if (!stripos($s_list, $s_emotion) === false)
        $b_res = true;
    return $b_res;
}

/**
 * convert emotion to closest emotion from accepted emotions list
 * @param string $s_emotion - emotion
 * @return: string
*/
function convertEmotion($s_emotion) {
    //documented: [happy], [sad], [angry], [surprised], [fearful], [disgusted]
    //inworld tts (not max) most frequent refusals: aroused anxious nervous worried embarrassed resentful
    $s_emo = strtolower(trim($s_emotion));
    $s_res = "";
    if (strlen($s_emo) > 2) {
        if ($s_emo == 'aroused') 
            $s_res = 'happy';
        if ($s_emo == 'arousal') 
            $s_res = 'happy';
        elseif ($s_emo == 'anxious') 
            $s_res = 'fearful';
        elseif ($s_emo == 'nervous') 
            $s_res = 'fearful';
        elseif ($s_emo == 'worried') 
            $s_res = 'fearful';
        elseif ($s_emo == 'embarrassed') 
            $s_res = 'ashamed';
        elseif ($s_emo == 'resentful') 
            $s_res = 'angry';
        elseif ($s_emo == 'offended') 
            $s_res = 'angry';
        elseif ($s_emo == 'grieving') 
            $s_res = 'sad';
        elseif ($s_emo == 'disappointed') 
            $s_res = 'sad';
        elseif ($s_emo == 'joy') 
            $s_res = 'happy';
        elseif ($s_emo == 'joyful') 
            $s_res = 'happy';
    }
    return $s_res;
}

/**
 * list of accepted non-verbal vocalization markups
 * @return: string
*/
function getNonVerbalVocalizationList() {
    //non-verbal vocalization markups add in non-verbal sounds based on where they are placed in the text.
    //[breathe] [clear_throat] [cough] [laugh] [sigh] [yawn]
    return "[breathe] [clear_throat] [cough] [laugh] [sigh] [yawn]";
}

/**
 * is in accepted non-verbal vocalization markups list
 * @param string - markup string
 * @return: boolean
*/
function isInNonVerbalVocalizationList($s_markup) {
    $b_res = false;
    $s_list = getNonVerbalVocalizationList();
    if (!stripos($s_list, $s_markup) === false)
        $b_res = true;
    return $b_res;
}


/**
 * Get or create Inworld voice ID for a given voice sample
 * 
 * @param string $voiceName The name of the voice (without extension)
 * @return string|false The Inworld voice ID or false on error
 */
function getOrCreateInworldVoice($voiceName) {
    global $db;
    
    if (!isset($GLOBALS["db"]) || !$GLOBALS["db"]) {
        require_once(__DIR__ . "/../lib/{$GLOBALS["DBDRIVER"]}.class.php");
    }
    if (!isset($GLOBALS["db"]) || !$GLOBALS["db"]) {
        $GLOBALS["db"] = new sql();
    }
    
    $db = $GLOBALS["db"];
    
    // Check if we already have an Inworld voice ID for this voice
    $optKey = "inworld_voice_id_{$voiceName}";
    $optKeyEscaped = $db->escape($optKey);
    $row = $db->fetchOne("SELECT value FROM conf_opts WHERE id = '{$optKeyEscaped}'");
    
    if (is_array($row) && !empty($row['value'])) {
        Logger::info("Using cached Inworld voice ID for {$voiceName}: {$row['value']}");
        return $row['value'];
    }
    
    // No cached voice ID, need to clone the voice
    Logger::info("No cached Inworld voice ID found for {$voiceName}, cloning voice...");
    
    // Try multiple path formats
    $baseDir = dirname(__FILE__);
    $possiblePaths = array(
        $baseDir . "/../data/voices/{$voiceName}.wav",
        $baseDir . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "data" . DIRECTORY_SEPARATOR . "voices" . DIRECTORY_SEPARATOR . "{$voiceName}.wav",
        "/var/www/html/HerikaServer/data/voices/{$voiceName}.wav",
        __DIR__ . "/../data/voices/{$voiceName}.wav"
    );
    
    $voiceSamplePath = null;
    foreach ($possiblePaths as $testPath) {
        $normalized = realpath($testPath);
        if ($normalized !== false && file_exists($normalized)) {
            $voiceSamplePath = $normalized;
            break;
        }
    }
    
    if ($voiceSamplePath === null || !file_exists($voiceSamplePath)) {
        Logger::error("Voice sample not found: {$voiceName}.wav");
        Logger::error("Searched paths:");
        foreach ($possiblePaths as $testPath) {
            $normalized = realpath($testPath);
            Logger::error("  - {$testPath} " . ($normalized !== false && file_exists($normalized) ? "(found)" : "(not found)"));
        }
        // Check if voices directory exists
        $voicesDir = dirname(__FILE__) . "/../data/voices";
        if (!is_dir($voicesDir)) {
            Logger::error("Voices directory does not exist: {$voicesDir}");
        } else {
            Logger::error("Voices directory exists but file not found. Available files: " . implode(", ", array_slice(scandir($voicesDir), 2)));
        }
        return false;
    }
    
    $fileSize = filesize($voiceSamplePath);
    Logger::info("Found voice sample at {$voiceSamplePath} (size: " . number_format($fileSize) . " bytes)");
    
    $inworldVoiceId = cloneVoiceToInworld($voiceName, $voiceSamplePath);
    
    if ($inworldVoiceId === false) {
        Logger::error("Failed to clone voice {$voiceName} to Inworld");
        return false;
    }
    
    // Cache the voice ID in the database
    $optKeyEscaped = $db->escape($optKey);
    $voiceIdEscaped = $db->escape($inworldVoiceId);
    $db->execQuery(
        "INSERT INTO conf_opts (id, value) VALUES ('{$optKeyEscaped}', '{$voiceIdEscaped}') 
         ON CONFLICT(id) DO UPDATE SET value = '{$voiceIdEscaped}'"
    );
    
    Logger::info("Successfully cloned voice {$voiceName} to Inworld with ID: {$inworldVoiceId}");
    
    return $inworldVoiceId;
}

/**
 * Clone a voice to Inworld
 * 
 * @param string $voiceName The name to give the voice
 * @param string $voiceSamplePath Path to the voice sample file
 * @return string|false The Inworld voice ID or false on error
 */
function cloneVoiceToInworld($voiceName, $voiceSamplePath) {
    $apiCredential = '';
    
    // Try to get API credential from API badge first
    try {
        global $db;
        if (!isset($GLOBALS["db"]) || !$GLOBALS["db"]) {
            require_once(__DIR__ . "/../lib/{$GLOBALS["DBDRIVER"]}.class.php");
        }
        if (!isset($GLOBALS["db"]) || !$GLOBALS["db"]) {
            $GLOBALS["db"] = new sql();
        }
        $row = $GLOBALS["db"]->fetchOne("SELECT api_key FROM core_api_badge WHERE lower(label)='inworld' LIMIT 1");
        if (is_array($row) && !empty($row['api_key'])) {
            $apiCredential = trim($row['api_key']);
        }
    } catch (Throwable $_e) {}
    
    if (empty($apiCredential)) {
        Logger::error("Inworld API credential not found. Please set it in the API Badge page.");
        return false;
    }
    
    // Get workspace ID from config
    $workspace = $GLOBALS["TTS"]["INWORLD"]["workspace"] ?? '';
    if (empty($workspace)) {
        Logger::error("Inworld workspace ID not configured. Please set it in TTS settings.");
        return false;
    }
    
    // Ensure workspace format is correct (workspaces/{workspace})
    if (strpos($workspace, 'workspaces/') !== 0) {
        $workspace = "workspaces/{$workspace}";
    }
    
    Logger::info("Cloning voice {$voiceName} to Inworld...");
    
    $url = "https://api.inworld.ai/voices/v1/{$workspace}/voices:clone";
    
    // Get language from config
    $language = $GLOBALS["TTS"]["INWORLD"]["language"] ?? 'en-US';
    
    // Override language if set globally
    if (isset($GLOBALS["PATCH_OVERRIDE_TTS_LANGUAGE"])) {
        $language = mapLanguageToInworld($GLOBALS["PATCH_OVERRIDE_TTS_LANGUAGE"]);
    } else {
        $language = mapLanguageToInworld($language);
    }
    
    // Read audio file and encode to base64
    $audioContent = file_get_contents($voiceSamplePath);
    if ($audioContent === false) {
        Logger::error("Failed to read voice file: {$voiceSamplePath}");
        return false;
    }
    Logger::info("Read " . number_format(strlen($audioContent)) . " bytes from voice file");
    
    $audioBase64 = base64_encode($audioContent);
    
    // Prepare request data
    $data = array(
        'displayName' => $voiceName,
        'langCode' => $language,
        'voiceSamples' => array(
            array(
                'audioData' => $audioBase64
            )
        ),
        'description' => "Voice generated by CHIM for NPC: {$voiceName}"
    );
    
    // Prepare request with Basic Auth
    $authHeader = "Basic " . $apiCredential;
    $options = array(
        'http' => array(
            'method' => 'POST',
            'header' => "Authorization: {$authHeader}\r\n" .
                       "Content-Type: application/json\r\n",
            'content' => json_encode($data),
            'timeout' => 60
        )
    );
    
    $context = stream_context_create($options);
    $response = @file_get_contents($url, false, $context);
    
    if ($response === false) {
        $error = error_get_last();
        $errorMsg = $error['message'] ?? 'Unknown error';
        Logger::error("Failed to clone voice to Inworld: " . $errorMsg);
        
        // Log HTTP response headers for debugging
        if (isset($http_response_header)) {
            Logger::error("HTTP Response Headers: " . json_encode($http_response_header));
        }
        
        // Provide helpful message for common errors
        if (strpos($errorMsg, '401') !== false) {
            Logger::error("Inworld returned 401 Unauthorized. Your API credential may be invalid.");
        } else if (strpos($errorMsg, '403') !== false) {
            Logger::error("Inworld returned 403 Forbidden. Please check your workspace ID and API permissions.");
        } else if (strpos($errorMsg, '404') !== false) {
            Logger::error("Inworld returned 404 Not Found. Please verify your workspace ID is correct.");
        }
        
        return false;
    }
    
    $result = json_decode($response, true);
    
    if (!isset($result['voice']['voiceId'])) {
        Logger::error("Invalid response from Inworld clone API: " . $response);
        return false;
    }
    
    return $result['voice']['voiceId'];
}

/**
 * Generate TTS audio from Inworld
 * 
 * @param string $text The text to synthesize
 * @param string $voiceId The Inworld voice ID
 * @param string $mood The mood/emotion for the voice (not used by Inworld currently)
 * @param string|null $outputFile Optional file path to write streaming chunks directly
 * @return string|false The audio data or false on error
 */
function generateInworldTTS($text, $voiceId, $mood = 'normal', $outputFile = null) {
    $apiCredential = '';
    
    // Try to get API credential from API badge first
    try {
        global $db;
        if (!isset($GLOBALS["db"]) || !$GLOBALS["db"]) {
            require_once(__DIR__ . "/../lib/{$GLOBALS["DBDRIVER"]}.class.php");
        }
        if (!isset($GLOBALS["db"]) || !$GLOBALS["db"]) {
            $GLOBALS["db"] = new sql();
        }
        $row = $GLOBALS["db"]->fetchOne("SELECT api_key FROM core_api_badge WHERE lower(label)='inworld' LIMIT 1");
        if (is_array($row) && !empty($row['api_key'])) {
            $apiCredential = trim($row['api_key']);
        }
    } catch (Throwable $_e) {}
    
    if (empty($apiCredential)) {
        Logger::error("Inworld API credential not found");
        return false;
    }
    
    $url = "https://api.inworld.ai/tts/v1/voice:stream";
    
    // Get model ID
    $modelId = $GLOBALS["TTS"]["INWORLD"]["model_id"] ?? 'inworld-tts-1';
    
    // Get speed setting (speakingRate: 0.5-1.5, default 1.0)
    $speed = $GLOBALS["TTS"]["INWORLD"]["speed"] ?? 1.0;
    $speed = floatval($speed);
    if ($speed < 0.5) $speed = 0.5;
    if ($speed > 1.5) $speed = 1.5;
    
    // Get temperature
    $temperature = $GLOBALS["TTS"]["INWORLD"]["temperature"] ?? 1.1;
    $temperature = floatval($temperature);
    if ($temperature < 0.0) $temperature = 0.0;
    if ($temperature > 2.0) $temperature = 2.0;
    
    // Get language from config
    $language = $GLOBALS["TTS"]["INWORLD"]["language"] ?? 'en-US';
    
    // Override language if set globally
    if (isset($GLOBALS["PATCH_OVERRIDE_TTS_LANGUAGE"])) {
        $language = mapLanguageToInworld($GLOBALS["PATCH_OVERRIDE_TTS_LANGUAGE"]);
    } else {
        $language = mapLanguageToInworld($language);
    }
    
    // Prepare audio config
    // Inworld returns LINEAR16 PCM (signed 16-bit little-endian) at 22050 Hz, mono
    $audioConfig = array(
        'audioEncoding' => 'LINEAR16',
        'sampleRateHertz' => 22050,
        'speakingRate' => $speed
    );
    
    // Store audio format for FFmpeg processing
    $GLOBALS['INWORLD_AUDIO_FORMAT'] = array(
        'format' => 's16le',      // signed 16-bit little-endian
        'sample_rate' => 22050,   // 22050 Hz
        'channels' => 1           // mono
    );
    
    // Prepare request data
    $data = array(
        'text' => $text,
        'voiceId' => $voiceId,
        'modelId' => $modelId,
        'language' => $language,        
        'audioConfig' => $audioConfig,
        //'applyTextNormalization' => 'ON', // APPLY_TEXT_NORMALIZATION_UNSPECIFIED (default), ON, OFF 
        'temperature' => $temperature
    );
    
    // Prepare request with Basic Auth
    $authHeader = "Basic " . $apiCredential;
    
    // Make a simple POST request and get the complete response
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Return the response
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        "Authorization: {$authHeader}",
        "Content-Type: application/json"
    ));
    curl_setopt($ch, CURLOPT_TIMEOUT, 120); // Timeout for longer audio
    
    // Execute request
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if (!empty($curlError)) {
        Logger::error("Failed to generate TTS from Inworld: " . $curlError);
        Logger::error("Request data: " . json_encode($data));
        return false;
    }
    
    if ($httpCode !== 200) {
        Logger::error("Inworld API returned HTTP code {$httpCode}");
        Logger::error("Response: " . substr($response, 0, 500));
        return false;
    }
    
    if (empty($response)) {
        Logger::error("Empty response from Inworld TTS");
        return false;
    }
    
    Logger::debug("Inworld TTS response received: " . number_format(strlen($response)) . " bytes");
    
    // Parse SSE response to extract audio chunks
    $audioData = '';
    $chunkCount = 0;
    $lines = explode("\n", $response);
    
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;
        
        // Skip SSE data prefix if present
        if (strpos($line, 'data: ') === 0) {
            $line = substr($line, 6);
        }
        
        // Skip SSE event and comment lines
        if (strpos($line, 'event:') === 0 || $line === ':') {
            continue;
        }
        
        // Try to decode as JSON
        $chunk = json_decode($line, true);
        if (is_array($chunk)) {
            if (isset($chunk['error'])) {
                Logger::error("Inworld API error: " . json_encode($chunk['error']));
                return false;
            }
            if (isset($chunk['result']['audioContent'])) {
                // Decode base64 audio content
                $chunkAudio = base64_decode($chunk['result']['audioContent'], true);
                if ($chunkAudio !== false) {
                    $chunkCount++;
                    
                    // IMPORTANT: Inworld returns each chunk with a WAV header
                    // We need to strip the WAV header (first 44 bytes) from each chunk
                    // and only keep the raw PCM data
                    $wavHeaderSize = 44;
                    if (strlen($chunkAudio) > $wavHeaderSize) {
                        // Check if this chunk starts with a WAV header (RIFF signature)
                        if (substr($chunkAudio, 0, 4) === 'RIFF') {
                            Logger::trace("Chunk #{$chunkCount} has WAV header, stripping it");
                            // Strip the WAV header, keep only raw PCM data
                            $chunkAudio = substr($chunkAudio, $wavHeaderSize);
                        }
                    }
                    
                    $audioData .= $chunkAudio;
                } else {
                    Logger::warn("Failed to decode base64 audio chunk #{$chunkCount}");
                }
            }
        }
    }
    
    Logger::debug("Extracted {$chunkCount} audio chunks, total audio size: " . number_format(strlen($audioData)) . " bytes (WAV headers stripped)");
    
    if (empty($audioData)) {
        Logger::error("No audio data extracted from Inworld response");
        return false;
    }
    
    // Write complete audio data to file
    if ($outputFile !== null) {
        $written = file_put_contents($outputFile, $audioData);
        if ($written === false) {
            Logger::error("Failed to write audio data to file: {$outputFile}");
            return false;
        }
        Logger::debug("Wrote " . number_format($written) . " bytes of audio data to file");
        return $outputFile;
    }
    
    return $audioData;
}

/**
 * Map language code to Inworld format
 * Converts ISO 639-1 codes to Inworld regional codes
 * 
 * @param string $langCode ISO 639-1 language code (e.g., 'en', 'fr')
 * @return string Inworld language code (e.g., 'en-US', 'fr-FR')
 */
function mapLanguageToInworld($langCode) {
    $langMap = array(
        'en' => 'en-US',
        'zh' => 'zh-CN',
        'ko' => 'ko-KR',
        'ja' => 'ja-JP',
        'ru' => 'ru-RU',
        'it' => 'it-IT',
        'es' => 'es-ES',
        'pt' => 'pt-BR',
        'de' => 'de-DE',
        'fr' => 'fr-FR',
        'ar' => 'ar-SA',
        'pl' => 'pl-PL',
        'nl' => 'nl-NL',
        'hi' => 'hi-IN',
        'he' => 'he-IL'
    );
    $legacyLangMap = array(
        'EN_US' => 'en-US',
        'ZH_CN' => 'zh-CN',
        'KO_KR' => 'ko-KR',
        'JA_JP' => 'ja-JP',
        'RU_RU' => 'ru-RU',
        'IT_IT' => 'it-IT',
        'ES_ES' => 'es-ES',
        'PT_BR' => 'pt-BR',
        'DE_DE' => 'de-DE',
        'FR_FR' => 'fr-FR',
        'AR_SA' => 'ar-SA',
        'PL_PL' => 'pl-PL',
        'NL_NL' => 'nl-NL',
        'HI_IN' => 'hi-IN',
        'HE_IL' => 'he-IL'
    );
    
    $langCode = trim((string) $langCode);
    $langLower = strtolower($langCode);

    if ($langCode === '') {
        return 'en-US';
    }

    // Convert legacy underscore codes to the new BCP-47 format.
    if (isset($legacyLangMap[$langCode])) {
        return $legacyLangMap[$langCode];
    }

    // If already in BCP-47 format, preserve the configured value.
    if (preg_match('/^[a-z]{2}-[A-Z]{2}$/', $langCode)) {
        return $langCode;
    }
    
    // Map ISO 639-1 to Inworld format
    if (isset($langMap[$langLower])) {
        return $langMap[$langLower];
    }
    
    // Try tolerant normalization for inputs like en_us or EN-US.
    $langNormalized = str_replace('_', '-', $langCode);
    if (preg_match('/^([A-Za-z]{2})-([A-Za-z]{2})$/', $langNormalized, $matches)) {
        return strtolower($matches[1]) . '-' . strtoupper($matches[2]);
    }

    // Default to en-US if unknown
    Logger::info("Unknown Inworld language code '{$langCode}', defaulting to en-US");
    return 'en-US';
}

/**
 * Get voice name to use for TTS
 * 
 * @return string The voice name
 */
function getInworldVoiceName() {
    require_once(__DIR__ . "/../lib/utils.php");
    
    $voice = isset($GLOBALS["TTS"]["FORCED_VOICE_DEV"]) 
        ? $GLOBALS["TTS"]["FORCED_VOICE_DEV"] 
        : ($GLOBALS["TTS"]["INWORLD"]["voiceid"] ?? '');
    
    // If voiceid is blank, try to get voicetype from database
    if (empty($voice)) {
        // Get codename from HERIKA_NAME (NPC name)
        $codename = npcNameToCodename($GLOBALS["HERIKA_NAME"] ?? '');
        
        if (!empty($codename)) {
            // Try to get voiceid from NPC profile first
            try {
                if (!isset($GLOBALS["db"]) || !$GLOBALS["db"]) {
                    require_once(__DIR__ . "/../lib/{$GLOBALS["DBDRIVER"]}.class.php");
                    $GLOBALS["db"] = new sql();
                }
                $npcRow = $GLOBALS["db"]->fetchOne(
                    "SELECT voiceid FROM core_npc_master WHERE lower(npc_name) = lower('" . $GLOBALS["db"]->escape($GLOBALS["HERIKA_NAME"] ?? '') . "') LIMIT 1"
                );
                if (is_array($npcRow) && !empty($npcRow['voiceid'])) {
                    $voice = trim($npcRow['voiceid']);
                }
            } catch (Throwable $_e) {}
            
            // If still empty, get voicetype from conf_opts
            if (empty($voice)) {
                try {
                    if (!isset($GLOBALS["db"]) || !$GLOBALS["db"]) {
                        require_once(__DIR__ . "/../lib/{$GLOBALS["DBDRIVER"]}.class.php");
                        $GLOBALS["db"] = new sql();
                    }
                    $cn = $GLOBALS["db"]->escape("Voicetype/$codename");
                    $vtype = $GLOBALS["db"]->fetchAll("SELECT value FROM conf_opts WHERE id = '$cn' LIMIT 1");
                    if (is_array($vtype) && !empty($vtype[0]["value"])) {
                        $voicetypeString = $vtype[0]["value"];
                        $voicetype = explode("\\", $voicetypeString);
                        if (isset($voicetype[3]) && !empty($voicetype[3])) {
                            $voice = strtolower($voicetype[3]);
                        }
                    }
                } catch (Throwable $_e) {}
            }
        }
        
        // Final fallback to herika_name if voicetype not found
        if (empty($voice)) {
            $voice = str_replace(" ", "_", mb_strtolower($GLOBALS["HERIKA_NAME"] ?? '', 'UTF-8'));
            $voice = str_replace("'", "+", $voice);
            $voice = preg_replace('/[^a-zA-Z0-9_+]/u', '', $voice);
        }
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
    $voiceName = getInworldVoiceName();
    
    // Get or create Inworld voice ID
    $inworldVoiceId = getOrCreateInworldVoice($voiceName);
    
    if ($inworldVoiceId === false || empty($inworldVoiceId)) {
        Logger::error("Failed to get or create Inworld voice for {$voiceName}");
        return false;
    }
    
    // Validate voice ID format (should be workspace__voice format)
    if (strpos($inworldVoiceId, '__') === false) {
        Logger::error("Invalid Inworld voice ID format for {$voiceName}: {$inworldVoiceId}");
        // Clear invalid cache and retry
        try {
            if (!isset($GLOBALS["db"]) || !$GLOBALS["db"]) {
                require_once(__DIR__ . "/../lib/{$GLOBALS["DBDRIVER"]}.class.php");
                $GLOBALS["db"] = new sql();
            }
            $optKey = "inworld_voice_id_{$voiceName}";
            $optKeyEscaped = $GLOBALS["db"]->escape($optKey);
            $GLOBALS["db"]->execQuery("DELETE FROM conf_opts WHERE id = '{$optKeyEscaped}'");
            Logger::info("Cleared invalid voice ID cache for {$voiceName}, retrying...");
            $inworldVoiceId = getOrCreateInworldVoice($voiceName);
            if ($inworldVoiceId === false || empty($inworldVoiceId)) {
                Logger::error("Failed to get valid Inworld voice ID after cache clear for {$voiceName}");
                return false;
            }
        } catch (Throwable $e) {
            Logger::error("Error clearing invalid voice ID cache: " . $e->getMessage());
            return false;
        }
    }
    
    // Prepare output file paths
    // Use .pcm extension for raw PCM data (no WAV header)
    $oname = dirname(__FILE__) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . 
            "soundcache/" . md5(trim($stringforhash)) . "_o.pcm";
            
    // pronunciation: 
    $textString = str_ireplace(
        ['Herika',    'Aeter',      'f-f-f' ], // Change from
        ['/hˈɛɹɪ.kə/','/ˈiːθə(r)/', 'f... f'], // to this. Could be International Phonetic Alphabet (IPA) format, wrapped in slashes. 
        $textString
    );
    
    // emotions:
    $b_emotions = isset($GLOBALS["LAST_LLM_RESPONSE"]) && ($GLOBALS['use_emotions_expression'] ?? false);
    if (isEmotionCapable() && $b_emotions) {
        $s_mood = strtolower(extractFirstEmoteMood($GLOBALS["LAST_LLM_RESPONSE"]["mood"] ?? ""));
        if (isset($GLOBALS["FORCE_MOOD"]) && (strlen($GLOBALS["FORCE_MOOD"]) > 0)) {
            $s_mood = strtolower(extractFirstEmoteMood($GLOBALS["FORCE_MOOD"]));
        }
        
        if (($s_mood == "whispering") || ($s_mood == "laughing"))  {
            $textString = "[{$s_mood}] " . $textString; 
        } else {  
            $s_emo_int = $GLOBALS["LAST_LLM_RESPONSE"]["emotion_intensity"] ?? "";
            if (($s_emo_int == "strong") || ($s_emo_int == "moderate")) {
                $s_emo = strtolower($GLOBALS["LAST_LLM_RESPONSE"]["emotion"] ?? "");
                if (strlen($s_emo) > 0) {
                    if (isInEmotionsList($s_emo)) {
                        $textString = "[$s_emo] " . $textString; 
                    } else {
                        $s_emo2 = convertEmotion($s_emo);
                        if (strlen($s_emo2) > 0)
                            $textString = "[$s_emo2] " . $textString; 
                    }
                    
                    $b_inject = ($GLOBALS["TTS_INJECT_NONVERBAL_VOCALIZATION"] ?? false) && isNonVerbalVocalizationCapable() && ($s_emo_int == "strong"); // spice with non-verbals 
                    if ($b_inject) { // add some random non-verbal vocalization
                        $n_rnd = rand(0,19); // 
                        if ($n_rnd == 0) { 
                            if (isInNonVerbalVocalizationList("sigh")) {
                                $i_pos = strrpos($textString, '...'); // last ellipsis
                                if ($i_pos !== false) {
                                    if (($i_pos > 16) && ($i_pos < (strlen($textString) - 16))) { // not close to begining, not at the end
                                        $textString = substr_replace($textString, '... [sigh] ', $i_pos, 3);
                                        //error_log(" sigh "); // debug
                                    }
                                }
                            }
                        } elseif ($n_rnd == 1) { 
                            if (isInNonVerbalVocalizationList("breathe")) {
                                $i_pos = strrpos($textString, '...'); // last ellipsis
                                if ($i_pos !== false) {
                                    if (($i_pos > 16) && ($i_pos < (strlen($textString) - 16))) { // not close to begining, not at the end
                                        $textString = substr_replace($textString, '... [breathe] ', $i_pos, 3);
                                        //error_log(" breathe "); // debug
                                    }
                                }
                            }
                        } elseif ($n_rnd == 2) { 
                            if (isInNonVerbalVocalizationList("laugh")) {
                                // joy happy amusement: laugh
                                //$b_laugh = true; // debug 
                                $b_laugh = (!strpos('playful,delighted,amused', $s_mood) === false) || 
                                           (!strpos('happy,happiness,amusement,amused,joy,joyful', $s_emo) === false); 
                                if ($b_laugh) {
                                    $i_pos = strrpos($textString, '...'); // last ellipsis
                                    if ($i_pos !== false) {
                                        if (($i_pos > 16) && ($i_pos < (strlen($textString) - 16))) { // not close to begining, not at the end
                                            $textString = substr_replace($textString, '... [laugh] ', $i_pos, 3);
                                            //error_log(" laugh "); // debug
                                        }
                                    }
                                }
                            }
                        }
                        //2do: Emphasis Markers: Asterisks around a word (e.g. *really*)
                    }
                }
            }
        } 
    } // --- endif emotions

    // Generate TTS with streaming directly to file
    $response = generateInworldTTS($textString, $inworldVoiceId, $mood, $oname);
    
    if ($response === false) {
        Logger::error("Failed to generate TTS from Inworld for voice {$voiceName} (ID: {$inworldVoiceId})");
        // If TTS generation fails, the voice ID might be invalid - clear cache and retry once
        try {
            if (!isset($GLOBALS["db"]) || !$GLOBALS["db"]) {
                require_once(__DIR__ . "/../lib/{$GLOBALS["DBDRIVER"]}.class.php");
                $GLOBALS["db"] = new sql();
            }
            $optKey = "inworld_voice_id_{$voiceName}";
            $optKeyEscaped = $GLOBALS["db"]->escape($optKey);
            $cachedId = $GLOBALS["db"]->fetchOne("SELECT value FROM conf_opts WHERE id = '{$optKeyEscaped}'");
            // Only retry if we had a cached ID (not a fresh clone attempt)
            if (is_array($cachedId) && !empty($cachedId['value']) && $cachedId['value'] === $inworldVoiceId) {
                Logger::info("TTS generation failed with cached voice ID, clearing cache and retrying clone for {$voiceName}...");
                $GLOBALS["db"]->execQuery("DELETE FROM conf_opts WHERE id = '{$optKeyEscaped}'");
                $inworldVoiceId = getOrCreateInworldVoice($voiceName);
                if ($inworldVoiceId !== false && !empty($inworldVoiceId)) {
                    $response = generateInworldTTS($textString, $inworldVoiceId, $mood, $oname);
                    if ($response === false) {
                        Logger::error("Failed to generate TTS from Inworld after retry for {$voiceName}");
                        return false;
                    }
                } else {
                    Logger::error("Failed to get valid Inworld voice ID after retry for {$voiceName}");
                    return false;
                }
            } else {
                return false;
            }
        } catch (Throwable $e) {
            Logger::error("Error during voice ID retry: " . $e->getMessage());
            return false;
        }
    }
    
    // Apply FFMPEG filters if configured
    // Keep it simple - just add adelay like Cartesia does
    if (is_array($GLOBALS["TTS_FFMPEG_FILTERS"] ?? null)) {
        $GLOBALS["TTS_FFMPEG_FILTERS"]["adelay"] = "adelay=150|150";
        $FFMPEG_FILTER = '-af "' . implode(",", $GLOBALS["TTS_FFMPEG_FILTERS"]) . '"';
    } else {
        $FFMPEG_FILTER = '-filter:a "adelay=150|150"';
    }
    
    // Response is either file path (if streaming) or audio data
    if ($response === $oname) {
        // Streaming was used, file already exists
        $size = filesize($oname);
    } else {
        // Fallback: write audio data to file
        $size = strlen($response);
        file_put_contents($oname, $response);
    }
    
    $fname = dirname(__FILE__) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . 
            "soundcache/" . md5(trim($stringforhash)) . ".wav";
    
    // Inworld returns raw LINEAR16 PCM (s16le) at 22050 Hz, mono
    // FFmpeg needs explicit format specification for raw PCM input
    $audioFormat = $GLOBALS['INWORLD_AUDIO_FORMAT'] ?? array('format' => 's16le', 'sample_rate' => 22050, 'channels' => 1);
    $ffmpegInputFormat = "-f {$audioFormat['format']} -ar {$audioFormat['sample_rate']} -ac {$audioFormat['channels']}";
    
    // Verify raw audio file exists and has content
    if (!file_exists($oname) || filesize($oname) === 0) {
        Logger::error("Raw audio file is missing or empty: {$oname}");
        return false;
    }
    
    $rawAudioSize = filesize($oname);
    Logger::debug("Raw audio file size: " . number_format($rawAudioSize) . " bytes before FFmpeg processing");
    
    $startTimeTrans = microtime(true);
    // Specify input format for raw PCM
    // Use careful processing to avoid artifacts
    $ffmpegCmd = "ffmpeg -y {$ffmpegInputFormat} -i \"$oname\" ";
    
    // Apply audio filters
    if (!empty($FFMPEG_FILTER)) {
        $ffmpegCmd .= "{$FFMPEG_FILTER} ";
    }
    
    // Output settings: pcm_s16le codec at 22050Hz, ensure no resampling artifacts
    // Use same format as input to minimize processing
    $ffmpegCmd .= "-c:a pcm_s16le -ar 22050 -ac 1 \"$fname\" 2>&1";
    
    Logger::debug("FFmpeg command: {$ffmpegCmd}");
    $ffmpegOutput = shell_exec($ffmpegCmd);
    $endTimeTrans = microtime(true) - $startTimeTrans;
    
    Logger::debug("FFmpeg processing took " . number_format($endTimeTrans, 3) . " seconds");
    
    // Check if output file was created successfully
    if (!file_exists($fname) || filesize($fname) === 0) {
        Logger::error("FFmpeg failed to create output file: {$fname}");
        Logger::error("FFmpeg command: {$ffmpegCmd}");
        Logger::error("FFmpeg output: " . substr($ffmpegOutput, 0, 500));
        return false;
    }
    
    $finalAudioSize = filesize($fname);
    Logger::debug("Final audio file size: " . number_format($finalAudioSize) . " bytes after FFmpeg processing");
    
    // Save debug info
    file_put_contents(
        dirname(__FILE__) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . 
        "soundcache/" . md5(trim($stringforhash)) . ".txt",
        trim($textString) . "\n$FFMPEG_FILTER\n\rtotal call time:" . 
        (microtime(true) - $startTime) . " ms\n\rffmpeg transcoding: $endTimeTrans secs\n\r" .
        "size of wav ($size)\n\rfunction tts(\$textString,\$mood,\$stringforhash)"
    );
    
    $GLOBALS["DEBUG_DATA"][] = (microtime(true) - $startTime) . " secs in Inworld TTS call";
    
    return "soundcache/" . md5(trim($stringforhash)) . ".wav";
};

