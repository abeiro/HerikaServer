<?php

/*
curl -X 'POST' \
  'http://localhost:8084/tts' \
  -H 'accept: application/json' \
  -H 'Content-Type: application/json' \
  -d '{
  "speaker": "malenord",
  "text": "In Skyrim\'s land of snow and ice, Where dragons soar and souls entwine, Heroes rise, their fate unveiled, As ancient tales, the land does bind.",
  "speed": 1,
  "language": "EN"
}'
*/

$GLOBALS["TTS_IN_USE"]=function($textString, $mood , $stringforhash) {
    
    $newString=$textString;
    
    $starTime = microtime(true);

    
    $lang=isset($GLOBALS["TTS"]["FORCED_LANG_DEV"])?$GLOBALS["TTS"]["FORCED_LANG_DEV"]:$GLOBALS["TTS"]["MELOTTS"]["language"];
    
    
    if ((isset($GLOBALS["LLM_LANG"]))&&(isset($GLOBALS["LANG_LLM_XTTS"]))&&$GLOBALS["LANG_LLM_XTTS"]) {
        $lang=$GLOBALS["LLM_LANG"];

    }
    

    if (empty($lang))
        $lang=$GLOBALS["TTS"]["MELOTTS"]["language"];


    

    $voice=$GLOBALS["TTS"]["MELOTTS"]["voiceid"];
    
    if (empty($voice))
        $voice=$GLOBALS["TTS"]["MELOTTS"]["voiceid"];


    if (empty($voice)) {

        $codename=strtr(strtolower(trim($GLOBALS["HERIKA_NAME"])),[" "=>"_","'"=>"+"]);
        $codename=preg_replace('/[^a-zA-Z0-9_+]/u', '', $codename);

        $cn=$GLOBALS["db"]->escape("Voicetype/$codename");
        $vtype=$GLOBALS["db"]->fetchAll("select value from conf_opts where id='$cn'");
        $voicetypeString=(isOk($vtype))?$vtype[0]["value"]:null;
        $voicetype=explode("\\",$voicetypeString);
        $voice=strtolower($voicetype[3]);
    }
    

    if (isset($GLOBALS["PATCH_OVERRIDE_VOICE"]))
        $voice=$GLOBALS["PATCH_OVERRIDE_VOICE"];
 

    $speed=$GLOBALS["TTS"]["MELOTTS"]["speed"]+0.01;


    if (empty($voice))
        error_log("Error, voiceid is no set");


    $finalData =["speaker"=>"$voice","text"=>"$textString","language"=>"EN","speed"=>$speed];
    //print_r($finalData);
	
	$options = array(
		'http' => array(
			'header' => "Content-type: application/json\r\n" .
						"Accept: application/json\r\n",
			'method' => 'POST',
			'content' => json_encode($finalData)
		)
	);
	$context = stream_context_create($options);

    $url="{$GLOBALS["TTS"]["MELOTTS"]["endpoint"]}/tts";
    
    $result = file_get_contents($url, false, $context);
    
    if (is_array($GLOBALS["TTS_FFMPEG_FILTERS"])) {
        $GLOBALS["TTS_FFMPEG_FILTERS"]["adelay"]="adelay=150|150";
        $FFMPEG_FILTER='-af "'.implode(",",$GLOBALS["TTS_FFMPEG_FILTERS"]).'"';
        
    } else {
        $FFMPEG_FILTER='-filter:a "adelay=150|150"';
    }


    // Handle the response
    if ($result !== false ) {
        $response = $result;
        // Handle the successful response
        $size=strlen($response);
        $oname=dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "soundcache/" . md5(trim($stringforhash)) . "_o.wav";
        $fname=dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "soundcache/" . md5(trim($stringforhash)) . ".wav";
        
        file_put_contents($oname, $response); // Save the audio response to a file

        $startTimeTrans = microtime(true);
        //shell_exec("ffmpeg -y -i $oname  -af \"adelay=150|150,silenceremove=start_periods=1:start_silence=0.1:start_threshold=-25dB,areverse,silenceremove=start_periods=1:start_silence=0.1:start_threshold=-40dB,areverse,speechnorm=e=3:r=0.0001:l=1:p=0.75\" $fname 2>/dev/null >/dev/null");
        shell_exec("ffmpeg -y -i $oname  $FFMPEG_FILTER $fname 2>/dev/null >/dev/null");
        $endTimeTrans = microtime(true)-$startTimeTrans;
        
        file_put_contents(dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "soundcache/" . md5(trim($stringforhash)) . ".txt", trim($textString) . "\n\rtotal call time:" . (microtime(true) - $starTime) . " ms\n\rffmpeg transcoding: $endTimeTrans secs\n\rsize of wav ($size)\n\rfunction tts($textString,$mood=\"cheerful\",$stringforhash)");
        $GLOBALS["DEBUG_DATA"][]=(microtime(true) - $starTime)." secs in melotts call";
        return "soundcache/" . md5(trim($stringforhash)) . ".wav";
        
    } 


};


/*
$GLOBALS["TTS"]["MELOTTS"]["endpoint"]='http://127.0.0.1:8084';
$GLOBALS["TTS"]["MELOTTS"]["voiceid"]='malenord';
$GLOBALS["TTS"]["MELOTTS"]["language"]='EN';
$GLOBALS["TTS"]["MELOTTS"]["speed"]=1;


$textTosay="In Skyrim's land of snow and ice, Where dragons soar and souls entwine, Heroes rise, their fate unveiled, As ancient tales, the land does bind.";

echo tts($textTosay,'',$textTosay).PHP_EOL;
*/

?>
