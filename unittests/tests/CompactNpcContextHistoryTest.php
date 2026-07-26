<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'compact_context_history.php');

final class CompactNpcContextHistoryTest extends TestCase
{
    protected function tearDown(): void
    {
        unset(
            $GLOBALS['FOCUS_CHAT_MODE'],
            $GLOBALS['HERIKA_NAME']
        );
    }

    public function testSettingIsDisabledByDefault(): void
    {
        $GLOBALS['HERIKA_NAME'] = 'Lucan Valerius';

        $this->assertFalse(chimFocusChatContextEnabled());
        $this->assertFalse(chimShouldCompactNpcContextHistory());
    }

    public function testNarratorIsExcludedWhenSettingIsEnabled(): void
    {
        $GLOBALS['FOCUS_CHAT_MODE'] = true;

        $this->assertFalse(chimShouldCompactNpcContextHistory('The Narrator'));
        $this->assertTrue(chimShouldCompactNpcContextHistory('Lucan Valerius'));
    }

    public function testCombinesRecentLucanConversationIntoOnePlaintextBlock(): void
    {
        $history = [
            [
                'role' => 'assistant',
                'content' => '{"character":"Lucan Valerius","listener":"RANGROO","mood":"kindly","action":"Talk","target":"","item":"","lang":"en","message":"Good evening. Looking for something in particular?"}',
            ],
            ['role' => 'user', 'content' => " (... \nLucan Valerius: The sooner you find the claw, the sooner our lives can get back to normal.\n...)"],
            ['role' => 'user', 'content' => 'RANGROO: What have you got for sale? (Talking to Lucan Valerius)'],
            ['role' => 'assistant', 'content' => 'Oh, a bit of this and a bit of that.'],
            ['role' => 'user', 'content' => '10.9999968 hours have passed. Current date/time: Day name: Tirdas, Hour: 7:19 AM, Day Number: 19, Month: Last Seed, 4th Era, Year: 201'],
            ['role' => 'user', 'content' => 'LOCATION CHANGE to Riverwood Trader, hold: Whiterun, timeline mark: 0 hours ago'],
        ];

        $formatted = chimFormatCompactNpcContextHistory($history, 'Lucan Valerius');

        $this->assertSame(
            implode("\n", [
                '# Lucan Valerius, speaking to RANGROO: Good evening. Looking for something in particular?',
                '# Ambient dialogue: Lucan Valerius: The sooner you find the claw, the sooner our lives can get back to normal.',
                '# RANGROO, speaking to Lucan Valerius: What have you got for sale?',
                '# Lucan Valerius: Oh, a bit of this and a bit of that.',
                '# After 11 hours, it is now Tirdas, 7:19 AM, 19 Last Seed, 4E 201; the current scene is at Riverwood Trader in Whiterun Hold.',
            ]),
            $formatted
        );
        $this->assertStringNotContainsString('{"character"', $formatted);
    }

    public function testKeepsHistoricActionsInPlaintext(): void
    {
        $history = [[
            'role' => 'assistant',
            'content' => '{"character":"Lucan Valerius","listener":"RANGROO","message":"Come with me.","action":"Follow","target":"RANGROO"}',
        ]];

        $formatted = chimFormatCompactNpcContextHistory($history, 'Lucan Valerius');

        $this->assertSame(
            '# Lucan Valerius, speaking to RANGROO: Come with me. [Action: Follow, targeting RANGROO]',
            $formatted
        );
    }

    public function testConvertsToolHistoryToPlaintext(): void
    {
        $history = [
            ['role' => 'user', 'content' => 'RANGROO: Wait here. (Talking to Lucan Valerius)'],
            [
                'role' => 'assistant',
                'content' => null,
                'tool_calls' => [[
                    'id' => 'call_1',
                    'function' => ['name' => 'WaitHere'],
                ]],
            ],
            ['role' => 'tool', 'content' => 'WaitHere completed.'],
        ];

        $formatted = chimFormatCompactNpcContextHistory($history, 'Lucan Valerius');

        $this->assertSame(
            implode("\n", [
                '# RANGROO, speaking to Lucan Valerius: Wait here.',
                '# Requested action: WaitHere.',
                '# Tool result: WaitHere completed.',
            ]),
            $formatted
        );
    }

    public function testAppendsHistoryInsideTheSystemPromptWithoutAddingMessages(): void
    {
        $worldContext = [[
            'role' => 'system',
            'content' => '<actors_nearby>Lucan Valerius</actors_nearby>',
        ]];

        $result = chimAppendCompactHistoryToPrompt(
            $worldContext,
            "# After 11 hours, it is now Tirdas, 7:19 AM.\n# RANGROO, speaking to Hilde: hello"
        );

        $this->assertCount(1, $result);
        $this->assertSame('system', $result[0]['role']);
        $this->assertSame(
            "<actors_nearby>Lucan Valerius</actors_nearby>\n\n"
                . "# After 11 hours, it is now Tirdas, 7:19 AM.\n"
                . "# RANGROO, speaking to Hilde: hello",
            $result[0]['content']
        );
    }
}
