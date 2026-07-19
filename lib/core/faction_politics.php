<?php

function chimFactionPoliticsText($value): string
{
    return trim((string)$value);
}

function chimFactionPoliticsKey(array $faction): string
{
    $stableKey = chimFactionPoliticsText($faction['stable_key'] ?? '');
    if ($stableKey !== '' && strpos($stableKey, '|') !== false) {
        [$stablePlugin, $stableLocalFormId] = explode('|', $stableKey, 2);
        $stableLocalFormId = preg_replace('/[^0-9a-f]/i', '', $stableLocalFormId);
        if (trim($stablePlugin) !== '' && $stableLocalFormId !== '') {
            return strtoupper(trim($stablePlugin)) . '|'
                . strtoupper(str_pad($stableLocalFormId, 8, '0', STR_PAD_LEFT));
        }
    }

    $plugin = chimFactionPoliticsText($faction['plugin'] ?? '');
    $localFormId = preg_replace('/^0x/i', '', chimFactionPoliticsText($faction['local_formid'] ?? ''));
    if ($plugin !== '' && $localFormId !== '' && ctype_xdigit($localFormId)) {
        return strtoupper($plugin) . '|' . strtoupper(str_pad($localFormId, 8, '0', STR_PAD_LEFT));
    }

    $formId = preg_replace('/^0x/i', '', chimFactionPoliticsText($faction['formid'] ?? ''));
    if ($formId !== '' && ctype_xdigit($formId)) {
        return 'FORMID|' . strtoupper(str_pad($formId, 8, '0', STR_PAD_LEFT));
    }

    $name = strtolower(chimFactionPoliticsText($faction['name'] ?? ''));
    $name = preg_replace('/[^a-z0-9]+/', '-', $name);
    return $name === '' ? '' : 'NAME|' . trim($name, '-');
}

function chimFactionPoliticsName(array $faction, string $fallback = ''): string
{
    $name = chimFactionPoliticsText($faction['name'] ?? '');
    return $name !== '' ? $name : chimFactionPoliticsText($fallback);
}

function chimFactionPoliticsCanonicalPair(string $keyA, string $nameA, string $keyB, string $nameB): array
{
    $left = [chimFactionPoliticsText($keyA), chimFactionPoliticsText($nameA)];
    $right = [chimFactionPoliticsText($keyB), chimFactionPoliticsText($nameB)];
    if (strcmp($left[0], $right[0]) > 0) {
        [$left, $right] = [$right, $left];
    }
    return [$left[0], $left[1], $right[0], $right[1]];
}

function chimFactionPoliticsClamp($value): int
{
    return max(-100, min(100, (int)$value));
}

function chimFactionPoliticsEnum($value, array $allowed, string $fallback): string
{
    $value = strtolower(chimFactionPoliticsText($value));
    return in_array($value, $allowed, true) ? $value : $fallback;
}

function chimFactionPoliticsDecodeExtendedData($value): array
{
    if (is_array($value)) {
        return $value;
    }
    $decoded = json_decode((string)$value, true);
    return is_array($decoded) ? $decoded : [];
}

function chimFactionPoliticsMembershipsFromNpcRows(array $npcRows): array
{
    $memberships = [];
    foreach ($npcRows as $npcRow) {
        $extendedData = chimFactionPoliticsDecodeExtendedData($npcRow['extended_data'] ?? []);
        $factions = isset($extendedData['factions']) && is_array($extendedData['factions'])
            ? $extendedData['factions']
            : [];
        foreach ($factions as $faction) {
            if (!is_array($faction) || (isset($faction['rank']) && (int)$faction['rank'] < 0)) {
                continue;
            }
            $key = chimFactionPoliticsKey($faction);
            if ($key === '') {
                continue;
            }
            $memberships[$key] = chimFactionPoliticsName($faction, $key);
        }
    }
    ksort($memberships, SORT_NATURAL | SORT_FLAG_CASE);
    return $memberships;
}

function chimFactionPoliticsSceneNames($currentNpcData, $cachePeople): array
{
    $names = [];
    if (is_array($currentNpcData)) {
        $currentName = chimFactionPoliticsText($currentNpcData['npc_name'] ?? '');
        if ($currentName !== '') {
            $names[strtolower($currentName)] = $currentName;
        }
    }
    foreach (explode('|', (string)$cachePeople) as $name) {
        $name = chimFactionPoliticsText($name);
        if ($name !== '') {
            $names[strtolower($name)] = $name;
        }
    }
    return array_values($names);
}

function chimFactionPoliticsXml($value): string
{
    return htmlspecialchars(chimFactionPoliticsText($value), ENT_QUOTES | ENT_XML1, 'UTF-8');
}

function chimFactionPoliticsBuildContext(
    array $memberships,
    array $states,
    array $relations,
    array $developments,
    int $developmentLimit = 5
): string {
    if (empty($memberships)) {
        return '';
    }

    $relevantKeys = array_fill_keys(array_keys($memberships), true);
    $lines = [];
    foreach ($states as $state) {
        $key = chimFactionPoliticsText($state['faction_key'] ?? '');
        if (!isset($relevantKeys[$key])) {
            continue;
        }
        $line = '- ' . chimFactionPoliticsXml($state['faction_name'] ?? $memberships[$key]);
        $line .= ': ' . chimFactionPoliticsXml($state['status'] ?? 'stable');
        $line .= ', influence ' . chimFactionPoliticsClamp($state['influence'] ?? 0);
        if (chimFactionPoliticsText($state['agenda'] ?? '') !== '') {
            $line .= '. Agenda: ' . chimFactionPoliticsXml($state['agenda']);
        }
        if (chimFactionPoliticsText($state['summary'] ?? '') !== '') {
            $line .= '. ' . chimFactionPoliticsXml($state['summary']);
        }
        $lines[] = $line;
    }

    foreach ($relations as $relation) {
        $keyA = chimFactionPoliticsText($relation['faction_a_key'] ?? '');
        $keyB = chimFactionPoliticsText($relation['faction_b_key'] ?? '');
        if (!isset($relevantKeys[$keyA]) && !isset($relevantKeys[$keyB])) {
            continue;
        }
        $line = '- ' . chimFactionPoliticsXml($relation['faction_a_name'] ?? $keyA);
        $line .= ' and ' . chimFactionPoliticsXml($relation['faction_b_name'] ?? $keyB);
        $line .= ': ' . chimFactionPoliticsXml($relation['stance'] ?? 'neutral');
        $line .= ' (' . chimFactionPoliticsClamp($relation['score'] ?? 0) . ')';
        if (chimFactionPoliticsText($relation['summary'] ?? '') !== '') {
            $line .= '. ' . chimFactionPoliticsXml($relation['summary']);
        }
        $lines[] = $line;
    }

    $developmentLimit = max(0, min(10, $developmentLimit));
    $developmentCount = 0;
    foreach ($developments as $development) {
        if ($developmentCount >= $developmentLimit) {
            break;
        }
        $keys = $development['faction_keys'] ?? [];
        if (is_string($keys)) {
            $keys = json_decode($keys, true);
        }
        if (!is_array($keys) || empty(array_intersect(array_keys($relevantKeys), $keys))) {
            continue;
        }
        $line = '- Development: ' . chimFactionPoliticsXml($development['title'] ?? 'Political development');
        if (chimFactionPoliticsText($development['summary'] ?? '') !== '') {
            $line .= '. ' . chimFactionPoliticsXml($development['summary']);
        }
        $lines[] = $line;
        $developmentCount++;
    }

    if (empty($lines)) {
        return '';
    }

    return "\n<faction_politics>\n# CURRENT FACTION POLITICS\n"
        . implode("\n", $lines)
        . "\nUse this as current world state. Do not invent political changes that are not supported by events.\n</faction_politics>";
}

function chimFactionPoliticsTablesExist($db): bool
{
    try {
        $row = $db->fetchOne("SELECT to_regclass('public.core_faction_politics_state') AS state_table");
        return !empty($row['state_table']);
    } catch (Throwable $e) {
        return false;
    }
}

function chimFactionPoliticsBuildSceneContext($db, $currentNpcData, $cachePeople, int $developmentLimit = 5): string
{
    if (!chimFactionPoliticsTablesExist($db)) {
        return '';
    }

    $names = chimFactionPoliticsSceneNames($currentNpcData, $cachePeople);
    if (empty($names)) {
        return '';
    }

    $escapedNames = array_map(static fn($name) => "lower('" . $db->escape($name) . "')", $names);
    try {
        $npcRows = $db->fetchAll(
            'SELECT npc_name, extended_data FROM core_npc_master WHERE lower(npc_name) IN ('
            . implode(',', $escapedNames) . ')'
        );
        if (is_array($currentNpcData)) {
            $npcRows[] = $currentNpcData;
        }
        $memberships = chimFactionPoliticsMembershipsFromNpcRows($npcRows);
        if (empty($memberships)) {
            return '';
        }

        $states = $db->fetchAll('SELECT * FROM core_faction_politics_state ORDER BY faction_name');
        $relations = $db->fetchAll('SELECT * FROM core_faction_politics_relation ORDER BY faction_a_name, faction_b_name');
        $developments = $db->fetchAll(
            "SELECT * FROM core_faction_politics_development WHERE status = 'active' ORDER BY gamets DESC, id DESC LIMIT 50"
        );
        return chimFactionPoliticsBuildContext(
            $memberships,
            is_array($states) ? $states : [],
            is_array($relations) ? $relations : [],
            is_array($developments) ? $developments : [],
            $developmentLimit
        );
    } catch (Throwable $e) {
        return '';
    }
}

function chimFactionPoliticsDetectedCatalog($db): array
{
    $catalog = [];
    try {
        $rows = $db->fetchAll('SELECT extended_data FROM core_npc_master WHERE extended_data IS NOT NULL');
        foreach (chimFactionPoliticsMembershipsFromNpcRows(is_array($rows) ? $rows : []) as $key => $name) {
            $catalog[$key] = $name;
        }
        $factions = $db->fetchAll("SELECT formid, name FROM factions WHERE name IS NOT NULL AND btrim(name) <> ''");
        foreach (is_array($factions) ? $factions : [] as $faction) {
            $key = chimFactionPoliticsKey($faction);
            if ($key !== '' && !isset($catalog[$key])) {
                $catalog[$key] = chimFactionPoliticsName($faction, $key);
            }
        }
    } catch (Throwable $e) {
        return $catalog;
    }
    natcasesort($catalog);
    return $catalog;
}
