<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/lib/plugin_package_manager.php';

header('Cache-Control: no-store');

function pluginPackageJson(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    exit;
}

function pluginPackageInput(): array
{
    $input = json_decode((string)file_get_contents('php://input'), true);
    return is_array($input) ? $input : [];
}

try {
    $manager = new DwemerPluginPackageManager();
    $action = (string)($_GET['action'] ?? $_POST['action'] ?? '');

    if ($action === 'probe' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = pluginPackageInput();
        pluginPackageJson([
            'ok' => true,
            'package' => $manager->probe((string)($input['name'] ?? ''), (string)($input['version'] ?? '')),
        ]);
    }

    if ($action === 'start-upload' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = pluginPackageInput();
        $upload = $manager->startChunkedUpload(
            (string)($input['name'] ?? ''),
            (string)($input['version'] ?? ''),
            (string)($input['archive_name'] ?? ''),
            (int)($input['size'] ?? 0),
            (int)($input['total_chunks'] ?? 0)
        );
        pluginPackageJson(['ok' => true, 'upload' => $upload], 201);
    }

    if ($action === 'upload-chunk' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $upload = $manager->appendUploadChunk(
            (string)($_GET['upload_id'] ?? ''),
            (int)($_GET['index'] ?? -1),
            (string)file_get_contents('php://input')
        );
        pluginPackageJson(['ok' => true, 'upload' => $upload], $upload['complete'] ? 201 : 200);
    }

    if ($action === 'status' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        pluginPackageJson(['ok' => true, 'job' => $manager->getJob((string)($_GET['job_id'] ?? ''))]);
    }

    if ($action === 'packages' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        pluginPackageJson(['ok' => true, 'packages' => $manager->installedPackages()]);
    }

    pluginPackageJson(['ok' => false, 'error' => 'Unsupported package API action.'], 404);
} catch (DwemerPluginPackageException $error) {
    pluginPackageJson(['ok' => false, 'error' => $error->getMessage()], 400);
} catch (Throwable $error) {
    error_log('[PLUGIN-PACKAGE] ' . $error->getMessage());
    pluginPackageJson(['ok' => false, 'error' => 'Unexpected package service failure.'], 500);
}
