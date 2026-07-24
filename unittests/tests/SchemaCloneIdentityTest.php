<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once(
    __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..'
    . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'playthrough_schema.php'
);

final class SchemaCloneIdentityTest extends TestCase
{
    public function testLegacyCloneFunctionNeedsRefresh(): void
    {
        $legacyDefinition = 'INSERT INTO destination SELECT * FROM source';

        $this->assertFalse(pts_clone_function_is_current($legacyDefinition));
    }

    public function testIdentityOnlyCloneFunctionNeedsSequenceRefresh(): void
    {
        $identityOnlyDefinition =
            'INSERT INTO destination OVERRIDING SYSTEM VALUE SELECT * FROM source';

        $this->assertFalse(pts_clone_function_is_current($identityOnlyDefinition));
    }

    public function testIdentityAwareCloneFunctionIsCurrent(): void
    {
        $currentDefinition = <<<'SQL'
INSERT INTO destination OVERRIDING SYSTEM VALUE SELECT * FROM source;
PERFORM chim_meta.sync_schema_sequences(dest_schema);
SQL;

        $this->assertTrue(pts_clone_function_is_current($currentDefinition));
    }

    public function testSchemaClonePreservesGeneratedIdentityValues(): void
    {
        $sql = file_get_contents(
            __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..'
            . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'schema_clone_function.sql'
        );

        $this->assertIsString($sql);
        $this->assertStringContainsString(
            'INSERT INTO %I.%I OVERRIDING SYSTEM VALUE SELECT * FROM %I.%I ON CONFLICT DO NOTHING',
            $sql
        );
        $this->assertStringContainsString('sync_schema_sequences', $sql);
        $this->assertStringContainsString("dependency.deptype IN ('a', 'i')", $sql);
        $this->assertStringContainsString(
            'PERFORM chim_meta.sync_schema_sequences(dest_schema)',
            $sql
        );
        $this->assertStringContainsString(
            'SELECT last_value, is_called FROM %I.%I',
            $sql
        );
        $this->assertStringContainsString(
            'THEN source_last_value + obj.increment_by',
            $sql
        );
        $this->assertStringContainsString(
            "SELECT setval(''%I.%I'', %s, false)",
            $sql
        );
    }

    public function testDatabaseUpdateRepairsExistingPublicSequences(): void
    {
        $updates = file_get_contents(
            __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..'
            . DIRECTORY_SEPARATOR . 'debug' . DIRECTORY_SEPARATOR . 'db_updates.php'
        );

        $this->assertIsString($updates);
        $this->assertStringContainsString(
            "SELECT chim_meta.sync_schema_sequences('public')",
            $updates
        );
        $this->assertStringContainsString(
            '$updateVersion("playthrough_schema", 20260723001)',
            $updates
        );
    }
}
