<?php

class LLMConnector {

    private $table = "core_llm_connector";

    public function create($data) {
        $fields = [
            "label", "metadata", "url", "model", "provider", "driver", "service", "reasoning_model",
            "max_tokens", "enforce_json", "prefill_json", "api_badge_id", "json_schema",
            "temperature", "presence_penalty", "frequency_penalty", "repetition_penalty",
            "top_p", "top_k", "min_p", "top_a"
        ];

        foreach ($data as $k => $v) {
            if (empty("$v") && $v !== "0") {
                $data[$k] = null;
            }
        }

        // JSON encode metadata if it's an array
        if (isset($data['metadata']) && is_array($data['metadata'])) {
            $data['metadata'] = json_encode($data['metadata']);
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
            "label", "metadata", "url", "model", "provider", "driver", "service", "reasoning_model",
            "max_tokens", "enforce_json", "prefill_json", "api_badge_id", "json_schema",
            "temperature", "presence_penalty", "frequency_penalty", "repetition_penalty",
            "top_p", "top_k", "min_p", "top_a"
        ];

        // DEBUG: Log metadata before processing
        error_log("[LLM UPDATE DEBUG] Received metadata: " . var_export($data['metadata'] ?? 'NOT SET', true));

        foreach ($data as $k => $v) {
            if (empty("$v") && $v!=="0") {
                $data[$k] = null;
            }
        }

        // DEBUG: Log metadata after empty check
        error_log("[LLM UPDATE DEBUG] After empty check: " . var_export($data['metadata'] ?? 'NOT SET', true));

        // JSON encode metadata if it's an array
        if (isset($data['metadata']) && is_array($data['metadata'])) {
            $data['metadata'] = json_encode($data['metadata']);
        }

        // DEBUG: Log final metadata value
        error_log("[LLM UPDATE DEBUG] Final metadata to save: " . var_export($data['metadata'] ?? 'NOT SET', true));

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
            error_log("[CORE SYSTEM] Using new profile system CONNECTOR openaijson {$currentConnectorData["id"]} {$currentConnectorData["driver"]}/{$currentConnectorData["model"]}");
            $GLOBALS["CONNECTOR"]["openaijson"]["url"] = $currentConnectorData["url"] ?? 'https://openrouter.ai/api/v1/chat/completions';
            $GLOBALS["CONNECTOR"]["openaijson"]["model"] = $currentConnectorData["model"] ?? 'google/gemini-2.0-flash-001';
            
            $GLOBALS["CONNECTOR"]["openaijson"]["reasoning_model"] = $currentConnectorData["reasoning_model"] ?? false;
            $GLOBALS["CONNECTOR"]["openaijson"]["max_tokens"] = $currentConnectorData["max_tokens"] ?? '1024';
            $GLOBALS["CONNECTOR"]["openaijson"]["temperature"] = $currentConnectorData["temperature"] ?? 1.0;
            $GLOBALS["CONNECTOR"]["openaijson"]["presence_penalty"] = $currentConnectorData["presence_penalty"] ?? 0.0;
            $GLOBALS["CONNECTOR"]["openaijson"]["frequency_penalty"] = $currentConnectorData["frequency_penalty"] ?? 0.0;
            $GLOBALS["CONNECTOR"]["openaijson"]["repetition_penalty"] = $currentConnectorData["repetition_penalty"] ?? 1.0;
            $GLOBALS["CONNECTOR"]["openaijson"]["top_p"] = $currentConnectorData["top_p"] ?? 1.0;
            $GLOBALS["CONNECTOR"]["openaijson"]["top_k"] = $currentConnectorData["top_k"] ?? 0.0;
            $GLOBALS["CONNECTOR"]["openaijson"]["min_p"] = $currentConnectorData["min_p"] ?? 0.0;
            $GLOBALS["CONNECTOR"]["openaijson"]["top_a"] = $currentConnectorData["top_a"] ?? 0.0;
            $GLOBALS["CONNECTOR"]["openaijson"]["ENFORCE_JSON"] = $currentConnectorData["enforce_json"] ?? true;
            $GLOBALS["CONNECTOR"]["openaijson"]["PREFILL_JSON"] = $currentConnectorData["prefill_json"] ?? false;
            $GLOBALS["CONNECTOR"]["openaijson"]["API_KEY"] = $apiKeyData["api_key"];
            $GLOBALS["CONNECTOR"]["openaijson"]["json_schema"] = $currentConnectorData["json_schema"] ?? false;

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
            error_log("[CORE SYSTEM] Using new profile system CONNECTOR openaijson {$currentConnectorData["id"]} {$currentConnectorData["driver"]}/{$currentConnectorData["model"]}");
            $GLOBALS["CONNECTOR"]["openrouterjson"]["url"] = $currentConnectorData["url"] ?? 'https://openrouter.ai/api/v1/chat/completions';
            $GLOBALS["CONNECTOR"]["openrouterjson"]["model"] = $currentConnectorData["model"] ?? 'google/gemini-2.0-flash-001';
            $GLOBALS["CONNECTOR"]["openrouterjson"]["PROVIDER"] = $currentConnectorData["provider"] ?? '';
            $GLOBALS["CONNECTOR"]["openrouterjson"]["reasoning_model"] = $currentConnectorData["reasoning_model"] ?? false;
            $GLOBALS["CONNECTOR"]["openrouterjson"]["max_tokens"] = $currentConnectorData["max_tokens"] ?? '1024';
            $GLOBALS["CONNECTOR"]["openrouterjson"]["temperature"] = $currentConnectorData["temperature"] ?? 1.0;
            $GLOBALS["CONNECTOR"]["openrouterjson"]["presence_penalty"] = $currentConnectorData["presence_penalty"] ?? 0.0;
            $GLOBALS["CONNECTOR"]["openrouterjson"]["frequency_penalty"] = $currentConnectorData["frequency_penalty"] ?? 0.0;
            $GLOBALS["CONNECTOR"]["openrouterjson"]["repetition_penalty"] = $currentConnectorData["repetition_penalty"] ?? 1.0;
            $GLOBALS["CONNECTOR"]["openrouterjson"]["top_p"] = $currentConnectorData["top_p"] ?? 1.0;
            $GLOBALS["CONNECTOR"]["openrouterjson"]["top_k"] = $currentConnectorData["top_k"] ?? 0.0;
            $GLOBALS["CONNECTOR"]["openrouterjson"]["min_p"] = $currentConnectorData["min_p"] ?? 0.0;
            $GLOBALS["CONNECTOR"]["openrouterjson"]["top_a"] = $currentConnectorData["top_a"] ?? 0.0;
            $GLOBALS["CONNECTOR"]["openrouterjson"]["ENFORCE_JSON"] = $currentConnectorData["enforce_json"] ?? true;
            $GLOBALS["CONNECTOR"]["openrouterjson"]["PREFILL_JSON"] = $currentConnectorData["prefill_json"] ?? false;
            $GLOBALS["CONNECTOR"]["openrouterjson"]["API_KEY"] = $apiKeyData["api_key"];
            $GLOBALS["CONNECTOR"]["openrouterjson"]["json_schema"] = $currentConnectorData["json_schema"] ?? false;

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
            error_log("[CORE SYSTEM] Using new profile system CONNECTOR openaijson {$currentConnectorData["id"]} {$currentConnectorData["driver"]}/{$currentConnectorData["model"]}");
            $GLOBALS["CONNECTOR"]["google_openaijson"]["url"] = $currentConnectorData["url"] ?? 'https://openrouter.ai/api/v1/chat/completions';
            $GLOBALS["CONNECTOR"]["google_openaijson"]["model"] = $currentConnectorData["model"] ?? 'google/gemini-2.0-flash-001';
            $GLOBALS["CONNECTOR"]["google_openaijson"]["PROVIDER"] = $currentConnectorData["provider"] ?? '';
            $GLOBALS["CONNECTOR"]["google_openaijson"]["reasoning_model"] = $currentConnectorData["reasoning_model"] ?? false;
            $GLOBALS["CONNECTOR"]["google_openaijson"]["max_tokens"] = $currentConnectorData["max_tokens"] ?? '1024';
            $GLOBALS["CONNECTOR"]["google_openaijson"]["temperature"] = $currentConnectorData["temperature"] ?? 1.0;
            $GLOBALS["CONNECTOR"]["google_openaijson"]["presence_penalty"] = $currentConnectorData["presence_penalty"] ?? 0.0;
            $GLOBALS["CONNECTOR"]["google_openaijson"]["frequency_penalty"] = $currentConnectorData["frequency_penalty"] ?? 0.0;
            $GLOBALS["CONNECTOR"]["google_openaijson"]["repetition_penalty"] = $currentConnectorData["repetition_penalty"] ?? 1.0;
            $GLOBALS["CONNECTOR"]["google_openaijson"]["top_p"] = $currentConnectorData["top_p"] ?? 1.0;
            $GLOBALS["CONNECTOR"]["google_openaijson"]["top_k"] = $currentConnectorData["top_k"] ?? 0.0;
            $GLOBALS["CONNECTOR"]["google_openaijson"]["min_p"] = $currentConnectorData["min_p"] ?? 0.0;
            $GLOBALS["CONNECTOR"]["google_openaijson"]["top_a"] = $currentConnectorData["top_a"] ?? 0.0;
            $GLOBALS["CONNECTOR"]["google_openaijson"]["ENFORCE_JSON"] = $currentConnectorData["enforce_json"] ?? true;
            $GLOBALS["CONNECTOR"]["google_openaijson"]["PREFILL_JSON"] = $currentConnectorData["prefill_json"] ?? false;
            $GLOBALS["CONNECTOR"]["google_openaijson"]["API_KEY"] = $apiKeyData["api_key"];
            $GLOBALS["CONNECTOR"]["google_openaijson"]["json_schema"] = $currentConnectorData["json_schema"] ?? false;

             // Decode metadata and extended_data if available
            $metadata = json_decode($currentConnectorData['metadata'] ?? '{}', true);
            if (is_array($metadata)) {
                foreach ($metadata as $key => $value) {
                    $GLOBALS["CONNECTOR"]["google_openaijson"][$key] = $value;
                }
            }

        } else if ($currentConnectorData["driver"] == "openrouterjsoncached") {

            $apiBadge=new ApiBadge();
            $apiKeyData=$apiBadge->getById($currentConnectorData["api_badge_id"]);
            error_log("[CORE SYSTEM] Using new profile system CONNECTOR openrouterjsoncached {$currentConnectorData["id"]} {$currentConnectorData["driver"]}/{$currentConnectorData["model"]}");
            $GLOBALS["CONNECTOR"]["openrouterjsoncached"]["url"] = $currentConnectorData["url"] ?? 'https://openrouter.ai/api/v1/chat/completions';
            $GLOBALS["CONNECTOR"]["openrouterjsoncached"]["model"] = $currentConnectorData["model"] ?? 'anthropic/claude-3-haiku-20240307';
            $GLOBALS["CONNECTOR"]["openrouterjsoncached"]["PROVIDER"] = $currentConnectorData["provider"] ?? 'Anthropic';
            $GLOBALS["CONNECTOR"]["openrouterjsoncached"]["reasoning_model"] = $currentConnectorData["reasoning_model"] ?? false;
            $GLOBALS["CONNECTOR"]["openrouterjsoncached"]["max_tokens"] = $currentConnectorData["max_tokens"] ?? '4096';
            $GLOBALS["CONNECTOR"]["openrouterjsoncached"]["temperature"] = $currentConnectorData["temperature"] ?? 1.0;
            $GLOBALS["CONNECTOR"]["openrouterjsoncached"]["presence_penalty"] = $currentConnectorData["presence_penalty"] ?? 0;
            $GLOBALS["CONNECTOR"]["openrouterjsoncached"]["frequency_penalty"] = $currentConnectorData["frequency_penalty"] ?? 0;
            $GLOBALS["CONNECTOR"]["openrouterjsoncached"]["repetition_penalty"] = $currentConnectorData["repetition_penalty"] ?? 1;
            $GLOBALS["CONNECTOR"]["openrouterjsoncached"]["top_p"] = $currentConnectorData["top_p"] ?? 1.0;
            $GLOBALS["CONNECTOR"]["openrouterjsoncached"]["top_k"] = $currentConnectorData["top_k"] ?? 0;
            $GLOBALS["CONNECTOR"]["openrouterjsoncached"]["min_p"] = $currentConnectorData["min_p"] ?? 0;
            $GLOBALS["CONNECTOR"]["openrouterjsoncached"]["top_a"] = $currentConnectorData["top_a"] ?? 0;
            $GLOBALS["CONNECTOR"]["openrouterjsoncached"]["API_KEY"] = $apiKeyData["api_key"];

            // Decode metadata and extended_data if available
            // Metadata should contain caching-specific settings like:
            // provider_caching, response_format, include_*, dialogue_cache_uncached_count, etc.
            $metadata = json_decode($currentConnectorData['metadata'] ?? '{}', true);
            if (is_array($metadata)) {
                foreach ($metadata as $key => $value) {
                    $GLOBALS["CONNECTOR"]["openrouterjsoncached"][$key] = $value;
                }
            }

        } else if ($currentConnectorData["driver"] == "openrouterjsoncached_verbose") {

            $apiBadge=new ApiBadge();
            $apiKeyData=$apiBadge->getById($currentConnectorData["api_badge_id"]);
            error_log("[CORE SYSTEM] Using new profile system CONNECTOR openrouterjsoncached_verbose {$currentConnectorData["id"]} {$currentConnectorData["driver"]}/{$currentConnectorData["model"]}");
            $GLOBALS["CONNECTOR"]["openrouterjsoncached_verbose"]["url"] = $currentConnectorData["url"] ?? 'https://openrouter.ai/api/v1/chat/completions';
            $GLOBALS["CONNECTOR"]["openrouterjsoncached_verbose"]["model"] = $currentConnectorData["model"] ?? 'anthropic/claude-3-haiku-20240307';
            $GLOBALS["CONNECTOR"]["openrouterjsoncached_verbose"]["PROVIDER"] = $currentConnectorData["provider"] ?? 'Anthropic';
            $GLOBALS["CONNECTOR"]["openrouterjsoncached_verbose"]["reasoning_model"] = $currentConnectorData["reasoning_model"] ?? false;
            $GLOBALS["CONNECTOR"]["openrouterjsoncached_verbose"]["max_tokens"] = $currentConnectorData["max_tokens"] ?? '4096';
            $GLOBALS["CONNECTOR"]["openrouterjsoncached_verbose"]["temperature"] = $currentConnectorData["temperature"] ?? 1.0;
            $GLOBALS["CONNECTOR"]["openrouterjsoncached_verbose"]["presence_penalty"] = $currentConnectorData["presence_penalty"] ?? 0;
            $GLOBALS["CONNECTOR"]["openrouterjsoncached_verbose"]["frequency_penalty"] = $currentConnectorData["frequency_penalty"] ?? 0;
            $GLOBALS["CONNECTOR"]["openrouterjsoncached_verbose"]["repetition_penalty"] = $currentConnectorData["repetition_penalty"] ?? 1;
            $GLOBALS["CONNECTOR"]["openrouterjsoncached_verbose"]["top_p"] = $currentConnectorData["top_p"] ?? 1.0;
            $GLOBALS["CONNECTOR"]["openrouterjsoncached_verbose"]["top_k"] = $currentConnectorData["top_k"] ?? 0;
            $GLOBALS["CONNECTOR"]["openrouterjsoncached_verbose"]["min_p"] = $currentConnectorData["min_p"] ?? 0;
            $GLOBALS["CONNECTOR"]["openrouterjsoncached_verbose"]["top_a"] = $currentConnectorData["top_a"] ?? 0;
            $GLOBALS["CONNECTOR"]["openrouterjsoncached_verbose"]["API_KEY"] = $apiKeyData["api_key"];

            // Decode metadata and extended_data if available
            // Metadata should contain caching-specific settings like:
            // provider_caching, response_format, include_*, dialogue_cache_uncached_count, verbose_logging, etc.
            $metadata = json_decode($currentConnectorData['metadata'] ?? '{}', true);
            if (is_array($metadata)) {
                foreach ($metadata as $key => $value) {
                    $GLOBALS["CONNECTOR"]["openrouterjsoncached_verbose"][$key] = $value;
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