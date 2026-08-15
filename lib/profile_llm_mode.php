<?php

class ProfileLLMMode
{
    public const RANDOMIZER_KEY = 'LLM_RANDOMIZER_ENABLED';
    public const PROFILE_DEFAULTS = [
        'dynamic_profile' => 'DYNAMIC_PROFILE_ENABLED',
        'middle_term_memory' => 'MIDDLE_TERM_MEMORY_ENABLED',
        'auto_diary' => 'AUTO_DIARY_ENABLED',
        'auto_diary_wait' => 'AUTO_DIARY_WAIT_ENABLED',
        'physical_diary' => 'MATERIALIZE_DIARY_ENABLED',
    ];
    public const CONNECTOR_SLOTS = [
        1 => ['key' => 'standard', 'label' => 'Standard', 'field' => 'llm_primary_id'],
        2 => ['key' => 'fast', 'label' => 'Fast', 'field' => 'llm_secondary_id'],
        3 => ['key' => 'powerful', 'label' => 'Powerful', 'field' => 'llm_tertiary_id'],
        4 => ['key' => 'experimental', 'label' => 'Experimental', 'field' => 'llm_quaternary_id'],
    ];

    public static function isTruthy($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return ((int)$value) === 1;
        }

        return in_array(strtolower(trim((string)$value)), ['1', 'true', 'yes', 'on'], true);
    }

    public static function decodeMetadata($metadata): array
    {
        if (is_array($metadata)) {
            return $metadata;
        }

        if ($metadata === null || $metadata === '') {
            return [];
        }

        $decoded = json_decode((string)$metadata, true);
        return is_array($decoded) ? $decoded : [];
    }

    public static function isRandomEnabled(array $profileData): bool
    {
        $metadata = self::decodeMetadata($profileData['metadata'] ?? null);
        return self::isTruthy($metadata[self::RANDOMIZER_KEY] ?? false);
    }

    public static function updateRandomEnabledMetadata($metadata, bool $enabled): string
    {
        $decoded = self::decodeMetadata($metadata);
        $decoded[self::RANDOMIZER_KEY] = $enabled;
        return json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public static function getProfileDefaults(array $profileData): array
    {
        $metadata = self::decodeMetadata($profileData['metadata'] ?? null);
        $defaults = [];
        foreach (self::PROFILE_DEFAULTS as $setting => $metadataKey) {
            $defaults[$setting] = self::isTruthy($metadata[$metadataKey] ?? false);
        }

        return $defaults;
    }

    public static function updateProfileDefaultMetadata($metadata, string $setting, bool $enabled): string
    {
        $metadataKey = self::PROFILE_DEFAULTS[$setting] ?? null;
        if ($metadataKey === null) {
            throw new InvalidArgumentException('Unsupported profile default setting.');
        }

        $decoded = self::decodeMetadata($metadata);
        $decoded[$metadataKey] = $enabled;
        return json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public static function getConfiguredSlots(array $profileData): array
    {
        $configured = [];
        foreach (self::CONNECTOR_SLOTS as $slot => $definition) {
            if (intval($profileData[$definition['field']] ?? 0) > 0) {
                $configured[] = $slot;
            }
        }

        return $configured;
    }

    public static function getConfiguredConnectors(array $profileData): array
    {
        $configured = [];
        foreach (self::CONNECTOR_SLOTS as $slot => $definition) {
            $connectorId = intval($profileData[$definition['field']] ?? 0);
            if ($connectorId <= 0) {
                continue;
            }

            $configured[] = [
                'slot' => $slot,
                'key' => $definition['key'],
                'label' => $definition['label'],
                'connector_id' => $connectorId,
            ];
        }

        return $configured;
    }
}
