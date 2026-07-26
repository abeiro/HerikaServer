<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../debug/background_action_handler.php';

final class BackgroundActionLocationDb
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

final class BackgroundActionLocationTest extends TestCase
{
    public function testFuzzyResolutionRanksSimilarityBeforeDistance(): void
    {
        $db = new BackgroundActionLocationDb();
        $npc = [
            'metadata' => [
                'last_coords' => [100, 200],
            ],
        ];

        resolveTravelLocation("Lucan's Dry Goods", $npc, $db);

        self::assertStringContainsString(
            'ORDER BY exact_rank DESC, sim DESC, dist ASC',
            preg_replace('/\s+/', ' ', $db->lastQuery)
        );
    }

    public function testCurrentLocationUsesReportedLocationFormId(): void
    {
        $db = new BackgroundActionLocationDb();
        $db->nextResult = [
            'name' => "Alvor and Sigrid's House",
            'formid' => '117640',
            'sim' => 1,
            'exact_rank' => 4,
        ];
        $npc = [
            'metadata' => json_encode([
                'last_coords' => [
                    'location_formid' => 117640,
                ],
            ]),
        ];

        $result = resolveNpcCurrentLocation($npc, $db);

        self::assertSame("Alvor and Sigrid's House", $result['name']);
        self::assertStringContainsString("formid='117640'", $db->lastQuery);
    }

    public function testLowSimilarityLocationIsNotConfident(): void
    {
        self::assertFalse(isResolvedTravelLocationConfident([
            'formid' => '117642',
            'sim' => 0.083333336,
            'exact_rank' => 0,
        ]));
    }

    public function testExactOrStrongFuzzyLocationIsConfident(): void
    {
        self::assertTrue(isResolvedTravelLocationConfident([
            'formid' => '117640',
            'sim' => 0.60,
            'exact_rank' => 3,
        ]));
        self::assertTrue(isResolvedTravelLocationConfident([
            'formid' => '117641',
            'sim' => 0.80,
            'exact_rank' => 0,
        ]));
    }
}
