<?php

require_once(__DIR__ . DIRECTORY_SEPARATOR . 'tts_fallback.class.php');

class TTSConnector
{
    private $table = "core_tts_connector";

    private static $driverMap = [
        'none' => '',
        'pockettts' => 'POCKETTTS',
        'chatterbox' => 'CHATTERBOX',
        'xtts-fastapi' => 'XTTSFASTAPI',
        'omnivoice' => 'OMNIVOICE',
        'inworld' => 'INWORLD',
        'cartesia' => 'CARTESIA',
        'piper-tts' => 'PIPERTTS',
        'xvasynth' => 'XVASYNTH',
        'melotts' => 'MELOTTS',
        'mimic3' => 'MIMIC3',
        'azure' => 'AZURE',
        '11labs' => 'ELEVEN_LABS',
        'openai' => 'openai',
        'kokoro' => 'KOKORO',
        'koboldcpp' => 'koboldcpp',
        'zonos_gradio' => 'ZONOS_GRADIO',
        'deepgram' => 'deepgram',
    ];

    private static $displayNameMap = [
        'none' => 'Disabled',
        'pockettts' => 'PocketTTS',
        'chatterbox' => 'Chatterbox',
        'xtts-fastapi' => 'XTTS',
        'omnivoice' => 'OmniVoice',
        'inworld' => 'Inworld',
        'cartesia' => 'Cartesia',
        'piper-tts' => 'Piper TTS',
        'xvasynth' => 'xVASynth',
        'melotts' => 'MeloTTS',
        'mimic3' => 'Mimic3',
        'azure' => 'Azure',
        '11labs' => 'ElevenLabs',
        'openai' => 'OpenAI',
        'kokoro' => 'Kokoro',
        'koboldcpp' => 'KoboldCPP',
        'zonos_gradio' => 'Zonos',
        'deepgram' => 'Deepgram',
    ];

    private static $voiceFieldMap = [
        'pockettts' => 'voiceid',
        'chatterbox' => 'voiceid',
        'xtts-fastapi' => 'voiceid',
        'omnivoice' => 'voiceid',
        'inworld' => 'voiceid',
        'cartesia' => 'voiceid',
        'piper-tts' => 'voiceid',
        'xvasynth' => 'model',
        'melotts' => 'voiceid',
        'mimic3' => 'voice',
        'azure' => 'voice',
        '11labs' => 'voice_id',
        'openai' => 'voice',
        'kokoro' => 'voiceid',
        'koboldcpp' => 'voice',
        'zonos_gradio' => 'voiceid',
        'deepgram' => 'model',
    ];

    private static $apiBadgeLabelMap = [
        'AZURE' => 'Azure',
        'ELEVEN_LABS' => 'ElevenLabs',
        'openai' => 'OpenAI',
        'deepgram' => 'Deepgram',
        'CARTESIA' => 'Cartesia',
        'INWORLD' => 'Inworld',
    ];

    private static $localUrlDefaultMap = [
        'pockettts' => 'http://127.0.0.1:8086',
        'chatterbox' => 'http://127.0.0.1:8023',
        'xtts-fastapi' => 'http://127.0.0.1:8020',
        'omnivoice' => 'http://127.0.0.1:8021',
        'piper-tts' => 'http://127.0.0.1:5000',
        'xvasynth' => 'http://192.168.0.1:8008',
        'melotts' => 'http://127.0.0.1:8084',
        'mimic3' => 'http://127.0.0.1:59125',
        'kokoro' => 'http://127.0.0.1:8880',
        'koboldcpp' => 'http://127.0.0.1:5001/api/extra/tts',
        'zonos_gradio' => 'http://127.0.0.1:7860',
    ];

    private static $sharedMetadataDefaultMap = [
        'fallback_male' => 'malenord',
        'fallback_female' => 'femalenord',
    ];

    private static $metadataDefaultMap = [
        'melotts' => [
            'language' => 'EN',
            'speed' => 1.0,
        ],
        'xtts-fastapi' => [
            'language' => 'en',
            'voicelogic' => 'voicetype',
            'PARALINGUISTIC_TAGS_ENABLED' => false,
            'PARALINGUISTIC_TAGS_PROMPT' => '',
            'PARALINGUISTIC_TAGS_LIST' => '[clear throat],[sigh],[shush],[cough],[groan],[sniff],[gasp],[chuckle],[laugh]',
        ],
        'chatterbox' => [
            'language' => 'en',
            'voicelogic' => 'voicetype',
            'PARALINGUISTIC_TAGS_ENABLED' => false,
            'PARALINGUISTIC_TAGS_PROMPT' => '',
            'PARALINGUISTIC_TAGS_LIST' => '[clear throat],[sigh],[shush],[cough],[groan],[sniff],[gasp],[chuckle],[laugh]',
        ],
        'omnivoice' => [
            'language' => '',
            'voicelogic' => 'voicetype',
        ],
        'pockettts' => [
            'language' => 'en',
            'model' => 'pocket-tts',
            'voicelogic' => 'voicetype',
        ],
        'inworld' => [
            'language' => 'en-US',
            'model_id' => 'inworld-tts-2',
            'temperature' => 1.0,
            'speed' => 1.0,
        ],
        'mimic3' => [
            'rate' => 1,
            'volume' => 60,
        ],
        'xvasynth' => [
            'base_lang' => 'en',
            'modelType' => 'xVAPitch',
            'version' => '3.0',
            'game' => 'skyrim',
            'pace' => 1.0,
            'waveglowPath' => 'resources/app/models/waveglow_256channels_universal_v4.pt',
            'vocoder' => 'n/a',
            'distroname' => 'DwemerAI4Skyrim3',
        ],
        'azure' => [
            'fixedMood' => '',
            'region' => 'westeurope',
            'volume' => 20,
            'rate' => 1.25,
            'countour' => '(11%, +15%) (60%, -23%) (80%, -34%)',
            'validMoods' => ['whispering', 'default', 'dazed'],
        ],
        'openai' => [
            'endpoint' => 'https://api.openai.com/v1/audio/speech',
            'model_id' => 'tts-1',
        ],
        '11labs' => [
            'optimize_streaming_latency' => '0',
            'model_id' => 'eleven_monolingual_v1',
            'stability' => 0.75,
            'similarity_boost' => 0.75,
            'style' => 0.0,
            'speed' => 1.0,
            'use_speaker_boost' => true,
            'apply_text_normalization' => 'auto',
            'apply_language_text_normalization' => false,
            'v3_audio_tags' => '',
        ],
    ];

    public function create($data)
    {
        $fields = [
            "driver", "label", "metadata", "api_badge_id", "url", "voice_field",
        ];

        $driver = $data['driver'] ?? '';
        if (array_key_exists('metadata', $data)) {
            $data['metadata'] = $this->canonicalJson(
                $this->applyForcedMetadataDefaults(
                    $driver,
                    $this->stripVoiceMetadataForDriver($driver, $this->decodeMetadata($data['metadata']))
                )
            );
        }
        $data['api_badge_id'] = $this->normalizeApiBadgeIdForDriver($driver, $data['api_badge_id'] ?? null);
        $data['url'] = $this->normalizeUrlForDriver(
            $driver,
            $data['url'] ?? null,
            $this->decodeMetadata($data['metadata'] ?? '{}')
        );

        foreach ($data as $k => $v) {
            if (($v === "" || $v === null) && $v !== "0" && $v !== false && $v !== 0) {
                $data[$k] = null;
            }
        }

        $filtered = array_intersect_key($data, array_flip($fields));
        $columns = [];
        $values = [];
        foreach ($filtered as $column => $value) {
            $columns[] = $column;
            if ($value === null) {
                $values[] = "NULL";
            } else {
                $values[] = $GLOBALS["db"]->escapeLiteral(strval($value));
            }
        }
        $query = "INSERT INTO {$this->table} (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $values) . ") RETURNING id";
        $row = $GLOBALS["db"]->fetchOne($query);
        return isset($row['id']) ? intval($row['id']) : 0;
    }

    public function readAll()
    {
        $query = "SELECT * FROM {$this->table} ORDER BY LOWER(COALESCE(NULLIF(label,''), driver)) ASC, id ASC";
        return $GLOBALS["db"]->fetchAll($query);
    }

    public function readOne($id)
    {
        $id = intval($id);
        $query = "SELECT * FROM {$this->table} WHERE id = {$id} LIMIT 1";
        return $GLOBALS["db"]->fetchOne($query);
    }

    public function getById($id)
    {
        return $this->sanitizeStoredRowVoiceMetadata($this->readOne($id));
    }

    public function update($id, $data)
    {
        $id = intval($id);
        $where = "id = {$id}";
        $fields = [
            "driver", "label", "metadata", "api_badge_id", "url", "voice_field",
        ];
        $existing = $this->readOne($id);
        $driver = $data['driver'] ?? ($existing['driver'] ?? '');

        if (array_key_exists('metadata', $data)) {
            $data['metadata'] = $this->canonicalJson(
                $this->applyForcedMetadataDefaults(
                    $driver,
                    $this->stripVoiceMetadataForDriver($driver, $this->decodeMetadata($data['metadata']))
                )
            );
        }
        $data['api_badge_id'] = $this->normalizeApiBadgeIdForDriver(
            $driver,
            array_key_exists('api_badge_id', $data) ? $data['api_badge_id'] : ($existing['api_badge_id'] ?? null)
        );
        $data['url'] = $this->normalizeUrlForDriver(
            $driver,
            array_key_exists('url', $data) ? $data['url'] : ($existing['url'] ?? null),
            $this->decodeMetadata($data['metadata'] ?? ($existing['metadata'] ?? '{}'))
        );

        foreach ($data as $k => $v) {
            if (($v === "" || $v === null) && $v !== "0" && $v !== false && $v !== 0) {
                $data[$k] = null;
            }
        }

        $filtered = array_intersect_key($data, array_flip($fields));
        $GLOBALS["db"]->updateRow($this->table, $filtered, $where);
        return $id;
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
        $record = $this->readOne($id);
        if (!$record || !isset($record["driver"])) {
            return null;
        }
        $driver = $record["driver"];
        return new $driver();
    }

    public function clone($id)
    {
        $original = $this->readOne($id);
        if (!$original) {
            return 0;
        }

        unset($original['id']);
        $original['label'] = $this->uniqueLabel(trim(strval($original['label'] ?? '')) . ' (Copy)', 0);
        return $this->create($original);
    }

    public function getAllFk($fieldName)
    {
        if ($fieldName !== 'api_badge_id') {
            return [];
        }
        return $GLOBALS["db"]->fetchAll("SELECT id, label FROM core_api_badge ORDER BY LOWER(label) ASC");
    }

    public function getDriverOptions(): array
    {
        $schema = $this->loadRawSchema();
        $values = $schema['TTSFUNCTION']['values'] ?? [];
        if (!is_array($values) || empty($values)) {
            $values = array_keys(self::$driverMap);
        }
        return array_values(array_unique($values));
    }

    public function normalizeDriverValue($driver): string
    {
        return $this->normalizeDriver($driver);
    }

    public function getProviderKeyFromDriver($driver): string
    {
        $driver = $this->normalizeDriver($driver);
        return self::$driverMap[$driver] ?? '';
    }

    public function getDisplayName($driver): string
    {
        $driver = $this->normalizeDriver($driver);
        if (isset(self::$displayNameMap[$driver])) {
            return self::$displayNameMap[$driver];
        }
        return $driver !== '' ? $driver : 'Connector';
    }

    public function getVoiceFieldForDriver($driver): string
    {
        $driver = $this->normalizeDriver($driver);
        return self::$voiceFieldMap[$driver] ?? 'voiceid';
    }

    public function isDriverVoiceMetadataField($driver, $fieldName): bool
    {
        $voiceField = strtolower(trim($this->getVoiceFieldForDriver($driver)));
        $fieldName = strtolower(trim(strval($fieldName)));
        return $voiceField !== '' && $fieldName !== '' && $fieldName === $voiceField;
    }

    public function stripVoiceMetadataForDriver($driver, array $metadata): array
    {
        if (empty($metadata)) {
            return $metadata;
        }

        foreach (array_keys($metadata) as $fieldName) {
            if ($this->isDriverVoiceMetadataField($driver, $fieldName)) {
                unset($metadata[$fieldName]);
            }
        }

        return $metadata;
    }

    public function applyForcedMetadataDefaults($driver, array $metadata): array
    {
        $driver = $this->normalizeDriver($driver);
        $metadata = $this->mergeMissingMetadataDefaults($metadata, self::$sharedMetadataDefaultMap);
        $metadata = $this->mergeMissingMetadataDefaults($metadata, self::$metadataDefaultMap[$driver] ?? []);
        if (in_array($driver, ['xtts-fastapi', 'chatterbox', 'pockettts', 'omnivoice'], true)) {
            $metadata['voicelogic'] = 'voicetype';
        }

        return $metadata;
    }

    public function getConnectorMetadataFieldSchema(): array
    {
        return [
            'fallback_male' => [
                'type' => 'string',
                'description' => 'NPC male fallback VoiceID if the NPC voice is blank or the provider rejects it.',
            ],
            'fallback_female' => [
                'type' => 'string',
                'description' => 'NPC female fallback VoiceID if the NPC voice is blank or the provider rejects it.',
            ],
        ];
    }

    public function getFallbackVoiceForGender($connectorData, $gender): string
    {
        $metadata = $this->applyForcedMetadataDefaults(
            is_array($connectorData) ? ($connectorData['driver'] ?? '') : '',
            $this->decodeMetadata(is_array($connectorData) ? ($connectorData['metadata'] ?? '{}') : '{}')
        );

        if ($this->isFemaleGender($gender)) {
            return $this->resolveFallbackVoiceMetadata(
                $metadata['fallback_female'] ?? null,
                self::$sharedMetadataDefaultMap['fallback_female']
            );
        }

        return $this->resolveFallbackVoiceMetadata(
            $metadata['fallback_male'] ?? null,
            self::$sharedMetadataDefaultMap['fallback_male']
        );
    }

    private function resolveFallbackVoiceMetadata($value, string $default): string
    {
        // Legacy seed rows stored field schemas instead of resolved values.
        if (is_array($value)) {
            $value = $value['default'] ?? $default;
        }

        return is_scalar($value) ? trim(strval($value)) : $default;
    }

    public function resolveNpcVoiceForConnector(array $currentNpcData, $connectorData = null): array
    {
        $originalVoice = trim(strval($currentNpcData['voiceid'] ?? ''));
        $raceFallbackVoice = (new TTSFallback())->getVoice(
            $currentNpcData['race'] ?? '',
            $currentNpcData['gender'] ?? ''
        );
        $connectorFallbackVoice = $this->getFallbackVoiceForGender(
            $connectorData,
            $currentNpcData['gender'] ?? ''
        );

        $fallbackVoices = [];
        foreach ([$raceFallbackVoice, $connectorFallbackVoice] as $candidate) {
            $candidate = trim(strval($candidate));
            if ($candidate === '' || strcasecmp($candidate, $originalVoice) === 0) {
                continue;
            }
            $alreadyAdded = array_filter(
                $fallbackVoices,
                fn($voice) => strcasecmp($voice, $candidate) === 0
            );
            if (empty($alreadyAdded)) {
                $fallbackVoices[] = $candidate;
            }
        }

        $fallbackVoice = $fallbackVoices[0] ?? '';
        $resolvedVoice = $originalVoice !== '' ? $originalVoice : $fallbackVoice;

        return [
            'original_voice' => $originalVoice,
            'fallback_voice' => $fallbackVoice,
            'fallback_voices' => $fallbackVoices,
            'race_fallback_voice' => $raceFallbackVoice,
            'connector_fallback_voice' => $connectorFallbackVoice,
            'resolved_voice' => $resolvedVoice,
            'used_fallback' => ($originalVoice === '' && $fallbackVoice !== ''),
        ];
    }

    public function driverSupportsLanguageOverride($driver): bool
    {
        $driver = $this->normalizeDriver($driver);
        return in_array($driver, ['pockettts', 'chatterbox', 'xtts-fastapi', 'omnivoice', 'inworld', 'cartesia', 'piper-tts', 'xvasynth', 'melotts', 'zonos_gradio'], true);
    }

    public function getProviderFieldSchema($driver): array
    {
        $providerKey = $this->getProviderKeyFromDriver($driver);
        if ($providerKey === '') {
            return [];
        }

        $schema = $this->loadRawSchema();
        $providerSchema = $schema['TTS'][$providerKey] ?? [];
        return is_array($providerSchema) ? $providerSchema : [];
    }

    public function getProviderTitle($driver): string
    {
        $providerKey = $this->getProviderKeyFromDriver($driver);
        $schema = $this->getProviderFieldSchema($driver);
        if (!empty($schema['_title'])) {
            return strval($schema['_title']);
        }
        if ($providerKey !== '') {
            return $providerKey;
        }
        return $this->getDisplayName($driver);
    }

    public function driverUsesApiBadge($driver): bool
    {
        $providerKey = $this->getProviderKeyFromDriver($driver);
        return $providerKey !== '' && $this->getApiBadgeLabelForProvider($providerKey) !== '';
    }

    public function getDefaultApiBadgeIdForDriver($driver): int
    {
        $providerKey = $this->getProviderKeyFromDriver($driver);
        $badgeLabel = $this->getApiBadgeLabelForProvider($providerKey);
        if ($badgeLabel === '') {
            return 0;
        }

        if (!class_exists('ApiBadge')) {
            require_once(__DIR__ . DIRECTORY_SEPARATOR . "api_badge.class.php");
        }

        $apiBadge = new ApiBadge();
        $badge = $apiBadge->getByLabel($badgeLabel);
        return $badge ? intval($badge['id'] ?? 0) : 0;
    }

    public function driverSupportsEditableUrl($driver): bool
    {
        $driver = $this->normalizeDriver($driver);
        return array_key_exists($driver, self::$localUrlDefaultMap);
    }

    public function getDefaultUrlForDriver($driver): string
    {
        $driver = $this->normalizeDriver($driver);
        if (!$this->driverSupportsEditableUrl($driver)) {
            return '';
        }

        $providerKey = $this->getProviderKeyFromDriver($driver);
        if ($providerKey !== '' && isset($GLOBALS["TTS"][$providerKey]) && is_array($GLOBALS["TTS"][$providerKey])) {
            $resolved = $this->resolveUrlFromMetadata($GLOBALS["TTS"][$providerKey]);
            if ($resolved !== '') {
                return $resolved;
            }
        }

        return self::$localUrlDefaultMap[$driver] ?? '';
    }

    public function resolveConnectorUrl($connectorData): string
    {
        if (!is_array($connectorData)) {
            return '';
        }

        $driver = $connectorData['driver'] ?? '';
        $metadata = $this->decodeMetadata($connectorData['metadata'] ?? '{}');
        if ($this->isLegacySeedRow($connectorData)) {
            $metadata = [];
        }

        return trim(strval($this->normalizeUrlForDriver($driver, $connectorData['url'] ?? null, $metadata) ?? ''));
    }

    public function decodeMetadata($raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }
        $decoded = json_decode(strval($raw ?? '{}'), true);
        return is_array($decoded) ? $decoded : [];
    }

    public function isLegacySeedRow($row): bool
    {
        if (!$row) {
            return false;
        }

        $metadata = $this->decodeMetadata($row['metadata'] ?? '{}');
        foreach ($metadata as $value) {
            if (is_array($value) && (isset($value['type']) || isset($value['values']) || isset($value['description']))) {
                return true;
            }
        }

        return false;
    }

    public function uniqueLabel(string $base, int $excludeId = 0): string
    {
        $base = trim($base);
        if ($base === '') {
            $base = 'TTS Connector';
        }

        $rows = $this->readAll();
        $used = [];
        foreach ($rows as $row) {
            $id = intval($row['id'] ?? 0);
            if ($excludeId > 0 && $id === $excludeId) {
                continue;
            }
            $used[strtolower(trim(strval($row['label'] ?? '')))] = true;
        }

        if (!isset($used[strtolower($base)])) {
            return $base;
        }

        for ($i = 2; $i < 5000; $i++) {
            $candidate = $base . ' ' . $i;
            if (!isset($used[strtolower($candidate)])) {
                return $candidate;
            }
        }

        return $base . ' ' . time();
    }

    public function buildMetadataFromPostedForm($driver, array $source, array $existingMetadata = []): array
    {
        $schema = $this->getProviderFieldSchema($driver);
        $metadata = $this->applyForcedMetadataDefaults(
            $driver,
            $this->stripVoiceMetadataForDriver($driver, $existingMetadata)
        );
        foreach ($schema as $fieldName => $definition) {
            if (!is_array($definition) || $fieldName === '_title') {
                continue;
            }

            $postKey = 'meta__' . $fieldName;
            if (!array_key_exists($postKey, $source) && (($definition['type'] ?? '') !== 'boolean')) {
                continue;
            }

            $type = $definition['type'] ?? 'string';
            if ($type === 'boolean') {
                $metadata[$fieldName] = isset($source[$postKey]) && strval($source[$postKey]) === 'true';
            } elseif ($type === 'integer') {
                $raw = trim(strval($source[$postKey] ?? ''));
                $metadata[$fieldName] = ($raw === '') ? 0 : intval($raw);
            } elseif ($type === 'number') {
                $raw = trim(strval($source[$postKey] ?? ''));
                $metadata[$fieldName] = ($raw === '') ? 0 : floatval($raw);
            } elseif ($type === 'selectmultiple') {
                $metadata[$fieldName] = isset($source[$postKey]) && is_array($source[$postKey]) ? array_values($source[$postKey]) : [];
            } else {
                $metadata[$fieldName] = is_array($source[$postKey] ?? null) ? [] : trim(strval($source[$postKey] ?? ''));
            }
        }

        return $this->applyForcedMetadataDefaults(
            $driver,
            $this->stripVoiceMetadataForDriver($driver, $metadata)
        );
    }

    public function ensureLegacyConnectorMigration(bool $assignNullProfiles = true, int $preferredId = 0)
    {
        $row = $this->upsertLegacySelectionFromGlobals($preferredId);
        if ($row && $assignNullProfiles) {
            $this->assignConnectorToNullProfiles(intval($row['id'] ?? 0));
        }
        return $row;
    }

    public function ensureConnectorForProfile(array $currentProfileData = [])
    {
        $connectorId = intval($currentProfileData['tts_connector_id'] ?? 0);
        return $connectorId > 0 ? $this->getById($connectorId) : null;
    }

    public function assignConnectorToNullProfiles(int $connectorId): void
    {
        if ($connectorId <= 0) {
            return;
        }
        $GLOBALS["db"]->query("UPDATE core_profiles SET tts_connector_id = {$connectorId} WHERE tts_connector_id IS NULL");
    }

    public function importLegacyPlayerSettings(bool $force = false): void
    {
        if (!class_exists('Player')) {
            require_once(__DIR__ . DIRECTORY_SEPARATOR . "player.class.php");
        }

        $player = new Player();
        $legacyDriver = $this->normalizeDriver($GLOBALS["TTSFUNCTION_PLAYER"] ?? 'none');
        $legacyConnector = null;

        if ($legacyDriver !== '' && $legacyDriver !== 'none') {
            $originalDriver = $GLOBALS["TTSFUNCTION"] ?? null;
            $GLOBALS["TTSFUNCTION"] = $legacyDriver;
            $legacyConnector = $this->upsertLegacySelectionFromGlobals(0, 'Player ' . $this->getDisplayName($legacyDriver));
            if ($originalDriver === null) {
                unset($GLOBALS["TTSFUNCTION"]);
            } else {
                $GLOBALS["TTSFUNCTION"] = $originalDriver;
            }
        }

        $currentConnectorId = trim(strval($player->get('tts_connector_id') ?? ''));
        if (($force || $currentConnectorId === '') && $legacyConnector && intval($legacyConnector['id'] ?? 0) > 0) {
            $player->set('tts_connector_id', strval(intval($legacyConnector['id'])));
        }

        if ($legacyDriver !== 'none') {
            $voiceOverride = strval($GLOBALS["TTSFUNCTION_PLAYER_VOICE"] ?? '');
            $voiceIdOverride = strval($GLOBALS["TTSFUNCTION_PLAYER_VOICE_ID"] ?? '');
            $languageOverride = strval($GLOBALS["TTSFUNCTION_PLAYER_LANGUAGE"] ?? '');

            if ($force || $player->get('tts_voice_override') === null) {
                $player->set('tts_voice_override', $voiceOverride);
            }
            if ($force || $player->get('tts_voice_id_override') === null) {
                $player->set('tts_voice_id_override', $voiceIdOverride);
            }
            if ($force || $player->get('tts_language_override') === null) {
                $player->set('tts_language_override', $languageOverride);
            }
        }
    }

    public function setOldGlobals($currentTTSData)
    {
        if (!$currentTTSData) {
            return;
        }

        $GLOBALS["CHIM_CORE_CURRENT_TTS_CONNECTOR_ID"] = intval($currentTTSData['id'] ?? 0);

        $driver = $this->normalizeDriver($currentTTSData["driver"] ?? '');
        if ($driver === '') {
            $GLOBALS["TTSFUNCTION"] = 'none';
            $GLOBALS["TTS_FUNCTION"] = 'none';
            return;
        }

        $GLOBALS["TTSFUNCTION"] = $driver;
        $GLOBALS["TTS_FUNCTION"] = $driver;

        $providerKey = $this->getProviderKeyFromDriver($driver);
        if ($providerKey === '') {
            return;
        }

        if (!isset($GLOBALS["TTS"]) || !is_array($GLOBALS["TTS"])) {
            $GLOBALS["TTS"] = [];
        }
        $GLOBALS["TTS"][$providerKey] = [];

        $metadata = $this->decodeMetadata($currentTTSData['metadata'] ?? '{}');
        if ($this->isLegacySeedRow($currentTTSData)) {
            $metadata = [];
        }
        $metadata = $this->applyForcedMetadataDefaults(
            $driver,
            $this->stripVoiceMetadataForDriver($driver, $metadata)
        );

        $resolvedUrl = $this->resolveRuntimeUrlForDriver($driver, $currentTTSData["url"] ?? null, $metadata);
        if ($resolvedUrl !== null && $resolvedUrl !== '') {
            $metadata['endpoint'] = $resolvedUrl;
            $metadata['url'] = $resolvedUrl;
            $metadata['URL'] = $resolvedUrl;
            if ($driver === 'pockettts' && preg_match('/\:8086(?:\/|$)/', $resolvedUrl)) {
                if (!is_scalar($metadata['model'] ?? null) || trim(strval($metadata['model'] ?? '')) === '') {
                    $metadata['model'] = 'pocket-tts';
                }
            }
        }

        $apiBadgeId = intval($currentTTSData['api_badge_id'] ?? 0);
        if ($apiBadgeId > 0) {
            if (!class_exists('ApiBadge')) {
                require_once(__DIR__ . DIRECTORY_SEPARATOR . "api_badge.class.php");
            }
            $apiBadge = new ApiBadge();
            $apiKeyData = $apiBadge->getById($apiBadgeId);
            $apiKey = trim(strval($apiKeyData['api_key'] ?? ''));
            if ($apiKey !== '') {
                $metadata['API_KEY'] = $apiKey;
            }
        }

        foreach ($metadata as $key => $value) {
            if ($value === '' || $value === null) {
                continue;
            }
            $GLOBALS["TTS"][$providerKey][$key] = $value;
        }

        if (!empty($GLOBALS["TTS"][$providerKey]['language']) && !empty($GLOBALS["LANG_LLM_XTTS"])) {
            $GLOBALS["LLM_LANG"] = preg_replace('/[^a-z\-]/i', '', strtolower(trim(strval($GLOBALS["TTS"][$providerKey]['language']))));
        }
    }

    private function normalizeDriver($driver): string
    {
        $driver = strtolower(trim(strval($driver)));
        if ($driver === 'xtts') {
            return 'xtts-fastapi';
        }
        return $driver;
    }

    private function mergeMissingMetadataDefaults(array $metadata, array $defaults): array
    {
        if (empty($defaults)) {
            return $metadata;
        }

        foreach ($defaults as $key => $value) {
            if (!array_key_exists($key, $metadata) || $this->isMetadataValueMissing($metadata[$key])) {
                $metadata[$key] = $value;
            }
        }

        return $metadata;
    }

    private function isMetadataValueMissing($value): bool
    {
        if ($value === null) {
            return true;
        }
        if (is_string($value)) {
            return trim($value) === '';
        }
        if (is_array($value)) {
            return empty($value);
        }
        return false;
    }

    private function isFemaleGender($gender): bool
    {
        $gender = strtolower(trim(strval($gender)));
        return in_array($gender, ['f', 'female', 'woman', 'girl'], true);
    }

    private function loadRawSchema(): array
    {
        static $schema = null;
        if (is_array($schema)) {
            return $schema;
        }

        $schemaPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . "conf" . DIRECTORY_SEPARATOR . "conf_schema.json";
        $decoded = @json_decode(@file_get_contents($schemaPath), true);
        $schema = is_array($decoded) ? $decoded : [];
        return $schema;
    }

    private function canonicalize($value)
    {
        if (!is_array($value)) {
            return $value;
        }

        $isAssoc = array_keys($value) !== range(0, count($value) - 1);
        if ($isAssoc) {
            ksort($value);
        }
        foreach ($value as $key => $item) {
            $value[$key] = $this->canonicalize($item);
        }
        return $value;
    }

    private function canonicalJson($value): string
    {
        return json_encode($this->canonicalize($value), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function resolveUrlFromMetadata(array $metadata): string
    {
        foreach (['endpoint', 'url', 'URL'] as $key) {
            if (!array_key_exists($key, $metadata)) {
                continue;
            }

            $candidate = $this->resolveUrlCandidateValue($metadata[$key]);
            if ($candidate !== '') {
                return $candidate;
            }
        }
        return '';
    }

    private function resolveUrlCandidateValue($value): string
    {
        if (is_scalar($value)) {
            return trim(strval($value));
        }

        if (!is_array($value)) {
            return '';
        }

        foreach (['endpoint', 'url', 'URL', 'value', 'default'] as $key) {
            if (!array_key_exists($key, $value)) {
                continue;
            }

            $candidate = $this->resolveUrlCandidateValue($value[$key]);
            if ($candidate !== '') {
                return $candidate;
            }
        }

        return '';
    }

    private function getApiBadgeLabelForProvider(string $providerKey): string
    {
        return self::$apiBadgeLabelMap[$providerKey] ?? '';
    }

    private function resolveApiBadgeIdForMetadata(string $providerKey, array &$metadata): int
    {
        $badgeLabel = $this->getApiBadgeLabelForProvider($providerKey);
        if ($badgeLabel === '') {
            return 0;
        }

        if (!class_exists('ApiBadge')) {
            require_once(__DIR__ . DIRECTORY_SEPARATOR . "api_badge.class.php");
        }

        $apiKey = trim(strval($metadata['API_KEY'] ?? ''));
        $apiBadge = new ApiBadge();
        $badge = $apiBadge->getByLabel($badgeLabel);

        if (!$badge && $apiKey !== '') {
            $apiBadge->create([
                'label' => $badgeLabel,
                'api_key' => $apiKey,
            ]);
            $badge = $apiBadge->getByLabel($badgeLabel);
        } elseif ($badge && trim(strval($badge['api_key'] ?? '')) === '' && $apiKey !== '') {
            $apiBadge->update(intval($badge['id'] ?? 0), ['api_key' => $apiKey]);
            $badge = $apiBadge->getById(intval($badge['id'] ?? 0));
        }

        if ($badge && intval($badge['id'] ?? 0) > 0) {
            unset($metadata['API_KEY']);
            return intval($badge['id']);
        }

        return 0;
    }

    private function normalizeUrlForDriver($driver, $url, array $metadata = []): ?string
    {
        $driver = $this->normalizeDriver($driver);
        if (!$this->driverSupportsEditableUrl($driver)) {
            return null;
        }

        $candidate = trim(strval($url ?? ''));
        if ($candidate === '') {
            $candidate = $this->resolveUrlFromMetadata($metadata);
        }
        if ($candidate === '') {
            $candidate = $this->getDefaultUrlForDriver($driver);
        }

        return $candidate !== '' ? $candidate : null;
    }

    private function resolveRuntimeUrlForDriver($driver, $url, array $metadata = []): ?string
    {
        return $this->normalizeUrlForDriver($driver, $url, $metadata);
    }

    private function normalizeApiBadgeIdForDriver($driver, $apiBadgeId): ?int
    {
        $driver = $this->normalizeDriver($driver);
        if (!$this->driverUsesApiBadge($driver)) {
            return null;
        }

        $currentId = intval($apiBadgeId ?? 0);
        if ($currentId > 0) {
            return $currentId;
        }

        $defaultId = $this->getDefaultApiBadgeIdForDriver($driver);
        return $defaultId > 0 ? $defaultId : null;
    }

    private function buildLegacyConnectorPayloadFromGlobals($driver = null, $label = null): ?array
    {
        $driver = $this->normalizeDriver($driver ?? ($GLOBALS["TTSFUNCTION"] ?? 'none'));
        if ($driver === '') {
            $driver = 'none';
        }

        $providerKey = $this->getProviderKeyFromDriver($driver);
        $metadata = [];
        if ($providerKey !== '' && isset($GLOBALS["TTS"][$providerKey]) && is_array($GLOBALS["TTS"][$providerKey])) {
            $metadata = $GLOBALS["TTS"][$providerKey];
        }

        $metadata = $this->applyForcedMetadataDefaults(
            $driver,
            $this->stripVoiceMetadataForDriver($driver, $this->decodeMetadata($metadata))
        );
        $url = $this->resolveUrlFromMetadata($metadata);
        $apiBadgeId = $providerKey !== '' ? $this->resolveApiBadgeIdForMetadata($providerKey, $metadata) : 0;

        if ($label === null || trim(strval($label)) === '') {
            $label = ($driver === 'none')
                ? 'Disabled TTS'
                : ('Default ' . $this->getDisplayName($driver));
        }

        return [
            'driver' => $driver,
            'label' => trim(strval($label)),
            'metadata' => $this->canonicalJson($metadata),
            'api_badge_id' => $apiBadgeId > 0 ? $apiBadgeId : null,
            'url' => $this->normalizeUrlForDriver($driver, $url, $metadata),
            'voice_field' => $driver === 'none' ? null : $this->getVoiceFieldForDriver($driver),
        ];
    }

    private function findMatchingConnector(array $payload)
    {
        $rows = $this->readAll();
        foreach ($rows as $row) {
            if ($this->normalizeDriver($row['driver'] ?? '') !== $this->normalizeDriver($payload['driver'] ?? '')) {
                continue;
            }
            if (trim(strval($row['url'] ?? '')) !== trim(strval($payload['url'] ?? ''))) {
                continue;
            }
            if (intval($row['api_badge_id'] ?? 0) !== intval($payload['api_badge_id'] ?? 0)) {
                continue;
            }
            if (trim(strval($row['voice_field'] ?? '')) !== trim(strval($payload['voice_field'] ?? ''))) {
                continue;
            }

            $rowMetadata = $this->canonicalJson(
                $this->applyForcedMetadataDefaults(
                    $row['driver'] ?? '',
                    $this->stripVoiceMetadataForDriver(
                        $row['driver'] ?? '',
                        $this->decodeMetadata($row['metadata'] ?? '{}')
                    )
                )
            );
            if ($rowMetadata === strval($payload['metadata'] ?? '{}')) {
                return $row;
            }
        }

        return null;
    }

    private function findLegacySeedCandidate(int $preferredId = 0)
    {
        if ($preferredId > 0) {
            $preferred = $this->getById($preferredId);
            if ($preferred && $this->isLegacySeedRow($preferred)) {
                return $preferred;
            }
        }

        foreach ($this->readAll() as $row) {
            if ($this->isLegacySeedRow($row)) {
                return $row;
            }
        }

        return null;
    }

    private function upsertLegacySelectionFromGlobals(int $preferredId = 0, string $label = '')
    {
        $payload = $this->buildLegacyConnectorPayloadFromGlobals(null, $label !== '' ? $label : null);
        if (!$payload) {
            return null;
        }

        if ($preferredId > 0) {
            $preferred = $this->getById($preferredId);
            if ($preferred && $this->isLegacySeedRow($preferred)) {
                $payload['label'] = $this->uniqueLabel($payload['label'], $preferredId);
                $this->update($preferredId, $payload);
                return $this->getById($preferredId);
            }
        }

        $match = $this->findMatchingConnector($payload);
        if ($match) {
            return $match;
        }

        $legacySeed = $this->findLegacySeedCandidate($preferredId);
        if ($legacySeed) {
            $legacyId = intval($legacySeed['id'] ?? 0);
            $payload['label'] = $this->uniqueLabel($payload['label'], $legacyId);
            $this->update($legacyId, $payload);
            return $this->getById($legacyId);
        }

        $payload['label'] = $this->uniqueLabel($payload['label'], 0);
        $newId = $this->create($payload);
        return $newId > 0 ? $this->getById($newId) : null;
    }

    private function sanitizeStoredRowVoiceMetadata($row)
    {
        if (!$row || !array_key_exists('metadata', $row)) {
            return $row;
        }

        $driver = $row['driver'] ?? '';
        $metadata = $this->decodeMetadata($row['metadata'] ?? '{}');
        $sanitizedMetadata = $this->applyForcedMetadataDefaults(
            $driver,
            $this->stripVoiceMetadataForDriver($driver, $metadata)
        );
        $normalizedApiBadgeId = $this->normalizeApiBadgeIdForDriver($driver, $row['api_badge_id'] ?? null);
        $normalizedUrl = $this->normalizeUrlForDriver($driver, $row['url'] ?? null, $sanitizedMetadata);
        $currentUrl = array_key_exists('url', $row) ? $row['url'] : null;
        if ($this->canonicalJson($metadata) === $this->canonicalJson($sanitizedMetadata)
            && intval($row['api_badge_id'] ?? 0) === intval($normalizedApiBadgeId ?? 0)
            && trim(strval($currentUrl ?? '')) === trim(strval($normalizedUrl ?? ''))) {
            return $row;
        }

        $row['metadata'] = $this->canonicalJson($sanitizedMetadata);
        $row['api_badge_id'] = $normalizedApiBadgeId;
        $row['url'] = $normalizedUrl;
        $id = intval($row['id'] ?? 0);
        if ($id > 0) {
            $this->update($id, [
                'metadata' => $row['metadata'],
                'driver' => $driver,
                'label' => $row['label'] ?? '',
                'api_badge_id' => $normalizedApiBadgeId,
                'url' => $normalizedUrl,
                'voice_field' => $row['voice_field'] ?? null,
            ]);
        }

        return $row;
    }
}

?>
