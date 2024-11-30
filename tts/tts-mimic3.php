<?php

$localPath = dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR;
require_once($localPath . "conf".DIRECTORY_SEPARATOR."conf.php"); 


$GLOBALS["TTS_IN_USE"]=function($textString, $mood = "cheerful", $stringforhash='') {
    
    $start = microtime(true);

    $ttsServiceUri = $GLOBALS["TTS"]["MIMIC3"]["URL"] . "/api/tts";
    
    $testP="?text=".urlencode($textString);
    $voiceP="&voice=".urlencode($GLOBALS["TTS"]["MIMIC3"]["voice"]);

    if (isset($GLOBALS["PATCH_OVERRIDE_VOICE"]))
        $voiceP="&voice=".urlencode($GLOBALS["PATCH_OVERRIDE_VOICE"]);

    $noiseScaleP="&noiseScale=0.667";
    $noiseW="&noiseW=0.8";
    $lengthScaleP="&lengthScale=".urlencode($GLOBALS["TTS"]["MIMIC3"]["rate"]);
    $restP="&ssml=false&audioTarget=client";
    $result = file_get_contents($ttsServiceUri.$testP.$voiceP.$noiseScaleP.$noiseW.$lengthScaleP.$restP, false, $context);
     
    if (!$result) {
        file_put_contents(dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "soundcache/" . md5(trim($stringforhash)) . ".err", trim($data));
        return false;
    } else {
    }
   

    // Trying to avoid sync problems.
    $stream = fopen(dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "soundcache/" . md5(trim($stringforhash)) . ".wav", 'w');
    $size = fwrite($stream, $result);
    fsync($stream);
    fclose($stream);

    $end = microtime(true);

    $executionTime = ($end - $start);

    file_put_contents(dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "soundcache/" . md5(trim($stringforhash)) . ".txt", trim($data) . "\n\rsize of wav ($size)\n\rexecution time: $executionTime secs  function tts($textString,$mood=\"cheerful\",$stringforhash)");

    return "soundcache/" . md5(trim($stringforhash)) . ".wav";
    
    
};

function ttsMimicOld($textString, $mood = "cheerful", $stringforhash='')
{

    $start = microtime(true);

    $ttsServiceUri = $GLOBALS["TTS"]["MIMIC3"]["URL"] . "/api/tts";

    $doc = new DOMDocument();

    $root = $doc->createElement("speak");

    $voice = $doc->createElement("voice");
    $voice->setAttribute("name", $GLOBALS["TTS"]["MIMIC3"]["voice"]);

    $text = $doc->createTextNode($textString);


    $prosody = $doc->createElement("prosody");
    $prosody->setAttribute("rate", $GLOBALS["TTS"]["MIMIC3"]["rate"]);
    $prosody->setAttribute("volume", $GLOBALS["TTS"]["MIMIC3"]["volume"]);

    $sentence = $doc->createElement("s");
    $sentence->appendChild($text);
    $prosody->appendChild($sentence);

    $voice->appendChild($prosody);
    $root->appendChild($voice);
    $doc->appendChild($root);
    $data = $doc->saveXML();

    $options = array(
        'http' => array(
            'header' => "Content-type: application/ssml+xml\r\n" .
            "User-Agent: TTSPHP\r\n" .
            "content-length: " . strlen($data) . "\r\n",
            'method' => 'POST',
            'content' => $data,
        ),
    );

    $context = stream_context_create($options);

    if (is_array($GLOBALS["TTS_FFMPEG_FILTERS"])) {
        $GLOBALS["TTS_FFMPEG_FILTERS"]["adelay"]="adelay=150|150";
        $FFMPEG_FILTER='-af "'.implode(",",$GLOBALS["TTS_FFMPEG_FILTERS"]).'"';
        
    } else {
        $FFMPEG_FILTER='-filter:a "adelay=150|150"';
    }


    // get the wave data
    $result = file_get_contents($ttsServiceUri, false, $context);
    if (!$result) {
        file_put_contents(dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "soundcache/" . md5(trim($stringforhash)) . ".err", trim($data));
        return false;
    } else {
    }
   
    

    // Trying to avoid sync problems.
    $oname=dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "soundcache/" . md5(trim($stringforhash)) . "_o.wav";
    $stream = fopen($oname, 'w');
    $size = fwrite($stream, $result);
    fsync($stream);
    fclose($stream);

    $fname=dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "soundcache/" . md5(trim($stringforhash)) . ".wav";
   
    $startTimeTrans = microtime(true);
    shell_exec("ffmpeg -y -i $oname  $FFMPEG_FILTER $fname 2>/dev/null >/dev/null");
    error_log("ffmpeg -y -i $oname  $FFMPEG_FILTER $fname ");
    $endTimeTrans = microtime(true)-$startTimeTrans;
    
    file_put_contents(dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "soundcache/" . md5(trim($stringforhash)) . ".txt", trim($textString) . "\n\rtotal call time:" . (microtime(true) - $starTime) . " ms\n\rffmpeg transcoding: $endTimeTrans secs\n\rsize of wav ($size)\n\rfunction tts($textString,$mood=\"cheerful\",$stringforhash)");
    $GLOBALS["DEBUG_DATA"][]=(microtime(true) - $starTime)." secs in xtts-fast-api call";
    return "soundcache/" . md5(trim($stringforhash)) . ".wav";

}
