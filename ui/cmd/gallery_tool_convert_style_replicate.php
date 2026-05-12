<?php

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {

    $jsonDataInput = json_decode(file_get_contents("php://input"), true);

    $startTime = microtime(true);

    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    $enginePath = dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR;
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

    $apiBadge   = new ApiBadge();
    $apiKeyData = $apiBadge->getByLabel("replicate");

    $api_key = $apiKeyData["api_key"];

    $source = ($jsonDataInput["source"]);
    $extra  = "";

// Detectar extensión y tipo MIME
    $ext  = strtolower(pathinfo($source, PATHINFO_EXTENSION));
    $mime = match ($ext) {
        'jpg', 'jpeg' => 'image/jpeg',
        'png'         => 'image/png',
        'gif'         => 'image/gif',
        'bmp'         => 'image/bmp',
        default       => 'application/octet-stream'
    };


    $refid = null;
    $gender = null;
    $race = null;
    $hints = '';

    // Check if filename matches the pattern: 8 hex characters + extension (e.g., 0001C1A4.jpg)
    $filename = pathinfo($source, PATHINFO_FILENAME);
    if (preg_match('/^[0-9A-F]{8}$/i', $filename)) {
        $refid = strtoupper($filename); // Normalize to uppercase if needed

        $npcData = $db->fetchOne("SELECT gender, race FROM core_npc_master WHERE refid = '$refid'");

        if ($npcData) {
            $gender = $npcData['gender'];
            $race = $npcData['race'];
            $hints = "Hint: The person in the picture is a $gender $race";
        }
    }

    // Leer archivo y codificar en base64
    $base64 = base64_encode(file_get_contents($source));

// Crear Data URI
    $sourceImageData = "data:$mime;base64,$base64";
    $api_token       = $api_key;

    $payload = json_encode([
        "input" => [
            "prompt"                 => $jsonDataInput["userhint"]?$jsonDataInput["userhint"]:"Convert image-0 to a semi-realistic style, like a high-quality CGI render. Reimagine the whole picture, while preserving  details like tattos, skin color, eye color, hair style, hair color, clothing, make-up , body proportions and environment. $hints",
            "input_image"            => $sourceImageData,
            "output_format"          => "png",
            "num_inference_steps"    => 30,
            "disable_safety_checker" => true,

        ],
    ], JSON_UNESCAPED_SLASHES);

// Escape payload for shell

    $ch = curl_init("https://api.replicate.com/v1/models/black-forest-labs/flux-kontext-dev/predictions");

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
        if ($httpCode !== 200 && $httpCode !== 201) {

            die(json_encode(["status" => "error $httpCode"]));

        }
    }

    curl_close($ch);

// Decodificar JSON
    $responseData = json_decode($output, true);

    error_log(print_r($responseData, true));

    $filename = '/var/www/html/HerikaServer/data/pictures/gallery/' . uniqid('image_repli_', true) . '.png';
    copy($responseData["output"], $filename);
    die(json_encode(["status" => "success"]));

}
die(json_encode(["status" => "success"]));
