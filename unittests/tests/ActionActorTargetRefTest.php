<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../lib/data_functions.php';

final class ActionActorTargetRefTest extends TestCase
{
    public function testExplicitActorRefTargetIsCanonicalized(): void
    {
        $this->assertSame(
            'Alvor [RefID: 00013475]',
            chimNormalizeExplicitActorRefTarget('Alvor [RefID: 0x13475]')
        );
        $this->assertSame(
            '[RefID: FF001234]',
            chimNormalizeExplicitActorRefTarget('[refid: ff001234]')
        );
    }

    public function testOrdinaryNamesRemainForExistingNameResolver(): void
    {
        $this->assertSame('', chimNormalizeExplicitActorRefTarget('Camilla Valerius'));
        $this->assertSame('', chimNormalizeExplicitActorRefTarget('Invalid [RefID: NOTHEX]'));
    }
}
