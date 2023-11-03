<?php

$localPath = dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR;
require_once($localPath . "conf".DIRECTORY_SEPARATOR."conf.php"); // API KEY must be there
require_once($localPath . "lib".DIRECTORY_SEPARATOR."sharedmem.class.php"); // Caching token

function itt($file)
{

    global $db;

    $AccessTokenUri = $GLOBALS["ITT"]["AZURE"]["ENDPOINT"];
    $apiKey = $GLOBALS["ITT"]["AZURE"]["API_KEY"];

    $fileContent = file_get_contents($file);

    $headers = array(
           'Content-Type: application/octet-stream',
           "Ocp-Apim-Subscription-Key: $apiKey"
       );



    $contextOptions = array(
        'http' => array(
            'method' => 'POST',
            'header' => implode("\r\n", $headers),
            'content' => $fileContent
        )
    );


    $context = stream_context_create($contextOptions);

    //get the Access Token
    $data = file_get_contents($AccessTokenUri."computervision/imageanalysis:analyze?api-version=2023-04-01-preview&features=caption&language=en&gender-neutral-caption=False", false, $context);


    // needs to be parsed to grab captionResult /text
    $response = json_decode($data, true);

    $db->insert(
        'log',
        array(
              'localts' => time(),
              'prompt' => print_r("USER:Context, roleplay In Skyrim universe, {$GLOBALS["HERIKA_NAME"]} watches this scene:", true),
              'response' => strtr($response["captionResult"]["text"], ["."=>"\n"]),
              'url' => print_r($_GET, true)


          )
    );
    return $response["captionResult"]["text"];
}
