<?php

$localPath = dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR;
require_once($localPath . "conf".DIRECTORY_SEPARATOR."conf.php"); // API KEY must be there


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

    
    $prompt = "Let's roleplay in the world of Skyrim.  Describe this Skyrim image as if it is real life.  Describe the objects and people you see in a fifth grade reading level.  Ignore video game HUD and UI elements in your description.  If you see a Breton woman with tan skin, shoulder length brown hair, and brown clothing / armor, ignore her in the description. $hints";

    $fileContent = base64_encode(file_get_contents($file));

    $headers = [
        'Content-Type: application/json',
        "Authorization: Bearer {$GLOBALS["ITT"]["openai"]["API_KEY"]}"
    ];

    $payload = array(
        "model" => $GLOBALS["ITT"]["openai"]["model"],
        "temperature" => 0.2,
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
        "max_tokens" => $GLOBALS["ITT"]["openai"]["max_tokens"]+0
    );

    $options = array(
        'http' => array(
            'method'  => 'POST',
            'header'  => implode("\r\n", $headers),
            'content' => json_encode($payload)
        )
    );

    $context  = stream_context_create($options);

    $rawResponse = file_get_contents($GLOBALS["ITT"]["openai"]["url"], false, $context);

    $response = json_decode($rawResponse, true);

    // Example: Inserting response into database
    $db->insert(
        'log',
        array(
            'localts' => time(),
            'prompt' => print_r("USER:Context, roleplay In Skyrim universe, {$GLOBALS["HERIKA_NAME"]} watches this scene:", true),
            'response' => strtr($response["choices"][0]["message"]["content"], ["." => "\n"]),
            'url' => print_r($_GET, true)
        )
    );

  

    
    //print_r($response);
    
    return $response["choices"][0]["message"]["content"];

}
?>
