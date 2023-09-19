<?php

$json = file_get_contents('php://input');
$data = json_decode($json, true);

$file = $data["url"];

$url_validation_regex = "/^https?:\\/\\/(?:www\\.)?[-a-zA-Z0-9@:%._\\+~#=]{1,256}\\.[a-zA-Z0-9()]{1,6}\\b(?:[-a-zA-Z0-9()@:%_\\+.~#?&\\/=]*)$/"; 
$regexpVal=preg_match($url_validation_regex, $file);

if ($regexpVal==0) {
    $exists = 2;
    $file_headers[]="Error parsing url";
} else {
    $options = array(
            'http' => array(
                'timeout' => 15
            )
        );

    $context = stream_context_create($options);
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
    ob_start();
    $file_headers = get_headers($file, false, $context);
    $buffer=ob_get_clean();
    ini_set('display_errors', 0);

    if(!$file_headers || $file_headers[0] == 'HTTP/1.1 404 Not Found') {
        $exists = 1;
        $file_headers[0]=strip_tags($buffer);
    } else {
        $exists = 0;
    }
}

$retVal=[
    "status"=>$exists,
    "info"=>$file_headers[0]
];

echo json_encode($retVal);
