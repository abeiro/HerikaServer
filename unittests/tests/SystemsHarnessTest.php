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

    public function testScenarioManifestContainsGeneratedAndExistingCoverage(): void
    {
        $scenarios = chimHarnessScenarios();

        $this->assertArrayHasKey('generated_variety', $scenarios);
        $this->assertArrayHasKey('existing_soak', $scenarios);
        $this->assertArrayHasKey('mixed_soak', $scenarios);
        $this->assertCount(3, $scenarios['generated_variety']['generated']);
        $this->assertSame([], $scenarios['existing_soak']['generated']);
    }
}
