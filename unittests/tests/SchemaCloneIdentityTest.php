<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class SchemaCloneIdentityTest extends TestCase
{
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
