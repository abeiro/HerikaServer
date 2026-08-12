#!/usr/bin/env php
<?php

declare(strict_types=1);

use HerikaServer\Database\MigrationRunner;

$root = dirname(__DIR__);
require_once $root . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'MigrationRunner.php';

$mode = $argv[1] ?? '';
if (!in_array($mode, ['baseline', 'current'], true)) {
    fwrite(STDERR, "Usage: php scripts/generate-schema-contract.php <baseline|current> [output-path]\n");
    exit(2);
}

$dsn = getenv('HERIKA_DATABASE_DSN');
if (!$dsn) {
    fwrite(STDERR, "HERIKA_DATABASE_DSN is required so the contract cannot be generated accidentally from a user database.\n");
    exit(2);
}

$connection = @pg_connect($dsn);
if (!$connection) {
    fwrite(STDERR, "Could not connect to the contract source database.\n");
    exit(1);
}

$queryRows = static function (string $sql) use ($connection): array {
    $result = @pg_query($connection, $sql);
    if (!$result) {
        throw new RuntimeException(pg_last_error($connection));
    }
    return pg_fetch_all($result) ?: [];
};

try {
    $contract = [
        'extensions' => [],
        'tables' => [],
        'views' => [],
        'constraints' => [],
        'indexes' => [],
    ];
    if ($mode === 'baseline') {
        $contract = ['baseline_version' => MigrationRunner::BASELINE_VERSION] + $contract;
    } else {
        $contract = ['schema_version' => MigrationRunner::latestVersion($root)] + $contract;
    }

    foreach ($queryRows("SELECT extname FROM pg_extension WHERE extname NOT IN ('plpgsql') ORDER BY extname") as $row) {
        $contract['extensions'][] = $row['extname'];
    }
    foreach ($queryRows("SELECT table_schema, table_name, table_type FROM information_schema.tables WHERE table_schema IN ('public','chim_meta') ORDER BY table_schema, table_name") as $row) {
        $relation = $row['table_schema'] . '.' . $row['table_name'];
        if ($row['table_type'] === 'VIEW') {
            $contract['views'][$relation] = '';
        } elseif ($row['table_type'] === 'BASE TABLE' && $relation !== MigrationRunner::LEDGER) {
            $contract['tables'][$relation] = ['columns' => []];
        }
    }
    foreach ($queryRows("SELECT schemaname, viewname, definition FROM pg_views WHERE schemaname IN ('public','chim_meta') ORDER BY schemaname, viewname") as $row) {
        $relation = $row['schemaname'] . '.' . $row['viewname'];
        if (array_key_exists($relation, $contract['views'])) {
            $contract['views'][$relation] = trim($row['definition']);
        }
    }
    foreach ($queryRows("SELECT table_schema, table_name, column_name, udt_schema, udt_name, is_nullable FROM information_schema.columns WHERE table_schema IN ('public','chim_meta') ORDER BY table_schema, table_name, ordinal_position") as $row) {
        $relation = $row['table_schema'] . '.' . $row['table_name'];
        if (!isset($contract['tables'][$relation])) {
            continue;
        }
        $contract['tables'][$relation]['columns'][$row['column_name']] = [
            'udt_schema' => $row['udt_schema'],
            'udt_name' => $row['udt_name'],
            'nullable' => $row['is_nullable'] === 'YES',
        ];
    }
    foreach ($queryRows("SELECT n.nspname AS schema_name, c.relname AS table_name, con.conname FROM pg_constraint con JOIN pg_class c ON c.oid=con.conrelid JOIN pg_namespace n ON n.oid=c.relnamespace WHERE n.nspname IN ('public','chim_meta') ORDER BY 1,2,3") as $row) {
        if ($row['schema_name'] . '.' . $row['table_name'] === MigrationRunner::LEDGER) {
            continue;
        }
        $contract['constraints'][] = $row['schema_name'] . '.' . $row['table_name'] . '.' . $row['conname'];
    }
    foreach ($queryRows("SELECT schemaname, tablename, indexname FROM pg_indexes WHERE schemaname IN ('public','chim_meta') ORDER BY 1,2,3") as $row) {
        if ($row['schemaname'] . '.' . $row['tablename'] === MigrationRunner::LEDGER) {
            continue;
        }
        $contract['indexes'][] = $row['schemaname'] . '.' . $row['tablename'] . '.' . $row['indexname'];
    }

    $json = json_encode($contract, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    $outputPath = $argv[2] ?? null;
    if ($outputPath !== null) {
        $directory = dirname($outputPath);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException("Could not create contract directory: {$directory}");
        }
        if (file_put_contents($outputPath, $json) === false) {
            throw new RuntimeException("Could not write contract: {$outputPath}");
        }
        fwrite(STDOUT, "Wrote {$outputPath}\n");
    } else {
        fwrite(STDOUT, $json);
    }
} catch (Throwable $error) {
    fwrite(STDERR, 'Contract generation failed: ' . $error->getMessage() . "\n");
    exit(1);
} finally {
    pg_close($connection);
}
