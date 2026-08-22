<?php

const CHIM_BGL_WORLD_X_MIN = -225242;
const CHIM_BGL_WORLD_X_MAX = 217068;
const CHIM_BGL_WORLD_Y_MIN = -164195;
const CHIM_BGL_WORLD_Y_MAX = 204675;
const CHIM_BGL_MAP_WIDTH = 1950;
const CHIM_BGL_MAP_HEIGHT = 1625;

// Convert Skyrim world coordinates to percentages shared by both dashboard surfaces.
function chimBglMapPosition($x, $y): array
{
    $xRange = CHIM_BGL_WORLD_X_MAX - CHIM_BGL_WORLD_X_MIN;
    $yRange = CHIM_BGL_WORLD_Y_MAX - CHIM_BGL_WORLD_Y_MIN;
    $mapX = (((float)$x - CHIM_BGL_WORLD_X_MIN) / $xRange) * CHIM_BGL_MAP_WIDTH;
    $mapY = ((CHIM_BGL_WORLD_Y_MAX - (float)$y) / $yRange) * CHIM_BGL_MAP_HEIGHT;

    $mapX = max(0, min(CHIM_BGL_MAP_WIDTH, $mapX));
    $mapY = max(0, min(CHIM_BGL_MAP_HEIGHT, $mapY));

    return [
        'x' => round($mapX),
        'y' => round($mapY),
        'percent_x' => round(($mapX / CHIM_BGL_MAP_WIDTH) * 100, 4),
        'percent_y' => round(($mapY / CHIM_BGL_MAP_HEIGHT) * 100, 4),
    ];
}

function chimBglDashboardColor(string $name): string
{
    return sprintf('#%06X', abs(crc32($name)) % 0xFFFFFF);
}

function chimBglDashboardSlug(string $value): string
{
    $words = preg_split('/[^a-z0-9]+/', strtolower($value), -1, PREG_SPLIT_NO_EMPTY);
    return implode('', $words ?: []);
}

function chimBglHistoryCategory(string $activity): string
{
    $activity = strtolower($activity);

    if (preg_match('/\b(travel|travelling|arriv)/', $activity)) {
        return 'travel';
    }
    if (preg_match('/\b(work|job|craft|mine|harvest)/', $activity)) {
        return 'work';
    }
    if (preg_match('/\b(sleep|rest|wait)/', $activity)) {
        return 'rest';
    }
    if (preg_match('/\b(speak|talk|conversation|visit)/', $activity)) {
        return 'social';
    }
    if (preg_match('/\b(move|search|find|follow)/', $activity)) {
        return 'movement';
    }
    if (preg_match('/\b(buy|sell|trade|gold|inventory)/', $activity)) {
        return 'trade';
    }

    return 'activity';
}

function chimBglActivityCategory(string $category, string $activity = ''): string
{
    $category = strtolower(trim($category));
    if ($category === '') {
        $category = chimBglHistoryCategory($activity);
    }

    return [
        'rest' => 'sleep',
        'social' => 'socialize',
        'movement' => 'travel',
    ][$category] ?? $category;
}

function chimBglActivityIcon(string $category, string $activity = ''): string
{
    $category = chimBglActivityCategory($category, $activity);

    return [
        'error' => '❌',
        'work' => '🛠️',
        'travel' => '👣',
        'sleep' => '😴',
        'produce_consume' => '🍽️',
        'socialize' => '🥂',
        'dialogue' => '💬',
        'relax' => '🪑',
        'trade' => '💰',
        'guard' => '🛡️',
        'move' => '🚶',
        'return_home' => '🏠',
        'find' => '🔎',
        'give' => '🎁',
        'letter' => '✉️',
        'warning' => '⚠️',
        'activity' => '✨',
    ][$category] ?? '✨';
}

function chimBglActivityLabel(string $category, string $activity = ''): string
{
    $category = chimBglActivityCategory($category, $activity);

    return [
        'error' => 'Error',
        'work' => 'Work',
        'travel' => 'Travel',
        'sleep' => 'Sleep',
        'produce_consume' => 'Produce / Consume',
        'socialize' => 'Socialize',
        'dialogue' => 'Dialogue',
        'relax' => 'Relax',
        'trade' => 'Trade',
        'guard' => 'Guard',
        'move' => 'Move',
        'return_home' => 'Return Home',
        'find' => 'Find',
        'give' => 'Give',
        'letter' => 'Letter',
        'warning' => 'Warning',
        'activity' => 'Activity',
    ][$category] ?? ucwords(str_replace('_', ' ', $category));
}

// Keep the dashboard usable while an existing install is waiting for migrations.
function chimBglHistoryCategorySelect(sql $db): string
{
    static $select = null;
    if ($select !== null) {
        return $select;
    }

    $rows = $db->fetchAll(
        "SELECT 1
         FROM information_schema.columns
         WHERE table_schema = 'public'
           AND table_name = 'bgl_history'
           AND column_name = 'category'
         LIMIT 1"
    );
    $select = empty($rows) ? '' : ', category';
    return $select;
}

// Resolve the same portrait candidates used by the web NPC cards.
function chimBglDashboardPortrait(
    string $enginePath,
    string $webRoot,
    string $race,
    string $refid,
    string $npcName,
    array $metadata
): string {
    $portraitRel = trim((string)($metadata['portrait'] ?? ''));
    $picturesRootFs = realpath(rtrim($enginePath, '/\\') . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'pictures');
    if ($portraitRel !== '' && $picturesRootFs !== false) {
        $portraitRel = ltrim(str_replace('\\', '/', $portraitRel), '/');
        $candidate = realpath($picturesRootFs . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $portraitRel));
        if ($candidate !== false && str_starts_with($candidate, $picturesRootFs) && is_file($candidate)) {
            return rtrim($webRoot, '/') . '/data/pictures/' . str_replace('%2F', '/', rawurlencode($portraitRel));
        }
    }

    $profileDir = rtrim($enginePath, '/\\') . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'pictures' . DIRECTORY_SEPARATOR . 'profile';
    $candidateNames = array_values(array_unique(array_filter([
        md5($npcName),
        strtoupper($refid),
        chimBglDashboardSlug($npcName),
    ])));
    foreach ($candidateNames as $candidateName) {
        foreach (['png', 'jpg', 'jpeg', 'webp', 'gif'] as $extension) {
            $candidate = $profileDir . DIRECTORY_SEPARATOR . $candidateName . '.' . $extension;
            if (is_file($candidate)) {
                return rtrim($webRoot, '/') . '/data/pictures/profile/' . rawurlencode($candidateName . '.' . $extension);
            }
        }
    }

    $raceAliases = [
        'highelf' => 'altmer',
        'woodelf' => 'bosmer',
        'darkelf' => 'dunmer',
        'orsimer' => 'orc',
        'oldpeople' => 'nord',
        'oldpeoplerace' => 'nord',
        'khajit' => 'khajiit',
    ];
    $raceSlug = chimBglDashboardSlug($race);
    $raceSlug = $raceAliases[$raceSlug] ?? $raceSlug;
    $raceDir = rtrim($enginePath, '/\\') . DIRECTORY_SEPARATOR . 'ui' . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'races';
    foreach (['png', 'jpg', 'jpeg', 'webp', 'gif', 'svg'] as $extension) {
        if ($raceSlug !== '' && is_file($raceDir . DIRECTORY_SEPARATOR . $raceSlug . '.' . $extension)) {
            return rtrim($webRoot, '/') . '/ui/images/races/' . rawurlencode($raceSlug . '.' . $extension);
        }
    }

    return rtrim($webRoot, '/') . '/ui/images/races/default.png';
}

function chimBglDashboardPassiveMarkers(string $enginePath): array
{
    $path = rtrim($enginePath, '/\\') . DIRECTORY_SEPARATOR . 'ui' . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'location_markers.json';
    if (!is_file($path)) {
        return [];
    }

    $payload = json_decode((string)file_get_contents($path), true);
    if (!is_array($payload) || !is_array($payload['locations'] ?? null)) {
        return [];
    }

    $markers = [];
    foreach ($payload['locations'] as $location) {
        $coords = $location['coords'] ?? [];
        if (!isset($coords['x'], $coords['y'])) {
            continue;
        }
        $position = chimBglMapPosition($coords['x'], $coords['y']);
        $markers[] = [
            'name' => trim((string)($location['name'] ?? 'Unknown location')),
            'description' => trim((string)($location['description'] ?? '')),
            'type' => trim((string)($location['type'] ?? '')),
            'form_id' => trim((string)($location['formID'] ?? '')),
            'icon' => trim((string)($location['icon'] ?? '')),
            'world_x' => (float)$coords['x'],
            'world_y' => (float)$coords['y'],
            'percent_x' => $position['percent_x'],
            'percent_y' => $position['percent_y'],
        ];
    }

    return $markers;
}

// Assemble the compact map and NPC card payload used by Prisma.
function chimBglDashboardPayload(sql $db, string $enginePath, string $webRoot, bool $showAllCoords = false): array
{
    $where = $showAllCoords
        ? "master.metadata->>'last_coords' IS NOT NULL"
        : "COALESCE(master.extended_data->>'background_life_enabled', 'false') = 'true'";
    $categorySelect = chimBglHistoryCategorySelect($db);
    $latestCategorySelect = $categorySelect === ''
        ? 'NULL::text AS category'
        : 'history.category AS category';
    $rows = $db->fetchAll(
        "SELECT master.id,
                master.npc_name,
                master.refid,
                master.race,
                master.metadata,
                master.extended_data,
                latest.data AS latest_activity,
                latest.gamets AS latest_gamets,
                latest.category AS latest_category
         FROM core_npc_master master
         LEFT JOIN LATERAL (
             SELECT history.data, history.gamets, {$latestCategorySelect}
             FROM bgl_history history
             WHERE history.npc = master.npc_name
             ORDER BY history.gamets DESC, history.ts DESC, history.rowid DESC
             LIMIT 1
         ) latest ON TRUE
         WHERE {$where}
         ORDER BY LOWER(master.npc_name) ASC"
    );

    $npcs = [];
    foreach ($rows as $row) {
        $metadata = json_decode((string)($row['metadata'] ?? '{}'), true);
        $metadata = is_array($metadata) ? $metadata : [];
        $extended = json_decode((string)($row['extended_data'] ?? '{}'), true);
        $extended = is_array($extended) ? $extended : [];
        $coordsValue = $metadata['last_coords'] ?? null;
        $coords = is_array($coordsValue)
            ? $coordsValue
            : json_decode((string)$coordsValue, true);
        $hasCoordinates = is_array($coords) && isset($coords[0], $coords[1]);
        $position = $hasCoordinates ? chimBglMapPosition($coords[0], $coords[1]) : null;
        $npcName = trim((string)($row['npc_name'] ?? 'Unknown NPC'));
        $refid = chimBglNormalizeRefId((string)($row['refid'] ?? ''));
        $latestGamets = (int)($row['latest_gamets'] ?? 0);
        $latestActivity = trim((string)($row['latest_activity'] ?? ''));
        $activityCategory = chimBglActivityCategory(
            (string)($row['latest_category'] ?? ''),
            $latestActivity
        );

        $npcs[] = [
            'npc_id' => (int)($row['id'] ?? 0),
            'name' => $npcName,
            'refid' => $refid,
            'race' => trim((string)($row['race'] ?? '')),
            'portrait_url' => chimBglDashboardPortrait(
                $enginePath,
                $webRoot,
                (string)($row['race'] ?? ''),
                $refid,
                $npcName,
                $metadata
            ),
            'activity' => $latestActivity,
            'activity_category' => $activityCategory,
            'activity_icon' => chimBglActivityIcon($activityCategory, $latestActivity),
            'activity_label' => chimBglActivityLabel($activityCategory, $latestActivity),
            'tamrielic_time' => $latestGamets > 0 ? convert_gamets2skyrim_long_date2($latestGamets) : '',
            'gamets' => $latestGamets,
            'auto_actions' => chimBglBoolean($extended['background_life_commands'] ?? false),
            'send_letters' => chimBglBoolean($extended['background_life_letters'] ?? false),
            'hourly_tracking' => chimBglBoolean($metadata['gps_track'] ?? false),
            'combat_participation' => chimBglBoolean($extended['background_life_combat_participation'] ?? false),
            'combat_initiate' => chimBglBoolean($extended['background_life_combat_initiate'] ?? false),
            'combat_lethal' => chimBglBoolean($extended['background_life_combat_lethal'] ?? false),
            'combat_loot' => chimBglBoolean($extended['background_life_combat_loot'] ?? false),
            'has_coordinates' => $hasCoordinates,
            'world_x' => $hasCoordinates ? (float)$coords[0] : null,
            'world_y' => $hasCoordinates ? (float)$coords[1] : null,
            'world_z' => $hasCoordinates && isset($coords[2]) ? (float)$coords[2] : null,
            'location' => $hasCoordinates ? trim((string)($coords[3] ?? '')) : '',
            'percent_x' => $position['percent_x'] ?? null,
            'percent_y' => $position['percent_y'] ?? null,
            'color' => chimBglDashboardColor($npcName),
        ];
    }

    $gameRow = $db->fetchAll('SELECT max(gamets) AS last_gamets FROM eventlog');
    $lastGamets = (int)($gameRow[0]['last_gamets'] ?? 0);

    return [
        'game' => [
            'gamets' => $lastGamets,
            'tamrielic_date' => $lastGamets > 0 ? convert_gamets2skyrim_long_date2($lastGamets) : '',
        ],
        'settings' => [
            'trigger_hours' => chimGetBackgroundLifeTriggerHours(),
            'show_all_coords' => $showAllCoords,
        ],
        'map' => [
            'image_url' => rtrim($webRoot, '/') . '/data/maps/Map_of_Skyrim.png?v=7',
            'width' => CHIM_BGL_MAP_WIDTH,
            'height' => CHIM_BGL_MAP_HEIGHT,
            'locations' => chimBglDashboardPassiveMarkers($enginePath),
        ],
        'npcs' => $npcs,
    ];
}
