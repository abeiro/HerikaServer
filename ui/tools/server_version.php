<?php

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$versionFile = dirname(__DIR__, 2) . '/.version_number.txt';
$serverVersionRaw = '2.8.2'; // Keep fallback aligned with navbar.php

if (file_exists($versionFile)) {
    $versionContent = trim((string) file_get_contents($versionFile));
    if ($versionContent !== '') {
        $serverVersionRaw = $versionContent;
    }
}

echo json_encode([
    'serverVersion' => $serverVersionRaw
]);

