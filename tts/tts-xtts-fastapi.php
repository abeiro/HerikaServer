<?php



/*
 * Clone voice 
 * 
curl -X 'POST' \
  'https://k-looked-appointments-ordered.trycloudflare.com/clone_speaker' \
  -H 'accept: application/json' \
  -H 'Content-Type: multipart/form-data' \
  -F 'wav_file=@bella.wav;type=audio/wav'

*/

/*
 "en", "de", "fr", "es", "it", "pl", "pt", "tr", "ru", "nl", "cs", "ar", "zh", "ja", "hu", "ko",
 
 */

function xtts_fastapi_settings($settings) {
	$url = $GLOBALS["TTS"]["XTTSFASTAPI"]["endpoint"].'/set_tts_settings';
	$data = json_decode('{
		"stream_chunk_size": 20,
		"temperature": 1,
		"speed": 1,
		"length_penalty": 0,
		"repetition_penalty": 5,
		"top_p": 0.5,
		"top_k": 50,
		"enable_text_splitting": true
		}',true);
	
	$finalData=array_merge($settings,$data);
	
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

function tts($textString, $mood , $stringforhash) {

		//xtts_fastapi_settings([]); //Check this
		
		if (!isset($GLOBALS["AVOID_TTS_CACHE"]))
			if (file_exists(dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "soundcache/" . md5(trim($stringforhash)) . ".wav"))
				return dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "soundcache/" . md5(trim($stringforhash)) . ".wav";
	
	    $starTime = microtime(true);

		$url = $GLOBALS["TTS"]["XTTSFASTAPI"]["endpoint"]."/tts_to_audio";

		// Request headers
		$headers = array(
			'Accept: audio/wav',
			'Content-Type: application/json'
		);
		
		$lang=isset($GLOBALS["TTS"]["FORCED_LANG_DEV"])?$GLOBALS["TTS"]["FORCED_LANG_DEV"]:$GLOBALS["TTS"]["XTTSFASTAPI"]["language"];
		if (empty($lang))
			$lang=$GLOBALS["TTS"]["XTTSFASTAPI"]["language"];
	
		// Request data
		$data = array(
			'text' => $textString,
			'language' => $lang
		);
		
		$voice=isset($GLOBALS["TTS"]["FORCED_VOICE_DEV"])?$GLOBALS["TTS"]["FORCED_VOICE_DEV"]:$GLOBALS["TTS"]["XTTSFASTAPI"]["voiceid"];
		
		if (empty($voice))
			$voice=$GLOBALS["TTS"]["XTTSFASTAPI"]["voiceid"];
	
		

		$data = array(
			'text' => $textString,
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
			return "";
		}
		
		// Handle the response
		if ($response !== false ) {
			// Handle the successful response
			$size=strlen($response);
			$oname=dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "soundcache/" . md5(trim($stringforhash)) . "_o.wav";
			$fname=dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "soundcache/" . md5(trim($stringforhash)) . ".wav";
			
			file_put_contents($oname, $response); // Save the audio response to a file
			$startTimeTrans = microtime(true);
			shell_exec("ffmpeg -y -i $oname -filter:a \"speechnorm=e=6:r=0.0001:l=1\" $fname 2>/dev/null >/dev/null");
			$endTimeTrans = microtime(true)-$startTimeTrans;
			
            file_put_contents(dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "soundcache/" . md5(trim($stringforhash)) . ".txt", trim($textString) . "\n\rtotal call time:" . (microtime(true) - $starTime) . " ms\n\rsize of wav ($size)\n\rfunction tts($textString,$mood=\"cheerful\",$stringforhash)");
			$GLOBALS["DEBUG_DATA"][]=(microtime(true) - $starTime)." secs in xtts-fast-api call";
			return "soundcache/" . md5(trim($stringforhash)) . ".wav";
			
		} else {
			$textString.=print_r($http_response_header,true);
			file_put_contents(dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "soundcache/" . md5(trim($stringforhash)) . ".err", trim($textString));
            return false;
			
		}

}

/*
$GLOBALS["TTS"]["XTTSFASTAPI"]["endpoint"]='http://localhost:8020';
$GLOBALS["TTS"]["XTTSFASTAPI"]["voiceid"]='jenassa';
$GLOBALS["TTS"]["XTTSFASTAPI"]["language"]='en';

$textTosay="Hello fellows...this is a new text to speech connector";

echo tts($textTosay,'',$textTosay);
*/



