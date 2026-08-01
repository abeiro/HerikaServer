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
        if (str_contains($query, 'INSERT INTO physical_npc_diaries')) {
            if (!empty($this->physicalRows)) {
                return [];
            }
            $this->physicalRows = [['npc_name' => 'Lydia']];
            return $this->physicalRows[0];
        }
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
        $parts = explode('@', $commands[0]['row']['action']);
        $this->assertSame("rolecommand|spawnBook@Lydia's Diary@0@666766@0", implode('@', array_slice($parts, 0, 5)));
        $this->assertStringStartsWith('b64:', $parts[5]);
        $this->assertSame(chimPhysicalDiaryContent(array_reverse($GLOBALS['db']->entries)), base64_decode(substr($parts[5], 4), true));
    }

    public function testExistingPhysicalDiaryQueuesInventoryEnsureCommand(): void
    {
        $GLOBALS['db']->physicalRows = [['npc_name' => 'Lydia']];
        $result = chimPhysicalDiaryMaterialize('Lydia', '000A2C8E', 600, static function (): void {});

        $this->assertTrue($result['ok']);
        $this->assertFalse($result['created']);
        $commands = array_values(array_filter(
            $GLOBALS['db']->inserts,
            static fn(array $insert): bool => $insert['table'] === 'responselog'
        ));
        $this->assertCount(1, $commands);
        $parts = explode('@', $commands[0]['row']['action']);
        $this->assertSame("rolecommand|spawnBook@Lydia's Diary@0@666766@0", implode('@', array_slice($parts, 0, 5)));
        $this->assertStringStartsWith('b64:', $parts[5]);
        $this->assertSame(chimPhysicalDiaryContent(array_reverse($GLOBALS['db']->entries)), base64_decode(substr($parts[5], 4), true));
    }

    public function testProfileSettingDefaultsOffWithoutRendering(): void
    {
        $rendered = false;
        $result = chimPhysicalDiarySyncForNpc(
            'Lydia',
            700,
            static function () use (&$rendered): void {
                $rendered = true;
            },
            static fn(): array => ['refid' => '000A2C8E', 'profile_metadata' => '{}']
        );

        $this->assertTrue($result['ok']);
        $this->assertFalse($result['enabled']);
        $this->assertFalse($rendered);
    }

    public function testEnabledProfileCreatesAndRefreshesOneDiary(): void
    {
        $resolver = static fn(): array => [
            'refid' => '000A2C8E',
            'profile_metadata' => '{"MATERIALIZE_DIARY_ENABLED":true}',
        ];

        $first = chimPhysicalDiarySyncForNpc('Lydia', 700, static function (): void {}, $resolver);
        $second = chimPhysicalDiarySyncForNpc('Lydia', 800, static function (): void {}, $resolver);

        $this->assertTrue($first['enabled']);
        $this->assertTrue($first['created']);
        $this->assertFalse($second['created']);
        $commands = array_values(array_filter(
            $GLOBALS['db']->inserts,
            static fn(array $insert): bool => $insert['table'] === 'responselog'
        ));
        $this->assertCount(2, $commands);
        $this->assertStringStartsWith('rolecommand|spawnBook@', $commands[0]['row']['action']);
        $this->assertStringStartsWith("rolecommand|spawnBook@Lydia's Diary@0@666766@0@b64:", $commands[1]['row']['action']);
    }

    public function testDisablingProfileStopsUpdatesWithoutRemovingTracking(): void
    {
        $GLOBALS['db']->physicalRows = [['npc_name' => 'Lydia']];
        $rendered = false;
        $result = chimPhysicalDiarySyncForNpc(
            'Lydia',
            900,
            static function () use (&$rendered): void {
                $rendered = true;
            },
            static fn(): array => [
                'refid' => '000A2C8E',
                'profile_metadata' => '{"MATERIALIZE_DIARY_ENABLED":false}',
            ]
        );

        $this->assertTrue($result['ok']);
        $this->assertFalse($result['enabled']);
        $this->assertFalse($rendered);
        $this->assertNotEmpty($GLOBALS['db']->physicalRows);
    }
}
