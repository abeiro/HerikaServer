<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../lib/plugin_package_manager.php';

final class PluginPackageManagerTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'chim-package-test-' . bin2hex(random_bytes(6));
        mkdir($this->root, 0770, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->root);
    }

    public function testProbeRequestsOnlyMissingOrChangedVersions(): void
    {
        $manager = $this->manager();
        $this->assertTrue($manager->probe('Example Plugin', '1.0.0')['upload_required']);

        $installed = $manager->installArchive($this->buildPackage('Example Plugin', '1.0.0'));
        $this->assertSame('completed', $installed['status']);

        $current = $manager->probe('example plugin', '1.0.0');
        $this->assertFalse($current['upload_required']);
        $this->assertSame('current', $current['reason']);

        $changed = $manager->probe('Example Plugin', '1.1.0');
        $this->assertTrue($changed['upload_required']);
        $this->assertSame('version_changed', $changed['reason']);
    }

    public function testInstalledPackagesIgnoreLegacyPackageIdRecords(): void
    {
        $manager = $this->manager();
        file_put_contents($this->root . '/state/packages/legacy.json', json_encode([
            'package_id' => 'example-plugin',
            'name' => 'Example Plugin',
            'version' => '1.0.0',
            'manifest' => ['schema_version' => 3],
        ], JSON_THROW_ON_ERROR));
        $manager->installArchive($this->buildPackage('Example-Plugin', '2.0.0'));

        $installed = $manager->installedPackages();

        $this->assertCount(1, $installed);
        $this->assertSame('Example-Plugin', $installed[0]['name']);
        $this->assertSame('2.0.0', $installed[0]['version']);
    }

    public function testRejectsTraversalBeforeExtraction(): void
    {
        $archive = $this->root . DIRECTORY_SEPARATOR . 'unsafe.dwpkg';
        $zip = new ZipArchive();
        $zip->open($archive, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('manifest.json', '{}');
        $zip->addFromString('checksums.sha256', str_repeat('0', 64) . "  manifest.json\n");
        $zip->addFromString('../escape.txt', 'unsafe');
        $zip->close();

        $this->expectException(DwemerPluginPackageException::class);
        $this->expectExceptionMessage('unsafe');
        $this->manager()->installArchive($archive);
    }

    public function testRejectsGamePayloadAndMismatchedFolderIdentity(): void
    {
        $archive = $this->buildPackage('Example Plugin', '1.0.0', ['game/plugin.dll' => 'not allowed']);
        try {
            $this->manager()->installArchive($archive);
            $this->fail('Game payload was accepted.');
        } catch (DwemerPluginPackageException $error) {
            $this->assertStringContainsString('Unsupported package payload', $error->getMessage());
        }

        $archive = $this->buildPackage('Example Plugin', '1.0.0');
        $this->expectException(DwemerPluginPackageException::class);
        $this->expectExceptionMessage('game-side plugin folder');
        $this->manager()->installArchive($archive, '1.0.0.dwpkg', 'Different Plugin', '1.0.0');
    }

    public function testChunkedUploadReassemblesAndActivatesPackage(): void
    {
        $archive = $this->buildPackage('Chunked Plugin', '2.1.0', ['server/payload.txt' => str_repeat('x', 4096)]);
        $contents = file_get_contents($archive);
        $this->assertIsString($contents);
        $chunks = str_split($contents, 512);
        $manager = $this->manager();
        $upload = $manager->startChunkedUpload('Chunked Plugin', '2.1.0', '2.1.0.zip', strlen($contents), count($chunks));

        $result = null;
        foreach ($chunks as $index => $chunk) {
            $result = $manager->appendUploadChunk($upload['upload_id'], $index, $chunk);
        }

        $this->assertTrue($result['complete']);
        $this->assertSame('completed', $result['job']['status']);
        $this->assertSame('Chunked Plugin', $result['job']['name']);
        $this->assertFileExists($this->root . '/server/ext/Chunked Plugin/payload.txt');
    }

    public function testChunkedUploadAcceptsDwpkgAndZipExtensions(): void
    {
        $manager = $this->manager();

        foreach (['1.0.0.dwpkg', '1.0.0.zip', '1.0.0.ZIP'] as $archiveName) {
            $upload = $manager->startChunkedUpload('Extension Plugin', '1.0.0', $archiveName, 1, 1);
            $this->assertNotEmpty($upload['upload_id']);
        }
    }

    public function testChunkedUploadRejectsUnsupportedArchiveExtension(): void
    {
        $this->expectException(DwemerPluginPackageException::class);
        $this->expectExceptionMessage('.dwpkg or .zip');
        $this->manager()->startChunkedUpload('Extension Plugin', '1.0.0', '1.0.0.7z', 1, 1);
    }

    public function testServerActivationPreservesDeclaredMutableFiles(): void
    {
        $manager = $this->manager();
        $first = $this->buildPackage('ExamplePlugin', '1.0.0', [
            'server/conf/conf.php' => 'default-v1',
            'server/plugin.php' => 'version-one',
        ], ['conf/conf.php']);
        $this->assertSame('completed', $manager->installArchive($first)['status']);

        $target = $this->root . '/server/ext/ExamplePlugin';
        file_put_contents($target . '/conf/conf.php', 'user-config');
        $second = $this->buildPackage('ExamplePlugin', '2.0.0', [
            'server/conf/conf.php' => 'default-v2',
            'server/plugin.php' => 'version-two',
        ], ['conf/conf.php']);
        $updated = $manager->installArchive($second);

        $this->assertSame('completed', $updated['status']);
        $this->assertSame('version-two', file_get_contents($target . '/plugin.php'));
        $this->assertSame('user-config', file_get_contents($target . '/conf/conf.php'));
    }

    public function testMigrationFailureRestoresPreviousServerExtension(): void
    {
        $migrationRunner = static function (string $targetDir, string $pluginName, array $migrations): void {
            if (str_contains((string)file_get_contents($targetDir . '/plugin.php'), 'broken')) {
                throw new RuntimeException('forced migration failure');
            }
        };
        $manager = $this->manager($migrationRunner);
        $first = $this->buildPackage('RollbackPlugin', '1.0.0', ['server/plugin.php' => 'stable-version']);
        $this->assertSame('completed', $manager->installArchive($first)['status']);

        $second = $this->buildPackage('RollbackPlugin', '2.0.0', [
            'server/plugin.php' => 'broken-version',
            'server/migrations/001_break.sql' => 'SELECT 1;',
        ]);
        $result = $manager->installArchive($second);

        $this->assertSame('failed', $result['status']);
        $this->assertStringContainsString('rolled back', $result['error']);
        $this->assertSame('stable-version', file_get_contents($this->root . '/server/ext/RollbackPlugin/plugin.php'));
        $this->assertSame('1.0.0', $manager->probe('RollbackPlugin', '1.0.0')['installed_version']);
    }

    private function manager(?callable $migrationRunner = null): DwemerPluginPackageManager
    {
        return new DwemerPluginPackageManager(
            $this->root . '/server',
            $this->root . '/state',
            $migrationRunner ?? static fn() => null
        );
    }

    private function buildPackage(
        string $name,
        string $version,
        array $extraFiles = [],
        array $mutablePaths = []
    ): string {
        $archive = $this->root . '/package-' . bin2hex(random_bytes(4)) . '.dwpkg';
        $manifest = [
            'schema_version' => 4,
            'name' => $name,
            'version' => $version,
            'description' => 'Unit test package',
            'server' => ['mutable_paths' => $mutablePaths],
        ];
        $files = [
            'manifest.json' => json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            'server/manifest.json' => json_encode(['name' => $name, 'version' => $version], JSON_THROW_ON_ERROR),
        ];
        foreach ($extraFiles as $path => $contents) $files[$path] = $contents;
        $checksums = [];
        foreach ($files as $path => $contents) $checksums[] = hash('sha256', $contents) . '  ' . $path;
        $files['checksums.sha256'] = implode("\n", $checksums) . "\n";

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($archive, ZipArchive::CREATE | ZipArchive::OVERWRITE));
        foreach ($files as $path => $contents) $this->assertTrue($zip->addFromString($path, $contents));
        $zip->close();
        return $archive;
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) return;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $entry) $entry->isDir() ? @rmdir($entry->getPathname()) : @unlink($entry->getPathname());
        @rmdir($path);
    }
}
