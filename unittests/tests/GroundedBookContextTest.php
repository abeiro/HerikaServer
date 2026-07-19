<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..'
    . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'book_context.php');

final class GroundedBookFakeDb
{
    public array $queries = [];
    public array $opened = [];
    public array $completeByTitle = [];
    public array $latestComplete = [];

    public function escape(string $value): string
    {
        return str_replace("'", "''", $value);
    }

    public function fetchOne(string $query): array
    {
        $this->queries[] = $query;
        if (str_contains($query, "LOWER(BTRIM(title)) =")) {
            foreach ($this->completeByTitle as $title => $record) {
                if (str_contains($query, "LOWER(BTRIM('" . $this->escape($title) . "'))")) {
                    return $record;
                }
            }
            return [];
        }
        if (str_contains($query, 'content IS NOT NULL')) {
            return $this->latestComplete;
        }
        return $this->opened;
    }
}

final class GroundedBookContextTest extends TestCase
{
    public function testOpenedTitleIsBoundToContentFromTheSameBook(): void
    {
        $db = new GroundedBookFakeDb();
        $db->opened = ['rowid' => 20, 'title' => "The Locked Room", 'gamets' => 900, 'ts' => 80];
        $db->completeByTitle['The Locked Room'] = [
            'rowid' => 21,
            'title' => 'The Locked Room',
            'content' => 'Only the captured passage.',
            'gamets' => 900,
            'ts' => 81,
        ];
        $db->latestComplete = [
            'rowid' => 19,
            'title' => 'An Older Book',
            'content' => 'Stale content that must not be used.',
            'gamets' => 700,
            'ts' => 60,
        ];

        $book = chimResolveGroundedBook($db, 1000);

        $this->assertTrue($book['available']);
        $this->assertSame('The Locked Room', $book['title']);
        $this->assertSame('Only the captured passage.', $book['content']);
        $queries = implode("\n", $db->queries);
        $this->assertStringContainsString("LOWER(BTRIM('The Locked Room'))", $queries);
        $this->assertStringNotContainsString("LOWER(BTRIM('An Older Book'))", $queries);
    }

    public function testMissingCurrentTextDoesNotFallBackToAnotherBook(): void
    {
        $db = new GroundedBookFakeDb();
        $db->opened = ['rowid' => 20, 'title' => 'Unfinished Upload', 'gamets' => 900, 'ts' => 80];
        $db->latestComplete = [
            'rowid' => 19,
            'title' => 'An Older Book',
            'content' => 'Stale content.',
            'gamets' => 700,
            'ts' => 60,
        ];

        $book = chimResolveGroundedBook($db, 1000);

        $this->assertFalse($book['available']);
        $this->assertSame('Unfinished Upload', $book['title']);
        $this->assertArrayNotHasKey('content', $book);
    }

    public function testContextProvidesExactTextAndExplicitGroundingRules(): void
    {
        $messages = chimBuildGroundedBookContext([
            'available' => true,
            'title' => 'Songs & Swords',
            'content' => "A bard says \"hello\" & leaves.\nThen rests.",
        ]);

        $this->assertCount(1, $messages);
        $context = $messages[0]['content'];
        $this->assertStringContainsString('<title>Songs &amp; Swords</title>', $context);
        $this->assertStringContainsString('A bard says &quot;hello&quot; &amp; leaves.', $context);
        $this->assertStringContainsString('Base the summary and discussion only on the captured text', $context);
        $this->assertStringContainsString('Do not invent missing passages', $context);
    }

    public function testUnavailableContextForbidsInventedSummary(): void
    {
        $messages = chimBuildGroundedBookContext([
            'available' => false,
            'title' => 'Unknown Pages',
        ]);

        $context = $messages[0]['content'];
        $this->assertStringContainsString('<title>Unknown Pages</title>', $context);
        $this->assertStringContainsString('captured_text available="false"', $context);
        $this->assertStringContainsString('Do not invent its contents', $context);
    }

    public function testLegacyClientCanUseLatestCompletePairedRow(): void
    {
        $db = new GroundedBookFakeDb();
        $db->latestComplete = [
            'rowid' => 7,
            'title' => 'Legacy Book',
            'content' => 'Legacy captured text.',
            'gamets' => 500,
            'ts' => 50,
        ];

        $book = chimResolveGroundedBook($db, 600);

        $this->assertTrue($book['available']);
        $this->assertSame('Legacy Book', $book['title']);
        $this->assertSame('Legacy captured text.', $book['content']);
    }
}
