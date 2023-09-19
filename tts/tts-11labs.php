<?php


function tts($textString, $mood = "default", $stringforhash) {

	    global $ELEVEN_LABS,$ELEVENLABS_API_KEY;

		$apiKey=$ELEVENLABS_API_KEY;

	    $starTime = microtime(true);

		$url = "https://api.elevenlabs.io/v1/text-to-speech/{$ELEVEN_LABS["voice_id"]}?{$ELEVEN_LABS["optimize_streaming_latency"]}=1";

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
			
		// Request data
		$data = array(
			'text' => $textString,
			'model_id' => 'eleven_monolingual_v1',
			'voice_settings' => array(
				'stability' => $ELEVEN_LABS["stability"]+0.0,
				'similarity_boost' => $ELEVEN_LABS["similarity_boost"]+0.0
			)
		);

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
			require_once(__DIR__.DIRECTORY_SEPARATOR."../lib/mp3riffer.php");
			$finalData=MP3toWav($response,strlen($response));

			file_put_contents(
				dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "soundcache/" . md5(trim($stringforhash)) . ".wav"
				, $finalData); // Save the audio response to a file

            file_put_contents(dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "soundcache/" . md5(trim($stringforhash)) . ".txt", trim($textString) . "\n\rCache:$cacheUsed\n\rtotal call time:" . (microtime(true) - $starTime) . " ms\n\rsize of wav ($size)\n\rfunction tts($textString,$mood=\"cheerful\",$stringforhash)");
			$GLOBALS["DEBUG_DATA"][]=(microtime(true) - $starTime)." secs in 11labs call and mp3riffer";
			return "soundcache/" . md5(trim($stringforhash)) . ".wav";
			
		} else {
			$textString.=print_r($http_response_header,true);
			file_put_contents(dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "soundcache/" . md5(trim($stringforhash)) . ".err", trim($textString));
            return false;
			
		}

}
