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

if (!function_exists('normalize_endpoint_url')) {
    function normalize_endpoint_url($url) {
        // Remove trailing slashes
        $url = rtrim($url, '/');
        return $url;
    }
}

function chatterbox_settings($settings,$resetAfter=false) {
	$url = normalize_endpoint_url($GLOBALS["TTS"]["CHATTERBOX"]["endpoint"]).'/set_tts_settings';
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
	
	if ($resetAfter)
		$GLOBALS["TTS"]["CHATTERBOX"]["RESET"]=true;

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
		Logger::error("Error occurred. ".__FILE__." ".__LINE__." ".__FUNCTION__);
	} else {
		;//ok
	}
}

// convert numbers into Japanese kanji
function num2kan_decimal($instr) {
    // Check if the input is exactly 0. Return katakana zero in that case.
    if ($instr === '0') {
        return 'ゼロ';
    }

	static $kantbl1 = array(0=>'', 1=>'一', 2=>'二', 3=>'三', 4=>'四', 5=>'五', 6=>'六', 7=>'七', 8=>'八', 9=>'九', '.'=>'．', '-'=>'－');
	static $kantbl2 = array(0=>'', 1=>'十', 2=>'百', 3=>'千');
	static $kantbl3 = array(0=>'', 1=>'万', 2=>'億', 3=>'兆', 4=>'京');

	$outstr = '';
	$len = strlen($instr);
	$m = (int)($len / 4);
	//repeat for each grouping of numbers (single digits, ten thousands, etc)
	for ($i = 0; $i <= $m; $i++) {
		$s2 = '';
		//repeat for each grouping of numbers inside a larger grouping (single digits, tens, hundreds, thousands)
		for ($j = 0; $j < 4; $j++) {
			$pos = $len - $i * 4 - $j - 1;
			if ($pos >= 0) {
				$ch  = substr($instr, $pos, 1);
				if ($ch == ',') continue;       //ignore commas
				$ch1 = isset($kantbl1[$ch]) ? $kantbl1[$ch] : '';
				$ch2 = isset($kantbl2[$j])  ? $kantbl2[$j]  : '';
				// handle case when leading one is present (10 should be 十 and not 一十)
				if ($ch1 != '') {
					if ($ch1 == '一' && $ch2 != '') $s2 = $ch2 . $s2;
					else                                $s2 = $ch1 . $ch2 . $s2;
				}
			}
		}
		if ($s2 != '')  $outstr = $s2 . $kantbl3[$i] . $outstr;
	}

	return $outstr;
}


$GLOBALS["TTS_IN_USE"]=function($textString, $mood , $stringforhash) {

		//chatterbox_settings([]); //Check this
		
		if (isset($GLOBALS["AVOID_TTS_CACHE"]) && $GLOBALS["AVOID_TTS_CACHE"]===false )
			if (file_exists(dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "soundcache/" . md5(trim($stringforhash)) . ".wav"))
				return dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "soundcache/" . md5(trim($stringforhash)) . ".wav";
		
		
		
		$newString=$textString;
		
	    $starTime = microtime(true);

		$url = normalize_endpoint_url($GLOBALS["TTS"]["CHATTERBOX"]["endpoint"])."/tts_to_audio/";

		// Request headers
		$headers = array(
			'Accept: audio/wav',
			'Content-Type: application/json'
		);
		
		$lang=isset($GLOBALS["TTS"]["FORCED_LANG_DEV"])?$GLOBALS["TTS"]["FORCED_LANG_DEV"]:$GLOBALS["TTS"]["CHATTERBOX"]["language"];
		
		
		if ((isset($GLOBALS["LLM_LANG"]))&&(isset($GLOBALS["LANG_LLM_XTTS"]))&&$GLOBALS["LANG_LLM_XTTS"]) {
			$lang=$GLOBALS["LLM_LANG"];

		}
		
		if (isset($GLOBALS["PATCH_OVERRIDE_TTS_LANGUAGE"]))
        	$lang=$GLOBALS["PATCH_OVERRIDE_TTS_LANGUAGE"];

		if (empty($lang))
			$lang=$GLOBALS["TTS"]["CHATTERBOX"]["language"];

		// Sanitize language code - remove any extra characters from LLM parsing
		// Valid XTTS language codes
		$validLangs = ['en', 'es', 'fr', 'de', 'it', 'pt', 'pl', 'tr', 'ru', 'nl', 'cs', 'ar', 'zh-cn', 'ja', 'hu', 'ko', 'hi'];
		$lang = preg_replace('/[^a-z\-]/i', '', strtolower(trim($lang ?? '')));
		if (!in_array($lang, $validLangs)) {
			Logger::warn("Invalid TTS language code '{$lang}', defaulting to 'en'");
			$lang = 'en';
		}

		// xtts has trouble reading numbers when lang is Japanese
		// PATCH it by converting numbers into kanji, which it can read
		if ($lang == 'ja') {
			$callback=function ($matches) {
				return num2kan_decimal($matches[0]);
			};
			// remove commas between digits
			$newString = preg_replace('/(?<=\d),(?=\d)/', '', $newString);
			// replace numbers with kanji
			$newString=preg_replace_callback('/\d+/', $callback, $newString);
		}
	
		// Hook. Last time transformations, custom settings for XTTS...
		if (isset($GLOBALS["HOOKS"]) && isset($GLOBALS["HOOKS"]["XTTS_TEXTMODIFIER"]) && is_array($GLOBALS["HOOKS"]["XTTS_TEXTMODIFIER"])) {
			foreach ($GLOBALS["HOOKS"]["XTTS_TEXTMODIFIER"] as $hook) {
				Logger::info("Calling hook.".__FILE__." ".__LINE__." ".__FUNCTION__);
				$newString=call_user_func($hook,$newString);
	
			}
		}
		$voice = $GLOBALS["PATCH_OVERRIDE_VOICE"]
			?? $GLOBALS["TTS"]["FORCED_VOICE_DEV"]
			?? ($GLOBALS["TTS"]["CHATTERBOX"]["voiceid"] ?? '');

		if (empty($voice))
			$voice = $GLOBALS["TTS"]["CHATTERBOX"]["voiceid"] ?? '';

		if ($GLOBALS)	
			$data = array(
				'text' => $newString,
				'speaker_wav' => $voice,
				'language' => $lang??'en'	//Defaults to english
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
			Logger::error("Error occurred. ".__FILE__." ".__LINE__." ".__FUNCTION__);
			
			// Lets try to use standard scheme:
			$codename = str_replace(" ", "_", mb_strtolower($GLOBALS["HERIKA_NAME"], 'UTF-8'));
			$codename = str_replace("'", "+", $codename);
			$codename=preg_replace('/[^a-zA-Z0-9_+]/u', '', $codename);
			
			$data = array(
				'text' => $newString,
				'speaker_wav' => $codename,
				'language' => $lang??"en"
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
		
		if (isset($GLOBALS["TTS"]["CHATTERBOX"]["RESET"]) && $GLOBALS["TTS"]["CHATTERBOX"]["RESET"]) {
			chatterbox_settings([]);
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
			//error_log("ffmpeg -y -i $oname  $FFMPEG_FILTER $fname ".__FILE__." ".__LINE__." ".__FUNCTION__);
			$endTimeTrans = microtime(true)-$startTimeTrans;
			
			$textString.=PHP_EOL.print_r($options,true);
			$textString.=PHP_EOL.print_r($http_response_header,true);
			
            file_put_contents(dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "soundcache/" . md5(trim($stringforhash)) . ".txt", trim($textString) . "\n$FFMPEG_FILTER\n\rtotal call time:" . (microtime(true) - $starTime) . " ms\n\rffmpeg transcoding: $endTimeTrans secs\n\rsize of wav ($size)\n\rfunction tts($textString,$mood=\"cheerful\",$stringforhash)");
			$GLOBALS["DEBUG_DATA"][]=(microtime(true) - $starTime)." secs in chatterbox call";

			if (isset($GLOBALS["DEVELOP_STORE_AUDIO_FOR_TRANING"]) && $GLOBALS["DEVELOP_STORE_AUDIO_FOR_TRANING"]) {
				$rootPath=dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "soundcache/" ;
				$tfolder=$rootPath."/$voice";
				@mkdir($tfolder);
				copy($fname,$tfolder."/".basename($fname));

			}
			return "soundcache/" . md5(trim($stringforhash)) . ".wav";
			
		} else {
			$textString.=PHP_EOL.print_r($options,true);
			$textString.=PHP_EOL.print_r(isset($http_response_header) ? $http_response_header : 'No HTTP response headers available',true);
			file_put_contents(dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "soundcache/" . md5(trim($stringforhash)) . ".err", trim($textString));
            return false;
			
		}

};

/*
$GLOBALS["TTS"]["CHATTERBOX"]["endpoint"]='http://localhost:8023';
$GLOBALS["TTS"]["CHATTERBOX"]["voiceid"]='svenja';
$GLOBALS["TTS"]["CHATTERBOX"]["language"]='en';

$textTosay="Hello fellows...this is a new text to speech connector";

echo tts($textTosay,'',$textTosay).PHP_EOL;
*/



