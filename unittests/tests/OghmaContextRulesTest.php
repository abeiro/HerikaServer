<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../lib/oghma_forced_context.php';

final class OghmaContextRuleFakeDb
{
    public array $queries = [];

    public function escape($value): string
    {
        return str_replace("'", "''", (string)$value);
    }

    public function fetchAll($query): array
    {
        $this->queries[] = $query;
        if (str_contains($query, 'FROM public.oghma_context_rule')) {
            return [[
                'id' => 7,
                'label' => 'Nord dialogue lore',
                'priority' => 10,
                'selector_type' => 'topic',
                'selector_value' => 'nord',
                'conditions' => json_encode([
                    'race' => ['Nord'],
                    'event_type' => ['inputtext'],
                ]),
                'max_articles' => 1,
            ]];
        }
        if (str_contains($query, 'FROM public.oghma')) {
            return [[
                'topic' => 'nord',
                'topic_desc' => 'Advanced Nord lore.',
                'knowledge_class' => '',
                'topic_desc_basic' => 'Basic Nord lore.',
                'knowledge_class_basic' => '',
            ]];
        }
        return [];
    }
}

final class OghmaContextRulesTest extends TestCase
{
    protected function tearDown(): void
    {
        foreach ([
            'CHIM_CORE_CURRENT_NPC_DATA',
            'HERIKA_NAME',
            'CACHE_PEOPLE',
            'gameRequest',
            'OGHMA_KNOWLEDGE',
            'OGHMA_HINT',
            'OGHMA_INJECTED_TOPICS',
        ] as $key) {
            unset($GLOBALS[$key]);
        }
    }

    public function testConditionsRequireEveryFieldAndAllowAlternativesWithinAField(): void
    {
        $context = [
            'race' => ['dark elf', 'dunmer'],
            'hold' => ['whiterun hold', 'whiterun'],
            'event_type' => ['inputtext'],
        ];
        $reasons = [];

        $this->assertTrue(chimOghmaContextRuleMatches([
            'race' => ['Nord', 'Dunmer'],
            'hold' => ['Whiterun'],
        ], $context, $reasons));
        $this->assertSame(['race=dunmer', 'hold=whiterun'], $reasons);

        $this->assertFalse(chimOghmaContextRuleMatches([
            'race' => ['Dunmer'],
            'hold' => ['The Rift'],
        ], $context));
    }

    public function testRuleInspectionReportsEveryConditionWithoutChangingMatchSemantics(): void
    {
        $inspection = chimOghmaInspectContextRuleConditions([
            'race' => ['Nord', 'Dunmer'],
            'hold' => ['The Rift'],
            'event_type' => ['inputtext'],
        ], [
            'race' => ['Dunmer'],
            'hold' => ['Whiterun'],
            'event_type' => ['inputtext'],
        ]);

        $this->assertFalse($inspection['matches']);
        $this->assertCount(3, $inspection['conditions']);
        $this->assertTrue($inspection['conditions'][0]['matches']);
        $this->assertSame(['dunmer'], $inspection['conditions'][0]['matched']);
        $this->assertFalse($inspection['conditions'][1]['matches']);
        $this->assertSame(['whiterun'], $inspection['conditions'][1]['actual']);
        $this->assertTrue($inspection['conditions'][2]['matches']);
    }

    public function testContextRuleInjectionUsesExistingPermissionAndDeduplicationPath(): void
    {
        $GLOBALS['CHIM_CORE_CURRENT_NPC_DATA'] = [
            'npc_name' => 'Hilde',
            'race' => 'NordRace',
            'profile_id' => 1,
        ];
        $GLOBALS['HERIKA_NAME'] = 'Hilde';
        $GLOBALS['CACHE_PEOPLE'] = '|Hilde|RANGROO|';
        $GLOBALS['gameRequest'] = ['inputtext'];
        $GLOBALS['OGHMA_KNOWLEDGE'] = 'knowall';
        $GLOBALS['OGHMA_HINT'] = '';
        $GLOBALS['OGHMA_INJECTED_TOPICS'] = [];

        $db = new OghmaContextRuleFakeDb();
        $this->assertSame(1, chimOghmaInjectContextRules($db));
        $this->assertStringContainsString('Advanced Nord lore.', $GLOBALS['OGHMA_HINT']);
        $this->assertTrue(chimOghmaTopicWasInjected('nord'));
    }

    public function testSelectorQueriesAreBoundedToFiveArticles(): void
    {
        $db = new OghmaContextRuleFakeDb();
        chimOghmaFindRowsForRuleSelector($db, 'category', 'Lore', 99);

        $this->assertStringContainsString('LIMIT 5', end($db->queries));
    }

    public function testWeatherConditionsCanMatchOnePartOfCombinedWeather(): void
    {
        $this->assertSame(
            ['outdoors it is rainy foggy', 'rainy foggy', 'rainy', 'foggy'],
            chimOghmaWeatherSignals('Outdoors it is rainy, foggy')
        );
    }
}
