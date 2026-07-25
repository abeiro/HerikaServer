<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../lib/memory_ranking.php';

final class MemoryRankingTest extends TestCase
{
    public function testCombinedDistanceSelectsBalancedSemanticAndKeywordMatch(): void
    {
        $selected = chimSelectBestHybridMemoryCandidate([
            [
                'rowid' => 1,
                'distance' => 0.20,
                'mixed_distance' => 0.20,
                'summary' => 'Semantic only',
            ],
            [
                'rowid' => 2,
                'distance' => 0.45,
                'mixed_distance' => 0.10,
                'summary' => 'Balanced match',
            ],
        ]);

        $this->assertSame('Balanced match', $selected['summary']);
        $this->assertEqualsWithDelta(1.30, $selected['rank_any'], 0.00001);
        $this->assertEqualsWithDelta(0.95, $selected['rank_all'], 0.00001);
    }

    public function testSemanticDistanceBreaksCombinedScoreTie(): void
    {
        $selected = chimSelectBestHybridMemoryCandidate([
            ['rowid' => 1, 'distance' => 0.35, 'mixed_distance' => 0.10],
            ['rowid' => 2, 'distance' => 0.25, 'mixed_distance' => 0.10],
        ]);

        $this->assertSame(2, $selected['rowid']);
    }

    public function testNewerMemoryBreaksEquivalentScoreTie(): void
    {
        $selected = chimSelectBestHybridMemoryCandidate([
            ['rowid' => 1, 'distance' => 0.25, 'mixed_distance' => 0.10, 'gamets_truncated' => 100],
            ['rowid' => 2, 'distance' => 0.25, 'mixed_distance' => 0.10, 'gamets_truncated' => 200],
        ]);

        $this->assertSame(2, $selected['rowid']);
    }

    public function testFallbackComputesMixedDistanceFromKeywordRank(): void
    {
        $selected = chimSelectBestHybridMemoryCandidate([
            ['rowid' => 1, 'distance' => 0.30, 'rank_fts' => 0.05],
            ['rowid' => 2, 'distance' => 0.40, 'rank_fts' => 0.20],
        ]);

        $this->assertSame(2, $selected['rowid']);
        $this->assertEqualsWithDelta(0.20, $selected['mixed_distance'], 0.00001);
    }

    public function testEmptyCandidateListReturnsNull(): void
    {
        $this->assertNull(chimSelectBestHybridMemoryCandidate([]));
    }

    public function testDatabaseCandidatePoolUsesHybridOrdering(): void
    {
        $source = file_get_contents(__DIR__ . '/../../lib/data_functions.php');

        $this->assertIsString($source);
        $this->assertMatchesRegularExpression(
            '/ORDER BY\s+mixed_distance ASC,\s+distance ASC,\s+gamets_truncated DESC,\s+rowid DESC\s+LIMIT 50/s',
            $source
        );
    }
}
