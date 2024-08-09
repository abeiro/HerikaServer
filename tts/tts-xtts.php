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
  
function tts($textString, $mood , $stringforhash) {


		if (!isset($GLOBALS["AVOID_TTS_CACHE"]))
			if (file_exists(dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "soundcache/" . md5(trim($stringforhash)) . ".wav"))
				return dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "soundcache/" . md5(trim($stringforhash)) . ".wav";
	
	    $starTime = microtime(true);

		$url = $GLOBALS["TTS"]["XTTS"]["endpoint"]."tts_stream";

		// Request headers
		$headers = array(
			'Accept: audio/wav',
			'Content-Type: application/json'
		);
		
		$lang=isset($GLOBALS["TTS"]["FORCED_LANG_DEV"])?$GLOBALS["TTS"]["FORCED_LANG_DEV"]:$GLOBALS["TTS"]["XTTS"]["language"];
		if (empty($lang))
			$lang=$GLOBALS["TTS"]["XTTS"]["language"];
	
		// Request data
		$data = array(
			'text' => $textString,
			'language' => $lang
		);
		
		$voice=isset($GLOBALS["TTS"]["FORCED_VOICE_DEV"])?$GLOBALS["TTS"]["FORCED_VOICE_DEV"]:$GLOBALS["TTS"]["XTTS"]["voiceid"];
		
		if (empty($voice))
			$voice=$GLOBALS["TTS"]["XTTS"]["voiceid"];
	
		$data_voice=json_decode(file_get_contents(__DIR__.DIRECTORY_SEPARATOR."data".DIRECTORY_SEPARATOR."{$voice}.json"),true);
		

		$datfinal=array_merge($data,$data_voice);
		
		//$GLOBALS["DEBUG_DATA"]["coqui"][]=$datfinal;
		// Create stream context options
		$options = array(
			'http' => array(
				'method' => 'POST',
				'header' => implode("\r\n", $headers),
				'content' => json_encode($datfinal)
			)
		);

		// Create stream context
		$context = stream_context_create($options);

		// Send the request
		$response = file_get_contents($url, false, $context);

		
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
			$GLOBALS["DEBUG_DATA"][]=(microtime(true) - $starTime)." secs in coaqui-ai call";
			return "soundcache/" . md5(trim($stringforhash)) . ".wav";
			
		} else {
			$textString.=print_r($http_response_header,true);
			file_put_contents(dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "soundcache/" . md5(trim($stringforhash)) . ".err", trim($textString));
            return false;
			
		}

}
