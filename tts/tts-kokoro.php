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


		$voiceMap = [
			"maleorc" => "am_fenrir+am_onyx",
			"femaleshrill" => "af_sky",
			"nmeridia04tahlanivoice" => "af_nova",
			"maleuniquegalmar" => "am_michael",
			"gurund_whiterun_guard" => "am_eric",
			"crhagravenvoice" => "af_kore+af_heart",
			"maleuniquecicero" => "am_puck",
			"dlc1maleuniqueflorentius" => "am_liam",
			"maleguard" => "am_eric",
			"femalecommoner" => "af_jessica",
			"nmeridia08cleansmanybootsvoice" => "af_nicole",
			"nmeridia04anvarilvoice" => "af_aoede",
			"femaledarkelf" => "af_river",
			"femalecoward" => "af_bella",
			"maleuniqueseptimus" => "am_v0gurney+am_santa",
			"maleoldgrumpy" => "am_santa+am_adam",
			"maleeventonedaccented" => "am_puck",
			"nmeridiagarinvoice" => "af_v0irulan",
			"dlc1maleuniquejiub" => "am_liam",
			"maleuniqueaventusaretino" => "am_puck",
			"liviasalvianvoice" => "af_heart",
			"femaleneivavoice" => "af_nova",
			"nmeridiafindarvoice" => "af_v0nicole",
			"femalekhajiit" => "af_jadzia",
			"nmeridia06vaegravoice" => "af_sarah",
			"TheNarratorFemale" => "af_heart",
			"dlc2maledarkelfcynical" => "am_onyx",
			"specialfemaleuniquegormlaith" => "af_aoede",
			"maleuniquekodlakwhitemane" => "am_santa+am_eric",
			"maleuniquebrynjolf" => "am_adam",
			"malecommoneraccented" => "am_puck",
			"femaleuniquedelphine" => "af_nova",
			"femalecondescending"=>"af_nova+af_aoede",
			"The Narrator" => "am_santa",
			"TheNarrator" => "am_santa",
			"femalesultry" => "af_river",
			"dlc1maleuniquesnowelfghost" => "am_v0gurney",
			"malecommoner" => "am_adam",
			"nmeridiaalaravoice" => "af_v0sky",
			"maleeventoned" => "am_liam",
			"AQTalosVoice" => "am_santa",
			"malenordcommander" => "am_michael",
			"malenord" => "am_michael",
			"dlc1malevampire" => "am_onyx+am_fenrir",
			"crdremoravoice" => "am_fenrir+am_onyx",
			"femaleelfhaughty" => "af_kore",
			"dlc1maleuniqueisran" => "am_michael",
			"maleuniquenazir" => "am_onyx",
			"femaleoldgrumpy" => "af_v0bella+af_aoede",
			"femaleoldkindly" => "af_heart",
			"nmeridiamartihvoice" => "af_v0sarah",
			"femalenord" => "af_sky",
			"dlc1seranavoice" => "af_river",
			"maleoldkindly" => "am_santa",
			"specialmaleuniquehakon" => "am_v0gurney+am_fenrir",
			"dlc2maleuniqueneloth" => "am_puck",
			"maleuniquetullius" => "am_michael",
			"femaleuniqueastrid" => "af_nova",
			"dlc1maleuniqueharkon" => "am_onyx+am_echo",
			"maleuniquearngeir" => "am_santa+am_echo",
			"femalecommander" => "af_sky",
			"malesoldier" => "am_eric",
			"malecommander" => "am_michael",
			"maleslycynical" => "am_onyx",
			"dlc1femaleuniquefura" => "af_kore",
			"dlc1maleuniquegelebor" => "am_onyx",
			"dlc2maledarkelfcommoner" => "am_onyx",
			"maleuniqueghostsvaknir" => "am_v0gurney",
			"nmeridiafiyavoice" => "af_nicole",
			"maledarkelf" => "am_onyx",
			"femaleuniqueghost" => "af_v0irulan",
			"malebrute" => "am_fenrir",
			"femaleuniquemeridia" => "af_nova",
			"dlc1maleuniquevyrthur" => "am_echo",
			"malecoward" => "am_puck",
			"dlc1femaleuniquevalerica" => "af_heart",
			"maleforsworn" => "am_fenrir+am_onyx",
			"maleuniquehircine" => "am_fenrir+am_v0gurney",
			"dlc2maleuniqueadril" => "am_onyx",
			"dlc1maleuniquedexion" => "am_v0gurney",
			"maleuniquedbguardian" => "am_fenrir",
			"maleuniqueamaundmotierre" => "am_puck",
			"maleyoungeager" => "am_liam",
			"maleuniquedelvinmallory" => "am_eric",
			"cruniquepaarthurnax" => "am_santa+am_echo",
			"maleuniquegallus" => "am_echo",
			"femaleeventoned" => "af_sarah",
			"maleargonian" => "am_fenrir",
			"femaleuniquevex" => "af_jessica",
			"NMeridiaJailorVoice" => "af_v0nicole",
			"malewarlock" => "am_onyx+am_echo",
			"maledrunk" => "am_santa+am_v0gurney",
			"femaleargonian" => "af_kore+af_jadzia",
			"dlc2femaledarkelfcommoner" => "af_river",
			"nmeridia04adelaidevoice" => "af_aoede"
		];

		$data = array(
            'model'=>'kokoro',
			'input' => $newString,
			'voice' => $voiceMap[$voice],
			'response_format' => 'wav',
            'speed' => $GLOBALS["TTS"]["KOKORO"]["speed"],
            'stream'=>true,
			"lang_code"=>"a" // wtf?


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
			file_put_contents(dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "soundcache/" . md5(trim($stringforhash)) . ".err", trim($textString)."\n".json_encode($data));
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



