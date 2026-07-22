<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../tts/tts-piper-tts.php';

final class PiperTtsEndpointTest extends TestCase
{
    public function testAppendsSynthesisRouteToBaseUrl(): void
    {
        $this->assertSame(
            'http://127.0.0.1:5000/synthesize',
            piper_tts_synthesis_url('http://127.0.0.1:5000/')
        );
    }

    public function testPreservesExplicitSynthesisRoute(): void
    {
        $this->assertSame(
            'http://127.0.0.1:5000/synthesize',
            piper_tts_synthesis_url('http://127.0.0.1:5000/synthesize')
        );
    }
}
