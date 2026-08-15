<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../lib/data_functions.php';

final class DataBeingsInCloseRangeTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($GLOBALS['db']);
    }

    public function testBusyActorsCanRemainPresentWithoutIncludingFarAwayActors(): void
    {
        $GLOBALS['db'] = new class {
            public function fetchAll(string $query): array
            {
                return [[
                    'data' => 'beings in range:Corpulus Vinius (busy)/Gulum-Ei (busy)/RANGROO/Noster Eagle-Eye (far away)'
                ]];
            }
        };

        $this->assertSame(
            '|RANGROO|',
            DataBeingsInCloseRange(true)
        );
        $this->assertSame(
            '|Corpulus Vinius|Gulum-Ei|RANGROO|',
            DataBeingsInCloseRange(true, true)
        );
    }
}
