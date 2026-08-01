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
                if (
                    stripos($query, "where lower(plugin_name) = lower('mymod.esp')") !== false
                    || stripos($query, "where formid_prefix = '02'") !== false
                ) {
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

                if (
                    stripos($query, "where lower(plugin_name) = lower('somelight.esl')") !== false
                    || stripos($query, "where formid_prefix = 'FE123'") !== false
                ) {
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

                if (
                    stripos($query, "where lower(plugin_name) = lower('skyrim.esm')") !== false
                    || stripos($query, "where formid_prefix = '00'") !== false
                ) {
                    return [
                        'plugin_name' => 'Skyrim.esm',
                        'is_light' => false,
                        'compile_index' => 0,
                        'small_file_compile_index' => 0,
                        'partial_index' => 0,
                        'formid_prefix' => '00',
                        'updated_at' => '2026-04-27 00:00:00',
                    ];
                }

                if (
                    stripos($query, "where lower(plugin_name) = lower('aiagent.esp')") !== false
                    || stripos($query, "where formid_prefix = '05'") !== false
                ) {
                    return [
                        'plugin_name' => 'AIAgent.esp',
                        'is_light' => false,
                        'compile_index' => 5,
                        'small_file_compile_index' => 0,
                        'partial_index' => 0,
                        'formid_prefix' => '05',
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

    public function testRuntimeFormIdsAreCanonicalizedToStableStorageReferences(): void
    {
        $this->assertSame(
            'MyMod.esp|000086EE',
            quest_reference_canonicalize_formid_for_text_storage('0x020086ee')
        );
        $this->assertSame(
            'SomeLight.esl|00000822',
            quest_reference_canonicalize_formid_for_text_storage('FE123822')
        );
        $this->assertSame(
            'Skyrim.esm|0001397E',
            quest_reference_canonicalize_formid_for_text_storage('0x0001397e')
        );
    }

    public function testUnresolvedRuntimeIdsArePreservedAndDynamicIdsAreRejected(): void
    {
        $this->assertSame(
            '0x03001234',
            quest_reference_canonicalize_formid_for_text_storage('0x03001234')
        );
        $this->assertNull(
            quest_reference_canonicalize_formid_for_text_storage('0xFF001234')
        );

        $unresolved = quest_reference_classify_formid_for_text_storage('0x03001234');
        $this->assertSame('unresolved', $unresolved['status']);

        $dynamic = quest_reference_classify_formid_for_text_storage('0xFF001234');
        $this->assertSame('dynamic', $dynamic['status']);
    }

    public function testLegacyQuestReferenceRepairUsesManifestPrefixesWithoutDroppingUnknownValues(): void
    {
        $plugins = [
            [
                'plugin_name' => 'MyMod.esp',
                'is_light' => false,
                'compile_index' => 2,
                'formid_prefix' => '02',
            ],
            [
                'plugin_name' => 'SomeLight.esl',
                'is_light' => true,
                'compile_index' => 254,
                'small_file_compile_index' => 0x123,
                'formid_prefix' => 'FE123',
            ],
        ];
        $pluginsByPrefix = chimIndexLoadedGamePluginsByPrefix($plugins);
        $pluginsByName = chimIndexLoadedGamePluginsByName($plugins);

        $repair = quest_reference_repair_formid_values(
            'item_types',
            'weapon',
            [
                '0x020086ee',
                'MyMod.esp|86EE',
                '0xFE123822',
                '0x03001234',
                '0xFF001234',
                'not-a-formid',
            ],
            $pluginsByPrefix,
            $pluginsByName
        );

        $this->assertSame([
            'MyMod.esp|000086EE',
            'SomeLight.esl|00000822',
            '0x03001234',
            '0xFF001234',
            'not-a-formid',
        ], $repair['values']);
        $this->assertTrue($repair['changed']);
        $this->assertSame(2, $repair['converted']);
        $this->assertSame(1, $repair['unresolved']);
        $this->assertSame(1, $repair['dynamic']);
        $this->assertSame(1, $repair['invalid']);
    }

    public function testLegacyAIAgentLocalIdsDoNotGetMisidentifiedAsSkyrimForms(): void
    {
        $plugins = [
            [
                'plugin_name' => 'Skyrim.esm',
                'is_light' => false,
                'compile_index' => 0,
                'formid_prefix' => '00',
            ],
            [
                'plugin_name' => 'AIAgent.esp',
                'is_light' => false,
                'compile_index' => 5,
                'formid_prefix' => '05',
            ],
        ];
        $pluginsByPrefix = chimIndexLoadedGamePluginsByPrefix($plugins);
        $pluginsByName = chimIndexLoadedGamePluginsByName($plugins);

        $npcTemplate = quest_reference_classify_dataset_formid_for_text_storage(
            'npc_own_templates',
            'female_breton_noble',
            '0x00025844',
            $pluginsByPrefix,
            $pluginsByName
        );
        $this->assertSame('AIAgent.esp|00025844', $npcTemplate['value']);
        $this->assertSame(
            hexdec('05025844'),
            quest_reference_normalize_formid($npcTemplate['value'])
        );

        $questItem = quest_reference_classify_dataset_formid_for_text_storage(
            'item_types',
            'potion',
            '0x0002481F',
            $pluginsByPrefix,
            $pluginsByName
        );
        $this->assertSame('AIAgent.esp|0002481F', $questItem['value']);

        $legacyBook = quest_reference_classify_dataset_formid_for_text_storage(
            'item_types',
            'book',
            '0x000CE70B',
            $pluginsByPrefix,
            $pluginsByName
        );
        $this->assertSame('Skyrim.esm|000CE70B', $legacyBook['value']);

        $customVanillaTemplate = quest_reference_classify_dataset_formid_for_text_storage(
            'npc_own_templates',
            'custom_template',
            '0x00025844',
            $pluginsByPrefix,
            $pluginsByName
        );
        $this->assertSame('Skyrim.esm|00025844', $customVanillaTemplate['value']);

        $vanillaTemplate = quest_reference_classify_dataset_formid_for_text_storage(
            'npc_templates',
            'male_redguard',
            '0x00013BAA',
            $pluginsByPrefix,
            $pluginsByName
        );
        $this->assertSame('Skyrim.esm|00013BAA', $vanillaTemplate['value']);
    }

    public function testPluginManifestRepairUpdatesLegacyQuestReferenceRows(): void
    {
        $db = new class {
            public array $queries = [];

            public function escape($value): string
            {
                return str_replace("'", "''", (string) $value);
            }

            public function fetchOne(string $query): ?array
            {
                if (stripos($query, 'information_schema.tables') !== false) {
                    return ['n' => 1];
                }
                if (stripos($query, 'information_schema.columns') !== false) {
                    return ['n' => 1];
                }

                return null;
            }

            public function fetchAll(string $query): array
            {
                if (stripos($query, 'from public.quest_item_types') !== false) {
                    return [[
                        'key_name' => 'weapon',
                        'formids_json' => '["0x020086ee","0x03001234"]',
                    ]];
                }

                return [];
            }

            public function execQuery(string $query): bool
            {
                $this->queries[] = $query;
                return true;
            }
        };
        $GLOBALS['db'] = $db;

        $repair = quest_reference_repair_runtime_formids_to_stable([
            [
                'plugin_name' => 'MyMod.esp',
                'is_light' => false,
                'compile_index' => 2,
                'formid_prefix' => '02',
            ],
        ]);

        $this->assertNull($repair['error']);
        $this->assertSame(1, $repair['rows_scanned']);
        $this->assertSame(1, $repair['rows_updated']);
        $this->assertSame(1, $repair['converted']);
        $this->assertSame(1, $repair['unresolved']);

        $updateQueries = array_values(array_filter(
            $db->queries,
            static fn (string $query): bool => stripos($query, 'UPDATE public.quest_item_types') !== false
        ));
        $this->assertCount(1, $updateQueries);
        $this->assertStringContainsString('MyMod.esp|000086EE', $updateQueries[0]);
        $this->assertStringContainsString('0x03001234', $updateQueries[0]);
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
