<?php

class Narrator
{
    public const CANONICAL_NAME = 'The Narrator';
    public const DEFAULT_ROLEPLAY_NAME = self::CANONICAL_NAME;

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
     * Normalize and validate the prompt-facing narrator name.
     */
    public static function normalizeRoleplayName($value): string
    {
        $name = preg_replace('/\s+/u', ' ', trim((string)$value));
        if ($name === '') {
            return self::DEFAULT_ROLEPLAY_NAME;
        }

        $length = function_exists('mb_strlen') ? mb_strlen($name, 'UTF-8') : strlen($name);
        if ($length > 64) {
            throw new \InvalidArgumentException('Narrator roleplay name must be 64 characters or fewer.');
        }

        if (preg_match("/^[\\p{L}\\p{M}\\p{N} .'’\\-]+$/u", $name) !== 1) {
            throw new \InvalidArgumentException('Narrator roleplay name may only contain letters, numbers, spaces, apostrophes, periods, and hyphens.');
        }

        if (in_array(strtolower($name), ['player', 'everyone'], true)) {
            throw new \InvalidArgumentException("Narrator roleplay name cannot be '{$name}'.");
        }

        return $name;
    }

    public function getRoleplayName(): string
    {
        try {
            return self::normalizeRoleplayName($this->get('roleplay_name'));
        } catch (\InvalidArgumentException $e) {
            return self::DEFAULT_ROLEPLAY_NAME;
        }
    }

    /**
     * Read a legacy boolean value from default narrator profile metadata.
     * Returns null when the key is missing or not parseable as boolean.
     */
    private function getLegacyDefaultNarratorProfileBool(string $legacyKey): ?bool
    {
        $query = "SELECT metadata FROM core_profiles WHERE default_narrator = '1' ORDER BY id ASC LIMIT 1";
        try {
            $result = $this->db->fetchOne($query);
        } catch (\Throwable $e) {
            return null;
        }
        if (!$result || !isset($result['metadata']) || $result['metadata'] === null || $result['metadata'] === '') {
            return null;
        }

        $metadata = json_decode($result['metadata'], true);
        if (!is_array($metadata) || !array_key_exists($legacyKey, $metadata)) {
            return null;
        }

        $value = $metadata[$legacyKey];
        if (is_bool($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return intval($value) !== 0;
        }
        if (is_string($value)) {
            if (strcasecmp($value, 'true') === 0 || $value === '1') {
                return true;
            }
            if (strcasecmp($value, 'false') === 0 || $value === '0') {
                return false;
            }
        }

        return null;
    }

    /**
     * Load all narrator settings into GLOBALS with proper type conversion
     * Falls back to existing GLOBALS values if not found in database
     */
    public function loadIntoGlobals(): void
    {
        $allSettings = $this->getAll();

        if (!isset($allSettings['inline_narration_mode'])) {
            $legacyInlineNarrationMode = null;
            $currentGlobalMode = strtolower(trim((string)($GLOBALS['INLINE_NARRATION_MODE'] ?? '')));
            if (in_array($currentGlobalMode, ['disabled', 'narrator', 'npc', 'text_only'], true)) {
                $legacyInlineNarrationMode = $currentGlobalMode;
            } else {
                $legacyInlineNarrationEnabled = null;
                if (isset($allSettings['inline_narration_enabled'])) {
                    $legacyInlineNarrationEnabled = filter_var($allSettings['inline_narration_enabled'], FILTER_VALIDATE_BOOLEAN);
                } else {
                    $legacyInlineNarrationEnabled = $this->getLegacyDefaultNarratorProfileBool('INLINE_NARRATION_ENABLED');
                    if ($legacyInlineNarrationEnabled === null && isset($GLOBALS['INLINE_NARRATION_ENABLED'])) {
                        $legacyInlineNarrationEnabled = (bool)$GLOBALS['INLINE_NARRATION_ENABLED'];
                    }
                }

                if ($legacyInlineNarrationEnabled !== null) {
                    $legacyInlineNarrationMode = $legacyInlineNarrationEnabled ? 'narrator' : 'disabled';
                }
            }

            if ($legacyInlineNarrationMode !== null && $this->set('inline_narration_mode', $legacyInlineNarrationMode)) {
                $allSettings['inline_narration_mode'] = $legacyInlineNarrationMode;
            }
        }

        if (!isset($allSettings['remove_asterisks_from_npc_output'])) {
            $legacyNpcOutputSetting = null;
            if (isset($allSettings['remove_asterisks_from_output'])) {
                $legacyNpcOutputSetting = filter_var($allSettings['remove_asterisks_from_output'], FILTER_VALIDATE_BOOLEAN);
            } else {
                $legacyNpcOutputSetting = $this->getLegacyDefaultNarratorProfileBool('REMOVE_ASTERISKS_FROM_OUTPUT');
                if ($legacyNpcOutputSetting === null) {
                    if (isset($GLOBALS['REMOVE_ASTERISKS_FROM_NPC_OUTPUT'])) {
                        $legacyNpcOutputSetting = (bool)$GLOBALS['REMOVE_ASTERISKS_FROM_NPC_OUTPUT'];
                    } elseif (isset($GLOBALS['REMOVE_ASTERISKS_FROM_OUTPUT'])) {
                        $legacyNpcOutputSetting = (bool)$GLOBALS['REMOVE_ASTERISKS_FROM_OUTPUT'];
                    }
                }
            }

            if ($legacyNpcOutputSetting !== null) {
                $serialized = $legacyNpcOutputSetting ? '1' : '0';
                if ($this->set('remove_asterisks_from_npc_output', $serialized)) {
                    $allSettings['remove_asterisks_from_npc_output'] = $serialized;
                }
            }
        }

        if (!isset($allSettings['remove_asterisks_from_player_input'])) {
            $legacyPlayerInputSetting = null;
            if (isset($GLOBALS['REMOVE_ASTERISKS_FROM_PLAYER_INPUT'])) {
                $legacyPlayerInputSetting = (bool)$GLOBALS['REMOVE_ASTERISKS_FROM_PLAYER_INPUT'];
            } elseif (isset($allSettings['remove_asterisks_from_output'])) {
                $legacyPlayerInputSetting = filter_var($allSettings['remove_asterisks_from_output'], FILTER_VALIDATE_BOOLEAN);
            } elseif (isset($GLOBALS['REMOVE_ASTERISKS_FROM_OUTPUT'])) {
                $legacyPlayerInputSetting = (bool)$GLOBALS['REMOVE_ASTERISKS_FROM_OUTPUT'];
            }

            if ($legacyPlayerInputSetting === null) {
                $legacyPlayerInputSetting = true;
            }

            $serialized = $legacyPlayerInputSetting ? '1' : '0';
            if ($this->set('remove_asterisks_from_player_input', $serialized)) {
                $allSettings['remove_asterisks_from_player_input'] = $serialized;
            }
        }

        if (!isset($allSettings['remove_player_autochat_asterisks'])) {
            $legacyRemovePlayerAutochatAsterisks = null;
            if (isset($allSettings['player_autochat_asterisks_enabled'])) {
                $legacyRemovePlayerAutochatAsterisks = !filter_var($allSettings['player_autochat_asterisks_enabled'], FILTER_VALIDATE_BOOLEAN);
            } elseif (isset($GLOBALS['REMOVE_PLAYER_AUTOCHAT_ASTERISKS'])) {
                $legacyRemovePlayerAutochatAsterisks = (bool)$GLOBALS['REMOVE_PLAYER_AUTOCHAT_ASTERISKS'];
            } elseif (isset($GLOBALS['PLAYER_AUTOCHAT_ASTERISKS_ENABLED'])) {
                $legacyRemovePlayerAutochatAsterisks = !(bool)$GLOBALS['PLAYER_AUTOCHAT_ASTERISKS_ENABLED'];
            }

            if ($legacyRemovePlayerAutochatAsterisks === null) {
                $legacyRemovePlayerAutochatAsterisks = true;
            }

            $serialized = $legacyRemovePlayerAutochatAsterisks ? '1' : '0';
            if ($this->set('remove_player_autochat_asterisks', $serialized)) {
                $allSettings['remove_player_autochat_asterisks'] = $serialized;
            }
        }
        
        // Map database keys to GLOBALS keys with type conversion
        $keyMapping = [
            'roleplay_name' => ['NARRATOR_ROLEPLAY_NAME', 'string', self::DEFAULT_ROLEPLAY_NAME],
            'enabled' => ['NARRATOR_TALKS', 'bool', true],
            'welcome_enabled' => ['NARRATOR_WELCOME', 'bool', false],
            'random_enabled' => ['RANDOM_NARATION', 'bool', false],
            'random_chance' => ['RANDOM_NARATION_CHANCE', 'int', 15],
            'random_cooldown' => ['RANDOM_NARRATION_COOLDOWN', 'int', 2],
            'bored_enabled' => ['ALLOW_NARRATOR_BORED_EVENTS', 'bool', false],
            'bored_chance' => ['ALLOW_NARRATOR_BORED_EVENTS_CHANCE', 'int', 25],
            'quest_comment_cooldown' => ['QUEST_COMMENT_COOLDOWN', 'int', 3],
            'books_only_narrator' => ['BOOK_EVENT_ALWAYS_NARRATOR', 'bool', false],
            'hide_from_context' => ['HIDE_NARRATOR_DIALOGUE', 'bool', true],
            'dynamic_profile' => ['DYNAMIC_PROFILE', 'bool', false],
            'inline_narration_mode' => ['INLINE_NARRATION_MODE', 'string', isset($GLOBALS['INLINE_NARRATION_MODE']) ? $GLOBALS['INLINE_NARRATION_MODE'] : 'disabled'],
            'remove_player_autochat_asterisks' => [
                'REMOVE_PLAYER_AUTOCHAT_ASTERISKS',
                'bool',
                isset($GLOBALS['REMOVE_PLAYER_AUTOCHAT_ASTERISKS']) ? (bool)$GLOBALS['REMOVE_PLAYER_AUTOCHAT_ASTERISKS'] : (isset($GLOBALS['PLAYER_AUTOCHAT_ASTERISKS_ENABLED']) ? !(bool)$GLOBALS['PLAYER_AUTOCHAT_ASTERISKS_ENABLED'] : true),
            ],
            'preserve_asterisks_in_context' => ['PRESERVE_ASTERISKS_IN_CONTEXT', 'bool', false],
            'remove_asterisks_from_player_input' => [
                'REMOVE_ASTERISKS_FROM_PLAYER_INPUT',
                'bool',
                isset($GLOBALS['REMOVE_ASTERISKS_FROM_PLAYER_INPUT']) ? (bool)$GLOBALS['REMOVE_ASTERISKS_FROM_PLAYER_INPUT'] : (isset($GLOBALS['REMOVE_ASTERISKS_FROM_OUTPUT']) ? (bool)$GLOBALS['REMOVE_ASTERISKS_FROM_OUTPUT'] : true),
            ],
            'remove_asterisks_from_npc_output' => [
                'REMOVE_ASTERISKS_FROM_NPC_OUTPUT',
                'bool',
                isset($GLOBALS['REMOVE_ASTERISKS_FROM_NPC_OUTPUT']) ? (bool)$GLOBALS['REMOVE_ASTERISKS_FROM_NPC_OUTPUT'] : (isset($GLOBALS['REMOVE_ASTERISKS_FROM_OUTPUT']) ? (bool)$GLOBALS['REMOVE_ASTERISKS_FROM_OUTPUT'] : true),
            ],
            'diary_enabled' => ['NARRATOR_DIARY_ENABLED', 'bool', false],
            'auto_diary_enabled' => ['NARRATOR_AUTO_DIARY_ENABLED', 'bool', false],
            'only_diary_access' => ['NARRATOR_ONLY_DIARY_ACCESS', 'bool', false],
            'connector_id' => ['NARRATOR_CONNECTOR_ID', 'int', null],
            'diary_connector_id' => ['NARRATOR_DIARY_CONNECTOR_ID', 'int', null],
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

        $inlineNarrationMode = strtolower(trim((string)($GLOBALS['INLINE_NARRATION_MODE'] ?? 'disabled')));
        if (!in_array($inlineNarrationMode, ['disabled', 'narrator', 'npc', 'text_only'], true)) {
            $inlineNarrationMode = 'disabled';
        }
        $GLOBALS['INLINE_NARRATION_MODE'] = $inlineNarrationMode;
        $GLOBALS['INLINE_NARRATION_ENABLED'] = $inlineNarrationMode !== 'disabled';
        $GLOBALS['REMOVE_ASTERISKS_FROM_OUTPUT'] = isset($GLOBALS['REMOVE_ASTERISKS_FROM_NPC_OUTPUT']) ? (bool)$GLOBALS['REMOVE_ASTERISKS_FROM_NPC_OUTPUT'] : true;
        
        // NOTE: Character data (HERIKA_NAME, HERIKA_PERS, PROMPT_HEAD, etc.) is NOT loaded here.
        // loadCharacterIntoGlobals() should only be called when The Narrator is confirmed
        // as the active speaker (in main.php profile loading or book override sections).
        // Loading it here would overwrite global PROMPT_HEAD before profile selection.
    }
    
    /**
     * Load narrator character data into GLOBALS (HERIKA_* variables)
     */
    public function loadCharacterIntoGlobals(): void
    {
        $allSettings = $this->getAll();
        
        // Routing always uses the canonical name; prompts may use the roleplay alias.
        $GLOBALS['HERIKA_NAME'] = self::CANONICAL_NAME;
        $GLOBALS['NARRATOR_ROLEPLAY_NAME'] = $this->getRoleplayName();
        $GLOBALS['HERIKA_ROLEPLAY_NAME'] = $GLOBALS['NARRATOR_ROLEPLAY_NAME'];
        $promptName = $GLOBALS['NARRATOR_ROLEPLAY_NAME'];
        
        // Map character fields to GLOBALS
        // Set HERIKA_PERS from core field (like NPCs do)
        if (isset($allSettings['core']) && $allSettings['core'] !== null && $allSettings['core'] !== '') {
            $GLOBALS['HERIKA_PERS'] = "Roleplay as {$promptName}.\n" . chimRenderNarratorRoleplayText($allSettings['core']);
        } else {
            $GLOBALS['HERIKA_PERS'] = "Roleplay as {$promptName}";
        }
        
        if (isset($allSettings['background'])) {
            $GLOBALS['HERIKA_BACKGROUND'] = chimRenderNarratorRoleplayText($allSettings['background']);
        }
        
        if (isset($allSettings['personality'])) {
            $GLOBALS['HERIKA_PERSONALITY'] = chimRenderNarratorRoleplayText($allSettings['personality']);
        }
        
        if (isset($allSettings['speechstyle'])) {
            $GLOBALS['HERIKA_SPEECHSTYLE'] = chimRenderNarratorRoleplayText($allSettings['speechstyle']);
        }
        
        if (isset($allSettings['goals'])) {
            $GLOBALS['HERIKA_GOALS'] = chimRenderNarratorRoleplayText($allSettings['goals']);
        }
        
        if (isset($allSettings['oghma_knowledge'])) {
            $GLOBALS['OGHMA_KNOWLEDGE'] = $allSettings['oghma_knowledge'];
        }
        
        if (isset($allSettings['voiceid'])) {
            $GLOBALS['OGHMA_KNOWLEDGE'] = $allSettings['oghma_knowledge'];
        }

        // Override PROMPT_HEAD if narrator has a custom prompt_head (like NPCs do)
        if (isset($allSettings['prompt_head']) && $allSettings['prompt_head'] !== null && $allSettings['prompt_head'] !== '') {
            $GLOBALS['PROMPT_HEAD'] = chimRenderNarratorRoleplayText($allSettings['prompt_head']);
        }

        if (isset($allSettings['voiceid']) && $allSettings['voiceid']) {
            $GLOBALS['PATCH_OVERRIDE_VOICE']          = $allSettings['voiceid'];

            $GLOBALS['TTS']['XTTSFASTAPI']['voiceid']  = $allSettings['voiceid'];
            $GLOBALS['TTS']['CHATTERBOX']['voiceid']   = $allSettings['voiceid'];
            $GLOBALS['TTS']['POCKETTTS']['voiceid']    = $allSettings['voiceid'];
            $GLOBALS['TTS']['OMNIVOICE']['voiceid']    = $allSettings['voiceid'];
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

        } else {
            unset($GLOBALS['PATCH_OVERRIDE_VOICE']);
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
            'npc_name' => self::CANONICAL_NAME,
            'roleplay_name' => $this->getRoleplayName(),
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
            'md5' => md5(self::CANONICAL_NAME),
            'dynamic_profile' => $this->getBool('dynamic_profile', false) ? 1 : 0,
        ];
    }
    
    /**
     * Get dynamic profile fields array
     * @return array Array of field names to update dynamically
     */
    public function getDynamicProfileFields(): array
    {
        $value = $this->get('dynamic_profile_fields');
        if ($value === null || $value === '') {
            return [];
        }
        
        $decoded = json_decode($value, true);
        if (!is_array($decoded)) {
            return [];
        }
        
        return $decoded;
    }
    
    /**
     * Set dynamic profile fields array
     * @param array $fields Array of field names (personality, speechstyle, goals)
     * @return bool Success status
     */
    public function setDynamicProfileFields(array $fields): bool
    {
        $validFields = ['personality', 'speechstyle', 'goals'];
        $filtered = array_intersect($fields, $validFields);
        $json = json_encode(array_values($filtered));
        return $this->set('dynamic_profile_fields', $json);
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
            'roleplay_name' => 'roleplay_name',
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

if (!function_exists('chimGetNarratorRoleplayName')) {
    function chimGetNarratorRoleplayName(): string
    {
        try {
            return Narrator::normalizeRoleplayName($GLOBALS['NARRATOR_ROLEPLAY_NAME'] ?? Narrator::DEFAULT_ROLEPLAY_NAME);
        } catch (\InvalidArgumentException $e) {
            return Narrator::DEFAULT_ROLEPLAY_NAME;
        }
    }
}

if (!function_exists('chimGetNarratorDisplayNameHeaderValue')) {
    function chimGetNarratorDisplayNameHeaderValue(): string
    {
        return base64_encode(chimGetNarratorRoleplayName());
    }
}

if (!function_exists('chimBuildNarratorContextLine')) {
    function chimBuildNarratorContextLine($text): string
    {
        return chimGetNarratorRoleplayName() . ': ' . ltrim((string)$text);
    }
}

if (!function_exists('chimGetPromptCharacterName')) {
    function chimGetPromptCharacterName(): string
    {
        $canonicalName = trim((string)($GLOBALS['HERIKA_NAME'] ?? ''));
        if ($canonicalName !== '' && strcasecmp($canonicalName, Narrator::CANONICAL_NAME) !== 0) {
            return $canonicalName;
        }

        return chimGetNarratorRoleplayName();
    }
}

if (!function_exists('chimRenderNarratorRoleplayText')) {
    function chimRenderNarratorRoleplayText($text): string
    {
        $text = (string)$text;
        $roleplayName = chimGetNarratorRoleplayName();
        if (strcasecmp($roleplayName, Narrator::CANONICAL_NAME) === 0) {
            return $text;
        }

        return str_ireplace(Narrator::CANONICAL_NAME, $roleplayName, $text);
    }
}

if (!function_exists('chimRenderNarratorContextText')) {
    function chimRenderNarratorContextText($text): string
    {
        return chimRenderNarratorRoleplayText($text);
    }
}

if (!function_exists('chimApplyNarratorRoleplayNameToContext')) {
    function chimApplyNarratorRoleplayNameToContext(array $messages): array
    {
        foreach ($messages as &$message) {
            if (is_array($message) && array_key_exists('content', $message) && is_string($message['content'])) {
                $message['content'] = chimRenderNarratorContextText($message['content']);
            }
        }
        unset($message);

        return $messages;
    }
}

if (!function_exists('chimNormalizeNarratorRoleplayActorName')) {
    function chimNormalizeNarratorRoleplayActorName($name): string
    {
        $name = trim((string)$name);
        $roleplayName = chimGetNarratorRoleplayName();
        if ($name !== '' && strcasecmp($name, $roleplayName) === 0) {
            return Narrator::CANONICAL_NAME;
        }

        return $name;
    }
}

