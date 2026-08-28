<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'logger.php');
require_once(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'data_functions.php');

/**
 * Records every query and replays canned results, so STM's bound arithmetic and generated SQL can
 * be asserted without a database. Covers the whole surface DataShortTermMemoryFor touches:
 * escape() everywhere, fetchOne() for NpcMaster::getByName and isIndividualMemoryEnabledForNpc,
 * fetchAll() for the summary read.
 */
final class StmStubDb
{
    /** @var string[] */
    public array $queries = [];
    /** @var array<int,array<string,mixed>> */
    public array $summaryRows = [];
    /** @var array<string,array<string,mixed>> substring of the query => row to return */
    public array $fetchOneMap = [];
    public bool $throwOnFetchAll = false;

    public function escape($string)
    {
        return str_replace("'", "''", (string) $string);
    }

    public function fetchOne($query)
    {
        $this->queries[] = $query;
        foreach ($this->fetchOneMap as $needle => $row) {
            if (strpos($query, (string) $needle) !== false) {
                return $row;
            }
        }
        return [];
    }

    public function fetchAll($query)
    {
        $this->queries[] = $query;
        if ($this->throwOnFetchAll) {
            throw new RuntimeException('stub database failure');
        }
        return $this->summaryRows;
    }

    public function lastSummaryQuery(): string
    {
        foreach (array_reverse($this->queries) as $q) {
            if (strpos($q, 'FROM memory_summary') !== false) {
                return $q;
            }
        }
        return '';
    }
}

final class ShortTermMemoryTest extends TestCase
{
    private StmStubDb $db;

    protected function setUp(): void
    {
        $this->db = new StmStubDb();
        $GLOBALS['db'] = $this->db;
        $GLOBALS['SHORT_TERM_MEMORY_ENABLED'] = true;
        unset(
            $GLOBALS['SHORT_TERM_MEMORY_MAX'],
            $GLOBALS['CONTEXT_WINDOW_FLOOR'],
            $GLOBALS['STM_CROP_GAMETS']
        );
    }

    protected function tearDown(): void
    {
        unset(
            $GLOBALS['db'],
            $GLOBALS['SHORT_TERM_MEMORY_ENABLED'],
            $GLOBALS['SHORT_TERM_MEMORY_MAX'],
            $GLOBALS['CONTEXT_WINDOW_FLOOR'],
            $GLOBALS['STM_CROP_GAMETS'],
            $GLOBALS['SHORT_TERM_MEMORY_IN_COMPACT_CHAT']
        );
    }

    /**
     * isIndividualMemoryEnabledForNpc() memoises per NPC name in a static that outlives the test,
     * so every test method uses its own name.
     */
    private function summaryRow(int $gamets, string $text): array
    {
        return [
            'summary'          => "#Summary: $text\n\n#Tags: #Whiterun #Lydia #Road",
            'gamets_truncated' => (string) $gamets,
        ];
    }

    public function testReturnsNothingWhenNotEnabled(): void
    {
        unset($GLOBALS['SHORT_TERM_MEMORY_ENABLED']);
        $this->db->summaryRows = [$this->summaryRow(500, 'Something happened.')];

        $this->assertSame([], DataShortTermMemoryFor('StmNpcDisabled'));
        $this->assertSame('', $this->db->lastSummaryQuery(), 'must not query when disabled');
    }

    public function testEnabledAcceptsTheStringOneFromTheProfileCheckbox(): void
    {
        $GLOBALS['SHORT_TERM_MEMORY_ENABLED'] = '1';
        $this->assertTrue(chimShortTermMemoryEnabled());

        $GLOBALS['SHORT_TERM_MEMORY_ENABLED'] = 'true';
        $this->assertTrue(chimShortTermMemoryEnabled());

        $GLOBALS['SHORT_TERM_MEMORY_ENABLED'] = false;
        $this->assertFalse(chimShortTermMemoryEnabled());

        unset($GLOBALS['SHORT_TERM_MEMORY_ENABLED']);
        $this->assertFalse(chimShortTermMemoryEnabled(), 'default must be off');
    }

    public function testReturnsNothingWhenTheCapIsZero(): void
    {
        $GLOBALS['SHORT_TERM_MEMORY_MAX'] = 0;
        $this->db->summaryRows = [$this->summaryRow(500, 'Something happened.')];

        $this->assertSame([], DataShortTermMemoryFor('StmNpcZeroCap'));
        $this->assertSame('', $this->db->lastSummaryQuery());
    }

    public function testQueryMirrorsTheMiddleTermDigestPopulation(): void
    {
        $this->db->summaryRows = [$this->summaryRow(900, 'A thing.')];

        DataShortTermMemoryFor('StmNpcScope');
        $q = $this->db->lastSummaryQuery();

        $this->assertStringContainsString("(scope IS NULL OR scope='global')", $q);
        $this->assertStringContainsString("companions LIKE '%|StmNpcScope|%'", $q);
        $this->assertStringContainsString("companions='StmNpcScope'", $q);
        $this->assertStringContainsString('summary IS NOT NULL', $q);
        $this->assertStringContainsString('ORDER BY gamets_truncated DESC', $q);
        $this->assertStringContainsString('LIMIT 10', $q);
    }

    public function testCapComesFromTheProfileSetting(): void
    {
        $GLOBALS['SHORT_TERM_MEMORY_MAX'] = 3;
        $this->db->summaryRows = [$this->summaryRow(900, 'A thing.')];

        DataShortTermMemoryFor('StmNpcCap');

        $this->assertStringContainsString('LIMIT 3', $this->db->lastSummaryQuery());
    }

    public function testLowerBoundIsTheMiddleTermHightide(): void
    {
        $this->db->fetchOneMap = [
            "npc_name = 'StmNpcHightide'" => [
                'npc_name'      => 'StmNpcHightide',
                'extended_data' => json_encode(['middle_term_memory' => ['4000' => 'old digest', '7250' => 'newer digest']]),
            ],
        ];
        $this->db->summaryRows = [$this->summaryRow(9000, 'After the digest.')];

        DataShortTermMemoryFor('StmNpcHightide');

        $this->assertStringContainsString('gamets_truncated > 7250', $this->db->lastSummaryQuery());
    }

    public function testLowerBoundIsZeroWhenTheNpcHasNoMiddleTermMemory(): void
    {
        $this->db->summaryRows = [$this->summaryRow(900, 'A thing.')];

        DataShortTermMemoryFor('StmNpcNoMtm');

        $this->assertStringContainsString('gamets_truncated > 0', $this->db->lastSummaryQuery());
    }

    public function testNoWindowFloorMeansNoStraddlerSubqueryAndNoCrop(): void
    {
        $this->db->summaryRows = [$this->summaryRow(900, 'A thing.')];

        DataShortTermMemoryFor('StmNpcNoFloor');

        $this->assertStringContainsString('gamets_truncated <= 9223372036854775807', $this->db->lastSummaryQuery());
        $this->assertSame(0, $GLOBALS['STM_CROP_GAMETS']);
    }

    public function testStraddlingSummarySetsTheCropBoundary(): void
    {
        $GLOBALS['CONTEXT_WINDOW_FLOOR'] = 5000;
        // Newest returned summary reaches into the window -> it straddles.
        $this->db->summaryRows = [
            $this->summaryRow(5200, 'Straddles the window edge.'),
            $this->summaryRow(4100, 'Fully before the window.'),
        ];

        DataShortTermMemoryFor('StmNpcStraddle');

        $this->assertStringContainsString('gamets_truncated >= 5000', $this->db->lastSummaryQuery());
        $this->assertSame(5200, $GLOBALS['STM_CROP_GAMETS']);
    }

    public function testSummariesEntirelyBeforeTheWindowDoNotCropIt(): void
    {
        $GLOBALS['CONTEXT_WINDOW_FLOOR'] = 5000;
        $this->db->summaryRows = [
            $this->summaryRow(4800, 'Ends before the window starts.'),
            $this->summaryRow(4100, 'Older still.'),
        ];

        DataShortTermMemoryFor('StmNpcNoStraddle');

        $this->assertSame(0, $GLOBALS['STM_CROP_GAMETS'], 'nothing reaches the window, so nothing to crop');
    }

    public function testRendersOldestFirstAndStripsTheSummaryMetadata(): void
    {
        $this->db->summaryRows = [
            $this->summaryRow(900, 'Lydia complained about the cold.'),
            $this->summaryRow(400, 'They left Riverwood at dawn.'),
        ];

        $out = DataShortTermMemoryFor('StmNpcRender');

        $this->assertCount(2, $out);
        $this->assertSame('user', $out[0]['role']);
        $this->assertStringContainsString('They left Riverwood at dawn.', $out[0]['content'], 'oldest must come first');
        $this->assertStringContainsString('Lydia complained about the cold.', $out[1]['content']);
        foreach ($out as $entry) {
            $this->assertStringStartsWith('(Earlier events - ', $entry['content']);
            $this->assertStringNotContainsString('#Summary:', $entry['content']);
            $this->assertStringNotContainsString('#Tags:', $entry['content']);
        }
    }

    public function testSkipsRowsThatAreNothingButMetadata(): void
    {
        $this->db->summaryRows = [
            ['summary' => "#Summary:\n\n#Tags: #Nothing", 'gamets_truncated' => '700'],
            $this->summaryRow(600, 'A real one.'),
        ];

        $out = DataShortTermMemoryFor('StmNpcEmptyRow');

        $this->assertCount(1, $out);
        $this->assertStringContainsString('A real one.', $out[0]['content']);
    }

    public function testFailsOpenWhenTheDatabaseThrows(): void
    {
        $this->db->throwOnFetchAll = true;

        $this->assertSame([], DataShortTermMemoryFor('StmNpcThrows'));
    }

    public function testCompactChatOptionDefaultsToOn(): void
    {
        unset($GLOBALS['SHORT_TERM_MEMORY_IN_COMPACT_CHAT']);
        $this->assertTrue(chimShortTermMemoryInCompactChatEnabled(), 'Compact Chat must not silently suppress STM');
    }

    public function testCompactChatOptionAcceptsTheStringsTheSettingsEditorWrites(): void
    {
        $GLOBALS['SHORT_TERM_MEMORY_IN_COMPACT_CHAT'] = 'false';
        $this->assertFalse(chimShortTermMemoryInCompactChatEnabled());

        $GLOBALS['SHORT_TERM_MEMORY_IN_COMPACT_CHAT'] = '0';
        $this->assertFalse(chimShortTermMemoryInCompactChatEnabled());

        $GLOBALS['SHORT_TERM_MEMORY_IN_COMPACT_CHAT'] = true;
        $this->assertTrue(chimShortTermMemoryInCompactChatEnabled());

        unset($GLOBALS['SHORT_TERM_MEMORY_IN_COMPACT_CHAT']);
    }

    public function testAttachStripsTheInternalStampWhenShortTermMemoryIsSkipped(): void
    {
        $window = [
            ['role' => 'user', 'content' => 'a', '_g' => 100],
            ['role' => 'user', 'content' => 'b', '_g' => 200],
        ];

        $out = chimAttachShortTermMemoryToWindow($window, 'AttachSkippedNpc', '', false);

        $this->assertCount(2, $out);
        $this->assertSame([], $this->db->queries);
        $this->assertSame('a', $out[0]['content']);
        foreach ($out as $entry) {
            $this->assertArrayNotHasKey('_g', $entry);
        }
    }

    public function testAttachLeavesEntriesWithoutAStampAlone(): void
    {
        $out = chimAttachShortTermMemoryToWindow(
            [['role' => 'user', 'content' => 'x']],
            'AttachPlainNpc',
            '',
            false
        );

        $this->assertSame([['role' => 'user', 'content' => 'x']], $out);
    }

    public function testAttachPrependsSummariesAndCropsCoveredWindowEntries(): void
    {
        $GLOBALS['CONTEXT_WINDOW_FLOOR'] = 500;
        $this->db->summaryRows = [
            $this->summaryRow(600, 'newer scene'),
            $this->summaryRow(400, 'older scene'),
        ];

        $window = [
            ['role' => 'user', 'content' => 'inside the straddler', '_g' => 550],
            ['role' => 'user', 'content' => 'after the straddler',  '_g' => 700],
        ];

        $out = chimAttachShortTermMemoryToWindow($window, 'AttachCropNpc', '', true);

        // Newest summary (600) reaches into the window (floor 500), so the crop is 600.
        $this->assertSame(600, intval($GLOBALS['STM_CROP_GAMETS']));

        $contents = array_column($out, 'content');
        $this->assertStringContainsString('older scene', $contents[0]);
        $this->assertStringContainsString('newer scene', $contents[1]);
        $this->assertSame('after the straddler', $contents[2]);
        $this->assertNotContains('inside the straddler', $contents);

        foreach ($out as $entry) {
            $this->assertArrayNotHasKey('_g', $entry);
        }
    }

    public function testAttachLeavesTheWindowIntactWhenNoSummaryReachesIt(): void
    {
        $GLOBALS['CONTEXT_WINDOW_FLOOR'] = 900;
        $this->db->summaryRows = [$this->summaryRow(300, 'long ago')];

        $window = [['role' => 'user', 'content' => 'recent', '_g' => 950]];

        $out = chimAttachShortTermMemoryToWindow($window, 'AttachNoCropNpc', '', true);

        $this->assertSame(0, intval($GLOBALS['STM_CROP_GAMETS']));
        $this->assertCount(2, $out);
        $this->assertSame('recent', $out[1]['content']);
    }
}
