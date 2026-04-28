<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'game_plugins.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'quest_reference_data.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'npc_master.class.php';

final class FormReferenceSupportTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['db'] = new class {
            public function escape($value): string
            {
                return str_replace("'", "''", (string) $value);
            }

            public function fetchOne(string $query): ?array
            {
                if (stripos($query, "where lower(plugin_name) = lower('mymod.esp')") !== false) {
                    return [
                        'plugin_name' => 'MyMod.esp',
                        'is_light' => false,
                        'compile_index' => 2,
                        'small_file_compile_index' => 0,
                        'partial_index' => 0,
                        'formid_prefix' => '02',
                        'updated_at' => '2026-04-27 00:00:00',
                    ];
                }

                if (stripos($query, "where lower(plugin_name) = lower('somelight.esl')") !== false) {
                    return [
                        'plugin_name' => 'SomeLight.esl',
                        'is_light' => true,
                        'compile_index' => 254,
                        'small_file_compile_index' => 0x123,
                        'partial_index' => 0x123,
                        'formid_prefix' => 'FE123',
                        'updated_at' => '2026-04-27 00:00:00',
                    ];
                }

                return null;
            }
        };
    }

    public function testQuestReferenceHelpersSupportStableReferences(): void
    {
        $this->assertSame(
            'MyMod.esp|000086EE',
            quest_reference_canonicalize_formid_for_text_storage('MyMod.esp|86ee')
        );
        $this->assertSame(
            hexdec('020086EE'),
            quest_reference_normalize_formid('MyMod.esp|000086EE')
        );

        $this->assertSame(
            'SomeLight.esl|00000822',
            quest_reference_canonicalize_formid_for_text_storage('SomeLight.esl|822')
        );
        $this->assertSame(
            hexdec('FE123822'),
            quest_reference_normalize_formid('SomeLight.esl|00000822')
        );
    }

    public function testNpcMasterSupportsStableFactionDetection(): void
    {
        $npcData = [
            'extended_data' => json_encode([
                'factions' => [
                    [
                        'formid' => '020086EE',
                        'rank' => 0,
                        'plugin' => 'MyMod.esp',
                        'local_formid' => '000086EE',
                        'stable_key' => 'MyMod.esp|000086EE',
                    ],
                ],
            ]),
        ];

        $npcMaster = new NpcMaster();

        $this->assertTrue($npcMaster->isNpcInFaction($npcData, 'MyMod.esp|000086EE'));
        $this->assertTrue($npcMaster->isNpcInFaction($npcData, '020086EE'));
        $this->assertFalse($npcMaster->isNpcInFaction($npcData, 'MyMod.esp|00001234'));
    }
}
