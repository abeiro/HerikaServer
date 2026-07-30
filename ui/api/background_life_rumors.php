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
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'cmd' . DIRECTORY_SEPARATOR . 'rumor_service.php';
require_once LIB_PATH . DIRECTORY_SEPARATOR . 'logger.php';
require_once LIB_PATH . DIRECTORY_SEPARATOR . "{$GLOBALS['DBDRIVER']}.class.php";
require_once LIB_PATH . DIRECTORY_SEPARATOR . 'utils_game_timestamp.php';

$db = new sql();

function chimBglRumorApiEntry(array $row, int $lastGamets, int $gametsPerDay): array
{
    $gamets = (int)($row['gamets'] ?? 0);
    $lengthDays = (int)($row['rumor_length_days'] ?? 7);
    return [
        'id' => (int)($row['id'] ?? 0),
        'hold' => trim((string)($row['hold'] ?? '')),
        'type' => trim((string)($row['type'] ?? 'General')),
        'content' => trim((string)($row['content'] ?? '')),
        'length_days' => $lengthDays,
        'gamets' => $gamets,
        'tamrielic_time' => $gamets > 0 ? convert_gamets2skyrim_long_date2($gamets) : '',
        'current' => ($gamets + ($lengthDays * $gametsPerDay)) > $lastGamets,
    ];
}

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $operation = trim((string)($_POST['operation'] ?? ''));
        if ($operation === 'delete') {
            $rumorId = (int)($_POST['id'] ?? 0);
            if ($rumorId <= 0) {
                throw new InvalidArgumentException('Invalid rumor selected for deletion');
            }
            $db->delete('rumors', 'id = ' . $rumorId);
            echo json_encode(['success' => true, 'message' => 'Rumor deleted successfully']);
            exit;
        }

        $prepared = chimPrepareRumorEntry(
            $_POST['hold'] ?? '',
            $_POST['type'] ?? '',
            $_POST['content'] ?? '',
            $_POST['length_days'] ?? null
        );
        if (!($prepared['ok'] ?? false)) {
            throw new InvalidArgumentException((string)($prepared['message'] ?? 'Invalid rumor'));
        }

        if ($operation === 'create') {
            $gameRows = $db->fetchAll('SELECT max(gamets) AS last_gamets FROM eventlog');
            $gamets = (int)($gameRows[0]['last_gamets'] ?? 0);
            if ($gamets <= 0) {
                throw new RuntimeException('Could not determine the current game time');
            }
            $id = $db->insertReturningId('rumors', [
                'gamets' => $gamets,
                'ts' => (int)round(microtime(true) * 1000),
                'hold' => $prepared['hold'],
                'content' => $prepared['content'],
                'type' => $prepared['type'],
                'rumor_length_days' => $prepared['rumor_length_days'],
            ]);
            if ($id <= 0) {
                throw new RuntimeException('Failed to create rumor');
            }
            echo json_encode(['success' => true, 'message' => 'Rumor created successfully', 'id' => $id]);
            exit;
        }

        if ($operation === 'update') {
            $rumorId = (int)($_POST['id'] ?? 0);
            if ($rumorId <= 0) {
                throw new InvalidArgumentException('Invalid rumor selected for editing');
            }
            $db->updateRow('rumors', [
                'hold' => $prepared['hold'],
                'content' => $prepared['content'],
                'type' => $prepared['type'],
                'rumor_length_days' => $prepared['rumor_length_days'],
            ], 'id = ' . $rumorId);
            echo json_encode(['success' => true, 'message' => 'Rumor updated successfully', 'id' => $rumorId]);
            exit;
        }

        throw new InvalidArgumentException('Unsupported rumor operation');
    }

    $gameRows = $db->fetchAll('SELECT max(gamets) AS last_gamets FROM eventlog');
    $lastGamets = (int)($gameRows[0]['last_gamets'] ?? 0);
    $gametsPerDay = (int)round(24 / 0.0000024);
    $rows = $db->fetchAll(
        'SELECT id, gamets, hold, content, type, COALESCE(rumor_length_days, 7) AS rumor_length_days
         FROM rumors
         ORDER BY gamets DESC, id DESC'
    );
    $entries = array_map(
        static fn(array $row): array => chimBglRumorApiEntry($row, $lastGamets, $gametsPerDay),
        $rows
    );

    echo json_encode([
        'success' => true,
        'data' => [
            'holds' => chimGetRumorHoldOptions(),
            'current' => array_values(array_filter($entries, static fn(array $entry): bool => $entry['current'])),
            'outdated' => array_values(array_filter($entries, static fn(array $entry): bool => !$entry['current'])),
        ],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $error) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $error->getMessage()]);
} catch (Throwable $error) {
    Logger::error('Background Life rumors API failed: ' . $error->getMessage());
    if (http_response_code() < 400) {
        http_response_code(500);
    }
    echo json_encode(['success' => false, 'error' => $error->getMessage()]);
}
