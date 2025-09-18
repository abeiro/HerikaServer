<?php

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {

    $jsonDataInput = json_decode(file_get_contents("php://input"), true);

    $startTime = microtime(true);

    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    $enginePath = dirname(__FILE__) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR;
    require_once $enginePath . "conf" . DIRECTORY_SEPARATOR . "conf.php";
    require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "model_dynmodel.php";
    require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "{$GLOBALS["DBDRIVER"]}.class.php";
    require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "chat_helper_functions.php";
    require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "data_functions.php";
    require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "logger.php";

    $GLOBALS["ENGINE_PATH"] = $enginePath;

    $db = new sql();

    require_once $enginePath . "lib/core/npc_master.class.php";
    require_once $enginePath . "lib/core/api_badge.class.php";
    require_once $enginePath . "lib/core/core_profiles.class.php";
    require_once $enginePath . "lib/core/llm_connector.class.php";

    $apiBadge   = new ApiBadge();
    $apiKeyData = $apiBadge->getByLabel("openrouter");
    $api_key    = $apiKeyData["api_key"];

// OpenRouter endpoint
    $url = "https://openrouter.ai/api/v1/chat/completions";

    $headers = [
        "Content-Type: application/json",
        "Authorization: Bearer " . $api_key,
    ];

    $source = ($jsonDataInput["source"]);
    $extra="";

    $imagePath = $source;

// Detectar extensión y tipo MIME
    $ext  = strtolower(pathinfo($imagePath, PATHINFO_EXTENSION));
    $mime = match ($ext) {
        'jpg', 'jpeg' => 'image/jpeg',
        'png'         => 'image/png',
        'gif'         => 'image/gif',
        'bmp'         => 'image/bmp',
        default       => 'application/octet-stream'
    };

// Leer archivo y codificar en base64
    $base64 = base64_encode(file_get_contents($imagePath));

// Crear Data URI
    $image_url = "data:$mime;base64,$base64";

// Request payload
    $payload = [
        "model"    => "google/gemini-2.5-flash-image-preview",
        "messages" => [
            [
                "role"    => "user",
                "content" => [
                    [
                        "type" => "text",
                        "text" => "Transform attached image to an ultra realistic photography,HD resolution. $extra.",
                    ],
                    [
                        "type"      => "image_url",
                        "image_url" => [
                            "url" => $image_url,
                        ],
                    ],
                ],
            ],
        ],
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        die(json_encode(["status" => "error " . curl_error($ch)]));
    }
    curl_close($ch);

// Decode JSON response
    $result = json_decode($response, true);

    error_log(print_r(array_keys($result), true));
    if (isset($result["error"])) {
        die(json_encode(["status" => "error " . $result["error"]]));

    }

    if (isset($result["choices"][0]["message"]["images"][0]["image_url"]["url"])) {
        // Image comes in base64. eg: data:image/png;base64,iVBORw0KGgo.....
        $base64data = $result["choices"][0]["message"]["images"][0]["image_url"]["url"];
        // Generate a unique filename
        $filename = '/var/www/html/HerikaServer/data/pictures/gallery/' . uniqid('image_or_', true) . '.png';

        // Extract base64 data (remove the data URI prefix)
        $base64data = preg_replace('#^data:image/\w+;base64,#i', '', $base64data);

        // Decode base64 and save to file
        if (file_put_contents($filename, base64_decode($base64data)) === false) {
            die(json_encode(["status" => "error"]));
        }
        die(json_encode(["status" => "success"]));

    }
}
die(json_encode(["status" => "error"]));
