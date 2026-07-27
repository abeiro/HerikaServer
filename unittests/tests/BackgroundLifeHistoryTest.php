<?php

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/lib/background_life_history.php';

final class BackgroundLifeHistoryTest extends TestCase
{
    public function testOriginalNamedDestinationWinsOverWireFormId(): void
    {
        $action = [
            'fullcall' => 'Camilla Valerius|command|TravelToRaw@117644',
            'original' => "Camilla Valerius|command|TravelTo@Sleeping Giant Inn\r\n",
        ];

        $db = new class {
            public function fetchOne(string $query): array
            {
                throw new RuntimeException('The named destination should not require a lookup.');
            }
        };

        $this->assertSame(
            'Sleeping Giant Inn',
            chimBglResolveActionDestination($action, $db)
        );
    }

    public function testNumericDestinationFallsBackToLocationLookup(): void
    {
        $action = [
            'fullcall' => 'Camilla Valerius|command|TravelToRaw@117644',
            'original' => 'backgroundaction',
        ];

        $db = new class {
            public string $query = '';

            public function fetchOne(string $query): array
            {
                $this->query = $query;
                return ['name' => 'Sleeping Giant Inn'];
            }
        };

        $this->assertSame(
            'Sleeping Giant Inn',
            chimBglResolveActionDestination($action, $db)
        );
        $this->assertStringContainsString('formid=117644', $db->query);
    }

    public function testNamedFullcallRemainsSupported(): void
    {
        $action = [
            'fullcall' => 'TravelTo:Whiterun Stables',
            'original' => '',
        ];

        $db = new class {
            public function fetchOne(string $query): array
            {
                throw new RuntimeException('The named destination should not require a lookup.');
            }
        };

        $this->assertSame(
            'Whiterun Stables',
            chimBglResolveActionDestination($action, $db)
        );
    }
}
