<?php

if (!function_exists('piper_tts_synthesis_url')) {
	function piper_tts_synthesis_url($endpoint) {
		$endpoint = rtrim(trim(strval($endpoint)), '/');
		if ($endpoint === '') {
			$endpoint = 'http://127.0.0.1:5000';
		}
		return substr($endpoint, -11) === '/synthesize' ? $endpoint : $endpoint . '/synthesize';
	}
}

$GLOBALS["TTS_IN_USE"]=function($textString, $mood , $stringforhash) {

		if (isset($GLOBALS["AVOID_TTS_CACHE"]) && $GLOBALS["AVOID_TTS_CACHE"]===false )
			if (file_exists(dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "soundcache/" . md5(trim($stringforhash)) . ".wav"))
				return dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "soundcache/" . md5(trim($stringforhash)) . ".wav";
		
		$newString = $textString;
		
	    $starTime = microtime(true);

		$url = piper_tts_synthesis_url($GLOBALS["TTS"]["PIPERTTS"]["endpoint"] ?? '');

		/*
		$lang = isset($GLOBALS["TTS"]["FORCED_LANG_DEV"]) ? $GLOBALS["TTS"]["FORCED_LANG_DEV"] : $GLOBALS["TTS"]["PIPERTTS"]["language"];
		
		if (isset($GLOBALS["PATCH_OVERRIDE_TTS_LANGUAGE"]))
        	$lang = $GLOBALS["PATCH_OVERRIDE_TTS_LANGUAGE"];

		if (empty($lang))
			$lang = $GLOBALS["TTS"]["PIPERTTS"]["language"] ?? "EN";
		*/
		
		$voice = $GLOBALS["TTS"]["FORCED_VOICE_DEV"] ?? ($GLOBALS["TTS"]["PIPERTTS"]["voiceid"] ?? "en_US-amy-low");
		if (empty($voice))
			$voice = $GLOBALS["TTS"]["PIPERTTS"]["voiceid"] ?? "en_US-amy-low";

		$speaker_name = trim($GLOBALS["TTS"]["PIPERTTS"]["speaker"] ?? "");
		$speaker_id = intval($GLOBALS["TTS"]["PIPERTTS"]["speaker_id"] ?? 0);
	
        if (isset($GLOBALS["PATCH_OVERRIDE_VOICE"])) {
			$voice = $GLOBALS["PATCH_OVERRIDE_VOICE"];
			$speaker_id = intval($GLOBALS["PATCH_OVERRIDE_VOICE_ID"] ?? 0);
		}

        // Normalize special narrator voice id
        if (is_string($voice) && strtolower($voice) === 'thenarrator') {
            $voice = 'malenord';
        }

		$timescale = floatval($GLOBALS["TTS"]["PIPERTTS"]["length_scale"] ?? 1.0);
		if ($timescale > 4.0)
			$timescale = 4.0;
		elseif ($timescale < 0.2)
			$timescale = 0.2;

		$noise_scale = floatval($GLOBALS["TTS"]["PIPERTTS"]["noise_scale"] ?? 1.0);
		if ($noise_scale > 1.0)
			$noise_scale = 1.0;
		elseif ($noise_scale < 0.1)
			$noise_scale = 0.0;

		$noise_w_scale = floatval($GLOBALS["TTS"]["PIPERTTS"]["noise_w_scale"] ?? 1.0);
		if ($noise_w_scale > 1.0)
			$noise_w_scale = 1.0;
		elseif ($noise_w_scale < 0.1)
			$noise_w_scale = 0.0;

		$data = array(
			'text' => $newString,
			'voice' => $voice,
			'length_scale' => $timescale
		);

		if ($noise_scale > 0.001)
			$data['noise_scale'] = $noise_scale; // optional
		if ($noise_w_scale > 0.001)
			$data['noise_w_scale'] = $noise_w_scale; // optional
		if (strlen($speaker_name) > 0)
			$data['speaker'] = $speaker_name; // optional
		if ($speaker_id > 0)
			$data['speaker_id'] = $speaker_id; // optional, overrides speaker

		$j_data = json_encode($data);
		//error_log(" pipertts json: $j_data - dbg "); // debug
		$options = array(
			'http' => array(
				'header' => "Content-type: application/json\r\n" .
							//"Accept: application/json\r\n",
							'Accept: audio/wav',
				'method' => 'POST',
				'content' => $j_data
			)
		);
		$context = stream_context_create($options);
		$response = file_get_contents($url, false, $context);


		if (isset($GLOBALS["TTS_FFMPEG_FILTERS"]) && is_array($GLOBALS["TTS_FFMPEG_FILTERS"])) {
			$GLOBALS["TTS_FFMPEG_FILTERS"]["adelay"]="adelay=150|150";
			$FFMPEG_FILTER='-af "'.implode(",",$GLOBALS["TTS_FFMPEG_FILTERS"]).'"';

			//if (isset($GLOBALS["TTS_FFMPEG_FILTERS"]["tempo"])) 
			//	error_log(" filter: $FFMPEG_FILTER - exec trace ".__FILE__." ".__LINE__." ".__FUNCTION__);
        } else {
			$FFMPEG_FILTER='-filter:a "adelay=150|150"';
		}
		
		// Handle the response
		if ($response !== false ) {
			// Handle the successful response
			$size = strlen($response);
			$oname = dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "soundcache/" . md5(trim($stringforhash)) . "_o.wav";
			$fname = dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "soundcache/" . md5(trim($stringforhash)) . ".wav";
			
			file_put_contents($oname, $response); // Save the audio response to a file
			$startTimeTrans = microtime(true);
			//shell_exec("ffmpeg -y -i $oname  -af \"adelay=150|150,silenceremove=start_periods=1:start_silence=0.1:start_threshold=-25dB,areverse,silenceremove=start_periods=1:start_silence=0.1:start_threshold=-40dB,areverse,speechnorm=e=3:r=0.0001:l=1:p=0.75\" $fname 2>/dev/null >/dev/null");
			shell_exec("ffmpeg -y -i $oname $FFMPEG_FILTER $fname 2>/dev/null >/dev/null");
			//error_log("ffmpeg -y -i $oname  $FFMPEG_FILTER $fname ".__FILE__." ".__LINE__." ".__FUNCTION__);
			$endTimeTrans = microtime(true)-$startTimeTrans;
			
            file_put_contents(dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "soundcache/" . md5(trim($stringforhash)) . ".txt", trim($textString) . "\n$FFMPEG_FILTER\n\rtotal call time:" . (microtime(true) - $starTime) . " ms\n\rffmpeg transcoding: $endTimeTrans secs\n\rsize of wav ($size)\n\rfunction tts($textString,$mood=\"cheerful\",$stringforhash)");
			$GLOBALS["DEBUG_DATA"][]=(microtime(true) - $starTime)." secs in Piper-TTS call, voice id={$voice} / {$speaker_name} / {$speaker_id}.";

			/* if (isset($GLOBALS["DEVELOP_STORE_AUDIO_FOR_TRANING"]) && $GLOBALS["DEVELOP_STORE_AUDIO_FOR_TRANING"]) {
				$rootPath=dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "soundcache/" ;
				$tfolder=$rootPath."/$voice";
				@mkdir($tfolder);
				copy($fname,$tfolder."/".basename($fname));

			}*/
			return "soundcache/" . md5(trim($stringforhash)) . ".wav";
			
		} else {
			Logger::error("PiperTTS: error occurred. ".__FILE__." ".__LINE__." ".__FUNCTION__);
			$textString.=print_r($http_response_header,true);
			file_put_contents(dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "soundcache/" . md5(trim($stringforhash)) . ".err", trim($textString));
            return false;
			
		}

};
