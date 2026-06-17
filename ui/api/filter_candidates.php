<?php
error_reporting(E_ERROR);
session_start();

define('BASE_PATH', dirname(dirname(__DIR__)));
define('LIB_PATH', BASE_PATH . DIRECTORY_SEPARATOR . 'lib');

require_once(LIB_PATH . DIRECTORY_SEPARATOR . 'runtime_bootstrap.php');

chimRuntimeBootstrap(BASE_PATH, [
    'load_general_settings' => false,
    'load_stt_connector' => false,
    'load_itt_connector' => false,
    'run_db_updates' => false,
]);

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate');

function filterCandidateConfigs(): array
{
    return [
        'MAGIC_EVENT_BLACKLIST' => [
            'title' => 'Recent Magic Events',
            'description' => 'Recent npcspellcast names parsed from the last 5000 eventlog rows.',
        ],
        'LOCATION_BLACKLIST' => [
            'title' => 'Recent Locations',
            'description' => 'Recent Points of Interest names parsed from the last 5000 eventlog rows.',
        ],
        'ITEM_BLACKLIST' => [
            'title' => 'Recent Items',
            'description' => 'Recent item names parsed from itemfound and nearby-item eventlog entries.',
        ],
        'EVENT_TYPE_FILTER' => [
            'title' => 'Recent Event Types',
            'description' => 'Prompt-relevant event types seen in the last 5000 eventlog rows.',
        ],
    ];
}

function filterCandidateNormalizeKey(string $value): string
{
    $value = trim($value);
    $value = preg_replace('/\s+/u', ' ', $value);
    return strtolower($value ?? '');
}

function filterCandidateCleanValue(string $value): string
{
    $value = trim($value);
    return preg_replace('/\s+/u', ' ', $value) ?? '';
}

function filterCandidateAdd(array &$items, string $value, ?string $sample = null): void
{
    $value = filterCandidateCleanValue($value);
    if ($value === '') {
        return;
    }

    $key = filterCandidateNormalizeKey($value);
    if ($key === '') {
        return;
    }

    if (!isset($items[$key])) {
        $items[$key] = [
            'value' => $value,
            'count' => 0,
            'sample' => $sample ? filterCandidateCleanValue($sample) : '',
        ];
    }

    $items[$key]['count']++;

    if ($items[$key]['sample'] === '' && $sample) {
        $items[$key]['sample'] = filterCandidateCleanValue($sample);
    }
}

function filterCandidateSort(array $items): array
{
    $items = array_values($items);
    usort($items, function (array $left, array $right): int {
        $countCompare = intval($right['count']) <=> intval($left['count']);
        if ($countCompare !== 0) {
            return $countCompare;
        }
        return strcasecmp(strval($left['value']), strval($right['value']));
    });
    return $items;
}

function filterCandidateQueryRecentRows(sql $db, array $types): array
{
    if (empty($types)) {
        return [];
    }

    $quotedTypes = array_map(function (string $type) use ($db): string {
        return "'" . $db->escape($type) . "'";
    }, $types);

    $sql = "
        SELECT rowid, type, data
        FROM (
            SELECT rowid, type, data
            FROM eventlog
            ORDER BY rowid DESC
            LIMIT 5000
        ) recent
        WHERE type IN (" . implode(', ', $quotedTypes) . ")
        ORDER BY rowid DESC
    ";

    return $db->fetchAll($sql);
}

function filterCandidateExtractMagicName(string $data): string
{
    if (preg_match('/^.+?\s+(?:casts|uses)\s+(.+?)(?:\s+on\s+.+)?$/i', trim($data), $matches)) {
        return trim($matches[1]);
    }
    return '';
}

function filterCandidateExtractLocationNames(string $data): array
{
    $source = trim($data);
    $segments = [];

    if (preg_match('/\b(?:Buildings|buildings)\s+to\s+go:\s*(.+?)(?:,\s*Current Date|\)\s*$)/i', $source, $matches)) {
        $segments[] = $matches[1];
    }
    if (preg_match('/\bto\s+go:\s*(.+?)(?:,,|\)\s*$|,\s*Current Date)/i', $source, $matches)) {
        $segments[] = $matches[1];
    }

    $locations = [];
    foreach ($segments as $segment) {
        foreach (explode(',', $segment) as $location) {
            $clean = filterCandidateCleanValue($location);
            if ($clean !== '') {
                $locations[] = $clean;
            }
        }
    }

    return array_values(array_unique($locations, SORT_STRING));
}

function filterCandidateStripItemTags(string $itemName): string
{
    $itemName = str_replace(' (STEALING)', '', $itemName);
    $itemName = str_replace(' (LOOKING AT)', '', $itemName);
    return filterCandidateCleanValue($itemName);
}

function filterCandidateExtractItemNameFromEvent(string $data): string
{
    if (!preg_match('/^.+?\s+(?:found|took|looted|traded|gave)\s+(.+?)(?:,\(value.+\))?\s*$/i', trim($data), $matches)) {
        return '';
    }

    $itemInfo = trim($matches[1]);
    $itemInfo = preg_replace('/\s+(?:from|to)\s+.+$/i', '', $itemInfo) ?? $itemInfo;
    $itemInfo = preg_replace('/\s+in\s+a\s+.+$/i', '', $itemInfo) ?? $itemInfo;
    $itemInfo = preg_replace('/^\d+\s+/i', '', $itemInfo) ?? $itemInfo;
    $itemInfo = preg_replace('/^an?\s+/i', '', $itemInfo) ?? $itemInfo;

    return filterCandidateStripItemTags($itemInfo);
}

function filterCandidateExtractItemsFromInfoItems(string $data): array
{
    if (!preg_match('/\(items in range:(.+)\)/i', trim($data), $matches)) {
        return [];
    }

    $items = [];
    foreach (explode(',', $matches[1]) as $entry) {
        $parts = explode(':', trim($entry), 3);
        if (count($parts) >= 3) {
            $items[] = filterCandidateStripItemTags($parts[2]);
        } elseif (count($parts) === 2) {
            $items[] = filterCandidateStripItemTags($parts[1]);
        }
    }

    return $items;
}

function filterCandidateBuildMagic(sql $db): array
{
    $rows = filterCandidateQueryRecentRows($db, ['npcspellcast']);
    $items = [];

    foreach ($rows as $row) {
        $data = strval($row['data'] ?? '');
        $value = filterCandidateExtractMagicName($data);
        if ($value !== '') {
            filterCandidateAdd($items, $value, $data);
        }
    }

    return filterCandidateSort($items);
}

function filterCandidateBuildLocations(sql $db): array
{
    $rows = filterCandidateQueryRecentRows($db, ['infoloc']);
    $items = [];

    foreach ($rows as $row) {
        $data = strval($row['data'] ?? '');
        foreach (filterCandidateExtractLocationNames($data) as $value) {
            filterCandidateAdd($items, $value, $data);
        }
    }

    return filterCandidateSort($items);
}

function filterCandidateBuildItems(sql $db): array
{
    $rows = filterCandidateQueryRecentRows($db, ['itemfound', 'infoitems']);
    $items = [];

    foreach ($rows as $row) {
        $type = strval($row['type'] ?? '');
        $data = strval($row['data'] ?? '');

        if ($type === 'itemfound') {
            $value = filterCandidateExtractItemNameFromEvent($data);
            if ($value !== '') {
                filterCandidateAdd($items, $value, $data);
            }
            continue;
        }

        if ($type === 'infoitems') {
            foreach (filterCandidateExtractItemsFromInfoItems($data) as $value) {
                filterCandidateAdd($items, $value, $data);
            }
        }
    }

    return filterCandidateSort($items);
}

function filterCandidateBuildEventTypes(sql $db): array
{
    $excludedTypes = [
        'combatend',
        'bored',
        'init',
        'infoloc',
        'info',
        'funcret',
        'book',
        'addnpc',
        'infonpc',
        'infoitems',
        'updateprofile',
        'rechat',
        'setconf',
        'status_msg',
        'user_input',
        'infonpc_close',
        'instruction',
        'request',
        'playerinfo',
        'im_alive',
        'region',
        'named_cell',
    ];

    $quotedTypes = array_map(function (string $type) use ($db): string {
        return "'" . $db->escape($type) . "'";
    }, $excludedTypes);

    $sql = "
        SELECT type, COUNT(*) AS count
        FROM (
            SELECT rowid, type
            FROM eventlog
            ORDER BY rowid DESC
            LIMIT 5000
        ) recent
        WHERE type NOT IN (" . implode(', ', $quotedTypes) . ")
        GROUP BY type
        ORDER BY COUNT(*) DESC, LOWER(type) ASC
    ";

    $rows = $db->fetchAll($sql);
    $items = [];

    foreach ($rows as $row) {
        $value = filterCandidateCleanValue(strval($row['type'] ?? ''));
        if ($value === '') {
            continue;
        }
        $items[] = [
            'value' => $value,
            'count' => intval($row['count'] ?? 0),
            'sample' => '',
        ];
    }

    return $items;
}

try {
    $field = strtoupper(trim(strval($_GET['field'] ?? '')));
    $configs = filterCandidateConfigs();

    if (!isset($configs[$field])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Unsupported field.',
        ]);
        exit;
    }

    /** @var sql $db */
    $db = $GLOBALS['db'];

    if ($field === 'MAGIC_EVENT_BLACKLIST') {
        $data = filterCandidateBuildMagic($db);
    } elseif ($field === 'LOCATION_BLACKLIST') {
        $data = filterCandidateBuildLocations($db);
    } elseif ($field === 'ITEM_BLACKLIST') {
        $data = filterCandidateBuildItems($db);
    } else {
        $data = filterCandidateBuildEventTypes($db);
    }

    echo json_encode([
        'success' => true,
        'field' => $field,
        'meta' => $configs[$field],
        'data' => $data,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ]);
}
