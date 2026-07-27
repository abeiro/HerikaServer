<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class BackgroundProcessorSingletonTest extends TestCase
{
    private string $script;

    protected function setUp(): void
    {
        $path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'service' . DIRECTORY_SEPARATOR . 'start.sh';
        $script = file_get_contents($path);
        $this->assertIsString($script);
        $this->script = $script;
    }

    public function testAcquiresProcessLifetimeLockBeforePortProbe(): void
    {
        $lockPosition = strpos($this->script, 'flock -n 9');
        $portProbePosition = strpos($this->script, 'nc -z localhost');

        $this->assertNotFalse($lockPosition);
        $this->assertNotFalse($portProbePosition);
        $this->assertLessThan($portProbePosition, $lockPosition);
        $this->assertStringContainsString('exec 9>"$LOCK_FILE"', $this->script);
    }

    public function testFailsClosedWhenFlockIsUnavailable(): void
    {
        $this->assertStringContainsString('command -v flock', $this->script);
        $this->assertStringContainsString(
            'Cannot start background processor: flock is unavailable.',
            $this->script
        );
    }
}
