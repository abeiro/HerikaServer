<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'lib'.DIRECTORY_SEPARATOR.'emote_moods.php';

final class EmoteMoodsTest extends TestCase
{
    public function testNormalizeEmoteMoods_SplitsConcatenatedKnownMoods(): void
    {
        $normalized = normalizeEmoteMoods('mockingdesperatedistressedpleadingsad');

        $this->assertSame(
            ['sarcastic', 'desperate', 'scared', 'pleading', 'sad'],
            $normalized
        );
    }

    public function testNormalizeEmoteMoods_PreservesCustomMoodsAndRemovesDuplicates(): void
    {
        $normalized = normalizeEmoteMoods("sassy, custommood ,sassy|teasing\ncustommood");

        $this->assertSame(
            ['sassy', 'custommood', 'teasing'],
            $normalized
        );
    }

    public function testNormalizeEmoteMoods_MapsDeprecatedValuesToPreferredSet(): void
    {
        $normalized = normalizeEmoteMoods('sardonic,default,assisting,distressed,mocking');

        $this->assertSame(
            ['sarcastic', 'neutral', 'scared'],
            $normalized
        );
    }

    public function testExtractFirstEmoteMood_ReturnsOnlyTheFirstMoodFromDelimitedInput(): void
    {
        $selectedMood = extractFirstEmoteMood('horrified|disturbed|shaken');

        $this->assertSame('horrified', $selectedMood);
    }

    public function testExtractFirstEmoteMood_NormalizesAliasesBeforeSelectingFirstMood(): void
    {
        $selectedMood = extractFirstEmoteMood('sardonic|teasing');

        $this->assertSame('sarcastic', $selectedMood);
    }
}
