#!/usr/bin/env php
<?php

declare(strict_types=1);

use HerikaServer\Database\MigrationRunner;

$root = dirname(__DIR__);
require_once $root . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'MigrationRunner.php';

$usage = static function (): never {
    fwrite(STDERR, "Usage: php scripts/database.php <status|migrate|verify|doctor|legacy-bridge>\n");
    exit(2);
};

try {
    $command = $argv[1] ?? null;
    if (!in_array($command, ['status', 'migrate', 'verify', 'doctor', 'legacy-bridge'], true)) {
        $usage();
    }

    if ($command === 'legacy-bridge') {
        if (getenv('HERIKA_DATABASE_DSN')) {
            throw new RuntimeException('legacy-bridge targets the configured HerikaServer database and does not accept HERIKA_DATABASE_DSN overrides.');
        }
        require $root . DIRECTORY_SEPARATOR . 'debug' . DIRECTORY_SEPARATOR . 'apply_db_updates.php';
        exit(0);
    }

    $nullDevice = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
    $commit = trim((string) @shell_exec('git -C ' . escapeshellarg($root) . ' rev-parse HEAD 2>' . $nullDevice)) ?: null;
    $runner = MigrationRunner::connect($root, null, $commit);

    if ($command === 'migrate') {
        $ran = $runner->migrate();
        fwrite(STDOUT, $ran === [] ? "Database is already current.\n" : 'Applied: ' . implode(', ', $ran) . "\n");
        exit(0);
    }

    $status = $runner->status();
    if ($command === 'status') {
        printf("Legacy baseline: %s\n", $status['baseline_problems'] === [] ? 'compatible' : 'not reconciled');
        foreach ($status['applied'] as $migration) {
            printf("%012d %-40s applied %s\n", $migration['version'], $migration['name'], $migration['checksum']);
        }
        foreach ($status['pending'] as $migration) {
            printf("%012d %-40s pending %s\n", $migration['version'], $migration['name'], $migration['checksum']);
        }
        exit($status['ready'] ? 0 : 1);
    }

    $problems = $runner->verify();
    if ($command === 'doctor') {
        if ($problems === []) {
            fwrite(STDOUT, "Database schema and migration history are healthy.\n");
            exit(0);
        }
        fwrite(STDOUT, "Database requires attention:\n- " . implode("\n- ", $problems) . "\n");
        exit(1);
    }

    if ($problems !== []) {
        throw new RuntimeException("Database verification failed:\n- " . implode("\n- ", $problems));
    }
    fwrite(STDOUT, "Database schema and migration history are current.\n");
} catch (Throwable $error) {
    fwrite(STDERR, 'Database command failed: ' . $error->getMessage() . "\n");
    exit(1);
}
