<?php
/**
 * RELATIONSHIP SYSTEM - NPC Save Handler
 *
 * This file processes the relationships_jsonb field when an NPC is saved.
 * It merges the relationship data into extended_data.relationships.
 *
 * INSTALLATION:
 * Add this line in npc_master.php BEFORE the extended_data processing (around line 195):
 *
 *   // Merge relationship editor data into extended_data
 *   if (file_exists(__DIR__."/../../ext/relationship_system/npc_save_handler.php")) {
 *       include(__DIR__."/../../ext/relationship_system/npc_save_handler.php");
 *   }
 */

// Ensure Logger is available (parent may have already loaded it)
if (!class_exists('Logger')) {
    require_once $GLOBALS["ENGINE_PATH"] . "lib/logger.php";
}
if (!class_exists('RelationshipManager')) {
    require_once $GLOBALS["ENGINE_PATH"] . "lib/relationship_manager.php";
}

// Only process if relationships_jsonb was submitted
if (isset($_POST['relationships_jsonb']) && $_POST['relationships_jsonb'] !== '') {
    $relJsonbRaw = $_POST['relationships_jsonb'];
    $relData = json_decode($relJsonbRaw, true);

    if (is_array($relData)) {
        foreach ($relData as &$relationship) {
            if (is_array($relationship) && array_key_exists('custom_info', $relationship)) {
                $relationship['custom_info'] = is_scalar($relationship['custom_info'])
                    ? trim((string)$relationship['custom_info'])
                    : '';
            }
        }
        unset($relationship);
        $relData = RelationshipManager::normalizeRelationshipMap($relData);

        // Get existing extended_data
        $extRaw = isset($_POST['extended_data']) ? (string)$_POST['extended_data'] : '{}';
        $extData = json_decode($extRaw, true);
        if (!is_array($extData)) {
            $extData = [];
        }

        // Get the old relationships for logging
        $oldRels = $extData['relationships'] ?? [];

        // Merge the new relationships
        $extData['relationships'] = $relData;

        // RELATIONSHIP LOCK: persist the editor's lock checkbox (hidden field always submits 0/1) so the relationship
        // model skips this NPC and stops overwriting manual edits.
        if (isset($_POST['relationships_locked'])) {
            $extData['relationships_locked'] = filter_var($_POST['relationships_locked'], FILTER_VALIDATE_BOOLEAN);
        }

        // Log the changes
        $npcName = $_POST['npc_name'] ?? 'Unknown';
        $changeCount = 0;

        foreach ($relData as $target => $newData) {
            $oldData = $oldRels[$target] ?? null;

            if ($oldData === null) {
                // New relationship
                Logger::info("[REL-UI] {$npcName}: Added relationship -> {$target}: aff={$newData['aff']}, type={$newData['type']}");
                $changeCount++;
            } elseif ($oldData['aff'] !== $newData['aff'] || $oldData['type'] !== $newData['type']) {
                // Modified relationship
                $oldAff = $oldData['aff'] ?? 0;
                $oldType = $oldData['type'] ?? 'neutral';
                Logger::info("[REL-UI] {$npcName}: Updated {$target}: aff {$oldAff} -> {$newData['aff']}, type {$oldType} -> {$newData['type']}");
                $changeCount++;
            }
        }

        // Log removed relationships
        foreach ($oldRels as $target => $oldData) {
            if (!isset($relData[$target])) {
                Logger::info("[REL-UI] {$npcName}: Removed relationship -> {$target}");
                $changeCount++;
            }
        }

        if ($changeCount > 0) {
            Logger::info("[REL-UI] {$npcName}: {$changeCount} relationship change(s) saved via UI");
        }

        // Update extended_data in POST. The structured editor is authoritative;
        // prevent the deprecated legacy textarea named "relationships" from
        // being treated as a relationship seed later by NpcMaster normalization.
        $_POST['extended_data'] = json_encode($extData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        unset($_POST['relationships'], $_POST['npc_relationships']);
    }
}
