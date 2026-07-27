<?php

function chimBglNormalizeRefId(string $refid): string
{
    $refid = strtoupper(trim($refid));
    if (str_starts_with($refid, '0X')) {
        $refid = substr($refid, 2);
    }

    $refid = preg_replace('/[^0-9A-F]/', '', $refid) ?? '';
    if ($refid === '' || strlen($refid) > 8) {
        return '';
    }

    return str_pad($refid, 8, '0', STR_PAD_LEFT);
}

function chimBglBoolean($value): bool
{
    return $value === true ||
        $value === 1 ||
        $value === '1' ||
        $value === 't' ||
        $value === 'true' ||
        $value === 'on';
}

function chimBglResolveNpc(NpcMaster $npcMaster, string $refid = '', string $npcName = ''): ?array
{
    $normalizedRefId = chimBglNormalizeRefId($refid);
    if ($normalizedRefId !== '') {
        $npc = $npcMaster->getByRefId($normalizedRefId);
        if (is_array($npc)) {
            return $npc;
        }
    }

    $npcName = trim($npcName);
    if ($npcName !== '') {
        $npc = $npcMaster->getByName($npcName);
        if (is_array($npc)) {
            return $npc;
        }
    }

    return null;
}

function chimBglNpcStatus(NpcMaster $npcMaster, ?array $npc, string $requestedRefId = '', string $requestedName = ''): array
{
    if (!$npc) {
        return [
            'exists' => false,
            'npc_id' => null,
            'name' => trim($requestedName),
            'refid' => chimBglNormalizeRefId($requestedRefId),
            'background_life_enabled' => false,
            'auto_actions' => false,
            'send_letters' => false,
            'hourly_tracking' => false,
        ];
    }

    $extendedData = $npcMaster->getExtendedData($npc);
    $metadata = $npcMaster->getMetadata($npc);

    return [
        'exists' => true,
        'npc_id' => (int)($npc['id'] ?? 0),
        'name' => trim((string)($npc['npc_name'] ?? $requestedName)),
        'refid' => chimBglNormalizeRefId((string)($npc['refid'] ?? $requestedRefId)),
        'background_life_enabled' => chimBglBoolean($extendedData['background_life_enabled'] ?? false),
        'auto_actions' => chimBglBoolean($extendedData['background_life_commands'] ?? false),
        'send_letters' => chimBglBoolean($extendedData['background_life_letters'] ?? false),
        'hourly_tracking' => chimBglBoolean($metadata['gps_track'] ?? false),
    ];
}

function chimBglUpdateNpcSetting(NpcMaster $npcMaster, array $npc, string $setting, bool $value): array
{
    $settingMap = [
        'auto_actions' => ['container' => 'extended', 'key' => 'background_life_commands'],
        'send_letters' => ['container' => 'extended', 'key' => 'background_life_letters'],
        'hourly_tracking' => ['container' => 'metadata', 'key' => 'gps_track'],
    ];
    if (!isset($settingMap[$setting])) {
        throw new InvalidArgumentException('Unsupported Background Life setting');
    }

    $mapping = $settingMap[$setting];
    if ($mapping['container'] === 'metadata') {
        $data = $npcMaster->getMetadata($npc);
        $data[$mapping['key']] = $value;
        $npc = $npcMaster->setMetadata($npc, $data);
    } else {
        $data = $npcMaster->getExtendedData($npc);
        $data[$mapping['key']] = $value;
        $npc = $npcMaster->setExtendedData($npc, $data);
    }

    $npcMaster->updateByArray($npc);
    $updatedNpc = $npcMaster->getById((int)$npc['id']);
    if (!is_array($updatedNpc)) {
        throw new RuntimeException('Could not read saved Background Life setting');
    }

    $status = chimBglNpcStatus($npcMaster, $updatedNpc);
    if (($status[$setting] ?? null) !== $value) {
        throw new RuntimeException('Could not save Background Life setting');
    }

    return $status;
}

function chimBglSetEnabled(NpcMaster $npcMaster, array $npc, bool $enabled): array
{
    $extendedData = $npcMaster->getExtendedData($npc);
    $extendedData['background_life_enabled'] = $enabled;
    $npc = $npcMaster->setExtendedData($npc, $extendedData);
    $npcMaster->updateByArray($npc);

    $updatedNpc = $npcMaster->getById((int)$npc['id']);
    if (!is_array($updatedNpc)) {
        throw new RuntimeException('Could not read saved Background Life enrollment');
    }

    $status = chimBglNpcStatus($npcMaster, $updatedNpc);
    if ($status['background_life_enabled'] !== $enabled) {
        throw new RuntimeException('Could not save Background Life enrollment');
    }

    return $status;
}

function chimBglNormalizeInstruction(string $instruction): string
{
    $instruction = preg_replace(
        '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u',
        '',
        $instruction
    ) ?? '';
    $instruction = trim($instruction);
    $instructionLength = function_exists('mb_strlen')
        ? mb_strlen($instruction, 'UTF-8')
        : strlen($instruction);
    if ($instructionLength > 1000) {
        throw new InvalidArgumentException('Instruction must be 1000 characters or fewer');
    }

    return $instruction;
}

function chimBglQueueRequest(
    $db,
    array $npc,
    string $requestType,
    string $instruction = ''
): string {
    if (!in_array($requestType, ['action', 'letter', 'instruction'], true)) {
        throw new InvalidArgumentException('Unsupported Background Life request');
    }
    $instruction = chimBglNormalizeInstruction($instruction);
    if ($requestType === 'instruction' && $instruction === '') {
        throw new InvalidArgumentException('Direct instruction is required');
    }

    $queueId = 'background_life_request_queue_' .
        time() . '_' .
        bin2hex(random_bytes(6));
    $payload = [
        'created_at' => time(),
        'attempts' => 0,
        'request_type' => $requestType,
        'npc_id' => (int)($npc['id'] ?? 0),
        'npc_name' => trim((string)($npc['npc_name'] ?? '')),
        'refid' => chimBglNormalizeRefId((string)($npc['refid'] ?? '')),
    ];
    if ($requestType === 'instruction') {
        $payload['instruction'] = $instruction;
    }

    $encoded = json_encode(
        $payload,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
    );
    $saved = $db->upsertRowOnConflict('conf_opts', [
        'id' => $queueId,
        'value' => $encoded,
    ], 'id');
    if ($saved === false) {
        throw new RuntimeException('Could not queue Background Life request');
    }

    return $queueId;
}

function chimBglRunQueuedRequest(
    string $enginePath,
    array $npc,
    string $requestType,
    string $instruction = ''
): array
{
    $npcName = trim((string)($npc['npc_name'] ?? ''));
    if ($npcName === '') {
        throw new RuntimeException('Queued Background Life request has no NPC name');
    }

    $extendedData = json_decode((string)($npc['extended_data'] ?? '{}'), true);
    if (!is_array($extendedData)) {
        $extendedData = [];
    }

    $instruction = chimBglNormalizeInstruction($instruction);
    if ($requestType === 'letter') {
        $script = 'service/background_life_runner.php';
        $arguments = [$npcName, 'forceletter'];
    } elseif (chimBglBoolean($extendedData['background_life_commands'] ?? false)) {
        $script = 'service/background_life_runner_v2.php';
        $arguments = [$npcName, 'full', 'forceaction'];
    } else {
        $script = 'service/background_life_runner.php';
        $arguments = [$npcName, 'full', 'forceaction'];
    }
    if ($requestType === 'instruction') {
        if ($instruction === '') {
            throw new RuntimeException('Queued direct instruction is empty');
        }
        $arguments[] = base64_encode($instruction);
    }

    $scriptPath = rtrim($enginePath, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $script);
    if (!is_file($scriptPath)) {
        throw new RuntimeException('Background Life request processor is unavailable');
    }

    $command = array_merge([PHP_BINARY, $scriptPath], $arguments);
    $pipes = [];
    $process = proc_open(
        $command,
        [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['redirect', 1],
        ],
        $pipes,
        rtrim($enginePath, '/\\'),
        null,
        ['bypass_shell' => true]
    );
    if (!is_resource($process)) {
        throw new RuntimeException('Could not start Background Life request processor');
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]) ?: '';
    fclose($pipes[1]);
    $exitCode = proc_close($process);
    $output = trim($stdout);

    return [
        'exit_code' => $exitCode,
        'stdout' => $exitCode === 0 ? $output : '',
        'stderr' => $exitCode === 0 ? '' : $output,
    ];
}

function chimBglProcessRequestQueue($db, string $enginePath, int $limit = 2): array
{
    $result = [
        'locked' => false,
        'processed' => 0,
        'failed' => 0,
    ];
    $lockRows = $db->fetchAll(
        "SELECT pg_try_advisory_lock(hashtext('herika_background_life_request_worker')) AS acquired"
    );
    if (empty($lockRows) || !chimBglBoolean($lockRows[0]['acquired'] ?? false)) {
        return $result;
    }

    $result['locked'] = true;
    $npcMaster = new NpcMaster();

    try {
        $limit = max(1, min(5, $limit));
        $rows = $db->fetchAll(
            "SELECT id, value
             FROM conf_opts
             WHERE id LIKE 'background_life_request_queue_%'
             ORDER BY id
             LIMIT {$limit}"
        );

        foreach ($rows as $row) {
            $queueId = (string)($row['id'] ?? '');
            $payload = json_decode((string)($row['value'] ?? ''), true);
            if ($queueId === '' || !is_array($payload)) {
                if ($queueId !== '') {
                    $db->delete('conf_opts', "id = '" . $db->escape($queueId) . "'");
                }
                $result['failed']++;
                continue;
            }

            try {
                $npc = chimBglResolveNpc(
                    $npcMaster,
                    (string)($payload['refid'] ?? ''),
                    (string)($payload['npc_name'] ?? '')
                );
                if (!$npc) {
                    throw new RuntimeException('NPC no longer exists');
                }

                $status = chimBglNpcStatus($npcMaster, $npc);
                if (!$status['background_life_enabled']) {
                    throw new RuntimeException('Background Life is disabled for this NPC');
                }

                $requestResult = chimBglRunQueuedRequest(
                    $enginePath,
                    $npc,
                    (string)($payload['request_type'] ?? ''),
                    (string)($payload['instruction'] ?? '')
                );
                if ((int)$requestResult['exit_code'] !== 0) {
                    throw new RuntimeException(
                        $requestResult['stderr'] !== ''
                            ? $requestResult['stderr']
                            : 'Background Life request processor failed'
                    );
                }

                $db->delete('conf_opts', "id = '" . $db->escape($queueId) . "'");
                $result['processed']++;
                Logger::info(
                    "[BGL REQUEST] Processed {$payload['request_type']} request for {$npc['npc_name']}"
                );
            } catch (Throwable $error) {
                $payload['attempts'] = (int)($payload['attempts'] ?? 0) + 1;
                $payload['last_error'] = substr($error->getMessage(), 0, 500);
                if ($payload['attempts'] >= 3) {
                    $db->delete('conf_opts', "id = '" . $db->escape($queueId) . "'");
                } else {
                    $db->upsertRowOnConflict('conf_opts', [
                        'id' => $queueId,
                        'value' => json_encode(
                            $payload,
                            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                        ),
                    ], 'id');
                }
                $result['failed']++;
                Logger::error("[BGL REQUEST] {$queueId} failed: " . $error->getMessage());
            }
        }
    } finally {
        $db->fetchAll(
            "SELECT pg_advisory_unlock(hashtext('herika_background_life_request_worker'))"
        );
    }

    return $result;
}
