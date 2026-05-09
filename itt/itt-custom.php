<?php

$localPath = dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR;
require_once($localPath . "lib" . DIRECTORY_SEPARATOR . "runtime_bootstrap.php");
chimRuntimeBootstrapIfNeeded($localPath, [
    'load_general_settings' => true,
    'load_stt_connector' => false,
    'load_itt_connector' => true,
]);

function itt($file, $hints)
{
    global $db;

    $config = $GLOBALS["ITT"]["custom"] ?? [];
    $url = trim(strval($config["url"] ?? ""));
    if ($url === "") {
        return "";
    }

    $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    if ($extension === "jpg" || $extension === "jpeg") {
        $mimeType = "image/jpeg";
    } elseif ($extension === "png") {
        $mimeType = "image/png";
    } else {
        $mimeType = "application/octet-stream";
    }

    $prompt = trim(strval($config["AI_VISION_PROMPT"] ?? ""));
    if ($prompt !== "") {
        $prompt .= ". ";
    }
    $prompt .= $hints;

    $imageUrl = [
        "url" => "data:$mimeType;base64," . base64_encode(file_get_contents($file)),
    ];
    $detail = trim(strval($config["detail"] ?? ""));
    if ($detail !== "") {
        $imageUrl["detail"] = $detail;
    }

    $headers = [
        "Content-Type: application/json",
    ];
    $apiKey = trim(strval($config["API_KEY"] ?? ""));
    if ($apiKey !== "") {
        $headers[] = "Authorization: Bearer " . $apiKey;
    }

    $payload = [
        "model" => trim(strval($config["model"] ?? "")),
        "temperature" => 0.0,
        "messages" => [
            [
                "role" => "user",
                "content" => [
                    [
                        "type" => "text",
                        "text" => $prompt,
                    ],
                    [
                        "type" => "image_url",
                        "image_url" => $imageUrl,
                    ],
                ],
            ],
        ],
        "max_tokens" => intval($config["max_tokens"] ?? 256),
    ];

    $options = [
        "http" => [
            "method" => "POST",
            "header" => implode("\r\n", $headers),
            "content" => json_encode($payload),
            "timeout" => intval($GLOBALS["HTTP_TIMEOUT"] ?? 30),
            "ignore_errors" => true,
        ],
    ];

    $context = stream_context_create($options);
    $rawResponse = file_get_contents($url, false, $context);
    $response = json_decode($rawResponse, true);

    if ($db) {
        $db->insert(
            "log",
            [
                "localts" => time(),
                "prompt" => $prompt,
                "response" => is_array($response) ? json_encode($response) : strval($rawResponse),
                "url" => "itt-custom",
            ]
        );
    }

    $content = $response["choices"][0]["message"]["content"] ?? "";
    if (is_array($content)) {
        $textParts = [];
        foreach ($content as $part) {
            if (is_array($part) && isset($part["text"])) {
                $textParts[] = strval($part["text"]);
            } elseif (is_string($part)) {
                $textParts[] = $part;
            }
        }
        return trim(implode("", $textParts));
    }

    if (is_string($content)) {
        return $content;
    }

    if (isset($response["content"]) && is_string($response["content"])) {
        return $response["content"];
    }

    return "";
}

?>
