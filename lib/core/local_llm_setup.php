<?php

require_once __DIR__ . DIRECTORY_SEPARATOR . 'llm_connector.class.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'api_badge.class.php';

function herikaLocalLlmServerCatalog(): array
{
    return [
        'lm_studio' => ['label' => 'LM Studio', 'port' => 1234],
        'ollama' => ['label' => 'Ollama', 'port' => 11434],
        'llama_cpp' => ['label' => 'llama.cpp', 'port' => 8080],
        'koboldcpp' => ['label' => 'KoboldCPP', 'port' => 5001],
        'other' => ['label' => 'Other OpenAI-compatible server', 'port' => null],
    ];
}

function herikaLocalLlmBoolish($value): bool
{
    if (is_bool($value)) {
        return $value;
    }

    $normalized = strtolower(trim(strval($value ?? '')));
    return $normalized !== '' && $normalized !== '0' && $normalized !== 'false'
        && $normalized !== 'no' && $normalized !== 'off';
}

function herikaLocalLlmDefaultEndpoint(string $serverType, string $hostIp): string
{
    $catalog = herikaLocalLlmServerCatalog();
    $hostIp = trim($hostIp);
    if (!isset($catalog[$serverType]) || $serverType === 'other' || $hostIp === '') {
        return '';
    }

    return 'http://' . $hostIp . ':' . intval($catalog[$serverType]['port']) . '/v1/chat/completions';
}

function herikaLocalLlmNetworkIps(): array
{
    $result = ['host_ip' => '', 'wsl_ip' => ''];
    if (empty($GLOBALS['db'])) {
        return $result;
    }

    foreach (['Network/HOST_IP' => 'host_ip', 'Network/WSL_IP' => 'wsl_ip'] as $settingId => $resultKey) {
        try {
            $row = $GLOBALS['db']->fetchOne(
                "SELECT value FROM conf_opts WHERE id='" . $GLOBALS['db']->escape($settingId) . "' LIMIT 1"
            );
            $result[$resultKey] = trim(strval($row['value'] ?? ''));
        } catch (Throwable $e) {
            $result[$resultKey] = '';
        }
    }

    return $result;
}

function herikaLocalLlmIsAllowedHost(string $host): bool
{
    $host = strtolower(trim($host, "[] \t\n\r\0\x0B"));
    if ($host === 'localhost') {
        return true;
    }

    $packed = @inet_pton($host);
    if ($packed === false) {
        return false;
    }

    if (strlen($packed) === 4) {
        $octets = array_values(unpack('C4', $packed));
        if ($octets[0] === 127 || $octets[0] === 10) {
            return true;
        }
        if ($octets[0] === 172 && $octets[1] >= 16 && $octets[1] <= 31) {
            return true;
        }
        if ($octets[0] === 192 && $octets[1] === 168) {
            return true;
        }
        return false;
    }

    if ($packed === str_repeat("\0", 15) . "\1") {
        return true;
    }

    $firstByte = ord($packed[0]);
    return $firstByte === 0xfc || $firstByte === 0xfd;
}

function herikaLocalLlmValidateUrl(string $rawUrl): string
{
    $url = trim($rawUrl);
    if ($url === '' || strlen($url) > 2048) {
        throw new InvalidArgumentException('Enter a local server URL.');
    }

    $parts = @parse_url($url);
    if (!is_array($parts)) {
        throw new InvalidArgumentException('Use a valid local server URL.');
    }

    $scheme = strtolower(strval($parts['scheme'] ?? ''));
    $host = trim(strval($parts['host'] ?? ''));
    if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
        throw new InvalidArgumentException('Use a valid http:// or https:// local server URL.');
    }
    if (isset($parts['user']) || isset($parts['pass'])) {
        throw new InvalidArgumentException('Put authentication in the API key field, not in the URL.');
    }
    if (!herikaLocalLlmIsAllowedHost($host)) {
        throw new InvalidArgumentException('Use localhost or a private LAN IP for a local LLM server.');
    }

    return $url;
}

function herikaLocalLlmUrlIsAllowed(string $rawUrl): bool
{
    try {
        herikaLocalLlmValidateUrl($rawUrl);
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function herikaLocalLlmNormalizeSetup(array $raw): array
{
    $catalog = herikaLocalLlmServerCatalog();
    $serverType = strtolower(trim(strval($raw['server_type'] ?? $raw['qs_local_llm_server_type'] ?? 'lm_studio')));
    if (!isset($catalog[$serverType])) {
        throw new InvalidArgumentException('Choose a supported local LLM server.');
    }

    $scope = strtolower(trim(strval($raw['scope'] ?? $raw['qs_local_llm_scope'] ?? 'conversations')));
    if (!in_array($scope, ['conversations', 'all'], true)) {
        throw new InvalidArgumentException('Choose a valid Local LLM routing option.');
    }

    $model = trim(strval($raw['model'] ?? $raw['qs_local_llm_model'] ?? ''));
    if ($model === '') {
        throw new InvalidArgumentException('Enter the model name loaded by your local server.');
    }
    $modelLength = function_exists('mb_strlen') ? mb_strlen($model) : strlen($model);
    if ($modelLength > 255) {
        throw new InvalidArgumentException('The model name is too long.');
    }

    $apiKey = strval($raw['api_key'] ?? $raw['qs_local_llm_api_key'] ?? '');
    if (strlen($apiKey) > 8192) {
        throw new InvalidArgumentException('The API key is too long.');
    }

    $timeout = intval($raw['timeout'] ?? $raw['qs_local_llm_timeout'] ?? 30);
    if ($timeout < 5 || $timeout > 120) {
        throw new InvalidArgumentException('Use a timeout from 5 to 120 seconds.');
    }

    return [
        'server_type' => $serverType,
        'server_label' => $catalog[$serverType]['label'],
        'url' => herikaLocalLlmValidateUrl(strval($raw['url'] ?? $raw['qs_local_llm_url'] ?? '')),
        'model' => $model,
        'api_key' => $apiKey,
        'disable_streaming' => herikaLocalLlmBoolish($raw['disable_streaming'] ?? $raw['qs_local_llm_disable_streaming'] ?? false),
        'timeout' => $timeout,
        'scope' => $scope,
    ];
}

function herikaLocalLlmManagedConnector(): ?array
{
    if (empty($GLOBALS['db'])) {
        return null;
    }

    $row = $GLOBALS['db']->fetchOne(
        "SELECT * FROM core_llm_connector WHERE COALESCE(metadata, '{}'::jsonb) @> " .
        "'{\"quickstart_managed\":true}'::jsonb ORDER BY id ASC LIMIT 1"
    );
    return is_array($row) && intval($row['id'] ?? 0) > 0 ? $row : null;
}

function herikaLocalLlmCurrentSetup(): array
{
    $networkIps = herikaLocalLlmNetworkIps();
    $defaults = [
        'server_type' => 'lm_studio',
        'url' => herikaLocalLlmDefaultEndpoint('lm_studio', $networkIps['host_ip']),
        'model' => '',
        'disable_streaming' => false,
        'timeout' => 30,
        'scope' => 'conversations',
        'connector_id' => 0,
        'has_api_key' => false,
    ];

    $connector = herikaLocalLlmManagedConnector();
    if ($connector === null) {
        return $defaults + $networkIps;
    }

    $metadata = json_decode(strval($connector['metadata'] ?? '{}'), true);
    $metadata = is_array($metadata) ? $metadata : [];
    return [
        'server_type' => isset(herikaLocalLlmServerCatalog()[$metadata['quickstart_server_type'] ?? ''])
            ? strval($metadata['quickstart_server_type'])
            : 'other',
        'url' => trim(strval($connector['url'] ?? '')),
        'model' => trim(strval($connector['model'] ?? '')),
        'disable_streaming' => herikaLocalLlmBoolish($metadata['disable_streaming'] ?? false),
        'timeout' => max(5, min(120, intval($metadata['quickstart_timeout'] ?? 30))),
        'scope' => in_array(($metadata['quickstart_scope'] ?? ''), ['conversations', 'all'], true)
            ? strval($metadata['quickstart_scope'])
            : 'conversations',
        'connector_id' => intval($connector['id']),
        'has_api_key' => intval($connector['api_badge_id'] ?? 0) > 0,
    ] + $networkIps;
}

function herikaLocalLlmUpsertApiBadge(?array $existingConnector, string $apiKey): ?int
{
    $existingBadgeId = intval($existingConnector['api_badge_id'] ?? 0);
    if ($apiKey === '') {
        return $existingBadgeId > 0 ? $existingBadgeId : null;
    }

    $badges = new ApiBadge();
    if ($existingBadgeId > 0 && is_array($badges->getById($existingBadgeId))) {
        if (!$badges->update($existingBadgeId, ['label' => 'Quickstart Local LLM', 'api_key' => $apiKey])) {
            throw new RuntimeException('Unable to update the Local LLM API key.');
        }
        return $existingBadgeId;
    }

    $createdId = intval($badges->create(['label' => 'Quickstart Local LLM', 'api_key' => $apiKey]));
    if ($createdId <= 0) {
        throw new RuntimeException('Unable to save the Local LLM API key.');
    }
    return $createdId;
}

function herikaLocalLlmUpsertConnector(array $setup): int
{
    $connectors = new LLMConnector();
    $existing = herikaLocalLlmManagedConnector();
    $apiBadgeId = herikaLocalLlmUpsertApiBadge($existing, $setup['api_key']);
    $metadata = [
        'quickstart_managed' => true,
        'quickstart_server_type' => $setup['server_type'],
        'quickstart_scope' => $setup['scope'],
        'quickstart_timeout' => $setup['timeout'],
        'disable_streaming' => $setup['disable_streaming'],
    ];
    $payload = [
        'label' => 'Local LLM - ' . $setup['server_label'],
        'metadata' => json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        'url' => $setup['url'],
        'model' => $setup['model'],
        'provider' => 'local',
        'driver' => 'openaijson',
        'service' => 'custom',
        'reasoning_model' => 0,
        'max_tokens' => 512,
        'enforce_json' => 1,
        'prefill_json' => 0,
        'api_badge_id' => $apiBadgeId,
        'json_schema' => 0,
        'temperature' => 0.7,
    ];

    $existingId = intval($existing['id'] ?? 0);
    if ($existingId > 0) {
        if (!$connectors->update($existingId, $payload)) {
            throw new RuntimeException('Unable to update the Local LLM connector.');
        }
        return $existingId;
    }

    $createdId = intval($connectors->create($payload));
    if ($createdId <= 0) {
        throw new RuntimeException('Unable to create the Local LLM connector.');
    }
    return $createdId;
}

function herikaLocalLlmRouteConnector(int $connectorId, string $scope): void
{
    $connectorId = intval($connectorId);
    if ($connectorId <= 0) {
        throw new InvalidArgumentException('Local LLM connector is missing.');
    }

    $profileFields = [
        'llm_primary_id',
        'llm_secondary_id',
        'llm_tertiary_id',
        'llm_quaternary_id',
    ];
    if ($scope === 'all') {
        $profileFields[] = 'diary_connector_id';
        $profileFields[] = 'llm_formatter_id';
    }
    $assignments = array_map(static function (string $field) use ($connectorId): string {
        return $field . '=' . $connectorId;
    }, $profileFields);

    $result = $GLOBALS['db']->query(
        'UPDATE core_profiles SET ' . implode(', ', $assignments) .
        " WHERE default_npc='1' OR default_narrator='1'"
    );
    if ($result === false) {
        throw new RuntimeException('Unable to route the Local LLM to the default profiles.');
    }

    if (function_exists('pg_affected_rows') && pg_affected_rows($result) === 0) {
        $fallback = $GLOBALS['db']->fetchOne('SELECT id FROM core_profiles ORDER BY id ASC LIMIT 1');
        $fallbackId = intval($fallback['id'] ?? 0);
        if ($fallbackId <= 0 || !$GLOBALS['db']->updateRow(
            'core_profiles',
            array_fill_keys($profileFields, $connectorId),
            'id=' . $fallbackId
        )) {
            throw new RuntimeException('No default profile is available for Local LLM routing.');
        }
    }

    if ($scope !== 'all') {
        return;
    }

    $globalFields = [
        'CORE_CONNECTOR_PLAYER',
        'CORE_CONNECTOR_SUMMARY',
        'CORE_CONNECTOR_MEDIUMTERM',
        'CORE_CONNECTOR_SCENECLASSIFIER',
        'CORE_CONNECTOR_PROFILES',
        'CORE_CONNECTOR_DIRECTOR',
        'RELLLM_CONNECTOR',
        'CORE_CONNECTOR_OGHMA_CUSTOM',
    ];
    foreach ($globalFields as $field) {
        if (!chimSetGeneralSetting($field, $connectorId, chimGetSchemaDescription($field))) {
            throw new RuntimeException('Unable to route ' . $field . ' to the Local LLM.');
        }
    }
}

function herikaLocalLlmApplySetup(array $raw): array
{
    $setup = herikaLocalLlmNormalizeSetup($raw);
    $connectorId = herikaLocalLlmUpsertConnector($setup);
    herikaLocalLlmRouteConnector($connectorId, $setup['scope']);

    return [
        'connector_id' => $connectorId,
        'server_type' => $setup['server_type'],
        'scope' => $setup['scope'],
    ];
}

function herikaLocalLlmTestDraft(array $raw): array
{
    $started = microtime(true);
    try {
        $setup = herikaLocalLlmNormalizeSetup($raw);
    } catch (Throwable $e) {
        return [
            'status' => 'fail',
            'message' => $e->getMessage(),
            'elapsed_ms' => 0,
            'details' => [],
        ];
    }

    if ($setup['api_key'] === '') {
        $managedConnector = herikaLocalLlmManagedConnector();
        $badgeId = intval($managedConnector['api_badge_id'] ?? 0);
        if ($badgeId > 0) {
            $badge = (new ApiBadge())->getById($badgeId);
            if (is_array($badge)) {
                $setup['api_key'] = strval($badge['api_key'] ?? '');
            }
        }
    }

    if (!function_exists('curl_init')) {
        return [
            'status' => 'fail',
            'message' => 'The PHP cURL extension is required to test a Local LLM.',
            'elapsed_ms' => 0,
            'details' => [],
        ];
    }

    $payload = json_encode([
        'model' => $setup['model'],
        'messages' => [
            ['role' => 'system', 'content' => 'You are a connection health check. Reply with OK.'],
            ['role' => 'user', 'content' => 'Reply with exactly OK.'],
        ],
        'stream' => false,
        'max_tokens' => 16,
        'temperature' => 0,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $headers = ['Content-Type: application/json', 'Accept: application/json'];
    if ($setup['api_key'] !== '') {
        $headers[] = 'Authorization: Bearer ' . $setup['api_key'];
    }

    $responseBody = '';
    $responseTooLarge = false;
    $ch = curl_init($setup['url']);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_CONNECTTIMEOUT => min(5, $setup['timeout']),
        CURLOPT_TIMEOUT => $setup['timeout'],
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_MAXREDIRS => 0,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$responseBody, &$responseTooLarge): int {
            if (strlen($responseBody) + strlen($chunk) > 262144) {
                $responseTooLarge = true;
                return 0;
            }
            $responseBody .= $chunk;
            return strlen($chunk);
        },
    ]);
    $curlOk = curl_exec($ch);
    $httpCode = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
    $curlError = trim(strval(curl_error($ch)));
    curl_close($ch);

    $elapsedMs = intval(round((microtime(true) - $started) * 1000));
    if ($responseTooLarge) {
        return ['status' => 'fail', 'message' => 'The Local LLM response was too large.', 'elapsed_ms' => $elapsedMs, 'details' => []];
    }
    if ($curlOk === false) {
        return [
            'status' => 'fail',
            'message' => $curlError !== '' ? $curlError : 'Could not reach the Local LLM server.',
            'elapsed_ms' => $elapsedMs,
            'details' => [],
        ];
    }
    if ($httpCode < 200 || $httpCode >= 300) {
        return [
            'status' => 'fail',
            'message' => 'The Local LLM server returned HTTP ' . $httpCode . '.',
            'elapsed_ms' => $elapsedMs,
            'details' => [],
        ];
    }

    $decoded = json_decode($responseBody, true);
    $content = is_array($decoded)
        ? trim(strval($decoded['choices'][0]['message']['content'] ?? $decoded['choices'][0]['text'] ?? ''))
        : '';
    if ($content === '') {
        return [
            'status' => 'fail',
            'message' => 'The Local LLM returned an empty or unsupported response.',
            'elapsed_ms' => $elapsedMs,
            'details' => [],
        ];
    }

    return [
        'status' => 'pass',
        'message' => 'Local LLM responded successfully.',
        'elapsed_ms' => $elapsedMs,
        'details' => ['response_preview' => function_exists('mb_substr') ? mb_substr($content, 0, 180) : substr($content, 0, 180)],
    ];
}
