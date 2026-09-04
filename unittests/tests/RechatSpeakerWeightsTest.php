<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../lib/logger.php';
Logger::setCustomLog(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'chim-rechat-speaker-weights-test.log');
require_once __DIR__ . '/../../lib/chat_helper_functions.php';
require_once __DIR__ . '/../../lib/data_functions.php';

final class RechatSpeakerWeightsTest extends TestCase
{
    protected function tearDown(): void
    {
        unset(
            $GLOBALS['gameRequest'],
            $GLOBALS['RECHAT_RESOLVED_TARGET'],
            $GLOBALS['LAST_LLM_RESPONSE'],
            $GLOBALS['CHIM_RECHAT_BUDGET_FILE'],
            $GLOBALS['CHIM_RECHAT_ENDED_NATURALLY']
        );
    }

    public function testNormalizeRejectsInvalidEntriesAndMergesDuplicateSpeakers(): void
    {
        $weights = chimNormalizeRechatSpeakerWeights([
            ['speaker' => 'Karrie', 'weight' => 70],
            ['speaker' => 'karrie', 'weight' => 60],
            ['speaker' => 'Jaryra', 'weight' => 0],
            ['speaker' => 'Catarina', 'weight' => 101],
            ['speaker' => '', 'weight' => 50],
            ['speaker' => 'Ralof', 'weight' => 'not-a-number'],
        ]);

        $this->assertSame([
            ['speaker' => 'Karrie', 'weight' => 100],
        ], $weights);
    }

    public function testCloseModeKeepsConversationalAudienceBounded(): void
    {
        $this->assertFalse(chimShouldExpandConversationalRechatAudience('conversational', 'CLOSE'));
        $this->assertTrue(chimShouldExpandConversationalRechatAudience('conversational', 'STANDARD'));
        $this->assertFalse(chimShouldExpandConversationalRechatAudience('tight', 'STANDARD'));
        $this->assertFalse(chimShouldExpandConversationalRechatAudience(
            'conversational',
            'STANDARD',
            [
                'mode' => 'close',
                'locked_audience' => ['Katia', 'Alex', 'Lydia'],
            ]
        ));
        $this->assertFalse(chimShouldExpandConversationalRechatAudience(
            'conversational',
            'STANDARD',
            [
                'mode' => 'bounded',
                'locked_audience' => [],
            ]
        ));
    }

    public function testCloseOriginScopeReplacesRediscoveredAudienceWithLockedMembers(): void
    {
        $scopedAudience = chimApplyRechatOriginScopeToAudience(
            '|Katia|Alex|Lydia|Newcomer|',
            [
                'mode' => 'close',
                'locked_audience' => ['Katia', 'Alex', 'Lydia'],
            ]
        );

        $this->assertSame('|Katia|Alex|Lydia|', $scopedAudience['people_pipe']);
        $this->assertSame(['Katia', 'Alex', 'Lydia'], $scopedAudience['audience']);
        $this->assertSame([
            'mode' => 'close',
            'locked_audience' => ['Katia', 'Alex', 'Lydia'],
        ], $scopedAudience['origin_scope']);
    }

    public function testBoundedFallbackCapturesTheExistingAudienceBeforeExpansion(): void
    {
        $scopedAudience = chimApplyRechatOriginScopeToAudience(
            '|Katia|Alex|Lydia|',
            [
                'mode' => 'bounded',
                'locked_audience' => [],
            ]
        );

        $this->assertSame('|Katia|Alex|Lydia|', $scopedAudience['people_pipe']);
        $this->assertSame(['Katia', 'Alex', 'Lydia'], $scopedAudience['audience']);
        $this->assertSame([
            'mode' => 'bounded',
            'locked_audience' => ['Katia', 'Alex', 'Lydia'],
        ], $scopedAudience['origin_scope']);
    }

    public function testStateIsSavedAndConsumedOnlyOnce(): void
    {
        $chainId = 'speaker-weight-test-' . bin2hex(random_bytes(8));
        $resolvedTarget = [
            'mode' => 'conversational',
            'chain_id' => $chainId,
            'selected' => 'Jaryra',
            'speaker_history' => ['Karrie', 'Jaryra', 'Karrie'],
        ];

        try {
            $this->assertTrue(chimSaveRechatSpeakerWeights($resolvedTarget, [
                ['speaker' => 'Karrie', 'weight' => 70],
                ['speaker' => 'Catarina', 'weight' => 30],
            ]));
            $this->assertSame([
                'speaker_weights' => [
                    ['speaker' => 'Karrie', 'weight' => 70],
                    ['speaker' => 'Catarina', 'weight' => 30],
                ],
                'speaker_history' => ['Karrie', 'Jaryra', 'Karrie'],
            ], chimConsumeRechatRouteState($chainId));
            $this->assertSame([
                'speaker_weights' => [],
                'speaker_history' => [],
            ], chimConsumeRechatRouteState($chainId));
        } finally {
            $stateFile = chimRechatRouteStateFile($chainId);
            if ($stateFile !== '' && is_file($stateFile)) {
                unlink($stateFile);
            }
        }
    }

    public function testSpeakerHistoryIsSavedWithoutValidWeights(): void
    {
        $chainId = 'speaker-history-only-test-' . bin2hex(random_bytes(8));
        $resolvedTarget = [
            'mode' => 'conversational',
            'chain_id' => $chainId,
            'selected' => 'Jaryra',
            'speaker_history' => ['Karrie', 'Jaryra'],
        ];

        try {
            $this->assertTrue(chimSaveRechatSpeakerWeights($resolvedTarget, [
                ['speaker' => 'Catarina', 'weight' => 0],
            ]));
            $this->assertSame([
                'speaker_weights' => [],
                'speaker_history' => ['Karrie', 'Jaryra'],
            ], chimConsumeRechatRouteState($chainId));
        } finally {
            $stateFile = chimRechatRouteStateFile($chainId);
            if ($stateFile !== '' && is_file($stateFile)) {
                unlink($stateFile);
            }
        }
    }

    public function testStateIsNotSavedOutsideConversationalModeOrWithoutChainId(): void
    {
        $this->assertFalse(chimSaveRechatSpeakerWeights([
            'mode' => 'tight',
            'chain_id' => 'tight-chain',
            'selected' => 'Jaryra',
        ], [['speaker' => 'Karrie', 'weight' => 100]]));

        $this->assertFalse(chimSaveRechatSpeakerWeights([
            'mode' => 'conversational',
            'chain_id' => '',
            'selected' => 'Jaryra',
        ], [['speaker' => 'Karrie', 'weight' => 100]]));
    }

    public function testRouteStateIsConsumedAndDiscardedOutsideConversationalMode(): void
    {
        $chainId = 'speaker-mode-change-test-' . bin2hex(random_bytes(8));
        $resolvedTarget = [
            'mode' => 'conversational',
            'chain_id' => $chainId,
            'selected' => 'Jaryra',
            'speaker_history' => ['Jaryra'],
        ];

        try {
            $this->assertTrue(chimSaveRechatSpeakerWeights($resolvedTarget, [
                ['speaker' => 'Karrie', 'weight' => 100],
            ]));
            $this->assertSame([
                'speaker_weights' => [],
                'speaker_history' => [],
            ], chimConsumeApplicableRechatRouteState($chainId, 'group'));
            $this->assertSame([
                'speaker_weights' => [],
                'speaker_history' => [],
            ], chimConsumeApplicableRechatRouteState($chainId, 'conversational'));
        } finally {
            $stateFile = chimRechatRouteStateFile($chainId);
            if ($stateFile !== '' && is_file($stateFile)) {
                unlink($stateFile);
            }
        }
    }

    public function testRepeatPenaltyStartsOnThirdSelectionAndStopsAtSixtyPercent(): void
    {
        $this->assertSame(40, chimApplyRechatSpeakerRepeatPenalty(40, 1));
        $this->assertSame(40, chimApplyRechatSpeakerRepeatPenalty(40, 2));
        $this->assertSame(34, chimApplyRechatSpeakerRepeatPenalty(40, 3));
        $this->assertSame(28, chimApplyRechatSpeakerRepeatPenalty(40, 4));
        $this->assertSame(24, chimApplyRechatSpeakerRepeatPenalty(40, 5));
        $this->assertSame(24, chimApplyRechatSpeakerRepeatPenalty(40, 20));
        $this->assertSame(1, chimApplyRechatSpeakerRepeatPenalty(1, 20));
    }

    public function testAlternatingSpeakersAccumulateIndependentSelectionCounts(): void
    {
        $history = ['Karrie', 'Jaryra', 'Karrie', 'Jaryra'];

        $this->assertSame(3, chimRechatSpeakerSelectionCount('Karrie', $history));
        $this->assertSame(3, chimRechatSpeakerSelectionCount('Jaryra', $history));
        $this->assertSame(1, chimRechatSpeakerSelectionCount('Catarina', $history));
    }

    public function testThirdParticipantResetsPreviousExchangeHistory(): void
    {
        $history = ['Karrie', 'Jaryra', 'Karrie', 'Jaryra'];

        $this->assertSame(
            ['Catarina'],
            chimAdvanceRechatSpeakerHistory($history, 'Catarina')
        );
        $this->assertSame(
            ['Karrie', 'Jaryra', 'Karrie', 'Jaryra', 'Karrie'],
            chimAdvanceRechatSpeakerHistory($history, 'Karrie')
        );
    }

    public function testStructuredResponseWeightsAreCapturedForTheNextTurn(): void
    {
        $chainId = 'speaker-capture-test-' . bin2hex(random_bytes(8));
        $GLOBALS['gameRequest'] = ['rechat'];
        $GLOBALS['RECHAT_RESOLVED_TARGET'] = [
            'mode' => 'conversational',
            'chain_id' => $chainId,
            'selected' => 'Jaryra',
            'speaker_history' => ['Jaryra'],
        ];
        $GLOBALS['LAST_LLM_RESPONSE'] = [
            'speaker_weights' => [
                ['speaker' => 'Karrie', 'weight' => 80],
                ['speaker' => 'Catarina', 'weight' => 20],
            ],
        ];

        try {
            $this->assertTrue(chimCaptureRechatSpeakerWeights());
            $this->assertSame([
                'speaker_weights' => [
                    ['speaker' => 'Karrie', 'weight' => 80],
                    ['speaker' => 'Catarina', 'weight' => 20],
                ],
                'speaker_history' => ['Jaryra'],
            ], chimConsumeRechatRouteState($chainId));
        } finally {
            $stateFile = chimRechatRouteStateFile($chainId);
            if ($stateFile !== '' && is_file($stateFile)) {
                unlink($stateFile);
            }
        }
    }

    public function testSpeakerHistoryIsCapturedWithoutParsedConnectorResponse(): void
    {
        $chainId = 'speaker-history-capture-test-' . bin2hex(random_bytes(8));
        $GLOBALS['gameRequest'] = ['rechat'];
        $GLOBALS['RECHAT_RESOLVED_TARGET'] = [
            'mode' => 'conversational',
            'chain_id' => $chainId,
            'selected' => 'Jaryra',
            'speaker_history' => ['Karrie', 'Jaryra'],
        ];

        try {
            $this->assertTrue(chimCaptureRechatSpeakerWeights());
            $this->assertSame([
                'speaker_weights' => [],
                'speaker_history' => ['Karrie', 'Jaryra'],
            ], chimConsumeRechatRouteState($chainId));
        } finally {
            $stateFile = chimRechatRouteStateFile($chainId);
            if ($stateFile !== '' && is_file($stateFile)) {
                unlink($stateFile);
            }
        }
    }

    public function testExactEndSentinelExhaustsBudgetWithoutAcceptingSimilarText(): void
    {
        $budgetFile = tempnam(sys_get_temp_dir(), 'chim-rechat-end-test-');
        $this->assertIsString($budgetFile);
        file_put_contents($budgetFile, json_encode([
            'budget' => 5,
            'used' => 1,
            'ts' => time(),
        ]));
        $GLOBALS['gameRequest'] = ['rechat'];
        $GLOBALS['CHIM_RECHAT_BUDGET_FILE'] = $budgetFile;

        try {
            $this->assertFalse(chimHandleRechatEndSentinel('__CHIM_RECHAT_END__ extra'));
            $unchangedState = json_decode((string)file_get_contents($budgetFile), true);
            $this->assertSame(1, $unchangedState['used']);

            $this->assertTrue(chimHandleRechatEndSentinel('__CHIM_RECHAT_END__'));
            $endedState = json_decode((string)file_get_contents($budgetFile), true);
            $this->assertSame(5, $endedState['used']);
            $this->assertTrue($GLOBALS['CHIM_RECHAT_ENDED_NATURALLY']);
        } finally {
            if (is_file($budgetFile)) {
                unlink($budgetFile);
            }
        }
    }
}
