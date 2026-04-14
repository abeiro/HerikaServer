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
}
