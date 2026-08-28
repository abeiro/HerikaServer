<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ProfileConnectorTestsRegressionTest extends TestCase
{
    protected function setUp(): void
    {
        require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'core' .
            DIRECTORY_SEPARATOR . 'local_llm_setup.php';
        require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'core' .
            DIRECTORY_SEPARATOR . 'settings_presets.php';
    }

    public function testLlmHealthCheckInitializesCommandPrompt(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'ui' . DIRECTORY_SEPARATOR . 'api' .
            DIRECTORY_SEPARATOR . 'profile_connector_tests.php'
        );

        $start = strpos($source, 'function profileConnectorTestsTestLlm');
        $end = strpos($source, 'function profileConnectorTestsTestTts', $start);
        $llmTestFunction = substr($source, $start, $end - $start);

        $this->assertStringContainsString('$GLOBALS["COMMAND_PROMPT"] = \'\';', $llmTestFunction);
    }

    public function testLocalLlmUrlValidationAllowsOnlyLoopbackAndPrivateLanHosts(): void
    {
        $this->assertSame(
            'http://192.168.1.10:1234/v1/chat/completions',
            herikaLocalLlmValidateUrl('http://192.168.1.10:1234/v1/chat/completions')
        );
        $this->assertTrue(herikaLocalLlmUrlIsAllowed('http://127.0.0.1:11434/v1/chat/completions'));
        $this->assertTrue(herikaLocalLlmUrlIsAllowed('http://[::1]:8080/v1/chat/completions'));
        $this->assertFalse(herikaLocalLlmUrlIsAllowed('http://169.254.169.254/latest/meta-data'));
        $this->assertFalse(herikaLocalLlmUrlIsAllowed('https://api.openai.com/v1/chat/completions'));
        $this->assertFalse(herikaLocalLlmUrlIsAllowed('file:///etc/passwd'));
    }

    public function testLocalLlmSetupRequiresAnAllowlistedServerScopeAndModel(): void
    {
        $setup = herikaLocalLlmNormalizeSetup([
            'server_type' => 'ollama',
            'url' => 'http://10.0.0.5:11434/v1/chat/completions',
            'model' => 'qwen2.5:14b',
            'scope' => 'conversations',
            'disable_streaming' => '1',
            'timeout' => '45',
        ]);

        $this->assertSame('ollama', $setup['server_type']);
        $this->assertSame('conversations', $setup['scope']);
        $this->assertTrue($setup['disable_streaming']);
        $this->assertSame(45, $setup['timeout']);
    }

    public function testProfilePresetCatalogAndQuickstartPresetsStayInSync(): void
    {
        $profilePresets = chimProfileSettingsPresetBuiltIns();
        $settingsPresets = chimSettingsPresetBuiltIns();

        $this->assertSame(
            ['builtin:default', 'builtin:local_llm', 'builtin:follower', 'builtin:passive'],
            array_keys($profilePresets)
        );
        $this->assertSame(['builtin:default', 'builtin:local_llm'], array_keys($settingsPresets));

        foreach (['builtin:default', 'builtin:local_llm'] as $id) {
            $this->assertSame(
                $profilePresets[$id]['profile_values'],
                $settingsPresets[$id]['snapshot']['built_in_profile_values']
            );
            $this->assertSame(
                $profilePresets[$id]['profile_overrides'],
                $settingsPresets[$id]['snapshot']['built_in_profile_overrides']
            );
        }
    }

    public function testSettingsPresetsManageAvailabilityWithoutChangingConnectorAssignments(): void
    {
        $connectorStateFields = [
            'PLAYER_RESPEECH',
            'CORE_CONNECTOR_SUMMARY_ENABLED',
            'CORE_CONNECTOR_MEDIUMTERM_ENABLED',
            'SCENE_CLASSIFIER_ENABLED',
            'CORE_CONNECTOR_PROFILES_ENABLED',
            'CORE_CONNECTOR_DIRECTOR_ENABLED',
            'CORE_CONNECTOR_BGL_ENABLED',
            'RELATIONSHIP_SYSTEM_ENABLED',
        ];

        $presets = chimSettingsPresetBuiltIns();
        foreach ($connectorStateFields as $field) {
            $this->assertTrue($presets['builtin:default']['snapshot']['global_settings'][$field]);
        }
        $this->assertTrue($presets['builtin:default']['snapshot']['global_settings']['COMPACT_CHAT_ENABLED']);
        $this->assertTrue($presets['builtin:local_llm']['snapshot']['global_settings']['COMPACT_CHAT_ENABLED']);
        $this->assertTrue($presets['builtin:local_llm']['snapshot']['global_settings']['PLAYER_RESPEECH']);
        $this->assertTrue($presets['builtin:local_llm']['snapshot']['global_settings']['CORE_CONNECTOR_DIRECTOR_ENABLED']);
        foreach (array_diff($connectorStateFields, ['PLAYER_RESPEECH', 'CORE_CONNECTOR_DIRECTOR_ENABLED']) as $field) {
            $this->assertFalse($presets['builtin:local_llm']['snapshot']['global_settings'][$field]);
        }

        $normalized = chimSettingsPresetNormalizeSettings(array_fill_keys($connectorStateFields, true));
        $this->assertSame(array_fill_keys($connectorStateFields, 'true'), $normalized);

        $this->assertSame([], chimSettingsPresetNormalizeSettings([
            'CORE_CONNECTOR_SUMMARY' => 99,
            'CORE_CONNECTOR_DIRECTOR' => 99,
        ]));
    }

    public function testRequestedProfilePresetConversationValues(): void
    {
        $presets = chimProfileSettingsPresetBuiltIns();

        $this->assertTrue($presets['builtin:default']['profile_overrides']['RECHAT_ALLOW_ACTIONS']);
        $this->assertSame(50, $presets['builtin:local_llm']['profile_overrides']['RECHAT_P']);
        $this->assertSame(30, $presets['builtin:local_llm']['profile_overrides']['BORED_EVENT']);
        $this->assertSame(100, $presets['builtin:local_llm']['profile_overrides']['COMBAT_BARK_COOLDOWN']);
        $this->assertSame(60, $presets['builtin:follower']['profile_overrides']['RECHAT_P']);
    }

    public function testCustomProfilePresetSnapshotUsesOnlyManagedSettings(): void
    {
        $snapshot = chimProfileSettingsPresetNormalizeSnapshot([
            'version' => 1,
            'profile_values' => [
                'CONTEXT_HISTORY' => 44,
                'CONTEXT_HISTORY_DIARY' => 55,
                'CONTEXT_HISTORY_DYNAMIC_PROFILE' => 66,
                'MAX_WORDS_LIMIT' => 77,
            ],
            'profile_overrides' => [
                'RECHAT_P' => 42,
                'RECHAT_ALLOW_ACTIONS' => false,
            ],
        ]);

        $this->assertSame(1, $snapshot['version']);
        $this->assertSame(44, $snapshot['profile_values']['CONTEXT_HISTORY']);
        $this->assertCount(4, $snapshot['profile_values']);
        $this->assertArrayNotHasKey('chim_context_mode', $snapshot['profile_values']);
        $this->assertSame(['RECHAT_P' => 42, 'RECHAT_ALLOW_ACTIONS' => false], $snapshot['profile_overrides']);
        $this->assertCount(14, chimProfileSettingsPresetManagedOverrideKeys());
    }

    public function testCustomProfilePresetRejectsUnknownSettingsAndBuiltInNames(): void
    {
        try {
            chimProfileSettingsPresetNormalizeSnapshot([
                'version' => 1,
                'profile_values' => [],
                'profile_overrides' => ['llm_primary_id' => 99],
            ]);
            $this->fail('Unknown settings must be rejected.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Unknown profile preset setting', $e->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);
        chimProfileSettingsPresetValidateName('Follower');
    }
}
