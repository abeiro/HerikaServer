<?php

$enginePath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR;

require_once($enginePath . 'lib' . DIRECTORY_SEPARATOR . 'runtime_bootstrap.php');
chimRuntimeBootstrap($enginePath, [
    'load_general_settings' => true,
    'load_player_name' => true,
]);

require_once($enginePath . 'lib' . DIRECTORY_SEPARATOR . 'logger.php');
require_once($enginePath . 'lib' . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'narrator.class.php');
require_once($enginePath . 'lib' . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'player.class.php');
require_once($enginePath . 'lib' . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'core_profiles.class.php');
require_once($enginePath . 'lib' . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'tts_connector.class.php');

const CHIM_PORTABLE_MAX_BYTES = 1048576;

// Portable fields intentionally omit live playthrough data, connector IDs, secrets, and local service URLs.
function chimPortablePlayerFields(): array
{
    return [
        'player_name' => 'string',
        'appearance' => 'string',
        'bio' => 'string',
        'bio_known_by_all' => 'boolean_true_false',
        'speech_style' => 'string',
        'diary_enabled' => 'boolean_10',
        'auto_diary_enabled' => 'boolean_10',
        'auto_diary_wait_enabled' => 'boolean_10',
        'tts_voice_override' => 'string',
        'tts_voice_id_override' => 'string',
        'tts_language_override' => 'string',
        'tts_elevenlabs_model_id' => 'string',
        'tts_elevenlabs_speed' => 'string',
        'tts_elevenlabs_stability' => 'string',
        'tts_elevenlabs_similarity_boost' => 'string',
        'tts_elevenlabs_style' => 'string',
        'tts_elevenlabs_use_speaker_boost' => 'string',
        'tts_elevenlabs_v3_audio_tags' => 'string',
    ];
}

function chimPortableGlobalFields(): array
{
    return [
        'PROMPT_HEAD' => 'string',
        'PROMPT_HEAD_MARKDOWN_ENABLED' => 'boolean',
        'EMOTEMOODS' => 'string',
        'RECHAT_MODE' => 'string',
        'ENFORCE_STRICT_RECHAT_RESPONSE' => 'boolean',
        'OGHMA_INFINIUM' => 'boolean',
        'OGHMA_AMOUNT' => 'integer',
        'RACIAL_OGHMA' => 'boolean',
        'LOCATION_OGHMA' => 'boolean',
        'FEATURES@MEMORY_EMBEDDING@ENABLED' => 'boolean',
        'FEATURES@MEMORY_EMBEDDING@USE_TEXT2VEC' => 'boolean',
        'FEATURES@MEMORY_EMBEDDING@AUTO_CREATE_SUMMARY_INTERVAL' => 'integer',
        'PLAYER_WORST_MEMORY_GAME_DAYS' => 'integer',
        'AUTO_LOCK_PROFILE' => 'boolean',
        'AUTOFILL_CUSTOM_PROFILES' => 'boolean',
        'AUTOFILL_CUSTOM_PROFILES_TRIGGER' => 'integer',
        'BGL_TRIGGER_HOURS' => 'number',
        'END_CONVERSATION_COOLDOWN' => 'integer',
        'CHIM_AI_QUEST_PROGRESSION' => 'boolean',
        'CHIM_PLAYER_ONLY_QUEST_ADVANCEMENT' => 'boolean',
        'DETECT_MAGIC_EVENT' => 'boolean',
        'GROUND_ITEMS_DESCRIPTIONS_ONLY' => 'boolean',
        'INVENTORY_ITEMS_DESCRIPTIONS_ONLY' => 'boolean',
        'HIDE_AMBIENT_COMBAT' => 'boolean',
        'DISABLE_REANIMATION_TRACKING' => 'boolean',
        'TRANSFORMATION_DETECTION' => 'boolean',
        'POWER_AWARENESS_ENABLED' => 'boolean',
        'CHIM_ITEM_PICKUP_EVENTLOG_MIN_VALUE' => 'integer',
        'PROMPT_TIMESTAMP' => 'boolean',
        'MAGIC_EVENT_BLACKLIST' => 'string',
        'LOCATION_BLACKLIST' => 'string',
        'ITEM_BLACKLIST' => 'string',
        'EVENT_TYPE_FILTER' => 'string',
        'PROMPT_CONTEXT_OPTIONS' => 'prompt_context',
        'RELATIONSHIP_UPDATE_CHANCE' => 'integer',
        'TRANSLATION_FUNCTION' => 'string',
        'TRANSLATION@settings@translate_audio' => 'boolean',
        'TRANSLATION@settings@translate_text' => 'boolean',
        'TRANSLATION@settings@save_translated_text' => 'boolean',
        'TRANSLATION@settings@translate_player_audio' => 'boolean',
        'TRANSLATION@settings@save_translated_player_text' => 'boolean',
        'TRANSLATION@DeepL@source_language' => 'string',
        'TRANSLATION@DeepL@target_language' => 'string',
        'TRANSLATION@DeepL@player_source_language' => 'string',
        'TRANSLATION@DeepL@player_target_language' => 'string',
        'PLAYER_RESPEECH' => 'boolean',
        'CORE_CONNECTOR_SUMMARY_ENABLED' => 'boolean',
        'CORE_CONNECTOR_MEDIUMTERM_ENABLED' => 'boolean',
        'CORE_CONNECTOR_PROFILES_ENABLED' => 'boolean',
        'CORE_CONNECTOR_DIRECTOR_ENABLED' => 'boolean',
        'CORE_CONNECTOR_BGL_ENABLED' => 'boolean',
        'RELATIONSHIP_SYSTEM_ENABLED' => 'boolean',
        'SCENE_CLASSIFIER_ENABLED' => 'boolean',
        'OGHMA_CUSTOM' => 'boolean',
        'OGHMA_EXTRACTOR_FALLBACK' => 'boolean',
        'OGHMA_EXTRACTOR_TIMEOUT_MS' => 'integer',
        'OGHMA_RESULT_LIMIT' => 'integer',
    ];
}

function chimPortableNarratorFields(): array
{
    return [
        'roleplay_name' => ['type' => 'narrator_name', 'default' => Narrator::DEFAULT_ROLEPLAY_NAME],
        'enabled' => ['type' => 'boolean_10', 'default' => true],
        'welcome_enabled' => ['type' => 'boolean_10', 'default' => false],
        'random_enabled' => ['type' => 'boolean_10', 'default' => false],
        'bored_enabled' => ['type' => 'boolean_10', 'default' => false],
        'books_only_narrator' => ['type' => 'boolean_10', 'default' => false],
        'hide_from_context' => ['type' => 'boolean_10', 'default' => true],
        'inline_narration_mode' => ['type' => 'narration_mode', 'default' => 'disabled'],
        'preserve_asterisks_in_context' => ['type' => 'boolean_10', 'default' => false],
        'remove_asterisks_from_player_input' => ['type' => 'boolean_10', 'default' => true],
        'remove_asterisks_from_npc_output' => ['type' => 'boolean_10', 'default' => true],
        'remove_player_autochat_asterisks' => ['type' => 'boolean_10', 'default' => true],
        'diary_enabled' => ['type' => 'boolean_10', 'default' => false],
        'auto_diary_enabled' => ['type' => 'boolean_10', 'default' => false],
        'only_diary_access' => ['type' => 'boolean_10', 'default' => false],
        'random_chance' => ['type' => 'integer', 'default' => 15, 'min' => 1, 'max' => 100],
        'random_cooldown' => ['type' => 'integer', 'default' => 2, 'min' => 0, 'max' => 10],
        'bored_chance' => ['type' => 'integer', 'default' => 25, 'min' => 1, 'max' => 100],
        'welcome_cooldown' => ['type' => 'integer', 'default' => 10, 'min' => 1, 'max' => 1440],
        'quest_comment_enabled' => ['type' => 'boolean_10', 'default' => false],
        'quest_comment_chance' => ['type' => 'integer', 'default' => 10, 'min' => 1, 'max' => 100],
        'quest_comment_cooldown' => ['type' => 'integer', 'default' => 3, 'min' => 1, 'max' => 60],
        'dynamic_profile' => ['type' => 'boolean_10', 'default' => false],
        'dynamic_profile_fields' => ['type' => 'dynamic_profile_fields', 'default' => []],
        'voiceid' => ['type' => 'string', 'default' => 'TheNarrator'],
        'core' => ['type' => 'string', 'default' => ''],
        'background' => ['type' => 'string', 'default' => ''],
        'personality' => ['type' => 'string', 'default' => ''],
        'speechstyle' => ['type' => 'string', 'default' => ''],
        'goals' => ['type' => 'string', 'default' => ''],
        'oghma_knowledge' => ['type' => 'string', 'default' => 'knowall'],
        'prompt_head' => ['type' => 'string', 'default' => ''],
    ];
}

function chimPortableNarratorPromptKeys(): array
{
    return [
        'dialogue_line_inline_response_narrator',
        'inline_narration_prompt_narrator',
        'dialogue_line_inline_response_npc',
        'inline_narration_prompt_npc',
        'narrator_welcome_prompt',
        'player_speech_style_prompt',
        'random_narration_prompt',
        'narrator_bored_prompt',
        'quest_comment_prompt',
    ];
}

function chimPortableScopeInfo(string $scope): array
{
    $scopes = [
        'player' => ['package_type' => 'chim_player_settings', 'display_name' => 'Player settings'],
        'narration' => ['package_type' => 'chim_narration_settings', 'display_name' => 'Narration settings'],
        'global' => ['package_type' => 'chim_global_settings', 'display_name' => 'Global Settings'],
    ];
    return $scopes[$scope] ?? [];
}

// Reduces an untrusted name to a lowercase, filename-safe slug for use in Content-Disposition.
function chimPortableFilenameSlug($value): string
{
    if (!is_string($value)) {
        return '';
    }
    $slug = trim(strval(preg_replace('/[^a-z0-9]+/', '_', strtolower($value))), '_');
    if (strlen($slug) > 48) {
        $slug = rtrim(substr($slug, 0, 48), '_');
    }
    return $slug;
}

function chimPortableServerVersion(string $enginePath): string
{
    $versionPath = $enginePath . '.version_number.txt';
    $version = is_file($versionPath) ? trim(strval(file_get_contents($versionPath))) : '';
    return $version !== '' ? $version : 'unknown';
}

function chimPortableJsonResponse(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function chimPortableNormalize($value, string $type, bool &$valid)
{
    $valid = true;
    if ($type === 'string') {
        if (is_array($value) || is_object($value)) {
            $valid = false;
            return null;
        }
        return trim(strval($value ?? ''));
    }

    if (in_array($type, ['boolean', 'boolean_10', 'boolean_true_false'], true)) {
        if (is_bool($value)) {
            return $value;
        }
        if (in_array($value, [1, '1', 'true'], true)) {
            return true;
        }
        if (in_array($value, [0, '0', 'false'], true)) {
            return false;
        }
        $valid = false;
        return null;
    }

    if ($type === 'integer') {
        if (!is_numeric($value)) {
            $valid = false;
            return null;
        }
        return intval($value);
    }

    if ($type === 'number') {
        if (!is_numeric($value)) {
            $valid = false;
            return null;
        }
        return floatval($value);
    }

    if ($type === 'prompt_context') {
        if (!is_array($value)) {
            $valid = false;
            return null;
        }
        return chimNormalizePromptContextOptions($value);
    }

    if ($type === 'narrator_name') {
        try {
            return Narrator::normalizeRoleplayName($value);
        } catch (InvalidArgumentException $e) {
            $valid = false;
            return null;
        }
    }

    if ($type === 'narration_mode') {
        $mode = strtolower(trim(strval($value ?? '')));
        if (!in_array($mode, ['disabled', 'narrator', 'npc', 'text_only'], true)) {
            $valid = false;
            return null;
        }
        return $mode;
    }

    if ($type === 'dynamic_profile_fields') {
        if (!is_array($value)) {
            $valid = false;
            return null;
        }
        $allowed = ['personality', 'speechstyle', 'goals'];
        return array_values(array_unique(array_filter($value, static function ($field) use ($allowed) {
            return is_string($field) && in_array($field, $allowed, true);
        })));
    }

    $valid = false;
    return null;
}

function chimPortableTypedGlobalValue(string $name, string $type)
{
    if ($type === 'boolean') {
        return chimGetGeneralSettingBool($name, false);
    }
    if ($type === 'integer') {
        return chimGetGeneralSettingInt($name, 0);
    }
    if ($type === 'number') {
        return chimGetGeneralSettingFloat($name, 0.0);
    }
    if ($type === 'prompt_context') {
        return chimGetPromptContextOptions();
    }
    return chimGetGeneralSetting($name, '');
}

function chimPortableDownload(string $scope, string $enginePath): void
{
    $version = chimPortableServerVersion($enginePath);
    $scopeInfo = chimPortableScopeInfo($scope);
    $export = [
        'package_type' => $scopeInfo['package_type'],
        'export_version' => $version,
        'exported_at' => date('c'),
        'settings' => [],
    ];

    if ($scope === 'player') {
        $player = new Player();
        $allPlayerData = $player->getAll();
        foreach (chimPortablePlayerFields() as $name => $type) {
            $raw = $allPlayerData[$name] ?? '';
            if (in_array($type, ['boolean_10', 'boolean_true_false'], true)) {
                $export['settings'][$name] = in_array(strtolower(trim(strval($raw))), ['1', 'true', 'yes', 'on'], true);
            } else {
                $export['settings'][$name] = strval($raw);
            }
        }

        $connectorId = trim(strval($allPlayerData['tts_connector_id'] ?? ''));
        $export['tts_connector'] = null;
        if ($connectorId !== '') {
            $connector = (new TTSConnector())->readOne($connectorId);
            if (is_array($connector)) {
                $export['tts_connector'] = [
                    'label' => strval($connector['label'] ?? ''),
                    'driver' => strval($connector['driver'] ?? ''),
                ];
            }
        }

        $safeName = trim(strval(preg_replace('/[^a-z0-9_-]+/i', '_', strtolower(trim(strval($allPlayerData['player_name'] ?? 'player'))))), '_');
        $filename = ($safeName !== '' ? $safeName : 'player') . '_player_settings.json';
    } elseif ($scope === 'narration') {
        $narrator = new Narrator();
        $allNarratorData = $narrator->getAll();
        foreach (chimPortableNarratorFields() as $name => $config) {
            $type = $config['type'];
            $raw = array_key_exists($name, $allNarratorData) ? $allNarratorData[$name] : $config['default'];
            if ($type === 'boolean_10') {
                $export['settings'][$name] = is_bool($raw)
                    ? $raw
                    : in_array(strtolower(trim(strval($raw))), ['1', 'true', 'yes', 'on'], true);
            } elseif ($type === 'integer') {
                $export['settings'][$name] = intval($raw);
            } elseif ($type === 'dynamic_profile_fields') {
                $export['settings'][$name] = $narrator->getDynamicProfileFields();
            } else {
                $export['settings'][$name] = strval($raw);
            }
        }

        $export['profile'] = null;
        $profileId = $narrator->getProfileId();
        if ($profileId !== null) {
            $profile = (new CoreProfile())->readOne($profileId);
            if (is_array($profile) && trim(strval($profile['label'] ?? '')) !== '') {
                $export['profile'] = ['label' => strval($profile['label'])];
            }
        }

        $export['prompts'] = array_fill_keys(chimPortableNarratorPromptKeys(), null);
        $promptRows = $GLOBALS['db']->fetchAll(
            'SELECT prompt_key, custom_prompt FROM prompts WHERE prompt_key IN (' .
            implode(', ', array_map([$GLOBALS['db'], 'escapeLiteral'], chimPortableNarratorPromptKeys())) . ')'
        );
        foreach (is_array($promptRows) ? $promptRows : [] as $promptRow) {
            $promptKey = strval($promptRow['prompt_key'] ?? '');
            if (array_key_exists($promptKey, $export['prompts'])) {
                $export['prompts'][$promptKey] = $promptRow['custom_prompt'] === null
                    ? null
                    : strval($promptRow['custom_prompt']);
            }
        }
        $filename = 'chim_narration_settings.json';
    } else {
        foreach (chimPortableGlobalFields() as $name => $type) {
            $export['settings'][$name] = chimPortableTypedGlobalValue($name, $type);
        }
        $presetSlug = chimPortableFilenameSlug($_GET['preset'] ?? '');
        $filename = $presetSlug !== ''
            ? 'chim_global_settings_' . $presetSlug . '.json'
            : 'chim_global_settings.json';
    }

    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store');
    echo json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function chimPortableImport(string $scope, array $export, string $enginePath): array
{
    $scopeInfo = chimPortableScopeInfo($scope);
    $expectedType = $scopeInfo['package_type'];
    if (($export['package_type'] ?? '') !== $expectedType) {
        throw new InvalidArgumentException('This file is not a ' . $scopeInfo['display_name'] . ' export.');
    }

    $exportVersion = trim(strval($export['export_version'] ?? ''));
    if ($exportVersion === '') {
        throw new InvalidArgumentException('The export does not include a server version.');
    }
    if (!isset($export['settings']) || !is_array($export['settings'])) {
        throw new InvalidArgumentException('The export does not contain valid settings.');
    }

    $manifest = $scope === 'player'
        ? chimPortablePlayerFields()
        : ($scope === 'narration' ? chimPortableNarratorFields() : chimPortableGlobalFields());
    $settings = $export['settings'];
    $applied = [];
    $skippedInvalid = [];
    $skippedUnknown = array_values(array_diff(array_keys($settings), array_keys($manifest)));
    $warnings = [];
    $db = $GLOBALS['db'];
    $transactionStarted = false;

    $currentVersion = chimPortableServerVersion($enginePath);
    if ($currentVersion !== 'unknown' && preg_match('/^\d+\.\d+\.\d+/', $exportVersion)) {
        if (version_compare($exportVersion, $currentVersion, '<')) {
            $warnings[] = "Imported settings from older CHIM {$exportVersion}; settings absent from the file were left unchanged.";
        } elseif (version_compare($exportVersion, $currentVersion, '>')) {
            $warnings[] = "Imported settings from newer CHIM {$exportVersion}; unsupported settings were skipped.";
        }
    }

    try {
        if ($db->query('BEGIN') === false) {
            throw new RuntimeException('Could not start settings import transaction.');
        }
        $transactionStarted = true;

        if ($scope === 'player') {
            $player = new Player();
            foreach ($manifest as $name => $type) {
                if (!array_key_exists($name, $settings)) {
                    continue;
                }
                $valid = false;
                $value = chimPortableNormalize($settings[$name], $type, $valid);
                if (!$valid) {
                    $skippedInvalid[] = $name;
                    continue;
                }
                if ($type === 'boolean_true_false') {
                    $value = $value ? 'true' : 'false';
                } elseif ($type === 'boolean_10') {
                    $value = $value ? '1' : '0';
                } else {
                    $value = strval($value);
                }
                if (!$player->set($name, $value)) {
                    throw new RuntimeException("Could not import player setting {$name}.");
                }
                $applied[] = $name;
            }

            if (array_key_exists('tts_connector', $export)) {
                $connectorRef = $export['tts_connector'];
                if ($connectorRef === null) {
                    if (!$player->set('tts_connector_id', '')) {
                        throw new RuntimeException('Could not clear the Player TTS connector.');
                    }
                    $applied[] = 'tts_connector';
                } elseif (is_array($connectorRef)) {
                    $label = trim(strval($connectorRef['label'] ?? ''));
                    $driver = trim(strval($connectorRef['driver'] ?? ''));
                    if ($label !== '' && $driver !== '') {
                        $connector = $db->fetchOne(
                            'SELECT id FROM core_tts_connector WHERE LOWER(label) = LOWER(' . $db->escapeLiteral($label) .
                            ') AND LOWER(driver) = LOWER(' . $db->escapeLiteral($driver) . ') LIMIT 1'
                        );
                        if (is_array($connector) && !empty($connector['id'])) {
                            if (!$player->set('tts_connector_id', strval($connector['id']))) {
                                throw new RuntimeException('Could not import the Player TTS connector.');
                            }
                            $applied[] = 'tts_connector';
                        } else {
                            $warnings[] = "TTS connector '{$label}' was not found; the current Player TTS connector was left unchanged.";
                        }
                    } else {
                        $skippedInvalid[] = 'tts_connector';
                    }
                } else {
                    $skippedInvalid[] = 'tts_connector';
                }
            }
        } elseif ($scope === 'narration') {
            $narrator = new Narrator();
            foreach ($manifest as $name => $config) {
                if (!array_key_exists($name, $settings)) {
                    continue;
                }
                $type = $config['type'];
                $valid = false;
                $value = chimPortableNormalize($settings[$name], $type, $valid);
                if ($valid && $type === 'integer') {
                    $valid = $value >= $config['min'] && $value <= $config['max'];
                }
                if (!$valid) {
                    $skippedInvalid[] = $name;
                    continue;
                }

                if ($name === 'roleplay_name') {
                    $currentRoleplayName = $narrator->getRoleplayName();
                    $playerName = trim(strval($GLOBALS['PLAYER_NAME'] ?? ''));
                    if ($playerName !== '' && strcasecmp($value, $playerName) === 0) {
                        $skippedInvalid[] = $name;
                        $warnings[] = 'Narrator Name matched the player name and was left unchanged.';
                        continue;
                    }
                    if (strcasecmp($value, $currentRoleplayName) !== 0) {
                        $matchingNpc = $db->fetchOne(
                            'SELECT npc_name FROM core_npc_master WHERE LOWER(npc_name) = LOWER(' .
                            $db->escapeLiteral($value) . ') LIMIT 1'
                        );
                        if (is_array($matchingNpc)) {
                            $skippedInvalid[] = $name;
                            $warnings[] = 'Narrator Name matched an existing NPC and was left unchanged.';
                            continue;
                        }
                    }
                }

                if ($type === 'dynamic_profile_fields') {
                    if (!$narrator->setDynamicProfileFields($value)) {
                        throw new RuntimeException('Could not import narrator dynamic profile fields.');
                    }
                } else {
                    $storedValue = $type === 'boolean_10' ? ($value ? '1' : '0') : strval($value);
                    if (!$narrator->set($name, $storedValue)) {
                        throw new RuntimeException("Could not import narrator setting {$name}.");
                    }
                }
                $applied[] = $name;
            }

            if (array_key_exists('profile', $export)) {
                $profileRef = $export['profile'];
                if (is_array($profileRef) && trim(strval($profileRef['label'] ?? '')) !== '') {
                    $profileLabel = trim(strval($profileRef['label']));
                    $profile = $db->fetchOne(
                        'SELECT id FROM core_profiles WHERE LOWER(label) = LOWER(' . $db->escapeLiteral($profileLabel) . ') LIMIT 1'
                    );
                    if (is_array($profile) && !empty($profile['id'])) {
                        if (!$narrator->set('profile_id', strval($profile['id']))) {
                            throw new RuntimeException('Could not import the Narrator profile.');
                        }
                        $applied[] = 'profile';
                    } else {
                        $warnings[] = "Profile '{$profileLabel}' was not found; the current Narrator profile was left unchanged.";
                    }
                } elseif ($profileRef !== null) {
                    $skippedInvalid[] = 'profile';
                }
            }

            if (array_key_exists('prompts', $export)) {
                if (!is_array($export['prompts'])) {
                    $skippedInvalid[] = 'prompts';
                } else {
                    $allowedPromptKeys = chimPortableNarratorPromptKeys();
                    foreach ($export['prompts'] as $promptKey => $customPrompt) {
                        if (!in_array($promptKey, $allowedPromptKeys, true)) {
                            $skippedUnknown[] = 'prompts.' . $promptKey;
                            continue;
                        }
                        if ($customPrompt !== null && !is_string($customPrompt)) {
                            $skippedInvalid[] = 'prompts.' . $promptKey;
                            continue;
                        }
                        $customPrompt = $customPrompt === null || trim($customPrompt) === '' ? null : trim($customPrompt);
                        $customPromptSql = $customPrompt === null ? 'NULL' : $db->escapeLiteral($customPrompt);
                        $updated = $db->execQuery(
                            'UPDATE prompts SET custom_prompt = ' . $customPromptSql .
                            ', updated_at = ' . $db->escapeLiteral(date('Y-m-d H:i:s')) .
                            ' WHERE prompt_key = ' . $db->escapeLiteral($promptKey)
                        );
                        if ($updated === false) {
                            throw new RuntimeException("Could not import narrator prompt {$promptKey}.");
                        }
                        $applied[] = 'prompts.' . $promptKey;
                    }
                }
            }
        } else {
            foreach ($manifest as $name => $type) {
                if (!array_key_exists($name, $settings)) {
                    continue;
                }
                $valid = false;
                $value = chimPortableNormalize($settings[$name], $type, $valid);
                if (!$valid) {
                    $skippedInvalid[] = $name;
                    continue;
                }
                if (!chimSetGeneralSetting($name, $value, null)) {
                    throw new RuntimeException("Could not import global setting {$name}.");
                }
                $applied[] = $name;
            }
        }

        if ($db->query('COMMIT') === false) {
            throw new RuntimeException('Could not commit settings import.');
        }
        $transactionStarted = false;
        if ($scope === 'global') {
            chimLoadGeneralSettingsIntoGlobals();
        } elseif ($scope === 'narration') {
            $narrator->loadIntoGlobals();
        }
    } catch (Throwable $e) {
        if ($transactionStarted) {
            $db->query('ROLLBACK');
        }
        throw $e;
    }

    return [
        'applied' => $applied,
        'skipped_unknown' => $skippedUnknown,
        'skipped_invalid' => array_values(array_unique($skippedInvalid)),
        'warnings' => $warnings,
        'export_version' => $exportVersion,
        'current_version' => $currentVersion,
    ];
}

$scope = strtolower(trim(strval($_GET['scope'] ?? '')));
if (!in_array($scope, ['player', 'narration', 'global'], true)) {
    chimPortableJsonResponse(['ok' => false, 'error' => 'Invalid settings scope.'], 400);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'export') {
    chimPortableDownload($scope, $enginePath);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    chimPortableJsonResponse(['ok' => false, 'error' => 'Unsupported request.'], 405);
}

$rawBody = file_get_contents('php://input');
if (!is_string($rawBody) || $rawBody === '' || strlen($rawBody) > CHIM_PORTABLE_MAX_BYTES) {
    chimPortableJsonResponse(['ok' => false, 'error' => 'Import file is empty or exceeds 1 MB.'], 400);
}

$request = json_decode($rawBody, true);
if (!is_array($request) || !isset($request['export']) || !is_array($request['export'])) {
    chimPortableJsonResponse(['ok' => false, 'error' => 'Invalid JSON import data.'], 400);
}

try {
    $result = chimPortableImport($scope, $request['export'], $enginePath);
    Logger::info('[SETTINGS_IMPORT] Imported ' . count($result['applied']) . " {$scope} settings from CHIM " . $result['export_version']);
    chimPortableJsonResponse(['ok' => true, 'result' => $result]);
} catch (InvalidArgumentException $e) {
    chimPortableJsonResponse(['ok' => false, 'error' => $e->getMessage()], 400);
} catch (Throwable $e) {
    Logger::error('[SETTINGS_IMPORT] ' . $e->getMessage());
    chimPortableJsonResponse(['ok' => false, 'error' => 'Import failed without changing existing settings.'], 500);
}
