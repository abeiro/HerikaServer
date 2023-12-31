<?php

$localPath = dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR;
require_once($localPath . "conf".DIRECTORY_SEPARATOR."conf.php"); 

function ttsMimic($textString, $mood = "cheerful", $stringforhash='')
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

    // get the wave data
    $result = file_get_contents($ttsServiceUri, false, $context);
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
}
