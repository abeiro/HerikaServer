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

    public function setOldGlobals($currentTTSData) {
        if (!$currentTTSData) {
            return;
        }

        // Set TTS driver
        if (isset($currentTTSData["driver"])) {
            $GLOBALS["TTS_FUNCTION"] = $currentTTSData["driver"];
        }

        // Decode and apply metadata
        $metadata = json_decode($currentTTSData['metadata'] ?? '{}', true);
        if (is_array($metadata)) {
            // Set TTS-specific configuration
            $driver = $currentTTSData["driver"];
            if (!isset($GLOBALS["TTS"][$driver])) {
                $GLOBALS["TTS"][$driver] = [];
            }

            foreach ($metadata as $key => $value) {
                if (!empty($value) || $value === 0 || $value === false) {
                    // CRITICAL FIX: If language is an array (schema definition), use default value instead
                    if ($key === "language" && is_array($value)) {
                        // Use default language based on driver
                        if ($driver === "XTTSFASTAPI" || $driver === "CHATTERBOX" || $driver === "POCKETTTS") {
                            $value = "en";
                        } elseif ($driver === "MELOTTS") {
                            $value = "EN";
                        } else {
                            $value = "en"; // Generic fallback
                        }
                        error_log("[CORE] TTS Connector language was schema array for {$driver}, set to default: {$value}");
                    }
                    
                    // Store in driver-specific TTS config
                    $GLOBALS["TTS"][$driver][$key] = $value;
                    
                    // If language is set, also set LLM_LANG for language-aware responses
                    if ($key === "language" && isset($GLOBALS["LANG_LLM_XTTS"]) && $GLOBALS["LANG_LLM_XTTS"]) {
                        // Sanitize language code
                        $GLOBALS["LLM_LANG"] = preg_replace('/[^a-z\-]/i', '', strtolower(trim($value)));
                        error_log("[CORE] TTS Connector set GLOBALS[LLM_LANG] = {$GLOBALS["LLM_LANG"]}");
                    }
                }
            }
        }

        // Handle URL and voice_field if present
        if (isset($currentTTSData["url"])) {
            $driver = $currentTTSData["driver"];
            if (!isset($GLOBALS["TTS"][$driver])) {
                $GLOBALS["TTS"][$driver] = [];
            }
            $GLOBALS["TTS"][$driver]["endpoint"] = $currentTTSData["url"];
        }
    }
}

?>