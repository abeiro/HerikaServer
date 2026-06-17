<?php
header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Only POST method allowed"]);
    exit;
}

$jsonDataInput = json_decode(file_get_contents("php://input"), true);
if (!is_array($jsonDataInput)) {
    $jsonDataInput = [];
}

error_reporting(0);
ini_set("display_errors", 0);

$enginePath = dirname(__FILE__) . DIRECTORY_SEPARATOR . "../../" . DIRECTORY_SEPARATOR;
$GLOBALS["ENGINE_ROOT"] = rtrim($enginePath, "\\/");
$GLOBALS["ENGINE_PATH"] = rtrim($enginePath, "\\/");

require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "runtime_bootstrap.php";
chimRuntimeBootstrap($enginePath, [
    'load_general_settings' => true,
    'load_player_name' => true,
    'load_narrator' => true,
]);

require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "logger.php";
require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "core" . DIRECTORY_SEPARATOR . "player.class.php";
require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "core" . DIRECTORY_SEPARATOR . "api_badge.class.php";
require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "core" . DIRECTORY_SEPARATOR . "llm_connector.class.php";

function truncate_text($text, $maxLength = 260)
{
    $text = trim((string)$text);
    if ($text === "") {
        return "";
    }
    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($text) <= $maxLength) {
            return $text;
        }
        return rtrim(mb_substr($text, 0, $maxLength - 3)) . "...";
    }
    if (strlen($text) <= $maxLength) {
        return $text;
    }
    return rtrim(substr($text, 0, $maxLength - 3)) . "...";
}

try {
    $db = $GLOBALS["db"];
    if (!$db) {
        echo json_encode(["status" => "error", "message" => "Database connection failed"]);
        exit;
    }

    $player = new Player();
    $playerName = trim((string)$player->get('player_name'));
    if ($playerName === '' && isset($GLOBALS["PLAYER_NAME"])) {
        $playerName = trim((string)$GLOBALS["PLAYER_NAME"]);
    }
    if ($playerName === '') {
        $playerName = "Player";
    }

    $rows = $db->fetchAll(
        "SELECT data, type, gamets, ts
         FROM eventlog
         WHERE type IN ('inputtext','inputtext_s','ginputtext','ginputtext_s')
         ORDER BY gamets DESC, ts DESC
         LIMIT 200"
    );

    if (!is_array($rows) || empty($rows)) {
        echo json_encode([
            "status" => "error",
            "message" => "No recent player input events were found."
        ]);
        exit;
    }

    $dialogueLines = [];
    foreach (array_reverse($rows) as $row) {
        $eventData = trim((string)($row["data"] ?? ""));
        if ($eventData === "") {
            continue;
        }

        $speaker = "";
        $utterance = $eventData;
        if (preg_match('/^\s*([^:]{1,128})\s*:\s*(.*)$/us', $eventData, $matches)) {
            $speaker = trim((string)$matches[1]);
            $utterance = trim((string)$matches[2]);
        }

        // Keep strict player input only when the event has an explicit speaker label.
        if ($speaker !== "" && strcasecmp($speaker, $playerName) !== 0) {
            continue;
        }

        $utterance = preg_replace('/\s+/u', ' ', trim((string)$utterance));
        if ($utterance === "") {
            continue;
        }

        $utterance = truncate_text($utterance, 260);
        if ($utterance !== "") {
            $dialogueLines[] = $utterance;
        }
    }

    if (empty($dialogueLines)) {
        echo json_encode([
            "status" => "error",
            "message" => "No usable player dialogue was found in the last 200 input events."
        ]);
        exit;
    }

    $playerGuidance = trim((string)($jsonDataInput["player_guidance"] ?? ""));
    $currentSpeechStyle = trim((string)($jsonDataInput["current_speech_style"] ?? ""));
    $dialogueBlock = "- " . implode(PHP_EOL . "- ", $dialogueLines);

    $promptTemplate = null;
    try {
        $promptData = $db->fetchOne("SELECT custom_prompt, default_prompt FROM prompts WHERE prompt_key = 'player_speech_style_prompt'");
        if (is_array($promptData)) {
            $promptTemplate = !empty($promptData['custom_prompt']) ? $promptData['custom_prompt'] : ($promptData['default_prompt'] ?? null);
        }
    } catch (Exception $e) {
        // Silent fallback
    }

    if (empty($promptTemplate)) {
        $promptTemplate = "Generate a practical speech style prompt for {PLAYER_NAME} using recent dialogue and optional guidance. "
            . "Write exactly one paragraph (3-5 sentences) that can be used directly to rewrite player dialogue in roleplay. "
            . "Capture vocabulary, tone, cadence, formality, recurring phrases, and interpersonal style. "
            . "Stay grounded in the dialogue samples and guidance. Do not use bullet points, labels, or headings.";
    }

    $promptInstruction = strtr($promptTemplate, [
        '{PLAYER_NAME}' => $playerName,
        '{PLAYER_GUIDANCE}' => ($playerGuidance !== '' ? $playerGuidance : 'None provided.'),
        '{CURRENT_SPEECH_STYLE}' => ($currentSpeechStyle !== '' ? $currentSpeechStyle : 'None set.'),
        '{DIALOGUE_SAMPLES}' => $dialogueBlock
    ]);

    $connector = new LLMConnector();
    $candidateConnectorIds = [];
    foreach (["CORE_CONNECTOR_PLAYER", "CORE_CONNECTOR_PROFILES"] as $key) {
        $id = isset($GLOBALS[$key]) ? intval($GLOBALS[$key]) : 0;
        if ($id > 0 && !in_array($id, $candidateConnectorIds, true)) {
            $candidateConnectorIds[] = $id;
        }
    }

    $currentConnectorData = null;
    foreach ($candidateConnectorIds as $candidateId) {
        $candidate = $connector->getById($candidateId);
        if (is_array($candidate) && !empty($candidate["driver"])) {
            $currentConnectorData = $candidate;
            break;
        }
    }

    if (!$currentConnectorData) {
        echo json_encode([
            "status" => "error",
            "message" => "No valid player/profile LLM connector is configured."
        ]);
        exit;
    }

    $GLOBALS["CURRENT_CONNECTOR"] = $currentConnectorData["driver"];
    $GLOBALS["CHIM_CORE_CURRENT_CONNECTOR_DATA"] = $currentConnectorData;
    $connector->setOldGlobals($currentConnectorData);
    $connectionHandler = $connector->getConnector($currentConnectorData);

    $maxTokens = intval($currentConnectorData["max_tokens"] ?? 1024);
    if ($maxTokens <= 0) {
        $maxTokens = 1024;
    }
    $maxTokens = max(320, min($maxTokens, 1200));

    $contextData = [
        [
            "role" => "system",
            "content" => "You produce concise, reusable speech-style instructions for player dialogue rewriting."
        ],
        [
            "role" => "user",
            "content" => "Player name: {$playerName}\n"
                . "Current speech style:\n" . ($currentSpeechStyle !== '' ? $currentSpeechStyle : 'None set.') . "\n\n"
                . "Optional user guidance:\n" . ($playerGuidance !== '' ? $playerGuidance : 'None provided.') . "\n\n"
                . "Recent player dialogue samples (oldest to newest):\n{$dialogueBlock}"
        ],
        [
            "role" => "user",
            "content" => $promptInstruction
        ]
    ];

    $buffer = "";
    if (method_exists($connectionHandler, 'fast_request')) {
        $buffer = (string)$connectionHandler->fast_request($contextData, ["MAX_TOKENS" => $maxTokens]);
    } else {
        $connectionHandler->open($contextData, ["MAX_TOKENS" => $maxTokens]);
        $breakFlag = false;
        while (true) {
            if ($breakFlag) {
                break;
            }
            if ($connectionHandler->isDone()) {
                $breakFlag = true;
            }
            $buffer .= (string)$connectionHandler->process();
        }
        $connectionHandler->close();
    }

    $buffer = str_replace(["\r", "\n"], " ", (string)$buffer);
    $buffer = preg_replace('/\s+/u', ' ', trim($buffer));
    $buffer = preg_replace('/^\s*(speech style|player speech style)\s*:\s*/i', '', (string)$buffer);
    $buffer = trim((string)$buffer);
    $buffer = truncate_text($buffer, 1400);

    if ($buffer === "") {
        echo json_encode([
            "status" => "error",
            "message" => "Generation returned an empty speech style. Try again after more dialogue."
        ]);
        exit;
    }

    echo json_encode([
        "status" => "success",
        "message" => "Speech style generated from " . count($dialogueLines) . " recent input events. Click Save Player Settings to persist it.",
        "new_value" => $buffer
    ]);
} catch (Throwable $e) {
    echo json_encode([
        "status" => "error",
        "message" => "Failed to generate player speech style: " . $e->getMessage()
    ]);
}
