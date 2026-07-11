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
}
