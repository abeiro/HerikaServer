<?php


function insertNoise($inputString, $noiseArray) {
    // Split the string into words
    $words = explode(' ', $inputString);

	if (!is_array($words))
		return $inputString;
    // Shuffle the noise array to ensure randomness
    shuffle($noiseArray);

    // Calculate the number of insert positions (between words)
    $numInsertPositions = count($words) - 1;

    // Ensure we don't have more noises than insert positions
    $numNoises = min(count($noiseArray), $numInsertPositions);

    // Get a random subset of the insert positions
    $insertPositions = array_rand(array_fill(0, $numInsertPositions, 1), $numNoises);

    // Ensure $insertPositions is an array even if there's only one position
    if (!is_array($insertPositions)) {
        $insertPositions = array($insertPositions);
    }

    // Sort insert positions in descending order to avoid shifting positions
    rsort($insertPositions);

    // Insert the noise elements at the chosen positions
    foreach ($insertPositions as $index => $pos) {
        array_splice($words, $pos + 1, 0, $noiseArray[$index]);
		break; //Comment  to more noise
    }

    // Join the words back into a string
    return implode(' ', $words);
}


function xtts_fastapi_settings($settings) {
	$url = $GLOBALS["TTS"]["XTTSFASTAPI"]["endpoint"].'/set_tts_settings';
	$data = json_decode('{
		"stream_chunk_size": 20,
		"temperature": 0.9,
		"speed": 1,
		"length_penalty": 1,
		"repetition_penalty": 5,
		"top_p": 0.85,
		"top_k": 50,
		"enable_text_splitting": true
		}',true);
	
	$finalData=array_merge($data,$settings);
	
	$options = array(
		'http' => array(
			'header' => "Content-type: application/json\r\n" .
						"Accept: application/json\r\n",
			'method' => 'POST',
			'content' => json_encode($finalData)
		)
	);
	$context = stream_context_create($options);
	$result = file_get_contents($url, false, $context);

	if ($result === FALSE) {
		// Handle error
		error_log("Error occurred.".__FILE__);
	} else {
		;//ok
	}
}




$GLOBALS["TTS_IN_USE"]=function($textString, $mood , $stringforhash) {

		//xtts_fastapi_settings([]); //Check this
		
		/*if (!isset($GLOBALS["AVOID_TTS_CACHE"]))
			if (file_exists(dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "soundcache/" . md5(trim($stringforhash)) . ".wav"))
				return dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "soundcache/" . md5(trim($stringforhash)) . ".wav";
		*/
		
		
		$newString=$textString;
		
	    $starTime = microtime(true);

		$url = $GLOBALS["TTS"]["XTTSFASTAPI"]["endpoint"]."/tts_to_audio/";

		// Request headers
		$headers = array(
			'Accept: audio/wav',
			'Content-Type: application/json'
		);
		
		$lang=isset($GLOBALS["TTS"]["FORCED_LANG_DEV"])?$GLOBALS["TTS"]["FORCED_LANG_DEV"]:$GLOBALS["TTS"]["XTTSFASTAPI"]["language"];
		
		
		if ((isset($GLOBALS["LLM_LANG"]))&&(isset($GLOBALS["LANG_LLM_XTTS"]))&&$GLOBALS["LANG_LLM_XTTS"]) {
			$lang=$GLOBALS["LLM_LANG"];

		}
		
		

		if (empty($lang))
			$lang=$GLOBALS["TTS"]["XTTSFASTAPI"]["language"];
	
	
		$voice=isset($GLOBALS["TTS"]["FORCED_VOICE_DEV"])?$GLOBALS["TTS"]["FORCED_VOICE_DEV"]:$GLOBALS["TTS"]["XTTSFASTAPI"]["voiceid"];
		
		if (empty($voice))
			$voice=$GLOBALS["TTS"]["XTTSFASTAPI"]["voiceid"];
	
		if (isset($GLOBALS["PATCH_OVERRIDE_VOICE"]))
			$voice=$GLOBALS["PATCH_OVERRIDE_VOICE"];

		$data = array(
			'text' => $newString,
			'speaker_wav' => $voice,
			'language' => $lang
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

		if ($response === FALSE) {
			// Handle error
			error_log("Error occurred.".__FILE__);
			
			// Lets try to use standard scheme:
			$codename = str_replace(" ", "_", mb_strtolower($GLOBALS["HERIKA_NAME"], 'UTF-8'));
			$codename = str_replace("'", "+", $codename);

			$data = array(
				'text' => $newString,
				'speaker_wav' => $codename,
				'language' => $lang
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


		}


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
			// error_log("ffmpeg -y -i $oname  $FFMPEG_FILTER $fname ");
			$endTimeTrans = microtime(true)-$startTimeTrans;
			
            file_put_contents(dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "soundcache/" . md5(trim($stringforhash)) . ".txt", trim($textString) . "\n\rtotal call time:" . (microtime(true) - $starTime) . " ms\n\rffmpeg transcoding: $endTimeTrans secs\n\rsize of wav ($size)\n\rfunction tts($textString,$mood=\"cheerful\",$stringforhash)");
			$GLOBALS["DEBUG_DATA"][]=(microtime(true) - $starTime)." secs in xtts-fast-api call";
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



