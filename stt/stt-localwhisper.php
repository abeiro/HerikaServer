<?php

$localPath = dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR;
require_once($localPath . "lib".DIRECTORY_SEPARATOR."runtime_bootstrap.php");
chimRuntimeBootstrapIfNeeded($localPath, [
    'load_general_settings' => true,
    'load_stt_connector' => true,
    'load_itt_connector' => false,
]);

if (!function_exists('sttLocalWhisperSetDiagnostic')) {
    function sttLocalWhisperSetDiagnostic(array $diagnostic): void
    {
        $GLOBALS['STT_LAST_DIAGNOSTIC'] = $diagnostic;
    }
}

if (!function_exists('sttLocalWhisperBuildHttpMeta')) {
    function sttLocalWhisperBuildHttpMeta(array $headers): array
    {
        $statusLine = '';
        foreach ($headers as $header) {
            if (stripos($header, 'HTTP/') === 0) {
                $statusLine = $header;
                break;
            }
        }

        $statusCode = 0;
        if ($statusLine !== '' && preg_match('/\s(\d{3})(?:\s|$)/', $statusLine, $matches)) {
            $statusCode = intval($matches[1]);
        }

        return [
            'http_status_line' => $statusLine,
            'http_status_code' => $statusCode,
        ];
    }
}

function stt($file)
{
    $url = trim(strval($GLOBALS["STT"]["LOCALWHISPER"]["URL"] ?? ''));
    $formField = trim(strval($GLOBALS["STT"]["LOCALWHISPER"]["FORMFIELD"] ?? 'file'));
    $timeoutSeconds = intval($GLOBALS["STT"]["LOCALWHISPER"]["TIMEOUT"] ?? 60);
    if ($timeoutSeconds <= 0) {
        $timeoutSeconds = 60;
    }

    $filePath = $file;
    if ($url === '') {
        sttLocalWhisperSetDiagnostic([
            'driver' => 'localwhisper',
            'stage' => 'config',
            'message' => 'LocalWhisper URL is empty.',
        ]);
        error_log("STT LocalWhisper: URL is empty");
        return "";
    }

    if (!is_file($filePath) || !is_readable($filePath)) {
        sttLocalWhisperSetDiagnostic([
            'driver' => 'localwhisper',
            'stage' => 'input',
            'message' => 'Audio file is missing or unreadable.',
            'file_path' => $filePath,
        ]);
        error_log("STT LocalWhisper: Audio file missing or unreadable: " . $filePath);
        return "";
    }

    $fileContent = @file_get_contents($filePath);
    if ($fileContent === false) {
        $lastError = error_get_last();
        sttLocalWhisperSetDiagnostic([
            'driver' => 'localwhisper',
            'stage' => 'input',
            'message' => 'Failed to read audio file.',
            'file_path' => $filePath,
            'php_error' => $lastError['message'] ?? '',
        ]);
        error_log("STT LocalWhisper: Failed to read audio file: " . $filePath);
        return "";
    }

    $boundary = '----WebKitFormBoundary' . md5(mt_rand() . microtime());
    $contentType = 'multipart/form-data; boundary=' . $boundary;

    $filename = basename($filePath);
    $multipartBody = "--{$boundary}\r\n"
        . "Content-Disposition: form-data; name=\"{$formField}\"; filename=\"{$filename}\"\r\n"
        . "Content-Type: audio/wav\r\n\r\n"
        . $fileContent . "\r\n"
        . "--{$boundary}--\r\n";

    // Set up the context for the request
    $contextOptions = [
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: {$contentType}\r\n" .
            "Content-Length: " . strlen($multipartBody) . "\r\n",
            'content' => $multipartBody,
            'timeout' => $timeoutSeconds,
            'ignore_errors' => true,
        ],
    ];

    $context = stream_context_create($contextOptions);

    // Send the request and get the response
    $GLOBALS['STT_LAST_DIAGNOSTIC'] = [];
    $response = file_get_contents($url, false, $context);
    $httpMeta = sttLocalWhisperBuildHttpMeta($http_response_header ?? []);
    $baseDiagnostic = [
        'driver' => 'localwhisper',
        'url' => $url,
        'form_field' => $formField,
        'file_path' => $filePath,
        'file_size' => strlen($fileContent),
        'timeout_seconds' => $timeoutSeconds,
    ] + $httpMeta;

    // Handle the response
    if ($response === false) {
        $lastError = error_get_last();
        sttLocalWhisperSetDiagnostic($baseDiagnostic + [
            'stage' => 'transport',
            'message' => 'Failed to get response from LocalWhisper server.',
            'php_error' => $lastError['message'] ?? '',
        ]);
        error_log("STT LocalWhisper: Failed to get response from server");
        return "";
    }

    $responseParsed = json_decode($response, true);

    if (!is_array($responseParsed)) {
        $decodeMessage = 'Failed to parse JSON response from LocalWhisper.';
        if (preg_match('/^\s*<!DOCTYPE html/i', $response) || preg_match('/<html/i', $response)) {
            $decodeMessage = 'Endpoint returned HTML instead of JSON. LocalWhisper URL likely points to a web page or wrong route.';
        }
        sttLocalWhisperSetDiagnostic($baseDiagnostic + [
            'stage' => 'decode',
            'message' => $decodeMessage,
            'response_excerpt' => substr($response, 0, 1000),
            'json_error' => function_exists('json_last_error_msg') ? json_last_error_msg() : 'Unknown JSON error',
        ]);
        error_log("STT LocalWhisper: Failed to parse JSON response: " . $response);
        return "";
    }

    // Support both 'text' and 'transcription' keys in the response
    foreach (['text', 'transcription'] as $key) {
        if (isset($responseParsed[$key]) && !is_array($responseParsed[$key])) {
            sttLocalWhisperSetDiagnostic($baseDiagnostic + [
                'stage' => 'success',
                'message' => 'LocalWhisper transcription received successfully.',
                'response_key' => $key,
            ]);
            return trim(strval($responseParsed[$key]));
        }
    }

    sttLocalWhisperSetDiagnostic($baseDiagnostic + [
        'stage' => 'response',
        'message' => 'JSON response did not contain a text or transcription field.',
        'response_excerpt' => substr(json_encode($responseParsed, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 0, 1000),
    ]);
    error_log("STT LocalWhisper: No text or transcription found in response: " . json_encode($responseParsed));
    return "";

}
