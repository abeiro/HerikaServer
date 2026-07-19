<?php

const CHIM_BGL_GAMETS_TO_HOURS = 0.0000024;
const CHIM_BGL_PACING_HISTORY_LIMIT = 6;
const CHIM_BGL_PACING_REPEAT_LIMIT = 2;

function chimBglPacingGametsForHours(float $hours): int
{
    return (int) round(max(0.0, $hours) / CHIM_BGL_GAMETS_TO_HOURS);
}

function chimBglPacingParseAction(string $action): array
{
    $action = trim($action);
    [$rawCommand, $argument] = array_pad(explode(':', $action, 2), 2, '');
    $commandLookup = [
        'travelto' => 'TravelTo',
        'stayatplace' => 'StayAtPlace',
        'returnhome' => 'ReturnHome',
        'findnpc' => 'FindNPC',
        'moveto' => 'MoveTo',
        'speakto' => 'SpeakTo',
        'buyitem' => 'BuyItem',
        'sellitem' => 'SellItem',
        'continue' => 'Continue',
    ];

    $commandKey = strtolower(trim($rawCommand));
    $command = $commandLookup[$commandKey] ?? trim($rawCommand);
    $argument = trim(preg_replace('/\s+/', ' ', $argument) ?? $argument);

    return [
        'command' => $command,
        'argument' => $argument,
    ];
}

function chimBglPacingActionSignature(string $action): string
{
    $parts = chimBglPacingParseAction($action);
    $command = strtolower($parts['command']);
    $argument = $parts['argument'];

    if (in_array($parts['command'], ['SpeakTo', 'MoveTo', 'FindNPC'], true)) {
        $argument = explode(':', $argument, 2)[0] ?? '';
    } elseif (in_array($parts['command'], ['BuyItem', 'SellItem'], true)) {
        $firstTransaction = explode(',', $argument, 2)[0] ?? '';
        $argument = explode(':', $firstTransaction, 2)[0] ?? '';
    }

    $argument = strtolower(trim(preg_replace('/\s+/', ' ', $argument) ?? $argument));
    return $argument === '' ? $command : "$command:$argument";
}

function chimBglPacingPhaseForAction(string $action): string
{
    $command = chimBglPacingParseAction($action)['command'];

    if (in_array($command, ['TravelTo', 'MoveTo', 'FindNPC', 'Continue'], true)) {
        return 'transition';
    }
    if (in_array($command, ['SpeakTo', 'BuyItem', 'SellItem'], true)) {
        return 'engagement';
    }
    if ($command === 'ReturnHome') {
        return 'resolution';
    }

    return 'quiet';
}

function chimBglPacingCadenceHours(string $phase, float $baseHours, int $repeatCount = 1): float
{
    $baseHours = max(1.0, $baseHours);
    $factor = [
        'transition' => 0.25,
        'engagement' => 0.5,
        'resolution' => 0.75,
        'quiet' => 1.0,
    ][$phase] ?? 1.0;

    $hours = max(1.0, $baseHours * $factor);
    if ($repeatCount > 1) {
        $hours *= 1.0 + (0.5 * min(2, $repeatCount - 1));
    }

    return min($baseHours, round($hours, 2));
}

function chimBglPacingReviewAction(
    string $action,
    array $state,
    string $currentLocation = '',
    bool $isTravelling = false
): array {
    $parts = chimBglPacingParseAction($action);
    $signature = chimBglPacingActionSignature($action);
    $lastSignature = (string) ($state['last_signature'] ?? '');
    $repeatCount = (int) ($state['repeat_count'] ?? 0);
    $reason = '';

    if ($parts['command'] === 'Continue' && !$isTravelling) {
        $reason = 'Continue was selected without an active journey.';
    } elseif ($signature !== '' && $signature === $lastSignature && $repeatCount >= CHIM_BGL_PACING_REPEAT_LIMIT) {
        $reason = 'The same background action was selected too many times in succession.';
    }

    if ($reason === '') {
        return [
            'action' => trim($action),
            'adjusted' => false,
            'reason' => '',
        ];
    }

    $location = trim(str_replace(':', ' ', $currentLocation));
    if ($location === '') {
        $location = 'Current Location';
    }

    return [
        'action' => "StayAtPlace:$location:Relax",
        'adjusted' => true,
        'reason' => $reason,
    ];
}

function chimBglPacingRecordAction(
    array $state,
    string $action,
    int $currentGamets,
    float $baseHours,
    bool $adjusted = false,
    string $adjustmentReason = ''
): array {
    $signature = chimBglPacingActionSignature($action);
    $lastSignature = (string) ($state['last_signature'] ?? '');
    $repeatCount = $signature !== '' && $signature === $lastSignature
        ? ((int) ($state['repeat_count'] ?? 0)) + 1
        : 1;
    $phase = chimBglPacingPhaseForAction($action);
    $cadenceHours = chimBglPacingCadenceHours($phase, $baseHours, $repeatCount);
    $recentActions = is_array($state['recent_actions'] ?? null) ? $state['recent_actions'] : [];

    $recentActions[] = [
        'action' => trim($action),
        'signature' => $signature,
        'phase' => $phase,
        'gamets' => $currentGamets,
        'adjusted' => $adjusted,
    ];
    $recentActions = array_slice($recentActions, -CHIM_BGL_PACING_HISTORY_LIMIT);

    return [
        'version' => 1,
        'phase' => $phase,
        'last_action' => trim($action),
        'last_signature' => $signature,
        'repeat_count' => $repeatCount,
        'recent_actions' => array_values($recentActions),
        'last_cycle_gamets' => $currentGamets,
        'next_cycle_gamets' => $currentGamets + chimBglPacingGametsForHours($cadenceHours),
        'cadence_hours' => $cadenceHours,
        'last_adjustment_reason' => $adjusted ? trim($adjustmentReason) : '',
    ];
}

function chimBglPacingIsDue(array $extendedData, int $currentGamets, float $baseHours): bool
{
    $state = is_array($extendedData['background_life_pacing'] ?? null)
        ? $extendedData['background_life_pacing']
        : [];
    $nextCycle = (int) ($state['next_cycle_gamets'] ?? 0);
    if ($nextCycle > 0) {
        return $currentGamets >= $nextCycle;
    }

    $lastUpdated = (int) ($extendedData['background_life_last_updated'] ?? 0);
    if ($lastUpdated <= 0) {
        return true;
    }

    return $currentGamets >= ($lastUpdated + chimBglPacingGametsForHours($baseHours));
}

function chimBglPacingNextDueGamets(array $npcRow, float $baseHours): int
{
    $extendedData = json_decode((string) ($npcRow['extended_data'] ?? '{}'), true) ?: [];
    $state = is_array($extendedData['background_life_pacing'] ?? null)
        ? $extendedData['background_life_pacing']
        : [];
    $nextCycle = (int) ($state['next_cycle_gamets'] ?? 0);
    if ($nextCycle > 0) {
        return $nextCycle;
    }

    $lastUpdated = (int) ($extendedData['background_life_last_updated'] ?? 0);
    return $lastUpdated > 0
        ? $lastUpdated + chimBglPacingGametsForHours($baseHours)
        : 0;
}

function chimBglPacingSortCandidates(array $npcRows, float $baseHours): array
{
    usort($npcRows, static function (array $left, array $right) use ($baseHours): int {
        $dueComparison = chimBglPacingNextDueGamets($left, $baseHours)
            <=> chimBglPacingNextDueGamets($right, $baseHours);
        if ($dueComparison !== 0) {
            return $dueComparison;
        }

        return strcasecmp((string) ($left['npc_name'] ?? ''), (string) ($right['npc_name'] ?? ''));
    });

    return $npcRows;
}

function chimBglPacingDefer(array $state, int $currentGamets, float $hours): array
{
    $state['version'] = 1;
    $state['next_cycle_gamets'] = $currentGamets + chimBglPacingGametsForHours($hours);
    $state['cadence_hours'] = max(1.0, $hours);
    return $state;
}

function chimBglPacingPromptBlock(array $state): string
{
    $phase = (string) ($state['phase'] ?? 'unstarted');
    $guidance = [
        'transition' => 'Complete or meaningfully advance the current journey before starting an unrelated thread.',
        'engagement' => 'Respond to the outcome of the interaction and avoid repeating the same conversation or exchange.',
        'resolution' => 'Let consequences settle and prefer a believable return to routine over immediate new drama.',
        'quiet' => 'A quiet routine is valid. Only begin a new objective when the context provides a concrete reason.',
        'unstarted' => 'Choose a grounded first beat based on established goals and current circumstances.',
    ][$phase] ?? 'Choose a grounded next beat that advances or settles the current thread.';

    $recent = [];
    foreach (array_slice((array) ($state['recent_actions'] ?? []), -4) as $entry) {
        if (is_array($entry) && trim((string) ($entry['action'] ?? '')) !== '') {
            $recent[] = trim((string) $entry['action']);
        }
    }
    $recentText = empty($recent) ? 'None recorded.' : implode(' -> ', $recent);

    return "<narrative_pacing>\n"
        . "Current phase: $phase\n"
        . "Recent background actions: $recentText\n"
        . "Guidance: $guidance\n"
        . "Do not repeat an identical action and target unless it is required to complete an unfinished journey.\n"
        . "</narrative_pacing>";
}
