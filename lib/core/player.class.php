<?php

class Player
{
    private $table = "core_player";
    private $db;

    public function __construct()
    {
        if (!isset($GLOBALS["db"])) {
            throw new \Exception("Database connection not initialized. Please ensure \$GLOBALS['db'] is set before instantiating Player class.");
        }
        $this->db = $GLOBALS["db"];
    }

    /**
     * Get a single player setting value
     * @param string $key The setting key
     * @return string|null The value, or null if not found
     */
    public function get(string $key): ?string
    {
        $escaped = $this->escape($key);
        $query = "SELECT value FROM {$this->table} WHERE id = '{$escaped}' LIMIT 1";
        $result = $this->db->fetchOne($query);
        
        if ($result && isset($result['value'])) {
            return $result['value'];
        }
        
        return null;
    }

    /**
     * Set/update a single player setting value
     * @param string $key The setting key
     * @param string $value The value to set
     * @return bool Success status
     */
    public function set(string $key, string $value): bool
    {
        $escaped_key = $this->escape($key);
        $escaped_value = $this->escape($value);
        
        // Check if key exists
        $exists = $this->get($key);
        
        if ($exists !== null) {
            // Update existing
            $query = "UPDATE {$this->table} SET value = '{$escaped_value}' WHERE id = '{$escaped_key}'";
        } else {
            // Insert new
            $query = "INSERT INTO {$this->table} (id, value) VALUES ('{$escaped_key}', '{$escaped_value}')";
        }
        
        $result = $this->db->query($query);
        return $result !== false;
    }

    /**
     * Get all player settings as associative array
     * @return array Associative array of key => value
     */
    public function getAll(): array
    {
        $query = "SELECT id, value FROM {$this->table}";
        $results = $this->db->fetchAll($query);
        
        $data = [];
        if (is_array($results)) {
            foreach ($results as $row) {
                if (isset($row['id']) && isset($row['value'])) {
                    $data[$row['id']] = $row['value'];
                }
            }
        }
        
        return $data;
    }

    /**
     * Set multiple player settings at once
     * @param array $data Associative array of key => value pairs
     * @return bool Success status
     */
    public function setMultiple(array $data): bool
    {
        $success = true;
        foreach ($data as $key => $value) {
            if (!$this->set($key, $value)) {
                $success = false;
            }
        }
        return $success;
    }

    /**
     * Delete a player setting
     * @param string $key The setting key to delete
     * @return bool Success status
     */
    public function delete(string $key): bool
    {
        $escaped = $this->escape($key);
        $query = "DELETE FROM {$this->table} WHERE id = '{$escaped}'";
        $result = $this->db->query($query);
        return $result !== false;
    }

    /**
     * Check if a key exists
     * @param string $key The setting key
     * @return bool True if exists, false otherwise
     */
    public function exists(string $key): bool
    {
        return $this->get($key) !== null;
    }

    /**
     * Get a value and parse it as boolean
     * @param string $key The setting key
     * @param bool $default Default value if not found
     * @return bool The boolean value
     */
    public function getBool(string $key, bool $default = false): bool
    {
        $value = $this->get($key);
        if ($value === null) {
            return $default;
        }
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Get a value and parse it as integer
     * @param string $key The setting key
     * @param int $default Default value if not found
     * @return int The integer value
     */
    public function getInt(string $key, int $default = 0): int
    {
        $value = $this->get($key);
        if ($value === null) {
            return $default;
        }
        return intval($value);
    }

    /**
     * Escape string for SQL
     * @param string $value The value to escape
     * @return string Escaped value
     */
    private function escape(string $value): string
    {
        return $this->db->escape($value);
    }

    /**
     * Get a value and decode it as JSON
     * @param string $key The setting key
     * @return array|null The decoded JSON data, or null if not found or invalid
     */
    public function getJson(string $key): ?array
    {
        $value = $this->get($key);
        if ($value === null) {
            return null;
        }
        
        $decoded = json_decode($value, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }
        
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Encode data as JSON and store it
     * @param string $key The setting key
     * @param array $data The data to encode and store
     * @return bool Success status
     */
    public function setJson(string $key, array $data): bool
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return false;
        }
        
        return $this->set($key, $json);
    }
}

