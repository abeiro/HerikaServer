<?php

function chimBglExtractActionDestination(array $action): string
{
    // The original command retains the requested name; fullcall may contain a wire-level FormID.
    foreach (['original', 'fullcall'] as $field) {
        $value = trim((string)($action[$field] ?? ''));
        if ($value === '') {
            continue;
        }

        if (preg_match('/^(?:TravelTo(?:Raw)?|MoveTo)\s*:\s*(.+)$/i', $value, $matches)) {
            return trim($matches[1]);
        }

        if (preg_match('/(?:^|\|)(?:TravelTo(?:Raw)?|MoveTo)@([^|\r\n]+)/i', $value, $matches)) {
            return trim($matches[1]);
        }
    }

    return '';
}

function chimBglResolveActionDestination(array $action, $db): string
{
    $destination = chimBglExtractActionDestination($action);
    if ($destination === '' || !ctype_digit($destination)) {
        return $destination;
    }

    $location = $db->fetchOne(
        'SELECT name FROM locations WHERE formid=' . (int)$destination . ' LIMIT 1'
    );

    return trim((string)($location['name'] ?? $destination));
}
