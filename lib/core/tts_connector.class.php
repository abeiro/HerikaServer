<?php

class TTSConnector {
    private $table = "core_tts_connector";

    public function create($data) {
        $fields = [
            "driver", "label", "metadata", "api_badge_id", "url", "voice_field"
        ];

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
            "driver", "label", "metadata", "api_badge_id", "url", "voice_field"
        ];
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

    public function getDriver($id) {
        $record = $this->readOne($id);
        if (!$record || !isset($record["driver"])) {
            return null;
        }
        $driver = $record["driver"];
        return new $driver();
    }
}

?>