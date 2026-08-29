<?php
// Get the relative web path from document root to our application
$scriptPath = $_SERVER['SCRIPT_NAME'];
$webRoot = dirname(dirname($scriptPath)); // Go up two levels from the script location
if ($webRoot == '/') $webRoot = '';
$webRoot = rtrim($webRoot, '/');

// Load necessary files for returnLines() function
$enginePath = __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR;
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "logger.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "model_dynmodel.php");
require_once($enginePath . "conf" . DIRECTORY_SEPARATOR . "conf_loader.php");
@include_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "data_functions.php");
@include_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "online_translation.php");
@include_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "chat_helper_functions.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "core" . DIRECTORY_SEPARATOR . "tts_connector.class.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "core" . DIRECTORY_SEPARATOR . "tts_fallback.class.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "core" . DIRECTORY_SEPARATOR . "tts_studio_provider_detection.php");

require_once(__DIR__.DIRECTORY_SEPARATOR."profile_loader.php");

// Normalize endpoint URL - defined early as it's used in multiple places
if (!function_exists('normalize_endpoint_url')) {
    function normalize_endpoint_url($url) {
        // Remove trailing slashes
        $url = rtrim($url, '/');
        return $url;
    }
}

if (!function_exists('chimTtsStudioTabToDriver')) {
    function chimTtsStudioTabToDriver(string $tab): string
    {
        return match (strtolower(trim($tab))) {
            'xtts' => 'xtts-fastapi',
            'chatterbox' => 'chatterbox',
            'pockettts' => 'pockettts',
            'omnivoice' => 'omnivoice',
            default => '',
        };
    }
}

if (!function_exists('chimTtsStudioSpeakerCacheKey')) {
    function chimTtsStudioSpeakerCacheKey(string $driver, string $scope = ''): string
    {
        $normalized = strtolower(trim($driver));
        if ($normalized === '') {
            $normalized = 'unknown';
        }
        $key = 'tts_studio_speakers_' . preg_replace('/[^a-z0-9_\-]/', '_', $normalized);
        $scope = strtolower(trim($scope));
        if ($scope !== '') {
            $key .= '_' . preg_replace('/[^a-z0-9_\-]/', '_', $scope);
        }
        return $key;
    }
}

if (!function_exists('chimTtsStudioSanitizeLanguage')) {
    function chimTtsStudioSanitizeLanguage($language): string
    {
        return preg_replace('/[^a-z\-]/i', '', strtolower(trim(strval($language ?? ''))));
    }
}

if (!function_exists('chimTtsStudioResolveConnectorRow')) {
    function chimTtsStudioResolveConnectorRow(string $driver): ?array
    {
        if (!isset($GLOBALS["db"]) || !$GLOBALS["db"]) {
            return null;
        }

        $ttsConnector = new TTSConnector();
        $driver = $ttsConnector->normalizeDriverValue($driver);
        if ($driver === '') {
            return null;
        }

        $rows = array_values(array_filter($ttsConnector->readAll(), function ($row) use ($driver, $ttsConnector) {
            return $ttsConnector->normalizeDriverValue($row['driver'] ?? '') === $driver;
        }));
        if (empty($rows)) {
            return null;
        }

        $profileUsageMap = [];
        $usageRows = $GLOBALS["db"]->fetchAll(
            "SELECT tts_connector_id, COUNT(*) AS c FROM core_profiles WHERE tts_connector_id IS NOT NULL GROUP BY tts_connector_id"
        );
        foreach ($usageRows as $usageRow) {
            $profileUsageMap[intval($usageRow['tts_connector_id'] ?? 0)] = intval($usageRow['c'] ?? 0);
        }

        $playerConnectorId = 0;
        $playerRow = $GLOBALS["db"]->fetchOne("SELECT value FROM core_player WHERE id = 'tts_connector_id' LIMIT 1");
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
}

if (!function_exists('chimTtsStudioResolveConnectorMetadata')) {
    function chimTtsStudioResolveConnectorMetadata(string $driver): array
    {
        $ttsConnector = new TTSConnector();
        $driver = $ttsConnector->normalizeDriverValue($driver);
        if ($driver === '') {
            return [];
        }

        $row = chimTtsStudioResolveConnectorRow($driver);
        if (!is_array($row)) {
            return [];
        }

        return $ttsConnector->applyForcedMetadataDefaults(
            $driver,
            $ttsConnector->stripVoiceMetadataForDriver(
                $driver,
                $ttsConnector->decodeMetadata($row['metadata'] ?? '{}')
            )
        );
    }
}

if (!function_exists('chimTtsStudioResolveEndpointForDriver')) {
    function chimTtsStudioResolveEndpointForDriver(string $driver): string
    {
        $ttsConnector = new TTSConnector();
        $driver = $ttsConnector->normalizeDriverValue($driver);
        if ($driver === '') {
            return '';
        }

        $row = chimTtsStudioResolveConnectorRow($driver);
        if (is_array($row)) {
            $rowUrl = $ttsConnector->resolveConnectorUrl($row);
            if ($rowUrl !== '') {
                return normalize_endpoint_url($rowUrl);
            }
        }

        $defaultUrl = trim(strval($ttsConnector->getDefaultUrlForDriver($driver)));
        return $defaultUrl !== '' ? normalize_endpoint_url($defaultUrl) : '';
    }
}

if (!function_exists('chimTtsStudioDetectPocketTtsRuntime')) {
    function chimTtsStudioDetectPocketTtsRuntime(string $endpoint): array
    {
        $endpoint = normalize_endpoint_url(trim($endpoint));
        if ($endpoint === '') {
            return [
                'reachable' => false,
                'mode' => 'standard',
                'reason' => 'No endpoint configured',
            ];
        }

        static $cache = [];
        if (isset($cache[$endpoint])) {
            return $cache[$endpoint];
        }

        $audioCppBase = chimTtsStudioAudioCppBaseEndpoint($endpoint);
        $healthProbe = chimTtsStudioProbeJson($audioCppBase . '/health');
        $modelsProbe = chimTtsStudioProbeSucceeded($healthProbe)
            ? chimTtsStudioProbeJson($audioCppBase . '/v1/models')
            : ['response' => false, 'decoded' => null, 'http_code' => 0, 'curl_error' => ''];
        $speakersProbe = chimTtsStudioProbeJson($endpoint . '/speakers_list');

        return $cache[$endpoint] = chimTtsStudioClassifyPocketTtsRuntime(
            $endpoint,
            chimTtsStudioResolveConnectorMetadata('pockettts'),
            $healthProbe,
            $modelsProbe,
            $speakersProbe
        );
    }
}

if (!function_exists('chimTtsStudioIsAudioCppPocketTts')) {
    function chimTtsStudioIsAudioCppPocketTts(string $driver, string $endpoint = ''): bool
    {
        $ttsConnector = new TTSConnector();
        if ($ttsConnector->normalizeDriverValue($driver) !== 'pockettts') {
            return false;
        }

        return (chimTtsStudioDetectPocketTtsRuntime($endpoint)['mode'] ?? '') === 'audio_cpp';
    }
}

if (!function_exists('chimTtsStudioAudioCppBaseEndpoint')) {
    function chimTtsStudioAudioCppBaseEndpoint(string $endpoint): string
    {
        $endpoint = normalize_endpoint_url(trim($endpoint));
        $suffix = '/v1/audio/speech';
        if (substr($endpoint, -strlen($suffix)) === $suffix) {
            return substr($endpoint, 0, -strlen($suffix));
        }
        return $endpoint;
    }
}

if (!function_exists('chimTtsStudioApplyConnectorGlobals')) {
    function chimTtsStudioApplyConnectorGlobals(string $driver): void
    {
        $ttsConnector = new TTSConnector();
        $driver = $ttsConnector->normalizeDriverValue($driver);
        if ($driver === '') {
            return;
        }

        $row = chimTtsStudioResolveConnectorRow($driver);
        if (is_array($row)) {
            $ttsConnector->setOldGlobals($row);
            return;
        }

        $providerKey = $ttsConnector->getProviderKeyFromDriver($driver);
        $endpoint = chimTtsStudioResolveEndpointForDriver($driver);
        $GLOBALS["TTSFUNCTION"] = $driver;
        $GLOBALS["TTS_FUNCTION"] = $driver;
        if ($providerKey !== '' && $endpoint !== '') {
            $GLOBALS["TTS"][$providerKey]['endpoint'] = $endpoint;
            $GLOBALS["TTS"][$providerKey]['url'] = $endpoint;
            $GLOBALS["TTS"][$providerKey]['URL'] = $endpoint;
        }
    }
}

if (!function_exists('chimTtsStudioFetchSpeakersList')) {
    function chimTtsStudioFetchSpeakersList(string $driver, string $language = ''): array
    {
        $endpoint = chimTtsStudioResolveEndpointForDriver($driver);
        if ($endpoint === '') {
            return [];
        }

        if (chimTtsStudioIsAudioCppPocketTts($driver, $endpoint) && function_exists('getLocalVoices')) {
            return getLocalVoices();
        }

        $url = $endpoint . '/speakers_list';
        if ($driver === 'omnivoice') {
            $language = chimTtsStudioSanitizeLanguage($language);
            if ($language !== '') {
                $url .= '?language=' . rawurlencode($language);
            }
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['accept: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        $response = curl_exec($ch);
        $httpCode = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
        $curlError = curl_errno($ch);
        curl_close($ch);

        if ($curlError || $httpCode !== 200) {
            return [];
        }

        $speakersList = json_decode(strval($response), true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($speakersList)) {
            return [];
        }

        return normalizeSpeakersList($speakersList);
    }
}

if (!function_exists('chimTtsStudioGetCachedSpeakersList')) {
    function chimTtsStudioGetCachedSpeakersList(string $driver, string $language = ''): array
    {
        $endpoint = chimTtsStudioResolveEndpointForDriver($driver);
        if (chimTtsStudioIsAudioCppPocketTts($driver, $endpoint) && function_exists('getLocalVoices')) {
            return getLocalVoices();
        }

        $cacheKey = chimTtsStudioSpeakerCacheKey($driver, $driver === 'omnivoice' ? $language : '');
        $speakers = $_SESSION[$cacheKey] ?? [];
        return is_array($speakers) ? $speakers : [];
    }
}

if (!function_exists('chimTtsStudioStoreSpeakersList')) {
    function chimTtsStudioStoreSpeakersList(string $driver, array $speakers, string $language = ''): void
    {
        $_SESSION[chimTtsStudioSpeakerCacheKey($driver, $driver === 'omnivoice' ? $language : '')] = normalizeSpeakersList($speakers);
    }
}

if (!function_exists('chimTtsStudioFetchOmniVoiceLibraries')) {
    function chimTtsStudioFetchOmniVoiceLibraries(): array
    {
        $endpoint = chimTtsStudioResolveEndpointForDriver('omnivoice');
        if ($endpoint === '') {
            return [];
        }

        $probe = chimTtsStudioProbeJson($endpoint . '/voice_libraries');
        if ($probe['response'] === false || intval($probe['http_code']) < 200 || intval($probe['http_code']) >= 300) {
            return [];
        }

        return is_array($probe['decoded']) ? $probe['decoded'] : [];
    }
}

if (!function_exists('chimTtsStudioResolveOmniVoiceLanguage')) {
    function chimTtsStudioResolveOmniVoiceLanguage($candidate = ''): string
    {
        $candidate = chimTtsStudioSanitizeLanguage($candidate);
        if ($candidate !== '') {
            return $candidate;
        }

        $metadata = chimTtsStudioResolveConnectorMetadata('omnivoice');
        $metadataLanguage = chimTtsStudioSanitizeLanguage($metadata['language'] ?? '');
        if ($metadataLanguage !== '') {
            return $metadataLanguage;
        }

        foreach (chimTtsStudioFetchOmniVoiceLibraries() as $library) {
            if (!is_array($library) || empty($library['active'])) {
                continue;
            }
            $libraryId = chimTtsStudioSanitizeLanguage($library['id'] ?? '');
            if ($libraryId !== '') {
                return $libraryId;
            }
        }

        foreach (chimTtsStudioFetchOmniVoiceLibraries() as $library) {
            if (!is_array($library)) {
                continue;
            }
            $libraryId = chimTtsStudioSanitizeLanguage($library['id'] ?? '');
            if ($libraryId !== '') {
                return $libraryId;
            }
        }

        return 'en';
    }
}

if (!function_exists('chimTtsStudioFetchOmniVoiceVoiceItems')) {
    function chimTtsStudioFetchOmniVoiceVoiceItems(string $language): array
    {
        $endpoint = chimTtsStudioResolveEndpointForDriver('omnivoice');
        $language = chimTtsStudioResolveOmniVoiceLanguage($language);
        if ($endpoint === '') {
            return [];
        }

        $probe = chimTtsStudioProbeJson($endpoint . '/speakers_list_extended?language=' . rawurlencode($language));
        if ($probe['response'] === false || intval($probe['http_code']) < 200 || intval($probe['http_code']) >= 300) {
            return [];
        }

        $decoded = $probe['decoded'];
        if (!is_array($decoded)) {
            return [];
        }

        if (isset($decoded['speakers']) && is_array($decoded['speakers'])) {
            return $decoded['speakers'];
        }

        return $decoded;
    }
}

if (!function_exists('chimTtsStudioVoiceUploadPostFields')) {
    function chimTtsStudioVoiceUploadPostFields(string $driver, string $voicePath, string $fileType, string $fileName, string $language = ''): array
    {
        $postFields = [
            'wavFile' => new CURLFile($voicePath, $fileType, $fileName),
            'force' => 'true',
        ];
        if ($driver === 'omnivoice') {
            $voiceName = pathinfo($fileName, PATHINFO_FILENAME);
            $postFields['language'] = chimTtsStudioResolveOmniVoiceLanguage($language);
            $postFields['speaker_name'] = $voiceName;
            $postFields['display_name'] = $voiceName;
        }
        return $postFields;
    }
}

if (!function_exists('chimTtsStudioDeleteVoice')) {
    function chimTtsStudioDeleteVoice(string $driver, string $voice, string $language = ''): array
    {
        $endpoint = chimTtsStudioResolveEndpointForDriver($driver);
        if ($endpoint === '') {
            return ['success' => false, 'message' => 'No endpoint is configured for this provider.'];
        }
        if (chimTtsStudioIsAudioCppPocketTts($driver, $endpoint)) {
            return [
                'success' => false,
                'message' => 'audio.cpp uses the local data/voices sample directly; upload a replacement local WAV instead.',
            ];
        }

        $voice = preg_replace('/\.wav$/i', '', trim($voice));
        if ($voice === '' || !preg_match('/^[A-Za-z0-9][A-Za-z0-9_.-]*$/', $voice)) {
            return ['success' => false, 'message' => 'Invalid voice ID.'];
        }

        $url = rtrim($endpoint, '/') . '/voices/' . rawurlencode($voice);
        if ($driver === 'omnivoice') {
            $url .= '?language=' . rawurlencode(chimTtsStudioResolveOmniVoiceLanguage($language));
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['accept: application/json']);
        $response = curl_exec($ch);
        $httpCode = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            return ['success' => false, 'message' => 'Delete request failed: ' . $curlError];
        }

        $decoded = json_decode($response, true);
        $detailValue = is_array($decoded)
            ? ($decoded['detail'] ?? $decoded['message'] ?? $decoded['error'] ?? '')
            : '';
        if (is_array($detailValue)) {
            $detailParts = array_filter([
                trim(strval($detailValue['error'] ?? '')),
                trim(strval($detailValue['reason'] ?? '')),
                trim(strval($detailValue['hint'] ?? '')),
            ]);
            $detail = implode(': ', $detailParts);
        } else {
            $detail = trim(strval($detailValue));
        }
        if ($detail === '' && !is_array($decoded)) {
            $detail = trim($response);
        }
        if ($httpCode >= 200 && $httpCode < 300) {
            return ['success' => true, 'message' => $detail !== '' ? $detail : 'Voice removed from provider.'];
        }

        if ($httpCode === 404 && $detail === '') {
            $detail = 'Voice was not found, or this connector version does not support deletion.';
        }
        return [
            'success' => false,
            'message' => $detail !== '' ? $detail : 'Provider returned HTTP ' . $httpCode . '.',
        ];
    }
}

if (!function_exists('chimTtsStudioVoiceUploadResult')) {
    function chimTtsStudioVoiceUploadResult(string $driver, int $httpCode, string $response): array
    {
        if ($driver !== 'omnivoice') {
            $alreadyExists = ($httpCode === 400 && stripos($response, 'already exists') !== false);
            return [
                'success' => $httpCode === 200 || $alreadyExists,
                'message' => $alreadyExists ? 'Voice already exists on server.' : trim($response),
            ];
        }

        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            return [
                'success' => false,
                'message' => 'OmniVoice returned an unreadable response: ' . $response,
            ];
        }

        $status = strtolower(trim(strval($decoded['import_status'] ?? $decoded['status'] ?? '')));
        if ($httpCode >= 200 && $httpCode < 300 && in_array($status, ['runtime_ready', 'ready', 'ok'], true)) {
            return [
                'success' => true,
                'message' => 'Voice imported into OmniVoice.',
            ];
        }

        $error = trim(strval($decoded['transcription_error'] ?? ''));
        if ($status === 'needs_reference_text') {
            return [
                'success' => false,
                'message' => $error !== ''
                    ? 'Uploaded, but local STT could not create reference text: ' . $error
                    : 'Uploaded, but local STT could not create reference text.',
            ];
        }

        return [
            'success' => false,
            'message' => trim($response) !== '' ? trim($response) : ('HTTP ' . $httpCode),
        ];
    }
}

if (!function_exists('chimTtsStudioVoiceSamplePath')) {
    function chimTtsStudioVoiceSamplePath(string $voice): string
    {
        $voice = trim($voice);
        $voice = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $voice);
        $voice = basename($voice);
        if (strtolower(substr($voice, -4)) !== '.wav') {
            $voice .= '.wav';
        }

        return __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'voices' . DIRECTORY_SEPARATOR . $voice;
    }
}

if (!function_exists('chimTtsStudioSyncVoiceSampleForTest')) {
    function chimTtsStudioSyncVoiceSampleForTest(string $driver, string $voice, string $logFile = '', string $language = ''): array
    {
        $endpoint = chimTtsStudioResolveEndpointForDriver($driver);
        if ($endpoint === '') {
            return ['success' => false, 'message' => 'No endpoint configured for ' . $driver . '.'];
        }

        $voiceSamplePath = chimTtsStudioVoiceSamplePath($voice);
        if (!file_exists($voiceSamplePath)) {
            return ['success' => false, 'message' => 'Voice sample not found: ' . basename($voiceSamplePath) . '.'];
        }

        $fileName = basename($voiceSamplePath);
        $fileType = function_exists('mime_content_type') ? trim(strval(@mime_content_type($voiceSamplePath))) : '';
        if ($fileType === '') {
            $fileType = 'audio/wav';
        }

        $url = $endpoint . '/upload_sample';
        @file_put_contents($logFile, "Attempting automatic voice sync to {$url} for {$fileName}\n", FILE_APPEND);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, chimTtsStudioVoiceUploadPostFields($driver, $voiceSamplePath, $fileType, $fileName, $language));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'accept: application/json',
            'Content-Type: multipart/form-data'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);

        $response = curl_exec($ch);
        $httpCode = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
        $curlError = curl_error($ch);
        curl_close($ch);

        $uploadResult = chimTtsStudioVoiceUploadResult($driver, $httpCode, strval($response));
        if ($curlError !== '') {
            @file_put_contents($logFile, "Automatic voice sync failed: {$curlError}\n", FILE_APPEND);
            return ['success' => false, 'message' => 'Voice sync failed: ' . $curlError];
        }
        if ($uploadResult['success']) {
            chimTtsStudioStoreSpeakersList($driver, chimTtsStudioFetchSpeakersList($driver, $language), $language);
            @file_put_contents($logFile, "Automatic voice sync succeeded with HTTP {$httpCode}\n", FILE_APPEND);
            return [
                'success' => true,
                'message' => $uploadResult['message'],
            ];
        }

        @file_put_contents($logFile, "Automatic voice sync failed with HTTP {$httpCode}: " . strval($response) . "\n", FILE_APPEND);
        return ['success' => false, 'message' => 'Voice sync failed with HTTP ' . $httpCode . ': ' . $uploadResult['message']];
    }
}

if (!function_exists('chimTtsStudioConfigureCompatibleTestGlobals')) {
    function chimTtsStudioConfigureCompatibleTestGlobals(string $driver, string $ttsFunction, string $voice, string $language = ''): void
    {
        chimTtsStudioApplyConnectorGlobals($driver);
        $GLOBALS["TTSFUNCTION"] = $ttsFunction;
        $GLOBALS["HERIKA_NAME"] = "The Narrator";
        $GLOBALS["AVOID_TTS_CACHE"] = true;
        $GLOBALS["TTS_FFMPEG_FILTERS"] = [];
        $GLOBALS["HERIKA_ANIMATIONS"] = false;
        $GLOBALS["SCRIPTLINE_LISTENER"] = '';
        $GLOBALS["SCRIPTLINE_EXPRESSION"] = '';
        $GLOBALS["DEBUG_DATA"] = [];
        if (!isset($GLOBALS["HTTP_TIMEOUT"]) || (int)$GLOBALS["HTTP_TIMEOUT"] <= 0) {
            $GLOBALS["HTTP_TIMEOUT"] = 20;
        }
        $GLOBALS["FEATURES"] = $GLOBALS["FEATURES"] ?? [];
        if (!isset($GLOBALS["FEATURES"]["MISC"])) {
            $GLOBALS["FEATURES"]["MISC"] = [];
        }
        if (!isset($GLOBALS["FEATURES"]["MISC"]["TTS_RANDOM_PITCH"])) {
            $GLOBALS["FEATURES"]["MISC"]["TTS_RANDOM_PITCH"] = false;
        }
        if (!isset($GLOBALS["PLAYER_NAME"])) {
            $GLOBALS["PLAYER_NAME"] = 'Player';
        }
        $GLOBALS["PATCH_DONT_STORE_SPEECH_ON_DB"] = true;
        $GLOBALS["PATCH_OVERRIDE_VOICE"] = $voice;
        $language = chimTtsStudioSanitizeLanguage($language);
        if ($language !== '') {
            $GLOBALS["PATCH_OVERRIDE_TTS_LANGUAGE"] = $language;
        }
        if (!isset($GLOBALS["TRACK"]) || !is_array($GLOBALS["TRACK"])) {
            $GLOBALS["TRACK"] = [];
        }
        $GLOBALS["TRACK"]["FILES_GENERATED"] = [];
    }
}

if (!function_exists('chimTtsStudioRunReturnLinesTest')) {
    function chimTtsStudioRunReturnLinesTest(string $testText, string $logFile): array
    {
        if (!function_exists('returnLines')) {
            @file_put_contents($logFile, "ERROR: returnLines function not found!\n", FILE_APPEND);
            return ['file' => '', 'error' => 'returnLines function not available'];
        }

        $GLOBALS["TRACK"]["FILES_GENERATED"] = [];
        $bufferLevel = ob_get_level();
        ob_start();

        try {
            @file_put_contents($logFile, "Calling returnLines...\n", FILE_APPEND);
            returnLines([$testText], false);
            $capturedOutput = ob_get_clean();
            if ($capturedOutput !== '') {
                @file_put_contents($logFile, "Captured output from returnLines: " . $capturedOutput . "\n", FILE_APPEND);
            }

            $file = isset($GLOBALS["TRACK"]["FILES_GENERATED"][0])
                ? basename((string)$GLOBALS["TRACK"]["FILES_GENERATED"][0])
                : '';
            @file_put_contents($logFile, "Generated file: " . ($file ?: 'NONE') . "\n", FILE_APPEND);

            if ($file === '') {
                return ['file' => '', 'error' => 'Failed to generate audio file'];
            }

            return ['file' => $file, 'error' => ''];
        } catch (Throwable $e) {
            while (ob_get_level() > $bufferLevel) {
                ob_end_clean();
            }
            @file_put_contents($logFile, "EXCEPTION: " . $e->getMessage() . "\n", FILE_APPEND);
            return ['file' => '', 'error' => $e->getMessage()];
        }
    }
}

// XTTS voice test handler - MUST be before output buffering starts
if (isset($_GET['action']) && $_GET['action'] === 'test_xtts' && isset($_GET['voice'])) {
    // Set up logging
    $logFile = $enginePath . 'log.txt';
    $logMsg = "\n=== XTTS Test Handler Started at " . date('Y-m-d H:i:s') . " ===\n";
    @file_put_contents($logFile, $logMsg, FILE_APPEND);

    $voice = $_GET['voice'];
    @file_put_contents($logFile, "Voice requested: " . $voice . "\n", FILE_APPEND);

    // Set up TTS test environment (same as global_settings)
    try { @set_time_limit(30); } catch (Throwable $_) {}
    try { @ini_set('default_socket_timeout', '20'); } catch (Throwable $_) {}

    // Initialize database if needed
    try {
        if (!isset($GLOBALS['db']) || !$GLOBALS['db']) {
            @include_once($enginePath . "conf" . DIRECTORY_SEPARATOR . "conf.php");
            if (isset($GLOBALS["DBDRIVER"])) {
                @require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . $GLOBALS["DBDRIVER"] . ".class.php");
            }
            $GLOBALS['db'] = new sql();
        }
        @file_put_contents($logFile, "Database initialized\n", FILE_APPEND);
    } catch (Throwable $e) {
        @file_put_contents($logFile, "Database init error: " . $e->getMessage() . "\n", FILE_APPEND);
    }
    chimTtsStudioApplyConnectorGlobals('xtts-fastapi');
    $GLOBALS["TTSFUNCTION"] = 'xtts-fastapi';
    $GLOBALS["HERIKA_NAME"] = "The Narrator";
    $GLOBALS["AVOID_TTS_CACHE"] = true;
    $GLOBALS["TTS_FFMPEG_FILTERS"] = [];
    $GLOBALS["HERIKA_ANIMATIONS"] = false;
    $GLOBALS["SCRIPTLINE_LISTENER"] = '';
    $GLOBALS["SCRIPTLINE_EXPRESSION"] = '';
    $GLOBALS["DEBUG_DATA"] = [];
    if (!isset($GLOBALS["HTTP_TIMEOUT"]) || (int)$GLOBALS["HTTP_TIMEOUT"] <= 0) { $GLOBALS["HTTP_TIMEOUT"] = 20; }
    $GLOBALS["FEATURES"] = $GLOBALS["FEATURES"] ?? [];
    if (!isset($GLOBALS["FEATURES"]["MISC"])) $GLOBALS["FEATURES"]["MISC"] = [];
    if (!isset($GLOBALS["FEATURES"]["MISC"]["TTS_RANDOM_PITCH"])) $GLOBALS["FEATURES"]["MISC"]["TTS_RANDOM_PITCH"] = false;
    if (!isset($GLOBALS["PLAYER_NAME"])) $GLOBALS["PLAYER_NAME"] = 'Player';
    $GLOBALS["PATCH_DONT_STORE_SPEECH_ON_DB"] = true;
    $GLOBALS["PATCH_OVERRIDE_VOICE"] = $voice;

    @file_put_contents($logFile, "Globals configured\n", FILE_APPEND);

    $testText = "CHIM has been described as the secret syllable of royalty, and can be considered a form of Apotheosis";

    // Start output buffering to catch any unwanted output
    ob_start();

    try {
        @file_put_contents($logFile, "Calling returnLines...\n", FILE_APPEND);

        // Check if returnLines exists
        if (!function_exists('returnLines')) {
            @file_put_contents($logFile, "ERROR: returnLines function not found!\n", FILE_APPEND);
            ob_end_clean();
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode(['error' => 'returnLines function not available']);
            exit;
        }

        returnLines([$testText], false);
        @file_put_contents($logFile, "returnLines completed\n", FILE_APPEND);

        // Clear any output from returnLines
        $capturedOutput = ob_get_clean();
        if ($capturedOutput !== '') {
            @file_put_contents($logFile, "Captured output from returnLines: " . $capturedOutput . "\n", FILE_APPEND);
        }

        $file = isset($GLOBALS["TRACK"]["FILES_GENERATED"][0]) ? basename((string)$GLOBALS["TRACK"]["FILES_GENERATED"][0]) : '';
        @file_put_contents($logFile, "Generated file: " . ($file ?: 'NONE') . "\n", FILE_APPEND);
        $testError = 'Failed to generate audio file';

        if ($file === '') {
            @file_put_contents($logFile, "No XTTS test file generated; attempting automatic voice sync and retry.\n", FILE_APPEND);
            $syncResult = chimTtsStudioSyncVoiceSampleForTest('xtts-fastapi', $voice, $logFile);
            if ($syncResult['success']) {
                chimTtsStudioConfigureCompatibleTestGlobals('xtts-fastapi', 'xtts-fastapi', $voice);
                $retryResult = chimTtsStudioRunReturnLinesTest($testText, $logFile);
                $file = $retryResult['file'];
                if ($file === '') {
                    @file_put_contents($logFile, "XTTS retry after sync failed: " . $retryResult['error'] . "\n", FILE_APPEND);
                    $testError = 'Automatic voice sync completed, but retry still failed: ' . $retryResult['error'];
                }
            } else {
                @file_put_contents($logFile, "Automatic XTTS voice sync failed: " . $syncResult['message'] . "\n", FILE_APPEND);
                $testError = 'Failed to generate audio file. Automatic voice sync failed: ' . $syncResult['message'];
            }
        }

        header('Content-Type: application/json');
        if ($file !== '') {
            $url = $webRoot . '/soundcache/' . $file . '?ts=' . time();
            @file_put_contents($logFile, "URL: " . $url . "\n", FILE_APPEND);
            @file_put_contents($logFile, "Sending JSON response...\n", FILE_APPEND);
            echo json_encode(['url' => $url]);
            @file_put_contents($logFile, "JSON sent successfully\n", FILE_APPEND);
        } else {
            @file_put_contents($logFile, "ERROR: No file generated\n", FILE_APPEND);
            http_response_code(500);
            echo json_encode(['error' => $testError]);
        }
    } catch (Throwable $e) {
        $errMsg = "EXCEPTION: " . $e->getMessage() . "\nTrace: " . $e->getTraceAsString() . "\n";
        @file_put_contents($logFile, $errMsg, FILE_APPEND);
        ob_end_clean();
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }

    @file_put_contents($logFile, "=== Test Handler Complete ===\n\n", FILE_APPEND);
    unset($GLOBALS["PATCH_OVERRIDE_VOICE"]);
    exit;
}

// Chatterbox voice test handler
if (isset($_GET['action']) && $_GET['action'] === 'test_chatterbox' && isset($_GET['voice'])) {
    $logFile = $enginePath . 'log.txt';
    $logMsg = "\n=== Chatterbox Test Handler Started at " . date('Y-m-d H:i:s') . " ===\n";
    @file_put_contents($logFile, $logMsg, FILE_APPEND);

    $voice = $_GET['voice'];
    @file_put_contents($logFile, "Voice requested: " . $voice . "\n", FILE_APPEND);

    try { @set_time_limit(30); } catch (Throwable $_) {}
    try { @ini_set('default_socket_timeout', '20'); } catch (Throwable $_) {}

    try {
        if (!isset($GLOBALS['db']) || !$GLOBALS['db']) {
            @include_once($enginePath . "conf" . DIRECTORY_SEPARATOR . "conf.php");
            if (isset($GLOBALS["DBDRIVER"])) {
                @require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . $GLOBALS["DBDRIVER"] . ".class.php");
            }
            $GLOBALS['db'] = new sql();
        }
    } catch (Throwable $e) {
        @file_put_contents($logFile, "Database init error: " . $e->getMessage() . "\n", FILE_APPEND);
    }
    chimTtsStudioApplyConnectorGlobals('chatterbox');
    $GLOBALS["TTSFUNCTION"] = 'chatterbox';
    $GLOBALS["HERIKA_NAME"] = "The Narrator";
    $GLOBALS["AVOID_TTS_CACHE"] = true;
    $GLOBALS["TTS_FFMPEG_FILTERS"] = [];
    $GLOBALS["HERIKA_ANIMATIONS"] = false;
    $GLOBALS["SCRIPTLINE_LISTENER"] = '';
    $GLOBALS["SCRIPTLINE_EXPRESSION"] = '';
    $GLOBALS["DEBUG_DATA"] = [];
    if (!isset($GLOBALS["HTTP_TIMEOUT"]) || (int)$GLOBALS["HTTP_TIMEOUT"] <= 0) { $GLOBALS["HTTP_TIMEOUT"] = 20; }
    $GLOBALS["FEATURES"] = $GLOBALS["FEATURES"] ?? [];
    if (!isset($GLOBALS["FEATURES"]["MISC"])) $GLOBALS["FEATURES"]["MISC"] = [];
    if (!isset($GLOBALS["FEATURES"]["MISC"]["TTS_RANDOM_PITCH"])) $GLOBALS["FEATURES"]["MISC"]["TTS_RANDOM_PITCH"] = false;
    if (!isset($GLOBALS["PLAYER_NAME"])) $GLOBALS["PLAYER_NAME"] = 'Player';
    $GLOBALS["PATCH_DONT_STORE_SPEECH_ON_DB"] = true;
    $GLOBALS["PATCH_OVERRIDE_VOICE"] = $voice;

    $testText = "CHIM has been described as the secret syllable of royalty, and can be considered a form of Apotheosis";

    ob_start();

    try {
        if (!function_exists('returnLines')) {
            ob_end_clean();
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode(['error' => 'returnLines function not available']);
            exit;
        }

        returnLines([$testText], false);

        $capturedOutput = ob_get_clean();
        if ($capturedOutput !== '') {
            @file_put_contents($logFile, "Captured output: " . $capturedOutput . "\n", FILE_APPEND);
        }

        $file = isset($GLOBALS["TRACK"]["FILES_GENERATED"][0]) ? basename((string)$GLOBALS["TRACK"]["FILES_GENERATED"][0]) : '';
        $testError = 'Failed to generate audio file';

        if ($file === '') {
            @file_put_contents($logFile, "No Chatterbox test file generated; attempting automatic voice sync and retry.\n", FILE_APPEND);
            $syncResult = chimTtsStudioSyncVoiceSampleForTest('chatterbox', $voice, $logFile);
            if ($syncResult['success']) {
                chimTtsStudioConfigureCompatibleTestGlobals('chatterbox', 'chatterbox', $voice);
                $retryResult = chimTtsStudioRunReturnLinesTest($testText, $logFile);
                $file = $retryResult['file'];
                if ($file === '') {
                    @file_put_contents($logFile, "Chatterbox retry after sync failed: " . $retryResult['error'] . "\n", FILE_APPEND);
                    $testError = 'Automatic voice sync completed, but retry still failed: ' . $retryResult['error'];
                }
            } else {
                @file_put_contents($logFile, "Automatic Chatterbox voice sync failed: " . $syncResult['message'] . "\n", FILE_APPEND);
                $testError = 'Failed to generate audio file. Automatic voice sync failed: ' . $syncResult['message'];
            }
        }

        header('Content-Type: application/json');
        if ($file !== '') {
            $url = $webRoot . '/soundcache/' . $file . '?ts=' . time();
            echo json_encode(['url' => $url]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => $testError]);
        }
    } catch (Throwable $e) {
        @file_put_contents($logFile, "EXCEPTION: " . $e->getMessage() . "\n", FILE_APPEND);
        ob_end_clean();
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }

    unset($GLOBALS["PATCH_OVERRIDE_VOICE"]);
    exit;
}

// PocketTTS voice test handler
if (isset($_GET['action']) && $_GET['action'] === 'test_pockettts' && isset($_GET['voice'])) {
    $logFile = $enginePath . 'log.txt';
    $logMsg = "\n=== PocketTTS Test Handler Started at " . date('Y-m-d H:i:s') . " ===\n";
    @file_put_contents($logFile, $logMsg, FILE_APPEND);
    @file_put_contents($logFile, "ACTION: test_pockettts confirmed\n", FILE_APPEND);

    $voice = $_GET['voice'];
    @file_put_contents($logFile, "Voice requested: " . $voice . "\n", FILE_APPEND);

    try { @set_time_limit(30); } catch (Throwable $_) {}
    try { @ini_set('default_socket_timeout', '20'); } catch (Throwable $_) {}

    try {
        if (!isset($GLOBALS['db']) || !$GLOBALS['db']) {
            @include_once($enginePath . "conf" . DIRECTORY_SEPARATOR . "conf.php");
            if (isset($GLOBALS["DBDRIVER"])) {
                @require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . $GLOBALS["DBDRIVER"] . ".class.php");
            }
            $GLOBALS['db'] = new sql();
        }
    } catch (Throwable $e) {
        @file_put_contents($logFile, "Database init error: " . $e->getMessage() . "\n", FILE_APPEND);
    }
    chimTtsStudioApplyConnectorGlobals('pockettts');
    $GLOBALS["TTSFUNCTION"] = 'pockettts';
    @file_put_contents($logFile, "TTSFUNCTION set to: pockettts\n", FILE_APPEND);
    $GLOBALS["HERIKA_NAME"] = "The Narrator";
    $GLOBALS["AVOID_TTS_CACHE"] = true;
    $GLOBALS["TTS_FFMPEG_FILTERS"] = [];
    $GLOBALS["HERIKA_ANIMATIONS"] = false;
    $GLOBALS["SCRIPTLINE_LISTENER"] = '';
    $GLOBALS["SCRIPTLINE_EXPRESSION"] = '';
    $GLOBALS["DEBUG_DATA"] = [];
    if (!isset($GLOBALS["HTTP_TIMEOUT"]) || (int)$GLOBALS["HTTP_TIMEOUT"] <= 0) { $GLOBALS["HTTP_TIMEOUT"] = 20; }
    $GLOBALS["FEATURES"] = $GLOBALS["FEATURES"] ?? [];
    if (!isset($GLOBALS["FEATURES"]["MISC"])) $GLOBALS["FEATURES"]["MISC"] = [];
    if (!isset($GLOBALS["FEATURES"]["MISC"]["TTS_RANDOM_PITCH"])) $GLOBALS["FEATURES"]["MISC"]["TTS_RANDOM_PITCH"] = false;
    if (!isset($GLOBALS["PLAYER_NAME"])) $GLOBALS["PLAYER_NAME"] = 'Player';
    $GLOBALS["PATCH_DONT_STORE_SPEECH_ON_DB"] = true;
    $GLOBALS["PATCH_OVERRIDE_VOICE"] = $voice;

    $testText = "CHIM has been described as the secret syllable of royalty, and can be considered a form of Apotheosis";

    ob_start();

    try {
        if (!function_exists('returnLines')) {
            ob_end_clean();
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode(['error' => 'returnLines function not available']);
            exit;
        }

        returnLines([$testText], false);

        $capturedOutput = ob_get_clean();
        if ($capturedOutput !== '') {
            @file_put_contents($logFile, "Captured output: " . $capturedOutput . "\n", FILE_APPEND);
        }

        $file = isset($GLOBALS["TRACK"]["FILES_GENERATED"][0]) ? basename((string)$GLOBALS["TRACK"]["FILES_GENERATED"][0]) : '';
        $testError = 'Failed to generate audio file';

        if ($file === '') {
            @file_put_contents($logFile, "No PocketTTS test file generated; attempting automatic voice sync and retry.\n", FILE_APPEND);
            $syncResult = chimTtsStudioSyncVoiceSampleForTest('pockettts', $voice, $logFile);
            if ($syncResult['success']) {
                chimTtsStudioConfigureCompatibleTestGlobals('pockettts', 'pockettts', $voice);
                $retryResult = chimTtsStudioRunReturnLinesTest($testText, $logFile);
                $file = $retryResult['file'];
                if ($file === '') {
                    @file_put_contents($logFile, "PocketTTS retry after sync failed: " . $retryResult['error'] . "\n", FILE_APPEND);
                    $testError = 'Automatic voice sync completed, but retry still failed: ' . $retryResult['error'];
                }
            } else {
                @file_put_contents($logFile, "Automatic PocketTTS voice sync failed: " . $syncResult['message'] . "\n", FILE_APPEND);
                $testError = 'Failed to generate audio file. Automatic voice sync failed: ' . $syncResult['message'];
            }
        }

        header('Content-Type: application/json');
        if ($file !== '') {
            $url = $webRoot . '/soundcache/' . $file . '?ts=' . time();
            echo json_encode(['url' => $url]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => $testError]);
        }
    } catch (Throwable $e) {
        @file_put_contents($logFile, "EXCEPTION: " . $e->getMessage() . "\n", FILE_APPEND);
        ob_end_clean();
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }

    unset($GLOBALS["PATCH_OVERRIDE_VOICE"]);
    exit;
}

// OmniVoice voice test handler
if (isset($_GET['action']) && $_GET['action'] === 'test_omnivoice' && isset($_GET['voice'])) {
    $logFile = $enginePath . 'log.txt';
    $logMsg = "\n=== OmniVoice Test Handler Started at " . date('Y-m-d H:i:s') . " ===\n";
    @file_put_contents($logFile, $logMsg, FILE_APPEND);

    $voice = trim(strval($_GET['voice']));
    $language = chimTtsStudioResolveOmniVoiceLanguage($_GET['language'] ?? '');
    @file_put_contents($logFile, "Voice requested: {$voice}\nLanguage requested: {$language}\n", FILE_APPEND);

    try { @set_time_limit(60); } catch (Throwable $_) {}
    try { @ini_set('default_socket_timeout', '45'); } catch (Throwable $_) {}

    try {
        if (!isset($GLOBALS['db']) || !$GLOBALS['db']) {
            @include_once($enginePath . "conf" . DIRECTORY_SEPARATOR . "conf.php");
            if (isset($GLOBALS["DBDRIVER"])) {
                @require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . $GLOBALS["DBDRIVER"] . ".class.php");
            }
            $GLOBALS['db'] = new sql();
        }
    } catch (Throwable $e) {
        @file_put_contents($logFile, "Database init error: " . $e->getMessage() . "\n", FILE_APPEND);
    }

    chimTtsStudioConfigureCompatibleTestGlobals('omnivoice', 'omnivoice', $voice, $language);
    $testText = "CHIM has been described as the secret syllable of royalty, and can be considered a form of Apotheosis";
    $testResult = chimTtsStudioRunReturnLinesTest($testText, $logFile);
    $file = $testResult['file'];
    $testError = $testResult['error'] !== '' ? $testResult['error'] : 'Failed to generate audio file';

    if ($file === '') {
        @file_put_contents($logFile, "No OmniVoice test file generated; attempting automatic voice sync and retry.\n", FILE_APPEND);
        $syncResult = chimTtsStudioSyncVoiceSampleForTest('omnivoice', $voice, $logFile, $language);
        if ($syncResult['success']) {
            chimTtsStudioConfigureCompatibleTestGlobals('omnivoice', 'omnivoice', $voice, $language);
            $retryResult = chimTtsStudioRunReturnLinesTest($testText, $logFile);
            $file = $retryResult['file'];
            if ($file === '') {
                @file_put_contents($logFile, "OmniVoice retry after sync failed: " . $retryResult['error'] . "\n", FILE_APPEND);
                $testError = 'Automatic voice sync completed, but retry still failed: ' . $retryResult['error'];
            }
        } else {
            @file_put_contents($logFile, "Automatic OmniVoice voice sync failed: " . $syncResult['message'] . "\n", FILE_APPEND);
            $testError = 'Failed to generate audio file. Automatic voice sync failed: ' . $syncResult['message'];
        }
    }

    header('Content-Type: application/json');
    if ($file !== '') {
        echo json_encode(['url' => $webRoot . '/soundcache/' . $file . '?ts=' . time()]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => $testError]);
    }

    unset($GLOBALS["PATCH_OVERRIDE_VOICE"], $GLOBALS["PATCH_OVERRIDE_TTS_LANGUAGE"]);
    exit;
}

// Handle test voice requests (GET) - MUST be before any output buffering
if (isset($_GET['action']) && ($_GET['action'] === 'test_cartesia' || $_GET['action'] === 'test_inworld') && isset($_GET['voice'])) {
    // Initialize database connection for test handlers
    if (!isset($GLOBALS["db"]) || !$GLOBALS["db"]) {
        require_once(__DIR__ . "/../lib/{$GLOBALS["DBDRIVER"]}.class.php");
    }
    if (!isset($GLOBALS["db"]) || !$GLOBALS["db"]) {
        $GLOBALS["db"] = new sql();
    }

    // Helper function for test handlers
    function getClonedVoicesForTest($provider) {
        global $db;
        if (!isset($GLOBALS["db"]) || !$GLOBALS["db"]) {
            return [];
        }
        if ($provider === 'cartesia') {
            chimTtsStudioApplyConnectorGlobals('cartesia');
            require_once(__DIR__ . '/../tts/tts-cartesia.php');
            return getCartesiaCachedVoicesMap(true);
        }
        if ($provider === 'inworld') {
            chimTtsStudioApplyConnectorGlobals('inworld');
            require_once(__DIR__ . '/../tts/tts-inworld.php');
            return getInworldCachedVoicesMap(true);
        }
        $db = $GLOBALS["db"];
        $prefix = $provider . '_voice_id_';
        $prefixEscaped = $db->escape($prefix);
        $rows = $db->fetchAll("SELECT id, value FROM conf_opts WHERE id LIKE '{$prefixEscaped}%'");
        $cloned = [];
        foreach ($rows as $row) {
            $voiceName = str_replace($prefix, '', $row['id']);
            $cloned[$voiceName] = $row['value'];
        }
        return $cloned;
    }

    if ($_GET['action'] === 'test_cartesia') {
        $voice = $_GET['voice'];
        $cartesiaStatus = getCartesiaConfigurationStatus();
        if (!$cartesiaStatus['configured']) {
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode(['error' => $cartesiaStatus['message'] !== '' ? $cartesiaStatus['message'] : 'Cartesia is not fully configured.']);
            exit;
        }

        chimTtsStudioApplyConnectorGlobals('cartesia');
        $clonedVoices = getClonedVoicesForTest('cartesia');

        if (!isset($clonedVoices[$voice]) || empty($clonedVoices[$voice])) {
            $syncedVoiceId = getOrCreateCartesiaVoice($voice);
            if ($syncedVoiceId !== false && !empty($syncedVoiceId)) {
                $clonedVoices = getClonedVoicesForTest('cartesia');
                if (!isset($clonedVoices[$voice]) || empty($clonedVoices[$voice])) {
                    $clonedVoices[$voice] = $syncedVoiceId;
                }
            }
        }

        if (!isset($clonedVoices[$voice]) || empty($clonedVoices[$voice])) {
            $detailedError = function_exists('getCartesiaLastError') ? getCartesiaLastError() : '';
            $message = 'Voice not generated yet and automatic sync failed.';
            if ($detailedError !== '') {
                $message .= ' ' . $detailedError;
            }
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode(['error' => $message]);
            exit;
        }

        require_once(__DIR__ . '/../tts/tts-cartesia.php');
        $testText = 'CHIM has been described as the secret syllable of royalty, and can be considered a form of Apotheosis';
        $audioData = generateCartesiaTTS($testText, $clonedVoices[$voice]);
        if (($audioData === false || empty($audioData))) {
            $syncedVoiceId = getOrCreateCartesiaVoice($voice);
            if ($syncedVoiceId !== false && !empty($syncedVoiceId)) {
                $audioData = generateCartesiaTTS($testText, $syncedVoiceId);
            }
        }

        if ($audioData !== false && !empty($audioData)) {
            // Save to soundcache like other TTS functions
            $soundcacheDir = __DIR__ . '/../soundcache/';
            if (!is_dir($soundcacheDir)) {
                mkdir($soundcacheDir, 0777, true);
            }
            $testHash = md5('test_cartesia_' . $voice . '_' . $testText);
            $audioFile = $soundcacheDir . $testHash . '.wav';
            file_put_contents($audioFile, $audioData);

            // Return URL to the audio file
            $webRoot = dirname(dirname($_SERVER['SCRIPT_NAME']));
            if ($webRoot == '/') $webRoot = '';
            $webRoot = rtrim($webRoot, '/');
            $audioUrl = $webRoot . '/soundcache/' . $testHash . '.wav?ts=' . time();

            header('Content-Type: application/json');
            echo json_encode(['url' => $audioUrl]);
            exit;
        }

        header('Content-Type: application/json');
        http_response_code(500);
        $detailedError = function_exists('getCartesiaLastError') ? getCartesiaLastError() : '';
        $message = 'Failed to generate test audio after automatic sync retry. The voice ID may be invalid or expired.';
        if ($detailedError !== '') {
            $message .= ' ' . $detailedError;
        }
        echo json_encode(['error' => $message]);
        exit;
    }

    if ($_GET['action'] === 'test_inworld') {
        $voice = $_GET['voice'];
        $inworldStatus = getInworldConfigurationStatus();
        if (!$inworldStatus['configured']) {
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode(['error' => $inworldStatus['message'] !== '' ? $inworldStatus['message'] : 'Inworld is not fully configured.']);
            exit;
        }

        chimTtsStudioApplyConnectorGlobals('inworld');
        $clonedVoices = getClonedVoicesForTest('inworld');

        if (!isset($clonedVoices[$voice]) || empty($clonedVoices[$voice])) {
            $syncedVoiceId = getOrCreateInworldVoice($voice);
            if ($syncedVoiceId !== false && !empty($syncedVoiceId)) {
                $clonedVoices = getClonedVoicesForTest('inworld');
                if (!isset($clonedVoices[$voice]) || empty($clonedVoices[$voice])) {
                    $clonedVoices[$voice] = $syncedVoiceId;
                }
            }
        }

        if (!isset($clonedVoices[$voice]) || empty($clonedVoices[$voice])) {
            $detailedError = function_exists('getInworldLastError') ? getInworldLastError() : '';
            $message = 'Voice not generated yet and automatic sync failed.';
            if ($detailedError !== '') {
                $message .= ' ' . $detailedError;
            }
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode(['error' => $message]);
            exit;
        }

        require_once(__DIR__ . '/../tts/tts-inworld.php');
        $testText = 'CHIM has been described as the secret syllable of royalty, and can be considered a form of Apotheosis';

        // Generate raw PCM audio data (no output file = returns raw PCM)
        $audioData = generateInworldTTS($testText, $clonedVoices[$voice]);
        if (($audioData === false || empty($audioData))) {
            $syncedVoiceId = getOrCreateInworldVoice($voice);
            if ($syncedVoiceId !== false && !empty($syncedVoiceId)) {
                $audioData = generateInworldTTS($testText, $syncedVoiceId);
            }
        }

        if ($audioData !== false && !empty($audioData)) {
            // Save to soundcache like other TTS functions
            $soundcacheDir = __DIR__ . '/../soundcache/';
            if (!is_dir($soundcacheDir)) {
                mkdir($soundcacheDir, 0777, true);
            }
            $testHash = md5('test_inworld_' . $voice . '_' . $testText);

            // Write raw PCM to temporary file
            $pcmFile = $soundcacheDir . $testHash . '_temp.pcm';
            file_put_contents($pcmFile, $audioData);

            // Convert raw PCM to WAV using FFmpeg (Inworld returns LINEAR16 PCM at 22050 Hz, mono)
            $audioFile = $soundcacheDir . $testHash . '.wav';
            $ffmpegCmd = "ffmpeg -y -f s16le -ar 22050 -ac 1 -i \"$pcmFile\" -c:a pcm_s16le -ar 22050 -ac 1 \"$audioFile\" 2>&1";
            $ffmpegOutput = shell_exec($ffmpegCmd);

            // Clean up temporary PCM file
            if (file_exists($pcmFile)) {
                unlink($pcmFile);
            }

            // Check if WAV file was created successfully
            if (file_exists($audioFile) && filesize($audioFile) > 0) {
                // Return URL to the audio file
                $webRoot = dirname(dirname($_SERVER['SCRIPT_NAME']));
                if ($webRoot == '/') $webRoot = '';
                $webRoot = rtrim($webRoot, '/');
                $audioUrl = $webRoot . '/soundcache/' . $testHash . '.wav?ts=' . time();

                header('Content-Type: application/json');
                echo json_encode(['url' => $audioUrl]);
                exit;
            } else {
                // FFmpeg conversion failed
                error_log("Inworld test audio FFmpeg conversion failed: " . substr($ffmpegOutput, 0, 500));
                header('Content-Type: application/json');
                http_response_code(500);
                echo json_encode(['error' => 'Failed to convert audio to WAV format. Check server logs.']);
                exit;
            }
        }

        header('Content-Type: application/json');
        http_response_code(500);
        $detailedError = function_exists('getInworldLastError') ? getInworldLastError() : '';
        $message = 'Failed to generate test audio after automatic sync retry. The voice ID may be invalid or expired.';
        if ($detailedError !== '') {
            $message .= ' ' . $detailedError;
        }
        echo json_encode(['error' => $message]);
        exit;
    }
}

// AJAX Batch Upload Handler - MUST be before output buffering starts
if (isset($_GET['action']) && $_GET['action'] === 'batch_process' && isset($_GET['provider']) && isset($_GET['voice'])) {
    header('Content-Type: application/json');

    $provider = $_GET['provider'];
    $voiceName = $_GET['voice'];
    $response = ['success' => false, 'message' => ''];

    try {
        // Initialize database if needed
        if (!isset($GLOBALS['db']) || !$GLOBALS['db']) {
            @include_once($enginePath . "conf" . DIRECTORY_SEPARATOR . "conf.php");
            if (isset($GLOBALS["DBDRIVER"])) {
                @require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . $GLOBALS["DBDRIVER"] . ".class.php");
            }
            $GLOBALS['db'] = new sql();
        }

        $voicePath = $enginePath . 'data' . DIRECTORY_SEPARATOR . 'voices' . DIRECTORY_SEPARATOR . $voiceName;

        if (!file_exists($voicePath)) {
            $response['message'] = "Voice file not found: {$voiceName}";
            echo json_encode($response);
            exit;
        }

        if (in_array($provider, ['xtts', 'chatterbox', 'pockettts', 'omnivoice'], true)) {
            // Upload to XTTS server
            $fileName = basename($voiceName);
            $fileType = mime_content_type($voicePath);
            $language = $provider === 'omnivoice'
                ? chimTtsStudioResolveOmniVoiceLanguage($_GET['language'] ?? '')
                : '';

            $driver = chimTtsStudioTabToDriver($provider);
            $endpoint = chimTtsStudioResolveEndpointForDriver($driver);
            if ($endpoint === '') {
                $response['message'] = 'No endpoint configured for ' . $provider;
                echo json_encode($response);
                exit;
            }

            if (chimTtsStudioIsAudioCppPocketTts($driver, $endpoint)) {
                chimTtsStudioStoreSpeakersList($driver, chimTtsStudioFetchSpeakersList($driver));
                $response['success'] = true;
                $response['message'] = 'Voice is available locally for audio.cpp PocketTTS';
                echo json_encode($response);
                exit;
            }

            $url = $endpoint . '/upload_sample';
            $postFields = chimTtsStudioVoiceUploadPostFields($driver, $voicePath, $fileType, $fileName, $language);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'accept: application/json',
                'Content-Type: multipart/form-data'
            ]);

            $curlResponse = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            if (curl_errno($ch)) {
                $response['message'] = 'cURL Error: ' . curl_error($ch);
            } else {
                $uploadResult = chimTtsStudioVoiceUploadResult($driver, intval($httpCode), strval($curlResponse));
                $response['success'] = $uploadResult['success'];
                $response['message'] = $uploadResult['success']
                    ? 'Successfully uploaded to ' . $provider . ' server'
                    : 'Upload failed (HTTP ' . $httpCode . '): ' . $uploadResult['message'];
                if ($uploadResult['success']) {
                    chimTtsStudioStoreSpeakersList($driver, chimTtsStudioFetchSpeakersList($driver, $language), $language);
                }
            }
            curl_close($ch);

        } elseif ($provider === 'cartesia') {
            // Load Cartesia TTS functions
            require_once($enginePath . 'tts' . DIRECTORY_SEPARATOR . 'tts-cartesia.php');
            chimTtsStudioApplyConnectorGlobals('cartesia');

            $voiceBasename = pathinfo($voiceName, PATHINFO_FILENAME);
            $result = getOrCreateCartesiaVoice($voiceBasename);

            if ($result !== false && !empty($result)) {
                $response['success'] = true;
                $response['message'] = 'Successfully generated voice for Cartesia';
                $response['voiceId'] = $result;
            } else {
                $errorMsg = error_get_last();
                if ($errorMsg && (strpos($errorMsg['message'], '429') !== false || strpos($errorMsg['message'], 'Too Many Requests') !== false)) {
                    $response['message'] = 'Rate limit reached. Please wait before uploading more.';
                    $response['rateLimit'] = true;
                } else {
                    $detailedError = function_exists('getCartesiaLastError') ? getCartesiaLastError() : '';
                    $response['message'] = $detailedError !== '' ? $detailedError : 'Failed to generate voice. Check API configuration.';
                }
            }

        } elseif ($provider === 'inworld') {
            // Load Inworld TTS functions
            require_once($enginePath . 'tts' . DIRECTORY_SEPARATOR . 'tts-inworld.php');
            chimTtsStudioApplyConnectorGlobals('inworld');

            $voiceBasename = pathinfo($voiceName, PATHINFO_FILENAME);
            $result = getOrCreateInworldVoice($voiceBasename);

            if ($result !== false && !empty($result)) {
                $response['success'] = true;
                $response['message'] = 'Successfully generated voice for Inworld';
                $response['voiceId'] = $result;
            } else {
                $errorMsg = error_get_last();
                if ($errorMsg && (strpos($errorMsg['message'], '429') !== false || strpos($errorMsg['message'], 'Too Many Requests') !== false)) {
                    $response['message'] = 'Rate limit reached. Please wait before uploading more.';
                    $response['rateLimit'] = true;
                } else {
                    $detailedError = function_exists('getInworldLastError') ? getInworldLastError() : '';
                    $response['message'] = $detailedError !== '' ? $detailedError : 'Failed to generate voice. Check API configuration.';
                }
            }

        } else {
            $response['message'] = 'Invalid provider: ' . $provider;
        }

    } catch (Throwable $e) {
        $response['message'] = 'Error: ' . $e->getMessage();
    }

    echo json_encode($response);
    exit;
}

$TITLE = "🔊 Voice Management";

ob_start();

include(__DIR__.DIRECTORY_SEPARATOR."tmpl/head.html");

// Add meta tag for API endpoint
echo '<meta name="api-endpoint" content="' . htmlspecialchars($GLOBALS["TTS"]["XTTSFASTAPI"]["endpoint"]) . '">';

$isEmbed = (isset($_GET['embed']) && $_GET['embed'] == '1');

$debugPaneLink = false;

// Enable error reporting (for development purposes)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Tab state from URL parameter
$activeTab = $_GET['tab'] ?? 'xtts';
$validTabs = ['xtts', 'chatterbox', 'pockettts', 'omnivoice', 'cartesia', 'inworld', 'fallbacks', 'pronunciations'];
if (!in_array($activeTab, $validTabs, true)) {
    $activeTab = 'xtts';
}

// Initialize database connection
if (!isset($GLOBALS["db"]) || !$GLOBALS["db"]) {
    require_once(__DIR__ . "/../lib/{$GLOBALS["DBDRIVER"]}.class.php");
}
if (!isset($GLOBALS["db"]) || !$GLOBALS["db"]) {
    $GLOBALS["db"] = new sql();
}

// Auto-refresh speakers list on page load if the active provider tab is empty/stale
$activeOmniVoiceLanguage = chimTtsStudioResolveOmniVoiceLanguage(
    $activeTab === 'omnivoice' ? ($_POST['language'] ?? ($_GET['language'] ?? '')) : ''
);
$activeTtsStudioDriver = chimTtsStudioTabToDriver($activeTab);
if ($activeTtsStudioDriver !== '') {
    $activeLanguageScope = $activeTtsStudioDriver === 'omnivoice' ? $activeOmniVoiceLanguage : '';
    $cachedSpeakers = chimTtsStudioGetCachedSpeakersList($activeTtsStudioDriver, $activeLanguageScope);
    if (empty($cachedSpeakers)) {
        chimTtsStudioStoreSpeakersList(
            $activeTtsStudioDriver,
            chimTtsStudioFetchSpeakersList($activeTtsStudioDriver, $activeLanguageScope),
            $activeLanguageScope
        );
    }
}

// Load TTS functions for Cartesia and Inworld
require_once(__DIR__ . '/../tts/tts-cartesia.php');
require_once(__DIR__ . '/../tts/tts-inworld.php');

// Helper functions

// Normalize speakers list from XTTS server into a flat array of speaker names.
// Standard XTTS returns: ["speaker1", "speaker2"]
// Mantella XTTS returns: {"en": {"speakers": ["speaker1", ...]}, "de": {"speakers": []}}
function normalizeSpeakersList($speakersList) {
    if (!is_array($speakersList)) {
        return [];
    }
    // Check if it's already a flat array of strings
    if (isset($speakersList[0]) || empty($speakersList)) {
        return $speakersList;
    }
    // Nested format: language code => {"speakers": [...]}
    $flat = [];
    foreach ($speakersList as $langData) {
        if (is_array($langData) && isset($langData['speakers']) && is_array($langData['speakers'])) {
            foreach ($langData['speakers'] as $speaker) {
                $flat[] = $speaker;
            }
        }
    }
    return array_unique($flat);
}

function getLocalVoices() {
    $voiceDir = __DIR__ . '/../data/voices/';
    $voices = [];
    if (is_dir($voiceDir)) {
        foreach (glob($voiceDir . '*.wav') as $file) {
            $voices[] = pathinfo($file, PATHINFO_FILENAME);
        }
    }
    sort($voices);
    return $voices;
}

function resolveLocalVoiceName($voiceName): string {
    $voiceName = basename(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, trim(strval($voiceName))));
    if (strtolower(substr($voiceName, -4)) === '.wav') {
        $voiceName = substr($voiceName, 0, -4);
    }
    if ($voiceName === '') {
        return '';
    }

    foreach (getLocalVoices() as $localVoice) {
        if ($localVoice === $voiceName) {
            return $localVoice;
        }
    }

    return '';
}

function getClonedVoices($provider) {
    global $db;
    if (!isset($GLOBALS["db"]) || !$GLOBALS["db"]) {
        return [];
    }
    if ($provider === 'cartesia') {
        chimTtsStudioApplyConnectorGlobals('cartesia');
        require_once(__DIR__ . '/../tts/tts-cartesia.php');
        return getCartesiaCachedVoicesMap(true);
    }
    if ($provider === 'inworld') {
        chimTtsStudioApplyConnectorGlobals('inworld');
        require_once(__DIR__ . '/../tts/tts-inworld.php');
        return getInworldCachedVoicesMap(true);
    }
    $db = $GLOBALS["db"];
    $prefix = $provider . '_voice_id_';
    $prefixEscaped = $db->escape($prefix);
    $rows = $db->fetchAll("SELECT id, value FROM conf_opts WHERE id LIKE '{$prefixEscaped}%'");
    $cloned = [];
    foreach ($rows as $row) {
        $voiceName = str_replace($prefix, '', $row['id']);
        $cloned[$voiceName] = $row['value'];
    }
    return $cloned;
}

function isProviderConfigured($provider) {
    global $db;
    if (!isset($GLOBALS["db"]) || !$GLOBALS["db"]) {
        return false;
    }
    $db = $GLOBALS["db"];
    $providerLower = strtolower($provider);
    $row = $db->fetchOne("SELECT api_key FROM core_api_badge WHERE lower(label)='{$providerLower}' LIMIT 1");
    return (is_array($row) && !empty($row['api_key']));
}

function getCartesiaConfigurationStatus(): array
{
    $row = chimTtsStudioResolveConnectorRow('cartesia');
    $hasConnector = is_array($row);

    $hasApiCredential = false;
    $apiBadgeId = intval($row['api_badge_id'] ?? 0);
    if ($apiBadgeId > 0 && isset($GLOBALS["db"]) && $GLOBALS["db"]) {
        $badgeRow = $GLOBALS["db"]->fetchOne("SELECT api_key FROM core_api_badge WHERE id = {$apiBadgeId} LIMIT 1");
        $hasApiCredential = is_array($badgeRow) && !empty($badgeRow['api_key']);
    }
    if (!$hasApiCredential) {
        $hasApiCredential = isProviderConfigured('cartesia');
    }

    if ($hasConnector && $hasApiCredential) {
        return [
            'configured' => true,
            'title' => 'Cartesia connector and API badge are configured',
            'message' => '',
        ];
    }

    $missingParts = [];
    if (!$hasConnector) {
        $missingParts[] = 'connector';
    }
    if (!$hasApiCredential) {
        $missingParts[] = 'API credential';
    }

    $message = '';
    if (!$hasConnector) {
        $message = 'Please configure an active Cartesia connector';
        if (!$hasApiCredential) {
            $message .= ' and API credential';
        }
        $message .= ' before syncing voices.';
    } else {
        $message = 'Please configure your active Cartesia connector API credential before syncing voices.';
    }

    return [
        'configured' => false,
        'title' => 'Missing Cartesia ' . implode(' and ', $missingParts),
        'message' => $message,
    ];
}

function getInworldConfigurationStatus(): array
{
    $row = chimTtsStudioResolveConnectorRow('inworld');
    $metadata = chimTtsStudioResolveConnectorMetadata('inworld');
    $hasConnector = is_array($row);

    $hasApiCredential = false;
    $apiBadgeId = intval($row['api_badge_id'] ?? 0);
    if ($apiBadgeId > 0 && isset($GLOBALS["db"]) && $GLOBALS["db"]) {
        $badgeRow = $GLOBALS["db"]->fetchOne("SELECT api_key FROM core_api_badge WHERE id = {$apiBadgeId} LIMIT 1");
        $hasApiCredential = is_array($badgeRow) && !empty($badgeRow['api_key']);
    }
    if (!$hasApiCredential) {
        $hasApiCredential = isProviderConfigured('inworld');
    }

    $workspace = trim(strval($metadata['workspace'] ?? ($GLOBALS["TTS"]["INWORLD"]["workspace"] ?? '')));
    $hasWorkspace = ($workspace !== '');

    if ($hasConnector && $hasApiCredential && $hasWorkspace) {
        return [
            'configured' => true,
            'title' => 'Inworld connector, API badge, and workspace are configured',
            'message' => '',
        ];
    }

    $missingParts = [];
    if (!$hasConnector) {
        $missingParts[] = 'connector';
    }
    if (!$hasApiCredential) {
        $missingParts[] = 'API credential';
    }
    if (!$hasWorkspace) {
        $missingParts[] = 'workspace';
    }

    $message = '';
    if (!$hasConnector) {
        $message = 'Please configure an active Inworld connector';
        if (!$hasApiCredential) {
            $message .= ' and API credential';
        }
        if (!$hasWorkspace) {
            $message .= ($hasApiCredential ? ' and workspace' : ' and workspace');
        }
        $message .= ' before syncing voices.';
    } else {
        $details = [];
        if (!$hasApiCredential) {
            $details[] = 'API credential';
        }
        if (!$hasWorkspace) {
            $details[] = 'workspace';
        }
        $message = 'Please configure your active Inworld connector ' . implode(' and ', $details) . ' before syncing voices.';
    }

    return [
        'configured' => false,
        'title' => 'Missing Inworld ' . implode(' and ', $missingParts),
        'message' => $message,
    ];
}

function chimTtsStudioProbeJson(string $url, string $method = 'GET', ?array $payload = null): array
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 4);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);

    $headers = ['accept: application/json'];
    if (strtoupper($method) === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        $body = json_encode($payload ?? []);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        $headers[] = 'content-type: application/json';
    }

    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
    curl_close($ch);

    $decoded = null;
    if ($response !== false) {
        $decoded = json_decode(strval($response), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $decoded = null;
        }
    }

    return [
        'response' => $response,
        'decoded' => $decoded,
        'http_code' => $httpCode,
        'curl_error' => $curlError,
    ];
}

function chimTtsStudioDetectEndpointProvider(string $endpoint): array
{
    $endpoint = normalize_endpoint_url(trim($endpoint));
    if ($endpoint === '') {
        return [
            'reachable' => false,
            'provider' => '',
            'reason' => 'No endpoint configured',
        ];
    }

    static $cache = [];
    if (isset($cache[$endpoint])) {
        return $cache[$endpoint];
    }

    $providerProbe = chimTtsStudioProbeJson($endpoint . '/provider_info');
    $providerDecoded = $providerProbe['decoded'];
    $providerIdentity = is_array($providerDecoded)
        ? chimTtsStudioNormalizeProviderIdentity(strval($providerDecoded['provider'] ?? ''))
        : '';
    if ($providerProbe['response'] !== false
        && intval($providerProbe['http_code']) >= 200
        && intval($providerProbe['http_code']) < 300
        && $providerIdentity !== '') {
        return $cache[$endpoint] = [
            'reachable' => true,
            'provider' => $providerIdentity,
            'reason' => 'Provider identity endpoint matched ' . $providerIdentity,
        ];
    }

    $openApiProbe = chimTtsStudioProbeJson($endpoint . '/openapi.json');
    $openApiIdentity = is_array($openApiProbe['decoded'] ?? null)
        ? chimTtsStudioProviderFromOpenApi($openApiProbe['decoded'])
        : '';
    if (chimTtsStudioProbeSucceeded($openApiProbe) && $openApiIdentity !== '') {
        return $cache[$endpoint] = [
            'reachable' => true,
            'provider' => $openApiIdentity,
            'reason' => 'Legacy OpenAPI fingerprint matched ' . $openApiIdentity,
        ];
    }

    if (strpos($endpoint, ':8086') !== false || strpos($endpoint, '/v1/audio/speech') !== false) {
        $audioCppBase = chimTtsStudioAudioCppBaseEndpoint($endpoint);
        $healthProbe = chimTtsStudioProbeJson($audioCppBase . '/health');
        $modelsProbe = chimTtsStudioProbeJson($audioCppBase . '/v1/models');
        if ($healthProbe['response'] !== false
            && intval($healthProbe['http_code']) >= 200
            && intval($healthProbe['http_code']) < 300
            && $modelsProbe['response'] !== false
            && intval($modelsProbe['http_code']) >= 200
            && intval($modelsProbe['http_code']) < 300) {
            return $cache[$endpoint] = [
                'reachable' => true,
                'provider' => 'pockettts',
                'reason' => 'audio.cpp PocketTTS health and models endpoints responded',
            ];
        }
    }

    $speakersProbe = chimTtsStudioProbeJson($endpoint . '/speakers_list');
    $speakersReachable = ($speakersProbe['response'] !== false
        && intval($speakersProbe['http_code']) >= 200
        && intval($speakersProbe['http_code']) < 300
        && is_array($speakersProbe['decoded']));

    if (!$speakersReachable) {
        $reason = $speakersProbe['curl_error'] !== ''
            ? $speakersProbe['curl_error']
            : ('HTTP ' . (intval($speakersProbe['http_code']) > 0 ? strval($speakersProbe['http_code']) : 'no response'));
        return $cache[$endpoint] = [
            'reachable' => false,
            'provider' => '',
            'reason' => $reason,
        ];
    }

    $normalizedSpeakers = normalizeSpeakersList($speakersProbe['decoded']);
    if (empty($normalizedSpeakers) && $speakersProbe['decoded'] !== [] && !isset($speakersProbe['decoded']['speakers'])) {
        return $cache[$endpoint] = [
            'reachable' => true,
            'provider' => '',
            'reason' => 'Endpoint responded, but /speakers_list was not XTTS-compatible',
        ];
    }

    $chatterboxProbe = chimTtsStudioProbeJson($endpoint . '/speakers_list_extended');
    $chatterboxDecoded = $chatterboxProbe['decoded'];
    if ($chatterboxProbe['response'] !== false
        && intval($chatterboxProbe['http_code']) >= 200
        && intval($chatterboxProbe['http_code']) < 300
        && is_array($chatterboxDecoded)
        && isset($chatterboxDecoded['speakers'])
        && isset($chatterboxDecoded['count'])
        && is_array($chatterboxDecoded['speakers'])) {
        return $cache[$endpoint] = [
            'reachable' => true,
            'provider' => 'chatterbox',
            'reason' => 'Chatterbox fingerprint matched',
        ];
    }

    $xttsProbe = chimTtsStudioProbeJson($endpoint . '/languages');
    $xttsDecoded = $xttsProbe['decoded'];
    if ($xttsProbe['response'] !== false
        && intval($xttsProbe['http_code']) >= 200
        && intval($xttsProbe['http_code']) < 300
        && is_array($xttsDecoded)
        && isset($xttsDecoded['languages'])
        && is_array($xttsDecoded['languages'])) {
        return $cache[$endpoint] = [
            'reachable' => true,
            'provider' => 'xtts-fastapi',
            'reason' => 'XTTS fingerprint matched',
        ];
    }

    return $cache[$endpoint] = [
        'reachable' => true,
        'provider' => 'pockettts',
        'reason' => 'Generic XTTS-compatible API responded, but Chatterbox and XTTS fingerprints were absent',
    ];
}

function chimTtsStudioProbeEndpointStatus(string $driver, string $endpoint): array
{
    $endpoint = normalize_endpoint_url(trim($endpoint));
    if ($endpoint === '') {
        return [
            'label' => 'Not Connected',
            'class' => 'disconnected',
            'title' => 'No endpoint configured',
        ];
    }

    if ($driver === 'pockettts') {
        $runtime = chimTtsStudioDetectPocketTtsRuntime($endpoint);
        $modeLabel = ($runtime['mode'] ?? '') === 'audio_cpp' ? 'audio.cpp' : 'standard API';
        if (!empty($runtime['reachable'])) {
            return [
                'label' => 'Connected',
                'class' => 'connected',
                'title' => $endpoint . ' - PocketTTS ' . $modeLabel . ' detected automatically',
            ];
        }

        return [
            'label' => 'Not Connected',
            'class' => 'disconnected',
            'title' => $endpoint . ' - PocketTTS ' . $modeLabel . ' configured; ' . ($runtime['reason'] ?? 'no response'),
        ];
    }

    $detected = chimTtsStudioDetectEndpointProvider($endpoint);
    if (!$detected['reachable']) {
        return [
            'label' => 'Not Connected',
            'class' => 'disconnected',
            'title' => $endpoint . ' - ' . $detected['reason'],
        ];
    }

    if ($detected['provider'] === '') {
        return [
            'label' => 'Not Connected',
            'class' => 'disconnected',
            'title' => $endpoint . ' - ' . $detected['reason'],
        ];
    }

    if ($detected['provider'] === $driver) {
        return [
            'label' => 'Connected',
            'class' => 'connected',
            'title' => $endpoint . ' - Active engine detected: ' . $detected['provider'],
        ];
    }

    return [
        'label' => 'Not Connected',
        'class' => 'disconnected',
        'title' => $endpoint . ' - Active engine detected: ' . $detected['provider'],
    ];
}

/**
 * Extract .wav files from ZIP archive
 * @param string $zipPath Path to ZIP file
 * @param string $destDir Destination directory for extracted files
 * @return array Array of extracted .wav filenames or error array
 */
function extractWavFromZip($zipPath, $destDir) {
    $result = [
        'success' => false,
        'files' => [],
        'errors' => []
    ];

    if (!class_exists('ZipArchive')) {
        $result['errors'][] = 'ZipArchive class not available';
        return $result;
    }

    $zip = new ZipArchive();
    $openResult = $zip->open($zipPath);

    if ($openResult !== true) {
        $result['errors'][] = 'Failed to open ZIP file (Error code: ' . $openResult . ')';
        return $result;
    }

    $extractedFiles = [];
    $tempDir = sys_get_temp_dir() . '/tts_batch_' . uniqid();

    if (!mkdir($tempDir, 0777, true)) {
        $zip->close();
        $result['errors'][] = 'Failed to create temporary directory';
        return $result;
    }

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $filename = $zip->getNameIndex($i);
        $fileInfo = pathinfo($filename);

        // Skip directories and non-wav files
        if (substr($filename, -1) === '/' || strtolower($fileInfo['extension'] ?? '') !== 'wav') {
            continue;
        }

        // Extract to temp location
        $tempFile = $tempDir . '/' . basename($filename);
        if (copy('zip://' . $zipPath . '#' . $filename, $tempFile)) {
            // Validate it's a real WAV file
            $mimeType = mime_content_type($tempFile);
            if ($mimeType === 'audio/wav' || $mimeType === 'audio/x-wav') {
                // Move to destination
                $destFile = $destDir . basename($filename);
                if (rename($tempFile, $destFile)) {
                    $extractedFiles[] = basename($filename);
                } else {
                    $result['errors'][] = 'Failed to move ' . basename($filename) . ' to destination';
                }
            } else {
                $result['errors'][] = basename($filename) . ' is not a valid WAV file';
                @unlink($tempFile);
            }
        } else {
            $result['errors'][] = 'Failed to extract ' . basename($filename);
        }
    }

    $zip->close();
    @rmdir($tempDir);

    $result['success'] = count($extractedFiles) > 0;
    $result['files'] = $extractedFiles;

    return $result;
}

$xttsStudioEndpoints = [
    'xtts-fastapi' => chimTtsStudioResolveEndpointForDriver('xtts-fastapi'),
    'chatterbox' => chimTtsStudioResolveEndpointForDriver('chatterbox'),
    'pockettts' => chimTtsStudioResolveEndpointForDriver('pockettts'),
    'omnivoice' => chimTtsStudioResolveEndpointForDriver('omnivoice'),
];
$pocketTtsRuntime = chimTtsStudioDetectPocketTtsRuntime($xttsStudioEndpoints['pockettts'] ?? '');
$pocketTtsModeLabel = ($pocketTtsRuntime['mode'] ?? '') === 'audio_cpp' ? 'audio.cpp' : 'Standard API';

$ttsStudioProviderStatuses = [
    'xtts' => chimTtsStudioProbeEndpointStatus('xtts-fastapi', $xttsStudioEndpoints['xtts-fastapi'] ?? ''),
    'chatterbox' => chimTtsStudioProbeEndpointStatus('chatterbox', $xttsStudioEndpoints['chatterbox'] ?? ''),
    'pockettts' => chimTtsStudioProbeEndpointStatus('pockettts', $xttsStudioEndpoints['pockettts'] ?? ''),
    'omnivoice' => chimTtsStudioProbeEndpointStatus('omnivoice', $xttsStudioEndpoints['omnivoice'] ?? ''),
    'cartesia' => (function () {
        $status = getCartesiaConfigurationStatus();
        return $status['configured']
            ? ['label' => 'Configured', 'class' => 'configured', 'title' => $status['title']]
            : ['label' => 'Not Configured', 'class' => 'unconfigured', 'title' => $status['title']];
    })(),
    'inworld' => (function () {
        $status = getInworldConfigurationStatus();
        return $status['configured']
            ? ['label' => 'Configured', 'class' => 'configured', 'title' => $status['title']]
            : ['label' => 'Not Configured', 'class' => 'unconfigured', 'title' => $status['title']];
    })(),
];

// Initialize message variables
$message = '';
$speakersMessage = '';
$cartesiaMessage = '';
$inworldMessage = '';
$ttsFallbackManager = new TTSFallback();
$ttsPronunciationManager = new TTSPronunciationDictionary();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pronunciationAction = strval($_POST['action'] ?? '');
    if ($pronunciationAction === 'save_tts_pronunciation') {
        $pronunciationId = intval($_POST['id'] ?? 0);
        $saved = $ttsPronunciationManager->saveCustom(
            $pronunciationId > 0 ? $pronunciationId : null,
            strval($_POST['source_text'] ?? ''),
            strval($_POST['spoken_text'] ?? ''),
            strval($_POST['oghma_tags'] ?? ''),
            isset($_POST['enabled'])
        );
        $message .= $saved
            ? "<p style='color:#4caf50;'><strong>Pronunciation saved.</strong></p>"
            : "<p style='color:#f44336;'><strong>Pronunciation could not be saved. Check the fields for blanks or duplicates, apply database updates, and try again.</strong></p>";
    } elseif ($pronunciationAction === 'toggle_tts_pronunciation') {
        $updated = $ttsPronunciationManager->setEnabled(
            intval($_POST['id'] ?? 0),
            chimTtsPronunciationBoolean($_POST['enabled'] ?? false)
        );
        $message .= $updated
            ? "<p style='color:#4caf50;'><strong>Pronunciation status updated.</strong></p>"
            : "<p style='color:#f44336;'><strong>Pronunciation status could not be updated.</strong></p>";
    } elseif ($pronunciationAction === 'delete_tts_pronunciation') {
        $deleted = $ttsPronunciationManager->deleteCustom(intval($_POST['id'] ?? 0));
        $message .= $deleted
            ? "<p style='color:#4caf50;'><strong>Custom pronunciation deleted.</strong></p>"
            : "<p style='color:#f44336;'><strong>Custom pronunciation could not be deleted.</strong></p>";
    }

    if (($_POST['action'] ?? '') === 'save_tts_fallbacks') {
        $submittedFallbacks = $_POST['fallbacks'] ?? [];
        if (!is_array($submittedFallbacks)) {
            $message .= "<p style='color:#f44336;'><strong>Invalid fallback voice settings.</strong></p>";
        } elseif ($ttsFallbackManager->saveMatrix($submittedFallbacks)) {
            $message .= "<p style='color:#4caf50;'><strong>Fallback voices saved.</strong></p>";
        } else {
            $message .= "<p style='color:#f44336;'><strong>Fallback voices could not be saved. Apply database updates and try again.</strong></p>";
        }
    }

    // Cartesia sync handler (all missing)
    if (isset($_POST['action']) && $_POST['action'] === 'sync_cartesia') {
        $cartesiaStatus = getCartesiaConfigurationStatus();
        if (!$cartesiaStatus['configured']) {
            $cartesiaMessage .= "<p style='color:red;'><strong>" . htmlspecialchars($cartesiaStatus['message']) . "</strong></p>";
        } else {
            chimTtsStudioApplyConnectorGlobals('cartesia');
            $localVoices = getLocalVoices();
            $clonedVoices = getClonedVoices('cartesia');
            $syncedCount = 0;
            $errorCount = 0;
            $rateLimitCount = 0;
            $firstVoice = true;

            foreach ($localVoices as $voice) {
                if (!isset($clonedVoices[$voice])) {
                    $voiceSamplePath = __DIR__ . '/../data/voices/' . $voice . '.wav';
                    if (file_exists($voiceSamplePath)) {
                        // Add delay between requests to avoid rate limiting (2 seconds)
                        if (!$firstVoice) {
                            sleep(2);
                        }
                        $firstVoice = false;

                        $result = getOrCreateCartesiaVoice($voice);
                        if ($result !== false) {
                            $syncedCount++;
                        } else {
                            $errorCount++;
                            $detailedError = function_exists('getCartesiaLastError') ? getCartesiaLastError() : '';
                            $errorMsg = error_get_last();
                            if ($errorMsg && (strpos($errorMsg['message'], '429') !== false || strpos($errorMsg['message'], 'Too Many Requests') !== false)) {
                                $rateLimitCount++;
                                $cartesiaMessage .= "<p style='color:orange;'>Rate limit hit while generating voice: {$voice}. Please wait a moment and try syncing remaining voices.</p>";
                                break;
                            } else {
                                $suffix = $detailedError !== '' ? ' ' . htmlspecialchars($detailedError) : '';
                                $cartesiaMessage .= "<p>Error generating voice: {$voice}.{$suffix}</p>";
                            }
                        }
                    }
                }
            }

            if ($syncedCount > 0) {
                $cartesiaMessage .= "<p style='color:rgb(247, 231, 16);'><strong>Successfully synced {$syncedCount} voice(s) to Cartesia.</strong></p>";
            }
            if ($rateLimitCount > 0) {
                $cartesiaMessage .= "<p style='color:orange;'><strong>Hit rate limit after {$syncedCount} voices. Please wait a few minutes before syncing remaining voices.</strong></p>";
            }
            if ($errorCount > 0 && $rateLimitCount === 0) {
                $cartesiaMessage .= "<p style='color:red;'><strong>Failed to sync {$errorCount} voice(s).</strong></p>";
            }
            if ($syncedCount === 0 && $errorCount === 0 && $rateLimitCount === 0) {
                $cartesiaMessage .= "<p>All voices are already synced to Cartesia.</p>";
            }
        }
    }

    // Cartesia single voice sync handler
    if (isset($_POST['action']) && $_POST['action'] === 'sync_cartesia_single' && isset($_POST['voice'])) {
        $cartesiaStatus = getCartesiaConfigurationStatus();
        if (!$cartesiaStatus['configured']) {
            $cartesiaMessage .= "<p style='color:red;'><strong>" . htmlspecialchars($cartesiaStatus['message']) . "</strong></p>";
        } else {
            chimTtsStudioApplyConnectorGlobals('cartesia');
            $voice = resolveLocalVoiceName($_POST['voice']);
            $voiceSamplePath = __DIR__ . '/../data/voices/' . $voice . '.wav';
            if ($voice !== '' && file_exists($voiceSamplePath)) {
                $result = getOrCreateCartesiaVoice($voice);
                if ($result !== false && !empty($result)) {
                    // Redirect to refresh the page and show updated status
                    header('Location: ' . $webRoot . '/ui/xtts_clone.php?tab=cartesia&synced=' . urlencode($voice));
                    exit;
                } else {
                    $detailedError = function_exists('getCartesiaLastError') ? getCartesiaLastError() : '';
                    $suffix = $detailedError !== '' ? ' ' . htmlspecialchars($detailedError) : ' Please check API configuration and logs.';
                    $cartesiaMessage .= "<p style='color:red;'><strong>Failed to generate voice '{$voice}' for Cartesia.{$suffix}</strong></p>";
                }
            } else {
                $cartesiaMessage .= "<p style='color:red;'><strong>Voice file not found.</strong></p>";
            }
        }
    }

    // Cartesia single voice unsync handler
    if (isset($_POST['action']) && $_POST['action'] === 'unsync_cartesia_single' && isset($_POST['voice'])) {
        chimTtsStudioApplyConnectorGlobals('cartesia');
        $voice = resolveLocalVoiceName($_POST['voice']);
        if ($voice !== '') {
            deleteCachedCartesiaVoiceId($voice, null, true);
            deleteCartesiaVoiceMetadata($voice);
            $cartesiaMessage .= "<p style='color:rgb(247, 231, 16);'><strong>Unsynced Cartesia voice cache for " . htmlspecialchars($voice) . ".</strong></p>";
        } else {
            $cartesiaMessage .= "<p style='color:red;'><strong>Voice file not found.</strong></p>";
        }
    }

    // Cartesia single voice resync handler
    if (isset($_POST['action']) && $_POST['action'] === 'resync_cartesia_single' && isset($_POST['voice'])) {
        $cartesiaStatus = getCartesiaConfigurationStatus();
        if (!$cartesiaStatus['configured']) {
            $cartesiaMessage .= "<p style='color:red;'><strong>" . htmlspecialchars($cartesiaStatus['message']) . "</strong></p>";
        } else {
            chimTtsStudioApplyConnectorGlobals('cartesia');
            $voice = resolveLocalVoiceName($_POST['voice']);
            $voiceSamplePath = __DIR__ . '/../data/voices/' . $voice . '.wav';
            if ($voice !== '' && file_exists($voiceSamplePath)) {
                $result = rebuildCartesiaVoice($voice);
                if ($result !== false && !empty($result)) {
                    header('Location: ' . $webRoot . '/ui/xtts_clone.php?tab=cartesia&synced=' . urlencode($voice));
                    exit;
                }

                $detailedError = function_exists('getCartesiaLastError') ? getCartesiaLastError() : '';
                $suffix = $detailedError !== '' ? ' ' . htmlspecialchars($detailedError) : ' Please check API configuration and logs.';
                $cartesiaMessage .= "<p style='color:red;'><strong>Failed to resync voice '" . htmlspecialchars($voice) . "' for Cartesia.{$suffix}</strong></p>";
            } else {
                $cartesiaMessage .= "<p style='color:red;'><strong>Voice file not found.</strong></p>";
            }
        }
    }

    if (isset($_POST['action']) && $_POST['action'] === 'delete_cartesia_single' && isset($_POST['voice'])) {
        chimTtsStudioApplyConnectorGlobals('cartesia');
        $voice = resolveLocalVoiceName($_POST['voice']);
        if ($voice !== '' && deleteManagedCartesiaVoice($voice)) {
            $cartesiaMessage .= "<p style='color:rgb(247, 231, 16);'><strong>Deleted managed Cartesia clone for " . htmlspecialchars($voice) . ".</strong></p>";
        } else {
            $error = getCartesiaLastError();
            $cartesiaMessage .= "<p style='color:red;'><strong>" . htmlspecialchars($error !== '' ? $error : 'Could not delete Cartesia voice.') . "</strong></p>";
        }
    }

    // Cartesia clear cache handler
    if (isset($_POST['action']) && $_POST['action'] === 'clear_cartesia_cache') {
        $db = $GLOBALS["db"];
        $legacyPrefixEscaped = $db->escape('cartesia_voice_id_');
        $scopedPrefixEscaped = $db->escape('cartesia_voice_scope_');
        $metadataPrefixEscaped = $db->escape('cartesia_voice_meta_');
        $db->execQuery("DELETE FROM conf_opts WHERE id LIKE '{$legacyPrefixEscaped}%' OR id LIKE '{$scopedPrefixEscaped}%' OR id LIKE '{$metadataPrefixEscaped}%'");
        $cartesiaMessage .= "<p style='color:rgb(247, 231, 16);'><strong>Cartesia voice cache cleared.</strong></p>";
    }

    // Cartesia upload handler
    if (isset($_POST["submit_cartesia"])) {
        $saveDir = __DIR__ . '/../data/voices/';
        $filesToProcess = [];

        // Ensure the directory exists
        if (!is_dir($saveDir)) {
            mkdir($saveDir, 0777, true);
        }

        $total = count($_FILES['file']['name']);

        for ($i = 0; $i < $total; $i++) {
            if ($_FILES['file']['error'][$i] !== UPLOAD_ERR_OK) {
                $cartesiaMessage .= '<p style="color:red;">Error: File upload error code ' . $_FILES['file']['error'][$i] . '</p>';
                continue;
            }

            $fileTmpPath = $_FILES["file"]["tmp_name"][$i];
            $fileName = $_FILES["file"]["name"][$i];
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            // Handle ZIP files
            if ($fileExtension === 'zip') {
                $zipResult = extractWavFromZip($fileTmpPath, $saveDir);

                if ($zipResult['success']) {
                    foreach ($zipResult['files'] as $wavFile) {
                        $filesToProcess[] = $wavFile;
                    }
                    $cartesiaMessage .= "<p style='color:rgb(247, 231, 16);'>Extracted " . count($zipResult['files']) . " .wav file(s) from " . htmlspecialchars($fileName) . "</p>";
                }

                if (!empty($zipResult['errors'])) {
                    foreach ($zipResult['errors'] as $error) {
                        $cartesiaMessage .= "<p style='color:orange;'>Warning: " . htmlspecialchars($error) . "</p>";
                    }
                }
                continue;
            }

            // Handle individual WAV files
            $fileType = mime_content_type($fileTmpPath);

            if ($fileExtension !== 'wav' || ($fileType !== 'audio/wav' && $fileType !== 'audio/x-wav')) {
                $cartesiaMessage .= "<p style='color:red;'>Error: Please upload a valid .wav file. (" . htmlspecialchars($fileName) . ")</p>";
            } else {
                $destinationPath = $saveDir . $fileName;
                if (move_uploaded_file($fileTmpPath, $destinationPath)) {
                    $filesToProcess[] = $fileName;
                } else {
                    $cartesiaMessage .= "<p style='color:red;'>Error: File could not be saved to $destinationPath.</p>";
                }
            }
        }

        // Note: Files are saved but not auto-generated
        // JavaScript will handle batch processing with rate limiting
        if (count($filesToProcess) > 0) {
            $cartesiaMessage .= "<p style='color:rgb(247, 231, 16);'><strong>Uploaded " . count($filesToProcess) . " voice file(s). Use the batch upload feature to generate voices with rate limiting.</strong></p>";
        }
    }

    // Inworld sync handler (all missing)
    if (isset($_POST['action']) && $_POST['action'] === 'sync_inworld') {
        $inworldStatus = getInworldConfigurationStatus();
        if (!$inworldStatus['configured']) {
            $inworldMessage .= "<p style='color:red;'><strong>" . htmlspecialchars($inworldStatus['message']) . "</strong></p>";
        } else {
            chimTtsStudioApplyConnectorGlobals('inworld');
        $localVoices = getLocalVoices();
        $clonedVoices = getClonedVoices('inworld');
        $syncedCount = 0;
        $errorCount = 0;
        $rateLimitCount = 0;
        $firstVoice = true;

        foreach ($localVoices as $voice) {
            if (!isset($clonedVoices[$voice])) {
                $voiceSamplePath = __DIR__ . '/../data/voices/' . $voice . '.wav';
                if (file_exists($voiceSamplePath)) {
                    // Add delay between requests to avoid rate limiting (3 seconds for Inworld)
                    if (!$firstVoice) {
                        sleep(3);
                    }
                    $firstVoice = false;

                    $result = getOrCreateInworldVoice($voice);
                    if ($result !== false) {
                        $syncedCount++;
                    } else {
                        $errorCount++;
                        $detailedError = function_exists('getInworldLastError') ? getInworldLastError() : '';
                        // Check if it's a rate limit error
                        $errorMsg = error_get_last();
                        if ($errorMsg && (strpos($errorMsg['message'], '429') !== false || strpos($errorMsg['message'], 'Too Many Requests') !== false)) {
                            $rateLimitCount++;
                            $inworldMessage .= "<p style='color:orange;'>Rate limit hit while generating voice: {$voice}. Please wait a moment and try syncing remaining voices.</p>";
                            // Stop syncing if we hit rate limit
                            break;
                        } else {
                            $suffix = $detailedError !== '' ? ' ' . htmlspecialchars($detailedError) : '';
                            $inworldMessage .= "<p>Error generating voice: {$voice}.{$suffix}</p>";
                        }
                    }
                }
            }
        }

        if ($syncedCount > 0) {
            $inworldMessage .= "<p style='color:rgb(247, 231, 16);'><strong>Successfully synced {$syncedCount} voice(s) to Inworld.</strong></p>";
        }
        if ($rateLimitCount > 0) {
            $inworldMessage .= "<p style='color:orange;'><strong>Hit rate limit after {$syncedCount} voices. Please wait a few minutes before syncing remaining voices.</strong></p>";
        }
        if ($errorCount > 0 && $rateLimitCount === 0) {
            $inworldMessage .= "<p style='color:red;'><strong>Failed to sync {$errorCount} voice(s).</strong></p>";
        }
        if ($syncedCount === 0 && $errorCount === 0 && $rateLimitCount === 0) {
            $inworldMessage .= "<p>All voices are already synced to Inworld.</p>";
        }
        }
    }

    // Inworld single voice sync handler
    if (isset($_POST['action']) && $_POST['action'] === 'sync_inworld_single' && isset($_POST['voice'])) {
        $inworldStatus = getInworldConfigurationStatus();
        if (!$inworldStatus['configured']) {
            $inworldMessage .= "<p style='color:red;'><strong>" . htmlspecialchars($inworldStatus['message']) . "</strong></p>";
        } else {
            chimTtsStudioApplyConnectorGlobals('inworld');
            $voice = resolveLocalVoiceName($_POST['voice']);
            $voiceSamplePath = __DIR__ . '/../data/voices/' . $voice . '.wav';
            if ($voice !== '' && file_exists($voiceSamplePath)) {
                $result = getOrCreateInworldVoice($voice);
                if ($result !== false && !empty($result)) {
                    // Redirect to refresh the page and show updated status
                    header('Location: ' . $webRoot . '/ui/xtts_clone.php?tab=inworld&synced=' . urlencode($voice));
                    exit;
                } else {
                    $detailedError = function_exists('getInworldLastError') ? getInworldLastError() : '';
                    $suffix = $detailedError !== '' ? ' ' . htmlspecialchars($detailedError) : ' Please check API configuration and logs.';
                    $inworldMessage .= "<p style='color:red;'><strong>Failed to generate voice '{$voice}' for Inworld.{$suffix}</strong></p>";
                }
            } else {
                $inworldMessage .= "<p style='color:red;'><strong>Voice file not found.</strong></p>";
            }
        }
    }

    // Inworld single voice unsync handler
    if (isset($_POST['action']) && $_POST['action'] === 'unsync_inworld_single' && isset($_POST['voice'])) {
        chimTtsStudioApplyConnectorGlobals('inworld');
        $voice = resolveLocalVoiceName($_POST['voice']);
        if ($voice !== '') {
            deleteCachedInworldVoiceId($voice, null, true);
            deleteInworldVoiceMetadata($voice);
            $inworldMessage .= "<p style='color:rgb(247, 231, 16);'><strong>Unsynced Inworld voice cache for " . htmlspecialchars($voice) . ".</strong></p>";
        } else {
            $inworldMessage .= "<p style='color:red;'><strong>Voice file not found.</strong></p>";
        }
    }

    // Inworld single voice resync handler
    if (isset($_POST['action']) && $_POST['action'] === 'resync_inworld_single' && isset($_POST['voice'])) {
        $inworldStatus = getInworldConfigurationStatus();
        if (!$inworldStatus['configured']) {
            $inworldMessage .= "<p style='color:red;'><strong>" . htmlspecialchars($inworldStatus['message']) . "</strong></p>";
        } else {
            chimTtsStudioApplyConnectorGlobals('inworld');
            $voice = resolveLocalVoiceName($_POST['voice']);
            $voiceSamplePath = __DIR__ . '/../data/voices/' . $voice . '.wav';
            if ($voice !== '' && file_exists($voiceSamplePath)) {
                $result = rebuildInworldVoice($voice);
                if ($result !== false && !empty($result)) {
                    header('Location: ' . $webRoot . '/ui/xtts_clone.php?tab=inworld&synced=' . urlencode($voice));
                    exit;
                }

                $detailedError = function_exists('getInworldLastError') ? getInworldLastError() : '';
                $suffix = $detailedError !== '' ? ' ' . htmlspecialchars($detailedError) : ' Please check API configuration and logs.';
                $inworldMessage .= "<p style='color:red;'><strong>Failed to resync voice '" . htmlspecialchars($voice) . "' for Inworld.{$suffix}</strong></p>";
            } else {
                $inworldMessage .= "<p style='color:red;'><strong>Voice file not found.</strong></p>";
            }
        }
    }

    if (isset($_POST['action']) && $_POST['action'] === 'delete_inworld_single' && isset($_POST['voice'])) {
        chimTtsStudioApplyConnectorGlobals('inworld');
        $voice = resolveLocalVoiceName($_POST['voice']);
        if ($voice !== '' && deleteManagedInworldVoice($voice)) {
            $inworldMessage .= "<p style='color:rgb(247, 231, 16);'><strong>Deleted managed Inworld clone for " . htmlspecialchars($voice) . ".</strong></p>";
        } else {
            $error = getInworldLastError();
            $inworldMessage .= "<p style='color:red;'><strong>" . htmlspecialchars($error !== '' ? $error : 'Could not delete Inworld voice.') . "</strong></p>";
        }
    }

    // Inworld clear cache handler
    if (isset($_POST['action']) && $_POST['action'] === 'clear_inworld_cache') {
        $db = $GLOBALS["db"];
        $legacyPrefixEscaped = $db->escape('inworld_voice_id_');
        $scopedPrefixEscaped = $db->escape('inworld_voice_scope_');
        $metadataPrefixEscaped = $db->escape('inworld_voice_meta_');
        $db->execQuery("DELETE FROM conf_opts WHERE id LIKE '{$legacyPrefixEscaped}%' OR id LIKE '{$scopedPrefixEscaped}%' OR id LIKE '{$metadataPrefixEscaped}%'");
        $inworldMessage .= "<p style='color:rgb(247, 231, 16);'><strong>Inworld voice cache cleared.</strong></p>";
    }

    // Inworld upload handler
    if (isset($_POST["submit_inworld"])) {
        $saveDir = __DIR__ . '/../data/voices/';
        $filesToProcess = [];

        // Ensure the directory exists
        if (!is_dir($saveDir)) {
            mkdir($saveDir, 0777, true);
        }

        $total = count($_FILES['file']['name']);

        for ($i = 0; $i < $total; $i++) {
            if ($_FILES['file']['error'][$i] !== UPLOAD_ERR_OK) {
                $inworldMessage .= '<p style="color:red;">Error: File upload error code ' . $_FILES['file']['error'][$i] . '</p>';
                continue;
            }

            $fileTmpPath = $_FILES["file"]["tmp_name"][$i];
            $fileName = $_FILES["file"]["name"][$i];
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            // Handle ZIP files
            if ($fileExtension === 'zip') {
                $zipResult = extractWavFromZip($fileTmpPath, $saveDir);

                if ($zipResult['success']) {
                    foreach ($zipResult['files'] as $wavFile) {
                        $filesToProcess[] = $wavFile;
                    }
                    $inworldMessage .= "<p style='color:rgb(247, 231, 16);'>Extracted " . count($zipResult['files']) . " .wav file(s) from " . htmlspecialchars($fileName) . "</p>";
                }

                if (!empty($zipResult['errors'])) {
                    foreach ($zipResult['errors'] as $error) {
                        $inworldMessage .= "<p style='color:orange;'>Warning: " . htmlspecialchars($error) . "</p>";
                    }
                }
                continue;
            }

            // Handle individual WAV files
            $fileType = mime_content_type($fileTmpPath);

            if ($fileExtension !== 'wav' || ($fileType !== 'audio/wav' && $fileType !== 'audio/x-wav')) {
                $inworldMessage .= "<p style='color:red;'>Error: Please upload a valid .wav file. (" . htmlspecialchars($fileName) . ")</p>";
            } else {
                $destinationPath = $saveDir . $fileName;
                if (move_uploaded_file($fileTmpPath, $destinationPath)) {
                    $filesToProcess[] = $fileName;
                } else {
                    $inworldMessage .= "<p style='color:red;'>Error: File could not be saved to $destinationPath.</p>";
                }
            }
        }

        // Note: Files are saved but not auto-generated
        // JavaScript will handle batch processing with rate limiting
        if (count($filesToProcess) > 0) {
            $inworldMessage .= "<p style='color:rgb(247, 231, 16);'><strong>Uploaded " . count($filesToProcess) . " voice file(s). Use the batch upload feature to generate voices with rate limiting.</strong></p>";
        }
    }


    // Remove only the provider-side copy. The local data/voices WAV remains available for re-upload.
    if (isset($_POST['action'], $_POST['voice']) &&
        in_array($_POST['action'], ['delete_xtts_single', 'delete_chatterbox_single', 'delete_pockettts_single', 'delete_omnivoice_single'], true)) {
        $deleteActionTabs = [
            'delete_xtts_single' => 'xtts',
            'delete_chatterbox_single' => 'chatterbox',
            'delete_pockettts_single' => 'pockettts',
            'delete_omnivoice_single' => 'omnivoice',
        ];
        $redirectTab = $deleteActionTabs[$_POST['action']];
        $driver = chimTtsStudioTabToDriver($redirectTab);
        $voice = trim(strval($_POST['voice']));
        $language = $driver === 'omnivoice'
            ? chimTtsStudioResolveOmniVoiceLanguage($_POST['language'] ?? ($_GET['language'] ?? ''))
            : '';
        $deleteResult = chimTtsStudioDeleteVoice($driver, $voice, $language);

        if ($deleteResult['success']) {
            chimTtsStudioStoreSpeakersList(
                $driver,
                chimTtsStudioFetchSpeakersList($driver, $language),
                $language
            );
            $redirect = $webRoot . '/ui/xtts_clone.php?tab=' . rawurlencode($redirectTab) . '&deleted=' . rawurlencode($voice);
            if ($language !== '') {
                $redirect .= '&language=' . rawurlencode($language);
            }
            header('Location: ' . $redirect);
            exit;
        }

        $message .= '<p style="color:red;">Could not remove voice from provider: '
            . htmlspecialchars($deleteResult['message']) . '</p>';
    }

    // XTTS/Chatterbox/PocketTTS/OmniVoice single voice sync handler
    if (isset($_POST['action']) && isset($_POST['voice']) &&
        in_array($_POST['action'], ['sync_xtts_single', 'sync_chatterbox_single', 'sync_pockettts_single', 'sync_omnivoice_single'])) {
        $voice = $_POST['voice'];
        // Detect which tab the request came from
        $redirectTab = isset($_GET['tab']) && in_array($_GET['tab'], ['xtts', 'chatterbox', 'pockettts', 'omnivoice'], true) ? $_GET['tab'] : 'xtts';

        // Determine which endpoint to use based on tab
        $driver = chimTtsStudioTabToDriver($redirectTab);
        $endpoint = chimTtsStudioResolveEndpointForDriver($driver);
        $language = $driver === 'omnivoice'
            ? chimTtsStudioResolveOmniVoiceLanguage($_POST['language'] ?? ($_GET['language'] ?? ''))
            : '';
        if ($endpoint === '') {
            $message .= '<p style="color:red;">No endpoint configured for ' . htmlspecialchars($redirectTab) . '.</p>';
        }
        $voiceSamplePath = __DIR__ . '/../data/voices/' . $voice . '.wav';
        if ($endpoint !== '' && file_exists($voiceSamplePath)) {
            if (chimTtsStudioIsAudioCppPocketTts($driver, $endpoint)) {
                chimTtsStudioStoreSpeakersList($driver, chimTtsStudioFetchSpeakersList($driver));
                header('Location: ' . $webRoot . '/ui/xtts_clone.php?tab=' . $redirectTab . '&synced=' . urlencode($voice));
                exit;
            }

            $fileName = $voice . '.wav';
            $fileType = mime_content_type($voiceSamplePath);

            // Prepare the cURL request using the appropriate endpoint
            $url = $endpoint . '/upload_sample';
            $postFields = chimTtsStudioVoiceUploadPostFields($driver, $voiceSamplePath, $fileType, $fileName, $language);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'accept: application/json',
                'Content-Type: multipart/form-data'
            ));

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            if (curl_errno($ch)) {
                $message .= '<p style="color:red;">Error syncing voice to XTTS/Chatterbox/Pocket-TTS server: ' . curl_error($ch) . '</p>';
            } else {
                $uploadResult = chimTtsStudioVoiceUploadResult($driver, intval($httpCode), strval($response));

                if ($uploadResult['success']) {
                    // Refresh speakers list and redirect to show updated status
                    chimTtsStudioStoreSpeakersList($driver, chimTtsStudioFetchSpeakersList($driver, $language), $language);

                    $languageQuery = $driver === 'omnivoice' ? '&language=' . urlencode($language) : '';
                    header('Location: ' . $webRoot . '/ui/xtts_clone.php?tab=' . $redirectTab . $languageQuery . '&synced=' . urlencode($voice));
                    exit;
                } else {
                    $message .= '<p style="color:red;">Error syncing voice to server (HTTP code ' . $httpCode . '): ' . htmlspecialchars($uploadResult['message']) . '</p>';
                }
            }
            curl_close($ch);
        } else {
            $message .= "<p style='color:red;'><strong>Voice file not found: {$voice}</strong></p>";
        }
    }

    // Get speakers list for POST request - store in session for display
    if (isset($_POST["get_speakers"])) {
        $refreshDriver = chimTtsStudioTabToDriver($activeTab);
        if ($refreshDriver !== '') {
            $refreshLanguage = $refreshDriver === 'omnivoice' ? $activeOmniVoiceLanguage : '';
            chimTtsStudioStoreSpeakersList(
                $refreshDriver,
                chimTtsStudioFetchSpeakersList($refreshDriver, $refreshLanguage),
                $refreshLanguage
            );
        }
    }
    if (isset($_POST["submit"])) {
        $submitDriver = chimTtsStudioTabToDriver($activeTab);
        $submitEndpoint = chimTtsStudioResolveEndpointForDriver($submitDriver);
        $submitProviderLabel = $activeTab === 'chatterbox'
            ? 'Chatterbox'
            : ($activeTab === 'pockettts' ? 'PocketTTS' : ($activeTab === 'omnivoice' ? 'OmniVoice' : 'XTTS'));
        $submitLanguage = $submitDriver === 'omnivoice' ? $activeOmniVoiceLanguage : '';
        if ($submitEndpoint === '') {
            $message .= "<p style='color:red;'>No endpoint configured for {$submitProviderLabel}.</p>";
        }
        $saveDir = __DIR__ . '/../data/voices/';
        $filesToProcess = [];

        // Ensure the directory exists
        if (!is_dir($saveDir)) {
            mkdir($saveDir, 0777, true);
        }

        $total = count($_FILES['file']['name']);

        for ($i = 0; $i < $total; $i++) {
            if ($_FILES['file']['error'][$i] !== UPLOAD_ERR_OK) {
                $message .= '<p style="color:red;">Error: File upload error code ' . $_FILES['file']['error'][$i] . '</p>';
                continue;
            }

            $fileTmpPath = $_FILES["file"]["tmp_name"][$i];
            $fileName = $_FILES["file"]["name"][$i];
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            // Handle ZIP files
            if ($fileExtension === 'zip') {
                $zipResult = extractWavFromZip($fileTmpPath, $saveDir);

                if ($zipResult['success']) {
                    foreach ($zipResult['files'] as $wavFile) {
                        $filesToProcess[] = $wavFile;
                    }
                    $message .= "<p style='color:rgb(247, 231, 16);'>Extracted " . count($zipResult['files']) . " .wav file(s) from " . htmlspecialchars($fileName) . "</p>";
                }

                if (!empty($zipResult['errors'])) {
                    foreach ($zipResult['errors'] as $error) {
                        $message .= "<p style='color:orange;'>Warning: " . htmlspecialchars($error) . "</p>";
                    }
                }
                continue;
            }

            // Handle individual WAV files
            $fileType = mime_content_type($fileTmpPath);

            if ($fileExtension !== 'wav' || ($fileType !== 'audio/wav' && $fileType !== 'audio/x-wav')) {
                $message .= "<p style='color:red;'>Error: Please upload a valid .wav file. (" . htmlspecialchars($fileName) . ")</p>";
            } else {
                $destinationPath = $saveDir . $fileName;
                if (move_uploaded_file($fileTmpPath, $destinationPath)) {
                    $filesToProcess[] = $fileName;
                } else {
                    $message .= "<p style='color:red;'>Error: File could not be saved to $destinationPath.</p>";
                }
            }
        }

        // Process uploaded files - upload to XTTS server
        $uploadedCount = 0;
        if (chimTtsStudioIsAudioCppPocketTts($submitDriver, $submitEndpoint)) {
            $uploadedCount = count($filesToProcess);
        } else {
        foreach (($submitEndpoint !== '' ? $filesToProcess : []) as $fileName) {
            $destinationPath = $saveDir . $fileName;
            $fileType = mime_content_type($destinationPath);

            $url = $submitEndpoint . '/upload_sample';
            $postFields = chimTtsStudioVoiceUploadPostFields($submitDriver, $destinationPath, $fileType, $fileName, $submitLanguage);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'accept: application/json',
                'Content-Type: multipart/form-data'
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            if (curl_errno($ch)) {
                $message .= '<p style="color:red;">cURL Error for ' . htmlspecialchars($fileName) . ': ' . curl_error($ch) . '</p>';
            } else {
                $uploadResult = chimTtsStudioVoiceUploadResult($submitDriver, intval($httpCode), strval($response));
                if ($uploadResult['success']) {
                    $uploadedCount++;
                } else {
                    $message .= '<p style="color:red;">Upload failed for ' . htmlspecialchars($fileName) . ' (HTTP code ' . $httpCode . '): ' . htmlspecialchars($uploadResult['message']) . '</p>';
                }
            }
            curl_close($ch);
        }
        }

        if ($uploadedCount > 0) {
            chimTtsStudioStoreSpeakersList(
                $submitDriver,
                chimTtsStudioFetchSpeakersList($submitDriver, $submitLanguage),
                $submitLanguage
            );
            $verb = chimTtsStudioIsAudioCppPocketTts($submitDriver, $submitEndpoint)
                ? 'saved for'
                : ($submitDriver === 'omnivoice' ? 'imported into' : 'uploaded and cached to');
            $message .= "<p style='color:rgb(247, 231, 16);'><strong>Successfully {$verb} {$submitProviderLabel}: {$uploadedCount} voice(s).</strong></p>";
        }
    } elseif (isset($_POST["upload_all"])) {
        $uploadAllDriver = chimTtsStudioTabToDriver($activeTab);
        $uploadAllEndpoint = chimTtsStudioResolveEndpointForDriver($uploadAllDriver);
        $uploadAllLanguage = $uploadAllDriver === 'omnivoice' ? $activeOmniVoiceLanguage : '';
        if ($uploadAllEndpoint === '') {
            $message .= "<p style='color:red;'>No endpoint configured for this provider.</p>";
        }
        // Upload all .wav files in ../data/voices
        $saveDir = __DIR__ . '/../data/voices/';
        $files = glob($saveDir . '*.wav');
        $numFiles = count($files);
        $numUploaded = 0;

        if (chimTtsStudioIsAudioCppPocketTts($uploadAllDriver, $uploadAllEndpoint)) {
            $numUploaded = $numFiles;
        } else {
        foreach (($uploadAllEndpoint !== '' ? $files : []) as $filePath) {
            $fileName = basename($filePath);
            $fileType = mime_content_type($filePath);

            // Ensure the file is a .wav file
            if ($fileType !== 'audio/wav' && $fileType !== 'audio/x-wav') {
                $message .= "<p>Error: $fileName is not a valid .wav file.</p>";
            } else {
                // Prepare the cURL request
                $url = $uploadAllEndpoint . '/upload_sample';
                $postFields = chimTtsStudioVoiceUploadPostFields($uploadAllDriver, $filePath, $fileType, $fileName, $uploadAllLanguage);

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                    'accept: application/json',
                    'Content-Type: multipart/form-data'
                ));

                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

                if (curl_errno($ch)) {
                    $message .= '<p>cURL Error while uploading ' . htmlspecialchars($fileName) . ': ' . curl_error($ch) . '</p>';
                } else {
                    $uploadResult = chimTtsStudioVoiceUploadResult($uploadAllDriver, intval($httpCode), strval($response));
                    if ($uploadResult['success']) {
                        $numUploaded++;
                    } else {
                        $message .= '<p>Error uploading ' . htmlspecialchars($fileName) . ' (HTTP code ' . $httpCode . '): ' . htmlspecialchars($uploadResult['message']) . '</p>';
                    }
                }
                curl_close($ch);
            }
        }
        }
        chimTtsStudioStoreSpeakersList(
            $uploadAllDriver,
            chimTtsStudioFetchSpeakersList($uploadAllDriver, $uploadAllLanguage),
            $uploadAllLanguage
        );
        $verb = chimTtsStudioIsAudioCppPocketTts($uploadAllDriver, $uploadAllEndpoint)
            ? 'are available locally'
            : ($uploadAllDriver === 'omnivoice' ? 'have been imported' : 'have been synced');
        $message .= "<p><h3 style='color:rgb(247, 231, 16);'>$numUploaded out of $numFiles voice files {$verb}.</h3></p>";
    }
}

$ttsFallbackDefinitions = $ttsFallbackManager->getDefinitions();
$ttsFallbackMatrix = $ttsFallbackManager->getMatrix();
$ttsFallbackVoiceIds = $ttsFallbackManager->getSuggestedVoiceIds();
$ttsPronunciationTags = $ttsPronunciationManager->getAvailableTags();
$ttsPronunciationFilter = strtolower(trim(strval($_GET['oghma_tag'] ?? '')));
if ($ttsPronunciationFilter !== '' && !in_array($ttsPronunciationFilter, $ttsPronunciationTags, true)) {
    $ttsPronunciationFilter = '';
}
$ttsPronunciationRows = $ttsPronunciationManager->getRows($ttsPronunciationFilter);

// Add the JavaScript functions
?>
<script>
    const WEB_ROOT = <?php echo json_encode($webRoot); ?>;

    // Clean up URL after showing success message
    window.addEventListener('DOMContentLoaded', function() {
        const url = new URL(window.location);
        if (url.searchParams.has('synced') || url.searchParams.has('deleted')) {
            // Remove one-time result parameters from the URL without refreshing.
            url.searchParams.delete('synced');
            url.searchParams.delete('deleted');
            window.history.replaceState({}, '', url);
        }
    });

    function normalizeUrl(url) {
        return url.replace(/\/+$/, '');
    }

    function showLoadingMessage(customMessage) {
        const overlay = document.getElementById('loading-overlay');
        const messageEl = document.getElementById('loading-message');
        if (customMessage && messageEl) {
            messageEl.innerHTML = customMessage + ' <br><b>Do not refresh the page<span id="ellipsis"></span></b>';
        }
        overlay.style.display = 'block';
        animateEllipsis();
    }

    function animateEllipsis() {
        var ellipsis = document.getElementById('ellipsis');
        var dots = 0;
        window.ellipsisInterval = setInterval(function() {
            dots = (dots + 1) % 4;
            var dotStr = '';
            for (var i = 0; i < dots; i++) {
                dotStr += '.';
            }
            ellipsis.innerHTML = dotStr;
        }, 500);
    }

    function showToast(message, duration = 1500) {
        const toast = document.getElementById('toast');
        const messageSpan = toast.querySelector('.message');
        messageSpan.textContent = message;
        toast.classList.add('show');

        setTimeout(() => {
            toast.classList.remove('show');
        }, duration);
    }

    function getOmniVoiceLanguage() {
        const selector = document.getElementById('omnivoice-language');
        return selector ? selector.value : '';
    }

    function appendProviderLanguage(url, provider) {
        if (provider !== 'omnivoice') {
            return url;
        }
        const language = getOmniVoiceLanguage();
        if (!language) {
            return url;
        }
        const separator = url.includes('?') ? '&' : '?';
        return url + separator + 'language=' + encodeURIComponent(language);
    }

    function appendProviderLanguageInput(form, provider) {
        if (provider !== 'omnivoice') {
            return;
        }
        const language = getOmniVoiceLanguage();
        if (!language) {
            return;
        }
        const languageInput = document.createElement('input');
        languageInput.type = 'hidden';
        languageInput.name = 'language';
        languageInput.value = language;
        form.appendChild(languageInput);
    }

    function changeOmniVoiceLanguage(language) {
        const currentUrl = new URL(window.location.href);
        currentUrl.searchParams.set('tab', 'omnivoice');
        currentUrl.searchParams.set('language', language);
        window.location.href = currentUrl.toString();
    }

    function copyToClipboard(text) {
        navigator.clipboard.writeText(text).then(function() {
            showToast('Copied to clipboard');
        }).catch(function(err) {
            console.error('Failed to copy text: ', err);
        });
    }

    function testVoice(voiceName) {
        // Determine which tab is active using data attribute
        const activeTab = document.querySelector('.tab-content.active');
        let action = 'test_xtts'; // default

        if (activeTab && activeTab.hasAttribute('data-tab-type')) {
            const tabType = activeTab.getAttribute('data-tab-type');
            if (tabType === 'xtts') action = 'test_xtts';
            else if (tabType === 'chatterbox') action = 'test_chatterbox';
            else if (tabType === 'pockettts') action = 'test_pockettts';
            else if (tabType === 'omnivoice') action = 'test_omnivoice';
        }

        console.log('Testing voice:', voiceName, 'using action:', action);

        const provider = action === 'test_omnivoice' ? 'omnivoice' : '';
        const testUrl = appendProviderLanguage(
            WEB_ROOT + '/ui/xtts_clone.php?action=' + action + '&voice=' + encodeURIComponent(voiceName),
            provider
        );

        fetch(testUrl)
            .then(function(response) {
                if (!response.ok) {
                    return response.json().then(function(data) {
                        throw new Error(data.error || 'Failed to generate test audio');
                    });
                }
                return response.json();
            })
            .then(function(data) {
                if (data.url) {
                    const audio = new Audio(data.url);
                    audio.play().catch(function(err) {
                        console.error('Error playing audio:', err);
                        alert('Error playing audio: ' + err.message);
                    });
                } else {
                    throw new Error(data.error || 'No audio URL returned');
                }
            })
            .catch(function(err) {
                console.error('Error testing voice:', err);
                alert('Error: ' + err.message);
            });
    }

    function switchTab(tabName) {
        const currentUrl = new URL(window.location.href);
        currentUrl.searchParams.set('tab', tabName);
        if (tabName === 'omnivoice') {
            const language = getOmniVoiceLanguage();
            if (language) {
                currentUrl.searchParams.set('language', language);
            }
        }
        window.location.href = currentUrl.toString();
    }

    function testCartesiaVoice(voiceName) {
        const testUrl = WEB_ROOT + '/ui/xtts_clone.php?action=test_cartesia&voice=' + encodeURIComponent(voiceName);

        fetch(testUrl)
            .then(function(response) {
                if (!response.ok) {
                    return response.json().then(function(data) {
                        throw new Error(data.error || 'Failed to generate test audio');
                    });
                }
                return response.json();
            })
            .then(function(data) {
                if (data.url) {
                    const audio = new Audio(data.url);
                    audio.play().catch(function(err) {
                        console.error('Error playing audio:', err);
                        alert('Error playing test audio. Please check the console for details.');
                    });
                } else {
                    throw new Error(data.error || 'No audio URL returned');
                }
            })
            .catch(function(err) {
                console.error('Error testing voice:', err);
                alert('Error: ' + err.message);
            });
    }

    function testInworldVoice(voiceName) {
        const testUrl = WEB_ROOT + '/ui/xtts_clone.php?action=test_inworld&voice=' + encodeURIComponent(voiceName);

        fetch(testUrl)
            .then(function(response) {
                if (!response.ok) {
                    return response.json().then(function(data) {
                        throw new Error(data.error || 'Failed to generate test audio');
                    });
                }
                return response.json();
            })
            .then(function(data) {
                if (data.url) {
                    const audio = new Audio(data.url);
                    audio.play().catch(function(err) {
                        console.error('Error playing audio:', err);
                        alert('Error playing test audio. Please check the console for details.');
                    });
                } else {
                    throw new Error(data.error || 'No audio URL returned');
                }
            })
            .catch(function(err) {
                console.error('Error testing voice:', err);
                alert('Error: ' + err.message);
            });
    }

    function syncSingleVoice(provider, voiceName) {
        const actionMap = {
            xtts: 'Syncing voice to XTTS server',
            chatterbox: 'Syncing voice to Chatterbox server',
            pockettts: 'Syncing voice to PocketTTS server',
            omnivoice: 'Importing voice into OmniVoice',
            cartesia: 'Generating voice for Cartesia',
            inworld: 'Generating voice for Inworld'
        };
        const actionText = actionMap[provider] || ('Processing voice for ' + provider);
        showLoadingMessage(actionText + ', please wait...');
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = WEB_ROOT + '/ui/xtts_clone.php?tab=' + provider;

        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'sync_' + provider + '_single';

        const voiceInput = document.createElement('input');
        voiceInput.type = 'hidden';
        voiceInput.name = 'voice';
        voiceInput.value = voiceName;

        form.appendChild(actionInput);
        form.appendChild(voiceInput);
        appendProviderLanguageInput(form, provider);
        document.body.appendChild(form);
        form.submit();
    }

    function deleteProviderVoice(provider, voiceName) {
        const providerNames = {
            xtts: 'XTTS',
            chatterbox: 'Chatterbox',
            pockettts: 'PocketTTS',
            omnivoice: 'OmniVoice'
        };
        const providerName = providerNames[provider] || provider;
        if (!window.confirm('Remove "' + voiceName + '" from ' + providerName + '? The local WAV will be kept for re-upload.')) {
            return;
        }

        showLoadingMessage('Removing voice from ' + providerName + ', please wait...');
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = WEB_ROOT + '/ui/xtts_clone.php?tab=' + provider;

        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'delete_' + provider + '_single';

        const voiceInput = document.createElement('input');
        voiceInput.type = 'hidden';
        voiceInput.name = 'voice';
        voiceInput.value = voiceName;

        form.appendChild(actionInput);
        form.appendChild(voiceInput);
        appendProviderLanguageInput(form, provider);
        document.body.appendChild(form);
        form.submit();
    }

    function manageCloudVoice(provider, voiceName, mode) {
        const actionTextMap = {
            unsync: 'Forgetting cached voice ID for ' + provider,
            resync: 'Rebuilding voice from the local sample for ' + provider,
            delete: 'Deleting managed cloud voice from ' + provider
        };
        if (mode === 'delete' && !confirm('Delete the managed ' + provider + ' cloud clone for ' + voiceName + '? The local sample will be kept.')) {
            return;
        }
        showLoadingMessage((actionTextMap[mode] || 'Processing voice for ' + provider) + ', please wait...');

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = WEB_ROOT + '/ui/xtts_clone.php?tab=' + provider;

        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = mode + '_' + provider + '_single';

        const voiceInput = document.createElement('input');
        voiceInput.type = 'hidden';
        voiceInput.name = 'voice';
        voiceInput.value = voiceName;

        form.appendChild(actionInput);
        form.appendChild(voiceInput);
        document.body.appendChild(form);
        form.submit();
    }

    // Batch upload functionality
    let batchCancelled = false;
    let currentBatchProvider = null;

    function startBatchUpload(provider, voices) {
        if (!voices || voices.length === 0) {
            alert('No voices to process');
            return;
        }

        batchCancelled = false;
        currentBatchProvider = provider;

        // Show progress UI
        const progressDiv = document.getElementById('batch-progress-' + provider);
        const cancelBtn = document.getElementById('cancel-batch-' + provider);
        const statusDiv = document.getElementById('batch-status-' + provider);

        if (progressDiv) progressDiv.style.display = 'block';
        if (cancelBtn) cancelBtn.style.display = 'inline-block';
        if (statusDiv) statusDiv.innerHTML = '';

        // Update totals
        const totalSpan = document.getElementById('batch-total-' + provider);
        const currentSpan = document.getElementById('batch-current-' + provider);
        if (totalSpan) totalSpan.textContent = voices.length;
        if (currentSpan) currentSpan.textContent = '0';

        // Process voices one by one
        processBatchVoices(provider, voices, 0);
    }

    function processBatchVoices(provider, voices, index) {
        if (batchCancelled || index >= voices.length) {
            if (batchCancelled) {
                addBatchStatus(provider, '❌ Batch upload cancelled', 'orange');
            } else {
                addBatchStatus(provider, '✅ Batch upload complete!', 'rgb(247, 231, 16)');
                // Refresh page after completion to show updated voice list
                setTimeout(() => {
                    window.location.href = appendProviderLanguage(WEB_ROOT + '/ui/xtts_clone.php?tab=' + provider, provider);
                }, 2000);
            }

            const cancelBtn = document.getElementById('cancel-batch-' + provider);
            if (cancelBtn) cancelBtn.style.display = 'none';
            return;
        }

        const voice = voices[index];
        const current = index + 1;

        // Update progress
        updateBatchProgress(provider, current, voices.length);

        // Add processing status
        addBatchStatus(provider, '⏳ Processing: ' + voice, '#4a8ab6');

        // Get delay based on provider
        const delays = {
            'xtts': 0,
            'chatterbox': 0,
            'pockettts': 0,
            'omnivoice': 0,
            'cartesia': 2000,
            'inworld': 3000
        };
        const delay = delays[provider] || 0;

        // Make AJAX request
        const url = appendProviderLanguage(
            WEB_ROOT + '/ui/xtts_clone.php?action=batch_process&provider=' +
                encodeURIComponent(provider) + '&voice=' + encodeURIComponent(voice + '.wav'),
            provider
        );

        fetch(url)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    addBatchStatus(provider, '✓ ' + voice, '#4caf50');
                } else {
                    const errorMsg = data.message || 'Unknown error';
                    addBatchStatus(provider, '✗ ' + voice + ': ' + errorMsg, '#f44336');

                    // If rate limited, stop the batch
                    if (data.rateLimit) {
                        batchCancelled = true;
                        addBatchStatus(provider, '⚠ Rate limit reached. Please wait before continuing.', 'orange');
                        const cancelBtn = document.getElementById('cancel-batch-' + provider);
                        if (cancelBtn) cancelBtn.style.display = 'none';
                        return;
                    }
                }

                // Process next voice after delay
                setTimeout(() => {
                    processBatchVoices(provider, voices, index + 1);
                }, delay);
            })
            .catch(error => {
                addBatchStatus(provider, '✗ ' + voice + ': ' + error.message, '#f44336');

                // Continue with next voice after delay
                setTimeout(() => {
                    processBatchVoices(provider, voices, index + 1);
                }, delay);
            });
    }

    function updateBatchProgress(provider, current, total) {
        const currentSpan = document.getElementById('batch-current-' + provider);
        const progressBar = document.getElementById('batch-progress-bar-' + provider);
        const etaSpan = document.getElementById('batch-eta-' + provider);

        if (currentSpan) currentSpan.textContent = current;

        const percentage = (current / total) * 100;
        if (progressBar) progressBar.style.width = percentage + '%';

        // Calculate ETA
        if (etaSpan && current < total) {
            const delays = {
                'xtts': 0,
                'chatterbox': 0,
                'pockettts': 0,
                'omnivoice': 0,
                'cartesia': 2,
                'inworld': 3
            };
            const avgDelay = delays[provider] || 0;
            const remaining = total - current;
            const etaSeconds = remaining * avgDelay;

            if (etaSeconds > 60) {
                const minutes = Math.ceil(etaSeconds / 60);
                etaSpan.textContent = '(~' + minutes + ' minute' + (minutes > 1 ? 's' : '') + ' remaining)';
            } else if (etaSeconds > 0) {
                etaSpan.textContent = '(~' + etaSeconds + ' seconds remaining)';
            } else {
                etaSpan.textContent = '';
            }
        } else if (etaSpan) {
            etaSpan.textContent = '';
        }
    }

    function addBatchStatus(provider, message, color) {
        const statusDiv = document.getElementById('batch-status-' + provider);
        if (!statusDiv) return;

        const statusItem = document.createElement('div');
        statusItem.style.padding = '5px 0';
        statusItem.style.color = color || '#f8f9fa';
        statusItem.textContent = message;

        statusDiv.appendChild(statusItem);

        // Auto-scroll to bottom
        statusDiv.scrollTop = statusDiv.scrollHeight;
    }

    function cancelBatchUpload() {
        batchCancelled = true;
    }

</script>
<?php

?>
<link rel="stylesheet" href="<?php echo $webRoot; ?>/ui/css/main.css">
<link rel="stylesheet" href="<?php echo $webRoot; ?>/ui/css/tts-pronunciations.css">
<style>
    /* Font Face Declaration */
    @font-face {
        font-family: 'MagicCards';
        src: url('<?php echo $webRoot; ?>/ui/css/font/MagicCardsNormal.ttf') format('truetype');
        font-weight: normal;
        font-style: normal;
    }

    /* Override main container styles */
    main {
        padding-top: 80px;
        padding-bottom: 40px;
        padding-left: 10%;
        padding-right: 10%;
        width: 100%;
        margin: 0;
    }

    /* Override footer styles */
    footer {
        position: fixed;
        bottom: 0;
        width: 100%;
        height: 20px;
        background: #031633;
        z-index: 100;
    }

    /* Page Header Styling */
    .page-header {
        text-align: center;
        margin-bottom: 30px;
        padding: 20px;
        background: linear-gradient(180deg, rgba(42, 42, 42, 0.95), rgba(34, 34, 34, 0.98));
        border-radius: 10px;
        border: 1px solid #3a3a3a;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15), inset 0 1px rgba(255, 255, 255, 0.03);
    }

    .page-header h1 {
        margin-bottom: 8px;
        font-family: 'MagicCards', serif;
        word-spacing: 8px;
        font-size: 2.2em;
        color: rgb(242, 124, 17);
        text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
    }

    .page-subtitle {
        margin: 0 0 10px 0;
        color: #bbb;
        font-size: 1.1em;
        line-height: 1.6;
    }

    .page-note {
        color: #f0ad4e;
        font-size: 0.9em;
        margin: 10px 0 0 0;
    }

    .info-link {
        display: inline-block;
        margin-left: 15px;
        color: rgb(242, 124, 17);
        text-decoration: none;
        font-size: 0.7em;
        vertical-align: top;
        border: 2px solid rgb(242, 124, 17);
        border-radius: 50%;
        width: 24px;
        height: 24px;
        text-align: center;
        line-height: 20px;
        transition: all 0.3s ease;
    }

    .info-link:hover {
        background: rgb(242, 124, 17);
        color: white;
        box-shadow: 0 2px 8px rgba(242, 124, 17, 0.4);
    }

    .page-header h3 {
        text-align: center;
        margin-bottom: 15px;
    }

    .page-header h4 {
        text-align: center;
        margin-bottom: 25px;
    }

    /* Content Section Headers */
    .content-section h1, .indent5 h1 {
        font-family: 'MagicCards', serif;
        font-size: 1.8em;
        color: rgb(242, 124, 17);
        text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
        word-spacing: 8px;
        text-align: center;
        margin-bottom: 20px;
    }

    /* Form Container Styling */
    .form-container {
        background: linear-gradient(135deg, rgba(42, 42, 42, 0.95), rgba(34, 34, 34, 0.98));
        padding: 25px;
        border-radius: 10px;
        border: 1px solid #3a3a3a;
        margin-bottom: 20px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15), inset 0 1px rgba(255, 255, 255, 0.03);
        transition: border-color 0.3s ease, box-shadow 0.3s ease;
    }

    .form-container:hover {
        border-color: rgba(242, 124, 17, 0.3);
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.25), inset 0 1px rgba(255, 255, 255, 0.05);
    }

    .button-group {
        display: flex;
        gap: 15px;
        margin-top: 15px;
        flex-wrap: wrap;
    }

    /* Content Layout Improvements */
    .content-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 30px;
        margin-bottom: 30px;
    }

    .content-section {
        background: linear-gradient(135deg, rgba(42, 42, 42, 0.95), rgba(34, 34, 34, 0.98));
        padding: 25px;
        border-radius: 10px;
        border: 1px solid #3a3a3a;
        margin-bottom: 20px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15), inset 0 1px rgba(255, 255, 255, 0.03);
        transition: border-color 0.3s ease, box-shadow 0.3s ease;
    }

    .content-section:hover {
        border-color: rgba(242, 124, 17, 0.3);
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.25), inset 0 1px rgba(255, 255, 255, 0.05);
    }

    .full-width-section {
        grid-column: 1 / -1;
    }

    /* Voice list styling */
    .response-container {
        margin-top: 15px;
        padding: 15px;
        background: linear-gradient(135deg, rgba(44, 44, 44, 0.8), rgba(38, 38, 38, 0.9));
        border: 1px solid #3a3a3a;
        border-radius: 8px;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15), inset 0 1px rgba(255, 255, 255, 0.02);
    }

    .voice-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
        gap: 8px;
        margin-top: 10px;
    }

    .speaker-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 8px 12px;
        background: linear-gradient(135deg, rgba(58, 58, 58, 0.8), rgba(48, 48, 48, 0.9));
        border: 1px solid #3a3a3a;
        border-radius: 6px;
        color: #f8f9fa;
        transition: all 0.2s ease;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .speaker-item:hover {
        background: linear-gradient(135deg, rgba(68, 68, 68, 0.9), rgba(58, 58, 58, 1));
        border-color: rgba(242, 124, 17, 0.3);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
    }

    .copy-btn {
        opacity: 0.4;
        background: none;
        border: none;
        color: #fff;
        cursor: pointer;
        padding: 4px;
        font-size: 12px;
        transition: all 0.2s ease;
        margin-left: 8px;
    }

    .speaker-item:hover .copy-btn {
        opacity: 0.8;
    }

    .copy-btn:hover {
        opacity: 1 !important;
        transform: scale(1.1);
    }

    .button-container {
        display: flex;
        gap: 4px;
        align-items: center;
    }

    .play-btn {
        opacity: 0.4;
        background: none;
        border: none;
        color: #fff;
        cursor: pointer;
        padding: 4px;
        font-size: 10px;
        transition: all 0.2s ease;
    }

    .speaker-item:hover .play-btn {
        opacity: 0.8;
    }

    .play-btn:hover {
        opacity: 1 !important;
        transform: scale(1.1);
    }

    .sync-btn,
    .unsync-btn,
    .resync-btn,
    .delete-provider-btn {
        opacity: 0.4;
        background: none;
        border: none;
        color: #fff;
        cursor: pointer;
        padding: 4px;
        font-size: 14px;
        transition: all 0.2s ease;
    }

    .voice-status-item:hover .sync-btn,
    .voice-status-item:hover .unsync-btn,
    .voice-status-item:hover .resync-btn,
    .voice-status-item:hover .delete-provider-btn {
        opacity: 0.8;
    }

    .sync-btn:hover:not(:disabled),
    .resync-btn:hover:not(:disabled) {
        opacity: 1 !important;
        transform: scale(1.1);
        color: rgb(242, 124, 17);
    }

    .unsync-btn:hover:not(:disabled),
    .delete-provider-btn:hover:not(:disabled) {
        opacity: 1 !important;
        transform: scale(1.1);
        color: #f44336;
    }

    .sync-btn:disabled,
    .resync-btn:disabled {
        opacity: 0.2;
        cursor: not-allowed;
    }

    /* Voice list container styling */
    .voice-list-container {
        background: #1a1a1a;
        border-radius: 8px;
        border: 2px solid rgb(242, 124, 17);
        padding: 20px;
        margin-top: 15px;
    }

    .voice-list-header h3 {
        font-family: 'MagicCards', serif;
        color: rgb(242, 124, 17);
        text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
        word-spacing: 6px;
        text-align: center;
    }

    .voice-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px 15px;
        background-color: #3a3a3a;
        border: 1px solid #555555;
        border-radius: 6px;
        color: #f8f9fa;
        transition: all 0.2s ease;
    }

    .voice-item:hover {
        background-color: #4a4a4a;
        border-color: rgb(242, 124, 17);
    }

    /* Tab Navigation */
    .tab-nav {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-bottom: 30px;
        border-bottom: 2px solid rgba(242, 124, 17, 0.2);
        padding-bottom: 10px;
    }

    .tab-btn {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 4px;
        padding: 12px 24px;
        background: linear-gradient(180deg, rgba(42, 42, 42, 0.8), rgba(34, 34, 34, 0.9));
        border: 2px solid #3a3a3a;
        border-bottom: none;
        border-radius: 8px 8px 0 0;
        color: #cfd8e3;
        cursor: pointer;
        font-size: 16px;
        font-weight: 600;
        transition: all 0.3s ease;
        margin-bottom: -2px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .tab-label {
        line-height: 1.1;
    }

    .tab-status {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 999px;
        font-size: 10px;
        font-weight: 700;
        line-height: 1.1;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        border: 1px solid transparent;
    }

    .tab-status.connected {
        background: rgba(76, 175, 80, 0.16);
        color: #7ee08a;
        border-color: rgba(76, 175, 80, 0.35);
    }

    .tab-status.disconnected {
        background: rgba(244, 67, 54, 0.16);
        color: #ff9b92;
        border-color: rgba(244, 67, 54, 0.35);
    }

    .tab-status.configured {
        background: rgba(74, 138, 182, 0.16);
        color: #93c5fd;
        border-color: rgba(74, 138, 182, 0.35);
    }

    .tab-status.unconfigured {
        background: rgba(158, 158, 158, 0.16);
        color: #d1d5db;
        border-color: rgba(158, 158, 158, 0.3);
    }

    .tab-btn:hover {
        background: linear-gradient(180deg, rgba(58, 58, 58, 0.9), rgba(48, 48, 48, 1));
        color: rgb(242, 124, 17);
        border-color: rgba(242, 124, 17, 0.3);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
    }

    .tab-btn.active {
        background: linear-gradient(180deg, rgba(42, 42, 42, 0.95), rgba(34, 34, 34, 0.98));
        border-color: rgba(242, 124, 17, 0.5);
        border-bottom: 2px solid rgba(42, 42, 42, 0.95);
        color: rgb(242, 124, 17);
        position: relative;
        z-index: 1;
        box-shadow: 0 4px 8px rgba(242, 124, 17, 0.2);
    }

    .tab-content {
        display: none;
    }

    .tab-content.active {
        display: block;
    }

    .fallback-voice-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
        gap: 12px;
        margin: 18px 0;
    }

    .fallback-race-card {
        background: #242424;
        border: 1px solid #414141;
        border-radius: 7px;
        padding: 14px;
    }

    .fallback-race-card h2 {
        color: #f2f2f2;
        font-size: 16px;
        margin: 0 0 4px;
    }

    .fallback-race-key {
        color: #8b98a8;
        font-size: 11px;
        margin-bottom: 12px;
    }

    .fallback-gender-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
    }

    .fallback-gender-grid label {
        color: #cfd8e3;
        display: block;
        font-size: 12px;
        font-weight: 700;
        margin-bottom: 5px;
        text-transform: uppercase;
    }

    .fallback-gender-grid input {
        background: #191919;
        border: 1px solid #4a4a4a;
        border-radius: 5px;
        box-sizing: border-box;
        color: #f8f9fa;
        padding: 9px 10px;
        width: 100%;
    }

    .fallback-gender-grid input:focus {
        border-color: rgb(242, 124, 17);
        outline: none;
    }

    @media (max-width: 560px) {
        .fallback-voice-grid,
        .fallback-gender-grid {
            grid-template-columns: 1fr;
        }
    }
    .voice-status-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 12px;
        margin-top: 20px;
    }

    .voice-status-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 16px;
        background: linear-gradient(135deg, rgba(58, 58, 58, 0.8), rgba(48, 48, 48, 0.9));
        cursor: pointer;
        border: 1px solid #3a3a3a;
        border-radius: 8px;
        color: #f8f9fa;
        transition: all 0.2s ease;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .voice-status-item:hover {
        background: linear-gradient(135deg, rgba(74, 74, 74, 0.9), rgba(64, 64, 64, 1));
        border-color: rgba(242, 124, 17, 0.5);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
    }

    .voice-status-item .voice-name {
        flex: 1;
        font-weight: 500;
    }

    .voice-status-item .status-icon {
        margin: 0 12px;
        font-size: 18px;
    }

    .voice-status-item .status-icon.cloned {
        color: #4caf50;
    }

    .voice-status-item .status-icon.not-cloned {
        color: #f44336;
    }

    .voice-status-item .voice-id {
        font-size: 11px;
        color: #aaa;
        margin-right: 8px;
        max-width: 120px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        main {
            padding-left: 5%;
            padding-right: 5%;
        }

        .content-grid {
            grid-template-columns: 1fr;
        }

        .form-container {
            padding: 15px;
        }

        .content-section {
            padding: 15px;
        }

        .page-header {
            padding: 15px;
        }

        .page-header h1 {
            font-size: 1.8em;
        }

        .content-section h1, .indent5 h1 {
            font-size: 1.6em;
        }

        .voice-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 480px) {
        main {
            padding-left: 2%;
            padding-right: 2%;
        }

        .page-header h1 {
            font-size: 1.5em;
        }

        .content-section h1, .indent5 h1 {
            font-size: 1.3em;
        }

        .button-group {
            flex-direction: column;
        }

        .voice-grid {
            grid-template-columns: 1fr;
            gap: 6px;
        }

        .voice-item {
            padding: 8px 12px;
        }
    }
</style>

<?php if ($isEmbed): ?>
<style>
    /* Embedded in hub: remove extra top padding since navbar is hidden */
    main { padding-top: 20px; }
</style>
<?php endif; ?>

<main>
    <div id="loading-overlay">
        <p id="loading-message">Processing, please wait... <br><b>Do not refresh the page<span id="ellipsis"></span></b></p>
    </div>

    <div id="toast" class="toast-notification">
        <span class="message"></span>
    </div>

    <div class="page-header">
        <h1>Voice Management
        </h1>
        <p class="page-subtitle">Manage voice samples, global NPC fallback voices, and pronunciations across all TTS providers.</p>
        <p class="page-note"><strong>Note:</strong> XTTS, Chatterbox, and PocketTTS share a simple voice sample flow. OmniVoice imports voices into the selected language library.</p>
    </div>

    <!-- Tab Navigation -->
    <div class="tab-nav">
        <button class="tab-btn <?php echo $activeTab === 'xtts' ? 'active' : ''; ?>"
                title="<?php echo htmlspecialchars($ttsStudioProviderStatuses['xtts']['title']); ?>"
                onclick="switchTab('xtts')"><span class="tab-label">XTTS</span><span class="tab-status <?php echo htmlspecialchars($ttsStudioProviderStatuses['xtts']['class']); ?>"><?php echo htmlspecialchars($ttsStudioProviderStatuses['xtts']['label']); ?></span></button>
        <button class="tab-btn <?php echo $activeTab === 'chatterbox' ? 'active' : ''; ?>"
                title="<?php echo htmlspecialchars($ttsStudioProviderStatuses['chatterbox']['title']); ?>"
                onclick="switchTab('chatterbox')"><span class="tab-label">Chatterbox</span><span class="tab-status <?php echo htmlspecialchars($ttsStudioProviderStatuses['chatterbox']['class']); ?>"><?php echo htmlspecialchars($ttsStudioProviderStatuses['chatterbox']['label']); ?></span></button>
        <button class="tab-btn <?php echo $activeTab === 'pockettts' ? 'active' : ''; ?>"
                title="<?php echo htmlspecialchars($ttsStudioProviderStatuses['pockettts']['title']); ?>"
                onclick="switchTab('pockettts')"><span class="tab-label">PocketTTS (<?php echo htmlspecialchars($pocketTtsModeLabel); ?>)</span><span class="tab-status <?php echo htmlspecialchars($ttsStudioProviderStatuses['pockettts']['class']); ?>"><?php echo htmlspecialchars($ttsStudioProviderStatuses['pockettts']['label']); ?></span></button>
        <button class="tab-btn <?php echo $activeTab === 'omnivoice' ? 'active' : ''; ?>"
                title="<?php echo htmlspecialchars($ttsStudioProviderStatuses['omnivoice']['title']); ?>"
                onclick="switchTab('omnivoice')"><span class="tab-label">OmniVoice</span><span class="tab-status <?php echo htmlspecialchars($ttsStudioProviderStatuses['omnivoice']['class']); ?>"><?php echo htmlspecialchars($ttsStudioProviderStatuses['omnivoice']['label']); ?></span></button>
        <button class="tab-btn <?php echo $activeTab === 'cartesia' ? 'active' : ''; ?>"
                title="<?php echo htmlspecialchars($ttsStudioProviderStatuses['cartesia']['title']); ?>"
                onclick="switchTab('cartesia')"><span class="tab-label">Cartesia</span><span class="tab-status <?php echo htmlspecialchars($ttsStudioProviderStatuses['cartesia']['class']); ?>"><?php echo htmlspecialchars($ttsStudioProviderStatuses['cartesia']['label']); ?></span></button>
        <button class="tab-btn <?php echo $activeTab === 'inworld' ? 'active' : ''; ?>"
                title="<?php echo htmlspecialchars($ttsStudioProviderStatuses['inworld']['title']); ?>"
                onclick="switchTab('inworld')"><span class="tab-label">Inworld</span><span class="tab-status <?php echo htmlspecialchars($ttsStudioProviderStatuses['inworld']['class']); ?>"><?php echo htmlspecialchars($ttsStudioProviderStatuses['inworld']['label']); ?></span></button>
        <button class="tab-btn <?php echo $activeTab === 'fallbacks' ? 'active' : ''; ?>"
                title="Global race and gender voice fallbacks used by every TTS connector"
                onclick="switchTab('fallbacks')"><span class="tab-label">Fallback Voices</span><span class="tab-status configured">Global</span></button>
        <button class="tab-btn <?php echo $activeTab === 'pronunciations' ? 'active' : ''; ?>"
                title="Global pronunciation dictionary applied to spoken audio for every TTS connector"
                onclick="switchTab('pronunciations')"><span class="tab-label">Pronunciations</span><span class="tab-status configured">Global</span></button>
    </div>

    <?php if (!empty($message)): ?>
        <div class="message"><?php echo $message; ?></div>
    <?php endif; ?>
    <?php if (!empty($cartesiaMessage)): ?>
        <div class="message"><?php echo $cartesiaMessage; ?></div>
    <?php endif; ?>
    <?php if (!empty($inworldMessage)): ?>
        <div class="message"><?php echo $inworldMessage; ?></div>
    <?php endif; ?>
    <?php if (isset($_GET['synced']) && ($activeTab === 'xtts' || $activeTab === 'chatterbox' || $activeTab === 'pockettts' || $activeTab === 'omnivoice')): ?>
        <div class="message">
            <p style='color:rgb(247, 231, 16);'><strong>Successfully synced voice '<?php echo htmlspecialchars($_GET['synced']); ?>' to server.</strong></p>
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['deleted']) && in_array($activeTab, ['xtts', 'chatterbox', 'pockettts', 'omnivoice'], true)): ?>
        <div class="message">
            <p style='color:rgb(247, 231, 16);'><strong>Removed provider copy of '<?php echo htmlspecialchars($_GET['deleted']); ?>'. The local WAV is still available to re-upload.</strong></p>
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['synced']) && $activeTab === 'cartesia'): ?>
        <div class="message">
            <p style='color:rgb(247, 231, 16);'><strong>Successfully generated voice '<?php echo htmlspecialchars($_GET['synced']); ?>' for Cartesia.</strong></p>
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['synced']) && $activeTab === 'inworld'): ?>
        <div class="message">
            <p style='color:rgb(247, 231, 16);'><strong>Successfully generated voice '<?php echo htmlspecialchars($_GET['synced']); ?>' for Inworld.</strong></p>
        </div>
    <?php endif; ?>

    <!-- Fallback Voices Tab Content -->
    <div class="tab-content <?php echo $activeTab === 'fallbacks' ? 'active' : ''; ?>">
        <div class="content-section full-width-section">
            <h1>Fallback Voices</h1>
            <p>Choose the voice used when an NPC has no explicit voice assigned. These settings apply to every TTS connector.</p>
            <p style="color:#aeb8c4;"><strong>Resolution order:</strong> explicit NPC voice, matching race and gender voice, then the connector's male or female fallback. Leave a field blank to skip the race fallback for that combination.</p>

            <form action="<?php echo $webRoot; ?>/ui/xtts_clone.php?tab=fallbacks" method="post">
                <input type="hidden" name="action" value="save_tts_fallbacks">
                <div class="fallback-voice-grid">
                    <?php foreach ($ttsFallbackDefinitions as $race => $definition): ?>
                        <section class="fallback-race-card">
                            <h2><?php echo htmlspecialchars($definition['label'] ?? $race); ?></h2>
                            <div class="fallback-race-key"><?php echo htmlspecialchars($race); ?></div>
                            <div class="fallback-gender-grid">
                                <?php foreach (['male' => 'Male', 'female' => 'Female'] as $gender => $genderLabel): ?>
                                    <div>
                                        <label for="fallback-<?php echo htmlspecialchars($race . '-' . $gender); ?>"><?php echo $genderLabel; ?></label>
                                        <input
                                            id="fallback-<?php echo htmlspecialchars($race . '-' . $gender); ?>"
                                            name="fallbacks[<?php echo htmlspecialchars($race); ?>][<?php echo htmlspecialchars($gender); ?>]"
                                            value="<?php echo htmlspecialchars($ttsFallbackMatrix[$race][$gender] ?? ''); ?>"
                                            list="tts-fallback-voiceids"
                                            autocomplete="off"
                                            spellcheck="false">
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endforeach; ?>
                </div>

                <datalist id="tts-fallback-voiceids">
                    <?php foreach ($ttsFallbackVoiceIds as $voiceId): ?>
                        <option value="<?php echo htmlspecialchars($voiceId); ?>"></option>
                    <?php endforeach; ?>
                </datalist>

                <div class="button-group">
                    <button type="submit" class="action-button upload-csv">Save Fallback Voices</button>
                </div>
            </form>
        </div>
    </div>
    <!-- Pronunciations Tab Content -->
    <?php include(__DIR__ . DIRECTORY_SEPARATOR . 'tmpl' . DIRECTORY_SEPARATOR . 'tts_pronunciations.php'); ?>

    <!-- XTTS Tab Content -->
    <div class="tab-content <?php echo $activeTab === 'xtts' ? 'active' : ''; ?>" data-tab-type="xtts">
        <div class="content-section full-width-section">
            <h1>Voice Sample Upload</h1>
            <p>Upload voice samples to <code>data/voices</code>. Files will be cached and uploaded to the XTTS server.</p>
            <p style="color: #4a8ab6;"><strong>XTTS Info:</strong> XTTS is a TTS engine that generates cloned voices from samples. Uses roughly 4GB of VRAM. Best for NVIDIA GPUs.</p>

            <form action="<?php echo $webRoot; ?>/ui/xtts_clone.php?tab=xtts" method="post" enctype="multipart/form-data" style="margin-top: 20px;">
                <div style="margin-bottom: 15px;">
                    <label for="file" style="display: block; margin-bottom: 8px;">Select .wav file(s) or .zip archive to upload:</label>
                    <input type="file" name="file[]" id="file" accept=".wav,.zip" multiple="multiple" required style="width: 100%; max-width: 500px; padding: 8px;">
                </div>
                <div class="button-group">
                    <input type="submit" name="submit" value="Upload Voice Sample" class="action-button upload-csv">
                </div>
            </form>

            <div style="margin-top: 20px; padding: 15px; background: rgba(74, 138, 182, 0.1); border: 1px solid #4a8ab6; border-radius: 6px;">
                <p style="margin: 0 0 10px 0; font-weight: 600; color: #4a8ab6;">📋 File Requirements:</p>
                <ul style="margin: 0; padding-left: 20px;">
                    <li>Format: WAV (PCM), 16-bit, Mono, 20500Hz</li>
                    <li>Size: 5MB or less</li>
                    <li>Filename: lowercase with underscores (e.g., "mjoll_the_lioness.wav")</li>
                    <li><b>XTTS:</b> Audio clips must be at least <b>5 seconds</b> long for voice generation</li>
                </ul>
                <p style="margin: 10px 0 0 0; font-size: 0.9em; color: #aaa;"><b>Note:</b> If replacing an existing voice, restart the XTTS server after upload.</p>
            </div>
        </div>

        <div class="content-section full-width-section">
            <h1>XTTS Voice Cache</h1>
            <p>Manage voice samples for XTTS. Voices are uploaded from local .wav files in <code>data/voices</code> to the XTTS server.</p>

            <?php
            $localVoices = getLocalVoices();
            $xttsVoices = [];
            foreach (chimTtsStudioGetCachedSpeakersList('xtts-fastapi') as $speaker) {
                    $displayName = basename($speaker, '.wav');
                    $xttsVoices[$displayName] = true;
            }
            ?>

            <div class="button-group" style="margin-top: 20px;">
                <form action="<?php echo $webRoot; ?>/ui/xtts_clone.php?tab=xtts" method="post" style="display: inline;">
                    <input type="hidden" name="get_speakers" value="1">
                    <input type="submit" value="Refresh XTTS Server Voices" class="action-button download-csv">
                </form>
            </div>

            <div class="voice-status-grid" style="margin-top: 20px;">
                <?php foreach ($localVoices as $voice): ?>
                    <?php $isOnServer = isset($xttsVoices[$voice]); ?>
                    <div class="voice-status-item" onclick="copyToClipboard('<?php echo htmlspecialchars($voice, ENT_QUOTES); ?>')" title="Click to copy voice name">
                        <span class="voice-name"><?php echo htmlspecialchars($voice); ?></span>
                        <span class="status-icon <?php echo $isOnServer ? 'cloned' : 'not-cloned'; ?>">
                            <?php echo $isOnServer ? '✓' : '✗'; ?>
                        </span>
                        <div class="button-container">
                            <?php if ($isOnServer): ?>
                                <button onclick="event.stopPropagation(); testVoice('<?php echo htmlspecialchars($voice, ENT_QUOTES); ?>')"
                                        class="play-btn" title="Test voice">▶</button>
                                <button onclick="event.stopPropagation(); deleteProviderVoice('xtts', '<?php echo htmlspecialchars($voice, ENT_QUOTES); ?>')"
                                        class="delete-provider-btn" title="Remove from XTTS server">×</button>
                            <?php else: ?>
                                <button onclick="event.stopPropagation(); syncSingleVoice('xtts', '<?php echo htmlspecialchars($voice, ENT_QUOTES); ?>')"
                                        class="sync-btn" title="Sync this voice to XTTS server">↻</button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($localVoices)): ?>
                    <p>No voice files found in <code>data/voices</code>. Upload voice samples above first.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="content-section full-width-section">
            <h1>Batch Process Missing Voices</h1>
            <p>Upload missing local voices to the XTTS server.</p>

            <?php
            $localVoices = getLocalVoices();
            $xttsVoices = [];
            foreach (chimTtsStudioGetCachedSpeakersList('xtts-fastapi') as $speaker) {
                    $displayName = basename($speaker, '.wav');
                    $xttsVoices[$displayName] = true;
            }
            $missingXttsVoices = [];
            foreach ($localVoices as $voice) {
                if (!isset($xttsVoices[$voice])) {
                    $missingXttsVoices[] = $voice;
                }
            }
            ?>

            <?php if (count($missingXttsVoices) > 0): ?>
                <p style="color: #4a8ab6;">Found <?php echo count($missingXttsVoices); ?> voice(s) not yet uploaded to XTTS server.</p>
                <div class="button-group">
                    <button onclick="startBatchUpload('xtts', <?php echo htmlspecialchars(json_encode($missingXttsVoices)); ?>)" class="action-button upload-csv">
                        Batch Upload Missing Voices (<?php echo count($missingXttsVoices); ?>)
                    </button>
                    <button onclick="cancelBatchUpload()" id="cancel-batch-xtts" class="action-button delete" style="display:none;">Cancel</button>
                </div>

                <div id="batch-progress-xtts" style="display:none; margin-top: 20px; padding: 15px; background: #2c2c2c; border: 1px solid #4a4a4a; border-radius: 5px;">
                    <div style="margin-bottom: 10px;">
                        <strong>Progress: <span id="batch-current-xtts">0</span> / <span id="batch-total-xtts">0</span></strong>
                        <span id="batch-eta-xtts" style="margin-left: 15px; color: #aaa;"></span>
                    </div>
                    <div style="background: #1a1a1a; height: 30px; border-radius: 4px; overflow: hidden; margin-bottom: 15px;">
                        <div id="batch-progress-bar-xtts" style="background: rgb(242, 124, 17); height: 100%; width: 0%; transition: width 0.3s;"></div>
                    </div>
                    <div id="batch-status-xtts" style="max-height: 300px; overflow-y: auto;">
                        <!-- Status messages will appear here -->
                    </div>
                </div>
            <?php else: ?>
                <p style="color: #4caf50;">✓ All local voices are already uploaded to XTTS server.</p>
            <?php endif; ?>
        </div>

        <div class="content-section full-width-section">
            <h1>Cloud XTTS Sync</h1>
            <form action="<?php echo $webRoot; ?>/ui/xtts_clone.php?tab=xtts" method="post" onsubmit="showLoadingMessage('Syncing voice cache to XTTS server, this can take a couple minutes...');">
                <p><strong>Only required for online XTTS instances.</strong></p>
                <p>Sync just needs to be ran ONE TIME after initial setup of a new instance.</p>
                <p>Empty voice cache is acceptable - new NPC voices will be cached automatically.</p>
                <p>For cloud setup instructions, see our <a href="https://dwemerdynamics.com/chim/remote-hosting-guide.html" style="color: yellow;" target="_blank" rel="noopener noreferrer">Cloud XTTS Guide</a>.</p>
                <p>Cached voices are stored in <code>data/voices</code>. <a href="<?php echo $webRoot; ?>/data/voices" style="color: yellow;" target="_blank">View Cache Directory</a></p>
                <div class="button-group">
                    <input type="submit" name="upload_all" value="Sync Voice Cache" class="action-button edit">
                </div>
            </form>
            <p>Advanced XTTS configuration: <a href="<?php echo htmlspecialchars($xttsStudioEndpoints['xtts-fastapi']); ?>/docs" style="color: yellow;" target="_blank"><?php echo htmlspecialchars($xttsStudioEndpoints['xtts-fastapi']); ?>/docs</a></p>
        </div>
    </div>

    <!-- Chatterbox Tab Content -->
    <div class="tab-content <?php echo $activeTab === 'chatterbox' ? 'active' : ''; ?>" data-tab-type="chatterbox">
        <div class="content-section full-width-section">
            <h1>Voice Sample Upload</h1>
            <p>Upload voice samples to <code>data/voices</code>. Files will be cached and uploaded to the Chatterbox server.</p>
            <p style="color: #4a8ab6;"><strong>Chatterbox Info:</strong> Chatterbox is an optimized fork of XTTS with faster inference. Uses roughly 4GB of VRAM. Shares the same API as XTTS.</p>

            <form action="<?php echo $webRoot; ?>/ui/xtts_clone.php?tab=chatterbox" method="post" enctype="multipart/form-data" style="margin-top: 20px;">
                <div style="margin-bottom: 15px;">
                    <label for="file" style="display: block; margin-bottom: 8px;">Select .wav file(s) or .zip archive to upload:</label>
                    <input type="file" name="file[]" id="file" accept=".wav,.zip" multiple="multiple" required style="width: 100%; max-width: 500px; padding: 8px;">
                </div>
                <div class="button-group">
                    <input type="submit" name="submit" value="Upload Voice Sample" class="action-button upload-csv">
                </div>
            </form>

            <div style="margin-top: 20px; padding: 15px; background: rgba(74, 138, 182, 0.1); border: 1px solid #4a8ab6; border-radius: 6px;">
                <p style="margin: 0 0 10px 0; font-weight: 600; color: #4a8ab6;">📋 File Requirements:</p>
                <ul style="margin: 0; padding-left: 20px;">
                    <li>Format: WAV (PCM), 16-bit, Mono, 20500Hz</li>
                    <li>Size: 5MB or less</li>
                    <li>Filename: lowercase with underscores (e.g., "mjoll_the_lioness.wav")</li>
                    <li><b>Chatterbox:</b> Audio clips must be at least <b>5 seconds</b> long for voice generation</li>
                </ul>
                <p style="margin: 10px 0 0 0; font-size: 0.9em; color: #aaa;"><b>Note:</b> If replacing an existing voice, restart the Chatterbox server after upload.</p>
            </div>
        </div>

        <div class="content-section full-width-section">
            <h1>Chatterbox Voice Cache</h1>
            <p>Manage voice samples for Chatterbox. This view now shows all voices currently reported by the live Chatterbox endpoint, plus any local cached samples in <code>data/voices</code>.</p>

            <?php
            $localVoices = getLocalVoices();
            $localVoiceMap = [];
            foreach ($localVoices as $voice) {
                $localVoiceMap[$voice] = true;
            }

            $serverVoices = [];
            foreach (chimTtsStudioGetCachedSpeakersList('chatterbox') as $speaker) {
                $displayName = basename($speaker, '.wav');
                $serverVoices[$displayName] = true;
            }

            $displayVoices = array_values(array_unique(array_merge(array_keys($serverVoices), $localVoices)));
            natcasesort($displayVoices);
            $displayVoices = array_values($displayVoices);
            ?>

            <div class="button-group" style="margin-top: 20px;">
                <form action="<?php echo $webRoot; ?>/ui/xtts_clone.php?tab=chatterbox" method="post" style="display: inline;">
                    <input type="hidden" name="get_speakers" value="1">
                    <input type="submit" value="Refresh Chatterbox Server Voices" class="action-button download-csv">
                </form>
            </div>

            <div class="voice-status-grid" style="margin-top: 20px;">
                <?php foreach ($displayVoices as $voice): ?>
                    <?php $isOnServer = isset($serverVoices[$voice]); ?>
                    <?php $hasLocalSample = isset($localVoiceMap[$voice]); ?>
                    <div class="voice-status-item" onclick="copyToClipboard('<?php echo htmlspecialchars($voice, ENT_QUOTES); ?>')" title="Click to copy voice name">
                        <span class="voice-name"><?php echo htmlspecialchars($voice); ?></span>
                        <span class="status-icon <?php echo $isOnServer ? 'cloned' : 'not-cloned'; ?>">
                            <?php echo $isOnServer ? '✓' : '✗'; ?>
                        </span>
                        <?php if ($isOnServer && !$hasLocalSample): ?>
                            <span class="voice-id" title="Available on the live Chatterbox server only">server</span>
                        <?php elseif (!$isOnServer && $hasLocalSample): ?>
                            <span class="voice-id" title="Available in local cache but not currently uploaded to Chatterbox">local</span>
                        <?php endif; ?>
                        <div class="button-container">
                            <?php if ($isOnServer): ?>
                                <button onclick="event.stopPropagation(); testVoice('<?php echo htmlspecialchars($voice, ENT_QUOTES); ?>')"
                                        class="play-btn" title="Test voice">▶</button>
                                <button onclick="event.stopPropagation(); deleteProviderVoice('chatterbox', '<?php echo htmlspecialchars($voice, ENT_QUOTES); ?>')"
                                        class="delete-provider-btn" title="Remove from Chatterbox server">×</button>
                            <?php elseif ($hasLocalSample): ?>
                                <button onclick="event.stopPropagation(); syncSingleVoice('chatterbox', '<?php echo htmlspecialchars($voice, ENT_QUOTES); ?>')"
                                        class="sync-btn" title="Sync this voice to Chatterbox server">↻</button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($displayVoices)): ?>
                    <p>No Chatterbox voices were found on the live endpoint, and no local voice files exist in <code>data/voices</code>.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="content-section full-width-section">
            <h1>Batch Process Missing Voices</h1>
            <p>Upload missing local voices to the Chatterbox server.</p>

            <?php
            $localVoices = getLocalVoices();
            $serverVoices = [];
            foreach (chimTtsStudioGetCachedSpeakersList('chatterbox') as $speaker) {
                $displayName = basename($speaker, '.wav');
                $serverVoices[$displayName] = true;
            }
            $missingXttsVoices = [];
            foreach ($localVoices as $voice) {
                if (!isset($serverVoices[$voice])) {
                    $missingXttsVoices[] = $voice;
                }
            }
            ?>

            <?php if (count($missingXttsVoices) > 0): ?>
                <p style="color: #4a8ab6;">Found <?php echo count($missingXttsVoices); ?> voice(s) not yet uploaded to Chatterbox server.</p>
                <div class="button-group">
                    <button onclick="startBatchUpload('chatterbox', <?php echo htmlspecialchars(json_encode($missingXttsVoices)); ?>)" class="action-button upload-csv">
                        Batch Upload Missing Voices (<?php echo count($missingXttsVoices); ?>)
                    </button>
                    <button onclick="cancelBatchUpload()" id="cancel-batch-chatterbox" class="action-button delete" style="display:none;">Cancel</button>
                </div>

                <div id="batch-progress-chatterbox" style="display:none; margin-top: 20px; padding: 15px; background: #2c2c2c; border: 1px solid #4a4a4a; border-radius: 5px;">
                    <div style="margin-bottom: 10px;">
                        <strong>Progress: <span id="batch-current-chatterbox">0</span> / <span id="batch-total-chatterbox">0</span></strong>
                        <span id="batch-eta-chatterbox" style="margin-left: 15px; color: #aaa;"></span>
                    </div>
                    <div style="background: #1a1a1a; height: 30px; border-radius: 4px; overflow: hidden; margin-bottom: 15px;">
                        <div id="batch-progress-bar-chatterbox" style="background: rgb(242, 124, 17); height: 100%; width: 0%; transition: width 0.3s;"></div>
                    </div>
                    <div id="batch-status-chatterbox" style="max-height: 300px; overflow-y: auto;">
                        <!-- Status messages will appear here -->
                    </div>
                </div>
            <?php else: ?>
                <p style="color: #4caf50;">✓ All local voices are already uploaded to Chatterbox server.</p>
            <?php endif; ?>
        </div>

        <div class="content-section full-width-section">
            <h1>Cloud Chatterbox Sync</h1>
            <form action="<?php echo $webRoot; ?>/ui/xtts_clone.php?tab=chatterbox" method="post" onsubmit="showLoadingMessage('Syncing voice cache to Chatterbox server, this can take a couple minutes...');">
                <p><strong>Only required for online Chatterbox instances.</strong></p>
                <p>Sync just needs to be ran ONE TIME after initial setup of a new instance.</p>
                <p>Empty voice cache is acceptable - new NPC voices will be cached automatically.</p>
                <p>For cloud setup instructions, see our <a href="https://dwemerdynamics.com/chim/remote-hosting-guide.html" style="color: yellow;" target="_blank" rel="noopener noreferrer">Cloud XTTS Guide</a>.</p>
                <p>Cached voices are stored in <code>data/voices</code>. <a href="<?php echo $webRoot; ?>/data/voices" style="color: yellow;" target="_blank">View Cache Directory</a></p>
                <div class="button-group">
                    <input type="submit" name="upload_all" value="Sync Voice Cache" class="action-button edit">
                </div>
            </form>
            <p>Advanced Chatterbox configuration: <a href="<?php echo htmlspecialchars($xttsStudioEndpoints['chatterbox']); ?>/docs" style="color: yellow;" target="_blank"><?php echo htmlspecialchars($xttsStudioEndpoints['chatterbox']); ?>/docs</a></p>
        </div>
    </div>

    <!-- PocketTTS Tab Content -->
    <div class="tab-content <?php echo $activeTab === 'pockettts' ? 'active' : ''; ?>" data-tab-type="pockettts">
        <div class="content-section full-width-section">
            <h1>Voice Sample Upload</h1>
            <p>Upload voice samples to <code>data/voices</code>. TTS Studio automatically uses local files for audio.cpp or server synchronization for the standard API.</p>
            <p style="color: #4a8ab6;"><strong>Detected PocketTTS Mode:</strong> <?php echo htmlspecialchars($pocketTtsModeLabel); ?> at <code><?php echo htmlspecialchars($xttsStudioEndpoints['pockettts'] ?? 'Not configured'); ?></code>.</p>

            <form action="<?php echo $webRoot; ?>/ui/xtts_clone.php?tab=pockettts" method="post" enctype="multipart/form-data" style="margin-top: 20px;">
                <div style="margin-bottom: 15px;">
                    <label for="file" style="display: block; margin-bottom: 8px;">Select .wav file(s) or .zip archive to upload:</label>
                    <input type="file" name="file[]" id="file" accept=".wav,.zip" multiple="multiple" required style="width: 100%; max-width: 500px; padding: 8px;">
                </div>
                <div class="button-group">
                    <input type="submit" name="submit" value="Upload Voice Sample" class="action-button upload-csv">
                </div>
            </form>

            <div style="margin-top: 20px; padding: 15px; background: rgba(74, 138, 182, 0.1); border: 1px solid #4a8ab6; border-radius: 6px;">
                <p style="margin: 0 0 10px 0; font-weight: 600; color: #4a8ab6;">📋 File Requirements:</p>
                <ul style="margin: 0; padding-left: 20px;">
                    <li>Format: WAV (PCM), 16-bit, Mono, 20500Hz</li>
                    <li>Size: 5MB or less</li>
                    <li>Filename: lowercase with underscores (e.g., "mjoll_the_lioness.wav")</li>
                    <li><b>PocketTTS:</b> Audio clips must be at least <b>5 seconds</b> long for voice generation</li>
                </ul>
                <p style="margin: 10px 0 0 0; font-size: 0.9em; color: #aaa;"><b>Note:</b> If replacing an existing voice, restart the PocketTTS server after upload.</p>
            </div>
        </div>

        <div class="content-section full-width-section">
            <h1>PocketTTS Voice Cache</h1>
            <p>Manage voice samples for PocketTTS. Current mode: <strong><?php echo htmlspecialchars($pocketTtsModeLabel); ?></strong>.</p>

            <?php
            $localVoices = getLocalVoices();
            $xttsVoices = [];
            foreach (chimTtsStudioGetCachedSpeakersList('pockettts') as $speaker) {
                    $displayName = basename($speaker, '.wav');
                    $xttsVoices[$displayName] = true;
            }
            $pocketTtsEndpoint = chimTtsStudioResolveEndpointForDriver('pockettts');
            $pocketTtsCanDeleteRemote = !chimTtsStudioIsAudioCppPocketTts('pockettts', $pocketTtsEndpoint);
            ?>

            <div class="button-group" style="margin-top: 20px;">
                <form action="<?php echo $webRoot; ?>/ui/xtts_clone.php?tab=pockettts" method="post" style="display: inline;">
                    <input type="hidden" name="get_speakers" value="1">
                    <input type="submit" value="Refresh PocketTTS Voices" class="action-button download-csv">
                </form>
            </div>

            <div class="voice-status-grid" style="margin-top: 20px;">
                <?php foreach ($localVoices as $voice): ?>
                    <?php $isOnServer = isset($xttsVoices[$voice]); ?>
                    <div class="voice-status-item" onclick="copyToClipboard('<?php echo htmlspecialchars($voice, ENT_QUOTES); ?>')" title="Click to copy voice name">
                        <span class="voice-name"><?php echo htmlspecialchars($voice); ?></span>
                        <span class="status-icon <?php echo $isOnServer ? 'cloned' : 'not-cloned'; ?>">
                            <?php echo $isOnServer ? '✓' : '✗'; ?>
                        </span>
                        <div class="button-container">
                            <?php if ($isOnServer): ?>
                                <button onclick="event.stopPropagation(); testVoice('<?php echo htmlspecialchars($voice, ENT_QUOTES); ?>')"
                                        class="play-btn" title="Test voice">▶</button>
                                <?php if ($pocketTtsCanDeleteRemote): ?>
                                    <button onclick="event.stopPropagation(); deleteProviderVoice('pockettts', '<?php echo htmlspecialchars($voice, ENT_QUOTES); ?>')"
                                            class="delete-provider-btn" title="Remove from PocketTTS server">×</button>
                                <?php endif; ?>
                            <?php else: ?>
                                <button onclick="event.stopPropagation(); syncSingleVoice('pockettts', '<?php echo htmlspecialchars($voice, ENT_QUOTES); ?>')"
                                        class="sync-btn" title="Sync this voice to PocketTTS server">↻</button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($localVoices)): ?>
                    <p>No voice files found in <code>data/voices</code>. Upload voice samples above first.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="content-section full-width-section">
            <h1>Batch Process Missing Voices</h1>
            <p>Make missing local voices available to PocketTTS.</p>

            <?php
            $localVoices = getLocalVoices();
            $xttsVoices = [];
            foreach (chimTtsStudioGetCachedSpeakersList('pockettts') as $speaker) {
                    $displayName = basename($speaker, '.wav');
                    $xttsVoices[$displayName] = true;
            }
            $missingXttsVoices = [];
            foreach ($localVoices as $voice) {
                if (!isset($xttsVoices[$voice])) {
                    $missingXttsVoices[] = $voice;
                }
            }
            ?>

            <?php if (count($missingXttsVoices) > 0): ?>
                <p style="color: #4a8ab6;">Found <?php echo count($missingXttsVoices); ?> voice(s) not yet available to PocketTTS.</p>
                <div class="button-group">
                    <button onclick="startBatchUpload('pockettts', <?php echo htmlspecialchars(json_encode($missingXttsVoices)); ?>)" class="action-button upload-csv">
                        Process Missing Voices (<?php echo count($missingXttsVoices); ?>)
                    </button>
                    <button onclick="cancelBatchUpload()" id="cancel-batch-pockettts" class="action-button delete" style="display:none;">Cancel</button>
                </div>

                <div id="batch-progress-pockettts" style="display:none; margin-top: 20px; padding: 15px; background: #2c2c2c; border: 1px solid #4a4a4a; border-radius: 5px;">
                    <div style="margin-bottom: 10px;">
                        <strong>Progress: <span id="batch-current-pockettts">0</span> / <span id="batch-total-pockettts">0</span></strong>
                        <span id="batch-eta-pockettts" style="margin-left: 15px; color: #aaa;"></span>
                    </div>
                    <div style="background: #1a1a1a; height: 30px; border-radius: 4px; overflow: hidden; margin-bottom: 15px;">
                        <div id="batch-progress-bar-pockettts" style="background: rgb(242, 124, 17); height: 100%; width: 0%; transition: width 0.3s;"></div>
                    </div>
                    <div id="batch-status-pockettts" style="max-height: 300px; overflow-y: auto;">
                        <!-- Status messages will appear here -->
                    </div>
                </div>
            <?php else: ?>
                <p style="color: #4caf50;">✓ All local voices are available to PocketTTS.</p>
            <?php endif; ?>
        </div>

        <div class="content-section full-width-section">
            <h1>Cloud PocketTTS Sync</h1>
            <form action="<?php echo $webRoot; ?>/ui/xtts_clone.php?tab=pockettts" method="post" onsubmit="showLoadingMessage('Syncing voice cache to PocketTTS server, this can take a couple minutes...');">
                <p><strong>Only required for legacy or online PocketTTS instances.</strong></p>
                <p>Sync just needs to be ran ONE TIME after initial setup of a new instance.</p>
                <p>Empty voice cache is acceptable - new NPC voices will be cached automatically.</p>
                <p>For cloud setup instructions, see our <a href="https://dwemerdynamics.com/chim/remote-hosting-guide.html" style="color: yellow;" target="_blank" rel="noopener noreferrer">Cloud XTTS Guide</a>.</p>
                <p>Cached voices are stored in <code>data/voices</code>. <a href="<?php echo $webRoot; ?>/data/voices" style="color: yellow;" target="_blank">View Cache Directory</a></p>
                <div class="button-group">
                    <input type="submit" name="upload_all" value="Sync Voice Cache" class="action-button edit">
                </div>
            </form>
            <p>Advanced PocketTTS configuration: <a href="<?php echo htmlspecialchars($xttsStudioEndpoints['pockettts']); ?>/docs" style="color: yellow;" target="_blank"><?php echo htmlspecialchars($xttsStudioEndpoints['pockettts']); ?>/docs</a></p>
        </div>
    </div>

    <!-- OmniVoice Tab Content -->
    <div class="tab-content <?php echo $activeTab === 'omnivoice' ? 'active' : ''; ?>" data-tab-type="omnivoice">
        <?php
        $omnivoiceLibraries = chimTtsStudioFetchOmniVoiceLibraries();
        $activeOmniVoiceLanguage = chimTtsStudioResolveOmniVoiceLanguage($activeOmniVoiceLanguage);
        $omnivoiceLanguageOptions = [];
        foreach ($omnivoiceLibraries as $library) {
            if (!is_array($library)) {
                continue;
            }
            $libraryId = chimTtsStudioSanitizeLanguage($library['id'] ?? '');
            if ($libraryId === '') {
                continue;
            }
            $libraryLabel = trim(strval($library['name'] ?? ($library['display_name'] ?? strtoupper($libraryId))));
            $omnivoiceLanguageOptions[$libraryId] = [
                'label' => $libraryLabel !== '' ? $libraryLabel : strtoupper($libraryId),
                'voice_count' => intval($library['voice_count'] ?? 0),
                'total_voice_folders' => intval($library['total_voice_folders'] ?? 0),
                'active' => !empty($library['active']),
            ];
        }
        if (!isset($omnivoiceLanguageOptions[$activeOmniVoiceLanguage])) {
            $omnivoiceLanguageOptions[$activeOmniVoiceLanguage] = [
                'label' => strtoupper($activeOmniVoiceLanguage),
                'voice_count' => 0,
                'total_voice_folders' => 0,
                'active' => false,
            ];
        }

        $localVoices = getLocalVoices();
        $localVoiceMap = [];
        foreach ($localVoices as $voice) {
            $localVoiceMap[$voice] = true;
        }

        $omnivoiceItems = chimTtsStudioFetchOmniVoiceVoiceItems($activeOmniVoiceLanguage);
        $omnivoiceReadyVoices = [];
        $omnivoiceAllVoices = [];
        $omnivoiceItemMap = [];
        foreach ($omnivoiceItems as $item) {
            if (!is_array($item)) {
                continue;
            }
            $voiceId = trim(strval($item['voice_id'] ?? ($item['name'] ?? ($item['speaker'] ?? ''))));
            if ($voiceId === '') {
                continue;
            }
            $omnivoiceAllVoices[$voiceId] = true;
            $omnivoiceItemMap[$voiceId] = $item;
            if (!empty($item['runtime_ready']) || in_array(strtolower(trim(strval($item['status'] ?? ''))), ['ready', 'runtime_ready', 'ok'], true)) {
                $omnivoiceReadyVoices[$voiceId] = true;
            }
        }

        $omnivoiceDisplayVoices = array_values(array_unique(array_merge(array_keys($omnivoiceAllVoices), $localVoices)));
        natcasesort($omnivoiceDisplayVoices);
        $omnivoiceDisplayVoices = array_values($omnivoiceDisplayVoices);

        $missingOmniVoiceVoices = [];
        foreach ($localVoices as $voice) {
            if (!isset($omnivoiceReadyVoices[$voice])) {
                $missingOmniVoiceVoices[] = $voice;
            }
        }
        ?>

        <div class="content-section full-width-section">
            <h1>OmniVoice Language Library</h1>
            <p>Choose the language library that uploads, sync, and tests should use.</p>
            <div style="margin: 14px 0 0 0; max-width: 420px;">
                <label for="omnivoice-language" style="display: block; margin-bottom: 8px;">Language library:</label>
                <select id="omnivoice-language" name="language" onchange="changeOmniVoiceLanguage(this.value)" style="width: 100%; max-width: 360px; padding: 8px;">
                    <?php foreach ($omnivoiceLanguageOptions as $languageId => $languageData): ?>
                        <option value="<?php echo htmlspecialchars($languageId); ?>" <?php echo $languageId === $activeOmniVoiceLanguage ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($languageData['label'] . ' (' . $languageId . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="content-section full-width-section">
            <h1>Voice Sample Upload</h1>
            <p>Upload WAV samples into the selected OmniVoice language library. Reference text is generated automatically by local STT.</p>
            <form action="<?php echo $webRoot; ?>/ui/xtts_clone.php?tab=omnivoice&language=<?php echo urlencode($activeOmniVoiceLanguage); ?>" method="post" enctype="multipart/form-data" style="margin-top: 20px;">
                <input type="hidden" name="language" value="<?php echo htmlspecialchars($activeOmniVoiceLanguage); ?>">
                <div style="margin-bottom: 15px;">
                    <label for="file_omnivoice" style="display: block; margin-bottom: 8px;">Select .wav file(s) or .zip archive to import:</label>
                    <input type="file" name="file[]" id="file_omnivoice" accept=".wav,.zip" multiple="multiple" required style="width: 100%; max-width: 500px; padding: 8px;">
                </div>
                <div class="button-group">
                    <input type="submit" name="submit" value="Import Voice Sample" class="action-button upload-csv">
                </div>
            </form>

            <div style="margin-top: 20px; padding: 15px; background: rgba(74, 138, 182, 0.1); border: 1px solid #4a8ab6; border-radius: 6px;">
                <p style="margin: 0 0 10px 0; font-weight: 600; color: #4a8ab6;">File Requirements:</p>
                <ul style="margin: 0; padding-left: 20px;">
                    <li>Format: WAV voice reference sample</li>
                    <li>Filename becomes the VoiceID, such as <code>femalenord.wav</code></li>
                    <li>No transcript file is required; local STT creates the reference text</li>
                </ul>
            </div>
        </div>

        <div class="content-section full-width-section">
            <h1>OmniVoice Voice Library</h1>
            <p>Manage voices for the selected language library: <code><?php echo htmlspecialchars($activeOmniVoiceLanguage); ?></code>.</p>

            <div class="button-group" style="margin-top: 20px;">
                <form action="<?php echo $webRoot; ?>/ui/xtts_clone.php?tab=omnivoice&language=<?php echo urlencode($activeOmniVoiceLanguage); ?>" method="post" style="display: inline;">
                    <input type="hidden" name="language" value="<?php echo htmlspecialchars($activeOmniVoiceLanguage); ?>">
                    <input type="hidden" name="get_speakers" value="1">
                    <input type="submit" value="Refresh OmniVoice Voices" class="action-button download-csv">
                </form>
            </div>

            <div class="voice-status-grid" style="margin-top: 20px;">
                <?php foreach ($omnivoiceDisplayVoices as $voice): ?>
                    <?php
                    $item = $omnivoiceItemMap[$voice] ?? [];
                    $isReady = isset($omnivoiceReadyVoices[$voice]);
                    $hasLocalSample = isset($localVoiceMap[$voice]);
                    $canDeleteProviderVoice = !empty($item['can_delete']) || !empty($item['custom_voice']);
                    $serverStatus = strtolower(trim(strval($item['status'] ?? ($isReady ? 'ready' : 'local'))));
                    $statusTitle = $serverStatus;
                    if (!$isReady && !empty($item['transcription_error'])) {
                        $statusTitle .= ': ' . trim(strval($item['transcription_error']));
                    }
                    ?>
                    <div class="voice-status-item" onclick="copyToClipboard('<?php echo htmlspecialchars($voice, ENT_QUOTES); ?>')" title="Click to copy voice name">
                        <span class="voice-name"><?php echo htmlspecialchars($voice); ?></span>
                        <span class="status-icon <?php echo $isReady ? 'cloned' : 'not-cloned'; ?>" title="<?php echo htmlspecialchars($statusTitle); ?>">
                            <?php echo $isReady ? '✓' : '✗'; ?>
                        </span>
                        <?php if ($isReady && !$hasLocalSample): ?>
                            <span class="voice-id" title="Available in the OmniVoice language library only">server</span>
                        <?php elseif (!$isReady && $hasLocalSample): ?>
                            <span class="voice-id" title="Local sample can be imported or retried">local</span>
                        <?php elseif (!$isReady && isset($omnivoiceAllVoices[$voice])): ?>
                            <span class="voice-id" title="<?php echo htmlspecialchars($statusTitle); ?>">needs text</span>
                        <?php endif; ?>
                        <div class="button-container">
                            <?php if ($isReady): ?>
                                <button onclick="event.stopPropagation(); testVoice('<?php echo htmlspecialchars($voice, ENT_QUOTES); ?>')"
                                        class="play-btn" title="Test voice">▶</button>
                            <?php elseif ($hasLocalSample): ?>
                                <button onclick="event.stopPropagation(); syncSingleVoice('omnivoice', '<?php echo htmlspecialchars($voice, ENT_QUOTES); ?>')"
                                        class="sync-btn" title="Import this voice into OmniVoice">↻</button>
                            <?php endif; ?>
                            <?php if ($canDeleteProviderVoice): ?>
                                <button onclick="event.stopPropagation(); deleteProviderVoice('omnivoice', '<?php echo htmlspecialchars($voice, ENT_QUOTES); ?>')"
                                        class="delete-provider-btn" title="Remove custom voice from OmniVoice">×</button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($omnivoiceDisplayVoices)): ?>
                    <p>No OmniVoice voices were found for this language, and no local voice files exist in <code>data/voices</code>.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="content-section full-width-section">
            <h1>Batch Import Missing Voices</h1>
            <p>Import missing local voice samples into the selected OmniVoice language library.</p>

            <?php if (count($missingOmniVoiceVoices) > 0): ?>
                <p style="color: #4a8ab6;">Found <?php echo count($missingOmniVoiceVoices); ?> voice(s) not yet ready in OmniVoice for <code><?php echo htmlspecialchars($activeOmniVoiceLanguage); ?></code>.</p>
                <div class="button-group">
                    <button onclick="startBatchUpload('omnivoice', <?php echo htmlspecialchars(json_encode($missingOmniVoiceVoices)); ?>)" class="action-button upload-csv">
                        Batch Import Missing Voices (<?php echo count($missingOmniVoiceVoices); ?>)
                    </button>
                    <button onclick="cancelBatchUpload()" id="cancel-batch-omnivoice" class="action-button delete" style="display:none;">Cancel</button>
                </div>

                <div id="batch-progress-omnivoice" style="display:none; margin-top: 20px; padding: 15px; background: #2c2c2c; border: 1px solid #4a4a4a; border-radius: 5px;">
                    <div style="margin-bottom: 10px;">
                        <strong>Progress: <span id="batch-current-omnivoice">0</span> / <span id="batch-total-omnivoice">0</span></strong>
                        <span id="batch-eta-omnivoice" style="margin-left: 15px; color: #aaa;"></span>
                    </div>
                    <div style="background: #1a1a1a; height: 30px; border-radius: 4px; overflow: hidden; margin-bottom: 15px;">
                        <div id="batch-progress-bar-omnivoice" style="background: rgb(242, 124, 17); height: 100%; width: 0%; transition: width 0.3s;"></div>
                    </div>
                    <div id="batch-status-omnivoice" style="max-height: 300px; overflow-y: auto;">
                        <!-- Status messages will appear here -->
                    </div>
                </div>
            <?php else: ?>
                <p style="color: #4caf50;">✓ All local voices are ready in OmniVoice for this language.</p>
            <?php endif; ?>
        </div>

        <div class="content-section full-width-section">
            <h1>OmniVoice Service</h1>
            <p>Advanced OmniVoice configuration: <a href="<?php echo htmlspecialchars($xttsStudioEndpoints['omnivoice']); ?>/docs" style="color: yellow;" target="_blank"><?php echo htmlspecialchars($xttsStudioEndpoints['omnivoice']); ?>/docs</a></p>
        </div>
    </div>

    <!-- Cartesia Tab Content -->
    <div class="tab-content <?php echo $activeTab === 'cartesia' ? 'active' : ''; ?>">
        <div class="content-section full-width-section">
            <h1>Voice Sample Upload</h1>
            <p>Upload voice samples to <code>data/voices</code>. Files will be available for generating voices in Cartesia.</p>

            <form action="<?php echo $webRoot; ?>/ui/xtts_clone.php?tab=cartesia" method="post" enctype="multipart/form-data" style="margin-top: 20px;" onsubmit="showLoadingMessage('Uploading voice files for Cartesia, please wait...');">
                <div style="margin-bottom: 15px;">
                    <label for="file_cartesia" style="display: block; margin-bottom: 8px;">Select .wav file(s) or .zip archive to upload:</label>
                    <input type="file" name="file[]" id="file_cartesia" accept=".wav,.zip" multiple="multiple" required style="width: 100%; max-width: 500px; padding: 8px;">
                </div>
                <div class="button-group">
                    <input type="submit" name="submit_cartesia" value="Upload Voice Sample" class="action-button upload-csv">
                </div>
            </form>

            <div style="margin-top: 20px; padding: 15px; background: rgba(74, 138, 182, 0.1); border: 1px solid #4a8ab6; border-radius: 6px;">
                <p style="margin: 0 0 10px 0; font-weight: 600; color: #4a8ab6;">📋 File Requirements:</p>
                <ul style="margin: 0; padding-left: 20px;">
                    <li>Format: WAV (PCM), 16-bit, Mono, 20500Hz</li>
                    <li>Size: 5MB or less</li>
                    <li>Filename: lowercase with underscores (e.g., "mjoll_the_lioness.wav")</li>
                </ul>
                <p style="margin: 10px 0 0 0; font-size: 0.9em; color: #aaa;"><b>Note:</b> Files will be saved to data/voices and automatically generated for Cartesia.</p>
            </div>
        </div>

        <div class="content-section full-width-section">
            <h1>Cartesia Voice Cacje</h1>
            <p>Manage voice generation for Cartesia TTS. Voices are generated from local .wav files in <code>data/voices</code>.</p>
            <p>For detailed information, see our <a href="https://dwemerdynamics.com/chim/tts.html#Cartesia" style="color: yellow;" target="_blank" rel="noopener noreferrer">Cartesia TTS Guide</a>.</p>

            <?php
            $cartesiaConfigured = isProviderConfigured('cartesia');
            $localVoices = getLocalVoices();
            $clonedVoices = getClonedVoices('cartesia');
            $missingVoices = array_diff($localVoices, array_keys($clonedVoices));
            ?>

            <?php if (!$cartesiaConfigured): ?>
                <div style="background: rgba(244, 67, 54, 0.1); border: 2px solid #f44336; border-radius: 8px; padding: 16px; margin: 20px 0; color: #f8f9fa;">
                    <p style="margin: 0; font-weight: 600; color: #f44336;">⚠️ Cartesia API not configured</p>
                    <p style="margin: 8px 0 0 0;">Please configure your Cartesia API key in the <a href="<?php echo $webRoot; ?>/ui/core/api_badge.php" style="color: yellow;">API Badge</a> page before syncing voices.</p>
                </div>
            <?php endif; ?>

            <div style="background: rgba(74, 138, 182, 0.1); border: 2px solid #4a8ab6; border-radius: 8px; padding: 16px; margin: 20px 0; color: #f8f9fa;">
                <p style="margin: 0; font-weight: 600; color: #4a8ab6;">ℹ️ Automatic Voice Generation</p>
                <p style="margin: 8px 0 0 0;">Voices are automatically generated when you speak to an NPC using that voice in-game, if Cartesia TTS is selected as your TTS provider. You don't need to manually sync all voices upfront.</p>
            </div>

            <div class="voice-status-grid">
                <?php foreach ($localVoices as $voice): ?>
                    <?php
                    $isCloned = isset($clonedVoices[$voice]);
                    $cartesiaMetadata = $isCloned ? getCartesiaVoiceMetadata($voice) : [];
                    $cartesiaManaged = !empty($cartesiaMetadata['managed'])
                        && trim(strval($cartesiaMetadata['voice_id'] ?? '')) === strval($clonedVoices[$voice] ?? '');
                    ?>
                    <div class="voice-status-item" onclick="copyToClipboard('<?php echo htmlspecialchars($voice, ENT_QUOTES); ?>')" title="Click to copy voice name">
                        <span class="voice-name"><?php echo htmlspecialchars($voice); ?></span>
                        <span class="status-icon <?php echo $isCloned ? 'cloned' : 'not-cloned'; ?>">
                            <?php echo $isCloned ? '✓' : '✗'; ?>
                        </span>
                        <?php if ($isCloned): ?>
                            <span class="voice-id" title="<?php echo htmlspecialchars($clonedVoices[$voice]); ?>">
                                <?php echo htmlspecialchars(substr($clonedVoices[$voice], 0, 15)) . (strlen($clonedVoices[$voice]) > 15 ? '...' : ''); ?>
                            </span>
                        <?php endif; ?>
                        <div class="button-container">
                            <?php if ($isCloned): ?>
                                <button onclick="event.stopPropagation(); testCartesiaVoice('<?php echo htmlspecialchars($voice, ENT_QUOTES); ?>')"
                                        class="play-btn" title="Test voice">▶</button>
                                <button onclick="event.stopPropagation(); manageCloudVoice('cartesia', '<?php echo htmlspecialchars($voice, ENT_QUOTES); ?>', 'unsync')"
                                        class="unsync-btn" title="Forget cached Cartesia voice ID">×</button>
                                <button onclick="event.stopPropagation(); manageCloudVoice('cartesia', '<?php echo htmlspecialchars($voice, ENT_QUOTES); ?>', 'resync')"
                                        class="resync-btn" title="Clone again from the local sample, validate it, and switch IDs" <?php echo !$cartesiaConfigured ? 'disabled' : ''; ?>>↻</button>
                                <?php if ($cartesiaManaged): ?>
                                    <button onclick="event.stopPropagation(); manageCloudVoice('cartesia', '<?php echo htmlspecialchars($voice, ENT_QUOTES); ?>', 'delete')"
                                            class="delete-provider-btn" title="Delete this managed Cartesia cloud clone">🗑</button>
                                <?php endif; ?>
                            <?php else: ?>
                                <button onclick="event.stopPropagation(); syncSingleVoice('cartesia', '<?php echo htmlspecialchars($voice, ENT_QUOTES); ?>')"
                                        class="sync-btn" title="Sync this voice" <?php echo !$cartesiaConfigured ? 'disabled' : ''; ?>>↻</button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($localVoices)): ?>
                    <p>No voice files found in <code>data/voices</code>. Upload voice samples in the XTTS tab first.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="content-section full-width-section">
            <h1>Batch Generate Missing Voices</h1>
            <p>Generate voice clones for Cartesia from uploaded files. Rate limited to avoid API errors (2 second delay between requests).</p>

            <?php
            $localVoices = getLocalVoices();
            $clonedVoices = getClonedVoices('cartesia');
            $missingCartesiaVoices = array_diff($localVoices, array_keys($clonedVoices));
            ?>

            <?php if (count($missingCartesiaVoices) > 0): ?>
                <p style="color: #4a8ab6;">Found <?php echo count($missingCartesiaVoices); ?> voice(s) not yet generated for Cartesia.</p>
                <p style="color: #aaa; font-size: 0.9em;">Estimated time: ~<?php echo ceil(count($missingCartesiaVoices) * 2 / 60); ?> minute(s)</p>
                <div class="button-group">
                    <button onclick="startBatchUpload('cartesia', <?php echo htmlspecialchars(json_encode(array_values($missingCartesiaVoices))); ?>)" class="action-button upload-csv" <?php echo !$cartesiaConfigured ? 'disabled title="Configure Cartesia API first"' : ''; ?>>
                        Batch Generate Missing Voices (<?php echo count($missingCartesiaVoices); ?>)
                    </button>
                    <button onclick="cancelBatchUpload()" id="cancel-batch-cartesia" class="action-button delete" style="display:none;">Cancel</button>
                </div>

                <div id="batch-progress-cartesia" style="display:none; margin-top: 20px; padding: 15px; background: #2c2c2c; border: 1px solid #4a4a4a; border-radius: 5px;">
                    <div style="margin-bottom: 10px;">
                        <strong>Progress: <span id="batch-current-cartesia">0</span> / <span id="batch-total-cartesia">0</span></strong>
                        <span id="batch-eta-cartesia" style="margin-left: 15px; color: #aaa;"></span>
                    </div>
                    <div style="background: #1a1a1a; height: 30px; border-radius: 4px; overflow: hidden; margin-bottom: 15px;">
                        <div id="batch-progress-bar-cartesia" style="background: rgb(242, 124, 17); height: 100%; width: 0%; transition: width 0.3s;"></div>
                    </div>
                    <div id="batch-status-cartesia" style="max-height: 300px; overflow-y: auto;">
                        <!-- Status messages will appear here -->
                    </div>
                </div>
            <?php else: ?>
                <p style="color: #4caf50;">✓ All local voices have been generated for Cartesia.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Inworld Tab Content -->
    <div class="tab-content <?php echo $activeTab === 'inworld' ? 'active' : ''; ?>">
        <div class="content-section full-width-section">
            <h1>Voice Sample Upload</h1>
            <p>Upload voice samples to <code>data/voices</code>. Files will be available for generating voices in Inworld.</p>

            <form action="<?php echo $webRoot; ?>/ui/xtts_clone.php?tab=inworld" method="post" enctype="multipart/form-data" style="margin-top: 20px;" onsubmit="showLoadingMessage('Uploading voice files for Inworld, please wait...');">
                <div style="margin-bottom: 15px;">
                    <label for="file_inworld" style="display: block; margin-bottom: 8px;">Select .wav file(s) or .zip archive to upload:</label>
                    <input type="file" name="file[]" id="file_inworld" accept=".wav,.zip" multiple="multiple" required style="width: 100%; max-width: 500px; padding: 8px;">
                </div>
                <div class="button-group">
                    <input type="submit" name="submit_inworld" value="Upload Voice Sample" class="action-button upload-csv">
                </div>
            </form>

            <div style="margin-top: 20px; padding: 15px; background: rgba(74, 138, 182, 0.1); border: 1px solid #4a8ab6; border-radius: 6px;">
                <p style="margin: 0 0 10px 0; font-weight: 600; color: #4a8ab6;">📋 File Requirements:</p>
                <ul style="margin: 0; padding-left: 20px;">
                    <li>Format: WAV (PCM), 16-bit, Mono, 20500Hz</li>
                    <li>Size: 5MB or less</li>
                    <li>Filename: lowercase with underscores (e.g., "mjoll_the_lioness.wav")</li>
                </ul>
                <p style="margin: 10px 0 0 0; font-size: 0.9em; color: #aaa;"><b>Note:</b> Files will be saved to data/voices and automatically generated for Inworld.</p>
            </div>
        </div>

        <div class="content-section full-width-section">
            <h1>Inworld Voice Cache</h1>
            <p>Manage voice generation for Inworld TTS. Voices are generated from local .wav files in <code>data/voices</code>.</p>
            <p>For detailed information, see our <a href="https://dwemerdynamics.com/chim/tts.html#Inworld" style="color: yellow;" target="_blank" rel="noopener noreferrer">Inworld TTS Guide</a>.</p>

            <?php
            $inworldStatus = getInworldConfigurationStatus();
            $inworldConfigured = $inworldStatus['configured'];
            $localVoices = getLocalVoices();
            $clonedVoices = getClonedVoices('inworld');
            $missingVoices = array_diff($localVoices, array_keys($clonedVoices));
            ?>

            <?php if (!$inworldConfigured): ?>
                <div style="background: rgba(244, 67, 54, 0.1); border: 2px solid #f44336; border-radius: 8px; padding: 16px; margin: 20px 0; color: #f8f9fa;">
                    <p style="margin: 0; font-weight: 600; color: #f44336;">⚠️ Inworld is not fully configured</p>
                    <p style="margin: 8px 0 0 0;"><?php echo htmlspecialchars($inworldStatus['message']); ?> Set the API credential in <a href="<?php echo $webRoot; ?>/ui/core/api_badge.php" style="color: yellow;">API Badge</a> and the workspace on your active Inworld TTS connector.</p>
                </div>
            <?php endif; ?>

            <div style="background: rgba(74, 138, 182, 0.1); border: 2px solid #4a8ab6; border-radius: 8px; padding: 16px; margin: 20px 0; color: #f8f9fa;">
                <p style="margin: 0; font-weight: 600; color: #4a8ab6;">ℹ️ Automatic Voice Generation</p>
                <p style="margin: 8px 0 0 0;">Voices are automatically generated when you speak to an NPC using that voice in-game, if Inworld TTS is selected as your TTS provider. You don't need to manually sync all voices upfront.</p>
            </div>

            <div class="voice-status-grid">
                <?php foreach ($localVoices as $voice): ?>
                    <?php
                    $isCloned = isset($clonedVoices[$voice]);
                    $inworldMetadata = $isCloned ? getInworldVoiceMetadata($voice) : [];
                    $inworldManaged = !empty($inworldMetadata['managed'])
                        && trim(strval($inworldMetadata['voice_id'] ?? '')) === strval($clonedVoices[$voice] ?? '');
                    ?>
                    <div class="voice-status-item" onclick="copyToClipboard('<?php echo htmlspecialchars($voice, ENT_QUOTES); ?>')" title="Click to copy voice name">
                        <span class="voice-name"><?php echo htmlspecialchars($voice); ?></span>
                        <span class="status-icon <?php echo $isCloned ? 'cloned' : 'not-cloned'; ?>">
                            <?php echo $isCloned ? '✓' : '✗'; ?>
                        </span>
                        <?php if ($isCloned): ?>
                            <span class="voice-id" title="<?php echo htmlspecialchars($clonedVoices[$voice]); ?>">
                                <?php echo htmlspecialchars(substr($clonedVoices[$voice], 0, 15)) . (strlen($clonedVoices[$voice]) > 15 ? '...' : ''); ?>
                            </span>
                        <?php endif; ?>
                        <div class="button-container">
                            <?php if ($isCloned): ?>
                                <button onclick="event.stopPropagation(); testInworldVoice('<?php echo htmlspecialchars($voice, ENT_QUOTES); ?>')"
                                        class="play-btn" title="Test voice">▶</button>
                                <button onclick="event.stopPropagation(); manageCloudVoice('inworld', '<?php echo htmlspecialchars($voice, ENT_QUOTES); ?>', 'unsync')"
                                        class="unsync-btn" title="Forget cached Inworld voice ID">×</button>
                                <button onclick="event.stopPropagation(); manageCloudVoice('inworld', '<?php echo htmlspecialchars($voice, ENT_QUOTES); ?>', 'resync')"
                                        class="resync-btn" title="Clone again from the local sample, validate it, and switch IDs" <?php echo !$inworldConfigured ? 'disabled' : ''; ?>>↻</button>
                                <?php if ($inworldManaged): ?>
                                    <button onclick="event.stopPropagation(); manageCloudVoice('inworld', '<?php echo htmlspecialchars($voice, ENT_QUOTES); ?>', 'delete')"
                                            class="delete-provider-btn" title="Delete this managed Inworld cloud clone">🗑</button>
                                <?php endif; ?>
                            <?php else: ?>
                                <button onclick="event.stopPropagation(); syncSingleVoice('inworld', '<?php echo htmlspecialchars($voice, ENT_QUOTES); ?>')"
                                        class="sync-btn" title="Sync this voice" <?php echo !$inworldConfigured ? 'disabled' : ''; ?>>↻</button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($localVoices)): ?>
                    <p>No voice files found in <code>data/voices</code>. Upload voice samples in the XTTS tab first.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="content-section full-width-section">
            <h1>Batch Generate Missing Voices</h1>
            <p>Generate voice clones for Inworld from uploaded files. Rate limited to avoid API errors (3 second delay between requests).</p>

            <?php
            $localVoices = getLocalVoices();
            $clonedVoices = getClonedVoices('inworld');
            $missingInworldVoices = array_diff($localVoices, array_keys($clonedVoices));
            ?>

            <?php if (count($missingInworldVoices) > 0): ?>
                <p style="color: #4a8ab6;">Found <?php echo count($missingInworldVoices); ?> voice(s) not yet generated for Inworld.</p>
                <p style="color: #aaa; font-size: 0.9em;">Estimated time: ~<?php echo ceil(count($missingInworldVoices) * 3 / 60); ?> minute(s)</p>
                <div class="button-group">
                    <button onclick="startBatchUpload('inworld', <?php echo htmlspecialchars(json_encode(array_values($missingInworldVoices))); ?>)" class="action-button upload-csv" <?php echo !$inworldConfigured ? 'disabled title="Configure Inworld API first"' : ''; ?>>
                        Batch Generate Missing Voices (<?php echo count($missingInworldVoices); ?>)
                    </button>
                    <button onclick="cancelBatchUpload()" id="cancel-batch-inworld" class="action-button delete" style="display:none;">Cancel</button>
                </div>

                <div id="batch-progress-inworld" style="display:none; margin-top: 20px; padding: 15px; background: #2c2c2c; border: 1px solid #4a4a4a; border-radius: 5px;">
                    <div style="margin-bottom: 10px;">
                        <strong>Progress: <span id="batch-current-inworld">0</span> / <span id="batch-total-inworld">0</span></strong>
                        <span id="batch-eta-inworld" style="margin-left: 15px; color: #aaa;"></span>
                    </div>
                    <div style="background: #1a1a1a; height: 30px; border-radius: 4px; overflow: hidden; margin-bottom: 15px;">
                        <div id="batch-progress-bar-inworld" style="background: rgb(242, 124, 17); height: 100%; width: 0%; transition: width 0.3s;"></div>
                    </div>
                    <div id="batch-status-inworld" style="max-height: 300px; overflow-y: auto;">
                        <!-- Status messages will appear here -->
                    </div>
                </div>
            <?php else: ?>
                <p style="color: #4caf50;">✓ All local voices have been generated for Inworld.</p>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php
include(__DIR__.DIRECTORY_SEPARATOR."tmpl/footer.html");

$buffer = ob_get_contents();
ob_end_clean();
$title = $TITLE;
$buffer = preg_replace('/(<title>)(.*?)(<\/title>)/i', '$1' . $title . '$3', $buffer);
echo $buffer;
?>
