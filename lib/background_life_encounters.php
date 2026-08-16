<?php

require_once __DIR__ . '/background_life_requests.php';

const CHIM_BGL_COMBAT_MAX_PARTICIPANTS = 6;
const CHIM_BGL_COMBAT_NEARBY_HOURS = 6.0;
const CHIM_BGL_COMBAT_SNAPSHOT_HOURS = 6.0;

// Decode NPC metadata fields without letting malformed legacy JSON break a cycle.
function chimBglEncounterJsonArray($value): array
{
    if (is_array($value)) {
        return $value;
    }

    $decoded = json_decode((string)$value, true);
    return is_array($decoded) ? $decoded : [];
}

function chimBglEncounterGameHours(float $newer, float $older): float
{
    $factor = defined('GAMETS_TO_HOURS') ? (float)constant('GAMETS_TO_HOURS') : 0.0000024;
    return max(0.0, ($newer - $older) * $factor);
}

function chimBglEncounterNpcSettings(NpcMaster $npcMaster, array $npc): array
{
    $extended = $npcMaster->getExtendedData($npc);
    return [
        'enabled' => chimBglBoolean($extended['background_life_enabled'] ?? false),
        'participation' => chimBglBoolean($extended['background_life_combat_participation'] ?? false),
        'initiate' => chimBglBoolean($extended['background_life_combat_initiate'] ?? false),
        'lethal' => chimBglBoolean($extended['background_life_combat_lethal'] ?? false),
        'loot' => chimBglBoolean($extended['background_life_combat_loot'] ?? false),
    ];
}

function chimBglEncounterIsActiveForNpc($db, int $npcId): bool
{
    $row = $db->fetchOne(
        "SELECT 1
         FROM bgl_encounter_participants p
         JOIN bgl_encounters e ON e.id = p.encounter_id
         WHERE p.npc_id = {$npcId}
           AND e.state IN ('pending', 'resolving', 'applying', 'loot_pending')
         LIMIT 1"
    );
    return !empty($row);
}

// Read the newest nearby-actor telemetry and resolve it to stable NPC records.
function chimBglEncounterNearbyCandidates(array $currentNpcData, float $gameTs, NpcMaster $npcMaster, $db): array
{
    $metadata = $npcMaster->getMetadata($currentNpcData);
    $snapshots = $metadata['low_process_actors'] ?? [];
    if (!is_array($snapshots) || empty($snapshots)) {
        return [];
    }

    $snapshotTs = 0.0;
    $actorList = [];
    foreach ($snapshots as $candidateTs => $candidateActors) {
        if (is_numeric($candidateTs) && (float)$candidateTs >= $snapshotTs && is_array($candidateActors)) {
            $snapshotTs = (float)$candidateTs;
            $actorList = $candidateActors;
        }
    }
    if ($snapshotTs <= 0 || chimBglEncounterGameHours($gameTs, $snapshotTs) > CHIM_BGL_COMBAT_NEARBY_HOURS) {
        return [];
    }

    $currentId = (int)($currentNpcData['id'] ?? 0);
    $resolved = [];
    foreach ($actorList as $key => $actor) {
        $name = '';
        $refid = '';
        if (is_array($actor)) {
            $refid = (string)($actor['refid'] ?? $actor[0] ?? $key);
            $name = trim((string)($actor['name'] ?? $actor[1] ?? ''));
        } else {
            $name = trim((string)$actor);
            $refid = is_string($key) ? $key : '';
        }
        if ($name === '' || strcasecmp($name, 'The Narrator') === 0 || strcasecmp($name, (string)($GLOBALS['PLAYER_NAME'] ?? 'Player')) === 0) {
            continue;
        }

        $npc = chimBglResolveNpc($npcMaster, $refid, $name);
        if (!$npc || (int)($npc['id'] ?? 0) === $currentId || chimBglNormalizeRefId((string)($npc['refid'] ?? '')) === '') {
            continue;
        }
        $settings = chimBglEncounterNpcSettings($npcMaster, $npc);
        $candidateMetadata = $npcMaster->getMetadata($npc);
        if (!$settings['enabled'] || !$settings['participation'] || chimBglBoolean($candidateMetadata['stats']['is_dead'] ?? false)) {
            continue;
        }
        if (chimBglEncounterIsActiveForNpc($db, (int)$npc['id'])) {
            continue;
        }
        $npc['_bgl_combat_settings'] = $settings;
        $resolved[(int)$npc['id']] = $npc;
        if (count($resolved) >= CHIM_BGL_COMBAT_MAX_PARTICIPANTS - 1) {
            break;
        }
    }

    return array_values($resolved);
}

function chimBglEncounterSnapshotFresh(array $npc, NpcMaster $npcMaster, float $gameTs): bool
{
    $metadata = $npcMaster->getMetadata($npc);
    foreach (['last_inventory_update_gamets', 'last_equipment_update_gamets', 'last_stats_update_gamets'] as $key) {
        if (!isset($metadata[$key]) || !is_numeric($metadata[$key])) {
            return false;
        }
        if (chimBglEncounterGameHours($gameTs, (float)$metadata[$key]) > CHIM_BGL_COMBAT_SNAPSHOT_HOURS) {
            return false;
        }
    }
    return true;
}

function chimBglQueueCombatSnapshot($db, array $npc, int $delaySeconds = 0): void
{
    $refid = chimBglNormalizeRefId((string)($npc['refid'] ?? ''));
    if ($refid === '') {
        return;
    }
    $db->insert('responselog', [
        'localts' => time() + max(0, $delaySeconds),
        'sent' => 0,
        'actor' => 'rolemaster',
        'text' => '',
        'action' => "rolecommand|BackgroundCmd@{$refid}@UpdateCombatSnapshot",
        'tag' => '',
    ]);
}

function chimBglEncounterSnapshot(array $npc, NpcMaster $npcMaster): array
{
    $metadata = $npcMaster->getMetadata($npc);
    $extended = $npcMaster->getExtendedData($npc);
    return [
        'npc_id' => (int)($npc['id'] ?? 0),
        'name' => (string)($npc['npc_name'] ?? ''),
        'refid' => chimBglNormalizeRefId((string)($npc['refid'] ?? '')),
        'race' => (string)($npc['race'] ?? ''),
        'gender' => (string)($npc['gender'] ?? ''),
        'skills' => $metadata['skills'] ?? chimBglEncounterJsonArray($npc['skills'] ?? []),
        'stats' => $metadata['stats'] ?? [],
        'equipment' => $metadata['equipment'] ?? [],
        'inventory' => $metadata['inventory'] ?? [],
        'spells' => $metadata['spells'] ?? [],
        'relationships' => $extended['relationships'] ?? [],
        'last_coords' => $metadata['last_coords'] ?? [],
        'allows_lethal' => chimBglBoolean($extended['background_life_combat_lethal'] ?? false),
        'allows_loot' => chimBglBoolean($extended['background_life_combat_loot'] ?? false),
    ];
}

function chimBglEncounterParseJson(string $buffer): ?array
{
    $buffer = trim(preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($buffer)) ?? $buffer);
    $decoded = json_decode($buffer, true);
    if (is_array($decoded)) {
        return $decoded;
    }
    $start = strpos($buffer, '{');
    $end = strrpos($buffer, '}');
    if ($start === false || $end === false || $end <= $start) {
        return null;
    }
    $decoded = json_decode(substr($buffer, $start, $end - $start + 1), true);
    return is_array($decoded) ? $decoded : null;
}

function chimBglCombatActionPrompt(array $currentNpcData, float $gameTs, NpcMaster $npcMaster, $db): string
{
    $settings = chimBglEncounterNpcSettings($npcMaster, $currentNpcData);
    if (!$settings['enabled'] || !$settings['participation'] || !$settings['initiate'] || chimBglEncounterIsActiveForNpc($db, (int)$currentNpcData['id'])) {
        return '';
    }

    $candidates = chimBglEncounterNearbyCandidates($currentNpcData, $gameTs, $npcMaster, $db);
    if (empty($candidates)) {
        return '';
    }
    $lines = [];
    foreach ($candidates as $candidate) {
        $lines[] = '- ' . $candidate['npc_name'] . ':' . chimBglNormalizeRefId((string)$candidate['refid']);
    }
    return "\nAttackNPC:<target_name>:<target_refid>\n"
        . "- Begin a Background Life encounter with one listed nearby participant. A dedicated combat resolver may include eligible nearby allies (2-6 NPCs total).\n"
        . "- Use only when conflict is a natural consequence of the context; never target the player or narrator.\n"
        . "Eligible targets:\n" . implode("\n", $lines) . "\n";
}

function chimBglLootActionPrompt(array $currentNpcData, NpcMaster $npcMaster, $db): string
{
    $settings = chimBglEncounterNpcSettings($npcMaster, $currentNpcData);
    if (!$settings['loot']) {
        return '';
    }
    $npcId = (int)$currentNpcData['id'];
    $rows = $db->fetchAll(
        "SELECT e.id, e.narrative
         FROM bgl_encounters e
         JOIN bgl_encounter_participants p ON p.encounter_id = e.id
         WHERE p.npc_id = {$npcId}
           AND p.side = e.winning_side
           AND e.state = 'applied'
           AND e.loot_status = 'available'
         ORDER BY e.gamets DESC, e.id DESC
         LIMIT 3"
    );
    if (empty($rows)) {
        return '';
    }
    $lines = array_map(static fn($row) => '- ' . (int)$row['id'] . ': ' . trim((string)$row['narrative']), $rows);
    return "\nLootEncounter:<encounter_id>\n"
        . "- Take selected eligible items from defeated participants in one completed encounter.\n"
        . "- Only current, unequipped, non-quest inventory can be selected.\n"
        . "Eligible encounters:\n" . implode("\n", $lines) . "\n";
}

function chimBglResolveCombatWithLlm($connectionHandler, array $snapshots, string $initiatorRef, string $targetRef, string $reason): ?array
{
    $allowed = [];
    foreach ($snapshots as $snapshot) {
        $allowed[] = $snapshot['refid'];
    }
    $prompt = [[
        'role' => 'system',
        'content' => "Resolve a Skyrim NPC encounter as a story simulation. Equipment, inventory, statistics, skills, spells, relationships, and context are evidence only. Do not calculate a winner with a formula. Decide and describe the actual combat actions. Select 2-6 participants from the supplied eligible list, with exactly two sides named aggressor and defender. The initiator and target are mandatory. Return JSON only: {\"result\":\"victory|draw|interrupted\",\"winning_side\":\"aggressor|defender|none\",\"narrative\":\"...\",\"participants\":[{\"refid\":\"00000000\",\"side\":\"aggressor|defender\",\"outcome\":\"unhurt|minor_wound|serious_wound|incapacitated|surrendered|escaped|dead\"}]}."
    ], [
        'role' => 'user',
        'content' => "Initiator: {$initiatorRef}\nTarget: {$targetRef}\nEligible refs: " . implode(', ', $allowed)
            . "\nReason/context: {$reason}\nParticipant evidence:\n" . json_encode($snapshots, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]];
    $buffer = $connectionHandler->fast_request($prompt, ['MAX_TOKENS' => 1400], 'backgroundlife_combat');
    if (function_exists('updateLastLLMCall')) {
        updateLastLLMCall((string)($GLOBALS['HERIKA_NAME'] ?? ''));
    }
    return chimBglEncounterParseJson((string)$buffer);
}

function chimBglValidateCombatResolution(array $resolution, array $candidateSnapshots, string $initiatorRef, string $targetRef): ?array
{
    $allowedOutcomes = ['unhurt', 'minor_wound', 'serious_wound', 'incapacitated', 'surrendered', 'escaped', 'dead'];
    $allowedSides = ['aggressor', 'defender'];
    $byRef = [];
    foreach ($candidateSnapshots as $snapshot) {
        $byRef[$snapshot['refid']] = $snapshot;
    }

    $participants = [];
    foreach (($resolution['participants'] ?? []) as $participant) {
        if (!is_array($participant)) {
            return null;
        }
        $refid = chimBglNormalizeRefId((string)($participant['refid'] ?? ''));
        $side = strtolower(trim((string)($participant['side'] ?? '')));
        $outcome = strtolower(trim((string)($participant['outcome'] ?? '')));
        if (!isset($byRef[$refid]) || isset($participants[$refid]) || !in_array($side, $allowedSides, true) || !in_array($outcome, $allowedOutcomes, true)) {
            return null;
        }
        if ($refid === $initiatorRef && $side !== 'aggressor') {
            return null;
        }
        if ($refid === $targetRef && $side !== 'defender') {
            return null;
        }
        $stats = $byRef[$refid]['stats'] ?? [];
        if ($outcome === 'dead' && (!$byRef[$refid]['allows_lethal'] || chimBglBoolean($stats['is_essential'] ?? false) || chimBglBoolean($stats['is_protected'] ?? false))) {
            $outcome = 'incapacitated';
        }
        $participants[$refid] = [
            'snapshot' => $byRef[$refid],
            'side' => $side,
            'outcome' => $outcome,
        ];
    }
    if (count($participants) < 2 || count($participants) > CHIM_BGL_COMBAT_MAX_PARTICIPANTS || !isset($participants[$initiatorRef], $participants[$targetRef])) {
        return null;
    }
    if (count(array_unique(array_column($participants, 'side'))) !== 2) {
        return null;
    }

    $winningSide = strtolower(trim((string)($resolution['winning_side'] ?? 'none')));
    if (!in_array($winningSide, ['aggressor', 'defender', 'none'], true)) {
        return null;
    }
    return [
        'result' => in_array(($resolution['result'] ?? ''), ['victory', 'draw', 'interrupted'], true) ? $resolution['result'] : 'interrupted',
        'winning_side' => $winningSide,
        'narrative' => trim((string)($resolution['narrative'] ?? 'The encounter ended.')),
        'participants' => $participants,
    ];
}

// Validate and persist an LLM-resolved encounter before any game command is sent.
function chimBglHandleAttackNpcAction(string $actionArg, array $currentNpcData, string $reason, float $gameTs, int $eventTs, string $location, NpcMaster $npcMaster, $db, $connectionHandler): bool
{
    [$targetName, $targetRef] = array_pad(explode(':', $actionArg, 2), 2, '');
    $target = chimBglResolveNpc($npcMaster, $targetRef, $targetName);
    $initiatorSettings = chimBglEncounterNpcSettings($npcMaster, $currentNpcData);
    if (!$target || !$initiatorSettings['enabled'] || !$initiatorSettings['participation'] || !$initiatorSettings['initiate']) {
        return false;
    }

    $nearby = chimBglEncounterNearbyCandidates($currentNpcData, $gameTs, $npcMaster, $db);
    $candidates = [(int)$currentNpcData['id'] => $currentNpcData];
    foreach ($nearby as $candidate) {
        $candidates[(int)$candidate['id']] = $candidate;
    }
    if (!isset($candidates[(int)$target['id']]) || (int)$target['id'] === (int)$currentNpcData['id']) {
        return false;
    }

    $stale = [];
    foreach ($candidates as $candidate) {
        if (!chimBglEncounterSnapshotFresh($candidate, $npcMaster, $gameTs)) {
            $stale[] = $candidate;
        }
    }
    if (!empty($stale)) {
        foreach ($stale as $index => $npc) {
            chimBglQueueCombatSnapshot($db, $npc, $index);
        }
        error_log('[BGL COMBAT] Deferred encounter until fresh participant snapshots arrive');
        return false;
    }

    $snapshots = array_map(static fn($npc) => chimBglEncounterSnapshot($npc, $npcMaster), array_values($candidates));
    $initiatorRef = chimBglNormalizeRefId((string)$currentNpcData['refid']);
    $targetRef = chimBglNormalizeRefId((string)$target['refid']);
    $resolution = chimBglResolveCombatWithLlm($connectionHandler, $snapshots, $initiatorRef, $targetRef, $reason);
    $validated = is_array($resolution) ? chimBglValidateCombatResolution($resolution, $snapshots, $initiatorRef, $targetRef) : null;
    if (!$validated) {
        error_log('[BGL COMBAT] Dedicated resolver returned an invalid encounter');
        return false;
    }

    $participantIds = array_map(static fn($row) => (int)$row['snapshot']['npc_id'], $validated['participants']);
    sort($participantIds, SORT_NUMERIC);
    $encounterKey = substr(hash('sha256', implode(':', [$gameTs, $initiatorRef, $targetRef, microtime(true)])), 0, 32);
    $scene = chimBglEncounterSnapshot($currentNpcData, $npcMaster)['last_coords'];
    $db->execQuery('BEGIN');
    try {
        foreach ($participantIds as $npcId) {
            if ($db->execQuery("SELECT pg_advisory_xact_lock({$npcId})") === false) {
                throw new RuntimeException('Could not lock encounter participant');
            }
            if (chimBglEncounterIsActiveForNpc($db, $npcId)) {
                throw new RuntimeException('A participant entered another active encounter');
            }
        }
        $encounterId = $db->insertReturningId('bgl_encounters', [
            'encounter_key' => $encounterKey,
            'initiator_npc_id' => (int)$currentNpcData['id'],
            'initiator_refid' => $initiatorRef,
            'target_npc_id' => (int)$target['id'],
            'target_refid' => $targetRef,
            'gamets' => $gameTs,
            'ts' => $eventTs,
            'localts' => time(),
            'state' => 'applying',
            'result' => $validated['result'],
            'winning_side' => $validated['winning_side'],
            'reason' => $reason,
            'narrative' => $validated['narrative'],
            'location' => $location,
            'scene' => json_encode($scene, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'resolution' => json_encode($resolution, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'loot_status' => 'locked',
        ]);
        if ($encounterId <= 0) {
            throw new RuntimeException('Could not create encounter');
        }

        foreach ($validated['participants'] as $refid => $participant) {
            $snapshot = $participant['snapshot'];
            $corpseStatus = $participant['outcome'] === 'dead' ? 'pending_placement' : 'not_applicable';
            $db->insert('bgl_encounter_participants', [
                'encounter_id' => $encounterId,
                'npc_id' => $snapshot['npc_id'],
                'npc_name' => $snapshot['name'],
                'refid' => $refid,
                'side' => $participant['side'],
                'initial_snapshot' => json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'intended_outcome' => $participant['outcome'],
                'final_coords' => json_encode($scene, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'corpse_status' => $corpseStatus,
                'apply_attempts' => 1,
                'last_attempt_localts' => time(),
            ]);
            $db->insert('responselog', [
                'localts' => time(),
                'sent' => 0,
                'actor' => 'rolemaster',
                'text' => '',
                'action' => "rolecommand|BackgroundCmd@{$refid}@CombatOutcome/{$encounterKey}/{$participant['outcome']}/{$initiatorRef}",
                'tag' => '',
            ]);
        }
        $participantCount = $db->fetchOne("SELECT COUNT(*) AS total FROM bgl_encounter_participants WHERE encounter_id={$encounterId}");
        if ((int)($participantCount['total'] ?? 0) !== count($validated['participants'])) {
            throw new RuntimeException('Could not save all encounter participants');
        }
        $db->insert('actions_issued', [
            'action' => 'AttackNPC',
            'fullcall' => "AttackNPC:{$target['npc_name']}:{$targetRef}",
            'actorname' => $currentNpcData['npc_name'],
            'ts' => $eventTs,
            'gamets' => $gameTs,
            'localts' => time(),
            'original' => 'backgroundaction',
        ]);
        $db->execQuery('COMMIT');
        return true;
    } catch (Throwable $e) {
        $db->execQuery('ROLLBACK');
        error_log('[BGL COMBAT] Encounter creation failed: ' . $e->getMessage());
        return false;
    }
}

function chimBglEncounterPeople(array $participants): string
{
    $names = [];
    foreach ($participants as $participant) {
        $name = trim((string)($participant['npc_name'] ?? ''));
        if ($name !== '') {
            $names[$name] = true;
        }
    }
    return empty($names) ? '' : '|' . implode('|', array_keys($names)) . '|';
}

function chimBglFinalizeCombatEncounter($db, int $encounterId): void
{
    $encounter = $db->fetchOne("SELECT * FROM bgl_encounters WHERE id = {$encounterId} FOR UPDATE");
    if (!$encounter || $encounter['state'] !== 'applying') {
        return;
    }
    $participants = $db->fetchAll("SELECT * FROM bgl_encounter_participants WHERE encounter_id = {$encounterId} ORDER BY id");
    foreach ($participants as $participant) {
        if (!in_array($participant['application_status'], ['applied', 'failed'], true)) {
            return;
        }
    }
    $failed = array_filter($participants, static fn($row) => $row['application_status'] === 'failed');
    if (!empty($failed)) {
        $db->execQuery("UPDATE bgl_encounters SET state='failed', completed_localts=" . time() . " WHERE id={$encounterId}");
        return;
    }

    $hasLootTarget = false;
    $hasLooter = false;
    $hasDeath = false;
    foreach ($participants as $participant) {
        $outcome = $participant['applied_outcome'];
        $hasLootTarget = $hasLootTarget || in_array($outcome, ['dead', 'incapacitated', 'surrendered'], true);
        $snapshot = chimBglEncounterJsonArray($participant['initial_snapshot'] ?? []);
        $hasLooter = $hasLooter || ($participant['side'] === $encounter['winning_side'] && chimBglBoolean($snapshot['allows_loot'] ?? false));
        $hasDeath = $hasDeath || $outcome === 'dead';
    }
    $lootStatus = $hasLootTarget && $hasLooter && $encounter['winning_side'] !== 'none' ? 'available' : 'not_available';
    $db->execQuery("UPDATE bgl_encounters SET state='applied', loot_status='" . $db->escape($lootStatus) . "', completed_localts=" . time() . " WHERE id={$encounterId}");

    $people = chimBglEncounterPeople($participants);
    $type = $hasDeath ? 'death' : 'combatend';
    $db->insert('eventlog', [
        'ts' => (int)$encounter['ts'],
        'gamets' => (float)$encounter['gamets'],
        'type' => $type,
        'data' => 'The Narrator: ' . $encounter['narrative'],
        'sess' => 'pending',
        'localts' => time(),
        'people' => $people,
        'location' => (string)$encounter['location'],
        'party' => '',
    ]);
    foreach ($participants as $participant) {
        $db->insert('bgl_history', [
            'npc' => $participant['npc_name'],
            'ts' => (int)$encounter['ts'],
            'gamets' => (float)$encounter['gamets'],
            'localts' => time(),
            'data' => $encounter['narrative'] . ' Outcome: ' . $participant['applied_outcome'] . '.',
            'category' => 'combat',
        ]);
    }
}

function chimBglHandleCombatResultAck($db, string $payload): bool
{
    [$encounterKey, $refid, $status, $outcome] = array_pad(explode('@', $payload, 4), 4, '');
    $encounterKey = trim($encounterKey);
    $refid = chimBglNormalizeRefId($refid);
    $status = strtolower(trim($status));
    $outcome = strtolower(trim($outcome));
    if ($encounterKey === '' || $refid === '' || !in_array($status, ['applied', 'failed'], true)) {
        return false;
    }
    $encounter = $db->fetchOne("SELECT id FROM bgl_encounters WHERE encounter_key='" . $db->escape($encounterKey) . "' LIMIT 1");
    if (!$encounter) {
        return false;
    }
    $encounterId = (int)$encounter['id'];
    $corpseSql = $outcome === 'dead' && $status === 'applied' ? ", corpse_status='placed'" : '';
    $db->execQuery('BEGIN');
    try {
        $db->execQuery(
            "UPDATE bgl_encounter_participants
             SET application_status='" . $db->escape($status) . "', applied_outcome='" . $db->escape($outcome) . "', last_attempt_localts=" . time() . $corpseSql . "
             WHERE encounter_id={$encounterId} AND refid='" . $db->escape($refid) . "'"
        );
        chimBglFinalizeCombatEncounter($db, $encounterId);
        $db->execQuery('COMMIT');
        return true;
    } catch (Throwable $e) {
        $db->execQuery('ROLLBACK');
        error_log('[BGL COMBAT] Combat acknowledgement failed: ' . $e->getMessage());
        return false;
    }
}

function chimBglEligibleLootItems(array $snapshot): array
{
    $equipmentIds = [];
    foreach (($snapshot['equipment'] ?? []) as $key => $value) {
        if (str_ends_with((string)$key, '_baseid')) {
            $equipmentIds[chimBglNormalizeRefId((string)$value)] = true;
        }
    }
    $eligible = [];
    foreach (($snapshot['inventory'] ?? []) as $item) {
        if (!is_array($item) || !empty($item['equipped']) || !empty($item['is_quest_item']) || (int)($item['count'] ?? 0) <= 0) {
            continue;
        }
        $itemId = chimBglNormalizeRefId((string)($item['baseid'] ?? ''));
        $keywords = array_map('strtolower', is_array($item['keywords'] ?? null) ? $item['keywords'] : []);
        $isQuestKeyword = (bool)array_filter($keywords, static fn($keyword) => str_contains($keyword, 'quest'));
        if ($itemId === '' || isset($equipmentIds[$itemId]) || $isQuestKeyword) {
            continue;
        }
        $eligible[$itemId] = [
            'itemid' => $itemId,
            'name' => trim((string)($item['name'] ?? $itemId)),
            'count' => (int)$item['count'],
        ];
    }
    return $eligible;
}

function chimBglHandleLootEncounterAction(int $encounterId, array $currentNpcData, float $gameTs, int $eventTs, NpcMaster $npcMaster, $db, $connectionHandler): bool
{
    $npcId = (int)$currentNpcData['id'];
    $settings = chimBglEncounterNpcSettings($npcMaster, $currentNpcData);
    $encounter = $db->fetchOne(
        "SELECT e.* FROM bgl_encounters e
         JOIN bgl_encounter_participants p ON p.encounter_id=e.id
         WHERE e.id={$encounterId} AND e.state='applied' AND e.loot_status='available'
           AND p.npc_id={$npcId} AND p.side=e.winning_side LIMIT 1"
    );
    if (!$encounter || !$settings['loot']) {
        return false;
    }
    $participants = $db->fetchAll("SELECT * FROM bgl_encounter_participants WHERE encounter_id={$encounterId} ORDER BY id");
    $winners = [];
    $sources = [];
    foreach ($participants as $participant) {
        $npc = $npcMaster->getById((int)$participant['npc_id']);
        if (!$npc) {
            continue;
        }
        if ($participant['side'] === $encounter['winning_side']) {
            $winnerSettings = chimBglEncounterNpcSettings($npcMaster, $npc);
            if ($winnerSettings['loot']) {
                $winners[$participant['refid']] = ['participant' => $participant, 'npc' => $npc];
            }
        } elseif (in_array($participant['applied_outcome'], ['dead', 'incapacitated', 'surrendered'], true)) {
            if (!chimBglEncounterSnapshotFresh($npc, $npcMaster, $gameTs)) {
                chimBglQueueCombatSnapshot($db, $npc);
                return false;
            }
            $snapshot = chimBglEncounterSnapshot($npc, $npcMaster);
            $items = chimBglEligibleLootItems($snapshot);
            if (!empty($items)) {
                $sources[$participant['refid']] = ['participant' => $participant, 'npc' => $npc, 'items' => $items];
            }
        }
    }
    if (empty($winners) || empty($sources)) {
        $db->execQuery("UPDATE bgl_encounters SET loot_status='not_available' WHERE id={$encounterId}");
        return false;
    }

    $prompt = [[
        'role' => 'system',
        'content' => 'Select reasonable loot transfers after a resolved Skyrim encounter. Use only the supplied source, recipient, item, and maximum counts. Do not invent items. Return JSON only: {"narrative":"...","transfers":[{"source_refid":"00000000","recipient_refid":"00000000","itemid":"00000000","count":1}]}.',
    ], [
        'role' => 'user',
        'content' => 'Encounter: ' . $encounter['narrative'] . "\nEligible winning recipients: " . json_encode(array_keys($winners))
            . "\nEligible defeated inventories: " . json_encode(array_map(static fn($source) => $source['items'], $sources), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]];
    $result = chimBglEncounterParseJson((string)$connectionHandler->fast_request($prompt, ['MAX_TOKENS' => 900], 'backgroundlife_loot'));
    if (function_exists('updateLastLLMCall')) {
        updateLastLLMCall((string)($GLOBALS['HERIKA_NAME'] ?? ''));
    }
    if (!$result || !is_array($result['transfers'] ?? null)) {
        return false;
    }

    $reservations = [];
    $reservedCounts = [];
    foreach ($result['transfers'] as $transfer) {
        $sourceRef = chimBglNormalizeRefId((string)($transfer['source_refid'] ?? ''));
        $recipientRef = chimBglNormalizeRefId((string)($transfer['recipient_refid'] ?? ''));
        $itemId = chimBglNormalizeRefId((string)($transfer['itemid'] ?? ''));
        $count = (int)($transfer['count'] ?? 0);
        if (!isset($sources[$sourceRef], $winners[$recipientRef], $sources[$sourceRef]['items'][$itemId]) || $count <= 0) {
            return false;
        }
        $reservationKey = $sourceRef . ':' . $itemId;
        $reservedCounts[$reservationKey] = ($reservedCounts[$reservationKey] ?? 0) + $count;
        if ($reservedCounts[$reservationKey] > $sources[$sourceRef]['items'][$itemId]['count']) {
            return false;
        }
        $transferKey = $sourceRef . ':' . $recipientRef . ':' . $itemId;
        if (isset($reservations[$transferKey])) {
            $reservations[$transferKey]['count'] += $count;
        } else {
            $reservations[$transferKey] = compact('sourceRef', 'recipientRef', 'itemId', 'count');
        }
    }
    if (empty($reservations)) {
        return false;
    }

    $db->execQuery('BEGIN');
    try {
        $locked = $db->fetchOne("SELECT state,loot_status FROM bgl_encounters WHERE id={$encounterId} FOR UPDATE");
        if (!$locked || $locked['state'] !== 'applied' || $locked['loot_status'] !== 'available') {
            throw new RuntimeException('Encounter is no longer available for loot');
        }
        foreach ($reservations as $reservation) {
            $source = $sources[$reservation['sourceRef']];
            $winner = $winners[$reservation['recipientRef']];
            $item = $source['items'][$reservation['itemId']];
            $db->insert('bgl_encounter_loot', [
                'encounter_id' => $encounterId,
                'source_participant_id' => (int)$source['participant']['id'],
                'recipient_participant_id' => (int)$winner['participant']['id'],
                'itemid' => $reservation['itemId'],
                'item_name' => $item['name'],
                'requested_count' => $reservation['count'],
                'apply_attempts' => 1,
                'last_attempt_localts' => time(),
            ]);
            $db->insert('responselog', [
                'localts' => time(),
                'sent' => 0,
                'actor' => 'rolemaster',
                'text' => '',
                'action' => "rolecommand|BackgroundCmd@{$reservation['sourceRef']}@BackgroundLoot/{$encounter['encounter_key']}/{$reservation['recipientRef']}/{$reservation['itemId']}/{$reservation['count']}",
                'tag' => '',
            ]);
        }
        $lootCount = $db->fetchOne("SELECT COUNT(*) AS total FROM bgl_encounter_loot WHERE encounter_id={$encounterId}");
        if ((int)($lootCount['total'] ?? 0) !== count($reservations)) {
            throw new RuntimeException('Could not reserve all loot transfers');
        }
        $lootNarrative = trim((string)($result['narrative'] ?? 'The victors gathered useful belongings.'));
        $db->execQuery("UPDATE bgl_encounters SET state='loot_pending', loot_status='reserved', resolution=jsonb_set(resolution, '{loot_narrative}', to_jsonb('" . $db->escape($lootNarrative) . "'::text), true) WHERE id={$encounterId}");
        $db->insert('actions_issued', [
            'action' => 'LootEncounter',
            'fullcall' => "LootEncounter:{$encounterId}",
            'actorname' => $currentNpcData['npc_name'],
            'ts' => $eventTs,
            'gamets' => $gameTs,
            'localts' => time(),
            'original' => 'backgroundaction',
        ]);
        $db->execQuery('COMMIT');
        return true;
    } catch (Throwable $e) {
        $db->execQuery('ROLLBACK');
        error_log('[BGL LOOT] Reservation failed: ' . $e->getMessage());
        return false;
    }
}

function chimBglFinalizeLootEncounter($db, int $encounterId): void
{
    $encounter = $db->fetchOne("SELECT * FROM bgl_encounters WHERE id={$encounterId} FOR UPDATE");
    if (!$encounter || $encounter['state'] !== 'loot_pending') {
        return;
    }
    $rows = $db->fetchAll(
        "SELECT l.*, source.npc_name AS source_name, source.refid AS source_refid,
                recipient.npc_name AS recipient_name, recipient.refid AS recipient_refid
         FROM bgl_encounter_loot l
         JOIN bgl_encounter_participants source ON source.id=l.source_participant_id
         JOIN bgl_encounter_participants recipient ON recipient.id=l.recipient_participant_id
         WHERE l.encounter_id={$encounterId} ORDER BY l.id"
    );
    foreach ($rows as $row) {
        if (!in_array($row['status'], ['applied', 'failed'], true)) {
            return;
        }
    }
    $applied = array_filter($rows, static fn($row) => $row['status'] === 'applied' && (int)$row['applied_count'] > 0);
    $participants = $db->fetchAll("SELECT * FROM bgl_encounter_participants WHERE encounter_id={$encounterId} ORDER BY id");
    $resolution = chimBglEncounterJsonArray($encounter['resolution'] ?? []);
    $narrative = trim((string)($resolution['loot_narrative'] ?? 'The victors gathered useful belongings.'));
    $db->execQuery("UPDATE bgl_encounters SET state='complete', loot_status='complete', completed_localts=" . time() . " WHERE id={$encounterId}");
    if (!empty($applied)) {
        $db->insert('eventlog', [
            'ts' => (int)$encounter['ts'] + 1,
            'gamets' => (float)$encounter['gamets'] + 1,
            'type' => 'itemfound',
            'data' => 'The Narrator: ' . $narrative,
            'sess' => 'pending',
            'localts' => time(),
            'people' => chimBglEncounterPeople($participants),
            'location' => (string)$encounter['location'],
            'party' => '',
        ]);
        foreach ($participants as $participant) {
            $db->insert('bgl_history', [
                'npc' => $participant['npc_name'],
                'ts' => (int)$encounter['ts'] + 1,
                'gamets' => (float)$encounter['gamets'] + 1,
                'localts' => time(),
                'data' => $narrative,
                'category' => 'loot',
            ]);
        }
        $refs = [];
        foreach ($applied as $row) {
            $refs[$row['source_refid']] = true;
            $refs[$row['recipient_refid']] = true;
        }
        foreach (array_keys($refs) as $index => $refid) {
            $db->insert('responselog', [
                'localts' => time() + $index,
                'sent' => 0,
                'actor' => 'rolemaster',
                'text' => '',
                'action' => "rolecommand|BackgroundCmd@{$refid}@UpdateInventory",
                'tag' => '',
            ]);
        }
    }
}

function chimBglHandleLootResultAck($db, string $payload): bool
{
    [$encounterKey, $sourceRef, $recipientRef, $itemId, $status, $count] = array_pad(explode('@', $payload, 6), 6, '');
    $sourceRef = chimBglNormalizeRefId($sourceRef);
    $recipientRef = chimBglNormalizeRefId($recipientRef);
    $itemId = chimBglNormalizeRefId($itemId);
    $status = strtolower(trim($status));
    if ($encounterKey === '' || $sourceRef === '' || $recipientRef === '' || $itemId === '' || !in_array($status, ['applied', 'failed'], true)) {
        return false;
    }
    $encounter = $db->fetchOne("SELECT id FROM bgl_encounters WHERE encounter_key='" . $db->escape($encounterKey) . "' LIMIT 1");
    if (!$encounter) {
        return false;
    }
    $encounterId = (int)$encounter['id'];
    $db->execQuery('BEGIN');
    try {
        $db->execQuery(
            "UPDATE bgl_encounter_loot l SET status='" . $db->escape($status) . "', applied_count=" . max(0, (int)$count) . ", last_attempt_localts=" . time() . "
             FROM bgl_encounter_participants source, bgl_encounter_participants recipient
             WHERE l.source_participant_id=source.id AND l.recipient_participant_id=recipient.id
               AND l.encounter_id={$encounterId} AND source.refid='" . $db->escape($sourceRef) . "'
               AND recipient.refid='" . $db->escape($recipientRef) . "' AND l.itemid='" . $db->escape($itemId) . "'"
        );
        chimBglFinalizeLootEncounter($db, $encounterId);
        $db->execQuery('COMMIT');
        return true;
    } catch (Throwable $e) {
        $db->execQuery('ROLLBACK');
        error_log('[BGL LOOT] Loot acknowledgement failed: ' . $e->getMessage());
        return false;
    }
}

// Add applied encounter facts to future Background Life context for every participant.
function chimBglEncounterContextEvents($db, int $npcId, float $afterGameTs): array
{
    return $db->fetchAll(
        "SELECT e.gamets, e.narrative, p.applied_outcome, e.state
         FROM bgl_encounters e
         JOIN bgl_encounter_participants p ON p.encounter_id=e.id
         WHERE p.npc_id={$npcId} AND e.gamets > {$afterGameTs}
           AND e.state IN ('applied','loot_pending','complete')
         ORDER BY e.gamets, e.id LIMIT 20"
    );
}

// Requeue unacknowledged commands with the same idempotency keys after a short timeout.
function chimBglRetryPendingEncounterCommands($db, int $npcId): void
{
    $cutoff = time() - 30;
    $participants = $db->fetchAll(
        "SELECT e.encounter_key, e.initiator_refid, p.refid, p.intended_outcome, p.apply_attempts
         FROM bgl_encounters e JOIN bgl_encounter_participants p ON p.encounter_id=e.id
         WHERE e.id IN (SELECT encounter_id FROM bgl_encounter_participants WHERE npc_id={$npcId})
           AND e.state='applying' AND p.application_status='pending'
           AND (p.last_attempt_localts IS NULL OR p.last_attempt_localts < {$cutoff}) AND p.apply_attempts < 3"
    );
    foreach ($participants as $row) {
        $db->insert('responselog', [
            'localts' => time(), 'sent' => 0, 'actor' => 'rolemaster', 'text' => '',
            'action' => "rolecommand|BackgroundCmd@{$row['refid']}@CombatOutcome/{$row['encounter_key']}/{$row['intended_outcome']}/{$row['initiator_refid']}", 'tag' => '',
        ]);
        $db->execQuery("UPDATE bgl_encounter_participants SET apply_attempts=apply_attempts+1,last_attempt_localts=" . time() . " WHERE refid='" . $db->escape($row['refid']) . "' AND encounter_id=(SELECT id FROM bgl_encounters WHERE encounter_key='" . $db->escape($row['encounter_key']) . "')");
    }

    $lootRows = $db->fetchAll(
        "SELECT e.encounter_key, l.id, source.refid AS source_refid, recipient.refid AS recipient_refid,
                l.itemid, l.requested_count, l.apply_attempts
         FROM bgl_encounters e
         JOIN bgl_encounter_loot l ON l.encounter_id=e.id
         JOIN bgl_encounter_participants source ON source.id=l.source_participant_id
         JOIN bgl_encounter_participants recipient ON recipient.id=l.recipient_participant_id
         WHERE e.id IN (SELECT encounter_id FROM bgl_encounter_participants WHERE npc_id={$npcId})
           AND e.state='loot_pending' AND l.status='pending'
           AND (l.last_attempt_localts IS NULL OR l.last_attempt_localts < {$cutoff}) AND l.apply_attempts < 3"
    );
    foreach ($lootRows as $row) {
        $db->insert('responselog', [
            'localts' => time(), 'sent' => 0, 'actor' => 'rolemaster', 'text' => '',
            'action' => "rolecommand|BackgroundCmd@{$row['source_refid']}@BackgroundLoot/{$row['encounter_key']}/{$row['recipient_refid']}/{$row['itemid']}/{$row['requested_count']}", 'tag' => '',
        ]);
        $db->execQuery("UPDATE bgl_encounter_loot SET apply_attempts=apply_attempts+1,last_attempt_localts=" . time() . " WHERE id=" . (int)$row['id']);
    }

    $expiredEncounters = $db->fetchAll(
        "SELECT DISTINCT e.id FROM bgl_encounters e
         JOIN bgl_encounter_participants p ON p.encounter_id=e.id
         WHERE e.id IN (SELECT encounter_id FROM bgl_encounter_participants WHERE npc_id={$npcId})
           AND e.state='applying' AND p.application_status='pending'
           AND p.apply_attempts >= 3 AND p.last_attempt_localts < {$cutoff}"
    );
    foreach ($expiredEncounters as $encounter) {
        $encounterId = (int)$encounter['id'];
        $db->execQuery('BEGIN');
        $db->execQuery("UPDATE bgl_encounter_participants SET application_status='failed' WHERE encounter_id={$encounterId} AND application_status='pending' AND apply_attempts >= 3 AND last_attempt_localts < {$cutoff}");
        chimBglFinalizeCombatEncounter($db, $encounterId);
        $db->execQuery('COMMIT');
    }

    $expiredLootEncounters = $db->fetchAll(
        "SELECT DISTINCT e.id FROM bgl_encounters e
         JOIN bgl_encounter_loot l ON l.encounter_id=e.id
         WHERE e.id IN (SELECT encounter_id FROM bgl_encounter_participants WHERE npc_id={$npcId})
           AND e.state='loot_pending' AND l.status='pending'
           AND l.apply_attempts >= 3 AND l.last_attempt_localts < {$cutoff}"
    );
    foreach ($expiredLootEncounters as $encounter) {
        $encounterId = (int)$encounter['id'];
        $db->execQuery('BEGIN');
        $db->execQuery("UPDATE bgl_encounter_loot SET status='failed' WHERE encounter_id={$encounterId} AND status='pending' AND apply_attempts >= 3 AND last_attempt_localts < {$cutoff}");
        chimBglFinalizeLootEncounter($db, $encounterId);
        $db->execQuery('COMMIT');
    }
}
