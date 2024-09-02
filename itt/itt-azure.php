<?php

$localPath = dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR;
require_once($localPath . "conf".DIRECTORY_SEPARATOR."conf.php"); // API KEY must be there
require_once($localPath . "lib".DIRECTORY_SEPARATOR."sharedmem.class.php"); // Caching token

function itt($file)
{

    // Credits to teddybear082
    
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
    $data = file_get_contents($AccessTokenUri."computervision/imageanalysis:analyze?api-version=2023-04-01-preview&features=caption,tags&language=en&gender-neutral-caption=False", false, $context);


    // needs to be parsed to grab captionResult /text
    $response = json_decode($data, true);
    
    // Check if "tagsResult" key exists in the response
    if (isset($response["tagsResult"]["values"])) {
        foreach($response["tagsResult"]["values"] as $tagArray)
            $tagNames[]=$tagArray["name"];
        
        $tagString = implode(",",$tagNames);
    } else {
        // Handle the case where "tagsResult" key is not present in the response
        $tagString = "none";
    }

 
    
    
   
    $badWords = array("video game", "game", "screenshot", "screen shot");
	$uneditedResponse = $response["captionResult"]["text"] . " with these details: " . $tagString;
    $finalResponse = str_replace($badWords, "view", $uneditedResponse);
    
    if ($db) {
        $db->insert(
            'log',
            array(
                'localts' => time(),
                'prompt' => print_r("USER:Context, roleplay In Skyrim universe, {$GLOBALS["HERIKA_NAME"]} watches this scene:", true),
                'response' => $finalResponse,
                'url' => $data


            )
        );
    }
    
    return $finalResponse;
    
}
