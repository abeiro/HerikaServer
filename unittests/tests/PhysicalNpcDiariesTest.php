<?php declare(strict_types=1);

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../lib/core/physical_npc_diaries.php';

final class PhysicalDiaryFakeDb
{
    public array $entries = [];
    public array $physicalRows = [];
    public array $books = [];
    public array $inserts = [];
    public array $queries = [];

    public function escape(string $value): string
    {
        return str_replace("'", "''", $value);
    }

    public function fetchAll(string $query): array
    {
        $this->queries[] = $query;
        return $this->entries;
    }

    public function fetchOne(string $query): array
    {
        $this->queries[] = $query;
        if (str_contains($query, 'FROM physical_npc_diaries')) {
            return $this->physicalRows[0] ?? [];
        }
        if (str_contains($query, 'FROM books')) {
            return $this->books[0] ?? [];
        }
        return [];
    }

    public function execQuery(string $query): bool
    {
        $this->queries[] = $query;
        if (str_contains($query, 'INSERT INTO physical_npc_diaries')) {
            $this->physicalRows = [['npc_name' => 'Lydia']];
        }
        return true;
    }

    public function insert(string $table, array $row): bool
    {
        $this->inserts[] = ['table' => $table, 'row' => $row];
        return true;
    }
}

#[RunTestsInSeparateProcesses]
final class PhysicalNpcDiariesTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['db'] = new PhysicalDiaryFakeDb();
        $GLOBALS['db']->entries = [
            [
                'rowid' => 2,
                'topic' => '17th of Last Seed',
                'content' => 'We reached Whiterun before dusk.',
                'location' => 'Whiterun',
                'gamets' => 200,
                'localts' => 20,
            ],
            [
                'rowid' => 1,
                'topic' => '16th of Last Seed',
                'content' => 'The road was quiet.',
                'location' => 'Riverwood',
                'gamets' => 100,
                'localts' => 10,
            ],
        ];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['db'], $GLOBALS['taskId']);
    }

    public function testRefIdsConvertToPapyrusSignedIntegers(): void
    {
        $this->assertSame(0x000A2C8E, chimPhysicalDiaryRefIdToSignedInt('0x000A2C8E'));
        $this->assertSame(-1, chimPhysicalDiaryRefIdToSignedInt('FFFFFFFF'));
        $this->assertNull(chimPhysicalDiaryRefIdToSignedInt('not-a-form'));
    }

    public function testContentUsesChronologicalOrderAndGroundedHeadings(): void
    {
        $entries = chimPhysicalDiaryEntries('Lydia');
        $content = chimPhysicalDiaryContent($entries);

        $this->assertStringStartsWith('[16th of Last Seed - Riverwood]', $content);
        $this->assertStringContainsString('[17th of Last Seed - Whiterun]', $content);
        $this->assertLessThan(
            strpos($content, 'We reached Whiterun'),
            strpos($content, 'The road was quiet')
        );
    }

    public function testFirstMaterializationRendersAndQueuesOneNpcInventoryBook(): void
    {
        $rendered = [];
        $renderer = static function (string $title, string $content) use (&$rendered): void {
            $rendered = compact('title', 'content');
        };

        $result = chimPhysicalDiaryMaterialize('Lydia', '000A2C8E', 500, $renderer);

        $this->assertTrue($result['ok']);
        $this->assertTrue($result['created']);
        $this->assertSame("Lydia's Diary", $rendered['title']);

        $commands = array_values(array_filter(
            $GLOBALS['db']->inserts,
            static fn(array $insert): bool => $insert['table'] === 'responselog'
        ));
        $this->assertCount(1, $commands);
        $this->assertSame(
            "rolecommand|spawnBook@Lydia's Diary@0@666766@0@Lydia's Diary",
            $commands[0]['row']['action']
        );
    }

    public function testExistingPhysicalDiaryRefreshesWithoutSpawningDuplicate(): void
    {
        $GLOBALS['db']->physicalRows = [['npc_name' => 'Lydia']];
        $result = chimPhysicalDiaryMaterialize('Lydia', '000A2C8E', 600, static function (): void {});

        $this->assertTrue($result['ok']);
        $this->assertFalse($result['created']);
        $commands = array_filter(
            $GLOBALS['db']->inserts,
            static fn(array $insert): bool => $insert['table'] === 'responselog'
        );
        $this->assertCount(0, $commands);
    }

    public function testInactiveDiaryRefreshDoesNoRenderingWork(): void
    {
        $rendered = false;
        $result = chimPhysicalDiaryRefreshIfActive('Lydia', 700, static function () use (&$rendered): void {
            $rendered = true;
        });

        $this->assertTrue($result['ok']);
        $this->assertFalse($result['active']);
        $this->assertFalse($rendered);
    }
}
