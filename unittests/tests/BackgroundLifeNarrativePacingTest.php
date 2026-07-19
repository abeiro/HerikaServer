<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR
    . 'lib' . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'bgl_narrative_pacing.php');

final class BackgroundLifeNarrativePacingTest extends TestCase
{
    public function testActionSignatureUsesMeaningfulTarget(): void
    {
        $this->assertSame('speakto:adrianne avenicci', chimBglPacingActionSignature('SpeakTo:Adrianne Avenicci:0001A67C'));
        $this->assertSame('buyitem:belethor', chimBglPacingActionSignature('BuyItem:Belethor:00012EB7:1:10'));
    }

    public function testActionPhasesRepresentNarrativeBeatTypes(): void
    {
        $this->assertSame('transition', chimBglPacingPhaseForAction('TravelTo:Whiterun'));
        $this->assertSame('engagement', chimBglPacingPhaseForAction('SpeakTo:Lydia:000A2C8E'));
        $this->assertSame('resolution', chimBglPacingPhaseForAction('ReturnHome'));
        $this->assertSame('quiet', chimBglPacingPhaseForAction('StayAtPlace:Bannered Mare:Relax'));
    }

    public function testCadenceKeepsActiveThreadsMovingWithoutExceedingBaseCooldown(): void
    {
        $this->assertSame(6.0, chimBglPacingCadenceHours('transition', 24.0));
        $this->assertSame(12.0, chimBglPacingCadenceHours('engagement', 24.0));
        $this->assertSame(18.0, chimBglPacingCadenceHours('resolution', 24.0));
        $this->assertSame(24.0, chimBglPacingCadenceHours('quiet', 24.0));
        $this->assertSame(12.0, chimBglPacingCadenceHours('transition', 24.0, 3));
    }

    public function testThirdIdenticalActionIsRedirectedToQuietBeat(): void
    {
        $state = [
            'last_signature' => 'speakto:lydia',
            'repeat_count' => 2,
        ];

        $review = chimBglPacingReviewAction('SpeakTo:Lydia:000A2C8E', $state, 'Dragonsreach (Interior)', false);

        $this->assertTrue($review['adjusted']);
        $this->assertSame('StayAtPlace:Dragonsreach (Interior):Relax', $review['action']);
        $this->assertStringContainsString('too many times', $review['reason']);
    }

    public function testContinueIsOnlyAllowedDuringActiveJourney(): void
    {
        $blocked = chimBglPacingReviewAction('Continue', [], 'Whiterun', false);
        $allowed = chimBglPacingReviewAction('Continue', [], 'Whiterun', true);

        $this->assertTrue($blocked['adjusted']);
        $this->assertFalse($allowed['adjusted']);
        $this->assertSame('Continue', $allowed['action']);
    }

    public function testRecordingActionPersistsRecentBeatAndNextDueTime(): void
    {
        $state = chimBglPacingRecordAction([], 'TravelTo:Whiterun', 1000, 24.0);

        $this->assertSame('transition', $state['phase']);
        $this->assertSame(1, $state['repeat_count']);
        $this->assertSame(6.0, $state['cadence_hours']);
        $this->assertSame(1000 + chimBglPacingGametsForHours(6.0), $state['next_cycle_gamets']);
        $this->assertCount(1, $state['recent_actions']);
    }

    public function testDueCheckUsesPacingStateAndLegacyTimestampFallback(): void
    {
        $this->assertFalse(chimBglPacingIsDue([
            'background_life_pacing' => ['next_cycle_gamets' => 2000],
        ], 1999, 24.0));
        $this->assertTrue(chimBglPacingIsDue([
            'background_life_pacing' => ['next_cycle_gamets' => 2000],
        ], 2000, 24.0));

        $baseGamets = chimBglPacingGametsForHours(24.0);
        $this->assertFalse(chimBglPacingIsDue([
            'background_life_last_updated' => 1000,
        ], 999 + $baseGamets, 24.0));
        $this->assertTrue(chimBglPacingIsDue([
            'background_life_last_updated' => 1000,
        ], 1000 + $baseGamets, 24.0));
    }

    public function testCandidateSortPrioritizesOldestDueNpc(): void
    {
        $rows = [
            ['npc_name' => 'Later', 'extended_data' => json_encode(['background_life_pacing' => ['next_cycle_gamets' => 3000]])],
            ['npc_name' => 'Never Run', 'extended_data' => '{}'],
            ['npc_name' => 'Sooner', 'extended_data' => json_encode(['background_life_pacing' => ['next_cycle_gamets' => 2000]])],
        ];

        $sorted = chimBglPacingSortCandidates($rows, 24.0);

        $this->assertSame(['Never Run', 'Sooner', 'Later'], array_column($sorted, 'npc_name'));
    }

    public function testPromptBlockExplainsCurrentPhaseAndRecentActions(): void
    {
        $prompt = chimBglPacingPromptBlock([
            'phase' => 'engagement',
            'recent_actions' => [
                ['action' => 'MoveTo:Lydia'],
                ['action' => 'SpeakTo:Lydia:000A2C8E'],
            ],
        ]);

        $this->assertStringContainsString('<narrative_pacing>', $prompt);
        $this->assertStringContainsString('Current phase: engagement', $prompt);
        $this->assertStringContainsString('MoveTo:Lydia -> SpeakTo:Lydia:000A2C8E', $prompt);
    }
}
