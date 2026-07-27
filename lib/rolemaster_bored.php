<?php

function chimRolemasterBoredActorKey(string $actorName): string
{
    return function_exists('mb_strtolower')
        ? mb_strtolower($actorName, 'UTF-8')
        : strtolower($actorName);
}

function chimRolemasterBoredActorMap(string $actorsInRange, string $playerName = '', string $seedActor = ''): array
{
    $actors = [];
    foreach (preg_split('/[|\/]/', $actorsInRange) ?: [] as $actor) {
        $actor = trim($actor);
        if ($actor === '' || ($playerName !== '' && strcasecmp($actor, $playerName) === 0)) {
            continue;
        }
        $actors[chimRolemasterBoredActorKey($actor)] = $actor;
    }

    $seedActor = trim($seedActor);
    if ($seedActor !== '' && ($playerName === '' || strcasecmp($seedActor, $playerName) !== 0)) {
        $actors[chimRolemasterBoredActorKey($seedActor)] = $seedActor;
    }

    return $actors;
}

function chimRolemasterBoredCanonicalActor(string $actorName, array $actorMap): ?string
{
    $actorName = trim($actorName);
    if ($actorName === '') {
        return null;
    }

    return $actorMap[chimRolemasterBoredActorKey($actorName)] ?? null;
}

function chimRolemasterFilterBoredInstructions(array $instructions, array $actorMap, string $seedActor): array
{
    $valid = [];
    $seedInstruction = null;

    foreach ($instructions as $instruction) {
        if (!is_array($instruction)) {
            continue;
        }

        $canonicalActor = chimRolemasterBoredCanonicalActor((string)($instruction['character'] ?? ''), $actorMap);
        if ($canonicalActor === null) {
            continue;
        }

        $instruction['character'] = $canonicalActor;
        if ($seedActor !== '' && strcasecmp($canonicalActor, $seedActor) === 0) {
            $seedInstruction = $instruction;
        } else {
            $valid[] = $instruction;
        }
    }

    if ($seedActor !== '' && $seedInstruction === null) {
        return [];
    }

    if ($seedInstruction !== null) {
        array_unshift($valid, $seedInstruction);
    }

    return $valid;
}

function chimRolemasterBoredListenerRequirement(string $target, array $actorMap): string
{
    $canonicalTarget = chimRolemasterBoredCanonicalActor($target, $actorMap);
    if ($canonicalTarget === null) {
        return '';
    }

    return " The dialogue listener must be {$canonicalTarget}.";
}
