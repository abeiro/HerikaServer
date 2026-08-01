<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'quest_asset_library.php';
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'quest_reference_data.php';

final class QuestAssetLibraryTest extends TestCase
{
    private function manifest(string $filename): array
    {
        $path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR
            . 'quest_assets' . DIRECTORY_SEPARATOR . $filename;
        $manifest = json_decode((string) file_get_contents($path), true);
        $this->assertIsArray($manifest, $filename . ' must contain valid JSON.');
        return $manifest;
    }

    private function minimalManifest(): array
    {
        return [
            'schema' => 'chim.quest-assets.v1',
            'pack' => [
                'key' => 'test_pack',
                'label' => 'Test Pack',
                'game' => 'SkyrimSE',
                'version' => '1',
                'required_plugins' => ['Skyrim.esm'],
                'active' => false,
            ],
            'assets' => [[
                'stable_ref' => 'Skyrim.esm|13989',
                'signature' => 'WEAP',
                'editor_id' => 'IronSword',
                'display_name' => 'Iron Sword',
                'source_plugin' => 'Skyrim.esm',
                'winning_plugin' => 'Skyrim.esm',
                'metadata' => ['source' => 'test'],
                'safety_status' => 'review',
                'active' => true,
            ]],
            'groups' => [[
                'dataset' => 'item_types',
                'key' => 'sword',
                'selection_policy' => [],
                'active' => false,
                'members' => [[
                    'stable_ref' => 'Skyrim.esm|13989',
                    'weight' => 2,
                    'constraints' => [],
                    'active' => true,
                ]],
            ]],
        ];
    }

    public function testBundledCuratedManifestsValidate(): void
    {
        $expected = [
            'skyrim_official.json' => [178, 93],
            'chim_spawn_templates.json' => [79, 442],
        ];

        foreach ($expected as $filename => [$assetCount, $groupCount]) {
            $validation = quest_asset_manifest_validate($this->manifest($filename));
            $this->assertTrue($validation['valid'], implode('; ', $validation['errors']));
            $this->assertCount($assetCount, $validation['manifest']['assets']);
            $this->assertCount($groupCount, $validation['manifest']['groups']);
        }
    }

    public function testSpawnTemplatePackContainsOnlyApprovedChimOwnedNpcBases(): void
    {
        $manifest = quest_asset_manifest_validate($this->manifest('chim_spawn_templates.json'))['manifest'];
        $this->assertTrue($manifest['pack']['active']);
        $this->assertSame(['AIAgent.esp'], $manifest['pack']['required_plugins']);

        foreach ($manifest['assets'] as $asset) {
            $this->assertSame('NPC_', $asset['signature']);
            $this->assertSame('AIAgent.esp', $asset['source_plugin']);
            $this->assertStringStartsWith('AIAgent.esp|', $asset['stable_ref']);
            $this->assertSame('approved', $asset['safety_status']);

            $localFormId = hexdec(substr($asset['stable_ref'], strrpos($asset['stable_ref'], '|') + 1));
            $this->assertTrue(
                ($localFormId >= 0x00025844 && $localFormId <= 0x0002584D)
                    || ($localFormId >= 0x00025DAF && $localFormId <= 0x00025DED)
                    || ($localFormId >= 0x00045CE7 && $localFormId <= 0x00045CEE),
                $asset['stable_ref'] . ' is not a shipped AIAgent.esp NPC template.'
            );
        }
        foreach ($manifest['groups'] as $group) {
            $this->assertSame('npc_own_templates', $group['dataset']);
        }
    }

    public function testEveryCuratedHumanoidOutfitHasSafeSpawnTemplateAliases(): void
    {
        $official = quest_asset_manifest_validate($this->manifest('skyrim_official.json'))['manifest'];
        $spawnTemplates = quest_asset_manifest_validate($this->manifest('chim_spawn_templates.json'))['manifest'];
        $classes = [];
        foreach ($official['groups'] as $group) {
            if ($group['dataset'] === 'outfit') {
                $classes[] = $group['key'];
            }
        }
        $ownGroups = [];
        foreach ($spawnTemplates['groups'] as $group) {
            $ownGroups[$group['key']] = true;
        }

        foreach (['male', 'female'] as $gender) {
            foreach (quest_reference_playable_races() as $race) {
                foreach ($classes as $class) {
                    $this->assertArrayHasKey("{$gender}_{$race}_{$class}", $ownGroups);
                }
            }
        }
    }

    public function testOfficialNpcRecordsAreAppearanceDonorsNotOwnTemplates(): void
    {
        $manifest = quest_asset_manifest_validate($this->manifest('skyrim_official.json'))['manifest'];
        $datasets = array_unique(array_column($manifest['groups'], 'dataset'));
        $this->assertContains('npc_templates', $datasets);
        $this->assertNotContains('npc_own_templates', $datasets);
    }

    public function testWeaponsUseGenericAssetGroupsWithoutLegacyStorage(): void
    {
        $this->assertArrayNotHasKey('weapons', quest_reference_dataset_config());
        $this->assertSame(['WEAP'], quest_asset_dataset_signatures()['weapons']);

        $manifest = quest_asset_manifest_validate($this->manifest('skyrim_official.json'))['manifest'];
        $weaponGroups = array_values(array_filter(
            $manifest['groups'],
            static fn(array $group): bool => $group['dataset'] === 'weapons'
        ));
        $this->assertCount(23, $weaponGroups);

        $schema = (string) file_get_contents(
            dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'quest_asset_library.sql'
        );
        $this->assertStringNotContainsString('quest_weapons', $schema);
    }

    public function testLibraryOnlyWeaponDatasetLoadsForRuntime(): void
    {
        $db = new class {
            public function escape($value): string
            {
                return str_replace("'", "''", (string) $value);
            }

            public function fetchOne(string $query): array
            {
                if (str_contains($query, 'FROM public.game_plugins')) {
                    return [
                        'plugin_name' => 'QuestAssetTestWeapons.esm',
                        'formid_prefix' => '01',
                    ];
                }
                return ['table_name' => 'present'];
            }

            public function fetchAll(string $query): array
            {
                if (str_contains($query, 'm.stable_ref')) {
                    return [[
                        'group_key' => 'guard',
                        'stable_ref' => 'QuestAssetTestWeapons.esm|00001234',
                        'weight' => 1,
                        'required_plugins_json' => '["QuestAssetTestWeapons.esm"]',
                    ]];
                }
                return [];
            }
        };
        $previousDb = $GLOBALS['db'] ?? null;
        $GLOBALS['db'] = $db;
        try {
            $weapons = quest_reference_load_dataset('weapons');
        } finally {
            if ($previousDb === null) {
                unset($GLOBALS['db']);
            } else {
                $GLOBALS['db'] = $previousDb;
            }
        }

        $this->assertSame([hexdec('01001234')], $weapons['guard']);
    }

    public function testRuntimeFormIdsUseSignedPapyrusWireValues(): void
    {
        $this->assertSame(0, quest_reference_formid_for_papyrus(0));
        $this->assertSame(2147483647, quest_reference_formid_for_papyrus('0x7FFFFFFF'));
        $this->assertSame(-2147483648, quest_reference_formid_for_papyrus('0x80000000'));
        $this->assertSame(-1493017151, quest_reference_formid_for_papyrus('0xA7025DC1'));
        $this->assertSame(-1, quest_reference_formid_for_papyrus('0xFFFFFFFF'));
    }

    public function testFullPluginRuntimeFormIdsBecomePluginLocalValues(): void
    {
        $this->assertSame(0x0025DC1, quest_reference_formid_for_full_plugin_file('0xA7025DC1'));
        $this->assertSame(0x00013989, quest_reference_formid_for_full_plugin_file('0x00013989'));
    }

    public function testOfficialPackContainsOnlyApprovedOfficialRecords(): void
    {
        $manifest = quest_asset_manifest_validate($this->manifest('skyrim_official.json'))['manifest'];
        $this->assertTrue($manifest['pack']['active']);
        $this->assertSame(['Skyrim.esm'], $manifest['pack']['required_plugins']);
        $officialPlugins = [
            'Skyrim.esm',
            'Update.esm',
            'Dawnguard.esm',
            'HearthFires.esm',
            'Dragonborn.esm',
        ];

        foreach ($manifest['assets'] as $asset) {
            $this->assertContains($asset['source_plugin'], $officialPlugins);
            $this->assertContains($asset['winning_plugin'], $officialPlugins);
            $this->assertStringStartsWith($asset['source_plugin'] . '|', $asset['stable_ref']);
            $this->assertSame('approved', $asset['safety_status']);
        }
    }

    public function testManifestNormalizesPluginStableReferences(): void
    {
        $validation = quest_asset_manifest_validate($this->minimalManifest());
        $this->assertTrue($validation['valid'], implode('; ', $validation['errors']));
        $this->assertSame('Skyrim.esm|00013989', $validation['manifest']['assets'][0]['stable_ref']);
        $this->assertSame('Skyrim.esm|00013989', $validation['manifest']['groups'][0]['members'][0]['stable_ref']);
    }

    public function testManifestRejectsMalformedShapesAndDuplicateIdentities(): void
    {
        $manifest = $this->minimalManifest();
        $manifest['pack']['required_plugins'] = 'Skyrim.esm';
        $manifest['assets'][0]['metadata'] = ['not', 'an', 'object'];
        $manifest['groups'][] = $manifest['groups'][0];
        $manifest['groups'][0]['members'][] = $manifest['groups'][0]['members'][0];

        $validation = quest_asset_manifest_validate($manifest);
        $errors = implode('\n', $validation['errors']);
        $this->assertFalse($validation['valid']);
        $this->assertStringContainsString('pack.required_plugins must be a JSON array', $errors);
        $this->assertStringContainsString('metadata must be a JSON object', $errors);
        $this->assertStringContainsString('Duplicate group', $errors);
        $this->assertStringContainsString('Duplicate member', $errors);
    }

    public function testManifestRejectsWrongDatasetSignatureAndSelfFallback(): void
    {
        $manifest = $this->minimalManifest();
        $manifest['groups'][0]['dataset'] = 'npc_templates';
        $manifest['groups'][0]['selection_policy'] = ['fallback_group' => 'sword'];

        $validation = quest_asset_manifest_validate($manifest);
        $errors = implode('\n', $validation['errors']);
        $this->assertFalse($validation['valid']);
        $this->assertStringContainsString("cannot be used in dataset 'npc_templates'", $errors);
        $this->assertStringContainsString('fallback_group must be a different valid group key', $errors);
    }

    public function testFallbackChainsResolveWithoutFollowingCycles(): void
    {
        $resolved = quest_asset_apply_fallback_map(
            ['base' => [42]],
            ['target' => 'middle', 'middle' => 'base']
        );
        $this->assertSame([42], $resolved['middle']);
        $this->assertSame([42], $resolved['target']);

        $cyclic = quest_asset_apply_fallback_map([], ['first' => 'second', 'second' => 'first']);
        $this->assertArrayNotHasKey('first', $cyclic);
        $this->assertArrayNotHasKey('second', $cyclic);
    }

    public function testSafeSpawnBaseNeverCrossesRaceOrGender(): void
    {
        $dataset = [
            'male_nord_warrior' => [101],
            'female_nord_warrior' => [202],
            'male_orc_warrior' => [303],
        ];
        $this->assertSame(101, quest_reference_pick_safe_spawn_base($dataset, 'male', 'nord', 'guard'));
        $this->assertSame(202, quest_reference_pick_safe_spawn_base($dataset, 'female', 'nord', 'guard'));
        $this->assertSame(0, quest_reference_pick_safe_spawn_base($dataset, 'female', 'orc', 'guard'));
    }

    public function testEveryShippedSpawnRaceHasDonorsAndSafeSpawnBases(): void
    {
        $official = quest_asset_manifest_validate($this->manifest('skyrim_official.json'))['manifest'];
        $spawnTemplates = quest_asset_manifest_validate($this->manifest('chim_spawn_templates.json'))['manifest'];
        $donorKeys = [];
        $spawnKeys = [];
        $classes = [];

        foreach ($official['groups'] as $group) {
            if ($group['dataset'] === 'npc_templates') {
                $donorKeys[] = $group['key'];
            } elseif ($group['dataset'] === 'outfit') {
                $classes[] = $group['key'];
            }
        }
        foreach ($spawnTemplates['groups'] as $group) {
            if ($group['dataset'] === 'npc_own_templates') {
                $spawnKeys[] = $group['key'];
            }
        }

        $this->assertSame(
            quest_reference_playable_races(),
            quest_reference_spawnable_playable_races($donorKeys, $spawnKeys, $classes)
        );
    }

    public function testIncompletePlayableRaceIsNotAdvertisedAsSpawnable(): void
    {
        $this->assertSame(['nord'], quest_reference_spawnable_playable_races(
            ['male_nord', 'female_nord', 'male_altmer'],
            ['male_nord_warrior', 'female_nord_warrior', 'male_altmer_warrior'],
            ['warrior']
        ));
    }

    public function testAdditionalOutfitClassesDoNotRemoveSpawnablePlayableRaces(): void
    {
        $this->assertSame(['nord'], quest_reference_spawnable_playable_races(
            ['male_nord', 'female_nord'],
            ['male_nord_warrior', 'female_nord_farmer'],
            ['warrior', 'farmer', 'unsupported_legacy_class']
        ));
    }

    public function testPlayableRaceWithoutBothGenderSpawnBasesIsNotAdvertised(): void
    {
        $this->assertSame([], quest_reference_spawnable_playable_races(
            ['male_nord', 'female_nord'],
            ['male_nord_warrior'],
            ['warrior']
        ));
    }

    public function testMissingDatasetDefaultsPreserveExistingKeys(): void
    {
        $this->assertSame(
            [
                'female_altmer' => ['Skyrim.esm|00013269'],
                'male_altmer' => ['Skyrim.esm|000233D2'],
            ],
            quest_reference_missing_dataset_values(
                [
                    'female_nord' => ['Skyrim.esm|000955B6'],
                    'female_altmer' => ['Skyrim.esm|00013269'],
                    'male_altmer' => ['Skyrim.esm|000233D2'],
                ],
                ['female_nord']
            )
        );
    }

    public function testPromptConstraintsReplaceEveryAssetPlaceholder(): void
    {
        $constraints = [
            'races' => quest_reference_normalize_allowed_values(['Nord', 'nord', 'Breton']),
            'classes' => quest_reference_normalize_allowed_values(['Warrior', 'Mage']),
            'item_types' => quest_reference_normalize_allowed_values(['Book', 'Sword']),
        ];
        $prompt = quest_reference_apply_prompt_constraints(
            '{{ALLOWED_RACES}}|{{ALLOWED_CLASSES}}|{{ALLOWED_ITEM_TYPES}}',
            $constraints
        );
        $this->assertSame('breton, nord|mage, warrior|book, sword', $prompt);
        $this->assertStringNotContainsString('{{ALLOWED_', $prompt);
    }

    public function testEmptyManifestObjectsEncodeAsJsonObjects(): void
    {
        $this->assertSame('{}', quest_asset_encode_json_object([]));
        $this->assertSame('{"fallback_group":"warrior"}', quest_asset_encode_json_object([
            'fallback_group' => 'warrior',
        ]));
    }

    public function testImportPrunesRemovedRowsAndPreservesRetainedReviewChoices(): void
    {
        $db = new class {
            public array $queries = [];

            public function escape($value): string
            {
                return str_replace("'", "''", (string) $value);
            }

            public function fetchOne(string $query): array
            {
                return ['table_name' => 'present'];
            }

            public function execQuery(string $query): bool
            {
                $this->queries[] = $query;
                return true;
            }
        };
        $previousDb = $GLOBALS['db'] ?? null;
        $GLOBALS['db'] = $db;
        try {
            $result = quest_asset_import_manifest($this->minimalManifest(), 'test.json');
        } finally {
            if ($previousDb === null) {
                unset($GLOBALS['db']);
            } else {
                $GLOBALS['db'] = $previousDb;
            }
        }

        $this->assertTrue($result['success'], implode('; ', $result['errors']));
        $sql = implode("\n", $db->queries);
        $this->assertStringContainsString('DELETE FROM public.quest_asset_groups', $sql);
        $this->assertStringContainsString('DELETE FROM public.quest_assets', $sql);
        $this->assertStringContainsString('DELETE FROM public.quest_asset_group_members', $sql);
        $this->assertStringNotContainsString('safety_status = EXCLUDED.safety_status', $sql);
        $this->assertStringNotContainsString('active = EXCLUDED.active', $sql);
    }
}
