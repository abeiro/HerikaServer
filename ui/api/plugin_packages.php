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

function pluginPackageRequireBroker(DwemerPluginPackageManager $manager): void
{
    $token = $_SERVER['HTTP_X_DWEMER_PLUGIN_TOKEN'] ?? null;
    if (!$manager->authenticateBrokerToken(is_string($token) ? $token : null)) {
        pluginPackageJson(['ok' => false, 'error' => 'Launcher authentication failed.'], 401);
    }
}

try {
    $manager = new DwemerPluginPackageManager();
    $action = (string)($_GET['action'] ?? $_POST['action'] ?? '');

    if ($action === 'upload' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_FILES['package']) || !is_array($_FILES['package'])) {
            pluginPackageJson(['ok' => false, 'error' => 'No .dwpkg file was uploaded.'], 400);
        }
        $upload = $_FILES['package'];
        if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            pluginPackageJson(['ok' => false, 'error' => 'Package upload failed with code ' . (int)$upload['error'] . '.'], 400);
        }
        $name = (string)($upload['name'] ?? 'package.dwpkg');
        if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'dwpkg') {
            pluginPackageJson(['ok' => false, 'error' => 'Unified plugin packages must use the .dwpkg extension.'], 400);
        }
        $job = $manager->queueArchive((string)$upload['tmp_name'], $name);
        pluginPackageJson(['ok' => true, 'job' => $job], 202);
    }

    if ($action === 'start-upload' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = pluginPackageInput();
        $upload = $manager->startChunkedUpload(
            (string)($input['name'] ?? ''),
            (int)($input['size'] ?? 0),
            (int)($input['total_chunks'] ?? 0)
        );
        pluginPackageJson(['ok' => true, 'upload' => $upload], 201);
    }

    if ($action === 'upload-chunk' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = (string)file_get_contents('php://input');
        $upload = $manager->appendUploadChunk(
            (string)($_GET['upload_id'] ?? ''),
            (int)($_GET['index'] ?? -1),
            $data
        );
        pluginPackageJson(['ok' => true, 'upload' => $upload], $upload['complete'] ? 202 : 200);
    }

    if ($action === 'status' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        pluginPackageJson(['ok' => true, 'job' => $manager->getJob((string)($_GET['job_id'] ?? ''))]);
    }

    if ($action === 'pending' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        pluginPackageRequireBroker($manager);
        pluginPackageJson(['ok' => true, 'jobs' => $manager->pendingJobs()]);
    }

    if ($action === 'claim' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        pluginPackageRequireBroker($manager);
        $input = pluginPackageInput();
        pluginPackageJson(['ok' => true, 'job' => $manager->claimJob((string)($input['job_id'] ?? ''))]);
    }

    if ($action === 'download' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        pluginPackageRequireBroker($manager);
        $path = $manager->archivePathForJob((string)($_GET['job_id'] ?? ''));
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="plugin-package.dwpkg"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    if ($action === 'complete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        pluginPackageRequireBroker($manager);
        $input = pluginPackageInput();
        $job = $manager->completeGameInstall(
            (string)($input['job_id'] ?? ''),
            (string)($input['claim_token'] ?? ''),
            (bool)($input['success'] ?? false),
            is_array($input['result'] ?? null) ? $input['result'] : []
        );
        pluginPackageJson(['ok' => true, 'job' => $job]);
    }

    if ($action === 'rollback' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        pluginPackageRequireBroker($manager);
        $input = pluginPackageInput();
        $job = $manager->recordGameRollback(
            (string)($input['job_id'] ?? ''),
            is_array($input['result'] ?? null) ? $input['result'] : []
        );
        pluginPackageJson(['ok' => true, 'job' => $job]);
    }

    pluginPackageJson(['ok' => false, 'error' => 'Unsupported package API action.'], 404);
} catch (DwemerPluginPackageException $error) {
    pluginPackageJson(['ok' => false, 'error' => $error->getMessage()], 400);
} catch (Throwable $error) {
    error_log('[PLUGIN-PACKAGE] ' . $error->getMessage());
    pluginPackageJson(['ok' => false, 'error' => 'Unexpected package service failure.'], 500);
}
