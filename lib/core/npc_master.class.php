<?php

if (!function_exists('chimParseStableFormReference')) {
    require_once(__DIR__ . DIRECTORY_SEPARATOR . "game_plugins.php");
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
        $miscParts = array_unique(array_filter(array_map('trim', explode(',', $misc))));
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
                 "BORED_EVENT_SERVERSIDE", "CONTEXT_HISTORY", "CONTEXT_HISTORY_DIARY", "CONTEXT_HISTORY_DYNAMIC_PROFILE",
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
        $currentNpcData['profile_id'] = 1;                                // Default profile
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

        if (isset($currentNpcData['npc_static_bio'])) {
            $GLOBALS['HERIKA_BACKGROUND'] = $currentNpcData['npc_static_bio'];
        }

        if (isset($currentNpcData['oghma_knowledge_tags'])) {
            $GLOBALS['OGHMA_KNOWLEDGE'] = $currentNpcData['oghma_knowledge_tags'];
        }

        if (isset($currentNpcData['personality'])) {
            $GLOBALS['HERIKA_PERSONALITY'] = $currentNpcData['personality'];
        }

        unset($GLOBALS['HERIKA_RELATIONSHIPS']);

        if (isset($currentNpcData['occupation'])) {
            $GLOBALS['HERIKA_OCCUPATION'] = $currentNpcData['occupation'];
        }

        if (isset($currentNpcData['appearance'])) {
            $GLOBALS['HERIKA_APPEARANCE'] = $currentNpcData['appearance'];
        }

        if (isset($currentNpcData['skills'])) {
            $GLOBALS['HERIKA_SKILLS'] = $currentNpcData['skills'];
        }

        if (isset($currentNpcData['speechstyle'])) {
            $GLOBALS['HERIKA_SPEECHSTYLE'] = $currentNpcData['speechstyle'];
        }

        if (isset($currentNpcData['emote_moods']) && ! empty(trim($currentNpcData['emote_moods']))) {
            $GLOBALS['EMOTEMOODS'] = $currentNpcData['emote_moods'];
        }

        if (isset($currentNpcData['goals'])) {
            $GLOBALS['HERIKA_GOALS'] = $currentNpcData['goals'];
        }

        if (isset($currentNpcData['core'])) {
            $GLOBALS['HERIKA_PERS'] = "Roleplay as {$GLOBALS['HERIKA_NAME']}.\n{$currentNpcData['core']}";
        } else {
            $GLOBALS['HERIKA_PERS'] = "Roleplay as {$GLOBALS['HERIKA_NAME']}";
        }

        $voiceResolution = $this->resolveNpcTtsVoice($currentNpcData);
        $resolvedVoice = $voiceResolution['resolved_voice'];
        $originalVoice = $voiceResolution['original_voice'];
        $fallbackVoice = $voiceResolution['fallback_voice'];

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
                    // Convert string "true"/"false" to actual booleans for proper PHP evaluation
                    if ($value === 'true') {
                        $value = true;
                    } elseif ($value === 'false') {
                        $value = false;
                    }
                    $GLOBALS[$key] = $value;
                    //error_log("[CORE] NPC  GLOBALS[$key] = ".print_r($value,true));
                }

            }
        }

        // Apply extended_data overrides (highest precedence - NPC level)
        // Reserved keys are excluded (system fields managed by dedicated subsystems/toggles)
        $reservedKeys = ['middle_term_memory', 'middle_term_enabled', 'individual_memory_enabled', 'chim_core_migrated'];
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
                // Apply override to GLOBALS
                // Handle nested keys (space-separated): "TTS MELOTTS voiceid" -> $GLOBALS['TTS']['MELOTTS']['voiceid']
                if (! empty($value) || is_numeric($value) || is_bool($value)) {
                    $parts = explode(' ', $key);
                    if (count($parts) === 1) {
                        // Simple key
                        $GLOBALS[$key] = $value;
                        // error_log("[CORE] NPC EXTENDED_DATA OVERRIDE  GLOBALS[$key] = " . print_r($value, true));
                    } else if (count($parts) === 2) {
                        // Nested 2 levels: TTS MELOTTS
                        if (! isset($GLOBALS[$parts[0]])) {
                            $GLOBALS[$parts[0]] = [];
                        }

                        $GLOBALS[$parts[0]][$parts[1]] = $value;
                        // error_log("[CORE] NPC EXTENDED_DATA OVERRIDE  GLOBALS[{$parts[0]}][{$parts[1]}] = " . print_r($value, true));
                    } else if (count($parts) === 3) {
                        // Nested 3 levels: TTS MELOTTS voiceid
                        if (! isset($GLOBALS[$parts[0]])) {
                            $GLOBALS[$parts[0]] = [];
                        }

                        if (! isset($GLOBALS[$parts[0]][$parts[1]])) {
                            $GLOBALS[$parts[0]][$parts[1]] = [];
                        }

                        $GLOBALS[$parts[0]][$parts[1]][$parts[2]] = $value;
                        // error_log("[CORE] NPC EXTENDED_DATA OVERRIDE  GLOBALS[{$parts[0]}][{$parts[1]}][{$parts[2]}] = " . print_r($value, true));
                    }
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

    public function backupNpcById($id)
    {
        $id = (int) $id;

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
                gender, race, refid, profile_id, dynamic_profile, extended_data,
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
        $startTime = time();
        $query     =
            "WITH deleted AS (
    DELETE FROM core_npc_master
    WHERE npc_name<>'The Narrator' and COALESCE(lock_profile,0)=0
    and COALESCE(gamets_last_updated,0)>0
    RETURNING id
),
restore AS (
    SELECT DISTINCT ON (h.npc_id)
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
        h.extended_data,
        h.md5,
        h.gamets_last_updated,
        h.core,
        h.base,
        h.tags,
        h.appearance
    FROM core_npc_master_history h
    JOIN deleted d ON h.npc_id = d.id
    WHERE h.gamets_last_updated <= $timestamp OR h.gamets_last_updated IS NULL
    ORDER BY h.npc_id, h.gamets_last_updated DESC NULLS LAST,h.created DESC
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

        $bglife_q="UPDATE public.core_npc_master
        SET extended_data = jsonb_set(
            extended_data,
            '{background_life_enabled}',   -- JSON path
            'false'::jsonb,                -- new value
            true                           -- create if missing (optional)
        )
        WHERE (extended_data ->> 'background_life_enabled')::boolean = true";

        $GLOBALS["db"]->execQuery($bglife_q);

        // RELATIONSHIP SYSTEM: Clear "future" relationship data from NPCs that weren't restored
        // NPCs added AFTER the save timestamp don't have history entries, so they keep their
        // current (future) state. We need to clear their relationship data to prevent paradoxes.
        $rel_reset_q = "UPDATE public.core_npc_master
            SET extended_data = extended_data - 'relationships' - 'relationships_updated' - 'relationships_model' - 'relationships_inferred'
            WHERE npc_name <> 'The Narrator'
              AND (gamets_last_updated > $timestamp OR gamets_last_updated IS NULL)
              AND extended_data IS NOT NULL
              AND extended_data ? 'relationships'";

        try {
            $GLOBALS["db"]->execQuery($rel_reset_q);
            error_log("[NPC RESTORE] Cleared future relationship data for NPCs with gamets > $timestamp");
        } catch (Exception $e) {
            error_log("[NPC RESTORE] Failed to clear future relationships: " . $e->getMessage());
        }

        error_log("[NPC RESTORE] " . date('Y-m-d H:i:s') . ", NPCs restore made in " . (time() - $startTime) . " secs ");
        return true;
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
        if ($connectorId > 0) {
            $ttsConnector = new TTSConnector();
            $connectorData = $ttsConnector->getById($connectorId);
            return $ttsConnector->resolveNpcVoiceForConnector($currentNpcData, $connectorData);
        }

        $voiceId = trim(strval($currentNpcData['voiceid'] ?? ''));
        return [
            'original_voice' => $voiceId,
            'fallback_voice' => '',
            'resolved_voice' => $voiceId,
            'used_fallback' => false,
        ];
    }

    private function applyNpcVoiceToTtsGlobals(string $voiceId): void
    {
        $GLOBALS['TTS']['XTTSFASTAPI']['voiceid'] = $voiceId;
        $GLOBALS['TTS']['CHATTERBOX']['voiceid'] = $voiceId;
        $GLOBALS['TTS']['POCKETTTS']['voiceid'] = $voiceId;
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
}
