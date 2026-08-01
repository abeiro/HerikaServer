<?php

/* Voice Sample Extractor */

$path = dirname((__FILE__)) . DIRECTORY_SEPARATOR;
$GLOBALS["ENGINE_PATH"]=$path;

require_once $path . "lib/runtime_bootstrap.php";
chimRuntimeBootstrap($path, [
    'load_general_settings' => true,
    'load_stt_connector' => false,
    'load_itt_connector' => false,
    'load_tts_connector' => 'pockettts',
    'load_player_name' => true,
]);
require_once $path . "lib/utils.php";
require_once $path . "lib/fuz_convert.php"; // API KEY must be there
require_once $path . "lib/auditing.php";
require_once $path . "lib/logger.php";
require_once $path . "lib/voice_sample_metadata.php";

$db = $GLOBALS["db"] ?? new sql();
$GLOBALS["db"] = $db;

require_once $path . "lib/core/npc_master.class.php";
require_once $path . "lib/core/api_badge.class.php";
require_once $path . "lib/core/core_profiles.class.php";
require_once $path . "lib/core/llm_connector.class.php";
require_once $path . "lib/core/tts_connector.class.php";
require_once $path . "lib/semaphore_manager.class.php";

function chimVsxRespond(int $statusCode, bool $ok, string $message = '', array $extra = []): void
{
    if (!headers_sent()) {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
    }

    $payload = array_merge([
        'schema' => 'chim.voice_sample.response.v1',
        'request_id' => strval($GLOBALS['AUDIT_RUNID'] ?? ''),
        'ok' => $ok,
    ], $extra);
    if ($message !== '') {
        $payload[$ok ? 'message' : 'error'] = $message;
    }

    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit;
}

function normalize_endpoint_url($url)
{
    // Remove trailing slashes
    $url = rtrim($url, '/');
    return $url;
}

function chimVsxResolveCloneTtsRuntime(string $actorName): array
{
    $ttsConnector = new TTSConnector();
    $supportedCloneDrivers = ['xtts-fastapi', 'chatterbox', 'pockettts', 'inworld'];

    $fallbackDriver = $ttsConnector->normalizeDriverValue($GLOBALS["TTSFUNCTION"] ?? 'pockettts');
    if ($fallbackDriver === '') {
        $fallbackDriver = 'pockettts';
    }

    $selectedDriver = $fallbackDriver;
    $profileData = null;

    if ($actorName !== '') {
        $profile = new CoreProfile();

        if (strcasecmp($actorName, 'The Narrator') === 0) {
            require_once $GLOBALS["ENGINE_PATH"] . "lib/core/narrator.class.php";
            $narrator = new Narrator();
            $profileId = intval($narrator->getProfileId() ?? 0);
            if ($profileId > 0) {
                $profileData = $profile->getById($profileId);
            }
        } else {
            $npcMaster = new NpcMaster();
            $currentNpcData = $npcMaster->getByName($actorName);
            if ($currentNpcData) {
                $profileId = intval($currentNpcData['profile_id'] ?? 0);
                if ($profileId > 0) {
                    $profileData = $profile->getById($profileId);
                } else {
                    $profileData = $profile->getDefaultNpc();
                }
            }
        }

        if ($profileData) {
            $profileConnectorRow = $ttsConnector->ensureConnectorForProfile($profileData);
            $profileDriver = $ttsConnector->normalizeDriverValue($profileConnectorRow['driver'] ?? '');
            if ($profileConnectorRow && in_array($profileDriver, $supportedCloneDrivers, true)) {
                $GLOBALS["CHIM_CORE_CURRENT_PROFILE_DATA"] = $profileData;
                $profile->setOldGlobals($profileData);
                $selectedDriver = $profileDriver;
            } elseif ($profileConnectorRow) {
                Logger::info("[vsx] Actor '{$actorName}' uses non-clone TTS driver '{$profileDriver}', falling back to {$fallbackDriver}");
            }
        }
    }

    $providerKey = $ttsConnector->getProviderKeyFromDriver($selectedDriver);
    $providerConfig = ($providerKey !== '' && isset($GLOBALS["TTS"][$providerKey]) && is_array($GLOBALS["TTS"][$providerKey]))
        ? $GLOBALS["TTS"][$providerKey]
        : [];

    $endpoint = trim(strval($providerConfig['endpoint'] ?? $providerConfig['url'] ?? $providerConfig['URL'] ?? ''));
    if ($endpoint === '' && $selectedDriver !== $fallbackDriver && $selectedDriver !== 'inworld') {
        $fallbackProviderKey = $ttsConnector->getProviderKeyFromDriver($fallbackDriver);
        $fallbackConfig = ($fallbackProviderKey !== '' && isset($GLOBALS["TTS"][$fallbackProviderKey]) && is_array($GLOBALS["TTS"][$fallbackProviderKey]))
            ? $GLOBALS["TTS"][$fallbackProviderKey]
            : [];
        $endpoint = trim(strval($fallbackConfig['endpoint'] ?? $fallbackConfig['url'] ?? $fallbackConfig['URL'] ?? ''));
        $providerKey = $fallbackProviderKey;
        $providerConfig = $fallbackConfig;
        $selectedDriver = $fallbackDriver;
    }

    $voicelogic = trim(strval($providerConfig['voicelogic'] ?? ''));
    if ($voicelogic === '') {
        $voicelogic = 'voicetype';
    }

    return [
        'driver' => $selectedDriver,
        'provider_key' => $providerKey,
        'endpoint' => ($endpoint !== '') ? normalize_endpoint_url($endpoint) : '',
        'voicelogic' => $voicelogic,
    ];
}

$GLOBALS["AUDIT_RUNID_REQUEST"] = "vsx";

try {
    $voiceSampleMetadata = chim_voice_sample_decode_metadata($_POST, $_GET);
} catch (InvalidArgumentException $e) {
    chimVsxRespond(400, false, $e->getMessage());
}

$actorName = $voiceSampleMetadata['actor_name'];
$sourcePath = $voiceSampleMetadata['original_name'];
$vsxTtsRuntime = chimVsxResolveCloneTtsRuntime($actorName);
$voicelogic = $vsxTtsRuntime['voicelogic'];
$ttsEndpoint = $vsxTtsRuntime['endpoint'];
Logger::info("[vsx] Using clone driver '{$vsxTtsRuntime['driver']}' for actor '{$actorName}' with endpoint '{$ttsEndpoint}' protocol='{$voiceSampleMetadata['protocol']}'");

// Lock
$semaphore_timeout = $GLOBALS["SEMAPHORES_TIMEOUT"] ?? 300;
if (!SemaphoreWait("VSX", $semaphore_timeout, 47, null)) {
    Logger::warn("[vsx] semaphore wait failed in " . __FILE__ . " " . __LINE__);
    chimVsxRespond(503, false, 'Voice sample service is busy');
}

$databaseError = null;
try {
    $actorCodename = npcNameToCodename($actorName);
    $db->upsertRowTrx(
        'conf_opts',
        [
            'value' => $sourcePath,
            'id' => "Nametype/$actorCodename",
        ],
        ['id' => "Nametype/$actorCodename"]
    );

    $sourceParts = preg_split('/[\\\\\/]+/', $sourcePath);
    $sourceVoiceId = (is_array($sourceParts) && count($sourceParts) >= 4)
        ? strtolower(trim(strval($sourceParts[3])))
        : '';
    $codename = preg_replace('/[^a-z0-9_-]/i', '', $sourceVoiceId) ?: '';
    if ($codename === '') {
        $codename = preg_replace('/[^a-z0-9_-]/i', '', strtolower($actorCodename)) ?: '';
    }
    if ($codename === '') {
        throw new RuntimeException('Unable to determine voice sample identifier');
    }

    $db->upsertRowTrx(
        'conf_opts',
        [
            'value' => $sourcePath,
            'id' => "Voicetype/$codename",
        ],
        ['id' => "Voicetype/$codename"]
    );

    $npcMaster      = new NpcMaster();
    $currentNpcData = $npcMaster->getByName($actorName);
    if ($currentNpcData) {
        if (empty($currentNpcData["voiceid"])) {
            $currentNpcData["voiceid"] = $codename;
        }

        $extended = $npcMaster->getExtendedData($currentNpcData);
        unset($extended["voice_refresh_requested_at"]);
        $extended["voice_refresh_last_result"] = "sample_uploaded";
        $extended["voice_refresh_last_resolved_at"] = time();
        $extended["voice_sample_source"] = $sourcePath;
        $extended["voice_sample_reference_text"] = $voiceSampleMetadata['reference_text'];
        $currentNpcData = $npcMaster->setExtendedData($currentNpcData, $extended);
        $currentNpcData = $npcMaster->updateByArray($currentNpcData);
    }
} catch (Throwable $e) {
    $databaseError = $e;
} finally {
    $db->close();
    SemaphoreManager::release("VSX");
}

if ($databaseError !== null) {
    Logger::error('[vsx] Failed to store voice metadata: ' . $databaseError->getMessage());
    chimVsxRespond(500, false, 'Failed to store voice sample metadata');
}
audit_log("vsx.php data available for $codename");

$ext = strtolower(pathinfo(str_replace('\\', '/', $sourcePath), PATHINFO_EXTENSION));
if (!in_array($ext, ['fuz', 'xwm', 'wav'], true)) {
    chimVsxRespond(400, false, 'Unsupported voice sample extension', ['extension' => $ext]);
}

if (empty($_FILES['file']['tmp_name']) || !is_file($_FILES['file']['tmp_name'])) {
    chimVsxRespond(400, false, 'No voice sample uploaded');
}
if (filesize($_FILES['file']['tmp_name']) <= 0) {
    Logger::error("Empty file {$_FILES['file']['tmp_name']}");
    chimVsxRespond(400, false, 'Uploaded voice sample was empty');
}

$finalName = __DIR__ . DIRECTORY_SEPARATOR . "soundcache/_vsx_" . md5($_FILES["file"]["tmp_name"]) . ".$ext";
if (!@copy($_FILES['file']['tmp_name'], $finalName)) {
    chimVsxRespond(500, false, 'Failed to stage uploaded voice sample');
}
Logger::info("Received sample: {$sourcePath}");

$finalFile = match ($ext) {
    'fuz' => fuzToWav($finalName),
    'xwm' => xwmToWav($finalName),
    'wav' => wavToWav($finalName),
};
if (empty($finalFile) || !is_file($finalFile) || filesize($finalFile) <= 44) {
    Logger::error("[vsx] Failed to create converted voice sample for {$codename} from {$sourcePath}");
    chimVsxRespond(500, false, 'Voice sample conversion failed', ['codename' => $codename]);
}

$voiceCacheFile = $path . "data/voices/$codename.wav";
$cacheAlreadyAvailable = is_file($voiceCacheFile) && filesize($voiceCacheFile) > 44;
if (!chim_voice_sample_replace_file($finalFile, $voiceCacheFile)) {
    Logger::error("[vsx] Failed to cache converted voice sample at {$voiceCacheFile}");
    chimVsxRespond(500, false, 'Voice sample cache copy failed', ['codename' => $codename]);
}
if (!chim_voice_sample_write_metadata($voiceCacheFile, $codename, $voiceSampleMetadata)) {
    Logger::error("[vsx] Failed to write voice sample metadata for {$codename}");
    chimVsxRespond(500, false, 'Voice sample metadata write failed', ['codename' => $codename]);
}
Logger::info("[vsx] Cached normalized voice sample {$codename}.wav sha256=" . hash_file('sha256', $voiceCacheFile));

$syncAttempted = false;
$syncSucceeded = null;
if ($ttsEndpoint !== '') {
    $syncAttempted = true;
    $url  = rtrim($ttsEndpoint, '/') . '/upload_sample';
    $curl = curl_init();

    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => [
            'wavFile' => new CURLFile($voiceCacheFile, 'audio/wav', "$codename.wav"),
        ],
    ]);

    $response = curl_exec($curl);
    $syncSucceeded = $response !== false;
    if (!$syncSucceeded) {
        Logger::warn("[vsx] Voice provider sync failed for {$codename}: " . curl_error($curl));
    }
    curl_close($curl);
} else {
    Logger::info("[vsx] Cached {$codename}.wav locally for '{$vsxTtsRuntime['driver']}'; no immediate clone endpoint required");
}

audit_log("vsx.php voice available for {$actorName}");
chimVsxRespond(200, true, 'Voice sample uploaded', [
    'codename' => $codename,
    'driver' => $vsxTtsRuntime['driver'] ?? '',
    'already_available' => $cacheAlreadyAvailable,
    'cached_path' => "data/voices/$codename.wav",
    'metadata_path' => "data/voices/$codename.json",
    'provider_sync_attempted' => $syncAttempted,
    'provider_sync_succeeded' => $syncSucceeded,
]);
