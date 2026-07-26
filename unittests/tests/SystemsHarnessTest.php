<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../lib/chim_systems_harness.php';

final class SystemsHarnessTest extends TestCase
{
    public function testDurationIsBoundedForSafeSoakRuns(): void
    {
        $this->assertSame(5, chimHarnessNormalizeDuration(0));
        $this->assertSame(30, chimHarnessNormalizeDuration(30));
        $this->assertSame(480, chimHarnessNormalizeDuration(900));
    }

    public function testBackgroundLifeCadenceIsBoundedForSoakRuns(): void
    {
        $this->assertSame(24.0, chimHarnessNormalizeBglTriggerHours(null));
        $this->assertSame(1.0, chimHarnessNormalizeBglTriggerHours(0));
        $this->assertSame(12.0, chimHarnessNormalizeBglTriggerHours(12));
        $this->assertSame(24.0, chimHarnessNormalizeBglTriggerHours(120));
    }

    public function testFinalStatusPreservesFailureAfterCleanup(): void
    {
        $this->assertSame('completed', chimHarnessFinalStatus([]));
        $this->assertSame('completed', chimHarnessFinalStatus(['error' => '']));
        $this->assertSame('failed', chimHarnessFinalStatus(['error' => 'Provisioning failed']));
    }

    public function testTemporaryActionRestoreEntriesAreStrictlyScoped(): void
    {
        $entries = chimHarnessTemporaryActionRestoreEntries([
            'temporary_action_overrides' => [
                'MoveTo' => [
                    'table' => 'core_action_custom',
                    'id' => 15,
                    'metadata' => ['custom_config' => ['confirmation_required' => true]],
                    'restore_required' => true,
                ],
                'AlreadyRestored' => [
                    'table' => 'core_action_custom',
                    'id' => 16,
                    'metadata' => [],
                    'restore_required' => true,
                    'restored_at' => 123,
                ],
                'UnsafeTable' => [
                    'table' => 'core_action',
                    'id' => 17,
                    'metadata' => [],
                    'restore_required' => true,
                ],
            ],
        ]);

        $this->assertSame(['MoveTo'], array_keys($entries));
        $this->assertSame(15, $entries['MoveTo']['id']);
        $this->assertTrue($entries['MoveTo']['metadata']['custom_config']['confirmation_required']);
    }

    public function testRefIdsAreNormalizedWithoutChangingIdentity(): void
    {
        $this->assertSame('0001A6C8', chimHarnessNormalizeRefId('0x1a6c8'));
        $this->assertSame('FF001234', chimHarnessNormalizeRefId('ff001234'));
        $this->assertSame('', chimHarnessNormalizeRefId('not-a-form'));
    }

    public function testScenarioNamesAreUniquePerRun(): void
    {
        $scenario = [
            'generated' => [
                ['key' => 'one', 'name' => 'CHIMTEST_{RUN}_One'],
                ['key' => 'two', 'name' => 'CHIMTEST_{RUN}_Two'],
            ],
        ];

        $actors = chimHarnessExpandGeneratedActors($scenario, 42);

        $this->assertSame('CHIMTEST_0042_One', $actors[0]['name']);
        $this->assertSame('CHIMTEST_0042_Two', $actors[1]['name']);
    }

    public function testOnlyPrivateAddressesCanControlTheHarness(): void
    {
        $this->assertTrue(chimHarnessIsPrivateRequest('127.0.0.1'));
        $this->assertTrue(chimHarnessIsPrivateRequest('192.168.169.218'));
        $this->assertFalse(chimHarnessIsPrivateRequest('8.8.8.8'));
    }

    public function testMetricActorScopeUsesExactEscapedNames(): void
    {
        $hadDb = array_key_exists('db', $GLOBALS);
        $originalDb = $GLOBALS['db'] ?? null;
        $GLOBALS['db'] = new class {
            public function escape(string $value): string
            {
                return str_replace("'", "''", $value);
            }
        };

        try {
            $scope = chimHarnessMetricActorScope([
                ['actor_name' => 'Camilla Valerius'],
                ['actor_name' => "J'zara"],
                ['actor_name' => 'Camilla Valerius'],
                ['actor_name' => ''],
            ]);
        } finally {
            if ($hadDb) {
                $GLOBALS['db'] = $originalDb;
            } else {
                unset($GLOBALS['db']);
            }
        }

        $this->assertSame(['Camilla Valerius', "J'zara"], $scope['names']);
        $this->assertStringContainsString("'Camilla Valerius'", $scope['memory']);
        $this->assertStringContainsString("'J''zara'", $scope['memory']);
        $this->assertStringContainsString('string_to_array', $scope['summaries']);
        $this->assertStringContainsString("ARRAY['Camilla Valerius', 'J''zara']::text[]", $scope['summaries']);
    }

    public function testEmptyMetricActorScopeCannotMatchUnrelatedRows(): void
    {
        $scope = chimHarnessMetricActorScope([]);

        $this->assertSame([], $scope['names']);
        $this->assertSame('FALSE', $scope['memory']);
        $this->assertSame('FALSE', $scope['summaries']);
    }

    public function testScenarioManifestContainsGeneratedAndExistingCoverage(): void
    {
        $scenarios = chimHarnessScenarios();

        $this->assertArrayHasKey('generated_variety', $scenarios);
        $this->assertArrayHasKey('existing_soak', $scenarios);
        $this->assertArrayHasKey('mixed_soak', $scenarios);
        $this->assertCount(3, $scenarios['generated_variety']['generated']);
        $this->assertSame([], $scenarios['existing_soak']['generated']);

        $supportedSpawnRaces = ['nord', 'imperial', 'redguard', 'breton', 'orc', 'argonian'];
        foreach ($scenarios as $scenario) {
            foreach ($scenario['generated'] as $actor) {
                $this->assertContains(strtolower((string)$actor['race']), $supportedSpawnRaces);
            }
        }
    }
}
