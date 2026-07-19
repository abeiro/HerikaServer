<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'scripted_dialogue_context.php');

final class ScriptedDialogueContextTest extends TestCase
{
    private function scriptedRow(string $speaker, string $line, int $localTs): array
    {
        return [
            'type' => 'chat',
            'subtype' => 'BACKDIAG',
            'data' => "(Context location: Bannered Mare, Hold: Whiterun background chat) {$speaker}: {$line}",
            'localts' => $localTs,
        ];
    }

    public function testSceneModePreservesAllScriptedDialogue(): void
    {
        $rows = [
            $this->scriptedRow('Lydia', 'Stay close.', 200),
            $this->scriptedRow('Hulda', 'Need a room?', 190),
        ];

        $this->assertSame($rows, chimFilterScriptedDialogueContextRows($rows, 'Lydia', [
            'mode' => 'scene',
        ]));
    }

    public function testSpeakerModeKeepsOnlyCurrentNpcLines(): void
    {
        $lydia = $this->scriptedRow('Lydia', 'Stay close.', 200);
        $rows = [
            $lydia,
            $this->scriptedRow('Hulda', 'Need a room?', 190),
        ];

        $this->assertSame([$lydia], array_values(chimFilterScriptedDialogueContextRows($rows, 'Lydia (busy)', [
            'mode' => 'speaker',
        ])));
    }

    public function testDisabledModeOnlyRemovesScriptedDialogue(): void
    {
        $normalChat = [
            'type' => 'chat',
            'subtype' => '',
            'data' => 'Lydia: I am sworn to carry your burdens.',
            'localts' => 200,
        ];

        $this->assertSame([$normalChat], array_values(chimFilterScriptedDialogueContextRows([
            $this->scriptedRow('Hulda', 'Need a room?', 210),
            $normalChat,
        ], 'Lydia', [
            'mode' => 'disabled',
        ])));
    }

    public function testRepeatWindowRetainsNewestMatchingLine(): void
    {
        $newest = $this->scriptedRow('Guard', 'No lollygagging.', 200);
        $olderDuplicate = $this->scriptedRow('Guard', 'No   lollygagging.', 180);
        $oldEnough = $this->scriptedRow('Guard', 'No lollygagging.', 100);

        $this->assertSame([$newest, $oldEnough], array_values(chimFilterScriptedDialogueContextRows([
            $newest,
            $olderDuplicate,
            $oldEnough,
        ], 'Guard', [
            'mode' => 'scene',
            'dedup_seconds' => 30,
        ])));
    }

    public function testLineLimitDoesNotRemoveOtherContextRows(): void
    {
        $normalEvent = [
            'type' => 'quest',
            'subtype' => 'QUEST',
            'data' => 'The Golden Claw has progressed.',
            'localts' => 195,
        ];
        $newest = $this->scriptedRow('Lydia', 'First.', 200);

        $this->assertSame([$newest, $normalEvent], array_values(chimFilterScriptedDialogueContextRows([
            $newest,
            $normalEvent,
            $this->scriptedRow('Hulda', 'Second.', 190),
        ], 'Lydia', [
            'mode' => 'scene',
            'line_limit' => 1,
        ])));
    }

    public function testPrefixDetectionDoesNotClassifyOrdinaryAiChat(): void
    {
        $this->assertFalse(chimIsScriptedDialogueContextRow([
            'type' => 'chat',
            'data' => 'Lydia: We should keep moving.',
        ]));
        $this->assertSame('Hivorate [Dremora]', chimExtractScriptedDialogueSpeaker(
            $this->scriptedRow('Hivorate [Dremora]', 'Mortal, prove yourself.', 200)
        ));
    }
}
