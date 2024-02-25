<?php


function tts($textString, $mood , $stringforhash) {

		$GLOBALS["DATA_PATH"]=__DIR__."/../soundcache/";
		
		if (!isset($GLOBALS["AVOID_TTS_CACHE"]))
			if (file_exists($GLOBALS["DATA_PATH"]."/".session_id()."/"  . md5(trim($stringforhash)) . ".wav"))
				return $GLOBALS["DATA_PATH"]."/".session_id()."/"  . md5(trim($stringforhash)) . ".wav";
	
	    $starTime = microtime(true);

		$url = filter_var("{$GLOBALS["TTS"]["STYLETTSV2"]["endpoint"]}", FILTER_SANITIZE_URL, FILTER_FLAG_PATH_REQUIRED);

		$voiceFile=__DIR__."/../data/voices/{$GLOBALS["TTS"]["STYLETTSV2"]["voice"]}";
					
		if (!isset($GLOBALS["TTS"]["STYLETTSV2"]["voice"]) || !file_exists($voiceFile)) 
			return "";
		/*
		// Set headers
		$ch = curl_init($url."session/new");

		// Set cURL options
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, [
			'voice' => new \CURLFile($voiceFile),
		]);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		// Execute cURL session
		$response = curl_exec($ch);

		// Check for cURL errors
		if (curl_errno($ch)) {
			echo 'Curl error: ' . curl_error($ch);
		}

		// Close cURL session
		curl_close($ch);

		$responseJ=json_decode($response,true);
		
		$voice=$responseJ["voice"];
		$session=$responseJ["session_id"];*/
		
		// Request headers
		$headers = array(
			'Accept: audio/wav',
			'Content-Type: application/json'
		);
		
		// Request data
		$data = array(
			'text' =>"$textString", //U+2026
			'alpha'=> $GLOBALS["TTS"]["STYLETTSV2"]["alpha"],
			'beta'=> $GLOBALS["TTS"]["STYLETTSV2"]["beta"],
			'diffusion_steps'=> $GLOBALS["TTS"]["STYLETTSV2"]["diffusion_steps"],
			'embedding_scale'=> $GLOBALS["TTS"]["STYLETTSV2"]["embedding_scale"],
			'sessionId'=> 12345//$responseJ["session_id"]
		);
		
		error_log("Using voice: $voiceFile");
		
		$options = array(
			'http' => array(
				'method' => 'POST',
				'header' => implode("\r\n", $headers),
				'content' => json_encode($data)
			)
		);

		// Create stream context
		$context = stream_context_create($options);

		// Send the request
		$response = file_get_contents($url."tts", false, $context);

		
		// Handle the response
		if ($response !== false ) {
			// Handle the successful response
			$size=strlen($response);
			$oname=$GLOBALS["DATA_PATH"]."/" . md5(trim($stringforhash)) . ".wav";
			
			file_put_contents($oname, ($response)); // Save the audio response to a file
			$startTimeTrans = microtime(true);
			//shell_exec("ffmpeg -y -i $oname -filter:a \"highpass=f=200, lowpass=f=3000\"  $fname 2>/dev/null >/dev/null");
			$endTimeTrans = microtime(true)-$startTimeTrans;
			
            file_put_contents($GLOBALS["DATA_PATH"] ."/" . md5(trim($stringforhash)) . ".txt", trim($textString) . "\n\rtotal call time:" . (microtime(true) - $starTime) . " ms\n\rsize of wav ($size)\n\rfunction tts($textString,$mood=\"cheerful\",$stringforhash)");
			$GLOBALS["DEBUG_DATA"][]=(microtime(true) - $starTime)." secs in stylettsv2 call";
			return $GLOBALS["DATA_PATH"]."/"  . md5(trim($stringforhash)) . ".wav";
			
		} else {
			$textString.=print_r($http_response_header,true);
			file_put_contents($GLOBALS["DATA_PATH"]."/"  . md5(trim($stringforhash)) . ".err", trim($textString));
            return false;
			
		}

}


if (true &&  (php_sapi_name()=="cli") ) {
	 $GLOBALS["DATA_PATH"]="/var/www/html/AIConnectX/data/";
	 echo tts($argv[1], "" , $argv[1]);
	
}

?>
