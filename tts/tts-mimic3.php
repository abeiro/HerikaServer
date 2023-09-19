<?php
$path = dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR;
require_once($path . "conf.php"); // API KEY must be there

function ttsMimic($textString, $mood = "cheerful", $stringforhash='')
{

    $start = microtime(true);

    $ttsServiceUri = $GLOBALS["MIMIC3"] . "/api/tts";

    $doc = new DOMDocument();

    $root = $doc->createElement("speak");

    $voice = $doc->createElement("voice");
    $voice->setAttribute("name", $GLOBALS["MIMIC3_CONF"]["voice"]);

    $text = $doc->createTextNode($textString);


    $prosody = $doc->createElement("prosody");
    $prosody->setAttribute("rate", $GLOBALS["MIMIC3_CONF"]["rate"]);
    $prosody->setAttribute("volume", $GLOBALS["MIMIC3_CONF"]["volume"]);

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
