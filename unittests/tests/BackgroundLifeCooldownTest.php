<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'settings.php');

final class BackgroundLifeCooldownTest extends TestCase
{
    private array $originalGlobals = [];

    protected function setUp(): void
    {
        foreach (['BGL_TRIGGER_HOURS', 'BGL_TRIGGER_DAYS'] as $key) {
            $this->originalGlobals[$key] = [
                'exists' => array_key_exists($key, $GLOBALS),
                'value' => $GLOBALS[$key] ?? null,
            ];
            unset($GLOBALS[$key]);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->originalGlobals as $key => $original) {
            if ($original['exists']) {
                $GLOBALS[$key] = $original['value'];
            } else {
                unset($GLOBALS[$key]);
            }
        }
    }

    public function testDefaultsToTwentyFourHours(): void
    {
        $this->assertSame(24.0, chimGetBackgroundLifeTriggerHours());
    }

    public function testReadsHoursSettingDirectly(): void
    {
        $GLOBALS['BGL_TRIGGER_HOURS'] = '36';

        $this->assertSame(36.0, chimGetBackgroundLifeTriggerHours());
    }

    public function testConvertsLegacyDaysToHours(): void
    {
        $GLOBALS['BGL_TRIGGER_DAYS'] = '5';

        $this->assertSame(120.0, chimGetBackgroundLifeTriggerHours());
    }

    public function testHoursSettingTakesPrecedenceOverLegacyDays(): void
    {
        $GLOBALS['BGL_TRIGGER_HOURS'] = '24';
        $GLOBALS['BGL_TRIGGER_DAYS'] = '5';

        $this->assertSame(24.0, chimGetBackgroundLifeTriggerHours());
    }

    public function testCooldownIsClampedToSupportedRange(): void
    {
        $this->assertSame(1.0, chimNormalizeBackgroundLifeTriggerHours(0));
        $this->assertSame(720.0, chimNormalizeBackgroundLifeTriggerHours(1000));
    }

    public function testMissingOrNonPositiveTimestampIsDueImmediately(): void
    {
        $this->assertTrue(chimIsBackgroundLifeDue(null, -1000.0));
        $this->assertTrue(chimIsBackgroundLifeDue('', -1000.0));
        $this->assertTrue(chimIsBackgroundLifeDue(0, -1000.0));
        $this->assertTrue(chimIsBackgroundLifeDue(-1, -1000.0));
    }

    public function testPositiveTimestampUsesConfiguredThreshold(): void
    {
        $this->assertTrue(chimIsBackgroundLifeDue(500, 1000.0));
        $this->assertFalse(chimIsBackgroundLifeDue(1000, 1000.0));
        $this->assertFalse(chimIsBackgroundLifeDue(1500, 1000.0));
    }

    public function testSchedulerUsesConfiguredHoursWithoutLegacyOneDayMinimum(): void
    {
        $source = file_get_contents(
            __DIR__ . '/../../service/processors/middleterm/entrypoint.php'
        );

        $this->assertIsString($source);
        $this->assertGreaterThanOrEqual(
            2,
            substr_count($source, 'chimIsBackgroundLifeDue($mwdata["background_life_last_updated"] ?? null, $bglTriggerHoursAgoGamets)')
        );
        $this->assertStringNotContainsString('1-day HARDCODED RULE', $source);
        $this->assertStringNotContainsString("\$GLOBALS['BGL_TRIGGER_DAYS']", $source);
    }

    public function testSchedulerDoesNotWriteRoutineSkipDiagnosticsToServiceLog(): void
    {
        $source = file_get_contents(
            __DIR__ . '/../../service/processors/middleterm/entrypoint.php'
        );

        $this->assertIsString($source);
        $this->assertStringNotContainsString('error_log("[BGL] Checking', $source);
        $this->assertStringNotContainsString('error_log("[BGL] Skipping', $source);
        $this->assertStringNotContainsString('error_log("[BGL] (Passive) Skipping', $source);
        $this->assertStringContainsString('error_log("[BGL] Event for', $source);
        $this->assertStringContainsString('error_log("[BGL] {$npc["npc_name"]} has been near a player for more than 10 checks. Issuing INSTRUCTION")', $source);
    }
}
