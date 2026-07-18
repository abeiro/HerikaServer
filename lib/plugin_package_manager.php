<?php

declare(strict_types=1);

final class DwemerPluginPackageException extends RuntimeException
{
}

final class DwemerPluginPackageManager
{
    private const SUPPORTED_ARCHIVE_EXTENSIONS = ['dwpkg', 'zip'];

    public const SCHEMA_VERSION = 4;
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
        $this->stateRoot = rtrim(
            $stateRoot ?? ($this->serverRoot . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'plugin_packages'),
            DIRECTORY_SEPARATOR
        );
        $this->migrationRunner = $migrationRunner;
        $this->ensureStateDirectories();
    }

    public function probe(string $name, string $version): array
    {
        $this->validatePluginName($name);
        $this->validateVersion($version);
        $installed = $this->installedPackage($name);
        $current = is_array($installed) && hash_equals($this->canonicalName((string)$installed['name']), $this->canonicalName($name));
        $sameVersion = $current && hash_equals((string)$installed['version'], $version);

        return [
            'name' => $name,
            'requested_version' => $version,
            'installed_version' => $current ? (string)$installed['version'] : null,
            'upload_required' => !$sameVersion,
            'reason' => $sameVersion ? 'current' : ($current ? 'version_changed' : 'not_installed'),
        ];
    }

    public function installedPackages(): array
    {
        $packages = [];
        foreach (glob($this->stateRoot . DIRECTORY_SEPARATOR . 'packages' . DIRECTORY_SEPARATOR . '*.json') ?: [] as $path) {
            try {
                $package = $this->readJsonFile($path);
                if (($package['manifest']['schema_version'] ?? null) !== self::SCHEMA_VERSION) {
                    continue;
                }
                $this->validatePluginName((string)($package['name'] ?? ''));
                $this->validateVersion((string)($package['version'] ?? ''));
                $packages[] = $package;
            } catch (Throwable) {
                continue;
            }
        }
        usort($packages, static fn(array $left, array $right): int => strcasecmp((string)$left['name'], (string)$right['name']));
        return $packages;
    }

    public function installArchive(
        string $sourceArchive,
        ?string $originalName = null,
        ?string $expectedName = null,
        ?string $expectedVersion = null
    ): array {
        if (!is_file($sourceArchive) || !is_readable($sourceArchive)) {
            throw new DwemerPluginPackageException('Package archive is missing or unreadable.');
        }
        if (!class_exists(ZipArchive::class)) {
            throw new DwemerPluginPackageException('PHP ZipArchive support is required for server plugin packages.');
        }
        if (filesize($sourceArchive) > self::MAX_ARCHIVE_BYTES) {
            throw new DwemerPluginPackageException('Package archive exceeds 512 MB.');
        }

        $jobId = bin2hex(random_bytes(16));
        $archivePath = $this->stateRoot . DIRECTORY_SEPARATOR . 'archives' . DIRECTORY_SEPARATOR . $jobId . '.dwpkg';
        $stageRoot = $this->stateRoot . DIRECTORY_SEPARATOR . 'staging' . DIRECTORY_SEPARATOR . $jobId;

        try {
            if (!copy($sourceArchive, $archivePath)) {
                throw new DwemerPluginPackageException('Could not copy the package into server staging.');
            }
            $manifest = $this->validateAndExtractArchive($archivePath, $stageRoot);
            if ($expectedName !== null && $this->canonicalName($manifest['name']) !== $this->canonicalName($expectedName)) {
                throw new DwemerPluginPackageException('Uploaded package name does not match its game-side plugin folder.');
            }
            if ($expectedVersion !== null && !hash_equals((string)$manifest['version'], $expectedVersion)) {
                throw new DwemerPluginPackageException('Uploaded package version does not match its game-side filename.');
            }

            $now = gmdate(DATE_ATOM);
            $job = [
                'id' => $jobId,
                'status' => 'activating_server',
                'name' => (string)$manifest['name'],
                'version' => (string)$manifest['version'],
                'original_name' => $originalName ?? basename($sourceArchive),
                'archive_path' => $archivePath,
                'archive_sha256' => hash_file('sha256', $archivePath),
                'stage_root' => $stageRoot,
                'manifest' => $manifest,
                'created_at' => $now,
                'updated_at' => $now,
                'error' => null,
            ];
            $this->writeJob($job);
            return $this->activateAndFinalize($job);
        } catch (Throwable $error) {
            $this->removeDirectory($stageRoot);
            @unlink($archivePath);
            throw $error;
        }
    }

    public function startChunkedUpload(
        string $name,
        string $version,
        string $originalName,
        int $size,
        int $totalChunks
    ): array {
        $this->validatePluginName($name);
        $this->validateVersion($version);
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($extension, self::SUPPORTED_ARCHIVE_EXTENSIONS, true)) {
            throw new DwemerPluginPackageException('Server plugin packages must use the .dwpkg or .zip extension.');
        }
        if ($size < 1 || $size > self::MAX_ARCHIVE_BYTES) {
            throw new DwemerPluginPackageException('Package archive size is invalid or exceeds 512 MB.');
        }
        if ($totalChunks < 1 || $totalChunks > 4096) {
            throw new DwemerPluginPackageException('Package upload chunk count is invalid.');
        }

        $uploadId = bin2hex(random_bytes(16));
        $metadata = [
            'id' => $uploadId,
            'name' => $name,
            'version' => $version,
            'original_name' => basename($originalName),
            'size' => $size,
            'total_chunks' => $totalChunks,
            'next_index' => 0,
            'received_bytes' => 0,
            'created_at' => gmdate(DATE_ATOM),
        ];
        $this->atomicWrite(
            $this->uploadMetadataPath($uploadId),
            json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL
        );
        return ['upload_id' => $uploadId, 'next_index' => 0];
    }

    public function appendUploadChunk(string $uploadId, int $index, string $data): array
    {
        $length = strlen($data);
        if ($length < 1 || $length > self::MAX_UPLOAD_CHUNK_BYTES) {
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
            $received = (int)$metadata['received_bytes'] + $length;
            if ($received > (int)$metadata['size']) {
                throw new DwemerPluginPackageException('Upload exceeds its declared archive size.');
            }
            if (file_put_contents($this->uploadPartPath($uploadId), $data, FILE_APPEND | LOCK_EX) === false) {
                throw new DwemerPluginPackageException('Could not write upload chunk.');
            }
            $metadata['received_bytes'] = $received;
            $metadata['next_index'] = $index + 1;
            $complete = $metadata['next_index'] === (int)$metadata['total_chunks'];
            if ($complete && $received !== (int)$metadata['size']) {
                throw new DwemerPluginPackageException('Completed upload size does not match the declared archive size.');
            }
            rewind($handle);
            ftruncate($handle, 0);
            fwrite($handle, json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL);
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
            $job = $this->installArchive(
                $this->uploadPartPath($uploadId),
                (string)$metadata['original_name'],
                (string)$metadata['name'],
                (string)$metadata['version']
            );
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

    public function validateAndExtractArchive(string $archivePath, string $stageRoot): array
    {
        $zip = new ZipArchive();
        if ($zip->open($archivePath) !== true) {
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
                $directory = str_ends_with((string)$stat['name'], '/');
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
                $entries[$name] = ['index' => $index, 'directory' => $directory];
            }
            foreach (['manifest.json', 'checksums.sha256'] as $required) {
                if (!isset($entries[$required]) || $entries[$required]['directory']) {
                    throw new DwemerPluginPackageException("Package is missing {$required}.");
                }
            }

            $manifest = json_decode((string)$zip->getFromIndex($entries['manifest.json']['index']), true, 64, JSON_THROW_ON_ERROR);
            if (!is_array($manifest)) {
                throw new DwemerPluginPackageException('manifest.json must contain an object.');
            }
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
                $output = fopen($destination, 'wb');
                if (!is_resource($input) || !is_resource($output)) {
                    if (is_resource($input)) fclose($input);
                    if (is_resource($output)) fclose($output);
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
        foreach (['name', 'version', 'server'] as $field) {
            if (!array_key_exists($field, $manifest)) {
                throw new DwemerPluginPackageException("Package manifest is missing '{$field}'.");
            }
        }
        $this->validatePluginName((string)$manifest['name']);
        $this->validateVersion((string)$manifest['version']);
        if (!is_array($manifest['server'])) {
            throw new DwemerPluginPackageException('Package server settings must be an object.');
        }
        $this->validateMutablePaths($manifest['server']['mutable_paths'] ?? []);
        $this->requirePayloadPrefix($entries, 'server/');
        foreach ($entries as $path => $entry) {
            if ($entry['directory'] || in_array($path, ['manifest.json', 'checksums.sha256'], true)) {
                continue;
            }
            if (!str_starts_with($path, 'server/')) {
                throw new DwemerPluginPackageException("Unsupported package payload '{$path}'.");
            }
        }
    }

    private function validatePluginName(string $name): void
    {
        if (strlen($name) > 64 || trim($name) !== $name || !preg_match('/^[A-Za-z0-9][A-Za-z0-9 ._-]{0,63}$/', $name)) {
            throw new DwemerPluginPackageException('Plugin name contains unsupported characters.');
        }
        if (str_ends_with($name, '.') || str_ends_with($name, ' ')) {
            throw new DwemerPluginPackageException('Plugin name cannot end with a dot or space.');
        }
    }

    private function validateVersion(string $version): void
    {
        if (!preg_match('/^[0-9A-Za-z][0-9A-Za-z._+-]{0,63}$/', $version)) {
            throw new DwemerPluginPackageException('Plugin version contains unsupported characters.');
        }
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

    private function requirePayloadPrefix(array $entries, string $prefix): void
    {
        foreach ($entries as $path => $entry) {
            if (!$entry['directory'] && str_starts_with($path, $prefix)) {
                return;
            }
        }
        throw new DwemerPluginPackageException("Package payload '{$prefix}' is empty.");
    }

    private function verifyChecksums(string $stageRoot, array $entries): void
    {
        $lines = file($stageRoot . DIRECTORY_SEPARATOR . 'checksums.sha256', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
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
            if ($entry['directory'] || $path === 'checksums.sha256') continue;
            if (!isset($expected[$path])) {
                throw new DwemerPluginPackageException("Package file '{$path}' is not covered by checksums.sha256.");
            }
            $filePath = $stageRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
            if (!hash_equals($expected[$path], hash_file('sha256', $filePath))) {
                throw new DwemerPluginPackageException("Checksum mismatch for '{$path}'.");
            }
            unset($expected[$path]);
        }
        if ($expected) {
            throw new DwemerPluginPackageException("Checksum references missing file '" . array_key_first($expected) . "'.");
        }
    }

    private function activateAndFinalize(array $job): array
    {
        try {
            $serverState = $this->activateServerComponent($job);
            $job['status'] = 'completed';
            $job['updated_at'] = gmdate(DATE_ATOM);
            $job['error'] = null;
            $this->writeJob($job);
            $this->recordInstalledPackage($job, $serverState);
            $this->removeDirectory((string)$job['stage_root']);
            @unlink((string)$job['archive_path']);
            return $this->publicJob($job);
        } catch (Throwable $error) {
            $job['status'] = 'failed';
            $job['error'] = $error->getMessage();
            $job['updated_at'] = gmdate(DATE_ATOM);
            $this->writeJob($job);
            return $this->publicJob($job);
        }
    }

    private function activateServerComponent(array $job): array
    {
        $name = (string)$job['name'];
        $source = (string)$job['stage_root'] . DIRECTORY_SEPARATOR . 'server';
        $target = $this->serverRoot . DIRECTORY_SEPARATOR . 'ext' . DIRECTORY_SEPARATOR . $name;
        $backup = $this->stateRoot . DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR . $job['id'] . DIRECTORY_SEPARATOR . $name;
        $failed = $this->stateRoot . DIRECTORY_SEPARATOR . 'failed' . DIRECTORY_SEPARATOR . $job['id'] . DIRECTORY_SEPARATOR . $name;
        if (!is_dir($source)) {
            throw new DwemerPluginPackageException('Staged server payload is missing.');
        }
        $this->ensureDirectory(dirname($target));
        $this->ensureDirectory(dirname($backup));
        $this->preserveMutablePaths($target, $source, $job['manifest']['server']['mutable_paths'] ?? []);
        $hadPrevious = is_dir($target);
        if ($hadPrevious && !rename($target, $backup)) {
            throw new DwemerPluginPackageException("Could not back up existing server extension '{$name}'.");
        }
        try {
            if (!rename($source, $target)) {
                throw new DwemerPluginPackageException("Could not activate server extension '{$name}'.");
            }
            $this->runMigrations($target, $name);
        } catch (Throwable $error) {
            if (is_dir($target)) {
                $this->ensureDirectory(dirname($failed));
                @rename($target, $failed);
                if (is_dir($target)) $this->removeDirectory($target);
            }
            if ($hadPrevious && is_dir($backup)) @rename($backup, $target);
            throw new DwemerPluginPackageException('Server activation rolled back: ' . $error->getMessage(), 0, $error);
        }
        return [
            'install_name' => $name,
            'path' => $target,
            'backup_path' => $hadPrevious ? $backup : null,
            'files' => $this->buildFileLedger($target),
        ];
    }

    private function preserveMutablePaths(string $oldRoot, string $newRoot, array $mutablePaths): void
    {
        if (!is_dir($oldRoot)) return;
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

    private function runMigrations(string $targetDir, string $pluginName): void
    {
        $migrations = glob($targetDir . DIRECTORY_SEPARATOR . 'migrations' . DIRECTORY_SEPARATOR . '*.sql') ?: [];
        sort($migrations, SORT_STRING);
        if (!$migrations) return;
        if (is_callable($this->migrationRunner)) {
            ($this->migrationRunner)($targetDir, $pluginName, $migrations);
            return;
        }
        if (!function_exists('pg_connect')) {
            throw new DwemerPluginPackageException('PostgreSQL support is required to run plugin migrations.');
        }
        $connection = @pg_connect('host=localhost port=5432 dbname=dwemer user=dwemer password=dwemer');
        if (!$connection) throw new DwemerPluginPackageException('Could not connect to PostgreSQL for plugin migrations.');
        try {
            if (!pg_query($connection, 'BEGIN')) throw new DwemerPluginPackageException('Could not start plugin migration transaction.');
            $setup = 'CREATE SCHEMA IF NOT EXISTS plugins; CREATE TABLE IF NOT EXISTS plugins.plugin_migrations (plugin_name VARCHAR(255), migration_name VARCHAR(255), executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (plugin_name, migration_name));';
            if (!pg_query($connection, $setup)) throw new DwemerPluginPackageException('Could not initialize plugin migration tracking.');
            foreach ($migrations as $migrationPath) {
                $migrationName = basename($migrationPath);
                $existing = pg_query_params($connection, 'SELECT 1 FROM plugins.plugin_migrations WHERE plugin_name = $1 AND migration_name = $2', [$pluginName, $migrationName]);
                if ($existing && pg_num_rows($existing) > 0) continue;
                $sql = file_get_contents($migrationPath);
                if ($sql === false || !pg_query($connection, $sql)) {
                    throw new DwemerPluginPackageException("Migration '{$migrationName}' failed: " . pg_last_error($connection));
                }
                if (!pg_query_params($connection, 'INSERT INTO plugins.plugin_migrations (plugin_name, migration_name) VALUES ($1, $2)', [$pluginName, $migrationName])) {
                    throw new DwemerPluginPackageException("Could not record migration '{$migrationName}'.");
                }
            }
            if (!pg_query($connection, 'COMMIT')) throw new DwemerPluginPackageException('Could not commit plugin migrations.');
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
            'name' => $job['name'],
            'version' => $job['version'],
            'installed_at' => gmdate(DATE_ATOM),
            'job_id' => $job['id'],
            'archive_sha256' => $job['archive_sha256'],
            'server' => $serverState,
            'manifest' => $job['manifest'],
        ];
        $this->atomicWrite(
            $this->installedPackagePath((string)$job['name']),
            json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL
        );
    }

    private function installedPackage(string $name): ?array
    {
        $path = $this->installedPackagePath($name);
        return is_file($path) ? $this->readJsonFile($path) : null;
    }

    private function installedPackagePath(string $name): string
    {
        return $this->stateRoot . DIRECTORY_SEPARATOR . 'packages' . DIRECTORY_SEPARATOR . hash('sha256', $this->canonicalName($name)) . '.json';
    }

    private function canonicalName(string $name): string
    {
        return strtolower($name);
    }

    private function buildFileLedger(string $root): array
    {
        $ledger = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (!$file->isFile()) continue;
            $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($file->getPathname(), strlen($root) + 1));
            $ledger[$relative] = hash_file('sha256', $file->getPathname());
        }
        ksort($ledger, SORT_STRING);
        return $ledger;
    }

    private function publicJob(array $job): array
    {
        return [
            'id' => $job['id'],
            'status' => $job['status'],
            'name' => $job['name'],
            'version' => $job['version'],
            'created_at' => $job['created_at'],
            'updated_at' => $job['updated_at'],
            'error' => $job['error'] ?? null,
        ];
    }

    private function writeJob(array $job): void
    {
        $this->atomicWrite($this->jobPath((string)$job['id']), json_encode($job, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL);
    }

    private function readJob(string $jobId): array
    {
        $path = $this->jobPath($jobId);
        if (!is_file($path)) throw new DwemerPluginPackageException('Package job was not found.');
        return $this->readJsonFile($path);
    }

    private function jobPath(string $jobId): string
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $jobId)) throw new DwemerPluginPackageException('Package job ID is invalid.');
        return $this->stateRoot . DIRECTORY_SEPARATOR . 'jobs' . DIRECTORY_SEPARATOR . $jobId . '.json';
    }

    private function readJsonFile(string $path): array
    {
        $contents = @file_get_contents($path);
        if ($contents === false) throw new DwemerPluginPackageException('Could not read package state.');
        try {
            $decoded = json_decode($contents, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new DwemerPluginPackageException('Package state is corrupted.', 0, $error);
        }
        if (!is_array($decoded)) throw new DwemerPluginPackageException('Package state is invalid.');
        return $decoded;
    }

    private function zipEntryIsSymlink(ZipArchive $zip, int $index): bool
    {
        $opsys = 0;
        $attributes = 0;
        if (!$zip->getExternalAttributesIndex($index, $opsys, $attributes) || $opsys !== ZipArchive::OPSYS_UNIX) return false;
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
        if (!preg_match('/^[a-f0-9]{32}$/', $uploadId)) throw new DwemerPluginPackageException('Chunked upload ID is invalid.');
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
        if (file_put_contents($temporary, $contents, LOCK_EX) === false) throw new DwemerPluginPackageException("Could not write '{$path}'.");
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
            if ($entry->isDot()) continue;
            $target = $destination . DIRECTORY_SEPARATOR . $entry->getFilename();
            if ($entry->isDir() && !$entry->isLink()) {
                $this->copyDirectory($entry->getPathname(), $target);
            } elseif ($entry->isFile()) {
                $this->ensureDirectory(dirname($target));
                if (!copy($entry->getPathname(), $target)) throw new DwemerPluginPackageException("Could not preserve '{$entry->getFilename()}'.");
            }
        }
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) return;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $entry) {
            $entry->isDir() && !$entry->isLink() ? @rmdir($entry->getPathname()) : @unlink($entry->getPathname());
        }
        @rmdir($path);
    }
}
