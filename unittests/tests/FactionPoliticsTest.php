<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../lib/core/faction_politics.php';

final class FactionPoliticsTest extends TestCase
{
    public function testFactionKeyPrefersStablePluginReference(): void
    {
        $this->assertSame(
            'MYSOCIETY.ESP|00001234',
            chimFactionPoliticsKey([
                'stable_key' => 'MySociety.esp|001234',
                'formid' => 'FE012345',
                'name' => 'My Society',
            ])
        );
        $this->assertSame('FORMID|0005C84D', chimFactionPoliticsKey(['formid' => '5c84d']));
        $this->assertSame('NAME|unnamed-circle', chimFactionPoliticsKey(['name' => 'Unnamed Circle']));
    }

    public function testCanonicalPairIsIndependentOfInputOrder(): void
    {
        $this->assertSame(
            ['A', 'Faction A', 'B', 'Faction B'],
            chimFactionPoliticsCanonicalPair('B', 'Faction B', 'A', 'Faction A')
        );
    }

    public function testMembershipsIgnoreInactiveFactionRanks(): void
    {
        $memberships = chimFactionPoliticsMembershipsFromNpcRows([[
            'extended_data' => json_encode(['factions' => [
                ['stable_key' => 'Guards.esp|00000001', 'name' => 'City Guard', 'rank' => 0],
                ['stable_key' => 'Guild.esp|00000002', 'name' => 'Former Guild', 'rank' => -1],
            ]]),
        ]]);

        $this->assertSame(['GUARDS.ESP|00000001' => 'City Guard'], $memberships);
    }

    public function testContextIncludesOnlyPoliticsConnectedToSceneFactions(): void
    {
        $context = chimFactionPoliticsBuildContext(
            ['GUARDS' => 'City Guard'],
            [
                ['faction_key' => 'GUARDS', 'faction_name' => 'City Guard', 'status' => 'rising', 'influence' => 25, 'agenda' => 'Secure the roads', 'summary' => 'Recruiting patrols'],
                ['faction_key' => 'MAGES', 'faction_name' => 'Mage Circle', 'status' => 'stable', 'influence' => 10],
            ],
            [
                ['faction_a_key' => 'GUARDS', 'faction_a_name' => 'City Guard', 'faction_b_key' => 'MERCHANTS', 'faction_b_name' => 'Merchants', 'stance' => 'friendly', 'score' => 30, 'summary' => 'Shared road patrols'],
                ['faction_a_key' => 'MAGES', 'faction_a_name' => 'Mage Circle', 'faction_b_key' => 'THIEVES', 'faction_b_name' => 'Thieves', 'stance' => 'hostile', 'score' => -80],
            ],
            [
                ['title' => 'Caravan protected', 'summary' => 'A caravan arrived safely.', 'faction_keys' => json_encode(['GUARDS'])],
                ['title' => 'Secret duel', 'summary' => 'Unrelated.', 'faction_keys' => json_encode(['MAGES'])],
            ]
        );

        $this->assertStringContainsString('City Guard: rising, influence 25', $context);
        $this->assertStringContainsString('City Guard and Merchants: friendly (30)', $context);
        $this->assertStringContainsString('Caravan protected', $context);
        $this->assertStringNotContainsString('Mage Circle', $context);
        $this->assertStringNotContainsString('Secret duel', $context);
    }

    public function testContextEscapesMarkupAndHonorsDevelopmentLimit(): void
    {
        $context = chimFactionPoliticsBuildContext(
            ['A' => 'A'],
            [],
            [],
            [
                ['title' => '<first>', 'summary' => 'One & two', 'faction_keys' => ['A']],
                ['title' => 'second', 'summary' => 'Hidden', 'faction_keys' => ['A']],
            ],
            1
        );

        $this->assertStringContainsString('&lt;first&gt;', $context);
        $this->assertStringContainsString('One &amp; two', $context);
        $this->assertStringNotContainsString('second', $context);
    }

    public function testNoMembershipOrNoRelevantRecordsAddsNoPromptText(): void
    {
        $this->assertSame('', chimFactionPoliticsBuildContext([], [], [], []));
        $this->assertSame('', chimFactionPoliticsBuildContext(['A' => 'A'], [], [], []));
    }

    public function testSceneLoaderUsesCurrentAndNearbyNpcMemberships(): void
    {
        $db = new class {
            public array $queries = [];

            public function escape(string $value): string
            {
                return str_replace("'", "''", $value);
            }

            public function fetchOne(string $query): array
            {
                return ['state_table' => 'core_faction_politics_state'];
            }

            public function fetchAll(string $query): array
            {
                $this->queries[] = $query;
                if (str_contains($query, 'FROM core_npc_master')) {
                    return [[
                        'npc_name' => 'Nearby NPC',
                        'extended_data' => json_encode(['factions' => [[
                            'stable_key' => 'Nearby.esp|00000010',
                            'name' => 'Nearby Faction',
                            'rank' => 0,
                        ]]]),
                    ]];
                }
                if (str_contains($query, 'FROM core_faction_politics_state')) {
                    return [[
                        'faction_key' => 'CURRENT.ESP|00000001',
                        'faction_name' => 'Current Faction',
                        'status' => 'dominant',
                        'influence' => 80,
                    ]];
                }
                return [];
            }
        };

        $context = chimFactionPoliticsBuildSceneContext($db, [
            'npc_name' => 'Current NPC',
            'extended_data' => json_encode(['factions' => [[
                'stable_key' => 'Current.esp|00000001',
                'name' => 'Current Faction',
                'rank' => 0,
            ]]]),
        ], '|Nearby NPC|');

        $this->assertStringContainsString('Current Faction: dominant', $context);
        $this->assertStringContainsString("lower('Nearby NPC')", $db->queries[0]);
        $this->assertStringContainsString("lower('Current NPC')", $db->queries[0]);
    }
}
