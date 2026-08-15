<?php

/**
 * Cartesia TTS Implementation
 * 
 * This implementation supports:
 * - Voice cloning from local voice samples
 * - Text-to-speech generation using cloned voices
 * - Automatic voice cloning when first needed (like XTTS)
 * - Connector-scoped caching of Cartesia voice IDs in conf_opts
 */

function ensureCartesiaDb() {
    if (!isset($GLOBALS["db"]) || !$GLOBALS["db"]) {
        require_once(__DIR__ . "/../lib/{$GLOBALS["DBDRIVER"]}.class.php");
    }
    if (!isset($GLOBALS["db"]) || !$GLOBALS["db"]) {
        $GLOBALS["db"] = new sql();
    }
    return $GLOBALS["db"] ?? null;
}

function setCartesiaLastError(string $message): void {
    $GLOBALS["CARTESIA_LAST_ERROR_MESSAGE"] = trim($message);
}

function getCartesiaLastError(): string {
    return trim(strval($GLOBALS["CARTESIA_LAST_ERROR_MESSAGE"] ?? ''));
}

function clearCartesiaLastError(): void {
    unset($GLOBALS["CARTESIA_LAST_ERROR_MESSAGE"]);
}

function resolveCartesiaConnectorRow(): ?array {
    $db = ensureCartesiaDb();
    if (!$db) {
        return null;
    }
    if (!class_exists('TTSConnector')) {
        require_once(__DIR__ . "/../lib/core/tts_connector.class.php");
    }

    $ttsConnector = new TTSConnector();
    $candidateIds = [
        intval($GLOBALS["CHIM_CORE_CURRENT_TTS_CONNECTOR_ID"] ?? 0),
        intval($GLOBALS["CHIM_CORE_CURRENT_PROFILE_DATA"]['tts_connector_id'] ?? 0),
    ];

    foreach ($candidateIds as $candidateId) {
        if ($candidateId <= 0) {
            continue;
        }
        $row = $ttsConnector->getById($candidateId);
        if (is_array($row) && $ttsConnector->normalizeDriverValue($row['driver'] ?? '') === 'cartesia') {
            return $row;
        }
    }

    $rows = array_values(array_filter($ttsConnector->readAll(), function ($row) use ($ttsConnector) {
        return $ttsConnector->normalizeDriverValue($row['driver'] ?? '') === 'cartesia';
    }));
    if (empty($rows)) {
        return null;
    }

    $profileUsageMap = [];
    $usageRows = $db->fetchAll(
        "SELECT tts_connector_id, COUNT(*) AS c FROM core_profiles WHERE tts_connector_id IS NOT NULL GROUP BY tts_connector_id"
    );
    foreach ($usageRows as $usageRow) {
        $profileUsageMap[intval($usageRow['tts_connector_id'] ?? 0)] = intval($usageRow['c'] ?? 0);
    }

    $playerConnectorId = 0;
    $playerRow = $db->fetchOne("SELECT value FROM core_player WHERE id = 'tts_connector_id' LIMIT 1");
    if (is_array($playerRow)) {
        $playerConnectorId = intval($playerRow['value'] ?? 0);
    }

    usort($rows, function ($a, $b) use ($profileUsageMap, $playerConnectorId) {
        $aId = intval($a['id'] ?? 0);
        $bId = intval($b['id'] ?? 0);
        $aPlayer = ($aId > 0 && $aId === $playerConnectorId) ? 1 : 0;
        $bPlayer = ($bId > 0 && $bId === $playerConnectorId) ? 1 : 0;
        if ($aPlayer !== $bPlayer) {
            return $bPlayer <=> $aPlayer;
        }

        $aUsage = $profileUsageMap[$aId] ?? 0;
        $bUsage = $profileUsageMap[$bId] ?? 0;
        if ($aUsage !== $bUsage) {
            return $bUsage <=> $aUsage;
        }

        $aLabel = strtolower(trim(strval($a['label'] ?? '')));
        $bLabel = strtolower(trim(strval($b['label'] ?? '')));
        if ($aLabel !== $bLabel) {
            return $aLabel <=> $bLabel;
        }

        return $aId <=> $bId;
    });

    return $ttsConnector->getById(intval($rows[0]['id'] ?? 0));
}

function hydrateCartesiaConnectorGlobals(): ?array {
    $row = resolveCartesiaConnectorRow();
    if (!is_array($row)) {
        return null;
    }
    if (!class_exists('TTSConnector')) {
        require_once(__DIR__ . "/../lib/core/tts_connector.class.php");
    }
    $ttsConnector = new TTSConnector();
    $ttsConnector->setOldGlobals($row);
    return $row;
}

function getCartesiaActiveConfig(): array {
    $row = null;
    $connectorId = intval($GLOBALS["CHIM_CORE_CURRENT_TTS_CONNECTOR_ID"] ?? 0);
    $apiKey = trim(strval($GLOBALS["TTS"]["CARTESIA"]["API_KEY"] ?? ''));

    if ($connectorId <= 0 || $apiKey === '') {
        $row = hydrateCartesiaConnectorGlobals();
        $connectorId = intval($GLOBALS["CHIM_CORE_CURRENT_TTS_CONNECTOR_ID"] ?? ($row['id'] ?? 0));
        $apiKey = trim(strval($GLOBALS["TTS"]["CARTESIA"]["API_KEY"] ?? ''));
    } else {
        $row = resolveCartesiaConnectorRow();
    }

    $apiBadgeId = intval($row['api_badge_id'] ?? 0);
    if ($apiKey === '' && is_array($row) && $apiBadgeId > 0) {
        $db = ensureCartesiaDb();
        if ($db) {
            $badgeRow = $db->fetchOne("SELECT api_key FROM core_api_badge WHERE id = {$apiBadgeId} LIMIT 1");
            if (is_array($badgeRow) && !empty($badgeRow['api_key'])) {
                $apiKey = trim(strval($badgeRow['api_key']));
            }
        }
    }

    return [
        'connector_id' => $connectorId,
        'api_badge_id' => $apiBadgeId,
        'api_key' => $apiKey,
        'row' => $row,
    ];
}

function getCartesiaVoiceCachePrefix(?array $config = null): string {
    $config = is_array($config) ? $config : getCartesiaActiveConfig();
    $connectorId = intval($config['connector_id'] ?? 0);
    $apiBadgeId = intval($config['api_badge_id'] ?? 0);
    if ($connectorId <= 0 && $apiBadgeId <= 0) {
        return 'cartesia_voice_id_';
    }

    $scopeHash = substr(md5(json_encode([
        'connector_id' => $connectorId,
        'api_badge_id' => $apiBadgeId,
    ])), 0, 12);

    return "cartesia_voice_scope_{$scopeHash}__";
}

function getCartesiaVoiceCachePrefixes(?array $config = null, bool $includeLegacy = true): array {
    $config = is_array($config) ? $config : getCartesiaActiveConfig();
    $prefixes = [];
    $scopedPrefix = getCartesiaVoiceCachePrefix($config);
    if ($scopedPrefix !== 'cartesia_voice_id_') {
        $prefixes[] = $scopedPrefix;
    }
    if ($includeLegacy || empty($prefixes)) {
        $prefixes[] = 'cartesia_voice_id_';
    }
    return array_values(array_unique($prefixes));
}

function getCachedCartesiaVoiceId($voiceName, ?array $config = null): string {
    $db = ensureCartesiaDb();
    if (!$db) {
        return '';
    }

    $config = is_array($config) ? $config : getCartesiaActiveConfig();
    $scopedPrefix = getCartesiaVoiceCachePrefix($config);
    foreach (getCartesiaVoiceCachePrefixes($config, true) as $prefix) {
        $optKeyEscaped = $db->escape($prefix . $voiceName);
        $row = $db->fetchOne("SELECT value FROM conf_opts WHERE id = '{$optKeyEscaped}' LIMIT 1");
        $voiceId = trim(strval($row['value'] ?? ''));
        if ($voiceId === '') {
            continue;
        }
        if ($prefix === 'cartesia_voice_id_' && $scopedPrefix !== 'cartesia_voice_id_') {
            $scopedKeyEscaped = $db->escape($scopedPrefix . $voiceName);
            $voiceIdEscaped = $db->escape($voiceId);
            $db->execQuery(
                "INSERT INTO conf_opts (id, value) VALUES ('{$scopedKeyEscaped}', '{$voiceIdEscaped}')
                 ON CONFLICT(id) DO UPDATE SET value = '{$voiceIdEscaped}'"
            );
        }
        return $voiceId;
    }

    return '';
}

function storeCachedCartesiaVoiceId($voiceName, $voiceId, ?array $config = null): void {
    $db = ensureCartesiaDb();
    if (!$db) {
        return;
    }

    $optKeyEscaped = $db->escape(getCartesiaVoiceCachePrefix($config) . $voiceName);
    $voiceIdEscaped = $db->escape($voiceId);
    $db->execQuery(
        "INSERT INTO conf_opts (id, value) VALUES ('{$optKeyEscaped}', '{$voiceIdEscaped}')
         ON CONFLICT(id) DO UPDATE SET value = '{$voiceIdEscaped}'"
    );
}

function deleteCachedCartesiaVoiceId($voiceName, ?array $config = null, bool $includeLegacy = true): void {
    $db = ensureCartesiaDb();
    if (!$db) {
        return;
    }

    foreach (getCartesiaVoiceCachePrefixes($config, $includeLegacy) as $prefix) {
        $optKeyEscaped = $db->escape($prefix . $voiceName);
        $db->execQuery("DELETE FROM conf_opts WHERE id = '{$optKeyEscaped}'");
    }
}

function getCartesiaVoiceMetadataKey($voiceName, ?array $config = null): string {
    return 'cartesia_voice_meta_' . substr(md5(getCartesiaVoiceCachePrefix($config)), 0, 12) . '__' . $voiceName;
}

function getCartesiaVoiceMetadata($voiceName, ?array $config = null): array {
    $db = ensureCartesiaDb();
    if (!$db) {
        return [];
    }
    $key = $db->escape(getCartesiaVoiceMetadataKey($voiceName, $config));
    $row = $db->fetchOne("SELECT value FROM conf_opts WHERE id = '{$key}' LIMIT 1");
    $decoded = json_decode(strval($row['value'] ?? ''), true);
    return is_array($decoded) ? $decoded : [];
}

function storeCartesiaVoiceMetadata($voiceName, $voiceId, $samplePath, bool $managed, ?array $config = null): void {
    $db = ensureCartesiaDb();
    if (!$db) {
        return;
    }
    $metadata = [
        'voice_id' => trim(strval($voiceId)),
        'managed' => $managed,
        'sample_sha256' => is_file($samplePath) ? hash_file('sha256', $samplePath) : '',
        'created_at' => gmdate('c'),
    ];
    $key = $db->escape(getCartesiaVoiceMetadataKey($voiceName, $config));
    $value = $db->escape(json_encode($metadata, JSON_UNESCAPED_SLASHES));
    $db->execQuery(
        "INSERT INTO conf_opts (id, value) VALUES ('{$key}', '{$value}')
         ON CONFLICT(id) DO UPDATE SET value = '{$value}'"
    );
}

function deleteCartesiaVoiceMetadata($voiceName, ?array $config = null): void {
    $db = ensureCartesiaDb();
    if (!$db) {
        return;
    }
    $key = $db->escape(getCartesiaVoiceMetadataKey($voiceName, $config));
    $db->execQuery("DELETE FROM conf_opts WHERE id = '{$key}'");
}

function findCartesiaVoiceSamplePath($voiceName): string {
    $possiblePaths = [
        __DIR__ . "/../data/voices/{$voiceName}.wav",
        "/var/www/html/HerikaServer/data/voices/{$voiceName}.wav",
    ];
    foreach ($possiblePaths as $testPath) {
        $normalized = realpath($testPath);
        if ($normalized !== false && is_file($normalized)) {
            return $normalized;
        }
    }
    return '';
}

function getCartesiaCachedVoicesMap(bool $includeLegacy = true, ?array $config = null): array {
    $db = ensureCartesiaDb();
    if (!$db) {
        return [];
    }

    $config = is_array($config) ? $config : getCartesiaActiveConfig();
    $cloned = [];
    foreach (getCartesiaVoiceCachePrefixes($config, $includeLegacy) as $prefix) {
        $prefixEscaped = $db->escape($prefix);
        $rows = $db->fetchAll("SELECT id, value FROM conf_opts WHERE id LIKE '{$prefixEscaped}%'");
        foreach ($rows as $row) {
            $voiceName = substr(strval($row['id'] ?? ''), strlen($prefix));
            $voiceId = trim(strval($row['value'] ?? ''));
            if ($voiceName === '' || $voiceId === '') {
                continue;
            }
            if (!isset($cloned[$voiceName])) {
                $cloned[$voiceName] = $voiceId;
            }
        }
    }

    return $cloned;
}

function normalizeCartesiaVoiceLookupToken($value): string {
    $value = strtolower(trim(strval($value)));
    if ($value === '') {
        return '';
    }

    return preg_replace('/[^a-z0-9]+/', '', $value);
}

function getCartesiaRemoteVoicesCacheKey(?array $config = null): string {
    $config = is_array($config) ? $config : getCartesiaActiveConfig();
    return substr(md5(json_encode([
        'connector_id' => intval($config['connector_id'] ?? 0),
        'api_badge_id' => intval($config['api_badge_id'] ?? 0),
    ])), 0, 16);
}

function listExistingCartesiaVoices(?array $config = null, string $query = ''): array {
    $config = is_array($config) ? $config : getCartesiaActiveConfig();
    $apiKey = trim(strval($config['api_key'] ?? ''));
    if ($apiKey === '') {
        return [];
    }

    $query = trim($query);
    $cacheKey = getCartesiaRemoteVoicesCacheKey($config) . '|' . strtolower($query);
    if (isset($GLOBALS['CARTESIA_REMOTE_VOICES_CACHE'][$cacheKey]) && is_array($GLOBALS['CARTESIA_REMOTE_VOICES_CACHE'][$cacheKey])) {
        return $GLOBALS['CARTESIA_REMOTE_VOICES_CACHE'][$cacheKey];
    }

    $voices = [];
    $startingAfter = '';

    do {
        $params = [
            'is_owner' => 'true',
            'limit' => 100,
        ];
        if ($query !== '') {
            $params['q'] = $query;
        }
        if ($startingAfter !== '') {
            $params['starting_after'] = $startingAfter;
        }

        $url = 'https://api.cartesia.ai/voices?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "Authorization: Bearer {$apiKey}\r\n" .
                            "X-API-Key: {$apiKey}\r\n" .
                            "Cartesia-Version: 2026-03-01\r\n" .
                            "Accept: application/json\r\n",
                'ignore_errors' => true,
                'timeout' => 30,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);
        $httpStatusLine = $http_response_header[0] ?? '';
        $httpCode = 0;
        if (preg_match('/\s(\d{3})\s/', $httpStatusLine, $matches)) {
            $httpCode = intval($matches[1]);
        }

        if ($response === false) {
            $error = error_get_last();
            Logger::warn("Failed to list Cartesia voices: " . ($error['message'] ?? 'Unknown error'));
            break;
        }

        if ($httpCode >= 400) {
            Logger::warn("Cartesia list voices API returned HTTP {$httpCode}: " . substr($response, 0, 500));
            break;
        }

        $result = json_decode($response, true);
        if (!is_array($result)) {
            Logger::warn("Invalid response from Cartesia list voices API: " . substr($response, 0, 500));
            break;
        }

        foreach (($result['voices'] ?? []) as $voiceRow) {
            if (is_array($voiceRow)) {
                $voices[] = $voiceRow;
            }
        }

        $hasMore = !empty($result['has_more']);
        $startingAfter = $hasMore ? trim(strval($result['next_page'] ?? '')) : '';
    } while ($startingAfter !== '');

    $GLOBALS['CARTESIA_REMOTE_VOICES_CACHE'][$cacheKey] = $voices;
    return $voices;
}

function getExistingCartesiaVoiceIdByName($voiceName, ?array $config = null): string {
    $normalizedNeedle = normalizeCartesiaVoiceLookupToken($voiceName);
    if ($normalizedNeedle === '') {
        return '';
    }

    foreach (listExistingCartesiaVoices($config, $voiceName) as $voiceRow) {
        foreach ([$voiceRow['name'] ?? '', $voiceRow['id'] ?? ''] as $candidate) {
            if (normalizeCartesiaVoiceLookupToken($candidate) === $normalizedNeedle) {
                return trim(strval($voiceRow['id'] ?? ''));
            }
        }
    }

    return '';
}

/**
 * Get or create Cartesia voice ID for a given voice sample
 * 
 * @param string $voiceName The name of the voice (without extension)
 * @return string|false The Cartesia voice ID or false on error
 */
function getOrCreateCartesiaVoice($voiceName) {
    $db = ensureCartesiaDb();
    if (!$db) {
        return false;
    }

    clearCartesiaLastError();
    $config = getCartesiaActiveConfig();

    $cachedVoiceId = getCachedCartesiaVoiceId($voiceName, $config);
    if ($cachedVoiceId !== '') {
        Logger::info("Using cached Cartesia voice ID for {$voiceName}: {$cachedVoiceId}");
        return $cachedVoiceId;
    }

    $existingVoiceId = getExistingCartesiaVoiceIdByName($voiceName, $config);
    if ($existingVoiceId !== '') {
        Logger::info("Found existing Cartesia voice for {$voiceName}: {$existingVoiceId}");
        storeCachedCartesiaVoiceId($voiceName, $existingVoiceId, $config);
        storeCartesiaVoiceMetadata($voiceName, $existingVoiceId, '', false, $config);
        return $existingVoiceId;
    }
    
    // No cached voice ID, need to clone the voice
    Logger::info("No cached Cartesia voice ID found for {$voiceName}, cloning voice...");
    
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
        setCartesiaLastError("Voice sample not found for {$voiceName}.");
        Logger::error("Voice sample not found: {$voiceName}.wav");
        Logger::error("Searched paths:");
        foreach ($possiblePaths as $testPath) {
            $normalized = realpath($testPath);
            Logger::error("  - {$testPath} " . ($normalized !== false && file_exists($normalized) ? "(found)" : "(not found)"));
        }
        return false;
    }
    
    $fileSize = filesize($voiceSamplePath);
    Logger::info("Found voice sample at {$voiceSamplePath} (size: " . number_format($fileSize) . " bytes)");
    
    $cartesiaVoiceId = cloneVoiceToCartesia($voiceName, $voiceSamplePath, $config);
    
    if ($cartesiaVoiceId === false) {
        if (getCartesiaLastError() === '') {
            setCartesiaLastError("Failed to clone voice {$voiceName} to Cartesia.");
        }
        Logger::error("Failed to clone voice {$voiceName} to Cartesia");
        return false;
    }
    
    storeCachedCartesiaVoiceId($voiceName, $cartesiaVoiceId, $config);
    storeCartesiaVoiceMetadata($voiceName, $cartesiaVoiceId, $voiceSamplePath, true, $config);
    clearCartesiaLastError();
    
    Logger::info("Successfully cloned voice {$voiceName} to Cartesia with ID: {$cartesiaVoiceId}");
    
    return $cartesiaVoiceId;
}

/**
 * Clone a voice to Cartesia
 * 
 * @param string $voiceName The name to give the voice
 * @param string $voiceSamplePath Path to the voice sample file
 * @param array|null $config Resolved connector-scoped Cartesia configuration
 * @return string|false The Cartesia voice ID or false on error
 */
function cloneVoiceToCartesia($voiceName, $voiceSamplePath, ?array $config = null) {
    $config = is_array($config) ? $config : getCartesiaActiveConfig();
    $apiKey = trim(strval($config['api_key'] ?? ''));

    if (empty($apiKey)) {
        setCartesiaLastError("Cartesia API key not found. Please set it on the active Cartesia TTS connector.");
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
    $data .= "Voice generated by CHIM for NPC: {$voiceName}" . $eol;
    
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
            'ignore_errors' => true,
            'timeout' => 60
        )
    );
    
    $context = stream_context_create($options);
    $response = @file_get_contents($url, false, $context);
    $httpStatusLine = $http_response_header[0] ?? '';
    $httpCode = 0;
    if (preg_match('/\s(\d{3})\s/', $httpStatusLine, $matches)) {
        $httpCode = intval($matches[1]);
    }
    
    if ($response === false) {
        $error = error_get_last();
        $errorMsg = $error['message'] ?? 'Unknown error';
        setCartesiaLastError($errorMsg);
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

    if ($httpCode >= 400) {
        $errorBody = json_decode($response, true);
        if (is_array($errorBody)) {
            $message = trim(strval($errorBody['message'] ?? ($errorBody['error'] ?? '')));
            if ($message !== '') {
                setCartesiaLastError($message);
                Logger::error("Cartesia clone API message: " . $message);
            } else {
                setCartesiaLastError("Cartesia clone API returned HTTP {$httpCode}.");
                Logger::error("Cartesia clone API response: " . substr($response, 0, 500));
            }
        } else {
            setCartesiaLastError("Cartesia clone API returned HTTP {$httpCode}.");
            Logger::error("Cartesia clone API response: " . substr($response, 0, 500));
        }
        return false;
    }
    
    $result = json_decode($response, true);
    
    if (!isset($result['id'])) {
        setCartesiaLastError('Invalid response from Cartesia clone API.');
        Logger::error("Invalid response from Cartesia clone API: " . $response);
        return false;
    }
    
    clearCartesiaLastError();
    return $result['id'];
}

function deleteCartesiaRemoteVoice($voiceId, ?array $config = null): bool {
    $config = is_array($config) ? $config : getCartesiaActiveConfig();
    $apiKey = trim(strval($config['api_key'] ?? ''));
    $voiceId = trim(strval($voiceId));
    if ($apiKey === '' || $voiceId === '') {
        return false;
    }
    $options = [
        'http' => [
            'method' => 'DELETE',
            'header' => "X-API-Key: {$apiKey}\r\nCartesia-Version: 2026-03-01\r\nAccept: application/json\r\n",
            'ignore_errors' => true,
            'timeout' => 30,
        ],
    ];
    $response = @file_get_contents(
        'https://api.cartesia.ai/voices/' . rawurlencode($voiceId),
        false,
        stream_context_create($options)
    );
    $statusLine = $http_response_header[0] ?? '';
    $httpCode = preg_match('/\s(\d{3})\s/', $statusLine, $matches) ? intval($matches[1]) : 0;
    if ($httpCode >= 200 && $httpCode < 300) {
        unset($GLOBALS['CARTESIA_REMOTE_VOICES_CACHE']);
        return true;
    }
    setCartesiaLastError("Cartesia delete API returned HTTP {$httpCode}.");
    Logger::warn("Failed to delete Cartesia voice {$voiceId}: HTTP {$httpCode}; " . substr(strval($response), 0, 500));
    return false;
}

function rebuildCartesiaVoice($voiceName) {
    clearCartesiaLastError();
    $config = getCartesiaActiveConfig();
    $samplePath = findCartesiaVoiceSamplePath($voiceName);
    if ($samplePath === '') {
        setCartesiaLastError("Voice sample not found for {$voiceName}.");
        return false;
    }

    $oldVoiceId = getCachedCartesiaVoiceId($voiceName, $config);
    $oldMetadata = getCartesiaVoiceMetadata($voiceName, $config);
    $newVoiceId = cloneVoiceToCartesia($voiceName, $samplePath, $config);
    if ($newVoiceId === false || $newVoiceId === '') {
        return false;
    }

    $validationAudio = generateCartesiaTTS('Voice synchronization test.', $newVoiceId);
    if (!is_string($validationAudio) || $validationAudio === '') {
        $validationError = getCartesiaLastError();
        deleteCartesiaRemoteVoice($newVoiceId, $config);
        setCartesiaLastError($validationError !== '' ? $validationError : 'The new Cartesia clone failed validation.');
        return false;
    }

    storeCachedCartesiaVoiceId($voiceName, $newVoiceId, $config);
    storeCartesiaVoiceMetadata($voiceName, $newVoiceId, $samplePath, true, $config);
    if (
        $oldVoiceId !== '' &&
        $oldVoiceId !== $newVoiceId &&
        !empty($oldMetadata['managed']) &&
        trim(strval($oldMetadata['voice_id'] ?? '')) === $oldVoiceId
    ) {
        deleteCartesiaRemoteVoice($oldVoiceId, $config);
    }
    clearCartesiaLastError();
    return $newVoiceId;
}

function deleteManagedCartesiaVoice($voiceName): bool {
    clearCartesiaLastError();
    $config = getCartesiaActiveConfig();
    $voiceId = getCachedCartesiaVoiceId($voiceName, $config);
    $metadata = getCartesiaVoiceMetadata($voiceName, $config);
    if (
        $voiceId === '' ||
        empty($metadata['managed']) ||
        trim(strval($metadata['voice_id'] ?? '')) !== $voiceId
    ) {
        setCartesiaLastError('This Cartesia voice is not marked as managed by this installation; only its cached ID can be forgotten.');
        return false;
    }
    if (!deleteCartesiaRemoteVoice($voiceId, $config)) {
        return false;
    }
    deleteCachedCartesiaVoiceId($voiceName, $config, true);
    deleteCartesiaVoiceMetadata($voiceName, $config);
    clearCartesiaLastError();
    return true;
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
    $config = getCartesiaActiveConfig();
    $apiKey = trim(strval($config['api_key'] ?? ''));

    if (empty($apiKey)) {
        setCartesiaLastError('Cartesia API key not found.');
        Logger::error("Cartesia API key not found");
        return false;
    }
    
    $url = "https://api.cartesia.ai/tts/bytes";
    
    // Get model ID
    $modelId = $GLOBALS["TTS"]["CARTESIA"]["model_id"] ?? 'sonic-3';
    
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
    
    // Emotion support disabled for now - needs testing with cloned voices
    // Add emotion SSML tag if enabled and mood is provided
    // $useEmotionsEnabled = $GLOBALS["TTS"]["CARTESIA"]["use_emotions"] ?? false;
    // 
    // Logger::info("Cartesia TTS called - use_emotions: " . ($useEmotionsEnabled ? 'true' : 'false') . ", mood: '{$mood}'");
    // 
    // if ($useEmotionsEnabled && !empty($mood) && $mood !== 'default') {
    //     $emotion = mapMoodToCartesiaEmotion($mood);
    //     if (!empty($emotion)) {
    //         // Prepend SSML emotion tag to text
    //         $text = '<emotion value="' . $emotion . '" /> ' . $text;
    //         Logger::info("Using Cartesia emotion: {$emotion} (from mood: {$mood})");
    //     } else {
    //         Logger::info("Mood '{$mood}' did not map to any Cartesia emotion");
    //     }
    // } else {
    //     if (!$useEmotionsEnabled) {
    //         Logger::info("Cartesia emotions disabled in config");
    //     } else if (empty($mood)) {
    //         Logger::info("No mood provided to Cartesia TTS");
    //     } else if ($mood === 'default') {
    //         Logger::info("Mood is 'default', skipping emotion");
    //     }
    // }
    
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
            'ignore_errors' => true,
            'timeout' => 30
        )
    );
    
    $context = stream_context_create($options);
    $response = @file_get_contents($url, false, $context);
    $httpStatusLine = $http_response_header[0] ?? '';
    $httpCode = 0;
    if (preg_match('/\s(\d{3})\s/', $httpStatusLine, $matches)) {
        $httpCode = intval($matches[1]);
    }
    
    if ($response === false) {
        $error = error_get_last();
        setCartesiaLastError($error['message'] ?? 'Unknown error');
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

    if ($httpCode >= 400) {
        $jsonCheck = json_decode($response, true);
        if (is_array($jsonCheck)) {
            $message = trim(strval($jsonCheck['message'] ?? ($jsonCheck['error'] ?? '')));
            if ($message !== '') {
                setCartesiaLastError($message);
                Logger::error("Cartesia API error: " . $message);
            } else {
                setCartesiaLastError("Cartesia TTS API returned HTTP {$httpCode}.");
                Logger::error("Cartesia API error: " . json_encode($jsonCheck));
            }
        } else {
            setCartesiaLastError("Cartesia TTS API returned HTTP {$httpCode}.");
            Logger::error("Cartesia API response: " . substr($response, 0, 500));
        }
        return false;
    }
    
    // Check if response is JSON error
    $jsonCheck = json_decode($response, true);
    if (is_array($jsonCheck) && isset($jsonCheck['error'])) {
        setCartesiaLastError(trim(strval($jsonCheck['error'])));
        Logger::error("Cartesia API error: " . json_encode($jsonCheck));
        return false;
    }
    
    clearCartesiaLastError();
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
 * Uses voicetype from database instead of NPC name
 * 
 * @return string The voice name
 */
function getCartesiaVoiceName() {
    require_once(__DIR__ . "/../lib/utils.php");
    
    $voice = isset($GLOBALS["TTS"]["FORCED_VOICE_DEV"]) 
        ? $GLOBALS["TTS"]["FORCED_VOICE_DEV"] 
        : ($GLOBALS["TTS"]["CARTESIA"]["voiceid"] ?? '');
    
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

