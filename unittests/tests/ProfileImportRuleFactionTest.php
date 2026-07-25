<?php

use PHPUnit\Framework\TestCase;

final class ProfileImportRuleFactionTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testFreshAndUpgradeSchemasIncludeFactionMatcher(): void
    {
        $schema = file_get_contents($this->root . '/data/import_rules.sql');
        $updates = file_get_contents($this->root . '/debug/db_updates.php');

        $this->assertStringContainsString('match_faction text', $schema);
        $this->assertStringContainsString(
            'ALTER TABLE public.import_rules ADD COLUMN IF NOT EXISTS match_faction text',
            $updates
        );
        $this->assertStringContainsString('$updateVersion("import_rules", 20260725001)', $updates);
    }

    public function testRuleStorageUiAndRuntimeConsumeFactionMatcher(): void
    {
        $model = file_get_contents($this->root . '/lib/core/import_rules.class.php');
        $ui = file_get_contents($this->root . '/ui/core/core_profiles.php');
        $runtime = file_get_contents($this->root . '/processor/comm.php');

        $this->assertGreaterThanOrEqual(2, substr_count($model, '"match_faction"'));
        $this->assertStringContainsString("'match_faction' => chimNullIfBlank", $ui);
        $this->assertStringContainsString("formData.append('match_faction'", $ui);
        $this->assertStringContainsString('FROM unnest($factionsArray) AS npc_faction(name)', $runtime);
        $this->assertStringContainsString('npc_faction.name ~ r.match_faction', $runtime);
    }
}
