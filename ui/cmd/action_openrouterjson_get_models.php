<?php

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {

  // Read JSON data from the request
  $jsonDataInput = json_decode(file_get_contents("php://input"), true);
  

    // Set the request headers
    $headers = [
        'Content-Type: application/json'
    ];

    // Create a context for the stream
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => implode("\r\n", $headers),
        ],
    ]);

    // Specify the URL
    $url = "https://openrouter.ai/api/v1/models";
    
    // Perform the HTTP POST request
    $response = file_get_contents($url, false, $context);
  
    $data=json_decode($response,true);
    

  // Initialize an empty array to store the result
  $result = [];

  // Loop through the 'data' array
  foreach ($data['data'] as $model) {
      // Extract the required properties
      $id = $model['id'];
      foreach ($model['pricing'] as $k=>$v) {
        if ($v<0) {
          $model['pricing'][$k]='free';
        } else 
          $model['pricing'][$k]=number_format($v*1000000,2).'$';
      }
      
      unset($model['pricing']['image']);
      unset($model['pricing']['request']);
      $pricing = json_encode($model['pricing']);
      $context_length = number_format($model['context_length']/1024,0);
    
      // Create the value string
      $value = "$pricing, ctx_lgth:{$context_length}K / ".($model['top_provider']['is_moderated']?"moderated":"");

      // Add the key-value pair to the result array
      $result[] = ["value"=>"$id","label"=>$value];
  }

  // Print the result array
  echo json_encode($result);
}

?>
