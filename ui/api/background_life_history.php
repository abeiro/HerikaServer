<?php
error_reporting(E_ERROR);
session_start();

define('BASE_PATH', dirname(dirname(__DIR__)));
define('CONFIG_PATH', BASE_PATH . DIRECTORY_SEPARATOR . 'conf');
define('LIB_PATH', BASE_PATH . DIRECTORY_SEPARATOR . 'lib');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if (!file_exists(CONFIG_PATH . DIRECTORY_SEPARATOR . 'conf.php')) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Configuration file not found',
    ]);
    exit;
}

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'profile_loader.php';
require_once LIB_PATH . DIRECTORY_SEPARATOR . 'logger.php';
require_once LIB_PATH . DIRECTORY_SEPARATOR . "{$GLOBALS['DBDRIVER']}.class.php";
require_once LIB_PATH . DIRECTORY_SEPARATOR . 'utils_game_timestamp.php';
require_once LIB_PATH . DIRECTORY_SEPARATOR . 'background_life_dashboard.php';

$db = new sql();

function chimBglHistoryTamrielicDate(int $gamets): string
{
    if ($gamets <= 0) {
        return 'Unknown time';
    }

    return convert_gamets2skyrim_long_date2($gamets);
}

function chimBglHistoryUtcDate(int $localts): string
{
    if ($localts <= 0) {
        return '';
    }

    $date = new DateTimeImmutable('@' . $localts);
    return $date->setTimezone(new DateTimeZone('UTC'))->format('d-m-Y H:i:s') . ' UTC';
}

try {
    $limit = max(10, min(100, (int)($_GET['limit'] ?? 20)));
    $page = max(1, (int)($_GET['page'] ?? 1));
    $offset = ($page - 1) * $limit;
    $npc = trim((string)($_GET['npc'] ?? ''));
    $search = trim((string)($_GET['search'] ?? ''));

    if (strlen($npc) > 160) {
        $npc = substr($npc, 0, 160);
    }
    if (strlen($search) > 200) {
        $search = substr($search, 0, 200);
    }

    $where = ['TRUE'];
    if ($npc !== '') {
        $where[] = 'npc = ' . $db->escapeLiteral($npc);
    }
    if ($search !== '') {
        $where[] = 'data ILIKE ' . $db->escapeLiteral('%' . $search . '%');
    }
    $whereSql = implode(' AND ', $where);

    $categorySelect = chimBglHistoryCategorySelect($db);
    $rows = $db->fetchAll(
        "SELECT rowid, npc, gamets, ts, localts, data{$categorySelect}
         FROM bgl_history
         WHERE {$whereSql}
         ORDER BY gamets DESC, ts DESC, rowid DESC
         LIMIT {$limit} OFFSET {$offset}"
    );

    $countRows = $db->fetchAll(
        "SELECT COUNT(*) AS total
         FROM bgl_history
         WHERE {$whereSql}"
    );
    $totalRecords = (int)($countRows[0]['total'] ?? 0);
    $totalPages = max(1, (int)ceil($totalRecords / $limit));

    $npcRows = $db->fetchAll(
        "SELECT npc, COUNT(*) AS activity_count
         FROM bgl_history
         WHERE COALESCE(npc, '') <> ''
         GROUP BY npc
         ORDER BY LOWER(npc) ASC"
    );

    $entries = array_map(static function (array $row): array {
        $activity = trim((string)($row['data'] ?? ''));
        $gamets = (int)($row['gamets'] ?? 0);
        $localts = (int)($row['localts'] ?? 0);

        return [
            'rowid' => (int)($row['rowid'] ?? 0),
            'npc' => trim((string)($row['npc'] ?? 'Unknown NPC')),
            'activity' => $activity,
            'category' => trim((string)($row['category'] ?? '')) ?: chimBglHistoryCategory($activity),
            'tamrielic_time' => chimBglHistoryTamrielicDate($gamets),
            'server_time' => chimBglHistoryUtcDate($localts),
            'gamets' => $gamets,
        ];
    }, $rows);

    $npcs = array_map(static function (array $row): array {
        return [
            'name' => (string)($row['npc'] ?? ''),
            'count' => (int)($row['activity_count'] ?? 0),
        ];
    }, $npcRows);

    echo json_encode([
        'success' => true,
        'entries' => $entries,
        'npcs' => $npcs,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_records' => $totalRecords,
            'limit' => $limit,
        ],
        'latest_rowid' => !empty($entries) ? max(array_column($entries, 'rowid')) : 0,
        'timestamp' => time(),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (Throwable $error) {
    Logger::error('Background Life history API failed: ' . $error->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Unable to load Background Life history',
    ]);
}
