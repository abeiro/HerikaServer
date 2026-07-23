<?php

require_once(__DIR__ . DIRECTORY_SEPARATOR . 'settings.php');

if (!function_exists('chimEnsureVisualContextTable')) {
    function chimEnsureVisualContextTable(): bool
    {
        static $ready = false;
        if ($ready) {
            return true;
        }

        $db = $GLOBALS['db'] ?? null;
        if (!$db) {
            return false;
        }

        $ready = $db->execQuery(<<<'SQL'
CREATE TABLE IF NOT EXISTS public.visual_context (
    id BIGSERIAL PRIMARY KEY,
    subject_type TEXT NOT NULL DEFAULT 'scene',
    subject_key TEXT NOT NULL,
    subject_name TEXT NOT NULL DEFAULT '',
    plugin TEXT NOT NULL DEFAULT '',
    baseid TEXT NOT NULL DEFAULT '',
    refid TEXT NOT NULL DEFAULT '',
    cell_id TEXT NOT NULL DEFAULT '',
    location_name TEXT NOT NULL DEFAULT '',
    image_path TEXT NOT NULL DEFAULT '',
    image_sha256 TEXT NOT NULL DEFAULT '',
    description TEXT NOT NULL DEFAULT '',
    perspective TEXT NOT NULL DEFAULT 'first_person',
    provider TEXT NOT NULL DEFAULT '',
    model TEXT NOT NULL DEFAULT '',
    metadata JSONB NOT NULL DEFAULT '{}'::jsonb,
    locked BOOLEAN NOT NULL DEFAULT FALSE,
    active BOOLEAN NOT NULL DEFAULT TRUE,
    user_edited BOOLEAN NOT NULL DEFAULT FALSE,
    captured_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS visual_context_location_idx
    ON public.visual_context (LOWER(location_name), active, captured_at DESC);
CREATE INDEX IF NOT EXISTS visual_context_subject_idx
    ON public.visual_context (subject_type, subject_key, active, captured_at DESC);
CREATE INDEX IF NOT EXISTS visual_context_image_idx
    ON public.visual_context (image_sha256);
SQL) !== false;

        return $ready;
    }
}

if (!function_exists('chimVisualContextSubjectType')) {
    function chimVisualContextSubjectType($value): string
    {
        $value = strtolower(trim(strval($value)));
        $allowed = ['scene', 'location', 'actor', 'player', 'item', 'furniture', 'object'];
        return in_array($value, $allowed, true) ? $value : 'scene';
    }
}

if (!function_exists('chimVisualContextText')) {
    function chimVisualContextText($value, int $maxLength = 500): string
    {
        if (is_array($value) || is_object($value)) {
            $value = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
        $value = trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', strval($value)) ?? '');
        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $maxLength);
        }
        return substr($value, 0, $maxLength);
    }
}

if (!function_exists('chimVisualContextLocationBase')) {
    function chimVisualContextLocationBase($value): string
    {
        $value = html_entity_decode(chimVisualContextText($value, 1000), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($value === '') {
            return '';
        }

        if (preg_match('/Context\s*(?:new\s*)?location:\s*([^,\)]+)/iu', $value, $matches)) {
            $value = $matches[1];
        } else {
            $value = preg_split('/,\s*Hold\s*:/iu', $value, 2)[0] ?? $value;
            $value = preg_replace('/^(?:Location|Context\s*(?:new\s*)?location):\s*/iu', '', $value) ?? $value;
        }

        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        return chimVisualContextText(trim($value, " \t\n\r\0\x0B,()"), 300);
    }
}

if (!function_exists('chimVisualContextFilenamePart')) {
    function chimVisualContextFilenamePart($value, string $fallback, int $maxLength = 120): string
    {
        $value = html_entity_decode(chimVisualContextText($value, 500), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (function_exists('iconv')) {
            $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
            if ($transliterated !== false) {
                $value = $transliterated;
            }
        }

        $value = preg_replace('/[^A-Za-z0-9]+/', '_', $value) ?? '';
        $value = trim($value, '_');
        if ($value === '') {
            $value = $fallback;
        }

        return substr($value, 0, max(1, $maxLength));
    }
}

if (!function_exists('chimVisualContextGalleryFilename')) {
    function chimVisualContextGalleryFilename($location, $gameDate, string $extension = 'jpg'): string
    {
        $locationPart = chimVisualContextFilenamePart(chimVisualContextLocationBase($location), 'Unknown_Location', 100);
        $datePart = chimVisualContextFilenamePart($gameDate, 'Unknown_Skyrim_Time', 140);
        $extension = strtolower(preg_replace('/[^A-Za-z0-9]/', '', $extension) ?? 'jpg');
        if ($extension === '') {
            $extension = 'jpg';
        }

        return $locationPart . '__' . $datePart . '.' . $extension;
    }
}

if (!function_exists('chimVisualContextStore')) {
    function chimVisualContextStore(array $record): bool
    {
        $db = $GLOBALS['db'] ?? null;
        if (!$db || !chimEnsureVisualContextTable()) {
            return false;
        }

        $subjectType = chimVisualContextSubjectType($record['subject_type'] ?? 'scene');
        $location = chimVisualContextText($record['location_name'] ?? '', 300);
        $subjectName = chimVisualContextText($record['subject_name'] ?? '', 300);
        $subjectKey = chimVisualContextText($record['subject_key'] ?? '', 500);
        if ($subjectKey === '') {
            $subjectKey = $subjectType . ':' . strtolower($subjectName !== '' ? $subjectName : $location);
        }

        $metadata = $record['metadata'] ?? [];
        if (!is_array($metadata)) {
            $metadata = [];
        }
        $metadataJson = json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($metadataJson === false) {
            $metadataJson = '{}';
        }

        $query = "INSERT INTO public.visual_context (
                subject_type, subject_key, subject_name, plugin, baseid, refid, cell_id,
                location_name, image_path, image_sha256, description, perspective,
                provider, model, metadata, locked, active, user_edited
            ) VALUES (" . implode(', ', [
                $db->escapeLiteral($subjectType),
                $db->escapeLiteral($subjectKey),
                $db->escapeLiteral($subjectName),
                $db->escapeLiteral(chimVisualContextText($record['plugin'] ?? '', 255)),
                $db->escapeLiteral(chimVisualContextText($record['baseid'] ?? '', 32)),
                $db->escapeLiteral(chimVisualContextText($record['refid'] ?? '', 32)),
                $db->escapeLiteral(chimVisualContextText($record['cell_id'] ?? '', 32)),
                $db->escapeLiteral($location),
                $db->escapeLiteral(chimVisualContextText($record['image_path'] ?? '', 1000)),
                $db->escapeLiteral(chimVisualContextText($record['image_sha256'] ?? '', 64)),
                $db->escapeLiteral(chimVisualContextText($record['description'] ?? '', 12000)),
                $db->escapeLiteral(chimVisualContextText($record['perspective'] ?? 'first_person', 50)),
                $db->escapeLiteral(chimVisualContextText($record['provider'] ?? '', 100)),
                $db->escapeLiteral(chimVisualContextText($record['model'] ?? '', 255)),
                $db->escapeLiteral($metadataJson) . '::jsonb',
                !empty($record['locked']) ? 'TRUE' : 'FALSE',
                array_key_exists('active', $record) && empty($record['active']) ? 'FALSE' : 'TRUE',
                !empty($record['user_edited']) ? 'TRUE' : 'FALSE',
            ]) . ')';

        return $db->execQuery($query) !== false;
    }
}

if (!function_exists('chimVisualContextList')) {
    function chimVisualContextList(int $limit = 250): array
    {
        $db = $GLOBALS['db'] ?? null;
        if (!$db || !chimEnsureVisualContextTable()) {
            return [];
        }
        $limit = max(1, min($limit, 1000));
        $rows = $db->fetchAll("SELECT * FROM public.visual_context ORDER BY locked DESC, captured_at DESC LIMIT {$limit}");
        return is_array($rows) ? $rows : [];
    }
}

if (!function_exists('chimVisualContextUpdate')) {
    function chimVisualContextUpdate(int $id, array $values): bool
    {
        $db = $GLOBALS['db'] ?? null;
        if ($id < 1 || !$db || !chimEnsureVisualContextTable()) {
            return false;
        }

        $assignments = [];
        if (array_key_exists('description', $values)) {
            $assignments[] = 'description=' . $db->escapeLiteral(chimVisualContextText($values['description'], 12000));
            $assignments[] = 'user_edited=TRUE';
        }
        foreach (['locked', 'active'] as $booleanKey) {
            if (array_key_exists($booleanKey, $values)) {
                $assignments[] = $booleanKey . '=' . (!empty($values[$booleanKey]) ? 'TRUE' : 'FALSE');
            }
        }
        if (!$assignments) {
            return false;
        }
        $assignments[] = 'updated_at=CURRENT_TIMESTAMP';
        return $db->execQuery('UPDATE public.visual_context SET ' . implode(', ', $assignments) . ' WHERE id=' . intval($id)) !== false;
    }
}

if (!function_exists('chimVisualContextDelete')) {
    function chimVisualContextDelete(int $id): bool
    {
        $db = $GLOBALS['db'] ?? null;
        return $id > 0 && $db && chimEnsureVisualContextTable()
            ? $db->delete('public.visual_context', 'id=' . intval($id)) !== false
            : false;
    }
}

if (!function_exists('chimBuildVisualContextPrompt')) {
    function chimBuildVisualContextPrompt(string $location): string
    {
        $db = $GLOBALS['db'] ?? null;
        $location = trim($location);
        $locationBase = chimVisualContextLocationBase($location);
        if (!$db || $locationBase === '' || !chimEnsureVisualContextTable()) {
            return '';
        }

        $ttlMinutes = max(1, min(chimGetGeneralSettingInt('VISUAL_CONTEXT_SCENE_TTL_MINUTES', 10), 1440));
        $maxChars = max(200, min(chimGetGeneralSettingInt('VISUAL_CONTEXT_PROMPT_MAX_CHARS', 1800), 12000));
        $locationLiteral = $db->escapeLiteral($locationBase);
        $rows = $db->fetchAll("SELECT subject_type, subject_name, description, captured_at
            FROM public.visual_context
            WHERE active = TRUE
              AND description <> ''
              AND LOWER(BTRIM(REGEXP_REPLACE(
                    SPLIT_PART(location_name, ',', 1),
                    '^\\(?context[[:space:]]+(new[[:space:]]+)?location:[[:space:]]*',
                    '',
                    'i'
                  ), ' ()')) = LOWER({$locationLiteral})
              AND (locked = TRUE OR captured_at >= CURRENT_TIMESTAMP - INTERVAL '{$ttlMinutes} minutes')
            ORDER BY locked DESC, user_edited DESC, captured_at DESC
            LIMIT 3");
        if (!is_array($rows) || !$rows) {
            return '';
        }

        $entries = [];
        foreach ($rows as $row) {
            $label = chimVisualContextText($row['subject_name'] ?? '', 120);
            $description = chimVisualContextText($row['description'] ?? '', $maxChars);
            if ($description === '') {
                continue;
            }
            $entries[] = ($label !== '' ? $label . ': ' : '') . $description;
        }
        if (!$entries) {
            return '';
        }

        $body = chimVisualContextText(implode("\n", $entries), $maxChars);
        $body = htmlspecialchars($body, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return "<visual_context>\n# RECENT VISUAL CONTEXT FOR THE CURRENT LOCATION\n{$body}\n</visual_context>";
    }
}
