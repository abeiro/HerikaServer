<?php

class Narrator
{
    private $table = "core_narrator";
    private $db;

    public function __construct()
    {
        if (!isset($GLOBALS["db"])) {
            throw new \Exception("Database connection not initialized. Please ensure \$GLOBALS['db'] is set before instantiating Narrator class.");
        }
        $this->db = $GLOBALS["db"];
    }

    /**
     * Get a single narrator setting value
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
     * Set/update a single narrator setting value
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
     * Get all narrator settings as associative array
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
     * Set multiple narrator settings at once
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
     * Delete a narrator setting
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
     * Escape string for SQL
     * @param string $value The value to escape
     * @return string Escaped value
     */
    private function escape(string $value): string
    {
        return $this->db->escape($value);
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
     * Load all narrator settings into GLOBALS with proper type conversion
     * Falls back to existing GLOBALS values if not found in database
     */
    public function loadIntoGlobals(): void
    {
        $allSettings = $this->getAll();
        
        // Map database keys to GLOBALS keys with type conversion
        $keyMapping = [
            'enabled' => ['NARRATOR_TALKS', 'bool', true],
            'welcome_enabled' => ['NARRATOR_WELCOME', 'bool', false],
            'random_enabled' => ['RANDOM_NARATION', 'bool', false],
            'random_chance' => ['RANDOM_NARATION_CHANCE', 'int', 15],
            'random_cooldown' => ['RANDOM_NARRATION_COOLDOWN', 'int', 2],
            'quest_comment_cooldown' => ['QUEST_COMMENT_COOLDOWN', 'int', 3],
            'books_only_narrator' => ['BOOK_EVENT_ALWAYS_NARRATOR', 'bool', false],
            'hide_from_context' => ['HIDE_NARRATOR_DIALOGUE', 'bool', false],
        ];
        
        foreach ($keyMapping as $dbKey => $config) {
            list($globalKey, $type, $default) = $config;
            
            if (isset($allSettings[$dbKey])) {
                if ($type === 'bool') {
                    $GLOBALS[$globalKey] = filter_var($allSettings[$dbKey], FILTER_VALIDATE_BOOLEAN);
                } elseif ($type === 'int') {
                    $GLOBALS[$globalKey] = intval($allSettings[$dbKey]);
                } else {
                    $GLOBALS[$globalKey] = $allSettings[$dbKey];
                }
            } elseif (!isset($GLOBALS[$globalKey])) {
                // Only set default if GLOBALS doesn't already have a value
                $GLOBALS[$globalKey] = $default;
            }
        }
        
        // Load character fields into HERIKA_* globals
        $this->loadCharacterIntoGlobals();
    }
    
    /**
     * Load narrator character data into GLOBALS (HERIKA_* variables)
     */
    public function loadCharacterIntoGlobals(): void
    {
        $allSettings = $this->getAll();
        
        // Set HERIKA_NAME to The Narrator
        $GLOBALS['HERIKA_NAME'] = 'The Narrator';
        
        // Map character fields to GLOBALS
        // Set HERIKA_PERS from core field (like NPCs do)
        if (isset($allSettings['core']) && $allSettings['core'] !== null && $allSettings['core'] !== '') {
            $GLOBALS['HERIKA_PERS'] = "Roleplay as {$GLOBALS['HERIKA_NAME']}.\n{$allSettings['core']}";
        } else {
            $GLOBALS['HERIKA_PERS'] = "Roleplay as {$GLOBALS['HERIKA_NAME']}";
        }
        
        if (isset($allSettings['background'])) {
            $GLOBALS['HERIKA_BACKGROUND'] = $allSettings['background'];
        }
        
        if (isset($allSettings['personality'])) {
            $GLOBALS['HERIKA_PERSONALITY'] = $allSettings['personality'];
        }
        
        if (isset($allSettings['speechstyle'])) {
            $GLOBALS['HERIKA_SPEECHSTYLE'] = $allSettings['speechstyle'];
        }
        
        if (isset($allSettings['goals'])) {
            $GLOBALS['HERIKA_GOALS'] = $allSettings['goals'];
        }
        
        if (isset($allSettings['oghma_knowledge'])) {
            $GLOBALS['OGHMA_KNOWLEDGE'] = $allSettings['oghma_knowledge'];
        }
        
        if (isset($allSettings['voiceid'])) {
            $GLOBALS['OGHMA_KNOWLEDGE'] = $allSettings['oghma_knowledge'];
        }

        // Override PROMPT_HEAD if narrator has a custom prompt_head (like NPCs do)
        if (isset($allSettings['prompt_head']) && $allSettings['prompt_head'] !== null && $allSettings['prompt_head'] !== '') {
            $GLOBALS['PROMPT_HEAD'] = $allSettings['prompt_head'];
        }

        if (isset($allSettings['voiceid']) && $allSettings['voiceid']) {

            $GLOBALS['TTS']['XTTSFASTAPI']['voiceid']  = $allSettings['voiceid'];
            $GLOBALS['TTS']['MELOTTS']['voiceid']      = $allSettings['voiceid'];
            $GLOBALS['TTS']['MIMIC3']['voice']         = $allSettings['voiceid'];
            $GLOBALS['TTS']['XVASYNTH']['model']       = $allSettings['voiceid'];
            $GLOBALS['TTS']['ZONOS_GRADIO']['voiceid'] = $allSettings['voiceid'];
            $GLOBALS['TTS']['PIPERTTS']['voiceid']     = $allSettings['voiceid'];
            $GLOBALS['TTS']['ELEVEN_LABS']['voice_id'] = $allSettings['voiceid'];
            $GLOBALS['TTS']['AZURE']['voice']          = $allSettings['voiceid'];
            $GLOBALS['TTS']['KOKORO']['voiceid']       = $allSettings['voiceid'];
            $GLOBALS['TTS']['openai']['voice']         = $allSettings['voiceid'];
            $GLOBALS['TTS']['deepgram']['model']       = $allSettings['voiceid'];
            $GLOBALS['TTS']['CARTESIA']['voiceid']     = $allSettings['voiceid'];
            $GLOBALS['TTS']['INWORLD']['voiceid']      = $allSettings['voiceid'];

        }
    }
    
    /**
     * Get the profile_id for the narrator
     * @return int|null The profile ID, or null if not set
     */
    public function getProfileId(): ?int
    {
        $value = $this->get('profile_id');
        if ($value === null) {
            return null;
        }
        return intval($value);
    }
    
    /**
     * Get all narrator data as an array compatible with NpcMaster::getByName format
     * This allows existing code to work with minimal changes
     * @return array Narrator data in NPC format
     */
    public function getNarratorData(): array
    {
        $allSettings = $this->getAll();
        
        return [
            'id' => 1, // Narrator always has ID 1 conceptually
            'npc_name' => 'The Narrator',
            'profile_id' => $this->getProfileId(),
            'voiceid' => $allSettings['voiceid'] ?? 'TheNarrator',
            'core' => $allSettings['core'] ?? '',
            'npc_static_bio' => $allSettings['background'] ?? '',
            'personality' => $allSettings['personality'] ?? '',
            'speechstyle' => $allSettings['speechstyle'] ?? '',
            'goals' => $allSettings['goals'] ?? '',
            'oghma_knowledge_tags' => $allSettings['oghma_knowledge'] ?? 'knowall',
            'gender' => $allSettings['gender'] ?? 'male',
            'prompt_head' => $allSettings['prompt_head'] ?? '',
            'lock_profile' => 1, // Narrator is always locked
            'npc_favorite' => 1, // Narrator is always favorited
            'md5' => md5('The Narrator'),
        ];
    }
    
    /**
     * Set narrator character data from an array (for migration/compatibility)
     * @param array $data Array with keys matching core_narrator fields
     * @return bool Success status
     */
    public function setCharacterData(array $data): bool
    {
        $fieldMapping = [
            'profile_id' => 'profile_id',
            'voiceid' => 'voiceid',
            'core' => 'core',
            'npc_static_bio' => 'background',
            'background' => 'background',
            'personality' => 'personality',
            'speechstyle' => 'speechstyle',
            'goals' => 'goals',
            'oghma_knowledge_tags' => 'oghma_knowledge',
            'oghma_knowledge' => 'oghma_knowledge',
            'gender' => 'gender',
            'prompt_head' => 'prompt_head',
        ];
        
        $success = true;
        foreach ($fieldMapping as $sourceKey => $targetKey) {
            if (isset($data[$sourceKey]) && $data[$sourceKey] !== null && $data[$sourceKey] !== '') {
                if (!$this->set($targetKey, (string)$data[$sourceKey])) {
                    $success = false;
                }
            }
        }
        
        return $success;
    }
}

