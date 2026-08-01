<?php

$enginePath = __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR;

require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "runtime_bootstrap.php");
chimRuntimeBootstrap($enginePath, [
    'load_general_settings' => true,
    'load_player_name' => true,
    'load_narrator' => true,
]);

require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "logger.php");

$scriptPath = $_SERVER['SCRIPT_NAME'];
$uiPos = strpos($scriptPath, '/ui/');
if ($uiPos !== false) {
    $webRoot = substr($scriptPath, 0, $uiPos);
} else {
    $webRoot = '';
}
if ($webRoot == '/') {
    $webRoot = '';
}
$webRoot = rtrim($webRoot, '/');

function chimAiResponseH($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
}

function chimAiResponseFormatLocalTs($value)
{
    $ts = intval($value ?? 0);
    if ($ts <= 0) {
        return "";
    }

    $dt = new DateTime("@" . $ts);
    $dt->setTimezone(new DateTimeZone("UTC"));
    return $dt->format("d-m-Y H:i:s");
}

function chimAiResponseSafeFetchAll($db, $query)
{
    try {
        return $db->fetchAll($query);
    } catch (Throwable $exception) {
        if (class_exists('Logger')) {
            Logger::warn("AI response page query failed: " . $exception->getMessage());
        }
        return [];
    }
}

function chimAiResponseSafeDeleteLog($db)
{
    try {
        $db->delete("log", "true");
        return true;
    } catch (Throwable $exception) {
        if (class_exists('Logger')) {
            Logger::warn("AI response page clean failed: " . $exception->getMessage());
        }
        return false;
    }
}

function chimAiResponseNormalizeMarkup($value)
{
    if ($value === null) {
        return "";
    }

    if (!is_string($value)) {
        if (is_array($value) || is_object($value)) {
            $value = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } else {
            $value = (string)$value;
        }
    }

    $value = str_replace(["<br />", "<br>", "<br/>"], "\n", $value);
    $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
    $value = str_replace(['\/', '\r\n', '\n', '\r'], ['/', "\n", "\n", "\n"], $value);

    return trim($value);
}

function chimAiResponseNormalizePromptMarkup($value)
{
    if ($value === null) {
        return "";
    }

    if (!is_string($value)) {
        if (is_array($value) || is_object($value)) {
            $value = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } else {
            $value = (string)$value;
        }
    }

    $value = str_replace(["<br />", "<br>", "<br/>"], "\n", $value);
    $value = html_entity_decode($value, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");

    return trim($value);
}

function chimAiResponseNormalizeDisplayedText($value)
{
    $value = chimAiResponseNormalizeMarkup($value);
    if ($value === "") {
        return "";
    }

    return trim(str_replace("''", "'", $value));
}

function chimAiResponseDecodeStoredPayload($value)
{
    $clean = chimAiResponseNormalizeMarkup($value);
    if ($clean === "") {
        return null;
    }

    $decoded = json_decode($clean, true);
    return is_array($decoded) ? $decoded : null;
}

function chimAiResponseLooksStructured($value)
{
    $value = ltrim(chimAiResponseNormalizeMarkup($value));
    if ($value === "") {
        return true;
    }

    if (preg_match('/^(Array\s*\(|array\s*\(|\{|\[)/u', $value)) {
        return true;
    }

    return strpos($value, '"response_connector"') !== false
        || strpos($value, "'response_connector'") !== false
        || strpos($value, '"OUTPUT_LOG"') !== false
        || strpos($value, "'OUTPUT_LOG'") !== false;
}

function chimAiResponseExtractSubtitleFromScriptQueue($value)
{
    $value = chimAiResponseNormalizeMarkup($value);
    if ($value === "") {
        return "";
    }

    if (preg_match('/\|ScriptQueue\|(.+?)(?:\/|\r|\n|$)/us', $value, $matches)) {
        return chimAiResponseNormalizeDisplayedText($matches[1]);
    }

    if (preg_match('/\|Talk\|(.+?)(?:\r|\n|$)/us', $value, $matches)) {
        return chimAiResponseNormalizeDisplayedText($matches[1]);
    }

    return "";
}

function chimAiResponseValueAtPath($payload, array $path)
{
    $cursor = $payload;
    foreach ($path as $segment) {
        if (!is_array($cursor) || !array_key_exists($segment, $cursor)) {
            return null;
        }
        $cursor = $cursor[$segment];
    }

    return $cursor;
}

function chimAiResponseExtractTextFromPayload($payload)
{
    if (!is_array($payload)) {
        return "";
    }

    if (isset($payload['response']) && is_array($payload['response'])) {
        $parts = [];
        foreach ($payload['response'] as $responseEntry) {
            if (is_array($responseEntry) && isset($responseEntry['processed'])) {
                $processed = chimAiResponseNormalizeDisplayedText($responseEntry['processed']);
                if ($processed !== "") {
                    $parts[] = str_replace('|', "\n", $processed);
                }
            }
        }

        if (!empty($parts)) {
            return implode("\n", array_values(array_unique($parts)));
        }
    }

    if (isset($payload['OUTPUT_LOG'])) {
        $subtitle = chimAiResponseExtractSubtitleFromScriptQueue($payload['OUTPUT_LOG']);
        if ($subtitle !== "") {
            return $subtitle;
        }
    }

    $candidatePaths = [
        ['choices', 0, 'message', 'content'],
        ['full', 'choices', 0, 'message', 'content'],
        ['response_full', 'choices', 0, 'message', 'content'],
        ['candidates', 0, 'content', 'parts', 0, 'text'],
        ['full', 'candidates', 0, 'content', 'parts', 0, 'text'],
        ['response_full', 'candidates', 0, 'content', 'parts', 0, 'text'],
        ['message'],
        ['content'],
        ['text'],
    ];

    foreach ($candidatePaths as $path) {
        $candidate = chimAiResponseValueAtPath($payload, $path);
        if (is_string($candidate)) {
            $text = chimAiResponseNormalizeDisplayedText($candidate);
            if ($text !== "" && !chimAiResponseLooksStructured($text)) {
                return $text;
            }
        }
    }

    return "";
}

function chimAiResponseExtractBestText($rawResponseValue, $rawPromptValue = "")
{
    $responseText = chimAiResponseNormalizeDisplayedText($rawResponseValue);
    if ($responseText !== "" && !chimAiResponseLooksStructured($responseText)) {
        return $responseText;
    }

    $subtitleFromResponse = chimAiResponseExtractSubtitleFromScriptQueue($rawResponseValue);
    if ($subtitleFromResponse !== "") {
        return $subtitleFromResponse;
    }

    $decodedResponse = chimAiResponseDecodeStoredPayload($rawResponseValue);
    if (is_array($decodedResponse)) {
        $decodedText = chimAiResponseExtractTextFromPayload($decodedResponse);
        if ($decodedText !== "") {
            return $decodedText;
        }
    }

    $decodedPrompt = chimAiResponseDecodeStoredPayload($rawPromptValue);
    if (is_array($decodedPrompt)) {
        $decodedPromptText = chimAiResponseExtractTextFromPayload($decodedPrompt);
        if ($decodedPromptText !== "") {
            return $decodedPromptText;
        }
    }

    $subtitleFromPrompt = chimAiResponseExtractSubtitleFromScriptQueue($rawPromptValue);
    if ($subtitleFromPrompt !== "") {
        return $subtitleFromPrompt;
    }

    return $responseText;
}

function chimAiResponseDecodePromptExportString($value)
{
    return str_replace(["\\\\", "\\'"], ["\\", "'"], $value);
}

function chimAiResponseReadPhpExportQuotedString($text, $startPos)
{
    $length = strlen($text);
    if ($startPos < 0 || $startPos >= $length) {
        return null;
    }

    $value = "";
    $index = $startPos;
    while ($index < $length) {
        $char = $text[$index];
        if ($char === "\\") {
            if ($index + 1 < $length) {
                $value .= $char . $text[$index + 1];
                $index += 2;
                continue;
            }

            $value .= $char;
            $index++;
            continue;
        }

        if ($char === "'") {
            return [
                "value" => $value,
                "next" => $index + 1,
            ];
        }

        $value .= $char;
        $index++;
    }

    return null;
}

function chimAiResponseExtractPhpExportScalarField($text, $fieldName)
{
    $needle = "'" . $fieldName . "' => '";
    $fieldPos = strpos($text, $needle);
    if ($fieldPos === false) {
        return "";
    }

    $parsed = chimAiResponseReadPhpExportQuotedString($text, $fieldPos + strlen($needle));
    if (!is_array($parsed) || !isset($parsed["value"])) {
        return "";
    }

    return chimAiResponseDecodePromptExportString((string)$parsed["value"]);
}

function chimAiResponseExtractPromptPayloadFromDecodedArray(array $decoded)
{
    $promptPayload = null;
    if (isset($decoded["response_full"]) && is_array($decoded["response_full"])) {
        $promptPayload = $decoded["response_full"];
    } elseif (isset($decoded["full"]) && is_array($decoded["full"])) {
        $promptPayload = $decoded["full"];
    } elseif (isset($decoded["payload"]) && is_array($decoded["payload"])) {
        $promptPayload = $decoded["payload"];
    }

    $responseConnector = isset($decoded["response_connector"]) && is_array($decoded["response_connector"])
        ? $decoded["response_connector"]
        : [];

    if (isset($decoded["connector_type"]) && trim((string)$decoded["connector_type"]) !== "" && !isset($responseConnector["label"])) {
        $responseConnector["label"] = trim((string)$decoded["connector_type"]);
    }
    if (isset($decoded["driver"]) && trim((string)$decoded["driver"]) !== "" && !isset($responseConnector["driver"])) {
        $responseConnector["driver"] = trim((string)$decoded["driver"]);
    }

    $messages = [];
    if (is_array($promptPayload) && isset($promptPayload["messages"]) && is_array($promptPayload["messages"])) {
        $messages = $promptPayload["messages"];
    } elseif (isset($decoded["messages"]) && is_array($decoded["messages"])) {
        $messages = $decoded["messages"];
    }

    $model = "";
    if (is_array($promptPayload) && isset($promptPayload["model"])) {
        $model = trim((string)$promptPayload["model"]);
    } elseif (isset($decoded["model"])) {
        $model = trim((string)$decoded["model"]);
    }

    return [
        "messages" => $messages,
        "model" => $model,
        "response_connector" => $responseConnector,
    ];
}

function chimAiResponseExtractPromptPayloadFromPhpArrayExport($rawPrompt)
{
    $messagesStart = strpos($rawPrompt, "'messages' =>");
    if ($messagesStart === false) {
        return null;
    }

    $messages = [];
    $roleNeedle = "'role' => '";
    $contentNeedle = "'content' => '";
    $cursor = $messagesStart;

    while (($rolePos = strpos($rawPrompt, $roleNeedle, $cursor)) !== false) {
        $roleParsed = chimAiResponseReadPhpExportQuotedString($rawPrompt, $rolePos + strlen($roleNeedle));
        if (!is_array($roleParsed) || !isset($roleParsed["value"], $roleParsed["next"])) {
            break;
        }

        $contentPos = strpos($rawPrompt, $contentNeedle, intval($roleParsed["next"]));
        if ($contentPos === false) {
            break;
        }

        $contentParsed = chimAiResponseReadPhpExportQuotedString($rawPrompt, $contentPos + strlen($contentNeedle));
        if (!is_array($contentParsed) || !isset($contentParsed["value"], $contentParsed["next"])) {
            break;
        }

        $messages[] = [
            "role" => chimAiResponseDecodePromptExportString((string)$roleParsed["value"]),
            "content" => chimAiResponseDecodePromptExportString((string)$contentParsed["value"]),
        ];
        $cursor = intval($contentParsed["next"]);
    }

    if (count($messages) === 0) {
        return null;
    }

    $responseConnector = [];
    $connectorType = chimAiResponseExtractPhpExportScalarField($rawPrompt, "connector_type");
    if ($connectorType !== "") {
        $responseConnector["label"] = $connectorType;
    }

    return [
        "messages" => $messages,
        "model" => chimAiResponseExtractPhpExportScalarField($rawPrompt, "model"),
        "response_connector" => $responseConnector,
    ];
}

function chimAiResponseExtractPromptModalData($rawPromptValue)
{
    $rawPrompt = chimAiResponseNormalizePromptMarkup($rawPromptValue);
    if ($rawPrompt === "") {
        return null;
    }

    $decoded = json_decode($rawPrompt, true);
    if (is_array($decoded)) {
        return chimAiResponseExtractPromptPayloadFromDecodedArray($decoded);
    }

    return chimAiResponseExtractPromptPayloadFromPhpArrayExport($rawPrompt);
}

function chimAiResponseExtractWorldKnowledgeTopics($rawPromptValue)
{
    $text = chimAiResponseNormalizeMarkup($rawPromptValue);
    if ($text === "") {
        return [];
    }

    $topics = [];
    $seen = [];

    if (preg_match_all('/<knowledge>\s*(.*?)\s*<\/knowledge>/is', $text, $knowledgeMatches) >= 1) {
        foreach ($knowledgeMatches[1] as $knowledgeBlock) {
            if (!is_string($knowledgeBlock) || trim($knowledgeBlock) === "") {
                continue;
            }

            if (preg_match_all('/<entry>\s*(.*?)\s*<\/entry>/is', $knowledgeBlock, $entryMatches) < 1) {
                continue;
            }

            foreach ($entryMatches[1] as $entryText) {
                $entry = trim(html_entity_decode(strip_tags(strval($entryText)), ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"));
                if ($entry === "") {
                    continue;
                }

                $topic = $entry;
                $colonPos = strpos($entry, ":");
                if ($colonPos !== false) {
                    $topic = trim(substr($entry, 0, $colonPos));
                }

                if ($topic === "") {
                    continue;
                }

                $topicKey = strtolower($topic);
                if (isset($seen[$topicKey])) {
                    continue;
                }

                $seen[$topicKey] = true;
                $topics[] = $topic;
            }
        }
    }

    return $topics;
}

function chimAiResponseExtractOghmaTopicAndLevel($rawPromptValue)
{
    $result = [null, 'none'];
    $text = chimAiResponseNormalizeMarkup($rawPromptValue);
    if ($text === "") {
        return $result;
    }

    $decoded = json_decode($text, true);
    if (is_array($decoded)) {
        $messageText = "";
        $promptPayload = null;
        if (isset($decoded['response_full']) && is_array($decoded['response_full'])) {
            $promptPayload = $decoded['response_full'];
        } elseif (isset($decoded['full']) && is_array($decoded['full'])) {
            $promptPayload = $decoded['full'];
        }

        if (is_array($promptPayload) && isset($promptPayload['messages']) && is_array($promptPayload['messages'])) {
            foreach ($promptPayload['messages'] as $msg) {
                if (isset($msg['content']) && is_string($msg['content'])) {
                    $messageText .= $msg['content'] . "\n";
                }
            }
        } elseif (isset($decoded['messages']) && is_array($decoded['messages'])) {
            foreach ($decoded['messages'] as $msg) {
                if (isset($msg['content']) && is_string($msg['content'])) {
                    $messageText .= $msg['content'] . "\n";
                }
            }
        } else {
            $messageText = json_encode($decoded);
        }

        $text = (string)$messageText;
    }

    $text = str_replace(["\r\n", "\r"], "\n", $text);

    if (preg_match('/#Lore Information\s*\((?=[^)]*advanced knowledge)[^)]*\):\s*([^"\<\r\n]+)/i', $text, $matches)) {
        $topic = trim(strip_tags($matches[1]));
        if ($topic !== "") {
            return [$topic, 'advanced'];
        }
    }

    if (preg_match('/#Lore Information\s*\((?=[^)]*basic knowledge)[^)]*\):\s*([^"\<\r\n]+)/i', $text, $matches)) {
        $topic = trim(strip_tags($matches[1]));
        if ($topic !== "") {
            return [$topic, 'basic'];
        }
    }

    if (preg_match('/#Lore Information[^\n]*\nYou do not know ANYTHING about\s+([^"\<\r\n]+)/i', $text, $matches)) {
        $topic = trim(strip_tags($matches[1]));
        if ($topic !== "") {
            return [$topic, 'none'];
        }
    }

    return $result;
}

function chimAiResponseFormatWorldKnowledge($rawPromptValue)
{
    [$oghmaTopic, $oghmaLevel] = chimAiResponseExtractOghmaTopicAndLevel($rawPromptValue);
    $topics = chimAiResponseExtractWorldKnowledgeTopics($rawPromptValue);
    $labels = [];
    $seen = [];

    if ($oghmaTopic !== null && trim((string)$oghmaTopic) !== "") {
        $label = trim((string)$oghmaTopic) . " (" . trim((string)$oghmaLevel) . ")";
        $labels[] = $label;
        $seen[strtolower(trim((string)$oghmaTopic))] = true;
    }

    foreach ($topics as $topic) {
        $topicKey = strtolower(trim((string)$topic));
        if ($topicKey === "" || isset($seen[$topicKey])) {
            continue;
        }
        $seen[$topicKey] = true;
        $labels[] = (string)$topic;
    }

    return empty($labels) ? "None" : implode(", ", $labels);
}

function chimAiResponseFormatPromptModalMessageBodyHtml($content)
{
    if (is_array($content)) {
        $content = json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    } else {
        $content = (string)$content;
    }

    return '<div class="prompt-modal-message-body">'
        . chimAiResponseH($content)
        . '</div>';
}

function chimAiResponseFormatPromptModalHtml($rawPromptValue)
{
    $rawPrompt = chimAiResponseNormalizePromptMarkup($rawPromptValue);
    if ($rawPrompt === "") {
        return '<pre class="prompt-raw">Prompt payload is empty for this row.</pre>';
    }

    $formattedPrompt = "";
    $promptModalData = chimAiResponseExtractPromptModalData($rawPromptValue);

    if (is_array($promptModalData)) {
        $responseConnector = is_array($promptModalData["response_connector"] ?? null)
            ? $promptModalData["response_connector"]
            : [];
        $responseConnectorLabel = trim((string)($responseConnector["label"] ?? ""));
        $responseConnectorDriver = trim((string)($responseConnector["driver"] ?? ""));
        $model = trim((string)($promptModalData["model"] ?? ""));
        if ($model === "") {
            $model = "unknown";
        }

        $formattedPrompt = '<div class="prompt-modal-messages">';
        $metaBits = [];
        if ($responseConnectorLabel !== "") {
            $metaBits[] = '<span class="prompt-meta prompt-meta-label">' . chimAiResponseH($responseConnectorLabel) . '</span>';
        }
        if ($responseConnectorDriver !== "") {
            $metaBits[] = '<span class="prompt-meta prompt-meta-driver">' . chimAiResponseH($responseConnectorDriver) . '</span>';
        }
        if ($model !== "unknown") {
            $metaBits[] = '<span class="prompt-meta prompt-meta-model">' . chimAiResponseH($model) . '</span>';
        }
        if (!empty($metaBits)) {
            $formattedPrompt .= '<div class="prompt-meta-row">' . implode("", $metaBits) . '</div>';
        }

        $messages = is_array($promptModalData["messages"] ?? null)
            ? $promptModalData["messages"]
            : [];

        foreach ($messages as $msgIndex => $msg) {
            if (!isset($msg["role"]) || !array_key_exists("content", $msg)) {
                continue;
            }

            $role = (string)$msg["role"];
            $roleClass = preg_replace('/[^a-z0-9_-]/i', '', strtolower($role));
            if ($roleClass === "") {
                $roleClass = "unknown";
            }

            $formattedPrompt .= '<div class="prompt-modal-message prompt-role-' . chimAiResponseH($roleClass) . '">';
            $formattedPrompt .= '<div class="prompt-modal-message-header">';
            $formattedPrompt .= '<span class="prompt-role">' . chimAiResponseH(strtoupper($role)) . '</span>';
            $formattedPrompt .= '<span class="prompt-index">#' . intval($msgIndex) . '</span>';
            $formattedPrompt .= '</div>';
            $formattedPrompt .= chimAiResponseFormatPromptModalMessageBodyHtml($msg["content"]);
            $formattedPrompt .= '</div>';
        }

        $formattedPrompt .= '</div>';
    }

    if ($formattedPrompt === "") {
        $formattedPrompt = '<pre class="prompt-raw">' . chimAiResponseH($rawPrompt) . '</pre>';
    }

    return $formattedPrompt;
}

function chimAiResponseTimeColor($seconds)
{
    if ($seconds <= 2.0) {
        return "#88cc88";
    }
    if ($seconds <= 5.0) {
        return "#ffff00";
    }
    if ($seconds <= 8.0) {
        return "#ffa500";
    }
    return "#ff6666";
}

function chimAiResponseFormatUrlWithTimings($url, $response)
{
    $safeUrl = trim((string)$url);
    if ($safeUrl === "") {
        return "";
    }

    if (strpos((string)$response, "Array") === 0) {
        $stripped = preg_replace('/ in \d+\.?\d* secs$/', '', $safeUrl);
        return chimAiResponseH((string)$stripped);
    }

    $pattern = '/\[AI secs\]\s+([\d.]+)\s+\[TTS secs\]\s+([\d.]+)/';
    if (preg_match($pattern, $safeUrl, $matches) !== 1) {
        return nl2br(chimAiResponseH($safeUrl));
    }

    $aiTime = floatval($matches[1] ?? 0.0);
    $totalTts = floatval($matches[2] ?? 0.0);
    $ttsOnly = max(0.0, $totalTts - $aiTime);

    $baseText = trim(substr($safeUrl, 0, (int)strpos($safeUrl, '[AI secs]')));
    $baseHtml = $baseText !== "" ? nl2br(chimAiResponseH($baseText)) . "<br>" : "";

    return $baseHtml
        . "[LLM] <span style='color:" . chimAiResponseH(chimAiResponseTimeColor($aiTime)) . "'>" . chimAiResponseH(number_format($aiTime, 2)) . "</span>"
        . " [TTS] <span style='color:" . chimAiResponseH(chimAiResponseTimeColor($ttsOnly)) . "'>" . chimAiResponseH(number_format($ttsOnly, 2)) . "</span>"
        . " [Total] <span style='color:" . chimAiResponseH(chimAiResponseTimeColor($totalTts)) . "'>" . chimAiResponseH(number_format($totalTts, 2)) . "</span>";
}

function chimAiResponseBuildUrl($page, $limit, array $extraParams = [])
{
    $params = array_merge([
        "page" => max(1, intval($page)),
        "limit" => max(10, intval($limit)),
    ], $extraParams);

    return "ai-response.php?" . http_build_query($params);
}

$db = $GLOBALS["db"];
$limit = isset($_GET["limit"]) ? intval($_GET["limit"]) : 50;
$limit = max(10, min(500, $limit));
$page = isset($_GET["page"]) ? intval($_GET["page"]) : 1;
$page = max(1, $page);
$offset = ($page - 1) * $limit;

if (isset($_GET["cleanlog"]) && $_GET["cleanlog"]) {
    chimAiResponseSafeDeleteLog($db);
    header("Location: ai-response.php");
    exit;
}

$totalRow = chimAiResponseSafeFetchAll($db, "SELECT COUNT(*) AS total FROM log");
$totalRecords = intval($totalRow[0]["total"] ?? 0);
$totalPages = max(1, (int)ceil($totalRecords / $limit));

if (isset($_GET["export"]) && ($_GET["export"] === "1" || $_GET["export"] === "log")) {
    $allRows = chimAiResponseSafeFetchAll(
        $db,
        "SELECT A.*, ROWID
         FROM log a
         ORDER BY localts DESC, rowid DESC"
    );

    $extraColumns = [];
    foreach ($allRows as $row) {
        foreach (array_keys($row) as $key) {
            if (in_array($key, ["localts", "response", "prompt", "url", "ROWID", "rowid"], true)) {
                continue;
            }
            $extraColumns[$key] = true;
        }
    }

    header("Content-Type: text/csv; charset=UTF-8");
    header("Content-Disposition: attachment; filename=\"chim_ai_responses.csv\"");
    $out = fopen("php://output", "w");
    if ($out !== false) {
        fputcsv($out, array_merge(["rowid", "time_utc", "ai_response", "oghma_topic", "prompt", "http_request"], array_keys($extraColumns)));
        foreach ($allRows as $row) {
            $csvRow = [
                intval($row["ROWID"] ?? $row["rowid"] ?? 0),
                chimAiResponseFormatLocalTs($row["localts"] ?? 0),
                chimAiResponseExtractBestText($row["response"] ?? "", $row["prompt"] ?? ""),
                chimAiResponseFormatWorldKnowledge($row["prompt"] ?? ""),
                strval($row["prompt"] ?? ""),
                strval($row["url"] ?? ""),
            ];
            foreach (array_keys($extraColumns) as $extraKey) {
                $csvRow[] = strval($row[$extraKey] ?? "");
            }
            fputcsv($out, $csvRow);
        }
        fclose($out);
    }
    exit;
}

$rows = chimAiResponseSafeFetchAll(
    $db,
    "SELECT A.*, ROWID
     FROM log a
     ORDER BY localts DESC, rowid DESC
     LIMIT " . intval($limit) . " OFFSET " . intval($offset)
);

$extraColumns = [];
foreach ($rows as $row) {
    foreach (array_keys($row) as $key) {
        if (in_array($key, ["localts", "response", "prompt", "url", "ROWID", "rowid"], true)) {
            continue;
        }
        $extraColumns[$key] = true;
    }
}

$promptModalContentById = [];

$TITLE = "AI Responses";
$BODY_CLASS = 'hub-page';
ob_start();
include(__DIR__ . DIRECTORY_SEPARATOR . "tmpl" . DIRECTORY_SEPARATOR . "head.html");
?>
<link rel="stylesheet" href="<?php echo $webRoot; ?>/ui/css/main.css">
<style>
    main.ai-response-page {
        padding: 0 12px 40px;
    }

    @font-face {
        font-family: "MagicCards";
        src: url("css/font/MagicCardsNormal.ttf") format("truetype");
        font-weight: normal;
        font-style: normal;
    }

    h1, h3 {
        font-family: "MagicCards", sans-serif;
        letter-spacing: 1.5px;
    }

    .tab-content {
        display: block;
        background: linear-gradient(135deg, rgba(42, 42, 42, 0.95), rgba(34, 34, 34, 0.98));
        padding: 20px;
        border-radius: 8px;
        border-top-left-radius: 0;
        border: 1px solid #3a3a3a;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15), inset 0 1px rgba(255, 255, 255, 0.03);
    }

    .table-container {
        max-height: calc(100vh - 430px);
        margin-top: 20px;
        width: 100%;
        overflow: auto;
        background: linear-gradient(180deg, rgba(42, 42, 42, 0.95), rgba(34, 34, 34, 0.98));
        border-radius: 10px;
        border: 1px solid #3a3a3a;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15), inset 0 1px rgba(255, 255, 255, 0.03);
        padding: 12px;
    }

    .ai-response-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 0;
        font-size: small;
    }

    .ai-response-table th {
        position: sticky;
        top: 0;
        z-index: 2;
        padding: 12px;
        font-weight: bold;
        text-align: left;
        color: rgb(242, 124, 17);
        background: rgba(26, 26, 26, 0.95);
        border-bottom: 2px solid rgba(242, 124, 17, 0.3);
    }

    .ai-response-table td {
        padding: 10px;
        text-align: left;
        border-bottom: 1px solid rgba(74, 74, 74, 0.3);
        color: #f8f9fa;
        word-wrap: break-word;
        overflow-wrap: anywhere;
        vertical-align: top;
        line-height: 1.5;
    }

    .ai-response-table tr:hover td {
        background: rgba(242, 124, 17, 0.05);
    }

    .response-cell {
        white-space: pre-wrap;
        max-width: 680px;
    }

    .http-cell {
        min-width: 240px;
    }

    .pagination-row {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-top: 12px;
        flex-wrap: wrap;
    }

    .info-message {
        color: #9ca3af;
        padding: 14px 2px;
    }

    .btn-base {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 7px 12px;
        border-radius: 8px;
        border: 1px solid rgba(138, 155, 182, 0.38);
        background: rgba(30, 35, 45, 0.8);
        color: #fff;
        text-decoration: none;
        cursor: pointer;
        transition: all 0.2s ease-in-out;
    }

    .btn-base:hover {
        color: #fff;
        text-decoration: none;
        transform: translateY(-1px);
        border-color: rgba(242, 124, 17, 0.45);
    }

    .btn-primary {
        background: linear-gradient(135deg, #204e7a, #16395a);
    }

    .btn-danger {
        background: linear-gradient(135deg, #9b1c31, #6f1424);
    }

    .view-contents-btn {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border: none;
        color: white;
        padding: 8px 16px;
        text-align: center;
        text-decoration: none;
        display: inline-block;
        font-size: 14px;
        margin: 2px;
        cursor: pointer;
        border-radius: 6px;
        transition: all 0.3s ease;
        font-weight: 600;
        box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        white-space: nowrap;
    }

    .view-contents-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(102, 126, 234, 0.4);
    }

    .modal {
        display: none;
        position: fixed;
        z-index: 100000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.5);
        backdrop-filter: blur(5px);
        -webkit-backdrop-filter: blur(5px);
    }

    .modal-content {
        background: linear-gradient(135deg, rgba(42, 42, 42, 0.98), rgba(34, 34, 34, 0.98));
        margin: 3% auto;
        padding: 20px;
        border: 2px solid rgba(242, 124, 17, 0.5);
        width: 90%;
        max-width: 1600px;
        max-height: 90vh;
        overflow-y: auto;
        border-radius: 10px;
        color: #fff;
        position: relative;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5), inset 0 1px rgba(255, 255, 255, 0.03);
    }

    .close {
        color: #aaa;
        float: right;
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
        position: sticky;
        z-index: 1;
    }

    .close:hover,
    .close:focus {
        color: #fff;
        text-decoration: none;
    }

    #modalText {
        white-space: normal;
        word-wrap: break-word;
        line-height: 1.8;
        padding: 20px;
        font-size: 13px;
        font-family: "Consolas", "Monaco", "Courier New", monospace;
        background: #1a1a1a;
        border-radius: 8px;
        color: #e0e0e0;
    }

    body.modal-open {
        overflow: hidden;
    }

    .prompt-meta-row {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 14px;
    }

    .prompt-meta {
        display: inline-block;
        padding: 3px 8px;
        border-radius: 999px;
    }

    .prompt-meta-label {
        background: #1f2937;
        color: #e5e7eb;
    }

    .prompt-meta-driver {
        background: #111827;
        color: #93c5fd;
    }

    .prompt-meta-model {
        background: #0f172a;
        color: #cbd5e1;
    }

    .prompt-modal-message {
        margin: 10px 0;
        border-left: 3px solid #c586c0;
        padding-left: 15px;
    }

    .prompt-role-system {
        border-left-color: #4ec9b0;
    }

    .prompt-role-user {
        border-left-color: #dcdcaa;
    }

    .prompt-role-assistant {
        border-left-color: #c586c0;
    }

    .prompt-modal-message-header {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 6px;
    }

    .prompt-role {
        color: #dcdcaa;
        font-weight: bold;
    }

    .prompt-role-system .prompt-role {
        color: #4ec9b0;
    }

    .prompt-role-user .prompt-role {
        color: #dcdcaa;
    }

    .prompt-role-assistant .prompt-role {
        color: #c586c0;
    }

    .prompt-index {
        color: #6b7280;
        font-size: 12px;
    }

    .prompt-modal-message-body,
    .prompt-raw {
        color: #ce9178;
        white-space: pre-wrap;
        overflow-wrap: anywhere;
        word-break: break-word;
        margin-top: 5px;
        background: #1e1e1e;
        padding: 10px;
        border-radius: 5px;
    }

    @media (max-width: 768px) {
        .table-container {
            margin: 10px -15px;
            border-radius: 0;
        }
        .ai-response-table {
            font-size: smaller;
        }
        .ai-response-table th,
        .ai-response-table td {
            padding: 8px;
        }
    }
</style>
<link rel="stylesheet" href="<?php echo $webRoot; ?>/ui/css/hub-navigation.css?v=<?php echo filemtime(__DIR__ . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'hub-navigation.css'); ?>">
<?php include(__DIR__ . DIRECTORY_SEPARATOR . "tmpl" . DIRECTORY_SEPARATOR . "navbar.php"); ?>

<div id="contentModal" class="modal">
    <div class="modal-content">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
            <h2 style="margin: 0; color: rgb(242, 124, 17); font-family: 'MagicCards', sans-serif;">&#x1F4DC; Prompt Viewer</h2>
            <div>
                <button id="copyPromptBtn" class="btn-base btn-primary" style="margin-right: 10px; padding: 8px 16px;">&#x1F4CB; Copy</button>
                <span class="close">&times;</span>
            </div>
        </div>
        <div id="modalText"></div>
    </div>
</div>

<main class="container-fluid ai-response-page">
    <div class="tab-container">
        <?php
        $eventsMemoriesActiveTab = 'responselog';
        include(__DIR__ . DIRECTORY_SEPARATOR . 'tmpl' . DIRECTORY_SEPARATOR . 'events_memories_navigation.php');
        ?>

        <div id="responselog-tab" class="tab-content">
            <div style="background: #2a2a2a; border-left: 4px solid rgb(242, 124, 17); padding: 12px 15px; border-radius: 5px; margin: 15px 0; font-size: 0.9em;">
                <span style="color: rgb(242, 124, 17); font-weight: bold;">AI Responses:</span>
                <span style="color: #f8f9fa;">Complete log of AI-generated responses including the full context payload sent to the LLM. Use this to debug model behavior, prompt composition, Oghma topics, and timing.</span>
            </div>

            <div class="pagination-row" style="margin-bottom: 10px;">
                <span class="info-message" style="padding:0">Page <?php echo intval($page); ?> / <?php echo intval($totalPages); ?> (<?php echo intval($totalRecords); ?> rows)</span>
                <?php if ($page > 1): ?>
                    <a class="btn-base btn-primary" href="<?php echo chimAiResponseH(chimAiResponseBuildUrl($page - 1, $limit)); ?>">Previous</a>
                <?php endif; ?>
                <?php if ($page < $totalPages): ?>
                    <a class="btn-base btn-primary" href="<?php echo chimAiResponseH(chimAiResponseBuildUrl($page + 1, $limit)); ?>">Next</a>
                <?php endif; ?>
                <div style="margin-left:auto; display:flex; gap:10px; flex-wrap:wrap;">
                    <button onclick="if(confirm('This will clear all the entries in the Response Log. ARE YOU SURE?')) window.location.href='ai-response.php?cleanlog=true'" class="btn-base btn-danger" style="padding: 8px 12px; font-size: 0.9em;">Clean Response Log</button>
                    <button onclick="window.open('<?php echo chimAiResponseH(chimAiResponseBuildUrl($page, $limit, ['export' => 1])); ?>', '_blank')" class="btn-base btn-primary" style="padding: 8px 12px; font-size: 0.9em;">Export Response Log</button>
                </div>
            </div>

            <div class="table-container">
                <table class="ai-response-table">
                    <thead>
                    <tr>
                        <th style="width:11%">Time (UTC)</th>
                        <th style="width:29%">AI Response</th>
                        <th style="width:16%">Oghma Topic</th>
                        <th style="width:12%">Prompt</th>
                        <th style="width:20%">HTTP Request</th>
                        <th style="width:7%">rowid</th>
                        <?php foreach (array_keys($extraColumns) as $extraColumn): ?>
                            <th><?php echo chimAiResponseH($extraColumn); ?></th>
                        <?php endforeach; ?>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (count($rows) > 0): ?>
                        <?php foreach ($rows as $row): ?>
                            <?php
                            $rowId = $row["ROWID"] ?? $row["rowid"] ?? md5((string)($row["prompt"] ?? ""));
                            $promptId = "prompt_" . preg_replace('/[^A-Za-z0-9_\-]/', "_", (string)$rowId);
                            $promptModalContentById[$promptId] = chimAiResponseFormatPromptModalHtml($row["prompt"] ?? "");
                            $displayResponse = chimAiResponseExtractBestText($row["response"] ?? "", $row["prompt"] ?? "");
                            $oghmaTopicDisplay = chimAiResponseFormatWorldKnowledge($row["prompt"] ?? "");
                            ?>
                            <tr>
                                <td><?php echo chimAiResponseH(chimAiResponseFormatLocalTs($row["localts"] ?? 0)); ?></td>
                                <td class="response-cell"><?php echo nl2br(chimAiResponseH($displayResponse)); ?></td>
                                <td><?php echo nl2br(chimAiResponseH($oghmaTopicDisplay)); ?></td>
                                <td>
                                    <button class="view-contents-btn" data-prompt-id="<?php echo chimAiResponseH($promptId); ?>">&#x1F9FE; View Prompt</button>
                                </td>
                                <td class="http-cell"><?php echo chimAiResponseFormatUrlWithTimings($row["url"] ?? "", $row["response"] ?? ""); ?></td>
                                <td><?php echo chimAiResponseH($rowId); ?></td>
                                <?php foreach (array_keys($extraColumns) as $extraColumn): ?>
                                    <td><?php echo nl2br(chimAiResponseH($row[$extraColumn] ?? "")); ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="<?php echo 6 + count($extraColumns); ?>">No AI response rows found.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if (!empty($promptModalContentById)): ?>
                <div id="prompt-modal-store" style="display:none;">
                    <?php foreach ($promptModalContentById as $promptId => $promptHtml): ?>
                        <div id="<?php echo chimAiResponseH($promptId); ?>"><?php echo $promptHtml; ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="pagination-row">
                <span class="info-message" style="padding:0">Page <?php echo intval($page); ?> / <?php echo intval($totalPages); ?> (<?php echo intval($totalRecords); ?> rows)</span>
                <?php if ($page > 1): ?>
                    <a class="btn-base" href="<?php echo chimAiResponseH(chimAiResponseBuildUrl($page - 1, $limit)); ?>">Previous</a>
                <?php endif; ?>
                <?php if ($page < $totalPages): ?>
                    <a class="btn-base" href="<?php echo chimAiResponseH(chimAiResponseBuildUrl($page + 1, $limit)); ?>">Next</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<script>
document.addEventListener("DOMContentLoaded", function() {
    var modal = document.getElementById("contentModal");
    var modalText = document.getElementById("modalText");
    var span = document.getElementsByClassName("close")[0];
    var copyBtn = document.getElementById("copyPromptBtn");

    if (!modal || !modalText || !span || !copyBtn) {
        return;
    }

    span.onclick = function() {
        modal.style.display = "none";
        document.body.classList.remove("modal-open");
    };

    window.onclick = function(event) {
        if (event.target == modal) {
            modal.style.display = "none";
            document.body.classList.remove("modal-open");
        }
    };

    copyBtn.onclick = function() {
        var textToCopy = modalText.innerText || modalText.textContent;

        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(textToCopy).then(function() {
                var originalText = copyBtn.innerHTML;
                copyBtn.innerHTML = "&#x2705; Copied!";
                copyBtn.style.background = "#28a745";
                setTimeout(function() {
                    copyBtn.innerHTML = originalText;
                    copyBtn.style.background = "";
                }, 2000);
            }).catch(function(err) {
                console.error("Failed to copy: ", err);
                alert("Failed to copy to clipboard");
            });
        } else {
            var textArea = document.createElement("textarea");
            textArea.value = textToCopy;
            textArea.style.position = "fixed";
            textArea.style.left = "-999999px";
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            try {
                document.execCommand("copy");
                var originalText = copyBtn.innerHTML;
                copyBtn.innerHTML = "&#x2705; Copied!";
                copyBtn.style.background = "#28a745";
                setTimeout(function() {
                    copyBtn.innerHTML = originalText;
                    copyBtn.style.background = "";
                }, 2000);
            } catch (err) {
                console.error("Fallback copy failed: ", err);
                alert("Failed to copy to clipboard");
            }
            document.body.removeChild(textArea);
        }
    };

    document.querySelectorAll(".view-contents-btn").forEach(function(element) {
        element.addEventListener("click", function() {
            var promptId = this.getAttribute("data-prompt-id");
            var promptDiv = document.getElementById(promptId);
            if (promptDiv) {
                modalText.innerHTML = promptDiv.innerHTML;
            } else {
                modalText.innerHTML = this.getAttribute("data-full-content") || "Content not found";
            }
            modal.style.display = "block";
            document.body.classList.add("modal-open");
        });
    });
});
</script>
<?php
$buffer = ob_get_contents();
ob_end_clean();
$buffer = preg_replace('/(<title>)(.*?)(<\/title>)/i', '$1' . $TITLE . '$3', $buffer);
echo $buffer;
?>
