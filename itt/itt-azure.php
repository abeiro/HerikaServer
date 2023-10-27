<?php

$localPath = dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR;
require_once($localPath . "conf".DIRECTORY_SEPARATOR."conf.php"); // API KEY must be there
require_once($localPath . "lib".DIRECTORY_SEPARATOR."sharedmem.class.php"); // Caching token

function itt($file)
{



    $AccessTokenUri = $GLOBALS["ITT"]["AZURE"]["ENDPOINT"];
    $apiKey = $GLOBALS["ITT"]["AZURE"]["API_KEY"];


    $headers = array(
           'Content-Type: image/jpeg',
           "Ocp-Apim-Subscription-Key: $apiKey"
       );

    $testurl=[ "url"=>"https://cdn.britannica.com/96/1296-050-4A65097D/gelding-bay-coat.jpg" ];
    
    
    
    
    $contextOptions = array(
        'http' => array(
            'method' => 'POST',
            'header' => implode("\r\n", $headers),
            'content' => file_get_contents($file)
        )     
    );
    
    
    $context = stream_context_create($contextOptions);

    //get the Access Token
    $data = file_get_contents($AccessTokenUri."computervision/imageanalysis:analyze?api-version=2023-04-01-preview&features=Tags,Read", false, $context);

    print_r(json_decode($data));


}
