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


function queueJob($text,$voice="EN-US",$speed=1.2,$lang="EN") {

    $url = $GLOBALS["TTS"]["MELOTTS"]["endpoint"];
    $url.= "/queue/join";

    $speed=1.2;

    $voice=(empty(trim($voice)))?"EN-US":$voice;
    $lang=(empty(trim($lang)))?"EN":$lang;
    
    // Data payload for the first request
    $data = [
        "data" => [$voice, $text, $speed, $lang],   // Speaker, Text, Speed, Lang
        "event_data" => null,
        "fn_index" => 1,
        "session_hash" => "1"
    ];

    error_log("$url [$voice, $text, $speed, $lang]");
    // Set up CURL for the initial request
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: */*'
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

    // Execute the request
    $response = curl_exec($ch);
    curl_close($ch);

    // Decode the JSON response to get the event_id
    $responseData = json_decode($response, true);
    if (isset($responseData['event_id'])) {
        error_log(__FILE__." $response ");
        return $responseData['event_id'];
    }

    return false;
}

function checkJobStatus($event_id) {

    $url = $GLOBALS["TTS"]["MELOTTS"]["endpoint"];
    $url .= "/queue/data?session_hash=1";
    
    $n=0;

    while (true) {
        // Set up CURL for the second request
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: text/event-stream',
            'Cache-Control: no-cache',
            'Connection: keep-alive'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10); // Timeout for the entire cURL execution

        // Execute the request
        $response = curl_exec($ch);
        curl_close($ch);

        // Handle the raw event stream format, line by line
        $lines = explode("\n", $response);

        foreach ($lines as $line) {
            $line = trim($line);
            if (strpos($line, 'data:') === 0) {
                $jsonData = json_decode(substr($line, 5), true);
                
                // Look for the 'process_completed' message
                if (isset($jsonData['msg']) && $jsonData['msg'] === 'process_completed') {
                    // Job is completed, return the URL of the generated file
                    if (isset($jsonData['output']['data'][0]['url'])) {
                        return $jsonData['output']['data'][0]['url'];
                    }
                }
            }
        }

        // Sleep for a short period to avoid hammering the server too fast
        $n++;
        if ($n>100)
            return false;
        usleep(1000 * 250 );
    }
}


function tts($textString, $mood , $stringforhash) {

    //xtts_fastapi_settings([]); //Check this
    
    /*if (!isset($GLOBALS["AVOID_TTS_CACHE"]))
        if (file_exists(dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "soundcache/" . md5(trim($stringforhash)) . ".wav"))
            return dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "soundcache/" . md5(trim($stringforhash)) . ".wav";
    */
    
    
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

    
    $speed=$GLOBALS["TTS"]["MELOTTS"]["speed"];

    /*
    $event_id = queueJob($newString,$voice,$speed,$lang);
    if ($event_id) {
        error_log(__FILE__.": Job queued successfully. Event ID: $event_id");
        $fileUrl = checkJobStatus($event_id);
        error_log(__FILE__.": Process completed. File URL: $fileUrl");
        
    } else {
        error_log(__FILE__.": Failed to queue the job");
        file_put_contents(dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "soundcache/" . md5(trim($stringforhash)) . ".err", trim($textString));
        return false;
    }
    */

    $fileUrl="{$GLOBALS["TTS"]["MELOTTS"]["endpoint"]}/tts?speaker={$GLOBALS["TTS"]["MELOTTS"]["voiceid"]}&text=".urlencode($textString)."&speed=1&language=EN";
    
    // Handle the response
    if ($fileUrl !== false ) {
        $response = file_get_contents($fileUrl);
        // Handle the successful response
        $size=strlen($response);
        $oname=dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "soundcache/" . md5(trim($stringforhash)) . "_o.wav";
        $fname=dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "soundcache/" . md5(trim($stringforhash)) . ".wav";
        
        file_put_contents($oname, $response); // Save the audio response to a file

        $startTimeTrans = microtime(true);
        //shell_exec("ffmpeg -y -i $oname  -af \"adelay=150|150,silenceremove=start_periods=1:start_silence=0.1:start_threshold=-25dB,areverse,silenceremove=start_periods=1:start_silence=0.1:start_threshold=-40dB,areverse,speechnorm=e=3:r=0.0001:l=1:p=0.75\" $fname 2>/dev/null >/dev/null");
        shell_exec("ffmpeg -y -i $oname  -af \"adelay=150|150\" $fname 2>/dev/null >/dev/null");
        $endTimeTrans = microtime(true)-$startTimeTrans;
        
        file_put_contents(dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "soundcache/" . md5(trim($stringforhash)) . ".txt", trim($textString) . "\n\rtotal call time:" . (microtime(true) - $starTime) . " ms\n\rffmpeg transcoding: $endTimeTrans secs\n\rsize of wav ($size)\n\rfunction tts($textString,$mood=\"cheerful\",$stringforhash)");
        $GLOBALS["DEBUG_DATA"][]=(microtime(true) - $starTime)." secs in melotts call";
        return "soundcache/" . md5(trim($stringforhash)) . ".wav";
        
    } 


}


/*
$GLOBALS["TTS"]["MELOTTS"]["endpoint"]='http://127.0.0.1:7860';
$GLOBALS["TTS"]["MELOTTS"]["voiceid"]='EN-US';
$GLOBALS["TTS"]["MELOTTS"]["language"]='EN';


$textTosay="I used to wear light armor, but over the months it wore down until it became unusable.";

echo tts($textTosay,'',$textTosay).PHP_EOL;

*/
?>