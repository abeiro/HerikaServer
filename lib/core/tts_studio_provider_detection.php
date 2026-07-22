<?php

if (!function_exists('chimTtsStudioProbeSucceeded')) {
    function chimTtsStudioProbeSucceeded(array $probe): bool
    {
        $httpCode = intval($probe['http_code'] ?? 0);
        return ($probe['response'] ?? false) !== false && $httpCode >= 200 && $httpCode < 300;
    }
}

if (!function_exists('chimTtsStudioNormalizeProviderIdentity')) {
    function chimTtsStudioNormalizeProviderIdentity(string $provider): string
    {
        return match (strtolower(trim($provider))) {
            'xtts', 'xtts-fastapi' => 'xtts-fastapi',
            'pocket_tts', 'pocket-tts', 'pockettts' => 'pockettts',
            'chatterbox' => 'chatterbox',
            'omnivoice' => 'omnivoice',
            default => '',
        };
    }
}

if (!function_exists('chimTtsStudioClassifyPocketTtsRuntime')) {
    function chimTtsStudioClassifyPocketTtsRuntime(
        string $endpoint,
        array $metadata,
        array $healthProbe,
        array $modelsProbe,
        array $speakersProbe
    ): array {
        if (chimTtsStudioProbeSucceeded($healthProbe) && chimTtsStudioProbeSucceeded($modelsProbe)) {
            return [
                'reachable' => true,
                'mode' => 'audio_cpp',
                'reason' => 'audio.cpp health and models endpoints responded',
            ];
        }

        if (chimTtsStudioProbeSucceeded($speakersProbe) && is_array($speakersProbe['decoded'] ?? null)) {
            return [
                'reachable' => true,
                'mode' => 'standard',
                'reason' => 'Standard PocketTTS speakers endpoint responded',
            ];
        }

        $apiFormatValue = $metadata['api_format'] ?? '';
        $apiFormat = is_scalar($apiFormatValue) ? strtolower(trim(strval($apiFormatValue))) : '';
        $normalizedEndpoint = rtrim(strtolower(trim($endpoint)), '/');
        $fallbackMode = ($apiFormat === 'audio_cpp'
            || $apiFormat === 'audiocpp'
            || strpos($normalizedEndpoint, ':8086') !== false
            || str_ends_with($normalizedEndpoint, '/v1/audio/speech'))
            ? 'audio_cpp'
            : 'standard';

        $probe = $fallbackMode === 'audio_cpp' ? $healthProbe : $speakersProbe;
        $reason = trim(strval($probe['curl_error'] ?? ''));
        if ($reason === '') {
            $httpCode = intval($probe['http_code'] ?? 0);
            $reason = 'HTTP ' . ($httpCode > 0 ? strval($httpCode) : 'no response');
        }

        return [
            'reachable' => false,
            'mode' => $fallbackMode,
            'reason' => $reason,
        ];
    }
}

if (!function_exists('chimTtsStudioProviderFromOpenApi')) {
    function chimTtsStudioProviderFromOpenApi(array $document): string
    {
        $title = strtolower(trim(strval($document['info']['title'] ?? '')));
        $paths = is_array($document['paths'] ?? null) ? array_keys($document['paths']) : [];
        // Released XTTS exposes several generic speaker/settings routes also used
        // by the other compatible APIs, so test its unique routes first.
        if (in_array('/languages', $paths, true)
            || in_array('/get_models_list', $paths, true)) {
            return 'xtts-fastapi';
        }
        if (str_contains($title, 'chatterbox')
            || (in_array('/sample/{file_name}', $paths, true)
                && in_array('/speakers_list_extended', $paths, true))) {
            return 'chatterbox';
        }
        if (in_array('/tts_to_audio_form', $paths, true)
            || (in_array('/tts_to_audio', $paths, true)
                && in_array('/voices/{voice_id}', $paths, true))) {
            return 'pockettts';
        }
        return '';
    }
}
