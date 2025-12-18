<?php

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
    
    $voiceSamplePath = "/var/www/html/HerikaServer/data/voices/{$voiceName}.wav";
    
    if (!file_exists($voiceSamplePath)) {
        Logger::error("Voice sample not found: {$voiceSamplePath}");
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
    $language = $GLOBALS["TTS"]["INWORLD"]["language"] ?? 'EN_US';
    
    // Override language if set globally
    if (isset($GLOBALS["PATCH_OVERRIDE_TTS_LANGUAGE"])) {
        $language = mapLanguageToInworld($GLOBALS["PATCH_OVERRIDE_TTS_LANGUAGE"]);
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
 * @return string|false The audio data or false on error
 */
function generateInworldTTS($text, $voiceId, $mood = 'normal') {
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
    if ($temperature < 0) $temperature = 0;
    if ($temperature > 2) $temperature = 2;
    
    // Get language from config
    $language = $GLOBALS["TTS"]["INWORLD"]["language"] ?? 'EN_US';
    
    // Override language if set globally
    if (isset($GLOBALS["PATCH_OVERRIDE_TTS_LANGUAGE"])) {
        $language = mapLanguageToInworld($GLOBALS["PATCH_OVERRIDE_TTS_LANGUAGE"]);
    }
    
    // Prepare audio config
    $audioConfig = array(
        'audioEncoding' => 'LINEAR16',
        'sampleRateHertz' => 22050,
        'speakingRate' => $speed
    );
    
    // Prepare request data
    $data = array(
        'text' => $text,
        'voiceId' => $voiceId,
        'modelId' => $modelId,
        'language' => $language,
        'audioConfig' => $audioConfig,
        'temperature' => $temperature
    );
    
    // Prepare request with Basic Auth
    $authHeader = "Basic " . $apiCredential;
    $options = array(
        'http' => array(
            'method' => 'POST',
            'header' => "Authorization: {$authHeader}\r\n" .
                       "Content-Type: application/json\r\n",
            'content' => json_encode($data),
            'timeout' => 30
        )
    );
    
    $context = stream_context_create($options);
    $response = @file_get_contents($url, false, $context);
    
    if ($response === false) {
        $error = error_get_last();
        Logger::error("Failed to generate TTS from Inworld: " . ($error['message'] ?? 'Unknown error'));
        
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
    
    // Inworld streaming endpoint returns JSON chunks
    // Each chunk has either 'result' or 'error'
    // We need to concatenate all audio chunks
    $audioData = '';
    $lines = explode("\n", $response);
    
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;
        
        $chunk = json_decode($line, true);
        if (is_array($chunk)) {
            if (isset($chunk['error'])) {
                Logger::error("Inworld API error: " . json_encode($chunk['error']));
                return false;
            }
            if (isset($chunk['result']['audioContent'])) {
                // Decode base64 audio content
                $chunkAudio = base64_decode($chunk['result']['audioContent']);
                if ($chunkAudio !== false) {
                    $audioData .= $chunkAudio;
                }
            }
        }
    }
    
    if (empty($audioData)) {
        Logger::error("No audio data received from Inworld TTS");
        return false;
    }
    
    return $audioData;
}

/**
 * Map language code to Inworld format
 * Converts ISO 639-1 codes to Inworld regional codes
 * 
 * @param string $langCode ISO 639-1 language code (e.g., 'en', 'fr')
 * @return string Inworld language code (e.g., 'EN_US', 'FR_FR')
 */
function mapLanguageToInworld($langCode) {
    $langMap = array(
        'en' => 'EN_US',
        'zh' => 'ZH_CN',
        'ko' => 'KO_KR',
        'ja' => 'JA_JP',
        'ru' => 'RU_RU',
        'it' => 'IT_IT',
        'es' => 'ES_ES',
        'pt' => 'PT_BR',
        'de' => 'DE_DE',
        'fr' => 'FR_FR',
        'ar' => 'AR_SA',
        'pl' => 'PL_PL',
        'nl' => 'NL_NL',
        'hi' => 'HI_IN',
        'he' => 'HE_IL'
    );
    
    $langLower = strtolower($langCode);
    
    // If already in Inworld format, return as-is
    if (preg_match('/^[A-Z]{2}_[A-Z]{2}$/', $langCode)) {
        return $langCode;
    }
    
    // Map ISO 639-1 to Inworld format
    if (isset($langMap[$langLower])) {
        return $langMap[$langLower];
    }
    
    // Default to EN_US if unknown
    Logger::info("Unknown language code '{$langCode}', defaulting to EN_US");
    return 'EN_US';
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
    
    // Generate TTS
    $response = generateInworldTTS($textString, $inworldVoiceId, $mood);
    
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
                    $response = generateInworldTTS($textString, $inworldVoiceId, $mood);
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
    
    $GLOBALS["DEBUG_DATA"][] = (microtime(true) - $startTime) . " secs in Inworld TTS call";
    
    return "soundcache/" . md5(trim($stringforhash)) . ".wav";
};

