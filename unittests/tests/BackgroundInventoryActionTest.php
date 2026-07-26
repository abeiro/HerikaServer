<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../debug/background_action_handler.php';

final class BackgroundInventoryActionTest extends TestCase
{
    public function testKeepsValidProducedAndConsumeActions(): void
    {
        self::assertSame(
            ['Produced:0x00064B3F:2', 'Consume:00034CDF:1'],
            normalizeBglInventoryActions([
                'Produced:0x00064B3F:2',
                'Consume:00034CDF:1',
            ])
        );
    }

    public function testTreatsDoNothingAsNoInventoryAction(): void
    {
        self::assertSame([], normalizeBglInventoryActions(['DoNothing']));
    }

    public function testRejectsMalformedOrUnsafeActions(): void
    {
        self::assertSame(
            [],
            normalizeBglInventoryActions([
                'Produced',
                'Produced:not-a-form-id:1',
                'Consume:00034CDF:0',
                'DeleteEverything:00034CDF:99',
                ['Produced:00034CDF:1'],
            ])
        );
    }
}
