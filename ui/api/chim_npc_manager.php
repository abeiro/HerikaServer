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
require_once LIB_PATH . DIRECTORY_SEPARATOR . 'utils_game_timestamp.php';
require_once LIB_PATH . DIRECTORY_SEPARATOR . 'eventlog_helper.php';

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
    $mods = is_array($metadata['mods'] ?? null)
        ? array_values(array_filter(array_map('trim', $metadata['mods'])))
        : [];
    $profile = $profileMap[(string)($row['profile_id'] ?? '')] ?? null;
    $duplicateCount = isset($row['duplicate_count']) ? (int)$row['duplicate_count'] : 0;
    if ($duplicateCount <= 0 && !empty($row['npc_name'])) {
        $escapedName = $GLOBALS['db']->escape((string)$row['npc_name']);
        $countRow = $GLOBALS['db']->fetchOne(
            "SELECT COUNT(*) AS total FROM core_npc_master WHERE lower(npc_name) = lower('{$escapedName}')"
        );
        $duplicateCount = (int)($countRow['total'] ?? 1);
    }
    return [
        'id' => (int)($row['id'] ?? 0),
        'name' => trim((string)($row['npc_name'] ?? 'Unknown NPC')),
        'gender' => trim((string)($row['gender'] ?? '')),
        'race' => trim((string)($row['race'] ?? '')),
        'refid' => trim((string)($row['refid'] ?? '')),
        'refid_source' => (string)($metadata['refid_source'] ?? ''),
        'profile_sharing' => [
            'linked' => !empty($row['profile_owner_npc_id']) || chimNpcManagerBool($row['_has_shared_profile'] ?? false),
            'automatic' => !empty($metadata['_chim_auto_link_group']),
            'auto_link_disabled' => !empty($metadata['_chim_auto_link_disabled']),
            'owner_id' => (int)($row['profile_owner_npc_id'] ?? $row['id']),
        ],
        'source_mod' => (string)($mods[0] ?? ''),
        'mods' => $mods,
        'duplicate_count' => max(1, $duplicateCount),
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
    $raw = (new NpcMaster())->getActorById((int)$row['id']);
    $sharing = chimNpcProfileSharing($raw);
    $revision = chimNpcProfileRevision(chimNpcProfileMembers($raw));
    $row = chimNpcEffectiveProfile($raw);
    $row['_has_shared_profile'] = $sharing['linked'];
    $metadata = chimNpcManagerDecodeJson($row['metadata'] ?? '{}');
    $extended = chimNpcManagerDecodeJson($row['extended_data'] ?? '{}');
    $profileMap = chimNpcManagerProfileMap($profiles);
    $profile = $profileMap[(string)($row['profile_id'] ?? '')] ?? null;
    $profileMetadata = $profile['metadata'] ?? [];

    return [
        'card' => chimNpcManagerCard($row, $profileMap),
        'profile_sharing' => $sharing,
        'profile_revision' => $revision,
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
        'readonly_fields' => NpcMaster::isActorBound($row) ? ['refid'] : [],
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
            return chimNpcEffectiveProfile($row);
        }
        throw new InvalidArgumentException('NPC profile no longer exists');
    }

    $refid = trim((string)($input['refid'] ?? ''));
    if ($refid !== '') {
        $escaped = $GLOBALS['db']->escape(strtolower($refid));
        $rows = $GLOBALS['db']->fetchAll("SELECT * FROM core_npc_master WHERE lower(refid) = '{$escaped}' ORDER BY gamets_last_updated DESC NULLS LAST, id ASC LIMIT 2");
        if (count((array)$rows) === 1) {
            return chimNpcEffectiveProfile($rows[0]);
        }
        if (count((array)$rows) > 1) {
            throw new InvalidArgumentException('RefID matches more than one profile; use the profile id');
        }
    }

    $name = trim((string)($input['name'] ?? $input['npc_name'] ?? ''));
    if ($name !== '') {
        $escaped = $GLOBALS['db']->escape($name);
        $rows = $GLOBALS['db']->fetchAll("SELECT * FROM core_npc_master WHERE npc_name = '{$escaped}' ORDER BY id ASC LIMIT 2");
        if (count((array)$rows) === 1) {
            return chimNpcEffectiveProfile($rows[0]);
        }
        if (count((array)$rows) > 1) {
            throw new InvalidArgumentException('NPC name matches more than one profile; use the profile id or RefID');
        }
    }

    throw new InvalidArgumentException('NPC not found');
}

// The event log stores recipients as a '|'-delimited list of visible names, so anything routed
// through it is name-scoped. Count the profiles sharing a name to detect when that is ambiguous.
function chimNpcManagerSharedNameCount(string $npcName): int
{
    $npcName = trim($npcName);
    if ($npcName === '') {
        return 0;
    }
    $escaped = $GLOBALS['db']->escape($npcName);
    $row = $GLOBALS['db']->fetchOne(
        "SELECT COUNT(*) AS total FROM core_npc_master WHERE lower(npc_name) = lower('{$escaped}')"
    );
    return max(1, (int)($row['total'] ?? 1));
}

function chimNpcManagerSharedNameNotice(string $npcName, int $count): string
{
    return $count . ' profiles share the name "' . $npcName . '", and the event log identifies NPCs'
        . ' by name only, so events cannot be routed to just one of them.';
}

// Name-scoped event writes must refuse rather than silently reach every same-named actor.
function chimNpcManagerGuardSharedNameEvents(string $npcName, string $operationLabel): void
{
    $count = chimNpcManagerSharedNameCount($npcName);
    if ($count > 1) {
        throw new InvalidArgumentException(
            $operationLabel . ' is unavailable for "' . $npcName . '": '
            . chimNpcManagerSharedNameNotice($npcName, $count)
        );
    }
}

function chimNpcManagerEventRecipients($people): array
{
    $recipients = [];
    foreach (explode('|', trim((string)$people, '|')) as $recipient) {
        $recipient = trim((string)$recipient);
        if ($recipient !== '' && !in_array($recipient, $recipients, true)) {
            $recipients[] = $recipient;
        }
    }
    return $recipients;
}

function chimNpcManagerHistory(array $input): array
{
    $npc = chimNpcManagerFindNpc($input);
    $npcName = trim((string)($npc['npc_name'] ?? ''));
    $limit = max(1, min(100, (int)($input['limit'] ?? 100)));
    $selectedEventType = trim((string)($input['event_type'] ?? ''));
    $hiddenEventTypes = chimGetPersistedEventLogHiddenTypes($GLOBALS['db']);
    // Keep NPC History aligned with the PHP Adventure Log's default narrative event list.
    $allowedEventTypes = [
        'im_alive',
        'chat',
        'infoaction',
        'rpg_word',
        'rpg_lvlup',
        'rechat',
        'quest',
        'itemfound',
        'inputtext',
        'goodnight',
        'goodmorning',
        'ginputtext',
        'death',
        'combatendmighty',
        'combatend',
    ];
    $escapedAllowedEventTypes = array_map(static function ($eventType) {
        return "'" . $GLOBALS['db']->escape($eventType) . "'";
    }, $allowedEventTypes);
    $allowedTypesWhere = 'a.type IN (' . implode(',', $escapedAllowedEventTypes) . ')';
    $peopleWhere = chimBuildNpcEventLogPeopleWhereClause($GLOBALS['db'], $npcName, 'a.people');
    $visibleWhere = chimBuildVisibleEventLogWhereClause(
        $GLOBALS['db'],
        $selectedEventType,
        $hiddenEventTypes
    );
    $rows = $GLOBALS['db']->fetchAll(
        "SELECT a.rowid, a.type, a.data, a.people, a.gamets, a.localts, a.ts, a.sess
         FROM eventlog a
         WHERE {$allowedTypesWhere} AND {$visibleWhere} AND {$peopleWhere}
         ORDER BY a.gamets DESC, a.ts DESC, a.localts DESC, a.rowid DESC
         LIMIT {$limit}"
    );
    $visibleTypesWhere = chimBuildVisibleEventLogWhereClause($GLOBALS['db'], '', $hiddenEventTypes);
    $eventTypes = $GLOBALS['db']->fetchAll(
        "SELECT a.type, COUNT(*) AS total
         FROM eventlog a
         WHERE {$allowedTypesWhere} AND {$visibleTypesWhere} AND {$peopleWhere}
         GROUP BY a.type
         ORDER BY a.type ASC"
    );

    $events = array_map(static function ($row) {
        $gamets = (int)($row['gamets'] ?? 0);
        return [
            'rowid' => (int)($row['rowid'] ?? 0),
            'type' => (string)($row['type'] ?? ''),
            'data' => (string)($row['data'] ?? ''),
            'recipients' => chimNpcManagerEventRecipients($row['people'] ?? ''),
            'gamets' => $gamets,
            'tamrielic_time' => $gamets > 0 ? convert_gamets2skyrim_long_date2($gamets) : '',
            'local_time' => !empty($row['localts']) ? gmdate('d-m-Y H:i:s', (int)$row['localts']) : '',
            'manual_injection' => strtolower((string)($row['type'] ?? '')) === 'inputtext'
                && (string)($row['sess'] ?? '') === 'npc_editor',
        ];
    }, (array)$rows);

    $sharedNameCount = chimNpcManagerSharedNameCount($npcName);

    return [
        'npc' => ['id' => (int)$npc['id'], 'name' => $npcName],
        'shared_name' => [
            'shared' => $sharedNameCount > 1,
            'count' => $sharedNameCount,
            'notice' => $sharedNameCount > 1
                ? 'This history is shared: ' . chimNpcManagerSharedNameNotice($npcName, $sharedNameCount)
                    . ' Injecting and deleting events is unavailable here.'
                : '',
        ],
        'events' => $events,
        'filters' => [
            'selected_event_type' => $selectedEventType,
            'hidden_event_types' => $hiddenEventTypes,
            'event_types' => array_map(static function ($row) {
                return [
                    'type' => (string)($row['type'] ?? ''),
                    'total' => (int)($row['total'] ?? 0),
                ];
            }, (array)$eventTypes),
        ],
    ];
}

function chimNpcManagerResolveEventRecipients(array $input, array $npc): array
{
    $ids = [(int)$npc['id']];
    $requestedIds = is_array($input['recipient_ids'] ?? null) ? $input['recipient_ids'] : [];
    foreach ($requestedIds as $requestedId) {
        $requestedId = (int)$requestedId;
        if ($requestedId > 0 && !in_array($requestedId, $ids, true)) {
            $ids[] = $requestedId;
        }
    }
    if (count($ids) > 12) {
        throw new InvalidArgumentException('An event can include at most 12 NPCs');
    }

    $recipients = [];
    foreach ($ids as $id) {
        $row = $id === (int)$npc['id'] ? $npc : chimNpcManagerFindNpc(['id' => $id]);
        $name = trim((string)($row['npc_name'] ?? ''));
        if ($name === '' || strpos($name, '|') !== false) {
            throw new InvalidArgumentException('One of the selected NPC names cannot be used for event routing');
        }
        $recipients[] = ['id' => (int)$row['id'], 'name' => $name];
    }
    return $recipients;
}

function chimNpcManagerInjectEvent(array $input): array
{
    $npc = chimNpcManagerFindNpc($input);
    $eventText = trim((string)($input['event'] ?? ''));
    if (strlen($eventText) >= 2 && $eventText[0] === '(' && substr($eventText, -1) === ')') {
        $eventText = trim(substr($eventText, 1, -1));
    }
    if ($eventText === '') {
        throw new InvalidArgumentException('Event text is required');
    }
    $eventLength = function_exists('mb_strlen') ? mb_strlen($eventText, 'UTF-8') : strlen($eventText);
    if ($eventLength > 4000) {
        throw new InvalidArgumentException('Event text must be 4000 characters or fewer');
    }

    $recipients = chimNpcManagerResolveEventRecipients($input, $npc);
    foreach ($recipients as $recipient) {
        chimNpcManagerGuardSharedNameEvents($recipient['name'], 'Event injection');
    }
    $people = '|' . implode('|', array_column($recipients, 'name')) . '|';
    $rowId = $GLOBALS['db']->insertReturningId('eventlog', [
        'ts' => max(0, (int)DataLastKnownTS()) + 1,
        'gamets' => max(0, (int)DataLastKnownGameTS()),
        'type' => 'inputtext',
        'data' => '(' . $eventText . ')',
        'sess' => 'npc_editor',
        'localts' => time(),
        'people' => $people,
        'location' => '',
        'party' => '',
    ], 'rowid');
    if ($rowId <= 0) {
        throw new RuntimeException('Event could not be injected');
    }

    return [
        'message' => 'Event injected for ' . implode(', ', array_column($recipients, 'name')) . '.',
        'rowid' => $rowId,
        'recipients' => $recipients,
    ];
}

function chimNpcManagerDeleteEvent(array $input): array
{
    $npc = chimNpcManagerFindNpc($input);
    $rowId = (int)($input['rowid'] ?? 0);
    if ($rowId <= 0) {
        throw new InvalidArgumentException('Invalid event row');
    }

    $npcName = trim((string)($npc['npc_name'] ?? ''));
    chimNpcManagerGuardSharedNameEvents($npcName, 'Event deletion');
    $peopleWhere = chimBuildNpcEventLogPeopleWhereClause($GLOBALS['db'], $npcName, 'a.people');
    $visibleWhere = chimBuildVisibleEventLogWhereClause($GLOBALS['db']);
    $event = $GLOBALS['db']->fetchOne(
        "SELECT a.rowid FROM eventlog a WHERE a.rowid = {$rowId} AND {$visibleWhere} AND {$peopleWhere} LIMIT 1"
    );
    if (!$event) {
        throw new InvalidArgumentException('Event is not available in this NPC history');
    }

    $result = chimDeleteEventLogRow($GLOBALS['db'], $rowId);
    if (empty($result['ok'])) {
        throw new RuntimeException((string)($result['message'] ?? 'Event could not be deleted'));
    }
    return ['message' => 'Event deleted.', 'rowid' => $rowId];
}

// Persist only the temporary return point without overwriting live NPC metadata updates.
function chimNpcManagerSaveReturnLocation(int $npcId, ?array $returnLocation): bool
{
    if ($npcId <= 0) {
        return false;
    }

    if ($returnLocation === null) {
        return $GLOBALS['db']->execQuery(
            "UPDATE core_npc_master SET metadata = COALESCE(metadata, '{}'::jsonb) - 'npc_manager_return_location' WHERE id = {$npcId}"
        ) !== false;
    }

    $encoded = json_encode($returnLocation, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($encoded === false) {
        return false;
    }
    $locationLiteral = $GLOBALS['db']->escape($encoded);
    return $GLOBALS['db']->execQuery(
        "UPDATE core_npc_master SET metadata = jsonb_set(COALESCE(metadata, '{}'::jsonb), '{npc_manager_return_location}', '{$locationLiteral}'::jsonb, true) WHERE id = {$npcId}"
    ) !== false;
}

function chimNpcManagerAction(array $input): array
{
    $row = chimNpcManagerFindNpc($input);
    $action = strtolower(trim((string)($input['action'] ?? '')));
    $npcName = trim((string)($row['npc_name'] ?? 'NPC'));
    $npcManager = new NpcMaster();
    $metadata = $npcManager->getMetadata($row);
    $returnLocationKey = 'npc_manager_return_location';

    if ($action === 'bgl_inception') {
        $idea = trim((string)($input['idea'] ?? ''));
        if ($idea === '') {
            throw new InvalidArgumentException('Background Life inception requires a thought');
        }
        // Row-scoped: same-named actors keep separate profiles, so only the selected row changes.
        if (!$npcManager->updateExtendedKeysById((int)$row['id'], ['bgl_inception' => $idea])) {
            throw new RuntimeException('Background Life inception could not be saved');
        }
        return ['message' => "Background Life thought set for {$npcName}."];
    }

    if (!in_array($action, ['visit', 'teleport', 'return'], true)) {
        throw new InvalidArgumentException('Unsupported NPC action');
    }

    $refid = strtoupper(preg_replace('/^0X/i', '', trim((string)($row['refid'] ?? ''))));
    if (!preg_match('/^[0-9A-F]{1,8}$/', $refid)) {
        throw new InvalidArgumentException("{$npcName} does not have a valid RefID");
    }
    $npcRefid = '0x' . str_pad($refid, 8, '0', STR_PAD_LEFT);

    if ($action === 'return') {
        $returnLocation = $metadata[$returnLocationKey] ?? null;
        if (!is_array($returnLocation)) {
            throw new InvalidArgumentException("{$npcName} does not have a saved return location");
        }

        $locationName = trim((string)($returnLocation['name'] ?? ''));
        $locationFormId = trim((string)($returnLocation['formid'] ?? ''));
        if ($locationName === '' && $locationFormId === '') {
            throw new InvalidArgumentException("{$npcName}'s saved return location is invalid");
        }
        if ($locationName === '' && $locationFormId !== '') {
            $formIdLiteral = $GLOBALS['db']->escape($locationFormId);
            $location = $GLOBALS['db']->fetchOne("SELECT name FROM locations WHERE formid = '{$formIdLiteral}' LIMIT 1");
            $locationName = trim((string)($location['name'] ?? 'previous location'));
        }

        $targetName = str_replace('@', '', NpcMaster::displayIdentifier($npcName, $row['refid']));
        $locationName = str_replace('@', '', $locationName);
        $roleCommand = $locationFormId !== ''
            ? "rolecommand|TeleportNPCRaw@{$targetName}@{$locationFormId}@{$locationName}"
            : "rolecommand|TeleportNPC@{$targetName}@{$locationName}";

        $GLOBALS['db']->insert('responselog', [
            'localts' => time(),
            'sent' => 0,
            'actor' => 'rolemaster',
            'text' => '',
            'action' => $roleCommand,
            'tag' => '',
        ]);

        if (!chimNpcManagerSaveReturnLocation((int)$row['id'], null)) {
            throw new RuntimeException('Saved return location could not be cleared');
        }

        return [
            'message' => "Return command sent for {$npcName} to {$locationName}.",
            'next_action' => 'teleport',
            'return_location' => '',
        ];
    }

    if ($action === 'teleport') {
        if (is_array($metadata[$returnLocationKey] ?? null)) {
            throw new InvalidArgumentException("Return {$npcName} before teleporting them again");
        }

        $lastCoords = $metadata['last_coords'] ?? null;
        if (!is_array($lastCoords) && is_array($metadata['last_coords_history'] ?? null)) {
            $history = $metadata['last_coords_history'];
            $lastCoords = empty($history) ? null : end($history);
        }
        if (!is_array($lastCoords)) {
            throw new InvalidArgumentException("{$npcName} does not have a tracked location to return to");
        }

        $locationName = trim((string)($lastCoords[3] ?? ''));
        $locationFormId = trim((string)($lastCoords['location_formid'] ?? ''));
        if ($locationFormId === '' && $locationName !== '') {
            $locationLiteral = $GLOBALS['db']->escape($locationName);
            $location = $GLOBALS['db']->fetchOne(
                "SELECT name, formid FROM locations ORDER BY similarity(name, '{$locationLiteral}') DESC LIMIT 1"
            );
            if (is_array($location)) {
                $locationName = trim((string)($location['name'] ?? $locationName));
                $locationFormId = trim((string)($location['formid'] ?? ''));
            }
        }
        if ($locationFormId === '' && is_numeric($lastCoords[0] ?? null) && is_numeric($lastCoords[1] ?? null)) {
            $pointLiteral = $GLOBALS['db']->escape('(' . (float)$lastCoords[0] . ',' . (float)$lastCoords[1] . ')');
            $location = $GLOBALS['db']->fetchOne(
                "SELECT name, formid FROM locations WHERE coords IS NOT NULL ORDER BY coords <-> '{$pointLiteral}'::point LIMIT 1"
            );
            if (is_array($location)) {
                $locationName = trim((string)($location['name'] ?? $locationName));
                $locationFormId = trim((string)($location['formid'] ?? ''));
            }
        }
        if ($locationName === '' && $locationFormId === '') {
            throw new InvalidArgumentException("{$npcName}'s tracked return location is invalid");
        }

        $returnLocation = [
            'name' => $locationName,
            'formid' => $locationFormId,
            'coords' => $lastCoords,
            'saved_at' => time(),
        ];
        if (!chimNpcManagerSaveReturnLocation((int)$row['id'], $returnLocation)) {
            throw new RuntimeException('Return location could not be saved');
        }
    }

    require_once LIB_PATH . DIRECTORY_SEPARATOR . 'scriptproxy_papyrus.php';
    $commandBuilder = new SkyrimCommandBuilder();
    $command = $action === 'visit'
        ? $commandBuilder->ObjectReference->MoveTo(PLAYER_REFID, $npcRefid)
        : $commandBuilder->ObjectReference->MoveTo($npcRefid, PLAYER_REFID);
    $commandBuilder->send($command);

    return [
        'message' => $action === 'visit'
            ? "Visit command sent for {$npcName}."
            : "Teleport command sent for {$npcName}.",
        'next_action' => $action === 'teleport' ? 'return' : null,
        'return_location' => $action === 'teleport' ? $locationName : '',
    ];
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
        $normalizedSearch = preg_replace('/^0x/i', '', $search);
        $escapedNormalized = $GLOBALS['db']->escape('%' . $normalizedSearch . '%');
        $conditions[] = "(npc_name ILIKE '{$escaped}' OR race ILIKE '{$escaped}' OR refid ILIKE '{$escaped}'
            OR replace(lower(refid), '0x', '') LIKE lower('{$escapedNormalized}')
            OR metadata::text ILIKE '{$escaped}')";
    }

    $profileId = (int)($_GET['profile_id'] ?? 0);
    if ($profileId > 0) {
        $conditions[] = "(CASE WHEN profile_owner_npc_id IS NULL THEN profile_id ELSE
            (SELECT owner.profile_id FROM core_npc_master owner WHERE owner.id = core_npc_master.profile_owner_npc_id)
            END) = {$profileId}";
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
        "SELECT core_npc_master.*, name_counts.duplicate_count,
            CASE WHEN EXISTS (SELECT 1 FROM core_npc_master child WHERE child.profile_owner_npc_id = core_npc_master.id)
                THEN 1 ELSE 0 END AS _has_shared_profile
         FROM core_npc_master
         JOIN (
             SELECT lower(npc_name) AS normalized_name, COUNT(*) AS duplicate_count
             FROM core_npc_master
             GROUP BY lower(npc_name)
         ) name_counts ON name_counts.normalized_name = lower(core_npc_master.npc_name)
         WHERE {$where}
         ORDER BY npc_favorite DESC NULLS LAST, npc_name ASC, id ASC
         LIMIT {$limit} OFFSET {$offset}"
    );
    $profileMap = chimNpcManagerProfileMap($profiles);

    return [
        'npcs' => array_map(static function ($row) use ($profileMap) {
            return chimNpcManagerCard(chimNpcEffectiveProfile($row), $profileMap);
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
    $raw = (new NpcMaster())->getActorById($id);
    $members = chimNpcProfileMembers($raw);
    // Older clients remain compatible for never-linked rows. A shared/previously shared editor must be current.
    if (chimNpcProfileBinding($raw) !== ':' || isset($input['profile_revision'])) {
        if (!hash_equals(chimNpcProfileRevision($members), (string)($input['profile_revision'] ?? ''))) {
            throw new InvalidArgumentException('Profile changed. Reopen this NPC before saving.');
        }
    }
    $fields = is_array($input['fields'] ?? null) ? $input['fields'] : [];
    $overrides = is_array($input['overrides'] ?? null) ? $input['overrides'] : [];
    $update = ['_profile_binding' => $row['_profile_binding'] ?? ':'];

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

    if (array_key_exists('npc_name', $update) && $update['npc_name'] === '') {
        throw new InvalidArgumentException('NPC name is required');
    }

    // RefID is part of the profile selector, so management cannot change it independently.
    if (NpcMaster::isActorBound($row)) {
        unset($update['refid']);
    }

    // The lookup key always follows the stored Name + RefID identity, never client-supplied md5.
    $update['md5'] = NpcMaster::identityMd5($row, $update['npc_name'] ?? ($row['npc_name'] ?? ''));

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
            foreach (['relation', 'note', 'best', 'worst', 'custom_info'] as $key) {
                if (array_key_exists($key, $relationship)) {
                    $relationship[$key] = is_scalar($relationship[$key])
                        ? trim((string)$relationship[$key])
                        : '';
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
    $ownerId = (int)($row['profile_owner_npc_id'] ?? $id);
    $lockId = $relationshipChanged ? 1001000000 + $ownerId : null;
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
            chimRelationshipTimelineStamp($ownerId);
        }
        (new NpcMaster())->backupNpcById($ownerId);
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
        $operation = strtolower(trim((string)($input['operation'] ?? 'save')));
        if ($operation === 'inject_event') {
            chimNpcManagerRespond(['success' => true, 'data' => chimNpcManagerInjectEvent($input)]);
        }
        if ($operation === 'delete_event') {
            chimNpcManagerRespond(['success' => true, 'data' => chimNpcManagerDeleteEvent($input)]);
        }
        if ($operation === 'action') {
            chimNpcManagerRespond(['success' => true, 'data' => chimNpcManagerAction($input)]);
        }
        if ($operation !== 'save') {
            throw new InvalidArgumentException('Unsupported NPC manager operation');
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
    if ($operation === 'history') {
        chimNpcManagerRespond(['success' => true, 'data' => chimNpcManagerHistory($_GET)]);
    }
    throw new InvalidArgumentException('Unsupported NPC manager operation');
} catch (InvalidArgumentException $error) {
    chimNpcManagerRespond(['success' => false, 'error' => $error->getMessage()], 400);
} catch (Throwable $error) {
    Logger::error('CHIM NPC manager API failed: ' . $error->getMessage());
    chimNpcManagerRespond(['success' => false, 'error' => 'Unable to process NPC manager request'], 500);
}
