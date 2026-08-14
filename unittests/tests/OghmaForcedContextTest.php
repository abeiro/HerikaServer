<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../lib/oghma_forced_context.php';

final class OghmaForcedContextTest extends TestCase
{
    public function testRaceAliasesResolveToCanonicalLoreTopics(): void
    {
        $this->assertSame(['high elf', 'altmer'], chimOghmaRaceSignals('HighElfRace'));
        $this->assertSame(['dark elf', 'dunmer'], chimOghmaRaceSignals('Dark Elf'));
        $this->assertSame([], chimOghmaRaceSignals('WolfRace'));
    }

    public function testVanillaRaceFormIdsResolveThroughStablePluginKeys(): void
    {
        $this->assertSame('skyrim.esm|00013746', chimOghmaStableFormKey('0x00013746'));
        $this->assertSame(['nord'], chimOghmaRaceIdentitySignals([
            'race' => 'UnknownRace',
            'race_formid' => '00013746',
            'race_plugin' => 'Skyrim.esm',
        ]));
        $this->assertSame(['high elf', 'altmer'], chimOghmaRaceIdentitySignals([
            'race_stable_key' => 'Skyrim.esm|00013743',
        ]));
    }

    public function testAdvancedAndBasicKnowledgePermissionsArePreserved(): void
    {
        $row = [
            'topic_desc' => 'Restricted advanced lore.',
            'knowledge_class' => 'scholar,!bandit',
            'topic_desc_basic' => 'Common basic lore.',
            'knowledge_class_basic' => '',
        ];

        $this->assertSame(
            ['level' => 'advanced', 'description' => 'Restricted advanced lore.'],
            chimOghmaResolveKnowledgePayload($row, ['scholar'])
        );
        $this->assertSame(
            ['level' => 'basic', 'description' => 'Common basic lore.'],
            chimOghmaResolveKnowledgePayload($row, ['scholar', 'bandit'])
        );
    }

    public function testInjectedTopicAliasesDeduplicateLaterSearchResults(): void
    {
        $GLOBALS['OGHMA_INJECTED_TOPICS'] = [];
        chimOghmaMarkTopicInjected('high_elf, altmer');

        $this->assertTrue(chimOghmaTopicWasInjected('High Elf'));
        $this->assertTrue(chimOghmaTopicWasInjected('altmer'));
        $this->assertFalse(chimOghmaTopicWasInjected('bosmer'));
    }

    public function testDifferentTopicsWithIdenticalLoreAreInjectedOnlyOnce(): void
    {
        $GLOBALS['OGHMA_HINT'] = '';
        $GLOBALS['OGHMA_INJECTED_TOPICS'] = [];
        $GLOBALS['OGHMA_INJECTED_PAYLOADS'] = [];
        $rows = [
            [
                'topic' => 'bosmer',
                'topic_desc' => 'Wood Elves are native to Valenwood.',
                'knowledge_class' => '',
            ],
            [
                'topic' => 'wood_elf',
                'topic_desc' => "  Wood Elves are native\n to Valenwood.  ",
                'knowledge_class' => '',
            ],
        ];

        $this->assertSame(1, chimOghmaAppendForcedRows($rows, [], 'racial', 4));
        $this->assertSame(1, substr_count($GLOBALS['OGHMA_HINT'], 'Wood Elves are native'));
        $this->assertTrue(chimOghmaTopicWasInjected('bosmer'));
        $this->assertTrue(chimOghmaTopicWasInjected('wood elf'));
    }

    public function testCanonicalHoldSignalsIncludeTheLoreTopicAlias(): void
    {
        $this->assertSame(
            ['whiterun hold', 'whiterun'],
            chimOghmaHoldSignals('Whiterun Hold')
        );
    }

    public function testInteriorLocationAndHoldProduceSeparateSignals(): void
    {
        $this->assertSame(
            [
                'location' => ['riverwood trader', 'riverwood'],
                'hold' => ['whiterun hold', 'whiterun'],
            ],
            chimOghmaBuildLocationSignalGroups(
                'Riverwood Trader',
                [],
                'Whiterun Hold',
                'Whiterun'
            )
        );
    }

    public function testLocationResolverPrefersNormalizedFormIdBeforeTextFallback(): void
    {
        $db = new class {
            public array $queries = [];
            public function fetchAll(string $query): array
            {
                $this->queries[] = $query;
                return str_contains($query, '119247')
                    ? [['formid' => '119247', 'name' => 'Sleeping Giant Inn', 'region' => 'Riverwood', 'hold' => 'Whiterun']]
                    : [];
            }
            public function escape(string $value): string { return addslashes($value); }
        };
        $rows = chimOghmaResolveLocationRows($db, ['location_formid' => '119247'], 'Wrong Transcription');
        $this->assertSame('Sleeping Giant Inn', $rows[0]['name']);
        $this->assertCount(1, $db->queries);
    }

    public function testKnownLocationRowsKeepRegionSeparateFromHold(): void
    {
        $this->assertSame(
            [
                'location' => ['sleeping giant inn', 'sleeping giant', 'riverwood'],
                'hold' => ['whiterun', 'whiterun hold'],
            ],
            chimOghmaBuildLocationSignalGroups(
                'Sleeping Giant Inn',
                [['name' => 'Sleeping Giant Inn', 'region' => 'Riverwood', 'hold' => 'Whiterun']],
                'Whiterun Hold',
                'Whiterun'
            )
        );
    }
}
