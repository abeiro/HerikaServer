<?php

if (!function_exists('omnivoice_normalize_endpoint_url')) {
    function omnivoice_normalize_endpoint_url($url) {
        return rtrim(trim(strval($url ?? '')), '/');
    }
}

if (!function_exists('omnivoice_sanitize_language')) {
    function omnivoice_sanitize_language($language): string {
        $language = preg_replace('/[^a-z\-]/i', '', strtolower(trim(strval($language ?? ''))));
        return $language !== '' ? $language : '';
    }
}

if (!function_exists('omnivoice_apply_text_hooks')) {
    function omnivoice_apply_text_hooks(string $text): string {
        foreach (['OMNIVOICE_TEXTMODIFIER', 'XTTS_TEXTMODIFIER'] as $hookGroup) {
            if (!isset($GLOBALS["HOOKS"][$hookGroup]) || !is_array($GLOBALS["HOOKS"][$hookGroup])) {
                continue;
            }
            foreach ($GLOBALS["HOOKS"][$hookGroup] as $hook) {
                Logger::info("Calling hook." . __FILE__ . " " . __LINE__ . " " . __FUNCTION__);
                $text = call_user_func($hook, $text);
            }
        }
        return $text;
    }
}

if (!function_exists('omnivoice_fallback_codename')) {
    function omnivoice_fallback_codename(): string {
        $name = $GLOBALS["HERIKA_NAME"] ?? $GLOBALS["DIALECTIC_NAME"] ?? '';
        $codename = str_replace(" ", "_", mb_strtolower(strval($name), 'UTF-8'));
        $codename = str_replace("'", "+", $codename);
        return preg_replace('/[^a-zA-Z0-9_+]/u', '', $codename);
    }
}

if (!function_exists('omnivoice_post_tts')) {
    function omnivoice_post_tts(string $url, string $text, string $voice, string $language) {
        $data = [
            'text' => $text,
            'speaker_wav' => $voice,
        ];
        if ($language !== '') {
            $data['language'] = $language;
        }

        $options = [
            'http' => [
                'header' => "Content-type: application/json\r\nAccept: application/json\r\n",
                'method' => 'POST',
                'content' => json_encode($data),
            ],
        ];

        return [
            'response' => file_get_contents($url, false, stream_context_create($options)),
            'options' => $options,
        ];
    }
}

if (!function_exists('omnivoice_switch_language')) {
    function omnivoice_switch_language(string $endpoint, string $language): void {
        $payload = json_encode(['language' => $language]);
        if (!is_string($payload) || $payload === '') {
            return;
        }

        $options = [
            'http' => [
                'header' => "Content-type: application/json\r\nAccept: application/json\r\n",
                'method' => 'POST',
                'content' => $payload,
                'ignore_errors' => true,
            ],
        ];
        $result = @file_get_contents($endpoint . "/active_language", false, stream_context_create($options));
        if ($result === false) {
            Logger::warn("Unable to switch OmniVoice active language to '{$language}'");
        }
    }
}

if (!function_exists('omnivoice_first_non_empty')) {
    function omnivoice_first_non_empty(...$values): string {
        foreach ($values as $value) {
            $text = trim(strval($value ?? ''));
            if ($text !== '') {
                return $text;
            }
        }
        return '';
    }
}

if (!function_exists('omnivoice_resolve_language')) {
    function omnivoice_resolve_language(): string {
        return omnivoice_sanitize_language(omnivoice_first_non_empty(
            $GLOBALS["PATCH_OVERRIDE_TTS_LANGUAGE"] ?? '',
            $GLOBALS["TTS"]["FORCED_LANG_DEV"] ?? '',
            $GLOBALS["TTS"]["OMNIVOICE"]["language"] ?? ''
        ));
    }
}

if (!function_exists('omnivoice_fallback_candidates')) {
    function omnivoice_fallback_candidates(string $requestedVoice): array {
        $candidates = [
            $GLOBALS["TTS"]["OMNIVOICE"]["fallback_male"] ?? '',
            $GLOBALS["TTS"]["OMNIVOICE"]["fallback_female"] ?? '',
            omnivoice_fallback_codename(),
        ];

        $seen = [strtolower($requestedVoice) => true];
        $result = [];
        foreach ($candidates as $candidate) {
            $candidate = trim(strval($candidate));
            $key = strtolower($candidate);
            if ($candidate === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $result[] = $candidate;
        }
        return $result;
    }
}

if (!function_exists('omnivoice_ffmpeg_command')) {
    function omnivoice_ffmpeg_command(string $inputFile, string $filterArgs, string $outputFile): string {
        $ffmpegCandidates = array_filter([
            getenv('FFMPEG_PATH') ?: '',
            'C:\\Program Files\\ShareX\\ffmpeg.exe',
            'ffmpeg',
        ]);
        $ffmpegPath = 'ffmpeg';
        foreach ($ffmpegCandidates as $candidate) {
            if ($candidate === 'ffmpeg' || file_exists($candidate)) {
                $ffmpegPath = $candidate;
                break;
            }
        }
        $nullDevice = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? 'NUL' : '/dev/null';
        return escapeshellarg($ffmpegPath) . " -y -i " . escapeshellarg($inputFile) . " " . $filterArgs . " " . escapeshellarg($outputFile) . " >$nullDevice 2>$nullDevice";
    }
}

$GLOBALS["TTS_IN_USE"] = function($textString, $mood, $stringforhash) {
    $cacheHash = md5(trim($stringforhash));
    $cachePath = dirname(__FILE__) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "soundcache" . DIRECTORY_SEPARATOR;

    if (isset($GLOBALS["AVOID_TTS_CACHE"]) && $GLOBALS["AVOID_TTS_CACHE"] === false) {
        $cachedFile = $cachePath . $cacheHash . ".wav";
        if (file_exists($cachedFile)) {
            return $cachedFile;
        }
    }

    $starTime = microtime(true);
    $newString = omnivoice_apply_text_hooks($textString);
    $endpoint = omnivoice_normalize_endpoint_url($GLOBALS["TTS"]["OMNIVOICE"]["endpoint"] ?? 'http://127.0.0.1:8021');
    $url = $endpoint . "/tts_to_audio";

    $lang = omnivoice_resolve_language();
    if ($lang !== '') {
        omnivoice_switch_language($endpoint, $lang);
    }

    $voice = omnivoice_first_non_empty(
        $GLOBALS["PATCH_OVERRIDE_VOICE"] ?? '',
        $GLOBALS["TTS"]["FORCED_VOICE_DEV"] ?? '',
        $GLOBALS["TTS"]["OMNIVOICE"]["voiceid"] ?? ''
    );

    $result = omnivoice_post_tts($url, $newString, $voice, $lang);
    $response = $result['response'];
    $options = $result['options'];

    if ($response === false) {
        Logger::error("Error occurred. " . __FILE__ . " " . __LINE__ . " " . __FUNCTION__);
        foreach (omnivoice_fallback_candidates($voice) as $fallbackVoice) {
            $result = omnivoice_post_tts($url, $newString, $fallbackVoice, $lang);
            $response = $result['response'];
            $options = $result['options'];
            if ($response !== false) {
                $voice = $fallbackVoice;
                break;
            }
        }
    }

    if (is_array($GLOBALS["TTS_FFMPEG_FILTERS"] ?? null)) {
        $GLOBALS["TTS_FFMPEG_FILTERS"]["adelay"] = "adelay=150|150";
        $FFMPEG_FILTER = '-af "' . implode(",", $GLOBALS["TTS_FFMPEG_FILTERS"]) . '"';
    } else {
        $FFMPEG_FILTER = '-filter:a "adelay=150|150"';
    }

    if ($response !== false) {
        $size = strlen($response);
        $oname = $cachePath . $cacheHash . "_o.wav";
        $fname = $cachePath . $cacheHash . ".wav";

        file_put_contents($oname, $response);
        $startTimeTrans = microtime(true);
        $ffmpegCommand = omnivoice_ffmpeg_command($oname, $FFMPEG_FILTER, $fname);
        shell_exec($ffmpegCommand);
        if (!file_exists($fname) || filesize($fname) < 44) {
            Logger::error("OmniVoice ffmpeg conversion failed: {$ffmpegCommand}");
        }
        $endTimeTrans = microtime(true) - $startTimeTrans;

        $textString .= PHP_EOL . print_r($options, true);
        $textString .= PHP_EOL . print_r($http_response_header ?? [], true);
        file_put_contents($cachePath . $cacheHash . ".txt", trim($textString) . "\n$FFMPEG_FILTER\n\rtotal call time:" . (microtime(true) - $starTime) . " ms\n\rffmpeg transcoding: $endTimeTrans secs\n\rsize of wav ($size)\n\rfunction tts($textString,$mood=\"cheerful\",$stringforhash)");
        $GLOBALS["DEBUG_DATA"][] = (microtime(true) - $starTime) . " secs in omnivoice call";

        if (isset($GLOBALS["DEVELOP_STORE_AUDIO_FOR_TRANING"]) && $GLOBALS["DEVELOP_STORE_AUDIO_FOR_TRANING"]) {
            $tfolder = $cachePath . "/" . $voice;
            @mkdir($tfolder);
            copy($fname, $tfolder . "/" . basename($fname));
        }

        return "soundcache/" . $cacheHash . ".wav";
    }

    $textString .= PHP_EOL . print_r($options, true);
    $textString .= PHP_EOL . print_r($http_response_header ?? 'No HTTP response headers available', true);
    file_put_contents($cachePath . $cacheHash . ".err", trim($textString));
    return false;
};
