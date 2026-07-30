<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'rolemaster_helpers.php';

final class SpawnCommandWireTest extends TestCase
{
    public function testSpawnCommandUsesPluginLocalAndSignedRuntimeFormIds(): void
    {
        $db = new class {
            public array $inserted = [];

            public function fetchAll(string $query): array
            {
                return [];
            }

            public function fetchOne(string $query): array
            {
                return [];
            }

            public function escape($value): string
            {
                return str_replace("'", "''", (string)$value);
            }

            public function insert(string $table, array $data): void
            {
                $this->inserted[] = [$table, $data];
            }
        };

        $previousGlobals = [];
        foreach (['db', 'npc_templates', 'npc_own_templates', 'weapons', 'outfit'] as $key) {
            $previousGlobals[$key] = $GLOBALS[$key] ?? null;
        }

        $GLOBALS['db'] = $db;
        $GLOBALS['npc_templates'] = ['female_nord' => [0x0003DE6E]];
        $GLOBALS['npc_own_templates'] = ['female_nord_soldier' => [0xA7025DC1]];
        $GLOBALS['weapons'] = ['soldier' => [0xA7013985], 'default' => [0xA7013989]];
        $GLOBALS['outfit'] = ['soldier' => [0xA70E108F]];

        try {
            npcProfileBase('Wire Test', 'soldier', 'Nord', 'female', 'nearby', '0');
        } finally {
            foreach ($previousGlobals as $key => $value) {
                if ($value === null) {
                    unset($GLOBALS[$key]);
                } else {
                    $GLOBALS[$key] = $value;
                }
            }
        }

        $this->assertCount(1, $db->inserted);
        $this->assertSame('responselog', $db->inserted[0][0]);
        $this->assertSame(
            'rolecommand|spawnCharacter@Wire Test@155073@-1492250481@-1493091963@0@0@253550',
            $db->inserted[0][1]['action']
        );
    }
}
