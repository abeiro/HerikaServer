<?php

$localPath = dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR;
require_once($localPath . "conf".DIRECTORY_SEPARATOR."conf.php"); // API KEY must be there


function itt($file,$hints)
{

    global $db;
    
    $fileContent = file_get_contents($file);
    $base64Encoded = base64_encode($fileContent);

    // Define the POST data as an associative array
    $postData = [
        'prompt' => strtr("{$GLOBALS["ITT"]["LLAMACPP"]["AI_VISION_PROMPT"]}.Hints: $hints. \nASSISTANT:",["#HERIKA_NPC1#"=>$GLOBALS["HERIKA_NAME"]]),
        'n_predict' => 256,
        'image_data' => [["data"=>$base64Encoded,"id"=>1]],
        'ignore_eos' => false,
        'temperature' => 0.0

    ];

    // [{"data": "<BASE64_STRING>", "id": 12}]}
    // Encode the data as JSON
    $jsonData = json_encode($postData);

    // Set the request headers
    $headers = [
        'Content-Type: application/json',
    ];

    // Create a context for the stream
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => implode("\r\n", $headers),
            'content' => $jsonData,
            'timeout'=>120
        ],
    ]);

    // Specify the URL
    $url = $GLOBALS["ITT"]["LLAMACPP"]["URL"]."/completion";

    // Perform the HTTP POST request
    $responseRaw = file_get_contents($url, false, $context);
    

    $response=json_decode($responseRaw,true);
    if ($db) {
     $db->insert(
            'log',
            array(
                'localts' => time(),
                'prompt' => print_r($postData["prompt"],true),
                'response' => strtr($response["content"],["."=>"\n"]),
                'url' => print_r($_GET,true)


            )
        );
    }
    return $response["content"];
    error_log($responseRaw);

}
?>
