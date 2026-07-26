<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../lib/background_life_connector.php';

final class BackgroundLifeConnectorTest extends TestCase
{
    public function testConfiguredConnectorTakesPriority(): void
    {
        $result = chimResolveBackgroundLifeConnector(
            7,
            ['llm_primary_id' => 8],
            ['llm_primary_id' => 9],
            static fn(int $id): array => ['id' => $id, 'driver' => 'openrouterjson']
        );

        self::assertSame(7, $result['data']['id']);
        self::assertSame('Background Life setting', $result['source']);
    }

    public function testFallsBackToNpcProfileWhenSettingIsEmpty(): void
    {
        $result = chimResolveBackgroundLifeConnector(
            '',
            ['llm_primary_id' => 8],
            ['llm_primary_id' => 9],
            static fn(int $id): array => ['id' => $id, 'driver' => 'openrouterjson']
        );

        self::assertSame(8, $result['data']['id']);
        self::assertSame('NPC profile primary LLM', $result['source']);
    }

    public function testFallsBackToDefaultProfileWhenNpcConnectorIsInvalid(): void
    {
        $result = chimResolveBackgroundLifeConnector(
            null,
            ['llm_primary_id' => 8],
            ['llm_primary_id' => 9],
            static fn(int $id): array => $id === 8
                ? ['id' => 8, 'driver' => '']
                : ['id' => 9, 'driver' => 'openrouterjson']
        );

        self::assertSame(9, $result['data']['id']);
        self::assertSame('default profile primary LLM', $result['source']);
    }

    public function testFailsCleanlyWithoutAnyUsableConnector(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Background Life has no usable LLM connector');

        chimResolveBackgroundLifeConnector(
            null,
            [],
            [],
            static fn(int $id): array => []
        );
    }
}
