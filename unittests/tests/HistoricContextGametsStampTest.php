<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'logger.php');
require_once(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'data_functions.php');

/**
 * compactHistoricContext() rebuilds every history entry as a fresh array literal, which used to
 * drop the row's game-timestamp. STM needs it: the window's true oldest gamets is the only honest
 * answer to "how far back does this window reach", and therefore the only honest place to stop
 * showing summaries. These tests pin the '_g' stamp onto every output shape.
 */
final class HistoricContextGametsStampTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['HERIKA_NAME'] = 'Lydia';
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['HERIKA_NAME']);
    }

    public function testEveryCompactedEntryCarriesTheGametsStamp(): void
    {
        $input = [
            ['role' => 'player',        'content' => 'Prisoner: Where are we going?', 'gamets' => 1000],
            ['role' => 'assistant',     'content' => 'Lydia: North, my Thane.',       'gamets' => 1100],
            ['role' => 'narratorci',    'content' => 'LOCATION CHANGE to Whiterun',   'gamets' => 1200],
            ['role' => 'backgroundchat','content' => 'Guard: Halt.',                  'gamets' => 1300],
            ['role' => 'player',        'content' => 'Prisoner: Fine.',               'gamets' => 1400],
        ];

        $out = compactHistoricContext($input, 'Lydia', false);

        $this->assertNotEmpty($out, 'compaction returned nothing');
        foreach ($out as $i => $entry) {
            $this->assertArrayHasKey('_g', $entry, "entry $i lost its gamets stamp");
            $this->assertIsInt($entry['_g'], "entry $i has a non-int stamp");
            $this->assertGreaterThan(0, $entry['_g'], "entry $i has an empty stamp");
        }
    }

    public function testStampIsMonotonicAndTracksTheSourceRows(): void
    {
        $input = [
            ['role' => 'player',    'content' => 'Prisoner: One.',   'gamets' => 500],
            ['role' => 'assistant', 'content' => 'Lydia: Two.',      'gamets' => 900],
            ['role' => 'player',    'content' => 'Prisoner: Three.', 'gamets' => 1700],
        ];

        $out = compactHistoricContext($input, 'Lydia', false);

        $stamps = array_column($out, '_g');
        $this->assertNotEmpty($stamps);
        $sorted = $stamps;
        sort($sorted);
        $this->assertSame($sorted, $stamps, 'stamps must not go backwards through the window');
        $this->assertGreaterThanOrEqual(500, min($stamps));
        $this->assertLessThanOrEqual(1700, max($stamps));
    }

    public function testSynthesisedEntriesInheritTheLastSeenStampRatherThanZero(): void
    {
        // The first compaction pass invents assistant rows that carry no gamets of their own.
        // They belong to the same moment as the row before them, so they must inherit, not blank.
        $input = [
            ['role' => 'player',    'content' => 'Prisoner: Are you well?', 'gamets' => 2000],
            ['role' => 'assistant', 'content' => 'Lydia: I am.'],
            ['role' => 'assistant', 'content' => 'Lydia: Truly.'],
            ['role' => 'player',    'content' => 'Prisoner: Good.',         'gamets' => 2400],
        ];

        $out = compactHistoricContext($input, 'Lydia', false);

        foreach ($out as $i => $entry) {
            $this->assertArrayHasKey('_g', $entry, "entry $i lost its stamp");
            $this->assertGreaterThanOrEqual(2000, $entry['_g'], "entry $i fell back to zero");
        }
    }
}
