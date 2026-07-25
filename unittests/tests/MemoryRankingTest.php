<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../lib/memory_ranking.php';

final class MemoryRankingTest extends TestCase
{
    public function testBuildsBoundedRecallAuditInHybridRankOrder(): void
    {
        $candidates = [
            [
                'rowid' => 10,
                'distance' => 0.20,
                'rank_fts' => 0.01,
                'mixed_distance' => 0.19,
                'summary' => 'Semantic-only candidate',
            ],
            [
                'rowid' => 11,
                'distance' => 0.30,
                'rank_fts' => 0.25,
                'mixed_distance' => 0.05,
                'summary' => 'Hybrid winner',
            ],
        ];
        $selected = chimSelectBestHybridMemoryCandidate($candidates);

        $audit = chimBuildMemoryRecallAuditCandidates($candidates, $selected, 1);

        $this->assertCount(1, $audit);
        $this->assertSame('11', $audit[0]['rowid']);
        $this->assertTrue($audit[0]['selected']);
        $this->assertEqualsWithDelta(0.30, $audit[0]['semantic_distance'], 0.00001);
        $this->assertEqualsWithDelta(0.25, $audit[0]['keyword_score'], 0.00001);
        $this->assertEqualsWithDelta(1.35, $audit[0]['hybrid_score'], 0.00001);
        $this->assertSame('Hybrid winner', $audit[0]['memory_preview']);
    }

    public function testRecallAuditSkipsInvalidCandidatesAndTruncatesMemoryText(): void
    {
        $audit = chimBuildMemoryRecallAuditCandidates([
            ['rowid' => 1, 'summary' => 'missing distance'],
            [
                'rowid' => 2,
                'distance' => 0.4,
                'rank_fts' => 0.0,
                'summary' => str_repeat('x', 400),
            ],
        ], ['rowid' => 2]);

        $this->assertCount(1, $audit);
        $this->assertSame(300, strlen($audit[0]['memory_preview']));
    }

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

    public function testRecallAuditUsesTheSameNewerMemoryTieBreakAsSelection(): void
    {
        $candidates = [
            ['rowid' => 10, 'distance' => 0.25, 'mixed_distance' => 0.10, 'gamets_truncated' => 100],
            ['rowid' => 5, 'distance' => 0.25, 'mixed_distance' => 0.10, 'gamets_truncated' => 200],
        ];
        $selected = chimSelectBestHybridMemoryCandidate($candidates);

        $audit = chimBuildMemoryRecallAuditCandidates($candidates, $selected, 1);

        $this->assertCount(1, $audit);
        $this->assertSame('5', $audit[0]['rowid']);
        $this->assertTrue($audit[0]['selected']);
        $this->assertSame(200.0, $audit[0]['game_time']);
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
