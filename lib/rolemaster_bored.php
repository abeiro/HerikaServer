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

function chimRolemasterDefaultBoredEventRules(): string
{
    return <<<'PROMPT'
# Bored event rules
{SEED_ACTOR_RULE}
* Only use speakers from this nearby eligible actor list: {NEARBY_ACTORS}.
* Do not invent distant or off-scene actors.
* Do not target or comment on {PLAYER_NAME} merely because time passed or the player is idle.
* Prefer a natural NPC-to-NPC interaction or scene action. Involve the player only when recent player activity clearly requires a response.
* When an instruction targets another nearby actor, direct the dialogue to that actor.
PROMPT;
}

function chimRolemasterRenderBoredEventRules(
    string $template,
    string $seedActor,
    string $playerName,
    array $actorMap
): string {
    if (trim($template) === '') {
        $template = chimRolemasterDefaultBoredEventRules();
    }

    $seedActor = trim($seedActor);
    $seedActorRule = $seedActor === ''
        ? ''
        : "* The first instruction must use the selected initiating actor: {$seedActor}.";
    $nearbyActors = implode(', ', array_values($actorMap));

    $rendered = str_replace(
        ['{SEED_ACTOR_RULE}', '{SEED_ACTOR}', '{NEARBY_ACTORS}', '{PLAYER_NAME}'],
        [$seedActorRule, $seedActor, $nearbyActors, $playerName],
        $template
    );

    return trim((string)preg_replace("/\n{3,}/", "\n\n", $rendered));
}
