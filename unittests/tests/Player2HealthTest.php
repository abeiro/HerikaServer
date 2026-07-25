<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../lib/player2_health.php';

final class Player2HealthTest extends TestCase
{
    public function testNormalizesSupportedConnectorUrls(): void
    {
        self::assertSame(
            'http://127.0.0.1:4315/v1/health',
            chimPlayer2HealthNormalizeUrl('http://127.0.0.1:4315/v1/chat/completions')
        );
        self::assertSame(
            'https://player2.example:443/v1/health',
            chimPlayer2HealthNormalizeUrl('https://player2.example:443/docs')
        );
    }

    public function testRequiresFreshGameActivityAndCurrentArmedSession(): void
    {
        $state = [
            'last_activity' => 950,
            'session_started' => 900,
            'active_session' => 900,
            'last_used' => 940,
            'last_attempt' => 900,
            'health_url' => 'http://127.0.0.1:4315/v1/health',
        ];

        self::assertTrue(chimPlayer2HealthShouldPing($state, 1000));
        self::assertFalse(chimPlayer2HealthShouldPing($state, 959));

        $state['last_activity'] = 700;
        self::assertFalse(chimPlayer2HealthShouldPing($state, 1000));

        $state['last_activity'] = 950;
        $state['active_session'] = 800;
        self::assertFalse(chimPlayer2HealthShouldPing($state, 1000));

        $state['active_session'] = 900;
        $state['last_used'] = 699;
        self::assertFalse(chimPlayer2HealthShouldPing($state, 1000));
    }
}
