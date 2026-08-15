<?php

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}
require_once __DIR__ . DIRECTORY_SEPARATOR . 'rumor_service.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'ok' => false,
        'message' => 'POST required.',
    ]);
    exit;
}

$host = 'localhost';
$port = '5432';
$dbname = 'dwemer';
$schema = 'public';
$username = 'dwemer';
$password = 'dwemer';

$adminConn = @pg_connect("host={$host} port={$port} dbname={$dbname} user={$username} password={$password}");
if (!$adminConn) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => 'Database connection failed.',
    ]);
    exit;
}

$result = chimCreateRumorEntry(
    $adminConn,
    $_POST['rumor_hold'] ?? '',
    $_POST['rumor_type'] ?? '',
    $_POST['rumor_content'] ?? '',
    $_POST['rumor_length_days'] ?? ''
);

if (!($result['ok'] ?? false)) {
    $errorType = $result['error_type'] ?? 'server';
    http_response_code($errorType === 'validation' ? 400 : 500);
}

echo json_encode($result);
