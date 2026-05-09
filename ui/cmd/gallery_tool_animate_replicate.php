<?php

$method = $_SERVER['REQUEST_METHOD'];
set_time_limit(300);

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

    $refid  = null;
    $gender = null;
    $race   = null;
    $hints  = '';

    // Check if filename matches the pattern: 8 hex characters + extension (e.g., 0001C1A4.jpg)
    $filename = pathinfo($jsonDataInput["source"], PATHINFO_FILENAME);
    if (preg_match('/^[0-9A-F]{8}$/i', $filename)) {
        $refid = strtoupper($filename); // Normalize to uppercase if needed

        $npcData = $db->fetchOne("SELECT gender, race FROM core_npc_master WHERE refid = '$refid'");

        if ($npcData) {
            $gender = $npcData['gender'];
            $race   = $npcData['race'];
            $hints  = "Hint: The person in the picture is a $gender $race";
        }
    }

    $imagePath = $source;

// Detectar extensión y tipo MIME
    $ext  = strtolower(pathinfo($imagePath, PATHINFO_EXTENSION));
    $mime = match ($ext) {
        'jpg', 'jpeg' => 'image/jpeg',
        'png'   => 'image/png',
        'gif'   => 'image/gif',
        'bmp'   => 'image/bmp',
        default => 'application/octet-stream'
    };

    $apiBadge   = new ApiBadge();
    $apiKeyData = $apiBadge->getByLabel("openrouter");
    $api_key    = $apiKeyData["api_key"];

// OpenRouter endpoint
    $url = "https://openrouter.ai/api/v1/chat/completions";

    $headers = [
        "Content-Type: application/json",
        "Authorization: Bearer " . $api_key,
    ];

    $source = $imagePath;
    $extra  = "";

    $imagePath = $source;

// Detectar extensión y tipo MIME
    $ext  = strtolower(pathinfo($imagePath, PATHINFO_EXTENSION));
    $mime = match ($ext) {
        'jpg', 'jpeg' => 'image/jpeg',
        'png'   => 'image/png',
        'gif'   => 'image/gif',
        'bmp'   => 'image/bmp',
        default => 'application/octet-stream'
    };

// Leer archivo y codificar en base64
    $base64 = base64_encode(file_get_contents($imagePath));
// Create a new version of the image with the Mean Removal filter
    $image = null;

    $image_url = "data:$mime;base64,$base64";

// Request payload
// Step 1: Describe the image

    if (isset($argv[3])) {

        $hint = "Character in this video should {$argv[3]}. NSFW actions allowed.";
    } else {
        $userHint=$jsonDataInput["userhint"]??"";
        $hint = $userHint??"Combine elements from the image (like characters or animals) to describe their actions,
such as running or waving, or mundane things like arranging hair, clothes, or even sensual expressions if image is sensual explicit, or  checking weapons... .Control intensity and speed with adjectives like \"quickly\" or \"slowly.\"";
    }

    $describe_payload = [
        "model"    => "qwen/qwen3-vl-235b-a22b-instruct",
        "messages" => [
            [
                "role"    => "user",
                "content" => [
                    [
                        "type" => "text",
                        "text" => "You're an AI movie director creating a short video sequence.
Analyze the attached image.
Write a detailed description, using concise words and keywords (1 paragraph)
Then add another short paragraph, using concise words and keywords,creating a Movement/Action Description:
$hint
Then add another ver short simple paragraph, using concise words and keywords,creating a single Camera Movement:
For specific lens effects, use phrases like \"camera pushes forward\" or \"camera moves left.\" To keep the shot steady, add \"fixed lens.\",
Camera Tilt Up,Camera Tilt Down,Handheld Shot, Following Shot,Orbiting Shot,",
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

    error_log($description);

    $apiBadge   = new ApiBadge();
    $apiKeyData = $apiBadge->getByLabel("replicate");
    $api_key    = $apiKeyData["api_key"];
    $api_token  = $api_key;

    $payload = json_encode([
        "input" => [
            "prompt"                   => $description,
            "image"                    => $image_url,
            "num_inference_steps"      => 30,
            "disable_safety_checker"   => true,
            "num_frames"               => 121,
            "resolution"               => "480p",
            "sample_shift"             => 12,
            "frames_per_second"        => 16,
            "interpolate_output"       => true,
            "lora_scale_transformer"   => 1,
            "lora_scale_transformer_2" => 1,
            "go_fast"                  => true,

        ],
    ], JSON_UNESCAPED_SLASHES);

// Escape payload for shell

    $ch = curl_init("https://api.replicate.com/v1/models/wan-video/wan-2.2-i2v-fast/predictions");

// Opciones
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $api_token",
        "Content-Type: application/json",
        "Prefer: wait",
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

// Ejecutar y capturar respuesta
    $output = curl_exec($ch);

// Manejar errores
    if (curl_errno($ch)) {
        die(json_encode(["status" => "error " . curl_error($ch)]));

    } else {
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($httpCode !== 200 && $httpCode !== 201 && $httpCode !== 202) {

            die(json_encode(["status" => "error $httpCode"]));

        }
    }

    curl_close($ch);

// Decodificar JSON
    $responseData = json_decode($output, true);

    error_log(print_r($responseData, true));
    //https://api.replicate.com/v1/predictions/15kwfzdw7srme0css3qbb11zdc
    if (empty($responseData["output"])) {
        error_log("Generation still in process. ".print_r($responseData["urls"],true));
        sleep(30);
        // Long generation?? retry.
        $ch = curl_init($responseData["urls"]["get"]);

// Opciones
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer $api_token",
            "Content-Type: application/json",
            "Prefer: wait",
        ]);


// Ejecutar y capturar respuesta
        $output = curl_exec($ch);

// Manejar errores
        if (curl_errno($ch)) {
            error_log("[REPLICATE ANIMATE] Error: ".curl_error($ch));
            die(json_encode(["status" => "error " . curl_error($ch)]));

        } else {
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($httpCode !== 200 && $httpCode !== 201 && $httpCode !== 202) {
                error_log("[REPLICATE ANIMATE] Error: ".$httpCode);
                die(json_encode(["status" => "error $httpCode"]));

            }
        }

        curl_close($ch);

        $responseData = json_decode($output, true);

        error_log(print_r($responseData, true));

    }
    $filename = '/var/www/html/HerikaServer/data/pictures/gallery/' . uniqid('image_repli_', true) . '.mp4';
    copy($responseData["output"], $filename);
    die(json_encode(["status" => "success"]));

}
die(json_encode(["status" => "error?"]));
