<?php
/**
 * Fetch available Groq models using user's API key
 * Returns list of models for the connector UI autocomplete
 */

$enginePath = __DIR__.DIRECTORY_SEPARATOR."../../";
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."runtime_bootstrap.php");
chimRuntimeBootstrap($enginePath, [
    'load_general_settings' => true,
    'load_player_name' => true,
    'load_narrator' => true,
]);

require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."core/api_badge.class.php");

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    header('Content-Type: application/json');

    // Read JSON data from the request
    $jsonDataInput = json_decode(file_get_contents("php://input"), true);
    $apiBadgeId = $jsonDataInput['api_badge_id'] ?? null;

    if (empty($apiBadgeId)) {
        echo json_encode(['error' => 'API badge ID required']);
        exit;
    }

    // Fetch the API key from the database
    $apiBadge = new ApiBadge();
    $badgeRow = $apiBadge->getById((int)$apiBadgeId);

    if (!$badgeRow || empty($badgeRow['api_key'])) {
        echo json_encode(['error' => 'API key not found for badge ID ' . $apiBadgeId]);
        exit;
    }

    $apiKey = $badgeRow['api_key'];

    // Set the request headers
    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ];

    // Create a context for the stream
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => implode("\r\n", $headers),
            'timeout' => 10
        ],
    ]);

    // Groq models endpoint (OpenAI-compatible)
    $url = "https://api.groq.com/openai/v1/models";

    // Perform the HTTP GET request
    $response = @file_get_contents($url, false, $context);

    if ($response === false) {
        echo json_encode(['error' => 'Failed to fetch models from Groq API']);
        exit;
    }

    $data = json_decode($response, true);

    if (!isset($data['data']) || !is_array($data['data'])) {
        echo json_encode(['error' => 'Invalid response from Groq API']);
        exit;
    }

    // Format the result for the autocomplete dropdown
    // Groq returns: id, object, owned_by, active, context_window
    $result = [];

    foreach ($data['data'] as $model) {
        $id = $model['id'] ?? '';
        if (empty($id)) continue;

        $owned_by = $model['owned_by'] ?? 'Groq';
        $context_window = $model['context_window'] ?? null;

        // Build label with context window if available
        $label = $owned_by;
        if ($context_window) {
            $label .= ', ctx: ' . number_format($context_window / 1024, 0) . 'K';
        }

        $result[] = [
            'value' => $id,
            'label' => $label,
            'id' => $id,
            'owned_by' => $owned_by,
            'context_window' => $context_window
        ];
    }

    // Sort by model ID
    usort($result, function($a, $b) {
        return strcmp($a['id'], $b['id']);
    });

    echo json_encode($result);
}

?>
