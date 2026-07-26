<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../debug/background_action_handler.php';

final class BackgroundInventoryClaimDb
{
    public string $lastQuery = '';
    public $nextResult = null;

    public function escape($value): string
    {
        return str_replace("'", "''", (string) $value);
    }

    public function fetchOne(string $query)
    {
        $this->lastQuery = $query;
        return $this->nextResult;
    }
}

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

    public function testClaimsEachIdleActionWithAtomicConditionalUpdate(): void
    {
        $db = new BackgroundInventoryClaimDb();
        $db->nextResult = ['id' => 2190];

        self::assertTrue(claimBglInventorySettlement("Camilla Valerius", 37136115, $db));
        self::assertStringContainsString('UPDATE core_npc_master', $db->lastQuery);
        self::assertStringContainsString(
            "background_life_inventory_last_processed_idle_gamets",
            $db->lastQuery
        );
        self::assertStringContainsString('< 37136115', $db->lastQuery);
    }

    public function testRejectsDuplicateOrInvalidIdleActionClaims(): void
    {
        $db = new BackgroundInventoryClaimDb();
        $db->nextResult = null;

        self::assertFalse(claimBglInventorySettlement('Camilla Valerius', 37136115, $db));
        self::assertFalse(claimBglInventorySettlement('Camilla Valerius', 0, $db));
    }
}
