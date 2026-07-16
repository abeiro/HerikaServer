<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'profile_llm_mode.php';

final class ProfileLLMModeTest extends TestCase
{
    public function testUpdatingRandomModePreservesExistingMetadata(): void
    {
        $metadata = '{"CORE_LANG":"ja","CUSTOM":{"keep":true}}';

        $updated = ProfileLLMMode::updateRandomEnabledMetadata($metadata, true);
        $decoded = json_decode($updated, true);

        $this->assertTrue($decoded['LLM_RANDOMIZER_ENABLED']);
        $this->assertSame('ja', $decoded['CORE_LANG']);
        $this->assertSame(['keep' => true], $decoded['CUSTOM']);
    }

    public function testConfiguredSlotsExcludeMissingConnectors(): void
    {
        $profile = [
            'llm_primary_id' => 11,
            'llm_secondary_id' => null,
            'llm_tertiary_id' => 33,
            'llm_quaternary_id' => 0,
        ];

        $this->assertSame([1, 3], ProfileLLMMode::getConfiguredSlots($profile));
    }

    public function testRandomModeAcceptsLegacyTruthyValues(): void
    {
        $this->assertTrue(ProfileLLMMode::isRandomEnabled([
            'metadata' => '{"LLM_RANDOMIZER_ENABLED":"on"}',
        ]));
        $this->assertFalse(ProfileLLMMode::isRandomEnabled([
            'metadata' => '{"LLM_RANDOMIZER_ENABLED":"0"}',
        ]));
    }
}
