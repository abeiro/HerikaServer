<?php

$localPath = dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR;
require_once($localPath . "lib".DIRECTORY_SEPARATOR."runtime_bootstrap.php");
chimRuntimeBootstrapIfNeeded($localPath, [
    'load_general_settings' => true,
    'load_stt_connector' => false,
    'load_itt_connector' => true,
]);
require_once($localPath . "lib" . DIRECTORY_SEPARATOR . "core" . DIRECTORY_SEPARATOR . "api_badge.class.php");

function itt($file,$hints)
{
    global $db;

    $extension = pathinfo($file, PATHINFO_EXTENSION);
    if ($extension === "jpg") $mime_type = "image/jpeg";
    else if ($extension === "png") $mime_type = "image/png";
    else $mime_type = "application/octet-stream";

    $prompt = ($GLOBALS["ITT"]["openrouter"]["AI_VISION_PROMPT"] ?? '') . ". " . $hints;

    $fileContent = base64_encode(file_get_contents($file));

    // Resolve API key from conf or API Badge (OpenRouter)
    $apiKey = trim((string)($GLOBALS["ITT"]["openrouter"]["API_KEY"] ?? ''));
    if ($apiKey === '') {
        $badge = new ApiBadge();
        $badges = $badge->getAll();
        foreach ($badges as $b) {
            $lbl = strtolower((string)($b['label'] ?? ''));
            if (strpos($lbl, 'openrouter') !== false && trim((string)($b['api_key'] ?? '')) !== '') { $apiKey = trim((string)$b['api_key']); break; }
        }
    }

    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey,
        // Recommended by OpenRouter
        'HTTP-Referer: ' . ((isset($_SERVER['HTTP_HOST']) ? ('http'.((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']==='on')?'s':'').'://'.$_SERVER['HTTP_HOST']) : 'http://localhost')),
        'X-Title: CHIM'
    ];

    $payload = array(
        "model" => ($GLOBALS["ITT"]["openrouter"]["model"] ?? 'google/gemini-2.5-flash'),
        "temperature" => 0.0,
        "messages" => array(
            array(
                "role" => "user",
                "content" => array(
                    array("type" => "text", "text" => $prompt),
                    array("type" => "image_url", "image_url" => array("url" => "data:$mime_type;base64," . $fileContent))
                )
            )
        ),
        "max_tokens" => intval($GLOBALS["ITT"]["openrouter"]["max_tokens"] ?? 256)
    );

    $options = array(
        'http' => array(
            'method'  => 'POST',
            'header'  => implode("\r\n", $headers),
            'content' => json_encode($payload),
            'timeout' => intval($GLOBALS['HTTP_TIMEOUT'] ?? 30),
            'ignore_errors' => true
        )
    );

    $context  = stream_context_create($options);
    $rawResponse = file_get_contents(($GLOBALS["ITT"]["openrouter"]["url"] ?? 'https://openrouter.ai/api/v1/chat/completions'), false, $context);
    $response = json_decode($rawResponse, true);

    if ($db) {
        $db->insert('log', [
            'localts' => time(),
            'prompt' => $prompt,
            'response' => is_array($response) ? json_encode($response) : (string)$rawResponse,
            'url' => 'itt-openrouter'
        ]);
    }

    if (isset($response['choices'][0]['message']['content'])) {
        return $response['choices'][0]['message']['content'];
    }
    return '';
}
?>


