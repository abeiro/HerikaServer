<?php


function tts($textString, $mood , $stringforhash) {


		$apiKey=$GLOBALS["TTS"]["openai"]["API_KEY"];

		// Cache 
		if (!isset($GLOBALS["AVOID_TTS_CACHE"]))
			if (file_exists(dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "soundcache/" . md5(trim($stringforhash)) . ".wav"))
				return dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "soundcache/" . md5(trim($stringforhash)) . ".wav";
	
	    $starTime = microtime(true);

		$url = $GLOBALS["TTS"]["openai"]["endpoint"]; //"https://api.openai.com/v1/audio/speech";

		// Request headers
		$headers = array(
			"Authorization: Bearer $apiKey",
			'Content-Type: application/json'
		);
		
			
		// Request data
		$data = array(
			'input' => "$textString",
			'model' => $GLOBALS["TTS"]["openai"]["model_id"],
			'voice' => $GLOBALS["TTS"]["openai"]["voice"],
			'style' => $GLOBALS["TTS"]["openai"]["style"]+0
			);


		// Create stream context options
		$options = array(
			'http' => array(
				'method' => 'POST',
				'header' => implode("\r\n", $headers),
				'content' => json_encode($data)
			)
		);

		;
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
			shell_exec("ffmpeg -y -i $mp3Name -filter:a \"speechnorm=e=6:r=0.0001:l=1\" $wavName 2>/dev/null >/dev/null");
			$endTimeTrans = microtime(true)-$startTimeTrans;
            //file_put_contents(dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "soundcache/" . md5(trim($stringforhash)) . ".mp3", trim($response));
			//$finalData=MP3toWav($response,strlen($response));
			//$size=strlen($finalData);
			//file_put_contents(
			//	dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "soundcache/" . md5(trim($stringforhash)) . ".wav"
			//	, $finalData); // Save the audio response to a file
			//
			
            file_put_contents(dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "soundcache/" . md5(trim($stringforhash)) . ".txt", trim($textString) . "\n\rtotal call time:" . (microtime(true) - $starTime) . " ms\n\r\n\rfunction tts($textString,$mood=\"cheerful\",$stringforhash)");
			$GLOBALS["DEBUG_DATA"][]=(microtime(true) - $starTime)." secs in 11labs call and $endTimeTrans microseconds in ffmpeg transcoding";
			return "soundcache/" . md5(trim($stringforhash)) . ".wav";
			
		} else {
			$textString.=print_r($http_response_header,true);
			file_put_contents(dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "soundcache/" . md5(trim($stringforhash)) . ".err", trim($textString));
            return false;
			
		}

}
