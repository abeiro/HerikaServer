<?php

require_once(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."lib".DIRECTORY_SEPARATOR."emote_moods.php");
require_once(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."lib".DIRECTORY_SEPARATOR."voice_sample_metadata.php");

/**
 * Inworld TTS Implementation
 * 
 * This implementation supports:
 * - Voice cloning from local voice samples
 * - Text-to-speech generation using cloned voices
 * - Automatic voice cloning when first needed (like XTTS)
 * - Connector-scoped caching of Inworld voice IDs in conf_opts
 */


/**
 * is this TTS service capable to express emotions?
 * @return: boolean
*/
function isEmotionCapable() {
    // inworld-tts-1 is not yet capable to handle emotions consistently, max is
    $modelId = $GLOBALS["TTS"]["INWORLD"]["model_id"] ?? 'inworld-tts-2';
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
    $modelId = $GLOBALS["TTS"]["INWORLD"]["model_id"] ?? 'inworld-tts-2';
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

function setInworldLastError(string $message): void {
    $GLOBALS["INWORLD_LAST_ERROR_MESSAGE"] = trim($message);
}

function getInworldLastError(): string {
    return trim(strval($GLOBALS["INWORLD_LAST_ERROR_MESSAGE"] ?? ''));
}

function clearInworldLastError(): void {
    unset($GLOBALS["INWORLD_LAST_ERROR_MESSAGE"]);
}

function getInworldVoiceSampleMetadata(string $voiceName): array {
    $voicesDirectory = __DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."data".DIRECTORY_SEPARATOR."voices";
    return chim_voice_sample_read_metadata($voiceName, $voicesDirectory);
}

function ensureInworldDb() {
    if (!isset($GLOBALS["db"]) || !$GLOBALS["db"]) {
        require_once(__DIR__ . "/../lib/{$GLOBALS["DBDRIVER"]}.class.php");
    }
    if (!isset($GLOBALS["db"]) || !$GLOBALS["db"]) {
        $GLOBALS["db"] = new sql();
    }
    return $GLOBALS["db"] ?? null;
}

function normalizeInworldWorkspaceName($workspace): string {
    $workspace = trim(strval($workspace));
    if (strpos($workspace, 'workspaces/') === 0) {
        $workspace = substr($workspace, strlen('workspaces/'));
    }
    return trim($workspace, "/ \t\n\r\0\x0B");
}

function resolveInworldConnectorRow(): ?array {
    $db = ensureInworldDb();
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
        if (is_array($row) && $ttsConnector->normalizeDriverValue($row['driver'] ?? '') === 'inworld') {
            return $row;
        }
    }

    $rows = array_values(array_filter($ttsConnector->readAll(), function ($row) use ($ttsConnector) {
        return $ttsConnector->normalizeDriverValue($row['driver'] ?? '') === 'inworld';
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

function hydrateInworldConnectorGlobals(): ?array {
    $row = resolveInworldConnectorRow();
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

function getInworldActiveConfig(): array {
    $row = null;
    $connectorId = intval($GLOBALS["CHIM_CORE_CURRENT_TTS_CONNECTOR_ID"] ?? 0);
    $workspace = normalizeInworldWorkspaceName($GLOBALS["TTS"]["INWORLD"]["workspace"] ?? '');
    $apiCredential = trim(strval($GLOBALS["TTS"]["INWORLD"]["API_KEY"] ?? ''));

    if ($connectorId <= 0 || $workspace === '' || $apiCredential === '') {
        $row = hydrateInworldConnectorGlobals();
        $connectorId = intval($GLOBALS["CHIM_CORE_CURRENT_TTS_CONNECTOR_ID"] ?? ($row['id'] ?? 0));
        $workspace = normalizeInworldWorkspaceName($GLOBALS["TTS"]["INWORLD"]["workspace"] ?? '');
        $apiCredential = trim(strval($GLOBALS["TTS"]["INWORLD"]["API_KEY"] ?? ''));
    } else {
        $row = resolveInworldConnectorRow();
    }

    if (is_array($row)) {
        if ($workspace === '') {
            if (!class_exists('TTSConnector')) {
                require_once(__DIR__ . "/../lib/core/tts_connector.class.php");
            }
            $ttsConnector = new TTSConnector();
            $metadata = $ttsConnector->decodeMetadata($row['metadata'] ?? '{}');
            $workspace = normalizeInworldWorkspaceName($metadata['workspace'] ?? '');
        }
        if ($apiCredential === '') {
            $db = ensureInworldDb();
            $apiBadgeId = intval($row['api_badge_id'] ?? 0);
            if ($db && $apiBadgeId > 0) {
                $badgeRow = $db->fetchOne("SELECT api_key FROM core_api_badge WHERE id = {$apiBadgeId} LIMIT 1");
                if (is_array($badgeRow) && !empty($badgeRow['api_key'])) {
                    $apiCredential = trim(strval($badgeRow['api_key']));
                }
            }
        }
    }

    return [
        'connector_id' => $connectorId,
        'workspace' => $workspace,
        'workspace_path' => $workspace !== '' ? "workspaces/{$workspace}" : '',
        'api_key' => $apiCredential,
        'row' => $row,
    ];
}

function inworldVoiceIdMatchesWorkspace($voiceId, $workspace): bool {
    $voiceId = trim(strval($voiceId));
    $workspace = normalizeInworldWorkspaceName($workspace);
    if ($voiceId === '') {
        return false;
    }
    if ($workspace === '') {
        return true;
    }
    return strpos($voiceId, $workspace . '__') === 0;
}

function getInworldVoiceCachePrefix(?array $config = null): string {
    $config = is_array($config) ? $config : getInworldActiveConfig();
    $connectorId = intval($config['connector_id'] ?? 0);
    $workspace = normalizeInworldWorkspaceName($config['workspace'] ?? '');
    if ($connectorId <= 0 && $workspace === '') {
        return 'inworld_voice_id_';
    }

    $scopeHash = substr(md5(json_encode([
        'connector_id' => $connectorId,
        'workspace' => strtolower($workspace),
    ])), 0, 12);

    return "inworld_voice_scope_{$scopeHash}__";
}

function getInworldVoiceCachePrefixes(?array $config = null, bool $includeLegacy = true): array {
    $config = is_array($config) ? $config : getInworldActiveConfig();
    $prefixes = [];
    $scopedPrefix = getInworldVoiceCachePrefix($config);
    if ($scopedPrefix !== 'inworld_voice_id_') {
        $prefixes[] = $scopedPrefix;
    }
    if ($includeLegacy || empty($prefixes)) {
        $prefixes[] = 'inworld_voice_id_';
    }
    return array_values(array_unique($prefixes));
}

function getInworldVoiceCacheKey($voiceName, ?array $config = null): string {
    return getInworldVoiceCachePrefix($config) . $voiceName;
}

function getCachedInworldVoiceId($voiceName, ?array $config = null): string {
    $db = ensureInworldDb();
    if (!$db) {
        return '';
    }

    $config = is_array($config) ? $config : getInworldActiveConfig();
    $prefixes = getInworldVoiceCachePrefixes($config, true);
    $scopedPrefix = getInworldVoiceCachePrefix($config);
    $workspace = $config['workspace'] ?? '';

    foreach ($prefixes as $prefix) {
        $optKeyEscaped = $db->escape($prefix . $voiceName);
        $row = $db->fetchOne("SELECT value FROM conf_opts WHERE id = '{$optKeyEscaped}' LIMIT 1");
        $voiceId = trim(strval($row['value'] ?? ''));
        if ($voiceId === '') {
            continue;
        }
        if ($prefix === 'inworld_voice_id_' && !inworldVoiceIdMatchesWorkspace($voiceId, $workspace)) {
            continue;
        }
        if ($prefix === 'inworld_voice_id_' && $scopedPrefix !== 'inworld_voice_id_') {
            $voiceIdEscaped = $db->escape($voiceId);
            $scopedKeyEscaped = $db->escape($scopedPrefix . $voiceName);
            $db->execQuery(
                "INSERT INTO conf_opts (id, value) VALUES ('{$scopedKeyEscaped}', '{$voiceIdEscaped}')
                 ON CONFLICT(id) DO UPDATE SET value = '{$voiceIdEscaped}'"
            );
        }
        return $voiceId;
    }

    return '';
}

function storeCachedInworldVoiceId($voiceName, $voiceId, ?array $config = null): void {
    $db = ensureInworldDb();
    if (!$db) {
        return;
    }

    $optKeyEscaped = $db->escape(getInworldVoiceCacheKey($voiceName, $config));
    $voiceIdEscaped = $db->escape($voiceId);
    $db->execQuery(
        "INSERT INTO conf_opts (id, value) VALUES ('{$optKeyEscaped}', '{$voiceIdEscaped}')
         ON CONFLICT(id) DO UPDATE SET value = '{$voiceIdEscaped}'"
    );
}

function deleteCachedInworldVoiceId($voiceName, ?array $config = null, bool $includeLegacy = true): void {
    $db = ensureInworldDb();
    if (!$db) {
        return;
    }

    foreach (getInworldVoiceCachePrefixes($config, $includeLegacy) as $prefix) {
        $optKeyEscaped = $db->escape($prefix . $voiceName);
        $db->execQuery("DELETE FROM conf_opts WHERE id = '{$optKeyEscaped}'");
    }
}

function getInworldVoiceMetadataKey($voiceName, ?array $config = null): string {
    return 'inworld_voice_meta_' . substr(md5(getInworldVoiceCachePrefix($config)), 0, 12) . '__' . $voiceName;
}

function getInworldVoiceMetadata($voiceName, ?array $config = null): array {
    $db = ensureInworldDb();
    if (!$db) {
        return [];
    }
    $key = $db->escape(getInworldVoiceMetadataKey($voiceName, $config));
    $row = $db->fetchOne("SELECT value FROM conf_opts WHERE id = '{$key}' LIMIT 1");
    $decoded = json_decode(strval($row['value'] ?? ''), true);
    return is_array($decoded) ? $decoded : [];
}

function storeInworldVoiceMetadata($voiceName, $voiceId, $samplePath, bool $managed, ?array $config = null): void {
    $db = ensureInworldDb();
    if (!$db) {
        return;
    }
    $metadata = [
        'voice_id' => trim(strval($voiceId)),
        'managed' => $managed,
        'sample_sha256' => is_file($samplePath) ? hash_file('sha256', $samplePath) : '',
        'created_at' => gmdate('c'),
    ];
    $key = $db->escape(getInworldVoiceMetadataKey($voiceName, $config));
    $value = $db->escape(json_encode($metadata, JSON_UNESCAPED_SLASHES));
    $db->execQuery(
        "INSERT INTO conf_opts (id, value) VALUES ('{$key}', '{$value}')
         ON CONFLICT(id) DO UPDATE SET value = '{$value}'"
    );
}

function deleteInworldVoiceMetadata($voiceName, ?array $config = null): void {
    $db = ensureInworldDb();
    if (!$db) {
        return;
    }
    $key = $db->escape(getInworldVoiceMetadataKey($voiceName, $config));
    $db->execQuery("DELETE FROM conf_opts WHERE id = '{$key}'");
}

function findInworldVoiceSamplePath($voiceName): string {
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

function getInworldCachedVoicesMap(bool $includeLegacy = true, ?array $config = null): array {
    $db = ensureInworldDb();
    if (!$db) {
        return [];
    }

    $config = is_array($config) ? $config : getInworldActiveConfig();
    $workspace = $config['workspace'] ?? '';
    $cloned = [];

    foreach (getInworldVoiceCachePrefixes($config, $includeLegacy) as $prefix) {
        $prefixEscaped = $db->escape($prefix);
        $rows = $db->fetchAll("SELECT id, value FROM conf_opts WHERE id LIKE '{$prefixEscaped}%'");
        foreach ($rows as $row) {
            $voiceName = substr(strval($row['id'] ?? ''), strlen($prefix));
            $voiceId = trim(strval($row['value'] ?? ''));
            if ($voiceName === '' || $voiceId === '') {
                continue;
            }
            if ($prefix === 'inworld_voice_id_' && !inworldVoiceIdMatchesWorkspace($voiceId, $workspace)) {
                continue;
            }
            if (!isset($cloned[$voiceName])) {
                $cloned[$voiceName] = $voiceId;
            }
        }
    }

    return $cloned;
}

function normalizeInworldVoiceLookupToken($value): string {
    $value = strtolower(trim(strval($value)));
    if ($value === '') {
        return '';
    }

    return preg_replace('/[^a-z0-9]+/', '', $value);
}

function getInworldRemoteVoicesCacheKey(?array $config = null): string {
    $config = is_array($config) ? $config : getInworldActiveConfig();
    return substr(md5(json_encode([
        'connector_id' => intval($config['connector_id'] ?? 0),
        'workspace' => normalizeInworldWorkspaceName($config['workspace'] ?? ''),
    ])), 0, 16);
}

function inworldVoiceRowMatchesWorkspace(array $voiceRow, string $workspace): bool {
    $workspace = normalizeInworldWorkspaceName($workspace);
    if ($workspace === '') {
        return true;
    }

    $voiceId = trim(strval($voiceRow['voiceId'] ?? ''));
    if ($voiceId !== '' && inworldVoiceIdMatchesWorkspace($voiceId, $workspace)) {
        return true;
    }

    $name = trim(strval($voiceRow['name'] ?? ''));
    if ($name !== '' && strpos($name, "workspaces/{$workspace}/") === 0) {
        return true;
    }

    foreach (['workspace', 'workspaceId', 'workspace_id'] as $field) {
        $workspaceValue = normalizeInworldWorkspaceName($voiceRow[$field] ?? '');
        if ($workspaceValue !== '' && $workspaceValue === $workspace) {
            return true;
        }
    }

    return false;
}

function listExistingInworldWorkspaceVoices(?array $config = null): array {
    $config = is_array($config) ? $config : getInworldActiveConfig();
    $apiCredential = trim(strval($config['api_key'] ?? ''));
    $workspace = normalizeInworldWorkspaceName($config['workspace'] ?? '');
    if ($apiCredential === '') {
        return [];
    }

    $cacheKey = getInworldRemoteVoicesCacheKey($config);
    if (isset($GLOBALS['INWORLD_REMOTE_VOICES_CACHE'][$cacheKey]) && is_array($GLOBALS['INWORLD_REMOTE_VOICES_CACHE'][$cacheKey])) {
        return $GLOBALS['INWORLD_REMOTE_VOICES_CACHE'][$cacheKey];
    }

    $voices = [];
    $pageToken = '';

    do {
        $query = [
            'filter' => 'source = "IVC"',
            'orderBy' => 'created_at desc',
            'pageSize' => 2000,
        ];
        if ($pageToken !== '') {
            $query['pageToken'] = $pageToken;
        }

        $url = 'https://api.inworld.ai/voices/v1/voices?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "Authorization: Basic {$apiCredential}\r\n",
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
            Logger::warn("Failed to list Inworld voices: " . ($error['message'] ?? 'Unknown error'));
            break;
        }

        if ($httpCode >= 400) {
            Logger::warn("Inworld list voices API returned HTTP {$httpCode}: " . substr($response, 0, 500));
            break;
        }

        $result = json_decode($response, true);
        if (!is_array($result)) {
            Logger::warn("Invalid response from Inworld list voices API: " . substr($response, 0, 500));
            break;
        }

        foreach (($result['voices'] ?? []) as $voiceRow) {
            if (is_array($voiceRow) && inworldVoiceRowMatchesWorkspace($voiceRow, $workspace)) {
                $voices[] = $voiceRow;
            }
        }

        $pageToken = trim(strval($result['nextPageToken'] ?? ''));
    } while ($pageToken !== '');

    $GLOBALS['INWORLD_REMOTE_VOICES_CACHE'][$cacheKey] = $voices;
    return $voices;
}

function getExistingInworldVoiceIdByName($voiceName, ?array $config = null): string {
    $normalizedNeedle = normalizeInworldVoiceLookupToken($voiceName);
    if ($normalizedNeedle === '') {
        return '';
    }

    foreach (listExistingInworldWorkspaceVoices($config) as $voiceRow) {
        $candidates = [
            $voiceRow['displayName'] ?? '',
            $voiceRow['voiceId'] ?? '',
        ];

        $fullName = trim(strval($voiceRow['name'] ?? ''));
        if ($fullName !== '') {
            $parts = explode('/', $fullName);
            $candidates[] = end($parts);
        }

        foreach ($candidates as $candidate) {
            if (normalizeInworldVoiceLookupToken($candidate) === $normalizedNeedle) {
                return trim(strval($voiceRow['voiceId'] ?? ''));
            }
        }
    }

    return '';
}


/**
 * Get or create Inworld voice ID for a given voice sample
 * 
 * @param string $voiceName The name of the voice (without extension)
 * @return string|false The Inworld voice ID or false on error
 */
function getOrCreateInworldVoice($voiceName) {
    $db = ensureInworldDb();
    if (!$db) {
        return false;
    }

    clearInworldLastError();
    $config = getInworldActiveConfig();

    $cachedVoiceId = getCachedInworldVoiceId($voiceName, $config);
    if ($cachedVoiceId !== '') {
        Logger::info("Using cached Inworld voice ID for {$voiceName}: {$cachedVoiceId}");
        return $cachedVoiceId;
    }

    $existingVoiceId = getExistingInworldVoiceIdByName($voiceName, $config);
    if ($existingVoiceId !== '') {
        Logger::info("Found existing Inworld voice for {$voiceName}: {$existingVoiceId}");
        storeCachedInworldVoiceId($voiceName, $existingVoiceId, $config);
        storeInworldVoiceMetadata($voiceName, $existingVoiceId, '', false, $config);
        return $existingVoiceId;
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
        setInworldLastError("Voice sample not found for {$voiceName}.");
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
    
    $inworldVoiceId = cloneVoiceToInworld($voiceName, $voiceSamplePath, $config);
    
    if ($inworldVoiceId === false) {
        if (getInworldLastError() === '') {
            setInworldLastError("Failed to clone voice {$voiceName} to Inworld.");
        }
        Logger::error("Failed to clone voice {$voiceName} to Inworld");
        return false;
    }
    
    storeCachedInworldVoiceId($voiceName, $inworldVoiceId, $config);
    storeInworldVoiceMetadata($voiceName, $inworldVoiceId, $voiceSamplePath, true, $config);
    clearInworldLastError();
    
    Logger::info("Successfully cloned voice {$voiceName} to Inworld with ID: {$inworldVoiceId}");
    
    return $inworldVoiceId;
}

/**
 * Clone a voice to Inworld
 * 
 * @param string $voiceName The name to give the voice
 * @param string $voiceSamplePath Path to the voice sample file
 * @param array|null $config Resolved connector-scoped Inworld configuration
 * @return string|false The Inworld voice ID or false on error
 */
function cloneVoiceToInworld($voiceName, $voiceSamplePath, ?array $config = null) {
    $config = is_array($config) ? $config : getInworldActiveConfig();
    $apiCredential = trim(strval($config['api_key'] ?? ''));

    if (empty($apiCredential)) {
        setInworldLastError('Inworld API credential not found. Please set it in the API Badge page.');
        Logger::error("Inworld API credential not found. Please set it in the API Badge page.");
        return false;
    }

    $workspace = normalizeInworldWorkspaceName($config['workspace'] ?? '');
    if (empty($workspace)) {
        setInworldLastError('Inworld workspace ID not configured. Please set it on the active Inworld TTS connector.');
        Logger::error("Inworld workspace ID not configured. Please set it in TTS settings.");
        return false;
    }

    $workspacePath = trim(strval($config['workspace_path'] ?? ''));
    if ($workspacePath === '') {
        $workspacePath = "workspaces/{$workspace}";
    }
    
    Logger::info("Cloning voice {$voiceName} to Inworld...");
    
    $url = "https://api.inworld.ai/voices/v1/{$workspacePath}/voices:clone";
    
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
    $voiceSample = array('audioData' => $audioBase64);
    $voiceSampleMetadata = getInworldVoiceSampleMetadata($voiceName);
    $referenceText = trim(strval($voiceSampleMetadata['reference_text'] ?? ''));
    if ($referenceText !== '') {
        $voiceSample['transcription'] = $referenceText;
    }
    
    // Prepare request data
    $data = array(
        'displayName' => $voiceName,
        'langCode' => $language,
        'voiceSamples' => array($voiceSample),
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
        setInworldLastError($errorMsg);
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

    if ($httpCode >= 400) {
        Logger::error("Inworld clone API returned HTTP {$httpCode}");
        $errorBody = json_decode($response, true);
        if (is_array($errorBody) && !empty($errorBody['message'])) {
            setInworldLastError(strval($errorBody['message']));
            Logger::error("Inworld clone API message: " . $errorBody['message']);
        } else {
            setInworldLastError("Inworld clone API returned HTTP {$httpCode}.");
            Logger::error("Inworld clone API response: " . substr($response, 0, 500));
        }
        return false;
    }
    
    $result = json_decode($response, true);
    
    if (!isset($result['voice']['voiceId'])) {
        setInworldLastError('Invalid response from Inworld clone API.');
        Logger::error("Invalid response from Inworld clone API: " . $response);
        return false;
    }

    clearInworldLastError();
    
    return $result['voice']['voiceId'];
}

function deleteInworldRemoteVoice($voiceId, ?array $config = null): bool {
    $config = is_array($config) ? $config : getInworldActiveConfig();
    $apiCredential = trim(strval($config['api_key'] ?? ''));
    $voiceId = trim(strval($voiceId));
    if ($apiCredential === '' || $voiceId === '') {
        return false;
    }
    $options = [
        'http' => [
            'method' => 'DELETE',
            'header' => "Authorization: Basic {$apiCredential}\r\nAccept: application/json\r\n",
            'ignore_errors' => true,
            'timeout' => 30,
        ],
    ];
    $response = @file_get_contents(
        'https://api.inworld.ai/voices/v1/voices/' . rawurlencode($voiceId),
        false,
        stream_context_create($options)
    );
    $statusLine = $http_response_header[0] ?? '';
    $httpCode = preg_match('/\s(\d{3})\s/', $statusLine, $matches) ? intval($matches[1]) : 0;
    if ($httpCode >= 200 && $httpCode < 300) {
        unset($GLOBALS['INWORLD_REMOTE_VOICES_CACHE']);
        return true;
    }
    setInworldLastError("Inworld delete API returned HTTP {$httpCode}.");
    Logger::warn("Failed to delete Inworld voice {$voiceId}: HTTP {$httpCode}; " . substr(strval($response), 0, 500));
    return false;
}

function rebuildInworldVoice($voiceName) {
    clearInworldLastError();
    $config = getInworldActiveConfig();
    $samplePath = findInworldVoiceSamplePath($voiceName);
    if ($samplePath === '') {
        setInworldLastError("Voice sample not found for {$voiceName}.");
        return false;
    }

    $oldVoiceId = getCachedInworldVoiceId($voiceName, $config);
    $oldMetadata = getInworldVoiceMetadata($voiceName, $config);
    $newVoiceId = cloneVoiceToInworld($voiceName, $samplePath, $config);
    if ($newVoiceId === false || $newVoiceId === '') {
        return false;
    }

    $validationAudio = generateInworldTTS('Voice synchronization test.', $newVoiceId);
    if (!is_string($validationAudio) || $validationAudio === '') {
        $validationError = getInworldLastError();
        deleteInworldRemoteVoice($newVoiceId, $config);
        setInworldLastError($validationError !== '' ? $validationError : 'The new Inworld clone failed validation.');
        return false;
    }

    storeCachedInworldVoiceId($voiceName, $newVoiceId, $config);
    storeInworldVoiceMetadata($voiceName, $newVoiceId, $samplePath, true, $config);
    if (
        $oldVoiceId !== '' &&
        $oldVoiceId !== $newVoiceId &&
        !empty($oldMetadata['managed']) &&
        trim(strval($oldMetadata['voice_id'] ?? '')) === $oldVoiceId
    ) {
        deleteInworldRemoteVoice($oldVoiceId, $config);
    }
    clearInworldLastError();
    return $newVoiceId;
}

function deleteManagedInworldVoice($voiceName): bool {
    clearInworldLastError();
    $config = getInworldActiveConfig();
    $voiceId = getCachedInworldVoiceId($voiceName, $config);
    $metadata = getInworldVoiceMetadata($voiceName, $config);
    if (
        $voiceId === '' ||
        empty($metadata['managed']) ||
        trim(strval($metadata['voice_id'] ?? '')) !== $voiceId
    ) {
        setInworldLastError('This Inworld voice is not marked as managed by this installation; only its cached ID can be forgotten.');
        return false;
    }
    if (!deleteInworldRemoteVoice($voiceId, $config)) {
        return false;
    }
    deleteCachedInworldVoiceId($voiceName, $config, true);
    deleteInworldVoiceMetadata($voiceName, $config);
    clearInworldLastError();
    return true;
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
    $config = getInworldActiveConfig();
    $apiCredential = trim(strval($config['api_key'] ?? ''));

    if (empty($apiCredential)) {
        Logger::error("Inworld API credential not found");
        return false;
    }
    
    $url = "https://api.inworld.ai/tts/v1/voice:stream";
    
    // Get model ID
    $modelId = $GLOBALS["TTS"]["INWORLD"]["model_id"] ?? 'inworld-tts-2';
    
    // Get speed setting (speakingRate: 0.5-1.5, default 1.0)
    $speed = $GLOBALS["TTS"]["INWORLD"]["speed"] ?? 1.0;
    $speed = floatval($speed);
    if ($speed < 0.5) $speed = 0.5;
    if ($speed > 1.5) $speed = 1.5;
    
    // Get temperature
    $temperature = $GLOBALS["TTS"]["INWORLD"]["temperature"] ?? 1.0;
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
    $config = getInworldActiveConfig();
    
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
            deleteCachedInworldVoiceId($voiceName, $config, true);
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
            $cachedId = getCachedInworldVoiceId($voiceName, $config);
            // Only retry if we had a cached ID (not a fresh clone attempt)
            if ($cachedId !== '' && $cachedId === $inworldVoiceId) {
                Logger::info("TTS generation failed with cached voice ID, clearing cache and retrying clone for {$voiceName}...");
                deleteCachedInworldVoiceId($voiceName, $config, true);
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

