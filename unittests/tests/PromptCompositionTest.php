<?php

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'prompt_composition.php';

final class PromptCompositionTest extends TestCase
{
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
