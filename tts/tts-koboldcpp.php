<?php


$GLOBALS["TTS_IN_USE"]=function($textString, $mood , $stringforhash) {

    // Cache 
		if (!isset($GLOBALS["AVOID_TTS_CACHE"]))
			if (file_exists(dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "soundcache/" . md5(trim($stringforhash)) . ".wav"))
				return dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "soundcache/" . md5(trim($stringforhash)) . ".wav";
	
		$url = $GLOBALS["TTS"]["koboldcpp"]["endpoint"]; //http://127.0.0.1:5001/api/extra/tts

		// Request headers
		$headers = array(
			"accept: audio/wav",
			'Content-Type: application/json'
		);
		
			
		// Request data
		$data = array(
			'input' => "$textString",
			'voice' => $GLOBALS["TTS"]["koboldcpp"]["voice"],
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
			$wavName=dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "soundcache/" . md5(trim($stringforhash)) . ".wav";
			file_put_contents($wavName, trim($response));
			return "soundcache/" . md5(trim($stringforhash)) . ".wav";
		} else {
			$textString.=print_r($http_response_header,true);
			file_put_contents(dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "soundcache/" . md5(trim($stringforhash)) . ".err", trim($textString));
            return false;
			
		}

};
