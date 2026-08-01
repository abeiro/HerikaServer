<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."lib".DIRECTORY_SEPARATOR."minimet5_service.php");

final class MiniMeServiceUrlTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($GLOBALS['FEATURES']);
    }

    public function testUsesConfiguredRemoteServiceUrl(): void
    {
        $GLOBALS['FEATURES']['MEMORY_EMBEDDING']['TXTAI_URL'] = 'http://192.168.1.40:8082/';

        $this->assertSame('http://192.168.1.40:8082', _minimeServiceBaseUrl());
        $this->assertSame(
            'http://192.168.1.40:8082/extract?text=hello%20world',
            _minimeServiceEndpoint('extract', ['text' => 'hello world'])
        );
    }

    public function testPreservesHttpsAndPathPrefixes(): void
    {
        $GLOBALS['FEATURES']['MEMORY_EMBEDDING']['TXTAI_URL'] = 'https://ai.example.test/services/minime/';

        $this->assertSame(
            'https://ai.example.test/services/minime/topic?text=Whiterun',
            _minimeServiceEndpoint('/topic', ['text' => 'Whiterun'])
        );
    }

    public function testFallsBackToLocalDefaultForInvalidUrl(): void
    {
        $GLOBALS['FEATURES']['MEMORY_EMBEDDING']['TXTAI_URL'] = 'not a URL';

        $this->assertSame('http://127.0.0.1:8082', _minimeServiceBaseUrl());
    }
}
