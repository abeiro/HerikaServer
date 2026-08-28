<?php

// Character data may be shared; identity and live actor state never are.
const CHIM_SHARED_NPC_FIELDS = [
    'prompt_head', 'npc_static_bio', 'oghma_knowledge_tags', 'emote_moods', 'personality',
    'relationships', 'occupation', 'skills', 'speechstyle', 'goals', 'voiceid',
    'profile_id', 'dynamic_profile', 'core', 'tags',
];
const CHIM_SHARED_NPC_EXTENDED = [
    'middle_term_memory', 'middle_term_enabled', 'individual_memory_enabled',
    'auto_diary_enabled', 'auto_diary_wait_enabled', 'salutation_after_a_while',
    'relationships', 'relationships_locked', 'relationships_analyzed', 'relationships_inferred',
    'relationships_last_eval', 'relationships_model', 'relationships_updated',
    'voice_refresh_requested_at', 'voice_refresh_last_result', 'voice_refresh_last_resolved_at',
    'background_life_last_updated', 'background_life_last_updated_presence_delta',
];

// Administrative state survives imports, save rollback and load-order renumbering.
const CHIM_NPC_PROFILE_METADATA_KEYS = [
    '_chim_profile_epoch', '_chim_auto_link_group', '_chim_auto_link_disabled',
];

// Skyrim.esm ACHR (placed reference) IDs verified against the game records, not NPC_ base IDs.
// Exclude flashbacks, summoned copies, the Emperor's decoy and DA05SindingGhost (Hircine).
const CHIM_NPC_ALTERNATE_REFERENCES = [
    'astrid' => ['0001BDE8', '0004D6D1'], // AstridRef, AstridEndRef
    'cicero' => ['000550F1', '0001E64A', '0009BCB0'], // Road, sanctuary, Dawnstar
    'erik' => ['000350B8', '000656E2'], // ErikRef, HirelingEriktheSlayerref
    'ulfric' => ['0001B131', '000BE32B', '00053C66', '000EA584'], // Normal, battle, Sovngarde
    'sinding' => ['0002ABBD', '0006C1B8'], // DA05SindingREF, DA05SindingHumanREF
    'kodlak' => ['0001A68F', '000AD3A6', '000DCCC1', '00058300', '00098BD4'], // Living, dead, ghost, Sovngarde
];

// Names can differ (Erik the Slayer, translations); only exact originating references establish equivalence.
function chimNpcAlternateGroup(array $row): ?string
{
    $source = chimParseNpcReferenceSource(chimNpcProfileJson($row['metadata'] ?? null)['refid_source'] ?? '');
    if (!$source || strcasecmp($source['plugin_name'], 'Skyrim.esm') !== 0) { return null; }
    foreach (CHIM_NPC_ALTERNATE_REFERENCES as $group => $references) {
        if (in_array($source['local_formid'], $references, true)) { return $group; }
    }
    return null;
}

// Registration-only, database-only linking. Existing groups keep their owner; otherwise the oldest row wins.
function chimNpcAutoLinkProfile(array $actor): bool
{
    $group = chimNpcAlternateGroup($actor);
    if ($group === null || (int)$actor['id'] <= 1) { return false; }
    $db = $GLOBALS['db'];
    $sources = implode(',', array_map(
        static fn($ref) => "'skyrim.esm|" . strtolower($ref) . "'", CHIM_NPC_ALTERNATE_REFERENCES[$group]
    ));
    $query = "SELECT * FROM core_npc_master WHERE lower(metadata->>'refid_source') IN ({$sources}) ORDER BY id";
    // Repeated registrations need no write lock, snapshots or epoch change once the group is settled.
    $rows = $db->fetchAll($query);
    $owners = array_unique(array_map(static fn($row) => (int)($row['profile_owner_npc_id'] ?? $row['id']), $rows));
    foreach ($rows as $row) {
        if (!empty(chimNpcProfileJson($row['metadata'])['_chim_auto_link_disabled'])) { return false; }
    }
    if (count($rows) < 2 || count($owners) === 1) { return false; }
    if ($db->execQuery('BEGIN') === false) { throw new RuntimeException('Cannot begin automatic profile link'); }
    try {
        if ($db->execQuery('LOCK TABLE game_plugins, core_npc_master IN SHARE ROW EXCLUSIVE MODE') === false) {
            throw new RuntimeException('Cannot lock automatic profile link');
        }
        $rows = $db->fetchAll($query);
        $ids = array_map(static fn($row) => (int)$row['id'], $rows);
        $existingOwners = [];
        $seen = [];
        $eligible = count($rows) >= 2 && in_array((int)$actor['id'], $ids, true);
        foreach ($rows as $row) {
            $metadata = chimNpcProfileJson($row['metadata']);
            $source = chimParseNpcReferenceSource($metadata['refid_source'] ?? '');
            $refid = NpcMaster::normalizeRefId($row['refid'] ?? '');
            if ((int)$row['id'] <= 1 || !empty($metadata['_chim_auto_link_disabled']) || !$source ||
                isset($seen[strtolower($source['stable_key'])]) || $refid === '' || str_starts_with($refid, 'FF') ||
                !chimStableFormReferenceEquals($source['stable_key'], chimConvertRuntimeFormIdToStableReference($refid))) {
                $eligible = false;
                break;
            }
            $seen[strtolower($source['stable_key'])] = true;
            if (!empty($row['profile_owner_npc_id'])) { $existingOwners[(int)$row['profile_owner_npc_id']] = true; }
            foreach (chimNpcProfileMembers($row) as $member) {
                if (!in_array((int)$member['id'], $ids, true)) { $eligible = false; }
            }
        }
        // Never join conflicting manually kept profiles or pull an unrelated actor into an automatic group.
        if (!$eligible || count($existingOwners) > 1) { $db->execQuery('ROLLBACK'); return false; }
        $ownerId = $existingOwners ? (int)array_key_first($existingOwners) : $ids[0];
        $changed = array_filter($rows, static fn($row) =>
            (int)($row['profile_owner_npc_id'] ?? $row['id']) !== $ownerId);
        if (!$changed) { $db->execQuery('ROLLBACK'); return false; }
        $manager = new NpcMaster();
        foreach ($rows as $row) {
            if ($manager->backupNpcById($row['id']) === false) { throw new RuntimeException('Cannot preserve original profiles'); }
        }
        $epoch = bin2hex(random_bytes(16));
        $idList = implode(',', $ids);
        if ($db->execQuery("UPDATE core_npc_master SET
            profile_owner_npc_id = CASE WHEN id = {$ownerId} THEN NULL ELSE {$ownerId} END,
            metadata = COALESCE(metadata, '{}'::jsonb) || jsonb_build_object(
                '_chim_profile_epoch', '{$epoch}', '_chim_auto_link_group', '{$group}')
            WHERE id IN ({$idList})") === false || $db->execQuery('COMMIT') === false) {
            throw new RuntimeException('Cannot automatically link profiles');
        }
        return true;
    } catch (Throwable $error) {
        $db->execQuery('ROLLBACK');
        throw $error;
    }
}

function chimNpcProfileJson($value): array
{
    if (is_array($value)) { return $value; }
    $decoded = json_decode((string)$value, true);
    return is_array($decoded) ? $decoded : [];
}

// The epoch changes on merge/unlink, invalidating work started under the old ownership.
function chimNpcProfileBinding(array $row): string
{
    $metadata = chimNpcProfileJson($row['metadata'] ?? null);
    return (string)($row['profile_owner_npc_id'] ?? '') . ':' . ($metadata['_chim_profile_epoch'] ?? '');
}

// Overlay only character fields, retaining the requesting actor's id, hash and physical state.
function chimNpcEffectiveProfile($actor)
{
    if (!is_array($actor) || !$actor) { return $actor; }
    $actor['_profile_binding'] = chimNpcProfileBinding($actor);
    $ownerId = (int)($actor['profile_owner_npc_id'] ?? 0);
    if (!$ownerId) { return $actor; }
    $owner = $GLOBALS['db']->fetchOne("SELECT * FROM core_npc_master WHERE id = {$ownerId}");
    if (!$owner || !empty($owner['profile_owner_npc_id']) ||
        (strcasecmp(trim($owner['npc_name']), trim($actor['npc_name'])) !== 0 &&
            (chimNpcAlternateGroup($actor) === null || chimNpcAlternateGroup($actor) !== chimNpcAlternateGroup($owner)))) {
        throw new RuntimeException('Invalid shared NPC profile; unlink it before continuing');
    }
    foreach (CHIM_SHARED_NPC_FIELDS as $field) { $actor[$field] = $owner[$field] ?? null; }
    $extended = chimNpcProfileJson($actor['extended_data'] ?? null);
    $ownerExtended = chimNpcProfileJson($owner['extended_data'] ?? null);
    foreach (CHIM_SHARED_NPC_EXTENDED as $key) {
        unset($extended[$key]);
        if (array_key_exists($key, $ownerExtended)) { $extended[$key] = $ownerExtended[$key]; }
    }
    $actor['extended_data'] = json_encode($extended, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return $actor;
}

// Fingerprint actual persisted data and the active playthrough table, not the identity MD5.
function chimNpcProfileRevision(array $rows): string
{
    usort($rows, static fn($a, $b) => (int)$a['id'] <=> (int)$b['id']);
    $scope = $GLOBALS['db']->fetchOne("SELECT 'core_npc_master'::regclass::oid AS oid");
    foreach ($rows as &$row) {
        foreach (array_keys($row) as $key) {
            if (str_starts_with($key, '_')) { unset($row[$key]); }
        }
        ksort($row);
    }
    unset($row);
    return hash('sha256', json_encode([$scope['oid'], $rows], JSON_THROW_ON_ERROR));
}

function chimNpcProfileMembers(array $actor): array
{
    $ownerId = (int)($actor['profile_owner_npc_id'] ?? $actor['id']);
    return $GLOBALS['db']->fetchAll(
        "SELECT * FROM core_npc_master WHERE id = {$ownerId} OR profile_owner_npc_id = {$ownerId} ORDER BY id"
    );
}

// Read name-scoped summaries across a verified linked name change, without absorbing unrelated namesakes.
function chimNpcProfileMemoryNames(array $actor): array
{
    $names = [$actor['npc_name']];
    $group = chimNpcAlternateGroup($actor);
    if ($group === null) { return $names; }
    $ownerId = (int)($actor['profile_owner_npc_id'] ?? $actor['id']);
    foreach (chimNpcProfileMembers($actor) as $member) {
        $name = $member['npc_name'];
        if (in_array($name, $names, true) || strpbrk($name, '%_|') !== false ||
            chimNpcAlternateGroup($member) !== $group) { continue; }
        $escaped = $GLOBALS['db']->escape($name);
        $unrelated = $GLOBALS['db']->fetchOne("SELECT id FROM core_npc_master
            WHERE lower(btrim(npc_name)) = lower(btrim('{$escaped}'))
            AND COALESCE(profile_owner_npc_id, id) <> {$ownerId} LIMIT 1");
        if (!$unrelated) { $names[] = $name; }
    }
    return $names;
}

function chimNpcProfileIdentity(array $row): array
{
    return [
        'id' => (int)$row['id'], 'name' => $row['npc_name'], 'refid' => $row['refid'] ?? '',
        'refid_source' => chimNpcProfileJson($row['metadata'] ?? null)['refid_source'] ?? '',
        'profile_owner_npc_id' => isset($row['profile_owner_npc_id']) ? (int)$row['profile_owner_npc_id'] : null,
    ];
}

function chimNpcProfileSharing(array $row): array
{
    $members = chimNpcProfileMembers($row);
    $metadata = chimNpcProfileJson($row['metadata'] ?? null);
    return [
        'linked' => count($members) > 1,
        'automatic' => count($members) > 1 && !empty($metadata['_chim_auto_link_group']),
        'auto_link_disabled' => !empty($metadata['_chim_auto_link_disabled']),
        'owner_id' => (int)($row['profile_owner_npc_id'] ?? $row['id']),
        'members' => array_map('chimNpcProfileIdentity', $members),
    ];
}

// Both IDs must be explicit, available plugin references in this playthrough and not already shared.
function chimNpcProfileMergePair(int $first, int $second): array
{
    if ($first <= 1 || $second <= 1 || $first === $second) {
        throw new InvalidArgumentException('Choose two different NPC profiles');
    }
    $rows = $GLOBALS['db']->fetchAll("SELECT * FROM core_npc_master WHERE id IN ({$first}, {$second}) ORDER BY id");
    if (count($rows) !== 2 || strcasecmp(trim($rows[0]['npc_name']), trim($rows[1]['npc_name'])) !== 0) {
        throw new InvalidArgumentException('Choose two profiles with the same name');
    }
    foreach ($rows as $row) {
        $source = chimParseNpcReferenceSource(chimNpcProfileJson($row['metadata'] ?? null)['refid_source'] ?? '');
        $refid = NpcMaster::normalizeRefId($row['refid'] ?? '');
        if (!$source || $refid === '' || str_starts_with($refid, 'FF') ||
            !chimStableFormReferenceEquals($source['stable_key'], chimConvertRuntimeFormIdToStableReference($refid))) {
            throw new InvalidArgumentException('Only currently available plugin-defined references can be merged');
        }
        if (count(chimNpcProfileMembers($row)) !== 1 || !empty($row['profile_owner_npc_id'])) {
            throw new InvalidArgumentException('Unlink existing shared profiles before merging again');
        }
    }
    return $rows;
}

// Database-only administrative operation: validate the preview again, snapshot, then bind atomically.
function chimNpcMergeProfiles(int $ownerId, int $otherId, string $revision): void
{
    $db = $GLOBALS['db'];
    if ($db->execQuery('BEGIN') === false) { throw new RuntimeException('Cannot begin profile merge'); }
    try {
        // Same order as manifest remapping; registration and profile writes cannot interleave.
        if ($db->execQuery('LOCK TABLE game_plugins, core_npc_master IN SHARE ROW EXCLUSIVE MODE') === false) {
            throw new RuntimeException('Cannot lock profiles');
        }
        $rows = chimNpcProfileMergePair($ownerId, $otherId);
        if (!hash_equals(chimNpcProfileRevision($rows), $revision)) {
            throw new UnexpectedValueException('Profiles changed. Review the merge again.');
        }
        $manager = new NpcMaster();
        foreach ($rows as $row) {
            if ($manager->backupNpcById($row['id']) === false) { throw new RuntimeException('Cannot preserve original profiles'); }
        }
        $epoch = bin2hex(random_bytes(16));
        if ($db->execQuery("UPDATE core_npc_master SET
            profile_owner_npc_id = CASE WHEN id = {$otherId} THEN {$ownerId} ELSE NULL END,
            metadata = jsonb_set(CASE WHEN jsonb_typeof(metadata) = 'object' THEN metadata ELSE '{}'::jsonb END,
                '{_chim_profile_epoch}', '\"{$epoch}\"'::jsonb)
            WHERE id IN ({$ownerId}, {$otherId})") === false || $db->execQuery('COMMIT') === false) {
            throw new RuntimeException('Cannot merge profiles');
        }
    } catch (Throwable $error) {
        $db->execQuery('ROLLBACK');
        throw $error;
    }
}

// Unlink leaves the owner's current shared data and the other row's original character data intact.
function chimNpcUnlinkProfiles(int $id, string $revision): void
{
    $db = $GLOBALS['db'];
    if ($db->execQuery('BEGIN') === false) { throw new RuntimeException('Cannot begin unlink'); }
    try {
        if ($db->execQuery('LOCK TABLE core_npc_master IN SHARE ROW EXCLUSIVE MODE') === false) {
            throw new RuntimeException('Cannot lock profiles');
        }
        $actor = (new NpcMaster())->getActorById($id);
        if (!$actor) { throw new InvalidArgumentException('NPC profile no longer exists'); }
        $members = chimNpcProfileMembers($actor);
        if (count($members) < 2) { throw new InvalidArgumentException('This profile is not shared'); }
        if (!hash_equals(chimNpcProfileRevision($members), $revision)) {
            throw new UnexpectedValueException('Profiles changed. Review the unlink again.');
        }
        $ids = implode(',', array_map(static fn($row) => (int)$row['id'], $members));
        $epoch = bin2hex(random_bytes(16));
        // Opt out the whole known group, including alternate versions not encountered yet.
        $disableIds = implode(',', array_map(static fn($row) => (int)$row['id'],
            array_filter($members, static fn($row) => chimNpcAlternateGroup($row) !== null))) ?: '0';
        if ($db->execQuery("UPDATE core_npc_master SET profile_owner_npc_id = NULL,
            metadata = jsonb_set(COALESCE(metadata, '{}'::jsonb) - '_chim_auto_link_group',
                '{_chim_profile_epoch}', '\"{$epoch}\"'::jsonb)
                || CASE WHEN id IN ({$disableIds}) THEN '{\"_chim_auto_link_disabled\":true}'::jsonb ELSE '{}'::jsonb END
            WHERE id IN ({$ids})") === false || $db->execQuery('COMMIT') === false) {
            throw new RuntimeException('Cannot unlink profiles');
        }
    } catch (Throwable $error) {
        $db->execQuery('ROLLBACK');
        throw $error;
    }
}

// Route a shared write without ever copying the owner's identity or overwriting the dormant profile.
function chimNpcWriteSharedProfile(NpcMaster $manager, int $id, array $data): bool
{
    $db = $GLOBALS['db'];
    if ($db->execQuery('BEGIN') === false) { return false; }
    try {
        // Only active links take this path. Lock the pair in ID order to avoid opposite-actor deadlocks.
        $rows = $db->fetchAll("SELECT * FROM core_npc_master WHERE id IN (
            SELECT id FROM core_npc_master WHERE id = {$id}
            UNION SELECT profile_owner_npc_id FROM core_npc_master WHERE id = {$id}
        ) ORDER BY id FOR UPDATE");
        $actors = array_column($rows, null, 'id');
        $actor = $actors[$id] ?? null;
        if (!$actor || ($data['_profile_binding'] ?? '') !== chimNpcProfileBinding($actor)) {
            throw new UnexpectedValueException('Profile sharing changed; reload before saving');
        }
        $ownerId = (int)($actor['profile_owner_npc_id'] ?? $id);
        if (isset($data['npc_name']) && $data['npc_name'] !== $actor['npc_name'] && count(chimNpcProfileMembers($actor)) > 1) {
            throw new InvalidArgumentException('Unlink shared profiles before renaming');
        }
        if ($ownerId === $id) {
            $saved = $manager->updateActor($id, $data);
        } else {
            $owner = $actors[$ownerId] ?? null;
            if (!$owner || !empty($owner['profile_owner_npc_id'])) { throw new RuntimeException('Invalid profile owner'); }
            if (isset($data['npc_name']) && $data['npc_name'] !== $actor['npc_name']) {
                throw new InvalidArgumentException('Unlink shared profiles before renaming');
            }
            $shared = array_intersect_key($data, array_flip(CHIM_SHARED_NPC_FIELDS));
            $physical = array_diff_key($data, array_flip(CHIM_SHARED_NPC_FIELDS));
            if (array_key_exists('extended_data', $data)) {
                $incoming = chimNpcProfileJson($data['extended_data']);
                $submitted = $incoming;
                $ownerExtended = chimNpcProfileJson($owner['extended_data'] ?? null);
                $actorExtended = chimNpcProfileJson($actor['extended_data'] ?? null);
                foreach (CHIM_SHARED_NPC_EXTENDED as $key) {
                    unset($ownerExtended[$key], $incoming[$key]);
                    if (array_key_exists($key, $submitted)) { $ownerExtended[$key] = $submitted[$key]; }
                }
                // Retain the other profile's original shared keys; only its physical keys change.
                $physical['extended_data'] = json_encode(array_replace(
                    $incoming, array_intersect_key($actorExtended, array_flip(CHIM_SHARED_NPC_EXTENDED))
                ));
                $shared['extended_data'] = json_encode($ownerExtended);
            }
            if (isset($data['gamets_last_updated'])) { $shared['gamets_last_updated'] = $data['gamets_last_updated']; }
            $saved = (!$shared || $manager->updateActor($ownerId, $shared) !== false)
                && $manager->updateActor($id, $physical) !== false;
        }
        if (!$saved || $db->execQuery('COMMIT') === false) { throw new RuntimeException('Shared profile update failed'); }
        return true;
    } catch (Throwable $error) {
        $db->execQuery('ROLLBACK');
        error_log('[NPC PROFILE] ' . $error->getMessage());
        return false;
    }
}

// Save rollback must preserve administrative links, including when neither actor has an older snapshot.
function chimNpcRestoreSharedProfiles(NpcMaster $manager, $timestamp, bool $preserveRelationships): void
{
    $db = $GLOBALS['db'];
    if ($db->execQuery('BEGIN') === false) { throw new RuntimeException('Cannot restore shared profiles'); }
    try {
        if ($db->execQuery('LOCK TABLE core_npc_master IN SHARE ROW EXCLUSIVE MODE') === false) {
            throw new RuntimeException('Cannot lock shared profile restore');
        }
        $rows = $db->fetchAll("SELECT * FROM core_npc_master c WHERE profile_owner_npc_id IS NOT NULL
            OR metadata->>'_chim_auto_link_disabled' = 'true'
            OR EXISTS (SELECT 1 FROM core_npc_master child WHERE child.profile_owner_npc_id = c.id)");
        foreach ($rows as $row) {
            $id = (int)$row['id'];
            if (empty($row['lock_profile']) && ($row['gamets_last_updated'] ?? 0) > 0) {
                $history = $db->fetchOne("SELECT * FROM core_npc_master_history WHERE npc_id = {$id}
                    AND (gamets_last_updated <= {$timestamp} OR gamets_last_updated IS NULL)
                    ORDER BY gamets_last_updated DESC NULLS LAST,
                    CASE WHEN extended_data->>'_chim_history_source' = 'infosave' THEN 1 ELSE 0 END DESC,
                    created DESC, history_id DESC LIMIT 1");
                $restored = $history ?: $row;
                $extended = chimNpcProfileJson($restored['extended_data'] ?? null);
                unset($extended['_chim_history_source']);
                if (!$history) {
                    // Preserve the character and link, but never carry a future personal summary backwards.
                    unset($extended['middle_term_memory']);
                }
                if ($preserveRelationships) {
                    $currentExtended = chimNpcProfileJson($row['extended_data'] ?? null);
                    foreach (CHIM_SHARED_NPC_EXTENDED as $key) {
                        if (str_starts_with($key, 'relationships')) {
                            unset($extended[$key]);
                            if (array_key_exists($key, $currentExtended)) { $extended[$key] = $currentExtended[$key]; }
                        }
                    }
                } elseif (!$history) {
                    foreach (array_keys($extended) as $key) {
                        if (str_starts_with($key, 'relationships')) { unset($extended[$key]); }
                    }
                }
                $restored['extended_data'] = json_encode($extended);
                // Structural identity and current physical reference are always taken from the live row.
                $restored['npc_name'] = $row['npc_name'];
                $restored['gamets_last_updated'] = $timestamp;
                if ($manager->updateActor($id, $restored) === false) { throw new RuntimeException('Shared profile restore failed'); }
            }
            $current = $manager->getActorById($id);
            $extended = chimNpcProfileJson($current['extended_data'] ?? null);
            if (is_array($extended['middle_term_memory'] ?? null)) {
                $extended['middle_term_memory'] = array_filter($extended['middle_term_memory'],
                    static fn($key) => is_numeric($key) && (float)$key <= (float)$timestamp, ARRAY_FILTER_USE_KEY);
                if ($manager->updateActor($id, ['extended_data' => json_encode($extended)]) === false) {
                    throw new RuntimeException('Cannot remove future personal memory');
                }
            }
            $epoch = bin2hex(random_bytes(16));
            if ($db->execQuery("UPDATE core_npc_master SET metadata = jsonb_set(COALESCE(metadata, '{}'::jsonb),
                '{_chim_profile_epoch}', '\"{$epoch}\"'::jsonb) WHERE id = {$id}") === false) {
                throw new RuntimeException('Cannot invalidate old profile work');
            }
        }
        if ($db->execQuery('COMMIT') === false) { throw new RuntimeException('Cannot commit shared restore'); }
    } catch (Throwable $error) {
        $db->execQuery('ROLLBACK');
        throw $error;
    }
}
