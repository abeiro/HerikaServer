<?php

function herikaGetNpcDefaultActionCodes()
{
    return [
        'Inspect',
        'InspectSurroundings',
        'MoveTo',
        'OpenInventory',
        'OpenInventory2',
        'Attack',
        'AttackHunt',
        'TravelTo',
        'Follow',
        'CheckInventory',
        'Relax',
        'TakeASeat',
        'IncreaseWalkSpeed',
        'DecreaseWalkSpeed',
        'WaitHere',
        'ComeCloser',
        'TakeGoldFromPlayer',
        'RentRoom',
        'HireCarriage',
        'HireFerry',
        'AddBounty',
        'PayBounty',
        'ArrestPlayer',
        'ForgiveCrime',
        'FollowPlayer',
        'Brawl',
        'GiveGoldTo',
        'GiveItemTo',
        'PickupItem',
        'GoToSleep',
        'UseSoulGaze',
        'CastSpell',
        'MakeFollower',
        'Drink',
        'Toast',
        'StartRitualCeremony',
        'EndRitualCeremony',
        'Training',
        'EndConversation',
    ];
}

function herikaGetFollowerDefaultActionCodes()
{
    return [
        'Inspect',
        'InspectSurroundings',
        'OpenInventory',
        'OpenInventory2',
        'Attack',
        'AttackHunt',
        'TravelTo',
        'Follow',
        'CheckInventory',
        'SheatheWeapon',
        'Relax',
        'TakeASeat',
        'ReadQuestJournal',
        'IncreaseWalkSpeed',
        'DecreaseWalkSpeed',
        'WaitHere',
        'ComeCloser',
        'TakeGoldFromPlayer',
        'RentRoom',
        'HireCarriage',
        'HireFerry',
        'AddBounty',
        'PayBounty',
        'ArrestPlayer',
        'ForgiveCrime',
        'Brawl',
        'GiveGoldTo',
        'GiveItemTo',
        'PickupItem',
        'GoToSleep',
        'UseSoulGaze',
        'CastSpell',
        'Drink',
        'Toast',
        'Training',
        'StartRitualCeremony',
        'EndRitualCeremony',
    ];
}

function herikaActionCatalogSqlBool($value)
{
    return $value ? 'TRUE' : 'FALSE';
}

function herikaActionCatalogSqlText($value)
{
    $text = strval($value);
    if ($text === '') {
        return "''";
    }

    return $GLOBALS["db"]->escapeLiteral($text);
}

function herikaActionCatalogToBool($value)
{
    if (is_bool($value)) {
        return $value;
    }

    $text = strtolower(trim(strval($value)));
    return in_array($text, ['1', 'true', 't', 'yes', 'on'], true);
}

function herikaActionCatalogResetCache()
{
    unset($GLOBALS["HERIKA_ACTION_CATALOG_DB_READY"]);
    unset($GLOBALS["HERIKA_ACTION_CATALOG_ROWS_BY_CODE"]);
}

function herikaActionCatalogDbReady()
{
    if (isset($GLOBALS["HERIKA_ACTION_CATALOG_DB_READY"])) {
        return $GLOBALS["HERIKA_ACTION_CATALOG_DB_READY"];
    }

    if (($GLOBALS["DBDRIVER"] ?? '') !== 'postgresql') {
        $GLOBALS["HERIKA_ACTION_CATALOG_DB_READY"] = false;
        return false;
    }

    if (!isset($GLOBALS["db"]) || !($GLOBALS["db"] instanceof sql)) {
        $GLOBALS["HERIKA_ACTION_CATALOG_DB_READY"] = false;
        return false;
    }

    $coreAction = $GLOBALS["db"]->fetchOne("
        SELECT 1 AS exists
        FROM information_schema.tables
        WHERE table_schema = 'public' AND table_name = 'core_action'
    ");
    $coreActionCustom = $GLOBALS["db"]->fetchOne("
        SELECT 1 AS exists
        FROM information_schema.tables
        WHERE table_schema = 'public' AND table_name = 'core_action_custom'
    ");
    $combinedView = $GLOBALS["db"]->fetchOne("
        SELECT 1 AS exists
        FROM information_schema.views
        WHERE table_schema = 'public' AND table_name = 'combined_core_action'
    ");

    $ready = isset($coreAction["exists"]) && isset($coreActionCustom["exists"]) && isset($combinedView["exists"]);
    $GLOBALS["HERIKA_ACTION_CATALOG_DB_READY"] = $ready;
    return $ready;
}

function herikaBuildActionCatalogSeedRows($actionNames, $descriptions, $returnMessages, $currentEnabledCodes = [], $defaultEnabledCodes = [])
{
    $npcDefaults = herikaGetNpcDefaultActionCodes();
    $followerDefaults = herikaGetFollowerDefaultActionCodes();
    $activationDefaults = count($defaultEnabledCodes) > 0 ? $defaultEnabledCodes : array_unique(array_merge($npcDefaults, $followerDefaults));
    $allCodeNames = array_unique(array_merge(
        array_keys(is_array($actionNames) ? $actionNames : []),
        array_keys(is_array($descriptions) ? $descriptions : []),
        array_keys(is_array($returnMessages) ? $returnMessages : []),
        is_array($currentEnabledCodes) ? $currentEnabledCodes : [],
        $activationDefaults,
        $npcDefaults,
        $followerDefaults
    ));

    natcasesort($allCodeNames);

    $rows = [];
    foreach ($allCodeNames as $codeName) {
        $codeName = trim(strval($codeName));
        if ($codeName === '') {
            continue;
        }

        $availableToNpc = in_array($codeName, $npcDefaults, true);
        $availableToFollowers = in_array($codeName, $followerDefaults, true);
        $isActivated = in_array($codeName, $activationDefaults, true) || in_array($codeName, $currentEnabledCodes, true);

        $rows[$codeName] = [
            'code_name' => $codeName,
            'action_name' => isset($actionNames[$codeName]) && trim(strval($actionNames[$codeName])) !== '' ? strval($actionNames[$codeName]) : $codeName,
            'description' => isset($descriptions[$codeName]) ? strval($descriptions[$codeName]) : '',
            'return_message' => isset($returnMessages[$codeName]) ? strval($returnMessages[$codeName]) : '',
            'available_to_npc' => $availableToNpc,
            'available_to_followers' => $availableToFollowers,
            'is_activated' => $isActivated,
        ];
    }

    return $rows;
}

function herikaSyncActionCatalogBaseRows($rowsByCode)
{
    if (!herikaActionCatalogDbReady()) {
        return;
    }

    foreach ($rowsByCode as $row) {
        if (!is_array($row) || empty($row['code_name'])) {
            continue;
        }

        $GLOBALS["db"]->execQuery("
            INSERT INTO public.core_action (
                code_name,
                action_name,
                description,
                return_message,
                available_to_npc,
                available_to_followers,
                is_activated
            ) VALUES (
                " . herikaActionCatalogSqlText($row['code_name']) . ",
                " . herikaActionCatalogSqlText($row['action_name'] ?? '') . ",
                " . herikaActionCatalogSqlText($row['description'] ?? '') . ",
                " . herikaActionCatalogSqlText($row['return_message'] ?? '') . ",
                " . herikaActionCatalogSqlBool(!empty($row['available_to_npc'])) . ",
                " . herikaActionCatalogSqlBool(!empty($row['available_to_followers'])) . ",
                " . herikaActionCatalogSqlBool(!empty($row['is_activated'])) . "
            )
            ON CONFLICT (code_name) DO UPDATE SET
                action_name = EXCLUDED.action_name,
                description = EXCLUDED.description,
                return_message = EXCLUDED.return_message,
                available_to_npc = EXCLUDED.available_to_npc,
                available_to_followers = EXCLUDED.available_to_followers,
                is_activated = EXCLUDED.is_activated,
                updated_at = NOW()
        ");
    }

    herikaActionCatalogResetCache();
}

function herikaMarkLegacyActionPreferencesImported()
{
    if (!isset($GLOBALS["db"]) || !($GLOBALS["db"] instanceof sql)) {
        return;
    }

    $GLOBALS["db"]->execQuery("
        INSERT INTO public.conf_opts (id, value)
        VALUES ('core_action_legacy_user_pref_imported', '1')
        ON CONFLICT (id) DO UPDATE SET value = EXCLUDED.value
    ");
}

function herikaLegacyActionPreferencesImported()
{
    if (!isset($GLOBALS["db"]) || !($GLOBALS["db"] instanceof sql)) {
        return false;
    }

    $row = $GLOBALS["db"]->fetchOne("
        SELECT value
        FROM public.conf_opts
        WHERE id = 'core_action_legacy_user_pref_imported'
        LIMIT 1
    ");

    return isset($row['value']) && trim(strval($row['value'])) === '1';
}

function herikaImportLegacyActionPreferences($rowsByCode)
{
    if (!herikaActionCatalogDbReady() || herikaLegacyActionPreferencesImported()) {
        return;
    }

    $userPrefPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'functions' . DIRECTORY_SEPARATOR . 'user_pref.json';
    if (!file_exists($userPrefPath)) {
        herikaMarkLegacyActionPreferencesImported();
        return;
    }

    $selectedCodes = json_decode(file_get_contents($userPrefPath), true);
    if (!is_array($selectedCodes) || count($selectedCodes) === 0) {
        herikaMarkLegacyActionPreferencesImported();
        return;
    }

    $selectedMap = array_fill_keys(array_map('strval', $selectedCodes), true);
    foreach ($rowsByCode as $row) {
        if (!is_array($row) || empty($row['code_name'])) {
            continue;
        }

        $GLOBALS["db"]->execQuery("
            INSERT INTO public.core_action_custom (
                code_name,
                action_name,
                description,
                return_message,
                available_to_npc,
                available_to_followers,
                is_activated
            ) VALUES (
                " . herikaActionCatalogSqlText($row['code_name']) . ",
                " . herikaActionCatalogSqlText($row['action_name'] ?? '') . ",
                " . herikaActionCatalogSqlText($row['description'] ?? '') . ",
                " . herikaActionCatalogSqlText($row['return_message'] ?? '') . ",
                " . herikaActionCatalogSqlBool(!empty($row['available_to_npc'])) . ",
                " . herikaActionCatalogSqlBool(!empty($row['available_to_followers'])) . ",
                " . herikaActionCatalogSqlBool(isset($selectedMap[$row['code_name']])) . "
            )
            ON CONFLICT (code_name) DO UPDATE SET
                action_name = EXCLUDED.action_name,
                description = EXCLUDED.description,
                return_message = EXCLUDED.return_message,
                available_to_npc = EXCLUDED.available_to_npc,
                available_to_followers = EXCLUDED.available_to_followers,
                is_activated = EXCLUDED.is_activated,
                updated_at = NOW()
        ");
    }

    herikaMarkLegacyActionPreferencesImported();
    herikaActionCatalogResetCache();
}

function herikaGetActionCatalogRowsByCode()
{
    if (isset($GLOBALS["HERIKA_ACTION_CATALOG_ROWS_BY_CODE"])) {
        return $GLOBALS["HERIKA_ACTION_CATALOG_ROWS_BY_CODE"];
    }

    $GLOBALS["HERIKA_ACTION_CATALOG_ROWS_BY_CODE"] = [];
    if (!herikaActionCatalogDbReady()) {
        return $GLOBALS["HERIKA_ACTION_CATALOG_ROWS_BY_CODE"];
    }

    $rows = $GLOBALS["db"]->fetchAll("
        SELECT
            code_name,
            action_name,
            description,
            return_message,
            available_to_npc,
            available_to_followers,
            is_activated
        FROM public.combined_core_action
    ");

    foreach ($rows as $row) {
        $codeName = trim(strval($row['code_name'] ?? ''));
        if ($codeName === '') {
            continue;
        }

        $GLOBALS["HERIKA_ACTION_CATALOG_ROWS_BY_CODE"][$codeName] = [
            'code_name' => $codeName,
            'action_name' => strval($row['action_name'] ?? $codeName),
            'description' => strval($row['description'] ?? ''),
            'return_message' => strval($row['return_message'] ?? ''),
            'available_to_npc' => herikaActionCatalogToBool($row['available_to_npc'] ?? false),
            'available_to_followers' => herikaActionCatalogToBool($row['available_to_followers'] ?? false),
            'is_activated' => herikaActionCatalogToBool($row['is_activated'] ?? false),
        ];
    }

    return $GLOBALS["HERIKA_ACTION_CATALOG_ROWS_BY_CODE"];
}

function herikaLoadEnabledActionCodesForMode($isNpc)
{
    $rowsByCode = herikaGetActionCatalogRowsByCode();
    if (count($rowsByCode) === 0) {
        return [];
    }

    $enabledCodes = [];
    foreach ($rowsByCode as $codeName => $row) {
        if (!$row['is_activated']) {
            continue;
        }

        if ($isNpc && !empty($row['available_to_npc'])) {
            $enabledCodes[] = $codeName;
        } elseif (!$isNpc && !empty($row['available_to_followers'])) {
            $enabledCodes[] = $codeName;
        }
    }

    return array_values(array_unique($enabledCodes));
}

function herikaActionCatalogIsActionEnabled($codeName)
{
    $codeName = trim(strval($codeName));
    if ($codeName === '') {
        return false;
    }

    $rowsByCode = herikaGetActionCatalogRowsByCode();
    if (!isset($rowsByCode[$codeName])) {
        return true;
    }

    return !empty($rowsByCode[$codeName]['is_activated']);
}

function herikaActionCatalogUpsertCustomToggle($codeName, $enabled)
{
    $codeName = trim(strval($codeName));
    if ($codeName === '' || !herikaActionCatalogDbReady()) {
        return false;
    }

    $literalCode = herikaActionCatalogSqlText($codeName);
    $row = $GLOBALS["db"]->fetchOne("
        SELECT
            code_name,
            action_name,
            description,
            return_message,
            available_to_npc,
            available_to_followers
        FROM public.combined_core_action
        WHERE code_name = {$literalCode}
        LIMIT 1
    ");

    if (!$row) {
        return false;
    }

    $result = $GLOBALS["db"]->execQuery("
        INSERT INTO public.core_action_custom (
            code_name,
            action_name,
            description,
            return_message,
            available_to_npc,
            available_to_followers,
            is_activated
        ) VALUES (
            " . herikaActionCatalogSqlText($row['code_name'] ?? $codeName) . ",
            " . herikaActionCatalogSqlText($row['action_name'] ?? '') . ",
            " . herikaActionCatalogSqlText($row['description'] ?? '') . ",
            " . herikaActionCatalogSqlText($row['return_message'] ?? '') . ",
            " . herikaActionCatalogSqlBool(herikaActionCatalogToBool($row['available_to_npc'] ?? false)) . ",
            " . herikaActionCatalogSqlBool(herikaActionCatalogToBool($row['available_to_followers'] ?? false)) . ",
            " . herikaActionCatalogSqlBool((bool) $enabled) . "
        )
        ON CONFLICT (code_name) DO UPDATE SET
            action_name = EXCLUDED.action_name,
            description = EXCLUDED.description,
            return_message = EXCLUDED.return_message,
            available_to_npc = EXCLUDED.available_to_npc,
            available_to_followers = EXCLUDED.available_to_followers,
            is_activated = EXCLUDED.is_activated,
            updated_at = NOW()
    ");

    herikaActionCatalogResetCache();
    return $result !== false;
}
