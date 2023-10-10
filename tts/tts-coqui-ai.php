<?php


function tts($textString, $mood = "default", $stringforhash) {

		$apiKey=$GLOBALS["TTS"]["COQUI_AI"]["API_KEY"];

	    $starTime = microtime(true);

		$url = "https://app.coqui.ai/api/v2/samples/xtts/stream";

		// Request headers
		$headers = array(
			'Accept: audio/wav',
			"authorization: Bearer $apiKey",
			'Content-Type: application/json'
		);
		
		// 11labs does not have sggml styles, but support some kinf of prompting
		/*if ($mood!="default") {
			$textString="\"$textString\" she said $mood";
		}*/
			
		// Request data
		$data = array(
			'text' => $textString,
			'speed' => $GLOBALS["TTS"]["COQUI_AI"]["speed"]+0,
			'voice_id' => $GLOBALS["TTS"]["COQUI_AI"]["voice_id"],
			'language' => $GLOBALS["TTS"]["COQUI_AI"]["language"],
			
		);

		$GLOBALS["DEBUG_DATA"]["coqui"][]=$data;
		// Create stream context options
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
		$response = file_get_contents($url, false, $context);

		
		// Handle the response
		if ($response !== false ) {
			// Handle the successful response
			$size=strlen($response);
			file_put_contents(
				dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "soundcache/" . md5(trim($stringforhash)) . ".wav"
				, $response); // Save the audio response to a file

            file_put_contents(dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "soundcache/" . md5(trim($stringforhash)) . ".txt", trim($textString) . "\n\rtotal call time:" . (microtime(true) - $starTime) . " ms\n\rsize of wav ($size)\n\rfunction tts($textString,$mood=\"cheerful\",$stringforhash)");
			$GLOBALS["DEBUG_DATA"][]=(microtime(true) - $starTime)." secs in coaqui-ai call";
			return "soundcache/" . md5(trim($stringforhash)) . ".wav";
			
		} else {
			$textString.=print_r($http_response_header,true);
			file_put_contents(dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "soundcache/" . md5(trim($stringforhash)) . ".err", trim($textString));
            return false;
			
		}

}
