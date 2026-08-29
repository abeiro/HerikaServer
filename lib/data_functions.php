<?php

require_once(__DIR__."/utils.php");
// used for openai_token_count table

require_once(__DIR__."/utils_game_timestamp.php");
require_once(__DIR__."/lazy_xml.php");
require_once(__DIR__."/model_dynmodel.php");
require_once(__DIR__."/emote_moods.php");
require_once(__DIR__."/core/activity_status.php");
require_once(__DIR__."/core/transformation_state.php");
require_once(__DIR__."/core/game_plugins.php");
require_once(__DIR__."/core/event_type.php");
require_once(__DIR__."/core/npc_master.class.php");
require_once(__DIR__."/core/core_profiles.class.php");
require_once(__DIR__."/prompt_injections.php");
require_once(__DIR__."/vr_items.php");
require_once(__DIR__."/visual_context.php");
require_once(__DIR__."/memory_ranking.php");


function ChangeHerikaName($new_name="") {
    if ($new_name > "") {
        SaveOriginalHerikaName();
        $GLOBALS["HERIKA_NAME"] = $new_name;
    }
}

function SaveOriginalHerikaName() {
    $b_already_saved = ($GLOBALS["ORIGINAL_HERIKA_NAME_SAVED"] ?? false);
    if (!$b_already_saved) {
        $herika = ($GLOBALS["HERIKA_NAME"] ?? "");
                if ((strlen($herika) > 0) && ($herika !== "The Narrator") && ($herika !== "Player") && ($herika !== "LLMFallback") && (stripos($herika, "Narrator") === false) && (stripos($herika, "actor") === false) && (stripos($herika, "everyone") === false) && (stripos($herika, "*") === false) && (stripos($herika, "none") === false) ) {
            $GLOBALS["ORIGINAL_HERIKA_NAME"] = $herika;
            $GLOBALS["ORIGINAL_HERIKA_NAME_SAVED"] = true;
        }
    }
}

function GetOriginalHerikaName() {
    $b_already_saved = ($GLOBALS["ORIGINAL_HERIKA_NAME_SAVED"] ?? false);
    if ($b_already_saved) {
        $herika = $GLOBALS["ORIGINAL_HERIKA_NAME"] ?? '';
    } else {
        $herika = $GLOBALS["HERIKA_NAME"];
    }
    return $herika;
} 

function get_connector_id($s_driver='', $s_model='', $s_url='') {
    $i_res = -1;
    if (isset($GLOBALS["CHIM_CORE_CURRENT_CONNECTOR_DATA"])) {
        $i_res = $GLOBALS["CHIM_CORE_CURRENT_CONNECTOR_DATA"]["id"] ?? -1;
    }
    if ($i_res < 0) {
        if ((strlen($s_model) > 0) && (strlen($s_url) > 0)  && (strlen($s_driver) > 0)) {
            $query = "SELECT id FROM public.core_llm_connector WHERE (url='{$s_url}') AND (model='{$s_model}') AND (driver='{$s_driver}') LIMIT 1 ";
            $ret = $GLOBALS["db"]->fetchAll($query);
            if ($ret) {
                $i_res = intval($ret[0]['id'] ?? -1);
            }
        }
    }
    return $i_res;
}

function ReplacePlayerNamePlaceholder($s_input) {
    //replace #PLAYER_NAME# with player name
    $s_res = $s_input;
    if ((strlen(trim($s_input))) > 12) {
        $promptCharacterName = function_exists('chimGetPromptCharacterName')
            ? chimGetPromptCharacterName()
            : $GLOBALS["HERIKA_NAME"];
        $narratorRoleplayName = function_exists('chimGetNarratorRoleplayName')
            ? chimGetNarratorRoleplayName()
            : 'The Narrator';
        $s_res = strtr($s_input, [
            "{HERIKA_NAME}" =>$promptCharacterName,
            "{NARRATOR_NAME}" =>$narratorRoleplayName,
            "{PLAYER_NAME}"=>$GLOBALS["PLAYER_NAME"],
            "#HERIKA_NAME#" =>$promptCharacterName,
            "#NARRATOR_NAME#" =>$narratorRoleplayName,
            "#PLAYER_NAME#"=>$GLOBALS["PLAYER_NAME"]
        ]);
    }
    return $s_res;
}

if (!function_exists('chimAppendDiaryConnectorCandidate')) {
    function chimAppendDiaryConnectorCandidate(array &$candidates, string $candidate): void
    {
        $normalized = strtolower(trim($candidate));
        if ($normalized === '' || $normalized === 'array') {
            return;
        }

        switch ($normalized) {
            case 'openrouterjson':
                $normalized = 'openrouter';
                break;
            case 'openaijson':
                $normalized = 'openai';
                break;
            case 'koboldcppjson':
                $normalized = 'koboldcpp';
                break;
        }

        if (!in_array($normalized, $candidates, true)) {
            $candidates[] = $normalized;
        }
    }
}

if (!function_exists('chimExtractDiaryConnectorCandidates')) {
    function chimExtractDiaryConnectorCandidates($value): array
    {
        $candidates = [];

        $pushValue = function ($candidate) use (&$candidates, &$pushValue): void {
            if (is_array($candidate)) {
                foreach ($candidate as $nestedCandidate) {
                    $pushValue($nestedCandidate);
                }
                return;
            }

            if (!is_scalar($candidate)) {
                return;
            }

            $stringValue = trim((string)$candidate);
            if ($stringValue === '') {
                return;
            }

            if (($stringValue[0] === '[' || $stringValue[0] === '{')) {
                $decoded = json_decode($stringValue, true);
                if (is_array($decoded)) {
                    foreach ($decoded as $decodedCandidate) {
                        $pushValue($decodedCandidate);
                    }
                    return;
                }
            }

            if (strpos($stringValue, ',') !== false) {
                foreach (explode(',', $stringValue) as $splitCandidate) {
                    $pushValue($splitCandidate);
                }
                return;
            }

            chimAppendDiaryConnectorCandidate($candidates, $stringValue);
        };

        $pushValue($value);

        return $candidates;
    }
}

if (!function_exists('chimResolveDiaryConnectorName')) {
    function chimResolveDiaryConnectorName($rawValue = null, bool $persistGlobal = true): ?string
    {
        $connectorDir = __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "connector" . DIRECTORY_SEPARATOR;
        $sourceValue = func_num_args() > 0 ? $rawValue : ($GLOBALS["CONNECTORS_DIARY"] ?? null);

        $candidates = chimExtractDiaryConnectorCandidates($sourceValue);
        foreach (chimExtractDiaryConnectorCandidates($GLOBALS["CONNECTORS"] ?? null) as $candidate) {
            if (!in_array($candidate, $candidates, true)) {
                $candidates[] = $candidate;
            }
        }

        foreach (['openrouter', 'openai', 'google_openaijson', 'koboldcpp'] as $fallbackCandidate) {
            if (!in_array($fallbackCandidate, $candidates, true)) {
                $candidates[] = $fallbackCandidate;
            }
        }

        foreach ($candidates as $candidate) {
            if (file_exists($connectorDir . $candidate . ".php")) {
                if ($persistGlobal) {
                    $originalValue = is_scalar($sourceValue) ? trim((string)$sourceValue) : json_encode($sourceValue);
                    if ($originalValue !== $candidate) {
                        Logger::warn("DIARY: Resolved CONNECTORS_DIARY value " . var_export($sourceValue, true) . " to '{$candidate}'");
                    }
                    $GLOBALS["CONNECTORS_DIARY"] = $candidate;
                }
                return $candidate;
            }
        }

        return null;
    }
}

function getGoldFromMetadata($npcName = null) {
    if ($npcName === null) {
        $npcName = isset($GLOBALS["HERIKA_NAME"]) ? $GLOBALS["HERIKA_NAME"] : "";
    }
    
    if (empty($npcName)) {
        return 0;
    }
    
    try {
        $npcMaster = new NpcMaster();
        $npcData = $npcMaster->getByName($npcName);
        
        if (!$npcData) {
            return 0;
        }
        
        $metaData = $npcMaster->getMetaData($npcData);
        
        if (!isset($metaData["inventory"]) || !is_array($metaData["inventory"])) {
            return 0;
        }
        
        foreach ($metaData["inventory"] as $item) {
            $itemName = isset($item["name"]) ? strtolower($item["name"]) : "";
            if (stripos($itemName, "gold") !== false || stripos($itemName, "coin") !== false || stripos($itemName, "septim") !== false) {
                return isset($item["count"]) ? intval($item["count"]) : 0;
            }
        }
    } catch (Exception $e) {
        // Silently fail and return 0
    }
    
    return 0;
}

function isItemBlacklisted($itemName) {
    if (!isset($GLOBALS["ITEM_BLACKLIST"]) || empty($GLOBALS["ITEM_BLACKLIST"])) {
        return false;
    }
    
    $blacklistedItems = array_map('trim', explode(',', $GLOBALS["ITEM_BLACKLIST"]));
    $itemNameLower = strtolower(trim($itemName));
    
    foreach ($blacklistedItems as $blacklistedItem) {
        if (strtolower($blacklistedItem) === $itemNameLower) {
            return true;
        }
    }
    
    return false;
}

/**
 * Lookup a description by candidate base IDs while preserving override priority.
 * Custom rows must win across all legacy/stable candidates before seeded defaults.
 */
function lookupDescriptionRecordByCandidates(array $candidateBaseIds, bool $requireDescription = false): ?array {
    global $db;

    $candidateRows = [];
    $pushCandidateRow = function (string $plugin, string $baseid) use (&$candidateRows): void {
        $plugin = trim($plugin);
        $baseid = trim($baseid);
        if ($baseid === '') {
            return;
        }

        $key = $plugin . '|' . $baseid;
        if (!isset($candidateRows[$key])) {
            $candidateRows[$key] = ['plugin' => $plugin, 'baseid' => $baseid];
        }
    };

    foreach ($candidateBaseIds as $candidateBaseId) {
        $candidateBaseId = trim((string) $candidateBaseId);
        if ($candidateBaseId === '') {
            continue;
        }

        if (strpos($candidateBaseId, '|') !== false) {
            $parsedStable = chimParseStableFormReference($candidateBaseId);
            if (!$parsedStable) {
                continue;
            }
            $plugin = $parsedStable['plugin_name'];
            $baseid = $parsedStable['local_formid'];
            $pushCandidateRow($plugin, $baseid);

            $pluginRow = function_exists('chimGetLoadedGamePluginByName')
                ? chimGetLoadedGamePluginByName($plugin)
                : null;
            if ($pluginRow && !empty($pluginRow['formid_prefix']) && function_exists('chimComputeRuntimeFormIdFromPrefix')) {
                $runtimeBaseid = chimComputeRuntimeFormIdFromPrefix($pluginRow['formid_prefix'], $baseid);
                if ($runtimeBaseid !== null && $runtimeBaseid !== $baseid) {
                    $pushCandidateRow($plugin, $runtimeBaseid);
                }
            }
        } else {
            $pushCandidateRow('', strtoupper($candidateBaseId));
        }
    }

    foreach (['descriptions_custom', 'descriptions'] as $tableName) {
        foreach ($candidateRows as $candidateRow) {
            $escapedPlugin = $db->escape($candidateRow['plugin']);
            $escapedBaseId = $db->escape($candidateRow['baseid']);
            $record = $db->fetchOne(
                "SELECT plugin, baseid, name, description
                   FROM public.{$tableName}
                  WHERE plugin = '{$escapedPlugin}'
                    AND baseid = '{$escapedBaseId}'
                  LIMIT 1"
            );

            if (!$record) {
                continue;
            }

            if ($requireDescription && empty($record['description'])) {
                continue;
            }

            return $record;
        }
    }

    return null;
}

/**
 * Lookup description from the merged descriptions view using runtime, legacy, or stable keys.
 * Supports:
 * - exact runtime FormIDs (e.g. 020098A0)
 * - legacy wildcard keys (e.g. XX0098A0, FEXXX822)
 * - internal plugin-aware candidates generated from loaded plugin metadata
 * 
 * @param string $formId The identifier to lookup
 * @return array|null Array with 'baseid', 'name', and 'description' keys, or null if not found
 */
function lookupDescriptionByFormID(string $formId): ?array {
    return lookupDescriptionRecordByCandidates(chimBuildDescriptionBaseIdCandidates($formId));
}

function chimLookupItemDescriptionForContext(string $itemName, ?string $baseid = null): ?string {
    global $db;

    if (!isset($db)) {
        return null;
    }

    $baseid = trim((string) $baseid);
    if ($baseid !== '') {
        $record = lookupDescriptionByFormID($baseid);
        if (!empty($record['description'])) {
            return trim((string) $record['description']);
        }
    }

    $itemName = trim($itemName);
    if ($itemName === '' || stripos($itemName, 'Missing Name') !== false) {
        return null;
    }

    $escapedName = $db->escape($itemName);
    $record = $db->fetchOne("
        SELECT description
          FROM public.combined_descriptions
         WHERE LOWER(name) = LOWER('{$escapedName}')
           AND NULLIF(TRIM(description), '') IS NOT NULL
         LIMIT 1
    ");

    if (!empty($record['description'])) {
        return trim((string) $record['description']);
    }

    return null;
}

function getNameForItemReference($refid) {
    global $db;
    if (strpos($refid, '0x') === 0) {
        $refid = substr($refid, 2);
    }
    $baseid = "00" . substr($refid, 2);
    $modIndex = substr($refid, 0, 4);
    $candidateMod = $db->fetchOne("select * from game_plugins where formid_prefix='{$modIndex}'");
    // error_log("[DEBUG] getNameForItemReference: refid={$refid}, baseid={$baseid}, modIndex={$modIndex}, candidateMod=" . var_export($candidateMod, true));
    if (!$candidateMod) {
        $modIndex = substr($refid, 0, 2);
        $candidateMod = $db->fetchOne("select * from game_plugins where formid_prefix='{$modIndex}'");
    }

    // error_log("[DEBUG] getNameForItemReference: refid={$refid}, baseid={$baseid}, modIndex={$modIndex}, candidateMod=" . var_export($candidateMod, true));
    if ($candidateMod) {
        $pluginName = $candidateMod['plugin_name'];
        $existing = $db->fetchOne("select * from combined_descriptions where baseid='{$baseid}' and plugin='{$pluginName}'");
        // error_log("[DEBUG] getNameForItemReference: refid={$refid}, baseid={$baseid}, pluginName={$pluginName}, existing=" . var_export($existing, true));
        return $existing['name'] ?? null;
    }

    return null;
}

function chimEquipmentVanillaSlotLabels(): array
{
    return [
        'helmet' => 'Helmet',
        'armor' => 'Armor',
        'boots' => 'Boots',
        'gloves' => 'Gloves',
        'amulet' => 'Amulet',
        'ring' => 'Ring',
        'left_hand' => 'Left Hand',
        'right_hand' => 'Right Hand',
    ];
}

function chimEquipmentModdedSlotLabels(): array
{
    return [
        'mod_mouth' => 'Face/Mouth',
        'mod_neck' => 'Neck',
        'cape' => 'Cape/Chest Outer',
        'backpack' => 'Back/Backpack',
        'mod_misc1' => 'Misc Slot 48',
        'mod_pelvis_primary' => 'Pelvis Outer',
        'mod_pelvis_secondary' => 'Pelvis Secondary',
        'mod_leg_right' => 'Right Leg',
        'mod_leg_left' => 'Left Leg',
        'mod_face_jewelry' => 'Face Jewelry',
        'shirt' => 'Chest Under',
        'mod_shoulder' => 'Shoulder',
        'mod_arm_left' => 'Left Arm',
        'mod_arm_right' => 'Right Arm',
        'mod_misc2' => 'Misc Slot 60',
    ];
}

function chimEquipmentAllSlotLabels(): array
{
    return chimEquipmentVanillaSlotLabels() + chimEquipmentModdedSlotLabels();
}

function chimEquipmentProfileSlotKeys(): array
{
    return array_keys(chimEquipmentAllSlotLabels());
}

function chimEquipmentSlotHasVisibleItem(array $equipmentData, string $slot): bool
{
    if (empty($equipmentData[$slot])) {
        return false;
    }

    $itemName = trim((string) $equipmentData[$slot]);
    return $itemName !== '' && !isItemBlacklisted($itemName) && stripos($itemName, 'Missing Name') === false;
}

function chimEquipmentHasBodyCoverage(array $equipmentData): bool
{
    foreach (['armor', 'shirt', 'cape'] as $slot) {
        if (chimEquipmentSlotHasVisibleItem($equipmentData, $slot)) {
            return true;
        }
    }

    return false;
}

function chimNormalizePromptFormId($value): ?string
{
    $formId = trim((string) $value);
    if ($formId === '') {
        return null;
    }

    if (stripos($formId, '0x') === 0) {
        $formId = substr($formId, 2);
    }

    if (!preg_match('/^[0-9a-f]{1,8}$/i', $formId)) {
        return null;
    }

    return '0x' . strtoupper(str_pad($formId, 8, '0', STR_PAD_LEFT));
}

function chimEscapePromptItemText($value): string
{
    $value = str_replace(["\r", "\n", "\t"], ' ', (string) $value);
    $value = trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    $value = htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    return str_replace('`', '&#96;', $value);
}

function chimFormatInventoryPromptLines(
    array $inventory,
    ?callable $getItemDescription = null,
    array &$describedBaseids = [],
    bool $descriptionsOnly = false,
    bool $showGoldValue = false
): array {
    $lines = [];

    foreach ($inventory as $item) {
        $itemName = trim((string) ($item['name'] ?? ''));
        if ($itemName === '' || $itemName === '<Missing Name>' || isItemBlacklisted($itemName)) {
            continue;
        }

        $count = max(0, intval($item['count'] ?? 0));
        $goldValue = max(0, intval($item['goldvalue'] ?? 0));
        $rawBaseId = trim((string) ($item['baseid'] ?? ''));
        $baseId = chimNormalizePromptFormId($rawBaseId);
        $baseIdKey = $baseId ?? '';
        $description = null;

        if ($getItemDescription !== null && $count <= 5 && ($baseIdKey === '' || !in_array($baseIdKey, $describedBaseids, true))) {
            $description = $getItemDescription($itemName, $rawBaseId !== '' ? $rawBaseId : null);
            if ($description && $baseIdKey !== '') {
                $describedBaseids[] = $baseIdKey;
            }
        }

        if ($descriptionsOnly && !$description) {
            continue;
        }

        $safeName = chimEscapePromptItemText($itemName);
        $identifier = $baseId !== null ? "`{$baseId}:{$safeName}`" : $safeName;
        $line = "- {$identifier} ({$count})";
        if ($showGoldValue) {
            $line .= " - Gold Value: {$goldValue}";
        }
        if ($description) {
            $line .= ' - ' . chimEscapePromptItemText($description);
        }

        $lines[] = $line;
    }

    return $lines;
}

function chimBuildInventoryPromptContext(
    array $inventory,
    ?callable $getItemDescription = null,
    array &$describedBaseids = [],
    bool $descriptionsOnly = false
): string {
    $lines = chimFormatInventoryPromptLines($inventory, $getItemDescription, $describedBaseids, $descriptionsOnly);
    if (empty($lines)) {
        return '';
    }

    return "<inventory>\n# INVENTORY\nFormat: BaseID:ItemName (quantity)\n\n"
        . implode("\n", $lines)
        . "\n</inventory>";
}

function chimFormatEquipmentPromptLines(array $equipmentData, array $slotLabels, ?callable $getItemDescription = null, array &$describedBaseids = []): array
{
    $equipmentParts = [];

    foreach ($slotLabels as $slot => $label) {
        if (empty($equipmentData[$slot])) {
            continue;
        }

        $itemName = trim((string) $equipmentData[$slot]);
        if ($itemName === '' || isItemBlacklisted($itemName) || stripos($itemName, 'Missing Name') !== false) {
            continue;
        }

        $baseid = isset($equipmentData[$slot . '_baseid']) ? trim((string) $equipmentData[$slot . '_baseid']) : '';
        $itemLine = "  - {$label}: {$itemName}";

        if ($getItemDescription !== null) {
            $baseidKey = chimNormalizePromptFormId($baseid) ?? '';
            if ($baseidKey !== '' && in_array($baseidKey, $describedBaseids, true)) {
                $equipmentParts[] = $itemLine;
                continue;
            }

            $description = $getItemDescription($itemName, $baseid !== '' ? $baseid : null);
            if ($description) {
                $itemLine .= " - {$description}";
                if ($baseidKey !== '') {
                    $describedBaseids[] = $baseidKey;
                }
            }
        }

        $equipmentParts[] = $itemLine;
    }

    return $equipmentParts;
}

function chimFormatProfileEquipmentParts(array $equipmentData, array $slots, bool $includeDescriptions = true): array {
    $equipmentParts = [];
    $describedBaseids = [];

    foreach ($slots as $slot) {
        if (empty($equipmentData[$slot])) {
            continue;
        }

        if (!is_scalar($equipmentData[$slot])) {
            continue;
        }

        $itemName = trim((string) $equipmentData[$slot]);
        if ($itemName === '' || isItemBlacklisted($itemName) || stripos($itemName, 'Missing Name') !== false) {
            continue;
        }

        $baseid = isset($equipmentData[$slot . '_baseid']) && is_scalar($equipmentData[$slot . '_baseid'])
            ? trim((string) $equipmentData[$slot . '_baseid'])
            : '';
        $description = null;
        $baseidKey = $baseid !== '' ? strtoupper($baseid) : '';

        if ($includeDescriptions && ($baseidKey === '' || !in_array($baseidKey, $describedBaseids, true))) {
            $description = chimLookupItemDescriptionForContext($itemName, $baseid);
            if ($description !== null && $baseidKey !== '') {
                $describedBaseids[] = $baseidKey;
            }
        }

        $equipmentParts[] = $description !== null
            ? "{$itemName} ({$description})"
            : $itemName;
    }

    return $equipmentParts;
}

function chimProfileEquipmentSlotsFromData(array $equipmentData, array $preferredSlots): array {
    $slots = $preferredSlots;
    foreach ($equipmentData as $key => $value) {
        $slot = trim((string) $key);
        if ($slot === '' || substr($slot, -7) === '_baseid' || substr($slot, -9) === '_keywords' || !is_scalar($value)) {
            continue;
        }
        if (!in_array($slot, $slots, true)) {
            $slots[] = $slot;
        }
    }
    return $slots;
}

function chimNormalizeProfileScalar($value): string {
    if (is_bool($value)) {
        return $value ? 'true' : 'false';
    }
    if (is_int($value) || is_float($value)) {
        return (string) $value;
    }
    return trim((string) $value);
}

function chimFirstProfileValue(...$values): string {
    foreach ($values as $value) {
        $value = chimNormalizeProfileScalar($value);
        if ($value !== '') {
            return $value;
        }
    }
    return '';
}

function chimParsePlayerInfoEventData(string $data): array {
    $fields = [];
    foreach (['level', 'name', 'race', 'gender'] as $key) {
        if (preg_match('/(?:^|,)\s*' . preg_quote($key, '/') . '\s*:\s*"([^"]*)"/i', $data, $quotedMatch)) {
            $fields[$key] = trim($quotedMatch[1]);
            continue;
        }
        if (preg_match('/(?:^|,)\s*' . preg_quote($key, '/') . '\s*:\s*([^,]+)/i', $data, $plainMatch)) {
            $fields[$key] = trim($plainMatch[1], " \t\r\n\"");
        }
    }
    return $fields;
}

function chimGetLatestPlayerInfoEventData(): array {
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $cached = [];
    if (empty($GLOBALS['db'])) {
        return $cached;
    }

    try {
        $row = $GLOBALS['db']->fetchOne("
            SELECT data
            FROM eventlog
            WHERE type IN ('playerinfo', 'infoplayer')
            ORDER BY localts DESC
            LIMIT 1
        ");
        if (is_array($row) && isset($row['data'])) {
            $cached = chimParsePlayerInfoEventData((string) $row['data']);
        }
    } catch (Throwable $e) {
        Logger::debug("Could not read latest playerinfo event: " . $e->getMessage());
    }

    return $cached;
}

function chimBuildPlayerProfileName(string $actor, $player): string {
    $eventInfo = chimGetLatestPlayerInfoEventData();
    $transformData = method_exists($player, 'getJson') ? ($player->getJson('transformation_state') ?? []) : [];

    $gender = chimFirstProfileValue(
        method_exists($player, 'get') ? $player->get('gender') : '',
        $eventInfo['gender'] ?? ''
    );
    $race = chimFirstProfileValue(
        method_exists($player, 'get') ? $player->get('race') : '',
        $transformData['race_name'] ?? '',
        $eventInfo['race'] ?? ''
    );

    $profileName = $actor;
    if ($gender !== '') {
        $gender = ucfirst(strtolower($gender));
    }
    if ($gender !== '' && $race !== '') {
        $profileName .= " ({$gender} {$race})";
    } elseif ($race !== '') {
        $profileName .= " ({$race})";
    }

    return $profileName;
}

function chimNormalizePlayerProfileBio(string $bio, string $actor): string {
    $bio = trim(ReplacePlayerNamePlaceholder($bio));
    if ($bio === '') {
        return '';
    }

    $actorPattern = preg_quote($actor, '/');
    if (preg_match('/^I(?:\'m| am)\s+' . $actorPattern . '\.?$/i', $bio)) {
        return '';
    }

    return $bio;
}

/**
 * Lookup description only by exact runtime FormID or internal plugin-aware candidate.
 * This deliberately skips legacy wildcard keys and name fallback to avoid
 * cross-matching unrelated item descriptions for spells.
 *
 * @param string $formId The runtime or stable identifier to lookup
 * @return array|null Array with 'baseid', 'name', and 'description' keys, or null if not found
 */
function lookupStrictDescriptionByFormID(string $formId): ?array {
    $candidates = [];
    $pushCandidate = function ($candidate) use (&$candidates): void {
        $candidate = trim((string) $candidate);
        if ($candidate === '') {
            return;
        }

        if (strpos($candidate, '|') !== false) {
            $parsedStable = chimParseStableFormReference($candidate);
            if ($parsedStable) {
                $candidate = $parsedStable['stable_key'];
            }
        } else {
            $candidate = strtoupper($candidate);
        }

        if (!in_array($candidate, $candidates, true)) {
            $candidates[] = $candidate;
        }
    };

    $formId = trim($formId);
    if ($formId === '') {
        return null;
    }

    $parsedStableReference = chimParseStableFormReference($formId);
    if ($parsedStableReference) {
        $pushCandidate($parsedStableReference['stable_key']);

        $pluginRow = chimGetLoadedGamePluginByName($parsedStableReference['plugin_name']);
        if ($pluginRow && !empty($pluginRow['formid_prefix'])) {
            $runtimeFormId = chimComputeRuntimeFormIdFromPrefix(
                $pluginRow['formid_prefix'],
                $parsedStableReference['local_formid']
            );
            if ($runtimeFormId) {
                $pushCandidate($runtimeFormId);
            }
        }
    } else {
        $runtimeFormId = chimNormalizeRuntimeFormId($formId);
        if ($runtimeFormId !== '') {
            $pushCandidate($runtimeFormId);

            $pluginRow = chimGetLoadedGamePluginByRuntimeFormId($runtimeFormId);
            $localFormId = chimExtractLocalFormIdFromRuntimeFormId($runtimeFormId);
            if ($pluginRow && !empty($pluginRow['plugin_name']) && $localFormId !== '') {
                $pushCandidate(chimBuildStableFormReference($pluginRow['plugin_name'], $localFormId));
            }
        }
    }

    return lookupDescriptionRecordByCandidates($candidates, true);
}

/**
 * Get height description based on scale value
 * Reads height descriptions from prompts table with hardcoded fallback
 * 
 * @param float $scale The NPC scale value (typically 0.6 to 1.4)
 * @return string Height description or empty string if not found
 */
function getHeightDescription(float $scale): string {
    static $heightDescriptions = null;
    
    // Hardcoded fallback in case database fails
    $fallbackDescriptions = [
        ['name' => 'VerySmall', 'min_scale' => 0.0, 'max_scale' => 0.60, 'description' => 'Very small and tiny in stature'],
        ['name' => 'Small', 'min_scale' => 0.60, 'max_scale' => 0.80, 'description' => 'Smaller than most people'],
        ['name' => 'ModestStature', 'min_scale' => 0.80, 'max_scale' => 0.95, 'description' => 'Slightly below average height'],
        ['name' => 'Average', 'min_scale' => 0.95, 'max_scale' => 1.05, 'description' => 'Typical height'],
        ['name' => 'Tall', 'min_scale' => 1.05, 'max_scale' => 1.20, 'description' => 'Tall, standing a head above most people'],
        ['name' => 'VeryTall', 'min_scale' => 1.20, 'max_scale' => 1.40, 'description' => 'Very tall'],
        ['name' => 'Giantlike', 'min_scale' => 1.40, 'max_scale' => 99.0, 'description' => 'Giant in height and stature']
    ];
    
    // Load height descriptions from prompts table (cached)
    if ($heightDescriptions === null) {
        try {
            global $db;
            $result = $db->fetchOne("SELECT COALESCE(custom_prompt, default_prompt) as prompt FROM prompts WHERE prompt_key = 'height_descriptions'");
            
            if ($result && !empty($result['prompt'])) {
                $data = json_decode($result['prompt'], true);
                $heightDescriptions = $data['height_descriptions'] ?? $fallbackDescriptions;
            } else {
                // Database query succeeded but no data - use fallback
                $heightDescriptions = $fallbackDescriptions;
            }
        } catch (Exception $e) {
            // Database error - use fallback
            Logger::debug("Using fallback height descriptions due to database error: " . $e->getMessage());
            $heightDescriptions = $fallbackDescriptions;
        }
    }
    
    // Find matching height description
    foreach ($heightDescriptions as $desc) {
        if ($scale >= $desc['min_scale'] && $scale < $desc['max_scale']) {
            return $desc['description'];
        }
    }
    
    return ''; // No description if out of range
}


function DataDequeue($timestamp = 0)
{
    global $db;
    if ($timestamp !== 0) {
        $clause="and localts<={$timestamp} ";
    } else {
        $clause="";
    }
    // Claim pending responses atomically, then return them in their original queue order.
    $results = $db->fetchAll(
        "WITH queued AS (
             SELECT rowid
             FROM responselog
             WHERE sent=0 $clause
             ORDER BY rowid ASC
             FOR UPDATE SKIP LOCKED
         ), claimed AS (
             UPDATE responselog AS response
             SET sent=1
             FROM queued
             WHERE response.rowid=queued.rowid
             RETURNING response.*
         )
         SELECT * FROM claimed ORDER BY rowid ASC"
    );
    
    $finalData = array();
    foreach ($results as $row) {
        $finalData[] = $row;
    }

    return $finalData;

}

function DataLastDataFor($actor, $lastNelements = -10)
{
    global $db;
    $lastDialogFull = array();
    $results = $db->fetchAll("select  
    case 
      when type like 'info%' or type like 'death%' or type like 'funcret%' or type like 'location%' or type='chat_background' or data like '%background chat%' then 'The Narrator:'
      when type='book' then 'The Narrator: ({$GLOBALS["PLAYER_NAME"]} took the book ' 
      else '' 
    end||a.data  as data 
    FROM  eventlog a WHERE data like '%$actor%' 
    and type<>'combatend'  
    and type<>'bored' and type<>'init' and type<>'lockpicked' and type<>'infonpc' and type<>'infoloc' and type<>'infoitems' and type<>'info' and type<>'funcret'  and type<>'quest'
    and type<>'user_input'
    and type<>'funccall'  and type<>'togglemodel' order by gamets desc,ts desc,localts desc,rowid desc LIMIT 150 OFFSET 0");
    $lastData = "";


    foreach ($results as $row) {

        if ($lastData != md5($row["data"])) {
            if ((strpos($row["data"], "{$GLOBALS["HERIKA_NAME"]}:") !== false) || ((strpos($row["data"], "{$GLOBALS["PLAYER_NAME"]}:") !== false))) {
                $pattern = "/\(Context location:[^)]+?\)/"; // Remove only the exact context location pattern
                $replacement = "";
                $row["data"] = preg_replace($pattern, $replacement, $row["data"]); // // assistant vs user war
                if ((strpos($row["data"], "{$GLOBALS["HERIKA_NAME"]}:") !== false)) {
                    $role = "assistant";
                } else {
                    $role = "user";
                }

                $lastDialogFull[] = array('role' => $role, 'content' => $row["data"]);

            } else {
                $lastDialogFull[] = array('role' => 'user', 'content' => $row["data"]);
            }

        }
        $lastData = md5($row["data"]);

    }

    // Date issues

    foreach ($lastDialogFull as $n => $line) {

        $pattern = '/(\w+), (\d{1,2}:\d{2} (?:AM|PM)), (\d{1,2})(?:st|nd|rd|th) of ([A-Za-z\'\ ]+), 4E (\d+)/'; //extract also for months with apostrophe like Sun's Something
        $replacement = 'Day name: $1, Hour: $2, Day Number: $3, Month: $4, 4th Era, Year: $5';
        $result = preg_replace($pattern, $replacement, $line["content"]);
        $lastDialogFull[$n]["content"] = $result;
    }


    // Clean context locations for Herikas dialog.


    $lastDialogFullReversed = array_reverse($lastDialogFull);
    $lastDialog = array_slice($lastDialogFullReversed, $lastNelements);
    $last_location = null;


    return $lastDialog;

}

/**
 * Get context for actor to send to llm
 */
function DataLastInfoFor($actorBeingCalled, $lastNelements = -2,$addNPCDescriptions=false,$excludeBusy=false,$excludeFarAway=false)
{
    
    $lastDialog = array(); // Initialize the return array
    $followers=[];
    $actorsInRangeList=DataBeingsInCloseRange($excludeFarAway);
    $actorsInRange=strtr($actorsInRangeList,["|"=>"\n* "]);
    $actorDetailedList=explode("|",$actorsInRangeList);
    // Not always the same order
    shuffle($actorDetailedList);
    // error_log("[DataLastInfoFor] $actorsInRangeList");

    $nearbyContextOptionEnabled = function (string $bucket, string $id, bool $default = true): bool {
        if (function_exists('chimPromptContextOptionEnabled')) {
            return chimPromptContextOptionEnabled($bucket, $id);
        }
        return $default;
    };
    $nearbyActorsIncludeBasicSummary = $nearbyContextOptionEnabled('enabled_nearby_actor_subsections', 'basic_summary');
    $nearbyActorsIncludeAppearance = $nearbyContextOptionEnabled('enabled_nearby_actor_subsections', 'appearance');
    $nearbyActorsIncludeEquipment = $nearbyContextOptionEnabled('enabled_nearby_actor_subsections', 'equipment');
    $nearbyActorsEquipmentDescriptions = $nearbyContextOptionEnabled('enabled_nearby_actor_subsections', 'equipment_descriptions');
    $nearbyActorsIncludeActivity = $nearbyContextOptionEnabled('enabled_nearby_actor_subsections', 'current_activity');
    $nearbyActorsIncludePower = $nearbyContextOptionEnabled('enabled_nearby_actor_subsections', 'power_awareness');
    $nearbyActorsIncludeFactions = $nearbyContextOptionEnabled('enabled_nearby_actor_subsections', 'factions');
    $nearbyActorsIncludeCustomState = $nearbyContextOptionEnabled('enabled_nearby_actor_subsections', 'custom_state');
    
    // Track seen faction descriptions to avoid duplicates
    $seenFactionFormIDs = [];
    $factionDescriptions = []; // Store unique faction descriptions
    
    // Actors
    if ($actorsInRange && $addNPCDescriptions) {
        $actorDetailedListWithProfile=[];
        foreach ($actorDetailedList as $actor) {
            if (empty($actor))
                continue;
            if ($excludeBusy)
                if ((strpos($actor,"(busy)")>0)||(strpos($actor,"(dead)")>0))
                    continue;

            $actorName=trim(str_replace("(far away)","",$actor));
            if ($actorName==$GLOBALS["HERIKA_NAME"]) 
                continue;

            /* if (!(strpos($GLOBALS["HERIKA_NAME"],"actor")===false)) { // debug
                Logger::warn("DataLastInfoFor: unexpected value for HERIKA_NAME={$GLOBALS["HERIKA_NAME"]} | actor={$actor} actorname={$actorName} ");
            } */

            if ((strpos($actor,"(")===false) && ($GLOBALS["HERIKA_NAME"]!="The Narrator") && (strpos($GLOBALS["HERIKA_NAME"],"actor")===false)) {   
                $interactions=DirectConversationsWith($actor);
                if ($interactions==0) {
                    $ittext="{$actor} ({$GLOBALS["HERIKA_NAME"]} never talked to {$actorName} before, {$GLOBALS["HERIKA_NAME"]} should speak to this person as to a stranger or traveler...)";
                } else if ($interactions<5) {
                    $ittext="{$actor} ({$GLOBALS["HERIKA_NAME"]} has talked to {$actorName} a couple of times before)";
                } else {
                    $ittext="{$actor}";
                }
            } else {
                $ittext="{$actor}";
            }

            if ($actor==$GLOBALS["PLAYER_NAME"]) {
                // Player - read from core_player table (don't reveal they're "the player character")
                $profileString = "$actor";
                
                try {
                    require_once(__DIR__ . DIRECTORY_SEPARATOR . "core" . DIRECTORY_SEPARATOR . "player.class.php");
                    $player = new Player();
                    $profileString = chimBuildPlayerProfileName($actor, $player);
                    $hasProfileBody = false;

                    $playerBio = chimNormalizePlayerProfileBio(ResolvePlayerBackstory($player), $actor);
                    $bioKnownByAll = filter_var((string)($player->get('bio_known_by_all') ?? ''), FILTER_VALIDATE_BOOLEAN);
                    $isNarrator = isset($GLOBALS["HERIKA_NAME"]) && strcasecmp((string)$GLOBALS["HERIKA_NAME"], "The Narrator") === 0;
                    if ($nearbyActorsIncludeBasicSummary && $playerBio !== "" && ($bioKnownByAll || $isNarrator)) {
                        $profileString .= ": " . trim($playerBio);
                        $hasProfileBody = true;
                    }

                    // Add appearance if available
                    $appearance = $player->get('appearance');
                    if ($nearbyActorsIncludeAppearance && !empty($appearance)) {
                        if ($hasProfileBody) {
                            $profileString .= ". Appearance: " . trim($appearance);
                        } else {
                            $profileString .= ": Appearance: " . trim($appearance);
                            $hasProfileBody = true;
                        }
                    }
                    
                    // Add equipment if available
                    $equipmentData = $player->getJson('equipment');
                    if ($nearbyActorsIncludeEquipment && is_array($equipmentData) && !empty($equipmentData)) {
                        $slots = chimEquipmentProfileSlotKeys();
                        $slots = chimProfileEquipmentSlotsFromData($equipmentData, $slots);
                        $equipmentParts = chimFormatProfileEquipmentParts($equipmentData, $slots, $nearbyActorsEquipmentDescriptions);
                        if (!empty($equipmentParts)) {
                            if ($hasProfileBody) {
                                $profileString .= ". Equipment: " . implode(", ", $equipmentParts);
                            } else {
                                $profileString .= ": Equipment: " . implode(", ", $equipmentParts);
                                $hasProfileBody = true;
                            }
                        }
                    }

                    if ($nearbyActorsIncludeCustomState) {
                        $profileExtra = chimBuildActorProfileEnrichmentText($actor, "player", [
                            "source" => "nearby_actors",
                        ]);
                        if ($profileExtra !== "") {
                            if ($hasProfileBody) {
                                $profileString .= ". " . $profileExtra;
                            } else {
                                $profileString .= ": " . $profileExtra;
                                $hasProfileBody = true;
                            }
                        }
                    }
                    
                    // Power Awareness: Add relative power assessment for player
                    if ($nearbyActorsIncludePower && isset($GLOBALS["POWER_AWARENESS_ENABLED"]) && $GLOBALS["POWER_AWARENESS_ENABLED"]) {
                        require_once(__DIR__ . DIRECTORY_SEPARATOR . "power_awareness.php");
                        
                        // Get player's level
                        $playerLevel = getPlayerLevel();
                        
                        // Get assessing actor's level (the NPC looking at the player)
                        if (!empty($GLOBALS["HERIKA_NAME"])) {
                            $assessorLevel = getNpcLevel($GLOBALS["HERIKA_NAME"]);
                            
                            if ($assessorLevel !== null && $playerLevel !== null) {
                                $powerComparison = calculatePowerComparison($assessorLevel, $playerLevel);
                                $profileString .= " ({$powerComparison})";
                            }
                        }
                    }
                    
                } catch (Exception $e) {
                    Logger::debug("Could not load player data for context: " . $e->getMessage());
                }
                
                // Don't append $ittext for player - profileString already starts with player name
                $actorDetailedListWithProfile[] = $profileString;
                
            } else {
                
                $actorName = preg_replace("/\s*\(.*?\)\s*/", "", $actor);
                $codename = npcNameToCodename($actorName);
                $npcMaster=new NpcMaster();
                $currentNpcData=$npcMaster->getByName($actorName);

                
                if (isset($currentNpcData["core"]) && !empty($currentNpcData["core"])) {
                    // NPC name should always be at core section.
                    $npcName = $currentNpcData["npc_name"];
                    error_log("[DataLastInfoFor] Actors around, Checking NPC Name: " . $npcName." actors in range: ".$actorsInRangeList);
                    // Format gender (capitalize first letter)
                    $gender = !empty($currentNpcData["gender"]) ? ucfirst(strtolower(trim($currentNpcData["gender"]))) : "";
                    $race = !empty($currentNpcData["race"]) ? trim($currentNpcData["race"]) : "";
                    
                    // Build name with race/gender in parentheses
                    $nameWithRaceGender = $npcName;
                    if (!empty($gender) && !empty($race)) {
                        $nameWithRaceGender .= " ({$gender} {$race})";
                    } elseif (!empty($race)) {
                        $nameWithRaceGender .= " ({$race})";
                    }
                    
                    // Check for reanimation status early to add to core
                    $extendedData = $npcMaster->getExtendedData($currentNpcData);
                    $reanimationText = "";
                    if (empty($GLOBALS["DISABLE_REANIMATION_TRACKING"]) && isset($extendedData["reanimated"]) && $extendedData["reanimated"] === true) {
                        $reanimationText = " This person has been reanimated from death as a zombie.";
                    }
                    
                    $profileString = $nearbyActorsIncludeBasicSummary
                        ? "{$nameWithRaceGender}: " . trim("{$currentNpcData["core"]}{$reanimationText}")
                        : "{$nameWithRaceGender}";
                    
                    // Add appearance if available
                    if ($nearbyActorsIncludeAppearance && !empty($currentNpcData["appearance"])) {
                        $profileString .= ". Appearance: " . trim($currentNpcData["appearance"]);
                    }
                    
                    // Add zombie appearance if reanimated
                    if ($nearbyActorsIncludeAppearance && empty($GLOBALS["DISABLE_REANIMATION_TRACKING"]) && isset($extendedData["reanimated"]) && $extendedData["reanimated"] === true) {
                        $zombieAppearance = "Their skin has a deathly pale, greyish pallor with a corpse-like appearance. Their eyes are glazed and lifeless, and their movements are stiff and unnatural";
                        if (!empty($currentNpcData["appearance"])) {
                            $profileString .= ". " . $zombieAppearance;
                        } else {
                            $profileString .= ". Appearance: " . $zombieAppearance;
                        }
                    }
                    
                    // Get metadata once for both scale and equipment
                    $metaData = $npcMaster->getMetaData($currentNpcData);
                    
                    // Add height description based on scale
                    if (isset($metaData["stats"]["scale"])) {
                        $heightDesc = getHeightDescription(floatval($metaData["stats"]["scale"]));
                        if (!empty($heightDesc)) {
                            $profileString .= ". " . $heightDesc;
                        }
                    }
                    
                    // Power Awareness: Add relative power assessment
                    if ($nearbyActorsIncludePower && isset($GLOBALS["POWER_AWARENESS_ENABLED"]) && $GLOBALS["POWER_AWARENESS_ENABLED"]) {
                        require_once(__DIR__ . DIRECTORY_SEPARATOR . "power_awareness.php");
                        
                        // Get current NPC's level
                        $npcLevel = isset($metaData["stats"]["level"]) ? intval($metaData["stats"]["level"]) : null;
                        
                        // Get assessing actor's level (the NPC looking at this person)
                        if (!empty($GLOBALS["HERIKA_NAME"])) {
                            $assessorLevel = getNpcLevel($GLOBALS["HERIKA_NAME"]);
                            
                            if ($assessorLevel !== null && $npcLevel !== null) {
                                $powerComparison = calculatePowerComparison($assessorLevel, $npcLevel);
                                $profileString .= " ({$powerComparison})";
                            }
                        }
                    }

                    $activityStatus = chimNormalizeActivityStatus($metaData);
                    if ($nearbyActorsIncludeActivity && !empty($activityStatus['fresh']) && !empty($activityStatus['summary'])) {
                        $profileString .= ". Current activity: " . $activityStatus['summary'];
                    }
                    
                    // Add equipment if available
                    if ($nearbyActorsIncludeEquipment && isset($metaData["equipment"]) && is_array($metaData["equipment"])) {
                        $slots = chimEquipmentProfileSlotKeys();
                        $equipmentParts = chimFormatProfileEquipmentParts($metaData["equipment"], $slots, $nearbyActorsEquipmentDescriptions);
                        if (!empty($equipmentParts)) {
                            $profileString .= ". Equipment: " . implode(", ", $equipmentParts);
                        }
                        
                        // Check if humanoid NPC has no body armor - if so, note they're naked
                        $humanoidRaces = ['nord', 'imperial', 'breton', 'redguard', 'orc', 'orsimer', 
                                        'altmer', 'highelf', 'bosmer', 'woodelf', 'dunmer', 'darkelf', 
                                        'argonian', 'khajiit', 'khajit'];
                        $npcRace = isset($currentNpcData["race"]) ? strtolower(trim($currentNpcData["race"])) : '';
                        
                        if ($npcRace && in_array($npcRace, $humanoidRaces) && !chimEquipmentHasBodyCoverage($metaData["equipment"])) {
                            $profileString .= ". Naked (no body armor/clothing worn)";
                        }
                    }

                    if ($nearbyActorsIncludeCustomState) {
                        $profileExtra = chimBuildActorProfileEnrichmentText($npcName, "npc", [
                            "source" => "nearby_actors",
                            "metadata" => $metaData,
                            "npc_data" => $currentNpcData,
                        ]);
                        if ($profileExtra !== "") {
                            $profileString .= ". " . $profileExtra;
                        }
                    }
                    
                    // Add faction information after equipment
                    $extendedData = $npcMaster->getExtendedData($currentNpcData);
                    if ($nearbyActorsIncludeFactions && isset($extendedData['factions']) && is_array($extendedData['factions']) && count($extendedData['factions']) > 0) {
                        $factionNames = [];
                        foreach ($extendedData['factions'] as $faction) {
                            if (isset($faction['formid'])) {
                                // Lookup faction using helper function (supports XX prefix)
                                $factionRecord = lookupDescriptionByFormID($faction['formid']);
                                
                                // Only add if found in descriptions table
                                if ($factionRecord && !empty($factionRecord['name'])) {
                                    $factionNames[] = $factionRecord['name'];
                                    
                                    // Track faction description (only once)
                                    if (!in_array($faction['formid'], $seenFactionFormIDs)) {
                                        $seenFactionFormIDs[] = $faction['formid'];
                                        if (!empty($factionRecord['description'])) {
                                            $factionDescriptions[$factionRecord['name']] = $factionRecord['description'];
                                        }
                                    }
                                }
                            }
                        }
                        
                        if (!empty($factionNames)) {
                            $profileString .= ". Groups " . implode(", ", $factionNames);
                        }
                    }
                    
                    $actorDetailedListWithProfile[] = $profileString;

                }
                else {
                    error_log("[DataLastInfoFor] Actors around, Checking NPC Name: " . $ittext. " with no profile data, actors in range: ".$actorsInRangeList);
                    $actorDetailedListWithProfile[] = $ittext;
                }
                
            }
        }
        $actorDetailedListWithProfileSanitized=[];
        foreach ($actorDetailedListWithProfile as $e)
            if (!empty($e))
                $actorDetailedListWithProfileSanitized[]=$e;

        if (!empty($actorDetailedListWithProfileSanitized))
            $actorsInRange=implode("\n## ",$actorDetailedListWithProfileSanitized);
        else 
            $actorsInRange="\nNo more actors in scene";// Catch
    }

    
    //Followers

    foreach (json_decode(DataGetCurrentPartyConf(),JSON_OBJECT_AS_ARRAY) as $followername=>$followerdata) {
        if (!$followername)
            continue;

        if ($followername==$GLOBALS["PLAYER_NAME"]) {
            $followers[]="$followername (roleplayed by player)";

        } else {
            if (isset($followerdata["core"]))
                $followers[]="{$followerdata["core"]} level {$followerdata["level"]},{$followerdata["gender"]} {$followerdata["race"]}".(($followerdata["isVampire"]=="yes")?", is vampire":"");
            else
                $followers[]="$followername, level {$followerdata["level"]},{$followerdata["gender"]} {$followerdata["race"]}".(($followerdata["isVampire"]=="yes")?", is vampire":"");
            
            $followersV2[]=$followername;

        }
            
    }

    $followers[]="{$GLOBALS["PLAYER_NAME"]}";
    $followersV2[]=$GLOBALS["PLAYER_NAME"];

    if (!isset($GLOBALS["PROMPT_NEARBY_SECTIONS"])) {
        $GLOBALS["PROMPT_NEARBY_SECTIONS"] = "";
    }
    if (function_exists("chimBuildCurrentTurnPresentPeoplePrompt")) {
        $peoplePresentPrompt = chimBuildCurrentTurnPresentPeoplePrompt();
        if ($peoplePresentPrompt !== "") {
            $GLOBALS["PROMPT_NEARBY_SECTIONS"] .= "\n" . $peoplePresentPrompt;
        }
    }
    $GLOBALS["PROMPT_NEARBY_SECTIONS"] .= "\n<nearby_actors>\n# NEARBY ACTORS/NPC IN THE SCENE \n## $actorsInRange\n</nearby_actors>";
    
    // Add faction descriptions section if any factions were found
    if (!empty($factionDescriptions)) {
        $factionDescText = "";
        foreach ($factionDescriptions as $name => $desc) {
            $factionDescText .= "## {$name}: {$desc}\n";
        }
        $GLOBALS["PROMPT_NEARBY_SECTIONS"] .= "\n<group_descriptions>\n# GROUP/FACTION DESCRIPTIONS\n{$factionDescText}</group_descriptions>";
    }
    
    // Add nearby items to context if available
    $nearbyItemsIncludeDescriptions = $nearbyContextOptionEnabled('enabled_nearby_item_subsections', 'item_descriptions');
    $nearbyItemsGroupDuplicates = $nearbyContextOptionEnabled('enabled_nearby_item_subsections', 'group_duplicates', false);
    $itemsInRange = DataItemsInCloseRange();
    
    if (!empty($itemsInRange)) {
        $itemsList = explode(',', $itemsInRange);
        $formattedItems = [];
        $seenBaseIDs = [];
        $itemDescriptions = [];
        $groupedItems = [];
        $playerName = $GLOBALS["PLAYER_NAME"] ?? "Player";
        $playerLookingTag = " ({$playerName} is looking at this)";
        $shorterNearbyItemList = $nearbyItemsGroupDuplicates;
        
        foreach ($itemsList as $item) {
            $trimmedItem = trim($item);
            if (empty($trimmedItem)) continue;
            
            // Parse format: "0xRefID:0xBaseID:ItemName" (new) or "0xRefID:ItemName" (old)
            $parts = explode(':', $trimmedItem, 3);
            
            if (count($parts) >= 3) {
                // New format with BaseID
                $refID = $parts[0];
                $baseID = $parts[1];
                $itemName = $parts[2];
                
                // Strip prompt-only tags for blacklist and description lookup.
                $itemNameClean = str_replace([' (STEALING)', $playerLookingTag], '', $itemName);
                
                // Skip blacklisted items
                if (isItemBlacklisted($itemNameClean)) {
                    continue;
                }
                
                // Track unique base IDs for descriptions
                $hasDescription = false;
                if (!in_array($baseID, $seenBaseIDs)) {
                    $seenBaseIDs[] = $baseID;
                    
                    // Look up description through the shared runtime/stable/legacy resolver
                    $descRecord = lookupDescriptionByFormID($baseID);
                    
                    if ($descRecord && !empty($descRecord['description'])) {
                        // Store description under clean name (without STEALING tag)
                        if ($nearbyItemsIncludeDescriptions) {
                            $itemDescriptions[$itemNameClean] = $descRecord['description'];
                        }
                        $hasDescription = true;
                    }
                }
                
                // If filter is enabled and item has no description, skip it
                if (isset($GLOBALS["GROUND_ITEMS_DESCRIPTIONS_ONLY"]) && $GLOBALS["GROUND_ITEMS_DESCRIPTIONS_ONLY"] && !$hasDescription) {
                    continue;
                }
                
                if ($shorterNearbyItemList) {
                    $groupKey = $itemName;
                    if (!isset($groupedItems[$groupKey])) {
                        $groupedItems[$groupKey] = [
                            'count' => 0,
                            'sample_refid' => $refID,
                            'sample_item_name' => $itemName,
                        'description' => $nearbyItemsIncludeDescriptions ? ($itemDescriptions[$itemNameClean] ?? '') : '',
                        ];
                    }
                    $groupedItems[$groupKey]['count']++;
                    if ($nearbyItemsIncludeDescriptions && empty($groupedItems[$groupKey]['description']) && !empty($itemDescriptions[$itemNameClean])) {
                        $groupedItems[$groupKey]['description'] = $itemDescriptions[$itemNameClean];
                    }
                } else {
                    // Format for display: "RefID:ItemName" (hide BaseID from NPC, keep STEALING tag)
                    $displayItem = "{$refID}:{$itemName}";
                    $formattedItems[] = $displayItem;
                }
            } elseif (count($parts) == 2) {
                // Old format without BaseID - just use as-is
                $refID = $parts[0];
                $itemName = $parts[1];
                
                // Strip prompt-only tags for blacklist checks.
                $itemNameClean = str_replace([' (STEALING)', $playerLookingTag], '', $itemName);
                
                // Skip blacklisted items
                if (isItemBlacklisted($itemNameClean)) {
                    continue;
                }
                
                if ($shorterNearbyItemList) {
                    $groupKey = $itemName;
                    if (!isset($groupedItems[$groupKey])) {
                        $groupedItems[$groupKey] = [
                            'count' => 0,
                            'sample_refid' => $refID,
                            'sample_item_name' => $itemName,
                            'description' => '',
                        ];
                    }
                    $groupedItems[$groupKey]['count']++;
                } else {
                    // Keep STEALING tag in display
                    $displayItem = "{$refID}:{$itemName}";
                    $formattedItems[] = $displayItem;
                }
            }
        }

        if ($shorterNearbyItemList && !empty($groupedItems)) {
            foreach ($groupedItems as $group) {
                $formattedItems[] = "{$group['count']}x {$group['sample_item_name']}";
            }
        }
        
        if (!empty($formattedItems)) {
            $itemsText = implode("\n## ", $formattedItems);
            
            // Add descriptions for unique items if available
            $descriptionText = "";
            if ($nearbyItemsIncludeDescriptions && $shorterNearbyItemList) {
                $descParts = [];
                foreach ($groupedItems as $group) {
                    if (!empty($group['description'])) {
                        $descParts[] = "{$group['sample_refid']}:{$group['sample_item_name']}: {$group['description']}";
                    }
                }
                if (!empty($descParts)) {
                    $descriptionText = "\n\n# ITEM DESCRIPTIONS\n## " . implode("\n## ", $descParts);
                }
            } elseif ($nearbyItemsIncludeDescriptions && !empty($itemDescriptions)) {
                $descParts = [];
                foreach ($itemDescriptions as $name => $desc) {
                    $descParts[] = "{$name}: {$desc}";
                }
                $descriptionText = "\n\n# ITEM DESCRIPTIONS\n## " . implode("\n## ", $descParts);
            }

            $nearbyItemsHeader = $shorterNearbyItemList
                ? "# NEARBY ITEMS (grouped unique counts; use representative RefID from ITEM DESCRIPTIONS for PickupItem)"
                : "# NEARBY ITEMS (format: RefID:ItemName)";
            $contextContent = "<nearby_items>\n{$nearbyItemsHeader}\n## {$itemsText}{$descriptionText}\n</nearby_items>";
            if (!isset($GLOBALS["PROMPT_NEARBY_SECTIONS"])) {
                $GLOBALS["PROMPT_NEARBY_SECTIONS"] = "";
            }
            $GLOBALS["PROMPT_NEARBY_SECTIONS"] .= "\n" . $contextContent;
        }
    }

    $heldItemsContext = HeldItems::getHeldItemsContext();
    if (!empty($heldItemsContext)) {
        if (!isset($GLOBALS["PROMPT_NEARBY_SECTIONS"])) {
            $GLOBALS["PROMPT_NEARBY_SECTIONS"] = "";
        }
        $GLOBALS["PROMPT_NEARBY_SECTIONS"] .= "\n" . $heldItemsContext;
    }
    
    /*
    if (!isset($GLOBALS["IS_NPC"]) || !$GLOBALS["IS_NPC"])
        $lastDialog[] = array('role' => 'user', 'content' => "# PARTY STATUS\n## ". (implode("\n## ",$followers)));
    else 
        $lastDialog[] = array('role' => 'user', 'content' => "# YOU'RE NOT PART OF THE GROUP FORMED BY\n## ". (implode("\n## ",$followers)));

    $arr_poi = DataPosibleLocationsToGo();
    if (isset($arr_poi) && is_array($arr_poi) && (count($arr_poi) > 0)) {
        $lastDialog[] = array('role' => 'user', 'content' => "# POIs - Points of Interest nearby \n## ". (implode("\n## ",$arr_poi)));
    }
    */
    if (!empty($followersV2)) {
        $lastFollower = array_pop($followersV2);
        if (!empty($followersV2)) {
            $followersString = implode(", ", $followersV2) . " and " . $lastFollower;
        } else {
            $followersString = $lastFollower;
        }
    } else {
        $followersString = "";
    }

	if ($followersString!=$GLOBALS["PLAYER_NAME"] && !empty($followersString)) {
	    if (!isset($GLOBALS["PROMPT_NEARBY_SECTIONS"])) {
	        $GLOBALS["PROMPT_NEARBY_SECTIONS"] = "";
	    }
	    $GLOBALS["PROMPT_NEARBY_SECTIONS"] .= "\n<adventuring_party>
        # ADVENTURING PARTY
	     $followersString are together as an **adventuring party**, acting as close companions.
	     - The others **can know each other**, but they are **not part** of {$followersString}'s group.
	     - Generally speaking, any mention of **plans, missions, or objectives** refers **only to the adventuring party**, never to the other NPCs.
	     </adventuring_party>";
	}
    $arr_poi = DataPosibleLocationsToGo();
    if (isset($arr_poi) && is_array($arr_poi) && (count($arr_poi) > 0)) {
        // Filter blacklisted locations
        if (isset($GLOBALS["LOCATION_BLACKLIST"]) && !empty($GLOBALS["LOCATION_BLACKLIST"])) {
            $blacklistedLocations = array_map('trim', explode(',', strtolower($GLOBALS["LOCATION_BLACKLIST"])));
            $arr_poi = array_filter($arr_poi, function($poi) use ($blacklistedLocations) {
                $poiLower = strtolower($poi);
                foreach ($blacklistedLocations as $blacklistedLocation) {
                    if (!empty($blacklistedLocation) && strpos($poiLower, $blacklistedLocation) !== false) {
                        return false;
                    }
                }
                return true;
            });
        }
        
        if (count($arr_poi) > 0) {
            if (!isset($GLOBALS["PROMPT_NEARBY_SECTIONS"])) {
                $GLOBALS["PROMPT_NEARBY_SECTIONS"] = "";
            }
            $GLOBALS["PROMPT_NEARBY_SECTIONS"] .= "\n<points_of_interest>\n# POIs - Points of Interest nearby \n## ". (implode("\n## ",$arr_poi))."\n</points_of_interest>";
        }
    }
    
    
 
    // Rolemaster notes
    
    $timeCut=time();
    $rolemasterNotes=$GLOBALS["db"]->fetchAll("SELECT data FROM rolemaster where type='scenenote' and localts+ttl>$timeCut order by localts asc");
    if (is_array($rolemasterNotes) && !empty($rolemasterNotes)) {
        $notes=[];
        foreach ($rolemasterNotes as $note)
            $notes[]= $note["data"];
        if (!isset($GLOBALS["PROMPT_NEARBY_SECTIONS"])) {
            $GLOBALS["PROMPT_NEARBY_SECTIONS"] = "";
        }
        $GLOBALS["PROMPT_NEARBY_SECTIONS"] .= "\n<scene_notes>\n# SCENE NOTES \n## ".implode(".",$notes)."</scene_notes>";
    }
        
    // This is intended to give info about nearby actors, ALL actors (dead ones included).

    $nearbyActors=$excludeFarAway ? trim($actorsInRangeList, "|") : DataBeingsOrDeathsInRangeExcluding("",true);
    $nearbyActorsList=[];
    if ($nearbyActors) {
        foreach (explode("|",$nearbyActors) as $k=>$v) {
            $nearbyActor=trim($v);
            if (!empty($nearbyActor))
                $nearbyActorsList[]=$nearbyActor;
        }
        $GLOBALS["PROMPT_NEARBY_SECTIONS"] .= "\n<actors_nearby>\n" . implode(", ", $nearbyActorsList) . "\n</actors_nearby>";
    }

    $visualContext = chimBuildVisualContextPrompt(DataLastKnownLocation());
    if ($visualContext !== '') {
        if (!isset($GLOBALS["PROMPT_NEARBY_SECTIONS"])) {
            $GLOBALS["PROMPT_NEARBY_SECTIONS"] = "";
        }
        $GLOBALS["PROMPT_NEARBY_SECTIONS"] .= "\n" . $visualContext;
    }
    

    $lastDialog=[];
    // This function originally returned an array, now it's directly filling PROMPT_NEARBY_SECTIONS.
    // MUST return an array, even if empty; Review where is called to ensure it's handled properly
    // Proposal: $lastDialog[]=array('role' => 'user', 'content' => $GLOBALS["PROMPT_NEARBY_SECTIONS"]);
    return $lastDialog;

}

function DataLocationsAround($current_location = "") {
    global $db;

    $s_res = "";

    if (strlen($current_location) > 0) {
        $s_loc = $db->escape(strtolower(trim($current_location))); 
        $s_sql = "SELECT data FROM eventlog WHERE (type in ('infoloc')) AND (data ILIKE '(Context location: {$s_loc}%') ORDER BY gamets DESC, ts DESC LIMIT 1";
    } else {
        $s_sql = "SELECT data FROM eventlog WHERE (type in ('infoloc')) AND (data ILIKE '(Context location:%') ORDER BY gamets DESC, ts DESC LIMIT 1";
    }
    $results = $db->fetchAll($s_sql);
    foreach ($results as $row) {
        $re = '/(to go:)(.+),,/';
        preg_match($re, $row["data"], $matches, PREG_OFFSET_CAPTURE, 0);
        if (isset($matches[2][0])) {
            $s_res .= $matches[2][0];
        }
        break;
    }
    
    return $s_res;
} 

function ParseNpcCloseActorNames($data)
{
    $beings = strtr((string)$data, ["beings in range:" => ""]);
    $beingsArray = explode("/", $beings);
    $retData = [];

    foreach ($beingsArray as $v) {
        $v = trim(preg_replace('/\s*\([^)]*\)/', '', $v));
        if (empty($v)) {
            continue;
        }
        if (strpos($v, "Horse") === 0 || strpos($v, "Chicken") === 0) {
            continue;
        }

        $retData[$v] = $v;
    }

    return array_values($retData);
}

function DataPosibleLocationsToGo()
{
    if (isset($GLOBALS["CACHE_POSIBLE_LOCATIONS_TO_GO"])) {
        return $GLOBALS["CACHE_POSIBLE_LOCATIONS_TO_GO"];
    }

    global $db;
    $lastDialogFull = array();
    $results = $db->fetchAll("select  a.data  as data  FROM  eventlog a 
    WHERE type in ('infoloc')  order by gamets desc,ts desc LIMIT 1 OFFSET 0");
    $lastData = "";
    $retData = [];
    foreach ($results as $row) {
        //$row = $results->fetchArray();

        $re = '/(to go:)(.+),,/';

        preg_match($re, $row["data"], $matches, PREG_OFFSET_CAPTURE, 0);
        if (isset($matches[2])) {
            $retData = explode(",", $matches[2][0]);
        }
        ;
        break;
    }

    // Location blacklist // $LOCATION_BLACKLIST
    if (isset($GLOBALS["LOCATION_BLACKLIST"]) && (strlen($GLOBALS["LOCATION_BLACKLIST"])>0)) {
        $LOCATION_BLACKLIST_ARRAY = explode(",", $GLOBALS["LOCATION_BLACKLIST"]); 
        //$LOCATION_BLACKLIST_ARRAY = empty($GLOBALS["LOCATION_BLACKLIST"]) ? [] : explode(",", $GLOBALS["LOCATION_BLACKLIST"]); 
        if (count($LOCATION_BLACKLIST_ARRAY) > 0) {
            foreach ($retData as $k => $v) {
                foreach ($LOCATION_BLACKLIST_ARRAY as $blacklistedLocation) {
                    $blacklistedLocationTrimmed = trim($blacklistedLocation);
                    if (!empty($blacklistedLocationTrimmed) && (stripos($v, $blacklistedLocationTrimmed) !== false)) {
                        unset($retData[$k]);
                        break; // No need to check other blacklisted locations
                    }
                }
            }
        }
    }
    
    foreach ($retData as $k => $v) {
        if ($v=="Skyrim") {
            $retData[$k].=" (exit)";
        }
    }
    //print_r($matches);
    // ? this part with 'Herika can see this beings in range:' seems outdated 
    /* $results = $db->fetchAll("select  a.data  as data  FROM  eventlog a 
    WHERE type in ('infonpc')  order by gamets desc,ts desc LIMIT 50 OFFSET 0");
    $lastData = "";
    $matches = [];
    foreach ($results as $row) {
        //$row = $results->fetchArray();

        $pattern = "/Herika can see this beings in range:(.*)/";
        preg_match_all($pattern, $row["data"], $matches);

        if (!empty($matches) && !empty($matches[1]) && isset($matches[1][0])) {
            $retData = array_merge($retData, explode(",", $matches[1][0]));
        }

        //print_r($matches);
        break;
    }

    foreach ($retData as $k => $v) {
        if (strlen($v) < 2) {
            unset($retData[$k]);
        } else {
            $retData[$k] = preg_replace("/\([^)]+\)/", '', $v);
            //$retData[$k]=$v;
            $retData[$k]=trim($retData[$k]);
        }
        
    }     */
    //return ["Goldenglow Estate","Faldar's Tooth","Goldenglow Estate Sewer","Pit Wolf(dead)","Pit Wolf(dead)","Herika"];
    //error_log("DataPosibleLocationsToGo: ".print_r($retData,true));
    $GLOBALS["CACHE_POSIBLE_LOCATIONS_TO_GO"] = array_values($retData);
    return $GLOBALS["CACHE_POSIBLE_LOCATIONS_TO_GO"];
}

function DataPosibleMoveToTargets()
{
    if (isset($GLOBALS["CACHE_POSIBLE_MOVETO_TARGETS"])) {
        return $GLOBALS["CACHE_POSIBLE_MOVETO_TARGETS"];
    }

    global $db;
    $results = $db->fetchAll("SELECT a.data AS data FROM eventlog a WHERE type IN ('infonpc_close') ORDER BY gamets DESC, ts DESC LIMIT 1 OFFSET 0");
    $retData = [];

    if (is_array($results) && isset($results[0]["data"])) {
        $retData = ParseNpcCloseActorNames($results[0]["data"]);
    }

    $GLOBALS["CACHE_POSIBLE_MOVETO_TARGETS"] = array_values($retData);
    return $GLOBALS["CACHE_POSIBLE_MOVETO_TARGETS"];
}

function DataPosibleLocationsToGoWide()
{
    if (isset($GLOBALS["CACHE_POSIBLE_LOCATIONS_TO_GO_WIDE"])) {
        return $GLOBALS["CACHE_POSIBLE_LOCATIONS_TO_GO_WIDE"];
    }

    global $db;
    $lastDialogFull = array();
    $r=[];
    $results = $db->fetchOne("select  a.data  as data  FROM  eventlog a 
    WHERE type in ('region')  order by gamets desc,ts desc LIMIT 1 OFFSET 0");

    if ($results) {
        $regCn=$db->escape(trim($results["data"]));
        error_log("select  name  FROM  locations_v where region ilike '{$regCn}'");
        $locs = $db->fetchAll("select  name,tags  FROM  locations_v where region ilike '{$regCn}'");
        
        foreach ($locs as $loc) {
            if ($loc["tags"])
                $r[$loc["name"]]=$loc["tags"];
            else
                $r[$loc["name"]]="";

        }
        
    } else {
        
        $locs = $db->fetchAll("SELECT L.name,L.tags, 
                L.coords <-> P.coords AS distance
            FROM locations_v L
            CROSS JOIN (
                SELECT B.coords
                FROM public.named_cell A
                LEFT JOIN locations_v B ON B.formid = A.location_id
                WHERE A.id = 0
            ) AS P
            WHERE L.coords <-> P.coords < 15000
            ORDER BY distance ASC
        ");
        
        foreach ($locs as $loc) {
            if ($loc["tags"])
                $r[$loc["name"]]=$loc["tags"];
            else
                $r[$loc["name"]]="";

        }
        $GLOBALS["CACHE_POSIBLE_LOCATIONS_TO_GO_WIDE"] = $r;
    }

    $GLOBALS["CACHE_POSIBLE_LOCATIONS_TO_GO_WIDE"] = $r;
    return $r;

}

function DataPosibleInspectTargets($pack=true)
{
    if (isset($GLOBALS["CACHE_POSIBLE_INSPECT_TARGETS"][(int)$pack])) {
        return $GLOBALS["CACHE_POSIBLE_INSPECT_TARGETS"][(int)$pack];
    }

    global $db;
    $results = $db->fetchAll("select  a.data  as data  FROM  eventlog a 
    WHERE type in ('infonpc')  order by gamets desc,ts desc LIMIT 50 OFFSET 0");
    $lastData = "";
    $matches = [];
    foreach ($results as $row) {
        //$row = $results->fetchArray();

        $pattern = "/beings in range:(.*)/";
        preg_match_all($pattern, $row["data"], $matches);

        if (!empty($matches) && !empty($matches[1]) && isset($matches[1][0])) {
            $retData = explode(",", $matches[1][0]);
        }


        break;
    }

    
    
    if (!isset($retData)||!is_array($retData)) {
        $retData = [];
    }

    $compData=[];

    if ($pack) {
        foreach ($retData as $k => $v) {
            if (strlen($v) < 2) {
                unset($retData[$k]);
            } else {
                $retData[$k] = preg_replace("/\([^)]+\)/", '', $v);
                $retData[$k] = $v;
                if (!isset($compData[$v]))
                    $compData[$v]=0;
                $compData[$v]++; // Reduce same names (Chicken, Chicken -> Chicken)
                //$retData[$k]=$v;

            }

        }
        $retData=[];
        foreach ($compData as $l=>$n) {
            if ($n==1)
                $retData[]="$l";
            else
                $retData[]="$n $l";
        }

        
    }

    $GLOBALS["CACHE_POSIBLE_INSPECT_TARGETS"][(int)$pack] = array_values($retData);
    return $GLOBALS["CACHE_POSIBLE_INSPECT_TARGETS"][(int)$pack];
}

function chimDescribeConditionStat(string $kind, float $cur, float $max): string
{
    if ($max <= 0) {
        return "Unknown";
    }

    $pct = ($cur < 0 ? 0.0 : ($cur > $max ? $max : $cur)) / $max * 100.0;
    if ($kind === 'health') {
        if ($pct >= 75.0) {
            return "Near full health";
        }
        if ($pct >= 50.0) {
            return "Wounded";
        }
        if ($pct >= 25.0) {
            return "Badly wounded";
        }
        return "On the brink of collapse";
    }

    if ($kind === 'magicka') {
        if ($pct >= 75.0) {
            return "Magicka reserves strong";
        }
        if ($pct >= 50.0) {
            return "Magicka reserves middling";
        }
        if ($pct >= 25.0) {
            return "Magicka reserves low";
        }
        return "Magicka nearly drained";
    }

    if ($pct >= 75.0) {
        return "Well-rested";
    }
    if ($pct >= 50.0) {
        return "Winded";
    }
    if ($pct >= 25.0) {
        return "Exhausted";
    }
    return "Spent";
}

function chimBuildCurrentConditionLinesFromMetadata($stats, array $metadata = [])
{
    $lines = [];

    if (is_array($stats) && !empty($stats)) {
        $h = chimDescribeConditionStat('health', (float)($stats['health'] ?? 0), (float)($stats['health_max'] ?? 0));
        $m = chimDescribeConditionStat('magicka', (float)($stats['magicka'] ?? 0), (float)($stats['magicka_max'] ?? 0));
        $st = chimDescribeConditionStat('stamina', (float)($stats['stamina'] ?? 0), (float)($stats['stamina_max'] ?? 0));

        if ($h !== 'Unknown') {
            $lines[] = "  • Health: {$h}";
        }
        if ($m !== 'Unknown') {
            $lines[] = "  • Magicka: {$m}";
        }
        if ($st !== 'Unknown') {
            $lines[] = "  • Stamina: {$st}";
        }
    }

    $lines = array_merge($lines, chimBuildTransformationStateConditionLines($metadata));

    return $lines;
}

function chimBuildCurrentConditionBlockFromMetadata($stats, array $metadata = [])
{
    $lines = chimBuildCurrentConditionLinesFromMetadata($stats, $metadata);
    if (empty($lines)) {
        return '';
    }

    return "<condition>\n#Condition\n" . implode("\n", $lines) . "\n</condition>";
}

function chimBuildNpcInspectSummary(string $npcName)
{
    $npcName = trim($npcName);
    if ($npcName === '') {
        return '';
    }

    $npcMaster = new NpcMaster();
    $currentNpcData = $npcMaster->getByName($npcName);
    if (!is_array($currentNpcData) || empty($currentNpcData)) {
        return '';
    }

    $metaData = $npcMaster->getMetaData($currentNpcData);
    if (!is_array($metaData)) {
        $metaData = [];
    }

    $sections = [];

    $conditionBlock = chimBuildCurrentConditionBlockFromMetadata($metaData['stats'] ?? null, $metaData);
    if ($conditionBlock !== '') {
        $sections[] = $conditionBlock;
    }

    $activityStatus = chimNormalizeActivityStatus($metaData);
    if (!empty($activityStatus['summary'])) {
        $sections[] = "<activity>\n#Activity\n" . ucfirst($activityStatus['summary']) . ".\n</activity>";
    }

    return implode("\n", $sections);
}

function DataQuestJournal($quest)
{
    global $db;
    if (empty($quest)||($quest=="None")||true) {
        
        $results = $db->fetchAll("SElECT name,id_quest,briefing,briefing2 as notes, 'pending' as status FROM quests");
        $finalRow = [];
        foreach ($results as $row) {
            if (isset($finalRow[$row["id_quest"]])) {
                continue;
            } else {
                $finalRow[$row["id_quest"]] = ["name"=>$row["name"],"briefing"=>$row["briefing"],"personal notes"=>$row["notes"]];
            }
        }

        if (sizeof($finalRow) == 0) {
            $data[] = "no active quests";
        } else {
            $data = array_values($finalRow);
        }

        $extraData = DataGetCurrentTask();

        $data[] = ["side note" => "$extraData"];

        return json_encode($data);

    } else {
        $lastDialogFull = array();
        $results = $db->fetchAll("SElECT  name,id_quest,briefing,data
      FROM quests where lower(id_quest)=lower('$quest') or lower(name)=lower('$quest') ");
        $lastOne = -1;
        $data = array();
        if (!$results) {
            $data["error"] = "quest not found, make sure you use id_quest";
            return json_encode($data);

        }
        foreach ($results as $row) {
            $lastOne++;
            $data[] = $row;
        }
        if ($lastOne >= 0) {
            $data[$lastOne]["stage_completed"] = "no";
        }

        if (sizeof($data) == 0) {
            $data["error"] = "quest not found, make sure you use id_quest";

        }

        return json_encode($data);

    }
}

function removeTalkingToOccurrences($input) {
    $pattern = '/\((?:(?:talking|whispering|shouting)|speaking privately)\s+to\s+[^()]+\)/i';
    preg_match_all($pattern, $input, $matches, PREG_OFFSET_CAPTURE);

    // Get all positions of the matches
    $positions = $matches[0];

    // If there are no matches or only one match, return the input string as it is
    if (count($positions) <= 1) {
        return $input;
    }

    // Remove all but the last occurrence
    for ($i = 0; $i < count($positions) - 1; $i++) {
        $pos = $positions[$i][1];
        $input = substr_replace($input, '', $pos, strlen($positions[$i][0]));
        
        // After each removal, adjust the positions of subsequent matches
        for ($j = $i + 1; $j < count($positions); $j++) {
            $positions[$j][1] -= strlen($positions[$i][0]);
        }
    }

    return $input;
}

function moveDialogueTargetSuffixToEnd($input) {
    $input = trim((string)$input);
    if ($input === "") {
        return "";
    }

    $pattern = '/\s*(\((?:(?:talking|whispering|shouting)|speaking privately)\s+to [^()]+?\)|\(speaking loudly to [^()]+?\))\s*/i';
    if (preg_match_all($pattern, $input, $matches) !== 1 || empty($matches[1])) {
        return trim(preg_replace('/\s+/', ' ', $input));
    }

    $targetSuffix = trim((string)end($matches[1]));
    $withoutSuffix = preg_replace($pattern, ' ', $input);
    $withoutSuffix = trim(preg_replace('/\s+/', ' ', (string)$withoutSuffix));
    if ($withoutSuffix === "") {
        return $targetSuffix;
    }

    return "{$withoutSuffix} {$targetSuffix}";
}


function DataLastDataExpandedForNPC($actor, $lastNelements = -10,$sqlfilter="") {

        global $db;

        $actorcn=$db->escape($actor);
        $results = $db->fetchAll("SELECT speaker,speech,listener,gamets,localts,'speech',gamets - LAG(gamets) OVER (ORDER BY gamets ASC) AS gamets_diff,location,ts
        FROM speech where companions like '%$actorcn%' order by ts desc LIMIT 1000 OFFSET 0");    
         $rawData=[];
        foreach ($results as $row) {
            $rawData[] = $row;
        }


        $orderedData = array_reverse($rawData);
        
        $lastDialogFull=[];
        
        $lastlocation="";
        $lastSpeaker=null;
        $lastListener=null;
        $buffer="";
        foreach ($orderedData as $speechEvent)  {
            
            if (($speechEvent["gamets_diff"] * 0.0000024) > 1.0) { // more than one hour
                $lastDialogFull[$speechEvent["ts"]] = array('role' => "user", 'content' => "The Narrator: about ".number_format(floor($speechEvent["gamets_diff"]*0.0000024),0)." hours later...");
            }

            
            if ($lastlocation!=$speechEvent["location"]) {
                $lastlocation=$speechEvent["location"];
                $lastDialogFull[$speechEvent["ts"]] = array('role' => "user", 'content' => "The Narrator: action moved to new location: $lastlocation");
            }

            $currentSpeaker="user";
            
            
            if ($lastSpeaker==$actor)
                $currentSpeaker="assistant";
            else if ($speechEvent["speaker"]=="The Narrator")
                continue;
            
            if (($lastSpeaker!=$speechEvent["speaker"])&&($lastSpeaker!=null)) {
                $talkingto="";
                if ($lastListener!="The Narrator")
                    $talkingto="(talking to {$lastListener})";
                
                if ($lastSpeaker==$GLOBALS["PLAYER_NAME"])
                    $talkingto="";

                $lastDialogFull[$speechEvent["ts"]] = array('role' => $currentSpeaker, 'content' => "$lastSpeaker: $buffer $talkingto");   
                $buffer="";
                $lastSpeaker=$speechEvent["speaker"];
            } else {
                $lastSpeaker=$speechEvent["speaker"];
            }
            $buffer.=$speechEvent["speech"];
            $lastListener=$speechEvent["listener"];

        }
        
        
        $results = $db->fetchAll("SELECT gamets,data,ts FROM eventlog where type in ('infoaction','itemfound') order by gamets desc LIMIT 10 OFFSET 0");    
        $rawData=[];
        foreach ($results as $row) {
            $lastDialogFull[$row["ts"]]= array('role' => 'user', 'content' => "The Narrator: {$row["data"]}");  
        }
        
        $results = $db->fetchAll("SELECT gamets,data,ts FROM eventlog where type in ('infoloc') order by gamets desc LIMIT 10 OFFSET 0");    
        $rawData=[];
        foreach ($results as $row) {
            $lastDialogFull[$row["ts"]]= array('role' => 'user', 'content' => "The Narrator: {$row["data"]}");  
        }

        ksort($lastDialogFull);
        
        $results = $db->fetchAll("SELECT gamets,data,ts
            FROM eventlog
            WHERE type in ('inputtext','inputtext_s','ginputtext','ginputtext_s','narrator_inputtext')
              AND people like '%$actorcn%'
            ORDER BY gamets desc, ts desc");
        $rawData=[];
        foreach ($results as $row) {
            $rawData[] = $row;
        }
        $rawData = array_reverse($rawData);
        foreach ($rawData as $row) {
            $lastDialogFull[] = array('role' => 'user', 'content' => "{$row["data"]}");
        }

       
                
        $orderedData = array_slice($lastDialogFull, $lastNelements);
        
        Logger::info("Using NPC data retriever");
        
        
        return $orderedData;
}

function removeEmptyElements(array $array): array {
    return array_filter($array, function($value) {
        return !empty($value) || $value === 0 || $value === "0"; 
    });
}

/**
 * Consolidate repeated similar events
 * 
 * @param array $events Array of event entries with role, content, subtype, type, gamets
 * @return array Consolidated array of events
 */
function consolidateEvents(array $events): array {
    // Hardcoded defaults - always enabled for efficiency
    $timeWindow = 300; // 5 minutes game time
    $typesToConsolidate = ["death", "itemfound", "rpg_word", "spellcast", "npcspellcast", "infoaction"];
    
    $consolidated = [];
    $consolidationBuffer = [];
    
    foreach ($events as $event) {
        if (!isset($event['type']) || !in_array($event['type'], $typesToConsolidate)) {
            // Flush buffer if we hit a non-consolidatable event
            if (!empty($consolidationBuffer)) {
                $consolidated = array_merge($consolidated, flushConsolidationBuffer($consolidationBuffer));
                $consolidationBuffer = [];
            }
            $consolidated[] = $event;
            continue;
        }
        
        // Extract pattern from event content
        $pattern = extractEventPattern($event);
        if ($pattern === null) {
            // Can't extract pattern, add as-is
            if (!empty($consolidationBuffer)) {
                $consolidated = array_merge($consolidated, flushConsolidationBuffer($consolidationBuffer));
                $consolidationBuffer = [];
            }
            $consolidated[] = $event;
            continue;
        }
        
        // Check if this event can be merged with buffer
        $merged = false;
        $actorName = extractActorName($event['content']);
        
        foreach ($consolidationBuffer as $key => &$buffered) {
            if ($buffered['pattern'] === $pattern) {
                // Check time window
                $timeDiff = abs(($event['gamets'] ?? 0) - ($buffered['first_gamets'] ?? 0));
                if ($timeDiff <= $timeWindow) {
                    // Check if this is a different actor doing the same action (e.g., combat engagement)
                    $isMultiActorPattern = (strpos($pattern, 'combat:') === 0 || strpos($pattern, 'activate:') === 0);
                    
                    if ($isMultiActorPattern && $actorName) {
                        // Multi-actor pattern: collect actor names
                        if (!isset($buffered['actors'])) {
                            $buffered['actors'] = [extractActorName($buffered['event']['content'])];
                        }
                        if (!in_array($actorName, $buffered['actors'])) {
                            $buffered['actors'][] = $actorName;
                        }
                    } elseif (strpos($pattern, 'itemfound:') === 0) {
                        // Item collection pattern: collect items
                        if (!isset($buffered['items'])) {
                            $buffered['items'] = [extractItemInfo($buffered['event']['content'])];
                        }
                        $buffered['items'][] = extractItemInfo($event['content']);
                    } else {
                        // Same actor repeating: increment count
                        $buffered['count']++;
                    }
                    
                    $buffered['last_gamets'] = $event['gamets'] ?? 0;
                    $merged = true;
                    break;
                }
            }
        }
        unset($buffered);
        
        if (!$merged) {
            // Flush older patterns and start new buffer entry
            $consolidationBuffer[] = [
                'event' => $event,
                'pattern' => $pattern,
                'count' => 1,
                'first_gamets' => $event['gamets'] ?? 0,
                'last_gamets' => $event['gamets'] ?? 0,
                'actors' => $actorName ? [$actorName] : null,
                'items' => (strpos($pattern, 'itemfound:') === 0) ? [extractItemInfo($event['content'])] : null
            ];
        }
    }
    
    // Flush remaining buffer
    if (!empty($consolidationBuffer)) {
        $consolidated = array_merge($consolidated, flushConsolidationBuffer($consolidationBuffer));
    }
    
    return $consolidated;
}

/**
 * Extract actor name from event content
 * 
 * @param string $content Event content
 * @return string|null Actor name or null if not extractable
 */
function extractActorName(string $content): ?string {
    // Extract actor from patterns like "ActorName does something"
    if (preg_match('/^([^:]+?)(?:\s+(?:engages combat with|activates|uses|casts|has defeated|found|took|looted|gave)\s+.+)$/i', $content, $matches)) {
        return trim($matches[1]);
    }
    return null;
}

/**
 * Extract item information from item pickup event
 * 
 * @param string $content Event content
 * @return string|null Item description with quantity
 */
function extractItemInfo(string $content): ?string {
    // Extract "N ItemName from/in X" or just "N ItemName"
    if (preg_match('/(?:found|took|looted|traded|gave)\s+(.+?)(?:,\(value.+\))?$/i', $content, $matches)) {
        $itemInfo = trim($matches[1]);
        
        // Extract just the item name (remove quantity) for blacklist check
        // Pattern: "2 Iron Sword" or "Iron Sword" or "an Iron Sword"
        if (preg_match('/^(?:\d+\s+|an?\s+)?(.+?)$/i', $itemInfo, $nameMatches)) {
            $itemName = trim($nameMatches[1]);
            
            // Check if item is blacklisted
            if (isItemBlacklisted($itemName)) {
                return null; // Filter out blacklisted items
            }
        }
        
        return $itemInfo;
    }
    return null;
}

/**
 * Extract consolidation pattern from event
 * 
 * @param array $event Event data
 * @return string|null Pattern identifier or null if not extractable
 */
function extractEventPattern(array $event): ?string {
    $content = $event['content'] ?? '';
    $type = $event['type'] ?? '';
    
    if ($type === 'death') {
        // Pattern: "X DIED" (just death announcement)
        if (preg_match('/^(.+?)\s+died\s*$/i', $content, $matches)) {
            $victim = trim($matches[1]);
            return "death_announce:{$victim}";
        }
        // Pattern: "X has defeated Y" or "X killed Y" etc
        // Extract: actor + victim
        if (preg_match('/^(.+?)\s+(?:has defeated|defeated|killed|slain)\s+(.+?)(?:\s+with\s+.+)?(?:\s+in an awesome move)?$/i', $content, $matches)) {
            $actor = trim($matches[1]);
            $victim = trim($matches[2]);
            return "death:{$actor}→{$victim}";
        }
    } elseif ($type === 'itemfound') {
        // Pattern: "X found/took/looted N Y" or "X found/took/looted Y"
        // Group by actor only for multi-item consolidation
        if (preg_match('/^(.+?)\s+(found|took|looted|traded|gave)\s+(.+)$/i', $content, $matches)) {
            $actor = trim($matches[1]);
            $action = trim($matches[2]);
            return "itemfound:{$actor}→{$action}"; // Only actor+action, group all items together
        }
    } elseif ($type === 'rpg_word') {
        // Generic combat shouts - consolidate identical ones
        return "rpg_word:" . md5($content);
    } elseif ($type === 'spellcast' || $type === 'npcspellcast') {
        // Pattern: "X casts Y" or "X uses Y"
        if (preg_match('/^(.+?)\s+(?:casts|uses)\s+(.+?)$/i', $content, $matches)) {
            $actor = trim($matches[1]);
            $spell = trim($matches[2]);
            return "spell:{$actor}→{$spell}";
        }
    } elseif ($type === 'infoaction') {
        // Pattern: "X engages combat with Y" - group by enemy only (multi-actor consolidation)
        if (preg_match('/^(.+?)\s+engages combat with\s+(.+?)$/i', $content, $matches)) {
            $enemy = trim($matches[2]);
            return "combat:{$enemy}"; // Only enemy in pattern, so multiple actors get grouped
        }
        // Pattern: "X activates Y" - group by object only (multi-actor consolidation)
        if (preg_match('/^(.+?)\s+activates\s+(.+?)$/i', $content, $matches)) {
            $object = trim($matches[2]);
            return "activate:{$object}"; // Only object in pattern
        }
    }
    
    return null;
}

/**
 * Flush consolidation buffer and format consolidated entries
 * 
 * @param array $buffer Consolidation buffer
 * @return array Formatted events
 */
function flushConsolidationBuffer(array $buffer): array {
    $result = [];
    
    foreach ($buffer as $buffered) {
        $event = $buffered['event'];
        
        // Check if this is an item event (single or multi) and filter blacklisted items
        if (isset($buffered['items'])) {
            // Filter out null entries (blacklisted items)
            $filteredItems = array_filter($buffered['items']);
            
            // If all items were filtered out, skip this event entirely
            if (empty($filteredItems)) {
                continue;
            }
            
            // Check if this is a multi-item consolidation
            if (count($filteredItems) > 1) {
                // Multiple items picked up by same actor - list them
                $content = $event['content'];
                
                if (preg_match('/^(.+?)\s+(found|took|looted|traded|gave)\s+/i', $content, $matches)) {
                    $actor = trim($matches[1]);
                    $action = trim($matches[2]);
                    
                    // Build item list from filtered items
                    $itemList = implode(', ', $filteredItems);
                    $event['content'] = "{$actor} {$action} {$itemList}";
                }
            }
            // Single item events will keep their original content (already filtered by extractItemInfo)
        } elseif (isset($buffered['actors']) && count($buffered['actors']) > 1) {
            // Multiple actors doing the same action - list them
            $actorList = implode(', ', $buffered['actors']);
            $content = $event['content'];
            
            // Replace single actor name with list and adjust verb to plural
            if (preg_match('/^(.+?)\s+(engages combat with|activates|uses|casts)\s+(.+?)$/i', $content, $matches)) {
                $action = trim($matches[2]);
                $target = trim($matches[3]);
                
                // Convert verb to plural form
                if (stripos($action, 'engages') !== false) {
                    $action = 'engage combat with';
                } elseif (stripos($action, 'activates') !== false) {
                    $action = 'activate';
                } elseif (stripos($action, 'uses') !== false) {
                    $action = 'use';
                } elseif (stripos($action, 'casts') !== false) {
                    $action = 'cast';
                }
                
                $event['content'] = "{$actorList} {$action} {$target}";
            }
        } elseif ($buffered['count'] > 1) {
            // Same event repeating - add count prefix for clarity (e.g., "2x SKEEVER DIED")
            $event['content'] = "{$buffered['count']}x " . trim($event['content']);
        }
        
        $result[] = $event;
    }
    
    return $result;
}

/**
 * Convert time difference in hours to a human-readable time category
 * 
 * @param float $hoursAgo Number of in-game hours since the event
 * @return string Human-readable time category
 */
function getTimeCategory($hoursAgo) {
    if ($hoursAgo < 0.02) return "Happened Recently";
    if ($hoursAgo < 0.1) return "Moments Ago";
    if ($hoursAgo < 0.25) return "A few minutes ago";
    if ($hoursAgo < 0.5) return "A while ago";
    if ($hoursAgo < 1.5) return "About an hour ago";
    if ($hoursAgo < 4) return "A couple of hours ago";
    if ($hoursAgo < 12) return "Earlier in the day";
    if ($hoursAgo < 36) return "A day ago";
    return "Days ago";
}


function herikaShouldExcludeEventFromPromptContext(array $row): bool
{
    $type = strtolower(trim(strval($row['type'] ?? '')));
    $data = trim(strval($row['data'] ?? ''));

    static $csvImportEventTypes = [
        'biography_import',
        'oghma_import',
        'dynamic_oghma_import',
        'description_import',
        'custom_action_import',
        'traditional_quest_import',
        'item_import',
        'npcvoice_refresh',
    ];

    
    static $promptOnlyEventTypes = [
        'ext_held_item_pickup',
        'ext_held_item_drop',
    ];

    if (in_array($type, $csvImportEventTypes, true)) {
        return true;
    }

    // Held item state is injected separately through <held_items>; avoid replaying
    // every pickup/drop as historic NPC event context.
    
    $shouldRemoveHeldEvents=false;// Make this configuruable.

    if ($shouldRemoveHeldEvents) {
        if (in_array($type, $promptOnlyEventTypes, true)) {
            return true;
        }
    }

    if ($type === 'status_msg' && stripos($data, 'csv_import@') === 0) {
        return true;
    }

    if (preg_match('/^CSV upload(?:\s*\(|:| failed:)/i', $data) === 1) {
        return true;
    }

    if (preg_match('/^[^@]+@[0-9a-f]{8}@nullvoicetype$/i', $data) === 1) {
        return true;
    }

    return false;
}

function buildHistoricContext($actor, $lastNelements = -10,$sqlfilter="") {

    global $db;

    if ($lastNelements == 0) { // if context_history is 0, all records will be retrieved
        $lastNelements = -1;
    }

    $nRecordsLimit = 32 + (2 * abs($lastNelements)); // reduce the default 1000 recs loaded from db to a number proportional to context_history 

    if (isset($GLOBALS["gameRequest"][2])) { 
        $currentGameTs=intval($GLOBALS["gameRequest"][2]);
    } else {
        $currentGameTs=intval(DataLastKnownGameTS());
    }

    if ($GLOBALS["gameRequest"][0]=="chatnf_book") {
        $removeBooks="";
    } else {
        $removeBooks ="and type<>'contentbook' " ;
    }
    
    $ext_sqlfilter1 = $GLOBALS["EXT_CONTEXT_SQL_FILTER1"] ?? "";
    $ext_sqlfilter2 = $GLOBALS["EXT_CONTEXT_SQL_FILTER2"] ?? "";

    $lastDialogFull = array();
    $b_actor = (strlen($actor) > 0);
    if ($b_actor)
        $actorEscaped=$db->escape($actor);
    else
        $actorEscaped='';
    //$playerEscaped=$db->escape($GLOBALS["PLAYER_NAME"]);

    $visibleChatStateSql = chimBuildChatDeliveryStateSql('delivery_state');

    $query="select  
    case 
      when type='infoaction' and a.data like '#%MEMORY%' then 'MEMORY'
      when type='infoaction' and a.data like '<memory>%' then 'MEMORY'
      when type like 'info%' or type like 'funcret%' or type like 'location%' then 'CONTEXTI'
      when a.type='chat_background' or a.data like '%background chat%' then 'BACKDIAG'
      when type='book' then 'BOOKEVT' 
      when type='contentbook' then 'BOOKEVT'
      when type='quest' then 'QUEST' 
      when type='itemfound' then 'ITEM' 
      when type='rpg_word' then 'RPG_WORD' 
      when type='rpg_lvl' then 'RPG_LVL' 
      when type='rpg_shout' then 'RPG_SHOUT' 
      when type='death' then 'RPG_DEATH' 
      when type='welcome' then 'RPG_SPAWN' 
      when type='bleedout' then 'RPG_DEFEAT' 
      when type='waitstart' then 'CONTEXTI' 
      when type='waitstop' then 'CONTEXTI' 
      when type='spellcast' then 'CONTEXTI' 
      when type='npcspellcast' then 'CONTEXTI' 
      when type='reanimate' then 'CONTEXTI' 
      when type='info_timeforward' then 'TIMELAPSE' 
      when type='backgroundaction' then 'CONTEXTI' 
      when type='innerchat' then 'BGLCHAT' 
      when type='ext_held_item_pickup' or type='ext_held_item_drop' then 'HELD_ITEM' 
      when type like 'ext_%' then 'PLUGIN'
      else '' 
    end as subtype,a.data  as data , gamets,localts,type,location
    FROM  eventlog a WHERE
    type<>'combatend'
    and type<>'bored' and type<>'init' and type<>'infoloc' and type<>'info' and type<>'funcret' and type<>'book'
    and type<>'addnpc' and type<>'infonpc' and type<>'infoitems'
    and type<>'updateprofile' and type<>'rechat' and type<>'setconf' and  type<>'status_msg'  and type<>'user_input'
    and type<>'infonpc_close' and type<>'instruction'
    and type<>'request' and type<>'playerinfo' and type<>'im_alive' and type<>'region' and type<>'named_cell'
    AND type<>'narrator_welcome'
    and (type<>'chat' or {$visibleChatStateSql})
    AND type<>'funccall' AND type<>'togglemodel'
    {$removeBooks} {$sqlfilter} {$ext_sqlfilter1}
    ".(($b_actor) ? "
    AND (
     people like '%|$actorEscaped|%'
     or people like '$actorEscaped'
     or people like '%|$actorEscaped (busy)|%'
     or people like '%|$actorEscaped (hostile)|%'
     or people like '%|$actorEscaped (in combat)|%'
     or people like '%|$actorEscaped (restrained)|%'
     or type='info_timeforward'
    )
    " : " ").
    //((false)?" and gamets>".($currentGameTs-(60*60*60*60)):"").
    " {$ext_sqlfilter2} 
    ORDER BY gamets desc, ts desc, rowid desc LIMIT {$nRecordsLimit} OFFSET 0 ";
    
    // error_log("[BGL] $query");   
    // Keep generic far-away actors out of historic context. Shared narrator rows are flattened on write.
    $results = $db->fetchAll($query);



    // Filter stored event types, treating legacy background chat rows as chat_background.
    if (isset($GLOBALS["EVENT_TYPE_FILTER"]) && !empty($GLOBALS["EVENT_TYPE_FILTER"])) {
        $results = chimFilterRowsByEventType($results, $GLOBALS["EVENT_TYPE_FILTER"]);
    }

    


    $results = array_filter($results, function ($row) {
        return !herikaShouldExcludeEventFromPromptContext($row);
    });

    
    //error_log($query);
    $rawData=[];
    foreach ($results as $row) {
        $rawData[md5($row["data"].$row["localts"])] = $row;
    }

    $rawDataFiltered=[];

    // $rawDataFiltered is ordered by gamets desc. We want to keep only first HELD_ITEM subtype found if:
    // 1. type='ext_held_item_pickup'         
    // 2. event is in the first 5 entries.
    
    $heldItemAdded=false;
    $localCounter = 0;
    foreach ($rawData as $key => $row) {
        if ($row["subtype"]=="HELD_ITEM") {
            if ($row["type"]=="ext_held_item_pickup") {
                if ($localCounter < 5 && !$heldItemAdded) {
                    $rawDataFiltered[] = $row;
                    $heldItemAdded = true;
                }
            } else if ($row["type"]=="ext_held_item_drop") {
                $heldItemAdded=true; // Mark as added to prevent further pickups from being added
            }
        } else
            $rawDataFiltered[] = $row;
        
        $localCounter++;    
    }
    
    $orderedData = array_reverse($rawDataFiltered);

    //$orderedData = array_slice($orderedData, $lastNelements);

    
    $currentLocation = "";
    $writeLocation = true;

    $lastSpeaker = "";
    $buffer = [];
    $timeStampBuffer = [];

    $beingsPresent=null;
    $lastlocation="";
    $lastGameTs=0;
    $memoryLogToRemove=[];
    
    $lastTimeCategory = null; // Track last timestamp category for PROMPT_TIMESTAMP feature

    foreach ($orderedData as $n=>$row) {
        $rowData = $row["data"];
        
        if ($rowData==="The Narrator:") // Hunt empty rows
            continue;
        
        // Remove Context location from data
        $pattern = '/\s*\(Context location: .*?\)/';
        if ($rowData)
            $rowData = preg_replace($pattern, "", $rowData); 

        // Figure out location form location field, and only add to context if changed    
        $printLocation=false;
        $string = $row["location"];
        if (!empty($string)) {
            preg_match('/Context\s*(?:new\s*)?location:\s*([^,]+?)(?:,|$)/u', $string, $locationMatch);
            preg_match('/Hold:\s*([^,\)]+?)(?:,|\)|$)/u', $string, $holdMatch);
        }
        
        if (!isset($holdMatch[1])) {
            //error_log(print_r($string,true));
            $locationFinal=$lastlocation;
        } else {
            $hold = trim($holdMatch[1]);
            $location = trim($locationMatch[1]);
            $locationFinal="$location, hold: $hold";
        }
        
        if ($lastlocation!=$locationFinal) {
            $lastlocation=$locationFinal;
            if ($row["type"]!="location")
                $printLocation=true;
            $currentLocation=$lastlocation;
        }
        
        // Special case, logaction is the return data of an action call.
        if ($row["type"]=="logaction") {
            $logactionData=json_decode($rowData,true);
            if (is_array($logactionData)) {
                if ($logactionData["character"]!=$GLOBALS["HERIKA_NAME"])
                    continue;
            }
        }
        
        // Skip empty rows
        if (!$rowData)
            $rowData="";
        

        // Figure out real speaker
        if (($row["type"]=="logaction") && (strpos($rowData, "{$GLOBALS["HERIKA_NAME"]}") !== false))  {
            $speaker = "assistant";
            
        } else if ($row["type"]=="vision") {
            $speaker = "user";
            
        } else if ($row["subtype"]=="MEMORY") {
            $speaker = "memory";
            
        } else if ((strpos($rowData, "{$GLOBALS["HERIKA_NAME"]}:") === 0) && (strpos($rowData, "The Narrator:") === false)) {
            // Only a line that STARTS with "<thisNPC>:" is this NPC's own turn (assistant). Using !== false
            // here matched the name ANYWHERE, so another NPC's line that merely contained "<thisNPC>:" was
            // mis-claimed as this NPC's own line -> cross-NPC identity bleed. Mirrors the player/narrator checks below.
            $speaker = "assistant";
            
        } else if ((strpos($rowData, "{$GLOBALS["PLAYER_NAME"]}:") === 0)) {
            $speaker = "player";
            
        } else if ((strpos($rowData, "The Narrator:") === 0) && $row["type"]=="chat") {
            $speaker = "narratorchat";
            
        } else if ($row["subtype"]=="BACKDIAG") {
            $speaker = "backgroundchat";
            
        } else if ($row["subtype"]=="BGLCHAT") {
            $speaker = "backgroundchat";
            
        } else if ($row["subtype"]=="BOOKEVT") {
            $speaker = "narratorci";
            
        } else if ($row["subtype"]=="CONTEXTI") {
            if (strpos($rowData,"should not be visible")!==false)
                continue;
            
         
            $speaker = "narratorci";
            
        } else if ($row["subtype"]=="QUEST") {
            $speaker = "narratorci";
            
        } else if ($row["subtype"]=="ITEM") {
            $speaker = "narratorci";
            
        } else if ($row["subtype"]=="RPG_WORD") {
            $speaker = "narratorci";
            
        } else if ($row["subtype"]=="RPG_LVL") {
            $speaker = "narratorci";
            
        } else if ($row["subtype"]=="RPG_SPAWN") {
            $speaker = "narratorci";
            
        } else if ($row["subtype"]=="RPG_SHOUT") {
            $speaker = "narratorci";
            
        } else if ($row["subtype"]=="RPG_DEATH") {
            $speaker = "narratorci";
            $rowData = strtoupper($rowData);
            
        } else if ($row["subtype"]=="RPG_DEFEAT") {
            $speaker = "narratorci";
            $rowData = strtoupper($rowData);
            
        } else if ($row["subtype"]=="TIMELAPSE") {
            $rowData = strtoupper($rowData);
            
        } else if ($row["subtype"]=="HELD_ITEM") {
            $rowData = trim(strip_tags($rowData))." Holding it in hand (held item)";
            $speaker = "narratorci";
        } else if ($row["subtype"]=="PLUGIN") {
            $speaker = $row["type"];
            
        } else {
            
            $speaker = "npc";
            
        }

        // Compact info_timeforward events
        if ($row["type"] == "info_timeforward") {
            if (isset($previousRow) && $previousRow["type"] == "info_timeforward") {
                // Extract hours passed from the current row and the current date/time portion
                preg_match('/([\d.]+)\s*hours have passed\.?/i', $row["data"], $currentMatch);
                $currentHours = isset($currentMatch[1]) ? (float)$currentMatch[1] : 0;
                preg_match('/(Current date\/time: .+)$/i', $row["data"], $currentDateMatch);
                $currentDateTime = isset($currentDateMatch[1]) ? trim($currentDateMatch[1]) : '';

                // Extract hours passed from the previous row (if present)
                preg_match('/([\d.]+)\s*hours have passed\.?/i', $previousRow["content"], $previousMatch);
                $previousHours = isset($previousMatch[1]) ? (float)$previousMatch[1] : 0;

                // Sum the hours
                $totalHours = $currentHours + $previousHours;

                // error_log("[TIMEFORWARD] $totalHours = $currentHours + $previousHours ");

                // Build a normalized single-line content: "<hours> hours have passed. Current date/time: ..."
                if ($currentDateTime !== '') {
                    $previousRow["content"] = "{$totalHours} hours have passed. {$currentDateTime}";
                } else {
                    // Fallback: use the trimmed current row data if date/time portion wasn't found
                    $previousRow["content"] = "{$totalHours} hours have passed. " . trim($row["data"]);
                }

                continue; // Skip adding this row to the context
            } else {
                $row["role"]="narratorci";
                $row["content"]=trim($rowData);
                $row["gamets"]=$lastGameTs;// gamets will be previous record gamets

                $previousRow=$row;
                continue; // Skip adding this row to the context
            }
        } else if (isset($previousRow) && $previousRow["type"] == "info_timeforward") {
            $lastDialogFull[]=$previousRow;
            unset($previousRow);
        }

        //if (($GLOBALS["FEATURES"]["MISC"]["ADD_TIME_MARKS"])&&(true) && $row["type"] != "info_timeforward") {
        if ($row["type"] != "info_timeforward") {
    
            
            if ($lastGameTs==0)
                $lastGameTs=$row["gamets"];
            else {
                $timeGapInHours=round(($row["gamets"]-$lastGameTs) * 0.0000024, 0);
                
                if ($timeGapInHours>36) {
                    $timeGapInDays=round($timeGapInHours/24,1);
                    $lastDialogFull[] = array('role' => "narratorci", 'content' => "!!! IMPORTANT CONTEXT !!!
A MAJOR TIME JUMP HAS OCCURRED.
Elapsed time since last interaction: ~$timeGapInDays days
New setting: $currentLocation
!!! END CONTEXT !!! ");
                } else if ($timeGapInHours>5) {
                    $timeGapInDays=round($timeGapInHours/24,1);
                    $lastDialogFull[] = array('role' => "narratorci", 'content' => "(minor timelapse of about $timeGapInHours hours)");
                }
                $lastGameTs=$row["gamets"];
            }

            if ($printLocation ) {
                $hoursAgo=round(($currentGameTs-$row["gamets"]) * 0.0000024, 0);
                if (!isset($timeStampBuffer[$hoursAgo])) {
                    if ($currentLocation) {
                        if (DataLastKnownLocationHuman(false,true)==$currentLocation)   // Enforce current location.
                            $lastDialogFull[] = array('role' => "narratorci", 'content' => "LOCATION CHANGE, THIS IS THE CURRENT LOCATION: $currentLocation");
                        
                        else
                            $lastDialogFull[] = array('role' => "narratorci", 'content' => "LOCATION CHANGE to $currentLocation, timeline mark: $hoursAgo hours ago  ");
                    }
                }
            } else {
               

            }
        }

        $lastSpeaker = $speaker;
        
        // Insert timestamp subdividers if PROMPT_TIMESTAMP is enabled
        if (!empty($GLOBALS["PROMPT_TIMESTAMP"]) && $row["type"] != "info_timeforward") {
            $hoursAgo = ($currentGameTs - $row["gamets"]) * 0.0000024;
            $currentTimeCategory = getTimeCategory($hoursAgo);
            
            // If category changed, insert a subdivider
            if ($lastTimeCategory !== null && $currentTimeCategory !== $lastTimeCategory) {
                $lastDialogFull[] = array('role' => "narratorci", 'content' => "--- {$currentTimeCategory} ---");
            }
            
            $lastTimeCategory = $currentTimeCategory;
        }
        
        $row= array('role' => $lastSpeaker, 'content' => trim($rowData),'subtype'=>$row["subtype"]?:strtoupper($lastSpeaker),'type'=>$row["type"],'gamets'=>$row["gamets"]);
        $lastDialogFull[] = $row;
        $previousRow=$row;

    }
    
    if (isset($previousRow)) {
        if (sizeof($previousRow)>0) {
            if (sizeof($lastDialogFull) === 0 || $previousRow !== end($lastDialogFull)) {
                $lastDialogFull[]=$previousRow;
            }
            
        }
    }

    file_put_contents(__DIR__."/../log/context_for_{$actor}_stage_1_.txt",print_r($lastDialogFull,true));

    // Remove memory logs, only leave last one.
    $lastDialogFullOnlyLastMemory=[];
    $localFlag=0;
    foreach (array_reverse($lastDialogFull) as $element) {
        if ($element["role"]=="memory") {
            if ($localFlag==0) {
                $element["role"]="narratorci";
                $lastDialogFullOnlyLastMemory[]=$element;
                $localFlag++;
            } else {
                $localFlag++;
            }
        } else {
            $lastDialogFullOnlyLastMemory[]=$element;
        }
    }

    error_log("[buildHistoricContext] $localFlag memories removed");
    $lastDialogFull=array_reverse($lastDialogFullOnlyLastMemory);
    // End of memory logs cleaning
    
    // Consolidate repeated events to reduce context size
    $eventCountBefore = count($lastDialogFull);
    $lastDialogFull = consolidateEvents($lastDialogFull);
    $eventCountAfter = count($lastDialogFull);
    if ($eventCountBefore > $eventCountAfter) {
        error_log("[buildHistoricContext] Consolidated events: {$eventCountBefore} → {$eventCountAfter} (saved " . ($eventCountBefore - $eventCountAfter) . " slots)");
    }

    // Filter ambient combat deaths if configured
    if (!empty($GLOBALS["HIDE_AMBIENT_COMBAT"])) {
        $beforeFilter = count($lastDialogFull);
        $lastDialogFull = array_values(array_filter($lastDialogFull, function($event) {
            // Keep non-death events
            if (!isset($event['type']) || $event['type'] !== 'death') {
                return true;
            }
            
            // Keep death events that don't contain "has killed" (i.e., keep "has defeated")
            $content = $event['content'] ?? '';
            if (stripos($content, 'has killed') !== false) {
                return false; // Filter out ambient combat
            }
            
            return true; // Keep significant combat events
        }));
        $afterFilter = count($lastDialogFull);
        if ($beforeFilter > $afterFilter) {
            error_log("[buildHistoricContext] Filtered ambient combat: {$beforeFilter} → {$afterFilter} (removed " . ($beforeFilter - $afterFilter) . " events)");
        }
    }

    file_put_contents(__DIR__."/../log/context_for_{$actor}_stage_1_.txt",print_r($query,true),FILE_APPEND);
    
    return $lastDialogFull;

}

function compactHistoricContext($lastDialogFull,$actor,$compactContextInfo=false) {

    $lastrole="";
    $bufferHerika=[];
    $lastDialogFullCopy=[];
    $compactedBuffer = "";
 
    foreach ($lastDialogFull as $n => $line) {
        if (($line["role"] == "assistant")) {
            $isJson=json_decode($line["content"],true);
            if (is_array($isJson)) {
                $lastDialogFullCopy[]=$line;
                continue;
            }
            $cleanedText=$line["content"];
           
            $bufferHerika[]=$cleanedText;

            
        } else {
            if ($lastrole=="assistant") {
                // This breaks with spaces?
                $compactedBuffer="";
                foreach ($bufferHerika as $m=>$singleline) {
                    $compactedBuffer .=" ";
                    if ($m>0) {
                        //$regexpNpcName = strtr($GLOBALS["HERIKA_NAME"],["-"=>'\-', "["=>"\[", "]"=>"\]"]);
                        // Capture spoken text after a leading "Name:" (supports names with brackets and dashes)
                        // and optionally strip a trailing parenthetical note like "(talking to X)".
                        preg_match('/^\s*[^:]+:\s*(.*?)\s*(?:\([^)]*\))?\s*$/s', $singleline, $matches);
                        $extracted=$matches[1] ?? $singleline;
                        $compactedBuffer .= trim(removeTalkingToOccurrences($extracted));
                        $compactedBuffer=str_replace("{$GLOBALS["HERIKA_NAME"]};","",$compactedBuffer);

                    } else {
                        $compactedBuffer .= trim(removeTalkingToOccurrences($singleline));
                        $compactedBuffer=str_replace("{$GLOBALS["HERIKA_NAME"]}:","",$compactedBuffer);
                    }


                }
                $lastDialogFullCopy[] = ["role"=>"assistant","content"=>trim($compactedBuffer)];

            }
            $bufferHerika=[];
            $compactedBuffer="";
            $lastDialogFullCopy[]=$line;
        } 

        
        
        $lastrole=$line["role"];
    }

    // Last entry
    if (sizeof($bufferHerika)>0) {
        foreach ($bufferHerika as $m=>$singleline) {
            $compactedBuffer .=" ";
            if ($m>0) {
                //$regexpNpcName = strtr($GLOBALS["HERIKA_NAME"],["-"=>'\-', "["=>"\[", "]"=>"\]"]);
                // Same robust extraction for subsequent lines in the buffer
                preg_match('/^\s*[^:]+:\s*(.*?)\s*(?:\([^)]*\))?\s*$/s', $singleline, $matches);
                $extracted=$matches[1] ?? $singleline;
                $compactedBuffer .= trim(removeTalkingToOccurrences($extracted));
                $compactedBuffer=str_replace("{$GLOBALS["HERIKA_NAME"]};","",$compactedBuffer);

            } else {
                $compactedBuffer .= trim(removeTalkingToOccurrences($singleline));
                $compactedBuffer=str_replace("{$GLOBALS["HERIKA_NAME"]};","",$compactedBuffer);
            }



        }
        $lastDialogFullCopy[] = ["role"=>"assistant","content"=>trim($compactedBuffer)];
        $bufferHerika=[];
    }

    // file_put_contents(__DIR__."/../log/context_for_{$actor}_stage_1_5_.txt",print_r($lastDialogFullCopy,true));

    
    // Compact other info
    $lastSpeaker = "";
    $buffer = [];
    $lastDialogFull=[];
    $g = 0; // STM: last-seen gamets, stamped onto compacted entries ('_g') for the floor capture in replaceRoles


    foreach ($lastDialogFullCopy as $n => $line) {
        $speaker=$line["role"];
        if (isset($line["gamets"])) $g = intval($line["gamets"]);
        
        if ($speaker=="npc") { // Tricky, npc could be any char
            preg_match('/^([^:]+):/', $line["content"], $matches);
            // Output the extracted name
            $speakerNPC=$matches[1] ?? "";
            $speaker="npc_$speakerNPC";
        }
        

        if ($lastSpeaker == $speaker) {
            // Same speaker as last iteration, remove extra text
            if (strpos($speaker,"npc") === 0 || $speaker == "narratorchat") {
                $matches = [];
                
                // Clean talking to and npc name , only leave it on first line
                $matches = [];
                // And for compacting other dialog lines: capture content after the speaker name
                preg_match('/^\s*[^:]+:\s*(.*?)\s*(?:\([^)]*\))?\s*$/s', $line["content"], $matches);
                $buffer[]=$matches[1] ?? $line["content"];
            } else {

                if (!$compactContextInfo) {
                    $lastDialogFull[]=array('role' => $lastSpeaker, 'content' => trim(isset($buffer[0])?$buffer[0]:$line["content"]), '_g' => $g);
                    if (isset($buffer[0])) {
                        $buffer = [];
                        $buffer[] = $line["content"];
                    } else
                        $buffer = [];
                } else {
                    $buffer[] = strtr($line["content"],["The Narrator:"=>"","{$GLOBALS["HERIKA_NAME"]}:"=>""]);
                }
                
            }
        } else {

            if (sizeof($buffer) > 0) {
                if ($lastSpeaker=="narratorci" || $lastSpeaker=="narratorloc") {
                    if (!$compactContextInfo) {
                        $lastDialogFull[] = array('role' => $lastSpeaker, 'content' => "".implode(" ", removeEmptyElements($buffer)), '_g' => $g);  // Should be only one line
                    } else {
                        $lastDialogFull[] = array('role' => $lastSpeaker, 'content' => "* ".implode("\n* ", removeEmptyElements($buffer)), '_g' => $g); 
                    }

                }
                else if ($lastSpeaker=="backgroundchat")
                    $lastDialogFull[] = array('role' => $lastSpeaker, 'content' => implode("\n", removeEmptyElements($buffer)), '_g' => $g);
                else 
                    $lastDialogFull[] = array('role' => $lastSpeaker, 'content' => moveDialogueTargetSuffixToEnd(implode(" ", removeEmptyElements($buffer))), '_g' => $g);
            }
            $buffer = [];
            $buffer[] = $line["content"];
            $lastSpeaker = $speaker;

            if ($speaker=="assistant") {    //Leave as is
                $line['_g'] = $g; $lastDialogFull[] = $line;
                $lastSpeaker = "";
                $buffer = [];
                continue;
            }
        }

    }

    // Clean empty entries
    $bufferCopy=[];
    foreach ($buffer as $n=>$bufferEntry) {
        if (!empty(trim($bufferEntry)))
            $bufferCopy[]=$bufferEntry;

    }

    // Last buffer, probably user input.
    if (sizeof($bufferCopy)) {
        if ($lastSpeaker=="narratorci" || $lastSpeaker=="narratorloc") 
            $lastDialogFull[] = array('role' => $lastSpeaker, 'content' => implode("\n* ", $bufferCopy), '_g' => $g);
        else if ($lastSpeaker=="backgroundchat")
            $lastDialogFull[] = array('role' => $lastSpeaker, 'content' => implode("\n", $bufferCopy), '_g' => $g);
        else 
            $lastDialogFull[] = array('role' => $lastSpeaker, 'content' => moveDialogueTargetSuffixToEnd(implode(" ", $bufferCopy)), '_g' => $g);
    }

    $contextDataHistory=[];
    foreach ($lastDialogFull as $n=>$lastDialogFullEntry) {
        if (!empty(trim($lastDialogFullEntry["content"])))
                $contextDataHistory[]=$lastDialogFullEntry;

    }

    file_put_contents(__DIR__."/../log/context_for_{$actor}_stage_2_.txt",print_r($contextDataHistory,true));
    return $contextDataHistory;
}

function replaceRoles($lastDialogFull,$actor,$lastNelements) {

     // Replace roles for user.
     foreach ($lastDialogFull as $n => $line) {
        if ($line["role"] == "player") {
            $lastDialogFull[$n]["role"] = "user";
        } else if (strpos($line["role"],"npc")===0) {
        
            $lastDialogFull[$n]["role"] = "user";
        
        } else if ($line["role"] == "backgroundchat") {
        
            $lastDialogFull[$n]["role"] = "user";
            if (strlen(trim($lastDialogFull[$n]["content"])) > 0) {
                $lastDialogFull[$n]["content"] = " (... ".PHP_EOL.$lastDialogFull[$n]["content"]."\n...)";
            }
            
        } else if ($line["role"] == "narratorci") {
        
            $lastDialogFull[$n]["role"] = "user";
            $lastDialogFull[$n]["content"] = $lastDialogFull[$n]["content"]."\n";
        
        } else if ($line["role"] == "narratorchat") {

            $lastDialogFull[$n]["role"] = "user";

        } else if ($line["role"] == "narratorloc") {

            $lastDialogFull[$n]["role"] = "user";

        }
    }

    // Date issues

    foreach ($lastDialogFull as $n => $line) {

        $pattern = '/(\w+), (\d{1,2}:\d{2} (?:AM|PM)), (\d{1,2})(?:st|nd|rd|th) of ([A-Za-z\'\ ]+), 4E (\d+)/'; //extract also for months with aphostrophe like Sun's Something
        $replacement = 'Day name: $1, Hour: $2, Day Number: $3, Month: $4, 4th Era, Year: $5';
        $result = preg_replace($pattern, $replacement, $line["content"]);
        $lastDialogFull[$n]["content"] = $result;
    }



    error_log("[CHIM] Using effective context limit of : $lastNelements");
    $orderedData = array_slice($lastDialogFull, $lastNelements);
    // STM: capture the window's oldest surviving gamets (the true floor). '_g' is kept on the
    // entries so main.php can crop the window by it; main.php strips '_g' before the LLM sees them.
    $__floorG = 0;
    foreach ($orderedData as $__e) {
        if (is_array($__e) && !empty($__e['_g'])) {
            $__floorG = ($__floorG == 0) ? intval($__e['_g']) : min($__floorG, intval($__e['_g']));
        }
    }
    $GLOBALS["CONTEXT_WINDOW_FLOOR"] = $__floorG;

    file_put_contents(__DIR__."/../log/context_for_$actor.txt",print_r($orderedData,true));
    $GLOBALS["CONTEXT_BUILDING_DATA"]=$orderedData;
    requireFilesRecursively(__DIR__."/../ext/","context_building.php");

    file_put_contents(__DIR__."/../log/context_for_{$actor}_ext.txt",print_r($GLOBALS["CONTEXT_BUILDING_DATA"],true));

    return $GLOBALS["CONTEXT_BUILDING_DATA"];

}

function DataLastDataExpandedFor($actor, $lastNelements = -10,$sqlfilter="")
{

    $localStartTime=microtime(true);

    $ctx1=buildHistoricContext($actor, $lastNelements ,$sqlfilter);    
    error_log("[buildHistoricContext] Elapsed time: " . (microtime(true) - $localStartTime) . " seconds");


    $ctx2=compactHistoricContext($ctx1,$actor,false);  // Don't compact Context Info

    error_log("[compactHistoricContext] Elapsed time: " . (microtime(true) - $localStartTime) . " seconds");

    $ctx3=replaceRoles($ctx2,$actor,$lastNelements);
      
    error_log("[replaceRoles] Elapsed time: " . (microtime(true) - $localStartTime) . " seconds");

    // Cases of self rechat
    if ((sizeof($ctx3)>3)&&(($GLOBALS["gameRequest"][3] ?? "")=="rechat")) {
        $lastElement = $ctx3[sizeof($ctx3)-1];
        // Last element is assistant
        if ($lastElement["role"]=="assistant") {
            if ($GLOBALS["gameRequest"][3]=="rechat") {
                // NPC is rechatting himself
                
                Logger::warn("[RECHAT] actor is replying itself, case 1, aborting");

                echo 'X-CUSTOM-CLOSE'.PHP_EOL;
                if (!getenv("PHPUNIT_TEST")) {
                    @ob_end_flush();
                    @flush();
                }
            }

        }

        $preLastElement = $ctx3[sizeof($ctx3)-2];
        // Pre last element is assistant, and last is a memory.
        if (($preLastElement["role"]=="assistant")&&(strpos($lastElement["content"],"MEMORY")!==false)) {
            if ($GLOBALS["gameRequest"][3]=="rechat") {
                // NPC is rechatting himself
                
                Logger::warn("[RECHAT] actor is replying itself,case 2, aborting");

                echo 'X-CUSTOM-CLOSE'.PHP_EOL;
                if (!getenv("PHPUNIT_TEST")) {
                    @ob_end_flush();
                    @flush();
                }
            }

        }
    }

    //error_log("[DataLastDataExpandedFor end] Elapsed time: " . (microtime(true) - $localStartTime) . " seconds");

    return $ctx3;

}

function DataLastDataExpandedForBak($actor, $lastNelements = -10,$sqlfilter="")
{

    global $db;

    $currentGameTs=$GLOBALS["gameRequest"][2]+0;
    if ($GLOBALS["gameRequest"][0]=="chatnf_book") {
        $removeBooks="";
    } else {
        $removeBooks ="and type<>'contentbook' " ;
    }
    
    $lastDialogFull = array();
    
    $results = $db->fetchAll("select  
    case 
    when type like 'info%' or type like 'death%' or  type like 'funcret%' or type like 'location%'  then 'The Narrator:'
    when a.type='chat_background' or a.data like '%background chat%' then 'The Narrator: background dialogue: '
    when type='book' then 'The Narrator: ({$GLOBALS["PLAYER_NAME"]} took the book ' 
    else '' 
    end||a.data  as data , gamets,localts,type
    FROM  eventlog a WHERE 1=1
    and type<>'combatend'  
    and type<>'bored' and type<>'init' and type<>'infoloc' and type<>'info' and type<>'funcret' and type<>'book' and type<>'addnpc' and type<>'infoitems' 
    and type<>'updateprofile' and type<>'rechat' and type<>'narration' and type<>'setconf' and type<>'backgroundaction'
    and type<>'funccall' $removeBooks  and type<>'togglemodel' $sqlfilter  
    and gamets>".($currentGameTs-(60*60*60*60))."
    order by gamets desc,ts desc,rowid desc LIMIT 1000 OFFSET 0");
    

    
 
    $rawData=[];
    foreach ($results as $row) {
        $rawData[md5($row["data"].$row["localts"])] = $row;
    }

    
    $orderedData = array_reverse($rawData);

    
    //$orderedData = array_slice($orderedData, $lastNelements);

    $currentLocation = "";
    $writeLocation = true;

    $currentSpeaker = "user";
    $buffer = [];
    $timeStampBuffer = [];

    $beingsPresent=null;
    
    foreach ($orderedData as $row) {
        $rowData = $row["data"];
        // Extract location
        $pattern = '/\(Context location: (.*?),(.*?)\)/';

        if (preg_match($pattern, str_replace(" background dialogue", "", $rowData), $matches)) {

            $contextLocation = $matches[0];
            if ($currentLocation != $contextLocation) {
                $currentLocation = $contextLocation;
                $writeLocation = true;
            } else {
                $writeLocation = false;
            }

        } else {

        }

        if (!$writeLocation) {
            $pattern = "/\([^)]*Context location[^)]*?\)/";
            $rowData = preg_replace($pattern, "", $rowData); // Remove context location if repeated
        }

        
        // This is used for compacting.
        
        if (($row["type"]=="logaction") && (strpos($rowData, "{$GLOBALS["HERIKA_NAME"]}") !== false))  {
            $speaker = "assistant";
            
        } else if ($row["type"]=="vision") {
            $speaker = "user";
            
        } else if ((strpos($rowData, "{$GLOBALS["HERIKA_NAME"]}:") !== false)) {
            $speaker = "assistant";
            
        } 
         else if ((strpos($rowData, "{$GLOBALS["PLAYER_NAME"]}:") !== false)) {
            $speaker = "player";
            
        } else {
            $speaker = "user";
            
        }
        
        if (!empty($actor)) {
            if ( $row["type"]=="infonpc") {
                $beingsPresent=$rowData;
                continue;
            }
            if (empty($beingsPresent)) {
                continue;
            }
         
            if (strpos($beingsPresent,$actor)===false) {
                continue;
            }
        } else {
            if ( $row["type"]=="infonpc")   
                continue;
        }



        if (($currentSpeaker == $speaker) && ($speaker == "assistant") && $row["type"]!="logaction") {
            $buffer[] = $rowData;
        } else {
            if (sizeof($buffer) > 0) {
                $lastDialogFull[] = array('role' => $currentSpeaker, 'content' => implode("\n", $buffer));
            }
            $buffer = [];
            $buffer[] = $rowData;
            $currentSpeaker = $speaker;
        }

        if ($GLOBALS["FEATURES"]["MISC"]["ADD_TIME_MARKS"]) {
            $hoursAgo=round(($currentGameTs-$row["gamets"]) * 0.0000024, 0);
            if ($hoursAgo>12) {
                if (!isset($timeStampBuffer[$hoursAgo])) {
                    if ($currentLocation) {
                        $timeStampBuffer[$hoursAgo]="set";
                        $lastDialogFull[] = array('role' => "user", 'content' => "The Narrator: SCENARIO CHANGE, $currentLocation, timeline mark: $hoursAgo hours ago  ");
                    }
                }
            }
        }

    }

 
    // if (($currentGameTs-$row["gamets"])>600) {


    //}

       
    print_r($lastDialogFull);
    die();
    
    $lastDialogFull[] = array('role' => $currentSpeaker, 'content' => implode("\n", $buffer));

    // Compact Herika's lines
    foreach ($lastDialogFull as $n => $line) {
        if ($line["role"] == "assistant") {
            $pattern = "/\(Context location:[^)]+?\)/";
            $cleanedText = trim(preg_replace($pattern, "", $line["content"])); // Remove context location always for assistant
            // This breaks with spaces?
            $re = '/[^(' . strtr($GLOBALS["HERIKA_NAME"],["-"=>'\-']) . ':)].*(' . strtr($GLOBALS["HERIKA_NAME"],["-"=>'\-']) . ':)/m';
            $subst = "";
            $cleanedText = preg_replace($re, $subst, $cleanedText);
            
            
            $cleanedText = removeTalkingToOccurrences($cleanedText);
            
            $lastDialogFull[$n]["content"] = $cleanedText;
        }

    }

    // Replace player for user.
    foreach ($lastDialogFull as $n => $line) {
        if ($line["role"] == "player") {
            $lastDialogFull[$n]["role"] = "user";
        }
    }

    // Date issues

    foreach ($lastDialogFull as $n => $line) {

        $pattern = '/(\w+), (\d{1,2}:\d{2} (?:AM|PM)), (\d{1,2})(?:st|nd|rd|th) of ([A-Za-z\'\ ]+), 4E (\d+)/'; //extract also for months with aphostrophe like Sun's Something
        $replacement = 'Day name: $1, Hour: $2, Day Number: $3, Month: $4, 4th Era, Year: $5';
        $result = preg_replace($pattern, $replacement, $line["content"]);
        $lastDialogFull[$n]["content"] = $result;
    }


    $orderedData = array_slice($lastDialogFull, $lastNelements);

   
    return $orderedData;

}

function DataSpeechJournal($topic,$limit=50) 
{

    global $db;

    $lastDialogFull = [];
    $tn=$db->escape($topic);
    $results = $db->fetchAll("SElECT  speaker,speech,location,listener,topic as quest, convert_gamets2skyrim_date(gamets) AS sk_date, gamets FROM speech
     where (speaker like '%$tn%' or  listener like '%$tn%' or location like '%$tn%' or  
      companions like '%|$tn|%' or  companions like '%$tn%' OR companions LIKE '%|$tn (busy)|%' 
      OR companions LIKE '%|$tn (hostile)|%' OR companions LIKE '%|$tn (restrained)|%' ) 
      and listener<>'unknown' 
      order by rowid desc");
    if (!$results) {
        return json_encode([]);
    }

    $data = [];

    foreach ($results as $row) {
        $data[] = $row;
    }

    if (sizeof($data) == 0) {
        return json_encode([]);
    } elseif (sizeof($data) < $limit) {
        $dataReversed = array_reverse($data);
    } else {
        $smalldata = array_slice($data, 0,$limit);
        $dataReversed = array_reverse($smalldata);
    }


    return json_encode($dataReversed);

}

/*
 * Diary functions are attached to FTS queries, Should be driver agnostic. work on this
 * */
function DataDiaryLog($topic)
{

    global $db;
    /*
    $results = $db->query("SElECT  topic,content,tags,people  FROM diarylog
    where (tags like '%$topic%' or topic like '%$topic%' or people like '%$topic%') order by gamets asc");
    */
    $topicTok = explode(" ", strtr($topic, array("'" => "")));
    $topicFmt = implode(" OR ", $topicTok);
    $results = $db->fetchAll(SQLite3::escapeString("SElECT  topic as page,content,tags,people  FROM diarylogv2
      where (tags MATCH \"$topicFmt\" or topic MATCH \"$topicFmt\" or content MATCH \"$topicFmt\" or people MATCH \"$topicFmt\") ORDER BY rank"));


    if (!$results) { // No match, will return a list of current memories
        $results = $db->fetchAll(SQLite3::escapeString("SElECT  topic as page,tags  FROM diarylogv2 order by rowid asc"));

        if (!$results) {
            return json_encode([]);
        }

        $data = [];

        foreach ($results as $row) {
            $data[] = $row;
        }

        return json_encode(["return value" => "Page not found", "similar pages" => $data]);


    } else { // Return best matching memory

        file_put_contents(__DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "data". DIRECTORY_SEPARATOR ."logquery.txt", SQLite3::escapeString("\nSElECT  topic,content,tags,people  FROM diarylogv2
        where (tags MATCH \"$topicFmt\" or topic MATCH \"$topicFmt\" or content MATCH \"$topicFmt\" or people MATCH \"$topicFmt\") ORDER BY rank"), FILE_APPEND);

        $data = [];
        foreach ($results as $row) {
            $data[] = $row;
            break; // Only space for one memory
        }

    }

    if (sizeof($data) == 0) { // No match, will return a list of current memories. Revise limits

        $results = $db->fetchAll(SQLite3::escapeString("SElECT  topic as page  FROM diarylogv2 order by rowid asc"));

        $data = [];

        foreach ($results as $row) {
            $data[] = $row;
        }

        return json_encode(["return value" => "Page not found", "available pages" => $data]);
    }

    return json_encode($data);


}


function DataDiaryLogIndex($topic)
{

    global $db;
    //$results = $db->query('SElECT  topic,tags  FROM diarylogv2 where tags  MATCH NEAR(\'one two\' \'three four\', 10) order by rank');
    $preData = $db->fetchAll("SElECT  topic as page,tags,people  FROM diarylogv2 where tags  MATCH 'NEAR(\"$topic\")' or topic  MATCH 'NEAR(\"$topic\")' or people  MATCH 'NEAR(\"$topic\")'  order by rank");
    //$preData=  self::fetchAll("SElECT  topic,tags,people  FROM diarylogv2 where tags  MATCH \"$topic\" order by rank");
    if (sizeof($preData) == 0) {
        $preData = $db->fetchAll("SElECT  topic as page,tags,people  FROM diarylogv2 where tags  like '%$topic%'  or topic  like '%$topic%' or people  like '%$topic%'");
        if (sizeof($preData) == 0) {
            $results = $db->fetchAll(SQLite3::escapeString("SElECT  topic as page,tags,people  FROM diarylogv2 order by rowid asc"));
            $data = [];

            foreach ($results as $row) {
                $data[] = $row;
            }
        } else {
            $data = $preData;
        }

    } else {

        $data = $preData;

    }


    return json_encode($data);

}


function DataGetCurrentTask()
{
    global $db;

    $hourThreshold= DataLastKnownGameTS()-(2/ 0.0000024);

    $results = $db->fetchAll("SElECT  distinct description as description,gamets FROM currentmission where sess='ephemeral' and gamets>$hourThreshold order by gamets desc");
    error_log("SElECT  distinct description as description,gamets FROM currentmission where sess='ephemeral' and gamets>$hourThreshold order by gamets desc");

    if (!empty($results)) {
        // couldnt find usages of ephemeral quests so didnt modify this apart from new lines
        return "\n{$results[0]["description"]}\n";
    }

    $data = "";
    $results = $db->fetchAll("SELECT distinct description as description,gamets FROM currentmission where sess<>'ephemeral' and gamets>$hourThreshold order by gamets desc LIMIT 5 ");
    if (!empty($results)) {
        $data = "\n\n<current_plans>\n#Current Plans\n";
        $n = 0;
        foreach ($results as $row) {
            if ($n == 0) {
                $data .= "## Current: {$row["description"]}.\n";
            } elseif ($n == 1) {
                $data .= "## Previous: {$row["description"]}.\n";
            } else {
                break;
            }
            $n++;
        }
        $data .="</current_plans>\n";
    }

    // quests are an unordered list (because of how the aiagent plugin works - delete current, bulk update)
    // we would need to get clever with ignoring _questreset or expiring untouched quests, and using upserts on _quest
    // quests, and making "current" if _questdata updates after initial insert
    // for now lets just list all active quests rather than saying Current: xxx Previous: yyy
    // ! listing all quests could generate thousands tokens in prompt, let's limit
    $results = $db->fetchAll("SElECT  distinct name, briefing as description,gamets FROM quests order by gamets desc LIMIT 8"); 
    if (!$results) {
        Logger::info("No quests ".__FILE__." ".__LINE__." ".__FUNCTION__);
        return $data;
    }

    // dont think we need to limit it now since we dont require exactly two to format Current: xxx Previous: yyy
    //    if (sizeof($results)>2) {
    //        Logger::info("Too much quests ".__FILE__);
    //        return $data;
    //    }

    $data .= "\n\n<active_quests>\n#Active Quests\n";
    foreach ($results as $row) {
        $questDesc = trim($row["description"]);
        if (!empty($questDesc)) {
            $data .= "## {$row["name"]}: $questDesc\n";
        } else {
            $data .= "## {$row["name"]}\n";
        }
    }
    $data .="</active_quests>\n";
    return $data;
}


function DataLastRetFunc($actor, $lastNelements = -2)
{
    global $db;
    $lastDialogFull = array();
    $results = $db->fetchAll("select  a.data  as data  FROM  eventlog a 
    WHERE data like '%$actor%' and type in ('funcret')  order by gamets desc,ts desc LIMIT 1 OFFSET 0");
    $lastData = "";
    foreach ($results as $row) {
        $pattern = "/\{(.*?)\(/";
        preg_match($pattern, $row["data"], $matches);
        $functionName = $matches[1];
        $lastDialogFull[] = array('role' => 'function', 'name' => $functionName, 'content' => $row["data"]);

    }

    $lastDialogFullReversed = array_reverse($lastDialogFull);
    $lastDialog = array_slice($lastDialogFullReversed, $lastNelements);
    $last_location = null;

    // Remove Context Location part when repeated
    foreach ($lastDialog as $k => $message) {
        preg_match('/\(Context location: [^)]+?\)/', $message['content'], $matches);
        $current_location = isset($matches[1]) ? $matches[1] : null;
        if ($current_location === $last_location) {
            $message['content'] = preg_replace('/\(Context location: [^)]+?\)/', '', $message['content']);
        } else {
            $last_location = $current_location;
        }
        $lastDialog[$k]["content"] = $message['content'];
    }


    return $lastDialog;

}

function DataLastAction($actor)
{
    global $db;
    
    $lastDialogFull = array();
    $cnActor = $db->escape($actor);
    $results = $db->fetchOne("select  *  FROM public.actions_issued
    WHERE actorname='$cnActor' order by gamets desc,ts desc LIMIT 1 OFFSET 0");
    
    return $results;

}

function DataActorHasDied($actor)
{
    global $db;
    
    $lastDialogFull = array();
    $cnActor = $db->escape($actor);
    
    $rows = $GLOBALS["db"]->fetchAll("select 1 as n,gamets from eventlog where type='death'
        and (data like '%defeated $cnActor%' or data like '%killed $cnActor%')
        order by gamets desc limit 1");
    if ($rows)
        return true;
    
    return false;

}

function DataLastKnowDate() 
{
    if (isset($GLOBALS["CACHE_LAST_KNOW_DATE"])) {
        return $GLOBALS["CACHE_LAST_KNOW_DATE"];
    }

    global $db;
    
    // Get gamets and use PHP conversion function (skip SQL function to avoid PostgreSQL errors)
    $lastLoc=$db->fetchAll("SELECT a.gamets FROM eventlog a WHERE (type in ('infoloc')) ORDER BY gamets desc, ts desc LIMIT 1");
    if (is_array($lastLoc) && sizeof($lastLoc) > 0 && !empty($lastLoc[0]["gamets"])) {
        require_once(__DIR__ . "/utils_game_timestamp.php");
        $GLOBALS["CACHE_LAST_KNOW_DATE"] = convert_gamets2skyrim_long_date($lastLoc[0]["gamets"]);
        return $GLOBALS["CACHE_LAST_KNOW_DATE"];
    }
    
    // Fall back to parsing data field
    $lastLoc=$db->fetchAll("select  a.data  as data  FROM  eventlog a  WHERE (type in ('infoloc')) and (data like '%Current Date%')  order by gamets desc, ts desc LIMIT 1"); //make sure record has datetime
    if (!is_array($lastLoc) || sizeof($lastLoc)==0) {
        $GLOBALS["CACHE_LAST_KNOW_DATE"] = "";
        return "";
    }
    $re = '/(\w+), (\d{1,2}:\d{2} (?:AM|PM)), (\d{1,2})(?:st|nd|rd|th) of ([A-Za-z\'\ ]+), 4E (\d+)/'; //extract also for months with apostrophe like Sun's Something
    if (preg_match($re, $lastLoc[0]["data"], $matches, PREG_OFFSET_CAPTURE, 0)) {
        $GLOBALS["CACHE_LAST_KNOW_DATE"] = $matches[0][0];
        return $GLOBALS["CACHE_LAST_KNOW_DATE"];
    } else {
        Logger::info("DataLastKnowDate: NO match found");
        $GLOBALS["CACHE_LAST_KNOW_DATE"] = "";
        return "";
    }
}


function DataLastKnownLocation()
{
    if (isset($GLOBALS["CACHE_LAST_KNOWN_LOCATION"])) {
        return $GLOBALS["CACHE_LAST_KNOWN_LOCATION"];
    }

    global $db;

    $lastLoc=$db->fetchAll("select  a.data  as data  FROM  eventlog a  WHERE type in ('infoloc','location') and data like '%(Context%'  order by gamets desc,ts desc LIMIT 1 OFFSET 0");
    if (!is_array($lastLoc) || sizeof($lastLoc)==0) {
        $GLOBALS["CACHE_LAST_KNOWN_LOCATION"] = "";
        return "";
    }
    /*
    $re = '/Context location: ([\w\ \']*)/';
    preg_match($re, $lastLoc[0]["data"], $matches, PREG_OFFSET_CAPTURE, 0);
    */
    $GLOBALS["CACHE_LAST_KNOWN_LOCATION"] = $lastLoc[0]["data"];
    return $GLOBALS["CACHE_LAST_KNOWN_LOCATION"];

}

function normalizeLocationContextToken($value, $stripStateSuffix = false)
{
    $value = trim((string) $value);
    if ($value === "") {
        return "";
    }

    $value = preg_replace('/\s+/u', ' ', $value);
    $value = trim((string) $value, " \t\n\r\0\x0B,");

    if ($stripStateSuffix) {
        $value = preg_replace('/\s+(outdoors|interior)\s*$/iu', '', $value);
        $value = trim((string) $value, " \t\n\r\0\x0B,");
    }

    return $value;
}

function getCanonicalHoldGroups()
{
    return [
        "Eastmarch" => ["Eastmarch"],
        "Falkreath Hold" => ["Falkreath Hold", "Falkreath"],
        "Haafingar" => ["Haafingar"],
        "Hjaalmarch" => ["Hjaalmarch"],
        "The Pale" => ["The Pale", "the Pale"],
        "The Reach" => ["The Reach"],
        "The Rift" => ["The Rift"],
        "Whiterun Hold" => ["Whiterun Hold", "Whiterun"],
        "Winterhold" => ["Winterhold"],
    ];
}

function canonicalizeHoldName($value)
{
    static $aliasMap = null;

    if ($aliasMap === null) {
        $aliasMap = [];
        foreach (getCanonicalHoldGroups() as $canonical => $aliases) {
            foreach ($aliases as $alias) {
                $aliasKey = strtolower(normalizeLocationContextToken($alias, true));
                if ($aliasKey !== "") {
                    $aliasMap[$aliasKey] = $canonical;
                }
            }
        }
    }

    $valueKey = strtolower(normalizeLocationContextToken($value, true));
    if ($valueKey === "") {
        return "";
    }

    return $aliasMap[$valueKey] ?? "";
}

function getCanonicalHoldAliases($value)
{
    $canonical = canonicalizeHoldName($value);
    $groups = getCanonicalHoldGroups();

    if ($canonical !== "" && isset($groups[$canonical])) {
        return $groups[$canonical];
    }

    $value = normalizeLocationContextToken($value, true);
    return $value !== "" ? [$value] : [];
}

function DataLastKnownLocationContextParts($cached = false)
{
    if (isset($GLOBALS["CACHE_LAST_KNOWN_LOCATION_CONTEXT_PARTS"])) {
        return $GLOBALS["CACHE_LAST_KNOWN_LOCATION_CONTEXT_PARTS"];
    }

    global $db;

    $lastLoc = $db->fetchAll("select  a.data  as data  FROM  eventlog a  WHERE type in ('infoloc','location','request') and data like '%(Context%'  order by gamets desc,ts desc LIMIT 1 OFFSET 0");
    if (!is_array($lastLoc) || sizeof($lastLoc) == 0) {
        $GLOBALS["CACHE_LAST_KNOWN_LOCATION_CONTEXT_PARTS"] = [
            "location" => "",
            "location_base" => "",
            "hold_raw" => "",
        ];
        return $GLOBALS["CACHE_LAST_KNOWN_LOCATION_CONTEXT_PARTS"];
    }

    $location = "";
    $holdRaw = "";
    if (preg_match('/Context\s*(?:new\s*)?location:\s*([^,]+?)(?:,|$)/u', $lastLoc[0]["data"], $locationMatch) && isset($locationMatch[1])) {
        $location = normalizeLocationContextToken($locationMatch[1], false);
    }

    if (preg_match('/Hold:\s*([^,\)]+?)(?:,|\)|$)/u', $lastLoc[0]["data"], $holdMatch) && isset($holdMatch[1])) {
        $holdRaw = normalizeLocationContextToken($holdMatch[1], false);
    }

    $GLOBALS["CACHE_LAST_KNOWN_LOCATION_CONTEXT_PARTS"] = [
        "location" => $location,
        "location_base" => normalizeLocationContextToken($location, true),
        "hold_raw" => $holdRaw,
    ];

    return $GLOBALS["CACHE_LAST_KNOWN_LOCATION_CONTEXT_PARTS"];
}

function DataLastKnownLocationBaseHuman($cached = false)
{
    $parts = DataLastKnownLocationContextParts($cached);
    return $parts["location_base"] ?? "";
}

function locationFieldMatchesCandidate($row, $field, $candidateKey)
{
    if (!isset($row[$field])) {
        return false;
    }

    return strtolower(normalizeLocationContextToken($row[$field], true)) === $candidateKey;
}

function resolveCanonicalHoldFromLocationRows($rows, $candidateKey)
{
    if (!is_array($rows) || empty($rows) || $candidateKey === "") {
        return "";
    }

    $prioritizedMatches = [
        ["matchField" => "name", "valueField" => "hold"],
        ["matchField" => "name", "valueField" => "region"],
        ["matchField" => "region", "valueField" => "hold"],
        ["matchField" => "hold", "valueField" => "hold"],
    ];

    foreach ($prioritizedMatches as $rule) {
        foreach ($rows as $row) {
            if (locationFieldMatchesCandidate($row, $rule["matchField"], $candidateKey)) {
                $canonical = canonicalizeHoldName($row[$rule["valueField"]] ?? "");
                if ($canonical !== "") {
                    return $canonical;
                }
            }
        }
    }

    foreach (["hold", "region", "name"] as $field) {
        foreach ($rows as $row) {
            $canonical = canonicalizeHoldName($row[$field] ?? "");
            if ($canonical !== "") {
                return $canonical;
            }
        }
    }

    return "";
}

function lookupCanonicalHoldByLocationCandidate($candidate)
{
    global $db;

    $candidateKey = strtolower(normalizeLocationContextToken($candidate, true));
    if ($candidateKey === "") {
        return "";
    }

    if (isset($GLOBALS["CACHE_CANONICAL_HOLD_BY_LOCATION_CANDIDATE"][$candidateKey])) {
        return $GLOBALS["CACHE_CANONICAL_HOLD_BY_LOCATION_CANDIDATE"][$candidateKey];
    }

    $candidateEsc = $db->escape($candidateKey);
    $rows = $db->fetchAll(
        "SELECT name, region, hold
           FROM locations
          WHERE LOWER(name) = '{$candidateEsc}'
             OR LOWER(region) = '{$candidateEsc}'
             OR LOWER(hold) = '{$candidateEsc}'"
    );

    $canonical = resolveCanonicalHoldFromLocationRows($rows, $candidateKey);
    $GLOBALS["CACHE_CANONICAL_HOLD_BY_LOCATION_CANDIDATE"][$candidateKey] = $canonical;

    return $canonical;
}

function DataLastKnownCanonicalHoldHuman($cached = false)
{
    $cacheKey = "HOLD_CANONICAL";
    if (isset($GLOBALS["CACHE_LAST_KNOWN_LOCATION_HUMAN"][$cacheKey])) {
        return $GLOBALS["CACHE_LAST_KNOWN_LOCATION_HUMAN"][$cacheKey];
    }

    $parts = DataLastKnownLocationContextParts($cached);
    $canonical = "";

    $currentLocation = $parts["location_base"] ?? "";
    if ($currentLocation !== "") {
        $canonical = lookupCanonicalHoldByLocationCandidate($currentLocation);
    }

    $reportedHold = $parts["hold_raw"] ?? "";
    if ($canonical === "" && $reportedHold !== "") {
        $canonical = canonicalizeHoldName($reportedHold);
    }
    if ($canonical === "" && $reportedHold !== "") {
        $canonical = lookupCanonicalHoldByLocationCandidate($reportedHold);
    }
    if ($canonical === "" && $reportedHold !== "") {
        $canonical = normalizeLocationContextToken($reportedHold, true);
    }

    $GLOBALS["CACHE_LAST_KNOWN_LOCATION_HUMAN"][$cacheKey] = $canonical;
    return $canonical;
}

function DataLastKnownLocationHuman($hold=false,$cached=false)
{

    $cache_key = $hold ? "HOLD" : "LOC";
    if (isset($GLOBALS["CACHE_LAST_KNOWN_LOCATION_HUMAN"][$cache_key]))
        return $GLOBALS["CACHE_LAST_KNOWN_LOCATION_HUMAN"][$cache_key];

    $parts = DataLastKnownLocationContextParts($cached);
    $val = $hold ? ($parts["hold_raw"] ?? "") : ($parts["location"] ?? "");

    $GLOBALS["CACHE_LAST_KNOWN_LOCATION_HUMAN"][$cache_key] = $val;
    return $val;

}

function buildWorldPrompt($gamets = 0)
{
    $worldLines = [];

    $currentLoc = trim(DataLastKnownLocationHuman(false, false));
    if ($currentLoc !== "") {
        $worldLines[] = "  <location>" . xml_fragment_escape_text($currentLoc) . "</location>";
    }

    $currentHold = trim(DataLastKnownCanonicalHoldHuman(false));
    if ($currentHold !== "") {
        $worldLines[] = "  <hold>" . xml_fragment_escape_text($currentHold) . "</hold>";
    }

    $currentWeather = trim(DataLastKnownWeatherHuman());
    if ($currentWeather !== "") {
        $worldLines[] = "  <weather>" . xml_fragment_escape_text($currentWeather) . "</weather>";
    }

    $f_gamets = floatval($gamets);
    if ($f_gamets <= 0.0) {
        $f_gamets = floatval(DataLastKnownGameTS());
    }

    if ($f_gamets > 0.0) {
        $tsTime = gamets2timestamp($f_gamets);
        $currentDate = trim(convert_gamets2skyrim_long_date_no_time($f_gamets));
        $currentTime = date('g:i A', $tsTime);
        $dayPart = hour2part_of_day(date('H', $tsTime));

        if ($currentDate !== "") {
            $worldLines[] = "  <date>" . xml_fragment_escape_text($currentDate) . "</date>";
        }
        $worldLines[] = "  <time>" . xml_fragment_escape_text("{$currentTime}, {$dayPart}") . "</time>";
    }

    if (empty($worldLines)) {
        return "";
    }

    return "\n\n<world>\n" . implode("\n", $worldLines) . "\n</world>";
}

function DataLastKnownWeatherHuman()
{
    $cacheKey = "WEATHER";
    if (isset($GLOBALS["CACHE_LAST_KNOWN_LOCATION_HUMAN"][$cacheKey])) {
        return $GLOBALS["CACHE_LAST_KNOWN_LOCATION_HUMAN"][$cacheKey];
    }

    global $db;

    $lastWeather = $db->fetchAll("select a.data as data FROM eventlog a WHERE type in ('location','infoloc','request') and lower(data) like '%current weather:%' order by gamets desc,ts desc LIMIT 1 OFFSET 0");
    if (!is_array($lastWeather) || sizeof($lastWeather) == 0) {
        $GLOBALS["CACHE_LAST_KNOWN_LOCATION_HUMAN"][$cacheKey] = "";
        return "";
    }

    $weatherValue = "";
    if (preg_match('/current weather:\s*([^\)]+)/i', $lastWeather[0]["data"], $matches) && isset($matches[1])) {
        $rawWeather = trim($matches[1]);
        $prefix = "";
        if (stripos($rawWeather, 'outdoors it is ') === 0) {
            $prefix = 'outdoors it is ';
            $rawWeather = trim(substr($rawWeather, strlen($prefix)));
        }

        $rawParts = array_filter(array_map('trim', explode(',', $rawWeather)), function ($part) {
            return $part !== "";
        });

        $weatherMap = [
            'pleasant' => 'Pleasant',
            'clear' => 'Clear',
            'cloudy' => 'Cloudy',
            'rainy' => 'Raining',
            'raining' => 'Raining',
            'snowy' => 'Snowning',
            'snowning' => 'Snowning',
            'foggy' => 'Foggy',
            'unknown' => 'Unknown',
        ];

        if (!empty($rawParts)) {
            $weatherParts = [];
            foreach ($rawParts as $part) {
                $normalizedPart = strtolower($part);
                $displayPart = $weatherMap[$normalizedPart] ?? $part;
                if (!in_array($displayPart, $weatherParts, true)) {
                    $weatherParts[] = $displayPart;
                }
            }
            $weatherValue = $prefix . implode(', ', $weatherParts);
        } else {
            $normalizedWeather = strtolower(trim($rawWeather, " ,"));
            $weatherValue = $prefix . ($weatherMap[$normalizedWeather] ?? $rawWeather);
        }
    }

    $GLOBALS["CACHE_LAST_KNOWN_LOCATION_HUMAN"][$cacheKey] = $weatherValue;
    return $weatherValue;
}


function PackIntoSummary($onlyMissingDiary=false)
{

    global $db;
    
    if ($onlyMissingDiary) {
        $results = $db->query("insert into memory_summary (gamets_truncated,n,packed_message,summary,classifier,uid,companions,scope)
        select gamets,1,message,message,'diary',uid,
            case
                when nullif(trim(speaker), '') is null then ''
                else '|' || trim(both '|' from trim(speaker)) || '|'
            end,
            'global'
        from memory
        where event in ('diary','auto_diary','backgroundlife_diary')
        and uid not in (select uid from memory_summary where classifier in  ('diary','auto_diary','backgroundlife_diary'))");

        $maxRow=0;

    } else {
        $lastGameTsRecord = $GLOBALS["db"]->fetchOne("select gamets as gamets from eventlog order by gamets desc LIMIT 1"); // 2.1ms
        $results = $GLOBALS["db"]->fetchAll("select gamets_truncated from memory_summary order by gamets_truncated desc LIMIT 1"); // 0.5ms, faster 

        $maxRow = isset($results[0]["gamets_truncated"]) ? intval($results[0]["gamets_truncated"]) : 0;
        $minRow = intval($lastGameTsRecord["gamets"]);
        $minRowTs = intval($lastGameTsRecord["gamets"] -  ( 1 /0.0000024));
        
        $pfi = intval($GLOBALS["FEATURES"]["MEMORY_EMBEDDING"]["AUTO_CREATE_SUMMARY_INTERVAL"] ?? 10) * 100000;
        $minEventsPerSummary = intval($GLOBALS["FEATURES"]["MEMORY_EMBEDDING"]["AUTO_CREATE_SUMMARY_MIN_EVENTS"] ?? 5);
        if ($minEventsPerSummary < 1) {
            $minEventsPerSummary = 1;
        }
        // Queue boundaries are hard-cut by location changes.
        // Unknown location entries are isolated into their own queue to avoid cross-location mixing.
        $query="insert into memory_summary (gamets_truncated,n,packed_message,summary,classifier,uid,scope)
                                with source_rows as (
                                    select
                                        uid,
                                        gamets,
                                        coalesce(ts, 0) as ts,
                                        message,
                                        round(gamets::numeric/$pfi, 0) as time_bucket,
                                        trim(regexp_replace(coalesce(
                                            substring(message from '(?i)\\(context\\s+(?:new\\s+)?location:\\s*([^,\\)]+)'),
                                            substring(message from '(?i)\\(at\\s+([^\\)]+)\\)'),
                                            ''
                                        ), '\\s+', ' ', 'g')) as location_key
                                    from memory_v
                                    where message not ilike 'Dear Diary%'
                                      and gamets>$maxRow
                                ),
                                normalized_rows as (
                                    select
                                        uid,
                                        gamets,
                                        ts,
                                        message,
                                        time_bucket,
                                        case
                                            when location_key='' then null
                                            else lower(location_key)
                                        end as location_key
                                    from source_rows
                                ),
                                queue_boundaries as (
                                    select
                                        uid,
                                        gamets,
                                        ts,
                                        message,
                                        time_bucket,
                                        location_key,
                                        lag(location_key) over (order by gamets asc, ts asc, uid asc) as prev_location_key,
                                        lag(time_bucket) over (order by gamets asc, ts asc, uid asc) as prev_time_bucket
                                    from normalized_rows
                                ),
                                queued_rows as (
                                    select
                                        uid,
                                        gamets,
                                        ts,
                                        message,
                                        case
                                            when prev_time_bucket is null then 1
                                            when location_key is null then 1
                                            when prev_location_key is null then 1
                                            when location_key<>prev_location_key then 1
                                            when time_bucket<>prev_time_bucket then 1
                                            else 0
                                        end as is_new_queue
                                    from queue_boundaries
                                ),
                                grouped_rows as (
                                    select
                                        uid,
                                        gamets,
                                        ts,
                                        message,
                                        sum(is_new_queue) over (
                                            order by gamets asc, ts asc, uid asc
                                            rows between unbounded preceding and current row
                                        ) as queue_id
                                    from queued_rows
                                )
                                select * from (
                                    select
                                        max(gamets) as gamets_truncated,
                                        count(*) as n,
                                        STRING_AGG(message, chr(13) || chr(10) || chr(13) || chr(10) order by gamets asc, ts asc, uid asc) AS packed_message,
                                        NULL as summary,
                                        'dialogue' as classifier,
                                        max(uid) as uid,
                                        'global' as scope
                                    from grouped_rows
                                    group by queue_id
                                    having count(*)>=$minEventsPerSummary
                                    order by max(gamets) asc
                                ) as T
                                where gamets_truncated>$maxRow and gamets_truncated<$minRowTs";
        //error_log($query);

        $results = $db->query($query);
        
        $results = $db->query("insert into memory_summary (gamets_truncated,n,packed_message,summary,classifier,uid,companions,scope)
                                    select gamets,1,message,message,'diary',uid,
                                        case
                                            when nullif(trim(speaker), '') is null then ''
                                            else '|' || trim(both '|' from trim(speaker)) || '|'
                                        end,
                                        'global'
                                    from memory
                                    where event='diary'
                                    and gamets>$maxRow
                                ");

    }

    
    return $maxRow;
}

if (!function_exists('chimFilterRechatHistorySinceLatestInput')) {
    function chimFilterRechatHistorySinceLatestInput(array $historyRows)
    {
        $chainRows = [];

        foreach ($historyRows as $row) {
            $eventType = strtolower(trim((string)($row['type'] ?? '')));
            if ($eventType === '') {
                continue;
            }

            if (in_array($eventType, ['inputtext', 'inputtext_s', 'ginputtext', 'ginputtext_s', 'narrator_inputtext'], true)) {
                // A fresh player turn must reset the rechat chain. The first rechat after player input
                // should therefore start at round 0 instead of inheriting an older chain budget.
                $chainRows = [];
                continue;
            }

            if (in_array($eventType, ['rechat', 'narration'], true)) {
                $chainRows[] = $row;
            }
        }

        return $chainRows;
    }
}

function DataRechatHistory()
{

    global $db;
    // Include only actual rechat turns. Player input is not part of the rechat budget.
    // Keep the row payload so callers can scope the count to the current speaker.
    $lastRechat=$db->fetchAll("select type,data,gamets FROM  eventlog a  WHERE type in ('rechat','narration') 
    and localts>".(time()-120)."  order by gamets desc,ts desc LIMIT 10 OFFSET 0");
    
    return $lastRechat;

}



function extractDialogueTarget($string) {
    // Check if the string contains a directed-dialogue tag.
    if ($string && preg_match('/\((?:(?:talking|whispering|shouting)|speaking privately)\s+to\s+/i', $string)) {
        // Extract the target's name using regular expression
        preg_match('/\((?:(?:talking|whispering|shouting)|speaking privately)\s+to\s+([^\)]+)\)/i', $string, $matches);
        
        // Check if a match is found and extract the target's name
        if (isset($matches[1])) {
            $target = $matches[1];

            // Remove the directed-dialogue tag from the original string
            $cleanedString = preg_replace('/\((?:(?:talking|whispering|shouting)|speaking privately)\s+to\s+[^\)]+\)/i', '', $string);
            if (strpos($cleanedString,"{$GLOBALS["HERIKA_NAME"]}:")===0) {
                $cleanedString=str_replace("{$GLOBALS["HERIKA_NAME"]}:","",$cleanedString);
            }
            
            return ['target' => $target, 'cleanedString' => trim($cleanedString)];
        }
    }

    // Return the original string if no target is found
    return ['target' => null, 'cleanedString' => $string];
}

function DataGetLastReadedBook() {
    global $db;
    
    
    // To push where the book was taken from.
    $results = $db->fetchAll("select data from eventlog where data is not null and type='itemfound' and data like '%book%' 
    order by gamets desc,ts desc,localts desc,rowid desc LIMIT 1 OFFSET 0");
    
    if ($results) {
        $bookOnlyContext[] = array('role' => "user", 'content' => $results[0]["data"]);
    }
    
    
    $lastData = "";
    $results = $db->fetchAll("select content from books where content is not null
    order by gamets desc,ts desc,localts desc,rowid desc LIMIT 1 OFFSET 0");
    $lastData = "";
    
    $bookOnlyContext[] = array('role' => "user", 'content' => $results[0]["content"]);

    return $bookOnlyContext;
    
}

function DataGetTrackedStat($stat) {
    global $db;
    
    // Try to get from core_player table first
    try {
        require_once(__DIR__ . DIRECTORY_SEPARATOR . "core" . DIRECTORY_SEPARATOR . "player.class.php");
        $player = new Player();
        $value = $player->get($stat);
        
        if ($value !== null) {
            return json_encode([['id' => $stat, 'value' => $value]]);
        }
    } catch (Exception $e) {
        Logger::debug("Could not read stat from core_player: " . $e->getMessage());
    }
    
    // Fallback to conf_opts
    $escapedStat = $db->escape($stat);
    $results = $db->fetchAll("select * from conf_opts where id='{$escapedStat}'");
    
    return json_encode($results);
}

function ResolvePlayerBackstory($player = null): string
{
    $playerBio = '';

    if ($player === null) {
        try {
            require_once(__DIR__ . DIRECTORY_SEPARATOR . "core" . DIRECTORY_SEPARATOR . "player.class.php");
            $player = new Player();
        } catch (Throwable $e) {
            Logger::debug("Could not initialize Player while resolving backstory: " . $e->getMessage());
        }
    }

    if ($player instanceof Player) {
        try {
            $playerBio = trim((string)($player->get('bio') ?? ''));
        } catch (Throwable $e) {
            Logger::debug("Could not read player bio from core_player: " . $e->getMessage());
        }
    }

    if ($playerBio !== '') {
        return $playerBio;
    }

    $legacyPlayerBio = trim((string)($GLOBALS["PLAYER_BIOS"] ?? ''));
    if ($legacyPlayerBio !== '') {
        return $legacyPlayerBio;
    }

    if (isset($GLOBALS["db"]) && is_object($GLOBALS["db"]) && method_exists($GLOBALS["db"], 'fetchOne')) {
        try {
            $legacyPlayerBioRow = $GLOBALS["db"]->fetchOne("SELECT value FROM conf_opts WHERE id='PLAYER_BIOS' LIMIT 1");
            $legacyPlayerBio = trim((string)($legacyPlayerBioRow['value'] ?? ''));
            if ($legacyPlayerBio !== '') {
                return $legacyPlayerBio;
            }
        } catch (Throwable $e) {
            Logger::debug("Could not read legacy PLAYER_BIOS from conf_opts: " . $e->getMessage());
        }
    }

    return '';
}

function DataGetCurrentPartyConf() {
    global $db;

    $results = $db->fetchAll("select value from conf_opts where id='CurrentParty'");
    if (is_array($results) && sizeof($results)>0) {
        // The C++ code stores party data like: {"name":"Lydia"},{"name":"Serana"},
        // We need to wrap it in brackets and remove trailing comma to make valid JSON
        $partyData = trim($results[0]["value"]);
        if (empty($partyData)) {
            return json_encode([]);
        }
        
        // Remove trailing comma if present
        $partyData = rtrim($partyData, ',');
        
        // Wrap in brackets to make it a valid JSON array
        $jsonString = "[" . $partyData . "]";
        
        $guys = json_decode($jsonString, true);
        if (!is_array($guys)) {
            Logger::warn("DataGetCurrentPartyConf: Failed to parse party JSON: " . $jsonString);
            return json_encode([]);
        }
        
        $finalparty=[];
        foreach ($guys as $guy) {
            if (isset($guy["name"])) {
                $finalparty[$guy["name"]]=$guy;
                $npcMaster=new NpcMaster();
                $currentNpcData=$npcMaster->getByName($guy["name"]);
                if (isset($currentNpcData["core"])&&!empty($currentNpcData["core"]))
                    $finalparty[$guy["name"]]["core"]=$currentNpcData["core"];

            }
        }
    
        return json_encode($finalparty);
    } else
        return json_encode([]);
    
}

function DataBeingsInRange()
{
    if (isset($GLOBALS["CACHE_BEINGS_IN_RANGE"])) {
        return $GLOBALS["CACHE_BEINGS_IN_RANGE"];
    }

    global $db;

    $lastLoc=$db->fetchAll("select  a.data  as data  FROM  eventlog a  WHERE type in ('infonpc')  order by gamets desc,ts desc LIMIT 1 OFFSET 0");
    if (!is_array($lastLoc) || sizeof($lastLoc)==0) {
        $GLOBALS["CACHE_BEINGS_IN_RANGE"] = "";
        return "";
    }
    
    $beings=strtr($lastLoc[0]["data"],["(beings in range:"=>""]);
    $beingsArray=explode(",",$beings);
    $beingsArrayNew=[];
    $beingsArrayNew[]="{$GLOBALS["PLAYER_NAME"]}";  // Add player to beings in range
    foreach ($beingsArray as $k=>$v) {
        if (strpos($v,"(")===false) 
            if (strpos($v,"Horse")!==0) 
                if (strpos($v,"Chicken")!==0) 
                    $beingsArrayNew[]=strtr($v,[")"=>""]);
            
        
    }
    $beingsFormatted=implode("|",$beingsArrayNew);
    
    $GLOBALS["CACHE_BEINGS_IN_RANGE"] = "|".$beingsFormatted."|";
    return $GLOBALS["CACHE_BEINGS_IN_RANGE"];
}

function DataBeingsInRangeExcluding($excludeNPC="", $excludePlayer=true)
{
    if (isset($GLOBALS["CACHE_BEINGS_IN_RANGE_EXCLUDING"][$excludeNPC][(int)$excludePlayer])) {
        return $GLOBALS["CACHE_BEINGS_IN_RANGE_EXCLUDING"][$excludeNPC][(int)$excludePlayer];
    }

    global $db;

    $lastLoc=$db->fetchAll("select  a.data  as data  FROM  eventlog a  WHERE type in ('infonpc')  order by gamets desc,ts desc LIMIT 1 OFFSET 0");
    if (!is_array($lastLoc) || sizeof($lastLoc)==0) {
        $GLOBALS["CACHE_BEINGS_IN_RANGE_EXCLUDING"][$excludeNPC][(int)$excludePlayer] = "";
        return "";
    }
    if (trim($excludeNPC) > "")
        $exNPC = trim($excludeNPC);
    else
        $exNPC = "x_y_z";
            
    $beings=strtr($lastLoc[0]["data"],["(beings in range:"=>""]);
    $beingsArray=explode(",",$beings);
    $beingsArrayNew=[];
    if (!$excludePlayer)
        $beingsArrayNew[]="{$GLOBALS["PLAYER_NAME"]}";  // Add player to beings in range
    foreach ($beingsArray as $k=>$v) {
        if (strpos($v,")")===false) {
            if (strpos($v,"Horse")!==0) 
                if (strpos($v,"Chicken")!==0) 
                    if (strpos($v,$exNPC)!==0) 
                        $beingsArrayNew[]=$v;
        }
    }
    $beingsFormatted=implode("|",$beingsArrayNew);
    error_log("<{$lastLoc[0]["data"]}> $beingsFormatted");
    $GLOBALS["CACHE_BEINGS_IN_RANGE_EXCLUDING"][$excludeNPC][(int)$excludePlayer] = "|".$beingsFormatted."|";
    return $GLOBALS["CACHE_BEINGS_IN_RANGE_EXCLUDING"][$excludeNPC][(int)$excludePlayer];
}

function DataBeingsOrDeathsInRangeExcluding($excludeNPC="", $excludePlayer=true)
{
    if (isset($GLOBALS["CACHE_BEINGS_OR_DEATHS_IN_RANGE_EXCLUDING"][$excludeNPC][(int)$excludePlayer])) {
        return $GLOBALS["CACHE_BEINGS_OR_DEATHS_IN_RANGE_EXCLUDING"][$excludeNPC][(int)$excludePlayer];
    }

    global $db;

    $lastLoc=$db->fetchAll("select  a.data  as data  FROM  eventlog a  WHERE type in ('infonpc')  order by gamets desc,ts desc LIMIT 1 OFFSET 0");
    if (!is_array($lastLoc) || sizeof($lastLoc)==0) {
        $GLOBALS["CACHE_BEINGS_OR_DEATHS_IN_RANGE_EXCLUDING"][$excludeNPC][(int)$excludePlayer] = "";
        return "";
    }
    if (trim($excludeNPC) > "")
        $exNPC = trim($excludeNPC);
    else
        $exNPC = "x_y_z";
            
    $beings=strtr($lastLoc[0]["data"],["(beings in range:"=>""]);
    $beingsArray=explode(",",$beings);
    $beingsArrayNew=[];
    if (!$excludePlayer)
        $beingsArrayNew[]="{$GLOBALS["PLAYER_NAME"]}";  // Add player to beings in range

    foreach ($beingsArray as $k=>$v) {
        if (strpos($v,")")!==0) {
            if (strpos($v,"Horse")!==0) 
                if (strpos($v,"Chicken")!==0) 
                    if (strpos($v,$exNPC)!==0) 
                        $beingsArrayNew[]=$v;
        }
    }
    $beingsFormatted=implode("|",$beingsArrayNew);
    error_log("<{$lastLoc[0]["data"]}> $beingsFormatted");
    $GLOBALS["CACHE_BEINGS_OR_DEATHS_IN_RANGE_EXCLUDING"][$excludeNPC][(int)$excludePlayer] = "|".$beingsFormatted."|";
    return $GLOBALS["CACHE_BEINGS_OR_DEATHS_IN_RANGE_EXCLUDING"][$excludeNPC][(int)$excludePlayer];
}

function chimDataActorStatusSuffixPattern()
{
    return '/\s*\((?:busy|hostile|in combat|far away|too far away|restrained|dead|disabled|unavailable|audible|narrator|checking(?: hearing|: [^)]+)?|can hear you(?:, muffled|: [^)]+)?|can[\'"]?t hear you(?: clearly)?(?:: [^)]+)?|no (?:target|crosshair target))\)\s*$/iu';
}

function chimDataStripActorStateSuffix($name)
{
    $name = trim((string)$name);
    if ($name === "") {
        return "";
    }

    $name = trim($name, "|");
    $name = preg_replace(chimDataActorStatusSuffixPattern(), '', $name);
    return trim((string)$name);
}

function chimDataActorStatusBlocksCloseRange($token, $includeBusy = false)
{
    if (!preg_match('/\s*\(([^()]*)\)\s*$/u', (string)$token, $matches)) {
        return false;
    }

    $status = strtolower(trim((string)$matches[1]));
    if ($status === "") {
        return false;
    }

    if (strpos($status, "can hear you") === 0) {
        return false;
    }

    if ($includeBusy && $status === "busy") {
        return false;
    }

    return preg_match('/^(?:busy|hostile|in combat|far away|too far away|restrained|dead|disabled|unavailable|checking|can[\'"]?t hear you|no target|no crosshair target)/i', $status) === 1;
}

function DataBeingsInCloseRange($excludeFarAway=false, $includeBusy=false)
{

    global $db;

    $s_res = "";
    
    $lastLoc=$db->fetchAll("SELECT a.data as data FROM eventlog a WHERE type in ('infonpc_close') order by gamets desc,ts desc LIMIT 1 OFFSET 0");
    if (!is_array($lastLoc) || sizeof($lastLoc)==0) {
        return "";
    }
    
    $s_npcs = trim($lastLoc[0]["data"] ?? "");
    if (strlen($s_npcs) > 0) {
        if (stripos($s_npcs, "beings in range") !== false) {
            $beings=strtr($s_npcs,["beings in range:"=>""]);
        } else 
            $beings=$s_npcs;
        $beingsArray=preg_split('/[\/|]/', $beings);
        if (!is_array($beingsArray))
            $beingsArray=[];
        $beingsArrayNew=[];
        foreach ($beingsArray as $k=>$v) {
            $v = trim((string)$v);
            if ($excludeFarAway && chimDataActorStatusBlocksCloseRange($v, $includeBusy))
                continue;
            if (preg_match('/\((?:dead|disabled)\)\s*$/i', $v)) //??
                continue;
            if (empty($v))
                continue;
            $actorName = chimDataStripActorStateSuffix($v);
            if (empty($actorName))
                continue;
            //if (strpos($v,")")===false) 
                if (strpos($actorName,"Horse")!==0)
                    if (strpos($actorName,"Chicken")!==0)
                    if (strpos($actorName,"Goat")!==0)
                    if (strpos($actorName,"House Cat")!==0)
                    if (strpos($actorName,"Stray Cat")!==0)
                    if (strpos($actorName,"Cow")!==0)
                    if (strpos($actorName,"Deer")!==0)
                    if (strpos($actorName,"Elk")!==0)
                    if (strpos($actorName,"Bear")!==0)
                    if (strpos($actorName,"Rabbit")!==0)
                    if (strpos($actorName,"Troll")!==0)
                    if (strpos($actorName,"Fox")!==0)
                        $beingsArrayNew[]=$actorName;
        }
        $beingsFormatted=implode("|",$beingsArrayNew);
        $s_res = "|".$beingsFormatted."|";
    }

    return $s_res;
}

function DataItemsInCloseRange()
{
    global $db;

    $lastItems = $db->fetchAll("SELECT a.data as data FROM eventlog a WHERE type in ('infoitems') order by gamets desc,ts desc LIMIT 1 OFFSET 0");
    
    if (!is_array($lastItems) || sizeof($lastItems) == 0) {
        return "";
    }
    
    $s_items = trim($lastItems[0]["data"] ?? "");
    
    if (strlen($s_items) > 0) {
        if (stripos($s_items, "items in range") !== false) {
            // Extract items from "(items in range:0x123:Item1,0x456:Item2)"
            // Use greedy match (.+) to capture everything including (STEALING) and (LOOKING AT) tags until the LAST closing paren
            if (preg_match('/\(items in range:(.+)\)/', $s_items, $matches)) {
                $items = $matches[1];
                
                // Translate (LOOKING AT) marker to natural language
                // Replace "(LOOKING AT)" with "{$GLOBALS['PLAYER_NAME']} is looking at"
                $playerName = $GLOBALS["PLAYER_NAME"] ?? "Player";
                $items = preg_replace_callback(
                    '/([^,]+)\s*\(LOOKING AT\)/',
                    function($match) use ($playerName) {
                        // $match[1] is the item (e.g., "0x123:0x456:Soul Gem (Grand)")
                        return trim($match[1]) . " ({$playerName} is looking at this)";
                    },
                    $items
                );
                
                return $items; // Return comma-separated list with translated markers
            }
        }
    }
    
    return "";
}

function chimNormalizeExplicitActorRefTarget($target)
{
    $target = trim((string)$target);
    if ($target === "") {
        return "";
    }

    $pattern = '/\s*\[\s*RefID\s*:\s*(?:0x)?([0-9A-Fa-f]{1,8})\s*\]\s*/i';
    if (!preg_match($pattern, $target, $matches)) {
        return "";
    }

    $refId = strtoupper(str_pad($matches[1], 8, "0", STR_PAD_LEFT));
    $fallbackName = trim((string)preg_replace($pattern, " ", $target));
    $fallbackName = trim($fallbackName, " \t\n\r\0\x0B,;");

    return ($fallbackName !== "" ? $fallbackName . " " : "") . "[RefID: " . $refId . "]";
}

// Find actor name with closest name, useful to sanitize actions parameters
function FindClosestActorName($actorName)
{
    global $db;

    $lastLoc = $db->fetchAll("SELECT a.data AS data FROM eventlog a WHERE type IN ('infonpc_close') ORDER BY gamets DESC, ts DESC LIMIT 1 OFFSET 0");
    if (!is_array($lastLoc) || sizeof($lastLoc) == 0) {
        return "";
    }

    $beingsArrayCleaned = ParseNpcCloseActorNames($lastLoc[0]["data"]);

    if (empty($beingsArrayCleaned)) {
        return "";
    }

    // Find the closest match using Levenshtein distance
    $closest = null;
    $shortest = -1;

    foreach ($beingsArrayCleaned as $name) {
        $lev = levenshtein($actorName, $name);

        if ($lev == 0) {
            return $name; // Exact match
        }

        if ($lev < $shortest || $shortest < 0) {
            $closest = $name;
            $shortest = $lev;
        }
    }

    return $closest;
}

function FindClosestNPCName($actorName)
{
    global $db;

    $lastLoc = $db->fetchAll("SELECT a.data as people FROM eventlog a WHERE type IN ('infonpc_close') ORDER BY gamets DESC, ts DESC LIMIT 1 OFFSET 0");
    if (!is_array($lastLoc) || sizeof($lastLoc) == 0) {
        error_log("Note: no FindClosestNPCName data");
        return "";
    }

    $beings = strtr($lastLoc[0]["people"], ["beings in range:" => ""]);
    $beingsArray = explode("/", $beings);
    $beingsArrayCleaned = [];

    foreach ($beingsArray as $v) {
        // Remove all text within parentheses and trim whitespace
        $v = trim(preg_replace('/\s*\([^)]*\)/', '', $v));

    }

    if (empty($beingsArrayCleaned)) {
        error_log("Note: empty(beingsArrayCleaned)");
        return $actorName;
    }

    // Find the closest match using Levenshtein distance
    $closest = null;
    $shortest = -1;

    foreach ($beingsArrayCleaned as $name) {
        $lev = levenshtein($actorName, $name);
        error_log("Comparing: $actorName, $name");

        if ($lev == 0) {
            return $name; // Exact match
        }

        if ($lev < $shortest || $shortest < 0) {
            $closest = $name;
            $shortest = $lev;
        }
    }

    return (!empty(trim($closest)))?$closest:$actorName;
}

function DirectConversationsWith($actor, $speaker="")
{

    global $db;
    $i_res = 0;
    
    if ($speaker=="")
        $speakerprmt=$db->escape(GetOriginalHerikaName());
    else 
        $speakerprmt=$db->escape($speaker);
    
    $listenerprmt=$db->escape($actor);
    $gametsLimit=round(($GLOBALS["gameRequest"][2]??0)-(getGametsLimitFor($actor)/0.0000024),0);
    $lastLoc=$db->fetchAll("SELECT count(*) as N FROM speech WHERE (speaker='$speakerprmt' and listener='$listenerprmt') OR (listener='$speakerprmt' and speaker='$listenerprmt') and gamets<$gametsLimit");
    
    if (!is_array($lastLoc) || sizeof($lastLoc)==0) {
        Logger::warn("DirectConversationsWith: zero interactions {$speakerprmt} - {$listenerprmt} ");
    } else {
        $i_res = intval($lastLoc[0]["n"]);
    }
    //error_log(" --- dbg DirectConversationsWith: |{$i_res}| {$speakerprmt} - {$listenerprmt} ");
    return $i_res;
    
}

function isIndividualMemoryEnabledForNpc($npcName)
{
    static $cache = [];

    $npcName = trim((string) $npcName);
    if ($npcName === '' || $npcName === '%' || strpos($npcName, '%') !== false || strpos($npcName, '_') !== false) {
        return false;
    }

    if (isset($cache[$npcName])) {
        return $cache[$npcName];
    }

    $enabled = false;
    try {
        $escaped = $GLOBALS["db"]->escape($npcName);
        $row = $GLOBALS["db"]->fetchOne("SELECT extended_data FROM core_npc_master WHERE npc_name='$escaped' LIMIT 1");
        if (is_array($row) && !empty($row["extended_data"])) {
            $extendedData = json_decode($row["extended_data"], true);
            if (
                is_array($extendedData)
                && array_key_exists('individual_memory_enabled', $extendedData)
                && $extendedData['individual_memory_enabled'] !== null
                && $extendedData['individual_memory_enabled'] !== ''
            ) {
                $enabled = !empty($extendedData['individual_memory_enabled']);
            }
        }
    } catch (Throwable $e) {
        Logger::warn("isIndividualMemoryEnabledForNpc failed for {$npcName}: " . $e->getMessage());
    }

    $cache[$npcName] = $enabled;
    return $enabled;
}

function dataGetMemoryScopeConditionSql($npcName)
{
    if (isIndividualMemoryEnabledForNpc($npcName)) {
        $npcEsc = $GLOBALS["db"]->escape($npcName);
        return "scope='$npcEsc'";
    }

    return "(scope IS NULL OR scope='global')";
}

function dataGetMemoryCompanionConditionSql(
    $npcName,
    string $column = 'companions',
    string $classifierColumn = 'classifier'
): string
{
    $npcName = trim((string)$npcName);
    if ($npcName === '') {
        $narratorOnlyDiaryAccess = filter_var(
            $GLOBALS['NARRATOR_ONLY_DIARY_ACCESS'] ?? false,
            FILTER_VALIDATE_BOOLEAN
        );
        if (!$narratorOnlyDiaryAccess) {
            // By default, the narrator searches every NPC diary in the global memory bank.
            return 'TRUE';
        }

        $narratorName = $GLOBALS['db']->escape('The Narrator');
        return "(COALESCE($classifierColumn, '') NOT IN ('diary','auto_diary','backgroundlife_diary')"
            . " OR $column LIKE '%|$narratorName|%' OR $column='$narratorName')";
    }

    $npcEsc = $GLOBALS['db']->escape($npcName);
    return "($column LIKE '%|$npcEsc|%' OR $column='$npcEsc')";
}

/**
 * Is short-term memory switched on for the NPC taking this turn?
 *
 * Set per profile (core_profiles.metadata.SHORT_TERM_MEMORY_ENABLED) and overridable per NPC
 * (core_npc_master.metadata or extended_data, same key) - both are pushed to $GLOBALS at turn time
 * by CoreProfile::setOldGlobals and NpcMaster::setOldGlobalsFromCurrentNpcData, in that order, so
 * the NPC-level value wins. Off unless a profile turns it on.
 *
 * FILTER_VALIDATE_BOOLEAN because the value can arrive as the string "1" from the profile
 * checkbox, as the string "true" from the settings editor, or as a real bool from the JSON editor.
 */
function chimShortTermMemoryEnabled(): bool
{
    return filter_var($GLOBALS["SHORT_TERM_MEMORY_ENABLED"] ?? false, FILTER_VALIDATE_BOOLEAN);
}

/**
 * Should short-term memory still be injected while Compact Chat is on?
 *
 * Compact Chat flattens the whole history into a single compact block, and STM's entries are folded
 * into it rather than dropped, so the default is yes. This global setting exists for users who run
 * Compact Chat specifically to shrink the prompt and want the summaries left out of it.
 */
function chimShortTermMemoryInCompactChatEnabled(): bool
{
    if (!array_key_exists("SHORT_TERM_MEMORY_IN_COMPACT_CHAT", $GLOBALS)) {
        return true;
    }
    return filter_var($GLOBALS["SHORT_TERM_MEMORY_IN_COMPACT_CHAT"], FILTER_VALIDATE_BOOLEAN);
}

/**
 * Short-Term Memory (STM): the scene summaries an NPC has lived through but cannot currently see -
 * newer than its middle-term-memory digest, older than the verbatim rolling window.
 *
 * The middle-term digest only regenerates every ten summaries, so the rows past its hightide that
 * have already scrolled out of the window are invisible to the NPC. This reads exactly those.
 *
 *  lower bound = array_key_last(extended_data.middle_term_memory), or 0 if the NPC has no digest
 *  upper bound = the straddling summary (the oldest whose bucket reaches $GLOBALS["CONTEXT_WINDOW_FLOOR"])
 *  cap         = $GLOBALS["SHORT_TERM_MEMORY_MAX"], default 10
 *
 * Selects from exactly the population the digest selects from - same scope partition, same
 * companions clause as service/processors/middleterm/cmd/generate.php - so the two layers cannot
 * disagree about what has already been digested.
 *
 * Also sets $GLOBALS["STM_CROP_GAMETS"], which main.php uses to crop the window to start after the
 * straddler, so no event is present twice, once summarised and once verbatim.
 *
 * $sqlfilter is accepted for signature parity with DataLastDataExpandedFor() at the same call site;
 * summaries are not event rows, so there is nothing for it to filter.
 */
function DataShortTermMemoryFor($actor, $sqlfilter = "")
{
    global $db;

    if (!chimShortTermMemoryEnabled()) {
        return [];
    }

    $cap = isset($GLOBALS["SHORT_TERM_MEMORY_MAX"]) ? intval($GLOBALS["SHORT_TERM_MEMORY_MAX"]) : 10;
    if ($cap < 1) {
        return [];
    }

    $GLOBALS["STM_CROP_GAMETS"] = 0;

    try {
        // Lower bound: where the middle-term digest ends.
        $mtmHightide = 0;
        $npcMaster = new NpcMaster();
        $npcRow = $npcMaster->getByName($actor);
        if ($npcRow) {
            $ed = $npcMaster->getExtendedData($npcRow);
            if (isset($ed["middle_term_memory"]) && is_array($ed["middle_term_memory"]) && count($ed["middle_term_memory"])) {
                $mtmHightide = intval(array_key_last($ed["middle_term_memory"]));
            }
        }

        $scopeConditionSql     = dataGetMemoryScopeConditionSql($actor);
        $companionConditionSql = dataGetMemoryCompanionConditionSql($actor);

        // Upper bound: the "straddling" summary - the oldest summary whose bucket reaches into the
        // live window. STM shows summaries up to and including it and main.php crops the window to
        // start after it. If no summary reaches the window there is no overlap, so show everything
        // past the hightide, capped, and crop nothing.
        $wOldest = intval($GLOBALS["CONTEXT_WINDOW_FLOOR"] ?? 0);
        $bigInt  = "9223372036854775807";
        $boundExpr = ($wOldest > 0)
            ? "COALESCE((SELECT min(gamets_truncated) FROM memory_summary
                          WHERE summary IS NOT NULL AND $scopeConditionSql AND $companionConditionSql
                            AND gamets_truncated >= " . $wOldest . "), $bigInt)"
            : $bigInt;

        $query = "SELECT summary, gamets_truncated
                  FROM memory_summary
                  WHERE summary IS NOT NULL
                    AND $scopeConditionSql
                    AND $companionConditionSql
                    AND gamets_truncated > " . intval($mtmHightide) . "
                    AND gamets_truncated <= $boundExpr
                  ORDER BY gamets_truncated DESC
                  LIMIT " . intval($cap);

        $rows = $db->fetchAll($query);
        if (!$rows) {
            return [];
        }

        // Crop the window only if the newest summary actually reaches into it.
        $newest = intval($rows[0]["gamets_truncated"]);
        $GLOBALS["STM_CROP_GAMETS"] = ($wOldest > 0 && $newest >= $wOldest) ? $newest : 0;

        $out = [];
        foreach (array_reverse($rows) as $r) {          // oldest -> newest
            // Strip the storage metadata before injecting: the leading "#Summary:" label and the
            // trailing "#Tags: #..." block, which is embedding/RAG metadata worth ~60-80 tokens.
            $summary = trim($r["summary"]);
            $summary = preg_replace('/^#Summary:\s*/i', '', $summary);
            $summary = preg_replace('/\s*#Tags:.*$/is', '', $summary);
            $summary = trim($summary);
            if ($summary === "") {
                continue;
            }
            $when = convert_gamets2skyrim_date($r["gamets_truncated"]);
            $out[] = ['role' => 'user', 'content' => "(Earlier events - $when) $summary"];
        }
        return $out;

    } catch (\Throwable $e) {
        Logger::warn("[STM] DataShortTermMemoryFor failed for $actor: " . $e->getMessage());
        return [];
    }
}

/**
 * Merges short-term memory ahead of the verbatim window and returns the combined message list.
 *
 * Crops window entries the newest returned summary already covers, so no event appears both
 * summarised and verbatim, then removes the internal '_g' gamets stamp from every entry. The
 * '_g' strip runs whether or not summaries were attached.
 *
 * $allowStm is the caller's decision; this function does not read it from globals.
 */
function chimAttachShortTermMemoryToWindow(array $window, string $actor, string $sqlfilter, bool $allowStm): array
{
    if ($allowStm) {
        $stm = DataShortTermMemoryFor($actor, $sqlfilter);
        if (!empty($stm)) {
            if (!empty($GLOBALS["STM_CROP_GAMETS"])) {
                $cropBoundary = intval($GLOBALS["STM_CROP_GAMETS"]);
                $window = array_values(array_filter($window, function ($e) use ($cropBoundary) {
                    return !is_array($e) || empty($e['_g']) || intval($e['_g']) > $cropBoundary;
                }));
            }
            $window = array_merge($stm, $window);
        }
    }

    foreach ($window as $k => $e) {
        if (is_array($e) && array_key_exists('_g', $e)) {
            unset($window[$k]['_g']);
        }
    }

    return array_values($window);
}


function DataSearchMemory($rawstring,$npcfilter) {
    
    //$kw=explode(" ",($rawstring));
    if (is_array($rawstring)) {
        $kwStringAny=implode(" | ",$rawstring);
        $kwStringAll=implode(" & ",$rawstring);
        
    } else if (isMinimeT5Enabled()) {
        // MiniMe keyword extraction
        Logger::info("Using minime-t5 context");
        $rawstring=strtr($rawstring,["{$GLOBALS["PLAYER_NAME"]}:"=>""]);
        $rawstring=strtr($rawstring,[
            "Talking to The Narrator"=>"",
            "Whispering to The Narrator"=>"",
            "Speaking privately to The Narrator"=>""
        ]);

        $pattern = "/\(Context location:[^)]+?\)/"; // Remove only the exact context location pattern
        $replacement = "";
        $TEST_TEXT = preg_replace($pattern, $replacement, $rawstring); 
                    
        $pattern = '/\((?:(?:talking|whispering|shouting)|speaking privately)\s+to\s+[^()]+\)/i';
        $TEST_TEXT = preg_replace($pattern, '', $TEST_TEXT);

        $keywords=minimeExtract($TEST_TEXT);
        $reponse=json_decode($keywords,true);
        
        //print_r($reponse);
        
        if (isset($reponse["is_memory_recall"]) && $reponse["is_memory_recall"]=="No") {
             $GLOBALS["db"]->insert(
                'audit_memory',
                array(
                    'input' => $TEST_TEXT,
                    'keywords' =>'minibot declined',
                    'rank_any'=> -1,
                    'rank_all'=>-1,
                    'memory'=>'',
                    'time'=>$reponse["elapsed_time"]
                )
            );
            return "";
        } else  if (isset($reponse["is_memory_recall"])) {
        
            if (isset($reponse["version"]) && $reponse["version"]==2) {
                $altKeywords=explode(" ",lastNames(15,["inputtext"]));
                $altKeywords=[];
                $keywords=explode(" ",strtr($reponse["generated_tags"],["remember"=>"","Remember"=>""]));
                $kwStringAny=implode(" | ",$keywords);
                $kwStringAll=implode(" & ",$keywords);
                $result = array_unique($keywords);
            } else {
                $altKeywords=explode(" ",lastNames(15,["inputtext"]));
                $altKeywords=[];
                $keywords=explode("|",strtr($reponse["generated_tags"],["remember"=>"","Remember"=>""]));
                array_merge($keywords,$altKeywords);
                $kw=[];
            
                foreach ($keywords as $tag) {
                    if (strlen($tag)<4)
                        continue;

                    
                    $lkwPre="";
                    foreach (explode(" ",$tag) as $stag) {
                        $lkwPre.=ucfirst($stag);
                    }
                    
                    //$lkw=hashtagify($tag);    
                    $lkw="#$lkwPre";
                    
                    if ($lkw) {
                        $kw=array_merge($kw,explode(" ",$lkw));
                    }
                }
                $result = array_unique($kw);

                $kwStringAny=implode(" | ",$result);
                $kwStringAll=implode(" & ",$result);
            }
            Logger::debug("CONTEXT SEARCH KEYWORDS FROM MINIME: ".print_r($result,true));
        }
        
    } 

    if (empty($kwStringAll)) {
        Logger::info("Using dumb context");
        $rawstring=strtr($rawstring,["{$GLOBALS["PLAYER_NAME"]}:"=>""]);
        $rawstring=strtr($rawstring,[
            "Talking to The Narrator"=>"",
            "Whispering to The Narrator"=>"",
            "Speaking privately to The Narrator"=>""
        ]);

        $pattern = "/\(Context location:[^)]+?\)/"; // Remove only the exact context location pattern
        $replacement = "";
        $TEST_TEXT = preg_replace($pattern, $replacement, $rawstring); // // assistant vs user war
                    
        $pattern = '/\((?:(?:talking|whispering|shouting)|speaking privately)\s+to\s+[^()]+\)/i';
        $TEST_TEXT = preg_replace($pattern, '', $TEST_TEXT);

        $keywords=hashtagifySentences($TEST_TEXT);
        $kw=[];
        
        //print_r($keywords);

        foreach (explode(" ",$keywords) as $tag) {
            if (strlen($tag)<4)
                continue;
            $lkw=hashtagify(strtr($tag,["remember"=>"","Remember"=>""]));    
            if ($lkw) {
                $kw=array_merge($kw,explode(" ",$lkw));
            }
        }
        $result = array_unique($kw);

        $kwStringAny=implode(" | ",$result);
        $kwStringAll=implode(" & ",$result);
        Logger::debug("CONTEXT SEARCH KEYWORDS FROM DUMB: ".print_r($result,true));
    }
        
    
    
    
    $scopeConditionSql = dataGetMemoryScopeConditionSql($npcfilter);
    $companionConditionSql = dataGetMemoryCompanionConditionSql($npcfilter, 'A.companions', 'A.classifier');

    $memory=$GLOBALS["db"]->fetchAll("
        SELECT summary,gamets_truncated,
        ts_rank(native_vec, to_tsquery('$kwStringAny')) AS rank_any,
        ts_rank(native_vec, to_tsquery('$kwStringAll')) AS rank_all
        FROM memory_summary A
        where native_vec @@to_tsquery('$kwStringAny')
        and not (native_vec @@to_tsquery('#Reminiscence'))
        and $scopeConditionSql
        and $companionConditionSql

        ORDER BY rank_all DESC, rank_any DESC;
        ",true);
            
        if (!isset($memory[0]))
            $memory[0]=["rank_any"=>null,"rank_all"=>null,"summary"=>null];

        $GLOBALS["db"]->insert(
                'audit_memory',
                array(
                    'input' => $TEST_TEXT,
                    'keywords' =>$kwStringAny,
                    'rank_any'=> $memory[0]["rank_any"],
                    'rank_all'=>$memory[0]["rank_all"],
                    'memory'=>$memory[0]["summary"],
                    'time'=>isset($reponse["elapsed_time"])?$reponse["elapsed_time"]:"0 secs (internal)"
                )
            );
            
    
    return $memory;
    
}


function chimNormalizeTsQueryTerms(string $text): array {
    if (!preg_match_all('/[\p{L}\p{N}_]+/u', $text, $matches)) {
        return [];
    }

    $terms = [];
    foreach ($matches[0] as $term) {
        if (mb_strlen($term, 'UTF-8') < 3) {
            continue;
        }
        $terms[] = $term;
    }

    return array_values(array_unique($terms));
}

function DataSearchMemoryByVector($rawstring,$npcfilter,$useContextKw=false,$timeThreshold=0) {
    
        $localStartTime=microtime(true);
        Logger::info("Using DataSearchMemoryByVector $rawstring,$npcfilter,$useContextKw=false,$timeThreshold=0");
        
        if (!$timeThreshold)
            $timeThreshold=0;
        
        $result=[];
        if (is_array($rawstring)) {
            $kwStringAny=implode(" ",$rawstring);
            $kwStringAll=implode(" ",$rawstring);
        
        } else if (isMinimeT5Enabled()) {
            // MiniMe keyword extraction
            Logger::info("Using minime-t5 context");
            error_log("[DataSearchMemoryByVector] Using minime-t5 context");
            $rawstring=strtr($rawstring,["{$GLOBALS["PLAYER_NAME"]}:"=>""]);
            $rawstring=strtr($rawstring,[
                "Talking to The Narrator"=>"",
                "Whispering to The Narrator"=>"",
                "Speaking privately to The Narrator"=>""
            ]);

            $pattern = "/\(Context location:[^)]+?\)/"; // Remove only the exact context location pattern
            $replacement = "";
            $TEST_TEXT = preg_replace($pattern, $replacement, $rawstring); 
                        
            $pattern = '/\((?:(?:talking|whispering|shouting)|speaking privately)\s+to\s+[^()]+\)/i';
            $TEST_TEXT = preg_replace($pattern, '', $TEST_TEXT);

            error_log("[DataSearchMemoryByVector start] minimeExtract : " . (microtime(true) - $localStartTime) . " seconds");
            $TEST_TEXT = preg_replace('/[(),;:!?."\'-]/', ' ', $TEST_TEXT);
            $TEST_TEXT = preg_replace('/\s+/', ' ', trim($TEST_TEXT));
            $TEST_TEXT=internalDumbTranslator($TEST_TEXT);
            
            if (isset($GLOBALS["PATCH_BYPASS_MINIME_EXTRACT"]) && $GLOBALS["PATCH_BYPASS_MINIME_EXTRACT"]) {
                error_log("[DataSearchMemoryByVector ] PATCH_BYPASS_MINIME_EXTRACT");
                $keywords=json_encode(["is_memory_recall"=>"Yes"]);
            } else {
                $keywords=minimeExtract($TEST_TEXT,true);// Only to check if memory is needed
            }

            error_log("[DataSearchMemoryByVector end] minimeExtract : " . (microtime(true) - $localStartTime) . " seconds");
            $reponse=json_decode($keywords,true);
            
            error_log("[DataSearchMemoryByVector end] minimeExtract : " .print_r($reponse,true));

            
            if (isset($reponse["is_memory_recall"]) && $reponse["is_memory_recall"]=="No") {
                $GLOBALS["db"]->insert(
                    'audit_memory',
                    array(
                        'input' => $TEST_TEXT,
                        'keywords' =>'minibot declined',
                        'rank_any'=> -1,
                        'rank_all'=>-1,
                        'memory'=>'',
                        'time'=>$reponse["elapsed_time"]
                    )
                );
                return null;

            }/* else  if (isset($reponse["is_memory_recall"])) {
            
                if (isset($reponse["version"]) && $reponse["version"]==2) {
                    $altKeywords=explode(" ",lastNames(15,["inputtext"]));
                    $altKeywords=[];
                    $keywords=explode(" ",strtr($reponse["generated_tags"],["remember"=>"","Remember"=>""]));
                    $result = array_unique($keywords);

                } else {
                    $altKeywords=explode(" ",lastNames(15,["inputtext"]));
                    $altKeywords=[];
                    $keywords=explode("|",strtr($reponse["generated_tags"],["remember"=>"","Remember"=>""]));
                    array_merge($keywords,$altKeywords);
                    $kw=[];
                
                    foreach ($keywords as $tag) {
                        if (strlen($tag)<4)
                            continue;

                        
                        $lkwPre="";
                        foreach (explode(" ",$tag) as $stag) {
                            $lkwPre.=ucfirst($stag);
                        }
                        
                        //$lkw=hashtagify($tag);    
                        $lkw="$lkwPre";
                        
                        if ($lkw) {
                            $kw=array_merge($kw,explode(" ",$lkw));
                        }
                    }
                    $result = array_unique($kw);

                   
                }
                Logger::debug("CONTEXT SEARCH KEYWORDS FROM MINIME: ".print_r($result,true));
            }*/
            
        } else {
            error_log("[DataSearchMemoryByVector] Minime-Disabled. what to do here?");
            return null;
        }

        if (sizeof($result)<1) {
            Logger::info("Using dumb context");
            $rawstring=strtr($rawstring,["{$GLOBALS["PLAYER_NAME"]}:"=>""]);
            $rawstring=strtr($rawstring,[
                "Talking to The Narrator"=>"",
                "Whispering to The Narrator"=>"",
                "Speaking privately to The Narrator"=>""
            ]);

            $pattern = "/\(Context location:[^)]+?\)/"; // Remove only the exact context location pattern
            $replacement = "";
            $TEST_TEXT = preg_replace($pattern, $replacement, $rawstring); // // assistant vs user war
                        
            $pattern = '/\((?:(?:talking|whispering|shouting)|speaking privately)\s+to\s+[^()]+\)/i';
            $TEST_TEXT = preg_replace($pattern, '', $TEST_TEXT);

            $keywords=strtr($TEST_TEXT,["."=>" ",","=>" ","'"=>" "]);
            $kw=[];
            
            //print_r($keywords);

            foreach (explode(" ",$keywords) as $tag) {
                if (strlen($tag)<4)
                    continue;
                $lkw=$tag;
                if ($lkw) {
                    $kw=array_merge($kw,explode(" ",$lkw));
                }
            }
            $result = array_unique($kw);

            $resultEn=[];
            foreach ($result as $r) {
                $resultEn[]=internalDumbTranslator($r);
            }

            if (!sizeof($resultEn)<1) {
                $resultEn=$result;
            }

            $kwStringAny=implode(" | ",$resultEn);
            $kwStringAll=implode(" & ",$resultEn);

            Logger::debug("CONTEXT SEARCH KEYWORDS FROM DUMB: ".print_r($resultEn,true));
            error_log("CONTEXT SEARCH KEYWORDS FROM DUMB: <".implode("><",$resultEn).">");
        }

       
        if (!empty($npcfilter) && $useContextKw) {
            $result=array_merge($result,lastKeyWordsContext(5,$npcfilter));
        }

        $scopeConditionSql = dataGetMemoryScopeConditionSql($npcfilter);
        $companionConditionSql = dataGetMemoryCompanionConditionSql($npcfilter);

        $contextKeywords  = implode(" ", $result);
        $contextKeywords=strtr(internalDumbTranslator($contextKeywords),["remember"=>"","Remember"=>"","do you remember"=>""]);


        $url = $GLOBALS["FEATURES"]["MEMORY_EMBEDDING"]["TXTAI_URL"].'/embed';

        $data = [
            
            'text' => $contextKeywords   
        ];

        // Convert to JSON
        $options = [
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: application/json\r\n" .
                            "Accept: application/json\r\n",
                'content' => json_encode($data),
                'ignore_errors' => true // to capture error messages if any
            ]
        ];

        // Create context and send the request
        $context  = stream_context_create($options);
        
        error_log("[DataSearchMemoryByVector Embedding start] Elapsed time: " . (microtime(true) - $localStartTime) . " seconds");
        $response = file_get_contents($url, false, $context);
        error_log("[DataSearchMemoryByVector Embedding end] Elapsed time: " . (microtime(true) - $localStartTime) . " seconds");

        // Output the response
        if ($response === false) {
            Logger::error("Request failed.\n");
        } else {
            Logger::info("Request done:\n");

        }

        $resultNormalized = chimNormalizeTsQueryTerms($contextKeywords);
        $kwStringAny=implode(" | ",$resultNormalized);
        $kwStringAll=implode(" & ",$resultNormalized);
        error_log("[DataSearchMemoryByVector] Generated Tags: $kwStringAny" );
        $vector=json_decode($response,true);

        if (is_array($vector) && isset($vector["embedding"])) {
            $vectorString="'[".implode(",",$vector["embedding"])."]'";
            $rankAnySql = $kwStringAny !== ''
                ? "ts_rank(native_vec, to_tsquery('" . $GLOBALS["db"]->escape($kwStringAny) . "'))"
                : "0::real";
            $rankAllSql = $kwStringAll !== ''
                ? "ts_rank(native_vec, to_tsquery('" . $GLOBALS["db"]->escape($kwStringAll) . "'))"
                : "0::real";
            $rankCombinedSql = "($rankAnySql + $rankAllSql)";

            $finalQuery="
                SELECT rowid,gamets_truncated,
                        embedding <-> $vectorString as distance,
                         $rankAnySql AS rank_any_fts_raw,
                         $rankAllSql AS rank_all_fts_raw,
                         $rankCombinedSql AS rank_fts,
                         (embedding <-> $vectorString) - $rankCombinedSql AS mixed_distance,
                         summary
                    FROM public.memory_summary 
                    WHERE embedding IS NOT NULL
                    and $scopeConditionSql
                    and $companionConditionSql
                    and (gamets_truncated<$timeThreshold or $timeThreshold=0)
                    
                    ORDER BY
                        mixed_distance ASC,
                        distance ASC,
                        gamets_truncated DESC,
                        rowid DESC
                    LIMIT 50 OFFSET 0
                ";    
            $memory=$GLOBALS["db"]->fetchAll($finalQuery);
            $singleMemory = chimSelectBestHybridMemoryCandidate($memory);
         
            if (!isset($singleMemory)) {
                $singleMemory = [
                    "rank_any" => null,
                    "rank_all" => null,
                    "summary" => null,
                    "distance" => 1.4,
                    "mixed_distance" => 1.4,
                ];
            }
            
            /*error_log("
                 SELECT summary, gamets_truncated,
                        embedding <-> $vectorString as distance,
                         ts_rank(native_vec, to_tsquery('$kwStringAny')) AS rank_any_fts,
                         ts_rank(native_vec, to_tsquery('$kwStringAll')) AS rank_all_fts
                    FROM public.memory_summary 
                    WHERE embedding IS NOT NULL
                    and companions like '%{$GLOBALS["db"]->escape($npcfilter)}%'
                    ORDER BY (embedding <-> $vectorString)-ts_rank(native_vec, to_tsquery('$kwStringAny')) 
                    LIMIT 5 OFFSET 0
                ");*/

            $GLOBALS["db"]->insert(
                    'audit_memory',
                    array(
                        'input' => $TEST_TEXT,
                        'keywords' =>'text2vec search / (input plus "'.$contextKeywords.'"',
                        'rank_any'=> (1.40-$singleMemory["mixed_distance"]),// Try to mimic FTS query rank
                        'rank_all'=> (1.40-$singleMemory["distance"]),// Try to mimic FTS query rank
                        'memory'=>$singleMemory["summary"],
                        'time'=>isset($vector["timing"])?$vector["timing"]["generation_time_seconds"]:"0 secs (text2vec)"
                    )
                );
            
        } else {
            return null;
        }
            
    
    return [$singleMemory];
    
}

function DataSearchOghmaByVector($rawstring,$currentOghmaTopic,$locationCtx,$contextKeywords) {
//function DataSearchOghmaByVector($rawstring) {
    
    
    Logger::info("Using DataSearchOghmaByVector");
    $rawstring=strtr($rawstring,["{$GLOBALS["PLAYER_NAME"]}:"=>""]);
    $rawstring=strtr($rawstring,[
        "Talking to The Narrator"=>"",
        "Whispering to The Narrator"=>"",
        "Speaking privately to The Narrator"=>""
    ]);

    $pattern = "/\(Context location:[^)]+?\)/"; // Remove only the exact context location pattern
    $replacement = "";
    $TEST_TEXT = preg_replace($pattern, $replacement, $rawstring); 
                
    $pattern = '/\((?:(?:talking|whispering|shouting)|speaking privately)\s+to\s+[^()]+\)/i';
    $TEST_TEXT = preg_replace($pattern, '', $TEST_TEXT);

   
    Logger::info("DataSearchOghmaByVector <$TEST_TEXT> Expanded keywords: <$currentOghmaTopic> <$locationCtx> <$contextKeywords>");
    /***/

    $embeddingFunction=function($text) {
        if (empty($text))
            return ["embedding"=>array_fill(0, 384, 0)];

        $url = $GLOBALS["FEATURES"]["MEMORY_EMBEDDING"]["TXTAI_URL"].'/embed';
        $data = [
            'text' => $text   // We add previous keywords
        ];

        // Convert to JSON
        $options = [
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: application/json\r\n" .
                            "Accept: application/json\r\n",
                'content' => json_encode($data),
                'ignore_errors' => true // to capture error messages if any
            ]
        ];

        // Create context and send the request
        $context  = stream_context_create($options);
        $response = file_get_contents($url, false, $context);

        // Output the response
        if ($response === false) {
            Logger::error("Request failed.\n");
        } else {
            Logger::info("Request done: Searched: {$data["text"]}\n");

        }

        $vector=json_decode($response,true);
        return sizeof($vector)>0?$vector:["embedding"=>array_fill(0, 384, 0)];

    };

    $vector1=$embeddingFunction($TEST_TEXT);
    $vector2=$embeddingFunction($locationCtx);
    $vector3=$embeddingFunction($contextKeywords);
    $vector4=$embeddingFunction($currentOghmaTopic);
    
    

    if (is_array($vector1) && isset($vector1["embedding"])) {
        $vectorString1="'[".implode(",",$vector1["embedding"])."]'";
        $vectorString2="'[".implode(",",$vector2["embedding"])."]'";
        $vectorString3="'[".implode(",",$vector3["embedding"])."]'";
        $vectorString4="'[".implode(",",$vector4["embedding"])."]'";

        $memory=$GLOBALS["db"]->fetchAll("
            SELECT  topic_desc,
                                topic,
                                knowledge_class,
                                knowledge_class_basic,
                                topic_desc_basic, 
                    vector384 <-> $vectorString1 as distance1,
                    vector384 <-> $vectorString2 as distance2,
                    vector384 <-> $vectorString3 as distance3,
                    vector384 <-> $vectorString4 as distance4,
                    ((vector384 <-> $vectorString1) + (vector384 <-> $vectorString2)/4 + (vector384 <-> $vectorString3)/2 + (vector384 <-> $vectorString4)/2 )/2 as distance
                FROM public.oghma 
                WHERE vector384 IS NOT NULL
                ORDER BY ((vector384 <-> $vectorString1) + (vector384 <-> $vectorString2)/4 + (vector384 <-> $vectorString3)/2 + (vector384 <-> $vectorString4)/2 )/4 ASC
                LIMIT 5 OFFSET 0
            ");
        
        

        if (!isset($memory[0]))
            $memory[0]=["combined_rank"=>null];
        else {
             $memory[0]['combined_rank']=(7.95-$memory[0]["distance"]);
             $memory[0]['combined_rank']=(7.95-$memory[0]["distance"]);
        }
        
        $GLOBALS["db"]->insert(
                'audit_memory',
                array(
                    'input' => $TEST_TEXT,
                    'keywords' =>'text2vec oghma search /'.$contextKeywords,
                    'rank_any'=> (1.40-$memory[0]["distance"]),// Try to mimic FTS query rank
                    'rank_all'=> (1.40-$memory[0]["distance"]),// Try to mimic FTS query rank
                    'memory'=>$memory[0]["topic"],
                    'time'=>isset($vector1["timing"])?$vector1["timing"]["generation_time_seconds"]:"0 secs (text2vec)"
                )
            );
        
    } else {
        return null;
    }
        

    return $memory;

}

function FastCallOAI($question) {
    
    $call["messages"]=[
        [
            "role"=>"user",
            "content"=>"$question"
        ]
    ];


    $call["stream"]=false;
    $call["stop"]=["\n"];

    $headers = ['Content-Type: application/json'];

    $options = array(
        'http' => array(
            'method' => 'POST',
                'header' => implode("\r\n", $headers),
                'content' => json_encode($call),
            )
    );

    $netContext = stream_context_create($options);
    $response=file_get_contents('http://localhost:5001/v1/chat/completions', false,$netContext);
    $rawResponse=json_decode($response,true);
    
    if (isset($rawResponse["choices"][0]["message"]["content"]))
        return $rawResponse["choices"][0]["message"]["content"];
    else
        return null;
    
}

function snapshot_response_prompt_debug_data($connectorData = null) {
    if (!isset($GLOBALS["DEBUG_DATA"]) || !is_array($GLOBALS["DEBUG_DATA"])) {
        $GLOBALS["DEBUG_DATA"] = [];
    }

    if (isset($GLOBALS["DEBUG_DATA"]["full"]) && is_array($GLOBALS["DEBUG_DATA"]["full"])) {
        $GLOBALS["DEBUG_DATA"]["response_full"] = $GLOBALS["DEBUG_DATA"]["full"];
    } else {
        unset($GLOBALS["DEBUG_DATA"]["response_full"]);
    }

    if ($connectorData === null
        && isset($GLOBALS["CHIM_CORE_CURRENT_CONNECTOR_DATA"])
        && is_array($GLOBALS["CHIM_CORE_CURRENT_CONNECTOR_DATA"])) {
        $connectorData = $GLOBALS["CHIM_CORE_CURRENT_CONNECTOR_DATA"];
    }

    if (!is_array($connectorData)) {
        unset($GLOBALS["DEBUG_DATA"]["response_connector"]);
        return;
    }

    $responseConnector = array_filter([
        'id' => $connectorData['id'] ?? null,
        'label' => $connectorData['label'] ?? null,
        'driver' => $connectorData['driver'] ?? null,
        'model' => $connectorData['model'] ?? null,
    ], function ($value) {
        return $value !== null && $value !== '';
    });

    if (!empty($responseConnector)) {
        $GLOBALS["DEBUG_DATA"]["response_connector"] = $responseConnector;
    } else {
        unset($GLOBALS["DEBUG_DATA"]["response_connector"]);
    }
}

function call_llm() {
    global $contextData, $gameRequest, $receivedData, $startTime, $db;
    global $ERROR_TRIGGERED, $talkedSoFar, $alreadysent, $FUNCTIONS_ARE_ENABLED;
    global $overrideParameters, $request;
    
    // Call the internal function (which now handles fallback itself)
    return call_llm_internal();
}

function call_llm_internal() {
    global $contextData, $gameRequest, $receivedData, $startTime, $db;
    global $ERROR_TRIGGERED, $talkedSoFar, $alreadysent, $FUNCTIONS_ARE_ENABLED;
    global $overrideParameters, $request;
    
    $outputWasValid = true;
    

    if (isset($GLOBALS["CHIM_CORE_CURRENT_CONNECTOR_DATA"])) {
        $connector=new LLMConnector();
        $connectionHandler = $connector->getConnector($GLOBALS["CHIM_CORE_CURRENT_CONNECTOR_DATA"]);
        error_log("[CORE SYSTEM] Using new profile system {$GLOBALS["CHIM_CORE_CURRENT_CONNECTOR_DATA"]["driver"]}/{$GLOBALS["CHIM_CORE_CURRENT_CONNECTOR_DATA"]["model"]}");
    } else {
        error_log("No connector defined");
        Logger::error("No connector defined");
        terminate();
    }

    /*
    Player TTS

    Player TTS. We overwrite some confs an then restore them.
    */
    // Only process player TTS on the first attempt, not during fallback retry
    if (!isset($GLOBALS["IN_FALLBACK_MODE"]) && in_array($gameRequest[0],["inputtext","inputtext_s","ginputtext","ginputtext_s","narrator_inputtext"]) && !Translation::isSavePlayerTranslationEnabled()) {
        require(__DIR__."/../processor/player_tts.php");
    }

    $connectionHandler->open($contextData,$overrideParameters);
    snapshot_response_prompt_debug_data();
    error_log("[FALLBACK DEBUG] Checking primary_handler status: " . ($connectionHandler->primary_handler === false ? "FALSE" : "OK"));
    
    if ($connectionHandler->primary_handler === false) {
        error_log("[FALLBACK DEBUG] primary_handler is false, checking fallback conditions");
        
        // Check if we should try fallback BEFORE sending error message
        if (!isset($GLOBALS["IN_FALLBACK_MODE"])) {
            $shouldTryFallback = false;
            $fallbackConnectorId = null;
            
            if (isset($GLOBALS["CHIM_CORE_CURRENT_PROFILE_DATA"])) {
                $profileData = $GLOBALS["CHIM_CORE_CURRENT_PROFILE_DATA"];
                $fallbackConnectorId = class_exists('LLMRandomizer')
                    ? LLMRandomizer::getConnectorIdForField($profileData, "llm_fallback_id")
                    : ($profileData["llm_fallback_id"] ?? null);
                error_log("[FALLBACK DEBUG] Fallback connector ID from profile: " . ($fallbackConnectorId ?? "NULL"));
                
                // Check if fallback is enabled in metadata
                if (!empty($profileData["metadata"])) {
                    $metadata = is_string($profileData["metadata"]) 
                        ? json_decode($profileData["metadata"], true) 
                        : $profileData["metadata"];
                    if (is_array($metadata)) {
                        $fallbackEnabled = !empty($metadata["LLM_FALLBACK_ENABLED"]);
                        error_log("[FALLBACK DEBUG] Fallback enabled in metadata: " . ($fallbackEnabled ? "YES" : "NO"));
                        $currentConnectorId = $GLOBALS["CHIM_CORE_CURRENT_CONNECTOR_DATA"]["id"] ?? null;
                        error_log("[FALLBACK DEBUG] Current connector ID: " . ($currentConnectorId ?? "NULL"));
                        $shouldTryFallback = $fallbackEnabled && $fallbackConnectorId && $fallbackConnectorId != $currentConnectorId;
                        error_log("[FALLBACK DEBUG] Should try fallback: " . ($shouldTryFallback ? "YES" : "NO"));
                    }
                }
            }
            
            if ($shouldTryFallback) {
                error_log("[FALLBACK] Primary connector failed (connection error). Attempting fallback connector ID: {$fallbackConnectorId}");
                
                // Set fallback mode flag to prevent player TTS reprocessing
                $GLOBALS["IN_FALLBACK_MODE"] = true;
                
                // Load and try fallback connector
                $connector = new LLMConnector();
                $fallbackConnectorData = $connector->getById($fallbackConnectorId);
                
                if ($fallbackConnectorData) {
                    error_log("[FALLBACK] Loaded fallback connector: {$fallbackConnectorData["driver"]}/{$fallbackConnectorData["model"]}");
                    $GLOBALS["CHIM_CORE_CURRENT_CONNECTOR_DATA"] = $fallbackConnectorData;
                    $connector->setOldGlobals($fallbackConnectorData);
                    
                    error_log("[FALLBACK] Recursively retrying with fallback connector");
                    // Recursively retry with fallback (flag stays set throughout retry)
                    $result = call_llm_internal();
                    
                    // Clear fallback mode flag after retry completes
                    unset($GLOBALS["IN_FALLBACK_MODE"]);
                    
                    return $result;
                } else {
                    error_log("[FALLBACK] Fallback connector ID {$fallbackConnectorId} not found.");
                    unset($GLOBALS["IN_FALLBACK_MODE"]);
                }
            }
        } else {
            error_log("[FALLBACK DEBUG] Already in fallback mode, not retrying");
        }
        
        // No fallback or fallback also failed - send error message
        error_log("[FALLBACK DEBUG] Sending ERROR_OPENAI message to user");
        $db->insert(
            'log',
            array(
                'localts' => time(),
                'prompt' => nl2br((json_encode($GLOBALS["DEBUG_DATA"], JSON_PRETTY_PRINT))),
                'response' => ((print_r(error_get_last(), true))),
                'url' => nl2br(("$receivedData in " . (microtime(true) - $startTime) . " secs "))
            )
        );
        if (Translation::isEnabled()) {
            Translation::translate($GLOBALS["ERROR_OPENAI"]);
            Translation::$sentences = [Translation::$response];
        }        
        returnLines([$GLOBALS["ERROR_OPENAI"]]);
        
        $ERROR_TRIGGERED=true;
        @ob_end_flush();

        Logger::error(print_r(error_get_last(), true));
        return false;
    }

    // Check for error response code
    $statusCode = method_exists($connectionHandler, 'getHttpStatusCode') ? $connectionHandler->getHttpStatusCode() : 200;
    if ($statusCode >= 300) {
        // Check if we should try fallback BEFORE sending error message
        if (!isset($GLOBALS["IN_FALLBACK_MODE"])) {
            $shouldTryFallback = false;
            $fallbackConnectorId = null;
            
            if (isset($GLOBALS["CHIM_CORE_CURRENT_PROFILE_DATA"])) {
                $profileData = $GLOBALS["CHIM_CORE_CURRENT_PROFILE_DATA"];
                $fallbackConnectorId = class_exists('LLMRandomizer')
                    ? LLMRandomizer::getConnectorIdForField($profileData, "llm_fallback_id")
                    : ($profileData["llm_fallback_id"] ?? null);
                
                // Check if fallback is enabled in metadata
                if (!empty($profileData["metadata"])) {
                    $metadata = is_string($profileData["metadata"]) 
                        ? json_decode($profileData["metadata"], true) 
                        : $profileData["metadata"];
                    if (is_array($metadata)) {
                        $fallbackEnabled = !empty($metadata["LLM_FALLBACK_ENABLED"]);
                        $currentConnectorId = $GLOBALS["CHIM_CORE_CURRENT_CONNECTOR_DATA"]["id"] ?? null;
                        $shouldTryFallback = $fallbackEnabled && $fallbackConnectorId && $fallbackConnectorId != $currentConnectorId;
                    }
                }
            }
            
            if ($shouldTryFallback) {
                error_log("[FALLBACK] Primary connector failed (HTTP {$statusCode}). Attempting fallback connector ID: {$fallbackConnectorId}");
                
                // Set fallback mode flag to prevent player TTS reprocessing
                $GLOBALS["IN_FALLBACK_MODE"] = true;
                
                // Load and try fallback connector
                $connector = new LLMConnector();
                $fallbackConnectorData = $connector->getById($fallbackConnectorId);
                
                if ($fallbackConnectorData) {
                    $GLOBALS["CHIM_CORE_CURRENT_CONNECTOR_DATA"] = $fallbackConnectorData;
                    $connector->setOldGlobals($fallbackConnectorData);
                    
                    // Recursively retry with fallback (flag stays set throughout retry)
                    $result = call_llm_internal();
                    
                    // Clear fallback mode flag after retry completes
                    unset($GLOBALS["IN_FALLBACK_MODE"]);
                    
                    return $result;
                } else {
                    error_log("[FALLBACK] Fallback connector ID {$fallbackConnectorId} not found.");
                    unset($GLOBALS["IN_FALLBACK_MODE"]);
                }
            }
        }
        
        Logger::error("LLM provider error response code: $statusCode");
        return false;
    }

    // Read and process the response line by line
    $buffer="";
    $totalBuffer="";
    $breakFlag=false;
    $lineCounter=0;
    $fullContent="";
    $totalProcessedData="";
    $numOutputTokens = 0;
    $INCREMENTAL_SENTENCESIZE=20;

    while (true) {
        if ($breakFlag) {
            break;
        }

        $tmpData=$connectionHandler->process();
        if ($tmpData==-1 || (isset($GLOBALS["VALIDATE_LLM_OUTPUT_FNCT"]) && !$GLOBALS["VALIDATE_LLM_OUTPUT_FNCT"]($tmpData))) {
            Logger::warn("Invalid JSON Output.");
            $outputWasValid=false;
            $buffer="";
            $breakFlag=true;
        }
        else {
            $buffer.= $tmpData;
            $totalBuffer.=$buffer; 
        }

        if ($connectionHandler->isDone()) {
            $breakFlag=true;
        }

        $buffer=strtr($buffer, array("\""=>"",".)"=>")."));

        // For narration events, allow immediate streaming without minimum buffer size
        if ($gameRequest[0] !== "narration" && strlen($buffer)<$INCREMENTAL_SENTENCESIZE) {	// Avoid too short buffers
            continue;
        }

        // disable streaming when translating to avoid sentence fragments getting translated
        if (Translation::isEnabled()) {
            continue;
        }

        $position = findFastSentencePosition($buffer,$INCREMENTAL_SENTENCESIZE);

        //echo "<$buffer>".PHP_EOL;
        if (($position !== false) && ($gameRequest[0] === "narration" || $position>$INCREMENTAL_SENTENCESIZE)) {
            $extractedData = substr($buffer, 0, $position + 1);
            $remainingData = substr($buffer, $position + 1);
            $sentences=split_sentences_stream(cleanResponse($extractedData));
            $GLOBALS["DEBUG_DATA"]["response"][]=["raw"=>$buffer,"processed"=>implode("|", $sentences)];
            $GLOBALS["DEBUG_DATA"]["perf"][]=(microtime(true) - $startTime)." secs in openai stream";

            if ($gameRequest[0] != "diary") {
                returnLines($sentences);
                $INCREMENTAL_SENTENCESIZE=MINIMUM_SENTENCE_SIZE;
            } else { //why is the diary talking? is this correct?
                $talkedSoFar[md5(implode(" ", $sentences))]=implode(" ", $sentences);
            }

            //echo "$extractedData  # ".(microtime(true)-$startTime)."\t".strlen($finalData)."\t".PHP_EOL;  // Output
            $totalProcessedData.=$extractedData;
            $extractedData="";
            $buffer=$remainingData;
            //$user_input_after=$GLOBALS["db"]->fetchAll("select count(*) as N from eventlog where type='user_input' and ts>$gameRequest[1]"); //9.0ms
            

        }
        // This is intended to stop the generation as soon as user input is detected, so we will attend new request instead of keeping generating this
        $user_input_after=$GLOBALS["db"]->fetchAll("select rowid as N from eventlog where type='user_input' and ts>$gameRequest[1] LIMIT 1"); // 2.1ms, faster than count(*)
        if (isset($user_input_after[0]))
            if (isset($user_input_after[0]["N"]))
                if ($user_input_after[0]["N"]>0) {
                    Logger::info("Generation stopped because user_input. ".__FILE__." ".__LINE__." ".__FUNCTION__);
                    error_log("Generation stopped because user_input. ".__FILE__." ".__LINE__." ".__FUNCTION__);
                    $connectionHandler->close();
                    die('X-CUSTOM-CLOSE');
                    // Abort , user input detected
                }

    } // --- end while
    
    
    if ($outputWasValid && trim($buffer)) {
        Logger::info("REMAINING DATA <$buffer>");
        $sentences=split_sentences_stream(cleanResponse(trim($buffer)));

        if (Translation::isEnabled()) {
            Translation::translate($buffer);
            if (isset(Translation::$response)) {
                if (strlen(trim(Translation::$response)) > 0) {
                    Translation::$sentences = split_sentences_stream(cleanResponse(trim(Translation::$response)));
                    Translation::normalizeArrays($sentences, Translation::$sentences);
                }
            }
        }

        $GLOBALS["DEBUG_DATA"]["response"][]=["raw"=>$buffer,"processed"=>implode("|", $sentences)];
        $GLOBALS["DEBUG_DATA"]["perf"][]=(microtime(true) - $startTime)." secs in openai stream";
        if ($gameRequest[0] != "diary") {
            returnLines($sentences);
        } else {
            $talkedSoFar[md5(implode(" ", $sentences))]=implode(" ", $sentences);
        }
        $totalBuffer.=trim($buffer);
        $totalProcessedData.=trim($buffer);
    }

    if ($GLOBALS["FUNCTIONS_ARE_ENABLED"] && $outputWasValid)  {
        $actions=$connectionHandler->processActions();
        if (isset($GLOBALS["action_post_process_fnct"])) {
            $actions=$GLOBALS["action_post_process_fnct"]($actions);
        }

        // Extnded version which is an array, so we can hook more than one function
        if (isset($GLOBALS["action_post_process_fnct_ex"]) && is_array($GLOBALS["action_post_process_fnct_ex"])) {
            foreach ($GLOBALS["action_post_process_fnct_ex"] as $postFilterFunc)
                $actions=$postFilterFunc($actions);
        }

        
        if (is_array($actions) && (sizeof($actions)>0)) {
            
            // ACTION POST-FILTER
            
            if ($GLOBALS["FUNCTIONS_ARE_ENABLED"]) {
                $isRolemasteredNpc = (
                    (!empty($GLOBALS["NPC_ROLEMASTERED"])) ||
                    herikaResolveNpcRolemasterState($GLOBALS["HERIKA_NAME"] ?? '', ['load_lookup' => true])
                );
                $copyActions=[];
                foreach ($actions as $n=>$action) {
                    $copyActions[$n]=$actions[$n];
                    $actionParts=explode("|",$action);
                    $actionParts2=explode("@",$actionParts[2]);
                    
                    if (isset($actionParts2[1])) {
                        // Parameter part 
                        $explicitActorRefTarget = chimNormalizeExplicitActorRefTarget($actionParts2[1]);
                        $refAwarePlainActions = [
                            "Attack", "GiveItemTo", "GiveGoldTo", "TradeItems",
                            "Follow", "MoveTo", "Brawl"
                        ];
                        if ($explicitActorRefTarget !== "" &&
                            in_array($actionParts2[0], $refAwarePlainActions, true) &&
                            substr(trim($actionParts2[1]), 0, 1) !== "{") {
                            $actions[$n] = "{$actionParts[0]}|{$actionParts[1]}|{$actionParts2[0]}@{$explicitActorRefTarget}";
                            error_log("[ACTION POSTFILTER {$actionParts2[0]}] Preserving explicit actor target {$explicitActorRefTarget}");
                            continue;
                        }

                        if ($actionParts2[0]=="Attack") {
                            // Lets polish the parameters
                            $localtarget=$actionParts2[1];
                            $mang1=explode(",",$localtarget);
                            $mang2=explode(" and ",$mang1[0]);
                            $mang3=explode("(",$mang2[0]);
                            $mang4=FindClosestNPCName($mang3[0]);

                            //$actions[$n]="{$actionParts[0]}|{$actionParts[1]}|Attack@{$mang3[0]}";

                            if ($mang4)
                                $actions[$n]="{$actionParts[0]}|{$actionParts[1]}|Attack@{$mang4}";
                            else
                                $actions[$n]="{$actionParts[0]}|{$actionParts[1]}|Attack@{$mang3[0]}";

                            error_log("[ACTION POSTFILTER Attack] $localtarget => {$mang3[0]} => $mang4");
                        } else if ($actionParts2[0]=="GiveItemTo") {
                            // Check if parameter is JSON (multi-param) - skip post-filtering for JSON
                            if (isset($actionParts2[1]) && substr(trim($actionParts2[1]), 0, 1) === '{') {
                                error_log("[ACTION POSTFILTER GiveItemTo] JSON parameter detected, skipping post-filter");
                                // Keep the action as-is for JSON parameters
                            } else {
                                // Legacy: polish the parameters for single-param format
                                $localtarget=$actionParts2[1];
                                $mang1=explode(",",$localtarget);
                                $mang2=explode(" and ",$mang1[0]);
                                $mang3=explode("(",$mang2[0]);
                                $mang4=FindClosestActorName($mang3[0]);
                                error_log("[ACTION POSTFILTER GiveItemTo] $localtarget => {$mang3[0]} => $mang4");

                                if ($mang4)
                                    $actions[$n]="{$actionParts[0]}|{$actionParts[1]}|GiveItemTo@{$mang4}";
                                else
                                    $actions[$n]="{$actionParts[0]}|{$actionParts[1]}|GiveItemTo@{$mang3[0]}";
                            }

                        } else if ($actionParts2[0]=="GiveGoldTo") {
                            // Check if parameter is JSON (multi-param) - validate gold amount
                            if (isset($actionParts2[1]) && substr(trim($actionParts2[1]), 0, 1) === '{') {
                                error_log("[ACTION POSTFILTER GiveGoldTo] JSON parameter detected, validating gold amount");
                                
                                // Parse JSON to extract amount
                                $jsonStr = trim($actionParts2[1]);
                                $requestedAmount = null;
                                
                                // Simple JSON parsing for amount
                                if (preg_match('/"amount"\s*:\s*(\d+)/', $jsonStr, $matches)) {
                                    $requestedAmount = intval($matches[1]);
                                }
                                
                                // Get available gold from NPC metadata
                                $availableGold = getGoldFromMetadata();
                                
                                if ($requestedAmount !== null && $requestedAmount > 0) {
                                    if ($requestedAmount > $availableGold) {
                                        // Cap the amount to available gold
                                        error_log("[ACTION POSTFILTER GiveGoldTo] Requested {$requestedAmount} gold but only have {$availableGold}, capping amount");
                                        $jsonStr = preg_replace('/"amount"\s*:\s*\d+/', '"amount":' . $availableGold, $jsonStr);
                                        $actions[$n] = "{$actionParts[0]}|{$actionParts[1]}|GiveGoldTo@{$jsonStr}";
                                    } else {
                                        // Amount is valid, keep as-is
                                        $actions[$n] = "{$actionParts[0]}|{$actionParts[1]}|GiveGoldTo@{$jsonStr}";
                                    }
                                } else {
                                    // No amount specified or invalid, keep as-is (plugin will handle error)
                                    $actions[$n] = "{$actionParts[0]}|{$actionParts[1]}|GiveGoldTo@{$jsonStr}";
                                }
                            } else {
                                // Legacy: polish the parameters for single-param format
                                $localtarget=$actionParts2[1];
                                $mang1=explode(",",$localtarget);
                                $mang2=explode(" and ",$mang1[0]);
                                $mang3=explode("(",$mang2[0]);
                                $mang4=FindClosestActorName($mang3[0]);
                                error_log("[ACTION POSTFILTER GiveGoldTo] $localtarget => {$mang3[0]} => $$mang4");

                                if ($mang4)
                                    $actions[$n]="{$actionParts[0]}|{$actionParts[1]}|GiveGoldTo@{$mang4}";
                                else
                                    $actions[$n]="{$actionParts[0]}|{$actionParts[1]}|GiveGoldTo@{$mang3[0]}";
                            }


                        }  else if ($actionParts2[0]=="TradeItems") {
                            // Lets polish the parammeters
                            $localtarget=$actionParts2[1];
                            $mang1=explode(",",$localtarget);
                            $mang2=explode(" and ",$mang1[0]);
                            $mang3=explode("(",$mang2[0]);

                            $mang4=FindClosestActorName($mang3[0]);

                            error_log("[ACTION POSTFILTER TradeItems] $localtarget => {$mang3[0]} => $mang4");

                            if ($mang4)
                                $destination=$mang4;
                            else
                                $destination=$mang3[0];

                            error_log("[ACTION POSTFILTER TradeItems] $localtarget => {$mang3[0]} => $destination");

                            if ($destination!=$GLOBALS["PLAYER_NAME"])
                                $actions[$n]="{$actionParts[0]}|{$actionParts[1]}|TradeItems@$destination";

                        }  else if ($actionParts2[0]=="Follow") {
                            // Lets polish the parammeters
                            $localtarget=$actionParts2[1];
                            $mang1=explode(",",$localtarget);
                            $mang2=explode(" and ",$mang1[0]);
                            $mang3=explode("(",$mang2[0]);
                            $mang4=FindClosestActorName($mang3[0]);

                            error_log("[ACTION POSTFILTER Follow] $localtarget =>  {$mang3[0]} => $mang4");

                            if ($mang4)
                                $destination=$mang4;
                            else
                                $destination=$mang3[0];
                            if ($destination!=$GLOBALS["PLAYER_NAME"])
                                $actions[$n]="{$actionParts[0]}|{$actionParts[1]}|Follow@$destination";
                            else
                                $actions[$n]="{$actionParts[0]}|{$actionParts[1]}|FollowPlayer@";
                            

                            error_log("[ACTION POSTFILTER Follow] $localtarget => {$mang3[0]} => $destination");

                        } else if ($actionParts2[0]=="TravelTo") {
                            // Lets polish the parammeters
                            $localtarget=$actionParts2[1];
                            $mang1=explode(",",$localtarget);
                            $mang2=explode(" and ",$mang1[0]);
                            $mang3=explode("(",$mang2[0]);
                            $mang4=explode("--",$mang3[0]);
                            
                            $destination=$mang4[0];

                            error_log("[ACTION POSTFILTER TravelTo]  $localtarget => {$mang4[0]} => $destination");

                            $destinationName=$GLOBALS["db"]->escape(trim($destination));
                            $dbDestination=$GLOBALS["db"]->fetchOne("SELECT name, similarity(name, '$destinationName') AS sim,formid FROM locations ORDER BY sim DESC LIMIT 1");
                            $dbDestinationRegion=$GLOBALS["db"]->fetchOne("SELECT name, similarity(region, '$destinationName') AS sim,formid FROM locations ORDER BY sim DESC LIMIT 1");

                            $contextDestinations=DataPosibleLocationsToGo();

                            if (in_array(trim($localtarget),$contextDestinations)) {
                                // Perfect match
                                error_log("[ACTION POSTFILTER TravelTo] Seems valid as-is (context destination): <$localtarget> => $localtarget");
                                $actions[$n]="{$actionParts[0]}|{$actionParts[1]}|TravelTo@$localtarget";

                            } else if (in_array($destination,$contextDestinations)) {
                                error_log("[ACTION POSTFILTER TravelTo] Seemd valid (context destination): $localtarget => $destination");
                                $actions[$n]="{$actionParts[0]}|{$actionParts[1]}|TravelTo@$destination";

                            } else {
                                if ($isRolemasteredNpc) {
                                    if (stripos($destination,"home")===0) {
                                        // Rolemastered NPC wants to return back home
                                        $actions[$n]="{$actionParts[0]}|{$actionParts[1]}|ReturnBackHome@"; 
                                        continue;

                                    }

                                } 
                                if (is_array($dbDestination) && isset($dbDestination["formid"])) {
                                    // TravelToRaw change
                                    $destination=$dbDestination["formid"];
                                    error_log("[ACTION POSTFILTER TravelTo] found database entry for $localtarget => $destination => {$dbDestination["name"]}, similarity ({$dbDestination["sim"]})");
                                    $actions[$n]="{$actionParts[0]}|{$actionParts[1]}|TravelToRaw@$destination";    
                                
                                } else if (is_array($dbDestinationRegion) && isset($dbDestinationRegion["formid"])) {
                                    // TravelToRaw change
                                    $destination=$dbDestinationRegion["formid"];

                                    error_log("[ACTION POSTFILTER TravelTo] found database (searching by region) entry for $localtarget => $destination => {$dbDestinationRegion["name"]}, similarity ({$dbDestinationRegion["sim"]})");
                                    $actions[$n]="{$actionParts[0]}|{$actionParts[1]}|TravelToRaw@$destination";

                                } else if (stripos($destination,"outside")!==false) {
                                    $destination=DataLastKnownLocationHuman(true,false);
                                    error_log("[ACTION POSTFILTER TravelTo] reference to outside detected , $localtarget => $destination");
                                    
                                } else
                                    $actions[$n]="{$actionParts[0]}|{$actionParts[1]}|TravelTo@$destination";
                            }
                            
                        } else if ($actionParts2[0]=="MoveTo") {
                            // MoveTo is actor-only. Locations must remain TravelTo.
                            $localtarget=$actionParts2[1] ?? "";
                            $mang1=explode(",",$localtarget);
                            $mang2=explode(" and ",$mang1[0]);
                            $mang3=explode("(",$mang2[0]);
                            $mang4=explode("--",$mang3[0]);
                            
                            $target=trim($mang4[0]);
                            $resolvedTarget="";

                            if ($target !== "" && isset($GLOBALS["PLAYER_NAME"]) && strcasecmp($target, $GLOBALS["PLAYER_NAME"]) === 0) {
                                $resolvedTarget=$GLOBALS["PLAYER_NAME"];
                            }

                            if ($resolvedTarget === "") {
                                foreach (DataPosibleMoveToTargets() as $candidateTarget) {
                                    if (strcasecmp($target, $candidateTarget) === 0) {
                                        $resolvedTarget=$candidateTarget;
                                        break;
                                    }
                                }
                            }

                            if ($resolvedTarget === "") {
                                $closestTarget=FindClosestActorName($target);
                                if ($closestTarget !== "" && levenshtein(strtolower($target), strtolower($closestTarget)) <= 3) {
                                    $resolvedTarget=$closestTarget;
                                }
                            }

                            if ($resolvedTarget === "") {
                                $resolvedTarget=$target;
                            }

                            error_log("[ACTION POSTFILTER MoveTo] $localtarget => $target => $resolvedTarget");
                            $actions[$n]="{$actionParts[0]}|{$actionParts[1]}|MoveTo@$resolvedTarget";
                            
                        }  else if ($actionParts2[0]=="FollowPlayer") {
                            
                            error_log("[ACTION POSTFILTER FollowPlayer] Just Cleaning here");
                            $actions[$n]="{$actionParts[0]}|{$actionParts[1]}|FollowPlayer@";
                            
                        }  else if ($actionParts2[0]=="ReturnBackHome") {
                            
                            error_log("[ACTION POSTFILTER ReturnBackHome] Just Cleaning here");
                            $actions[$n]="{$actionParts[0]}|{$actionParts[1]}|ReturnBackHome@";
                            
                        }  else if ($actionParts2[0]=="Brawl") {
                            // Lets polish the parammeters
                            $localtarget=$actionParts2[1];
                            $mang1=explode(",",$localtarget);
                            $mang2=explode(" and ",$mang1[0]);
                            $mang3=explode("(",$mang2[0]);

                            $mang4=FindClosestActorName($mang3[0]);

                            error_log("[ACTION POSTFILTER Brawl] $localtarget => {$mang3[0]} => $mang4");

                            if ($mang4)
                                $finaltarget=$mang4;
                            else
                                $finaltarget=$mang3[0];

                            error_log("[ACTION POSTFILTER Brawl] $localtarget => {$mang3[0]} => $finaltarget");

                            $actions[$n]="{$actionParts[0]}|{$actionParts[1]}|Brawl@$finaltarget";

                        } else if ($actionParts2[0]=="TakeGoldFromPlayer") {
                            // Lets polish the parammeters
                            $localtarget=$actionParts2[1];
                            $mang1=explode(",",$localtarget);
                            $mang2=explode(" and ",$mang1[0]);
                            $mang3=explode("(",$mang2[0]);

                            $mang4 = is_numeric(trim($mang3[0])) ? trim($mang3[0]) + 0 : null;

                            error_log("[ACTION POSTFILTER TakeGoldFromPlayer] $localtarget => {$mang3[0]} => $mang4");

                            if (!is_numeric($mang4)) {
                                // Try to figure out quantity from speech
                                $localNpc=$GLOBALS["db"]->escape($GLOBALS["HERIKA_NAME"]);
                                $qtyrecord=$GLOBALS["db"]->fetchOne("SELECT speech,(regexp_matches(speech, '\d+'))[1]::int AS quantity FROM public.speech 
                                WHERE listener = '$localNpc' OR speaker = '$localNpc' order by rowid desc LIMIT 100");
                                if (isset($qtyrecord["quantity"])) {
                                    $qty=trim($qtyrecord["quantity"]);
                                    error_log("[ACTION POSTFILTER TakeGoldFromPlayer] quantity found $qty");
                                    $actions[$n]="{$actionParts[0]}|{$actionParts[1]}|TakeGoldFromPlayer@$qty";
                                } else
                                $actions[$n]="{$actionParts[0]}|{$actionParts[1]}|TakeGoldFromPlayer@";
                            } else
                                $actions[$n]="{$actionParts[0]}|{$actionParts[1]}|TakeGoldFromPlayer@$mang4";

                        } else if ($actionParts2[0]=="PickupItem") {
                            // Parse item parameter - can be JSON or plain string
                            $itemParam = trim($actionParts2[1]);
                            
                            Logger::info("[PickupItem PostFilter] Raw LLM item parameter: '{$itemParam}'");
                            
                            // Check if parameter is JSON (multi-param format)
                            if (substr($itemParam, 0, 1) === '{') {
                                // JSON format: {"target":"","item":"0xFF00550D:Diamond"}
                                $params = json_decode($itemParam, true);
                                $itemParam = isset($params['item']) ? trim($params['item']) : '';
                                Logger::info("[PickupItem PostFilter] Extracted item from JSON: '{$itemParam}'");
                            }
                            
                            // If still empty, can't proceed
                            if (empty($itemParam)) {
                                Logger::warn("[PickupItem PostFilter] No item parameter provided, skipping");
                                continue;
                            }
                            
                            // Get the last infoitems context from eventlog (contains RefID:BaseID:ItemName)
                            $lastItemsContext = $GLOBALS["db"]->fetchOne(
                                "SELECT data FROM eventlog WHERE type='infoitems' ORDER BY localts DESC LIMIT 1"
                            );
                            
                            if ($lastItemsContext && !empty($lastItemsContext['data'])) {
                                Logger::info("[PickupItem PostFilter] Found infoitems in database");
                                // Extract items from context: "(items in range:0xRef:0xBase:Item1,0xRef2:0xBase2:Item2)"
                                // Use greedy match to capture everything including (STEALING) tags
                                if (preg_match('/\(items in range:(.+)\)/', $lastItemsContext['data'], $matches)) {
                                    $itemsStr = $matches[1];
                                    $itemsList = explode(',', $itemsStr);
                                    
                                    Logger::info("[PickupItem PostFilter] Found " . count($itemsList) . " items in database");
                                    Logger::info("[PickupItem PostFilter] First 3 items: " . implode(' | ', array_slice($itemsList, 0, 3)));
                                    
                                    $foundItem = false;
                                    
                                    // Check if LLM provided the RefID:ItemName format
                                    if (preg_match('/^0x[0-9A-Fa-f]+:/', $itemParam)) {
                                        // LLM provided "0xRefID:ItemName", extract the RefID
                                        $paramParts = explode(':', $itemParam, 2);
                                        $paramRefID = $paramParts[0];
                                        
                                        Logger::info("[PickupItem PostFilter] LLM provided RefID: {$paramRefID}, searching for exact match...");
                                        
                                        // Search for exact RefID match
                                        foreach ($itemsList as $itemEntry) {
                                            // Parse "RefID:BaseID:ItemName" from database
                                            $entryParts = explode(':', trim($itemEntry), 3);
                                            if (count($entryParts) >= 3) {
                                                $refID = $entryParts[0];
                                                $itemName = $entryParts[2];
                                                
                                                // Exact RefID match (case-insensitive)
                                                if (strcasecmp($refID, $paramRefID) === 0) {
                                                    // Send RefID:ItemName without (STEALING) tag to game
                                                    $cleanItemName = str_replace(' (STEALING)', '', $itemName);
                                                    $cleanFormat = "{$refID}:{$cleanItemName}";
                                                    Logger::info("[PickupItem PostFilter] EXACT MATCH FOUND! Sending: {$cleanFormat}");
                                                    $actions[$n]="{$actionParts[0]}|{$actionParts[1]}|PickupItem@{$cleanFormat}";
                                                    $foundItem = true;
                                                    break;
                                                }
                                            }
                                        }
                                        
                                        if (!$foundItem) {
                                            Logger::warn("[PickupItem PostFilter] No exact match found for RefID: {$paramRefID}");
                                            Logger::warn("[PickupItem PostFilter] Item may have despawned or moved. Available RefIDs: " . 
                                                implode(', ', array_map(function($item) {
                                                    $parts = explode(':', trim($item), 3);
                                                    return $parts[0] ?? 'invalid';
                                                }, array_slice($itemsList, 0, 10))));
                                        }
                                    } else {
                                        // LLM provided just the item name, search by name
                                        foreach ($itemsList as $itemEntry) {
                                            $entryParts = explode(':', trim($itemEntry), 3);
                                            if (count($entryParts) >= 3) {
                                                $refID = $entryParts[0];
                                                $itemName = $entryParts[2];
                                                
                                                // Strip (STEALING) tag for comparison
                                                $cleanItemName = str_replace(' (STEALING)', '', $itemName);
                                                
                                                if (stripos($cleanItemName, $itemParam) !== false) {
                                                    // Send RefID:ItemName without (STEALING) tag to game
                                                    $displayFormat = "{$refID}:{$cleanItemName}";
                                                    $actions[$n]="{$actionParts[0]}|{$actionParts[1]}|PickupItem@{$displayFormat}";
                                                    $foundItem = true;
                                                    break;
                                                }
                                            }
                                        }
                                    }
                                }
                            }

                        }
                    }
                    
                }
            }
            
            // Log actions
            foreach ($actions as $n=>$singleaction) {
                $actionPart=explode("|",$singleaction); 
                $actionArg=explode("@",$actionPart[2]); 
                
                $GLOBALS["db"]->insert(
                    'actions_issued',
                    array(
                        'action' => $actionArg[0],
                        'fullcall' =>$singleaction,
                        'actorname'=> isset($GLOBALS["PATCH_ACTION_ALL_ACTORS"])?$GLOBALS["PATCH_ACTION_ALL_ACTORS"]:$actionPart[0],
                        'ts' => $gameRequest[1],
                        'gamets' => $gameRequest[2],
                        'localts'=>time(),
                        'original'=>function_exists('herikaActionCatalogApplyFollowupChainToActionsIssuedOriginal')
                            ? herikaActionCatalogApplyFollowupChainToActionsIssuedOriginal($copyActions[$n])
                            : $copyActions[$n]
                    )
                );


            }
            $GLOBALS["DEBUG_DATA"]["response"][]=$actions;
            
            // Log actions before echoing
            foreach ($actions as $action) {
                Logger::info("Echoing action to plugin: {$action}");
            }
            
            echo implode("\r\n", $actions)."\r\n";
            
            file_put_contents(__DIR__."/../log/output_to_plugin.log",implode("\r\n", $actions)."\r\n", FILE_APPEND | LOCK_EX);
            // Enforce flush output
            if (ob_get_level()) @ob_end_flush();
            @flush();

        }
    }
    
    $connectionHandler->close('standard');
    //fwrite($fileLog, $totalBuffer . PHP_EOL); // Write the line to the file with a line break // DEBUG CODE


    return $outputWasValid;
}

function AddFirstTimeMet($followerName,$momentum,$gamets,$ts) {

    $fn=$GLOBALS["db"]->escape($followerName);
    
    // Check if already recorded - with error handling
    $already = @$GLOBALS["db"]->fetchAll("select 1 as t from memory where event='first_met' and message like '%met {$fn}%'");
    if ($already === false) {
        Logger::warn("[AddFirstTimeMet] Query to memory table failed for follower: {$followerName}");
        return;
    }
    
    if (is_array($already) && sizeof($already)>0) {
        // Already exists;
        return;
    }

    // Get first interaction timestamp - with error handling
    $realFirst = @$GLOBALS["db"]->fetchAll("SELECT gamets,convert_gamets2skyrim_date(gamets) as sk_date,ts,localts FROM speech where companions ilike '%$fn%' order by rowid asc limit 1 offset 0");
    
    if ($realFirst === false) {
        Logger::warn("[AddFirstTimeMet] Query to speech table failed for follower: {$followerName}");
        return;
    }

    if (is_array($realFirst) && sizeof($realFirst)>0) {
        $gamets=$realFirst[0]["gamets"];
        $ts=$realFirst[0]["ts"];
        $momentum=$realFirst[0]["localts"];
        $sk_date=$realFirst[0]["sk_date"]; // game timestamp converted to skyrim date YYYY-MM-DD HH:MM:SS

        logMemory($GLOBALS["PLAYER_NAME"], $followerName,
        "(Important note: {$GLOBALS["PLAYER_NAME"]} met {$followerName} for the first time on {$sk_date}. This is an important event, so use tag #FirstTimeMet.)",
        $momentum, $gamets,'first_met',$ts);
    }


}


function DataRetrieveFirstTimeMet($s_player_name, $s_npc_name) {
    global $db;

	$s_res = "";

	if ((strlen($s_player_name)>0) && (strlen($s_npc_name)>0) && ($s_player_name != $s_npc_name)) {
        if (($s_npc_name == "Herika") || ($s_player_name == "Herika")) { // Herika easter egg
            return "{$s_player_name} met {$s_npc_name} for the first time on 0199-04-26, 15:36:00, years ago.";
        }
		$s_player = $db->escape($s_player_name);
		$s_npc = $db->escape($s_npc_name);

        $crt_gamets = intval(DataLastKnownGameTS());

		$db_rec = $db->fetchAll("SELECT speaker,listener,
			message,gamets,momentum,rowid  
			FROM memory 
			WHERE event = 'first_met' AND gamets > 0 AND
			((speaker = '{$s_player}' AND listener = '$s_npc') OR
			(listener = '{$s_player}' AND speaker = '$s_npc'))
			ORDER BY rowid ASC LIMIT 1; ");
            
        $b_found_memory = (is_array($db_rec) && sizeof($db_rec)>0); 
        
        if (!$b_found_memory) { // check conversations
            $gts_met = GetFirstInteraction($s_player, $s_npc); 
        } else {
			$gts_met = intval($db_rec[0]['gamets'] ?? 0);
		}

        if (($gts_met > 0) && ($crt_gamets > $gts_met)) {
            $gts_ago = $crt_gamets - $gts_met;
            $s_met = convert_gamets2skyrim_date($gts_ago);
			$hours_ago = convert_gamets2hours($gts_ago);
            
			if ($hours_ago < 49)
				$s_time_ago = "{$hours_ago} hours ago";
			else {
				$days_ago = intval($hours_ago / 24); 
				$s_time_ago = "{$days_ago} days ago";
			}
			$s_res = "{$s_player_name} met {$s_npc_name} for the first time on {$s_met}, {$s_time_ago}.";

        } else { 
			Logger::info("DataRetrieveLastMet: NO match found");
			//$s_res = "There is no record of when {$s_player_name} met {$s_npc_name}.";
		}
	}
	return $s_res;
}

function GetFirstTimeMetMemory($s_player_name, $s_npc_name) {
    global $db;
    $i_res = 0;

	if ((strlen($s_player_name)>0) && (strlen($s_npc_name)>0) && ($s_player_name != $s_npc_name)) {
		$s_player = $db->escape($s_player_name);
		$s_npc = $db->escape($s_npc_name);

        //$crt_gamets = intval(DataLastKnownGameTS());

		$db_rec = $db->fetchAll("SELECT speaker,listener,
			message,gamets,momentum,rowid  
			FROM memory 
			WHERE event = 'first_met' AND gamets > 0 AND
			((speaker = '{$s_player}' AND listener = '$s_npc') OR
			(listener = '{$s_player}' AND speaker = '$s_npc'))
			ORDER BY rowid ASC LIMIT 1; ");
            
        $b_found_memory = (is_array($db_rec) && sizeof($db_rec)>0); 
        
        if ($b_found_memory) { 
			$i_res = intval($db_rec[0]['gamets'] ?? 0);
		}

	}
	return $i_res;
}

function GetFirstTimeMet($s_player_name, $s_npc_name) {
    $i_res = 0;

	if ((strlen($s_player_name)>0) && (strlen($s_npc_name)>0) && ($s_player_name != $s_npc_name)) {
        
        $i_res = GetFirstTimeMetMemory($s_player_name, $s_npc_name); 

        if ($i_res <= 0) { // check conversations
            $i_res = GetFirstInteraction($s_player_name, $s_npc_name); 
		}
	}
	return $i_res;
}

function GetLastInteraction($s_player_name, $s_npc_name) {
    global $db;
	$i_res = 0;
	if ((strlen($s_player_name)>0) && (strlen($s_npc_name)>0) && ($s_player_name != $s_npc_name)) {
		$s_player = $db->escape($s_player_name);
		$s_npc = $db->escape($s_npc_name);
		$db_rec = $db->fetchAll("SELECT gamets FROM speech 
        WHERE (gamets > 0) AND 
          ((speaker = '{$s_player}' AND listener = '{$s_npc}') OR 
          (listener = '{$s_player}' AND speaker = '{$s_npc}'))  
        ORDER BY gamets DESC LIMIT 1 ");
		if (is_array($db_rec) && sizeof($db_rec)>0) {
			$i_res = intval($db_rec[0]['gamets']);
		}
	}
	return $i_res;
}

function GetLastSpeechTs() {
    global $db;
    $i_res=0;
	$db_rec = $db->fetchAll("SELECT gamets as gamets FROM speech 
        WHERE (gamets > 0) ORDER BY gamets DESC LIMIT 1 ");
	if (is_array($db_rec) && sizeof($db_rec)>0) {
		$i_res = intval($db_rec[0]['gamets']);
	}
	
	return $i_res;
}

function GetFirstInteraction($s_player_name, $s_npc_name) {
    global $db;
	$i_res = 0;
	if ((strlen($s_player_name)>0) && (strlen($s_npc_name)>0) && ($s_player_name != $s_npc_name)) {
		$s_player = $db->escape($s_player_name);
		$s_npc = $db->escape($s_npc_name);
		$db_rec = $db->fetchAll("SELECT gamets FROM speech 
        WHERE (gamets > 0) AND 
          ((speaker = '{$s_player}' AND listener = '{$s_npc}') OR 
          (listener = '{$s_player}' AND speaker = '{$s_npc}'))  
        ORDER BY gamets ASC LIMIT 1 ");
		if (is_array($db_rec) && sizeof($db_rec)>0) {
			$i_res = intval($db_rec[0]['gamets']);
		}
	}
	return $i_res;
}

function DataRetrieveLastTimeTalk($s_player_name, $s_npc_name) {
    global $db;

	$s_res = "";

	if ((strlen($s_player_name)>0) && (strlen($s_npc_name)>0) && (!($s_player_name == 'The Narrator')) && (!($s_npc_name == 'The Narrator'))) {
		$crt_gamets = intval(DataLastKnownGameTS());
		$gts_met = GetLastInteraction($s_player_name, $s_npc_name); 
		if ($gts_met > 0) {
			$s_date = gamets2str_format_date($gts_met, $dt_format = 'Y-m-d'); 
			$gts_ago = $crt_gamets - $gts_met;
			$hours_ago = convert_gamets2hours($gts_ago);
			if ($hours_ago > 3) {
				if ($hours_ago < 48) {
					$s_res = "{$s_player_name} and {$s_npc_name} last spoke {$hours_ago} hours ago.";
				} else {
					$days_ago = convert_gamets2days($gts_ago);
					if ($days_ago < 31) {
						$s_res = "{$s_player_name} and {$s_npc_name} last spoke {$days_ago} days ago.";
					} else {
						$months_ago = intval($days_ago * 0.03333333);
						if ($months_ago < 12) {
							$s_res = "{$s_player_name} and {$s_npc_name} last spoke {$months_ago} months ago on {$s_date}.";
						} else {
							$s_res = "{$s_player_name} and {$s_npc_name} last spoke long time ago on {$s_date}.";
						}
					}
				}	
			} else {
                Logger::debug("DataRetrieveLastTimeTalk: {$s_player_name} and {$s_npc_name} spoke recently");
				//$s_res = "{$s_player_name} and {$s_npc_name} spoke recently.";
			}
		} else { 
			Logger::debug("DataRetrieveLastTimeTalk: NO match found for {$s_player_name} - {$s_npc_name}");
			//$s_res = "There is no record of when {$s_player_name} and {$s_npc_name} last spoke.";
		}
	}
	return $s_res;
}


function GetAnimationHex($mood)
{
    $mood = extractFirstEmoteMood($mood);
    if ($mood === '') {
        return "";
    }

    //error_log("Getting animation for mood: $mood");
    $ANIMATIONS=[
        "ArmsCrossed"=>"IdleExamine",        // Arms crossed
        "PointClose"=>"IdlePointClose",
        "HandsBehindBack"=>"IdleHandsBehindBack",    // 000B240A ? // Arms behind back
        //"DrawAttention"=>"0x0006FF15",     // Continous
        //"Cheer"=>"0x00066374",             // Continous
        "ApplauseSarcastic"=>"IdleApplaudSarcastic",  // Continous
        "WaveHand"=>"IdleWave",
        "Nervous"=>"IdleNervous",
        "ArmsRaised"=>"IdleSurrender",
        "NervousDialogue"=>"IdleDialogueMovingTalkA",
        "NervousDialogue1"=>"IdleDialogueMovingTalkB",
        "NervousDialogue2"=>"IdleDialogueMovingTalkC",
        "NervousDialogue3"=>"IdleDialogueMovingTalkD",
        "Cheer"=>"SpectatorCheer",
        "ComeThisWay"=>"IdleComeThisWay",
        "SarcasticMove"=>"IdleDialogueExpressiveStart",
        "Applause1"=>"IdleApplaud2",
        "Applause2"=>"IdleApplaud3",
        "Applause3"=>"IdleApplaud4",
        "Applause4"=>"IdleApplaud5",
        "DrinkPotion"=>"IdleDrinkPotion",        // Don't use while talking
        "PointFar"=>"IdlePointFar_01",
        "PointFar2"=>"IdlePointFar_02",
        "GiveSomething"=>"IdleGive",
        "TakeSomething"=>"IdleTake",
        "Salute"=>"IdleSalute",
        "CleanSweat"=>"IdleWipeBrow",
        "NoteRead"=>"IdleNoteRead",
        "LookFar"=>"IdleLookFar",
        "Laugh"=>"IdleLaugh",
        "CleanSword"=>"IdleCleanSword",
        "WarmArms"=>"IdleWarmArms",
        "Positive"=>"LooseDialogueResponsePositive",
        "Negative"=>"LooseDialogueResponseNegative",
        "HappyDialogue"=>"IdleDialogueHappyStart",
        "AngryDialogue"=>"IdleDialogueAngryStart",
        "Agitated"=>"IdleCiceroAgitated",
        "HandOnChinGesture"=>"IdleDialogueHandOnChinGesture",
        
    ];
    
    $animationsDb=$GLOBALS["db"]->fetchAll("select animations from animations_custom where mood ilike '%$mood%'");
    foreach ($animationsDb as $an) {
        $candidates=explode(",", $an["animations"]);
        if (is_array($candidates)) {
            error_log("[ANIMATION] {$an["animations"]}");
            return $candidates[array_rand($candidates)];
        }

    }

    $animationsDb=$GLOBALS["db"]->fetchAll("select animations from animations where mood ilike '%$mood%'");
    foreach ($animationsDb as $an) {
        $candidates=explode(",", $an["animations"]);
        if (is_array($candidates)) {
            // error_log("[ANIMATION] {$an["animations"]}");
            return $candidates[array_rand($candidates)];
        }

    }


    if ($mood=="sarcastic") {
        return array_rand(array_flip([$ANIMATIONS["SarcasticMove"],$ANIMATIONS["CleanSweat"],$ANIMATIONS["Agitated"],$ANIMATIONS["ApplauseSarcastic"]]), 1);
        
        
    } else if ($mood=="sassy") {
        return array_rand(array_flip([$ANIMATIONS["SarcasticMove"],$ANIMATIONS["CleanSweat"],$ANIMATIONS["Agitated"],$ANIMATIONS["ApplauseSarcastic"]]), 1);
        
        
    } else if ($mood=="sardonic") {
        return array_rand(array_flip([$ANIMATIONS["SarcasticMove"],$ANIMATIONS["CleanSweat"],$ANIMATIONS["Agitated"],$ANIMATIONS["ApplauseSarcastic"]]), 1);
        
        
    } else if ($mood=="irritated") {
        return array_rand(array_flip([$ANIMATIONS["PointClose"],$ANIMATIONS["Negative"],$ANIMATIONS["AngryDialogue"]]), 1);
       
        
    } else if ($mood=="mocking") {
        return array_rand(array_flip([$ANIMATIONS["Applause1"],$ANIMATIONS["Applause2"],$ANIMATIONS["Applause3"],$ANIMATIONS["Applause4"]]), 1);
        
        
    } else if ($mood=="playful") {
        return array_rand(array_flip([$ANIMATIONS["Cheer"],$ANIMATIONS["HappyDialogue"],$ANIMATIONS["Positive"]]), 1);
            
    } else if ($mood=="teasing") {
        return array_rand(array_flip([$ANIMATIONS["NervousDialogue"],$ANIMATIONS["NervousDialogue1"],$ANIMATIONS["NervousDialogue2"],$ANIMATIONS["NervousDialogue3"]]), 1);
        
        
    } else if ($mood=="smug") {
        return $ANIMATIONS["Nervous"];
        
        
    } else if ($mood=="amused") {
        return $ANIMATIONS["ArmsRaised"];
        
    } else if ($mood=="smirking") {
        return $ANIMATIONS["Nervous"];
    
        
    } else if ($mood=="serious") {
        return array_rand(array_flip([$ANIMATIONS["CleanSweat"],$ANIMATIONS["PointClose"],$ANIMATIONS["HandOnChinGesture"]]), 1);
    
        
    } else if ($mood=="firm") {
        return array_rand(array_flip([$ANIMATIONS["CleanSweat"],$ANIMATIONS["PointClose"],$ANIMATIONS["HandOnChinGesture"]]), 1);
    
        
    } else if ($mood=="neutral") {
        return array_rand(array_flip([$ANIMATIONS["HappyDialogue"]]), 1);
        
        
    } else if ($mood=="drunk") {
        // No animation :(
        Logger::info("Using filter for mood drunk");
        $GLOBALS["TTS_FFMPEG_FILTERS"]["tempo"]='atempo=0.65';
        return "DrunkStart";
        
    } else if ($mood=="sober") {

        Logger::info("Resetting mood drunk.");
        
        return "DrunkStop";
        
    } else if ($mood=="high") {
        // No animation :(
        $GLOBALS["TTS_FFMPEG_FILTERS"]["tempo"]='atempo=1.45';
        
    } 
                      
    
    //error_log("Getting animation for mood: $mood, no result found");
    return "";

}


function GetExpression($mood) {
     $mood = extractFirstEmoteMood($mood);
     if ($mood === '') {
         return "";
     }
     $EXPRESSIONS=[
     "DialogueAnger",    "DialogueFear",    "DialogueHappy",     "DialogueSad",
     "DialogueSurprise", "DialoguePuzzled", "DialogueDisgusted", "MoodNeutral",
     "MoodAnger",        "MoodFear",        "MoodHappy",        "MoodSad",
     "MoodSurprise",    "MoodPuzzled",    "MoodDisgusted",    "CombatAnger",
     "CombatShout"
     ];
     
     $result="MoodNeutral";
     if ($mood=="sarcastic") {
        $result= array_rand(array_flip(["DialoguePuzzled"]), 1);
         
         
     } else if ($mood=="sassy") {
        $result= array_rand(array_flip(["DialoguePuzzled"]), 1);
         
         
     } else if ($mood=="sardonic") {
        $result= array_rand(array_flip(["DialoguePuzzled"]), 1);
         
         
     } else if ($mood=="irritated") {
        $result= array_rand(array_flip(["DialogueAnger"]), 1);
        
         
     } else if ($mood=="mocking") {
        $result= array_rand(array_flip(["DialogueHappy"]), 1);
         
         
     } else if ($mood=="playful") {
        $result= array_rand(array_flip(["DialogueHappy"]), 1);
             
     } else if ($mood=="teasing") {
        $result= array_rand(array_flip(["DialogueSurprise"]), 1);
         
         
     } else if ($mood=="smug") {
        $result= array_rand(array_flip(["DialogueAnger"]), 1);
         
         
     } else if ($mood=="amused") {
        $result= array_rand(array_flip(["DialogueSurprise"]), 1);
         
     } else if ($mood=="smirking") {
        $result= array_rand(array_flip(["DialogueHappy"]), 1);
     } else if (in_array($mood, ["sexy", "kindly", "lovely", "seductive", "happy"], true)) {
        $result="DialogueHappy";
     } else if (in_array($mood, ["desperate", "scared", "pleading"], true)) {
        $result="DialogueFear";
     } else if (in_array($mood, ["assertive", "angry"], true)) {
        $result="DialogueAnger";
     } else if ($mood=="sad") {
        $result="DialogueSad";
     } else if ($mood=="surprised") {
        $result="DialogueSurprise";
     } else if (in_array($mood, ["drunk", "shy"], true)) {
        $result="DialoguePuzzled";
     
         
     } else if ($mood=="serious") {
        $result= array_rand(array_flip(["MoodNeutral"]), 1);
     
         
     } else if ($mood=="firm") {
        $result= array_rand(array_flip(["MoodNeutral"]), 1);
     
         
     } if ($mood=="neutral") {
        $result= array_rand(array_flip(["MoodNeutral"]), 1);
         
         
     }
                             
     
     $GLOBALS["PATCH_ORIGINAL_MOOD_ISSUED"]=$mood;
     return $result;
     
 }

 

function isOk($arr) {
    if (is_array($arr))
        if (sizeof($arr)>0)
            return true;

    return false;
}

function getArrayKey($arr,$key) {
    if (is_array($arr))
        if (isset($arr[$key]))
            return $arr[$key];

    return false;
}

function profile_exists($npcname) {
    $path = dirname((__FILE__)) . DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR;
    $newConfFile=md5($npcname);
    return file_exists($path . "conf".DIRECTORY_SEPARATOR."conf_$newConfFile.php");
}

function createProfile($npcname, $FORCE_PARMS = [], $overwrite = false, $baseprofile = '')
{
    // This should be done at NpcMaster::createProfile
    global $db;

    if ($npcname == "The Narrator")   // Refuse to add Narrator [review this]
        return;

    $path = dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR;
    $newConfFile = md5($npcname);

    $codename = npcNameToCodename($npcname);
    $baseprofileName = npcNameToCodename($baseprofile);

    $npcMaster = new NpcMaster();
    $currentNpcData = $npcMaster->getByName($npcname);

    $EMPTY_PROFILE=false;

    if (!$currentNpcData || $overwrite ) {
        error_log("Creating/overwriting:$overwrite  profile for $npcname");
        //sleep (1);
        if (empty($GLOBALS["CORE_LANG"])) {
            $npcTemlate = $db->fetchAll("SELECT core FROM combined_bio_templates where npc_name='$codename'");
            // Query for new HERIKA fields (bio tables)
            $npcNewFields = $db->fetchAll("SELECT npc_static_bio, personality, appearance, relationships, occupation, skills, speechstyle, goals, oghma_knowledge_tags, voiceid, gender, race, refid FROM combined_bio_templates where npc_name='$codename'");
        } else {
            Logger::info("Using npc_templates_trl, name_trl='$codename' and lang='{$GLOBALS["CORE_LANG"]}'");
            $npcTemlate = $db->fetchAll("SELECT npc_pers FROM npc_templates_trl where name_trl='$codename' and lang='{$GLOBALS["CORE_LANG"]}'");
            if (!isset($npcTemlate[0])) {
                Logger::info("No trl found, using standard template");
                $npcTemlate = $db->fetchAll("SELECT core FROM combined_bio_templates where npc_name='$codename'");
                // Query for new HERIKA fields (bio tables)
                $npcNewFields = $db->fetchAll("SELECT npc_static_bio, personality, appearance, relationships, occupation, skills, speechstyle, goals, oghma_knowledge_tags, voiceid, gender, race, refid FROM combined_bio_templates where npc_name='$codename'");
            } else {
                // Keep translated core text, but still seed structured metadata from the bio template view.
                $npcNewFields = $db->fetchAll("SELECT npc_static_bio, personality, appearance, relationships, occupation, skills, speechstyle, goals, oghma_knowledge_tags, voiceid, gender, race, refid FROM combined_bio_templates where npc_name='$codename'");
            }
        }

        // 3. Extract the bracketed portion and convert it to the "stripped" version
        //    e.g. Bofesar [Whiterun Guard] -> whiterun_guard
        $bracketMatch = '';
        if (preg_match('/\[(.*?)\]/', $npcname, $matches)) {
            $bracketMatch = trim($matches[1]);    // remove possible extra spaces
            $bracketMatch = strtolower($bracketMatch);
            $bracketMatch = str_replace(' ', '_', $bracketMatch);
        }

        // Original logic for pulling from database
        if (isset($npcTemlate[0]) && is_array($npcTemlate[0])) {
                error_log("Creating from template ");

            // Build core from template core (or npc_pers in translated case)
            $coreFull = '';
            if (array_key_exists('core', $npcTemlate[0])) {
                $coreFull = trim((string)$npcTemlate[0]['core']);
            } elseif (array_key_exists('npc_pers', $npcTemlate[0])) {
                $coreFull = trim((string)$npcTemlate[0]['npc_pers']);
            }
            if ($coreFull === '') {
                // Fallback: minimal core
                $coreFull = trim($npcname);
            }

            $npcMaster->create([

                    "npc_name" => $npcname,
                    'npc_static_bio' => $npcNewFields[0]["npc_static_bio"] ?? '',
                    'personality' => $npcNewFields[0]["personality"] ?? '',
                    'core' => $coreFull,
                    'relationships' => $npcNewFields[0]["relationships"] ?? '',
                    'occupation' => $npcNewFields[0]["occupation"] ?? '',
                    'appearance' => $npcNewFields[0]["appearance"] ?? '',
                    'skills' => $npcNewFields[0]["skills"] ?? '',
                    'speechstyle' => $npcNewFields[0]["speechstyle"] ?? '',
                    'goals' => $npcNewFields[0]["goals"] ?? '',
                    'oghma_knowledge_tags' => $npcNewFields[0]["oghma_knowledge_tags"] ?? ''

                ]
            );

            // RealNamesExtended support for generic npcs
        } elseif (!empty($bracketMatch)) {
            
            // Query for new HERIKA fields for bracket match (bio tables)
            $npcNewFields2 = $db->fetchAll("SELECT npc_static_bio, personality, appearance, relationships, occupation, skills, speechstyle, goals, oghma_knowledge_tags, voiceid, gender, race, refid FROM combined_bio_templates WHERE npc_name='" . $db->escape($bracketMatch) . "'");
            $npcCore2 = $db->fetchAll("SELECT core FROM combined_bio_templates WHERE npc_name='" . $db->escape($bracketMatch) . "'");

            if (!empty($npcNewFields2[0])) {
                error_log("Creating from template bracketMatch");
                // Build core from template core
                $coreFull2 = '';
                if (!empty($npcCore2[0]) && array_key_exists('core', $npcCore2[0])) {
                    $coreFull2 = trim((string)$npcCore2[0]['core']);
                }
                if ($coreFull2 === '') { $coreFull2 = trim($npcname); }

                $npcMaster->create([
                        "npc_name" => $npcname,
                        'npc_static_bio' => $npcNewFields2[0]["npc_static_bio"] ?? '',
                        'personality' => $npcNewFields2[0]["personality"] ?? '',
                        'core' => $coreFull2,
                        'relationships' => $npcNewFields2[0]["relationships"] ?? '',
                        'occupation' => $npcNewFields2[0]["occupation"] ?? '',
                        'appearance' => $npcNewFields2[0]["appearance"] ?? '',
                        'skills' => $npcNewFields2[0]["skills"] ?? '',
                        'speechstyle' => $npcNewFields2[0]["speechstyle"] ?? '',
                        'goals' => $npcNewFields2[0]["goals"] ?? '',
                        'oghma_knowledge_tags' => $npcNewFields2[0]["oghma_knowledge_tags"] ?? ''
                    ]
                );
            } else {
                error_log("Creating initial empty profile");
                $npcMaster->create([
                        "npc_name" => $npcname
                    ]
                );
            }
        } else {
            error_log("Creating initial empty profile");
            $npcMaster->create([
                    "npc_name" => $npcname
                ]
            );
            $EMPTY_PROFILE=true;
            $newData = $npcMaster->GetByName($npcname);
            

        }

        // Voice
        //TODO i dont think these ids are used to set the voice in this code - not sure i follow it [needs review]
        // $voiceRow = $db->fetchAll("SELECT voiceid FROM combined_bio_templates WHERE npc_name='$codename'");
        // $melottsid = $db->fetchAll("SELECT melotts_voiceid FROM combined_npc_templates WHERE npc_name='$codename'");
        // $xvasynthid = $db->fetchAll("SELECT xvasynth_voiceid	 FROM combined_npc_templates WHERE npc_name='$codename'");

        // $cn = $db->escape("Voicetype/$codename");
        // $vtype = $db->fetchAll("select value from conf_opts where id='$cn'");
        // $voicetypeString = (isOk($vtype)) ? $vtype[0]["value"] : null;
        // if ($voicetypeString) {
            // $voicetype = explode("\\", $voicetypeString);
        // } else {
        //     $voicetype = null;
        // }

        // $voicelogic = $GLOBALS["TTS"]["XTTSFASTAPI"]["voicelogic"];
        //use the Nametype conf opts to latch onto the character name while still being able to pull the correct voicetype[3]
        // if ($voicelogic === "voicetype") {
            //$codename = npcNameToCodename($npcname);
            //$cn = $db->escape("Nametype/$codename");
            //$vtype = $db->fetchAll("select value from conf_opts where id='$cn'");
            //$voicetypeString = (isOk($vtype)) ? $vtype[0]["value"] : null;
            //if ($voicetypeString) {
                //$voicetype = explode("\\", $voicetypeString);
            //}
        //}
        // Legacy per-engine voice ids (deprecated in favor of unified voiceid)
        // $melottsid = $db->fetchAll("SELECT melotts_voiceid FROM combined_npc_templates WHERE npc_name='$codename'");
        // $xvasynthid = $db->fetchAll("SELECT xvasynth_voiceid FROM combined_npc_templates WHERE npc_name='$codename'");

        // Populate voiceid for core_npc_master using XTTS-style logic with template fallback
        $voiceid = "";

        // 1) Prefer explicit template voiceid if present
        if (isset($npcNewFields) && isset($npcNewFields[0]["voiceid"]) && !empty($npcNewFields[0]["voiceid"])) {
            $voiceid = trim($npcNewFields[0]["voiceid"]);
        } else if (isset($npcNewFields2) && isset($npcNewFields2[0]["voiceid"]) && !empty($npcNewFields2[0]["voiceid"])) {
            $voiceid = trim($npcNewFields2[0]["voiceid"]);
        }

        // 2) Else derive from conf_opts Nametype/Voicetype path (old XTTS behavior)
        if (empty($voiceid)) {
            $codename = npcNameToCodename($npcname);
            $cnVoicetype = $db->escape("Voicetype/$codename");
            $cnNametype  = $db->escape("Nametype/$codename");

            $row = $db->fetchAll("select value from conf_opts where id='$cnVoicetype' limit 1");
            if (!is_array($row) || sizeof($row) == 0) {
                $row = $db->fetchAll("select value from conf_opts where id='$cnNametype' limit 1");
            }

            if (is_array($row) && sizeof($row) > 0 && isset($row[0]["value"])) {
                $parts = explode("\\", $row[0]["value"]);
                if (sizeof($parts) >= 4 && !empty($parts[3])) {
                    $voiceid = strtolower($parts[3]);
                }
            }
        }

        // 3) Assign (may remain empty if nothing found)
        $currentData = $npcMaster->GetByName($npcname);
        $currentData["voiceid"] = $voiceid;

        $existingMetadata = [];
        if (!empty($currentData['metadata'])) {
            $decodedMetadata = json_decode((string)$currentData['metadata'], true);
            if (is_array($decodedMetadata)) {
                $existingMetadata = $decodedMetadata;
            }
        }
        $currentData['metadata'] = json_encode($existingMetadata, JSON_UNESCAPED_UNICODE);

        $existingExtendedData = [];
        if (!empty($currentData['extended_data'])) {
            $decodedExtendedData = json_decode((string)$currentData['extended_data'], true);
            if (is_array($decodedExtendedData)) {
                $existingExtendedData = $decodedExtendedData;
            }
        }
        $existingExtendedData['chim_core_migrated'] = 2;
        $currentData['extended_data'] = json_encode($existingExtendedData, JSON_UNESCAPED_UNICODE);
        $defaultProfileId = 1;
        try {
            $coreProfile = new CoreProfile();
            $defaultProfile = $coreProfile->getDefaultNpc();
            if (is_array($defaultProfile) && !empty($defaultProfile['id'])) {
                $defaultProfileId = (int)$defaultProfile['id'];
            }
        } catch (Throwable $e) {
            error_log("[CREATEPROFILE] Could not resolve default NPC profile, falling back to profile #1: " . $e->getMessage());
        }

        $currentData['profile_id'] = $defaultProfileId;
        $currentData['md5'] = md5($currentData["npc_name"]);
        $currentData['gamets_last_updated'] = $GLOBALS["gameRequest"][2];

        if ($EMPTY_PROFILE) {
            error_log("[CREATEPROFILE] Created initial empty profile");
        }

        $npcMaster->updateByArray($currentData);
        return 1;
    }
    return 2;
}

function getConfFileFor($npcname) {

    global $db; 
    $path = dirname((__FILE__)) . DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR;
    $newConfFile=md5($npcname);

    
    return $path . "conf".DIRECTORY_SEPARATOR."conf_$newConfFile.php";
    
}

function buildDynamicBiography(array $FOLLOWER_CONF, bool $forLetter = false, bool $forThought = false,bool $addItemId = false)
{
    /**
     * Build dynamic biography from new HERIKA fields, with fallback to legacy HERIKA_DYNAMIC
     * @param array $FOLLOWER_CONF Configuration array containing HERIKA fields
     * @param bool $forLetter If false, removes <letter_guidance> sections from HERIKA_SPEECHSTYLE
     * @param bool $forThought If false, removes <inner_thought_guidance> sections from HERIKA_SPEECHSTYLE
     * @param bool $addItemId If true, appends item baseid to equipment descriptions
     * @return string The dynamic biography content
     */
    $dynamicBio = '';
    
    // Helper function to get item description from combined view
    $getItemDescription = function($itemName, $baseid = null) {
        global $db;
        
        // Try the shared runtime/stable/legacy baseid resolver first if provided
        if (!empty($baseid)) {
            $record = lookupDescriptionByFormID((string) $baseid);
            if (!empty($record['description'])) {
                return $record['description'];
            }
        }
        
        // Fallback to name-based search
        if (!empty($itemName) && $itemName != '<Missing Name>') {
            $escapedName = $db->escape($itemName);
            $result = $db->fetchAll("SELECT description FROM combined_descriptions WHERE LOWER(name) = LOWER('{$escapedName}') LIMIT 1");
            if (!empty($result) && !empty($result[0]['description'])) {
                return $result[0]['description'];
            }
        }
        
        return null;
    };
    
    // List of new HERIKA fields to include
    $herikaFields = [
        'HERIKA_BACKGROUND' => 'Basic Summary',
        'HERIKA_PERSONALITY' => 'Personality', 
        'HERIKA_APPEARANCE' => 'Appearance',
        'HERIKA_OCCUPATION' => 'Occupation',
        'HERIKA_SKILLS' => 'Skills',
        'HERIKA_SPEECHSTYLE' => 'Speech Style',
        'HERIKA_GOALS' => 'Goals'
    ];
    $hasStructuredBiographyFields = false;
    foreach (array_keys($herikaFields) as $structuredFieldName) {
        if (isset($FOLLOWER_CONF[$structuredFieldName]) && !empty(trim((string)$FOLLOWER_CONF[$structuredFieldName]))) {
            $hasStructuredBiographyFields = true;
            break;
        }
    }
    $SKILLS_ADD="";
    $EQUIPMENT_ADD="";
    $TARGET_EQUIPMENT_ADD="";
    $STATS_ADD="";
    $SPELLS_ADD="";
    $ACTIVITY_ADD="";
    
    $npcMaster=new NpcMaster();
    $currentNpcData=$npcMaster->getByName($FOLLOWER_CONF["HERIKA_NAME"]);
    $metaData=$npcMaster->getMetaData($currentNpcData);
    $extendedData=$npcMaster->getExtendedData($currentNpcData);
    $activityStatus = chimNormalizeActivityStatus($metaData);

    if (!empty($activityStatus['summary'])) {
        $ACTIVITY_ADD = "\n\n<activity>\n#Activity\n" . ucfirst($activityStatus['summary']) . ".\n</activity>\n";
    }
    
    if (isset($metaData["skills"])) {
        // Convert numeric skills to descriptive levels, grouped by category
        $skillCategories = [
            'Combat' => ['archery', 'block', 'onehanded', 'twohanded', 'heavyarmor', 'lightarmor'],
            'Magic' => ['conjuration', 'destruction', 'illusion', 'restoration', 'alteration', 'enchanting'],
            'Stealth' => ['sneak', 'pickpocket', 'lockpicking', 'speechcraft'],
            'Crafting' => ['smithing', 'alchemy']
        ];
        
        $formattedSkills = "\n\nSkill Proficiencies:";
        
        foreach ($skillCategories as $category => $skillNames) {
            $categorySkills = [];
            foreach ($skillNames as $skillName) {
                if (isset($metaData["skills"][$skillName])) {
                    $skillValue = $metaData["skills"][$skillName];
                    $level = '';
                    if ($skillValue >= 100) {
                        $level = 'Master';
                    } elseif ($skillValue >= 75) {
                        $level = 'Expert';
                    } elseif ($skillValue >= 50) {
                        $level = 'Adept';
                    } elseif ($skillValue >= 25) {
                        $level = 'Apprentice';
                    } else {
                        $level = 'Novice';
                    }
                    
                    // Always show skills, including Novice
                    $categorySkills[] = ucfirst($skillName) . " (" . $level . ")";
                }
            }
            
            if (!empty($categorySkills)) {
                $formattedSkills .= "\n  • {$category}: " . implode(", ", $categorySkills);
            }
        }
        
        $SKILLS_ADD = $formattedSkills;
    } 
    
    // Add NPC's own equipment (skip for The Narrator - they don't need equipment context)
    if ($FOLLOWER_CONF["HERIKA_NAME"] !== "The Narrator" && isset($metaData["equipment"]) && is_array($metaData["equipment"])) {
        $describedBaseids = []; // Track which baseids we've already described
        $vanillaEquipmentParts = chimFormatEquipmentPromptLines($metaData["equipment"], chimEquipmentVanillaSlotLabels(), $getItemDescription, $describedBaseids);
        $moddedEquipmentParts = chimFormatEquipmentPromptLines($metaData["equipment"], chimEquipmentModdedSlotLabels(), $getItemDescription, $describedBaseids);
        $equipmentSections = [];

        if (!empty($vanillaEquipmentParts)) {
            $equipmentSections[] = "Vanilla Slots:\n" . implode("\n", $vanillaEquipmentParts);
        }

        if (!empty($moddedEquipmentParts)) {
            $equipmentSections[] = "Modded Slots:\n" . implode("\n", $moddedEquipmentParts);
        }
        
        if (!empty($equipmentSections)) {
            $EQUIPMENT_ADD = "\n<equipment>\n#Current Equipment\nYou are currently wearing/wielding:\n" . implode("\n", $equipmentSections);
            
            // Check if humanoid NPC has no body armor - if so, note they're naked
            $humanoidRaces = ['nord', 'imperial', 'breton', 'redguard', 'orc', 'orsimer', 
                            'altmer', 'highelf', 'bosmer', 'woodelf', 'dunmer', 'darkelf', 
                            'argonian', 'khajiit', 'khajit'];
            $npcRace = isset($currentNpcData["race"]) ? strtolower(trim($currentNpcData["race"])) : '';
            
            if ($npcRace && in_array($npcRace, $humanoidRaces) && !chimEquipmentHasBodyCoverage($metaData["equipment"])) {
                $EQUIPMENT_ADD .= "\nNote: You are naked (no body armor/clothing worn).";
            }
            
            $EQUIPMENT_ADD .= "\n</equipment>";
        }
    }

     // Add NPC's inventory (skip for The Narrator - they don't need inventory context)
    if ($FOLLOWER_CONF["HERIKA_NAME"] !== "The Narrator" && isset($metaData["inventory"]) && is_array($metaData["inventory"])) {
        // Continue using the same IDs from equipment to dedupe descriptions across both sections.
        if (!isset($describedBaseids)) {
            $describedBaseids = [];
        }

        $inventoryContext = chimBuildInventoryPromptContext(
            $metaData["inventory"],
            $getItemDescription,
            $describedBaseids,
            !empty($GLOBALS["INVENTORY_ITEMS_DESCRIPTIONS_ONLY"])
        );

        if ($inventoryContext !== '') {
            $INVENTORY_ADD = "\n" . $inventoryContext;
        }
    }
	// Add current condition (qualitative HP/MP/SP based on percent, with richer descriptors)
	if (isset($metaData["stats"]) && is_array($metaData["stats"])) {
		$s = $metaData["stats"];
		$describe = function(string $kind, float $cur, float $max): string {
			if ($max <= 0) return "Unknown";
			$pct = ($cur < 0 ? 0.0 : ($cur > $max ? $max : $cur)) / $max * 100.0;
			if ($kind === 'health') {
				if ($pct >= 75.0) return "Near full health";           // 100–75
				if ($pct >= 50.0) return "Wounded";                    // 74–50
				if ($pct >= 25.0) return "Badly wounded";              // 50–25
				return "On the brink of collapse";                      // 24–0
			}
			if ($kind === 'magicka') {
				if ($pct >= 75.0) return "Magicka reserves strong";
				if ($pct >= 50.0) return "Magicka reserves middling";
				if ($pct >= 25.0) return "Magicka reserves low";
				return "Magicka nearly drained";
			}
			// stamina
			if ($pct >= 75.0) return "Well‑rested";
			if ($pct >= 50.0) return "Winded";
			if ($pct >= 25.0) return "Exhausted";
			return "Spent";
		};
		$h = $describe('health', (float)($s['health'] ?? 0), (float)($s['health_max'] ?? 0));
		$m = $describe('magicka', (float)($s['magicka'] ?? 0), (float)($s['magicka_max'] ?? 0));
		$st = $describe('stamina', (float)($s['stamina'] ?? 0), (float)($s['stamina_max'] ?? 0));
		if ($h !== 'Unknown' || $m !== 'Unknown' || $st !== 'Unknown') {
			$lines = [];
			if ($h !== 'Unknown') { $lines[] = "  • Health: {$h}"; }
			if ($m !== 'Unknown') { $lines[] = "  • Magicka: {$m}"; }
			if ($st !== 'Unknown') { $lines[] = "  • Stamina: {$st}"; }
			if (!empty($lines)) {
				$STATS_ADD = "\n\n<condition>\n#Condition\n" . implode("\n", $lines)."\n</condition>\n";
			}
		}
	}
    
    $conditionLines = chimBuildCurrentConditionLinesFromMetadata($metaData["stats"] ?? null, $metaData);
    if (!empty($conditionLines)) {
        $STATS_ADD = "\n\n<condition>\n#Condition\n" . implode("\n", $conditionLines)."\n</condition>\n";
    } else {
        $STATS_ADD = "";
    }

    // Add NPC's known spells (skip for The Narrator)
    if ($FOLLOWER_CONF["HERIKA_NAME"] !== "The Narrator" && isset($metaData["spells"]) && is_array($metaData["spells"])) {
        $spellParts = [];
        // Continue using the same $describedBaseids from equipment/inventory to dedupe across all items
        if (!isset($describedBaseids)) {
            $describedBaseids = [];
        }
        
        // Casting type labels
        $castingTypes = [
            0 => 'Concentration',
            1 => 'Fire & Forget',
            2 => 'Constant'
        ];
        // Delivery type labels
        $deliveryTypes = [
            0 => 'Self',
            1 => 'Contact',
            2 => 'Aimed',
            3 => 'Target Actor',
            4 => 'Target Location'
        ];
        
        foreach ($metaData["spells"] as $spell) {
            $spellName = isset($spell['name']) ? $spell['name'] : null;
            $baseid = isset($spell['baseid']) ? $spell['baseid'] : null;
            $castingType = isset($spell['casting_type']) ? intval($spell['casting_type']) : 0;
            $deliveryType = isset($spell['delivery']) ? intval($spell['delivery']) : 0;
            
            if (empty($spellName)) {
                continue;
            }
            
            // Only add spells that have exact/stable spell descriptions in the database.
            // Do not use legacy wildcard or name fallback here; those can collide with
            // unrelated item descriptions after mod-aware FormID resolution.
            $description = null;
            if (!empty($baseid) && !in_array($baseid, $describedBaseids)) {
                $record = lookupStrictDescriptionByFormID((string) $baseid);
                if (!empty($record['description'])) {
                    $description = $record['description'];
                    $describedBaseids[] = $baseid;
                }
            }
            
            // Skip spells without descriptions
            if (!$description) {
                continue;
            }
            
            // Format: Spell Name (Casting Type, Delivery) - Description
            $castingLabel = $castingTypes[$castingType] ?? 'Unknown';
            $deliveryLabel = $deliveryTypes[$deliveryType] ?? 'Unknown';
            
            $spellLine = "  • {$spellName} ({$castingLabel}, {$deliveryLabel}) - {$description}";
            $spellParts[] = $spellLine;
        }
        
        if (!empty($spellParts)) {
            $SPELLS_ADD = "\n\n<spells>\n#Known Spells\nYou know the following spells:\n" . implode("\n", $spellParts) . "\n</spells>\n";
        } else {
            // NPC has spells in metadata, but none matched descriptions
            $SPELLS_ADD = "\n\n<spells>\n#Known Spells\nYou know no spells.\n</spells>\n";
        }
    }
    
    // Add dialogue target's equipment (if DIALOGUE_TARGET is set)
    if (isset($GLOBALS["DIALOGUE_TARGET"]) && !empty($GLOBALS["DIALOGUE_TARGET"])) {
        $targetName = $GLOBALS["DIALOGUE_TARGET"];
        $targetNpcData = $npcMaster->getByName($targetName);
        
        if ($targetNpcData) {
            $targetMetaData = $npcMaster->getMetaData($targetNpcData);
            
            if (isset($targetMetaData["equipment"]) && is_array($targetMetaData["equipment"])) {
                $targetVanillaEquipmentParts = chimFormatEquipmentPromptLines($targetMetaData["equipment"], chimEquipmentVanillaSlotLabels());
                $targetModdedEquipmentParts = chimFormatEquipmentPromptLines($targetMetaData["equipment"], chimEquipmentModdedSlotLabels());
                $targetEquipmentSections = [];

                if (!empty($targetVanillaEquipmentParts)) {
                    $targetEquipmentSections[] = "Vanilla Slots:\n" . implode("\n", $targetVanillaEquipmentParts);
                }

                if (!empty($targetModdedEquipmentParts)) {
                    $targetEquipmentSections[] = "Modded Slots:\n" . implode("\n", $targetModdedEquipmentParts);
                }
                
                if (!empty($targetEquipmentSections)) {
                    $TARGET_EQUIPMENT_ADD = "\n<target_equipment>\n#{$targetName}'s Equipment\n{$targetName} is currently wearing/wielding:\n" . implode("\n", $targetEquipmentSections);
                    
                    // Check if humanoid NPC has no body armor - if so, note they're naked
                    $humanoidRaces = ['nord', 'imperial', 'breton', 'redguard', 'orc', 'orsimer', 
                                    'altmer', 'highelf', 'bosmer', 'woodelf', 'dunmer', 'darkelf', 
                                    'argonian', 'khajiit', 'khajit'];
                    $targetRace = isset($targetNpcData["race"]) ? strtolower(trim($targetNpcData["race"])) : '';
                    
                    if ($targetRace && in_array($targetRace, $humanoidRaces) && !chimEquipmentHasBodyCoverage($targetMetaData["equipment"])) {
                        $TARGET_EQUIPMENT_ADD .= "\nNote: {$targetName} is naked (no body armor/clothing worn).";
                    }
                    
                    $TARGET_EQUIPMENT_ADD .= "\n</target_equipment>\n";
                }
            }
        }
    }

    foreach ($herikaFields as $fieldName => $label) {
        if (isset($FOLLOWER_CONF[$fieldName]) && !empty(trim($FOLLOWER_CONF[$fieldName]))) {
            $xmlLabel=strtr(strtolower($label),[" "=>"_"]);
            $fieldValue = trim($FOLLOWER_CONF[$fieldName]);

            // Apply conditional XML tag removal for HERIKA_SPEECHSTYLE field
            if ($fieldName === 'HERIKA_SPEECHSTYLE') {
                if (!$forLetter) {
                    // Remove <letter_guidance>...</letter_guidance> and its content
                    $fieldValue = preg_replace('/<letter_guidance>.*?<\/letter_guidance>/is', '', $fieldValue);
                }
                if (!$forThought) {
                    // Remove <inner_thought_guidance>...</inner_thought_guidance> and its content
                    $fieldValue = preg_replace('/<inner_thought_guidance>.*?<\/inner_thought_guidance>/is', '', $fieldValue);
                }
                // Clean up any excessive whitespace left after removal
                $fieldValue = trim(preg_replace('/\n{3,}/', "\n\n", $fieldValue));
            }


            $dynamicBio .= "\n<$xmlLabel>\n" . $fieldValue ."\n</$xmlLabel>";
            
            // Add groups (factions) right after HERIKA_BACKGROUND (basic_summary) section
            if ($fieldName=="HERIKA_BACKGROUND") {
                $extendedData = $npcMaster->getExtendedData($currentNpcData);
                if (isset($extendedData['factions']) && is_array($extendedData['factions']) && count($extendedData['factions']) > 0) {
                    $factionLines = [];
                    foreach ($extendedData['factions'] as $faction) {
                        if (isset($faction['formid'])) {
                            // Lookup faction using helper function (supports XX prefix)
                            $factionRecord = lookupDescriptionByFormID($faction['formid']);
                            
                            // Only add to prompt if found in descriptions table
                            if ($factionRecord && !empty($factionRecord['name'])) {
                                $factionName = $factionRecord['name'];
                                $factionDesc = !empty($factionRecord['description']) ? $factionRecord['description'] : '';
                                $factionLines[] = "{$factionName} - {$factionDesc}";
                            }
                        }
                    }
                    
                    if (count($factionLines) > 0) {
                        $dynamicBio .= "\n<groups>\nYou belong to these factions:\n" . implode("\n", $factionLines) . "\n</groups>";
                    }
                }
            }
            
            // Add skills right after HERIKA_SKILLS section
            if ($fieldName=="HERIKA_SKILLS") {
                $dynamicBio.=!empty($SKILLS_ADD) ?"\n<rpg_skills>\n$SKILLS_ADD\n</rpg_skills>\n": "";
            }
            
        }

        if ($fieldName=="HERIKA_APPEARANCE" && $hasStructuredBiographyFields) {
            // Check if this NPC is reanimated
            $extendedData = $npcMaster->getExtendedData($currentNpcData);
            if (empty($GLOBALS["DISABLE_REANIMATION_TRACKING"]) && isset($extendedData["reanimated"]) && $extendedData["reanimated"] === true) {
                $dynamicBio .= "\n<reanimation_status>\nYou have been reanimated from death as a zombie. Your skin has a deathly pale, greyish pallor with a corpse-like appearance. Your eyes are glazed and lifeless, and your movements are stiff and unnatural.\n</reanimation_status>";
            }

            $dynamicBio.=$EQUIPMENT_ADD ?? "";
            $dynamicBio.=$TARGET_EQUIPMENT_ADD ?? "";
            $dynamicBio.=$INVENTORY_ADD ?? "";
            $dynamicBio.=$ACTIVITY_ADD ?? "";
            $dynamicBio.=$STATS_ADD ?? "";
            $dynamicBio.=$SPELLS_ADD ?? "";
        }
    }
    
    

    // Fall back to HERIKA_DYNAMIC if no new fields are set
    if (empty(trim($dynamicBio)) && isset($FOLLOWER_CONF["HERIKA_DYNAMIC"]) && !empty(trim($FOLLOWER_CONF["HERIKA_DYNAMIC"]))) {
        $dynamicBio = $FOLLOWER_CONF["HERIKA_DYNAMIC"];
    }
    
    if (isset($GLOBALS["HOOKS"]["BIOGRAPHY_BUILDER"])) {
        foreach ($GLOBALS["HOOKS"]["BIOGRAPHY_BUILDER"] as $fName => $builder) {
            error_log("[buildDynamicBiography] BIOGRAPHY_BUILDER {$fName}");

            if (!is_callable($builder)) {
                error_log("[buildDynamicBiography] Builder {$fName} is not callable, skipping.");
                continue;
            }

            // Call the builder. Support both styles:
            //  - builder returns a new bio string
            //  - builder modifies the first argument by-reference
            // We call with call_user_func_array and pass $dynamicBio by-reference so
            // builders that accept a reference can mutate it directly. If the builder
            // returns a non-empty string, prefer that return value as the new bio.
            $result = null;
            try {
                $result = call_user_func_array($builder, array(&$dynamicBio, $currentNpcData));
            } catch (Throwable $e) {
                // Protect against hook errors — log and continue with current bio
                error_log("[buildDynamicBiography] Exception in builder {$fName}: " . $e->getMessage());
                continue;
            }

            if (is_string($result) && strlen(trim($result)) > 0) {
                // Builder returned a non-empty string -> use it
                $dynamicBio = $result;
            }
            // otherwise assume builder mutated $dynamicBio by reference (or left it unchanged)
        }
    }

    if (isset($extendedData["starring_in_quest"])&&!empty($extendedData["starring_in_quest"])) {
        $quest = $GLOBALS["db"]->fetchOne("SELECT * FROM sneq_quests WHERE quest_id='{$extendedData["starring_in_quest"]}'");
        error_log("[SNQE] Current quest data for quest_id {$extendedData["starring_in_quest"]}: {$quest["briefing"]}");
        if ($quest) {
            $questData = json_decode($quest["quest_data"], true);
            $dynamicBio .= "\n<storyline_starring>\n#Current Quest\nYou are currently starring in the quest:{$quest["title"]}\n{$questData["briefing"]} \n</storyline_starring>";

            // Find this NPC's key in the quest npcs list by matching name
            $thisNpcKey = null;
            $thisNpcName = $FOLLOWER_CONF["HERIKA_NAME"] ?? '';
            if (!empty($questData['npcs']) && is_array($questData['npcs'])) {
                foreach ($questData['npcs'] as $npcKey => $npcData) {
                    if (isset($npcData['name']) && strcasecmp($npcData['name'], $thisNpcName) === 0) {
                        $thisNpcKey = $npcKey;
                        break;
                    }
                }
            }

            // Gather topics this NPC is the giver of
            if ($thisNpcKey !== null && !empty($questData['topics']) && is_array($questData['topics'])) {
                $knownTopics = [];
                foreach ($questData['topics'] as $topicKey => $topic) {
                    if (isset($topic['giver']) && $topic['giver'] === $thisNpcKey) {
                        $topicName = $topic['name'] ?? $topicKey;
                        $topicInfo = $topic['info'] ?? '';
                        if (!empty($topicInfo)) {
                            $knownTopics[] = "- {$topicName}: {$topicInfo}";
                        }
                    }
                }
                if (!empty($knownTopics)) {
                    $dynamicBio .= "\n<quest_topics>\n#Topics You Know About\n" . implode("\n", $knownTopics) . "\n</quest_topics>";
                }
            }
        }
        
        
    }
    return $dynamicBio;
}

function buildDynamicProfileDisplay() {
    /**
     * Build formatted dynamic profile display for profile updates
     * @return string The formatted dynamic profile content
     */
    $currentDynamicProfile = '';
    $herikaFields = [
        'HERIKA_BACKGROUND' => 'Background',
        'HERIKA_PERSONALITY' => 'Personality', 
        'HERIKA_APPEARANCE' => 'Appearance',
        'HERIKA_OCCUPATION' => 'Occupation',
        'HERIKA_SKILLS' => 'Skills',
        'HERIKA_SPEECHSTYLE' => 'Speech Style',
        'HERIKA_GOALS' => 'Goals'
    ];
    
    foreach ($herikaFields as $fieldName => $label) {
        if (isset($GLOBALS[$fieldName]) && !empty(trim($GLOBALS[$fieldName]))) {
            $currentDynamicProfile .= "\n$label:\n" . trim($GLOBALS[$fieldName]) . "\n";
        }
    }
    
    // Fall back to HERIKA_DYNAMIC if no new fields are set
    if (empty(trim($currentDynamicProfile)) && isset($GLOBALS["HERIKA_DYNAMIC"]) && !empty(trim($GLOBALS["HERIKA_DYNAMIC"]))) {
        $currentDynamicProfile = "Legacy Dynamic Profile:\n" . $GLOBALS["HERIKA_DYNAMIC"];
    }
    
    if (empty(trim($currentDynamicProfile))) {
        $currentDynamicProfile = "No dynamic profile information available.";
    }
    
    return $currentDynamicProfile;
}


function requireFilesRecursively($dir,$name) {
    
    global $gameRequest;

    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }

        $path = $dir . '/' . $file;

        if (is_dir($path)) {
            requireFilesRecursively($path,$name);
        } elseif (is_file($path) && $file === $name) {
            require_once($path);
        } 
    }
}


/**
 * Parses a PHP file and extracts variable assignments into an associative array.
 *
 * Handles:
 * - Scalars: $name = 'Herika';
 * - Arrays: $data = ["a", "b"];
 * - Nested array keys: $a["x"]["y"] = 123;
 *
 * All values are returned in raw form (e.g., quoted strings are unquoted).
 *
 * @param string $filePath Path to the PHP file to parse.
 * @return array Associative array of variable names (or paths) => raw values.
 */
function extract_assignments($filePath) {
    $code = file_get_contents($filePath);
    $tokens = token_get_all($code);

    $variables = [];
    $varName = '';
    $indexStack = [];
    $collectValue = false;
    $valueBuffer = '';
    $bracketDepth = 0;
    $expectingKey = false;

    foreach ($tokens as $i => $token) {
        if (is_array($token)) {
            [$id, $text] = $token;

            if ($id === T_VARIABLE) {
                $varName = substr($text, 1);
                $indexStack = [];
                $collectValue = false;
                $valueBuffer = '';
                $bracketDepth = 0;
                $expectingKey = false;
            }

            elseif ($expectingKey && in_array($id, [T_CONSTANT_ENCAPSED_STRING, T_STRING, T_LNUMBER, T_DNUMBER])) {
                $indexStack[] = trim($text, "'\"");
                $expectingKey = false;
            }

            elseif ($collectValue) {
                $valueBuffer .= $text;
            }

        } else {
            // Symbolic tokens
            if ($token === '[' && !$collectValue) {
                $expectingKey = true;
            }

            elseif ($token === '=' && !$collectValue) {
                $collectValue = true;
                $valueBuffer = '';
                $bracketDepth = 0;
            }

            elseif ($collectValue) {
                if ($token === '[') {
                    $bracketDepth++;
                    $valueBuffer .= $token;
                } elseif ($token === ']') {
                    $bracketDepth--;
                    $valueBuffer .= $token;
                } elseif (($token === ';' || $token === ',') && $bracketDepth === 0) {
                    // Don't add the terminating character to the buffer
                    $rawValue = trim($valueBuffer);

                    // Remove quotes and unescape if present
                    if (strlen($rawValue) >= 2) {
                        if ($rawValue[0] === '"' && $rawValue[-1] === '"') {
                            // Double-quoted string - remove quotes and unescape
                            $rawValue = stripcslashes(substr($rawValue, 1, -1));
                        } elseif ($rawValue[0] === "'" && $rawValue[-1] === "'") {
                            // Single-quoted string - remove quotes and unescape single quotes and backslashes
                            $rawValue = substr($rawValue, 1, -1);
                            $rawValue = str_replace(["\\'", "\\\\"], ["'", "\\"], $rawValue);
                        }
                    }

                    // Build full key
                    $fullKey = $varName;
                    foreach ($indexStack as $key) {
                        $fullKey .= "['$key']";
                    }

                    $variables[$fullKey] = $rawValue;

                    // Reset state
                    $collectValue = false;
                    $valueBuffer = '';
                    $indexStack = [];
                } else {
                    $valueBuffer .= $token;
                }
            }

            // Reset expectingKey if we see closing bracket
            if ($token === ']' && !$collectValue) {
                $expectingKey = false;
            }
        }
    }

    return $variables;
}


/**
 * Writes variable assignments to a PHP file using clean formatting.
 *
 * Accepts keys like 'VAR' or 'ARRAY["KEY"]["SUB"]' and writes them back to PHP code.
 * Automatically quotes strings, and leaves numbers, booleans, null, and arrays untouched.
 *
 * @param array $assignments The variable assignments, as [name => raw_value]
 * @param string $filePath Path to save the output PHP code
 */
function write_php_assignments(array $assignments, string $filePath): bool {
    $output = "<?php\n\n";

    foreach ($assignments as $key => $value) {
        // Clean the value - remove trailing semicolons and trim whitespace
        $cleaned = rtrim(trim($value), ';');
        
        // If the value is already quoted, unquote it first
        if (strlen($cleaned) >= 2) {
            if (($cleaned[0] === '"' && $cleaned[-1] === '"') || 
                ($cleaned[0] === "'" && $cleaned[-1] === "'")) {
                $cleaned = substr($cleaned, 1, -1);
            }
        }
        
        // Now determine the correct output format based on the cleaned value
        $lowerCleaned = strtolower($cleaned);
        
        if (in_array($lowerCleaned, ['true', 'false', 'null'], true)) {
            // Boolean or null values - output as-is
            $finalValue = $lowerCleaned;
        } elseif (is_numeric($cleaned) && !str_contains($cleaned, ' ')) {
            // Numeric values - output as-is
            $finalValue = $cleaned;
        } elseif (preg_match('/^\s*\[.*\]\s*$/s', $cleaned)) {
            // Array literals - output as-is
            $finalValue = $cleaned;
        } else {
            // String values - apply comprehensive sanitization for AI-generated content
            if (is_string($cleaned)) {
                // Sanitize AI-generated content to prevent PHP syntax errors
                $cleaned = str_replace("\0", '', $cleaned); // Remove null bytes
                $cleaned = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $cleaned); // Remove control chars
                if (!mb_check_encoding($cleaned, 'UTF-8')) {
                    $cleaned = mb_convert_encoding($cleaned, 'UTF-8', 'UTF-8'); // Fix encoding
                }
                if (strlen($cleaned) > 100000) {
                    $cleaned = substr($cleaned, 0, 100000) . '... [truncated]'; // Limit length
                }
                $cleaned = str_replace(['<?php', '<?', '?>'], ['&lt;?php', '&lt;?', '?&gt;'], $cleaned); // Escape PHP tags
                
                // Additional sanitization for var_export compatibility
                $cleaned = str_replace('\\', '\\\\', $cleaned); // Escape backslashes
                $cleaned = str_replace("\r\n", "\n", $cleaned); // Normalize line endings
                $cleaned = str_replace("\r", "\n", $cleaned); // Convert Mac line endings
                $cleaned = preg_replace('/\n{3,}/', "\n\n", $cleaned); // Limit consecutive newlines
            }
            
            // Use var_export for safer escaping instead of addslashes
            $finalValue = var_export($cleaned, true);
        }

        // Build the assignment line
        if (strpos($key, '[') !== false) {
            $line = "\${$key} = {$finalValue};";
        } else {
            $line = "\$$key = {$finalValue};";
        }

        $output .= $line . "\n";
    }

    return file_put_contents($filePath, $output, LOCK_EX);
}

function getInGameSkillDataFor($npcName) {

    $npcEscapedName=$GLOBALS["db"]->escape($npcName);
    $query="
WITH npc_spells AS (
  SELECT
    TRIM(SUBSTRING(data FROM '$npcEscapedName casts\s+(.+)$')) AS spell
  FROM public.eventlog
  WHERE type = 'npcspellcast' AND data LIKE '$npcEscapedName casts%'
),

npc_weapons AS (
  SELECT
    TRIM(SUBSTRING(data FROM 'using weapon\s+(.+)$')) AS weapon
  FROM public.eventlog
  WHERE type = 'death' AND data LIKE '%$npcEscapedName has defeated%'
)

SELECT
  'spell' AS type,
  spell AS item,
  COUNT(*) AS usage_count
FROM npc_spells
where spell is not null
GROUP BY spell
HAVING COUNT(*)>1

UNION ALL

SELECT
  'weapon' AS type,
  weapon AS item,
  COUNT(*) AS usage_count
FROM npc_weapons
where weapon is not null
GROUP BY weapon
HAVING COUNT(*)>1

ORDER BY type, usage_count DESC;
";
    $skillsData=$GLOBALS["db"]->fetchAll($query);

    if (sizeof ($skillsData)==0)
        return "";

    $spells = [];
    $weapons = [];

    foreach ($skillsData as $entry) {
        if ($entry['type'] === 'spell') {
            $spells[] = $entry['item'];
        } elseif ($entry['type'] === 'weapon') {
            $weapons[] = $entry['item'];
        }
    }

    // Store in strings
    $spellsList = sizeof($spells)>0?implode(', ', $spells):"none";
    $weaponsList = sizeof($weapons)>0?implode(', ', $weapons):"none";
    

    return "* Fav.Spells: $spellsList\n* Fav. Weapons: $weaponsList\n";
}

/**
 * Safely export a value to PHP code with comprehensive sanitization to prevent syntax errors
 * 
 * This function sanitizes AI-generated content to prevent PHP syntax errors that can occur
 * with standard var_export() when dealing with special characters, encoding issues, etc.
 * 
 * @param mixed $value The value to export
 * @param bool $return Whether to return the string instead of outputting it
 * @return string|null The exported PHP code
 */
function safe_var_export($value, $return = true) {
    // First, sanitize string values
    if (is_string($value)) {
        // Remove null bytes that can break PHP parsing
        $value = str_replace("\0", '', $value);
        
        // Ensure valid UTF-8 encoding
        if (!mb_check_encoding($value, 'UTF-8')) {
            $value = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
        }
        
        // Remove or replace problematic characters
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value);
        
        // Limit length to prevent extremely long strings
        if (strlen($value) > 100000) {
            $value = substr($value, 0, 100000) . '... [truncated]';
        }
        
        // Ensure balanced quotes and backslashes don't break escaping
        $value = str_replace(['\\', "'", '"'], ['\\\\', "\\'", '\\"'], $value);
        $value = stripslashes($value); // Remove double escaping
    }
    
    // Try var_export first
    $exported = var_export($value, true);
    
    // Validate that the exported code is syntactically correct
    $testCode = "<?php return $exported; ?>";
    
    // Use eval to test syntax (in a safe way)
    $oldLevel = error_reporting(0);
    $syntaxValid = @eval("return true; $testCode") !== false;
    error_reporting($oldLevel);
    
    if (!$syntaxValid) {
        // Fallback: manual string escaping for safety
        if (is_string($value)) {
            $exported = "'" . addcslashes($value, "'\\") . "'";
        } else {
            // For non-strings, convert to string safely
            $exported = "'" . addcslashes((string)$value, "'\\") . "'";
        }
    }
    
    if ($return) {
        return $exported;
    } else {
        echo $exported;
        return null;
    }
}

/**
 * Safely update a PHP configuration file variable with proper error handling
 * 
 * @param string $filePath Path to the PHP file
 * @param string $varName Variable name (without $)
 * @param mixed $value New value
 * @return array Result with success status and message
 */
function safe_update_php_variable($filePath, $varName, $value) {
    if (!file_exists($filePath)) {
        return ["success" => false, "error" => "File not found: " . basename($filePath)];
    }
    
    // Read current content
    $content = file_get_contents($filePath);
    if ($content === false) {
        return ["success" => false, "error" => "Cannot read file: " . basename($filePath)];
    }
    
    // Use safe export
    $escapedValue = safe_var_export($value, true);
    
    // Validate the escaped value produces valid PHP
    $testAssignment = "\$$varName = $escapedValue;";
    $testCode = "<?php $testAssignment ?>";
    
    $oldLevel = error_reporting(0);
    $syntaxValid = @eval("return true; $testCode") !== false;
    error_reporting($oldLevel);
    
    if (!$syntaxValid) {
        return ["success" => false, "error" => "Generated PHP code would be invalid for variable $varName"];
    }
    
    // Update or add variable
    $pattern = '/\$' . preg_quote($varName, '/') . '\s*=\s*[^;]+;/';
    if (preg_match($pattern, $content)) {
        $content = preg_replace($pattern, '$' . $varName . '=' . $escapedValue . ';', $content);
    } else {
        // Add before closing 
        $content = str_replace('?>', '$' . $varName . '=' . $escapedValue . ';' . PHP_EOL . '?>', $content);
    }
    
    // Write with atomic operation
    $tempFile = $filePath . '.tmp.' . uniqid();
    if (file_put_contents($tempFile, $content, LOCK_EX) === false) {
        return ["success" => false, "error" => "Cannot write to temporary file"];
    }
    
    if (!rename($tempFile, $filePath)) {
        unlink($tempFile);
        return ["success" => false, "error" => "Cannot update file: " . basename($filePath)];
    }
    
    return ["success" => true, "message" => "Variable $varName updated successfully"];
}


/**
 * Retrieves base data for an NPC from the event log based on the NPC's name.
 *
 * This function queries the database for the most recent log entry of type 'addnpc'
 * that matches the given NPC name. It extracts and returns the NPC's gender, race,
 * and reference ID from the log data. If the NPC name is empty, no matching data is found,
 * or the data is insufficient, the function returns null.
 *
 * @param string $npcname The name of the NPC to retrieve data for.
 * @return array|null An associative array containing 'gender', 'race', and 'refid' keys,
 *                    or null if no valid data is found.
 */
function getBaseDataForNpcFromLog($npcname) {
    if (empty($npcname)) {
        error_log("getBaseDataForNpcFromLog: NPC name is empty.");
        return null;
    }

    $npcNameEscaped = $GLOBALS["db"]->escape($npcname);
    $result = $GLOBALS["db"]->fetchOne("SELECT data FROM eventlog WHERE type='addnpc' AND data LIKE '$npcNameEscaped%' ORDER BY rowid DESC LIMIT 1");

    if (!$result || !isset($result["data"])) {
        error_log("getBaseDataForNpcFromLog: No data found for NPC '$npcname'.");
        return null;
    }

    $splitNameBase = explode("@", $result["data"]);
    if (count($splitNameBase) < 5) {
        error_log("getBaseDataForNpcFromLog: Insufficient data for NPC '$npcname'. Data: " . print_r($result["data"], true));
        return null;
    }

    $currentNpcData = [
        "gender" => $splitNameBase[2],
        "race" => $splitNameBase[3],
        "refid" => $splitNameBase[4]
    ];

    return $currentNpcData;
}

function getLastLocationNamedCell() {
    $q="SELECT A.gamets,A.localts,cell_name,C.name as location_name,statics_list,A.sess::BIGINT,interior,worldspace,B.location_id
FROM public.eventlog A
LEFT JOIN public.named_cell B ON (B.id = A.sess::BIGINT )
LEFT JOIN public.locations C ON (C.formid=B.location_id  )
WHERE A.sess ~ '^[0-9]+$' and type='request'
and A.sess<>'pending'
order by A.gamets desc,A.localts desc
limit 1";

    $locData=$GLOBALS["db"]->fetchOne($q);
    $locationDetailedName = null;
    if ($locData && isset($locData['location_name']) && !empty($locData['location_name'])) {
        $locData['worldspace']=trim($locData['worldspace'] ?? '');
        if ($locData['worldspace']=="Skyrim") {
            $locationDetailedName = $locData['location_name'] . " (outdoors)";
        } else {
            $locationDetailedName = $locData['location_name'] . " (inside " . $locData['worldspace'] . ")";
        }
        if ($locData['interior']==1) {
            $locationDetailedName.=" (interior)";
        } 
        
    } else {
        $locationDetailedName=$locData["cell_name"] ?? "Unknown Location";
    }

    return $locationDetailedName;
}

/**
 * Build a situational map description with doors/passages and their directions
 * 
 * Retrieves the current cell from the eventlog, finds all doors in the same worldspace,
 * and generates a description of available passages with compass directions based on
 * relative door positions.
 * 
 * @return string Situational map description with doors and their directions
 */
function buildSituationalMapDescription() {
    // Get current cell from eventlog
    $current_cell_result = $GLOBALS["db"]->fetchOne(
        "SELECT A.sess::BIGINT as current_cell
         FROM public.eventlog A
         WHERE A.sess ~ '^[0-9]+$' and type='request'
         and A.sess<>'pending'
         order by A.gamets desc, A.localts desc
         limit 1"
    );
    
    if (!$current_cell_result || !isset($current_cell_result['current_cell'])) {
        error_log("buildSituationalMapDescription: No current cell found in eventlog.");
        return "";
    }
    
    $current_cell_id = $current_cell_result['current_cell'];
    
    // Get the worldspace of the current cell
    $current_cell_data = $GLOBALS["db"]->fetchOne(
        "SELECT worldspace,cell_name,location_id FROM named_cell WHERE id = {$current_cell_id} LIMIT 1"
    );
    
    if (!$current_cell_data) {
        error_log("buildSituationalMapDescription: Current cell ID {$current_cell_id} not found in named_cell.");
        return "";
    }
    
    $current_worldspace = trim($current_cell_data['worldspace'] ?? '');
    $current_cell_name = trim($current_cell_data['cell_name'] ?? '');
    
    $current_player_cell_data = $GLOBALS["db"]->fetchOne(
        "SELECT worldspace,cell_name,location_id,door_x,door_y FROM named_cell WHERE id = 0 LIMIT 1"
    );

    $player_x=$current_player_cell_data['door_x'] ?? 0;
    $player_y=$current_player_cell_data['door_y'] ?? 0;

    // If worldspace is Skyrim, just return base description
    if ($current_worldspace === 'Skyrim') {
        error_log("buildSituationalMapDescription: Current worldspace is Skyrim, returning base description.");
        // Get all doors in the worldspace Skyrim, with valid coordinates and (distance< 1024 *10), door_x,door_y is relative to player position
        $doors_result = $GLOBALS["db"]->fetchAll(
            "SELECT id, cell_name, door_name, door_id,door_x, door_y, dest_door_exterior, interior,location_id, sqrt((door_x-({$player_x}))*(door_x-({$player_x})) + (door_y-({$player_y}))*(door_y-({$player_y}))) as distance
            FROM named_cell 
            WHERE worldspace = '{$current_worldspace}' and location_id={$current_cell_data['location_id']}
            AND door_name <> ''
            AND id<>dest_door_cell_id
            ORDER BY id"
        );
        
    } else {
        error_log("buildSituationalMapDescription: Current worldspace is '{$current_worldspace}', fetching doors in the same worldspace.");
        // Get all doors in the same worldspace 
        $doors_result = $GLOBALS["db"]->fetchAll(
            "SELECT id, cell_name, door_name, door_id,door_x, door_y, dest_door_exterior, interior,location_id, sqrt((door_x-({$player_x}))*(door_x-({$player_x})) + (door_y-({$player_y}))*(door_y-({$player_y}))) as distance
            FROM named_cell 
            WHERE worldspace = '{$current_worldspace}' and location_id={$current_cell_data['location_id']}
            AND door_name <> ''
            AND id<>dest_door_cell_id
            ORDER BY id"
        );
    }
    
    if (empty($doors_result)) {
        error_log("buildSituationalMapDescription: No doors found in worldspace '{$current_worldspace}' for cell ID {$current_cell_id}.");  
        if ($current_worldspace != $current_cell_name)
            return "You are in {$current_worldspace}, {$current_cell_name}. No other exits found.";
        else
            return "You are in {$current_worldspace}. No other exits found.";
    }
    
    $directional_doors = array();
    
    // Categorize doors by direction
    foreach ($doors_result as $door) {
        $door_x = floatval($door['door_x']);
        $door_y = floatval($door['door_y']);
        $door_name = trim($door['door_name'] ?? 'Unknown');
        $dest_worldspace = trim($door['dest_door_exterior'] ?? '');
        $interior = intval($door['interior'] ?? 0);
        $distance = round(floatval($door['distance'] ?? 0)/70);// Convert to approximate meters (assuming 70 units = 1 meter)

        $unsignedInt = $door['door_id'] & 0xFFFFFFFF;
        $doorHexid=  "0x" . str_pad(dechex($unsignedInt), 8, "0", STR_PAD_LEFT);

        
        if ($distance > 1000) {
            error_log("buildSituationalMapDescription: Ignoring door '{$door_name}' at distance {$distance} meters (too far).");
            // Ignore doors farther than 1000 meters
            continue;
        }
        // Calculate relative position
        $delta_x = $door_x - $player_x;
        $delta_y = $door_y - $player_y;
        // error_log("Door '{$door_name}' at ({$door_x}, {$door_y}), delta ({$delta_x}, {$delta_y})");
        // Determine cardinal direction
        $direction = getCardinalDirection($delta_x, $delta_y);
        
        
        $passage_type = "Door/Passage to {$door_name} ({$distance} meters) [door id:{$doorHexid}]";
                
        if (!isset($directional_doors[$direction])) {
            $directional_doors[$direction] = array();
        }
        if ($current_cell_id == $door['id']) {
            // We're at this cell
            if ($interior==1) {
                $current_worldspace = $door["cell_name"];
            } else {
                
            }
        }
        $directional_doors[$direction][] = $passage_type;
    }
    
    // Build the map description
    
    if ($current_worldspace != $current_cell_name)
        $map_description = "You are in {$current_worldspace}, {$current_cell_name}. ";
    else
        $map_description = "You are in {$current_cell_name}. ";

    $passages = array();
    
    $cardinal_order = array('North', 'Northeast', 'East', 'Southeast', 'South', 'Southwest', 'West', 'Northwest');
    
    foreach ($cardinal_order as $direction) {
        if (isset($directional_doors[$direction])) {
            foreach ($directional_doors[$direction] as $passage) {
                $passages[] = "{$passage} at {$direction}";
            }
        }
    }
    
    if (!empty($passages)) {
        $map_description .= implode(", ", $passages) . ".";
    } else {
        $map_description .= "No other exits found.";
    }
    
    return $map_description;
}

/**
 * Helper function to determine cardinal direction from relative coordinates
 * 
 * @param float $delta_x Change in X coordinate
 * @param float $delta_y Change in Y coordinate
 * @return string Cardinal direction (N, NE, E, SE, S, SW, W, NW)
 */
function getCardinalDirection($delta_x, $delta_y) {
    // Normalize to get angle
    $angle = atan2($delta_y, $delta_x) * 180 / M_PI;
    
    // Adjust angle to 0-360 range
    if ($angle < 0) {
        $angle += 360;
    }
    
    // Map angle to cardinal direction
    // Using 22.5 degree boundaries for 8-point compass
    if ($angle >= 337.5 || $angle < 22.5) {
        return 'East';
    } elseif ($angle >= 22.5 && $angle < 67.5) {
        return 'Northeast';
    } elseif ($angle >= 67.5 && $angle < 112.5) {
        return 'North';
    } elseif ($angle >= 112.5 && $angle < 157.5) {
        return 'Northwest';
    } elseif ($angle >= 157.5 && $angle < 202.5) {
        return 'West';
    } elseif ($angle >= 202.5 && $angle < 247.5) {
        return 'Southwest';
    } elseif ($angle >= 247.5 && $angle < 292.5) {
        return 'South';
    } else {
        return 'Southeast';
    }
}

/*
is_interior now represents a bitwise obtained result:

Value	Meaning
00	    Exists, exterior
01	    Exists, interior
10	    Doesn't exist
11	    Reserved (or treat as invalid)
Bits	Field
0-1	    inside_entrance
2-3	    location_center
4-5	    raw_location_marker
6-7	    outside_entrance
*/

function checkInterior(int $value) {
    // if inside_entrance is interior, or location_center is interior or raw_location_marker is interior, we return true
    $insideEntrance = ($value & 0b11) === 0b01;
    $locationCenter = ($value & 0b1100) === 0b0100;
    $rawLocationMarker = ($value & 0b110000) === 0b010000;

    return $insideEntrance || $locationCenter || $rawLocationMarker;
}

function getInteriorRef($locationRow) {
    // locationRow is a row result orm locations
    
    // e.g. 0x0001bdf1:0x1a0559e0;0x0001bdf1:0x1a0559e0;0x0010f63c:0x1a01c7b7
    // 0x0001bdf1 is the type, 0x1a0559e0 is the reference formid
    // types:
    // 0x0001bdf1 locationCenterRefType
    // 0x000130fb outsideEntranceMarkerRefType
    // 0x000130fc insideMarkerRefType
    // 0x0010f63c mapMarkerRefType
    // so we must parse refs column on $locationRow and get the first reference with this types and precedence order:
    // 1) insideMarkerRefType
    // 2) locationCenterRefType
    // 3) mapMarkerRefType

    $refs = explode(';', $locationRow['refs']);
    $precedence = [
        '0x000130fc', // insideMarkerRefType
        '0x0001bdf1', // locationCenterRefType
        '0x0010f63c', // mapMarkerRefType
    ];
    
    foreach ($precedence as $type) {
        foreach ($refs as $ref) {
            list($refType, $refFormid) = explode(':', $ref);
            if ($refType === $type) {
                return $refFormid;
            }
        }
    }
    return null;
}

/**
 * Resolve a TravelTo location using exact + fuzzy matching and optional coord distance.
 *
 * @param string $location
 * @param array $currentNpcData
 * @param object $db
 * @return array|null
 */
function resolveTravelLocation($location, $currentNpcData, $db)
{
    $cnLocation = $db->escape($location);

    if (strcasecmp($cnLocation, 'random') === 0) {
        return $db->fetchOne(
            "SELECT name, region, hold, formid, coords
             FROM locations
             ORDER BY CASE WHEN name = region THEN 1 ELSE 0 END DESC, random()
             LIMIT 1"
        );
    }

    $npcPoint = getNpcLastCoordsPoint($currentNpcData);
    $metaData=json_decode($currentNpcData['metadata'] ?? '{}', true);
    $pointSql = '';
    $orderByDistanceSql = '';
    if (!empty($npcPoint) && ($metaData["last_coords"]["world"]=="Skyrim") || $metaData["last_coords"]["world"]=="Whiterun") {// Only use coords on global worldspace
    
        $npcPointEsc = $db->escape($npcPoint);
        $pointSql = ", coords <-> '{$npcPointEsc}'::point AS dist";
        $orderByDistanceSql = ', dist ASC';
    }

    // Prefer exact matches first, then fuzzy similarity. If we know NPC coords,
    // nearest matching marker is preferred when names collide.
   

    $query = "
    SELECT
        name,
        region,
        hold,
        formid,
        coords,
        is_interior,refs,
        (
            (is_interior & 3) = 1 OR
            (is_interior & 12) = 4 OR
            (is_interior & 48) = 16
        ) AS has_interior
        $pointSql,
        GREATEST(
            COALESCE(similarity(name, '$cnLocation'), 0),
            COALESCE(similarity(name||' (Interior)', '$cnLocation'), 0),
            COALESCE(similarity(region, '$cnLocation'), 0),
            COALESCE(similarity(hold, '$cnLocation'), 0)
        ) AS sim,
        CASE
            WHEN lower(name) = lower('$cnLocation') THEN 3
            WHEN lower(name||' (Interior)') = lower('$cnLocation')
                 AND (
                    (is_interior & 3) = 1 OR
                    (is_interior & 12) = 4 OR
                    (is_interior & 48) = 16
                 )
            THEN 4
            WHEN lower(region) = lower('$cnLocation') THEN 2
            WHEN lower(hold) = lower('$cnLocation') THEN 1
            ELSE 0
        END AS exact_rank
     FROM locations
     WHERE formid IS NOT NULL
     ORDER BY exact_rank DESC, sim DESC$orderByDistanceSql,updated_at DESC
     LIMIT 1";


    //error_log("resolveTravelLocation query: $query");
    $loc = $db->fetchOne($query);

    if (strpos($location, '(Interior)') !== false && checkInterior($loc['is_interior'])) {
        // Interior location requested, we should return an interior reference.
        $loc["direct_destination_ref"] = getInteriorRef($loc);
    }

    return $loc ?: null;
}

function DataSearchMemoryByVectorFromContextKeywords($contextKeywords,$npcfilter,$timeThreshold=0) {
    
        $localStartTime=microtime(true);

        $scopeConditionSql = dataGetMemoryScopeConditionSql($npcfilter);
        $companionConditionSql = dataGetMemoryCompanionConditionSql($npcfilter);
        
        $url = $GLOBALS["FEATURES"]["MEMORY_EMBEDDING"]["TXTAI_URL"].'/embed';

        $data = [
            
            'text' => $contextKeywords   
        ];

        // Convert to JSON
        $options = [
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: application/json\r\n" .
                            "Accept: application/json\r\n",
                'content' => json_encode($data),
                'ignore_errors' => true // to capture error messages if any
            ]
        ];

        // Create context and send the request
        $context  = stream_context_create($options);
        
        error_log("[DataSearchMemoryByVector Embedding start] Elapsed time: " . (microtime(true) - $localStartTime) . " seconds");
        $response = file_get_contents($url, false, $context);
        error_log("[DataSearchMemoryByVector Embedding end] Elapsed time: " . (microtime(true) - $localStartTime) . " seconds");

        // Output the response
        if ($response === false) {
            Logger::error("Request failed.\n");
        } else {
            Logger::info("Request done:\n");

        }
        $contextKeywords=$GLOBALS["db"]->escape($contextKeywords);
        $resultNormalized = chimNormalizeTsQueryTerms($contextKeywords);
        $kwStringAny=implode(" | ",$resultNormalized);
        $kwStringAll=implode(" & ",$resultNormalized);
        error_log("[DataSearchMemoryByVector] Generated Tags: $kwStringAny" );
        $vector=json_decode($response,true);

        if (is_array($vector) && isset($vector["embedding"])) {
            $vectorString="'[".implode(",",$vector["embedding"])."]'";
            $rankAnySql = $kwStringAny !== ''
                ? "ts_rank(native_vec, to_tsquery('" . $GLOBALS["db"]->escape($kwStringAny) . "'))"
                : "0::real";
            $rankAllSql = $kwStringAll !== ''
                ? "ts_rank(native_vec, to_tsquery('" . $GLOBALS["db"]->escape($kwStringAll) . "'))"
                : "0::real";
            $rankCombinedSql = "($rankAnySql + $rankAllSql)";

            $finalQuery="
                SELECT rowid,gamets_truncated,
                        embedding <-> $vectorString as distance,
                         $rankAnySql AS rank_any_fts_raw,
                         $rankAllSql AS rank_all_fts_raw,
                         $rankCombinedSql AS rank_fts,
                         (embedding <-> $vectorString) - $rankCombinedSql AS mixed_distance,
                         summary,
                         '$contextKeywords' as keywords_used
                    FROM public.memory_summary 
                    WHERE embedding IS NOT NULL
                    and $scopeConditionSql
                    and $companionConditionSql
                    and (gamets_truncated<$timeThreshold or $timeThreshold=0)
                    
                    ORDER BY
                        mixed_distance ASC,
                        distance ASC,
                        gamets_truncated DESC,
                        rowid DESC
                    LIMIT 50 OFFSET 0
                ";    
            $memory=$GLOBALS["db"]->fetchAll($finalQuery);
            $singleMemory = chimSelectBestHybridMemoryCandidate($memory);
         
            if (!isset($singleMemory)) {
                $singleMemory = [
                    "rank_any" => null,
                    "rank_all" => null,
                    "summary" => null,
                    "distance" => 1.4,
                    "mixed_distance" => 1.4,
                ];
            }
            
            /*error_log("
                 SELECT summary, gamets_truncated,
                        embedding <-> $vectorString as distance,
                         ts_rank(native_vec, to_tsquery('$kwStringAny')) AS rank_any_fts,
                         ts_rank(native_vec, to_tsquery('$kwStringAll')) AS rank_all_fts
                    FROM public.memory_summary 
                    WHERE embedding IS NOT NULL
                    and companions like '%{$GLOBALS["db"]->escape($npcfilter)}%'
                    ORDER BY (embedding <-> $vectorString)-ts_rank(native_vec, to_tsquery('$kwStringAny')) 
                    LIMIT 5 OFFSET 0
                ");*/

            $GLOBALS["db"]->insert(
                    'audit_memory',
                    array(
                        'input' => $TEST_TEXT,
                        'keywords' =>'text2vec search / (input plus "'.$contextKeywords.'"',
                        'rank_any'=> (1.40-$singleMemory["mixed_distance"]),// Try to mimic FTS query rank
                        'rank_all'=> (1.40-$singleMemory["distance"]),// Try to mimic FTS query rank
                        'memory'=>$singleMemory["summary"],
                        'time'=>isset($vector["timing"])?$vector["timing"]["generation_time_seconds"]:"0 secs (text2vec)"
                    )
                );
            
        } else {
            return null;
        }
            
    
    return [$singleMemory];
    
}

?>
