<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."lib".DIRECTORY_SEPARATOR."logger.php");

@define("MAXIMUM_SENTENCE_SIZE", 125);
@define("MINIMUM_SENTENCE_SIZE", 75);

require_once(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."lib".DIRECTORY_SEPARATOR."chat_helper_functions.php");

final class AsteriskParsingTest extends TestCase
{
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

    public function testPlayerSpeechStripsAsteriskActionBlocksFromTts(): void
    {
        $GLOBALS['REMOVE_ASTERISKS_FROM_OUTPUT'] = true;
        $GLOBALS['HERIKA_NAME'] = 'Player';

        $result = unmoodSentence('*draws close* Hello there *smiles softly*');

        $this->assertSame('Hello there', $result);
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

    public function testPlayerSpeechStillStripsActionsWhenRemoveAsterisksToggleIsOff(): void
    {
        $GLOBALS['REMOVE_ASTERISKS_FROM_OUTPUT'] = false;
        $GLOBALS['HERIKA_NAME'] = 'Player';

        $result = unmoodSentence('*wave* hello *wave* dude');

        $this->assertSame('hello dude', $result);
    }

    public function testPlayerSubtitleTextPreservesAsteriskActions(): void
    {
        $this->assertSame(
            'hello *wave* how are you *wave*',
            formatPlayerSubtitleText('hello *wave* how are you *wave* (Talking to Jon Battle-Born)')
        );
    }

    public function testNpcSpeechKeepsInlineEmphasisTextWhenRemovingAsterisks(): void
    {
        $GLOBALS['REMOVE_ASTERISKS_FROM_OUTPUT'] = true;
        $GLOBALS['HERIKA_NAME'] = 'Carlotta Valentia';

        $result = unmoodSentence("You find *my* humble produce stall 'wow-worthy,' Your Majesty?");

        $this->assertSame("You find my humble produce stall 'wow-worthy,' Your Majesty?", $result);
    }
}
