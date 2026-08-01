<?php

error_reporting(E_ERROR);
session_start();

define('BASE_PATH', dirname(dirname(__DIR__)));
define('CONFIG_PATH', BASE_PATH . DIRECTORY_SEPARATOR . 'conf');
define('LIB_PATH', BASE_PATH . DIRECTORY_SEPARATOR . 'lib');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if (!file_exists(CONFIG_PATH . DIRECTORY_SEPARATOR . 'conf.php')) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Configuration file not found']);
    exit;
}

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'profile_loader.php';
require_once LIB_PATH . DIRECTORY_SEPARATOR . 'logger.php';
require_once LIB_PATH . DIRECTORY_SEPARATOR . "{$GLOBALS['DBDRIVER']}.class.php";
require_once LIB_PATH . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'npc_master.class.php';
require_once LIB_PATH . DIRECTORY_SEPARATOR . 'relationship_manager.php';

$db = new sql();
$npcMaster = new NpcMaster();

function chimNpcManagerRespond(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function chimNpcManagerDecodeJson($value): array
{
    if (is_array($value)) {
        return $value;
    }
    $decoded = json_decode((string)$value, true);
    return is_array($decoded) ? $decoded : [];
}

function chimNpcManagerBool($value): bool
{
    if (is_bool($value)) {
        return $value;
    }
    if (is_int($value) || is_float($value)) {
        return (int)$value !== 0;
    }
    return in_array(strtolower(trim((string)$value)), ['1', 'true', 'yes', 'on'], true);
}

function chimNpcManagerProfiles(): array
{
    $rows = $GLOBALS['db']->fetchAll('SELECT id, label, metadata FROM core_profiles ORDER BY label ASC, id ASC');
    $profiles = [];
    foreach ((array)$rows as $row) {
        $profiles[] = [
            'id' => (int)($row['id'] ?? 0),
            'label' => trim((string)($row['label'] ?? 'Unnamed Profile')),
            'metadata' => chimNpcManagerDecodeJson($row['metadata'] ?? '{}'),
        ];
    }
    return $profiles;
}

function chimNpcManagerProfileMap(array $profiles): array
{
    $map = [];
    foreach ($profiles as $profile) {
        $map[(string)$profile['id']] = $profile;
    }
    return $map;
}

function chimNpcManagerToggleState($override, array $profileMetadata, string $profileKey, bool $default = false): array
{
    if ($override !== null && $override !== '') {
        return [
            'value' => chimNpcManagerBool($override),
            'source' => 'npc',
            'profile_default' => array_key_exists($profileKey, $profileMetadata)
                ? chimNpcManagerBool($profileMetadata[$profileKey])
                : $default,
        ];
    }

    $profileDefault = array_key_exists($profileKey, $profileMetadata)
        ? chimNpcManagerBool($profileMetadata[$profileKey])
        : $default;
    return [
        'value' => $profileDefault,
        'source' => array_key_exists($profileKey, $profileMetadata) ? 'profile' : 'default',
        'profile_default' => $profileDefault,
    ];
}

function chimNpcManagerWebRoot(): string
{
    $scriptPath = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
    $marker = '/HerikaServer/';
    $position = stripos($scriptPath, $marker);
    if ($position !== false) {
        return substr($scriptPath, 0, $position + strlen('/HerikaServer'));
    }
    return '/HerikaServer';
}

function chimNpcManagerPortraitUrl(array $row, array $metadata): string
{
    $webRoot = chimNpcManagerWebRoot();
    $portrait = ltrim(str_replace('\\', '/', trim((string)($metadata['portrait'] ?? ''))), '/');
    if ($portrait !== '') {
        $picturesRoot = realpath(BASE_PATH . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'pictures');
        $portraitPath = realpath(BASE_PATH . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'pictures'
            . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $portrait));
        if ($picturesRoot !== false && $portraitPath !== false
            && strncmp($portraitPath, $picturesRoot, strlen($picturesRoot)) === 0
            && is_file($portraitPath)) {
            return $webRoot . '/data/pictures/' . str_replace('%2F', '/', rawurlencode($portrait));
        }
    }

    $profileDirectory = BASE_PATH . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'pictures'
        . DIRECTORY_SEPARATOR . 'profile' . DIRECTORY_SEPARATOR;
    $candidates = array_filter([
        trim((string)($row['md5'] ?? '')),
        strtoupper(trim((string)($row['refid'] ?? ''))),
        preg_replace('/[^a-z0-9_-]+/i', '', strtolower((string)($row['npc_name'] ?? ''))),
    ]);
    foreach (array_unique($candidates) as $candidate) {
        foreach (['png', 'jpg', 'jpeg', 'webp', 'gif'] as $extension) {
            if (is_file($profileDirectory . $candidate . '.' . $extension)) {
                return $webRoot . '/data/pictures/profile/' . rawurlencode($candidate . '.' . $extension);
            }
        }
    }

    $race = strtolower(trim((string)($row['race'] ?? '')));
    $race = preg_replace('/[^a-z0-9]+/', '', $race);
    $aliases = [
        'highelf' => 'altmer', 'woodelf' => 'bosmer', 'darkelf' => 'dunmer',
        'orsimer' => 'orc', 'khajit' => 'khajiit', 'oldpeople' => 'nord',
        'oldpeoplerace' => 'nord',
    ];
    $race = $aliases[$race] ?? $race;
    foreach (['png', 'jpg', 'jpeg', 'webp', 'gif', 'svg'] as $extension) {
        $racePath = BASE_PATH . DIRECTORY_SEPARATOR . 'ui' . DIRECTORY_SEPARATOR . 'images'
            . DIRECTORY_SEPARATOR . 'races' . DIRECTORY_SEPARATOR . $race . '.' . $extension;
        if ($race !== '' && is_file($racePath)) {
            return $webRoot . '/ui/images/races/' . rawurlencode($race . '.' . $extension);
        }
    }
    return $webRoot . '/ui/images/races/default.png';
}

function chimNpcManagerCard(array $row, array $profileMap): array
{
    $metadata = chimNpcManagerDecodeJson($row['metadata'] ?? '{}');
    $profile = $profileMap[(string)($row['profile_id'] ?? '')] ?? null;
    return [
        'id' => (int)($row['id'] ?? 0),
        'name' => trim((string)($row['npc_name'] ?? 'Unknown NPC')),
        'gender' => trim((string)($row['gender'] ?? '')),
        'race' => trim((string)($row['race'] ?? '')),
        'refid' => trim((string)($row['refid'] ?? '')),
        'profile_id' => isset($row['profile_id']) ? (int)$row['profile_id'] : null,
        'profile_label' => $profile['label'] ?? 'No Profile',
        'favorite' => chimNpcManagerBool($row['npc_favorite'] ?? false),
        'locked' => chimNpcManagerBool($row['lock_profile'] ?? false),
        'portrait_url' => chimNpcManagerPortraitUrl($row, $metadata),
    ];
}

function chimNpcManagerLatestMemory(array $extended): string
{
    $memory = $extended['middle_term_memory'] ?? [];
    if (!is_array($memory) || empty($memory)) {
        return '';
    }
    $latestKey = array_key_last($memory);
    return $latestKey === null ? '' : trim((string)$memory[$latestKey]);
}

function chimNpcManagerDetail(array $row, array $profiles): array
{
    $metadata = chimNpcManagerDecodeJson($row['metadata'] ?? '{}');
    $extended = chimNpcManagerDecodeJson($row['extended_data'] ?? '{}');
    $profileMap = chimNpcManagerProfileMap($profiles);
    $profile = $profileMap[(string)($row['profile_id'] ?? '')] ?? null;
    $profileMetadata = $profile['metadata'] ?? [];

    return [
        'card' => chimNpcManagerCard($row, $profileMap),
        'fields' => [
            'npc_name' => (string)($row['npc_name'] ?? ''),
            'profile_id' => isset($row['profile_id']) ? (int)$row['profile_id'] : null,
            'lock_profile' => chimNpcManagerBool($row['lock_profile'] ?? false),
            'npc_favorite' => chimNpcManagerBool($row['npc_favorite'] ?? false),
            'gender' => (string)($row['gender'] ?? ''),
            'race' => (string)($row['race'] ?? ''),
            'base' => (string)($row['base'] ?? ''),
            'refid' => (string)($row['refid'] ?? ''),
            'voiceid' => (string)($row['voiceid'] ?? ''),
            'oghma_knowledge_tags' => (string)($row['oghma_knowledge_tags'] ?? ''),
            'tags' => (string)($row['tags'] ?? ''),
            'prompt_head' => (string)($row['prompt_head'] ?? ''),
            'core' => (string)($row['core'] ?? ''),
            'npc_static_bio' => (string)($row['npc_static_bio'] ?? ''),
            'appearance' => (string)($row['appearance'] ?? ''),
            'personality' => (string)($row['personality'] ?? ''),
            'occupation' => (string)($row['occupation'] ?? ''),
            'skills' => (string)($row['skills'] ?? ''),
            'speechstyle' => (string)($row['speechstyle'] ?? ''),
            'goals' => (string)($row['goals'] ?? ''),
            'emote_moods' => (string)($row['emote_moods'] ?? ''),
            'middle_term_latest' => chimNpcManagerLatestMemory($extended),
        ],
        'toggles' => [
            'dynamic_profile' => chimNpcManagerToggleState($row['dynamic_profile'] ?? null, $profileMetadata, 'DYNAMIC_PROFILE_ENABLED'),
            'middle_term_enabled' => chimNpcManagerToggleState($extended['middle_term_enabled'] ?? null, $profileMetadata, 'MIDDLE_TERM_MEMORY_ENABLED'),
            'individual_memory_enabled' => chimNpcManagerToggleState($extended['individual_memory_enabled'] ?? null, $profileMetadata, 'INDIVIDUAL_MEMORY_ENABLED'),
            'auto_diary_enabled' => chimNpcManagerToggleState($extended['auto_diary_enabled'] ?? null, $profileMetadata, 'AUTO_DIARY_ENABLED'),
            'auto_diary_wait_enabled' => chimNpcManagerToggleState($extended['auto_diary_wait_enabled'] ?? null, $profileMetadata, 'AUTO_DIARY_WAIT_ENABLED'),
            'salutation_after_a_while' => chimNpcManagerToggleState($extended['salutation_after_a_while'] ?? null, $profileMetadata, 'SALUTATION_AFTER_A_WHILE'),
        ],
        'relationships' => RelationshipManager::normalizeRelationshipMap($extended['relationships'] ?? []),
        'relationships_locked' => chimNpcManagerBool($extended['relationships_locked'] ?? false),
        'metadata' => $metadata,
        'profiles' => array_map(static function ($profile) {
            return ['id' => $profile['id'], 'label' => $profile['label']];
        }, $profiles),
    ];
}

function chimNpcManagerFindNpc(array $input): array
{
    $id = (int)($input['id'] ?? 0);
    if ($id > 0) {
        $row = $GLOBALS['db']->fetchOne("SELECT * FROM core_npc_master WHERE id = {$id} LIMIT 1");
        if ($row) {
            return $row;
        }
    }

    $refid = trim((string)($input['refid'] ?? ''));
    if ($refid !== '') {
        $escaped = $GLOBALS['db']->escape(strtolower($refid));
        $row = $GLOBALS['db']->fetchOne("SELECT * FROM core_npc_master WHERE lower(refid) = '{$escaped}' ORDER BY gamets_last_updated DESC NULLS LAST LIMIT 1");
        if ($row) {
            return $row;
        }
    }

    $name = trim((string)($input['name'] ?? $input['npc_name'] ?? ''));
    if ($name !== '') {
        $escaped = $GLOBALS['db']->escape($name);
        $row = $GLOBALS['db']->fetchOne("SELECT * FROM core_npc_master WHERE npc_name = '{$escaped}' ORDER BY gamets_last_updated DESC NULLS LAST LIMIT 1");
        if ($row) {
            return $row;
        }
    }

    throw new InvalidArgumentException('NPC not found');
}

function chimNpcManagerList(array $profiles): array
{
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = max(1, min(100, (int)($_GET['limit'] ?? 40)));
    $offset = ($page - 1) * $limit;
    $conditions = ["npc_name IS NOT NULL", "btrim(npc_name) <> ''"];

    $search = trim((string)($_GET['search'] ?? ''));
    if ($search !== '') {
        $escaped = $GLOBALS['db']->escape('%' . $search . '%');
        $conditions[] = "(npc_name ILIKE '{$escaped}' OR race ILIKE '{$escaped}' OR refid ILIKE '{$escaped}')";
    }

    $profileId = (int)($_GET['profile_id'] ?? 0);
    if ($profileId > 0) {
        $conditions[] = "profile_id = {$profileId}";
    }

    $refids = array_values(array_filter(array_map('trim', explode(',', (string)($_GET['refids'] ?? '')))));
    $names = array_values(array_filter(array_map('trim', explode('|', (string)($_GET['names'] ?? '')))));
    if (!empty($refids) || !empty($names)) {
        $nearbyConditions = [];
        if (!empty($refids)) {
            $escapedRefids = array_map(static function ($value) {
                return "'" . $GLOBALS['db']->escape(strtolower($value)) . "'";
            }, $refids);
            $nearbyConditions[] = 'lower(refid) IN (' . implode(',', $escapedRefids) . ')';
        }
        if (!empty($names)) {
            $escapedNames = array_map(static function ($value) {
                return "'" . $GLOBALS['db']->escape($value) . "'";
            }, $names);
            $nearbyConditions[] = 'npc_name IN (' . implode(',', $escapedNames) . ')';
        }
        $conditions[] = '(' . implode(' OR ', $nearbyConditions) . ')';
    }

    $where = implode(' AND ', $conditions);
    $countRow = $GLOBALS['db']->fetchOne("SELECT COUNT(*) AS total FROM core_npc_master WHERE {$where}");
    $total = (int)($countRow['total'] ?? 0);
    $rows = $GLOBALS['db']->fetchAll(
        "SELECT * FROM core_npc_master WHERE {$where} ORDER BY npc_favorite DESC NULLS LAST, npc_name ASC, id ASC LIMIT {$limit} OFFSET {$offset}"
    );
    $profileMap = chimNpcManagerProfileMap($profiles);

    return [
        'npcs' => array_map(static function ($row) use ($profileMap) {
            return chimNpcManagerCard($row, $profileMap);
        }, (array)$rows),
        'profiles' => array_map(static function ($profile) {
            return ['id' => $profile['id'], 'label' => $profile['label']];
        }, $profiles),
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'pages' => max(1, (int)ceil($total / $limit)),
        ],
    ];
}

function chimNpcManagerSave(array $input, array $profiles): array
{
    $row = chimNpcManagerFindNpc($input);
    $id = (int)$row['id'];
    $fields = is_array($input['fields'] ?? null) ? $input['fields'] : [];
    $overrides = is_array($input['overrides'] ?? null) ? $input['overrides'] : [];
    $update = [];

    $allowedFields = [
        'npc_name', 'profile_id', 'lock_profile', 'npc_favorite', 'gender', 'race', 'base',
        'refid', 'voiceid', 'oghma_knowledge_tags', 'tags', 'prompt_head', 'core',
        'npc_static_bio', 'appearance', 'personality', 'occupation', 'skills', 'speechstyle',
        'goals', 'emote_moods',
    ];
    foreach ($allowedFields as $field) {
        if (!array_key_exists($field, $fields)) {
            continue;
        }
        if (in_array($field, ['lock_profile', 'npc_favorite'], true)) {
            $update[$field] = chimNpcManagerBool($fields[$field]) ? 1 : 0;
        } elseif ($field === 'profile_id') {
            $profileId = (int)$fields[$field];
            $profileExists = false;
            foreach ($profiles as $profile) {
                if ((int)$profile['id'] === $profileId) {
                    $profileExists = true;
                    break;
                }
            }
            if (!$profileExists) {
                throw new InvalidArgumentException('Selected profile does not exist');
            }
            $update[$field] = $profileId;
        } else {
            $update[$field] = trim((string)$fields[$field]);
        }
    }

    if (array_key_exists('npc_name', $update)) {
        if ($update['npc_name'] === '') {
            throw new InvalidArgumentException('NPC name is required');
        }
        $update['md5'] = md5($update['npc_name']);
    }

    $extended = chimNpcManagerDecodeJson($row['extended_data'] ?? '{}');
    $relationshipChanged = false;
    $toggleMap = [
        'middle_term_enabled' => 'middle_term_enabled',
        'individual_memory_enabled' => 'individual_memory_enabled',
        'auto_diary_enabled' => 'auto_diary_enabled',
        'auto_diary_wait_enabled' => 'auto_diary_wait_enabled',
        'salutation_after_a_while' => 'salutation_after_a_while',
    ];
    if (array_key_exists('dynamic_profile', $overrides)) {
        $update['dynamic_profile'] = $overrides['dynamic_profile'] === null
            ? null
            : (chimNpcManagerBool($overrides['dynamic_profile']) ? 1 : 0);
    }
    foreach ($toggleMap as $requestKey => $extendedKey) {
        if (!array_key_exists($requestKey, $overrides)) {
            continue;
        }
        if ($overrides[$requestKey] === null) {
            unset($extended[$extendedKey]);
        } else {
            $extended[$extendedKey] = chimNpcManagerBool($overrides[$requestKey]) ? 1 : 0;
        }
    }

    if (array_key_exists('middle_term_latest', $fields)) {
        $latest = trim((string)$fields['middle_term_latest']);
        $memories = is_array($extended['middle_term_memory'] ?? null) ? $extended['middle_term_memory'] : [];
        $latestKey = empty($memories) ? null : array_key_last($memories);
        if ($latestKey !== null) {
            if ($latest === '') {
                unset($memories[$latestKey]);
            } else {
                $memories[$latestKey] = $latest;
            }
        } elseif ($latest !== '') {
            $memories['0'] = $latest;
        }
        if (empty($memories)) {
            unset($extended['middle_term_memory']);
        } else {
            $extended['middle_term_memory'] = $memories;
        }
    }

    if (array_key_exists('relationships', $input)) {
        $relationships = is_array($input['relationships']) ? $input['relationships'] : [];
        foreach ($relationships as $target => &$relationship) {
            if (!is_array($relationship)) {
                $relationship = [];
            }
            $relationship['aff'] = max(-100, min(100, (int)($relationship['aff'] ?? 0)));
            $relationship['type'] = strtolower(trim((string)($relationship['type'] ?? 'neutral')));
            foreach (['relation', 'note', 'best', 'worst'] as $key) {
                if (array_key_exists($key, $relationship)) {
                    $relationship[$key] = trim((string)$relationship[$key]);
                }
            }
        }
        unset($relationship);
        $extended['relationships'] = RelationshipManager::normalizeRelationshipMap($relationships);
        $relationshipChanged = true;
    }
    if (array_key_exists('relationships_locked', $input)) {
        $extended['relationships_locked'] = chimNpcManagerBool($input['relationships_locked']);
        $relationshipChanged = true;
    }

    if (!array_key_exists('AUTO_LOCK_PROFILE', $GLOBALS) || chimNpcManagerBool($GLOBALS['AUTO_LOCK_PROFILE'])) {
        $update['lock_profile'] = 1;
    }

    $update['extended_data'] = json_encode($extended, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $lockId = $relationshipChanged ? 1001000000 + $id : null;
    try {
        if ($lockId !== null) {
            $GLOBALS['db']->execQuery("SELECT pg_advisory_lock({$lockId})");
        }
        $save = static function () use ($id, $update) {
            $manager = new NpcMaster();
            return $manager->update($id, $update);
        };
        $saved = $relationshipChanged ? chimRunWithRelationshipExtendedDataWrite($save) : $save();
        if ($saved === false) {
            throw new RuntimeException('NPC update failed');
        }
        if ($relationshipChanged && function_exists('chimRelationshipTimelineStamp')) {
            chimRelationshipTimelineStamp($id);
        }
        (new NpcMaster())->backupNpcById($id);
    } finally {
        if ($lockId !== null) {
            $GLOBALS['db']->execQuery("SELECT pg_advisory_unlock({$lockId})");
        }
    }

    $fresh = (new NpcMaster())->getById($id);
    if (!$fresh) {
        throw new RuntimeException('NPC could not be reloaded after saving');
    }
    return chimNpcManagerDetail($fresh, $profiles);
}

try {
    $profiles = chimNpcManagerProfiles();
    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if ($method === 'POST') {
        $input = json_decode((string)file_get_contents('php://input'), true);
        if (!is_array($input)) {
            throw new InvalidArgumentException('Invalid JSON request');
        }
        chimNpcManagerRespond(['success' => true, 'data' => chimNpcManagerSave($input, $profiles)]);
    }

    $operation = strtolower(trim((string)($_GET['operation'] ?? 'list')));
    if ($operation === 'list') {
        chimNpcManagerRespond(['success' => true, 'data' => chimNpcManagerList($profiles)]);
    }
    if ($operation === 'detail') {
        $row = chimNpcManagerFindNpc($_GET);
        chimNpcManagerRespond(['success' => true, 'data' => chimNpcManagerDetail($row, $profiles)]);
    }
    throw new InvalidArgumentException('Unsupported NPC manager operation');
} catch (InvalidArgumentException $error) {
    chimNpcManagerRespond(['success' => false, 'error' => $error->getMessage()], 400);
} catch (Throwable $error) {
    Logger::error('CHIM NPC manager API failed: ' . $error->getMessage());
    chimNpcManagerRespond(['success' => false, 'error' => 'Unable to process NPC manager request'], 500);
}
