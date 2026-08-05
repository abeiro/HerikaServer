<?php

/**
 * Reduce a Background Life action to the fields that define its behavior,
 * excluding generated reasoning and other volatile text.
 */
function normalizeBackgroundActionSignature($action): string
{
    if (!is_scalar($action)) {
        return '';
    }

    $action = trim((string) $action);
    if ($action === '') {
        return '';
    }

    $parts = array_map('trim', explode(':', $action));
    $command = strtolower((string) ($parts[0] ?? ''));
    if (!in_array($command, ['stayatplace', 'travelto', 'moveto', 'findnpc', 'speakto'], true)) {
        return '';
    }

    if ($command === 'stayatplace') {
        return $command . ':' . strtolower((string) ($parts[2] ?? ''));
    }

    return $command . ':' . strtolower((string) ($parts[1] ?? ''));
}

/**
 * Block a third identical decision within the current in-game day.
 */
function backgroundActionRepeatLimitReached($action, array $recentActionRows, int $allowedMatches = 2): bool
{
    $candidate = normalizeBackgroundActionSignature($action);
    if ($candidate === '') {
        return false;
    }

    $matches = 0;
    foreach ($recentActionRows as $row) {
        if (!is_array($row)) {
            continue;
        }

        $recordedAction = trim((string) ($row['fullcall'] ?? ''));
        if ($recordedAction === '') {
            $recordedAction = trim((string) ($row['action'] ?? ''));
        }
        if (normalizeBackgroundActionSignature($recordedAction) !== $candidate) {
            continue;
        }

        $matches++;
        if ($matches >= $allowedMatches) {
            return true;
        }
    }

    return false;
}

/**
 * Validate and canonicalize an idle inventory action returned by the LLM.
 */
function normalizeBackgroundIdleInventoryAction($action): ?string
{
    if (!is_scalar($action)) {
        return null;
    }

    $action = trim((string) $action);
    if ($action === '' || strcasecmp($action, 'DoNothing') === 0) {
        return null;
    }

    $parts = array_map('trim', explode(':', $action, 3));
    $actionType = $parts[0] ?? '';
    $itemId = strtr(strtolower((string) ($parts[1] ?? '')), ['0x' => '']);
    $count = (string) ($parts[2] ?? '');
    if (count($parts) !== 3
        || !in_array($actionType, ['Consume', 'Produced'], true)
        || !preg_match('/^[0-9a-f]{1,8}$/', $itemId)
        || !ctype_digit($count)
        || (int) $count < 1) {
        return null;
    }

    return "$actionType:$itemId:$count";
}
