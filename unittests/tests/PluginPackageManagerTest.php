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

    public function testRejectsTraversalBeforeExtraction(): void
    {
        $archive = $this->root . DIRECTORY_SEPARATOR . 'unsafe.dwpkg';
        $zip = new ZipArchive();
        $zip->open($archive, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('manifest.json', '{}');
        $zip->addFromString('checksums.sha256', str_repeat('0', 64) . "  manifest.json\n");
        $zip->addFromString('../escape.txt', 'unsafe');
        $zip->close();

        $manager = $this->manager();
        $this->expectException(DwemerPluginPackageException::class);
        $this->expectExceptionMessage('unsafe');
        $manager->queueArchive($archive);
    }

    public function testGamePackageWaitsForLauncherAndCanFailCleanly(): void
    {
        $archive = $this->buildPackage(
            $this->manifest(['game' => $this->gameComponent()]),
            ['game/skyrim-se/SKSE/Plugins/TestPlugin.ini' => 'enabled=true']
        );
        $manager = $this->manager();

        $queued = $manager->queueArchive($archive, 'test.dwpkg');
        $this->assertSame('awaiting_launcher', $queued['status']);
        $this->assertCount(1, $manager->pendingJobs());

        $claimed = $manager->claimJob($queued['id']);
        $this->assertSame('installing_game', $claimed['status']);
        $this->assertNotEmpty($claimed['claim_token']);

        $completed = $manager->completeGameInstall(
            $queued['id'],
            $claimed['claim_token'],
            false,
            ['error' => 'MO2 profile was not selected.']
        );
        $this->assertSame('failed', $completed['status']);
        $this->assertSame('MO2 profile was not selected.', $completed['error']);
    }

    public function testRejectsUnsafeMo2ModFolderName(): void
    {
        $archive = $this->buildPackage(
            $this->manifest(['game' => [
                'mod_name' => '..',
                'variants' => ['skyrim-se' => 'game/skyrim-se'],
                'mutable_paths' => [],
            ]]),
            ['game/skyrim-se/payload.txt' => 'unsafe']
        );

        $this->expectException(DwemerPluginPackageException::class);
        $this->expectExceptionMessage('safe MO2 folder name');
        $this->manager()->queueArchive($archive);
    }

    public function testChunkedUploadReassemblesAndQueuesPackage(): void
    {
        $archive = $this->buildPackage(
            $this->manifest(['game' => $this->gameComponent()]),
            ['game/skyrim-se/SKSE/Plugins/TestPlugin.ini' => str_repeat('x', 2048)]
        );
        $contents = file_get_contents($archive);
        $this->assertIsString($contents);
        $chunks = str_split($contents, 512);
        $manager = $this->manager();
        $upload = $manager->startChunkedUpload('chunked.dwpkg', strlen($contents), count($chunks));

        $result = null;
        foreach ($chunks as $index => $chunk) {
            $result = $manager->appendUploadChunk($upload['upload_id'], $index, $chunk);
        }

        $this->assertTrue($result['complete']);
        $this->assertSame('awaiting_launcher', $result['job']['status']);
        $this->assertSame('Test Package', $result['job']['package_name']);
    }

    public function testServerActivationPreservesDeclaredMutableFiles(): void
    {
        $manager = $this->manager();
        $component = [
            'install_name' => 'ExamplePlugin',
            'mutable_paths' => ['conf/conf.php'],
        ];
        $first = $this->buildPackage(
            $this->manifest(['server' => $component], '1.0.0'),
            [
                'server/manifest.json' => '{"name":"ExamplePlugin","version":"1.0.0"}',
                'server/conf/conf.php' => 'default-v1',
                'server/plugin.php' => 'version-one',
            ]
        );
        $installed = $manager->queueArchive($first);
        $this->assertSame('completed', $installed['status']);

        $target = $this->root . DIRECTORY_SEPARATOR . 'server' . DIRECTORY_SEPARATOR . 'ext' . DIRECTORY_SEPARATOR . 'ExamplePlugin';
        file_put_contents($target . DIRECTORY_SEPARATOR . 'conf' . DIRECTORY_SEPARATOR . 'conf.php', 'user-config');

        $second = $this->buildPackage(
            $this->manifest(['server' => $component], '2.0.0'),
            [
                'server/manifest.json' => '{"name":"ExamplePlugin","version":"2.0.0"}',
                'server/conf/conf.php' => 'default-v2',
                'server/plugin.php' => 'version-two',
            ]
        );
        $updated = $manager->queueArchive($second);

        $this->assertSame('completed', $updated['status']);
        $this->assertSame('version-two', file_get_contents($target . DIRECTORY_SEPARATOR . 'plugin.php'));
        $this->assertSame('user-config', file_get_contents($target . DIRECTORY_SEPARATOR . 'conf' . DIRECTORY_SEPARATOR . 'conf.php'));
    }

    public function testMigrationFailureRestoresPreviousServerExtension(): void
    {
        $migrationRunner = static function (string $targetDir, string $packageId, array $migrations): void {
            throw new RuntimeException('forced migration failure');
        };
        $manager = $this->manager($migrationRunner);
        $component = ['install_name' => 'RollbackPlugin', 'mutable_paths' => []];
        $first = $this->buildPackage(
            $this->manifest(['server' => $component], '1.0.0'),
            [
                'server/manifest.json' => '{"name":"RollbackPlugin","version":"1.0.0"}',
                'server/plugin.php' => 'stable-version',
            ]
        );
        $this->assertSame('completed', $manager->queueArchive($first)['status']);

        $second = $this->buildPackage(
            $this->manifest(['server' => $component], '2.0.0'),
            [
                'server/manifest.json' => '{"name":"RollbackPlugin","version":"2.0.0"}',
                'server/plugin.php' => 'broken-version',
                'server/migrations/001_break.sql' => 'SELECT 1;',
            ]
        );
        $result = $manager->queueArchive($second);
        $target = $this->root . DIRECTORY_SEPARATOR . 'server' . DIRECTORY_SEPARATOR . 'ext' . DIRECTORY_SEPARATOR . 'RollbackPlugin' . DIRECTORY_SEPARATOR . 'plugin.php';

        $this->assertSame('failed', $result['status']);
        $this->assertStringContainsString('rolled back', $result['error']);
        $this->assertSame('stable-version', file_get_contents($target));
    }

    private function manager(?callable $migrationRunner = null): DwemerPluginPackageManager
    {
        return new DwemerPluginPackageManager(
            $this->root . DIRECTORY_SEPARATOR . 'server',
            $this->root . DIRECTORY_SEPARATOR . 'state',
            $migrationRunner ?? static fn() => null
        );
    }

    private function manifest(array $components, string $version = '1.0.0'): array
    {
        return [
            'schema_version' => 3,
            'package_id' => 'test-package',
            'name' => 'Test Package',
            'version' => $version,
            'description' => 'Unit test package',
            'components' => $components,
        ];
    }

    private function gameComponent(): array
    {
        return [
            'mod_name' => 'CHIM Plugin - Test Package',
            'variants' => ['skyrim-se' => 'game/skyrim-se'],
            'mutable_paths' => [],
        ];
    }

    private function buildPackage(array $manifest, array $payload): string
    {
        $archive = $this->root . DIRECTORY_SEPARATOR . 'package-' . bin2hex(random_bytes(4)) . '.dwpkg';
        $files = ['manifest.json' => json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)] + $payload;
        $checksums = [];
        foreach ($files as $path => $contents) {
            $checksums[] = hash('sha256', $contents) . '  ' . $path;
        }
        $files['checksums.sha256'] = implode("\n", $checksums) . "\n";

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($archive, ZipArchive::CREATE | ZipArchive::OVERWRITE));
        foreach ($files as $path => $contents) {
            $this->assertTrue($zip->addFromString($path, $contents));
        }
        $zip->close();
        return $archive;
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
            $entry->isDir() ? @rmdir($entry->getPathname()) : @unlink($entry->getPathname());
        }
        @rmdir($path);
    }
}
