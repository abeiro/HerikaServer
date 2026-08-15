<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../lib/data_functions.php';

final class DiaryMemoryRecallTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['db'] = new class {
            public array $queries = [];

            public function escape($value): string
            {
                return str_replace("'", "''", (string)$value);
            }

            public function query(string $query): bool
            {
                $this->queries[] = $query;
                return true;
            }
        };
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['db'], $GLOBALS['NARRATOR_ONLY_DIARY_ACCESS']);
    }

    public function testNarratorSearchesTheGlobalMemoryBankByDefault(): void
    {
        $this->assertSame('TRUE', dataGetMemoryCompanionConditionSql(''));
    }

    public function testNarratorCanBeRestrictedToItsOwnDiary(): void
    {
        $GLOBALS['NARRATOR_ONLY_DIARY_ACCESS'] = true;

        $this->assertSame(
            "(COALESCE(memory_summary.classifier, '') NOT IN ('diary','auto_diary','backgroundlife_diary')"
                . " OR memory_summary.companions LIKE '%|The Narrator|%'"
                . " OR memory_summary.companions='The Narrator')",
            dataGetMemoryCompanionConditionSql(
                '',
                'memory_summary.companions',
                'memory_summary.classifier'
            )
        );
    }

    public function testDiaryPackingWritesCanonicalOwnerFormat(): void
    {
        PackIntoSummary(true);

        $this->assertCount(1, $GLOBALS['db']->queries);
        $this->assertStringContainsString("'|' || trim(both '|' from trim(speaker)) || '|'", $GLOBALS['db']->queries[0]);
        $this->assertStringContainsString("event in ('diary','auto_diary','backgroundlife_diary')", $GLOBALS['db']->queries[0]);
    }

    public function testNpcRecallMatchesCanonicalAndLegacyDiaryOwners(): void
    {
        $this->assertSame(
            "(memory_summary.companions LIKE '%|Embry|%' OR memory_summary.companions='Embry')",
            dataGetMemoryCompanionConditionSql('Embry', 'memory_summary.companions')
        );
    }

    public function testNpcNameIsEscapedInRecallCondition(): void
    {
        $this->assertSame(
            "(companions LIKE '%|M''aiq''s Friend|%' OR companions='M''aiq''s Friend')",
            dataGetMemoryCompanionConditionSql("M'aiq's Friend")
        );
    }
}
