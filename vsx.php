<?php

/* Voice Sample Extractor */

$path = dirname((__FILE__)) . DIRECTORY_SEPARATOR;
$GLOBALS["ENGINE_PATH"]=$path;

require_once $path . "lib/runtime_bootstrap.php";
chimRuntimeBootstrap($path, [
    'load_general_settings' => true,
    'load_stt_connector' => false,
    'load_itt_connector' => false,
    'load_tts_connector' => 'xtts-fastapi',
    'load_player_name' => true,
]);
require_once $path . "lib/utils.php";
require_once $path . "lib/fuz_convert.php"; // API KEY must be there
require_once $path . "lib/auditing.php";
require_once $path . "lib/logger.php";

$db = $GLOBALS["db"] ?? new sql();
$GLOBALS["db"] = $db;

require_once $path . "lib/core/npc_master.class.php";
require_once $path . "lib/core/api_badge.class.php";
require_once $path . "lib/core/core_profiles.class.php";
require_once $path . "lib/core/llm_connector.class.php";
require_once $path . "lib/core/tts_connector.class.php";
require_once $path . "lib/semaphore_manager.class.php";

function normalize_endpoint_url($url)
{
    // Remove trailing slashes
    $url = rtrim($url, '/');
    return $url;
}

function chimVsxResolveCloneTtsRuntime(string $actorName): array
{
    $ttsConnector = new TTSConnector();
    $supportedCloneDrivers = ['xtts-fastapi', 'chatterbox', 'pockettts'];

    $fallbackDriver = $ttsConnector->normalizeDriverValue($GLOBALS["TTSFUNCTION"] ?? 'xtts-fastapi');
    if ($fallbackDriver === '') {
        $fallbackDriver = 'xtts-fastapi';
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
    if ($endpoint === '' && $selectedDriver !== $fallbackDriver) {
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

// Put info into DB asap
$vsxTtsRuntime = chimVsxResolveCloneTtsRuntime(trim(strval($_GET["codename"] ?? '')));
$voicelogic = $vsxTtsRuntime['voicelogic'];
$ttsEndpoint = $vsxTtsRuntime['endpoint'];
Logger::info("[vsx] Using clone driver '{$vsxTtsRuntime['driver']}' for actor '{$_GET["codename"]}' with endpoint '{$ttsEndpoint}'");

// Lock
$semaphore_timeout = $GLOBALS["SEMAPHORES_TIMEOUT"] ?? 300;
if (!SemaphoreWait("VSX", $semaphore_timeout, 47, null)) {
    Logger::warn("[vsx] semaphore wait failed in " . __FILE__ . " " . __LINE__);
    terminate();
}

if ($voicelogic === 'voicetype' || true) { // force 

    //db insert for name entry for data_functions.
    $codename = npcNameToCodename($_GET["codename"]);

    $db->upsertRowTrx(
        'conf_opts',
        [
            'value' => $_GET["oname"],
            "id"    => "Nametype/$codename",
        ],
        ["id" => "Nametype/$codename"]
    );

                                                // new logic so codename is set to voicetype so it generates voicetype sample
    $voicetype = explode("\\", $_GET["oname"]); // Split the path
    $codename  = strtolower($voicetype[3]);     // Use the 4th part of the path
                                                // Delete and insert the database entry

    $db->upsertRowTrx(
        'conf_opts',
        [
            'value' => $_GET["oname"],
            "id"    => "Voicetype/$codename",
        ],
        ["id" => "Voicetype/$codename"]
    );

    $npcMaster      = new NpcMaster();
    $currentNpcData = $npcMaster->getByName($_GET["codename"]);
    if ($currentNpcData) {
        if (empty($currentNpcData["voiceid"])) {
            $currentNpcData["voiceid"] = $codename;
        }

        $extended = $npcMaster->getExtendedData($currentNpcData);
        unset($extended["voice_refresh_requested_at"]);
        $extended["voice_refresh_last_result"] = "sample_uploaded";
        $extended["voice_refresh_last_resolved_at"] = time();
        $currentNpcData = $npcMaster->setExtendedData($currentNpcData, $extended);
        $currentNpcData = $npcMaster->updateByArray($currentNpcData);
    }

    $db->close();

} else {
    $codename = npcNameToCodename($_GET["codename"]);
    // Old name logic

    $db->upsertRowTrx(
        'conf_opts',
        [
            'value' => $_GET["oname"],
            "id"    => "Voicetype/$codename",
        ],
        ["id" => "Voicetype/$codename"]

    );
    $db->close();
}

// Release lock, this is the time consuming part, we have the needed data into the database

audit_log("vsx.php data available for $codename");

SemaphoreManager::release("VSX");

if (strpos($_GET["oname"], ".fuz")) {
    $ext = "fuz";
} else if (strpos($_GET["oname"], ".xwm")) {
    $ext = "xwm";
} else if (strpos($_GET["oname"], ".wav")) {
    $ext = "wav";
}

$already   = ($ttsEndpoint !== '') ? file_exists($ttsEndpoint . "/sample/$codename.wav") : false;
$finalName = __DIR__ . DIRECTORY_SEPARATOR . "soundcache/_vsx_" . md5($_FILES["file"]["tmp_name"]) . ".$ext";
@copy($_FILES["file"]["tmp_name"], $finalName);

if (! $already) {

    if (file_exists($path . "data/voices/$codename.wav")) {
        // File exists in HS data/voices. Dont't convert again
        $finalFile = $path . "data/voices/$codename.wav";

    } else {

        if (! $_FILES["file"]["tmp_name"]) {
            die("VSX error, no data given");
        }

        if (filesize($_FILES["file"]["tmp_name"]) == 0) {
            Logger::error("Empty file {$_FILES["file"]["tmp_name"]}");
            die();
        }

        Logger::info("Received sample: {$_GET["oname"]}");

        if (strpos($_GET["oname"], ".fuz")) {
            $finalFile = fuzToWav($finalName);

        } else if (strpos($_GET["oname"], ".xwm")) {

            $finalFile = xwmToWav($finalName);

        } else if (strpos($_GET["oname"], ".wav")) {

            $finalFile = wavToWav($finalName);
        }
    }
    if ($ttsEndpoint === '') {
        die("Error");
    }

} else {
    Logger::info("Empty file {$_FILES["file"]["tmp_name"]} already exists at {$ttsEndpoint}/sample/$codename.wav");

}

if ($already) {
    die();
}

// Lets store voice files
@copy($finalFile, $path . "data/voices/$codename.wav");

$url  = $ttsEndpoint . '/upload_sample';
$curl = curl_init();

// Set cURL options
curl_setopt_array($curl, [
    CURLOPT_URL            => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => [
        'wavFile' => new CURLFile($finalFile, 'audio/wav', "$codename.wav"),
    ],
    CURLOPT_HTTPHEADER     => [
        'Content-Type: multipart/form-data',
    ],
]);

// Execute cURL request and get response
$response = curl_exec($curl);

audit_log("vsx.php voice available for {$_GET["codename"]}");
