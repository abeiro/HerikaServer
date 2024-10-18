<?php
$localPath = dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR;
require_once($localPath . "conf".DIRECTORY_SEPARATOR."conf.php"); // API KEY must be there
require_once($localPath . "lib".DIRECTORY_SEPARATOR."sharedmem.class.php"); // Caching token

function tts($textString, $mood , $stringforhash)
{
    if (!isset($GLOBALS["AVOID_TTS_CACHE"]))
        if (file_exists(dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "soundcache/" . md5(trim($stringforhash)) . ".wav"))
            return dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "soundcache/" . md5(trim($stringforhash)) . ".wav";
    
    if (empty($mood))
        $mood="default";
    
    $region = $GLOBALS["TTS"]["AZURE"]["region"];
    $AccessTokenUri = "https://" . $region . ".api.cognitive.microsoft.com/sts/v1.0/issueToken";
    $apiKey = $GLOBALS["TTS"]["AZURE"]["API_KEY"];

    if (empty(trim($mood)))
        $mood = "default";

    if ($GLOBALS["TTS"]["AZURE"]["validMoods"])
        $valid_tokens = $GLOBALS["TTS"]["AZURE"]["validMoods"];
    else
        $valid_tokens = array('angry', 'cheerful', 'assistant', 'calm', 'embarrassed', 'excited', 'lyrical', 'sad', 'shouting', 'whispering', 'terrified');


    if (in_array($mood, $valid_tokens))
        $validMood = $mood;
    else
        $validMood = "default";

    if ($validMood=="dazed")
        $OverWriteRate=0.7;
    
    if ($validMood=="angry") {
        if (!isset($GLOBALS["TEMP"]["VOLHACK"])) {
            $GLOBALS["TEMP"]["VOLHACK"]=$GLOBALS["TTS"]["AZURE"]["volume"];
        }
        $GLOBALS["TTS"]["AZURE"]["volume"]=$GLOBALS["TEMP"]["VOLHACK"]+20;
        
    } else {
        if (isset($GLOBALS["TEMP"]["VOLHACK"])) 
            $GLOBALS["TTS"]["AZURE"]["volume"]=$GLOBALS["TEMP"]["VOLHACK"];
    }
    
    $starTime = microtime(true);

    $cache = new CacheManager();

    if (!$cache->get_cache()) {


        $options = array(
            'http' => array(
                'header' => "Ocp-Apim-Subscription-Key: " . $apiKey . "\r\n" .
                "content-length: 0\r\n",
                'method' => 'POST',
            ),
        );

        $context = stream_context_create($options);

        //get the Access Token
        $access_token = file_get_contents($AccessTokenUri, false, $context);
        $cache->save_cache($access_token);
        $cacheUsed = "false";
    } else {
        $access_token = $cache->get_cache();
        $cacheUsed = "yes";
    }


    if (!$access_token) {
        return false;
    } else {
        //echo "Access Token: ". $access_token. "<br>";


        $ttsServiceUri = "https://" . $region . ".tts.speech.microsoft.com/cognitiveservices/v1";

        //$SsmlTemplate = "<speak version='1.0' xml:lang='en-us'><voice xml:lang='%s' xml:gender='%s' name='%s'>%s</voice></speak>";
        $doc = new DOMDocument();

        $root = $doc->createElement("speak");
        $root->setAttribute("version", "1.0");
        $root->setAttribute("xml:lang", "en-us");
        $root->setAttribute("xmlns:mstts", "https://www.w3.org/2001/mstts");


        $voice = $doc->createElement("voice");
        //$voice->setAttribute( "xml:lang" , "en-us" );
        $voice->setAttribute("xml:gender", "Female");

        $voiceId=$GLOBALS["TTS"]["AZURE"]["voice"];

        if (isset($GLOBALS["PATCH_OVERRIDE_VOICE"]))
			$voiceId=$GLOBALS["PATCH_OVERRIDE_VOICE"];

        

        $voice->setAttribute("name", $voiceId); // Read https://learn.microsoft.com/es-es/azure/cognitive-services/speech-service/language-support?tabs=tts

        $text = $doc->createTextNode($textString);


        $prosody = $doc->createElement("prosody");
        $prosody->setAttribute("rate", (isset($OverWriteRate))?$OverWriteRate:$GLOBALS["TTS"]["AZURE"]["rate"]); //https://learn.microsoft.com/en-us/azure/cognitive-services/speech-service/speech-synthesis-markup-voice#adjust-prosody
        $prosody->setAttribute("volume", $GLOBALS["TTS"]["AZURE"]["volume"]);
        if ($GLOBALS["TTS"]["AZURE"]["countour"])
            $prosody->setAttribute("contour", $GLOBALS["TTS"]["AZURE"]["countour"]);



        $prosody->appendChild($text);

        $style = $doc->createElement("mstts:express-as");
        if (isset($GLOBALS["TTS"]["AZURE"]["fixedMood"])&& (!empty($GLOBALS["TTS"]["AZURE"]["fixedMood"])))
            $style->setAttribute("style", $GLOBALS["TTS"]["AZURE"]["fixedMood"]); // not supported for all voices
        else
            $style->setAttribute("style", $validMood); // not supported for all voices

        $style->setAttribute("styledegree", "2"); // not supported for all voices
        //$style->setAttribute( "role" , "YoungAdultFemale" );  // not supported for all voices
        $style->appendChild($prosody);

        $voice->appendChild($style);
        $root->appendChild($voice);
        $doc->appendChild($root);
        $data = $doc->saveXML();

        //echo "tts post data: ". $data . "<br>";
        $data=str_replace("Skyrim","<phoneme ph=\"ˈskaɪrɪm\">Skyrim</phoneme>",$data); // Hack to correect Skyrim pronunciation in other langs

        $options = array(
            'http' => array(
                'header' => "Content-type: application/ssml+xml\r\n" .
                "X-Microsoft-OutputFormat: riff-24khz-16bit-mono-pcm\r\n" .
                "Authorization: " . "Bearer " . $access_token . "\r\n" .
                "X-Search-AppId: 07D3234E49CE426DAA29772419F436CA\r\n" .
                "X-Search-ClientID: 1ECFAE91408841A480F00935DC390960\r\n" .
                "User-Agent: TTSPHP\r\n" .
                "content-length: " . strlen($data) . "\r\n",
                'method' => 'POST',
                'content' => $data,
            ),
        );

        $context = stream_context_create($options);

        $starTime = microtime(true);

        // get the wave data
        $result = file_get_contents($ttsServiceUri, false, $context);
        if (!$result) {
            file_put_contents(dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "soundcache/" . md5(trim($stringforhash)) . ".err", trim($data));
            return false;
            //throw new Exception("Problem with $ttsServiceUri, $php_errormsg");
        } else {
            //echo "Wave data length: ". strlen($result);
        }
        //fwrite(STDOUT, $result);

        

        // Trying to avoid sync problems.
        $fileNameOrig=dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "soundcache/" . md5(trim($stringforhash)) . "_orig.wav";
        $fileName=dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "soundcache/" . md5(trim($stringforhash)) . ".wav";

        $stream = fopen($fileNameOrig, 'w');
        
        $size = fwrite($stream, $result);
        
        fsync($stream);
        fclose($stream);
        shell_exec("ffmpeg -y -i $fileNameOrig -filter:a \"speechnorm=e=6:r=0.0001:l=1\" $fileName 2>/dev/null >/dev/null");
        
        //file_put_contents(dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR."soundcache/" . md5(trim($stringforhash)) . ".wav", $result);
        file_put_contents(dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "soundcache/" . md5(trim($stringforhash)) . ".txt", trim($data) . "\n\rCache:$cacheUsed\n\rtotal call time:" . (microtime(true) - $starTime) . " ms\n\rsize of wav ($size)\n\rfunction tts($textString,$mood / $validMood ,$stringforhash)");
        $GLOBALS["DEBUG_DATA"][]=(microtime(true) - $starTime)." secs in azure call ";

        return "soundcache/" . md5(trim($stringforhash)) . ".wav";
    }
    
}
