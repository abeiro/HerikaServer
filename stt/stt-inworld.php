<?php

$localPath = dirname(__FILE__) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR;
require_once($localPath . "lib" . DIRECTORY_SEPARATOR . "runtime_bootstrap.php");
chimRuntimeBootstrapIfNeeded($localPath, [
    'load_general_settings' => true,
    'load_stt_connector' => true,
    'load_itt_connector' => false,
]);

function stt($filePath)
{
    if (!isset($GLOBALS["db"]) || !$GLOBALS["db"]) {
        $GLOBALS["db"] = new sql();
    }

    $fileContent = @file_get_contents($filePath);
    if ($fileContent === false) {
        error_log("STT Inworld: warning - input file missing or unreadable! {$filePath}");
        return '';
    }

    $cfg = $GLOBALS["STT"]["INWORLD"] ?? [];

    // Resolve API key: prefer STT conf, fallback to API Badge "Inworld"
    $apiKey = trim((string)($cfg["API_KEY"] ?? ''));
    if ($apiKey === '') {
        try {
            $row = $GLOBALS["db"]->fetchOne("SELECT api_key FROM core_api_badge WHERE lower(label)='inworld' LIMIT 1");
            if (is_array($row) && !empty($row['api_key'])) {
                $apiKey = trim((string)$row['api_key']);
            }
        } catch (Throwable $_e) {}
    }
    if ($apiKey === '') {
        error_log("STT Inworld: No API key found. Set STT INWORLD API_KEY or API badge 'Inworld'.");
        return '';
    }

    $modelId = trim((string)($cfg["MODEL_ID"] ?? 'groq/whisper-large-v3'));
    if ($modelId === '') {
        $modelId = 'groq/whisper-large-v3';
    }

    $language = trim((string)($cfg["LANGUAGE"] ?? 'en-US'));

    $transcribeConfig = [
        "modelId" => $modelId,
        // Hardcoded defaults: keep UI surface minimal (model + language only).
        "audioEncoding" => "AUTO_DETECT",
        "sampleRateHertz" => 16000,
        "numberOfChannels" => 1
    ];
    if ($language !== '') {
        $transcribeConfig["language"] = $language;
    }

    $payload = [
        "transcribeConfig" => $transcribeConfig,
        "audioData" => [
            "content" => base64_encode($fileContent)
        ]
    ];

    $url = "https://api.inworld.ai/stt/v1/transcribe";
    $body = json_encode($payload);

    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Basic {$apiKey}",
            "Content-Type: application/json",
            "Accept: application/json"
        ]);

        $response = curl_exec($ch);
        $httpCode = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            error_log("STT Inworld: cURL request failed: {$curlErr}");
            return '';
        }
    } else {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Authorization: Basic {$apiKey}\r\n" .
                            "Content-Type: application/json\r\n" .
                            "Accept: application/json\r\n",
                'content' => $body,
                'timeout' => 60
            ]
        ]);
        $response = @file_get_contents($url, false, $context);
        $httpCode = 0;
        if (isset($http_response_header) && is_array($http_response_header)) {
            foreach ($http_response_header as $hline) {
                if (preg_match('/HTTP\/\d\.\d\s+(\d+)/', (string)$hline, $m)) {
                    $httpCode = intval($m[1]);
                    break;
                }
            }
        }
        if ($response === false) {
            error_log("STT Inworld: HTTP request failed and cURL is unavailable.");
            return '';
        }
    }

    $parsed = json_decode((string)$response, true);

    if ($httpCode !== 200) {
        $errMsg = '';
        if (is_array($parsed)) {
            $errMsg = trim((string)($parsed['message'] ?? ''));
        }
        error_log("STT Inworld: HTTP {$httpCode}" . ($errMsg !== '' ? " - {$errMsg}" : ""));
        return '';
    }

    if (!is_array($parsed)) {
        error_log("STT Inworld: Invalid JSON response");
        return '';
    }

    $transcript = trim((string)($parsed['transcription']['transcript'] ?? ''));
    if ($transcript === '') {
        error_log("STT Inworld: Empty transcription response");
    }

    return $transcript;
}
