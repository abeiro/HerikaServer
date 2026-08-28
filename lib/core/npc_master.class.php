<?php

if (!function_exists('chimParseStableFormReference')) {
    require_once(__DIR__ . DIRECTORY_SEPARATOR . "game_plugins.php");
}

if (!function_exists('herikaRolemasterStateToBool')) {
    function herikaRolemasterStateToBool($value)
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return $value != 0;
        }

        $text = strtolower(trim(strval($value)));
        if ($text === '' || $text === '0' || $text === 'false' || $text === 'f' || $text === 'no' || $text === 'off' || $text === 'null') {
            return false;
        }

        return in_array($text, ['1', 'true', 't', 'yes', 'on'], true);
    }
}

if (!function_exists('herikaRolemasterStateResetCache')) {
    function herikaRolemasterStateResetCache()
    {
        unset($GLOBALS['HERIKA_ROLEMASTER_LOOKUP_CACHE']);
        unset($GLOBALS['HERIKA_ROLEMASTER_LEGACY_CACHE']);
    }
}

if (!function_exists('herikaRolemasterStateGetLegacyFlag')) {
    function herikaRolemasterStateGetLegacyFlag($npcName = '')
    {
        $npcName = trim(strval($npcName));
        if ($npcName === '') {
            return false;
        }

        if (!isset($GLOBALS['HERIKA_ROLEMASTER_LEGACY_CACHE']) || !is_array($GLOBALS['HERIKA_ROLEMASTER_LEGACY_CACHE'])) {
            $GLOBALS['HERIKA_ROLEMASTER_LEGACY_CACHE'] = [];
        }

        if (array_key_exists($npcName, $GLOBALS['HERIKA_ROLEMASTER_LEGACY_CACHE'])) {
            return $GLOBALS['HERIKA_ROLEMASTER_LEGACY_CACHE'][$npcName];
        }

        if (
            !isset($GLOBALS['db']) ||
            !is_object($GLOBALS['db']) ||
            !method_exists($GLOBALS['db'], 'escape') ||
            !method_exists($GLOBALS['db'], 'fetchOne')
        ) {
            $GLOBALS['HERIKA_ROLEMASTER_LEGACY_CACHE'][$npcName] = false;
            return false;
        }

        $namedKey = $GLOBALS['db']->escape($npcName . '_is_rolemastered');
        $row = $GLOBALS['db']->fetchOne("SELECT value FROM conf_opts WHERE id='{$namedKey}' LIMIT 1");

        if (is_array($row)) {
            $rawValue = $row['value'] ?? null;
        } else {
            $rawValue = $row;
        }

        $resolved = herikaRolemasterStateToBool($rawValue);
        $GLOBALS['HERIKA_ROLEMASTER_LEGACY_CACHE'][$npcName] = $resolved;

        return $resolved;
    }
}

if (!function_exists('herikaResolveNpcRolemasterState')) {
    function herikaResolveNpcRolemasterState($npcName = '', array $options = [])
    {
        $npcName = trim(strval($npcName));
        if ($npcName === '') {
            $npcName = trim(strval($GLOBALS['HERIKA_NAME'] ?? ''));
        }

        $useGlobal = !array_key_exists('use_global', $options) || !empty($options['use_global']);
        if ($useGlobal && !empty($GLOBALS['is_rolemastered'])) {
            return true;
        }

        $npcData = is_array($options['npc_data'] ?? null) ? $options['npc_data'] : null;
        if (is_array($npcData) && !empty($npcData['is_rolemastered'])) {
            return true;
        }

        $metadata = is_array($options['metadata'] ?? null) ? $options['metadata'] : null;
        $extended = is_array($options['extended'] ?? null) ? $options['extended'] : null;
        $loadLookup = !array_key_exists('load_lookup', $options) || !empty($options['load_lookup']);

        if (($metadata === null || $extended === null) && $loadLookup && $npcName !== '' && class_exists('NpcMaster')) {
            if (!isset($GLOBALS['HERIKA_ROLEMASTER_LOOKUP_CACHE']) || !is_array($GLOBALS['HERIKA_ROLEMASTER_LOOKUP_CACHE'])) {
                $GLOBALS['HERIKA_ROLEMASTER_LOOKUP_CACHE'] = [];
            }

            if (!array_key_exists($npcName, $GLOBALS['HERIKA_ROLEMASTER_LOOKUP_CACHE'])) {
                $lookup = [
                    'npc_data' => [],
                    'metadata' => [],
                    'extended' => [],
                ];

                $npcMaster = new NpcMaster();
                $lookupNpcData = $npcMaster->getByName($npcName);
                if (is_array($lookupNpcData) && count($lookupNpcData) > 0) {
                    $lookup['npc_data'] = $lookupNpcData;
                    $lookup['metadata'] = $npcMaster->getMetadata($lookupNpcData);
                    $lookup['extended'] = $npcMaster->getExtendedData($lookupNpcData);
                }

                $GLOBALS['HERIKA_ROLEMASTER_LOOKUP_CACHE'][$npcName] = $lookup;
            }

            $lookup = $GLOBALS['HERIKA_ROLEMASTER_LOOKUP_CACHE'][$npcName];
            if ($npcData === null && is_array($lookup['npc_data'])) {
                $npcData = $lookup['npc_data'];
            }
            if ($metadata === null && is_array($lookup['metadata'])) {
                $metadata = $lookup['metadata'];
            }
            if ($extended === null && is_array($lookup['extended'])) {
                $extended = $lookup['extended'];
            }
        }

        if (is_array($npcData) && !empty($npcData['is_rolemastered'])) {
            return true;
        }
        if (is_array($metadata) && !empty($metadata['is_rolemastered'])) {
            return true;
        }
        if (is_array($extended) && !empty($extended['is_rolemastered'])) {
            return true;
        }

        $useLegacy = !array_key_exists('use_legacy', $options) || !empty($options['use_legacy']);
        if ($useLegacy) {
            return herikaRolemasterStateGetLegacyFlag($npcName);
        }

        return false;
    }
}

if (!function_exists('chimRelationshipTimelineState')) {
    /**
     * Extract durable relationship state while excluding volatile evaluation timestamps.
     */
    function chimRelationshipTimelineState($extendedData)
    {
        if (is_string($extendedData) && trim($extendedData) !== '') {
            $extendedData = json_decode($extendedData, true);
        }
        if (!is_array($extendedData)) {
            return null;
        }

        $state = [];
        foreach (['relationships', 'relationships_analyzed', 'relationships_inferred', 'relationships_model'] as $key) {
            if (array_key_exists($key, $extendedData)) {
                $state[$key] = $extendedData[$key];
            }
        }
        return $state;
    }
}

if (!function_exists('chimRelationshipRestoreQuery')) {
    /**
     * Build the relationship-only restore query for production and disposable-schema tests.
     */
    function chimRelationshipRestoreQuery($timestamp, $schema = 'public')
    {
        if (!is_numeric($timestamp) || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', (string) $schema)) {
            throw new InvalidArgumentException('Invalid relationship restore query input.');
        }
        $timestamp = (float) $timestamp;

        return "WITH restore AS (
            SELECT DISTINCT ON (h.npc_id)
                h.npc_id,
                h.extended_data
            FROM {$schema}.core_npc_master_history h
            JOIN {$schema}.core_npc_master c ON c.id = h.npc_id
            WHERE c.npc_name <> 'The Narrator'
              AND (h.gamets_last_updated <= {$timestamp} OR h.gamets_last_updated IS NULL)
            ORDER BY
                h.npc_id,
                h.gamets_last_updated DESC NULLS LAST,
                h.created DESC,
                CASE
                    WHEN h.extended_data ->> '_chim_history_source' = 'relationship' THEN 2
                    WHEN h.extended_data ->> '_chim_history_source' = 'infosave' THEN 1
                    ELSE 0
                END DESC,
                h.history_id DESC
        ),
        updated AS (
            UPDATE {$schema}.core_npc_master c
            SET extended_data = (
                (
                    COALESCE(c.extended_data, '{}'::jsonb)
                    - 'relationships'
                    - 'relationships_analyzed'
                    - 'relationships_inferred'
                    - 'relationships_last_eval'
                    - 'relationships_model'
                    - 'relationships_updated'
                    - '_chim_history_source'
                )
                || jsonb_strip_nulls(jsonb_build_object(
                    'relationships', restore.extended_data -> 'relationships',
                    'relationships_analyzed', restore.extended_data -> 'relationships_analyzed',
                    'relationships_inferred', restore.extended_data -> 'relationships_inferred',
                    'relationships_last_eval', restore.extended_data -> 'relationships_last_eval',
                    'relationships_model', restore.extended_data -> 'relationships_model',
                    'relationships_updated', restore.extended_data -> 'relationships_updated'
                ))
            )
            FROM restore
            WHERE c.id = restore.npc_id
            RETURNING c.id
        )
        SELECT COUNT(*)::int AS affected FROM updated";
    }
}

if (!function_exists('chimRelationshipFutureClearQuery')) {
    function chimRelationshipFutureClearQuery($timestamp, $schema = 'public')
    {
        if (!is_numeric($timestamp) || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', (string) $schema)) {
            throw new InvalidArgumentException('Invalid relationship clear query input.');
        }
        $timestamp = (float) $timestamp;

        return "WITH cleared AS (
                UPDATE {$schema}.core_npc_master AS c
                SET extended_data = c.extended_data
                    - 'relationships'
                    - 'relationships_analyzed'
                    - 'relationships_inferred'
                    - 'relationships_last_eval'
                    - 'relationships_model'
                    - 'relationships_updated'
                    - '_chim_history_source'
                WHERE c.npc_name <> 'The Narrator'
                  AND (c.gamets_last_updated > {$timestamp} OR c.gamets_last_updated IS NULL)
                  AND c.extended_data IS NOT NULL
                  AND c.extended_data ? 'relationships'
                  AND NOT EXISTS (
                      SELECT 1
                      FROM {$schema}.core_npc_master_history h
                      WHERE h.npc_id = c.id
                        AND (h.gamets_last_updated <= {$timestamp} OR h.gamets_last_updated IS NULL)
                        AND h.extended_data IS NOT NULL
                        AND h.extended_data ? 'relationships'
                  )
                RETURNING c.npc_name
            ),
            sample AS (
                SELECT npc_name FROM cleared ORDER BY npc_name LIMIT 10
            )
            SELECT
                (SELECT COUNT(*)::int FROM cleared) AS affected,
                COALESCE((SELECT string_agg(npc_name, ', ') FROM sample), '') AS sample_names";
    }
}

if (!function_exists('chimRelationshipTimelineStamp')) {
    // Relationship writes live on the game timeline. Snapshot whenever the durable relationship
    // state differs from the history row that restoreNPC would consider for this game timestamp.
    // Identical state is deduplicated without a real-time throttle, so a reconnect cannot restore
    // a recent-but-stale snapshot. Loading an older save still excludes future relationship state.
    function chimRelationshipTimelineStamp($npcId)
    {
        try {
            $npcId = (int) $npcId;
            if ($npcId <= 0 || !isset($GLOBALS['db'])) {
                return false;
            }
            $g = 0;
            if (isset($GLOBALS['gameRequest'][2]) && is_numeric($GLOBALS['gameRequest'][2])) {
                $g = (float) $GLOBALS['gameRequest'][2];
            } elseif (function_exists('DataLastKnownGameTS')) {
                $g = (float) DataLastKnownGameTS();
            }
            if ($g > 0) {
                $stampResult = $GLOBALS['db']->execQuery(
                    "UPDATE core_npc_master SET gamets_last_updated = {$g} WHERE id = {$npcId}"
                );
                if ($stampResult === false) {
                    throw new RuntimeException('failed to stamp game timestamp');
                }
            }

            $current = $GLOBALS['db']->fetchOne(
                "SELECT extended_data, gamets_last_updated FROM core_npc_master WHERE id = {$npcId} LIMIT 1"
            );
            if (!$current) {
                throw new RuntimeException('NPC row not found after relationship write');
            }
            $currentState = chimRelationshipTimelineState($current['extended_data'] ?? null);
            if ($currentState === null) {
                throw new RuntimeException('live extended_data is not valid relationship JSON');
            }

            $eligibleClause = $g > 0
                ? "AND (gamets_last_updated <= {$g} OR gamets_last_updated IS NULL)"
                : '';
            $historyQuery = "SELECT extended_data, gamets_last_updated
                FROM core_npc_master_history
                WHERE npc_id = {$npcId}
                  {$eligibleClause}
                ORDER BY
                    gamets_last_updated DESC NULLS LAST,
                    created DESC,
                    CASE
                        WHEN extended_data ->> '_chim_history_source' = 'relationship' THEN 2
                        WHEN extended_data ->> '_chim_history_source' = 'infosave' THEN 1
                        ELSE 0
                    END DESC,
                    history_id DESC
                LIMIT 1";
            $history = $GLOBALS['db']->fetchOne($historyQuery);
            $historyState = $history
                ? chimRelationshipTimelineState($history['extended_data'] ?? null)
                : null;

            if ($history && $historyState !== null && $currentState == $historyState) {
                error_log("[REL] Timeline snapshot skipped for npc_id {$npcId} (relationship state unchanged)");
                return true;
            }

            $nm = new NpcMaster();
            $nm->backupNpcById($npcId, 'relationship');

            $verified = $GLOBALS['db']->fetchOne($historyQuery);
            $verifiedState = $verified
                ? chimRelationshipTimelineState($verified['extended_data'] ?? null)
                : null;
            if ($verifiedState === null || $currentState != $verifiedState) {
                throw new RuntimeException('relationship history snapshot verification failed');
            }

            error_log("[REL] Timeline snapshot for npc_id {$npcId} (relationship progress persisted to history)");
            return true;
        } catch (Throwable $e) {
            error_log("[REL] Timeline stamp failed for npc_id " . (int) $npcId . ": " . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('chimRunWithRelationshipExtendedDataWrite')) {
    function chimRunWithRelationshipExtendedDataWrite($callback)
    {
        $hadPrevious = array_key_exists('CHIM_ALLOW_RELATIONSHIP_EXTENDED_DATA_WRITE', $GLOBALS);
        $previous = $hadPrevious ? $GLOBALS['CHIM_ALLOW_RELATIONSHIP_EXTENDED_DATA_WRITE'] : null;
        $GLOBALS['CHIM_ALLOW_RELATIONSHIP_EXTENDED_DATA_WRITE'] = true;

        try {
            return $callback();
        } finally {
            if ($hadPrevious) {
                $GLOBALS['CHIM_ALLOW_RELATIONSHIP_EXTENDED_DATA_WRITE'] = $previous;
            } else {
                unset($GLOBALS['CHIM_ALLOW_RELATIONSHIP_EXTENDED_DATA_WRITE']);
            }
        }
    }
}

class NpcMaster
{
    private $table = "core_npc_master";
    private $db;

    public static function profileExists($npcName, $checkLegacyFile = false)
    {
        // Access global DB instance

        if (!isset($GLOBALS["db"])) { $GLOBALS["db"] = new sql(); }
        $db = $GLOBALS["db"];

        $escaped = $db->escape($npcName);
        $query   = "SELECT 1 FROM core_npc_master WHERE npc_name = '{$escaped}' LIMIT 1";
        $result  = $db->fetchOne($query);

        if ($result) {
            return true; // Found in database
        }

        return false;
    }

    public function __construct()
    {
        $this->db = $GLOBALS["db"];
    }

    // Create (Insert)
    public function create($data)
    {
        // Prevent creating The Narrator - it's now managed via core_narrator table
        if (isset($data["npc_name"]) && $data["npc_name"] === "The Narrator") {
            throw new \Exception("The Narrator cannot be created via NpcMaster. Use the Narrator class and Narrator Management UI instead.");
        }

        $data = $this->normalizeNpcDataForPersistence($data);
        
        $fields = [
            "npc_name",
            "npc_favorite",
            "lock_profile",
            "prompt_head",
            "npc_static_bio",
            "oghma_knowledge_tags",
            "emote_moods",
            "personality",
            "occupation",
            "appearance",
            "skills",
            "speechstyle",
            "goals",
            "voiceid",
            "metadata",
            "extended_data",
            "gender",
            "race",
            "refid",
            "profile_id",
            "dynamic_profile",
            "md5",
            "gamets_last_updated",
            "base",
            "core",
            "tags",
        ];

        foreach ($data as $k => $v) {
            // Preserve explicit 0/false values; only treat empty-string/null as unset.
            if ($v === '' || $v === null) {
                $data[$k] = null;
            }
        }
        $data["md5"] = md5($data["npc_name"]);
        $filtered    = array_intersect_key($data, array_flip($fields));
        return $this->db->insert($this->table, $filtered);
    }

    // Read NPC by ID
    public function getById($id)
    {
        $id    = (int) $id;
        $query = "SELECT * FROM {$this->table} WHERE id = $id LIMIT 1";
        return $this->db->fetchOne($query);
    }

    // Read NPC by unique name
    public function getByName($npcName)
    {
        // The Narrator is now managed via core_narrator table, not core_npc_master
        if ($npcName === "The Narrator") {
            return null;
        }

        $escaped = $this->escape($npcName);
        $query   = "SELECT * FROM {$this->table} WHERE npc_name = '{$escaped}' LIMIT 1";
        return $this->db->fetchOne($query);
    }

    // Read NPC by md5
    public function getByMD5($md5Hash)
    {
        // The Narrator is now managed via core_narrator table, not core_npc_master
        // Check if this MD5 corresponds to The Narrator
        if ($md5Hash === md5('The Narrator')) {
            return null;
        }

        $escaped = $this->escape($md5Hash);
        $query   = "SELECT * FROM {$this->table} WHERE md5 = '{$escaped}' LIMIT 1";
        return $this->db->fetchOne($query);
    }

    // Read NPC by md5
    public function getByRefId($npcName)
    {
        $escaped = $this->escape($npcName);
        $query   = "SELECT * FROM {$this->table} WHERE refid = '{$escaped}' order by 	gamets_last_updated	desc nulls last LIMIT 1";
        return $this->db->fetchOne($query);
    }

    // Read all NPCs (optional WHERE)
    public function getAll($where = "TRUE")
    {
        $query = "SELECT * FROM {$this->table} WHERE $where";
        return $this->db->fetchAll($query);
    }

    // Update NPC by ID
    public function update($id, $data)
    {
        $data = $this->normalizeNpcDataForPersistence($data);

        $fields = [
            "npc_name",
            "npc_favorite",
            "lock_profile",
            "prompt_head",
            "npc_static_bio",
            "oghma_knowledge_tags",
            "emote_moods",
            "personality",
            "occupation",
            "appearance",
            "skills",
            "speechstyle",
            "goals",
            "voiceid",
            "metadata",
            "extended_data",
            "gender",
            "race",
            "refid",
            "profile_id",
            "dynamic_profile",
            "md5",
            "gamets_last_updated",
            "base",
            "core",
            "tags",
        ];

        $id    = (int) $id;
        $where = "id = $id";

        // Prevent renaming The Narrator
        $existing = $this->getById($id);
        if ($existing && isset($existing['npc_name']) && $existing['npc_name'] === 'The Narrator') {
            if (isset($data['npc_name']) && $data['npc_name'] !== $existing['npc_name']) {
                unset($data['npc_name']);
            }
        }

        $data = $this->preserveRelationshipExtendedDataOnGenericUpdate($data, $existing);

        foreach ($data as $k => $v) {
            // Preserve explicit 0/false values; only treat empty-string/null as unset.
            if ($v === '' || $v === null) {
                $data[$k] = null;
            }
        }

        $id       = intval($id);
        $where    = "id = {$id}";
        $filtered = array_intersect_key($data, array_flip($fields));
        return $GLOBALS["db"]->updateRow($this->table, $filtered, $where);

    }

    // Update NPC using an array (id key required)
    public function updateByArray($data)
    {
        if (! isset($data['id'])) {
            return false;
        }

        $id = (int) $data['id'];
        unset($data['id']); // Remove 'id' from the data array to avoid updating it

        return $this->update($id, $data);
    }

    private function preserveRelationshipExtendedDataOnGenericUpdate($data, $existing)
    {
        if (!is_array($data) || !array_key_exists('extended_data', $data)) {
            return $data;
        }

        if (!empty($GLOBALS['CHIM_ALLOW_RELATIONSHIP_EXTENDED_DATA_WRITE'])) {
            return $data;
        }

        if (!is_array($existing) || !array_key_exists('extended_data', $existing)) {
            return $data;
        }

        $incoming = $this->decodeExtendedDataForRelationshipGuard($data['extended_data']);
        $current = $this->decodeExtendedDataForRelationshipGuard($existing['extended_data'] ?? null);
        if (!is_array($incoming) || !is_array($current)) {
            return $data;
        }

        $relationshipKeys = [
            'relationships',
            'relationships_analyzed',
            'relationships_inferred',
            'relationships_last_eval',
            'relationships_model',
            'relationships_updated',
        ];

        $changed = false;
        foreach ($relationshipKeys as $key) {
            if (array_key_exists($key, $current)) {
                $incoming[$key] = $current[$key];
                $changed = true;
            } elseif (array_key_exists($key, $incoming)) {
                unset($incoming[$key]);
                $changed = true;
            }
        }

        if ($changed) {
            $data['extended_data'] = json_encode($incoming, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        return $data;
    }

    private function decodeExtendedDataForRelationshipGuard($value)
    {
        if (is_array($value)) {
            return $value;
        }

        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : null;
    }

    // Delete NPC by ID
    public function delete($id)
    {
        $id    = (int) $id;
        $where = "id = $id";
        // Disallow deleting The Narrator profile (by id or name)
        $row = $this->getById($id);
        if ($row && (intval($row['id']) === 1 || ($row['npc_name'] ?? '') === 'The Narrator')) {
            return false;
        }
        return $this->db->delete($this->table, $where);
    }

    // Truncate table (dangerous!)
    public function truncate($restart = false, $cascade = false)
    {
        return $this->db->truncate($this->table, $restart, $cascade);
    }

    // Upsert using ON CONFLICT
    public function upsert($data, $conflictTarget)
    {
        return $this->db->upsertRowOnConflict($this->table, $data, $conflictTarget);
    }

    // Escape strings for raw queries
    public function escape($str)
    {
        return $this->db->escape($str);
    }

    // Convert NPC name to codename
    public function npcNameToCodename($npcName)
    {
        $codename = mb_convert_encoding($npcName, 'UTF-8', mb_detect_encoding($npcName));
        // Use multibyte lowercase so accented capitals (e.g., É) convert correctly
        $codename = mb_strtolower(trim($codename), 'UTF-8');
        $codename = strtr($codename, [" " => "_", "'" => "+"]);
        // Allow unicode letters/digits plus underscore, plus and hyphen
        $codename = preg_replace('/[^\p{L}\p{N}_+-]/u', '', $codename);
        return $codename;
    }

    public function normalizeNpcDataForPersistence($data)
    {
        if (!is_array($data)) {
            return $data;
        }

        $aliasMap = [
            'npc_misc' => 'oghma_knowledge_tags',
            'npc_background' => 'npc_static_bio',
            'npc_personality' => 'personality',
            'npc_appearance' => 'appearance',
            'npc_occupation' => 'occupation',
            'npc_skills' => 'skills',
            'npc_speechstyle' => 'speechstyle',
            'npc_goals' => 'goals',
        ];

        foreach ($aliasMap as $legacyKey => $canonicalKey) {
            if (
                array_key_exists($legacyKey, $data)
                && (
                    !array_key_exists($canonicalKey, $data)
                    || $data[$canonicalKey] === null
                    || $data[$canonicalKey] === ''
                )
            ) {
                $data[$canonicalKey] = $data[$legacyKey];
            }
            unset($data[$legacyKey]);
        }

        $relationshipSeed = null;
        foreach (['relationships', 'npc_relationships'] as $relationshipKey) {
            if (!array_key_exists($relationshipKey, $data)) {
                continue;
            }

            $relationshipSeed = $this->decodeRelationshipSeed($data[$relationshipKey]);
            unset($data[$relationshipKey]);

            if (is_array($relationshipSeed)) {
                break;
            }
        }

        if (is_array($relationshipSeed)) {
            $extendedData = $this->decodeJsonObject($data['extended_data'] ?? null);
            $extendedData['relationships'] = $relationshipSeed;
            $data['extended_data'] = json_encode($extendedData, JSON_UNESCAPED_UNICODE);
        } elseif (isset($data['extended_data']) && is_array($data['extended_data'])) {
            $data['extended_data'] = json_encode($data['extended_data'], JSON_UNESCAPED_UNICODE);
        }

        if (isset($data['metadata']) && is_array($data['metadata'])) {
            $data['metadata'] = json_encode($data['metadata'], JSON_UNESCAPED_UNICODE);
        }

        return $data;
    }

    private function decodeJsonObject($value)
    {
        if (is_array($value)) {
            return $value;
        }

        if (!is_string($value)) {
            return [];
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return [];
        }

        $decoded = json_decode($trimmed, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function decodeRelationshipSeed($value)
    {
        if (is_array($value)) {
            return $value;
        }

        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        if ($trimmed[0] !== '{') {
            return null;
        }

        $decoded = json_decode($trimmed, true);
        return is_array($decoded) ? $decoded : null;
    }

    public function createProfile($npcname, $FORCE_PARMS = [], $overwrite = false, $baseprofile = '')
    {
        if ($npcname === "The Narrator") {
            return; // refuse narrator
        }

        $codename        = $this->npcNameToCodename($npcname);
        $baseprofileName = $this->npcNameToCodename($baseprofile);

        // Check if NPC already exists in DB
        $existing = $this->getByName($npcname);

        if ($existing && ! $overwrite) {
            // Profile exists, and no overwrite requested
            // BUT still update race/gender/refid if they're empty and FORCE_PARMS has them
            $needsUpdate = false;
            $updateFields = [];

            if (empty($existing['race']) && !empty($FORCE_PARMS['race'])) {
                $updateFields['race'] = $FORCE_PARMS['race'];
                $needsUpdate = true;
            }
            if (empty($existing['gender']) && !empty($FORCE_PARMS['gender'])) {
                $updateFields['gender'] = $FORCE_PARMS['gender'];
                $needsUpdate = true;
            }
            if (empty($existing['refid']) && !empty($FORCE_PARMS['refid'])) {
                $updateFields['refid'] = $FORCE_PARMS['refid'];
                $needsUpdate = true;
            }

            if ($needsUpdate) {
                $this->update($existing['id'], $updateFields);
                Logger::info("NPC '{$npcname}' updated with game data (race/gender/refid).");
            }
            return;
        }

        // Fetch NPC templates
        $templateRow = $this->fetchTemplateRow($codename);

        // Override with FORCE_PARMS
        foreach ($FORCE_PARMS as $key => $value) {
            $templateRow[$key] = $value;
        }

        // Compose knowledge string (OGHMA_KNOWLEDGE)
        $templateRow['oghma_knowledge_tags'] = $this->composeKnowledgeString($templateRow['oghma_knowledge_tags'] ?? '', $codename);

        // Fetch voice IDs
        $voiceData = $this->fetchVoiceData($codename);

        // Compose the row data to insert/update
        $rowData = array_merge($templateRow, $voiceData, [
            'npc_name'     => $npcname,
            'npc_codename' => $codename,
        ]);

        // Insert or update into DB
        if ($existing) {
            $this->update($existing['id'], $rowData);
        } else {
            $this->create($rowData);
        }

        // Log success
        Logger::info("NPC profile created/updated for '{$npcname}' in DB.");
    }

    private function fetchTemplateRow($codename)
    {
        $lang    = $GLOBALS["CORE_LANG"] ?? '';
        $escCode = $this->db->escape($codename);
        if ($lang) {
            $escLang = $this->db->escape($lang);
            // If translations exist, we only pull legacy npc_pers; otherwise fallback to bio view
            $templateRow = $this->db->fetchOne("SELECT npc_pers FROM npc_templates_trl WHERE lower(name_trl) = lower('{$escCode}') AND lang = '{$escLang}'");
        }

        if (! $templateRow) {
            $templateRow = $this->db->fetchOne(
                "SELECT core, oghma_knowledge_tags, npc_static_bio, personality, appearance, relationships, occupation, skills, speechstyle, goals FROM combined_bio_templates WHERE lower(npc_name) = lower('{$escCode}')"
            );
        }

        return $templateRow ?: [
            'core' => 'Roleplay as ' . $codename,
            'oghma_knowledge_tags' => $codename,
            'npc_static_bio' => '',
            'personality' => '',
            'appearance' => '',
            'relationships' => '',
            'occupation' => '',
            'skills' => '',
            'speechstyle' => '',
            'goals' => '',
        ];
    }

    private function composeKnowledgeString($misc, $codename)
    {
        $miscParts = array_unique(array_filter(
            array_map('trim', preg_split('/\s*[,|;]\s*/', (string) $misc) ?: []),
            static fn($tag): bool => $tag !== '' && ! in_array(strtolower($tag), ['common', 'esoteric'], true)
        ));
        if (! in_array($codename, $miscParts)) {
            $miscParts[] = $codename;
        }
        return implode(', ', $miscParts);
    }

    private function fetchVoiceData($codename)
    {
        $escCode         = $this->db->escape($codename);
        $voiceRow        = $this->db->fetchOne("SELECT voiceid FROM combined_bio_templates WHERE lower(npc_name) = lower('{$escCode}')");
        $voicetypeString = $this->fetchVoicetype($codename);

        return array_merge($voiceRow ?: [], ['voicetype' => $voicetypeString]);
    }

    private function fetchVoicetype($codename)
    {
        $cn        = $this->db->escape("Voicetype/$codename");
        $vtypeRows = $this->db->fetchAll("SELECT value FROM conf_opts WHERE lower(id) = lower('$cn')");
        return $vtypeRows[0]['value'] ?? '';
    }

    public function migrateFromOldProfile($currentNpcData, $OLD_GLOBALS_ARRAY)
    {

        $currentNpcData['npc_favorite']    = 0; // Default
        $currentNpcData['lock_profile']    = isset($OLD_GLOBALS_ARRAY['LOCK_PROFILE']) ? ($OLD_GLOBALS_ARRAY['LOCK_PROFILE'] ? 1 : 0) : 0;
        $currentNpcData['dynamic_profile'] = isset($OLD_GLOBALS_ARRAY['DYNAMIC_PROFILE']) ? ($OLD_GLOBALS_ARRAY['DYNAMIC_PROFILE'] ? 1 : 0) : 0;
        if (isset($OLD_GLOBALS_ARRAY['HERIKA_PERS'])) {
            $currentNpcData['core'] = $OLD_GLOBALS_ARRAY['HERIKA_PERS'];
        }

        if (isset($OLD_GLOBALS_ARRAY['PROMPT_HEAD'])) {
            $currentNpcData['prompt_head'] = $OLD_GLOBALS_ARRAY['PROMPT_HEAD'];
        }

        if (isset($OLD_GLOBALS_ARRAY['HERIKA_BACKGROUND'])) {
            $currentNpcData['npc_static_bio'] .= $OLD_GLOBALS_ARRAY['HERIKA_BACKGROUND'];
        }

        if (isset($OLD_GLOBALS_ARRAY['OGHMA_KNOWLEDGE'])) {
            $currentNpcData['oghma_knowledge_tags'] = $OLD_GLOBALS_ARRAY['OGHMA_KNOWLEDGE'];
        }

        if (isset($OLD_GLOBALS_ARRAY['HERIKA_PERSONALITY'])) {
            $currentNpcData['personality'] = $OLD_GLOBALS_ARRAY['HERIKA_PERSONALITY'];
        }

        if (isset($OLD_GLOBALS_ARRAY['HERIKA_OCCUPATION'])) {
            $currentNpcData['occupation'] = $OLD_GLOBALS_ARRAY['HERIKA_OCCUPATION'];
        }

        if (isset($OLD_GLOBALS_ARRAY['HERIKA_APPEARANCE'])) {
            $currentNpcData['appearance'] = $OLD_GLOBALS_ARRAY['HERIKA_APPEARANCE'];
        }

        if (isset($OLD_GLOBALS_ARRAY['HERIKA_SKILLS'])) {
            $currentNpcData['skills'] = $OLD_GLOBALS_ARRAY['HERIKA_SKILLS'];
        }

        if (isset($OLD_GLOBALS_ARRAY['HERIKA_SPEECHSTYLE'])) {
            $currentNpcData['speechstyle'] = $OLD_GLOBALS_ARRAY['HERIKA_SPEECHSTYLE'];
        }

        if (isset($OLD_GLOBALS_ARRAY['EMOTEMOODS'])) {
            $currentNpcData['emote_moods'] = $OLD_GLOBALS_ARRAY['EMOTEMOODS'];
        }

        if (isset($OLD_GLOBALS_ARRAY['HERIKA_GOALS'])) {
            $currentNpcData['goals'] = $OLD_GLOBALS_ARRAY['HERIKA_GOALS'];
        }

        if (isset($OLD_GLOBALS_ARRAY['TTS']['XTTSFASTAPI']['voiceid'])) {
            $currentNpcData['voiceid'] = $OLD_GLOBALS_ARRAY['TTS']['XTTSFASTAPI']['voiceid'];
        }

        $overrides=[];
        /*
        foreach ($OLD_GLOBALS_ARRAY as $k=>$v) {
            if (!is_array($v)) {
                if (in_array($k,[
                 "DIARY_COOLDOWN", "COMBAT_BARK_COOLDOWN", "AUTO_DIARY", "AUTO_DIARY_WAIT", "MINIME_T5",
                 "OGHMA_INFINIUM", "OGHMA_AMOUNT", "RECHAT_H", "RECHAT_P", "RECHAT_ALLOW_ACTIONS", "BORED_EVENT",
                 "CONTEXT_HISTORY", "CONTEXT_HISTORY_DIARY", "CONTEXT_HISTORY_DYNAMIC_PROFILE",
                 "ALIVE_MESSAGE", "TIME_AWARENESS", "QUEST_COMMENT", "QUEST_COMMENT_CHANCE", "CURRENT_TASK",
                 "CORE_LANG", "LANG_LLM_XTTS", "MAX_WORDS_LIMIT",
                 "REMOVE_ASTERISKS_FROM_OUTPUT", "REMOVE_ASTERISKS_FROM_PLAYER_INPUT", "REMOVE_ASTERISKS_FROM_NPC_OUTPUT",
                 "INLINE_NARRATION_ENABLED", "INLINE_NARRATION_MODE", "REMOVE_PLAYER_AUTOCHAT_ASTERISKS", "PLAYER_AUTOCHAT_ASTERISKS_ENABLED",
                 "ENFORCE_ACTIONS_PROMPT", "DIARY_PROMPT"]))
                    if (!empty($v))
                        $overrides[$k]=$v;
            }
        }
        */
        if (empty($currentNpcData['personality']) && isset($OLD_GLOBALS_ARRAY['HERIKA_PERSONALITY'])) {
            $currentNpcData['personality'] = $OLD_GLOBALS_ARRAY['HERIKA_PERSONALITY'];
        }
        $currentNpcData['metadata']      = json_encode($overrides);
        $currentNpcData['extended_data'] = json_encode(["chim_core_migrated" => 1]);
        $currentNpcData['gender']        = null; // Optional: you might use HERIKA_PERSONALITY/gender logic
        $currentNpcData['race']          = null; // Optional: no race found
        $currentNpcData['refid']         = null; // Optional: no race found
        $currentNpcData['base']          = null; // Optional: no race found
                                                 // Prefer HERIKA_PERS for core; fallback to HERIKA_NAME if core not set
        if (! isset($currentNpcData['core']) || $currentNpcData['core'] === '') {
            if (isset($OLD_GLOBALS_ARRAY['HERIKA_PERS']) && $OLD_GLOBALS_ARRAY['HERIKA_PERS'] !== '') {
                $currentNpcData['core'] = $OLD_GLOBALS_ARRAY['HERIKA_PERS'];
            } else if (isset($OLD_GLOBALS_ARRAY['HERIKA_NAME']) && $OLD_GLOBALS_ARRAY['HERIKA_NAME'] !== '') {
                $currentNpcData['core'] = $OLD_GLOBALS_ARRAY['HERIKA_NAME'];
            }
        }
        $defaultProfileId = 1;
        try {
            if (!class_exists('CoreProfile')) {
                require_once __DIR__ . DIRECTORY_SEPARATOR . 'core_profiles.class.php';
            }
            $coreProfile = new CoreProfile();
            $defaultProfile = $coreProfile->getDefaultNpc();
            if (is_array($defaultProfile) && !empty($defaultProfile['id'])) {
                $defaultProfileId = (int)$defaultProfile['id'];
            }
        } catch (Throwable $e) {
            error_log("[NPCMASTER] Could not resolve default NPC profile, falling back to profile #1: " . $e->getMessage());
        }

        $currentNpcData['profile_id'] = $defaultProfileId;
        $currentNpcData['md5']        = md5($currentNpcData["npc_name"]); // Default profile

        return $currentNpcData;

    }

    public function setOldGlobalsFromCurrentNpcData($currentNpcData)
    {

        if (isset($currentNpcData['npc_name'])) {
            $GLOBALS['HERIKA_NAME'] = $currentNpcData['npc_name'];
        }

        if (isset($currentNpcData['lock_profile'])) {
            $GLOBALS['LOCK_PROFILE'] = $currentNpcData['lock_profile'] ? true : false;
        }

        if (isset($currentNpcData['dynamic_profile'])) {
            $GLOBALS['DYNAMIC_PROFILE'] = $currentNpcData['dynamic_profile'] ? true : false;
        }

        if (isset($currentNpcData['prompt_head'])) {
            $GLOBALS['PROMPT_HEAD'] = $currentNpcData['prompt_head'];
        }

        // ALWAYS reset identity/bio globals from THIS NPC's own row (coalesce NULL -> ''), never leave the
        // previous NPC's value in place. A generic/bio-less NPC (e.g. an unnamed "Breton") has NULL profile
        // fields; the old `if (isset())` guards skipped the assignment on NULL, so the global retained the
        // last-processed NPC's data and the bio-less NPC spoke as them (the cross-NPC identity bleed
        // bleed). Mirrors the per-NPC reset already done for HERIKA_RELATIONSHIPS below.
        $GLOBALS['HERIKA_BACKGROUND']  = $currentNpcData['npc_static_bio'] ?? '';
        $GLOBALS['OGHMA_KNOWLEDGE']    = $currentNpcData['oghma_knowledge_tags'] ?? '';
        $GLOBALS['HERIKA_PERSONALITY'] = $currentNpcData['personality'] ?? '';

        unset($GLOBALS['HERIKA_RELATIONSHIPS']);

        $GLOBALS['HERIKA_OCCUPATION']  = $currentNpcData['occupation'] ?? '';
        $GLOBALS['HERIKA_APPEARANCE']  = $currentNpcData['appearance'] ?? '';
        $GLOBALS['HERIKA_SKILLS']      = $currentNpcData['skills'] ?? '';
        $GLOBALS['HERIKA_SPEECHSTYLE'] = $currentNpcData['speechstyle'] ?? '';

        if (isset($currentNpcData['emote_moods']) && ! empty(trim($currentNpcData['emote_moods']))) {
            $GLOBALS['EMOTEMOODS'] = $currentNpcData['emote_moods'];
        }

        $GLOBALS['HERIKA_GOALS']       = $currentNpcData['goals'] ?? '';

        if (isset($currentNpcData['core'])) {
            $GLOBALS['HERIKA_PERS'] = "Roleplay as {$GLOBALS['HERIKA_NAME']}.\n{$currentNpcData['core']}";
        } else {
            $GLOBALS['HERIKA_PERS'] = "Roleplay as {$GLOBALS['HERIKA_NAME']}";
        }

        $voiceResolution = $this->resolveNpcTtsVoice($currentNpcData);
        $resolvedVoice = $voiceResolution['resolved_voice'];
        $originalVoice = $voiceResolution['original_voice'];
        $fallbackVoice = $voiceResolution['fallback_voice'];
        $fallbackVoices = $voiceResolution['fallback_voices'] ?? ($fallbackVoice !== '' ? [$fallbackVoice] : []);

        if ($resolvedVoice !== '') {
            $GLOBALS['PATCH_OVERRIDE_VOICE'] = $resolvedVoice;
            $this->applyNpcVoiceToTtsGlobals($resolvedVoice);
        } else {
            unset($GLOBALS['PATCH_OVERRIDE_VOICE']);
        }

        if ($originalVoice !== '') {
            $GLOBALS['TTS_NPC_ORIGINAL_VOICE'] = $originalVoice;
        } else {
            unset($GLOBALS['TTS_NPC_ORIGINAL_VOICE']);
        }

        if ($fallbackVoice !== '') {
            $GLOBALS['TTS_NPC_FALLBACK_VOICE'] = $fallbackVoice;
        } else {
            unset($GLOBALS['TTS_NPC_FALLBACK_VOICE']);
        }

        if (!empty($fallbackVoices)) {
            $GLOBALS['TTS_NPC_FALLBACK_VOICES'] = $fallbackVoices;
        } else {
            unset($GLOBALS['TTS_NPC_FALLBACK_VOICES']);
        }

        if ($resolvedVoice !== '') {
            $GLOBALS['TTS_NPC_RESOLVED_VOICE'] = $resolvedVoice;
        } else {
            unset($GLOBALS['TTS_NPC_RESOLVED_VOICE']);
        }

        // Decode metadata and extended_data if available
        $metadata = json_decode($currentNpcData['metadata'] ?? '{}', true);
        $narratorManagedKeys = [
            'REMOVE_ASTERISKS_FROM_OUTPUT',
            'REMOVE_ASTERISKS_FROM_PLAYER_INPUT',
            'REMOVE_ASTERISKS_FROM_NPC_OUTPUT',
            'INLINE_NARRATION_ENABLED',
            'INLINE_NARRATION_MODE',
            'REMOVE_PLAYER_AUTOCHAT_ASTERISKS',
            'PLAYER_AUTOCHAT_ASTERISKS_ENABLED',
            'PRESERVE_ASTERISKS_IN_CONTEXT'
        ];
        if (is_array($metadata)) {
            foreach ($metadata as $key => $value) {
                if (in_array(strtoupper((string)$key), $narratorManagedKeys, true)) {
                    continue;
                }
                // Handle boolean false and numeric 0 properly - empty() would skip these
                if (! empty($value) || is_numeric($value) || is_bool($value)) {
                    chimApplyOverrideValueToGlobals(strval($key), $value);
                    //error_log("[CORE] NPC  GLOBALS[$key] = ".print_r($value,true));
                }

            }
        }

        // Apply extended_data overrides (highest precedence - NPC level)
        // Reserved keys are excluded (system fields managed by dedicated subsystems/toggles)
        $reservedKeys = ['middle_term_memory', 'middle_term_enabled', 'individual_memory_enabled', 'background_life_goals', 'chim_core_migrated'];
        $extendedData = json_decode($currentNpcData['extended_data'] ?? '{}', true);
        if (is_array($extendedData)) {
            foreach ($extendedData as $key => $value) {
                // Skip reserved system keys
                if (in_array($key, $reservedKeys, true)) {
                    continue;
                }
                if (in_array(strtoupper((string)$key), $narratorManagedKeys, true)) {
                    continue;
                }
                if (! empty($value) || is_numeric($value) || is_bool($value)) {
                    chimApplyOverrideValueToGlobals(strval($key), $value);
                }
            }
        }

        $GLOBALS['ENFORCE_ACTIONS_PROMPT'] = false;

    }

    public function getAllFk($field)
    {
        // Map foreign key fields to their respective tables
        $fkMap = [
            "profile_id" => "core_profiles",
        ];

        if (! array_key_exists($field, $fkMap)) {
            return []; // Unknown field
        }

        $table = $fkMap[$field];
        $query = "SELECT id, label FROM {$table} ORDER BY id ASC";
        return $GLOBALS["db"]->fetchAll($query);
    }

    public function getExtendedData($currentNpcData): array
    {
        return json_decode($currentNpcData['extended_data'] ?? '{}', true) ?: [];
    }

    public function appendBackgroundLifeGoals(string $biography, $currentNpcData): string
    {
        $extendedData = $this->getExtendedData($currentNpcData);
        $backgroundLifeGoals = trim((string)($extendedData['background_life_goals'] ?? ''));
        if ($backgroundLifeGoals === '') {
            return $biography;
        }

        return $biography . "\n\n<background_life_goals>\n{$backgroundLifeGoals}\n</background_life_goals>";
    }

    public function setExtendedData($currentNpcData, array $data)
    {

        $currentNpcData['extended_data'] = json_encode($data);
        return $currentNpcData;
    }

    public function getMetadata($currentNpcData): array
    {
        return json_decode($currentNpcData['metadata'] ?? '{}', true) ?: [];
    }

    public function setMetadata($currentNpcData, array $data)
    {

        $currentNpcData['metadata'] = json_encode($data);
        return $currentNpcData;
    }

    public function updateMetadataKeysByName(string $npcName, array $setValues = [], array $unsetKeys = []): bool
    {
        $npcName = trim($npcName);
        if ($npcName === '') {
            return false;
        }

        $normalizedSetValues = [];
        foreach ($setValues as $key => $value) {
            $metadataKey = trim((string) $key);
            if ($metadataKey === '') {
                continue;
            }

            if ($value === null) {
                $unsetKeys[] = $metadataKey;
                continue;
            }

            $normalizedSetValues[$metadataKey] = $value;
        }

        $normalizedUnsetKeys = [];
        foreach ($unsetKeys as $key) {
            $metadataKey = trim((string) $key);
            if ($metadataKey === '') {
                continue;
            }
            $normalizedUnsetKeys[$metadataKey] = true;
        }

        if (count($normalizedSetValues) === 0 && count($normalizedUnsetKeys) === 0) {
            return false;
        }

        $metadataExpr = "COALESCE(metadata, '{}'::jsonb)";

        foreach (array_keys($normalizedUnsetKeys) as $metadataKey) {
            $escapedKey = $this->db->escape($metadataKey);
            $metadataExpr = "({$metadataExpr} - '{$escapedKey}')";
        }

        foreach ($normalizedSetValues as $metadataKey => $value) {
            $escapedKey = $this->db->escape($metadataKey);
            $encodedValue = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($encodedValue === false) {
                continue;
            }

            $escapedValue = $this->db->escape($encodedValue);
            $metadataExpr = "jsonb_set({$metadataExpr}, '{\"{$escapedKey}\"}', '{$escapedValue}'::jsonb, true)";
        }

        $escapedNpcName = $this->db->escape($npcName);
        $query = "
            UPDATE {$this->table}
            SET metadata = {$metadataExpr}
            WHERE npc_name = '{$escapedNpcName}'
        ";

        return $this->db->execQuery($query) !== false;
    }


    public function updateExtendedKeysByName(string $npcName, array $setValues = [], array $unsetKeys = []): bool
    {
        $npcName = trim($npcName);
        if ($npcName === '') {
            return false;
        }

        $normalizedSetValues = [];
        foreach ($setValues as $key => $value) {
            $metadataKey = trim((string) $key);
            if ($metadataKey === '') {
                continue;
            }

            if ($value === null) {
                $unsetKeys[] = $metadataKey;
                continue;
            }

            $normalizedSetValues[$metadataKey] = $value;
        }

        $normalizedUnsetKeys = [];
        foreach ($unsetKeys as $key) {
            $metadataKey = trim((string) $key);
            if ($metadataKey === '') {
                continue;
            }
            $normalizedUnsetKeys[$metadataKey] = true;
        }

        if (count($normalizedSetValues) === 0 && count($normalizedUnsetKeys) === 0) {
            return false;
        }

        $metadataExpr = "COALESCE(extended_data, '{}'::jsonb)";

        foreach (array_keys($normalizedUnsetKeys) as $metadataKey) {
            $escapedKey = $this->db->escape($metadataKey);
            $metadataExpr = "({$metadataExpr} - '{$escapedKey}')";
        }

        foreach ($normalizedSetValues as $metadataKey => $value) {
            $escapedKey = $this->db->escape($metadataKey);
            $encodedValue = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($encodedValue === false) {
                continue;
            }

            $escapedValue = $this->db->escape($encodedValue);
            $metadataExpr = "jsonb_set({$metadataExpr}, '{\"{$escapedKey}\"}', '{$escapedValue}'::jsonb, true)";
        }

        $escapedNpcName = $this->db->escape($npcName);
        $query = "
            UPDATE {$this->table}
            SET extended_data = {$metadataExpr}
            WHERE npc_name = '{$escapedNpcName}'
        ";

        return $this->db->execQuery($query) !== false;
    }

    public function backupNpcById($id, $source = 'manual')
    {
        $id = (int) $id;
        $source = in_array($source, ['manual', 'relationship'], true) ? $source : 'manual';

        // Retrieve the current NPC
        $npc = $this->getById($id);
        if (! $npc) {
            return false; // NPC not found
        }
        //error_log("[NPC BACKUP] Backup of {$npc["npc_name"]} ".print_r($npc,true));
        // Remove the original 'id' field, since the history table likely has its own auto-increment ID
        unset($npc['id']);

        // Add a reference to the original NPC ID
        $npc['npc_id'] = $id;

        // Add the current timestamp for tracking purposes (optional)
        $npc['created'] = date('Y-m-d H:i:s');

        $npc['extended_data'] = $this->markHistoryExtendedData(
            $npc['extended_data'] ?? null,
            $source
        );

        // Insert the data into the history table
        return $this->db->insert('core_npc_master_history', $npc);
    }

    public function backupAllNpcs($timestamp)
    {
        // Validate the timestamp (ensure it's a float or numeric format, as per your schema)
        if (! is_numeric($timestamp)) {
            throw new InvalidArgumentException("Invalid timestamp value.");
        }

        date_default_timezone_set('UTC');

        $startTime = time();
        $updateQuery = "UPDATE {$this->table} SET gamets_last_updated = $timestamp";
        $GLOBALS["db"]->execQuery($updateQuery);

        // Insert all NPCs into history table in a single query
        $createdTimestamp = date('Y-m-d H:i:s');
        $insertQuery = "
            INSERT INTO core_npc_master_history (
                npc_id, npc_name, npc_favorite, lock_profile, prompt_head, npc_static_bio,
                oghma_knowledge_tags, emote_moods, personality, relationships,
                occupation, skills, speechstyle, goals, voiceid, metadata,
                gender, race, refid, profile_id, dynamic_profile, extended_data,
                md5, gamets_last_updated, core, base, tags, appearance, created
            )
            SELECT
                id, npc_name, npc_favorite, lock_profile, prompt_head, npc_static_bio,
                oghma_knowledge_tags, emote_moods, personality, relationships,
                occupation, skills, speechstyle, goals, voiceid, metadata,
                gender, race, refid, profile_id, dynamic_profile,
                COALESCE(extended_data, '{}'::jsonb) || jsonb_build_object('_chim_history_source', 'infosave'),
                md5, $timestamp, core, base, tags, appearance, '{$createdTimestamp}'
            FROM core_npc_master
        ";
        $GLOBALS["db"]->execQuery($insertQuery);
        error_log("[NPC BACKUP] " . date('Y-m-d H:i:s') . ", NPCs backup made in " . (time() - $startTime) . " secs ");
        return true;
    }

    public function restoreNPC($timestamp)
    {
        // Validate the timestamp (ensure it's a float or numeric format, as per your schema)
        if (! is_numeric($timestamp)) {
            throw new InvalidArgumentException("Invalid timestamp value.");
        }
        $neverClearRelationshipData = filter_var(
            $GLOBALS['NEVER_CLEAR_RELATIONSHIP_DATA'] ?? false,
            FILTER_VALIDATE_BOOLEAN
        );
        $preserveRelationshipDataSql = $neverClearRelationshipData ? 'TRUE' : 'FALSE';
        $startTime = time();
        $query     =
            "WITH deleted AS (
    DELETE FROM core_npc_master AS c
    WHERE c.npc_name<>'The Narrator' and COALESCE(c.lock_profile,0)=0
    and COALESCE(c.gamets_last_updated,0)>0
    and (
        NOT {$preserveRelationshipDataSql}
        OR EXISTS (
            SELECT 1
            FROM core_npc_master_history eligible_history
            WHERE eligible_history.npc_id = c.id
              AND (eligible_history.gamets_last_updated <= $timestamp OR eligible_history.gamets_last_updated IS NULL)
        )
    )
    RETURNING c.id, c.extended_data AS current_extended_data
),
restore AS (
    SELECT
        h.npc_id AS id,
        h.npc_name,
        h.npc_favorite,
        h.lock_profile,
        h.prompt_head,
        h.npc_static_bio,
        h.oghma_knowledge_tags,
        h.emote_moods,
        h.personality,
        h.relationships,
        h.occupation,
        h.skills,
        h.speechstyle,
        h.goals,
        h.voiceid,
        h.metadata,
        h.gender,
        h.race,
        h.refid,
        h.profile_id,
        h.dynamic_profile,
        CASE
            WHEN {$preserveRelationshipDataSql} THEN (
                (
                    COALESCE(h.extended_data, '{}'::jsonb)
                    - 'relationships'
                    - 'relationships_analyzed'
                    - 'relationships_inferred'
                    - 'relationships_last_eval'
                    - 'relationships_model'
                    - 'relationships_updated'
                    - '_chim_history_source'
                )
                || jsonb_strip_nulls(jsonb_build_object(
                    'relationships', d.current_extended_data -> 'relationships',
                    'relationships_analyzed', d.current_extended_data -> 'relationships_analyzed',
                    'relationships_inferred', d.current_extended_data -> 'relationships_inferred',
                    'relationships_last_eval', d.current_extended_data -> 'relationships_last_eval',
                    'relationships_model', d.current_extended_data -> 'relationships_model',
                    'relationships_updated', d.current_extended_data -> 'relationships_updated'
                ))
            )
            WHEN h.extended_data IS NULL THEN NULL
            ELSE h.extended_data - '_chim_history_source'
        END AS extended_data,
        h.md5,
        h.gamets_last_updated,
        h.core,
        h.base,
        h.tags,
        h.appearance
    FROM deleted d
    JOIN LATERAL (
        SELECT h.*
        FROM core_npc_master_history h
        WHERE h.npc_id = d.id
          AND (h.gamets_last_updated <= $timestamp OR h.gamets_last_updated IS NULL)
        ORDER BY
            h.gamets_last_updated DESC NULLS LAST,
            CASE WHEN h.extended_data ->> '_chim_history_source' = 'infosave' THEN 1 ELSE 0 END DESC,
            h.created DESC,
            h.history_id DESC
        LIMIT 1
    ) h ON true
)
INSERT INTO core_npc_master (
    id, npc_name, npc_favorite, lock_profile, prompt_head, npc_static_bio,
    oghma_knowledge_tags, emote_moods, personality, relationships,
    occupation, skills, speechstyle, goals, voiceid, metadata,
    gender, race, refid, profile_id, dynamic_profile, extended_data,
    md5, gamets_last_updated, core, base, tags, appearance
)
SELECT
    id, npc_name, npc_favorite, lock_profile, prompt_head, npc_static_bio,
    oghma_knowledge_tags, emote_moods, personality, relationships,
    occupation, skills, speechstyle, goals, voiceid, metadata,
    gender, race, refid, profile_id, dynamic_profile, extended_data,
    md5, gamets_last_updated, core, base, tags, appearance
FROM restore
";

        error_log("[NPC RESTORE] using gamets: $timestamp.. " . date('Y-m-d H:i:s'));
        $GLOBALS["db"]->query($query);

        $relationshipRestoreSucceeded = false;
        if ($neverClearRelationshipData) {
            error_log('[NPC RESTORE] Preserved current relationship data because Never Clear Relationship Data is enabled');
        } else {
            // Relationship state has its own snapshots. Merge the newest eligible relationship keys
            // after the full-profile restore without replacing unrelated NPC profile fields.
            try {
                $relationshipRestoreResult = $GLOBALS["db"]->fetchOne(chimRelationshipRestoreQuery($timestamp));
                if (!is_array($relationshipRestoreResult) || !array_key_exists('affected', $relationshipRestoreResult)) {
                    throw new RuntimeException('relationship restore query did not return a result');
                }
                $relationshipRestoreCount = (int) ($relationshipRestoreResult['affected'] ?? 0);
                $relationshipRestoreSucceeded = true;
                error_log("[NPC RESTORE] Restored relationship timeline data for {$relationshipRestoreCount} NPCs at gamets $timestamp");
            } catch (Throwable $e) {
                error_log("[NPC RESTORE] Failed to restore NPC relationships: " . $e->getMessage());
            }
        }

        $bglife_q="UPDATE public.core_npc_master
        SET extended_data = jsonb_set(
            extended_data,
            '{background_life_enabled}',   -- JSON path
            'false'::jsonb,                -- new value
            true                           -- create if missing (optional)
        )
        WHERE (extended_data ->> 'background_life_enabled')::boolean = true";

        $GLOBALS["db"]->execQuery($bglife_q);

        // Clear the background_life_last_updated timestamp for NPCs that were restored from history,
        //  so that they can be re-evaluated for background life generation.
        $bglife_q="UPDATE public.core_npc_master
        SET extended_data = jsonb_set(
            extended_data,
            '{background_life_last_updated}',   -- JSON path
            '0'::jsonb,                -- new value
            true                           -- create if missing (optional)
        )
        WHERE (extended_data ->> 'background_life_last_updated')::bigint > {$timestamp}";


        $GLOBALS["db"]->execQuery($bglife_q);


        // Clear the background_life_last_run timestamp for NPCs that were restored from history,
        //  so that they can be re-evaluated for background life generation.
        $bglife_q="UPDATE public.core_npc_master
        SET extended_data = jsonb_set(
            extended_data,
            '{background_life_last_run}',   -- JSON path
            '0'::jsonb,                -- new value
            true                           -- create if missing (optional)
        )
        WHERE (extended_data ->> 'background_life_last_run')::bigint > {$timestamp}";


        $GLOBALS["db"]->execQuery($bglife_q);

        if (!$neverClearRelationshipData) {
            // NPCs created after the loaded save have no eligible history. Clear only those rows;
            // relationship data restored from valid history must survive this cleanup.
            if ($relationshipRestoreSucceeded) {
                try {
                    $clearResult = $GLOBALS["db"]->fetchOne(chimRelationshipFutureClearQuery($timestamp));
                    if (!is_array($clearResult) || !array_key_exists('affected', $clearResult)) {
                        throw new RuntimeException('future relationship clear query did not return a result');
                    }
                    $clearCount = (int) ($clearResult['affected'] ?? 0);
                    $clearNames = trim((string) ($clearResult['sample_names'] ?? ''));
                    $clearSample = $clearNames !== '' ? " [{$clearNames}" . ($clearCount > 10 ? ', ...' : '') . ']' : '';
                    error_log("[NPC RESTORE] Cleared future-only relationship data for {$clearCount} NPCs at gamets $timestamp{$clearSample}");
                } catch (Throwable $e) {
                    error_log("[NPC RESTORE] Failed to clear future relationships: " . $e->getMessage());
                }
            } else {
                error_log("[NPC RESTORE] Skipped future relationship clear because timeline restore failed");
            }
        }

        error_log("[NPC RESTORE] " . date('Y-m-d H:i:s') . ", NPCs restore made in " . (time() - $startTime) . " secs ");
        return true;
    }

    private function markHistoryExtendedData($extendedData, $source)
    {
        $decoded = null;
        if (is_array($extendedData)) {
            $decoded = $extendedData;
        } elseif (is_string($extendedData) && trim($extendedData) !== '') {
            $decoded = json_decode($extendedData, true);
        }

        if (!is_array($decoded)) {
            $decoded = [];
        }

        $decoded['_chim_history_source'] = $source;

        return json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public function renameNPC($oldname, $newname)
    {

        $currentNpcData    = $this->getByName($newname);
        $currentNpcDataAlt = $this->getByName($oldname);

        $newId   = $currentNpcData["id"];
        $newName = $currentNpcData["npc_name"];

        $oldName = $GLOBALS["db"]->escape($currentNpcDataAlt["npc_name"]);
        $newName = $GLOBALS["db"]->escape($currentNpcData["npc_name"]);

        $currentNpcData = $currentNpcDataAlt; // Copy from old profile

        $currentNpcData["id"]       = $newId;
        $currentNpcData["npc_name"] = $newName;
        $currentNpcData["md5"]      = md5($newName);

        // eventlog.people (pipe-separated list)
        $GLOBALS["db"]->execQuery("
                    UPDATE eventlog
                    SET people = REPLACE(people, '$oldName', '$newName')
                    WHERE people LIKE CONCAT('%', '$oldName', '%')
                ");

        // speech.speaker and speech.listener
        $GLOBALS["db"]->execQuery("
                    UPDATE speech
                    SET speaker = '$newName'
                    WHERE speaker = '$oldName'
                ");
        $GLOBALS["db"]->execQuery("
                    UPDATE speech
                    SET listener = '$newName'
                    WHERE listener = '$oldName'
                ");

        // memory.speaker and memory.listener
        $GLOBALS["db"]->execQuery("
                    UPDATE memory
                    SET speaker = '$newName'
                    WHERE speaker = '$oldName'
                ");
        $GLOBALS["db"]->execQuery("
                    UPDATE memory
                    SET listener = '$newName'
                    WHERE listener = '$oldName'
                ");

        // memory_summary.companions (pipe-separated list)
        $GLOBALS["db"]->execQuery("
                    UPDATE memory_summary
                    SET companions = REPLACE(companions, '$oldName', '$newName')
                    WHERE companions LIKE CONCAT('%', '$oldName', '%')
                ");

        $currentNpcData["core"] .= ".Formerly known as {$currentNpcDataAlt["npc_name"]}";
        $this->updateByArray($currentNpcData);
    }

    /**
     * Retrieve all rows from the factions table.
     *
     * @param string $where Optional SQL WHERE clause (defaults to TRUE = all rows).
     * @return array        Array of faction rows from the factions table.
     */
    public function getAllfactions($where = "TRUE")
    {
        $query = "SELECT * FROM factions WHERE $where";
        return $this->db->fetchAll($query);
    }

    /**
     * Extract the factions an NPC belongs to from their extended_data JSON.
     *
     * Returns the raw factions array stored in extended_data, optionally filtered
     * to only active memberships (rank > -1).
     *
     * @param array $npcData        The NPC data array (must contain 'extended_data').
     * @param bool  $activeOnly     When true, only factions with rank > -1 are returned.
     * @return array                Array of faction entries (each with 'formid' and 'rank'),
     *                              or an empty array when none are found.
     */
    public function getNpcFactions(array $npcData, bool $activeOnly = true): array
    {
        if (empty($npcData['extended_data'])) {
            return [];
        }

        $extendedData = json_decode($npcData['extended_data'], true);

        if (!is_array($extendedData) || !isset($extendedData['factions']) || !is_array($extendedData['factions'])) {
            return [];
        }

        if (!$activeOnly) {
            return $extendedData['factions'];
        }

        return array_values(array_filter($extendedData['factions'], function ($faction) {
            return isset($faction['rank']) && $faction['rank'] > -1;
        }));
    }

    /**
     * Check if an NPC is in a specific faction by formid
     * 
     * @param array $npcData The NPC data array
     * @param string $factionFormId The faction formid to check (e.g., "0002817C")
     * @return bool True if the NPC is in the faction, false otherwise
     */
    public function isNpcInFaction($npcData, $factionFormId)
    {
        if (!isset($npcData['extended_data']) || empty($npcData['extended_data'])) {
            return false;
        }

        $extendedData = json_decode($npcData['extended_data'], true);
        
        if (!is_array($extendedData) || !isset($extendedData['factions']) || !is_array($extendedData['factions'])) {
            return false;
        }

        $stableReference = chimParseStableFormReference($factionFormId);
        if ($stableReference) {
            foreach ($extendedData['factions'] as $faction) {
                if (
                    isset($faction['rank']) && $faction['rank'] > -1 &&
                    chimFactionEntryMatchesStableFormReference($faction, $stableReference['stable_key'])
                ) {
                    return true;
                }
            }

            $resolvedRuntimeFormId = chimResolveStableFormReferenceToRuntimeFormId($stableReference['stable_key']);
            if ($resolvedRuntimeFormId !== null) {
                $factionFormId = $resolvedRuntimeFormId;
            }
        }

        // Normalize formid for comparison (handle case-insensitive comparison)
        $normalizedSearchFormId = strtoupper($factionFormId);

        // Check if any faction in the array matches the formid
        foreach ($extendedData['factions'] as $faction) {
            if (isset($faction['formid']) && strtoupper($faction['formid']) === $normalizedSearchFormId) {
                if ($faction['rank'] > -1) { // Optional: check if rank is greater than 0 to confirm active membership
                    return true;
                }
            }
        }

        return false;
    }

    private function resolveNpcTtsVoice(array $currentNpcData): array
    {
        if (!class_exists('TTSConnector')) {
            require_once(__DIR__ . DIRECTORY_SEPARATOR . "tts_connector.class.php");
        }

        $connectorData = null;
        $profileData = $GLOBALS["CHIM_CORE_CURRENT_PROFILE_DATA"] ?? [];
        $connectorId = intval($profileData['tts_connector_id'] ?? 0);
        $ttsConnector = new TTSConnector();
        if ($connectorId > 0) {
            $connectorData = $ttsConnector->getById($connectorId);
        }

        return $ttsConnector->resolveNpcVoiceForConnector($currentNpcData, $connectorData);
    }

    private function applyNpcVoiceToTtsGlobals(string $voiceId): void
    {
        $GLOBALS['TTS']['XTTSFASTAPI']['voiceid'] = $voiceId;
        $GLOBALS['TTS']['CHATTERBOX']['voiceid'] = $voiceId;
        $GLOBALS['TTS']['POCKETTTS']['voiceid'] = $voiceId;
        $GLOBALS['TTS']['OMNIVOICE']['voiceid'] = $voiceId;
        $GLOBALS['TTS']['MELOTTS']['voiceid'] = $voiceId;
        $GLOBALS['TTS']['MIMIC3']['voice'] = $voiceId;
        $GLOBALS['TTS']['XVASYNTH']['model'] = $voiceId;
        $GLOBALS['TTS']['ZONOS_GRADIO']['voiceid'] = $voiceId;
        $GLOBALS['TTS']['PIPERTTS']['voiceid'] = $voiceId;
        $GLOBALS['TTS']['ELEVEN_LABS']['voice_id'] = $voiceId;
        $GLOBALS['TTS']['AZURE']['voice'] = $voiceId;
        $GLOBALS['TTS']['KOKORO']['voiceid'] = $voiceId;
        $GLOBALS['TTS']['openai']['voice'] = $voiceId;
        $GLOBALS['TTS']['deepgram']['model'] = $voiceId;
        $GLOBALS['TTS']['CARTESIA']['voiceid'] = $voiceId;
        $GLOBALS['TTS']['INWORLD']['voiceid'] = $voiceId;
    }

    /* For reference. Restores the last version of each NPC list from history. */
    
    private function restoreLastNpcList() {

         $GLOBALS["db"]->execQuery("INSERT INTO core_npc_master (
    id, npc_name, npc_favorite, lock_profile, prompt_head, npc_static_bio,
    oghma_knowledge_tags, emote_moods, personality, relationships,
    occupation, skills, speechstyle, goals, voiceid, metadata,
    gender, race, refid, profile_id, dynamic_profile, extended_data,
    md5, gamets_last_updated, core, base, tags, appearance
)
SELECT
    npc_id AS id,
    npc_name,
    npc_favorite,
    lock_profile,
    prompt_head,
    npc_static_bio,
    oghma_knowledge_tags,
    emote_moods,
    personality,
    relationships,
    occupation,
    skills,
    speechstyle,
    goals,
    voiceid,
    metadata,
    gender,
    race,
    refid,
    profile_id,
    dynamic_profile,
    extended_data,
    md5,
    gamets_last_updated,
    core,
    base,
    tags,
    appearance
FROM (
    SELECT
        *,
        ROW_NUMBER() OVER (
            PARTITION BY npc_name
            ORDER BY gamets_last_updated DESC
        ) AS rn
    FROM core_npc_master_history
) t
WHERE rn = 1
AND npc_name not in (select distinct npc_name from core_npc_master)");
    }
}

