<?php

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {

  // Read JSON data from the request
  $jsonDataInput = json_decode(file_get_contents("php://input"), true);
  

    // Set the request headers
    $headers = [
        'Content-Type: application/json',
        "Authorization: Bearer {$jsonDataInput["CONNECTOR@openrouterjson@API_KEY"]}",
    ];

    // Create a context for the stream
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => implode("\r\n", $headers),
        ],
    ]);

    // Specify the URL
    $url = "https://openrouter.ai/api/v1/parameters/{$jsonDataInput["CONNECTOR@openrouterjson@model"]}";
    
    // Perform the HTTP POST request
    $response = file_get_contents($url, false, $context);
  
    $responseData=json_decode($response,true);
    if ($_GET["P"]==10) {
      $responseParsed["CONNECTOR@openrouterjson@frequency_penalty"]=$responseData["data"]["frequency_penalty_p10"];
      $responseParsed["CONNECTOR@openrouterjson@presence_penalty"]=$responseData["data"]["presence_penalty_p10"];
      $responseParsed["CONNECTOR@openrouterjson@repetition_penalty"]=$responseData["data"]["repetition_penalty_p10"];
      $responseParsed["CONNECTOR@openrouterjson@temperature"]=$responseData["data"]["temperature_p10"];
      $responseParsed["CONNECTOR@openrouterjson@top_a"]=$responseData["data"]["top_a_p10"];
      $responseParsed["CONNECTOR@openrouterjson@top_k"]=$responseData["data"]["top_k_p10"];
      $responseParsed["CONNECTOR@openrouterjson@top_p"]=$responseData["data"]["top_p_p10"];
      $responseParsed["CONNECTOR@openrouterjson@min_p"]=$responseData["data"]["min_p_p10"];
    } else if ($_GET["P"]==50) {
      $responseParsed["CONNECTOR@openrouterjson@frequency_penalty"]=$responseData["data"]["frequency_penalty_p50"];
      $responseParsed["CONNECTOR@openrouterjson@presence_penalty"]=$responseData["data"]["presence_penalty_p50"];
      $responseParsed["CONNECTOR@openrouterjson@repetition_penalty"]=$responseData["data"]["repetition_penalty_p50"];
      $responseParsed["CONNECTOR@openrouterjson@temperature"]=$responseData["data"]["temperature_p50"];
      $responseParsed["CONNECTOR@openrouterjson@top_a"]=$responseData["data"]["top_a_p50"];
      $responseParsed["CONNECTOR@openrouterjson@top_k"]=$responseData["data"]["top_k_p50"];
      $responseParsed["CONNECTOR@openrouterjson@top_p"]=$responseData["data"]["top_p_p50"];
      $responseParsed["CONNECTOR@openrouterjson@min_p"]=$responseData["data"]["min_p_p50"];
    } else if ($_GET["P"]==90) {
      $responseParsed["CONNECTOR@openrouterjson@frequency_penalty"]=$responseData["data"]["frequency_penalty_p90"];
      $responseParsed["CONNECTOR@openrouterjson@presence_penalty"]=$responseData["data"]["presence_penalty_p90"];
      $responseParsed["CONNECTOR@openrouterjson@repetition_penalty"]=$responseData["data"]["repetition_penalty_p90"];
      $responseParsed["CONNECTOR@openrouterjson@temperature"]=$responseData["data"]["temperature_p90"];
      $responseParsed["CONNECTOR@openrouterjson@top_a"]=$responseData["data"]["top_a_p90"];
      $responseParsed["CONNECTOR@openrouterjson@top_k"]=$responseData["data"]["top_k_p90"];
      $responseParsed["CONNECTOR@openrouterjson@top_p"]=$responseData["data"]["top_p_p90"];
      $responseParsed["CONNECTOR@openrouterjson@min_p"]=$responseData["data"]["min_p_p90"];
    }

    
    echo json_encode($responseParsed);
}

?>
