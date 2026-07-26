<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../lib/data_functions.php';

final class MoveToTargetGuardTest extends TestCase
{
    public function testExactVisibleActorIsAccepted(): void
    {
        $this->assertSame(
            'Camilla Valerius',
            chimResolveMoveToActorTarget(
                'camilla valerius',
                ['Alvor', 'Camilla Valerius'],
                'RANGROO',
                ''
            )
        );
    }

    public function testPlayerIsAcceptedWithoutNearbyActorEntry(): void
    {
        $this->assertSame(
            'RANGROO',
            chimResolveMoveToActorTarget('rangroo', [], 'RANGROO', '')
        );
    }

    public function testSmallActorNameTypoUsesClosestMatch(): void
    {
        $this->assertSame(
            'Faendal',
            chimResolveMoveToActorTarget('Faendall', ['Alvor'], 'RANGROO', 'Faendal')
        );
    }

    public function testUnresolvedPlaceOrObjectIsRejected(): void
    {
        $this->assertSame(
            '',
            chimResolveMoveToActorTarget(
                'Blacksmith Forge',
                ['Alvor', 'Camilla Valerius'],
                'RANGROO',
                'Alvor'
            )
        );
    }
}
