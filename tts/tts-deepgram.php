<?php 


$GLOBALS["TTS_IN_USE"] = function($textString, $mood, $stringforhash) {

    $apiKey = $GLOBALS["TTS"]["deepgram"]["API_KEY"];
    $voiceModel = isset($GLOBALS["TTS"]["deepgram"]["model"]) ? $GLOBALS["TTS"]["deepgram"]["model"] : "aura-2-thalia-en";
    $bitRate = isset($GLOBALS["TTS"]["deepgram"]["bitrate"]) ? $GLOBALS["TTS"]["deepgram"]["bitrate"] : 24000;
    $encoding = "linear16";

    $startTimeFull = microtime(true);
    Logger::info("TTS Deepgram started");

    $cachePath = dirname(__FILE__) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "soundcache/";
    $hash = md5(trim($stringforhash));
    $mp3Name = $cachePath . $hash . ".mp3";
    $wavName = $cachePath . $hash . ".wav";

    // Check cache
    if (!isset($GLOBALS["AVOID_TTS_CACHE"])) {
        if (file_exists($wavName)) {
            Logger::info("TTS cache hit for hash: " . $hash);
            return "soundcache/" . $hash . ".wav";
        } else {
            Logger::info("TTS cache miss for hash: " . $hash);
        }
    } else {
        Logger::info("TTS cache avoidance enabled.");
    }

    $url = "https://api.deepgram.com/v1/speak?model=$voiceModel&encoding=$encoding&sample_rate=$bitRate";
    $headers = [
        "Authorization: Token $apiKey",
        "Content-Type: application/json"
    ];

    $data = json_encode(["text" => $textString]);

    $options = [
        'http' => [
            'method'  => 'POST',
            'header'  => implode("\r\n", $headers),
            'content' => $data,
            'ignore_errors' => true
        ]
    ];

    $context = stream_context_create($options);
    $response = @file_get_contents($url, false, $context);

    if ($response !== false) {
        file_put_contents($mp3Name, $response);
        Logger::info("TTS content written to mp3: " . $mp3Name);

        // Convert to WAV and normalize
        $startTimeTrans = microtime(true);
        $command = "ffmpeg -y -i $mp3Name -filter:a \"speechnorm=e=6:r=0.0001:l=1\" $wavName 2>/dev/null >/dev/null";
        shell_exec($command);
        $endTimeTrans = microtime(true) - $startTimeTrans;
        $endTimeFull = microtime(true) - $startTimeFull;
        Logger::info("FFMPEG command executed: " . $command);

        file_put_contents($cachePath . $hash . ".txt", trim($textString) . "\n\rtotal call time:" . (microtime(true) - $startTimeTrans) . " sec\n\r");
        $GLOBALS["DEBUG_DATA"][] = "Deepgram TTS complete. FFMPEG time: $endTimeTrans sec, total $endTimeFull secs";
        Logger::info("Deepgram TTS complete for hash: " . $hash . ", FFMPEG time: $endTimeTrans sec, total $endTimeFull secs");

        return "soundcache/" . $hash . ".wav";
    } else {
        $error = error_get_last();
        $errorMessage = "Error fetching TTS data from Deepgram: " . print_r($http_response_header, true) . ' ' . $error['message'];
        Logger::error($errorMessage);

        $textString .= print_r($http_response_header, true) . ' ' . $error['message'];
        file_put_contents($cachePath . $hash . ".err", trim($textString));

        Logger::error("TTS failed and error written to: " . $cachePath . $hash . ".err");
        return false;
    }
};
