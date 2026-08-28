<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'game_plugins.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'quest_reference_data.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'npc_master.class.php';

final class FormReferenceSupportTest extends TestCase
{
    public function testNpcReferencesSurviveNormalAndLightLoadOrderChanges(): void
    {
        $old = [
            ['plugin_name' => 'First.esp', 'formid_prefix' => '05'],
            ['plugin_name' => 'Second.esp', 'formid_prefix' => '07'],
            ['plugin_name' => 'Light.esp', 'formid_prefix' => 'FE012', 'is_light' => true],
        ];
        $new = $old;
        $new[0]['formid_prefix'] = '07';
        $new[1]['formid_prefix'] = '05';
        $new[2]['formid_prefix'] = 'FE034';
        $rows = [];
        foreach (['05001234', '07001234', 'FE012ABC', 'FF001234'] as $index => $refid) {
            $rows[] = ['id' => $index + 1, 'npc_name' => 'Guard', 'refid' => $refid, 'metadata' => '{}'];
        }
        $updates = chimPlanNpcReferenceRemap($rows, $old, $new);
        $this->assertSame(['07001234', '05001234', 'FE034ABC'], array_column($updates, 'refid'));
        $this->assertSame(['First.esp|00001234', 'Second.esp|00001234', 'Light.esp|00000ABC'], array_column($updates, 'source'));
        $this->assertSame(md5('Guard [RefID: 07001234]'), $updates[0]['md5']);
        $this->assertSame('First.esp|00001234', chimParseNpcReferenceSource('First.esp/00001234')['stable_key']);
        $this->assertNull(chimParseNpcReferenceSource('../First.esp/1234'));
        $this->assertNull(chimParseNpcReferenceSource('First.esp/FF001234'));
        $this->assertSame('VR.esp|00012ABC', chimConvertRuntimeFormIdToStableReference('FE012ABC',
            chimIndexLoadedGamePluginsByPrefix([['plugin_name' => 'VR.esp', 'formid_prefix' => 'FE']])));
    }

    public function testRemovedPluginProfilesRemainUnavailableUntilTheirPluginReturns(): void
    {
        $row = ['id' => 1, 'npc_name' => 'Guard', 'refid' => '05001234',
            'metadata' => '{"refid_source":"First.esp|00001234","mods":["First.esp"]}'];
        $other = [['plugin_name' => 'Unrelated.esp', 'formid_prefix' => '05']];
        $updates = chimPlanNpcReferenceRemap([$row], [], $other);
        $this->assertNull($updates[0]['refid']);
        $row['refid'] = null;
        $row['md5'] = $updates[0]['md5'];
        $this->assertSame($row['md5'], NpcMaster::identityMd5($row));
        $this->assertSame([], chimPlanNpcReferenceRemap([$row], [], $other));
        $restored = chimPlanNpcReferenceRemap([$row], $other, [['plugin_name' => 'First.esp', 'formid_prefix' => '09']]);
        $this->assertSame('09001234', $restored[0]['refid']);
    }

    public function testNpcReferenceRemapRejectsAmbiguousDestinations(): void
    {
        $rows = [
            ['id' => 1, 'npc_name' => 'Guard', 'refid' => '05001234', 'metadata' => '{}'],
            ['id' => 2, 'npc_name' => 'Guard', 'refid' => '07001234', 'metadata' => '{}'],
        ];
        $this->expectException(RuntimeException::class);
        chimPlanNpcReferenceRemap($rows,
            [['plugin_name' => 'First.esp', 'formid_prefix' => '05']],
            [['plugin_name' => 'First.esp', 'formid_prefix' => '07']]);
    }

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

    public function testSharedCharacterOverlayPreservesPhysicalActorIdentity(): void
    {
        $actor = ['id' => 3, 'npc_name' => 'Astrid', 'refid' => '05012345', 'md5' => 'physical',
            'profile_owner_npc_id' => 2, 'personality' => 'other', 'appearance' => 'burnt',
            'metadata' => '{"refid_source":"Alternate.esp|00012345","health":20}',
            'extended_data' => '{"factions":["physical"],"middle_term_memory":{"1":"other"},"custom":true}'];
        $GLOBALS['db'] = new class {
            public function fetchOne($query) {
                return ['id' => 2, 'npc_name' => 'Astrid', 'refid' => '00013475', 'personality' => 'kept',
                    'extended_data' => '{"middle_term_memory":{"2":"shared"},"factions":["owner"]}'];
            }
        };
        $effective = chimNpcEffectiveProfile($actor);
        $this->assertSame('kept', $effective['personality']);
        foreach (['id', 'refid', 'md5', 'appearance', 'metadata'] as $field) {
            $this->assertSame($actor[$field], $effective[$field]);
        }
        $this->assertSame(['physical'], chimNpcProfileJson($effective['extended_data'])['factions']);
        $this->assertSame([2 => 'shared'], chimNpcProfileJson($effective['extended_data'])['middle_term_memory']);
        $this->assertTrue(chimNpcProfileJson($effective['extended_data'])['custom']);
        $this->assertSame([], chimNpcEffectiveProfile([]));
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
