<?php

class ProfileLLMMode
{
    public const RANDOMIZER_KEY = 'LLM_RANDOMIZER_ENABLED';

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

    public static function getConfiguredSlots(array $profileData): array
    {
        $slotFields = [
            1 => 'llm_primary_id',
            2 => 'llm_secondary_id',
            3 => 'llm_tertiary_id',
            4 => 'llm_quaternary_id',
        ];

        $configured = [];
        foreach ($slotFields as $slot => $field) {
            if (intval($profileData[$field] ?? 0) > 0) {
                $configured[] = $slot;
            }
        }

        return $configured;
    }
}
