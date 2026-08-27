<?php

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'prompt_composition.php';

final class PromptCompositionTest extends TestCase
{
    public function testPromptHeadKeepsXmlWrapperWhenMarkdownIsDisabled(): void
    {
        $systemPrompt = "<roleplay_instructions>\nStay in character.\n</roleplay_instructions>\n\n<world>\nWhiterun\n</world>\n";

        $this->assertSame($systemPrompt, chimFormatPromptHeadSection($systemPrompt, false));
    }

    public function testPromptHeadUsesMarkdownHeadingWhenEnabled(): void
    {
        $systemPrompt = "<roleplay_instructions>\nStay in character.\n</roleplay_instructions>\n\n"
            . "<world>\n<location>Whiterun</location>\n</world>\n\n"
            . "<character>\n<activity>\n#Activity\nIdle.\n</activity>\n"
            . "<personality>\n<traits>Loyal.</traits>\n</personality>\nUse <speech_style> for reference.\n</character>\n";

        $formatted = chimFormatPromptHeadSection($systemPrompt, true);

        $this->assertStringStartsWith("# Roleplay Instructions\n\nStay in character.", $formatted);
        $this->assertStringContainsString("# World\n\n- Location: Whiterun", $formatted);
        $this->assertStringContainsString("# Character\n\n## Activity\n\nIdle.", $formatted);
        $this->assertStringContainsString("## Personality\n\n### Traits\n\nLoyal.", $formatted);
        $this->assertStringContainsString('Use `Speech Style` for reference.', $formatted);
        $this->assertDoesNotMatchRegularExpression('/<\/?[A-Za-z][A-Za-z0-9_-]*>/', $formatted);
        $this->assertStringNotContainsString('#Activity', $formatted);
        $this->assertSame($formatted, chimFormatPromptHeadSection($formatted, true));
    }

    public function testMarkdownUsesHyphenListMarkersOnlyAtLineStarts(): void
    {
        $systemPrompt = "<condition>\n  • Health\n* Stamina\n+ Magicka\n- Existing\nInline • marker and *emphasis*.\n</condition>\n";

        $this->assertSame(
            "# Condition\n\n  - Health\n- Stamina\n- Magicka\n- Existing\nInline • marker and *emphasis*.\n",
            chimFormatPromptHeadSection($systemPrompt, true)
        );
        $this->assertSame($systemPrompt, chimFormatPromptHeadSection($systemPrompt, false));
    }

    public function testMarkdownKeepsListEntriesAndInstructionsWithoutDuplicateHeadings(): void
    {
        $systemPrompt = "<available_actions_list>\n#Available Actions\nUse an action:\nAVAILABLE ACTION: Talk\n</available_actions_list>\n"
            . "<nearby_actors>\n# NEARBY ACTORS/NPC IN THE SCENE\n## Anoriath (Male Wood Elf)\n</nearby_actors>\n"
            . "<nearby_items>\n# NEARBY ITEMS (format: RefID:ItemName)\n## 0x123:Bucket\n"
            . "# ITEM DESCRIPTIONS\n## Bucket: Wooden.\n</nearby_items>\n"
            . "<scene_notes>\n# SCENE NOTES\n## Someone is waiting.</scene_notes>\n"
            . "<knowledge>\n#Lore Information (You know this): Whiterun\nA city.\n</knowledge>\n";

        $this->assertSame(
            "# Available Actions\n\nUse an action:\n- AVAILABLE ACTION: Talk\n\n"
                . "# Nearby Actors\n\n- Anoriath (Male Wood Elf)\n\n"
                . "# Nearby Items\n\n(format: RefID:ItemName)\n- 0x123:Bucket\n\n"
                . "## Item Descriptions\n\n- Bucket: Wooden.\n\n"
                . "# Scene Notes\n\n- Someone is waiting.\n\n"
                . "# Knowledge\n\n## Lore Information (You know this): Whiterun\n\nA city.\n",
            chimFormatPromptHeadSection($systemPrompt, true)
        );
    }

    public function testMeasuresStringsAndMessageArraysWithoutSerializingMetadata(): void
    {
        $messages = [
            ['role' => 'system', 'content' => 'abcd'],
            ['role' => 'user', 'content' => '12345678'],
        ];

        $measurement = chimPromptCompositionMeasure($messages);

        $this->assertSame(12, $measurement['characters']);
        $this->assertSame(3, $measurement['estimated_tokens']);
    }

    public function testBuildsSectionBreakdownAndFinalMessageTotal(): void
    {
        $report = chimBuildPromptCompositionReport(
            'inputtext',
            [
                'roleplay_instructions' => '1234',
                'history' => [
                    ['role' => 'user', 'content' => '12345678'],
                ],
                'empty' => null,
            ],
            [
                ['role' => 'system', 'content' => '1234'],
                ['role' => 'user', 'content' => '12345678'],
            ]
        );

        $this->assertSame('inputtext', $report['request_type']);
        $this->assertSame(2, $report['message_count']);
        $this->assertSame(12, $report['total_characters']);
        $this->assertSame(3, $report['estimated_total_tokens']);
        $this->assertSame(
            ['characters' => 4, 'estimated_tokens' => 1],
            $report['sections']['roleplay_instructions']
        );
        $this->assertSame(
            ['characters' => 8, 'estimated_tokens' => 2],
            $report['sections']['history']
        );
        $this->assertSame(
            ['characters' => 0, 'estimated_tokens' => 0],
            $report['sections']['empty']
        );
    }

    public function testCountsUtf8CharactersWhenMbstringIsAvailable(): void
    {
        $measurement = chimPromptCompositionMeasure('éééé');

        $expectedCharacters = function_exists('mb_strlen') ? 4 : strlen('éééé');
        $this->assertSame($expectedCharacters, $measurement['characters']);
        $this->assertSame(intval(ceil($expectedCharacters / 4)), $measurement['estimated_tokens']);
    }

    public function testDiaryCompositionMeasuresTheConnectorBoundPrompt(): void
    {
        $main = file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'main.php');
        $diary = file_get_contents(
            dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'dynamic_update_util.php'
        );

        $this->assertIsString($main);
        $this->assertIsString($diary);
        $this->assertStringContainsString("if ((\$gameRequest[0] ?? '') !== 'diary')", $main);

        $followerStart = strpos($diary, 'function generateFollowerDiary');
        $this->assertNotFalse($followerStart);
        $followerSource = substr($diary, $followerStart);
        $matched = preg_match(
            "/chimLogPromptComposition\\s*\\(\\s*'diary'/",
            $followerSource,
            $logMatch,
            PREG_OFFSET_CAPTURE
        );
        $logPosition = $matched === 1 ? $logMatch[0][1] : false;
        $requestPosition = strpos($followerSource, '$connectionHandler->fast_request($contextData');

        $this->assertNotFalse($logPosition);
        $this->assertNotFalse($requestPosition);
        $this->assertLessThan($requestPosition, $logPosition);
    }
}
