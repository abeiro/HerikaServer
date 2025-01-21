<?php



$GLOBALS["TTS_IN_USE"]=function($textString, $mood , $stringforhash) {

		//xtts_fastapi_settings([]); //Check this
		
		/*if (!isset($GLOBALS["AVOID_TTS_CACHE"]))
			if (file_exists(dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "soundcache/" . md5(trim($stringforhash)) . ".wav"))
				return dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "soundcache/" . md5(trim($stringforhash)) . ".wav";
		*/
		
		
		$newString=$textString;
		
	    $starTime = microtime(true);

		$url = $GLOBALS["TTS"]["KOKORO"]["endpoint"]."/v1/audio/speech";

		// Request headers
		$headers = array(
			'Accept: audio/wav',
			'Content-Type: application/json'
		);
		
	
		$voice=isset($GLOBALS["TTS"]["FORCED_VOICE_DEV"])?$GLOBALS["TTS"]["FORCED_VOICE_DEV"]:$GLOBALS["TTS"]["KOKORO"]["voiceid"];
		
		if (empty($voice))
			$voice=$GLOBALS["TTS"]["KOKORO"]["voiceid"];
	
		if (isset($GLOBALS["PATCH_OVERRIDE_VOICE"]))
			$voice=$GLOBALS["PATCH_OVERRIDE_VOICE"];

		$data = array(
            'model'=>'kokoro',
			'input' => $newString,
			'voice' => $voice,
			'response_format' => 'wav',
            'speed' => $GLOBALS["TTS"]["KOKORO"]["speed"],
            'stream'=>true

		);
		$options = array(
			'http' => array(
				'header' => "Content-type: application/json\r\n" .
							"Accept: application/json\r\n",
				'method' => 'POST',
				'content' => json_encode($data)
			)
		);
		$context = stream_context_create($options);
		$response = file_get_contents($url, false, $context);


		if (is_array($GLOBALS["TTS_FFMPEG_FILTERS"])) {
			$GLOBALS["TTS_FFMPEG_FILTERS"]["adelay"]="adelay=150|150";
			$FFMPEG_FILTER='-af "'.implode(",",$GLOBALS["TTS_FFMPEG_FILTERS"]).'"';
			
		} else {
			$FFMPEG_FILTER='-filter:a "adelay=150|150"';
		}
		
		// Handle the response
		if ($response !== false ) {
			// Handle the successful response
			$size=strlen($response);
			$oname=dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "soundcache/" . md5(trim($stringforhash)) . "_o.wav";
			$fname=dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "soundcache/" . md5(trim($stringforhash)) . ".wav";
			
			file_put_contents($oname, $response); // Save the audio response to a file
			$startTimeTrans = microtime(true);
			//shell_exec("ffmpeg -y -i $oname  -af \"adelay=150|150,silenceremove=start_periods=1:start_silence=0.1:start_threshold=-25dB,areverse,silenceremove=start_periods=1:start_silence=0.1:start_threshold=-40dB,areverse,speechnorm=e=3:r=0.0001:l=1:p=0.75\" $fname 2>/dev/null >/dev/null");
			shell_exec("ffmpeg -y -i $oname  $FFMPEG_FILTER $fname 2>/dev/null >/dev/null");
			//error_log("ffmpeg -y -i $oname  $FFMPEG_FILTER $fname ");
			$endTimeTrans = microtime(true)-$startTimeTrans;
			
            file_put_contents(dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "soundcache/" . md5(trim($stringforhash)) . ".txt", trim($textString) . "\n\rtotal call time:" . (microtime(true) - $starTime) . " ms\n\rffmpeg transcoding: $endTimeTrans secs\n\rsize of wav ($size)\n\rfunction tts($textString,$mood=\"cheerful\",$stringforhash)");
			$GLOBALS["DEBUG_DATA"][]=(microtime(true) - $starTime)." secs in kokoro call";
			return "soundcache/" . md5(trim($stringforhash)) . ".wav";
			
		} else {
			$textString.=print_r($http_response_header,true);
			file_put_contents(dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "soundcache/" . md5(trim($stringforhash)) . ".err", trim($textString));
            return false;
			
		}

};

/*
$GLOBALS["TTS"]["XTTSFASTAPI"]["endpoint"]='http://localhost:8020';
$GLOBALS["TTS"]["XTTSFASTAPI"]["voiceid"]='svenja';
$GLOBALS["TTS"]["XTTSFASTAPI"]["language"]='en';

$textTosay="Hello fellows...this is a new text to speech connector";

echo tts($textTosay,'',$textTosay).PHP_EOL;
*/



