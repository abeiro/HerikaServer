<?php

class CoreProfile {
    private $table = "core_profiles";
    private $lastError = '';

    public function create($data) {
        $fields = [
            "label",
            "default_npc",
            "default_narrator",
            "tts_connector_id",
            "itt_connector_id",
            "diary_connector_id",
            "llm_primary_id",
            "llm_secondary_id",
            "llm_tertiary_id",
            "llm_quaternary_id",
            "llm_formatter_id",
            "metadata",
            "slot",
            "prompt"
        ];

        foreach ($data as $k=>$v)
            if (empty($v))
                $data[$k]=null;

        // Validate slot (NULL or 1..4 and unique)
        if (array_key_exists('slot', $data)) {
            $slotRaw = $data['slot'];
            $slotVal = ($slotRaw === null || $slotRaw === '') ? null : intval($slotRaw);
            if (!is_null($slotVal) && ($slotVal < 1 || $slotVal > 4)) {
                $this->lastError = 'Slot must be 1-4 or empty';
                return false;
            }
            if (!is_null($slotVal)) {
                $exists = $GLOBALS["db"]->fetchOne("SELECT id FROM core_profiles WHERE slot=".$slotVal." LIMIT 1");
                if (is_array($exists) && isset($exists['id'])) {
                    $this->lastError = 'That slot is already assigned to another profile';
                    return false;
                }
            }
            $data['slot'] = $slotVal;
        }

        $filtered = array_intersect_key($data, array_flip($fields));
        return $GLOBALS["db"]->insert($this->table, $filtered);
    }

    public function readAll() {
        $query = "SELECT * FROM {$this->table} ORDER BY id ASC";
        return $GLOBALS["db"]->fetchAll($query);
    }

    public function readOne($id) {
        $id = intval($id);
        $query = "SELECT * FROM {$this->table} WHERE id = {$id} LIMIT 1";
        return $GLOBALS["db"]->fetchOne($query);
    }

    public function getById($id) {
        return $this->readOne($id);
    }

    public function update($id, $data) {
        $id = intval($id);
        $where = "id = {$id}";

        $fields = [
            "label",
            "default_npc",
            "default_narrator",
            "tts_connector_id",// fk to table  core_tts_connector
            "itt_connector_id",// fk to table  core_itt_connector
            "diary_connector_id",// fk to table  core_diary_connector
            "llm_primary_id",// fk to table  core_llm_connector
            "llm_secondary_id",// fk to table  core_llm_connector
            "llm_tertiary_id",// fk to table  core_llm_connector
            "llm_quaternary_id",// fk to table  core_llm_connector
            "llm_formatter_id",// fk to table  core_llm_connector
            "metadata",
            "slot",
            "prompt"
        ];

        foreach ($data as $k=>$v)
            if (empty($v))
                $data[$k]=null;

        // Validate slot (NULL or 1..4 and unique for other profiles)
        if (array_key_exists('slot', $data)) {
            $slotRaw = $data['slot'];
            $slotVal = ($slotRaw === null || $slotRaw === '') ? null : intval($slotRaw);
            if (!is_null($slotVal) && ($slotVal < 1 || $slotVal > 4)) {
                $this->lastError = 'Slot must be 1-4 or empty';
                return false;
            }
            if (!is_null($slotVal)) {
                $exists = $GLOBALS["db"]->fetchOne("SELECT id FROM core_profiles WHERE slot=".$slotVal." AND id<>".$id." LIMIT 1");
                if (is_array($exists) && isset($exists['id'])) {
                    $this->lastError = 'That slot is already assigned to another profile';
                    return false;
                }
            }
            $data['slot'] = $slotVal;
        }

        $filtered = array_intersect_key($data, array_flip($fields));
        return $GLOBALS["db"]->updateRow($this->table, $filtered, $where);
    }

    public function delete($id) {
        $id = intval($id);
        return $GLOBALS["db"]->delete($this->table, "id = {$id}");
    }

    public function truncate($restart = false, $cascade = false) {
        return $GLOBALS["db"]->truncate($this->table, $restart, $cascade);
    }

    public function getLastError() {
        if (!empty($this->lastError)) return $this->lastError;
        return $GLOBALS["db"]->GetLastError();
    }

    public function getAllFk($field) {
        // Map foreign key fields to their respective tables
        $fkMap = [
            "tts_connector_id"     => "core_tts_connector",
            "itt_connector_id"     => "core_itt_connector",
            "diary_connector_id"   => "core_llm_connector",
            "llm_primary_id"       => "core_llm_connector",
            "llm_secondary_id"     => "core_llm_connector",
            "llm_tertiary_id"      => "core_llm_connector",
            "llm_quaternary_id"    => "core_llm_connector",
            "llm_formatter_id"     => "core_llm_connector"
        ];
    
        if (!array_key_exists($field, $fkMap)) {
            return []; // Unknown field
        }
    
        $table = $fkMap[$field];
        // For LLM-backed fields, prefer connector label; fallback to model if label is empty
        if ($table === 'core_llm_connector') {
            $query = "SELECT id, COALESCE(NULLIF(label,''), model) AS label FROM {$table} ORDER BY id ASC";
        } else {
            $query = "SELECT id, label FROM {$table} ORDER BY id ASC";
        }
        return $GLOBALS["db"]->fetchAll($query);
    }

    public function setOldGlobals($currentProfileData) {

        // Decode metadata and extended_data if available
        
        $ttsConMgr=new TTSConnector();
        $ttsCon=$ttsConMgr->getById($currentProfileData["tts_connector_id"]);

        $GLOBALS["TTS_FUNCTION"]=$ttsCon["driver"];

        $metadata = json_decode($currentProfileData['metadata'] ?? '{}', true);
        if (is_array($metadata)) {
            foreach ($metadata as $key => $value) {
                $GLOBALS[$key] = $value;
            }
        }
        if (isset($currentProfileData["prompt"]))
            $GLOBALS["PROFILE_PROMPT"]=$currentProfileData["prompt"];
        
    }

    public function clone($id) {
        $id = intval($id);
        $original = $this->readOne($id);

        if (!$original) {
            return false; // Original record not found
        }

        unset($original['id']); // Remove the ID to create a new record
        $original['label'] = $original['label'] . ' (Copy)'; // Modify label to indicate it's a clone

        return $this->create($original);
    }
    
}

?>
