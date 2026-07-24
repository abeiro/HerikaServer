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

    public function testIdentityAwareCloneFunctionIsCurrent(): void
    {
        $currentDefinition = 'INSERT INTO destination OVERRIDING SYSTEM VALUE SELECT * FROM source';

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
    }
}
