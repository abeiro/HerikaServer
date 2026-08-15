<?php

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$enginePath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR;

require_once($enginePath . 'lib' . DIRECTORY_SEPARATOR . 'runtime_bootstrap.php');
chimRuntimeBootstrap($enginePath, [
    'load_general_settings' => false,
    'load_player_name' => false,
    'load_narrator' => false,
]);

require_once($enginePath . 'lib' . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'core_profiles.class.php');
require_once($enginePath . 'lib' . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'npc_master.class.php');
require_once($enginePath . 'lib' . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'narrator.class.php');
require_once($enginePath . 'lib' . DIRECTORY_SEPARATOR . 'profile_llm_mode.php');

class ChimProfileLlmModeNotFoundException extends RuntimeException
{
}

function chimProfileLlmModeRespond(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function chimProfileLlmModeResolveTarget(string $targetName, string $targetType): array
{
    $profileManager = new CoreProfile();
    $isNarrator = $targetType === 'narrator';
    $targetLabel = $targetName;

    if ($isNarrator) {
        $narrator = new Narrator();
        $profileId = intval($narrator->getProfileId() ?? 0);
        $targetLabel = $narrator->getRoleplayName();
    } else {
        if ($targetName === '') {
            throw new InvalidArgumentException('A target NPC is required.');
        }

        $npcManager = new NpcMaster();
        $npc = $npcManager->getByName($targetName);
        if (!$npc) {
            throw new ChimProfileLlmModeNotFoundException('Target NPC was not found in CHIM.');
        }
        $profileId = intval($npc['profile_id'] ?? 0);
        $targetLabel = (string)($npc['npc_name'] ?? $targetName);
    }

    if ($profileId <= 0) {
        throw new ChimProfileLlmModeNotFoundException('The target does not have an assigned profile.');
    }

    $profile = $profileManager->getById($profileId);
    if (!$profile) {
        throw new ChimProfileLlmModeNotFoundException('The assigned profile could not be found.');
    }

    $npcCountRow = $GLOBALS['db']->fetchOne(
        'SELECT COUNT(*) AS c FROM core_npc_master WHERE profile_id=' . $profileId
    );
    $sharedCount = intval($npcCountRow['c'] ?? 0);

    $narrator = new Narrator();
    if (intval($narrator->getProfileId() ?? 0) === $profileId) {
        $sharedCount++;
    }

    return [
        'target_name' => $targetLabel,
        'target_type' => $isNarrator ? 'narrator' : 'npc',
        'profile' => $profile,
        'shared_count' => $sharedCount,
    ];
}

function chimProfileLlmModePayload(array $resolved): array
{
    $profile = $resolved['profile'];
    $configuredSlots = ProfileLLMMode::getConfiguredSlots($profile);
    $configuredConnectors = ProfileLLMMode::getConfiguredConnectors($profile);
    $randomEnabled = ProfileLLMMode::isRandomEnabled($profile);

    $connectorIds = array_map(
        static fn(array $connector): int => intval($connector['connector_id']),
        $configuredConnectors
    );
    $connectorLabels = [];
    if ($connectorIds !== []) {
        $rows = $GLOBALS['db']->fetchAll(
            'SELECT id, label, model FROM core_llm_connector WHERE id IN (' .
            implode(',', $connectorIds) . ')'
        );
        foreach ($rows ?: [] as $row) {
            $connectorLabels[intval($row['id'])] = trim((string)($row['label'] ?? '')) !== ''
                ? (string)$row['label']
                : (string)($row['model'] ?? ('Connector ' . intval($row['id'])));
        }
    }

    foreach ($configuredConnectors as &$connector) {
        $connector['connector_name'] = $connectorLabels[$connector['connector_id']]
            ?? ('Connector ' . $connector['connector_id']);
    }
    unset($connector);

    $availableProfiles = [];
    $profileRows = $GLOBALS['db']->fetchAll(
        'SELECT id, label, slot, metadata, llm_primary_id, llm_secondary_id, ' .
        'llm_tertiary_id, llm_quaternary_id FROM core_profiles ' .
        'WHERE slot BETWEEN 1 AND 4 ORDER BY slot ASC'
    );
    foreach ($profileRows ?: [] as $profileRow) {
        $availableProfiles[] = [
            'slot' => intval($profileRow['slot']),
            'profile_id' => intval($profileRow['id']),
            'profile_name' => (string)($profileRow['label'] ?? ('Profile ' . intval($profileRow['id']))),
            'random_enabled' => ProfileLLMMode::isRandomEnabled($profileRow),
            'configured_slot_count' => count(ProfileLLMMode::getConfiguredSlots($profileRow)),
        ];
    }

    return [
        'target_name' => $resolved['target_name'],
        'target_type' => $resolved['target_type'],
        'profile_id' => intval($profile['id']),
        'profile_name' => (string)($profile['label'] ?? ('Profile ' . intval($profile['id']))),
        'profile_slot' => intval($profile['slot'] ?? 0),
        'shared_count' => intval($resolved['shared_count']),
        'selection_mode' => $randomEnabled ? 'random' : 'fixed',
        'random_enabled' => $randomEnabled,
        'profile_defaults' => ProfileLLMMode::getProfileDefaults($profile),
        'configured_slots' => $configuredSlots,
        'configured_slot_count' => count($configuredSlots),
        'configured_connectors' => $configuredConnectors,
        'available_profiles' => $availableProfiles,
    ];
}

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
if (!in_array($method, ['GET', 'POST'], true)) {
    chimProfileLlmModeRespond(405, ['ok' => false, 'message' => 'GET or POST required.']);
}

$input = $method === 'POST' ? $_POST : $_GET;
$targetName = trim((string)($input['target_name'] ?? ''));
$targetType = strtolower(trim((string)($input['target_type'] ?? 'npc')));
if (!in_array($targetType, ['npc', 'narrator'], true)) {
    chimProfileLlmModeRespond(400, ['ok' => false, 'message' => 'Unsupported target type.']);
}

try {
    $resolved = chimProfileLlmModeResolveTarget($targetName, $targetType);

    if ($method === 'POST') {
        $expectedProfileId = intval($input['expected_profile_id'] ?? 0);
        $profileId = intval($resolved['profile']['id']);
        if ($expectedProfileId > 0 && $expectedProfileId !== $profileId) {
            chimProfileLlmModeRespond(409, [
                'ok' => false,
                'message' => 'The target profile changed. Refresh and try again.',
            ]);
        }

        $metadata = $resolved['profile']['metadata'] ?? null;
        $setting = strtolower(trim((string)($input['setting'] ?? '')));
        if ($setting !== '') {
            $enabledInput = trim((string)($input['enabled'] ?? ''));
            if (!in_array($enabledInput, ['0', '1'], true)) {
                chimProfileLlmModeRespond(400, ['ok' => false, 'message' => 'Enabled must be 0 or 1.']);
            }

            $enabled = $enabledInput === '1';
            $metadata = ProfileLLMMode::updateProfileDefaultMetadata($metadata, $setting, $enabled);
            $logDescription = 'profile default ' . $setting . ' changed to ' . ($enabled ? 'enabled' : 'disabled');
        } else {
            $mode = strtolower(trim((string)($input['mode'] ?? '')));
            if (!in_array($mode, ['fixed', 'random'], true)) {
                chimProfileLlmModeRespond(400, ['ok' => false, 'message' => 'Mode must be fixed or random.']);
            }

            $enabled = $mode === 'random';
            $configuredSlots = ProfileLLMMode::getConfiguredSlots($resolved['profile']);
            if ($enabled && count($configuredSlots) === 0) {
                chimProfileLlmModeRespond(409, [
                    'ok' => false,
                    'message' => 'This profile has no configured LLM connectors.',
                ]);
            }

            $metadata = ProfileLLMMode::updateRandomEnabledMetadata($metadata, $enabled);
            $logDescription = 'LLM selection mode changed to ' . ($enabled ? 'random' : 'fixed');
        }

        $profileManager = new CoreProfile();
        if ($profileManager->update($profileId, ['metadata' => $metadata]) === false) {
            throw new RuntimeException($profileManager->getLastError() ?: 'Profile update failed.');
        }

        $resolved['profile']['metadata'] = $metadata;
        Logger::info(
            '[CHATBOX] Profile #' . $profileId . ' ' . $logDescription
        );
    }

    chimProfileLlmModeRespond(200, [
        'ok' => true,
        'profile' => chimProfileLlmModePayload($resolved),
    ]);
} catch (InvalidArgumentException $e) {
    chimProfileLlmModeRespond(400, ['ok' => false, 'message' => $e->getMessage()]);
} catch (ChimProfileLlmModeNotFoundException $e) {
    chimProfileLlmModeRespond(404, ['ok' => false, 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    Logger::error('[CHATBOX] Failed to update profile LLM mode: ' . $e->getMessage());
    chimProfileLlmModeRespond(500, ['ok' => false, 'message' => 'Failed to update profile LLM mode.']);
}
