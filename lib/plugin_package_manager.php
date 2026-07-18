<?php

declare(strict_types=1);

final class DwemerPluginPackageException extends RuntimeException
{
}

final class DwemerPluginPackageManager
{
    public const SCHEMA_VERSION = 3;
    public const MAX_ENTRIES = 5000;
    public const MAX_UNCOMPRESSED_BYTES = 1073741824;
    public const MAX_ARCHIVE_BYTES = 536870912;
    public const MAX_UPLOAD_CHUNK_BYTES = 1572864;

    private string $serverRoot;
    private string $stateRoot;
    private $migrationRunner;

    public function __construct(?string $serverRoot = null, ?string $stateRoot = null, ?callable $migrationRunner = null)
    {
        $this->serverRoot = rtrim($serverRoot ?? dirname(__DIR__), DIRECTORY_SEPARATOR);
        $this->stateRoot = rtrim($stateRoot ?? ($this->serverRoot . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'plugin_packages'), DIRECTORY_SEPARATOR);
        $this->migrationRunner = $migrationRunner;
        $this->ensureStateDirectories();
    }

    public function getBrokerToken(): string
    {
        $path = $this->stateRoot . DIRECTORY_SEPARATOR . 'broker_token';
        if (is_file($path)) {
            $token = trim((string)file_get_contents($path));
            if (preg_match('/^[a-f0-9]{64}$/', $token)) {
                return $token;
            }
        }

        $token = bin2hex(random_bytes(32));
        $this->atomicWrite($path, $token . PHP_EOL, 0600);
        return $token;
    }

    public function authenticateBrokerToken(?string $token): bool
    {
        return is_string($token) && $token !== '' && hash_equals($this->getBrokerToken(), trim($token));
    }

    public function queueArchive(string $sourceArchive, ?string $originalName = null): array
    {
        if (!is_file($sourceArchive) || !is_readable($sourceArchive)) {
            throw new DwemerPluginPackageException('Package archive is missing or unreadable.');
        }
        if (!class_exists(ZipArchive::class)) {
            throw new DwemerPluginPackageException('PHP ZipArchive support is required for unified plugin packages.');
        }

        $jobId = bin2hex(random_bytes(16));
        $archivePath = $this->stateRoot . DIRECTORY_SEPARATOR . 'archives' . DIRECTORY_SEPARATOR . $jobId . '.dwpkg';
        $stageRoot = $this->stateRoot . DIRECTORY_SEPARATOR . 'staging' . DIRECTORY_SEPARATOR . $jobId;

        try {
            if (!copy($sourceArchive, $archivePath)) {
                throw new DwemerPluginPackageException('Could not copy the package into server staging.');
            }

            $manifest = $this->validateAndExtractArchive($archivePath, $stageRoot);
            $hasGame = isset($manifest['components']['game']);
            $hasServer = isset($manifest['components']['server']);
            $now = gmdate(DATE_ATOM);
            $job = [
                'id' => $jobId,
                'status' => $hasGame ? 'awaiting_launcher' : 'activating_server',
                'package_id' => $manifest['package_id'],
                'package_name' => $manifest['name'],
                'version' => $manifest['version'],
                'original_name' => $originalName ?? basename($sourceArchive),
                'archive_path' => $archivePath,
                'stage_root' => $stageRoot,
                'manifest' => $manifest,
                'has_game' => $hasGame,
                'has_server' => $hasServer,
                'created_at' => $now,
                'updated_at' => $now,
                'error' => null,
            ];
            $this->writeJob($job);

            if (!$hasGame) {
                $job = $this->activateAndFinalize($job);
            }

            return $this->publicJob($job);
        } catch (Throwable $error) {
            $this->removeDirectory($stageRoot);
            @unlink($archivePath);
            throw $error;
        }
    }

    public function startChunkedUpload(string $originalName, int $size, int $totalChunks): array
    {
        if (strtolower(pathinfo($originalName, PATHINFO_EXTENSION)) !== 'dwpkg') {
            throw new DwemerPluginPackageException('Unified plugin packages must use the .dwpkg extension.');
        }
        if ($size < 1 || $size > self::MAX_ARCHIVE_BYTES) {
            throw new DwemerPluginPackageException('Package archive size is invalid or exceeds 512 MB.');
        }
        if ($totalChunks < 1 || $totalChunks > 1024) {
            throw new DwemerPluginPackageException('Package upload chunk count is invalid.');
        }
        $uploadId = bin2hex(random_bytes(16));
        $metadata = [
            'id' => $uploadId,
            'original_name' => basename($originalName),
            'size' => $size,
            'total_chunks' => $totalChunks,
            'next_index' => 0,
            'received_bytes' => 0,
            'created_at' => gmdate(DATE_ATOM),
        ];
        $this->atomicWrite($this->uploadMetadataPath($uploadId), json_encode($metadata, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL);
        return ['upload_id' => $uploadId, 'next_index' => 0];
    }

    public function appendUploadChunk(string $uploadId, int $index, string $data): array
    {
        if (strlen($data) < 1 || strlen($data) > self::MAX_UPLOAD_CHUNK_BYTES) {
            throw new DwemerPluginPackageException('Upload chunk is empty or exceeds the chunk size limit.');
        }
        $metadataPath = $this->uploadMetadataPath($uploadId);
        if (!is_file($metadataPath)) {
            throw new DwemerPluginPackageException('Chunked upload was not found.');
        }
        $handle = fopen($metadataPath, 'c+');
        if (!is_resource($handle) || !flock($handle, LOCK_EX)) {
            throw new DwemerPluginPackageException('Could not lock chunked upload.');
        }
        $complete = false;
        try {
            rewind($handle);
            $metadata = json_decode((string)stream_get_contents($handle), true, 32, JSON_THROW_ON_ERROR);
            if ($index !== (int)$metadata['next_index']) {
                throw new DwemerPluginPackageException('Upload chunks must arrive once and in order.');
            }
            $receivedBytes = (int)$metadata['received_bytes'] + strlen($data);
            if ($receivedBytes > (int)$metadata['size']) {
                throw new DwemerPluginPackageException('Upload exceeds its declared archive size.');
            }
            $partPath = $this->uploadPartPath($uploadId);
            if (file_put_contents($partPath, $data, FILE_APPEND | LOCK_EX) === false) {
                throw new DwemerPluginPackageException('Could not write upload chunk.');
            }
            $metadata['received_bytes'] = $receivedBytes;
            $metadata['next_index'] = $index + 1;
            $complete = $metadata['next_index'] === (int)$metadata['total_chunks'];
            if ($complete && $receivedBytes !== (int)$metadata['size']) {
                throw new DwemerPluginPackageException('Completed upload size does not match the declared archive size.');
            }
            rewind($handle);
            ftruncate($handle, 0);
            fwrite($handle, json_encode($metadata, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL);
            fflush($handle);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }

        if (!$complete) {
            return ['complete' => false, 'next_index' => $index + 1];
        }

        try {
            $metadata = $this->readJsonFile($metadataPath);
            $job = $this->queueArchive($this->uploadPartPath($uploadId), (string)$metadata['original_name']);
            return ['complete' => true, 'job' => $job];
        } finally {
            @unlink($metadataPath);
            @unlink($this->uploadPartPath($uploadId));
        }
    }

    public function getJob(string $jobId): array
    {
        return $this->publicJob($this->readJob($jobId));
    }

    public function pendingJobs(): array
    {
        $jobs = [];
        foreach (glob($this->stateRoot . DIRECTORY_SEPARATOR . 'jobs' . DIRECTORY_SEPARATOR . '*.json') ?: [] as $jobPath) {
            $job = $this->readJsonFile($jobPath);
            if (($job['status'] ?? '') === 'installing_game' && isset($job['claimed_at'])) {
                $claimedAt = strtotime((string)$job['claimed_at']);
                if ($claimedAt !== false && $claimedAt < time() - 600) {
                    $job = $this->withLockedJob((string)$job['id'], static function (array $lockedJob): array {
                        if (($lockedJob['status'] ?? '') === 'installing_game') {
                            $lockedJob['status'] = 'awaiting_launcher';
                            $lockedJob['updated_at'] = gmdate(DATE_ATOM);
                            unset($lockedJob['claim_token'], $lockedJob['claimed_at']);
                        }
                        return $lockedJob;
                    });
                }
            }
            if (($job['status'] ?? '') !== 'awaiting_launcher') {
                continue;
            }
            $jobs[] = $this->publicJob($job);
        }
        usort($jobs, static fn(array $left, array $right): int => strcmp((string)$left['created_at'], (string)$right['created_at']));
        return $jobs;
    }

    public function claimJob(string $jobId): array
    {
        return $this->withLockedJob($jobId, function (array $job): array {
            if (($job['status'] ?? '') !== 'awaiting_launcher') {
                throw new DwemerPluginPackageException('Package job is not available for launcher installation.');
            }
            $job['status'] = 'installing_game';
            $job['claim_token'] = bin2hex(random_bytes(24));
            $job['claimed_at'] = gmdate(DATE_ATOM);
            $job['updated_at'] = $job['claimed_at'];
            return $job;
        }, true);
    }

    public function completeGameInstall(string $jobId, string $claimToken, bool $success, array $result = []): array
    {
        $job = $this->withLockedJob($jobId, function (array $job) use ($claimToken, $success, $result): array {
            if (($job['status'] ?? '') !== 'installing_game') {
                throw new DwemerPluginPackageException('Package job is not awaiting a launcher result.');
            }
            if (!isset($job['claim_token']) || !hash_equals((string)$job['claim_token'], $claimToken)) {
                throw new DwemerPluginPackageException('Package claim token is invalid.');
            }
            unset($job['claim_token']);
            $job['game_result'] = $result;
            $job['updated_at'] = gmdate(DATE_ATOM);
            if (!$success) {
                $job['status'] = 'failed';
                $job['error'] = (string)($result['error'] ?? 'The launcher could not install the game component.');
                return $job;
            }
            $job['status'] = !empty($job['has_server']) ? 'activating_server' : 'completed';
            return $job;
        });

        if (($job['status'] ?? '') === 'activating_server') {
            $job = $this->activateAndFinalize($job);
        } elseif (($job['status'] ?? '') === 'completed') {
            $this->recordInstalledPackage($job, []);
            $this->removeDirectory((string)$job['stage_root']);
        }

        return $this->publicJob($job);
    }

    public function archivePathForJob(string $jobId): string
    {
        $job = $this->readJob($jobId);
        $path = (string)($job['archive_path'] ?? '');
        if ($path === '' || !is_file($path)) {
            throw new DwemerPluginPackageException('Package archive is no longer available.');
        }
        return $path;
    }

    public function recordGameRollback(string $jobId, array $result): array
    {
        $job = $this->withLockedJob($jobId, static function (array $job) use ($result): array {
            if (($job['status'] ?? '') !== 'failed') {
                throw new DwemerPluginPackageException('Only failed package jobs can record a game rollback.');
            }
            $job['game_rollback'] = $result;
            $job['updated_at'] = gmdate(DATE_ATOM);
            return $job;
        });
        return $this->publicJob($job);
    }

    public function validateAndExtractArchive(string $archivePath, string $stageRoot): array
    {
        $zip = new ZipArchive();
        $openResult = $zip->open($archivePath);
        if ($openResult !== true) {
            throw new DwemerPluginPackageException('Package is not a readable ZIP archive.');
        }

        try {
            if ($zip->numFiles < 3 || $zip->numFiles > self::MAX_ENTRIES) {
                throw new DwemerPluginPackageException('Package has an invalid number of entries.');
            }

            $totalBytes = 0;
            $entries = [];
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $stat = $zip->statIndex($index);
                if (!is_array($stat) || !isset($stat['name'])) {
                    throw new DwemerPluginPackageException('Package contains an unreadable entry.');
                }
                $name = self::normalizeArchivePath((string)$stat['name']);
                $isDirectory = str_ends_with((string)$stat['name'], '/');
                $totalBytes += (int)($stat['size'] ?? 0);
                if ($totalBytes > self::MAX_UNCOMPRESSED_BYTES) {
                    throw new DwemerPluginPackageException('Package exceeds the uncompressed size limit.');
                }
                if ($this->zipEntryIsSymlink($zip, $index)) {
                    throw new DwemerPluginPackageException("Package entry '{$name}' is a symbolic link.");
                }
                if (isset($entries[$name])) {
                    throw new DwemerPluginPackageException("Package contains duplicate path '{$name}'.");
                }
                $entries[$name] = ['index' => $index, 'directory' => $isDirectory];
            }

            foreach (['manifest.json', 'checksums.sha256'] as $required) {
                if (!isset($entries[$required]) || $entries[$required]['directory']) {
                    throw new DwemerPluginPackageException("Package is missing {$required}.");
                }
            }

            $manifestJson = $zip->getFromIndex($entries['manifest.json']['index']);
            $manifest = json_decode((string)$manifestJson, true, 64, JSON_THROW_ON_ERROR);
            $this->validateManifest($manifest, $entries);

            $this->removeDirectory($stageRoot);
            $this->ensureDirectory($stageRoot);
            foreach ($entries as $name => $entry) {
                $destination = $stageRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name);
                if ($entry['directory']) {
                    $this->ensureDirectory($destination);
                    continue;
                }
                $this->ensureDirectory(dirname($destination));
                $input = $zip->getStream((string)$zip->getNameIndex($entry['index']));
                if (!is_resource($input)) {
                    throw new DwemerPluginPackageException("Could not read package entry '{$name}'.");
                }
                $output = fopen($destination, 'wb');
                if (!is_resource($output)) {
                    fclose($input);
                    throw new DwemerPluginPackageException("Could not stage package entry '{$name}'.");
                }
                stream_copy_to_stream($input, $output);
                fclose($input);
                fclose($output);
            }

            $this->verifyChecksums($stageRoot, $entries);
            return $manifest;
        } catch (JsonException $error) {
            throw new DwemerPluginPackageException('manifest.json is not valid JSON.', 0, $error);
        } finally {
            $zip->close();
        }
    }

    public static function normalizeArchivePath(string $path): string
    {
        if ($path === '' || str_contains($path, "\0") || str_contains($path, '\\')) {
            throw new DwemerPluginPackageException('Package contains an invalid archive path.');
        }
        if ($path[0] === '/' || preg_match('/^[A-Za-z]:/', $path)) {
            throw new DwemerPluginPackageException("Package path '{$path}' is absolute.");
        }
        $trimmed = rtrim($path, '/');
        if ($trimmed === '') {
            throw new DwemerPluginPackageException('Package contains an empty archive path.');
        }
        foreach (explode('/', $trimmed) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new DwemerPluginPackageException("Package path '{$path}' is unsafe.");
            }
        }
        return $trimmed;
    }

    private function validateManifest(array $manifest, array $entries): void
    {
        if (($manifest['schema_version'] ?? null) !== self::SCHEMA_VERSION) {
            throw new DwemerPluginPackageException('Unsupported package schema version.');
        }
        foreach (['package_id', 'name', 'version', 'components'] as $field) {
            if (!isset($manifest[$field]) || ($field !== 'components' && trim((string)$manifest[$field]) === '')) {
                throw new DwemerPluginPackageException("Package manifest is missing '{$field}'.");
            }
        }
        if (!preg_match('/^[a-z0-9][a-z0-9._-]{1,63}$/', (string)$manifest['package_id'])) {
            throw new DwemerPluginPackageException('package_id must be a stable lowercase identifier.');
        }
        if (!is_array($manifest['components']) || empty($manifest['components'])) {
            throw new DwemerPluginPackageException('Package must contain at least one component.');
        }

        $components = $manifest['components'];
        if (isset($components['server'])) {
            $server = $components['server'];
            if (!is_array($server) || !preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,63}$/', (string)($server['install_name'] ?? ''))) {
                throw new DwemerPluginPackageException('Server component install_name is invalid.');
            }
            $this->requirePayloadPrefix($entries, 'server/');
            $this->validateMutablePaths($server['mutable_paths'] ?? []);
        }

        if (isset($components['game'])) {
            $game = $components['game'];
            if (!is_array($game)) {
                throw new DwemerPluginPackageException('Game component mod_name is required.');
            }
            $this->validateGameModName((string)($game['mod_name'] ?? ''));
            $variants = $game['variants'] ?? null;
            if (!is_array($variants) || empty($variants)) {
                throw new DwemerPluginPackageException('Game component must declare at least one variant.');
            }
            foreach ($variants as $variant => $prefix) {
                if (!in_array($variant, ['skyrim-se', 'skyrim-vr'], true)) {
                    throw new DwemerPluginPackageException("Unsupported game variant '{$variant}'.");
                }
                $normalizedPrefix = self::normalizeArchivePath((string)$prefix) . '/';
                if (!str_starts_with($normalizedPrefix, 'game/')) {
                    throw new DwemerPluginPackageException('Game payload paths must be below game/.');
                }
                $this->requirePayloadPrefix($entries, $normalizedPrefix);
            }
            $this->validateMutablePaths($game['mutable_paths'] ?? []);
        }

        foreach (array_keys($components) as $componentName) {
            if (!in_array($componentName, ['server', 'game'], true)) {
                throw new DwemerPluginPackageException("Unsupported component '{$componentName}'.");
            }
        }
    }

    private function requirePayloadPrefix(array $entries, string $prefix): void
    {
        foreach ($entries as $path => $entry) {
            if (!$entry['directory'] && str_starts_with($path, $prefix)) {
                return;
            }
        }
        throw new DwemerPluginPackageException("Package payload '{$prefix}' is empty.");
    }

    private function validateMutablePaths(mixed $paths): void
    {
        if (!is_array($paths)) {
            throw new DwemerPluginPackageException('mutable_paths must be an array.');
        }
        foreach ($paths as $path) {
            self::normalizeArchivePath((string)$path);
        }
    }

    private function validateGameModName(string $modName): void
    {
        if ($modName === '' || trim($modName) !== $modName || $modName === '.' || $modName === '..') {
            throw new DwemerPluginPackageException('Game component mod_name is not a safe MO2 folder name.');
        }
        if (preg_match('/[\\x00-\\x1F\\\\\\/:*?"<>|]/', $modName) || str_ends_with($modName, '.')) {
            throw new DwemerPluginPackageException('Game component mod_name contains invalid filename characters.');
        }
        $deviceName = strtoupper((string)strtok($modName, '.'));
        if (preg_match('/^(CON|PRN|AUX|NUL|COM[1-9]|LPT[1-9])$/', $deviceName)) {
            throw new DwemerPluginPackageException('Game component mod_name is reserved by Windows.');
        }
    }

    private function verifyChecksums(string $stageRoot, array $entries): void
    {
        $checksumPath = $stageRoot . DIRECTORY_SEPARATOR . 'checksums.sha256';
        $lines = file($checksumPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) {
            throw new DwemerPluginPackageException('Could not read checksums.sha256.');
        }
        $expected = [];
        foreach ($lines as $line) {
            if (!preg_match('/^([a-fA-F0-9]{64})\s+\*?(.+)$/', trim($line), $match)) {
                throw new DwemerPluginPackageException('checksums.sha256 contains an invalid line.');
            }
            $path = self::normalizeArchivePath($match[2]);
            if ($path === 'checksums.sha256' || isset($expected[$path])) {
                throw new DwemerPluginPackageException("Duplicate or recursive checksum entry '{$path}'.");
            }
            $expected[$path] = strtolower($match[1]);
        }

        foreach ($entries as $path => $entry) {
            if ($entry['directory'] || $path === 'checksums.sha256') {
                continue;
            }
            if (!isset($expected[$path])) {
                throw new DwemerPluginPackageException("Package file '{$path}' is not covered by checksums.sha256.");
            }
            $filePath = $stageRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
            if (!hash_equals($expected[$path], hash_file('sha256', $filePath))) {
                throw new DwemerPluginPackageException("Checksum mismatch for '{$path}'.");
            }
            unset($expected[$path]);
        }
        if (!empty($expected)) {
            throw new DwemerPluginPackageException("Checksum references missing file '" . array_key_first($expected) . "'.");
        }
    }

    private function activateAndFinalize(array $job): array
    {
        try {
            $serverState = !empty($job['has_server']) ? $this->activateServerComponent($job) : [];
            $job['status'] = 'completed';
            $job['updated_at'] = gmdate(DATE_ATOM);
            $job['error'] = null;
            $this->writeJob($job);
            $this->recordInstalledPackage($job, $serverState);
            $this->removeDirectory((string)$job['stage_root']);
            return $job;
        } catch (Throwable $error) {
            $job['status'] = 'failed';
            $job['error'] = $error->getMessage();
            $job['updated_at'] = gmdate(DATE_ATOM);
            $this->writeJob($job);
            return $job;
        }
    }

    private function activateServerComponent(array $job): array
    {
        $component = $job['manifest']['components']['server'];
        $installName = (string)$component['install_name'];
        $source = (string)$job['stage_root'] . DIRECTORY_SEPARATOR . 'server';
        $target = $this->serverRoot . DIRECTORY_SEPARATOR . 'ext' . DIRECTORY_SEPARATOR . $installName;
        $backup = $this->stateRoot . DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR . $job['id'] . DIRECTORY_SEPARATOR . $installName;
        $failed = $this->stateRoot . DIRECTORY_SEPARATOR . 'failed' . DIRECTORY_SEPARATOR . $job['id'] . DIRECTORY_SEPARATOR . $installName;

        if (!is_dir($source)) {
            throw new DwemerPluginPackageException('Staged server component is missing.');
        }
        $this->ensureDirectory(dirname($target));
        $this->ensureDirectory(dirname($backup));
        $this->preserveMutablePaths($target, $source, $component['mutable_paths'] ?? []);

        $hadPrevious = is_dir($target);
        if ($hadPrevious && !rename($target, $backup)) {
            throw new DwemerPluginPackageException("Could not back up existing server extension '{$installName}'.");
        }

        try {
            if (!rename($source, $target)) {
                throw new DwemerPluginPackageException("Could not activate server extension '{$installName}'.");
            }
            $this->runMigrations($target, (string)$job['package_id']);
        } catch (Throwable $error) {
            if (is_dir($target)) {
                $this->ensureDirectory(dirname($failed));
                @rename($target, $failed);
                if (is_dir($target)) {
                    $this->removeDirectory($target);
                }
            }
            if ($hadPrevious && is_dir($backup)) {
                @rename($backup, $target);
            }
            throw new DwemerPluginPackageException('Server activation rolled back: ' . $error->getMessage(), 0, $error);
        }

        return [
            'install_name' => $installName,
            'path' => $target,
            'backup_path' => $hadPrevious ? $backup : null,
            'files' => $this->buildFileLedger($target),
        ];
    }

    private function preserveMutablePaths(string $oldRoot, string $newRoot, array $mutablePaths): void
    {
        if (!is_dir($oldRoot)) {
            return;
        }
        foreach ($mutablePaths as $relativePath) {
            $normalized = self::normalizeArchivePath((string)$relativePath);
            $oldPath = $oldRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalized);
            $newPath = $newRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalized);
            if (is_file($oldPath)) {
                $this->ensureDirectory(dirname($newPath));
                if (!copy($oldPath, $newPath)) {
                    throw new DwemerPluginPackageException("Could not preserve mutable file '{$normalized}'.");
                }
            } elseif (is_dir($oldPath)) {
                $this->copyDirectory($oldPath, $newPath);
            }
        }
    }

    private function runMigrations(string $targetDir, string $packageId): void
    {
        $migrations = glob($targetDir . DIRECTORY_SEPARATOR . 'migrations' . DIRECTORY_SEPARATOR . '*.sql') ?: [];
        sort($migrations, SORT_STRING);
        if (empty($migrations)) {
            return;
        }
        if (is_callable($this->migrationRunner)) {
            ($this->migrationRunner)($targetDir, $packageId, $migrations);
            return;
        }
        if (!function_exists('pg_connect')) {
            throw new DwemerPluginPackageException('PostgreSQL support is required to run plugin migrations.');
        }

        $connection = @pg_connect('host=localhost port=5432 dbname=dwemer user=dwemer password=dwemer');
        if (!$connection) {
            throw new DwemerPluginPackageException('Could not connect to PostgreSQL for plugin migrations.');
        }
        try {
            if (!pg_query($connection, 'BEGIN')) {
                throw new DwemerPluginPackageException('Could not start plugin migration transaction.');
            }
            $setupSql = 'CREATE SCHEMA IF NOT EXISTS plugins; CREATE TABLE IF NOT EXISTS plugins.plugin_migrations (plugin_name VARCHAR(255), migration_name VARCHAR(255), executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (plugin_name, migration_name));';
            if (!pg_query($connection, $setupSql)) {
                throw new DwemerPluginPackageException('Could not initialize plugin migration tracking.');
            }
            foreach ($migrations as $migrationPath) {
                $migrationName = basename($migrationPath);
                $existing = pg_query_params($connection, 'SELECT 1 FROM plugins.plugin_migrations WHERE plugin_name = $1 AND migration_name = $2', [$packageId, $migrationName]);
                if ($existing && pg_num_rows($existing) > 0) {
                    continue;
                }
                $sql = file_get_contents($migrationPath);
                if ($sql === false || !pg_query($connection, $sql)) {
                    throw new DwemerPluginPackageException("Migration '{$migrationName}' failed: " . pg_last_error($connection));
                }
                if (!pg_query_params($connection, 'INSERT INTO plugins.plugin_migrations (plugin_name, migration_name) VALUES ($1, $2)', [$packageId, $migrationName])) {
                    throw new DwemerPluginPackageException("Could not record migration '{$migrationName}'.");
                }
            }
            if (!pg_query($connection, 'COMMIT')) {
                throw new DwemerPluginPackageException('Could not commit plugin migrations.');
            }
        } catch (Throwable $error) {
            @pg_query($connection, 'ROLLBACK');
            throw $error;
        } finally {
            pg_close($connection);
        }
    }

    private function recordInstalledPackage(array $job, array $serverState): void
    {
        $state = [
            'package_id' => $job['package_id'],
            'name' => $job['package_name'],
            'version' => $job['version'],
            'installed_at' => gmdate(DATE_ATOM),
            'job_id' => $job['id'],
            'server' => $serverState,
            'game' => $job['game_result'] ?? null,
            'manifest' => $job['manifest'],
        ];
        $path = $this->stateRoot . DIRECTORY_SEPARATOR . 'packages' . DIRECTORY_SEPARATOR . $job['package_id'] . '.json';
        $this->atomicWrite($path, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL);
    }

    private function buildFileLedger(string $root): array
    {
        $ledger = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }
            $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($file->getPathname(), strlen($root) + 1));
            $ledger[$relative] = hash_file('sha256', $file->getPathname());
        }
        ksort($ledger, SORT_STRING);
        return $ledger;
    }

    private function publicJob(array $job, bool $includeClaim = false): array
    {
        $public = [
            'id' => $job['id'],
            'status' => $job['status'],
            'package_id' => $job['package_id'],
            'package_name' => $job['package_name'],
            'version' => $job['version'],
            'has_game' => (bool)$job['has_game'],
            'has_server' => (bool)$job['has_server'],
            'game' => $job['manifest']['components']['game'] ?? null,
            'created_at' => $job['created_at'],
            'updated_at' => $job['updated_at'],
            'error' => $job['error'] ?? null,
            'game_result' => $job['game_result'] ?? null,
            'game_rollback' => $job['game_rollback'] ?? null,
        ];
        if ($includeClaim && isset($job['claim_token'])) {
            $public['claim_token'] = $job['claim_token'];
        }
        return $public;
    }

    private function withLockedJob(string $jobId, callable $callback, bool $includeClaim = false): array
    {
        $path = $this->jobPath($jobId);
        if (!is_file($path)) {
            throw new DwemerPluginPackageException('Package job was not found.');
        }
        $handle = fopen($path, 'c+');
        if (!is_resource($handle) || !flock($handle, LOCK_EX)) {
            throw new DwemerPluginPackageException('Could not lock package job.');
        }
        try {
            rewind($handle);
            $contents = stream_get_contents($handle);
            $job = json_decode((string)$contents, true, 64, JSON_THROW_ON_ERROR);
            $job = $callback($job);
            rewind($handle);
            ftruncate($handle, 0);
            fwrite($handle, json_encode($job, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL);
            fflush($handle);
            return $includeClaim ? $this->publicJob($job, true) : $job;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private function writeJob(array $job): void
    {
        $this->atomicWrite($this->jobPath((string)$job['id']), json_encode($job, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL);
    }

    private function readJob(string $jobId): array
    {
        $path = $this->jobPath($jobId);
        if (!is_file($path)) {
            throw new DwemerPluginPackageException('Package job was not found.');
        }
        return $this->readJsonFile($path);
    }

    private function jobPath(string $jobId): string
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $jobId)) {
            throw new DwemerPluginPackageException('Package job ID is invalid.');
        }
        return $this->stateRoot . DIRECTORY_SEPARATOR . 'jobs' . DIRECTORY_SEPARATOR . $jobId . '.json';
    }

    private function readJsonFile(string $path): array
    {
        $contents = @file_get_contents($path);
        if ($contents === false) {
            throw new DwemerPluginPackageException('Could not read package state.');
        }
        try {
            $decoded = json_decode($contents, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new DwemerPluginPackageException('Package state is corrupted.', 0, $error);
        }
        if (!is_array($decoded)) {
            throw new DwemerPluginPackageException('Package state is invalid.');
        }
        return $decoded;
    }

    private function zipEntryIsSymlink(ZipArchive $zip, int $index): bool
    {
        $opsys = 0;
        $attributes = 0;
        if (!$zip->getExternalAttributesIndex($index, $opsys, $attributes) || $opsys !== ZipArchive::OPSYS_UNIX) {
            return false;
        }
        return (($attributes >> 16) & 0170000) === 0120000;
    }

    private function ensureStateDirectories(): void
    {
        foreach (['archives', 'backups', 'failed', 'jobs', 'packages', 'staging', 'uploads'] as $directory) {
            $this->ensureDirectory($this->stateRoot . DIRECTORY_SEPARATOR . $directory);
        }
    }

    private function uploadMetadataPath(string $uploadId): string
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $uploadId)) {
            throw new DwemerPluginPackageException('Chunked upload ID is invalid.');
        }
        return $this->stateRoot . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $uploadId . '.json';
    }

    private function uploadPartPath(string $uploadId): string
    {
        $this->uploadMetadataPath($uploadId);
        return $this->stateRoot . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $uploadId . '.part';
    }

    private function ensureDirectory(string $path): void
    {
        if (!is_dir($path) && !mkdir($path, 0770, true) && !is_dir($path)) {
            throw new DwemerPluginPackageException("Could not create directory '{$path}'.");
        }
    }

    private function atomicWrite(string $path, string $contents, int $mode = 0660): void
    {
        $this->ensureDirectory(dirname($path));
        $temporary = $path . '.tmp-' . bin2hex(random_bytes(6));
        if (file_put_contents($temporary, $contents, LOCK_EX) === false) {
            throw new DwemerPluginPackageException("Could not write '{$path}'.");
        }
        @chmod($temporary, $mode);
        if (!rename($temporary, $path)) {
            @unlink($temporary);
            throw new DwemerPluginPackageException("Could not publish '{$path}'.");
        }
    }

    private function copyDirectory(string $source, string $destination): void
    {
        $this->ensureDirectory($destination);
        foreach (new DirectoryIterator($source) as $entry) {
            if ($entry->isDot()) {
                continue;
            }
            $target = $destination . DIRECTORY_SEPARATOR . $entry->getFilename();
            if ($entry->isDir() && !$entry->isLink()) {
                $this->copyDirectory($entry->getPathname(), $target);
            } elseif ($entry->isFile()) {
                $this->ensureDirectory(dirname($target));
                if (!copy($entry->getPathname(), $target)) {
                    throw new DwemerPluginPackageException("Could not preserve '{$entry->getFilename()}'.");
                }
            }
        }
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $entry) {
            if ($entry->isDir() && !$entry->isLink()) {
                @rmdir($entry->getPathname());
            } else {
                @unlink($entry->getPathname());
            }
        }
        @rmdir($path);
    }
}
