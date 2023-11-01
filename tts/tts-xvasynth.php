<?php


function tts($textString, $mood = "default", $stringforhash) {


		$apiEndpoint = $GLOBALS["TTS"]["XVASYNTH"]["url"];
		$fileName=md5(trim($stringforhash));
		
		$jsonData=array(
			"sequence" => "$textString",
			"editorStyles" => (object)[],
			"pace" => $GLOBALS["TTS"]["XVASYNTH"]["pace"],
			"base_lang" => $GLOBALS["TTS"]["XVASYNTH"]["base_lang"],
			"base_emb" => array(),
			"modelType" => $GLOBALS["TTS"]["XVASYNTH"]["modelType"],
			"useSR" => false,
			"useCleanup" => false,
			"outfile" => "\\\\wsl.localhost\\DwemerAI4Skyrim2\\var\\www\\html\\HerikaServer\\soundcache\\{$fileName}.wav",
			"pluginsContext" => "{}",
			"vocoder" => $GLOBALS["TTS"]["XVASYNTH"]["vocoder"],
			"waveglowPath" => $GLOBALS["TTS"]["XVASYNTH"]["waveglowPath"]
		);
		
		$context = stream_context_create([
			'http' => [
				'method' => 'POST',
				'header' => "Content-Type: text/plain;charset=UTF-8\r\n",
				'content' => json_encode($jsonData),
			],
		]);
		
		$GLOBALS["DEBUG_DATA"]["request"]=json_encode($jsonData);

		// Send the POST request with the specified headers
		$response = file_get_contents("$apiEndpoint/synthesize", false, $context);
		
		$GLOBALS["DEBUG_DATA"]["response"]=$response;
		
		// Handle the response
		if ($response !== false ) {
			// Handle the successful response
			
			return "soundcache/" . md5(trim($stringforhash)) . ".wav";
			
		} else {
			$textString.=print_r($http_response_header,true);
			$GLOBALS["DEBUG_DATA"]["error"]="$textString";
			file_put_contents(dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "soundcache/" . md5(trim($stringforhash)) . ".err", trim($textString));
            return false;
			
		}

}
