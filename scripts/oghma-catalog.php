<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'runtime_bootstrap.php';
chimRuntimeBootstrapIfNeeded($root, [
    'load_general_settings' => false,
    'load_stt_connector' => false,
    'load_itt_connector' => false,
    'load_tts_connector' => false,
]);
require_once $root . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'oghma_catalog.php';

$command = strtolower(trim((string) ($argv[1] ?? 'status')));
$manager = new ChimOghmaCatalogManager($GLOBALS['db'], $root);

try {
    $result = match ($command) {
        'plan' => $manager->plan($argv[2] ?? $manager->activePackagePath()),
        'sync', 'provision' => $manager->synchronize($argv[2] ?? $manager->activePackagePath()),
        'status' => $manager->status(),
        default => throw new InvalidArgumentException('Usage: php scripts/oghma-catalog.php plan|sync|status [package-path]'),
    };
    if ($command === 'plan') {
        $result = [
            'catalog_version' => $result['catalog_version'],
            'row_count' => count($result['articles']),
            'articles_sha256' => $result['articles_sha256'],
            'manifest_sha256' => $result['manifest_sha256'],
        ];
    }
    echo json_encode($result, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, '[OghmaCatalog] ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
