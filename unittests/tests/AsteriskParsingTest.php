<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."lib".DIRECTORY_SEPARATOR."logger.php");

@define("MAXIMUM_SENTENCE_SIZE", 125);
@define("MINIMUM_SENTENCE_SIZE", 75);

require_once(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."lib".DIRECTORY_SEPARATOR."chat_helper_functions.php");

final class AsteriskParsingTest extends TestCase
{
    protected function tearDown(): void
    {
        unset(
            $GLOBALS['HERIKA_NAME'],
            $GLOBALS['PLAYER_NAME'],
            $GLOBALS['INLINE_NARRATION_MODE'],
            $GLOBALS['INLINE_NARRATION_ENABLED'],
            $GLOBALS['PRESERVE_ASTERISKS_IN_CONTEXT'],
            $GLOBALS['REMOVE_ASTERISKS_FROM_PLAYER_INPUT'],
            $GLOBALS['REMOVE_ASTERISKS_FROM_NPC_OUTPUT'],
            $GLOBALS['REMOVE_ASTERISKS_FROM_OUTPUT'],
            $GLOBALS['strip_emotes_from_output']
        );
    }

    public function testFullWrappedNarrationBlockDoesNotSplitMidReply(): void
    {
        $wrappedReply = "*A slight chuckle escapes me as I straighten a few more apples, my eyes crinkling at the corners. 'Wow,' you say? I hope that's a good 'wow,' Your Majesty. My produce is usually met with enthusiasm for its quality, not surprise. Though, I suppose a king might have seen grander displays of... apples.*";

        $splitPoint = findFastSentencePosition($wrappedReply);
        $chunks = split_sentences_stream($wrappedReply);
        $parts = extractNarrationAndDialogue($chunks[0]);

        $this->assertFalse($splitPoint);
        $this->assertCount(1, $chunks);
        $this->assertTrue($parts['has_narration']);
        $this->assertSame('', $parts['dialogue']);
        $this->assertSame(
            "A slight chuckle escapes me as I straighten a few more apples, my eyes crinkling at the corners. 'Wow,' you say? I hope that's a good 'wow,' Your Majesty. My produce is usually met with enthusiasm for its quality, not surprise. Though, I suppose a king might have seen grander displays of... apples.",
            $parts['narrations'][0]
        );
    }

    public function testLegacyInlineNarrationToggleFallsBackToNarratorMode(): void
    {
        $GLOBALS['INLINE_NARRATION_ENABLED'] = true;

        $this->assertSame('narrator', getInlineNarrationMode());
        $this->assertTrue(shouldSplitInlineNarration());
    }

    public function testInlineNarrationModeSupportsNpcVoiceMode(): void
    {
        $GLOBALS['INLINE_NARRATION_MODE'] = 'npc';

        $this->assertSame('npc', getInlineNarrationMode());
        $this->assertFalse(shouldSplitInlineNarration());
        $this->assertTrue(isInlineNarrationEnabled());
    }

    public function testPlayerSpeechStripsAsteriskActionBlocksWhenPlayerFilterEnabled(): void
    {
        $GLOBALS['REMOVE_ASTERISKS_FROM_PLAYER_INPUT'] = true;
        $GLOBALS['HERIKA_NAME'] = 'Player';

        $result = unmoodSentence('*draws close* Hello there *smiles softly*');

        $this->assertSame('Hello there', $result);
    }

    public function testPlayerSpeechKeepsAsteriskActionContentWhenPlayerFilterDisabled(): void
    {
        $GLOBALS['REMOVE_ASTERISKS_FROM_PLAYER_INPUT'] = false;
        $GLOBALS['HERIKA_NAME'] = 'Player';

        $result = unmoodSentence('*draws close* Hello there *smiles softly*');

        $this->assertSame('draws close Hello there smiles softly', $result);
    }

    public function testPlayerSpeechStripsRepeatedInlineActionBlocks(): void
    {
        $this->assertSame(
            'hello how are you',
            stripPlayerAsteriskActions('*wave* hello *wave* how *wave* are *wave* you')
        );
    }

    public function testPlayerSpeechActionOnlyBecomesSilent(): void
    {
        $this->assertSame('', stripPlayerAsteriskActions('*wave*'));
    }

    public function testPlayerSubtitleTextKeepsAsteriskActionsWhenPlayerFilterEnabled(): void
    {
        $GLOBALS['REMOVE_ASTERISKS_FROM_PLAYER_INPUT'] = true;

        $this->assertSame(
            'hello *wave* how are you *wave*',
            formatPlayerSubtitleText('hello *wave* how are you *wave* (Talking to Jon Battle-Born)')
        );
    }

    public function testPlayerSubtitleTextKeepsAsteriskActionsWhenPlayerFilterDisabled(): void
    {
        $GLOBALS['REMOVE_ASTERISKS_FROM_PLAYER_INPUT'] = false;

        $this->assertSame(
            'hello *wave* how are you *wave*',
            formatPlayerSubtitleText('hello *wave* how are you *wave* (Talking to Jon Battle-Born)')
        );
    }

    public function testNpcSpeechFiltersKnownAsteriskEmotesWhenNpcOutputFilterEnabled(): void
    {
        $GLOBALS['REMOVE_ASTERISKS_FROM_NPC_OUTPUT'] = true;
        $GLOBALS['HERIKA_NAME'] = 'Carlotta Valentia';

        $result = unmoodSentence('*smiles* Hello there');

        $this->assertSame('Hello there', $result);
    }

    public function testNpcSpeechKeepsAsteriskActionContentWhenNpcOutputFilterDisabled(): void
    {
        $GLOBALS['REMOVE_ASTERISKS_FROM_NPC_OUTPUT'] = false;
        $GLOBALS['HERIKA_NAME'] = 'Carlotta Valentia';

        $result = unmoodSentence('*smiles* Hello there');

        $this->assertSame('smiles Hello there', $result);
    }

    public function testNpcSpeechKeepsInlineEmphasisTextWhenNpcOutputFilterEnabled(): void
    {
        $GLOBALS['REMOVE_ASTERISKS_FROM_NPC_OUTPUT'] = true;
        $GLOBALS['HERIKA_NAME'] = 'Carlotta Valentia';

        $result = unmoodSentence("You find *my* humble produce stall 'wow-worthy,' Your Majesty?");

        $this->assertSame("You find my humble produce stall 'wow-worthy,' Your Majesty?", $result);
    }

    public function testNpcSubtitleFiltersAsteriskTextWhenNpcOutputFilterEnabled(): void
    {
        $GLOBALS['REMOVE_ASTERISKS_FROM_NPC_OUTPUT'] = true;
        $GLOBALS['HERIKA_NAME'] = 'Carlotta Valentia';

        $this->assertSame('Hello there', formatNpcSubtitleText('*smiles* Hello there'));
    }

    public function testNpcSubtitlePreservesAsteriskTextWhenNpcOutputFilterDisabled(): void
    {
        $GLOBALS['REMOVE_ASTERISKS_FROM_NPC_OUTPUT'] = false;
        $GLOBALS['HERIKA_NAME'] = 'Carlotta Valentia';

        $this->assertSame('*smiles* Hello there', formatNpcSubtitleText('*smiles* Hello there'));
    }

    public function testInlineNarrationDialogueSubtitleRespectsNpcOutputFilter(): void
    {
        $GLOBALS['REMOVE_ASTERISKS_FROM_NPC_OUTPUT'] = true;
        $GLOBALS['HERIKA_NAME'] = 'Carlotta Valentia';

        $this->assertSame('Hello my friend', formatInlineNarrationDialogueSubtitleText('. Hello *my* friend'));
    }

    public function testInlineNarrationDialogueSubtitleKeepsAsteriskTextWhenNpcOutputFilterDisabled(): void
    {
        $GLOBALS['REMOVE_ASTERISKS_FROM_NPC_OUTPUT'] = false;
        $GLOBALS['HERIKA_NAME'] = 'Carlotta Valentia';

        $this->assertSame('Hello *my* friend', formatInlineNarrationDialogueSubtitleText('. Hello *my* friend'));
    }

    public function testInlineNarrationDialogueSubtitleKeepsLeadingEmphasisWhenNpcOutputFilterDisabled(): void
    {
        $GLOBALS['REMOVE_ASTERISKS_FROM_NPC_OUTPUT'] = false;
        $GLOBALS['HERIKA_NAME'] = 'Carlotta Valentia';

        $this->assertSame('*My* friend', formatInlineNarrationDialogueSubtitleText('*My* friend'));
    }

    public function testNarrationSubtitleFormatterWrapsNarrationInSingleAsterisks(): void
    {
        $this->assertSame('*She smiles softly*', formatNarrationSubtitleText('She smiles softly'));
    }

    public function testCleanContextFirstPassKeepsAsterisksWhenNarratorModeEnabled(): void
    {
        $GLOBALS['INLINE_NARRATION_MODE'] = 'narrator';
        $GLOBALS['PRESERVE_ASTERISKS_IN_CONTEXT'] = false;

        $this->assertFalse(shouldStripAsterisksFromCleanContextBuffer());
    }

    public function testCleanContextFirstPassKeepsAsterisksWhenNpcModeEnabled(): void
    {
        $GLOBALS['INLINE_NARRATION_MODE'] = 'npc';
        $GLOBALS['PRESERVE_ASTERISKS_IN_CONTEXT'] = false;

        $this->assertFalse(shouldStripAsterisksFromCleanContextBuffer());
    }

    public function testCleanContextFirstPassKeepsAsterisksWhenContextPreservationIsEnabled(): void
    {
        $GLOBALS['INLINE_NARRATION_MODE'] = 'disabled';
        $GLOBALS['PRESERVE_ASTERISKS_IN_CONTEXT'] = true;

        $this->assertFalse(shouldStripAsterisksFromCleanContextBuffer());
    }

    public function testCleanContextFirstPassStripsAsterisksOnlyWhenNarrationIsDisabledAndNotPreserved(): void
    {
        $GLOBALS['INLINE_NARRATION_MODE'] = 'disabled';
        $GLOBALS['PRESERVE_ASTERISKS_IN_CONTEXT'] = false;

        $this->assertTrue(shouldStripAsterisksFromCleanContextBuffer());
    }
}
