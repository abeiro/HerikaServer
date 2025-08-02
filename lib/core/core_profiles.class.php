<?php

class CoreProfile {
    private $table = "core_profiles";

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
            "metadata"
        ];

        foreach ($data as $k=>$v)
            if (empty($v))
                $data[$k]=null;

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
            "metadata"
        ];

        foreach ($data as $k=>$v)
            if (empty($v))
                $data[$k]=null;

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
            "llm_quaternary_id"    => "core_llm_connector"
        ];
    
        if (!array_key_exists($field, $fkMap)) {
            return []; // Unknown field
        }
    
        $table = $fkMap[$field];
        $query = "SELECT id, label FROM {$table} ORDER BY id ASC";
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
        

        
    }
    
}

?>
