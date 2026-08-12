<?php

require_once(__DIR__ . DIRECTORY_SEPARATOR . 'DatabaseTestCase.php');
require_once(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'MigrationRunner.php');

use HerikaServer\Database\MigrationRunner;

final class MigrationRunnerTest extends DatabaseTestCase
{
    private function runner(): MigrationRunner
    {
        return MigrationRunner::connect(
            dirname(__DIR__, 2),
            'host=localhost dbname=testdb user=dwemer password=dwemer'
        );
    }

    public function testMigrateIsIdempotent(): void
    {
        $this->assertSame([], $this->runner()->migrate());
        $this->assertSame([], $this->runner()->verify());
    }

    public function testVerifyDetectsStructuralSchemaDrift(): void
    {
        $connection = pg_connect('host=localhost dbname=testdb user=dwemer password=dwemer');
        pg_query($connection, 'ALTER TABLE public.audit_request DROP COLUMN response');

        $this->assertContains('Missing column public.audit_request.response', $this->runner()->verify());
    }

    public function testVerifyRejectsMigrationChecksumDrift(): void
    {
        $connection = pg_connect('host=localhost dbname=testdb user=dwemer password=dwemer');
        pg_query_params(
            $connection,
            'UPDATE chim_meta.schema_migrations SET checksum=$1 WHERE version=$2',
            [str_repeat('0', 64), '202608120001']
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Migration checksum drift detected at 202608120001');
        $this->runner()->verify();
    }

    public function testFailedSqlMigrationRollsBackSchemaAndLedger(): void
    {
        $root = dirname(__DIR__, 2);
        $fixture = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'herika_migration_' . bin2hex(random_bytes(8));
        $baselineDirectory = $fixture . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'baseline';
        $migrationDirectory = $fixture . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations';
        mkdir($baselineDirectory, 0775, true);
        mkdir($migrationDirectory, 0775, true);

        $baselineName = MigrationRunner::BASELINE_VERSION . '_contract.json';
        copy($root . '/database/baseline/' . $baselineName, $baselineDirectory . '/' . $baselineName);
        copy($root . '/database/schema-contract.json', $fixture . '/database/schema-contract.json');
        copy(
            $root . '/database/migrations/202608120001_audit_request_response.sql',
            $migrationDirectory . '/202608120001_audit_request_response.sql'
        );
        $failingMigration = $migrationDirectory . '/202608120002_rollback_probe.sql';
        file_put_contents(
            $failingMigration,
            "CREATE TABLE public.migration_rollback_probe (id integer);\n"
            . "INSERT INTO public.migration_table_that_does_not_exist (id) VALUES (1);\n"
        );

        $connection = pg_connect('host=localhost dbname=testdb user=dwemer password=dwemer');
        try {
            $runner = new MigrationRunner($connection, $fixture);
            try {
                $runner->migrate();
                $this->fail('The deliberately failing migration unexpectedly succeeded.');
            } catch (RuntimeException $error) {
                $this->assertStringContainsString('migration_table_that_does_not_exist', $error->getMessage());
            }

            $table = pg_fetch_result(pg_query($connection, "SELECT to_regclass('public.migration_rollback_probe')"), 0, 0);
            $ledger = pg_fetch_result(
                pg_query($connection, 'SELECT count(*) FROM chim_meta.schema_migrations WHERE version=202608120002'),
                0,
                0
            );
            $this->assertSame('', strval($table));
            $this->assertSame('0', $ledger);
        } finally {
            pg_close($connection);
            unlink($failingMigration);
            unlink($migrationDirectory . '/202608120001_audit_request_response.sql');
            unlink($fixture . '/database/schema-contract.json');
            unlink($baselineDirectory . '/' . $baselineName);
            rmdir($migrationDirectory);
            rmdir($baselineDirectory);
            rmdir($fixture . '/database');
            rmdir($fixture);
        }
    }
}
