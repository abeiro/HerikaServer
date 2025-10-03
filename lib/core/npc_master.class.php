<?php

class NpcMaster {
    private $table = "core_npc_master";
    private $db;

    public static function profileExists($npcName, $checkLegacyFile = false) {
        // Access global DB instance
        $db = $GLOBALS["db"];
        $escaped = $db->escape($npcName);
        $query = "SELECT 1 FROM core_npc_master WHERE npc_name = '{$escaped}' LIMIT 1";
        $result = $db->fetchOne($query);

        if ($result) {
            return true; // Found in database
        }

        return false;
    }

    public function __construct() {
        $this->db = $GLOBALS["db"];
    }

    // Create (Insert)
    public function create($data) {
        $fields = [
            "npc_name",
            "npc_favorite",
            "lock_profile",
            "prompt_head",
            "npc_static_bio",
            "oghma_knowledge_tags",
            "emote_moods",
            "personality",
            "relationships",
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
            "tags"
        ];

        foreach ($data as $k => $v) {
            if (empty($v)) {
                $data[$k] = null;
            }
        }
        $data["md5"]=md5($data["npc_name"]);
        $filtered = array_intersect_key($data, array_flip($fields));
        return $this->db->insert($this->table, $filtered);
    }

    // Read NPC by ID
    public function getById($id) {
        $id = (int)$id;
        $query = "SELECT * FROM {$this->table} WHERE id = $id LIMIT 1";
        return $this->db->fetchOne($query);
    }

    // Read NPC by unique name
    public function getByName($npcName) {
        $escaped = $this->escape($npcName);
        $query = "SELECT * FROM {$this->table} WHERE npc_name = '{$escaped}' LIMIT 1";
        return $this->db->fetchOne($query);
    }

     // Read NPC by md5
     public function getByMD5($npcName) {
        $escaped = $this->escape($npcName);
        $query = "SELECT * FROM {$this->table} WHERE md5 = '{$escaped}' LIMIT 1";
        return $this->db->fetchOne($query);
    }


    // Read all NPCs (optional WHERE)
    public function getAll($where = "TRUE") {
        $query = "SELECT * FROM {$this->table} WHERE $where";
        return $this->db->fetchAll($query);
    }

    // Update NPC by ID
    public function update($id, $data) {
        $fields = [
            "npc_name",
            "npc_favorite",
            "lock_profile",
            "prompt_head",
            "npc_static_bio",
            "oghma_knowledge_tags",
            "emote_moods",
            "personality",
            "relationships",
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
            "tags"
        ];

        $id = (int)$id;
        $where = "id = $id";

        // Prevent renaming The Narrator
        $existing = $this->getById($id);
        if ($existing && isset($existing['npc_name']) && $existing['npc_name'] === 'The Narrator') {
            if (isset($data['npc_name']) && $data['npc_name'] !== $existing['npc_name']) {
                unset($data['npc_name']);
            }
        }

        foreach ($data as $k => $v) {
            if (empty($v)) {
                $data[$k] = null;
            }
        }

        $id = intval($id);
        $where = "id = {$id}";
        $filtered = array_intersect_key($data, array_flip($fields));
        return $GLOBALS["db"]->updateRow($this->table, $filtered, $where);
       
    }

    // Update NPC using an array (id key required)
    public function updateByArray($data) {
        if (!isset($data['id'])) {
            return false;
        }

        $id = (int)$data['id'];
        unset($data['id']); // Remove 'id' from the data array to avoid updating it

        
        return $this->update($id,$data);
    }

    // Delete NPC by ID
    public function delete($id) {
        $id = (int)$id;
        $where = "id = $id";
        // Disallow deleting The Narrator profile (by id or name)
        $row = $this->getById($id);
        if ($row && (intval($row['id']) === 1 || ($row['npc_name'] ?? '') === 'The Narrator')) {
            return false;
        }
        return $this->db->delete($this->table, $where);
    }

    // Truncate table (dangerous!)
    public function truncate($restart = false, $cascade = false) {
        return $this->db->truncate($this->table, $restart, $cascade);
    }

    // Upsert using ON CONFLICT
    public function upsert($data, $conflictTarget) {
        return $this->db->upsertRowOnConflict($this->table, $data, $conflictTarget);
    }

    // Escape strings for raw queries
    public function escape($str) {
        return $this->db->escape($str);
    }

    // Convert NPC name to codename
    public function npcNameToCodename($npcName) {
        $codename = mb_convert_encoding($npcName, 'UTF-8', mb_detect_encoding($npcName));
        // Use multibyte lowercase so accented capitals (e.g., É) convert correctly
        $codename = mb_strtolower(trim($codename), 'UTF-8');
        $codename = strtr($codename, [" " => "_", "'" => "+"]);
        // Allow unicode letters/digits plus underscore, plus and hyphen
        $codename = preg_replace('/[^\p{L}\p{N}_+-]/u', '', $codename);
        return $codename;
    }

    public function createProfile($npcname, $FORCE_PARMS = [], $overwrite = false, $baseprofile = '') {
        if ($npcname === "The Narrator") {
            return; // refuse narrator
        }

        $codename = $this->npcNameToCodename($npcname);
        $baseprofileName = $this->npcNameToCodename($baseprofile);

        // Check if NPC already exists in DB
        $existing = $this->getByName($npcname);

        if ($existing && !$overwrite) {
            // Profile exists, and no overwrite requested — bail
            return;
        }

        // Fetch NPC templates
        $templateRow = $this->fetchTemplateRow($codename);

        // Override with FORCE_PARMS
        foreach ($FORCE_PARMS as $key => $value) {
            $templateRow[$key] = $value;
        }

        // Compose knowledge string (OGHMA_KNOWLEDGE)
        $templateRow['npc_misc'] = $this->composeKnowledgeString($templateRow['npc_misc'], $codename);

        // Fetch voice IDs
        $voiceData = $this->fetchVoiceData($codename);

        // Compose the row data to insert/update
        $rowData = array_merge($templateRow, $voiceData, [
            'npc_name' => $npcname,
            'npc_codename' => $codename
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

    private function fetchTemplateRow($codename) {
        $lang = $GLOBALS["CORE_LANG"] ?? '';
        $escCode = $this->db->escape($codename);
        if ($lang) {
            $escLang = $this->db->escape($lang);
            // If translations exist, we only pull legacy npc_pers; otherwise fallback to bio view
            $templateRow = $this->db->fetchOne("SELECT npc_pers FROM npc_templates_trl WHERE lower(name_trl) = lower('{$escCode}') AND lang = '{$escLang}'");
        }

        if (!$templateRow) {
            $templateRow = $this->db->fetchOne(
                "SELECT core, oghma_knowledge_tags as npc_misc, npc_static_bio as npc_background, personality as npc_personality, appearance as npc_appearance, relationships as npc_relationships, occupation as npc_occupation, skills as npc_skills, speechstyle as npc_speechstyle, goals as npc_goals FROM combined_bio_templates WHERE lower(npc_name) = lower('{$escCode}')"
            );
        }

        
        return $templateRow ?: [
            'core' => 'Roleplay as ' . $codename,
            'npc_misc' => $codename,
            'npc_background' => '',
            'npc_personality' => '',
            'npc_appearance' => '',
            'npc_relationships' => '',
            'npc_occupation' => '',
            'npc_skills' => '',
            'npc_speechstyle' => '',
            'npc_goals' => ''
        ];
    }

    private function composeKnowledgeString($misc, $codename) {
        $miscParts = array_unique(array_filter(array_map('trim', explode(',', $misc))));
        if (!in_array($codename, $miscParts)) {
            $miscParts[] = $codename;
        }
        return implode(', ', $miscParts);
    }

    private function fetchVoiceData($codename) {
        $escCode = $this->db->escape($codename);
        $voiceRow = $this->db->fetchOne("SELECT voiceid FROM combined_bio_templates WHERE lower(npc_name) = lower('{$escCode}')");
        $voicetypeString = $this->fetchVoicetype($codename);

        return array_merge($voiceRow ?: [], ['voicetype' => $voicetypeString]);
    }

    private function fetchVoicetype($codename) {
        $cn = $this->db->escape("Voicetype/$codename");
        $vtypeRows = $this->db->fetchAll("SELECT value FROM conf_opts WHERE lower(id) = lower('$cn')");
        return $vtypeRows[0]['value'] ?? '';
    }

    public function migrateFromOldProfile($currentNpcData,$OLD_GLOBALS_ARRAY) {

        $currentNpcData['npc_favorite'] = 0; // Default
        $currentNpcData['lock_profile'] = isset($OLD_GLOBALS_ARRAY['LOCK_PROFILE']) ? ($OLD_GLOBALS_ARRAY['LOCK_PROFILE'] ? 1 : 0) : 0;
        $currentNpcData['dynamic_profile'] = isset($OLD_GLOBALS_ARRAY['DYNAMIC_PROFILE']) ? ($OLD_GLOBALS_ARRAY['DYNAMIC_PROFILE'] ? 1 : 0) : 0;
        if (isset($OLD_GLOBALS_ARRAY['HERIKA_PERS'])) $currentNpcData['core'] = $OLD_GLOBALS_ARRAY['HERIKA_PERS'];
        if (isset($OLD_GLOBALS_ARRAY['PROMPT_HEAD'])) $currentNpcData['prompt_head'] = $OLD_GLOBALS_ARRAY['PROMPT_HEAD'];
        if (isset($OLD_GLOBALS_ARRAY['HERIKA_BACKGROUND'])) $currentNpcData['npc_static_bio'] .= $OLD_GLOBALS_ARRAY['HERIKA_BACKGROUND'];
        if (isset($OLD_GLOBALS_ARRAY['OGHMA_KNOWLEDGE'])) $currentNpcData['oghma_knowledge_tags'] = $OLD_GLOBALS_ARRAY['OGHMA_KNOWLEDGE'];
        if (isset($OLD_GLOBALS_ARRAY['HERIKA_PERSONALITY'])) $currentNpcData['personality'] = $OLD_GLOBALS_ARRAY['HERIKA_PERSONALITY'];
        if (isset($OLD_GLOBALS_ARRAY['HERIKA_RELATIONSHIPS'])) $currentNpcData['relationships'] = $OLD_GLOBALS_ARRAY['HERIKA_RELATIONSHIPS'];
        if (isset($OLD_GLOBALS_ARRAY['HERIKA_OCCUPATION'])) $currentNpcData['occupation'] = $OLD_GLOBALS_ARRAY['HERIKA_OCCUPATION'];
        if (isset($OLD_GLOBALS_ARRAY['HERIKA_APPEARANCE'])) $currentNpcData['appearance'] = $OLD_GLOBALS_ARRAY['HERIKA_APPEARANCE'];
        if (isset($OLD_GLOBALS_ARRAY['HERIKA_SKILLS'])) $currentNpcData['skills'] = $OLD_GLOBALS_ARRAY['HERIKA_SKILLS'];
        if (isset($OLD_GLOBALS_ARRAY['HERIKA_SPEECHSTYLE'])) $currentNpcData['speechstyle'] = $OLD_GLOBALS_ARRAY['HERIKA_SPEECHSTYLE'];
        if (isset($OLD_GLOBALS_ARRAY['EMOTEMOODS'])) $currentNpcData['emote_moods'] = $OLD_GLOBALS_ARRAY['EMOTEMOODS'];
        if (isset($OLD_GLOBALS_ARRAY['HERIKA_GOALS'])) $currentNpcData['goals'] = $OLD_GLOBALS_ARRAY['HERIKA_GOALS'];
        if (isset($OLD_GLOBALS_ARRAY['TTS']['XTTSFASTAPI']['voiceid'])) $currentNpcData['voiceid'] = $OLD_GLOBALS_ARRAY['TTS']['XTTSFASTAPI']['voiceid'];

        /*
        foreach ($OLD_GLOBALS_ARRAY as $k=>$v) {
            if (!is_array($v)) {
                if (in_array($k,[
                 "DIARY_COOLDOWN", "AUTO_DIARY", "AUTO_DIARY_WAIT", "MINIME_T5",
                 "OGHMA_INFINIUM", "OGHMA_AMOUNT", "RECHAT_H", "RECHAT_P", "RECHAT_ALLOW_ACTIONS", "BORED_EVENT", 
                 "BORED_EVENT_SERVERSIDE", "CONTEXT_HISTORY", "CONTEXT_HISTORY_DIARY", "CONTEXT_HISTORY_DYNAMIC_PROFILE",
                 "ALIVE_MESSAGE", "TIME_AWARENESS", "QUEST_COMMENT", "QUEST_COMMENT_CHANCE", "CURRENT_TASK", 
                 "HERIKA_ANIMATIONS", "CORE_LANG", "LANG_LLM_XTTS", "MAX_WORDS_LIMIT", 
                 "REMOVE_ASTERISKS_FROM_OUTPUT", "ENFORCE_ACTIONS_PROMPT", "DIARY_PROMPT"]))
                    if (!empty($v))
                        $overrides[$k]=$v;
            }
        }
        */
        if (empty($currentNpcData['personality']) && isset($OLD_GLOBALS_ARRAY['HERIKA_PERSONALITY'])) {
            $currentNpcData['personality'] = $OLD_GLOBALS_ARRAY['HERIKA_PERSONALITY'];
        }
        $currentNpcData['metadata'] = json_encode($overrides);
        $currentNpcData['extended_data'] = json_encode(["chim_core_migrated"=>1]);
        $currentNpcData['gender'] = null; // Optional: you might use HERIKA_PERSONALITY/gender logic
        $currentNpcData['race'] = null; // Optional: no race found
        $currentNpcData['refid'] = null; // Optional: no race found
        $currentNpcData['base'] = null; // Optional: no race found
        // Prefer HERIKA_PERS for core; fallback to HERIKA_NAME if core not set
        if (!isset($currentNpcData['core']) || $currentNpcData['core'] === '') {
            if (isset($OLD_GLOBALS_ARRAY['HERIKA_PERS']) && $OLD_GLOBALS_ARRAY['HERIKA_PERS'] !== '') {
                $currentNpcData['core'] = $OLD_GLOBALS_ARRAY['HERIKA_PERS'];
            } else if (isset($OLD_GLOBALS_ARRAY['HERIKA_NAME']) && $OLD_GLOBALS_ARRAY['HERIKA_NAME'] !== '') {
                $currentNpcData['core'] = $OLD_GLOBALS_ARRAY['HERIKA_NAME'];
            }
        }
        $currentNpcData['profile_id'] = 1; // Default profile
        $currentNpcData['md5'] = md5($currentNpcData["npc_name"]); // Default profile

        return $currentNpcData;

    }

    public function setOldGlobalsFromCurrentNpcData($currentNpcData) {
        
        if (isset($currentNpcData['npc_name'])) $GLOBALS['HERIKA_NAME'] = $currentNpcData['npc_name'];
        if (isset($currentNpcData['lock_profile'])) $GLOBALS['LOCK_PROFILE'] = $currentNpcData['lock_profile'] ? true : false;
        if (isset($currentNpcData['dynamic_profile'])) $GLOBALS['DYNAMIC_PROFILE'] = $currentNpcData['dynamic_profile'] ? true : false;
        if (isset($currentNpcData['prompt_head'])) $GLOBALS['PROMPT_HEAD'] = $currentNpcData['prompt_head'];
        if (isset($currentNpcData['npc_static_bio'])) $GLOBALS['HERIKA_BACKGROUND'] = $currentNpcData['npc_static_bio'];
        if (isset($currentNpcData['oghma_knowledge_tags'])) $GLOBALS['OGHMA_KNOWLEDGE'] = $currentNpcData['oghma_knowledge_tags'];
        if (isset($currentNpcData['personality'])) $GLOBALS['HERIKA_PERSONALITY'] = $currentNpcData['personality'];
        if (isset($currentNpcData['relationships'])) $GLOBALS['HERIKA_RELATIONSHIPS'] = $currentNpcData['relationships'];
        if (isset($currentNpcData['occupation'])) $GLOBALS['HERIKA_OCCUPATION'] = $currentNpcData['occupation'];
        if (isset($currentNpcData['appearance'])) $GLOBALS['HERIKA_APPEARANCE'] = $currentNpcData['appearance'];
        if (isset($currentNpcData['skills'])) $GLOBALS['HERIKA_SKILLS'] = $currentNpcData['skills'];
        if (isset($currentNpcData['speechstyle'])) $GLOBALS['HERIKA_SPEECHSTYLE'] = $currentNpcData['speechstyle'];
        if (isset($currentNpcData['emote_moods']) && !empty(trim($currentNpcData['emote_moods']))) $GLOBALS['EMOTEMOODS'] = $currentNpcData['emote_moods'];
        if (isset($currentNpcData['goals'])) $GLOBALS['HERIKA_GOALS'] = $currentNpcData['goals'];
        if (isset($currentNpcData['core']))
            $GLOBALS['HERIKA_PERS'] = "Roleplay as {$currentNpcData['core']}";
        else
            $GLOBALS['HERIKA_PERS'] = "Roleplay as {$GLOBALS['HERIKA_NAME']}";

        // Check this
        if (isset($currentNpcData['voiceid']) && $currentNpcData['voiceid']) {
            
            $GLOBALS['TTS']['XTTSFASTAPI']['voiceid'] = $currentNpcData['voiceid'];
            $GLOBALS['TTS']['MELOTTS']['voiceid'] = $currentNpcData['voiceid'];
            $GLOBALS['TTS']['MIMIC3']['voice'] = $currentNpcData['voiceid'];
            $GLOBALS['TTS']['XVASYNTH']['model'] = $currentNpcData['voiceid'];
            $GLOBALS['TTS']['ZONOS_GRADIO']['voiceid'] = $currentNpcData['voiceid'];
            $GLOBALS['TTS']['PIPERTTS']['voiceid'] = $currentNpcData['voiceid'];

        }

        // Decode metadata and extended_data if available
        $metadata = json_decode($currentNpcData['metadata'] ?? '{}', true);
        if (is_array($metadata)) {
            foreach ($metadata as $key => $value) {
                if (!empty($value)) {
                    $GLOBALS[$key] = $value;
                    error_log("[CORE] NPC  GLOBALS[$key] = ".print_r($value,true));
                }

            }
        }

        
    }

    public function getAllFk($field) {
        // Map foreign key fields to their respective tables
        $fkMap = [
            "profile_id"     => "core_profiles"
        ];
    
        if (!array_key_exists($field, $fkMap)) {
            return []; // Unknown field
        }
    
        $table = $fkMap[$field];
        $query = "SELECT id, label FROM {$table} ORDER BY id ASC";
        return $GLOBALS["db"]->fetchAll($query);
    }

    public function getExtendedData($currentNpcData): array {
        return json_decode($currentNpcData['extended_data'] ?? '{}', true) ?: [];
    }

    public function setExtendedData($currentNpcData, array $data) {
        
        $currentNpcData['extended_data'] = json_encode($data);
        return $currentNpcData;
    }

    public function getMetadata($currentNpcData): array {
        return json_decode($currentNpcData['metadata'] ?? '{}', true) ?: [];
    }

    public function setMetadata($currentNpcData, array $data) {
        
        $currentNpcData['metadata'] = json_encode($data);
        return $currentNpcData;
    }

    public function backupNpcById($id) {
        $id = (int)$id;
        
        // Retrieve the current NPC
        $npc = $this->getById($id);
        if (!$npc) {
            return false; // NPC not found
        }
    
        // Remove the original 'id' field, since the history table likely has its own auto-increment ID
        unset($npc['id']);
    
        // Add a reference to the original NPC ID
        $npc['npc_id'] = $id;
    
        // Add the current timestamp for tracking purposes (optional)
        $npc['created'] = date('Y-m-d H:i:s');
    
        // Insert the data into the history table
        return $this->db->insert('core_npc_master_history', $npc);
    }
    

    public function backupAllNpcs($timestamp) {
        // Validate the timestamp (ensure it's a float or numeric format, as per your schema)
        if (!is_numeric($timestamp)) {
            throw new InvalidArgumentException("Invalid timestamp value.");
        }

        $startTime=time();
        // Fetch all current NPCs
        $npcs = $this->getAll();
        error_log("[NPC BACKUP] ".date('Y-m-d H:i:s'));

        foreach ($npcs as $npc) {
            // Remove original ID
            $npc_id = $npc['id'];
            unset($npc['id']);
    
            // Set the reference and override timestamps
            $npc['npc_id'] = $npc_id;
            $npc['gamets_last_updated'] = $timestamp;
            $npc['created'] = date('Y-m-d H:i:s'); // Current timestamp
    
            // Insert into history
            $this->db->insert('core_npc_master_history', $npc);
        }
        error_log("[NPC BACKUP] ".date('Y-m-d H:i:s'). ", NPCs backup made in ".(time()-$startTime)." secs ");
        return true;
    }

     public function restoreNPC($timestamp) {
        // Validate the timestamp (ensure it's a float or numeric format, as per your schema)
        if (!is_numeric($timestamp)) {
            throw new InvalidArgumentException("Invalid timestamp value.");
        }
        $startTime=time();
        $query=
"WITH deleted AS (
    DELETE FROM core_npc_master
    WHERE npc_name<>'The Narrator'
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
    WHERE h.gamets_last_updated < $timestamp OR h.gamets_last_updated IS NULL
    ORDER BY h.npc_id, h.gamets_last_updated DESC NULLS LAST
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
        
        error_log("[NPC RESTORE] using gamets: $timestamp.. ".date('Y-m-d H:i:s'));
        $GLOBALS["db"]->query($query);

        error_log("[NPC RESTORE] ".date('Y-m-d H:i:s'). ", NPCs restore made in ".(time()-$startTime)." secs ");
        return true;
    }

}

?>
