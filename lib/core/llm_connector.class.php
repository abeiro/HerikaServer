<?php

require_once __DIR__ . DIRECTORY_SEPARATOR . "api_badge.class.php";

if (!function_exists('chimConnectorExtraParametersEnabled')) {
    function chimConnectorExtraParametersEnabled(array $connector): bool
    {
        if (!array_key_exists('extra_parameters_enabled', $connector)) {
            return true;
        }

        $value = $connector['extra_parameters_enabled'];

        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return ((int)$value) !== 0;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            if ($normalized === '' || $normalized === '0' || $normalized === 'false' || $normalized === 'off' || $normalized === 'no') {
                return false;
            }

            return true;
        }

        return (bool)$value;
    }
}

if (!function_exists('chimGetEnabledConnectorExtraParameters')) {
    function chimGetEnabledConnectorExtraParameters(array $connector): array
    {
        if (!chimConnectorExtraParametersEnabled($connector)) {
            return [];
        }

        if (!isset($connector['extra_parameters']) || !is_array($connector['extra_parameters'])) {
            return [];
        }

        return $connector['extra_parameters'];
    }
}

class LLMConnector
{

    private $table = "core_llm_connector";

    private function normalizeConnectorUrlValue($value)
    {
        if (!is_string($value)) {
            return $value;
        }

        $trimmed = trim($value);
        return $trimmed === "" ? null : $trimmed;
    }

    private function normalizeConnectorRecord(array $data, bool $forWrite = false): array
    {
        if (array_key_exists("url", $data)) {
            $normalizedUrl = $this->normalizeConnectorUrlValue($data["url"]);
            if (!$forWrite && $normalizedUrl === null) {
                $normalizedUrl = "";
            }
            $data["url"] = $normalizedUrl;
        }

        return $data;
    }

    private function normalizeConnectorRecords(array $rows): array
    {
        foreach ($rows as $index => $row) {
            if (is_array($row)) {
                $rows[$index] = $this->normalizeConnectorRecord($row);
            }
        }

        return $rows;
    }

    private function getApiKeyForBadge($apiBadgeId): string
    {
        if (empty($apiBadgeId)) {
            return "";
        }

        $apiBadge = new ApiBadge();
        $apiKeyData = $apiBadge->getById($apiBadgeId);
        if (!is_array($apiKeyData)) {
            return "";
        }

        return (string)($apiKeyData["api_key"] ?? "");
    }
    public function create($data)
    {
        $fields = [
            "label",
            "metadata",
            "url",
            "model",
            "provider",
            "driver",
            "service",
            "reasoning_model",
            "max_tokens",
            "enforce_json",
            "prefill_json",
            "api_badge_id",
            "json_schema",
            "temperature",
            "presence_penalty",
            "frequency_penalty",
            "repetition_penalty",
            "top_p",
            "top_k",
            "min_p",
            "top_a"
        ];

        $data = $this->normalizeConnectorRecord($data, true);

        foreach ($data as $k => $v) {
            if (($v === "" || $v === null) && $v !== "0" && $v !== false && $v !== 0) {
                $data[$k] = null;
            }
        }

        $filtered = array_intersect_key($data, array_flip($fields));
        return $GLOBALS["db"]->insertReturningId($this->table, $filtered);
    }

    public function readAll()
    {
        $query = "SELECT * FROM {$this->table} ORDER BY LOWER(COALESCE(NULLIF(label,''), model)) ASC";
        return $this->normalizeConnectorRecords($GLOBALS["db"]->fetchAll($query));
    }

    public function readOne($id)
    {
        $id = intval($id);
        $query = "SELECT * FROM {$this->table} WHERE id = {$id} LIMIT 1";
        $data = $GLOBALS["db"]->fetchOne($query);

        if (!is_array($data)) {
            return $data;
        }

        return $this->normalizeConnectorRecord($data);
    }

    public function getById($id)
    {
        return $this->readOne($id);
    }

    public function update($id, $data)
    {
        $fields = [
            "label",
            "metadata",
            "url",
            "model",
            "provider",
            "driver",
            "service",
            "reasoning_model",
            "max_tokens",
            "enforce_json",
            "prefill_json",
            "api_badge_id",
            "json_schema",
            "temperature",
            "presence_penalty",
            "frequency_penalty",
            "repetition_penalty",
            "top_p",
            "top_k",
            "min_p",
            "top_a"
        ];

        $data = $this->normalizeConnectorRecord($data, true);

        foreach ($data as $k => $v) {
            if (($v === "" || $v === null) && $v !== "0" && $v !== false && $v !== 0) {
                $data[$k] = null;
            }
        }

        $id = intval($id);
        $where = "id = {$id}";
        $filtered = array_intersect_key($data, array_flip($fields));
        return $GLOBALS["db"]->updateRow($this->table, $filtered, $where);
    }

    public function delete($id)
    {
        $id = intval($id);
        return $GLOBALS["db"]->delete($this->table, "id = {$id}");
    }

    public function truncate($restart = false, $cascade = false)
    {
        return $GLOBALS["db"]->truncate($this->table, $restart, $cascade);
    }

    public function getLastError()
    {
        return $GLOBALS["db"]->GetLastError();
    }

    public function getDriver($id)
    {
        $driver = $this->readOne($id)["driver"];
        return new $driver();
    }

    public function getAllFk($fieldName)
    {
        // Define supported foreign keys and their corresponding table + label fields
        $foreignKeys = [
            "api_badge_id" => ["table" => "core_api_badge", "label_field" => "label"]
        ];

        if (!isset($foreignKeys[$fieldName])) {
            return []; // Unknown FK
        }

        $table = $foreignKeys[$fieldName]["table"];
        $labelField = $foreignKeys[$fieldName]["label_field"];

        $query = "SELECT id, {$labelField} AS label FROM {$table} ORDER BY LOWER({$labelField}) ASC";
        return $GLOBALS["db"]->fetchAll($query);
    }

    public function clone($id)
    {
        $id = intval($id);
        $original = $this->readOne($id);

        if (!$original) {
            return false; // Original record not found
        }

        unset($original['id']); // Remove the ID to create a new record
        $original['label'] = $original['label'] . ' (Copy)'; // Modify label to indicate it's a clone

        return $this->create($original);
    }

    public function setOldGlobals($currentConnectorData)
    {
        if (is_array($currentConnectorData)) {
            $currentConnectorData = $this->normalizeConnectorRecord($currentConnectorData);
        }

        if ($currentConnectorData["driver"] == "openaijson") {

            $apiKey = $this->getApiKeyForBadge($currentConnectorData["api_badge_id"] ?? null);
            // error_log("[CORE SYSTEM] Using new profile system CONNECTOR openaijson {$currentConnectorData["id"]} {$currentConnectorData["driver"]}/{$currentConnectorData["model"]}");
            $GLOBALS["CONNECTOR"]["openaijson"]["url"] = $currentConnectorData["url"] ?? 'https://openrouter.ai/api/v1/chat/completions';
            $GLOBALS["CONNECTOR"]["openaijson"]["model"] = $currentConnectorData["model"] ?? 'google/gemini-2.5-flash';

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
            $GLOBALS["CONNECTOR"]["openaijson"]["API_KEY"] = $apiKey;
            $GLOBALS["CONNECTOR"]["openaijson"]["json_schema"] = $currentConnectorData["json_schema"] ?? false;

            // Decode metadata and extended_data if available
            $metadata = json_decode($currentConnectorData['metadata'] ?? '{}', true);
            if (is_array($metadata)) {
                foreach ($metadata as $key => $value) {
                    $GLOBALS["CONNECTOR"]["openaijson"][$key] = $value;
                }
            }

        } else if ($currentConnectorData["driver"] == "openrouterjson") {

            $apiKey = $this->getApiKeyForBadge($currentConnectorData["api_badge_id"] ?? null);
            // error_log("[CORE SYSTEM] Using new profile system CONNECTOR openaijson {$currentConnectorData["id"]} {$currentConnectorData["driver"]}/{$currentConnectorData["model"]}");
            $GLOBALS["CONNECTOR"]["openrouterjson"]["url"] = $currentConnectorData["url"] ?? 'https://openrouter.ai/api/v1/chat/completions';
            $GLOBALS["CONNECTOR"]["openrouterjson"]["model"] = $currentConnectorData["model"] ?? 'deepseek/deepseek-v4-flash';
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
            $GLOBALS["CONNECTOR"]["openrouterjson"]["API_KEY"] = $apiKey;
            $GLOBALS["CONNECTOR"]["openrouterjson"]["json_schema"] = $currentConnectorData["json_schema"] ?? false;

            // Decode metadata and extended_data if available
            $metadata = json_decode($currentConnectorData['metadata'] ?? '{}', true);
            if (is_array($metadata)) {
                foreach ($metadata as $key => $value) {
                    $GLOBALS["CONNECTOR"]["openrouterjson"][$key] = $value;
                }
            }

        } else if ($currentConnectorData["driver"] == "google_openaijson") {

            $apiKey = $this->getApiKeyForBadge($currentConnectorData["api_badge_id"] ?? null);
            // error_log("[CORE SYSTEM] Using new profile system CONNECTOR openaijson {$currentConnectorData["id"]} {$currentConnectorData["driver"]}/{$currentConnectorData["model"]}");
            $GLOBALS["CONNECTOR"]["google_openaijson"]["url"] = $currentConnectorData["url"] ?? 'https://openrouter.ai/api/v1/chat/completions';
            $GLOBALS["CONNECTOR"]["google_openaijson"]["model"] = $currentConnectorData["model"] ?? 'google/gemini-2.5-flash';
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
            $GLOBALS["CONNECTOR"]["google_openaijson"]["API_KEY"] = $apiKey;
            $GLOBALS["CONNECTOR"]["google_openaijson"]["json_schema"] = $currentConnectorData["json_schema"] ?? false;

            // Decode metadata and extended_data if available
            $metadata = json_decode($currentConnectorData['metadata'] ?? '{}', true);
            if (is_array($metadata)) {
                foreach ($metadata as $key => $value) {
                    $GLOBALS["CONNECTOR"]["google_openaijson"][$key] = $value;
                }
            }

        } else if ($currentConnectorData["driver"] == "groqjson") {

            $apiKey = $this->getApiKeyForBadge($currentConnectorData["api_badge_id"] ?? null);
            // error_log("[CORE SYSTEM] Using new profile system CONNECTOR groqjson {$currentConnectorData["id"]} {$currentConnectorData["driver"]}/{$currentConnectorData["model"]}");
            $GLOBALS["CONNECTOR"]["groqjson"]["url"] = $currentConnectorData["url"] ?? 'https://api.groq.com/openai/v1/chat/completions';
            $GLOBALS["CONNECTOR"]["groqjson"]["model"] = $currentConnectorData["model"] ?? 'llama-3.3-70b-versatile';
            $GLOBALS["CONNECTOR"]["groqjson"]["reasoning_model"] = $currentConnectorData["reasoning_model"] ?? false;
            $GLOBALS["CONNECTOR"]["groqjson"]["max_tokens"] = $currentConnectorData["max_tokens"] ?? '1024';
            $GLOBALS["CONNECTOR"]["groqjson"]["temperature"] = $currentConnectorData["temperature"] ?? 1.0;
            $GLOBALS["CONNECTOR"]["groqjson"]["presence_penalty"] = $currentConnectorData["presence_penalty"] ?? 0.0;
            $GLOBALS["CONNECTOR"]["groqjson"]["frequency_penalty"] = $currentConnectorData["frequency_penalty"] ?? 0.0;
            $GLOBALS["CONNECTOR"]["groqjson"]["repetition_penalty"] = $currentConnectorData["repetition_penalty"] ?? 1.0;
            $GLOBALS["CONNECTOR"]["groqjson"]["top_p"] = $currentConnectorData["top_p"] ?? 1.0;
            $GLOBALS["CONNECTOR"]["groqjson"]["top_k"] = $currentConnectorData["top_k"] ?? 0.0;
            $GLOBALS["CONNECTOR"]["groqjson"]["min_p"] = $currentConnectorData["min_p"] ?? 0.0;
            $GLOBALS["CONNECTOR"]["groqjson"]["top_a"] = $currentConnectorData["top_a"] ?? 0.0;
            $GLOBALS["CONNECTOR"]["groqjson"]["ENFORCE_JSON"] = $currentConnectorData["enforce_json"] ?? true;
            $GLOBALS["CONNECTOR"]["groqjson"]["PREFILL_JSON"] = $currentConnectorData["prefill_json"] ?? false;
            $GLOBALS["CONNECTOR"]["groqjson"]["API_KEY"] = $apiKey;
            $GLOBALS["CONNECTOR"]["groqjson"]["json_schema"] = false; // Force disabled for Groq - not supported on most models

            // Decode metadata and extended_data if available
            $metadata = json_decode($currentConnectorData['metadata'] ?? '{}', true);
            if (is_array($metadata)) {
                foreach ($metadata as $key => $value) {
                    $GLOBALS["CONNECTOR"]["groqjson"][$key] = $value;
                }
            }

        } else if ($currentConnectorData["driver"] == "player2json") {

            $gameKey = "CHIM";
            if (!empty($currentConnectorData["api_badge_id"])) {
                $apiKey = $this->getApiKeyForBadge($currentConnectorData["api_badge_id"] ?? null);
                if (!empty($apiKey)) {
                    $gameKey = $apiKey;
                }
            }

            $GLOBALS["CONNECTOR"]["player2json"]["url"] = $currentConnectorData["url"] ?? 'http://127.0.0.1:4315/v1/chat/completions';
            $GLOBALS["CONNECTOR"]["player2json"]["model"] = $currentConnectorData["model"] ?? '';
            $GLOBALS["CONNECTOR"]["player2json"]["reasoning_model"] = $currentConnectorData["reasoning_model"] ?? false;
            $GLOBALS["CONNECTOR"]["player2json"]["max_tokens"] = $currentConnectorData["max_tokens"] ?? '1024';
            $GLOBALS["CONNECTOR"]["player2json"]["temperature"] = $currentConnectorData["temperature"] ?? 1.0;
            $GLOBALS["CONNECTOR"]["player2json"]["presence_penalty"] = $currentConnectorData["presence_penalty"] ?? 0.0;
            $GLOBALS["CONNECTOR"]["player2json"]["frequency_penalty"] = $currentConnectorData["frequency_penalty"] ?? 0.0;
            $GLOBALS["CONNECTOR"]["player2json"]["repetition_penalty"] = $currentConnectorData["repetition_penalty"] ?? 1.0;
            $GLOBALS["CONNECTOR"]["player2json"]["top_p"] = $currentConnectorData["top_p"] ?? 1.0;
            $GLOBALS["CONNECTOR"]["player2json"]["top_k"] = $currentConnectorData["top_k"] ?? 0.0;
            $GLOBALS["CONNECTOR"]["player2json"]["min_p"] = $currentConnectorData["min_p"] ?? 0.0;
            $GLOBALS["CONNECTOR"]["player2json"]["top_a"] = $currentConnectorData["top_a"] ?? 0.0;
            $GLOBALS["CONNECTOR"]["player2json"]["ENFORCE_JSON"] = $currentConnectorData["enforce_json"] ?? true;
            $GLOBALS["CONNECTOR"]["player2json"]["PREFILL_JSON"] = $currentConnectorData["prefill_json"] ?? false;
            $GLOBALS["CONNECTOR"]["player2json"]["API_KEY"] = $gameKey;
            $GLOBALS["CONNECTOR"]["player2json"]["player2_game_key"] = $gameKey;
            $GLOBALS["CONNECTOR"]["player2json"]["json_schema"] = $currentConnectorData["json_schema"] ?? false;

            $metadata = json_decode($currentConnectorData['metadata'] ?? '{}', true);
            if (is_array($metadata)) {
                foreach ($metadata as $key => $value) {
                    $GLOBALS["CONNECTOR"]["player2json"][$key] = $value;
                }
            }

        }

    }


    public function getConnector($currentConnectorData)
    {

        if (!isset($currentConnectorData["driver"]) || empty($currentConnectorData["driver"])) {
            throw new \Exception("Invalid connector data: missing or empty 'driver' key");
        }

        require_once($GLOBALS["ENGINE_PATH"] . "/connector" . DIRECTORY_SEPARATOR . "{$currentConnectorData["driver"]}.php");
        $connector = new $currentConnectorData["driver"]();

        $GLOBALS["PATCH_PROMPT_ENFORCE_ACTIONS"] = false;
        $GLOBALS["COMMAND_PROMPT_ENFORCE_ACTIONS"] = "";
        error_log("[CORE SYSTEM] Action enforcement prompt hard-disabled for connector ID {$currentConnectorData["id"]} ({$currentConnectorData["driver"]}/{$currentConnectorData["model"]})");
        return $connector;

    }

}

?>
