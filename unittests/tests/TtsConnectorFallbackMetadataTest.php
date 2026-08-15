<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'tts_connector.class.php');

final class TtsConnectorFallbackMetadataTest extends TestCase
{
    public function testUsesScalarFallbackVoices(): void
    {
        $connector = new TTSConnector();
        $connectorData = [
            'driver' => 'pockettts',
            'metadata' => json_encode([
                'fallback_male' => 'malecustom',
                'fallback_female' => 'femalecustom',
            ]),
        ];

        $this->assertSame('malecustom', $connector->getFallbackVoiceForGender($connectorData, 'male'));
        $this->assertSame('femalecustom', $connector->getFallbackVoiceForGender($connectorData, 'female'));
    }

    public function testResolvesLegacyFieldSchemaDefaults(): void
    {
        $connector = new TTSConnector();
        $connectorData = [
            'driver' => 'pockettts',
            'metadata' => json_encode([
                'fallback_male' => ['type' => 'string', 'default' => 'malelegacy'],
                'fallback_female' => ['type' => 'string', 'default' => 'femalelegacy'],
            ]),
        ];

        $this->assertSame('malelegacy', $connector->getFallbackVoiceForGender($connectorData, 'male'));
        $this->assertSame('femalelegacy', $connector->getFallbackVoiceForGender($connectorData, 'female'));
    }

    public function testMalformedLegacyFieldSchemasUseSharedDefaults(): void
    {
        $connector = new TTSConnector();
        $connectorData = [
            'driver' => 'pockettts',
            'metadata' => json_encode([
                'fallback_male' => ['type' => 'string'],
                'fallback_female' => ['type' => 'string'],
            ]),
        ];

        $this->assertSame('malenord', $connector->getFallbackVoiceForGender($connectorData, 'male'));
        $this->assertSame('femalenord', $connector->getFallbackVoiceForGender($connectorData, 'female'));
    }
}
