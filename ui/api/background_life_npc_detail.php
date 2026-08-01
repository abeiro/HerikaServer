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
    echo json_encode(['success' => false, 'error' => 'Configuration file not found']);
    exit;
}

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'profile_loader.php';
require_once LIB_PATH . DIRECTORY_SEPARATOR . 'logger.php';
require_once LIB_PATH . DIRECTORY_SEPARATOR . "{$GLOBALS['DBDRIVER']}.class.php";
require_once LIB_PATH . DIRECTORY_SEPARATOR . 'utils_game_timestamp.php';
require_once LIB_PATH . DIRECTORY_SEPARATOR . 'background_life_dashboard.php';

$db = new sql();

function chimBglDetailDiaryEntry(array $row): array
{
    $gamets = (int)($row['gamets'] ?? 0);
    return [
        'topic' => trim((string)($row['topic'] ?? 'Journal Entry')),
        'content' => trim((string)($row['content'] ?? '')),
        'tags' => trim((string)($row['tags'] ?? '')),
        'location' => trim((string)($row['location'] ?? '')),
        'gamets' => $gamets,
        'tamrielic_time' => $gamets > 0 ? convert_gamets2skyrim_long_date2($gamets) : '',
    ];
}

try {
    $npcName = trim((string)($_GET['npc'] ?? ''));
    if ($npcName === '') {
        throw new InvalidArgumentException('NPC name is required');
    }
    if (strlen($npcName) > 160) {
        $npcName = substr($npcName, 0, 160);
    }
    $npcLiteral = $db->escapeLiteral($npcName);
    $peopleLiteral = $db->escapeLiteral('%' . $npcName . '%');

    $categorySelect = chimBglHistoryCategorySelect($db);
    $eventRows = $db->fetchAll(
        "SELECT rowid, npc, gamets, ts, localts, data{$categorySelect}
         FROM bgl_history
         WHERE npc = {$npcLiteral}
         ORDER BY gamets DESC, ts DESC, rowid DESC
         LIMIT 20"
    );
    $events = array_map(static function (array $row): array {
        $gamets = (int)($row['gamets'] ?? 0);
        return [
            'rowid' => (int)($row['rowid'] ?? 0),
            'activity' => trim((string)($row['data'] ?? '')),
            'category' => trim((string)($row['category'] ?? ''))
                ?: chimBglHistoryCategory((string)($row['data'] ?? '')),
            'gamets' => $gamets,
            'tamrielic_time' => $gamets > 0 ? convert_gamets2skyrim_long_date2($gamets) : '',
        ];
    }, $eventRows);

    $letterRows = $db->fetchAll(
        "SELECT topic, content, tags, location, gamets
         FROM diarylog
         WHERE people LIKE {$peopleLiteral} AND topic = 'Sent Letter'
         ORDER BY gamets DESC, localts DESC
         LIMIT 20"
    );
    $thoughtRows = $db->fetchAll(
        "SELECT topic, content, tags, location, gamets
         FROM diarylog
         WHERE people LIKE {$peopleLiteral} AND (topic <> 'Sent Letter' OR topic IS NULL)
         ORDER BY gamets DESC, localts DESC
         LIMIT 20"
    );

    echo json_encode([
        'success' => true,
        'data' => [
            'npc' => $npcName,
            'events' => $events,
            'letters' => array_map('chimBglDetailDiaryEntry', $letterRows),
            'thoughts' => array_map('chimBglDetailDiaryEntry', $thoughtRows),
        ],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $error) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $error->getMessage()]);
} catch (Throwable $error) {
    Logger::error('Background Life NPC detail API failed: ' . $error->getMessage());
    if (http_response_code() < 400) {
        http_response_code(500);
    }
    echo json_encode(['success' => false, 'error' => 'Unable to load NPC history']);
}
