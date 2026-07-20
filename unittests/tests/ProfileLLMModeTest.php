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

    public function testConfiguredConnectorsIncludeUiLabelsAndIds(): void
    {
        $profile = [
            'llm_primary_id' => 11,
            'llm_secondary_id' => null,
            'llm_tertiary_id' => 33,
            'llm_quaternary_id' => 0,
        ];

        $this->assertSame([
            [
                'slot' => 1,
                'key' => 'standard',
                'label' => 'Standard',
                'connector_id' => 11,
            ],
            [
                'slot' => 3,
                'key' => 'powerful',
                'label' => 'Powerful',
                'connector_id' => 33,
            ],
        ], ProfileLLMMode::getConfiguredConnectors($profile));
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

    public function testProfileDefaultsReadExistingMetadataValues(): void
    {
        $this->assertSame([
            'dynamic_profile' => true,
            'middle_term_memory' => false,
            'auto_diary' => true,
            'auto_diary_wait' => false,
            'physical_diary' => false,
        ], ProfileLLMMode::getProfileDefaults([
            'metadata' => '{"DYNAMIC_PROFILE_ENABLED":"on","MIDDLE_TERM_MEMORY_ENABLED":0,' .
                '"AUTO_DIARY_ENABLED":true,"AUTO_DIARY_WAIT_ENABLED":"false"}',
        ]));
    }

    public function testUpdatingProfileDefaultPreservesOtherMetadata(): void
    {
        $updated = ProfileLLMMode::updateProfileDefaultMetadata(
            '{"CORE_LANG":"ja","AUTO_DIARY_ENABLED":false}',
            'auto_diary',
            true
        );
        $decoded = json_decode($updated, true);

        $this->assertTrue($decoded['AUTO_DIARY_ENABLED']);
        $this->assertSame('ja', $decoded['CORE_LANG']);
    }

    public function testPhysicalDiaryDefaultsOffAndCanBeEnabled(): void
    {
        $defaults = ProfileLLMMode::getProfileDefaults(['metadata' => '{}']);
        $this->assertFalse($defaults['physical_diary']);

        $updated = ProfileLLMMode::updateProfileDefaultMetadata('{}', 'physical_diary', true);
        $this->assertTrue(json_decode($updated, true)['MATERIALIZE_DIARY_ENABLED']);
    }

    public function testUpdatingUnsupportedProfileDefaultFails(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ProfileLLMMode::updateProfileDefaultMetadata('{}', 'unknown_setting', true);
    }
}
