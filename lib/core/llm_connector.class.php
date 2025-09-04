<?php
class LLMConnector {

    private $table = "core_llm_connector";

    public function create($data) {
        $fields = [
            "label", "metadata", "url", "model", "provider", "driver", "reasoning_model",
            "max_tokens", "enforce_json", "prefill_json", "api_badge_id", "json_schema",
            "temperature", "presence_penalty", "frequency_penalty", "repetition_penalty",
            "top_p", "top_k", "min_p", "top_a"
        ];

        foreach ($data as $k => $v) {
            if (empty($v)) {
                $data[$k] = null;
            }
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
        $fields = [
            "label", "metadata", "url", "model", "provider", "driver", "reasoning_model",
            "max_tokens", "enforce_json", "prefill_json", "api_badge_id", "json_schema",
            "temperature", "presence_penalty", "frequency_penalty", "repetition_penalty",
            "top_p", "top_k", "min_p", "top_a"
        ];

        foreach ($data as $k => $v) {
            if (empty("$v") && $v!=="0") {
                $data[$k] = null;
            }
        }

        $id = intval($id);
        $where = "id = {$id}";
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

    public function getDriver() {
        $driver = $this->readOne($id)["driver"];
        return new $driver();
    }

    public function getAllFk($fieldName) {
        // Define supported foreign keys and their corresponding table + label fields
        $foreignKeys = [
            "api_badge_id" => ["table" => "core_api_badge", "label_field" => "label"]
        ];

        if (!isset($foreignKeys[$fieldName])) {
            return []; // Unknown FK
        }

        $table = $foreignKeys[$fieldName]["table"];
        $labelField = $foreignKeys[$fieldName]["label_field"];

        $query = "SELECT id, {$labelField} AS label FROM {$table} ORDER BY id ASC";
        return $GLOBALS["db"]->fetchAll($query);
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

    public function setOldGlobals($currentConnectorData) {

        if ($currentConnectorData["driver"] == "openaijson") {

            $apiBadge=new ApiBadge();
            $apiKeyData=$apiBadge->getById($currentConnectorData["api_badge_id"]);
            error_log("[CORE SYSTEM] Using new profile system CONNECTOR openaijson {$currentConnectorData["id"]}");
            $GLOBALS["CONNECTOR"]["openaijson"]["url"] = $currentConnectorData["url"] ?? 'https://openrouter.ai/api/v1/chat/completions';
            $GLOBALS["CONNECTOR"]["openaijson"]["model"] = $currentConnectorData["model"] ?? 'google/gemini-2.0-flash-001';
            
            $GLOBALS["CONNECTOR"]["openaijson"]["reasoning_model"] = $currentConnectorData["reasoning_model"] ?? false;
            $GLOBALS["CONNECTOR"]["openaijson"]["max_tokens"] = $currentConnectorData["max_tokens"] ?? '250';
            $GLOBALS["CONNECTOR"]["openaijson"]["temperature"] = $currentConnectorData["temperature"] ?? 1.05;
            $GLOBALS["CONNECTOR"]["openaijson"]["presence_penalty"] = $currentConnectorData["presence_penalty"] ?? 0;
            $GLOBALS["CONNECTOR"]["openaijson"]["frequency_penalty"] = $currentConnectorData["frequency_penalty"] ?? 0;
            $GLOBALS["CONNECTOR"]["openaijson"]["repetition_penalty"] = $currentConnectorData["repetition_penalty"] ?? 1;
            $GLOBALS["CONNECTOR"]["openaijson"]["top_p"] = $currentConnectorData["top_p"] ?? 0.7;
            $GLOBALS["CONNECTOR"]["openaijson"]["top_k"] = $currentConnectorData["top_k"] ?? 0;
            $GLOBALS["CONNECTOR"]["openaijson"]["min_p"] = $currentConnectorData["min_p"] ?? 0;
            $GLOBALS["CONNECTOR"]["openaijson"]["top_a"] = $currentConnectorData["top_a"] ?? 0;
            $GLOBALS["CONNECTOR"]["openaijson"]["ENFORCE_JSON"] = $currentConnectorData["enforce_json"] ?? true;
            $GLOBALS["CONNECTOR"]["openaijson"]["PREFILL_JSON"] = $currentConnectorData["prefill_json"] ?? false;
            $GLOBALS["CONNECTOR"]["openaijson"]["API_KEY"] = $apiKeyData["api_key"];
            $GLOBALS["CONNECTOR"]["openaijson"]["json_schema"] = $currentConnectorData["json_schema"] ?? true;

             // Decode metadata and extended_data if available
            $metadata = json_decode($currentConnectorData['metadata'] ?? '{}', true);
            if (is_array($metadata)) {
                foreach ($metadata as $key => $value) {
                    $GLOBALS["CONNECTOR"]["openaijson"][$key] = $value;
                }
            }

        } else if ($currentConnectorData["driver"] == "openrouterjson") {

            $apiBadge=new ApiBadge();
            $apiKeyData=$apiBadge->getById($currentConnectorData["api_badge_id"]);
            error_log("[CORE SYSTEM] Using new profile system CONNECTOR openrouterjson {$currentConnectorData["id"]}");
            $GLOBALS["CONNECTOR"]["openrouterjson"]["url"] = $currentConnectorData["url"] ?? 'https://openrouter.ai/api/v1/chat/completions';
            $GLOBALS["CONNECTOR"]["openrouterjson"]["model"] = $currentConnectorData["model"] ?? 'google/gemini-2.0-flash-001';
            $GLOBALS["CONNECTOR"]["openrouterjson"]["PROVIDER"] = $currentConnectorData["provider"] ?? '';
            $GLOBALS["CONNECTOR"]["openrouterjson"]["reasoning_model"] = $currentConnectorData["reasoning_model"] ?? false;
            $GLOBALS["CONNECTOR"]["openrouterjson"]["max_tokens"] = $currentConnectorData["max_tokens"] ?? '250';
            $GLOBALS["CONNECTOR"]["openrouterjson"]["temperature"] = $currentConnectorData["temperature"] ?? 1.05;
            $GLOBALS["CONNECTOR"]["openrouterjson"]["presence_penalty"] = $currentConnectorData["presence_penalty"] ?? 0;
            $GLOBALS["CONNECTOR"]["openrouterjson"]["frequency_penalty"] = $currentConnectorData["frequency_penalty"] ?? 0;
            $GLOBALS["CONNECTOR"]["openrouterjson"]["repetition_penalty"] = $currentConnectorData["repetition_penalty"] ?? 1;
            $GLOBALS["CONNECTOR"]["openrouterjson"]["top_p"] = $currentConnectorData["top_p"] ?? 0.7;
            $GLOBALS["CONNECTOR"]["openrouterjson"]["top_k"] = $currentConnectorData["top_k"] ?? 0;
            $GLOBALS["CONNECTOR"]["openrouterjson"]["min_p"] = $currentConnectorData["min_p"] ?? 0;
            $GLOBALS["CONNECTOR"]["openrouterjson"]["top_a"] = $currentConnectorData["top_a"] ?? 0;
            $GLOBALS["CONNECTOR"]["openrouterjson"]["ENFORCE_JSON"] = $currentConnectorData["enforce_json"] ?? true;
            $GLOBALS["CONNECTOR"]["openrouterjson"]["PREFILL_JSON"] = $currentConnectorData["prefill_json"] ?? false;
            $GLOBALS["CONNECTOR"]["openrouterjson"]["API_KEY"] = $apiKeyData["api_key"];
            $GLOBALS["CONNECTOR"]["openrouterjson"]["json_schema"] = $currentConnectorData["json_schema"] ?? true;

             // Decode metadata and extended_data if available
            $metadata = json_decode($currentConnectorData['metadata'] ?? '{}', true);
            if (is_array($metadata)) {
                foreach ($metadata as $key => $value) {
                    $GLOBALS["CONNECTOR"]["openrouterjson"][$key] = $value;
                }
            }

        } else if ($currentConnectorData["driver"] == "google_openaijson") {

            $apiBadge=new ApiBadge();
            $apiKeyData=$apiBadge->getById($currentConnectorData["api_badge_id"]);
            error_log("[CORE SYSTEM] Using new profile system CONNECTOR google_openaijson {$currentConnectorData["id"]}");
            $GLOBALS["CONNECTOR"]["google_openaijson"]["url"] = $currentConnectorData["url"] ?? 'https://openrouter.ai/api/v1/chat/completions';
            $GLOBALS["CONNECTOR"]["google_openaijson"]["model"] = $currentConnectorData["model"] ?? 'google/gemini-2.0-flash-001';
            $GLOBALS["CONNECTOR"]["google_openaijson"]["PROVIDER"] = $currentConnectorData["provider"] ?? '';
            $GLOBALS["CONNECTOR"]["google_openaijson"]["reasoning_model"] = $currentConnectorData["reasoning_model"] ?? false;
            $GLOBALS["CONNECTOR"]["google_openaijson"]["max_tokens"] = $currentConnectorData["max_tokens"] ?? '250';
            $GLOBALS["CONNECTOR"]["google_openaijson"]["temperature"] = $currentConnectorData["temperature"] ?? 1.05;
            $GLOBALS["CONNECTOR"]["google_openaijson"]["presence_penalty"] = $currentConnectorData["presence_penalty"] ?? 0;
            $GLOBALS["CONNECTOR"]["google_openaijson"]["frequency_penalty"] = $currentConnectorData["frequency_penalty"] ?? 0;
            $GLOBALS["CONNECTOR"]["google_openaijson"]["repetition_penalty"] = $currentConnectorData["repetition_penalty"] ?? 1;
            $GLOBALS["CONNECTOR"]["google_openaijson"]["top_p"] = $currentConnectorData["top_p"] ?? 0.7;
            $GLOBALS["CONNECTOR"]["google_openaijson"]["top_k"] = $currentConnectorData["top_k"] ?? 0;
            $GLOBALS["CONNECTOR"]["google_openaijson"]["min_p"] = $currentConnectorData["min_p"] ?? 0;
            $GLOBALS["CONNECTOR"]["google_openaijson"]["top_a"] = $currentConnectorData["top_a"] ?? 0;
            $GLOBALS["CONNECTOR"]["google_openaijson"]["ENFORCE_JSON"] = $currentConnectorData["enforce_json"] ?? true;
            $GLOBALS["CONNECTOR"]["google_openaijson"]["PREFILL_JSON"] = $currentConnectorData["prefill_json"] ?? false;
            $GLOBALS["CONNECTOR"]["google_openaijson"]["API_KEY"] = $apiKeyData["api_key"];
            $GLOBALS["CONNECTOR"]["google_openaijson"]["json_schema"] = $currentConnectorData["json_schema"] ?? true;

             // Decode metadata and extended_data if available
            $metadata = json_decode($currentConnectorData['metadata'] ?? '{}', true);
            if (is_array($metadata)) {
                foreach ($metadata as $key => $value) {
                    $GLOBALS["CONNECTOR"]["google_openaijson"][$key] = $value;
                }
            }

        }

       

    }



    public function getConnector($currentConnectorData) {

        require_once($GLOBALS["ENGINE_PATH"]."/connector".DIRECTORY_SEPARATOR."{$currentConnectorData["driver"]}.php");
        $connector=new $currentConnectorData["driver"]();
        
        return $connector;

        
    }

}

?>