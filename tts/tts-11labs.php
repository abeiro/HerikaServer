<?php

function elevenLabsGetSetting(array $settings, string $key, $default = null)
{
    return array_key_exists($key, $settings) ? $settings[$key] : $default;
}

function elevenLabsNormalizeBool($value, $default = null)
{
    if ($value === null || $value === '') {
        return $default;
    }
    if (is_bool($value)) {
        return $value;
    }

    $normalized = strtolower(trim(strval($value)));
    if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
        return true;
    }
    if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
        return false;
    }

    return $default;
}

function elevenLabsNormalizeFloat($value, $default = null)
{
    if ($value === null || $value === '') {
        return $default;
    }
    return floatval($value);
}

function elevenLabsNormalizeString($value, string $default = ''): string
{
    $normalized = trim(strval($value ?? ''));
    return $normalized !== '' ? $normalized : $default;
}


$GLOBALS["TTS_IN_USE"]=function($textString, $mood = "default", $stringforhash) {

	    global $ELEVEN_LABS,$ELEVENLABS_API_KEY;

		// Resolve API key: prefer API Badge 'ElevenLabs', fallback to schema
		$apiKey = '';
		try {
			if (!isset($GLOBALS["db"]) || !$GLOBALS["db"]) require_once(__DIR__ . "/../lib/{$GLOBALS["DBDRIVER"]}.class.php");
			if (!isset($GLOBALS["db"]) || !$GLOBALS["db"]) $GLOBALS["db"] = new sql();
			$row = $GLOBALS["db"]->fetchOne("SELECT api_key FROM core_api_badge WHERE lower(label)='elevenlabs' LIMIT 1");
			if (is_array($row) && !empty($row['api_key'])) $apiKey = trim($row['api_key']);
		} catch (Throwable $_e) {}
		if ($apiKey === '') $apiKey=$GLOBALS["TTS"]["ELEVEN_LABS"]["API_KEY"];

		// Cache 
		if (!isset($GLOBALS["AVOID_TTS_CACHE"]))
			if (file_exists(dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "soundcache/" . md5(trim($stringforhash)) . ".wav"))
				return dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "soundcache/" . md5(trim($stringforhash)) . ".wav";
	
	    $starTime = microtime(true);

        $settings = $GLOBALS["TTS"]["ELEVEN_LABS"] ?? [];
        $overrideOptions = $GLOBALS["PATCH_OVERRIDE_TTS_OPTIONS"] ?? null;
        if (is_array($overrideOptions)) {
            $overrideDriver = strtolower(trim(strval($overrideOptions['driver'] ?? '')));
            if ($overrideDriver === '11labs' && isset($overrideOptions['metadata']) && is_array($overrideOptions['metadata'])) {
                $settings = array_merge($settings, $overrideOptions['metadata']);
            }
        }

		$voice = elevenLabsNormalizeString(elevenLabsGetSetting($settings, 'voice_id', ''), 'EXAVITQu4vr4xnSDxMaL');

		if (isset($GLOBALS["PATCH_OVERRIDE_VOICE"]) && trim(strval($GLOBALS["PATCH_OVERRIDE_VOICE"])) !== '') {
			$voice = trim(strval($GLOBALS["PATCH_OVERRIDE_VOICE"]));
        }

        $modelId = elevenLabsNormalizeString(elevenLabsGetSetting($settings, 'model_id', ''), 'eleven_monolingual_v1');
        $requestText = $textString;
        $v3AudioTags = elevenLabsNormalizeString(elevenLabsGetSetting($settings, 'v3_audio_tags', ''));
        if (strtolower($modelId) === 'eleven_v3' && $v3AudioTags !== '') {
            $requestText = trim($v3AudioTags . ' ' . ltrim($requestText));
        }

        $queryParams = [];
        $optimizeStreamingLatency = elevenLabsNormalizeString(elevenLabsGetSetting($settings, 'optimize_streaming_latency', ''));
        if ($optimizeStreamingLatency !== '') {
            $queryParams['optimize_streaming_latency'] = $optimizeStreamingLatency;
        }

		$url = "https://api.elevenlabs.io/v1/text-to-speech/{$voice}";
        if (!empty($queryParams)) {
            $url .= '?' . http_build_query($queryParams);
        }

		// Request headers
		$headers = array(
			'Accept: audio/mpeg',
			"xi-api-key: $apiKey",
			'Content-Type: application/json'
		);
		
		// 11labs does not have sggml styles, but support some kinf of prompting
		/*if ($mood!="default") {
			$textString="\"$textString\" she said $mood";
		}*/
			
        $voiceSettings = [];
        foreach ([
            'stability' => null,
            'similarity_boost' => null,
            'style' => null,
            'speed' => 1.0,
        ] as $settingKey => $fallbackValue) {
            $normalizedValue = elevenLabsNormalizeFloat(elevenLabsGetSetting($settings, $settingKey, $fallbackValue), $fallbackValue);
            if ($normalizedValue !== null) {
                $voiceSettings[$settingKey] = $normalizedValue;
            }
        }

        if (strtolower($modelId) !== 'eleven_v3') {
            $speakerBoost = elevenLabsNormalizeBool(elevenLabsGetSetting($settings, 'use_speaker_boost', true), true);
            if ($speakerBoost !== null) {
                $voiceSettings['use_speaker_boost'] = $speakerBoost;
            }
        }

		// Request data
		$data = array(
			'text' => $requestText,
			'model_id' => $modelId,
			'voice_settings' => $voiceSettings
		);

        $languageCode = elevenLabsNormalizeString(elevenLabsGetSetting($settings, 'language_code', ''));
        if ($languageCode !== '') {
            $data['language_code'] = $languageCode;
        }

        $applyTextNormalization = strtolower(elevenLabsNormalizeString(elevenLabsGetSetting($settings, 'apply_text_normalization', '')));
        if (in_array($applyTextNormalization, ['auto', 'on', 'off'], true)) {
            $data['apply_text_normalization'] = $applyTextNormalization;
        }

        $applyLanguageTextNormalization = elevenLabsNormalizeBool(
            elevenLabsGetSetting($settings, 'apply_language_text_normalization', false),
            false
        );
        if ($applyLanguageTextNormalization !== null) {
            $data['apply_language_text_normalization'] = $applyLanguageTextNormalization;
        }

		// Create stream context options
		$options = array(
			'http' => array(
				'method' => 'POST',
				'header' => implode("\r\n", $headers),
				'content' => json_encode($data),
				//'timeout' => 10 // Set the timeout value in seconds
			)
		);

		// Create stream context
		$context = stream_context_create($options);

		// Send the request
		$response = file_get_contents($url, false, $context);

		// Handle the response
		if ($response !== false ) {
			// Handle the successful response
			//require_once(__DIR__.DIRECTORY_SEPARATOR."../lib/misc_utils_mp3riffer.php");
			$mp3Name=dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "soundcache/" . md5(trim($stringforhash)) . ".mp3";
			$wavName=dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "soundcache/" . md5(trim($stringforhash)) . ".wav";
			file_put_contents($mp3Name, trim($response));
			$startTimeTrans = microtime(true);
			shell_exec("ffmpeg -y -i $mp3Name $wavName 2>/dev/null >/dev/null");
			$endTimeTrans = microtime(true)-$startTimeTrans;
            //file_put_contents(dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "soundcache/" . md5(trim($stringforhash)) . ".mp3", trim($response));
			//$finalData=MP3toWav($response,strlen($response));
			$size=filesize($wavName);
			//file_put_contents(
			//	dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "soundcache/" . md5(trim($stringforhash)) . ".wav"
			//	, $finalData); // Save the audio response to a file
			//
			
            file_put_contents(dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "soundcache/" . md5(trim($stringforhash)) . ".txt", trim($textString) . "\n\rtotal call time:" . (microtime(true) - $starTime) . " ms\n\rsize of wav ($size)\n\rfunction tts($textString,$mood=\"cheerful\",$stringforhash)");
			$GLOBALS["DEBUG_DATA"][]=(microtime(true) - $starTime)." secs in 11labs call and $endTimeTrans microseconds in ffmpeg transcoding";
			return "soundcache/" . md5(trim($stringforhash)) . ".wav";
			
		} else {
			$textString.=print_r($http_response_header,true);
			file_put_contents(dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "soundcache/" . md5(trim($stringforhash)) . ".err", trim($textString));
            return false;
			
		}

};
