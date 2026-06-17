<?php

$localPath = dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR;
require_once($localPath . "lib".DIRECTORY_SEPARATOR."runtime_bootstrap.php");
chimRuntimeBootstrapIfNeeded($localPath, [
    'load_general_settings' => true,
    'load_stt_connector' => false,
    'load_itt_connector' => true,
]);
require_once($localPath . "lib" . DIRECTORY_SEPARATOR . "core" . DIRECTORY_SEPARATOR . "api_badge.class.php");


function itt($file,$hints)
{

    global $db;
    
    
    $extension = pathinfo($file, PATHINFO_EXTENSION);

    // Check the file extension and determine the MIME type
    if ($extension === "jpg") {
        $mime_type = "image/jpeg";
    } elseif ($extension === "png") {
        $mime_type = "image/png";
    } else {
        $mime_type = "unknown"; // You can set a default MIME type for other extensions
    }

    
    $prompt = $GLOBALS["ITT"]["google_openai"]["AI_VISION_PROMPT"].". $hints";

    /*
    foreach (json_decode(DataSpeechJournal("",5),true) as $element) {
    
        if ($lastListener!=$element["listener"]) {
            if ($element["listener"]!="The Narrator")
                $listener=" (talking to {$element["listener"]})";
            $lastListener=$element["listener"];
        }
        else
            $listener="";

        if ($lastPlace!=$element["location"]){
            $place=" (at {$element["location"]})";
            $lastPlace=$element["location"];
        }
        else
            $place="";

       
        $historyData.=trim("{$element["speaker"]}:".trim($element["speech"])." $listener $place").PHP_EOL;
    
    }

    // Also add some dialogue context
    $prompt.="#Context dialogie info\n$historyData\n";
    */
    $fileContent = base64_encode(file_get_contents($file));

    // Resolve API key: prefer configured, else fall back to API Badge (Google)
    $apiKey = trim((string)($GLOBALS["ITT"]["google_openai"]["API_KEY"] ?? ''));
    if ($apiKey === '') {
        $badge = new ApiBadge();
        $badges = $badge->getAll();
        foreach ($badges as $b) {
            $lbl = strtolower((string)($b['label'] ?? ''));
            if (strpos($lbl, 'google') !== false && trim((string)($b['api_key'] ?? '')) !== '') { $apiKey = trim((string)$b['api_key']); break; }
        }
    }
    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ];

    $payload = array(
        "model" => $GLOBALS["ITT"]["google_openai"]["model"],
        "temperature" => 0.0,
        "messages" => array(
            array(
                "role" => "user",
                "content" => array(
                    array(
                        "type" => "text",
                        "text" => $prompt
                    ),
                    array(
                        "type" => "image_url",
                        "image_url" => array(
                            "url" => "data:$mime_type;base64," . $fileContent
                        )
                    )
                )
            )
        ),
        "max_tokens" => $GLOBALS["ITT"]["google_openai"]["max_tokens"]+0
    );

    $options = array(
        'http' => array(
            'method'  => 'POST',
            'header'  => implode("\r\n", $headers),
            'content' => json_encode($payload)
        )
    );

    $context  = stream_context_create($options);
    file_put_contents(__DIR__."/../log/vision.log",print_r($payload,true));
    $rawResponse = file_get_contents($GLOBALS["ITT"]["google_openai"]["url"], false, $context);

    $response = json_decode($rawResponse, true);
    file_put_contents(__DIR__."/../log/vision.log",print_r($rawResponse,true),FILE_APPEND);
    // Example: Inserting response into database
    if ($db) {
        $db->insert(
            'log',
            array(
                'localts' => time(),
                'prompt' => print_r(strtr($GLOBALS["ITT"]["google_openai"]["AI_PROMPT"],["#HERIKA_NPC1#"=>$GLOBALS["HERIKA_NAME"]]), true),
                'response' => strtr($response["choices"][0]["message"]["content"], ["." => "\n"]),
                'url' => print_r($_GET, true)
            )
        );
    }

  

    
    //print_r($response);
    
    return $response["choices"][0]["message"]["content"];

}
?>
