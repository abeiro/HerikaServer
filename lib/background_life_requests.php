<?php

// Normalize Skyrim reference IDs to the eight-digit form used by NPC storage.
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

// Interpret the boolean forms returned by PostgreSQL, JSON, and HTTP requests.
function chimBglBoolean($value): bool
{
    return $value === true ||
        $value === 1 ||
        $value === '1' ||
        $value === 't' ||
        $value === 'true' ||
        $value === 'on';
}

// Prefer the stable reference ID, with the NPC name kept as a legacy fallback.
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

// Build the control-panel status shape from an NPC and its stored metadata.
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
            'location' => '',
        ];
    }

    $extendedData = $npcMaster->getExtendedData($npc);
    $metadata = $npcMaster->getMetadata($npc);
    $coordsValue = $metadata['last_coords'] ?? null;
    $coords = is_array($coordsValue) ? $coordsValue : json_decode((string)$coordsValue, true);
    $location = is_array($coords) ? trim((string)($coords[3] ?? '')) : '';

    return [
        'exists' => true,
        'npc_id' => (int)($npc['id'] ?? 0),
        'name' => trim((string)($npc['npc_name'] ?? $requestedName)),
        'refid' => chimBglNormalizeRefId((string)($npc['refid'] ?? $requestedRefId)),
        'background_life_enabled' => chimBglBoolean($extendedData['background_life_enabled'] ?? false),
        'auto_actions' => chimBglBoolean($extendedData['background_life_commands'] ?? false),
        'send_letters' => chimBglBoolean($extendedData['background_life_letters'] ?? false),
        'hourly_tracking' => chimBglBoolean($metadata['gps_track'] ?? false),
        'location' => $location,
    ];
}

// Update one supported control while preserving unrelated NPC data.
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

// Toggle Background Life enrollment without replacing other extended metadata.
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

// Resolve the CLI interpreter when PHP is running under Apache rather than CLI.
function chimBglPhpCliBinary(): string
{
    $binaryName = DIRECTORY_SEPARATOR === '\\' ? 'php.exe' : 'php';
    $candidates = [];

    $phpBindir = rtrim((string)(PHP_BINDIR ?? ''), '/\\');
    if ($phpBindir !== '') {
        $candidates[] = $phpBindir . DIRECTORY_SEPARATOR . $binaryName;
    }

    $phpBinary = trim((string)(PHP_BINARY ?? ''));
    if ($phpBinary !== '') {
        $phpBinaryName = basename(str_replace('\\', '/', $phpBinary));
        if (preg_match('/^php(?:[0-9.]+)?(?:\.exe)?$/i', $phpBinaryName) === 1) {
            $candidates[] = $phpBinary;
        }
    }

    if (DIRECTORY_SEPARATOR !== '\\') {
        $candidates[] = '/usr/bin/php';
        $candidates[] = '/usr/local/bin/php';
    }

    foreach (array_unique($candidates) as $candidate) {
        if (is_file($candidate) && is_executable($candidate)) {
            return $candidate;
        }
    }

    return $binaryName;
}

// Invoke the same one-shot action and letter runners used by the existing map UI.
function chimBglRunRequest(string $enginePath, array $npc, string $requestType): array
{
    if (!in_array($requestType, ['action', 'letter'], true)) {
        throw new InvalidArgumentException('Unsupported Background Life request');
    }

    $npcName = trim((string)($npc['npc_name'] ?? ''));
    if ($npcName === '') {
        throw new RuntimeException('Background Life request has no NPC name');
    }

    $extendedData = json_decode((string)($npc['extended_data'] ?? '{}'), true);
    if (!is_array($extendedData)) {
        $extendedData = [];
    }

    if ($requestType === 'letter') {
        $script = 'debug/simple_llm_request_with_context_life.php';
        $arguments = [$npcName, 'forceletter'];
    } elseif (chimBglBoolean($extendedData['background_life_commands'] ?? false)) {
        $script = 'debug/simple_llm_request_with_context_life_v2.php';
        $arguments = [$npcName, 'full', 'forceaction'];
    } else {
        $script = 'debug/simple_llm_request_with_context_life.php';
        $arguments = [$npcName, 'full', 'forceaction'];
    }

    $scriptPath = rtrim($enginePath, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $script);
    if (!is_file($scriptPath)) {
        throw new RuntimeException('Background Life request processor is unavailable');
    }

    $command = array_merge([chimBglPhpCliBinary(), $scriptPath], $arguments);
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

// Run the coordinate trackers used by the Background Life map controls.
function chimBglRunTrackingRequest(string $enginePath, string $npcName = ''): array
{
    $scriptPath = rtrim($enginePath, '/\\') . DIRECTORY_SEPARATOR . 'debug' . DIRECTORY_SEPARATOR . 'simple_llm_request_with_context_life_command.php';
    if (!is_file($scriptPath)) {
        throw new RuntimeException('Background Life coordinate processor is unavailable');
    }

    $arguments = trim($npcName) === '' ? ['The Narrator', 'TrackAll'] : [trim($npcName), 'Track'];
    $command = array_merge([chimBglPhpCliBinary(), $scriptPath], $arguments);
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
        throw new RuntimeException('Could not start Background Life coordinate processor');
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
