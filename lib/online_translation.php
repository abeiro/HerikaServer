<?php
function translate($message, $toLang, $fromLang = 'EN') {
    $authKey = '[60c523a1-b103-425a-97f0-08168ce4f673:fx]';  // Replace with your actual DeepL Auth Key

    audit_log(__FILE__." ".__LINE__);


    // Data to be sent in the POST request
    $data = [
        'text' => [$message],
        'target_lang' => $toLang,
        'source_lang' => $fromLang
    ];

    // Convert data to JSON format
    $jsonData = json_encode($data);

    // Create headers
    $options = [
        'http' => [
            'header' => [
                'Authorization: DeepL-Auth-Key ' . $authKey,
                'Content-Type: application/json',
            ],
            'method' => 'POST',
            'content' => $jsonData
        ]
    ];

    // Create a stream context with the options
    $context = stream_context_create($options);

    // Make the POST request
    $url = 'https://api.deepl.com/v2/translate';
    $response = file_get_contents($url, false, $context);

    // Handle errors (if any)
    if ($response === FALSE) {
        return $message;
    }

    // Decode the JSON response
    $responseData = json_decode($response, true);

    audit_log(__FILE__." ".__LINE__);


    // Return the translated text
    if (isset($responseData['translations'][0]['text'])) {
        return $responseData['translations'][0]['text'];
    } else {
        return $message;
    }
}


?>