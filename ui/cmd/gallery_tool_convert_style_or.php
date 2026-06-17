<?php

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {

    $jsonDataInput = json_decode(file_get_contents("php://input"), true);

    $startTime = microtime(true);

    $enginePath = dirname(__FILE__) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR;
    require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "runtime_bootstrap.php";
    chimRuntimeBootstrap($enginePath, [
        'load_general_settings' => true,
        'load_player_name' => true,
        'load_narrator' => true,
    ]);

    require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "model_dynmodel.php";
    require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "chat_helper_functions.php";
    require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "data_functions.php";
    require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "logger.php";

    $GLOBALS["ENGINE_PATH"] = $enginePath;

    $db = $GLOBALS["db"];

    require_once $enginePath . "lib/core/npc_master.class.php";
    require_once $enginePath . "lib/core/api_badge.class.php";
    require_once $enginePath . "lib/core/core_profiles.class.php";
    require_once $enginePath . "lib/core/llm_connector.class.php";

    error_reporting(E_ALL);
    ini_set('display_errors', 1);

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
    $extra  = "";

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
    // Create a new version of the image with the Mean Removal filter
    $image = null;

    switch ($mime) {
        case 'image/jpeg':
            $image = imagecreatefromjpeg($imagePath);
            break;
        case 'image/png':
            $image = imagecreatefrompng($imagePath);
            break;
        case 'image/gif':
            $image = imagecreatefromgif($imagePath);
            break;
        case 'image/bmp':
            $image = imagecreatefrombmp($imagePath);
            break;
        default:
            die(json_encode(["status" => "error", "msg" => "Unsupported image format"]));
    }

    if ($image) {
        // Apply the Mean Removal filter
        if (imagefilter($image, IMG_FILTER_PIXELATE, 8, true)) {
            //imagefilter($image, IMG_FILTER_SMOOTH,-100);
            $filteredImagePath = "/tmp/" . uniqid('filtered_') . '.' . $ext;

            
            switch ($mime) {
                case 'image/jpeg':
                    error_log("[GALLERY REIMAGINE] Applying filter  image jpeg <$mime> on $filteredImagePath");
                    imagejpeg($image, $filteredImagePath) or die(json_encode(["status" => "error", "msg" => "Failed to apply filter"]));
                    break;
                case 'image/png':
                    error_log("[GALLERY REIMAGINE] Applying filter imagepng <$mime> on $filteredImagePath");
                    imagepng($image, $filteredImagePath) or die(json_encode(["status" => "error", "msg" => "Failed to apply filter"]));
                    break;
                case 'image/gif':
                    imagegif($image, $filteredImagePath) or die(json_encode(["status" => "error", "msg" => "Failed to apply filter"]));
                    break;
                case 'image/bmp':
                    imagebmp($image, $filteredImagePath) or die(json_encode(["status" => "error", "msg" => "Failed to apply filter"]));
                    break;
            }

            imagedestroy($image);
        } else {
            imagedestroy($image);
            die(json_encode(["status" => "error", "msg" => "Failed to apply filter"]));
        }

        if (! file_exists($filteredImagePath)) {
            error_log("[GALLERY REIMAGINE] $filteredImagePath does not exists");

        }
    }

    //$filename   = '/var/www/html/HerikaServer/data/pictures/gallery/t.png';
    //copy($filteredImagePath,$filename);
        

    $base64_alt = base64_encode(file_get_contents($filteredImagePath));
    // Crear Data URI
    $image_url     = "data:$mime;base64,$base64";
    $image_url_alt = "data:$mime;base64,$base64_alt";

    $base64dataAlt = preg_replace('#^data:image/\w+;base64,#i', '', $image_url_alt);

    // Request payload
    // Step 1: Describe the image

    $describe_payload = [
        "model"    => "google/gemini-2.5-flash",
        "messages" => [
            [
                "role"    => "user",
                "content" => [
                    [
                        "type" => "text",
                        "text" => "Describe the attached image in detail. Focus on the scene, objects, people, style, mood, and any notable features.",
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
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($describe_payload));
    $describe_response = curl_exec($ch);
    if (curl_errno($ch)) {
        die(json_encode(["status" => "error " . curl_error($ch)]));
    }
    curl_close($ch);

    $describe_result = json_decode($describe_response, true);
    if (isset($describe_result["error"])) {
        die(json_encode(["status" => "error " . $describe_result["error"]]));
    }

    // Extract the description text
    $description = null;
    if (isset($describe_result["choices"][0]["message"]["content"])) {
        $description = $describe_result["choices"][0]["message"]["content"];

    }
    if (! $description) {
        die(json_encode(["status" => "error", "msg" => "No description returned"]));
    }

    // Optionally, save the description to a file (uncomment if needed)
    file_put_contents("/tmp/" . uniqid('desc_') . '.txt', $description);
    error_log($description);
    // Step 2: Generate a new image from the description
    $generate_payload = [
        "model"    => "google/gemini-2.5-flash-image-preview",
        "messages" => [
            [
                "role"    => "user",
                "content" => [
                    [
                        "type" => "text",
                        "text" => "Transform this description into a cinematic, ultra-photorealistic image, you must use an y16:9 aspect ratio. Render the scene with incredible detail, a shallow depth of field, and dynamic, natural lighting that casts realistic shadows and highlights. The final image should have an 8K resolution, with rich colors and textures that create a compelling, lifelike atmosphere. $extra\n\nDescription: $description. Use same image size as attached image",
                    ],
                    [
                        "type"      => "image_url",
                        "image_url" => [
                            "url" => $image_url_alt,
                        ],
                    ]
                ],
            ],
        ],
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($generate_payload));
    $generate_response = curl_exec($ch);
    if (curl_errno($ch)) {
        die(json_encode(["status" => "error " . curl_error($ch)]));
    }
    curl_close($ch);

    $generate_result = json_decode($generate_response, true);
    if (isset($generate_result["error"])) {
        die(json_encode(["status" => "error " . $generate_result["error"]]));
    }

    if (isset($generate_result["choices"][0]["message"]["images"][0]["image_url"]["url"])) {
        // error_log(print_r( $generate_result["choices"][0]["message"],true));
        $base64data = $generate_result["choices"][0]["message"]["images"][0]["image_url"]["url"];
        $filename   = '/var/www/html/HerikaServer/data/pictures/gallery/' . uniqid('image_or_', true) . '.png';
        $base64data = preg_replace('#^data:image/\w+;base64,#i', '', $base64data);
        if (file_put_contents($filename, base64_decode($base64data)) === false) {
            die(json_encode(["status" => "error"]));
        }
        die(json_encode(["status" => "success", "description" => $description]));
    }
}
die(json_encode(["status" => "error"]));
