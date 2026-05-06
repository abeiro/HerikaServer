<?php

function chimTransformationStateNowMs(): int
{
    return (int) round(microtime(true) * 1000);
}

function chimNormalizeTransformationStateType(?string $state): string
{
    $normalized = strtolower(trim((string) $state));
    $normalized = strtr($normalized, [' ' => '_', '-' => '_']);

    if ($normalized === 'vampire' || $normalized === 'vampire_lord_form' || $normalized === 'vampire_beast') {
        return 'vampire_lord';
    }
    if ($normalized === 'werewolf_form' || $normalized === 'beast_form') {
        return 'werewolf';
    }

    return in_array($normalized, ['normal', 'werewolf', 'vampire_lord'], true) ? $normalized : '';
}

function chimInferTransformationStateType(array $state): string
{
    if (!empty($state['is_werewolf_form'])) {
        return 'werewolf';
    }
    if (!empty($state['is_vampire_lord_form'])) {
        return 'vampire_lord';
    }

    return 'normal';
}

function chimSanitizeTransformationStatePayload(array $payload): array
{
    $state = [
        'state' => chimNormalizeTransformationStateType($payload['state'] ?? ''),
        'is_werewolf_form' => !empty($payload['is_werewolf_form']),
        'is_vampire_lord_form' => !empty($payload['is_vampire_lord_form']),
        'race_name' => trim((string) ($payload['race_name'] ?? '')),
        'race_editor_id' => trim((string) ($payload['race_editor_id'] ?? '')),
        'timestamp' => isset($payload['timestamp']) && is_numeric($payload['timestamp'])
            ? (int) $payload['timestamp']
            : chimTransformationStateNowMs(),
        'gamets' => isset($payload['gamets']) && is_numeric($payload['gamets'])
            ? (int) $payload['gamets']
            : 0,
    ];

    if ($state['state'] === '') {
        $state['state'] = chimInferTransformationStateType($state);
    }

    if ($state['state'] === 'werewolf') {
        $state['is_werewolf_form'] = true;
        $state['is_vampire_lord_form'] = false;
    } elseif ($state['state'] === 'vampire_lord') {
        $state['is_werewolf_form'] = false;
        $state['is_vampire_lord_form'] = true;
    } else {
        $state['state'] = 'normal';
        $state['is_werewolf_form'] = false;
        $state['is_vampire_lord_form'] = false;
    }

    return $state;
}

function chimBuildTransformationStateMetadataUpdates(array $payload): array
{
    $state = chimSanitizeTransformationStatePayload($payload);

    $setValues = [
        'transformation_state' => $state,
        'transformation_state_type' => $state['state'],
        'is_werewolf_form' => $state['is_werewolf_form'],
        'is_vampire_lord_form' => $state['is_vampire_lord_form'],
        'transformation_state_timestamp' => $state['timestamp'],
    ];
    $unsetKeys = [];

    if ($state['race_name'] === '') {
        $unsetKeys[] = 'transformation_race_name';
    } else {
        $setValues['transformation_race_name'] = $state['race_name'];
    }

    if ($state['race_editor_id'] === '') {
        $unsetKeys[] = 'transformation_race_editor_id';
    } else {
        $setValues['transformation_race_editor_id'] = $state['race_editor_id'];
    }

    return [
        'set' => $setValues,
        'unset' => $unsetKeys,
    ];
}

function chimNormalizeTransformationState(array $metadata): array
{
    $rawState = $metadata['transformation_state'] ?? [];
    if (!is_array($rawState)) {
        $rawState = [];
    }

    if (!empty($metadata['transformation_state_type']) && empty($rawState['state'])) {
        $rawState['state'] = $metadata['transformation_state_type'];
    }
    if (array_key_exists('is_werewolf_form', $metadata) && !array_key_exists('is_werewolf_form', $rawState)) {
        $rawState['is_werewolf_form'] = $metadata['is_werewolf_form'];
    }
    if (array_key_exists('is_vampire_lord_form', $metadata) && !array_key_exists('is_vampire_lord_form', $rawState)) {
        $rawState['is_vampire_lord_form'] = $metadata['is_vampire_lord_form'];
    }
    if (!empty($metadata['transformation_state_timestamp']) && empty($rawState['timestamp'])) {
        $rawState['timestamp'] = $metadata['transformation_state_timestamp'];
    }
    if (!empty($metadata['transformation_race_name']) && empty($rawState['race_name'])) {
        $rawState['race_name'] = $metadata['transformation_race_name'];
    }
    if (!empty($metadata['transformation_race_editor_id']) && empty($rawState['race_editor_id'])) {
        $rawState['race_editor_id'] = $metadata['transformation_race_editor_id'];
    }

    return chimSanitizeTransformationStatePayload($rawState);
}

function chimBuildTransformationStateConditionLines(array $metadata): array
{
    $enabled = true;
    if (function_exists('chimGetGeneralSettingBool')) {
        $enabled = chimGetGeneralSettingBool('TRANSFORMATION_DETECTION', true);
    } elseif (array_key_exists('TRANSFORMATION_DETECTION', $GLOBALS)) {
        $rawValue = strtolower(trim(strval($GLOBALS['TRANSFORMATION_DETECTION'])));
        $enabled = in_array($rawValue, ['1', 'true', 'yes', 'on'], true);
    }

    if (!$enabled) {
        return [];
    }

    $state = chimNormalizeTransformationState($metadata);
    if (($state['state'] ?? 'normal') === 'werewolf') {
        return ["  • Form: Currently transformed into a werewolf"];
    }
    if (($state['state'] ?? 'normal') === 'vampire_lord') {
        return ["  • Form: Currently transformed into a vampire lord"];
    }

    return [];
}
